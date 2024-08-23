<?php

namespace App\Repository;

use App\Entity\Keyword;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Keyword>
 *
 * @method Keyword|null find($id, $lockMode = null, $lockVersion = null)
 * @method Keyword|null findOneBy(array $criteria, array $orderBy = null)
 * @method Keyword[]    findAll()
 * @method Keyword[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class KeywordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Keyword::class);
    }

    /**
     * @param $value
     * @return Keyword|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneByLabel($value): ?Keyword
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.nom = :val')
            ->setParameter('val', $value)
            ->getQuery()->getOneOrNullResult();
    }

    public function searchQB(string $value = null): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')->where('t.valide = 1');
        if($value) $qb->andWhere('t.nom LIKE :o')->setParameter('o', "%".$value."%");
        return $qb;
    }

    public function search(string $value = null): array
    {
        return $this->searchQB($value)->orderBy('t.id', 'DESC')->getQuery()->getResult();
    }

    public function clean(\DateTime $before): int
    {
        return $this->createQueryBuilder('n')
            ->where('n.valide = 0 AND n.creeLe < :date')
            ->setParameter('date', $before)
            ->delete(Keyword::class, 'n')
            ->getQuery()->execute();
    }

    public function findUnusedTags($class, $limit = 12)
    {
        $em = $this->getEntityManager();
        $sm = new ResultSetMappingBuilder($em);
        $sm->addRootEntityFromClassMetadata(Keyword::class, 't');
        return $em->createNativeQuery('
                    SELECT t.id, t.nom 
                    FROM keyword t
                    LEFT JOIN notice_keyword p on t.id = p.keyword_id
                    WHERE p.keyword_id is null 
                   ', $sm)->getResult();
    }

    public function add(Keyword $u=null): ?Keyword
    {
        if($u) $this->_em->persist($u);
        $this->_em->flush();
        return $u;
    }

    public function del(Keyword $u): Keyword
    {
        $this->_em->remove($u);
        $this->_em->flush();

        return $u;
    }
}
