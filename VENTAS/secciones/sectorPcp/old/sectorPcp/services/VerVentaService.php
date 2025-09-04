<?php

class VerVentaService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function obtenerVentaParaProcesamiento($idVenta)
    {
        $venta = $this->repository->obtenerVentaPorId($idVenta);

        if (!$venta) {
            throw new Exception('Venta no encontrada o ya procesada');
        }

        $venta['simbolo_moneda'] = $this->obtenerSimboloMoneda($venta['moneda']);
        $productos = $this->repository->obtenerProductosVenta($idVenta);
        $venta['productos'] = $this->procesarProductosParaVista($productos, $idVenta);

        if ($venta['id_autorizacion']) {
            $venta['imagenes_autorizacion'] = $this->repository->obtenerImagenesAutorizacion($venta['id_autorizacion']);
        } else {
            $venta['imagenes_autorizacion'] = [];
        }

        $venta['imagenes_productos'] = $this->obtenerImagenesProductosMapeadas($productos);
        $nombresProductos = array_column($productos, 'descripcion');
        $venta['stock_general'] = $this->repository->obtenerStockGeneral($nombresProductos);

        return $venta;
    }

    private function procesarProductosParaVista($productos, $idVenta)
    {
        $productosConCantidades = [];
        $itemsEnviadosExpedicion = $this->repository->obtenerItemsEnviadosExpedicion($idVenta);

        foreach ($productos as $producto) {
            $cantidadEfectiva = $this->obtenerCantidadEfectiva($producto['id_producto'], $producto['tipoproducto']);
            $estoallita = (strtolower($producto['tipoproducto']) === 'toallitas');
            $espanos = (strtolower($producto['tipoproducto']) === 'paños');

            $cantidadExpedicion = 0;
            $cantidadDesdeStock = 0;
            foreach ($itemsEnviadosExpedicion as $itemExp) {
                if ($itemExp['id_producto_venta'] == $producto['id']) {
                    $cantidadExpedicion = $itemExp['cantidad_expedicion'];
                    $cantidadDesdeStock = $itemExp['cantidad_desde_stock'];
                    break;
                }
            }

            $totalExpedicion = $cantidadExpedicion + $cantidadDesdeStock;

            if ($estoallita) {
                $maxProduccion = $producto['cantidad'] - $totalExpedicion;
                $unidadMedida = 'Cajas';
                $pesoParaMostrar = $cantidadEfectiva;
            } elseif ($espanos) {
                $maxProduccion = $producto['cantidad'] - $totalExpedicion;
                $unidadMedida = !empty($producto['unidadmedida']) ? $producto['unidadmedida'] : 'Unidades';
                $pesoParaMostrar = $cantidadEfectiva;
            } else {
                $pesoTotal = (float)$producto['cantidad'];
                $pesoRestante = $pesoTotal - $totalExpedicion;
                $maxProduccion = $cantidadEfectiva > 0 ? round($pesoRestante / $cantidadEfectiva) : 0;
                $unidadMedida = 'Bobinas';
                $pesoParaMostrar = $cantidadEfectiva;
            }

            $producto['cantidad_efectiva'] = $cantidadEfectiva;
            $producto['max_produccion'] = max(0, $maxProduccion);
            $producto['cantidad_expedicion'] = $totalExpedicion;
            $producto['cantidad_desde_stock'] = $cantidadDesdeStock;
            $producto['estoallita'] = $estoallita;
            $producto['espanos'] = $espanos;
            $producto['unidad_medida'] = $unidadMedida;
            $producto['peso_por_bobina'] = $pesoParaMostrar;

            $productosConCantidades[] = $producto;
        }

        return $productosConCantidades;
    }

    public function finalizarVenta($idVenta, $observaciones, $idUsuario)
    {
        try {
            $conexion = $this->repository->getConexion();
            $conexion->beginTransaction();

            $fechaProcesamiento = date('Y-m-d H:i:s');
            $nuevoEstado = 'Finalizado Manualmente';

            // Actualizar el estado de la venta
            $resultadoActualizacion = $this->repository->actualizarEstadoVenta($idVenta, $nuevoEstado);

            if (!$resultadoActualizacion) {
                throw new Exception('No se pudo actualizar el estado de la venta');
            }

            // Insertar en el proceso PCP para mantener el historial
            $datosProceso = [
                'id_venta' => $idVenta,
                'id_usuario' => $idUsuario,
                'fecha_procesamiento' => $fechaProcesamiento,
                'observaciones' => $observaciones ?: 'Venta finalizada desde PCP',
                'estado' => $nuevoEstado
            ];
            $this->repository->insertarProcesoPcp($datosProceso);

            $conexion->commit();

            // Insertar en el historial de acciones
            $this->insertarHistorialAccion([
                'id_venta' => $idVenta,
                'id_usuario' => $idUsuario,
                'sector' => 'PCP',
                'accion' => 'Finalizar Venta',
                'fecha_accion' => $fechaProcesamiento,
                'observaciones' => $observaciones ?: 'Venta finalizada',
                'estado_resultante' => $nuevoEstado
            ]);

            return [
                'success' => true,
                'mensaje' => 'Venta finalizada correctamente'
            ];
        } catch (Exception $e) {
            $conexion = $this->repository->getConexion();
            $conexion->rollBack();
            error_log("Error finalizando venta: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al finalizar la venta: ' . $e->getMessage()
            ];
        }
    }

    private function obtenerCantidadEfectiva($idProducto, $tipoProducto)
    {
        try {
            $tipoLower = strtolower($tipoProducto);

            if ($tipoLower === 'toallitas' || $tipoLower === 'paños') {
                return 1;
            }

            $productosEnKilos = [
                'tnt',
                'spunlace',
                'laminadora',
                'laminado',
                'laminados',
                'lamina',
                'laminas'
            ];

            if (in_array($tipoLower, $productosEnKilos)) {
                $cantidadEfectiva = $this->repository->obtenerCantidadEfectivaProducto($idProducto);
                return $cantidadEfectiva;
            }

            $cantidadEfectiva = $this->repository->obtenerCantidadEfectivaProducto($idProducto);
            return $cantidadEfectiva;
        } catch (Exception $e) {
            error_log("Error obteniendo cantidad efectiva: " . $e->getMessage());
            return 1;
        }
    }

    private function obtenerImagenesProductosMapeadas($productos)
    {
        $mapeoProductos = [];
        foreach ($productos as $producto) {
            if (!empty($producto['id_producto'])) {
                $mapeoProductos[$producto['id']] = $producto['id_producto'];
            }
        }

        if (empty($mapeoProductos)) {
            return [];
        }

        $idsProductos = array_values($mapeoProductos);
        $imagenes = $this->repository->obtenerImagenesProductos($idsProductos);

        $imagenesProductos = [];
        foreach ($imagenes as $img) {
            foreach ($mapeoProductos as $idLineaPresupuesto => $idProductoCatalogo) {
                if ($idProductoCatalogo == $img['id']) {
                    $imagenesProductos[$idLineaPresupuesto] = [
                        'imagen' => $img['imagen'],
                        'tipo' => $img['tipo'],
                        'nombre' => $img['nombre']
                    ];
                }
            }
        }

        return $imagenesProductos;
    }

    public function procesarVenta($idVenta, $observaciones, $idUsuario)
    {
        try {
            $conexion = $this->repository->getConexion();
            $conexion->beginTransaction();

            $fechaProcesamiento = date('Y-m-d H:i:s');
            $nuevoEstado = 'Procesado';

            $this->repository->actualizarEstadoVenta($idVenta, $nuevoEstado);

            $datosProceso = [
                'id_venta' => $idVenta,
                'id_usuario' => $idUsuario,
                'fecha_procesamiento' => $fechaProcesamiento,
                'observaciones' => $observaciones,
                'estado' => $nuevoEstado
            ];
            $this->repository->insertarProcesoPcp($datosProceso);

            $conexion->commit();

            $this->insertarHistorialAccion([
                'id_venta' => $idVenta,
                'id_usuario' => $idUsuario,
                'sector' => 'PCP',
                'accion' => 'Procesar',
                'fecha_accion' => $fechaProcesamiento,
                'observaciones' => $observaciones,
                'estado_resultante' => $nuevoEstado
            ]);

            return ['success' => true, 'mensaje' => 'Venta procesada correctamente'];
        } catch (Exception $e) {
            $conexion = $this->repository->getConexion();
            $conexion->rollBack();
            error_log("Error procesando venta: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al procesar la venta'];
        }
    }

    public function devolverVentaContabilidad($idVenta, $motivoDevolucion, $idUsuario)
    {
        try {
            $conexion = $this->repository->getConexion();
            $conexion->beginTransaction();

            $fechaProcesamiento = date('Y-m-d H:i:s');
            $nuevoEstado = 'Devuelto a Contabilidad';

            $this->repository->actualizarEstadoVenta($idVenta, $nuevoEstado);
            $this->repository->actualizarEstadoAutorizacion($idVenta, $nuevoEstado);

            $datosProcesoPcp = [
                'id_venta' => $idVenta,
                'id_usuario' => $idUsuario,
                'fecha_procesamiento' => $fechaProcesamiento,
                'observaciones' => $motivoDevolucion,
                'estado' => $nuevoEstado
            ];
            $this->repository->insertarProcesoPcp($datosProcesoPcp);

            $conexion->commit();

            $this->insertarHistorialAccion([
                'id_venta' => $idVenta,
                'id_usuario' => $idUsuario,
                'sector' => 'PCP',
                'accion' => 'Devolver',
                'fecha_accion' => $fechaProcesamiento,
                'observaciones' => $motivoDevolucion,
                'estado_resultante' => $nuevoEstado
            ]);

            return ['success' => true, 'mensaje' => 'Venta devuelta a contabilidad'];
        } catch (Exception $e) {
            $conexion = $this->repository->getConexion();
            $conexion->rollBack();
            error_log("Error devolviendo venta: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al devolver la venta'];
        }
    }

    public function enviarProductosProduccion($idVenta, $cantidadesProduccion, $observaciones, $idUsuario)
    {
        try {
            $conexion = $this->repository->getConexion();
            $conexion->beginTransaction();

            $fechaProcesamiento = date('Y-m-d H:i:s');
            $productos = $this->repository->obtenerProductosVenta($idVenta);
            $totalProductosProduccion = 0;

            foreach ($productos as $producto) {
                $idProducto = $producto['id'];
                $cantidadProduccionInput = floatval($cantidadesProduccion[$idProducto] ?? 0);
                $tipoProducto = strtolower($producto['tipoproducto']);

                if ($cantidadProduccionInput > 0) {
                    $productosDirectos = ['toallitas', 'paños'];

                    if (in_array($tipoProducto, $productosDirectos)) {
                        $cantidadProduccionReal = $cantidadProduccionInput;
                    } else {
                        $cantidadEfectiva = $this->repository->obtenerCantidadEfectivaProducto($producto['id_producto']);
                        $cantidadProduccionReal = $cantidadProduccionInput * $cantidadEfectiva;
                    }

                    $datosProduccion = [
                        'id_venta' => $idVenta,
                        'id_producto' => $idProducto,
                        'id_usuario' => $idUsuario,
                        'fecha_asignacion' => $fechaProcesamiento,
                        'destino' => 'Producción',
                        'cantidad' => $cantidadProduccionReal,
                        'observaciones' => $observaciones
                    ];

                    $this->repository->insertarProductoProduccion($datosProduccion);
                    $totalProductosProduccion++;
                }
            }

            if ($totalProductosProduccion === 0) {
                $conexion->rollBack();
                return ['success' => false, 'error' => 'No se especificaron productos para enviar a producción'];
            }

            $nuevoEstado = 'En Producción';
            $this->repository->actualizarEstadoVenta($idVenta, $nuevoEstado);

            $conexion->commit();

            $this->insertarHistorialAccion([
                'id_venta' => $idVenta,
                'id_usuario' => $idUsuario,
                'sector' => 'PCP',
                'accion' => 'Enviar a Produccion',
                'fecha_accion' => $fechaProcesamiento,
                'observaciones' => $observaciones,
                'estado_resultante' => $nuevoEstado
            ]);

            return [
                'success' => true,
                'mensaje' => "Venta enviada a producción. $totalProductosProduccion productos enviados"
            ];
        } catch (Exception $e) {
            $conexion = $this->repository->getConexion();
            $conexion->rollBack();
            error_log("Error enviando productos a producción: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al enviar productos a producción'];
        }
    }


    /**
     * MÉTODO ÚNICO: Crear reservas de stock usando el sistema mejorado
     */
    public function crearReservasStock($idVenta, $cantidadesBobinas, $observaciones, $idUsuario)
    {
        // ✅ CORREGIDO: Una sola transacción
        try {
            $conexion = $this->repository->getConexion();
            $conexion->beginTransaction();

            $fechaProcesamiento = date('Y-m-d H:i:s');
            $ventaInfo = $this->repository->obtenerInfoVentaParaExpedicion($idVenta);

            if (!$ventaInfo) {
                throw new Exception('No se pudo obtener información de la venta');
            }

            $clienteVenta = $ventaInfo['cliente'];
            $totalReservasCreadas = 0;
            $reservasIds = [];
            $detallesReservas = [];
            $productosVenta = $this->repository->obtenerProductosVenta($idVenta);

            foreach ($cantidadesBobinas as $nombreProducto => $bobinasSolicitadas) {
                $bobinasSolicitadas = (int)$bobinasSolicitadas;
                if ($bobinasSolicitadas <= 0) continue;

                // ✅ LLAMAR A LA FUNCIÓN POSTGRESQL DIRECTAMENTE (sin transacciones anidadas)
                $sql = "SELECT * FROM reservar_stock_paquetes_mejorado(
            :nombre_producto, 
            :bobinas_solicitadas,
            NULL, 
            :id_venta, 
            :cliente,
            :usuario
        )";

                $stmt = $conexion->prepare($sql);
                // ✅ MÉTODO MÁS SIMPLE: Pasar parámetros directamente
                $sql = "SELECT * FROM reservar_stock_paquetes_mejorado(?, ?, NULL, ?, ?, ?)";

                $stmt = $conexion->prepare($sql);
                $stmt->execute([
                    $nombreProducto,
                    $bobinasSolicitadas,
                    $idVenta,
                    $clienteVenta,
                    $_SESSION['nombre'] ?? 'SISTEMA'
                ]);

                $resultadoReserva = $stmt->fetch(PDO::FETCH_ASSOC);


                if (!$resultadoReserva || !$resultadoReserva['exito']) {
                    $conexion->rollBack();
                    return [
                        'success' => false,
                        'error' => "Error reservando $nombreProducto: " . ($resultadoReserva['mensaje'] ?? 'Error desconocido')
                    ];
                }

                $reservasIds[] = $resultadoReserva['id_reserva'];
                $totalReservasCreadas++;

                $detallesReservas[] = [
                    'producto' => $nombreProducto,
                    'bobinas_solicitadas' => $bobinasSolicitadas,
                    'paquetes_reservados' => $resultadoReserva['paquetes_reservados'] ?? 0,
                    'bobinas_reservadas' => $resultadoReserva['bobinas_reservadas'] ?? 0,
                    'id_reserva' => $resultadoReserva['id_reserva']
                ];

                foreach ($productosVenta as $productoVenta) {
                    if (trim($productoVenta['descripcion']) === $nombreProducto) {
                        // ✅ OBTENER cantidadEfectiva del producto
                        $cantidadEfectiva = $this->obtenerCantidadEfectiva(
                            $productoVenta['id_producto'],
                            $productoVenta['tipoproducto']
                        );

                        $cantidadBobinas = $resultadoReserva['bobinas_reservadas'] ?? $bobinasSolicitadas;

                        // ✅ CALCULAR cantidad = cantidad_bobinas * cantidadEfectiva
                        $cantidad = $cantidadBobinas * $cantidadEfectiva;

                        error_log("DEBUG - Producto: $nombreProducto | CantidadEfectiva: $cantidadEfectiva | CantidadBobinas: $cantidadBobinas | Cantidad calculada: $cantidad");

                        $datosExpedicion = [
                            'id_venta' => $idVenta,
                            'id_producto' => $productoVenta['id'],
                            'id_usuario' => $idUsuario,
                            'fecha_asignacion' => $fechaProcesamiento,
                            'cantidad' => $cantidad, // ✅ AGREGADO: cantidad = cantidad_bobinas * cantidadEfectiva
                            'cantidad_bobinas' => $cantidadBobinas,
                            'observaciones' => $observaciones . " | Reserva ID: " . $resultadoReserva['id_reserva'] .
                                " | Paquetes: " . ($resultadoReserva['paquetes_reservados'] ?? 0) .
                                " | Bobinas: " . ($resultadoReserva['bobinas_reservadas'] ?? 0) .
                                " | CantidadEfectiva: $cantidadEfectiva | Cantidad: $cantidad",
                            'origen' => 'stock_reservas_automaticas'
                        ];

                        $this->repository->insertarProductoExpedicion($datosExpedicion);
                        break;
                    }
                }
            }

            if ($totalReservasCreadas === 0) {
                $conexion->rollBack();
                return ['success' => false, 'error' => 'No se crearon reservas'];
            }

            // Actualizar estado de la venta
            $nuevoEstado = $this->determinarNuevoEstadoVenta($idVenta, $productosVenta);
            if ($nuevoEstado) {
                $this->repository->actualizarEstadoVenta($idVenta, $nuevoEstado);
            }

            $conexion->commit();

            // ... resto del código para mensajes y historial ...

            // Crear mensaje detallado
            $mensajeDetalles = [];
            foreach ($detallesReservas as $detalle) {
                $mensajeDetalles[] = "• {$detalle['producto']}: {$detalle['bobinas_solicitadas']} bobinas solicitadas → {$detalle['paquetes_reservados']} paquetes ({$detalle['bobinas_reservadas']} bobinas reservadas)";
            }

            $observacionesHistorial = "Reservas automáticas creadas: $totalReservasCreadas. IDs: " . implode(', ', $reservasIds) . "\n" . implode("\n", $mensajeDetalles);
            if (!empty($observaciones)) {
                $observacionesHistorial .= " | Observaciones: $observaciones";
            }

            $this->insertarHistorialAccion([
                'id_venta' => $idVenta,
                'id_usuario' => $idUsuario,
                'sector' => 'PCP',
                'accion' => 'Crear Reservas Stock Automáticas',
                'fecha_accion' => $fechaProcesamiento,
                'observaciones' => $observacionesHistorial,
                'estado_resultante' => $nuevoEstado ?: 'Reservas Creadas'
            ]);

            $mensajeExito = "Se crearon $totalReservasCreadas reservas automáticas:\n" . implode("\n", $mensajeDetalles);

            return [
                'success' => true,
                'mensaje' => $mensajeExito,
                'reservas_creadas' => $reservasIds,
                'total_reservas' => $totalReservasCreadas,
                'detalles' => $detallesReservas
            ];
        } catch (Exception $e) {
            $conexion = $this->repository->getConexion();
            $conexion->rollBack();
            error_log("Error creando reservas automáticas: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al crear reservas: ' . $e->getMessage()];
        }
    }

    // REEMPLAZAR las líneas 535-580 aproximadamente con esto:
    public function cancelarReservasVenta($idVenta, $idUsuario)
    {
        try {
            $conexion = $this->repository->getConexion();
            $conexion->beginTransaction();

            $reservas = $this->repository->obtenerReservasVenta($idVenta);
            $reservasActivas = array_filter($reservas, function ($r) {
                return $r['estado'] === 'activa';
            });

            if (empty($reservasActivas)) {
                return ['success' => false, 'error' => 'No hay reservas activas para cancelar'];
            }

            $reservasCanceladas = 0;
            $detallesCancelaciones = [];

            foreach ($reservasActivas as $reserva) {
                // Usar el nuevo método mejorado de cancelación
                $resultado = $this->repository->cancelarReservaMejorada(
                    $reserva['id'],
                    'Cancelación masiva desde PCP',
                    $_SESSION['nombre'] ?? 'SISTEMA'
                );

                if ($resultado['exito']) {
                    $reservasCanceladas++;
                    $detallesCancelaciones[] = [
                        'id_reserva' => $reserva['id'],
                        'producto' => $reserva['nombre_producto'],
                        'paquetes_liberados' => $resultado['paquetes_liberados'],
                        'bobinas_liberadas' => $resultado['bobinas_liberadas']
                    ];
                }
            }

            $conexion->commit();

            $observacionesDetalladas = "Reservas canceladas: $reservasCanceladas\n";
            foreach ($detallesCancelaciones as $detalle) {
                $observacionesDetalladas .= "• {$detalle['producto']}: {$detalle['paquetes_liberados']} paquetes, {$detalle['bobinas_liberadas']} bobinas liberadas\n";
            }

            $this->insertarHistorialAccion([
                'id_venta' => $idVenta,
                'id_usuario' => $idUsuario,
                'sector' => 'PCP',
                'accion' => 'Cancelar Reservas Masivas',
                'fecha_accion' => date('Y-m-d H:i:s'),
                'observaciones' => $observacionesDetalladas,
                'estado_resultante' => 'Reservas Canceladas'
            ]);

            return [
                'success' => true,
                'mensaje' => "Se cancelaron $reservasCanceladas reservas correctamente",
                'reservas_canceladas' => $reservasCanceladas,
                'detalles' => $detallesCancelaciones
            ];
        } catch (Exception $e) {
            $conexion = $this->repository->getConexion();
            $conexion->rollBack();
            error_log("Error cancelando reservas: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al cancelar reservas: ' . $e->getMessage()];
        }
    }


    private function determinarNuevoEstadoVenta($idVenta, $productosVenta)
    {
        $itemsEnviadosActualizados = $this->repository->obtenerItemsEnviadosExpedicion($idVenta);
        $hayProductosEnProduccion = $this->repository->verificarProductosEnProduccion($idVenta);
        $todoCompletamenteEnviado = true;

        foreach ($productosVenta as $producto) {
            $totalExpedicion = 0;
            foreach ($itemsEnviadosActualizados as $itemExp) {
                if ($itemExp['id_producto_venta'] == $producto['id']) {
                    $totalExpedicion = $itemExp['cantidad_expedicion'] + $itemExp['cantidad_desde_stock'];
                    break;
                }
            }

            if ($totalExpedicion < $producto['cantidad']) {
                $todoCompletamenteEnviado = false;
                break;
            }
        }

        if ($todoCompletamenteEnviado && !$hayProductosEnProduccion) {
            return 'En Expedición';
        } elseif ($hayProductosEnProduccion) {
            return 'En Producción';
        }

        return null;
    }

    private function insertarHistorialAccion($datos)
    {
        try {
            $this->repository->insertarHistorialAccion($datos);
        } catch (Exception $e) {
            error_log("Error insertando historial: " . $e->getMessage());
        }
    }

    public function obtenerSimboloMoneda($moneda)
    {
        switch ($moneda) {
            case 'Dólares':
                return 'USD';
            case 'Real brasileño':
                return 'R$';
            case 'Guaraníes':
            default:
                return '₲';
        }
    }

    public function formatearNumero($numero, $decimales = 2)
    {
        $formateado = number_format((float)$numero, $decimales, ',', '.');
        if ($decimales > 0) {
            $formateado = rtrim($formateado, '0');
            $formateado = rtrim($formateado, ',');
        }
        return $formateado;
    }
}
