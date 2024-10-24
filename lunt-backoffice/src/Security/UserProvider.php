<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\{UnsupportedUserException, UserNotFoundException};
use Symfony\Component\Security\Core\User\{PasswordAuthenticatedUserInterface,PasswordUpgraderInterface,UserInterface,UserProviderInterface};

readonly class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(private UserRepository $repository) {}

    public function loadUserByIdentifier(string $identifier): User
    {
        $user = $this->repository->createQueryBuilder('u')
            ->select('u,g,e,unt,f')->join('u.group', 'g')
            ->leftJoin('u.school', 'e')->leftJoin('u.untheme', 'unt')->leftJoin('unt.fields', 'f')
            ->where('u.email = :username')->setParameter('username', $identifier)
            ->getQuery()->getOneOrNullResult();

        if (!$user)
            throw new UserNotFoundException();
        return $user;
    }

    public function refreshUser(UserInterface $user): User
    {
        if (!$user instanceof User) throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        return $this->loadUserByIdentifier($user->getUserIdentifier());
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