<?php

namespace App\Command;

use App\Entity\Dto\{Classification, Contribute, Field, Motcle, SuplomDto};
use App\Entity\{Auteur, Dewey, DeweyPerso, Discipline, Etablissement, Keyword, Licence, Niveau, Notice, NoticEtat, TDocument, TPedagogie, User};
use App\Service\FileService;
use Symfony\Component\Console\{Attribute\AsCommand, Command\Command, Input\InputInterface, Output\OutputInterface, Style\SymfonyStyle};
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(
  name: 'import:notice-data',
  description: "Exécute le processus d'importation des notices suploms",
), ]
class ImportNoticeXmlCommand extends Command
{
  private FileService $fs;
  private array $droiRep, $niveRep, $tdocRep, $tpedRep;
  private EntityRepository $userRep, $deweRep, $auteRep, $kwrdRep, $discRep, $etabRep, $dewePersoRep;
  private Filesystem $filesystem;

  public function __construct(private readonly EntityManagerInterface $em, private readonly SerializerInterface $serializer)
  {
    $this->fs = new FileService('environment/');
    parent::__construct();
    $this->userRep = $this->em->getRepository(User::class);
    $this->deweRep = $this->em->getRepository(Dewey::class);
    $this->dewePersoRep = $this->em->getRepository(DeweyPerso::class);
    $this->auteRep = $this->em->getRepository(Auteur::class);
    $this->kwrdRep = $this->em->getRepository(Keyword::class);
    $this->discRep = $this->em->getRepository(Discipline::class);
    $this->etabRep = $this->em->getRepository(Etablissement::class);
    $this->filesystem = new Filesystem();

    $this->droiRep = array_reduce(
      $this->em->getRepository(Licence::class)->findAll(),
      fn(array $k, Licence $v) => $k + [strtolower(preg_replace('/\s+/', '', $v->getValeur())) => $v], []);

    $this->niveRep = array_reduce(
      $this->em->getRepository(Niveau::class)->findAll(),
      fn(array $k, Niveau $v) => $k + [strtolower($v->getCode()) => $v], []);

    $this->tdocRep = array_reduce(
      $this->em->getRepository(TDocument::class)->findAll(),
      fn(array $k, TDocument $v) => $k + [strtolower($v->getCode()) => $v], []);

    $this->tpedRep = array_reduce(
      $this->em->getRepository(TPedagogie::class)->findAll(),
      fn(array $k, TPedagogie $v) => $k + [strtolower($v->getSuplom()) => $v], []);
  }
  protected function configure(): void
  {
    $this
      ->setDescription('Importe les notices suplom (optionnellement avec un préfixe)')
      ->addArgument('prefix', \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'Préfixe des fichiers XML à importer (ex: uoh, unit)', null)
      ->addArgument('folder', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Dossier des fichiers XML à importer (déf: suplom)', 'suplom')
      ->addArgument('exposed', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Indique si les fichiers doivent être exposés sur le portail (déf: true)', true);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $UNT = $input->getArgument('prefix');
    $folder = $input->getArgument('folder');
    $exposed = $input->getArgument('exposed');
    $io = new SymfonyStyle($input, $output);
    $io->title('Suplom Importing');
    $notfoundValidateur = [];
    $notfoundPedagogie = [];
    $notfoundContext = [];
    $notfoundCreateur = [];
    $notfoundSpecialites = [];
    $codeDeweyNameDiff = [];
    $codeDeweyPersoNameDiff = [];
    $deweyWithoutCode = [];
    $deweyWithMalformedCode = [];
    $specialitesWithoutParent = [];
    $relationsToLink = [];
    $noticeWithoutLicence = [];
    $noticeWithoutPublisher = [];
    $noticeWithoutRelation = [];
    $noticeWithoutPublieeLe = [];
    $notfoundPorteur = [];
    $noticeWithZIP = [];

    if (strtolower($UNT) == "uoh"){
      $contribMail = "carole.schorle-stefan@unistra.fr";
      $docuMail = "juliette.touzene@unistra.fr";
    } else if (strtolower($UNT) == "unit"){
      $contribMail = "info@unit.eu";
      $docuMail = "contact@unit.eu";
    } else if (strtolower($UNT) == "aunge"){
      $contribMail = "info@aungee.eu";
      $docuMail = "contact@aungee.eu";
    }

    //Ré utilisation de la fonction readFilesFrom en dur car non fonctionnel avec un appel simple de celle-ci avec le meme répertoire
    $finder = new Finder();
    $path = FileService::REFERENTIELS_DIR . $folder;
    $names = ['*.xml'];
    $since = null;
    $deep = 0;
    if (!$this->filesystem->exists($path))
      $output->writeln("Aucun fichier trouvé.");
    $finder->files()->in($path)->name($names)->depth($deep);
    if ($since) $finder->date('>= ' . $since->format('Y-m-d H:i:s'));
    $data = $finder;
    $io->progressStart(count($data));
    $nr = $this->em->getRepository(Notice::class);

    foreach ($data as $file) {
      /** @var SuplomDto $item */
      $item = $this->serializer->deserialize($this->fs->readFile($file->getRealPath()), SuplomDto::class, 'xml');
      $uid = $item->general?->identifier?->entry;
      $existingNotice = $this->em->getRepository(Notice::class)->findOneBy(['uuid' => $uid]);
      if ($existingNotice) {
        continue;
      }
      $notice = new Notice();
      $io->progressAdvance();

      if (isset($item->relations)) {
        foreach ($item->relations as $relation) {
          if (
            isset($relation->resource)
            && isset($relation->resource->identifier)
            && isset($relation->resource->identifier->entry)
          ) {
            if (substr($file->getFilename(), 0, 8) === 'suplomfr'){
              parse_str(parse_url($relation->resource->identifier->entry, PHP_URL_QUERY), $params);
            } else if (substr($file->getFilename(), 0, 7) === 'oai_www') {
              $params['uuid'] = $relation->resource->identifier->entry;
            }
            if (isset($params['uuid'])) {
              $relationsToLink[$uid][] = $params['uuid'];
            }
          } else {
            $noticeWithoutRelation[] = [
              'nom' => $file->getFilename()
            ];
          }
        }
      }

      $contributes = array_merge(
        $item->metadata?->contributes,
        $item->lifeCycle?->contributes
      );
      $roleNotice = [];
      /** @var Contribute $role */
      foreach ($contributes as $role) {
        if (isset($role->entities[0])) {
          $roleValue = $role->role->value ?? null;
          $vcardFields = parseVCard($role->entities[0]);
          $firstName = $vcardFields['FIRSTNAME'] ?? '';
          $lastName  = $vcardFields['LASTNAME'] ?? '';
          $org       = $vcardFields['ORG'] ?? '';
          $email     = $vcardFields['EMAIL'] ?? '';
          $fn        = $vcardFields['FN'] ?? '';
          $name      = trim($firstName . ' ' . $lastName) ?: $org ?: $fn;

          // Stocke toutes les infos utiles pour ce rôle
          $roleNotice[$roleValue][] = [
            'name'      => $name,
            'firstname' => $firstName,
            'lastname'  => $lastName,
            'org'       => $org,
            'email'     => $email,
            'fn'        => $fn,
            'date'      => $role->date[0] ?? null,
          ];
        }
      }
      if (isset($roleNotice['author'])) {
        foreach ($roleNotice['author'] as $author) {
          // Cas où seule l'année est renseignée (YYYY)
          if (!empty($author['date']) && preg_match("/^\d{4}$/", $author['date'])) {
            $notice->setRessDate($author['date']);
            break;
          // Cas où la date est au format YYYY-MM-DD
          } elseif (!empty($author['date']) && preg_match("/^\d{4}-\d{2}-\d{2}$/", $author['date'])) {
            $date = \DateTime::createFromFormat('Y-m-d', $author['date']);
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
            $date = \DateTime::createFromFormat("Y-m-d", $publisher['date']);
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
            $date = \DateTime::createFromFormat("Y-m-d", $validator['date']);
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
          'nom' => $file->getFilename()
        ];
      }
      if ($notice->getRessDate() === null) {
        $notice->setRessDate(date('Y'));
      }
      //Validateur
      $validateur = null;
      $validatorName = null;
      foreach ($roleNotice["validator"] ?? [] as $validatorInfo) {
        $validatorName = trim(($validatorInfo['firstname'] ?? '') . ' ' . ($validatorInfo['lastname'] ?? ''));
        $validatorNameInverse = trim(($validatorInfo['lastname'] ?? '') . ' ' . ($validatorInfo['firstname'] ?? ''));
        // Tester nom + prénom et prénom + nom
        $validateur = $this->userRep->findOneBy(['name' => $validatorName])
          ?? $this->userRep->findOneBy(['name' => $validatorNameInverse]);
        if ($validateur) {
          break;
        }
      }
      $notice->setValidateur($validateur);
      if ($notice->getValidateur() == null) {
        $notfoundValidateur[] = [
          'nom' => $validatorName,
          'nameFile' => $file->getFilename()
        ];
        $existingUserName = $this->userRep->findOneBy(['name' => 'créateur inconnu']);
        $existingUserEmail = $this->userRep->findOneBy(['email' => $docuMail]);
        if ($existingUserName === null && $existingUserEmail === null) {
          $entity = new User('créateur inconnu', $docuMail);
          $this->em->persist($entity);
          $this->em->flush();
          $notice->setValidateur($entity);
        } else {
          $notice->setValidateur($existingUserEmail);
        }
      }

      $creator = null;
      $creatorName = null;
      foreach ($roleNotice["creator"] ?? [] as $creatorInfo) {
        $creatorName = trim(($creatorInfo['lastname'] ?? '') . ' ' . ($creatorInfo['firstname'] ?? ''));
        $creatorNameInverse = trim(($creatorInfo['firstname'] ?? '') . ' ' . ($creatorInfo['lastname'] ?? ''));
        // Tester nom + prénom et prénom + nom
        $creator = $this->userRep->findOneBy(['name' => $creatorName])
          ?? $this->userRep->findOneBy(['name' => $creatorNameInverse]);
        if ($creator) {
          break;
        }
      }
      $notice->setCreateur($creator);

      // Ajouter un utilisateur par défaut quand une notice en a pas
      if ($notice->getCreateur() == null) {
        $notfoundCreateur[] = [
          'nom' => $creatorName,
          'nameFile' => $file->getFilename()
        ];
        $existingUserName = $this->userRep->findOneBy(['name' => 'créateur inconnu']);
        $existingUserEmail = $this->userRep->findOneBy(['email' => $contribMail]);
        if ($existingUserName === null && $existingUserEmail === null) {
          $entity = new User('créateur inconnu', $contribMail);
          $this->em->persist($entity);
          $this->em->flush();
          $notice->setCreateur($entity);
        } else {
          $notice->setCreateur($existingUserEmail);
        }
      }

      if (!empty($roleNotice["author"])) {
        foreach ($roleNotice["author"] as $authorInfo) {
          $lName = $authorInfo['lastname'] ?? '';
          $fName = $authorInfo['firstname'] ?? '';
          if ($lName || $fName) {
            $auteur = $this->auteRep->findOneBy(['nom' => $lName, 'prenom' => $fName]);
            if ($auteur) {
              $notice->addAuteur($auteur);
            } else {
              $entity = new Auteur($lName, $fName);
              $this->em->persist($entity);
              $this->em->flush();
              $notice->addAuteur($entity);
            }
          }
        }
      }
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
          $etablissementTrouve = $this->etabRep->findOneBy(['nom' => $org]);
        }

        // Test 2 : Si pas trouvé avec ORG, essayer avec FN
        if (!$etablissementTrouve && $fn !== '') {
          $etablissementTrouve = $this->etabRep->findOneBy(['nom' => $fn]);
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
              'nameFile' => $file->getFilename()
            ];
          }
        }
      }

      $motcles = array_map(fn(Motcle $s) => trim($s->string?->value), $item->general?->keywords);
      // Gestion des mots-clés (keywords)
      foreach ($motcles as $motcle) {
        if (!is_string($motcle) || trim($motcle) === '') continue;
        $motcle = trim($motcle);
        $keyword = $this->kwrdRep->findOneBy(['nom' => $motcle]);
        if (!$keyword) {
          $keyword = new Keyword($motcle);
          $keyword->setValide(true);
          $this->em->persist($keyword);
          $this->em->flush();
        }
        $notice->addTag($keyword);
      }

      // conversion Octets en Mo
      if (isset($item->technical?->size)) {
        $notice->setRessSize(round(floatval($item->technical->size) / 1048576, 2));
      }
      $notice->setExportOAI($exposed)->setEtat(NoticEtat::Approved)
        ->setUuid($uid) //->setVignette("$uid.jpg")
        ->setTitre($item->general->title[0]?->value)
        ->setDescription($item->general->description[0]?->value)
        ->setDureExec(normalizeDuration($item->technical?->duration->duration ?? null))
        ->setRessLang($item->general->languages)
        ->setUserLang($item->educational->languages ?? null)
        ->setDureAppr(normalizeDuration($item->educational->typicalLearningTime->duration ?? null))
        ->setProprIntel(strtolower($item->rights?->copyrightAndOtherRestrictions?->value ?? '') !== "no")
        ->setRessPayant(strtolower($item->rights?->cost?->value ?? '') !== "no")
        ->setPropUser(array_map(fn(Motcle $s) => $s->string?->value, $item->educational?->description));

      $licenceKey = null;
      if (isset($item->rights->description[0]->value)) {
        $licenceKey = strtolower(preg_replace('/\s+/', '', $item->rights->description[0]->value));
      }
      $licence = $this->droiRep[$licenceKey] ?? null;
      $notice->setDroit($licence);

      // Ajouter une licence par défaut quand une notice en a pas
      if ($notice->getDroit() == null) {
        $noticeWithoutLicence[] = [
          'nom' => $item->rights->description[0]->value ?? '[licence inconnue]',
          'nomSearchInBase' => $licenceKey,
          'nameFile' => $file->getFilename()
        ];
        // Recherche la licence par défaut dans la base (pas dans $this->droiRep)
        $existingLicence = $this->em->getRepository(Licence::class)->findOneBy(['code' => 'LPD']);
        if ($existingLicence === null) {
          $entity = new Licence('LPD', 'Licence par défaut');
          $this->em->persist($entity);
          $this->em->flush();
          $notice->setDroit($entity);
        } else {
          $notice->setDroit($existingLicence);
        }
      }

      if (str_contains(trim($item->technical?->location), 'document/')) {
        $notice->setRessUrl(trim($item->technical?->location));
        $noticeWithZIP[] = [
          'fileName' => $file->getFilename(),
          'lien_ZIP' => $item->technical?->location
        ];
      } else {
        $notice->setRessUrl(trim($item->technical?->location));
      }


      foreach (($item->educational?->contexts ?? []) as $s){
        $key = strtolower($s->value);
        if (isset($this->niveRep[$key])){
          $notice->addNiveau($this->niveRep[$key]);
        } else {
          $notfoundContext[] = [
            'nom' => $s->value,
            'nameFile' => $file->getFilename()
          ];
        }
      }
      $allDocumentTypes = array_merge($item->general?->documentTypesLOMFR, $item->general?->documentTypesLOM);
      foreach ($allDocumentTypes as $s) {
        $docTypeValue = $s->value ?? null;
        if (!empty($docTypeValue) && isset($this->tdocRep[strtolower($docTypeValue)])) {
          $notice->addDocType($this->tdocRep[strtolower($docTypeValue)]);
        }
      }
      foreach ($item->educational?->learningResourceTypes as $s) {
        $key = trim(strtolower($s->value));
        if (isset($this->tpedRep[$key])) {
          $notice->addPedType($this->tpedRep[$key]);
        } else {
          $notfoundPedagogie[] = [
            'nom' => $s->value,
            'nameFile' => $file->getFilename()
          ];
        }
      }
      /** @var Classification $class */
      foreach ($item->classifications as $class) {
        foreach ($class->taxonPathes as $taxonPath) {
          $key = array_reduce($taxonPath->source, fn(string $a, Field $s) => "$a $s->value", "");

          // Gestion des spécialités
          if (str_contains($key, 'lassification')) {
            $disciplineGroupsByParent = [];
            foreach ($taxonPath->taxons as $taxon) {
              $spec = trim($taxon->entry[0]?->value ?? '');
              $disc = null;
              $champDisc = null;
              $exist = null;
              // Spécialités suplom au format "discipline. specialité" sinon spécialité autres UNT
              if (str_contains($spec, '.')) {
                $splitPos = strpos($spec, '.');
                $discNom = trim(substr($spec, 0, $splitPos));
                $spec = trim(substr($spec, $splitPos + 1));
                $disc = $this->discRep->findOneBy(['nom' => $discNom]);
                if ($disc) {
                  $champDisc = $disc->getParent();
                  $exist = $this->discRep->findOneBy(['nom' => $spec, 'parent' => $disc]);
                }
                if (!$exist) {
                  $exist = $this->discRep->findOneBy(['nom' => $spec]);
                }
              } else {
                $exist = $this->discRep->findOneBy(['nom' => $spec]);
              }
              if (!$exist) {
                $notfoundSpecialites[] = [
                  'nom' => $spec,
                  'nameFile' => $file->getFilename()
                ];
                continue;
              }
              if ($disc === null) {
                $disc = $exist->getParent();
                $champDisc = $disc?->getParent();
              }
              if (!$champDisc || !$disc) {
                $specialitesWithoutParent[] = [
                  'nom' => $spec,
                  'nameFile' => $file->getFilename()
                ];
                continue;
              }
              $groupKey = $champDisc->getId() . '-' . $disc->getId();
              if (!isset($disciplineGroupsByParent[$groupKey])) {
                $group = new \App\Entity\DisciplineGroup();
                $group->setChampDisc($champDisc);
                $group->setDiscipline($disc);
                $notice->addDisciplineGroup($group);
                $disciplineGroupsByParent[$groupKey] = $group;
                $this->em->persist($group);
              }
              $disciplineGroupsByParent[$groupKey]->addSpecialite($exist);
            }
          }

          // Gestion des Deweys
          if (str_contains($key, 'CDD 22')) {
            $deweyGroupsByParent = [];
            foreach ($taxonPath->taxons as $taxon) {
              $spec = trim($taxon->entry[0]?->value ?? '');
              $id = str_replace(' ', '', $taxon->id) ?? null;
              if (!$id) {
                $deweyWithoutCode[] = [
                  'nom' => $spec,
                  'file' => $file->getFilename()
                ];
                continue;
              }
              // Regex pour vérifier que le format du code correspond à la notation suivante :
              // 123.45678 (notation dewey sans espaces)
              if (!preg_match('/^(?:\d{1,3}|\d{3}\.\d+)$/', $id)) {
                $deweyWithMalformedCode[] = [
                  'nom' => $spec,
                  'code' => $id,
                  'file' => $file->getFilename()
                ];
                continue;
              }
              // code dewey original
              $code = "http://dewey.info/class/" . $id . "/";
              $exist = $this->deweRep->findOneBy(['code' => $code]);
              if ($exist) {
                // Vérifier si le nom est différent
                if ($exist->getNom() !== $spec) {
                  $codeDeweyNameDiff[] = [
                    'nom' => $spec,
                    'code' => $code,
                    'file' => $file->getFilename(),
                    'nom_en_base' => $exist->getNom(),
                  ];
                }
                $disc = $exist->getParent();
                $champDisc = $disc?->getParent();
                if ($champDisc && $disc) {
                  $groupKey = $champDisc->getId() . '-' . $disc->getId();
                  if (!isset($deweyGroupsByParent[$groupKey])) {
                    $group = new \App\Entity\DeweyGroup();
                    $group->setDewey($champDisc);
                    $group->setDivision($disc);
                    $notice->addDeweyGroup($group);
                    $deweyGroupsByParent[$groupKey] = $group;
                    $this->em->persist($group);
                  }
                  $deweyGroupsByParent[$groupKey]->addCodewey($exist);
                }
              } else {
                // code dewey personnaliser
                $existPerso = $this->dewePersoRep->findOneBy(['code' => $code]);
                if ($existPerso) {
                  // Vérifier si le nom est différent
                  if ($existPerso->getNom() !== $spec) {
                    $codeDeweyPersoNameDiff[] = [
                      'nom' => $spec,
                      'code' => $code,
                      'file' => $file->getFilename(),
                      'nom_en_base' => $existPerso->getNom(),
                    ];
                  }
                  $notice->addDeweyPerso($existPerso);
                } else {
                  $deweyPerso = new DeweyPerso();
                  $deweyPerso->setCode($code);
                  $deweyPerso->setNom($spec);
                  $this->em->persist($deweyPerso);
                  $this->em->flush();
                  $notice->addDeweyPerso($deweyPerso);
                }
              }
            }
          }
        }
        if (!isset($class->taxonPathes) && str_contains($class->purpose?->value, 'educational')) {
            $notice->setObjectif($class->description[0]?->string->value);
        }
      }
      $this->em->persist($notice);
    }
    $this->em->flush();

