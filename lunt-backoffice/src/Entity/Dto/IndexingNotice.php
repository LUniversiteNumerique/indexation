<?php

namespace App\Entity\Dto;

use App\Entity\{Auteur, Etablissement, Niveau, Notice};
use JMS\Serializer\Annotation\{SerializedName,
    Type,
    XmlAttribute,
    XmlElement,
    XmlList,
    XmlNamespace,
    XmlRoot,
    XmlValue};

readonly class IndexingNotice
{
    public function __construct(
        public ?string $uuid = null,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $formEvalUrl = null,
        public ?string $entrepot_logo = null,
        public ?string $vignette = null,
        public ?string $dewey = null,
        public ?string $specialite = null,
        public ?string $correspondant = null,
        public ?string $dureeApprentissage = null,
        public ?string $objectifsPedagogiques = null,
        public ?string $propositionUtilisation = null,
        public ?string $motsCles = null,
        public ?string $niveaux = null,
        public ?string $typesDocumentaires = null,
        public ?string $typesPedagogiques = null,
        public ?string $contributions = null,
        public ?string $etablissementPorteur = null,
        public ?string $languesUtilisateur = null,
        public ?string $languesPessource = null,
        public ?string $datePublication = null,
        public ?string $dateModification = null,
        public ?bool $ressourcePayante = null,
        public ?bool $proprieteIntellectuelle = null,
        public ?bool $exportOai = null,
        public bool $externalResource = true){}

    static function create(Notice $notice): IndexingNotice
    {
        return new self(
            $notice->getUuid(),
            $notice->getTitre(),
            $notice->getDescription(),
            $notice->getFormEvalUrl(),
            "none",
            $notice->getVignette(),
            $notice->getCodewey(),
            $notice->getSpecialite(),
            $notice->getCreateur(),
            $notice->getDureAppr(),
            $notice->getObjectif(),
            implode(", ", (array)$notice->getPropUser()),
            implode(", ", $notice->getTags()->toArray()),
            array_reduce($notice->getNiveaux()->toArray(),fn(string $tmp, Niveau $etab): string => $tmp.sprintf("%s, ",$etab->getNom()),""),
            implode(", ", $notice->getDocTypes()->toArray()),
            implode(", ", $notice->getPedTypes()->toArray()),
            array_reduce($notice->getAuteurs()->toArray(),fn(string $tmp, Auteur $etab): string => $tmp.sprintf("{nom=%s %s, email=%s}, ",$etab->getPrenom(),$etab->getNom(),$etab->getEmail()),""),
            array_reduce($notice->getPorteurs()->toArray(),fn(string $tmp, Etablissement $etab): string => $tmp.sprintf("%s, ",$etab->getNom()),""),
            implode(", ", (array)$notice->getUserLang()),
            implode(", ", (array)$notice->getRessLang()),
            $notice->getPublieLe()?->format('Y-m-d H:i:s'),
            $notice->getEditeLe()?->format('Y-m-d H:i:s'),
            $notice->isRessPayant(), $notice->isProprIntel(),
            $notice->isExportOai(), false,
        );
    }

    public function __toString(): string
    {
        $field = "";
        foreach ($this as $key => $value) {
            if ($key==='description') $value = strip_tags($value);
            $field .= sprintf("<field name='%s'>%s</field>", $key, $value===false ? 0 : $value);
        }
        return $field;
    }
}

#[
    XmlRoot("dc", namespace:"http://www.openarchives.org/OAI/2.0/oai_dc/", prefix: 'oai_dc'),
    XmlNamespace(uri:"http://www.w3.org/2001/XMLSchema-instance", prefix:"xsi"),
    XmlNamespace(uri:"http://purl.org/dc/elements/1.1/", prefix:"dc")
]
class OaidcDto
{
    #[XmlAttribute(namespace:"http://www.w3.org/2001/XMLSchema-instance"),Type('string'),SerializedName('schemaLocation')]
    public ?string $schemaLocation;

    public function __construct(
        #[XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"),Type('string')]
        public ?string $uuid = null,
        #[XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"),Type('string')]
        public ?string $identifier = null,
        #[XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"),Type('string')]
        public ?string $title = null,
        #[XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"),Type('string')]
        public ?string $description = null,
        #[XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"), Type('string')]
        public ?string $source = null,
        #[XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"), Type('string')]
        public ?string $rights = null,
        #[XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"), Type('string')]
        public ?string $date = null,

        #[XmlList(entry: "language", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Type("array<string>"),XmlElement(cdata: false)]
        public array $reslangs = [],
        #[XmlList(entry: "subject", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Type("array<string>"),XmlElement(cdata: false)]
        public array $keywords = [],
        #[XmlList(entry: "creator", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Type("array<string>"),XmlElement(cdata: false)]
        public array $auteurs = [],
        #[XmlList(entry: "publisher", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Type("array<string>"),XmlElement(cdata: false)]
        public array $porteurs = [],
        #[XmlList(entry: "format", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Type("array<string>"),XmlElement(cdata: false)]
        public array $doctypes = [],
        #[XmlList(entry: "type", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Type("array<string>"),XmlElement(cdata: false)]
        public array $pedtypes = [],
    ) {
        $this->schemaLocation = "http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd";
    }


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

#[XmlRoot("lom", namespace:"http://ltsc.ieee.org/xsd/LOM", prefix: 'lom'),
    XmlNamespace(uri:"http://www.w3.org/2001/XMLSchema-instance", prefix:"xsi"),
    XmlNamespace(uri:"http://www.lom-fr.fr/xsd/LOMFR", prefix:"lomfr")]
class SuplomDto
{
    #[XmlAttribute(namespace:"http://www.w3.org/2001/XMLSchema-instance"),Type('string'),SerializedName('schemaLocation')]
    public ?string $schemaLocation;
    public function __construct(
        #[XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Type('string')]
        public ?string $uuid = null,
        #[XmlList(entry: "language", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Type("array<string>"),XmlElement(cdata: false)]
        public array $language = [],
        #[XmlList(entry: "string", namespace: "http://ltsc.ieee.org/xsd/LOM"), Type("array<".Item::class.">"), XmlElement(cdata: false,namespace: "http://ltsc.ieee.org/xsd/LOM")]
        public array $title = [],
        #[XmlList(entry: "string", namespace: "http://ltsc.ieee.org/xsd/LOM"), Type("array<".Item::class.">"), XmlElement(cdata: false,namespace: "http://ltsc.ieee.org/xsd/LOM")]
        public array $description = [],
        #[XmlList(entry: "keyword", namespace: "http://ltsc.ieee.org/xsd/LOM"), Type("array<".Item::class.">"),XmlElement(cdata: false)]
        public array $keywords = [],
    ){
        $this->schemaLocation = "http://ltsc.ieee.org/xsd/LOM http://lom-fr.fr/xsd/lomfrv1.0/std/lomfr.xsd";
    }

    static function create(Notice $n): SuplomDto
    {
        $items = [];
        foreach ($n->getRessLang() as $lang) {
            $items[0][] =  new Item($lang, $n->getTitre());
            $items[1][] =  new Item($lang, $n->getDescription());
            $items[2][] =  array_map(fn(string $tag) => new Item($lang, $tag), $n->getTags()->toArray());
        }
        return new self(
            $n->getUuid(), $n->getRessLang(),
            $items[0], $items[1],$items[2]
        );
    }
}

class Item
{
    public function __construct(
        #[XmlAttribute, SerializedName('language')] public string $key,
        #[XmlValue(cdata: false)] public string $value
    ){}
}