<?php

function iniciarSesionAislada()
{
    if (session_status() == PHP_SESSION_NONE) {
        session_name('AMERICA_TNT_SESSION');

        // 🔒 Seguridad y duración
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', 0); // true si usás HTTPS

        // ⏳ Mantener la sesión en el servidor durante 7 días
        ini_set('session.gc_maxlifetime', 604800);

        // 🍪 La cookie vive mientras el navegador esté abierto
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();

        if (!isset($_SESSION['session_regenerated'])) {
            session_regenerate_id(true);
            $_SESSION['session_regenerated'] = time();
        }
    }
}


function cerrarSesionAislada()
{
    global $url_base;

    if (session_status() == PHP_SESSION_NONE) {
        session_name('AMERICA_TNT_SESSION');
        session_start();
    }

    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: " . $url_base . "../login.php?logout=success");
    exit();
}

function verificarSesionAislada()
{
    iniciarSesionAislada();

    return isset($_SESSION['loggedin']) &&
        $_SESSION['loggedin'] === true &&
        isset($_SESSION['america_tnt_app']) &&
        $_SESSION['america_tnt_app'] === true;
}

function marcarSesionComoAmericaTNT()
{
    $_SESSION['america_tnt_app'] = true;
    $_SESSION['app_name'] = 'AMERICA_TNT_PRODUCCION';
    $_SESSION['session_start_time'] = time();
}
