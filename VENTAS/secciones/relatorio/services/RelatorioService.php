<?php

/**
 * Service para lógica de negocio de relatorios de ventas
 * Excluye: estado NULL/vacío, "En revision", "Pendiente" y monto <= 0
 */
class RelatorioService
{
    private $repository;
    private $debug;
    private $tasasMoneda = null; // Cache de tasas

    public function __construct($repository, $debug = false)
    {
        $this->repository = $repository;
        $this->debug = $debug;
        // Cargar tasas al inicializar
        $this->cargarTasasMoneda();
    }


    private function cargarTasasMoneda()
    {
        try {
            $this->tasasMoneda = $this->repository->obtenerTasasMoneda();
        } catch (Exception $e) {
            error_log("Error cargando tasas de moneda: " . $e->getMessage());
            // Fallback a tasas fijas si falla la BD
            $this->tasasMoneda = [
                'guaraníes' => 7500,
                'real brasileño' => 5.55
            ];
        }
    }

    public function convertirAUsd($monto, $moneda)
    {
        $monto = (float)$monto;
        $monedaKey = $this->normalizarNombreMoneda($moneda);

        // Buscar tasa en cache
        if (isset($this->tasasMoneda[$monedaKey])) {
            return $monto / $this->tasasMoneda[$monedaKey];
        }

        // Si no encuentra la moneda, asumir que ya está en USD
        return $monto;
    }

    private function normalizarNombreMoneda($moneda)
    {
        $moneda = strtolower(trim($moneda));

        // Mapeo de variantes a nombres estándar
        $mapeo = [
            'guaraníes' => 'guaraníes',
            'pyg' => 'guaraníes',
            'g' => 'guaraníes',
            '₲' => 'guaraníes',
            'guarani' => 'guaraníes',
            'gs' => 'guaraníes',

            'real brasileño' => 'real brasileño',
            'real' => 'real brasileño',
            'brl' => 'real brasileño',
            'r$' => 'real brasileño',
            'reales' => 'real brasileño'
        ];

        return $mapeo[$moneda] ?? $moneda;
    }

    public function obtenerTasasConversion()
    {
        $tasas = [];
        foreach ($this->tasasMoneda as $moneda => $valor) {
            $codigo = $this->obtenerCodigoMoneda($moneda);
            $tasas[$codigo . '_USD'] = [
                'tasa' => $valor,
                'descripcion' => ucfirst($moneda) . ' a Dólares',
                'simbolo_origen' => $this->obtenerSimboloMoneda($moneda),
                'simbolo_destino' => '$'
            ];
        }

        $tasas['USD_USD'] = [
            'tasa' => 1,
            'descripcion' => 'Dólares (sin conversión)',
            'simbolo_origen' => '$',
            'simbolo_destino' => '$'
        ];

        return $tasas;
    }

    private function obtenerCodigoMoneda($moneda)
    {
        $codigos = [
            'guaraníes' => 'PYG',
            'real brasileño' => 'BRL'
        ];
        return $codigos[$moneda] ?? 'USD';
    }

