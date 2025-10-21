<?php

namespace App\Message\Handler;

use Doctrine\ORM\{EntityManagerInterface,EntityRepository};
use App\Entity\{Dto\IndexingNotice, Dto\SuplomDto, IndexingConfig, Notice, Univerique};
use App\Message\ExtexingConfigMessage;
use App\Service\{FileService, SolrApiService};
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ExterIndexingHandler
{
    private EntityRepository $noticeRep,$configRep;
    public function __construct(
        private FileService $fileService,
        private SolrApiService $solrManager,
        private LoggerInterface $logger,
        private SerializerInterface $serializer,
        private EntityManagerInterface $manager,
    ){
        $this->noticeRep = $this->manager->getRepository(Notice::class);
        $this->configRep = $this->manager->getRepository(IndexingConfig::class);
    }

    public function __invoke(ExtexingConfigMessage $message): void
    {
        /** @var IndexingConfig $task */
        $task = $this->configRep->find($message->taskId);
        if (!$task) throw new \Exception("Aucun planificateur d'identifant ".$message->taskId);
        $coreIndex = $task->getIndexCore()?->getName();
        $this->logger->warning(sprintf("Début d'externe indexation %s de %s", $task->isFullMode()?'complète':'différentielle', $coreIndex));

        $sources = $this->fileService->readFilesFrom($task->isFullMode() ?null: $task->getScheduleAt(), "$coreIndex/suplom_externe", '< 2');
        if ($sources->count()) { $news = []; $olds = []; $dq = null;

            foreach ($sources as $file) {
                $item = $this->serializer->deserialize($this->fileService->readFile($file->getRealPath()), SuplomDto::class, 'xml');

                if($file->getMTime() > $task->getScheduleAt()?->getTimestamp())
                    $olds[] = substr($item?->general?->identifier?->entry, -36);
                $news[] = $item;
            }
            if ($task->isFullMode()) $dq = "<query>external_resource:true</query>"; elseif(count($olds) > 0)
                $dq = array_reduce($olds,fn(string $acc, string $uuid): string => $acc."<query>uuid:$uuid</query>","");
            if($dq) $this->solrManager->delDocuments($dq, "$coreIndex/update?commit=true");

            $this->logger->info(sprintf("%d notices concernées", count($news)));
            $this->solrManager->addDocuments($this->pushFromSuplom($news, $task->getIndexCore()), "$coreIndex/update?commit=true");

            $task->setScheduleAt(new \DateTime());
            $this->manager->flush(); unset($sources);
        }

        $this->logger->warning(sprintf("Fin d'externe indexation %s de %s", $task->isFullMode()?'complète':'différentielle', $coreIndex));
    }

    public function pushFromSuplom(array $sources, Univerique $unt): string
    {
        $itemSP = ""; /** @var SuplomDto $suplom */
        foreach ($sources as $suplom) {
            $notice = IndexingNotice::fromSuplom($suplom,$unt);
            $item = $this->serializer->serialize($notice, 'xml');
            $itemSP .= preg_replace('/<\?xml.*?\?>/', '', $item);
        }
        return "<add>$itemSP</add>";
    }

    public function pushFromNotice(array $uuids): string
    {
        $itemSP = ""; $sources = $this->noticeRep->findBy(['uuid' => $uuids]);
        foreach ($sources as $notice) {
            $item = $this->serializer->serialize(IndexingNotice::fromNotice($notice), 'xml');
            $itemSP .= preg_replace('/<\?xml.*?\?>/', '', $item);
        }
        return "<add>$itemSP</add>";
    }
}