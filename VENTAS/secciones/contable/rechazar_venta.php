<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '3']);

// ===== DEBUG: LOG DE DATOS RECIBIDOS =====
error_log("=== DEBUG RECHAZAR VENTA ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("POST DATA: " . print_r($_POST, true));
error_log("SESSION: " . print_r($_SESSION, true));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("ERROR: Método no es POST");
    header("Location: devoluciones_pcp.php?error=Método no permitido");
    exit();
}

if (file_exists("controllers/ContableController.php")) {
    include "controllers/ContableController.php";
} else {
    error_log("ERROR: No se pudo cargar el controlador contable");
    die("Error: No se pudo cargar el controlador contable.");
}

$controller = new ContableController($conexion, $url_base);

if (!$controller->verificarPermisos()) {
    error_log("ERROR: Usuario sin permisos - Rol: " . ($_SESSION['rol'] ?? 'sin rol'));
    header("Location: " . $url_base . "secciones/contable/devoluciones_pcp.php?error=No tienes permisos para rechazar ventas");
    exit();
}

$idVenta = isset($_POST['id_venta']) ? (int)$_POST['id_venta'] : 0;
$descripcionRechazo = isset($_POST['descripcion_rechazo']) ? trim($_POST['descripcion_rechazo']) : '';

// ===== DEBUG: LOG DE DATOS PROCESADOS =====
error_log("ID Venta recibido: " . var_export($idVenta, true));
error_log("Descripción rechazo recibida: '" . $descripcionRechazo . "'");
error_log("Longitud descripción: " . strlen($descripcionRechazo));
error_log("¿ID Venta > 0?: " . ($idVenta > 0 ? 'SÍ' : 'NO'));
error_log("¿Descripción vacía?: " . (empty($descripcionRechazo) ? 'SÍ' : 'NO'));

if ($idVenta <= 0 || empty($descripcionRechazo)) {
    error_log("ERROR: Datos incompletos - ID: $idVenta, Desc vacía: " . (empty($descripcionRechazo) ? 'SÍ' : 'NO'));
    header("Location: devoluciones_pcp.php?error=Datos incompletos");
    exit();
}

if (!$controller->validarIdVenta($idVenta)) {
    error_log("ERROR: ID de venta inválido según validador");
    header("Location: devoluciones_pcp.php?error=ID de venta inválido");
    exit();
}

try {
    error_log("Iniciando procesamiento de rechazo...");
    
    $datos = [
        'descripcion_rechazo' => $descripcionRechazo
    ];

    error_log("Datos para procesamiento: " . print_r($datos, true));
    
    $resultado = $controller->procesarRechazoVentaDevuelta($idVenta, $datos);

    error_log("Resultado procesamiento: " . print_r($resultado, true));

    if ($resultado['success']) {
        error_log("ÉXITO: Venta rechazada correctamente");
        header("Location: devoluciones_pcp.php?mensaje=" . urlencode($resultado['mensaje']));
    } else {
        error_log("ERROR EN PROCESAMIENTO: " . ($resultado['error'] ?? 'Error desconocido'));
        header("Location: devoluciones_pcp.php?error=" . urlencode($resultado['error']));
    }
} catch (Exception $e) {
    error_log("EXCEPCIÓN: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    header("Location: devoluciones_pcp.php?error=" . urlencode("Error interno del servidor"));
}

error_log("=== FIN DEBUG RECHAZAR VENTA ===");
exit();
?>