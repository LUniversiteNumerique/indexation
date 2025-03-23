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
        $objs = array();

        foreach ($data->concepts as $value) {
            $node = $this->setDewe($value);
            $parentUri = $this->getParentUri($value->uri);
            if (isset($objs[$parentUri]))
                $objs[$parentUri]->getChildren()->add($node->setParent($objs[$parentUri]));
            $objs[$value->uri] = $node;
            $this->em->persist($node);
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
        if($logo->hasResults()) $etab->setLogo($name);

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