    // Appliquer les liens entre notices
    foreach ($relationsToLink as $noticeUuid => $linkedUuids) {
      $notice = $this->em->getRepository(Notice::class)->findOneBy(['uuid' => $noticeUuid]);
      foreach ($linkedUuids as $linkedUuid) {
        $linkedNotice = $this->em->getRepository(Notice::class)->findOneBy(['uuid' => $linkedUuid]);
        if ($notice && $linkedNotice) {
          $notice->addRessource($linkedNotice);
        }
      }
    }
    $this->em->flush();

    $io->progressFinish();
    $output->writeln("Affichage de plusieurs listes de données manquantes lors de l'import : ");
    if (count($notfoundCreateur) > 0) {
      $output->writeln("\nListe des créateurs non trouvés (uniques) :");
      foreach ($notfoundCreateur as $item) {
        $nom = $item['nom'] ?? '[nom inconnu]';
        $nameFile = $item['nameFile'] ?? '[fichier inconnu]';
        $output->writeln('- ' . $nameFile . ' | ' . $nom);
      }
    }
    if (count($notfoundValidateur) > 0) {
      $output->writeln("\nListe des validateurs non trouvés (uniques) :");
      foreach ($notfoundValidateur as $item) {
        $nom = $item['nom'] ?? '[nom inconnu]';
        $nameFile = $item['nameFile'] ?? '[fichier inconnu]';
        $output->writeln('- ' . $nameFile . ' | ' . $nom);
      }
    }
    if (count($notfoundPedagogie) > 0) {
      $output->writeln("\nListe des types pédagogiques non trouvés (uniques) :");
      foreach ($notfoundPedagogie as $item) {
        $nom = $item['nom'] ?? '[nom inconnu]';
        $nameFile = $item['nameFile'] ?? '[fichier inconnu]';
        $output->writeln('- ' . $nameFile . ' | ' . $nom);
      }
    }

