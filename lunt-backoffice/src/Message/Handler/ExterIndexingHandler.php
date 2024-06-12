<?php

namespace App\Message\Handler;

use Doctrine\ORM\{EntityManagerInterface,EntityRepository};
use App\Entity\{IndexingConfig,Dto\SuplomDto};
use App\Message\ExtexingConfigMessage;
use App\Service\{FileService, SolrApiService};
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ExterIndexingHandler
{
    private EntityRepository $configRep;
    public function __construct(
        private FileService $fileService,
        private SolrApiService $solrManager,
        private SerializerInterface $serializer,
        private EntityManagerInterface $manager,
    ){ $this->configRep = $this->manager->getRepository(IndexingConfig::class); }

    public function __invoke(ExtexingConfigMessage $message): void
    {
        /** @var IndexingConfig $task */
        $task = $this->configRep->find($message->taskId);
        if (!$task) throw new \Exception("Aucun planificateur d'identifant ".$message->taskId);
        $coreIndex = $task->getIndexCore()?->getName();

        if ($task->isFullMode()) $this->solrManager->delDocuments("<query>external_resource:true</query>","$coreIndex/update?commit=true");
        $sources = $this->fileService->readFilesFrom($task->isFullMode() ?null: $task->getScheduleAt(), "suplom_externe/$coreIndex") ?? [];

        $itemSF = "";
        foreach ($sources as $file) {
            $content = $this->fileService->readFile($file->getRealPath());
            $item = $this->serializer->deserialize($content,SuplomDto::class,'xml');

            // TODO : mapping SuplomDto -> IndexingNotice

            $itemSF .= "<doc>$item</doc>"; //dump($file->getFilename());
        }
        if (empty($sources)) $this->solrManager->addDocuments($itemSF,"$coreIndex/update?commit=true");

        $task->setScheduleAt(new \DateTime());
        $this->manager->flush();
    }
}