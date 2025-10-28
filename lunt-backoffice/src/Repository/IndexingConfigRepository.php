<?php

namespace App\Repository;

use App\Entity\IndexingConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}