<?php

namespace App\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Component\Form\Extension\Core\Type\DateIntervalType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Champ personnalisé pour la gestion des durées dans EasyAdmin.
 *
 * Permet de configurer un champ de formulaire basé sur DateIntervalType.
 */
class DurationField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_INPUT = 'input';
    public const OPTION_WIDGET = 'widget';
    public const OPTION_ATTR = 'attr';

    /**
     * Crée une nouvelle instance de DurationField.
     *
     * @param string $propertyName Le nom de la propriété associée au champ.
     * @param TranslatableInterface|string|false|null $label Le label du champ (optionnel).
     * @return self
     */
    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(DateIntervalType::class)
            ->addCssClass('field-text')
            ->setDefaultColumns('col-md-6 col-xxl-5')
            ->setCustomOption(self::OPTION_INPUT, 'string')
            ->setCustomOption(self::OPTION_WIDGET, 'single_text')
            ->setCustomOption(self::OPTION_ATTR, ['placeholder' => 'PT00H00M00S'])
            ->setCustomOption('with_years', false)
            ->setCustomOption('with_months', false)
            ->setCustomOption('with_days', false)
            ->setCustomOption('with_hours', true)
            ->setCustomOption('with_minutes', true)
            ->setCustomOption('with_seconds', true);
    }

    /**
     * Définit une option personnalisée pour le champ.
     *
     * @param string $optionName Le nom de l'option.
     * @param mixed $optionValue La valeur de l'option.
     * @return self
     */
    public function setCustomOption(string $optionName, $optionValue): self
    {
        $this->setFormTypeOption($optionName, $optionValue);

        return $this;
    }
}