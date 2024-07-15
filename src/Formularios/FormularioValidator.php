<?php
namespace ArquetipoPHP\Formularios;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class FormularioValidator
{
    private $logForm;
    public function __construct()
    {
        $logsDir = __DIR__ . '/../logs';
        if (!file_exists($logsDir) && !is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
        
        $this->
            logForm = new Logger('form');
        $this->logForm->pushHandler(new StreamHandler(__DIR__ . '/../logs/form.log', Logger::INFO));
    }

    public function validarNombre($nombre)
    {
        return strlen($nombre) >= 2;
    }

    public function validarEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public function validarContraseña($contraseña, &$errores)
    {
        if (strlen($contraseña) < 6) {
            $errores[] = "La contraseña debe tener al menos 6 caracteres.";
        }
        if
        (!preg_match('/[0-9]/', $contraseña)) {
            $errores[] = "La contraseña debe contener al menos un número.";
        }
        if
        (!preg_match('/[A-Z]/', $contraseña)) {
            $errores[] = "La contraseña debe contener al menos una mayúscula.";
        }
        if
        (!preg_match('/[a-z]/', $contraseña)) {
            $errores[] = "La contraseña debe contener al menos una minúscula.";
        }
        if
        (!preg_match('/[^a-zA-Z0-9]/', $contraseña)) {
            $errores[] = "La contraseña debe contener al menos un carácter especial.";
        }
        return empty($errores);
    }
    public
        function guardarUsuarioEnJSON(
        $nombre,
        $email,
        $contraseña
    ) {
        $usuario = [
            'nombre' => $nombre,
            'email' => $email,
            'pass' => $contraseña
        ];

        $usuarios = [];

        $archivo = __DIR__ . '/../../users/users.json';
        if (file_exists($archivo)) {
            $contenido = file_get_contents($archivo);
            $usuarios = json_decode($contenido, true);
        }
        $usuarios[] = $usuario;
        $usuariosJSON = json_encode($usuarios, JSON_PRETTY_PRINT);

        file_put_contents($archivo, $usuariosJSON);
    }

    public function procesarFormulario($nombre, $email, $contraseña, $confirmar_contraseña, &$errores)
    {
        if (!$this->validarNombre($nombre)) {
            $errores[] = "El nombre debe tener al menos 2 caracteres.";
        }
        if (!$this->validarEmail($email)) {
            $errores[] = "El correo electrónico no es válido.";
        }
        if ($contraseña !== $confirmar_contraseña) {
            $errores[] = "Las contraseñas no coinciden.";
        }
        $this->validarContraseña($contraseña, $errores);

        if (empty($errores)) {
            $this->logForm->info('Formulario enviado', ['nombre' => $nombre, 'email' => $email]);
            $this->guardarUsuarioEnJSON($nombre, $email, $contraseña);
            return true;
        } else {
            $this->logForm->error('Error al procesar el formulario', ['errores' => $errores]);
            return false;
        }
    }
}