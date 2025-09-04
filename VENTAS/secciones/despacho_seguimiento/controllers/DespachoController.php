<?php
require_once __DIR__ . '/../repository/DespachoRepository.php';
require_once __DIR__ . '/../services/DespachoService.php';

date_default_timezone_set('America/Asuncion');

class DespachoController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new DespachoRepository($conexion);
        $this->service = new DespachoService($this->repository);
        $this->urlBase = $urlBase;
    }

    public function handleApiRequest()
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        header('Content-Type: application/json');

        switch ($_GET['action']) {
            case 'buscar_expediciones':
                $this->buscarExpedicionesApi();
                break;

            case 'obtener_estadisticas_despacho':
                $this->obtenerEstadisticasDespachoApi();
                break;

            case 'obtener_transportistas':
                $this->obtenerTransportistasApi();
                break;

            case 'obtener_estados_expedicion':
                $this->obtenerEstadosExpedicionApi();
                break;

            case 'obtener_destinos':
                $this->obtenerDestinosApi();
                break;

            case 'obtener_detalles_expedicion':
                $this->obtenerDetallesExpedicionApi();
                break;

            case 'obtener_resumen_expediciones':
                $this->obtenerResumenExpedicionesApi();
                break;

            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

        return true;
    }

    private function buscarExpedicionesApi()
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
            $resultados = $this->service->buscarExpediciones($termino);

            echo json_encode([
                'success' => true,
                'resultados' => $resultados
            ]);
        } catch (Exception $e) {
            error_log("Error en API buscar expediciones: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerEstadisticasDespachoApi()
    {
        try {
            $estadisticas = $this->service->obtenerEstadisticas();

            echo json_encode([
                'success' => true,
                'estadisticas' => $estadisticas
            ]);
        } catch (Exception $e) {
            error_log("Error en API estadísticas despacho: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerTransportistasApi()
    {
        try {
            $transportistas = $this->service->obtenerTransportistas();

            echo json_encode([
                'success' => true,
                'transportistas' => $transportistas
            ]);
        } catch (Exception $e) {
            error_log("Error en API transportistas: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerEstadosExpedicionApi()
    {
        try {
            $estados = $this->service->obtenerEstadosExpedicion();

            echo json_encode([
                'success' => true,
                'estados' => $estados
            ]);
        } catch (Exception $e) {
            error_log("Error en API estados expedición: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerDestinosApi()
    {
        try {
            $destinos = $this->service->obtenerDestinos();

            echo json_encode([
                'success' => true,
                'destinos' => $destinos
            ]);
        } catch (Exception $e) {
            error_log("Error en API destinos: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerDetallesExpedicionApi()
    {
        $numeroExpedicion = $_GET['numero_expedicion'] ?? '';

        if (empty($numeroExpedicion)) {
            echo json_encode([
                'success' => false,
                'error' => 'Número de expedición requerido'
            ]);
            return;
        }

        try {
            $detalles = $this->service->obtenerDetallesExpedicion($numeroExpedicion);

            echo json_encode([
                'success' => true,
                'detalles' => $detalles
            ]);
        } catch (Exception $e) {
            error_log("Error en API detalles expedición: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function obtenerResumenExpedicionesApi()
    {
        try {
            $resumen = $this->service->obtenerResumenExpediciones();

            echo json_encode([
                'success' => true,
                'resumen' => $resumen
            ]);
        } catch (Exception $e) {
            error_log("Error en API resumen expediciones: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }


    public function obtenerExpediciones($filtros = [])
    {
        try {
            return $this->service->obtenerExpediciones($filtros);
        } catch (Exception $e) {
            error_log("Error obteniendo expediciones: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerDetallesExpedicion($numeroExpedicion)
    {
        try {
            $agrupar = isset($_GET['agrupar']) && $_GET['agrupar'] === 'true';

            return $this->service->obtenerDetallesExpedicion($numeroExpedicion, $agrupar);
        } catch (Exception $e) {
            error_log("Error obteniendo detalles de expedición: " . $e->getMessage());
            throw new Exception('Expedición no encontrada o sin items');
        }
    }

    public function obtenerDatosVista()
    {
        try {
            $estadisticas = $this->service->obtenerEstadisticas();
            $transportistas = $this->service->obtenerTransportistas();
            $estadosExpedicion = $this->service->obtenerEstadosExpedicion();
            $destinos = $this->service->obtenerDestinos();

            return [
                'titulo' => 'Seguimiento de Expediciones',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => $this->esAdministrador(),
                'estadisticas' => $estadisticas,
                'transportistas' => $transportistas,
                'estados_expedicion' => $estadosExpedicion,
                'destinos' => $destinos
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => 'Seguimiento de Expediciones',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => false,
                'estadisticas' => [],
                'transportistas' => [],
                'estados_expedicion' => [],
                'destinos' => []
            ];
        }
    }

    public function procesarFiltros()
    {
        $filtros = [
            'numero_expedicion' => trim($_GET['numero_expedicion'] ?? ''),
            'transportista' => trim($_GET['transportista'] ?? ''),
            'destino' => trim($_GET['destino'] ?? ''),
            'estado' => trim($_GET['estado'] ?? ''),
            'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
            'id_stock' => trim($_GET['id_stock'] ?? ''),
            'id_venta_asignado' => trim($_GET['id_venta_asignado'] ?? ''),
            'pagina' => max(1, (int)($_GET['page'] ?? 1))
        ];

        try {
            $errores = $this->service->validarFiltros($filtros);

            if (!empty($errores)) {
                return [
                    'error' => implode(', ', $errores),
                    'expediciones' => [],
                    'filtros_aplicados' => $filtros
                ];
            }

            $expediciones = $this->obtenerExpediciones($filtros);

            return [
                'error' => '',
                'expediciones' => $expediciones,
                'filtros_aplicados' => $filtros,
                'resumen' => $this->service->generarResumenExport($expediciones)
            ];
        } catch (Exception $e) {
            error_log("Error procesando filtros: " . $e->getMessage());
            return [
                'error' => 'Error al procesar la búsqueda',
                'expediciones' => [],
                'filtros_aplicados' => $filtros
            ];
        }
    }

    private function esAdministrador()
    {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === '1,';
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

        $mensaje = "DESPACHO_SEGUIMIENTO - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

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
            'modulo' => 'despacho_seguimiento'
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
        $rolUsuario = $_SESSION['rol'] ?? '0';
        $tienePermiso = false;

        switch ($accion) {
            case 'ver':
            case 'listar':
                $tienePermiso = true;
                break;

            case 'exportar':
                $tienePermiso = $esAdmin || in_array($rolUsuario, ['1,', '2,', '3,']);
                break;

            case 'modificar_expedicion':
                $tienePermiso = $esAdmin;
                break;

            case 'editar_descripcion':
                // CAMBIO: Ahora cualquier usuario puede editar descripciones
                $tienePermiso = true;
                break;

            default:
                $tienePermiso = false;
        }

        return $tienePermiso;
    }
}

$repositoryPath = __DIR__ . '/../repository/DespachoRepository.php';
$servicePath = __DIR__ . '/../services/DespachoService.php';

if (!file_exists($repositoryPath) || !file_exists($servicePath)) {
    die("Error crítico: Faltan archivos de dependencia del controlador." .
        "<br>Ruta del repositorio buscada: " . htmlspecialchars($repositoryPath) .
        "<br>Ruta del servicio buscada: " . htmlspecialchars($servicePath));
}
