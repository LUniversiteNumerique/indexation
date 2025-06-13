<?php
namespace App\Form;

use App\Entity\Dewey;
use App\Entity\DeweyGroup;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use EasyCorp\Bundle\EasyAdminBundle\Config\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use function Symfony\Component\Translation\t;

class DeweyGroupType extends AbstractType
{
  private EntityManagerInterface $em;

  public function __construct(EntityManagerInterface $em)
  {
    $this->em = $em;
  }

  public function buildForm(FormBuilderInterface $builder, array $options)
  {
    $user = $options['user'];

    // Ajout des trois champs vides (division et codeweys seront remplis dynamiquement)
    $builder
      ->add('dewey', EntityType::class, [
        'class' => Dewey::class,
        'choice_label' => 'nom',
        'required' => true,
        'placeholder' => '-- Sélectionner un Dewey --',
        'label' => 'Discipline fondamentale',
        'help' => t('Le premier niveau de classification dewey', domain: 'EasyAdminBundle'),
        'query_builder' => function (EntityRepository $er) {
          return $er->createQueryBuilder('d')
            ->where('d.parent IS NULL')
            ->orderBy('d.nom', 'ASC');
        },
      ])
      ->add('division', EntityType::class, [
        'class' => Dewey::class,
        'choices' => [],
        'choice_loader' => null,
        'required' => false,
        'placeholder' => '-- Sélectionner une division --',
      ])
      ->add('codeweys', EntityType::class, [
        'class' => Dewey::class,
        'multiple' => true,
        'autocomplete' => true,
        'choices' => [],
        'choice_loader' => null,
        'required' => false,
        'placeholder' => '-- Sélectionner des codes --',
        'label' => 'Codes Dewey',
      ]);

    $formModifier = function (FormInterface $form, Dewey $dewey = null, Dewey $selectedDivision = null) {
      $divisions = null === $dewey ? [] : $dewey->getChildren()->toArray();
      if ($selectedDivision && !in_array($selectedDivision, $divisions, true)) {
        $divisions[] = $selectedDivision;
      }
      $form->add('division', EntityType::class, [
        'class' => Dewey::class,
        'choices' => $divisions,
        'required' => false,
        'placeholder' => '-- Sélectionner une division --',
        'label' => t('notice.division', domain: 'EasyAdminBundle'),
        'help' => t('notice.division_help', domain: 'EasyAdminBundle'),
        'data' => $selectedDivision,
      ]);
      if ($selectedDivision && $selectedDivision instanceof Dewey) {
        $codeweys = $selectedDivision->getChildren()->toArray();
        $form->add('codeweys', EntityType::class, [
          'class' => Dewey::class,
          'choices' => $codeweys,
          'multiple' => true,
          'autocomplete' => true,
          'required' => false,
          'placeholder' => 'Sélectionnez le code Dewey',
          'label' => 'Codes Dewey',
        ]);
      } else {
        if ($form->has('codeweys')) {
          $form->remove('codeweys');
        }
      }
    };

    // Fonction pour remplir les codes selon la division
    $codeweysModifier = function (FormInterface $form, Dewey $division = null, array $selectedCodes = []) {
      if ($division && !$division instanceof Dewey) {
        $division = $this->em->getRepository(Dewey::class)->find($division);
      }
      $codes = null === $division ? [] : $division->getChildren()->toArray();
      $form->add('codeweys', EntityType::class, [
        'class' => Dewey::class,
        'choices' => $codes,
        'multiple' => true,
        'autocomplete' => true,
        'required' => false,
        'placeholder' => 'Sélectionnez des codes',
        'label' => 'Codes Dewey',
        'data' => $selectedCodes,
      ]);
    };

    $builder->get('dewey')->addEventListener(
      FormEvents::POST_SUBMIT,
      function (FormEvent $event) use ($formModifier) {
        $form = $event->getForm()->getParent();
        $dewey = $event->getForm()->getData();
        $index = $form->getName();
        $division = null;
        if (
          isset($_POST['Notice']['deweyGroups'][$index]['division'])
          && $_POST['Notice']['deweyGroups'][$index]['division']
        ) {
          $divisionId = $_POST['Notice']['deweyGroups'][$index]['division'];
          $division = $this->em->getRepository(Dewey::class)->find($divisionId);
        }
        $formModifier($form, $dewey, $division);
      }
    );

    // Listener sur division
    $builder->get('division')->addEventListener(
      FormEvents::POST_SUBMIT,
      function (FormEvent $event) use ($formModifier, $codeweysModifier) {
        $form = $event->getForm()->getParent();
        $dewey = $form->has('dewey') ? $form->get('dewey')->getData() : null;
        $division = $event->getForm()->getData();
        $index = $form->getName();
        if(!$division){
          $submittedData = $event->getForm()->getViewData();
          if ($submittedData) {
            $division = $this->em->getRepository(Dewey::class)->find($submittedData);
          } elseif (
            isset($_POST['Notice']['deweyGroups'][$index]['division'])
            && $_POST['Notice']['deweyGroups'][$index]['division']
          ) {
            $divisionId = $_POST['Notice']['divisionGroups'][$index]['division'];
            $division = $this->em->getRepository(Dewey::class)->find($divisionId);
          } elseif (!$division instanceof Dewey) {
            $division = $this->em->getRepository(Dewey::class)->find($division);
          }
          $formModifier($form, $dewey, $division);
          $codeweysModifier($form, $division, []);

        }
      }
    );

    // Pré-remplissage à l'édition
    $builder->addEventListener(
      FormEvents::POST_SET_DATA,
      function (FormEvent $event) use ($formModifier, $codeweysModifier) {
        $data = $event->getData();
        $form = $event->getForm();
        if ($data === null) {
          $formModifier($form, null, null);
          $codeweysModifier($form, null, []);
          return;
        }
        $dewey = $data->getDewey();
        $division = $data->getDivision();
        if ($division && !$division instanceof Dewey) {
          $division = $this->em->getRepository(Dewey::class)->find($division);
        }
        $selectedCodes = $data->getCodeweys() ? $data->getCodeweys()->toArray() : [];
        $formModifier($form, $dewey, $division);
        $codeweysModifier($form, $division, $selectedCodes);
      }
    );
  }

  public function configureAssets(Assets $assets): Assets
  {
    return $assets->addJsFile(Asset::new('../assets/form.js')->onlyOnForms());
  }

  public function configureOptions(OptionsResolver $resolver)
  {
    $resolver->setDefaults([
      'user' => null,
      'data_class' => DeweyGroup::class,
    ]);
  }
}
