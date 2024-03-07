<?php

namespace App\Form;

use App\Entity\Keyword;
use App\Repository\KeywordRepository;
use App\Form\DataTransformer\TagTransformer;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\{AbstractType,FormBuilderInterface};
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
            'required' => false,'multiple' => true,
            'class' => Keyword::class,'query_builder' =>  fn(string $query) => dump($query),
        ]);
    }

    /** {@inheritdoc} */
    public function getParent(): ?string
    {
        return TextType::class;
    }
}
