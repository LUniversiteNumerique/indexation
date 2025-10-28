<?php

namespace App\Form\Type;

use App\Entity\Notice;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\{AsEntityAutocompleteField, BaseEntityAutocompleteType};

#[AsEntityAutocompleteField]
class NoticeAutoField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Notice::class,
            'placeholder' => 'Choisir les Notices',
            'multiple' => true,
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
