<?php

namespace App\Message\Handler;

use App\Entity\{IndexingConfig, Notice, NoticEtat};
use Doctrine\ORM\{EntityManagerInterface,EntityRepository};
use App\Entity\Dto\{OaidcDto,SuplomDto,IndexingNotice};
use App\Service\{FileService, SolrApiService};
use App\Message\IntexingConfigMessage;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class InterIndexingHandler
{
    private EntityRepository $configRep, $noticeRep;
    public function __construct(
        private EntityManagerInterface $em,
        private SerializerInterface $js,
        private SolrApiService $sm,
        private FileService $fs,
    ) {
        $this->noticeRep = $this->em->getRepository(Notice::class);
        $this->configRep = $this->em->getRepository(IndexingConfig::class);
    }

    public function __invoke(IntexingConfigMessage $message): void
    {
        /** @var IndexingConfig $task */
        $task = $this->configRep->find($message->taskId);
        if (!$task) throw new \Exception("Aucun planificateur d'identifant ".$message->taskId);
        $urlSolr = sprintf("unt%s/update?commit=true", $task->getIndexCore()?->getId());

        if ($task->isFullMode() && $this->fs->removeFilesFrom('dc') && $this->fs->removeFilesFrom('sf'))
            $this->sm->delDocuments("<query>external_resource:false</query>",$urlSolr);
        $offset = 0;

        do {
            /** @var Notice[] $data */
            $data = $this->noticeRep->findFrom(
                $task->getIndexCore()?->getId(),
                $task->isFullMode() ?null: $task->getScheduleAt(),
                $task->getBatchSize(), $offset
            );

            $news = []; $olds = [];
            foreach ($data as $d) {
                if ($d->getEtat() === NoticEtat::Approved) $news[] = $d->setPublieLe(new \DateTime()); // publié
                elseif($d->getPublieLe()) $olds[] = $d->setPublieLe(null)->getUuid(); // dépublié
            }

            $this->push($news, $urlSolr);
            $this->pop($olds, $urlSolr);
            $this->em->flush(); $this->em->clear();

            $offset += $task->getBatchSize();
        } while (!empty($data));

        $task->setScheduleAt(new \DateTime());
        $this->em->flush();
    }

    private function pop(array $sources, string $url): void
    {
        $itemSP = array_reduce($sources,fn(string $acc, string $uuid): string => $acc."<uuid>$uuid</uuid>","");

        $this->sm->delDocuments($itemSP, $url);
        $this->fs->removeFilesFrom('dc', $sources);
        $this->fs->removeFilesFrom('sf', $sources);
    }
    private function push(array $sources, string $url): void
    {
        $itemSF = []; $itemDC = []; $itemSP = "";
        foreach ($sources as $notice) {
            $item = IndexingNotice::create($notice);
            $itemSP .= "<doc>$item</doc>";                                                                                    //SolrPivotBuildingAnalyzer

            $itemDC[sprintf("dc_%s.xml", $item->uuid)] = $this->js->serialize(OaidcDto::create($notice), 'xml'); //DublinCoreExportAnalyzer
            $itemSF[sprintf("sf_%s.xml", $item->uuid)] = $this->js->serialize(SuplomDto::create($notice), 'xml'); //SuplomfrExportAnalyzer
        }

        $this->sm->addDocuments($itemSP, $url);
        $this->fs->writeFilesTo($itemDC, 'dc/');
        $this->fs->writeFilesTo($itemSF, 'sf/');
    }
}