<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\{Exception\ExceptionInterface, HttpClientInterface, ResponseInterface};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

class SolrApiService
{
    public function __construct(
        private HttpClientInterface $client,
        #[Autowire(env: 'SOLR_BASE_URL')]
        private readonly string     $solrUrl)
    {
        $this->client = $client->withOptions([
            'base_uri' => $this->solrUrl,
            'headers' => ['Content-Type' => 'application/xml'],
        ]);
    }

    public function getDocuments($data = ['q'=>'*:*'], string $url = 'unt1/select'): ?array
    {
        $resp = $this->handleApi($url, ['query' => $data], 'GET');
        if($resp && $resp->getStatusCode() === Response::HTTP_OK)
            return $resp->toArray()["response"];
        return null;
    }
    public function addDocuments($data, string $url='unt1/update?commit=true'): ?string
    {
        $resp = $this->handleApi($url, ['body' => "<add>$data</add>",]);
        if($resp && $resp->getStatusCode() === Response::HTTP_OK)
            return $resp->getContent();
        return null;
    }
    public function editDocuments($data, string $url='unt1/update?commit=true'): ?string
    {
        $resp = $this->handleApi($url, ['body' => "<update>$data</update>",]);
        if($resp && $resp->getStatusCode() === Response::HTTP_OK)
            return $resp->getContent();
        return null;
    }
    public function delDocuments($data = '<query>*:*</query>', string $url='unt1/update?commit=true'): ?string //'<id>eddae4db-7ceb-4d26-97e6-0cf35cb8bc25</id>'
    {
        $resp = $this->handleApi($url, ['body' => "<delete>$data</delete>"]);
        if($resp && $resp->getStatusCode() === Response::HTTP_OK)
            return $resp->getContent();
        return null;
    }

    private function handleApi(string $url, array $options = [], string $method = 'POST'): ?ResponseInterface
    {
        try { return $this->client->request($method, $url, $options); }catch (ExceptionInterface $e) {
            dump($e);
            return null;
        }
    }
}