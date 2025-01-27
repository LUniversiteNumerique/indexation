<?php

namespace App\Command;

use App\Repository\{KeywordRepository,NoticeRepository};
use Symfony\Component\Console\{Attribute\AsCommand,Command\Command,Input\InputInterface,Output\OutputInterface,Style\SymfonyStyle};
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[
    AsCommand('app:clean-data'),
    //AsCronTask('0 0 1 * *', method: 'execute')
]
class CleaNoticeCommand extends Command
{
    const MONTH_SIZE = 1;
    public function __construct(
        private readonly NoticeRepository $nrep,
        private readonly KeywordRepository $krep
    ) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Suppression des données');

        $cleanAllBefore = new \DateTime(-self::MONTH_SIZE.' month');
        $n = $this->nrep->clean($cleanAllBefore);
        $io->success(sprintf('%d notices supprimés', $n));
        $k = $this->krep->clean($cleanAllBefore);
        $io->success(sprintf('%d mot-clés supprimés', $k));

        return Command::SUCCESS;
    }
}