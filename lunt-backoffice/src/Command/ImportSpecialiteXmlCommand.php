<?php

namespace App\Command;

use App\Entity\Discipline;
use App\Service\FileService;
use SimpleXMLElement;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Entity\Univerique;

/**
 * Commande d'importation des spécialités depuis un fichier XML.
 */
#[AsCommand(
    name: 'import:specialites-data',
    description: "Exécute le processus d'importation des spécialités depuis un fichier XML",
)]
class ImportSpecialiteXmlCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private readonly EntityManagerInterface $em;

    /**
     * Constructeur de la commande.
     *
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::__construct();
    }

    /**
     * Configure la commande.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Imports data from a XML file into the database.')
            ->setHelp('This command allows you to import data from a XML file into the database.')
            ->addArgument('prefix', InputArgument::REQUIRED, 'Préfixe du fichier XML à importer (ex: uoh, unit)');
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
        $io->title('Importing specialites');

        $prefix = $input->getArgument('prefix');
        $xmlFilePath = FileService::REFERENTIELS_DIR . 'specialite_' . $prefix . '.xml';
        if (!file_exists($xmlFilePath)) {
            $io->warning('Fichier specialite_' . $prefix . '.xml introuvable.');
            return Command::FAILURE;
        }
        $xml = simplexml_load_file($xmlFilePath);
        if (!$xml) {
            $io->error('Erreur lors de la lecture du fichier XML.');
            return Command::FAILURE;
        }

        $unt = $this->em->getRepository(Univerique::class)->findOneBy(['label' => strtoupper($prefix)]);
        $io->progressStart(count($xml));

        foreach ($xml->item as $item1) {
            $this->importNiveau1($item1, $unt, $io);
            $io->progressAdvance();
        }

        $this->em->flush();
        $io->progressFinish();
        $io->success('Import successful');
        return Command::SUCCESS;
    }

    /**
     * Importe une discipline de niveau 1 et ses sous-disciplines.
     *
     * @param SimpleXMLElement $item1 Élément XML du niveau 1
     * @param Univerique $unt Entité Univerique associée
     * @param SymfonyStyle $io
     * @return void
     */
    private function importNiveau1(SimpleXMLElement $item1, Univerique $unt, SymfonyStyle $io): void
    {
        [$id1, $libelle1] = $this->extractIdLibelle($item1);
        if ($id1 === null) {
            return;
        }
        $discipline1 = new Discipline($id1, $libelle1);
        $discipline1->setParent(null);
        $this->em->persist($discipline1);
        $unt->addField($discipline1);
        $this->em->persist($unt);

        foreach ($item1->item as $item2) {
            $this->importNiveau2($item2, $discipline1, $io);
            $io->progressAdvance();
        }
    }

    /**
     * Importe une discipline de niveau 2 et ses sous-disciplines.
     *
     * @param SimpleXMLElement $item2 Élément XML du niveau 2
     * @param Discipline $discipline1 Discipline parente de niveau 1
     * @param SymfonyStyle $io
     * @return void
     */
    private function importNiveau2(SimpleXMLElement $item2, Discipline $discipline1, SymfonyStyle $io): void
    {
        [$id2, $libelle2] = $this->extractIdLibelle($item2);
        if ($id2 === null) {
            return;
        }
        $discipline2 = new Discipline($id2, $libelle2);
        $discipline2->setParent($discipline1);
        $this->em->persist($discipline2);

        foreach ($item2->item as $item3) {
            $this->importNiveau3($item3, $discipline2);
            $io->progressAdvance();
        }
    }

    /**
     * Importe une discipline de niveau 3.
     *
     * @param SimpleXMLElement $item3 Élément XML du niveau 3
     * @param Discipline $discipline2 Discipline parente de niveau 2
     * @return void
     */
    private function importNiveau3(SimpleXMLElement $item3, Discipline $discipline2): void
    {
        [$id3, $libelle3] = $this->extractIdLibelle($item3);
        if ($id3 === null) {
            return;
        }
        $discipline3 = new Discipline($id3, $libelle3);
        $discipline3->setParent($discipline2);
        $this->em->persist($discipline3);
    }

    /**
     * Extrait les propriétés id et libelle d'un élément XML.
     *
     * @param SimpleXMLElement $item Élément XML à analyser
     * @return array Tableau contenant l'id et le libellé [id, libelle]
     */
    private function extractIdLibelle(SimpleXMLElement $item): array
    {
        $id = null;
        $libelle = null;
        foreach ($item->property as $prop) {
            $key = (string)$prop['key'];
            if ($key === 'id') {
                $id = (string)$prop;
            } elseif ($key === 'libelle') {
                $libelle = (string)$prop;
            }
        }
        return [$id, $libelle];
    }
}