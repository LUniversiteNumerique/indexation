<?php

namespace App\Repository;

use App\Entity\Discipline;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Discipline>
 *
 * @method Discipline|null find($id, $lockMode = null, $lockVersion = null)
 * @method Discipline|null findOneBy(array $criteria, array $orderBy = null)
 * @method Discipline[]    findAll()
 * @method Discipline[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DisciplineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Discipline::class);
    }
    public function rootQB(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->select('s,c,cc')
            ->join('s.children', 'c')
            ->join('c.children', 'cc')
            ->where('s.parent is null');
    }
    public function findParentWithChild()
    {
            return $this->rootQB()->getQuery()->getResult();
    }

    public function add(Discipline $d=null): ?Discipline
    {
        if($d) $this->_em->persist($d);
        $this->_em->flush();
        return $d;
    }

    public function del(Discipline $d): Discipline
    {
        $this->_em->remove($d);
        $this->_em->flush();

        return $d;
    }
}
