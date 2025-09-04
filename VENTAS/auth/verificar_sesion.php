<?php
require_once __DIR__ . "/../config/database/conexionBD.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function estaLogueado()
{
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

function tieneRol($rol_requerido)
{
    if (!estaLogueado()) {
        return false;
    }

    if (is_array($rol_requerido)) {
        return in_array($_SESSION['rol'], $rol_requerido);
    }
    return $_SESSION['rol'] == $rol_requerido;
}

function requerirLogin()
{
    global $url_base;
    if (!estaLogueado()) {
        header("Location: " . $url_base . "login.php");
        exit();
    }
}

function requerirRol($rol_requerido)
{
    global $url_base;
    requerirLogin();

    if (!tieneRol($rol_requerido)) {
        header("Location: " . $url_base . "auth/acceso_denegado.php");
        exit();
    }
}

function cerrarSesion()
{
    global $url_base;
    $_SESSION = array();
    session_destroy();
    header("Location: " . $url_base . "login.php");
    exit();
}
