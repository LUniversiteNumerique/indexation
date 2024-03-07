<?php

namespace App\Repository;

use App\Entity\{Etablissement,User};
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\{PasswordAuthenticatedUserInterface,PasswordUpgraderInterface};

/**
 * @extends ServiceEntityRepository<User>
 *
 * @implements PasswordUpgraderInterface<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findWithField(int $page,Etablissement $etab = null, array $disc = []): array
    {
        $qr = $this->createQueryBuilder('u')->select('u,f')->leftJoin('u.fields', 'f');
        if(!empty($disc))
            foreach ($disc as $option => $key) $qr->andWhere(":in$key MEMBER OF u.untheme.fields")->setParameter("in$key", $option);
        elseif($etab) $qr->andWhere("u.school = :etab")->setParameter("etab", $etab);

        return $qr->getQuery()->getResult();
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User)
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        $this->add($user->setPassword($newHashedPassword));
    }

    public function add(User $u=null): ?User
    {
        if($u) $this->_em->persist($u);
        $this->_em->flush();
        return $u;
    }

    public function del(User $u): User
    {
        $this->_em->remove($u);
        $this->_em->flush();

        return $u;
    }
}
