<?php

namespace App\Message\Handler;

use App\Repository\NoticeRepository;
use App\Entity\Dto\IndexingNotice;
use App\Service\{Converter\PrefixNameConverter, FileService, SolrApiService, XmlDataLoader};
use App\Message\IntexingConfigMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class InterIndexingHandler
{
    const PREFIX_SF = 'lom', PREFIX_DC = 'dc';
    private XmlDataLoader $sfLoader, $dcLoader;
    public function __construct(
        private NoticeRepository $nr,
        private SolrApiService $sm,
        private FileService $fs
    ){
        $this->sfLoader = new XmlDataLoader(new PrefixNameConverter(self::PREFIX_SF.":"));
        $this->dcLoader = new XmlDataLoader(new PrefixNameConverter(self::PREFIX_DC.":"));
    }

    public function __invoke(IntexingConfigMessage $message): void
    {
        if ($message->isFullExec && $this->fs->removeFilesFrom('dc/') && $this->fs->removeFilesFrom('sf/'))
            $this->sm->delDocuments("<query>external_resource:false</query>");
        $sources = $this->nr->findBy(['id' => $message->itemIds]);

        //Itération sur les items de la source => iterateOverSource();
        $itemSF = []; $itemSP = ""; $itemDC = [];
        foreach ($sources as $notice) {
            $item = IndexingNotice::create($notice);
            $itemSP .= "<doc>$item</doc>";                                                               //SolrPivotBuildingAnalyzer

            $itemDC[sprintf("dc_%s.xml",$item->getUuid())] = $this->dcLoader->encode([
                "@xmlns:oai_dc" => "http://www.openarchives.org/OAI/2.0/oai_dc/",
                "@xmlns:dc" => "http://purl.org/dc/elements/1.1/",
                "@xmlns:xsi" => "http://www.w3.org/2001/XMLSchema-instance",
                "@xsi:schemaLocation" => "http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd",
                '#' => $item
            ],sprintf("oai_%s:%s",self::PREFIX_DC,self::PREFIX_DC));//DublinCoreExportAnalyzer
            $itemSF[sprintf("sf_%s.xml",$item->getUuid())] = $this->sfLoader->encode([
                '@xmlns:lom' => "http://ltsc.ieee.org/xsd/LOM",
                '@xmlns:lomfr' => "http://www.lom-fr.fr/xsd/LOMFR",
                '@xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
                '@xsi:schemaLocation' => "http://ltsc.ieee.org/xsd/LOM http://lom-fr.fr/xsd/lomfrv1.0/std/lomfr.xsd",
                '#' => $item
            ],self::PREFIX_SF.":".self::PREFIX_SF); //SuplomfrExportAnalyzer
        }

        // Enregistrement des différents documents dans solr et sur les disques
        $this->sm->addDocuments($itemSP);                  //Indexation dans Solr
        $this->fs->writeFilesTo($itemDC,'dc/'); // Write the XML to the file
        $this->fs->writeFilesTo($itemSF,'sf/'); //$this->messageBus->dispatch(new RunCommandMessage($itemDC,$itemSF));
    }
}