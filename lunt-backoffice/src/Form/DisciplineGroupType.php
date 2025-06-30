<?php

namespace App\Form;

use App\Entity\Discipline;
use App\Entity\DisciplineGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\{Notice, Univerique};
use function Symfony\Component\Translation\t;
use Doctrine\ORM\EntityManagerInterface;

class DisciplineGroupType extends AbstractType
{
  private EntityManagerInterface $em;

  public function __construct(EntityManagerInterface $em)
  {
    $this->em = $em;
  }

  public function buildForm(FormBuilderInterface $builder, array $options)
  {
    $user = $options['user'];

    $builder
      ->add('champDisc', EntityType::class, [
        'class' => Discipline::class,
        'required' => true,
        'placeholder' => 'Sélectionner un champ',
        'label' => 'Domaine de connaissance',
        'query_builder' => function ($repo) use ($user) {
          $qb = $repo->createQueryBuilder('entity');
          if ($user->getUntheme() instanceof Univerique) {
            $qb->where('entity IN (:champs)')
              ->setParameter('champs', $user->getUntheme()->getFields());
          } else {
            $qb->where('entity.parent IS NULL');
          }
          return $qb
            ->join('entity.children', 's')
            ->leftJoin('s.children', 'd')
            ->addSelect('s,d')
            ->orderBy('entity.nom', 'ASC')
            ->addOrderBy('s.nom', 'ASC')
            ->addOrderBy('d.nom', 'ASC');
        }
      ])
      ->add('discipline', EntityType::class, [
        'class' => Discipline::class,
        'choices' => [],
        'choice_loader' => null,
        'required' => false,
        'placeholder' => 'Sélectionner une discipline',
      ])
      ->add('specialites', EntityType::class, [
        'class' => Discipline::class,
        'multiple' => true,
        'autocomplete' => true,
        'choices' => [],
        'choice_loader' => null,
        'required' => false,
        'placeholder' => 'Sélectionner des spécialités',
      ]);

    $formModifier = function (FormInterface $form, Discipline $champDisc = null, Discipline $selectedDiscipline = null) {
      $disciplines = null === $champDisc ? [] : $champDisc->getChildren()->toArray();
      if ($selectedDiscipline && !in_array($selectedDiscipline, $disciplines, true)) {
        $disciplines[] = $selectedDiscipline;
      }
      $form->add('discipline', EntityType::class, [
        'class' => Discipline::class,
        'choices' => $disciplines,
        'required' => false,
        'placeholder' => 'Sélectionner la discipline',
        'label' => t('notice.discipline', domain: 'EasyAdminBundle'),
        'help' => t('notice.discipline_help', domain: 'EasyAdminBundle'),
        'data' => $selectedDiscipline,
      ]);
      if ($selectedDiscipline && $selectedDiscipline instanceof Discipline) {
        $specialites = $selectedDiscipline->getChildren()->toArray();
        $form->add('specialites', EntityType::class, [
          'class' => Discipline::class,
          'choices' => $specialites,
          'multiple' => true,
          'autocomplete' => true,
          'required' => false,
          'placeholder' => 'Sélectionner la spécialité',
          'label' => t('notice.specialite', domain: 'EasyAdminBundle'),
          'help' => t('notice.specialite_help', domain: 'EasyAdminBundle'),
        ]);
      } else {
        if ($form->has('specialites')) {
          $form->remove('specialites');
        }
      }
    };

    $specialitesModifier = function (FormInterface $form, $discipline = null, array $selectedSpecialites = []) {
      if ($discipline && !$discipline instanceof Discipline) {
        $discipline = $this->em->getRepository(Discipline::class)->find($discipline);
      }
      $specialites = null === $discipline ? [] : $discipline->getChildren()->toArray();
      $form->add('specialites', EntityType::class, [
        'class' => Discipline::class,
        'choices' => $specialites,
        'multiple' => true,
        'autocomplete' => true,
        'required' => false,
        'placeholder' => 'Sélectionner la spécialité',
        'label' => t('notice.specialite', domain: 'EasyAdminBundle'),
        'help' => t('notice.specialite_help', domain: 'EasyAdminBundle'),
        'data' => $selectedSpecialites,
      ]);
    };

    $builder->get('champDisc')->addEventListener(
      FormEvents::POST_SUBMIT,
      function (FormEvent $event) use ($formModifier) {
        $form = $event->getForm()->getParent();
        $champDisc = $event->getForm()->getData();
        $index = $form->getName();
        $discipline = null;
        if (
          isset($_POST['Notice']['disciplineGroups'][$index]['discipline'])
          && $_POST['Notice']['disciplineGroups'][$index]['discipline']
        ) {
          $disciplineId = $_POST['Notice']['disciplineGroups'][$index]['discipline'];
          $discipline = $this->em->getRepository(Discipline::class)->find($disciplineId);
        }
        $formModifier($form, $champDisc, $discipline);
      }
    );

    $builder->get('discipline')->addEventListener(
      FormEvents::POST_SUBMIT,
      function (FormEvent $event) use ($formModifier, $specialitesModifier) {
        $form = $event->getForm()->getParent();
        $champDisc = $form->has('champDisc') ? $form->get('champDisc')->getData() : null;
        $discipline = $event->getForm()->getData();
        $index = $form->getName();
        if (!$discipline) {
          $submittedData = $event->getForm()->getViewData();
          if ($submittedData) {
            $discipline = $this->em->getRepository(Discipline::class)->find($submittedData);
          } elseif (
            isset($_POST['Notice']['disciplineGroups'][$index]['discipline'])
            && $_POST['Notice']['disciplineGroups'][$index]['discipline']
          ) {
            $disciplineId = $_POST['Notice']['disciplineGroups'][$index]['discipline'];
            $discipline = $this->em->getRepository(Discipline::class)->find($disciplineId);
          }
        } elseif (!$discipline instanceof Discipline) {
          $discipline = $this->em->getRepository(Discipline::class)->find($discipline);
        }
        $formModifier($form, $champDisc, $discipline);
        $specialitesModifier($form, $discipline, []);
      }
    );

    $builder->addEventListener(
      FormEvents::POST_SET_DATA,
      function (FormEvent $event) use ($formModifier, $specialitesModifier) {
        $data = $event->getData();
        $form = $event->getForm();
        if ($data === null) {
          $formModifier($form, null, null);
          $specialitesModifier($form, null, []);
          return;
        }
        $champDisc = $data->getChampDisc();
        $discipline = $data->getDiscipline();
        if ($discipline && !$discipline instanceof Discipline) {
          $discipline = $this->em->getRepository(Discipline::class)->find($discipline);
        }
        $selectedSpecialites = $data->getSpecialites() ? $data->getSpecialites()->toArray() : [];
        $formModifier($form, $champDisc, $discipline);
        $specialitesModifier($form, $discipline, $selectedSpecialites);
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
      'data_class' => DisciplineGroup::class,
    ]);
  }
}
