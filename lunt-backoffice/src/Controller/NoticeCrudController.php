<?php

namespace App\Controller;

use App\Entity\{Notice, NoticEtat, User};
use App\Event\{AfterNoticeAdjustingEvent, AfterNoticeApprovingEvent, AfterNoticeRejectingEvent, AfterNoticeStateSetEvent, AfterNoticeSubmissionEvent};
use App\Field\{DurationField, EntityField, FileField};
use App\Form\DeweyGroupType;
use App\Form\DisciplineGroupType;
use App\Form\Type\{AuteurAutoField, NoticeAutoField, TagAutoField, TreeChoiceType};
use App\Repository\{DossierRepository, NoticeRepository};
use App\Security\Voter\NoticeActionVoter;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\{EntityManagerInterface, NonUniqueResultException, QueryBuilder};
use EasyCorp\Bundle\EasyAdminBundle\{Context\AdminContext, Event\AfterEntityPersistedEvent, Factory\FormFactory, Provider\AdminContextProvider, Router\AdminUrlGenerator};
use EasyCorp\Bundle\EasyAdminBundle\Collection\{ActionCollection, FieldCollection, FilterCollection};
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Asset, Assets, Crud, Filters, KeyValueStore};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\{ActionDto, EntityDto, SearchDto};
use EasyCorp\Bundle\EasyAdminBundle\Field\{ArrayField, AssociationField, BooleanField, ChoiceField, CollectionField, DateTimeField, FormField, IdField, ImageField, NumberField, TextareaField, TextEditorField, TextField, UrlField};
use EasyCorp\Bundle\EasyAdminBundle\Filter\{ChoiceFilter, DateTimeFilter, EntityFilter, TextFilter};
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\{FileUploadType, Model\FileUploadState};
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\{Intl\Languages, Routing\Generator\UrlGeneratorInterface, Uid\Uuid};
use Symfony\Component\Form\{FormBuilderInterface, FormInterface};
use Symfony\Component\HttpFoundation\{RedirectResponse, Request, Response};
use Symfony\Component\Validator\Constraints\{File, Image};
use ZipArchive;
use function Symfony\Component\{String\u, Translation\t};

/**
 * Contrôleur CRUD pour la gestion des entités Notice dans EasyAdmin.
 *
 * Gère la configuration des champs, actions, filtres, assets et la logique métier
 * associée à la création, modification, duplication, soumission, validation, rejet,
 * publication, autorisation, rectification, labellisation et déplacement des notices.
 *
 * Fourni également des méthodes utilitaires pour le traitement des fichiers uploadés
 * et l'affichage des badges de collections d'entités.
 *
 * @package App\Controller
 */
class NoticeCrudController extends AbstractCrudController
{
  public const SAVE_AND_FORWARD = 'saveAndForward';
  public const FORWARD_ACTION = 'forwardNotice';

  public function __construct(
    private readonly FormFactory              $factory,
    private readonly DossierRepository        $dossierRepository,
    private readonly NoticeRepository         $noticeRepository,
    private readonly AdminUrlGenerator        $generator,
    private readonly UrlGeneratorInterface    $router,
    private readonly EventDispatcherInterface $dispatcher,
  ) {}

  /**
   * Retourne le FQCN (nom de classe complet) de l'entité Notice.
   *
   * @return string
   */
  public static function getEntityFqcn(): string
  {
    return Notice::class;
  }

  /**
   * Configure le CRUD pour l'entité Notice.
   *
   * @param Crud $crud Instance de configuration CRUD
   * @return Crud Configuration CRUD modifiée
   */
  public function configureCrud(Crud $crud): Crud
  {
    // Configuration générale du CRUD
    $crud
      ->setAutofocusSearch()
      ->setSearchFields(['titre', 'description'])
      ->setDefaultSort(['creeLe' => 'DESC'])
      ->setEntityLabelInPlural('Notices')
      ->setEntityLabelInSingular('notice')
      ->setFormOptions([
        'attr' => [
          'data-controller' => 'notice-setting',
          'data-notice-setting-target' => 'form',
        ],
      ])
      // Titres des pages
      ->setPageTitle(Action::NEW, fn() => 'Créer une notice')
      ->setPageTitle(Action::EDIT, fn() => 'Modifier une notice')
      ->setPageTitle(Crud::PAGE_DETAIL, static fn(Notice $notice) => $notice->getTitre());

    // Personnalisation de l'affichage si l'utilisateur est validateur
    if ($this->isGranted('ROLE_VALI_NOTI')) {
      $crud
        ->renderSidebarMinimized()
        ->overrideTemplates([
          'crud/detail' => 'admin/actions/notice_show.html.twig',
          'crud/new' => 'admin/actions/notice_new.html.twig',
        ]);
    }

    return $crud;
  }

