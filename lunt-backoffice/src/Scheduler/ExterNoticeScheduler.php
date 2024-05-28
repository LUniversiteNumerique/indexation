<?php

namespace App\Scheduler;

use App\Message\ExtexingConfigMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Symfony\Component\Console\{
    Attribute\AsCommand,
    Command\Command,
    Input\InputInterface,
    Output\OutputInterface,
    Style\SymfonyStyle};

#[AsCommand(
    name: 'app:ext-scheduler-exec',
    description: "Exécute le planificateur d'indexation externe",
)]
#[AsCronTask(expression: '0 4 * * *')]
class ExterNoticeScheduler extends Command
{
    public function __construct(private readonly MessageBusInterface $messageBus,) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->messageBus->dispatch(new ExtexingConfigMessage(true));

        $io->success('Import successful');
        return Command::SUCCESS;
    }
}