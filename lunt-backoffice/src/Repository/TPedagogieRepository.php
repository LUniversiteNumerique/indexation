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

    public function add(TPedagogie $u=null): ?TPedagogie
    {
        if($u) $this->_em->persist($u);
        $this->_em->flush();
        return $u;
    }

    public function del(TPedagogie $u): TPedagogie
    {
        $this->_em->remove($u);
        $this->_em->flush();

        return $u;
    }
}
