<?php

class ExpedicionesDespachadasService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function obtenerDatosVista($fechaInicio, $fechaFin, $transportista, $codigoExpedicion, $pagina, $porPagina)
    {
        try {
            $fechaInicio = $this->validarFecha($fechaInicio, null);
            $fechaFin = $this->validarFecha($fechaFin, null);
            $transportista = trim($transportista);
            $codigoExpedicion = trim($codigoExpedicion);
            $pagina = max(1, (int)$pagina);
            $porPagina = max(5, min(100, (int)$porPagina));

            $offset = ($pagina - 1) * $porPagina;

            $expediciones = $this->repository->obtenerExpedicionesDespachadas(
                $fechaInicio,
                $fechaFin,
                $transportista,
                $codigoExpedicion,
                $offset,
                $porPagina
            );

            $expedicionesFormateadas = $this->formatearExpediciones($expediciones);

            $total = $this->repository->contarExpedicionesDespachadas($fechaInicio, $fechaFin, $transportista, $codigoExpedicion);

            $estadisticas = $this->repository->obtenerEstadisticasGenerales($fechaInicio, $fechaFin, $transportista, $codigoExpedicion);

            $transportistas = $this->repository->obtenerTransportistasConDespachos();

            return [
                'success' => true,
                'expediciones' => $expedicionesFormateadas,
                'total' => $total,
                'estadisticas' => $estadisticas,
                'transportistas' => $transportistas,
                'filtros' => [
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin,
                    'transportista' => $transportista,
                    'codigo_expedicion' => $codigoExpedicion,
                    'pagina' => $pagina,
                    'por_pagina' => $porPagina
                ],
                'paginacion' => [
                    'pagina_actual' => $pagina,
                    'total_paginas' => ceil($total / $porPagina),
                    'total_registros' => $total,
                    'registros_por_pagina' => $porPagina,
                    'desde' => ($offset + 1),
                    'hasta' => min($offset + $porPagina, $total)
                ]
            ];
        } catch (Exception $e) {
            error_log("Error en obtenerDatosVista: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'expediciones' => [],
                'total' => 0,
                'estadisticas' => $this->getEstadisticasVacias(),
                'transportistas' => []
            ];
        }
    }

    private function formatearExpediciones($expediciones)
    {
        return array_map(function ($expedicion) {
            $expedicion['peso_total_bruto_formateado'] = number_format(floatval($expedicion['peso_total_bruto'] ?? 0), 2);
            $expedicion['peso_total_liquido_formateado'] = number_format(floatval($expedicion['peso_total_liquido'] ?? 0), 2);
            $expedicion['tara_total_formateada'] = number_format(floatval($expedicion['tara_total'] ?? 0), 2);
            $expedicion['metragem_total_formateada'] = number_format(intval($expedicion['metragem_total'] ?? 0));
            $expedicion['total_bobinas_formateado'] = number_format(intval($expedicion['total_bobinas'] ?? 0));

            if (isset($expedicion['largura_promedio']) && $expedicion['largura_promedio'] > 0) {
                $expedicion['largura_promedio_formateada'] = number_format($expedicion['largura_promedio'], 2) . ' mm';
            }

            if (isset($expedicion['gramatura_promedio']) && $expedicion['gramatura_promedio'] > 0) {
                $expedicion['gramatura_promedio_formateada'] = number_format($expedicion['gramatura_promedio'], 2) . ' g/m²';
            }

            $horasTranscurridas = floatval($expedicion['horas_transcurridas'] ?? 0);
            if ($horasTranscurridas > 0) {
                $expedicion['tiempo_despacho'] = $this->formatearTiempo($horasTranscurridas);
            }

            return $expedicion;
        }, $expediciones);
    }

    public function obtenerItemsExpedicion($numeroExpedicion)
    {
        try {
            $expedicion = $this->repository->verificarExpedicionDespachada($numeroExpedicion);
            if (!$expedicion) {
                return [
                    'success' => false,
                    'error' => 'Expedición no encontrada o no está despachada'
                ];
            }

            $itemsResumen = $this->repository->obtenerResumenItemsExpedicion($numeroExpedicion);
            $itemsDetalle = $this->repository->obtenerItemsExpedicion($numeroExpedicion);
            $itemsFormateados = $this->formatearItems($itemsResumen);
            $estadisticas = $this->calcularEstadisticasBasicas($itemsDetalle);
            return [
                'success' => true,
                'expedicion' => $expedicion,
                'items' => $itemsFormateados,
                'items_detalle' => $itemsDetalle,
                'estadisticas' => $estadisticas
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo items de expedición: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function formatearItems($items)
    {
        return array_map(function ($item) {
            $item['peso_bruto_formateado'] = number_format(floatval($item['peso_total_bruto'] ?? 0), 2) . ' kg';
            $item['peso_liquido_formateado'] = number_format(floatval($item['peso_total_liquido'] ?? 0), 2) . ' kg';
            $item['tara_formateada'] = number_format(floatval($item['tara_total'] ?? 0), 2) . ' kg';
            $item['metragem_formateada'] = number_format(intval($item['metragem'] ?? 0)) . ' m';
            $item['bobinas_formateadas'] = number_format(intval($item['total_bobinas'] ?? 0));

            if (isset($item['largura_promedio']) && $item['largura_promedio'] > 0) {
                $item['largura_formateada'] = number_format($item['largura_promedio'], 2) . ' mm';
            }

            if (isset($item['gramatura_promedio']) && $item['gramatura_promedio'] > 0) {
                $item['gramatura_formateada'] = number_format($item['gramatura_promedio'], 2) . ' g/m²';
            }

            return $item;
        }, $items);
    }

    private function validarFecha($fecha, $porDefecto)
    {
        if (empty($fecha)) {
            return null;
        }

        if (!strtotime($fecha)) {
            return null;
        }

        return date('Y-m-d', strtotime($fecha));
    }

    private function formatearTiempo($horas)
    {
        $horas = floatval($horas);

        if ($horas < 1) {
            return intval(round($horas * 60)) . ' minutos';
        } elseif ($horas < 24) {
            return round($horas, 1) . ' horas';
        } else {
            $dias = intval(floor($horas / 24));
            $horasRestantes = round(fmod($horas, 24), 1);
            return $dias . ' día' . ($dias != 1 ? 's' : '') .
                ($horasRestantes > 0 ? ' y ' . $horasRestantes . ' horas' : '');
        }
    }

    private function calcularEstadisticasBasicas($items)
    {
        $totalItems = count($items);
        $pesoTotalBruto = floatval(array_sum(array_column($items, 'peso_bruto')));
        $pesoTotalLiquido = floatval(array_sum(array_column($items, 'peso_liquido')));
        $taraTotal = floatval(array_sum(array_column($items, 'tara')));
        $clientesUnicos = count(array_unique(array_column($items, 'cliente')));
        $productosUnicos = count(array_unique(array_column($items, 'nombre_producto')));
        $tiposProductoUnicos = count(array_unique(array_column($items, 'tipo_producto')));
        $metragemTotal = intval(array_sum(array_column($items, 'metragem')));
        $bobinasTotal = intval(array_sum(array_column($items, 'bobinas_pacote')));

        return [
            'total_items' => $totalItems,
            'peso_total_bruto' => $pesoTotalBruto,
            'peso_total_liquido' => $pesoTotalLiquido,
            'tara_total' => $taraTotal,
            'peso_total_bruto_formateado' => number_format($pesoTotalBruto, 2) . ' kg',
            'peso_total_liquido_formateado' => number_format($pesoTotalLiquido, 2) . ' kg',
            'tara_total_formateada' => number_format($taraTotal, 2) . ' kg',
            'clientes_unicos' => $clientesUnicos,
            'productos_unicos' => $productosUnicos,
            'tipos_producto_unicos' => $tiposProductoUnicos,
            'metragem_total' => $metragemTotal,
            'metragem_total_formateada' => number_format($metragemTotal) . ' m',
            'bobinas_total' => $bobinasTotal,
            'peso_promedio' => $totalItems > 0 ? round($pesoTotalBruto / $totalItems, 2) : 0
        ];
    }

    private function getEstadisticasVacias()
    {
        return [
            'total_expediciones' => 0,
            'total_items' => 0,
            'peso_total_bruto' => 0,
            'peso_total_liquido' => 0,
            'peso_total_bruto_formateado' => '0.00 kg',
            'peso_total_liquido_formateado' => '0.00 kg',
            'clientes_unicos' => 0,
            'productos_unicos' => 0,
            'tipos_producto_unicos' => 0,
            'transportistas_unicos' => 0
        ];
    }
}
