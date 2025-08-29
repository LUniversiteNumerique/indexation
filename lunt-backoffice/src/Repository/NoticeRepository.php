<?php

namespace App\Repository;

use Doctrine\ORM\Tools\Pagination\Paginator;
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

    public function findFrom(IndexingConfig $cfg, int $limit=20, int $offset=0): array
    {
        $qr = $this->createQueryBuilder('n')->join('n.disciplineGroups','dg')
            ->leftJoin('n.codeweys','e')->leftJoin('n.ressources','r')->leftJoin('n.tags','k')
            ->leftJoin('n.porteurs','p')->leftJoin('n.auteurs','a')->leftJoin('n.niveaux','t')
            ->leftJoin('n.docTypes','dd')->leftJoin('n.pedTypes','pp')->join('n.droit','l')
            ->join('dg.champDisc', 'cd')->where('n.etat = :etat')->distinct();
        if(!$cfg->isFullMode()) $qr->andWhere('n.publieLe is null AND n.editeLe > :date')->setParameter('date', $cfg->getScheduleAt());
        $qr->orWhere('n.etat != :etat AND n.publieLe is not null');

        return $qr->andWhere("cd IN (:fields)")
            ->setParameter("fields", $cfg->getIndexCore()?->getFields())->setParameter("etat", NoticEtat::Approved)
            ->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();
    }

  public function findLatestBy(User $user, int $limit = 20): array
  {
    $qb = $this->createQueryBuilder('n')
      ->select('n,a,e,dg,s,dgw,dpw,dp')
      ->leftJoin('n.auteurs','a')
      ->leftJoin('n.codeweys','e')
      ->leftJoin('n.disciplineGroups', 'dg')
      ->leftJoin('dg.specialites', 's')
      ->leftJoin('n.deweyGroups', 'dgw')
      ->leftJoin('dgw.codeweys', 'dpw')
      ->leftJoin('n.deweyPersos', 'dp')
      ->where('n.deleted = 0 AND n.etat = :etat')
      ->setParameter('etat', NoticEtat::Approved);

    $school = $user->getSchool();
    $unt = $user->getUntheme();
    $role = $user->getGroup()->getLabel();

    if ($role === 'Administrateur') {
      // Administrateur => toutes les notices
    }
    elseif ($role === 'Contributeur') {
      if ($school) {
        $qb->andWhere($qb->expr()->orX(
          $qb->expr()->eq('n.createur', ':user'),
          ':school MEMBER OF n.porteurs'
        ))
          ->setParameter('user', $user->getId())
          ->setParameter('school', $school);
      } else {
        // pas de school => seulement ses propres notices
        $qb->andWhere('n.createur = :user')
          ->setParameter('user', $user->getId());
      }
    }
    elseif ($role === 'Documentaliste') {
      if ($unt) {
        $qb->join('dg.champDisc', 'cd')
          ->andWhere($qb->expr()->orX(
            $qb->expr()->eq('n.createur', ':user'),
            'cd IN (:champs)'
          ))
          ->setParameter('user', $user->getId())
          ->setParameter('champs', $unt->getFields());
      } else {
        // pas d'UNT => seulement ses propres notices
        $qb->andWhere('n.createur = :user')
          ->setParameter('user', $user->getId());
      }
    }

    return $qb->orderBy('n.creeLe', 'DESC')
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult();
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

        return $qb->groupBy('n.etat')->select('n.etat, COUNT(DISTINCT n.id) as nombre')->getQuery()->getResult();
    }

    private function forUser(User $u): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('n')->where('n.deleted = 0');
        $andX = $qb->expr()->andX('n.etat != :etat');

      $sch = $u->getSchool();
      $unt = $u->getUntheme();
      $role = $u->getGroup()->getLabel();
      $hasRestriction = false;

      if ($role === 'Administrateur') {
        // Administrateur => toutes les notices
      }
      elseif ($role === 'Contributeur') {
        if ($sch) {
          $qb->setParameter('school', $sch);
          $andX->add(':school MEMBER OF n.porteurs');
          $hasRestriction = true;
        } else {
          // pas de school => seulement ses propres notices
          $qb->andWhere('n.createur = :user')
            ->setParameter('user', $u->getId());
        }
      }
      elseif ($role === 'Documentaliste') {
        if ($unt) {
          $qb->join('n.disciplineGroups', 'dg')->join('dg.champDisc', 'cd')
            ->setParameter('champs', $unt->getFields());
          $andX->add('cd IN (:champs)');
          $hasRestriction = true;
        } else {
          // pas d'UNT => seulement ses propres notices
          $qb->andWhere('n.createur = :user')
            ->setParameter('user', $u->getId());
        }
      }
      if ($hasRestriction) {
        $qb->andWhere($qb->expr()->orX(
          $qb->expr()->eq('n.createur', ':user'),
          $andX
        ))
          ->setParameter('user', $u->getId())
          ->setParameter('etat', NoticEtat::Working);
      }
      return $qb;
    }
}