  /**
   * Configure les actions personnalisées et standards pour le CRUD des notices.
   *
   * Définit les actions disponibles selon la page, leur affichage conditionnel selon l'état
   * de la notice et les permissions de l'utilisateur, ainsi que la personnalisation de leur apparence.
   *
   * @param Actions $actions Collection d'actions à configurer
   * @return Actions Collection d'actions configurée
   */
  public function configureActions(Actions $actions): Actions
  {
    // Définition des actions personnalisées
    $duplicate = Action::new('dupliquer', null, 'fa fa-copy')
      ->linkToCrudAction('duplicateNotice')
      ->addCssClass('btn btn-outline-light')
      ->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Dupliquer cette notice']);
    $forward = Action::new('soumettre', null, 'fa fa-send')
      ->linkToCrudAction(self::FORWARD_ACTION)->addCssClass('btn btn-outline-success')
      ->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Soumettre cette notice']);
    $approve = Action::new('valider', null, 'fa fa-check')
      ->linkToCrudAction('approveNotice')
      ->addCssClass('btn btn-outline-success')
      ->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Valider la notice pour publication']);
    $reject = Action::new('rejeter', null, 'fa fa-close')
      ->linkToCrudAction('rejectNotice')
      ->addCssClass('btn btn-outline-danger')
      ->setHtmlAttributes(['data-bs-toggle' => 'modal', 'data-bs-target' => '#modal-reject', 'data-toggle' => 'tooltip', 'title' => 'Rejeter la notice pour correction']);
    $publish = Action::new('dépublier', null, 'fa fa-step-backward')
      ->linkToCrudAction('publishNotice')->addCssClass('btn btn-outline-danger')
      ->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Dépublier cette notice publiée']);
    $allowed = Action::new('autoriser', null, 'fa fa-fast-backward')
      ->linkToCrudAction('allowedNotice')->addCssClass('btn btn-outline-info')
      ->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Autoriser la notice pour modification']);
    $adjust = Action::new('rectifier', null, 'fa fa-share-square')
      ->linkToCrudAction('adjustNotice')->addCssClass('btn btn-outline-info')
      ->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Demander la modification de cette notice']);
    $category = Action::new('catégoriser', null, 'fa fa-tag')
      ->linkToCrudAction('labelNotice')->addCssClass('btn btn-outline-warning confirm-action')
      ->setHtmlAttributes(['data-bs-toggle' => 'modal', 'data-bs-target' => '#modal-confirm',]);

    // Fonction utilitaire pour vérifier les permissions sur une notice
    $fwdoc = fn(Notice $notice, string $action = NoticeActionVoter::VALI) => $this->isGranted($action, $notice);

    $actions
      ->add(Crud::PAGE_DETAIL, $duplicate->displayIf(static fn(Notice $notice) => $fwdoc($notice, NoticeActionVoter::VIEW)))
      ->add(Crud::PAGE_DETAIL, $forward->displayIf(static fn(Notice $notice) => $notice->getEtat() === NoticEtat::Working && $fwdoc($notice, NoticeActionVoter::EDIT)))
      ->add(Crud::PAGE_DETAIL, $reject->displayIf(static fn(Notice $notice) => $notice->getEtat() === NoticEtat::Forward && $fwdoc($notice)))
      ->add(Crud::PAGE_DETAIL, $approve->displayIf(static fn(Notice $notice) => $notice->getEtat() === NoticEtat::Forward && $fwdoc($notice)))
      ->add(Crud::PAGE_DETAIL, $publish->displayIf(static fn(Notice $notice) => $notice->getEtat() === NoticEtat::Approved && $fwdoc($notice)))
      ->add(Crud::PAGE_DETAIL, $category->displayIf(static fn(Notice $notice) => $notice->getEtat() === NoticEtat::Approved && $fwdoc($notice)))
      ->add(Crud::PAGE_DETAIL, $allowed->displayIf(static fn(Notice $notice) => $fwdoc($notice) && $notice->isEditDemande()))
      ->add(Crud::PAGE_DETAIL, $adjust->displayIf(static fn(Notice $notice) => $notice->getEtat() === NoticEtat::Approved && $fwdoc($notice, NoticeActionVoter::DEFA) && !$notice->isEditDemande()))
      // Ajout des actions standards
      ->add(Crud::PAGE_INDEX, Action::DETAIL)
      ->add(Crud::PAGE_NEW, Action::INDEX)
      // Personnalisation des actions standards
      ->update(Crud::PAGE_INDEX, Action::DETAIL, fn(Action $action) => $action->setCssClass('btn btn-outline-secondary'))
      ->update(Crud::PAGE_DETAIL, Action::EDIT, static fn(Action $action) => $action->setIcon('fa fa-pencil')->displayIf(static fn(Notice $notice) => $fwdoc($notice, NoticeActionVoter::EDIT)))
      ->update(Crud::PAGE_DETAIL, Action::DELETE, static fn(Action $action) => $action->addCssClass('btn btn-outline-danger')->displayIf(static fn(Notice $notice) => $fwdoc($notice, NoticeActionVoter::DROP)))
      ->update(Crud::PAGE_INDEX, Action::NEW, fn(Action $action) => $action->setLabel('Créer une <b>notice</b>'))
      ->update(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER, fn(Action $action) => $action->setLabel('Créer et ajouter une <b>nouvelle</b>'))
      // Suppression des actions non désirées
      ->remove(Crud::PAGE_INDEX, Action::EDIT)
      ->remove(Crud::PAGE_INDEX, Action::DELETE)
      ->remove(Crud::PAGE_DETAIL, Action::INDEX)
      ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
      ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
      // Définition des permissions pour chaque action
      ->setPermission(Action::INDEX, 'ROLE_READ_NOTI')
      ->setPermission(Action::NEW, 'ROLE_CREA_NOTI')
      ->setPermission(Action::DETAIL, 'ROLE_READ_NOTI')
      ->setPermission(Action::EDIT, 'ROLE_EDIT_NOTI')
      ->setPermission(Action::DELETE, 'ROLE_DROP_NOTI');

    return $actions;
  }

  /**
   * Configure les filtres disponibles dans le CRUD pour l'entité Notice.
   *
   * Permet de filtrer les notices par état, titre, auteurs, date de création et date de modification.
   *
   * @param Filters $filters Instance de configuration des filtres
   * @return Filters Collection de filtres configurée
   */
  public function configureFilters(Filters $filters): Filters
  {
    return $filters
      ->add(
        ChoiceFilter::new('etat')
          ->setChoices(NoticEtat::getLabels())
          ->renderExpanded()
      )
      ->add(TextFilter::new('titre'))
      ->add(EntityFilter::new('auteurs'))
      ->add(DateTimeFilter::new('creeLe', 'Créée le'))
      ->add(DateTimeFilter::new('editeLe', 'Date de modification'));
  }

