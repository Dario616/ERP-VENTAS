<?php
require_once __DIR__ . "/../config/database/conexionBD.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica si el usuario está logueado
 * @return bool
 */
function estaLogueado()
{
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

/**
 * Verifica si el usuario tiene el rol requerido
 * @param string|array $rol_requerido
 * @return bool
 */
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

/**
 * Requiere que el usuario esté logueado, sino redirige al login
 */
function requerirLogin()
{
    global $url_base;
    if (!estaLogueado()) {
        header("Location: " . $url_base . "login.php");
        exit();
    }
}

/**
 * Requiere que el usuario tenga un rol específico
 * @param string|array $rol_requerido
 */
function requerirRol($rol_requerido)
{
    global $url_base;
    requerirLogin();

    if (!tieneRol($rol_requerido)) {
        header("Location: " . $url_base . "auth/acceso_denegado.php");
        exit();
    }
}

/**
 * Cierra la sesión del usuario y redirige al login
 */
function cerrarSesion()
{
    global $url_base;
    $_SESSION = array();
    session_destroy();
    header("Location: " . $url_base . "login.php");
    exit();
}

/**
 * Obtiene el nombre del usuario actual
 * @return string|null
 */
function obtenerNombreUsuario()
{
    return estaLogueado() ? $_SESSION['nombre'] ?? $_SESSION['usuario'] ?? null : null;
}

/**
 * Obtiene el rol del usuario actual
 * @return string|null
 */
function obtenerRolUsuario()
{
    return estaLogueado() ? $_SESSION['rol'] ?? null : null;
}
