<?php

/**
 * Service para lógica de negocio del stock agregado
 * Versión optimizada con soporte completo para stock completo
 */
class StockServices
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    /**
     * Obtener stock agregado paginado con información enriquecida
     * @param string $filtroProducto Filtro por nombre de producto
     * @param int $pagina Número de página
     * @param int $registrosPorPagina Registros por página
     * @param string $filtroTipo Filtro por tipo de producto
     * @param bool $stockCompleto Si true, muestra todos los productos incluyendo sin stock
     */
    public function obtenerStockPaginado($filtroProducto = '', $pagina = 1, $registrosPorPagina = 20, $filtroTipo = '', $stockCompleto = false)
    {
        // Validar parámetros
        $this->validarParametrosPaginacion($pagina, $registrosPorPagina);

        $offset = ($pagina - 1) * $registrosPorPagina;

        // Obtener datos del repositorio con el parámetro stockCompleto
        $datos = $this->repository->obtenerStockAgregadoPaginado($filtroProducto, $filtroTipo, $registrosPorPagina, $offset, $stockCompleto);
        $totalRegistros = $this->repository->contarTotalStockAgregado($filtroProducto, $filtroTipo, $stockCompleto);

        // Enriquecer datos
        $datosEnriquecidos = array_map([$this, 'enriquecerDatosStockAgregado'], $datos);

        // Calcular información de paginación
        $totalPaginas = ceil($totalRegistros / $registrosPorPagina);

        return [
            'datos' => $datosEnriquecidos,
            'paginacion' => [
                'pagina_actual' => $pagina,
                'registros_por_pagina' => $registrosPorPagina,
                'total_registros' => $totalRegistros,
                'total_paginas' => $totalPaginas,
                'hay_pagina_anterior' => $pagina > 1,
                'hay_pagina_siguiente' => $pagina < $totalPaginas,
                'pagina_anterior' => max(1, $pagina - 1),
                'pagina_siguiente' => min($totalPaginas, $pagina + 1)
            ],
            'estadisticas' => $this->calcularEstadisticasPagina($datosEnriquecidos),
            'filtros_aplicados' => [
                'producto' => $filtroProducto,
                'tipo' => $filtroTipo,
                'stock_completo' => $stockCompleto
            ]
        ];
    }

    /**
     * Enriquecer datos de stock agregado con información calculada
     */
    private function enriquecerDatosStockAgregado($item)
    {
        // Formatear números para visualización
        $item['cantidad_total_formateada'] = number_format($item['cantidad_total'] ?? 0, 0, ',', '.');
        $item['cantidad_disponible_formateada'] = number_format($item['cantidad_disponible'] ?? 0, 0, ',', '.');
        $item['cantidad_reservada_formateada'] = number_format($item['cantidad_reservada'] ?? 0, 0, ',', '.');
        $item['cantidad_despachada_formateada'] = number_format($item['cantidad_despachada'] ?? 0, 0, ',', '.');
        $item['cantidad_paquetes_formateada'] = number_format($item['cantidad_paquetes'] ?? 0, 0, ',', '.');

        // Calcular porcentajes
        if (($item['cantidad_total'] ?? 0) > 0) {
            $item['porcentaje_disponible'] = round(($item['cantidad_disponible'] / $item['cantidad_total']) * 100, 1);
            $item['porcentaje_reservado'] = round(($item['cantidad_reservada'] / $item['cantidad_total']) * 100, 1);
            $item['porcentaje_despachado'] = round(($item['cantidad_despachada'] / $item['cantidad_total']) * 100, 1);
        } else {
            $item['porcentaje_disponible'] = 0;
            $item['porcentaje_reservado'] = 0;
            $item['porcentaje_despachado'] = 0;
        }

        // Configurar tipo de producto
        $item['configuracion_tipo'] = $this->configurarTipoProducto($item['tipo_producto']);

        // Información de bobinas/paquete
        $item['bobinas_pacote_formateado'] = $item['bobinas_pacote'] ?? 1;

        // Estado del stock mejorado
        $item['estado_stock_detallado'] = $this->determinarEstadoStock($item);

        return $item;
    }

    /**
     * Determinar estado detallado del stock
     */
    private function determinarEstadoStock($item)
    {
        $cantidad = $item['cantidad_disponible'] ?? 0;

        if ($cantidad === 0) {
            return [
                'estado' => 'sin_stock',
                'clase_css' => 'text-danger',
                'icono' => 'fas fa-times-circle',
                'descripcion' => 'Sin stock'
            ];
        } elseif ($cantidad <= 2) {
            return [
                'estado' => 'critico',
                'clase_css' => 'text-warning',
                'icono' => 'fas fa-exclamation-triangle',
                'descripcion' => 'Stock crítico'
            ];
        } elseif ($cantidad <= 5) {
            return [
                'estado' => 'bajo',
                'clase_css' => 'text-warning',
                'icono' => 'fas fa-exclamation-circle',
                'descripcion' => 'Stock bajo'
            ];
        } else {
            return [
                'estado' => 'normal',
                'clase_css' => 'text-success',
                'icono' => 'fas fa-check-circle',
                'descripcion' => 'Stock normal'
            ];
        }
    }

    /**
     * Configurar visualización del tipo de producto
     */
    private function configurarTipoProducto($tipoProducto)
    {
        $configuraciones = [
            'TOALLITAS' => [
                'color' => '#3b82f6',
                'icono' => 'fas fa-tissue',
                'descripcion' => 'Toallitas húmedas'
            ],
            'TNT' => [
                'color' => '#6366f1',
                'icono' => 'fas fa-industry',
                'descripcion' => 'Tejido no tejido'
            ],
            'SPUNLACE' => [
                'color' => '#8b5cf6',
                'icono' => 'fas fa-layer-group',
                'descripcion' => 'Spunlace'
            ],
            'LAMINADO' => [
                'color' => '#06b6d4',
                'icono' => 'fas fa-layers',
                'descripcion' => 'Material laminado'
            ]
        ];

        $tipoUpper = strtoupper($tipoProducto ?? '');
        return $configuraciones[$tipoUpper] ?? [
            'color' => '#6b7280',
            'icono' => 'fas fa-box',
            'descripcion' => 'Producto genérico'
        ];
    }

    /**
     * Calcular estadísticas de la página actual
     */
    private function calcularEstadisticasPagina($datos)
    {
        if (empty($datos)) {
            return [
                'total_productos' => 0,
                'disponible_total' => 0,
                'reservado_total' => 0,
                'despachado_total' => 0,
                'productos_sin_stock' => 0,
                'productos_criticos' => 0,
                'productos_bajo_stock' => 0,
                'productos_normales' => 0
            ];
        }

        $totalProductos = count($datos);
        $disponibleTotal = array_sum(array_column($datos, 'cantidad_disponible'));
        $reservadoTotal = array_sum(array_column($datos, 'cantidad_reservada'));
        $despachadoTotal = array_sum(array_column($datos, 'cantidad_despachada'));

        $productosSinStock = 0;
        $productosCriticos = 0;
        $productosBajoStock = 0;
        $productosNormales = 0;

        foreach ($datos as $item) {
            $cantidad = $item['cantidad_disponible'] ?? 0;
            if ($cantidad === 0) {
                $productosSinStock++;
            } elseif ($cantidad <= 2) {
                $productosCriticos++;
            } elseif ($cantidad <= 5) {
                $productosBajoStock++;
            } else {
                $productosNormales++;
            }
        }

        return [
            'total_productos' => $totalProductos,
            'disponible_total' => $disponibleTotal,
            'reservado_total' => $reservadoTotal,
            'despachado_total' => $despachadoTotal,
            'productos_sin_stock' => $productosSinStock,
            'productos_criticos' => $productosCriticos,
            'productos_bajo_stock' => $productosBajoStock,
            'productos_normales' => $productosNormales,
            'disponible_total_formateado' => number_format($disponibleTotal, 0, ',', '.'),
            'reservado_total_formateado' => number_format($reservadoTotal, 0, ',', '.'),
            'despachado_total_formateado' => number_format($despachadoTotal, 0, ',', '.')
        ];
    }

    /**
     * Obtener estadísticas generales del sistema
     */
    public function obtenerEstadisticasGenerales()
    {
        $estadisticas = $this->repository->obtenerEstadisticasStockAgregado();
        $productosPorTipo = $this->repository->obtenerProductosPorTipoAgregado();
        $productosBajoStock = $this->repository->obtenerProductosBajoStockAgregado();
        $productosSinStock = $this->repository->obtenerProductosSinStockAgregado();

        // Enriquecer estadísticas
        if ($estadisticas) {
            // Formatear números
            $estadisticas['total_items_formateado'] = number_format($estadisticas['total_items'] ?? 0, 0, ',', '.');
            $estadisticas['disponible_total_formateado'] = number_format($estadisticas['disponible_total'] ?? 0, 0, ',', '.');
            $estadisticas['reservado_total_formateado'] = number_format($estadisticas['reservado_total'] ?? 0, 0, ',', '.');
            $estadisticas['despachado_total_formateado'] = number_format($estadisticas['despachado_total'] ?? 0, 0, ',', '.');

            // Calcular porcentajes
            if (($estadisticas['total_items'] ?? 0) > 0) {
                $estadisticas['porcentaje_disponible'] = round(($estadisticas['disponible_total'] / $estadisticas['total_items']) * 100, 1);
                $estadisticas['porcentaje_reservado'] = round(($estadisticas['reservado_total'] / $estadisticas['total_items']) * 100, 1);
                $estadisticas['porcentaje_despachado'] = round(($estadisticas['despachado_total'] / $estadisticas['total_items']) * 100, 1);
            } else {
                $estadisticas['porcentaje_disponible'] = 0;
                $estadisticas['porcentaje_reservado'] = 0;
                $estadisticas['porcentaje_despachado'] = 0;
            }

            // Calcular salud del inventario
            $estadisticas['salud_inventario'] = $this->calcularSaludInventario($estadisticas);
        }

        return [
            'generales' => $estadisticas ?: [],
            'por_tipo' => $productosPorTipo,
            'bajo_stock' => $productosBajoStock,
            'sin_stock' => $productosSinStock,
            'resumen_tipos' => $this->procesarResumenTipos($productosPorTipo)
        ];
    }

    /**
     * Calcular salud del inventario
     */
    private function calcularSaludInventario($estadisticas)
    {
        $totalProductos = $estadisticas['total_productos'] ?? 0;
        $productosSinStock = $estadisticas['productos_sin_stock'] ?? 0;
        $productosCriticos = $estadisticas['productos_criticos'] ?? 0;

        if ($totalProductos === 0) {
            return [
                'puntuacion' => 0,
                'estado' => 'sin_datos',
                'descripcion' => 'Sin datos disponibles'
            ];
        }

        $porcentajeSinStock = ($productosSinStock / $totalProductos) * 100;
        $porcentajeCriticos = ($productosCriticos / $totalProductos) * 100;

        $puntuacion = 100 - ($porcentajeSinStock * 2) - ($porcentajeCriticos * 1.5);
        $puntuacion = max(0, min(100, $puntuacion));

        if ($puntuacion >= 90) {
            $estado = 'excelente';
            $descripcion = 'Inventario en excelente estado';
        } elseif ($puntuacion >= 75) {
            $estado = 'bueno';
            $descripcion = 'Inventario en buen estado';
        } elseif ($puntuacion >= 50) {
            $estado = 'regular';
            $descripcion = 'Inventario requiere atención';
        } else {
            $estado = 'critico';
            $descripcion = 'Inventario en estado crítico';
        }

        return [
            'puntuacion' => round($puntuacion, 1),
            'estado' => $estado,
            'descripcion' => $descripcion
        ];
    }

    /**
     * Procesar resumen de tipos
     */
    private function procesarResumenTipos($productosPorTipo)
    {
        $resumen = [];
        foreach ($productosPorTipo as $tipo) {
            $resumen[] = [
                'tipo' => $tipo['tipo_producto'],
                'total_variantes' => $tipo['total_variantes'],
                'disponible_total' => $tipo['disponible_total'],
                'configuracion' => $this->configurarTipoProducto($tipo['tipo_producto'])
            ];
        }
        return $resumen;
    }

    /**
     * Buscar productos para autocompletado
     */
    public function buscarProductosAutocompletado($termino)
    {
        $this->validarTerminoBusqueda($termino);

        $productos = $this->repository->buscarProductosAutocompletadoAgregado($termino);

        // Enriquecer con información adicional
        return array_map(function ($producto) {
            return [
                'nombre' => $producto['nombre_producto'],
                'tipo' => $producto['tipo_producto'],
                'cantidad_disponible' => $producto['cantidad_disponible_total'],
                'variantes' => $producto['variantes'],
                'configuracion_tipo' => $this->configurarTipoProducto($producto['tipo_producto']),
                'texto_completo' => $producto['nombre_producto'] . ' (' . $producto['tipo_producto'] . ') - ' .
                    number_format($producto['cantidad_disponible_total']) . ' disponibles, ' .
                    $producto['variantes'] . ' variante(s)'
            ];
        }, $productos);
    }

    /**
     * Obtener tipos de producto disponibles
     */
    public function obtenerTiposProducto()
    {
        return $this->repository->obtenerTiposProductoAgregado();
    }

    /**
     * Obtener stock completo para exportación
     * @param string $filtroProducto Filtro por producto
     * @param string $filtroTipo Filtro por tipo
     * @param bool $stockCompleto Incluir productos sin stock
     */
    public function obtenerStockCompleto($filtroProducto = '', $filtroTipo = '', $stockCompleto = false)
    {
        $datos = $this->repository->obtenerStockCompletoAgregado($filtroProducto, $filtroTipo, $stockCompleto);

        // Enriquecer datos para exportación
        return array_map([$this, 'enriquecerDatosStockAgregado'], $datos);
    }

    /**
     * Obtener detalles de un producto específico
     */
    public function obtenerDetallesProducto($nombreProducto, $bobinasPacote = null, $tipoProducto = null)
    {
        $detalles = $this->repository->obtenerDetallesProductoAgregado($nombreProducto, $bobinasPacote, $tipoProducto);

        // Enriquecer cada variante
        $detallesEnriquecidos = array_map([$this, 'enriquecerDatosStockAgregado'], $detalles);

        // Calcular resumen del producto
        $resumen = $this->calcularResumenProducto($detallesEnriquecidos);

        return [
            'variantes' => $detallesEnriquecidos,
            'resumen' => $resumen,
            'total_variantes' => count($detallesEnriquecidos)
        ];
    }

    /**
     * Calcular resumen de un producto
     */
    private function calcularResumenProducto($variantes)
    {
        if (empty($variantes)) {
            return [];
        }

        $totalDisponible = array_sum(array_column($variantes, 'cantidad_disponible'));
        $totalReservado = array_sum(array_column($variantes, 'cantidad_reservada'));
        $totalDespachado = array_sum(array_column($variantes, 'cantidad_despachada'));
        $totalGeneral = array_sum(array_column($variantes, 'cantidad_total'));

        return [
            'total_variantes' => count($variantes),
            'total_disponible' => $totalDisponible,
            'total_reservado' => $totalReservado,
            'total_despachado' => $totalDespachado,
            'total_general' => $totalGeneral,
            'total_disponible_formateado' => number_format($totalDisponible, 0, ',', '.'),
            'total_reservado_formateado' => number_format($totalReservado, 0, ',', '.'),
            'total_despachado_formateado' => number_format($totalDespachado, 0, ',', '.'),
            'total_general_formateado' => number_format($totalGeneral, 0, ',', '.')
        ];
    }

    /**
     * Obtener productos que requieren reposición
     */
    public function obtenerProductosReposicion($umbral = 5)
    {
        $productos = $this->repository->obtenerProductosParaReposicion($umbral);

        return array_map(function ($producto) {
            $producto['configuracion_tipo'] = $this->configurarTipoProducto($producto['tipo_producto']);
            $producto['estado_detallado'] = $this->determinarEstadoStock($producto);
            return $producto;
        }, $productos);
    }

    /**
     * Generar reporte de inventario
     */
    public function generarReporteInventario($filtroTipo = '', $incluirSinStock = false)
    {
        $estadisticas = $this->obtenerEstadisticasGenerales();
        $productosReposicion = $this->obtenerProductosReposicion();

        // Obtener datos según filtros
        $datosCompletos = $this->repository->obtenerStockCompletoAgregado('', $filtroTipo, $incluirSinStock);
        $datosEnriquecidos = array_map([$this, 'enriquecerDatosStockAgregado'], $datosCompletos);

        return [
            'estadisticas_generales' => $estadisticas,
            'productos' => $datosEnriquecidos,
            'productos_reposicion' => $productosReposicion,
            'total_productos' => count($datosEnriquecidos),
            'fecha_reporte' => date('Y-m-d H:i:s'),
            'filtros_aplicados' => [
                'tipo' => $filtroTipo,
                'incluir_sin_stock' => $incluirSinStock
            ]
        ];
    }

    /**
     * Validar parámetros de paginación
     */
    private function validarParametrosPaginacion($pagina, $registrosPorPagina)
    {
        if (!is_numeric($pagina) || $pagina < 1) {
            throw new Exception('Número de página inválido');
        }

        if (!is_numeric($registrosPorPagina) || $registrosPorPagina < 1 || $registrosPorPagina > 100) {
            throw new Exception('Número de registros por página inválido');
        }
    }

    /**
     * Validar término de búsqueda
     */
    private function validarTerminoBusqueda($termino)
    {
        if (strlen(trim($termino)) < 2) {
            throw new Exception('El término de búsqueda debe tener al menos 2 caracteres');
        }

        if (strlen(trim($termino)) > 100) {
            throw new Exception('El término de búsqueda es demasiado largo');
        }
    }

    /**
     * Obtener métricas de rendimiento del inventario
     */
    public function obtenerMetricasRendimiento()
    {
        $estadisticas = $this->repository->obtenerEstadisticasComparativas();

        if (!$estadisticas) {
            return [];
        }

        return [
            'eficiencia_stock' => [
                'productos_activos' => $estadisticas['productos_con_stock'],
                'productos_inactivos' => $estadisticas['productos_sin_stock'],
                'porcentaje_activos' => $estadisticas['porcentaje_con_stock'],
                'porcentaje_inactivos' => 100 - $estadisticas['porcentaje_con_stock']
            ],
            'distribucion_stock' => [
                'disponible' => $estadisticas['suma_disponible_sistema'],
                'reservado' => $estadisticas['suma_reservada_sistema'],
                'despachado' => $estadisticas['suma_despachada_sistema'],
                'total' => $estadisticas['suma_total_sistema']
            ],
            'salud_general' => $this->calcularSaludInventario($estadisticas)
        ];
    }
}