  /**
   * Configure les champs affichés dans le CRUD pour l'entité Notice.
   *
   * Les champs sont organisés par section (description, liens, droits, pédagogie, classification, dates, validation)
   * et adaptés selon la page et le rôle de l'utilisateur (validateur ou non).
   *
   * @param string $pageName Nom de la page CRUD (index, new, edit, detail)
   * @return iterable Liste des champs configurés
   */
  public function configureFields(string $pageName): iterable
  {
    /** @var User $user */
    $user = $this->getUser();
    $isValidator = $this->isGranted('ROLE_VALI_NOTI');
    $langList = array_merge(array_flip(Languages::getAlpha3Names('fr')), ['français' => 'fre']);

    // Section Soumission (si validateur)
    if ($isValidator) {
      yield FormField::addTab('Soumission')->setHelp("Infos renseignées par la contribution des établissements");
    }
    yield FormField::addColumn(6);

    // Section Description générale
    yield FormField::addFieldset('Description générale')
      ->setIcon('fa fa-pencil');
    yield IdField::new('id')
      ->onlyOnDetail();
    yield TextField::new('titre')
      ->setHelp(t('notice.titre_help', domain: 'EasyAdminBundle'));
    yield TextEditorField::new('description')
      ->setHelp(t('notice.description_help', domain: 'EasyAdminBundle'))
      ->setTemplatePath('admin/fields/text_editor.html.twig')
      ->hideOnIndex();
    yield EntityField::new('porteurs', t('notice.porteurs', domain: 'EasyAdminBundle'))
      ->setHelp(t('notice.porteurs_help', domain: 'EasyAdminBundle'))
      ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->orderBy('entity.code', 'ASC'))
      ->setSortable(false)->hideOnIndex()
      ->formatValue(fn($value, $entity) => $this->renderEntityCollectionBadges($value));
    yield EntityField::new('auteurs', t('notice.auteurs', domain: 'EasyAdminBundle'))
      ->setFormType(AuteurAutoField::class)
      ->setHelp(t('notice.auteurs_help', domain: 'EasyAdminBundle'))
      ->setSortable(false)
      ->formatValue(fn($value, $entity) => $this->renderEntityCollectionBadges($value));
    yield TextField::new('allSpecialitesString', 'Spécialités')
      ->onlyOnIndex()
      ->renderAsHtml()
      ->setTemplatePath('admin/fields/text.html.twig');
    yield EntityField::new('tags', t('notice.tags', domain: 'EasyAdminBundle'))
      ->hideOnIndex()
      ->setHelp(t('notice.tags_help', domain: 'EasyAdminBundle'))
      ->setFormType(TagAutoField::class)
      ->setFormTypeOption('attr', [
        'data-tag-autocreate-url-value' => $this->router->generate('app_keywork_new'),
        'data-controller' => 'tag-autocreate',
      ])
      ->formatValue(fn($value, $entity) => $this->renderEntityCollectionBadges($value));
    yield ChoiceField::new('ressDate', t('notice.date', domain: 'EasyAdminBundle'))
      ->setChoices(array_combine($years = range((int)date('Y'), (int)date('Y') - 100), $years))
      ->hideOnIndex();

