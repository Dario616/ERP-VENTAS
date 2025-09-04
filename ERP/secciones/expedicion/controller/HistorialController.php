<?php
require_once 'repository/historialRepository.php';
require_once 'services/historialService.php';

date_default_timezone_set('America/Asuncion');

class HistorialController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new HistorialRepository($conexion);
        $this->service = new HistorialService($this->repository);
        $this->urlBase = $urlBase;
    }

    public function handleRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['accion'])) {
            return false;
        }

        header('Content-Type: application/json');
        $usuario = $_SESSION['nombre'] ?? 'Sistema';

        try {
            switch ($_POST['accion']) {
                case 'obtener_detalle_asignacion':
                    $this->obtenerDetalleAsignacion();
                    break;

                case 'exportar_historial':
                    $this->exportarHistorial();
                    break;

                case 'obtener_estadisticas_periodo':
                    $this->obtenerEstadisticasPeriodo();
                    break;

                default:
                    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            }
        } catch (Exception $e) {
            error_log("Error en handleRequest (historial): " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
        }

        return true;
    }

    public function obtenerDatosVistaHistorial($filtros = [], $pagina = 1, $porPagina = 20)
    {
        try {
            $totalRegistros = $this->repository->contarTotalHistorial($filtros);
            $totalPaginas = ceil($totalRegistros / $porPagina);

            if ($pagina > $totalPaginas && $totalPaginas > 0) {
                $pagina = $totalPaginas;
            }
            $historialAsignaciones = $this->repository->obtenerHistorialAsignaciones($filtros, $pagina, $porPagina);
            $historialAsignaciones = $this->service->enriquecerHistorialAsignaciones($historialAsignaciones);
            $estadisticasGenerales = $this->repository->obtenerEstadisticasGenerales($filtros);
            $clientesDisponibles = $this->repository->obtenerClientesConHistorial();
            $rejillasDisponibles = $this->repository->obtenerRejillasConHistorial();
            $usuariosDisponibles = $this->repository->obtenerUsuariosConHistorial();

            return [
                'historial_asignaciones' => $historialAsignaciones,
                'estadisticas_generales' => $estadisticasGenerales,
                'clientes_disponibles' => $clientesDisponibles,
                'rejillas_disponibles' => $rejillasDisponibles,
                'usuarios_disponibles' => $usuariosDisponibles,
                'total_registros' => $totalRegistros,
                'total_paginas' => $totalPaginas,
                'pagina_actual' => $pagina,
                'resultados_por_pagina' => $porPagina,
                'filtros_aplicados' => $filtros
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos vista historial: " . $e->getMessage());
            return [
                'historial_asignaciones' => [],
                'estadisticas_generales' => [],
                'clientes_disponibles' => [],
                'rejillas_disponibles' => [],
                'usuarios_disponibles' => [],
                'total_registros' => 0,
                'total_paginas' => 0,
                'pagina_actual' => 1,
                'resultados_por_pagina' => $porPagina,
                'filtros_aplicados' => $filtros
            ];
        }
    }
    private function obtenerDetalleAsignacion()
    {
        try {
            $idAsignacion = (int)($_POST['id_asignacion'] ?? 0);

            if ($idAsignacion === 0) {
                throw new Exception('ID de asignación inválido');
            }

            $detalle = $this->repository->obtenerDetalleCompletoAsignacion($idAsignacion);
            $historialEstados = $this->repository->obtenerHistorialEstadosAsignacion($idAsignacion);

            if (!$detalle) {
                throw new Exception('Asignación no encontrada');
            }
            $detalle = $this->service->enriquecerDetalleAsignacion($detalle);
            echo json_encode([
                'success' => true,
                'detalle' => $detalle,
                'historial_estados' => $historialEstados
            ]);
        } catch (Exception $e) {
            error_log("Error obteniendo detalle de asignación: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    private function obtenerEstadisticasPeriodo()
    {
        try {
            $fechaInicio = $_POST['fecha_inicio'] ?? null;
            $fechaFin = $_POST['fecha_fin'] ?? null;

            if (!$fechaInicio || !$fechaFin) {
                throw new Exception('Fechas de inicio y fin requeridas');
            }

            $estadisticas = $this->repository->obtenerEstadisticasPorPeriodo($fechaInicio, $fechaFin);
            $estadisticas = $this->service->procesarEstadisticasPeriodo($estadisticas);

            echo json_encode([
                'success' => true,
                'estadisticas' => $estadisticas,
                'periodo' => [
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin
                ]
            ]);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas por período: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    private function exportarHistorial()
    {
        try {
            $filtros = [
                'fecha_inicio' => $_POST['fecha_inicio'] ?? null,
                'fecha_fin' => $_POST['fecha_fin'] ?? null,
                'cliente' => $_POST['cliente'] ?? null,
                'estado' => $_POST['estado'] ?? null,
                'rejilla' => $_POST['rejilla'] ?? null
            ];
            $historialCompleto = $this->repository->obtenerHistorialAsignaciones($filtros, 1, 10000);
            $historialCompleto = $this->service->prepararDatosParaExportacion($historialCompleto);
            echo json_encode([
                'success' => true,
                'datos' => $historialCompleto,
                'total_registros' => count($historialCompleto),
                'filtros_aplicados' => array_filter($filtros)
            ]);
        } catch (Exception $e) {
            error_log("Error preparando exportación: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function obtenerConfiguracion()
    {
        return [
            'items_por_pagina' => 20,
            'formato_fecha' => 'd/m/Y H:i',
            'estados_disponibles' => ['activa', 'completada', 'cancelada'],
            'tipos_exportacion' => ['excel', 'csv', 'pdf'],
            'campos_exportables' => [
                'fecha_asignacion',
                'cliente',
                'producto',
                'rejilla',
                'cantidad_unidades',
                'peso_asignado',
                'estado_asignacion',
                'usuario_asignacion'
            ],
            'version_historial' => '1.0'
        ];
    }
    public function validarPermisos($accion = null)
    {
        $rolesPermitidos = ['1', '3'];
        $rolUsuario = $_SESSION['rol'] ?? '';

        if (!in_array($rolUsuario, $rolesPermitidos)) {
            return false;
        }

        return true;
    }

    public function logActividad($accion, $contexto = null, $usuario = null, $detalles = null)
    {
        $usuario = $usuario ?? $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "HISTORIAL-EXPEDICION-v1.0 - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($contexto) {
            $mensaje .= " | Contexto: {$contexto}";
        }

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);
    }

    public function getUrlBase()
    {
        return $this->urlBase;
    }
}

if (!file_exists('repository/historialRepository.php') || !file_exists('services/historialService.php')) {
    die("Error: Faltan archivos del sistema MVC. Verifique que existan los archivos repository/historialRepository.php y services/historialService.php");
}

$historialController = new HistorialController($conexion, $url_base);
if (!$historialController->validarPermisos()) {
    header('Location: ' . $url_base . 'index.php');
    exit;
}
if ($historialController->handleRequest()) {
    exit();
}