    if (count($notfoundContext) > 0) {
      $output->writeln("\nListe des contextes non trouvés (uniques) :");
      foreach ($notfoundContext as $item) {
        $nom = $item['nom'] ?? '[nom inconnu]';
        $nameFile = $item['nameFile'] ?? '[fichier inconnu]';
        $output->writeln('- ' . $nameFile . ' | ' . $nom);
      }
    }

    if (count($notfoundSpecialites) > 0) {
      $output->writeln("\nListe des spécialités non trouvés :");
      foreach ($notfoundSpecialites as $item) {
        $nom = $item['nom'] ?? '[nom inconnu]';
        $nameFile = $item['nameFile'] ?? '[fichier inconnu]';
        $output->writeln('- ' . $nameFile . ' | ' . $nom);
      }
    }

    if (count($specialitesWithoutParent) > 0) {
      $output->writeln("\nListe des spécialités sans parents (uniques) :");
      foreach ($specialitesWithoutParent as $item) {
        $nom = $item['nom'] ?? '[nom inconnu]';
        $nameFile = $item['nameFile'] ?? '[fichier inconnu]';
        $output->writeln('- ' . $nameFile . ' | ' . $nom);
      }
    }
    if (count($deweyWithoutCode) > 0) {
      $output->writeln("\nListe des Dewey sans code :");
      foreach ($deweyWithoutCode as $item) {
        $nom = trim($item['nom'] ?? '');
        $file = $item['file'] ?? '[fichier inconnu]';
        $nomSuplom = $nom !== '' ? $nom : '[nom manquant]';
        $output->writeln('- ' . $file . ' | ' . $nomSuplom);
      }
    }
    if (count($deweyWithMalformedCode) > 0) {
      $output->writeln("\nListe des Dewey avec un code invalide :");
      foreach ($deweyWithMalformedCode as $item) {
        $nom = trim($item['nom'] ?? '');
        $file = $item['file'] ?? '[fichier inconnu]';
        $nomSuplom = $nom !== '' ? $nom : '[nom manquant]';
          $output->writeln('- ' . $file . ' | ' . ($item['code'] ?? '-') . ' | ' . $nomSuplom);
      }
    }
    if (count($codeDeweyNameDiff) > 0) {
      $output->writeln("\nListe des Dewey avec un code existant mais un nom différent (uniques) :");
      foreach ($codeDeweyNameDiff as $item) {
        $nom = trim($item['nom'] ?? '');
        $file = $item['file'] ?? '[fichier inconnu]';
        $nomSuplom = $nom !== '' ? $nom : '[nom manquant]';
        $nomEnBase = $item['nom_en_base'] ?? '-';
        $output->writeln('- ' . $file . ' | ' . ($item['code'] ?? '-') . ' | ' . $nomSuplom . ' | ' . $nomEnBase);
      }
    }
    if (count($codeDeweyPersoNameDiff) > 0) {
      $output->writeln("\nListe des Dewey persos avec un code existant mais un nom différent (uniques) :");
      foreach ($codeDeweyPersoNameDiff as $item) {
        $nom = trim($item['nom'] ?? '');
        $file = $item['file'] ?? '[fichier inconnu]';
        $nomSuplom = $nom !== '' ? $nom : '[nom manquant]';
        $nomEnBase = $item['nom_en_base'] ?? '-';
        $output->writeln('- ' . $file . ' | ' . ($item['code'] ?? '-') . ' | ' . $nomSuplom . ' | ' . $nomEnBase);
      }
    }

