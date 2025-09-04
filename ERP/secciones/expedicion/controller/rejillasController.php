<?php
if (!isset($conexion)) {
    try {
        require_once __DIR__ . '../../../../config/conexionBD.php';
        require_once __DIR__ . '../../../../auth/verificar_sesion.php';

        if (!function_exists('requerirRol')) {
            if (!isset($_SESSION['id_rol']) || !in_array($_SESSION['id_rol'], ['1', '3'])) {
                http_response_code(403);
                echo json_encode([
                    'exito' => false,
                    'mensaje' => 'Sin permisos para acceder a esta funcionalidad',
                    'datos' => null
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            requerirRol(['1', '3']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'exito' => false,
            'mensaje' => 'Error de configuración del sistema',
            'datos' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

require_once __DIR__ . '../../services/rejillasService.php';

class RejillasController
{
    private $service;

    public function __construct($conexion)
    {
        try {
            $this->service = new RejillasService($conexion);
        } catch (Exception $e) {
            throw new Exception("Error inicializando servicio de rejillas: " . $e->getMessage());
        }
    }

    public function obtenerDatosVistaRejillas()
    {
        try {
            return $this->service->obtenerDatosVistaRejillas();
        } catch (Exception $e) {
            error_log("Error en controlador obteniendo datos de vista: " . $e->getMessage());
            return [
                'rejillas' => [],
                'estadisticas_generales' => [],
                'alertas' => [[
                    'tipo' => 'danger',
                    'icono' => 'exclamation-triangle',
                    'titulo' => 'Error del Sistema',
                    'mensaje' => 'No se pudieron cargar los datos. Contacte al administrador.'
                ]],
                'configuracion' => []
            ];
        }
    }

    public function procesarAccionAjax($accion, $datos)
    {
        if (empty($accion)) {
            return $this->errorResponse('Acción no especificada', 400);
        }

        if (!is_array($datos)) {
            return $this->errorResponse('Datos inválidos', 400);
        }

        try {
            $datosLimpios = $this->sanitizarDatos($datos);
            switch ($accion) {
                case 'obtener_detalles_rejilla':
                    return $this->procesarObtenerDetalles($datosLimpios);

                case 'obtener_estadisticas_rejillas':
                    return $this->procesarObtenerEstadisticas();

                case 'obtener_rejillas_disponibles':
                    return $this->procesarObtenerDisponibles();

                case 'marcar_item_completado':
                    return $this->procesarMarcarCompletado($datosLimpios);

                case 'reactivar_item_completado':
                    return $this->procesarReactivarItem($datosLimpios);

                case 'obtener_items_completados':
                    return $this->procesarObtenerCompletados($datosLimpios);

                case 'limpiar_item_asignacion':
                    return $this->procesarLimpiarAsignacion($datosLimpios);

                case 'actualizar_descripcion_rejilla':
                    return $this->procesarActualizarDescripcion($datosLimpios);

                default:
                    return $this->errorResponse("Acción no reconocida: $accion", 400);
            }
        } catch (Exception $e) {
            error_log("Error en controlador procesando acción '$accion': " . $e->getMessage());
            return $this->errorResponse('Error interno del servidor', 500);
        }
    }
    private function procesarObtenerDetalles($datos)
    {
        if (!isset($datos['id_rejilla'])) {
            return $this->errorResponse('ID de rejilla requerido', 400);
        }

        if (!is_numeric($datos['id_rejilla'])) {
            return $this->errorResponse('ID de rejilla debe ser numérico', 400);
        }
        try {
            $detalles = $this->service->obtenerDetallesRejilla($datos['id_rejilla']);
            return $this->successResponse($detalles, 'Detalles obtenidos correctamente');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    private function procesarObtenerEstadisticas()
    {
        try {
            $estadisticas = $this->service->obtenerEstadisticasCompletas();
            return $this->successResponse($estadisticas, 'Estadísticas obtenidas correctamente');
        } catch (Exception $e) {
            return $this->errorResponse('Error obteniendo estadísticas', 500);
        }
    }

    private function procesarObtenerDisponibles()
    {
        try {
            $rejillas = $this->service->obtenerRejillasDisponibles();
            return $this->successResponse($rejillas, 'Rejillas disponibles obtenidas correctamente');
        } catch (Exception $e) {
            return $this->errorResponse('Error obteniendo rejillas disponibles', 500);
        }
    }

    private function procesarMarcarCompletado($datos)
    {
        // Validación HTTP
        if (!isset($datos['id_asignacion']) || !is_numeric($datos['id_asignacion'])) {
            return $this->errorResponse('ID de asignación requerido y debe ser numérico', 400);
        }

        $observaciones = isset($datos['observaciones']) ? trim($datos['observaciones']) : null;
        if ($observaciones === '') {
            $observaciones = null;
        }
        return $this->service->marcarItemComoCompletado($datos['id_asignacion'], $observaciones);
    }

    private function procesarReactivarItem($datos)
    {
        if (!isset($datos['id_asignacion']) || !is_numeric($datos['id_asignacion'])) {
            return $this->errorResponse('ID de asignación requerido y debe ser numérico', 400);
        }

        $observaciones = isset($datos['observaciones']) ? trim($datos['observaciones']) : null;
        if ($observaciones === '') {
            $observaciones = null;
        }
        return $this->service->reactivarItemCompletado($datos['id_asignacion'], $observaciones);
    }

    private function procesarObtenerCompletados($datos)
    {
        if (!isset($datos['id_rejilla']) || !is_numeric($datos['id_rejilla'])) {
            return $this->errorResponse('ID de rejilla requerido y debe ser numérico', 400);
        }

        try {
            $itemsCompletados = $this->service->obtenerItemsCompletadosRejilla($datos['id_rejilla']);
            return $this->successResponse($itemsCompletados, 'Items completados obtenidos correctamente');
        } catch (Exception $e) {
            return $this->errorResponse('Error obteniendo items completados', 500);
        }
    }

    private function procesarLimpiarAsignacion($datos)
    {
        if (!isset($datos['id_asignacion']) || !is_numeric($datos['id_asignacion'])) {
            return $this->errorResponse('ID de asignación requerido y debe ser numérico', 400);
        }

        $observaciones = isset($datos['observaciones']) ? trim($datos['observaciones']) : null;
        if ($observaciones === '') {
            $observaciones = 'Asignación limpiada por usuario';
        }

        return $this->service->limpiarAsignacion($datos['id_asignacion'], $observaciones);
    }

    private function procesarActualizarDescripcion($datos)
    {
        if (!isset($datos['id_rejilla']) || !is_numeric($datos['id_rejilla'])) {
            return $this->errorResponse('ID de rejilla requerido y debe ser numérico', 400);
        }

        if (!isset($datos['descripcion'])) {
            return $this->errorResponse('Descripción requerida', 400);
        }

        if (strlen($datos['descripcion']) > 1000) {
            return $this->errorResponse('Descripción demasiado larga', 400);
        }

        return $this->service->actualizarDescripcionRejilla($datos['id_rejilla'], $datos['descripcion']);
    }
    private function successResponse($datos, $mensaje = 'Operación exitosa')
    {
        return [
            'exito' => true,
            'mensaje' => $mensaje,
            'datos' => $datos,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function errorResponse($mensaje, $codigoHttp = 400)
    {
        if ($codigoHttp !== 200) {
            http_response_code($codigoHttp);
        }

        return [
            'exito' => false,
            'mensaje' => $mensaje,
            'datos' => null,
            'codigo_error' => $codigoHttp,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function sanitizarDatos($datos)
    {
        if (!is_array($datos)) {
            return $datos;
        }

        $datosLimpios = [];
        foreach ($datos as $clave => $valor) {
            if (is_string($valor)) {
                $datosLimpios[$clave] = trim(strip_tags($valor));
            } else {
                $datosLimpios[$clave] = $valor;
            }
        }

        return $datosLimpios;
    }
}
try {
    $controller = new RejillasController($conexion);
} catch (Exception $e) {
    error_log("Error fatal inicializando RejillasController: " . $e->getMessage());
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'exito' => false,
            'mensaje' => 'Error del sistema. Contacte al administrador.',
            'datos' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    die("Error del sistema. Contacte al administrador.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $resultado = $controller->procesarAccionAjax($_POST['accion'], $_POST);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        $errorResponse = [
            'exito' => false,
            'mensaje' => 'Error del servidor: ' . $e->getMessage(),
            'datos' => null,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
    }
    exit;
}
