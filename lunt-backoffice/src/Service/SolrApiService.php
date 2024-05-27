<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\{Exception\ExceptionInterface, HttpClientInterface, ResponseInterface};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

class SolrApiService
{
    public function __construct(
        #[Autowire(env: 'SOLR_BASE_URL')]
        private readonly string     $solrBaseUrl,
    private HttpClientInterface $client)
    {
        $this->client = $client->withOptions([
            'base_uri' => $this->solrBaseUrl,
            'headers' => [
                'Accept' => 'application/xml',
                'Content-Type' => 'application/xml'
            ],
        ]);
    }

    public function getDocuments($data = '*:*'): ?array
    {
        $resp = $this->handleApi([], 'GET',"select?q=$data");
        if($resp && $resp->getStatusCode() === Response::HTTP_OK)
            return $resp->toArray()["response"];
        return null;
    }
    public function addDocuments($data): ?string
    {
        $resp = $this->handleApi(['body' => "<add>$data</add>",]);
        if($resp && $resp->getStatusCode() === Response::HTTP_OK)
            return $resp->getContent();
        return null;
    }
    public function delDocuments($data = '<query>*:*</query>'): ?string //'<id>eddae4db-7ceb-4d26-97e6-0cf35cb8bc25</id>'
    {
        $resp = $this->handleApi(['body' => "<delete>$data</delete>"]);
        if($resp && $resp->getStatusCode() === Response::HTTP_OK)
            return $resp->getContent();
        return null;
    }

    private function handleApi(array $options = [], string $method = 'POST', string $url='update?commit=true'): ?ResponseInterface
    {
        try { return $this->client->request($method, $url, $options); }catch (ExceptionInterface $e) {
            dump($e->getMessage());
            return null;
        }
    }
}