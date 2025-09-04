<?php
require_once __DIR__ . '/../repository/ProduccionRepository.php';
require_once __DIR__ . '/../services/ProduccionService.php';

date_default_timezone_set('America/Asuncion');

class ProduccionController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new ProduccionRepository($conexion);
        $this->service = new ProduccionService($this->repository);
        $this->urlBase = $urlBase;
    }

    public function handleApiRequest()
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        header('Content-Type: application/json');

        switch ($_GET['action']) {
            case 'buscar_productos':
                $this->buscarProductosApi();
                break;

            case 'obtener_estadisticas':
                $this->obtenerEstadisticasApi();
                break;

            case 'obtener_tipos_producto':
                $this->obtenerTiposProductoApi();
                break;

            case 'obtener_detalles_grupo':
                $this->obtenerDetallesGrupoApi();
                break;

            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

        return true;
    }

    private function buscarProductosApi()
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
            $productos = $this->service->buscarProductos($termino);

            echo json_encode([
                'success' => true,
                'productos' => $productos
            ]);
        } catch (Exception $e) {
            error_log("Error en API buscar productos: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerEstadisticasApi()
    {
        try {
            $estadisticas = $this->service->obtenerEstadisticas();

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

    private function obtenerTiposProductoApi()
    {
        try {
            $tipos = $this->service->obtenerTiposProducto();

            echo json_encode([
                'success' => true,
                'tipos' => $tipos
            ]);
        } catch (Exception $e) {
            error_log("Error en API tipos producto: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerDetallesGrupoApi()
    {
        $nombreProducto = $_GET['nombre_producto'] ?? '';
        $tipoProducto = $_GET['tipo_producto'] ?? '';
        $metragem = $_GET['metragem'] ?? '';
        $largura = $_GET['largura'] ?? '';
        $gramatura = $_GET['gramatura'] ?? '';

        if (empty($nombreProducto) || empty($tipoProducto)) {
            echo json_encode([
                'success' => false,
                'error' => 'Parámetros insuficientes'
            ]);
            return;
        }

        try {
            $detalles = $this->service->obtenerDetallesGrupo($nombreProducto, $tipoProducto, $metragem, $largura, $gramatura);

            echo json_encode([
                'success' => true,
                'detalles' => $detalles
            ]);
        } catch (Exception $e) {
            error_log("Error en API detalles grupo: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    public function obtenerProduccionAgrupada($filtros = [])
    {
        try {
            return $this->service->obtenerProduccionAgrupada($filtros);
        } catch (Exception $e) {
            error_log("Error obteniendo producción agrupada: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerDetallesGrupo($nombreProducto, $tipoProducto, $metragem, $largura, $gramatura)
    {
        try {
            return $this->service->obtenerDetallesGrupo($nombreProducto, $tipoProducto, $metragem, $largura, $gramatura);
        } catch (Exception $e) {
            error_log("Error obteniendo detalles de grupo: " . $e->getMessage());
            throw new Exception('Grupo no encontrado');
        }
    }

    public function obtenerDatosVista()
    {
        try {
            $estadisticas = $this->service->obtenerEstadisticas();
            $tiposProducto = $this->service->obtenerTiposProducto();

            return [
                'titulo' => 'Seguimiento de Producción',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => $this->esAdministrador(),
                'estadisticas' => $estadisticas,
                'tipos_producto' => $tiposProducto
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => 'Seguimiento de Producción',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => false,
                'estadisticas' => [],
                'tipos_producto' => []
            ];
        }
    }

    public function procesarFiltros()
    {
        $filtros = [
            'producto' => trim($_GET['producto'] ?? ''),
            'tipo_producto' => trim($_GET['tipo_producto'] ?? ''),
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

            $grupos = $this->obtenerProduccionAgrupada($filtros);

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

        return $errores;
    }

    public function logActividad($accion, $detalles = null)
    {
        $usuario = $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "PRODUCCION - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

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
            'rol' => $_SESSION['rol'] ?? '0'
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

            default:
                $tienePermiso = false;
        }

        return $tienePermiso;
    }
}

$repositoryPath = __DIR__ . '/../repository/ProduccionRepository.php';
$servicePath = __DIR__ . '/../services/ProduccionService.php';

if (!file_exists($repositoryPath) || !file_exists($servicePath)) {
    die("Error crítico: Faltan archivos de dependencia del controlador." .
        "<br>Ruta del repositorio buscada: " . htmlspecialchars($repositoryPath) .
        "<br>Ruta del servicio buscada: " . htmlspecialchars($servicePath));
}
