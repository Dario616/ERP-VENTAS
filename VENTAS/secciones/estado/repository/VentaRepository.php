<?php

class VentaRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    public function obtenerVentas($idUsuario = null, $filtros = [])
    {
        try {
            $whereConditions = [];
            $params = [];

            if ($idUsuario !== null) {
                $whereConditions[] = "p.id_usuario = :id_usuario";
                $params[':id_usuario'] = $idUsuario;
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "p.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['estado'])) {
                $whereConditions[] = "p.estado = :estado";
                $params[':estado'] = $filtros['estado'];
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "p.fecha_venta >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "p.fecha_venta <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['proforma'])) {
                $whereConditions[] = "p.proforma = :proforma";
                $params[':proforma'] = $filtros['proforma'];
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            $sql = "SELECT p.*,
                   COUNT(pp.id) as total_productos,
                   (SELECT COUNT(DISTINCT 
                        CASE 
                            WHEN UPPER(sp_prod.tipo) IN ('TNT', 'SPUNLACE', 'LAMINADORA') 
                            THEN CONCAT(ps_prod.nombre_producto, '_', ps_prod.metragem, '_', ps_prod.largura)
                            ELSE ps_prod.nombre_producto
                        END
                    )
                    FROM sist_prod_stock ps_prod
                    JOIN sist_ventas_orden_produccion op_prod ON ps_prod.id_orden_produccion = op_prod.id
                    LEFT JOIN sist_ventas_productos sp_prod ON ps_prod.nombre_producto ILIKE '%' || sp_prod.descripcion || '%'
                    WHERE op_prod.id_venta = p.id) as productos_producidos,
                   (SELECT COUNT(DISTINCT 
                        CASE 
                            WHEN UPPER(sp_desp.tipo) IN ('TNT', 'SPUNLACE', 'LAMINADORA') 
                            THEN CONCAT(ps_desp.nombre_producto, '_', ps_desp.metragem, '_', ps_desp.largura)
                            ELSE ps_desp.nombre_producto
                        END
                    )
                    FROM sist_prod_stock ps_desp
                    LEFT JOIN sist_ventas_productos sp_desp ON ps_desp.nombre_producto ILIKE '%' || sp_desp.descripcion || '%'
                    WHERE ps_desp.id_venta = p.id 
                    AND ps_desp.estado = 'despachado') as productos_despachados,
                   CASE WHEN pcp.id IS NOT NULL THEN true ELSE false END as tiene_proceso_pcp
            FROM sist_ventas_presupuesto p 
            LEFT JOIN sist_ventas_pres_product pp ON p.id = pp.id_presupuesto
            LEFT JOIN sist_ventas_proceso_pcp pcp ON p.id = pcp.id_venta
            {$whereClause} 
            GROUP BY p.id, pcp.id
            ORDER BY p.fecha_venta DESC, p.id DESC";

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $key => $val) {
                if (in_array($key, [':id_usuario', ':proforma'])) {
                    $stmt->bindValue($key, $val, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $val, PDO::PARAM_STR);
                }
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo ventas: " . $e->getMessage());
            return [];
        }
    }


    public function obtenerVentaPorId($id, $idUsuario = null)
    {
        try {
            $sql = "SELECT * FROM sist_ventas_presupuesto WHERE id = :id";
            $params = [':id' => $id];

            if ($idUsuario !== null) {
                $sql .= " AND id_usuario = :id_usuario";
                $params[':id_usuario'] = $idUsuario;
            }

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            }
            $stmt->execute();

            $venta = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($venta) {
                $venta['productos'] = $this->obtenerProductosVenta($id);
                $venta['proceso_pcp'] = $this->obtenerProcesoPCP($id);
            }

            return $venta;
        } catch (Exception $e) {
            error_log("Error obteniendo venta por ID: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerResumenProduccion($idVenta, $cliente)
    {
        try {
            $sql = "SELECT 
                pp.id as id_producto,
                pp.descripcion as producto,
                pp.cantidad as cantidad_pedida_kg,
                pp.precio as precio_unitario,
                sp.tipo as tipo_producto,
                sp.cantidad as peso_por_bobina,
                
                CASE 
                    WHEN UPPER(sp.tipo) IN ('TNT', 'SPUNLACE', 'LAMINADORA') AND sp.cantidad > 0 
                    THEN CAST(ROUND(CAST(pp.cantidad / sp.cantidad AS numeric), 0) AS integer)
                    ELSE pp.cantidad
                END as cantidad_pedida,
                
                CASE 
                    WHEN UPPER(sp.tipo) IN ('TNT', 'SPUNLACE', 'LAMINADORA') 
                    THEN 'bobinas'
                    ELSE 'kg'
                END as unidad_medida
                
            FROM sist_ventas_pres_product pp
            LEFT JOIN sist_ventas_productos sp ON UPPER(TRIM(pp.descripcion)) = UPPER(TRIM(sp.descripcion))
            WHERE pp.id_presupuesto = :id_venta
            ORDER BY pp.id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($productos as &$producto) {
                $datosProduccion = $this->obtenerDatosProduccionSimplificados($idVenta, $producto['producto']);
                $datosDespacho = $this->obtenerDatosDespachosSimplificados($idVenta, $producto['producto']);

                $producto = array_merge($producto, $datosProduccion, $datosDespacho);
                if ($producto['unidad_medida'] === 'bobinas') {
                    error_log("DEBUG - Producto bobinas '{$producto['producto']}': Pedido={$producto['cantidad_pedida']}, Producido={$producto['cantidad_producida']}, Items={$producto['items_producidos']}, Bobinas={$producto['bobinas_producidas']}");
                }
            }

            return $productos;
        } catch (Exception $e) {
            error_log("Error obteniendo resumen de producción simplificado: " . $e->getMessage());
            return [];
        }
    }

    private function obtenerDatosProduccionSimplificados($idVenta, $nombreProducto)
    {
        try {
            $sqlTipo = "SELECT tipo FROM sist_ventas_productos 
                       WHERE UPPER(TRIM(descripcion)) ILIKE :nombre_producto_tipo
                       LIMIT 1";

            $stmtTipo = $this->conexion->prepare($sqlTipo);
            $stmtTipo->bindValue(':nombre_producto_tipo', '%' . $nombreProducto . '%', PDO::PARAM_STR);
            $stmtTipo->execute();
            $tipoProducto = $stmtTipo->fetchColumn();

            $esTipoBobinas = $tipoProducto && in_array(strtoupper($tipoProducto), ['TNT', 'SPUNLACE', 'LAMINADORA']);
            $sql = "SELECT 
                COUNT(ps.id) as items_producidos,
                SUM(ps.bobinas_pacote) as bobinas_producidas,
                SUM(ps.peso_liquido) as peso_producido,
                " . ($esTipoBobinas ? "SUM(ps.bobinas_pacote)" : "COUNT(ps.id)") . " as cantidad_producida
            FROM sist_prod_stock ps
            JOIN sist_ventas_orden_produccion op ON ps.id_orden_produccion = op.id
            WHERE op.id_venta = :id_venta 
            AND ps.nombre_producto ILIKE :nombre_producto";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->bindValue(':nombre_producto', '%' . $nombreProducto . '%', PDO::PARAM_STR);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'cantidad_producida' => $resultado['cantidad_producida'] ?: 0,
                'items_producidos' => $resultado['items_producidos'] ?: 0,
                'bobinas_producidas' => $resultado['bobinas_producidas'] ?: 0,
                'peso_producido' => $resultado['peso_producido'] ?: 0,
                'es_tipo_bobinas' => $esTipoBobinas
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de producción: " . $e->getMessage());
            return [
                'cantidad_producida' => 0,
                'items_producidos' => 0,
                'bobinas_producidas' => 0,
                'peso_producido' => 0,
                'es_tipo_bobinas' => false
            ];
        }
    }

    private function obtenerDatosDespachosSimplificados($idVenta, $nombreProducto)
    {
        try {
            $sqlTipo = "SELECT tipo FROM sist_ventas_productos 
                       WHERE UPPER(TRIM(descripcion)) ILIKE :nombre_producto_tipo
                       LIMIT 1";

            $stmtTipo = $this->conexion->prepare($sqlTipo);
            $stmtTipo->bindValue(':nombre_producto_tipo', '%' . $nombreProducto . '%', PDO::PARAM_STR);
            $stmtTipo->execute();
            $tipoProducto = $stmtTipo->fetchColumn();

            $esTipoBobinas = $tipoProducto && in_array(strtoupper($tipoProducto), ['TNT', 'SPUNLACE', 'LAMINADORA']);
            $sql = "SELECT 
                COUNT(ps.id) as items_despachados,
                SUM(ps.bobinas_pacote) as bobinas_despachadas,
                SUM(ps.peso_liquido) as peso_despachado,
                " . ($esTipoBobinas ? "SUM(ps.bobinas_pacote)" : "COUNT(ps.id)") . " as cantidad_despachada
            FROM sist_prod_stock ps
            WHERE ps.id_venta = :id_venta 
            AND ps.nombre_producto ILIKE :nombre_producto
            AND ps.estado = 'despachado'";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->bindValue(':nombre_producto', '%' . $nombreProducto . '%', PDO::PARAM_STR);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $datosProduccion = $this->obtenerDatosProduccionSimplificados($idVenta, $nombreProducto);
            $cantidadStock = max(
                0,
                ($datosProduccion['cantidad_producida'] ?? 0) -
                    ($resultado['cantidad_despachada'] ?: 0)
            );

            return [
                'cantidad_despachada' => $resultado['cantidad_despachada'] ?: 0,
                'items_despachados' => $resultado['items_despachados'] ?: 0,
                'bobinas_despachadas' => $resultado['bobinas_despachadas'] ?: 0,
                'peso_despachado' => $resultado['peso_despachado'] ?: 0,
                'cantidad_stock' => $cantidadStock,
                'es_tipo_bobinas' => $esTipoBobinas
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de despacho: " . $e->getMessage());
            return [
                'cantidad_despachada' => 0,
                'items_despachados' => 0,
                'bobinas_despachadas' => 0,
                'peso_despachado' => 0,
                'cantidad_stock' => 0,
                'es_tipo_bobinas' => false
            ];
        }
    }

    public function obtenerProcesoPCP($idVenta)
    {
        try {
            $sql = "SELECT pcp.*
                    FROM sist_ventas_proceso_pcp pcp
                    WHERE pcp.id_venta = :id_venta
                    ORDER BY pcp.fecha_procesamiento DESC
                    LIMIT 1";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado) {
                $resultado['usuario_pcp_completo'] = 'Usuario ID: ' . $resultado['id_usuario_pcp'];
                if ($resultado['fecha_procesamiento']) {
                    $fecha = new DateTime($resultado['fecha_procesamiento']);
                    $resultado['fecha_procesamiento_formateada'] = $fecha->format('d/m/Y H:i:s');
                }
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("Error obteniendo proceso PCP: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerHistorialProcesoPCP($idVenta)
    {
        try {
            $sql = "SELECT pcp.*
                    FROM sist_ventas_proceso_pcp pcp
                    WHERE pcp.id_venta = :id_venta
                    ORDER BY pcp.fecha_procesamiento DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($resultados as &$resultado) {
                $resultado['usuario_pcp_completo'] = 'Usuario ID: ' . $resultado['id_usuario_pcp'];
                if ($resultado['fecha_procesamiento']) {
                    $fecha = new DateTime($resultado['fecha_procesamiento']);
                    $resultado['fecha_procesamiento_formateada'] = $fecha->format('d/m/Y H:i:s');
                }
            }

            return $resultados;
        } catch (Exception $e) {
            error_log("Error obteniendo historial proceso PCP: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerProductosVenta($idVenta)
    {
        try {
            $sql = "SELECT * FROM sist_ventas_pres_product WHERE id_presupuesto = :id_venta ORDER BY id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos de venta: " . $e->getMessage());
            return [];
        }
    }

    public function actualizarEstadoVenta($id, $estado, $idUsuario = null)
    {
        try {
            $sql = "UPDATE sist_ventas_presupuesto SET estado = :estado WHERE id = :id";
            $params = [':estado' => $estado, ':id' => $id];

            if ($idUsuario !== null) {
                $sql .= " AND id_usuario = :id_usuario";
                $params[':id_usuario'] = $idUsuario;
            }

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                if ($key === ':id' || $key === ':id_usuario') {
                    $stmt->bindValue($key, $val, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $val, PDO::PARAM_STR);
                }
            }

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando estado de venta: " . $e->getMessage());
            return false;
        }
    }


    public function actualizarEstadoReserva($idReserva, $estado)
    {
        try {
            $sql = "UPDATE sist_ventas_reserva_stock SET estado = :estado WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
            $stmt->bindValue(':id', $idReserva, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando estado de reserva: " . $e->getMessage());
            return false;
        }
    }


    public function obtenerEstadisticas($idUsuario = null)
    {
        try {
            $whereClause = '';
            $params = [];

            if ($idUsuario !== null) {
                $whereClause = 'WHERE id_usuario = :id_usuario';
                $params[':id_usuario'] = $idUsuario;
            }

            $sql = "SELECT 
                        COUNT(*) as total_ventas,
                        COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as ventas_pendientes,
                        COUNT(CASE WHEN estado = 'en_produccion' THEN 1 END) as ventas_produccion,
                        COUNT(CASE WHEN estado = 'despachada' THEN 1 END) as ventas_despachadas,
                        COUNT(CASE WHEN estado = 'completada' THEN 1 END) as ventas_completadas,
                        COUNT(CASE WHEN estado = 'cancelada' THEN 1 END) as ventas_canceladas,
                        COUNT(CASE WHEN estado = 'Finalizado Manualmente' THEN 1 END) as ventas_finalizadas_manualmente,
                        SUM(monto_total) as monto_total,
                        AVG(monto_total) as monto_promedio,
                        COUNT(CASE WHEN fecha_venta >= CURRENT_DATE - INTERVAL '30 days' THEN 1 END) as ventas_mes
                    FROM sist_ventas_presupuesto {$whereClause}";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            }
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [];
        }
    }

    public function buscarVentas($termino, $idUsuario = null, $limite = 10)
    {
        try {
            $whereConditions = ["(cliente ILIKE :termino OR proforma::text ILIKE :termino OR estado ILIKE :termino)"];
            $params = [':termino' => '%' . $termino . '%'];

            if ($idUsuario !== null) {
                $whereConditions[] = "id_usuario = :id_usuario";
                $params[':id_usuario'] = $idUsuario;
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $sql = "SELECT id, cliente, proforma, estado, fecha_venta, monto_total 
                    FROM sist_ventas_presupuesto 
                    {$whereClause} 
                    ORDER BY fecha_venta DESC 
                    LIMIT :limite";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                if ($key === ':limite') {
                    $stmt->bindValue($key, $val, PDO::PARAM_INT);
                } elseif ($key === ':id_usuario') {
                    $stmt->bindValue($key, $val, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $val, PDO::PARAM_STR);
                }
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error buscando ventas: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerInfoProducto($descripcion)
    {
        try {
            $sql = "SELECT tipo, cantidad, descripcion 
                    FROM sist_ventas_productos 
                    WHERE UPPER(TRIM(descripcion)) = UPPER(TRIM(:descripcion))
                    LIMIT 1";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo información del producto: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerEstadosDisponibles()
    {
        return [
            'pendiente' => 'Pendiente',
            'confirmada' => 'Confirmada',
            'en_produccion' => 'En Producción',
            'produccion_completa' => 'Producción Completa',
            'despachada' => 'Despachada',
            'completada' => 'Completada',
            'cancelada' => 'Cancelada',
            'Finalizado Manualmente' => 'Finalizado Manualmente'
        ];
    }


    public function obtenerItemsProduccionEnStock($idVenta, $idProducto = null)
    {
        try {
            $whereClause = "WHERE op.id_venta = :id_venta";
            $params = [':id_venta' => $idVenta];

            if ($idProducto !== null) {
                $whereClause .= " AND EXISTS (
                SELECT 1 FROM sist_ventas_pres_product pp 
                WHERE pp.id = :id_producto 
                AND ps.nombre_producto ILIKE '%' || pp.descripcion || '%'
            )";
                $params[':id_producto'] = $idProducto;
            }

            $sql = "SELECT 
            ps.id,
            ps.peso_bruto,
            ps.peso_liquido,
            ps.fecha_hora_producida,
            ps.estado,
            ps.numero_item,
            ps.nombre_producto,
            ps.tipo_producto,
            ps.id_orden_produccion,
            ps.tara,
            ps.metragem,
            ps.largura,
            ps.gramatura,
            ps.bobinas_pacote,
            ps.usuario,
            op.cliente,
            op.fecha_orden,
            op.observaciones as observaciones_orden
        FROM sist_prod_stock ps
        JOIN sist_ventas_orden_produccion op ON ps.id_orden_produccion = op.id
        {$whereClause}
        ORDER BY ps.fecha_hora_producida DESC, ps.numero_item ASC";

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo items de producción: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerItemsProduccionEnStockAgrupados($idVenta, $idProducto = null)
    {
        try {
            $whereClause = "WHERE op.id_venta = :id_venta";
            $params = [':id_venta' => $idVenta];

            if ($idProducto !== null) {
                $whereClause .= " AND EXISTS (
                SELECT 1 FROM sist_ventas_pres_product pp 
                WHERE pp.id = :id_producto 
                AND ps.nombre_producto ILIKE '%' || pp.descripcion || '%'
            )";
                $params[':id_producto'] = $idProducto;
            }

            $sql = "SELECT 
    ps.nombre_producto,
    ps.metragem,
    ps.tipo_producto,
    ps.largura,
    ps.gramatura,
    COUNT(ps.id) AS total_items,
    SUM(ps.bobinas_pacote) AS bobinas_pacote_total,
    SUM(ps.peso_bruto) AS peso_bruto_total,
    SUM(ps.peso_liquido) AS peso_liquido_total,
    MIN(ps.fecha_hora_producida) as primera_produccion,
    MAX(ps.fecha_hora_producida) as ultima_produccion,
    op.cliente,
    COUNT(DISTINCT ps.id_orden_produccion) as total_ordenes,
    STRING_AGG(DISTINCT ps.id_orden_produccion::text, ', ' ORDER BY ps.id_orden_produccion::text) as ordenes_produccion
FROM sist_prod_stock ps
JOIN sist_ventas_orden_produccion op ON ps.id_orden_produccion = op.id
{$whereClause}
GROUP BY 
    ps.nombre_producto,
    ps.metragem,
    ps.tipo_producto,
    ps.largura,
    ps.gramatura,
    op.cliente
ORDER BY 
    ps.nombre_producto ASC, 
    ps.metragem ASC";

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo items de producción agrupados: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerItemsDespachosAgrupados($idVenta, $idProducto = null)
    {
        try {
            $whereClause = "WHERE ps.id_venta = :id_venta AND ps.estado = 'despachado'";
            $params = [':id_venta' => $idVenta];

            if ($idProducto !== null) {
                $whereClause .= " AND EXISTS (
                SELECT 1 FROM sist_ventas_pres_product pp 
                WHERE pp.id = :id_producto 
                AND ps.nombre_producto ILIKE '%' || pp.descripcion || '%'
            )";
                $params[':id_producto'] = $idProducto;
            }

            $sql = "SELECT 
            ps.nombre_producto,
            ps.metragem,
            ps.tipo_producto,
            ps.largura,
            ps.gramatura,
            COUNT(ps.id) AS total_items,
            SUM(ps.bobinas_pacote) AS bobinas_pacote_total,
            SUM(ps.peso_bruto) AS peso_bruto_total,
            SUM(ps.peso_liquido) AS peso_liquido_total,
            MIN(ps.fecha_hora_producida) as primera_produccion,
            MAX(ps.fecha_hora_producida) as ultima_produccion,
            COUNT(DISTINCT ps.id_orden_produccion) as total_ordenes
        FROM sist_prod_stock ps
        {$whereClause}
        GROUP BY 
            ps.nombre_producto,
            ps.metragem,
            ps.tipo_producto,
            ps.largura,
            ps.gramatura
        ORDER BY 
            ps.nombre_producto ASC, 
            ps.metragem ASC";

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo items despachados agrupados: " . $e->getMessage());
            return [];
        }
    }
}
