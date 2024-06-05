<?php

namespace App\Message\Handler;

use Doctrine\ORM\{EntityManagerInterface,EntityRepository};
use App\Entity\{IndexingConfig,Dto\SuplomDto};
use App\Message\ExtexingConfigMessage;
use App\Service\{FileService, SolrApiService};
use JMS\Serializer\SerializerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ExterIndexingHandler
{
    private FileService $fileService;
    private EntityRepository $configRep;
    public function __construct(
        private SolrApiService $solrManager,
        private SerializerInterface $serializer,
        private EntityManagerInterface $manager,
        #[Autowire('%kernel.project_dir%/var/files')] private string $directory,
    ){
        $this->fileService = new FileService($this->directory);
        $this->configRep = $this->manager->getRepository(IndexingConfig::class);
    }

    public function __invoke(ExtexingConfigMessage $message): void
    {
        /** @var IndexingConfig $task */
        $task = $this->configRep->find($message->taskId);
        if (!$task) throw new \Exception("Aucun planificateur d'identifant ".$message->taskId);
        $coreIndex = "unt".$task->getIndexCore()?->getId();

        if ($task->isFullMode()) $this->solrManager->delDocuments("<query>external_resource:true</query>","$coreIndex/update?commit=true");
        $sources = $this->fileService->readFilesFrom($task->isFullMode() ?null: $task->getScheduleAt(), $coreIndex);

        $itemSF = "";
        foreach ($sources as $file) {
            $content = $this->fileService->readFile($file->getRealPath());
            $item = $this->serializer->deserialize($content,SuplomDto::class,'xml');
            $itemSF .= "<doc>$item</doc>"; //dump($file->getFilename());
        }
        $this->solrManager->addDocuments($itemSF,"$coreIndex/update?commit=true");

        $task->setScheduleAt(new \DateTime());
        $this->manager->flush();;
    }
}