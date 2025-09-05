<?php

/**
 * Service para lógica de negocio de producción - CORREGIDO CON GESTIÓN DE RECETAS
 */
class ProduccionService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    /**
     * ✅ NUEVO: Emitir órdenes de producción CON RECETAS
     */
    public function emitirOrdenesProduccionConRecetas($idVenta, $productosSeleccionados, $recetasSeleccionadas, $observaciones, $idUsuario)
    {
        try {
            $conexion = $this->repository->getConexion();
            $conexion->beginTransaction();

            $venta = $this->repository->obtenerVentaProduccion($idVenta);
            if (!$venta) {
                throw new Exception('Venta no encontrada');
            }

            $productosEmitidos = [];

            foreach ($productosSeleccionados as $idProductoProduccion => $completado) {
                if ($completado === "1") {
                    // Obtener datos del producto
                    $productoInfo = $this->repository->obtenerInfoProductoProduccion($idProductoProduccion);
                    if (!$productoInfo) {
                        continue;
                    }

                    // LOG PARA DEBUGGING
                    error_log("PROCESANDO PRODUCTO CON RECETAS - ID: {$idProductoProduccion}, TIPO: '{$productoInfo['tipoproducto']}'");

                    // CORREGIDO: Obtener cantidad real del inventario
                    $cantidadInventario = $this->repository->obtenerCantidadInventario($idProductoProduccion);

                    // CORREGIDO: Obtener gramatura calculada
                    $gramaturaCalculada = $this->repository->obtenerGramaturaCalculada($idProductoProduccion);

                    // Crear orden de producción principal
                    $idOrdenProduccion = $this->repository->crearOrdenProduccion($idVenta, $venta['cliente'], $observaciones);

                    if (!$idOrdenProduccion) {
                        throw new Exception('Error creando orden de producción');
                    }

                    // ✅ NUEVA FUNCIONALIDAD: Asignar recetas a la orden si existen
                    $recetasAsignadas = [];
                    if (isset($recetasSeleccionadas[$idProductoProduccion]) && !empty($recetasSeleccionadas[$idProductoProduccion])) {
                        $recetasProducto = $recetasSeleccionadas[$idProductoProduccion];

                        error_log("SERVICE - Procesando recetas para producto {$idProductoProduccion}");

                        // Convertir a array simple de IDs de recetas
                        $idsRecetas = [];
                        foreach ($recetasProducto as $idReceta => $seleccionado) {
                            if ($seleccionado === true || $seleccionado === 1 || $seleccionado === '1' || $seleccionado === 'true') {
                                $idsRecetas[] = (int)$idReceta;
                            }
                        }

                        error_log("SERVICE - IDs de recetas a insertar: " . json_encode($idsRecetas));

                        if (!empty($idsRecetas)) {
                            $resultadoRecetas = $this->repository->asignarRecetasAOrden($idOrdenProduccion, $idsRecetas);

                            if ($resultadoRecetas) {
                                $recetasAsignadas = $this->repository->obtenerRecetasAsignadasAOrden($idOrdenProduccion);
                                error_log("SERVICE - Recetas asignadas exitosamente: " . count($recetasAsignadas));
                            } else {
                                error_log("SERVICE - ERROR asignando recetas a orden: {$idOrdenProduccion}");
                            }
                        } else {
                            error_log("SERVICE - No hay recetas válidas para insertar");
                        }
                    } else {
                        error_log("SERVICE - Producto {$idProductoProduccion} sin recetas configuradas");
                    }

                    // CORRECCIÓN: Normalizar correctamente con UTF-8
                    $tipoProducto = mb_strtoupper(trim($productoInfo['tipoproducto']), 'UTF-8');

                    // 🔥 PROCESAMIENTO CORREGIDO: Ahora actualiza automáticamente el campo cantidad
                    $resultadoProcesamiento = $this->procesarProductoCorregido($tipoProducto, $idOrdenProduccion, $productoInfo, $cantidadInventario, $gramaturaCalculada, $idProductoProduccion, $idVenta);

                    if ($resultadoProcesamiento['success']) {
                        // ✅ CORRECCIÓN: Usar unidad real de la BD
                        $unidadReal = $productoInfo['unidadmedida'] ?? ($tipoProducto === 'PAÑOS' ? 'unidades' : 'kg');

                        $productosEmitidos[] = [
                            'id_orden' => $idOrdenProduccion,
                            'descripcion' => $productoInfo['descripcion'],
                            'tipo' => $tipoProducto,
                            'cantidad' => $productoInfo['cantidad'],
                            'unidad' => $unidadReal,
                            'cantidad_inventario' => $cantidadInventario,
                            'gramatura' => $gramaturaCalculada,
                            'cantidad_actualizada' => true,
                            'recetas_asignadas' => $recetasAsignadas // ✅ NUEVO: Lista de recetas asignadas
                        ];

                        // LOG ÉXITO
                        error_log("PRODUCTO PROCESADO EXITOSAMENTE CON RECETAS - TIPO: {$tipoProducto}, ORDEN: {$idOrdenProduccion}, RECETAS: " . count($recetasAsignadas));
                    } else {
                        error_log("ERROR PROCESANDO PRODUCTO - TIPO: {$tipoProducto}, ERROR: " . ($resultadoProcesamiento['error'] ?? 'Desconocido'));
                    }
                }
            }

            if (empty($productosEmitidos)) {
                throw new Exception('No se procesaron productos para emisión');
            }

            // Registrar historial con información de recetas
            $totalRecetas = array_sum(array_map(function ($p) {
                return count($p['recetas_asignadas']);
            }, $productosEmitidos));
            $observacionesHistorial = $observaciones . " - Órdenes emitidas con {$totalRecetas} recetas asignadas";

            $this->repository->insertarHistorialAccion([
                'id_venta' => $idVenta,
                'id_usuario' => $idUsuario,
                'sector' => 'Producción',
                'accion' => 'Emitir Orden de Producción',
                'fecha_accion' => date('Y-m-d H:i:s'),
                'observaciones' => $observacionesHistorial,
                'estado_resultante' => 'En Producción/Expedición'
            ]);

            $conexion->commit();

            return [
                'success' => true,
                'productos_emitidos' => $productosEmitidos,
                'mensaje' => 'Órdenes de producción emitidas exitosamente con recetas configuradas'
            ];
        } catch (Exception $e) {
            $conexion = $this->repository->getConexion();
            $conexion->rollBack();
            error_log("Error emitiendo órdenes con recetas: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al emitir órdenes de producción: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ CORREGIDO: Emitir órdenes de producción - actualiza campo cantidad (MÉTODO ORIGINAL MANTENIDO)
     */
    public function emitirOrdenesProduccion($idVenta, $productosSeleccionados, $observaciones, $idUsuario)
    {
        // Llamar al método con recetas pero sin recetas seleccionadas
        return $this->emitirOrdenesProduccionConRecetas($idVenta, $productosSeleccionados, [], $observaciones, $idUsuario);
    }

    /**
     * ✅ CORREGIDO: Procesar producto con actualización del campo cantidad
     */
    private function procesarProductoCorregido($tipoProducto, $idOrdenProduccion, $productoInfo, $cantidadInventario, $gramaturaCalculada, $idProductoProduccion, $idVenta)
    {
        try {
            // LOG PARA DEBUGGING
            error_log("ENTRANDO A procesarProductoCorregido - TIPO: '{$tipoProducto}'");

            $resultadoProcesamiento = null;

            switch ($tipoProducto) {
                case 'TNT':
                case 'LAMINADORA':
                    error_log("PROCESANDO COMO TNT/LAMINADORA");
                    $resultadoProcesamiento = $this->procesarTNTCorregido($idOrdenProduccion, $productoInfo, $cantidadInventario, $idProductoProduccion, $idVenta);
                    break;

                case 'SPUNLACE':
                    error_log("PROCESANDO COMO SPUNLACE");
                    $resultadoProcesamiento = $this->procesarSpunlaceCorregido($idOrdenProduccion, $productoInfo, $cantidadInventario, $idProductoProduccion, $idVenta);
                    break;

                case 'TOALLITAS':
                    error_log("PROCESANDO COMO TOALLITAS");
                    $resultadoProcesamiento = $this->procesarToallitasCorregido($idOrdenProduccion, $productoInfo, $idProductoProduccion, $idVenta);
                    break;

                case 'PAÑOS':
                    error_log("PROCESANDO COMO PAÑOS - ¡CORREGIDO!");
                    $resultadoProcesamiento = $this->procesarPanosCorregido($idOrdenProduccion, $productoInfo, $cantidadInventario, $gramaturaCalculada, $idProductoProduccion, $idVenta);
                    break;

                default:
                    error_log("TIPO NO RECONOCIDO, USANDO TNT POR DEFECTO - TIPO: '{$tipoProducto}'");
                    $resultadoProcesamiento = $this->procesarTNTCorregido($idOrdenProduccion, $productoInfo, $cantidadInventario, $idProductoProduccion, $idVenta);
                    break;
            }

            // 🔥 NUEVA FUNCIONALIDAD: Actualizar tabla productos_produccion con cantidad real de orden
            if ($resultadoProcesamiento && $resultadoProcesamiento['success']) {
                // Obtener la cantidad real de la orden creada
                $cantidadRealOrden = $this->repository->obtenerCantidadRealOrdenProduccion($idProductoProduccion, $tipoProducto);

                // Si no se puede obtener la cantidad de la orden, usar la cantidad original
                if ($cantidadRealOrden === null) {
                    $cantidadRealOrden = (float)$productoInfo['cantidad'];
                    error_log("WARNING - No se pudo obtener cantidad real de orden, usando cantidad original: {$cantidadRealOrden}");
                } else {
                    error_log("SUCCESS - Cantidad real obtenida de orden: {$cantidadRealOrden}");
                }

                // ✅ CORRECCIÓN: Actualizar el registro con movimiento y cantidad real en campo cantidad
                $observacionesCompletas = "Orden de producción creada. Tipo: {$tipoProducto}. Orden ID: {$idOrdenProduccion}. Cantidad real de orden: {$cantidadRealOrden}";

                $actualizacionCompleta = $this->repository->actualizarEstadoCompletoProducto(
                    $idProductoProduccion,
                    'Orden Emitida',
                    $cantidadRealOrden, // ✅ Esta cantidad ahora va al campo 'cantidad' (no a cantidad_completada)
                    $observacionesCompletas
                );

                if ($actualizacionCompleta) {
                    error_log("SUCCESS - Actualizado campo cantidad con valor real de orden: {$cantidadRealOrden} para producto: {$idProductoProduccion}");
                } else {
                    error_log("ERROR - Fallo actualizando campo cantidad para producto: {$idProductoProduccion}");
                }

                return $resultadoProcesamiento;
            }

            return $resultadoProcesamiento;
        } catch (Exception $e) {
            error_log("Error procesando producto corregido: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * ✅ CORREGIDO: Procesar Paños con los valores correctos y gramatura extraída
     */
    private function procesarPanosCorregido($idOrdenProduccion, $productoInfo, $cantidadInventario, $gramaturaCalculada, $idProductoProduccion, $idVenta)
    {
        $descripcion = $productoInfo['descripcion'];
        $cantidadTotal = (float)$productoInfo['cantidad'];

        // ✅ USAR LA UNIDAD DE LA BASE DE DATOS
        $unidadReal = $productoInfo['unidadmedida'] ?? 'unidades';

        // LOG PARA DEBUGGING
        error_log("PROCESANDO PAÑOS CORREGIDO - Descripción: {$descripcion}, Cantidad: {$cantidadTotal}, Unidad BD: {$unidadReal}, Cantidad Inventario: {$cantidadInventario}, Gramatura: {$gramaturaCalculada}");

        // Extraer datos técnicos de paños con la unidad correcta
        $datosExtraidos = $this->extraerDatosTecnicosPanosCorregido($descripcion, $unidadReal);

        // LOG ESPECÍFICO PARA VERIFICAR EXTRACCIÓN
        error_log("DATOS EXTRAÍDOS COMPLETOS: " . json_encode($datosExtraidos));

        // ✅ EXTRAER GRAMATURA ESPECÍFICAMENTE DE LA DESCRIPCIÓN (35g/m2, 70g/m2, etc.)
        $gramaturaDescripcion = null;
        if (preg_match('/(\d+(?:[.,]?\d+)?)\s*g\/m[²2]/i', $descripcion, $matches)) {
            $gramaturaDescripcion = (float)str_replace(',', '.', $matches[1]);
        }

        // LOG para verificar extracción de gramatura
        error_log("GRAMATURA EXTRAÍDA DE DESCRIPCIÓN: " . ($gramaturaDescripcion ?? 'NO ENCONTRADA'));

        $datos = [
            ':id_orden_produccion' => $idOrdenProduccion,
            ':nombre' => $descripcion,
            ':cantidad_total' => $cantidadTotal,
            ':color' => $datosExtraidos['color'],
            ':largura' => $datosExtraidos['largura'],
            ':picotado' => $datosExtraidos['picotado'],
            ':cant_panos' => $datosExtraidos['cant_panos'],
            ':unidad' => $datosExtraidos['unidad'], // ✅ AHORA USA LA UNIDAD CORRECTA
            ':peso' => $cantidadInventario, // ✅ AHORA PESO = CANTIDAD DE INVENTARIO
            ':gramatura' => $gramaturaDescripcion, // ✅ GRAMATURA EXTRAÍDA DE LA DESCRIPCIÓN
            ':id_producto' => $productoInfo['id_producto'],
            ':id_venta' => $idVenta,
            ':id_producto_produccion' => $idProductoProduccion
        ];

        // LOG PARA DEBUGGING
        error_log("PAÑOS CORREGIDO - Datos a insertar: " . json_encode($datos));

        $resultado = $this->repository->insertarOrdenPanosActualizado($datos);

        // LOG RESULTADO
        error_log("PAÑOS CORREGIDO - Resultado inserción: " . ($resultado ? 'ÉXITO' : 'ERROR'));

        return ['success' => $resultado];
    }

    /**
     * ✅ CORREGIDO: Extraer datos técnicos de Paños - Ahora recibe la unidad correcta
     */
    private function extraerDatosTecnicosPanosCorregido($descripcion, $unidadReal = 'unidades')
    {
        $datos = [
            'color' => 'Blanco',
            'largura' => null,
            'picotado' => null,
            'cant_panos' => null,
            'unidad' => $unidadReal, // ✅ USAR LA UNIDAD REAL DE LA BASE DE DATOS
            'peso' => null
        ];

        // Extraer dimensiones (formato NxN como 28x40)
        if (preg_match('/(\d+)\s*x\s*(\d+)/i', $descripcion, $matches)) {
            $datos['largura'] = (int)$matches[1];  // 28 en el ejemplo
            $datos['picotado'] = (int)$matches[2]; // 40 en el ejemplo
        }

        // Extraer cantidad de paños (450 paños)
        if (preg_match('/(\d+)\s+pa[nñ]os/iu', $descripcion, $matches)) {
            $datos['cant_panos'] = (int)$matches[1]; // 450 en el ejemplo
        }

        // Extraer color (última palabra o después de especificaciones técnicas)
        if (preg_match('/\s(Blanco|Azul|Verde|Amarillo|Rojo|Rosa|Negro|Gris)(?:\s|$)/i', $descripcion, $matches)) {
            $datos['color'] = trim($matches[1]);
        } elseif (preg_match('/g\/m[²2]\s+(\w+)(?:\s|$)/i', $descripcion, $matches)) {
            $datos['color'] = trim($matches[1]);
        }

        return $datos;
    }

    /**
     * Obtener venta completa para procesamiento de producción
     */
    public function obtenerVentaCompleta($idVenta)
    {
        try {
            $venta = $this->repository->obtenerVentaProduccion($idVenta);

            if (!$venta) {
                throw new Exception('Venta no encontrada o no está en producción');
            }

            $productosProduccion = $this->repository->obtenerProductosPendientes($idVenta);
            $productosCompletados = $this->repository->obtenerProductosCompletados($idVenta);

            return [
                'venta' => $venta,
                'productos_produccion' => $productosProduccion,
                'productos_completados' => $productosCompletados
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo venta completa: " . $e->getMessage());
            throw new Exception('Error al obtener información de la venta: ' . $e->getMessage());
        }
    }

    /**
     * CORREGIDO: Procesar TNT (ahora recibe cantidadInventario en lugar de pesoBobina)
     */
    private function procesarTNTCorregido($idOrdenProduccion, $productoInfo, $cantidadInventario, $idProductoProduccion, $idVenta)
    {
        $descripcion = $productoInfo['descripcion'];
        $cantidadKilos = (float)$productoInfo['cantidad']; // Ya viene en kilos

        // Extraer datos técnicos
        $datosExtraidos = $this->extraerDatosTecnicosTNT($descripcion);

        // Cálculos simplificados - usar cantidadInventario como peso de bobina
        $pesoBobina = $cantidadInventario;
        $totalBobinas = $pesoBobina > 0 ? $cantidadKilos / $pesoBobina : 1;
        $pesoMinBobina = $pesoBobina - ($pesoBobina * 0.03); // 3% tolerancia

        $datos = [
            ':id_orden_produccion' => $idOrdenProduccion,
            ':gramatura' => $datosExtraidos['gramatura'],
            ':largura' => $datosExtraidos['largura'],
            ':longitud' => $datosExtraidos['longitud'],
            ':color' => $datosExtraidos['color'],
            ':peso_bobina' => $pesoBobina,
            ':cantidad_total' => $cantidadKilos,
            ':total_bobinas' => $totalBobinas,
            ':id_producto' => $productoInfo['id_producto'],
            ':id_producto_produccion' => $idProductoProduccion,
            ':peso_min_bobina' => $pesoMinBobina,
            ':nombre' => $descripcion,
            ':id_venta' => $idVenta
        ];

        $resultado = $this->repository->insertarOrdenTNT($datos);
        return ['success' => $resultado];
    }

    /**
     * CORREGIDO: Procesar Spunlace (ahora recibe cantidadInventario en lugar de pesoBobina)
     */
    private function procesarSpunlaceCorregido($idOrdenProduccion, $productoInfo, $cantidadInventario, $idProductoProduccion, $idVenta)
    {
        $descripcion = $productoInfo['descripcion'];
        $cantidadKilos = (float)$productoInfo['cantidad']; // Ya viene en kilos

        // Extraer datos técnicos
        $datosExtraidos = $this->extraerDatosTecnicosSpunlace($descripcion);

        // Cálculos simplificados - usar cantidadInventario como peso de bobina
        $pesoBobina = $cantidadInventario;
        $totalBobinas = $pesoBobina > 0 ? ceil($cantidadKilos / $pesoBobina) : 1;
        $pesoMinBobina = $pesoBobina - ($pesoBobina * 0.03);

        $datos = [
            ':id_orden_produccion' => $idOrdenProduccion,
            ':gramatura' => $datosExtraidos['gramatura'],
            ':largura' => $datosExtraidos['largura'],
            ':longitud' => $datosExtraidos['longitud'],
            ':color' => $datosExtraidos['color'],
            ':peso_bobina' => $pesoBobina,
            ':cantidad_total' => $cantidadKilos,
            ':total_bobinas' => $totalBobinas,
            ':id_producto' => $productoInfo['id_producto'],
            ':id_producto_produccion' => $idProductoProduccion,
            ':peso_min_bobina' => $pesoMinBobina,
            ':acabado' => $datosExtraidos['acabado'],
            ':nombre' => $descripcion,
            ':id_venta' => $idVenta
        ];

        $resultado = $this->repository->insertarOrdenSpunlace($datos);
        return ['success' => $resultado];
    }

    /**
     * CORREGIDO: Procesar Toallitas (sin cambios necesarios)
     */
    private function procesarToallitasCorregido($idOrdenProduccion, $productoInfo, $idProductoProduccion, $idVenta)
    {
        $datos = [
            ':id_orden_produccion' => $idOrdenProduccion,
            ':nombre' => $productoInfo['descripcion'],
            ':cantidad_total' => $productoInfo['cantidad'],
            ':id_producto' => $productoInfo['id_producto'],
            ':id_producto_produccion' => $idProductoProduccion,
            ':id_venta' => $idVenta
        ];

        $resultado = $this->repository->insertarOrdenToallitas($datos);
        return ['success' => $resultado];
    }

    /**
     * ✅ CORRECCIÓN: Devolver productos a PCP para usar unidad correcta
     */
    public function devolverProductosPCP($idVenta, $productosDevueltos, $cantidadesDevueltas, $motivoDevolucion, $idUsuario)
    {
        try {
            $conexion = $this->repository->getConexion();
            $conexion->beginTransaction();

            if (empty($productosDevueltos)) {
                throw new Exception("Debe seleccionar al menos un producto para devolver");
            }

            $productosDevueltosInfo = [];
            $motivoCompleto = "Producción: " . $motivoDevolucion;

            foreach ($productosDevueltos as $idProductoProduccion) {
                $cantidadDevuelta = isset($cantidadesDevueltas[$idProductoProduccion]) ?
                    (float)$cantidadesDevueltas[$idProductoProduccion] : 0;

                if ($cantidadDevuelta <= 0) {
                    continue;
                }

                // Obtener información del producto
                $producto = $this->repository->obtenerInfoProductoProduccion($idProductoProduccion);
                if (!$producto) {
                    continue;
                }

                // Validar cantidad
                if ($cantidadDevuelta > $producto['cantidad']) {
                    $cantidadDevuelta = $producto['cantidad'];
                }

                // Procesar devolución
                $nuevaCantidad = $producto['cantidad'] - $cantidadDevuelta;

                if ($nuevaCantidad <= 0) {
                    // Devolver completamente
                    $this->repository->marcarProductoDevuelto($idProductoProduccion, $motivoCompleto, $idUsuario);
                } else {
                    // Devolución parcial - actualizar el campo cantidad
                    $this->repository->actualizarCantidadProducto($idProductoProduccion, $nuevaCantidad, $motivoCompleto);
                    $this->repository->crearRegistroDevolucion($idVenta, $producto['id_producto'], $cantidadDevuelta, $motivoCompleto, $idUsuario);
                }

                // ✅ USAR LA UNIDAD REAL DE LA BASE DE DATOS EN LUGAR DE HARDCODEAR
                $unidadReal = $producto['unidadmedida'] ?? 'unidades';

                $productosDevueltosInfo[] = [
                    'descripcion' => $producto['descripcion'],
                    'tipo' => $producto['tipoproducto'],
                    'cantidad_devuelta' => $cantidadDevuelta,
                    'cantidad_original' => $producto['cantidad'],
                    'unidad' => $unidadReal // ✅ UNIDAD CORRECTA DE LA BD
                ];
            }

            if (empty($productosDevueltosInfo)) {
                throw new Exception('No se procesaron productos para devolución');
            }

            // Registrar historial
            $this->repository->insertarHistorialAccion([
                'id_venta' => $idVenta,
                'id_usuario' => $idUsuario,
                'sector' => 'Producción',
                'accion' => 'Devolver a PCP',
                'fecha_accion' => date('Y-m-d H:i:s'),
                'observaciones' => $motivoCompleto,
                'estado_resultante' => 'En Producción/Expedición'
            ]);

            $conexion->commit();

            // 🔥 NUEVA FUNCIONALIDAD: Verificar si todos los productos están devueltos
            if ($this->repository->verificarTodosProductosDevueltos($idVenta)) {
                $this->repository->actualizarEstadoVenta($idVenta, 'Devuelto a PCP');
                error_log("VENTA #{$idVenta} - Estado cambiado a 'Devuelto a PCP' - todos los productos devueltos");
            }

            return [
                'success' => true,
                'productos_devueltos' => $productosDevueltosInfo,
                'mensaje' => 'Productos devueltos exitosamente a PCP'
            ];
        } catch (Exception $e) {
            $conexion = $this->repository->getConexion();
            $conexion->rollBack();
            error_log("Error devolviendo productos: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al devolver productos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ NUEVO: Obtener recetas disponibles para un tipo de producto
     */
    public function obtenerRecetasDisponibles($tipoProducto)
    {
        try {
            $recetas = $this->repository->obtenerRecetasPorTipoProducto($tipoProducto);

            if (empty($recetas)) {
                return [
                    'success' => true,
                    'recetas' => [],
                    'mensaje' => "No hay recetas disponibles para el tipo de producto: {$tipoProducto}"
                ];
            }

            // Agrupar recetas por nombre y versión
            $recetasAgrupadas = $this->agruparRecetasPorNombreYVersion($recetas);

            return [
                'success' => true,
                'recetas' => $recetasAgrupadas,
                'total_recetas' => count($recetas),
                'grupos_recetas' => count($recetasAgrupadas)
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo recetas disponibles: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al obtener recetas disponibles: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ NUEVO: Agrupar recetas por nombre y versión
     */
    private function agruparRecetasPorNombreYVersion($recetas)
    {
        $agrupadas = [];

        foreach ($recetas as $receta) {
            $nombreReceta = $receta['nombre_receta'];
            $version = $receta['version_receta'];

            if (!isset($agrupadas[$nombreReceta])) {
                $agrupadas[$nombreReceta] = [
                    'nombre_receta' => $nombreReceta,
                    'versiones' => []
                ];
            }

            if (!isset($agrupadas[$nombreReceta]['versiones'][$version])) {
                $agrupadas[$nombreReceta]['versiones'][$version] = [
                    'version' => $version,
                    'fecha_creacion' => $receta['fecha_creacion'],
                    'usuario_creacion' => $receta['usuario_creacion'],
                    'activo' => $receta['activo'],
                    'materias_primas' => []
                ];
            }

            $agrupadas[$nombreReceta]['versiones'][$version]['materias_primas'][] = [
                'id_receta' => $receta['id'],
                'nombre_materia_prima' => $receta['nombre_materia_prima'],
                'cantidad_por_kilo' => $receta['cantidad_por_kilo'],
                'es_materia_extra' => $receta['es_materia_extra'],
                'unidad_medida_extra' => $receta['unidad_medida_extra'],
            ];
        }

        // Ordenar versiones descendentemente
        foreach ($agrupadas as &$grupo) {
            krsort($grupo['versiones']);
        }

        return array_values($agrupadas);
    }

    /**
     * ✅ NUEVO: Validar recetas seleccionadas - SIMPLIFICADO
     */
    public function validarRecetasSeleccionadas($recetasSeleccionadas)
    {
        $errores = [];

        if (empty($recetasSeleccionadas)) {
            return $errores; // Es válido no seleccionar recetas
        }

        foreach ($recetasSeleccionadas as $idProducto => $recetas) {
            if (!is_array($recetas)) {
                $errores[] = "Las recetas del producto {$idProducto} deben ser un array";
                continue;
            }

            // Ya no validamos cantidades porque son automáticas
            foreach ($recetas as $idReceta => $seleccionado) {
                if (!is_bool($seleccionado) && !in_array($seleccionado, [0, 1, '0', '1', true, false])) {
                    $errores[] = "La selección de la receta {$idReceta} debe ser verdadero o falso";
                }
            }
        }

        return $errores;
    }

    /**
     * ✅ NUEVO: Calcular materias primas necesarias basadas en recetas - SIMPLIFICADO
     */
    public function calcularMateriasPrimasNecesarias($recetasAsignadas, $cantidadTotal)
    {
        $materiasPrimas = [];

        foreach ($recetasAsignadas as $receta) {
            $nombreMateria = $receta['nombre_materia_prima'];
            $cantidadPorKilo = (float)$receta['cantidad_por_kilo'];
            $esExtra = $receta['es_materia_extra'];

            // Calcular cantidad total necesaria
            // Si NO es extra, es porcentaje (se usa tal como está)
            // Si ES extra, se multiplica por los kilos totales
            if ($esExtra) {
                $cantidadNecesaria = $cantidadPorKilo * $cantidadTotal;
            } else {
                // Es porcentaje, se aplica sobre el total
                $cantidadNecesaria = ($cantidadPorKilo / 100) * $cantidadTotal;
            }

            if (!isset($materiasPrimas[$nombreMateria])) {
                $materiasPrimas[$nombreMateria] = [
                    'nombre' => $nombreMateria,
                    'cantidad_total' => 0,
                    'unidad' => $esExtra ? ($receta['unidad_medida_extra'] ?? 'kg') : '%',
                    'es_extra' => $esExtra,
                    'recetas_origen' => []
                ];
            }

            $materiasPrimas[$nombreMateria]['cantidad_total'] += $cantidadNecesaria;
            $materiasPrimas[$nombreMateria]['recetas_origen'][] = [
                'nombre_receta' => $receta['nombre_receta'],
                'version_receta' => $receta['version_receta'],
                'cantidad_contribuida' => $cantidadNecesaria,
                'tipo' => $esExtra ? 'Extra' : 'Porcentaje'
            ];
        }

        return array_values($materiasPrimas);
    }

    /**
     * Extraer datos técnicos de TNT (simplificado)
     */
    private function extraerDatosTecnicosTNT($descripcion)
    {
        $datos = [
            'gramatura' => null,
            'largura' => null,
            'longitud' => null,
            'color' => 'Blanco'
        ];

        // Extraer gramatura
        if (preg_match('/(\d+(?:[,.]?\d+)?)\s*g\/m[²2]/i', $descripcion, $matches)) {
            $datos['gramatura'] = str_replace(',', '.', $matches[1]);
        } elseif (preg_match('/(\d+)\s*GR/i', $descripcion, $matches)) {
            $datos['gramatura'] = $matches[1];
        }

        // Extraer largura/ancho
        if (preg_match('/Ancho\s+(\d+(?:[,.]?\d+)?)\s*cm/i', $descripcion, $matches)) {
            $datos['largura'] = str_replace(',', '.', $matches[1]) / 100; // cm a metros
        } elseif (preg_match('/(\d+[,.]?\d*)\s*CM/i', $descripcion, $matches)) {
            $datos['largura'] = str_replace(',', '.', $matches[1]) / 100;
        }

        // Extraer longitud
        if (preg_match('/Rollo\s+de\s+(\d+)\s*metros/i', $descripcion, $matches)) {
            $datos['longitud'] = $matches[1];
        } elseif (preg_match('/(\d+)\s*METROS/i', $descripcion, $matches)) {
            $datos['longitud'] = $matches[1];
        }

        // Extraer color
        if (preg_match('/Color\s+(\w+(?:\s+\w+)*)\s*$/i', $descripcion, $matches)) {
            $datos['color'] = trim($matches[1]);
        } elseif (preg_match('/Color\s+(\w+(?:\s+\w+)*)/i', $descripcion, $matches)) {
            $datos['color'] = trim($matches[1]);
        }

        return $datos;
    }

    /**
     * Extraer datos técnicos de Spunlace (simplificado)
     */
    private function extraerDatosTecnicosSpunlace($descripcion)
    {
        $datos = [
            'gramatura' => null,
            'largura' => null,
            'longitud' => null,
            'color' => 'Blanco',
            'acabado' => null
        ];

        // Extraer gramatura
        if (preg_match('/(\d+(?:[.,]?\d+)?)\s*g\/m[²2]/i', $descripcion, $matches)) {
            $datos['gramatura'] = str_replace(',', '.', $matches[1]);
        }

        // Extraer largura/ancho
        if (preg_match('/Ancho\s+(\d+(?:[.,]?\d+)?)\s*cm/i', $descripcion, $matches)) {
            $datos['largura'] = str_replace(',', '.', $matches[1]) / 100;
        }

        // Extraer longitud
        if (preg_match('/Rollo\s+de\s+(\d+)\s*metros/i', $descripcion, $matches)) {
            $datos['longitud'] = (int)$matches[1];
        }

        // Extraer acabado
        if (preg_match('/\d+%\s+VISCOSE\s+(.*)$/i', $descripcion, $matches)) {
            $datos['acabado'] = trim($matches[1]);
        }

        return $datos;
    }

    /**
     * Obtener historial de acciones de producción con paginación
     */
    public function obtenerHistorialProduccionPaginado($filtros, $registrosPorPagina, $paginaActual)
    {
        $inicio = ($paginaActual - 1) * $registrosPorPagina;

        $acciones = $this->repository->obtenerHistorialProduccion(
            $filtros,
            $registrosPorPagina,
            $inicio
        );

        $totalRegistros = $this->repository->contarHistorialProduccion($filtros);
        $totalPaginas = ceil($totalRegistros / $registrosPorPagina);

        return [
            'acciones' => $acciones,
            'totalRegistros' => $totalRegistros,
            'totalPaginas' => $totalPaginas,
            'paginaActual' => $paginaActual
        ];
    }

    /**
     * Obtener usuarios que han trabajado en producción
     */
    public function obtenerUsuariosProduccion()
    {
        return $this->repository->obtenerUsuariosProduccion();
    }

    /**
     * Obtener tipos de acciones de producción
     */
    public function obtenerAccionesProduccion()
    {
        return $this->repository->obtenerAccionesProduccion();
    }

    /**
     * Registrar acción en historial de producción
     */
    public function registrarHistorialProduccion($idVenta, $idUsuario, $accion, $observaciones = '', $estadoResultante = 'En Producción')
    {
        try {
            $datos = [
                'id_venta' => (int)$idVenta,
                'id_usuario' => (int)$idUsuario,
                'sector' => 'Producción',
                'accion' => $accion,
                'fecha_accion' => date('Y-m-d H:i:s'),
                'observaciones' => $observaciones,
                'estado_resultante' => $estadoResultante
            ];

            return $this->repository->insertarHistorialAccion($datos);
        } catch (Exception $e) {
            error_log("Error registrando historial de producción: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener estadísticas del historial de producción
     */
    public function obtenerEstadisticasHistorial($filtros = [])
    {
        try {
            // Obtener todas las acciones para las estadísticas
            $todasLasAcciones = $this->repository->obtenerHistorialProduccion($filtros, 1000, 0);

            $estadisticas = [
                'total_acciones' => count($todasLasAcciones),
                'acciones_por_tipo' => [],
                'acciones_por_usuario' => [],
                'acciones_ultima_semana' => 0,
                'ventas_procesadas' => []
            ];

            $fechaHaceSemana = date('Y-m-d H:i:s', strtotime('-7 days'));
            $ventasUnicas = [];

            foreach ($todasLasAcciones as $accion) {
                // Contar por tipo de acción
                $tipoAccion = $accion['accion'];
                if (!isset($estadisticas['acciones_por_tipo'][$tipoAccion])) {
                    $estadisticas['acciones_por_tipo'][$tipoAccion] = 0;
                }
                $estadisticas['acciones_por_tipo'][$tipoAccion]++;

                // Contar por usuario
                $usuario = $accion['nombre_usuario'];
                if (!isset($estadisticas['acciones_por_usuario'][$usuario])) {
                    $estadisticas['acciones_por_usuario'][$usuario] = 0;
                }
                $estadisticas['acciones_por_usuario'][$usuario]++;

                // Contar acciones de la última semana
                if ($accion['fecha_accion'] >= $fechaHaceSemana) {
                    $estadisticas['acciones_ultima_semana']++;
                }

                // Contar ventas únicas procesadas
                $ventasUnicas[$accion['id_venta']] = $accion['cliente'];
            }

            $estadisticas['ventas_procesadas'] = count($ventasUnicas);

            return $estadisticas;
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas historial: " . $e->getMessage());
            return [
                'total_acciones' => 0,
                'acciones_por_tipo' => [],
                'acciones_por_usuario' => [],
                'acciones_ultima_semana' => 0,
                'ventas_procesadas' => 0
            ];
        }
    }

    /**
     * Obtener ventas en producción con filtros
     */
    public function obtenerVentasProduccion($filtros = [], $pagina = 1, $registrosPorPagina = 10)
    {
        return $this->repository->obtenerVentasProduccion($filtros, $pagina, $registrosPorPagina);
    }

    /**
     * Validar datos de entrada
     */
    public function validarDatos($datos, $tipoValidacion = 'emision')
    {
        $errores = [];

        switch ($tipoValidacion) {
            case 'emision':
                if (empty($datos['productos_completados'])) {
                    $errores[] = 'Debe seleccionar al menos un producto';
                }
                break;

            case 'devolucion':
                if (empty($datos['productos_devueltos'])) {
                    $errores[] = 'Debe seleccionar al menos un producto para devolver';
                }
                if (empty(trim($datos['motivo_devolucion'] ?? ''))) {
                    $errores[] = 'El motivo de devolución es obligatorio';
                }
                break;

            case 'recetas': // ✅ NUEVO TIPO DE VALIDACIÓN
                if (isset($datos['recetas_seleccionadas'])) {
                    $erroresRecetas = $this->validarRecetasSeleccionadas($datos['recetas_seleccionadas']);
                    $errores = array_merge($errores, $erroresRecetas);
                }
                break;
        }

        return $errores;
    }

    /**
     * Obtener estadísticas de producción
     */
    public function obtenerEstadisticasProduccion()
    {
        try {
            // Implementar según necesidades específicas
            return [
                'ventas_en_produccion' => 0,
                'productos_pendientes' => 0,
                'productos_completados' => 0,
                'ordenes_emitidas_hoy' => 0
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [
                'ventas_en_produccion' => 0,
                'productos_pendientes' => 0,
                'productos_completados' => 0,
                'ordenes_emitidas_hoy' => 0
            ];
        }
    }

    /**
     * Formatear moneda
     */
    public function formatearMoneda($monto, $moneda)
    {
        $simbolo = $moneda === 'Dólares' ? 'U$D' : '₲';
        return $simbolo . ' ' . number_format((float)$monto, 2, ',', '.');
    }

    /**
     * Obtener tipos de productos soportados
     */
    public function getTiposProductosSoportados()
    {
        return ['TNT', 'SPUNLACE', 'LAMINADORA', 'TOALLITAS', 'PAÑOS'];
    }
}