    // Section Liens de la ressource
    yield FormField::addFieldset('Liens de la ressource')->setIcon('fa fa-paperclip');
    if (in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT], true)) {
      // Champs spécifiques à la création/édition
      yield BooleanField::new('zipFile', 'Fichier Zip')
        ->setFormTypeOptions(['mapped' => false, 'attr' => ['data-notice-setting-target' => 'ressToggle']])
        ->onlyOnForms();
      yield TextField::new('ressUrl', t('notice.ressurl', domain: 'EasyAdminBundle'))
        ->setHelp(t('notice.ressurl_help', domain: 'EasyAdminBundle'))
        ->setFormTypeOptions(['attr' => ['placeholder' => 'https://...']])
        ->setSortable(false);
      yield FileField::new('ressZip', 'Contenu Zip')
        ->setUploadDir('public/uploads/files')
        ->setHelp(t('notice.ressurl_help', domain: 'EasyAdminBundle'))
        ->setUploadedFileNamePattern('[timestamp]-[randomhash].[extension]')
        ->setBasePath('uploads/files')
        ->setRequired(true)
        ->onlyOnForms()
        ->setFileConstraints([new File(maxSize: '64M', mimeTypes: ["application/zip", "application/x-zip-compressed", "multipart/x-zip"])]);
    } else {
      // Champs spécifiques à la vue détail
      yield UrlField::new('ressUrl', t('notice.ressurl', domain: 'EasyAdminBundle'))
        ->setHelp(t('notice.ressurl_help', domain: 'EasyAdminBundle'));
    }
    yield EntityField::new('ressources', t('notice.notices', domain: 'EasyAdminBundle'))
      ->hideOnIndex()
      ->setHelp(t('notice.notices_help', domain: 'EasyAdminBundle'))
      ->setFormType(NoticeAutoField::class);
    yield ChoiceField::new('etat')
      ->setChoices(NoticEtat::getLabels())
      ->renderAsBadges(NoticEtat::getColors())
      ->hideOnForm();
    yield AssociationField::new('validateur', t('notice.validateur', domain: 'EasyAdminBundle'))
      ->onlyOnDetail();

    // Section Droits attachés à la ressource
    yield FormField::addFieldset('Droits attachés à la ressource')
      ->setIcon('fa fa-gavel');
    yield AssociationField::new('droit', t('notice.droit', domain: 'EasyAdminBundle'))
      ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->orderBy('entity.valeur', 'ASC'))
      ->setHelp(t('notice.droit_help', domain: 'EasyAdminBundle'))
      ->setSortable(false)
      ->hideOnIndex();
    yield BooleanField::new('ressPayant', t('notice.resspayant', domain: 'EasyAdminBundle'))
      ->setHelp(t('notice.resspayant_help', domain: 'EasyAdminBundle'))
      ->renderAsSwitch(false)
      ->hideOnIndex()
      ->setColumns(6);
    yield BooleanField::new('proprIntel', t('notice.proprintel', domain: 'EasyAdminBundle'))
      ->setHelp(t('notice.proprintel_help', domain: 'EasyAdminBundle'))
      ->setFormTypeOptions(['data' => true])
      ->renderAsSwitch(false)
      ->hideOnIndex()
      ->setColumns(6);

    yield FormField::addColumn(6);

    // Section Indications pédagogiques
    yield FormField::addFieldset('Indications pédagogiques')
      ->setIcon('fa fa-th-list');
    yield ChoiceField::new('ressLang', t('notice.resslang', domain: 'EasyAdminBundle'))
      ->setChoices($langList)
      ->allowMultipleChoices()
      ->setHelp(t('notice.resslang_help', domain: 'EasyAdminBundle'))
      ->renderAsBadges()
      ->setColumns(6)
      ->hideOnIndex();
    yield DurationField::new('dureAppr', "Durée d'apprentissage")
      ->setHelp(t('notice.dureappr_help', domain: 'EasyAdminBundle'))
      ->hideOnIndex()
      ->setColumns(6);
    yield EntityField::new('pedTypes', t('notice.pedtypes', domain: 'EasyAdminBundle'))
      ->setHelp(t('notice.pedtypes_help', domain: 'EasyAdminBundle'))
      ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->orderBy('entity.nom', 'ASC'))
      ->setSortable(false)
      ->hideOnIndex()
      ->formatValue(fn($value, $entity) => $this->renderEntityCollectionBadges($value));
    yield ArrayField::new('propUser', t('notice.propuser', domain: 'EasyAdminBundle'))
      ->setHelp(t('notice.propuser_help', domain: 'EasyAdminBundle'))
      ->hideOnIndex();
    yield EntityField::new('docTypes', t('notice.doctypes', domain: 'EasyAdminBundle'))
      ->setHelp(t('notice.doctypes_help', domain: 'EasyAdminBundle'))
      ->setFormTypeOption('multiple', true)
      ->setFormTypeOption('expanded', true)
      ->setColumns(6)
      ->hideOnIndex()
      ->setHelp(t('notice.doctypes_help', domain: 'EasyAdminBundle'))
      ->formatValue(fn($value, $entity) => $this->renderEntityCollectionBadges($value));
    yield EntityField::new('niveaux', t('notice.niveaux', domain: 'EasyAdminBundle'))
      ->setFormTypeOption('multiple', true)
      ->setFormTypeOption('expanded', true)
      ->setHelp(t('notice.niveaux_help', domain: 'EasyAdminBundle'))
      ->setColumns(6)->hideOnIndex()
      ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->orderBy('entity.ordre', 'ASC'))
      ->formatValue(fn($value, $entity) => $this->renderEntityCollectionBadges($value));

    // Section Classification thématique
    yield FormField::addFieldset('Classification thématique')
      ->setIcon('fa fa-book');
    yield TextField::new('allSpecialitesString', 'Spécialités')
      ->onlyOnDetail()
      ->renderAsHtml()
      ->setTemplatePath('admin/fields/text.html.twig');
    yield CollectionField::new('disciplineGroups')
      ->setEntryType(DisciplineGroupType::class)
      ->setFormTypeOption('by_reference', false)
      ->setFormTypeOption('entry_options', ['user' => $user])
      ->allowAdd()
      ->allowDelete()
      ->onlyOnForms()
      ->setLabel(false);

    // Section Dates
    yield DateTimeField::new('creeLe', t('notice.creele', domain: 'EasyAdminBundle'))
      ->onlyOnDetail();
    yield DateTimeField::new('editeLe', t('notice.editele', domain: 'EasyAdminBundle'))
      ->hideOnForm();

    // Section Validation (si validateur)
    if ($isValidator) {
      yield FormField::addTab('Validation')
        ->setHelp("Infos techniques complémentaires de validation");
      yield FormField::addColumn(6);

      // Champs spécifiques à la validation
      yield FormField::addFieldset('Liens de la ressource')
        ->setIcon('fa fa-folder-open');
      yield EntityField::new('repertoire', t('notice.repertoire', domain: 'EasyAdminBundle'))
        ->setHelp(t('notice.repertoire_help', domain: 'EasyAdminBundle'))
        ->setFormType(TreeChoiceType::class)
        ->setQueryBuilder(fn(QueryBuilder $qb) => $qb->join('entity.children', 's')->leftJoin('s.children', 'd')->addSelect('s,d'))
        ->hideOnIndex();
      yield ImageField::new('vignette')
        ->setUploadDir('public/uploads/images')
        ->setHelp(t('notice.vignette_help', domain: 'EasyAdminBundle'))
        ->setBasePath('/uploads/images')
        ->setUploadedFileNamePattern('[timestamp]-[contenthash].[extension]')
        ->setFileConstraints([new Image(['maxWidth' => 620, 'maxHeight' => 390])])
        ->setSortable(false);
      yield TextField::new('AllDeweyString', 'Codes Dewey')
        ->onlyOnIndex()
        ->renderAsHtml()
        ->setTemplatePath('admin/fields/text.html.twig');
      yield NumberField::new('ressSize', t('notice.resssize', domain: 'EasyAdminBundle'))
        ->setHelp(t('notice.resssize_help', domain: 'EasyAdminBundle'))
        ->setColumns(6)
        ->hideOnIndex();
      yield DurationField::new('dureExec', "Durée d'exécution")
        ->setHelp(t('notice.dureexec_help', domain: 'EasyAdminBundle'))
        ->setColumns(6)
        ->hideOnIndex();
      yield UrlField::new('formEvalUrl', t('notice.formevalurl', domain: 'EasyAdminBundle'))
        ->setFormTypeOptions(['default_protocol' => 'https', 'attr' => ['class' => 'isUrl', 'placeholder' => 'https://...']])
        ->setHelp(t('notice.formevalurl_help', domain: 'EasyAdminBundle'))
        ->hideOnIndex();
      yield ChoiceField::new('userLang', t('notice.userlang', domain: 'EasyAdminBundle'))
        ->setHelp(t('notice.userlang_help', domain: 'EasyAdminBundle'))
        ->hideOnIndex()
        ->setChoices($langList)
        ->allowMultipleChoices()
        ->renderAsBadges()
        ->setFormTypeOption('autocomplete', true);
      yield TextEditorField::new('objectif', t('notice.objectif', domain: 'EasyAdminBundle'))
        ->setHelp(t('notice.objectif_help', domain: 'EasyAdminBundle'))
        ->hideOnIndex();
      yield TextField::new('champExt1', "Champ d'extension 1")
        ->hideOnIndex();
      yield TextField::new('champExt2', "Champ d'extension 2")
        ->hideOnIndex();
      yield TextField::new('champExt3', "Champ d'extension 3")
        ->hideOnIndex();
      yield TextField::new('champExt4', "Champ d'extension 4")
        ->hideOnIndex();
      yield TextField::new('champExt5', "Champ d'extension 5")
        ->hideOnIndex();
      yield BooleanField::new('exportOAI', t('notice.exportoai', domain: 'EasyAdminBundle'))
        ->setFormTypeOptions(['data' => true])
        ->setHelp(t('notice.exportoai_help', domain: 'EasyAdminBundle'))
        ->renderAsSwitch(false)
        ->hideOnIndex();

      yield FormField::addColumn(6);

      yield FormField::addFieldset('Classification thématique')
        ->setIcon('fa fa-book');
      yield TextField::new('AllDeweyString', 'Codes Dewey')
        ->onlyOnDetail()
        ->renderAsHtml()
        ->setTemplatePath('admin/fields/text.html.twig');
      yield TextField::new('label', t('notice.label', domain: 'EasyAdminBundle'))
        ->setHelp(t('notice.label_help', domain: 'EasyAdminBundle'))
        ->onlyOnDetail();
      yield CollectionField::new('deweyGroups')
        ->setEntryType(DeweyGroupType::class)
        ->setFormTypeOption('by_reference', false)
        ->allowAdd()
        ->allowDelete()
        ->onlyOnForms()
        ->setLabel(false);
      yield EntityField::new('deweyPersos', 'Dewey personnalisés')
        ->setFormTypeOption('multiple', true)
        ->setFormTypeOption('autocomplete', true)
        ->onlyOnForms();
      yield TextareaField::new('dummy_dewey_perso', '')
        ->setHelp('
          <div id="dewey-perso-add-form" class="mb-3">
            <div class="row g-2 align-items-center">
              <div class="col-12">
                <label class="form-label">Créer un Dewey</label>
              </div>
              <div class="col-auto">
                <input type="text" class="form-control" id="dewey_perso_code" placeholder="Code Dewey" style="width:120px;" />
              </div>
              <div class="col-auto">
                <input type="text" class="form-control" id="dewey_perso_nom" placeholder="Libellé Dewey" style="width:200px;" />
              </div>
              <div class="col-auto">
                <button type="button" class="btn btn-primary" id="add_dewey_perso_btn">Ajouter</button>
              </div>
              <div class="col-auto">
                <span id="dewey_perso_add_msg" style="color:green;"></span>
              </div>
            </div>
          </div>
          ')
        ->onlyOnForms()
        ->setLabel(false)
        ->setFormTypeOption('mapped', false)
        ->setFormTypeOption('required', false)
        ->setFormTypeOption('attr', ['style' => 'display:none']);
      yield BooleanField::new('editDemande', 'Rectifiée ?')
        ->renderAsSwitch(false)
        ->setSortable(false)
        ->onlyOnIndex();
      yield DateTimeField::new('publieLe', t('notice.publiele', domain: 'EasyAdminBundle'))
        ->onlyOnDetail();
    }
  }

  /**
   * Configure les assets (fichiers JS/CSS) utilisés dans le CRUD pour l'entité Notice.
   *
   * Permet d'ajouter des fichiers JavaScript ou CSS spécifiques selon le contexte (ex : uniquement sur les formulaires).
   *
   * @param Assets $assets Instance de configuration des assets
   * @return Assets Collection d'assets configurée
   */
  public function configureAssets(Assets $assets): Assets
  {
    return $assets->addJsFile(
      Asset::new('../assets/form.js')->onlyOnForms()
    );
  }

  /**
   * Crée et initialise une nouvelle entité Notice.
   *
   * Définit le créateur et le répertoire par défaut selon l'utilisateur et le dossier sélectionné.
   *
   * @param string $entityFqcn Le FQCN de l'entité à instancier
   * @return Notice Instance de Notice initialisée
   * @throws ContainerExceptionInterface
   * @throws NonUniqueResultException
   * @throws NotFoundExceptionInterface
   */
  public function createEntity(string $entityFqcn): Notice
  {
    $contextProvider = $this->container->get(AdminContextProvider::class);
    /** @var Notice $notice */
    $notice = parent::createEntity($entityFqcn);

    $folderId = $contextProvider->getRequest()->get('folderId', 1);
    $folder = $this->dossierRepository->findOneById($folderId);

    /** @var User $user */
    $user = $this->getUser();
    if ($school = $user->getSchool()) {
      $notice->addPorteur($school);
    }

    return $notice
      ->setCreateur($user)
      ->setRepertoire($folder);
  }

  /**
   * Surcharge la construction de la requête d'index pour lister les notices.
   *
   * Permet d'enrichir le QueryBuilder avec des critères spécifiques à l'utilisateur connecté.
   *
   * @param SearchDto $searchDto Données de recherche et de pagination
   * @param EntityDto $entityDto Métadonnées de l'entité
   * @param FieldCollection $fields Collection des champs affichés
   * @param FilterCollection $filters Collection des filtres appliqués
   * @return QueryBuilder Requête Doctrine enrichie pour l'index
   */
  public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
  {
    /** @var User $user */
    $user = $this->getUser();
    $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

    return $this->noticeRepository->enrichIndexQueryBuilder($qb, $user);
  }

  /**
   * Crée le FormBuilder pour le formulaire de création d'une notice.
   *
   * Permet de personnaliser les options du formulaire avant sa génération.
   *
   * @param EntityDto $entityDto Métadonnées de l'entité
   * @param KeyValueStore $formOptions Options du formulaire
   * @param AdminContext $context Contexte EasyAdmin
   * @return FormBuilderInterface FormBuilder configuré pour la création
   */
  public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
  {
    $formOptions->setIfNotSet('action', $context->getRequest()->getRequestUri());
    return $this->factory->createNewFormBuilder($entityDto, $formOptions, $context);
  }

  /**
   * Crée le FormBuilder pour le formulaire d'édition d'une notice.
   *
   * Permet de personnaliser les options du formulaire avant sa génération.
   *
   * @param EntityDto $entityDto Métadonnées de l'entité
   * @param KeyValueStore $formOptions Options du formulaire
   * @param AdminContext $context Contexte EasyAdmin
   * @return FormBuilderInterface FormBuilder configuré pour l'édition
   */
  public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
  {
    $formOptions->setIfNotSet('action', $context->getRequest()->getRequestUri());
    return $this->factory->createEditFormBuilder($entityDto, $formOptions, $context);
  }

  /**
   * Détermine la redirection à effectuer après la sauvegarde d'une notice.
   *
   * Redirige selon le bouton de soumission utilisé (soumettre, continuer, ajouter, retour).
   *
   * @param AdminContext $context Contexte EasyAdmin
   * @param string $action Nom de l'action exécutée
   * @return RedirectResponse Réponse de redirection adaptée
   */
  protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
  {
    $request = $context->getRequest();
    $entityId = $context->getEntity()->getPrimaryKeyValue();
    $formData = $request->request->all()['ea']['newForm'] ?? [];
    $submitButtonName = $formData['btn'] ?? null;

    $entityUrl = $this->generator
      ->setAction(Action::DETAIL)
      ->setEntityId($entityId);

    if ($folderId = $request->get('folderId')) {
      $entityUrl->set('folderId', $folderId);
    }

    $url = match ($submitButtonName) {
      self::SAVE_AND_FORWARD => $this->generator->setAction(self::FORWARD_ACTION)->setEntityId($entityId)->generateUrl(),
      Action::SAVE_AND_CONTINUE => $this->generator->setAction(Action::EDIT)->setEntityId($entityId)->generateUrl(),
      Action::SAVE_AND_ADD_ANOTHER => $this->generator->setAction(Action::NEW)->generateUrl(),
      Action::SAVE_AND_RETURN => $context->getReferrer() ?? $entityUrl->generateUrl(),
      default => $this->generateUrl($context->getDashboardRouteName()),
    };

    return $this->redirect($url);
  }

  /**
   * Traite les fichiers uploadés dans le formulaire.
   *
   * Parcourt les champs du formulaire pour gérer l'ajout, la suppression et l'extraction des fichiers (notamment ZIP).
   * Supprime les fichiers existants si nécessaire et extrait les archives ZIP dans le répertoire cible.
   *
   * @param FormInterface $form Formulaire contenant les fichiers uploadés
   * @return void
   */
  protected function processUploadedFiles(FormInterface $form): void
  {
    foreach ($form as $child) {
      $config = $child->getConfig();
      $type = $config->getType()->getInnerType();

      // Si ce n'est pas un champ de type FileUploadType
      if (!$type instanceof FileUploadType) {
        if ($config->getCompound()) {
          $this->processUploadedFiles($child);
        }
        continue;
      }

      /** @var FileUploadState $state */
      $state = $config->getAttribute('state');
      if (!$state->isModified()) {
        continue;
      }

      $uploadDelete = $config->getOption('upload_delete');

      // Supprimer les fichiers existants si nécessaire
      if ($state->hasCurrentFiles() && ($state->isDelete() || (!$state->isAddAllowed() && $state->hasUploadedFiles()))) {
        foreach ($state->getCurrentFiles() as $file) {
          $uploadDelete($file);
        }
        $state->setCurrentFiles([]);
      }

      $filePaths = (array)$child->getData();
      $uploadDir = $config->getOption('upload_dir');
      $uploadNew = $config->getOption('upload_new');

      foreach ($state->getUploadedFiles() as $index => $file) {
        $fileName = u($filePaths[$index])->replace($uploadDir, '')->toString();
        $targetDir = $uploadDir . pathinfo($fileName, PATHINFO_FILENAME);

        if ($file->guessExtension() === 'zip') {
          $zip = new ZipArchive();
          if ($zip->open($file->getRealPath())) {
            $zip->extractTo($targetDir);
            $zip->close();
          }
        } else {
          $uploadNew($file, $uploadDir, $fileName);
        }
      }
    }
  }

  /**
   * Persiste une nouvelle entité Notice en base.
   *
   * Met à jour l'URL de la ressource si un fichier ZIP est présent avant la sauvegarde.
   *
   * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine
   * @param Notice $entityInstance Instance de Notice à persister
   * @return void
   */
  public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
  {
    if ($zipDir = $entityInstance->getRessZip()) {
      $entityInstance->setRessUrl(sprintf('uploads/files/%s', pathinfo($zipDir, PATHINFO_FILENAME)));
    }
    parent::persistEntity($entityManager, $entityInstance);
  }

  /**
   * Met à jour une entité Notice existante en base.
   *
   * Met à jour l'URL de la ressource si un fichier ZIP est présent avant la sauvegarde.
   *
   * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine
   * @param Notice $entityInstance Instance de Notice à mettre à jour
   * @return void
   */
  public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
  {
    if ($zipDir = $entityInstance->getRessZip()) {
      $entityInstance->setRessUrl(sprintf('uploads/files/%s', pathinfo($zipDir, PATHINFO_FILENAME)));
    }
    parent::updateEntity($entityManager, $entityInstance);
  }

  /**
   * Supprime logiquement une entité Notice (soft delete).
   *
   * Marque la notice comme supprimée sans la retirer physiquement de la base.
   *
   * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine
   * @param Notice $entityInstance Instance de Notice à supprimer
   * @return void
   */
  public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
  {
    $entityInstance->setDeleted(true);
    $entityManager->flush();
  }

  /**
   * Affiche la liste des notices dans EasyAdmin.
   *
   * Vérifie les permissions de lecture avant d'afficher l'index.
   *
   * @param AdminContext $context Contexte EasyAdmin
   * @return Response
   */
  public function index(AdminContext $context)
  {
    $this->denyAccessUnlessGranted('ROLE_READ_NOTI');
    return parent::index($context);
  }

  /**
   * Affiche le formulaire de création d'une nouvelle notice.
   *
   * Vérifie les permissions de création et ajoute le dossier courant aux paramètres si présent.
   *
   * @param AdminContext $context Contexte EasyAdmin
   * @return KeyValueStore|Response Paramètres de la vue ou réponse HTTP
   */
  public function new(AdminContext $context)
  {
    $this->denyAccessUnlessGranted('ROLE_CREA_NOTI');
    $resParams = parent::new($context);

    if ($context->getRequest()->get('folderId')) {
      $currItem = $context->getEntity()->getInstance()?->getRepertoire();
      $resParams->set('curritem', $currItem);
    }
    return $resParams;
  }

  /**
   * Affiche le détail d'une notice dans EasyAdmin.
   *
   * Vérifie les permissions de consultation et ajoute le dossier courant aux paramètres si présent.
   *
   * @param AdminContext $context Contexte EasyAdmin
   * @return KeyValueStore Paramètres de la vue détail
   * @throws NonUniqueResultException
   */
  public function detail(AdminContext $context): KeyValueStore
  {
    /** @var Notice $notice */
    $notice = $context->getEntity()->getInstance();
    $this->denyAccessUnlessGranted(
      NoticeActionVoter::VIEW,
      $notice,
      "Vous n'êtes pas autorisé à exécuter cette action sur cette notice."
    );

    $resParams = parent::detail($context);

    if ($folderId = $context->getRequest()->get('folderId')) {
      $folder = $this->dossierRepository->findOneById($folderId);

      // Ajout de l'attribut folderId à chaque action
      $actions = array_map(
        function (ActionDto $action) use ($folderId) {
          $action->setLinkUrl($action->getLinkUrl() . '&folderId=' . $folderId);
          return $action;
        },
        $context->getEntity()->getActions()->all()
      );
      $context->getEntity()->setActions(ActionCollection::new($actions));

      $resParams->set('curritem', $folder);
    }

    return $resParams;
  }

  /**
   * Affiche le formulaire d'édition d'une notice dans EasyAdmin.
   *
   * Vérifie les permissions de consultation avant d'afficher le formulaire d'édition.
   *
   * @param AdminContext $context Contexte EasyAdmin
   * @return Response
   */
  public function edit(AdminContext $context)
  {
    /** @var Notice $notice */
    $notice = $context->getEntity()->getInstance();
    $this->denyAccessUnlessGranted(
      NoticeActionVoter::EDIT,
      $notice,
      "Vous n'êtes pas autorisé à exécuter cette action sur cette notice."
    );

    return parent::edit($context);
  }

  /**
   * Supprime une notice via EasyAdmin.
   *
   * Vérifie les permissions de suppression avant d'exécuter l'action.
   *
   * @param AdminContext $context Contexte EasyAdmin
   * @return Response
   */
  public function delete(AdminContext $context)
  {
    $this->denyAccessUnlessGranted('ROLE_DROP_NOTI');
    return parent::delete($context);
  }

  /**
   * Duplique une notice existante.
   *
   * Crée une nouvelle notice à partir de l’originale, réinitialise certains champs et redirige vers le détail.
   *
   * @return Response
   */
  public function duplicateNotice()
  {
    $context = $this->getContext();
    /** @var Notice $notice */
    $notice = $context->getEntity()->getInstance();
    $this->denyAccessUnlessGranted(NoticeActionVoter::VIEW, $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

    $duplicatedNotice = (clone $notice)
      ->setCreateur($this->getUser())
      ->setValidateur(null)
      ->setCreeLe(new DateTimeImmutable())
      ->setEditeLe(null)
      ->setUuid(Uuid::v4())
      ->setEtat(NoticEtat::Working)
      ->setEditDemande(false)
      ->setTitre("COPIE - " . $notice->getTitre());

    $this->noticeRepository->add($duplicatedNotice);
    $this->dispatcher->dispatch(new AfterEntityPersistedEvent($duplicatedNotice));
    $this->addFlash('success', "Cette notice dupliquée vient d'être créée avec succès !");

    $urlGenerator = $this->generator
      ->setController(self::class)
      ->setAction(Action::DETAIL)
      ->setEntityId($duplicatedNotice->getId());

    if ($folderId = $context->getRequest()->get('folderId')) {
      $urlGenerator->set('folderId', $folderId);
    }

    return $this->redirect($urlGenerator->removeReferrer()->generateUrl());
  }

  /**
   * Soumet une notice pour validation.
   *
   * Change l’état de la notice à "Soumise", déclenche les événements associés et redirige vers le détail.
   *
   * @return Response
   */
  public function forwardNotice()
  {
    $context = $this->getContext();
    $urlGenerator = $this->redirecTo($context->getRequest())->removeReferrer();

    /** @var Notice|null $notice */
    $notice = $context->getEntity()->getInstance();
    $this->denyAccessUnlessGranted(NoticeActionVoter::EDIT, $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

    $this->noticeRepository->add($notice->setEtat(NoticEtat::Forward));
    $this->dispatcher->dispatch(new AfterNoticeSubmissionEvent($notice));
    $this->dispatcher->dispatch(new AfterNoticeStateSetEvent($notice, ['Soumettre', NoticEtat::Forward->getLabel(), 'Soumission']));
    $this->addFlash('success', sprintf("La notice est bien %s avec succès !", NoticEtat::Forward->getLabel()));

    return $this->redirect($urlGenerator->generateUrl());
  }

  /**
   * Valide une notice pour publication.
   *
   * Change l’état à "Validée", assigne le validateur et redirige vers le détail.
   *
   * @return Response
   */
  public function approveNotice()
  {
    $context = $this->getContext();
    $urlGenerator = $this->redirecTo($context->getRequest())->removeReferrer();

    /** @var User $user */
    $user = $this->getUser();
    /** @var Notice|null $notice */
    $notice = $context->getEntity()->getInstance();
    $this->denyAccessUnlessGranted(NoticeActionVoter::EDIT, $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

    $this->noticeRepository->add($notice->setEtat(NoticEtat::Approved)->setValidateur($user));
    $this->dispatcher->dispatch(new AfterNoticeApprovingEvent($notice));
    $this->addFlash('success', sprintf("La notice est bien %s avec succès !", NoticEtat::Approved->getLabel()));

    return $this->redirect($urlGenerator->generateUrl());
  }

  /**
   * Rejette une notice pour correction.
   *
   * Change l’état à "En cours de rédaction", enregistre les motifs et redirige vers le détail.
   *
   * @return Response
   */
  public function rejectNotice()
  {
    $context = $this->getContext();
    $urlGenerator = $this->redirecTo($context->getRequest())->removeReferrer();
    $motifs = $context->getRequest()->get('motifs');

    /** @var Notice|null $notice */
    $notice = $context->getEntity()->getInstance();
    $this->denyAccessUnlessGranted(NoticeActionVoter::EDIT, $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

    $this->noticeRepository->add($notice->setEtat(NoticEtat::Working));
    $this->dispatcher->dispatch(new AfterNoticeRejectingEvent($notice, $motifs));
    $this->addFlash('success', "La notice est bien rejetée avec succès !");

    return $this->redirect($urlGenerator->generateUrl());
  }

  /**
   * Dépublie une notice validée.
   *
   * Change l’état à "Soumise", déclenche l’événement de dépublication et redirige vers le détail.
   *
   * @return Response
   */
  public function publishNotice()
  {
    $context = $this->getContext();
    $urlGenerator = $this->redirecTo($context->getRequest())->removeReferrer();

    /** @var Notice|null $notice */
    $notice = $context->getEntity()->getInstance();
    $this->denyAccessUnlessGranted('ROLE_VALI_NOTI', $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

    $this->noticeRepository->add($notice->setEtat(NoticEtat::Forward));
    $this->dispatcher->dispatch(new AfterNoticeStateSetEvent($notice, ['Dépublier', 'Dépubliée', 'Dépublication']));
    $this->addFlash('success', "La notice est bien dépubliée avec succès !");

    return $this->redirect($urlGenerator->generateUrl());
  }

  /**
   * Autorise la modification d’une notice rectifiée.
   *
   * Change l’état à "En cours de rédaction", désactive la demande de rectification et redirige vers le détail.
   *
   * @return Response
   */
  public function allowedNotice()
  {
    $context = $this->getContext();
    $urlGenerator = $this->redirecTo($context->getRequest())->removeReferrer();

    /** @var Notice|null $notice */
    $notice = $context->getEntity()->getInstance();
    $this->denyAccessUnlessGranted('ROLE_VALI_NOTI', $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

    $this->noticeRepository->add($notice->setEtat(NoticEtat::Working)->setEditDemande(false));
    $this->dispatcher->dispatch(new AfterNoticeStateSetEvent($notice, ['Autoriser', 'Autorisée', 'Autorisation']));
    $this->addFlash('success', "La notice est bien autorisée avec succès !");

    return $this->redirect($urlGenerator->generateUrl());
  }

  /**
   * Demande la rectification d’une notice.
   *
   * Active le flag de demande de rectification et redirige vers le détail.
   *
   * @return Response
   */
  public function adjustNotice()
  {
    $context = $this->getContext();
    $urlGenerator = $this->redirecTo($context->getRequest())->removeReferrer();

    /** @var Notice|null $notice */
    $notice = $context->getEntity()->getInstance();
    $this->denyAccessUnlessGranted(NoticeActionVoter::VIEW, $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

    $this->noticeRepository->add($notice->setEditDemande(true));
    $this->dispatcher->dispatch(new AfterNoticeAdjustingEvent($notice));
    $this->addFlash('success', "Votre demande de rectification est bien envoyée !");

    return $this->redirect($urlGenerator->generateUrl());
  }

  /**
   * Labellise une notice.
   *
   * Affecte un label à la notice, déclenche l’événement de catégorisation et redirige vers le détail.
   *
   * @return Response
   */
  public function labelNotice()
  {
    $context = $this->getContext();
    $urlGenerator = $this->redirecTo($context->getRequest())->removeReferrer();
    $label = $context->getRequest()->get('label');

    /** @var Notice $notice */
    $notice = $context->getEntity()->getInstance();
    $this->denyAccessUnlessGranted('ROLE_VALI_NOTI', $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

    $this->noticeRepository->add($notice->setLabel($label));
    $this->dispatcher->dispatch(new AfterNoticeStateSetEvent($notice, ['Catégoriser', 'Labellisée', 'Catégorisation']));
    $this->addFlash('success', "La notice est labellisée avec succès !");

    return $this->redirect($urlGenerator->generateUrl());
  }

  /**
   * Déplace une notice vers un autre dossier.
   *
   * Modifie le répertoire associé à la notice, déclenche l’événement de déplacement et redirige vers le détail.
   *
   * @param AdminContext $context Contexte EasyAdmin
   * @return Response
   */
  public function moveNotice(AdminContext $context)
  {
    $urlGenerator = $this->redirecTo($context->getRequest())->removeReferrer();
    $dossierData = $context->getRequest()->get("dossier");

    /** @var Notice $notice */
    $notice = $context->getEntity()->getInstance();
    $this->denyAccessUnlessGranted(NoticeActionVoter::EDIT, $notice, "Vous n'êtes pas autorisé à exécuter cette action sur cette notice.");

    if (!empty($dossierData["dossier"]) && $dossier = $this->dossierRepository->find($dossierData["dossier"])) {
      $notice->setRepertoire($dossier);
    }

    $this->noticeRepository->add($notice);
    $this->dispatcher->dispatch(new AfterNoticeStateSetEvent($notice, ['Déplacer', 'Déplacée', 'Déplacement']));
    $this->addFlash('success', "La notice est bien déplacée avec succès !");

    return $this->redirect($urlGenerator->generateUrl());
  }

  /**
   * Génère l'URL de redirection après une action selon le dossier courant.
   *
   * Redirige vers le détail du dossier si `folderId` est présent, sinon vers l'index des notices.
   *
   * @param Request $request Requête HTTP courante
   * @return AdminUrlGenerator Générateur d'URL EasyAdmin configuré
   */
  private function redirecTo(Request $request): AdminUrlGenerator
  {
    $folderId = $request->get('folderId');

    return $folderId
      ? $this->generator->setController(DossierCrudController::class)->setAction(Action::DETAIL)->setEntityId($folderId)
      : $this->generator->setController(self::class)->setAction(Action::INDEX);
  }

  /**
   * Retourne les badges (libellés) d'une collection d'entités.
   *
   * Formate chaque élément selon ses méthodes (`getPrenom`, `getNom`, ou `__toString`).
   * Retourne un libellé par défaut si la collection est vide.
   *
   * @param Collection|null $collection Collection d'entités à afficher
   * @return array Liste des libellés formatés
   */
  private function renderEntityCollectionBadges(?Collection $collection): array
  {
    if (empty($collection) || (is_iterable($collection) && count($collection) === 0)) {
      return ['Aucune'];
    }

    $labels = [];
    foreach ($collection as $item) {
      if (method_exists($item, 'getPrenom') && method_exists($item, 'getNom')) {
        $labels[] = trim($item->getPrenom() . ' ' . $item->getNom());
      } else {
        $labels[] = method_exists($item, 'getNom') ? $item->getNom() : (string)$item;
      }
    }
    return $labels;
  }
}
