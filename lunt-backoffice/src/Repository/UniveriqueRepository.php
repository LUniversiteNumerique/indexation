<?php

namespace App\Repository;

use App\Entity\Univerique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Univerique>
 *
 * @method Univerique|null find($id, $lockMode = null, $lockVersion = null)
 * @method Univerique|null findOneBy(array $criteria, array $orderBy = null)
 * @method Univerique[]    findAll()
 * @method Univerique[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UniveriqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Univerique::class);
    }

    public function add(Univerique $u=null): ?Univerique
    {
        if($u) $this->_em->persist($u);
        $this->_em->flush();
        return $u;
    }

    public function del(Univerique $u): Univerique
    {
        $this->_em->remove($u);
        $this->_em->flush();

        return $u;
    }
}
