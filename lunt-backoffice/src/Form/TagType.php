<?php

namespace App\Form;

use App\Entity\Keyword;
use App\Repository\KeywordRepository;
use Symfony\Component\Form\AbstractType;
use App\Form\DataTransformer\TagTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Bridge\Doctrine\Form\DataTransformer\CollectionToArrayTransformer;

class TagType extends AbstractType
{
    public function __construct(private readonly KeywordRepository $repository){}

    /** {@inheritdoc} */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->addModelTransformer(new CollectionToArrayTransformer(), true)
            ->addModelTransformer(new TagTransformer($this->repository), true);
    }

    /** {@inheritdoc} */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'class' => Keyword::class,'multiple' => true,
            'query_builder' =>  function (string $query) {
                dd($query);
            },
        ]);
    }

    /** {@inheritdoc} */
    public function getParent(): ?string
    {
        return TextType::class;
    }
}
