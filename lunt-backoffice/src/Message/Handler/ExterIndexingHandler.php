<?php

namespace App\Message\Handler;

use App\Entity\IndexingConfig;
use App\Message\ExtexingConfigMessage;
use App\Repository\IndexingConfigRepository;
use App\Service\{FileService, SolrApiService, XmlDataLoader};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ExterIndexingHandler
{
    const EXTERNAL_RESOURCE = "external_resource:true";
    private FileService $fileService;
    public function __construct(
        private XmlDataLoader $dataLoader,
        private SolrApiService $solrManager,
        private IndexingConfigRepository $configRep,
        #[Autowire('%kernel.project_dir%/var/files')] private string $directory,
    ){
        $this->fileService = new FileService($this->directory);
    }

    public function __invoke(ExtexingConfigMessage $message): void
    {
        $oldConfig = $this->configRep->findLatest(true);
        if(!$oldConfig) $oldConfig = new IndexingConfig($message->isFullExec, true);
        $newConfig = new IndexingConfig($message->isFullExec, true);

        if ($message->isFullExec) $this->solrManager->delDocuments(sprintf("<query>%s</query>",self::EXTERNAL_RESOURCE));
        $sources = $this->fileService->readFilesFrom($message->isFullExec ?null: $oldConfig->getScheduleAt());

        $itemSF = "";
        foreach ($sources as $file) {
            $content = $this->fileService->readFile($file->getRealPath());
            $item = $this->dataLoader->decode($content);
            $itemSF .= "<doc>$item</doc>"; //dump($file->getFilename());
        }
        $this->solrManager->addDocuments($itemSF); //Indexation dans Solr
        $di = $newConfig->getScheduleAt()->diff(new \DateTime());

        // Mise à jour des statistiques de l'indexation
        $indexedFiles = $this->solrManager->getDocuments(self::EXTERNAL_RESOURCE);
        if($indexedFiles) $newConfig->setFilesOut($indexedFiles['numFound']);
        $newConfig->setInDuration($di->s+($di->i*60)+($di->h*3600)+($di->days*86400));
        $this->configRep->add($newConfig->setFilesIn(count($sources)));
    }
}