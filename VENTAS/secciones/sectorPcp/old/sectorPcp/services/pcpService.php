<?php

class PcpService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function obtenerVentasAprobadas($filtros = [], $pagina = 1, $registrosPorPagina = 10)
    {
        $inicio = ($pagina - 1) * $registrosPorPagina;
        $todasVentas = $this->repository->obtenerVentasAprobadas($filtros, 0, 1000);
        $ventasPendientes = [];

        foreach ($todasVentas as $venta) {
            if ($this->ventaTieneProductosPendientes($venta['id'])) {
                $ventasPendientes[] = $venta;
            }
        }

        $totalRegistros = count($ventasPendientes);
        $totalPaginas = ceil($totalRegistros / $registrosPorPagina);
        $ventasPagina = array_slice($ventasPendientes, $inicio, $registrosPorPagina);

        return [
            'ventas' => $ventasPagina,
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'pagina_actual' => $pagina
        ];
    }

    public function ventaTieneProductosPendientes($idVenta)
    {
        try {
            $productos = $this->repository->obtenerProductosVenta($idVenta);

            if (empty($productos)) {
                return false;
            }

            foreach ($productos as $producto) {
                $cantidadRequerida = (float)$producto['cantidad'];
                $estoallita = (strtolower($producto['tipoproducto']) === 'toallitas');
                $espanos = (strtolower($producto['tipoproducto']) === 'paños');

                if ($estoallita || $espanos) {
                    $cantidadEfectiva = 1;
                } else {
                    $cantidadEfectiva = $producto['peso_por_bobina'] ? (float)$producto['peso_por_bobina'] : 1;
                }

                $totalEnviado = $this->repository->obtenerCantidadTotalEnviada($idVenta, $producto['id']);

                if ($totalEnviado < $cantidadRequerida) {
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            error_log("Error verificando productos pendientes: " . $e->getMessage());
            return true;
        }
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

    public function obtenerDevolucionesPcp($filtros = [], $pagina = 1, $registrosPorPagina = 10)
    {
        $inicio = ($pagina - 1) * $registrosPorPagina;

        $devoluciones = $this->repository->obtenerDevolucionesPcp($filtros, $inicio, $registrosPorPagina);
        $totalRegistros = $this->repository->contarDevolucionesPcp($filtros);
        $totalPaginas = ceil($totalRegistros / $registrosPorPagina);

        return [
            'devoluciones' => $devoluciones,
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'pagina_actual' => $pagina
        ];
    }

    public function obtenerHistorialAcciones($filtros = [], $pagina = 1, $registrosPorPagina = 10)
    {
        $inicio = ($pagina - 1) * $registrosPorPagina;

        $historial = $this->repository->obtenerHistorialAcciones($filtros, $inicio, $registrosPorPagina);
        $totalRegistros = $this->repository->contarHistorialAcciones($filtros);
        $totalPaginas = ceil($totalRegistros / $registrosPorPagina);

        return [
            'historial' => $historial,
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'pagina_actual' => $pagina
        ];
    }

    public function obtenerEstadisticasDashboard()
    {
        return $this->repository->obtenerEstadisticasDashboard();
    }

    public function validarParametros($parametros)
    {
        $errores = [];

        if (isset($parametros['id'])) {
            $id = (int)$parametros['id'];
            if ($id < 1) {
                $errores[] = 'ID de venta inválido';
            }
        }

        if (isset($parametros['pagina'])) {
            $pagina = (int)$parametros['pagina'];
            if ($pagina < 1) {
                $errores[] = 'Número de página inválido';
            }
        }

        return $errores;
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

    public function enviarStockExpedicion($idVenta, $cantidadesStock, $observaciones, $idUsuario)
    {
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

            if (empty($cantidadesStock)) {
                $conexion->rollBack();
                return ['success' => false, 'error' => 'No se recibieron datos de cantidad de stock'];
            }

            $productosVenta = $this->repository->obtenerProductosVenta($idVenta);

            foreach ($cantidadesStock as $nombreProductoStock => $cantidad) {
                $cantidad = (int)$cantidad;
                if ($cantidad <= 0) continue;

                $stockInfo = $this->obtenerInfoStockAgregado($nombreProductoStock);

                if (!$stockInfo) {
                    $conexion->rollBack();
                    return [
                        'success' => false,
                        'error' => "Producto '$nombreProductoStock' no encontrado en stock agregado"
                    ];
                }

                $bobinasPacote = $stockInfo['bobinas_pacote'];

                $resultadoReserva = $this->repository->crearReservaStock(
                    $nombreProductoStock,
                    $bobinasPacote,
                    $cantidad,
                    $idVenta,
                    $clienteVenta
                );

                if (!$resultadoReserva['exito']) {
                    $conexion->rollBack();
                    return [
                        'success' => false,
                        'error' => "Error reservando $nombreProductoStock: " . $resultadoReserva['mensaje']
                    ];
                }

                $reservasIds[] = $resultadoReserva['id_reserva'];
                $totalReservasCreadas++;

                $detallesReservas[] = [
                    'producto' => $nombreProductoStock,
                    'cantidad' => $cantidad,
                    'bobinas_pacote' => $bobinasPacote,
                    'id_reserva' => $resultadoReserva['id_reserva']
                ];

                foreach ($productosVenta as $productoVenta) {
                    if (trim($productoVenta['descripcion']) === $nombreProductoStock) {
                        $cantidadParaExpedicion = $this->calcularCantidadExpedicionReserva(
                            $cantidad,
                            $productoVenta,
                            $bobinasPacote
                        );

                        $datosExpedicion = [
                            'id_venta' => $idVenta,
                            'id_producto' => $productoVenta['id'],
                            'id_usuario' => $idUsuario,
                            'fecha_asignacion' => $fechaProcesamiento,
                            'cantidad' => $cantidadParaExpedicion,
                            'observaciones' => $observaciones . " | Reserva ID: " . $resultadoReserva['id_reserva'],
                            'origen' => 'stock_general_reservas'
                        ];

                        $this->repository->insertarProductoExpedicion($datosExpedicion);
                        break;
                    }
                }
            }

            if ($totalReservasCreadas === 0) {
                $conexion->rollBack();
                return ['success' => false, 'error' => 'No se crearon reservas para enviar a expedición'];
            }

            $nuevoEstado = $this->determinarNuevoEstadoVenta($idVenta, $productosVenta);

            if ($nuevoEstado) {
                $this->repository->actualizarEstadoVenta($idVenta, $nuevoEstado);
            }

            $conexion->commit();

            $observacionesHistorial = "Reservas creadas: $totalReservasCreadas. IDs: " . implode(', ', $reservasIds);
            if (!empty($observaciones)) {
                $observacionesHistorial .= " | Observaciones: $observaciones";
            }

            $estadoParaHistorial = $nuevoEstado ?: 'Reservado para Expedición';

            $this->insertarHistorialAccion([
                'id_venta' => $idVenta,
                'id_usuario' => $idUsuario,
                'sector' => 'PCP',
                'accion' => 'Crear Reservas Stock',
                'fecha_accion' => $fechaProcesamiento,
                'observaciones' => $observacionesHistorial,
                'estado_resultante' => $estadoParaHistorial
            ]);

            $mensajeExito = "Se crearon $totalReservasCreadas reservas de stock. ";
            $mensajeExito .= "Los productos están apartados y listos para expedición. ";
            $mensajeExito .= "El sistema de expedición los despachará automáticamente cuando los escanee.";

            if ($nuevoEstado) {
                $mensajeExito .= " Estado actualizado a: $nuevoEstado";
            }

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
            error_log("Error creando reservas de stock: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al crear reservas de stock: ' . $e->getMessage()];
        }
    }

    public function enviarStockExpedicionVariantes($idVenta, $variantesSeleccionadas, $observaciones, $idUsuario)
    {
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

            if (empty($variantesSeleccionadas)) {
                $conexion->rollBack();
                return ['success' => false, 'error' => 'No se recibieron variantes seleccionadas'];
            }

            $productosVenta = $this->repository->obtenerProductosVenta($idVenta);

            foreach ($variantesSeleccionadas as $claveVariante => $datosVariante) {
                $nombreProducto = $datosVariante['nombre_producto'];
                $cantidad = (int)$datosVariante['cantidad'];
                $bobinasPacote = (int)$datosVariante['bobinas_pacote'];
                $tipoProducto = $datosVariante['tipo_producto'];

                if ($cantidad <= 0) continue;

                $disponibilidadVariante = $this->validarDisponibilidadVarianteEspecifica($nombreProducto, $bobinasPacote, $cantidad);

                if (!$disponibilidadVariante['disponible']) {
                    $conexion->rollBack();
                    return [
                        'success' => false,
                        'error' => "Error en $nombreProducto ({$bobinasPacote} bob/paq): " . $disponibilidadVariante['error']
                    ];
                }

                $resultadoReserva = $this->repository->crearReservaStock(
                    $nombreProducto,
                    $bobinasPacote,
                    $cantidad,
                    $idVenta,
                    $clienteVenta
                );

                if (!$resultadoReserva['exito']) {
                    $conexion->rollBack();
                    return [
                        'success' => false,
                        'error' => "Error reservando $nombreProducto: " . $resultadoReserva['mensaje']
                    ];
                }

                $reservasIds[] = $resultadoReserva['id_reserva'];
                $totalReservasCreadas++;

                $detallesReservas[] = [
                    'producto' => $nombreProducto,
                    'cantidad' => $cantidad,
                    'bobinas_pacote' => $bobinasPacote,
                    'tipo_producto' => $tipoProducto,
                    'id_reserva' => $resultadoReserva['id_reserva']
                ];

                foreach ($productosVenta as $productoVenta) {
                    if (trim($productoVenta['descripcion']) === $nombreProducto) {
                        $cantidadParaExpedicion = $this->calcularCantidadExpedicionVariante(
                            $cantidad,
                            $productoVenta,
                            $bobinasPacote,
                            $tipoProducto
                        );

                        $datosExpedicion = [
                            'id_venta' => $idVenta,
                            'id_producto' => $productoVenta['id'],
                            'id_usuario' => $idUsuario,
                            'fecha_asignacion' => $fechaProcesamiento,
                            'cantidad' => $cantidadParaExpedicion,
                            'observaciones' => $observaciones . " | Variante: {$bobinasPacote} bob/paq | Reserva: " . $resultadoReserva['id_reserva'],
                            'origen' => 'stock_variante_especifica'
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

            $nuevoEstado = $this->determinarNuevoEstadoVenta($idVenta, $productosVenta);
            if ($nuevoEstado) {
                $this->repository->actualizarEstadoVenta($idVenta, $nuevoEstado);
            }

            $conexion->commit();

            $observacionesHistorial = "Reservas variantes específicas: $totalReservasCreadas. IDs: " . implode(', ', $reservasIds);
            if (!empty($observaciones)) {
                $observacionesHistorial .= " | " . $observaciones;
            }

            $this->insertarHistorialAccion([
                'id_venta' => $idVenta,
                'id_usuario' => $idUsuario,
                'sector' => 'PCP',
                'accion' => 'Crear Reservas Variantes',
                'fecha_accion' => $fechaProcesamiento,
                'observaciones' => $observacionesHistorial,
                'estado_resultante' => $nuevoEstado ?: 'Reservas Creadas'
            ]);

            $mensajeExito = "Se crearon $totalReservasCreadas reservas de variantes específicas. ";
            $mensajeExito .= "Las variantes están apartadas y listas para expedición.";

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
            error_log("Error creando reservas variantes: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al crear reservas: ' . $e->getMessage()];
        }
    }

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
            foreach ($reservasActivas as $reserva) {
                $resultado = $this->repository->cancelarReserva($reserva['id']);
                if ($resultado['exito']) {
                    $reservasCanceladas++;
                }
            }

            $conexion->commit();

            $this->insertarHistorialAccion([
                'id_venta' => $idVenta,
                'id_usuario' => $idUsuario,
                'sector' => 'PCP',
                'accion' => 'Cancelar Reservas',
                'fecha_accion' => date('Y-m-d H:i:s'),
                'observaciones' => "Reservas canceladas: $reservasCanceladas",
                'estado_resultante' => 'Reservas Canceladas'
            ]);

            return [
                'success' => true,
                'mensaje' => "Se cancelaron $reservasCanceladas reservas correctamente",
                'reservas_canceladas' => $reservasCanceladas
            ];
        } catch (Exception $e) {
            $conexion = $this->repository->getConexion();
            $conexion->rollBack();
            error_log("Error cancelando reservas: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al cancelar reservas: ' . $e->getMessage()];
        }
    }

    private function validarDisponibilidadVarianteEspecifica($nombreProducto, $bobinasPacote, $cantidadSolicitada)
    {
        try {
            $sql = "SELECT cantidad_disponible FROM stock_agregado 
                    WHERE nombre_producto = :nombre_producto 
                        AND bobinas_pacote = :bobinas_pacote 
                        AND cantidad_disponible > 0
                    LIMIT 1";

            $conexion = $this->repository->getConexion();
            $stmt = $conexion->prepare($sql);
            $stmt->bindParam(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
            $stmt->bindParam(':bobinas_pacote', $bobinasPacote, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resultado) {
                return [
                    'disponible' => false,
                    'error' => "Variante no encontrada ({$bobinasPacote} bob/paq)"
                ];
            }

            $disponible = (int)$resultado['cantidad_disponible'];

            if ($cantidadSolicitada > $disponible) {
                return [
                    'disponible' => false,
                    'error' => "Insuficiente. Disponible: $disponible, solicitado: $cantidadSolicitada"
                ];
            }

            return ['disponible' => true];
        } catch (Exception $e) {
            return [
                'disponible' => false,
                'error' => 'Error interno al validar'
            ];
        }
    }

    private function obtenerInfoStockAgregado($nombreProducto)
    {
        try {
            $sql = "SELECT id, bobinas_pacote, tipo_producto, cantidad_disponible 
                    FROM stock_agregado 
                    WHERE nombre_producto = :nombre_producto 
                        AND cantidad_disponible > 0
                    ORDER BY cantidad_disponible DESC
                    LIMIT 1";

            $conexion = $this->repository->getConexion();
            $stmt = $conexion->prepare($sql);
            $stmt->bindParam(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo info stock agregado: " . $e->getMessage());
            return false;
        }
    }

    private function calcularCantidadExpedicionReserva($cantidadReservada, $producto, $bobinasPacote)
    {
        $esProductoEnUnidades = $this->esProductoEnUnidades($producto['tipoproducto']);

        if ($esProductoEnUnidades) {
            return $cantidadReservada;
        } else {
            $totalBobinasReservadas = $cantidadReservada * $bobinasPacote;
            $cantidadEfectiva = $this->repository->obtenerCantidadEfectivaProducto($producto['id_producto']);
            return $totalBobinasReservadas * $cantidadEfectiva;
        }
    }

    private function calcularCantidadExpedicionVariante($cantidadReservada, $producto, $bobinasPacote, $tipoProducto)
    {
        $esProductoEnUnidades = $this->esProductoEnUnidades($tipoProducto);

        if ($esProductoEnUnidades) {
            return $cantidadReservada;
        } else {
            $totalBobinasReservadas = $cantidadReservada * $bobinasPacote;
            $cantidadEfectiva = $this->repository->obtenerCantidadEfectivaProducto($producto['id_producto']);
            return $totalBobinasReservadas * $cantidadEfectiva;
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

    public function reasignarVentaAprobado($idVenta, $idUsuario, $idProducto = null)
    {
        try {
            $conexion = $this->repository->getConexion();
            $conexion->beginTransaction();

            $fechaReasignacion = date('Y-m-d H:i:s');
            $nuevoEstado = 'Enviado a PCP';

            if ($idProducto) {
                // OPCIÓN 1: Eliminar producto específico
                $eliminadosProduccion = $this->repository->eliminarProductoEspecificoProduccion($idVenta, $idProducto);
                $eliminadosExpedicion = $this->repository->eliminarProductoEspecificoExpedicion($idVenta, $idProducto);

                $totalEliminados = $eliminadosProduccion + $eliminadosExpedicion;

                // ✅ SIEMPRE cambiar estado de la venta a "Enviado a PCP"
                $this->repository->actualizarEstadoVenta($idVenta, $nuevoEstado);

                $mensaje = "Producto reasignado correctamente. Venta devuelta a PCP.";
                $observaciones = "Producto ID $idProducto reasignado - Eliminados $totalEliminados registros (Prod: $eliminadosProduccion, Exp: $eliminadosExpedicion) - Venta devuelta a PCP";
            } else {
                // OPCIÓN 2: Eliminar toda la venta (compatibilidad con código anterior)
                $this->repository->eliminarProductosProduccionVenta($idVenta);
                $this->repository->eliminarProductosExpedicionVenta($idVenta);
                $this->repository->actualizarEstadoVenta($idVenta, $nuevoEstado);

                $mensaje = 'Venta reasignada correctamente y todos los registros eliminados';
                $observaciones = 'Venta completa reasignada desde devoluciones - Todos los registros eliminados';
            }

            // Registrar en historial
            $this->insertarHistorialAccion([
                'id_venta' => $idVenta,
                'id_usuario' => $idUsuario,
                'sector' => 'PCP',
                'accion' => $idProducto ? 'Reasignar Producto Específico' : 'Reasignar Venta Completa',
                'fecha_accion' => $fechaReasignacion,
                'observaciones' => $observaciones,
                'estado_resultante' => $nuevoEstado  
            ]);

            $conexion->commit();

            error_log("SUCCESS - " . ($idProducto ? "Producto $idProducto" : "Venta completa") . " reasignado para venta $idVenta - Estado cambiado a: $nuevoEstado");
            return ['success' => true, 'mensaje' => $mensaje];
        } catch (Exception $e) {
            $conexion = $this->repository->getConexion();
            $conexion->rollBack();
            error_log("Error reasignando: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al reasignar'];
        }
    }

    private function esProductoEnUnidades($tipoProducto)
    {
        $tipoLower = strtolower($tipoProducto);
        $productosEnUnidades = ['toallitas', 'paños'];
        return in_array($tipoLower, $productosEnUnidades);
    }
}