    if (count($noticeWithoutPublisher) > 0) {
      $output->writeln("\nListe des notices sans publisher (uniques) :");
      $noms = [];
      foreach ($noticeWithoutPublisher as $item) {
        $noms[] = $item['nom'] ?? '[nom inconnu]';
      }
      $nomsUniques = array_unique($noms);
      foreach ($nomsUniques as $nom) {
        $output->writeln('- ' . $nom);
      }
    }
    if (count($noticeWithoutRelation) > 0) {
      $output->writeln("\nListe des notices sans Relations (uniques) :");
      $noms = [];
      foreach ($noticeWithoutRelation as $item) {
        $noms[] = $item['nom'] ?? '[nom inconnu]';
      }
      $nomsUniques = array_unique($noms);
      foreach ($nomsUniques as $nom) {
        $output->writeln('- ' . $nom);
      }
    }
    if (count($noticeWithoutPublieeLe) > 0) {
      $output->writeln("\nListe des notices sans Publiee le (uniques) :");
      $noms = [];
      foreach ($noticeWithoutPublieeLe as $item) {
        $noms[] = $item['nom'] ?? '[nom inconnu]';
      }
      $nomsUniques = array_unique($noms);
      foreach ($nomsUniques as $nom) {
        $output->writeln('- ' . $nom);
      }
    }

