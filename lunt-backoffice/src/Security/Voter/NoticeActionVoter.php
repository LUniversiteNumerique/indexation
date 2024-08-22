<?php

namespace App\Security\Voter;

use App\Entity\{Notice, NoticEtat, User};
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
            self::VIEW => $this->canEdit($subject, $user),
            self::EDIT => ($subject->getEtat() === NoticEtat::Working && $this->canEdit($subject, $user))||($this->canView() && $subject->getEtat() !== NoticEtat::Working),
            self::DROP => ($subject->getEtat() === NoticEtat::Working && $this->canEdit($subject, $user))||($this->canView() && $subject->getEtat() === NoticEtat::Forward),
            self::VALI => $this->canView(),
            default => throw new \LogicException('This code should not be reached!')
        };
        return false;
    }

    private function canEdit(Notice $subject, User $user): bool
    {
        return $subject->getPorteurs()->contains($user->getSchool()); //$user === $subject->getCreateur();
    }

    private function canView(string $role = 'ROLE_VALI_NOTI'): bool
    {
        return $this->security->isGranted($role);
    }
}
