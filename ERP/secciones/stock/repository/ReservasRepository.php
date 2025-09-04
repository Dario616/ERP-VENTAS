<?php

/**
 * Repository para operaciones de base de datos de reservas de stock
 * Modificado para vista agrupada por productos
 */
class ReservasRepository
{
    public $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    /**
     * Obtener productos con reservas activas agrupados
     */
    public function obtenerProductosConReservas($filtroProducto = '', $limit = 20, $offset = 0)
    {
        try {
            $whereConditions = ["r.estado = 'activa'"];
            $params = [];

            if (!empty($filtroProducto)) {
                $whereConditions[] = "sa.nombre_producto ILIKE :producto";
                $params[':producto'] = '%' . $filtroProducto . '%';
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $sql = "
            SELECT 
                sa.id as id_stock,
                sa.nombre_producto,
                sa.tipo_producto,
                sa.bobinas_pacote,
                sa.cantidad_disponible,
                sa.cantidad_total,
                
                -- Agregaciones de reservas
                COUNT(r.id) as total_reservas,
                SUM(r.cantidad_reservada) as total_bobinas_reservadas,
                SUM(CASE 
                    WHEN sa.bobinas_pacote > 0 THEN 
                        FLOOR(r.cantidad_reservada::numeric / sa.bobinas_pacote::numeric)
                    ELSE 0 
                END) as total_paquetes_reservados,
                
                -- Información de clientes
                COUNT(DISTINCT r.cliente) as total_clientes,
                STRING_AGG(DISTINCT r.cliente, ', ' ORDER BY r.cliente) as clientes_list,
                
                -- Información de ventas
                COUNT(DISTINCT r.id_venta) as total_ventas,
                
                -- Fechas
                MIN(r.fecha_reserva) as fecha_primera_reserva,
                MAX(r.fecha_reserva) as fecha_ultima_reserva,
                
                -- Días promedio de reserva
                AVG(EXTRACT(days FROM (NOW() - r.fecha_reserva)))::integer as promedio_dias_reserva,
                MAX(EXTRACT(days FROM (NOW() - r.fecha_reserva)))::integer as max_dias_reserva,
                
                -- Porcentaje del stock comprometido
                ROUND((SUM(r.cantidad_reservada)::numeric / CASE WHEN sa.cantidad_total = 0 THEN 1 ELSE sa.cantidad_total END::numeric * 100), 2) as porcentaje_comprometido
                
            FROM stock_agregado sa
            INNER JOIN reservas_stock r ON sa.id = r.id_stock_agregado
            {$whereClause}
            GROUP BY sa.id, sa.nombre_producto, sa.tipo_producto, sa.bobinas_pacote, 
                     sa.cantidad_disponible, sa.cantidad_total
            ORDER BY total_bobinas_reservadas DESC, sa.nombre_producto
            LIMIT :limit OFFSET :offset
        ";

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos con reservas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar total de productos con reservas activas
     */
    public function contarProductosConReservas($filtroProducto = '')
    {
        try {
            $whereConditions = ["r.estado = 'activa'"];
            $params = [];

            if (!empty($filtroProducto)) {
                $whereConditions[] = "sa.nombre_producto ILIKE :producto";
                $params[':producto'] = '%' . $filtroProducto . '%';
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $sql = "
            SELECT COUNT(DISTINCT sa.id) 
            FROM stock_agregado sa
            INNER JOIN reservas_stock r ON sa.id = r.id_stock_agregado
            {$whereClause}
        ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_STR);
            }

            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error contando productos con reservas: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener información básica de un producto por ID
     */
    public function obtenerProductoPorId($idStock)
    {
        try {
            $sql = "
                SELECT 
                    id,
                    nombre_producto,
                    tipo_producto,
                    bobinas_pacote,
                    cantidad_disponible,
                    cantidad_total
                FROM stock_agregado 
                WHERE id = :id_stock
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_stock', $idStock, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo producto por ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener reservas específicas de un producto
     */
    public function obtenerReservasPorProducto($idStock)
    {
        try {
            $sql = "
            SELECT 
                r.id,
                r.cantidad_reservada,
                r.id_venta,
                r.cliente,
                r.fecha_reserva,
                r.observaciones,
                r.usuario_creacion,
                
                -- Datos del stock
                sa.nombre_producto,
                sa.tipo_producto,
                sa.bobinas_pacote,
                sa.cantidad_disponible,
                sa.cantidad_total,
                
                -- Datos de la venta
                v.proforma,
                v.fecha_venta,
                v.estado as estado_venta,
                v.monto_total,
                v.moneda,
                v.cond_pago,
                v.descripcion as descripcion_venta,
                
                -- Calcular paquetes reservados
                CASE 
                    WHEN sa.bobinas_pacote > 0 THEN 
                        FLOOR(r.cantidad_reservada::numeric / sa.bobinas_pacote::numeric)
                    ELSE 0 
                END as paquetes_reservados,
                
                -- Días desde la reserva
                EXTRACT(days FROM (NOW() - r.fecha_reserva))::integer as dias_reserva
                
            FROM reservas_stock r
            JOIN stock_agregado sa ON r.id_stock_agregado = sa.id
            LEFT JOIN sist_ventas_presupuesto v ON r.id_venta = v.id
            WHERE r.estado = 'activa' AND sa.id = :id_stock
            ORDER BY r.fecha_reserva DESC
        ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_stock', $idStock, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo reservas por producto: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener detalles de una reserva específica
     */
    public function obtenerDetalleReserva($idReserva)
    {
        try {
            $sql = "
                SELECT 
                    r.*,
                    sa.nombre_producto,
                    sa.tipo_producto,
                    sa.bobinas_pacote,
                    sa.cantidad_disponible,
                    v.proforma,
                    v.cliente as cliente_venta,
                    v.fecha_venta,
                    v.monto_total,
                    v.estado as estado_venta
                FROM reservas_stock r
                JOIN stock_agregado sa ON r.id_stock_agregado = sa.id
                LEFT JOIN sist_ventas_presupuesto v ON r.id_venta = v.id
                WHERE r.id = :id_reserva
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_reserva', $idReserva, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo detalle de reserva: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancelar reserva usando la función PostgreSQL
     */
    public function cancelarReserva($idReserva, $motivo = 'Cancelación desde interfaz', $usuario = 'SISTEMA')
    {
        try {
            // Primero verificar si existe la función mejorada
            $checkFunction = "
                SELECT EXISTS (
                    SELECT 1 FROM pg_proc p
                    JOIN pg_namespace n ON p.pronamespace = n.oid
                    WHERE n.nspname = 'public' AND p.proname = 'cancelar_reserva_mejorada'
                )
            ";

            $stmt = $this->conexion->prepare($checkFunction);
            $stmt->execute();
            $funcionExiste = $stmt->fetchColumn();

            if ($funcionExiste) {
                // Usar función mejorada
                $sql = "SELECT * FROM cancelar_reserva_mejorada(:id_reserva, :motivo, :usuario)";
            } else {
                // Fallback a método manual
                return $this->cancelarReservaManual($idReserva, $motivo, $usuario);
            }

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_reserva', $idReserva, PDO::PARAM_INT);
            $stmt->bindValue(':motivo', $motivo, PDO::PARAM_STR);
            $stmt->bindValue(':usuario', $usuario, PDO::PARAM_STR);

            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error cancelando reserva: " . $e->getMessage());
            return [
                'exito' => false,
                'mensaje' => 'Error interno: ' . $e->getMessage(),
                'paquetes_liberados' => 0,
                'bobinas_liberadas' => 0
            ];
        }
    }

    /**
     * Cancelar reserva de forma manual (fallback)
     */
    private function cancelarReservaManual($idReserva, $motivo, $usuario)
    {
        try {
            $this->conexion->beginTransaction();

            // Obtener datos de la reserva
            $sql = "
                SELECT r.*, sa.bobinas_pacote, sa.nombre_producto
                FROM reservas_stock r
                JOIN stock_agregado sa ON r.id_stock_agregado = sa.id
                WHERE r.id = :id_reserva AND r.estado = 'activa'
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_reserva', $idReserva, PDO::PARAM_INT);
            $stmt->execute();
            $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reserva) {
                $this->conexion->rollBack();
                return [
                    'exito' => false,
                    'mensaje' => 'Reserva no encontrada o ya cancelada',
                    'paquetes_liberados' => 0,
                    'bobinas_liberadas' => 0
                ];
            }

            // Actualizar el stock disponible
            $sql = "
                UPDATE stock_agregado 
                SET cantidad_disponible = cantidad_disponible + :cantidad
                WHERE id = :id_stock
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':cantidad', $reserva['cantidad_reservada'], PDO::PARAM_INT);
            $stmt->bindValue(':id_stock', $reserva['id_stock_agregado'], PDO::PARAM_INT);
            $stmt->execute();

            // Marcar la reserva como cancelada
            $sql = "
                UPDATE reservas_stock 
                SET estado = 'cancelada',
                    fecha_cancelacion = NOW(),
                    motivo_cancelacion = :motivo,
                    usuario_cancelacion = :usuario
                WHERE id = :id_reserva
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':motivo', $motivo, PDO::PARAM_STR);
            $stmt->bindValue(':usuario', $usuario, PDO::PARAM_STR);
            $stmt->bindValue(':id_reserva', $idReserva, PDO::PARAM_INT);
            $stmt->execute();

            $this->conexion->commit();

            $paquetesLiberados = $reserva['bobinas_pacote'] > 0 ?
                floor($reserva['cantidad_reservada'] / $reserva['bobinas_pacote']) : 0;

            return [
                'exito' => true,
                'mensaje' => 'Reserva cancelada exitosamente',
                'paquetes_liberados' => $paquetesLiberados,
                'bobinas_liberadas' => $reserva['cantidad_reservada']
            ];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("Error en cancelación manual: " . $e->getMessage());
            return [
                'exito' => false,
                'mensaje' => 'Error al cancelar la reserva: ' . $e->getMessage(),
                'paquetes_liberados' => 0,
                'bobinas_liberadas' => 0
            ];
        }
    }

    /**
     * Buscar reservas específicas para cancelación
     */
    public function buscarReservasParaCancelacion($idStock, $cliente = '', $cantidadMinima = 0)
    {
        try {
            $whereConditions = [
                "r.estado = 'activa'",
                "r.id_stock_agregado = :id_stock"
            ];
            $params = [':id_stock' => $idStock];

            if (!empty($cliente)) {
                $whereConditions[] = "r.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $cliente . '%';
            }

            if ($cantidadMinima > 0) {
                $whereConditions[] = "r.cantidad_reservada >= :cantidad_minima";
                $params[':cantidad_minima'] = $cantidadMinima;
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $sql = "
            SELECT 
                r.id,
                r.cantidad_reservada,
                r.cliente,
                r.fecha_reserva,
                r.id_venta,
                v.proforma,
                CASE 
                    WHEN sa.bobinas_pacote > 0 THEN 
                        FLOOR(r.cantidad_reservada::numeric / sa.bobinas_pacote::numeric)
                    ELSE 0 
                END as paquetes_reservados,
                EXTRACT(days FROM (NOW() - r.fecha_reserva))::integer as dias_reserva
            FROM reservas_stock r
            JOIN stock_agregado sa ON r.id_stock_agregado = sa.id
            LEFT JOIN sist_ventas_presupuesto v ON r.id_venta = v.id
            {$whereClause}
            ORDER BY r.fecha_reserva ASC
        ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                if ($key === ':id_stock' || $key === ':cantidad_minima') {
                    $stmt->bindValue($key, $val, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $val, PDO::PARAM_STR);
                }
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error buscando reservas para cancelación: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener estadísticas de reservas
     */
    public function obtenerEstadisticasReservas()
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_reservas_activas,
                    COALESCE(SUM(r.cantidad_reservada), 0) as total_bobinas_reservadas,
                    COUNT(DISTINCT r.id_venta) as ventas_con_reservas,
                    COUNT(DISTINCT r.cliente) as clientes_con_reservas,
                    COUNT(DISTINCT r.id_stock_agregado) as productos_con_reservas,
                    COALESCE(AVG(EXTRACT(days FROM (NOW() - r.fecha_reserva)))::integer, 0) as promedio_dias_reserva,
                    COALESCE(SUM(
                        CASE 
                            WHEN sa.bobinas_pacote > 0 THEN 
                                FLOOR(r.cantidad_reservada::numeric / sa.bobinas_pacote::numeric)
                            ELSE 0 
                        END
                    ), 0) as total_paquetes_reservados
                FROM reservas_stock r
                JOIN stock_agregado sa ON r.id_stock_agregado = sa.id
                WHERE r.estado = 'activa'
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas de reservas: " . $e->getMessage());
            return [
                'total_reservas_activas' => 0,
                'total_bobinas_reservadas' => 0,
                'ventas_con_reservas' => 0,
                'clientes_con_reservas' => 0,
                'productos_con_reservas' => 0,
                'promedio_dias_reserva' => 0,
                'total_paquetes_reservados' => 0
            ];
        }
    }

    /**
     * Registrar log de cancelación
     */
    public function registrarLogCancelacion($idReserva, $usuario, $motivo, $resultado)
    {
        try {
            // Verificar si la tabla existe
            $checkTable = "
                SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'public' 
                    AND table_name = 'log_stock_consultas'
                )
            ";

            $stmt = $this->conexion->prepare($checkTable);
            $stmt->execute();
            $tablaExiste = $stmt->fetchColumn();

            if (!$tablaExiste) {
                // Crear tabla si no existe
                $this->crearTablaLog();
            }

            $sql = "
                INSERT INTO log_stock_consultas 
                (usuario, ip_address, accion, detalles, fecha_consulta) 
                VALUES (:usuario, :ip, 'CANCELACION_RESERVA', :detalles, NOW())
            ";

            $detalles = json_encode([
                'id_reserva' => $idReserva,
                'motivo' => $motivo,
                'resultado' => $resultado
            ], JSON_UNESCAPED_UNICODE);

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':usuario', $usuario, PDO::PARAM_STR);
            $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'] ?? 'Desconocida', PDO::PARAM_STR);
            $stmt->bindValue(':detalles', $detalles, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error registrando log de cancelación: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crear tabla de log si no existe
     */
    private function crearTablaLog()
    {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS log_stock_consultas (
                    id SERIAL PRIMARY KEY,
                    usuario VARCHAR(100),
                    ip_address VARCHAR(45),
                    accion VARCHAR(100),
                    detalles TEXT,
                    fecha_consulta TIMESTAMP DEFAULT NOW()
                )
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            error_log("Tabla log_stock_consultas creada exitosamente");
        } catch (Exception $e) {
            error_log("Error creando tabla de log: " . $e->getMessage());
        }
    }

    /**
     * Verificar integridad de la base de datos
     */
    public function verificarIntegridad()
    {
        $errores = [];

        try {
            // Verificar tabla stock_agregado
            $sql = "SELECT COUNT(*) FROM stock_agregado LIMIT 1";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
        } catch (Exception $e) {
            $errores[] = "Tabla stock_agregado no accesible: " . $e->getMessage();
        }

        try {
            // Verificar tabla reservas_stock
            $sql = "SELECT COUNT(*) FROM reservas_stock LIMIT 1";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
        } catch (Exception $e) {
            $errores[] = "Tabla reservas_stock no accesible: " . $e->getMessage();
        }

        try {
            // Verificar tabla sist_ventas_presupuesto
            $sql = "SELECT COUNT(*) FROM sist_ventas_presupuesto LIMIT 1";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
        } catch (Exception $e) {
            $errores[] = "Tabla sist_ventas_presupuesto no accesible: " . $e->getMessage();
        }

        return empty($errores) ? true : $errores;
    }

    /**
     * Limpiar reservas huérfanas (opcional)
     */
    public function limpiarReservasHuerfanas()
    {
        try {
            $sql = "
                UPDATE reservas_stock 
                SET estado = 'cancelada', 
                    motivo_cancelacion = 'Limpieza automática - producto inexistente',
                    fecha_cancelacion = NOW()
                WHERE estado = 'activa' 
                AND id_stock_agregado NOT IN (SELECT id FROM stock_agregado)
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Error limpiando reservas huérfanas: " . $e->getMessage());
            return 0;
        }
    }
}
