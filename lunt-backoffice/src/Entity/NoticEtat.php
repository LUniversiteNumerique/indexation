<?php

namespace App\Entity;

enum NoticEtat: int
{
    case Working = 0;
    case Forward = 1;
    case Approved = 2; //case Published = 'Publiée';

    public function getLabel(): string
    {
        return match ($this) {
            self::Working => "En travail",
            self::Forward => 'Soumise',
            self::Approved => 'Validée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Working => "dark",
            self::Forward => 'danger',
            self::Approved => 'success',
        };
    }

    public static function getLabels(): array
    {
        return [
            self::Working->getLabel() => self::Working->value,
            self::Forward->getLabel() => self::Forward->value,
            self::Approved->getLabel() => self::Approved->value
        ];
    }

    public static function getColors(): array
    {
        return [self::Working->getColor(),self::Forward->getColor(),self::Approved->getColor()];
    }

    public static function getTransition(NoticEtat $from, NoticEtat $to): array
    {
        return match (true) {
            $from === self::Working && $to === self::Forward => ['Soumettre', 'Soumise', 'Soummision'],
            $from === self::Forward && $to === self::Working => ['Rejeter', 'Rejetée', 'Rejet'],
            $from === self::Forward && $to === self::Approved => ['Valider', 'Validée', 'Validation'],
            $from === self::Approved && $to === self::Forward => ['Dépublier', 'Dépubliée', 'Dépublication'],
            $from === self::Approved && $to === self::Working => ['Autoriser', 'Autorisée', 'Autorisation'],
            default => array('Rectifier', 'Signalée', 'Rectification'),
        };
    }
}
