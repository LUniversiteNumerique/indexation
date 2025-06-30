<?php

namespace App\Command;

use App\Entity\Discipline;
use App\Repository\GroupeRepository;
use Symfony\Component\Console\{Attribute\AsCommand,Command\Command,Input\InputInterface,Output\OutputInterface,Style\SymfonyStyle};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Entity\Univerique;

#[AsCommand(
  name: 'import:specialites-data',
  description: "Exécute le processus d'importation des specialites depuis le fichier XML",
),]

class ImportSpecialiteXmlCommand extends Command
{
  public function __construct(private readonly EntityManagerInterface $em, private readonly GroupeRepository $grep)
  {
    parent::__construct();
  }

  protected function configure()
  {
    $this
      ->setDescription('Imports data from a XML file into the database.')
      ->setHelp('This command allows you to import data from a XML file into the database.')
      ->addArgument('prefix', InputArgument::REQUIRED, 'Préfixe du fichier XML à importer (ex: uoh, unit)');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);
    $io->title('Specialites Importing');

    $prefix = $input->getArgument('prefix');
    $xmlFilePath ='data/specialite_'.$prefix.'.xml';
    $xml = simplexml_load_file($xmlFilePath);
    $univeriqueMaj = strtoupper($prefix);
    $univerique = $this->em->getRepository(Univerique::class)->findOneBy(['label' => $univeriqueMaj]);
    $io->progressStart(count($xml));

    foreach ($xml->item as $item1) {
      $id1 = null;
      $libelle1 = null;

      // propriété niveau 1
      foreach ($item1->property as $prop) {
        $key = (string)$prop['key'];
        if ($key === 'id') {
          $id1 = (string)$prop;
        } elseif ($key === 'libelle') {
          $libelle1 = (string)$prop;
        }
      }

      if ($id1 !== null) {
        // Création Discipline pour le niveau 1
        $discipline1 = new Discipline($id1, $libelle1);
        $this->em->persist($discipline1);
        // 1er niveau parent = null
        $discipline1->setParent(null);
        // liaison de l'UNT avec le champs disc
        $univerique->addField($discipline1);
        $this->em->persist($univerique);

        // niveau 2
        foreach ($item1->item as $item2) {
          $id2 = null;
          $libelle2 = null;

          // propriété niveau 2
          foreach ($item2->property as $prop) {
            $key = (string)$prop['key'];
            if ($key === 'id') {
              $id2 = (string)$prop;
            } elseif ($key === 'libelle') {
              $libelle2 = (string)$prop;
            }
          }

          if ($id2 !== null) {
            // Création Discipline pour le niveau 2
            $discipline2 = new Discipline($id2, $libelle2);
            $discipline2->setParent($discipline1);
            $this->em->persist($discipline2);

            // Lien avec les enfants du niveau 3 si nécessaire
            foreach ($item2->item as $item3) {
              $id3 = null;
              $libelle3 = null;

              // propriété niveau 3
              foreach ($item3->property as $prop) {
                $key = (string)$prop['key'];
                if ($key === 'id') {
                  $id3 = (string)$prop;
                } elseif ($key === 'libelle') {
                  $libelle3 = (string)$prop;
                }
              }

              if ($id3 !== null) {
                // Création Discipline pour le niveau 3
                $discipline3 = new Discipline($id3, $libelle3);
                $discipline3->setParent($discipline2);
                $this->em->persist($discipline3);
              }
            }
          }
        }
      }
      $io->progressAdvance();
    }
    $this->em->flush();
    $io->progressFinish();
    return Command::SUCCESS;
  }
}
