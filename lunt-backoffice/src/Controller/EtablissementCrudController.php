<?php

namespace App\Controller;

use App\Entity\Etablissement;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Asset, Crud};
use App\Form\XmloadType;
use App\Repository\EtablissementRepository;
use App\Service\XmlDataLoader;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{DateTimeField, IdField, TextField};
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class EtablissementCrudController extends AbstractCrudController
{
    const ACTION_UPLOAD = 'uploadXml';

    public static function getEntityFqcn(): string
    {
        return Etablissement::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setSearchFields(['nom', 'abrege'])->setEntityPermission('ROLE_READ_ETAB');
    }

    public function configureActions(Actions $actions): Actions
    {
        $xmload = Action::new(self::ACTION_UPLOAD, 'Importer')->setIcon('fa fa-file-code-o')
            ->linkToCrudAction('xmlUpload')->addCssClass('btn btn-outline-primary')
            ->createAsGlobalAction();

        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, $xmload)
            ->disable(Action::DETAIL)
            ->setPermission(Action::NEW, 'ROLE_CREA_ETAB')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_ETAB')
            ->setPermission(Action::DELETE, 'ROLE_DROP_ETAB');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('nom'),
            TextField::new('abrege'),
            DateTimeField::new('creeLe')->onlyOnDetail(),
            DateTimeField::new('editeLe')->onlyOnDetail()
        ];
    }

    public function xmlUpload(AdminContext $ctx, AdminUrlGenerator $generator, XmlDataLoader $loader, EtablissementRepository $repository): Response
    {
        $form = $this->createForm(XmloadType::class);
        $form->handleRequest($ctx->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $xmlFile */
            $xmlFile = $form->get('file')->getData();
            $etabs = $loader->parseXML($xmlFile->getRealPath());

            $keys = array_keys($etabs);
            $olds = $repository->findBy(['abrege' => $keys]);
            foreach ($olds as $etab) {
                $old_item = $etabs[$etab->getAbrege()];
                $new_item = $etab->setNom($old_item['libelle_uoh']);
                $repository->add($new_item);
            }
            $news = array_diff($keys, $olds);
            foreach ($news as $etab) $repository->add(Etablissement::create($etabs[$etab]));
            $targetUrl = $generator->setController(self::class)->setAction(Crud::PAGE_INDEX);

            return $this->redirect($targetUrl->generateUrl());
        }
        return $this->render('admin/xmload.html.twig', [
            'form' => $form->createView(),
            'ea_field_assets'  => Asset::fromEasyAdminAssetPackage('field-file-upload.js')->getAsDto()
        ]);
    }
}
