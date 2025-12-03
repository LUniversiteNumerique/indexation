<?php

namespace App\Command;

use App\Entity\Dto\{Field, Motcle, SuplomDto};
use App\Entity\{Auteur, Dewey, DeweyGroup, DeweyPerso, Discipline, DisciplineGroup, Etablissement, Keyword, Licence, Niveau, Notice, NoticEtat, TDocument, TPedagogie, User};
use App\Service\FileService;
use Symfony\Component\Console\{Attribute\AsCommand, Command\Command, Input\InputArgument, Input\InputInterface, Output\OutputInterface, Style\SymfonyStyle};
use DateTime;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use JMS\Serializer\SerializerInterface;
use SplFileInfo;

/**
 * Commande d'importation des notices suplom à partir de fichiers XML.
 */
#[AsCommand(
    name: 'import:notice-data',
    description: "Exécute le processus d'importation des notices suploms",
)]
class ImportNoticeXmlCommand extends Command
{
    private FileService $fileService;
    /** @var array<string, Licence> */
    private array $licenceRepository;
    /** @var array<string, Niveau> */
    private array $niveauRepository;
    /** @var array<string, TDocument> */
    private array $tdocumentRepository;
    /** @var array<string, TPedagogie> */
    private array $tpedagogiqueRepository;

    private EntityRepository $userRepository;
    private EntityRepository $deweyRepository;
    private EntityRepository $auteurRepository;
    private EntityRepository $keywordRepository;
    private EntityRepository $disciplineRepository;
    private EntityRepository $etablissementRepository;
    private EntityRepository $deweyPersoRepository;

    /**
     * @param EntityManagerInterface $entityManager
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface    $serializer
    ) {
        $this->fileService = new FileService();
        parent::__construct();

        $this->userRepository = $this->entityManager->getRepository(User::class);
        $this->deweyRepository = $this->entityManager->getRepository(Dewey::class);
        $this->deweyPersoRepository = $this->entityManager->getRepository(DeweyPerso::class);
        $this->auteurRepository = $this->entityManager->getRepository(Auteur::class);
        $this->keywordRepository = $this->entityManager->getRepository(Keyword::class);
        $this->disciplineRepository = $this->entityManager->getRepository(Discipline::class);
        $this->etablissementRepository = $this->entityManager->getRepository(Etablissement::class);

        $this->licenceRepository = $this->buildRepositoryMap(Licence::class, fn(Licence $licence) => strtolower(preg_replace('/\s+/', '', $licence->getValeur())));
        $this->niveauRepository = $this->buildRepositoryMap(Niveau::class, fn(Niveau $niveau) => strtolower($niveau->getCode()));
        $this->tdocumentRepository = $this->buildRepositoryMap(TDocument::class, fn(TDocument $tdocument) => strtolower($tdocument->getCode()));
        $this->tpedagogiqueRepository = $this->buildRepositoryMap(TPedagogie::class, fn(TPedagogie $tpedagogie) => strtolower($tpedagogie->getSuplom()));
    }

    /**
     * Construit un tableau associatif à partir d'une entité Doctrine.
     *
     * @template T
     * @param class-string<T> $class
     * @param callable(T): string $keyFunction
     * @return array<string, T>
     */
    private function buildRepositoryMap(string $class, callable $keyFunction): array
    {
        return array_reduce(
            $this->entityManager->getRepository($class)->findAll(),
            function (array $key, $value) use ($keyFunction) {
                return $key + [$keyFunction($value) => $value];
            },
            []
        );
    }

    /**
     * Configure les arguments de la commande.
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Importe les notices suplom (optionnellement avec un préfixe)')
            ->addArgument('prefix', InputArgument::REQUIRED, 'Préfixe des fichiers XML à importer (ex: uoh, unit)')
            ->addArgument('folder', InputArgument::OPTIONAL, 'Dossier des fichiers XML à importer (déf: suplom)', 'suplom')
            ->addArgument('exposed', InputArgument::OPTIONAL, 'Indique si les fichiers doivent être exposés sur le portail (déf: true)', true);
    }

    /**
     * Exécute la commande d'importation.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Suplom Importing');

        $prefix = $input->getArgument('prefix');
        $folder = $input->getArgument('folder');
        $exposed = $input->getArgument('exposed');

        [$mailContributeur, $mailDocumentaliste] = $this->getMailsByPrefix($prefix);

        $finder = $this->fileService->readFilesFrom(null, FileService::REFERENTIELS_DIR . $folder);
        if ($finder === null) {
            return Command::SUCCESS;
        }

        $stats = $this->initStats();
        $io->progressStart(count($finder));

        foreach ($finder as $file) {
            $this->importNoticeFromFile($file, $exposed, $mailContributeur, $mailDocumentaliste, $stats);
            $io->progressAdvance();
        }
        $this->entityManager->flush();

        $this->linkNoticeRelations($stats['relationsToLink']);
        $io->progressFinish();

        $this->afficherListesManquantes($output, $stats);

        $output->writeln("Suplom imported successfully !");
        return Command::SUCCESS;
    }

    /**
     * Retourne les emails contributeur et documentaliste selon le préfixe.
     *
     * @param string $prefix
     * @return array{0: string, 1: string}
     */
    private function getMailsByPrefix(string $prefix): array
    {
        $prefix = strtolower($prefix);
        return match ($prefix) {
            'uoh'   => ['carole.schorle-stefan@unistra.fr', 'juliette.touzene@unistra.fr'],
            'unit'  => ['info@unit.eu', 'contact@unit.eu'],
            'aunge' => ['info@aungee.eu', 'contact@aungee.eu'],
            default => ['', ''],
        };
    }

