<?php

namespace App\Controller;

use App\Entity\{Dewey, DeweyPerso};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur CRUD pour DeweyPerso.
 */
class DeweyPersoCrudController extends AbstractController
{
    /**
     * Ajoute une nouvelle entité DeweyPerso.
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    #[Route('/admin/dewey-perso/add', name: 'admin_dewey_perso_add', methods: ['POST'])]
    public function addDeweyPerso(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $code = isset($data['code']) ? str_replace(' ', '', trim($data['code'])) : null;
        $nom = isset($data['nom']) ? trim($data['nom']) : null;

        if (empty($code) || empty($nom)) {
            return new JsonResponse(['error' => 'Code et nom requis'], 400);
        }

        if (!preg_match('/^(?:\d{1,3}|\d{3}\.\d+)$/', $code)) {
            return new JsonResponse([
                'error' => 'Le code est invalide (1 à 3 chiffres, puis un point pour séparer les trois premiers chiffres des suivants).'
            ], 400);
        }

        $fullCode = 'http://dewey.info/class/' . $code . '/';

        if (
            $entityManager->getRepository(DeweyPerso::class)->findOneBy(['code' => $fullCode]) ||
            $entityManager->getRepository(Dewey::class)->findOneBy(['code' => $fullCode])
        ) {
            return new JsonResponse(['error' => 'Ce code existe déjà !'], 409);
        }

        $deweyPerso = (new DeweyPerso())
            ->setCode($fullCode)
            ->setNom($nom);

        $entityManager->persist($deweyPerso);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'id' => $deweyPerso->getId(),
            'nom' => $deweyPerso->getNom(),
            'code' => $deweyPerso->getCode()
        ]);
    }
}
