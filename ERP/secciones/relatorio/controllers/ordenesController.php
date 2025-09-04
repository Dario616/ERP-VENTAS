<?php
require_once 'repository/ordenesRepository.php';

class OrdenesController
{
    private $repository;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new OrdenesRepository($conexion);
        $this->urlBase = $urlBase;
    }

    public function handleApiRequest()
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        header('Content-Type: application/json; charset=utf-8');

        try {
            switch ($_GET['action']) {
                case 'obtener_ordenes':
                    $this->obtenerOrdenesApi();
                    break;
                case 'obtener_productos_orden':
                    $this->obtenerProductosOrdenApi();
                    break;
                case 'obtener_produccion_real':
                    $this->obtenerProduccionRealApi();
                    break;
                case 'obtener_produccion_real_agrupada':
                    $this->obtenerProduccionRealAgrupadaApi();
                    break;
                case 'obtener_resumen_produccion':
                    $this->obtenerResumenProduccionApi();
                    break;
                default:
                    $this->enviarErrorJson('Acción no válida', 400);
            }
        } catch (Exception $e) {
            error_log("Error en API órdenes: " . $e->getMessage());
            $this->enviarErrorJson('Error interno del servidor', 500);
        }

        return true;
    }

    private function obtenerOrdenesApi()
    {
        $filtros = $this->procesarFiltrosApi();
        $ordenes = $this->repository->obtenerOrdenes($filtros);

        $this->enviarJsonExitoso([
            'ordenes' => $ordenes
        ]);
    }

    private function obtenerProductosOrdenApi()
    {
        $idOrden = (int)($_GET['id_orden'] ?? 0);
        
        if ($idOrden <= 0) {
            $this->enviarErrorJson('ID de orden inválido', 400);
            return;
        }

        $productos = $this->repository->obtenerProductosOrden($idOrden);

        $this->enviarJsonExitoso([
            'productos' => $productos
        ]);
    }

    private function obtenerProduccionRealApi()
    {
        $idOrden = (int)($_GET['id_orden'] ?? 0);
        
        if ($idOrden <= 0) {
            $this->enviarErrorJson('ID de orden inválido', 400);
            return;
        }

        $produccionReal = $this->repository->obtenerProduccionReal($idOrden);

        $this->enviarJsonExitoso([
            'produccion_real' => $produccionReal
        ]);
    }

    // NUEVA FUNCIÓN: API para obtener producción real agrupada
    private function obtenerProduccionRealAgrupadaApi()
    {
        $idOrden = (int)($_GET['id_orden'] ?? 0);
        
        if ($idOrden <= 0) {
            $this->enviarErrorJson('ID de orden inválido', 400);
            return;
        }

        $produccionRealAgrupada = $this->repository->obtenerProduccionRealAgrupada($idOrden);

        $this->enviarJsonExitoso([
            'produccion_real_agrupada' => $produccionRealAgrupada
        ]);
    }

    private function obtenerResumenProduccionApi()
    {
        $idOrden = (int)($_GET['id_orden'] ?? 0);
        
        if ($idOrden <= 0) {
            $this->enviarErrorJson('ID de orden inválido', 400);
            return;
        }

        $resumen = $this->repository->obtenerResumenProduccion($idOrden);

        $this->enviarJsonExitoso([
            'resumen' => $resumen
        ]);
    }

    private function procesarFiltrosApi()
    {
        return [
            'fecha_inicio' => $_GET['fecha_inicio'] ?? date('Y-m-01'),
            'fecha_fin' => $_GET['fecha_fin'] ?? date('Y-m-d'),
            'estado' => trim($_GET['estado'] ?? ''),
            'cliente' => trim($_GET['cliente'] ?? ''),
            'numero_orden' => trim($_GET['numero_orden'] ?? '')
        ];
    }

    private function enviarJsonExitoso($datos)
    {
        echo json_encode([
            'success' => true
        ] + $datos, JSON_UNESCAPED_UNICODE);
        exit();
    }

    private function enviarErrorJson($mensaje, $codigo = 500)
    {
        http_response_code($codigo);
        echo json_encode([
            'success' => false,
            'error' => $mensaje
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    public function obtenerDatosVista()
    {
        return [
            'titulo' => 'Órdenes de Producción - America TNT',
            'url_base' => $this->urlBase,
            'fecha_actual' => date('Y-m-d H:i:s'),
            'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario'
        ];
    }
}