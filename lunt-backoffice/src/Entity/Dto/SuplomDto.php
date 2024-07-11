<?php

namespace App\Entity\Dto;

use App\Entity\{Auteur, Keyword, Niveau, Notice, TDocument, TPedagogie};
use JMS\Serializer\Annotation as Jms;

#[
    Jms\XmlRoot("lom", namespace:"http://ltsc.ieee.org/xsd/LOM", prefix: 'lom'),
    Jms\XmlNamespace(uri:"http://www.w3.org/2001/XMLSchema-instance", prefix:"xsi"),
    Jms\XmlNamespace(uri:"http://www.lom-fr.fr/xsd/LOMFR", prefix:"lomfr")]
class SuplomDto {
    const RESOURCE_URI = "http://orioai.univ-valenciennes.fr/";

    #[Jms\XmlAttribute(namespace:"http://www.w3.org/2001/XMLSchema-instance"),Jms\Type('string'),Jms\SerializedName('schemaLocation')]
    public ?string $schemaLocation;
    public function __construct(
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(General::class)] public ?General $general = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(LifeCycle::class), Jms\SerializedName('lifeCycle')] public ?LifeCycle $lifeCycle = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(MetaData::class), Jms\SerializedName('metaMetadata')] public ?MetaData $metadata = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Technical::class)] public ?Technical $technical = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Educational::class)] public ?Educational $educational = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Right::class)] public ?Right $rights = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Relation::class)] public ?Relation $relation = null,
        #[Jms\XmlList(entry: "classification", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Classification::class.">"), Jms\XmlElement(cdata: false)] public array $classifications = [],
    ){ $this->schemaLocation = "http://ltsc.ieee.org/xsd/LOM http://lom-fr.fr/xsd/lomfrv1.0/std/lomfr.xsd"; }

    static function create(Notice $n): SuplomDto
    {
        $lang = current($n->getRessLang())??'fre';
        $discs = []; $disc = $n->getSpecialite();
        while ($disc) {
            $discs[] = new Taxon('http://data.bnf.fr/ark:/12148/'.$disc->getCode(), [new Field($lang, $disc->getNom())]);
            $disc = $disc->getParent();
        }
        $dewey = []; $dewe = $n->getCodewey();
        while ($dewe) {
            $dewey[] = new Taxon('http://data.bnf.fr/ark:/12148/'.$dewe->getCode(), [new Field($lang, $dewe->getNom())]);
            $dewe = $dewe->getParent();
        }

        $creat = $n->getCreateur(); $valid = $n->getValidateur();
        return new self(
            new General(
                new Catalog('URI', self::RESOURCE_URI .$n->getUuid()), [new Field($lang, $n->getTitre())], [new Field($lang, $n->getDescription())],
                array_map(fn(Keyword $k) => new Field($lang, $k->getNom()), $n->getTags()->toArray()),
                array_map(fn(TDocument $d) => new Source('LOMFRv1.0',$d->getNom()), $n->getDocTypes()->toArray()),
                $n->getRessLang()
            ),
            new LifeCycle([new Field($lang, "Première version")], new Source('LOMv1.0',"final"),
                array_map(fn(Auteur $a) => new Contribute(new Source('LOMv1.0',"Author"), [sprintf("BEGIN:VCARD VERSION:3.0 N:%s;%s;; FN:%s UID:%s END:vcard", $a->getNom(), $a->getPrenom(), $a->getName(), $a->getId())], [$n->getCreeLe()?->format('Y-m-d H:i:s')]), $n->getAuteurs()->toArray())
            ),
            new Metadata(new Catalog('URI',"oai:uoh.fr:uoh_".$n->getUuid()), [
                new Contribute(new Source('LOMv1.0',"creator"), [sprintf("BEGIN:VCARD VERSION:3.0 N:%s;; FN:%s UID:%s END:vcard", $creat->getEmail(), $creat->getName(), $creat->getId())], [$n->getEditeLe()?->format('Y-m-d H:i:s')]),
                new Contribute(new Source('LOMv1.0',"validator"), [sprintf("BEGIN:VCARD VERSION:3.0 N:%s;; FN:%s UID:%s END:vcard", $valid->getEmail(), $valid->getName(), $valid->getId())], [$n->getPublieLe()?->format('Y-m-d H:i:s')]),
                ], ['LOMv1.0', 'LOMFRv1.0', 'SupLOMFRv1.0'], (array)$n->getRessLang()),
            new Technical($n->getRessUrl(), $n->getRessSize(),$n->getDureExec()),
            new Educational(
                array_map(fn(TPedagogie $d) => new Source('LOMFRv1.0',$d->getNom()), $n->getPedTypes()->toArray()),
                array_map(fn(Niveau $d) => new Source('LOMFRv1.0',$d->getNom()), $n->getNiveaux()->toArray()), [$n->getDureAppr()], array_map(fn(string $p) => new Field($lang, $p), (array)$n->getPropUser()),
                (array)$n->getUserLang(),
            ),
            new Right(new Source('LOMFRv1.0',$n->isRessPayant()?'Yes':'No'),new Source('LOMFRv1.0',$n->isProprIntel()?'Yes':'No'), [new Field($lang, $n->getDroit())]),
            new Relation(new Source('LOMFRv1.0',"est associée à"), array_map(fn(Notice $r) => new Resource(new Catalog('URI',$r->getUuid()), array_map(fn(string $lang) => new Field($lang,$r->getTitre()),$r->getRessLang())),$n->getRessources()->toArray())),
            [
                new Classification(new Source('LOMv1.0',"specialite"), new TaxonPath([new Field($lang, 'Classification UVHC')], $discs)),
                new Classification(new Source('LOMv1.0',"dewey"), new TaxonPath([new Field($lang, 'CDD 22e éd.')], $dewey)),
            ]
        );
    }
}