    /**
     * Initialise les tableaux d'erreurs de l'import et le tableau de liaison des notices.
     *
     * @return array
     */
    private function initStats(): array
    {
        return [
            'notFoundCreateur' => [],
            'notFoundValidateur' => [],
            'notFoundPedagogie' => [],
            'notfoundNiveauEtude' => [],
            'notFoundSpecialites' => [],
            'specialitesWithoutParent' => [],
            'deweyWithoutCode' => [],
            'deweyWithMalformedCode' => [],
            'codeDeweyNameDiff' => [],
            'codeDeweyPersoNameDiff' => [],
            'noticeWithoutPublisher' => [],
            'noticeWithoutRelation' => [],
            'noticeWithoutPublieeLe' => [],
            'noticeWithZIP' => [],
            'noticeWithoutLicence' => [],
            'notFoundPorteur' => [],
            'relationsToLink' => [],
        ];
    }

    /**
     * Importe une notice à partir d'un fichier XML.
     *
     * @param SplFileInfo $file
     * @param bool $exposed
     * @param string $mailContributeur
     * @param string $mailDocumentaliste
     * @param array &$stats
     * @return void
     */
    private function importNoticeFromFile(SplFileInfo $file, bool $exposed, string $mailContributeur, string $mailDocumentaliste, array &$stats): void
    {
        $item = $this->serializer->deserialize($this->fileService->readFile($file->getRealPath()), SuplomDto::class, 'xml');
        $fileName = $file->getFilename();
        $uid = $item->general?->identifier?->entry;
        $existingNotice = $this->entityManager->getRepository(Notice::class)->findOneBy(['uuid' => $uid]);
        if ($existingNotice) {
            return;
        }
        $notice = new Notice();

        $this->handleRelations($item, $uid, $fileName, $stats['relationsToLink'], $stats['noticeWithoutRelation']);
        $roleNotice = SuplomDto::extractRoles($item);

        $this->setDates($notice, $roleNotice, $fileName, $stats['noticeWithoutPublieeLe']);
        $this->setValidateur($notice, $roleNotice, $mailDocumentaliste, $fileName, $stats['notFoundValidateur']);
        $this->setCreateur($notice, $roleNotice, $mailContributeur, $fileName, $stats['notFoundCreateur']);
        $this->setAuteurs($notice, $roleNotice);
        $this->setPorteurs($notice, $roleNotice, $fileName, $stats['notFoundPorteur']);
        $this->setKeywords($notice, $item);
        $this->setTechnicalFields($notice, $item, $exposed, $fileName, $stats['noticeWithZIP']);
        $this->setLicence($notice, $item, $fileName, $stats['noticeWithoutLicence']);
        $this->setNiveauxEtude($notice, $item, $fileName, $stats['notfoundNiveauEtude']);
        $this->setDocumentTypes($notice, $item);
        $this->setPedagogies($notice, $item, $fileName, $stats['notFoundPedagogie']);
        $this->setClassifications($notice, $item, $fileName, $stats);

        $this->entityManager->persist($notice);
    }

    /**
     * Gère les relations entre notices à partir des données importées.
     *
     * @param SuplomDto $item Les données importées du fichier XML.
     * @param mixed $uid L'identifiant unique de la notice courante.
     * @param string $fileName Le nom du fichier en cours de traitement.
     * @param array &$relationsToLink Référence vers le tableau des relations à lier.
     * @param array &$noticeWithoutRelation Référence vers le tableau des notices sans relation.
     * @return void
     */
    private function handleRelations(SuplomDto $item, mixed $uid, string $fileName, array &$relationsToLink, array &$noticeWithoutRelation): void
    {
        if (isset($item->relations)) {
            foreach ($item->relations as $relation) {
                if (isset($relation->resource->identifier->entry)) {
                    if (str_starts_with($fileName, 'suplomfr')){
                        parse_str(parse_url($relation->resource->identifier->entry, PHP_URL_QUERY), $params);
                    } else if (str_starts_with($fileName, 'oai_www')) {
                        $params['uuid'] = $relation->resource->identifier->entry;
                    }
                    if (isset($params['uuid'])) {
                        $relationsToLink[$uid][] = $params['uuid'];
                    }
                }
            }
        }
        if (empty($relationsToLink[$uid])){
            $noticeWithoutRelation[] = [
                'fileName' => $fileName
            ];
        }
    }

