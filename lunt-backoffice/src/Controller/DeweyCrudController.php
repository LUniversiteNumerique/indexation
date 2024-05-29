<?php

namespace App\Controller;

use App\Entity\Dewey;
use App\Field\EntityField;
use App\Form\XmloadType;
use App\Repository\DeweyRepository;
use App\Service\XmlDataLoader;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Asset, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Collection\{FieldCollection,FilterCollection};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{DateTimeField, IdField, TextField};
use EasyCorp\Bundle\EasyAdminBundle\Dto\{EntityDto, SearchDto};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class DeweyCrudController extends AbstractCrudController
{
    const ACTION_UPLOAD = 'uploadXml';

    public static function getEntityFqcn(): string
    {
        return Dewey::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setSearchFields(null)->setEntityPermission('ROLE_READ_DEWE');
    }

    public function configureActions(Actions $actions): Actions
    {
        $xmload = Action::new(self::ACTION_UPLOAD, 'Importer')->setIcon('fa fa-file-code-o')
            ->linkToCrudAction('xmlUpload')->addCssClass('btn btn-outline-primary')
            ->createAsGlobalAction();

        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, $xmload)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->setPermission(self::ACTION_UPLOAD, 'ROLE_CREA_DEWE')
            ->setPermission(Action::NEW, 'ROLE_CREA_DEWE')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_DEWE')
            ->setPermission(Action::DELETE, 'ROLE_DROP_DEWE')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('code'),
            TextField::new('nom'),
            EntityField::new('parent')->onlyOnForms(),
            EntityField::new('children','Sous-Code')->hideOnForm()->setTemplatePath('admin/fields/tree.html.twig'),
            DateTimeField::new('creeLe')->onlyOnDetail(),
            DateTimeField::new('editeLe')->onlyOnDetail()
        ];
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->leftJoin('entity.children','d')->leftJoin('d.children','s')
            ->andWhere('entity.parent is null')->select('entity,d,s');
    }

    public function xmlUpload(
        AdminContext $ctx,
        AdminUrlGenerator $generator,
        XmlDataLoader $loader,
        DeweyRepository $repository): Response
    {
        $form = $this->createForm(XmloadType::class);
        $form->handleRequest($ctx->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $xmlFile */
            $xmlFile = $form->get('file')->getData();
            $loader->parse($xmlFile->getRealPath()); //$repository->add();
            $targetUrl = $generator->setController(self::class)->setAction(Crud::PAGE_INDEX);

            return $this->redirect($targetUrl->generateUrl());
        }
        return $this->render('admin/xmload.html.twig', [
            'form' => $form->createView(),
            'ea_field_assets'  => Asset::fromEasyAdminAssetPackage('field-file-upload.js')->getAsDto()
        ]);
    }
}
