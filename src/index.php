<?php

require 'vendor/autoload.php';

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/OktaAuth/OktaAuth.php';


$config = parse_ini_file('config.properties');

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use ArquetipoPHP\Formularios\FormularioValidator;
use ArquetipoPHP\Http\HttpRequest;
use ArquetipoPHP\Database\PostgreSQL;
use ArquetipoPHP\Database\MySQL;



$log = new Logger('database');
$log->pushHandler(new StreamHandler(__DIR__ . '/logs/db.log', Logger::INFO));

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/views');
$twig = new \Twig\Environment($loader);

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$state = 'applicationState';






function hacerSolicitud()
{
    $httpRequest = new ArquetipoPHP\Http\HttpRequest();
    return $httpRequest->get('https://jsonplaceholder.typicode.com/posts/2');
}

$dispatcher = FastRoute\simpleDispatcher(function (FastRoute\RouteCollector $r) use ($twig, $state) {
    $r->get('/', function () use ($twig) {
        echo $twig->render('index.twig', [
            'authenticated' => \ArquetipoPHP\OktaAuth\isAuthenticated(),
            'profile' => \ArquetipoPHP\OktaAuth\getProfile()
        ]);
    });

    $r->get('/login', function () use ($state) {
        $query = http_build_query([
            'client_id' => $_ENV['CLIENT_ID'],
            'response_type' => 'code',
            'response_mode' => 'query',
            'scope' => 'openid profile',
            'redirect_uri' => 'http://localhost:8080/authorization-code/callback',
            'state' => $state
        ]);
        header('Location: ' . $_ENV["ISSUER"] . '/v1/authorize?' . $query);
    });

    $r->get('/authorization-code/callback', function () use ($state) {
        \ArquetipoPHP\OktaAuth\handleAuthorizationCallback($state);
    });

    $r->get('/profile', function () use ($twig) {
        if (!\ArquetipoPHP\OktaAuth\isAuthenticated()) {
            header('Location: /login');
            exit();
        }
        echo $twig->render('profile.twig', [
            'authenticated' => \ArquetipoPHP\OktaAuth\isAuthenticated(),
            'profile' => \ArquetipoPHP\OktaAuth\getProfile()
        ]);
    });

    $r->get('/show-tables', function () use ($twig) {
        $db = new ArquetipoPHP\Database\MySQL();
        $db->connect();
        $tables = $db->getTables();
        echo $twig->render('show_tables.twig', [
            'tables' => $tables,
            'authenticated' => \ArquetipoPHP\OktaAuth\isAuthenticated()
        ]);
    });

    $r->get('/show-records/{table}', function ($vars) use ($twig) {
        $db = new ArquetipoPHP\Database\MySQL();
        $db->connect();
        $table = $vars['table'];
        $records = $db->getRecords($table);
        echo $twig->render('records.twig', [
            'records' => $records,
            'authenticated' => \ArquetipoPHP\OktaAuth\isAuthenticated()
        ]);
    });

    $r->get('/show-tables-postgres', function () use ($twig) {
        $db = new ArquetipoPHP\Database\PostgreSQL();
        $db->connect();
        $tables = $db->getTables();
        echo $twig->render('show_tables_postgre.twig', [
            'tables' => $tables,
            'authenticated' => \ArquetipoPHP\OktaAuth\isAuthenticated()
        ]);
    });

    $r->get('/show-records-postgres/{table}', function ($vars) use ($twig) {
        $db = new ArquetipoPHP\Database\PostgreSQL();
        $db->connect();
        $table = $vars['table'];
        $records = $db->getRecords($table);
        echo $twig->render('records.twig', [
            'records' => $records,
            'authenticated' => \ArquetipoPHP\OktaAuth\isAuthenticated()
        ]);
    });

    $r->get('/form', function () use ($twig) {
        session_start();
        $old_data = isset($_SESSION['old_data']) ? $_SESSION['old_data'] : [];
        $errores = isset($_SESSION['errores']) ? $_SESSION['errores'] : [];
        unset($_SESSION['old_data'], $_SESSION['errores']);

        echo $twig->render('form.twig', [
            'authenticated' => \ArquetipoPHP\OktaAuth\isAuthenticated(),
            'old_data' => $old_data,
            'errores' => $errores
        ]);
    });

    $r->get('/solicitudes', function () use ($twig) {
        $solicitudes = hacerSolicitud();

        echo $twig->render('solicitudes.twig', [
            'solicitudes' => $solicitudes,
            'authenticated' => \ArquetipoPHP\OktaAuth\isAuthenticated()
        ]);
    });

    $r->post('/submit-form', function () use ($twig) {
        session_start();

        $validator = new ArquetipoPHP\Formularios\FormularioValidator();

        $nombre = $_POST['nombre'];
        $email = $_POST['email'];
        $contraseña = $_POST['contraseña'];
        $confirmar_contraseña = $_POST['confirmar_contraseña'];

        $errores = [];
        if ($validator->procesarFormulario($nombre, $email, $contraseña, $confirmar_contraseña, $errores)) {
            header('Location: /form');
            exit();
        } else {
            $_SESSION['errores'] = $errores;
            $_SESSION['old_data'] = ['nombre' => $nombre, 'email' => $email];
            echo $twig->render('form.twig', [
                'authenticated' => \ArquetipoPHP\OktaAuth\isAuthenticated(),
                'old_data' => $_SESSION['old_data'],
                'errores' => $_SESSION['errores']
            ]);
        }
    });


    $r->get('/logout', function () {
        \ArquetipoPHP\OktaAuth\logout();
    });
});


$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        die('Not Found');
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        die('Not Allowed');
        break;
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        print $handler($vars);
        break;
}