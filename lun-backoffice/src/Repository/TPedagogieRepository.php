<?php

namespace App\Repository;

use App\Entity\TPedagogie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TPedagogie>
 *
 * @method TPedagogie|null find($id, $lockMode = null, $lockVersion = null)
 * @method TPedagogie|null findOneBy(array $criteria, array $orderBy = null)
 * @method TPedagogie[]    findAll()
 * @method TPedagogie[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TPedagogieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TPedagogie::class);
    }

//    /**
//     * @return TPedagogie[] Returns an array of TPedagogie objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?TPedagogie
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
