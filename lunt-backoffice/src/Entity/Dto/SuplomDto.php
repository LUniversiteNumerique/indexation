<?php

namespace App\Entity\Dto;

use App\Entity\{Auteur, Dewey, DeweyPerso, Discipline, Keyword, Niveau, Notice, TDocument, TPedagogie};
use JMS\Serializer\Annotation as Jms;

#[Jms\XmlRoot("lom:lom"), Jms\XmlNamespace(uri: "http://ltsc.ieee.org/xsd/LOM", prefix: 'lom'), Jms\XmlNamespace(uri: "http://www.w3.org/2001/XMLSchema-instance", prefix: "xsi"), Jms\XmlNamespace(uri: "http://www.lom-fr.fr/xsd/LOMFR", prefix: "lomfr")]
class SuplomDto
{
    public const RESOURCE_URI = "http://orioai.univ-valenciennes.fr/";

    #[Jms\XmlAttribute(namespace: "http://www.w3.org/2001/XMLSchema-instance"), Jms\Type('string'), Jms\SerializedName('schemaLocation')]
    public ?string $schemaLocation;

    public function __construct(
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(General::class)] public ?General                                                                          $general = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(LifeCycle::class), Jms\SerializedName('lifeCycle')] public ?LifeCycle                                     $lifeCycle = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(MetaData::class), Jms\SerializedName('metaMetadata')] public ?MetaData                                    $metadata = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Technical::class)] public ?Technical                                                                      $technical = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Educational::class)] public ?Educational                                                                  $educational = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Right::class)] public ?Right                                                                              $rights = null,
        #[Jms\XmlList(entry: "relation", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<" . Relation::class . ">")] public array                                           $relations = [],
        #[Jms\XmlList(entry: "classification", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<" . Classification::class . ">"), Jms\XmlElement(cdata: false)] public array $classifications = [],
    )
    {
        $this->schemaLocation = "http://ltsc.ieee.org/xsd/LOM http://lom-fr.fr/xsd/lomfrv1.0/std/lomfr.xsd";
    }

    /**
     * Crée un SuplomDto à partir d'une Notice.
     *
     * @param Notice $notice
     * @return self
     */
    public static function create(Notice $notice): self
    {
        $lang = 'fre';
        $creator = $notice->getCreateur();
        $validator = $notice->getValidateur();

        $disciplines = array_map(
            fn(Discipline $discipline) => new Taxon($discipline->getId(), [new Field($discipline->getNom(), $lang, null)]),
            $notice->getAllSpecialites()
        );
        $deweys = array_map(
            fn(Dewey $dewey) => new Taxon($dewey->getCode(), [new Field($dewey->getNom(), $lang, null)]),
            $notice->getAllDeweyGroup()
        );
        $deweyPersos = array_map(
            fn(DeweyPerso $deweyPerso) => new Taxon($deweyPerso->getCode(), [new Field($deweyPerso->getNom(), $lang, null)]),
            $notice->getAllDeweyPerso()
        );

        $general = new General(
            new Catalog('URI', self::RESOURCE_URI . $notice->getUuid()),
            [new Field($notice->getTitre(), $lang, null)],
            [new Field($notice->getDescription(), $lang, null)],
            array_map(fn(Keyword $keyword) => new Motcle(new Field($keyword->getNom(), $lang, null)), $notice->getTags()->toArray()),
            array_map(fn(TDocument $typeDoc) => new Sources('LOMFRv1.0', $typeDoc->getCode()), $notice->getDocTypes()->toArray()),
            [],
            $notice->getRessLang()
        );

        $lifeCycle = new LifeCycle(
            [new Field("Première version", $lang, null)],
            new Source('LOMv1.0', "final"),
            array_map(
                fn(Auteur $author) => new Contribute(
                    new Source('LOMv1.0', "author"),
                    [sprintf(
                        "BEGIN:VCARD\r\nVERSION:3.0\r\nN:%s;%s;;;\r\nFN:%s\r\nEND:VCARD",
                        $author->getNom(),
                        $author->getPrenom(),
                        $author->getName()
                    )],
                    [$notice->getCreeLe()?->format('Y-m-d H:i:s')]
                ),
                $notice->getAuteurs()->toArray()
            )
        );

        $creatorFullName = $creator->getName();
        $creatorParts = explode(' ', $creatorFullName, 2);
        // Si "prénom nom", $parts[1] est le nom, sinon c'est $parts[0]
        $creatorFamilyName = $creatorParts[1] ?? $creatorParts[0];
        // Si "prénom nom", $parts[0] est le prénom, sinon vide
        $creatorGivenName = isset($creatorParts[1]) ? $creatorParts[0] : '';
        $creatorORG = $creator->getSchool() ? "\r\nORG:" . $creator->getSchool()->getNom() : "";

        $validatorFullName = $validator->getName();
        $validatorParts = explode(' ', $validatorFullName, 2);
        // Si "prénom nom", $parts[1] est le nom, sinon c'est $parts[0]
        $validatorFamilyName = $validatorParts[1] ?? $validatorParts[0];
        // Si "prénom nom", $parts[0] est le prénom, sinon vide
        $validatorGivenName = isset($validatorParts[1]) ? $validatorParts[0] : '';
        $validatorORG = $validator->getUntheme() ? "\r\nORG:" . $validator->getUntheme()->getName() : "";

        $metadata = new Metadata(
            new Catalog('URI', $notice->getUuid()),
            [
                new Contribute(
                    new Source('LOMv1.0', "creator"),
                    [sprintf(
                        "BEGIN:VCARD\r\nVERSION:3.0\r\nN:%s;%s;;;\r\nFN:%s%s\r\nEND:VCARD",
                        $creatorFamilyName,
                        $creatorGivenName,
                        $creatorFullName,
                        $creatorORG
                    )],
                    [$notice->getEditeLe()?->format('Y-m-d H:i:s')]
                ),
                new Contribute(
                    new Source('LOMv1.0', "validator"),
                    [sprintf(
                        "BEGIN:VCARD\r\nVERSION:3.0\r\nN:%s;%s;;;\r\nFN:%s%s\r\nEND:VCARD",
                        $validatorFamilyName,
                        $validatorGivenName,
                        $validatorFullName,
                        $validatorORG
                    )],
                    [$notice->getPublieLe()?->format('Y-m-d H:i:s')]
                ),
            ],
            ['LOMv1.0', 'LOMFRv1.0', 'SupLOMFRv1.0'],
            (array)$notice->getRessLang()
        );

        $technical = new Technical(
            $notice->getRessUrl(),
            $notice->getRessSize(),
            new Duration($notice->getDureExec())
        );

        $educational = new Educational(
            array_map(fn(TPedagogie $typePed) => new Source('LOMv1.0', $typePed->getSuplom()), $notice->getPedTypes()->toArray()),
            array_map(fn(Niveau $level) => new Source('LOMv1.0', $level->getCode()), $notice->getNiveaux()->toArray()),
            array_map(fn(string $keyword) => new Motcle(new Field($keyword, $lang, null)), $notice->getPropUser()),
            (array)$notice->getUserLang(),
            new Duration($notice->getDureAppr())
        );

        $rights = new Right(
            new Source('LOMv1.0', $notice->isRessPayant() ? 'Yes' : 'No'),
            new Source('LOMv1.0', $notice->isProprIntel() ? 'Yes' : 'No'),
            [new Field($notice->getDroit() ?? "", $lang, null)]
        );

        $relations = array_map(
            fn(Notice $relation) => new Relation(
                new Source('LOMv1.0', "ispartof"),
                new Resource(
                    new Catalog('URI', $relation->getUuid()),
                    array_map(fn(string $lang) => new Field($relation->getTitre(), $lang, null), $relation->getRessLang())
                )
            ),
            $notice->getRessources()->toArray()
        );

        $classifications = [
            new Classification(
                new Source('LOMv1.0', "discipline"),
                [new TaxonPath([new Field('Classification', $lang, null)], $disciplines)]
            ),
            new Classification(
                new Source('LOMv1.0', "dewey"),
                [new TaxonPath([new Field('CDD 22e éd.', $lang, null)], array_merge($deweys, $deweyPersos))]
            ),
            new Classification(
                new Source('LOMv1.0', "pedagogie"),
                [],
                [new Motcle(new Field($notice->getObjectif() ?? "", $lang, null))]
            ),
        ];

        return new self(
            $general,
            $lifeCycle,
            $metadata,
            $technical,
            $educational,
            $rights,
            $relations,
            $classifications
        );
    }

    /**
     * Parse une chaîne vCard et retourne un tableau associatif des champs.
     *
     * @param string $vcardString
     * @return array<string, string>
     */
    public static function parseVCard(string $vcardString): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $vcardString);
        $vcardData = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_contains($line, ':')) {
                list($key, $value) = explode(':', $line, 2);
                $vcardData[$key] = $value;
                if ($key === 'N') {
                    $parts = explode(';', $value);
                    $vcardData['LASTNAME'] = $parts[0] ?? '';
                    $vcardData['FIRSTNAME'] = $parts[1] ?? '';
                }
            }
        }
        return $vcardData;
    }

    /**
     * Extrait les rôles contributeurs depuis le DTO suplom.
     *
     * @param SuplomDto $item Les données importées du fichier XML.
     * @return array Tableau associatif des rôles et de leurs informations (nom, prénom, organisation, email, etc.).
     */
    public static function extractRoles(SuplomDto $item): array
    {
        $contributes = array_merge(
            $item->metadata?->contributes,
            $item->lifeCycle?->contributes
        );
        $roleNotice = [];
        foreach ($contributes as $contribute) {
            if (isset($contribute->entities[0])) {
                $role = $contribute->role->value ?? null;
                $vcardFields = SuplomDto::parseVCard($contribute->entities[0]);
                $firstName = $vcardFields['FIRSTNAME'] ?? '';
                $lastName  = $vcardFields['LASTNAME'] ?? '';
                $org       = $vcardFields['ORG'] ?? '';
                $email     = $vcardFields['EMAIL'] ?? '';
                $fn        = $vcardFields['FN'] ?? '';
                $name      = trim($firstName . ' ' . $lastName) ?: $org ?: $fn;

                // Stocke toutes les infos utiles pour ce rôle
                $roleNotice[$role][] = [
                    'name'      => $name,
                    'firstname' => $firstName,
                    'lastname'  => $lastName,
                    'org'       => $org,
                    'email'     => $email,
                    'fn'        => $fn,
                    'date'      => $contribute->date[0] ?? null,
                ];
            }
        }
        return $roleNotice;
    }
}


