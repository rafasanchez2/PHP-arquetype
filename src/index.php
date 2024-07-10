<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/connector-mysql.php';
require_once __DIR__ . '/connector-postgresql.php';

$config = parse_ini_file('config.properties');

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$log = new Logger('database');
$log->pushHandler(new StreamHandler(__DIR__ . '/logs/db.log', Logger::INFO));

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/views');
$twig = new \Twig\Environment($loader);

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$state = 'applicationState';
$host = $config['DB_HOST'];
$dbname = $config['DB_NAME'];
$username = $config['DB_USER'];
$password = $config['DB_PASS'];

$postgre_host = $_ENV['POSTGRE_HOST'];
$postgre_port = $_ENV['POSTGRE_PORT'];
$postgre_dbname = $_ENV['POSTGRE_NAME'];
$postgre_username = $_ENV['POSTGRE_USER'];
$postgre_password = $_ENV['POSTGRE_PASSWORD'];

$pdo = connectToDatabase($host, $dbname, $username, $password);
$pdo_postgres = connectToDatabasePostgres();

$dispatcher = FastRoute\simpleDispatcher(function (FastRoute\RouteCollector $r) use ($twig, $state) {
    $r->get('/', function () use ($twig) {
        echo $twig->render('index.twig', [
            'authenticated' => isAuthenticated(),
            'profile' => getProfile()
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
        if (array_key_exists('state', $_REQUEST) && $_REQUEST['state'] !== $state) {
            throw new \Exception('State does not match.');
        }
        if (array_key_exists('code', $_REQUEST)) {
            $exchange = exchangeCode($_REQUEST['code']);
            if (!isset($exchange->access_token)) {
                die('Could not exchange code for an access token');
            }
            if (verifyJwt($exchange->access_token) == false) {
                die('Verification of JWT failed');
            }
            setcookie("access_token", "$exchange->access_token", time() + $exchange->expires_in, "/", false);
            header('Location: / ');
        }
        die('An error during login has occurred');
    });

    $r->get('/profile', function () use ($twig) {
        if (!isAuthenticated()) {
            header('Location: /');
        }
        echo $twig->render('profile.twig', [
            'authenticated' => isAuthenticated(),
            'profile' => getProfile()
        ]);
    });

    $r->get('/show-tables', function () use ($twig) {
        $pdo = connectToDatabase();
        $tables = showTables($pdo);
        echo $twig->render('show_tables.twig', [
            'tables' => $tables,
            'authenticated' => isAuthenticated()
        ]);
    });

    $r->get('/show-records/{table}', function ($vars) use ($twig) {
        global $pdo;
        $table = $vars['table'];
        $records = getRecords($pdo, $table);
        echo $twig->render('records.twig', [
            'records' => $records,
            'authenticated' => isAuthenticated()
        ]);
    });

    $r->get('/show-tables-postgres', function () use ($twig) {
        $pdo_postgres = connectToDatabasePostgres();
        $tables_postgres = showTablesPostgres($pdo_postgres);
        echo $twig->render('show_tables_postgre.twig', [
            'tables' => $tables_postgres,
            'authenticated' => isAuthenticated()
        ]);
    });

    $r->get('/show-records-postgres/{table}', function ($vars) use ($twig) {
        global $pdo_postgres;
        $table = $vars['table'];
        $records = getPostgresRecords($pdo_postgres, $table);
        echo $twig->render('records.twig', [
            'records' => $records,
            'authenticated' => isAuthenticated()
        ]);
    });

    $r->get('/form', function () use ($twig) {
        session_start();
        $old_data = isset($_SESSION['old_data']) ? $_SESSION['old_data'] : [];
        $errores = isset($_SESSION['errores']) ? $_SESSION['errores'] : [];
        unset($_SESSION['old_data'], $_SESSION['errores']);

        echo $twig->render('form.twig', [
            'authenticated' => isAuthenticated(),
            'old_data' => $old_data,
            'errores' => $errores
        ]);
    });

    $r->get('/solicitudes', function () use ($twig) {
        require_once __DIR__ . '/solicitudes.php';

        $solicitudes = hacerSolicitud();

        echo $twig->render('solicitudes.twig', [
            'solicitudes' => $solicitudes,
            'authenticated' => isAuthenticated()
        ]);
    });

    $r->post('/submit-form', function () use ($twig) {
        session_start();
        require 'validate-form.php';

        $nombre = $_POST['nombre'];
        $email = $_POST['email'];
        $contraseña = $_POST['contraseña'];
        $confirmar_contraseña = $_POST['confirmar_contraseña'];

        $errores = [];
        if (!validarNombre($nombre)) {
            $errores[] = "El nombre debe tener al menos 2 caracteres.";
        }
        if (!validarEmail($email)) {
            $errores[] = "El correo electrónico no es válido.";
        }
        if ($contraseña !== $confirmar_contraseña) {
            $errores[] = "Las contraseñas no coinciden.";
        }
        validarContraseña($contraseña, $errores); 

        if (count($errores) > 0) {
            $_SESSION['errores'] = $errores;
            $_SESSION['old_data'] = ['nombre' => $nombre, 'email' => $email];
            echo $twig->render('form.twig', [
                'authenticated' => isAuthenticated(),
                'old_data' => $_SESSION['old_data'],
                'errores' => $_SESSION['errores']
            ]);
            exit();
        }
        echo "Formulario enviado. Nombre: $nombre, Correo electrónico: $email";
    });


    $r->post('/logout', function () {
        setcookie("access_token", NULL, -1, "/", false);
        header('Location: /');
    });
});

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