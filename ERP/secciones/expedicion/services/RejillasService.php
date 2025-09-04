<?php

require_once __DIR__ . '../../repository/rejillasRepository.php';

class RejillasService
{
    private $repository;

    public function __construct($conexion)
    {
        $this->repository = new RejillasRepository($conexion);
    }
    public function obtenerDatosVistaRejillas()
    {
        try {
            $rejillas = $this->repository->obtenerRejillasDetalladas();
            $estadisticasGenerales = $this->repository->obtenerEstadisticasRejillas();
            $rejillasEnriquecidas = $this->aplicarCalculosNegocio($rejillas);
            $alertas = $this->generarAlertasInteligentes($rejillas, $estadisticasGenerales);
            $configuracion = $this->obtenerConfiguracionSistema();

            return [
                'rejillas' => $rejillasEnriquecidas,
                'estadisticas_generales' => $estadisticasGenerales,
                'alertas' => $alertas,
                'configuracion' => $configuracion
            ];
        } catch (Exception $e) {
            error_log("Error en servicio obteniendo datos de vista rejillas: " . $e->getMessage());
            throw new Exception("Error al obtener datos de rejillas: " . $e->getMessage());
        }
    }

    public function obtenerDetallesRejilla($idRejilla)
    {
        $this->validarIdRejilla($idRejilla);

        try {
            $rejillas = $this->repository->obtenerRejillasDetalladas();
            $rejilla = null;
            foreach ($rejillas as $r) {
                if ($r['id'] == $idRejilla) {
                    $rejilla = $r;
                    break;
                }
            }

            if (!$rejilla) {
                throw new Exception("Rejilla no encontrada");
            }
            $itemsAsignados = $this->repository->obtenerItemsAsignadosRejilla($idRejilla);
            $rejillaEnriquecida = $this->enriquecerDatosRejilla($rejilla);
            $itemsProcesados = $this->procesarItemsParaVista($itemsAsignados);
            $metricas = $this->calcularMetricasRejilla($rejilla, $itemsAsignados);

            return [
                'rejilla' => $rejillaEnriquecida,
                'items_asignados' => $itemsProcesados,
                'metricas' => $metricas
            ];
        } catch (Exception $e) {
            error_log("Error en servicio obteniendo detalles de rejilla: " . $e->getMessage());
            throw $e;
        }
    }

    public function obtenerItemsCompletadosRejilla($idRejilla)
    {
        $this->validarIdRejilla($idRejilla);

        try {
            $itemsCompletados = $this->repository->obtenerItemsCompletadosRejilla($idRejilla);
            return $this->procesarItemsParaVista($itemsCompletados);
        } catch (Exception $e) {
            error_log("Error en servicio obteniendo items completados: " . $e->getMessage());
            return [];
        }
    }

    public function marcarItemComoCompletado($idAsignacion, $observaciones = null)
    {
        $this->validarIdAsignacion($idAsignacion);

        try {
            $this->validarPuedeCompletarse($idAsignacion);

            $observacionesProcesadas = $this->procesarObservaciones($observaciones, 'COMPLETADO');
            return $this->repository->marcarItemComoCompletado($idAsignacion, $observacionesProcesadas);
        } catch (Exception $e) {
            error_log("Error en servicio marcando item como completado: " . $e->getMessage());
            return [
                'exito' => false,
                'mensaje' => $e->getMessage()
            ];
        }
    }

    public function reactivarItemCompletado($idAsignacion, $observaciones = null)
    {
        $this->validarIdAsignacion($idAsignacion);

        try {
            $this->validarPuedeReactivarse($idAsignacion);

            $observacionesProcesadas = $this->procesarObservaciones($observaciones, 'REACTIVADO');
            return $this->repository->reactivarItemCompletado($idAsignacion, $observacionesProcesadas);
        } catch (Exception $e) {
            error_log("Error en servicio reactivando item: " . $e->getMessage());
            return [
                'exito' => false,
                'mensaje' => $e->getMessage()
            ];
        }
    }
    public function limpiarAsignacion($idAsignacion, $observaciones = null)
    {
        $this->validarIdAsignacion($idAsignacion);

        try {
            $validacion = $this->validarPuedeLimpiarse($idAsignacion);
            if (!$validacion['puede_limpiar']) {
                throw new Exception($validacion['razon']);
            }
            $observacionesProcesadas = $this->procesarObservaciones($observaciones, 'CANCELADA');
            return $this->repository->limpiarAsignacion($idAsignacion, $observacionesProcesadas);
        } catch (Exception $e) {
            error_log("Error en servicio limpiando asignación: " . $e->getMessage());
            return [
                'exito' => false,
                'mensaje' => $e->getMessage()
            ];
        }
    }

