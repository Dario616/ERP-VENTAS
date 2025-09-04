<?php

class PendienteService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    /**
     * Obtiene el resumen agregado de producción pendiente
     * @param array $filtros Filtros opcionales para la consulta
     * @return array Resumen agregado por tipo de producto
     */
    public function obtenerResumenAgregado($filtros = [])
    {
        $datos = $this->repository->obtenerResumenPendiente();

        $resumen = [
            'TNT_M1' => [
                'total_kg' => 0,
                'ordenes' => 0,
                'detalles' => []
            ],
            'TNT_M2' => [
                'total_kg' => 0,
                'ordenes' => 0,
                'detalles' => []
            ],
            'SPUNLACE' => [
                'total_kg' => 0,
                'ordenes' => 0,
                'detalles' => []
            ],
            'TOALLITAS' => [
                'total_unidades' => 0,
                'total_kg' => 0,
                'ordenes' => 0,
                'detalles' => []
            ],
            'PAÑOS' => [
                'total_kg' => 0,
                'ordenes' => 0,
                'detalles' => []
            ],
            'OTROS' => [
                'total_kg' => 0,
                'ordenes' => 0,
                'detalles' => []
            ]
        ];

        foreach ($datos as $fila) {
            // Aplicar filtros si están definidos
            if ($this->aplicarFiltros($fila, $filtros)) {
                continue;
            }

            $cantidadTotal = (float)$fila['cantidad_total'];
            $stockProducido = (float)$fila['stock_producido'];
            $cantidadPendiente = $cantidadTotal - $stockProducido;
            $pesoUnitario = (float)($fila['peso_unitario'] ?? 0);

            // Solo considerar si hay cantidad pendiente
            if ($cantidadPendiente <= 0) {
                continue;
            }

            $itemEnriquecido = $this->enriquecerItem($fila, $cantidadTotal, $stockProducido, $cantidadPendiente, $pesoUnitario);
            $categoria = $this->determinarCategoria($fila);

            if (isset($resumen[$categoria])) {
                if ($categoria === 'TOALLITAS') {
                    // Para toallitas: contar unidades y calcular kg
                    $resumen[$categoria]['total_unidades'] += $cantidadPendiente;
                    $kgPendientes = $cantidadPendiente * $pesoUnitario;
                    $resumen[$categoria]['total_kg'] += $kgPendientes;
                } elseif ($categoria === 'PAÑOS') {
                    // Para paños: si viene en unidades, convertir a kg
                    if ($pesoUnitario > 0) {
                        $kgPendientes = $cantidadPendiente * $pesoUnitario;
                        $resumen[$categoria]['total_kg'] += $kgPendientes;
                    } else {
                        // Si no hay peso unitario, asumir que ya viene en kg
                        $resumen[$categoria]['total_kg'] += $cantidadPendiente;
                    }
                } else {
                    // Para TNT, SPUNLACE y otros: usar kg directamente
                    $resumen[$categoria]['total_kg'] += $cantidadPendiente;
                }

                $resumen[$categoria]['ordenes']++;
                $resumen[$categoria]['detalles'][] = $itemEnriquecido;
            }
        }

        return $this->formatearResumen($resumen);
    }

    /**
     * Obtiene detalles completos organizados por tipo para la vista
     * @param array $filtros Filtros opcionales
     * @return array Detalles organizados por tipo de producto
     */
    public function obtenerDetallesPorTipo($filtros = [])
    {
        $detalleCompleto = $this->repository->obtenerResumenPendiente();
        $detallesPorTipo = [];

        foreach ($detalleCompleto as $item) {
            // Aplicar filtros si están definidos
            if ($this->aplicarFiltros($item, $filtros)) {
                continue;
            }

            $cantidadTotal = (float)$item['cantidad_total'];
            $stockProducido = (float)$item['stock_producido'];
            $cantidadPendiente = $cantidadTotal - $stockProducido;
            $pesoUnitario = (float)($item['peso_unitario'] ?? 0);

            // Solo incluir items con cantidad pendiente
            if ($cantidadPendiente <= 0) {
                continue;
            }

            $categoria = $this->determinarCategoria($item);

            if (!isset($detallesPorTipo[$categoria])) {
                $detallesPorTipo[$categoria] = [];
            }

            $itemEnriquecido = $this->enriquecerItem($item, $cantidadTotal, $stockProducido, $cantidadPendiente, $pesoUnitario);
            $detallesPorTipo[$categoria][] = $itemEnriquecido;
        }

        // Ordenar detalles por fecha de asignación
        foreach ($detallesPorTipo as &$detalles) {
            usort($detalles, function ($a, $b) {
                return strtotime($a['fecha_orden']) - strtotime($b['fecha_orden']);
            });
        }

        return $detallesPorTipo;
    }

    /**
     * Obtiene estadísticas avanzadas de producción pendiente
     * @param array $filtros Filtros opcionales
     * @return array Estadísticas detalladas
     */
    public function obtenerEstadisticas($filtros = [])
    {
        $resumen = $this->obtenerResumenAgregado($filtros);

        $estadisticas = [
            'resumen_general' => [
                'total_ordenes' => 0,
                'total_kg_pendientes' => 0,
                'total_unidades_pendientes' => 0
            ],
            'por_tipo' => [],
            'por_destino' => $this->obtenerEstadisticasPorDestino($filtros),
            'urgencias' => $this->identificarUrgencias($resumen),
            'tendencias' => $this->calcularTendencias($filtros)
        ];

        foreach ($resumen as $tipo => $datos) {
            if ($datos['ordenes'] > 0) {
                $estadisticas['resumen_general']['total_ordenes'] += $datos['ordenes'];

                // Sumar kg de todos los tipos
                if (isset($datos['total_kg'])) {
                    $estadisticas['resumen_general']['total_kg_pendientes'] += $datos['total_kg'];
                }

                // Solo sumar unidades para toallitas
                if (isset($datos['total_unidades']) && $tipo === 'TOALLITAS') {
                    $estadisticas['resumen_general']['total_unidades_pendientes'] += $datos['total_unidades'];
                }

                $estadisticas['por_tipo'][$tipo] = [
                    'ordenes' => $datos['ordenes'],
                    'cantidad_kg' => $datos['total_kg'] ?? 0,
                    'cantidad_unidades' => $datos['total_unidades'] ?? 0,
                    'unidad_principal' => ($tipo === 'TOALLITAS') ? 'unidades' : 'kg',
                    'promedio_kg_por_orden' => $datos['ordenes'] > 0 ?
                        round(($datos['total_kg'] ?? 0) / $datos['ordenes'], 2) : 0,
                    'promedio_unidades_por_orden' => ($tipo === 'TOALLITAS' && $datos['ordenes'] > 0) ?
                        round($datos['total_unidades'] / $datos['ordenes'], 0) : 0
                ];
            }
        }

        return $estadisticas;
    }

    /**
     * Obtiene estadísticas por destino
     * @param array $filtros Filtros opcionales
     * @return array Estadísticas por destino
     */
    public function obtenerEstadisticasPorDestino($filtros = [])
    {
        $datos = $this->repository->obtenerResumenPendiente();
        $estadisticasPorDestino = [];

        foreach ($datos as $item) {
            if ($this->aplicarFiltros($item, $filtros)) {
                continue;
            }

            $cantidadTotal = (float)$item['cantidad_total'];
            $stockProducido = (float)$item['stock_producido'];
            $cantidadPendiente = $cantidadTotal - $stockProducido;
            $pesoUnitario = (float)($item['peso_unitario'] ?? 0);

            if ($cantidadPendiente <= 0) {
                continue;
            }

            $destino = $item['destino'] ?? 'Sin destino';

            if (!isset($estadisticasPorDestino[$destino])) {
                $estadisticasPorDestino[$destino] = [
                    'ordenes' => 0,
                    'cantidad_kg' => 0,
                    'cantidad_unidades' => 0
                ];
            }

            $estadisticasPorDestino[$destino]['ordenes']++;

            if ($item['tipoproducto'] === 'TOALLITAS') {
                $estadisticasPorDestino[$destino]['cantidad_unidades'] += $cantidadPendiente;
                $estadisticasPorDestino[$destino]['cantidad_kg'] += $cantidadPendiente * $pesoUnitario;
            } elseif ($this->esProductoPano($item['tipoproducto'], $item['producto_descripcion'])) {
                if ($pesoUnitario > 0) {
                    $estadisticasPorDestino[$destino]['cantidad_kg'] += $cantidadPendiente * $pesoUnitario;
                } else {
                    $estadisticasPorDestino[$destino]['cantidad_kg'] += $cantidadPendiente;
                }
            } else {
                $estadisticasPorDestino[$destino]['cantidad_kg'] += $cantidadPendiente;
            }
        }

        // Ordenar por cantidad total (kg)
        uasort($estadisticasPorDestino, function ($a, $b) {
            return $b['cantidad_kg'] <=> $a['cantidad_kg'];
        });

        return $estadisticasPorDestino;
    }

    /**
     * Valida los filtros proporcionados
     * @param array $filtros Filtros a validar
     * @return array Array con errores de validación (vacío si todo está bien)
     */
    public function validarFiltros($filtros)
    {
        $errores = [];

        // Validar fechas
        if (!empty($filtros['fecha_desde']) && !empty($filtros['fecha_hasta'])) {
            $fechaDesde = strtotime($filtros['fecha_desde']);
            $fechaHasta = strtotime($filtros['fecha_hasta']);

            if ($fechaDesde === false || $fechaHasta === false) {
                $errores[] = 'Formato de fecha inválido';
            } elseif ($fechaDesde > $fechaHasta) {
                $errores[] = 'La fecha desde no puede ser mayor que la fecha hasta';
            }
        }

        // Validar tipo de producto
        if (!empty($filtros['tipo_producto'])) {
            $tiposValidos = ['TNT', 'SPUNLACE', 'TOALLITAS', 'PAÑOS', 'LAMINADORA'];
            if (!in_array(strtoupper($filtros['tipo_producto']), $tiposValidos)) {
                $errores[] = 'Tipo de producto no válido';
            }
        }

        // Validar cliente
        if (!empty($filtros['cliente']) && strlen($filtros['cliente']) < 2) {
            $errores[] = 'El nombre del cliente debe tener al menos 2 caracteres';
        }

        // Validar destino
        if (!empty($filtros['destino']) && strlen($filtros['destino']) < 2) {
            $errores[] = 'El destino debe tener al menos 2 caracteres';
        }

        return $errores;
    }

    /**
     * Obtiene métricas de rendimiento para el dashboard
     * @return array Métricas de rendimiento
     */
    public function obtenerMetricasRendimiento()
    {
        $resumen = $this->obtenerResumenAgregado();

        $metricas = [
            'eficiencia_global' => 0,
            'sectores_mas_cargados' => [],
            'sectores_menos_cargados' => [],
            'productos_criticos' => $this->identificarProductosCriticos(),
            'recomendaciones' => []
        ];

        // Calcular carga por sector (en kg)
        $cargaPorSector = [];
        foreach ($resumen as $tipo => $datos) {
            if ($datos['ordenes'] > 0) {
                $carga = $datos['total_kg'] ?? 0;
                $cargaPorSector[$tipo] = [
                    'carga' => $carga,
                    'ordenes' => $datos['ordenes'],
                    'promedio' => $carga > 0 ? $carga / $datos['ordenes'] : 0
                ];
            }
        }

        // Identificar sectores más y menos cargados
        if (!empty($cargaPorSector)) {
            arsort($cargaPorSector);
            $sectoresOrdenados = array_keys($cargaPorSector);

            $metricas['sectores_mas_cargados'] = array_slice($sectoresOrdenados, 0, 3);
            $metricas['sectores_menos_cargados'] = array_slice($sectoresOrdenados, -3);

            // Calcular eficiencia global básica
            $totalOrdenes = array_sum(array_column($cargaPorSector, 'ordenes'));
            $promedioGeneral = $totalOrdenes > 0 ?
                array_sum(array_column($cargaPorSector, 'carga')) / $totalOrdenes : 0;

            $metricas['eficiencia_global'] = min(100, max(0, 100 - ($promedioGeneral * 0.1))); // Fórmula básica

            // Generar recomendaciones
            $metricas['recomendaciones'] = $this->generarRecomendaciones($cargaPorSector);
        }

        return $metricas;
    }

    /**
     * MÉTODOS PRIVADOS DE SOPORTE
     */

    private function determinarCategoria($item)
    {
        $tipoproducto = strtoupper(trim($item['tipoproducto'] ?? ''));
        $descripcion = strtoupper($item['producto_descripcion'] ?? '');

        // Manejar TNT y LAMINADORA
        if (in_array($tipoproducto, ['TNT', 'LAMINADORA'])) {
            return $item['categoria'] === 'M2' ? 'TNT_M2' : 'TNT_M1';
        }

        // SOLUCIÓN PARA PAÑOS: Múltiples criterios de detección
        if ($this->esProductoPano($tipoproducto, $descripcion)) {
            return 'PAÑOS';
        }

        // Otros tipos de productos
        switch ($tipoproducto) {
            case 'SPUNLACE':
                return 'SPUNLACE';
            case 'TOALLITAS':
                return 'TOALLITAS';
            default:
                return 'OTROS';
        }
    }

    /**
     * Detecta productos tipo paño usando múltiples criterios
     */
    private function esProductoPano($tipoproducto, $descripcion)
    {
        // Normalizar strings eliminando acentos
        $tipoNormalizado = $this->eliminarAcentos($tipoproducto);
        $descripcionNormalizada = $this->eliminarAcentos($descripcion);

        // Criterio 1: Tipo de producto
        $tiposValidos = ['PAÑOS', 'PANOS', 'PAÑO', 'PANO'];
        foreach ($tiposValidos as $tipo) {
            if (strpos($tipoNormalizado, $this->eliminarAcentos($tipo)) !== false) {
                return true;
            }
        }

        // Criterio 2: Descripción contiene palabras clave
        $palabrasClave = ['PAÑO', 'PANO', 'CAJA PAÑO', 'CAJA PANO', 'MULTIUSO'];
        foreach ($palabrasClave as $palabra) {
            if (strpos($descripcionNormalizada, $this->eliminarAcentos($palabra)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Elimina acentos y caracteres especiales
     */
    private function eliminarAcentos($cadena)
    {
        $acentos = [
            'Ñ' => 'N',
            'ñ' => 'n',
            'Á' => 'A',
            'á' => 'a',
            'É' => 'E',
            'é' => 'e',
            'Í' => 'I',
            'í' => 'i',
            'Ó' => 'O',
            'ó' => 'o',
            'Ú' => 'U',
            'ú' => 'u'
        ];

        return strtr($cadena, $acentos);
    }

    private function aplicarFiltros($fila, $filtros)
    {
        if (!empty($filtros['cliente'])) {
            if (stripos($fila['cliente'], $filtros['cliente']) === false) {
                return true; // Excluir este item
            }
        }

        if (!empty($filtros['tipo_producto'])) {
            $tipoFila = strtoupper($fila['tipoproducto']);
            $tipoFiltro = strtoupper($filtros['tipo_producto']);

            // TNT incluye tanto TNT como LAMINADORA
            if ($tipoFiltro === 'TNT') {
                if (!in_array($tipoFila, ['TNT', 'LAMINADORA'])) {
                    return true; // Excluir
                }
            } else {
                if ($tipoFila !== $tipoFiltro) {
                    return true; // Excluir
                }
            }
        }

        if (!empty($filtros['fecha_desde'])) {
            if (strtotime($fila['fecha_orden']) < strtotime($filtros['fecha_desde'])) {
                return true; // Excluir este item
            }
        }

        if (!empty($filtros['fecha_hasta'])) {
            if (strtotime($fila['fecha_orden']) > strtotime($filtros['fecha_hasta'] . ' 23:59:59')) {
                return true; // Excluir este item
            }
        }

        if (!empty($filtros['destino'])) {
            if (stripos($fila['destino'] ?? '', $filtros['destino']) === false) {
                return true; // Excluir este item
            }
        }

        if (!empty($filtros['estado'])) {
            $estadoItem = $fila['estado'] ?? 'Sin Estado';
            if (stripos($estadoItem, $filtros['estado']) === false) {
                return true; // Excluir este item
            }
        }

        return false; // No excluir
    }

    private function enriquecerItem($fila, $cantidadTotal, $stockProducido, $cantidadPendiente, $pesoUnitario = 0)
    {
        $porcentaje = $cantidadTotal > 0 ? ($stockProducido / $cantidadTotal) * 100 : 0;

        // Calcular peso en kg para toallitas y paños
        $pesoEnKg = 0;
        $tipoproducto = strtoupper($fila['tipoproducto']);

        if ($tipoproducto === 'TOALLITAS' && $pesoUnitario > 0) {
            $pesoEnKg = $cantidadPendiente * $pesoUnitario;
        } elseif ($this->esProductoPano($tipoproducto, $fila['producto_descripcion']) && $pesoUnitario > 0) {
            $pesoEnKg = $cantidadPendiente * $pesoUnitario;
        } else {
            $pesoEnKg = $cantidadPendiente; // Para TNT, SPUNLACE ya están en kg
        }

        return array_merge($fila, [
            'cantidad_total' => $cantidadTotal,
            'cantidad_pendiente' => $cantidadPendiente,
            'peso_pendiente_kg' => $pesoEnKg,
            'peso_unitario' => $pesoUnitario,
            'porcentaje_completado' => round($porcentaje, 2),
            'unidad' => $this->obtenerUnidad($fila['tipoproducto']),
            'dias_pendiente' => $this->calcularDiasPendiente($fila['fecha_orden']),
            'info_descripcion' => $this->extraerInfoDescripcion($fila['producto_descripcion'] ?? ''),
            // Campos para compatibilidad con la vista anterior
            'id_orden' => $fila['id'],
            'estado_orden' => $fila['estado'] ?? 'Sin Estado'
        ]);
    }

    private function obtenerUnidad($tipoProducto)
    {
        switch (strtoupper($tipoProducto)) {
            case 'TOALLITAS':
                return 'cajas';
            case 'TNT':
            case 'LAMINADORA':
            case 'SPUNLACE':
            case 'PAÑOS':
            default:
                return 'kg';
        }
    }

    private function calcularDiasPendiente($fechaOrden)
    {
        $fecha = strtotime($fechaOrden);
        $hoy = strtotime(date('Y-m-d'));
        return ceil(($hoy - $fecha) / (24 * 60 * 60));
    }

    private function extraerInfoDescripcion($descripcion)
    {
        $info = [
            'ancho_cm' => null,
            'largura_m' => null,
            'gramatura' => null,
            'color' => null
        ];

        // Extraer ancho en cm
        if (preg_match('/ancho.*?([0-9]+[,.]?[0-9]*).*cm/i', $descripcion, $matches)) {
            $info['ancho_cm'] = floatval(str_replace(',', '.', $matches[1]));
        }

        // Extraer largura en metros
        if (preg_match('/largura.*?([0-9]+[,.]?[0-9]*).*m(?!g)/i', $descripcion, $matches)) {
            $info['largura_m'] = floatval(str_replace(',', '.', $matches[1]));
        }

        // Extraer gramatura
        if (preg_match('/([0-9]+)\s*gr?\/m2?/i', $descripcion, $matches)) {
            $info['gramatura'] = intval($matches[1]);
        }

        // Extraer color (básico)
        $colores = ['blanco', 'azul', 'verde', 'rojo', 'amarillo', 'negro', 'gris', 'rosa', 'celeste'];
        foreach ($colores as $color) {
            if (stripos($descripcion, $color) !== false) {
                $info['color'] = ucfirst($color);
                break;
            }
        }

        return $info;
    }

    private function formatearResumen($resumen)
    {
        foreach ($resumen as $tipo => &$datos) {
            if (isset($datos['total_kg'])) {
                $datos['total_kg'] = round($datos['total_kg'], 2);
            }
            if (isset($datos['total_unidades'])) {
                $datos['total_unidades'] = round($datos['total_unidades'], 0);
            }
        }
        return $resumen;
    }

    private function identificarUrgencias($resumen)
    {
        $urgencias = [];

        foreach ($resumen as $tipo => $datos) {
            if ($datos['ordenes'] > 0) {
                $esUrgente = false;
                $razones = [];

                // Muchas órdenes
                if ($datos['ordenes'] >= 8) {
                    $esUrgente = true;
                    $razones[] = "Muchas órdenes pendientes ({$datos['ordenes']})";
                }

                // Gran cantidad en kg
                $cantidadKg = $datos['total_kg'] ?? 0;
                $umbralKg = 3000; // Umbral en kg

                if ($cantidadKg > $umbralKg) {
                    $esUrgente = true;
                    $razones[] = "Gran cantidad pendiente (" . number_format($cantidadKg, 0) . " kg)";
                }

                // Para toallitas, también verificar unidades
                if ($tipo === 'TOALLITAS') {
                    $cantidadUnidades = $datos['total_unidades'] ?? 0;
                    if ($cantidadUnidades > 5000) {
                        $esUrgente = true;
                        $razones[] = "Gran cantidad pendiente (" . number_format($cantidadUnidades, 0) . " unidades)";
                    }
                }

                // Verificar productos críticos en detalles
                foreach ($datos['detalles'] as $detalle) {
                    if ($detalle['dias_pendiente'] > 30) {
                        $esUrgente = true;
                        $razones[] = "Productos con más de 30 días pendientes";
                        break;
                    }
                }

                if ($esUrgente) {
                    $urgencias[] = [
                        'tipo' => $tipo,
                        'razones' => $razones,
                        'ordenes' => $datos['ordenes'],
                        'cantidad_kg' => $cantidadKg,
                        'cantidad_unidades' => $datos['total_unidades'] ?? 0,
                        'nivel' => $this->determinarNivelUrgencia($datos, $razones)
                    ];
                }
            }
        }

        // Ordenar por nivel de urgencia
        usort($urgencias, function ($a, $b) {
            $niveles = ['Alta' => 3, 'Media' => 2, 'Baja' => 1];
            return ($niveles[$b['nivel']] ?? 0) - ($niveles[$a['nivel']] ?? 0);
        });

        return $urgencias;
    }

    private function determinarNivelUrgencia($datos, $razones)
    {
        $cantidadKg = $datos['total_kg'] ?? 0;

        if ($datos['ordenes'] > 15 || $cantidadKg > 10000 || count($razones) >= 3) {
            return 'Alta';
        } elseif ($datos['ordenes'] > 10 || $cantidadKg > 5000 || count($razones) >= 2) {
            return 'Media';
        } else {
            return 'Baja';
        }
    }

    private function identificarProductosCriticos()
    {
        $datos = $this->repository->obtenerResumenPendiente();
        $criticos = [];

        foreach ($datos as $item) {
            $cantidadTotal = (float)$item['cantidad_total'];
            $stockProducido = (float)$item['stock_producido'];
            $cantidadPendiente = $cantidadTotal - $stockProducido;

            if ($cantidadPendiente <= 0) continue;

            $diasPendiente = $this->calcularDiasPendiente($item['fecha_orden']);

            // Criterios para productos críticos
            if (
                $diasPendiente > 30 ||
                $cantidadPendiente > 3000 ||
                ($item['estado'] === null && $diasPendiente > 7)
            ) {

                $criticos[] = [
                    'id' => $item['id'],
                    'cliente' => $item['cliente'],
                    'producto' => substr($item['producto_descripcion'], 0, 50),
                    'cantidad_pendiente' => $cantidadPendiente,
                    'dias_pendiente' => $diasPendiente,
                    'motivo_critico' => $this->determinarMotivoCritico($item, $diasPendiente, $cantidadPendiente)
                ];
            }
        }

        // Ordenar por días pendientes descendente
        usort($criticos, function ($a, $b) {
            return $b['dias_pendiente'] - $a['dias_pendiente'];
        });

        return array_slice($criticos, 0, 10); // Top 10 críticos
    }

    private function determinarMotivoCritico($item, $diasPendiente, $cantidadPendiente)
    {
        $motivos = [];

        if ($diasPendiente > 30) {
            $motivos[] = "Más de 30 días pendiente";
        }

        if ($cantidadPendiente > 3000) {
            $motivos[] = "Cantidad elevada";
        }

        if ($item['estado'] === null) {
            $motivos[] = "Sin orden emitida";
        }

        return implode(', ', $motivos);
    }

    private function calcularTendencias($filtros)
    {
        // Implementación básica de tendencias
        // En una implementación completa, compararías períodos históricos

        $datosActuales = $this->obtenerResumenAgregado($filtros);
        $totalActual = 0;

        foreach ($datosActuales as $datos) {
            $totalActual += $datos['total_kg'] ?? 0;
        }

        return [
            'variacion_semanal' => 0, // Requiere datos históricos
            'variacion_mensual' => 0, // Requiere datos históricos
            'proyeccion' => $totalActual > 10000 ? 'creciente' : ($totalActual > 5000 ? 'estable' : 'decreciente'),
            'total_actual' => $totalActual
        ];
    }

    private function generarRecomendaciones($cargaPorSector)
    {
        $recomendaciones = [];

        if (count($cargaPorSector) >= 2) {
            $sectores = array_keys($cargaPorSector);
            $masCargado = $sectores[0];
            $menosCargado = end($sectores);

            $diferencia = $cargaPorSector[$masCargado]['carga'] - $cargaPorSector[$menosCargado]['carga'];

            if ($diferencia > 1000) {
                $recomendaciones[] = "Considerar redistribución de carga: {$masCargado} tiene " .
                    number_format($diferencia, 0) . " kg más que {$menosCargado}";
            }

            // Recomendaciones específicas por sector
            foreach ($cargaPorSector as $sector => $datos) {
                if ($datos['ordenes'] > 12) {
                    $recomendaciones[] = "Sector {$sector}: Alta carga ({$datos['ordenes']} órdenes) - Considerar priorización";
                }

                if ($datos['promedio'] > 500) {
                    $recomendaciones[] = "Sector {$sector}: Órdenes de gran volumen promedio - Revisar capacidad";
                }
            }

            // Recomendaciones generales
            $totalOrdenes = array_sum(array_column($cargaPorSector, 'ordenes'));
            if ($totalOrdenes > 50) {
                $recomendaciones[] = "Alto volumen total de órdenes ({$totalOrdenes}) - Evaluar ampliación de capacidad";
            }
        }

        return array_slice($recomendaciones, 0, 5); // Máximo 5 recomendaciones
    }
}
