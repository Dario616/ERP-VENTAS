<?php
require_once 'repository/configRepository.php';
require_once 'services/configService.php';

date_default_timezone_set('America/Asuncion');

class ConfigController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new ConfigRepository($conexion);
        $this->service = new ConfigService($this->repository);
        $this->urlBase = $urlBase;
    }

    public function handleApiRequest()
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        header('Content-Type: application/json');

        switch ($_GET['action']) {
            case 'listar_creditos':
                $this->listarCreditosApi();
                break;

            case 'listar_tipos_producto':
                $this->listarTiposProductoApi();
                break;

            case 'listar_unidades_medida':
                $this->listarUnidadesMedidaApi();
                break;

            case 'eliminar_credito':
                $this->eliminarCreditoApi();
                break;

            case 'eliminar_tipo_producto':
                $this->eliminarTipoProductoApi();
                break;

            case 'eliminar_unidad_medida':
                $this->eliminarUnidadMedidaApi();
                break;

            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

        return true;
    }

    private function listarCreditosApi()
    {
        try {
            $creditos = $this->service->obtenerCreditos();
            echo json_encode([
                'success' => true,
                'datos' => $creditos
            ]);
        } catch (Exception $e) {
            error_log("Error listando créditos: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function listarTiposProductoApi()
    {
        try {
            $tipos = $this->service->obtenerTiposProducto();
            echo json_encode([
                'success' => true,
                'datos' => $tipos
            ]);
        } catch (Exception $e) {
            error_log("Error listando tipos de producto: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function listarUnidadesMedidaApi()
    {
        try {
            $unidades = $this->service->obtenerUnidadesMedida();
            echo json_encode([
                'success' => true,
                'datos' => $unidades
            ]);
        } catch (Exception $e) {
            error_log("Error listando unidades de medida: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function eliminarCreditoApi()
    {
        if (!isset($_POST['id'])) {
            echo json_encode(['success' => false, 'error' => 'ID requerido']);
            return;
        }

        try {
            $resultado = $this->service->eliminarCredito($_POST['id']);
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error eliminando crédito: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function eliminarTipoProductoApi()
    {
        if (!isset($_POST['id'])) {
            echo json_encode(['success' => false, 'error' => 'ID requerido']);
            return;
        }

        try {
            $resultado = $this->service->eliminarTipoProducto($_POST['id']);
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error eliminando tipo de producto: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function eliminarUnidadMedidaApi()
    {
        if (!isset($_POST['id'])) {
            echo json_encode(['success' => false, 'error' => 'ID requerido']);
            return;
        }

        try {
            $resultado = $this->service->eliminarUnidadMedida($_POST['id']);
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error eliminando unidad de medida: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    public function procesarCreditos($operacion, $datos = [])
    {
        try {
            switch ($operacion) {
                case 'crear':
                    return $this->service->crearCredito($datos);

                case 'actualizar':
                    return $this->service->actualizarCredito($datos['id'], $datos);

                case 'eliminar':
                    return $this->service->eliminarCredito($datos['id']);

                case 'obtener':
                    $credito = $this->service->obtenerCredito($datos['id']);
                    return ['success' => true, 'datos' => $credito];

                case 'listar':
                default:
                    return ['success' => true, 'datos' => $this->service->obtenerCreditos()];
            }
        } catch (Exception $e) {
            error_log("Error procesando créditos: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function procesarTiposProducto($operacion, $datos = [])
    {
        try {
            switch ($operacion) {
                case 'crear':
                    return $this->service->crearTipoProducto($datos);

                case 'actualizar':
                    return $this->service->actualizarTipoProducto($datos['id'], $datos);

                case 'eliminar':
                    return $this->service->eliminarTipoProducto($datos['id']);

                case 'obtener':
                    $tipo = $this->service->obtenerTipoProducto($datos['id']);
                    return ['success' => true, 'datos' => $tipo];

                case 'listar':
                default:
                    return ['success' => true, 'datos' => $this->service->obtenerTiposProducto()];
            }
        } catch (Exception $e) {
            error_log("Error procesando tipos de producto: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function procesarUnidadesMedida($operacion, $datos = [])
    {
        try {
            switch ($operacion) {
                case 'crear':
                    return $this->service->crearUnidadMedida($datos);

                case 'actualizar':
                    return $this->service->actualizarUnidadMedida($datos['id'], $datos);

                case 'eliminar':
                    return $this->service->eliminarUnidadMedida($datos['id']);

                case 'obtener':
                    $unidad = $this->service->obtenerUnidadMedida($datos['id']);
                    return ['success' => true, 'datos' => $unidad];

                case 'listar':
                default:
                    return ['success' => true, 'datos' => $this->service->obtenerUnidadesMedida()];
            }
        } catch (Exception $e) {
            error_log("Error procesando unidades de medida: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }


    public function obtenerDatosVista()
    {
        try {
            return [
                'titulo' => 'Configuración del Sistema',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'creditos' => $this->service->obtenerCreditos(),
                'tipos_producto' => $this->service->obtenerTiposProducto(),
                'unidades_medida' => $this->service->obtenerUnidadesMedida()
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => 'Configuración del Sistema',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'creditos' => [],
                'tipos_producto' => [],
                'unidades_medida' => []
            ];
        }
    }

    public function validarParametros($parametros, $tipo)
    {
        $errores = [];

        switch ($tipo) {
            case 'credito':
                if (empty($parametros['descripcion'])) {
                    $errores[] = 'La descripción del crédito es obligatoria';
                }
                break;

            case 'tipo_producto':
                if (empty($parametros['desc'])) {
                    $errores[] = 'La descripción del tipo de producto es obligatoria';
                }
                break;

            case 'unidad_medida':
                if (empty($parametros['desc'])) {
                    $errores[] = 'La descripción de la unidad de medida es obligatoria';
                }
                break;
        }

        return $errores;
    }

    public function obtenerConfiguracionJS()
    {
        return [
            'urlBase' => $this->urlBase,
            'usuario' => $_SESSION['nombre'] ?? 'Usuario',
            'debug' => isset($_GET['debug']),
            'autoRefresh' => false
        ];
    }
}

if (!file_exists('repository/configRepository.php') || !file_exists('services/configService.php')) {
    die("Error: Faltan archivos del sistema MVC. Verifique que existan los archivos repository/configRepository.php y services/configService.php");
}
