<?php

namespace App\Message\Handler;

use DateTime;
use Doctrine\ORM\{EntityManagerInterface,EntityRepository};
use App\Entity\{Dto\IndexingNotice, Dto\SuplomDto, IndexingConfig, Univerique};
use App\Message\ExtexingConfigMessage;
use Exception;
use App\Service\{FileService, SolrApiService};
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler pour l'indexation externe.
 */
#[AsMessageHandler]
readonly class ExterIndexingHandler
{
    private EntityRepository $configRepository;

    /**
     * @param FileService $fileService
     * @param SolrApiService $solrManager
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        private FileService $fileService,
        private SolrApiService $solrManager,
        private LoggerInterface $logger,
        private SerializerInterface $serializer,
        private EntityManagerInterface $entityManager,
    ) {
        $this->configRepository = $this->entityManager->getRepository(IndexingConfig::class);
    }

    /**
     * Traite le message d'indexation externe.
     *
     * @param ExtexingConfigMessage $message
     * @throws Exception
     */
    public function __invoke(ExtexingConfigMessage $message): void
    {
        /** @var IndexingConfig|null $task */
        $task = $this->configRepository->find($message->taskId);
        if (!$task) {
            throw new Exception("Aucun planificateur d'identifiant " . $message->taskId);
        }

        $coreIndex = $task->getIndexCore()?->getName();
        $this->logger->warning(sprintf(
            "Début d'externe indexation %s de %s",
            $task->isFullMode() ? 'complète' : 'différentielle',
            $coreIndex
        ));

        $sources = $this->fileService->readFilesFrom(
            $task->isFullMode() ? null : $task->getScheduleAt(),
            FileService::XML_DIR . $coreIndex . DIRECTORY_SEPARATOR . 'suplom_externe',
            '< 2',
            ['/^(?!.*_hdr\.xml$).*\.xml$/i']
        );

        if ($sources && $sources->count()) {
            $newItems = [];
            $oldUuids = [];
            $deleteQuery = null;

            foreach ($sources as $file) {
                /** @var SuplomDto $item */
                $item = $this->serializer->deserialize(
                    $this->fileService->readFile($file->getRealPath()),
                    SuplomDto::class,
                    'xml'
                );

                if ($file->getMTime() > $task->getScheduleAt()?->getTimestamp()) {
                    $oldUuids[] = substr($item?->general?->identifier?->entry, -36);
                }
                $newItems[] = $item;
            }

            if ($task->isFullMode()) {
                $deleteQuery = "<query>external_resource:true</query>";
            } elseif (count($oldUuids) > 0) {
                $deleteQuery = array_reduce(
                    $oldUuids,
                    fn(string $acc, string $uuid): string => $acc . "<query>uuid:$uuid</query>",
                    ""
                );
            }

            if ($deleteQuery) {
                $this->solrManager->delDocuments($deleteQuery, "$coreIndex/update?commit=true");
            }

            $this->logger->info(sprintf("%d notices concernées", count($newItems)));
            $this->solrManager->addDocuments(
                $this->pushFromSuplom($newItems, $task->getIndexCore()),
                "$coreIndex/update?commit=true"
            );

            $task->setScheduleAt(new DateTime());
            $this->entityManager->flush();
            unset($sources);
        }

        $this->logger->warning(sprintf(
            "Fin d'externe indexation %s de %s",
            $task->isFullMode() ? 'complète' : 'différentielle',
            $coreIndex
        ));
    }

    /**
     * Prépare les documents à indexer à partir de SuplomDto.
     *
     * @param SuplomDto[] $sources
     * @param Univerique $univerique
     * @return string
     */
    public function pushFromSuplom(array $sources, Univerique $univerique): string
    {
        $itemsXml = '';
        foreach ($sources as $suplom) {
            $notice = IndexingNotice::fromSuplom($suplom, $univerique);
            $itemXml = $this->serializer->serialize($notice, 'xml');
            $itemsXml .= preg_replace('/<\?xml.*?\?>/', '', $itemXml);
        }
        return "<add>$itemsXml</add>";
    }
}