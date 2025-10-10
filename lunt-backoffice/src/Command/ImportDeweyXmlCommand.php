<?php

namespace App\Command;

use App\Entity\Dewey;
use App\Entity\Dto\DeweyData;
use App\Service\FileService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande d'importation des Dewey depuis un fichier XML.
 */
#[AsCommand(
    name: 'import:dewey-data',
    description: "Run the Dewey import process from an XML file",
)]
class ImportDeweyXmlCommand extends Command
{
    private readonly EntityManagerInterface $em;
    private FileService $fileService;
    private SerializerInterface $serializer;

    public function __construct(
        EntityManagerInterface $em,
        FileService $fileService,
        SerializerInterface $serializer
    ) {
        $this->em = $em;
        $this->fileService = $fileService;
        $this->serializer = $serializer;
        parent::__construct();
    }

    /**
     * Configure la commande d'importation Dewey.
     *
     * Défini la description et l'aide de la commande CLI.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Import Dewey data from referentiels/dewey.xml file.')
            ->setHelp('This command imports Dewey data from the XML file in the referentiels folder.');
    }

    /**
     * Point d'entrée de la commande.
     *
     * Exécute le processus d'importation des données Dewey à partir d'un fichier XML,
     * construit la hiérarchie et affiche le résultat dans la console.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int Code de statut de la commande (SUCCESS ou FAILURE)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Importing Dewey data');

        $xmlContent = $this->fileService->readFile(FileService::REFERENTIELS_DIR . 'dewey.xml');
        if (!$xmlContent) {
            $io->error('dewey.xml file not found or unreadable.');
            return Command::FAILURE;
        }

        try {
            /** @var DeweyData $deweyData */
            $deweyData = $this->serializer->deserialize($xmlContent, DeweyData::class, 'xml');
        } catch (\Exception $e) {
            $io->error('Erreur lors de la désérialisation du fichier XML : ' . $e->getMessage());
            return Command::FAILURE;
        }

        [$deweyObjects, $meta, $invalidEntries] = $this->importConcepts($deweyData, $io);
        if (empty($deweyObjects)) {
            $io->error('No valid Dewey entries found in the XML file.');
            return Command::FAILURE;
        }

        $io->note('Import finished, now building hierarchy...');
        $this->buildHierarchy($deweyObjects, $meta, $io);

        $this->em->flush();
        $io->progressFinish();
        $io->note(sprintf('%d Dewey entries imported.', count($deweyObjects)));
        if (count($invalidEntries)) {
            $io->warning([
                'Import completed but some entries were not imported due to an invalid code.',
                'See https://fr.wikipedia.org/wiki/Classification_Dewey for more information about Dewey code format.',
                'Please, correct the following entries and re-run the import:'
            ]);
            foreach ($invalidEntries as $code => $label) {
                $io->writeln(' - ' . $code . ' : ' . $label);
            }
        } else {
            $io->success('Import successful');
        }

        return Command::SUCCESS;
    }

    /**
     * Importe les concepts Dewey depuis le XML.
     *
     * Parcourt les concepts du fichier XML, crée ou met à jour les entités Dewey,
     * et prépare les métadonnées nécessaires à la construction de la hiérarchie.
     *
     * @param DeweyData $xml
     * @param SymfonyStyle $io
     * @return array Tableau contenant la liste des objets Dewey et les métadonnées associées
     */
    private function importConcepts(DeweyData $xml, SymfonyStyle $io): array
    {
        $deweyObjects = [];
        $invalidEntries = [];
        $meta = [];
        $concepts = $xml->concepts ?? [];

        $io->progressStart(count($concepts));

        foreach ($concepts as $concept) {
            $uri = rtrim((string)$concept->uri, '/') . '/';
            $label = ($concept->label ?? '');

            $dewey = $this->em->getRepository(Dewey::class)->findOneBy(['code' => $uri]);
            if (!$dewey) {
                $dewey = new Dewey();
                $dewey->setCode($uri);
            }
            $dewey->setNom($label);

            $codeRaw = $dewey->getNumericCode();
            // Regex pour vérifier que le format du code correspond à la notation suivante :
            // 123.456 7890 (voir la page Wikipedia https://fr.wikipedia.org/wiki/Classification_Dewey)
            if (!preg_match('/^(?:\d{1,3}|\d{3}\.\d{1,3}|\d{3}\.\d{3} \d+)$/', $codeRaw)) {
                $invalidEntries[$codeRaw] = $label;
                continue;
            }
            $codeLeft = substr($codeRaw, 0, 3);
            $level = isset($concept->level) ? (int)$concept->level : strlen($codeLeft);

            $deweyObjects[$uri] = $dewey;
            $meta[$uri] = ['code' => $codeLeft, 'level' => $level];
            $this->em->persist($dewey);

            $io->progressAdvance();
        }
        return [$deweyObjects, $meta, $invalidEntries];
    }

    /**
     * Construit la hiérarchie des Dewey.
     *
     * Associe chaque concept à son parent en fonction de son code et de son niveau.
     *
     * @param array $deweyObjects Liste des objets Dewey indexés par URI
     * @param array $meta Métadonnées des concepts
     * @param SymfonyStyle $io
     * @return void
     */
    private function buildHierarchy(array &$deweyObjects, array $meta, SymfonyStyle $io): void
    {
        $io->progressStart(count($deweyObjects));
        $metaList = $this->sortMetaByLevel($meta);

        foreach ($metaList as $entry) {
            $uri = $entry['uri'];
            $info = $entry['info'];
            $level = $info['level'];
            if ($level <= 1) continue;
            $code = (string) $info['code'];
            if ($code === '') continue;
            $parentUri = $this->findParentUri($code, $deweyObjects);
            if ($parentUri && isset($deweyObjects[$parentUri])) {
                $parentNode = $deweyObjects[$parentUri];
                $deweyObjects[$uri]->setParent($parentNode);
                $parentNode->getChildren()->add($deweyObjects[$uri]);
            }

            $io->progressAdvance();
        }
    }

    /**
     * Trie la liste des métadonnées par niveau croissant.
     *
     * @param array $meta Métadonnées des concepts
     * @return array Liste triée des métadonnées
     */
    private function sortMetaByLevel(array $meta): array
    {
        $metaList = [];
        foreach ($meta as $uri => $info) {
            $metaList[] = ['uri' => $uri, 'info' => $info];
        }
        usort($metaList, fn($a, $b) => ($a['info']['level'] <=> $b['info']['level']));
        return $metaList;
    }

    /**
     * Trouve l'URI du parent à partir du code Dewey.
     *
     * @param string $code Code Dewey du concept enfant
     * @param array $deweyObjects Liste des objets Dewey indexés par URI
     * @return string|null URI du parent si trouvé, sinon null
     */
    private function findParentUri(string $code, array $deweyObjects): ?string
    {
        $candidate = $code;
        while (strlen($candidate) > 0) {
            $candidate = substr($candidate, 0, -1);
            if ($candidate === '') break;
            $candidates = $this->generateParentUris($candidate, strlen($code));
            foreach ($candidates as $parentUri) {
                if (isset($deweyObjects[$parentUri])) {
                    return $parentUri;
                }
            }
        }
        return null;
    }

    /**
     * Génère les URIs candidates pour le parent à partir d'un code partiel.
     *
     * @param string $candidate Partie du code Dewey potentiellement parent
     * @param int $childLen Longueur du code de l'enfant
     * @return array Liste des URIs candidates
     */
    private function generateParentUris(string $candidate, int $childLen): array
    {
        $baseUri = 'http://dewey.info/class/';
        $uris = [];
        $uris[] = $baseUri . $candidate . '/';
        $candLen = strlen($candidate);
        if ($childLen >= 3 && $candLen < 3) {
            if ($candLen === 1) $uris[] = $baseUri . '0' . $candidate . '/';
            if ($candLen <= 2) $uris[] = $baseUri . str_pad($candidate, 3, '0', STR_PAD_LEFT) . '/';
        }
        if ($childLen === 2 && $candLen === 1) {
            $uris[] = $baseUri . '0' . $candidate . '/';
        }
        return $uris;
    }
}
