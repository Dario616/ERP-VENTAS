<?php

class ExpedicionService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function calcularPorcentajeUso($pesoActual, $capacidadMaxima)
    {
        if (!$capacidadMaxima || $capacidadMaxima == 0) {
            return 0;
        }
        $porcentaje = ($pesoActual / $capacidadMaxima) * 100;
        return round($porcentaje, 1);
    }

    public function procesarAsignacionCompleta($datos)
    {
        try {
            $this->validarDatosAsignacion($datos);

            $pesoTotalCalculado = $datos['cantidad_asignar_unidades'] * $datos['peso_unitario'];

            $verificacionCapacidad = $this->repository->verificarCapacidadParaReserva(
                $datos['id_rejilla'],
                $pesoTotalCalculado
            );

            if (!$verificacionCapacidad['valida']) {
                throw new Exception($verificacionCapacidad['razon']);
            }

            $datos['cantidad_asignar_kg'] = $pesoTotalCalculado;

            $resultadoAsignacion = $this->repository->crearAsignacionPresupuesto($datos);

            return [
                'success' => true,
                'message' => "Producto '{$datos['nombre_producto']}' reservado completamente en rejilla. {$datos['cantidad_asignar_unidades']} {$resultadoAsignacion['tipo_unidad']} asignadas. El producto desaparecerá del listado ya que está EN REJILLAS.",
                'id_asignacion' => $resultadoAsignacion['id_asignacion'],
                'cantidad_reservada_kg' => $pesoTotalCalculado,
                'cantidad_reservada_unidades' => $datos['cantidad_asignar_unidades'],
                'peso_exacto' => $resultadoAsignacion['peso_exacto'],
                'peso_unitario' => $datos['peso_unitario'],
                'tipo_unidad' => $resultadoAsignacion['tipo_unidad'],
                'nombre_producto_guardado' => $resultadoAsignacion['nombre_producto_guardado'] ?? $datos['nombre_producto'],
                'tipo_asignacion' => 'reserva_completa_presupuesto',
                'movimiento_actualizado' => true,
                'producto_guardado_en_rejilla' => true,
                'unidades_guardadas_en_rejilla' => true
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function cancelarReserva($idAsignacion, $usuario)
    {
        try {
            $this->repository->cancelarReserva($idAsignacion);

            return [
                'success' => true,
                'message' => 'Reserva cancelada correctamente. El producto volverá a aparecer en el listado.',
                'movimiento_reseteado' => true
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function enriquecerClientesConVentas($clientes)
    {
        foreach ($clientes as &$cliente) {
            $cliente['total_cantidad_vendida_formateada'] = number_format($cliente['total_cantidad_vendida'] ?? 0, 0);

            $cliente['total_unidades_vendidas_formateada'] = number_format($cliente['total_unidades_vendidas'] ?? 0, 0);

            if ($cliente['ultima_venta']) {
                $cliente['ultima_venta_formateada'] = $this->formatearFecha($cliente['ultima_venta']);
            }

            $totalProductos = $cliente['total_productos'] ?? 0;
            $totalCantidad = $cliente['total_cantidad_vendida'] ?? 0;
            $totalUnidades = $cliente['total_unidades_vendidas'] ?? 0;

            if ($totalProductos > 10 && ($totalCantidad > 1000 || $totalUnidades > 40)) {
                $cliente['estado_actividad'] = 'alta';
                $cliente['clase_actividad'] = 'success';
                $cliente['icono_actividad'] = 'fas fa-arrow-up';
            } elseif ($totalProductos > 5 && ($totalCantidad > 500 || $totalUnidades > 20)) {
                $cliente['estado_actividad'] = 'media';
                $cliente['clase_actividad'] = 'warning';
                $cliente['icono_actividad'] = 'fas fa-minus';
            } else {
                $cliente['estado_actividad'] = 'baja';
                $cliente['clase_actividad'] = 'info';
                $cliente['icono_actividad'] = 'fas fa-arrow-down';
            }

            $estadisticasCliente = $this->repository->obtenerEstadisticasProduccionExpedicion($cliente['nombre']);
            $cliente = array_merge($cliente, $this->procesarEstadisticasProduccionExpedicion($estadisticasCliente));
        }

        return $clientes;
    }

    public function enriquecerProductosConProduccionExpedicion($productos)
    {
        foreach ($productos as &$producto) {
            $pesoTotalVendido = floatval($producto['peso_total_vendido_kg'] ?? 0);
            $pesoUnitario = floatval($producto['peso_unitario_kg'] ?? 0);
            $cantidadUnidades = intval($producto['cantidad_unidades_vendidas'] ?? 0);

            $pesoProduccion = floatval($producto['peso_asignado_produccion_kg'] ?? 0);
            $unidadesProduccion = intval($producto['unidades_asignadas_produccion'] ?? 0);

            $pesoExpedicion = floatval($producto['peso_asignado_expedicion_kg'] ?? 0);
            $unidadesExpedicion = intval($producto['unidades_asignadas_expedicion'] ?? 0);

            $unidadesReservadas = intval($producto['unidades_reservadas'] ?? 0);
            $pesoReservado = floatval($producto['peso_asignado_rejillas'] ?? 0);

            $pesoPendiente = $pesoTotalVendido - $pesoProduccion - $pesoExpedicion;
            $unidadesPendientes = $cantidadUnidades - $unidadesProduccion - $unidadesExpedicion;

            $unidadesDisponiblesParaReservar = max(0, $cantidadUnidades - $unidadesReservadas);

            $pesoDisponibleParaReservar = $unidadesDisponiblesParaReservar * $pesoUnitario;

            $porcentajeProduccion = $pesoTotalVendido > 0 ? ($pesoProduccion / $pesoTotalVendido) * 100 : 0;
            $porcentajeExpedicion = $pesoTotalVendido > 0 ? ($pesoExpedicion / $pesoTotalVendido) * 100 : 0;
            $porcentajePendiente = $pesoTotalVendido > 0 ? ($pesoPendiente / $pesoTotalVendido) * 100 : 0;
            $porcentajeReservado = $pesoTotalVendido > 0 ? ($pesoReservado / $pesoTotalVendido) * 100 : 0;

            $producto['peso_pendiente_kg'] = $pesoPendiente;
            $producto['unidades_pendientes'] = max(0, $unidadesPendientes);
            $producto['porcentaje_produccion'] = round($porcentajeProduccion, 1);
            $producto['porcentaje_expedicion'] = round($porcentajeExpedicion, 1);
            $producto['porcentaje_pendiente'] = round($porcentajePendiente, 1);
            $producto['porcentaje_reservado'] = round($porcentajeReservado, 1);

            $producto['disponible_para_reservar_unidades'] = $unidadesDisponiblesParaReservar;
            $producto['disponible_para_reservar_kg'] = $pesoDisponibleParaReservar;
            $producto['unidades_ya_reservadas'] = $unidadesReservadas;
            $producto['peso_ya_reservado'] = $pesoReservado;

            $producto['tipo_unidad'] = $this->determinarTipoUnidad($producto['nombre_producto']);

            $porcentajeCompletado = $porcentajeProduccion + $porcentajeExpedicion + $porcentajeReservado;

            if ($porcentajeCompletado >= 100) {
                $producto['estado_asignacion'] = 'completo';
                $producto['clase_estado'] = 'success';
                $producto['icono_estado'] = 'fas fa-check-circle';
            } elseif ($porcentajeCompletado >= 80) {
                $producto['estado_asignacion'] = 'casi_completo';
                $producto['clase_estado'] = 'info';
                $producto['icono_estado'] = 'fas fa-clock';
            } elseif ($porcentajeCompletado >= 50) {
                $producto['estado_asignacion'] = 'parcial';
                $producto['clase_estado'] = 'warning';
                $producto['icono_estado'] = 'fas fa-exclamation-triangle';
            } else {
                $producto['estado_asignacion'] = 'pendiente';
                $producto['clase_estado'] = 'danger';
                $producto['icono_estado'] = 'fas fa-times-circle';
            }

            $producto['peso_total_vendido_formateado'] = number_format($pesoTotalVendido, 1);
            $producto['peso_produccion_formateado'] = number_format($pesoProduccion, 1);
            $producto['peso_expedicion_formateado'] = number_format($pesoExpedicion, 1);
            $producto['peso_pendiente_formateado'] = number_format($pesoPendiente, 1);
            $producto['peso_unitario_formateado'] = number_format($pesoUnitario, 2);

            $producto['unidades_vendidas_formateado'] = number_format($cantidadUnidades, 0);
            $producto['unidades_produccion_formateado'] = number_format($unidadesProduccion, 0);
            $producto['unidades_expedicion_formateado'] = number_format($unidadesExpedicion, 0);
            $producto['unidades_disponibles_formateado'] = number_format($unidadesDisponiblesParaReservar, 0);
            $producto['peso_disponible_formateado'] = number_format($pesoDisponibleParaReservar, 1);
        }

        return $productos;
    }

    private function determinarTipoUnidad($nombreProducto)
    {
        $nombreUpper = strtoupper($nombreProducto);

        if (strpos($nombreUpper, 'TNT') !== false || strpos($nombreUpper, 'SPUNLACE') !== false) {
            return 'bobinas';
        } elseif (strpos($nombreUpper, 'TOALLITA') !== false || strpos($nombreUpper, 'TOALLA') !== false || strpos($nombreUpper, 'PAÑO') !== false || strpos($nombreUpper, 'PAÑOS') !== false) {
            return 'cajas';
        } else {
            return 'unidades';
        }
    }

    private function procesarEstadisticasProduccionExpedicion($estadisticas)
    {
        $datos = [
            'estadisticas_produccion_expedicion' => [
                'total_peso_vendido' => floatval($estadisticas['total_peso_vendido'] ?? 0),
                'total_peso_produccion' => floatval($estadisticas['total_peso_produccion'] ?? 0),
                'total_peso_expedicion' => floatval($estadisticas['total_peso_expedicion'] ?? 0),
                'total_unidades_vendidas' => intval($estadisticas['total_unidades_vendidas'] ?? 0),
                'productos_con_produccion' => intval($estadisticas['productos_con_produccion'] ?? 0),
                'productos_con_expedicion' => intval($estadisticas['productos_con_expedicion'] ?? 0),
                'productos_con_pendientes' => intval($estadisticas['productos_con_pendientes'] ?? 0)
            ]
        ];

        $pesoTotal = $datos['estadisticas_produccion_expedicion']['total_peso_vendido'];
        $unidadesTotal = $datos['estadisticas_produccion_expedicion']['total_unidades_vendidas'];

        if ($pesoTotal > 0) {
            $datos['estadisticas_produccion_expedicion']['porcentaje_produccion'] = round(
                ($datos['estadisticas_produccion_expedicion']['total_peso_produccion'] / $pesoTotal) * 100,
                1
            );
            $datos['estadisticas_produccion_expedicion']['porcentaje_expedicion'] = round(
                ($datos['estadisticas_produccion_expedicion']['total_peso_expedicion'] / $pesoTotal) * 100,
                1
            );
            $datos['estadisticas_produccion_expedicion']['porcentaje_pendiente'] = round(
                (($pesoTotal - $datos['estadisticas_produccion_expedicion']['total_peso_produccion'] -
                    $datos['estadisticas_produccion_expedicion']['total_peso_expedicion']) / $pesoTotal) * 100,
                1
            );
        } else {
            $datos['estadisticas_produccion_expedicion']['porcentaje_produccion'] = 0;
            $datos['estadisticas_produccion_expedicion']['porcentaje_expedicion'] = 0;
            $datos['estadisticas_produccion_expedicion']['porcentaje_pendiente'] = 0;
        }

        $datos['estadisticas_produccion_expedicion']['total_unidades_vendidas_formateado'] =
            number_format($unidadesTotal, 0);

        return $datos;
    }

    public function enriquecerRejillas($rejillas)
    {
        foreach ($rejillas as &$rejilla) {
            $rejilla['porcentaje_uso'] = $this->calcularPorcentajeUso(
                $rejilla['peso_actual'],
                $rejilla['capacidad_maxima']
            );

            $rejilla['peso_actual_formateado'] = number_format($rejilla['peso_actual'] ?? 0, 1);
            $rejilla['capacidad_maxima_formateada'] = number_format($rejilla['capacidad_maxima'] ?? 0, 1);
            $rejilla['capacidad_disponible_formateada'] = number_format($rejilla['capacidad_disponible'] ?? 0, 1);

            $porcentaje = $rejilla['porcentaje_uso'];
            if ($porcentaje >= 95) {
                $rejilla['estado_visual'] = 'llena';
                $rejilla['color_progreso'] = 'danger';
            } elseif ($porcentaje >= 80) {
                $rejilla['estado_visual'] = 'casi_llena';
                $rejilla['color_progreso'] = 'warning';
            } elseif ($porcentaje >= 50) {
                $rejilla['estado_visual'] = 'ocupada';
                $rejilla['color_progreso'] = 'info';
            } else {
                $rejilla['estado_visual'] = 'disponible';
                $rejilla['color_progreso'] = 'success';
            }
        }

        return $rejillas;
    }

    public function enriquecerRejillasDetalladas($rejillas)
    {
        foreach ($rejillas as &$rejilla) {
            $rejilla['porcentaje_uso'] = $this->calcularPorcentajeUso(
                $rejilla['peso_actual'],
                $rejilla['capacidad_maxima']
            );

            $rejilla['total_unidades_asignadas_formateado'] = number_format($rejilla['total_unidades_asignadas'] ?? 0, 0);

            if ($rejilla['fecha_actualizacion']) {
                $fechaActualizacion = new DateTime($rejilla['fecha_actualizacion']);
                $ahora = new DateTime();
                $diferencia = $ahora->diff($fechaActualizacion);
                $rejilla['dias_desde_actualizacion'] = $diferencia->days;
            } else {
                $rejilla['dias_desde_actualizacion'] = 0;
            }

            if ($rejilla['ultima_asignacion']) {
                $fechaAsignacion = new DateTime($rejilla['ultima_asignacion']);
                $ahora = new DateTime();
                $diferencia = $ahora->diff($fechaAsignacion);
                $rejilla['dias_desde_ultima_asignacion'] = $diferencia->days;
            } else {
                $rejilla['dias_desde_ultima_asignacion'] = null;
            }

            $rejilla['fecha_creacion_formateada'] = $this->formatearFecha($rejilla['fecha_creacion']);
            $rejilla['fecha_actualizacion_formateada'] = $this->formatearFecha($rejilla['fecha_actualizacion']);
            $rejilla['ultima_asignacion_formateada'] = $this->formatearFecha($rejilla['ultima_asignacion']);

            $diasSinUso = $rejilla['dias_desde_ultima_asignacion'];
            if ($diasSinUso !== null && $diasSinUso > 7 && $rejilla['peso_actual'] > 0) {
                $rejilla['requiere_atencion'] = true;
                $rejilla['razon_atencion'] = 'Sin movimiento por más de 7 días';
            } elseif ($rejilla['porcentaje_uso'] >= 95) {
                $rejilla['requiere_atencion'] = true;
                $rejilla['razon_atencion'] = 'Rejilla casi llena';
            } else {
                $rejilla['requiere_atencion'] = false;
                $rejilla['razon_atencion'] = null;
            }

            $totalUnidades = $rejilla['total_unidades_asignadas'] ?? 0;
            if ($totalUnidades > 0) {
                $rejilla['promedio_unidades_por_item'] = round($totalUnidades / max(1, $rejilla['total_items_asignados']), 1);
                $rejilla['densidad_unidades'] = round($totalUnidades / max(1, $rejilla['peso_actual']), 2);
            } else {
                $rejilla['promedio_unidades_por_item'] = 0;
                $rejilla['densidad_unidades'] = 0;
            }

            $rejilla = $this->enriquecerRejillas([$rejilla])[0];
        }

        return $rejillas;
    }

    private function validarDatosAsignacion($datos)
    {
        if (!isset($datos['id_venta']) || $datos['id_venta'] <= 0) {
            throw new Exception('ID de venta inválido');
        }

        if (!isset($datos['id_producto_presupuesto']) || $datos['id_producto_presupuesto'] <= 0) {
            throw new Exception('ID de producto presupuesto inválido');
        }

        if (!isset($datos['id_rejilla']) || $datos['id_rejilla'] <= 0) {
            throw new Exception('ID de rejilla inválido');
        }

        if (!isset($datos['cantidad_asignar_unidades']) || $datos['cantidad_asignar_unidades'] <= 0) {
            throw new Exception('Cantidad de unidades inválida');
        }

        if (!isset($datos['peso_unitario']) || $datos['peso_unitario'] <= 0) {
            throw new Exception('Peso unitario inválido');
        }

        if (empty($datos['nombre_producto'])) {
            throw new Exception('Nombre de producto requerido');
        }

        if (empty($datos['usuario'])) {
            throw new Exception('Usuario requerido');
        }

        return true;
    }

    private function formatearFecha($fecha)
    {
        if (!$fecha) return null;

        try {
            $dt = new DateTime($fecha);
            return $dt->format('d/m/Y');
        } catch (Exception $e) {
            return $fecha;
        }
    }

    public function procesarAsignacionVentaCompleta($datos)
    {
        try {
            $this->validarDatosAsignacionVentaCompleta($datos);

            $resultadoAsignacion = $this->repository->crearAsignacionVentaCompleta($datos);

            if ($resultadoAsignacion['success']) {
                $mensaje = "Venta completa asignada exitosamente a la rejilla.\n\n";
                $mensaje .= "• Cliente: {$datos['cliente']}\n";
                $mensaje .= "• Productos asignados: " . count($datos['productos']) . "\n";
                $mensaje .= "• Unidades totales: {$datos['unidades_totales']}\n";
                $mensaje .= "• Peso total: " . round($datos['peso_total_requerido'], 1) . " kg\n\n";

                if (isset($datos['exceso_capacidad']) && $datos['exceso_capacidad'] > 0) {
                    $excesoTexto = $datos['exceso_capacidad'] >= 1000
                        ? round($datos['exceso_capacidad'] / 1000, 1) . " toneladas"
                        : round($datos['exceso_capacidad'], 1) . " kg";
                    $mensaje .= "⚠️ NOTA: La rejilla excedió su capacidad por {$excesoTexto}.\n";
                    $mensaje .= "La asignación se completó exitosamente.\n\n";
                }

                $mensaje .= "Todos los productos desaparecerán del listado ya que están EN REJILLAS.";

                return [
                    'success' => true,
                    'message' => $mensaje,
                    'productos_asignados' => count($datos['productos']),
                    'unidades_totales' => $datos['unidades_totales'],
                    'peso_total' => $datos['peso_total_requerido'],
                    'exceso_capacidad' => $datos['exceso_capacidad'] ?? 0,
                    'capacidad_excedida' => isset($datos['exceso_capacidad']) && $datos['exceso_capacidad'] > 0,
                    'id_rejilla' => $datos['id_rejilla'],
                    'id_venta' => $datos['id_venta'],
                    'asignaciones_creadas' => $resultadoAsignacion['asignaciones_creadas'],
                    'tipo_asignacion' => 'reserva_venta_completa',
                    'movimiento_actualizado' => true,
                    'productos_guardados_en_rejilla' => true
                ];
            } else {
                throw new Exception($resultadoAsignacion['error'] ?? 'Error creando asignaciones');
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function validarDatosAsignacionVentaCompleta($datos)
    {
        if (!isset($datos['id_venta']) || $datos['id_venta'] <= 0) {
            throw new Exception('ID de venta inválido');
        }

        if (!isset($datos['id_rejilla']) || $datos['id_rejilla'] <= 0) {
            throw new Exception('ID de rejilla inválido');
        }

        if (!isset($datos['productos']) || !is_array($datos['productos']) || empty($datos['productos'])) {
            throw new Exception('Lista de productos inválida');
        }

        if (!isset($datos['peso_total_requerido']) || $datos['peso_total_requerido'] <= 0) {
            throw new Exception('Peso total requerido inválido');
        }

        if (!isset($datos['unidades_totales']) || $datos['unidades_totales'] <= 0) {
            throw new Exception('Unidades totales inválidas');
        }

        if (empty($datos['usuario'])) {
            throw new Exception('Usuario requerido');
        }

        if (empty($datos['cliente'])) {
            throw new Exception('Cliente requerido');
        }

        foreach ($datos['productos'] as $producto) {
            if (!isset($producto['id_producto_presupuesto']) || $producto['id_producto_presupuesto'] <= 0) {
                throw new Exception('ID de producto presupuesto inválido en uno de los productos');
            }

            if (empty($producto['nombre_producto'])) {
                throw new Exception('Nombre de producto requerido');
            }

            if (!isset($producto['unidades_disponibles']) || $producto['unidades_disponibles'] <= 0) {
                throw new Exception('Unidades disponibles inválidas para producto: ' . $producto['nombre_producto']);
            }

            if (!isset($producto['peso_unitario']) || $producto['peso_unitario'] <= 0) {
                throw new Exception('Peso unitario inválido para producto: ' . $producto['nombre_producto']);
            }
        }

        return true;
    }

    public function marcarItemComoCompletado($idAsignacion, $observaciones = null, $usuario = null)
    {
        try {
            $resultado = $this->repository->marcarItemComoCompletado($idAsignacion, $observaciones);

            if ($resultado['exito']) {
                return [
                    'success' => true,
                    'message' => $resultado['mensaje'] . ' (estado_asignacion cambiado a "completada")',
                    'filas_afectadas' => $resultado['filas_afectadas']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $resultado['mensaje']
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function reactivarItemCompletado($idAsignacion, $observaciones = null, $usuario = null)
    {
        try {
            $resultado = $this->repository->reactivarItemCompletado($idAsignacion, $observaciones);

            if ($resultado['exito']) {
                return [
                    'success' => true,
                    'message' => $resultado['mensaje'] . ' (estado_asignacion cambiado a "activa")',
                    'filas_afectadas' => $resultado['filas_afectadas']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $resultado['mensaje']
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
