<?php

class OrdenesRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    public function obtenerOrdenes($filtros)
    {
        try {
            $whereConditions = $this->construirCondicionesWhere($filtros);
            $params = $this->construirParametros($filtros);

            $sql = "
                SELECT 
                    op.id,
                    op.fecha_orden,
                    op.estado,
                    op.cliente,
                    op.observaciones,
                    COUNT(DISTINCT COALESCE(tnt.id, toall.id, spun.id, panos.id)) as total_productos,
                    COUNT(DISTINCT ps.id) as items_producidos,
                    SUM(ps.bobinas_pacote) as bobinas_producidas,
                   ROUND(SUM(ps.peso_liquido)::NUMERIC, 2) as peso_total_producido
                FROM sist_ventas_orden_produccion op
                LEFT JOIN sist_ventas_op_tnt tnt ON op.id = tnt.id_orden_produccion
                LEFT JOIN sist_ventas_op_toallitas toall ON op.id = toall.id_orden_produccion  
                LEFT JOIN sist_ventas_op_spunlace spun ON op.id = spun.id_orden_produccion
                LEFT JOIN sist_ventas_op_panos panos ON op.id = panos.id_orden_produccion
                LEFT JOIN sist_prod_stock ps ON op.id = ps.id_orden_produccion
                {$whereConditions}
                GROUP BY op.id, op.fecha_orden, op.estado, op.cliente, op.observaciones
                ORDER BY op.fecha_orden DESC
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo órdenes: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerProductosOrden($idOrden)
    {
        try {
            $productos = [];

            // TNT
            $sql = "SELECT 'TNT' as tipo, nombre, gramatura, largura_metros, longitud_bobina, 
                          color, peso_bobina, cantidad_total, total_bobinas
                   FROM sist_ventas_op_tnt WHERE id_orden_produccion = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id', $idOrden);
            $stmt->execute();
            $productos = array_merge($productos, $stmt->fetchAll(PDO::FETCH_ASSOC));

            // Toallitas
            $sql = "SELECT 'TOALLITAS' as tipo, nombre, cantidad_total, NULL as gramatura,
                          NULL as largura_metros, NULL as longitud_bobina, NULL as color,
                          NULL as peso_bobina, NULL as total_bobinas
                   FROM sist_ventas_op_toallitas WHERE id_orden_produccion = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id', $idOrden);
            $stmt->execute();
            $productos = array_merge($productos, $stmt->fetchAll(PDO::FETCH_ASSOC));

            // Spunlace
            $sql = "SELECT 'SPUNLACE' as tipo, nombre, gramatura, largura_metros, longitud_bobina,
                          color, peso_bobina, cantidad_total, total_bobinas, acabado
                   FROM sist_ventas_op_spunlace WHERE id_orden_produccion = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id', $idOrden);
            $stmt->execute();
            $productos = array_merge($productos, $stmt->fetchAll(PDO::FETCH_ASSOC));

            // Paños
            $sql = "SELECT 'PANOS' as tipo, nombre, cantidad_total, gramatura, color,
                          largura, picotado, cant_panos, unidad, peso
                   FROM sist_ventas_op_panos WHERE id_orden_produccion = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id', $idOrden);
            $stmt->execute();
            $productos = array_merge($productos, $stmt->fetchAll(PDO::FETCH_ASSOC));

            return $productos;
        } catch (Exception $e) {
            error_log("Error obteniendo productos de orden: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerProduccionReal($idOrden)
    {
        try {
            $sql = "
                SELECT 
                    id,
                    nombre_producto,
                    tipo_producto,
                    peso_bruto,
                    peso_liquido,
                    fecha_hora_producida,
                    estado,
                    metragem,
                    largura,
                    gramatura,
                    bobinas_pacote,
                    usuario,
                    numero_item
                FROM sist_prod_stock 
                WHERE id_orden_produccion = :id_orden
                ORDER BY fecha_hora_producida DESC
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_orden', $idOrden);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo producción real: " . $e->getMessage());
            return [];
        }
    }

    // NUEVA FUNCIÓN: Obtener producción real agrupada
    public function obtenerProduccionRealAgrupada($idOrden)
    {
        try {
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
                    COUNT(DISTINCT ps.id_orden_produccion) AS ordenes_diferentes,
                    -- CAMPOS ADICIONALES ÚTILES
                    AVG(ps.peso_bruto) AS peso_bruto_promedio,
                    AVG(ps.peso_liquido) AS peso_liquido_promedio,
                    STRING_AGG(DISTINCT ps.estado, ', ' ORDER BY ps.estado) AS estados,
                    STRING_AGG(DISTINCT ps.usuario, ', ' ORDER BY ps.usuario) AS operadores
                FROM sist_prod_stock ps 
                WHERE ps.id_orden_produccion = :id_orden
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
                    ps.gramatura ASC
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_orden', $idOrden);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo producción real agrupada: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerResumenProduccion($idOrden)
    {
        try {
            $sql = "
                SELECT 
                    tipo_producto,
                    COUNT(*) as items_producidos,
                    SUM(bobinas_pacote) as total_bobinas,
                    SUM(peso_bruto) as peso_bruto_total,
                    SUM(peso_liquido) as peso_liquido_total,
                    AVG(peso_bruto) as peso_promedio,
                    MIN(fecha_hora_producida) as primera_produccion,
                    MAX(fecha_hora_producida) as ultima_produccion
                FROM sist_prod_stock 
                WHERE id_orden_produccion = :id_orden
                GROUP BY tipo_producto
                ORDER BY items_producidos DESC
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_orden', $idOrden);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo resumen de producción: " . $e->getMessage());
            return [];
        }
    }

    private function construirCondicionesWhere($filtros)
    {
        $conditions = ["1=1"];

        if (!empty($filtros['fecha_inicio'])) {
            $conditions[] = "DATE(op.fecha_orden) >= :fecha_inicio";
        }

        if (!empty($filtros['fecha_fin'])) {
            $conditions[] = "DATE(op.fecha_orden) <= :fecha_fin";
        }

        if (!empty($filtros['estado'])) {
            $conditions[] = "op.estado = :estado";
        }

        if (!empty($filtros['cliente'])) {
            $conditions[] = "op.cliente ILIKE :cliente";
        }

        if (!empty($filtros['numero_orden'])) {
            $conditions[] = "op.id = :numero_orden";
        }

        return "WHERE " . implode(" AND ", $conditions);
    }

    private function construirParametros($filtros)
    {
        $params = [];

        if (!empty($filtros['fecha_inicio'])) {
            $params[':fecha_inicio'] = $filtros['fecha_inicio'];
        }

        if (!empty($filtros['fecha_fin'])) {
            $params[':fecha_fin'] = $filtros['fecha_fin'];
        }

        if (!empty($filtros['estado'])) {
            $params[':estado'] = $filtros['estado'];
        }

        if (!empty($filtros['cliente'])) {
            $params[':cliente'] = '%' . $filtros['cliente'] . '%';
        }

        if (!empty($filtros['numero_orden'])) {
            $params[':numero_orden'] = $filtros['numero_orden'];
        }

        return $params;
    }
}