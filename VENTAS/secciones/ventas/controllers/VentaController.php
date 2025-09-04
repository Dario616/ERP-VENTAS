<?php
require_once __DIR__ . '/../repository/VentaRepository.php';
require_once __DIR__ . '/../services/VentaService.php';

date_default_timezone_set('America/Asuncion');

class VentaController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new VentaRepository($conexion);
        $this->service = new VentaService($this->repository);
        $this->urlBase = $urlBase;
    }

    public function handleApiRequest()
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        header('Content-Type: application/json');

        switch ($_GET['action']) {
            case 'actualizar_transportadora':
                $this->actualizarTransportadoraApi();
                break;

            case 'eliminar_venta':
                $this->eliminarVentaApi();
                break;

            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

        return true;
    }

    public function contarVentasRechazadas($mostrarTodas, $idUsuario)
    {
        try {
            $filtros = ['estado' => 'Rechazado'];
            return $this->repository->contarVentas($filtros, $mostrarTodas, $idUsuario);
        } catch (Exception $e) {
            error_log("Error contando ventas rechazadas: " . $e->getMessage());
            return 0;
        }
    }


    private function actualizarTransportadoraApi()
    {
        $idVenta = $_POST['id_venta'] ?? null;
        $transportadora = $_POST['transportadora'] ?? '';

        if (!$idVenta) {
            echo json_encode(['success' => false, 'error' => 'ID de venta no proporcionado']);
            return;
        }

        try {
            $resultado = $this->service->actualizarTransportadora($idVenta, $transportadora);
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en API actualizar transportadora: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function eliminarVentaApi()
    {
        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
            return;
        }

        try {
            $resultado = $this->service->eliminarVenta($id);
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en API eliminar venta: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }


    public function procesarRegistro()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['error' => 'Método no permitido'];
        }

        try {
            $datos = $this->obtenerDatosFormulario();
            $productos = $this->obtenerProductosFormulario();

            $resultado = $this->service->crearVenta($datos, $productos, $_SESSION['id']);

            if ($resultado['success']) {
                header("Location: " . $this->urlBase . "secciones/ventas/index.php?mensaje=" . urlencode($resultado['mensaje']));
                exit();
            } else {
                return [
                    'errores' => $resultado['errores'],
                    'datos' => $datos
                ];
            }
        } catch (Exception $e) {
            error_log("Error procesando registro: " . $e->getMessage());
            return [
                'error' => 'Error interno del servidor',
                'datos' => $this->obtenerDatosFormulario()
            ];
        }
    }

    public function procesarEdicion($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['error' => 'Método no permitido'];
        }

        try {
            $datos = $this->obtenerDatosFormulario();
            $productos = $this->obtenerProductosFormulario();

            $resultado = $this->service->actualizarVenta($id, $datos, $productos, $_SESSION['id']);

            if ($resultado['success']) {
                header("Location: " . $this->urlBase . "secciones/ventas/index.php?mensaje=" . urlencode($resultado['mensaje']));
                exit();
            } else {
                return [
                    'errores' => $resultado['errores'],
                    'datos' => $datos
                ];
            }
        } catch (Exception $e) {
            error_log("Error procesando edición: " . $e->getMessage());
            return [
                'error' => 'Error interno del servidor',
                'datos' => $this->obtenerDatosFormulario()
            ];
        }
    }

    public function obtenerVentaParaEdicion($id)
    {
        try {
            return $this->service->obtenerVentaSimple($id);
        } catch (Exception $e) {
            error_log("Error obteniendo venta: " . $e->getMessage());
            throw new Exception('Venta no encontrada');
        }
    }


    public function obtenerVentaParaVer($id, $mostrarTodas = true, $idUsuario = null)
    {
        try {
            return $this->service->obtenerVentaPorId($id, $mostrarTodas, $idUsuario);
        } catch (Exception $e) {
            error_log("Error obteniendo venta: " . $e->getMessage());
            throw new Exception('Venta no encontrada o sin permisos');
        }
    }


    public function obtenerProductosVenta($idVenta)
    {
        try {
            return $this->service->obtenerProductosVenta($idVenta);
        } catch (Exception $e) {
            error_log("Error obteniendo productos: " . $e->getMessage());
            return [];
        }
    }


    public function procesarEliminacion($id)
    {
        try {
            $resultado = $this->service->eliminarVenta($id);

            if ($resultado['success']) {
                return ['mensaje' => $resultado['mensaje']];
            } else {
                return ['error' => $resultado['error']];
            }
        } catch (Exception $e) {
            error_log("Error procesando eliminación: " . $e->getMessage());
            return ['error' => 'Error interno del servidor'];
        }
    }


    public function obtenerListaVentas($filtros, $registrosPorPagina, $paginaActual, $mostrarTodas, $idUsuarioActual)
    {
        try {
            return $this->service->obtenerVentasPaginadas(
                $filtros,
                $registrosPorPagina,
                $paginaActual,
                $mostrarTodas,
                $idUsuarioActual
            );
        } catch (Exception $e) {
            error_log("Error obteniendo lista de ventas: " . $e->getMessage());
            return [
                'ventas' => [],
                'totalRegistros' => 0,
                'totalPaginas' => 0,
                'paginaActual' => 1
            ];
        }
    }

    public function procesarTransportadora()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['actualizar_transportadora'])) {
            return null;
        }

        $idVenta = $_POST['id_venta'];
        $transportadora = $_POST['transportadora'];

        try {
            $resultado = $this->service->actualizarTransportadora($idVenta, $transportadora);

            if ($resultado['success']) {
                header("Location: " . $this->urlBase . "secciones/ventas/index.php?mensaje=" . urlencode($resultado['mensaje']));
                exit();
            } else {
                return ['error' => $resultado['error']];
            }
        } catch (Exception $e) {
            error_log("Error procesando transportadora: " . $e->getMessage());
            return ['error' => 'Error interno del servidor'];
        }
    }


    public function procesarAutorizacion($idVenta)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['error' => 'Método no permitido'];
        }

        try {
            $descripcion = $_POST['descripcion'] ?? '';
            $resultado = $this->service->procesarAutorizacion($idVenta, $descripcion, $_FILES, $_SESSION['id']);

            if ($resultado['success']) {
                header("Location: " . $this->urlBase . "secciones/ventas/index.php?mensaje=" . urlencode($resultado['mensaje']));
                exit();
            } else {
                return ['error' => $resultado['error']];
            }
        } catch (Exception $e) {
            error_log("Error procesando autorización: " . $e->getMessage());
            return ['error' => 'Error interno del servidor'];
        }
    }

    public function verificarPermisos($accion, $idVenta = null, $idUsuario = null)
    {
        $esAdminOVendedor = isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['1', '2']);

        if (!$esAdminOVendedor) {
            return false;
        }

        switch ($accion) {
            case 'ver':
            case 'listar':
            case 'crear':
                return true;

            case 'editar':
            case 'eliminar':
                if ($idVenta) {
                    try {
                        $venta = $this->service->obtenerVentaSimple($idVenta);
                        $estado = $venta['estado'] ?? '';

                        return in_array($estado, ['Pendiente', 'Rechazado', '']) || empty($estado);
                    } catch (Exception $e) {
                        return false;
                    }
                }
                return true;

            case 'autorizar':
                if ($idVenta) {
                    try {
                        $venta = $this->service->obtenerVentaSimple($idVenta);
                        $estado = $venta['estado'] ?? '';

                        return in_array($estado, ['Pendiente', 'Rechazado', '']) || empty($estado);
                    } catch (Exception $e) {
                        return false;
                    }
                }
                return true;

            default:
                return false;
        }
    }


    public function obtenerTiposProductos()
    {
        return $this->service->obtenerTiposProductos();
    }


    public function obtenerTiposCredito()
    {
        return $this->service->obtenerTiposCredito();
    }


    public function obtenerEstadosVentas($mostrarTodas = true, $idUsuario = null)
    {
        return $this->service->obtenerEstadosVentas($mostrarTodas, $idUsuario);
    }

    public function obtenerDatosVista()
    {
        try {
            return [
                'titulo' => 'Gestión de Ventas',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => $this->esAdministrador(),
                'es_vendedor' => $this->esVendedor()
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => 'Gestión de Ventas',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => false,
                'es_vendedor' => false
            ];
        }
    }


    private function obtenerDatosFormulario()
    {
        $datos = [
            'cliente' => trim($_POST['cliente'] ?? ''),
            'tipoflete' => trim($_POST['tipoflete'] ?? ''),
            'moneda' => trim($_POST['moneda'] ?? ''),
            'cond_pago' => trim($_POST['cond_pago'] ?? ''),
            'tipo_pago' => trim($_POST['tipo_pago'] ?? ''),
            'fecha_venta' => trim($_POST['fecha_venta'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'aplicar_iva' => isset($_POST['aplicar_iva']) ? $_POST['aplicar_iva'] : null
        ];

        if ($datos['cond_pago'] === 'Crédito') {
            $datos['tipocredito'] = trim($_POST['tipocredito'] ?? '');
        }

        return $datos;
    }


    private function obtenerProductosFormulario()
    {
        if (!isset($_POST['productos']) || !is_array($_POST['productos'])) {
            throw new Exception("No se encontraron productos en los datos enviados");
        }

        $productosLimpios = [];
        $indicesOriginales = array_keys($_POST['productos']);

        foreach ($indicesOriginales as $indiceOriginal) {
            $productoOriginal = $_POST['productos'][$indiceOriginal];

            $productoLimpio = [
                'id_producto' => $productoOriginal['id_producto'] ?? '',
                'tipo_producto' => $productoOriginal['tipo_producto'] ?? '',
                'descripcion' => $productoOriginal['descripcion'] ?? '',
                'unidad_medida' => $productoOriginal['unidad_medida'] ?? '',
                'ncm' => $productoOriginal['ncm'] ?? '',
                'instruccion' => trim($productoOriginal['instruccion'] ?? ''),
                'cantidad' => $productoOriginal['cantidad'] ?? '',
                'precio' => $productoOriginal['precio'] ?? '',
            ];

            $productosLimpios[] = $productoLimpio;
        }

        return $productosLimpios;
    }


    private function esAdministrador()
    {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === '1';
    }


    private function esVendedor()
    {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === '2';
    }


    public function obtenerConfiguracionJS()
    {
        return [
            'url_base' => $this->urlBase,
            'usuario' => $_SESSION['nombre'] ?? 'Usuario',
            'esAdmin' => $this->esAdministrador(),
            'esVendedor' => $this->esVendedor(),
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

    public function logActividad($accion, $detalles = null)
    {
        $usuario = $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "VENTAS - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);
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

        if (isset($parametros['pagina'])) {
            $pagina = (int)$parametros['pagina'];
            if ($pagina < 1) {
                $errores[] = 'Número de página inválido';
            }
        }

        return $errores;
    }

    public function obtenerInformacionCompletaVenta($idVenta, $mostrarTodo, $idUsuarioActual)
    {
        try {
            $venta = $this->obtenerVentaParaVer($idVenta, $mostrarTodo, $idUsuarioActual);

            $productos = $this->obtenerProductosVenta($idVenta);

            $historial = $this->obtenerHistorialVenta($idVenta);

            $productosProduccion = $this->obtenerProductosProduccion($idVenta);

            $productosExpedicion = $this->obtenerProductosExpedicion($idVenta);

            $ordenesConDetalles = $this->obtenerOrdenesProduccion($productosProduccion);

            return [
                'venta' => $venta,
                'productos' => $productos,
                'historial' => $historial,
                'productosProduccion' => $productosProduccion,
                'productosExpedicion' => $productosExpedicion,
                'ordenesConDetalles' => $ordenesConDetalles
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo información completa de venta: " . $e->getMessage());
            throw $e;
        }
    }

    private function obtenerHistorialVenta($idVenta)
    {
        try {
            $sql = "SELECT h.*, u.nombre as usuario_nombre 
                    FROM public.sist_ventas_historial_acciones h
                    LEFT JOIN public.sist_ventas_usuario u ON h.id_usuario = u.id
                    WHERE h.id_venta = :id_venta 
                    ORDER BY h.fecha_accion ASC";

            $stmt = $this->repository->getConexion()->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo historial: " . $e->getMessage());
            return [];
        }
    }

    private function obtenerProductosProduccion($idVenta)
    {
        try {
            $sql = "SELECT DISTINCT pp.*, 
                            prod.descripcion, 
                            prod.tipoproducto, 
                            prod.unidadmedida,
                            COALESCE(ot.id_orden_produccion, tnt.id_orden_produccion, sp.id_orden_produccion) as id_orden_produccion,
                            COALESCE(ot.nombre, 
                                    CONCAT('TNT ', tnt.gramatura, 'g - ', tnt.color),
                                    CONCAT('Spunlace ', sp.gramatura, 'g - ', sp.color, COALESCE(CONCAT(' - ', sp.acabado), ''))
                            ) as nombre_orden_produccion,
                            inv.cantidad_inventario
                     FROM public.sist_ventas_productos_produccion pp
                     LEFT JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
                     LEFT JOIN public.sist_ventas_op_toallitas ot ON pp.id = ot.id_producto_produccion
                     LEFT JOIN public.sist_ventas_op_tnt tnt ON pp.id = tnt.id_producto_produccion  
                     LEFT JOIN public.sist_ventas_op_spunlace sp ON pp.id = sp.id_producto_produccion
                     LEFT JOIN (
                         SELECT p.id, p.cantidad as cantidad_inventario
                         FROM public.sist_ventas_productos p
                         JOIN public.sist_ventas_pres_product prod ON prod.id_producto = p.id
                     ) inv ON inv.id = prod.id_producto
                     WHERE pp.id_venta = :id_venta AND pp.destino = 'Producción'
                     ORDER BY pp.fecha_asignacion DESC";

            $stmt = $this->repository->getConexion()->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos en producción: " . $e->getMessage());
            return [];
        }
    }

    private function obtenerProductosExpedicion($idVenta)
    {
        try {
            $sql = "SELECT pe.*, prod.descripcion, prod.tipoproducto, prod.unidadmedida
                    FROM public.sist_ventas_productos_expedicion pe
                    LEFT JOIN public.sist_ventas_pres_product prod ON pe.id_producto = prod.id
                    WHERE pe.id_venta = :id_venta
                    ORDER BY pe.fecha_asignacion DESC";

            $stmt = $this->repository->getConexion()->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos en expedición: " . $e->getMessage());
            return [];
        }
    }

    private function obtenerOrdenesProduccion($productosProduccion)
    {
        try {
            if (empty($productosProduccion)) {
                return [];
            }

            $ordenesIds = array_filter(array_unique(array_column($productosProduccion, 'id_orden_produccion')));

            if (empty($ordenesIds)) {
                return [];
            }

            $ordenesPlaceholder = str_repeat('?,', count($ordenesIds) - 1) . '?';

            $sql = "SELECT DISTINCT
                           op.id,
                           op.fecha_orden,
                           op.estado,
                           op.observaciones,
                           (
                               (SELECT COUNT(DISTINCT ot.id) FROM public.sist_ventas_op_toallitas ot WHERE ot.id_orden_produccion = op.id) +
                               (SELECT COUNT(DISTINCT tnt.id) FROM public.sist_ventas_op_tnt tnt WHERE tnt.id_orden_produccion = op.id) +
                               (SELECT COUNT(DISTINCT sp.id) FROM public.sist_ventas_op_spunlace sp WHERE sp.id_orden_produccion = op.id)
                           ) as total_items,
                           (
                               COALESCE((SELECT SUM(ot.cantidad_total) FROM public.sist_ventas_op_toallitas ot WHERE ot.id_orden_produccion = op.id), 0) +
                               COALESCE((SELECT SUM(tnt.cantidad_total) FROM public.sist_ventas_op_tnt tnt WHERE tnt.id_orden_produccion = op.id), 0) +
                               COALESCE((SELECT SUM(sp.cantidad_total) FROM public.sist_ventas_op_spunlace sp WHERE sp.id_orden_produccion = op.id), 0)
                           ) as cantidad_total_orden
                      FROM public.sist_ventas_orden_produccion op
                      WHERE op.id IN ($ordenesPlaceholder)
                      ORDER BY op.fecha_orden DESC";

            $stmt = $this->repository->getConexion()->prepare($sql);
            $stmt->execute($ordenesIds);
            $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $ordenesConDetalles = [];
            foreach ($ordenes as $orden) {
                $orden['productos'] = $this->obtenerProductosOrden($orden['id']);
                $orden['progreso'] = $this->calcularProgresoOrden($orden);
                $ordenesConDetalles[] = $orden;
            }

            return $ordenesConDetalles;
        } catch (Exception $e) {
            error_log("Error obteniendo órdenes de producción: " . $e->getMessage());
            return [];
        }
    }

    private function obtenerProductosOrden($idOrden)
    {
        try {
            $sql = "
                -- Toallitas
                SELECT 
                    'toallitas' as tipo_producto,
                    ot.id,
                    ot.nombre,
                    ot.cantidad_total,
                    ot.id_producto,
                    ot.id_producto_produccion,
                    prod.tipoproducto,
                    pp.cantidad as cantidad_asignada_produccion,
                    '' as gramatura,
                    0 as largura_metros,
                    0 as longitud_bobina,
                    '' as color,
                    0 as peso_bobina,
                    0 as total_bobinas,
                    0 as pesominbobina,
                    '' as acabado,
                    (SELECT COUNT(*) FROM public.sist_prod_stock stock 
                     WHERE stock.id_orden_produccion = ot.id_orden_produccion 
                     AND stock.estado = 'en stock'
                     AND (stock.nombre_producto = ot.nombre 
                          OR stock.nombre_producto = (SELECT descripcion FROM public.sist_ventas_pres_product WHERE id = pp.id_producto))
                    ) as cantidad_stock_real
                FROM public.sist_ventas_op_toallitas ot
                LEFT JOIN public.sist_ventas_productos_produccion pp ON ot.id_producto_produccion = pp.id
                LEFT JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
                WHERE ot.id_orden_produccion = :id_orden_produccion
                
                UNION ALL
                
                -- TNT
                SELECT 
                    'tnt' as tipo_producto,
                    tnt.id,
                    CONCAT('TNT ', tnt.gramatura, 'g - ', tnt.color) as nombre,
                    tnt.cantidad_total,
                    tnt.id_producto,
                    tnt.id_producto_produccion,
                    prod.tipoproducto,
                    pp.cantidad as cantidad_asignada_produccion,
                    tnt.gramatura,
                    tnt.largura_metros,
                    tnt.longitud_bobina,
                    tnt.color,
                    tnt.peso_bobina,
                    tnt.total_bobinas,
                    tnt.pesominbobina,
                    '' as acabado,
                    (SELECT COALESCE(SUM(stock.peso_bruto), 0) FROM public.sist_prod_stock stock 
                     WHERE stock.id_orden_produccion = tnt.id_orden_produccion 
                     AND stock.estado = 'en stock'
                    ) as cantidad_stock_real
                FROM public.sist_ventas_op_tnt tnt
                LEFT JOIN public.sist_ventas_productos_produccion pp ON tnt.id_producto_produccion = pp.id
                LEFT JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
                WHERE tnt.id_orden_produccion = :id_orden_produccion
                
                UNION ALL
                
                -- Spunlace
                SELECT 
                    'spunlace' as tipo_producto,
                    sp.id,
                    CONCAT('Spunlace ', sp.gramatura, 'g - ', sp.color, COALESCE(CONCAT(' - ', sp.acabado), '')) as nombre,
                    sp.cantidad_total,
                    sp.id_producto,
                    sp.id_producto_produccion,
                    prod.tipoproducto,
                    pp.cantidad as cantidad_asignada_produccion,
                    sp.gramatura,
                    sp.largura_metros,
                    sp.longitud_bobina,
                    sp.color,
                    sp.peso_bobina,
                    sp.total_bobinas,
                    sp.pesominbobina,
                    sp.acabado,
                    (SELECT COALESCE(SUM(stock.peso_bruto), 0) FROM public.sist_prod_stock stock 
                     WHERE stock.id_orden_produccion = sp.id_orden_produccion 
                     AND stock.estado = 'en stock'
                    ) as cantidad_stock_real
                FROM public.sist_ventas_op_spunlace sp
                LEFT JOIN public.sist_ventas_productos_produccion pp ON sp.id_producto_produccion = pp.id
                LEFT JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
                WHERE sp.id_orden_produccion = :id_orden_produccion
                
                ORDER BY tipo_producto, id";

            $stmt = $this->repository->getConexion()->prepare($sql);
            $stmt->bindParam(':id_orden_produccion', $idOrden, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos de orden: " . $e->getMessage());
            return [];
        }
    }

    private function calcularProgresoOrden($orden)
    {
        try {
            $totalAsignado = 0;
            $totalCompletado = 0;

            foreach ($orden['productos'] as $producto) {
                if ($producto['tipo_producto'] === 'toallitas') {
                    $totalAsignado += $producto['cantidad_total'];
                    $totalCompletado += $producto['cantidad_stock_real'];
                } else {
                    $totalAsignado += $producto['cantidad_total'];
                    $totalCompletado += $producto['cantidad_stock_real'];
                }
            }

            $progreso = $totalAsignado > 0 ? ($totalCompletado / $totalAsignado) * 100 : 0;
            $orden['cantidad_completada_total'] = $totalCompletado;

            return $progreso;
        } catch (Exception $e) {
            error_log("Error calculando progreso: " . $e->getMessage());
            return 0;
        }
    }


    public function procesarFiltrosHistorial()
    {
        return [
            'id_venta' => trim($_GET['id_venta'] ?? ''),
            'cliente_historial' => trim($_GET['cliente_historial'] ?? ''),
            'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
            'id_usuario' => trim($_GET['id_usuario'] ?? '')
        ];
    }

    public function obtenerHistorialAcciones($filtros, $paginaActual)
    {
        try {
            $registrosPorPagina = 10;
            $idUsuarioActual = $_SESSION['id'] ?? null;
            $rolUsuario = $_SESSION['rol'] ?? '0';

            $resultado = $this->service->obtenerHistorialAccionesPaginado(
                $filtros,
                $registrosPorPagina,
                $paginaActual,
                $idUsuarioActual,
                $rolUsuario
            );

            foreach ($resultado['acciones'] as &$accion) {
                $accion['fecha_accion_formateada'] = date('d/m/Y H:i', strtotime($accion['fecha_accion']));
                $accion['accion_badge'] = $this->obtenerBadgeAccion($accion['accion']);
                $accion['estado_badge'] = $this->obtenerBadgeEstado($accion['estado_resultante']);
            }

            return [
                'historial' => $resultado['acciones'],
                'total' => $resultado['totalRegistros'],
                'total_paginas' => $resultado['totalPaginas'],
                'es_administrador' => $resultado['esAdministrador']
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo historial: " . $e->getMessage());
            return [
                'historial' => [],
                'total' => 0,
                'total_paginas' => 0,
                'es_administrador' => false
            ];
        }
    }

    private function obtenerBadgeAccion($accion)
    {
        switch ($accion) {
            case 'Crear':
                return ['class' => 'bg-success', 'icon' => 'fa-plus'];
            case 'Editar':
                return ['class' => 'bg-warning text-dark', 'icon' => 'fa-edit'];
            case 'Eliminar':
                return ['class' => 'bg-danger', 'icon' => 'fa-trash'];
            case 'Enviar al sector contable':
                return ['class' => 'bg-primary', 'icon' => 'fa-paper-plane'];
            case 'Actualizar transportadora':
                return ['class' => 'bg-info', 'icon' => 'fa-truck'];
            default:
                return ['class' => 'bg-secondary', 'icon' => 'fa-cog'];
        }
    }

    private function obtenerBadgeEstado($estado)
    {
        switch ($estado) {
            case 'Pendiente':
                return ['class' => 'bg-warning text-dark', 'icon' => 'fa-clock'];
            case 'En revision':
                return ['class' => 'bg-info', 'icon' => 'fa-eye'];
            case 'Aprobado':
                return ['class' => 'bg-success', 'icon' => 'fa-check'];
            case 'Rechazado':
                return ['class' => 'bg-danger', 'icon' => 'fa-times'];
            case 'Eliminado':
                return ['class' => 'bg-dark', 'icon' => 'fa-trash'];
            default:
                return ['class' => 'bg-secondary', 'icon' => 'fa-question'];
        }
    }

    public function generarUrlConParametros($archivo, $parametros)
    {
        $url = $this->urlBase . "secciones/ventas/" . $archivo;
        if (!empty($parametros)) {
            $url .= '?' . http_build_query(array_filter($parametros));
        }
        return $url;
    }

    public function obtenerUsuariosParaFiltro()
    {
        try {
            if (!$this->esAdministrador()) {
                return [];
            }

            $sql = "SELECT id, nombre FROM public.sist_ventas_usuario WHERE activo = true ORDER BY nombre";
            $stmt = $this->repository->getConexion()->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo usuarios para filtro: " . $e->getMessage());
            return [];
        }
    }
}
