<?php

namespace App\Field\Configurator;

use App\Field\FileField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\{EntityDto, FieldDto};
use InvalidArgumentException;
use function Symfony\Component\String\u;

/**
 * Configureur pour le champ fichier dans EasyAdmin.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final readonly class FileConfigurator implements FieldConfiguratorInterface
{
    /**
     * @param string|null $projectDir Répertoire racine du projet
     */
    public function __construct(private ?string $projectDir = '') {}

    /**
     * Vérifie si le configurateur supporte le champ donné.
     *
     * @param FieldDto $field
     * @param EntityDto $entityDto
     * @return bool
     */
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return FileField::class === $field->getFieldFqcn();
    }

    /**
     * Configure le champ fichier.
     *
     * @param FieldDto $field
     * @param EntityDto $entityDto
     * @param AdminContext $context
     * @return void
     */
    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $configuredBasePath = $field->getCustomOption(FileField::OPTION_BASE_PATH);

        $formattedValue = is_array($field->getValue())
            ? $this->getFilesPaths($field->getValue(), $configuredBasePath)
            : $this->getFilePath($field->getValue(), $configuredBasePath);

        $field->setFormattedValue($formattedValue);
        $field->setFormTypeOption('upload_filename', $field->getCustomOption(FileField::OPTION_UPLOADED_FILE_NAME_PATTERN));

        if ($this->isEmptyValue($formattedValue, $configuredBasePath)) {
            $field->setTemplateName('label/empty');
        }

        if (!in_array($context->getCrud()->getCurrentPage(), [Crud::PAGE_EDIT, Crud::PAGE_NEW], true)) {
            return;
        }

        $relativeUploadDir = $field->getCustomOption(FileField::OPTION_UPLOAD_DIR);
        if (null === $relativeUploadDir) {
            throw new InvalidArgumentException(sprintf(
                'The "%s" image field must define the directory where the images are uploaded using the setUploadDir() method.',
                $field->getProperty()
            ));
        }

        $absoluteUploadDir = $this->getAbsoluteUploadDir($relativeUploadDir);
        $field->setFormTypeOption('upload_dir', $absoluteUploadDir);
        $field->setFormTypeOption('file_constraints', $field->getCustomOption(FileField::OPTION_FILE_CONSTRAINTS));
    }

    /**
     * Retourne les chemins des fichiers.
     *
     * @param array|null $images
     * @param string|null $basePath
     * @return array
     */
    private function getFilesPaths(?array $images, ?string $basePath): array
    {
        return array_map(fn($image) => $this->getFilePath($image, $basePath), $images ?? []);
    }

    /**
     * Retourne le chemin du fichier.
     *
     * @param string|null $imagePath
     * @param string|null $basePath
     * @return string|null
     */
    private function getFilePath(?string $imagePath, ?string $basePath): ?string
    {
        // add the base path only to images that are not absolute URLs (http or https) or protocol-relative URLs (//)
        if (null === $imagePath || 0 !== preg_match('/^(http[s]?|\/\/)/i', $imagePath)) {
            return $imagePath;
        }

        // remove project path from filepath
        $imagePath = str_replace($this->projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR, '', $imagePath);

        return isset($basePath)
            ? rtrim($basePath, '/') . '/' . ltrim($imagePath, '/')
            : '/' . ltrim($imagePath, '/');
    }

    /**
     * Vérifie si la valeur du champ est vide.
     *
     * @param mixed $value
     * @param string|null $basePath
     * @return bool
     */
    private function isEmptyValue(mixed $value, ?string $basePath): bool
    {
        return null === $value
            || '' === $value
            || (is_array($value) && 0 === count($value))
            || $value === rtrim($basePath ?? '', '/');
    }

    /**
     * Retourne le chemin absolu d'upload.
     *
     * @param string $relativeUploadDir
     * @return string
     */
    private function getAbsoluteUploadDir(string $relativeUploadDir): string
    {
        $relativeUploadDir = u($relativeUploadDir)->trimStart(DIRECTORY_SEPARATOR)->ensureEnd(DIRECTORY_SEPARATOR)->toString();
        $isStreamWrapper = filter_var($relativeUploadDir, FILTER_VALIDATE_URL);

        return $isStreamWrapper
            ? $relativeUploadDir
            : u($relativeUploadDir)->ensureStart($this->projectDir . DIRECTORY_SEPARATOR)->toString();
    }
}
