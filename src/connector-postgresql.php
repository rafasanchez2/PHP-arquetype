<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$log = new Logger('database');
$log->pushHandler(new StreamHandler(__DIR__.'/logs/db.log', Logger::INFO));

function connectToDatabasePostgres()
{
    $host = $_ENV['POSTGRE_HOST'];
    $port = $_ENV['POSTGRE_PORT'];
    $dbname = $_ENV['POSTGRE_NAME'];
    $username = $_ENV['POSTGRE_USER'];
    $password = $_ENV['POSTGRE_PASSWORD'];

    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$username;password=$password";
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        global $log;
        $log->info('Conexión exitosa a PostgreSQL', ['host' => $host, 'dbname' => $dbname]);
        return $pdo;
    } catch (PDOException $e) {
        global $log;
        $log->error('Error al conectar con PostgreSQL: ' . $e->getMessage());
        die("Error de conexión: " . $e->getMessage());
    }
}

function showTablesPostgres($pdo)
{
    try {
        $query = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'");
        $tables = $query->fetchAll(PDO::FETCH_COLUMN);
        global $log;
        $log->info('Consulta de tablas ejecutada correctamente', ['tables' => $tables]);
        return $tables;
    } catch (PDOException $e) {
        global $log;
        $log->error('Error al obtener la lista de tablas: ' . $e->getMessage());
        die("Error al obtener la lista de tablas: " . $e->getMessage());
    }
}