class Source {
    public function __construct(
        #[Jms\Type('string'), Jms\XmlElement(cdata:false, namespace:"http://ltsc.ieee.org/xsd/LOM"),] public ?string $source = null,
        #[Jms\Type('string'), Jms\XmlElement(cdata:false, namespace:"http://ltsc.ieee.org/xsd/LOM"),] public ?string $value = null,
    ){}
}
class Sources {
    public function __construct(
        #[Jms\Type('string'), Jms\XmlElement(cdata:false, namespace:"http://www.lom-fr.fr/xsd/LOMFR"),] public ?string $source = null,
        #[Jms\Type('string'), Jms\XmlElement(cdata:false, namespace:"http://www.lom-fr.fr/xsd/LOMFR"),] public ?string $value = null,
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
        #[Jms\XmlList(entry: "keyword", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Motcle::class.">")] public array $keywords = [],
        #[Jms\XmlList(entry: "documentType", inline: true, namespace: "http://www.lom-fr.fr/xsd/LOMFR"), Jms\Type("array<".Sources::class.">")] public array $documentTypesLOMFR = [],
        #[Jms\XmlList(entry: "documentType", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Source::class.">")] public array $documentTypesLOM = [],
        #[Jms\XmlList(entry: "language", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<string>"),Jms\XmlElement(cdata: false)] public array $languages = [],
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
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("string")] public ?string $location = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("string")] public ?string $size = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Duration::class)] public ?Duration $duration = null,
    ){}
}

