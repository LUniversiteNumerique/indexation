<?php

namespace App\Service;

use App\Entity\Dto\IndexingNotice;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\DomCrawler\Crawler;

readonly class XmlDataLoader
{
    public function __construct(private SerializerInterface $serializer)
    {}

    private function getCrawler($domDoc): Crawler
    {
        return new Crawler($domDoc);
    }

    public function parse(string $fileContent): array
    {
        $crawler = $this->getCrawler(file_get_contents($fileContent))
            ->filter('table>item'); $objects = array();
        foreach ($crawler as $item) {
            $item_crawler = $this->getCrawler($item); $properties = [];
            foreach ($item_crawler->children() as $item_prop)
                $properties[$item_prop->getAttribute('key')] = $item_prop->nodeValue;
            $item_id = $properties['id']; //$item_crawler->attr('level'); //uri
            $objects[$item_id] = $properties;
        }
        return $objects;
    }

    public function encode(array $data, string $rootXml): string
    {
        return $this->serializer->serialize($data, 'xml');//XmlEncoder::FORMAT, [XmlEncoder::FORMAT_OUTPUT => true, XmlEncoder::ENCODING => 'UTF-8', XmlEncoder::ROOT_NODE_NAME => $rootXml,]);
    }

    public function decode($item, string $type = IndexingNotice::class)
    {
        return $this->serializer->deserialize($item, $type, 'xml');
    }
}