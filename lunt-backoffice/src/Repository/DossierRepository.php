<?php

namespace App\Repository;

use App\Entity\Dossier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    public function rootQB(): \Doctrine\ORM\QueryBuilder
    {
      return $this->createQueryBuilder('s')->select('s,c,cc')
        ->leftJoin('s.children', 'c')
        ->leftJoin('c.children', 'cc');
    }
    public function findParentWithChild()
    {
      return $this->rootQB()->where('s.parent is null')->getQuery()->getResult();
    }
    public function findWithChild(?int $id): array
    {
      $qb = $this->rootQB();

      if($id) $qb->where('s.parent = :parent')
        ->setParameter('parent',$id);
      else $qb->where('s.parent is null');

      return $qb->getQuery()->getResult();
    }
    public function findOneForAll(?int $id)
    {
      $qb = $this->rootQB();

      if($id) return $qb->where('s = :parent')->setParameter('parent',$id)
        ->getQuery()->getOneOrNullResult();
      else return $qb->where('s.parent is null')->getQuery()->getResult();
    }

    public function add(Dossier $d=null): ?Dossier
    {
        if($d) $this->_em->persist($d);
        $this->_em->flush();
        return $d;
    }

    public function del(Dossier $d): Dossier
    {
        $this->_em->remove($d);
        $this->_em->flush();

        return $d;
    }
}
