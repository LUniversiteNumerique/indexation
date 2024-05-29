<?php

namespace App\Message\Handler;

use App\Entity\{Dewey, Discipline};
use App\Entity\Dto\{DeweyData, DeweyDto, DisciplineData, PropertyDto};
use App\Message\ImportXmlMessage;
use App\Service\FileService;
use Doctrine\ORM\{EntityRepository,EntityManagerInterface};
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ImportXmlHandler
{
    private EntityRepository $deweRep, $discRep;
    public function __construct(
        private FileService $fs,
        private SerializerInterface $js,
        private EntityManagerInterface $em
    ){
        $this->deweRep = $this->em->getRepository(Dewey::class);
        $this->discRep = $this->em->getRepository(Discipline::class);
    }

    public function __invoke(ImportXmlMessage $message): void
    {
        $objs = array();
        $content = $this->fs->readFile($message->path);
        if ($message->name === 'dewey') $objs = $this->getDewe($content);
        else if ($message->name === 'specialite') $objs = $this->getDisc($content);
        $this->em->flush(); dump(count($objs));
    }

    /**
     * @param string $content
     * @return array
     */
    private function getDewe(string $content): array
    {
        $objs = array();
        /** @var DeweyData $data */
        $data = $this->js->deserialize($content, DeweyData::class, 'xml');
        foreach ($data->concepts as $value) {
            $dewe = $this->setDewe($value);
            $objs[$value->notation] = $dewe;
            $this->em->persist($dewe);
        }
        return $objs;
    }

    /**
     * @param string $content
     * @return array
     */
    private function getDisc(string $content): array
    {
        $objs = array();
        /** @var DisciplineData $data */
        $data = $this->js->deserialize($content, DisciplineData::class, 'xml');
        foreach ($data->items as $value) {
            $prop = array_reduce($value->properties, fn($tmp, PropertyDto $prop) => $tmp + [$prop->key => $prop->value], []);
            $disc = $this->setDisc($prop);

            $objs[$prop['id']] = $disc;
            $this->em->persist($disc);
        }
        return $objs;
    }

    private function setDisc(array $prop): Discipline
    {
        $disc = $this->discRep->findOneBy(['code' => $prop['id']]);
        if(!$disc) $disc = Discipline::create($prop);
        return $disc->setNom($prop['libelle_uoh']);
    }

    private function setDewe(DeweyDto $dto): Dewey
    {
        $dewe = $this->deweRep->findOneBy(['code' => $dto->notation]);
        if(!$dewe) $dewe = Dewey::create($dto);
        return $dewe->setNom($dto->label);
    }
}