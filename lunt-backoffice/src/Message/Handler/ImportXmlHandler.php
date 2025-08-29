<?php

namespace App\Message\Handler;

use App\Entity\{Dewey, Discipline, Etablissement, TPedagogie};
use App\Entity\Dto\{DeweyData, DeweyDto, DisciplineData, PropertyDto, SpecialiteDto};
use App\Message\ImportXmlMessage;
use App\Service\FileService;
use Doctrine\ORM\{EntityRepository,EntityManagerInterface};
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ImportXmlHandler
{
    private EntityRepository $deweRep, $discRep, $etabRep, $tpedRep;
    public function __construct(
        private FileService $fs,
        private SerializerInterface $js,
        private EntityManagerInterface $em
    ){
        $this->deweRep = $this->em->getRepository(Dewey::class);
        $this->discRep = $this->em->getRepository(Discipline::class);
        $this->etabRep = $this->em->getRepository(Etablissement::class);
        $this->tpedRep = $this->em->getRepository(TPedagogie::class);
    }

    public function __invoke(ImportXmlMessage $message): void
    {
        $content = $this->fs->readFile($message->path);
        $objs = match ($message->name) {
            'dewey' => $this->getDewe($content),
            'specialite' => $this->getDisc($content),
            'etablissement' => $this->getType($content, [$this, 'setEtab']),
            'type_pedagogique' => $this->getType($content, [$this, 'setTped'])
        };

        $this->em->flush(); dump(count($objs));
    }

    /**
     * @param string $content
     * @return array
     */
    private function getDewe(string $content): array
    {
      /** @var DeweyData $data */
      $data = $this->js->deserialize($content, DeweyData::class, 'xml');
      $objs = [];
      $meta = [];

      foreach ($data->concepts as $value) {
        $uri = rtrim($value->uri, '/') . '/';
        $node = $this->setDewe($value);
        $objs[$uri] = $node;
        // Recupere le code dans l'URI
        // Sinon dans la balise Notation
        $codeRaw = null;
        if (isset($value->uri) && preg_match('#class\/([^\/]+)#', $value->uri, $m)) {
          $codeRaw = $m[1];
        } elseif (isset($value->notation) && $value->notation !== null) {
          $codeRaw = (string) $value->notation;
        } elseif (isset($value->Notation) && $value->Notation !== null) {
          $codeRaw = (string) $value->Notation;
        } elseif (isset($value->uri)) {
          $codeRaw = trim($value->uri, '/');
        }
        // Garde la partie entiere du nombre
        $codeLeft = $codeRaw !== null ? explode('.', $codeRaw)[0] : '';
        // reprend le level dans le XML
        $level = isset($value->level) ? (int) $value->level : (strlen($codeLeft) > 2 ? 3 : (strlen($codeLeft) > 1 ? 2 : 1));
        $meta[$uri] = ['code' => $codeLeft, 'level' => $level];
        $this->em->persist($node);
      }

      // lier les parents en utilisant la troncature numérique et les informations de niveau
      // traite les niveaux dans l'ordre pour assurer que les parents soit disponible
      $metaList = [];
      foreach ($meta as $uri => $info) {
        $metaList[] = ['uri' => $uri, 'info' => $info];
      }
      usort($metaList, fn($a, $b) => ($a['info']['level'] <=> $b['info']['level']));

      foreach ($metaList as $entry) {
        $uri = $entry['uri'];
        $info = $entry['info'];
        $level = $info['level'];
        if ($level <= 1) continue;
        $code = (string) $info['code'];
        if ($code === '') continue;
        // Test de supprimer les derniers chiffres pour trouver un parent.
        //Gere les cas comme 385->38->3 et 030->03->3
        $found = false;
        $candidate = $code;
        while (strlen($candidate) > 0 && !$found) {
          //Supprime le dernier caractere
          $candidate = substr($candidate, 0, -1);
          if ($candidate === '') break;
          // Construction de URI a tester avec et sans 0
          $candidates = [];
          $candidates[] = 'http://dewey.info/class/' . $candidate . '/';
          // Si le code enfant a 3 chiffres et que le candidat en a 2, essayer également la version avec un zéro ajouté
          $childLen = strlen($code);
          $candLen = strlen($candidate);
          if ($childLen >= 3 && $candLen < 3) {
            if ($candLen === 1) $candidates[] = 'http://dewey.info/class/0' . $candidate . '/';
            if ($candLen <= 2) $candidates[] = 'http://dewey.info/class/' . str_pad($candidate, 3, '0', STR_PAD_LEFT) . '/';
          }
          if ($childLen === 2 && strlen($candidate) === 1) {
            $candidates[] = 'http://dewey.info/class/0' . $candidate . '/';
          }
          foreach ($candidates as $parentUri) {
            if (isset($objs[$parentUri])) {
              $parentNode = $objs[$parentUri];
              $objs[$uri]->setParent($parentNode);
              $parentNode->getChildren()->add($objs[$uri]);
              $found = true;
              break;
            }
          }
        }
      }

      return $objs;
    }

    private function getParentUri($uri): string
    {
        $parts = explode('class/', trim($uri,'/'));
        $pos = strpos($parts[1], '.');

        $parts[1] = substr($parts[1], 0, $pos-1);
        return implode('class/', $parts) . '/';
    }

    /**
     * @param string $content
     * @return array
     */
    private function getDisc(string $content): array
    {
        /** @var DisciplineData $data */
        $data = $this->js->deserialize($content, DisciplineData::class, 'xml');
        $objs = array();
        foreach ($data->items as $value) {
            $node = $this->processNode($value, null);

            $objs[$node->getCode()] = $node;
            $this->em->persist($node);
        }
        return $objs;
    }

    private function processNode(SpecialiteDto $value, ?Discipline $parent): Discipline
    {
        $prop = array_reduce($value->properties, fn($tmp, PropertyDto $prop) => $tmp + [$prop->key => $prop->value], []);
        $node = $this->setDisc($prop);
        $parent?->getChildren()->add($node->setParent($parent));

        foreach ($value->children as $childNode)
            $this->processNode($childNode, $node);
        return $node;
    }

    /**
     * @param string $content
     * @param callable $callback
     * @return array
     */
    private function getType(string $content, callable $callback): array
    {
        /** @var DisciplineData $data */
        $data = $this->js->deserialize($content, DisciplineData::class, 'xml');
        $objs = array();
        foreach ($data->items as $value) {
            $prop = array_reduce($value->properties, fn($tmp, PropertyDto $prop) => $tmp + [$prop->key => $prop->value], []);
            $node = $callback($prop);

            $objs[$prop['id']] = $node;
            $this->em->persist($node);
        }
        return $objs;
    }

    private function setEtab(array $prop): Etablissement
    {
        $name = $prop['id'].".png";
        $logo = $this->fs->readFilesFrom(null, "uploads/logos/", $name);
        $etab = $this->etabRep->findOneBy(['code' => $prop['id']]) ?? Etablissement::create($prop);
        if($logo?->hasResults()) $etab->setLogo($name);

        return $etab->setNom($prop['libelle_import']);
    }

    private function setTped(array $prop): TPedagogie
    {
        $tped = $this->tpedRep->findOneBy(['code' => $prop['id']]) ?? TPedagogie::create($prop);

        return $tped->setNom($prop['libelle_uoh'])->setSuplom($prop['libelle_suplomfr']);
    }

    private function setDisc(array $prop): Discipline
    {
        $disc = $this->discRep->findOneBy(['code' => $prop['id']]);
        if(!$disc) $disc = Discipline::create($prop);
        return $disc->setNom($prop['libelle_import']);
    }

    private function setDewe(DeweyDto $dto): Dewey
    {
        $dewe = $this->deweRep->findOneBy(['code' => $dto->uri]);
        if(!$dewe) $dewe = Dewey::create($dto);
        return $dewe->setNom($dto->label);
    }
}
