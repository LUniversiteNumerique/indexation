<?php

namespace App\Service;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\{SerializerInterface,Encoder\XmlEncoder};

readonly class XmlDataLoader
{
    public function __construct(private SerializerInterface $serializer) {}

    public function parseXML(string $fileContent): array
    {
        $crawler = ($this->getCrawler(file_get_contents($fileContent)))
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

    public function encodeXML(object $data, string $xmlRoot, int $status = 200, array $headers = []): Response
    {
        return new Response(
            $this->serializer->serialize($data, XmlEncoder::FORMAT, [
                XmlEncoder::ROOT_NODE_NAME => $xmlRoot,
                XmlEncoder::ENCODING => 'UTF-8',
            ]), $status, array_merge($headers, ['Content-Type' => 'application/xml;charset=UTF-8'])
        );
    }

    public function decodeXML($xmlData = '<root>...</root>')
    {
        return $this->serializer->deserialize($xmlData, XmlFile::class, 'xml');
    }

    private function getCrawler($domDoc): Crawler
    {
        return new Crawler($domDoc);
    }
}