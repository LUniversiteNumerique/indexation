<?php

namespace App\Command;

use App\Entity\Univerique;
use App\Entity\User;
use App\Entity\Etablissement;
use App\Repository\GroupeRepository;
use App\Repository\UniveriqueRepository;
use Symfony\Component\Console\{Attribute\AsCommand,Command\Command,Input\InputInterface,Output\OutputInterface,Style\SymfonyStyle};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'import:users-uoh-data',
    description: "Exécute le processus d'importation des utilisateurs UOH depuis l'API alfresco",
),]
class ImportUsersUOHAPICommand extends Command
{
  private const USERS_API_URL = 'http://admin:sthk7BT!@alfresco.di.unistra.fr/share/proxy/alfresco/api/people/';

  public function __construct(private readonly EntityManagerInterface $em, private readonly GroupeRepository $grep, private readonly UniveriqueRepository $unt,
                              private readonly HttpClientInterface $httpClient)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Imports data from API alfresco into the database.')
            ->setHelp('This command allows you to import data from API alfresco into the database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Users Importing');

        $docuGroup = $this->grep->find(3);
        $ctrbGroup = $this->grep->find(2);
        $uohId = $this->unt->find(3);

        $response = $this->httpClient->request('GET', self::USERS_API_URL);
        $data = $response->toArray();
        $users = $data['people'] ?? [];

        $emails = []; $duplicate = []; $usersWithGroupsCount = 0;

        $io->progressStart(count($users));
        foreach ($users as $item) {
            $io->progressAdvance();
            if ($item['enabled']) {
                $emailLower = strtolower($item['email']);
                if(in_array($emailLower, $emails, true)){
                  $duplicate[] = [
                    'username' => $item['userName'],
                    'firstName' => $item['firstName'],
                    'lastName' => $item['lastName'],
                    'email' => $item['email']
                  ];
                } else {
                  $entity = new User($item['firstName'] . ' ' . $item['lastName'], $item['email']);

                  // Appel API pour récupèrer l'établissement et le role
                  $responseGroup = $this->httpClient->request('GET', self::USERS_API_URL.$item['userName'].'?groups=true');
                  $dataGroup = $responseGroup->toArray();

                  // Vérification si l'utilisateur a des groupes
                  if (isset($dataGroup['groups']) && !empty($dataGroup['groups'])) {
                    $groupAssigned = false;
                    // Vérification de la présence des groupes et attribution du rôle avec priorité
                    foreach ($dataGroup['groups'] as $group) {

                      // Définit son role s'il en possède 1
                      if (!$groupAssigned && strpos($group['itemName'], 'DOCUMENTALISTE') !== false) {
                        $entity->setGroup($docuGroup);
                        $usersWithGroupsCount++;
                        $groupAssigned = true;
                        $entity->setUntheme($uohId);
                      } elseif (!$groupAssigned && strpos($group['itemName'], 'CONTRIBUTEUR') !== false) {
                        $entity->setGroup($ctrbGroup);
                        $usersWithGroupsCount++;
                        $groupAssigned = true;
                      }

                      // Récupérer le nom de l'établissement. ex : UOH_GRENOBLE_2
                      $schoolNameParts = explode('_', $group['displayName']);
                      // Vérifier si on a bien plus de une partie
                      if (count($schoolNameParts) > 1) {
                        // On prend le reste, ex : GRENOBLE_2
                        $schoolName = implode('_', array_slice($schoolNameParts, 1));
                      }
                      // Recherche de l'établissement
                      $etablissement = $this->em->getRepository(Etablissement::class)->findOneBy(['code' => $schoolName]);
                      if ($etablissement) {
                        $entity->setSchool($etablissement);
                      }
                    }
                  }
                  $this->em->persist($entity);
                  $emails[] = $item['email'];
                }
            }
        }
        $io->progressFinish();

        $this->em->flush();

        $output->writeln("Total users with groups: $usersWithGroupsCount");
        $output->writeln("Users imported successfully with " . count($duplicate) . " duplicates: ");
        foreach ($duplicate as $dup) {
          $output->writeln("Duplicate: {$dup['firstName']} {$dup['lastName']} ({$dup['username']}) - {$dup['email']}");
        }

      return Command::SUCCESS;
    }
}
