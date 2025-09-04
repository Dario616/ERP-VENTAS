<?php
class VentaService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }
    public function obtenerVentas($idUsuario = null, $filtros = [])
    {
        $ventas = $this->repository->obtenerVentas($idUsuario, $filtros);

        return array_map([$this, 'enriquecerDatosVenta'], $ventas);
    }

    public function obtenerVentaPorId($id, $idUsuario = null)
    {
        if (!$this->validarId($id)) {
            throw new Exception('ID de venta inválido');
        }

        $venta = $this->repository->obtenerVentaPorId($id, $idUsuario);

        if (!$venta) {
            throw new Exception('Venta no encontrada');
        }

        return $this->enriquecerDatosVenta($venta);
    }

    public function actualizarEstadoVenta($id, $estado, $idUsuario = null)
    {
        if (!$this->validarId($id)) {
            return ['success' => false, 'error' => 'ID de venta inválido'];
        }

        $estadosValidos = array_keys($this->repository->obtenerEstadosDisponibles());
        if (!in_array($estado, $estadosValidos)) {
            return ['success' => false, 'error' => 'Estado no válido'];
        }

        $venta = $this->repository->obtenerVentaPorId($id, $idUsuario);
        if (!$venta) {
            return ['success' => false, 'error' => 'Venta no encontrada'];
        }

        if (!$this->validarTransicionEstado($venta['estado'], $estado)) {
            return ['success' => false, 'error' => 'Transición de estado no válida'];
        }

        $resultado = $this->repository->actualizarEstadoVenta($id, $estado, $idUsuario);

        if ($resultado) {
            return ['success' => true, 'mensaje' => 'Estado actualizado correctamente'];
        } else {
            return ['success' => false, 'error' => 'Error al actualizar el estado'];
        }
    }

    public function actualizarEstadoReserva($idReserva, $estado)
    {
        if (!$this->validarId($idReserva)) {
            return ['success' => false, 'error' => 'ID de reserva inválido'];
        }

        $estadosReservaValidos = ['activa', 'despachada', 'completada', 'cancelada'];
        if (!in_array($estado, $estadosReservaValidos)) {
            return ['success' => false, 'error' => 'Estado de reserva no válido'];
        }

        $resultado = $this->repository->actualizarEstadoReserva($idReserva, $estado);

        if ($resultado) {
            return ['success' => true, 'mensaje' => 'Estado de reserva actualizado correctamente'];
        } else {
            return ['success' => false, 'error' => 'Error al actualizar el estado de la reserva'];
        }
    }

    public function obtenerResumenVenta($id, $idUsuario = null)
    {
        $venta = $this->obtenerVentaPorId($id, $idUsuario);

        if ($venta['estado'] === 'Finalizado Manualmente') {
            return [
                'venta' => $venta,
                'es_finalizado_manualmente' => true,
                'proceso_pcp' => $venta['proceso_pcp'] ?? null,
                'historial_pcp' => $this->repository->obtenerHistorialProcesoPCP($id),
                'resumen_produccion' => [],
                'progreso_general' => [
                    'porcentaje' => 100,
                    'clase_progreso' => 'bg-success',
                    'items_completos' => 0,
                    'items_total' => 0
                ]
            ];
        }

        $resumenProduccion = $this->repository->obtenerResumenProduccion($id, $venta['cliente']);

        $resumenProduccion = array_map([$this, 'enriquecerResumenProductoSimplificado'], $resumenProduccion);

        return [
            'venta' => $venta,
            'es_finalizado_manualmente' => false,
            'proceso_pcp' => null,
            'historial_pcp' => [],
            'resumen_produccion' => $resumenProduccion,
            'progreso_general' => $this->calcularProgresoGeneralSimplificado($resumenProduccion)
        ];
    }

    private function enriquecerResumenProductoSimplificado($producto)
    {
        $cantidadPedida = $producto['cantidad_pedida'] ?: 1;
        $cantidadProducida = $producto['cantidad_producida'] ?: 0;
        $cantidadDespachada = $producto['cantidad_despachada'] ?: 0;
        $cantidadStock = $producto['cantidad_stock'] ?: 0;

        $itemsProducidos = $producto['items_producidos'] ?: 0;
        $bobinasProducidas = $producto['bobinas_producidas'] ?: 0;
        $esTipoBobinas = $producto['es_tipo_bobinas'] ?? false;

        $producto['porcentaje_produccion'] = min(100, round(($cantidadProducida / $cantidadPedida) * 100, 1));
        $producto['porcentaje_despacho'] = $cantidadPedida > 0
            ? min(100, round(($cantidadDespachada / $cantidadPedida) * 100, 1))
            : 0;

        $producto['clase_progreso_produccion'] = $this->obtenerClaseProgreso($producto['porcentaje_produccion']);
        $producto['clase_progreso_despacho'] = $this->obtenerClaseProgreso($producto['porcentaje_despacho']);

        $unidad = $producto['unidad_medida'] ?? 'kg';
        $producto['cantidad_pedida_formateada'] = number_format($cantidadPedida, 0) . ' ' . $unidad;
        $producto['cantidad_producida_formateada'] = number_format($cantidadProducida, 0) . ' ' . $unidad;
        $producto['cantidad_despachada_formateada'] = number_format($cantidadDespachada, 0) . ' ' . $unidad;
        $producto['cantidad_stock_formateada'] = number_format($cantidadStock, 0) . ' ' . $unidad;

        if ($esTipoBobinas) {
            $producto['debug_info'] = [
                'items_producidos' => $itemsProducidos,
                'bobinas_producidas' => $bobinasProducidas,
                'cantidad_mostrada' => $cantidadProducida,
                'tipo' => 'bobinas',
                'calculo' => $cantidadProducida == $bobinasProducidas ? 'CORRECTO (usa bobinas)' : 'INCORRECTO (usa items)'
            ];

            error_log("DEBUG Service - Producto bobinas '{$producto['producto']}': Items=$itemsProducidos, Bobinas=$bobinasProducidas, Mostrado=$cantidadProducida");
        }

        if ($unidad === 'bobinas' && isset($producto['peso_por_bobina']) && $producto['peso_por_bobina'] > 0) {
            $producto['peso_total_pedido'] = $cantidadPedida * $producto['peso_por_bobina'];
            $producto['peso_total_producido'] = $cantidadProducida * $producto['peso_por_bobina'];
            $producto['peso_total_despachado'] = $cantidadDespachada * $producto['peso_por_bobina'];

            $producto['peso_total_pedido_formateado'] = number_format($producto['peso_total_pedido'], 2) . ' kg';
            $producto['peso_total_producido_formateado'] = number_format($producto['peso_total_producido'], 2) . ' kg';
            $producto['peso_total_despachado_formateado'] = number_format($producto['peso_total_despachado'], 2) . ' kg';
        }

        return $producto;
    }

    public function buscarVentas($termino, $idUsuario = null, $limite = 10)
    {
        if (strlen(trim($termino)) < 2) {
            throw new Exception('El término de búsqueda debe tener al menos 2 caracteres');
        }

        $ventas = $this->repository->buscarVentas($termino, $idUsuario, $limite);

        return array_map(function ($venta) {
            return [
                'id' => $venta['id'],
                'cliente' => $venta['cliente'],
                'proforma' => $venta['proforma'],
                'estado' => $venta['estado'],
                'fecha_venta' => $venta['fecha_venta'],
                'monto_total' => $venta['monto_total'],
                'texto_completo' => "#{$venta['proforma']} - {$venta['cliente']} - " . $this->formatearMoneda($venta['monto_total'])
            ];
        }, $ventas);
    }


    public function obtenerEstadisticas($idUsuario = null)
    {
        $estadisticas = $this->repository->obtenerEstadisticas($idUsuario);

        if (!empty($estadisticas)) {
            $total = $estadisticas['total_ventas'];
            if ($total > 0) {
                $estadisticas['porcentaje_pendientes'] = round(($estadisticas['ventas_pendientes'] / $total) * 100, 1);
                $estadisticas['porcentaje_produccion'] = round(($estadisticas['ventas_produccion'] / $total) * 100, 1);
                $estadisticas['porcentaje_despachadas'] = round(($estadisticas['ventas_despachadas'] / $total) * 100, 1);
                $estadisticas['porcentaje_completadas'] = round(($estadisticas['ventas_completadas'] / $total) * 100, 1);
                $estadisticas['porcentaje_finalizadas_manualmente'] = round(($estadisticas['ventas_finalizadas_manualmente'] / $total) * 100, 1);
            }
            $estadisticas['monto_total_formateado'] = $this->formatearMoneda($estadisticas['monto_total']);
            $estadisticas['monto_promedio_formateado'] = $this->formatearMoneda($estadisticas['monto_promedio']);
        }

        return $estadisticas ?: [];
    }

    private function enriquecerDatosVenta($venta)
    {
        $estado = $venta['estado'] ?? 'pendiente';

        if (!empty($venta['fecha_venta'])) {
            $venta['fecha_venta_formateada'] = $this->formatearFecha($venta['fecha_venta']);
        }

        $venta['monto_total_formateado'] = $this->formatearMoneda($venta['monto_total'] ?? 0);
        $venta['subtotal_formateado'] = $this->formatearMoneda($venta['subtotal'] ?? 0);

        $venta['estado'] = $estado;
        $venta['estado_label'] = $this->obtenerLabelEstado($estado);
        $venta['estado_class'] = $this->obtenerClaseEstado($estado);
        $venta['estado_icono'] = $this->obtenerIconoEstado($estado);
        $venta['es_credito_texto'] = ($venta['es_credito'] ?? false) ? 'Sí' : 'No';

        if ($estado === 'Finalizado Manualmente') {
            $venta['progreso_produccion'] = [
                'porcentaje' => 100,
                'productos_total' => 0,
                'productos_producidos' => 0,
                'clase_progreso' => 'bg-success'
            ];
            $venta['progreso_despacho'] = [
                'porcentaje' => 100,
                'productos_total' => 0,
                'productos_despachados' => 0,
                'clase_progreso' => 'bg-success'
            ];
        } else {
            if (isset($venta['total_productos']) && isset($venta['productos_producidos'])) {
                $venta['progreso_produccion'] = $this->calcularProgresoProduccion($venta);
            }

            if (isset($venta['productos_producidos']) && isset($venta['productos_despachados'])) {
                $venta['progreso_despacho'] = $this->calcularProgresoDespacho($venta);
            }
        }

        return $venta;
    }


    private function calcularProgresoProduccion($venta)
    {
        $totalProductos = $venta['total_productos'] ?: 1;
        $productosProducidos = $venta['productos_producidos'] ?: 0;

        $porcentaje = min(100, round(($productosProducidos / $totalProductos) * 100, 1));

        return [
            'porcentaje' => $porcentaje,
            'productos_total' => $totalProductos,
            'productos_producidos' => $productosProducidos,
            'clase_progreso' => $this->obtenerClaseProgreso($porcentaje)
        ];
    }


    private function calcularProgresoDespacho($venta)
    {
        $productosProducidos = $venta['productos_producidos'] ?: 1;
        $productosDespachados = $venta['productos_despachados'] ?: 0;

        $porcentaje = min(100, round(($productosDespachados / $productosProducidos) * 100, 1));

        return [
            'porcentaje' => $porcentaje,
            'productos_total' => $productosProducidos,
            'productos_despachados' => $productosDespachados,
            'clase_progreso' => $this->obtenerClaseProgreso($porcentaje)
        ];
    }

    private function calcularProgresoGeneralSimplificado($resumenProduccion)
    {
        $totalItems = count($resumenProduccion);
        if ($totalItems === 0) {
            return ['porcentaje' => 0, 'clase_progreso' => 'bg-secondary'];
        }

        $itemsCompletos = 0;
        $pesoTotal = 0;
        $pesoCompletado = 0;

        foreach ($resumenProduccion as $item) {
            $cantidadPedida = $item['cantidad_pedida'] ?: 0;
            $cantidadDespachada = $item['cantidad_despachada'] ?: 0;

            if ($cantidadDespachada >= $cantidadPedida && $cantidadPedida > 0) {
                $itemsCompletos++;
            }

            $pesoTotal += $cantidadPedida;
            $pesoCompletado += min($cantidadDespachada, $cantidadPedida);
        }

        $porcentaje = $pesoTotal > 0 ? round(($pesoCompletado / $pesoTotal) * 100, 1) : 0;

        return [
            'porcentaje' => $porcentaje,
            'items_completos' => $itemsCompletos,
            'items_total' => $totalItems,
            'peso_total' => $pesoTotal,
            'peso_completado' => $pesoCompletado,
            'clase_progreso' => $this->obtenerClaseProgreso($porcentaje)
        ];
    }

    private function validarTransicionEstado($estadoActual, $nuevoEstado)
    {
        $estadoActual = $estadoActual ?? 'pendiente';
        $nuevoEstado = $nuevoEstado ?? '';

        $transicionesValidas = [
            'pendiente' => ['confirmada', 'cancelada', 'Finalizado Manualmente'],
            'confirmada' => ['en_produccion', 'cancelada', 'Finalizado Manualmente'],
            'en_produccion' => ['produccion_completa', 'cancelada', 'Finalizado Manualmente'],
            'produccion_completa' => ['despachada', 'Finalizado Manualmente'],
            'despachada' => ['completada', 'Finalizado Manualmente'],
            'completada' => ['Finalizado Manualmente'],
            'cancelada' => ['pendiente'],
            'Finalizado Manualmente' => []
        ];

        return in_array($nuevoEstado, $transicionesValidas[$estadoActual] ?? []);
    }

    private function obtenerLabelEstado($estado)
    {
        $labels = [
            'pendiente' => 'Pendiente',
            'confirmada' => 'Confirmada',
            'en_produccion' => 'En Producción',
            'produccion_completa' => 'Producción Completa',
            'despachada' => 'Despachada',
            'completada' => 'Completada',
            'cancelada' => 'Cancelada',
            'Finalizado Manualmente' => 'Finalizado Manualmente'
        ];

        return $labels[$estado] ?? ($estado ? ucfirst($estado) : 'Sin Estado');
    }

    private function obtenerClaseEstado($estado)
    {
        $clases = [
            'pendiente' => 'primary',
            'En Expedición' => 'info',
            'En Producción' => 'info',
            'En revision' => 'warning',
            'Enviado a PCP' => 'bg-pcp',
            'completada' => 'success',
            'cancelada' => 'danger',
            'Finalizado Manualmente' => 'dark'
        ];

        return $clases[$estado] ?? 'primary';
    }

    private function obtenerIconoEstado($estado)
    {
        $iconos = [
            'pendiente' => 'fas fa-clock',
            'En Expedición' => 'fas fa-box-open',
            'En Producción' => 'fas fa-tools',
            'En revision' => 'fas fa-search',
            'Enviado a PCP' => 'fas fa-paper-plane',
            'completada' => 'fas fa-flag-checkered',
            'cancelada' => 'fas fa-times-circle',
            'Finalizado Manualmente' => 'fas fa-hand-paper'
        ];

        return $iconos[$estado] ?? 'fas fa-clock';
    }


    public function obtenerItemsProduccionEnStock($id, $idUsuario = null, $idProducto = null)
    {
        if (!$this->validarId($id)) {
            throw new Exception('ID de venta inválido');
        }

        $venta = $this->repository->obtenerVentaPorId($id, $idUsuario);
        if (!$venta) {
            throw new Exception('Venta no encontrada');
        }

        $items = $this->repository->obtenerItemsProduccionEnStock($id, $idProducto);

        return array_map(function ($item) {
            if ($item['fecha_hora_producida']) {
                $fecha = new DateTime($item['fecha_hora_producida']);
                $item['fecha_hora_producida_formateada'] = $fecha->format('d/m/Y H:i:s');
            }
            $item['peso_bruto_formateado'] = number_format($item['peso_bruto'], 2) . ' kg';
            $item['peso_liquido_formateado'] = number_format($item['peso_liquido'], 2) . ' kg';
            $item['tara_formateada'] = number_format($item['tara'], 2) . ' kg';
            if ($item['metragem'] && $item['largura']) {
                $item['dimensiones'] = $item['metragem'] . 'm x ' . number_format($item['largura'], 4) . 'm';
            }
            if ($item['gramatura']) {
                $item['gramatura_formateada'] = $item['gramatura'] . ' g/m²';
            }
            return $item;
        }, $items);
    }


    public function obtenerItemsProduccionEnStockAgrupados($id, $idUsuario = null, $idProducto = null)
    {
        if (!$this->validarId($id)) {
            throw new Exception('ID de venta inválido');
        }

        $venta = $this->repository->obtenerVentaPorId($id, $idUsuario);
        if (!$venta) {
            throw new Exception('Venta no encontrada');
        }

        $items = $this->repository->obtenerItemsProduccionEnStockAgrupados($id, $idProducto);

        return array_map([$this, 'formatearItemAgrupado'], $items);
    }


    public function obtenerItemsDespachosAgrupados($id, $idUsuario = null, $idProducto = null)
    {
        if (!$this->validarId($id)) {
            throw new Exception('ID de venta inválido');
        }

        $venta = $this->repository->obtenerVentaPorId($id, $idUsuario);
        if (!$venta) {
            throw new Exception('Venta no encontrada');
        }

        $items = $this->repository->obtenerItemsDespachosAgrupados($id, $idProducto);

        return array_map(function ($item) {
            $itemFormateado = $this->formatearItemAgrupado($item);
            $itemFormateado['estado_badge'] = '<span class="badge bg-success">Despachado</span>';
            return $itemFormateado;
        }, $items);
    }

    private function formatearItemAgrupado($item)
    {
        $item['peso_bruto_total_formateado'] = number_format($item['peso_bruto_total'], 2) . ' kg';
        $item['peso_liquido_total_formateado'] = number_format($item['peso_liquido_total'], 2) . ' kg';
        $item['total_items_formateado'] = number_format($item['total_items']) . ' items';
        $item['bobinas_pacote_total_formateado'] = number_format($item['bobinas_pacote_total']) . ' bobinas';
        if ($item['metragem'] && $item['largura']) {
            $item['dimensiones'] = $item['metragem'] . 'm x ' . number_format($item['largura'], 4) . 'm';
        }
        if ($item['gramatura']) {
            $item['gramatura_formateada'] = $item['gramatura'] . ' g/m²';
        }

        if ($item['total_items'] > 0) {
            $peso_promedio = $item['peso_liquido_total'] / $item['total_items'];
            $item['peso_promedio_por_item'] = number_format($peso_promedio, 2) . ' kg/item';

            $bobinas_promedio = $item['bobinas_pacote_total'] / $item['total_items'];
            $item['bobinas_promedio_por_item'] = number_format($bobinas_promedio, 1) . ' bob/item';
        }

        return $item;
    }

    private function obtenerClaseProgreso($porcentaje)
    {
        if ($porcentaje >= 100) return 'bg-success';
        if ($porcentaje >= 75) return 'bg-info';
        if ($porcentaje >= 50) return 'bg-warning';
        if ($porcentaje >= 25) return 'bg-primary';
        return 'bg-secondary';
    }

    private function formatearFecha($fecha)
    {
        if (!$fecha || empty($fecha)) return '';

        try {
            $dt = new DateTime($fecha);
            return $dt->format('d/m/Y');
        } catch (Exception $e) {
            return $fecha;
        }
    }


    private function formatearMoneda($monto)
    {
        if (!is_numeric($monto) || $monto === null) return '₲ 0';

        return '₲ ' . number_format($monto, 0, ',', '.');
    }

    private function validarId($id)
    {
        return is_numeric($id) && $id > 0;
    }
}