    /**
     * Définit les dates de la notice à partir des rôles contributeurs.
     *
     * @param Notice $notice La notice à enrichir.
     * @param array $roleNotice Les rôles contributeurs extraits du DTO.
     * @param string $fileName Le nom du fichier en cours de traitement.
     * @param array &$noticeWithoutPublieeLe Référence vers le tableau des notices sans date de publication.
     * @return void
     */
    private function setDates(Notice $notice, array $roleNotice, string $fileName, array &$noticeWithoutPublieeLe): void
    {
        if (isset($roleNotice['author'])) {
            foreach ($roleNotice['author'] as $author) {
                // Cas où seule l'année est renseignée (YYYY)
                if (!empty($author['date']) && preg_match("/^\d{4}$/", $author['date'])) {
                    $notice->setRessDate($author['date']);
                    break;
                    // Cas où la date est au format YYYY-MM-DD
                } elseif (!empty($author['date']) && preg_match("/^\d{4}-\d{2}-\d{2}$/", $author['date'])) {
                    $date = DateTime::createFromFormat('Y-m-d', $author['date']);
                    if ($date !== false) {
                        $notice->setRessDate($date->format('Y'));
                        break;
                    }
                }
            }
        }
        // PublieLe : première date valide d'un publisher, sinon validator
        $publieLe = null;
        if (isset($roleNotice['publisher'])) {
            foreach ($roleNotice['publisher'] as $publisher) {
                if (!empty($publisher['date'])) {
                    $date = DateTime::createFromFormat("Y-m-d", $publisher['date']);
                    if ($date !== false) {
                        $publieLe = $date;
                        break;
                    }
                }
            }
        }
        if ($publieLe === null && isset($roleNotice['validator'])) {
            foreach ($roleNotice['validator'] as $validator) {
                if (!empty($validator['date'])) {
                    $date = DateTime::createFromFormat("Y-m-d", $validator['date']);
                    if ($date !== false) {
                        $publieLe = $date;
                        break;
                    }
                }
            }
        }
        if ($publieLe !== null) {
            $notice->setPublieLe($publieLe);
        } else {
            $noticeWithoutPublieeLe[] = [
                'fileName' => $fileName
            ];
        }
        if ($notice->getRessDate() === null) {
            $notice->setRessDate(date('Y'));
        }
    }

    /**
     * Définit le validateur de la notice à partir des rôles contributeurs.
     *
     * @param Notice $notice La notice à enrichir.
     * @param array $roleNotice Les rôles contributeurs extraits du DTO.
     * @param string $mailDocumentaliste L'adresse email du documentaliste par défaut.
     * @param string $fileName Le nom du fichier en cours de traitement.
     * @param array &$notfoundValidateur Référence vers le tableau des validateurs non trouvés.
     * @return void
     */
    private function setValidateur(Notice $notice, array $roleNotice, string $mailDocumentaliste, string $fileName, array &$notfoundValidateur): void
    {
        $validateur = null;
        $validatorName = null;
        foreach ($roleNotice["validator"] ?? [] as $validatorInfo) {
            $validatorName = trim(($validatorInfo['firstname'] ?? '') . ' ' . ($validatorInfo['lastname'] ?? ''));
            $validatorNameInverse = trim(($validatorInfo['lastname'] ?? '') . ' ' . ($validatorInfo['firstname'] ?? ''));
            // Tester nom + prénom et prénom + nom
            $validateur = $this->userRepository->findOneBy(['name' => $validatorName])
                ?? $this->userRepository->findOneBy(['name' => $validatorNameInverse]);
            if ($validateur) {
                break;
            }
        }
        $notice->setValidateur($validateur);
        if ($notice->getValidateur() == null) {
            $notfoundValidateur[] = [
                'nom' => $validatorName,
                'nameFile' => $fileName
            ];
            $existingUserName = $this->userRepository->findOneBy(['name' => 'créateur inconnu']);
            $existingUserEmail = $this->userRepository->findOneBy(['email' => $mailDocumentaliste]);
            if ($existingUserName === null && $existingUserEmail === null) {
                $entity = new User('créateur inconnu', $mailDocumentaliste);
                $this->entityManager->persist($entity);
                $this->entityManager->flush();
                $notice->setValidateur($entity);
            } else {
                $notice->setValidateur($existingUserEmail);
            }
        }
    }

