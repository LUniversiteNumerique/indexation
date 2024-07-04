<?php

namespace App\Form\Type;

use App\Entity\Keyword;
use App\Form\DataTransformer\TagTransformer;
use App\Repository\KeywordRepository;
use Symfony\Bridge\Doctrine\Form\DataTransformer\CollectionToArrayTransformer;
use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\{AbstractType,FormBuilderInterface};
use Symfony\Component\OptionsResolver\{Options,OptionsResolver};
use Symfony\UX\Autocomplete\Form\{AsEntityAutocompleteField, ChoiceList\Loader\ExtraLazyChoiceLoader};
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEntityAutocompleteField]
final class TagAutoField extends AbstractType
{
    public function __construct(
        private readonly UrlGeneratorInterface $generator,
        private readonly KeywordRepository $repository,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->addModelTransformer(new CollectionToArrayTransformer(), true)
            ->addModelTransformer(new TagTransformer($this->repository), true)
            ->setAttribute('autocomplete_url', $this->getAutocompleteUrl($builder, $options))
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $choiceLoader = static function (Options $options, $loader) {
            if (null === $loader) {
                return null;
            }

            return new ExtraLazyChoiceLoader($loader);
        };

        $resolver->setDefaults([
            'autocomplete' => true,
            'choice_loader' => $choiceLoader,
            // set to the fields to search on or null to search on all fields
            'searchable_fields' => null,
            'filter_query' => null,
            // set to the string role that's required to view the autocomplete results
            // or a callable: function(Symfony\Component\Security\Core\Security $security): bool
            'security' => false,
            // set the max results number that a query on automatic endpoint return.
            'max_results' => 10,
            'choice_label' => 'nom',

            'class' => Keyword::class,
            'placeholder' => 'Sélectionne vos tags',
            'preload' => true,
            'tom_select_options' => ['create' => true, 'createOnBlur' => true],

            'required' => false,'multiple' => true,
            'query_builder' => $this->repository->createQueryBuilder('entity')->where('entity.valide = 1'),
        ]);

        $resolver->setAllowedTypes('security', ['boolean', 'string', 'callable']);
        $resolver->setAllowedTypes('max_results', ['int', 'null']);
        $resolver->setAllowedTypes('filter_query', ['callable', 'null']);
        $resolver->setNormalizer('searchable_fields', function (Options $options, ?array $searchableFields) {
            if (null !== $searchableFields && null !== $options['filter_query'])
                throw new RuntimeException('Both the searchable_fields and filter_query options cannot be set.');

            return $searchableFields;
        });
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'ux_entity_autocomplete';
    }

    private function getAutocompleteUrl(FormBuilderInterface $builder, array $options): string
    {
        if ($options['autocomplete_url']) return $options['autocomplete_url'];

        $formType = $builder->getType()->getInnerType();
        $attribute = AsEntityAutocompleteField::getInstance($formType::class);

        if (!$attribute) throw new \LogicException(sprintf('You must either provide your own autocomplete_url, or add #[AsEntityAutocompleteField] attribute to %s.', $formType::class));

        return $this->generator->generate($attribute->getRoute(), [
            'alias' => $attribute->getAlias() ?: AsEntityAutocompleteField::shortName($formType::class),
        ]);
    }
}
