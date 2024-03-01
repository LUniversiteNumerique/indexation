<?php

namespace App\Controller;

use App\Form\TagType;
use App\Field\EntityField;
use App\Entity\{Discipline,Notice,NoticEtat,User};
use Doctrine\ORM\{EntityManagerInterface,QueryBuilder};
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Assets, Crud, Filters, KeyValueStore};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, BooleanField, ChoiceField, FormField, IntegerField, TextEditorField, TextField, UrlField};
use EasyCorp\Bundle\EasyAdminBundle\{Collection\FieldCollection,
    Collection\FilterCollection,
    Dto\EntityDto,
    Context\AdminContext,
    Dto\SearchDto,
    Factory\FormFactory,
    Router\AdminUrlGenerator};
use Psr\Container\{ContainerExceptionInterface,NotFoundExceptionInterface};
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\{FormBuilderInterface,FormEvent,FormEvents,FormInterface};
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Languages;

class NoticeCrudController extends AbstractCrudController
{
    const ACTION_DUPLICATE = 'duplicate';

    public function __construct(private readonly EntityManagerInterface $manager, private readonly AdminUrlGenerator $generator) {}

    public static function getEntityFqcn(): string
    {
        return Notice::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityPermission(User::ROLE_DEFAULT)->setPageTitle(Crud::PAGE_DETAIL, static fn (Notice $n) => sprintf('#%s %s', $n->getId(), $n->getTitre()));
    }

