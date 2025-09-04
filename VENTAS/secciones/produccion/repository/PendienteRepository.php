<?php

class PendienteRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    /**
     * Obtiene el resumen detallado de producción pendiente usando la nueva tabla
     * ✅ CORREGIDO: Stock producido específico por producto
     * @return array Array con todos los registros de producción pendiente
     */
    public function obtenerResumenPendiente()
    {
        try {
            $sql = "
            SELECT 
                prod.tipoproducto,
                CASE 
                    WHEN prod.tipoproducto IN ('TNT', 'LAMINADORA') THEN
                        CASE 
                            WHEN " . $this->getCondicionTNT_M2() . " THEN 'M2'
                            ELSE 'M1'
                        END
                    ELSE prod.tipoproducto
                END as categoria,
                pp.id,
                pp.id_venta,
                pp.id_producto,
                pp.fecha_asignacion,
                pp.destino,
                pp.cantidad as cantidad_total,
                pp.observaciones,
                pp.estado,
                prod.descripcion as producto_descripcion,
                prod.unidadmedida,
                pres.cliente,
                pres.moneda,
                pp.fecha_asignacion as fecha_orden,
                
                -- Peso unitario del producto desde sist_ventas_productos
                COALESCE(prod_base.cantidad, 0) as peso_unitario,
                
                -- ✅ CORRECCIÓN: Stock producido específico por producto usando nombre_producto
                COALESCE(
                    CASE 
                        WHEN prod.tipoproducto = 'TOALLITAS' THEN
                            (SELECT COUNT(*) 
                             FROM public.sist_prod_stock stock 
                             JOIN public.sist_ventas_orden_produccion op ON stock.id_orden_produccion = op.id
                             WHERE op.id_venta = pp.id_venta
                             AND " . $this->getProductMatchCondition('stock.nombre_producto', 'prod.descripcion') . ")
                        ELSE
                            (SELECT COALESCE(SUM(stock.peso_liquido), 0) 
                             FROM public.sist_prod_stock stock 
                             JOIN public.sist_ventas_orden_produccion op ON stock.id_orden_produccion = op.id
                             WHERE op.id_venta = pp.id_venta
                             AND " . $this->getProductMatchCondition('stock.nombre_producto', 'prod.descripcion') . ")
                    END, 0
                ) as stock_producido
                
            FROM public.sist_ventas_productos_produccion pp
            JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
            JOIN public.sist_ventas_presupuesto pres ON pp.id_venta = pres.id
            -- JOIN para obtener peso unitario de productos
            LEFT JOIN public.sist_ventas_productos prod_base ON prod_base.descripcion = prod.descripcion
            WHERE (pp.estado IS NULL OR pp.estado IN ('Pendiente', 'Orden Emitida', 'En Produccion'))
            AND prod.tipoproducto IS NOT NULL
            ORDER BY prod.tipoproducto, categoria, pp.fecha_asignacion";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo resumen pendiente: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ CORREGIDO: Estadísticas agregadas por tipo de producto pendiente
     * @param array $filtros Filtros opcionales para la consulta
     * @return array Array con estadísticas por tipo de producto
     */
    public function obtenerEstadisticasPendientes($filtros = [])
    {
        try {
            $whereConditions = [
                "(pp.estado IS NULL OR pp.estado IN ('Pendiente', 'Orden Emitida', 'En Produccion'))",
                "prod.tipoproducto IS NOT NULL"
            ];
            $params = [];

            // Aplicar filtros opcionales
            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "pp.fecha_asignacion >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "pp.fecha_asignacion <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "pres.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['tipo_producto'])) {
                if ($filtros['tipo_producto'] === 'TNT') {
                    $whereConditions[] = "prod.tipoproducto IN ('TNT', 'LAMINADORA')";
                } else {
                    $whereConditions[] = "prod.tipoproducto = :tipo_producto";
                    $params[':tipo_producto'] = $filtros['tipo_producto'];
                }
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            $productMatchCondition = $this->getProductMatchCondition('stock.nombre_producto', 'prod.descripcion');

            $sql = "
            SELECT 
                'TNT_M1' as categoria,
                COUNT(pp.id) as total_ordenes,
                SUM(pp.cantidad - COALESCE(
                    (SELECT COALESCE(SUM(stock.peso_liquido), 0) FROM public.sist_prod_stock stock 
                     JOIN public.sist_ventas_orden_produccion op ON stock.id_orden_produccion = op.id
                     WHERE op.id_venta = pp.id_venta AND $productMatchCondition), 0
                )) as cantidad_pendiente
            FROM public.sist_ventas_productos_produccion pp
            JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
            JOIN public.sist_ventas_presupuesto pres ON pp.id_venta = pres.id
            $whereClause
            AND prod.tipoproducto IN ('TNT', 'LAMINADORA')
            AND NOT (" . $this->getCondicionTNT_M2() . ")
            HAVING SUM(pp.cantidad - COALESCE(
                (SELECT COALESCE(SUM(stock.peso_liquido), 0) FROM public.sist_prod_stock stock 
                 JOIN public.sist_ventas_orden_produccion op ON stock.id_orden_produccion = op.id
                 WHERE op.id_venta = pp.id_venta AND $productMatchCondition), 0
            )) > 0

            UNION ALL

            SELECT 
                'TNT_M2' as categoria,
                COUNT(pp.id) as total_ordenes,
                SUM(pp.cantidad - COALESCE(
                    (SELECT COALESCE(SUM(stock.peso_liquido), 0) FROM public.sist_prod_stock stock 
                     JOIN public.sist_ventas_orden_produccion op ON stock.id_orden_produccion = op.id
                     WHERE op.id_venta = pp.id_venta AND $productMatchCondition), 0
                )) as cantidad_pendiente
            FROM public.sist_ventas_productos_produccion pp
            JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
            JOIN public.sist_ventas_presupuesto pres ON pp.id_venta = pres.id
            $whereClause
            AND prod.tipoproducto IN ('TNT', 'LAMINADORA')
            AND " . $this->getCondicionTNT_M2() . "
            HAVING SUM(pp.cantidad - COALESCE(
                (SELECT COALESCE(SUM(stock.peso_liquido), 0) FROM public.sist_prod_stock stock 
                 JOIN public.sist_ventas_orden_produccion op ON stock.id_orden_produccion = op.id
                 WHERE op.id_venta = pp.id_venta AND $productMatchCondition), 0
            )) > 0

            UNION ALL

            SELECT 
                prod.tipoproducto as categoria,
                COUNT(pp.id) as total_ordenes,
                SUM(pp.cantidad - COALESCE(
                    CASE 
                        WHEN prod.tipoproducto = 'TOALLITAS' THEN
                            (SELECT COUNT(*) FROM public.sist_prod_stock stock 
                             JOIN public.sist_ventas_orden_produccion op ON stock.id_orden_produccion = op.id
                             WHERE op.id_venta = pp.id_venta AND $productMatchCondition)
                        ELSE
                            (SELECT COALESCE(SUM(stock.peso_liquido), 0) FROM public.sist_prod_stock stock 
                             JOIN public.sist_ventas_orden_produccion op ON stock.id_orden_produccion = op.id
                             WHERE op.id_venta = pp.id_venta AND $productMatchCondition)
                    END, 0
                )) as cantidad_pendiente
            FROM public.sist_ventas_productos_produccion pp
            JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
            JOIN public.sist_ventas_presupuesto pres ON pp.id_venta = pres.id
            $whereClause
            AND prod.tipoproducto NOT IN ('TNT', 'LAMINADORA')
            GROUP BY prod.tipoproducto
            HAVING SUM(pp.cantidad - COALESCE(
                CASE 
                    WHEN prod.tipoproducto = 'TOALLITAS' THEN
                        (SELECT COUNT(*) FROM public.sist_prod_stock stock 
                         JOIN public.sist_ventas_orden_produccion op ON stock.id_orden_produccion = op.id
                         WHERE op.id_venta = pp.id_venta AND $productMatchCondition)
                    ELSE
                        (SELECT COALESCE(SUM(stock.peso_liquido), 0) FROM public.sist_prod_stock stock 
                         JOIN public.sist_ventas_orden_produccion op ON stock.id_orden_produccion = op.id
                         WHERE op.id_venta = pp.id_venta AND $productMatchCondition)
                END, 0
            )) > 0
            
            ORDER BY categoria";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas pendientes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ CORREGIDO: Detalles de un producto específico con información de stock
     * @param int $idProductoProduccion ID del registro de producción
     * @return array|false Detalles del producto o false si no existe
     */
    public function obtenerDetalleProducto($idProductoProduccion)
    {
        try {
            $productMatchCondition = $this->getProductMatchCondition('stock.nombre_producto', 'prod.descripcion');

            $sql = "
            SELECT 
                pp.*,
                prod.descripcion as producto_descripcion,
                prod.tipoproducto,
                prod.unidadmedida,
                pres.cliente,
                pres.moneda,
                usr.nombre as usuario_pcp,
                
                -- Peso unitario del producto
                COALESCE(prod_base.cantidad, 0) as peso_unitario,
                
                -- ✅ CORRECCIÓN: Stock producido específico por producto usando nombre_producto
                COALESCE(
                    CASE 
                        WHEN prod.tipoproducto = 'TOALLITAS' THEN
                            (SELECT COUNT(*) FROM public.sist_prod_stock stock 
                             JOIN public.sist_ventas_orden_produccion op ON stock.id_orden_produccion = op.id
                             WHERE op.id_venta = pp.id_venta AND $productMatchCondition)
                        ELSE
                            (SELECT COALESCE(SUM(stock.peso_liquido), 0) FROM public.sist_prod_stock stock 
                             JOIN public.sist_ventas_orden_produccion op ON stock.id_orden_produccion = op.id
                             WHERE op.id_venta = pp.id_venta AND $productMatchCondition)
                    END, 0
                ) as stock_producido
                
            FROM public.sist_ventas_productos_produccion pp
            JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
            JOIN public.sist_ventas_presupuesto pres ON pp.id_venta = pres.id
            LEFT JOIN public.sist_ventas_usuario usr ON pp.id_usuario_pcp = usr.id
            LEFT JOIN public.sist_ventas_productos prod_base ON prod_base.descripcion = prod.descripcion
            WHERE pp.id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $idProductoProduccion, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo detalle de producto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ CORREGIDO: Productos por destino
     * @param string $destino Destino específico
     * @return array Productos del destino
     */
    public function obtenerProductosPorDestino($destino)
    {
        try {
            $productMatchCondition = $this->getProductMatchCondition('stock.nombre_producto', 'prod.descripcion');

            $sql = "
            SELECT 
                pp.*,
                prod.descripcion as producto_descripcion,
                prod.tipoproducto,
                pres.cliente,
                COALESCE(prod_base.cantidad, 0) as peso_unitario,
                COALESCE(
                    CASE 
                        WHEN prod.tipoproducto = 'TOALLITAS' THEN
                            (SELECT COUNT(*) FROM public.sist_prod_stock stock 
                             JOIN public.sist_ventas_orden_produccion op ON stock.id_orden_produccion = op.id
                             WHERE op.id_venta = pp.id_venta AND $productMatchCondition)
                        ELSE
                            (SELECT COALESCE(SUM(stock.peso_liquido), 0) FROM public.sist_prod_stock stock 
                             JOIN public.sist_ventas_orden_produccion op ON stock.id_orden_produccion = op.id
                             WHERE op.id_venta = pp.id_venta AND $productMatchCondition)
                    END, 0
                ) as stock_producido
            FROM public.sist_ventas_productos_produccion pp
            JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
            JOIN public.sist_ventas_presupuesto pres ON pp.id_venta = pres.id
            LEFT JOIN public.sist_ventas_productos prod_base ON prod_base.descripcion = prod.descripcion
            WHERE pp.destino = :destino
            AND (pp.estado IS NULL OR pp.estado IN ('Pendiente', 'Orden Emitida', 'En Produccion'))
            ORDER BY pp.fecha_asignacion";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':destino', $destino);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos por destino: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ MATCH EXACTO ÚNICAMENTE - Máxima velocidad
     * @param string $stockProductField Campo del nombre del producto en stock
     * @param string $ventaProductField Campo de descripción del producto en venta
     * @return string Condición SQL de match exacto
     */
    private function getProductMatchCondition($stockProductField, $ventaProductField)
    {
        return "$stockProductField = $ventaProductField";
    }
    /**
     * Genera la condición SQL para identificar productos TNT de M2
     * Basado en el análisis de la descripción del producto
     */
    private function getCondicionTNT_M2()
    {
        return "
            (prod.descripcion ~* 'ancho.*([0-9]+([,.]?[0-9]+)?).*cm' 
             AND (
                 -- Extraer valor numérico del ancho y convertir a metros
                 (CAST(regexp_replace(
                     regexp_replace(prod.descripcion, '.*ancho.*?([0-9]+[,.]?[0-9]*).*cm.*', '\\1', 'i'),
                     ',', '.', 'g'
                 ) AS numeric) / 100.0 > 0.10 
                 AND CAST(regexp_replace(
                     regexp_replace(prod.descripcion, '.*ancho.*?([0-9]+[,.]?[0-9]*).*cm.*', '\\1', 'i'),
                     ',', '.', 'g'
                 ) AS numeric) / 100.0 <= 1.25)
                 OR
                 (CAST(regexp_replace(
                     regexp_replace(prod.descripcion, '.*ancho.*?([0-9]+[,.]?[0-9]*).*cm.*', '\\1', 'i'),
                     ',', '.', 'g'
                 ) AS numeric) / 100.0 > 1.65 
                 AND CAST(regexp_replace(
                     regexp_replace(prod.descripcion, '.*ancho.*?([0-9]+[,.]?[0-9]*).*cm.*', '\\1', 'i'),
                     ',', '.', 'g'
                 ) AS numeric) / 100.0 <= 2.50)
             ))
            OR
            (prod.descripcion ~* 'largura.*([0-9]+([,.]?[0-9]+)?).*m' 
             AND (
                 -- Extraer valor numérico de largura en metros
                 (CAST(regexp_replace(
                     regexp_replace(prod.descripcion, '.*largura.*?([0-9]+[,.]?[0-9]*).*m.*', '\\1', 'i'),
                     ',', '.', 'g'
                 ) AS numeric) > 0.10 
                 AND CAST(regexp_replace(
                     regexp_replace(prod.descripcion, '.*largura.*?([0-9]+[,.]?[0-9]*).*m.*', '\\1', 'i'),
                     ',', '.', 'g'
                 ) AS numeric) <= 1.25)
                 OR
                 (CAST(regexp_replace(
                     regexp_replace(prod.descripcion, '.*largura.*?([0-9]+[,.]?[0-9]*).*m.*', '\\1', 'i'),
                     ',', '.', 'g'
                 ) AS numeric) > 1.65 
                 AND CAST(regexp_replace(
                     regexp_replace(prod.descripcion, '.*largura.*?([0-9]+[,.]?[0-9]*).*m.*', '\\1', 'i'),
                     ',', '.', 'g'
                 ) AS numeric) <= 2.50)
             ))
        ";
    }
}
