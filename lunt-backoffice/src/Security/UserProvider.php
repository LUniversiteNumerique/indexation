<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Security\Core\Exception\{UnsupportedUserException, UserNotFoundException};
use Symfony\Component\Security\Core\User\{PasswordAuthenticatedUserInterface, PasswordUpgraderInterface, UserInterface, UserProviderInterface};

/**
 * Fournisseur d'utilisateurs personnalisé pour l'authentification Symfony.
 */
readonly class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    /**
     * @param UserRepository $repository Le repository des utilisateurs.
     */
    public function __construct(private UserRepository $repository) {}

    /**
     * Charge un utilisateur à partir de son identifiant (email).
     *
     * @param string $identifier L'identifiant de l'utilisateur.
     * @return User L'utilisateur trouvé.
     * @throws UserNotFoundException|NonUniqueResultException Si aucun utilisateur n'est trouvé.
     */
    public function loadUserByIdentifier(string $identifier): User
    {
        $user = $this->repository->createQueryBuilder('u')
            ->select('u,g,e,unt,f')
            ->join('u.group', 'g')
            ->leftJoin('u.school', 'e')
            ->leftJoin('u.untheme', 'unt')
            ->leftJoin('unt.fields', 'f')
            ->where('u.email = :username')
            ->setParameter('username', $identifier)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            throw new UserNotFoundException();
        }
        return $user;
    }

    /**
     * Rafraîchit l'utilisateur depuis la base de données.
     *
     * @param UserInterface $user L'utilisateur à rafraîchir.
     * @return User L'utilisateur rafraîchi.
     * @throws NonUniqueResultException
     */
    public function refreshUser(UserInterface $user): User
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    /**
     * Met à jour le mot de passe hashé de l'utilisateur.
     *
     * @param PasswordAuthenticatedUserInterface $user L'utilisateur.
     * @param string $newHashedPassword Le nouveau mot de passe hashé.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        $this->repository->upgradePassword($user, $newHashedPassword);
    }

    /**
     * Vérifie si la classe donnée est supportée par ce provider.
     *
     * @param string $class Le nom de la classe.
     * @return bool
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}