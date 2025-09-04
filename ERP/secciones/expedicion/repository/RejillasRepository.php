<?php

class RejillasRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
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
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    
    CASE WHEN COUNT(se.id) > 0 THEN true ELSE false END as tiene_expedicion_abierta,
    
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
LEFT JOIN sist_expediciones se ON r.id = se.id_rejilla 
    AND se.estado = 'ABIERTA'

GROUP BY r.id, r.numero_rejilla, r.capacidad_maxima, r.peso_actual, r.estado, 
         r.fecha_creacion, r.fecha_actualizacion, r.observaciones, r.ubicacion, r.descripcion
ORDER BY r.id ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            $rejillas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rejillas as &$rejilla) {
                $rejilla['peso_total_producido'] = 0;
                $rejilla['cantidad_total_producida_historica'] = 0;

                error_log("DEBUG: Procesando rejilla " . $rejilla['numero_rejilla'] . " (ID: " . $rejilla['id'] . ")");

                $sqlVentas = "SELECT DISTINCT ra.id_venta, ra.cliente 
                         FROM sist_rejillas_asignaciones ra 
                         WHERE ra.id_rejilla = ? AND ra.estado_asignacion = 'activa'";

                $stmtVentas = $this->conexion->prepare($sqlVentas);
                $stmtVentas->execute([$rejilla['id']]);
                $ventas = $stmtVentas->fetchAll(PDO::FETCH_ASSOC);

                error_log("DEBUG: Rejilla " . $rejilla['numero_rejilla'] . " tiene " . count($ventas) . " ventas asignadas");

                $pesoTotalProducido = 0;
                $bobinasTotal = 0;

                foreach ($ventas as $venta) {
                    $sqlOrden = "SELECT id, estado FROM sist_ventas_orden_produccion 
                           WHERE id_venta = ? AND cliente = ?";
                    $stmtOrden = $this->conexion->prepare($sqlOrden);
                    $stmtOrden->execute([$venta['id_venta'], $venta['cliente']]);
                    $orden = $stmtOrden->fetch(PDO::FETCH_ASSOC);

                    if ($orden) {
                        error_log("DEBUG: Venta " . $venta['id_venta'] . " tiene orden de producción ID: " . $orden['id'] . " Estado: " . $orden['estado']);

                        $sqlProd = "SELECT 
                                   COUNT(*) as items,
                                   SUM(COALESCE(peso_liquido, peso_bruto, 0)) as peso_total,
                                   SUM(COALESCE(bobinas_pacote, 1)) as bobinas_total
                               FROM sist_prod_stock 
                               WHERE id_orden_produccion = ?
                               AND COALESCE(peso_liquido, peso_bruto, 0) > 0";

                        $stmtProd = $this->conexion->prepare($sqlProd);
                        $stmtProd->execute([$orden['id']]);
                        $produccion = $stmtProd->fetch(PDO::FETCH_ASSOC);

                        if ($produccion && $produccion['items'] > 0) {
                            $pesoTotalProducido += floatval($produccion['peso_total']);
                            $bobinasTotal += intval($produccion['bobinas_total']);

                            error_log("DEBUG: Orden " . $orden['id'] . " tiene " . $produccion['items'] . " items producidos, peso: " . $produccion['peso_total'] . "kg, bobinas: " . $produccion['bobinas_total']);
                        } else {
                            error_log("DEBUG: Orden " . $orden['id'] . " NO tiene items de producción con peso");
                        }
                    } else {
                        error_log("DEBUG: Venta " . $venta['id_venta'] . " NO tiene orden de producción");
                    }
                }

                $rejilla['peso_total_producido'] = $pesoTotalProducido;
                $rejilla['cantidad_total_producida_historica'] = $bobinasTotal;

                error_log("DEBUG: Rejilla " . $rejilla['numero_rejilla'] . " TOTAL: peso=" . $pesoTotalProducido . "kg, bobinas=" . $bobinasTotal);
            }

            return $rejillas;
        } catch (Exception $e) {
            error_log("Error obteniendo rejillas detalladas: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerItemsAsignadosRejilla($idRejilla)
    {
        try {
            $sql = "SELECT 
                ra.id,
                ra.tipo_origen,
                ra.peso_asignado,
                ra.fecha_asignacion,
                ra.usuario_asignacion,
                ra.cantidad_asignada,
                ra.cantidad_reservada,
                ra.observaciones as observaciones_asignacion,
                ra.nombre_producto,
                ra.estado_asignacion,
                ra.cliente,
                
                COALESCE(ra.cant_uni, 0) as cantidad_unidades_asignadas,
                COALESCE(ra.peso_unitario, 0) as peso_unitario,
                COALESCE(ra.tipo_unidad, 'unidades') as tipo_unidad,
                
                COALESCE(ra.despachado, 0) as despachado,
                COALESCE(ra.peso_despachado, 0) as peso_despachado,
                
                vp.cliente as cliente_presupuesto,
                vp.fecha_venta,
                vp.id as id_presupuesto,
                
                vpp.descripcion as nombre_producto_presupuesto,
                vpp.tipoproducto as tipo_producto,
                vpp.cantidad as cantidad_presupuestada,
                vpp.precio,
                vpp.total,
                vpp.unidadmedida,
                
                svp.cantidad as peso_unitario_real,
                
                ra.id_venta,
                ra.id_producto_presupuesto,
                
                ROUND((ra.peso_asignado / r.capacidad_maxima) * 100, 2) as porcentaje_rejilla,
                
                COALESCE(prod_real.items_producidos, 0) as items_producidos,
                COALESCE(prod_real.bobinas_total_producidas, 0) as cantidad_producida,
                COALESCE(prod_real.bobinas_total_producidas, 0) as cantidad_total_producida_historica,
                COALESCE(prod_real.bobinas_despachadas, 0) as cantidad_despachada_stock,
                COALESCE(prod_real.bobinas_pendientes, 0) as cantidad_pendiente_despacho,
                COALESCE(prod_real.peso_total_producido, 0) as peso_total_producido_real,
                prod_real.productos_producidos,
                prod_real.ultima_produccion,
                prod_real.cliente_produccion as cliente_reserva,
                prod_real.porcentaje_despachado,
                
                COALESCE(prod_real.estado_orden, 'Sin Orden') as estado_produccion_orden
                
            FROM sist_rejillas_asignaciones ra
            INNER JOIN sist_rejillas r ON ra.id_rejilla = r.id
            INNER JOIN sist_ventas_presupuesto vp ON ra.id_venta = vp.id
            INNER JOIN sist_ventas_pres_product vpp ON ra.id_producto_presupuesto = vpp.id
            LEFT JOIN sist_ventas_productos svp ON vpp.id_producto = svp.id
            
            LEFT JOIN (
                SELECT 
                    svop.id_venta,
                    svop.cliente as cliente_produccion,
                    svop.estado as estado_orden,
                    sps.nombre_producto,
                    
                    COUNT(*) as items_producidos,
                    COUNT(CASE WHEN sps.estado = 'despachado' THEN 1 END) as items_despachados,
                    COUNT(CASE WHEN COALESCE(sps.estado, 'pendiente') != 'despachado' THEN 1 END) as items_pendientes,
                    
                    SUM(COALESCE(sps.peso_liquido, sps.peso_bruto, 0)) as peso_total_producido,
                    SUM(CASE WHEN sps.estado = 'despachado' THEN COALESCE(sps.peso_liquido, sps.peso_bruto, 0) ELSE 0 END) as peso_despachado,
                    
                    SUM(COALESCE(sps.bobinas_pacote, 1)) as bobinas_total_producidas,
                    SUM(CASE WHEN sps.estado = 'despachado' THEN COALESCE(sps.bobinas_pacote, 1) ELSE 0 END) as bobinas_despachadas,
                    SUM(CASE WHEN COALESCE(sps.estado, 'pendiente') != 'despachado' THEN COALESCE(sps.bobinas_pacote, 1) ELSE 0 END) as bobinas_pendientes,
                    
                    STRING_AGG(DISTINCT sps.nombre_producto, ', ' ORDER BY sps.nombre_producto) as productos_producidos,
                    MAX(sps.fecha_hora_producida) as ultima_produccion,
                    
                    CASE 
                        WHEN SUM(COALESCE(sps.bobinas_pacote, 1)) > 0 THEN 
                            ROUND((SUM(CASE WHEN sps.estado = 'despachado' THEN COALESCE(sps.bobinas_pacote, 1) ELSE 0 END)::DECIMAL / SUM(COALESCE(sps.bobinas_pacote, 1))) * 100, 1)
                        ELSE 0
                    END as porcentaje_despachado
                    
                FROM sist_ventas_orden_produccion svop
                INNER JOIN sist_prod_stock sps ON svop.id = sps.id_orden_produccion
                GROUP BY svop.id_venta, svop.cliente, svop.estado, sps.nombre_producto
                
            ) prod_real ON vp.id = prod_real.id_venta 
                        AND COALESCE(ra.cliente, vp.cliente) = COALESCE(prod_real.cliente_produccion, '')
                        AND ra.nombre_producto = prod_real.nombre_producto
                        
            WHERE ra.id_rejilla = :id_rejilla 
                AND ra.tipo_origen = 'reserva_presupuesto'
                AND ra.estado_asignacion = 'activa'
            ORDER BY ra.fecha_asignacion DESC, COALESCE(ra.cliente, vp.cliente) ASC, vpp.descripcion ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_rejilla', $idRejilla, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo items asignados a rejilla: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerItemsCompletadosRejilla($idRejilla)
    {
        try {
            $sql = "SELECT 
                    ra.id,
                    ra.tipo_origen,
                    ra.peso_asignado,
                    ra.fecha_asignacion,
                    ra.usuario_asignacion,
                    ra.cantidad_asignada,
                    ra.cantidad_reservada,
                    ra.observaciones as observaciones_asignacion,
                    ra.nombre_producto,
                    ra.estado_asignacion,
                    ra.cliente,
                    
                    COALESCE(ra.cant_uni, 0) as cantidad_unidades_asignadas,
                    COALESCE(ra.peso_unitario, 0) as peso_unitario,
                    COALESCE(ra.tipo_unidad, 'unidades') as tipo_unidad,
                    
                    COALESCE(ra.despachado, 0) as despachado,
                    COALESCE(ra.peso_despachado, 0) as peso_despachado,
                    
                    vp.cliente as cliente_presupuesto,
                    vp.fecha_venta,
                    vp.id as id_presupuesto,
                    
                    vpp.descripcion as nombre_producto_presupuesto,
                    vpp.tipoproducto as tipo_producto,
                    vpp.cantidad as cantidad_presupuestada,
                    vpp.precio,
                    vpp.total,
                    vpp.unidadmedida,
                    
                    svp.cantidad as peso_unitario_real,
                    
                    ra.id_venta,
                    ra.id_producto_presupuesto
                    
                FROM sist_rejillas_asignaciones ra
                INNER JOIN sist_rejillas r ON ra.id_rejilla = r.id
                INNER JOIN sist_ventas_presupuesto vp ON ra.id_venta = vp.id
                INNER JOIN sist_ventas_pres_product vpp ON ra.id_producto_presupuesto = vpp.id
                LEFT JOIN sist_ventas_productos svp ON vpp.id_producto = svp.id
                
                WHERE ra.id_rejilla = :id_rejilla 
                    AND ra.tipo_origen = 'reserva_presupuesto'
                    AND ra.estado_asignacion = 'completada'
                ORDER BY ra.fecha_asignacion DESC, COALESCE(ra.cliente, vp.cliente) ASC, vpp.descripcion ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_rejilla', $idRejilla, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo items completados de rejilla: " . $e->getMessage());
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

    public function marcarItemComoCompletado($idAsignacion, $observaciones = null)
    {
        try {
            $sqlSelect = "SELECT observaciones FROM sist_rejillas_asignaciones WHERE id = ?";
            $stmtSelect = $this->conexion->prepare($sqlSelect);
            $stmtSelect->execute([$idAsignacion]);
            $result = $stmtSelect->fetch(PDO::FETCH_ASSOC);

            $observacionesActuales = $result['observaciones'] ?? '';
            $nuevaObservacion = '';

            if ($observaciones !== null && trim($observaciones) !== '') {
                $nuevaObservacion = 'COMPLETADO: ' . trim($observaciones);
            } else {
                $nuevaObservacion = 'COMPLETADO';
            }

            $observacionesFinales = '';
            if (!empty($observacionesActuales)) {
                $observacionesFinales = $observacionesActuales . "\n" . $nuevaObservacion;
            } else {
                $observacionesFinales = $nuevaObservacion;
            }

            $sql = "UPDATE sist_rejillas_asignaciones 
                   SET estado_asignacion = ?,
                       observaciones = ?
                   WHERE id = ? 
                   AND estado_asignacion = 'activa'";

            $stmt = $this->conexion->prepare($sql);
            $resultado = $stmt->execute(['completada', $observacionesFinales, $idAsignacion]);

            if ($resultado && $stmt->rowCount() > 0) {
                return [
                    'exito' => true,
                    'mensaje' => 'Item marcado como COMPLETADO correctamente',
                    'filas_afectadas' => $stmt->rowCount()
                ];
            } else {
                return [
                    'exito' => false,
                    'mensaje' => 'No se pudo marcar el item como completado o ya estaba completado'
                ];
            }
        } catch (Exception $e) {
            error_log("Error marcando item como completado: " . $e->getMessage());
            return [
                'exito' => false,
                'mensaje' => 'Error interno al marcar el item como completado'
            ];
        }
    }

    public function reactivarItemCompletado($idAsignacion, $observaciones = null)
    {
        try {
            $sqlSelect = "SELECT observaciones FROM sist_rejillas_asignaciones WHERE id = ?";
            $stmtSelect = $this->conexion->prepare($sqlSelect);
            $stmtSelect->execute([$idAsignacion]);
            $result = $stmtSelect->fetch(PDO::FETCH_ASSOC);

            $observacionesActuales = $result['observaciones'] ?? '';
            $nuevaObservacion = '';

            if ($observaciones !== null && trim($observaciones) !== '') {
                $nuevaObservacion = 'REACTIVADO: ' . trim($observaciones);
            } else {
                $nuevaObservacion = 'REACTIVADO';
            }

            $observacionesFinales = '';
            if (!empty($observacionesActuales)) {
                $observacionesFinales = $observacionesActuales . "\n" . $nuevaObservacion;
            } else {
                $observacionesFinales = $nuevaObservacion;
            }

            $sql = "UPDATE sist_rejillas_asignaciones 
                   SET estado_asignacion = ?,
                       observaciones = ?
                   WHERE id = ? 
                   AND estado_asignacion = 'completada'";

            $stmt = $this->conexion->prepare($sql);
            $resultado = $stmt->execute(['activa', $observacionesFinales, $idAsignacion]);

            if ($resultado && $stmt->rowCount() > 0) {
                return [
                    'exito' => true,
                    'mensaje' => 'Item reactivado correctamente',
                    'filas_afectadas' => $stmt->rowCount()
                ];
            } else {
                return [
                    'exito' => false,
                    'mensaje' => 'No se pudo reactivar el item o no estaba completado'
                ];
            }
        } catch (Exception $e) {
            error_log("Error reactivando item: " . $e->getMessage());
            return [
                'exito' => false,
                'mensaje' => 'Error interno al reactivar el item'
            ];
        }
    }

    public function limpiarAsignacion($idAsignacion, $observaciones = null)
    {
        try {
            $sql = "SELECT id_rejilla, peso_asignado, id_venta, id_producto_presupuesto, estado_asignacion,
                       COALESCE(cant_uni, 0) as cant_uni,
                       COALESCE(despachado, 0) as despachado, 
                       COALESCE(peso_unitario, 0) as peso_unitario,
                       observaciones
               FROM sist_rejillas_asignaciones 
               WHERE id = ? 
               AND estado_asignacion = 'activa'";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([$idAsignacion]);

            $asignacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$asignacion) {
                return [
                    'exito' => false,
                    'mensaje' => 'Asignación no encontrada o ya no está activa'
                ];
            }

            $this->beginTransaction();

            $observacionesActuales = $asignacion['observaciones'] ?? '';
            $nuevaObservacion = '';

            if ($observaciones !== null && trim($observaciones) !== '') {
                $nuevaObservacion = 'cancelada: ' . trim($observaciones);
            } else {
                $nuevaObservacion = 'cancelada por usuario';
            }

            $observacionesFinales = '';
            if (!empty($observacionesActuales)) {
                $observacionesFinales = $observacionesActuales . "\n" . $nuevaObservacion;
            } else {
                $observacionesFinales = $nuevaObservacion;
            }

            $sqlUpdate = "UPDATE sist_rejillas_asignaciones 
                         SET estado_asignacion = ?,
                             observaciones = ?
                         WHERE id = ?";

            $stmtUpdate = $this->conexion->prepare($sqlUpdate);
            $resultadoUpdate = $stmtUpdate->execute([
                'cancelada',
                $observacionesFinales,
                $idAsignacion
            ]);

            if (!$resultadoUpdate || $stmtUpdate->rowCount() === 0) {
                throw new Exception('No se pudo actualizar el estado de la asignación');
            }

            $cantidadTotal = floatval($asignacion['cant_uni']);
            $cantidadDespachada = floatval($asignacion['despachado']);
            $pesoUnitario = floatval($asignacion['peso_unitario']);

            $unidadesPendientes = max(0, $cantidadTotal - $cantidadDespachada);
            $pesoALiberar = $unidadesPendientes * $pesoUnitario;

            if ($pesoALiberar > 0) {
                $this->liberarPesoRejilla($asignacion['id_rejilla'], $pesoALiberar);
            }

            $this->resetearMovimientoProduccion($asignacion['id_venta'], $asignacion['id_producto_presupuesto']);
            $this->resetearMovimientoExpedicion($asignacion['id_venta'], $asignacion['id_producto_presupuesto']);

            $this->commit();

            return [
                'exito' => true,
                'mensaje' => "Asignación limpiada correctamente. Liberado: {$pesoALiberar} kg ({$unidadesPendientes} unidades pendientes).",
                'peso_liberado' => $pesoALiberar,
                'unidades_liberadas' => $unidadesPendientes,
                'peso_original_asignado' => $asignacion['peso_asignado'],
                'id_rejilla' => $asignacion['id_rejilla']
            ];
        } catch (Exception $e) {
            $this->rollBack();
            error_log("Error limpiando asignación: " . $e->getMessage());
            return [
                'exito' => false,
                'mensaje' => 'Error interno al limpiar la asignación: ' . $e->getMessage()
            ];
        }
    }

    public function actualizarDescripcionRejilla($idRejilla, $descripcion)
    {
        try {
            $sql = "UPDATE sist_rejillas 
                    SET descripcion = ?,
                        fecha_actualizacion = CURRENT_TIMESTAMP
                    WHERE id = ?";

            $stmt = $this->conexion->prepare($sql);
            $resultado = $stmt->execute([
                trim($descripcion),
                $idRejilla
            ]);

            if ($resultado && $stmt->rowCount() > 0) {
                return [
                    'exito' => true,
                    'mensaje' => 'Descripción actualizada correctamente',
                    'filas_afectadas' => $stmt->rowCount()
                ];
            } else {
                return [
                    'exito' => false,
                    'mensaje' => 'No se pudo actualizar la descripción o la rejilla no existe'
                ];
            }
        } catch (Exception $e) {
            error_log("Error actualizando descripción de rejilla: " . $e->getMessage());
            return [
                'exito' => false,
                'mensaje' => 'Error interno al actualizar la descripción'
            ];
        }
    }

    private function liberarPesoRejilla($idRejilla, $pesoALiberar)
    {
        try {
            $sqlSelect = "SELECT peso_actual, capacidad_maxima FROM sist_rejillas WHERE id = ?";
            $stmt = $this->conexion->prepare($sqlSelect);
            $stmt->execute([$idRejilla]);
            $rejilla = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rejilla) {
                throw new Exception("Rejilla no encontrada: $idRejilla");
            }

            $pesoActual = floatval($rejilla['peso_actual']);
            $nuevoPeso = max(0, $pesoActual - $pesoALiberar);
            $capacidadMaxima = floatval($rejilla['capacidad_maxima']);

            $nuevoEstado = '';
            if ($nuevoPeso <= 0) {
                $nuevoEstado = 'disponible';
            } elseif ($nuevoPeso < $capacidadMaxima) {
                $nuevoEstado = 'ocupada';
            } else {
                $nuevoEstado = 'llena';
            }

            $sqlUpdate = "UPDATE sist_rejillas 
                         SET peso_actual = ?,
                             estado = ?
                         WHERE id = ?";

            $stmtUpdate = $this->conexion->prepare($sqlUpdate);
            $resultado = $stmtUpdate->execute([$nuevoPeso, $nuevoEstado, $idRejilla]);

            if ($resultado) {
                error_log("Peso liberado en rejilla $idRejilla: $pesoALiberar kg (Peso anterior: $pesoActual kg, Nuevo peso: $nuevoPeso kg, Estado: $nuevoEstado)");
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("Error liberando peso de rejilla: " . $e->getMessage());
            throw $e;
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
