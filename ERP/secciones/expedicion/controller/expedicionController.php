<?php
require_once 'repository/expedicionRepository.php';
require_once 'services/expedicionService.php';

date_default_timezone_set('America/Asuncion');
class ExpedicionController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new ExpedicionRepository($conexion);
        $this->service = new ExpedicionService($this->repository);
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
                case 'reservar_completo':
                    $this->reservarProductoCompleto($usuario);
                    break;
                case 'cancelar_reserva':
                    $this->cancelarReserva($usuario);
                    break;
                case 'obtener_productos_vendidos_cliente':
                    $this->obtenerProductosVendidosCliente();
                    break;
                case 'reservar_venta_completa':
                    $this->reservarVentaCompleta($usuario);
                    break;
                case 'marcar_item_completado':
                    $this->marcarItemComoCompletado($usuario);
                    break;
                case 'reactivar_item_completado':
                    $this->reactivarItemCompletado($usuario);
                    break;
                default:
                    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            }
        } catch (Exception $e) {
            error_log("Error en handleRequest (expedicion): " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
        }
        return true;
    }

    public function obtenerDatosVistaExpedicion($filtroCliente = '', $pagina = 1, $porPagina = 10)
    {
        try {
            $totalClientes = $this->repository->contarTotalClientesConVentas($filtroCliente);
            $totalPaginas = ceil($totalClientes / $porPagina);
            if ($pagina > $totalPaginas && $totalPaginas > 0) {
                $pagina = $totalPaginas;
            }
            $clientesConVentas = $this->repository->obtenerClientesConVentas($filtroCliente, $pagina, $porPagina);
            $rejillasDisponibles = $this->repository->obtenerRejillasDisponibles();
            $clientesConVentas = $this->service->enriquecerClientesConVentas($clientesConVentas);
            $rejillasDisponibles = $this->service->enriquecerRejillas($rejillasDisponibles);
            $estadisticasGlobales = $this->repository->obtenerEstadisticasProduccionExpedicion();
            return [
                'clientes_con_ventas' => $clientesConVentas,
                'rejillas_disponibles' => $rejillasDisponibles,
                'estadisticas_globales_produccion_expedicion' => $estadisticasGlobales,
                'total_clientes' => $totalClientes,
                'total_paginas' => $totalPaginas,
                'pagina_actual' => $pagina,
                'resultados_por_pagina' => $porPagina,
                'filtro_cliente' => $filtroCliente
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos vista expedición: " . $e->getMessage());
            return [
                'clientes_con_ventas' => [],
                'rejillas_disponibles' => [],
                'estadisticas_globales_produccion_expedicion' => [],
                'total_clientes' => 0,
                'total_paginas' => 0,
                'pagina_actual' => 1,
                'resultados_por_pagina' => $porPagina,
                'filtro_cliente' => $filtroCliente
            ];
        }
    }

    private function reservarProductoCompleto($usuario)
    {
        try {
            $idVenta = (int)($_POST['id_venta'] ?? 0);
            $idProductoPresupuesto = (int)($_POST['id_producto_presupuesto'] ?? 0);
            $idRejilla = (int)($_POST['id_rejilla'] ?? 0);
            $nombreProducto = $_POST['nombre_producto'] ?? '';
            $cliente = $_POST['cliente'] ?? '';
            if ($idVenta === 0 || $idProductoPresupuesto === 0 || $idRejilla === 0) {
                throw new Exception('Parámetros inválidos');
            }
            if (empty($nombreProducto)) {
                throw new Exception('Nombre de producto requerido');
            }
            if (empty($cliente)) {
                throw new Exception('Cliente requerido');
            }
            $productosCliente = $this->repository->obtenerProductosVendidosCliente($cliente);
            $producto = null;
            foreach ($productosCliente as $prod) {
                if ($prod['id_venta'] == $idVenta && $prod['id_producto_presupuesto'] == $idProductoPresupuesto) {
                    $producto = $prod;
                    break;
                }
            }
            if (!$producto) {
                throw new Exception('Producto no encontrado o ya está asignado a rejillas');
            }
            $productosEnriquecidos = $this->service->enriquecerProductosConProduccionExpedicion([$producto]);
            $productoEnriquecido = $productosEnriquecidos[0];
            $disponibleParaReservarUnidades = $productoEnriquecido['disponible_para_reservar_unidades'] ?? 0;
            $disponibleParaReservarKg = $productoEnriquecido['disponible_para_reservar_kg'] ?? 0;
            if ($disponibleParaReservarUnidades <= 0) {
                throw new Exception('No hay unidades disponibles para reservar. Este producto ya está completamente reservado o asignado a rejillas.');
            }
            $pesoUnitario = floatval($productoEnriquecido['peso_unitario_kg'] ?? 0);
            $esPañoOToallita = $this->esProductoPañoOToallita($nombreProducto);
            if ($pesoUnitario <= 0) {
                throw new Exception('No se puede determinar el peso unitario del producto. Verifique la configuración en sist_ventas_productos.');
            }
            $disponibleParaReservarKg = $disponibleParaReservarUnidades * $pesoUnitario;
            $datos = [
                'id_venta' => $idVenta,
                'id_producto_presupuesto' => $idProductoPresupuesto,
                'id_rejilla' => $idRejilla,
                'cantidad_asignar_unidades' => $disponibleParaReservarUnidades,
                'cantidad_asignar_kg' => $disponibleParaReservarKg,
                'peso_unitario' => $pesoUnitario,
                'nombre_producto' => $nombreProducto,
                'usuario' => $usuario,
                'cliente' => $cliente,
                'es_paño_o_toallita' => $esPañoOToallita
            ];
            $resultado = $this->service->procesarAsignacionCompleta($datos);
            if ($resultado['success']) {
                $resultado['unidades_asignadas'] = $disponibleParaReservarUnidades;
                $resultado['peso_total_asignado'] = $disponibleParaReservarKg;
                $resultado['peso_unitario'] = $pesoUnitario;
                $resultado['tipo_unidad'] = $this->determinarTipoUnidad($nombreProducto);
                if (isset($resultado['nombre_producto_guardado'])) {
                    $resultado['producto_guardado_en_rejilla'] = $resultado['nombre_producto_guardado'];
                    $tipoTexto = $this->obtenerTextoUnidad($nombreProducto);
                    $resultado['message'] .= " Asignadas {$disponibleParaReservarUnidades} {$tipoTexto} ({$disponibleParaReservarKg} kg) a la rejilla.";
                }
            }
            echo json_encode($resultado);
            if ($resultado['success']) {
                $tipoTexto = $this->obtenerTextoUnidad($nombreProducto);
                $this->logActividad(
                    'Reserva completa - Producto guardado en rejilla con unidades',
                    "Rejilla: $idRejilla - Cliente: $cliente - Producto: $nombreProducto",
                    $usuario,
                    "Unidades: {$disponibleParaReservarUnidades} {$tipoTexto} - Peso total: " . round($disponibleParaReservarKg, 2) . " kg - Peso unitario: " . round($pesoUnitario, 2) . " kg"
                );
            }
        } catch (Exception $e) {
            error_log("Error reservando producto completo: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function esProductoPañoOToallita($nombreProducto)
    {
        if (!$nombreProducto) return false;
        $nombreUpper = strtoupper($nombreProducto);
        return (strpos($nombreUpper, 'PAÑO') !== false ||
            strpos($nombreUpper, 'PAÑOS') !== false ||
            strpos($nombreUpper, 'TOALLITA') !== false ||
            strpos($nombreUpper, 'TOALLA') !== false);
    }

    private function determinarTipoUnidad($nombreProducto)
    {
        $nombreUpper = strtoupper($nombreProducto);
        if (strpos($nombreUpper, 'TNT') !== false || strpos($nombreUpper, 'SPUNLACE') !== false) {
            return 'bobinas';
        } elseif (strpos($nombreUpper, 'TOALLITA') !== false || strpos($nombreUpper, 'TOALLA') !== false || strpos($nombreUpper, 'PAÑO') !== false || strpos($nombreUpper, 'PAÑOS') !== false) {
            return 'cajas';
        } else {
            return 'unidades';
        }
    }

    private function obtenerTextoUnidad($nombreProducto)
    {
        $tipo = $this->determinarTipoUnidad($nombreProducto);

        switch ($tipo) {
            case 'bobinas':
                return 'bobinas';
            case 'cajas':
                $nombreUpper = strtoupper($nombreProducto);
                if (strpos($nombreUpper, 'PAÑO') !== false) {
                    return 'cajas de paños';
                } elseif (strpos($nombreUpper, 'TOALLITA') !== false) {
                    return 'cajas de toallitas';
                }
                return 'cajas';
            default:
                return 'unidades';
        }
    }

    private function cancelarReserva($usuario)
    {
        try {
            $idAsignacion = (int)($_POST['id_asignacion'] ?? 0);

            if ($idAsignacion === 0) {
                throw new Exception('ID de asignación inválido');
            }

            $resultado = $this->service->cancelarReserva($idAsignacion, $usuario);
            echo json_encode($resultado);

            if ($resultado['success']) {
                $this->logActividad(
                    'Cancelar reserva - Movimiento reseteado a NULL',
                    "Asignación: $idAsignacion",
                    $usuario
                );
            }
        } catch (Exception $e) {
            error_log("Error cancelando reserva: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }


    private function marcarItemComoCompletado($usuario)
    {
        try {
            $idAsignacion = (int)($_POST['id_asignacion'] ?? 0);
            $observaciones = $_POST['observaciones'] ?? null;

            if ($idAsignacion === 0) {
                throw new Exception('ID de asignación inválido');
            }

            $resultado = $this->service->marcarItemComoCompletado($idAsignacion, $observaciones, $usuario);
            echo json_encode($resultado);

            if ($resultado['success']) {
                $this->logActividad(
                    'Marcar item como completado (estado_asignacion = completada)',
                    "Asignación: $idAsignacion",
                    $usuario,
                    $observaciones ? "Observaciones: $observaciones" : null
                );
            }
        } catch (Exception $e) {
            error_log("Error marcando item como completado: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function reactivarItemCompletado($usuario)
    {
        try {
            $idAsignacion = (int)($_POST['id_asignacion'] ?? 0);
            $observaciones = $_POST['observaciones'] ?? null;
            if ($idAsignacion === 0) {
                throw new Exception('ID de asignación inválido');
            }
            $resultado = $this->service->reactivarItemCompletado($idAsignacion, $observaciones, $usuario);
            echo json_encode($resultado);
            if ($resultado['success']) {
                $this->logActividad(
                    'Reactivar item completado (estado_asignacion = activa)',
                    "Asignación: $idAsignacion",
                    $usuario,
                    $observaciones ? "Observaciones: $observaciones" : null
                );
            }
        } catch (Exception $e) {
            error_log("Error reactivando item completado: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function obtenerProductosVendidosCliente()
    {
        try {
            $cliente = $_POST['cliente'] ?? '';
            if (empty($cliente)) {
                throw new Exception('Parámetro cliente requerido');
            }
            error_log("=== CONTROLLER: Solicitando productos para cliente: $cliente ===");
            $productos = $this->repository->obtenerProductosVendidosCliente($cliente);
            error_log("=== CONTROLLER: Productos obtenidos: " . count($productos) . " ===");

            if (empty($productos)) {
                error_log("=== CONTROLLER: No se encontraron productos pendientes para este cliente ===");
                echo json_encode([
                    'mensaje' => 'No se encontraron productos pendientes para este cliente'
                ]);
                return;
            }

            $productosEnriquecidos = $this->service->enriquecerProductosConProduccionExpedicion($productos);
            $ventasAgrupadas = [];

            foreach ($productosEnriquecidos as $producto) {
                $idVenta = $producto['id_venta'];
                if (!isset($ventasAgrupadas[$idVenta])) {
                    $ventasAgrupadas[$idVenta] = [
                        'id_venta' => $idVenta,
                        'cliente' => $producto['cliente'],
                        'fecha_venta' => $producto['fecha_venta'],
                        'productos' => [],
                        'total_productos' => 0,
                        'resumen_produccion_expedicion' => [
                            'peso_total_vendido' => 0,
                            'peso_total_produccion' => 0,
                            'peso_total_expedicion' => 0,
                            'peso_total_pendiente' => 0,
                            'unidades_total_vendidas' => 0,
                            'unidades_total_produccion' => 0,
                            'unidades_total_expedicion' => 0,
                            'unidades_total_pendientes' => 0
                        ]
                    ];
                }
                $ventasAgrupadas[$idVenta]['productos'][] = $producto;
                $ventasAgrupadas[$idVenta]['total_productos']++;
                $resumen = &$ventasAgrupadas[$idVenta]['resumen_produccion_expedicion'];
                $resumen['peso_total_vendido'] += floatval($producto['peso_total_vendido_kg'] ?? 0);
                $resumen['peso_total_produccion'] += floatval($producto['peso_asignado_produccion_kg'] ?? 0);
                $resumen['peso_total_expedicion'] += floatval($producto['peso_asignado_expedicion_kg'] ?? 0);
                $resumen['peso_total_pendiente'] += floatval($producto['peso_pendiente_kg'] ?? 0);
                $resumen['unidades_total_vendidas'] += intval($producto['cantidad_unidades_vendidas'] ?? 0);
                $resumen['unidades_total_produccion'] += intval($producto['unidades_asignadas_produccion'] ?? 0);
                $resumen['unidades_total_expedicion'] += intval($producto['unidades_asignadas_expedicion'] ?? 0);
                $resumen['unidades_total_pendientes'] += intval($producto['unidades_pendientes'] ?? 0);
            }
            $ventasFinales = array_values($ventasAgrupadas);
            error_log("=== CONTROLLER: Ventas agrupadas finales: " . count($ventasFinales) . " ===");
            echo json_encode($ventasFinales);
        } catch (Exception $e) {
            error_log("Error obteniendo productos vendidos: " . $e->getMessage());
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }


    public function logActividad($accion, $contexto = null, $usuario = null, $detalles = null)
    {
        $usuario = $usuario ?? $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $mensaje = "EXPEDICION-SIMPLIFICADO-v4.5-SOLO-ESTADO-ASIGNACION - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";
        if ($contexto) {
            $mensaje .= " | Contexto: {$contexto}";
        }
        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }
        error_log($mensaje);
    }

    public function obtenerConfiguracion()
    {
        return [
            'items_por_pagina' => 10,
            'max_items_por_asignacion' => 1000,
            'formatos_fecha' => [
                'corta' => 'd/m/Y',
                'completa' => 'd/m/Y H:i',
                'sistema' => 'Y-m-d H:i:s'
            ],
            'estados_rejilla' => ['disponible', 'ocupada', 'llena', 'mantenimiento'],
            'tipos_asignacion' => ['reserva_presupuesto'],
            'tipos_unidad' => ['bobinas', 'cajas', 'unidades'],
            'version_sistema' => '4.5',
            'sistema_simplificado' => true,
            'calculo_peso_exacto' => true,
            'sin_stock_fisico' => true,
            'diferenciacion_produccion_expedicion' => true,
            'manejo_movimiento_en_rejillas' => true,
            'auto_ocultar_asignados' => true,
            'manejo_unidades_especificas' => true,
            'correccion_paños_toallitas' => true,
            'consultas_sincronizadas' => true,
            'debug_avanzado' => true,
            'solo_estado_asignacion' => true,
            'estados_asignacion_permitidos' => ['activa', 'completada'],
            'calculo_logica' => [
                'paños_toallitas' => 'cantidad_real × peso_unitario = peso_total',
                'bobinas' => 'peso_total ÷ peso_unitario = cantidad_bobinas',
                'otros' => 'cantidad_directa'
            ]
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


    private function reservarVentaCompleta($usuario)
    {
        try {
            $idVenta = (int)($_POST['id_venta'] ?? 0);
            $idRejilla = (int)($_POST['id_rejilla'] ?? 0);
            $cliente = $_POST['cliente'] ?? '';
            $productosData = $_POST['productos_data'] ?? '';
            $excesoCapacidad = floatval($_POST['exceso_capacidad'] ?? 0);

            if ($idVenta === 0 || $idRejilla === 0) {
                throw new Exception('Parámetros inválidos');
            }

            if (empty($cliente)) {
                throw new Exception('Cliente requerido');
            }

            if (empty($productosData)) {
                throw new Exception('Datos de productos requeridos');
            }

            $productos = json_decode($productosData, true);
            if (!is_array($productos) || empty($productos)) {
                throw new Exception('Datos de productos inválidos');
            }

            $pesoTotalRequerido = 0;
            $unidadesTotales = 0;
            foreach ($productos as $producto) {
                $pesoTotalRequerido += floatval($producto['peso_total'] ?? 0);
                $unidadesTotales += intval($producto['unidades_disponibles'] ?? 0);
            }

            if ($pesoTotalRequerido <= 0) {
                throw new Exception('Peso total inválido');
            }

            $verificacionCapacidad = $this->repository->verificarCapacidadParaReserva(
                $idRejilla,
                $pesoTotalRequerido
            );

            $datos = [
                'id_venta' => $idVenta,
                'id_rejilla' => $idRejilla,
                'productos' => $productos,
                'peso_total_requerido' => $pesoTotalRequerido,
                'unidades_totales' => $unidadesTotales,
                'usuario' => $usuario,
                'cliente' => $cliente,
                'exceso_capacidad' => $excesoCapacidad,
                'capacidad_validada' => $verificacionCapacidad['valida'] ?? false
            ];

            $resultado = $this->service->procesarAsignacionVentaCompleta($datos);

            echo json_encode($resultado);

            if ($resultado['success']) {
                $detallesLog = "Productos: " . count($productos) . " - Unidades totales: $unidadesTotales - Peso total: " . round($pesoTotalRequerido, 2) . " kg";

                if ($excesoCapacidad > 0) {
                    $detallesLog .= " - ⚠️ EXCESO DE CAPACIDAD: " . round($excesoCapacidad, 1) . " kg";
                }

                $this->logActividad(
                    'Reserva venta completa - Todos los productos asignados' . ($excesoCapacidad > 0 ? ' (CON EXCESO)' : ''),
                    "Venta: $idVenta - Rejilla: $idRejilla - Cliente: $cliente",
                    $usuario,
                    $detallesLog
                );
            }
        } catch (Exception $e) {
            error_log("Error reservando venta completa: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}

if (!file_exists('repository/expedicionRepository.php') || !file_exists('services/expedicionService.php')) {
    die("Error: Faltan archivos del sistema MVC. Verifique que existan los archivos repository/expedicionRepository.php y services/expedicionService.php");
}

$controller = new ExpedicionController($conexion, $url_base);

if (!$controller->validarPermisos()) {
    header('Location: ' . $url_base . 'index.php');
    exit;
}

if ($controller->handleRequest()) {
    exit();
}
