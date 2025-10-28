<?php

namespace App\Repository;

use App\Entity\TDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TDocument>
 *
 * @method TDocument|null find($id, $lockMode = null, $lockVersion = null)
 * @method TDocument|null findOneBy(array $criteria, array $orderBy = null)
 * @method TDocument[]    findAll()
 * @method TDocument[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TDocument::class);
    }
}
