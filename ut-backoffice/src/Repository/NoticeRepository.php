<?php

namespace App\Repository;

use App\Entity\{Etablissement,Notice};
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notice>
 *
 * @method Notice|null find($id, $lockMode = null, $lockVersion = null)
 * @method Notice|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notice[]    findAll()
 * @method Notice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NoticeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notice::class);
    }

    public function findAllorBy(int $page,Etablissement $etab = null): array
    {
         $qr = $this->createQueryBuilder('n')
             ->select('n,a,d,p,u,t,l,s,q')->leftJoin('n.ressources', 'r')
             ->join('n.droit', 'l')->join('n.porteurs', 'q')
             ->join('n.specialite', 's')->join('n.auteurs', 'a')
            ->join('n.docTypes', 'd')->join('n.pedTypes', 'p')
            ->join('n.niveaux', 'u')->join('n.tags', 't');
        if($etab) $qr->andWhere(":etab MEMBER OF n.porteurs")->setParameter("etab", $etab);

        return $qr->getQuery()->getResult();
    }

    public function add(Notice $n=null): ?Notice
    {
        if($n) $this->_em->persist($n);
        $this->_em->flush();
        return $n;
    }

    public function del(?Notice $n): Notice
    {
        $this->_em->remove($n);
        $this->_em->flush();
        return $n;
    }
}
