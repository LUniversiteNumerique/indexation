<?php

namespace App\Field\Configurator;

use App\Field\FileField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\{EntityDto,FieldDto};
use function Symfony\Component\String\u;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final readonly class FileConfigurator implements FieldConfiguratorInterface
{
    public function __construct(private ?string $projectDir = ''){}

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return FileField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $configuredBasePath = $field->getCustomOption(FileField::OPTION_BASE_PATH);

        $formattedValue = \is_array($field->getValue())
            ? $this->getFilesPaths($field->getValue(), $configuredBasePath)
            : $this->getFilePath($field->getValue(), $configuredBasePath);
        $field->setFormattedValue($formattedValue);

        $field->setFormTypeOption('upload_filename', $field->getCustomOption(FileField::OPTION_UPLOADED_FILE_NAME_PATTERN));

        // this check is needed to avoid displaying broken images when image properties are optional
        if (null === $formattedValue || '' === $formattedValue || (\is_array($formattedValue) && 0 === \count($formattedValue)) || $formattedValue === rtrim($configuredBasePath ?? '', '/')) {
            $field->setTemplateName('label/empty');
        }

        if (!\in_array($context->getCrud()->getCurrentPage(), [Crud::PAGE_EDIT, Crud::PAGE_NEW], true)) {
            return;
        }

        $relativeUploadDir = $field->getCustomOption(FileField::OPTION_UPLOAD_DIR);
        if (null === $relativeUploadDir) throw new \InvalidArgumentException(sprintf('The "%s" image field must define the directory where the images are uploaded using the setUploadDir() method.', $field->getProperty()));

        $relativeUploadDir = u($relativeUploadDir)->trimStart(\DIRECTORY_SEPARATOR)->ensureEnd(\DIRECTORY_SEPARATOR)->toString();
        $isStreamWrapper = filter_var($relativeUploadDir, \FILTER_VALIDATE_URL);
        if ($isStreamWrapper) $absoluteUploadDir = $relativeUploadDir;
        else $absoluteUploadDir = u($relativeUploadDir)->ensureStart($this->projectDir.\DIRECTORY_SEPARATOR)->toString();

        $field->setFormTypeOption('upload_dir', $absoluteUploadDir);
        $field->setFormTypeOption('file_constraints', $field->getCustomOption(FileField::OPTION_FILE_CONSTRAINTS));
    }

    private function getFilesPaths(?array $images, ?string $basePath): array
    {
        $imagesPaths = [];
        foreach ($images as $image) {
            $imagesPaths[] = $this->getFilePath($image, $basePath);
        }

        return $imagesPaths;
    }

    private function getFilePath(?string $imagePath, ?string $basePath): ?string
    {
        // add the base path only to images that are not absolute URLs (http or https) or protocol-relative URLs (//)
        if (null === $imagePath || 0 !== preg_match('/^(http[s]?|\/\/)/i', $imagePath)) {
            return $imagePath;
        }

        // remove project path from filepath
        $imagePath = str_replace($this->projectDir.\DIRECTORY_SEPARATOR.'public'.\DIRECTORY_SEPARATOR, '', $imagePath);

        return isset($basePath)
            ? rtrim($basePath, '/').'/'.ltrim($imagePath, '/')
            : '/'.ltrim($imagePath, '/');
    }
}
