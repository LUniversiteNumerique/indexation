<?php

namespace App\Repository;

use App\Entity\{IndexingConfig, Notice, NoticEtat, Univerique, User};
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
        $qr = $this->createQueryBuilder('n')->leftJoin('n.specialites', 's')
            ->join('s.parent', 'u')->where('n.etat = :etat');
        if(!$cfg->isFullMode()) $qr->andWhere('n.publieLe is null OR n.editeLe > :date')->setParameter('date', $cfg->getScheduleAt());
        $qr->orWhere('n.etat != :etat AND n.publieLe is not null');

        return $qr->andWhere("u.parent = :core")
            ->setParameter("core", $cfg->getIndexCore())->setParameter("etat", NoticEtat::Approved)
            ->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();
    }

    public function findLatestBy(User $user, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('n')->select('n,a,e,s')
            ->leftJoin('n.auteurs','a')->leftJoin('n.codeweys','e')->leftJoin('n.specialites', 's')
            ->where('n.deleted = 0 AND n.etat = :etat')->setParameter('etat', NoticEtat::Approved);

        if ($sch = $user->getSchool()) $qb->andWhere(':school MEMBER OF n.porteurs')->setParameter('school', $sch->getId());
        elseif ($unt = $user->getUntheme()) $qb->join('s.parent', 'u')->andWhere('u.parent in (:champs)')->setParameter('champs', $unt->getFields());

        return $qb->orderBy('n.creeLe', 'DESC')->setMaxResults($limit)->getQuery()->getResult();
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

    public function countByEtat(User $user, ?string $date = null)
    {
        $qb = $this->forUser($user);
        if($date) $qb->andWhere('n.creeLe > :date')->setParameter('date', new \DateTIME("-1 $date"));

        return $qb->groupBy('n.etat')->select('n.etat, COUNT(n.id) as nombre')->getQuery()->getResult();
    }

    private function forUser(User $u): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('n')->leftJoin('n.specialites', 's')
            ->join('s.parent', 'u')->where('n.deleted = 0');
        $andX = $qb->expr()->andX('n.etat != :etat');

        if ($sch = $u->getSchool()) {
            $qb->setParameter('school', $sch->getId());
            $andX->add(':school MEMBER OF n.porteurs');
        } elseif ($unt = $u->getUntheme()) {
            $qb->setParameter('champs', $unt->getFields());
            $andX->add('u.parent in (:champs)');
        }

        return $qb->andWhere($qb->expr()->orX(
            $qb->expr()->eq('n.createur',':user'), $andX
        ))
            ->setParameter('user', $u->getId())
            ->setParameter('etat', NoticEtat::Working);
    }
}
