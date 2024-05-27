<?php

namespace App\Controller;

use App\{Event\AfterNoticeStateSetEvent, Security\Voter\NoticeActionVoter, Service\MailerService, Validator\UploadImage};
use App\Entity\{Dewey, Discipline, Etablissement, Notice, NoticEtat, Univerique, User};
use App\Field\{DurationField, EntityField};
use App\Form\Type\{AuteurAutoField, TagType, TreeChoiceType};
use App\Repository\{DossierRepository, NoticeRepository};
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\{Context\AdminContext, Event\AfterEntityPersistedEvent, Factory\FormFactory, Filter\ChoiceFilter, Router\AdminUrlGenerator};
use EasyCorp\Bundle\EasyAdminBundle\Collection\{ActionCollection, FieldCollection, FilterCollection};
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Asset, Assets, Crud, Filters, KeyValueStore};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\{ActionDto, EntityDto, SearchDto};
use EasyCorp\Bundle\EasyAdminBundle\Field as Field;
use Psr\Container\{ContainerExceptionInterface, NotFoundExceptionInterface};
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\{FormBuilderInterface, FormEvent, FormEvents, FormInterface};
use Symfony\Component\HttpFoundation\{RedirectResponse, Response};
use Symfony\Component\Intl\Languages;

class NoticeCrudController extends AbstractCrudController
{
    const SAVE_AND_FORWARD = 'saveAndForward';
    const FORWARD_ACTION = 'forwardNotice';

    public function __construct(
        private readonly DossierRepository $rep,
        private readonly NoticeRepository $repository,
        private readonly AdminUrlGenerator $generator,
        private readonly MailerService $mailer) {}

    public static function getEntityFqcn(): string
    {
        return Notice::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $crud = $crud->setAutofocusSearch()->setSearchFields(['titre','description'])->setPageTitle(Crud::PAGE_DETAIL, static fn (Notice $n) => $n->getTitre());
        if($this->isGranted('ROLE_VALI_NOTI')) $crud->renderSidebarMinimized()->overrideTemplates([
            'crud/detail'=>'admin/actions/notice_show.html.twig',
            'crud/new'=>'admin/actions/notice_new.html.twig'
        ]);
        return $crud;
    }

