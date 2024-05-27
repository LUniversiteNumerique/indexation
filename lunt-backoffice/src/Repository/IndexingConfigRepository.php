<?php

namespace App\Repository;

use App\Entity\IndexingConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IndexingConfig>
 *
 * @method IndexingConfig|null find($id, $lockMode = null, $lockVersion = null)
 * @method IndexingConfig|null findOneBy(array $criteria, array $orderBy = null)
 * @method IndexingConfig[]    findAll()
 * @method IndexingConfig[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IndexingConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IndexingConfig::class);
    }

    public function findLatest(?bool $extIndex = false): ?IndexingConfig
    {
        try { return $this->createQueryBuilder('c')
            ->where('c.indexType = :isExt')->setParameter('isExt', $extIndex)
            ->orderBy('c.id', 'DESC')->setMaxResults(1)
            ->getQuery()->getOneOrNullResult(); }
        catch (NonUniqueResultException) { return null; }
    }
    public function add(IndexingConfig $a=null): ?IndexingConfig
    {
        if($a) $this->_em->persist($a);
        $this->_em->flush();
        return $a;
    }

    public function del(IndexingConfig $a): IndexingConfig
    {
        $this->_em->remove($a);
        $this->_em->flush();

        return $a;
    }
}
