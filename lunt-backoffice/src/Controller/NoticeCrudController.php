<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use App\{Event\AfterNoticeStateSetEvent, Security\Voter\NoticeActionVoter};
use App\Entity\{Dewey, Discipline, Etablissement, Notice, NoticEtat, Univerique, User};
use App\Field\{DurationField, EntityField, FileField};
use App\Form\Type\{AuteurAutoField, NoticeAutoField, TagAutoField, TreeChoiceType};
use App\Repository\{DossierRepository, NoticeRepository};
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\{FileUploadType, Model\FileUploadState};
use EasyCorp\Bundle\EasyAdminBundle\{Context\AdminContext,
    Event\AfterEntityPersistedEvent,
    Factory\FormFactory,
    Filter\ChoiceFilter,
    Filter\DateTimeFilter,
    Provider\AdminContextProvider,
    Router\AdminUrlGenerator};
use EasyCorp\Bundle\EasyAdminBundle\Collection\{ActionCollection, FieldCollection, FilterCollection};
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Asset, Assets, Crud, Filters, KeyValueStore};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\{ActionDto, EntityDto, SearchDto};
use EasyCorp\Bundle\EasyAdminBundle\Field as Field;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\{FormBuilderInterface, FormEvent, FormEvents, FormInterface};
use Symfony\Component\HttpFoundation\{File\Exception\FileException, File\UploadedFile, RedirectResponse, Response};
use Symfony\Component\Intl\Languages;
use Symfony\Component\Validator\Constraints\{File, Image, Url};
use Symfony\Component\Uid\Uuid;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use function Symfony\Component\String\u;
use function Symfony\Component\Translation\t;

class NoticeCrudController extends AbstractCrudController
{
    const SAVE_AND_FORWARD = 'saveAndForward';
    const FORWARD_ACTION = 'forwardNotice';

    public function __construct(
        private readonly FormFactory $factory,
        private readonly DossierRepository $rep,
        private readonly NoticeRepository $repository,
        private readonly AdminUrlGenerator $generator,
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    public static function getEntityFqcn(): string
    {
        return Notice::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $crud = $crud->setAutofocusSearch()->setSearchFields(['titre','description'])->setEntityLabelInPlural('Notices')
            ->setPageTitle(Action::NEW, fn () => 'Créer une <b>Notice</b>')
            ->setPageTitle(Action::EDIT, fn (Notice $n) => 'Modifier une <b>Notice</b>')
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (Notice $n) => $n->getTitre());
        if($this->isGranted('ROLE_VALI_NOTI')) $crud->renderSidebarMinimized()->overrideTemplates([
            'crud/detail'=>'admin/actions/notice_show.html.twig',
            'crud/new'=>'admin/actions/notice_new.html.twig'
        ]);
        return $crud;
    }

