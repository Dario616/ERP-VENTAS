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
            case 'obtener_stock_real':
                $this->obtenerStockRealApi();
                break;

            case 'filtrar_productos':
                $this->filtrarProductosApi();
                break;

            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

        return true;
    }

    private function obtenerStockRealApi()
    {
        $idOrden = $_GET['id_orden'] ?? null;

        if (!$idOrden) {
            echo json_encode([
                'success' => false,
                'error' => 'ID de orden no proporcionado'
            ]);
            return;
        }

        try {
            $stockReal = $this->service->obtenerStockReal($idOrden);

            echo json_encode([
                'success' => true,
                'stock_real' => $stockReal
            ]);
        } catch (Exception $e) {
            error_log("Error en API stock real: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function filtrarProductosApi()
    {
        try {
            $filtros = [
                'cliente' => trim($_GET['cliente'] ?? ''),
                'producto' => trim($_GET['producto'] ?? ''),
                'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
                'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
                'tipo_producto' => trim($_GET['tipo_producto'] ?? ''),
                'pagina' => max(1, (int)($_GET['pagina'] ?? 1))
            ];

            $resultado = $this->service->obtenerProductosConStock($filtros);

            echo json_encode([
                'success' => true,
                'productos' => $resultado['productos'],
                'total_paginas' => $resultado['total_paginas'],
                'total_registros' => $resultado['total_registros']
            ]);
        } catch (Exception $e) {
            error_log("Error en API filtrar productos: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    public function obtenerProductosEnProduccion($filtros = [])
    {
        try {
            return $this->service->obtenerProductosConStock($filtros);
        } catch (Exception $e) {
            error_log("Error obteniendo productos en producción: " . $e->getMessage());
            return [
                'productos' => [],
                'total_paginas' => 0,
                'total_registros' => 0
            ];
        }
    }

    public function obtenerDetallesOrden($idOrden)
    {
        try {
            if (!$this->validarId($idOrden)) {
                throw new Exception('ID de orden inválido');
            }

            return $this->service->obtenerDetallesOrden($idOrden);
        } catch (Exception $e) {
            error_log("Error obteniendo detalles orden: " . $e->getMessage());
            throw new Exception('Orden no encontrada');
        }
    }

    public function obtenerOrdenesProduccion($filtros = [])
    {
        try {
            return $this->service->obtenerOrdenesProduccion($filtros);
        } catch (Exception $e) {
            error_log("Error obteniendo órdenes de producción: " . $e->getMessage());
            return [
                'ordenes' => [],
                'total_paginas' => 0,
                'total_registros' => 0
            ];
        }
    }

    public function obtenerDatosVista()
    {
        return [
            'titulo' => 'Gestión de Producción',
            'url_base' => $this->urlBase,
            'fecha_actual' => date('Y-m-d H:i:s'),
            'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
            'es_admin' => $this->esAdministrador()
        ];
    }

    public function procesarFiltros()
    {
        $filtros = [
            'cliente' => trim($_GET['cliente'] ?? ''),
            'producto' => trim($_GET['producto'] ?? ''),
            'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
            'tipo_producto' => trim($_GET['tipo_producto'] ?? ''),
            'pagina' => max(1, (int)($_GET['pagina'] ?? 1))
        ];

        try {
            $productos = $this->obtenerProductosEnProduccion($filtros);

            return [
                'error' => '',
                'productos' => $productos['productos'],
                'total_paginas' => $productos['total_paginas'],
                'total_registros' => $productos['total_registros'],
                'filtros_aplicados' => $filtros
            ];
        } catch (Exception $e) {
            error_log("Error procesando filtros: " . $e->getMessage());
            return [
                'error' => 'Error al procesar la búsqueda',
                'productos' => [],
                'total_paginas' => 0,
                'total_registros' => 0,
                'filtros_aplicados' => $filtros
            ];
        }
    }

    public function procesarFiltrosOrdenes()
    {
        $filtros = [
            'cliente' => trim($_GET['cliente'] ?? ''),
            'estado' => trim($_GET['estado'] ?? ''),
            'tipo' => trim($_GET['tipo'] ?? ''),
            'tiene_receta' => trim($_GET['tiene_receta'] ?? ''), // Nuevo filtro
            'pagina' => max(1, (int)($_GET['pagina'] ?? 1))
        ];

        try {
            $ordenes = $this->obtenerOrdenesProduccion($filtros);

            return [
                'error' => '',
                'ordenes' => $ordenes['ordenes'],
                'total_paginas' => $ordenes['total_paginas'],
                'total_registros' => $ordenes['total_registros'],
                'tipos_productos' => $ordenes['tipos_productos'],
                'filtros_aplicados' => $filtros
            ];
        } catch (Exception $e) {
            error_log("Error procesando filtros órdenes: " . $e->getMessage());
            return [
                'error' => 'Error al procesar la búsqueda',
                'ordenes' => [],
                'total_paginas' => 0,
                'total_registros' => 0,
                'tipos_productos' => [],
                'filtros_aplicados' => $filtros
            ];
        }
    }

    private function esAdministrador()
    {
        return isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['1', '4']); // Admin y Producción
    }

    private function validarId($id)
    {
        return is_numeric($id) && $id > 0;
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

    public function verificarPermisos($accion)
    {
        $esAdmin = $this->esAdministrador();
        $tienePermiso = false;

        switch ($accion) {
            case 'ver':
            case 'listar':
                $tienePermiso = $esAdmin;
                break;

            case 'gestionar':
                $tienePermiso = $esAdmin;
                break;

            default:
                $tienePermiso = false;
        }

        return $tienePermiso;
    }

    public function obtenerTiposProductos()
    {
        try {
            return $this->service->obtenerTiposProductos();
        } catch (Exception $e) {
            error_log("Error obteniendo tipos de productos: " . $e->getMessage());
            return [];
        }
    }
}
