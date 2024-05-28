<?php

namespace App\Form\Type;

use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\{AbstractType, FormInterface, FormView};
use Symfony\Component\Form\ChoiceList\View\ChoiceView;

class TreeChoiceType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $choices = new ArrayCollection();
        foreach ($view->vars['choices'] as $choice) {
            if ($choice->data?->getParent() === null)
                $choices->set($choice->value, $choice->data);
        }
        $choices = $this->buildTree($choices);
        $view->vars['choices'] = $choices;
    }

    /**
     * Build Tree Choices
     *
     * @param Collection $choices
     * @param integer $level
     * @return array
     */
    protected function buildTree(Collection $choices, int $level = 0): array
    {
        $result = array();
        foreach ($choices as $choice) {
            $result[] = new ChoiceView($choice, (string)$choice->getId(),
                sprintf("%s %s",str_repeat('-', $level),$choice->getNom()));
            if (!$choice->getChildren()->isEmpty())
                $result = array_merge($result, $this->buildTree($choice->getChildren(), $level + 1));
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(): ?string
    {
        return EntityType::class;
    }
}
