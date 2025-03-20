<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\GroupeRepository;
use Symfony\Component\Console\{Attribute\AsCommand,Command\Command,Input\InputInterface,Output\OutputInterface,Style\SymfonyStyle};
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'import:users-data',
    description: "Exécute le processus d'importation des utilisateurs depuis le fichier JSON",
),]
class ImportUsersJsonCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly GroupeRepository $grep)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Imports data from a JSON file into the database.')
            ->setHelp('This command allows you to import data from a JSON file into the database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Users Importing');

        $luntGroup = $this->grep->find(3);
        $ctrbGroup = $this->grep->find(2);

        $jsonFilePath ='data/uoh_users.json';
        $jsonData = file_get_contents($jsonFilePath);
        $data = json_decode($jsonData, true);

        $emails = []; $duplicate = 0;

        $io->progressStart(count($data));
        foreach ($data as $item) {
            $io->progressAdvance();
            if ($item['enabled']) {
                if(in_array($item['email'], $emails, true)) $duplicate += 1; else {
                    $entity = new User($item['firstName'] . ' ' . $item['lastName'], $item['email']);
                    if ($item['jobtitle'] == null)
                        $entity->setGroup($luntGroup);
                    else $entity->setGroup($ctrbGroup);

                    $this->em->persist($entity);
                    $emails[] = $item['email'];
                }
            }
        }
        $io->progressFinish();

        $this->em->flush();

        $output->writeln("Users imported successfully with $duplicate off");

        return Command::SUCCESS;
    }
}