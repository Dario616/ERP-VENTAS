<?php

$modo_desarrollo = true;

if ($modo_desarrollo) {
    $url_base = "http://localhost:8001/";
    $servidor = "localhost";
    $puerto = "5432";
    $basededatos = "ERP-VENTAS";
    $usuario = "postgres";
    $contrasenia = "6770";
} else {
    $url_base = "http://192.168.1.127/ERP/";
    $servidor = "192.168.1.127";
    $puerto = "5432";
    $basededatos = "ERP-VENTAS";
    $usuario = "postgres";
    $contrasenia = "159angel";
}

try {
    $dsn = "pgsql:host=$servidor;port=$puerto;dbname=$basededatos";
    $opciones = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $options = $opciones;

    $conexion = new PDO($dsn, $usuario, $contrasenia, $opciones);

    if ($modo_desarrollo) {
    }
} catch (PDOException $e) {
    if ($modo_desarrollo) {
        echo "<h3>Error de conexión a la base de datos</h3>";
        echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
        echo "<p><strong>Código:</strong> " . $e->getCode() . "</p>";
        echo "<p><strong>Archivo:</strong> " . $e->getFile() . "</p>";
        echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    } else {
        echo "Lo sentimos, no se pudo establecer conexión con la base de datos. Por favor, inténtelo más tarde.";

        error_log("Error de conexión PDO: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    }

    exit();
}
