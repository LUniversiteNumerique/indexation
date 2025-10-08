<?php

namespace App\Event\Subscriber;

use App\Event\AfterUntCreatedEvent;
use App\Service\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

/**
 * Abonné à l'événement AfterUntCreatedEvent pour gérer la création des répertoires
 * et du core Solr associé à une UNT.
 */
#[AsEventListener]
readonly class AfterUntCreatedProcess
{
    /**
     * @param HttpClientInterface $solrClient  Client HTTP pour communiquer avec Solr
     * @param LoggerInterface     $untLogger   Logger pour les erreurs liées à l'UNT
     * @param string              $sorlConfig  Nom du configSet Solr à utiliser
     */
    public function __construct(
        private HttpClientInterface $solrClient,
        private LoggerInterface $untLogger,
        private string $sorlConfig
    ) {}

    /**
     * Gère la création des répertoires et du core Solr lors de la création d'une UNT.
     *
     * @param AfterUntCreatedEvent $event
     * @return void
     */
    public function __invoke(AfterUntCreatedEvent $event): void
    {
        $unt = $event->getUnt();

        // Création des répertoires associés à l'UNT
        $untDirXml = FileService::RESOURCES_DIR . "XML" . DIRECTORY_SEPARATOR . $unt->getName();
        $untDirSuplom = FileService::RESOURCES_DIR . "suplom_externe" . DIRECTORY_SEPARATOR . $unt->getName();

        foreach ([$untDirXml, $untDirSuplom] as $dir) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new \RuntimeException("Failed to create destination directory: $dir");
            }
        }

        // Création du core Solr de l'UNT à partir du config untconfig
        try {
            $this->solrClient->request("POST", "api/cores", [
                'json' => [
                    'create' => [
                        'configSet' => $this->sorlConfig,
                        'name' => $unt->getName()
                    ]
                ]
            ]);
        } catch (ExceptionInterface $e) {
            $this->untLogger->error(
                sprintf("Erreur lors de l'enregistrement du core SOLR de l'UNT <<%s>>", $unt),
                [
                    'untId' => $unt->getId(),
                    'exception' => $e->getMessage(),
                ]
            );
        }
    }
}