    public function configureActions(Actions $actions): Actions
    {
        $duplicate = Action::new('dupliquer',null,'fa fa-copy')->linkToCrudAction('duplicateNotice')->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Dupliquer cette notice']);
        $forward = Action::new('soumettre',null,'fa fa-send')->linkToCrudAction(self::FORWARD_ACTION)->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Soumettre cette notice']);
        $approve = Action::new('valider',null,'fa fa-check')->linkToCrudAction('approveNotice')->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Valider la notice pour publication']);
        $reject = Action::new('rejeter',null,'fa fa-close')->linkToCrudAction('rejectNotice')->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Rejeter la notice pour correction']);
        $publish = Action::new('dépublier',null,'fa fa-step-backward')->linkToCrudAction('publishNotice')->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Dépublier cette notice publiée']);
        $allowed = Action::new('autoriser',null,'fa fa-fast-backward')->linkToCrudAction('allowedNotice')->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Autoriser la notice pour modification']);
        $saward = Action::new(self::SAVE_AND_FORWARD, 'Créer et soumettre la notice', 'fa fa-send')->linkToCrudAction(Action::NEW)->setHtmlAttributes(['type' => 'submit', 'name' => 'ea[newForm][btn]', 'value' => self::SAVE_AND_FORWARD]);
        $adjust = Action::new('rectifier',null,'fa fa-share-square')->linkToCrudAction('adjustNotice');
        $category = Action::new('catégoriser',null,'fa fa-tag')->linkToCrudAction('labelNotice')
            ->addCssClass('text-warning confirm-action')->setHtmlAttributes(['data-bs-toggle' => 'modal', 'data-bs-target' => '#modal-confirm',]);

        $fwdoc = fn(Notice $n,string $s = NoticeActionVoter::VALI) => $this->isGranted($s,$n);
        return $actions
            ->add(Crud::PAGE_DETAIL, $duplicate->displayIf(static fn (Notice $n) => $n->getEtat()===NoticEtat::Working && $fwdoc($n,NoticeActionVoter::EDIT)))
            ->add(Crud::PAGE_DETAIL, $forward->displayIf(static fn (Notice $n) => $n->getEtat()===NoticEtat::Working && $fwdoc($n,NoticeActionVoter::EDIT)))
            ->add(Crud::PAGE_DETAIL, $reject->displayIf(static fn (Notice $n) => $n->getEtat()===NoticEtat::Forward && $fwdoc($n)))
            ->add(Crud::PAGE_DETAIL, $approve->displayIf(static fn (Notice $n) => $n->getEtat()===NoticEtat::Forward && $fwdoc($n)))
            ->add(Crud::PAGE_DETAIL, $publish->displayIf(static fn (Notice $n) => $n->getEtat()===NoticEtat::Approved && $fwdoc($n)))
            ->add(Crud::PAGE_DETAIL, $allowed->displayIf(static fn (Notice $n) => $n->isEditDemande() && $fwdoc($n)))
            ->add(Crud::PAGE_DETAIL, $category->displayIf(static fn (Notice $n) => $n->getEtat()===NoticEtat::Approved && $fwdoc($n)))
            ->add(Crud::PAGE_DETAIL, $adjust->displayIf(static fn (Notice $n) => $n->getEtat()===NoticEtat::Approved && $fwdoc($n,NoticeActionVoter::VIEW)))
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_NEW, $saward->displayAsButton())
            ->update(Crud::PAGE_INDEX, Action::DETAIL, fn (Action $a) => $a->setCssClass('btn btn-outline-secondary'))
            ->update(Crud::PAGE_DETAIL, Action::EDIT, static fn(Action $a) => $a->setIcon('fa fa-pencil')->displayIf(static fn (Notice $n) => $fwdoc($n,NoticeActionVoter::EDIT)))
            ->update(Crud::PAGE_DETAIL, Action::DELETE, static fn(Action $a) => $a->addCssClass('btn btn-outline-danger')->displayIf(static fn (Notice $n) => $fwdoc($n,NoticeActionVoter::DROP)))
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::INDEX)
            ->setPermission(Action::NEW, 'ROLE_CREA_NOTI');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('etat')
            ->setChoices(NoticEtat::getLabels())->renderExpanded())
            ->add('specialite')->add('creeLe');
    }

    public function configureFields(string $pageName): iterable
    {
        /** @var User $user */ $user = $this->getUser();
        $valdoc = $this->isGranted('ROLE_VALI_NOTI');
        $langList = Languages::getAlpha3Names('fr');
        if ($valdoc) yield Field\FormField::addTab('Soumission')->setHelp("Infos renseignées par la contribution des établissements");

        yield Field\FormField::addColumn(6);
        yield Field\FormField::addFieldset('Description générale')->setIcon('fa fa-pencil');
        yield Field\IdField::new('id')->onlyOnDetail();
        yield Field\TextField::new('titre')->setHelp('Le titre de cette notice');
        yield Field\TextEditorField::new('description')->hideOnIndex();
        yield EntityField::new('porteurs', 'Établissement(s) porteur(s)')->setRequired(true)->hideOnIndex();
        yield EntityField::new('auteurs')->setFormType(AuteurAutoField::class)->setRequired(true);
        //yield Field\CollectionField::new('auteurs')->setEntryType(AuteurType::class)->formatValue(fn ($value, Auteur $entity) => $entity->getNom() ?? '');
        yield EntityField::new('tags', 'Mots-clés')->setFormType(TagType::class)
            ->setFormTypeOptions(['autocomplete' => true, 'autocomplete_url' => $this->generateUrl('app_tags'),
                'tom_select_options' => ['create' => true, 'createOnBlur' => true, 'preload' => true],
            ])->hideOnIndex()->setRequired(true);
        yield Field\DateField::new('ressDate', "Date de création")->setFormat('yyyy')->hideOnIndex();

        yield Field\FormField::addFieldset('Liens de la ressource')->setIcon('fa fa-paperclip');
        yield Field\UrlField::new('ressUrl', 'URL Contenu');
        yield EntityField::new('ressources','Ressource(s) liée(s)')->autocomplete()->hideOnIndex();
        yield Field\ChoiceField::new('etat')->setChoices(NoticEtat::getLabels())->renderAsBadges(NoticEtat::getColors())->hideOnForm();

        yield Field\FormField::addFieldset('Droits attachés à la ressource')->setIcon('fa fa-gavel');
        yield Field\AssociationField::new('droit',"Licence et conditions d'utilisation")->hideOnIndex();
        yield Field\BooleanField::new('ressPayant','Ressource payante')->renderAsSwitch(false)->hideOnIndex()->setColumns(6);
        yield Field\BooleanField::new('proprIntel','Propriété intellectuelle')->renderAsSwitch(false)->hideOnIndex()->setColumns(6);


        yield Field\FormField::addColumn(6);
        yield Field\FormField::addFieldset('Indications pédagogiques')->setIcon('fa fa-th-list');
        yield Field\ChoiceField::new('ressLang', 'Langue(s) de la resource')->setChoices(array_flip($langList))
            ->allowMultipleChoices()->renderAsBadges()->hideOnIndex()->setRequired(true)->setColumns(6);
        yield DurationField::new('dureAppr', "Durée d'apprentissage")->setColumns(6)->hideOnIndex();
        yield EntityField::new('pedTypes', 'Type pédagogique')->hideOnIndex()->setRequired(true);
        yield Field\ArrayField::new('propUser', "Proposition d'utilisation")->hideOnIndex();
        yield EntityField::new('docTypes', 'Type documentaire')->setFormTypeOptions(['multiple' => true, 'expanded' => true])->setColumns(6)->hideOnIndex()->setRequired(true);
        yield EntityField::new('niveaux', 'Niveau du public cible')->setFormTypeOptions(['multiple' => true, 'expanded' => true])->setColumns(6)->hideOnIndex()->setRequired(true);

        yield Field\FormField::addFieldset('Classification thématique')->setIcon('fa fa-book');
        yield EntityField::new('champDisc', 'Domaine de connaissance')->onlyOnForms()->setFormTypeOptions(['class' => Discipline::class, 'mapped' => false, 'required' => false])
            ->setQueryBuilder(fn(QueryBuilder $qb) => ($valdoc && $user->getUntheme() instanceof Univerique)? $qb->where('entity IN (:champs)')->setParameter('champs', $user->getUntheme()->getFields()) : $qb->where('entity.parent is null'));
        yield EntityField::new('discipline')->setFormTypeOptions([
            'class' => Discipline::class, 'auto_initialize' => false, 'mapped' => false, 'required' => false
        ])->onlyOnForms();
        yield EntityField::new('specialite','Sous-discipline')->setFormTypeOptions(['class' => Discipline::class]);
        yield Field\DateTimeField::new('editeLe')->hideOnForm();

        if ($valdoc) {
            yield Field\FormField::addTab('Validation')->setHelp("Infos techniques complémentaires de validation");

            yield Field\FormField::addColumn(6);
            yield Field\FormField::addFieldset('Liens de la ressource')->setIcon('fa fa-folder-open');
            yield Field\ImageField::new('vignette')->setUploadDir('public/images/uploads')
                ->setUploadedFileNamePattern('[timestamp]-[contenthash].[extension]')->setBasePath('/images/uploads')
                ->setFormTypeOption('constraints', [new UploadImage(['maxWidth'=>620, 'maxHeight'=>390])]);
            yield Field\IntegerField::new('taille','Taille (Mo)')->setColumns(6)->hideOnIndex();
            yield DurationField::new('dureExec',"Durée d'exécution")->setColumns(6)->hideOnIndex();
            yield Field\UrlField::new('formEvalUrl', 'URL formulaire évaluation ressource')->hideOnIndex();
            yield Field\ChoiceField::new('userLang',"Langues de l'utilisateur")->setChoices(array_flip($langList))->allowMultipleChoices()->renderExpanded(false)->renderAsBadges()->hideOnIndex();
            yield Field\TextEditorField::new('objectif','Objectif pédagogique')->hideOnIndex();
            yield Field\BooleanField::new('exportOAI', 'Export OAI')->renderAsSwitch(false)->hideOnIndex();

            yield Field\FormField::addColumn(6);
            yield Field\FormField::addFieldset('Classification thématique')->setIcon('fa fa-book');
            yield EntityField::new('disciFond','Discipline fondamentale')->onlyOnForms()
                ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->where('entity.parent is null'))->setFormTypeOptions(['class' => Dewey::class,'mapped' => false,'required' => false]);
            yield EntityField::new('division')->setFormTypeOptions(['class' => Dewey::class,'auto_initialize' => false,'mapped' => false,'required' => false])->onlyOnForms();
            yield EntityField::new('codewey','Code Dewey')->setFormTypeOptions(['class' => Dewey::class])->setRequired(true);
            yield Field\TextField::new('label','Catégorie')->onlyOnDetail();
            yield Field\DateTimeField::new('creeLe')->onlyOnDetail();
            yield EntityField::new('repertoire')->setFormType(TreeChoiceType::class);
        }
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile(Asset::new('../assets/form.js')->onlyOnForms());
    }

    public function createEntity(string $entityFqcn): Notice
    {
        /** @var Notice $notice */
        $notice = parent::createEntity($entityFqcn);
        return $notice->setCreateur($this->getUser());
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        /** @var User $user */$user = $this->getUser();
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->select('entity,r,e,a,n,p,dd,pp,k,l,s')
            ->leftJoin('entity.ressources','r')->leftJoin('entity.codewey','e')
            ->join('entity.tags','k')->join('entity.auteurs','a')
            ->join('entity.niveaux','n')->join('entity.porteurs','p')
            ->join('entity.docTypes','dd')->join('entity.pedTypes','pp')
            ->join('entity.droit','l')->join('entity.specialite','s');

        if($this->isGranted('ROLE_READ_NOTI') && $user->getSchool() instanceof Etablissement)
            $qb->andWhere(':school MEMBER OF entity.porteurs')->setParameter('school', $user->getSchool());
        elseif($this->isGranted('ROLE_VALI_NOTI') && $user->getUntheme() instanceof Univerique)
            $qb->join('s.parent','d')->addSelect('d')
                ->andWhere('d.parent in (:champs)')->setParameter('champs',$user->getUntheme()->getFields());
        return $qb->orderBy('entity.creeLe', 'DESC');
    }

    /** @throws NotFoundExceptionInterface|ContainerExceptionInterface */
    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $builder = $this->container->get(FormFactory::class)->createNewFormBuilder($entityDto, $formOptions, $context);
        return $this->addFormEvent($builder);
    }

    /** @throws NotFoundExceptionInterface|ContainerExceptionInterface */
    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        /** @var Notice $notice */
        $notice = $context->getEntity()->getInstance();
        $this->denyAccessUnlessGranted(NoticeActionVoter::EDIT, $notice);

        $builder = $this->container->get(FormFactory::class)->createEditFormBuilder($entityDto, $formOptions, $context);
        return $this->addFormEvent($builder);
    }

    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $request = $context->getRequest();
        $entityId = $context->getEntity()->getPrimaryKeyValue();
        $submitButtonName = $request->request->all()['ea']['newForm']['btn'];

        $entityUrl = $this->generator->setAction(Action::DETAIL)->setEntityId($entityId);
        if($folderId = $request->get('folderId')) $entityUrl->set('folderId', $folderId);
        $url = match ($submitButtonName) {
            self::SAVE_AND_FORWARD => $this->generator->setAction(self::FORWARD_ACTION)->setEntityId($entityId)->generateUrl(),
            Action::SAVE_AND_CONTINUE => $this->generator->setAction(Action::EDIT)->setEntityId($entityId)->generateUrl(),
            Action::SAVE_AND_ADD_ANOTHER => $this->generator->setAction(Action::NEW)->generateUrl(),
            Action::SAVE_AND_RETURN => $context->getReferrer() ?? $entityUrl->generateUrl(),
            default => $this->generateUrl($context->getDashboardRouteName()),
        };

        return $this->redirect($url);
    }

    /** @throws NotFoundExceptionInterface|ContainerExceptionInterface */
    public function duplicateNotice(): Response
    {
        $ctx = $this->getContext();

        /** @var Notice $notice */
        $notice = $ctx->getEntity()->getInstance();
        $dupNot = (clone $notice)->setCreeLe(new \DateTimeImmutable());

        $this->repository->add($dupNot->setCreateur($this->getUser()));
        $this->container->get('event_dispatcher')->dispatch(new AfterEntityPersistedEvent($dupNot));
        $this->addFlash('success', "Cette notice dupliquée vient d'être créé avec succès !");

        /** @var AdminUrlGenerator $genUrl */
        $genUrl = $this->generator->setController(self::class)->setAction(Action::DETAIL)->setEntityId($dupNot->getId());
        if($folderId = $ctx->getRequest()->get('folderId')) $genUrl->set('folderId',$folderId);
        return $this->redirect($genUrl->removeReferrer()->generateUrl());
    }

    public function forwardNotice(): Response
    {
        $ctx = $this->getContext();

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Working
        return $this->changEtatNotice(['Soumettre', NoticEtat::Forward->getLabel(), 'Soummision', true], $notice->setEtat(NoticEtat::Forward),$ctx->getRequest()->get('folderId'));
    }

    public function approveNotice(): Response
    {
        $ctx = $this->getContext();

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Forward
        return $this->changEtatNotice(['Valider', NoticEtat::Approved->getLabel(), 'Validation', true], $notice->setEtat(NoticEtat::Approved),$ctx->getRequest()->get('folderId'));
    }

    public function rejectNotice(): Response
    {
        $ctx = $this->getContext();

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Forward
        return $this->changEtatNotice(['Rejeter', 'Rejetée', 'Rejet', true], $notice->setEtat(NoticEtat::Working),$ctx->getRequest()->get('folderId'));
    }

    public function publishNotice(): Response
    {
        $ctx = $this->getContext();

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Approved
        return $this->changEtatNotice(['Dépublier', 'Dépubliée', 'Dépublication', false], $notice->setEtat(NoticEtat::Forward),$ctx->getRequest()->get('folderId'));
    }

    public function allowedNotice(): Response
    {
        $ctx = $this->getContext();

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Approved
        return $this->changEtatNotice(['Autoriser', 'Autorisée', 'Autorisation', false], $notice->setEtat(NoticEtat::Working)->setEditDemande(false),$ctx->getRequest()->get('folderId'));
    }

    public function adjustNotice(): Response
    {
        $ctx = $this->getContext();

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Approved
        return $this->changEtatNotice(['Rectifier', 'Signalée', 'Rectification', true], $notice->setEditDemande(true),$ctx->getRequest()->get('folderId'));
    }

    public function labelNotice(): Response
    {
        $ctx = $this->getContext();
        $label = $ctx->getRequest()->get('label');

        /** @var Notice $notice */
        $notice = $ctx->getEntity()->getInstance();
        return $this->changEtatNotice(['Catégoriser', 'Labellisée', 'Catégorisation', false], $notice->setLabel($label),$ctx->getRequest()->get('folderId'));
    }

    private function changEtatNotice(array $transition, Notice $notice, ?int $folderId = null): Response
    {
        $url = $folderId ?
            $this->generator->setController(DossierCrudController::class)->setAction(Action::DETAIL)->setEntityId($folderId):
            $this->generator->setController(self::class)->setAction(Action::INDEX);

        $this->repository->add($notice);
        /** @var User $user */ $user = $this->getUser();
        $group = $this->isGranted("ROLE_VALI_NOTI");
        if($transition[3]) $this->mailer->sendTwig(($group?$notice->getCreateur():$notice->getValidateur())?->getEmail(),
            sprintf("Notice %d en statut %s", $notice->getId(), $notice->getEtat()?->getLabel()), 'emails/notif.html.twig',
            ['notice' => $notice->getTitre(), 'url' => $url->removeReferrer()->generateUrl(), 'message' => $group ?
                sprintf("La notice <<%s>> a été %s par le %s %s", $notice, lcfirst($transition[1]), $user->getGroup(), $user):
                sprintf("Une demande de modification vous a été transmise concernant la notice <<%s>> par le %s %s", $notice, $user->getGroup(),  $user)
            ]
        );
        try {
            $this->container->get('event_dispatcher')->dispatch(new AfterNoticeStateSetEvent($notice, $transition));
            $this->addFlash('success', sprintf("La notice est bien %s avec succès !",$transition[1]));
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface) {}

        return $this->redirect($url->removeReferrer()->generateUrl());
    }

    private function addDisc(FormInterface $form, ?Discipline $child): void
    {
        $builder = $form->getConfig()->getFormFactory()->createNamedBuilder('discipline', EntityType::class, null, [
            'class' => Discipline::class, 'mapped' => false, 'auto_initialize' => false,
            'required' => false, 'choices' => $child ? $child->getChildren() : [],
            'placeholder' => $child ? 'Sélectionnez la discipline' : 'Sélectionnez le champ disciplinaire',
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $this->addSpec($form->getParent(), $form->getData());
        });

        $form->add($builder->getForm());
    }
    private function addDivi(FormInterface $form, ?Dewey $child): void
    {
        $builder = $form->getConfig()->getFormFactory()->createNamedBuilder('division', EntityType::class, null, [
            'class' => Dewey::class, 'mapped' => false, 'auto_initialize' => false,
            'required' => false, 'choices' => $child ? $child->getChildren() : [],
            'placeholder' => $child ? 'Sélectionnez la division' : 'Sélectionnez la discipline fondamentale',
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $this->addCode($form->getParent(), $form->getData());
        });

        $form->add($builder->getForm());
    }

    private function addSpec(FormInterface $form, ?Discipline $child): void {
        $form->add('specialite', EntityType::class, [
            'label' => 'Spécialité', 'class' => Discipline::class,
            'choices' => $child ? $child->getChildren() : [],
            'placeholder' => $child ? 'Sélectionnez la spécialité' : 'Sélectionnez la discipline',
        ]);
    }
    private function addCode(FormInterface $form, ?Dewey $child): void {
        $form->add('codewey', EntityType::class, [
            'label' => 'Code Dewey', 'class' => Dewey::class,
            'required' => false, 'choices' => $child ? $child->getChildren() : [],
            'placeholder' => $child ? 'Sélectionnez le code dewey' : 'Sélectionnez la division',
        ]);
    }

    private function addFormEvent(FormBuilderInterface $builder): FormBuilderInterface
    {
        $builder->get('champDisc')->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $this->addDisc($form->getParent(), $form->getData());
        });
        if($builder->has('disciFond')) $builder->get('disciFond')->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $this->addDivi($form->getParent(), $form->getData());
        });
        return $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

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
            if($form->has('disciFond')) {
                /* @var $codewey Dewey */
                $codewey = $data->getCodewey();
                $division = $codewey?->getParent();
                $discip = $division?->getParent();

                $this->addDivi($form, $discip);
                $this->addCode($form, $division);
                if ($codewey) {
                    $form->get('disciFond')->setData($discip);
                    $form->get('division')->setData($division);
                }
            }
        });
    }

    public function new(AdminContext $context)
    {
        $resParams = parent::new($context);
        if ($resParams instanceof KeyValueStore && $folderId = $context->getRequest()->get('folderId')) {
            /** @var Notice $entity */
            $entity = $context->getEntity()->getInstance();
            $folder = $this->rep->findOneForAll($folderId);
            $context->getEntity()->setInstance($entity->setRepertoire($folder));
            $resParams->set('curritem', $folder);
        }
        return $resParams;
    }

    public function detail(AdminContext $context): KeyValueStore
    {
        $resParams = parent::detail($context);
        if ($folderId = $context->getRequest()->get('folderId')) {
            $folder = $this->rep->findOneForAll($folderId);
            $context->getEntity()->setActions(ActionCollection::new(array_map(function(ActionDto $action) use($folderId) {
                $action->setLinkUrl(sprintf("%s&folderId=%d",$action->getLinkUrl(),$folderId));
                return $action;
            }, $context->getEntity()->getActions()->all())));
            $resParams->set('curritem', $folder);
        }
        return $resParams;
    }

    public function moveNotice(AdminContext $ctx): Response
    {
        $request = $ctx->getRequest()->get("dossier");

        /** @var Notice $notice */
        $notice = $ctx->getEntity()->getInstance();
        if($request["dossier"] && $dossier = $this->rep->find($request["dossier"])) $notice->setRepertoire($dossier);
        return $this->changEtatNotice(['Déplacer', 'Déplacée', 'Déplacement', false], $notice, $ctx->getRequest()->get('folderId'));
    }
}