    /**
     * Définit le créateur de la notice à partir des rôles contributeurs.
     *
     * @param Notice $notice La notice à enrichir.
     * @param array $roleNotice Les rôles contributeurs extraits du DTO.
     * @param string $mailContributeur L'adresse email du contributeur par défaut.
     * @param string $fileName Le nom du fichier en cours de traitement.
     * @param array &$notfoundCreateur Référence vers le tableau des créateurs non trouvés.
     * @return void
     */
    private function setCreateur(Notice $notice, array $roleNotice, string $mailContributeur, string $fileName, array &$notfoundCreateur): void
    {
        $creator = null;
        $creatorName = null;
        foreach ($roleNotice["creator"] ?? [] as $creatorInfo) {
            $creatorName = trim(($creatorInfo['lastname'] ?? '') . ' ' . ($creatorInfo['firstname'] ?? ''));
            $creatorNameInverse = trim(($creatorInfo['firstname'] ?? '') . ' ' . ($creatorInfo['lastname'] ?? ''));
            // Tester nom + prénom et prénom + nom
            $creator = $this->userRepository->findOneBy(['name' => $creatorName])
                ?? $this->userRepository->findOneBy(['name' => $creatorNameInverse]);
            if ($creator) {
                break;
            }
        }
        $notice->setCreateur($creator);

        // Ajouter un utilisateur par défaut quand une notice en a pas
        if ($notice->getCreateur() == null) {
            $notfoundCreateur[] = [
                'nom' => $creatorName,
                'nameFile' => $fileName
            ];
            $existingUserName = $this->userRepository->findOneBy(['name' => 'créateur inconnu']);
            $existingUserEmail = $this->userRepository->findOneBy(['email' => $mailContributeur]);
            if ($existingUserName === null && $existingUserEmail === null) {
                $entity = new User('créateur inconnu', $mailContributeur);
                $this->entityManager->persist($entity);
                $this->entityManager->flush();
                $notice->setCreateur($entity);
            } else {
                $notice->setCreateur($existingUserEmail);
            }
        }
    }

    /**
     * Ajoute les auteurs à la notice à partir des rôles contributeurs.
     *
     * @param Notice $notice La notice à enrichir.
     * @param array $roleNotice Les rôles contributeurs extraits du DTO.
     * @return void
     */
    private function setAuteurs(Notice $notice, array $roleNotice): void
    {
        if (!empty($roleNotice["author"])) {
            foreach ($roleNotice["author"] as $authorInfo) {
                $lName = $authorInfo['lastname'] ?? '';
                $fName = $authorInfo['firstname'] ?? '';
                if ($lName || $fName) {
                    $auteur = $this->auteurRepository->findOneBy(['nom' => $lName, 'prenom' => $fName]);
                    if ($auteur) {
                        $notice->addAuteur($auteur);
                    } else {
                        $entity = new Auteur($lName, $fName);
                        $this->entityManager->persist($entity);
                        $this->entityManager->flush();
                        $notice->addAuteur($entity);
                    }
                }
            }
        }
    }

    /**
     * Ajoute les porteurs (établissements) à la notice à partir des rôles contributeurs.
     *
     * @param Notice $notice La notice à enrichir.
     * @param array $roleNotice Les rôles contributeurs extraits du DTO.
     * @param string $fileName Le nom du fichier en cours de traitement.
     * @param array &$notfoundPorteur Référence vers le tableau des établissements non trouvés.
     * @return void
     */
    private function setPorteurs(Notice $notice, array $roleNotice, string $fileName, array &$notfoundPorteur): void
    {
        // Ajout d'une catégorie publisher car certaines notices n'ont pas de role publisher
        if (empty($roleNotice["publisher"] ?? null)) {
            $roleNotice["publisher"] = [];
        }

        foreach ($roleNotice["publisher"] as $publisherInfo) {
            $org = trim($publisherInfo['org'] ?? '');
            $fn = trim($publisherInfo['fn'] ?? '');

            $etablissementTrouve = null;

            // Test 1 : Recherche avec ORG d'abord
            if ($org !== '') {
                $etablissementTrouve = $this->etablissementRepository->findOneBy(['nom' => $org]);
            }

            // Test 2 : Si pas trouvé avec ORG, essayer avec FN
            if (!$etablissementTrouve && $fn !== '') {
                $etablissementTrouve = $this->etablissementRepository->findOneBy(['nom' => $fn]);
            }

            // Traitement du résultat
            if ($etablissementTrouve) {
                $notice->addPorteur($etablissementTrouve);
            } else {
                // Prendre ORG en priorité, sinon FN
                $nomNonTrouve = $org !== '' ? $org : $fn;
                if ($nomNonTrouve !== '') {
                    $notfoundPorteur[] = [
                        'nom' => $nomNonTrouve,
                        'fileName' => $fileName
                    ];
                }
            }
        }
    }

    /**
     * Ajoute les mots-clés à la notice.
     *
     * @param Notice $notice La notice à enrichir.
     * @param SuplomDto $item Les données importées du fichier XML.
     * @return void
     */
    private function setKeywords(Notice $notice, SuplomDto $item): void
    {
        $motcles = array_map(fn(Motcle $s) => trim($s->string?->value), $item->general?->keywords);
        // Gestion des mots-clés (keywords)
        foreach ($motcles as $motcle) {
            if (!is_string($motcle) || trim($motcle) === '') continue;
            $motcle = trim($motcle);
            $keyword = $this->keywordRepository->findOneBy(['nom' => $motcle]);
            if (!$keyword) {
                $keyword = new Keyword($motcle);
                $keyword->setValide(true);
                $this->entityManager->persist($keyword);
                $this->entityManager->flush();
            }
            $notice->addTag($keyword);
        }
    }

