<?php

namespace App\Message\Handler;

use App\Entity\{IndexingConfig, Notice, NoticEtat};
use Doctrine\ORM\{EntityManagerInterface,EntityRepository};
use App\Entity\Dto\{OaidcDto, SuplomDto, IndexingNotice};
use App\Service\{FileService, SolrApiService};
use App\Message\IntexingConfigMessage;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class InterIndexingHandler
{
    private EntityRepository $configRep, $noticeRep;
    public function __construct(
        private EntityManagerInterface $em,
        private SerializerInterface    $js,
        private LoggerInterface        $lg,
        private SolrApiService         $sm,
        private FileService            $fs,
    ) {
        $this->noticeRep = $this->em->getRepository(Notice::class);
        $this->configRep = $this->em->getRepository(IndexingConfig::class);
    }

    public function __invoke(IntexingConfigMessage $message): void
    {
        /** @var IndexingConfig $task */
        $task = $this->configRep->find($message->taskId);
        if (!$task) throw new \RuntimeException("Aucun planificateur d'identifant ".$message->taskId);
        //$this->fs = new FileService($task->getBaseUri());

        $core = $task->getIndexCore()?->getName(); $offset = 0;
        $this->lg->warning(sprintf("Début d'indexation %s de %s", $task->isFullMode()?'complète':'différentielle', $core));

        if ($task->isFullMode()) {
            $this->sm->delDocuments("<query>external_resource:false</query>", "$core/update?commit=true");
            $this->fs->removeFilesFrom("oai/$core");
            $this->fs->removeFilesFrom("suplom/$core");
        }

        do {
            /** @var Notice[] $data */
            $data = $this->noticeRep->findFrom($task->getIndexCore()?->getId(), !$task->isFullMode(), $task->getBatchSize(), $offset);

            $news = []; $olds = [];
            foreach ($data as $d) {
                if ($d->getEtat() === NoticEtat::Approved) $news[] = $d->setPublieLe(new \DateTime()); // A publier
                elseif($d->getPublieLe()) $olds[] = $d->setPublieLe(null)->getUuid(); // A dépublier
            }

            if (!empty($data)) {
                $this->sm->editDocuments($this->push($news, $core) . $this->pop($olds, $core), "$core/update?commit=true");
                $task->setScheduleAt(new \DateTime());
                $this->em->flush(); $this->em->clear();
                $this->lg->info(sprintf("Notices concernées %d:  %d (indexées) + %d (dépubliées)", count($data), count($news), count($olds)));
            }

            $offset += $task->getBatchSize();
        } while (count($data) > 0);

        $this->lg->warning(sprintf("Fin d'indexation %s de %s", $task->isFullMode()?'complète':'différentielle', $core));
    }

    private function pop(array $sources, string $index): string
    {
        $itemSP = array_reduce($sources,fn(string $acc, string $uuid): string => $acc."<query>uuid:$uuid</query>","");

        $this->fs->removeFilesFrom("oai/$index", $sources);
        $this->fs->removeFilesFrom("suplom/$index", $sources);
        return "<delete>$itemSP</delete>";
    }

    private function push(array $sources, string $index): string
    {
        $itemSF = []; $itemDC = []; $itemSP = "";
        foreach ($sources as $notice) {
            $item = $this->js->serialize(IndexingNotice::fromNotice($notice), 'xml'); $itemSP .= preg_replace('/<\?xml.*?\?>/', '', $item); //SolrPivotBuildingAnalyzer
            $itemDC[sprintf("dc_%s.xml", $notice->getUuid())] = $this->js->serialize(OaidcDto::create($notice), 'xml'); //DublinCoreExportAnalyzer
            $itemSF[sprintf("sf_%s.xml", $notice->getUuid())] = $this->js->serialize(SuplomDto::create($notice), 'xml'); //SuplomfrExportAnalyzer
        }

        $this->fs->writeFilesTo($itemDC, "oai/$index/");
        $this->fs->writeFilesTo($itemSF, "suplom/$index/");
        return "<add>$itemSP</add>";
    }
}