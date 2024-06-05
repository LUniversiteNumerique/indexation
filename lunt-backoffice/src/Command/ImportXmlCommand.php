<?php

namespace App\Command;

use App\Message\ImportXmlMessage;
use App\Service\FileService;
use Symfony\Component\Console\{Attribute\AsCommand,Command\Command,Input\InputInterface,Output\OutputInterface,Style\SymfonyStyle};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsCommand(
    name: 'app:import-xml-data',
    description: "Exécute le processus d'importation des données XML du serveur",
)]
#[AsPeriodicTask(frequency: '1 day', from: '00:00')]
class ImportXmlCommand extends Command
{
    private FileService $fileService;

    public function __construct(
        #[Autowire('%kernel.project_dir%/imports')]
        private readonly string              $directory,
        private readonly MessageBusInterface $eventBus
    )
    {
        parent::__construct();
        $this->fileService = new FileService($this->directory);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Importing XML');
        $files = $this->fileService->readFilesFrom();

        $io->progressStart(count($files));
        foreach ($files as $file) {
            $this->eventBus->dispatch(
                new ImportXmlMessage($file->getFilenameWithoutExtension(), $file->getRealPath())
            ); $io->progressAdvance();
            $io->info(sprintf('Source "%s" fetching scheduled...', $file->getFilename()));
        }
        $io->progressFinish();

        $io->success('Import successful');
        return Command::SUCCESS;
    }
}
