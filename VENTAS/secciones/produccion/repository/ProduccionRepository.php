<?php

class ProduccionRepository
{
    private $conexion;
    private $registrosPorPagina = 10;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    public function obtenerProductosConStock($filtros = [])
    {
        try {
            $whereConditions = [];
            $params = [];

            $whereConditions[] = "(op.id IS NOT NULL OR tnt_op.id IS NOT NULL OR sp_op.id IS NOT NULL OR pa_op.id IS NOT NULL OR prod.tipoproducto IN ('PAÑOS', 'Paños'))";

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['producto'])) {
                $whereConditions[] = "prod.descripcion ILIKE :producto";
                $params[':producto'] = '%' . $filtros['producto'] . '%';
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "COALESCE(op.fecha_orden, tnt_op.fecha_orden, sp_op.fecha_orden, pa_op.fecha_orden) >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "COALESCE(op.fecha_orden, tnt_op.fecha_orden, sp_op.fecha_orden, pa_op.fecha_orden) <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }

            if (!empty($filtros['tipo_producto'])) {
                $whereConditions[] = "prod.tipoproducto = :tipo_producto";
                $params[':tipo_producto'] = $filtros['tipo_producto'];
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $pagina = $filtros['pagina'] ?? 1;
            $offset = ($pagina - 1) * $this->registrosPorPagina;

            $sqlCount = "SELECT COUNT(DISTINCT pp.id) as total 
                        FROM public.sist_ventas_productos_produccion pp
                        JOIN public.sist_ventas_presupuesto v ON pp.id_venta = v.id
                        JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
                        LEFT JOIN public.sist_ventas_op_toallitas ot ON pp.id = ot.id_producto_produccion
                        LEFT JOIN public.sist_ventas_orden_produccion op ON ot.id_orden_produccion = op.id
                        LEFT JOIN public.sist_ventas_op_tnt tnt ON pp.id = tnt.id_producto_produccion
                        LEFT JOIN public.sist_ventas_orden_produccion tnt_op ON tnt.id_orden_produccion = tnt_op.id
                        LEFT JOIN public.sist_ventas_op_spunlace sp ON pp.id = sp.id_producto_produccion
                        LEFT JOIN public.sist_ventas_orden_produccion sp_op ON sp.id_orden_produccion = sp_op.id
                        LEFT JOIN public.sist_ventas_op_panos pa ON pp.id = pa.id_producto_produccion
                        LEFT JOIN public.sist_ventas_orden_produccion pa_op ON pa.id_orden_produccion = pa_op.id
                        $whereClause";

            $stmtCount = $this->conexion->prepare($sqlCount);
            foreach ($params as $key => $value) {
                $stmtCount->bindValue($key, $value);
            }
            $stmtCount->execute();
            $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
            $totalPaginas = ceil($totalRegistros / $this->registrosPorPagina);

            $sql = "SELECT DISTINCT pp.*, 
                           v.cliente, 
                           v.moneda, 
                           prod.descripcion as producto_descripcion, 
                           prod.tipoproducto,
                           prod.unidadmedida, 
                           u.nombre as nombre_responsable,
                           TO_CHAR(pp.fecha_asignacion, 'DD/MM/YYYY HH24:MI') as fecha_asignacion_formateada,
                           
                           COALESCE(op.id, tnt_op.id, sp_op.id, pa_op.id) as id_orden,
                           COALESCE(
                               TO_CHAR(op.fecha_orden, 'DD/MM/YYYY HH24:MI'),
                               TO_CHAR(tnt_op.fecha_orden, 'DD/MM/YYYY HH24:MI'),
                               TO_CHAR(sp_op.fecha_orden, 'DD/MM/YYYY HH24:MI'),
                               TO_CHAR(pa_op.fecha_orden, 'DD/MM/YYYY HH24:MI')
                           ) as fecha_completado_formateada,
                           COALESCE(op.estado, tnt_op.estado, sp_op.estado, pa_op.estado) as estado_orden,
                           
                           pp.cantidad as cantidad_asignada,
                           tnt.cantidad_total as tnt_cantidad_total,
                           sp.cantidad_total as sp_cantidad_total,
                           pa.cantidad_total as pa_cantidad_total
                           
                    FROM public.sist_ventas_productos_produccion pp
                    JOIN public.sist_ventas_presupuesto v ON pp.id_venta = v.id
                    JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
                    LEFT JOIN public.sist_ventas_usuario u ON pp.id_usuario_pcp = u.id
                    
                    LEFT JOIN public.sist_ventas_op_toallitas ot ON pp.id = ot.id_producto_produccion
                    LEFT JOIN public.sist_ventas_orden_produccion op ON ot.id_orden_produccion = op.id
                    
                    LEFT JOIN public.sist_ventas_op_tnt tnt ON pp.id = tnt.id_producto_produccion
                    LEFT JOIN public.sist_ventas_orden_produccion tnt_op ON tnt.id_orden_produccion = tnt_op.id
                    
                    LEFT JOIN public.sist_ventas_op_spunlace sp ON pp.id = sp.id_producto_produccion
                    LEFT JOIN public.sist_ventas_orden_produccion sp_op ON sp.id_orden_produccion = sp_op.id
                    
                    LEFT JOIN public.sist_ventas_op_panos pa ON pp.id = pa.id_producto_produccion
                    LEFT JOIN public.sist_ventas_orden_produccion pa_op ON pa.id_orden_produccion = pa_op.id
                    
                    $whereClause
                    ORDER BY pp.fecha_asignacion DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $this->registrosPorPagina, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'productos' => $productos,
                'total_registros' => $totalRegistros,
                'total_paginas' => $totalPaginas
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo productos con stock: " . $e->getMessage());
            return [
                'productos' => [],
                'total_registros' => 0,
                'total_paginas' => 0
            ];
        }
    }

    public function obtenerStockReal($idOrden, $tipoProducto)
    {
        try {
            $tipo = strtoupper(trim($tipoProducto ?? ''));
            $stockReal = 0;

            if ($tipo === 'TOALLITAS') {
                $sql = "SELECT COUNT(*) as cantidad_completada
                        FROM public.sist_prod_stock stock
                        WHERE stock.id_orden_produccion = :id_orden_produccion
                        AND stock.estado = 'en stock'";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':id_orden_produccion', $idOrden, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $stockReal = intval($result['cantidad_completada'] ?: 0);
            } elseif (in_array($tipo, ['TNT', 'SPUNLACE', 'LAMINADORA', 'PAÑOS'])) {
                $sql = "SELECT COALESCE(SUM(stock.peso_bruto), 0) as total_kilos
                        FROM public.sist_prod_stock stock
                        WHERE stock.id_orden_produccion = :id_orden_produccion
                        AND stock.estado = 'en stock'";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':id_orden_produccion', $idOrden, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $stockReal = floatval($result['total_kilos'] ?: 0);
            }

            return $stockReal;
        } catch (Exception $e) {
            error_log("Error calculando stock real: " . $e->getMessage());
            return 0;
        }
    }

    public function obtenerOrdenesProduccion($filtros = [])
    {
        try {
            $whereConditions = ["1=1", "op.finalizado IS DISTINCT FROM true"];
            $params = [];

            if (!empty($filtros['estado'])) {
                $whereConditions[] = "op.estado = :estado";
                $params[':estado'] = $filtros['estado'];
            }

            if (!empty($filtros['tipo'])) {
                $whereConditions[] = "prod.tipoproducto = :tipo";
                $params[':tipo'] = $filtros['tipo'];
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            // Nuevo filtro para recetas
            if (!empty($filtros['tiene_receta'])) {
                if ($filtros['tiene_receta'] === 'si') {
                    $whereConditions[] = "EXISTS (SELECT 1 FROM public.sist_ventas_orden_produccion_recetas r WHERE r.id_orden_produccion = op.id)";
                } elseif ($filtros['tiene_receta'] === 'no') {
                    $whereConditions[] = "NOT EXISTS (SELECT 1 FROM public.sist_ventas_orden_produccion_recetas r WHERE r.id_orden_produccion = op.id)";
                }
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $pagina = $filtros['pagina'] ?? 1;
            $offset = ($pagina - 1) * $this->registrosPorPagina;

            $sqlCount = "SELECT COUNT(DISTINCT op.id) as total 
                         FROM public.sist_ventas_orden_produccion op
                         JOIN public.sist_ventas_presupuesto v ON op.id_venta = v.id
                         LEFT JOIN public.sist_ventas_op_tnt tnt ON tnt.id_orden_produccion = op.id
                         LEFT JOIN public.sist_ventas_op_spunlace spun ON spun.id_orden_produccion = op.id
                         LEFT JOIN public.sist_ventas_op_toallitas toal ON toal.id_orden_produccion = op.id
                         LEFT JOIN public.sist_ventas_op_panos pa ON pa.id_orden_produccion = op.id
                         LEFT JOIN public.sist_ventas_pres_product prod ON (tnt.id_producto = prod.id OR spun.id_producto = prod.id OR toal.id_producto = prod.id OR pa.id_producto = prod.id)
                         $whereClause";

            $stmtCount = $this->conexion->prepare($sqlCount);
            foreach ($params as $key => $value) {
                $stmtCount->bindValue($key, $value);
            }
            $stmtCount->execute();
            $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
            $totalPaginas = ceil($totalRegistros / $this->registrosPorPagina);

            $sql = "SELECT op.id, op.id_venta, op.fecha_orden, op.estado, op.observaciones,
                    v.cliente, v.moneda, v.monto_total,
                    u.nombre as nombre_vendedor,
                    TO_CHAR(op.fecha_orden, 'DD/MM/YYYY HH24:MI') as fecha_orden_formateada,
                    prod.descripcion as nombre_producto,
                    prod.tipoproducto as tipo_producto,
                    prod.unidadmedida as unidad_medida,
                    
                    CASE 
                        WHEN tnt.id IS NOT NULL THEN tnt.cantidad_total
                        WHEN spun.id IS NOT NULL THEN spun.cantidad_total
                        WHEN toal.id IS NOT NULL THEN toal.cantidad_total
                        WHEN pa.id IS NOT NULL THEN pa.cantidad_total
                        ELSE 0
                    END as cantidad_total,
                    
                    -- Información de recetas usando subconsultas
                    (SELECT COUNT(*) FROM public.sist_ventas_orden_produccion_recetas r WHERE r.id_orden_produccion = op.id) > 0 as tiene_receta,
                    (SELECT COUNT(*) FROM public.sist_ventas_orden_produccion_recetas r WHERE r.id_orden_produccion = op.id) as cantidad_recetas,
                    (SELECT STRING_AGG(DISTINCT r.estado, ', ') FROM public.sist_ventas_orden_produccion_recetas r WHERE r.id_orden_produccion = op.id) as estados_recetas
                    
                    FROM public.sist_ventas_orden_produccion op
                    JOIN public.sist_ventas_presupuesto v ON op.id_venta = v.id
                    LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                    LEFT JOIN public.sist_ventas_op_tnt tnt ON tnt.id_orden_produccion = op.id
                    LEFT JOIN public.sist_ventas_op_spunlace spun ON spun.id_orden_produccion = op.id
                    LEFT JOIN public.sist_ventas_op_toallitas toal ON toal.id_orden_produccion = op.id
                    LEFT JOIN public.sist_ventas_op_panos pa ON pa.id_orden_produccion = op.id
                    LEFT JOIN public.sist_ventas_pres_product prod ON (tnt.id_producto = prod.id OR spun.id_producto = prod.id OR toal.id_producto = prod.id OR pa.id_producto = prod.id)
                    $whereClause
                    ORDER BY op.fecha_orden DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $this->registrosPorPagina, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'ordenes' => $ordenes,
                'total_registros' => $totalRegistros,
                'total_paginas' => $totalPaginas
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo órdenes de producción: " . $e->getMessage());
            return [
                'ordenes' => [],
                'total_registros' => 0,
                'total_paginas' => 0
            ];
        }
    }

    public function obtenerDetallesOrden($idOrden)
    {
        try {
            $sql = "SELECT 
                    op.id, op.id_venta, op.fecha_orden, op.estado, op.observaciones,
                    v.cliente, v.moneda, v.monto_total,
                    u.nombre as nombre_vendedor,
                    TO_CHAR(op.fecha_orden, 'DD/MM/YYYY HH24:MI') as fecha_orden_formateada,
                    
                    prod.descripcion as nombre_producto,
                    prod.tipoproducto as tipo_producto,
                    prod.unidadmedida as unidad_medida,
                    prod.precio,
                    
                    tnt.gramatura as tnt_gramatura,
                    tnt.largura_metros as tnt_largura,
                    tnt.longitud_bobina as tnt_longitud,
                    tnt.color as tnt_color,
                    tnt.peso_bobina as tnt_peso_bobina,
                    tnt.cantidad_total as tnt_cantidad_total,
                    tnt.total_bobinas as tnt_total_bobinas,
                    
                    spun.gramatura as spun_gramatura,
                    spun.largura_metros as spun_largura,
                    spun.longitud_bobina as spun_longitud,
                    spun.color as spun_color,
                    spun.acabado as spun_acabado,
                    spun.peso_bobina as spun_peso_bobina,
                    spun.cantidad_total as spun_cantidad_total,
                    spun.total_bobinas as spun_total_bobinas,
                    
                    toal.nombre as toal_nombre,
                    toal.cantidad_total as toal_cantidad_total,
                    
                    pa.nombre as pa_nombre,
                    pa.cantidad_total as pa_cantidad_total,
                    pa.color as pa_color,
                    pa.largura as pa_largura,
                    pa.picotado as pa_picotado,
                    pa.cant_panos as pa_cant_panos,
                    pa.unidad as pa_unidad,
                    pa.peso as pa_peso,
                    
                    CASE
                        WHEN tnt.id IS NOT NULL THEN 'TNT'
                        WHEN spun.id IS NOT NULL THEN 'SPUNLACE'
                        WHEN toal.id IS NOT NULL THEN 'TOALLITAS'
                        WHEN pa.id IS NOT NULL THEN 'PAÑOS'
                        WHEN prod.tipoproducto = 'PAÑOS' THEN 'PAÑOS'
                        ELSE 'DESCONOCIDO'
                    END as tipo_detectado,
                    
                    CASE
                        WHEN tnt.id IS NOT NULL THEN tnt.cantidad_total
                        WHEN spun.id IS NOT NULL THEN spun.cantidad_total
                        WHEN toal.id IS NOT NULL THEN toal.cantidad_total
                        WHEN pa.id IS NOT NULL THEN pa.cantidad_total
                        ELSE 0
                    END as cantidad_total,
                    
                    -- Información de recetas usando subconsultas
                    (SELECT COUNT(*) FROM public.sist_ventas_orden_produccion_recetas r WHERE r.id_orden_produccion = op.id) > 0 as tiene_receta,
                    (SELECT COUNT(*) FROM public.sist_ventas_orden_produccion_recetas r WHERE r.id_orden_produccion = op.id) as cantidad_recetas,
                    (SELECT STRING_AGG(DISTINCT r.estado, ', ') FROM public.sist_ventas_orden_produccion_recetas r WHERE r.id_orden_produccion = op.id) as estados_recetas
                    
                FROM public.sist_ventas_orden_produccion op
                JOIN public.sist_ventas_presupuesto v ON op.id_venta = v.id
                LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                LEFT JOIN public.sist_ventas_op_tnt tnt ON tnt.id_orden_produccion = op.id
                LEFT JOIN public.sist_ventas_op_spunlace spun ON spun.id_orden_produccion = op.id
                LEFT JOIN public.sist_ventas_op_toallitas toal ON toal.id_orden_produccion = op.id
                LEFT JOIN public.sist_ventas_op_panos pa ON pa.id_orden_produccion = op.id
                LEFT JOIN public.sist_ventas_pres_product prod ON (
                    tnt.id_producto = prod.id OR
                    spun.id_producto = prod.id OR
                    toal.id_producto = prod.id OR
                    pa.id_producto = prod.id
                )
                WHERE op.id = :id_orden";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_orden', $idOrden, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo detalles orden: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerRecetasOrden($idOrden)
    {
        try {
            $sql = "SELECT 
                    MIN(opr.id) as id, 
                    MAX(opr.estado) as estado_asignacion, 
                    MAX(opr.fecha_asignacion) as fecha_asignacion, 
                    MAX(opr.observaciones) as observaciones,
                    MIN(r.id) as id_receta, 
                    r.nombre_receta, 
                    r.version_receta, 
                    MAX(r.descripcion) as descripcion,
                    MAX(r.tipo_receta) as tipo_receta, 
                    BOOL_OR(r.activo) as receta_activa,
                    MAX(tp.\"desc\") as tipo_producto_nombre,
                    MAX(mp_obj.descripcion) as materia_prima_objetivo,
                    
                    -- Contar materias primas de esta versión específica de receta
                    COUNT(*) as total_materias_primas
                    
                    FROM public.sist_ventas_orden_produccion_recetas opr
                    JOIN public.sist_prod_recetas r ON opr.id_receta = r.id
                    LEFT JOIN public.sist_ventas_tipoproduc tp ON r.id_tipo_producto = tp.id
                    LEFT JOIN public.sist_prod_materia_prima mp_obj ON r.id_materia_prima_objetivo = mp_obj.id
                    WHERE opr.id_orden_produccion = :id_orden
                    GROUP BY r.nombre_receta, r.version_receta
                    ORDER BY MAX(opr.fecha_asignacion) DESC, r.nombre_receta, r.version_receta DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_orden', $idOrden, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo recetas de orden: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerTiposProductos()
    {
        try {
            $sql = "SELECT DISTINCT prod.tipoproducto 
                    FROM public.sist_ventas_pres_product prod 
                    WHERE prod.tipoproducto IN ('TNT', 'SPUNLACE', 'LAMINADORA', 'TOALLITAS', 'PAÑOS')
                    ORDER BY prod.tipoproducto";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo tipos de productos: " . $e->getMessage());
            return [];
        }
    }
}
