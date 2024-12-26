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
            self::VIEW => $this->canView($subject, $user),
            self::EDIT => $this->canEdit($subject, $user),
            self::DROP => $this->canDrop($subject, $user),
            self::VALI => $this->canValidate($subject, $user),
            default => throw new \LogicException('This code should not be reached!')
        };
        return false;
    }

    private function canDrop(): bool {
        if($this->security->isGranted('ROLE_READ_UNIV')) {
            //Si Admin je peux tout faire 
            return true;
        }
        //($subject->getEtat() === NoticEtat::Working && $this->canEdit($subject, $user))||($this->canView() && $subject->getEtat() === NoticEtat::Forward)
        return false;
    }

    private function canView(Notice $subject, User $user): bool
    {
        if($this->security->isGranted('ROLE_READ_UNIV')) {
            //Si Admin je peux voir toutes les notices
            return true;
        } elseif ($this->security->isGranted('ROLE_VALI_NOTI')) {
            //Si je suis DOCUMENTALISTE 
            //je peux voir si c'est dans mon UNT
            //cad si la specialite de la notice est dans les themes de mon UNT
            
            //Get theme de l'UNT de l'utilisateur
            $userFields = []; 
            foreach($user->getUntheme()->getFields() as $discipline) {
                $userFields[] = $discipline->getId();
            } 
            //Get theme parent de la notice
            $noticeGrandParentId = $subject->getSpecialite()->getParent()->getParent()->getId();
            
            //Verification notice dans l'UNT du User
            if(in_array($noticeGrandParentId,$userFields)) { 
                return true;
            } else {
                return false;
            }
        } elseif($this->security->isGranted('ROLE_READ_NOTI')) {
            //Si je suis CONTRIBUTEUR
            //je peux voir que si c'est à moi.
            if($subject->getCreateur() == $user) {
                return true; 
            } else {
                return false;                
            }
        } 
    }

    
    private function canEdit(Notice $subject, User $user): bool
    {
        
        if($this->security->isGranted('ROLE_READ_UNIV')) {
            //Si Admin je peux tout faire 
            return true;
        } elseif ($this->security->isGranted('ROLE_VALI_NOTI')) {
            //Si je suis DOCUMENTALISTE 
            //je peux editer si c'est dans mon UNT
            //cad si la specialite de la notice est dans les themes de mon UNT
            
            //Get theme de l'UNT de l'utilisateur
            $userFields = []; 
            foreach($user->getUntheme()->getFields() as $discipline) {
                $userFields[] = $discipline->getId();
            } 
            //Get theme parent de la notice
            $noticeGrandParentId = $subject->getSpecialite()->getParent()->getParent()->getId();
            
            //Verification notice dans l'UNT du User
            if(in_array($noticeGrandParentId,$userFields)) { 
                return true;
            } else {
                return false;
            }
        } elseif($this->security->isGranted('ROLE_READ_NOTI')) {
            //Si je suis CONTRIBUTEUR
            
            //je peux editer si c'est à moi et que c'est en working state
            if($subject->getCreateur() == $user && $subject->getEtat() == NoticEtat::Working) {
                return true; 
            } else {
                return false;                
            }
        } 
    }

    /* Correspond à demande soumission de la notice par exemple 
    */
    private function canValidate(Notice $subject, User $user): bool
    {

        if($this->security->isGranted('ROLE_READ_UNIV')) {
            //Si admin 
            //tu peux faire ce que tu veux
            return true;

        } elseif($this->security->isGranted('ROLE_VALI_NOTI')) {
            //Si Docu 
            //Il faut le role ROLE_VALI_NOTI
            //et il faut que la notice soit dans ton UNT 
            if($subject->belongsToUniverique($user->getUntheme())) { 
                return true; 
            } else { 
                return false; 
            }
        } else {
            //TODO : tu peux que demander une rectification 
           return false;
        }
    }
}