    public function configureActions(Actions $actions): Actions
    {
        $duplicate = Action::new(self::ACTION_DUPLICATE)
            ->linkToCrudAction('duplicateNotice')
            ->setCssClass('btn btn-outline-info'); // ->setTemplatePath('admin/approve_action.html.twig')
        //->setHtmlAttributes(['data-bs-toggle' => 'modal', 'data-bs-target' => '#modal-resend',])

        return $actions
            ->add(Crud::PAGE_DETAIL, $duplicate->setIcon('fa fa-paste'))
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn(Action $a) => $a->setIcon('fa fa-plus'))
            ->update(Crud::PAGE_INDEX, Action::DETAIL, fn(Action $a) => $a->setIcon('fa fa-eye')->setCssClass('btn btn-outline-success'))
            ->update(Crud::PAGE_DETAIL, Action::DELETE, static fn(Action $a) =>
            $a->displayIf(static fn (Notice $n) => $n->getEtat()===NoticEtat::Approved));
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('titre')
            ->add('etat')->add('specialite')
            ->add('porteurs')->add('creeLe');
    }

    public function configureFields(string $pageName): iterable
    {
        $languagesList = Languages::getAlpha3Names('fr'); //Languages::getNames('en');
        yield FormField::addColumn(6);
        yield FormField::addFieldset('Basic information')->setIcon('fa fa-pencil')->setHelp('Phone number is preferred');
        yield TextField::new('titre');
        yield TextEditorField::new('description')->hideOnIndex()->setHelp('Renseignez la description de cette notice !');
        if (Crud::PAGE_NEW  === $pageName || $pageName === Crud::PAGE_EDIT) {
            yield UrlField::new('contenu.url');
            yield TextField::new('contenu.libelle');
        } else yield UrlField::new('contenu');

        yield FormField::addFieldset('Other information')->setIcon('fa fa-folder-open')->setHelp('Additional Details');
        yield AssociationField::new('droit');
        yield TextField::new('vignette')->setPermission('ROLE_DOCUM');
        yield TextField::new('dewey','Code Dewey')->setPermission('ROLE_DOCUM');
        yield IntegerField::new('taille')->setPermission('ROLE_DOCUM')->hideOnIndex();
        yield TextField::new('dureAppr',"Durée d'apprentissage")->setPermission('ROLE_DOCUM')->hideOnIndex();
        yield TextField::new('dureExec',"Durée d'exécution")->setPermission('ROLE_DOCUM')->hideOnIndex();
        yield TextField::new('propUser',"Proposition d'utilisation")->setPermission('ROLE_DOCUM')->hideOnIndex();
        yield TextEditorField::new('objectif','Objectifs pédagogiques')->setPermission('ROLE_DOCUM')->hideOnIndex();
        yield BooleanField::new('exportOAI', 'Export OAI')->renderAsSwitch(false)->setPermission('ROLE_DOCUM');
        yield ChoiceField::new('etat')->setChoices(NoticEtat::cases())->hideOnForm();




        yield FormField::addColumn(6);
        yield FormField::addFieldset('Advanced information')->setIcon('fa fa-th-list')->addCssClass('');
        yield ChoiceField::new('userLang')->setChoices(array_flip($languagesList))->allowMultipleChoices()->renderAsBadges()->setPermission('ROLE_DOCUM');
        yield ChoiceField::new('ressLang')->setChoices(array_flip($languagesList))->allowMultipleChoices()->renderAsBadges();
        yield AssociationField::new('porteurs');
        yield AssociationField::new('pedTypes')->hideOnIndex();
        yield AssociationField::new('auteurs')->autocomplete();
        yield AssociationField::new('tags')->setFormType(TagType::class)
            ->setFormTypeOptions(['label' => 'Mots clés', 'autocomplete' => true,
                'autocomplete_url' => $this->generateUrl('app_tags'),
                'tom_select_options' => ['create' => true, 'createOnBlur' => true, 'preload' => true],
            ]);

        yield FormField::addFieldset('Attachments')->setIcon('fa fa-paperclip')->addCssClass('');
        yield AssociationField::new('ressources')->autocomplete();
        yield AssociationField::new('niveaux')->setFormTypeOptions(['multiple' => true,'expanded' => true])->hideOnIndex();
        yield AssociationField::new('docTypes')->setFormTypeOptions(['multiple' => true,'expanded' => true])->hideOnIndex();
        yield EntityField::new('champDisc','Champ disciplinaire')->onlyOnForms()
            ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->where('entity.parent is null'))
            ->setFormTypeOptions(['class' => Discipline::class,'mapped' => false,'required' => false]);
        yield EntityField::new('discipline')->onlyOnForms()->setFormTypeOptions([
            'class' => Discipline::class,'auto_initialize' => false,'mapped' => false,'required' => false
        ]);
        yield EntityField::new('specialite','Spécialité')->setFormTypeOptions(['class' => Discipline::class]);

        //yield CollectionField::new('collectionComplex', 'Collection Field (complex)')->setFormTypeOption('entry_type', CollectionComplexType::class)->setEntryIsComplete();
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile('https://code.jquery.com/jquery-3.7.1.min.js')->addJsFile('build/admin.js');
    }

    public function createEntity(string $entityFqcn): Notice
    {
        /** @var Notice $notice */
        $notice = parent::createEntity($entityFqcn);
        return $notice->setUser($this->getUser());
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        /** @var User $user */
        $user = $this->getUser();
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->select('entity,r,a,n,p,dd,pp,k,l,s')
            ->leftJoin('entity.ressources','r')
            ->join('entity.tags','k')->join('entity.auteurs','a')
            ->join('entity.niveaux','n')->join('entity.porteurs','p')
            ->join('entity.docTypes','dd')->join('entity.pedTypes','pp')
            ->join('entity.droit','l')->join('entity.specialite','s')
        ;
        if($this->isGranted('ROLE_CONTR'))
            $qb->andWhere(':school MEMBER OF entity.porteurs')->setParameter('school', $user->getSchool());
        elseif($this->isGranted('ROLE_DOCUM') && $user->getFields()->count() >1)
            $qb->join('s.parent','d')->join('d.parent','c')
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->in('s', ':ok'),
                    $qb->expr()->in('d', ':ok'),
                    $qb->expr()->in('c', ':ok'),
                ))->setParameters(['ok' => $user->getFields()])
                ->addSelect('d,c');
        return $qb;
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $builder = $this->container->get(FormFactory::class)->createNewFormBuilder($entityDto, $formOptions, $context);
        $builder->get('champDisc')->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $this->addDisc($form->getParent(), $form->getData());
        });
        return $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();
            //$newForm = $this->createEditFormBuilder($context->getEntity(), $context->getCrud()->getEditFormOptions(), $context)->getForm(); $comments = $newForm->get('comments');

            /* @var $specialite Discipline */
            $specialite = $data->getSpecialite();
            $discipline = $specialite?->getParent();
            $champ = $discipline?->getParent();

            $this->addDisc($form, $champ);
            $this->addSpec($form, $discipline);
            if ($specialite) {
                $form->get('champDisc')->setData($champ);
                $form->get('discipline')->setData($discipline);
            }
        });
    }

    private function addDisc(FormInterface $form, ?Discipline $champs): void
    {
        $builder = $form->getConfig()->getFormFactory()->createNamedBuilder('discipline', EntityType::class, null, [
            'class' => Discipline::class, 'mapped' => false, 'auto_initialize' => false,
            'required' => false, 'choices' => $champs ? $champs->getChildren() : [],
            'placeholder' => $champs ? 'Sélectionnez la discipline' : 'Sélectionnez le champ disciplinaire',
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $this->addSpec($form->getParent(), $form->getData());
        });

        $form->add($builder->getForm());
    }

    private function addSpec(FormInterface $form, ?Discipline $discip): void {
        $form->add('specialite', EntityType::class, ['required' => false,
            'class' => Discipline::class, 'choices' => $discip ? $discip->getChildren() : [],
            'placeholder' => $discip ? 'Sélectionnez la spécialité' : 'Sélectionnez la discipline',
        ]);
    }

    public function duplicateNotice(): Response
    {
        /** @var Notice $notice */
        $notice = $this->getContext()->getEntity()->getInstance();
        $dupNot = (clone $notice)->setCreeLe(new \DateTime());
        parent::persistEntity($this->manager, $dupNot->setUser($this->getUser()));

        /** @var AdminUrlGenerator $genUrl */
        $genUrl = $this->generator->setController(self::class)
            ->setAction(Action::DETAIL)->setEntityId($dupNot->getId());
        return $this->redirect($genUrl->generateUrl());
    }

    public function resend(AdminContext $context): Response
    {
        $url = $this->generator
            ->setAction(Action::INDEX)
            ->removeReferrer()
            ->setController($context->getCrud()?->getControllerFqcn() ?? '')->generateUrl();

        /** @var Notice|null $request */
        $request = $context->getEntity()->getInstance();
        if (!$request) $this->addFlash('danger', 'easy.admin.flash.resend.danger');
        else {
            $data = $request->getRessLang();
            $data['manuel'] = 'Oui';

            //$this->requestMessageService->dispatchFormRequest($data);

            $request->setRessLang($data)->setModifieLe(new \DateTime());
            $this->manager->persist($request);
            $this->manager->flush();
            $this->addFlash('success', 'easy.admin.flash.resend.success');
        }

        return $this->redirect($url);
    }
}
