<?php

class DespachoServices
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function crearNuevaExpedicion($datos)
    {
        try {
            $this->validarDatosExpedicion($datos);
            $resultado = $this->repository->crearExpedicion($datos);

            $rejillaInfo = $this->obtenerInfoRejilla($datos['id_rejilla']);

            return [
                'success' => true,
                'numero_expedicion' => $resultado['numero_expedicion'],
                'mensaje' => "Expedición {$resultado['numero_expedicion']} creada exitosamente para Rejilla #{$rejillaInfo['numero_rejilla']} - Solo modo automático",
                'datos' => $resultado,
                'rejilla_info' => $rejillaInfo
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function obtenerInfoRejilla($idRejilla)
    {
        return $this->repository->obtenerInfoRejilla($idRejilla);
    }

    private function determinarClienteAutomatico($item, $numeroExpedicion)
    {
        $cantidadRealItem = !empty($item['bobinas_pacote']) && $item['bobinas_pacote'] > 0
            ? (int)$item['bobinas_pacote'] : 1;

        if (!($item['fuera_de_rejilla'] ?? false)) {
            $asignacionDisponible = $this->repository->buscarAsignacionDisponible(
                $item['nombre_producto'],
                $numeroExpedicion
            );

            if ($asignacionDisponible && isset($asignacionDisponible['asignacion'])) {
                $asignacion = $asignacionDisponible['asignacion'];
                $itemsPendientes = $asignacionDisponible['items_pendientes'];
                if ($cantidadRealItem <= $itemsPendientes) {
                    return [
                        'cliente' => $asignacion['cliente'],
                        'id_venta' => $asignacion['id_venta_real'],
                        'id_asignacion' => $asignacion['id'],
                        'es_desconocido' => false,
                        'origen' => 'asignacion_automatica',
                        'mensaje_asignacion' => '✅ Asignado automáticamente',
                        'modo_asignacion' => 'automatico',
                        'cantidad_item' => $cantidadRealItem,
                        'flexibilidad_aplicada' => $item['reserva_cancelada'] ?? false
                    ];
                }
            }
        }

        if (isset($item['reserva_cancelada']) && $item['reserva_cancelada']) {
            return [
                'cliente' => 'DESCONOCIDO',
                'id_venta' => null,
                'id_asignacion' => null,
                'es_desconocido' => true,
                'origen' => 'liberado_de_reserva',
                'mensaje_asignacion' => '🔄 Item liberado de reserva → DESCONOCIDO',
                'modo_asignacion' => 'desconocido_flexible',
                'cantidad_item' => $cantidadRealItem,
                'flexibilidad_aplicada' => true
            ];
        }

        if ($item['id_venta']) {
            $clienteOriginal = $this->repository->obtenerClientePorVenta($item['id_venta']);
            if ($clienteOriginal) {
                $asignacionDisponible = $this->repository->buscarAsignacionDisponible(
                    $item['nombre_producto'],
                    $numeroExpedicion
                );
                if ($asignacionDisponible && isset($asignacionDisponible['asignacion'])) {
                    $itemsPendientes = $asignacionDisponible['items_pendientes'];

                    if ($cantidadRealItem <= $itemsPendientes) {
                        return [
                            'cliente' => $clienteOriginal,
                            'id_venta' => $item['id_venta'],
                            'id_asignacion' => $asignacionDisponible['asignacion']['id'],
                            'es_desconocido' => false,
                            'origen' => 'venta_original',
                            'mensaje_asignacion' => '✅ Asignado por venta original',
                            'modo_asignacion' => 'automatico',
                            'cantidad_item' => $cantidadRealItem
                        ];
                    }
                }
            }
        }

        $razonDesconocido = 'Sin asignación disponible';
        $modoAsignacion = 'desconocido';

        if ($item['fuera_de_rejilla'] ?? false) {
            $razonDesconocido = 'Item sin asignaciones en esta rejilla';
            $modoAsignacion = 'desconocido_fuera_rejilla';
        }

        return [
            'cliente' => 'DESCONOCIDO',
            'id_venta' => null,
            'id_asignacion' => null,
            'es_desconocido' => true,
            'origen' => 'sin_asignacion',
            'mensaje_asignacion' => "❓ DESCONOCIDO - {$razonDesconocido}",
            'modo_asignacion' => $modoAsignacion,
            'cantidad_item' => $cantidadRealItem,
            'razon_detallada' => $razonDesconocido
        ];
    }

    public function procesarEscaneoItem($numeroExpedicion, $idItem, $usuario)
    {
        try {
            $expedicion = $this->repository->verificarExpedicion($numeroExpedicion);
            if (!$expedicion) {
                throw new Exception("Expedición no encontrada");
            }

            if ($expedicion['estado'] !== 'ABIERTA') {
                throw new Exception("La expedición está cerrada");
            }
            $idRejillaExpedicion = $expedicion['id_rejilla'];
            $validacion = $this->repository->validarItemParaEscaneado($idItem, $idRejillaExpedicion);

            if (!$validacion['valido']) {
                throw new Exception($validacion['error']);
            }

            $item = $validacion['item'];
            $cantidadRealItem = !empty($item['bobinas_pacote']) && $item['bobinas_pacote'] > 0
                ? (int)$item['bobinas_pacote'] : 1;

            $infoCliente = $this->determinarClienteAutomatico($item, $numeroExpedicion);

            $infoAdicional = [
                'cliente_asignado' => $infoCliente['cliente'],
                'id_venta_asignado' => $infoCliente['id_venta'],
                'id_asignacion_rejilla' => $infoCliente['id_asignacion'],
                'cantidad_escaneada' => $cantidadRealItem,
                'peso_escaneado' => $item['peso_bruto'],
                'es_desconocido' => $infoCliente['es_desconocido'],
                'modo_asignacion' => $infoCliente['modo_asignacion'],
                'reserva_cancelada' => $item['reserva_cancelada'] ?? false
            ];

            $agregado = $this->repository->agregarItemExpedicion($numeroExpedicion, $idItem, $usuario, $infoAdicional);
            if (!$agregado) {
                throw new Exception("Error al agregar item a la expedición");
            }

            $estadoItem = $infoCliente['es_desconocido'] ? 'DESCONOCIDO' : 'ASIGNADO';
            $iconoEstado = $infoCliente['es_desconocido'] ? '❓' : '✅';

            error_log("ESCANEADO FLEXIBLE - Item: {$item['numero_item']}, Cliente: {$infoCliente['cliente']}, Estado: {$estadoItem}, Expedición: {$numeroExpedicion}");

            return [
                'success' => true,
                'mensaje' => "{$iconoEstado} Item {$item['numero_item']} escaneado exitosamente",
                'item' => [
                    'id' => $item['id'],
                    'numero_item' => $item['numero_item'],
                    'nombre_producto' => $item['nombre_producto'],
                    'cliente' => $infoCliente['cliente'],
                    'es_desconocido' => $infoCliente['es_desconocido'],
                    'modo_asignacion' => $infoCliente['modo_asignacion'],
                    'peso_bruto' => (float)$item['peso_bruto'],
                    'peso_bruto_formateado' => number_format($item['peso_bruto'], 2) . ' kg',
                    'cantidad_escaneada' => $cantidadRealItem,
                    'bobinas_pacote' => $item['bobinas_pacote'],
                    'estado_simple' => $estadoItem,
                    'icono_estado' => $iconoEstado
                ],
                'info_asignacion' => $infoCliente,
                'expedicion' => $numeroExpedicion,
                'usuario' => $usuario,
                'fecha_escaneado' => date('d/m/Y H:i:s'),
                'modo_sistema' => 'FLEXIBLE_TOTAL',
                'flexibilidad' => [
                    'sistema_permisivo' => true,
                    'reserva_cancelada' => $item['reserva_cancelada'] ?? false,
                    'permite_cualquier_item_en_stock' => true,
                    'mensaje_flexibilidad' => $validacion['mensaje'] ?? ''
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'tipo_error' => $this->determinarTipoErrorSimple($e->getMessage())
            ];
        }
    }

    private function determinarTipoErrorSimple($mensajeError)
    {
        $mensaje = strtolower($mensajeError);

        if (strpos($mensaje, 'ya está en expedición') !== false) {
            return 'DUPLICADO';
        }
        if (strpos($mensaje, 'cerrada') !== false) {
            return 'EXPEDICION_CERRADA';
        }
        if (strpos($mensaje, 'no encontrado') !== false || strpos($mensaje, 'no está en stock') !== false) {
            return 'ITEM_NO_EXISTE';
        }

        return 'ERROR_GENERAL';
    }

    public function moverItemACliente($idExpedicionItem, $nuevoCliente, $idVenta = null)
    {
        try {
            $resultado = $this->repository->moverItemACliente($idExpedicionItem, $nuevoCliente, $idVenta);

            if ($resultado) {
                $mensaje = "Item DESCONOCIDO reasignado exitosamente a {$nuevoCliente}";

                if ($idVenta) {
                    $mensaje .= " (ID Venta: {$idVenta})";
                }

                $mensaje .= " - Datos actualizados en expedición (producto se actualizará al despachar)";

                return [
                    'success' => true,
                    'mensaje' => $mensaje,
                    'detalles' => [
                        'cliente_asignado' => $nuevoCliente,
                        'id_venta_asignado' => $idVenta,
                        'expedicion_actualizada' => true,
                        'producto_se_actualizara_al_despachar' => true,
                        'modo_asignacion' => 'reasignado_manual'
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error al reasignar el item - No se pudo actualizar la expedición'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'codigo_error' => 'REASIGNACION_ERROR'
            ];
        }
    }

    public function obtenerVistaPreviaClientes($numeroExpedicion)
    {
        try {
            $itemsPorCliente = $this->repository->obtenerItemsExpedicionPorCliente($numeroExpedicion);

            $resultado = [];
            foreach ($itemsPorCliente as $grupo) {
                $clienteAsignado = $this->obtenerCampoSeguro(
                    $grupo,
                    'cliente_asignado',
                    $this->obtenerCampoSeguro($grupo, 'cliente', 'DESCONOCIDO')
                );
                $esDesconocido = $this->obtenerCampoSeguro($grupo, 'es_desconocido', false);
                $modoAsignacion = $this->obtenerCampoSeguro($grupo, 'modo_asignacion', 'automatico');

                $cliente = $esDesconocido ? 'DESCONOCIDO' : $clienteAsignado;

                $icono = $esDesconocido ? '❓' : '👤';
                if ($esDesconocido && $modoAsignacion === 'desconocido_fuera_rejilla') {
                    $icono = '📍';
                } elseif ($esDesconocido && $modoAsignacion === 'desconocido_flexible') {
                    $icono = '🔄';
                } elseif ($modoAsignacion === 'automatico_flexible') {
                    $icono = '🔄✅';
                }

                $estadoVisual = $this->determinarEstadoVisual($grupo);

                $resultado[] = [
                    'cliente' => $cliente,
                    'cliente_asignado' => $clienteAsignado,
                    'es_desconocido' => $esDesconocido,
                    'modo_asignacion' => $modoAsignacion,
                    'icono' => $icono,
                    'total_items_escaneados' => $this->obtenerCampoSeguro($grupo, 'total_items_escaneados', 0),
                    'cantidad_total_escaneada' => $this->obtenerCampoSeguro($grupo, 'cantidad_total_escaneada', 0),
                    'peso_total_escaneado' => $this->obtenerCampoSeguro($grupo, 'peso_total_escaneado', 0),
                    'peso_total_formateado' => number_format($this->obtenerCampoSeguro($grupo, 'peso_total_escaneado', 0), 2) . ' kg',
                    'productos' => $this->obtenerCampoSeguro($grupo, 'productos', ''),
                    'numeros_items' => $this->obtenerCampoSeguro($grupo, 'numeros_items', ''),
                    'ids_expedicion_items' => $this->obtenerCampoSeguro($grupo, 'ids_expedicion_items', []),
                    'total_asignado' => $this->obtenerCampoSeguro($grupo, 'total_asignado', 0),
                    'peso_asignado' => $this->obtenerCampoSeguro($grupo, 'peso_asignado', 0),
                    'peso_asignado_formateado' => number_format($this->obtenerCampoSeguro($grupo, 'peso_asignado', 0), 2) . ' kg',
                    'asignaciones_relacionadas' => $this->obtenerCampoSeguro($grupo, 'asignaciones_relacionadas', 0),
                    'progreso_cantidad' => $this->obtenerCampoSeguro($grupo, 'progreso_cantidad', 0),
                    'progreso_peso' => $this->obtenerCampoSeguro($grupo, 'progreso_peso', 0),
                    'cantidad_pendiente' => $this->obtenerCampoSeguro($grupo, 'cantidad_pendiente', 0),
                    'peso_pendiente' => $this->obtenerCampoSeguro($grupo, 'peso_pendiente', 0),
                    'estado_progreso' => $estadoVisual,
                    'estado' => $this->obtenerCampoSeguro($grupo, 'estado', 'pendiente'),
                    'productos_asignados' => $this->obtenerCampoSeguro(
                        $grupo,
                        'productos_asignados',
                        $this->obtenerCampoSeguro($grupo, 'productos', '')
                    ),
                    'tiene_asignaciones' => $this->obtenerCampoSeguro($grupo, 'total_asignado', 0) > 0,
                    'prioridad' => $this->obtenerCampoSeguro($grupo, 'prioridad', '9999-12-31'),
                    'orden_visual' => $this->determinarOrdenVisual($grupo),
                    'cantidad_ya_despachada' => $this->obtenerCampoSeguro($grupo, 'cantidad_ya_despachada', 0),
                    'peso_ya_despachado' => $this->obtenerCampoSeguro($grupo, 'peso_ya_despachado', 0),
                    'cantidad_disponible' => $this->obtenerCampoSeguro($grupo, 'cantidad_disponible', 0),
                    'info_despachos' => $this->obtenerCampoSeguro($grupo, 'info_despachos', []),

                    // 🆕 PROPIEDADES PARA FLEXIBILIDAD
                    'fuera_de_rejilla' => $modoAsignacion === 'desconocido_fuera_rejilla',
                    'liberado_de_reserva' => in_array($modoAsignacion, ['desconocido_flexible', 'automatico_flexible']),
                    'requiere_atencion_especial' => $modoAsignacion === 'desconocido_fuera_rejilla',
                    'sistema_flexible_aplicado' => in_array($modoAsignacion, ['desconocido_flexible', 'automatico_flexible'])
                ];
            }

            usort($resultado, function ($a, $b) {
                return $a['orden_visual'] <=> $b['orden_visual'];
            });

            return $resultado;
        } catch (Exception $e) {
            error_log("Error obteniendo vista previa: " . $e->getMessage());
            return [];
        }
    }


    public function obtenerResumenExpedicion($numeroExpedicion)
    {
        try {
            $clientes = $this->obtenerVistaPreviaClientes($numeroExpedicion);

            $estadisticas = [
                'total_clientes' => 0,
                'clientes_pendientes' => 0,
                'clientes_en_progreso' => 0,
                'clientes_completados' => 0,
                'items_desconocidos' => 0,
                'items_fuera_de_rejilla' => 0,
                'total_items_escaneados' => 0,
                'total_peso_escaneado' => 0,
                'total_asignado' => 0,
                'total_peso_asignado' => 0,
                'progreso_general' => 0
            ];

            foreach ($clientes as $cliente) {
                if ($cliente['es_desconocido']) {
                    $estadisticas['items_desconocidos']++;
                    if ($cliente['fuera_de_rejilla']) {
                        $estadisticas['items_fuera_de_rejilla']++;
                    }
                } else {
                    $estadisticas['total_clientes']++;

                    $estado = $cliente['estado_progreso']['estado'];
                    switch ($estado) {
                        case 'pendiente':
                            $estadisticas['clientes_pendientes']++;
                            break;
                        case 'iniciado':
                        case 'en_progreso':
                        case 'casi_completo':
                            $estadisticas['clientes_en_progreso']++;
                            break;
                        case 'completado':
                            $estadisticas['clientes_completados']++;
                            break;
                    }

                    $estadisticas['total_asignado'] += $cliente['total_asignado'];
                    $estadisticas['total_peso_asignado'] += $cliente['peso_asignado'];
                }

                $estadisticas['total_items_escaneados'] += $cliente['total_items_escaneados'];
                $estadisticas['total_peso_escaneado'] += $cliente['peso_total_escaneado'];
            }

            if ($estadisticas['total_asignado'] > 0) {
                $totalEscaneadoClientes = 0;
                foreach ($clientes as $cliente) {
                    if (!$cliente['es_desconocido']) {
                        $totalEscaneadoClientes += $cliente['total_items_escaneados'];
                    }
                }
                $estadisticas['progreso_general'] = round(($totalEscaneadoClientes / $estadisticas['total_asignado']) * 100, 1);
            }

            return [
                'success' => true,
                'clientes' => $clientes,
                'estadisticas' => $estadisticas,
                'expedicion' => $numeroExpedicion
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo resumen: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function procesarDespacho($numeroExpedicion, $usuario)
    {
        try {
            $expedicion = $this->repository->verificarExpedicion($numeroExpedicion);
            if (!$expedicion) {
                throw new Exception("Expedición no encontrada");
            }

            if ($expedicion['estado'] !== 'ABIERTA') {
                throw new Exception("La expedición ya está despachada");
            }

            $items = $this->repository->obtenerItemsParaDespacho($numeroExpedicion);
            if (empty($items)) {
                throw new Exception("No hay items en la expedición para despachar");
            }

            $itemsConInfo = [];
            $estadisticasDespacho = [
                'total_items' => 0,
                'total_peso' => 0,
                'items_con_asignacion' => 0,
                'items_desconocidos' => 0,
                'items_fuera_de_rejilla' => 0,
                'asignaciones_afectadas' => 0,
                'ventas_afectadas' => []
            ];

            foreach ($items as $item) {
                $itemInfo = [
                    'id_stock' => $item['id_stock'],
                    'cliente_asignado' => $item['cliente_asignado'] ?: $item['cliente'],
                    'id_venta_asignado' => $item['id_venta_asignado'] ?: $item['id_venta'],
                    'id_asignacion_rejilla' => $item['id_asignacion_rejilla']
                ];

                $itemsConInfo[] = $itemInfo;

                $estadisticasDespacho['total_items']++;
                $estadisticasDespacho['total_peso'] += (float)$item['peso_bruto'];

                if ($item['id_asignacion_rejilla']) {
                    $estadisticasDespacho['items_con_asignacion']++;
                } else {
                    $estadisticasDespacho['items_desconocidos']++;
                }

                $idVenta = $item['id_venta_asignado'] ?: $item['id_venta'];
                if ($idVenta && !in_array($idVenta, $estadisticasDespacho['ventas_afectadas'])) {
                    $estadisticasDespacho['ventas_afectadas'][] = $idVenta;
                }
            }

            $asignacionesUnicas = array_filter(array_unique(array_column($itemsConInfo, 'id_asignacion_rejilla')));
            $estadisticasDespacho['asignaciones_afectadas'] = count($asignacionesUnicas);

            $marcados = $this->repository->marcarItemsDespachados($itemsConInfo);
            if (!$marcados) {
                throw new Exception("Error al marcar items como despachados");
            }

            $cerrada = $this->repository->cerrarExpedicion($numeroExpedicion, $usuario);
            if (!$cerrada) {
                throw new Exception("Error al cerrar la expedición");
            }

            $mensajeVentas = '';
            if (!empty($estadisticasDespacho['ventas_afectadas'])) {
                $totalVentas = count($estadisticasDespacho['ventas_afectadas']);
                $mensajeVentas = " - {$totalVentas} venta(s) actualizada(s): " . implode(', ', $estadisticasDespacho['ventas_afectadas']);
            }

            return [
                'success' => true,
                'mensaje' => "Expedición {$numeroExpedicion} despachada exitosamente desde Rejilla #{$expedicion['numero_rejilla']}{$mensajeVentas}",
                'expedicion' => $numeroExpedicion,
                'rejilla' => $expedicion['numero_rejilla'],
                'total_items' => $estadisticasDespacho['total_items'],
                'total_peso_kg' => round($estadisticasDespacho['total_peso'], 2),
                'items_despachados' => array_column($itemsConInfo, 'id_stock'),
                'usuario_despacho' => $usuario,
                'fecha_despacho' => date('d/m/Y H:i:s'),
                'actualizaciones_cliente' => count($itemsConInfo),
                'asignaciones_completadas' => $estadisticasDespacho['asignaciones_afectadas'],
                'items_con_asignacion' => $estadisticasDespacho['items_con_asignacion'],
                'items_desconocidos_despachados' => $estadisticasDespacho['items_desconocidos'],
                'items_fuera_de_rejilla_despachados' => $estadisticasDespacho['items_fuera_de_rejilla'],
                'asignaciones_rejilla_actualizadas' => $estadisticasDespacho['asignaciones_afectadas'],
                'ventas_actualizadas' => $estadisticasDespacho['ventas_afectadas'],
                'total_ventas_actualizadas' => count($estadisticasDespacho['ventas_afectadas']),
                'estado_ventas_nuevo' => 'Finalizado',
                'estadisticas_completas' => $estadisticasDespacho
            ];
        } catch (Exception $e) {
            error_log("Error en despacho: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function obtenerClientesDisponibles()
    {
        return $this->repository->obtenerClientesDisponibles();
    }

    public function obtenerClientesMismaRejilla($numeroExpedicion)
    {
        return $this->repository->obtenerClientesMismaRejilla($numeroExpedicion);
    }

    private function obtenerCampoSeguro($array, $campo, $valorPorDefecto = null)
    {
        if (!is_array($array)) {
            return $valorPorDefecto;
        }

        if (!array_key_exists($campo, $array)) {
            return $valorPorDefecto;
        }

        $valor = $array[$campo];
        if ($valor === null) {
            return $valorPorDefecto;
        }

        return $valor;
    }

    private function determinarEstadoVisual($grupo)
    {
        if ($grupo['es_desconocido']) {
            if (isset($grupo['modo_asignacion']) && $grupo['modo_asignacion'] === 'desconocido_fuera_rejilla') {
                return [
                    'clase' => 'bg-info text-white',
                    'estado' => 'fuera_de_rejilla',
                    'icono' => '📍',
                    'mensaje' => 'Item fuera de rejilla - Requiere reasignación'
                ];
            }

            return [
                'clase' => 'bg-warning text-dark',
                'estado' => 'desconocido',
                'icono' => '❓',
                'mensaje' => 'Requiere reasignación'
            ];
        }

        if ($grupo['total_asignado'] <= 0) {
            return [
                'clase' => 'bg-secondary',
                'estado' => 'sin_asignacion',
                'icono' => '⚪',
                'mensaje' => 'Sin asignaciones'
            ];
        }

        $porcentaje = $grupo['progreso_cantidad'];

        if ($porcentaje >= 100) {
            return [
                'clase' => 'bg-success',
                'estado' => 'completado',
                'icono' => '✅',
                'mensaje' => 'Asignación completada'
            ];
        } elseif ($porcentaje >= 75) {
            return [
                'clase' => 'bg-warning',
                'estado' => 'casi_completo',
                'icono' => '🟡',
                'mensaje' => 'Casi completado'
            ];
        } elseif ($porcentaje >= 25) {
            return [
                'clase' => 'bg-info',
                'estado' => 'en_progreso',
                'icono' => '🔵',
                'mensaje' => 'En progreso'
            ];
        } elseif ($porcentaje > 0) {
            return [
                'clase' => 'bg-primary',
                'estado' => 'iniciado',
                'icono' => '🔵',
                'mensaje' => 'Iniciado'
            ];
        } else {
            return [
                'clase' => 'bg-light text-dark',
                'estado' => 'pendiente',
                'icono' => '⏳',
                'mensaje' => 'Esperando items'
            ];
        }
    }

    private function determinarOrdenVisual($grupo)
    {
        if ($grupo['es_desconocido']) {
            if (isset($grupo['modo_asignacion']) && $grupo['modo_asignacion'] === 'desconocido_fuera_rejilla') {
                return 7;
            }
            return 8;
        }
        if ($grupo['total_asignado'] <= 0) return 7;

        $progreso = $grupo['progreso_cantidad'];

        if ($progreso >= 100) return 5;
        if ($progreso >= 75) return 2;
        if ($progreso >= 25) return 3;
        if ($progreso > 0) return 1;

        return 4;
    }


    private function validarDatosExpedicion($datos)
    {
        if (empty(trim($datos['transportista'] ?? ''))) {
            throw new Exception('El transportista es requerido');
        }

        if (empty($datos['id_rejilla'])) {
            throw new Exception('La rejilla es obligatoria para crear una expedición');
        }

        if (!is_numeric($datos['id_rejilla']) || $datos['id_rejilla'] <= 0) {
            throw new Exception('La rejilla seleccionada no es válida');
        }

        if (!empty($datos['peso']) && (!is_numeric($datos['peso']) || $datos['peso'] < 0)) {
            throw new Exception('El peso debe ser un número válido');
        }

        if (!empty($datos['placa']) && strlen(trim($datos['placa'])) > 50) {
            throw new Exception('La placa no puede exceder 50 caracteres');
        }

        return true;
    }

    public function enriquecerExpediciones($expediciones)
    {
        return array_map(function ($expedicion) {
            return $this->enriquecerExpedicion($expedicion);
        }, $expediciones);
    }

    public function enriquecerExpedicion($expedicion)
    {
        $expedicion['fecha_creacion_formateada'] = $this->formatearFecha($expedicion['fecha_creacion']);

        if ($expedicion['fecha_despacho']) {
            $expedicion['fecha_despacho_formateada'] = $this->formatearFecha($expedicion['fecha_despacho']);
        }

        $expedicion['peso_total_formateado'] = number_format($expedicion['peso_total'] ?? 0, 2);
        $expedicion['tiempo_abierta'] = $this->calcularTiempoTranscurrido($expedicion['fecha_creacion']);

        $horasAbierta = $this->calcularHorasTranscurridas($expedicion['fecha_creacion']);
        $expedicion['requiere_atencion'] = $horasAbierta > 2;

        $expedicion['rejilla_info'] = [
            'numero' => $expedicion['numero_rejilla'],
            'validacion_activa' => true,
            'modo_automatico_unico' => true,
            'permite_items_fuera_rejilla' => true,
            'badge_text' => "Rejilla #{$expedicion['numero_rejilla']} - Automático + Flexibilidad"
        ];

        return $expedicion;
    }

    public function obtenerRejillasConAsignaciones()
    {
        try {
            $rejillas = $this->repository->obtenerRejillasConAsignaciones();

            return array_map(function ($rejilla) {
                $rejilla['capacidad_disponible'] = $rejilla['capacidad_maxima'] - $rejilla['peso_actual'];
                $rejilla['porcentaje_uso'] = ($rejilla['peso_actual'] / $rejilla['capacidad_maxima']) * 100;
                $rejilla['peso_actual_formateado'] = number_format($rejilla['peso_actual'], 2);
                $rejilla['peso_total_asignado_formateado'] = number_format($rejilla['peso_total_asignado'] ?? 0, 2);

                return $rejilla;
            }, $rejillas);
        } catch (Exception $e) {
            error_log("Error obteniendo rejillas: " . $e->getMessage());
            return [];
        }
    }

    public function calcularEstadisticas($expediciones)
    {
        $totalExpediciones = count($expediciones);
        $totalItems = array_sum(array_column($expediciones, 'total_items'));

        return [
            'total_expediciones' => $totalExpediciones,
            'total_items' => $totalItems,
            'total_rejillas' => count($this->obtenerRejillasConAsignaciones()),
            'sistema_validacion' => 'REJILLA_OBLIGATORIA_FLEXIBLE_AUTOMATICO'
        ];
    }

    public function obtenerTransportistas()
    {
        try {
            return $this->repository->obtenerTransportistasPorDescripcion();
        } catch (Exception $e) {
            error_log("Error obteniendo transportistas en service: " . $e->getMessage());
            return [
                'AMERICA TNT TRANSPORT',
                'LOGISTICA EXPRESS',
                'TRANSPORTE SEGURO'
            ];
        }
    }

    public function obtenerTiposVehiculo()
    {
        try {
            return $this->repository->obtenerTiposVehiculoPorNombre();
        } catch (Exception $e) {
            error_log("Error obteniendo tipos de vehículo en service: " . $e->getMessage());
            return [
                'CAMION',
                'CAMIONETA',
                'FURGON'
            ];
        }
    }

    public function validarEstadoSistema()
    {
        try {
            $rejillas = $this->obtenerRejillasConAsignaciones();
            $tieneRejillasDisponibles = count($rejillas) > 0;

            $mensaje = '';
            if ($tieneRejillasDisponibles) {
                $mensaje = "Sistema operativo con validación de rejilla flexible y modo automático. " . count($rejillas) . " rejillas disponibles. Permite items fuera de rejilla como DESCONOCIDOS."; // 🆕 Mensaje actualizado
            } else {
                $mensaje = "Sin rejillas disponibles para crear expediciones";
            }

            return [
                'sistema_operativo' => $tieneRejillasDisponibles,
                'rejillas_disponibles' => $tieneRejillasDisponibles,
                'puede_crear_expediciones' => $tieneRejillasDisponibles,
                'mensaje' => $mensaje,
                'validacion_rejilla' => 'OBLIGATORIA_FLEXIBLE',
                'modo_automatico_unico' => true,
                'permite_items_fuera_rejilla' => true,
                'total_rejillas' => count($rejillas)
            ];
        } catch (Exception $e) {
            return [
                'sistema_operativo' => false,
                'rejillas_disponibles' => false,
                'puede_crear_expediciones' => false,
                'mensaje' => 'Error en el sistema: ' . $e->getMessage(),
                'validacion_rejilla' => 'ERROR',
                'modo_automatico_unico' => true,
                'permite_items_fuera_rejilla' => false
            ];
        }
    }
    public function obtenerItemsEscaneadosDetallados($numeroExpedicion)
    {
        try {
            $items = $this->repository->obtenerItemsEscaneadosDetallados($numeroExpedicion);

            $itemsEnriquecidos = array_map(function ($item) {
                $item['tipo_display'] = $this->determinarTipoDisplay($item);
                $item['clase_estado'] = $this->determinarClaseEstado($item);
                $item['icono_estado'] = $this->determinarIconoEstado($item);

                return $item;
            }, $items);

            return [
                'success' => true,
                'items' => $itemsEnriquecidos,
                'total_items' => count($itemsEnriquecidos),
                'estadisticas' => $this->calcularEstadisticasItems($itemsEnriquecidos),
                'expedicion' => $numeroExpedicion
            ];
        } catch (Exception $e) {
            error_log("Error en service obteniendo items escaneados: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function eliminarItemEscaneado($idExpedicionItem, $numeroExpedicion, $usuario)
    {
        try {
            $resultado = $this->repository->eliminarItemEscaneado($idExpedicionItem, $numeroExpedicion);

            if ($resultado['eliminado']) {
                $itemInfo = $resultado['item_info'];

                return [
                    'success' => true,
                    'mensaje' => "Item {$itemInfo['numero_item']} eliminado exitosamente",
                    'item_eliminado' => [
                        'numero_item' => $itemInfo['numero_item'],
                        'nombre_producto' => $itemInfo['nombre_producto'],
                        'cliente_asignado' => $itemInfo['cliente_asignado'],
                        'cantidad_escaneada' => $itemInfo['cantidad_escaneada'],
                        'peso_escaneado' => number_format($itemInfo['peso_escaneado'], 2) . ' kg'
                    ],
                    'id_stock_restaurado' => $itemInfo['id_stock'],
                    'usuario_eliminacion' => $usuario,
                    'fecha_eliminacion' => date('d/m/Y H:i:s')
                ];
            } else {
                throw new Exception("No se pudo eliminar el item de la expedición");
            }
        } catch (Exception $e) {
            error_log("Error en service eliminando item: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function eliminarMultiplesItems($idsItems, $numeroExpedicion, $usuario)
    {
        try {
            if (empty($idsItems) || !is_array($idsItems)) {
                throw new Exception("Debe proporcionar al menos un ID de item válido");
            }

            $resultado = $this->repository->eliminarMultiplesItemsEscaneados($idsItems, $numeroExpedicion);

            $itemsEliminados = $resultado['items_eliminados'] ?? [];
            $errores = $resultado['errores'] ?? [];
            $totalEliminados = count($itemsEliminados);
            $totalErrores = count($errores);
            $totalSolicitados = count($idsItems);

            $mensaje = "Eliminación completada: {$totalEliminados} de {$totalSolicitados} items eliminados";

            if ($totalErrores > 0) {
                $mensaje .= " ({$totalErrores} errores)";
            }

            return [
                'success' => $totalEliminados > 0,
                'mensaje' => $mensaje,
                'total_eliminados' => $totalEliminados,
                'total_errores' => $totalErrores,
                'total_solicitados' => $totalSolicitados,
                'items_eliminados' => $itemsEliminados,
                'errores' => $errores,
                'usuario_eliminacion' => $usuario,
                'fecha_eliminacion' => date('d/m/Y H:i:s'),
                'porcentaje_exito' => $totalSolicitados > 0 ? round(($totalEliminados / $totalSolicitados) * 100, 1) : 0
            ];
        } catch (Exception $e) {
            error_log("Error en service eliminando múltiples items: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function eliminarExpedicion($numeroExpedicion, $usuario)
    {
        try {
            $resultado = $this->repository->eliminarExpedicion($numeroExpedicion);

            return [
                'success' => true,
                'mensaje' => "Expedición {$numeroExpedicion} eliminada exitosamente",
                'items_eliminados' => $resultado['items_eliminados']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    private function determinarTipoDisplay($item)
    {
        if ($item['es_desconocido']) {
            if ($item['modo_asignacion'] === 'desconocido_fuera_rejilla') {
                return 'Fuera de rejilla';
            }
            return 'DESCONOCIDO';
        }

        return 'Asignado';
    }

    private function determinarClaseEstado($item)
    {
        if ($item['es_desconocido']) {
            if ($item['modo_asignacion'] === 'desconocido_fuera_rejilla') {
                return 'badge bg-info text-white';
            }
            return 'badge bg-warning text-dark';
        }

        return 'badge bg-success text-white';
    }

    private function determinarIconoEstado($item)
    {
        if ($item['es_desconocido']) {
            if ($item['modo_asignacion'] === 'desconocido_fuera_rejilla') {
                return '📍';
            }
            return '❓';
        }

        return '✅';
    }

    private function calcularEstadisticasItems($items)
    {
        $estadisticas = [
            'total_items' => count($items),
            'items_asignados' => 0,
            'items_desconocidos' => 0,
            'items_fuera_rejilla' => 0,
            'peso_total' => 0,
            'cantidad_total' => 0
        ];

        foreach ($items as $item) {
            $estadisticas['peso_total'] += $item['peso_escaneado'];
            $estadisticas['cantidad_total'] += $item['cantidad_escaneada'];

            if ($item['es_desconocido']) {
                $estadisticas['items_desconocidos']++;

                if ($item['modo_asignacion'] === 'desconocido_fuera_rejilla') {
                    $estadisticas['items_fuera_rejilla']++;
                }
            } else {
                $estadisticas['items_asignados']++;
            }
        }

        $estadisticas['peso_total_formateado'] = number_format($estadisticas['peso_total'], 2) . ' kg';

        return $estadisticas;
    }

    private function formatearFecha($fecha, $formato = 'd/m/Y H:i')
    {
        if (empty($fecha)) {
            return 'N/A';
        }

        try {
            $timestamp = is_string($fecha) ? strtotime($fecha) : $fecha;
            return date($formato, $timestamp);
        } catch (Exception $e) {
            return $fecha;
        }
    }

    private function calcularTiempoTranscurrido($fechaInicio)
    {
        try {
            $inicio = new DateTime($fechaInicio);
            $ahora = new DateTime();
            $diferencia = $ahora->diff($inicio);

            if ($diferencia->days > 0) {
                return $diferencia->days . ' días';
            } elseif ($diferencia->h > 0) {
                return $diferencia->h . ' horas';
            } else {
                return $diferencia->i . ' minutos';
            }
        } catch (Exception $e) {
            return 'N/A';
        }
    }

    private function calcularHorasTranscurridas($fechaInicio)
    {
        try {
            $inicio = new DateTime($fechaInicio);
            $ahora = new DateTime();
            $diferencia = $ahora->diff($inicio);

            return ($diferencia->days * 24) + $diferencia->h + ($diferencia->i / 60);
        } catch (Exception $e) {
            return 0;
        }
    }
}
