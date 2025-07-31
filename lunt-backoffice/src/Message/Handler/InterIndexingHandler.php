<?php

namespace App\Message\Handler;

use App\Entity\{IndexingConfig, Notice, NoticEtat, Univerique};
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
        $core = $task->getIndexCore()?->getName(); $offset = 0;
        $this->lg->warning(sprintf("Début d'indexation interne %s de %s", $task->isFullMode()?'complète':'différentielle', $core));

        /** @var Notice[] $data */
        while (!empty($data = $this->noticeRep->findFrom($task, $task->getBatchSize(), $offset))) {
            $news = []; $olds = []; dump(count($data));

            foreach ($data as $d) {
                if($d->getEtat() === NoticEtat::Approved) { //=> $d->isDeleted()!=1
                    if(empty($d->getPublieLe())) $news[] = $d->setPublieLe(new \DateTime()); // A publier
                    elseif($task->isFullMode() || $d->getEditeLe() > $task->getScheduleAt()) { $olds[] = $d->getUuid(); $news[] = $d; }// A republier
                } elseif($d->getPublieLe())  $olds[] = $d->setPublieLe(null)->getUuid();// A dépublier
            }

            $this->sm->editDocuments($this->pop($olds, $core) . $this->push($news, $task->getIndexCore()), "$core/update?commit=true");
            $task->setScheduleAt(new \DateTime());
            $this->em->flush(); $this->em->clear();

            $this->lg->info(sprintf("Notices concernées %d:  %d (indexées) + %d (désindexées)", count($data), count($news), count($olds)));
            $offset += $task->getBatchSize();
        }

        $this->lg->warning(sprintf("Fin d'indexation interne %s de %s", $task->isFullMode()?'complète':'différentielle', $core));
    }

    private function pop(array $sources, string $index): string
    {
        $itemSP = array_reduce($sources,fn(string $acc, string $uuid): string => $acc."<query>uuid:$uuid</query>","");

        $this->fs->removeFilesFrom("oai/$index", $sources);
        $this->fs->removeFilesFrom("suplom/$index", $sources);
        return "<delete>$itemSP</delete>";
    }

    private function push(array $sources, Univerique $unt): string
    {
      $index = $unt->getName();
      $itemSF = [];
      $itemSP = "";
      $itemOaiDC = [];
      $itemOaiSF = [];

      foreach ($sources as $notice) {
        $checkOai = $notice->isExportOAI();
        $indexingNotice = IndexingNotice::fromNotice($notice, $unt);
        $xmlContent = $this->js->serialize($indexingNotice, 'xml');
        $cleanXml = preg_replace('/<\?xml.*?\?>/', '', $xmlContent);
        $itemSP .= $cleanXml;
        // Utilisé pour les notices dont l’UUID ne respecte pas le format standard
        if(str_contains($notice->getuuid(), "http://")){
          $parts = explode('/uid/', $notice->getUuid());
          $uuid = end($parts);
          $dcKey = sprintf("dc_%s.xml", $uuid);
          $sfKey = sprintf("sf_%s.xml", $uuid);
        } else {
          $dcKey = sprintf("dc_%s.xml", $notice->getUuid());
          $sfKey = sprintf("sf_%s.xml", $notice->getUuid());
        }
        if ($checkOai) {
          $oaidcOaiDto = OaidcDto::create($notice);
          $itemOaiDC[$dcKey] = $this->js->serialize($oaidcOaiDto, 'xml');
          $suplomOaiDto = SuplomDto::create($notice);
          $itemOaiSF[$sfKey] = $this->js->serialize($suplomOaiDto, 'xml');
        }
        $suplomDto = SuplomDto::create($notice);
        $itemSF[$sfKey] = $this->js->serialize($suplomDto, 'xml');
      }
      // Indexation oai et suplom JOAI
      $suplomJoaiPath = "XML/$index/suplomfr/";
      $this->fs->writeFilesTo($itemOaiSF, $suplomJoaiPath);
      $oaiJoaiPath = "XML/$index/oai_dc/";
      $this->fs->writeFilesTo($itemOaiDC, $oaiJoaiPath);
      // Indexation suplom solr
      $suplomPath = "suplom/$index/";
      $this->fs->writeFilesTo($itemSF, $suplomPath);

      return "<add>$itemSP</add>";
    }
}
