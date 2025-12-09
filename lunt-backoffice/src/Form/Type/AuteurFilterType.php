<?php

namespace App\Form\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This class is a paste of EntityFilterType
 * It use SortedAuteurType to order authors alphabetically.
 */
class AuteurFilterType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'comparison_type_options' => ['type' => 'entity'],
            'value_type' => SortedAuteurType::class,
        ]);
    }

    public function getParent(): string
    {
        return ChoiceFilterType::class;
    }
}