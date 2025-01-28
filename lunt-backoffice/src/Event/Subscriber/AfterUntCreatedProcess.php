<?php

namespace App\Event\Subscriber;

use App\Event\AfterUntCreatedEvent;
use App\Service\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Process\{Process, Exception\ProcessFailedException};

#[AsEventListener]
final class AfterUntCreatedProcess
{
    const SUB_DIR = ["oai", "suplom", "suplom_externe"];
    private string $diresource;

    public function __construct(private readonly LoggerInterface $untLogger)
    {
        $this->diresource = FileService::RESOURCES_DIR. DIRECTORY_SEPARATOR. 'lunt-resources'. DIRECTORY_SEPARATOR;
    }

    public function __invoke(AfterUntCreatedEvent $event): void
    {
        $unt = $event->getUnt();
        $target = FileService::RESOURCES_DIR. DIRECTORY_SEPARATOR. 'lunt-solr'. DIRECTORY_SEPARATOR. $unt->getName();

        // Création des répertoires associés à l'UNT
        foreach (self::SUB_DIR as $subPath) {
            $untDir = $this->diresource. $subPath .DIRECTORY_SEPARATOR. $unt->getName();
            if (!mkdir($untDir, 0755, true) && !is_dir($untDir))
                throw new \RuntimeException("Failed to create destination directory: $untDir");
        }

        // création du dossier Solr de l'UNT à partir d'un template par défaut
        $source = $this->diresource. 'untsolr_defaultemplate';
        $command = ['docker', 'compose', 'exec', '-it', 'solr', 'solr', 'create_core', '-c', $unt->getName()];
        $process = new Process(['cp', '-r', $source, $target]); //docker compose exec -it solr solr create_core -c mycore -d /tmp/myconfig

        try {
            $process->mustRun(); // Ensure the command runs successfully
        } catch (ProcessFailedException $exception) {
            $this->untLogger->error(sprintf("Erreur lors de la création des repertoires de l'UNT <<%s>>",$unt), [
                'untId' => $unt->getId(), 'exception' => $exception->getMessage(),
            ]);
        }
    }

}