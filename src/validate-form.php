<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logForm = new Logger('form');
$logForm->pushHandler(new StreamHandler(__DIR__ . '/logs/form.log', Logger::INFO));

function validarNombre($nombre) {
    return strlen($nombre) >= 2;
}

function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validarContraseña($contraseña, &$errores) {
    if (strlen($contraseña) < 6) {
        $errores[] = "La contraseña debe tener al menos 6 caracteres.";
    }
    if (!preg_match('/[0-9]/', $contraseña)) {
        $errores[] = "La contraseña debe contener al menos un número.";
    }
    if (!preg_match('/[A-Z]/', $contraseña)) {
        $errores[] = "La contraseña debe contener al menos una mayúscula.";
    }
    if (!preg_match('/[a-z]/', $contraseña)) {
        $errores[] = "La contraseña debe contener al menos una minúscula.";
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $contraseña)) {
        $errores[] = "La contraseña debe contener al menos un carácter especial.";
    }
    return empty($errores);
}

function guardarUsuarioEnJSON($nombre, $email, $contraseña) {
    $usuario = [
        'nombre' => $nombre,
        'email' => $email,
        'pass' => $contraseña
    ];

    $usuarios = [];

    $archivo = __DIR__ . '/users/users.json';
    if (file_exists($archivo)) {
        $contenido = file_get_contents($archivo);
        $usuarios = json_decode($contenido, true);
    }
    $usuarios[] = $usuario;
    $usuariosJSON = json_encode($usuarios, JSON_PRETTY_PRINT);

    file_put_contents($archivo, $usuariosJSON);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    if (!validarContraseña($contraseña, $errores)) {
    }

    if (empty($errores)) {
        $logForm->info('Formulario enviado', ['nombre' => $nombre, 'email' => $email]);
        guardarUsuarioEnJSON($nombre, $email, $contraseña);
        header('Location: /form');
        exit();
    } else {
        $logForm->error('Error al procesar el formulario', ['errores' => $errores]);
    }
}