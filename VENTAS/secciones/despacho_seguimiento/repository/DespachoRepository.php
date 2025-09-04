<?php

class DespachoRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    public function obtenerExpediciones($filtros = [])
    {
        try {
            $whereConditions = ["1=1"];
            $params = [];

            if (!empty($filtros['numero_expedicion'])) {
                $whereConditions[] = "e.numero_expedicion ILIKE :numero_expedicion";
                $params[':numero_expedicion'] = '%' . $filtros['numero_expedicion'] . '%';
            }

            if (!empty($filtros['transportista'])) {
                $whereConditions[] = "e.transportista ILIKE :transportista";
                $params[':transportista'] = '%' . $filtros['transportista'] . '%';
            }

            if (!empty($filtros['destino'])) {
                $whereConditions[] = "e.destino ILIKE :destino";
                $params[':destino'] = '%' . $filtros['destino'] . '%';
            }

            if (!empty($filtros['estado'])) {
                $whereConditions[] = "e.estado = :estado";
                $params[':estado'] = $filtros['estado'];
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "DATE(e.fecha_creacion) >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "DATE(e.fecha_creacion) <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['id_stock'])) {
                $whereConditions[] = "EXISTS (
                SELECT 1 FROM sist_expedicion_items ei_filter 
                WHERE ei_filter.numero_expedicion = e.numero_expedicion 
                AND ei_filter.id_stock = :id_stock
            )";
                $params[':id_stock'] = $filtros['id_stock'];
            }

            if (!empty($filtros['id_venta_asignado'])) {
                $whereConditions[] = "EXISTS (
                SELECT 1 FROM sist_expedicion_items ei_filter 
                WHERE ei_filter.numero_expedicion = e.numero_expedicion 
                AND ei_filter.id_venta_asignado = :id_venta_asignado
            )";
                $params[':id_venta_asignado'] = $filtros['id_venta_asignado'];
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $sql = "
            SELECT 
    e.id,
    e.numero_expedicion,
    e.fecha_creacion,
    e.estado,
    e.transportista,
    e.conductor,
    e.placa_vehiculo,
    e.destino,
    e.observaciones,
    e.usuario_creacion,
    e.fecha_despacho,
    e.usuario_despacho,
    e.peso,
    e.tipovehiculo,
    e.id_rejilla,
    e.descripcion,
    COUNT(ei.id) AS total_items,
    COUNT(DISTINCT ei.cliente_asignado) AS clientes_diferentes,
    SUM(ei.cantidad_escaneada) AS cantidad_total,
    SUM(ei.peso_escaneado) AS peso_escaneado_total,
    COUNT(DISTINCT ps.nombre_producto) AS productos_diferentes,
    SUM(ps.peso_bruto) AS peso_bruto_total,
    SUM(ps.peso_liquido) AS peso_liquido_total,
    SUM(COALESCE(ps.bobinas_pacote, 0)) AS total_bobinas,  -- ← AGREGAR ESTA LÍNEA
    MIN(ei.fecha_escaneado) AS fecha_primer_escaneo,
    MAX(ei.fecha_escaneado) AS fecha_ultimo_escaneo,
    string_agg(DISTINCT ei.cliente_asignado, ', ') AS clientes_lista,
    string_agg(DISTINCT ps.nombre_producto, ', ') AS productos_lista
FROM sist_expediciones e
LEFT JOIN sist_expedicion_items ei ON e.numero_expedicion = ei.numero_expedicion
LEFT JOIN sist_prod_stock ps ON ei.id_stock = ps.id
{$whereClause}
GROUP BY 
    e.id,
    e.numero_expedicion,
    e.fecha_creacion,
    e.estado,
    e.transportista,
    e.conductor,
    e.placa_vehiculo,
    e.destino,
    e.observaciones,
    e.usuario_creacion,
    e.fecha_despacho,
    e.usuario_despacho,
    e.peso,
    e.tipovehiculo,
    e.id_rejilla,
    e.descripcion
