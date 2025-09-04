<?php

class CuentasCobrarService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function procesarVentaAprobada($idVenta, $montoTotal, $tipoCreditoStr, $fechaVenta, $manejarTransaccion = true, $fechaInicioCustom = null)
    {
        try {
            if ($manejarTransaccion) {
                $this->repository->beginTransaction();
            }

            $actualizarFechaInicio = $fechaInicioCustom !== null;

            $resultado = $this->repository->generarCuotasVenta(
                $idVenta,
                $montoTotal,
                $tipoCreditoStr,
                $fechaVenta,
                $fechaInicioCustom,
                $actualizarFechaInicio
            );

            if ($resultado) {
                if ($manejarTransaccion) {
                    $this->repository->commit();
                }

                $mensaje = 'Cuotas generadas correctamente';
                if ($fechaInicioCustom) {
                    $fechaFormateada = date('d/m/Y', strtotime($fechaInicioCustom));
                    $mensaje .= " con fecha de inicio personalizada: {$fechaFormateada}";
                }

                return ['success' => true, 'mensaje' => $mensaje];
            } else {
                if ($manejarTransaccion) {
                    $this->repository->rollBack();
                }
                return ['success' => false, 'error' => 'Error al generar las cuotas'];
            }
        } catch (Exception $e) {
            if ($manejarTransaccion) {
                $this->repository->rollBack();
            }
            error_log("Error procesando venta aprobada: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno: ' . $e->getMessage()];
        }
    }

    public function regenerarCuotasVenta($idVenta, $montoTotal, $tipoCreditoStr, $fechaVenta, $manejarTransaccion = true, $fechaInicioCustom = null)
    {
        try {
            if ($manejarTransaccion) {
                $this->repository->beginTransaction();
            }

            if (!$fechaInicioCustom) {
                throw new Exception("Fecha de inicio personalizada es requerida para regenerar cuotas");
            }

            if (strtotime($fechaInicioCustom) > time()) {
                throw new Exception("La fecha de inicio no puede ser futura");
            }

            $datosVenta = $this->repository->obtenerDatosVentaCredito($idVenta);
            if (!$datosVenta) {
                throw new Exception("Venta no encontrada");
            }

            $cuotasExistentes = $this->repository->obtenerCuotasPorVenta($idVenta);
            $hayPagos = false;
            $totalPagado = 0;

            foreach ($cuotasExistentes as $cuota) {
                if ($cuota['monto_pagado'] > 0) {
                    $hayPagos = true;
                    $totalPagado += $cuota['monto_pagado'];
                }
            }

            error_log("REGENERANDO CUOTAS - Venta: {$idVenta}, Nueva fecha: {$fechaInicioCustom}, Hay pagos: " . ($hayPagos ? 'Sí' : 'No') . ", Total pagado: {$totalPagado}");

            $resultado = $this->repository->generarCuotasVenta(
                $idVenta,
                $montoTotal,
                $tipoCreditoStr,
                $fechaVenta,
                $fechaInicioCustom,
                true
            );

            if ($resultado) {
                if ($manejarTransaccion) {
                    $this->repository->commit();
                }

                $fechaFormateada = date('d/m/Y', strtotime($fechaInicioCustom));
                $mensaje = "Cuotas regeneradas con nueva fecha de inicio: {$fechaFormateada}";

                if ($hayPagos) {
                    $mensaje .= " (NOTA: Se perdieron {$totalPagado} en pagos registrados - será necesario re-registrarlos)";
                }

                return [
                    'success' => true,
                    'mensaje' => $mensaje,
                    'hay_pagos_perdidos' => $hayPagos,
                    'total_pagos_perdidos' => $totalPagado
                ];
            } else {
                if ($manejarTransaccion) {
                    $this->repository->rollBack();
                }
                return ['success' => false, 'error' => 'Error al regenerar las cuotas'];
            }
        } catch (Exception $e) {
            if ($manejarTransaccion) {
                $this->repository->rollBack();
            }
            error_log("Error regenerando cuotas: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno: ' . $e->getMessage()];
        }
    }

    public function obtenerInformacionFechasVenta($idVenta)
    {
        try {
            $datosVenta = $this->repository->obtenerDatosVentaCredito($idVenta);

            if (!$datosVenta) {
                throw new Exception("Venta no encontrada");
            }

            return [
                'fecha_venta' => $datosVenta['fecha_venta'],
                'fecha_inicio_credito' => $datosVenta['fecha_inicio_credito'],
                'fecha_calculo_efectiva' => $datosVenta['fecha_calculo_efectiva'],
                'tiene_fecha_personalizada' => $datosVenta['tiene_fecha_personalizada'],
                'tipo_credito' => $datosVenta['tipocredito']
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo información de fechas: " . $e->getMessage());
            return null;
        }
    }

    public function obtenerCuentasCobrar($filtros = [], $pagina = 1, $registrosPorPagina = 15)
    {
        $offset = ($pagina - 1) * $registrosPorPagina;

        $ventas = $this->repository->obtenerCuentasCobrar($filtros, $registrosPorPagina, $offset);
        $total = $this->repository->contarCuentasCobrar($filtros);

        return [
            'cuentas' => array_map([$this, 'enriquecerDatosVenta'], $ventas),
            'total' => $total,
            'total_paginas' => ceil($total / $registrosPorPagina),
            'pagina_actual' => $pagina
        ];
    }

    public function obtenerDetalleCuota($idCuota)
    {
        $cuota = $this->repository->obtenerCuotaPorId($idCuota);

        if (!$cuota) {
            throw new Exception('Cuota no encontrada');
        }

        $pagos = $this->repository->obtenerPagosCuota($idCuota);
        $cuotasVenta = $this->repository->obtenerCuotasPorVenta($cuota['id_venta']);

        return [
            'cuota' => $this->enriquecerDatosCuenta($cuota),
            'pagos' => array_map([$this, 'enriquecerDatosPago'], $pagos),
            'otras_cuotas' => array_map([$this, 'enriquecerDatosCuenta'], $cuotasVenta),
            'info_fechas' => $this->obtenerInformacionFechasVenta($cuota['id_venta'])
        ];
    }

    public function registrarPago($idCuota, $datos, $idUsuario)
    {
        $errores = $this->validarDatosPago($datos);
        if (!empty($errores)) {
            return ['success' => false, 'errores' => $errores];
        }

        try {
            $this->repository->beginTransaction();

            $comprobante = null;
            if (isset($datos['comprobante']) && !empty($datos['comprobante']['tmp_name'])) {
                $comprobante = $this->procesarComprobante($datos['comprobante']);
                if (!$comprobante) {
                    throw new Exception("Error al procesar el comprobante");
                }
            }

            $completarCuota = isset($datos['completar_cuota']) && $datos['completar_cuota'] === true;
            $redistribuir = true;

            $resultado = $this->repository->registrarPagoCuota(
                $idCuota,
                $datos['monto_pago'],
                $datos['fecha_pago'],
                $idUsuario,
                $datos['forma_pago'] ?? '',
                $datos['referencia_pago'] ?? '',
                $datos['observaciones'] ?? '',
                $comprobante,
                $redistribuir,
                $completarCuota
            );

            if ($resultado) {
                $this->repository->commit();

                $mensaje = $completarCuota
                    ? "Cuota completada y saldo redistribuido correctamente"
                    : "Pago de " . $this->formatearMonto($datos['monto_pago']) . " registrado correctamente";

                return ['success' => true, 'mensaje' => $mensaje];
            } else {
                $this->repository->rollBack();
                return ['success' => false, 'error' => 'Error al registrar el pago'];
            }
        } catch (Exception $e) {
            $this->repository->rollBack();
            error_log("Error registrando pago: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno: ' . $e->getMessage()];
        }
    }

    public function actualizarMontoCuota($idCuota, $nuevoMonto, $idUsuario)
    {
        if ($nuevoMonto <= 0) {
            return ['success' => false, 'error' => 'El monto debe ser mayor a 0'];
        }

        try {
            $resultado = $this->repository->actualizarMontoCuota($idCuota, $nuevoMonto);

            if ($resultado) {
                return ['success' => true, 'mensaje' => 'Monto actualizado correctamente'];
            } else {
                return ['success' => false, 'error' => 'Error al actualizar el monto'];
            }
        } catch (Exception $e) {
            error_log("Error actualizando monto: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno: ' . $e->getMessage()];
        }
    }

    public function obtenerEstadisticas()
    {
        return $this->repository->obtenerEstadisticas();
    }

    private function enriquecerDatosCuenta($cuenta)
    {
        $cuenta['simbolo_moneda'] = $this->obtenerSimboloMoneda($cuenta['moneda']);
        $cuenta['monto_cuota_formateado'] = $this->formatearMonto($cuenta['monto_cuota']);
        $cuenta['monto_pagado_formateado'] = $this->formatearMonto($cuenta['monto_pagado']);
        $cuenta['monto_pendiente_formateado'] = $this->formatearMonto($cuenta['monto_pendiente']);

        if (!empty($cuenta['fecha_vencimiento'])) {
            $cuenta['fecha_vencimiento_formateada'] = date('d/m/Y', strtotime($cuenta['fecha_vencimiento']));
        }

        if (!empty($cuenta['fecha_ultimo_pago'])) {
            $cuenta['fecha_ultimo_pago_formateada'] = date('d/m/Y H:i', strtotime($cuenta['fecha_ultimo_pago']));
        }

        if (!empty($cuenta['fecha_venta'])) {
            $cuenta['fecha_venta_formateada'] = date('d/m/Y', strtotime($cuenta['fecha_venta']));
        }

        if (!empty($cuenta['fecha_inicio_credito'])) {
            $cuenta['fecha_inicio_credito_formateada'] = date('d/m/Y', strtotime($cuenta['fecha_inicio_credito']));
        }

        if (!empty($cuenta['fecha_calculo_efectiva'])) {
            $cuenta['fecha_calculo_efectiva_formateada'] = date('d/m/Y', strtotime($cuenta['fecha_calculo_efectiva']));
        }

        $cuenta['estado_class'] = $this->obtenerClaseEstado($cuenta['estado_actual'] ?? $cuenta['estado']);

        if (isset($cuenta['dias_vencimiento'])) {
            if ($cuenta['dias_vencimiento'] < 0) {
                $cuenta['dias_texto'] = abs($cuenta['dias_vencimiento']) . ' días vencida';
                $cuenta['dias_class'] = 'text-danger';
            } elseif ($cuenta['dias_vencimiento'] == 0) {
                $cuenta['dias_texto'] = 'Vence hoy';
                $cuenta['dias_class'] = 'text-warning';
            } else {
                $cuenta['dias_texto'] = $cuenta['dias_vencimiento'] . ' días';
                $cuenta['dias_class'] = 'text-success';
            }
        }

        return $cuenta;
    }

    private function enriquecerDatosPago($pago)
    {
        $pago['monto_pago_formateado'] = $this->formatearMonto($pago['monto_pago']);

        if (!empty($pago['fecha_pago'])) {
            $pago['fecha_pago_formateada'] = date('d/m/Y', strtotime($pago['fecha_pago']));
        }

        if (!empty($pago['fecha_registro'])) {
            $pago['fecha_registro_formateada'] = date('d/m/Y H:i', strtotime($pago['fecha_registro']));
        }

        return $pago;
    }

    private function enriquecerDatosVenta($venta)
    {
        $venta['simbolo_moneda'] = $this->obtenerSimboloMoneda($venta['moneda']);
        $venta['monto_total_cuotas_formateado'] = $this->formatearMonto($venta['monto_total_cuotas']);
        $venta['monto_total_pagado_formateado'] = $this->formatearMonto($venta['monto_total_pagado']);
        $venta['monto_total_pendiente_formateado'] = $this->formatearMonto($venta['monto_total_pendiente']);

        if (!empty($venta['proximo_vencimiento'])) {
            $venta['proximo_vencimiento_formateado'] = date('d/m/Y', strtotime($venta['proximo_vencimiento']));
        }

        if (!empty($venta['fecha_venta'])) {
            $venta['fecha_venta_formateada'] = date('d/m/Y', strtotime($venta['fecha_venta']));
        }

        if (!empty($venta['fecha_inicio_credito'])) {
            $venta['fecha_inicio_credito_formateada'] = date('d/m/Y', strtotime($venta['fecha_inicio_credito']));
        }

        if (!empty($venta['fecha_calculo_efectiva'])) {
            $venta['fecha_calculo_efectiva_formateada'] = date('d/m/Y', strtotime($venta['fecha_calculo_efectiva']));
        }

        $venta['estado_class'] = $this->obtenerClaseEstado($venta['estado_venta']);

        if (isset($venta['dias_vencimiento'])) {
            if ($venta['dias_vencimiento'] < 0) {
                $venta['dias_texto'] = abs($venta['dias_vencimiento']) . ' días vencida';
                $venta['dias_class'] = 'text-danger';
            } elseif ($venta['dias_vencimiento'] == 0) {
                $venta['dias_texto'] = 'Vence hoy';
                $venta['dias_class'] = 'text-warning';
            } else {
                $venta['dias_texto'] = $venta['dias_vencimiento'] . ' días';
                $venta['dias_class'] = 'text-success';
            }
        }

        return $venta;
    }

    private function validarDatosPago($datos)
    {
        $errores = [];

        if (empty($datos['monto_pago']) || $datos['monto_pago'] <= 0) {
            $errores[] = 'El monto del pago es obligatorio y debe ser mayor a 0';
        }

        if (empty($datos['fecha_pago'])) {
            $errores[] = 'La fecha de pago es obligatoria';
        } elseif (!strtotime($datos['fecha_pago'])) {
            $errores[] = 'La fecha de pago no es válida';
        }

        if (!empty($datos['fecha_pago']) && strtotime($datos['fecha_pago']) > time()) {
            $errores[] = 'La fecha de pago no puede ser futura';
        }

        return $errores;
    }

    private function procesarComprobante($archivo)
    {
        try {
            $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            $tamanoMaximo = 5 * 1024 * 1024;

            if ($archivo['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Error al subir el archivo");
            }

            if ($archivo['size'] > $tamanoMaximo) {
                throw new Exception("El archivo es demasiado grande (máximo 5MB)");
            }

            $tipoArchivo = $archivo['type'];
            if (!in_array($tipoArchivo, $tiposPermitidos)) {
                throw new Exception("Tipo de archivo no permitido");
            }

            $contenido = file_get_contents($archivo['tmp_name']);
            $base64 = base64_encode($contenido);

            return [
                'nombre' => $archivo['name'],
                'tipo' => $tipoArchivo,
                'base64' => $base64
            ];
        } catch (Exception $e) {
            error_log("Error procesando comprobante: " . $e->getMessage());
            return null;
        }
    }

    private function obtenerSimboloMoneda($moneda)
    {
        switch ($moneda) {
            case 'Dólares':
                return 'U$D ';
            case 'Real brasileño':
                return 'R$ ';
            default:
                return '₲ ';
        }
    }

    private function formatearMonto($monto)
    {
        return number_format((float)$monto, 2, ',', '.');
    }

    private function obtenerClaseEstado($estado)
    {
        switch ($estado) {
            case 'PAGADO':
                return 'bg-success';
            case 'PENDIENTE':
                return 'bg-warning text-dark';
            case 'PARCIAL':
                return 'bg-info';
            case 'VENCIDA':
                return 'bg-danger';
            default:
                return 'bg-secondary';
        }
    }

    public function obtenerCuentasPagadas($filtros = [], $pagina = 1, $registrosPorPagina = 15)
    {
        $offset = ($pagina - 1) * $registrosPorPagina;

        $ventas = $this->repository->obtenerCuentasPagadas($filtros, $registrosPorPagina, $offset);
        $total = $this->repository->contarCuentasPagadas($filtros);

        return [
            'cuentas' => array_map([$this, 'enriquecerDatosVentaPagada'], $ventas),
            'total' => $total,
            'total_paginas' => ceil($total / $registrosPorPagina),
            'pagina_actual' => $pagina
        ];
    }

    private function enriquecerDatosVentaPagada($venta)
    {
        $venta = $this->enriquecerDatosVenta($venta);

        if (!empty($venta['fecha_ultimo_pago'])) {
            $venta['fecha_ultimo_pago_formateada'] = date('d/m/Y', strtotime($venta['fecha_ultimo_pago']));
        }

        if (isset($venta['dias_desde_pago'])) {
            if ($venta['dias_desde_pago'] == 0) {
                $venta['dias_pago_texto'] = 'Hoy';
                $venta['dias_pago_class'] = 'text-success';
            } elseif ($venta['dias_desde_pago'] == 1) {
                $venta['dias_pago_texto'] = 'Ayer';
                $venta['dias_pago_class'] = 'text-info';
            } else {
                $venta['dias_pago_texto'] = 'Hace ' . $venta['dias_desde_pago'] . ' días';
                $venta['dias_pago_class'] = $venta['dias_desde_pago'] <= 7 ? 'text-primary' : 'text-muted';
            }
        }

        return $venta;
    }

    public function validarPermisos($rol)
    {
        return in_array($rol, ['1', '3']);
    }
}
