<?php

namespace App\Command;

use App\Entity\Dto\{Classification, Contribute, Field, Motcle, Relation, SuplomDto, Taxon};
use App\Entity\{Auteur,
  Dewey,
  DeweyPerso,
  Discipline,
  Etablissement,
  Keyword,
  Licence,
  Niveau,
  Notice,
  NoticEtat,
  TDocument,
  TPedagogie,
  User};
use App\Service\FileService;
use Symfony\Component\Console\{Attribute\AsCommand,
  Command\Command,
  Input\InputInterface,
  Output\OutputInterface,
  Style\SymfonyStyle};
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
  name: 'import:notice-data',
  description: "Exécute le processus d'importation des notices suploms",
), ]
class ImportNoticeXmlCommand extends Command
{
  private FileService $fs;
  private array $droiRep, $niveRep, $tdocRep, $tpedRep;
  private EntityRepository $userRep, $deweRep, $auteRep, $kwrdRep, $discRep, $etabRep, $dewePersoRep;
  private Filesystem $filesystem;

  public function __construct(private readonly EntityManagerInterface $em, private readonly SerializerInterface $serializer)
  {
    $this->fs = new FileService('environment/');
    parent::__construct();
    $this->userRep = $this->em->getRepository(User::class);
    $this->deweRep = $this->em->getRepository(Dewey::class);
    $this->dewePersoRep = $this->em->getRepository(DeweyPerso::class);
    $this->auteRep = $this->em->getRepository(Auteur::class);
    $this->kwrdRep = $this->em->getRepository(Keyword::class);
    $this->discRep = $this->em->getRepository(Discipline::class);
    $this->etabRep = $this->em->getRepository(Etablissement::class);
    $this->filesystem = new Filesystem();

    $this->droiRep = array_reduce(
      $this->em->getRepository(Licence::class)->findAll(),
      fn(array $k, Licence $v) => $k + [strtolower(preg_replace('/\s+/', '', $v->getValeur())) => $v], []);

    $this->niveRep = array_reduce(
      $this->em->getRepository(Niveau::class)->findAll(),
      fn(array $k, Niveau $v) => $k + [strtolower($v->getCode()) => $v], []);

    $this->tdocRep = array_reduce(
      $this->em->getRepository(TDocument::class)->findAll(),
      fn(array $k, TDocument $v) => $k + [strtolower($v->getCode()) => $v], []);

    $this->tpedRep = array_reduce(
      $this->em->getRepository(TPedagogie::class)->findAll(),
      fn(array $k, TPedagogie $v) => $k + [strtolower($v->getSuplom()) => $v], []);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);
    $io->title('Suplom Importing');
    $notfound = [];
    $disciplineGroupsByParent = [];
    $deweyGroupsByParent = [];

    //Ré utilisation de la fonction readFilesFrom en dur car non fonctionnel avec un appel simple de celle-ci avec le meme répertoire
    $finder = new Finder();
    $path = "data/suplom/";
    $names = ['*.xml'];
    $since = null;
    $deep = 0;
    if (!$this->filesystem->exists($path))
      $output->writeln("Aucun fichier trouvé.");
    $finder->files()->in($path)->name($names)->depth($deep);
    if ($since) $finder->date('>= ' . $since->format('Y-m-d H:i:s'));
    $data = $finder;

    //Suppression de l'enssemble des notices en base actuel
    $notices = $this->em->getRepository(Notice::class)->createQueryBuilder('n')
      ->where('n.id >= :id')
      ->setParameter('id', 4)
      ->getQuery()
      ->getResult();

    foreach ($notices as $notice) {
      $this->em->remove($notice);
    }
    $this->em->flush();

    $io->progressStart(count($data));
    $nr = $this->em->getRepository(Notice::class);

