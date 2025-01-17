<?php

namespace App\Controller;

use App\Entity\Keyword;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, Filters};
use App\Repository\KeywordRepository;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{BooleanField, DateTimeField, IdField, TextField};
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class KeywordCrudController extends AbstractCrudController
{
    public function __construct(private readonly KeywordRepository $repository){}

    public static function getEntityFqcn(): string
    {
        return Keyword::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(BooleanFilter::new('valide'));
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('mot-clé')->setEntityLabelInPlural('Mots clés')
            ->setSearchFields(['nom'])->setDefaultSort(['nom' => 'ASC'])->setEntityPermission('ROLE_READ_KEYW');
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::DETAIL)
            ->add(Crud::PAGE_NEW, Action::INDEX)
            ->setPermission(Action::NEW, 'ROLE_CREA_KEYW')
            ->setPermission(Action::EDIT, 'ROLE_EDIT_KEYW')
            ->setPermission(Action::DELETE, 'ROLE_DROP_KEYW')
            ->setPermission(Action::BATCH_DELETE, 'ROLE_DROP_KEYW');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('nom', 'Terme'),
            BooleanField::new('valide', 'Statut de validation')
                ->setSortable(false)->renderAsSwitch($this->isGranted('ROLE_EDIT_KEYW')),
            DateTimeField::new('creeLe', 'Date de création')->hideOnForm(),
            DateTimeField::new('editeLe', 'Dernière modification')->onlyOnDetail()
        ];
    }

    #[Route('/api/keyworks', name: 'app_keywork_new', methods: 'POST'), IsGranted('ROLE_CREA_KEYW')]
    public function ajaxNew(Request $request, SerializerInterface $serializer, ValidatorInterface $validator): Response
    {
        /** @var Keyword $values */
        $values = $serializer->deserialize($request->getContent(), Keyword::class, 'json');
        /** @var ConstraintViolationList $object */$object = $validator->validate($values);

        if ($object->count()) {
            $error = $object->get(0); //array_reduce($object->getIterator()?->getArrayCopy(), fn($acc, $obj) => $acc[$obj->getPropertyPath()] = $obj->getMessage());
            return $this->json(['error' => $error->getInvalidValue() .', '. $error->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $this->repository->add($values->setCreeLe(new \DateTimeImmutable()));
        return $this->json([
            'id' => $values->getId(),
            'name' => $values->getNom(),
        ], Response::HTTP_CREATED);
    }
}
