<?php

namespace App\Entity\Dto;

use App\Entity\{Auteur, Notice, NoticEtat, Univerique};
use JMS\Serializer\Annotation as Jms;

#[Jms\XmlRoot("doc")]
class IndexingNotice
{
    public function __construct(#[Jms\XmlList(entry: "field", inline: true), Jms\Type("array<".Field::class.">")] public array $fields = []){}

    static function fromNotice(Notice $notice): IndexingNotice
    {
        $user = $notice->getCreateur(); $dewe = $notice->getCodewey();
        $core = $user->getUntheme(); $disc = $notice->getSpecialite();
        return new self([
            new Field('uuid', $notice->getUuid()),
            new Field('titre', $notice->getTitre()),
            new Field('entrepot_nom',$core->getLabel()),
            new Field('entrepot_logo', $user->getSchool()?->getLogo()),
            new Field('entrepot_url',"http://www.uoh.fr"),
            new Field('vignette', $notice->getVignette()),
            new Field('ressource_lien', $notice->getRessUrl()),
            new Field('description', $notice->getDescription()),
            new Field('dure_apprentissage', $notice->getDureAppr()),
            new Field('estampillage', $notice->getLabel()??''),
            new Field('objectifs_pedagogiques', $notice->getObjectif()),
            new Field('evaluation_form_url', $notice->getFormEvalUrl()),
            new Field('description_text', strip_tags($notice->getDescription())),
            new Field('date_creation', $notice->getRessDate()),
            new Field('mots_cles', implode(";", $notice->getTags()->toArray())),
            new Field('niveaux', implode(",", $notice->getNiveaux()->toArray())),
            new Field('types_pedagogiques', implode(",", $notice->getPedTypes()->toArray())),
            new Field('types_documentaires', implode(",", $notice->getDocTypes()->toArray())),
            new Field('proposition_utilisation', implode(";",(array)$notice->getPropUser())),
            new Field('dewey', json_encode(["id"=>$dewe?->getCode(), "libelle"=>$dewe?->getNom()])),
            new Field('specialites', sprintf("%s - %s", $disc?->getParent(), $disc?->getNom())),
            new Field('domaines', sprintf("%s/%s/%s",$disc?->getParent()?->getParent(),$disc?->getParent(), $disc)),
            new Field('correspondant', json_encode(["nom"=>$user?->getName(), "email"=>$user?->getEmail(), "etablissement"=>$user?->getSchool()])),
            new Field('contributions', json_encode(array_map(fn(Auteur $a) => ["prenom"=>$a->getPrenom(), "nom"=>$a->getNom(), "email"=>$a->getEmail()],$notice->getAuteurs()->toArray()))),
            new Field('associations_associate', json_encode(array_map(fn(Notice $n) => ["id"=>$n->getId(),"uuid"=>$n->getUuid(),"titre"=>$n->getTitre()], $notice->getRessources()->filter(fn(Notice $n) => !$n->isDeleted() && $n->getEtat()===NoticEtat::Approved)->toArray()))),
            new Field('etablissement_porteur', $user?->getSchool()? json_encode(["id"=>$user?->getSchool()->getId(), "libelle"=>$user?->getSchool()->getNom()]):""),
            new Field('etablissements_co_editeurs', json_encode(array_map("strval",$notice->getPorteurs()->toArray()))),
            new Field('date_modification', ($notice->getEditeLe()??$notice->getCreeLe())->format('Y-m-d H:i:s')),
            new Field('date_publication', ($notice->getPublieLe() ?? new \DateTime())->format('Y-m-d H:i:s')),
            new Field('langues_utilisateur', implode(",",(array)$notice->getUserLang())),
            new Field('langues_ressource', implode(",",(array)$notice->getRessLang())),
            new Field('propriete_intellectuelle', $notice->isProprIntel()?:0),
            new Field('ressource_payante', $notice->isRessPayant()?:0),
            new Field('exposition_oai', $notice->isExportOai()?:0),
            new Field('droit',  $notice->getDroit()?->getValeur()),
            new Field('champ_extension1', $notice->getChampExt1()),
            new Field('champ_extension2', $notice->getChampExt2()),
            new Field('champ_extension3', $notice->getChampExt3()),
            new Field('champ_extension4', $notice->getChampExt4()),
            new Field('champ_extension5', $notice->getChampExt5()),
            new Field('external_resource', 0)
        ]);
    }
    static function fromSuplom(SuplomDto $suplom, ?Univerique $core): IndexingNotice
    {
        /** @var Contribute $creat */ $creat = $suplom->metadata->contributes[0];
        /** @var Contribute $valid */ $valid = $suplom->metadata->contributes[1];

        $ator_teurs = [
            "author" => [],
            "publisher" => [],
            "validator" => [],
            "initiator" => []
        ]; /** @var Contribute $c */
        foreach ($suplom->lifeCycle?->contributes as $c) {
            $entities = self::getContribute($c->entities);
            if($c->role->value === "contributeur" || $c->role->value === "initiator")
                $ator_teurs["creator"] = $entities;
            else $ator_teurs[$c->role->value] = $entities;
        }

        foreach ($suplom->metadata?->contributes as $c) {
            $entities = self::getContribute($c->entities);
            if($c->role->value === "contributeur" || $c->role->value === "initiator")
                $ator_teurs["creator"] = $entities;
            else $ator_teurs[$c->role->value] = $entities;
        }

        $inotice = new self([
            new Field('uuid', substr($suplom->general?->identifier?->entry, -36)),
            new Field('titre', $suplom->general->title[0]?->value),
            new Field('entrepot_nom',$core?->getLabel()),
            new Field('entrepot_logo', $core?->getName()),
            new Field('entrepot_url', $core?->getSiteWeb()),
            new Field('vignette', null),
            new Field('description', $suplom->general->description[0]?->value),
            new Field('ressource_lien', $suplom->technical?->location),
            new Field('dure_execution', $suplom->technical?->dureexec),
            //new Field('dure_apprentissage', $suplom->technical?->location),
            //new Field('estampillage', $notice->getLabel()??''),
            //new Field('objectifs_pedagogiques', $notice->getObjectif()),
            //new Field('evaluation_form_url', $notice->getFormEvalUrl()),
            new Field('description_text', strip_tags($suplom->general->description[0]?->value)),
            //new Field('date_creation', $notice->getRessDate()),
            new Field('mots_cles', array_reduce($suplom->general?->keywords, fn(string $acc, Motcle $s) => $acc.$s->string?->value.", ", "")),
            new Field('niveaux', array_reduce($suplom->educational?->contexts, fn(string $acc, Source $s) => $acc.$s->value.", ", '')),
            new Field('types_documentaires', array_reduce($suplom->general?->documentTypes, fn(string $acc, Source $s) => $acc.$s->value.", ", "")),
            new Field('types_pedagogiques', array_reduce($suplom->educational?->learningResourceTypes, fn(string $acc, Source $s) => $acc.$s->value.", ", "")),
            new Field('proposition_utilisation', array_reduce($suplom->educational?->description, fn(string $acc, Field $f) => $acc.$f->value.", ", '')),

            new Field('correspondant', $ator_teurs["creator"]),
            new Field('validateur', $ator_teurs["validator"]),
            new Field('contributions', array_reduce(array_unique($ator_teurs["author"]),fn(string $tmp, string $etab): string => $tmp.sprintf("%s, ",$etab),"")),
            //new Field('associations_associate', array_map(fn(Resource $r) => sprintf('%s|%s', $r->identifier->entry, $r->description[0]?->value), $suplom->relation?->resources)),
            new Field('etablissement_porteurs', array_reduce(array_unique($ator_teurs["publisher"]),fn(string $tmp, string $etab): string => $tmp.sprintf("%s, ",$etab),"")),
            //new Field('date_modification', $creat?->date[0]), new Field('date_publication', $valid?->date[0]),

            new Field('langues_utilisateur', implode(', ',$suplom->educational->languages)),
            new Field('langues_ressource', implode(', ',$suplom->general->languages)),

            new Field('propriete_intellectuelle', $suplom->rights?->copyrightAndOtherRestrictions?->value!=='No'),
            new Field('ressource_payante', $suplom->rights?->cost?->value!=='No'),
            new Field('droit', $suplom->rights?->description[0]?->value),

            new Field('exposition_oai', 0),
            new Field('external_resource', 1),
        ]);

        $taxons = [];
        /** @var Classification $class */
        foreach ($suplom->classifications as $class) {
            if($class->taxonPath->source[0]->value)
            array_map(fn(Taxon $taxon) => $taxon->entry[0]?->value, $class->taxonPath->taxons);
        }
        $inotice->fields[] = new Field('specialites', $taxons['discipline']);
        return $inotice;
    }

    private static function getContribute(array $c): array
    {
        $entities = explode("ORG:", $c[0]);
        if (count($entities) == 2 && !str_contains($entities[0],';;;')) {
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
            "https://www.uoh.fr",
            $n->getDroit(),
            $n->getRessDate(),
            $n->getRessLang(),
            $n->getTags()->toArray(),
            $n->getAuteurs()->toArray(),
            $n->getPorteurs()->toArray(),
            $n->getDocTypes()->toArray(),
            $n->getPedTypes()->toArray(),
        );
    }
}