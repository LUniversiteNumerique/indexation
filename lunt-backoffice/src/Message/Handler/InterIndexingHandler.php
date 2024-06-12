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
        $core = $task->getIndexCore()?->getName();

        if ($task->isFullMode()) {
            $this->sm->delDocuments("<query>external_resource:false</query>", "$core/update?commit=true");
            $this->fs->removeFilesFrom("oai/$core");
            $this->fs->removeFilesFrom("suplom/$core");
        }
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

            if (!(empty($news) && empty($olds)))
                $this->sm->editDocuments($this->push($news, $core).$this->pop($olds, $core), "$core/update?commit=true");
            $this->em->flush(); $this->em->clear();

            $offset += $task->getBatchSize(); dump(count($news), count($olds));
        } while (count($data) > 0);

        $task->setScheduleAt(new \DateTime());
        $this->em->flush();
    }

    private function pop(array $sources, string $index): string
    {
        $itemSP = array_reduce($sources,fn(string $acc, string $uuid): string => $acc."<query>uuid:$uuid</query>","");

        $this->fs->removeFilesFrom("oai/$index", $sources);
        $this->fs->removeFilesFrom("suplom/$index", $sources);
        //$this->sm->delDocuments($itemSP, "$index/update?commit=true");
        return "<delete>$itemSP</delete>";
    }

    private function push(array $sources, string $index): string
    {
        $itemSF = []; $itemDC = []; $itemSP = "";
        foreach ($sources as $notice) {
            $item = $this->js->serialize(IndexingNotice::create($notice), 'xml'); $itemSP .= preg_replace('/<\?xml.*?\?>/', '', $item); //SolrPivotBuildingAnalyzer
            $itemDC[sprintf("dc_%s.xml", $notice->getUuid())] = $this->js->serialize(OaidcDto::create($notice), 'xml'); //DublinCoreExportAnalyzer
            $itemSF[sprintf("sf_%s.xml", $notice->getUuid())] = $this->js->serialize(SuplomDto::create($notice), 'xml'); //SuplomfrExportAnalyzer
        }

        $this->fs->writeFilesTo($itemDC, "oai/$index/");
        $this->fs->writeFilesTo($itemSF, "suplom/$index/");
        //$this->sm->addDocuments($itemSP, "$index/update?commit=true");
        return "<add>$itemSP</add>";
    }
}