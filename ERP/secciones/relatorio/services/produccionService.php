<?php

/**
 * Service para lógica de negocio de reportes de producción con filtros de horario
 */
class ProduccionService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    /**
     * ✅ ACTUALIZADA: Obtener todos los datos del dashboard - Con soporte horario
     */
    public function obtenerDatosDashboard($filtros)
    {
        $estadisticasGenerales = $this->obtenerEstadisticasGenerales($filtros);
        $evolucionProduccion = $this->obtenerEvolucionProduccion($filtros);
        $topProductos = $this->obtenerTopProductos($filtros, 5);
        $estadisticasPorEstado = $this->repository->obtenerEstadisticasPorEstado($filtros);
        $estadisticasPorOperador = $this->repository->obtenerEstadisticasPorOperador($filtros);
        $datosRaw = $this->repository->obtenerDatosParaExportar($filtros);
        $performanceSectores = $this->calcularPerformanceSectores($datosRaw);

        $resultado = [
            'estadisticas_generales' => $estadisticasGenerales,
            'evolucion_produccion' => $evolucionProduccion,
            'top_productos' => $topProductos,
            'estadisticas_por_estado' => $estadisticasPorEstado,
            'estadisticas_por_operador' => $estadisticasPorOperador,
            'performance_sectores' => $performanceSectores,
            'resumen_periodo' => $this->calcularResumenPeriodo($evolucionProduccion, $filtros)
        ];

        // ✅ NUEVO: Agregar estadísticas específicas de horario si aplica
        if ($this->esFiltroPorHorario($filtros)) {
            $resultado['estadisticas_horario'] = $this->obtenerEstadisticasHorario($filtros);
            $resultado['comparacion_horaria'] = $this->obtenerComparacionHoraria($filtros);
        }

        return $resultado;
    }

    /**
     * ✅ NUEVA FUNCIÓN: Verificar si es un filtro por horario
     */
    private function esFiltroPorHorario($filtros)
    {
        return !empty($filtros['hora_inicio']) &&
            !empty($filtros['hora_fin']) &&
            !empty($filtros['fecha_inicio']) &&
            !empty($filtros['fecha_fin']) &&
            $filtros['fecha_inicio'] === $filtros['fecha_fin'];
    }

    /**
     * ✅ NUEVA FUNCIÓN: Obtener estadísticas específicas para filtros de horario
     */
    private function obtenerEstadisticasHorario($filtros)
    {
        try {
            $horaInicio = $filtros['hora_inicio'];
            $horaFin = $filtros['hora_fin'];

            // Calcular duración del período
            list($horaInicioH, $horaInicioM) = explode(':', $horaInicio);
            list($horaFinH, $horaFinM) = explode(':', $horaFin);

            $minutosInicio = ($horaInicioH * 60) + $horaInicioM;
            $minutosFin = ($horaFinH * 60) + $horaFinM;
            $duracionMinutos = $minutosFin - $minutosInicio;
            $duracionHoras = round($duracionMinutos / 60, 1);

            // Obtener estadísticas por turnos para comparación
            $estadisticasTurnos = $this->repository->obtenerEstadisticasPorRangoHorario($filtros);

            return [
                'rango_horario' => "{$horaInicio} - {$horaFin}",
                'duracion_horas' => $duracionHoras,
                'duracion_minutos' => $duracionMinutos,
                'estadisticas_por_turnos' => $estadisticasTurnos,
                'fecha_analizada' => $filtros['fecha_inicio']
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas de horario: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ NUEVA FUNCIÓN: Obtener comparación horaria con promedios históricos
     */
    private function obtenerComparacionHoraria($filtros)
    {
        try {
            // Obtener producción promedio por hora de los últimos 30 días
            $promediosHistoricos = $this->repository->obtenerPromedioProduccionPorHora($filtros, 30);

            // Obtener producción del día específico
            $produccionDia = $this->repository->obtenerProduccionPorHora($filtros);

            // Combinar datos para comparación
            $comparacion = [];
            $horasConDatos = [];

            // Crear un mapa de promedios históricos
            $mapaHistorico = [];
            foreach ($promediosHistoricos as $promedio) {
                $mapaHistorico[(int)$promedio['hora']] = $promedio;
            }

            // Crear un mapa de producción del día
            $mapaDia = [];
            foreach ($produccionDia as $hora) {
                $mapaDia[(int)$hora['hora']] = $hora;
                $horasConDatos[] = (int)$hora['hora'];
            }

            // Generar comparación para las horas relevantes
            for ($h = 0; $h <= 23; $h++) {
                $produccionActual = $mapaDia[$h]['cantidad_producida'] ?? 0;
                $promedioHistorico = $mapaHistorico[$h]['bobinas_por_dia'] ?? 0;

                $variacion = 0;
                if ($promedioHistorico > 0) {
                    $variacion = (($produccionActual - $promedioHistorico) / $promedioHistorico) * 100;
                }

                $comparacion[] = [
                    'hora' => $h,
                    'hora_formateada' => sprintf('%02d:00', $h),
                    'produccion_actual' => $produccionActual,
                    'promedio_historico' => round($promedioHistorico, 1),
                    'variacion_porcentaje' => round($variacion, 1),
                    'tiene_datos' => in_array($h, $horasConDatos)
                ];
            }

            return [
                'comparacion_por_hora' => $comparacion,
                'resumen' => [
                    'horas_con_produccion' => count($horasConDatos),
                    'total_horas_analizadas' => 24,
                    'periodo_comparacion' => '30 días'
                ]
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo comparación horaria: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calcular performance por sector con tara
     */
    public function calcularPerformanceSectores($datos)
    {
        $sectores = [];

        foreach ($datos as $fila) {
            $tipo = $fila['tipo_producto'] ?? 'Sin Clasificar';

            if (!isset($sectores[$tipo])) {
                $sectores[$tipo] = [
                    'tipo_producto' => $tipo,
                    'peso_bruto_total' => 0,
                    'peso_liquido_total' => 0,
                    'tara_total' => 0,
                    'total_bobinas' => 0,
                    'total_items' => 0
                ];
            }

            $pesoBruto = floatval($fila['peso_bruto'] ?? 0);
            $pesoLiquido = floatval($fila['peso_liquido'] ?? 0);
            $tara = $pesoBruto - $pesoLiquido;

            $sectores[$tipo]['peso_bruto_total'] += $pesoBruto;
            $sectores[$tipo]['peso_liquido_total'] += $pesoLiquido;
            $sectores[$tipo]['tara_total'] += $tara;
            $sectores[$tipo]['total_bobinas'] += intval($fila['bobinas_pacote'] ?? 1);
            $sectores[$tipo]['total_items']++;
        }

        // Calcular eficiencia (peso líquido / peso bruto * 100)
        foreach ($sectores as &$sector) {
            if ($sector['peso_bruto_total'] > 0) {
                $sector['eficiencia_porcentaje'] = ($sector['peso_liquido_total'] / $sector['peso_bruto_total']) * 100;
            } else {
                $sector['eficiencia_porcentaje'] = 0;
            }

            // Redondear valores
            $sector['peso_bruto_total'] = round($sector['peso_bruto_total'], 2);
            $sector['peso_liquido_total'] = round($sector['peso_liquido_total'], 2);
            $sector['tara_total'] = round($sector['tara_total'], 2);
            $sector['eficiencia_porcentaje'] = round($sector['eficiencia_porcentaje'], 2);
        }

        // Ordenar por tara total (descendente)
        uasort($sectores, function ($a, $b) {
            return $b['tara_total'] <=> $a['tara_total'];
        });

        return array_values($sectores);
    }

    /**
     * ✅ ACTUALIZADA: Obtener evolución de producción - Con soporte horario
     */
    public function obtenerEvolucionProduccion($filtros)
    {
        $datos = $this->repository->obtenerEvolucionProduccion($filtros);

        // Enriquecer datos para el gráfico
        $datosEnriquecidos = [];
        $totalAcumulado = 0;
        $esFiltroPorHorario = $this->esFiltroPorHorario($filtros);

        foreach ($datos as $item) {
            $totalAcumulado += $item['cantidad_producida'];

            $itemEnriquecido = [
                'cantidad_producida' => (int)$item['cantidad_producida'],
                'cantidad_formateada' => number_format($item['cantidad_producida'], 0, ',', '.'),
                'items_producidos' => (int)$item['items_producidos'],
                'peso_bruto_total' => round((float)$item['peso_bruto_total'], 2),
                'peso_liquido_total' => round((float)$item['peso_liquido_total'], 2),
                'total_acumulado' => $totalAcumulado,
                'promedio_por_item' => $item['items_producidos'] > 0 ?
                    round((float)$item['cantidad_producida'] / (float)$item['items_producidos'], 1) : 0
            ];

            // ✅ NUEVO: Manejar formato diferente para horarios vs fechas
            if ($esFiltroPorHorario && isset($item['hora'])) {
                $itemEnriquecido['hora'] = (int)$item['hora'];
                $itemEnriquecido['fecha'] = $item['fecha'];
                $itemEnriquecido['fecha_formateada'] = sprintf('%02d:00', $item['hora']);
                $itemEnriquecido['hora_completa'] = sprintf('%02d:00 - %02d:59', $item['hora'], $item['hora']);
            } else {
                $itemEnriquecido['fecha'] = $item['fecha'];
                $itemEnriquecido['fecha_formateada'] = $this->formatearFecha($item['fecha']);
            }

            $datosEnriquecidos[] = $itemEnriquecido;
        }

        return $datosEnriquecidos;
    }

    /**
     * Obtener top productos más producidos con información enriquecida
     */
    public function obtenerTopProductos($filtros, $limite = 5)
    {
        $datos = $this->repository->obtenerTopProductos($filtros, $limite);

        // Calcular total para porcentajes
        $totalGeneral = array_sum(array_column($datos, 'cantidad_total'));

        $datosEnriquecidos = [];
        foreach ($datos as $item) {
            $porcentaje = $totalGeneral > 0 ?
                round(((float)$item['cantidad_total'] / (float)$totalGeneral) * 100, 1) : 0;

            $datosEnriquecidos[] = [
                'nombre_producto' => $item['nombre_producto'],
                'tipo_producto' => $item['tipo_producto'],
                'cantidad_total' => (int)$item['cantidad_total'],
                'cantidad_formateada' => number_format($item['cantidad_total'], 0, ',', '.'),
                'items_producidos' => (int)$item['items_producidos'],
                'peso_bruto_total' => round((float)$item['peso_bruto_total'], 2),
                'peso_liquido_total' => round((float)$item['peso_liquido_total'], 2),
                'promedio_bobinas' => round((float)$item['promedio_bobinas'], 1),
                'primera_produccion' => $item['primera_produccion'],
                'ultima_produccion' => $item['ultima_produccion'],
                'porcentaje_del_total' => $porcentaje,
                'configuracion_tipo' => $this->configurarTipoProducto($item['tipo_producto']),
                'dias_activos' => $this->calcularDiasActivos($item['primera_produccion'], $item['ultima_produccion'])
            ];
        }

        return $datosEnriquecidos;
    }

    /**
     * Obtener estadísticas generales enriquecidas
     */
    public function obtenerEstadisticasGenerales($filtros)
    {
        $stats = $this->repository->obtenerEstadisticasGenerales($filtros);

        if (!$stats) {
            return $this->obtenerEstadisticasVacias();
        }

        // Enriquecer estadísticas
        $stats['total_bobinas_formateado'] = number_format($stats['total_bobinas'] ?? 0, 0, ',', '.');
        $stats['total_peso_bruto_formateado'] = number_format($stats['total_peso_bruto'] ?? 0, 2, ',', '.');
        $stats['total_peso_liquido_formateado'] = number_format($stats['total_peso_liquido'] ?? 0, 2, ',', '.');
        $stats['promedio_bobinas_formateado'] = number_format($stats['promedio_bobinas'] ?? 0, 1, ',', '.');

        // Calcular métricas adicionales
        $stats['eficiencia_peso'] = $this->calcularEficienciaPeso($stats);
        $stats['productividad_diaria'] = $this->calcularProductividadDiaria($stats, $filtros);
        $stats['rendimiento_promedio'] = $this->calcularRendimientoPromedio($stats);

        // Determinar tendencias
        $stats['tendencias'] = $this->analizarTendencias($filtros);

        return $stats;
    }

    /**
     * ✅ ACTUALIZADA: Calcular resumen del período - Con soporte horario
     */
    private function calcularResumenPeriodo($evolucionDatos, $filtros)
    {
        if (empty($evolucionDatos)) {
            return [
                'dias_activos' => 0,
                'mejor_dia' => null,
                'peor_dia' => null,
                'promedio_diario' => 0,
                'tendencia' => 'neutral'
            ];
        }

        $cantidades = array_column($evolucionDatos, 'cantidad_producida');
        $esFiltroPorHorario = $this->esFiltroPorHorario($filtros);

        $mejorIndice = array_search(max($cantidades), $cantidades);
        $peorIndice = array_search(min($cantidades), $cantidades);

        // Calcular tendencia
        $mitad = (int)(count($cantidades) / 2);
        $primeraMitad = array_slice($cantidades, 0, $mitad);
        $segundaMitad = array_slice($cantidades, $mitad);

        $promedioPrimera = count($primeraMitad) > 0 ? array_sum($primeraMitad) / count($primeraMitad) : 0;
        $promedioSegunda = count($segundaMitad) > 0 ? array_sum($segundaMitad) / count($segundaMitad) : 0;

        $tendencia = 'neutral';
        if ($promedioSegunda > $promedioPrimera * 1.1) {
            $tendencia = 'creciente';
        } elseif ($promedioSegunda < $promedioPrimera * 0.9) {
            $tendencia = 'decreciente';
        }

        // ✅ NUEVO: Ajustar etiquetas según el tipo de análisis
        $unidadTiempo = $esFiltroPorHorario ? 'horas' : 'días';
        $etiquetaMejor = $esFiltroPorHorario ? 'Mejor hora' : 'Mejor día';
        $etiquetaPeor = $esFiltroPorHorario ? 'Peor hora' : 'Peor día';

        $resultado = [
            'es_analisis_horario' => $esFiltroPorHorario,
            'unidad_tiempo' => $unidadTiempo,
            'periodos_activos' => count($evolucionDatos),
            'promedio_periodo' => round(array_sum($cantidades) / count($cantidades), 1),
            'tendencia' => $tendencia
        ];

        // Datos del mejor período
        if (isset($evolucionDatos[$mejorIndice])) {
            $mejorPeriodo = $evolucionDatos[$mejorIndice];
            $resultado['mejor_periodo'] = [
                'etiqueta' => $etiquetaMejor,
                'identificador' => $esFiltroPorHorario ?
                    $mejorPeriodo['hora_completa'] ?? $mejorPeriodo['fecha_formateada'] :
                    $mejorPeriodo['fecha_formateada'],
                'cantidad' => $cantidades[$mejorIndice],
                'fecha_formateada' => $mejorPeriodo['fecha_formateada']
            ];
        }

        // Datos del peor período
        if (isset($evolucionDatos[$peorIndice])) {
            $peorPeriodo = $evolucionDatos[$peorIndice];
            $resultado['peor_periodo'] = [
                'etiqueta' => $etiquetaPeor,
                'identificador' => $esFiltroPorHorario ?
                    $peorPeriodo['hora_completa'] ?? $peorPeriodo['fecha_formateada'] :
                    $peorPeriodo['fecha_formateada'],
                'cantidad' => $cantidades[$peorIndice],
                'fecha_formateada' => $peorPeriodo['fecha_formateada']
            ];
        }

        // Mantener compatibilidad con versión anterior
        $resultado['dias_activos'] = $resultado['periodos_activos'];
        $resultado['mejor_dia'] = $resultado['mejor_periodo'] ?? null;
        $resultado['peor_dia'] = $resultado['peor_periodo'] ?? null;
        $resultado['promedio_diario'] = $resultado['promedio_periodo'];

        return $resultado;
    }

    /**
     * Configurar visualización del tipo de producto
     */
    private function configurarTipoProducto($tipoProducto)
    {
        $configuraciones = [
            'TOALLITAS' => [
                'color' => '#3b82f6',
                'icono' => 'fas fa-tissue'
            ],
            'TNT' => [
                'color' => '#6366f1',
                'icono' => 'fas fa-industry'
            ],
            'SPUNLACE' => [
                'color' => '#8b5cf6',
                'icono' => 'fas fa-layer-group'
            ],
            'LAMINADO' => [
                'color' => '#06b6d4',
                'icono' => 'fas fa-layers'
            ]
        ];

        $tipoUpper = strtoupper($tipoProducto ?? '');
        return $configuraciones[$tipoUpper] ?? [
            'color' => '#6b7280',
            'icono' => 'fas fa-box'
        ];
    }

    /**
     * Calcular días activos de producción de un producto
     */
    private function calcularDiasActivos($fechaInicio, $fechaFin)
    {
        if (!$fechaInicio || !$fechaFin) {
            return 0;
        }

        $inicio = new DateTime($fechaInicio);
        $fin = new DateTime($fechaFin);
        $diferencia = $fin->diff($inicio);

        return $diferencia->days + 1;
    }

    /**
     * Calcular eficiencia de peso
     */
    private function calcularEficienciaPeso($stats)
    {
        $pesoBruto = (float)($stats['total_peso_bruto'] ?? 0);
        $pesoLiquido = (float)($stats['total_peso_liquido'] ?? 0);

        if ($pesoBruto <= 0) {
            return 0;
        }

        $eficiencia = ($pesoLiquido / $pesoBruto) * 100;
        return round($eficiencia, 2);
    }

    /**
     * ✅ ACTUALIZADA: Calcular productividad diaria - Con soporte horario
     */
    private function calcularProductividadDiaria($stats, $filtros)
    {
        if ($this->esFiltroPorHorario($filtros)) {
            // Para filtros por horario, calcular productividad por hora
            $horaInicio = $filtros['hora_inicio'];
            $horaFin = $filtros['hora_fin'];

            list($horaInicioH, $horaInicioM) = explode(':', $horaInicio);
            list($horaFinH, $horaFinM) = explode(':', $horaFin);

            $minutosInicio = ($horaInicioH * 60) + $horaInicioM;
            $minutosFin = ($horaFinH * 60) + $horaFinM;
            $duracionHoras = ($minutosFin - $minutosInicio) / 60;

            if ($duracionHoras <= 0) {
                return 0;
            }

            $totalBobinas = (float)($stats['total_bobinas'] ?? 0);
            return round($totalBobinas / $duracionHoras, 1);
        } else {
            // Cálculo normal por días
            $fechaInicio = new DateTime($filtros['fecha_inicio']);
            $fechaFin = new DateTime($filtros['fecha_fin']);
            $dias = $fechaFin->diff($fechaInicio)->days + 1;

            if ($dias <= 0) {
                return 0;
            }

            $totalBobinas = (float)($stats['total_bobinas'] ?? 0);
            return round($totalBobinas / $dias, 1);
        }
    }

    /**
     * Calcular rendimiento promedio por item
     */
    private function calcularRendimientoPromedio($stats)
    {
        $totalItems = (float)($stats['total_items'] ?? 0);
        $totalBobinas = (float)($stats['total_bobinas'] ?? 0);

        if ($totalItems <= 0) {
            return 0;
        }

        return round($totalBobinas / $totalItems, 1);
    }

    /**
     * Analizar tendencias de producción
     */
    private function analizarTendencias($filtros)
    {
        // Comparar con período anterior
        $diasPeriodo = $this->calcularDiasPeriodo($filtros);

        $filtrosAnterior = $filtros;
        $filtrosAnterior['fecha_fin'] = date('Y-m-d', strtotime($filtros['fecha_inicio'] . ' -1 day'));
        $filtrosAnterior['fecha_inicio'] = date('Y-m-d', strtotime($filtrosAnterior['fecha_fin'] . " -{$diasPeriodo} days"));

        // Remover filtros de hora para comparación con período anterior
        unset($filtrosAnterior['hora_inicio'], $filtrosAnterior['hora_fin']);

        $statsActual = $this->repository->obtenerEstadisticasGenerales($filtros);
        $statsAnterior = $this->repository->obtenerEstadisticasGenerales($filtrosAnterior);

        if (!$statsActual || !$statsAnterior) {
            return [
                'variacion_produccion' => 0,
                'variacion_items' => 0,
                'tendencia_general' => 'neutral'
            ];
        }

        $variacionProduccion = $this->calcularVariacion(
            (float)($statsAnterior['total_bobinas'] ?? 0),
            (float)($statsActual['total_bobinas'] ?? 0)
        );

        $variacionItems = $this->calcularVariacion(
            (float)($statsAnterior['total_items'] ?? 0),
            (float)($statsActual['total_items'] ?? 0)
        );

        $tendenciaGeneral = 'neutral';
        if ($variacionProduccion > 5) {
            $tendenciaGeneral = 'creciente';
        } elseif ($variacionProduccion < -5) {
            $tendenciaGeneral = 'decreciente';
        }

        return [
            'variacion_produccion' => $variacionProduccion,
            'variacion_items' => $variacionItems,
            'tendencia_general' => $tendenciaGeneral
        ];
    }

    /**
     * Calcular variación porcentual
     */
    private function calcularVariacion($valorAnterior, $valorActual)
    {
        if ($valorAnterior <= 0) {
            return $valorActual > 0 ? 100 : 0;
        }

        return round((($valorActual - $valorAnterior) / $valorAnterior) * 100, 1);
    }

    /**
     * ✅ ACTUALIZADA: Calcular días del período - Con consideración de horarios
     */
    private function calcularDiasPeriodo($filtros)
    {
        if ($this->esFiltroPorHorario($filtros)) {
            return 1; // Para filtros por horario, siempre es 1 día
        }

        $fechaInicio = new DateTime($filtros['fecha_inicio']);
        $fechaFin = new DateTime($filtros['fecha_fin']);
        return $fechaFin->diff($fechaInicio)->days + 1;
    }

    /**
     * Formatear fecha para visualización
     */
    private function formatearFecha($fecha)
    {
        $fechaObj = new DateTime($fecha);
        return $fechaObj->format('d/m/Y');
    }

    /**
     * Obtener estadísticas vacías por defecto
     */
    private function obtenerEstadisticasVacias()
    {
        return [
            'total_items' => 0,
            'total_bobinas' => 0,
            'total_peso_bruto' => 0,
            'total_peso_liquido' => 0,
            'productos_diferentes' => 0,
            'tipos_diferentes' => 0,
            'operadores_diferentes' => 0,
            'promedio_bobinas' => 0,
            'total_bobinas_formateado' => '0',
            'total_peso_bruto_formateado' => '0,00',
            'total_peso_liquido_formateado' => '0,00',
            'promedio_bobinas_formateado' => '0,0'
        ];
    }

    /**
     * Obtener tipos de producto
     */
    public function obtenerTiposProducto()
    {
        return $this->repository->obtenerTiposProducto();
    }

    /**
     * Obtener operadores
     */
    public function obtenerOperadores()
    {
        return $this->repository->obtenerOperadores();
    }

    /**
     * Obtener estados
     */
    public function obtenerEstados()
    {
        return $this->repository->obtenerEstados();
    }

    /**
     * Buscar operadores para autocompletado
     */
    public function buscarOperadores($termino)
    {
        $operadores = $this->repository->buscarOperadores($termino);

        return array_map(function ($operador) {
            return [
                'usuario' => $operador['usuario'],
                'cantidad_producciones' => $operador['cantidad_producciones'],
                'total_bobinas' => $operador['total_bobinas'],
                'texto_completo' => $operador['usuario'] . ' (' .
                    number_format($operador['total_bobinas']) . ' bobinas, ' .
                    $operador['cantidad_producciones'] . ' producciones)'
            ];
        }, $operadores);
    }

    /**
     * Obtener datos para exportar
     */
    public function obtenerDatosParaExportar($filtros)
    {
        return $this->repository->obtenerDatosParaExportar($filtros);
    }

    /**
     * ✅ ACTUALIZADA: Validar filtros de entrada - Con horarios
     */
    public function validarFiltros($filtros)
    {
        $errores = [];

        // Validar fechas
        if (!empty($filtros['fecha_inicio']) && !$this->validarFecha($filtros['fecha_inicio'])) {
            $errores[] = 'Fecha de inicio inválida';
        }

        if (!empty($filtros['fecha_fin']) && !$this->validarFecha($filtros['fecha_fin'])) {
            $errores[] = 'Fecha de fin inválida';
        }

        // Validar que fecha inicio no sea posterior a fecha fin
        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            if (strtotime($filtros['fecha_inicio']) > strtotime($filtros['fecha_fin'])) {
                $errores[] = 'La fecha de inicio no puede ser posterior a la fecha de fin';
            }
        }

        // ✅ NUEVO: Validar horarios
        if (!empty($filtros['hora_inicio']) && !empty($filtros['hora_fin'])) {
            if (!$this->validarHora($filtros['hora_inicio'])) {
                $errores[] = 'Hora de inicio inválida (formato HH:MM)';
            }

            if (!$this->validarHora($filtros['hora_fin'])) {
                $errores[] = 'Hora de fin inválida (formato HH:MM)';
            }

            if ($this->validarHora($filtros['hora_inicio']) && $this->validarHora($filtros['hora_fin'])) {
                if (!$this->validarRangoHorario($filtros['hora_inicio'], $filtros['hora_fin'])) {
                    $errores[] = 'La hora de inicio debe ser anterior a la hora de fin';
                }
            }

            // Validar que los filtros de hora solo se usen con el mismo día
            if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
                if ($filtros['fecha_inicio'] !== $filtros['fecha_fin']) {
                    $errores[] = 'Los filtros de horario solo se pueden usar cuando las fechas de inicio y fin son iguales';
                }
            }
        }

        // Validar que el rango no sea excesivo (máximo 1 año)
        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            $inicio = new DateTime($filtros['fecha_inicio']);
            $fin = new DateTime($filtros['fecha_fin']);
            $diferencia = $fin->diff($inicio)->days;

            if ($diferencia > 365) {
                $errores[] = 'El rango de fechas no puede ser mayor a 1 año';
            }
        }

        return $errores;
    }

    /**
     * ✅ NUEVA FUNCIÓN: Validar formato de hora
     */
    private function validarHora($hora)
    {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $hora);
    }

    /**
     * ✅ NUEVA FUNCIÓN: Validar rango horario
     */
    private function validarRangoHorario($horaInicio, $horaFin)
    {
        list($horaInicioH, $horaInicioM) = explode(':', $horaInicio);
        list($horaFinH, $horaFinM) = explode(':', $horaFin);

        $minutosInicio = ($horaInicioH * 60) + $horaInicioM;
        $minutosFin = ($horaFinH * 60) + $horaFinM;

        return $minutosInicio < $minutosFin;
    }

    /**
     * Obtener datos para gráficos de calidad
     */
    public function obtenerDatosGraficos($filtros)
    {
        $datosRaw = $this->repository->obtenerDatosParaExportar($filtros);

        $conteoClasificaciones = [
            'dentro-media' => 0,
            'pesado-05' => 0,
            'pesado-1' => 0,
            'liviano-3' => 0,
            'liviano-4' => 0,
            'muy-liviano' => 0,
            'fuera-rango' => 0,
            'sin-datos' => 0
        ];

        $dispersion = [];

        foreach ($datosRaw as $item) {
            $peso_teorico = $this->calcularPesoTeorico(
                $item['gramatura'],
                $item['metragem'],
                $item['largura'],
                $item['bobinas_pacote']
            );
            $clasificacion = $this->clasificarPeso($item['peso_liquido'], $peso_teorico);

            $conteoClasificaciones[$clasificacion['clase']]++;

            if ($peso_teorico > 0) {
                $dispersion[] = [
                    'x' => $peso_teorico,
                    'y' => $item['peso_liquido'],
                    'id' => $item['id'],
                    'clase' => $clasificacion['clase']
                ];
            }
        }

        return [
            'clasificaciones' => $conteoClasificaciones,
            'dispersion' => $dispersion
        ];
    }

    /**
     * Calcular peso teórico
     */
    private function calcularPesoTeorico($gramatura, $metragem, $largura, $bobinas_pacote)
    {
        if (!$gramatura || !$metragem || !$largura || !$bobinas_pacote) {
            return 0;
        }
        return ($gramatura * $metragem * $largura / 1000.0) * $bobinas_pacote;
    }

    /**
     * Clasificar peso
     */
    private function clasificarPeso($peso_real, $peso_teorico)
    {
        if ($peso_teorico == 0) return ['categoria' => 'Sin datos', 'clase' => 'sin-datos'];

        if ($peso_real <= $peso_teorico && $peso_real > ($peso_teorico * 0.979)) {
            return ['categoria' => 'DENTRO DE LA MEDIA 2%', 'clase' => 'dentro-media'];
        } elseif ($peso_real > $peso_teorico && $peso_real <= ($peso_teorico * 1.005)) {
            return ['categoria' => 'Material Pesado rango de 0.5%', 'clase' => 'pesado-05'];
        } elseif ($peso_real < ($peso_teorico * 0.979) && $peso_real < ($peso_teorico * 0.96)) {
            return ['categoria' => 'Material Liviano rango 3%', 'clase' => 'liviano-3'];
        } elseif ($peso_real < ($peso_teorico * 0.979) && $peso_real >= ($peso_teorico * 0.96)) {
            return ['categoria' => 'Material Liviano rango de 4%', 'clase' => 'liviano-4'];
        } elseif ($peso_real < ($peso_teorico * 0.96)) {
            return ['categoria' => 'Material muy liviano rango menor de 4.1%', 'clase' => 'muy-liviano'];
        } elseif ($peso_real > ($peso_teorico * 1.01)) {
            return ['categoria' => 'Material Pesado 1% arriba', 'clase' => 'pesado-1'];
        }

        return ['categoria' => 'Fuera de rango', 'clase' => 'fuera-rango'];
    }

    /**
     * Validar formato de fecha
     */
    private function validarFecha($fecha)
    {
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        return $d && $d->format('Y-m-d') === $fecha;
    }

    /**
     * Generar recomendaciones basadas en los datos
     */
    public function generarRecomendaciones($datos)
    {
        $recomendaciones = [];

        // Analizar producción por operador
        if (!empty($datos['estadisticas_por_operador'])) {
            $operadores = $datos['estadisticas_por_operador'];
            $promedioGeneral = count($operadores) > 0 ? array_sum(array_column($operadores, 'total_bobinas')) / count($operadores) : 0;

            $operadoresBajoRendimiento = array_filter($operadores, function ($op) use ($promedioGeneral) {
                return $op['total_bobinas'] < $promedioGeneral * 0.7;
            });

            if (!empty($operadoresBajoRendimiento)) {
                $recomendaciones[] = [
                    'tipo' => 'operadores',
                    'titulo' => 'Operadores con Bajo Rendimiento',
                    'mensaje' => 'Se identificaron ' . count($operadoresBajoRendimiento) . ' operador(es) con rendimiento por debajo del promedio',
                    'prioridad' => 'media',
                    'accion' => 'Considerar capacitación adicional'
                ];
            }
        }

        // Analizar tendencias
        if (!empty($datos['estadisticas_generales']['tendencias'])) {
            $tendencias = $datos['estadisticas_generales']['tendencias'];

            if ($tendencias['tendencia_general'] === 'decreciente') {
                $recomendaciones[] = [
                    'tipo' => 'tendencia',
                    'titulo' => 'Tendencia Decreciente',
                    'mensaje' => 'La producción ha disminuido ' . abs($tendencias['variacion_produccion']) . '% respecto al período anterior',
                    'prioridad' => 'alta',
                    'accion' => 'Revisar procesos y recursos'
                ];
            }
        }

        return $recomendaciones;
    }

    /**
     * ✅ ACTUALIZADA: Obtener productos paginados - Con fecha/hora completa
     */
    public function obtenerProductosPaginados($filtros, $pagina = 1)
    {
        $resultado = $this->repository->obtenerProductosPaginados($filtros, $pagina, 10);

        // Enriquecer datos
        $datosEnriquecidos = [];
        $esFiltroPorHorario = $this->esFiltroPorHorario($filtros);

        foreach ($resultado['datos'] as $item) {
            $itemEnriquecido = [
                'id' => $item['id'],
                'fecha_hora_producida' => $item['fecha_hora_producida'],
                'fecha_formateada' => $this->formatearFecha(date('Y-m-d', strtotime($item['fecha_hora_producida']))),
                'nombre_producto' => $item['nombre_producto'],
                'tipo_producto' => $item['tipo_producto'],
                'bobinas_pacote' => $item['bobinas_pacote'],
                'peso_bruto' => round($item['peso_bruto'], 2),
                'peso_liquido' => round($item['peso_liquido'], 2),
                'metragem' => $item['metragem'] ?? 0,
                'estado' => $item['estado'],
                'usuario' => $item['usuario'],
                'eficiencia' => $item['peso_bruto'] > 0 ? round(($item['peso_liquido'] / $item['peso_bruto']) * 100, 1) : 0
            ];

            // ✅ NUEVO: Agregar información horaria si es relevante
            if ($esFiltroPorHorario) {
                $fechaHora = new DateTime($item['fecha_hora_producida']);
                $itemEnriquecido['hora_produccion'] = $fechaHora->format('H:i');
                $itemEnriquecido['fecha_hora_completa'] = $fechaHora->format('d/m/Y H:i');
            }

            $datosEnriquecidos[] = $itemEnriquecido;
        }

        return [
            'productos' => $datosEnriquecidos,
            'paginacion' => [
                'total' => $resultado['total'],
                'pagina_actual' => $resultado['pagina_actual'],
                'por_pagina' => $resultado['por_pagina'],
                'total_paginas' => $resultado['total_paginas']
            ]
        ];
    }
}
