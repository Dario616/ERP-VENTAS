<?php

require_once __DIR__ . '/../repository/PendienteRepository.php';
require_once __DIR__ . '/../services/PendienteService.php';

date_default_timezone_set('America/Asuncion');

class PendienteController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new PendienteRepository($conexion);
        $this->service = new PendienteService($this->repository);
        $this->urlBase = $urlBase;
    }

    /**
     * Maneja peticiones API AJAX
     * @return bool True si se manejó una petición API, false en caso contrario
     */
    public function handleApiRequest()
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        header('Content-Type: application/json');

        try {
            switch ($_GET['action']) {
                case 'obtener_resumen':
                    $this->obtenerResumenApi();
                    break;

                case 'obtener_detalles':
                    $this->obtenerDetallesApi();
                    break;

                case 'obtener_estadisticas':
                    $this->obtenerEstadisticasApi();
                    break;

                case 'actualizar_datos':
                    $this->actualizarDatosApi();
                    break;

                case 'exportar_datos':
                    $this->exportarDatosApi();
                    break;

                case 'obtener_productos_por_destino':
                    $this->obtenerProductosPorDestinoApi();
                    break;

                case 'obtener_detalle_producto':
                    $this->obtenerDetalleProductoApi();
                    break;

                default:
                    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            }
        } catch (Exception $e) {
            error_log("Error en API PendienteController: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }

        return true;
    }

    /**
     * Obtiene todos los datos necesarios para la vista principal
     * @return array Datos completos para la vista
     */
    public function obtenerDatosVista()
    {
        try {
            $filtros = $this->procesarFiltros();

            // Validar filtros
            $erroresFiltros = $this->service->validarFiltros($filtros);
            if (!empty($erroresFiltros)) {
                return [
                    'error' => implode(', ', $erroresFiltros),
                    'resumen_pendiente' => [],
                    'detalles_por_tipo' => [],
                    'estadisticas' => [],
                    'metricas' => [],
                    'filtros_aplicados' => $filtros,
                    'datos_vista' => $this->obtenerDatosVistaBasicos()
                ];
            }

            $resumenPendiente = $this->service->obtenerResumenAgregado($filtros);
            $detallesPorTipo = $this->service->obtenerDetallesPorTipo($filtros);
            $estadisticas = $this->service->obtenerEstadisticas($filtros);
            $metricas = $this->service->obtenerMetricasRendimiento();

            return [
                'error' => '',
                'resumen_pendiente' => $resumenPendiente,
                'detalles_por_tipo' => $detallesPorTipo,
                'estadisticas' => $estadisticas,
                'metricas' => $metricas,
                'filtros_aplicados' => $filtros,
                'datos_vista' => $this->obtenerDatosVistaBasicos()
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos vista pendientes: " . $e->getMessage());
            return [
                'error' => 'Error al cargar los datos de producción pendiente',
                'resumen_pendiente' => [],
                'detalles_por_tipo' => [],
                'estadisticas' => [],
                'metricas' => [],
                'filtros_aplicados' => [],
                'datos_vista' => $this->obtenerDatosVistaBasicos()
            ];
        }
    }

    /**
     * Procesa filtros desde GET con soporte para nuevos campos
     * @return array Filtros procesados y validados
     */
    public function procesarFiltros()
    {
        return [
            'cliente' => $this->limpiarInput($_GET['cliente'] ?? ''),
            'tipo_producto' => $this->limpiarInput($_GET['tipo_producto'] ?? ''),
            'fecha_desde' => $this->validarFecha($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => $this->validarFecha($_GET['fecha_hasta'] ?? ''),
            'estado' => $this->limpiarInput($_GET['estado'] ?? ''),
            'destino' => $this->limpiarInput($_GET['destino'] ?? ''),
            'mostrar_completados' => isset($_GET['mostrar_completados']) ? (bool)$_GET['mostrar_completados'] : false,
            'usuario_pcp' => $this->limpiarInput($_GET['usuario_pcp'] ?? ''),
            'categoria_tnt' => $this->limpiarInput($_GET['categoria_tnt'] ?? '') // M1 o M2
        ];
    }

    /**
     * Obtiene configuración para el frontend JavaScript
     * @return array Configuración JS
     */
    public function obtenerConfiguracionJS()
    {
        return [
            'urlBase' => $this->urlBase,
            'usuario' => $_SESSION['nombre'] ?? 'Usuario',
            'esAdmin' => $this->esAdministrador(),
            'autoRefresh' => true,
            'refreshInterval' => 300000, // 5 minutos
            'debug' => isset($_GET['debug']),
            'rol' => $_SESSION['rol'] ?? '0',
            'endpoints' => [
                'resumen' => $this->urlBase . 'secciones/produccion/pendientes.php?action=obtener_resumen',
                'detalles' => $this->urlBase . 'secciones/produccion/pendientes.php?action=obtener_detalles',
                'estadisticas' => $this->urlBase . 'secciones/produccion/pendientes.php?action=obtener_estadisticas',
                'detalle_producto' => $this->urlBase . 'secciones/produccion/pendientes.php?action=obtener_detalle_producto'
            ],
            'permisos' => [
                'ver' => $this->verificarPermisos('ver'),
                'exportar' => $this->verificarPermisos('exportar'),
                'editar' => $this->verificarPermisos('actualizar_estado')
            ]
        ];
    }

    /**
     * Maneja mensajes de éxito y error para mostrar en la vista
     * @return array Mensajes para la vista
     */
    public function manejarMensajes()
    {
        $mensaje = '';
        $error = '';
        $info = '';

        if (isset($_GET['mensaje'])) {
            $mensaje = htmlspecialchars($_GET['mensaje']);
        }

        if (isset($_GET['error'])) {
            $error = htmlspecialchars($_GET['error']);
        }

        if (isset($_GET['info'])) {
            $info = htmlspecialchars($_GET['info']);
        }

        // Mensajes específicos de la nueva funcionalidad
        if (isset($_GET['estado_actualizado'])) {
            $mensaje = "Estado del producto actualizado correctamente";
        }

        return [
            'mensaje' => $mensaje,
            'error' => $error,
            'info' => $info
        ];
    }

    /**
     * Verifica permisos del usuario para diferentes acciones
     * @param string $accion Acción a verificar
     * @return bool True si tiene permisos, false en caso contrario
     */
    public function verificarPermisos($accion)
    {
        $esAdmin = $this->esAdministrador();

        switch ($accion) {
            case 'ver':
            case 'listar':
            case 'filtrar':
                return $esAdmin;

            case 'exportar':
                return $esAdmin;

            case 'gestionar_ordenes':
            case 'actualizar_estado':
                return $esAdmin && in_array($_SESSION['rol'], ['1', '4']); // Solo Admin y Producción

            case 'ver_detalle_completo':
                return $esAdmin && in_array($_SESSION['rol'], ['1', '4', '2']); // Admin, Producción y Ventas

            default:
                return false;
        }
    }

    /**
     * Registra actividad del usuario para auditoría
     * @param string $accion Acción realizada
     * @param string|null $detalles Detalles adicionales
     */
    public function logActividad($accion, $detalles = null)
    {
        $usuario = $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "PRODUCCION_PENDIENTE_V2 - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);
    }

    /**
     * MÉTODOS PRIVADOS PARA API
     */
    private function obtenerResumenApi()
    {
        $filtros = $this->procesarFiltros();

        $erroresFiltros = $this->service->validarFiltros($filtros);
        if (!empty($erroresFiltros)) {
            echo json_encode([
                'success' => false,
                'error' => implode(', ', $erroresFiltros)
            ]);
            return;
        }

        $resumen = $this->service->obtenerResumenAgregado($filtros);

        $this->logActividad('consulta_resumen_api', 'Filtros: ' . json_encode($filtros));

        echo json_encode([
            'success' => true,
            'resumen' => $resumen,
            'filtros_aplicados' => $filtros,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    private function obtenerDetallesApi()
    {
        $tipo = $_GET['tipo'] ?? '';

        if (empty($tipo)) {
            echo json_encode([
                'success' => false,
                'error' => 'Tipo de producto requerido'
            ]);
            return;
        }

        $filtros = $this->procesarFiltros();
        $filtros['tipo_producto'] = $tipo;

        $detalles = $this->service->obtenerDetallesPorTipo($filtros);

        $this->logActividad('consulta_detalles_api', "Tipo: {$tipo}");

        echo json_encode([
            'success' => true,
            'detalles' => $detalles[$tipo] ?? [],
            'tipo' => $tipo,
            'total_items' => count($detalles[$tipo] ?? []),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    private function obtenerEstadisticasApi()
    {
        $filtros = $this->procesarFiltros();

        $estadisticas = $this->service->obtenerEstadisticas($filtros);
        $metricas = $this->service->obtenerMetricasRendimiento();

        $this->logActividad('consulta_estadisticas_api');

        echo json_encode([
            'success' => true,
            'estadisticas' => $estadisticas,
            'metricas' => $metricas,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    private function actualizarDatosApi()
    {
        // Obtener datos actualizados
        $datos = $this->obtenerDatosVista();

        $this->logActividad('actualizacion_datos_api');

        echo json_encode([
            'success' => true,
            'datos_actualizados' => $datos,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    private function exportarDatosApi()
    {
        if (!$this->verificarPermisos('exportar')) {
            echo json_encode([
                'success' => false,
                'error' => 'Sin permisos para exportar datos'
            ]);
            return;
        }

        $formato = $_GET['formato'] ?? 'json';
        $filtros = $this->procesarFiltros();

        $datos = $this->service->obtenerDetallesPorTipo($filtros);

        $this->logActividad('exportar_datos_api', "Formato: {$formato}");

        switch ($formato) {
            case 'csv':
                $this->exportarCSV($datos);
                break;
            case 'excel':
                $this->exportarExcel($datos);
                break;
            default:
                echo json_encode([
                    'success' => true,
                    'datos' => $datos,
                    'formato' => $formato,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
        }
    }

    private function obtenerProductosPorDestinoApi()
    {
        $destino = $_GET['destino'] ?? '';

        if (empty($destino)) {
            echo json_encode([
                'success' => false,
                'error' => 'Destino requerido'
            ]);
            return;
        }

        $productos = $this->repository->obtenerProductosPorDestino($destino);

        $this->logActividad('consulta_productos_por_destino', "Destino: {$destino}");

        echo json_encode([
            'success' => true,
            'productos' => $productos,
            'destino' => $destino,
            'total_productos' => count($productos),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    private function obtenerDetalleProductoApi()
    {
        if (!$this->verificarPermisos('ver_detalle_completo')) {
            echo json_encode([
                'success' => false,
                'error' => 'Sin permisos para ver detalles completos'
            ]);
            return;
        }

        $idProducto = (int)($_GET['id_producto'] ?? 0);

        if ($idProducto <= 0) {
            echo json_encode([
                'success' => false,
                'error' => 'ID de producto requerido'
            ]);
            return;
        }

        $detalle = $this->repository->obtenerDetalleProducto($idProducto);

        $this->logActividad('consulta_detalle_producto', "ID: {$idProducto}");

        if ($detalle) {
            // Enriquecer el detalle con información de peso
            $cantidadTotal = (float)$detalle['cantidad'];
            $stockProducido = (float)$detalle['stock_producido'];
            $cantidadPendiente = $cantidadTotal - $stockProducido;
            $pesoUnitario = (float)($detalle['peso_unitario'] ?? 0);

            $detalle['cantidad_pendiente'] = $cantidadPendiente;
            $detalle['peso_pendiente_kg'] = 0;

            // Calcular peso en kg para toallitas y paños
            if (strtoupper($detalle['tipoproducto']) === 'TOALLITAS' && $pesoUnitario > 0) {
                $detalle['peso_pendiente_kg'] = $cantidadPendiente * $pesoUnitario;
            } elseif (strpos(strtoupper($detalle['producto_descripcion']), 'PAÑO') !== false && $pesoUnitario > 0) {
                $detalle['peso_pendiente_kg'] = $cantidadPendiente * $pesoUnitario;
            }

            echo json_encode([
                'success' => true,
                'detalle' => $detalle,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Producto no encontrado'
            ]);
        }
    }

    /**
     * MÉTODOS PRIVADOS DE SOPORTE
     */
    private function obtenerDatosVistaBasicos()
    {
        return [
            'titulo' => 'Producción Pendiente',
            'url_base' => $this->urlBase,
            'fecha_actual' => date('Y-m-d H:i:s'),
            'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
            'es_admin' => $this->esAdministrador(),
            'rol' => $_SESSION['rol'] ?? '0',
            'version' => 'v2.1' // Indicador de la nueva versión con peso
        ];
    }

    private function esAdministrador()
    {
        return isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['1', '4']); // Admin y Producción
    }

    private function limpiarInput($input)
    {
        return trim(htmlspecialchars(strip_tags($input)));
    }

    private function validarFecha($fecha)
    {
        if (empty($fecha)) {
            return '';
        }

        $timestamp = strtotime($fecha);
        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d', $timestamp);
    }

    private function exportarCSV($datos)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="produccion_pendiente_v2_1_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // Cabeceras CSV mejoradas con información de peso
        fputcsv($output, [
            'ID Producto Producción',
            'Tipo',
            'Categoría',
            'Cliente',
            'Producto',
            'Destino',
            'Cantidad Total',
            'Cantidad Pendiente',
            'Stock Producido',
            'Unidad Principal',
            'Peso Pendiente (kg)',
            'Peso Unitario',
            'Estado',
            'Fecha Asignación',
            'Días Pendiente',
            'Observaciones'
        ]);

        // Datos
        foreach ($datos as $tipo => $items) {
            foreach ($items as $item) {
                fputcsv($output, [
                    $item['id'] ?? '',
                    $tipo,
                    $item['categoria'] ?? $tipo,
                    $item['cliente'] ?? '',
                    $item['producto_descripcion'] ?? '',
                    $item['destino'] ?? '',
                    $item['cantidad_total'] ?? 0,
                    $item['cantidad_pendiente'] ?? 0,
                    $item['stock_producido'] ?? 0,
                    $item['unidad'] ?? 'kg',
                    $item['peso_pendiente_kg'] ?? $item['cantidad_pendiente'] ?? 0,
                    $item['peso_unitario'] ?? 0,
                    $item['estado_orden'] ?? 'Sin Estado',
                    isset($item['fecha_orden']) ? date('d/m/Y', strtotime($item['fecha_orden'])) : '',
                    $item['dias_pendiente'] ?? 0,
                    $item['observaciones'] ?? ''
                ]);
            }
        }

        fclose($output);
        exit;
    }

    private function exportarExcel($datos)
    {
        // Implementación básica para Excel - requeriría una librería como PhpSpreadsheet
        echo json_encode([
            'success' => false,
            'error' => 'Exportación a Excel no implementada. Use formato CSV.',
            'sugerencia' => 'Instale PhpSpreadsheet para habilitar exportación a Excel'
        ]);
    }

    /**
     * Obtiene lista de destinos únicos para filtros
     * @return array Lista de destinos
     */
    public function obtenerDestinos()
    {
        try {
            $datos = $this->repository->obtenerResumenPendiente();
            $destinos = [];

            foreach ($datos as $item) {
                $destino = $item['destino'] ?? 'Sin destino';
                if (!in_array($destino, $destinos)) {
                    $destinos[] = $destino;
                }
            }

            sort($destinos);
            return $destinos;
        } catch (Exception $e) {
            error_log("Error obteniendo destinos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene lista de estados únicos para filtros
     * @return array Lista de estados
     */
    public function obtenerEstados()
    {
        return [
            'Sin Estado' => 'Sin Estado (Orden no emitida)',
            'Pendiente' => 'Pendiente',
            'Orden Emitida' => 'Orden Emitida',
            'En Produccion' => 'En Producción',
            'Completado' => 'Completado',
            'Cancelado' => 'Cancelado'
        ];
    }
}
