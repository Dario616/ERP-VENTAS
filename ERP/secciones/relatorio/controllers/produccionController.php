<?php
require_once 'repository/produccionRepository.php';
require_once 'services/produccionService.php';

// Establecer la zona horaria de Paraguay/Asunción
date_default_timezone_set('America/Asuncion');

/**
 * Controller para manejo de reportes de producción con filtros de horario
 */
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

    /**
     * Maneja las peticiones API
     */
    public function handleApiRequest()
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        // Limpiar buffer de salida para asegurar JSON puro
        if (ob_get_level()) {
            ob_clean();
        }

        // Cabeceras para API JSON
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        try {
            switch ($_GET['action']) {
                case 'obtener_datos_dashboard':
                    $this->obtenerDatosDashboardApi();
                    break;

                case 'obtener_evolucion_produccion':
                    $this->obtenerEvolucionProduccionApi();
                    break;

                case 'obtener_top_productos':
                    $this->obtenerTopProductosApi();
                    break;

                case 'obtener_estadisticas_generales':
                    $this->obtenerEstadisticasGeneralesApi();
                    break;

                case 'exportar_reporte':
                    $this->exportarReporteApi();
                    break;

                case 'buscar_operadores':
                    $this->buscarOperadoresApi();
                    break;

                case 'obtener_datos_graficos':
                    $this->obtenerDatosGraficosApi();
                    break;

                case 'obtener_productos_paginados':
                    $this->obtenerProductosPaginadosApi();
                    break;

                default:
                    $this->enviarErrorJson('Acción no válida', 400);
            }
        } catch (Exception $e) {
            error_log("Error en API: " . $e->getMessage());
            $this->enviarErrorJson('Error interno del servidor', 500);
        }

        return true;
    }

    /**
     * API: Obtener productos paginados
     */
    private function obtenerProductosPaginadosApi()
    {
        $filtros = $this->procesarFiltrosApi();
        $pagina = (int)($_GET['pagina'] ?? 1);

        try {
            $productos = $this->service->obtenerProductosPaginados($filtros, $pagina);

            $this->enviarJsonExitoso([
                'productos' => $productos['productos'],
                'paginacion' => $productos['paginacion']
            ]);
        } catch (Exception $e) {
            error_log("Error en API productos paginados: " . $e->getMessage());
            $this->enviarErrorJson('Error al obtener productos paginados');
        }
    }

    /**
     * API: Obtener datos para gráficos
     */
    private function obtenerDatosGraficosApi()
    {
        $filtros = $this->procesarFiltrosApi();

        try {
            $datos = $this->service->obtenerDatosGraficos($filtros);

            $this->enviarJsonExitoso([
                'datos' => $datos
            ]);
        } catch (Exception $e) {
            error_log("Error en API datos gráficos: " . $e->getMessage());
            $this->enviarErrorJson('Error al obtener datos de gráficos');
        }
    }

    /**
     * API: Obtener todos los datos del dashboard
     */
    private function obtenerDatosDashboardApi()
    {
        $filtros = $this->procesarFiltrosApi();

        try {
            $datos = $this->service->obtenerDatosDashboard($filtros);

            $this->enviarJsonExitoso([
                'datos' => $datos,
                'filtros_aplicados' => $filtros,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error en API datos dashboard: " . $e->getMessage());
            $this->enviarErrorJson('Error al obtener datos del dashboard');
        }
    }

    /**
     * API: Obtener evolución de producción por período
     */
    private function obtenerEvolucionProduccionApi()
    {
        $filtros = $this->procesarFiltrosApi();

        try {
            $evolucion = $this->service->obtenerEvolucionProduccion($filtros);

            $this->enviarJsonExitoso([
                'evolucion' => $evolucion,
                'filtros_aplicados' => $filtros
            ]);
        } catch (Exception $e) {
            error_log("Error en API evolución producción: " . $e->getMessage());
            $this->enviarErrorJson('Error al obtener evolución de producción');
        }
    }

    /**
     * API: Obtener top 5 productos más producidos
     */
    private function obtenerTopProductosApi()
    {
        $filtros = $this->procesarFiltrosApi();

        try {
            $topProductos = $this->service->obtenerTopProductos($filtros, 5);

            $this->enviarJsonExitoso([
                'productos' => $topProductos,
                'filtros_aplicados' => $filtros
            ]);
        } catch (Exception $e) {
            error_log("Error en API top productos: " . $e->getMessage());
            $this->enviarErrorJson('Error al obtener top productos');
        }
    }

    /**
     * API: Obtener estadísticas generales
     */
    private function obtenerEstadisticasGeneralesApi()
    {
        $filtros = $this->procesarFiltrosApi();

        try {
            $estadisticas = $this->service->obtenerEstadisticasGenerales($filtros);

            $this->enviarJsonExitoso([
                'estadisticas' => $estadisticas,
                'filtros_aplicados' => $filtros
            ]);
        } catch (Exception $e) {
            error_log("Error en API estadísticas generales: " . $e->getMessage());
            $this->enviarErrorJson('Error al obtener estadísticas generales');
        }
    }

    /**
     * API: Exportar reporte a PDF/Excel
     */
    private function exportarReporteApi()
    {
        $formato = $_GET['formato'] ?? 'csv';
        $filtros = $this->procesarFiltrosApi();

        try {
            if ($formato === 'csv') {
                $this->exportarCSV($filtros);
            } else {
                $this->enviarErrorJson('Formato no soportado', 400);
            }
        } catch (Exception $e) {
            error_log("Error en API exportar reporte: " . $e->getMessage());
            $this->enviarErrorJson('Error al exportar reporte');
        }
    }

    /**
     * API: Buscar operadores para autocompletado
     */
    private function buscarOperadoresApi()
    {
        $termino = trim($_GET['termino'] ?? '');

        if (strlen($termino) < 2) {
            $this->enviarErrorJson('Mínimo 2 caracteres para buscar', 400);
            return;
        }

        try {
            $operadores = $this->service->buscarOperadores($termino);

            $this->enviarJsonExitoso([
                'operadores' => $operadores
            ]);
        } catch (Exception $e) {
            error_log("Error en API buscar operadores: " . $e->getMessage());
            $this->enviarErrorJson('Error al buscar operadores');
        }
    }

    /**
     * Enviar respuesta JSON exitosa
     */
    private function enviarJsonExitoso($datos)
    {
        echo json_encode([
            'success' => true
        ] + $datos, JSON_UNESCAPED_UNICODE);
        exit();
    }

    /**
     * Enviar respuesta JSON de error
     */
    private function enviarErrorJson($mensaje, $codigo = 500)
    {
        http_response_code($codigo);
        echo json_encode([
            'success' => false,
            'error' => $mensaje,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    /**
     * ✅ ACTUALIZADA: Procesar filtros de la API - Con soporte para horarios
     */
    private function procesarFiltrosApi()
    {
        $filtros = [
            'fecha_inicio' => $_GET['fecha_inicio'] ?? date('Y-01-01'),
            'fecha_fin' => $_GET['fecha_fin'] ?? date('Y-m-d'),
            'operador' => trim($_GET['operador'] ?? ''),
            'tipo_producto' => trim($_GET['tipo_producto'] ?? ''),
            'estado' => trim($_GET['estado'] ?? ''),
            'producto' => trim($_GET['producto'] ?? '')
        ];

        // ✅ NUEVO: Procesar filtros de horario
        if (!empty($_GET['hora_inicio']) && !empty($_GET['hora_fin'])) {
            // Validar que las fechas sean iguales (mismo día)
            if ($filtros['fecha_inicio'] === $filtros['fecha_fin']) {
                $filtros['hora_inicio'] = $_GET['hora_inicio'];
                $filtros['hora_fin'] = $_GET['hora_fin'];

                // Validar formato de hora
                if ($this->validarHora($filtros['hora_inicio']) && $this->validarHora($filtros['hora_fin'])) {
                    // Validar que hora_inicio <= hora_fin
                    if (!$this->validarRangoHorario($filtros['hora_inicio'], $filtros['hora_fin'])) {
                        error_log("Rango de horario inválido: {$filtros['hora_inicio']} - {$filtros['hora_fin']}");
                        // No agregar filtros de hora si son inválidos
                        unset($filtros['hora_inicio'], $filtros['hora_fin']);
                    }
                } else {
                    error_log("Formato de hora inválido: {$filtros['hora_inicio']} - {$filtros['hora_fin']}");
                    unset($filtros['hora_inicio'], $filtros['hora_fin']);
                }
            } else {
                error_log("Filtros de hora ignorados: fechas diferentes ({$filtros['fecha_inicio']} != {$filtros['fecha_fin']})");
            }
        }

        return $filtros;
    }

    /**
     * ✅ NUEVA FUNCIÓN: Validar formato de hora (HH:MM)
     */
    private function validarHora($hora)
    {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $hora);
    }

    /**
     * ✅ NUEVA FUNCIÓN: Validar que hora_inicio <= hora_fin
     */
    private function validarRangoHorario($horaInicio, $horaFin)
    {
        list($horaInicioH, $horaInicioM) = explode(':', $horaInicio);
        list($horaFinH, $horaFinM) = explode(':', $horaFin);

        $minutosInicio = ($horaInicioH * 60) + $horaInicioM;
        $minutosFin = ($horaFinH * 60) + $horaFinM;

        return $minutosInicio < $minutosFin;
    }

    /**
     * ✅ ACTUALIZADA: Exportar datos a CSV - Con información de horario
     */
    private function exportarCSV($filtros)
    {
        $datos = $this->service->obtenerDatosParaExportar($filtros);

        $fechaHora = date('Y-m-d_H-i-s');

        // ✅ NUEVO: Incluir información de horario en el nombre del archivo si aplica
        $sufijo = '';
        if (!empty($filtros['hora_inicio']) && !empty($filtros['hora_fin'])) {
            $sufijo = "_h{$filtros['hora_inicio']}-{$filtros['hora_fin']}";
            $sufijo = str_replace(':', '', $sufijo); // Remover : para compatibilidad con nombres de archivo
        }

        $nombreArchivo = "reporte_produccion_america_tnt_{$fechaHora}{$sufijo}.csv";

        // Limpiar buffer antes de enviar archivo
        if (ob_get_level()) {
            ob_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$nombreArchivo}\"");
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        $output = fopen('php://output', 'w');

        // BOM para UTF-8
        fwrite($output, "\xEF\xBB\xBF");

        // ✅ ACTUALIZADO: Encabezados con fecha/hora separadas
        fputcsv($output, [
            'Fecha Producción',
            'Hora Producción',
            'Producto',
            'Tipo',
            'Bobinas/Paquete',
            'Peso Bruto (kg)',
            'Peso Líquido (kg)',
            'Gramatura',
            'Largura',
            'Metragem',
            'Estado',
            'Operador',
            'Orden Producción'
        ]);

        // Datos
        foreach ($datos as $fila) {
            // ✅ NUEVO: Separar fecha y hora
            $fechaHoraCompleta = $fila['fecha_hora_producida'];
            $fecha = date('Y-m-d', strtotime($fechaHoraCompleta));
            $hora = date('H:i:s', strtotime($fechaHoraCompleta));

            fputcsv($output, [
                $fecha,
                $hora,
                $fila['nombre_producto'],
                $fila['tipo_producto'],
                $fila['bobinas_pacote'],
                $fila['peso_bruto'],
                $fila['peso_liquido'],
                $fila['gramatura'],
                $fila['largura'],
                $fila['metragem'],
                $fila['estado'],
                $fila['usuario'],
                $fila['id_orden_produccion']
            ]);
        }

        fclose($output);
        exit();
    }

    /**
     * Obtener datos para la vista principal
     */
    public function obtenerDatosVista()
    {
        try {
            $filtros = [
                'fecha_inicio' => $_GET['fecha_inicio'] ?? date('Y-01-01'),
                'fecha_fin' => $_GET['fecha_fin'] ?? date('Y-m-d'),
                'operador' => trim($_GET['operador'] ?? ''),
                'tipo_producto' => trim($_GET['tipo_producto'] ?? ''),
                'estado' => trim($_GET['estado'] ?? ''),
            ];

            $tiposProducto = $this->service->obtenerTiposProducto();
            $operadores = $this->service->obtenerOperadores();
            $estados = $this->service->obtenerEstados();

            return [
                'titulo' => 'Reportes de Producción - America TNT',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'filtros_aplicados' => $filtros,
                'tipos_producto' => $tiposProducto,
                'operadores' => $operadores,
                'estados' => $estados
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => 'Reportes de Producción - America TNT',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'filtros_aplicados' => [],
                'tipos_producto' => [],
                'operadores' => [],
                'estados' => []
            ];
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
            'version' => 'produccion_reportes_v1.1_horario', // ✅ ACTUALIZADA
            'filtrosHorario' => true // ✅ NUEVO: Indicar soporte para filtros de horario
        ];
    }

    /**
     * ✅ ACTUALIZADA: Log de actividad - Con información de horarios
     */
    public function logActividad($accion, $detalles = null)
    {
        $usuario = $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "PRODUCCION_REPORTES_HORARIO - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

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
     * ✅ ACTUALIZADA: Validar parámetros de entrada - Con horarios
     */
    public function validarParametros($parametros)
    {
        $errores = [];

        if (isset($parametros['fecha_inicio'])) {
            if (!$this->validarFecha($parametros['fecha_inicio'])) {
                $errores[] = 'Fecha de inicio inválida';
            }
        }

        if (isset($parametros['fecha_fin'])) {
            if (!$this->validarFecha($parametros['fecha_fin'])) {
                $errores[] = 'Fecha de fin inválida';
            }
        }

        if (isset($parametros['fecha_inicio']) && isset($parametros['fecha_fin'])) {
            if (strtotime($parametros['fecha_inicio']) > strtotime($parametros['fecha_fin'])) {
                $errores[] = 'La fecha de inicio no puede ser posterior a la fecha de fin';
            }
        }

        // ✅ NUEVO: Validar horarios
        if (isset($parametros['hora_inicio']) && isset($parametros['hora_fin'])) {
            if (!$this->validarHora($parametros['hora_inicio'])) {
                $errores[] = 'Hora de inicio inválida (formato: HH:MM)';
            }

            if (!$this->validarHora($parametros['hora_fin'])) {
                $errores[] = 'Hora de fin inválida (formato: HH:MM)';
            }

            if ($this->validarHora($parametros['hora_inicio']) && $this->validarHora($parametros['hora_fin'])) {
                if (!$this->validarRangoHorario($parametros['hora_inicio'], $parametros['hora_fin'])) {
                    $errores[] = 'La hora de inicio debe ser anterior a la hora de fin';
                }
            }
        }

        return $errores;
    }

    /**
     * Validar formato de fecha
     */
    private function validarFecha($fecha)
    {
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        return $d && $d->format('Y-m-d') === $fecha;
    }
}

// Verificar que las dependencias existan
if (!file_exists('repository/produccionRepository.php') || !file_exists('services/produccionService.php')) {
    die("Error: Faltan archivos del sistema MVC. Verifique que existan los archivos repository/produccionRepository.php y services/produccionService.php");
}

// Instanciar el controller
$controller = new ProduccionController($conexion, $url_base);

// Manejar peticiones API
if ($controller->handleApiRequest()) {
    exit();
}

// Obtener datos para la vista
$datosVista = $controller->obtenerDatosVista();
$configuracionJS = $controller->obtenerConfiguracionJS();

// Extraer datos de vista con valores por defecto
$titulo = $datosVista['titulo'] ?? 'Reportes de Producción';
$url_base = $datosVista['url_base'] ?? '';
$usuario_actual = $datosVista['usuario_actual'] ?? 'Usuario';
$filtros_aplicados = $datosVista['filtros_aplicados'] ?? [];
$tipos_producto = $datosVista['tipos_producto'] ?? [];
$operadores = $datosVista['operadores'] ?? [];
$estados = $datosVista['estados'] ?? [];

// ✅ ACTUALIZADO: Log de actividad con información de horarios si aplica
if (!empty($_GET) && !isset($_GET['action'])) {
    $filtrosStr = 'Consulta reportes producción';
    if (!empty($_GET['hora_inicio']) && !empty($_GET['hora_fin'])) {
        $filtrosStr .= " (horario: {$_GET['hora_inicio']}-{$_GET['hora_fin']})";
    }
    $controller->logActividad('Consulta reportes producción', $filtrosStr);
}
