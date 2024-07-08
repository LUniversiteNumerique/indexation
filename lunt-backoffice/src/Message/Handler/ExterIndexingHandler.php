<?php

namespace App\Message\Handler;

use Doctrine\ORM\{EntityManagerInterface,EntityRepository};
use App\Entity\{Dto\IndexingNotice, IndexingConfig, Dto\SuplomDto, Notice, Univerique};
use App\Message\ExtexingConfigMessage;
use App\Service\{FileService, SolrApiService};
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ExterIndexingHandler
{
    private EntityRepository $noticeRep,$configRep;
    public function __construct(
        private FileService $fileService,
        private SolrApiService $solrManager,
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

        if ($task->isFullMode()) $this->solrManager->delDocuments("<query>external_resource:true</query>","$coreIndex/update?commit=true");
        $sources = $this->fileService->readFilesFrom($task->isFullMode() ?null: $task->getScheduleAt(), "suplom_externe/$coreIndex") ?? [];

        $suploms = []; $uuids = [];
        foreach ($sources as $file) {
            $content = $this->fileService->readFile($file->getRealPath());
            /** @var SuplomDto $item */
            $item = $this->serializer->deserialize($content,SuplomDto::class,'xml');

            $uuids[] = substr($item?->general?->identifier?->entry, -36);
            $suploms[] = $item;
        }
        if (!empty($uuids)) $this->solrManager->addDocuments($this->pushFromNotice($uuids),"$coreIndex/update?commit=true");
        if (!empty($suploms)) $this->solrManager->addDocuments($this->pushFromSuplom($suploms, $task->getIndexCore()),"$coreIndex/update?commit=true");

        $task->setScheduleAt(new \DateTime());
        $this->manager->flush();
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
}