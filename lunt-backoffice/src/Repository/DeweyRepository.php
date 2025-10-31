<?php

namespace App\Repository;

use App\Entity\Dewey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dewey>
 *
 * @method Dewey|null find($id, $lockMode = null, $lockVersion = null)
 * @method Dewey|null findOneBy(array $criteria, array $orderBy = null)
 * @method Dewey[]    findAll()
 * @method Dewey[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeweyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dewey::class);
    }
}
