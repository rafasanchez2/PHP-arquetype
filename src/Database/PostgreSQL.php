<?php

namespace ArquetipoPHP\Database;

use PDO;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class PostgreSQL
{
    private $pdo;
    private $log;

    public function __construct()
    {
        $this->log = new Logger('database');
        $this->log->pushHandler(new StreamHandler(__DIR__.'/../logs/db.log', Logger::INFO));
    }

    public function connect()
    {
        $host = $_ENV['POSTGRE_HOST'];
        $port = $_ENV['POSTGRE_PORT'];
        $dbname = $_ENV['POSTGRE_NAME'];
        $username = $_ENV['POSTGRE_USER'];
        $password = $_ENV['POSTGRE_PASSWORD'];

        try {
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$username;password=$password";
            $this->pdo = new PDO($dsn);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->log->info('Conexión exitosa a PostgreSQL', ['host' => $host, 'dbname' => $dbname]);
        } catch (PDOException $e) {
            $this->log->error('Error al conectar con PostgreSQL: ' . $e->getMessage());
            die("Error de conexión: " . $e->getMessage());
        }
    }

    public function getTables()
    {
        try {
            $query = $this->pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'");
            $tables = $query->fetchAll(PDO::FETCH_COLUMN);
            $this->log->info('Consulta de tablas ejecutada correctamente', ['tables' => $tables]);
            return $tables;
        } catch (PDOException $e) {
            $this->log->error('Error al obtener la lista de tablas: ' . $e->getMessage());
            die("Error al obtener la lista de tablas: " . $e->getMessage());
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
            $this->log->error("Error al obtener los registros de la tabla $table: " . $e->getMessage());
            die("Error al obtener los registros de la tabla $table: " . $e->getMessage());
        }
    }
}