    if (count($noticeWithZIP) > 0) {
      $output->writeln("\nListe des notices avec ressources sous format ZIP :");
      foreach ($noticeWithZIP as $item) {
        $fileName = $item['fileName'] ?? '[fichier inconnu]';
        $lienZIP = $item['lien_ZIP'] ?? '[lien inconnu]';

        $output->writeln("Fichier : " . $fileName);
        $output->writeln("Lien trouvé pour format ZIP : " . trim($lienZIP));
      }
    }
    if (count($noticeWithoutLicence) > 0) {
      $output->writeln("\nListe des licence non trouvés :");
      foreach ($noticeWithoutLicence as $item) {
        $nom = $item['nom'] ?? '[nom inconnu]';
        $nomSearchInBase = $item['nomSearchInBase'] ?? '[nom inconnu]';
        $nameFile = $item['nameFile'] ?? '[fichier inconnu]';
        $output->writeln( $nameFile . ' | ' . $nom );
      }
    }

    if (count($notfoundPorteur) > 0) {
      $output->writeln("\nListe des Etablissement non trouvés :");
      foreach ($notfoundPorteur as $item) {
        $nom = $item['nom'] ?? '[nom inconnu]';
        $nameFile = $item['nameFile'] ?? '[fichier inconnu]';
        $output->writeln( $nameFile . ' | ' . $nom );
      }
    }

