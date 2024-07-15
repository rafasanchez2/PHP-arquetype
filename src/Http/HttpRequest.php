<?php
namespace ArquetipoPHP\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class HttpRequest
{
    private $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function get($url)
    {
        try {
            $response = $this->client->request('GET', $url);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            error_log('Error en la solicitud HTTP: ' . $e->getMessage());
            return [];
        }
    }

}