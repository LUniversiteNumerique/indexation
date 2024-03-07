<?php

namespace App\Controller;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, Filters};
use App\Field\EntityField;
use App\Service\MailerService;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Doctrine\ORM\{EntityManagerInterface,QueryBuilder};
use EasyCorp\Bundle\EasyAdminBundle\Collection\{FieldCollection,FilterCollection};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{BooleanField, DateTimeField, EmailField, FormField, IdField, TextField};
use EasyCorp\Bundle\EasyAdminBundle\Dto\{EntityDto,SearchDto};
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TokenGeneratorInterface $generator,
        private readonly MailerService $mailer) {}

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('group')->add('school')->add(BooleanFilter::new('enabled'));
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Utilisateur')->setEntityLabelInPlural('Utilisateurs')
            ->setSearchFields(['name', 'email'])->setEntityPermission('ROLE_READ_USER');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->setPermission(Action::NEW, 'ROLE_CREA_USER')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_USER')
            ->setPermission(Action::DELETE, 'ROLE_DROP_USER');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addColumn(6);
        yield FormField::addFieldset();
        yield IdField::new('id')->onlyOnDetail();
        yield TextField::new('name','Nom');
        yield EmailField::new('email');
        yield BooleanField::new('enabled','Actif')->hideOnForm()
            ->renderAsSwitch($this->isGranted('ROLE_EDIT_USER'));
        yield DateTimeField::new('creeLe', 'Date création')->onlyOnDetail();
        yield DateTimeField::new('editeLe', 'Date modification')->onlyOnDetail();

        yield FormField::addColumn(6);
        yield FormField::addFieldset();
        yield EntityField::new('group','Groupe');
        yield EntityField::new('school','Etablissement Contributeur')->setColumns(6);
        yield EntityField::new('untheme',"UNT Documentaliste")->setColumns(6); //->setQueryBuilder(fn(QueryBuilder $qb) => $qb->where('entity.parent is null'))
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
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $veriftoken = $this->generator->generateToken(); //$pass = random_bytes(12); $entityInstance->setPassword($this->hasher->hashPassword($entityInstance,$pass));
        $url = $this->generateUrl('app_reset_response', ['token' => $veriftoken], UrlGeneratorInterface::ABSOLUTE_URL);

        parent::persistEntity($entityManager, $entityInstance->setReseToken($veriftoken));
        $this->mailer->sendEmail($entityInstance->getEmail(), 'Création de votre compte/espace UNT',
            "Bonjour " . $entityInstance->getName() . '<br/>Votre espace UNT vient d\'être mise en place. Vous pouvez y accéder et <a href="' .$url. '">réinitialiser votre mot de passe</a>.',
        );
    }

}