    $output->writeln("Suplom imported successfully !");
    return Command::SUCCESS;
  }
}
function normalizeDuration($duration) {
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
        $hours = $matches[1] * 24 + $matches[2];
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
    // PT3H30M15S, laisse tel quel
    if (preg_match('/^PT\d+H\d+M\d+S$/', $duration)) {
        return $duration;
    }
    return $duration;
}

function parseVCard($vcardString)
{
  $fields = [];
  // Découpe la vCard
  $lines = preg_split('/\r\n|\r|\n/', $vcardString);
  foreach ($lines as $line) {
    $line = trim($line);
    if (strpos($line, 'FN:') === 0) {
      $fields['FN'] = substr($line, 3);
    } elseif (strpos($line, 'EMAIL') === 0) {
      $parts = explode(':', $line, 2);
      $fields['EMAIL'] = $parts[1] ?? '';
    } elseif (strpos($line, 'ORG:') === 0) {
      $parts = explode(':', $line, 2);
      $fields['ORG'] = isset($parts[1]) ? trim($parts[1]) : '';
    } elseif (strpos($line, 'N:') === 0) {
      $fields['N'] = substr($line, 2);
      $nParts = explode(';', $fields['N']);
      $fields['LASTNAME'] = $nParts[0] ?? '';
      $fields['FIRSTNAME'] = $nParts[1] ?? '';
    }
  }
  return $fields;
}
