<?php

namespace App\Entity;

enum DateUnit: string
{
    case MINUTES = 'min';
    case HEURES = 'hour';
    case JOURS = 'day';
    case SEMAINES = 'weeks';
    case MOIS = 'month';
    case ANS = 'year';
}
