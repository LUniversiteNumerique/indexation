<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\{AccountExpiredException, CustomUserMessageAccountStatusException};
use Symfony\Component\Security\Core\User\{UserCheckerInterface, UserInterface};

/**
 * Vérifie l'état du compte utilisateur avant et après l'authentification.
 */
class UserChecker implements UserCheckerInterface
{
    /**
     * Vérifie avant l'authentification si l'utilisateur existe dans le système.
     *
     * @param UserInterface $user
     * @throws AccountExpiredException Si l'utilisateur n'est pas une instance de User.
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) throw new AccountExpiredException("Ce compte n'existe pas dans le système.");
    }

    /**
     * Vérifie après l'authentification si le compte utilisateur est actif.
     *
     * @param UserInterface $user
     * @throws CustomUserMessageAccountStatusException Si le compte est inactif.
     */
    public function checkPostAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->isEnabled())
            throw new CustomUserMessageAccountStatusException("Votre compte est inactif, veuillez contacter l'admin.");
    }
}