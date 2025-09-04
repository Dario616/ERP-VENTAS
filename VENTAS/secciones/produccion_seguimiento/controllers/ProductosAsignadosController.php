<?php
require_once __DIR__ . '/../repository/ProductosAsignadosRepository.php';
require_once __DIR__ . '/../services/ProductosAsignadosService.php';

date_default_timezone_set('America/Asuncion');

class ProductosAsignadosController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new ProductosAsignadosRepository($conexion);
        $this->service = new ProductosAsignadosService($this->repository);
        $this->urlBase = $urlBase;
    }

    public function handleApiRequest()
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        header('Content-Type: application/json');

        switch ($_GET['action']) {
            case 'buscar_clientes_productos':
                $this->buscarClientesProductosApi();
                break;

            case 'obtener_estadisticas_asignados':
                $this->obtenerEstadisticasAsignadosApi();
                break;

            case 'obtener_clientes':
                $this->obtenerClientesApi();
                break;

            case 'obtener_estados_orden':
                $this->obtenerEstadosOrdenApi();
                break;

            case 'obtener_tipos_producto_asignados':
                $this->obtenerTiposProductoAsignadosApi();
                break;

            case 'obtener_detalles_orden':
                $this->obtenerDetallesOrdenApi();
                break;

            case 'obtener_resumen_clientes':
                $this->obtenerResumenClientesApi();
                break;

            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

        return true;
    }

    private function buscarClientesProductosApi()
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
            $resultados = $this->service->buscarClientesProductos($termino);

            echo json_encode([
                'success' => true,
                'resultados' => $resultados
            ]);
        } catch (Exception $e) {
            error_log("Error en API buscar clientes/productos: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerEstadisticasAsignadosApi()
    {
        try {
            $estadisticas = $this->service->obtenerEstadisticas();

            echo json_encode([
                'success' => true,
                'estadisticas' => $estadisticas
            ]);
        } catch (Exception $e) {
            error_log("Error en API estadísticas asignados: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerClientesApi()
    {
        try {
            $clientes = $this->service->obtenerClientes();

            echo json_encode([
                'success' => true,
                'clientes' => $clientes
            ]);
        } catch (Exception $e) {
            error_log("Error en API clientes: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerEstadosOrdenApi()
    {
        try {
            $estados = $this->service->obtenerEstadosOrden();

            echo json_encode([
                'success' => true,
                'estados' => $estados
            ]);
        } catch (Exception $e) {
            error_log("Error en API estados orden: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerTiposProductoAsignadosApi()
    {
        try {
            $tipos = $this->service->obtenerTiposProducto();

            echo json_encode([
                'success' => true,
                'tipos' => $tipos
            ]);
        } catch (Exception $e) {
            error_log("Error en API tipos producto asignados: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerDetallesOrdenApi()
    {
        $idOrdenProduccion = $_GET['id_orden_produccion'] ?? '';

        if (empty($idOrdenProduccion) || !is_numeric($idOrdenProduccion)) {
            echo json_encode([
                'success' => false,
                'error' => 'ID de orden de producción inválido'
            ]);
            return;
        }

        try {
            $detalles = $this->service->obtenerDetallesOrden($idOrdenProduccion);

            echo json_encode([
                'success' => true,
                'detalles' => $detalles
            ]);
        } catch (Exception $e) {
            error_log("Error en API detalles orden: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function obtenerResumenClientesApi()
    {
        try {
            $resumen = $this->service->obtenerResumenPorCliente();

            echo json_encode([
                'success' => true,
                'resumen' => $resumen
            ]);
        } catch (Exception $e) {
            error_log("Error en API resumen clientes: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    public function obtenerProductosAsignadosAgrupados($filtros = [])
    {
        try {
            return $this->service->obtenerProductosAsignadosAgrupados($filtros);
        } catch (Exception $e) {
            error_log("Error obteniendo productos asignados agrupados: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerDetallesOrden($idOrdenProduccion)
    {
        try {
            return $this->service->obtenerDetallesOrden($idOrdenProduccion);
        } catch (Exception $e) {
            error_log("Error obteniendo detalles de orden: " . $e->getMessage());
            throw new Exception('Orden no encontrada');
        }
    }

    public function obtenerDatosVista()
    {
        try {
            $estadisticas = $this->service->obtenerEstadisticas();
            $clientes = $this->service->obtenerClientes();
            $estadosOrden = $this->service->obtenerEstadosOrden();
            $tiposProducto = $this->service->obtenerTiposProducto();

            return [
                'titulo' => 'Productos Asignados a Órdenes',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => $this->esAdministrador(),
                'estadisticas' => $estadisticas,
                'clientes' => $clientes,
                'estados_orden' => $estadosOrden,
                'tipos_producto' => $tiposProducto
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => 'Productos Asignados a Órdenes',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => false,
                'estadisticas' => [],
                'clientes' => [],
                'estados_orden' => [],
                'tipos_producto' => []
            ];
        }
    }

    public function procesarFiltros()
    {
        $filtros = [
            'cliente' => trim($_GET['cliente'] ?? ''),
            'producto' => trim($_GET['producto'] ?? ''),
            'tipo_producto' => trim($_GET['tipo_producto'] ?? ''),
            'estado_orden' => trim($_GET['estado_orden'] ?? ''),
            'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
            'pagina' => max(1, (int)($_GET['page'] ?? 1))
        ];

        try {
            $errores = $this->service->validarFiltros($filtros);

            if (!empty($errores)) {
                return [
                    'error' => implode(', ', $errores),
                    'grupos' => [],
                    'filtros_aplicados' => $filtros
                ];
            }

            $grupos = $this->obtenerProductosAsignadosAgrupados($filtros);

            return [
                'error' => '',
                'grupos' => $grupos,
                'filtros_aplicados' => $filtros,
                'resumen' => $this->service->generarResumenExport($grupos)
            ];
        } catch (Exception $e) {
            error_log("Error procesando filtros: " . $e->getMessage());
            return [
                'error' => 'Error al procesar la búsqueda',
                'grupos' => [],
                'filtros_aplicados' => $filtros
            ];
        }
    }

    private function esAdministrador()
    {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === '1';
    }

    public function validarParametros($parametros)
    {
        $errores = [];

        if (isset($parametros['termino'])) {
            $termino = trim($parametros['termino']);
            if (strlen($termino) > 0 && strlen($termino) < 2) {
                $errores[] = 'El término de búsqueda debe tener al menos 2 caracteres';
            }
        }

        if (isset($parametros['fecha_desde'])) {
            if (!empty($parametros['fecha_desde']) && !DateTime::createFromFormat('Y-m-d', $parametros['fecha_desde'])) {
                $errores[] = 'Formato de fecha desde inválido';
            }
        }

        if (isset($parametros['fecha_hasta'])) {
            if (!empty($parametros['fecha_hasta']) && !DateTime::createFromFormat('Y-m-d', $parametros['fecha_hasta'])) {
                $errores[] = 'Formato de fecha hasta inválido';
            }
        }

        if (isset($parametros['id_orden_produccion'])) {
            if (!empty($parametros['id_orden_produccion']) && !is_numeric($parametros['id_orden_produccion'])) {
                $errores[] = 'ID de orden de producción debe ser numérico';
            }
        }

        return $errores;
    }

    public function logActividad($accion, $detalles = null)
    {
        $usuario = $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "PRODUCTOS_ASIGNADOS - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);
    }

    public function obtenerConfiguracionJS()
    {
        return [
            'url_base' => $this->urlBase,
            'usuario' => $_SESSION['nombre'] ?? 'Usuario',
            'esAdmin' => $this->esAdministrador(),
            'debug' => isset($_GET['debug']),
            'rol' => $_SESSION['rol'] ?? '0',
            'modulo' => 'productos_asignados'
        ];
    }

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

    public function verificarPermisos($accion)
    {
        $esAdmin = $this->esAdministrador();
        $tienePermiso = false;

        switch ($accion) {
            case 'ver':
            case 'listar':
                $tienePermiso = true;
                break;

            case 'exportar':
                $tienePermiso = $esAdmin || (isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['1', '2', '3']));
                break;

            case 'modificar_orden':
                $tienePermiso = $esAdmin;
                break;

            default:
                $tienePermiso = false;
        }

        return $tienePermiso;
    }
}

$repositoryPath = __DIR__ . '/../repository/ProductosAsignadosRepository.php';
$servicePath = __DIR__ . '/../services/ProductosAsignadosService.php';

if (!file_exists($repositoryPath) || !file_exists($servicePath)) {
    die("Error crítico: Faltan archivos de dependencia del controlador." .
        "<br>Ruta del repositorio buscada: " . htmlspecialchars($repositoryPath) .
        "<br>Ruta del servicio buscada: " . htmlspecialchars($servicePath));
}
