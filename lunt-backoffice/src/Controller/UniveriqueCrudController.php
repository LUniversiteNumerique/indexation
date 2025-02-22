<?php

namespace App\Controller;

use App\Entity\Univerique;
use App\Event\AfterUntCreatedEvent;
use App\Repository\UniveriqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action,Actions,Crud,Filters};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField,DateTimeField,IdField,TextField};
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use EasyCorp\Bundle\EasyAdminBundle\Filter\{TextFilter, DateTimeFilter};

class UniveriqueCrudController extends AbstractCrudController
{
    public function __construct(private readonly EventDispatcherInterface $dispatcher){}

    public static function getEntityFqcn(): string
    {
        return Univerique::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)->setEntityLabelInSingular('UNT')->setEntityLabelInPlural("UNT")
            ->setPageTitle(Action::NEW, fn () => 'Créer une <b>UNT</b>')
            ->setPageTitle(Action::EDIT, fn (Univerique $n) => 'Modifier une <b>UNT</b>');
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->setPermission(Action::DETAIL, 'ROLE_READ_UNIV')
            ->setPermission(Action::INDEX, 'ROLE_READ_UNIV')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_UNIV')
            ->setPermission(Action::NEW, 'ROLE_CREA_UNIV')
            ->setPermission(Action::DELETE, 'ROLE_DROP_UNIV')
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER, fn (Action $a) => $a->setLabel('Créer et ajouter une <b>nouvelle</b>'))
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $a) => $a->setLabel('Créer une <b>UNT</b>'))
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->disable(Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('label', 'Nom'),
            TextField::new('name', "Nom du répertoire")->hideWhenUpdating(),
            AssociationField::new('fields', "Champs disciplinaires")->setTemplatePath('admin/fields/badge.html.twig')
                ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->where('entity.parent is null')->orderBy('entity.nom'))->setSortable(false),
            DateTimeField::new('creeLe', 'Date de création')->hideOnForm(),
            DateTimeField::new('editeLe', "Date d'édition")->onlyOnDetail(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('label','Nom'))
            ->add(TextFilter::new('name','Répertoire'))
            ->add(DateTimeFilter::new('creeLe','Date Création')->setFormTypeOption('value_type', DateType::class));
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Univerique $entityInstance */
        parent::persistEntity($entityManager, $entityInstance);
        $this->dispatcher->dispatch(new AfterUntCreatedEvent($entityInstance));
    }

    public function index(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_UNIV');
        return parent::index($context);
    }

    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_CREA_UNIV');
        return parent::new($context);
    }

    public function detail(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_UNIV');
        return parent::detail($context);
    }

    public function edit(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_EDIT_UNIV');
        return parent::edit($context);
    }

    public function delete(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_DROP_UNIV');
        return parent::delete($context);
    }
}
