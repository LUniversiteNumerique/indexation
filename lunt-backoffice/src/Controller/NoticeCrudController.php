<?php

namespace App\Controller;

use App\{Event\AfterNoticeStateSetEvent,Repository\NoticeRepository,Security\Voter\NoticeActionVoter,Service\MailerService,Validator\UploadImage};
use App\Entity\{Dewey, Discipline, Etablissement, Notice, NoticEtat, Univerique, User};
use App\Field\{DurationField, EntityField};
use App\Form\{AuteurAutoField, TagType};
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\{Context\AdminContext,Event\AfterEntityPersistedEvent,Factory\FormFactory,Filter\ChoiceFilter,Router\AdminUrlGenerator};
use EasyCorp\Bundle\EasyAdminBundle\Collection\{FieldCollection, FilterCollection};
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Asset, Assets, Crud, Filters, KeyValueStore};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\{EntityDto, SearchDto};
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
        if($this->isGranted('ROLE_VALI_NOTI')) $crud->renderSidebarMinimized()->overrideTemplate('crud/detail', 'admin/actions/notice.html.twig');
        return $crud;
    }

    public function configureActions(Actions $actions): Actions
    {
        $duplicate = Action::new('dupliquer',null,'fa fa-copy')->linkToCrudAction('duplicateNotice');
        $forward = Action::new('soumettre',null,'fa fa-send')->linkToCrudAction(self::FORWARD_ACTION);
        $approve = Action::new('valider',null,'fa fa-check')->linkToCrudAction('approveNotice');
        $reject = Action::new('rejeter',null,'fa fa-close')->linkToCrudAction('rejectNotice');
        $publish = Action::new('dépublier',null,'fa fa-step-backward')->linkToCrudAction('publishNotice');
        $allowed = Action::new('autoriser',null,'fa fa-fast-backward')->linkToCrudAction('allowedNotice');
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
            ->setPermission(Action::NEW, 'ROLE_CREA_NOTI');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('etat')
            ->setChoices(array_flip(NoticEtat::getValues()))->renderExpanded())
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
        yield Field\TextField::new('titre');
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
        yield Field\ChoiceField::new('etat')->setChoices(NoticEtat::getValues())->renderAsBadges(['En travail' => 'dark', 'Soumise' => 'danger', 'Validée' => 'success'])->hideOnForm();

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

    /** @throws NotFoundExceptionInterface|ContainerExceptionInterface */
    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $submitButtonName = $context->getRequest()->request->all()['ea']['newForm']['btn'];
        if (self::SAVE_AND_FORWARD === $submitButtonName) {
            $url = $this->container->get(AdminUrlGenerator::class)->setController(self::class)
                ->setAction(self::FORWARD_ACTION)->setEntityId($context->getEntity()->getPrimaryKeyValue());

            return $this->redirect($url->generateUrl());
        }

        return parent::getRedirectResponseAfterSave($context, $action);
    }

    /** @throws NotFoundExceptionInterface|ContainerExceptionInterface */
    public function duplicateNotice(): Response
    {
        /** @var Notice $notice */
        $notice = $this->getContext()->getEntity()->getInstance();
        $dupNot = (clone $notice)->setCreeLe(new \DateTimeImmutable());

        $this->repository->add($dupNot->setCreateur($this->getUser()));
        $this->container->get('event_dispatcher')->dispatch(new AfterEntityPersistedEvent($dupNot));
        $this->addFlash('success', "Cette notice dupliquée vient d'être créé avec succès !");

        /** @var AdminUrlGenerator $genUrl */
        $genUrl = $this->generator->setController(self::class)
            ->setAction(Action::DETAIL)->setEntityId($dupNot->getId())->removeReferrer();
        return $this->redirect($genUrl->generateUrl());
    }

    public function forwardNotice(): Response
    {
        $ctx = $this->getContext();

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Working
        return $this->redirect($this->changEtatNotice(
            ['Soumettre', NoticEtat::Forward->value, 'Soummision'],
            $notice->setEtat(NoticEtat::Forward))
        );
    }

    public function approveNotice(): Response
    {
        $ctx = $this->getContext();

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Forward
        return $this->redirect($this->changEtatNotice(
            ['Valider', NoticEtat::Approved->value, 'Validation'],
            $notice->setEtat(NoticEtat::Approved))
        );
    }

    public function rejectNotice(): Response
    {
        $ctx = $this->getContext();

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Forward
        return $this->redirect($this->changEtatNotice(
            array('Rejeter', 'Rejetée', 'Rejet'),
            $notice->setEtat(NoticEtat::Working))
        );
    }

    public function publishNotice(): Response
    {
        $ctx = $this->getContext();

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Approved
        return $this->redirect($this->changEtatNotice(
            ['Dépublier', 'Dépubliée', 'Dépublication'],
            $notice->setEtat(NoticEtat::Forward))
        );
    }

    public function allowedNotice(): Response
    {
        $ctx = $this->getContext();

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Approved
        return $this->redirect($this->changEtatNotice(
            ['Autoriser', 'Autorisée', 'Autorisation'],
            $notice->setEtat(NoticEtat::Working)->setEditDemande(false))
        );
    }

    public function adjustNotice(): Response
    {
        $ctx = $this->getContext();

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Approved
        return $this->redirect($this->changEtatNotice(
            array('Rectifier', 'Signalée', 'Rectification'),
            $notice->setEditDemande(true))
        );
    }

    public function labelNotice(AdminContext $ctx): Response
    {
        $label = $ctx->getRequest()->get('label');

        /** @var Notice $notice */
        $notice = $ctx->getEntity()->getInstance();
        return $this->redirect($this->changEtatNotice(
            array('Catégoriser', 'Labellisée', 'Catégorisation'),
            $notice->setLabel($label))
        );
    }

    private function changEtatNotice(array $transition,Notice $notice): string
    {
        $url = $this->generator->setController(self::class)
            ->setAction(Action::INDEX)->removeReferrer();
        $note = sprintf("La notice est bien %s avec succès !",$transition[1]);

        $this->repository->add($notice);
        $this->mailer->sendEmail($notice->getCreateur()?->getEmail(),sprintf("Notice %d en statut %s",$notice->getId(),$notice->getEtat()?->value), $note);
        try {
            $this->container->get('event_dispatcher')->dispatch(new AfterNoticeStateSetEvent($notice, $transition));
            $this->addFlash('success', $note);
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {}

        return $url->generateUrl();
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

    /**
     * @param FormBuilderInterface $builder
     * @return FormBuilderInterface
     */
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
}
