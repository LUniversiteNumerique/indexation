<?php

namespace App\Form\Type;

use App\Entity\Keyword;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\{AsEntityAutocompleteField, BaseEntityAutocompleteType};
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEntityAutocompleteField]
class TagAutoField extends AbstractType
{
    public function __construct(private readonly UrlGeneratorInterface $router,) {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choice_label' => 'nom',
            'class' => Keyword::class,
            'placeholder' => 'Sélectionne vos tags',
            'preload' => true,
            'required' => false,'multiple' => true,
            'tom_select_options' => ['create' => true, 'createOnBlur' => true],
            'attr' => [
                'data-controller' => 'tag-autocreate',
                'data-tag-autocreate-url-value' => $this->router->generate('app_keywork_new'),
            ],
            //'query_builder' => fn(KeywordRepository $rep) => $rep->createQueryBuilder('entity')->orderBy('entity.nom', 'ASC'),
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
