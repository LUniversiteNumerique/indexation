<?php

namespace App\Form;

use App\Entity\{Discipline, Etablissement, Groupe, User};
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name')->add('email');

        if(!$options['owner']) $builder
            ->add('group', EntityType::class, [
                'class'  => Groupe::class,
            ])
            ->add('school', EntityType::class, [
                'class'  => Etablissement::class,
            ])
            ->add('fields', EntityType::class, [
                'class'  => Discipline::class,
                'multiple'=>true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'owner' => false
        ]);
    }
}
