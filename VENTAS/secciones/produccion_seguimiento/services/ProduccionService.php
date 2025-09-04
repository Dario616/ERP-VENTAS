<?php

class ProduccionService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function obtenerProduccionAgrupada($filtros = [])
    {
        $grupos = $this->repository->obtenerProduccionAgrupada($filtros);

        return array_map([$this, 'enriquecerDatosGrupo'], $grupos);
    }

    public function obtenerDetallesGrupo($nombreProducto, $tipoProducto, $metragem, $largura, $gramatura)
    {
        if (empty($nombreProducto) || empty($tipoProducto)) {
            throw new Exception('Parámetros insuficientes para obtener detalles del grupo');
        }

        $items = $this->repository->obtenerDetallesGrupo($nombreProducto, $tipoProducto, $metragem, $largura, $gramatura);

        return array_map([$this, 'enriquecerDatosItem'], $items);
    }


    public function obtenerEstadisticas()
    {
        $estadisticas = $this->repository->obtenerEstadisticas();

        if (!empty($estadisticas)) {
            $estadisticas['peso_bruto_formateado'] = $this->formatearPeso($estadisticas['peso_bruto_total']);
            $estadisticas['peso_liquido_formateado'] = $this->formatearPeso($estadisticas['peso_liquido_total']);
            $estadisticas['fecha_primera_formateada'] = $this->formatearFecha($estadisticas['fecha_primera_produccion']);
            $estadisticas['fecha_ultima_formateada'] = $this->formatearFecha($estadisticas['fecha_ultima_produccion']);
            if ($estadisticas['fecha_primera_produccion'] && $estadisticas['fecha_ultima_produccion']) {
                $fecha1 = new DateTime($estadisticas['fecha_primera_produccion']);
                $fecha2 = new DateTime($estadisticas['fecha_ultima_produccion']);
                $estadisticas['dias_produccion'] = $fecha1->diff($fecha2)->days + 1;
            }
        }

        return $estadisticas ?: [];
    }

    public function buscarProductos($termino, $limite = 10)
    {
        if (strlen(trim($termino)) < 2) {
            throw new Exception('El término de búsqueda debe tener al menos 2 caracteres');
        }

        $productos = $this->repository->buscarProductos($termino, $limite);

        return array_map(function ($producto) {
            return [
                'nombre' => $producto['nombre_producto'],
                'tipo' => $producto['tipo_producto'],
                'texto_completo' => $producto['nombre_producto'] . ' - ' . $producto['tipo_producto']
            ];
        }, $productos);
    }

    public function obtenerTiposProducto()
    {
        return $this->repository->obtenerTiposProducto();
    }

    public function validarFiltros($filtros)
    {
        $errores = [];

        if (!empty($filtros['fecha_desde'])) {
            if (!$this->validarFecha($filtros['fecha_desde'])) {
                $errores[] = 'Fecha desde no válida';
            }
        }

        if (!empty($filtros['fecha_hasta'])) {
            if (!$this->validarFecha($filtros['fecha_hasta'])) {
                $errores[] = 'Fecha hasta no válida';
            }
        }

        if (!empty($filtros['fecha_desde']) && !empty($filtros['fecha_hasta'])) {
            if ($filtros['fecha_desde'] > $filtros['fecha_hasta']) {
                $errores[] = 'La fecha desde no puede ser mayor que la fecha hasta';
            }
        }

        return $errores;
    }

    private function enriquecerDatosGrupo($grupo)
    {
        $grupo['peso_bruto_formateado'] = $this->formatearPeso($grupo['peso_bruto_total']);
        $grupo['peso_liquido_formateado'] = $this->formatearPeso($grupo['peso_liquido_total']);

        $grupo['fecha_primera_formateada'] = $this->formatearFecha($grupo['fecha_primera_produccion']);
        $grupo['fecha_ultima_formateada'] = $this->formatearFecha($grupo['fecha_ultima_produccion']);

        if ($grupo['fecha_primera_produccion'] && $grupo['fecha_ultima_produccion']) {
            $fecha1 = new DateTime($grupo['fecha_primera_produccion']);
            $fecha2 = new DateTime($grupo['fecha_ultima_produccion']);
            $grupo['dias_produccion'] = $fecha1->diff($fecha2)->days + 1;
        } else {
            $grupo['dias_produccion'] = 1;
        }

        $grupo['dimensiones'] = $this->formatearDimensiones($grupo['largura'], $grupo['metragem']);

        $grupo['id_grupo'] = md5($grupo['nombre_producto'] . $grupo['tipo_producto'] . $grupo['metragem'] . $grupo['largura'] . $grupo['gramatura']);

        return $grupo;
    }

    private function enriquecerDatosItem($item)
    {
        $item['peso_bruto_formateado'] = $this->formatearPeso($item['peso_bruto']);
        $item['peso_liquido_formateado'] = $this->formatearPeso($item['peso_liquido']);
        $item['tara_formateada'] = $this->formatearPeso($item['tara']);
        $item['fecha_producida_formateada'] = $this->formatearFecha($item['fecha_hora_producida']);
        if (!empty($item['fecha_orden'])) {
            $item['fecha_orden_formateada'] = $this->formatearFecha($item['fecha_orden']);
        }
        $item['dimensiones'] = $this->formatearDimensiones($item['largura'], $item['metragem']);
        $item['tiempo_desde_produccion'] = $this->calcularTiempoTranscurrido($item['fecha_hora_producida']);

        return $item;
    }
    private function formatearPeso($peso)
    {
        if (!$peso || $peso == 0) return '0 kg';

        if ($peso >= 1000) {
            return number_format($peso / 1000, 2, ',', '.') . ' t';
        } else {
            return number_format($peso, 2, ',', '.') . ' kg';
        }
    }
    private function formatearFecha($fecha)
    {
        if (!$fecha) return '';

        try {
            $dt = new DateTime($fecha);
            return $dt->format('d/m/Y H:i');
        } catch (Exception $e) {
            return $fecha;
        }
    }
    private function formatearDimensiones($largura, $metragem)
    {
        $partes = [];

        if (!empty($largura)) {
            $partes[] = $largura . 'cm';
        }

        if (!empty($metragem)) {
            $partes[] = $metragem . 'm';
        }

        return implode(' x ', $partes);
    }
    private function calcularTiempoTranscurrido($fechaProduccion)
    {
        if (!$fechaProduccion) return '';

        try {
            $fecha = new DateTime($fechaProduccion);
            $ahora = new DateTime();
            $diff = $ahora->diff($fecha);

            if ($diff->days == 0) {
                if ($diff->h == 0) {
                    return $diff->i . ' minutos';
                } else {
                    return $diff->h . ' horas';
                }
            } elseif ($diff->days == 1) {
                return '1 día';
            } else {
                return $diff->days . ' días';
            }
        } catch (Exception $e) {
            return '';
        }
    }
    private function validarFecha($fecha)
    {
        return DateTime::createFromFormat('Y-m-d', $fecha) !== false;
    }
    public function generarResumenExport($grupos)
    {
        $totalItems = array_sum(array_column($grupos, 'total_items'));
        $totalPesoBruto = array_sum(array_column($grupos, 'peso_bruto_total'));
        $totalPesoLiquido = array_sum(array_column($grupos, 'peso_liquido_total'));
        $totalBobinas = array_sum(array_column($grupos, 'bobinas_pacote_total'));

        return [
            'total_grupos' => count($grupos),
            'total_items' => $totalItems,
            'total_peso_bruto' => $totalPesoBruto,
            'total_peso_liquido' => $totalPesoLiquido,
            'total_bobinas' => $totalBobinas,
            'peso_bruto_formateado' => $this->formatearPeso($totalPesoBruto),
            'peso_liquido_formateado' => $this->formatearPeso($totalPesoLiquido)
        ];
    }
}
