<?php

namespace App\Form\Type;

use App\Entity\Keyword;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\{AsEntityAutocompleteField, BaseEntityAutocompleteType};

#[AsEntityAutocompleteField]
class TagAutoField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choice_label' => 'nom',
            'class' => Keyword::class,
            'placeholder' => 'Sélectionne vos tags',
            'preload' => true, 'multiple' => true, 'required' => false,
            'tom_select_options' => ['create' => false]
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
