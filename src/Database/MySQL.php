<?php

namespace ArquetipoPHP\Database;

use PDO;
use PDOException;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class MySQL
{
    private $pdo;
    private $log;

    public function __construct()
    {
        $this->log = new Logger('database');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../logs/db.log', Logger::INFO));
    }

    public function connect()
    {
        $config = parse_ini_file(__DIR__ . '/../config.properties');
        
        try {
            $dsn = "mysql:host={$config['DB_HOST']};dbname={$config['DB_NAME']};charset=utf8";
            $this->pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->log->info('Conexión exitosa a MySQL', ['host' => $config['DB_HOST'], 'dbname' => $config['DB_NAME']]);
        } catch (PDOException $e) {
            $this->log->error('Error al conectar con MySQL: ' . $e->getMessage());
            die("Error de conexión: " . $e->getMessage());
        }
    }

    public function getTables()
    {
        try {
            $query = $this->pdo->query("SHOW TABLES");
            $tables = $query->fetchAll(PDO::FETCH_COLUMN);
            $this->log->info('Consulta de tablas ejecutada correctamente');
            return $tables;
        } catch (PDOException $e) {
            $this->log->error('Error al consultar tablas: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getRecords($table)
    {
        try {
            $stmt = $this->pdo->query("SELECT * FROM $table");
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->log->info('Acceso a registros de la tabla realizado correctamente', ['table' => $table]);
            return $records;
        } catch (PDOException $e) {
            $this->log->error('Error al acceder a los registros de la tabla: ' . $e->getMessage(), ['table' => $table]);
            throw $e;
        }
    }
}