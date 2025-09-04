<?php

class CuentasCobrarRepository
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

    public function obtenerFechaInicioCredito($idVenta)
    {
        try {
            $sql = "SELECT fecha_inicio_credito, fecha_venta 
                    FROM public.sist_ventas_presupuesto 
                    WHERE id = :id_venta";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado) {
                return $resultado['fecha_inicio_credito'] ?? $resultado['fecha_venta'];
            }

            return null;
        } catch (PDOException $e) {
            error_log("Error obteniendo fecha inicio crédito: " . $e->getMessage());
            return null;
        }
    }

    public function actualizarFechaInicioCredito($idVenta, $fechaInicio)
    {
        try {
            $sql = "UPDATE public.sist_ventas_presupuesto 
                    SET fecha_inicio_credito = :fecha_inicio 
                    WHERE id = :id_venta";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error actualizando fecha inicio crédito: " . $e->getMessage());
            return false;
        }
    }

    public function generarCuotasVenta($idVenta, $montoTotal, $tipoCreditoStr, $fechaVenta, $fechaInicioCustom = null, $actualizarFechaInicio = false)
    {
        try {
            $diasCuotas = explode('/', $tipoCreditoStr);
            $numeroCuotas = count($diasCuotas);

            if ($numeroCuotas === 0) {
                throw new Exception("Tipo de crédito inválido: $tipoCreditoStr");
            }

            $fechaInicioCalculos = null;

            if ($fechaInicioCustom) {
                $fechaInicioCalculos = $fechaInicioCustom;

                if ($actualizarFechaInicio) {
                    $this->actualizarFechaInicioCredito($idVenta, $fechaInicioCustom);
                }
            } else {
                $fechaGuardada = $this->obtenerFechaInicioCredito($idVenta);
                $fechaInicioCalculos = $fechaGuardada ?? $fechaVenta;
            }

            $fechaInicioObj = new DateTime($fechaInicioCalculos);

            $montoPorCuota = round($montoTotal / $numeroCuotas, 2);

            $montoUltimaCuota = $montoTotal - ($montoPorCuota * ($numeroCuotas - 1));

            $this->eliminarCuotasVenta($idVenta);

            for ($i = 0; $i < $numeroCuotas; $i++) {
                $numeroCuota = $i + 1;
                $diasVencimiento = (int)$diasCuotas[$i];

                $fechaVencimiento = clone $fechaInicioObj;
                $intervalo = new DateInterval("P{$diasVencimiento}D");
                $fechaVencimiento->add($intervalo);

                $montoCuota = ($numeroCuota === $numeroCuotas) ? $montoUltimaCuota : $montoPorCuota;

                $sql = "INSERT INTO public.sist_ventas_cuentas_cobrar 
                    (id_venta, numero_cuota, fecha_vencimiento, monto_cuota, monto_pendiente, estado) 
                    VALUES (:id_venta, :numero_cuota, :fecha_vencimiento, :monto_cuota, :monto_pendiente, 'PENDIENTE')";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
                $stmt->bindParam(':numero_cuota', $numeroCuota, PDO::PARAM_INT);
                $stmt->bindParam(':fecha_vencimiento', $fechaVencimiento->format('Y-m-d'), PDO::PARAM_STR);
                $stmt->bindParam(':monto_cuota', $montoCuota, PDO::PARAM_STR);
                $stmt->bindParam(':monto_pendiente', $montoCuota, PDO::PARAM_STR);

                if (!$stmt->execute()) {
                    throw new Exception("Error al generar cuota $numeroCuota");
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Error generando cuotas: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerDatosVentaCredito($idVenta)
    {
        try {
            $sql = "SELECT v.*, 
                           COALESCE(v.fecha_inicio_credito, v.fecha_venta) as fecha_calculo_efectiva,
                           v.fecha_inicio_credito IS NOT NULL as tiene_fecha_personalizada
                    FROM public.sist_ventas_presupuesto v 
                    WHERE v.id = :id_venta";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo datos venta crédito: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerCuentasCobrar($filtros = [], $limite = 15, $offset = 0)
    {
        try {
            $whereConditions = ["1=1"];
            $params = [];

            $whereConditions[] = "v.estado != 'Rechazado'";

            if (!empty($filtros['id_venta'])) {
                $whereConditions[] = "v.id::text ILIKE :id_venta";
                $params[':id_venta'] = '%' . $filtros['id_venta'] . '%';
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['estado'])) {
                $whereConditions[] = "cc.estado = :estado";
                $params[':estado'] = $filtros['estado'];
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "cc.fecha_vencimiento >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "cc.fecha_vencimiento <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['solo_vencidas'])) {
                $whereConditions[] = "cc.fecha_vencimiento < CURRENT_DATE AND cc.estado != 'PAGADO'";
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "SELECT 
                cc.id as primera_cuota_id,
                v.id as id_venta,
                v.cliente,
                COALESCE(v.moneda, 'Guaraníes') as moneda,
                v.monto_total as total_venta,
                v.fecha_venta,
                v.tipocredito,
                v.fecha_inicio_credito,
                COALESCE(v.fecha_inicio_credito, v.fecha_venta) as fecha_calculo_efectiva,
                v.fecha_inicio_credito IS NOT NULL as tiene_fecha_personalizada,
                u.nombre as vendedor,
                totales.total_cuotas,
                totales.monto_total_cuotas,
                totales.monto_total_pagado,
                totales.monto_total_pendiente,
                CASE 
                    WHEN totales.monto_total_pendiente = 0 THEN 'PAGADO'
                    WHEN totales.monto_total_pagado > 0 THEN 'PARCIAL'
                    WHEN totales.proximo_vencimiento < CURRENT_DATE THEN 'VENCIDA'
                    ELSE 'PENDIENTE'
                END AS estado_venta,
                totales.proximo_vencimiento,
                (totales.proximo_vencimiento - CURRENT_DATE) AS dias_vencimiento
            FROM public.sist_ventas_cuentas_cobrar cc
            INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
            LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
            INNER JOIN (
                SELECT 
                    id_venta,
                    COUNT(*) as total_cuotas,
                    SUM(monto_cuota) as monto_total_cuotas,
                    SUM(monto_pagado) as monto_total_pagado,
                    SUM(monto_pendiente) as monto_total_pendiente,
                    MIN(CASE WHEN estado != 'PAGADO' THEN fecha_vencimiento END) as proximo_vencimiento
                FROM public.sist_ventas_cuentas_cobrar
                GROUP BY id_venta
            ) totales ON v.id = totales.id_venta
            WHERE {$whereClause}
            AND cc.numero_cuota = 1
            AND totales.monto_total_pendiente > 0
            ORDER BY v.id DESC
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
            error_log("Error obteniendo cuentas por cobrar: " . $e->getMessage());
            return [];
        }
    }

    public function contarCuentasCobrar($filtros = [])
    {
        try {
            $whereConditions = ["1=1"];
            $params = [];

            $whereConditions[] = "v.estado != 'Rechazado'";

            if (!empty($filtros['id_venta'])) {
                $whereConditions[] = "v.id::text ILIKE :id_venta";
                $params[':id_venta'] = '%' . $filtros['id_venta'] . '%';
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['estado'])) {
                $whereConditions[] = "cc.estado = :estado";
                $params[':estado'] = $filtros['estado'];
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "cc.fecha_vencimiento >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "cc.fecha_vencimiento <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['solo_vencidas'])) {
                $whereConditions[] = "cc.fecha_vencimiento < CURRENT_DATE AND cc.estado != 'PAGADO'";
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "SELECT COUNT(DISTINCT v.id) as total 
            FROM public.sist_ventas_cuentas_cobrar cc
            INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
            INNER JOIN (
                SELECT 
                    id_venta,
                    SUM(monto_pendiente) as monto_total_pendiente
                FROM public.sist_ventas_cuentas_cobrar
                GROUP BY id_venta
            ) totales ON v.id = totales.id_venta
            WHERE {$whereClause}
            AND totales.monto_total_pendiente > 0";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (PDOException $e) {
            error_log("Error contando cuentas por cobrar: " . $e->getMessage());
            return 0;
        }
    }

    public function obtenerCuotaPorId($idCuota)
    {
        try {
            $sql = "SELECT 
                        cc.*,
                        v.cliente,
                        COALESCE(v.moneda, 'Guaraníes') as moneda,
                        v.monto_total as total_venta,
                        v.fecha_venta,
                        v.tipocredito,
                        v.fecha_inicio_credito,
                        COALESCE(v.fecha_inicio_credito, v.fecha_venta) as fecha_calculo_efectiva,
                        v.fecha_inicio_credito IS NOT NULL as tiene_fecha_personalizada,
                        u.nombre as vendedor
                    FROM public.sist_ventas_cuentas_cobrar cc
                    INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
                    LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                    WHERE cc.id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $idCuota, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo cuota: " . $e->getMessage());
            return false;
        }
    }

    public function registrarPagoCuota($idCuota, $montoPago, $fechaPago, $idUsuario, $formaPago, $referencia, $observaciones, $comprobante = null, $redistribuir = true, $completarCuota = false)
    {
        try {
            $cuota = $this->obtenerCuotaPorId($idCuota);
            if (!$cuota) {
                throw new Exception("Cuota no encontrada");
            }

            $todasLasCuotas = $this->obtenerCuotasPorVenta($cuota['id_venta']);
            $cuotasPendientes = array_filter($todasLasCuotas, function ($c) {
                return $c['monto_pendiente'] > 0;
            });

            $cuotasPendientesArray = array_values($cuotasPendientes);
            $esUltimaCuota = count($cuotasPendientesArray) === 1 && $cuotasPendientesArray[0]['id'] == $idCuota;
            $montoRestante = $montoPago;
            $cuotasAfectadas = [];

            if ($montoPago <= 0) {
                throw new Exception("El monto del pago debe ser mayor a 0");
            }

            if ($montoPago > $cuota['monto_pendiente'] && !$redistribuir && !$completarCuota) {
                throw new Exception("El monto excede el saldo de la cuota (redistribución deshabilitada)");
            }

            if ($esUltimaCuota) {
                if (abs($montoPago - $cuota['monto_pendiente']) > 0.01) {
                    throw new Exception("Última cuota: debe pagar exactamente " . number_format($cuota['monto_pendiente'], 2, ',', '.'));
                }

                $nuevoMontoPagado = $cuota['monto_pagado'] + $montoPago;
                $nuevoMontoPendiente = 0;
                $nuevoEstado = 'PAGADO';

                $sqlActualizar = "UPDATE public.sist_ventas_cuentas_cobrar 
                     SET monto_pagado = :monto_pagado, 
                         monto_pendiente = :monto_pendiente, 
                         estado = :estado,
                         fecha_ultimo_pago = CURRENT_TIMESTAMP
                     WHERE id = :id";

                $stmt = $this->conexion->prepare($sqlActualizar);
                $stmt->bindParam(':monto_pagado', $nuevoMontoPagado, PDO::PARAM_STR);
                $stmt->bindParam(':monto_pendiente', $nuevoMontoPendiente, PDO::PARAM_STR);
                $stmt->bindParam(':estado', $nuevoEstado, PDO::PARAM_STR);
                $stmt->bindParam(':id', $idCuota, PDO::PARAM_INT);

                if (!$stmt->execute()) {
                    throw new Exception("Error al actualizar la última cuota");
                }

                $cuotasAfectadas[] = [
                    'numero' => $cuota['numero_cuota'],
                    'monto_aplicado' => $montoPago,
                    'nuevo_estado' => 'VENTA CERRADA - ÚLTIMA CUOTA'
                ];

                $observacionesCompletas = $observaciones . "\n\n[ÚLTIMA CUOTA - VENTA CERRADA]\n";
                $observacionesCompletas .= "• Cuota {$cuota['numero_cuota']}: VENTA COMPLETAMENTE CANCELADA\n";
                $observacionesCompletas .= "• Monto final: " . number_format($montoPago, 2, ',', '.') . "\n";
            } elseif ($completarCuota) {
                $nuevoMontoPagado = $montoPago;
                $nuevoMontoPendiente = 0;
                $nuevoEstado = 'PAGADO';

                $sqlActualizar = "UPDATE public.sist_ventas_cuentas_cobrar 
                     SET monto_pagado = :monto_pagado, 
                         monto_pendiente = :monto_pendiente, 
                         estado = :estado,
                         fecha_ultimo_pago = CURRENT_TIMESTAMP
                     WHERE id = :id";

                $stmt = $this->conexion->prepare($sqlActualizar);
                $stmt->bindParam(':monto_pagado', $nuevoMontoPagado, PDO::PARAM_STR);
                $stmt->bindParam(':monto_pendiente', $nuevoMontoPendiente, PDO::PARAM_STR);
                $stmt->bindParam(':estado', $nuevoEstado, PDO::PARAM_STR);
                $stmt->bindParam(':id', $idCuota, PDO::PARAM_INT);

                if (!$stmt->execute()) {
                    throw new Exception("Error al actualizar la cuota");
                }

                $saldoOriginalRestante = $cuota['monto_pendiente'] - $montoPago;

                if ($saldoOriginalRestante > 0) {
                    $cuotasPendientesParaRedistribuir = array_filter($todasLasCuotas, function ($c) use ($idCuota) {
                        return $c['id'] != $idCuota && $c['monto_pendiente'] > 0;
                    });

                    if (count($cuotasPendientesParaRedistribuir) > 0) {
                        $montoADistribuir = round($saldoOriginalRestante / count($cuotasPendientesParaRedistribuir), 2);

                        foreach ($cuotasPendientesParaRedistribuir as $cuotaPendiente) {
                            $nuevoMontoPendienteOtra = $cuotaPendiente['monto_pendiente'] + $montoADistribuir;
                            $nuevoMontoCuotaOtra = $cuotaPendiente['monto_pagado'] + $nuevoMontoPendienteOtra;

                            $sqlOtra = "UPDATE public.sist_ventas_cuentas_cobrar 
                               SET monto_cuota = :monto_cuota,
                                   monto_pendiente = :monto_pendiente
                               WHERE id = :id";

                            $stmtOtra = $this->conexion->prepare($sqlOtra);
                            $stmtOtra->bindParam(':monto_cuota', $nuevoMontoCuotaOtra, PDO::PARAM_STR);
                            $stmtOtra->bindParam(':monto_pendiente', $nuevoMontoPendienteOtra, PDO::PARAM_STR);
                            $stmtOtra->bindParam(':id', $cuotaPendiente['id'], PDO::PARAM_INT);
                            $stmtOtra->execute();

                            $cuotasAfectadas[] = [
                                'numero' => $cuotaPendiente['numero_cuota'],
                                'monto_agregado' => $montoADistribuir,
                                'nuevo_pendiente' => $nuevoMontoPendienteOtra
                            ];
                        }
                    }
                }

                $cuotasAfectadas[] = [
                    'numero' => $cuota['numero_cuota'],
                    'monto_aplicado' => $montoPago,
                    'nuevo_estado' => 'COMPLETADA'
                ];

                $observacionesCompletas = $observaciones;
                $observacionesCompletas .= "\n\n[CUOTA COMPLETADA CON REDISTRIBUCIÓN]\n";
                $observacionesCompletas .= "• Cuota {$cuota['numero_cuota']}: Marcada como PAGADA con {$montoPago}\n";

                if (isset($saldoOriginalRestante) && $saldoOriginalRestante > 0) {
                    $observacionesCompletas .= "• Saldo original pendiente: " . number_format($cuota['monto_pendiente'], 2, ',', '.') . "\n";
                    $observacionesCompletas .= "• Saldo redistribuido: " . number_format($saldoOriginalRestante, 2, ',', '.') . "\n";
                    $observacionesCompletas .= "• Redistribuido entre " . count($cuotasPendientesParaRedistribuir) . " cuota(s) pendiente(s)\n";

                    foreach ($cuotasAfectadas as $afectada) {
                        if ($afectada['numero'] != $cuota['numero_cuota']) {
                            $observacionesCompletas .= "  - Cuota {$afectada['numero']}: +{$afectada['monto_agregado']} → Pendiente: {$afectada['nuevo_pendiente']}\n";
                        }
                    }
                } else {
                    $observacionesCompletas .= "• Sin redistribución: Pago exacto del saldo pendiente\n";
                }
            } else {
                $montoAplicar = min($montoRestante, $cuota['monto_pendiente']);
                $nuevoMontoPagado = $cuota['monto_pagado'] + $montoAplicar;
                $nuevoMontoPendiente = $cuota['monto_pendiente'] - $montoAplicar;

                if ($nuevoMontoPendiente <= 0.01) {
                    $nuevoEstado = 'PAGADO';
                    $nuevoMontoPendiente = 0;
                } elseif ($nuevoMontoPagado > 0) {
                    $nuevoEstado = 'PARCIAL';
                } else {
                    $nuevoEstado = 'PENDIENTE';
                }

                $sqlActualizar = "UPDATE public.sist_ventas_cuentas_cobrar 
                     SET monto_pagado = :monto_pagado, 
                         monto_pendiente = :monto_pendiente, 
                         estado = :estado,
                         fecha_ultimo_pago = CURRENT_TIMESTAMP
                     WHERE id = :id";

                $stmt = $this->conexion->prepare($sqlActualizar);
                $stmt->bindParam(':monto_pagado', $nuevoMontoPagado, PDO::PARAM_STR);
                $stmt->bindParam(':monto_pendiente', $nuevoMontoPendiente, PDO::PARAM_STR);
                $stmt->bindParam(':estado', $nuevoEstado, PDO::PARAM_STR);
                $stmt->bindParam(':id', $idCuota, PDO::PARAM_INT);

                if (!$stmt->execute()) {
                    throw new Exception("Error al actualizar la cuota");
                }

                $cuotasAfectadas[] = [
                    'numero' => $cuota['numero_cuota'],
                    'monto_aplicado' => $montoAplicar,
                    'nuevo_estado' => strtoupper($nuevoEstado)
                ];

                $observacionesCompletas = $observaciones;
                if ($montoAplicar < $montoPago) {
                    $sobrante = $montoPago - $montoAplicar;
                    $observacionesCompletas .= "\n\n[PAGO TRADICIONAL - SIN REDISTRIBUCIÓN]\n";
                    $observacionesCompletas .= "• Aplicado a cuota {$cuota['numero_cuota']}: {$montoAplicar}\n";
                    $observacionesCompletas .= "• Sobrante no aplicado: {$sobrante} (redistribución deshabilitada)\n";
                } else {
                    $observacionesCompletas .= "\n\n[PAGO TRADICIONAL]\n";
                    $observacionesCompletas .= "• Cuota {$cuota['numero_cuota']}: {$montoAplicar} → {$nuevoEstado}\n";
                }
            }

            $sqlPago = "INSERT INTO public.sist_ventas_pagos_cuotas 
            (id_cuota, monto_pago, fecha_pago, id_usuario_registro, forma_pago, referencia_pago, observaciones, comprobante_nombre, comprobante_tipo, comprobante_base64) 
            VALUES (:id_cuota, :monto_pago, :fecha_pago, :id_usuario, :forma_pago, :referencia, :observaciones, :comp_nombre, :comp_tipo, :comp_base64)";

            $stmt = $this->conexion->prepare($sqlPago);
            $stmt->bindParam(':id_cuota', $idCuota, PDO::PARAM_INT);
            $stmt->bindParam(':monto_pago', $montoPago, PDO::PARAM_STR);
            $stmt->bindParam(':fecha_pago', $fechaPago, PDO::PARAM_STR);
            $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmt->bindParam(':forma_pago', $formaPago, PDO::PARAM_STR);
            $stmt->bindParam(':referencia', $referencia, PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $observacionesCompletas, PDO::PARAM_STR);

            if ($comprobante) {
                $stmt->bindParam(':comp_nombre', $comprobante['nombre'], PDO::PARAM_STR);
                $stmt->bindParam(':comp_tipo', $comprobante['tipo'], PDO::PARAM_STR);
                $stmt->bindParam(':comp_base64', $comprobante['base64'], PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':comp_nombre', null, PDO::PARAM_NULL);
                $stmt->bindValue(':comp_tipo', null, PDO::PARAM_NULL);
                $stmt->bindValue(':comp_base64', null, PDO::PARAM_NULL);
            }

            if (!$stmt->execute()) {
                throw new Exception("Error al registrar el pago");
            }

            $tipoOperacion = $esUltimaCuota ? 'CIERRE_VENTA' : ($completarCuota ? 'COMPLETAR_REDISTRIBUIR' : 'PAGO_TRADICIONAL');
            error_log("PAGO_REGISTRADO - Tipo: {$tipoOperacion} | Venta: {$cuota['id_venta']} | Cuota: {$cuota['numero_cuota']} | Monto: {$montoPago} | Usuario: {$idUsuario}");

            return true;
        } catch (Exception $e) {
            error_log("Error registrando pago en cuota {$idCuota}: " . $e->getMessage());
            throw $e;
        }
    }

    public function obtenerPagosCuota($idCuota)
    {
        try {
            $sql = "SELECT p.*, u.nombre as usuario_registro
                    FROM public.sist_ventas_pagos_cuotas p
                    LEFT JOIN public.sist_ventas_usuario u ON p.id_usuario_registro = u.id
                    WHERE p.id_cuota = :id_cuota
                    ORDER BY p.fecha_registro DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_cuota', $idCuota, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo pagos de cuota: " . $e->getMessage());
            return [];
        }
    }

    public function actualizarMontoCuota($idCuota, $nuevoMonto)
    {
        try {
            $cuota = $this->obtenerCuotaPorId($idCuota);
            if (!$cuota) {
                return false;
            }

            $nuevoMontoPendiente = $nuevoMonto - $cuota['monto_pagado'];

            if ($nuevoMontoPendiente <= 0) {
                $nuevoEstado = 'PAGADO';
                $nuevoMontoPendiente = 0;
            } elseif ($cuota['monto_pagado'] > 0) {
                $nuevoEstado = 'PARCIAL';
            } else {
                $nuevoEstado = 'PENDIENTE';
            }

            $sql = "UPDATE public.sist_ventas_cuentas_cobrar 
                    SET monto_cuota = :monto_cuota, 
                        monto_pendiente = :monto_pendiente,
                        estado = :estado
                    WHERE id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':monto_cuota', $nuevoMonto, PDO::PARAM_STR);
            $stmt->bindParam(':monto_pendiente', $nuevoMontoPendiente, PDO::PARAM_STR);
            $stmt->bindParam(':estado', $nuevoEstado, PDO::PARAM_STR);
            $stmt->bindParam(':id', $idCuota, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error actualizando monto de cuota: " . $e->getMessage());
            return false;
        }
    }

    public function eliminarCuotasVenta($idVenta)
    {
        try {
            $sql = "DELETE FROM public.sist_ventas_pagos_cuotas 
                    WHERE id_cuota IN (
                        SELECT id FROM public.sist_ventas_cuentas_cobrar 
                        WHERE id_venta = :id_venta
                    )";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            $sql = "DELETE FROM public.sist_ventas_cuentas_cobrar WHERE id_venta = :id_venta";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error eliminando cuotas: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerEstadisticas()
    {
        try {
            $stats = [];

            $sql = "SELECT COUNT(*) as total, COALESCE(SUM(monto_pendiente), 0) as monto_total
                    FROM public.sist_ventas_cuentas_cobrar 
                    WHERE estado IN ('PENDIENTE', 'PARCIAL')";
            $result = $this->conexion->query($sql)->fetch(PDO::FETCH_ASSOC);
            $stats['pendientes'] = $result['total'];
            $stats['monto_pendiente'] = $result['monto_total'];

            $sql = "SELECT COUNT(*) as total, COALESCE(SUM(monto_pendiente), 0) as monto_total
                    FROM public.sist_ventas_cuentas_cobrar 
                    WHERE fecha_vencimiento < CURRENT_DATE AND estado IN ('PENDIENTE', 'PARCIAL')";
            $result = $this->conexion->query($sql)->fetch(PDO::FETCH_ASSOC);
            $stats['vencidas'] = $result['total'];
            $stats['monto_vencido'] = $result['monto_total'];

            $sql = "SELECT COUNT(*) as total, COALESCE(SUM(monto_cuota), 0) as monto_total
                    FROM public.sist_ventas_cuentas_cobrar 
                    WHERE estado = 'PAGADO' 
                    AND EXTRACT(MONTH FROM fecha_ultimo_pago) = EXTRACT(MONTH FROM CURRENT_DATE)
                    AND EXTRACT(YEAR FROM fecha_ultimo_pago) = EXTRACT(YEAR FROM CURRENT_DATE)";
            $result = $this->conexion->query($sql)->fetch(PDO::FETCH_ASSOC);
            $stats['pagadas_mes'] = $result['total'];
            $stats['monto_cobrado_mes'] = $result['monto_total'];

            return $stats;
        } catch (PDOException $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [
                'pendientes' => 0,
                'monto_pendiente' => 0,
                'vencidas' => 0,
                'monto_vencido' => 0,
                'pagadas_mes' => 0,
                'monto_cobrado_mes' => 0
            ];
        }
    }

    public function obtenerCuotasPorVenta($idVenta)
    {
        try {
            $sql = "SELECT cc.*, 
                           COALESCE(v.moneda, 'Guaraníes') as moneda,
                           v.fecha_inicio_credito,
                           COALESCE(v.fecha_inicio_credito, v.fecha_venta) as fecha_calculo_efectiva,
                           v.fecha_inicio_credito IS NOT NULL as tiene_fecha_personalizada
                    FROM public.sist_ventas_cuentas_cobrar cc
                    LEFT JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
                    WHERE cc.id_venta = :id_venta 
                    ORDER BY cc.numero_cuota ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo cuotas por venta: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerCuentasPagadas($filtros = [], $limite = 15, $offset = 0)
    {
        try {
            $whereConditions = ["1=1"];
            $params = [];

            if (!empty($filtros['id_venta'])) {
                $whereConditions[] = "v.id::text ILIKE :id_venta";
                $params[':id_venta'] = '%' . $filtros['id_venta'] . '%';
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "v.fecha_venta >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "v.fecha_venta <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['fecha_pago_desde'])) {
                $whereConditions[] = "totales.fecha_ultimo_pago >= :fecha_pago_desde";
                $params[':fecha_pago_desde'] = $filtros['fecha_pago_desde'];
            }

            if (!empty($filtros['fecha_pago_hasta'])) {
                $whereConditions[] = "totales.fecha_ultimo_pago <= :fecha_pago_hasta";
                $params[':fecha_pago_hasta'] = $filtros['fecha_pago_hasta'];
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "SELECT 
                cc.id as primera_cuota_id,
                v.id as id_venta,
                v.cliente,
                COALESCE(v.moneda, 'Guaraníes') as moneda,
                v.monto_total as total_venta,
                v.fecha_venta,
                v.tipocredito,
                v.fecha_inicio_credito,
                COALESCE(v.fecha_inicio_credito, v.fecha_venta) as fecha_calculo_efectiva,
                v.fecha_inicio_credito IS NOT NULL as tiene_fecha_personalizada,
                u.nombre as vendedor,
                totales.total_cuotas,
                totales.monto_total_cuotas,
                totales.monto_total_pagado,
                totales.monto_total_pendiente,
                totales.fecha_ultimo_pago,
                'PAGADO' AS estado_venta,
                (CURRENT_DATE - totales.fecha_ultimo_pago::date) AS dias_desde_pago
            FROM public.sist_ventas_cuentas_cobrar cc
            INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
            LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
            INNER JOIN (
                SELECT 
                    id_venta,
                    COUNT(*) as total_cuotas,
                    SUM(monto_cuota) as monto_total_cuotas,
                    SUM(monto_pagado) as monto_total_pagado,
                    SUM(monto_pendiente) as monto_total_pendiente,
                    MAX(fecha_ultimo_pago) as fecha_ultimo_pago
                FROM public.sist_ventas_cuentas_cobrar
                GROUP BY id_venta
                HAVING SUM(monto_pendiente) = 0
            ) totales ON v.id = totales.id_venta
            WHERE {$whereClause}
              AND cc.numero_cuota = 1
            ORDER BY totales.fecha_ultimo_pago DESC
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
            error_log("Error obteniendo cuentas pagadas: " . $e->getMessage());
            return [];
        }
    }

    public function contarCuentasPagadas($filtros = [])
    {
        try {
            $whereConditions = ["1=1"];
            $params = [];

            if (!empty($filtros['id_venta'])) {
                $whereConditions[] = "v.id::text ILIKE :id_venta";
                $params[':id_venta'] = '%' . $filtros['id_venta'] . '%';
            }

            if (!empty($filtros['cliente'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "v.fecha_venta >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "v.fecha_venta <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            if (!empty($filtros['fecha_pago_desde'])) {
                $whereConditions[] = "totales.fecha_ultimo_pago >= :fecha_pago_desde";
                $params[':fecha_pago_desde'] = $filtros['fecha_pago_desde'];
            }

            if (!empty($filtros['fecha_pago_hasta'])) {
                $whereConditions[] = "totales.fecha_ultimo_pago <= :fecha_pago_hasta";
                $params[':fecha_pago_hasta'] = $filtros['fecha_pago_hasta'];
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "SELECT COUNT(DISTINCT v.id) as total 
            FROM public.sist_ventas_cuentas_cobrar cc
            INNER JOIN public.sist_ventas_presupuesto v ON cc.id_venta = v.id
            INNER JOIN (
                SELECT 
                    id_venta,
                    MAX(fecha_ultimo_pago) as fecha_ultimo_pago,
                    SUM(monto_pendiente) as monto_total_pendiente
                FROM public.sist_ventas_cuentas_cobrar
                GROUP BY id_venta
                HAVING SUM(monto_pendiente) = 0
            ) totales ON v.id = totales.id_venta
            WHERE {$whereClause}";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (PDOException $e) {
            error_log("Error contando cuentas pagadas: " . $e->getMessage());
            return 0;
        }
    }
}
