<?php

namespace App\Message\Handler;

use App\Entity\Dto\ConceptDto;
use App\Entity\Dto\DeweyDto;
use App\Message\ImportXmlMessage;
use App\Service\{FileService,XmlDataLoader,Converter\PrefixNameConverter};
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

#[AsMessageHandler]
readonly class ImportXmlHandler
{
    private XmlDataLoader $dwLoader, $dsLoader;

    public function __construct(
        private FileService $fs,
        private SerializerInterface $js,
        private EntityManagerInterface $em
    ){
        $this->dwLoader = new XmlDataLoader(new PrefixNameConverter("ns1:"));
        $this->dsLoader = new XmlDataLoader(new CamelCaseToSnakeCaseNameConverter());
    }

    public function __invoke(ImportXmlMessage $message): void
    {
        $content = $this->fs->readFile($message->path);

        if ($message->name === 'dewey') {
            $dewey = new DeweyDto([
                new ConceptDto('http://dewey.info/class/1',1,'001','Sciences'),
                new ConceptDto('http://dewey.info/class/2',2,'002','Société'),
            ]);
            //$data = $this->dwLoader->encode(['@xmlns:ns1'=>"http://www.uoh.fr/dewey/",'#'=>$dewey], 'ns1:DeweyCodes');
            $data = $this->dwLoader->decode($content, DeweyDto::class);
            dd($data);
            /*$repository = $this->em->getRepository($message->path);
            foreach ($data as $d) {
                $dewey = $this->createOrUpdate($d, $repository);
                $this->em->persist($dewey);
            }
            $this->em->flush();*/
        }
    }
}