<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\{AccountExpiredException,CustomUserMessageAccountStatusException};
use Symfony\Component\Security\Core\User\{UserCheckerInterface,UserInterface};

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) throw new AccountExpiredException("Ce compte n'existe pas dans le système.");
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->isEnabled())
            throw new CustomUserMessageAccountStatusException("Votre compte est inactif, veuillez contacter l'admin.");
    }
}