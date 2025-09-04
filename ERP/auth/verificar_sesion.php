<?php 
require_once __DIR__ . "/../config/conexionBD.php";  
require_once __DIR__ . "/../config/session_config.php";

iniciarSesionAislada();

function estaLogueado() {     
    return verificarSesionAislada();
}  

function tieneRol($rol_requerido) {     
    if (!estaLogueado()) {         
        return false;     
    }      
    
    if (is_array($rol_requerido)) {         
        return in_array($_SESSION['rol'], $rol_requerido);     
    }      
    
    return $_SESSION['rol'] == $rol_requerido; 
}  

function requerirLogin() {     
    global $url_base;     
    if (!estaLogueado()) {         
        header("Location: " . $url_base . "login.php");         
        exit();     
    } 
}  

function requerirRol($rol_requerido) {     
    global $url_base;     
    requerirLogin();      
    
    if (!tieneRol($rol_requerido)) {         
        header("Location: " . $url_base . "acceso_denegado.php");         
        exit();     
    } 
}  

function cerrarSesion() {     
    cerrarSesionAislada();
} 

function obtenerUsuarioActual() {     
    if (estaLogueado()) {         
        return array(             
            'id' => $_SESSION['id'],             
            'nombre' => $_SESSION['nombre'],             
            'usuario' => $_SESSION['usuario'],             
            'rol' => $_SESSION['rol'],
            'login_time' => $_SESSION['login_time'] ?? 'N/A',
            'app_name' => $_SESSION['app_name'] ?? 'AMERICA_TNT'         
        );     
    }     
    return null; 
}  

function esAdministrador() {     
    return tieneRol('administrador') || tieneRol('admin'); 
}

function validarTimeoutSesion($timeout_minutos = 240) { 
    if (estaLogueado()) {
        $ahora = time();
        $ultima_actividad = $_SESSION['last_activity'] ?? $ahora;
        
        if (($ahora - $ultima_actividad) > ($timeout_minutos * 60)) {
            // Sesión expirada
            cerrarSesion();
        } else {
            // Actualizar última actividad
            $_SESSION['last_activity'] = $ahora;
        }
    }
}

function autenticarUsuario($usuario, $contrasenia, $conexion) {
    try {
        $sql = "SELECT id, nombre, usuario, contrasenia, rol 
                FROM public.sist_prod_usuario 
                WHERE usuario = :usuario";
        
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
        $stmt->execute();
        
        $usuarioDB = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuarioDB) {
            if (password_verify($contrasenia, $usuarioDB['contrasenia']) || 
                $contrasenia === $usuarioDB['contrasenia']) { 
                
                $_SESSION['loggedin'] = true;
                $_SESSION['id'] = $usuarioDB['id'];
                $_SESSION['nombre'] = $usuarioDB['nombre'];
                $_SESSION['usuario'] = $usuarioDB['usuario'];
                $_SESSION['rol'] = $usuarioDB['rol'];
                
                marcarSesionComoAmericaTNT();
                
                return [
                    'success' => true,
                    'usuario' => $usuarioDB,
                    'error' => null
                ];
            } else {
                return [
                    'success' => false,
                    'usuario' => null,
                    'error' => 'Contraseña incorrecta'
                ];
            }
        } else {
            return [
                'success' => false,
                'usuario' => null,
                'error' => 'Usuario no encontrado'
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false,
            'usuario' => null,
            'error' => 'Error de base de datos: ' . $e->getMessage()
        ];
    }
}

function obtenerTodosLosUsuarios($conexion) {
    try {
        $sql = "SELECT id, nombre, usuario, rol 
                FROM public.sist_prod_usuario 
                ORDER BY nombre";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute();
        
        return [
            'success' => true,
            'usuarios' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'error' => null
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'usuarios' => [],
            'error' => 'Error al obtener usuarios: ' . $e->getMessage()
        ];
    }
}

function crearUsuario($nombre, $usuario, $contrasenia, $rol, $conexion) {
    try {
        $hash_contrasenia = password_hash($contrasenia, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO public.sist_prod_usuario (nombre, usuario, contrasenia, rol) 
                VALUES (:nombre, :usuario, :contrasenia, :rol)";
        
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
        $stmt->bindParam(':contrasenia', $hash_contrasenia, PDO::PARAM_STR);
        $stmt->bindParam(':rol', $rol, PDO::PARAM_STR);
        
        $resultado = $stmt->execute();
        
        if ($resultado) {
            return [
                'success' => true,
                'id_usuario' => $conexion->lastInsertId(),
                'error' => null
            ];
        } else {
            return [
                'success' => false,
                'id_usuario' => null,
                'error' => 'No se pudo crear el usuario'
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false,
            'id_usuario' => null,
            'error' => 'Error al crear usuario: ' . $e->getMessage()
        ];
    }
}

?>