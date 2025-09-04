<?php
require_once __DIR__ . '/../repository/VentaRepository.php';
require_once __DIR__ . '/../services/VentaService.php';

date_default_timezone_set('America/Asuncion');
class VentaController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new VentaRepository($conexion);
        $this->service = new VentaService($this->repository);
        $this->urlBase = $urlBase;
    }

    public function handleApiRequest()
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        header('Content-Type: application/json');

        switch ($_GET['action']) {
            case 'buscar_ventas':
                $this->buscarVentasApi();
                break;

            case 'obtener_estadisticas':
                $this->obtenerEstadisticasApi();
                break;

            case 'actualizar_estado_venta':
                $this->actualizarEstadoVentaApi();
                break;

            case 'actualizar_estado_reserva':
                $this->actualizarEstadoReservaApi();
                break;

            case 'obtener_resumen_venta':
                $this->obtenerResumenVentaApi();
                break;

            case 'obtener_proceso_pcp':
                $this->obtenerProcesoPcpApi();
                break;

            case 'obtener_items_produccion':
                $this->obtenerItemsProduccionApi();
                break;

            case 'obtener_items_produccion_agrupados':
                $this->obtenerItemsProduccionAgrupadosApi();
                break;

            case 'obtener_items_despachados_agrupados':
                $this->obtenerItemsDespachosAgrupadosApi();
                break;

            case 'debug_nueva_logica':
                $this->debugNuevaLogicaApi();
                break;

            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

        return true;
    }

    private function obtenerItemsDespachosAgrupadosApi()
    {
        $id = $_GET['id'] ?? null;
        $idProducto = $_GET['id_producto'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'error' => 'ID no proporcionado'
            ]);
            return;
        }

        try {
            $idUsuario = $this->obtenerIdUsuarioSegunRol();
            $items = $this->service->obtenerItemsDespachosAgrupados($id, $idUsuario, $idProducto);
            echo json_encode([
                'success' => true,
                'items' => $items,
                'total_grupos' => count($items),
                'id_producto' => $idProducto,
                'tipo' => 'despachado_agrupado',
                'metodo' => 'simplificado'
            ]);
        } catch (Exception $e) {
            error_log("Error en API items despachados agrupados: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function obtenerItemsProduccionAgrupadosApi()
    {
        $id = $_GET['id'] ?? null;
        $idProducto = $_GET['id_producto'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'error' => 'ID no proporcionado'
            ]);
            return;
        }

        try {
            $idUsuario = $this->obtenerIdUsuarioSegunRol();
            $items = $this->service->obtenerItemsProduccionEnStockAgrupados($id, $idUsuario, $idProducto);

            echo json_encode([
                'success' => true,
                'items' => $items,
                'total_grupos' => count($items),
                'id_producto' => $idProducto,
                'tipo' => 'produccion_agrupado',
                'metodo' => 'simplificado'
            ]);
        } catch (Exception $e) {
            error_log("Error en API items producción agrupados: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function buscarVentasApi()
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
            $idUsuario = $this->obtenerIdUsuarioSegunRol();
            $ventas = $this->service->buscarVentas($termino, $idUsuario);

            echo json_encode([
                'success' => true,
                'ventas' => $ventas
            ]);
        } catch (Exception $e) {
            error_log("Error en API buscar ventas: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerEstadisticasApi()
    {
        try {
            $idUsuario = $this->obtenerIdUsuarioSegunRol();
            $estadisticas = $this->service->obtenerEstadisticas($idUsuario);

            echo json_encode([
                'success' => true,
                'estadisticas' => $estadisticas
            ]);
        } catch (Exception $e) {
            error_log("Error en API estadísticas: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function actualizarEstadoVentaApi()
    {
        $id = $_POST['id'] ?? null;
        $estado = $_POST['estado'] ?? null;

        if (!$id || !$estado) {
            echo json_encode([
                'success' => false,
                'error' => 'Datos incompletos'
            ]);
            return;
        }

        try {
            $idUsuario = $this->obtenerIdUsuarioSegunRol();
            $resultado = $this->service->actualizarEstadoVenta($id, $estado, $idUsuario);

            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en API actualizar estado venta: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function actualizarEstadoReservaApi()
    {
        $id = $_POST['id'] ?? null;
        $estado = $_POST['estado'] ?? null;

        if (!$id || !$estado) {
            echo json_encode([
                'success' => false,
                'error' => 'Datos incompletos'
            ]);
            return;
        }

        try {
            $resultado = $this->service->actualizarEstadoReserva($id, $estado);

            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en API actualizar estado reserva: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerResumenVentaApi()
    {
        $id = $_GET['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'error' => 'ID no proporcionado'
            ]);
            return;
        }

        try {
            $idUsuario = $this->obtenerIdUsuarioSegunRol();

            $resumen = $this->service->obtenerResumenVenta($id, $idUsuario);

            echo json_encode([
                'success' => true,
                'resumen' => $resumen,
                'metodo' => 'simplificado'
            ]);
        } catch (Exception $e) {
            error_log("Error en API resumen venta: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }
    private function obtenerProcesoPcpApi()
    {
        $id = $_GET['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'error' => 'ID no proporcionado'
            ]);
            return;
        }

        try {
            $procesoPcp = $this->repository->obtenerProcesoPCP($id);
            $historialPcp = $this->repository->obtenerHistorialProcesoPCP($id);

            echo json_encode([
                'success' => true,
                'proceso_pcp' => $procesoPcp,
                'historial_pcp' => $historialPcp
            ]);
        } catch (Exception $e) {
            error_log("Error en API proceso PCP: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerItemsProduccionApi()
    {
        $id = $_GET['id'] ?? null;
        $idProducto = $_GET['id_producto'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'error' => 'ID no proporcionado'
            ]);
            return;
        }

        try {
            $idUsuario = $this->obtenerIdUsuarioSegunRol();

            $items = $this->service->obtenerItemsProduccionEnStock($id, $idUsuario, $idProducto);

            echo json_encode([
                'success' => true,
                'items' => $items,
                'total' => count($items),
                'id_producto' => $idProducto,
                'tipo' => 'produccion_individual',
                'metodo' => 'simplificado'
            ]);
        } catch (Exception $e) {
            error_log("Error en API items producción: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }


    private function debugNuevaLogicaApi()
    {
        $id = $_GET['id'] ?? null;

        if (!$id) {
            echo json_encode(['error' => 'ID requerido']);
            return;
        }

        try {
            $venta = $this->repository->obtenerVentaPorId($id);
            $resumenProduccion = $this->repository->obtenerResumenProduccion($id, $venta['cliente']);
            $itemsProduccion = $this->repository->obtenerItemsProduccionEnStock($id);
            $itemsProduccionAgrupados = $this->repository->obtenerItemsProduccionEnStockAgrupados($id);
            $itemsDespachos = $this->repository->obtenerItemsDespachosAgrupados($id);

            $verificacionBobinas = [];
            foreach ($resumenProduccion as $producto) {
                if ($producto['unidad_medida'] === 'bobinas') {
                    $verificacionBobinas[] = [
                        'producto' => $producto['producto'],
                        'pedido' => $producto['cantidad_pedida'],
                        'producido_mostrado' => $producto['cantidad_producida'],
                        'items_producidos' => $producto['items_producidos'] ?? 'N/A',
                        'bobinas_producidas' => $producto['bobinas_producidas'] ?? 'N/A',
                        'es_correcto' => ($producto['cantidad_producida'] == ($producto['bobinas_producidas'] ?? 0)),
                        'debug_info' => $producto['debug_info'] ?? null
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'debug' => [
                    'venta_id' => $id,
                    'cliente' => $venta['cliente'] ?? 'N/A',
                    'resumen_produccion' => $resumenProduccion,
                    'items_produccion_count' => count($itemsProduccion),
                    'items_produccion_agrupados_count' => count($itemsProduccionAgrupados),
                    'items_despachos_count' => count($itemsDespachos),
                    'verificacion_bobinas' => $verificacionBobinas,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function obtenerVentaParaVer($id)
    {
        try {
            $idUsuario = $this->obtenerIdUsuarioSegunRol();
            return $this->service->obtenerVentaPorId($id, $idUsuario);
        } catch (Exception $e) {
            error_log("Error obteniendo venta: " . $e->getMessage());
            throw new Exception('Venta no encontrada');
        }
    }


    public function obtenerResumenVenta($id)
    {
        try {
            $idUsuario = $this->obtenerIdUsuarioSegunRol();
            return $this->service->obtenerResumenVenta($id, $idUsuario);
        } catch (Exception $e) {
            error_log("Error obteniendo resumen de venta: " . $e->getMessage());
            throw new Exception('No se pudo obtener el resumen de la venta');
        }
    }


    public function obtenerListaVentas($filtros = [])
    {
        try {
            $idUsuario = $this->obtenerIdUsuarioSegunRol();
            return $this->service->obtenerVentas($idUsuario, $filtros);
        } catch (Exception $e) {
            error_log("Error obteniendo lista de ventas: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerDatosVista()
    {
        try {
            $idUsuario = $this->obtenerIdUsuarioSegunRol();
            $estadisticas = $this->service->obtenerEstadisticas($idUsuario);

            return [
                'titulo' => 'Seguimiento de Ventas',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => $this->esAdministrador(),
                'estadisticas' => $estadisticas,
                'estados_disponibles' => $this->repository->obtenerEstadosDisponibles()
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => 'Seguimiento de Ventas',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => false,
                'estadisticas' => [],
                'estados_disponibles' => []
            ];
        }
    }


    private function obtenerIdUsuarioSegunRol()
    {
        return $this->esAdministrador() ? null : $_SESSION['id'];
    }


    private function esAdministrador()
    {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === '1';
    }


    public function procesarFiltros()
    {
        $filtros = [
            'cliente' => trim($_GET['cliente'] ?? ''),
            'estado' => trim($_GET['estado'] ?? ''),
            'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
            'proforma' => trim($_GET['proforma'] ?? ''),
            'pagina' => max(1, (int)($_GET['page'] ?? 1))
        ];

        try {
            $ventas = $this->obtenerListaVentas($filtros);

            return [
                'error' => '',
                'ventas' => $ventas,
                'filtros_aplicados' => $filtros
            ];
        } catch (Exception $e) {
            error_log("Error procesando filtros: " . $e->getMessage());
            return [
                'error' => 'Error al procesar la búsqueda',
                'ventas' => [],
                'filtros_aplicados' => $filtros
            ];
        }
    }

    public function verificarPermisos($accion, $idVenta = null)
    {
        $esAdmin = $this->esAdministrador();
        $tienePermiso = false;

        switch ($accion) {
            case 'ver':
            case 'listar':
                $tienePermiso = true;
                break;

            case 'actualizar_estado':
                $tienePermiso = $esAdmin || (isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['1', '2']));
                break;

            case 'actualizar_reserva':
                $tienePermiso = $esAdmin || (isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['1', '2', '3']));
                break;

            case 'finalizar_manualmente':
                $tienePermiso = $esAdmin || (isset($_SESSION['rol']) && $_SESSION['rol'] === '4'); // Rol 4 = PCP
                break;

            default:
                $tienePermiso = false;
        }

        if ($tienePermiso && !$esAdmin && $idVenta && in_array($accion, ['ver', 'actualizar_estado'])) {
            try {
                $venta = $this->service->obtenerVentaPorId($idVenta, $_SESSION['id']);
                $tienePermiso = ($venta !== false);
            } catch (Exception $e) {
                $tienePermiso = false;
            }
        }

        return $tienePermiso;
    }


    public function obtenerConfiguracionJS()
    {
        return [
            'url_base' => $this->urlBase,
            'usuario' => $_SESSION['nombre'] ?? 'Usuario',
            'esAdmin' => $this->esAdministrador(),
            'debug' => isset($_GET['debug']),
            'rol' => $_SESSION['rol'] ?? '0',
            'estados_disponibles' => $this->repository->obtenerEstadosDisponibles()
        ];
    }

    public function logActividad($accion, $detalles = null)
    {
        $usuario = $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "VENTAS - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);
    }


    public function esVentaFinalizadaManualmente($idVenta)
    {
        try {
            $venta = $this->repository->obtenerVentaPorId($idVenta);
            return $venta && $venta['estado'] === 'Finalizado Manualmente';
        } catch (Exception $e) {
            error_log("Error verificando estado de venta: " . $e->getMessage());
            return false;
        }
    }


    public function obtenerInformacionPcp($idVenta)
    {
        try {
            return [
                'proceso_actual' => $this->repository->obtenerProcesoPCP($idVenta),
                'historial_completo' => $this->repository->obtenerHistorialProcesoPCP($idVenta)
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo información PCP: " . $e->getMessage());
            return [
                'proceso_actual' => null,
                'historial_completo' => []
            ];
        }
    }
}

$repositoryPath = __DIR__ . '/../repository/VentaRepository.php';
$servicePath = __DIR__ . '/../services/VentaService.php';

if (!file_exists($repositoryPath) || !file_exists($servicePath)) {
    die("Error crítico: Faltan archivos de dependencia del controlador." .
        "<br>Ruta del repositorio buscada: " . htmlspecialchars($repositoryPath) .
        "<br>Ruta del servicio buscada: " . htmlspecialchars($servicePath));
}
