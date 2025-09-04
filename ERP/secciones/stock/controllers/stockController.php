<?php
require_once 'repository/stockRepository.php';
require_once 'services/stockService.php';

// Establecer la zona horaria de Paraguay/Asunción
date_default_timezone_set('America/Asuncion');

/**
 * Controller para manejo del inventario de stock agregado
 */
class StockController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new StockRepository($conexion);
        $this->service = new StockServices($this->repository);
        $this->urlBase = $urlBase;
    }

    /**
     * Maneja las peticiones API
     */
    public function handleApiRequest()
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');

        switch ($_GET['action']) {
            case 'buscar_stock':
                $this->buscarStockApi();
                break;

            case 'filtrar_productos':
                $this->filtrarProductosApi();
                break;

            case 'exportar_stock':
                $this->exportarStockApi();
                break;

            case 'obtener_estadisticas':
                $this->obtenerEstadisticasApi();
                break;

            case 'obtener_detalles_producto':
                $this->obtenerDetallesProductoApi();
                break;

            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

        return true;
    }

    /**
     * API: Buscar productos en stock agregado
     */
    private function buscarStockApi()
    {
        $filtroProducto = trim($_GET['producto'] ?? '');
        $filtroTipo = trim($_GET['tipo'] ?? '');
        $stockCompleto = isset($_GET['stock_completo']) ? (bool)$_GET['stock_completo'] : false;
        $pagina = (int)($_GET['page'] ?? 1);
        $registrosPorPagina = (int)($_GET['limit'] ?? 10);

        if ($pagina < 1) $pagina = 1;
        if ($registrosPorPagina < 1 || $registrosPorPagina > 50) $registrosPorPagina = 10;

        try {
            $resultado = $this->service->obtenerStockPaginado($filtroProducto, $pagina, $registrosPorPagina, $filtroTipo, $stockCompleto);

            echo json_encode([
                'success' => true,
                'datos' => $resultado['datos'],
                'paginacion' => $resultado['paginacion'],
                'estadisticas' => $resultado['estadisticas'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error en API buscar stock: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    /**
     * API: Filtrar productos con autocompletado
     */
    private function filtrarProductosApi()
    {
        $termino = trim($_GET['termino'] ?? '');

        if (strlen($termino) < 2) {
            echo json_encode([
                'success' => false,
                'error' => 'Mínimo 2 caracteres'
            ]);
            return;
        }

        try {
            $productos = $this->service->buscarProductosAutocompletado($termino);

            echo json_encode([
                'success' => true,
                'productos' => $productos
            ]);
        } catch (Exception $e) {
            error_log("Error en API filtrar productos: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    /**
     * API: Exportar datos de stock
     */
    private function exportarStockApi()
    {
        $formato = $_GET['formato'] ?? 'csv';
        $filtroProducto = trim($_GET['producto'] ?? '');
        $filtroTipo = trim($_GET['tipo'] ?? '');
        $stockCompleto = isset($_GET['stock_completo']) ? (bool)$_GET['stock_completo'] : false;

        try {
            $datos = $this->service->obtenerStockCompleto($filtroProducto, $filtroTipo, $stockCompleto);

            if ($formato === 'csv') {
                $this->exportarCSV($datos);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Formato no soportado'
                ]);
            }
        } catch (Exception $e) {
            error_log("Error en API exportar stock: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
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
     * API: Obtener detalles de un producto específico
     */
    private function obtenerDetallesProductoApi()
    {
        $nombreProducto = trim($_GET['nombre'] ?? '');
        $bobinasPacote = !empty($_GET['bobinas']) ? (int)$_GET['bobinas'] : null;
        $tipoProducto = trim($_GET['tipo'] ?? '') ?: null;

        if (empty($nombreProducto)) {
            echo json_encode([
                'success' => false,
                'error' => 'Nombre de producto requerido'
            ]);
            return;
        }

        try {
            $detalles = $this->service->obtenerDetallesProducto($nombreProducto, $bobinasPacote, $tipoProducto);

            echo json_encode([
                'success' => true,
                'detalles' => $detalles,
                'total_variantes' => count($detalles)
            ]);
        } catch (Exception $e) {
            error_log("Error en API detalles producto: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    /**
     * Exportar datos a CSV
     */
    private function exportarCSV($datos)
    {
        $fechaHora = date('Y-m-d_H-i-s');
        $nombreArchivo = "stock_agregado_america_tnt_{$fechaHora}.csv";

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$nombreArchivo}\"");
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        $output = fopen('php://output', 'w');

        // BOM para UTF-8
        fwrite($output, "\xEF\xBB\xBF");

        // Encabezados
        fputcsv($output, [
            'Producto',
            'Tipo',
            'Bobinas/Paquete',
            'Gramatura',
            'Largura',
            'Metragem',
            'Total',
            'Disponible',
            'Reservado',
            'Despachado',
            'Cant. Paquetes',
            '% Disponible',
            '% Reservado',
            '% Despachado'
        ]);

        // Datos
        foreach ($datos as $fila) {
            fputcsv($output, [
                $fila['nombre_producto'],
                $fila['tipo_producto'],
                $fila['bobinas_pacote_formateado'],
                $fila['gramatura'] ?? '',
                $fila['largura'] ?? '',
                $fila['metragem'] ?? '',
                $fila['cantidad_total'],
                $fila['cantidad_disponible'],
                $fila['cantidad_reservada'],
                $fila['cantidad_despachada'],
                $fila['cantidad_paquetes'] ?? 0,
                $fila['porcentaje_disponible'] ?? 0,
                $fila['porcentaje_reservado'] ?? 0,
                $fila['porcentaje_despachado'] ?? 0
            ]);
        }

        fclose($output);
        exit();
    }

    /**
     * Procesar filtros del formulario
     */
    public function procesarFiltros()
    {
        $filtros = [
            'producto' => trim($_GET['producto'] ?? ''),
            'tipo' => trim($_GET['tipo'] ?? ''),
            'stock_completo' => trim($_GET['stock_completo'] ?? '0'),
            'pagina' => max(1, (int)($_GET['page'] ?? 1)),
            'registros_por_pagina' => 10
        ];

        try {
            $stockCompleto = $filtros['stock_completo'] === '1';

            $resultado = $this->service->obtenerStockPaginado(
                $filtros['producto'],
                $filtros['pagina'],
                $filtros['registros_por_pagina'],
                $filtros['tipo'],
                $stockCompleto
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
                'datos' => null,
                'filtros_aplicados' => $filtros
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
                'titulo' => 'Stock Agregado - America TNT',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'estadisticas' => $estadisticas,
                'tipos_producto' => $this->service->obtenerTiposProducto()
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => 'Stock Agregado - America TNT',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'estadisticas' => [],
                'tipos_producto' => []
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

        $mensaje = "STOCK_AGREGADO - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);

        // Guardar en base de datos
        try {
            $this->repository->registrarLog($usuario, $ip, $accion, $detalles);
        } catch (Exception $e) {
            error_log("Error guardando log en BD: " . $e->getMessage());
        }
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

        if (isset($parametros['tipo'])) {
            $tipo = trim($parametros['tipo']);
            if (strlen($tipo) > 50) {
                $errores[] = 'El filtro de tipo es demasiado largo';
            }
        }

        return $errores;
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
            'refreshInterval' => 60000, // 1 minuto
            'registrosPorPagina' => 10,
            'version' => 'stock_agregado_v2.0'
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
     * Generar paginación
     */
    public function generarPaginacion($paginaActual, $totalPaginas, $urlBase, $parametros = [])
    {
        $paginacion = [
            'pagina_actual' => $paginaActual,
            'total_paginas' => $totalPaginas,
            'mostrar_paginacion' => $totalPaginas > 1,
            'pagina_anterior' => max(1, $paginaActual - 1),
            'pagina_siguiente' => min($totalPaginas, $paginaActual + 1),
            'tiene_anterior' => $paginaActual > 1,
            'tiene_siguiente' => $paginaActual < $totalPaginas,
            'paginas' => []
        ];

        // Calcular rango de páginas a mostrar
        $inicio = max(1, $paginaActual - 2);
        $fin = min($totalPaginas, $paginaActual + 2);

        for ($i = $inicio; $i <= $fin; $i++) {
            $parametros['page'] = $i;
            $paginacion['paginas'][] = [
                'numero' => $i,
                'es_actual' => $i == $paginaActual,
                'url' => $urlBase . '?' . http_build_query($parametros)
            ];
        }

        return $paginacion;
    }
}

// Verificar que las dependencias existan
if (!file_exists('repository/stockRepository.php') || !file_exists('services/stockService.php')) {
    die("Error: Faltan archivos del sistema MVC. Verifique que existan los archivos repository/stockRepository.php y services/stockService.php");
}

// Instanciar el controller
$controller = new StockController($conexion, $url_base);

// Manejar peticiones API
if ($controller->handleApiRequest()) {
    exit();
}

// Procesar filtros y obtener datos
$resultado = $controller->procesarFiltros();
$datosVista = $controller->obtenerDatosVista();

// Inicializar variables para evitar errores
$mensajeError = $resultado['error'] ?? '';
$datosStock = $resultado['datos'] ?? null;
$filtrosAplicados = $resultado['filtros_aplicados'] ?? [];

// Log de actividad
if (!empty($_GET)) {
    $filtrosStr = !empty($filtrosAplicados['producto']) ? 'Filtro: ' . $filtrosAplicados['producto'] : 'Sin filtros';
    $controller->logActividad('Consulta stock agregado', $filtrosStr);
}

// Extraer datos de vista con valores por defecto
$titulo = $datosVista['titulo'] ?? 'Stock Agregado';
$url_base = $datosVista['url_base'] ?? '';
$usuario_actual = $datosVista['usuario_actual'] ?? 'Usuario';
$estadisticas = $datosVista['estadisticas'] ?? [];
$tipos_producto = $datosVista['tipos_producto'] ?? [];
$configuracionJS = $controller->obtenerConfiguracionJS();
$mensajes = $controller->manejarMensajes();
