<?php

namespace App\Repository;

use App\Entity\{IndexingConfig, Notice, NoticEtat, User};
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    /**
     * Supprime définitivement les notices supprimées et non publiées créées avant une date donnée.
     *
     * @param DateTime $before Date limite : seules les notices créées avant cette date seront supprimées.
     * @return int Nombre de notices supprimées.
     */
    public function clean(DateTime $before): int
    {
        return $this->createQueryBuilder('n')
            ->where('n.deleted = 1 AND n.publieLe IS NULL')
            ->andWhere('n.creeLe < :date')
            ->setParameter('date', $before)
            ->delete(Notice::class, 'n')
            ->getQuery()
            ->execute();
    }

    /**
     * Retourne une liste de notices selon la configuration d'indexation et la pagination.
     *
     * @param IndexingConfig $config Configuration d'indexation (mode, champs, date).
     * @param int $limit Nombre maximum de résultats à retourner.
     * @param int $offset Décalage pour la pagination.
     * @return Notice[] Liste des notices trouvées.
     */
    public function findFrom(IndexingConfig $config, int $limit = 20, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('n')
            ->join('n.disciplineGroups', 'dg')
            ->leftJoin('n.codeweys', 'e')
            ->leftJoin('n.ressources', 'r')
            ->leftJoin('n.tags', 'k')
            ->leftJoin('n.porteurs', 'p')
            ->leftJoin('n.auteurs', 'a')
            ->leftJoin('n.niveaux', 't')
            ->leftJoin('n.docTypes', 'dd')
            ->leftJoin('n.pedTypes', 'pp')
            ->join('n.droit', 'l')
            ->join('dg.champDisc', 'cd')
            ->where('n.etat = :etat')
            ->distinct();

        if (!$config->isFullMode()) {
            $qb->andWhere('n.publieLe IS NULL AND n.editeLe > :date')
                ->setParameter('date', $config->getScheduleAt());
        }

        $qb->orWhere('n.etat != :etat AND n.publieLe IS NOT NULL')
            ->andWhere('cd IN (:fields)')
            ->setParameter('fields', $config->getIndexCore()?->getFields())
            ->setParameter('etat', NoticEtat::Approved)
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne les dernières notices accessibles à l'utilisateur, selon son rôle et ses restrictions.
     *
     * @param User $user L'utilisateur pour lequel filtrer les notices.
     * @param int $limit Nombre maximum de notices à retourner.
     * @return Notice[] Liste des notices trouvées.
     */
    public function findLatestBy(User $user, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('n')
            ->select('n, a, e, dg, s, dgw, dpw, dp')
            ->leftJoin('n.auteurs', 'a')
            ->leftJoin('n.codeweys', 'e')
            ->leftJoin('n.disciplineGroups', 'dg')
            ->leftJoin('dg.specialites', 's')
            ->leftJoin('n.deweyGroups', 'dgw')
            ->leftJoin('dgw.codeweys', 'dpw')
            ->leftJoin('n.deweyPersos', 'dp')
            ->where('n.deleted = 0 AND n.etat = :etat')
            ->setParameter('etat', NoticEtat::Approved);

        $this->applyUserRestrictions($qb, $user);

        return $qb->orderBy('n.creeLe', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Ajoute une notice en base de données et retourne l'objet ajouté.
     *
     * @param Notice|null $notice La notice à ajouter.
     * @return Notice|null La notice ajoutée, ou null si aucun objet n'est passé.
     */
    public function add(?Notice $notice): ?Notice
    {
        if ($notice) {
            $this->_em->persist($notice);
        }
        $this->_em->flush();
        return $notice;
    }

    /**
     * Supprime une notice de la base de données et retourne l'objet supprimé.
     *
     * @param Notice|null $notice La notice à supprimer.
     * @return Notice|null La notice supprimée, ou null si aucun objet n'est passé.
     */
    public function del(?Notice $notice): ?Notice
    {
        if ($notice) {
            $this->_em->remove($notice);
            $this->_em->flush();
        }
        return $notice;
    }

    /**
     * Retourne le nombre de notices par état pour un utilisateur, éventuellement filtré par date.
     *
     * @param User $user L'utilisateur pour lequel filtrer les notices.
     * @param string|null $date Un intervalle de temps (ex: 'month', 'week') pour ne compter que les notices récentes.
     * @return array Liste des états et leur nombre de notices.
     */
    public function countByEtat(User $user, ?string $date = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->where('n.deleted = 0')
            ->join('n.disciplineGroups', 'dg');

        $this->applyUserRestrictions($qb, $user);


        if ($date) {
            $qb->andWhere('n.creeLe > :date')
                ->setParameter('date', new DateTime("-1 $date"));
        }

        return $qb->groupBy('n.etat')
            ->select('n.etat, COUNT(DISTINCT n.id) as nombre')
            ->getQuery()
            ->getResult();
    }

    /**
     * Enrichit le QueryBuilder EasyAdmin avec les jointures, sélections et restrictions métier selon l'utilisateur.
     *
     * Ajoute toutes les jointures nécessaires pour l'affichage en index EasyAdmin,
     * applique le filtrage métier selon le rôle et les droits de l'utilisateur.
     *
     * @param QueryBuilder $qb Le QueryBuilder initial (généré par EasyAdmin).
     * @param User $user L'utilisateur pour lequel filtrer les notices.
     * @return QueryBuilder      Le QueryBuilder enrichi et filtré.
     */
    public function enrichIndexQueryBuilder(QueryBuilder $qb, User $user): QueryBuilder
    {
        $qb->andWhere('entity.deleted = 0');

        // Applique les restrictions selon le rôle utilisateur
        $this->applyUserRestrictions($qb, $user, 'entity');
        return $qb;
    }

    /**
     * Applique les restrictions d'accès aux notices selon le rôle de l'utilisateur.
     *
     * - Administrateur : ses propres notices, ou toutes les notices soumises et validées.
     * - Contributeur : ses propres notices, ou celles soumises et validées de son établissement.
     * - Documentaliste : ses propres notices, ou celles soumises et validées liées à son UNT.
     * - Autres : uniquement ses propres notices.
     *
     * Modifie le QueryBuilder en ajoutant les conditions nécessaires.
     *
     * @param QueryBuilder $qb Le QueryBuilder à enrichir.
     * @param User $user L'utilisateur pour lequel filtrer les notices.
     * @param string $alias
     * @return void
     */
    private function applyUserRestrictions(QueryBuilder $qb, User $user, string $alias = 'n'): void
    {
        $role = $user->getGroup()->getLabel();
        $userId = $user->getId();
        $school = $user->getSchool();
        $unt = $user->getUntheme();

        if ($role === 'Administrateur') {
            // Ses propres notices OU les notices soumises et validées
            $qb->andWhere(
                $qb->expr()->orX(
                    $alias . '.createur = :user',
                    $alias . '.etat IN (:etats)'
                )
            )
                ->setParameter('user', $userId)
                ->setParameter('etats', [NoticEtat::Forward, NoticEtat::Approved]);
        } elseif ($role === 'Contributeur' && $school) {
            // Ses propres notices OU celles soumises et validées de son établissement
            $qb->andWhere(
                $qb->expr()->orX(
                    $alias . '.createur = :user',
                    $qb->expr()->andX(
                        ":school MEMBER OF $alias.porteurs",
                        $alias . '.etat IN (:etats)'
                    )
                )
            )
                ->setParameter('user', $userId)
                ->setParameter('school', $school)
                ->setParameter('etats', [NoticEtat::Forward, NoticEtat::Approved]);
        } elseif ($role === 'Documentaliste' && $unt) {
            // Ses propres notices OU celles soumises et validées liées à son UNT
            $qb->join('dg.champDisc', 'cd')
                ->andWhere(
                    $qb->expr()->orX(
                        $alias . '.createur = :user',
                        $qb->expr()->andX(
                            'cd IN (:champs)',
                            $alias . '.etat IN (:etats)'
                        )
                    )
                )
                ->setParameter('user', $userId)
                ->setParameter('champs', $unt->getFields())
                ->setParameter('etats', [NoticEtat::Forward, NoticEtat::Approved]);
        } else {
            // Seulement ses propres notices
            $qb->andWhere($alias . '.createur = :user')
                ->setParameter('user', $userId);
        }
    }
}
