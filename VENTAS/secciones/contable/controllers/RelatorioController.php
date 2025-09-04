<?php
require_once 'repository/RelatorioRepository.php';
require_once 'services/RelatorioService.php';

date_default_timezone_set('America/Asuncion');

class RelatorioController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new RelatorioRepository($conexion);
        $this->service = new RelatorioService($this->repository);
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
            case 'obtener_reporte_pagos':
                $this->obtenerReportePagosApi();
                break;
            case 'obtener_estadisticas_generales':
                $this->obtenerEstadisticasGeneralesApi();
                break;
            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

        return true;
    }
    private function obtenerReportePagosApi()
    {
        try {
            $filtros = $this->procesarFiltros();
            $reporte = $this->service->obtenerReportePagos($filtros);

            echo json_encode([
                'success' => true,
                'reporte' => $reporte
            ]);
        } catch (Exception $e) {
            error_log("Error en API reporte pagos: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }


    private function obtenerEstadisticasGeneralesApi()
    {
        try {
            $filtros = $this->procesarFiltros();
            $estadisticas = $this->service->obtenerEstadisticasGenerales($filtros);

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

    public function obtenerReportePagos($filtros = [])
    {
        try {
            return $this->service->obtenerReportePagos($filtros);
        } catch (Exception $e) {
            error_log("Error obteniendo reporte de pagos: " . $e->getMessage());
            return [
                'clientes' => []
            ];
        }
    }

    public function obtenerEstadisticasGenerales($filtros = [])
    {
        try {
            return $this->service->obtenerEstadisticasGenerales($filtros);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas generales: " . $e->getMessage());
            return [
                'total_clientes' => 0,
                'total_pagos' => 0,
                'montos_por_moneda' => []
            ];
        }
    }

    public function procesarFiltros()
    {
        $filtros = [];

        if (isset($_GET['fecha_desde']) && !empty(trim($_GET['fecha_desde']))) {
            $filtros['fecha_desde'] = trim($_GET['fecha_desde']);
        }

        if (isset($_GET['fecha_hasta']) && !empty(trim($_GET['fecha_hasta']))) {
            $filtros['fecha_hasta'] = trim($_GET['fecha_hasta']);
        }

        if (isset($_GET['cliente']) && !empty(trim($_GET['cliente']))) {
            $filtros['cliente'] = trim($_GET['cliente']);
        }

        if (isset($_GET['moneda']) && !empty(trim($_GET['moneda']))) {
            $filtros['moneda'] = trim($_GET['moneda']);
        }

        if (isset($_GET['forma_pago']) && !empty(trim($_GET['forma_pago']))) {
            $filtros['forma_pago'] = trim($_GET['forma_pago']);
        }

        if (isset($_GET['vendedor']) && !empty(trim($_GET['vendedor']))) {
            $filtros['vendedor'] = trim($_GET['vendedor']);
        }

        return $filtros;
    }

    public function verificarPermisos()
    {
        return $this->service->validarPermisos($_SESSION['rol'] ?? '0');
    }


    public function obtenerDatosVista($titulo = 'Reporte de Pagos por Cliente')
    {
        try {
            return [
                'titulo' => $titulo,
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => $this->esAdministrador(),
                'config_fechas' => [
                    'fecha_actual' => date('Y-m-d'),
                    'fecha_minima' => date('Y-m-d', strtotime('-2 years')),
                    'fecha_defecto_desde' => date('Y-m-01'),
                    'fecha_defecto_hasta' => date('Y-m-d'),
                    'formato_fecha' => 'dd/mm/yyyy'
                ]
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => $titulo,
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => false,
                'config_fechas' => [
                    'fecha_actual' => date('Y-m-d'),
                    'fecha_minima' => date('Y-m-d', strtotime('-2 years')),
                    'fecha_defecto_desde' => date('Y-m-01'),
                    'fecha_defecto_hasta' => date('Y-m-d'),
                    'formato_fecha' => 'dd/mm/yyyy'
                ]
            ];
        }
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

    public function obtenerConfiguracionJS()
    {
        return [
            'urlBase' => $this->urlBase,
            'usuario' => $_SESSION['nombre'] ?? 'Usuario',
            'esAdmin' => $this->esAdministrador(),
            'debug' => isset($_GET['debug']),
            'rol' => $_SESSION['rol'] ?? '0',
            'fechas' => [
                'formatoJS' => 'dd/mm/yyyy',
                'fechaActual' => date('Y-m-d'),
                'fechaMinima' => date('Y-m-d', strtotime('-2 years')),
                'idioma' => 'es-PY'
            ],
            'endpoints' => [
                'obtenerReporte' => $this->urlBase . 'secciones/contable/relatorio_pagos.php?action=obtener_reporte_pagos',
                'estadisticasGenerales' => $this->urlBase . 'secciones/contable/relatorio_pagos.php?action=obtener_estadisticas_generales'
            ]
        ];
    }

    public function obtenerListaClientes()
    {
        try {
            return $this->repository->obtenerListaClientes();
        } catch (Exception $e) {
            error_log("Error obteniendo lista de clientes: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerListaVendedores()
    {
        try {
            return $this->repository->obtenerListaVendedores();
        } catch (Exception $e) {
            error_log("Error obteniendo lista de vendedores: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerFormasPago()
    {
        try {
            return $this->repository->obtenerFormasPago();
        } catch (Exception $e) {
            error_log("Error obteniendo formas de pago: " . $e->getMessage());
            return [];
        }
    }

    public function logActividad($accion, $detalles = null)
    {
        $usuario = $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "RELATORIO_PAGOS - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);
    }

    private function esAdministrador()
    {
        return isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['1', '3']);
    }

    public function obtenerSimboloMoneda($moneda)
    {
        switch ($moneda) {
            case 'Dólares':
                return 'U$D ';
            case 'Real brasileño':
                return 'R$ ';
            default:
                return '₲ ';
        }
    }

    public function obtenerMesesDisponibles()
    {
        $meses = [];
        for ($i = 11; $i >= 0; $i--) {
            $fecha = date('Y-m', strtotime("-$i months"));
            $nombreMes = $this->obtenerNombreMes(date('n', strtotime($fecha . '-01')));
            $año = date('Y', strtotime($fecha . '-01'));

            $meses[] = [
                'valor' => $fecha,
                'texto' => $nombreMes . ' ' . $año,
                'desde' => $fecha . '-01',
                'hasta' => date('Y-m-t', strtotime($fecha . '-01'))
            ];
        }
        return $meses;
    }

    private function obtenerNombreMes($numeroMes)
    {
        $meses = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        ];
        return $meses[$numeroMes] ?? 'Mes';
    }

    public function manejarVistaDetallada()
    {
        if (!isset($_GET['cliente']) || empty(trim($_GET['cliente']))) {
            header("Location: " . $this->urlBase . "secciones/contable/relatorio_pagos.php?error=Cliente no especificado");
            exit;
        }

        $cliente = trim($_GET['cliente']);
        $moneda = isset($_GET['moneda']) ? trim($_GET['moneda']) : null;

        try {
            $filtros = $this->procesarFiltros();
            $filtros['cliente_exacto'] = $cliente;

            if ($moneda) {
                $filtros['moneda'] = $moneda;
            }

            $datosDetallados = $this->service->obtenerDatosDetalladosCliente($cliente, $filtros);

            return [
                'cliente' => $datosDetallados,
                'filtros_aplicados' => $filtros,
                'datos_vista' => $this->obtenerDatosVista('Detalles de Pagos - ' . $cliente)
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo vista detallada: " . $e->getMessage());
            header("Location: " . $this->urlBase . "secciones/contable/relatorio_pagos.php?error=Error al cargar los detalles");
            exit;
        }
    }

    public function obtenerDatosCumplimientoFechas($filtros = [])
    {
        try {
            return $this->repository->obtenerDatosCumplimientoFechas($filtros);
        } catch (Exception $e) {
            error_log("Error obteniendo datos de cumplimiento desde controller: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerDatosClientesConPuntajeReal($filtros = [])
    {
        try {
            return $this->repository->obtenerDatosClientesConPuntajeReal($filtros);
        } catch (Exception $e) {
            error_log("Error obteniendo clientes con puntaje real desde controller: " . $e->getMessage());
            return [];
        }
    }


    public function obtenerEstadisticasAtrasoReales($filtros = [])
    {
        try {
            return $this->repository->obtenerEstadisticasAtrasoReales($filtros);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas de atraso desde controller: " . $e->getMessage());
            return [
                'al_dia' => 0,
                'atraso_1_7' => 0,
                'atraso_8_15' => 0,
                'atraso_16_30' => 0,
                'atraso_mas_30' => 0
            ];
        }
    }
}
