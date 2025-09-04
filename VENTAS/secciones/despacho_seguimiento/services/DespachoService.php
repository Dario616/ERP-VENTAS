<?php

class DespachoService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function obtenerExpediciones($filtros = [])
    {
        $expediciones = $this->repository->obtenerExpediciones($filtros);

        return array_map([$this, 'enriquecerDatosExpedicion'], $expediciones);
    }

    public function obtenerDetallesExpedicion($numeroExpedicion, $agrupar = false)
    {
        if (empty($numeroExpedicion)) {
            throw new Exception('Número de expedición requerido');
        }

        $items = $this->repository->obtenerDetallesExpedicion($numeroExpedicion, $agrupar);

        if (empty($items)) {
            throw new Exception('Expedición no encontrada o sin items escaneados');
        }

        $expedicion = null;
        $itemsEnriquecidos = [];

        foreach ($items as $item) {
            if ($expedicion === null) {
                $expedicion = [
                    'expedicion_id' => $item['expedicion_id'],
                    'numero_expedicion' => $item['numero_expedicion'],
                    'fecha_creacion' => $item['fecha_creacion'],
                    'estado' => $item['estado'],
                    'transportista' => $item['transportista'],
                    'conductor' => $item['conductor'],
                    'placa_vehiculo' => $item['placa_vehiculo'],
                    'destino' => $item['destino'],
                    'observaciones' => $item['observaciones'],
                    'usuario_creacion' => $item['usuario_creacion'],
                    'fecha_despacho' => $item['fecha_despacho'],
                    'usuario_despacho' => $item['usuario_despacho'],
                    'peso_expedicion' => $item['peso_expedicion'],
                    'tipovehiculo' => $item['tipovehiculo'],
                    'descripcion' => $item['descripcion']
                ];
                $expedicion = $this->enriquecerDatosExpedicionDetalle($expedicion);
            }

            $itemsEnriquecidos[] = $this->enriquecerDatosItemExpedicion($item);
        }

        return [
            'expedicion' => $expedicion,
            'items' => $itemsEnriquecidos,
            'resumen' => $this->generarResumenItems($itemsEnriquecidos),
            'agrupado' => $agrupar
        ];
    }

    public function obtenerEstadisticas()
    {
        $estadisticas = $this->repository->obtenerEstadisticas();

        if (!empty($estadisticas)) {
            $estadisticas['peso_bruto_formateado'] = $this->formatearPeso($estadisticas['peso_bruto_total']);
            $estadisticas['peso_liquido_formateado'] = $this->formatearPeso($estadisticas['peso_liquido_total']);
            $estadisticas['peso_escaneado_formateado'] = $this->formatearPeso($estadisticas['peso_escaneado_total']);
            $estadisticas['fecha_primera_formateada'] = $this->formatearFecha($estadisticas['fecha_primera_expedicion']);
            $estadisticas['fecha_ultima_formateada'] = $this->formatearFecha($estadisticas['fecha_ultima_expedicion']);

            if ($estadisticas['fecha_primera_expedicion'] && $estadisticas['fecha_ultima_expedicion']) {
                $fecha1 = new DateTime($estadisticas['fecha_primera_expedicion']);
                $fecha2 = new DateTime($estadisticas['fecha_ultima_expedicion']);
                $estadisticas['dias_expediciones'] = $fecha1->diff($fecha2)->days + 1;
            }

            $totalExpediciones = $estadisticas['total_expediciones'];
            if ($totalExpediciones > 0) {
                $estadisticas['porcentaje_abiertas'] = round(($estadisticas['expediciones_abiertas'] / $totalExpediciones) * 100, 1);
                $estadisticas['porcentaje_transito'] = round(($estadisticas['expediciones_transito'] / $totalExpediciones) * 100, 1);
                $estadisticas['porcentaje_entregadas'] = round(($estadisticas['expediciones_entregadas'] / $totalExpediciones) * 100, 1);
                $estadisticas['porcentaje_canceladas'] = round(($estadisticas['expediciones_canceladas'] / $totalExpediciones) * 100, 1);
            }
        }

        return $estadisticas ?: [];
    }

    public function buscarExpediciones($termino, $limite = 10)
    {
        if (strlen(trim($termino)) < 2) {
            throw new Exception('El término de búsqueda debe tener al menos 2 caracteres');
        }

        $resultados = $this->repository->buscarExpediciones($termino, $limite);

        return array_map(function ($resultado) {
            return [
                'tipo' => $resultado['tipo'],
                'valor' => $resultado['valor'],
                'texto_completo' => $resultado['texto_completo']
            ];
        }, $resultados);
    }

    public function obtenerTransportistas()
    {
        return $this->repository->obtenerTransportistas();
    }

    public function obtenerEstadosExpedicion()
    {
        return $this->repository->obtenerEstadosExpedicion();
    }

    public function obtenerDestinos()
    {
        return $this->repository->obtenerDestinos();
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

        if (!empty($filtros['id_stock'])) {
            if (!is_numeric($filtros['id_stock']) || $filtros['id_stock'] <= 0) {
                $errores[] = 'El ID de stock debe ser un número válido mayor a 0';
            }
        }

        if (!empty($filtros['id_venta_asignado'])) {
            if (!is_numeric($filtros['id_venta_asignado']) || $filtros['id_venta_asignado'] <= 0) {
                $errores[] = 'El ID de ventas debe ser un número válido mayor a 0';
            }
        }

        return $errores;
    }

    public function obtenerResumenExpediciones()
    {
        $resumen = $this->repository->obtenerResumenPorTransportista();

        return array_map(function ($transportista) {
            $transportista['peso_bruto_formateado'] = $this->formatearPeso($transportista['peso_bruto_total']);
            $transportista['peso_escaneado_formateado'] = $this->formatearPeso($transportista['peso_escaneado_total']);
            return $transportista;
        }, $resumen);
    }


    private function enriquecerDatosExpedicion($expedicion)
    {
        $expedicion['peso_bruto_formateado'] = $this->formatearPeso($expedicion['peso_bruto_total']);
        $expedicion['peso_liquido_formateado'] = $this->formatearPeso($expedicion['peso_liquido_total']);
        $expedicion['peso_escaneado_formateado'] = $this->formatearPeso($expedicion['peso_escaneado_total']);

        $expedicion['fecha_creacion_formateada'] = $this->formatearFecha($expedicion['fecha_creacion']);
        $expedicion['fecha_despacho_formateada'] = $this->formatearFecha($expedicion['fecha_despacho']);
        $expedicion['fecha_primer_escaneo_formateada'] = $this->formatearFecha($expedicion['fecha_primer_escaneo']);
        $expedicion['fecha_ultimo_escaneo_formateada'] = $this->formatearFecha($expedicion['fecha_ultimo_escaneo']);

        if ($expedicion['fecha_creacion']) {
            $expedicion['tiempo_desde_creacion'] = $this->calcularTiempoTranscurrido($expedicion['fecha_creacion']);
        }

        $expedicion['estado_badge_class'] = $this->obtenerClaseBadgeEstado($expedicion['estado']);

        if (strlen($expedicion['clientes_lista'] ?? '') > 100) {
            $expedicion['clientes_lista_corta'] = substr($expedicion['clientes_lista'], 0, 100) . '...';
        } else {
            $expedicion['clientes_lista_corta'] = $expedicion['clientes_lista'] ?? '';
        }

        if (strlen($expedicion['productos_lista'] ?? '') > 100) {
            $expedicion['productos_lista_corta'] = substr($expedicion['productos_lista'], 0, 100) . '...';
        } else {
            $expedicion['productos_lista_corta'] = $expedicion['productos_lista'] ?? '';
        }

        $expedicion['id_expedicion'] = 'exp_' . $expedicion['numero_expedicion'];

        return $expedicion;
    }

    private function enriquecerDatosExpedicionDetalle($expedicion)
    {
        $expedicion['fecha_creacion_formateada'] = $this->formatearFecha($expedicion['fecha_creacion']);
        $expedicion['fecha_despacho_formateada'] = $this->formatearFecha($expedicion['fecha_despacho']);
        $expedicion['estado_badge_class'] = $this->obtenerClaseBadgeEstado($expedicion['estado']);
        $expedicion['tiempo_desde_creacion'] = $this->calcularTiempoTranscurrido($expedicion['fecha_creacion']);

        return $expedicion;
    }

    private function enriquecerDatosItemExpedicion($item)
    {
        $item['peso_bruto_formateado'] = $this->formatearPeso($item['peso_bruto']);
        $item['peso_liquido_formateado'] = $this->formatearPeso($item['peso_liquido']);
        $item['peso_escaneado_formateado'] = $this->formatearPeso($item['peso_escaneado']);
        $item['tara_formateada'] = $this->formatearPeso($item['tara']);

        $item['fecha_escaneado_formateada'] = $this->formatearFecha($item['fecha_escaneado']);
        $item['fecha_producida_formateada'] = $this->formatearFecha($item['fecha_hora_producida']);

        $item['dimensiones'] = $this->formatearDimensiones($item['largura'], $item['metragem']);

        $item['tiempo_desde_escaneo'] = $this->calcularTiempoTranscurrido($item['fecha_escaneado']);
        $item['tiempo_desde_produccion'] = $this->calcularTiempoTranscurrido($item['fecha_hora_producida']);

        $item['cliente_reasignado'] = ($item['cliente_asignado'] !== $item['cliente_original']);

        $item['modo_asignacion_badge'] = $this->obtenerBadgeModoAsignacion($item['modo_asignacion']);

        return $item;
    }

    private function generarResumenItems($items)
    {
        $totalItems = count($items);
        $totalPesoBruto = array_sum(array_column($items, 'peso_bruto'));
        $totalPesoLiquido = array_sum(array_column($items, 'peso_liquido'));
        $totalPesoEscaneado = array_sum(array_column($items, 'peso_escaneado'));
        $totalCantidad = array_sum(array_column($items, 'cantidad_escaneada'));
        $clientesUnicos = count(array_unique(array_column($items, 'cliente_asignado')));
        $productosUnicos = count(array_unique(array_column($items, 'nombre_producto')));

        $totalBobinas = array_sum(array_filter(array_column($items, 'bobinas_pacote')));
        $itemsConBobinas = array_filter($items, function ($item) {
            return !empty($item['bobinas_pacote']) && $item['bobinas_pacote'] > 0;
        });
        $paquetesConBobinas = count($itemsConBobinas);

        return [
            'total_items' => $totalItems,
            'total_peso_bruto' => $totalPesoBruto,
            'total_peso_liquido' => $totalPesoLiquido,
            'total_peso_escaneado' => $totalPesoEscaneado,
            'total_cantidad' => $totalCantidad,
            'clientes_unicos' => $clientesUnicos,
            'productos_unicos' => $productosUnicos,
            'total_bobinas' => $totalBobinas,
            'paquetes_con_bobinas' => $paquetesConBobinas,
            'peso_bruto_formateado' => $this->formatearPeso($totalPesoBruto),
            'peso_liquido_formateado' => $this->formatearPeso($totalPesoLiquido),
            'peso_escaneado_formateado' => $this->formatearPeso($totalPesoEscaneado)
        ];
    }

    private function obtenerClaseBadgeEstado($estado)
    {
        switch (strtoupper($estado)) {
            case 'ABIERTA':
                return 'bg-warning text-dark';
            case 'EN_TRANSITO':
                return 'bg-info';
            case 'ENTREGADA':
                return 'bg-success';
            case 'CANCELADA':
                return 'bg-danger';
            default:
                return 'bg-secondary';
        }
    }

    private function obtenerBadgeModoAsignacion($modo)
    {
        switch (strtolower($modo)) {
            case 'automatico':
                return 'bg-primary';
            case 'manual':
                return 'bg-warning text-dark';
            case 'reasignado':
                return 'bg-info';
            default:
                return 'bg-secondary';
        }
    }

    private function formatearPeso($peso)
    {
        if (!$peso || $peso == 0) return '0 kg';

        if ($peso >= 1000000) {
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
            $partes[] = $largura . 'm';
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

    public function generarResumenExport($expediciones)
    {
        $totalExpediciones = count($expediciones);
        $totalItems = array_sum(array_column($expediciones, 'total_items'));
        $totalPesoBruto = array_sum(array_column($expediciones, 'peso_bruto_total'));
        $totalPesoLiquido = array_sum(array_column($expediciones, 'peso_liquido_total'));
        $totalPesoEscaneado = array_sum(array_column($expediciones, 'peso_escaneado_total'));
        $transportistasUnicos = count(array_unique(array_column($expediciones, 'transportista')));
        $destinosUnicos = count(array_unique(array_column($expediciones, 'destino')));

        return [
            'total_expediciones' => $totalExpediciones,
            'total_items' => $totalItems,
            'total_peso_bruto' => $totalPesoBruto,
            'total_peso_liquido' => $totalPesoLiquido,
            'total_peso_escaneado' => $totalPesoEscaneado,
            'transportistas_unicos' => $transportistasUnicos,
            'destinos_unicos' => $destinosUnicos,
            'peso_bruto_formateado' => $this->formatearPeso($totalPesoBruto),
            'peso_liquido_formateado' => $this->formatearPeso($totalPesoLiquido),
            'peso_escaneado_formateado' => $this->formatearPeso($totalPesoEscaneado)
        ];
    }
}
