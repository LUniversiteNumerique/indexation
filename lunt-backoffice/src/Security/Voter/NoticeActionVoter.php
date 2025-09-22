<?php

namespace App\Security\Voter;

use App\Entity\{Notice, NoticEtat, User};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class NoticeActionVoter extends Voter
{
    public const VALI = 'NOTICE_VALI';
    public const VIEW = 'NOTICE_VIEW';
    public const EDIT = 'NOTICE_EDIT';
    public const DROP = 'NOTICE_DROP';
    public const DEFA = 'NOTICE_DEFA';

    public function __construct(private readonly Security $security){}

    /**
     * Détermine si le voter prend en charge l'attribut et le sujet donnés.
     *
     * @param string $attribute L'action à vérifier (ex : VIEW, EDIT, DROP, VALI, DEFA)
     * @param mixed $subject L'objet concerné (doit être une instance de Notice)
     * @return bool true si le voter gère cette combinaison, false sinon
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
                self::VIEW, self::EDIT, self::DROP, self::VALI, self::DEFA
            ]) && $subject instanceof Notice;
    }

    /**
     * Applique la logique de vote selon l'attribut, le sujet et l'utilisateur.
     *
     * @param string $attribute L'action à vérifier (ex : VIEW, EDIT, DROP, VALI, DEFA)
     * @param mixed $subject L'objet concerné (doit être une instance de Notice)
     * @param TokenInterface $token Le token d'authentification de l'utilisateur
     * @return bool true si l'accès est autorisé, false sinon
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var User $user */
        $user = $token->getUser();
        if (!$user instanceof User) return false;

        return match ($attribute) {
            self::VIEW => $this->canView($subject, $user),
            self::EDIT, self::DROP => $this->canEdit($subject, $user),
            self::VALI => $this->canVali($subject, $user),
            default => $this->canUser($subject, $user),
        };
    }

    /**
     * Vérifie si l'utilisateur peut voir la notice.
     *
     * Règles d'accès :
     * - Accès autorisé si l'utilisateur est le créateur de la notice.
     * - Accès autorisé selon les règles du rôle (voir canUser) :
     *   - Contributeur : créateur ou porteur de l'école si notice "Soumise"/"Validée".
     *   - Documentaliste : créateur ou UNT correspondant si notice "Soumise"/"Validée".
     *   - Administrateur : créateur ou notice "Soumise"/"Validée".
     *   - Autres : accès refusé.
     *
     * @param Notice $notice La notice concernée
     * @param User $user L'utilisateur à vérifier
     * @return bool true si l'accès à la vue est autorisé, false sinon
     */
    private function canView(Notice $notice, User $user): bool
    {
        return $this->isOwner($notice, $user) || $this->canUser($notice, $user);
    }

    /**
     * Vérifie si l'utilisateur peut éditer ou supprimer la notice.
     *
     * Règles d'accès :
     * - Seul le créateur peut modifier/supprimer une notice "En travail".
     * - Seuls les documentalistes ou administrateurs peuvent éditer une notice "Soumise".
     *
     * @param Notice $notice La notice concernée
     * @param User $user L'utilisateur à vérifier
     * @return bool true si l'accès à l'édition/suppression est autorisé, false sinon
     */
    private function canEdit(Notice $notice, User $user): bool
    {
        // Seul le créateur peut modifier/supprimer une notice "En travail"
        if ($notice->getEtat() === NoticEtat::Working) {
            return $this->isOwner($notice, $user);
        }
        // Seuls les documentalistes/admins peuvent éditer une notice "Soumise"
        if ($notice->getEtat() === NoticEtat::Forward) {
            return $this->canVali($notice, $user);
        }
        return false;
    }

    /**
     * Vérifie si l'utilisateur peut valider la notice.
     *
     * Règles d'accès :
     * - L'utilisateur doit avoir le rôle "ROLE_VALI_NOTI".
     * - Si l'utilisateur n'a pas d'UNT, accès total.
     * - Sinon, accès si la notice appartient à l'UNT de l'utilisateur.
     *
     * @param Notice $notice La notice concernée
     * @param User $user L'utilisateur à vérifier
     * @return bool true si l'accès à la validation est autorisé, false sinon
     */
    private function canVali(Notice $notice, User $user): bool
    {
        if (!$this->security->isGranted('ROLE_VALI_NOTI')) return false;
        $unt = $user->getUntheme();
        // Si pas d'UNT, accès total ; sinon, accès si la notice appartient à l'UNT
        return !$unt || $notice->belongsToUNT($unt);
    }

    /**
     * Règle d'accès générique selon le rôle de l'utilisateur.
     *
     * Règles d'accès :
     * - Contributeur :
     *   - Accès si créateur de la notice.
     *   - Accès si son école est porteuse ET notice "Soumise" ou "Validée".
     * - Documentaliste :
     *   - Accès si créateur de la notice.
     *   - Accès si son UNT correspond à la notice ET notice "Soumise" ou "Validée".
     * - Administrateur :
     *   - Accès si notice "Soumise" ou "Validée".
     *   - Accès si créateur de la notice.
     * - Autres : accès refusé.
     *
     * @param Notice $notice La notice concernée
     * @param User $user L'utilisateur à vérifier
     * @return bool true si l'accès est autorisé, false sinon
     */
    private function canUser(Notice $notice, User $user): bool
    {
        $role = $user->getGroup()->getLabel();

        // Contributeur
        if ($role === 'Contributeur') {
            if ($this->isOwner($notice, $user)) return true;
            $school = $user->getSchool();
            if ($school && in_array($school, $notice->getPorteurs()->toArray(), true)) {
                return in_array($notice->getEtat(), [NoticEtat::Forward, NoticEtat::Approved], true);
            }
            return false;
        }

        // Documentaliste
        if ($role === 'Documentaliste') {
            if ($this->isOwner($notice, $user)) return true;
            $unt = $user->getUntheme();
            if ($unt && $notice->belongsToUNT($unt)) {
                return in_array($notice->getEtat(), [NoticEtat::Forward, NoticEtat::Approved], true);
            }
            return false;
        }

        // Administrateur
        if ($role === 'Administrateur') {
            return in_array($notice->getEtat(), [NoticEtat::Forward, NoticEtat::Approved], true)
                || $this->isOwner($notice, $user);
        }

        // Par défaut, accès refusé
        return false;
    }

    /**
     * Vérifie si l'utilisateur est le créateur de la notice.
     *
     * @param Notice $notice La notice concernée
     * @param User $user L'utilisateur à vérifier
     * @return bool true si l'utilisateur est le créateur, false sinon
     */
    private function isOwner(Notice $notice, User $user): bool
    {
        return $notice->getCreateur() === $user;
    }
}
