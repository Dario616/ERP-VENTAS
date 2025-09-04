<?php

class HistorialRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }
    public function contarTotalHistorial($filtros = [])
    {
        try {
            $sql = "SELECT COUNT(*) as total 
                    FROM sist_rejillas_asignaciones ra
                    INNER JOIN sist_rejillas r ON ra.id_rejilla = r.id
                    LEFT JOIN sist_ventas_presupuesto vp ON ra.id_venta = vp.id
                    LEFT JOIN sist_ventas_pres_product vpp ON ra.id_producto_presupuesto = vpp.id
                    WHERE ra.tipo_origen = 'reserva_presupuesto'";

            $params = [];

            $sql .= $this->construirFiltrosSQL($filtros, $params);

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int)$result['total'];
        } catch (Exception $e) {
            error_log("Error contando historial: " . $e->getMessage());
            return 0;
        }
    }
    public function obtenerHistorialAsignaciones($filtros = [], $pagina = 1, $porPagina = 20)
    {
        try {
            $offset = ($pagina - 1) * $porPagina;

            $sql = "SELECT 
                        ra.id,
                        ra.id_rejilla,
                        ra.id_venta,
                        ra.id_producto_presupuesto,
                        ra.peso_asignado,
                        ra.fecha_asignacion,
                        ra.usuario_asignacion,
                        ra.cantidad_asignada,
                        ra.cantidad_reservada,
                        ra.observaciones,
                        ra.nombre_producto,
                        ra.estado_asignacion,
                        ra.cliente as cliente_asignacion,
                                                COALESCE(ra.cant_uni, 0) as cantidad_unidades,
                        COALESCE(ra.cant_uni, 0) as cant_uni,
                        COALESCE(ra.peso_unitario, 0) as peso_unitario,
                        COALESCE(ra.tipo_unidad, 'unidades') as tipo_unidad,
                                                COALESCE(ra.despachado, 0) as despachado,
                        COALESCE(ra.peso_despachado, 0) as peso_despachado,
                        
                        ra.fecha_completado,
                        ra.usuario_completado,
                        ra.fecha_cancelado,
                        
                        r.numero_rejilla,
                        r.capacidad_maxima as capacidad_rejilla,
                        r.ubicacion as ubicacion_rejilla,
                        
                        vp.cliente as cliente_presupuesto,
                        vp.fecha_venta,
                        vp.id as id_presupuesto,
                        
                        vpp.descripcion as descripcion_producto,
                        vpp.tipoproducto as tipo_producto,
                        vpp.cantidad as cantidad_presupuestada,
                        vpp.precio,
                        vpp.total as total_presupuesto,
                        vpp.unidadmedida,
                        EXTRACT(DAYS FROM (CURRENT_TIMESTAMP - ra.fecha_asignacion)) as dias_desde_asignacion,
                                                CASE 
                            WHEN ra.estado_asignacion = 'completada' THEN 'completado'
                            WHEN ra.estado_asignacion = 'cancelada' THEN 'cancelado'
                            WHEN EXTRACT(DAYS FROM (CURRENT_TIMESTAMP - ra.fecha_asignacion)) > 30 THEN 'antiguo'
                            WHEN EXTRACT(DAYS FROM (CURRENT_TIMESTAMP - ra.fecha_asignacion)) > 7 THEN 'medio'
                            ELSE 'reciente'
                        END as estado_temporal
                        
                    FROM sist_rejillas_asignaciones ra
                    INNER JOIN sist_rejillas r ON ra.id_rejilla = r.id
                    LEFT JOIN sist_ventas_presupuesto vp ON ra.id_venta = vp.id
                    LEFT JOIN sist_ventas_pres_product vpp ON ra.id_producto_presupuesto = vpp.id
                    WHERE ra.tipo_origen = 'reserva_presupuesto'";

            $params = [];

            $sql .= $this->construirFiltrosSQL($filtros, $params);

            $sql .= " ORDER BY ra.fecha_asignacion DESC, ra.id DESC";

            $sql .= " LIMIT :limit OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo historial de asignaciones: " . $e->getMessage());
            return [];
        }
    }

    private function construirFiltrosSQL($filtros, &$params)
    {
        $sql = "";

        if (!empty($filtros['fecha_inicio'])) {
            $sql .= " AND DATE(ra.fecha_asignacion) >= :fecha_inicio";
            $params[':fecha_inicio'] = $filtros['fecha_inicio'];
        }

        if (!empty($filtros['fecha_fin'])) {
            $sql .= " AND DATE(ra.fecha_asignacion) <= :fecha_fin";
            $params[':fecha_fin'] = $filtros['fecha_fin'];
        }

        if (!empty($filtros['cliente'])) {
            $sql .= " AND (LOWER(COALESCE(ra.cliente, vp.cliente)) LIKE LOWER(:cliente))";
            $params[':cliente'] = '%' . $filtros['cliente'] . '%';
        }

        if (!empty($filtros['estado'])) {
            $sql .= " AND ra.estado_asignacion = :estado";
            $params[':estado'] = $filtros['estado'];
        }

        if (!empty($filtros['rejilla'])) {
            $sql .= " AND r.numero_rejilla = :rejilla";
            $params[':rejilla'] = $filtros['rejilla'];
        }

        if (!empty($filtros['usuario'])) {
            $sql .= " AND LOWER(ra.usuario_asignacion) LIKE LOWER(:usuario)";
            $params[':usuario'] = '%' . $filtros['usuario'] . '%';
        }

        if (!empty($filtros['producto'])) {
            $sql .= " AND (LOWER(ra.nombre_producto) LIKE LOWER(:producto) OR LOWER(vpp.descripcion) LIKE LOWER(:producto))";
            $params[':producto'] = '%' . $filtros['producto'] . '%';
        }

        return $sql;
    }

    public function obtenerDetalleCompletoAsignacion($idAsignacion)
    {
        try {
            $sql = "SELECT 
                        ra.*,
                        
                        r.numero_rejilla,
                        r.capacidad_maxima,
                        r.peso_actual,
                        r.estado as estado_rejilla,
                        r.ubicacion,
                        
                        vp.cliente as cliente_presupuesto,
                        vp.fecha_venta,
                        vp.proforma as numero_presupuesto,
                        vp.monto_total as total_presupuesto,
                        
                        vpp.descripcion as descripcion_producto,
                        vpp.tipoproducto,
                        vpp.cantidad as cantidad_presupuestada,
                        vpp.precio,
                        vpp.total,
                        vpp.unidadmedida,
                        
                        svp.cantidad as peso_unitario_real,
                        
                        EXTRACT(DAYS FROM (CURRENT_TIMESTAMP - ra.fecha_asignacion)) as dias_desde_asignacion,
                        EXTRACT(HOURS FROM (CURRENT_TIMESTAMP - ra.fecha_asignacion)) as horas_desde_asignacion
                        
                    FROM sist_rejillas_asignaciones ra
                    INNER JOIN sist_rejillas r ON ra.id_rejilla = r.id
                    LEFT JOIN sist_ventas_presupuesto vp ON ra.id_venta = vp.id
                    LEFT JOIN sist_ventas_pres_product vpp ON ra.id_producto_presupuesto = vpp.id
                    LEFT JOIN sist_ventas_productos svp ON LOWER(TRIM(vpp.descripcion)) = LOWER(TRIM(svp.descripcion))
                    WHERE ra.id = :id_asignacion";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_asignacion', $idAsignacion, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo detalle de asignación: " . $e->getMessage());
            return null;
        }
    }

    public function obtenerHistorialEstadosAsignacion($idAsignacion)
    {
        try {
            $sql = "SELECT 
                        id,
                        estado_asignacion,
                        fecha_asignacion,
                        usuario_asignacion,
                        observaciones
                    FROM sist_rejillas_asignaciones 
                    WHERE id = :id_asignacion";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_asignacion', $idAsignacion, PDO::PARAM_INT);
            $stmt->execute();

            $asignacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$asignacion) {
                return [];
            }
            $historial = [];
            $historial[] = [
                'fecha' => $asignacion['fecha_asignacion'],
                'estado_anterior' => null,
                'estado_nuevo' => 'activa',
                'usuario' => $asignacion['usuario_asignacion'],
                'observaciones' => 'Asignación inicial',
                'tipo_cambio' => 'creacion'
            ];

            if (!empty($asignacion['observaciones'])) {
                $observaciones = $asignacion['observaciones'];

                if (strpos($observaciones, 'COMPLETADO') !== false) {
                    $historial[] = [
                        'fecha' => $asignacion['fecha_asignacion'],
                        'estado_anterior' => 'activa',
                        'estado_nuevo' => 'completada',
                        'usuario' => $asignacion['usuario_asignacion'],
                        'observaciones' => 'Marcado como completado',
                        'tipo_cambio' => 'completado'
                    ];
                }

                if (strpos($observaciones, 'cancelada') !== false || strpos($observaciones, 'CANCELADA') !== false) {
                    $historial[] = [
                        'fecha' => $asignacion['fecha_asignacion'], // Aproximado
                        'estado_anterior' => 'activa',
                        'estado_nuevo' => 'cancelada',
                        'usuario' => $asignacion['usuario_asignacion'],
                        'observaciones' => 'Asignación cancelada',
                        'tipo_cambio' => 'cancelacion'
                    ];
                }

                if (strpos($observaciones, 'REACTIVADO') !== false) {
                    $historial[] = [
                        'fecha' => $asignacion['fecha_asignacion'], // Aproximado
                        'estado_anterior' => 'completada',
                        'estado_nuevo' => 'activa',
                        'usuario' => $asignacion['usuario_asignacion'],
                        'observaciones' => 'Reactivado desde completado',
                        'tipo_cambio' => 'reactivacion'
                    ];
                }
            }

            return $historial;
        } catch (Exception $e) {
            error_log("Error obteniendo historial de estados: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerEstadisticasGenerales($filtros = [])
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_asignaciones,
                        COUNT(CASE WHEN ra.estado_asignacion = 'activa' THEN 1 END) as asignaciones_activas,
                        COUNT(CASE WHEN ra.estado_asignacion = 'completada' THEN 1 END) as asignaciones_completadas,
                        COUNT(CASE WHEN ra.estado_asignacion = 'cancelada' THEN 1 END) as asignaciones_canceladas,
                        
                        SUM(ra.peso_asignado) as peso_total_asignado,
                        SUM(COALESCE(ra.cant_uni, 0)) as unidades_totales_asignadas,
                        AVG(ra.peso_asignado) as peso_promedio_asignacion,
                        AVG(COALESCE(ra.cant_uni, 0)) as unidades_promedio_asignacion,
                        
                        COUNT(DISTINCT ra.id_rejilla) as rejillas_utilizadas,
                        COUNT(DISTINCT COALESCE(ra.cliente, vp.cliente)) as clientes_unicos,
                        COUNT(DISTINCT ra.usuario_asignacion) as usuarios_que_asignaron,
                        
                        COUNT(CASE WHEN DATE(ra.fecha_asignacion) = CURRENT_DATE THEN 1 END) as asignaciones_hoy,
                        COUNT(CASE WHEN ra.fecha_asignacion >= CURRENT_DATE - INTERVAL '7 days' THEN 1 END) as asignaciones_semana,
                        COUNT(CASE WHEN ra.fecha_asignacion >= CURRENT_DATE - INTERVAL '30 days' THEN 1 END) as asignaciones_mes,
                        
                        MIN(ra.fecha_asignacion) as primera_asignacion,
                        MAX(ra.fecha_asignacion) as ultima_asignacion
                        
                    FROM sist_rejillas_asignaciones ra
                    INNER JOIN sist_rejillas r ON ra.id_rejilla = r.id
                    LEFT JOIN sist_ventas_presupuesto vp ON ra.id_venta = vp.id
                    LEFT JOIN sist_ventas_pres_product vpp ON ra.id_producto_presupuesto = vpp.id
                    WHERE ra.tipo_origen = 'reserva_presupuesto'";

            $params = [];

            $sql .= $this->construirFiltrosSQL($filtros, $params);

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas generales: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerEstadisticasPorPeriodo($fechaInicio, $fechaFin)
    {
        try {
            $sql = "SELECT 
                        DATE(ra.fecha_asignacion) as fecha,
                        COUNT(*) as total_asignaciones,
                        COUNT(CASE WHEN ra.estado_asignacion = 'activa' THEN 1 END) as activas,
                        COUNT(CASE WHEN ra.estado_asignacion = 'completada' THEN 1 END) as completadas,
                        COUNT(CASE WHEN ra.estado_asignacion = 'cancelada' THEN 1 END) as canceladas,
                        SUM(ra.peso_asignado) as peso_total_dia,
                        SUM(COALESCE(ra.cant_uni, 0)) as unidades_total_dia,
                        COUNT(DISTINCT ra.id_rejilla) as rejillas_usadas_dia,
                        COUNT(DISTINCT COALESCE(ra.cliente, vp.cliente)) as clientes_unicos_dia
                        
                    FROM sist_rejillas_asignaciones ra
                    INNER JOIN sist_rejillas r ON ra.id_rejilla = r.id
                    LEFT JOIN sist_ventas_presupuesto vp ON ra.id_venta = vp.id
                    WHERE ra.tipo_origen = 'reserva_presupuesto'
                    AND DATE(ra.fecha_asignacion) BETWEEN :fecha_inicio AND :fecha_fin
                    GROUP BY DATE(ra.fecha_asignacion)
                    ORDER BY DATE(ra.fecha_asignacion) ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':fecha_inicio', $fechaInicio);
            $stmt->bindParam(':fecha_fin', $fechaFin);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas por período: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerClientesConHistorial()
    {
        try {
            $sql = "SELECT DISTINCT 
                        COALESCE(ra.cliente, vp.cliente) as cliente,
                        COUNT(*) as total_asignaciones
                    FROM sist_rejillas_asignaciones ra
                    LEFT JOIN sist_ventas_presupuesto vp ON ra.id_venta = vp.id
                    WHERE ra.tipo_origen = 'reserva_presupuesto'
                    AND COALESCE(ra.cliente, vp.cliente) IS NOT NULL
                    AND COALESCE(ra.cliente, vp.cliente) != ''
                    GROUP BY COALESCE(ra.cliente, vp.cliente)
                    ORDER BY total_asignaciones DESC, COALESCE(ra.cliente, vp.cliente) ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo clientes con historial: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerRejillasConHistorial()
    {
        try {
            $sql = "SELECT DISTINCT 
                        r.numero_rejilla,
                        COUNT(ra.id) as total_asignaciones
                    FROM sist_rejillas r
                    INNER JOIN sist_rejillas_asignaciones ra ON r.id = ra.id_rejilla
                    WHERE ra.tipo_origen = 'reserva_presupuesto'
                    GROUP BY r.numero_rejilla
                    ORDER BY total_asignaciones DESC, r.numero_rejilla ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo rejillas con historial: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerUsuariosConHistorial()
    {
        try {
            $sql = "SELECT DISTINCT 
                        ra.usuario_asignacion as usuario,
                        COUNT(*) as total_asignaciones
                    FROM sist_rejillas_asignaciones ra
                    WHERE ra.tipo_origen = 'reserva_presupuesto'
                    AND ra.usuario_asignacion IS NOT NULL
                    AND ra.usuario_asignacion != ''
                    GROUP BY ra.usuario_asignacion
                    ORDER BY total_asignaciones DESC, ra.usuario_asignacion ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo usuarios con historial: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerResumenActividadUsuarios($fechaInicio = null, $fechaFin = null)
    {
        try {
            $sql = "SELECT 
                        ra.usuario_asignacion as usuario,
                        COUNT(*) as total_asignaciones,
                        COUNT(CASE WHEN ra.estado_asignacion = 'activa' THEN 1 END) as asignaciones_activas,
                        COUNT(CASE WHEN ra.estado_asignacion = 'completada' THEN 1 END) as asignaciones_completadas,
                        COUNT(CASE WHEN ra.estado_asignacion = 'cancelada' THEN 1 END) as asignaciones_canceladas,
                        SUM(ra.peso_asignado) as peso_total_asignado,
                        SUM(COALESCE(ra.cant_uni, 0)) as unidades_totales_asignadas,
                        MIN(ra.fecha_asignacion) as primera_asignacion,
                        MAX(ra.fecha_asignacion) as ultima_asignacion
                        
                    FROM sist_rejillas_asignaciones ra
                    WHERE ra.tipo_origen = 'reserva_presupuesto'
                    AND ra.usuario_asignacion IS NOT NULL
                    AND ra.usuario_asignacion != ''";

            $params = [];

            if ($fechaInicio) {
                $sql .= " AND DATE(ra.fecha_asignacion) >= :fecha_inicio";
                $params[':fecha_inicio'] = $fechaInicio;
            }

            if ($fechaFin) {
                $sql .= " AND DATE(ra.fecha_asignacion) <= :fecha_fin";
                $params[':fecha_fin'] = $fechaFin;
            }

            $sql .= " GROUP BY ra.usuario_asignacion
                      ORDER BY total_asignaciones DESC, ra.usuario_asignacion ASC";

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo resumen de actividad por usuarios: " . $e->getMessage());
            return [];
        }
    }
}
