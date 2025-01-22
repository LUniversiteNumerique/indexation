<?php

namespace App\Controller;

use App\Entity\User;
use App\Event\UserPassSettingEvent;
use App\Field\EntityField;
use Doctrine\ORM\{EntityManagerInterface,QueryBuilder};
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, Filters};
use EasyCorp\Bundle\EasyAdminBundle\Collection\{FieldCollection,FilterCollection};
use EasyCorp\Bundle\EasyAdminBundle\Dto\{EntityDto,SearchDto};
use EasyCorp\Bundle\EasyAdminBundle\{Controller\AbstractCrudController, Context\AdminContext, Router\AdminUrlGenerator};
use EasyCorp\Bundle\EasyAdminBundle\Field\{BooleanField, DateTimeField, EmailField, FormField, IdField, TextField};
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TokenGeneratorInterface $tokGenerator,
        private readonly AdminUrlGenerator $urlGenerator,
        private readonly EventDispatcherInterface $dispatcher
    ) {}

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('name')  
            ->add('email')    
            ->add('enabled')
            ->add('group')
            ->add('school')
            ->add('untheme');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('utilisateur')->setEntityLabelInPlural('Utilisateurs')
            ->setSearchFields(['name', 'email'])->setDefaultSort(['name' => 'ASC'])->setEntityPermission('ROLE_READ_USER')->setFormOptions([
                'attr' => ['data-controller'=>"user-creating", 'data-user-creating-target'=>"form"]
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->setPermission(Action::INDEX, 'ROLE_READ_USER')
            ->setPermission(Action::NEW, 'ROLE_CREA_USER')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_USER')
            ->setPermission(Action::DELETE, 'ROLE_DROP_USER')
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->add(Crud::PAGE_EDIT, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_EDIT, Action::DETAIL, fn (Action $a) => $a->setIcon('fa fa-undo')->setLabel('Annuler les modifications'))
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addColumn(6);
        yield FormField::addFieldset();
        yield IdField::new('id')->onlyOnDetail();
        yield TextField::new('name','Nom');
        yield EmailField::new('email')->setSortable(false);
        yield BooleanField::new('enabled','Statut')->setSortable(false);
        yield DateTimeField::new('creeLe', 'Date création')->onlyOnDetail();
        yield DateTimeField::new('editeLe', 'Date modification')->onlyOnDetail();

        yield FormField::addColumn(6);
        yield FormField::addFieldset();
        yield EntityField::new('group','Groupe')->setFormTypeOption('attr', ['data-user-creating-target' => 'masterSelect',]);
        yield EntityField::new('school','Etablissement Contributeur')->setRequired(true);
        yield EntityField::new('untheme',"UNT Documentaliste")->setRequired(true);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->join('entity.group','g')
            ->leftJoin('entity.school','e')
            ->leftJoin('entity.untheme','f')
            ->select('entity,g,e,f');
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param User $entityInstance
     * @return void
     * @throws \DateMalformedStringException
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->dispatcher->dispatch(new UserPassSettingEvent($entityInstance->setReseToken($this->tokGenerator->generateToken())));

        parent::persistEntity($entityManager, $entityInstance->setTokenExpiresAt(new \DateTimeImmutable(User::VALIDATIME_TOKEN.' min')));
    }

    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $submitButtonName = $context->getRequest()->request->all()['ea']['newForm']['btn'];

        $url = match ($submitButtonName) {
            Action::SAVE_AND_CONTINUE => $this->urlGenerator->setAction(Action::EDIT)
                ->setEntityId($context->getEntity()->getPrimaryKeyValue())->generateUrl(),
            Action::SAVE_AND_RETURN => $context->getReferrer() ?? $this->urlGenerator->setAction(Action::DETAIL)
                    ->setEntityId($context->getEntity()->getPrimaryKeyValue())->generateUrl(),
            Action::SAVE_AND_ADD_ANOTHER => $this->urlGenerator->setAction(Action::NEW)->generateUrl(),
            default => $this->generateUrl($context->getDashboardRouteName()),
        };

        return $this->redirect($url);
    }
}
