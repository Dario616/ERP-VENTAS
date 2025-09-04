<?php
require_once 'repository/pcpRepository.php';
require_once 'services/pcpService.php';

date_default_timezone_set('America/Asuncion');

class PcpController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new PcpRepository($conexion);
        $this->service = new PcpService($this->repository);
        $this->urlBase = $urlBase;
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
            case 'procesar_venta':
                $this->procesarVentaApi();
                break;
            case 'devolver_venta':
                $this->devolverVentaApi();
                break;
            case 'obtener_resumen_stock_agregado':
                $this->obtenerResumenStockAgregadoApi();
                break;
            case 'cancelar_reservas':
                $this->cancelarReservasApi();
                break;
            case 'reasignar_venta':
                $this->reasignarVentaApi();
                break;

            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

        return true;
    }

    private function obtenerEstadisticasApi()
    {
        try {
            $estadisticas = $this->service->obtenerEstadisticasDashboard();
            echo json_encode([
                'success' => true,
                'estadisticas' => $estadisticas
            ]);
        } catch (Exception $e) {
            error_log("Error en API estadísticas PCP: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function procesarVentaApi()
    {
        $idVenta = $_POST['id_venta'] ?? null;
        $observaciones = $_POST['observaciones'] ?? '';

        if (!$idVenta) {
            echo json_encode([
                'success' => false,
                'error' => 'ID de venta no proporcionado'
            ]);
            return;
        }

        try {
            $resultado = $this->service->procesarVenta($idVenta, $observaciones, $_SESSION['id']);
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en API procesar venta: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function devolverVentaApi()
    {
        $idVenta = $_POST['id_venta'] ?? null;
        $motivo = $_POST['motivo_devolucion'] ?? '';

        if (!$idVenta || !$motivo) {
            echo json_encode([
                'success' => false,
                'error' => 'Datos incompletos'
            ]);
            return;
        }

        try {
            $resultado = $this->service->devolverVentaContabilidad($idVenta, $motivo, $_SESSION['id']);
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en API devolver venta: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerResumenStockAgregadoApi()
    {
        try {
            $filtros = [];
            if (!empty($_GET['producto'])) {
                $filtros['nombre_producto'] = $_GET['producto'];
            }

            $resumen = $this->repository->obtenerResumenStockAgregado($filtros);

            echo json_encode([
                'success' => true,
                'stock_agregado' => $resumen,
                'total_productos' => count($resumen)
            ]);
        } catch (Exception $e) {
            error_log("Error en API resumen stock agregado: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error obteniendo resumen de stock agregado'
            ]);
        }
    }

    private function cancelarReservasApi()
    {
        $idVenta = $_POST['id_venta'] ?? null;

        if (!$idVenta) {
            echo json_encode([
                'success' => false,
                'error' => 'ID de venta no proporcionado'
            ]);
            return;
        }

        try {
            $resultado = $this->service->cancelarReservasVenta($idVenta, $_SESSION['id']);
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en API cancelar reservas: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    public function obtenerVentasAprobadas($filtros = [], $pagina = 1, $registrosPorPagina = 10)
    {
        try {
            return $this->service->obtenerVentasAprobadas($filtros, $pagina, $registrosPorPagina);
        } catch (Exception $e) {
            error_log("Error obteniendo ventas aprobadas: " . $e->getMessage());
            return [
                'ventas' => [],
                'total_registros' => 0,
                'total_paginas' => 0,
                'pagina_actual' => $pagina
            ];
        }
    }

    public function obtenerVentaParaProcesamiento($idVenta)
    {
        try {
            return $this->service->obtenerVentaParaProcesamiento($idVenta);
        } catch (Exception $e) {
            error_log("Error obteniendo venta para procesamiento: " . $e->getMessage());
            throw new Exception('Venta no encontrada: ' . $e->getMessage());
        }
    }

    public function procesarFormularioVenta($idVenta, $datos)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['error' => 'Método no permitido'];
        }

        $accion = $datos['accion'] ?? '';

        try {
            switch ($accion) {
                case 'procesar':
                    $observaciones = $datos['observaciones_pcp'] ?? '';
                    $resultado = $this->service->procesarVenta($idVenta, $observaciones, $_SESSION['id']);
                    break;
                case 'devolver':
                    $motivo = $datos['motivo_devolucion'] ?? '';
                    if (empty(trim($motivo))) {
                        return ['error' => 'El motivo de devolución es obligatorio'];
                    }
                    $resultado = $this->service->devolverVentaContabilidad($idVenta, $motivo, $_SESSION['id']);
                    break;
                case 'enviar_produccion':
                    $resultado = $this->procesarEnvioProduccion($idVenta, $datos);
                    break;
                case 'enviar_expedicion_stock':
                    $resultado = $this->procesarEnvioExpedicionStock($idVenta, $datos);
                    break;
                default:
                    return ['error' => 'Acción no válida'];
            }

            if ($resultado['success']) {
                header("Location: " . $this->urlBase . "secciones/sectorPcp/index.php?mensaje=" . urlencode($resultado['mensaje']));
                exit();
            } else {
                return ['error' => $resultado['error']];
            }
        } catch (Exception $e) {
            error_log("Error procesando formulario venta: " . $e->getMessage());
            return ['error' => 'Error interno del servidor'];
        }
    }

    private function procesarEnvioProduccion($idVenta, $datos)
    {
        try {
            $cantidadesProduccion = $datos['cantidad_produccion'] ?? [];
            $observaciones = $datos['observaciones_produccion'] ?? '';

            return $this->service->enviarProductosProduccion($idVenta, $cantidadesProduccion, $observaciones, $_SESSION['id']);
        } catch (Exception $e) {
            error_log("Error procesando envío a producción: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al enviar productos a producción'];
        }
    }

    private function procesarEnvioExpedicionStock($idVenta, $datos)
    {
        try {
            $esFormularioNuevo = isset($datos['variantes']) && !empty($datos['variantes']);

            if ($esFormularioNuevo) {
                return $this->procesarEnvioExpedicionVariantes($idVenta, $datos);
            } else {
                $cantidadesStock = $datos['cantidad_stock'] ?? [];
                $observaciones = $datos['observaciones_expedicion'] ?? '';
                return $this->service->enviarStockExpedicion($idVenta, $cantidadesStock, $observaciones, $_SESSION['id']);
            }
        } catch (Exception $e) {
            error_log("Error procesando envío de stock: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al enviar stock a expedición'];
        }
    }

    private function procesarEnvioExpedicionVariantes($idVenta, $datos)
    {
        try {
            $variantes = $datos['variantes'] ?? [];
            $cantidadesStock = $datos['cantidad_stock'] ?? [];
            $observaciones = $datos['observaciones_expedicion'] ?? '';

            if (empty($variantes)) {
                return ['success' => false, 'error' => 'No se seleccionaron variantes'];
            }

            $cantidadesStockProcesadas = [];

            foreach ($variantes as $varianteId => $datosVariante) {
                $nombreProducto = $datosVariante['nombre_producto'] ?? '';
                $bobinasPacote = (int)($datosVariante['bobinas_pacote'] ?? 1);
                $tipoProducto = $datosVariante['tipo_producto'] ?? '';
                $cantidad = (int)($cantidadesStock[$nombreProducto] ?? 0);

                if ($cantidad > 0 && !empty($nombreProducto)) {
                    $claveUnica = $nombreProducto . '_' . $bobinasPacote . 'bob';
                    $cantidadesStockProcesadas[$claveUnica] = [
                        'nombre_producto' => $nombreProducto,
                        'cantidad' => $cantidad,
                        'bobinas_pacote' => $bobinasPacote,
                        'tipo_producto' => $tipoProducto
                    ];
                }
            }

            if (empty($cantidadesStockProcesadas)) {
                return ['success' => false, 'error' => 'No se especificaron cantidades válidas'];
            }

            $observacionesCompletas = "Reservas específicas: " . count($cantidadesStockProcesadas) . " variantes. " . $observaciones;

            return $this->service->enviarStockExpedicionVariantes($idVenta, $cantidadesStockProcesadas, $observacionesCompletas, $_SESSION['id']);
        } catch (Exception $e) {
            error_log("Error procesando variantes: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al procesar variantes'];
        }
    }

    public function obtenerDevolucionesPcp($filtros = [], $pagina = 1, $registrosPorPagina = 10)
    {
        try {
            return $this->service->obtenerDevolucionesPcp($filtros, $pagina, $registrosPorPagina);
        } catch (Exception $e) {
            error_log("Error obteniendo devoluciones PCP: " . $e->getMessage());
            return [
                'devoluciones' => [],
                'total_registros' => 0,
                'total_paginas' => 0,
                'pagina_actual' => $pagina
            ];
        }
    }

    public function obtenerHistorialAcciones($filtros = [], $pagina = 1, $registrosPorPagina = 10)
    {
        try {
            return $this->service->obtenerHistorialAcciones($filtros, $pagina, $registrosPorPagina);
        } catch (Exception $e) {
            error_log("Error obteniendo historial de acciones: " . $e->getMessage());
            return [
                'historial' => [],
                'total_registros' => 0,
                'total_paginas' => 0,
                'pagina_actual' => $pagina
            ];
        }
    }

    public function obtenerDatosVista($pagina = 'dashboard')
    {
        try {
            $datos = [
                'titulo' => 'Gestión PCP',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => $this->esAdministrador(),
                'pagina' => $pagina
            ];

            switch ($pagina) {
                case 'dashboard':
                    $datos['estadisticas'] = $this->service->obtenerEstadisticasDashboard();
                    break;
                case 'ventas_aprobadas':
                    $datos['titulo'] = 'Ventas Aprobadas - PCP';
                    break;
                case 'devoluciones':
                    $datos['titulo'] = 'Devoluciones a PCP';
                    break;
                case 'historial':
                    $datos['titulo'] = 'Historial de Acciones PCP';
                    break;
                case 'procesar_venta':
                    $datos['titulo'] = 'Procesar Venta - PCP';
                    break;
            }

            return $datos;
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => 'Gestión PCP',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => false,
                'estadisticas' => [],
                'pagina' => $pagina
            ];
        }
    }

    public function procesarFiltros($tipoFiltro = 'ventas')
    {
        $filtros = [];

        switch ($tipoFiltro) {
            case 'ventas':
                $filtros = [
                    'id_venta' => trim($_GET['id_venta'] ?? ''),
                    'cliente' => trim($_GET['cliente'] ?? ''),
                    'vendedor' => trim($_GET['vendedor'] ?? ''),
                    'contador' => trim($_GET['contador'] ?? ''),
                    'pagina' => max(1, (int)($_GET['pagina'] ?? 1))
                ];
                break;
            case 'devoluciones':
                $filtros = [
                    'id_venta' => trim($_GET['id_venta'] ?? ''),
                    'cliente' => trim($_GET['cliente'] ?? ''),
                    'producto' => trim($_GET['producto'] ?? ''),
                    'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
                    'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
                    'pagina' => max(1, (int)($_GET['pagina'] ?? 1))
                ];
                break;
            case 'historial':
                $filtros = [
                    'id_venta' => trim($_GET['id_venta'] ?? ''),
                    'cliente' => trim($_GET['cliente'] ?? ''),
                    'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
                    'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
                    'pagina' => max(1, (int)($_GET['pagina'] ?? 1))
                ];
                break;
        }

        return $filtros;
    }

    public function verificarPermisos($accion, $idVenta = null)
    {
        $esAdmin = $this->esAdministrador();
        $esPcp = $this->esPcp();

        switch ($accion) {
            case 'ver':
            case 'listar':
            case 'procesar':
            case 'devolver':
            case 'enviar_produccion':
            case 'enviar_expedicion':
                return $esAdmin || $esPcp;
            default:
                return false;
        }
    }

    private function esAdministrador()
    {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === '1';
    }

    private function esPcp()
    {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === '4';
    }

    public function logActividad($accion, $detalles = null)
    {
        $usuario = $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "PCP - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);
    }

    public function obtenerConfiguracionJS()
    {
        return [
            'urlBase' => $this->urlBase,
            'usuario' => $_SESSION['nombre'] ?? 'Usuario',
            'esAdmin' => $this->esAdministrador(),
            'esPcp' => $this->esPcp(),
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

    private function reasignarVentaApi()
    {
        $idVenta = $_POST['id_venta'] ?? null;
        $idProducto = $_POST['id_producto'] ?? null; // ← NUEVO: recibir id_producto

        if (!$idVenta) {
            echo json_encode(['success' => false, 'error' => 'ID de venta no proporcionado']);
            return;
        }

        try {
            // Pasar el id_producto al service (puede ser null para venta completa)
            $resultado = $this->service->reasignarVentaAprobado($idVenta, $_SESSION['id'], $idProducto);
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error reasignando: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
        }
    }

    public function formatearMoneda($monto, $moneda)
    {
        $simbolo = $this->service->obtenerSimboloMoneda($moneda);
        $numeroFormateado = $this->service->formatearNumero($monto);

        return $simbolo . ' ' . $numeroFormateado;
    }
}

if (!file_exists('repository/pcpRepository.php') || !file_exists('services/pcpService.php')) {
    die("Error: Faltan archivos del sistema MVC. Verifique que existan los archivos repository/pcpRepository.php y services/pcpService.php");
}
