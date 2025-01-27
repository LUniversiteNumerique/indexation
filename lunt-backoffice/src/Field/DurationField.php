<?php

namespace App\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Component\Form\Extension\Core\Type\DateIntervalType;
use Symfony\Contracts\Translation\TranslatableInterface;

class DurationField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_INPUT = 'input';
    public const OPTION_WIDGET = 'widget';
    public const OPTION_ATTR = 'attr';

    /**
     * @param TranslatableInterface|string|false|null $label
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
            ->setCustomOption('with_seconds', true)
        ;
    }

    public function setCustomOption(string $optionName, $optionValue): self
    {
        $this->setFormTypeOption($optionName,$optionValue);

        return $this;
    }
}