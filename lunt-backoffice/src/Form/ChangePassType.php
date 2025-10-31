<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\{PasswordType, RepeatedType};
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\{Length, NotBlank};

class ChangePassType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['user_logged']) {
            $builder->add(
                'currentPassword',
                PasswordType::class,
                [
                    'constraints' => [new UserPassword(),],
                    'label' => 'Mot de passe actuel',
                    'mapped' => false,
                    'attr' => ['autocomplete' => 'off',],
                ]
            );
        }
        $builder->add(
            'newPassword',
            RepeatedType::class,
            [
                'type' => PasswordType::class,
                'constraints' => [new NotBlank(), new Length(min: 5, max: 128,),],
                'second_options' => ['label' => 'Confirmer le mot de passe',],
                'first_options' => [
                    'hash_property_path' => 'password',
                    'label' => 'Nouveau mot de passe',
                ],
                'mapped' => false,
            ]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'user_logged' => true,
        ]);
    }
}
