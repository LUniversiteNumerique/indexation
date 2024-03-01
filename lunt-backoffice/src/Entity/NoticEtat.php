<?php

namespace App\Entity;

enum NoticEtat: string
{
    case Working = 'Travail';
    case Forward = 'Soumise';
    case Approved = 'Validée';
    case Rejected = 'Rejetée';
    case Published = 'Publiée';

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
