<?php

namespace App\Entity\Dto;

use App\Entity\{Auteur, Notice};
use JMS\Serializer\Annotation as Jms;

#[Jms\XmlRoot("doc")]
class IndexingNotice
{
    public function __construct(#[Jms\XmlList(entry: "field", inline: true), Jms\Type("array<".Field::class.">")] public array $fields = []){}

    static function create(Notice $notice): IndexingNotice
    {
        $core = $notice->getValidateur()?->getUntheme();
        $etab = $notice->getCreateur()?->getSchool();
        return new self([
            new Field('uuid', $notice->getUuid()),
            new Field('titre', $notice->getTitre()),
            new Field('vignette', $notice->getVignette()),
            new Field('specialite', $notice->getSpecialite()),
            new Field('correspondant', $notice->getCreateur()),
            new Field('description', $notice->getDescription()),
            new Field('dure_apprentissage', $notice->getDureAppr()),
            new Field('objectifs_pedagogiques', $notice->getObjectif()),
            new Field('evaluation_form_url', $notice->getFormEvalUrl()),
            new Field('etablissement_porteur', $etab??''),
            new Field('entrepot_nom',$core->getLabel()),
            new Field('dewey', $notice->getCodewey()),
            new Field('droit', $notice->getDroit()),
            new Field('entrepot_logo', $core->getName()),
            new Field('entrepot_url',"http://www.uoh.fr"),
            new Field('ressource_lien', $notice->getRessUrl()),
            new Field('estampillage', $notice->getLabel()??''),
            new Field('date_creation', $notice->getRessDate()?->format('Y')),
            new Field('mots_cles', implode(", ", $notice->getTags()->toArray())),
            new Field('niveaux', implode(", ", $notice->getNiveaux()->toArray())),
            new Field('proposition_utilisation', implode(", ", (array)$notice->getPropUser())),
            new Field('types_pedagogiques', implode(", ", $notice->getPedTypes()->toArray())),
            new Field('types_documentaires', implode(", ", $notice->getDocTypes()->toArray())),
            new Field('etablissements_co_editeurs', implode(", ", $notice->getPorteurs()->toArray())),
            new Field('date_modification', ($notice->getEditeLe()??$notice->getCreeLe())->format('Y-m-d H:i:s')),
            new Field('date_publication', ($notice->getPublieLe() ?? new \DateTime())->format('Y-m-d H:i:s')),
            new Field('associations_associate', implode(", ", $notice->getRessources()->toArray())),
            new Field('langues_utilisateur', implode(", ", (array)$notice->getUserLang())),
            new Field('langues_ressource', implode(", ", (array)$notice->getRessLang())),
            new Field('contributions', implode(", ", $notice->getAuteurs()->toArray())),
            new Field('propriete_intellectuelle', $notice->isProprIntel()),
            new Field('ressource_payante', $notice->isRessPayant()),
            new Field('exposition_oai', $notice->isExportOai()),
            new Field('external_resource', false),
            //new Field('fichiers_attaches', []),
        ]);
    }
}

#[
    Jms\XmlRoot("dc", namespace:"http://www.openarchives.org/OAI/2.0/oai_dc/", prefix: 'oai_dc'),
    Jms\XmlNamespace(uri:"http://www.w3.org/2001/XMLSchema-instance", prefix:"xsi"),
    Jms\XmlNamespace(uri:"http://purl.org/dc/elements/1.1/", prefix:"dc")
]
class OaidcDto
{
    #[Jms\XmlAttribute(namespace:"http://www.w3.org/2001/XMLSchema-instance"),Jms\Type('string'),Jms\SerializedName('schemaLocation')]
    public ?string $schemaLocation;

    public function __construct(
        #[Jms\XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"),Jms\Type('string')]
        public ?string $uuid = null,
        #[Jms\XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"),Jms\Type('string')]
        public ?string $identifier = null,
        #[Jms\XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"),Jms\Type('string')]
        public ?string $title = null,
        #[Jms\XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"),Jms\Type('string')]
        public ?string $description = null,
        #[Jms\XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"), Jms\Type('string')]
        public ?string $source = null,
        #[Jms\XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"), Jms\Type('string')]
        public ?string $rights = null,
        #[Jms\XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"), Jms\Type('string')]
        public ?string $date = null,

        #[Jms\XmlList(entry: "language", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"),Jms\XmlElement(cdata: false)]
        public array $reslangs = [],
        #[Jms\XmlList(entry: "subject", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"),Jms\XmlElement(cdata: false)]
        public array $keywords = [],
        #[Jms\XmlList(entry: "creator", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"),Jms\XmlElement(cdata: false)]
        public array $auteurs = [],
        #[Jms\XmlList(entry: "publisher", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"),Jms\XmlElement(cdata: false)]
        public array $porteurs = [],
        #[Jms\XmlList(entry: "format", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"),Jms\XmlElement(cdata: false)]
        public array $doctypes = [],
        #[Jms\XmlList(entry: "type", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"),Jms\XmlElement(cdata: false)]
        public array $pedtypes = [],
    ) { $this->schemaLocation = "http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd"; }


