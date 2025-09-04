<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

function detectarRutaBase()
{
    $rutaActual = $_SERVER['SCRIPT_NAME'];
    $rutaCompleta = $_SERVER['REQUEST_URI'];

    if (strpos($rutaActual, '/config/notificacion/') !== false) {
        return __DIR__ . '/../../';
    }

    $dirActual = __DIR__;
    $contador = 0;

    while ($contador < 5) {
        if (file_exists($dirActual . '/config/database/conexionBD.php')) {
            return $dirActual . '/';
        }
        $dirActual = dirname($dirActual);
        $contador++;
    }

    return $_SERVER['DOCUMENT_ROOT'] . '/';
}

$rutaBase = detectarRutaBase();

$conexionPath = $rutaBase . 'config/database/conexionBD.php';
$verificarSesionPath = $rutaBase . 'config/auth/verificar_sesion.php';


if (file_exists($conexionPath)) {
    include $conexionPath;
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'No se pudo encontrar conexionBD.php en: ' . $conexionPath,
        'debug' => [
            'ruta_base' => $rutaBase,
            'ruta_buscada' => $conexionPath,
            'archivos_en_config' => file_exists($rutaBase . 'config/') ? scandir($rutaBase . 'config/') : 'No existe carpeta config'
        ]
    ]);
    exit;
}

if (file_exists($verificarSesionPath)) {
    include $verificarSesionPath;
} else {
    $rutasAlternativas = [
        $rutaBase . 'auth/verificar_sesion.php',
        $rutaBase . 'verificar_sesion.php',
        $rutaBase . 'includes/verificar_sesion.php',
        $rutaBase . 'config/auth/verificar_sesion.php'
    ];

    $archivoEncontrado = false;
    foreach ($rutasAlternativas as $rutaAlternativa) {
        if (file_exists($rutaAlternativa)) {
            include $rutaAlternativa;
            $archivoEncontrado = true;
            break;
        }
    }

    if (!$archivoEncontrado) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'No se pudo encontrar verificar_sesion.php',
            'debug' => [
                'ruta_base' => $rutaBase,
                'rutas_intentadas' => $rutasAlternativas,
                'archivos_en_raiz' => is_dir($rutaBase) ? array_slice(scandir($rutaBase), 2, 10) : 'Directorio no existe'
            ]
        ]);
        exit;
    }
}

if (isset($_GET['verificar_permisos']) && $_GET['verificar_permisos'] == '1') {
    try {
        if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['1', '3'])) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Sin permisos para acceder a notificaciones'
            ]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'mensaje' => 'Usuario autorizado para notificaciones',
            'ruta_base_detectada' => $rutaBase
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error al verificar permisos'
        ]);
        exit;
    }
}

try {
    if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['1', '3'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Acceso denegado. Solo usuarios contables pueden acceder.'
        ]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Error de autenticación'
    ]);
    exit;
}

try {
    $ultimaVerificacion = isset($_GET['ultima_verificacion']) ? $_GET['ultima_verificacion'] : '2020-01-01 00:00:00';
    error_log("Sistema Universal - Verificando autorizaciones desde: " . $ultimaVerificacion);
    $sqlTotal = "SELECT COUNT(*) as total 
                 FROM public.sist_ventas_presupuesto v 
                 WHERE v.estado = 'En revision'";

    $stmtTotal = $conexion->prepare($sqlTotal);
    $stmtTotal->execute();
    $totalPendientes = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
    $ultimoIdVerificado = isset($_GET['ultimo_id']) ? (int)$_GET['ultimo_id'] : 0;

    $sqlNuevas = "SELECT COUNT(*) as nuevas,
                         MAX(v.id) as ultimo_id,
                         MAX(v.fecha_venta) as ultima_fecha
                  FROM public.sist_ventas_presupuesto v 
                  WHERE v.estado = 'En revision' 
                  AND v.id > :ultimo_id";

    $stmtNuevas = $conexion->prepare($sqlNuevas);
    $stmtNuevas->bindParam(':ultimo_id', $ultimoIdVerificado, PDO::PARAM_INT);
    $stmtNuevas->execute();
    $resultado = $stmtNuevas->fetch(PDO::FETCH_ASSOC);

    if ($resultado['nuevas'] == 0) {
        $sqlNuevasFecha = "SELECT COUNT(*) as nuevas_fecha,
                                  MAX(v.fecha_venta) as ultima_fecha_nueva
                           FROM public.sist_ventas_presupuesto v 
                           WHERE v.estado = 'En revision' 
                           AND v.fecha_venta > :ultima_verificacion";

        $stmtNuevasFecha = $conexion->prepare($sqlNuevasFecha);
        $stmtNuevasFecha->bindParam(':ultima_verificacion', $ultimaVerificacion);
        $stmtNuevasFecha->execute();
        $resultadoFecha = $stmtNuevasFecha->fetch(PDO::FETCH_ASSOC);

        if ($resultadoFecha['nuevas_fecha'] > 0) {
            $resultado['nuevas'] = $resultadoFecha['nuevas_fecha'];
            $resultado['ultima_fecha'] = $resultadoFecha['ultima_fecha_nueva'];
        }
    }

    $detallesNuevas = [];
    if ($resultado['nuevas'] > 0) {
        $sqlDetalles = "SELECT v.id, v.cliente, v.monto_total, v.moneda, u.nombre as vendedor, v.fecha_venta
                        FROM public.sist_ventas_presupuesto v 
                        LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                        WHERE v.estado = 'En revision' 
                        AND (v.id > :ultimo_id OR v.fecha_venta > :ultima_verificacion)
                        ORDER BY v.fecha_venta DESC, v.id DESC
                        LIMIT 5";

        $stmtDetalles = $conexion->prepare($sqlDetalles);
        $stmtDetalles->bindParam(':ultimo_id', $ultimoIdVerificado, PDO::PARAM_INT);
        $stmtDetalles->bindParam(':ultima_verificacion', $ultimaVerificacion);
        $stmtDetalles->execute();
        $detallesNuevas = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);
    }

    $sqlMaxId = "SELECT MAX(id) as max_id FROM public.sist_ventas_presupuesto WHERE estado = 'En revision'";
    $stmtMaxId = $conexion->prepare($sqlMaxId);
    $stmtMaxId->execute();
    $maxIdActual = $stmtMaxId->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;

    $response = [
        'success' => true,
        'total_pendientes' => (int)$totalPendientes,
        'nuevas_autorizaciones' => (int)$resultado['nuevas'],
        'ultima_fecha' => $resultado['ultima_fecha'],
        'timestamp' => date('Y-m-d H:i:s'),
        'detalles_nuevas' => $detallesNuevas,
        'ultimo_id_actual' => (int)$maxIdActual,
        'usuario_rol' => $_SESSION['rol'],
        'sistema_version' => 'universal',
        'debug' => [
            'ultima_verificacion_recibida' => $ultimaVerificacion,
            'ultimo_id_verificado' => $ultimoIdVerificado,
            'nuevas_encontradas' => (int)$resultado['nuevas'],
            'ruta_base_detectada' => $rutaBase,
            'script_actual' => $_SERVER['SCRIPT_NAME'],
            'request_uri' => $_SERVER['REQUEST_URI'],
            'conexion_path' => $conexionPath,
            'sesion_path' => $verificarSesionPath
        ]
    ];

    echo json_encode($response);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al verificar autorizaciones: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error general: ' . $e->getMessage()
    ]);
}
