<?php

namespace App\Controller;

use App\Event\{AfterNoticeAdjustingEvent, AfterNoticeApprovingEvent, AfterNoticeRejectingEvent, AfterNoticeStateSetEvent};
use App\Security\Voter\NoticeActionVoter;
use App\Entity\{Dewey, Discipline, Notice, NoticEtat, Univerique, User};
use App\Field\{DurationField, EntityField, FileField};
use App\Form\Type\{AuteurAutoField, NoticeAutoField, TagAutoField, TreeChoiceType};
use App\Repository\{DossierRepository, NoticeRepository};
use Doctrine\ORM\{QueryBuilder,EntityManagerInterface};
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\{FileUploadType, Model\FileUploadState};
use EasyCorp\Bundle\EasyAdminBundle\Filter\{ChoiceFilter, DateTimeFilter, EntityFilter, TextFilter};
use EasyCorp\Bundle\EasyAdminBundle\{Context\AdminContext, Event\AfterEntityPersistedEvent, Factory\FormFactory, Provider\AdminContextProvider, Router\AdminUrlGenerator};
use EasyCorp\Bundle\EasyAdminBundle\Collection\{ActionCollection, FieldCollection, FilterCollection};
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Asset, Assets, Crud, Filters, KeyValueStore};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\{ActionDto, EntityDto, SearchDto};
use EasyCorp\Bundle\EasyAdminBundle\Field as Field;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\{FormBuilderInterface, FormEvent, FormEvents, FormInterface};
use Symfony\Component\HttpFoundation\{RedirectResponse, Request, Response};
use Symfony\Component\{Intl\Languages, Routing\Generator\UrlGeneratorInterface, Uid\Uuid};
use Symfony\Component\Validator\Constraints\{File, Image};
use function Symfony\Component\{String\u,Translation\t};

class NoticeCrudController extends AbstractCrudController
{
    const SAVE_AND_FORWARD = 'saveAndForward';
    const FORWARD_ACTION = 'forwardNotice';

    public function __construct(
        private readonly FormFactory $factory,
        private readonly DossierRepository $rep,
        private readonly NoticeRepository $repository,
        private readonly AdminUrlGenerator $generator,
        private readonly UrlGeneratorInterface $router,
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    public static function getEntityFqcn(): string
    {
        return Notice::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $crud = $crud->setAutofocusSearch()->setSearchFields(['titre','description'])->setDefaultSort(['creeLe' => 'DESC'])
            ->setEntityLabelInPlural('Notices')->setEntityLabelInSingular('notice')->setFormOptions([
                'attr' => ['data-controller'=>"notice-setting", 'data-notice-setting-target'=>"form"]
            ])
            ->setPageTitle(Action::NEW, fn () => 'Créer une <b>notice</b>')
            ->setPageTitle(Action::EDIT, fn (Notice $n) => 'Modifier une <b>notice</b>')
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (Notice $n) => $n->getTitre());
        if($this->isGranted('ROLE_VALI_NOTI')) $crud->renderSidebarMinimized()->overrideTemplates([
            'crud/detail'=>'admin/actions/notice_show.html.twig',
            'crud/new'=>'admin/actions/notice_new.html.twig'
        ]);
        return $crud;
    }

