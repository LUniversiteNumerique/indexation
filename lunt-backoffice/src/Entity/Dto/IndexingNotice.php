<?php

namespace App\Entity\Dto;

use App\Entity\{Auteur, DeweyGroup, DeweyPerso, Discipline, DisciplineGroup, Notice, NoticEtat, Univerique};
use DateTime;
use JMS\Serializer\Annotation as Jms;

/**
 * DTO pour l'indexation d'une notice.
 */
#[Jms\XmlRoot("doc")]
class IndexingNotice
{
    /**
     * @var Field[]
     */
    #[Jms\XmlList(entry: "field", inline: true), Jms\Type("array<".Field::class.">")]
    public array $fields = [];

    /**
     * @param Field[] $fields
     */
    public function __construct(array $fields = [])
    {
        $this->fields = $fields;
    }

    /**
     * Crée un IndexingNotice à partir d'une Notice.
     *
     * @param Notice $notice
     * @param Univerique $core
     * @return IndexingNotice
     */
    public static function fromNotice(Notice $notice, Univerique $core): IndexingNotice
    {
        $user = $notice->getCreateur();
        $deweyGroups = $notice->getDeweyGroups();
        $disciplineGroups = $notice->getDisciplineGroups();
        $deweyPersos = $notice->getDeweyPersos();

        return new self([
            new Field($notice->getUuid(), null, 'uuid'),
            new Field($notice->getTitre(), null, 'titre'),
            new Field($core->getLabel(), null, 'entrepot_nom'),
            new Field($user->getSchool()?->getLogo(), null, 'entrepot_logo'),
            new Field($core->getSiteWeb(), null, 'entrepot_url'),
            new Field($notice->getVignette(), null, 'vignette'),
            new Field($notice->getRessUrl(), null, 'ressource_lien'),
            new Field($notice->getDescription(), null, 'description'),
            new Field($notice->getDureAppr(), null, 'dure_apprentissage'),
            new Field($notice->getLabel() ?? '', null, 'estampillage'),
            new Field($notice->getObjectif(), null, 'objectifs_pedagogiques'),
            new Field($notice->getFormEvalUrl(), null, 'evaluation_form_url'),
            new Field(strip_tags($notice->getDescription()), null, 'description_text'),
            new Field($notice->getRessDate(), null, 'date_creation'),
            new Field(implode(";", $notice->getTags()->toArray()), null, 'mots_cles'),
            new Field(implode(",", $notice->getNiveaux()->toArray()), null, 'niveaux'),
            new Field(implode(",", $notice->getPedTypes()->toArray()), null, 'types_pedagogiques'),
            new Field(implode(",", $notice->getDocTypes()->toArray()), null, 'types_documentaires'),
            new Field(implode(";", (array)$notice->getPropUser()), null, 'proposition_utilisation'),
            new Field(json_encode(array_map(function (DeweyGroup $group) {
                return [
                    "id" => $group->getDewey()?->getCode(),
                    "libelle" => $group->getDewey()?->getNom()
                ];
            }, $deweyGroups->toArray())), null, 'dewey'),
            new Field(json_encode(array_map(fn(DeweyPerso $deweyPerso) => [
                "id" => $deweyPerso->getCode(),
                "libelle" => $deweyPerso->getNom()
            ], $deweyPersos->toArray())), null, 'deweyPerso'),
            new Field(json_encode(array_merge(...array_map(function (DisciplineGroup $group) {
                return array_map(fn(Discipline $spec) => [
                    "id" => $spec->getCode(),
                    "libelle" => $spec->getNom()
                ], $group->getSpecialites()->toArray());
            }, $disciplineGroups->toArray()))), null, 'specialites'),
            new Field(json_encode([
                "nom" => $user?->getName(),
                "email" => $user->getEmail(),
                "etablissement" => $user->getSchool()
            ]), null, 'correspondant'),
            new Field(json_encode(array_map(fn(Auteur $auteur) => [
                "prenom" => $auteur->getPrenom(),
                "nom" => $auteur->getNom(),
                "email" => $auteur->getEmail() ?? ''
            ], $notice->getAuteurs()->toArray())), null, 'contributions'),
            new Field(json_encode(array_map(fn(Notice $notice) => [
                "id" => $notice->getId(),
                "uuid" => $notice->getUuid(),
                "titre" => $notice->getTitre()
            ], $notice->getRessources()->filter(fn(Notice $notice) => !$notice->isDeleted() && $notice->getEtat() === NoticEtat::Approved)->toArray())), null, 'associations_associate'),
            new Field($user->getSchool() ? json_encode([
                "id" => $user->getSchool()->getId(),
                "libelle" => $user->getSchool()->getNom()
            ]) : "", null, 'etablissement_porteur'),
            new Field(json_encode(array_map("strval", $notice->getPorteurs()->toArray())), null, 'etablissements_co_editeurs'),
            new Field(($notice->getEditeLe() ?? $notice->getCreeLe())->format('Y-m-d H:i:s'), null, 'date_modification'),
            new Field(($notice->getPublieLe() ?? new DateTime())->format('Y-m-d H:i:s'), null, 'date_publication'),
            new Field(implode(",", (array)$notice->getUserLang()), null, 'langues_utilisateur'),
            new Field(implode(",", (array)$notice->getRessLang()), null, 'langues_ressource'),
            new Field($notice->isProprIntel() ?: 0, null, 'propriete_intellectuelle'),
            new Field($notice->isRessPayant() ?: 0, null, 'ressource_payante'),
            new Field($notice->isExportOai() ?: 0, null, 'exposition_oai'),
            new Field($notice->getDroit()?->getValeur(), null, 'droit'),
            new Field($notice->getChampExt1(), null, 'champ_extension1'),
            new Field($notice->getChampExt2(), null, 'champ_extension2'),
            new Field($notice->getChampExt3(), null, 'champ_extension3'),
            new Field($notice->getChampExt4(), null, 'champ_extension4'),
            new Field($notice->getChampExt5(), null, 'champ_extension5'),
            new Field(0, null, 'external_resource')
        ]);
    }