    static function create(Notice $n): OaidcDto
    {
        return new self(
            $n->getUuid(),
            "https://www.uoh.fr/front/noticefr/?uuid=".$n->getUuid(),
            $n->getTitre(),
            $n->getDescription(),
            "https://www.uoh.fr/",
            $n->getDroit(),
            $n->getRessDate()?->format('Y'),
            $n->getRessLang(),
            $n->getTags()->toArray(),
            $n->getAuteurs()->toArray(),
            $n->getPorteurs()->toArray(),
            $n->getDocTypes()->toArray(),
            $n->getPedTypes()->toArray(),
        );
    }
}

#[
    Jms\XmlRoot("lom", namespace:"http://ltsc.ieee.org/xsd/LOM", prefix: 'lom'),
    Jms\XmlNamespace(uri:"http://www.w3.org/2001/XMLSchema-instance", prefix:"xsi"),
    Jms\XmlNamespace(uri:"http://www.lom-fr.fr/xsd/LOMFR", prefix:"lomfr")]
class SuplomDto
{
    #[Jms\XmlAttribute(namespace:"http://www.w3.org/2001/XMLSchema-instance"),Jms\Type('string'),Jms\SerializedName('schemaLocation')]
    public ?string $schemaLocation;
    public function __construct(
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type('string')]
        public ?string $uuid = null,
        #[Jms\XmlList(entry: "language", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<string>"),Jms\XmlElement(cdata: false)]
        public array $language = [],
        #[Jms\XmlList(entry: "string", namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Field::class.">"), Jms\XmlElement(cdata: false,namespace: "http://ltsc.ieee.org/xsd/LOM")]
        public array $title = [],
        #[Jms\XmlList(entry: "string", namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Field::class.">"), Jms\XmlElement(cdata: false,namespace: "http://ltsc.ieee.org/xsd/LOM")]
        public array $description = [],
        #[Jms\XmlList(entry: "keyword", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Items::class.">")] public array $keywords = [],
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Catalog::class)] public ?Catalog $identifier = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Source::class), Jms\SerializedName('documentType')] public ?Source $documentType = null,
    ){ $this->schemaLocation = "http://ltsc.ieee.org/xsd/LOM http://lom-fr.fr/xsd/lomfrv1.0/std/lomfr.xsd"; }

    static function create(Notice $n): SuplomDto
    {
        $items = [];
        foreach ($n->getRessLang() as $lang) {
            $items[0][] = new Field($lang, $n->getTitre());
            $items[1][] = new Field($lang, $n->getDescription());
            $items[2][] = new Items(array_map(fn(string $tag) => new Field($lang, $tag), $n->getTags()->toArray()));
        }
        return new self(
            $n->getUuid(), $n->getRessLang(),
            $items[0], $items[1],$items[2],
            new Catalog('URI',"http://orioai.univ-valenciennes.fr/uid/uvhc-ori-oai-wf-1-97"),
            new Source('LOMFRv1.0',"image en mouvement"),
        );
    }
}

class Field
{
    public function __construct(
        #[Jms\XmlAttribute, Jms\SerializedName('name')] public string $key,
        #[Jms\XmlValue(cdata: false)] public null|string|bool|array $value
    ){}
}
class Items
{
    public function __construct(
        #[Jms\XmlList(entry: "string", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"),
            Jms\XmlElement(cdata: false,namespace: "http://ltsc.ieee.org/xsd/LOM"),
            Jms\Type("array<".Field::class.">")] public array $item = [],
    ){}
}

class Source
{
    public function __construct(
        #[Jms\Type('string'), Jms\XmlElement(cdata:false, namespace:"http://ltsc.ieee.org/xsd/LOM"),]
        public ?string $source = null,
        #[Jms\Type('string'), Jms\XmlElement(cdata:false, namespace:"http://ltsc.ieee.org/xsd/LOM"),]
        public ?string $value = null,
    ){}
}
class Catalog
{
    public function __construct(
        #[Jms\Type('string'), Jms\XmlElement(cdata:false, namespace:"http://ltsc.ieee.org/xsd/LOM"),]
        public ?string $catalog = null,
        #[Jms\Type('string'), Jms\XmlElement(cdata:false, namespace:"http://ltsc.ieee.org/xsd/LOM"),]
        public ?string $entry = null,
    ){}
}

class Sources
{
    public function __construct(
        #[Jms\XmlList(entry: "string", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"),
            Jms\XmlElement(cdata: false,namespace: "http://ltsc.ieee.org/xsd/LOM"),
            Jms\Type("array<".Source::class.">")] public array $sources = [],
    ){}
}