    public function configureActions(Actions $actions): Actions
    {
        //Action dupliquer
        $duplicate = Action::new('dupliquer',null,'fa fa-copy')
            ->linkToCrudAction('duplicateNotice')
            ->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Dupliquer cette notice']);
        
        //Action Soumettre
        $forward = Action::new('soumettre',null,'fa fa-send')->linkToCrudAction(self::FORWARD_ACTION)->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Soumettre cette notice']);
        
        //Action Valider 
        $approve = Action::new('valider',null,'fa fa-check')
            ->linkToCrudAction('approveNotice')
            ->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Valider la notice pour publication']);
        
        //Action ???
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
            ->update(Crud::PAGE_DETAIL, Action::EDIT, static fn(Action $a) => $a->setIcon('fa fa-pencil')->displayIf(static fn (Notice $n) => $fwdoc($n, NoticeActionVoter::EDIT) && $n->getEtat() !== NoticEtat::Approved ))
            ->update(Crud::PAGE_DETAIL, Action::DELETE, static fn(Action $a) => $a->addCssClass('btn btn-outline-danger')->displayIf(static fn (Notice $n) => $fwdoc($n,NoticeActionVoter::DROP)))
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action) => $action->setLabel('Créer une <b>Notice</b>'))
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
        return $filters->add(ChoiceFilter::new('etat')
            ->setChoices(NoticEtat::getLabels())->renderExpanded())
            ->add(DateTimeFilter::new('creeLe', 'Créée le'));
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
        yield Field\TextEditorField::new('description')->setHelp(t('notice.description_help', domain: 'EasyAdminBundle'))->hideOnIndex();
        //Etablissement porteur 
        yield EntityField::new('porteurs', t('notice.porteurs', domain: 'EasyAdminBundle'))
            ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->orderBy('entity.abrege', 'ASC'))
            ->setHelp(t('notice.porteurs_help', domain: 'EasyAdminBundle'))
            ->setSortable(false)
            ->setRequired(true)
            ->hideOnIndex();
        
        //Auteurs
        // Todo : reactivate this one with noticeautofield // yield EntityField::new('auteurs','notice.auteurs')->setHelp(t('notice.auteurs_help', domain: 'EasyAdminBundle'))->setFormType(AuteurAutoField::class)->setSortable(false)->setRequired(true);
        yield EntityField::new('auteurs',t('notice.auteurs', domain: 'EasyAdminBundle'))
            ->setHelp(t('notice.auteurs_help', domain: 'EasyAdminBundle'))
            ->setSortable(false)
            ->setRequired(true)
            ->setQueryBuilder(function (QueryBuilder $queryBuilder) {
                return $queryBuilder->orderBy('entity.prenom', 'ASC');
            });;
        
        
        // Mots clés 
        // Todo : reactivate this one with noticeautofield // yield EntityField::new('tags', 't(notice_.ags')->setFormType(TagAutoField::class)->setHelp(t('notice.tags_help', domain: 'EasyAdminBundle'))->hideOnIndex()->setRequired(true);
        yield EntityField::new('tags', t('notice.tags', domain: 'EasyAdminBundle'))
            ->setHelp(t('notice.tags_help', domain: 'EasyAdminBundle'))
            ->hideOnIndex()
            ->setRequired(true)
            ->setQueryBuilder(function (QueryBuilder $queryBuilder) {
                return $queryBuilder->orderBy('entity.nom', 'ASC');
            });

        yield Field\TextField::new('ressDate', t('notice.date', domain: 'EasyAdminBundle'))->setHelp(t('notice.date_help', domain: 'EasyAdminBundle'))->hideOnIndex();

        yield Field\FormField::addFieldset('Liens de la ressource')->setIcon('fa fa-paperclip');
        yield Field\BooleanField::new('zipFile')->setFormTypeOptions(['mapped' => false])->setLabel('Fichier Zip')->onlyOnForms();
        yield Field\UrlField::new('ressUrl', t('notice.ressurl', domain: 'EasyAdminBundle'))->setHelp(t('notice.ressurl_help', domain: 'EasyAdminBundle'))->setFormTypeOptions(['attr' => ['class' => 'isUrl'],'constraints'=>[new Url()],'required'=>false])->setSortable(false);
        yield FileField::new('ressZip', 'Contenu Zip')->setUploadDir('public/uploads/files')->setHelp(t('notice.ressurl_help', domain: 'EasyAdminBundle'))->onlyOnForms()
            ->setUploadedFileNamePattern('[timestamp]-[randomhash].[extension]')->setBasePath('/uploads/files')->setFormTypeOptions(['attr' => ['class' => 'isZip'],'required'=>false])
            ->setFileConstraints([new File(maxSize: '64M', mimeTypes: ["application/zip", "application/x-zip-compressed", "multipart/x-zip"])]);
        
        // Ressource(s) liée(s) dropdown
        // Todo : reactivate this one with noticeautofield // yield EntityField::new('ressources','notice.notices')->setFormType(NoticeAutoField::class)->setHelp(t('notice.notices_help', domain: 'EasyAdminBundle'))->hideOnIndex();
        yield EntityField::new('ressources',t('notice.notices', domain: 'EasyAdminBundle'))
            ->setHelp(t('notice.notices_help', domain: 'EasyAdminBundle'))
            ->hideOnIndex()
            ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->orderBy('entity.titre', 'ASC'));
        
        yield Field\ChoiceField::new('etat')->setChoices(NoticEtat::getLabels())->renderAsBadges(NoticEtat::getColors())->hideOnForm();
        yield Field\AssociationField::new('validateur',t('notice.validateur', domain: 'EasyAdminBundle'))->onlyOnDetail();

        yield Field\FormField::addFieldset('Droits attachés à la ressource')->setIcon('fa fa-gavel');
        yield Field\AssociationField::new('droit',t('notice.droit', domain: 'EasyAdminBundle'))
            ->setHelp(t('notice.droit_help', domain: 'EasyAdminBundle'))
            ->setSortable(false)
            ->hideOnIndex()
            ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->orderBy('entity.valeur', 'ASC'));

        yield Field\BooleanField::new('ressPayant',t('notice.resspayant', domain: 'EasyAdminBundle'))->setHelp(t('notice.resspayant_help', domain: 'EasyAdminBundle'))->renderAsSwitch(false)->hideOnIndex()->setColumns(6);
        yield Field\BooleanField::new('proprIntel',t('notice.proprintel', domain: 'EasyAdminBundle'))->setHelp(t('notice.proprintel_help', domain: 'EasyAdminBundle'))->renderAsSwitch(false)->hideOnIndex()->setColumns(6);


        yield Field\FormField::addColumn(6);
        yield Field\FormField::addFieldset('Indications pédagogiques')->setIcon('fa fa-th-list');
        
        yield Field\ChoiceField::new('ressLang', t('notice.resslang', domain: 'EasyAdminBundle'))
            ->setChoices($langList)
            ->setHelp(t('notice.resslang_help', domain: 'EasyAdminBundle'))
            ->allowMultipleChoices()
            ->renderAsBadges()
            ->hideOnIndex()
            ->setRequired(true)
            ->setColumns(6);
        
            yield Field\TextField::new('dureAppr', t('notice.dureappr', domain: 'EasyAdminBundle'))
            ->setColumns(6)
            ->setHelp(t('notice.dureappr_help', domain: 'EasyAdminBundle'))
            ->hideOnIndex();

        // Type pédagogique
        yield EntityField::new('pedTypes', t('notice.pedtypes', domain: 'EasyAdminBundle'))
        ->setHelp(t('notice.pedtypes_help', domain: 'EasyAdminBundle'))
        ->hideOnIndex()->setSortable(false)
        ->setRequired(true)
        ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->orderBy('entity.nom', 'ASC'));
        
        yield Field\ArrayField::new('propUser', t('notice.propuser', domain: 'EasyAdminBundle'))->setHelp(t('notice.propuser_help', domain: 'EasyAdminBundle'))->hideOnIndex();
        yield EntityField::new('docTypes', t('notice.doctypes', domain: 'EasyAdminBundle'))->setHelp(t('notice.doctypes_help', domain: 'EasyAdminBundle'))->setFormTypeOptions(['multiple' => true, 'expanded' => true])->setColumns(6)->hideOnIndex()->setRequired(true);
        yield EntityField::new('niveaux', t('notice.niveaux', domain: 'EasyAdminBundle'))->setFormTypeOptions(['multiple' => true, 'expanded' => true])->setHelp(t('notice.niveaux_help', domain: 'EasyAdminBundle'))->setColumns(6)->hideOnIndex()->setRequired(true);

        yield Field\FormField::addFieldset('Classification thématique')->setIcon('fa fa-book');
        
        yield EntityField::new('champDisc', t('notice.champdisc', domain: 'EasyAdminBundle'))
            ->setFormTypeOptions([
                'class' => Discipline::class, 
                'mapped' => false
            ])
            ->setHelp(t('notice.champdisc_help', domain: 'EasyAdminBundle'))
            ->onlyOnForms()
            ->setQueryBuilder(function (QueryBuilder $qb) use ($user) {
                if ($user->getUntheme() instanceof Univerique)
                    $qb->where('entity IN (:champs)')->setParameter('champs', $user->getUntheme()->getFields());
                else $qb->where('entity.parent IS NULL');

                return $qb->orderBy('entity.nom', 'ASC');
            })
            ->setSortable(false);
        
        yield EntityField::new('discipline')
            ->setFormTypeOptions([
                'class' => Discipline::class, 
                'auto_initialize' => false, 
                'mapped' => false
            ])
            ->setHelp(t('notice.discipline_help', domain: 'EasyAdminBundle'))
            ->setSortable(false)
            ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->orderBy('entity.nom', 'ASC'))
            ->onlyOnForms();
        //Sous discipline 
        yield EntityField::new('specialite', 'Specialité')
            ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->orderBy('entity.nom', 'ASC'))
            ->setFormTypeOptions(['class' => Discipline::class])
            ->setSortable(false);

        yield Field\DateTimeField::new('editeLe', t('notice.editele', domain: 'EasyAdminBundle'))->hideOnForm();

        if ($valdoc) {
            yield Field\FormField::addTab('Validation')->setHelp("Infos techniques complémentaires de validation");
            yield Field\FormField::addColumn(6);
            yield Field\FormField::addFieldset('Liens de la ressource')->setIcon('fa fa-folder-open');
            yield Field\ImageField::new('vignette')->setUploadDir('public/uploads/images')
                ->setUploadedFileNamePattern('[timestamp]-[contenthash].[extension]')->setBasePath('/uploads/images')
                ->setFileConstraints([new Image(['maxWidth' => 620, 'maxHeight' => 390])])->setHelp(t('notice.vignette_help', domain: 'EasyAdminBundle'))->setSortable(false);
            yield Field\IntegerField::new('ressSize',t('notice.resssize', domain: 'EasyAdminBundle'))->setHelp(t('notice.resssize_help', domain: 'EasyAdminBundle'))->setColumns(6)->hideOnIndex();
            yield DurationField::new('dureExec',t('notice.dureexec', domain: 'EasyAdminBundle'))->setHelp(t('notice.dureexec_help', domain: 'EasyAdminBundle'))->setColumns(6)->hideOnIndex();
            yield Field\UrlField::new('formEvalUrl',t('notice.formevalurl', domain: 'EasyAdminBundle'))->setFormTypeOption('default_protocol', 'https')->setHelp(t('notice.formevalurl_help', domain: 'EasyAdminBundle'))->hideOnIndex();
            yield Field\ChoiceField::new('userLang',t('notice.userlang', domain: 'EasyAdminBundle'))->setHelp(t('notice.userlang_help', domain: 'EasyAdminBundle'))->hideOnIndex()
                ->setChoices($langList)->allowMultipleChoices()->renderExpanded(false)->renderAsBadges();
            yield Field\TextEditorField::new('objectif',t('notice.objectif', domain: 'EasyAdminBundle'))->setHelp(t('notice.objectif_help', domain: 'EasyAdminBundle'))->hideOnIndex()->formatValue(function ($value, $entity) { return $value;});
            yield Field\TextField::new('champExt1',"Champ d'extension 1")->hideOnIndex(); yield Field\TextField::new('champExt2',"Champ d'extension 2")->hideOnIndex();
            yield Field\TextField::new('champExt3',"Champ d'extension 3")->hideOnIndex(); yield Field\TextField::new('champExt4',"Champ d'extension 4")->hideOnIndex();
            yield Field\TextField::new('champExt5',"Champ d'extension 5")->hideOnIndex();
            yield Field\BooleanField::new('exportOAI', t('notice.exportoai', domain: 'EasyAdminBundle'))->setHelp(t('notice.exportoai_help', domain: 'EasyAdminBundle'))->renderAsSwitch(false)->hideOnIndex();

            yield Field\FormField::addColumn(6);
            yield Field\FormField::addFieldset('Classification thématique')->setIcon('fa fa-book');
            yield EntityField::new('disciFond',t('notice.discifond', domain: 'EasyAdminBundle'))->setHelp(t('notice.discifond_help', domain: 'EasyAdminBundle'))->onlyOnForms()
                ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->where('entity.parent is null'))->setFormTypeOptions(['class' => Dewey::class,'mapped' => false,'required' => false])->setSortable(false);
            yield EntityField::new('division')->setFormTypeOptions(['class' => Dewey::class,'auto_initialize' => false,'mapped' => false,'required' => false])->onlyOnForms();
            yield EntityField::new('codewey','Code Dewey')->setFormTypeOptions(['class' => Dewey::class])->setRequired(true);
            yield Field\TextField::new('label',t('notice.label', domain: 'EasyAdminBundle'))->setHelp(t('notice.label_help', domain: 'EasyAdminBundle'))->onlyOnDetail();
            yield Field\DateTimeField::new('creeLe',t('notice.creele', domain: 'EasyAdminBundle'))->onlyOnDetail();
            yield EntityField::new('repertoire',t('notice.repertoire', domain: 'EasyAdminBundle'))->setFormType(TreeChoiceType::class)->setHelp(t('notice.repertoire_help', domain: 'EasyAdminBundle'));
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
        if ($folderId = $ctx->getRequest()->get('folderId', 1)) {
            $folder = $this->rep->findOneForAll($folderId);
            $notice->setRepertoire($folder);
        }
        return $notice->setCreateur($this->getUser());
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        /** @var User $user */$user = $this->getUser();
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)->select('entity,r,e,a,n,p,dd,pp,k,l,s')
            ->leftJoin('entity.codewey','e')->leftJoin('entity.ressources','r')
            ->join('entity.tags','k')->join('entity.porteurs','p')
            ->join('entity.auteurs','a')->join('entity.niveaux','n')
            ->join('entity.docTypes','dd')->join('entity.pedTypes','pp')
            ->join('entity.droit','l')->join('entity.specialite','s');

        if($this->isGranted('ROLE_READ_NOTI') && $user->getSchool() instanceof Etablissement)
            $qb->andWhere(':school MEMBER OF entity.porteurs')->setParameter('school', $user->getSchool());
        elseif($this->isGranted('ROLE_VALI_NOTI') && $user->getUntheme() instanceof Univerique)
            $qb->join('s.parent','d')->addSelect('d')
                ->andWhere('d.parent in (:champs)')->setParameter('champs',$user->getUntheme()->getFields());
        return $qb->andWhere('entity.deleted = 0')->orderBy('entity.creeLe', 'DESC');
    }

    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $builder = $this->factory->createNewFormBuilder($entityDto, $formOptions, $context);
        return $this->addFormEvent($builder);
    }

    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $ctx = $this->getContext();
        $currentEntity = $ctx->getEntity()->getInstance(); 
        if (!$this->isGranted('NOTICE_EDIT', $currentEntity)) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas autorisé à modifier cette notice.');
        }

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
            $extractNew = function (UploadedFile $file, string $uploadDir, string $fileName) {
                $target = $uploadDir .DIRECTORY_SEPARATOR. pathinfo($fileName, PATHINFO_FILENAME);
                $zip = new \ZipArchive();
                try {
                    if ($zip->open($file->getRealPath()) === true)
                        $zip->extractTo($target);
                    $zip->close();
                }catch (\Exception $error){
                    throw new FileException(sprintf('Could not extract the file "%s" to "%s" (%s).', $file->getRealPath(), $target, $error->getMessage()));
                }
            };

            foreach ($state->getUploadedFiles() as $index => $file) {
                $fileName = u($filePaths[$index])->replace($uploadDir, '')->toString();
                if ("zip" === $file->guessExtension()) $extractNew($file, $uploadDir, $fileName);
                else $uploadNew($file, $uploadDir, $fileName);
            }
        }
    }

    public function duplicateNotice(): Response
    {
        $ctx = $this->getContext();
        $currentEntity = $ctx->getEntity()->getInstance(); 
        if (!$this->isGranted('NOTICE_VIEW', $currentEntity)) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas autorisé à valider cette notice.');
        }

        /** @var Notice $notice */
        $notice = $ctx->getEntity()->getInstance();
        $dupNot = (clone $notice)->setCreeLe(new \DateTimeImmutable())->setUuid(Uuid::v4());
        $dupNot->setEditeLe(new \DateTimeImmutable());
        $dupNot->setTitre("COPIE - ".$dupNot->getTitre());
        $this->repository->add($dupNot->setCreateur($this->getUser()));
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
        $currentEntity = $ctx->getEntity()->getInstance(); 
        if (!$this->isGranted('NOTICE_VIEW', $currentEntity)) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas autorisé à valider cette notice.');
        }

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Working
        return $this->changEtatNotice(['Soumettre', NoticEtat::Forward->getLabel(), 'Soummision', true], $notice->setEtat(NoticEtat::Forward),$ctx->getRequest()->get('folderId'));
    }

    public function approveNotice(): Response
    {
        /** @var User $user */ $user = $this->getUser();
        $ctx = $this->getContext();
        $currentEntity = $ctx->getEntity()->getInstance(); 
        if (!$this->isGranted('NOTICE_VALI', $currentEntity)) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas autorisé à valider cette notice.');
        }

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Forward
        $notice->setValidateur($user);
        return $this->changEtatNotice(['Valider', NoticEtat::Approved->getLabel(), 'Validation', true], $notice->setEtat(NoticEtat::Approved),$ctx->getRequest()->get('folderId'));
    }

    public function rejectNotice(): Response
    {
        $ctx = $this->getContext();
        $currentEntity = $ctx->getEntity()->getInstance(); 
        if (!$this->isGranted('NOTICE_VALI', $currentEntity)) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas autorisé à valider cette notice.');
        }

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Forward
        return $this->changEtatNotice(['Rejeter', 'Rejetée', 'Rejet', true], $notice->setEtat(NoticEtat::Working),$ctx->getRequest()->get('folderId'));
    }

    public function publishNotice(): Response
    {
        $ctx = $this->getContext();
        $currentEntity = $ctx->getEntity()->getInstance(); 
        if (!$this->isGranted('NOTICE_VALI', $currentEntity)) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas autorisé à pbulier cette notice.');
        }

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Approved
        return $this->changEtatNotice(['Dépublier', 'Dépubliée', 'Dépublication', false], $notice->setEtat(NoticEtat::Forward),$ctx->getRequest()->get('folderId'));
    }

    public function allowedNotice(): Response
    {
        $ctx = $this->getContext();
        $currentEntity = $ctx->getEntity()->getInstance(); 
        if (!$this->isGranted('NOTICE_VALI', $currentEntity)) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas autorisé à valider cette notice.');
        }

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Approved
        return $this->changEtatNotice(['Autoriser', 'Autorisée', 'Autorisation', false], $notice->setEtat(NoticEtat::Working)->setEditDemande(false),$ctx->getRequest()->get('folderId'));
    }

    public function adjustNotice(): Response
    {
        $ctx = $this->getContext();
        $currentEntity = $ctx->getEntity()->getInstance(); 
        if (!$this->isGranted('NOTICE_VALI', $currentEntity)) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas autorisé à valider cette notice.');
        }

        /** @var Notice|null $notice */
        $notice = $ctx->getEntity()->getInstance(); //Approved
        return $this->changEtatNotice(['Rectifier', 'Signalée', 'Rectification', true], $notice->setEditDemande(true),$ctx->getRequest()->get('folderId'));
    }

    public function labelNotice(): Response
    {
        $ctx = $this->getContext();
        $currentEntity = $ctx->getEntity()->getInstance(); 
        if (!$this->isGranted('NOTICE_VALI', $currentEntity)) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas autorisé à valider cette notice.');
        }
        $label = $ctx->getRequest()->get('label');

        /** @var Notice $notice */
        $notice = $ctx->getEntity()->getInstance();
        return $this->changEtatNotice(['Catégoriser', 'Labellisée', 'Catégorisation', false], $notice->setLabel($label),$ctx->getRequest()->get('folderId'));
    }

    private function changEtatNotice(array $transition, Notice $notice, ?int $folderId = null): Response
    {
        $ctx = $this->getContext();
        $currentEntity = $ctx->getEntity()->getInstance(); 
        if (!$this->isGranted('NOTICE_VIEW', $currentEntity)) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas autorisé à valider cette notice.');
        }

        $url = $folderId ?
            $this->generator->setController(DossierCrudController::class)->setAction(Action::DETAIL)->setEntityId($folderId):
            $this->generator->setController(self::class)->setAction(Action::INDEX);

        $this->repository->add($notice);
        $this->dispatcher->dispatch(new AfterNoticeStateSetEvent($notice, $transition));
        $this->addFlash('success', sprintf("La notice est bien %s avec succès !",$transition[1]));

        return $this->redirect($url->removeReferrer()->generateUrl());
    }

    private function addDisc(FormInterface $form, ?Discipline $child): void
    {
        $builder = $form->getConfig()->getFormFactory()->createNamedBuilder('discipline', EntityType::class, null, [
            'class' => Discipline::class, 'mapped' => false, 'auto_initialize' => false, 'required' => false,
            'label' => t('notice.discipline', domain: 'EasyAdminBundle'), 'choices' => $child ? $child->getChildren() : [], 'help' => t('notice.discipline_help', domain: 'EasyAdminBundle'),
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
            'class' => Dewey::class, 'mapped' => false, 'auto_initialize' => false, 'required' => false,
            'label' => t('notice.division', domain: 'EasyAdminBundle'), 'choices' => $child ? $child->getChildren() : [], 'help' => t('notice.division_help', domain: 'EasyAdminBundle'),
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
            'label' => t('notice.specialite', domain: 'EasyAdminBundle'), 'class' => Discipline::class,
            'choices' => $child ? $child->getChildren() : [], 'help' => t('notice.specialite_help', domain: 'EasyAdminBundle'),
            'placeholder' => $child ? 'Sélectionnez la spécialité' : 'Sélectionnez la discipline',
        ]);
    }
    private function addCode(FormInterface $form, ?Dewey $child): void {
        $form->add('codewey', EntityType::class, [
            'label' => t('notice.codewey', domain: 'EasyAdminBundle'), 'class' => Dewey::class, 'required' => false,
            'choices' => $child ? $child->getChildren() : [], 'help' => t('notice.codewey_help', domain: 'EasyAdminBundle'),
            'placeholder' => $child ? 'Sélectionnez le code dewey' : 'Sélectionnez la division',
        ]);
    }

    private function addFormEvent(FormBuilderInterface $builder): FormBuilderInterface
    {
        $builder->get('champDisc')->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $this->addDisc($form->getParent(), $form->getData());
        });
        if ($builder->has('disciFond')) $builder->get('disciFond')->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
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

    public function detail(AdminContext $context): KeyValueStore
    {
        $currentEntity = $context->getEntity()->getInstance(); 
        if (!$this->isGranted('NOTICE_VIEW', $currentEntity)) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas autorisé à voir les détails de cette notice.');
        }
        
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

    /**
     * @param EntityManagerInterface $entityManager
     * @var Notice $entityInstance
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if($zipDir = $entityInstance->getRessZip())
            $entityInstance->setRessUrl(pathinfo($zipDir, PATHINFO_FILENAME));
        parent::persistEntity($entityManager, $entityInstance);
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
}
