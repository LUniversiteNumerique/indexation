<?php

namespace App\Event\Subscriber;

use App\Event\AfterUntCreatedEvent;
use App\Service\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\{HttpClientInterface,Exception\ExceptionInterface};

#[AsEventListener]
readonly class AfterUntCreatedProcess
{

    public function __construct(
        private HttpClientInterface $solrClient,
        private LoggerInterface $untLogger,
        private string $sorlConfig
    ){}

    public function __invoke(AfterUntCreatedEvent $event): void
    {
        $unt = $event->getUnt();

        // Création des répertoires associés à l'UNT
        $untDir = FileService::RESOURCES_DIR. "XML" .DIRECTORY_SEPARATOR. $unt->getName();
        if (!mkdir($untDir, 0755, true) && !is_dir($untDir))
            throw new \RuntimeException("Failed to create destination directory: $untDir");

        // Création du core Solr de l'UNT à partir du config untconfig
        try {
            $this->solrClient->request("POST", "api/cores", ['json' => ['create' => [
                'configSet' => $this->sorlConfig,
                'name' => $unt->getName()
            ]]]);
            //if($resp->getStatusCode() === Response::HTTP_OK) echo $resp->getContent();
        } catch (ExceptionInterface $e) {
            $this->untLogger->error(sprintf("Erreur lors de l'enregistrement du core SOLR de l'UNT <<%s>>",$unt), [
                'untId' => $unt->getId(), 'exception' => $e->getMessage(),
            ]);
        } //$process = new Process(['cp', '-r', $source, $target]); //docker compose exec -it solr solr create_core -c mycore -d /tmp/myconfig
    }

}