    /**
     * Crée un IndexingNotice à partir d'un SuplomDto.
     *
     * @param SuplomDto $suplom
     * @param Univerique|null $core
     * @return IndexingNotice
     */
    public static function fromSuplom(SuplomDto $suplom, ?Univerique $core): IndexingNotice
    {
        $fields = [
            new Field(substr($suplom->general?->identifier?->entry, -36), null, 'uuid'),
            new Field($suplom->general->title[0]?->value, null, 'titre'),
            new Field($core?->getLabel(), null, 'entrepot_nom'),
            new Field($core?->getName(), null, 'entrepot_logo'),
            new Field($core?->getSiteWeb(), null, 'entrepot_url'),
            new Field(null, null, 'vignette'),
            new Field($suplom->general->description[0]?->value, null, 'description'),
            new Field($suplom->technical?->location, null, 'ressource_lien'),
            new Field($suplom->technical?->duration?->duration, null, 'dure_execution'),
            new Field(strip_tags($suplom->general->description[0]?->value), null, 'description_text'),
            new Field(implode(', ', $suplom->general->languages), null, 'langues_ressource'),
            new Field(implode(', ', $suplom->educational->languages), null, 'langues_utilisateur'),
            new Field($suplom->educational->typicalLearningTime?->duration, null, 'dure_apprentissage'),
            new Field(array_reduce($suplom->general?->keywords, fn(string $acc, Motcle $s) => $acc . trim($s->string?->value) . ", ", ""), null, 'mots_cles'),
            new Field(array_reduce($suplom->educational?->contexts, fn(string $acc, Source $s) => $acc . $s->value . ", ", ""), null, 'niveaux'),
            new Field(array_reduce(array_merge($suplom->general?->documentTypesLOMFR, $suplom->general?->documentTypesLOM), fn(string $acc, Source $s) => $acc . $s->value . ", ", ""), null, 'types_documentaires'),
            new Field(array_reduce($suplom->educational?->learningResourceTypes , fn(string $acc, Source $s) => $acc . $s->value . ", ", ""), null, 'types_pedagogiques'),
            new Field(array_reduce($suplom->educational?->description, fn(string $acc, Motcle $s) => $acc . trim($s->string?->value) . ", ", ""), null, 'proposition_utilisation'),
            new Field(strtolower($suplom->rights?->copyrightAndOtherRestrictions?->value ?? '') !== "no", null, 'propriete_intellectuelle'),
            new Field(strtolower($suplom->rights?->cost?->value ?? '') !== "no", null, 'ressource_payante'),
            new Field($suplom->rights?->description[0]?->value, null, 'droit'),
            new Field(0, null, 'exposition_oai'),
            new Field(1, null, 'external_resource'),
        ];

        if (isset($suplom->technical?->size)) {
            $fields[] = new Field($suplom->technical?->size, null, 'ressource_taille');
        }

        $contributors = [
            "author" => [],
            "publisher" => [],
            "validator" => [],
            "creator" => []
        ];

        foreach ($suplom->metadata?->contributes ?? [] as $contributor) {
            $entities = self::getContribute($contributor->entities);
            if ($contributor->role->value === "contributeur" || $contributor->role->value === "initiator") {
                $contributors["creator"] = $entities;
            } else {
                $contributors[$contributor->role->value] = $entities;
            }
        }

        foreach ($suplom->lifeCycle?->contributes ?? [] as $contribute) {
            $entities = self::getContribute($contribute->entities);
            if ($contribute->role->value === "contributeur" || $contribute->role->value === "initiator") {
                $contributors["creator"] = $entities;
            } else {
                $contributors[$contribute->role->value] = $entities;
            }
        }

        $fields[] = new Field($contributors["creator"], null, 'correspondant');
        $fields[] = new Field($contributors["validator"], null, 'validateur');
        $fields[] = new Field(array_reduce(array_unique($contributors["author"]), fn(string $tmp, string $etab): string => $tmp . sprintf("%s, ", $etab), ""), null, 'contributions');
        $fields[] = new Field(array_reduce(array_unique($contributors["publisher"]), fn(string $tmp, string $etab): string => $tmp . sprintf("%s, ", $etab), ""), null, 'etablissement_porteurs');

        foreach ($suplom->classifications ?? [] as $class) {
            $key = array_reduce($class->taxonPath->source ?? [], fn(string $a, Field $s) => "$a $s->value", "");
            $value = array_map(fn(Taxon $taxon) => $taxon->entry[0]?->value, $class->taxonPath->taxons ?? []);

            if (str_contains($key, 'lassification')) {
                $fields[] = new Field($value, null, 'specialites');
            }
            if (str_contains($key, 'CDD 22')) {
                $fields[] = new Field($value, null, 'dewey');
            }
        }

        return new self($fields);
    }

