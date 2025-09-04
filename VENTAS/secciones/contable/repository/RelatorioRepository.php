<?php

class RelatorioRepository
{
    protected $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    public function getConexion()
    {
        return $this->conexion;
    }

    public function obtenerReportePagosAgrupado($filtros = [])
    {
        try {
            $whereConditions = ["1=1"];
            $params = [];

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "p.fecha_pago >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "p.fecha_pago <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['moneda'])) {
                $whereConditions[] = "COALESCE(v.moneda, 'Guaraníes') = :moneda";
                $params[':moneda'] = $filtros['moneda'];
            }

            if (!empty($filtros['forma_pago'])) {
                $whereConditions[] = "p.forma_pago ILIKE :forma_pago";
                $params[':forma_pago'] = '%' . $filtros['forma_pago'] . '%';
            }

            if (!empty($filtros['vendedor'])) {
                $whereConditions[] = "u.nombre ILIKE :vendedor";
                $params[':vendedor'] = '%' . $filtros['vendedor'] . '%';
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "
            SELECT 
                v.cliente,
                COALESCE(v.moneda, 'Guaraníes') as moneda,
                u.nombre as vendedor,
                COUNT(DISTINCT p.id) as total_pagos,
                COUNT(DISTINCT v.id) as total_ventas,
                SUM(p.monto_pago) as total_monto_pagado,
                SUM(DISTINCT v.monto_total) as total_monto_ventas,
                MIN(p.fecha_pago) as primera_fecha_pago,
                MAX(p.fecha_pago) as ultima_fecha_pago,
                STRING_AGG(DISTINCT p.forma_pago, ', ') as formas_pago_utilizadas,
                EXTRACT(YEAR FROM p.fecha_pago) as año_pago,
                EXTRACT(MONTH FROM p.fecha_pago) as mes_pago,
                AVG(p.monto_pago) as promedio_pago,
                MAX(p.monto_pago) as mayor_pago,
                MIN(p.monto_pago) as menor_pago
            FROM public.sist_ventas_pagos_cuotas p
            INNER JOIN public.sist_ventas_cuentas_cobrar cc ON p.id_cuota = cc.id
            INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
            LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
            WHERE {$whereClause}
            GROUP BY 
                v.cliente, 
                COALESCE(v.moneda, 'Guaraníes'), 
                u.nombre,
                EXTRACT(YEAR FROM p.fecha_pago),
                EXTRACT(MONTH FROM p.fecha_pago)
            ORDER BY 
                v.cliente ASC, 
                COALESCE(v.moneda, 'Guaraníes') ASC,
                año_pago DESC,
                mes_pago DESC
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo reporte agrupado: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerTotalesPorMoneda($filtros = [])
    {
        try {
            $whereConditions = ["1=1"];
            $params = [];

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "p.fecha_pago >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "p.fecha_pago <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['forma_pago'])) {
                $whereConditions[] = "p.forma_pago ILIKE :forma_pago";
                $params[':forma_pago'] = '%' . $filtros['forma_pago'] . '%';
            }

            if (!empty($filtros['vendedor'])) {
                $whereConditions[] = "u.nombre ILIKE :vendedor";
                $params[':vendedor'] = '%' . $filtros['vendedor'] . '%';
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "
            SELECT 
                COALESCE(v.moneda, 'Guaraníes') as moneda,
                COUNT(DISTINCT p.id) as total_pagos,
                COUNT(DISTINCT v.cliente) as total_clientes,
                COUNT(DISTINCT v.id) as total_ventas,
                SUM(p.monto_pago) as total_monto,
                AVG(p.monto_pago) as promedio_pago,
                MAX(p.monto_pago) as mayor_pago,
                MIN(p.monto_pago) as menor_pago,
                MIN(p.fecha_pago) as primera_fecha,
                MAX(p.fecha_pago) as ultima_fecha
            FROM public.sist_ventas_pagos_cuotas p
            INNER JOIN public.sist_ventas_cuentas_cobrar cc ON p.id_cuota = cc.id
            INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
            LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
            WHERE {$whereClause}
            GROUP BY COALESCE(v.moneda, 'Guaraníes')
            ORDER BY total_monto DESC
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo totales por moneda: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerDetallesPagosCliente($cliente, $moneda, $filtros = [])
    {
        try {
            $whereConditions = [
                "v.cliente = :cliente",
                "COALESCE(v.moneda, 'Guaraníes') = :moneda"
            ];
            $params = [
                ':cliente' => $cliente,
                ':moneda' => $moneda
            ];

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "p.fecha_pago >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "p.fecha_pago <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "
            SELECT 
                p.id as id_pago,
                p.monto_pago,
                p.fecha_pago,
                p.forma_pago,
                p.referencia_pago,
                p.observaciones,
                v.id as id_venta,
                v.proforma,
                cc.numero_cuota,
                cc.monto_cuota,
                ur.nombre as usuario_registro,
                p.fecha_registro
            FROM public.sist_ventas_pagos_cuotas p
            INNER JOIN public.sist_ventas_cuentas_cobrar cc ON p.id_cuota = cc.id
            INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
            LEFT JOIN public.sist_ventas_usuario ur ON p.id_usuario_registro = ur.id
            WHERE {$whereClause}
            ORDER BY p.fecha_pago DESC, p.fecha_registro DESC
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo detalles de pagos: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerEstadisticasGenerales($filtros = [])
    {
        try {
            $whereConditions = ["1=1"];
            $params = [];

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "p.fecha_pago >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "p.fecha_pago <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "
            SELECT 
                COUNT(DISTINCT v.cliente) as total_clientes,
                COUNT(DISTINCT p.id) as total_pagos,
                COUNT(DISTINCT v.id) as total_ventas,
                COUNT(DISTINCT p.forma_pago) as total_formas_pago,
                COUNT(DISTINCT u.id) as total_vendedores,
                SUM(p.monto_pago) as monto_total_general,
                AVG(p.monto_pago) as promedio_general,
                MAX(p.monto_pago) as mayor_pago_general,
                MIN(p.fecha_pago) as fecha_primer_pago,
                MAX(p.fecha_pago) as fecha_ultimo_pago
            FROM public.sist_ventas_pagos_cuotas p
            INNER JOIN public.sist_ventas_cuentas_cobrar cc ON p.id_cuota = cc.id
            INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
            LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
            WHERE {$whereClause}
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo estadísticas generales: " . $e->getMessage());
            return [
                'total_clientes' => 0,
                'total_pagos' => 0,
                'total_ventas' => 0,
                'total_formas_pago' => 0,
                'total_vendedores' => 0,
                'monto_total_general' => 0,
                'promedio_general' => 0,
                'mayor_pago_general' => 0,
                'fecha_primer_pago' => null,
                'fecha_ultimo_pago' => null
            ];
        }
    }

    public function obtenerAnalisisTemporal($filtros = [])
    {
        try {
            $whereConditions = ["1=1"];
            $params = [];

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "p.fecha_pago >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "p.fecha_pago <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "
            SELECT 
                EXTRACT(YEAR FROM p.fecha_pago) as año,
                EXTRACT(MONTH FROM p.fecha_pago) as mes,
                COALESCE(v.moneda, 'Guaraníes') as moneda,
                COUNT(p.id) as total_pagos,
                SUM(p.monto_pago) as total_monto,
                COUNT(DISTINCT v.cliente) as clientes_activos,
                AVG(p.monto_pago) as promedio_pago
            FROM public.sist_ventas_pagos_cuotas p
            INNER JOIN public.sist_ventas_cuentas_cobrar cc ON p.id_cuota = cc.id
            INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
            WHERE {$whereClause}
            GROUP BY 
                EXTRACT(YEAR FROM p.fecha_pago),
                EXTRACT(MONTH FROM p.fecha_pago),
                COALESCE(v.moneda, 'Guaraníes')
            ORDER BY año DESC, mes DESC, moneda
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo análisis temporal: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerListaClientes()
    {
        try {
            $sql = "
            SELECT DISTINCT v.cliente
            FROM public.sist_ventas_presupuesto v
            INNER JOIN public.sist_ventas_cuentas_cobrar cc ON v.id = cc.id_venta
            INNER JOIN public.sist_ventas_pagos_cuotas p ON cc.id = p.id_cuota
            WHERE v.cliente IS NOT NULL AND TRIM(v.cliente) != ''
            ORDER BY v.cliente ASC
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Error obteniendo lista de clientes: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerListaVendedores()
    {
        try {
            $sql = "
            SELECT DISTINCT u.nombre
            FROM public.sist_ventas_usuario u
            INNER JOIN public.sist_ventas_presupuesto v ON u.id = v.id_usuario
            INNER JOIN public.sist_ventas_cuentas_cobrar cc ON v.id = cc.id_venta
            INNER JOIN public.sist_ventas_pagos_cuotas p ON cc.id = p.id_cuota
            WHERE u.nombre IS NOT NULL AND TRIM(u.nombre) != ''
            ORDER BY u.nombre ASC
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Error obteniendo lista de vendedores: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerFormasPago()
    {
        try {
            $sql = "
            SELECT DISTINCT p.forma_pago
            FROM public.sist_ventas_pagos_cuotas p
            WHERE p.forma_pago IS NOT NULL AND TRIM(p.forma_pago) != ''
            ORDER BY p.forma_pago ASC
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Error obteniendo formas de pago: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerRankingClientes($filtros = [], $limite = 10)
    {
        try {
            $whereConditions = ["1=1"];
            $params = [];

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "p.fecha_pago >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "p.fecha_pago <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['moneda'])) {
                $whereConditions[] = "COALESCE(v.moneda, 'Guaraníes') = :moneda";
                $params[':moneda'] = $filtros['moneda'];
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "
            SELECT 
                v.cliente,
                COALESCE(v.moneda, 'Guaraníes') as moneda,
                COUNT(p.id) as total_pagos,
                SUM(p.monto_pago) as total_monto,
                AVG(p.monto_pago) as promedio_pago,
                MAX(p.monto_pago) as mayor_pago,
                MIN(p.fecha_pago) as primera_fecha,
                MAX(p.fecha_pago) as ultima_fecha,
                RANK() OVER (ORDER BY SUM(p.monto_pago) DESC) as ranking
            FROM public.sist_ventas_pagos_cuotas p
            INNER JOIN public.sist_ventas_cuentas_cobrar cc ON p.id_cuota = cc.id
            INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
            WHERE {$whereClause}
            GROUP BY v.cliente, COALESCE(v.moneda, 'Guaraníes')
            ORDER BY total_monto DESC
            LIMIT :limite
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo ranking de clientes: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerInfoGeneralCliente($cliente, $filtros = [])
    {
        try {
            $whereConditions = ["v.cliente = :cliente"];
            $params = [':cliente' => $cliente];

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "p.fecha_pago >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "p.fecha_pago <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['moneda'])) {
                $whereConditions[] = "COALESCE(v.moneda, 'Guaraníes') = :moneda";
                $params[':moneda'] = $filtros['moneda'];
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "
        SELECT 
            v.cliente,
            u.nombre as vendedor_principal,
            COUNT(DISTINCT v.id) as total_ventas_cantidad,
            SUM(DISTINCT v.monto_total) as total_ventas,
            SUM(p.monto_pago) as total_pagado,
            SUM(DISTINCT v.monto_total) - COALESCE(SUM(p.monto_pago), 0) as total_pendiente,
            AVG(DISTINCT v.monto_total) as promedio_venta,
            MIN(v.fecha_venta) as primera_venta,
            MAX(v.fecha_venta) as ultima_venta,
            MAX(p.fecha_pago) as ultimo_pago,
            COUNT(DISTINCT p.forma_pago) as formas_pago_usadas,
            STRING_AGG(DISTINCT v.moneda, ', ') as monedas_utilizadas,
            STRING_AGG(DISTINCT v.tipocredito, ', ') as tipos_credito_utilizados
        FROM public.sist_ventas_presupuesto v
        LEFT JOIN public.sist_ventas_cuentas_cobrar cc ON v.id = cc.id_venta
        LEFT JOIN public.sist_ventas_pagos_cuotas p ON cc.id = p.id_cuota
        LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
        WHERE {$whereClause}
        GROUP BY v.cliente, u.nombre
        ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo info general del cliente: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerVentasCliente($cliente, $filtros = [])
    {
        try {
            $whereConditions = ["v.cliente = :cliente"];
            $params = [':cliente' => $cliente];

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "v.fecha_venta >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "v.fecha_venta <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['moneda'])) {
                $whereConditions[] = "COALESCE(v.moneda, 'Guaraníes') = :moneda";
                $params[':moneda'] = $filtros['moneda'];
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "
    SELECT 
        v.id,
        v.proforma,
        v.cliente,
        v.fecha_venta,
        v.monto_total,
        v.subtotal,
        v.descuento,
        v.moneda,
        v.cond_pago,
        v.tipo_pago,
        v.estado,
        v.es_credito,
        v.tipocredito,
        v.fecha_inicio_credito,
        v.descripcion,
        v.tipoflete,
        v.transportadora,
        u.nombre as vendedor,
        SUM(p.monto_pago) as monto_pagado,
        v.monto_total - SUM(p.monto_pago) as monto_pendiente,
        COUNT(DISTINCT cc.id) as total_cuotas,
        COUNT(DISTINCT CASE WHEN cc.estado = 'PAGADO' THEN cc.id END) as cuotas_pagadas,
        COUNT(DISTINCT CASE WHEN cc.estado = 'PENDIENTE' THEN cc.id END) as cuotas_pendientes,
        COUNT(DISTINCT CASE WHEN cc.fecha_vencimiento < CURRENT_DATE AND cc.estado != 'PAGADO' THEN cc.id END) as cuotas_vencidas
    FROM public.sist_ventas_presupuesto v
    LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
    INNER JOIN public.sist_ventas_cuentas_cobrar cc ON v.id = cc.id_venta
    INNER JOIN public.sist_ventas_pagos_cuotas p ON cc.id = p.id_cuota
    WHERE {$whereClause}
    GROUP BY v.id, v.proforma, v.cliente, v.fecha_venta, v.monto_total, v.subtotal, 
             v.descuento, v.moneda, v.cond_pago, v.tipo_pago, v.estado, v.es_credito,
             v.tipocredito, v.fecha_inicio_credito, v.descripcion, v.tipoflete, 
             v.transportadora, u.nombre
    ORDER BY v.fecha_venta DESC, v.proforma DESC
    ";


            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo ventas del cliente: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerCuotasYPagosCliente($cliente, $filtros = [])
    {
        try {
            $whereConditions = ["v.cliente = :cliente"];
            $params = [':cliente' => $cliente];

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "(cc.fecha_vencimiento >= :fecha_desde OR p.fecha_pago >= :fecha_desde2)";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
                $params[':fecha_desde2'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "(cc.fecha_vencimiento <= :fecha_hasta OR p.fecha_pago <= :fecha_hasta2)";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
                $params[':fecha_hasta2'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['moneda'])) {
                $whereConditions[] = "COALESCE(v.moneda, 'Guaraníes') = :moneda";
                $params[':moneda'] = $filtros['moneda'];
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "
        SELECT 
            cc.id as id_cuota,
            cc.numero_cuota,
            cc.fecha_vencimiento,
            cc.monto_cuota,
            cc.monto_pagado,
            cc.monto_pendiente,
            cc.estado,
            cc.fecha_creacion,
            cc.fecha_ultimo_pago,
            cc.observaciones as observaciones_cuota,
            v.id as id_venta,
            v.proforma,
            v.moneda,
            v.fecha_venta,
            p.id as id_pago,
            p.monto_pago,
            p.fecha_pago,
            p.fecha_registro,
            p.forma_pago,
            p.referencia_pago,
            p.observaciones as observaciones_pago,
            p.comprobante_nombre,
            p.comprobante_tipo,
            ur.nombre as usuario_registro
        FROM public.sist_ventas_cuentas_cobrar cc
        INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
        LEFT JOIN public.sist_ventas_pagos_cuotas p ON cc.id = p.id_cuota
        LEFT JOIN public.sist_ventas_usuario ur ON p.id_usuario_registro = ur.id
        WHERE {$whereClause}
        ORDER BY v.fecha_venta DESC, cc.numero_cuota ASC, p.fecha_pago DESC
        ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $cuotasAgrupadas = [];
            foreach ($resultados as $fila) {
                $idCuota = $fila['id_cuota'];

                if (!isset($cuotasAgrupadas[$idCuota])) {
                    $cuotasAgrupadas[$idCuota] = [
                        'id_cuota' => $fila['id_cuota'],
                        'numero_cuota' => $fila['numero_cuota'],
                        'fecha_vencimiento' => $fila['fecha_vencimiento'],
                        'monto_cuota' => $fila['monto_cuota'],
                        'monto_pagado' => $fila['monto_pagado'],
                        'monto_pendiente' => $fila['monto_pendiente'],
                        'estado' => $fila['estado'],
                        'fecha_creacion' => $fila['fecha_creacion'],
                        'fecha_ultimo_pago' => $fila['fecha_ultimo_pago'],
                        'observaciones_cuota' => $fila['observaciones_cuota'],
                        'id_venta' => $fila['id_venta'],
                        'proforma' => $fila['proforma'],
                        'moneda' => $fila['moneda'],
                        'fecha_venta' => $fila['fecha_venta'],
                        'pagos' => []
                    ];
                }

                if ($fila['id_pago']) {
                    $cuotasAgrupadas[$idCuota]['pagos'][] = [
                        'id_pago' => $fila['id_pago'],
                        'monto_pago' => $fila['monto_pago'],
                        'fecha_pago' => $fila['fecha_pago'],
                        'fecha_registro' => $fila['fecha_registro'],
                        'forma_pago' => $fila['forma_pago'],
                        'referencia_pago' => $fila['referencia_pago'],
                        'observaciones_pago' => $fila['observaciones_pago'],
                        'comprobante_nombre' => $fila['comprobante_nombre'],
                        'comprobante_tipo' => $fila['comprobante_tipo'],
                        'usuario_registro' => $fila['usuario_registro']
                    ];
                }
            }

            return array_values($cuotasAgrupadas);
        } catch (PDOException $e) {
            error_log("Error obteniendo cuotas y pagos del cliente: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerEstadisticasCliente($cliente, $filtros = [])
    {
        try {
            $whereConditions = ["v.cliente = :cliente"];
            $params = [':cliente' => $cliente];

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "p.fecha_pago >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "p.fecha_pago <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['moneda'])) {
                $whereConditions[] = "COALESCE(v.moneda, 'Guaraníes') = :moneda";
                $params[':moneda'] = $filtros['moneda'];
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "
        SELECT 
            COALESCE(v.moneda, 'Guaraníes') as moneda,
            COUNT(DISTINCT v.id) as cantidad_ventas,
            SUM(DISTINCT v.monto_total) as total_vendido,
            COUNT(DISTINCT p.id) as cantidad_pagos,
            SUM(p.monto_pago) as total_pagado,
            SUM(DISTINCT v.monto_total) - COALESCE(SUM(p.monto_pago), 0) as total_pendiente,
            AVG(p.monto_pago) as promedio_pago,
            MAX(p.monto_pago) as mayor_pago,
            MIN(p.monto_pago) as menor_pago,
            MIN(p.fecha_pago) as primer_pago,
            MAX(p.fecha_pago) as ultimo_pago,
            COUNT(DISTINCT p.forma_pago) as formas_pago_distintas,
            STRING_AGG(DISTINCT p.forma_pago, ', ') as formas_pago_utilizadas
        FROM public.sist_ventas_presupuesto v
        LEFT JOIN public.sist_ventas_cuentas_cobrar cc ON v.id = cc.id_venta
        LEFT JOIN public.sist_ventas_pagos_cuotas p ON cc.id = p.id_cuota
        WHERE {$whereClause}
        GROUP BY COALESCE(v.moneda, 'Guaraníes')
        ORDER BY total_vendido DESC
        ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo estadísticas del cliente: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerHistorialPagosDetallado($cliente, $filtros = [])
    {
        try {
            $whereConditions = ["v.cliente = :cliente"];
            $params = [':cliente' => $cliente];

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "p.fecha_pago >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "p.fecha_pago <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['moneda'])) {
                $whereConditions[] = "COALESCE(v.moneda, 'Guaraníes') = :moneda";
                $params[':moneda'] = $filtros['moneda'];
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "
        SELECT 
            p.id as id_pago,
            p.monto_pago,
            p.fecha_pago,
            p.fecha_registro,
            p.forma_pago,
            p.referencia_pago,
            p.observaciones,
            p.comprobante_nombre,
            p.comprobante_tipo,
            cc.numero_cuota,
            cc.monto_cuota,
            cc.fecha_vencimiento,
            v.id as id_venta,
            v.proforma,
            v.moneda,
            v.fecha_venta,
            v.monto_total as monto_venta,
            ur.nombre as usuario_registro,
            CASE 
                WHEN p.fecha_pago <= cc.fecha_vencimiento THEN 'A tiempo'
                ELSE 'Tardío'
            END as puntualidad,
            p.fecha_pago - cc.fecha_vencimiento as dias_diferencia_vencimiento
        FROM public.sist_ventas_pagos_cuotas p
        INNER JOIN public.sist_ventas_cuentas_cobrar cc ON p.id_cuota = cc.id
        INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
        LEFT JOIN public.sist_ventas_usuario ur ON p.id_usuario_registro = ur.id
        WHERE {$whereClause}
        ORDER BY p.fecha_pago DESC, p.fecha_registro DESC
        ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo historial de pagos: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerComprobantesCliente($cliente, $filtros = [])
    {
        try {
            $whereConditions = [
                "v.cliente = :cliente",
                "p.comprobante_nombre IS NOT NULL",
                "p.comprobante_base64 IS NOT NULL"
            ];
            $params = [':cliente' => $cliente];

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "p.fecha_pago >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "p.fecha_pago <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "
        SELECT 
            p.id as id_pago,
            p.comprobante_nombre,
            p.comprobante_tipo,
            LENGTH(p.comprobante_base64) as tamaño_archivo,
            p.fecha_pago,
            p.monto_pago,
            p.forma_pago,
            v.proforma,
            cc.numero_cuota,
            ur.nombre as usuario_registro
        FROM public.sist_ventas_pagos_cuotas p
        INNER JOIN public.sist_ventas_cuentas_cobrar cc ON p.id_cuota = cc.id
        INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
        LEFT JOIN public.sist_ventas_usuario ur ON p.id_usuario_registro = ur.id
        WHERE {$whereClause}
        ORDER BY p.fecha_pago DESC
        ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo comprobantes: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerCuotasPendientesCliente($cliente, $filtros = [])
    {
        try {
            $whereConditions = [
                "v.cliente = :cliente",
                "cc.estado != 'PAGADO'",
                "cc.monto_pendiente > 0"
            ];
            $params = [':cliente' => $cliente];

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "
        SELECT 
            cc.id as id_cuota,
            cc.numero_cuota,
            cc.fecha_vencimiento,
            cc.monto_cuota,
            cc.monto_pagado,
            cc.monto_pendiente,
            cc.estado,
            v.proforma,
            v.moneda,
            v.fecha_venta,
            CASE 
                WHEN cc.fecha_vencimiento < CURRENT_DATE THEN CURRENT_DATE - cc.fecha_vencimiento
                ELSE 0
            END as dias_vencido,
            CASE 
                WHEN cc.fecha_vencimiento >= CURRENT_DATE THEN cc.fecha_vencimiento - CURRENT_DATE
                ELSE 0
            END as dias_para_vencimiento
        FROM public.sist_ventas_cuentas_cobrar cc
        INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
        WHERE {$whereClause}
        ORDER BY cc.fecha_vencimiento ASC
        ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo cuotas pendientes: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerDatosCumplimientoFechas($filtros = [])
    {
        try {
            $whereConditions = ["1=1"];
            $params = [];

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "p.fecha_pago >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "p.fecha_pago <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['moneda'])) {
                $whereConditions[] = "COALESCE(v.moneda, 'Guaraníes') = :moneda";
                $params[':moneda'] = $filtros['moneda'];
            }

            if (!empty($filtros['vendedor'])) {
                $whereConditions[] = "u.nombre ILIKE :vendedor";
                $params[':vendedor'] = '%' . $filtros['vendedor'] . '%';
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "
            SELECT 
                p.id as id_pago,
                p.fecha_pago,
                p.monto_pago,
                cc.fecha_vencimiento,
                cc.numero_cuota,
                v.id,
                v.cliente,
                v.proforma,
                COALESCE(v.moneda, 'Guaraníes') as moneda,
                u.nombre as vendedor,
                p.fecha_pago - cc.fecha_vencimiento as dias_diferencia,
                CASE 
                    WHEN p.fecha_pago <= cc.fecha_vencimiento THEN 'A tiempo'
                    WHEN p.fecha_pago - cc.fecha_vencimiento <= 7 THEN 'Atraso leve'
                    WHEN p.fecha_pago - cc.fecha_vencimiento <= 15 THEN 'Atraso moderado'
                    WHEN p.fecha_pago - cc.fecha_vencimiento <= 30 THEN 'Atraso alto'
                    ELSE 'Atraso crítico'
                END as categoria_cumplimiento,
                EXTRACT(EPOCH FROM cc.fecha_vencimiento) as timestamp_vencimiento,
                EXTRACT(EPOCH FROM p.fecha_pago) as timestamp_pago_real
            FROM public.sist_ventas_pagos_cuotas p
            INNER JOIN public.sist_ventas_cuentas_cobrar cc ON p.id_cuota = cc.id
            INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
            LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
            WHERE {$whereClause}
            ORDER BY p.fecha_pago ASC
            LIMIT 50
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("Query cumplimiento ejecutada. Resultados encontrados: " . count($resultados));

            return $resultados;
        } catch (PDOException $e) {
            error_log("Error obteniendo datos de cumplimiento: " . $e->getMessage());
            return [];
        }
    }


    public function obtenerDatosClientesConPuntajeReal($filtros = [])
    {
        try {
            $whereConditions = ["1=1"];
            $params = [];

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "p.fecha_pago >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "p.fecha_pago <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['moneda'])) {
                $whereConditions[] = "COALESCE(v.moneda, 'Guaraníes') = :moneda";
                $params[':moneda'] = $filtros['moneda'];
            }

            if (!empty($filtros['vendedor'])) {
                $whereConditions[] = "u.nombre ILIKE :vendedor";
                $params[':vendedor'] = '%' . $filtros['vendedor'] . '%';
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "
            WITH datos_puntaje AS (
                SELECT 
                    v.cliente,
                    u.nombre as vendedor,
                    p.fecha_pago - cc.fecha_vencimiento as dias_diferencia,
                    CASE 
                        WHEN p.fecha_pago <= cc.fecha_vencimiento THEN 100
                        WHEN p.fecha_pago - cc.fecha_vencimiento <= 7 THEN 85
                        WHEN p.fecha_pago - cc.fecha_vencimiento <= 15 THEN 70
                        WHEN p.fecha_pago - cc.fecha_vencimiento <= 30 THEN 50
                        ELSE 25
                    END as puntaje_pago
                FROM public.sist_ventas_pagos_cuotas p
                INNER JOIN public.sist_ventas_cuentas_cobrar cc ON p.id_cuota = cc.id
                INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
                LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                WHERE {$whereClause}
            )
            SELECT 
                cliente as nombre,
                vendedor,
                COUNT(*) as total_pagos,
                ROUND(AVG(puntaje_pago), 1) as puntaje_cumplimiento
            FROM datos_puntaje
            GROUP BY cliente, vendedor
            ORDER BY puntaje_cumplimiento DESC, total_pagos DESC
            LIMIT 10
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("Clientes con puntaje real obtenidos: " . count($resultados));

            return $resultados;
        } catch (PDOException $e) {
            error_log("Error obteniendo clientes con puntaje real: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerEstadisticasAtrasoReales($filtros = [])
    {
        try {
            $whereConditions = ["1=1"];
            $params = [];

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "p.fecha_pago >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "p.fecha_pago <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['moneda'])) {
                $whereConditions[] = "COALESCE(v.moneda, 'Guaraníes') = :moneda";
                $params[':moneda'] = $filtros['moneda'];
            }

            if (!empty($filtros['vendedor'])) {
                $whereConditions[] = "u.nombre ILIKE :vendedor";
                $params[':vendedor'] = '%' . $filtros['vendedor'] . '%';
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "
            SELECT 
                SUM(CASE WHEN p.fecha_pago <= cc.fecha_vencimiento THEN 1 ELSE 0 END) as al_dia,
                SUM(CASE WHEN p.fecha_pago - cc.fecha_vencimiento BETWEEN 1 AND 7 THEN 1 ELSE 0 END) as atraso_1_7,
                SUM(CASE WHEN p.fecha_pago - cc.fecha_vencimiento BETWEEN 8 AND 15 THEN 1 ELSE 0 END) as atraso_8_15,
                SUM(CASE WHEN p.fecha_pago - cc.fecha_vencimiento BETWEEN 16 AND 30 THEN 1 ELSE 0 END) as atraso_16_30,
                SUM(CASE WHEN p.fecha_pago - cc.fecha_vencimiento > 30 THEN 1 ELSE 0 END) as atraso_mas_30
            FROM public.sist_ventas_pagos_cuotas p
            INNER JOIN public.sist_ventas_cuentas_cobrar cc ON p.id_cuota = cc.id
            INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
            LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
            WHERE {$whereClause}
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resultado) {
                return [
                    'al_dia' => 0,
                    'atraso_1_7' => 0,
                    'atraso_8_15' => 0,
                    'atraso_16_30' => 0,
                    'atraso_mas_30' => 0
                ];
            }

            foreach ($resultado as $key => $value) {
                $resultado[$key] = (int)($value ?? 0);
            }

            error_log("Estadísticas de atraso reales: " . json_encode($resultado));

            return $resultado;
        } catch (PDOException $e) {
            error_log("Error obteniendo estadísticas de atraso: " . $e->getMessage());
            return [
                'al_dia' => 0,
                'atraso_1_7' => 0,
                'atraso_8_15' => 0,
                'atraso_16_30' => 0,
                'atraso_mas_30' => 0
            ];
        }
    }
}
