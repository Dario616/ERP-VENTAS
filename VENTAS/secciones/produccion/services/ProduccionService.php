<?php

class ProduccionService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function obtenerProductosConStock($filtros = [])
    {
        $resultado = $this->repository->obtenerProductosConStock($filtros);

        $productosEnriquecidos = [];
        foreach ($resultado['productos'] as $producto) {
            $productoEnriquecido = $this->enriquecerProductoConStock($producto);
            $productosEnriquecidos[] = $productoEnriquecido;
        }

        return [
            'productos' => $productosEnriquecidos,
            'total_registros' => $resultado['total_registros'],
            'total_paginas' => $resultado['total_paginas']
        ];
    }

    private function enriquecerProductoConStock($producto)
    {
        $tipoProducto = strtoupper(trim($producto['tipoproducto'] ?? ''));

        if (empty($tipoProducto) || $tipoProducto === 'OTRO') {
            if (!empty($producto['pa_cantidad_total']) || !empty($producto['id_orden'])) {
                if (stripos($producto['producto_descripcion'] ?? '', 'paño') !== false) {
                    $tipoProducto = 'PAÑOS';
                }
            }
        }

        $cantidadAsignada = (float)$producto['cantidad_asignada'];
        $stockReal = 0;

        if (!empty($producto['id_orden'])) {
            $stockReal = $this->calcularStockReal($producto['id_orden'], $tipoProducto);
        }

        $porcentajeAvance = $cantidadAsignada > 0 ? ($stockReal / $cantidadAsignada) * 100 : 0;

        $estado = $this->determinarEstadoProduccion($porcentajeAvance);

        $producto['stock_real'] = $stockReal;
        $producto['porcentaje_avance'] = round($porcentajeAvance, 2);
        $producto['estado_produccion'] = $estado;
        $producto['tipo_simplificado'] = $this->obtenerTipoSimplificado($tipoProducto);
        $producto['unidad_stock'] = $this->obtenerUnidadStock($tipoProducto);
        $producto['clase_badge'] = $this->obtenerClaseBadge($tipoProducto);
        $producto['icono_tipo'] = $this->obtenerIconoTipo($tipoProducto);

        return $producto;
    }

    private function calcularStockReal($idOrden, $tipoProducto)
    {
        try {
            return $this->repository->obtenerStockReal($idOrden, $tipoProducto);
        } catch (Exception $e) {
            error_log("Error calculando stock real: " . $e->getMessage());
            return 0;
        }
    }

    public function obtenerStockReal($idOrden)
    {
        try {
            $detalles = $this->repository->obtenerDetallesOrden($idOrden);
            if (!$detalles) {
                throw new Exception('Orden no encontrada');
            }

            $tipoProducto = $detalles['tipo_detectado'];
            return $this->repository->obtenerStockReal($idOrden, $tipoProducto);
        } catch (Exception $e) {
            error_log("Error obteniendo stock real: " . $e->getMessage());
            throw new Exception('Error al obtener stock real');
        }
    }

    public function obtenerOrdenesProduccion($filtros = [])
    {
        $resultado = $this->repository->obtenerOrdenesProduccion($filtros);

        $ordenesEnriquecidas = [];
        foreach ($resultado['ordenes'] as $orden) {
            $ordenEnriquecida = $this->enriquecerOrden($orden);
            $ordenesEnriquecidas[] = $ordenEnriquecida;
        }

        $tiposProductos = $this->repository->obtenerTiposProductos();

        return [
            'ordenes' => $ordenesEnriquecidas,
            'total_registros' => $resultado['total_registros'],
            'total_paginas' => $resultado['total_paginas'],
            'tipos_productos' => $tiposProductos
        ];
    }

    private function enriquecerOrden($orden)
    {
        $tipoProducto = strtoupper(trim($orden['tipo_producto'] ?? ''));

        $orden['tipo_simplificado'] = $this->obtenerTipoSimplificado($tipoProducto);
        $orden['clase_badge'] = $this->obtenerClaseBadge($tipoProducto);
        $orden['icono_tipo'] = $this->obtenerIconoTipo($tipoProducto);
        $orden['archivo_pdf'] = $this->obtenerArchivoPDF($tipoProducto);
        $orden['cantidad_formateada'] = number_format($orden['cantidad_total'], 2);

        // Enriquecer información de recetas
        $orden['tiene_receta'] = (bool)$orden['tiene_receta'];
        $orden['cantidad_recetas'] = (int)$orden['cantidad_recetas'];
        $orden['clase_badge_receta'] = $orden['tiene_receta'] ? 'bg-success' : 'bg-warning';
        $orden['icono_receta'] = $orden['tiene_receta'] ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
        $orden['texto_receta'] = $orden['tiene_receta'] ?
            ($orden['cantidad_recetas'] > 1 ?
                $orden['cantidad_recetas'] . ' recetas' :
                '1 receta'
            ) :
            'Sin receta';

        // Información sobre estados de recetas
        if ($orden['tiene_receta'] && !empty($orden['estados_recetas'])) {
            $estados = explode(', ', $orden['estados_recetas']);
            $orden['estados_recetas_array'] = array_unique($estados);

            // Determinar estado general de recetas
            if (in_array('Completado', $estados)) {
                $orden['estado_general_recetas'] = 'Completado';
                $orden['clase_estado_recetas'] = 'bg-success';
            } elseif (in_array('En Proceso', $estados)) {
                $orden['estado_general_recetas'] = 'En Proceso';
                $orden['clase_estado_recetas'] = 'bg-info';
            } else {
                $orden['estado_general_recetas'] = 'Pendiente';
                $orden['clase_estado_recetas'] = 'bg-warning';
            }
        } else {
            $orden['estados_recetas_array'] = [];
            $orden['estado_general_recetas'] = 'Sin receta';
            $orden['clase_estado_recetas'] = 'bg-secondary';
        }

        return $orden;
    }

    public function obtenerDetallesOrden($idOrden)
    {
        $orden = $this->repository->obtenerDetallesOrden($idOrden);

        if (!$orden) {
            throw new Exception('Orden no encontrada');
        }

        $orden = $this->enriquecerDetallesOrden($orden);

        return $orden;
    }

    private function enriquecerDetallesOrden($orden)
    {
        $tipoProducto = $orden['tipo_detectado'];

        $orden['tipo_simplificado'] = $this->obtenerTipoSimplificado($tipoProducto);
        $orden['clase_badge'] = $this->obtenerClaseBadge($tipoProducto);
        $orden['icono_tipo'] = $this->obtenerIconoTipo($tipoProducto);
        $orden['simbolo_moneda'] = $orden['moneda'] === 'Dólares' ? 'U$D ' : '₲ ';

        $orden['datos_especificos'] = $this->prepararDatosEspecificos($orden);

        $orden['stock_real'] = $this->calcularStockReal($orden['id'], $tipoProducto);

        if ($orden['cantidad_total'] > 0) {
            $orden['porcentaje_completado'] = round(($orden['stock_real'] / $orden['cantidad_total']) * 100, 2);
        } else {
            $orden['porcentaje_completado'] = 0;
        }

        // Enriquecer información de recetas
        $orden['tiene_receta'] = (bool)$orden['tiene_receta'];
        $orden['cantidad_recetas'] = (int)$orden['cantidad_recetas'];

        // Obtener detalles completos de las recetas
        if ($orden['tiene_receta']) {
            $orden['recetas_detalles'] = $this->repository->obtenerRecetasOrden($orden['id']);
        } else {
            $orden['recetas_detalles'] = [];
        }

        return $orden;
    }

    private function prepararDatosEspecificos($orden)
    {
        $tipo = $orden['tipo_detectado'];
        $datos = null;

        switch ($tipo) {
            case 'TNT':
                $datos = [
                    'gramatura' => $orden['tnt_gramatura'],
                    'largura' => $orden['tnt_largura'],
                    'longitud' => $orden['tnt_longitud'],
                    'color' => $orden['tnt_color'],
                    'peso_bobina' => $orden['tnt_peso_bobina'],
                    'total_bobinas' => $orden['tnt_total_bobinas']
                ];
                break;

            case 'SPUNLACE':
                $datos = [
                    'gramatura' => $orden['spun_gramatura'],
                    'largura' => $orden['spun_largura'],
                    'longitud' => $orden['spun_longitud'],
                    'color' => $orden['spun_color'],
                    'acabado' => $orden['spun_acabado'],
                    'peso_bobina' => $orden['spun_peso_bobina'],
                    'total_bobinas' => $orden['spun_total_bobinas']
                ];
                break;

            case 'TOALLITAS':
                $datos = [
                    'nombre' => $orden['toal_nombre']
                ];
                break;

            case 'PAÑOS':
            case 'PANOS':
                $datos = [
                    'nombre' => $orden['pa_nombre'],
                    'color' => $orden['pa_color'],
                    'largura' => $orden['pa_largura'],
                    'picotado' => $orden['pa_picotado'],
                    'cant_panos' => $orden['pa_cant_panos'],
                    'unidad' => $orden['pa_unidad'],
                    'peso' => $orden['pa_peso']
                ];
                break;
        }

        return $datos;
    }

    public function obtenerTiposProductos()
    {
        try {
            return $this->repository->obtenerTiposProductos();
        } catch (Exception $e) {
            error_log("Error obteniendo tipos de productos: " . $e->getMessage());
            return [];
        }
    }

    private function determinarEstadoProduccion($porcentaje)
    {
        if ($porcentaje >= 100) {
            return 'completado';
        } elseif ($porcentaje > 0) {
            return 'en_proceso';
        } else {
            return 'pendiente';
        }
    }

    private function obtenerTipoSimplificado($tipoProducto)
    {
        $tipo = strtoupper(trim($tipoProducto ?? ''));

        $tipo = str_replace(['Ñ', 'ñ'], 'N', $tipo);

        switch ($tipo) {
            case 'TNT':
                return 'TNT';
            case 'SPUNLACE':
                return 'Spunlace';
            case 'LAMINADORA':
                return 'Laminadora';
            case 'TOALLITAS':
                return 'Toallitas';
            case 'PANOS':
            case 'PAÑOS':
                return 'Paños';
            default:
                error_log("Tipo de producto no reconocido: '" . $tipoProducto . "' (normalizado: '" . $tipo . "')");
                return 'Otro';
        }
    }

    private function obtenerUnidadStock($tipoProducto)
    {
        $tipo = strtoupper(trim($tipoProducto ?? ''));

        $tipo = str_replace(['Ñ', 'ñ'], 'N', $tipo);

        switch ($tipo) {
            case 'TNT':
            case 'SPUNLACE':
            case 'LAMINADORA':
            case 'PANOS':
            case 'PAÑOS':
                return 'kg';
            case 'TOALLITAS':
                return 'unidades';
            default:
                return 'kg';
        }
    }

    private function obtenerClaseBadge($tipoProducto)
    {
        $tipo = strtoupper(trim($tipoProducto ?? ''));

        $tipo = str_replace(['Ñ', 'ñ'], 'N', $tipo);

        switch ($tipo) {
            case 'TNT':
                return 'bg-primary';
            case 'SPUNLACE':
                return 'bg-purple';
            case 'LAMINADORA':
                return 'bg-info';
            case 'TOALLITAS':
                return 'bg-success';
            case 'PANOS':
            case 'PAÑOS':
                return 'bg-warning';
            default:
                return 'bg-secondary';
        }
    }

    private function obtenerIconoTipo($tipoProducto)
    {
        $tipo = strtoupper(trim($tipoProducto ?? ''));

        $tipo = str_replace(['Ñ', 'ñ'], 'N', $tipo);

        switch ($tipo) {
            case 'TNT':
                return 'fas fa-scroll';
            case 'SPUNLACE':
                return 'fas fa-swatchbook';
            case 'LAMINADORA':
                return 'fas fa-layer-group';
            case 'TOALLITAS':
                return 'fas fa-soap';
            case 'PANOS':
            case 'PAÑOS':
                return 'fas fa-tshirt';
            default:
                return 'fas fa-box';
        }
    }

    private function obtenerArchivoPDF($tipoProducto)
    {
        $tipo = strtoupper(trim($tipoProducto ?? ''));

        $tipo = str_replace(['Ñ', 'ñ'], 'N', $tipo);

        switch ($tipo) {
            case 'SPUNLACE':
                return 'produccion_spunlace.php';
            case 'TOALLITAS':
                return 'producciontoallitas.php';
            case 'LAMINADORA':
                return 'produccion.php';
            case 'PANOS':
            case 'PAÑOS':
                return 'produccionpanos.php';
            case 'TNT':
            default:
                return 'produccion.php';
        }
    }

    public function validarFiltros($filtros)
    {
        $errores = [];

        if (!empty($filtros['fecha_desde']) && !empty($filtros['fecha_hasta'])) {
            $fechaDesde = strtotime($filtros['fecha_desde']);
            $fechaHasta = strtotime($filtros['fecha_hasta']);

            if ($fechaDesde > $fechaHasta) {
                $errores[] = 'La fecha desde no puede ser mayor que la fecha hasta';
            }
        }
        if (!empty($filtros['tipo_producto'])) {
            $tiposValidos = ['TNT', 'SPUNLACE', 'LAMINADORA', 'TOALLITAS', 'PAÑOS', 'PANOS'];
            $tipoNormalizado = str_replace(['Ñ', 'ñ'], 'N', strtoupper($filtros['tipo_producto']));

            if (!in_array($tipoNormalizado, $tiposValidos) && !in_array(strtoupper($filtros['tipo_producto']), $tiposValidos)) {
                $errores[] = 'Tipo de producto no válido';
            }
        }

        return $errores;
    }
}