    /**
     * Définit les champs techniques de la notice à partir des données importées.
     *
     * @param Notice $notice La notice à enrichir.
     * @param SuplomDto $item Les données importées du fichier XML.
     * @param bool $exposed Indique si la notice doit être exposée sur le portail.
     * @param string $fileName Le nom du fichier en cours de traitement.
     * @param array &$noticeWithZIP Référence vers le tableau des notices avec ressources ZIP.
     * @return void
     */
    private function setTechnicalFields(Notice $notice, SuplomDto $item, bool $exposed, string $fileName, array &$noticeWithZIP): void
    {
        // conversion Octets en Mo
        if (isset($item->technical?->size)) {
            $notice->setRessSize(round(floatval($item->technical->size) / 1048576, 2));
        }
        $notice->setExportOAI($exposed)->setEtat(NoticEtat::Approved)
            ->setUuid($item->general->identifier?->entry)
            ->setTitre($item->general->title[0]?->value)
            ->setDescription($item->general->description[0]?->value)
            ->setDureExec(normalizeDuration($item->technical?->duration->duration ?? null))
            ->setRessLang($item->general->languages)
            ->setUserLang($item->educational->languages ?? null)
            ->setDureAppr(normalizeDuration($item->educational->typicalLearningTime->duration ?? null))
            ->setProprIntel(strtolower($item->rights?->copyrightAndOtherRestrictions?->value ?? '') !== "no")
            ->setRessPayant(strtolower($item->rights?->cost?->value ?? '') !== "no")
            ->setPropUser(array_map(fn(Motcle $s) => $s->string?->value, $item->educational?->description))
            ->setRessUrl(trim($item->technical?->location));

        if (str_contains(trim($item->technical?->location), 'document/')) {
            $noticeWithZIP[] = [
                'fileName' => $fileName,
                'lien_ZIP' => $item->technical?->location
            ];
        }

    }

    /**
     * Définit la licence de la notice à partir des données importées.
     *
     * @param Notice $notice La notice à enrichir.
     * @param SuplomDto $item Les données importées du fichier XML.
     * @param string $fileName Le nom du fichier en cours de traitement.
     * @param array &$noticeWithoutLicence Référence vers le tableau des licences non trouvées.
     * @return void
     */
    private function setLicence(Notice $notice, SuplomDto $item, string $fileName, array &$noticeWithoutLicence): void
    {
        $licenceKey = null;
        if (isset($item->rights->description[0]->value)) {
            $licenceKey = strtolower(preg_replace('/\s+/', '', $item->rights->description[0]->value));
        }
        $licence = $this->licenceRepository[$licenceKey] ?? null;
        $notice->setDroit($licence);

        // Ajouter une licence par défaut quand une notice en a pas
        if ($notice->getDroit() == null) {
            $noticeWithoutLicence[] = [
                'nom' => $item->rights->description[0]->value ?? '[licence inconnue]',
                'nameFile' => $fileName
            ];
            // Recherche la licence par défaut dans la base (pas dans $this->licenceRepository)
            $existingLicence = $this->entityManager->getRepository(Licence::class)->findOneBy(['code' => 'LPD']);
            if ($existingLicence === null) {
                $entity = new Licence('LPD', 'Licence par défaut');
                $this->entityManager->persist($entity);
                $this->entityManager->flush();
                $notice->setDroit($entity);
            } else {
                $notice->setDroit($existingLicence);
            }
        }
    }

    /**
     * Ajoute les contextes (niveaux d'étude) à la notice à partir des données importées.
     *
     * @param Notice $notice La notice à enrichir.
     * @param SuplomDto $item Les données importées du fichier XML.
     * @param string $fileName Le nom du fichier en cours de traitement.
     * @param array &$notfoundNiveauEtude Référence vers le tableau des niveaux d'étude non trouvés.
     * @return void
     */
    private function setNiveauxEtude(Notice $notice, SuplomDto $item, string $fileName, array &$notfoundNiveauEtude): void
    {
        foreach (($item->educational?->contexts ?? []) as $niveau){
            $key = strtolower($niveau->value);
            if (isset($this->niveauRepository[$key])){
                $notice->addNiveau($this->niveauRepository[$key]);
            } else {
                $notfoundNiveauEtude[] = [
                    'nom' => $niveau->value,
                    'nameFile' => $fileName
                ];
            }
        }
    }

