<?php

namespace App\Form\Type;

use App\Entity\Notice;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\{AsEntityAutocompleteField,BaseEntityAutocompleteType};

#[AsEntityAutocompleteField]
class NoticeAutoField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Notice::class,
            'placeholder' => 'Choisir les Notices',
            // 'choice_label' => 'name',

            // choose which fields to use in the search
            // if not passed, *all* fields are used
            // 'searchable_fields' => ['name'],

            'multiple' => true,// 'security' => 'ROLE_SOMETHING',
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
