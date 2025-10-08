<?php
// includes/conexion.php

declare(strict_types=1);

function conectar_bd(): mysqli {
    $host = '127.0.0.1';
    $usuario = 'root';
    $contrasena = '';
    $bd = 'optilife';
    $puerto = 3306;

    $mysqli = @new mysqli($host, $usuario, $contrasena, $bd, $puerto);

    if ($mysqli->connect_errno) {
        http_response_code(500);
        // No exponer credenciales en producción
        throw new RuntimeException('Error de conexión a MySQL: ' . $mysqli->connect_error);
    }

    // Establecer charset
    if (!$mysqli->set_charset('utf8mb4')) {
        throw new RuntimeException('No se pudo establecer charset: ' . $mysqli->error);
    }

    return $mysqli;
}
