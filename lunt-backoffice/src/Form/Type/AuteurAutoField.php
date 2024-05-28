<?php

namespace App\Form\Type;

use App\Entity\Auteur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\{AsEntityAutocompleteField, BaseEntityAutocompleteType};

#[AsEntityAutocompleteField]
class AuteurAutoField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Auteur::class,
            'placeholder' => 'Choisir un Auteur',
            'security' => 'ROLE_READ_ACTE',
            //'query_builder' => fn (AuteurRepository $rp) => $rp->createQueryBuilder('keyword'),
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
