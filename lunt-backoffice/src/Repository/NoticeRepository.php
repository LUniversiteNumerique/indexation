<?php

namespace App\Repository;

use App\Entity\{IndexingConfig, Notice, NoticEtat, Univerique};
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

    public function clean(\DateTime $before): int
    {
        return $this->createQueryBuilder('n')
            ->where('n.deleted = 1 AND n.publieLe is null')
            ->andWhere('n.creeLe < :date')->setParameter('date', $before)
            ->delete(Notice::class, 'n')
            ->getQuery()->execute();
    }

    public function findFrom(IndexingConfig $cfg, int $limit, int $offset): array
    {
        $qr = $this->createQueryBuilder('n')->join('n.validateur', 'u')->where('n.etat = :etat');
        if(!$cfg->isFullMode()) $qr->andWhere('n.publieLe is null OR n.editeLe > :date')->setParameter('date', $cfg->getScheduleAt());
        $qr->orWhere('n.etat != :etat AND n.publieLe is not null');

        return $qr->andWhere("u.untheme = :core")
            ->setParameter("core", $cfg->getIndexCore())->setParameter("etat", NoticEtat::Approved)
            ->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();
    }

    public function findByIds(array $uuids): array
    {
        $qr = $this->getJoin()
            ->where('n.id IN (:uuids)')->setParameter('uuids', $uuids);
        return $qr->getQuery()->getResult();
    }

    public function findAllorBy(int $etab = null): array
    {
        $qr = $this->getJoin();
        if($etab) $qr->andWhere("n.repertoire = :etab")->setParameter("etab", $etab);

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

    public function countByEtat(?Univerique $unt, ?string $date = null)
    {
        $qb = $this->createQueryBuilder('n')->select('n.etat, COUNT(n.id) as nombre')->where('n.deleted = 0');
        if ($unt) $qb->join('n.specialite','d')->join('d.parent','c')
            ->andWhere('c.parent in (:champs)')->setParameter('champs',$unt->getFields());
        if($date) $qb->andWhere('n.creeLe > :date')->setParameter('date', new \DateTIME("-1 $date"));
        return $qb->groupBy('n.etat')->getQuery()->getResult();
    }

    private function getJoin(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('n')->select('n,a,d,p,u,t,l,s,q')
            ->where('n.deleted = 0')->leftJoin('n.ressources', 'r')
            ->join('n.droit', 'l')->join('n.porteurs', 'q')
            ->join('n.specialite', 's')->join('n.auteurs', 'a')
            ->join('n.docTypes', 'd')->join('n.pedTypes', 'p')
            ->join('n.niveaux', 'u')->join('n.tags', 't');
    }
}
