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

    public function __construct(private readonly Security $security){}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DROP, self::VALI]) && $subject instanceof Notice;
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
            self::EDIT => $this->canEdit($subject, $user),
            self::DROP => $this->canDrop($subject, $user),
            self::VALI => $this->canVali($subject, $user),
            default => throw new \LogicException('This code should not be reached!')
        };
        return false;
    }

    private function canView(Notice $subject, User $user): bool
    {
        //Si Admin je peux voir toutes les notices
        //Si je suis DOCUMENTALISTE je peux voir si c'est dans mon UNT, cad si la specialite de la notice est dans les champs disciplinaires de mon UNT
        if($this->security->isGranted('ROLE_VALI_NOTI'))
            return !$user->getUntheme() || $subject->belongsToUNT($user->getUntheme());

        //Si je suis CONTRIBUTEUR je peux voir que si c'est à mon établissement.
        return (
            $this->security->isGranted('ROLE_READ_NOTI') &&
            $user->getSchool() instanceof Etablissement &&
            $subject->getPorteurs()->contains($user->getSchool())
        );
    }
    
    private function canEdit(Notice $subject, User $user): bool
    {
        if($this->security->isGranted('ROLE_VALI_NOTI'))
            return !$user->getUntheme() || $subject->belongsToUNT($user->getUntheme());

        //Si je suis CONTRIBUTEUR je peux editer si c'est à mon établissement et que c'est en working state
        return (
            $this->security->isGranted('ROLE_READ_NOTI') &&
            $subject->getPorteurs()->contains($user->getSchool()) &&
            $subject->getEtat() === NoticEtat::Working
        );
    }

    private function canDrop($subject, $user): bool {
        if($subject->getEtat() === NoticEtat::Forward && $this->security->isGranted('ROLE_VALI_NOTI'))
            return !$user->getUntheme() || $subject->belongsToUNT($user->getUntheme());
        return ($subject->getEtat() == NoticEtat::Working && $subject->getCreateur() === $user);
    }

    /* Correspond à demande soumission de la notice par exemple 
    */
    private function canVali(Notice $subject, User $user): bool
    {
        if($this->security->isGranted('ROLE_VALI_NOTI'))
            return (!$user->getUntheme() || $subject->belongsToUNT($user->getUntheme()));
        //TODO : tu peux que demander une rectification
        return false;
    }
}
