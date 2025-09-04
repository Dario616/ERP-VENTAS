<?php

class HistorialService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function enriquecerHistorialAsignaciones($asignaciones)
    {
        foreach ($asignaciones as &$asignacion) {
            $asignacion['fecha_asignacion_formateada'] = $this->formatearFecha($asignacion['fecha_asignacion'] ?? null);
            $asignacion['fecha_venta_formateada'] = $this->formatearFecha($asignacion['fecha_venta'] ?? null);

            $asignacion['peso_asignado_formateado'] = number_format($asignacion['peso_asignado'] ?? 0, 1);
            $asignacion['cantidad_unidades_formateada'] = number_format($asignacion['cant_uni'] ?? 0, 0);
            $asignacion['peso_unitario_formateado'] = number_format($asignacion['peso_unitario'] ?? 0, 2);

            $asignacion['cliente_unificado'] = $asignacion['cliente_asignacion'] ?? $asignacion['cliente_presupuesto'] ?? 'Sin cliente';

            $asignacion = $this->establecerEstadoVisual($asignacion);

            $asignacion = $this->calcularInformacionTemporal($asignacion);

            $asignacion['tipo_unidad_texto'] = $this->obtenerTextoTipoUnidad($asignacion['tipo_unidad'] ?? 'unidades');

            $cantidadUnidades = $asignacion['cant_uni'] ?? 0;
            $despachado = $asignacion['despachado'] ?? 0;

            if ($despachado > 0 && $cantidadUnidades > 0) {
                $porcentajeDespachado = ($despachado / $cantidadUnidades) * 100;
                $asignacion['porcentaje_despachado'] = round($porcentajeDespachado, 1);
                $asignacion['unidades_pendientes'] = max(0, $cantidadUnidades - $despachado);
            } else {
                $asignacion['porcentaje_despachado'] = 0;
                $asignacion['unidades_pendientes'] = $cantidadUnidades;
            }

            $asignacion['producto_unificado'] = $asignacion['nombre_producto'] ?? $asignacion['descripcion_producto'] ?? 'Producto sin nombre';

            $asignacion['tipo_producto_badge'] = $this->obtenerBadgeTipoProducto($asignacion['tipo_producto'] ?? '');

            $asignacion['observaciones_procesadas'] = $this->procesarObservaciones($asignacion['observaciones'] ?? '');

            if ($cantidadUnidades > 0) {
                $asignacion['peso_por_unidad'] = round(($asignacion['peso_asignado'] ?? 0) / $cantidadUnidades, 2);
            } else {
                $asignacion['peso_por_unidad'] = 0;
            }

            $asignacion['cantidad_unidades'] = $cantidadUnidades;
        }

        return $asignaciones;
    }

    private function establecerEstadoVisual($asignacion)
    {
        $estado = $asignacion['estado_asignacion'] ?? 'activa';
        $diasDesdeAsignacion = $asignacion['dias_desde_asignacion'] ?? 0;

        switch ($estado) {
            case 'completada':
                $asignacion['estado_visual'] = 'completado';
                $asignacion['estado_clase'] = 'success';
                $asignacion['estado_icono'] = 'fas fa-check-circle';
                $asignacion['estado_texto'] = 'Completado';
                break;

            case 'cancelada':
                $asignacion['estado_visual'] = 'cancelado';
                $asignacion['estado_clase'] = 'danger';
                $asignacion['estado_icono'] = 'fas fa-times-circle';
                $asignacion['estado_texto'] = 'Cancelado';
                break;

            case 'activa':
            default:
                if ($diasDesdeAsignacion > 30) {
                    $asignacion['estado_visual'] = 'activo_antiguo';
                    $asignacion['estado_clase'] = 'warning';
                    $asignacion['estado_icono'] = 'fas fa-clock';
                    $asignacion['estado_texto'] = 'Activo (Antiguo)';
                } elseif ($diasDesdeAsignacion > 7) {
                    $asignacion['estado_visual'] = 'activo_medio';
                    $asignacion['estado_clase'] = 'info';
                    $asignacion['estado_icono'] = 'fas fa-hourglass-half';
                    $asignacion['estado_texto'] = 'Activo';
                } else {
                    $asignacion['estado_visual'] = 'activo_reciente';
                    $asignacion['estado_clase'] = 'primary';
                    $asignacion['estado_icono'] = 'fas fa-play-circle';
                    $asignacion['estado_texto'] = 'Activo (Reciente)';
                }
                break;
        }

        return $asignacion;
    }

    private function calcularInformacionTemporal($asignacion)
    {
        $diasDesdeAsignacion = $asignacion['dias_desde_asignacion'] ?? 0;

        if ($diasDesdeAsignacion < 1) {
            $asignacion['tiempo_texto'] = 'Hoy';
            $asignacion['tiempo_clase'] = 'success';
        } elseif ($diasDesdeAsignacion < 7) {
            $asignacion['tiempo_texto'] = $diasDesdeAsignacion . ' día' . ($diasDesdeAsignacion > 1 ? 's' : '');
            $asignacion['tiempo_clase'] = 'info';
        } elseif ($diasDesdeAsignacion < 30) {
            $semanas = floor($diasDesdeAsignacion / 7);
            $asignacion['tiempo_texto'] = $semanas . ' semana' . ($semanas > 1 ? 's' : '');
            $asignacion['tiempo_clase'] = 'warning';
        } else {
            $meses = floor($diasDesdeAsignacion / 30);
            $asignacion['tiempo_texto'] = $meses . ' mes' . ($meses > 1 ? 'es' : '');
            $asignacion['tiempo_clase'] = 'danger';
        }

        return $asignacion;
    }

    private function obtenerTextoTipoUnidad($tipoUnidad)
    {
        switch (strtolower($tipoUnidad)) {
            case 'bobinas':
                return 'bobinas';
            case 'cajas':
                return 'cajas';
            case 'unidades':
            default:
                return 'unidades';
        }
    }

    private function obtenerBadgeTipoProducto($tipoProducto)
    {
        $tipo = strtoupper($tipoProducto);

        if (strpos($tipo, 'TNT') !== false) {
            return [
                'texto' => 'TNT',
                'clase' => 'tipo-tnt'
            ];
        } elseif (strpos($tipo, 'SPUNLACE') !== false) {
            return [
                'texto' => 'SPUNLACE',
                'clase' => 'tipo-spunlace'
            ];
        } elseif (strpos($tipo, 'LAMINADORA') !== false) {
            return [
                'texto' => 'LAMINADORA',
                'clase' => 'tipo-laminadora'
            ];
        } elseif (strpos($tipo, 'TOALLITA') !== false || strpos($tipo, 'PAÑO') !== false) {
            return [
                'texto' => 'TOALLITAS',
                'clase' => 'tipo-toallitas'
            ];
        } else {
            return [
                'texto' => 'OTRO',
                'clase' => 'tipo-otro'
            ];
        }
    }

    private function procesarObservaciones($observaciones)
    {
        if (empty($observaciones)) {
            return null;
        }

        $lineas = explode("\n", $observaciones);
        $procesadas = [];

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if (empty($linea)) continue;

            if (strpos($linea, 'COMPLETADO') !== false) {
                $procesadas[] = [
                    'tipo' => 'completado',
                    'texto' => $linea,
                    'icono' => 'fas fa-check-circle',
                    'clase' => 'text-success'
                ];
            } elseif (strpos($linea, 'cancelada') !== false || strpos($linea, 'CANCELADA') !== false) {
                $procesadas[] = [
                    'tipo' => 'cancelacion',
                    'texto' => $linea,
                    'icono' => 'fas fa-times-circle',
                    'clase' => 'text-danger'
                ];
            } elseif (strpos($linea, 'REACTIVADO') !== false) {
                $procesadas[] = [
                    'tipo' => 'reactivacion',
                    'texto' => $linea,
                    'icono' => 'fas fa-undo-alt',
                    'clase' => 'text-info'
                ];
            } elseif (strpos($linea, 'EXCESO') !== false) {
                $procesadas[] = [
                    'tipo' => 'exceso',
                    'texto' => $linea,
                    'icono' => 'fas fa-exclamation-triangle',
                    'clase' => 'text-warning'
                ];
            } else {
                $procesadas[] = [
                    'tipo' => 'general',
                    'texto' => $linea,
                    'icono' => 'fas fa-info-circle',
                    'clase' => 'text-muted'
                ];
            }
        }

        return $procesadas;
    }

    public function enriquecerDetalleAsignacion($detalle)
    {
        if (!$detalle) return null;

        $detalleEnriquecido = $this->enriquecerHistorialAsignaciones([$detalle])[0];

        $detalleEnriquecido['capacidad_rejilla_formateada'] = number_format($detalle['capacidad_maxima'] ?? 0, 1);
        $detalleEnriquecido['peso_actual_rejilla_formateado'] = number_format($detalle['peso_actual'] ?? 0, 1);

        if (isset($detalle['capacidad_maxima']) && $detalle['capacidad_maxima'] > 0) {
            $porcentajeRejilla = ($detalle['peso_asignado'] / $detalle['capacidad_maxima']) * 100;
            $detalleEnriquecido['porcentaje_rejilla'] = round($porcentajeRejilla, 2);
        } else {
            $detalleEnriquecido['porcentaje_rejilla'] = 0;
        }

        $detalleEnriquecido['numero_presupuesto_formateado'] = $detalle['proforma'] ?? 'N/A';
        $detalleEnriquecido['total_presupuesto_formateado'] = number_format($detalle['monto_total'] ?? 0, 0);
        $detalleEnriquecido['total_producto_formateado'] = number_format($detalle['total'] ?? 0, 0);

        return $detalleEnriquecido;
    }

    public function procesarEstadisticasPeriodo($estadisticas)
    {
        $estadisticasProcesadas = [];
        $totales = [
            'total_asignaciones' => 0,
            'total_peso' => 0,
            'total_unidades' => 0,
            'total_rejillas_usadas' => 0,
            'total_clientes_unicos' => 0
        ];

        foreach ($estadisticas as $estadistica) {
            $fecha = $estadistica['fecha'];
            $estadisticaFormateada = [
                'fecha' => $fecha,
                'fecha_formateada' => $this->formatearFecha($fecha),
                'total_asignaciones' => (int)$estadistica['total_asignaciones'],
                'activas' => (int)$estadistica['activas'],
                'completadas' => (int)$estadistica['completadas'],
                'canceladas' => (int)$estadistica['canceladas'],
                'peso_total_dia' => (float)$estadistica['peso_total_dia'],
                'peso_total_dia_formateado' => number_format($estadistica['peso_total_dia'], 1),
                'unidades_total_dia' => (int)$estadistica['unidades_total_dia'],
                'unidades_total_dia_formateadas' => number_format($estadistica['unidades_total_dia'], 0),
                'rejillas_usadas_dia' => (int)$estadistica['rejillas_usadas_dia'],
                'clientes_unicos_dia' => (int)$estadistica['clientes_unicos_dia']
            ];

            if ($estadisticaFormateada['total_asignaciones'] > 0) {
                $estadisticaFormateada['porcentaje_activas'] = round(($estadisticaFormateada['activas'] / $estadisticaFormateada['total_asignaciones']) * 100, 1);
                $estadisticaFormateada['porcentaje_completadas'] = round(($estadisticaFormateada['completadas'] / $estadisticaFormateada['total_asignaciones']) * 100, 1);
                $estadisticaFormateada['porcentaje_canceladas'] = round(($estadisticaFormateada['canceladas'] / $estadisticaFormateada['total_asignaciones']) * 100, 1);
            } else {
                $estadisticaFormateada['porcentaje_activas'] = 0;
                $estadisticaFormateada['porcentaje_completadas'] = 0;
                $estadisticaFormateada['porcentaje_canceladas'] = 0;
            }

            $estadisticasProcesadas[] = $estadisticaFormateada;

            $totales['total_asignaciones'] += $estadisticaFormateada['total_asignaciones'];
            $totales['total_peso'] += $estadisticaFormateada['peso_total_dia'];
            $totales['total_unidades'] += $estadisticaFormateada['unidades_total_dia'];
            $totales['total_rejillas_usadas'] = max($totales['total_rejillas_usadas'], $estadisticaFormateada['rejillas_usadas_dia']);
            $totales['total_clientes_unicos'] = max($totales['total_clientes_unicos'], $estadisticaFormateada['clientes_unicos_dia']);
        }

        return [
            'estadisticas_diarias' => $estadisticasProcesadas,
            'totales_periodo' => $totales,
            'total_dias' => count($estadisticasProcesadas),
            'promedio_asignaciones_dia' => count($estadisticasProcesadas) > 0 ? round($totales['total_asignaciones'] / count($estadisticasProcesadas), 1) : 0,
            'promedio_peso_dia' => count($estadisticasProcesadas) > 0 ? round($totales['total_peso'] / count($estadisticasProcesadas), 1) : 0
        ];
    }

    public function prepararDatosParaExportacion($historialAsignaciones)
    {
        $datosExportacion = [];

        foreach ($historialAsignaciones as $asignacion) {
            $datosExportacion[] = [
                'ID' => $asignacion['id'] ?? '',
                'Fecha Asignación' => $this->formatearFecha($asignacion['fecha_asignacion'] ?? null),
                'Cliente' => $asignacion['cliente_asignacion'] ?? $asignacion['cliente_presupuesto'] ?? 'Sin cliente',
                'Producto' => $asignacion['nombre_producto'] ?? $asignacion['descripcion_producto'] ?? 'Sin nombre',
                'Tipo Producto' => $asignacion['tipo_producto'] ?? '',
                'Rejilla' => $asignacion['numero_rejilla'] ?? '',
                'Peso Asignado (kg)' => number_format($asignacion['peso_asignado'] ?? 0, 2),
                'Cantidad Unidades' => $asignacion['cant_uni'] ?? 0,
                'Tipo Unidad' => $asignacion['tipo_unidad'] ?? 'unidades',
                'Estado' => $asignacion['estado_asignacion'] ?? 'activa',
                'Usuario Asignación' => $asignacion['usuario_asignacion'] ?? '',
                'Despachado' => $asignacion['despachado'] ?? 0,
                'Peso Despachado (kg)' => number_format($asignacion['peso_despachado'] ?? 0, 2),
                'Observaciones' => $asignacion['observaciones'] ?? '',
                'Días desde Asignación' => $asignacion['dias_desde_asignacion'] ?? 0,
                'ID Venta' => $asignacion['id_venta'] ?? '',
                'Fecha Venta' => $this->formatearFecha($asignacion['fecha_venta'] ?? null),
                'Precio Unitario' => number_format($asignacion['precio'] ?? 0, 0),
                'Total Producto' => number_format($asignacion['total'] ?? 0, 0)
            ];
        }

        return $datosExportacion;
    }

    public function generarResumenEjecutivo($estadisticasGenerales, $filtros = [])
    {
        $resumen = [
            'periodo_analizado' => $this->determinarPeriodoAnalizado($filtros),
            'metricas_principales' => [
                'total_asignaciones' => (int)($estadisticasGenerales['total_asignaciones'] ?? 0),
                'peso_total' => (float)($estadisticasGenerales['peso_total_asignado'] ?? 0),
                'unidades_totales' => (int)($estadisticasGenerales['unidades_totales_asignadas'] ?? 0),
                'rejillas_utilizadas' => (int)($estadisticasGenerales['rejillas_utilizadas'] ?? 0),
                'clientes_unicos' => (int)($estadisticasGenerales['clientes_unicos'] ?? 0)
            ],
            'distribucion_estados' => [
                'activas' => (int)($estadisticasGenerales['asignaciones_activas'] ?? 0),
                'completadas' => (int)($estadisticasGenerales['asignaciones_completadas'] ?? 0),
                'canceladas' => (int)($estadisticasGenerales['asignaciones_canceladas'] ?? 0)
            ],
            'promedios' => [
                'peso_por_asignacion' => round($estadisticasGenerales['peso_promedio_asignacion'] ?? 0, 2),
                'unidades_por_asignacion' => round($estadisticasGenerales['unidades_promedio_asignacion'] ?? 0, 1)
            ]
        ];

        $total = $resumen['metricas_principales']['total_asignaciones'];
        if ($total > 0) {
            $resumen['porcentajes_estados'] = [
                'activas' => round(($resumen['distribucion_estados']['activas'] / $total) * 100, 1),
                'completadas' => round(($resumen['distribucion_estados']['completadas'] / $total) * 100, 1),
                'canceladas' => round(($resumen['distribucion_estados']['canceladas'] / $total) * 100, 1)
            ];
        } else {
            $resumen['porcentajes_estados'] = [
                'activas' => 0,
                'completadas' => 0,
                'canceladas' => 0
            ];
        }

        $resumen['analisis_eficiencia'] = $this->analizarEficiencia($estadisticasGenerales);

        return $resumen;
    }

    private function determinarPeriodoAnalizado($filtros)
    {
        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            return [
                'tipo' => 'personalizado',
                'inicio' => $filtros['fecha_inicio'],
                'fin' => $filtros['fecha_fin'],
                'texto' => 'Del ' . $this->formatearFecha($filtros['fecha_inicio']) . ' al ' . $this->formatearFecha($filtros['fecha_fin'])
            ];
        } elseif (!empty($filtros['fecha_inicio'])) {
            return [
                'tipo' => 'desde',
                'inicio' => $filtros['fecha_inicio'],
                'texto' => 'Desde ' . $this->formatearFecha($filtros['fecha_inicio'])
            ];
        } elseif (!empty($filtros['fecha_fin'])) {
            return [
                'tipo' => 'hasta',
                'fin' => $filtros['fecha_fin'],
                'texto' => 'Hasta ' . $this->formatearFecha($filtros['fecha_fin'])
            ];
        } else {
            return [
                'tipo' => 'completo',
                'texto' => 'Historial completo'
            ];
        }
    }

    private function analizarEficiencia($estadisticas)
    {
        $total = (int)($estadisticas['total_asignaciones'] ?? 0);
        $completadas = (int)($estadisticas['asignaciones_completadas'] ?? 0);
        $canceladas = (int)($estadisticas['asignaciones_canceladas'] ?? 0);

        $eficiencia = [
            'tasa_completado' => $total > 0 ? round(($completadas / $total) * 100, 1) : 0,
            'tasa_cancelacion' => $total > 0 ? round(($canceladas / $total) * 100, 1) : 0,
            'tasa_exito' => 0,
            'nivel_eficiencia' => 'bajo',
            'recomendaciones' => []
        ];

        $eficiencia['tasa_exito'] = max(0, 100 - $eficiencia['tasa_cancelacion']);

        if ($eficiencia['tasa_completado'] >= 80 && $eficiencia['tasa_cancelacion'] <= 10) {
            $eficiencia['nivel_eficiencia'] = 'excelente';
        } elseif ($eficiencia['tasa_completado'] >= 60 && $eficiencia['tasa_cancelacion'] <= 20) {
            $eficiencia['nivel_eficiencia'] = 'bueno';
        } elseif ($eficiencia['tasa_completado'] >= 40 && $eficiencia['tasa_cancelacion'] <= 30) {
            $eficiencia['nivel_eficiencia'] = 'regular';
        } else {
            $eficiencia['nivel_eficiencia'] = 'bajo';
        }

        if ($eficiencia['tasa_cancelacion'] > 20) {
            $eficiencia['recomendaciones'][] = 'Revisar causas de cancelaciones elevadas';
        }
        if ($eficiencia['tasa_completado'] < 50) {
            $eficiencia['recomendaciones'][] = 'Mejorar seguimiento de asignaciones activas';
        }
        if ($total < 10) {
            $eficiencia['recomendaciones'][] = 'Incrementar uso del sistema de asignaciones';
        }

        return $eficiencia;
    }

    private function formatearFecha($fecha)
    {
        if (!$fecha) return null;

        try {
            $dt = new DateTime($fecha);
            return $dt->format('d/m/Y H:i');
        } catch (Exception $e) {
            return $fecha;
        }
    }
}
