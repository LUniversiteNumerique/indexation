<?php

namespace App\Security\Voter;

use App\Entity\{Etablissement, Notice, NoticEtat, User};
use Symfony\Component\Security\Core\{Authentication\Token\TokenInterface,Authorization\Voter\Voter,User\UserInterface};
use Symfony\Bundle\SecurityBundle\Security;

class NoticeActionVoter extends Voter
{
    public const VALI = 'NOTICE_VALI';
    public const VIEW = 'NOTICE_VIEW';
    public const EDIT = 'NOTICE_EDIT';
    public const DROP = 'NOTICE_DROP';
    public const DEFA = 'NOTICE_DEFA';

    public function __construct(private readonly Security $security){}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DROP, self::VALI, self::DEFA]) && $subject instanceof Notice;
    }

    /**
     * @param string $attribute
     * @param Notice $subject
     * @param TokenInterface $token
     * @return bool
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var User $user */ $user = $token->getUser();
        if ($user instanceof UserInterface) return match($attribute) {
            self::VIEW => $this->canView($subject, $user),
            self::EDIT, self::DROP => $this->canEdit($subject, $user),
            self::VALI => $this->canVali($subject, $user),
            default => $this->canUser($subject, $user),
        };
        return false;
    }

    private function canView(Notice $subject, User $user): bool
    {
        if ($this->hasEtat($subject)) return $this->isOwner($subject,$user) || $this->canUser($subject, $user);
        else return $this->canVali($subject, $user) || $this->canUser($subject, $user);
    }
    
    private function canEdit(Notice $subject, User $user): bool
    {
        if ($this->hasEtat($subject)) return $this->isOwner($subject,$user);
        else return $this->hasEtat($subject,NoticEtat::Forward) && $this->canVali($subject, $user);
    }

    private function canVali(Notice $subject, User $user): bool
    {
        //Si Admin je peux voir toutes les notices
        //Si je suis DOCUMENTALISTE je peux voir si c'est dans mon UNT, cad si la specialite de la notice est dans les champs disciplinaires de mon UNT

        return $this->security->isGranted('ROLE_VALI_NOTI') &&
            (!$user->getUntheme() || $subject->belongsToUNT($user->getUntheme()));
    }

    private function canUser(Notice $subject, User $user): bool
    {
        //Si je suis CONTRIBUTEUR je peux voir que si c'est à mon établissement.
        return $this->security->isGranted('ROLE_READ_NOTI') && $user->getSchool() instanceof Etablissement && $subject->getPorteurs()->contains($user->getSchool());
    }

    private function isOwner(Notice $subject, User $user): bool {
        return $subject->getCreateur() === $user;
    }

    private function hasEtat($subject, $etat = NoticEtat::Working): bool {
        return $subject->getEtat() === $etat;
    }
}
