<?php

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

function hacerSolicitud()
{
    $client = new Client();
    $response = $client->request('GET', 'https://jsonplaceholder.typicode.com/posts/3');
    $data = json_decode($response->getBody(), true);
    return $data;
}
$solicitudData = hacerSolicitud();

return  $solicitudData;