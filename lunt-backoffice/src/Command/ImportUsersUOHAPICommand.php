<?php

namespace App\Command;

use Symfony\Contracts\HttpClient\Exception\{ClientExceptionInterface, DecodingExceptionInterface, RedirectionExceptionInterface, ServerExceptionInterface, TransportExceptionInterface};
use App\Entity\{User, Etablissement};
use App\Repository\{GroupeRepository, UniveriqueRepository};
use Symfony\Component\Console\{Attribute\AsCommand, Command\Command, Input\InputInterface, Output\OutputInterface, Style\SymfonyStyle};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Command to import UOH users from the Alfresco API.
 */
#[AsCommand(
    name: 'import:users-uoh-data',
    description: "Exécute le processus d'importation des utilisateurs UOH depuis l'API alfresco",
),]
class ImportUsersUOHAPICommand extends Command
{
    private const USERS_API_URL = 'http://admin:sthk7BT!@alfresco.di.unistra.fr/share/proxy/alfresco/api/people/';

    /**
     * @param EntityManagerInterface $entityManager
     * @param GroupeRepository $groupeRepository
     * @param UniveriqueRepository $univeriqueRepository
     * @param HttpClientInterface $httpClient
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GroupeRepository       $groupeRepository,
        private readonly UniveriqueRepository   $univeriqueRepository,
        private readonly HttpClientInterface    $httpClient
    ) {
        parent::__construct();
    }

    /**
     * Configures the command.
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Imports users from the Alfresco API into the database.')
            ->setHelp('This command imports users from the Alfresco API into the database.');
    }

    /**
     * Executes the import command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Users Importing');

        $groupeDocumentaliste = $this->groupeRepository->find(3);
        $groupeContributeur = $this->groupeRepository->find(2);
        $uohUniverique = $this->univeriqueRepository->find(3);

        $response = $this->httpClient->request('GET', self::USERS_API_URL);
        $responseData = $response->toArray();
        $apiUsers = $responseData['people'] ?? [];

        $importedEmails = [];
        $duplicateUsers = [];
        $usersWithGroupCount = 0;

        $io->progressStart(count($apiUsers));
        foreach ($apiUsers as $apiUser) {
            $io->progressAdvance();
            if ($apiUser['enabled']) {
                $normalizedEmail = strtolower($apiUser['email']);
                if (in_array($normalizedEmail, $importedEmails, true)) {
                    $duplicateUsers[] = [
                        'username' => $apiUser['userName'],
                        'firstName' => $apiUser['firstName'],
                        'lastName' => $apiUser['lastName'],
                        'email' => $apiUser['email']
                    ];
                } else {
                    $user = new User(
                        $apiUser['firstName'] . ' ' . $apiUser['lastName'],
                        $apiUser['email']
                    );

                    // Appel API pour récupèrer l'établissement et le role
                    $groupsResponse = $this->httpClient->request(
                        'GET',
                        self::USERS_API_URL . $apiUser['userName'] . '?groups=true'
                    );
                    $groupsData = $groupsResponse->toArray();

                    // Vérification si l'utilisateur a des groupes
                    if (!empty($groupsData['groups'])) {
                        $groupAssigned = false;
                        // Vérification de la présence des groupes et attribution du rôle avec priorité
                        foreach ($groupsData['groups'] as $apiGroup) {
                            // Défini son role s'il en possède un
                            if (!$groupAssigned && str_contains($apiGroup['itemName'], 'DOCUMENTALISTE')) {
                                $user->setGroup($groupeDocumentaliste);
                                $usersWithGroupCount++;
                                $groupAssigned = true;
                                $user->setUntheme($uohUniverique);
                            } elseif (!$groupAssigned && str_contains($apiGroup['itemName'], 'CONTRIBUTEUR')) {
                                $user->setGroup($groupeContributeur);
                                $usersWithGroupCount++;
                                $groupAssigned = true;
                            }

                            // Récupérer le nom de l'établissement. ex : UOH_GRENOBLE_2
                            $schoolNameParts = explode('_', $apiGroup['displayName']);
                            if (count($schoolNameParts) > 1) {
                                $schoolCode = implode('_', array_slice($schoolNameParts, 1));
                                $school = $this->entityManager
                                    ->getRepository(Etablissement::class)
                                    ->findOneBy(['code' => $schoolCode]);
                                if ($school) {
                                    $user->setSchool($school);
                                }
                            }
                        }
                    }
                    $this->entityManager->persist($user);
                    $importedEmails[] = $normalizedEmail;
                }
            }
        }
        $io->progressFinish();

        $this->entityManager->flush();

        $output->writeln("Total users with group: $usersWithGroupCount");
        $output->writeln("Imported users with " . count($duplicateUsers) . " duplicates:");
        foreach ($duplicateUsers as $duplicateUser) {
            $output->writeln(
                "Duplicate: {$duplicateUser['firstName']} {$duplicateUser['lastName']} ({$duplicateUser['username']}) - {$duplicateUser['email']}"
            );
        }

        return Command::SUCCESS;
    }
}
