<?php

namespace App\Controller;

use App\Entity\Dossier;
use App\Field\EntityField;
use App\Form\DossierType;
use App\Repository\DossierRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Collection\{FieldCollection,FilterCollection};
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, KeyValueStore};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\{EntityDto,SearchDto};
use EasyCorp\Bundle\EasyAdminBundle\Field\{IdField,AssociationField,TextField};
use Symfony\Component\Form\FormBuilderInterface;

class DossierCrudController extends AbstractCrudController
{
    public function __construct(private readonly DossierRepository $repository){}

    public static function getEntityFqcn(): string
    {
        return Dossier::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
      return parent::configureCrud($crud)->showEntityActionsInlined(false)->overrideTemplates(['crud/detail' => 'admin/actions/dossier.html.twig']);
    }

    public function configureActions(Actions $actions): Actions
    {
      return parent::configureActions($actions)
        ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ->add(Crud::PAGE_DETAIL, Action::NEW)
        ->update(Crud::PAGE_DETAIL, Action::DELETE, static fn(Action $a) => $a->displayIf(static fn (Dossier $d) => $d->getChildren()->isEmpty() && $d->getNotices()->isEmpty()))
        ->remove(Crud::PAGE_DETAIL, Action::EDIT)
        ->remove(Crud::PAGE_DETAIL, Action::INDEX)
        ->setPermission(Action::INDEX, 'ROLE_READ_CORE')
        ->setPermission(Action::EDIT, 'ROLE_EDIT_CORE')
        ->setPermission(Action::DELETE, 'ROLE_DROP_CORE')
      ;
    }
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('nom'),
            EntityField::new('children')->onlyOnDetail(),
            AssociationField::new('notices')->onlyOnDetail(),
        ];
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
      return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)->andWhere('entity.parent is null'); //->leftJoin('entity.children','d')->leftJoin('d.children','s')->select('entity,d,s');
    }

    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
      /** @var Dossier $ent */
      $ent = $entityDto->getInstance();
      if ($id = $context->getRequest()->get('entityId')) {
        $ent->setParent($this->repository->find($id));
        $entityDto->setInstance($ent);
      }
      return parent::createNewFormBuilder($entityDto, $formOptions, $context);
    }

    public function detail(AdminContext $context): KeyValueStore|\Symfony\Component\HttpFoundation\Response
    {
        $resParams = parent::detail($context);
        $resParams->set('form', $this->createForm(DossierType::class));
        return $resParams;
    }
}
