<?php

namespace App\Entity\Dto;

use App\Entity\{Auteur, Etablissement, Notice, Univerique};
use JMS\Serializer\Annotation as Jms;

#[Jms\XmlRoot("doc")]
class IndexingNotice
{
    public function __construct(#[Jms\XmlList(entry: "field", inline: true), Jms\Type("array<".Field::class.">")] public array $fields = []){}

    static function fromNotice(Notice $notice): IndexingNotice
    {
        $user = $notice->getCreateur();
        $dewe = $notice->getCodewey(); $disc = $notice->getSpecialite();
        $core = $notice->getValidateur()?->getUntheme();
        $indx = new self([
            new Field('uuid', $notice->getUuid()),
            new Field('titre', $notice->getTitre()),
            new Field('vignette', $notice->getVignette()),
            new Field('description', $notice->getDescription()),
            new Field('description_text', strip_tags($notice->getDescription())),
            new Field('dure_apprentissage', $notice->getDureAppr()),
            new Field('objectifs_pedagogiques', $notice->getObjectif()),
            new Field('evaluation_form_url', $notice->getFormEvalUrl()),
            new Field('etablissement_porteur', $user?->getSchool()??''),
            new Field('droit', $notice->getDroit()),
            new Field('entrepot_nom',$core?->getLabel()),
            new Field('entrepot_logo', $core?->getName()),
            new Field('entrepot_url',"http://www.uoh.fr"),
            new Field('ressource_lien', $notice->getRessUrl()),
            new Field('estampillage', $notice->getLabel()??''),
            new Field('date_creation', $notice->getRessDate()?->format('Y')),
            new Field('mots_cles', implode(", ", $notice->getTags()->toArray())),
            new Field('niveaux', implode(", ", $notice->getNiveaux()->toArray())),
            new Field('proposition_utilisation', implode(", ", (array)$notice->getPropUser())),
            new Field('types_pedagogiques', implode(", ", $notice->getPedTypes()->toArray())),
            new Field('types_documentaires', implode(", ", $notice->getDocTypes()->toArray())),
            new Field('dewey', sprintf("{id=%s, libelle=%s}, ",$dewe?->getCode(),$dewe?->getNom())),
            new Field('specialite', sprintf("{id=%s, libelle=%s}, ",$disc?->getCode(),$disc?->getNom())),
            new Field('correspondant', sprintf("{nom:%s, email:%s, etablissement:%s}, ",$user?->getName(),$user?->getEmail(),$user?->getSchool())),
            new Field('contributions', array_reduce($notice->getAuteurs()->toArray(),fn(string $tmp, Auteur $etab): string => $tmp.sprintf("{prenom:%s, nom:%s, email:%s}, ",$etab->getPrenom(),$etab->getNom(),$etab->getEmail()),"")),
            new Field('etablissements_co_editeurs', array_reduce($notice->getPorteurs()->toArray(),fn(string $tmp, Etablissement $etab): string => $tmp.sprintf("%s, ",$etab->getNom()),"")),
            new Field('date_modification', ($notice->getEditeLe()??$notice->getCreeLe())->format('Y-m-d H:i:s')),
            new Field('date_publication', ($notice->getPublieLe() ?? new \DateTime())->format('Y-m-d H:i:s')),
            new Field('associations_associate', implode(", ", $notice->getRessources()->toArray())),
            new Field('langues_utilisateur', implode(", ", (array)$notice->getUserLang())),
            new Field('langues_ressource', implode(", ", (array)$notice->getRessLang())),
            new Field('propriete_intellectuelle', $notice->isProprIntel()),
            new Field('ressource_payante', $notice->isRessPayant()),
            new Field('exposition_oai', $notice->isExportOai()),
            new Field('external_resource', false),
        ]);

        if ($notice->getChampExt1()) $indx->fields[] = new Field('champ_extension1', $notice->getChampExt1());
        if ($notice->getChampExt2()) $indx->fields[] = new Field('champ_extension2', $notice->getChampExt2());
        if ($notice->getChampExt3()) $indx->fields[] = new Field('champ_extension3', $notice->getChampExt3());
        if ($notice->getChampExt4()) $indx->fields[] = new Field('champ_extension4', $notice->getChampExt4());
        if ($notice->getChampExt5()) $indx->fields[] = new Field('champ_extension5', $notice->getChampExt5());
        return $indx;
    }
    static function fromSuplom(SuplomDto $suplom, ?Univerique $core): IndexingNotice
    {
        /** @var Contribute $creat */ $creat = $suplom->metadata->contributes[0];
        /** @var Contribute $valid */ $valid = $suplom->metadata->contributes[1];

        $auteurs = []; $porteurs = []; /** @var Contribute $c */
        foreach ($suplom->lifeCycle?->contributes as $c) {
            $entities = self::getContribute($c->entities);
            if (!empty($entities)) list($auteurs[], $porteurs[]) = $entities;
        }

        $inotice = new self([
            new Field('entrepot_nom',$core?->getLabel()),
            new Field('entrepot_logo', $core?->getName()),
            new Field('entrepot_url',"http://www.uoh.fr"),
            new Field('vignette', 'default_value.png'),

            new Field('uuid', substr($suplom->general?->identifier?->entry, -36)),
            new Field('titre', $suplom->general->title[0]?->value),
            new Field('description', $suplom->general->description[0]?->value),
            new Field('langues_ressource', implode(', ',$suplom->general->languages)),
            new Field('mots_cles', array_reduce($suplom->general?->keyword, fn(string $acc, Field $s) => $acc.$s->value.", ", "")),
            new Field('types_documentaires', array_reduce($suplom->general?->documentTypes, fn(string $acc, Source $s) => $acc.$s->value.", ", "")),

            new Field('etablissements_co_editeurs', array_reduce($porteurs,fn(string $tmp, string $etab): string => $tmp.sprintf("%s, ",$etab),"")),
            new Field('contributions', array_reduce($auteurs,fn(string $tmp, string $etab): string => $tmp.sprintf("%s, ",$etab),"")),
            new Field('date_modification', $creat?->date[0]),
            new Field('date_publication', $valid?->date[0]),

            new Field('types_pedagogiques', array_reduce($suplom->educational?->learningResourceTypes, fn(string $acc, Source $s) => $acc.$s->value.", ", "")),
            new Field('niveaux', array_reduce($suplom->educational?->contexts, fn(string $acc, Source $s) => $acc.$s->value.", ", '')),
            new Field('proposition_utilisation', array_reduce($suplom->educational?->description, fn(string $acc, Field $f) => $acc.$f->value.", ", '')),
            //new Field('dure_apprentissage', $suplom->educational?->date[0]),
            new Field('langues_utilisateur', implode(', ',$suplom->educational->languages)),

            new Field('propriete_intellectuelle', $suplom->rights?->copyrightAndOtherRestrictions?->value!=='No'),
            new Field('ressource_payante', $suplom->rights?->cost?->value!=='No'),
            new Field('droit', $suplom->rights?->description[0]?->value),

            new Field('ressource_lien', $suplom->technical?->location),
            //new Field('associations_associate', array_map(fn(Resource $r) => sprintf('%s|%s', $r->identifier->entry, $r->description[0]?->value), $suplom->relation?->resources)),
            new Field('exposition_oai', true),
            new Field('external_resource', true),
        ]);

        /** @var Contribute $c */
        foreach ($suplom->metadata?->contributes as $c) {
            $entities = self::getContribute($c->entities);
            if (!empty($entities)) {
                list($auteur, $porteur) = $entities;
                if ($c->role->value == 'creator') {
                    $inotice->fields[] = new Field('correspondant', $auteur);
                    $inotice->fields[] = new Field('etablissement_porteur', $porteur);
                }elseif ($c->role->value == 'validator')
                    $inotice->fields[] = new Field('validateur', $auteur);
            }
        }

        /** @var Classification $class */
        foreach ($suplom->classifications as $class) {
            if($class->purpose && isset($class->taxonPath) && count($class->taxonPath->taxons) >0) {
                /** @var Taxon $taxon */
                $taxon = $class->taxonPath->taxons[0];
                $inotice->fields[] = new Field($class->purpose->value, sprintf("{id=%s, libelle=%s}, ",$taxon?->id??'',$taxon?->entry[0]?->value));
            }
        }
        return $inotice;
    }

    private static function getContribute(array $c): array
    {
        $entities = explode("ORG:", $c[0]);
        if (count($entities) == 2) {
            $entity = str_replace("VERSION:3.0",'{nom:',$entities[0]);
            $entity = str_replace("FN:",', email:',$entity);
            $entity = str_replace(['BEGIN:VCARD',';;','N:', '\n'],'',$entity);
            $entity = str_replace(' UID:',', id=',$entity);
            return [sprintf("%s}",trim($entity)), str_replace('END:VCARD', '', trim($entities[1]))];
        }
        return array();
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
        #[Jms\XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"),Jms\Type('string')] public ?string $uuid = null,
        #[Jms\XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"),Jms\Type('string')] public ?string $identifier = null,
        #[Jms\XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"),Jms\Type('string')] public ?string $title = null,
        #[Jms\XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"),Jms\Type('string')] public ?string $description = null,
        #[Jms\XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"), Jms\Type('string')] public ?string $source = null,
        #[Jms\XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"), Jms\Type('string')] public ?string $rights = null,
        #[Jms\XmlElement(cdata:false, namespace:"http://purl.org/dc/elements/1.1/"), Jms\Type('string')] public ?string $date = null,

        #[Jms\XmlList(entry: "language", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"),Jms\XmlElement(cdata: false)] public array $reslangs = [],
        #[Jms\XmlList(entry: "subject", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"),Jms\XmlElement(cdata: false)] public array $keywords = [],
        #[Jms\XmlList(entry: "creator", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"),Jms\XmlElement(cdata: false)] public array $auteurs = [],
        #[Jms\XmlList(entry: "publisher", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"),Jms\XmlElement(cdata: false)] public array $porteurs = [],
        #[Jms\XmlList(entry: "format", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"),Jms\XmlElement(cdata: false)] public array $doctypes = [],
        #[Jms\XmlList(entry: "type", inline: true, namespace: "http://purl.org/dc/elements/1.1/"), Jms\Type("array<string>"),Jms\XmlElement(cdata: false)] public array $pedtypes = [],
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