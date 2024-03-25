<?php

namespace App\Twig;

use App\Entity\Dossier;
use Twig\{TwigFunction, Extension\AbstractExtension};

class AppExtension extends AbstractExtension
{
  public function getFunctions(): array
  {
    return [ new TwigFunction('getCurrentParents', [$this, 'getParents']), ];
  }

  public function getParents(Dossier $item): array
  {
    $rootItem = $item->getParent();
    if (null === $rootItem) return [$item];
    return [...$this->getParents($rootItem), $item];
  }

}
