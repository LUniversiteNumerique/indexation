<?php

namespace App\Entity\Dto;

use App\Entity\{Notice, NoticEtat, Univerique};
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
        $createur = $notice->getCreateur();

        $fields = [
            new Field($notice->getUuid(), null, 'uuid'),
            new Field($notice->getTitre(), null, 'titre'),
            new Field($core->getLabel(), null, 'entrepot_nom'),
            new Field($createur->getSchool()?->getLogo(), null, 'entrepot_logo'),
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
            new Field(json_encode([
                "nom" => $createur?->getName(),
                "email" => $createur->getEmail(),
                "etablissement" => $createur->getSchool()
            ]), null, 'correspondant'),
            new Field($createur->getSchool() ? json_encode([
                "id" => $createur->getSchool()->getId(),
                "libelle" => $createur->getSchool()->getNom()
            ]) : "", null, 'etablissement_porteur'),
            new Field(($notice->getEditeLe() ?? $notice->getCreeLe())->format('Y-m-d H:i:s'), null, 'date_modification'),
            new Field(($notice->getPublieLe() ?? new DateTime())->format('Y-m-d H:i:s'), null, 'date_publication'),
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
            // dure_execution ?
            // ressource_taille ?
            // validateur ?
        ];
        // Champs multivalués
        $motsCles = $notice->getTags()->toArray();
        foreach ($motsCles as $motCle) {
            $fields[] = new Field($motCle, null, 'mots_cles');
        }
        $niveaux = $notice->getNiveaux()->toArray();
        foreach ($niveaux as $niveau) {
            $fields[] = new Field($niveau, null, 'niveaux');
        }
        $tPeds = $notice->getPedTypes()->toArray();
        foreach ($tPeds as $tPed) {
            $fields[] = new Field($tPed, null, 'types_pedagogiques');
        }
        $tDocs = $notice->getDocTypes()->toArray();
        foreach ($tDocs as $tDoc) {
            $fields[] = new Field($tDoc, null, 'types_documentaires');
        }
        $propUsers = $notice->getPropUser();
        foreach ($propUsers as $propUser) {
            $fields[] = new Field($propUser, null, 'proposition_utilisation');
        }
        $deweyGroups = $notice->getDeweyGroups()->toArray();
        foreach ($deweyGroups as $group) {
            $fields[] = new Field(json_encode([
                "id" => $group->getDewey()?->getCode(),
                "libelle" => $group->getDewey()?->getNom()
            ]), null, 'dewey');
        }
        $deweyPersos = $notice->getDeweyPersos()->toArray();
        foreach ($deweyPersos as $deweyPerso) {
            $fields[] = new Field(json_encode([
                "id" => $deweyPerso->getCode(),
                "libelle" => $deweyPerso->getNom()
            ]), null, 'dewey');
        }
        $disciplineGroups = $notice->getDisciplineGroups()->toArray();
        foreach ($disciplineGroups as $group) {
            $specs = $group->getSpecialites()->toArray();
            foreach ($specs as $spec) {
                $fields[] = new Field(json_encode([
                    "id" => $spec->getCode(),
                    "libelle" => $spec->getNom()
                ]), null, 'specialite');
            }
        }
        $auteurs = $notice->getAuteurs()->toArray();
        foreach ($auteurs as $auteur) {
            $fields[] = new Field(json_encode([
                "prenom" => $auteur->getPrenom(),
                "nom" => $auteur->getNom(),
                "email" => $auteur->getEmail() ?? ''
            ]), null, 'contributions');
        }
        $noticesAssociees = $notice->getRessources()->filter(fn(Notice $notice) => !$notice->isDeleted() && $notice->getEtat() === NoticEtat::Approved)->toArray();
        foreach ($noticesAssociees as $associe) {
            $fields[] = new Field(json_encode([
                "id" => $associe->getId(),
                "uuid" => $associe->getUuid(),
                "titre" => $associe->getTitre()
            ]), null, 'associations_associate');
        }
        $porteurs = $notice->getPorteurs()->toArray();
        foreach ($porteurs as $porteur) {
            $fields[] = new Field(strval($porteur), null, 'etablissements_co_editeurs');
        }
        $userLangs = $notice->getUserLang();
        foreach ($userLangs as $userLang) {
            $fields[] = new Field($userLang, null, 'langues_utilisateur');
        }
        $ressLangs = $notice->getRessLang();
        foreach ($ressLangs as $ressLang) {
            $fields[] = new Field($ressLang, null, 'langues_ressource');
        }

        return new self($fields);
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
            new Field($suplom->technical?->location, null, 'ressource_lien'),
            new Field($suplom->general->description[0]?->value, null, 'description'),
            new Field($suplom->educational->typicalLearningTime?->duration ?? null, null, 'dure_apprentissage'),
            new Field(strip_tags($suplom->general->description[0]?->value), null, 'description_text'),
            // etablissement_porteur ?
            new Field(strtolower($suplom->rights?->copyrightAndOtherRestrictions?->value ?? '') !== "no", null, 'propriete_intellectuelle'),
            new Field(strtolower($suplom->rights?->cost?->value ?? '') !== "no", null, 'ressource_payante'),
            new Field(0, null, 'exposition_oai'),
            new Field($suplom->rights?->description[0]?->value, null, 'droit'),
            new Field(1, null, 'external_resource'),
            new Field($suplom->technical?->duration?->duration ?? null, null, 'dure_execution'),
        ];
        // Champs multivalués
        $motsCles = $suplom->general?->keywords;
        foreach ($motsCles as $motCle) {
            $value = $motCle->string?->value;
            if (isset($value)) {
                $fields[] = new Field($value, null, 'mots_cles');
            }
        }
        $niveaux = $suplom->educational?->contexts;
        foreach ($niveaux as $niveau) {
            $fields[] = new Field($niveau->value, null, 'niveaux');
        }
        $tPeds = $suplom->educational?->learningResourceTypes;
        foreach ($tPeds as $tPed) {
            $fields[] = new Field($tPed->value, null, 'types_pedagogiques');
        }
        $tDocs = array_merge($suplom->general?->documentTypesLOMFR, $suplom->general?->documentTypesLOM);
        foreach ($tDocs as $tDoc) {
            $fields[] = new Field($tDoc->value, null, 'types_documentaires');
        }
        $propUsers = $suplom->educational?->description;
        foreach ($propUsers as $propUser) {
            $value = $propUser->string?->value;
            if (isset($value)) {
                $fields[] = new Field($value, null, 'proposition_utilisation');
            }
        }
        // associations_associate ?
        $userLangs = $suplom->educational->languages;
        foreach ($userLangs as $userLang) {
            $fields[] = new Field($userLang, null, 'langues_utilisateur');
        }
        $ressLangs = $suplom->general->languages;
        foreach ($ressLangs as $ressLang) {
            $fields[] = new Field($ressLang, null, 'langues_ressource');
        }

        if (isset($suplom->technical?->size)) {
            $fields[] = new Field($suplom->technical?->size, null, 'ressource_taille');
        }

        $roles = SuplomDto::extractRoles($suplom);

        $contributors = [
            "author" => [],
            "publisher" => [],
            "validator" => [],
            "creator" => []
        ];

        $dateCreation = null;
        $datePublicationPublisher = null;
        $datePublicationValidator = null;

        foreach ($roles as $role => $contributes) {
            foreach ($contributes as $contribute) {
                if ($role === 'contributeur' || $role === 'initiator') {
                    $contributors['creator'][] = json_encode([
                        'nom' => $contribute['name'],
                        'email' => $contribute['email']
                    ]);
                } else {
                    $contributors[$role][] = json_encode([
                        'nom' => $contribute['name'],
                        'email' => $contribute['email']
                    ]);
                }

                if (!isset($dateCreation) && $role === 'author' && isset($contribute['date'])) {
                    $dateCreation = (new DateTime($contribute['date']))->format('Y');
                    $fields[] = new Field($dateCreation, null, 'date_creation');
                }
                if (!isset($datePublicationPublisher) && $role === 'publisher' && isset($contribute['date'])) {
                    $datePublicationPublisher = $contribute['date'];
                }
                if (!isset($datePublicationValidator) && $role === 'validator' && isset($contribute['date'])) {
                    $datePublicationValidator = $contribute['date'];
                }
            }
        }

        if (isset($datePublicationPublisher)) {
            $fields[] = new Field($datePublicationPublisher, null, 'date_publication');
        } elseif (isset($datePublicationValidator)) {
            $fields[] = new Field($datePublicationValidator, null, 'date_publication');
        }

        $fields[] = new Field(reset($contributors["creator"]), null, 'correspondant');
        $fields[] = new Field(reset($contributors["validator"]), null, 'validateur');

        $auteurs = $contributors["author"];
        foreach ($auteurs as $auteur) {
            $fields[] = new Field($auteur, null, 'contributions');
        }
        $porteurs = $contributors["publisher"];
        foreach ($porteurs as $porteur) {
            $fields[] = new Field($porteur, null, 'etablissements_co_editeurs');
        }

        foreach ($suplom->classifications ?? [] as $class) {
            $key = array_reduce($class->taxonPath->source ?? [], fn(string $a, Field $s) => "$a $s->value", "");
            $value = array_map(fn(Taxon $taxon) => $taxon->entry[0]?->value, $class->taxonPath->taxons ?? []);

            if (!isset($class->taxonPathes) && str_contains($class->purpose?->value, 'educational')) {
                $fields[] = new Field($class->description[0]?->string->value, null, 'objectifs_pedagogiques');
            }
            if (str_contains($key, 'lassification')) {
                $fields[] = new Field($value, null, 'specialite');
            }
            if (str_contains($key, 'CDD 22')) {
                $fields[] = new Field($value, null, 'dewey');
            }
        }

        return new self($fields);
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