ORDER BY 
    e.fecha_creacion DESC";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo expediciones: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerDetallesExpedicion($numeroExpedicion, $agrupar = false)
    {
        try {
            if ($agrupar) {
                $sql = "
                    SELECT 
                        e.id as expedicion_id,
                        e.numero_expedicion,
                        e.fecha_creacion,
                        e.estado,
                        e.transportista,
                        e.conductor,
                        e.placa_vehiculo,
                        e.destino,
                        e.observaciones,
                        e.usuario_creacion,
                        e.fecha_despacho,
                        e.usuario_despacho,
                        e.peso as peso_expedicion,
                        e.tipovehiculo,
                        e.descripcion,
                        
                        COUNT(ei.id) as items_en_grupo,
                        MIN(ps.id) as stock_id,
                        string_agg(DISTINCT ps.id::text, ', ') as stock_ids_grupo,
                        MIN(ei.fecha_escaneado) as fecha_escaneado,
                        string_agg(DISTINCT ei.usuario_escaneo, ', ') as usuario_escaneo,
                        ei.cliente_asignado,
                        string_agg(DISTINCT COALESCE(ei.id_venta_asignado::text, ''), ', ') as id_venta_asignado,
                        SUM(ei.cantidad_escaneada) as cantidad_escaneada,
                        SUM(ei.peso_escaneado) as peso_escaneado,
                        string_agg(DISTINCT ei.modo_asignacion, ', ') as modo_asignacion,
                        
                        SUM(ps.peso_bruto) as peso_bruto,
                        SUM(ps.peso_liquido) as peso_liquido,
                        MIN(ps.fecha_hora_producida) as fecha_hora_producida,
                        ps.nombre_producto,
                        ps.tipo_producto,
                        string_agg(DISTINCT COALESCE(ps.id_orden_produccion::text, ''), ', ') as id_orden_produccion,
                        AVG(ps.tara) as tara,
                        ps.metragem,
                        ps.largura,
                        ps.gramatura,
                        SUM(COALESCE(ps.bobinas_pacote, 0)) as bobinas_pacote,
                        ps.cliente as cliente_original,
                        string_agg(DISTINCT COALESCE(ps.id_venta::text, ''), ', ') as venta_original,
                        string_agg(DISTINCT ps.usuario, ', ') as usuario_produccion,
                        
                        MIN(ps.numero_item) || 
                        CASE 
                            WHEN COUNT(ps.numero_item) > 1 
                            THEN ' (+' || (COUNT(ps.numero_item) - 1) || ' más)' 
                            ELSE '' 
                        END as numero_item,
                        
                        true as es_grupo
                        
                    FROM sist_expediciones e
                    INNER JOIN sist_expedicion_items ei ON e.numero_expedicion = ei.numero_expedicion
                    INNER JOIN sist_prod_stock ps ON ei.id_stock = ps.id
                    WHERE e.numero_expedicion = :numero_expedicion
                    
                    GROUP BY 
                        e.id, e.numero_expedicion, e.fecha_creacion, e.estado, e.transportista, 
                        e.conductor, e.placa_vehiculo, e.destino, e.observaciones, e.usuario_creacion,
                        e.fecha_despacho, e.usuario_despacho, e.peso, e.tipovehiculo, e.descripcion,
                        ei.cliente_asignado, ps.nombre_producto, ps.tipo_producto,
                        ps.metragem, ps.largura, ps.gramatura, ps.cliente
                        
                    ORDER BY 
                        ps.nombre_producto ASC,
                        ps.tipo_producto ASC,
                        ps.metragem ASC,
                        ps.largura ASC,
                        ei.cliente_asignado ASC,
                        ps.gramatura ASC";
            } else {
                $sql = "
                    SELECT 
                        e.id as expedicion_id,
                        e.numero_expedicion,
                        e.fecha_creacion,
                        e.estado,
                        e.transportista,
                        e.conductor,
                        e.placa_vehiculo,
                        e.destino,
                        e.observaciones,
                        e.usuario_creacion,
                        e.fecha_despacho,
                        e.usuario_despacho,
                        e.peso as peso_expedicion,
                        e.tipovehiculo,
                        e.descripcion,
                        
                        1 as items_en_grupo,
                        ei.fecha_escaneado,
                        ei.usuario_escaneo,
                        ei.cliente_asignado,
                        ei.id_venta_asignado,
                        ei.cantidad_escaneada,
                        ei.peso_escaneado,
                        ei.modo_asignacion,
                        
                        ps.id as stock_id,
                        ps.peso_bruto,
                        ps.peso_liquido,
                        ps.fecha_hora_producida,
                        ps.estado as estado_stock,
                        ps.numero_item,
                        ps.nombre_producto,
                        ps.tipo_producto,
                        ps.id_orden_produccion,
                        ps.tara,
                        ps.metragem,
                        ps.largura,
                        ps.gramatura,
                        ps.bobinas_pacote,
                        ps.cliente as cliente_original,
                        ps.id_venta as venta_original,
                        ps.usuario as usuario_produccion,
                        
                        ps.id::text as stock_ids_grupo,
                        false as es_grupo
                        
                    FROM sist_expediciones e
                    INNER JOIN sist_expedicion_items ei ON e.numero_expedicion = ei.numero_expedicion
                    INNER JOIN sist_prod_stock ps ON ei.id_stock = ps.id
                    WHERE e.numero_expedicion = :numero_expedicion
                    ORDER BY 
                        ei.fecha_escaneado DESC,
                        ps.nombre_producto ASC";
            }

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':numero_expedicion', $numeroExpedicion, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo detalles de expedición: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerEstadisticas()
    {
        try {
            $sql = "
                SELECT 
                    COUNT(e.id) as total_expediciones,
                    COUNT(DISTINCT e.transportista) as transportistas_diferentes,
                    COUNT(DISTINCT e.destino) as destinos_diferentes,
                    COUNT(ei.id) as total_items_escaneados,
                    COUNT(DISTINCT ps.nombre_producto) as productos_diferentes,
                    COUNT(DISTINCT ei.cliente_asignado) as clientes_diferentes,
                    SUM(ei.peso_escaneado) as peso_escaneado_total,
                    SUM(ps.peso_bruto) as peso_bruto_total,
                    SUM(ps.peso_liquido) as peso_liquido_total,
                    SUM(ei.cantidad_escaneada) as cantidad_total,
                    MIN(e.fecha_creacion) as fecha_primera_expedicion,
                    MAX(e.fecha_creacion) as fecha_ultima_expedicion,
                    COUNT(CASE WHEN e.estado = 'ABIERTA' THEN 1 END) as expediciones_abiertas,
                    COUNT(CASE WHEN e.estado = 'EN_TRANSITO' THEN 1 END) as expediciones_transito,
                    COUNT(CASE WHEN e.estado = 'ENTREGADA' THEN 1 END) as expediciones_entregadas,
                    COUNT(CASE WHEN e.estado = 'CANCELADA' THEN 1 END) as expediciones_canceladas
                FROM sist_expediciones e
                LEFT JOIN sist_expedicion_items ei ON e.numero_expedicion = ei.numero_expedicion
                LEFT JOIN sist_prod_stock ps ON ei.id_stock = ps.id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas de expediciones: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerTransportistas()
    {
        try {
            $sql = "SELECT DISTINCT transportista 
                    FROM sist_expediciones 
                    WHERE transportista IS NOT NULL 
                    AND transportista != ''
                    ORDER BY transportista";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo transportistas: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerEstadosExpedicion()
    {
        try {
            $sql = "SELECT DISTINCT estado 
                    FROM sist_expediciones 
                    WHERE estado IS NOT NULL
                    ORDER BY estado";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo estados de expedición: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerDestinos()
    {
        try {
            $sql = "SELECT DISTINCT destino 
                    FROM sist_expediciones 
                    WHERE destino IS NOT NULL 
                    AND destino != ''
                    ORDER BY destino";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo destinos: " . $e->getMessage());
            return [];
        }
    }

    public function buscarExpediciones($termino, $limite = 10)
    {
        try {
            $sql = "
                SELECT DISTINCT 
                    'expedicion' as tipo,
                    numero_expedicion as valor,
                    CONCAT('Exp. ', numero_expedicion, ' - ', COALESCE(destino, 'Sin destino')) as texto_completo
                FROM sist_expediciones
                WHERE numero_expedicion ILIKE :termino
                
                UNION
                
                SELECT DISTINCT 
                    'transportista' as tipo,
                    transportista as valor,
                    CONCAT('Transportista: ', transportista) as texto_completo
                FROM sist_expediciones
                WHERE transportista ILIKE :termino
                    AND transportista IS NOT NULL
                
                ORDER BY texto_completo
                LIMIT :limite";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':termino', '%' . $termino . '%', PDO::PARAM_STR);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error buscando expediciones: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerResumenPorTransportista()
    {
        try {
            $sql = "
                SELECT 
                    e.transportista,
                    COUNT(DISTINCT e.id) as total_expediciones,
                    COUNT(ei.id) as total_items,
                    SUM(ei.peso_escaneado) as peso_escaneado_total,
                    SUM(ps.peso_bruto) as peso_bruto_total,
                    COUNT(DISTINCT e.destino) as destinos_diferentes
                FROM sist_expediciones e
                LEFT JOIN sist_expedicion_items ei ON e.numero_expedicion = ei.numero_expedicion
                LEFT JOIN sist_prod_stock ps ON ei.id_stock = ps.id
                WHERE e.transportista IS NOT NULL
                GROUP BY e.transportista
                ORDER BY peso_bruto_total DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo resumen por transportista: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerExpedicion($numeroExpedicion)
    {
        try {
            $sql = "
                SELECT 
                    e.*,
                    COUNT(ei.id) as total_items,
                    SUM(ei.peso_escaneado) as peso_total_escaneado
                FROM sist_expediciones e
                LEFT JOIN sist_expedicion_items ei ON e.numero_expedicion = ei.numero_expedicion
                WHERE e.numero_expedicion = :numero_expedicion
                GROUP BY e.id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':numero_expedicion', $numeroExpedicion, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo expedición: " . $e->getMessage());
            return null;
        }
    }
}
