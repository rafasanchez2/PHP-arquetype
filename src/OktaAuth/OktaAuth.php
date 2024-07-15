<?php

namespace ArquetipoPHP\OktaAuth;

use Okta\JwtVerifier\JwtVerifierBuilder;

function isAuthenticated()
{
    if (isset($_COOKIE['access_token'])) {
        return true;
    }
    return false;
}

function getProfile()
{
    if (!isAuthenticated()) {
        return [];
    }
    $jwtVerifier = (new JwtVerifierBuilder())
        ->setAdaptor(new \Okta\JwtVerifier\Adaptors\FirebasePhpJwt)
        ->setIssuer($_ENV['ISSUER'])
        ->setAudience('api://default')
        ->setClientId($_ENV['CLIENT_ID'])
        ->build();

    $jwt = $jwtVerifier->verify($_COOKIE['access_token']);
    return $jwt->getClaims();
}

function exchangeCode($code)
{
    $authHeaderSecret = base64_encode($_ENV['CLIENT_ID'] . ':' . $_ENV['CLIENT_SECRET']);
    $query = http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => 'http://localhost:8080/authorization-code/callback'
    ]);
    $headers = [
        'Authorization: Basic ' . $authHeaderSecret,
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
        'Connection: close',
        'Content-Length: 0'
    ];
    $url = $_ENV["ISSUER"] . '/v1/token?' . $query;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    $output = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_error($ch)) {
        $httpcode = 500;
    }
    curl_close($ch);
    return json_decode($output);
}

function verifyJwt($jwt)
{
    try {
        $jwtVerifier = (new JwtVerifierBuilder())
            ->setAdaptor(new \Okta\JwtVerifier\Adaptors\FirebasePhpJwt)
            ->setIssuer($_ENV['ISSUER'])
            ->setAudience('api://default')
            ->setClientId($_ENV['CLIENT_ID'])
            ->build();

        return $jwtVerifier->verify($jwt);
    } catch (\Exception $e) {
        dd($e);
        return false;
    }
}

function logout()
{
    setcookie("access_token", NULL, -1, "/", false);
    header('Location: /');
}

// Función para manejar el callback de autorización
function handleAuthorizationCallback($state)
{
    if (array_key_exists('state', $_REQUEST) && $_REQUEST['state'] !== $state) {
        throw new \Exception('State does not match.');
    }
    if (array_key_exists('code', $_REQUEST)) {
        $exchange = exchangeCode($_REQUEST['code']);
        if (!isset($exchange->access_token)) {
            die('Could not exchange code for an access token');
        }
        if (!verifyJwt($exchange->access_token)) {
            die('Verification of JWT failed');
        }
        setcookie("access_token", $exchange->access_token, time() + $exchange->expires_in, "/", false);
        header('Location: /');
    }
    die('An error during login has occurred');
}