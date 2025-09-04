<?php
session_start();
include "../config/database/conexionBD.php";

if (isset($_POST['usuario']) && isset($_POST['password'])) {

    $usuario = trim($_POST['usuario']);
    $password = trim($_POST['password']);

    if (empty($usuario) || empty($password)) {
        header("Location: " . $url_base . "login.php?error=empty&usuario=" . urlencode($usuario));
        exit();
    }

    try {
        $query = "SELECT id, nombre, usuario, rol, contrasenia FROM public.sist_ventas_usuario WHERE usuario = :usuario";
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

                header("Location: " . $url_base . "index.php");
                exit();
            } else {
                header("Location: " . $url_base . "login.php?error=invalid&usuario=" . urlencode($usuario));
                exit();
            }
        } else {
            header("Location: " . $url_base . "login.php?error=invalid&usuario=" . urlencode($usuario));
            exit();
        }
    } catch (PDOException $e) {
        header("Location: " . $url_base . "login.php?error=database&usuario=" . urlencode($usuario));
        exit();
    }
} else {
    header("Location: " . $url_base . "login.php");
    exit();
}
