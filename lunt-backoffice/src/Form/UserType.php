<?php

namespace App\Form;

use App\Entity\{Etablissement, Groupe, Univerique, User};
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\{AbstractType,
    Extension\Core\Type\EmailType,
    Extension\Core\Type\TextType,
    FormBuilderInterface};
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, ['label' => 'Nom utilisateur'])
            ->add('email', EmailType::class);

        if(!$options['owner']) $builder
            ->add('group', EntityType::class, ['class'  => Groupe::class,])
            ->add('school', EntityType::class, ['class'  => Etablissement::class,])
            ->add('untheme', EntityType::class, ['class'  => Univerique::class,])
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
