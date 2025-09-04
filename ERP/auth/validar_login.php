<?php
require_once "../config/session_config.php";
require_once "../config/conexionBD.php";

iniciarSesionAislada();

if (isset($_POST['usuario']) && isset($_POST['password'])) {

    $usuario = trim($_POST['usuario']);
    $password = trim($_POST['password']);

    if (empty($usuario) || empty($password)) {
        header("Location: " . $url_base . "login.php?error=empty");
        exit();
    }

    try {
        $query = "SELECT id, nombre, usuario, rol, contrasenia FROM public.sist_prod_usuario WHERE usuario = :usuario";
        $stmt = $conexion->prepare($query);
        $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);

        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $usuario_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($password == $usuario_data['contrasenia']) {

                $_SESSION['id'] = $usuario_data['id'];
                $_SESSION['nombre'] = $usuario_data['nombre'];
                $_SESSION['usuario'] = $usuario_data['usuario'];
                $_SESSION['rol'] = $usuario_data['rol'];
                $_SESSION['loggedin'] = true;

                marcarSesionComoAmericaTNT();

                $_SESSION['login_time'] = date('Y-m-d H:i:s');
                $_SESSION['last_activity'] = time();

                header("Location: " . $url_base . "index.php");
                exit();
            } else {
                header("Location: " . $url_base . "login.php?error=invalid");
                exit();
            }
        } else {
            header("Location: " . $url_base . "login.php?error=invalid");
            exit();
        }
    } catch (PDOException $e) {
        header("Location: " . $url_base . "login.php?error=database");
        exit();
    }
} else {
    header("Location: " . $url_base . "login.php");
    exit();
}
