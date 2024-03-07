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

    public function add(Dewey $d=null): ?Dewey
    {
        if($d) $this->_em->persist($d);
        $this->_em->flush();
        return $d;
    }

    public function del(Dewey $d): Dewey
    {
        $this->_em->remove($d);
        $this->_em->flush();

        return $d;
    }
}
