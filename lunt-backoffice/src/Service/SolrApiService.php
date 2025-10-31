<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\{HttpClientInterface, ResponseInterface};
use Symfony\Contracts\HttpClient\Exception\{ClientExceptionInterface, DecodingExceptionInterface, ExceptionInterface, RedirectionExceptionInterface, ServerExceptionInterface, TransportExceptionInterface};
use Symfony\Component\HttpFoundation\Response;

/**
 * Service pour interagir avec l'API Solr.
 */
readonly class SolrApiService
{
    public function __construct(
        private HttpClientInterface $solrClient
    ) {}

    /**
     * Récupère des documents depuis Solr.
     *
     * @param array $data Paramètres de requête Solr.
     * @param string $url URL relative de l'API Solr.
     * @return array|null
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getDocuments(array $data = ['q' => '*:*'], string $url = 'unt1/select'): ?array
    {
        $resp = $this->handleApi($url, ['query' => $data], 'GET');
        if ($resp && $resp->getStatusCode() === Response::HTTP_OK) {
            return $resp->toArray()['response'] ?? null;
        }
        return null;
    }

    /**
     * Ajoute des documents à Solr.
     *
     * @param string $data Données XML à ajouter.
     * @param string $url URL relative de l'API Solr.
     * @return string|null
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function addDocuments(string $data, string $url = 'unt1/update?commit=true'): ?string
    {
        $resp = $this->handleApi($url, ['body' => "<add>$data</add>"]);
        if ($resp && $resp->getStatusCode() === Response::HTTP_OK) {
            return $resp->getContent();
        }
        return null;
    }

    /**
     * Modifie des documents dans Solr.
     *
     * @param string $data Données XML à modifier.
     * @param string $url URL relative de l'API Solr.
     * @return string|null
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function editDocuments(string $data, string $url = 'unt1/update?commit=true'): ?string
    {
        $resp = $this->handleApi($url, ['body' => "<update>$data</update>"]);
        if ($resp && $resp->getStatusCode() === Response::HTTP_OK) {
            return $resp->getContent();
        }
        return null;
    }

    /**
     * Supprime des documents de Solr.
     *
     * @param string $data Données XML pour la suppression.
     * @param string $url URL relative de l'API Solr.
     * @return string|null
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function delDocuments(string $data = '<query>*:*</query>', string $url = 'unt1/update?commit=true'): ?string
    {
        $resp = $this->handleApi($url, ['body' => "<delete>$data</delete>"]);
        if ($resp && $resp->getStatusCode() === Response::HTTP_OK) {
            return $resp->getContent();
        }
        return null;
    }

    /**
     * Gère l'appel à l'API Solr.
     *
     * @param string $url URL relative de l'API Solr.
     * @param array $options Options de la requête HTTP.
     * @param string $method Méthode HTTP.
     * @return ResponseInterface|null
     */
    private function handleApi(string $url, array $options = [], string $method = 'POST'): ?ResponseInterface
    {
        try {
            return $this->solrClient->request($method, "solr/$url", $options);
        } catch (ExceptionInterface $e) {
            dump($e);
            return null;
        }
    }
}