    public function actualizarDescripcionRejilla($idRejilla, $descripcion)
    {
        $this->validarIdRejilla($idRejilla);

        try {
            $descripcionValidada = $this->validarDescripcion($descripcion);
            return $this->repository->actualizarDescripcionRejilla($idRejilla, $descripcionValidada);
        } catch (Exception $e) {
            error_log("Error en servicio actualizando descripción: " . $e->getMessage());
            return [
                'exito' => false,
                'mensaje' => $e->getMessage()
            ];
        }
    }

    public function obtenerRejillasDisponibles()
    {
        try {
            return $this->repository->obtenerRejillasDisponibles();
        } catch (Exception $e) {
            error_log("Error en servicio obteniendo rejillas disponibles: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerEstadisticasCompletas()
    {
        try {
            $estadisticas = $this->repository->obtenerEstadisticasRejillas();
            $estadisticas['metricas_calculadas'] = $this->calcularMetricasAdicionales($estadisticas);
            return $estadisticas;
        } catch (Exception $e) {
            error_log("Error en servicio obteniendo estadísticas: " . $e->getMessage());
            throw $e;
        }
    }

    private function aplicarCalculosNegocio($rejillas)
    {
        foreach ($rejillas as &$rejilla) {
            $rejilla['porcentaje_ocupacion'] = ($rejilla['peso_actual'] / $rejilla['capacidad_maxima']) * 100;
            $rejilla['estado_calculado'] = $this->determinarEstadoRejilla($rejilla);
            $rejilla['nivel_prioridad'] = $this->calcularPrioridadRejilla($rejilla);
            $pesoAsignado = floatval($rejilla['peso_actual']);
            $pesoProducido = floatval($rejilla['peso_total_producido'] ?? 0);
            $rejilla['eficiencia_produccion'] = $pesoAsignado > 0 ? ($pesoProducido / $pesoAsignado) * 100 : 0;
        }

        return $rejillas;
    }

    private function determinarEstadoRejilla($rejilla)
    {
        $porcentaje = ($rejilla['peso_actual'] / $rejilla['capacidad_maxima']) * 100;

        if ($porcentaje > 100) {
            return 'sobrecargada';
        } elseif ($porcentaje >= 95) {
            return 'llena';
        } elseif ($porcentaje >= 85) {
            return 'alta_ocupacion';
        } elseif ($porcentaje > 0) {
            return 'ocupada';
        } else {
            return 'disponible';
        }
    }

    private function calcularPrioridadRejilla($rejilla)
    {
        $porcentaje = ($rejilla['peso_actual'] / $rejilla['capacidad_maxima']) * 100;

        if ($porcentaje > 100) return 'critica';
        if ($porcentaje >= 95) return 'alta';
        if ($porcentaje >= 85) return 'media';
        if ($porcentaje >= 70) return 'baja';
        return 'normal';
    }

    private function enriquecerDatosRejilla($rejilla)
    {
        $rejilla['porcentaje_ocupacion'] = ($rejilla['peso_actual'] / $rejilla['capacidad_maxima']) * 100;
        $rejilla['estado_calculado'] = $this->determinarEstadoRejilla($rejilla);
        $rejilla['nivel_prioridad'] = $this->calcularPrioridadRejilla($rejilla);

        return $rejilla;
    }

    private function procesarItemsParaVista($items)
    {
        foreach ($items as &$item) {
            $item['tiempo_en_rejilla'] = $this->calcularTiempoEnRejilla($item['fecha_asignacion']);
            $item['porcentaje_progreso'] = $this->calcularPorcentajeProgreso($item);
            $item['estado_produccion'] = $this->determinarEstadoProduccion($item);
        }
        usort($items, function ($a, $b) {
            return strtotime($b['fecha_asignacion']) - strtotime($a['fecha_asignacion']);
        });

        return $items;
    }

    private function calcularTiempoEnRejilla($fechaAsignacion)
    {
        $fecha = new DateTime($fechaAsignacion);
        $ahora = new DateTime();
        $diferencia = $ahora->diff($fecha);

        if ($diferencia->days > 0) {
            return "{$diferencia->days} día(s)";
        } elseif ($diferencia->h > 0) {
            return "{$diferencia->h} hora(s)";
        } else {
            return "Menos de 1 hora";
        }
    }

    private function calcularPorcentajeProgreso($item)
    {
        $cantidadAsignada = floatval($item['cantidad_unidades_asignadas'] ?? 0);
        $cantidadProducida = floatval($item['cantidad_producida'] ?? 0);

        if ($cantidadAsignada > 0) {
            return min(100, ($cantidadProducida / $cantidadAsignada) * 100);
        }

        return 0;
    }

    private function determinarEstadoProduccion($item)
    {
        $porcentaje = $this->calcularPorcentajeProgreso($item);

        if ($porcentaje >= 100) return 'completado';
        if ($porcentaje >= 75) return 'avanzado';
        if ($porcentaje >= 25) return 'en_proceso';
        if ($porcentaje > 0) return 'iniciado';
        return 'pendiente';
    }

    private function calcularMetricasRejilla($rejilla, $items)
    {
        $totalItems = count($items);
        $itemsConProduccion = 0;
        $tiempoPromedioRejilla = 0;

        foreach ($items as $item) {
            if (floatval($item['cantidad_producida'] ?? 0) > 0) {
                $itemsConProduccion++;
            }
        }

        return [
            'total_items' => $totalItems,
            'items_con_produccion' => $itemsConProduccion,
            'porcentaje_items_activos' => $totalItems > 0 ? ($itemsConProduccion / $totalItems) * 100 : 0,
            'estado_general' => $this->determinarEstadoRejilla($rejilla)
        ];
    }

    private function generarAlertasInteligentes($rejillas, $estadisticas)
    {
        $alertas = [];

        try {
            $sobrecargadas = array_filter($rejillas, function ($r) {
                return ($r['peso_actual'] / $r['capacidad_maxima']) * 100 > 100;
            });

            if (count($sobrecargadas) > 0) {
                $alertas[] = [
                    'tipo' => 'danger',
                    'icono' => 'exclamation-triangle',
                    'titulo' => 'Rejillas Sobrecargadas',
                    'mensaje' => '⚠️ ' . count($sobrecargadas) . ' rejilla(s) sobrecargada(s). Revisar inmediatamente.',
                    'prioridad' => 10
                ];
            }
            $altaOcupacion = array_filter($rejillas, function ($r) {
                $porcentaje = ($r['peso_actual'] / $r['capacidad_maxima']) * 100;
                return $porcentaje > 85 && $porcentaje <= 100;
            });
            if (count($altaOcupacion) > 0) {
                $alertas[] = [
                    'tipo' => 'warning',
                    'icono' => 'exclamation-circle',
                    'titulo' => 'Capacidad Alta',
                    'mensaje' => '📊 ' . count($altaOcupacion) . ' rejilla(s) con más del 85% de ocupación',
                    'prioridad' => 7
                ];
            }
            $mantenimiento = intval($estadisticas['mantenimiento'] ?? 0);
            if ($mantenimiento > 0) {
                $alertas[] = [
                    'tipo' => 'info',
                    'icono' => 'tools',
                    'titulo' => 'Mantenimiento',
                    'mensaje' => '🔧 ' . $mantenimiento . ' rejilla(s) en mantenimiento',
                    'prioridad' => 5
                ];
            }
            $eficienciaGlobal = floatval($estadisticas['eficiencia_almacen'] ?? 0);
            if ($eficienciaGlobal < 50) {
                $alertas[] = [
                    'tipo' => 'warning',
                    'icono' => 'chart-pie',
                    'titulo' => 'Eficiencia Global Baja',
                    'mensaje' => "⚡ Eficiencia del almacén: {$eficienciaGlobal}% - Considerar optimización",
                    'prioridad' => 6
                ];
            }
            usort($alertas, function ($a, $b) {
                return ($b['prioridad'] ?? 0) - ($a['prioridad'] ?? 0);
            });
            return array_slice($alertas, 0, 10);
        } catch (Exception $e) {
            error_log("Error generando alertas: " . $e->getMessage());
            return [[
                'tipo' => 'danger',
                'icono' => 'exclamation-triangle',
                'titulo' => 'Error del Sistema',
                'mensaje' => 'Hubo un problema al generar las alertas del sistema'
            ]];
        }
    }
    private function calcularMetricasAdicionales($estadisticas)
    {
        return [
            'indice_eficiencia_global' => $this->calcularIndiceEficiencia($estadisticas),
            'capacidad_optima_uso' => $this->calcularCapacidadOptima($estadisticas),
            'score_salud_almacen' => $this->calcularScoreSaludAlmacen($estadisticas)
        ];
    }

    private function calcularIndiceEficiencia($estadisticas)
    {
        $eficiencia = floatval($estadisticas['eficiencia_almacen'] ?? 0);
        $ocupadas = intval($estadisticas['ocupadas'] ?? 0);
        $total = intval($estadisticas['total_rejillas'] ?? 1);

        $factorOcupacion = ($ocupadas / $total) * 100;
        return ($eficiencia + $factorOcupacion) / 2;
    }

    private function calcularCapacidadOptima($estadisticas)
    {
        $capacidadTotal = floatval($estadisticas['capacidad_total'] ?? 1);
        $pesoActual = floatval($estadisticas['peso_total_actual'] ?? 0);
        $capacidadOptima = $capacidadTotal * 0.85;
        return [
            'capacidad_optima' => $capacidadOptima,
            'uso_actual' => $pesoActual,
            'diferencia' => $capacidadOptima - $pesoActual,
            'dentro_optimo' => $pesoActual <= $capacidadOptima
        ];
    }

    private function calcularScoreSaludAlmacen($estadisticas)
    {
        $score = 100;
        $llenas = intval($estadisticas['llenas'] ?? 0);
        $score -= $llenas * 10;
        $eficiencia = floatval($estadisticas['eficiencia_almacen'] ?? 0);
        if ($eficiencia < 50) {
            $score -= (50 - $eficiencia);
        }
        $mantenimiento = intval($estadisticas['mantenimiento'] ?? 0);
        $score -= $mantenimiento * 5;

        return max(0, min(100, $score));
    }

    private function obtenerConfiguracionSistema()
    {
        return [
            'version_sistema' => '4.5',
            'reglas_negocio' => [
                'capacidad_maxima_rejilla' => 16000,
                'porcentaje_alerta_alta' => 85,
                'porcentaje_alerta_critica' => 95,
                'porcentaje_sobrecarga' => 100,
                'dias_maximos_rejilla' => 30
            ],
            'configuracion_sistema' => [
                'auto_refresh_interval' => 30,
                'max_alertas_mostrar' => 10,
                'permitir_sobrecarga' => false,
                'mostrar_metricas_avanzadas' => true
            ]
        ];
    }

    private function validarPuedeCompletarse($idAsignacion)
    {
        return true;
    }

    private function validarPuedeReactivarse($idAsignacion)
    {
        return true;
    }

    private function validarPuedeLimpiarse($idAsignacion)
    {
        return [
            'puede_limpiar' => true,
            'razon' => ''
        ];
    }

    private function validarDescripcion($descripcion)
    {
        $descripcion = trim($descripcion);

        if (strlen($descripcion) > 500) {
            throw new Exception("La descripción no puede exceder 500 caracteres");
        }

        return htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8');
    }

    private function procesarObservaciones($observaciones, $prefijo)
    {
        if ($observaciones !== null && trim($observaciones) !== '') {
            return trim($observaciones);
        }
        return null;
    }

    private function validarIdRejilla($idRejilla)
    {
        if (!isset($idRejilla) || !is_numeric($idRejilla) || $idRejilla <= 0) {
            throw new Exception("ID de rejilla inválido");
        }
    }

    private function validarIdAsignacion($idAsignacion)
    {
        if (!isset($idAsignacion) || !is_numeric($idAsignacion) || $idAsignacion <= 0) {
            throw new Exception("ID de asignación inválido");
        }
    }
}
