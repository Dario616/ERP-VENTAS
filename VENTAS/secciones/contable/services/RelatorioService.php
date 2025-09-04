<?php

class RelatorioService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function obtenerReportePagos($filtros = [])
    {
        try {
            $datosAgrupados = $this->repository->obtenerReportePagosAgrupado($filtros);

            $clientesOrganizados = $this->organizarDatosPorCliente($datosAgrupados);

            $clientesEnriquecidos = array_map([$this, 'enriquecerDatosCliente'], $clientesOrganizados);

            return [
                'clientes' => $clientesEnriquecidos
            ];
        } catch (Exception $e) {
            error_log("Error generando reporte de pagos: " . $e->getMessage());
            throw $e;
        }
    }

    private function organizarDatosPorCliente($datosAgrupados)
    {
        $clientes = [];

        foreach ($datosAgrupados as $fila) {
            $cliente = $fila['cliente'];
            $moneda = $fila['moneda'];

            if (!isset($clientes[$cliente])) {
                $clientes[$cliente] = [
                    'nombre' => $cliente,
                    'vendedor' => $fila['vendedor'],
                    'monedas' => [],
                    'totales_cliente' => [
                        'total_pagos' => 0,
                        'total_ventas' => 0,
                        'primera_fecha' => null,
                        'ultima_fecha' => null
                    ]
                ];
            }

            if (!isset($clientes[$cliente]['monedas'][$moneda])) {
                $clientes[$cliente]['monedas'][$moneda] = [
                    'moneda' => $moneda,
                    'total_pagos' => 0,
                    'total_ventas' => 0,
                    'total_monto' => 0,
                    'total_ventas_monto' => 0,
                    'total_deuda' => 0,
                    'promedio_pago' => 0,
                    'mayor_pago' => 0,
                    'menor_pago' => 0,
                    'primera_fecha' => null,
                    'ultima_fecha' => null,
                    'formas_pago' => [],
                    'detalle_mensual' => []
                ];
            }

            $clientes[$cliente]['monedas'][$moneda]['total_pagos'] += $fila['total_pagos'];
            $clientes[$cliente]['monedas'][$moneda]['total_ventas'] += $fila['total_ventas'];
            $clientes[$cliente]['monedas'][$moneda]['total_monto'] += $fila['total_monto_pagado'];
            $clientes[$cliente]['monedas'][$moneda]['total_ventas_monto'] += $fila['total_monto_ventas'];
            $clientes[$cliente]['monedas'][$moneda]['total_deuda'] = $clientes[$cliente]['monedas'][$moneda]['total_ventas_monto'] - $clientes[$cliente]['monedas'][$moneda]['total_monto'];

            if ($clientes[$cliente]['monedas'][$moneda]['mayor_pago'] < $fila['mayor_pago']) {
                $clientes[$cliente]['monedas'][$moneda]['mayor_pago'] = $fila['mayor_pago'];
            }

            if ($clientes[$cliente]['monedas'][$moneda]['menor_pago'] == 0 || $clientes[$cliente]['monedas'][$moneda]['menor_pago'] > $fila['menor_pago']) {
                $clientes[$cliente]['monedas'][$moneda]['menor_pago'] = $fila['menor_pago'];
            }

            if (!$clientes[$cliente]['monedas'][$moneda]['primera_fecha'] || $fila['primera_fecha_pago'] < $clientes[$cliente]['monedas'][$moneda]['primera_fecha']) {
                $clientes[$cliente]['monedas'][$moneda]['primera_fecha'] = $fila['primera_fecha_pago'];
            }

            if (!$clientes[$cliente]['monedas'][$moneda]['ultima_fecha'] || $fila['ultima_fecha_pago'] > $clientes[$cliente]['monedas'][$moneda]['ultima_fecha']) {
                $clientes[$cliente]['monedas'][$moneda]['ultima_fecha'] = $fila['ultima_fecha_pago'];
            }

            if ($fila['formas_pago_utilizadas']) {
                $formas = explode(', ', $fila['formas_pago_utilizadas']);
                $clientes[$cliente]['monedas'][$moneda]['formas_pago'] = array_unique(array_merge(
                    $clientes[$cliente]['monedas'][$moneda]['formas_pago'],
                    $formas
                ));
            }

            $claveMes = $fila['año_pago'] . '-' . str_pad($fila['mes_pago'], 2, '0', STR_PAD_LEFT);
            $clientes[$cliente]['monedas'][$moneda]['detalle_mensual'][$claveMes] = [
                'año' => $fila['año_pago'],
                'mes' => $fila['mes_pago'],
                'total_pagos' => $fila['total_pagos'],
                'total_monto' => $fila['total_monto_pagado'],
                'promedio' => $fila['promedio_pago']
            ];

            $clientes[$cliente]['totales_cliente']['total_pagos'] += $fila['total_pagos'];
            $clientes[$cliente]['totales_cliente']['total_ventas'] += $fila['total_ventas'];

            if (!$clientes[$cliente]['totales_cliente']['primera_fecha'] || $fila['primera_fecha_pago'] < $clientes[$cliente]['totales_cliente']['primera_fecha']) {
                $clientes[$cliente]['totales_cliente']['primera_fecha'] = $fila['primera_fecha_pago'];
            }

            if (!$clientes[$cliente]['totales_cliente']['ultima_fecha'] || $fila['ultima_fecha_pago'] > $clientes[$cliente]['totales_cliente']['ultima_fecha']) {
                $clientes[$cliente]['totales_cliente']['ultima_fecha'] = $fila['ultima_fecha_pago'];
            }
        }

        foreach ($clientes as &$cliente) {
            foreach ($cliente['monedas'] as &$moneda) {
                if ($moneda['total_pagos'] > 0) {
                    $moneda['promedio_pago'] = $moneda['total_monto'] / $moneda['total_pagos'];
                }
            }
        }

        return array_values($clientes);
    }

    private function enriquecerDatosCliente($cliente)
    {
        if ($cliente['totales_cliente']['primera_fecha']) {
            $cliente['totales_cliente']['primera_fecha_formateada'] = date('d/m/Y', strtotime($cliente['totales_cliente']['primera_fecha']));
        }

        if ($cliente['totales_cliente']['ultima_fecha']) {
            $cliente['totales_cliente']['ultima_fecha_formateada'] = date('d/m/Y', strtotime($cliente['totales_cliente']['ultima_fecha']));
        }

        foreach ($cliente['monedas'] as $codigoMoneda => &$moneda) {
            $moneda['simbolo_moneda'] = $this->obtenerSimboloMoneda($codigoMoneda);
            $moneda['total_monto_formateado'] = $this->formatearMonto($moneda['total_monto']);
            $moneda['promedio_pago_formateado'] = $this->formatearMonto($moneda['promedio_pago']);
            $moneda['mayor_pago_formateado'] = $this->formatearMonto($moneda['mayor_pago']);
            $moneda['menor_pago_formateado'] = $this->formatearMonto($moneda['menor_pago']);
            $moneda['total_ventas_monto_formateado'] = $this->formatearMonto($moneda['total_ventas_monto']);
            $moneda['total_deuda_formateado'] = $this->formatearMonto($moneda['total_deuda']);

            if ($moneda['primera_fecha']) {
                $moneda['primera_fecha_formateada'] = date('d/m/Y', strtotime($moneda['primera_fecha']));
            }

            if ($moneda['ultima_fecha']) {
                $moneda['ultima_fecha_formateada'] = date('d/m/Y', strtotime($moneda['ultima_fecha']));
            }

            foreach ($moneda['detalle_mensual'] as &$mes) {
                $mes['total_monto_formateado'] = $this->formatearMonto($mes['total_monto']);
                $mes['promedio_formateado'] = $this->formatearMonto($mes['promedio']);
                $mes['nombre_mes'] = $this->obtenerNombreMes($mes['mes']);
            }

            uksort($moneda['detalle_mensual'], function ($a, $b) {
                return strcmp($b, $a);
            });
        }

        return $cliente;
    }

    public function obtenerEstadisticasGenerales($filtros = [])
    {
        try {
            $stats = $this->repository->obtenerEstadisticasGenerales($filtros);
            return [
                'generales' => $this->enriquecerEstadisticasGenerales($stats)
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas generales: " . $e->getMessage());
            throw $e;
        }
    }

    private function enriquecerEstadisticasGenerales($stats)
    {
        $stats['monto_total_general_formateado'] = $this->formatearMonto($stats['monto_total_general']);
        $stats['promedio_general_formateado'] = $this->formatearMonto($stats['promedio_general']);
        $stats['mayor_pago_general_formateado'] = $this->formatearMonto($stats['mayor_pago_general']);

        if ($stats['fecha_primer_pago']) {
            $stats['fecha_primer_pago_formateada'] = date('d/m/Y', strtotime($stats['fecha_primer_pago']));
        }

        if ($stats['fecha_ultimo_pago']) {
            $stats['fecha_ultimo_pago_formateada'] = date('d/m/Y', strtotime($stats['fecha_ultimo_pago']));
        }

        return $stats;
    }

    public function validarPermisos($rol)
    {
        return in_array($rol, ['1', '3']);
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

    private function obtenerNombreMes($numeroMes)
    {
        $meses = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        ];
        return $meses[(int)$numeroMes] ?? 'Mes';
    }

    public function obtenerDatosDetalladosCliente($cliente, $filtros = [])
    {
        try {
            $infoGeneral = $this->repository->obtenerInfoGeneralCliente($cliente, $filtros);

            $ventas = $this->repository->obtenerVentasCliente($cliente, $filtros);

            $cuotasYPagos = $this->repository->obtenerCuotasYPagosCliente($cliente, $filtros);

            $estadisticas = $this->repository->obtenerEstadisticasCliente($cliente, $filtros);

            $historialPagos = $this->repository->obtenerHistorialPagosDetallado($cliente, $filtros);

            return [
                'info_general' => $this->enriquecerInfoGeneral($infoGeneral),
                'ventas' => $this->enriquecerVentas($ventas),
                'cuotas_y_pagos' => $this->enriquecerCuotasYPagos($cuotasYPagos),
                'estadisticas' => $this->enriquecerEstadisticasCliente($estadisticas),
                'historial_pagos' => $this->enriquecerHistorialPagos($historialPagos),
                'resumen_por_moneda' => $this->generarResumenPorMoneda($estadisticas)
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos detallados del cliente: " . $e->getMessage());
            throw $e;
        }
    }

    private function enriquecerInfoGeneral($info)
    {
        if (empty($info)) return [];

        $info['total_ventas_formateado'] = $this->formatearMonto($info['total_ventas']);
        $info['total_pagado_formateado'] = $this->formatearMonto($info['total_pagado']);
        $info['total_pendiente_formateado'] = $this->formatearMonto($info['total_pendiente']);
        $info['promedio_venta_formateado'] = $this->formatearMonto($info['promedio_venta']);

        if ($info['primera_venta']) {
            $info['primera_venta_formateada'] = date('d/m/Y', strtotime($info['primera_venta']));
        }

        if ($info['ultima_venta']) {
            $info['ultima_venta_formateada'] = date('d/m/Y', strtotime($info['ultima_venta']));
        }

        if ($info['ultimo_pago']) {
            $info['ultimo_pago_formateado'] = date('d/m/Y', strtotime($info['ultimo_pago']));
        }

        return $info;
    }

    private function enriquecerVentas($ventas)
    {
        foreach ($ventas as &$venta) {
            $venta['monto_total_formateado'] = $this->formatearMonto($venta['monto_total']);
            $venta['subtotal_formateado'] = $this->formatearMonto($venta['subtotal']);
            $venta['descuento_formateado'] = $this->formatearMonto($venta['descuento']);
            $venta['monto_pagado_formateado'] = $this->formatearMonto($venta['monto_pagado']);
            $venta['monto_pendiente_formateado'] = $this->formatearMonto($venta['monto_pendiente']);

            if ($venta['fecha_venta']) {
                $venta['fecha_venta_formateada'] = date('d/m/Y', strtotime($venta['fecha_venta']));
            }

            if ($venta['fecha_inicio_credito']) {
                $venta['fecha_inicio_credito_formateada'] = date('d/m/Y', strtotime($venta['fecha_inicio_credito']));
            }

            if ($venta['monto_total'] > 0) {
                $venta['porcentaje_pagado'] = round(($venta['monto_pagado'] / $venta['monto_total']) * 100, 2);
            } else {
                $venta['porcentaje_pagado'] = 0;
            }

            if ($venta['porcentaje_pagado'] >= 100) {
                $venta['estado_visual'] = 'pagado';
                $venta['clase_css'] = 'success';
            } elseif ($venta['porcentaje_pagado'] > 0) {
                $venta['estado_visual'] = 'parcial';
                $venta['clase_css'] = 'warning';
            } else {
                $venta['estado_visual'] = 'pendiente';
                $venta['clase_css'] = 'danger';
            }
        }

        return $ventas;
    }

    private function enriquecerCuotasYPagos($cuotasYPagos)
    {
        foreach ($cuotasYPagos as &$cuota) {
            $cuota['monto_cuota_formateado'] = $this->formatearMonto($cuota['monto_cuota']);
            $cuota['monto_pagado_formateado'] = $this->formatearMonto($cuota['monto_pagado']);
            $cuota['monto_pendiente_formateado'] = $this->formatearMonto($cuota['monto_pendiente']);

            if ($cuota['fecha_vencimiento']) {
                $cuota['fecha_vencimiento_formateada'] = date('d/m/Y', strtotime($cuota['fecha_vencimiento']));

                $hoy = new DateTime();
                $vencimiento = new DateTime($cuota['fecha_vencimiento']);
                $diferencia = $hoy->diff($vencimiento);

                if ($hoy > $vencimiento && $cuota['estado'] != 'PAGADO') {
                    $cuota['dias_vencido'] = $diferencia->days;
                    $cuota['estado_vencimiento'] = 'vencido';
                } elseif ($cuota['estado'] != 'PAGADO') {
                    $cuota['dias_para_vencimiento'] = $diferencia->days;
                    $cuota['estado_vencimiento'] = 'pendiente';
                } else {
                    $cuota['estado_vencimiento'] = 'pagado';
                }
            }

            if (isset($cuota['pagos']) && is_array($cuota['pagos'])) {
                foreach ($cuota['pagos'] as &$pago) {
                    $pago['monto_pago_formateado'] = $this->formatearMonto($pago['monto_pago']);

                    if ($pago['fecha_pago']) {
                        $pago['fecha_pago_formateada'] = date('d/m/Y', strtotime($pago['fecha_pago']));
                    }

                    if ($pago['fecha_registro']) {
                        $pago['fecha_registro_formateada'] = date('d/m/Y H:i', strtotime($pago['fecha_registro']));
                    }

                    $pago['tiene_comprobante'] = !empty($pago['comprobante_nombre']);
                }
            }
        }

        return $cuotasYPagos;
    }

    private function enriquecerEstadisticasCliente($estadisticas)
    {
        foreach ($estadisticas as &$stat) {
            $stat['total_vendido_formateado'] = $this->formatearMonto($stat['total_vendido']);
            $stat['total_pagado_formateado'] = $this->formatearMonto($stat['total_pagado']);
            $stat['total_pendiente_formateado'] = $this->formatearMonto($stat['total_pendiente']);
            $stat['promedio_pago_formateado'] = $this->formatearMonto($stat['promedio_pago']);
            $stat['mayor_pago_formateado'] = $this->formatearMonto($stat['mayor_pago']);

            if ($stat['total_vendido'] > 0) {
                $stat['porcentaje_pagado'] = round(($stat['total_pagado'] / $stat['total_vendido']) * 100, 2);
            } else {
                $stat['porcentaje_pagado'] = 0;
            }

            $stat['simbolo_moneda'] = $this->obtenerSimboloMoneda($stat['moneda']);
        }

        return $estadisticas;
    }

    private function enriquecerHistorialPagos($historial)
    {
        foreach ($historial as &$pago) {
            $pago['monto_pago_formateado'] = $this->formatearMonto($pago['monto_pago']);

            if ($pago['fecha_pago']) {
                $pago['fecha_pago_formateada'] = date('d/m/Y', strtotime($pago['fecha_pago']));
            }

            if ($pago['fecha_registro']) {
                $pago['fecha_registro_formateada'] = date('d/m/Y H:i', strtotime($pago['fecha_registro']));
            }

            $pago['tiene_comprobante'] = !empty($pago['comprobante_nombre']);
            $pago['simbolo_moneda'] = $this->obtenerSimboloMoneda($pago['moneda']);
        }

        return $historial;
    }

    private function generarResumenPorMoneda($estadisticas)
    {
        $resumen = [];

        foreach ($estadisticas as $stat) {
            $moneda = $stat['moneda'];

            if (!isset($resumen[$moneda])) {
                $resumen[$moneda] = [
                    'moneda' => $moneda,
                    'simbolo' => $this->obtenerSimboloMoneda($moneda),
                    'total_vendido' => 0,
                    'total_pagado' => 0,
                    'total_pendiente' => 0,
                    'total_ventas' => 0,
                    'total_pagos' => 0
                ];
            }

            $resumen[$moneda]['total_vendido'] += $stat['total_vendido'];
            $resumen[$moneda]['total_pagado'] += $stat['total_pagado'];
            $resumen[$moneda]['total_pendiente'] += $stat['total_pendiente'];
            $resumen[$moneda]['total_ventas'] += $stat['cantidad_ventas'];
            $resumen[$moneda]['total_pagos'] += $stat['cantidad_pagos'];
        }

        foreach ($resumen as &$item) {
            $item['total_vendido_formateado'] = $this->formatearMonto($item['total_vendido']);
            $item['total_pagado_formateado'] = $this->formatearMonto($item['total_pagado']);
            $item['total_pendiente_formateado'] = $this->formatearMonto($item['total_pendiente']);

            if ($item['total_vendido'] > 0) {
                $item['porcentaje_pagado'] = round(($item['total_pagado'] / $item['total_vendido']) * 100, 2);
            } else {
                $item['porcentaje_pagado'] = 0;
            }
        }

        return array_values($resumen);
    }
}
