<?php

class PcpRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    public function obtenerVentasAprobadas($filtros = [], $inicio = 0, $limite = 10)
    {
        try {
            $whereConditions = ["v.estado = 'Enviado a PCP'"];
            $params = [];

            if (!empty($filtros['id_venta'])) {
                $whereConditions[] = "v.id::text ILIKE :id_venta";
                $params[':id_venta'] = '%' . $filtros['id_venta'] . '%';
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['vendedor'])) {
                $whereConditions[] = "u.nombre ILIKE :vendedor";
                $params[':vendedor'] = '%' . $filtros['vendedor'] . '%';
            }

            if (!empty($filtros['contador'])) {
                $whereConditions[] = "uc.nombre ILIKE :contador";
                $params[':contador'] = '%' . $filtros['contador'] . '%';
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $sql = "SELECT v.id, v.cliente, v.moneda, v.monto_total, v.es_credito, v.estado,
                    v.fecha_venta, v.tipoflete, v.tipocredito,
                    u.nombre as nombre_vendedor,
                    a.fecha_respuesta as fecha_aprobacion,
                    a.observaciones_contador,
                    uc.nombre as nombre_contador
                    FROM public.sist_ventas_presupuesto v
                    LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                    LEFT JOIN public.sist_ventas_autorizaciones a ON v.id = a.id_venta
                    LEFT JOIN public.sist_ventas_usuario uc ON a.id_contador = uc.id
                    $whereClause
                    ORDER BY a.fecha_respuesta DESC
                    LIMIT :limite OFFSET :inicio";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':inicio', $inicio, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo ventas aprobadas: " . $e->getMessage());
            return [];
        }
    }

    public function contarVentasAprobadas($filtros = [])
    {
        try {
            $whereConditions = ["v.estado = 'Enviado a PCP'"];
            $params = [];

            if (!empty($filtros['id_venta'])) {
                $whereConditions[] = "v.id::text ILIKE :id_venta";
                $params[':id_venta'] = '%' . $filtros['id_venta'] . '%';
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['vendedor'])) {
                $whereConditions[] = "u.nombre ILIKE :vendedor";
                $params[':vendedor'] = '%' . $filtros['vendedor'] . '%';
            }

            if (!empty($filtros['contador'])) {
                $whereConditions[] = "uc.nombre ILIKE :contador";
                $params[':contador'] = '%' . $filtros['contador'] . '%';
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $sql = "SELECT COUNT(*) as total 
                    FROM public.sist_ventas_presupuesto v
                    LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                    LEFT JOIN public.sist_ventas_autorizaciones a ON v.id = a.id_venta
                    LEFT JOIN public.sist_ventas_usuario uc ON a.id_contador = uc.id
                    $whereClause";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error contando ventas aprobadas: " . $e->getMessage());
            return 0;
        }
    }

    public function obtenerVentaPorId($idVenta)
    {
        try {
            $sql = "SELECT v.*, 
                    u.nombre as nombre_vendedor,
                    a.id as id_autorizacion,
                    a.descripcion as descripcion_vendedor,
                    a.descripcion as descripcion_autorizacion,
                    a.fecha_registro as fecha_envio,
                    a.fecha_respuesta as fecha_aprobacion,
                    a.observaciones_contador,
                    uc.nombre as nombre_contador,
                    (SELECT COUNT(*) FROM public.sist_ventas_pres_product WHERE id_presupuesto = v.id) AS num_productos
                    FROM public.sist_ventas_presupuesto v
                    LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                    LEFT JOIN public.sist_ventas_autorizaciones a ON v.id = a.id_venta
                    LEFT JOIN public.sist_ventas_usuario uc ON a.id_contador = uc.id
                    WHERE v.id = :id AND v.estado = 'Enviado a PCP'";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo venta por ID: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerProductosVenta($idVenta)
    {
        try {
            $sql = "SELECT pp.*, 
                           pp.unidadmedida,
                           sp.cantidad as peso_por_bobina,
                           sp.tipo as tipo_catalogo
                     FROM public.sist_ventas_pres_product pp
                     LEFT JOIN public.sist_ventas_productos sp ON pp.id_producto = sp.id
                     WHERE pp.id_presupuesto = :id_presupuesto";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_presupuesto', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos de venta: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerImagenesAutorizacion($idAutorizacion)
    {
        try {
            $sql = "SELECT id, nombre_archivo, tipo_archivo, base64_imagen, descripcion_imagen, orden_imagen
                   FROM public.sist_ventas_autorizaciones_imagenes 
                   WHERE id_autorizacion = :id_autorizacion 
                   ORDER BY orden_imagen ASC, id ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_autorizacion', $idAutorizacion, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo imágenes de autorización: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerImagenesProductos($idsProductos)
    {
        try {
            if (empty($idsProductos)) {
                return [];
            }

            $idsString = implode(',', $idsProductos);
            $sql = "SELECT id, base64img as imagen, tipoimg as tipo, nombreimg as nombre 
                   FROM public.sist_ventas_productos 
                   WHERE id IN ($idsString) AND base64img IS NOT NULL";

            $stmt = $this->conexion->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo imágenes de productos: " . $e->getMessage());
            return [];
        }
    }

    public function actualizarEstadoVenta($idVenta, $estado)
    {
        try {
            $sql = "UPDATE public.sist_ventas_presupuesto SET estado = :estado WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
            $stmt->bindParam(':id', $idVenta, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando estado de venta: " . $e->getMessage());
            return false;
        }
    }




    public function insertarProcesoPcp($datos)
    {
        try {
            $sql = "INSERT INTO public.sist_ventas_proceso_pcp 
                    (id_venta, id_usuario_pcp, fecha_procesamiento, observaciones, estado) 
                    VALUES (:id_venta, :id_usuario, :fecha, :observaciones, :estado)";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $datos['id_venta'], PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario', $datos['id_usuario'], PDO::PARAM_INT);
            $stmt->bindParam(':fecha', $datos['fecha_procesamiento'], PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $datos['observaciones'], PDO::PARAM_STR);
            $stmt->bindParam(':estado', $datos['estado'], PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error insertando proceso PCP: " . $e->getMessage());
            return false;
        }
    }


    public function insertarHistorialAccion($datos)
    {
        try {
            $sql = "INSERT INTO public.sist_ventas_historial_acciones 
                    (id_venta, id_usuario, sector, accion, fecha_accion, observaciones, estado_resultante) 
                    VALUES (:id_venta, :id_usuario, :sector, :accion, :fecha, :observaciones, :estado)";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $datos['id_venta'], PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario', $datos['id_usuario'], PDO::PARAM_INT);
            $stmt->bindParam(':sector', $datos['sector'], PDO::PARAM_STR);
            $stmt->bindParam(':accion', $datos['accion'], PDO::PARAM_STR);
            $stmt->bindParam(':fecha', $datos['fecha_accion'], PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $datos['observaciones'], PDO::PARAM_STR);
            $stmt->bindParam(':estado', $datos['estado_resultante'], PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error insertando historial de acción: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerStockGeneral($nombresProductos = [])
    {
        try {
            if (empty($nombresProductos)) {
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($nombresProductos), '?'));

            $sql = "SELECT 
            sa.nombre_producto,
            sa.tipo_producto,
            SUM(sa.cantidad_disponible) as total_items,
            sa.bobinas_pacote,
            sa.bobinas_pacote as avg_bobinas_pacote,
            (SELECT COALESCE(AVG(sps.peso_liquido), 0) 
             FROM sist_prod_stock sps 
             WHERE sps.nombre_producto = sa.nombre_producto 
               AND sps.estado = 'en stock'
               AND COALESCE(sps.bobinas_pacote, 1) = sa.bobinas_pacote
            ) as peso_promedio
        FROM stock_agregado sa
        WHERE sa.nombre_producto IN ($placeholders)
            AND sa.cantidad_disponible > 0
        GROUP BY sa.nombre_producto, sa.tipo_producto, sa.bobinas_pacote
        ORDER BY sa.nombre_producto ASC, sa.bobinas_pacote ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute($nombresProductos);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo stock: " . $e->getMessage());
            return [];
        }
    }

    public function crearReservaStock($nombreProducto, $bobinasPacote, $cantidad, $idVenta, $cliente)
    {
        try {
            $sql = "SELECT * FROM reservar_stock(:nombre_producto, :bobinas_pacote, :cantidad, :id_venta, :cliente)";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
            $stmt->bindParam(':bobinas_pacote', $bobinasPacote, PDO::PARAM_INT);
            $stmt->bindParam(':cantidad', $cantidad, PDO::PARAM_INT);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->bindParam(':cliente', $cliente, PDO::PARAM_STR);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado && $resultado['exito']) {
                error_log("SUCCESS - Reserva creada: ID {$resultado['id_reserva']} para producto $nombreProducto, cantidad $cantidad");
                return [
                    'exito' => true,
                    'mensaje' => $resultado['mensaje'],
                    'id_reserva' => $resultado['id_reserva']
                ];
            } else {
                error_log("ERROR - Fallo en reserva: " . ($resultado['mensaje'] ?? 'Error desconocido'));
                return [
                    'exito' => false,
                    'mensaje' => $resultado['mensaje'] ?? 'Error al crear reserva'
                ];
            }
        } catch (Exception $e) {
            error_log("Error creando reserva de stock: " . $e->getMessage());
            return [
                'exito' => false,
                'mensaje' => 'Error interno al crear reserva'
            ];
        }
    }

    public function obtenerReservasVenta($idVenta)
    {
        try {
            $sql = "SELECT 
            r.id,
            r.cantidad_reservada,
            r.fecha_reserva,
            r.observaciones,
            r.estado,
            sa.nombre_producto,
            sa.bobinas_pacote,
            sa.tipo_producto
        FROM reservas_stock r
        JOIN stock_agregado sa ON r.id_stock_agregado = sa.id
        WHERE r.id_venta = :id_venta 
        ORDER BY r.fecha_reserva ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo reservas de venta: " . $e->getMessage());
            return [];
        }
    }

    public function cancelarReserva($idReserva)
    {
        try {
            $sql = "SELECT * FROM cancelar_reserva(:id_reserva)";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_reserva', $idReserva, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'exito' => $resultado['exito'] ?? false,
                'mensaje' => $resultado['mensaje'] ?? 'Error al cancelar reserva'
            ];
        } catch (Exception $e) {
            error_log("Error cancelando reserva: " . $e->getMessage());
            return [
                'exito' => false,
                'mensaje' => 'Error interno al cancelar reserva'
            ];
        }
    }

    public function obtenerResumenStockAgregado($filtros = [])
    {
        try {
            $whereConditions = [];
            $params = [];

            if (!empty($filtros['nombre_producto'])) {
                $whereConditions[] = "sa.nombre_producto ILIKE :nombre_producto";
                $params[':nombre_producto'] = '%' . $filtros['nombre_producto'] . '%';
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            $sql = "SELECT 
            sa.nombre_producto,
            sa.bobinas_pacote,
            sa.tipo_producto,
            sa.cantidad_total,
            sa.cantidad_disponible,
            sa.cantidad_reservada,
            sa.cantidad_despachada,
            sa.fecha_actualizacion,
            COUNT(sps.id) as productos_fisicos_reales
        FROM stock_agregado sa
        LEFT JOIN sist_prod_stock sps ON (
            sps.nombre_producto = sa.nombre_producto 
            AND sps.bobinas_pacote = sa.bobinas_pacote
            AND sps.estado = 'en stock'
            AND COALESCE(sps.tipo_producto, '') = COALESCE(sa.tipo_producto, '')
        )
        $whereClause
        GROUP BY sa.id, sa.nombre_producto, sa.bobinas_pacote, sa.tipo_producto,
                 sa.cantidad_total, sa.cantidad_disponible, sa.cantidad_reservada, 
                 sa.cantidad_despachada, sa.fecha_actualizacion
        ORDER BY sa.nombre_producto, sa.bobinas_pacote";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo resumen stock agregado: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerDevolucionesPcp($filtros = [], $inicio = 0, $limite = 10)
    {
        try {
            $whereConditions = [
                "(pp.estado = 'Devuelto a PCP' OR pp.destino = 'Devuelto a PCP')",
            ];
            $params = [];

            if (!empty($filtros['id_venta'])) {
                $whereConditions[] = "v.id::text ILIKE :id_venta";
                $params[':id_venta'] = '%' . $filtros['id_venta'] . '%';
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['producto'])) {
                $whereConditions[] = "pres.descripcion ILIKE :producto";
                $params[':producto'] = '%' . $filtros['producto'] . '%';
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "pp.fecha_asignacion >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "pp.fecha_asignacion <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $sql = "SELECT 
            pp.id, 
            pp.id_venta, 
            pp.id_producto, 
            pp.fecha_asignacion, 
            pp.cantidad, 
            pp.observaciones, 
            pp.observaciones_produccion as observaciones_extra, 
            'Producción' as origen,
            v.cliente, 
            v.moneda, 
            v.estado,
            pres.descripcion, 
            pres.unidadmedida, 
            pres.ncm,
            COALESCE(u.nombre, 'Usuario Desconocido') as nombre_usuario
        FROM public.sist_ventas_productos_produccion pp
        JOIN public.sist_ventas_presupuesto v ON v.id = pp.id_venta
        JOIN public.sist_ventas_pres_product pres ON pres.id = pp.id_producto
        LEFT JOIN public.sist_ventas_usuario u ON u.id = pp.id_usuario_pcp
        $whereClause
        ORDER BY pp.fecha_asignacion DESC
        LIMIT :limite OFFSET :inicio";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':inicio', $inicio, PDO::PARAM_INT);
            $stmt->execute();

            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("SUCCESS - Devoluciones de producción encontradas: " . count($resultados));

            return $resultados;
        } catch (Exception $e) {
            error_log("Error obteniendo devoluciones de producción: " . $e->getMessage());
            return [];
        }
    }

    public function contarDevolucionesPcp($filtros = [])
    {
        try {
            $whereConditions = [
                "(pp.estado = 'Devuelto a PCP' OR pp.destino = 'Devuelto a PCP')",
            ];
            $params = [];

            if (!empty($filtros['id_venta'])) {
                $whereConditions[] = "v.id::text ILIKE :id_venta";
                $params[':id_venta'] = '%' . $filtros['id_venta'] . '%';
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['producto'])) {
                $whereConditions[] = "pres.descripcion ILIKE :producto";
                $params[':producto'] = '%' . $filtros['producto'] . '%';
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "pp.fecha_asignacion >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "pp.fecha_asignacion <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $sql = "SELECT COUNT(*) as total
            FROM public.sist_ventas_productos_produccion pp
            JOIN public.sist_ventas_presupuesto v ON v.id = pp.id_venta
            JOIN public.sist_ventas_pres_product pres ON pres.id = pp.id_producto
            $whereClause";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $total = $resultado['total'] ?? 0;

            error_log("SUCCESS - Total devoluciones de producción: " . $total);

            return $total;
        } catch (Exception $e) {
            error_log("Error contando devoluciones de producción: " . $e->getMessage());
            return 0;
        }
    }

    public function obtenerHistorialAcciones($filtros = [], $inicio = 0, $limite = 10)
    {
        try {
            $whereConditions = ["h.sector = 'PCP'"];
            $params = [];

            if (!empty($filtros['id_venta'])) {
                $whereConditions[] = "v.id::text ILIKE :id_venta";
                $params[':id_venta'] = '%' . $filtros['id_venta'] . '%';
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente::text ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "h.fecha_accion >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "h.fecha_accion <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $sql = "SELECT h.id, h.id_venta, h.sector, h.accion, h.fecha_accion, h.observaciones, h.estado_resultante,
                    v.cliente, v.moneda, v.monto_total,
                    u.nombre as nombre_usuario
                    FROM public.sist_ventas_historial_acciones h
                    JOIN public.sist_ventas_presupuesto v ON h.id_venta = v.id
                    JOIN public.sist_ventas_usuario u ON h.id_usuario = u.id
                    $whereClause
                    ORDER BY h.fecha_accion DESC
                    LIMIT :limite OFFSET :inicio";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':inicio', $inicio, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo historial de acciones: " . $e->getMessage());
            return [];
        }
    }

    public function contarHistorialAcciones($filtros = [])
    {
        try {
            $whereConditions = ["h.sector = 'PCP'"];
            $params = [];

            if (!empty($filtros['id_venta'])) {
                $whereConditions[] = "v.id::text ILIKE :id_venta";
                $params[':id_venta'] = '%' . $filtros['id_venta'] . '%';
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente::text ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "h.fecha_accion >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "h.fecha_accion <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $sql = "SELECT COUNT(*) as total
                    FROM public.sist_ventas_historial_acciones h
                    JOIN public.sist_ventas_presupuesto v ON h.id_venta = v.id
                    JOIN public.sist_ventas_usuario u ON h.id_usuario = u.id
                    $whereClause";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error contando historial de acciones: " . $e->getMessage());
            return 0;
        }
    }

    public function obtenerEstadisticasDashboard()
    {
        try {
            $estadisticas = [];

            $sql = "SELECT COUNT(*) as total FROM public.sist_ventas_presupuesto WHERE estado = 'Aprobado'";
            $estadisticas['pendientes'] = $this->conexion->query($sql)->fetchColumn();

            $sql = "SELECT COUNT(*) as total FROM public.sist_ventas_presupuesto WHERE estado = 'En Producción'";
            $estadisticas['produccion'] = $this->conexion->query($sql)->fetchColumn();

            $sqlCheck = "SELECT to_regclass('public.sist_ventas_historial_acciones') IS NOT NULL as existe";
            $tablaExiste = $this->conexion->query($sqlCheck)->fetchColumn();

            if ($tablaExiste) {
                $mes = date('m');
                $año = date('Y');

                $sql = "SELECT COUNT(*) as total 
                       FROM public.sist_ventas_historial_acciones 
                       WHERE sector = 'PCP' 
                       AND accion = 'Procesar' 
                       AND EXTRACT(MONTH FROM fecha_accion) = :mes 
                       AND EXTRACT(YEAR FROM fecha_accion) = :año";
                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':mes', $mes, PDO::PARAM_INT);
                $stmt->bindParam(':año', $año, PDO::PARAM_INT);
                $stmt->execute();
                $estadisticas['procesadas'] = $stmt->fetchColumn();

                $sql = "SELECT COUNT(*) as total 
                       FROM public.sist_ventas_historial_acciones 
                       WHERE sector = 'PCP' 
                       AND accion = 'Devolver' 
                       AND EXTRACT(MONTH FROM fecha_accion) = :mes 
                       AND EXTRACT(YEAR FROM fecha_accion) = :año";
                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':mes', $mes, PDO::PARAM_INT);
                $stmt->bindParam(':año', $año, PDO::PARAM_INT);
                $stmt->execute();
                $estadisticas['devueltas'] = $stmt->fetchColumn();
            } else {
                $estadisticas['procesadas'] = 0;
                $estadisticas['devueltas'] = 0;
            }

            return $estadisticas;
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas dashboard: " . $e->getMessage());
            return [
                'pendientes' => 0,
                'produccion' => 0,
                'procesadas' => 0,
                'devueltas' => 0
            ];
        }
    }

    public function getConexion()
    {
        return $this->conexion;
    }

    public function obtenerCantidadTotalEnviada($idVenta, $idProducto)
    {
        try {
            $sqlExpedicion = "SELECT 
                COALESCE(SUM(CASE WHEN origen = 'stock_general' THEN cantidad ELSE 0 END), 0) as cantidad_expedicion,
                COALESCE(SUM(CASE WHEN COALESCE(origen, '') != 'stock_general' THEN cantidad ELSE 0 END), 0) as cantidad_produccion
                FROM public.sist_ventas_productos_expedicion 
                WHERE id_venta = :id_venta AND id_producto = :id_producto";

            $stmt = $this->conexion->prepare($sqlExpedicion);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->execute();
            $resultadoExpedicion = $stmt->fetch(PDO::FETCH_ASSOC);

            $cantidadExpedicion = $resultadoExpedicion ? (float)$resultadoExpedicion['cantidad_expedicion'] : 0;
            $cantidadProduccionExp = $resultadoExpedicion ? (float)$resultadoExpedicion['cantidad_produccion'] : 0;

            $sqlProduccion = "SELECT COALESCE(SUM(cantidad), 0) as cantidad_produccion
                             FROM public.sist_ventas_productos_produccion 
                             WHERE id_venta = :id_venta AND id_producto = :id_producto";
            $stmt = $this->conexion->prepare($sqlProduccion);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->execute();
            $resultadoProduccion = $stmt->fetch(PDO::FETCH_ASSOC);

            $cantidadProduccion = $resultadoProduccion ? (float)$resultadoProduccion['cantidad_produccion'] : 0;

            return $cantidadExpedicion + $cantidadProduccionExp + $cantidadProduccion;
        } catch (Exception $e) {
            error_log("Error obteniendo cantidad total enviada: " . $e->getMessage());
            return 0;
        }
    }

    public function obtenerCantidadEfectivaProducto($idProducto)
    {
        try {
            $sql = "SELECT cantidad FROM public.sist_ventas_productos WHERE id = :id_producto";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return $resultado ? (float)$resultado['cantidad'] : 1;
        } catch (Exception $e) {
            error_log("Error obteniendo cantidad efectiva producto: " . $e->getMessage());
            return 1;
        }
    }

    public function obtenerItemsEnviadosExpedicion($idVenta)
    {
        try {
            $sql = "SELECT 
                pp.id as id_producto_venta,
                pp.tipoproducto,
                pp.cantidad as cantidad_original,
                pp.id_producto,
                COALESCE(exp.cantidad_expedicion, 0) as cantidad_expedicion,
                COALESCE(exp.cantidad_stock, 0) as cantidad_desde_stock
            FROM public.sist_ventas_pres_product pp
            LEFT JOIN (
                SELECT 
                    id_producto,
                    SUM(CASE WHEN COALESCE(origen, '') != 'stock_general' THEN cantidad ELSE 0 END) as cantidad_expedicion,
                    SUM(CASE WHEN origen = 'stock_general' THEN cantidad ELSE 0 END) as cantidad_stock
                FROM public.sist_ventas_productos_expedicion
                WHERE id_venta = :id_venta
                GROUP BY id_producto
            ) exp ON pp.id = exp.id_producto
            WHERE pp.id_presupuesto = :id_presupuesto";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->bindParam(':id_presupuesto', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo items enviados a expedición: " . $e->getMessage());
            return [];
        }
    }

    public function actualizarEstadoAutorizacion($idVenta, $estado)
    {
        try {
            $sql = "UPDATE public.sist_ventas_autorizaciones 
                    SET estado_autorizacion = :estado
                    WHERE id_venta = :id_venta";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando estado de autorización: " . $e->getMessage());
            return false;
        }
    }




    public function insertarProductoProduccion($datos)
    {
        try {
            $sql = "INSERT INTO public.sist_ventas_productos_produccion 
                    (id_venta, id_producto, id_usuario_pcp, fecha_asignacion, destino, cantidad, observaciones) 
                    VALUES (:id_venta, :id_producto, :id_usuario, :fecha, :destino, :cantidad, :observaciones)";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $datos['id_venta'], PDO::PARAM_INT);
            $stmt->bindParam(':id_producto', $datos['id_producto'], PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario', $datos['id_usuario'], PDO::PARAM_INT);
            $stmt->bindParam(':fecha', $datos['fecha_asignacion'], PDO::PARAM_STR);
            $stmt->bindParam(':destino', $datos['destino'], PDO::PARAM_STR);
            $stmt->bindParam(':cantidad', $datos['cantidad'], PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $datos['observaciones'], PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error insertando producto en producción: " . $e->getMessage());
            return false;
        }
    }

    public function insertarProductoExpedicion($datos)
    {
        try {
            $sql = "INSERT INTO sist_ventas_productos_expedicion 
                (id_venta, id_producto, id_usuario_pcp, fecha_asignacion, cantidad, observaciones, origen, movimiento) 
                VALUES (:id_venta, :id_producto, :id_usuario, :fecha, :cantidad, :observaciones, :origen, 'PENDIENTE')";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $datos['id_venta'], PDO::PARAM_INT);
            $stmt->bindParam(':id_producto', $datos['id_producto'], PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario', $datos['id_usuario'], PDO::PARAM_INT);
            $stmt->bindParam(':fecha', $datos['fecha_asignacion'], PDO::PARAM_STR);
            $stmt->bindParam(':cantidad', $datos['cantidad'], PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $datos['observaciones'], PDO::PARAM_STR);
            $stmt->bindParam(':origen', $datos['origen'], PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error insertando expedición: " . $e->getMessage());
            return false;
        }
    }

    public function verificarProductosEnProduccion($idVenta)
    {
        try {
            $sql = "SELECT COUNT(*) as productos_en_produccion 
                    FROM public.sist_ventas_productos_produccion 
                    WHERE id_venta = :id_venta";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return ($resultado['productos_en_produccion'] > 0);
        } catch (Exception $e) {
            error_log("Error verificando productos en producción: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerInfoVentaParaExpedicion($idVenta)
    {
        try {
            $sql = "SELECT cliente FROM public.sist_ventas_presupuesto WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo info venta para expedición: " . $e->getMessage());
            return false;
        }
    }
    public function eliminarProductosProduccionVenta($idVenta)
    {
        try {
            $sql = "DELETE FROM public.sist_ventas_productos_produccion WHERE id_venta = :id_venta";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);

            $resultado = $stmt->execute();
            $filasAfectadas = $stmt->rowCount();

            error_log("Eliminados $filasAfectadas registros de producción para venta $idVenta");
            return $resultado;
        } catch (Exception $e) {
            error_log("Error eliminando productos de producción: " . $e->getMessage());
            return false;
        }
    }

    public function eliminarProductoEspecificoProduccion($idVenta, $idProducto)
    {
        try {
            $sql = "DELETE FROM public.sist_ventas_productos_produccion 
                WHERE id_venta = :id_venta AND id_producto = :id_producto";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);

            $resultado = $stmt->execute();
            $filasAfectadas = $stmt->rowCount();

            error_log("Eliminado producto $idProducto de producción para venta $idVenta ($filasAfectadas registros)");
            return $filasAfectadas;
        } catch (Exception $e) {
            error_log("Error eliminando producto específico de producción: " . $e->getMessage());
            return 0;
        }
    }

    public function eliminarProductoEspecificoExpedicion($idVenta, $idProducto)
    {
        try {
            $sql = "DELETE FROM public.sist_ventas_productos_expedicion 
                WHERE id_venta = :id_venta AND id_producto = :id_producto";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);

            $resultado = $stmt->execute();
            $filasAfectadas = $stmt->rowCount();

            error_log("Eliminado producto $idProducto de expedición para venta $idVenta ($filasAfectadas registros)");
            return $filasAfectadas;
        } catch (Exception $e) {
            error_log("Error eliminando producto específico de expedición: " . $e->getMessage());
            return 0;
        }
    }
}
