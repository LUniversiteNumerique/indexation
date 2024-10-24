<?php

namespace App\Controller;

use App\Entity\User;
use App\Field\EntityField;
use App\Service\MailerService;
use Doctrine\ORM\{EntityManagerInterface,QueryBuilder};
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, Filters};
use EasyCorp\Bundle\EasyAdminBundle\Collection\{FieldCollection,FilterCollection};
use EasyCorp\Bundle\EasyAdminBundle\Dto\{EntityDto,SearchDto};
use EasyCorp\Bundle\EasyAdminBundle\{Controller\AbstractCrudController, Context\AdminContext, Filter\EntityFilter, Router\AdminUrlGenerator};
use EasyCorp\Bundle\EasyAdminBundle\Field\{BooleanField, DateTimeField, EmailField, FormField, IdField, TextField};
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TokenGeneratorInterface $tokenGenerator,
        private readonly AdminUrlGenerator $urlGenerator,
        private readonly MailerService $mailer) {}

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('enabled')->add('group')
            ->add(EntityFilter::new('school'))->add('untheme');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Utilisateur')->setEntityLabelInPlural('Utilisateurs')
            ->setSearchFields(['name', 'email'])->setDefaultSort(['name' => 'ASC'])->setEntityPermission('ROLE_READ_USER');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->add(Crud::PAGE_EDIT, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->setPermission(Action::NEW, 'ROLE_CREA_USER')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_USER')
            ->setPermission(Action::DELETE, 'ROLE_DROP_USER')
            ->update(Crud::PAGE_EDIT, Action::DETAIL, fn (Action $a) => $a->setIcon('fa fa-undo')->setLabel('Annuler les modifications'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addColumn(6);
        yield FormField::addFieldset();
        yield IdField::new('id')->onlyOnDetail();
        yield TextField::new('name','Nom');
        yield EmailField::new('email')->setSortable(false);
        yield BooleanField::new('enabled','Statut')->hideOnForm()->setSortable(false);
        yield DateTimeField::new('creeLe', 'Date création')->onlyOnDetail();
        yield DateTimeField::new('editeLe', 'Date modification')->onlyOnDetail();

        yield FormField::addColumn(6);
        yield FormField::addFieldset();
        yield EntityField::new('group','Groupe')->setRequired(true);
        yield EntityField::new('school','Etablissement Contributeur')->setQueryBuilder(fn(QueryBuilder $qb) => $qb->orderBy('entity.nom'))->setColumns(6);
        yield EntityField::new('untheme',"UNT Documentaliste")->setColumns(6);
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
        $veriftoken = $this->tokenGenerator->generateToken(); //$pass = random_bytes(12); $entityInstance->setPassword($this->hasher->hashPassword($entityInstance,$pass));
        $url = $this->generateUrl('app_reset_response', ['token' => $veriftoken], UrlGeneratorInterface::ABSOLUTE_URL);

        $entityInstance->setTokenExpiresAt(new \DateTimeImmutable(User::VALIDATIME_TOKEN.' min'));
        parent::persistEntity($entityManager, $entityInstance->setReseToken($veriftoken));
        $this->mailer->sendEmail($entityInstance->getEmail(), 'Création de votre compte/espace UNT',
            "Bonjour " . $entityInstance->getName() . '<br/>Votre espace UNT vient d\'être créé. Vous pouvez l\'activer à l\'adresse <a href="' .$url. '">et initialiser votre mot de passe</a>.',
        );
    }

    protected function getRedirectResponseAfterSave(AdminContext $ctx, string $action): RedirectResponse
    {
        $submitButtonName = $ctx->getRequest()->request->all()['ea']['newForm']['btn'];

        $url = match ($submitButtonName) {
            Action::SAVE_AND_CONTINUE => $this->urlGenerator->setAction(Action::EDIT)
                ->setEntityId($ctx->getEntity()->getPrimaryKeyValue())->generateUrl(),
            Action::SAVE_AND_RETURN => $ctx->getReferrer() ?? $this->urlGenerator->setAction(Action::DETAIL)
                    ->setEntityId($ctx->getEntity()->getPrimaryKeyValue())->generateUrl(),
            Action::SAVE_AND_ADD_ANOTHER => $this->urlGenerator->setAction(Action::NEW)->generateUrl(),
            default => $this->generateUrl($ctx->getDashboardRouteName()),
        };

        return $this->redirect($url);
    }

}
