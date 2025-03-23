<?php

namespace App\Command;

use App\Entity\Dto\{Classification, Contribute, Field, Motcle, SuplomDto, Taxon};
use App\Entity\{Auteur, Dewey, Discipline, Etablissement, Keyword, Licence, Niveau, Notice, NoticEtat, TDocument, TPedagogie, User};
use App\Service\FileService;
use Symfony\Component\Console\{Attribute\AsCommand,Command\Command,Input\InputInterface,Output\OutputInterface,Style\SymfonyStyle};
use Doctrine\ORM\{EntityManagerInterface,EntityRepository};
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'import:notice-data',
    description: "Exécute le processus d'importation des notices suploms",
),]
class ImportNoticeXmlCommand extends Command
{
    private FileService $fs; private array $droiRep, $niveRep, $tdocRep, $tpedRep;
    private EntityRepository $userRep, $deweRep, $auteRep, $kwrdRep, $discRep, $etabRep;

    public function __construct(private readonly EntityManagerInterface $em, private readonly SerializerInterface $serializer)
    {
        $this->fs = new FileService('environment/');
        parent::__construct();
        $this->userRep = $this->em->getRepository(User::class);
        $this->deweRep = $this->em->getRepository(Dewey::class);
        $this->auteRep = $this->em->getRepository(Auteur::class);
        $this->kwrdRep = $this->em->getRepository(Keyword::class);
        $this->discRep = $this->em->getRepository(Discipline::class);
        $this->etabRep = $this->em->getRepository(Etablissement::class);

        $this->droiRep = array_reduce(
            $this->em->getRepository(Licence::class)->findAll(),
            fn(array $k, Licence $v) => $k+[strtolower(preg_replace('/\s+/', '', $v->getValeur())) => $v], []);

        $this->niveRep = array_reduce(
            $this->em->getRepository(Niveau::class)->findAll(),
            fn(array $k, Niveau $v) => $k+[strtolower($v->getCode()) => $v], []);

        $this->tdocRep = array_reduce(
            $this->em->getRepository(TDocument::class)->findAll(),
            fn(array $k, TDocument $v) => $k+[strtolower($v->getCode()) => $v], []);

        $this->tpedRep = array_reduce(
            $this->em->getRepository(TPedagogie::class)->findAll(),
            fn(array $k, TPedagogie $v) => $k+[strtolower($v->getSuplom()) => $v], []);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Suplom Importing'); $notfound = 0; $authors = [];

        $data = $this->fs->readFilesFrom(null, "suplom/");
        $io->progressStart($data->count()); //$nr = $this->em->getRepository(Notice::class);
        foreach ($data as $file) {
            /** @var SuplomDto $item */
            $item = $this->serializer->deserialize($this->fs->readFile($file->getRealPath()), SuplomDto::class, 'xml');
            $uid = substr($item->general?->identifier?->entry, -36);
            $notice = new Notice(); $io->progressAdvance();

            /*if(isset($item->relations)) {
                $resources = array_reduce($item->relations, function(array $arr, Relation $res) use ($authors) {
                    if($res->kind->value === 'haspart') {
                        parse_str(parse_url($res->resource->identifier?->entry, PHP_URL_QUERY), $params);
                        if(isset($params['uuid'])) $arr['uuid'] = Uuid::fromString($params['uuid'])->toBinary();
                    }
                    return $arr;
                }, []);
                if (count($resources) > 0) {
                    $notice = $nr->findOneBy(['uuid' => $uid]);
                    if($notice) array_map(fn (Notice $etab) => $notice->addRessource($etab),$nr->findBy(['uuid' => $resources]));
                }
            } continue;*/

            $contributes = array_merge(
                $item->metadata?->contributes,
                $item->lifeCycle?->contributes
            ); $ator_teurs = ["author" => [], "publisher" => [], "validator" => [], "creator" => []];
            /** @var Contribute $c */
            foreach ($contributes as $c) {
                preg_match('/FN:(.*)/', $c->entities[0], $fnMatches);
                $name = $fnMatches[1] ?? null;
                if($c->role->value === "contributeur" || $c->role->value === "initiator")
                    $ator_teurs["creator"][] = $name;
                elseif (array_key_exists($c->role->value, $ator_teurs)) {
                    $ator_teurs[$c->role->value][] = $name;
                    if ($c->role->value === "validator"||$c->role->value === "creator") $ator_teurs[$c->role->value][] = implode(" ", array_reverse(explode(" ", $name)));
                }else $ator_teurs[$c->role->value] = [$name];

                if (ctype_digit($c->date[0])) {
                    if($c->role->value === "author") $notice->setRessDate($c->date[0]);
                } elseif ($c->role->value === "publisher") $notice->setPublieLe(\DateTime::createFromFormat("Y-m-d", $c->date[0]));
                elseif ($c->role->value === "validator" && $notice->getPublieLe() == null)
                    $notice->setPublieLe(\DateTime::createFromFormat("Y-m-d", $c->date[0]));
            }

            $notice->setCreateur($this->userRep->findOneBy(['name' => $ator_teurs["creator"]]))
                ->setValidateur($this->userRep->findOneBy(['name' => $ator_teurs["validator"]]));
            if($notice->getCreateur() == null) {
                $notfound += 1; //$author = $ator_teurs["creator"][0];
                if(!in_array($uid, $authors)) $authors[] = $uid;
                continue;
            }
            if(isset($item->technical?->size)) $notice->setRessSize(floatval($item->technical->size));
            foreach($ator_teurs["author"] as $n) {
                $name = explode(" ", $n); $cName = count($name);
                if (str_contains($n,'niversit')) continue;
                if ($cName == 2) {
                    $lName = $name[0];
                    $fName = $name[1];
                    //if ($fName=='Écri=') $fName = 'Écri+';
                } elseif ($cName > 2) {
                    $lName = implode(' ', array_slice($name, 0, -1));
                    $fName = $name[$cName - 1];
                }
                if(isset($lName) && $cName = $this->auteRep->findOneBy(['nom' => $lName, 'prenom' => $fName])) $notice->addAuteur($cName);
            }
            array_map(fn (Etablissement $etab) => $notice->addPorteur($etab), $this->etabRep->findBy(['nom' => $ator_teurs["publisher"]]));
            $motcles = array_map(fn(Motcle $s) => $s->string?->value, $item->general?->keywords);//foreach($item->general?->keywords as $s) $notice->addTag($this->kwrdRep->findOneBy(['nom' => $s->string?->value]) ?? new Keyword($s->string?->value));
            array_map(fn(Keyword $kywd) => $notice->addTag($kywd), $this->kwrdRep->findBy(['nom' => $motcles]));
            $notice->setExportOAI(true)->setEtat(NoticEtat::Forward)
                ->setUuid(Uuid::fromString($uid)) //->setVignette("$uid.jpg")
                ->setTitre($item->general->title[0]?->value)
                ->setDescription($item->general->description[0]?->value)
                ->setRessUrl(trim($item->technical?->location))
                ->setDureExec($item->technical?->duration->duration??null)
                ->setRessLang($item->general->languages)
                ->setUserLang($item->educational->languages)
                ->setDureAppr($item->educational->typicalLearningTime->duration??null)
                ->setProprIntel(strtolower($item->rights?->copyrightAndOtherRestrictions?->value??'')!=="no")
                ->setRessPayant(strtolower($item->rights?->cost?->value??'')!=="no")
                ->setPropUser(array_map(fn(Motcle $s) => $s->string?->value, $item->educational?->description))
                ->setDroit($this->droiRep[strtolower(preg_replace('/\s+/', '', $item->rights?->description[0]?->value))]);
            foreach ($item->educational?->contexts as $s) $notice->addNiveau($this->niveRep[strtolower($s->value)]);
            foreach ($item->general?->documentTypes as $s) $notice->addDocType($this->tdocRep[strtolower($s->value)]);
            foreach ($item->educational?->learningResourceTypes as $s) $notice->addPedType($this->tpedRep[strtolower($s->value)]);
            /** @var Classification $class */
            foreach ($item->classifications as $class) {
                if (isset($class->taxonPath)) {
                    $key = array_reduce($class->taxonPath->source, fn(string $a, Field $s) => "$a $s->value", "");
                    $value = array_map(fn(Taxon $taxon) => $taxon->entry[0]?->value, $class->taxonPath->taxons);

                    if (str_contains($key, 'lassification')) array_map(function (Discipline $spec) use ($notice) {
                        $disc = $spec->getParent();
                        if($disc?->getParent()) $notice->addSpecialite($spec);
                    }, $this->discRep->findBy(['nom' => $value]));
                    if (str_contains($key, 'CDD 22')) array_map(function(Dewey $spec) use ($notice) {
                        $disc = $spec->getParent();
                        if($disc?->getParent()) $notice->addCodewey($spec);
                    }, $this->deweRep->findBy(['nom' => $value]));
                } elseif (str_contains($class->purpose?->value, 'educational')) $notice->setObjectif($class->description[0]?->string->value);
            }
            $this->em->persist($notice);
        }
        $io->progressFinish(); $this->em->flush();

        $output->writeln("Suplom imported successfully !($notfound)");
        dump($authors);
        return Command::SUCCESS;
    }
}