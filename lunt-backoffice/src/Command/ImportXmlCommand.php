<?php

namespace App\Command;

use App\Message\ImportXmlMessage;
use App\Service\FileService;
use Symfony\Component\Console\{Attribute\AsCommand,Command\Command,Input\InputInterface,Output\OutputInterface,Style\SymfonyStyle};
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[
    AsCommand(
        name: 'app:import-xml-data',
        description: "Exécute le processus d'importation des données XML du serveur",
    ),
    //AsPeriodicTask(frequency: '1 month', from: '00:00')
]
class ImportXmlCommand extends Command
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly MessageBusInterface $eventBus
    )
    {
        parent::__construct();
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