    public function configureActions(Actions $actions): Actions
    {
        $duplicate = Action::new('dupliquer',null,'fa fa-copy')->linkToCrudAction('duplicateNotice')->addCssClass('btn btn-outline-light')->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Dupliquer cette notice']);
        $forward = Action::new('soumettre',null,'fa fa-send')->linkToCrudAction(self::FORWARD_ACTION)->addCssClass('btn btn-outline-success')->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Soumettre cette notice']);
        $approve = Action::new('valider',null,'fa fa-check')->linkToCrudAction('approveNotice')->addCssClass('btn btn-outline-success')->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Valider la notice pour publication']);
        $reject = Action::new('rejeter',null,'fa fa-close')->linkToCrudAction('rejectNotice')->addCssClass('btn btn-outline-danger')->setHtmlAttributes(['data-bs-toggle' => 'modal', 'data-bs-target' => '#modal-reject', 'data-toggle' => 'tooltip', 'title' => 'Rejeter la notice pour correction']);
        $publish = Action::new('dépublier',null,'fa fa-step-backward')->linkToCrudAction('publishNotice')->addCssClass('btn btn-outline-danger')->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Dépublier cette notice publiée']);
        $allowed = Action::new('autoriser',null,'fa fa-fast-backward')->linkToCrudAction('allowedNotice')->addCssClass('btn btn-outline-info')->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Autoriser la notice pour modification']);
        $adjust = Action::new('rectifier',null,'fa fa-share-square')->linkToCrudAction('adjustNotice')->addCssClass('btn btn-outline-info')->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Demander la modification de cette notice']);
        $category = Action::new('catégoriser',null,'fa fa-tag')->linkToCrudAction('labelNotice')->addCssClass('btn btn-outline-warning confirm-action')->setHtmlAttributes(['data-bs-toggle' => 'modal', 'data-bs-target' => '#modal-confirm',]);
        $saward = Action::new(self::SAVE_AND_FORWARD, 'Créer et soumettre la notice', 'fa fa-send')->linkToCrudAction(Action::NEW)->setHtmlAttributes(['type' => 'submit', 'name' => 'ea[newForm][btn]', 'value' => self::SAVE_AND_FORWARD]);

        $fwdoc = fn(Notice $n,string $s = NoticeActionVoter::VALI) => $this->isGranted($s,$n);
        
        return $actions
            ->add(Crud::PAGE_DETAIL, $duplicate->displayIf(static fn (Notice $n) => $fwdoc($n,NoticeActionVoter::VIEW)))
            ->add(Crud::PAGE_DETAIL, $forward->displayIf(static fn (Notice $n) => $n->getEtat()===NoticEtat::Working && $fwdoc($n,NoticeActionVoter::EDIT)))
            ->add(Crud::PAGE_DETAIL, $reject->displayIf(static fn (Notice $n) => $n->getEtat()===NoticEtat::Forward && $fwdoc($n)))
            ->add(Crud::PAGE_DETAIL, $approve->displayIf(static fn (Notice $n) => $n->getEtat()===NoticEtat::Forward && $fwdoc($n)))
            ->add(Crud::PAGE_DETAIL, $publish->displayIf(static fn (Notice $n) => $n->getEtat()===NoticEtat::Approved && $fwdoc($n)))
            ->add(Crud::PAGE_DETAIL, $category->displayIf(static fn (Notice $n) => $n->getEtat()===NoticEtat::Approved && $fwdoc($n)))
            ->add(Crud::PAGE_DETAIL, $allowed->displayIf(static fn (Notice $n) => $fwdoc($n) && $n->isEditDemande()))
            ->add(Crud::PAGE_DETAIL, $adjust->displayIf(static fn (Notice $n) => $n->getEtat()===NoticEtat::Approved && $fwdoc($n,NoticeActionVoter::DEFA) && !$n->isEditDemande()))
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_NEW, Action::INDEX)//->add(Crud::PAGE_NEW, $saward->displayAsButton())
            ->update(Crud::PAGE_INDEX, Action::DETAIL, fn (Action $a) => $a->setCssClass('btn btn-outline-secondary'))
            ->update(Crud::PAGE_DETAIL, Action::EDIT, static fn(Action $a) => $a->setIcon('fa fa-pencil')->displayIf(static fn (Notice $n) => $fwdoc($n, NoticeActionVoter::EDIT)))
            ->update(Crud::PAGE_DETAIL, Action::DELETE, static fn(Action $a) => $a->addCssClass('btn btn-outline-danger')->displayIf(static fn (Notice $n) => $fwdoc($n,NoticeActionVoter::DROP)))
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action) => $action->setLabel('Créer une <b>notice</b>'))
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER, fn (Action $a) => $a->setLabel('Créer et ajouter une <b>nouvelle</b>'))
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::INDEX)
            ->remove(Crud::PAGE_NEW,Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_EDIT,Action::SAVE_AND_CONTINUE)
            ->setPermission(Action::INDEX, 'ROLE_READ_NOTI') 
            ->setPermission(Action::NEW, 'ROLE_CREA_NOTI')
            ->setPermission(Action::DETAIL, 'ROLE_READ_NOTI')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_NOTI')  
            ->setPermission(Action::DELETE, 'ROLE_DROP_NOTI');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('etat')->setChoices(NoticEtat::getLabels())->renderExpanded())
            ->add(TextFilter::new('titre'))
            ->add(EntityFilter::new('specialite', 'Spécialité'))
            ->add(EntityFilter::new('auteurs'))
            ->add(DateTimeFilter::new('creeLe', 'Créée le'))
            ->add(DateTimeFilter::new('editeLe', 'Date de modification'));
    }

    public function configureFields(string $pageName): iterable
    {
        /** @var User $user */ $user = $this->getUser();
        $valdoc = $this->isGranted('ROLE_VALI_NOTI');
        $langList = array_merge(['français' => 'fra'], array_flip(Languages::getAlpha3Names('fr')));
        if ($valdoc) yield Field\FormField::addTab('Soumission')->setHelp("Infos renseignées par la contribution des établissements");

        yield Field\FormField::addColumn(6);

        yield Field\FormField::addFieldset('Description générale')->setIcon('fa fa-pencil');
        yield Field\IdField::new('id')->onlyOnDetail();
        yield Field\TextField::new('titre')->setHelp(t('notice.titre_help', domain: 'EasyAdminBundle'));
        yield Field\TextEditorField::new('description')->setHelp(t('notice.description_help', domain: 'EasyAdminBundle'))->setTemplatePath('admin/fields/text_editor.html.twig')->hideOnIndex()->setRequired(true);
        yield EntityField::new('porteurs', t('notice.porteurs', domain: 'EasyAdminBundle'))->setHelp(t('notice.porteurs_help', domain: 'EasyAdminBundle'))
            ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->orderBy('entity.abrege', 'ASC'))->setSortable(false)->setRequired(true)->hideOnIndex();
        yield EntityField::new('auteurs',t('notice.auteurs', domain: 'EasyAdminBundle'))->setFormType(AuteurAutoField::class)
            ->setHelp(t('notice.auteurs_help', domain: 'EasyAdminBundle'))->setSortable(false)->setRequired(true);
        yield EntityField::new('tags', t('notice.tags', domain: 'EasyAdminBundle'))->setRequired(true)->hideOnIndex()
            ->setHelp(t('notice.tags_help', domain: 'EasyAdminBundle'))->setFormType(TagAutoField::class)->setFormTypeOption('attr', [
                'data-tag-autocreate-url-value' => $this->router->generate('app_keywork_new'), 'data-controller' => 'tag-autocreate'
            ]);
        yield Field\ChoiceField::new('ressDate', t('notice.date', domain: 'EasyAdminBundle'))->setChoices(array_flip(range((int) date('Y'), (int) date('Y') - 100)))->hideOnIndex();

        yield Field\FormField::addFieldset('Liens de la ressource')->setIcon('fa fa-paperclip');

        if (Crud::PAGE_NEW  === $pageName || $pageName === Crud::PAGE_EDIT) {
            yield Field\BooleanField::new('zipFile', 'Fichier Zip')->setFormTypeOptions(['mapped' => false, 'attr' => ['data-notice-setting-target' => 'ressToggle',]])->onlyOnForms();
            yield Field\TextField::new('ressUrl', t('notice.ressurl', domain: 'EasyAdminBundle'))->setHelp(t('notice.ressurl_help', domain: 'EasyAdminBundle'))
                ->setFormTypeOptions(['attr' => ['placeholder' => 'https://...'], 'required' => true])->setSortable(false);
            yield FileField::new('ressZip', 'Contenu Zip')->setUploadDir('public/uploads/files')->setHelp(t('notice.ressurl_help', domain: 'EasyAdminBundle'))
                ->setUploadedFileNamePattern('[timestamp]-[randomhash].[extension]')->setBasePath('uploads/files')->setRequired(true)->onlyOnForms()
                ->setFileConstraints([new File(maxSize: '64M', mimeTypes: ["application/zip", "application/x-zip-compressed", "multipart/x-zip"])]);
        } else yield Field\UrlField::new('ressUrl', t('notice.ressurl', domain: 'EasyAdminBundle'))->setHelp(t('notice.ressurl_help', domain: 'EasyAdminBundle'));

        yield EntityField::new('ressources', t('notice.notices', domain: 'EasyAdminBundle'))->hideOnIndex()
            ->setHelp(t('notice.notices_help', domain: 'EasyAdminBundle'))->setFormType(NoticeAutoField::class);
        yield Field\ChoiceField::new('etat')->setChoices(NoticEtat::getLabels())->renderAsBadges(NoticEtat::getColors())->hideOnForm();
        yield Field\AssociationField::new('validateur',t('notice.validateur', domain: 'EasyAdminBundle'))->onlyOnDetail();

        yield Field\FormField::addFieldset('Droits attachés à la ressource')->setIcon('fa fa-gavel');
        yield Field\AssociationField::new('droit',t('notice.droit', domain: 'EasyAdminBundle'))
            ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->orderBy('entity.valeur', 'ASC'))->setHelp(t('notice.droit_help', domain: 'EasyAdminBundle'))->setSortable(false)->hideOnIndex();
        yield Field\BooleanField::new('ressPayant',t('notice.resspayant', domain: 'EasyAdminBundle'))->setHelp(t('notice.resspayant_help', domain: 'EasyAdminBundle'))->renderAsSwitch(false)->hideOnIndex()->setColumns(6);
        yield Field\BooleanField::new('proprIntel',t('notice.proprintel', domain: 'EasyAdminBundle'))->setHelp(t('notice.proprintel_help', domain: 'EasyAdminBundle'))->renderAsSwitch(false)->hideOnIndex()->setColumns(6);

        yield Field\FormField::addColumn(6);

        yield Field\FormField::addFieldset('Indications pédagogiques')->setIcon('fa fa-th-list');
        yield Field\ChoiceField::new('ressLang', t('notice.resslang', domain: 'EasyAdminBundle'))->setChoices($langList)->allowMultipleChoices()
            ->setHelp(t('notice.resslang_help', domain: 'EasyAdminBundle'))->renderAsBadges()->setColumns(6)->setRequired(true)->hideOnIndex();
        yield DurationField::new('dureAppr', "Durée d'apprentissage")->setHelp(t('notice.dureappr_help', domain: 'EasyAdminBundle'))->hideOnIndex()->setColumns(6);
        yield EntityField::new('pedTypes', t('notice.pedtypes', domain: 'EasyAdminBundle'))->setHelp(t('notice.pedtypes_help', domain: 'EasyAdminBundle'))
            ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->orderBy('entity.nom', 'ASC'))->setRequired(true)->setSortable(false)->hideOnIndex();
        yield Field\ArrayField::new('propUser', t('notice.propuser', domain: 'EasyAdminBundle'))->setHelp(t('notice.propuser_help', domain: 'EasyAdminBundle'))->hideOnIndex();
        yield EntityField::new('docTypes', t('notice.doctypes', domain: 'EasyAdminBundle'))->setHelp(t('notice.doctypes_help', domain: 'EasyAdminBundle'))
            ->setFormTypeOption('multiple', true)->setFormTypeOption('expanded', true)->setColumns(6)->hideOnIndex()->setRequired(true);
        yield EntityField::new('niveaux', t('notice.niveaux', domain: 'EasyAdminBundle'))->setFormTypeOption('multiple', true)->hideOnIndex()
            ->setFormTypeOption('expanded', true)->setHelp(t('notice.niveaux_help', domain: 'EasyAdminBundle'))->setColumns(6)->setRequired(true);

        yield Field\FormField::addFieldset('Classification thématique')->setIcon('fa fa-book');
        yield EntityField::new('champDisc', t('notice.champdisc', domain: 'EasyAdminBundle'))->setHelp(t('notice.champdisc_help', domain: 'EasyAdminBundle'))
            ->setFormTypeOptions(['class' => Discipline::class, 'mapped' => false, 'required' => true])->setQueryBuilder(function (QueryBuilder $qb) use ($user) {
                if ($user->getUntheme() instanceof Univerique)
                    $qb->where('entity IN (:champs)')->setParameter('champs', $user->getUntheme()->getFields());
                else $qb->where('entity.parent IS NULL');

                return $qb
                    ->join('entity.children','s')->leftJoin('s.children','d')->addSelect('s,d')
                    ->orderBy('entity.nom', 'ASC')->addOrderBy('s.nom', 'ASC')->addOrderBy('d.nom', 'ASC');
            })->setSortable(false)->onlyOnForms();
        yield EntityField::new('discipline')->setQueryBuilder(fn(QueryBuilder $qb) => $qb->orderBy('entity.nom', 'ASC'))->setHelp(t('notice.discipline_help', domain: 'EasyAdminBundle'))
            ->setFormTypeOptions(['class' => Discipline::class, 'auto_initialize' => false, 'mapped' => false])->setSortable(false)->onlyOnForms();
        yield EntityField::new('specialites', 'Specialités')->setFormTypeOption('class', Discipline::class)->setFormTypeOption('autocomplete', true)
            ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->orderBy('entity.nom', 'ASC'))->setFormTypeOption('multiple', true)->setSortable(false);
        yield Field\DateTimeField::new('creeLe',t('notice.creele', domain: 'EasyAdminBundle'))->onlyOnDetail();
        yield Field\DateTimeField::new('editeLe', t('notice.editele', domain: 'EasyAdminBundle'))->hideOnForm();

        if ($valdoc) {
            yield Field\FormField::addTab('Validation')->setHelp("Infos techniques complémentaires de validation");
            yield Field\FormField::addColumn(6);

            yield Field\FormField::addFieldset('Liens de la ressource')->setIcon('fa fa-folder-open');
            yield Field\ImageField::new('vignette')->setUploadDir('public/uploads/images')->setHelp(t('notice.vignette_help', domain: 'EasyAdminBundle'))->setBasePath('/uploads/images')
                ->setUploadedFileNamePattern('[timestamp]-[contenthash].[extension]')->setFileConstraints([new Image(['maxWidth' => 620, 'maxHeight' => 390])])->setSortable(false);
            yield Field\NumberField::new('ressSize',t('notice.resssize', domain: 'EasyAdminBundle'))->setHelp(t('notice.resssize_help', domain: 'EasyAdminBundle'))->setColumns(6)->hideOnIndex();
            yield DurationField::new('dureExec', "Durée d'exécution")->setHelp(t('notice.dureexec_help', domain: 'EasyAdminBundle'))->setColumns(6)->hideOnIndex();
            yield Field\UrlField::new('formEvalUrl',t('notice.formevalurl', domain: 'EasyAdminBundle'))
                ->setFormTypeOptions(['default_protocol' => 'https', 'attr' => ['class' => 'isUrl', 'placeholder' => 'https://...']])->setHelp(t('notice.formevalurl_help', domain: 'EasyAdminBundle'))->hideOnIndex();
            yield Field\ChoiceField::new('userLang',t('notice.userlang', domain: 'EasyAdminBundle'))->setHelp(t('notice.userlang_help', domain: 'EasyAdminBundle'))->hideOnIndex()
                ->setChoices($langList)->allowMultipleChoices()->renderAsBadges()->setFormTypeOption('autocomplete',true)->setRequired(true);
            yield Field\TextEditorField::new('objectif',t('notice.objectif', domain: 'EasyAdminBundle'))->setHelp(t('notice.objectif_help', domain: 'EasyAdminBundle'))->hideOnIndex()->formatValue(function ($value, $entity) { return $value;});
            yield Field\TextField::new('champExt1',"Champ d'extension 1")->hideOnIndex(); yield Field\TextField::new('champExt2',"Champ d'extension 2")->hideOnIndex();
            yield Field\TextField::new('champExt3',"Champ d'extension 3")->hideOnIndex(); yield Field\TextField::new('champExt4',"Champ d'extension 4")->hideOnIndex();
            yield Field\TextField::new('champExt5',"Champ d'extension 5")->hideOnIndex();
            yield Field\BooleanField::new('exportOAI', t('notice.exportoai', domain: 'EasyAdminBundle'))->setHelp(t('notice.exportoai_help', domain: 'EasyAdminBundle'))->renderAsSwitch(false)->hideOnIndex();

            yield Field\FormField::addColumn(6);

            yield Field\FormField::addFieldset('Classification thématique')->setIcon('fa fa-book');
            yield EntityField::new('disciFond',t('notice.discifond', domain: 'EasyAdminBundle'))->setHelp(t('notice.discifond_help', domain: 'EasyAdminBundle'))->onlyOnForms()->setFormTypeOptions(['class' => Dewey::class,'mapped' => false,'required' => true])->setSortable(false)
                ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->join('entity.children','s')->leftJoin('s.children','d')->where('entity.parent is null')->addSelect('s,d')->orderBy('entity.nom', 'ASC')->addOrderBy('s.nom', 'ASC')->addOrderBy('d.nom', 'ASC'));
            yield EntityField::new('division')->setFormTypeOptions(['class' => Dewey::class,'auto_initialize' => false,'mapped' => false,'required' => true])->onlyOnForms();
            yield EntityField::new('codeweys','Codes Dewey')->setFormTypeOptions(['class' => Dewey::class,'multiple' => true,'autocomplete' => true])->setSortable(false);
            yield Field\TextField::new('label',t('notice.label', domain: 'EasyAdminBundle'))->setHelp(t('notice.label_help', domain: 'EasyAdminBundle'))->onlyOnDetail();
            yield EntityField::new('repertoire',t('notice.repertoire', domain: 'EasyAdminBundle'))->setHelp(t('notice.repertoire_help', domain: 'EasyAdminBundle'))
                ->setFormType(TreeChoiceType::class)->setQueryBuilder(fn(QueryBuilder $qb) => $qb->join('entity.children','s')->leftJoin('s.children','d')->addSelect('s,d'))->hideOnIndex();
            yield Field\BooleanField::new('editDemande', 'Rectifiée ?')->renderAsSwitch(false)->setSortable(false)->onlyOnIndex();
            yield Field\DateTimeField::new('publieLe',t('notice.publiele', domain: 'EasyAdminBundle'))->onlyOnDetail();
        }
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile(Asset::new('../assets/form.js')->onlyOnForms());
    }

    public function createEntity(string $entityFqcn): Notice
    {
        $ctx = $this->container->get(AdminContextProvider::class);
        /** @var Notice $notice */
        $notice = parent::createEntity($entityFqcn);
        $folder = $this->rep->findOneForAll($ctx->getRequest()->get('folderId',1));

        /** @var User $user */$user = $this->getUser();
        if($sch = $user->getSchool()) $notice->addPorteur($sch);
        return $notice->setCreateur($this->getUser())->setRepertoire($folder);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        /** @var User $user */$user = $this->getUser();
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)->select('entity,d,e,r,k,p,a,n,dd,pp,l,s')
            ->leftJoin('entity.repertoire','d')->leftJoin('entity.codeweys','e')->leftJoin('entity.ressources','r')->leftJoin('entity.tags','k')
            ->leftJoin('entity.porteurs','p')->leftJoin('entity.auteurs','a')->leftJoin('entity.niveaux','n')
            ->leftJoin('entity.docTypes','dd')->leftJoin('entity.pedTypes','pp')->leftJoin('entity.specialites','s')->join('entity.droit','l');

        $andX = $qb->expr()->andX('entity.etat != :etat');
        if ($sch = $user->getSchool()) {
            $qb->setParameter('school', $sch->getId());
            $andX->add(':school MEMBER OF entity.porteurs');
        } elseif ($unt = $user->getUntheme()) {
            $qb->join('s.parent','u')->setParameter('champs', $unt->getFields());
            $andX->add('u.parent in (:champs)');
        }
        $qb->andWhere($qb->expr()->orX(
            $qb->expr()->eq('entity.createur',':user'), $andX
        ))
            ->setParameter('etat', NoticEtat::Working)
            ->setParameter('user', $user->getId());

        return $qb->andWhere('entity.deleted = 0');
    }

    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formOptions->setIfNotSet('action', $context->getRequest()->getRequestUri());
        $builder = $this->factory->createNewFormBuilder($entityDto, $formOptions, $context);
        return $this->addFormEvent($builder);
    }

    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formOptions->setIfNotSet('action', $context->getRequest()->getRequestUri());
        $builder = $this->factory->createEditFormBuilder($entityDto, $formOptions, $context);
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

    protected function processUploadedFiles(FormInterface $form): void
    {
        /** @var FormInterface $child */
        foreach ($form as $child) {
            $config = $child->getConfig();

            if (!$config->getType()->getInnerType() instanceof FileUploadType) {
                if ($config->getCompound()) $this->processUploadedFiles($child);

                continue;
            }

            /** @var FileUploadState $state */
            $state = $config->getAttribute('state');

            if (!$state->isModified()) continue;

            $uploadDelete = $config->getOption('upload_delete');

            if ($state->hasCurrentFiles() && ($state->isDelete() || (!$state->isAddAllowed() && $state->hasUploadedFiles()))) {
                foreach ($state->getCurrentFiles() as $file)
                    $uploadDelete($file); // supprimer son dossier si zip
                $state->setCurrentFiles([]);
            }

            $filePaths = (array) $child->getData();
            $uploadDir = $config->getOption('upload_dir');
            $uploadNew = $config->getOption('upload_new');

            foreach ($state->getUploadedFiles() as $index => $file) {
                $fileName = u($filePaths[$index])->replace($uploadDir, '')->toString();
                $target = $uploadDir.pathinfo($fileName, PATHINFO_FILENAME);

                if ("zip" === $file->guessExtension()) {
                    $zip = new \ZipArchive(); dump($target);
                    if($zip->open($file->getRealPath())) $zip->extractTo($target);
                    $zip->close();
                } else $uploadNew($file, $uploadDir, $fileName);

            }
        }
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @var Notice $entityInstance
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if($zipDir = $entityInstance->getRessZip())
            $entityInstance->setRessUrl(sprintf('uploads/files/%s', pathinfo($zipDir, PATHINFO_FILENAME)));
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if($zipDir = $entityInstance->getRessZip())
            $entityInstance->setRessUrl(sprintf('uploads/files/%s', pathinfo($zipDir, PATHINFO_FILENAME)));
        parent::updateEntity($entityManager, $entityInstance);
    }


    /**
     * @param EntityManagerInterface $entityManager
     * @param Notice $entityInstance
     * @return void
     */
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $entityInstance->setDeleted(true);
        $entityManager->flush();
    }

    public function index(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_READ_NOTI');
        return parent::index($context);
    }

    public function new(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_CREA_NOTI');
        $resParams = parent::new($context);

        if ($context->getRequest()->get('folderId'))
            $resParams->set('curritem', $context->getEntity()->getInstance()?->getRepertoire());
        return $resParams;
    }

    public function detail(AdminContext $context): KeyValueStore
    {
        /** @var Notice $notice */
        $notice = $context->getEntity()->getInstance();
        $this->denyAccessUnlessGranted(NoticeActionVoter::VIEW, $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

        $resParams = parent::detail($context);
        if ($folderId = $context->getRequest()->get('folderId')) {
            $folder = $this->rep->findOneForAll($folderId);
            $context->getEntity()->setActions(ActionCollection::new(array_map(function(ActionDto $action) use($folderId) {
                $action->setHtmlAttribute('folderId', $folderId);
                return $action;
            }, $context->getEntity()->getActions()->all())));
            $resParams->set('curritem', $folder);
        }
        return $resParams;
    }

    public function edit(AdminContext $context)
    {
        /** @var Notice $notice */
        $notice = $context->getEntity()->getInstance();
        $this->denyAccessUnlessGranted(NoticeActionVoter::VIEW, $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

        return parent::edit($context);
    }

    public function delete(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_DROP_NOTI');
        return parent::delete($context);
    }

    public function duplicateNotice(): Response
    {
        $ctx = $this->getContext();

        /** @var Notice $notice */
        $notice = $ctx->getEntity()->getInstance();
        $this->denyAccessUnlessGranted(NoticeActionVoter::VIEW, $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");
        $dupNot = (clone $notice)->setCreateur($this->getUser())->setValidateur(null)->setCreeLe(new \DateTimeImmutable())->setEditeLe(null);

        $this->repository->add($dupNot->setUuid(Uuid::v4())->setEtat(NoticEtat::Working)->setEditDemande(false)->setTitre("COPIE - ".$dupNot->getTitre()));
        $this->dispatcher->dispatch(new AfterEntityPersistedEvent($dupNot));
        $this->addFlash('success', "Cette notice dupliquée vient d'être créé avec succès !");

        /** @var AdminUrlGenerator $genUrl */
        $genUrl = $this->generator->setController(self::class)->setAction(Action::DETAIL)->setEntityId($dupNot->getId());
        if($folderId = $ctx->getRequest()->get('folderId')) $genUrl->set('folderId',$folderId);
        return $this->redirect($genUrl->removeReferrer()->generateUrl());
    }

    public function forwardNotice(): Response
    {
        $ctx = $this->getContext();
        $url = $this->redirecTo($ctx->getRequest())->removeReferrer();

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Working
        $this->denyAccessUnlessGranted(NoticeActionVoter::EDIT, $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

        $this->repository->add($notice->setEtat(NoticEtat::Forward));
        $this->dispatcher->dispatch(new AfterNoticeStateSetEvent($notice, ['Soumettre', NoticEtat::Forward->getLabel(), 'Soummision']));
        $this->addFlash('success', sprintf("La notice est bien %s avec succès !",NoticEtat::Forward->getLabel()));

        return $this->redirect($url->generateUrl());
    }

    public function approveNotice(): Response
    {
        $ctx = $this->getContext();
        $url = $this->redirecTo($ctx->getRequest())->removeReferrer();

        /** @var User $user */ $user = $this->getUser();
        /** @var Notice|null $notice */ $notice = $ctx->getEntity()->getInstance(); //Forward
        $this->denyAccessUnlessGranted(NoticeActionVoter::EDIT, $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

        $this->repository->add($notice->setEtat(NoticEtat::Approved)->setValidateur($user));
        $this->dispatcher->dispatch(new AfterNoticeApprovingEvent($notice));
        $this->addFlash('success', sprintf("La notice est bien %s avec succès !",NoticEtat::Approved->getLabel()));

        return $this->redirect($url->generateUrl());
    }

    public function rejectNotice(): Response
    {
        $ctx = $this->getContext();
        $url = $this->redirecTo($ctx->getRequest())->removeReferrer();
        $note = $ctx->getRequest()->get('motifs');

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Forward
        $this->denyAccessUnlessGranted(NoticeActionVoter::EDIT, $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

        $this->repository->add($notice->setEtat(NoticEtat::Working));
        $this->dispatcher->dispatch(new AfterNoticeRejectingEvent($notice, $note));
        $this->addFlash('success', "La notice est bien rejetée avec succès !");

        return $this->redirect($url->generateUrl());
    }

    public function publishNotice(): Response
    {
        $ctx = $this->getContext();
        $url = $this->redirecTo($ctx->getRequest())->removeReferrer();

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Approved
        $this->denyAccessUnlessGranted('ROLE_VALI_NOTI', $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

        $this->repository->add($notice->setEtat(NoticEtat::Forward));
        $this->dispatcher->dispatch(new AfterNoticeStateSetEvent($notice, ['Dépublier', 'Dépubliée', 'Dépublication']));
        $this->addFlash('success', "La notice est bien dépubliée avec succès !");

        return $this->redirect($url->generateUrl());
    }

    public function allowedNotice(): Response
    {
        $ctx = $this->getContext();
        $url = $this->redirecTo($ctx->getRequest())->removeReferrer();

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Approved
        $this->denyAccessUnlessGranted('ROLE_VALI_NOTI', $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

        $this->repository->add($notice->setEtat(NoticEtat::Working)->setEditDemande(false));
        $this->dispatcher->dispatch(new AfterNoticeStateSetEvent($notice, ['Autoriser', 'Autorisée', 'Autorisation']));
        $this->addFlash('success', "La notice est bien autorisée avec succès !");

        return $this->redirect($url->generateUrl());
    }

    public function adjustNotice(): Response
    {
        $ctx = $this->getContext();
        $url = $this->redirecTo($ctx->getRequest())->removeReferrer();

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Approved
        $this->denyAccessUnlessGranted(NoticeActionVoter::VIEW, $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

        $this->repository->add($notice->setEditDemande(true));
        $this->dispatcher->dispatch(new AfterNoticeAdjustingEvent($notice));
        $this->addFlash('success', "Votre demande de rectification est bien envoyée !");

        return $this->redirect($url->generateUrl());
    }

    public function labelNotice(): Response
    {
        $ctx = $this->getContext();
        $url = $this->redirecTo($ctx->getRequest())->removeReferrer();
        $label = $ctx->getRequest()->get('label');

        /** @var Notice $notice */
        $notice = $ctx->getEntity()->getInstance();
        $this->denyAccessUnlessGranted('ROLE_VALI_NOTI', $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

        $this->repository->add($notice->setLabel($label));
        $this->dispatcher->dispatch(new AfterNoticeStateSetEvent($notice, ['Catégoriser', 'Labellisée', 'Catégorisation']));
        $this->addFlash('success', "La notice est labellisée avec succès !");

        return $this->redirect($url->generateUrl());
    }

    public function moveNotice(AdminContext $ctx): Response
    {
        $url = $this->redirecTo($ctx->getRequest())->removeReferrer();
        $request = $ctx->getRequest()->get("dossier");

        /** @var Notice $notice */
        $notice = $ctx->getEntity()->getInstance();
        $this->denyAccessUnlessGranted(NoticeActionVoter::EDIT, $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");
        if($request["dossier"] && $dossier = $this->rep->find($request["dossier"])) $notice->setRepertoire($dossier);

        $this->repository->add($notice);
        $this->dispatcher->dispatch(new AfterNoticeStateSetEvent($notice, ['Déplacer', 'Déplacée', 'Déplacement']));
        $this->addFlash('success', "La notice est bien déplacée avec succès !");

        return $this->redirect($url->generateUrl());
    }

    private function addDisc(FormInterface $form, ?array $children): void
    {
        $builder = $form->getConfig()->getFormFactory()->createNamedBuilder('discipline', EntityType::class, null, [
            'class' => Discipline::class, 'mapped' => false, 'auto_initialize' => false, 'required' => true,
            'label' => t('notice.discipline', domain: 'EasyAdminBundle'), 'choices' => $children ?? [], 'help' => t('notice.discipline_help', domain: 'EasyAdminBundle'),
            'placeholder' => $children ? 'Sélectionnez la discipline' : 'Sélectionnez le champ disciplinaire',
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            /** @var Discipline $spec */
            $spec = $form->getData();
            $this->addSpec($form->getParent(), $spec?->getChildren()->toArray());
        });

        $form->add($builder->getForm());
    }
    private function addDivi(FormInterface $form, ?array $children): void
    {
        $builder = $form->getConfig()->getFormFactory()->createNamedBuilder('division', EntityType::class, null, [
            'class' => Dewey::class, 'mapped' => false, 'auto_initialize' => false, 'required' => true,
            'label' => t('notice.division', domain: 'EasyAdminBundle'), 'choices' => $children ?? [], 'help' => t('notice.division_help', domain: 'EasyAdminBundle'),
            'placeholder' => $children ? 'Sélectionnez la division' : 'Sélectionnez la discipline fondamentale',
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            /** @var Dewey $dewe */
            $dewe = $form->getData();
            $this->addCode($form->getParent(), $dewe?->getChildren()->toArray());
        });

        $form->add($builder->getForm());
    }

    private function addSpec(FormInterface $form, ?array $children): void {
        $form->add('specialites', EntityType::class, [
            'label' => t('notice.specialite', domain: 'EasyAdminBundle'), 'class' => Discipline::class, 'multiple' => true,
            'choices' => $children ?? [], 'help' => t('notice.specialite_help', domain: 'EasyAdminBundle'),
            'placeholder' => $children ? 'Sélectionnez la spécialité' : 'Sélectionnez la discipline', 'autocomplete' => true
        ]);
    }
    private function addCode(FormInterface $form, ?array $children): void {
        $form->add('codeweys', EntityType::class, [
            'label' => t('notice.codewey', domain: 'EasyAdminBundle'), 'class' => Dewey::class, 'required' => true,
            'choices' => $children ?? [], 'help' => t('notice.codewey_help', domain: 'EasyAdminBundle'), 'multiple' => true,
            'placeholder' => $children ? 'Sélectionnez le code dewey' : 'Sélectionnez la division', 'autocomplete' => true
        ]);
    }

    private function addFormEvent(FormBuilderInterface $builder): FormBuilderInterface
    {
        $builder->get('champDisc')->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            /** @var Discipline $disc */
            $disc = $form->getData();
            $this->addDisc($form->getParent(), $disc?->getChildren()->toArray());
        });
        if ($builder->has('disciFond')) $builder->get('disciFond')->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            /** @var Dewey $divi */
            $divi = $form->getData();
            $this->addDivi($form->getParent(), $divi?->getChildren()->toArray());
        });

        return $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            /* @var $data Notice */
            $data = $event->getData();
            $form = $event->getForm();

            /* @var $specialite Discipline */
            $specialite = $data->getSpecialites()->current()?:null;
            $discipline = $specialite?->getParent();
            $champ = $discipline?->getParent();

            $itr = function(array $arr) {
                usort($arr, fn($a,$b) => strcmp($a->getNom(), $b->getNom()));
                return $arr;
            };

            $this->addDisc($form, $champ ?
                $itr($champ->getChildren()->toArray()):
                $champ?->getChildren()->toArray()
            );
            $this->addSpec($form, $discipline ?
                $itr($discipline->getChildren()->toArray()):
                $discipline?->getChildren()->toArray()
            );
            if ($specialite) {
                $form->get('champDisc')->setData($champ);
                $form->get('discipline')->setData($discipline);
            }
            if($form->has('disciFond')) {
                /* @var $codewey Dewey */
                $codewey = $data->getCodeweys()->first()?:null;
                $division = $codewey?->getParent();
                $discip = $division?->getParent();

                $this->addDivi($form, $codewey ?
                    $itr($discip->getChildren()->toArray()):
                    $discip?->getChildren()->toArray()
                );
                $this->addCode($form, $codewey ?
                    $itr($division->getChildren()->toArray()):
                    $division?->getChildren()->toArray()
                );
                if ($codewey) {
                    $form->get('disciFond')->setData($discip);
                    $form->get('division')->setData($division);
                }
            }
        });
    }

    private function redirecTo(Request $req): AdminUrlGenerator
    {
        $folderId = $req->get('folderId');

        return $folderId ?
            $this->generator->setController(DossierCrudController::class)->setAction(Action::DETAIL)->setEntityId($folderId):
            $this->generator->setController(self::class)->setAction(Action::INDEX);
    }
}
