<?php

class ExpedicionesDespachadasRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    public function getConexion()
    {
        return $this->conexion;
    }

    public function obtenerExpedicionesDespachadas($fechaInicio, $fechaFin, $transportista = '', $codigoExpedicion = '', $offset = 0, $limite = 20)
    {
        try {
            $sql = "SELECT 
                       e.numero_expedicion,
                       e.transportista,
                       e.conductor,
                       e.placa_vehiculo,
                       e.destino,
                       e.tipovehiculo,
                       e.peso,
                       e.usuario_creacion,
                       e.usuario_despacho,
                       e.fecha_creacion,
                       e.fecha_despacho,
                       r.numero_rejilla,
                       
                       COUNT(ei.id) as total_items,
                       COALESCE(SUM(sps.peso_bruto), 0) as peso_total_bruto,
                       COALESCE(SUM(sps.peso_liquido), 0) as peso_total_liquido,
                       COALESCE(SUM(sps.tara), 0) as tara_total,
                       COUNT(DISTINCT sps.cliente) as total_clientes,
                       COUNT(DISTINCT sps.nombre_producto) as total_productos,
                       COUNT(DISTINCT sps.tipo_producto) as total_tipos_producto,
                       
                       COUNT(DISTINCT sps.id_orden_produccion) as ordenes_produccion,
                       COUNT(DISTINCT sps.id_venta) as ordenes_venta,
                       COUNT(DISTINCT sps.usuario) as usuarios_produccion,
                       COALESCE(SUM(sps.bobinas_pacote), 0) as total_bobinas,
                       COALESCE(SUM(sps.metragem), 0) as metragem_total,
                       
                       ROUND(AVG(sps.largura)::numeric, 2) as largura_promedio,
                       ROUND(AVG(sps.gramatura)::numeric, 2) as gramatura_promedio,
                       
                       MIN(sps.fecha_hora_producida) as fecha_produccion_mas_antigua,
                       MAX(sps.fecha_hora_producida) as fecha_produccion_mas_reciente,
                       
                       TO_CHAR(e.fecha_creacion, 'DD/MM/YYYY HH24:MI') as fecha_creacion_formateada,
                       TO_CHAR(e.fecha_despacho, 'DD/MM/YYYY HH24:MI') as fecha_despacho_formateada,
                       TO_CHAR(MIN(sps.fecha_hora_producida), 'DD/MM/YYYY HH24:MI') as fecha_produccion_mas_antigua_formateada,
                       TO_CHAR(MAX(sps.fecha_hora_producida), 'DD/MM/YYYY HH24:MI') as fecha_produccion_mas_reciente_formateada,
                       
                       EXTRACT(EPOCH FROM (e.fecha_despacho - e.fecha_creacion))/3600 as horas_transcurridas
                       
                   FROM sist_expediciones e
                   INNER JOIN sist_rejillas r ON e.id_rejilla = r.id
                   LEFT JOIN sist_expedicion_items ei ON e.numero_expedicion = ei.numero_expedicion
                   LEFT JOIN sist_prod_stock sps ON ei.id_stock = sps.id
                   
                   WHERE e.estado = 'DESPACHADA'";

            $params = [];

            if ($fechaInicio !== null) {
                $sql .= " AND DATE(e.fecha_despacho) >= :fecha_inicio";
                $params[':fecha_inicio'] = $fechaInicio;
            }

            if ($fechaFin !== null) {
                $sql .= " AND DATE(e.fecha_despacho) <= :fecha_fin";
                $params[':fecha_fin'] = $fechaFin;
            }

            if (!empty($transportista)) {
                $sql .= " AND LOWER(TRIM(e.transportista)) = LOWER(TRIM(:transportista))";
                $params[':transportista'] = $transportista;
            }

            if (!empty($codigoExpedicion)) {
                $sql .= " AND LOWER(e.numero_expedicion) LIKE LOWER(:codigo_expedicion)";
                $params[':codigo_expedicion'] = '%' . $codigoExpedicion . '%';
            }

            $sql .= " GROUP BY e.numero_expedicion, e.transportista, e.conductor, e.placa_vehiculo, 
                              e.destino, e.tipovehiculo, e.peso, e.usuario_creacion, e.usuario_despacho,
                              e.fecha_creacion, e.fecha_despacho, r.numero_rejilla
                      ORDER BY e.fecha_despacho DESC
                      LIMIT :limite OFFSET :offset";

            $params[':limite'] = $limite;
            $params[':offset'] = $offset;

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $param => $value) {
                if ($param === ':limite' || $param === ':offset') {
                    $stmt->bindValue($param, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($param, $value);
                }
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo expediciones despachadas: " . $e->getMessage());
            return [];
        }
    }

    public function contarExpedicionesDespachadas($fechaInicio, $fechaFin, $transportista = '', $codigoExpedicion = '')
    {
        try {
            $sql = "SELECT COUNT(DISTINCT e.numero_expedicion) as total
                   FROM sist_expediciones e
                   WHERE e.estado = 'DESPACHADA'";

            $params = [];

            if ($fechaInicio !== null) {
                $sql .= " AND DATE(e.fecha_despacho) >= :fecha_inicio";
                $params[':fecha_inicio'] = $fechaInicio;
            }

            if ($fechaFin !== null) {
                $sql .= " AND DATE(e.fecha_despacho) <= :fecha_fin";
                $params[':fecha_fin'] = $fechaFin;
            }

            if (!empty($transportista)) {
                $sql .= " AND LOWER(TRIM(e.transportista)) = LOWER(TRIM(:transportista))";
                $params[':transportista'] = $transportista;
            }
            if (!empty($codigoExpedicion)) {
                $sql .= " AND LOWER(e.numero_expedicion) LIKE LOWER(:codigo_expedicion)";
                $params[':codigo_expedicion'] = '%' . $codigoExpedicion . '%';
            }

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int)$resultado['total'];
        } catch (Exception $e) {
            error_log("Error contando expediciones: " . $e->getMessage());
            return 0;
        }
    }

    public function obtenerEstadisticasGenerales($fechaInicio, $fechaFin, $transportista = '', $codigoExpedicion = '')
    {
        try {
            $sql = "SELECT 
                       COUNT(DISTINCT e.numero_expedicion) as total_expediciones,
                       COUNT(ei.id) as total_items,
                       COUNT(DISTINCT sps.cliente) as clientes_unicos,
                       COUNT(DISTINCT sps.nombre_producto) as productos_unicos,
                       COUNT(DISTINCT e.transportista) as transportistas_unicos,
                       COUNT(DISTINCT sps.tipo_producto) as tipos_producto_unicos,
                       COUNT(DISTINCT sps.id_orden_produccion) as ordenes_produccion_unicas,
                       COUNT(DISTINCT sps.id_venta) as ordenes_venta_unicas,
                       COUNT(DISTINCT sps.usuario) as usuarios_produccion_unicos,
                       
                       COALESCE(SUM(sps.peso_bruto), 0) as peso_total_bruto,
                       COALESCE(SUM(sps.peso_liquido), 0) as peso_total_liquido,
                       COALESCE(SUM(sps.tara), 0) as tara_total,
                       COALESCE(SUM(sps.bobinas_pacote), 0) as total_bobinas,
                       COALESCE(SUM(sps.metragem), 0) as metragem_total,
                       
                       ROUND(AVG(sps.peso_bruto)::numeric, 2) as peso_promedio_item,
                       ROUND(AVG(sps.largura)::numeric, 2) as largura_promedio,
                       ROUND(AVG(sps.gramatura)::numeric, 2) as gramatura_promedio,
                       
                       MIN(e.fecha_despacho) as primer_despacho,
                       MAX(e.fecha_despacho) as ultimo_despacho,
                       MIN(sps.fecha_hora_producida) as primera_produccion,
                       MAX(sps.fecha_hora_producida) as ultima_produccion
                       
                   FROM sist_expediciones e
                   LEFT JOIN sist_expedicion_items ei ON e.numero_expedicion = ei.numero_expedicion
                   LEFT JOIN sist_prod_stock sps ON ei.id_stock = sps.id
                   
                   WHERE e.estado = 'DESPACHADA'";

            $params = [];

            if ($fechaInicio !== null) {
                $sql .= " AND DATE(e.fecha_despacho) >= :fecha_inicio";
                $params[':fecha_inicio'] = $fechaInicio;
            }

            if ($fechaFin !== null) {
                $sql .= " AND DATE(e.fecha_despacho) <= :fecha_fin";
                $params[':fecha_fin'] = $fechaFin;
            }

            if (!empty($transportista)) {
                $sql .= " AND LOWER(TRIM(e.transportista)) = LOWER(TRIM(:transportista))";
                $params[':transportista'] = $transportista;
            }

            if (!empty($codigoExpedicion)) {
                $sql .= " AND LOWER(e.numero_expedicion) LIKE LOWER(:codigo_expedicion)";
                $params[':codigo_expedicion'] = '%' . $codigoExpedicion . '%';
            }

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            $resultado['peso_total_bruto_formateado'] = number_format($resultado['peso_total_bruto'] ?? 0, 2) . ' kg';
            $resultado['peso_total_liquido_formateado'] = number_format($resultado['peso_total_liquido'] ?? 0, 2) . ' kg';
            $resultado['tara_total_formateada'] = number_format($resultado['tara_total'] ?? 0, 2) . ' kg';
            $resultado['metragem_total_formateada'] = number_format($resultado['metragem_total'] ?? 0) . ' m';

            return $resultado;
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [
                'total_expediciones' => 0,
                'total_items' => 0,
                'peso_total_bruto' => 0,
                'peso_total_liquido' => 0,
                'peso_total_bruto_formateado' => '0.00 kg',
                'peso_total_liquido_formateado' => '0.00 kg',
                'clientes_unicos' => 0,
                'productos_unicos' => 0,
                'tipos_producto_unicos' => 0,
                'transportistas_unicos' => 0
            ];
        }
    }

    public function obtenerTransportistasConDespachos()
    {
        try {
            $sql = "SELECT DISTINCT e.transportista
                   FROM sist_expediciones e
                   WHERE e.estado = 'DESPACHADA'
                   AND e.transportista IS NOT NULL 
                   AND TRIM(e.transportista) != ''
                   ORDER BY e.transportista";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo transportistas: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerItemsExpedicion($numeroExpedicion)
    {
        try {
            $sql = "SELECT 
                       sps.nombre_producto,
                       sps.tipo_producto,
                       sps.cliente,
                       sps.numero_item,
                       sps.peso_bruto,
                       sps.peso_liquido,
                       sps.tara,
                       sps.metragem,
                       sps.largura,
                       sps.gramatura,
                       sps.bobinas_pacote,
                       sps.id_orden_produccion,
                       sps.id_venta,
                       sps.usuario,
                       sps.fecha_hora_producida,
                       TO_CHAR(sps.fecha_hora_producida, 'DD/MM/YYYY HH24:MI') as fecha_produccion_formateada,
                       ei.cantidad_escaneada,
                       ei.es_desconocido,
                       ei.modo_asignacion,
                       ei.fecha_escaneado,
                       ei.usuario_escaneo
                       
                   FROM sist_expedicion_items ei
                   INNER JOIN sist_prod_stock sps ON ei.id_stock = sps.id
                   WHERE ei.numero_expedicion = :numero_expedicion
                   ORDER BY sps.nombre_producto, sps.cliente, sps.numero_item";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero_expedicion', $numeroExpedicion);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo items de expedición: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerResumenItemsExpedicion($numeroExpedicion)
    {
        try {
            $sql = "SELECT 
                       sps.nombre_producto,
                       sps.tipo_producto,
                       STRING_AGG(DISTINCT sps.cliente, ', ' ORDER BY sps.cliente) as clientes_list,
                       STRING_AGG(DISTINCT CAST(sps.id_orden_produccion AS VARCHAR), ', ' ORDER BY CAST(sps.id_orden_produccion AS VARCHAR)) as ordenes_produccion_list,
                       STRING_AGG(DISTINCT CAST(sps.id_venta AS VARCHAR), ', ' ORDER BY CAST(sps.id_venta AS VARCHAR)) as ordenes_venta_list,
                       STRING_AGG(DISTINCT sps.usuario, ', ' ORDER BY sps.usuario) as usuarios_produccion_list,
                       
                       COUNT(ei.id) as cantidad_items,
                       SUM(ei.cantidad_escaneada) as cantidad_escaneada,
                       SUM(sps.bobinas_pacote) as total_bobinas,
                       
                       SUM(sps.peso_bruto) as peso_total_bruto,
                       SUM(sps.peso_liquido) as peso_total_liquido,
                       SUM(sps.tara) as tara_total,
                       
                       sps.metragem as metragem,
                       ROUND(AVG(sps.largura)::numeric, 2) as largura_promedio,
                       ROUND(AVG(sps.gramatura)::numeric, 2) as gramatura_promedio,
                       
                       MIN(sps.fecha_hora_producida) as fecha_produccion_mas_antigua,
                       MAX(sps.fecha_hora_producida) as fecha_produccion_mas_reciente,
                       TO_CHAR(MIN(sps.fecha_hora_producida), 'DD/MM/YYYY HH24:MI') as fecha_produccion_mas_antigua_formateada,
                       TO_CHAR(MAX(sps.fecha_hora_producida), 'DD/MM/YYYY HH24:MI') as fecha_produccion_mas_reciente_formateada,
                       
                       STRING_AGG(CAST(sps.numero_item AS VARCHAR), ', ' ORDER BY sps.numero_item) as numeros_items
                       
                   FROM sist_expedicion_items ei
                   INNER JOIN sist_prod_stock sps ON ei.id_stock = sps.id
                   WHERE ei.numero_expedicion = :numero_expedicion
                   GROUP BY sps.nombre_producto, sps.tipo_producto, sps.metragem
                   ORDER BY sps.nombre_producto, sps.tipo_producto, sps.metragem";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero_expedicion', $numeroExpedicion);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo resumen de items: " . $e->getMessage());
            return [];
        }
    }

    public function verificarExpedicionDespachada($numeroExpedicion)
    {
        try {
            $sql = "SELECT 
                       e.numero_expedicion,
                       e.estado,
                       e.fecha_despacho,
                       e.transportista,
                       r.numero_rejilla
                   FROM sist_expediciones e
                   LEFT JOIN sist_rejillas r ON e.id_rejilla = r.id
                   WHERE e.numero_expedicion = :numero_expedicion
                   AND e.estado = 'DESPACHADA'";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero_expedicion', $numeroExpedicion);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error verificando expedición: " . $e->getMessage());
            return false;
        }
    }
}
