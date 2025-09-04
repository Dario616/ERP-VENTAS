<?php

class ProductosAsignadosRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    public function obtenerProductosAsignadosAgrupados($filtros = [])
    {
        try {
            $whereConditions = ["ps.estado = 'en stock'", "ps.id_orden_produccion IS NOT NULL"];
            $params = [];

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "op.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['producto'])) {
                $whereConditions[] = "ps.nombre_producto ILIKE :producto";
                $params[':producto'] = '%' . $filtros['producto'] . '%';
            }

            if (!empty($filtros['tipo_producto'])) {
                $whereConditions[] = "ps.tipo_producto = :tipo_producto";
                $params[':tipo_producto'] = $filtros['tipo_producto'];
            }

            if (!empty($filtros['estado_orden'])) {
                $whereConditions[] = "op.estado = :estado_orden";
                $params[':estado_orden'] = $filtros['estado_orden'];
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "DATE(op.fecha_orden) >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "DATE(op.fecha_orden) <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $sql = "
                SELECT 
                    op.id AS id_orden_produccion,
                    op.id_venta,
                    op.cliente,
                    op.fecha_orden,
                    op.estado AS estado_orden,
                    op.observaciones,
                    -- DATOS AGRUPADOS DE PRODUCTOS
                    COUNT(ps.id) AS total_items,
                    COUNT(DISTINCT ps.nombre_producto) AS productos_diferentes,
                    COUNT(DISTINCT ps.tipo_producto) AS tipos_diferentes,
                    SUM(ps.bobinas_pacote) AS bobinas_pacote_total,
                    SUM(ps.peso_bruto) AS peso_bruto_total,
                    SUM(ps.peso_liquido) AS peso_liquido_total,
                    MIN(ps.fecha_hora_producida) AS fecha_primera_produccion,
                    MAX(ps.fecha_hora_producida) AS fecha_ultima_produccion,
                    -- INFORMACIÓN ADICIONAL
                    string_agg(DISTINCT ps.nombre_producto, ', ') AS productos_lista,
                    string_agg(DISTINCT ps.tipo_producto, ', ') AS tipos_lista
                FROM sist_ventas_orden_produccion op
                INNER JOIN sist_prod_stock ps ON op.id = ps.id_orden_produccion
                {$whereClause}
                GROUP BY 
                    op.id,
                    op.id_venta,
                    op.cliente,
                    op.fecha_orden,
                    op.estado,
                    op.observaciones
                ORDER BY 
                    op.fecha_orden DESC,
                    op.cliente ASC";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos asignados agrupados: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerDetallesOrden($idOrdenProduccion)
    {
        try {
            $sql = "
            SELECT 
                ps.nombre_producto,
                ps.tipo_producto,
                ps.metragem,
                ps.largura,
                ps.gramatura,
                ps.id_orden_produccion,
                -- DATOS AGRUPADOS
                COUNT(ps.id) AS total_items,
                SUM(ps.bobinas_pacote) AS bobinas_pacote,
                SUM(ps.peso_bruto) AS peso_bruto,
                SUM(ps.peso_liquido) AS peso_liquido,
                SUM(ps.tara) AS tara,
                MIN(ps.fecha_hora_producida) AS fecha_hora_producida,
                MAX(ps.fecha_hora_producida) AS fecha_ultima_produccion,
                string_agg(DISTINCT ps.usuario, ', ') AS usuario,
                string_agg(DISTINCT ps.numero_item::text, ', ') AS numero_item,
                -- INFORMACIÓN DE LA ORDEN
                op.cliente,
                op.fecha_orden,
                op.estado AS estado_orden,
                op.observaciones,
                op.id_venta
            FROM sist_prod_stock ps
            INNER JOIN sist_ventas_orden_produccion op ON ps.id_orden_produccion = op.id
            WHERE ps.estado = 'en stock'
                AND ps.id_orden_produccion = :id_orden_produccion
            GROUP BY 
                ps.nombre_producto,
                ps.tipo_producto,
                ps.metragem,
                ps.largura,
                ps.gramatura,
                ps.id_orden_produccion,
                op.cliente,
                op.fecha_orden,
                op.estado,
                op.observaciones,
                op.id_venta
            ORDER BY 
                ps.nombre_producto ASC, 
                ps.tipo_producto ASC,
                ps.metragem ASC,
                ps.largura ASC,
                ps.gramatura ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_orden_produccion', $idOrdenProduccion, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo detalles de orden: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerEstadisticas()
    {
        try {
            $sql = "
                SELECT 
                    COUNT(ps.id) as total_items_asignados,
                    COUNT(DISTINCT ps.nombre_producto) as productos_diferentes,
                    COUNT(DISTINCT ps.tipo_producto) as tipos_diferentes,
                    COUNT(DISTINCT op.id) as ordenes_diferentes,
                    COUNT(DISTINCT op.cliente) as clientes_diferentes,
                    COUNT(DISTINCT op.id_venta) as ventas_diferentes,
                    SUM(ps.peso_bruto) as peso_bruto_total,
                    SUM(ps.peso_liquido) as peso_liquido_total,
                    SUM(ps.bobinas_pacote) as bobinas_total,
                    MIN(op.fecha_orden) as fecha_primera_orden,
                    MAX(op.fecha_orden) as fecha_ultima_orden,
                    -- Estadísticas por estado de orden
                    COUNT(CASE WHEN op.estado = 'Pendiente' THEN 1 END) as ordenes_pendientes,
                    COUNT(CASE WHEN op.estado = 'En Proceso' THEN 1 END) as ordenes_proceso,
                    COUNT(CASE WHEN op.estado = 'Completada' THEN 1 END) as ordenes_completadas
                FROM sist_prod_stock ps
                INNER JOIN sist_ventas_orden_produccion op ON ps.id_orden_produccion = op.id
                WHERE ps.estado = 'en stock'";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas de productos asignados: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerClientes()
    {
        try {
            $sql = "SELECT DISTINCT op.cliente 
                    FROM sist_ventas_orden_produccion op
                    INNER JOIN sist_prod_stock ps ON op.id = ps.id_orden_produccion
                    WHERE ps.estado = 'en stock' 
                    AND op.cliente IS NOT NULL 
                    AND op.cliente != ''
                    ORDER BY op.cliente";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo clientes: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerEstadosOrden()
    {
        try {
            $sql = "SELECT DISTINCT op.estado 
                    FROM sist_ventas_orden_produccion op
                    INNER JOIN sist_prod_stock ps ON op.id = ps.id_orden_produccion
                    WHERE ps.estado = 'en stock' 
                    AND op.estado IS NOT NULL
                    ORDER BY op.estado";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo estados de orden: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerTiposProducto()
    {
        try {
            $sql = "SELECT DISTINCT ps.tipo_producto 
                    FROM sist_prod_stock ps
                    INNER JOIN sist_ventas_orden_produccion op ON ps.id_orden_produccion = op.id
                    WHERE ps.estado = 'en stock' 
                    AND ps.tipo_producto IS NOT NULL
                    ORDER BY ps.tipo_producto";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo tipos de producto: " . $e->getMessage());
            return [];
        }
    }

    public function buscarClientesProductos($termino, $limite = 10)
    {
        try {
            $sql = "
                SELECT DISTINCT 
                    'cliente' as tipo,
                    op.cliente as valor,
                    op.cliente as texto_completo
                FROM sist_ventas_orden_produccion op
                INNER JOIN sist_prod_stock ps ON op.id = ps.id_orden_produccion
                WHERE ps.estado = 'en stock' 
                    AND op.cliente ILIKE :termino
                
                UNION
                
                SELECT DISTINCT 
                    'producto' as tipo,
                    ps.nombre_producto as valor,
                    CONCAT(ps.nombre_producto, ' - ', ps.tipo_producto) as texto_completo
                FROM sist_prod_stock ps
                INNER JOIN sist_ventas_orden_produccion op ON ps.id_orden_produccion = op.id
                WHERE ps.estado = 'en stock' 
                    AND (ps.nombre_producto ILIKE :termino OR ps.tipo_producto ILIKE :termino)
                
                ORDER BY texto_completo
                LIMIT :limite";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':termino', '%' . $termino . '%', PDO::PARAM_STR);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error buscando clientes y productos: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerResumenPorCliente()
    {
        try {
            $sql = "
                SELECT 
                    op.cliente,
                    COUNT(DISTINCT op.id) as total_ordenes,
                    COUNT(ps.id) as total_items,
                    SUM(ps.peso_bruto) as peso_bruto_total,
                    SUM(ps.peso_liquido) as peso_liquido_total,
                    COUNT(DISTINCT ps.nombre_producto) as productos_diferentes
                FROM sist_ventas_orden_produccion op
                INNER JOIN sist_prod_stock ps ON op.id = ps.id_orden_produccion
                WHERE ps.estado = 'en stock'
                GROUP BY op.cliente
                ORDER BY peso_bruto_total DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo resumen por cliente: " . $e->getMessage());
            return [];
        }
    }
}
