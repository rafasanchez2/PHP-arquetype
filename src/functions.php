<?php

require_once __DIR__ . '/vendor/autoload.php';

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
    $jwtVerifier = (new \Okta\JwtVerifier\JwtVerifierBuilder())
        ->setAdaptor(new \Okta\JwtVerifier\Adaptors\FirebasePhpJwt)
        ->setIssuer($_ENV['ISSUER'])
        ->setAudience('api://default')
        ->setClientId($_ENV['CLIENT_ID'])
        ->build();

    $jwt = $jwtVerifier->verify($_COOKIE['access_token']);
    return $jwt->getClaims();
}

function getRecords($pdo, $table)
{
    global $log;
    try {
        $stmt = $pdo->query("SELECT * FROM $table");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $log->info('Acceso a registros de la tabla realizado correctamente', ['table' => $table]);
        return $records;
    } catch (PDOException $e) {
        $log->error('Error al acceder a los registros de la tabla: ' . $e->getMessage(), ['table' => $table]);
        throw $e;
    }
}

function getPostgresRecords($pdo_postgres, $table)
{
    try {
        $stmt = $pdo_postgres->query("SELECT * FROM $table");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        global $log;
        $log->info('Acceso a registros de la tabla realizado correctamente', ['table' => $table]);
        return $records;
    } catch (PDOException $e) {
        global $log;
        $log->error("Error al obtener los registros de la tabla $table: " . $e->getMessage());
        die("Error al obtener los registros de la tabla $table: " . $e->getMessage());
    }
}

function verifyJwt($jwt)
{
    try {
        $jwtVerifier = (new \Okta\JwtVerifier\JwtVerifierBuilder())
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