    /**
     * Extrait les contributeurs d'un tableau d'entités.
     *
     * @param array $entities
     * @return array
     */
    private static function getContribute(array $entities): array
    {
        $parts = explode("ORG:", $entities[0] ?? '');
        if (count($parts) === 2 && !str_contains($parts[0], ';;;')) {
            $entity = str_replace("VERSION:3.0", '{nom:', $parts[0]);
            $entity = str_replace("FN:", ', email:', $entity);
            $entity = str_replace(['BEGIN:VCARD', ';;', 'N:', '\n'], '', $entity);
            $entity = str_replace(' UID:', ', id=', $entity);
            return [sprintf("%s}", trim($entity)), str_replace('END:VCARD', '', trim($parts[1]))];
        }
        return [];
    }
}

/**
 * DTO pour l'export OAI-DC.
 */
#[
    Jms\XmlRoot("dc", namespace: "http://www.openarchives.org/OAI/2.0/oai_dc/", prefix: 'oai_dc'),
    Jms\XmlNamespace(uri: "http://www.w3.org/2001/XMLSchema-instance", prefix: "xsi"),
    Jms\XmlNamespace(uri: "http://purl.org/dc/elements/1.1/", prefix: "dc")
]
class OaidcDto
{
    #[Jms\XmlAttribute(namespace: "http://www.w3.org/2001/XMLSchema-instance"), Jms\Type('string'), Jms\SerializedName('schemaLocation')]
    public ?string $schemaLocation;

    public function __construct(
        #[Jms\XmlElement(cdata: false, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type('string')] public ?string                                                     $uuid = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type('string')] public ?string                                                     $identifier = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type('string')] public ?string                                                     $title = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type('string')] public ?string                                                     $description = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type('string')] public ?string                                                     $source = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type('string')] public ?string                                                     $rights = null,
        #[Jms\XmlElement(cdata: false, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type('string')] public ?string                                                     $date = null,
        #[Jms\XmlList(entry: "language", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"), Jms\XmlElement(cdata: false)] public array  $reslangs = [],
        #[Jms\XmlList(entry: "subject", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"), Jms\XmlElement(cdata: false)] public array   $keywords = [],
        #[Jms\XmlList(entry: "creator", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"), Jms\XmlElement(cdata: false)] public array   $auteurs = [],
        #[Jms\XmlList(entry: "publisher", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"), Jms\XmlElement(cdata: false)] public array $porteurs = [],
        #[Jms\XmlList(entry: "format", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"), Jms\XmlElement(cdata: false)] public array    $doctypes = [],
        #[Jms\XmlList(entry: "type", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"), Jms\XmlElement(cdata: false)] public array      $pedtypes = [],
    )
    {
        $this->schemaLocation = "http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd";
    }

    /**
     * Crée un OaidcDto à partir d'une Notice.
     *
     * @param Notice $notice
     * @return OaidcDto
     */
    public static function create(Notice $notice): OaidcDto
    {
        return new self(
            $notice->getUuid(),
            "https://www.uoh.fr/front/noticefr/?uuid=" . $notice->getUuid(),
            $notice->getTitre(),
            $notice->getDescription(),
            "https://www.uoh.fr",
            $notice->getDroit(),
            $notice->getRessDate(),
            $notice->getRessLang(),
            $notice->getTags()->toArray(),
            $notice->getAuteurs()->toArray(),
            $notice->getPorteurs()->toArray(),
            $notice->getDocTypes()->toArray(),
            $notice->getPedTypes()->toArray(),
        );
    }
}
