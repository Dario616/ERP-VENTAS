<?php

class ExpedicionRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    private function obtenerFiltrosProductosPendientes()
    {
        return "
        AND vp.estado NOT IN ('Finalizado', 'Finalizado Manualmente')
        
        AND NOT EXISTS (
            SELECT 1 FROM sist_ventas_productos_produccion prod 
            WHERE prod.id_venta = vp.id 
            AND prod.id_producto = vpp.id 
            AND prod.movimiento = 'EN REJILLAS'
        )
        AND NOT EXISTS (
            SELECT 1 FROM sist_ventas_productos_expedicion exp 
            WHERE exp.id_venta = vp.id 
            AND exp.id_producto = vpp.id 
            AND exp.movimiento = 'EN REJILLAS'
        )
        AND (
            COALESCE(prod_info.cantidad_produccion, 0) + COALESCE(exp_info.cantidad_expedicion, 0) >= vpp.cantidad
        )
        AND (
            EXISTS (
                SELECT 1 FROM sist_ventas_productos_produccion prod 
                WHERE prod.id_venta = vp.id 
                AND prod.id_producto = vpp.id
                AND prod.movimiento = 'PENDIENTE'
            )
            OR EXISTS (
                SELECT 1 FROM sist_ventas_productos_expedicion exp 
                WHERE exp.id_venta = vp.id 
                AND exp.id_producto = vpp.id
                AND exp.movimiento = 'PENDIENTE'
            )
        )
    ";
    }

    private function obtenerSubqueriesComunes()
    {
        return "
            LEFT JOIN (
                SELECT 
                    id_venta,
                    id_producto,
                    SUM(cantidad) as cantidad_produccion
                FROM sist_ventas_productos_produccion 
                WHERE movimiento IS NULL OR movimiento != 'EN REJILLAS'
                GROUP BY id_venta, id_producto
            ) prod_info ON vp.id = prod_info.id_venta AND vpp.id = prod_info.id_producto

            LEFT JOIN (
                SELECT 
                    id_venta,
                    id_producto,
                    SUM(cantidad) as cantidad_expedicion
                FROM sist_ventas_productos_expedicion 
                WHERE movimiento IS NULL OR movimiento != 'EN REJILLAS'
                GROUP BY id_venta, id_producto
            ) exp_info ON vp.id = exp_info.id_venta AND vpp.id = exp_info.id_producto
        ";
    }

    public function contarTotalClientesConVentas($filtroCliente = null)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM (
                        SELECT vp.cliente
                        FROM sist_ventas_presupuesto vp
                        INNER JOIN sist_ventas_pres_product vpp ON vp.id = vpp.id_presupuesto
                        LEFT JOIN sist_ventas_productos svp ON vpp.id_producto = svp.id

                        " . $this->obtenerSubqueriesComunes() . "
                        WHERE vp.cliente IS NOT NULL
                        AND vp.cliente != ''
                        AND vp.cliente != 'AMERICA TNT'
                        " . $this->obtenerFiltrosProductosPendientes();

            $params = [];
            if (!empty($filtroCliente)) {
                $sql .= " AND LOWER(vp.cliente) LIKE LOWER(:filtroCliente)";
                $params[':filtroCliente'] = '%' . $filtroCliente . '%';
            }

            $sql .= " GROUP BY vp.cliente
                      HAVING COUNT(DISTINCT vpp.id) > 0
                    ) AS clientes_con_ventas";

            $stmt = $this->conexion->prepare($sql);

            if (!empty($filtroCliente)) {
                $stmt->bindParam(':filtroCliente', $params[':filtroCliente'], PDO::PARAM_STR);
            }

            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['total'];
        } catch (Exception $e) {
            error_log("Error contando clientes con ventas: " . $e->getMessage());
            return 0;
        }
    }

    public function obtenerClientesConVentas($filtroCliente = null, $pagina = 1, $porPagina = 10)
    {
        try {
            $offset = ($pagina - 1) * $porPagina;

            $sql = "SELECT
                        vp.cliente AS nombre,
                        COUNT(DISTINCT vp.id) AS total_ventas,
                        COUNT(DISTINCT vpp.id) AS total_productos,
                        
                        SUM(
                            CASE 
                                WHEN UPPER(vpp.descripcion) LIKE '%PAÑO%' OR UPPER(vpp.descripcion) LIKE '%PAÑOS%' OR 
                                     UPPER(vpp.descripcion) LIKE '%TOALLITA%' OR UPPER(vpp.descripcion) LIKE '%TOALLA%' THEN 
                                    vpp.cantidad * COALESCE(svp.cantidad, 1)
                                ELSE 
                                    vpp.cantidad
                            END
                        ) AS total_cantidad_vendida,
                        
                        SUM(
                            CASE 
                                WHEN UPPER(vpp.descripcion) LIKE '%PAÑO%' OR UPPER(vpp.descripcion) LIKE '%PAÑOS%' OR 
                                     UPPER(vpp.descripcion) LIKE '%TOALLITA%' OR UPPER(vpp.descripcion) LIKE '%TOALLA%' THEN 
                                    vpp.cantidad
                                WHEN COALESCE(svp.cantidad, 0) > 0 THEN 
                                    ROUND(vpp.cantidad / svp.cantidad, 0)
                                ELSE vpp.cantidad 
                            END
                        ) AS total_unidades_vendidas,
                        
                        MAX(vp.fecha_venta) AS ultima_venta
                    FROM
                        sist_ventas_presupuesto AS vp
                    INNER JOIN
                        sist_ventas_pres_product AS vpp ON vp.id = vpp.id_presupuesto
                    LEFT JOIN sist_ventas_productos svp ON vpp.id_producto = svp.id

                    " . $this->obtenerSubqueriesComunes() . "
                    WHERE
                        vp.cliente IS NOT NULL
                        AND vp.cliente != ''
                        AND vp.cliente != 'AMERICA TNT'
                        " . $this->obtenerFiltrosProductosPendientes();

            $params = [];
            if (!empty($filtroCliente)) {
                $sql .= " AND LOWER(vp.cliente) LIKE LOWER(:filtroCliente)";
                $params[':filtroCliente'] = '%' . $filtroCliente . '%';
            }

            $sql .= " GROUP BY vp.cliente
                      HAVING COUNT(DISTINCT vpp.id) > 0
                      ORDER BY MAX(vp.fecha_venta) DESC
                      LIMIT :limit OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);

            if (!empty($filtroCliente)) {
                $stmt->bindParam(':filtroCliente', $params[':filtroCliente'], PDO::PARAM_STR);
            }

            $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo clientes con ventas: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerProductosVendidosCliente($cliente)
    {
        try {
            error_log("=== DEBUG: Buscando productos para cliente: $cliente ===");

            $sql = "SELECT 
                        vp.id as id_venta,
                        vp.cliente,
                        vp.fecha_venta,
                        vpp.id as id_producto_presupuesto,
                        vpp.descripcion as nombre_producto,
                        vpp.tipoproducto as tipo_producto,
                        
                        CASE 
                            WHEN UPPER(vpp.descripcion) LIKE '%PAÑO%' OR UPPER(vpp.descripcion) LIKE '%PAÑOS%' OR 
                                 UPPER(vpp.descripcion) LIKE '%TOALLITA%' OR UPPER(vpp.descripcion) LIKE '%TOALLA%' THEN 
                                vpp.cantidad * COALESCE(svp.cantidad, 1)
                            ELSE 
                                vpp.cantidad
                        END as peso_total_vendido_kg,
                        
                        vpp.unidadmedida,
                        vpp.precio,
                        vpp.total,
                        
                        COALESCE(svp.cantidad, 0) as peso_unitario_kg,
                        
                        CASE 
                            WHEN UPPER(vpp.descripcion) LIKE '%PAÑO%' OR UPPER(vpp.descripcion) LIKE '%PAÑOS%' OR 
                                 UPPER(vpp.descripcion) LIKE '%TOALLITA%' OR UPPER(vpp.descripcion) LIKE '%TOALLA%' THEN 
                                vpp.cantidad
                            WHEN COALESCE(svp.cantidad, 0) > 0 THEN 
                                ROUND(vpp.cantidad / svp.cantidad, 0)
                            ELSE vpp.cantidad 
                        END as cantidad_unidades_vendidas,
                        
                        CASE 
                            WHEN UPPER(vpp.descripcion) LIKE '%PAÑO%' OR UPPER(vpp.descripcion) LIKE '%PAÑOS%' OR 
                                 UPPER(vpp.descripcion) LIKE '%TOALLITA%' OR UPPER(vpp.descripcion) LIKE '%TOALLA%' THEN 
                                CASE 
                                    WHEN COALESCE(prod_info.cantidad_produccion, 0) > 0 THEN
                                        CASE 
                                            WHEN prod_info.cantidad_produccion <= vpp.cantidad THEN 
                                                prod_info.cantidad_produccion * COALESCE(svp.cantidad, 1)
                                            ELSE 
                                                prod_info.cantidad_produccion
                                        END
                                    ELSE 0 
                                END
                            ELSE 
                                COALESCE(prod_info.cantidad_produccion, 0)
                        END as peso_asignado_produccion_kg,
                        
                        CASE 
                            WHEN UPPER(vpp.descripcion) LIKE '%PAÑO%' OR UPPER(vpp.descripcion) LIKE '%PAÑOS%' OR 
                                 UPPER(vpp.descripcion) LIKE '%TOALLITA%' OR UPPER(vpp.descripcion) LIKE '%TOALLA%' THEN 
                                CASE 
                                    WHEN COALESCE(prod_info.cantidad_produccion, 0) > 0 THEN
                                        CASE 
                                            WHEN prod_info.cantidad_produccion <= vpp.cantidad THEN 
                                                ROUND(prod_info.cantidad_produccion, 0)
                                            ELSE 
                                                ROUND(prod_info.cantidad_produccion / COALESCE(svp.cantidad, 1), 0)
                                        END
                                    ELSE 0 
                                END
                            ELSE 
                                CASE 
                                    WHEN COALESCE(svp.cantidad, 0) > 0 AND COALESCE(prod_info.cantidad_produccion, 0) > 0 THEN 
                                        ROUND(prod_info.cantidad_produccion / svp.cantidad, 0)
                                    ELSE 0
                                END
                        END as unidades_asignadas_produccion,
                        
                        CASE 
                            WHEN UPPER(vpp.descripcion) LIKE '%PAÑO%' OR UPPER(vpp.descripcion) LIKE '%PAÑOS%' OR 
                                 UPPER(vpp.descripcion) LIKE '%TOALLITA%' OR UPPER(vpp.descripcion) LIKE '%TOALLA%' THEN 
                                CASE 
                                    WHEN COALESCE(exp_info.cantidad_expedicion, 0) > 0 THEN
                                        CASE 
                                            WHEN exp_info.cantidad_expedicion <= vpp.cantidad THEN 
                                                exp_info.cantidad_expedicion * COALESCE(svp.cantidad, 1)
                                            ELSE 
                                                exp_info.cantidad_expedicion
                                        END
                                    ELSE 0 
                                END
                            ELSE 
                                COALESCE(exp_info.cantidad_expedicion, 0)
                        END as peso_asignado_expedicion_kg,
                        
                        CASE 
                            WHEN UPPER(vpp.descripcion) LIKE '%PAÑO%' OR UPPER(vpp.descripcion) LIKE '%PAÑOS%' OR 
                                 UPPER(vpp.descripcion) LIKE '%TOALLITA%' OR UPPER(vpp.descripcion) LIKE '%TOALLA%' THEN 
                                CASE WHEN COALESCE(svp.cantidad, 0) > 0 AND COALESCE(exp_info.cantidad_expedicion, 0) > 0 THEN
                                    ROUND(exp_info.cantidad_expedicion / svp.cantidad, 0)
                                ELSE 0 END
                            WHEN COALESCE(svp.cantidad, 0) > 0 AND COALESCE(exp_info.cantidad_expedicion, 0) > 0 THEN 
                                ROUND(exp_info.cantidad_expedicion / svp.cantidad, 0)
                            ELSE 0
                        END as unidades_asignadas_expedicion,
                        
                        COALESCE(asig_info.items_asignados, 0) as items_asignados,
                        COALESCE(asig_info.peso_asignado_rejillas, 0) as peso_asignado_rejillas,
                        COALESCE(asig_info.cantidad_reservada, 0) as cantidad_reservada,
                        COALESCE(asig_info.unidades_reservadas, 0) as unidades_reservadas
                        
                    FROM sist_ventas_presupuesto vp
                    INNER JOIN sist_ventas_pres_product vpp ON vp.id = vpp.id_presupuesto
                    LEFT JOIN sist_ventas_productos svp ON vpp.id_producto = svp.id

                    " . $this->obtenerSubqueriesComunes() . "
                    
                    LEFT JOIN (
                        SELECT 
                            id_venta,
                            id_producto_presupuesto,
                            COUNT(*) as items_asignados,
                            SUM(peso_asignado) as peso_asignado_rejillas,
                            SUM(cantidad_reservada) as cantidad_reservada,
                            SUM(COALESCE(cant_uni, 0)) as unidades_reservadas
                        FROM sist_rejillas_asignaciones 
                        WHERE estado_asignacion = 'activa'
                        AND tipo_origen = 'reserva_presupuesto'
                        GROUP BY id_venta, id_producto_presupuesto
                    ) asig_info ON vp.id = asig_info.id_venta 
                                AND vpp.id = asig_info.id_producto_presupuesto
                    
                    WHERE vp.cliente = :cliente
                    " . $this->obtenerFiltrosProductosPendientes() . "
                    ORDER BY vp.fecha_venta DESC, vpp.descripcion ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':cliente', $cliente, PDO::PARAM_STR);
            $stmt->execute();

            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("=== DEBUG: Productos encontrados para $cliente: " . count($productos) . " ===");

            return $productos;
        } catch (Exception $e) {
            error_log("Error obteniendo productos vendidos del cliente: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerRejillasDisponibles()
    {
        try {
            $sql = "SELECT 
                    id,
                    numero_rejilla,
                    capacidad_maxima,
                    peso_actual,
                    estado,
                    (capacidad_maxima - peso_actual) as capacidad_disponible
                FROM sist_rejillas 
                WHERE estado IN ('disponible', 'ocupada')
                ORDER BY id ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            $rejillas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $rejillas;
        } catch (Exception $e) {
            error_log("Error obteniendo rejillas: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerRejillasDetalladas()
    {
        try {
            $sql = "SELECT 
    r.id,
    r.numero_rejilla,
    r.capacidad_maxima,
    r.peso_actual,
    r.estado,
    r.fecha_creacion,
    r.fecha_actualizacion,
    r.observaciones,
    r.ubicacion,
    r.descripcion,
    (r.capacidad_maxima - r.peso_actual) as capacidad_disponible,
    
    COALESCE(COUNT(ra.id), 0) as total_items_asignados,
    COALESCE(SUM(ra.cantidad_asignada), 0) as total_productos_asignados,
    COALESCE(SUM(ra.peso_asignado), 0) as peso_total_asignado,
    COALESCE(SUM(ra.cant_uni), 0) as total_unidades_asignadas,
    COUNT(DISTINCT ra.cliente) as clientes_unicos,
    MAX(ra.fecha_asignacion) as ultima_asignacion,
    
    COALESCE(SUM(
        CASE 
            WHEN stock_info.cantidad_total_producida_historica > 0 AND stock_info.peso_unitario > 0 THEN 
                stock_info.cantidad_total_producida_historica * stock_info.peso_unitario
            ELSE 0 
        END
    ), 0) as peso_total_producido,
    
    CASE 
        WHEN r.estado = 'disponible' THEN 'Disponible para asignaciones'
        WHEN r.estado = 'ocupada' THEN 'En uso con espacio disponible'
        WHEN r.estado = 'llena' THEN 'Capacidad máxima alcanzada'
        WHEN r.estado = 'mantenimiento' THEN 'Fuera de servicio por mantenimiento'
        ELSE 'Estado desconocido'
    END as descripcion_estado
    
FROM sist_rejillas r
        LEFT JOIN sist_rejillas_asignaciones ra ON r.id = ra.id_rejilla 
            AND ra.estado_asignacion = 'activa'
        
        LEFT JOIN (
            SELECT 
                rs.id_venta,
                rs.cliente as cliente_reserva,
                rs.nombre_producto,
                
                SUM(rs.cantidad_reservada + COALESCE(rs.cantidad_despachada, 0)) as cantidad_total_producida_historica,
                
                AVG(svp.cantidad) as peso_unitario
                
            FROM reservas_stock rs
            INNER JOIN stock_agregado sa ON rs.id_stock_agregado = sa.id
            LEFT JOIN sist_ventas_productos svp ON sa.nombre_producto = svp.descripcion
            GROUP BY rs.id_venta, rs.cliente, rs.nombre_producto
        ) stock_info ON ra.id_venta = stock_info.id_venta 
                    AND COALESCE(ra.cliente, '') = COALESCE(stock_info.cliente_reserva, '')
                    AND ra.nombre_producto = stock_info.nombre_producto
        
        GROUP BY r.id, r.numero_rejilla, r.capacidad_maxima, r.peso_actual, r.estado, 
                 r.fecha_creacion, r.fecha_actualizacion, r.observaciones, r.ubicacion
        ORDER BY r.id ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo rejillas detalladas: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerEstadisticasRejillas()
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_rejillas,
                        COUNT(CASE WHEN estado = 'disponible' THEN 1 END) as disponibles,
                        COUNT(CASE WHEN estado = 'ocupada' THEN 1 END) as ocupadas,
                        COUNT(CASE WHEN estado = 'llena' THEN 1 END) as llenas,
                        COUNT(CASE WHEN estado = 'mantenimiento' THEN 1 END) as mantenimiento,
                        COUNT(CASE WHEN estado = 'fuera_servicio' THEN 1 END) as fuera_servicio,
                        
                        SUM(capacidad_maxima) as capacidad_total,
                        SUM(peso_actual) as peso_total_actual,
                        SUM(capacidad_maxima - peso_actual) as capacidad_total_disponible,
                        
                        ROUND(AVG(peso_actual / capacidad_maxima * 100), 1) as porcentaje_promedio_uso,
                        ROUND(SUM(peso_actual) / SUM(capacidad_maxima) * 100, 1) as porcentaje_global_uso,
                        
                        COUNT(CASE WHEN (peso_actual / capacidad_maxima * 100) > 85 AND estado != 'llena' THEN 1 END) as alta_ocupacion,
                        
                        COUNT(CASE WHEN (peso_actual / capacidad_maxima * 100) < 25 AND peso_actual > 0 THEN 1 END) as baja_ocupacion,
                        
                        CASE 
                            WHEN SUM(capacidad_maxima) > 0 THEN 
                                ROUND((SUM(peso_actual) / SUM(capacidad_maxima)) * 100, 1)
                            ELSE 0
                        END as eficiencia_almacen
                        
                    FROM sist_rejillas";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

            $sqlAsignaciones = "SELECT 
                                    COUNT(*) as total_asignaciones_activas,
                                    COUNT(DISTINCT id_rejilla) as rejillas_con_asignaciones,
                                    SUM(peso_asignado) as peso_total_asignado,
                                    COUNT(*) as total_reservas_presupuesto,
                                    SUM(cantidad_reservada) as total_productos_reservados,
                                    COUNT(DISTINCT ra.id_venta) as ventas_con_asignaciones,
                                    COUNT(DISTINCT COALESCE(ra.cliente, '')) as clientes_con_asignaciones,
                                    
                                    SUM(COALESCE(ra.cant_uni, 0)) as total_unidades_asignadas,
                                    COUNT(CASE WHEN ra.cant_uni > 0 THEN 1 END) as asignaciones_con_unidades,
                                    ROUND(AVG(COALESCE(ra.cant_uni, 0)), 0) as promedio_unidades_por_asignacion,
                                    
                                    ROUND(AVG(peso_asignado), 2) as promedio_peso_asignacion,
                                    
                                    COUNT(CASE WHEN DATE(ra.fecha_asignacion) = CURRENT_DATE THEN 1 END) as asignaciones_hoy,
                                    
                                    COUNT(CASE WHEN ra.fecha_asignacion >= CURRENT_DATE - INTERVAL '7 days' THEN 1 END) as asignaciones_semana
                                    
                                FROM sist_rejillas_asignaciones ra
                                WHERE ra.estado_asignacion = 'activa'
                                AND ra.tipo_origen = 'reserva_presupuesto'";

            $stmtAsignaciones = $this->conexion->prepare($sqlAsignaciones);
            $stmtAsignaciones->execute();
            $estadisticasAsignaciones = $stmtAsignaciones->fetch(PDO::FETCH_ASSOC);

            return array_merge($estadisticas, $estadisticasAsignaciones);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas de rejillas: " . $e->getMessage());
            return [];
        }
    }

    public function crearAsignacionPresupuesto($datos)
    {
        try {
            $pesoExacto = $this->obtenerPesoExactoProducto(
                $datos['nombre_producto'],
                $datos['cantidad_asignar_kg']
            );

            $tipoUnidad = $this->determinarTipoUnidad($datos['nombre_producto']);

            $this->beginTransaction();

            $sql = "INSERT INTO sist_rejillas_asignaciones 
                   (id_rejilla, id_venta, id_producto_presupuesto, 
                    peso_asignado, usuario_asignacion, cantidad_asignada, 
                    cantidad_reservada, tipo_origen, observaciones, nombre_producto,
                    cant_uni, peso_unitario, tipo_unidad, estado_asignacion, cliente)
                   VALUES (?, ?, ?, ?, ?, ?, ?, 'reserva_presupuesto', ?, ?, ?, ?, ?, 'activa', ?)";

            $stmt = $this->conexion->prepare($sql);

            $observaciones = "Reserva completa por presupuesto - Peso exacto calculado - Unidades: {$datos['cantidad_asignar_unidades']} {$tipoUnidad}";

            $stmt->execute([
                $datos['id_rejilla'],
                $datos['id_venta'],
                $datos['id_producto_presupuesto'],
                $pesoExacto,
                $datos['usuario'],
                $datos['cantidad_asignar_kg'],
                $datos['cantidad_asignar_kg'],
                $observaciones,
                $datos['nombre_producto'],
                $datos['cantidad_asignar_unidades'],
                $datos['peso_unitario'],
                $tipoUnidad,
                $datos['cliente']
            ]);

            $idAsignacion = $this->conexion->lastInsertId();

            $this->actualizarMovimientoProduccion($datos['id_venta'], $datos['id_producto_presupuesto']);

            $this->actualizarMovimientoExpedicion($datos['id_venta'], $datos['id_producto_presupuesto']);

            $this->actualizarRejillaConReserva($datos['id_rejilla'], $pesoExacto);

            $this->commit();

            return [
                'id_asignacion' => $idAsignacion,
                'peso_exacto' => $pesoExacto,
                'unidades_asignadas' => $datos['cantidad_asignar_unidades'],
                'tipo_unidad' => $tipoUnidad,
                'nombre_producto_guardado' => $datos['nombre_producto'],
                'cliente_guardado' => $datos['cliente']
            ];
        } catch (Exception $e) {
            $this->rollBack();
            error_log("Error creando asignación por presupuesto: " . $e->getMessage());
            throw $e;
        }
    }

    private function determinarTipoUnidad($nombreProducto)
    {
        $nombreUpper = strtoupper($nombreProducto);

        if (strpos($nombreUpper, 'SPUNBOND') !== false || strpos($nombreUpper, 'SPUNLACE') !== false) {
            return 'bobinas';
        } elseif (strpos($nombreUpper, 'TOALLITA') !== false || strpos($nombreUpper, 'TOALLA') !== false || strpos($nombreUpper, 'PAÑO') !== false || strpos($nombreUpper, 'PAÑOS') !== false) {
            return 'cajas';
        } else {
            return 'unidades';
        }
    }

    public function crearAsignacionVentaCompleta($datos)
    {
        try {
            $this->beginTransaction();

            $asignacionesCreadas = [];
            $pesoTotalAsignado = 0;
            $productosAsignados = 0;
            $excesoCapacidad = $datos['exceso_capacidad'] ?? 0;

            foreach ($datos['productos'] as $producto) {
                $pesoExacto = $this->obtenerPesoExactoProducto(
                    $producto['nombre_producto'],
                    $producto['peso_total']
                );

                $tipoUnidad = $this->determinarTipoUnidad($producto['nombre_producto']);

                $observaciones = "Reserva por venta completa #{$datos['id_venta']} - Unidades: {$producto['unidades_disponibles']} {$tipoUnidad}";
                if ($excesoCapacidad > 0) {
                    $excesoTexto = $excesoCapacidad >= 1000
                        ? round($excesoCapacidad / 1000, 1) . "t"
                        : round($excesoCapacidad, 1) . "kg";
                    $observaciones .= " - ⚠️ EXCESO: +{$excesoTexto}";
                }

                $sql = "INSERT INTO sist_rejillas_asignaciones 
                   (id_rejilla, id_venta, id_producto_presupuesto, 
                    peso_asignado, usuario_asignacion, cantidad_asignada, 
                    cantidad_reservada, tipo_origen, observaciones, nombre_producto,
                    cant_uni, peso_unitario, tipo_unidad, estado_asignacion, cliente)
                   VALUES (?, ?, ?, ?, ?, ?, ?, 'reserva_presupuesto', ?, ?, ?, ?, ?, 'activa', ?)";

                $stmt = $this->conexion->prepare($sql);

                $resultado = $stmt->execute([
                    $datos['id_rejilla'],
                    $datos['id_venta'],
                    $producto['id_producto_presupuesto'],
                    $pesoExacto,
                    $datos['usuario'],
                    $producto['peso_total'],
                    $producto['peso_total'],
                    $observaciones,
                    $producto['nombre_producto'],
                    $producto['unidades_disponibles'],
                    $producto['peso_unitario'],
                    $tipoUnidad,
                    $datos['cliente']
                ]);

                if (!$resultado) {
                    throw new Exception("Error creando asignación para producto: {$producto['nombre_producto']}");
                }

                $idAsignacion = $this->conexion->lastInsertId();
                $asignacionesCreadas[] = $idAsignacion;

                $this->actualizarMovimientoProduccion($datos['id_venta'], $producto['id_producto_presupuesto']);
                $this->actualizarMovimientoExpedicion($datos['id_venta'], $producto['id_producto_presupuesto']);

                $pesoTotalAsignado += $pesoExacto;
                $productosAsignados++;

                error_log("Producto asignado: {$producto['nombre_producto']} - {$producto['unidades_disponibles']} {$tipoUnidad} - {$pesoExacto} kg - Cliente: {$datos['cliente']}");
            }

            $this->actualizarRejillaConReservaSinLimite($datos['id_rejilla'], $pesoTotalAsignado);

            $this->commit();

            $mensajeLog = "Venta completa asignada: Venta #{$datos['id_venta']} - Cliente: {$datos['cliente']} - {$productosAsignados} productos - {$pesoTotalAsignado} kg total";
            if ($excesoCapacidad > 0) {
                $mensajeLog .= " - ⚠️ EXCESO: " . round($excesoCapacidad, 1) . " kg";
            }
            error_log($mensajeLog);

            return [
                'success' => true,
                'asignaciones_creadas' => $asignacionesCreadas,
                'productos_asignados' => $productosAsignados,
                'peso_total_asignado' => $pesoTotalAsignado,
                'exceso_capacidad' => $excesoCapacidad,
                'id_rejilla' => $datos['id_rejilla'],
                'id_venta' => $datos['id_venta'],
                'cliente_guardado' => $datos['cliente']
            ];
        } catch (Exception $e) {
            $this->rollBack();
            error_log("Error creando asignación de venta completa: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function actualizarMovimientoProduccion($idVenta, $idProductoPresupuesto)
    {
        try {
            $sql = "UPDATE sist_ventas_productos_produccion 
                   SET movimiento = 'EN REJILLAS'
                   WHERE id_venta = ? 
                   AND id_producto = ? 
                   AND (movimiento IS NULL OR movimiento != 'EN REJILLAS')";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$idVenta, $idProductoPresupuesto]);

            $rowsAffected = $stmt->rowCount();
            if ($rowsAffected > 0) {
                error_log("Actualizado movimiento producción: $rowsAffected registros para venta $idVenta, producto $idProductoPresupuesto");
            }
        } catch (Exception $e) {
            error_log("Error actualizando movimiento producción: " . $e->getMessage());
        }
    }

    private function actualizarMovimientoExpedicion($idVenta, $idProductoPresupuesto)
    {
        try {
            $sql = "UPDATE sist_ventas_productos_expedicion 
                   SET movimiento = 'EN REJILLAS'
                   WHERE id_venta = ? 
                   AND id_producto = ? 
                   AND (movimiento IS NULL OR movimiento != 'EN REJILLAS')";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$idVenta, $idProductoPresupuesto]);

            $rowsAffected = $stmt->rowCount();
            if ($rowsAffected > 0) {
                error_log("Actualizado movimiento expedición: $rowsAffected registros para venta $idVenta, producto $idProductoPresupuesto");
            }
        } catch (Exception $e) {
            error_log("Error actualizando movimiento expedición: " . $e->getMessage());
        }
    }

    private function resetearMovimientoProduccion($idVenta, $idProductoPresupuesto)
    {
        try {
            $sql = "UPDATE sist_ventas_productos_produccion 
                   SET movimiento = 'PENDIENTE'
                   WHERE id_venta = ? 
                   AND id_producto = ? 
                   AND movimiento = 'EN REJILLAS'";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$idVenta, $idProductoPresupuesto]);

            $rowsAffected = $stmt->rowCount();
            if ($rowsAffected > 0) {
                error_log("Reseteado movimiento producción: $rowsAffected registros para venta $idVenta, producto $idProductoPresupuesto");
            }
        } catch (Exception $e) {
            error_log("Error reseteando movimiento producción: " . $e->getMessage());
        }
    }

    private function resetearMovimientoExpedicion($idVenta, $idProductoPresupuesto)
    {
        try {
            $sql = "UPDATE sist_ventas_productos_expedicion 
                   SET movimiento = 'PENDIENTE'
                   WHERE id_venta = ? 
                   AND id_producto = ? 
                   AND movimiento = 'EN REJILLAS'";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$idVenta, $idProductoPresupuesto]);

            $rowsAffected = $stmt->rowCount();
            if ($rowsAffected > 0) {
                error_log("Reseteado movimiento expedición: $rowsAffected registros para venta $idVenta, producto $idProductoPresupuesto");
            }
        } catch (Exception $e) {
            error_log("Error reseteando movimiento expedición: " . $e->getMessage());
        }
    }

    private function obtenerPesoExactoProducto($nombreProducto, $cantidad)
    {
        try {
            $sql = "SELECT cantidad as peso_unitario 
                   FROM sist_ventas_productos 
                   WHERE LOWER(TRIM(descripcion)) = LOWER(TRIM(?))
                   LIMIT 1";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$nombreProducto]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado && $resultado['peso_unitario'] > 0) {
                return floatval($cantidad);
            } else {
                return $this->calcularPesoEstimadoFallback($nombreProducto, $cantidad);
            }
        } catch (Exception $e) {
            error_log("Error obteniendo peso exacto: " . $e->getMessage());
            return $this->calcularPesoEstimadoFallback($nombreProducto, $cantidad);
        }
    }

    private function calcularPesoEstimadoFallback($nombreProducto, $cantidad)
    {
        $pesosPorDefecto = [
            'TNT' => 25,
            'SPUNLACE' => 30,
            'LAMINADORA' => 28,
            'TOALLITAS' => 15
        ];

        $nombreUpper = strtoupper($nombreProducto);
        $pesoPorUnidad = 25;

        foreach ($pesosPorDefecto as $tipo => $peso) {
            if (strpos($nombreUpper, $tipo) !== false) {
                $pesoPorUnidad = $peso;
                break;
            }
        }

        return floatval($cantidad);
    }

    public function actualizarRejillaConReserva($idRejilla, $pesoReservado)
    {
        try {
            $sql = "UPDATE sist_rejillas 
                   SET peso_actual = peso_actual + ?,
                       estado = CASE 
                           WHEN (peso_actual + ?) >= capacidad_maxima THEN 'llena'
                           WHEN (peso_actual + ?) > 0 THEN 'ocupada'
                           ELSE 'disponible'
                       END,
                       fecha_actualizacion = CURRENT_TIMESTAMP
                   WHERE id = ?";

            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute([$pesoReservado, $pesoReservado, $pesoReservado, $idRejilla]);
        } catch (Exception $e) {
            error_log("Error actualizando rejilla con reserva: " . $e->getMessage());
            throw $e;
        }
    }

    public function actualizarRejillaConReservaSinLimite($idRejilla, $pesoReservado)
    {
        try {
            $sql = "UPDATE sist_rejillas 
               SET peso_actual = peso_actual + ?,
                   estado = CASE 
                       WHEN (peso_actual + ?) > capacidad_maxima THEN 'llena'
                       WHEN (peso_actual + ?) > 0 THEN 'ocupada'
                       ELSE 'disponible'
                   END,
                   fecha_actualizacion = CURRENT_TIMESTAMP
               WHERE id = ?";

            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute([$pesoReservado, $pesoReservado, $pesoReservado, $idRejilla]);
        } catch (Exception $e) {
            error_log("Error actualizando rejilla con reserva sin límite: " . $e->getMessage());
            throw $e;
        }
    }

    public function verificarCapacidadParaReserva($idRejilla, $pesoAdicional)
    {
        try {
            $sql = "SELECT 
                       id, 
                       numero_rejilla, 
                       capacidad_maxima, 
                       peso_actual, 
                       estado,
                       (capacidad_maxima - peso_actual) as capacidad_disponible
                   FROM sist_rejillas 
                   WHERE id = :id_rejilla 
                   AND estado IN ('disponible', 'ocupada')";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_rejilla', $idRejilla, PDO::PARAM_INT);
            $stmt->execute();

            $rejilla = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rejilla) {
                return [
                    'valida' => false,
                    'razon' => 'Rejilla no encontrada o no disponible'
                ];
            }

            if (($rejilla['peso_actual'] + $pesoAdicional) > $rejilla['capacidad_maxima']) {
                $exceso = ($rejilla['peso_actual'] + $pesoAdicional) - $rejilla['capacidad_maxima'];
                return [
                    'valida' => false,
                    'razon' => "Excede la capacidad por " . round($exceso, 1) . " kg"
                ];
            }

            return [
                'valida' => true,
                'rejilla' => $rejilla,
                'peso_total_resultante' => $rejilla['peso_actual'] + $pesoAdicional,
                'capacidad_restante' => $rejilla['capacidad_maxima'] - ($rejilla['peso_actual'] + $pesoAdicional)
            ];
        } catch (Exception $e) {
            error_log("Error verificando capacidad para reserva: " . $e->getMessage());
            return [
                'valida' => false,
                'razon' => 'Error interno'
            ];
        }
    }

    public function cancelarReserva($idAsignacion)
    {
        try {
            $sql = "SELECT id_rejilla, peso_asignado, id_venta, id_producto_presupuesto 
                   FROM sist_rejillas_asignaciones 
                   WHERE id = ? AND tipo_origen = 'reserva_presupuesto'";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$idAsignacion]);
            $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reserva) {
                throw new Exception('Reserva no encontrada');
            }

            $this->beginTransaction();

            $sqlDelete = "DELETE FROM sist_rejillas_asignaciones WHERE id = ?";
            $stmtDelete = $this->conexion->prepare($sqlDelete);
            $stmtDelete->execute([$idAsignacion]);

            $sqlUpdate = "UPDATE sist_rejillas 
                         SET peso_actual = peso_actual - ?,
                             estado = CASE 
                                 WHEN (peso_actual - ?) <= 0 THEN 'disponible'
                                 WHEN (peso_actual - ?) < capacidad_maxima THEN 'ocupada'
                                 ELSE estado
                             END,
                             fecha_actualizacion = CURRENT_TIMESTAMP
                         WHERE id = ?";

            $stmtUpdate = $this->conexion->prepare($sqlUpdate);
            $stmtUpdate->execute([
                $reserva['peso_asignado'],
                $reserva['peso_asignado'],
                $reserva['peso_asignado'],
                $reserva['id_rejilla']
            ]);

            $this->resetearMovimientoProduccion($reserva['id_venta'], $reserva['id_producto_presupuesto']);

            $this->resetearMovimientoExpedicion($reserva['id_venta'], $reserva['id_producto_presupuesto']);

            $this->commit();
            return true;
        } catch (Exception $e) {
            $this->rollBack();
            error_log("Error cancelando reserva: " . $e->getMessage());
            throw $e;
        }
    }
    public function obtenerEstadisticasProduccionExpedicion($cliente = null)
    {
        try {
            $sql = "SELECT 
                        COUNT(DISTINCT vp.id) as total_ventas,
                        COUNT(DISTINCT vpp.id) as total_productos,
                        
                        SUM(
                            CASE 
                                WHEN UPPER(vpp.descripcion) LIKE '%PAÑO%' OR UPPER(vpp.descripcion) LIKE '%PAÑOS%' OR 
                                     UPPER(vpp.descripcion) LIKE '%TOALLITA%' OR UPPER(vpp.descripcion) LIKE '%TOALLA%' THEN 
                                    vpp.cantidad * COALESCE(svp.cantidad, 1)
                                ELSE 
                                    vpp.cantidad
                            END
                        ) as total_peso_vendido,
                        
                        SUM(
                            CASE 
                                WHEN UPPER(vpp.descripcion) LIKE '%PAÑO%' OR UPPER(vpp.descripcion) LIKE '%PAÑOS%' OR 
                                     UPPER(vpp.descripcion) LIKE '%TOALLITA%' OR UPPER(vpp.descripcion) LIKE '%TOALLA%' THEN 
                                    vpp.cantidad
                                WHEN COALESCE(svp.cantidad, 0) > 0 THEN 
                                    ROUND(vpp.cantidad / svp.cantidad, 0)
                                ELSE vpp.cantidad 
                            END
                        ) as total_unidades_vendidas,
                        
                        SUM(
                            CASE 
                                WHEN UPPER(vpp.descripcion) LIKE '%PAÑO%' OR UPPER(vpp.descripcion) LIKE '%PAÑOS%' OR 
                                     UPPER(vpp.descripcion) LIKE '%TOALLITA%' OR UPPER(vpp.descripcion) LIKE '%TOALLA%' THEN 
                                    CASE 
                                        WHEN COALESCE(prod_info.cantidad_produccion, 0) > 0 THEN
                                            CASE 
                                                WHEN prod_info.cantidad_produccion <= vpp.cantidad THEN 
                                                    prod_info.cantidad_produccion * COALESCE(svp.cantidad, 1)
                                                ELSE 
                                                    prod_info.cantidad_produccion
                                            END
                                        ELSE 0 
                                    END
                                ELSE 
                                    COALESCE(prod_info.cantidad_produccion, 0)
                            END
                        ) as total_peso_produccion,
                        COUNT(DISTINCT CASE WHEN prod_info.cantidad_produccion > 0 THEN vpp.id END) as productos_con_produccion,
                        
                        SUM(
                            CASE 
                                WHEN UPPER(vpp.descripcion) LIKE '%PAÑO%' OR UPPER(vpp.descripcion) LIKE '%PAÑOS%' OR 
                                     UPPER(vpp.descripcion) LIKE '%TOALLITA%' OR UPPER(vpp.descripcion) LIKE '%TOALLA%' THEN 
                                    CASE 
                                        WHEN COALESCE(exp_info.cantidad_expedicion, 0) > 0 THEN
                                            CASE 
                                                WHEN exp_info.cantidad_expedicion <= vpp.cantidad THEN 
                                                    exp_info.cantidad_expedicion * COALESCE(svp.cantidad, 1)
                                                ELSE 
                                                    exp_info.cantidad_expedicion
                                            END
                                        ELSE 0 
                                    END
                                ELSE 
                                    COALESCE(exp_info.cantidad_expedicion, 0)
                            END
                        ) as total_peso_expedicion,
                        COUNT(DISTINCT CASE WHEN exp_info.cantidad_expedicion > 0 THEN vpp.id END) as productos_con_expedicion,
                        
                        COUNT(DISTINCT CASE 
                            WHEN COALESCE(prod_info.cantidad_produccion, 0) + COALESCE(exp_info.cantidad_expedicion, 0) < vpp.cantidad 
                            THEN vpp.id 
                        END) as productos_con_pendientes
                        
                    FROM sist_ventas_presupuesto vp
                    INNER JOIN sist_ventas_pres_product vpp ON vp.id = vpp.id_presupuesto
                    LEFT JOIN sist_ventas_productos svp ON vpp.id_producto = svp.id

                    " . $this->obtenerSubqueriesComunes() . "
                    WHERE vp.cliente IS NOT NULL AND vp.cliente != '' AND vp.cliente != 'AMERICA TNT'
                    " . $this->obtenerFiltrosProductosPendientes();

            $params = [];
            if (!empty($cliente)) {
                $sql .= " AND vp.cliente = :cliente";
                $params[':cliente'] = $cliente;
            }

            $stmt = $this->conexion->prepare($sql);

            if (!empty($cliente)) {
                $stmt->bindParam(':cliente', $params[':cliente'], PDO::PARAM_STR);
            }

            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas producción/expedición: " . $e->getMessage());
            return [];
        }
    }

    public function beginTransaction()
    {
        try {
            if (!$this->conexion->inTransaction()) {
                return $this->conexion->beginTransaction();
            }
            return true;
        } catch (Exception $e) {
            error_log("Error iniciando transacción: " . $e->getMessage());
            return false;
        }
    }

    public function commit()
    {
        try {
            if ($this->conexion->inTransaction()) {
                return $this->conexion->commit();
            }
            return true;
        } catch (Exception $e) {
            error_log("Error confirmando transacción: " . $e->getMessage());
            return false;
        }
    }
    public function rollBack()
    {
        try {
            if ($this->conexion->inTransaction()) {
                return $this->conexion->rollBack();
            }
            return true;
        } catch (Exception $e) {
            error_log("Error revirtiendo transacción: " . $e->getMessage());
            return false;
        }
    }
}
