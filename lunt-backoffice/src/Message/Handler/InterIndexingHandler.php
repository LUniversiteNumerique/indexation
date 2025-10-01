<?php

namespace App\Message\Handler;

use App\Entity\{IndexingConfig, Notice, NoticEtat, Univerique};
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use App\Entity\Dto\{OaidcDto, SuplomDto, IndexingNotice};
use App\Service\{FileService, SolrApiService};
use App\Message\IntexingConfigMessage;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class InterIndexingHandler
{
    private EntityRepository $configRep;
    private EntityRepository $noticeRep;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface    $serializer,
        private LoggerInterface        $logger,
        private SolrApiService         $solrService,
        private FileService            $fileService,
    ) {
        $this->noticeRep = $this->entityManager->getRepository(Notice::class);
        $this->configRep = $this->entityManager->getRepository(IndexingConfig::class);
    }

    public function __invoke(IntexingConfigMessage $message): void
    {
        /** @var IndexingConfig|null $task */
        $task = $this->configRep->find($message->taskId);
        if (!$task) {
            throw new \RuntimeException("Aucun planificateur d'identifiant " . $message->taskId);
        }

        $core = $task->getIndexCore()?->getName();
        $offset = 0;
        $this->logger->warning(sprintf(
            "Début d'indexation interne %s de %s",
            $task->isFullMode() ? 'complète' : 'différentielle',
            $core
        ));

        while (!empty($notices = $this->noticeRep->findFrom($task, $task->getBatchSize(), $offset))) {
            [$toIndex, $toUnindex] = $this->filterNotices($notices, $task);

            $this->solrService->editDocuments(
                $this->buildUnindexXml($toUnindex, $core) . $this->buildIndexXml($toIndex, $task->getIndexCore()),
                "$core/update?commit=true"
            );

            $task->setScheduleAt(new \DateTime());
            $this->entityManager->flush();
            $this->entityManager->clear();

            $this->logger->info(sprintf(
                "Notices concernées %d:  %d (indexées) + %d (désindexées)",
                count($notices), count($toIndex), count($toUnindex)
            ));
            $offset += $task->getBatchSize();
        }

        $this->logger->warning(sprintf(
            "Fin d'indexation interne %s de %s",
            $task->isFullMode() ? 'complète' : 'différentielle',
            $core
        ));
    }

    /**
     * Filtre les notices à indexer et à désindexer.
     *
     * @param Notice[] $notices
     * @param IndexingConfig $task
     * @return array{0: Notice[], 1: array}
     */
    private function filterNotices(array $notices, IndexingConfig $task): array
    {
        $toIndex = [];
        $toUnindex = [];

        foreach ($notices as $notice) {
            if ($notice->getEtat() === NoticEtat::Approved) {
                if (empty($notice->getPublieLe())) {
                    // A publier
                    $toIndex[] = $notice->setPublieLe(new \DateTime());
                } elseif ($task->isFullMode() || $notice->getEditeLe() > $task->getScheduleAt()) {
                    // A republier
                    $toUnindex[] = $notice->getUuid();
                    $toIndex[] = $notice;
                }
            } elseif ($notice->getPublieLe()) {
                // A dépublier
                $toUnindex[] = $notice->setPublieLe(null)->getUuid();
            }
        }

        return [$toIndex, $toUnindex];
    }

    /**
     * Génère le XML pour désindexer des notices.
     *
     * @param array $uuids
     * @param string $index
     * @return string
     */
    private function buildUnindexXml(array $uuids, string $index): string
    {
        $deleteQueries = array_reduce(
            $uuids,
            fn(string $acc, string $uuid): string => $acc . "<query>uuid:$uuid</query>",
            ""
        );

        $this->fileService->removeFilesFrom("oai/$index", $uuids);
        $this->fileService->removeFilesFrom("suplom/$index", $uuids);

        return $deleteQueries ? "<delete>$deleteQueries</delete>" : '';
    }

    /**
     * Génère le XML pour indexer des notices et gère l'écriture des fichiers associés.
     *
     * @param Notice[] $notices
     * @param Univerique $indexCore
     * @return string
     */
    private function buildIndexXml(array $notices, Univerique $indexCore): string
    {
        $index = $indexCore->getName();
        $itemSF = [];
        $itemSP = "";
        $itemOaiDC = [];
        $itemOaiSF = [];

        foreach ($notices as $notice) {
            $isOai = $notice->isExportOAI();
            $indexingNotice = IndexingNotice::fromNotice($notice, $indexCore);
            $xmlContent = $this->serializer->serialize($indexingNotice, 'xml');
            $cleanXml = preg_replace('/<\?xml.*?\?>/', '', $xmlContent);
            $itemSP .= $cleanXml;

            $uuid = $this->extractUuid($notice->getUuid());
            $dcKey = "dc_{$uuid}.xml";
            $sfKey = "sf_{$uuid}.xml";

            if ($isOai) {
                $oaidcOaiDto = OaidcDto::create($notice);
                $itemOaiDC[$dcKey] = $this->serializer->serialize($oaidcOaiDto, 'xml');
                $suplomOaiDto = SuplomDto::create($notice);
                $itemOaiSF[$sfKey] = $this->serializer->serialize($suplomOaiDto, 'xml');
            }
            $suplomDto = SuplomDto::create($notice);
            $itemSF[$sfKey] = $this->serializer->serialize($suplomDto, 'xml');
        }

        // Indexation oai et suplom JOAI
        $this->fileService->writeFilesTo($itemOaiSF, "XML/$index/suplomfr/");
        $this->fileService->writeFilesTo($itemOaiDC, "XML/$index/oai_dc/");

        // Indexation suplom solr
        $this->fileService->writeFilesTo($itemSF, "suplom/$index/");

        return $itemSP ? "<add>$itemSP</add>" : '';
    }

    /**
     * Extrait l'UUID d'une notice, même si elle contient une URL.
     *
     * @param string $uuid
     * @return string
     */
    private function extractUuid(string $uuid): string
    {
        if (str_contains($uuid, "http://")) {
            $parts = explode('/uid/', $uuid);
            return end($parts);
        }
        return $uuid;
    }
}
