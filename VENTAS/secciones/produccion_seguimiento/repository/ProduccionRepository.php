<?php
class ProduccionRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    public function obtenerProduccionAgrupada($filtros = [])
    {
        try {
            $whereConditions = ["ps.estado = 'en stock'"];
            $params = [];

            if (!empty($filtros['producto'])) {
                $whereConditions[] = "ps.nombre_producto ILIKE :producto";
                $params[':producto'] = '%' . $filtros['producto'] . '%';
            }

            if (!empty($filtros['tipo_producto'])) {
                $whereConditions[] = "ps.tipo_producto = :tipo_producto";
                $params[':tipo_producto'] = $filtros['tipo_producto'];
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "DATE(ps.fecha_hora_producida) >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "DATE(ps.fecha_hora_producida) <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $sql = "
                SELECT 
                    ps.nombre_producto,
                    ps.tipo_producto,
                    ps.metragem,
                    ps.largura,
                    ps.gramatura,
                    -- DATOS AGRUPADOS
                    COUNT(ps.id) AS total_items,
                    SUM(ps.bobinas_pacote) AS bobinas_pacote_total,
                    SUM(ps.peso_bruto) AS peso_bruto_total,
                    SUM(ps.peso_liquido) AS peso_liquido_total,
                    MIN(ps.fecha_hora_producida) AS fecha_primera_produccion,
                    MAX(ps.fecha_hora_producida) AS fecha_ultima_produccion,
                    COUNT(DISTINCT ps.id_orden_produccion) AS ordenes_diferentes
                FROM sist_prod_stock ps 
                {$whereClause}
                GROUP BY 
                    ps.nombre_producto,
                    ps.tipo_producto,
                    ps.metragem,
                    ps.largura,
                    ps.gramatura
                ORDER BY 
                    ps.nombre_producto ASC, 
                    ps.tipo_producto ASC,
                    ps.metragem ASC,
                    ps.largura ASC,
                    ps.gramatura ASC";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo producción agrupada: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerDetallesGrupo($nombreProducto, $tipoProducto, $metragem, $largura, $gramatura)
    {
        try {
            $sql = "
            SELECT 
                ps.id,
                ps.peso_bruto,
                ps.peso_liquido,
                ps.fecha_hora_producida,
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
                -- Corregido: usar las columnas que realmente existen en la tabla
                CASE 
                    WHEN op.id IS NOT NULL THEN CONCAT('Orden #', op.id)
                    ELSE NULL 
                END AS nombre_orden,
                op.fecha_orden AS fecha_orden
            FROM sist_prod_stock ps
            LEFT JOIN sist_ventas_orden_produccion op ON ps.id_orden_produccion = op.id
            WHERE ps.estado = 'en stock'
                AND ps.nombre_producto = :nombre_producto
                AND ps.tipo_producto = :tipo_producto
                AND ps.metragem = :metragem
                AND ps.largura = :largura
                AND ps.gramatura = :gramatura
            ORDER BY ps.fecha_hora_producida DESC, ps.numero_item ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
            $stmt->bindValue(':tipo_producto', $tipoProducto, PDO::PARAM_STR);
            $stmt->bindValue(':metragem', $metragem, PDO::PARAM_STR);
            $stmt->bindValue(':largura', $largura, PDO::PARAM_STR);
            $stmt->bindValue(':gramatura', $gramatura, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo detalles de grupo: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerEstadisticas()
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_items_stock,
                    COUNT(DISTINCT nombre_producto) as productos_diferentes,
                    COUNT(DISTINCT tipo_producto) as tipos_diferentes,
                    COUNT(DISTINCT id_orden_produccion) as ordenes_diferentes,
                    SUM(peso_bruto) as peso_bruto_total,
                    SUM(peso_liquido) as peso_liquido_total,
                    SUM(bobinas_pacote) as bobinas_total,
                    MIN(fecha_hora_producida) as fecha_primera_produccion,
                    MAX(fecha_hora_producida) as fecha_ultima_produccion
                FROM sist_prod_stock 
                WHERE estado = 'en stock'";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerTiposProducto()
    {
        try {
            $sql = "SELECT DISTINCT tipo_producto 
                    FROM sist_prod_stock 
                    WHERE estado = 'en stock' AND tipo_producto IS NOT NULL
                    ORDER BY tipo_producto";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo tipos de producto: " . $e->getMessage());
            return [];
        }
    }

    public function buscarProductos($termino, $limite = 10)
    {
        try {
            $sql = "
                SELECT DISTINCT 
                    nombre_producto,
                    tipo_producto
                FROM sist_prod_stock 
                WHERE estado = 'en stock' 
                    AND (nombre_producto ILIKE :termino OR tipo_producto ILIKE :termino)
                ORDER BY nombre_producto
                LIMIT :limite";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':termino', '%' . $termino . '%', PDO::PARAM_STR);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error buscando productos: " . $e->getMessage());
            return [];
        }
    }
}