    /**
     * Ajoute les types de document à la notice à partir des données importées.
     *
     * @param Notice $notice La notice à enrichir.
     * @param SuplomDto $item Les données importées du fichier XML.
     * @return void
     */
    private function setDocumentTypes(Notice $notice, SuplomDto $item): void
    {
        $allDocumentTypes = array_merge($item->general?->documentTypesLOMFR, $item->general?->documentTypesLOM);
        foreach ($allDocumentTypes as $documentType) {
            $docTypeValue = $documentType->value ?? null;
            if (!empty($docTypeValue) && isset($this->tdocumentRepository[strtolower($docTypeValue)])) {
                $notice->addDocType($this->tdocumentRepository[strtolower($docTypeValue)]);
            }
        }
    }

    /**
     * Ajoute les types pédagogiques à la notice à partir des données importées.
     *
     * @param Notice $notice La notice à enrichir.
     * @param SuplomDto $item Les données importées du fichier XML.
     * @param string $fileName Le nom du fichier en cours de traitement.
     * @param array &$notfoundPedagogie Référence vers le tableau des types pédagogiques non trouvés.
     * @return void
     */
    private function setPedagogies(Notice $notice, SuplomDto $item, string $fileName, array &$notfoundPedagogie): void
    {
        foreach ($item->educational?->learningResourceTypes as $typePedagogique) {
            $key = trim(strtolower($typePedagogique->value));
            if (isset($this->tpedagogiqueRepository[$key])) {
                $notice->addPedType($this->tpedagogiqueRepository[$key]);
            } else {
                $notfoundPedagogie[] = [
                    'nom' => $typePedagogique->value,
                    'nameFile' => $fileName
                ];
            }
        }
    }

    /**
     * Ajoute les classifications (spécialités, Dewey, etc.) à la notice.
     *
     * @param Notice $notice La notice à enrichir.
     * @param SuplomDto $item Les données importées du fichier XML.
     * @param string $fileName Le nom du fichier en cours de traitement.
     * @param array &$stats Référence vers le tableau des statistiques d'import (éléments manquants, anomalies, etc.).
     * @return void
     */
    private function setClassifications(Notice $notice, SuplomDto $item, string $fileName, array &$stats): void
    {
        foreach ($item->classifications as $class) {
            foreach ($class->taxonPathes as $taxonPath) {
                $key = array_reduce($taxonPath->source, fn(string $a, Field $s) => "$a $s->value", "");

                // Gestion des spécialités
                if (str_contains($key, 'lassification')) {
                    $this->setSpecialites($notice, $taxonPath, $fileName, $stats);
                }

                // Gestion des Deweys
                if (str_contains($key, 'CDD 22')) {
                    $this->setDeweys($notice, $taxonPath, $fileName, $stats);
                }
            }
            if (!isset($class->taxonPathes) && str_contains($class->purpose?->value, 'educational')) {
                $notice->setObjectif($class->description[0]?->string->value);
            }
        }
        $this->entityManager->persist($notice);
    }

    /**
     * Ajoute les spécialités (disciplines et sous-disciplines) à la notice à partir d'un chemin de taxons.
     *
     * @param Notice $notice La notice à enrichir.
     * @param mixed $taxonPath Le chemin de taxons contenant les spécialités.
     * @param string $fileName Le nom du fichier en cours de traitement.
     * @param array &$stats Référence vers le tableau des statistiques d'import (éléments manquants, anomalies, etc.).
     * @return void
     */
    function setSpecialites(Notice $notice, mixed $taxonPath, string $fileName, array &$stats): void
    {
        $disciplineGroupsByParent = [];
        foreach ($taxonPath->taxons as $taxon) {
            $specialite = trim($taxon->entry[0]?->value ?? '');
            $discipline = null;
            $champDisc = null;
            $exist = null;
            // Spécialités suplom au format "discipline. specialité" sinon spécialité autres UNT
            if (str_contains($specialite, '.')) {
                $splitPos = strpos($specialite, '.');
                $nomDiscipline = trim(substr($specialite, 0, $splitPos));
                $specialite = trim(substr($specialite, $splitPos + 1));
                $discipline = $this->disciplineRepository->findOneBy(['nom' => $nomDiscipline]);
                if ($discipline) {
                    $champDisc = $discipline->getParent();
                    $exist = $this->disciplineRepository->findOneBy(['nom' => $specialite, 'parent' => $discipline]);
                }
                if (!$exist) {
                    $exist = $this->disciplineRepository->findOneBy(['nom' => $specialite]);
                }
            } else {
                $exist = $this->disciplineRepository->findOneBy(['nom' => $specialite]);
            }
            if (!$exist) {
                $stats['notFoundSpecialites'][] = [
                    'nom' => $specialite,
                    'fileName' => $fileName
                ];
                continue;
            }
            if ($discipline === null) {
                $discipline = $exist->getParent();
                $champDisc = $discipline?->getParent();
            }
            if (!$champDisc || !$discipline) {
                $stats['specialitesWithoutParent'][] = [
                    'nom' => $specialite,
                    'fileName' => $fileName
                ];
                continue;
            }
            $groupKey = $champDisc->getId() . '-' . $discipline->getId();
            if (!isset($disciplineGroupsByParent[$groupKey])) {
                $group = new DisciplineGroup();
                $group->setChampDisc($champDisc);
                $group->setDiscipline($discipline);
                $notice->addDisciplineGroup($group);
                $disciplineGroupsByParent[$groupKey] = $group;
                $this->entityManager->persist($group);
            }
            $disciplineGroupsByParent[$groupKey]->addSpecialite($exist);
        }
    }

