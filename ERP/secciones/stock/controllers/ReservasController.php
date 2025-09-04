<?php
require_once 'repository/reservasRepository.php';
require_once 'services/reservasService.php';

// Establecer la zona horaria de Paraguay/Asunción
date_default_timezone_set('America/Asuncion');

/**
 * Controller para manejo de reservas de stock
 * Modificado para vista agrupada por productos
 */
class ReservasController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new ReservasRepository($conexion);
        $this->service = new ReservasService($this->repository);
        $this->urlBase = $urlBase;
    }

    /**
     * Maneja las peticiones API - VERSIÓN MEJORADA
     */
    public function handleApiRequest()
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        // Headers importantes para debugging
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');

        // Log de la petición para debugging
        error_log("API Request: " . $_GET['action'] . " | User: " . ($_SESSION['nombre'] ?? 'Unknown'));

        try {
            switch ($_GET['action']) {
                case 'test_conectividad':
                    $this->testConectividadApi();
                    break;

                case 'buscar_productos':
                    $this->buscarProductosApi();
                    break;

                case 'obtener_reservas_producto':
                    $this->obtenerReservasProductoApi();
                    break;

                case 'buscar_reservas_cancelacion':
                    $this->buscarReservasCancelacionApi();
                    break;

                case 'cancelar_reserva':
                    $this->cancelarReservaApi();
                    break;

                case 'obtener_estadisticas':
                    $this->obtenerEstadisticasApi();
                    break;

                default:
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Acción no válida: ' . $_GET['action']
                    ]);
            }
        } catch (Exception $e) {
            error_log("Error en API: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor',
                'debug' => isset($_GET['debug']) ? $e->getMessage() : null
            ]);
        }

        return true;
    }

    /**
     * API: Test de conectividad
     */
    private function testConectividadApi()
    {
        try {
            // Verificar conexión a la base de datos
            $stmt = $this->repository->conexion->query("SELECT 1");

            echo json_encode([
                'success' => true,
                'message' => 'Conectividad OK',
                'timestamp' => date('Y-m-d H:i:s'),
                'user' => $_SESSION['nombre'] ?? 'Unknown'
            ]);
        } catch (Exception $e) {
            http_response_code(503);
            echo json_encode([
                'success' => false,
                'error' => 'Error de conectividad con la base de datos'
            ]);
        }
    }

    /**
     * API: Buscar productos con reservas activas - MEJORADA
     */
    private function buscarProductosApi()
    {
        $filtroProducto = trim($_GET['producto'] ?? '');
        $pagina = (int)($_GET['page'] ?? 1);
        $registrosPorPagina = (int)($_GET['limit'] ?? 20);

        // Validaciones
        if ($pagina < 1) $pagina = 1;
        if ($registrosPorPagina < 1 || $registrosPorPagina > 50) $registrosPorPagina = 20;

        // Validar filtro de producto
        if (strlen($filtroProducto) === 1) {
            echo json_encode([
                'success' => false,
                'error' => 'El filtro de búsqueda debe tener al menos 2 caracteres'
            ]);
            return;
        }

        try {
            $resultado = $this->service->obtenerProductosConReservasPaginados(
                $filtroProducto,
                $pagina,
                $registrosPorPagina
            );

            echo json_encode([
                'success' => true,
                'datos' => $resultado['datos'],
                'paginacion' => $resultado['paginacion'],
                'estadisticas' => $resultado['estadisticas'],
                'filtros_aplicados' => [
                    'producto' => $filtroProducto,
                    'pagina' => $pagina,
                    'registros_por_pagina' => $registrosPorPagina
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error en API buscar productos: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al buscar productos con reservas'
            ]);
        }
    }

    /**
     * API: Obtener reservas específicas de un producto - MEJORADA
     */
    private function obtenerReservasProductoApi()
    {
        $idStock = (int)($_GET['id_stock'] ?? 0);

        if ($idStock <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'ID de stock requerido y debe ser un número positivo'
            ]);
            return;
        }

        try {
            // Verificar que el producto existe
            $producto = $this->repository->obtenerProductoPorId($idStock);
            if (!$producto) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Producto no encontrado'
                ]);
                return;
            }

            $reservas = $this->service->obtenerReservasPorProducto($idStock);

            echo json_encode([
                'success' => true,
                'reservas' => $reservas,
                'producto' => $producto,
                'total' => count($reservas),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error obteniendo reservas por producto: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener reservas del producto'
            ]);
        }
    }

    /**
     * API: Buscar reservas para cancelación - MEJORADA
     */
    private function buscarReservasCancelacionApi()
    {
        $idStock = (int)($_GET['id_stock'] ?? 0);
        $cliente = trim($_GET['cliente'] ?? '');
        $cantidadMinima = (int)($_GET['cantidad_minima'] ?? 0);

        if ($idStock <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'ID de stock requerido y debe ser un número positivo'
            ]);
            return;
        }

        try {
            $reservas = $this->service->buscarReservasParaCancelacion($idStock, $cliente, $cantidadMinima);

            echo json_encode([
                'success' => true,
                'reservas' => $reservas,
                'total' => count($reservas),
                'filtros' => [
                    'cliente' => $cliente,
                    'cantidad_minima' => $cantidadMinima
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error buscando reservas para cancelación: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al buscar reservas para cancelación'
            ]);
        }
    }

    /**
     * API: Cancelar reserva - MEJORADA
     */
    private function cancelarReservaApi()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Método no permitido. Use POST.'
            ]);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Datos JSON inválidos'
            ]);
            return;
        }

        $idReserva = $input['id_reserva'] ?? null;
        $motivo = trim($input['motivo'] ?? 'Cancelación desde interfaz');
        $usuario = $_SESSION['nombre'] ?? 'SISTEMA';

        if (empty($idReserva) || !is_numeric($idReserva)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'ID de reserva requerido y debe ser numérico'
            ]);
            return;
        }

        try {
            $resultado = $this->service->cancelarReserva($idReserva, $motivo, $usuario);

            if ($resultado['exito']) {
                echo json_encode([
                    'success' => true,
                    'mensaje' => $resultado['mensaje'],
                    'paquetes_liberados' => $resultado['paquetes_liberados'],
                    'bobinas_liberadas' => $resultado['bobinas_liberadas'],
                    'detalle_producto' => $resultado['detalle_producto'] ?? '',
                    'detalle_cliente' => $resultado['detalle_cliente'] ?? '',
                    'detalle_proforma' => $resultado['detalle_proforma'] ?? '',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => $resultado['mensaje']
                ]);
            }
        } catch (Exception $e) {
            error_log("Error cancelando reserva: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al cancelar la reserva'
            ]);
        }
    }

    /**
     * API: Obtener estadísticas generales
     */
    private function obtenerEstadisticasApi()
    {
        try {
            $estadisticas = $this->service->obtenerEstadisticasGenerales();

            echo json_encode([
                'success' => true,
                'estadisticas' => $estadisticas,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error en API estadísticas: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    /**
     * Procesar filtros del formulario
     */
    public function procesarFiltros()
    {
        $filtros = [
            'producto' => trim($_GET['producto'] ?? ''),
            'pagina' => max(1, (int)($_GET['page'] ?? 1)),
            'registros_por_pagina' => 20
        ];

        try {
            $resultado = $this->service->obtenerProductosConReservasPaginados(
                $filtros['producto'],
                $filtros['pagina'],
                $filtros['registros_por_pagina']
            );

            return [
                'error' => '',
                'datos' => $resultado,
                'filtros_aplicados' => $filtros
            ];
        } catch (Exception $e) {
            error_log("Error procesando filtros: " . $e->getMessage());

            return [
                'error' => 'Error al procesar la búsqueda',
                'datos' => [
                    'datos' => [],
                    'paginacion' => [
                        'pagina_actual' => 1,
                        'total_paginas' => 0,
                        'total_registros' => 0,
                        'hay_pagina_anterior' => false,
                        'hay_pagina_siguiente' => false,
                        'pagina_anterior' => 1,
                        'pagina_siguiente' => 1,
                        'registros_por_pagina' => 20
                    ],
                    'estadisticas' => []
                ],
                'filtros_aplicados' => $filtros
            ];
        }
    }

    /**
     * Obtener datos iniciales para la primera carga
     */
    public function obtenerDatosIniciales()
    {
        try {
            // Obtener los primeros 20 productos sin filtros
            $resultado = $this->service->obtenerProductosConReservasPaginados('', 1, 20);

            return [
                'error' => '',
                'datos' => $resultado,
                'filtros_aplicados' => [
                    'producto' => '',
                    'pagina' => 1,
                    'registros_por_pagina' => 20
                ]
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos iniciales: " . $e->getMessage());

            return [
                'error' => '',
                'datos' => [
                    'datos' => [],
                    'paginacion' => [
                        'pagina_actual' => 1,
                        'total_paginas' => 0,
                        'total_registros' => 0,
                        'hay_pagina_anterior' => false,
                        'hay_pagina_siguiente' => false,
                        'pagina_anterior' => 1,
                        'pagina_siguiente' => 1,
                        'registros_por_pagina' => 20
                    ],
                    'estadisticas' => []
                ],
                'filtros_aplicados' => [
                    'producto' => '',
                    'pagina' => 1,
                    'registros_por_pagina' => 20
                ]
            ];
        }
    }

    /**
     * Obtener datos para la vista
     */
    public function obtenerDatosVista()
    {
        try {
            $estadisticas = $this->service->obtenerEstadisticasGenerales();

            return [
                'titulo' => 'Gestión de Reservas por Productos - America TNT',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'estadisticas' => $estadisticas
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => 'Gestión de Reservas por Productos - America TNT',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'estadisticas' => []
            ];
        }
    }

    /**
     * Log de actividad
     */
    public function logActividad($accion, $detalles = null)
    {
        $usuario = $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "RESERVAS_PRODUCTOS - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);

        // Guardar en base de datos
        try {
            $this->repository->registrarLogCancelacion(0, $usuario, $accion, $detalles);
        } catch (Exception $e) {
            error_log("Error guardando log en BD: " . $e->getMessage());
        }
    }

    /**
     * Obtener configuración para JavaScript
     */
    public function obtenerConfiguracionJS()
    {
        return [
            'urlBase' => $this->urlBase,
            'usuario' => $_SESSION['nombre'] ?? 'Usuario',
            'debug' => isset($_GET['debug']),
            'autoRefresh' => true,
            'refreshInterval' => 120000, // 2 minutos
            'registrosPorPagina' => 20,
            'version' => 'reservas_productos_v2.0'
        ];
    }

    /**
     * Manejar mensajes de estado
     */
    public function manejarMensajes()
    {
        $mensaje = '';
        $error = '';

        if (isset($_GET['mensaje'])) {
            $mensaje = htmlspecialchars($_GET['mensaje']);
        }

        if (isset($_GET['error'])) {
            $error = htmlspecialchars($_GET['error']);
        }

        return [
            'mensaje' => $mensaje,
            'error' => $error
        ];
    }

    /**
     * Validar parámetros de entrada
     */
    public function validarParametros($parametros)
    {
        $errores = [];

        if (isset($parametros['page'])) {
            $pagina = (int)$parametros['page'];
            if ($pagina < 1) {
                $errores[] = 'Número de página inválido';
            }
        }

        if (isset($parametros['producto'])) {
            $producto = trim($parametros['producto']);
            if (strlen($producto) > 0 && strlen($producto) < 2) {
                $errores[] = 'El filtro de producto debe tener al menos 2 caracteres';
            }
        }

        return $errores;
    }
}

// SOLO EJECUTAR LA LÓGICA SI NO ESTAMOS EN EL TEST
if (basename($_SERVER['PHP_SELF']) !== 'test_sistema.php') {
    // Verificar que las dependencias existan
    if (!file_exists('repository/reservasRepository.php') || !file_exists('services/reservasService.php')) {
        die("Error: Faltan archivos del sistema MVC. Verifique que existan los archivos repository/reservasRepository.php y services/reservasService.php");
    }

    // Verificar que las variables estén disponibles
    if (!isset($conexion)) {
        die("Error: Variable \$conexion no está disponible. Verifique que se incluya el archivo de conexión.");
    }

    if (!isset($url_base)) {
        // Detectar automáticamente la URL base si no está definida
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $script = $_SERVER['SCRIPT_NAME'];
        $path = dirname(dirname($script)); // Subir dos niveles desde /secciones/stock/
        $url_base = $protocol . '://' . $host . $path . '/';
    }

    // Instanciar el controller
    $controller = new ReservasController($conexion, $url_base);

    // Manejar peticiones API
    if ($controller->handleApiRequest()) {
        exit();
    }

    // Procesar filtros y obtener datos
    $resultado = $controller->procesarFiltros();
    $datosVista = $controller->obtenerDatosVista();

    // Inicializar variables para evitar errores
    $mensajeError = $resultado['error'] ?? '';
    $datosReservas = $resultado['datos'] ?? null;
    $filtrosAplicados = $resultado['filtros_aplicados'] ?? [];

    // Log de actividad
    if (!empty($_GET)) {
        $filtrosStr = [];
        if (!empty($filtrosAplicados['producto'])) $filtrosStr[] = 'Producto: ' . $filtrosAplicados['producto'];
        $filtrosTexto = !empty($filtrosStr) ? implode(', ', $filtrosStr) : 'Sin filtros';
        $controller->logActividad('Consulta productos con reservas', $filtrosTexto);
    }

    // Extraer datos de vista con valores por defecto
    $titulo = $datosVista['titulo'] ?? 'Gestión de Reservas por Productos';
    $url_base = $datosVista['url_base'] ?? '';
    $usuario_actual = $datosVista['usuario_actual'] ?? 'Usuario';
    $estadisticas = $datosVista['estadisticas'] ?? [];
    $configuracionJS = $controller->obtenerConfiguracionJS();
    $mensajes = $controller->manejarMensajes();
}
