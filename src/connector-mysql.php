<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$config = parse_ini_file('config.properties');

$log = new Logger('database');
$streamHandler = new StreamHandler(__DIR__ . '/logs/db.log', Logger::INFO);
$log->pushHandler($streamHandler);


function connectToDatabase()
{
    global $config;
    global $log;

    try {
        $dsn = "mysql:host={$config['DB_HOST']};dbname={$config['DB_NAME']};charset=utf8";
        $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $log->info('Conexión exitosa a MySQL', ['host' => $config['DB_HOST'], 'dbname' => $config['DB_NAME']]);
        return $pdo;
    } catch (PDOException $e) {
        $log->error('Error al conectar con MySQL: ' . $e->getMessage());
        die("Error de conexión: " . $e->getMessage());
    }
}

function showTables($pdo)
{
    global $log;
    
    try {
        $query = $pdo->query("SHOW TABLES");
        $tables = $query->fetchAll(PDO::FETCH_COLUMN);
        $log->info('Consulta de tablas ejecutada correctamente');
        return $tables;
    } catch (PDOException $e) {
        $log->error('Error al consultar tablas: ' . $e->getMessage());
        throw $e;
    }
}