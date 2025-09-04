<?php
require_once 'repository/ContableRepository.php';
require_once 'services/ContableService.php';

date_default_timezone_set('America/Asuncion');

class ContableController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new ContableRepository($conexion);
        $this->service = new ContableService($this->repository);
        $this->urlBase = $urlBase;
    }

    public function getConexion()
    {
        return $this->repository->getConexion();
    }

    public function handleApiRequest()
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        header('Content-Type: application/json');

        switch ($_GET['action']) {
            case 'obtener_estadisticas':
                $this->obtenerEstadisticasApi();
                break;

            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

        return true;
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
            error_log("Error en API estadísticas contables: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    public function obtenerAutorizacionesPendientes($filtros = [], $pagina = 1)
    {
        try {
            return $this->service->obtenerAutorizacionesPendientes($filtros, $pagina);
        } catch (Exception $e) {
            error_log("Error obteniendo autorizaciones pendientes: " . $e->getMessage());
            return [
                'autorizaciones' => [],
                'total' => 0,
                'total_paginas' => 0,
                'pagina_actual' => $pagina
            ];
        }
    }

    public function obtenerVentaParaRevision($idVenta)
    {
        try {
            return $this->service->obtenerVentaParaRevision($idVenta);
        } catch (Exception $e) {
            error_log("Error obteniendo venta para revisión: " . $e->getMessage());
            throw new Exception('Venta no encontrada');
        }
    }

    public function procesarAccionAutorizacion($idVenta, $accion, $datos)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['error' => 'Método no permitido'];
        }

        if (!isset($_SESSION['id']) || empty($_SESSION['id'])) {
            return ['error' => 'Error: No se pudo obtener el ID del usuario de la sesión'];
        }

        try {
            $idUsuario = (int)$_SESSION['id'];
            $resultado = $this->service->procesarAccionAutorizacion($idVenta, $accion, $datos, $idUsuario);

            if ($resultado['success']) {
                header("Location: " . $this->urlBase . "secciones/contable/index.php?mensaje=" . urlencode($resultado['mensaje']));
                exit();
            } else {
                return [
                    'error' => $resultado['error'] ?? 'Error desconocido',
                    'errores' => $resultado['errores'] ?? []
                ];
            }
        } catch (Exception $e) {
            error_log("Error procesando acción de autorización: " . $e->getMessage());
            return ['error' => 'Error interno del servidor'];
        }
    }

    public function obtenerEstadisticas()
    {
        try {
            return $this->service->obtenerEstadisticas();
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [
                'pendientes' => 0,
                'devoluciones' => 0,
                'aprobadas' => 0,
                'rechazadas' => 0
            ];
        }
    }

    public function obtenerHistorialAcciones($filtros = [], $pagina = 1)
    {
        try {
            return $this->service->obtenerHistorialAcciones($filtros, $pagina);
        } catch (Exception $e) {
            error_log("Error obteniendo historial de acciones: " . $e->getMessage());
            return [
                'historial' => [],
                'total' => 0,
                'total_paginas' => 0,
                'pagina_actual' => $pagina
            ];
        }
    }

    public function obtenerDatosVista($titulo = 'Gestión Contable')
    {
        try {
            return [
                'titulo' => $titulo,
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => $this->esAdministrador(),
                'estadisticas' => $this->obtenerEstadisticas()
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => $titulo,
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => false,
                'estadisticas' => []
            ];
        }
    }

    public function procesarFiltros()
    {
        $filtros = [];

        if (isset($_GET['id_venta']) && !empty(trim($_GET['id_venta']))) {
            $filtros['id_venta'] = trim($_GET['id_venta']);
        }

        if (isset($_GET['cliente'])) {
            $filtros['cliente'] = trim($_GET['cliente']);
        }

        if (isset($_GET['vendedor'])) {
            $filtros['vendedor'] = trim($_GET['vendedor']);
        }

        if (isset($_GET['usuario_pcp'])) {
            $filtros['usuario_pcp'] = trim($_GET['usuario_pcp']);
        }

        if (isset($_GET['fecha_desde'])) {
            $filtros['fecha_desde'] = trim($_GET['fecha_desde']);
        }

        if (isset($_GET['fecha_hasta'])) {
            $filtros['fecha_hasta'] = trim($_GET['fecha_hasta']);
        }

        if (isset($_GET['cliente_historial'])) {
            $filtros['cliente_historial'] = trim($_GET['cliente_historial']);
        }

        if (isset($_GET['estado_resultante'])) {
            $filtros['estado_resultante'] = trim($_GET['estado_resultante']);
        }

        return $filtros;
    }

    public function verificarPermisos()
    {
        return $this->service->validarPermisos($_SESSION['rol'] ?? '0');
    }


    private function esAdministrador()
    {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === '1';
    }

    public function validarParametros($parametros)
    {
        $errores = [];

        if (isset($parametros['id'])) {
            $id = (int)$parametros['id'];
            if ($id < 1) {
                $errores[] = 'ID de venta inválido';
            }
        }

        return $errores;
    }


    public function logActividad($accion, $detalles = null)
    {
        $this->service->logActividad($accion, $detalles, $_SESSION);
    }


    public function obtenerConfiguracionJS()
    {
        return [
            'urlBase' => $this->urlBase,
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

    public function validarIdVenta($id)
    {
        if (!isset($id) || empty($id) || !is_numeric($id) || $id <= 0) {
            return false;
        }
        return true;
    }


    public function procesarRechazoVentaDevuelta($idVenta, $datos)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['error' => 'Método no permitido'];
        }

        if (!isset($_SESSION['id']) || empty($_SESSION['id'])) {
            return ['error' => 'Error: No se pudo obtener el ID del usuario de la sesión'];
        }

        try {
            $idUsuario = (int)$_SESSION['id'];
            $resultado = $this->service->procesarRechazoVentaDevuelta($idVenta, $datos, $idUsuario);

            return $resultado;
        } catch (Exception $e) {
            error_log("Error procesando rechazo de venta devuelta: " . $e->getMessage());
            return ['error' => 'Error interno del servidor'];
        }
    }

    public function obtenerDevolucionesPCP($filtros = [], $pagina = 1)
    {
        try {
            return $this->service->obtenerDevolucionesPCP($filtros, $pagina);
        } catch (Exception $e) {
            error_log("Error obteniendo devoluciones PCP: " . $e->getMessage());
            return [
                'devoluciones' => [],
                'total' => 0,
                'total_paginas' => 0,
                'pagina_actual' => $pagina
            ];
        }
    }

    public function procesarDatosFormulario()
    {
        $datos = [];

        if (isset($_POST['accion'])) {
            $accion = $_POST['accion'];

            if ($accion === 'rechazar') {
                $datos['descripcion_rechazo'] = trim($_POST['descripcion_rechazo'] ?? '');
            } elseif ($accion === 'enviar_pcp') {
                $datos['observaciones_pcp'] = trim($_POST['observaciones_pcp'] ?? '');
            }
        }

        return $datos;
    }

    public function obtenerVentaCompleta($idVenta)
    {
        try {
            return $this->service->obtenerVentaCompleta($idVenta);
        } catch (Exception $e) {
            error_log("Error obteniendo venta completa: " . $e->getMessage());
            throw new Exception('Venta no encontrada');
        }
    }

    public function generarUrlConParametros($archivo, $parametros = [])
    {
        $url = $this->urlBase . "secciones/contable/" . $archivo;

        if (!empty($parametros)) {
            $url .= "?" . http_build_query($parametros);
        }

        return $url;
    }


    public function formatearMoneda($monto, $moneda)
    {
        if ($moneda === 'Dólares') {
            $simbolo = 'U$D ';
        } elseif ($moneda === 'Real brasileño') {
            $simbolo = 'R$ ';
        } else {
            $simbolo = '₲ ';
        }

        return $simbolo . number_format((float)$monto, 2, ',', '.');
    }
}

if (!file_exists('repository/ContableRepository.php') || !file_exists('services/ContableService.php')) {
    die("Error: Faltan archivos del sistema MVC. Verifique que existan los archivos repository/ContableRepository.php y services/ContableService.php");
}
