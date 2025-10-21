<?php

namespace App\Controller;

use App\Entity\Dewey;
use App\Entity\DeweyPerso;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DeweyPersoCrudController extends AbstractController
{
  #[Route('/admin/dewey-perso/add', name: 'admin_dewey_perso_add', methods: ['POST'])]
  public function addDeweyPerso(Request $request, EntityManagerInterface $em): JsonResponse
  {
    $data = json_decode($request->getContent(), true);
    $code = isset($data['code']) ? str_replace(' ', '', trim($data['code'])) : null;
    $nom = isset($data['nom']) ? trim($data['nom']) : null;

    if (!preg_match('/^(?:\d{1,3}|\d{3}\.\d+)$/', $code)) {
      return new JsonResponse(['error' => 'Le code est invalide (1 à 3 chiffres, puis un point pour séparer les trois premiers chiffres des suivants).'], 400);
    }
    if (!$code || !$nom) {
      return new JsonResponse(['error' => 'Code et nom requis'], 400);
    }

    $existDeweyPerso = $em->getRepository(DeweyPerso::class)
      ->findOneBy(['code' => 'http://dewey.info/class/' . $code . '/']);
    $existDewey = $em->getRepository(Dewey::class)
      ->findOneBy(['code' => 'http://dewey.info/class/' . $code . '/']);
    if ($existDeweyPerso || $existDewey) {
      return new JsonResponse(['error' => 'Ce code existe déjà !'], 409);
    } else {
      $deweyPerso = new DeweyPerso();
      $deweyPerso->setCode('http://dewey.info/class/' . $code . '/')->setNom($nom);
      $em->persist($deweyPerso);
      $em->flush();
    }

    return new JsonResponse([
      'success' => true,
      'id' => $deweyPerso->getId(),
      'nom' => $deweyPerso->getNom(),
      'code' => $deweyPerso->getCode()
    ]);
  }
}
