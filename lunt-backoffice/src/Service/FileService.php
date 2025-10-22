<?php

namespace App\Service;

use DateTime;
use Symfony\Component\{Filesystem\Filesystem, Finder\Finder};
use Symfony\Component\Filesystem\Exception\{IOException,IOExceptionInterface};

class FileService
{
    const RESOURCES_DIR = DIRECTORY_SEPARATOR . 'opt' . DIRECTORY_SEPARATOR . 'lunt-resources' . DIRECTORY_SEPARATOR;
    const REFERENTIELS_DIR = self::RESOURCES_DIR . 'referentiels' . DIRECTORY_SEPARATOR;
    const XML_DIR = self::RESOURCES_DIR . 'XML' . DIRECTORY_SEPARATOR;
    private Filesystem $filesystem;
    public function __construct(private ?string $directory = null)
    {
        $this->directory = $directory ?? self::RESOURCES_DIR;
        $this->filesystem = new Filesystem();
        if (!$this->filesystem->exists($this->directory))
            $this->filesystem->mkdir($this->directory);
    }

    public function writeFile(string $filename, string $content): bool
    {
        try {
            // Chemin complet du fichier
            $filePath = $this->directory . $filename;

            // Écrire le contenu dans le fichier
            $this->filesystem->dumpFile($filePath, $content);

            return true;
        } catch (IOExceptionInterface) {
            return false;
        }
    }

    public function readFile(string $filePath, $isRelative = false): ?string
    {
        $path = $isRelative ? $this->directory. $filePath : $filePath;
        try {
            // Vérifier si le fichier existe
            if (!$this->filesystem->exists($path))
                throw new IOException("Le fichier n'existe pas : $path");

            // Lire le contenu du fichier
            return file_get_contents($path);
        } catch (IOExceptionInterface) { return null; }
    }

    public function writeFilesTo(array $filesContent,string $relativePath = ''): array
    {
        $path = $this->directory. $relativePath;
        if (!$this->filesystem->exists($path)) $this->filesystem->mkdir($path);
        return array_map(fn ($fileName) => $this->writeFile($relativePath.$fileName, $filesContent[$fileName]), array_keys($filesContent));
    }

    /**
     * Récupère les fichiers d'un répertoire selon des critères.
     *
     * @param DateTime|null $since Date minimale de modification
     * @param string $path Répertoire de collecte des fichiers
     * @param int|string $depth Profondeur de recherche
     * @param array $names Filtres de noms de fichiers (ex: ['*.xml'])
     * @return Finder|null Instance de Finder ou null en cas d'erreur
     */
    public function readFilesFrom(
        ?DateTime $since = null,
        string $path = '',
        int|string $depth = 0,
        array $names = ['*.xml']
    ): ?Finder {
        $finder = new Finder();
        if (!$this->filesystem->exists($path)) {
            throw new IOException("Le répertoire n'existe pas : $path");
        }
        $finder->files()->in($path)->name($names)->depth($depth);

        if ($since) {
            $finder->date('>= ' . $since->format('Y-m-d H:i:s'));
        }
        return $finder;
    }

    public function removeFilesFrom(string $relativePath = '', $filenames = null): bool
    {
        $finder = new Finder();
        $path = $this->directory. $relativePath;
        try {
            if (!$this->filesystem->exists($path))
                throw new IOException("Le répertoire n'existe pas : $path");
            if($filenames === null) {
                $finder->files()->in($path);
                foreach ($finder as $file) $this->filesystem->remove($file->getRealPath());
            } else foreach ($filenames as $file) {
                $filePath = sprintf("%s%s_%s.xml", $path, DIRECTORY_SEPARATOR.(str_starts_with($relativePath,'oai') ?'dc':'sf'), $file);
                if ($this->filesystem->exists($filePath)) $this->filesystem->remove($filePath);
            }
            return true;
        } catch (IOExceptionInterface) {
            return false;
        }
    }
}
