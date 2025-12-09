<?php

namespace App\Form\Type;

use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Form\ChoiceList\ORMQueryBuilderLoader;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class SortedAuteurType extends EntityType
{
    public function getLoader(ObjectManager $manager, object $queryBuilder, string $class): ORMQueryBuilderLoader
    {
        $alias = $queryBuilder->getAllAliases()[0];
        $queryBuilder->orderBy($alias . '.nom', 'ASC')->addOrderBy($alias . '.prenom', 'ASC');

        return new ORMQueryBuilderLoader($queryBuilder);
    }
}