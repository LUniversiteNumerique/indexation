<?php

namespace App\Scheduler;

use App\Message\IntexingConfigMessage;
use App\Entity\{IndexingConfig,Notice};
use Doctrine\ORM\{EntityManagerInterface,EntityRepository};
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Symfony\Component\Console\{
    Attribute\AsCommand,
    Command\Command,
    Input\InputInterface,
    Output\OutputInterface,
    Style\SymfonyStyle};

#[AsCommand(
    name: 'app:int-scheduler-exec',
    description: "Exécute le planificateur d'indexation interne",
)]
#[AsCronTask(expression: '0 3 * * *')] // 0 8-20/4 * * *
class InterNoticeScheduler extends Command
{
    const BATCH_SIZE = 100, FULL_EXEC = true;

    private EntityRepository $configRep, $noticeRep;

    public function __construct(
        private readonly MessageBusInterface    $bus,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
        $this->noticeRep = $this->em->getRepository(Notice::class);
        $this->configRep = $this->em->getRepository(IndexingConfig::class);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output); $now = new \DateTime();
        $oldConfig = $this->configRep->findLatest(false);
        if(!$oldConfig) $oldConfig = new IndexingConfig(self::FULL_EXEC);

        $offset = 0; $itemIds = [];
        while (true) {
            $data = $this->noticeRep->findFrom($offset, self::BATCH_SIZE, self::FULL_EXEC ?null: $oldConfig->getScheduleAt());
            if (empty($data)) break;

            $itemIds = array_column($data, 'id'); //array_map(fn($item) => $item->getId(), $data);
            $this->bus->dispatch(new IntexingConfigMessage(self::FULL_EXEC,$itemIds));
            $this->em->clear();

            $io->info(sprintf('Notice "%s" indexing scheduled...', count($itemIds)));
            $offset += self::BATCH_SIZE;
        }
        //$indexedFiles = $this->sm->getDocuments("external_resource:false"); $numFound = $indexedFiles['numFound'];
        $di = $now->diff(new \DateTime()); $numFound = $offset-self::BATCH_SIZE;
        $this->configRep->add(new IndexingConfig(self::FULL_EXEC, false, $numFound, count($itemIds)+$numFound, $di->s+($di->i*60)+($di->h*3600)+($di->days*86400)));

        return Command::SUCCESS;
    }
}