class Source {
    public function __construct(
        #[Jms\Type('string'), Jms\XmlElement(cdata:false, namespace:"http://ltsc.ieee.org/xsd/LOM"),] public ?string $source = null,
        #[Jms\Type('string'), Jms\XmlElement(cdata:false, namespace:"http://ltsc.ieee.org/xsd/LOM"),] public ?string $value = null,
    ){}
}

class Catalog {
    public function __construct(
        #[Jms\Type('string'), Jms\XmlElement(cdata:false, namespace:"http://ltsc.ieee.org/xsd/LOM"),] public ?string $catalog = null,
        #[Jms\Type('string'), Jms\XmlElement(cdata:false, namespace:"http://ltsc.ieee.org/xsd/LOM"),] public ?string $entry = null,
    ){}
}

class Right {
    public function __construct(
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Source::class)] public ?Source $cost = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Source::class), Jms\SerializedName('copyrightAndOtherRestrictions')] public ?Source $copyrightAndOtherRestrictions = null,
        #[Jms\XmlList(entry: "string", namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Field::class.">"), Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM")] public array $description = [],
    ){}
}

class General {
    public function __construct(
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Catalog::class)] public ?Catalog $identifier = null,
        #[Jms\XmlList(entry: "string", namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Field::class.">"), Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM")] public array $title = [],
        #[Jms\XmlList(entry: "string", namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Field::class.">"), Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM")] public array $description = [],
        #[Jms\XmlList(entry: "string", namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Field::class.">"), Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM")] public array $keyword = [],
        #[Jms\XmlList(entry: "documentType", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Source::class.">")] public array $documentTypes = [],
        #[Jms\XmlList(entry: "language", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<string>"),Jms\XmlElement(cdata: false)] public array $languages = [],
        //#[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Source::class), Jms\SerializedName('aggregationLevel')] public ?Source $aggregationLevel = null,
    ){}
}

class Contribute {
    public function __construct(
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Source::class)] public ?Source $role = null,
        #[Jms\XmlList(entry: "entity", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<string>"), Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM")] public array $entities = [],
        #[Jms\XmlList(entry: "dateTime", namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<string>"), Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM")] public array $date = [],
    ) {}
}

class LifeCycle {
    public function __construct(
        #[Jms\XmlList(entry: "string", namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Field::class.">"), Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM")] public array $version = [],
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Source::class)] public ?Source $status = null,
        #[Jms\XmlList(entry: "contribute", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Contribute::class.">")] public array $contributes = [],
    ) {}
}

class Technical {
    public function __construct(
        //#[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("string")] public ?string $format = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("string")] public ?string $location = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("string")] public ?string $size = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("string")] public ?string $dureexec = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("string")] public ?string $install = null,
    ){}
}

class Educational {
    public function __construct(
        #[Jms\XmlList(entry: "learningResourceType", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Source::class.">")] public array $learningResourceTypes = [],
        #[Jms\XmlList(entry: "context", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Source::class.">")] public array $contexts = [],
        #[Jms\XmlList(entry: "duration", namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<string>"), Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM")] public array $date = [],
        #[Jms\XmlList(entry: "string", namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Field::class.">"), Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM")] public array $description = [],
        #[Jms\XmlList(entry: "language", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<string>"), Jms\XmlElement(cdata: false)] public array $languages = [],
        //#[Jms\XmlList(entry: "activity", inline: true, namespace: "http://www.lom-fr.fr/xsd/LOMFR"), Jms\Type("array<".Source::class.">")] public array $activities = [],
        //#[Jms\XmlList(entry: "intendedEndUserRole", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Source::class.">")] public array $intendedEndUserRoles = [],
    ){}
}

class Resource {
    public function __construct(
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Catalog::class)] public ?Catalog $identifier = null,
        #[Jms\XmlList(entry: "string", namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Field::class.">"), Jms\XmlElement(cdata: false)] public array $description = [],
    ){}
}

class Relation {
    public function __construct(
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Source::class)] public ?Source $kind = null,
        #[Jms\XmlList(entry: "resource", namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Resource::class.">"), Jms\XmlElement(cdata: false)] public array $resources = [],
    ){}
}

class Metadata {
    public function __construct(
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Catalog::class)] public ?Catalog $identifier = null,
        #[Jms\XmlList(entry: "contribute", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Contribute::class.">")] public array $contributes = [],
        #[Jms\XmlList(entry: "metadataSchema", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<string>"), Jms\XmlElement(cdata: false)] public array $metaDataSchemas = [],
        #[Jms\XmlList(entry: "language", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<string>"), Jms\XmlElement(cdata: false)] public array $languages = [],
    ){}
}

class Classification {
    public function __construct(
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Source::class)] public ?Source $purpose = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(TaxonPath::class), Jms\SerializedName('taxonPath')] public ?TaxonPath $taxonPath = null,
        #[Jms\XmlList(entry: "string", namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Field::class.">"), Jms\XmlElement(cdata: false)] public array $description = [],
    ){}
}

class TaxonPath {
    public function __construct(
        #[Jms\XmlList(entry: "string", namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Field::class.">"), Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM")] public array $source = [],
        #[Jms\XmlList(entry: "taxon", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Taxon::class.">"), Jms\XmlElement(cdata: false)] public array $taxons = [],
    ) {}
}

class Taxon {
    public function __construct(
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("string")] public ?string $id = null,
        #[Jms\XmlList(entry: "string", namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Field::class.">"), Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM")] public array $entry = [],
    ){}
}