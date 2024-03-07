<?php

namespace App\Entity;

enum NoticEtat: string
{
    case Working = 'En travail';
    case Forward = 'Soumise';
    case Approved = 'Validée'; //case Published = 'Publiée';

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value','value');
    }

    public static function getTransition(NoticEtat $from, NoticEtat $to): array
    {
        return match (true) {
            $from === self::Working && $to === self::Forward => ['Soumettre', self::Forward->value, 'Soummision'],
            $from === self::Forward && $to === self::Working => ['Rejeter', 'Rejetée', 'Rejet'],
            $from === self::Forward && $to === self::Approved => ['Valider', self::Approved->value, 'Validation'],
            $from === self::Approved && $to === self::Forward => ['Dépublier', 'Dépubliée', 'Dépublication'],
            $from === self::Approved && $to === self::Working => ['Autoriser', 'Autorisée', 'Autorisation'],
            default => array('Rectifier', 'Signalée', 'Rectification'),
        };
    }
}
