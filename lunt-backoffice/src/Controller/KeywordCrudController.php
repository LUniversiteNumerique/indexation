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


    #[Route('/api/keyworks', name: 'app_keywork_new', methods: ['POST'])]
    public function ajaxNew(Request $request, SerializerInterface $serializer, ValidatorInterface $validator): Response
    {
        $values = $serializer->deserialize($request->getContent(), Keyword::class, 'json');
        $object = $validator->validate($values);
        dd($object, $values);
        if ($object->count()) {
            $errors = array_reduce((array)$object, fn($acc, $obj) => $acc[$obj->getPropertyPath()] = $obj->getMessage());
            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $this->repository->add($values);
        return $this->json($object, Response::HTTP_CREATED);
    }
}