class Educational {
    public function __construct(
        #[Jms\XmlList(entry: "learningResourceType", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Source::class.">")] public array $learningResourceTypes = [],
        #[Jms\XmlList(entry: "context", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Source::class.">")] public array $contexts = [],
        #[Jms\XmlList(entry: "description", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Motcle::class.">")] public array $description = [],
        #[Jms\XmlList(entry: "language", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<string>"), Jms\XmlElement(cdata: false)] public array $languages = [],
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Duration::class), Jms\SerializedName('typicalLearningTime')] public ?Duration $typicalLearningTime = null,
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
        #[Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type(Resource::class)] public ?Resource $resource = null,
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
        #[Jms\XmlList(entry: "taxonPath", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".TaxonPath::class.">"), Jms\XmlElement(cdata: false)] public array $taxonPathes = [],
        #[Jms\XmlList(entry: "description", inline: true, namespace: "http://ltsc.ieee.org/xsd/LOM"), Jms\Type("array<".Motcle::class.">")] public array $description = [],
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

class Motcle
{
    public function __construct(#[Jms\Type(Field::class), Jms\XmlElement(cdata: false, namespace: "http://ltsc.ieee.org/xsd/LOM")] public ?Field $string = null){}
}

class Duration
{
    public function __construct(#[Jms\Type('string'), Jms\XmlElement(cdata:false, namespace:"http://ltsc.ieee.org/xsd/LOM"),] public ?string $duration = null){}
}