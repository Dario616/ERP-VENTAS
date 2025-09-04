<?php

class ContableRepository
{
    protected $conexion;


    public function getConexion()
    {
        return $this->conexion;
    }

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    public function beginTransaction()
    {
        return $this->conexion->beginTransaction();
    }

    public function commit()
    {
        return $this->conexion->commit();
    }

    public function rollBack()
    {
        return $this->conexion->rollBack();
    }

    public function obtenerAutorizacionesPendientes($filtros = [], $limite = 10, $offset = 0)
    {
        try {
            $whereConditions = ["v.estado = 'En revision'"];
            $params = [];

            if (!empty($filtros['id_venta'])) {
                $whereConditions[] = "v.id::text ILIKE :id_venta";
                $params[':id_venta'] = '%' . $filtros['id_venta'] . '%';
            }

            if (!empty($filtros['vendedor'])) {
                $whereConditions[] = "u.nombre ILIKE :vendedor";
                $params[':vendedor'] = '%' . $filtros['vendedor'] . '%';
            }

            if (!empty($filtros['id'])) {
                $whereConditions[] = "v.id ILIKE :vendedor";
                $params[':id'] = '%' . $filtros['id'] . '%';
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "SELECT v.id, v.cliente, v.moneda, v.monto_total, v.es_credito, v.estado,
                    v.fecha_venta,
                    u.nombre as nombre_vendedor,
                    a.descripcion,
                    a.estado_autorizacion,
                    a.observaciones_contador,
                    a.fecha_registro as fecha_autorizacion
                    FROM public.sist_ventas_presupuesto v
                    LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                    LEFT JOIN public.sist_ventas_autorizaciones a ON v.id = a.id_venta
                    WHERE {$whereClause}
                    ORDER BY a.fecha_registro DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo autorizaciones pendientes: " . $e->getMessage());
            return [];
        }
    }


    public function contarAutorizacionesPendientes($filtros = [])
    {
        try {
            $whereConditions = ["v.estado = 'En revision'"];
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

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "SELECT COUNT(*) as total 
                    FROM public.sist_ventas_presupuesto v 
                    LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                    LEFT JOIN public.sist_ventas_autorizaciones a ON v.id = a.id_venta
                    WHERE {$whereClause}";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (PDOException $e) {
            error_log("Error contando autorizaciones pendientes: " . $e->getMessage());
            return 0;
        }
    }

    public function obtenerVentaPorId($id)
    {
        try {
            $sql = "SELECT v.*, 
                    u.nombre as nombre_vendedor,
                    (SELECT COUNT(*) FROM public.sist_ventas_pres_product WHERE id_presupuesto = v.id) AS num_productos,
                    a.id as id_autorizacion,
                    a.descripcion as descripcion_autorizacion,
                    a.fecha_registro as fecha_solicitud_autorizacion,
                    a.estado_autorizacion
                    FROM public.sist_ventas_presupuesto v
                    LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                    LEFT JOIN public.sist_ventas_autorizaciones a ON v.id = a.id_venta
                    WHERE v.id = :id AND v.estado = 'En revision'";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo venta por ID: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerProductosVenta($idVenta)
    {
        try {
            $sql = "SELECT pp.*
                    FROM public.sist_ventas_pres_product pp
                    WHERE pp.id_presupuesto = :id_presupuesto";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_presupuesto', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
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
        } catch (PDOException $e) {
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

            $sqlCheck = "SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = 'sist_ventas_productos'
            )";
            $exists = $this->conexion->query($sqlCheck)->fetchColumn();

            if (!$exists) {
                return [];
            }

            $idsString = implode(',', array_map('intval', $idsProductos));
            $sql = "SELECT id, base64img as imagen, tipoimg as tipo, nombreimg as nombre 
                    FROM public.sist_ventas_productos 
                    WHERE id IN ($idsString) AND base64img IS NOT NULL";

            $stmt = $this->conexion->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
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

            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error actualizando estado de venta: " . $e->getMessage());
            return false;
        }
    }


    public function actualizarAutorizacion($idVenta, $datos)
    {
        try {
            $sql = "UPDATE public.sist_ventas_autorizaciones 
                    SET fecha_respuesta = :fecha_respuesta,
                        observaciones_contador = :observaciones_contador,
                        id_contador = :id_contador,
                        estado_autorizacion = :estado_autorizacion
                    WHERE id_venta = :id_venta";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':fecha_respuesta', $datos['fecha_respuesta'], PDO::PARAM_STR);
            $stmt->bindParam(':observaciones_contador', $datos['observaciones_contador'], PDO::PARAM_STR);
            $stmt->bindParam(':id_contador', $datos['id_contador'], PDO::PARAM_INT);
            $stmt->bindParam(':estado_autorizacion', $datos['estado_autorizacion'], PDO::PARAM_STR);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);

            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error actualizando autorización: " . $e->getMessage());
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
        } catch (PDOException $e) {
            error_log("Error insertando historial de acción: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerEstadisticas()
    {
        try {
            $stats = [];

            $sql = "SELECT COUNT(*) as total FROM public.sist_ventas_presupuesto WHERE estado = 'En revision'";
            $stats['pendientes'] = $this->conexion->query($sql)->fetchColumn();

            $sql = "SELECT COUNT(*) as total FROM public.sist_ventas_presupuesto WHERE estado = 'Devuelto a Contabilidad'";
            $stats['devoluciones'] = $this->conexion->query($sql)->fetchColumn();

            $checkTabla = "SELECT to_regclass('public.sist_ventas_historial_acciones') IS NOT NULL as existe";
            $tablaExiste = $this->conexion->query($checkTabla)->fetchColumn();

            if ($tablaExiste) {
                $mes = (int)date('m');
                $anio = (int)date('Y');

                // Aprobadas en el mes
                $sql = "SELECT COUNT(*) as total 
                    FROM public.sist_ventas_historial_acciones 
                    WHERE sector = 'Contable' 
                    AND accion = 'Aprobar' 
                    AND EXTRACT(MONTH FROM fecha_accion) = :mes 
                    AND EXTRACT(YEAR FROM fecha_accion) = :anio";
                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':mes', $mes, PDO::PARAM_INT);
                $stmt->bindParam(':anio', $anio, PDO::PARAM_INT);
                $stmt->execute();
                $stats['aprobadas'] = $stmt->fetchColumn();

                $sql = "SELECT COUNT(*) as total 
                    FROM public.sist_ventas_historial_acciones 
                    WHERE sector = 'Contable' 
                    AND accion = 'Rechazar' 
                    AND EXTRACT(MONTH FROM fecha_accion) = :mes 
                    AND EXTRACT(YEAR FROM fecha_accion) = :anio";
                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':mes', $mes, PDO::PARAM_INT);
                $stmt->bindParam(':anio', $anio, PDO::PARAM_INT);
                $stmt->execute();
                $stats['rechazadas'] = $stmt->fetchColumn();
            } else {
                $stats['aprobadas'] = 0;
                $stats['rechazadas'] = 0;
            }

            return $stats;
        } catch (PDOException $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [
                'pendientes' => 0,
                'devoluciones' => 0,
                'aprobadas' => 0,
                'rechazadas' => 0
            ];
        }
    }

    public function obtenerHistorialAcciones($filtros = [], $limite = 10, $offset = 0)
    {
        try {
            $whereConditions = ["h.sector = 'Contable'"];
            $params = [];

            if (!empty($filtros['id_venta'])) {
                $whereConditions[] = "v.id::text ILIKE :id_venta";
                $params[':id_venta'] = '%' . $filtros['id_venta'] . '%';
            }


            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "h.fecha_accion >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "h.fecha_accion <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }

            if (!empty($filtros['cliente_historial'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente_historial";
                $params[':cliente_historial'] = '%' . $filtros['cliente_historial'] . '%';
            }

            if (!empty($filtros['estado_resultante'])) {
                $whereConditions[] = "h.estado_resultante = :estado_resultante";
                $params[':estado_resultante'] = $filtros['estado_resultante'];
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "SELECT h.id, h.id_venta, h.sector, h.accion, h.fecha_accion, h.observaciones, h.estado_resultante,
                v.cliente, v.moneda, v.monto_total,
                u.nombre as nombre_usuario
                FROM public.sist_ventas_historial_acciones h
                JOIN public.sist_ventas_presupuesto v ON h.id_venta = v.id
                JOIN public.sist_ventas_usuario u ON h.id_usuario = u.id
                WHERE {$whereClause}
                ORDER BY h.fecha_accion DESC
                LIMIT :limit OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo historial de acciones: " . $e->getMessage());
            return [];
        }
    }


    public function contarHistorialAcciones($filtros = [])
    {
        try {
            $whereConditions = ["h.sector = 'Contable'"];
            $params = [];

            if (!empty($filtros['id_venta'])) {
                $whereConditions[] = "v.id::text ILIKE :id_venta";
                $params[':id_venta'] = '%' . $filtros['id_venta'] . '%';
            }


            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "h.fecha_accion >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "h.fecha_accion <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }

            if (!empty($filtros['cliente_historial'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente_historial";
                $params[':cliente_historial'] = '%' . $filtros['cliente_historial'] . '%';
            }

            if (!empty($filtros['estado_resultante'])) {
                $whereConditions[] = "h.estado_resultante = :estado_resultante";
                $params[':estado_resultante'] = $filtros['estado_resultante'];
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "SELECT COUNT(*) as total 
                FROM public.sist_ventas_historial_acciones h
                JOIN public.sist_ventas_presupuesto v ON h.id_venta = v.id
                JOIN public.sist_ventas_usuario u ON h.id_usuario = u.id
                WHERE {$whereClause}";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (PDOException $e) {
            error_log("Error contando historial de acciones: " . $e->getMessage());
            return 0;
        }
    }


    public function actualizarEstadoVentaDevuelta($idVenta, $estado)
    {
        try {
            $sql = "UPDATE public.sist_ventas_presupuesto 
                    SET estado = :estado 
                    WHERE id = :id AND estado = 'Devuelto a Contabilidad'";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
            $stmt->bindParam(':id', $idVenta, PDO::PARAM_INT);

            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error actualizando estado de venta devuelta: " . $e->getMessage());
            return false;
        }
    }


    public function obtenerDevolucionesPCP($filtros = [], $limite = 10, $offset = 0)
    {
        try {
            $whereConditions = ["v.estado = 'Devuelto a Contabilidad'"];
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
                $whereConditions[] = "uv.nombre ILIKE :vendedor";
                $params[':vendedor'] = '%' . $filtros['vendedor'] . '%';
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "SELECT v.id, v.cliente, v.moneda, v.monto_total, v.es_credito, v.estado,
                    v.fecha_venta,
                    uv.nombre as nombre_vendedor,
                    p.fecha_procesamiento as fecha_devolucion,
                    p.observaciones as motivo_devolucion,
                    up.nombre as nombre_pcp
                    FROM public.sist_ventas_presupuesto v
                    LEFT JOIN public.sist_ventas_usuario uv ON v.id_usuario = uv.id
                    LEFT JOIN (
                        SELECT id_venta, MAX(fecha_procesamiento) as max_fecha
                        FROM public.sist_ventas_proceso_pcp
                        WHERE estado = 'Devuelto a Contabilidad'
                        GROUP BY id_venta
                    ) pm ON v.id = pm.id_venta
                    LEFT JOIN public.sist_ventas_proceso_pcp p ON pm.id_venta = p.id_venta AND pm.max_fecha = p.fecha_procesamiento
                    LEFT JOIN public.sist_ventas_usuario up ON p.id_usuario_pcp = up.id
                    WHERE {$whereClause}
                    ORDER BY p.fecha_procesamiento DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo devoluciones PCP: " . $e->getMessage());
            return [];
        }
    }


    public function contarDevolucionesPCP($filtros = [])
    {
        try {
            $whereConditions = ["v.estado = 'Devuelto a Contabilidad'"];
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
                $whereConditions[] = "uv.nombre ILIKE :vendedor";
                $params[':vendedor'] = '%' . $filtros['vendedor'] . '%';
            }

            if (!empty($filtros['usuario_pcp'])) {
                $whereConditions[] = "up.nombre ILIKE :usuario_pcp";
                $params[':usuario_pcp'] = '%' . $filtros['usuario_pcp'] . '%';
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "SELECT COUNT(*) as total 
                    FROM public.sist_ventas_presupuesto v 
                    LEFT JOIN public.sist_ventas_usuario uv ON v.id_usuario = uv.id
                    LEFT JOIN public.sist_ventas_proceso_pcp p ON v.id = p.id_venta
                    LEFT JOIN public.sist_ventas_usuario up ON p.id_usuario_pcp = up.id
                    WHERE {$whereClause}";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (PDOException $e) {
            error_log("Error contando devoluciones PCP: " . $e->getMessage());
            return 0;
        }
    }


    public function ventaFueRechazada($idVenta)
    {
        try {
            $sql = "SELECT COUNT(*) as total 
                FROM public.sist_ventas_historial_acciones 
                WHERE id_venta = :id_venta 
                AND sector IN ('Contable', 'PCP') 
                AND accion = 'Rechazar'";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error verificando si venta fue rechazada: " . $e->getMessage());
            return false;
        }
    }

    public function verificarCrearTablaHistorial()
    {
        try {
            $sqlCheck = "SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = 'sist_ventas_historial_acciones'
            )";
            $exists = $this->conexion->query($sqlCheck)->fetchColumn();

            if (!$exists) {
                $sqlCreate = "CREATE TABLE public.sist_ventas_historial_acciones (
                    id SERIAL PRIMARY KEY,
                    id_venta INTEGER NOT NULL,
                    id_usuario INTEGER NOT NULL,
                    sector VARCHAR(50) NOT NULL,
                    accion VARCHAR(50) NOT NULL,
                    fecha_accion TIMESTAMP NOT NULL,
                    observaciones TEXT,
                    estado_resultante VARCHAR(50) NOT NULL,
                    FOREIGN KEY (id_venta) REFERENCES public.sist_ventas_presupuesto(id),
                    FOREIGN KEY (id_usuario) REFERENCES public.sist_ventas_usuario(id)
                )";
                $this->conexion->exec($sqlCreate);
                return true;
            }
            return true;
        } catch (PDOException $e) {
            error_log("Error verificando/creando tabla historial: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerVentaCompletaPorId($id)
    {
        try {
            $sql = "SELECT v.*, 
                u.nombre as nombre_vendedor,
                (SELECT COUNT(*) FROM public.sist_ventas_pres_product WHERE id_presupuesto = v.id) AS num_productos,
                a.id as id_autorizacion,
                a.descripcion as descripcion_autorizacion,
                a.fecha_registro as fecha_solicitud_autorizacion,
                a.estado_autorizacion
                FROM public.sist_ventas_presupuesto v
                LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                LEFT JOIN public.sist_ventas_autorizaciones a ON v.id = a.id_venta
                WHERE v.id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo venta completa por ID: " . $e->getMessage());
            return false;
        }
    }
}
