<?php

class ProductosAsignadosService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }
    public function obtenerProductosAsignadosAgrupados($filtros = [])
    {
        $grupos = $this->repository->obtenerProductosAsignadosAgrupados($filtros);

        return array_map([$this, 'enriquecerDatosGrupoAsignado'], $grupos);
    }

    public function obtenerDetallesOrden($idOrdenProduccion)
    {
        if (empty($idOrdenProduccion)) {
            throw new Exception('ID de orden de producción requerido');
        }

        $items = $this->repository->obtenerDetallesOrden($idOrdenProduccion);

        if (empty($items)) {
            throw new Exception('Orden no encontrada o sin productos asignados');
        }

        return array_map([$this, 'enriquecerDatosItemAsignado'], $items);
    }

    public function obtenerEstadisticas()
    {
        $estadisticas = $this->repository->obtenerEstadisticas();

        if (!empty($estadisticas)) {
            $estadisticas['peso_bruto_formateado'] = $this->formatearPeso($estadisticas['peso_bruto_total']);
            $estadisticas['peso_liquido_formateado'] = $this->formatearPeso($estadisticas['peso_liquido_total']);
            $estadisticas['fecha_primera_formateada'] = $this->formatearFecha($estadisticas['fecha_primera_orden']);
            $estadisticas['fecha_ultima_formateada'] = $this->formatearFecha($estadisticas['fecha_ultima_orden']);
            if ($estadisticas['fecha_primera_orden'] && $estadisticas['fecha_ultima_orden']) {
                $fecha1 = new DateTime($estadisticas['fecha_primera_orden']);
                $fecha2 = new DateTime($estadisticas['fecha_ultima_orden']);
                $estadisticas['dias_ordenes'] = $fecha1->diff($fecha2)->days + 1;
            }
            $totalOrdenes = $estadisticas['ordenes_diferentes'];
            if ($totalOrdenes > 0) {
                $estadisticas['porcentaje_pendientes'] = round(($estadisticas['ordenes_pendientes'] / $totalOrdenes) * 100, 1);
                $estadisticas['porcentaje_proceso'] = round(($estadisticas['ordenes_proceso'] / $totalOrdenes) * 100, 1);
                $estadisticas['porcentaje_completadas'] = round(($estadisticas['ordenes_completadas'] / $totalOrdenes) * 100, 1);
            }
        }

        return $estadisticas ?: [];
    }
    public function buscarClientesProductos($termino, $limite = 10)
    {
        if (strlen(trim($termino)) < 2) {
            throw new Exception('El término de búsqueda debe tener al menos 2 caracteres');
        }

        $resultados = $this->repository->buscarClientesProductos($termino, $limite);

        return array_map(function ($resultado) {
            return [
                'tipo' => $resultado['tipo'],
                'valor' => $resultado['valor'],
                'texto_completo' => $resultado['texto_completo']
            ];
        }, $resultados);
    }

    public function obtenerClientes()
    {
        return $this->repository->obtenerClientes();
    }

    public function obtenerEstadosOrden()
    {
        return $this->repository->obtenerEstadosOrden();
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

    public function obtenerResumenPorCliente()
    {
        $resumen = $this->repository->obtenerResumenPorCliente();

        return array_map(function ($cliente) {
            $cliente['peso_bruto_formateado'] = $this->formatearPeso($cliente['peso_bruto_total']);
            $cliente['peso_liquido_formateado'] = $this->formatearPeso($cliente['peso_liquido_total']);
            return $cliente;
        }, $resumen);
    }

    private function enriquecerDatosGrupoAsignado($grupo)
    {
        $grupo['peso_bruto_formateado'] = $this->formatearPeso($grupo['peso_bruto_total']);
        $grupo['peso_liquido_formateado'] = $this->formatearPeso($grupo['peso_liquido_total']);

        $grupo['fecha_orden_formateada'] = $this->formatearFecha($grupo['fecha_orden']);
        $grupo['fecha_primera_formateada'] = $this->formatearFecha($grupo['fecha_primera_produccion']);
        $grupo['fecha_ultima_formateada'] = $this->formatearFecha($grupo['fecha_ultima_produccion']);

        if ($grupo['fecha_primera_produccion'] && $grupo['fecha_ultima_produccion']) {
            $fecha1 = new DateTime($grupo['fecha_primera_produccion']);
            $fecha2 = new DateTime($grupo['fecha_ultima_produccion']);
            $grupo['dias_produccion'] = $fecha1->diff($fecha2)->days + 1;
        } else {
            $grupo['dias_produccion'] = 1;
        }
        if ($grupo['fecha_orden']) {
            $grupo['tiempo_desde_orden'] = $this->calcularTiempoTranscurrido($grupo['fecha_orden']);
        }
        $grupo['estado_badge_class'] = $this->obtenerClaseBadgeEstado($grupo['estado_orden']);

        if (strlen($grupo['productos_lista']) > 100) {
            $grupo['productos_lista_corta'] = substr($grupo['productos_lista'], 0, 100) . '...';
        } else {
            $grupo['productos_lista_corta'] = $grupo['productos_lista'];
        }
        $grupo['id_grupo'] = 'orden_' . $grupo['id_orden_produccion'];

        return $grupo;
    }
    private function enriquecerDatosItemAsignado($item)
    {
        $item['peso_bruto_formateado'] = $this->formatearPeso($item['peso_bruto']);
        $item['peso_liquido_formateado'] = $this->formatearPeso($item['peso_liquido']);
        $item['tara_formateada'] = $this->formatearPeso($item['tara']);
        $item['fecha_producida_formateada'] = $this->formatearFecha($item['fecha_hora_producida']);
        $item['fecha_orden_formateada'] = $this->formatearFecha($item['fecha_orden']);
        $item['dimensiones'] = $this->formatearDimensiones($item['largura'], $item['metragem']);
        $item['tiempo_desde_produccion'] = $this->calcularTiempoTranscurrido($item['fecha_hora_producida']);
        $item['tiempo_desde_orden'] = $this->calcularTiempoTranscurrido($item['fecha_orden']);
        $item['estado_badge_class'] = $this->obtenerClaseBadgeEstado($item['estado_orden']);

        return $item;
    }

    private function obtenerClaseBadgeEstado($estado)
    {
        switch (strtolower($estado)) {
            case 'pendiente':
                return 'bg-warning text-dark';
            case 'en proceso':
                return 'bg-info';
            case 'completada':
                return 'bg-success';
            case 'cancelada':
                return 'bg-danger';
            default:
                return 'bg-secondary';
        }
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

    private function calcularTiempoTranscurrido($fecha)
    {
        if (!$fecha) return '';

        try {
            $fecha = new DateTime($fecha);
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
        $clientesUnicos = count(array_unique(array_column($grupos, 'cliente')));

        return [
            'total_ordenes' => count($grupos),
            'total_items' => $totalItems,
            'total_peso_bruto' => $totalPesoBruto,
            'total_peso_liquido' => $totalPesoLiquido,
            'total_bobinas' => $totalBobinas,
            'clientes_unicos' => $clientesUnicos,
            'peso_bruto_formateado' => $this->formatearPeso($totalPesoBruto),
            'peso_liquido_formateado' => $this->formatearPeso($totalPesoLiquido)
        ];
    }
}