    /**
     * Obtener datos para dashboard (métricas principales en USD)
     * SIN FILTROS DE FECHA POR DEFECTO
     */
    public function obtenerDatosDashboard($fechaInicio = null, $fechaFin = null, $filtros = [])
    {
        try {
            // Solo validar fechas si se proporcionan
            if ($fechaInicio && $fechaFin) {
                $this->validarFechas($fechaInicio, $fechaFin);
            }

            // ✅ FIX: Usar todos los filtros, no crear array con solo vendedor
            // ANTES: $filtros = $vendedor ? ['vendedor' => $vendedor] : [];
            // AHORA: Se reciben todos los filtros como parámetro

            $datos = $this->repository->obtenerMetricasGenerales($fechaInicio, $fechaFin, $filtros);

            // Calcular comparación con período anterior solo si hay fechas
            if ($fechaInicio && $fechaFin) {
                $diasPeriodo = $this->calcularDiasPeriodo($fechaInicio, $fechaFin);
                $fechaInicioAnterior = date('Y-m-d', strtotime($fechaInicio . " -{$diasPeriodo} days"));
                $fechaFinAnterior = date('Y-m-d', strtotime($fechaInicio . " -1 day"));

                $datosAnteriores = $this->repository->obtenerMetricasGenerales($fechaInicioAnterior, $fechaFinAnterior, $filtros);

                // Calcular variaciones
                $datos['variaciones'] = $this->calcularVariaciones($datos, $datosAnteriores);
                $datos['periodo_comparacion'] = [
                    'inicio' => $fechaInicioAnterior,
                    'fin' => $fechaFinAnterior
                ];
            } else {
                // Sin comparación si no hay filtro de fechas
                $datos['variaciones'] = [];
                $datos['periodo_comparacion'] = null;
            }

            // Agregar información de conversión y filtros
            $datos['moneda_display'] = 'USD';
            $datos['nota_conversion'] = 'Todos los valores convertidos a dólares estadounidenses';
            $datos['periodo_actual'] = $fechaInicio && $fechaFin ? "$fechaInicio al $fechaFin" : 'Todas las ventas históricas';
            $datos['filtros_aplicados'] = $filtros; // ✅ Para debug

            return $datos;
        } catch (Exception $e) {
            error_log("Error en obtenerDatosDashboard: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener ventas por período (para gráficos en USD)
     * SIN FILTROS DE FECHA POR DEFECTO
     */
    public function obtenerVentasPorPeriodo($fechaInicio = null, $fechaFin = null, $agruparPor = 'dia', $filtros = [])
    {
        try {
            // Solo validar fechas si se proporcionan
            if ($fechaInicio && $fechaFin) {
                $this->validarFechas($fechaInicio, $fechaFin);
            }

            $this->validarAgrupacion($agruparPor);

            // ✅ OBTENER DATOS DEL REPOSITORY (YA COMPLETADOS CON PERÍODOS FALTANTES)
            $datos = $this->repository->obtenerVentasPorPeriodo($fechaInicio, $fechaFin, $agruparPor, $filtros);

            // Agregar metadatos de conversión
            return [
                'datos' => $datos, // Los datos ya vienen completados desde el Repository
                'moneda' => 'USD',
                'tasas_conversion' => [
                    'PYG_USD' => 7500,
                    'BRL_USD' => 5.55
                ],
                'periodo' => $fechaInicio && $fechaFin ? "$fechaInicio al $fechaFin" : 'Ventas históricas válidas',
                'nota' => 'Valores convertidos a USD - Excluye: NULL, vacío, "En revision", "Pendiente"'
            ];
        } catch (Exception $e) {
            error_log("Error en obtenerVentasPorPeriodo: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener productos más vendidos (valores en USD)
     * SIN FILTROS DE FECHA POR DEFECTO
     */
    public function obtenerProductosMasVendidos($fechaInicio = null, $fechaFin = null, $limite = 10, $filtros = [])
    {
        try {
            // Solo validar fechas si se proporcionan
            if ($fechaInicio && $fechaFin) {
                $this->validarFechas($fechaInicio, $fechaFin);
            }

            $limite = $this->validarLimite($limite);

            $productos = $this->repository->obtenerProductosMasVendidos($fechaInicio, $fechaFin, $limite, $filtros);

            // Enriquecer datos con cálculos adicionales
            return array_map(function ($producto) {
                $producto['margen_precio'] = $producto['precio_maximo'] - $producto['precio_minimo'];
                $producto['promedio_por_venta'] = $producto['ventas_asociadas'] > 0 ?
                    $producto['cantidad_vendida'] / $producto['ventas_asociadas'] : 0;
                $producto['moneda'] = 'USD';
                return $producto;
            }, $productos);
        } catch (Exception $e) {
            error_log("Error en obtenerProductosMasVendidos: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener ventas por vendedor (valores en USD)
     * SIN FILTROS DE FECHA POR DEFECTO
     */
    public function obtenerVentasPorVendedor($fechaInicio = null, $fechaFin = null, $filtros = [])
    {
        try {
            // Solo validar fechas si se proporcionan
            if ($fechaInicio && $fechaFin) {
                $this->validarFechas($fechaInicio, $fechaFin);
            }

            $vendedores = $this->repository->obtenerVentasPorVendedor($fechaInicio, $fechaFin, $filtros);

            // Enriquecer datos con rankings por cantidad
            return $this->calcularRankingVendedores($vendedores);
        } catch (Exception $e) {
            error_log("Error en obtenerVentasPorVendedor: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener ventas detalladas (valores en USD)
     * SIN FILTROS DE FECHA POR DEFECTO
     */
    public function obtenerVentasDetalladas($fechaInicio = null, $fechaFin = null, $filtros = [])
    {
        try {
            // Solo validar fechas si se proporcionan
            if ($fechaInicio && $fechaFin) {
                $this->validarFechas($fechaInicio, $fechaFin);
            }

            $ventas = $this->repository->obtenerVentasDetalladas($fechaInicio, $fechaFin, $filtros);

            // Enriquecer datos con cálculos adicionales
            return array_map(function ($venta) {
                $venta['dias_desde_venta'] = $this->calcularDiasDesde($venta['fecha_venta']);
                $venta['tipo_cliente'] = $this->clasificarTipoCliente($venta['cantidad_productos'] ?? 0);
                $venta['formato_fecha'] = $this->formatearFechaLocal($venta['fecha_venta']);
                $venta['valor_formateado'] = $this->formatearMoneda($venta['monto_total']);

                return $venta;
            }, $ventas);
        } catch (Exception $e) {
            error_log("Error en obtenerVentasDetalladas: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener productos de una venta específica (valores en USD)
     */
    public function obtenerProductosVenta($ventaId)
    {
        try {
            // Validar ID de venta
            if (!is_numeric($ventaId) || $ventaId <= 0) {
                throw new Exception('ID de venta no válido');
            }

            $productos = $this->repository->obtenerProductosVenta($ventaId);

            if (empty($productos)) {
                return [];
            }

            // Enriquecer datos con cálculos adicionales
            $productosEnriquecidos = array_map(function ($producto) {
                // Calcular información adicional
                $cantidad = (float)$producto['cantidad'];
                $precioUnitario = (float)$producto['precio_unitario'];
                $totalUsd = (float)$producto['total_usd'];

                // Validaciones y cálculos
                $producto['subtotal'] = $cantidad * $precioUnitario;
                $producto['precio_formateado'] = $this->formatearMoneda($precioUnitario);
                $producto['total_formateado'] = $this->formatearMoneda($totalUsd);
                $producto['subtotal_formateado'] = $this->formatearMoneda($producto['subtotal']);

                // Información de conversión
                $producto['precio_original_formateado'] = $this->formatearMonedaOriginal(
                    $producto['precio_original'],
                    $producto['moneda_original']
                );

                $producto['total_original_formateado'] = $this->formatearMonedaOriginal(
                    $producto['total_original'],
                    $producto['moneda_original']
                );

                // Calcular diferencia si hay discrepancia
                $diferencia = abs($totalUsd - $producto['subtotal']);
                $producto['hay_diferencia'] = $diferencia > 0.01; // Tolerancia de 1 centavo
                $producto['diferencia'] = $diferencia;

                return $producto;
            }, $productos);

            // Calcular totales generales
            $totalGeneral = array_sum(array_column($productosEnriquecidos, 'total_usd'));
            $cantidadTotal = array_sum(array_column($productosEnriquecidos, 'cantidad'));
            $promedioProducto = count($productosEnriquecidos) > 0 ? $totalGeneral / count($productosEnriquecidos) : 0;

            // Agregar información de resumen
            foreach ($productosEnriquecidos as &$producto) {
                $producto['total_general'] = $totalGeneral;
                $producto['cantidad_total'] = $cantidadTotal;
                $producto['promedio_producto'] = $promedioProducto;
                $producto['porcentaje_del_total'] = $totalGeneral > 0 ?
                    round(($producto['total_usd'] / $totalGeneral) * 100, 2) : 0;
            }

            return $productosEnriquecidos;
        } catch (Exception $e) {
            error_log("Error en obtenerProductosVenta: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Validar que la venta existe y tiene productos
     */
    public function validarVentaConProductos($ventaId)
    {
        try {
            $productos = $this->repository->obtenerProductosVenta($ventaId);
            return [
                'existe' => !empty($productos),
                'cantidad_productos' => count($productos),
                'total_productos' => count($productos) > 0 ?
                    array_sum(array_column($productos, 'cantidad')) : 0
            ];
        } catch (Exception $e) {
            error_log("Error validando venta con productos: " . $e->getMessage());
            return [
                'existe' => false,
                'cantidad_productos' => 0,
                'total_productos' => 0
            ];
        }
    }

    /**
     * Obtener vendedores
     */
    public function obtenerVendedores()
    {
        return $this->repository->obtenerVendedores();
    }

    /**
     * Obtener clientes con ventas
     */
    public function obtenerClientesConVentas()
    {
        return $this->repository->obtenerClientesConVentas();
    }

    /**
     * Obtener estados de ventas
     */
    public function obtenerEstadosVentas()
    {
        return $this->repository->obtenerEstadosVentas();
    }

    // MÉTODOS PRIVADOS DE VALIDACIÓN

    /**
     * Validar fechas del período (solo si se proporcionan)
     */
    private function validarFechas($fechaInicio, $fechaFin)
    {
        if (!$this->esDateValid($fechaInicio) || !$this->esDateValid($fechaFin)) {
            throw new Exception('Formato de fecha inválido. Use YYYY-MM-DD');
        }

        if (strtotime($fechaInicio) > strtotime($fechaFin)) {
            throw new Exception('La fecha de inicio no puede ser mayor que la fecha fin');
        }

        // Permitir fechas hasta el día de hoy (incluido)
        $hoy = date('Y-m-d');
        if (strtotime($fechaFin) > strtotime($hoy)) {
            throw new Exception('La fecha fin no puede ser posterior a hoy');
        }

        // Limitar a máximo 5 años para evitar consultas muy pesadas
        $diasDiferencia = $this->calcularDiasPeriodo($fechaInicio, $fechaFin);
        if ($diasDiferencia > 1825) { // 5 años
            throw new Exception('El período no puede ser mayor a 5 años');
        }
    }

    /**
     * Validar formato de fecha
     */
    private function esDateValid($fecha)
    {
        if (!$fecha) return false;
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        return $d && $d->format('Y-m-d') === $fecha;
    }

    /**
     * Validar tipo de agrupación
     */
    private function validarAgrupacion($tipo)
    {
        $permitidos = ['dia', 'semana', 'mes', 'año'];
        if (!in_array($tipo, $permitidos)) {
            throw new Exception('Tipo de agrupación no válido. Use: ' . implode(', ', $permitidos));
        }
    }

    /**
     * Validar límite de resultados
     */
    private function validarLimite($limite)
    {
        $limite = (int)$limite;
        if ($limite < 1 || $limite > 100) {
            throw new Exception('El límite debe estar entre 1 y 100');
        }
        return $limite;
    }

    // MÉTODOS PRIVADOS DE CÁLCULO Y PROCESAMIENTO

    /**
     * Calcular días entre fechas
     */
    private function calcularDiasPeriodo($fechaInicio, $fechaFin)
    {
        return (strtotime($fechaFin) - strtotime($fechaInicio)) / (60 * 60 * 24) + 1;
    }

    /**
     * Calcular variaciones porcentuales
     */
    private function calcularVariaciones($datosActuales, $datosAnteriores)
    {
        $variaciones = [];
        $campos = ['cantidad_ventas', 'promedio_venta', 'clientes_unicos', 'total_ventas'];

        foreach ($campos as $campo) {
            $actual = $datosActuales[$campo] ?? 0;
            $anterior = $datosAnteriores[$campo] ?? 0;

            if ($anterior > 0) {
                $variacion = (($actual - $anterior) / $anterior) * 100;
                $variaciones[$campo] = [
                    'porcentaje' => round($variacion, 2),
                    'direccion' => $variacion >= 0 ? 'up' : 'down',
                    'valor_anterior' => $anterior,
                    'diferencia_absoluta' => $actual - $anterior
                ];
            } else {
                $variaciones[$campo] = [
                    'porcentaje' => $actual > 0 ? 100 : 0,
                    'direccion' => $actual > 0 ? 'up' : 'neutral',
                    'valor_anterior' => 0,
                    'diferencia_absoluta' => $actual
                ];
            }
        }

        return $variaciones;
    }

    /**
     * Obtener distribución de ventas por moneda (valores originales y USD)
     * SIN FILTROS DE FECHA POR DEFECTO
     */
    public function obtenerDistribucionPorMoneda($fechaInicio = null, $fechaFin = null, $filtros = [])
    {
        try {
            // Solo validar fechas si se proporcionan
            if ($fechaInicio && $fechaFin) {
                $this->validarFechas($fechaInicio, $fechaFin);
            }

            $distribucion = $this->repository->obtenerDistribucionPorMoneda($fechaInicio, $fechaFin, $filtros);

            if (empty($distribucion)) {
                return [];
            }

            // Enriquecer datos con información adicional
            $distribucionEnriquecida = array_map(function ($item) {
                // Formatear valores para display
                $item['total_original_formateado'] = $this->formatearMonedaOriginal(
                    $item['total_original'],
                    $item['moneda_original']
                );

                $item['total_usd_formateado'] = $this->formatearMoneda($item['total_usd']);

                $item['promedio_original_formateado'] = $this->formatearMonedaOriginal(
                    $item['promedio_original'],
                    $item['moneda_original']
                );

                $item['promedio_usd_formateado'] = $this->formatearMoneda($item['promedio_usd']);

                // Información adicional
                $item['nombre_moneda'] = $this->obtenerNombreCompleto($item['moneda_original']);
                $item['simbolo_moneda'] = $this->obtenerSimboloMoneda($item['moneda_original']);

                // Estadísticas adicionales
                $item['ticket_promedio_ranking'] = $this->clasificarTicketPromedio($item['promedio_usd']);
                $item['participacion_categoria'] = $this->clasificarParticipacion($item['porcentaje']);

                // Información de conversión
                $item['info_conversion'] = [
                    'moneda_origen' => $item['moneda_original'],
                    'moneda_destino' => 'USD',
                    'tasa_aplicada' => $item['tasa_conversion'],
                    'formula' => "Monto ÷ {$item['tasa_conversion']} = USD"
                ];

                return $item;
            }, $distribucion);

            // Ordenar por total USD descendente
            usort($distribucionEnriquecida, function ($a, $b) {
                return $b['total_usd'] <=> $a['total_usd'];
            });

            // Agregar ranking
            foreach ($distribucionEnriquecida as $index => &$item) {
                $item['ranking'] = $index + 1;
                $item['es_principal'] = $index === 0; // La moneda con mayor participación
            }

            return $distribucionEnriquecida;
        } catch (Exception $e) {
            error_log("Error en obtenerDistribucionPorMoneda: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener nombre completo de la moneda
     */
    private function obtenerNombreCompleto($codigoMoneda)
    {
        $nombres = [
            'PYG' => 'Guaraníes Paraguayos',
            'BRL' => 'Reales Brasileños',
            'USD' => 'Dólares Estadounidenses',
            'EUR' => 'Euros',
            'ARS' => 'Pesos Argentinos'
        ];

        return $nombres[$codigoMoneda] ?? $codigoMoneda;
    }

    /**
     * Clasificar ticket promedio
     */
    private function clasificarTicketPromedio($promedioUsd)
    {
        if ($promedioUsd >= 1000) return 'Alto';
        if ($promedioUsd >= 500) return 'Medio-Alto';
        if ($promedioUsd >= 200) return 'Medio';
        if ($promedioUsd >= 50) return 'Bajo';
        return 'Muy Bajo';
    }

    /**
     * Clasificar participación en el total
     */
    private function clasificarParticipacion($porcentaje)
    {
        if ($porcentaje >= 50) return 'Dominante';
        if ($porcentaje >= 25) return 'Significativa';
        if ($porcentaje >= 10) return 'Moderada';
        if ($porcentaje >= 5) return 'Menor';
        return 'Marginal';
    }



    /**
     * Obtener estadísticas generales de la distribución
     */
    public function obtenerEstadisticasDistribucion($fechaInicio = null, $fechaFin = null, $filtros = [])
    {
        try {
            $distribucion = $this->obtenerDistribucionPorMoneda($fechaInicio, $fechaFin, $filtros);

            if (empty($distribucion)) {
                return [
                    'total_monedas' => 0,
                    'moneda_principal' => null,
                    'concentracion' => 0,
                    'diversificacion' => 'N/A'
                ];
            }

            $totalMonedas = count($distribucion);
            $monedaPrincipal = $distribucion[0];
            $concentracion = $monedaPrincipal['porcentaje'];

            // Calcular índice de diversificación
            $diversificacion = 'Baja';
            if ($totalMonedas >= 3 && $concentracion < 60) {
                $diversificacion = 'Alta';
            } elseif ($totalMonedas >= 2 && $concentracion < 80) {
                $diversificacion = 'Media';
            }

            return [
                'total_monedas' => $totalMonedas,
                'moneda_principal' => [
                    'codigo' => $monedaPrincipal['moneda_original'],
                    'nombre' => $this->obtenerNombreCompleto($monedaPrincipal['moneda_original']),
                    'participacion' => $monedaPrincipal['porcentaje']
                ],
                'concentracion' => $concentracion,
                'diversificacion' => $diversificacion,
                'total_usd' => array_sum(array_column($distribucion, 'total_usd')),
                'total_ventas' => array_sum(array_column($distribucion, 'cantidad_ventas'))
            ];
        } catch (Exception $e) {
            error_log("Error en obtenerEstadisticasDistribucion: " . $e->getMessage());
            return [
                'total_monedas' => 0,
                'moneda_principal' => null,
                'concentracion' => 0,
                'diversificacion' => 'Error'
            ];
        }
    }
    /**
     * Calcular ranking de vendedores por cantidad
     */
    private function calcularRankingVendedores($vendedores)
    {
        // Ordenar por cantidad de ventas
        usort($vendedores, function ($a, $b) {
            return $b['cantidad_ventas'] <=> $a['cantidad_ventas'];
        });

        foreach ($vendedores as $index => &$vendedor) {
            $vendedor['ranking'] = $index + 1;
            $vendedor['eficiencia'] = $vendedor['cantidad_ventas'] > 0 ?
                round($vendedor['total_ventas'] / $vendedor['cantidad_ventas'], 2) : 0;
            $vendedor['moneda'] = 'USD';
        }

        return $vendedores;
    }

    /**
     * Obtener distribución de kilos por vendedor
     */
    public function obtenerDistribucionKilosPorVendedor($fechaInicio = null, $fechaFin = null, $filtros = [])
    {
        try {
            if ($fechaInicio && $fechaFin) {
                $this->validarFechas($fechaInicio, $fechaFin);
            }

            return $this->repository->obtenerDistribucionKilosPorVendedor($fechaInicio, $fechaFin, $filtros);
        } catch (Exception $e) {
            error_log("Error en obtenerDistribucionKilosPorVendedor: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener distribución crédito vs contado
     */
    public function obtenerDistribucionCredito($fechaInicio = null, $fechaFin = null, $filtros = [])
    {
        try {
            if ($fechaInicio && $fechaFin) {
                $this->validarFechas($fechaInicio, $fechaFin);
            }

            return $this->repository->obtenerDistribucionCredito($fechaInicio, $fechaFin, $filtros);
        } catch (Exception $e) {
            error_log("Error en obtenerDistribucionCredito: " . $e->getMessage());
            throw $e;
        }
    }
    /**
     * Calcular días desde una fecha
     */
    private function calcularDiasDesde($fecha)
    {
        // Para campos DATE, calcular directamente
        $fechaVenta = new DateTime($fecha);
        $fechaActual = new DateTime();
        return $fechaVenta->diff($fechaActual)->days;
    }
    /**
     * Clasificar tipo de cliente por cantidad de productos
     */
    private function clasificarTipoCliente($cantidadProductos)
    {
        if ($cantidadProductos >= 10) return 'Mayorista';
        if ($cantidadProductos >= 5) return 'Frecuente';
        if ($cantidadProductos >= 2) return 'Regular';
        return 'Minorista';
    }

    /**
     * Formatear fecha local
     */
    private function formatearFechaLocal($fecha)
    {
        // Para campos DATE, formatear directamente sin conversiones de zona horaria
        return date('d/m/Y', strtotime($fecha));
    }

    /**
     * Formatear moneda original con su símbolo correspondiente
     */
    private function formatearMonedaOriginal($valor, $moneda)
    {
        $simbolo = $this->obtenerSimboloMoneda($moneda);
        return $simbolo . ' ' . $this->formatearNumero($valor, 2);
    }

    /**
     * Formatear número para mostrar
     */
    public function formatearNumero($numero, $decimales = 2)
    {
        if (!is_numeric($numero)) return '0';
        return number_format((float)$numero, $decimales, ',', '.');
    }

    /**
     * Formatear moneda en USD
     */
    public function formatearMoneda($numero)
    {
        return '$ ' . $this->formatearNumero($numero, 2);
    }


    /**
     * Obtener símbolo de moneda original
     */
    public function obtenerSimboloMoneda($moneda)
    {
        switch (strtolower(trim($moneda))) {
            case 'guaraníes':
            case 'pyg':
            case 'g':
            case 'guarani':
            case 'gs':
                return '₲';

            case 'real':
            case 'brl':
            case 'real brasileño':
            case 'reales':
                return 'R$';

            case 'dólares':
            case 'usd':
            case 'dolares':
            case 'dollar':
            default:
                return '$';
        }
    }
}
