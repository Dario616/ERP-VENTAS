<?php

class StockFlexibleManager
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    public function cancelarReservaConflictiva($nombreProducto, $bobinasPackete, $bobinasRequeridas, $numeroExpedicion = null)
    {
        try {
            $this->conexion->beginTransaction();

            $sqlReserva = "
                SELECT r.id, r.cantidad_reservada, r.cliente, r.id_venta, sa.id as stock_agregado_id
                FROM reservas_stock r
                INNER JOIN stock_agregado sa ON r.id_stock_agregado = sa.id
                WHERE sa.nombre_producto = :nombre_producto
                AND sa.bobinas_pacote = :bobinas_pacote
                AND r.estado = 'activa'
                AND r.cantidad_reservada >= :bobinas_requeridas
                ORDER BY r.fecha_reserva ASC
                LIMIT 1";

            $stmt = $this->conexion->prepare($sqlReserva);
            $stmt->bindParam(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
            $stmt->bindParam(':bobinas_pacote', $bobinasPackete, PDO::PARAM_INT);
            $stmt->bindParam(':bobinas_requeridas', $bobinasRequeridas, PDO::PARAM_INT);
            $stmt->execute();
            $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reserva) {
                $this->conexion->rollback();
                return ['cancelado' => false, 'motivo' => 'No hay reservas que cancelar'];
            }

            $bobinasALiberar = min($reserva['cantidad_reservada'], $bobinasRequeridas);

            $sqlActualizarReserva = "
                UPDATE reservas_stock 
                SET cantidad_reservada = cantidad_reservada - :bobinas_liberar,
                    cantidad_cancelada = COALESCE(cantidad_cancelada, 0) + :bobinas_liberar,
                    fecha_cancelacion = CURRENT_TIMESTAMP,
                    observaciones = CONCAT(
                        COALESCE(observaciones, ''), 
                        ' | CANCELADO FLEXIBILIDAD: Expedición ', 
                        :numero_expedicion, ' - Bobinas: ', :bobinas_liberar
                    )
                WHERE id = :id_reserva";

            $stmt = $this->conexion->prepare($sqlActualizarReserva);
            $stmt->bindParam(':bobinas_liberar', $bobinasALiberar, PDO::PARAM_INT);
            $stmt->bindParam(':numero_expedicion', $numeroExpedicion, PDO::PARAM_STR);
            $stmt->bindParam(':id_reserva', $reserva['id'], PDO::PARAM_INT);
            $stmt->execute();

            $sqlMarcarCancelada = "
                UPDATE reservas_stock 
                SET estado = 'cancelada'
                WHERE id = :id_reserva AND cantidad_reservada <= 0";

            $stmt = $this->conexion->prepare($sqlMarcarCancelada);
            $stmt->bindParam(':id_reserva', $reserva['id'], PDO::PARAM_INT);
            $stmt->execute();

            $sqlLiberarStock = "
                UPDATE stock_agregado 
                SET cantidad_reservada = GREATEST(0, cantidad_reservada - :bobinas_liberar),
                    cantidad_disponible = cantidad_disponible + :bobinas_liberar,
                    fecha_actualizacion = CURRENT_TIMESTAMP
                WHERE id = :stock_agregado_id";

            $stmt = $this->conexion->prepare($sqlLiberarStock);
            $stmt->bindParam(':bobinas_liberar', $bobinasALiberar, PDO::PARAM_INT);
            $stmt->bindParam(':stock_agregado_id', $reserva['stock_agregado_id'], PDO::PARAM_INT);
            $stmt->execute();

            $this->conexion->commit();

            error_log("FLEXIBILIDAD - Reserva #{$reserva['id']} cancelada: {$bobinasALiberar} bobinas liberadas para {$nombreProducto}");

            return [
                'cancelado' => true,
                'reserva_cancelada' => $reserva['id'],
                'cliente_anterior' => $reserva['cliente'],
                'venta_anterior' => $reserva['id_venta'],
                'bobinas_liberadas' => $bobinasALiberar,
                'motivo' => 'Reserva cancelada automáticamente por flexibilidad'
            ];
        } catch (Exception $e) {
            $this->conexion->rollback();
            error_log("Error cancelando reserva: " . $e->getMessage());
            return ['cancelado' => false, 'motivo' => 'Error: ' . $e->getMessage()];
        }
    }

    public function despacharItem($nombreProducto, $bobinasPackete, $bobinasDespachar, $idProductoFisico = null, $idAsignacionRejilla = null)
    {
        try {
            $this->conexion->beginTransaction();

            $resultadoStock = $this->procesarDespachoStock($nombreProducto, $bobinasPackete, $bobinasDespachar, $idProductoFisico);

            if ($idAsignacionRejilla) {
                $this->procesarDespachoAsignacionRejilla($idAsignacionRejilla, $bobinasDespachar);
            }

            $this->conexion->commit();

            error_log("DESPACHO COMPLETO - {$nombreProducto}: {$bobinasDespachar} bobinas (Stock + Rejillas)");

            return [
                'success' => true,
                'origen_despacho' => $resultadoStock['origen'],
                'bobinas_despachadas' => $bobinasDespachar,
                'asignacion_rejilla_actualizada' => $idAsignacionRejilla ? true : false,
                'trigger_rejilla_disparado' => $idAsignacionRejilla ? true : false
            ];
        } catch (Exception $e) {
            $this->conexion->rollback();
            error_log("Error despachando item completo: " . $e->getMessage());
            throw $e;
        }
    }

    private function procesarDespachoStock($nombreProducto, $bobinasPackete, $bobinasDespachar, $idProductoFisico)
    {
        $sqlStock = "
        SELECT id, cantidad_disponible, cantidad_reservada, cantidad_despachada, cantidad_paquetes
        FROM stock_agregado
        WHERE nombre_producto = :nombre_producto 
        AND bobinas_pacote = :bobinas_pacote";

        $stmt = $this->conexion->prepare($sqlStock);
        $stmt->bindParam(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
        $stmt->bindParam(':bobinas_pacote', $bobinasPackete, PDO::PARAM_INT);
        $stmt->execute();
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stock) {
            throw new Exception("Producto no encontrado en stock agregado");
        }

        $stockDisponible = $stock['cantidad_disponible'];
        $stockReservado = $stock['cantidad_reservada'];
        $paquetesDespachar = 1;

        if ($stockReservado >= $bobinasDespachar) {
            $this->despacharDesdeReservas($nombreProducto, $bobinasPackete, $bobinasDespachar, $paquetesDespachar, $idProductoFisico);
            $origen = 'reservas';
            error_log("✅ DESPACHO DESDE RESERVAS - {$nombreProducto}: {$bobinasDespachar} bobinas (Reservado: {$stockReservado} → " . ($stockReservado - $bobinasDespachar) . ")");
        } elseif ($stockDisponible >= $bobinasDespachar) {
            $this->despacharDesdeStockDisponible($stock['id'], $bobinasDespachar, $paquetesDespachar, $idProductoFisico);
            $origen = 'stock_disponible';
            error_log("⚠️ DESPACHO DESDE DISPONIBLE - {$nombreProducto}: {$bobinasDespachar} bobinas (Disponible: {$stockDisponible} → " . ($stockDisponible - $bobinasDespachar) . ")");
        } else {
            throw new Exception("Stock insuficiente. Disponible: {$stockDisponible}, Reservado: {$stockReservado}, Requerido: {$bobinasDespachar}");
        }

        return ['origen' => $origen];
    }

    private function procesarDespachoAsignacionRejilla($idAsignacionRejilla, $bobinasDespachar)
    {
        try {
            $sqlVerificar = "
                SELECT id, despachado, cant_uni, cliente, nombre_producto, id_rejilla
                FROM sist_rejillas_asignaciones 
                WHERE id = :id_asignacion AND estado_asignacion = 'activa'";

            $stmt = $this->conexion->prepare($sqlVerificar);
            $stmt->bindParam(':id_asignacion', $idAsignacionRejilla, PDO::PARAM_INT);
            $stmt->execute();
            $asignacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$asignacion) {
                error_log("AVISO: Asignación ID {$idAsignacionRejilla} no encontrada o no activa");
                return false;
            }

            $despachadoAnterior = (int)($asignacion['despachado'] ?? 0);
            $cantidadAsignada = (int)$asignacion['cant_uni'];
            $nuevoDespachado = $despachadoAnterior + $bobinasDespachar;

            $sqlActualizar = "
                UPDATE sist_rejillas_asignaciones 
                SET despachado = :nuevo_despachado,
                    peso_despachado = COALESCE(peso_despachado, 0) + (peso_unitario * :bobinas_despachar),
                    fecha_completado = CURRENT_TIMESTAMP
                WHERE id = :id_asignacion";

            $stmt = $this->conexion->prepare($sqlActualizar);
            $stmt->bindParam(':nuevo_despachado', $nuevoDespachado, PDO::PARAM_INT);
            $stmt->bindParam(':bobinas_despachar', $bobinasDespachar, PDO::PARAM_INT);
            $stmt->bindParam(':id_asignacion', $idAsignacionRejilla, PDO::PARAM_INT);
            $resultado = $stmt->execute();

            if ($resultado) {
                error_log("✅ TRIGGER DISPARADO - Asignación ID: {$idAsignacionRejilla}, Despachado: {$despachadoAnterior} → {$nuevoDespachado}, Rejilla: {$asignacion['id_rejilla']}");
                if ($nuevoDespachado >= $cantidadAsignada) {
                    error_log("🎯 ASIGNACIÓN COMPLETADA - Cliente: {$asignacion['cliente']}, Producto: {$asignacion['nombre_producto']}");
                }
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("Error actualizando asignación de rejilla ID {$idAsignacionRejilla}: " . $e->getMessage());
            throw $e;
        }
    }

    private function despacharDesdeStockDisponible($stockAgregadoId, $bobinasDespachar, $paquetesDespachar, $idProductoFisico)
    {
        $sqlDespacho = "
            UPDATE stock_agregado 
            SET cantidad_disponible = GREATEST(0, cantidad_disponible - :bobinas_despachar),
                cantidad_despachada = cantidad_despachada + :bobinas_despachar,
                cantidad_paquetes = GREATEST(0, cantidad_paquetes - :paquetes_despachar),
                fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE id = :stock_id";

        $stmt = $this->conexion->prepare($sqlDespacho);
        $stmt->bindParam(':bobinas_despachar', $bobinasDespachar, PDO::PARAM_INT);
        $stmt->bindParam(':paquetes_despachar', $paquetesDespachar, PDO::PARAM_INT);
        $stmt->bindParam(':stock_id', $stockAgregadoId, PDO::PARAM_INT);
        $stmt->execute();

        error_log("DESPACHO STOCK DISPONIBLE - Stock ID: {$stockAgregadoId}, Bobinas: {$bobinasDespachar}, Paquetes: {$paquetesDespachar}");
    }

    private function despacharDesdeReservas($nombreProducto, $bobinasPackete, $bobinasDespachar, $paquetesDespachar, $idProductoFisico)
    {
        $sqlReserva = "
            SELECT r.id, r.cantidad_reservada, sa.id as stock_agregado_id
            FROM reservas_stock r
            INNER JOIN stock_agregado sa ON r.id_stock_agregado = sa.id
            WHERE sa.nombre_producto = :nombre_producto
            AND sa.bobinas_pacote = :bobinas_pacote
            AND r.estado = 'activa'
            AND r.cantidad_reservada >= :bobinas_requeridas
            ORDER BY r.fecha_reserva ASC
            LIMIT 1";

        $stmt = $this->conexion->prepare($sqlReserva);
        $stmt->bindParam(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
        $stmt->bindParam(':bobinas_pacote', $bobinasPackete, PDO::PARAM_INT);
        $stmt->bindParam(':bobinas_requeridas', $bobinasDespachar, PDO::PARAM_INT);
        $stmt->execute();
        $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reserva) {
            throw new Exception("No hay reservas suficientes para despachar");
        }
        $sqlStock = "
            UPDATE stock_agregado 
            SET cantidad_reservada = GREATEST(0, cantidad_reservada - :bobinas_despachar),
                cantidad_despachada = cantidad_despachada + :bobinas_despachar,
                fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE id = :stock_id";

        $stmt = $this->conexion->prepare($sqlStock);
        $stmt->bindParam(':bobinas_despachar', $bobinasDespachar, PDO::PARAM_INT);
        $stmt->bindParam(':stock_id', $reserva['stock_agregado_id'], PDO::PARAM_INT);
        $stmt->execute();

        $sqlActualizarReserva = "
            UPDATE reservas_stock 
            SET cantidad_reservada = cantidad_reservada - :bobinas_despachar,
                cantidad_despachada = COALESCE(cantidad_despachada, 0) + :bobinas_despachar,
                fecha_ultimo_despacho = CURRENT_TIMESTAMP
            WHERE id = :reserva_id";

        $stmt = $this->conexion->prepare($sqlActualizarReserva);
        $stmt->bindParam(':bobinas_despachar', $bobinasDespachar, PDO::PARAM_INT);
        $stmt->bindParam(':reserva_id', $reserva['id'], PDO::PARAM_INT);
        $stmt->execute();

        $sqlMarcarDespachada = "
            UPDATE reservas_stock 
            SET estado = 'despachada', fecha_completada = CURRENT_TIMESTAMP
            WHERE id = :reserva_id AND cantidad_reservada <= 0";

        $stmt = $this->conexion->prepare($sqlMarcarDespachada);
        $stmt->bindParam(':reserva_id', $reserva['id'], PDO::PARAM_INT);
        $stmt->execute();

        error_log("DESPACHO DESDE RESERVAS - Reserva ID: {$reserva['id']}, Bobinas: {$bobinasDespachar}");
    }

    public function verificarEstadoProducto($nombreProducto, $bobinasPackete)
    {
        $sql = "
            SELECT 
                cantidad_total,
                cantidad_disponible,
                cantidad_reservada, 
                cantidad_despachada,
                cantidad_paquetes
            FROM stock_agregado
            WHERE nombre_producto = :nombre_producto 
            AND bobinas_pacote = :bobinas_pacote";

        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
        $stmt->bindParam(':bobinas_pacote', $bobinasPackete, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function despacharItemCompleto($nombreProducto, $bobinasPackete, $bobinasDespachar, $idProductoFisico, $idAsignacionRejilla)
    {
        return $this->despacharItem($nombreProducto, $bobinasPackete, $bobinasDespachar, $idProductoFisico, $idAsignacionRejilla);
    }
}
