<?php

namespace App\Service;

use DateTime;
use Symfony\Component\{Filesystem\Filesystem, Finder\Finder};
use Symfony\Component\Filesystem\Exception\{IOException,IOExceptionInterface};

/**
 * Service de gestion des fichiers et répertoires.
 */
class FileService
{
    /** @var string Chemin du répertoire des ressources */
    public const RESOURCES_DIR = DIRECTORY_SEPARATOR . 'opt' . DIRECTORY_SEPARATOR . 'lunt-resources' . DIRECTORY_SEPARATOR;
    /** @var string Chemin du répertoire des référentiels */
    public const REFERENTIELS_DIR = self::RESOURCES_DIR . 'referentiels' . DIRECTORY_SEPARATOR;
    /** @var string Chemin du répertoire XML */
    public const XML_DIR = self::RESOURCES_DIR . 'XML' . DIRECTORY_SEPARATOR;

    /** @var Filesystem */
    private Filesystem $filesystem;

    /** @var string */
    private string $directory;

    /**
     * @param string|null $directory Répertoire de base pour les opérations sur les fichiers
     */
    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? self::RESOURCES_DIR;
        $this->filesystem = new Filesystem();

        if (!$this->filesystem->exists($this->directory)) {
            $this->filesystem->mkdir($this->directory);
        }
    }

    /**
     * Écrit le contenu dans un fichier.
     *
     * @param string $filename Nom du fichier (relatif au répertoire de base)
     * @param string $content Contenu à écrire
     * @return bool Succès de l'opération
     */
    public function writeFile(string $filename, string $content): bool
    {
        $filePath = $this->directory . $filename;
        try {
            $this->filesystem->dumpFile($filePath, $content);
            return true;
        } catch (IOExceptionInterface) {
            return false;
        }
    }

    /**
     * Lit le contenu d'un fichier.
     *
     * @param string $filePath Chemin du fichier (relatif ou absolu)
     * @param bool $isRelative Indique si le chemin est relatif au répertoire de base
     * @return string|null Contenu du fichier ou null en cas d'erreur
     */
    public function readFile(string $filePath, bool $isRelative = false): ?string
    {
        $path = $isRelative ? $this->directory . $filePath : $filePath;
        try {
            if (!$this->filesystem->exists($path)) {
                throw new IOException("Le fichier n'existe pas : $path");
            }
            return file_get_contents($path);
        } catch (IOExceptionInterface) {
            return null;
        }
    }

    /**
     * Écrit plusieurs fichiers dans un répertoire.
     *
     * @param array $filesContent Tableau associatif [nomFichier => contenu]
     * @param string $path Répertoire
     * @return array Résultats booléens pour chaque fichier
     */
    public function writeFilesTo(array $filesContent, string $path = ''): array
    {
        if (!$this->filesystem->exists($path)) {
            $this->filesystem->mkdir($path);
        }
        return array_map(
            fn($fileName) => $this->writeFile($path . $fileName, $filesContent[$fileName]),
            array_keys($filesContent)
        );
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

    /**
     * Supprime des fichiers d'un répertoire.
     *
     * @param string $path Répertoire
     * @param array|null $filenames Liste des noms de fichiers à supprimer (sans extension), ou null pour tout supprimer
     * @return bool Succès de l'opération
     */
    public function removeFilesFrom(string $path = '', ?array $filenames = null): bool
    {
        $finder = new Finder();
        try {
            if (!$this->filesystem->exists($path)) {
                throw new IOException("Le répertoire n'existe pas : $path");
            }
            if ($filenames === null) {
                $finder->files()->in($path);
                foreach ($finder as $file) {
                    $this->filesystem->remove($file->getRealPath());
                }
            } else {
                foreach ($filenames as $file) {
                    $filePath = sprintf("%s%s.xml", $path, $file);
                    if ($this->filesystem->exists($filePath)) {
                        $this->filesystem->remove($filePath);
                    }
                }
            }
            return true;
        } catch (IOExceptionInterface) {
            return false;
        }
    }
}