    /**
     * Ajoute les classifications Dewey à la notice à partir d'un chemin de taxons.
     *
     * @param Notice $notice La notice à enrichir.
     * @param mixed $taxonPath Le chemin de taxons contenant les codes Dewey.
     * @param string $fileName Le nom du fichier en cours de traitement.
     * @param array &$stats Référence vers le tableau des statistiques d'import (éléments manquants, anomalies, etc.).
     * @return void
     */
    function setDeweys(Notice $notice, mixed $taxonPath, string $fileName, array &$stats): void
    {
        $deweyGroupsByParent = [];
        foreach ($taxonPath->taxons as $taxon) {
            $nomDewey = trim($taxon->entry[0]?->value ?? '');
            $id = str_replace(' ', '', $taxon->id) ?? null;
            if (!$id) {
                $stats['deweyWithoutCode'][] = [
                    'nom' => $nomDewey,
                    'fileName' => $fileName
                ];
                continue;
            }
            // Regex pour vérifier que le format du code correspond à la notation suivante :
            // 123.45678 (notation dewey sans espaces)
            if (!preg_match('/^(?:\d{1,3}|\d{3}\.\d+)$/', $id)) {
                $stats['deweyWithMalformedCode'][] = [
                    'nom' => $nomDewey,
                    'code' => $id,
                    'fileName' => $fileName
                ];
                continue;
            }
            // code dewey original
            $code = "http://dewey.info/class/" . $id . "/";
            $exist = $this->deweyRepository->findOneBy(['code' => $code]);
            if ($exist) {
                // Vérifier si le nom est différent
                if ($exist->getNom() !== $nomDewey) {
                    $stats['codeDeweyNameDiff'][] = [
                        'nom' => $nomDewey,
                        'code' => $code,
                        'fileName' => $fileName,
                        'nom_en_base' => $exist->getNom(),
                    ];
                }
                $division = $exist->getParent();
                $dewey = $division?->getParent();
                if ($dewey && $division) {
                    $groupKey = $dewey->getId() . '-' . $division->getId();
                    if (!isset($deweyGroupsByParent[$groupKey])) {
                        $group = new DeweyGroup();
                        $group->setDewey($dewey);
                        $group->setDivision($division);
                        $notice->addDeweyGroup($group);
                        $deweyGroupsByParent[$groupKey] = $group;
                        $this->entityManager->persist($group);
                    }
                    $deweyGroupsByParent[$groupKey]->addCodewey($exist);
                }
            } else {
                // code dewey personnaliser
                $existPerso = $this->deweyPersoRepository->findOneBy(['code' => $code]);
                if ($existPerso) {
                    // Vérifier si le nom est différent
                    if ($existPerso->getNom() !== $nomDewey) {
                        $stats['codeDeweyPersoNameDiff'][] = [
                            'nom' => $nomDewey,
                            'code' => $code,
                            'fileName' => $fileName,
                            'nom_en_base' => $existPerso->getNom(),
                        ];
                    }
                    $notice->addDeweyPerso($existPerso);
                } else {
                    $deweyPerso = new DeweyPerso();
                    $deweyPerso->setCode($code);
                    $deweyPerso->setNom($nomDewey);
                    $this->entityManager->persist($deweyPerso);
                    $this->entityManager->flush();
                    $notice->addDeweyPerso($deweyPerso);
                }
            }
        }
    }

    /**
     * Applique les liens entre notices à partir d'un tableau d'UUID.
     *
     * @param array<string, string[]> $relationsToLink Tableau associatif [uuid_notice => [uuid_notice liée, ...]]
     * @return void
     */
    private function linkNoticeRelations(array $relationsToLink): void
    {
        $noticeRepository = $this->entityManager->getRepository(Notice::class);

        foreach ($relationsToLink as $noticeUuid => $linkedUuids) {
            $notice = $noticeRepository->findOneBy(['uuid' => $noticeUuid]);
            if (!$notice) {
                continue;
            }
            foreach ($linkedUuids as $linkedUuid) {
                $linkedNotice = $noticeRepository->findOneBy(['uuid' => $linkedUuid]);
                if ($linkedNotice) {
                    $notice->addRessource($linkedNotice);
                }
            }
        }
        $this->entityManager->flush();
    }

