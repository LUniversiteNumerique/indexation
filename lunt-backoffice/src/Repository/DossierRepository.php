<?php

namespace App\Repository;

use App\Entity\Dossier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dossier>
 *
 * @method Dossier|null find($id, $lockMode = null, $lockVersion = null)
 * @method Dossier|null findOneBy(array $criteria, array $orderBy = null)
 * @method Dossier[]    findAll()
 * @method Dossier[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DossierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dossier::class);
    }

    /**
     * Retourne un dossier avec ses enfants et petits-enfants selon l'id, ou tous les racines si id null.
     *
     * @param int|null $parentId
     * @return Dossier|Dossier[]|null
     * @throws NonUniqueResultException
     */
    public function findOneById(?int $parentId): Dossier|array|null
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s, c, cc')
            ->leftJoin('s.children', 'c')
            ->leftJoin('c.children', 'cc');

        if ($parentId !== null) {
            return $qb->where('s = :parent')
                ->setParameter('parent', $parentId)
                ->getQuery()
                ->getOneOrNullResult();
        }

        return $qb->where('s.parent IS NULL')
            ->getQuery()
            ->getResult();
    }
}