<?php

namespace App\Controller;

use App\Entity\Etablissement;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, Filters};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{DateTimeField, IdField, ImageField, TextField};
use Symfony\Component\Validator\Constraints\Image;
use EasyCorp\Bundle\EasyAdminBundle\Filter\{TextFilter, DateTimeFilter};
use function Symfony\Component\Translation\t;

class EtablissementCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Etablissement::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setSearchFields(['nom', 'code'])->setDefaultSort(['nom' => 'ASC'])
            ->setEntityLabelInPlural("Établissements")->setEntityLabelInSingular("établissement")
            ->setEntityPermission('ROLE_READ_ETAB');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('code','Nom abrégé'))
            ->add(TextFilter::new('nom','Etablissement'))
            ->add(DateTimeFilter::new('creeLe','Date Création'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->setPermission(Action::DETAIL, 'ROLE_READ_ETAB')
            ->setPermission(Action::INDEX, 'ROLE_READ_ETAB')
            ->setPermission(Action::NEW, 'ROLE_CREA_ETAB')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_ETAB')
            ->setPermission(Action::DELETE, 'ROLE_DROP_ETAB')
            ->setPermission(Action::BATCH_DELETE, 'ROLE_DROP_ETAB')
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->remove(Crud::PAGE_NEW,Action::SAVE_AND_ADD_ANOTHER);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('code', 'Nom abrégé'),
            TextField::new('nom', 'Intitulé'),
            ImageField::new('logo')->setUploadDir('public/uploads/logos')->setBasePath('/uploads/logos')
                ->setUploadedFileNamePattern('[timestamp]-[slug].[extension]')->setSortable(false)
                ->setFileConstraints([new Image(maxWidth:500, maxHeight:500)])->setHelp(t('notice.logo_help', domain: 'EasyAdminBundle')),
            DateTimeField::new('creeLe')->onlyOnDetail(),
            DateTimeField::new('editeLe')->onlyOnDetail()
        ];
    }

    public function index(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_ETAB');
        return parent::index($context);
    }

    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_CREA_ETAB');
        return parent::new($context);
    }

    public function detail(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_ETAB');
        return parent::detail($context);
    }

    public function edit(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_EDIT_ETAB');
        return parent::edit($context);
    }

    public function delete(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_DROP_ETAB');
        return parent::delete($context);
    }
}