    /**
     * Affiche les listes des éléments manquants ou en anomalie lors de l'import.
     *
     * @param OutputInterface $output L'interface de sortie pour afficher les résultats.
     * @param array $stats Les listes d'éléments à afficher.
     * @return void
     */
    private function afficherListesManquantes(OutputInterface $output, array $stats): void
    {
        $output->writeln("Affichage de plusieurs listes de données manquantes lors de l'import : ");

        $this->afficherListe($output, $stats['notFoundCreateur'], "Liste des créateurs non trouvés :", 'nom');
        $this->afficherListe($output, $stats['notFoundValidateur'], "Liste des validateurs non trouvés :", 'nom');
        $this->afficherListe($output, $stats['notFoundPedagogie'], "Liste des types pédagogiques non trouvés :", 'nom');
        $this->afficherListe($output, $stats['notfoundNiveauEtude'], "Liste des niveaux d'étude non trouvés :", 'nom');
        $this->afficherListe($output, $stats['notFoundSpecialites'], "Liste des spécialités non trouvées :", 'nom');
        $this->afficherListe($output, $stats['specialitesWithoutParent'], "Liste des spécialités sans parent :", 'nom');
        $this->afficherListe($output, $stats['deweyWithoutCode'], "Liste des dewey sans code :", 'nom');
        $this->afficherListe($output, $stats['deweyWithMalformedCode'], "Liste des dewey avec un code invalide :", 'nom', 'code');
        $this->afficherListe($output, $stats['codeDeweyNameDiff'], "Liste des dewey avec un code existant mais un nom différent :", 'nom', 'code', 'nom_en_base');
        $this->afficherListe($output, $stats['codeDeweyPersoNameDiff'], "Liste des dewey persos avec un code existant mais un nom différent :", 'nom', 'code', 'nom_en_base');
        $this->afficherListe($output, $stats['noticeWithoutRelation'], "Liste des notices sans relation (uniques) :");
        $this->afficherListe($output, $stats['noticeWithoutPublieeLe'], "Liste des notices sans date de publication (uniques) :");
        $this->afficherListe($output, $stats['noticeWithZIP'], "Liste des notices avec ressources sous format ZIP :", 'lien_ZIP');
        $this->afficherListe($output, $stats['noticeWithoutLicence'], "Liste des licences non trouvées :", 'nom');
        $this->afficherListe($output, $stats['notFoundPorteur'], "Liste des établissements non trouvés :", 'nom');
    }

    /**
     * Affiche une liste formatée selon les champs spécifiés.
     *
     * @param OutputInterface $output L'interface de sortie pour afficher les résultats.
     * @param array $liste La liste des éléments à afficher.
     * @param string $titre Le titre de la liste affichée.
     * @param string ...$champs Les champs à afficher pour chaque élément de la liste.
     * @return void
     */
    private function afficherListe(OutputInterface $output, array $liste, string $titre, ... $champs): void
    {
        if (count($liste) > 0) {
            $output->writeln("\n" . $titre);
            foreach ($liste as $item) {
                $ligne = '- ' . ($item['fileName'] ?? '[fichier inconnu]');
                foreach ($champs as $champ) {
                    $ligne .= ' | ' . ($item[$champ] ?? "[$champ inconnu]");
                }
                $output->writeln($ligne);
            }
        }
    }
}


/**
 * Normalise une durée ISO 8601 en format `PTxHxMxS`.
 *
 * @param string|null $duration
 * @return string|null
 */
function normalizeDuration(?string $duration): ?string
{
    $duration = trim($duration ?? '');
    if ($duration === '') {
        return null;
    }
    // PT seul -> PT0H00M00S
    if ($duration === 'PT') {
        return 'PT0H00M00S';
    }
    // P1DT12H -> PT36H00M00S
    if (preg_match('/^P(\d+)DT(\d+)H$/', $duration, $matches)) {
        $hours = (int)$matches[1] * 24 + (int)$matches[2];
        return sprintf('PT%dH00M00S', $hours);
    }
    // P1DT -> PT24H00M00S
    if (preg_match('/^P(\d+)DT$/', $duration, $matches)) {
        $hours = $matches[1] * 24;
        return sprintf('PT%dH00M00S', $hours);
    }
    // PT50H -> PT50H00M00S
    if (preg_match('/^PT(\d+)H$/', $duration, $matches)) {
        return sprintf('PT%dH00M00S', $matches[1]);
    }
    // PT3H30M -> PT3H30M00S
    if (preg_match('/^PT(\d+)H(\d+)M$/', $duration, $matches)) {
        return sprintf('PT%dH%dM00S', $matches[1], $matches[2]);
    }
    // PT30M -> PT0H30M00S
    if (preg_match('/^PT(\d+)M$/', $duration, $matches)) {
        return sprintf('PT0H%dM00S', $matches[1]);
    }
    // PT21M04S -> PT0H21M04S
    if (preg_match('/^PT(\d+)M(\d+)S$/', $duration, $matches)) {
        return sprintf('PT0H%dM%dS', $matches[1], $matches[2]);
    }
    return $duration;
}