    foreach ($data as $file) {
      /** @var SuplomDto $item */
      $item = $this->serializer->deserialize($this->fs->readFile($file->getRealPath()), SuplomDto::class, 'xml');
      $uid = substr($item->general?->identifier?->entry, -36);
      $notice = new Notice();
      $io->progressAdvance();
      $motcles = array_map(fn(Motcle $s) => trim($s->string?->value), $item->general?->keywords);

      /*if(isset($item->relations)) {
          $resources = array_reduce($item->relations, function(array $arr, Relation $res) use ($authors) {
              parse_str(parse_url($res->resource->identifier?->entry, PHP_URL_QUERY), $params);
              if (isset($params['uuid'])) $arr[] = Uuid::fromString($params['uuid'])->toBinary();
              return $arr;
          }, []);
          if (count($resources) > 0) {
              $notice = $nr->findOneBy(['uuid' => $uid]);
              $notices = $nr->findBy(['uuid' => $resources]);
              if($notice && count($notices) > 0) array_map(fn (Notice $etab) => $notice->addRessource($etab),$notices);
          }
      } continue;*/

      $contributes = array_merge(
        $item->metadata?->contributes,
        $item->lifeCycle?->contributes
      );
      $roleNotice = [];
      /** @var Contribute $role */
      foreach ($contributes as $role) {
        // Gestion des roles
        preg_match('/FN:(.*)/', $role->entities[0], $fnMatches);
        $name = $fnMatches[1] ?? null;
        $roleNotice[$role->role->value][] = $name;

        // Gestion date de création et ressdate
        if (ctype_digit($role->date[0])) {
          if ($role->role->value === "author") {
            $notice->setRessDate($role->date[0]);
          }
        } elseif ($role->role->value === "publisher") {
          $notice->setPublieLe(\DateTime::createFromFormat("Y-m-d", $role->date[0]));
        } elseif ($role->role->value === "validator" && $notice->getPublieLe() == null) {
          $notice->setPublieLe(\DateTime::createFromFormat("Y-m-d", $role->date[0]));
        }
      }

      $creatorName = $roleNotice["creator"][0] ?? null;
      $notice->setCreateur($this->userRep->findOneBy(['name' => $creatorName]));
      $validatorName = $roleNotice["validator"][0] ?? null;
      $notice->setValidateur($this->userRep->findOneBy(['name' => $validatorName]));

      // Ajouter un utilisateur par défaut quand une notice en a pas
      if ($notice->getCreateur() == null) {
        $notfound[] = [
          'titre' => $item->general->title[0]?->value,
          'lien' => 'https://ressources.luniversitenumerique.fr/'
        ];
        $existingUser = $this->userRep->findOneBy(['name' => 'créateur inconnu']);
        if ($existingUser === null) {
          $entity = new User('créateur inconnu', 'noreply@luniversitenumerique.fr');
          $this->em->persist($entity);
          $this->em->flush();
          $notice->setCreateur($entity);
        } else {
          $notice->setCreateur($existingUser);
        }
      }

      // Ajout d'une catégorie publisher car certaines notices n'ont pas de role publisher
      if (!array_key_exists("publisher", $roleNotice)) {
        $roleNotice["publisher"] = [];
      }


      if (isset($item->technical?->size)) $notice->setRessSize(floatval($item->technical->size));
      foreach ($roleNotice["author"] as $n) {
        $name = explode(" ", $n);
        $cName = count($name);
        if ($cName == 2) {
          $lName = $name[0];
          $fName = $name[1];
          //if ($fName=='Écri=') $fName = 'Écri+';
        } elseif ($cName > 2 && str_contains($n, 'niversit')) {
          $lName = implode(' ', array_slice($name, 2, $cName - 2));
          $fName = $name[0] . ' ' . $name[1];
        } else {
          $lName = implode(' ', array_slice($name, 0, -1));
          $fName = $name[$cName - 1];
        }
        $cName = $this->auteRep->findOneBy(['nom' => $lName, 'prenom' => $fName]);
        if ($cName) {
          $notice->addAuteur($cName);
        } else {
          $entity = new Auteur($lName, $fName);
          $this->em->persist($entity);
          $this->em->flush();
          $notice->addAuteur($entity);
        }
      }
      array_map(fn(Etablissement $etab) => $notice->addPorteur($etab), $this->etabRep->findBy(['nom' => $roleNotice["publisher"]]));
      array_map(fn(Keyword $kywd) => $notice->addTag($kywd), $this->kwrdRep->findBy(['nom' => $motcles]));
      $notice->setExportOAI(false)->setEtat(NoticEtat::Forward)
        ->setUuid(Uuid::fromString($uid)) //->setVignette("$uid.jpg")
        ->setTitre($item->general->title[0]?->value)
        ->setDescription($item->general->description[0]?->value)
        ->setDureExec($item->technical?->duration->duration ?? null)
        ->setRessLang($item->general->languages)
        ->setUserLang($item->educational->languages)
        ->setDureAppr(normalizeDuration($item->educational->typicalLearningTime->duration ?? null))
        ->setProprIntel(strtolower($item->rights?->copyrightAndOtherRestrictions?->value ?? '') !== "no")
        ->setRessPayant(strtolower($item->rights?->cost?->value ?? '') !== "no")
        ->setPropUser(array_map(fn(Motcle $s) => $s->string?->value, $item->educational?->description))
        ->setDroit($this->droiRep[strtolower(preg_replace('/\s+/', '', $item->rights?->description[0]?->value))]);

      if (str_contains(trim($item->technical?->location), 'document/')) {
        $notice->setRessUrl(trim($item->technical?->location));
        //$notice->setRessZip(trim($item->technical?->location));
        //$output->writeln("Lien trouvé pour format ZIP : " . $notice->getRessUrl() . "\n");
        $output->writeln("Lien trouvé pour format ZIP : " . trim($item->technical?->location) . "\n");
        $output->writeln("uuid : ". $notice->getUuid() . "\n");
      } else {
        $notice->setRessUrl(trim($item->technical?->location));
      }


      foreach ($item->educational?->contexts as $s) $notice->addNiveau($this->niveRep[strtolower($s->value)]);
      foreach ($item->general?->documentTypes as $s) $notice->addDocType($this->tdocRep[strtolower($s->value)]);
      foreach ($item->educational?->learningResourceTypes as $s) $notice->addPedType($this->tpedRep[strtolower($s->value)]);
      /** @var Classification $class */
      foreach ($item->classifications as $class) {
        if (isset($class->taxonPath)) {
          $key = array_reduce($class->taxonPath->source, fn(string $a, Field $s) => "$a $s->value", "");

          // Gestion des spécialités
          if (str_contains($key, 'lassification')) {
            foreach ($class->taxonPath->taxons as $taxon) {
              $spec = $taxon->entry[0]?->value;
              $exist = $this->discRep->findOneBy(['nom' => $spec]);
              if ($exist) {
                $disc = $exist->getParent();
                $champDisc = $disc?->getParent();
                if ($champDisc && $disc) {
                  $groupKey = $champDisc->getId() . '-' . $disc->getId();
                  if (!isset($disciplineGroupsByParent[$groupKey])) {
                    $existingGroup = $this->em->getRepository(\App\Entity\DisciplineGroup::class)
                      ->findOneBy(['champDisc' => $champDisc, 'discipline' => $disc]);
                    if ($existingGroup) {
                      $disciplineGroupsByParent[$groupKey] = $existingGroup;
                    } else {
                      $group = new \App\Entity\DisciplineGroup();
                      $group->setChampDisc($champDisc);
                      $group->setDiscipline($disc);
                      $this->em->persist($group);
                      $disciplineGroupsByParent[$groupKey] = $group;
                    }
                  }
                  $disciplineGroupsByParent[$groupKey]->addSpecialite($exist);
                  $this->em->persist($notice);
                  $notice->addDisciplineGroup($disciplineGroupsByParent[$groupKey]);
                }
              }
            }
          }

          // Gestion des Deweys
          if (str_contains($key, 'CDD 22')) {
            $deweyGroupsByParent = [];
            foreach ($class->taxonPath->taxons as $taxon) {
              $spec = $taxon->entry[0]?->value;
              $id = $taxon->id ?? null;

              // code dewey original
              $exist = $this->deweRep->findOneBy(['nom' => $spec]);
              if ($exist) {
                $disc = $exist->getParent();
                $champDisc = $disc?->getParent();
                if ($champDisc && $disc) {
                  $groupKey = $champDisc->getId() . '-' . $disc->getId();
                  if (!isset($deweyGroupsByParent[$groupKey])) {
                    $group = new \App\Entity\DeweyGroup();
                    $group->setDewey($champDisc);
                    $group->setDivision($disc);
                    $notice->addDeweyGroup($group);
                    $deweyGroupsByParent[$groupKey] = $group;
                    $this->em->persist($group);
                  }
                  $deweyGroupsByParent[$groupKey]->addCodewey($exist);
                }
              } else {
                // code dewey personnaliser
                $code = "http://dewey.info/class/" . $id . "/";
                $existPerso = $this->dewePersoRep->findOneBy(['code' => $code]);
                if ($existPerso) {
                  $notice->addDeweyPerso($existPerso);
                } else {
                  $deweyPerso = new DeweyPerso();
                  $deweyPerso->setCode($code);
                  $deweyPerso->setNom($spec);
                  $this->em->persist($deweyPerso);
                  $this->em->flush();
                  $notice->addDeweyPerso($deweyPerso);
                }
              }
            }
          }
        } elseif (str_contains($class->purpose?->value, 'educational')) $notice->setObjectif($class->description[0]?->string->value);


      }
      $this->em->persist($notice);
    }

    $io->progressFinish();
    $this->em->flush();
    $output->writeln("Suplom imported successfully ! count of suplom with creator not found : ". count($notfound));
    /*foreach ($notfound as $nf) {
      $output->writeln("title : " . $nf['titre']. "\n");
    }*/
    return Command::SUCCESS;
  }
}
// Ajout d'une fonction de conversion PT50H en jours/heures car cela causait des erreurs lors de la modification de certaines notices
function normalizeDuration($duration) {
  // Si la durée est du type PT50H, convertis en jours/heures
  if (preg_match('/^PT(\d+)H$/', $duration, $matches)) {
    $hours = (int)$matches[1];
    $days = intdiv($hours, 24);
    $restHours = $hours % 24;
    $result = 'P';
    if ($days > 0) $result .= $days . 'D';
    $result .= 'T';
    if ($restHours > 0) $result .= $restHours . 'H';
    return $result;
  }
  return $duration;
}
