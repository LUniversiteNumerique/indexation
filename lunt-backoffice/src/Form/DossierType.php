<?php

namespace App\Form;

use App\Entity\Dossier;
use App\Form\Type\TreeChoiceType;
use Symfony\Component\Form\{AbstractType,FormBuilderInterface};
use Symfony\Component\OptionsResolver\OptionsResolver;

class DossierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('dossier', TreeChoiceType::class, ['class'  => Dossier::class]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
