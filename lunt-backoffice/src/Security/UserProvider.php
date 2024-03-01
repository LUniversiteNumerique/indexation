<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Security\Core\Exception\{UnsupportedUserException,UserNotFoundException};
use Symfony\Component\Security\Core\User\{PasswordAuthenticatedUserInterface,PasswordUpgraderInterface,UserInterface,UserProviderInterface};

readonly class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(private UserRepository $repository) {}

    /**
     * Symfony calls this method if you use features like switch_user
     * or remember_me. If you're not using these features, you do not
     * need to implement this method.
     *
     * @throws UserNotFoundException if the user is not found
     * @throws NonUniqueResultException
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->repository->createQueryBuilder('u')
            ->select('u,g,e,f')->join('u.group', 'g')
            ->leftJoin('u.school','e')->leftJoin('u.fields','f')
            ->where('u.email = :username')->setParameter('username', $identifier)
            ->getQuery()->getOneOrNullResult();
    }

    /**
     * Refreshes the user after being reloaded from the session.
     *
     * When a user is logged in, at the beginning of each request, the
     * User object is loaded from the session and then this method is
     * called. Your job is to make sure the user's data is still fresh by,
     * for example, re-querying for fresh User data.
     *
     * If your firewall is "stateless: true" (for a pure API), this
     * method is not called.
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        try {
            $rur = $this->loadUserByIdentifier($user->getUserIdentifier());
        } catch (NonUniqueResultException $e) {
            throw new UserNotFoundException(sprintf('User with id %s not found', json_encode($user->getId())));
        }
        return $rur;
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        $this->repository->upgradePassword($user,$newHashedPassword);
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}