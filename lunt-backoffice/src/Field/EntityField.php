<?php

namespace App\Field;

use Closure;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Champ personnalisé pour la gestion des entités dans EasyAdmin.
 *
 * @method self setProperty(string $propertyName)
 * @method self setLabel(TranslatableInterface|string|false|null $label)
 * @method self setTemplatePath(string $templatePath)
 * @method self setFormType(string $formType)
 * @method self addCssClass(string $cssClass)
 * @method self setDefaultColumns(string $columns)
 * @method self setCustomOption(string $optionName, mixed $value)
 */
class EntityField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_AUTOCOMPLETE = 'autocomplete';
    public const OPTION_EMBEDDED_CRUD_FORM_CONTROLLER = 'crudControllerFqcn';
    public const OPTION_WIDGET = 'widget';
    public const OPTION_QUERY_BUILDER_CALLABLE = 'queryBuilderCallable';
    /** @internal this option is intended for internal use only */
    public const OPTION_RELATED_URL = 'relatedUrl';
    /** @internal this option is intended for internal use only */
    public const OPTION_DOCTRINE_ASSOCIATION_TYPE = 'associationType';

    public const WIDGET_AUTOCOMPLETE = 'autocomplete';
    public const WIDGET_NATIVE = 'native';

    /** @internal this option is intended for internal use only */
    public const PARAM_AUTOCOMPLETE_CONTEXT = 'autocompleteContext';

    /** @internal this option is intended for internal use only */
    public const OPTION_RENDER_AS_EMBEDDED_FORM = 'renderAsEmbeddedForm';

    public const OPTION_EMBEDDED_CRUD_FORM_NEW_PAGE_NAME = 'crudNewPageName';
    public const OPTION_EMBEDDED_CRUD_FORM_EDIT_PAGE_NAME = 'crudEditPageName';

    /**
     * Crée une nouvelle instance de EntityField.
     *
     * @param string $propertyName
     * @param TranslatableInterface|string|false|null $label Le label du champ.
     * @return self
     */
    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplatePath('admin/fields/entity.html.twig')
            ->setFormType(EntityType::class)
            ->addCssClass('field-association')
            ->setDefaultColumns('col-md-7 col-xxl-6')
            ->setCustomOption(self::OPTION_AUTOCOMPLETE, false)
            ->setCustomOption(self::OPTION_EMBEDDED_CRUD_FORM_CONTROLLER, null)
            ->setCustomOption(self::OPTION_WIDGET, self::WIDGET_AUTOCOMPLETE)
            ->setCustomOption(self::OPTION_QUERY_BUILDER_CALLABLE, null)
            ->setCustomOption(self::OPTION_RELATED_URL, null)
            ->setCustomOption(self::OPTION_DOCTRINE_ASSOCIATION_TYPE, null)
            ->setCustomOption(self::OPTION_RENDER_AS_EMBEDDED_FORM, false)
            ->setCustomOption(self::OPTION_EMBEDDED_CRUD_FORM_NEW_PAGE_NAME, null)
            ->setCustomOption(self::OPTION_EMBEDDED_CRUD_FORM_EDIT_PAGE_NAME, null);
    }

    /**
     * Active l'autocomplétion pour ce champ.
     *
     * @return self
     */
    public function autocomplete(): self
    {
        $this->setCustomOption(self::OPTION_AUTOCOMPLETE, true);

        return $this;
    }

    /**
     * Définit le widget comme natif ou autocomplétion.
     *
     * @param bool $asNative Utiliser le widget natif si true, sinon autocomplétion.
     * @return self
     */
    public function renderAsNativeWidget(bool $asNative = true): self
    {
        $this->setCustomOption(self::OPTION_WIDGET, $asNative ? self::WIDGET_NATIVE : self::WIDGET_AUTOCOMPLETE);

        return $this;
    }

    /**
     * Définit le contrôleur CRUD associé pour le formulaire embarqué.
     *
     * @param string $crudControllerFqcn FQCN du contrôleur CRUD.
     * @return self
     */
    public function setCrudController(string $crudControllerFqcn): self
    {
        $this->setCustomOption(self::OPTION_EMBEDDED_CRUD_FORM_CONTROLLER, $crudControllerFqcn);

        return $this;
    }

    /**
     * Définit une closure personnalisée pour le query builder.
     *
     * @param Closure $queryBuilderCallable
     * @return self
     */
    public function setQueryBuilder(Closure $queryBuilderCallable): self
    {
        $this->setCustomOption(self::OPTION_QUERY_BUILDER_CALLABLE, $queryBuilderCallable);

        return $this;
    }

    /**
     * Affiche le champ comme un formulaire embarqué.
     *
     * @param string|null $crudControllerFqcn
     * @param string|null $crudNewPageName
     * @param string|null $crudEditPageName
     * @return self
     */
    public function renderAsEmbeddedForm(?string $crudControllerFqcn = null, ?string $crudNewPageName = null, ?string $crudEditPageName = null): self
    {
        $this->setCustomOption(self::OPTION_RENDER_AS_EMBEDDED_FORM, true);
        $this->setCustomOption(self::OPTION_EMBEDDED_CRUD_FORM_CONTROLLER, $crudControllerFqcn);
        $this->setCustomOption(self::OPTION_EMBEDDED_CRUD_FORM_NEW_PAGE_NAME, $crudNewPageName);
        $this->setCustomOption(self::OPTION_EMBEDDED_CRUD_FORM_EDIT_PAGE_NAME, $crudEditPageName);

        return $this;
    }
}