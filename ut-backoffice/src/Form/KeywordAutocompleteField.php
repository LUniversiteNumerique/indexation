<?php

namespace App\Form;

use App\Entity\Keyword;
use App\Repository\KeywordRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\{AsEntityAutocompleteField,BaseEntityAutocompleteType};

#[AsEntityAutocompleteField]
class KeywordAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'class' => Keyword::class,
            'placeholder' => 'Choose a Keyword',
            // 'choice_label' => 'name',
            'query_builder' => fn (KeywordRepository $rp) => $rp->createQueryBuilder('keyword'),
            // 'security' => 'ROLE_SOMETHING',
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
