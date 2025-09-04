<?php

class MaterialConsumptionManager
{
    private static $conexion;
    private static $activo = true;
    private static $initialized = false;

    // Inicializar el sistema
    public static function initialize($conexion, $activo = true)
    {
        self::$conexion = $conexion;
        self::$activo = $activo;
        self::$initialized = true;

        error_log("MaterialConsumptionManager inicializado - Estado: " . (self::$activo ? "ACTIVO" : "INACTIVO"));
    }

    // Procesar descuento de materias primas después de registro exitoso
    public static function procesarDescuento($numeroOrden, $datosRegistro)
    {
        if (!self::$activo || !self::$initialized) {
            return ['success' => true, 'mensaje' => 'Sistema desactivado'];
        }

        try {
            $pesoLiquido = self::calcularPesoLiquido($datosRegistro);
            $cantidad = self::calcularCantidadItems($datosRegistro);

            error_log("DESCUENTO - Orden: $numeroOrden | Peso Líquido: $pesoLiquido kg | Cantidad Items: $cantidad");

            $resultado = self::procesarDescuentoMateriasPrimas($numeroOrden, $pesoLiquido, $cantidad, $datosRegistro);

            if ($resultado['success'] && !empty($resultado['descuentos_realizados'])) {
                error_log("Descuentos aplicados en orden $numeroOrden:");
                foreach ($resultado['descuentos_realizados'] as $descuento) {
                    $unidad = $descuento['tipo_descuento'] === 'peso' ? 'kg' : 'unid';
                    error_log("   • {$descuento['nombre_materia_prima']}: -{$descuento['cantidad_descontada']} $unidad");
                }
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("Error en procesarDescuento: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Revertir descuentos al eliminar un registro
    public static function revertirDescuento($numeroOrden, $datosRegistro)
    {
        if (!self::$activo || !self::$initialized) {
            return ['success' => true, 'mensaje' => 'Sistema desactivado'];
        }

        try {
            $pesoLiquido = self::calcularPesoLiquido($datosRegistro);
            $cantidad = self::calcularCantidadItems($datosRegistro);

            error_log("REVERSIÓN - Orden: $numeroOrden | Peso Líquido: $pesoLiquido kg | Cantidad Items: $cantidad");

            $resultado = self::procesarReversionMateriasPrimas($numeroOrden, $pesoLiquido, $cantidad, $datosRegistro);

            if ($resultado['success'] && !empty($resultado['reversiones_realizadas'])) {
                error_log("Reversiones aplicadas en orden $numeroOrden:");
                foreach ($resultado['reversiones_realizadas'] as $reversion) {
                    $unidad = $reversion['tipo_descuento'] === 'peso' ? 'kg' : 'unid';
                    error_log("   • {$reversion['nombre_materia_prima']}: +{$reversion['cantidad_revertida']} $unidad");
                }
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("Error en revertirDescuento: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Validar disponibilidad antes del registro
    public static function validarDisponibilidad($numeroOrden, $datosRegistro)
    {
        if (!self::$activo || !self::$initialized) {
            return ['success' => true, 'puede_continuar' => true, 'mensaje' => 'Sistema desactivado'];
        }

        try {
            $pesoLiquido = self::calcularPesoLiquido($datosRegistro);
            $cantidad = self::calcularCantidadItems($datosRegistro);

            error_log("VALIDACIÓN - Orden: $numeroOrden | Peso Líquido: $pesoLiquido kg | Cantidad Items: $cantidad");

            // Obtener recetas
            $recetas = self::obtenerRecetasOrden($numeroOrden);
            if (empty($recetas)) {
                return ['success' => true, 'puede_continuar' => true, 'mensaje' => 'No hay recetas asociadas'];
            }

            // Validar cada receta
            $faltantes = [];
            foreach ($recetas as $receta) {
                $cantidadNecesaria = self::calcularCantidadNecesaria($receta, $pesoLiquido, $cantidad);

                error_log("Validando: {$receta['nombre_materia_prima']} | Es Extra: " . ($receta['es_materia_extra'] ? 'SÍ' : 'NO') .
                    " | Unidad Extra: {$receta['unidad_medida_extra']} | Cantidad Necesaria: $cantidadNecesaria");

                if ($receta['es_materia_extra']) {
                    if (strtoupper($receta['unidad_medida_extra']) === 'KILOS' || strtoupper($receta['unidad_medida_extra']) === 'KILOGRAMOS') {
                        if ($receta['stock_peso'] < $cantidadNecesaria) {
                            $faltantes[] = [
                                'materia_prima' => $receta['nombre_materia_prima'],
                                'necesario' => $cantidadNecesaria,
                                'disponible' => $receta['stock_peso'],
                                'unidad' => 'kg',
                                'tipo' => 'extra_peso'
                            ];
                            error_log("FALTANTE PESO EXTRA: {$receta['nombre_materia_prima']} - Necesario: $cantidadNecesaria kg, Disponible: {$receta['stock_peso']} kg");
                        }
                    } else if (strtoupper($receta['unidad_medida_extra']) === 'UNIDAD' || strtoupper($receta['unidad_medida_extra']) === 'UNIDADES') {
                        if ($receta['stock_cantidad'] < $cantidadNecesaria) {
                            $faltantes[] = [
                                'materia_prima' => $receta['nombre_materia_prima'],
                                'necesario' => $cantidadNecesaria,
                                'disponible' => $receta['stock_cantidad'],
                                'unidad' => 'unid',
                                'tipo' => 'extra_cantidad'
                            ];
                            error_log("FALTANTE CANTIDAD EXTRA: {$receta['nombre_materia_prima']} - Necesario: $cantidadNecesaria unid, Disponible: {$receta['stock_cantidad']} unid");
                        }
                    }
                } else {
                    // Materia prima normal (por porcentaje)
                    if ($receta['stock_peso'] < $cantidadNecesaria) {
                        $faltantes[] = [
                            'materia_prima' => $receta['nombre_materia_prima'],
                            'necesario' => $cantidadNecesaria,
                            'disponible' => $receta['stock_peso'],
                            'unidad' => 'kg',
                            'tipo' => 'normal'
                        ];
                        error_log("FALTANTE PESO NORMAL: {$receta['nombre_materia_prima']} - Necesario: $cantidadNecesaria kg, Disponible: {$receta['stock_peso']} kg");
                    }
                }
            }

            $puedeContinar = empty($faltantes);

            if (!$puedeContinar) {
                error_log("Stock insuficiente en orden $numeroOrden:");
                foreach ($faltantes as $faltante) {
                    error_log("   • {$faltante['materia_prima']}: Necesario {$faltante['necesario']} {$faltante['unidad']}, Disponible {$faltante['disponible']} {$faltante['unidad']} (Tipo: {$faltante['tipo']})");
                }
            }

            return [
                'success' => true,
                'puede_continuar' => $puedeContinar,
                'mensaje' => $puedeContinar ? 'Stock suficiente' : 'Stock insuficiente',
                'materias_faltantes' => $faltantes
            ];
        } catch (Exception $e) {
            error_log("Error en validarDisponibilidad: " . $e->getMessage());
            return ['success' => false, 'puede_continuar' => false, 'error' => $e->getMessage()];
        }
    }

    // Procesar descuento de materias primas
    private static function procesarDescuentoMateriasPrimas($idOrdenProduccion, $pesoLiquido, $cantidad, $datosRegistro)
    {
        if (!self::$activo) {
            return ['success' => true, 'descuentos_realizados' => [], 'mensaje' => 'Sistema desactivado'];
        }

        try {
            self::$conexion->beginTransaction();

            $recetas = self::obtenerRecetasOrden($idOrdenProduccion);
            if (empty($recetas)) {
                self::$conexion->rollBack();
                return ['success' => true, 'descuentos_realizados' => [], 'mensaje' => 'No hay recetas asociadas'];
            }

            $descuentosRealizados = [];
            $errores = [];

            foreach ($recetas as $receta) {
                try {
                    $cantidadADescontar = self::calcularCantidadNecesaria($receta, $pesoLiquido, $cantidad);

                    if ($cantidadADescontar <= 0) continue;

                    error_log("PROCESANDO: {$receta['nombre_materia_prima']} | Es Extra: " . ($receta['es_materia_extra'] ? 'SÍ' : 'NO') .
                        " | Unidad Extra: {$receta['unidad_medida_extra']} | A Descontar: $cantidadADescontar");

                    // Determinar qué campo actualizar
                    $sql = null;
                    $tipoDescuento = null;

                    if ($receta['es_materia_extra']) {
                        $unidadExtra = strtoupper(trim($receta['unidad_medida_extra']));

                        if ($unidadExtra === 'KILOS' || $unidadExtra === 'KILOGRAMOS') {
                            $sql = "UPDATE public.sist_prod_materia_prima 
                                    SET peso_estimado = GREATEST(peso_estimado - :cantidad, 0),
                                        fecha_movimiento = CURRENT_TIMESTAMP
                                    WHERE id = :id_materia_prima";
                            $tipoDescuento = 'peso';
                            error_log("   → Descuento PESO EXTRA: $cantidadADescontar kg");
                        } else if ($unidadExtra === 'UNIDAD' || $unidadExtra === 'UNIDADES') {
                            $sql = "UPDATE public.sist_prod_materia_prima 
                                    SET cantidad = GREATEST(cantidad - :cantidad, 0),
                                        fecha_movimiento = CURRENT_TIMESTAMP
                                    WHERE id = :id_materia_prima";
                            $tipoDescuento = 'cantidad';
                            error_log("   → Descuento CANTIDAD EXTRA: $cantidadADescontar unidades");
                        } else {
                            error_log("   Unidad extra no reconocida: '$unidadExtra'");
                            $errores[] = "Unidad extra no reconocida para {$receta['nombre_materia_prima']}: '$unidadExtra'";
                            continue;
                        }
                    } else {
                        // Materia prima normal (por porcentaje de peso)
                        $sql = "UPDATE public.sist_prod_materia_prima 
                                SET peso_estimado = GREATEST(peso_estimado - :cantidad, 0),
                                    fecha_movimiento = CURRENT_TIMESTAMP
                                WHERE id = :id_materia_prima";
                        $tipoDescuento = 'peso';
                        error_log("   → Descuento PESO NORMAL: $cantidadADescontar kg");
                    }

                    if ($sql) {
                        $stmt = self::$conexion->prepare($sql);
                        $stmt->bindParam(':cantidad', $cantidadADescontar, PDO::PARAM_STR);
                        $stmt->bindParam(':id_materia_prima', $receta['id_materia_prima'], PDO::PARAM_INT);

                        if ($stmt->execute() && $stmt->rowCount() > 0) {
                            // Registrar movimiento
                            self::registrarMovimiento(
                                $receta['id_materia_prima'],
                                $cantidadADescontar,
                                $tipoDescuento,
                                $receta,
                                'DESCUENTO_PRODUCCION',
                                $datosRegistro
                            );

                            $descuentosRealizados[] = [
                                'nombre_materia_prima' => $receta['nombre_materia_prima'],
                                'cantidad_descontada' => $cantidadADescontar,
                                'tipo_descuento' => $tipoDescuento,
                                'es_materia_extra' => $receta['es_materia_extra'],
                                'unidad_medida_extra' => $receta['unidad_medida_extra']
                            ];

                            error_log("   Descuento aplicado exitosamente");
                        } else {
                            error_log("   No se pudo aplicar el descuento (registro no encontrado o sin cambios)");
                            $errores[] = "No se pudo descontar {$receta['nombre_materia_prima']} (registro no encontrado)";
                        }
                    }
                } catch (Exception $e) {
                    error_log("   Error procesando {$receta['nombre_materia_prima']}: " . $e->getMessage());
                    $errores[] = "Error en {$receta['nombre_materia_prima']}: " . $e->getMessage();
                }
            }

            self::$conexion->commit();

            $mensaje = 'Descuentos procesados';
            if (!empty($errores)) {
                $mensaje .= ' con algunos errores: ' . implode(', ', $errores);
            }

            return [
                'success' => true,
                'descuentos_realizados' => $descuentosRealizados,
                'mensaje' => $mensaje,
                'errores' => $errores
            ];
        } catch (Exception $e) {
            self::$conexion->rollBack();
            error_log("Error en procesarDescuentoMateriasPrimas: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Procesar reversión de descuentos de materias primas
    private static function procesarReversionMateriasPrimas($idOrdenProduccion, $pesoLiquido, $cantidad, $datosRegistro)
    {
        if (!self::$activo) {
            return ['success' => true, 'reversiones_realizadas' => [], 'mensaje' => 'Sistema desactivado'];
        }

        try {
            self::$conexion->beginTransaction();

            $recetas = self::obtenerRecetasOrden($idOrdenProduccion);
            if (empty($recetas)) {
                self::$conexion->rollBack();
                return ['success' => true, 'reversiones_realizadas' => [], 'mensaje' => 'No hay recetas asociadas'];
            }

            $reversionesRealizadas = [];
            $errores = [];

            foreach ($recetas as $receta) {
                try {
                    $cantidadARevertir = self::calcularCantidadNecesaria($receta, $pesoLiquido, $cantidad);

                    if ($cantidadARevertir <= 0) continue;

                    error_log("REVIRTIENDO: {$receta['nombre_materia_prima']} | Es Extra: " . ($receta['es_materia_extra'] ? 'SÍ' : 'NO') .
                        " | Unidad Extra: {$receta['unidad_medida_extra']} | A Revertir: $cantidadARevertir");

                    // Determinar qué campo actualizar (SUMAR en lugar de restar)
                    $sql = null;
                    $tipoDescuento = null;

                    if ($receta['es_materia_extra']) {
                        $unidadExtra = strtoupper(trim($receta['unidad_medida_extra']));

                        if ($unidadExtra === 'KILOS' || $unidadExtra === 'KILOGRAMOS') {
                            $sql = "UPDATE public.sist_prod_materia_prima 
                                    SET peso_estimado = peso_estimado + :cantidad,
                                        fecha_movimiento = CURRENT_TIMESTAMP
                                    WHERE id = :id_materia_prima";
                            $tipoDescuento = 'peso';
                            error_log("   → Reversión PESO EXTRA: +$cantidadARevertir kg");
                        } else if ($unidadExtra === 'UNIDAD' || $unidadExtra === 'UNIDADES') {
                            $sql = "UPDATE public.sist_prod_materia_prima 
                                    SET cantidad = cantidad + :cantidad,
                                        fecha_movimiento = CURRENT_TIMESTAMP
                                    WHERE id = :id_materia_prima";
                            $tipoDescuento = 'cantidad';
                            error_log("   → Reversión CANTIDAD EXTRA: +$cantidadARevertir unidades");
                        } else {
                            error_log("   Unidad extra no reconocida: '$unidadExtra'");
                            $errores[] = "Unidad extra no reconocida para {$receta['nombre_materia_prima']}: '$unidadExtra'";
                            continue;
                        }
                    } else {
                        // Materia prima normal (por porcentaje de peso)
                        $sql = "UPDATE public.sist_prod_materia_prima 
                                SET peso_estimado = peso_estimado + :cantidad,
                                    fecha_movimiento = CURRENT_TIMESTAMP
                                WHERE id = :id_materia_prima";
                        $tipoDescuento = 'peso';
                        error_log("   → Reversión PESO NORMAL: +$cantidadARevertir kg");
                    }

                    if ($sql) {
                        $stmt = self::$conexion->prepare($sql);
                        $stmt->bindParam(':cantidad', $cantidadARevertir, PDO::PARAM_STR);
                        $stmt->bindParam(':id_materia_prima', $receta['id_materia_prima'], PDO::PARAM_INT);

                        if ($stmt->execute() && $stmt->rowCount() > 0) {
                            // Registrar movimiento de reversión
                            self::registrarMovimiento(
                                $receta['id_materia_prima'],
                                $cantidadARevertir,
                                $tipoDescuento,
                                $receta,
                                'REVERSION_ELIMINACION',
                                $datosRegistro
                            );

                            $reversionesRealizadas[] = [
                                'nombre_materia_prima' => $receta['nombre_materia_prima'],
                                'cantidad_revertida' => $cantidadARevertir,
                                'tipo_descuento' => $tipoDescuento,
                                'es_materia_extra' => $receta['es_materia_extra'],
                                'unidad_medida_extra' => $receta['unidad_medida_extra']
                            ];

                            error_log("   Reversión aplicada exitosamente");
                        } else {
                            error_log("   No se pudo aplicar la reversión (registro no encontrado o sin cambios)");
                            $errores[] = "No se pudo revertir {$receta['nombre_materia_prima']} (registro no encontrado)";
                        }
                    }
                } catch (Exception $e) {
                    error_log("   Error procesando reversión {$receta['nombre_materia_prima']}: " . $e->getMessage());
                    $errores[] = "Error en reversión {$receta['nombre_materia_prima']}: " . $e->getMessage();
                }
            }

            self::$conexion->commit();

            $mensaje = 'Reversiones procesadas';
            if (!empty($errores)) {
                $mensaje .= ' con algunos errores: ' . implode(', ', $errores);
            }

            return [
                'success' => true,
                'reversiones_realizadas' => $reversionesRealizadas,
                'mensaje' => $mensaje,
                'errores' => $errores
            ];
        } catch (Exception $e) {
            self::$conexion->rollBack();
            error_log("Error en procesarReversionMateriasPrimas: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Obtener recetas de una orden
    private static function obtenerRecetasOrden($idOrdenProduccion)
    {
        $sql = "SELECT 
                    opr.id as id_orden_receta,
                    r.id_materia_prima,
                    r.cantidad_por_kilo,
                    r.es_materia_extra,
                    r.unidad_medida_extra,
                    r.nombre_receta,
                    mp.descripcion as nombre_materia_prima,
                    mp.peso_estimado as stock_peso,
                    mp.cantidad as stock_cantidad
                FROM public.sist_ventas_orden_produccion_recetas opr
                INNER JOIN public.sist_prod_recetas r ON opr.id_receta = r.id
                INNER JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                WHERE opr.id_orden_produccion = :id_orden_produccion
                AND opr.estado = 'Pendiente'
                AND r.activo = true";

        $stmt = self::$conexion->prepare($sql);
        $stmt->bindParam(':id_orden_produccion', $idOrdenProduccion, PDO::PARAM_INT);
        $stmt->execute();

        $recetas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Debug: mostrar recetas obtenidas
        error_log("Recetas obtenidas para orden $idOrdenProduccion:");
        foreach ($recetas as $receta) {
            error_log("   • {$receta['nombre_materia_prima']} | Extra: " . ($receta['es_materia_extra'] ? 'SÍ' : 'NO') .
                " | Unidad: {$receta['unidad_medida_extra']} | Stock Peso: {$receta['stock_peso']} | Stock Cantidad: {$receta['stock_cantidad']}");
        }

        return $recetas;
    }

    // Calcular cantidad necesaria de una materia prima
    private static function calcularCantidadNecesaria($receta, $pesoLiquido, $cantidad)
    {
        if ($receta['es_materia_extra']) {
            $unidadExtra = strtoupper(trim($receta['unidad_medida_extra']));

            if ($unidadExtra === 'KILOS' || $unidadExtra === 'KILOGRAMOS') {
                // Para materias extra en kilos: cantidad_por_kilo * peso_liquido
                $resultado = $receta['cantidad_por_kilo'] * $pesoLiquido;
                error_log("   Cálculo EXTRA KILOS: {$receta['cantidad_por_kilo']} * $pesoLiquido = $resultado kg");
                return $resultado;
            } else if ($unidadExtra === 'UNIDAD' || $unidadExtra === 'UNIDADES') {
                // Para materias extra en unidades: cantidad_por_kilo * cantidad_items
                $resultado = $receta['cantidad_por_kilo'] * $cantidad;
                error_log("   Cálculo EXTRA UNIDADES: {$receta['cantidad_por_kilo']} * $cantidad = $resultado unid");
                return $resultado;
            }
        } else {
            // Materia prima normal: porcentaje del peso líquido
            $porcentajeDecimal = $receta['cantidad_por_kilo'] / 100.0;
            $resultado = $porcentajeDecimal * $pesoLiquido;
            error_log("   Cálculo NORMAL: {$receta['cantidad_por_kilo']}% * $pesoLiquido = $resultado kg");
            return $resultado;
        }

        error_log("   No se pudo calcular cantidad para {$receta['nombre_materia_prima']}");
        return 0;
    }

    // Calcular peso líquido
    private static function calcularPesoLiquido($datos)
    {
        $pesoBruto = floatval($datos['peso_bruto'] ?? 0);
        $tara = floatval($datos['tara'] ?? 0);

        if (isset($datos['bobinas_pacote']) && $datos['bobinas_pacote'] > 1) {
            $bobinas = intval($datos['bobinas_pacote']);
            return $pesoBruto - ($tara * $bobinas);
        }

        return $pesoBruto - $tara;
    }

    // Calcular cantidad de items
    private static function calcularCantidadItems($datos)
    {
        return isset($datos['bobinas_pacote']) && $datos['bobinas_pacote'] > 1
            ? intval($datos['bobinas_pacote'])
            : 1;
    }

    // Registrar movimiento de materia prima
    private static function registrarMovimiento($idMateriaPrima, $cantidad, $tipo, $receta, $tipoMovimiento = 'DESCUENTO_PRODUCCION', $datosRegistro = null)
    {
        try {
            self::crearOActualizarTablaMovimientos();

            // Verificar si la columna datos_adicionales existe
            $columnaExiste = self::verificarColumnaExiste();

            if ($columnaExiste) {
                $sql = "INSERT INTO public.sist_prod_movimientos_materia_prima 
                        (id_materia_prima, tipo_movimiento, cantidad_afectada, tipo_descuento, usuario, observaciones, datos_adicionales)
                        VALUES (:id_materia_prima, :tipo_movimiento, :cantidad, :tipo, :usuario, :observaciones, :datos_adicionales)";
            } else {
                $sql = "INSERT INTO public.sist_prod_movimientos_materia_prima 
                        (id_materia_prima, tipo_movimiento, cantidad_afectada, tipo_descuento, usuario, observaciones)
                        VALUES (:id_materia_prima, :tipo_movimiento, :cantidad, :tipo, :usuario, :observaciones)";
            }

            $stmt = self::$conexion->prepare($sql);
            $stmt->bindParam(':id_materia_prima', $idMateriaPrima, PDO::PARAM_INT);
            $stmt->bindParam(':tipo_movimiento', $tipoMovimiento, PDO::PARAM_STR);
            $stmt->bindParam(':cantidad', $cantidad, PDO::PARAM_STR);
            $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);

            $usuario = $_SESSION['nombre'] ?? 'SISTEMA_PRODUCCION';
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);

            $accion = $tipoMovimiento === 'REVERSION_ELIMINACION' ? 'Reversión por eliminación' : 'Descuento automático';
            $observaciones = "$accion - {$receta['nombre_receta']} - {$receta['nombre_materia_prima']}";

            if ($receta['es_materia_extra']) {
                $observaciones .= " (Materia Extra: {$receta['unidad_medida_extra']})";
            }

            $stmt->bindParam(':observaciones', $observaciones, PDO::PARAM_STR);

            // Solo agregar datos adicionales si la columna existe
            if ($columnaExiste) {
                $datosAdicionales = json_encode([
                    'es_materia_extra' => $receta['es_materia_extra'],
                    'unidad_medida_extra' => $receta['unidad_medida_extra'],
                    'nombre_receta' => $receta['nombre_receta'],
                    'cantidad_por_kilo_original' => $receta['cantidad_por_kilo'],
                    'datos_registro' => $datosRegistro
                ]);
                $stmt->bindParam(':datos_adicionales', $datosAdicionales, PDO::PARAM_STR);
            }

            $stmt->execute();
            error_log("Movimiento registrado: $tipoMovimiento - $idMateriaPrima - $cantidad $tipo");
        } catch (Exception $e) {
            error_log("Error registrando movimiento: " . $e->getMessage());
        }
    }

    // Crear o actualizar tabla de movimientos
    private static function crearOActualizarTablaMovimientos()
    {
        try {
            // Verificar si la tabla existe
            $sqlCheck = "SELECT COUNT(*) FROM information_schema.tables 
                        WHERE table_schema = 'public' 
                        AND table_name = 'sist_prod_movimientos_materia_prima'";
            $stmt = self::$conexion->prepare($sqlCheck);
            $stmt->execute();
            $tablaExiste = $stmt->fetchColumn() > 0;

            if (!$tablaExiste) {
                // Crear tabla completa
                $sqlCreate = "CREATE TABLE public.sist_prod_movimientos_materia_prima (
                    id SERIAL PRIMARY KEY,
                    id_materia_prima INTEGER NOT NULL,
                    tipo_movimiento VARCHAR(50) NOT NULL,
                    cantidad_afectada NUMERIC NOT NULL,
                    tipo_descuento VARCHAR(20) NOT NULL,
                    usuario TEXT,
                    fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    observaciones TEXT,
                    datos_adicionales JSONB
                )";
                self::$conexion->exec($sqlCreate);
                error_log("Tabla sist_prod_movimientos_materia_prima creada");
            } else {
                // Verificar si tiene la columna datos_adicionales
                $sqlCheckColumn = "SELECT COUNT(*) FROM information_schema.columns 
                                  WHERE table_schema = 'public' 
                                  AND table_name = 'sist_prod_movimientos_materia_prima' 
                                  AND column_name = 'datos_adicionales'";
                $stmt = self::$conexion->prepare($sqlCheckColumn);
                $stmt->execute();
                $columnaExiste = $stmt->fetchColumn() > 0;

                if (!$columnaExiste) {
                    try {
                        $sqlAddColumn = "ALTER TABLE public.sist_prod_movimientos_materia_prima 
                                        ADD COLUMN datos_adicionales JSONB";
                        self::$conexion->exec($sqlAddColumn);
                        error_log("Columna datos_adicionales agregada a la tabla");
                    } catch (Exception $e) {
                        error_log("No se pudo agregar columna datos_adicionales: " . $e->getMessage());
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error en crearOActualizarTablaMovimientos: " . $e->getMessage());
        }
    }

    // Verificar si la columna datos_adicionales existe
    private static function verificarColumnaExiste()
    {
        try {
            $sqlCheckColumn = "SELECT COUNT(*) FROM information_schema.columns 
                              WHERE table_schema = 'public' 
                              AND table_name = 'sist_prod_movimientos_materia_prima' 
                              AND column_name = 'datos_adicionales'";
            $stmt = self::$conexion->prepare($sqlCheckColumn);
            $stmt->execute();
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("Error verificando columna: " . $e->getMessage());
            return false;
        }
    }

    // Activar/Desactivar el sistema
    public static function setActivo($activo)
    {
        $estadoAnterior = self::$activo;
        self::$activo = $activo;

        if ($estadoAnterior !== $activo) {
            error_log("MaterialConsumptionManager " . ($activo ? "ACTIVADO" : "DESACTIVADO") . " programáticamente");
        }
    }

    // Obtener estado del sistema
    public static function isActivo()
    {
        return self::$activo;
    }

    // Obtener stock de materias primas para una orden
    public static function obtenerStockOrden($idOrdenProduccion)
    {
        try {
            $sql = "SELECT 
                        mp.id,
                        mp.descripcion,
                        mp.peso_estimado,
                        mp.cantidad,
                        mp.unidad,
                        mp.tipo
                    FROM public.sist_prod_materia_prima mp
                    WHERE mp.id IN (
                        SELECT DISTINCT r.id_materia_prima 
                        FROM public.sist_ventas_orden_produccion_recetas opr
                        INNER JOIN public.sist_prod_recetas r ON opr.id_receta = r.id
                        WHERE opr.id_orden_produccion = :id_orden_produccion
                    )
                    ORDER BY mp.descripcion";

            $stmt = self::$conexion->prepare($sql);
            $stmt->bindParam(':id_orden_produccion', $idOrdenProduccion, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'success' => true,
                'materias_primas' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'materias_primas' => []];
        }
    }

    // Ver historial de movimientos de una materia prima
    public static function obtenerHistorialMovimientos($idMateriaPrima = null, $limite = 50)
    {
        try {
            $sql = "SELECT 
                        m.*,
                        mp.descripcion as nombre_materia_prima
                    FROM public.sist_prod_movimientos_materia_prima m
                    INNER JOIN public.sist_prod_materia_prima mp ON m.id_materia_prima = mp.id";

            if ($idMateriaPrima) {
                $sql .= " WHERE m.id_materia_prima = :id_materia_prima";
            }

            $sql .= " ORDER BY m.fecha_movimiento DESC LIMIT :limite";

            $stmt = self::$conexion->prepare($sql);

            if ($idMateriaPrima) {
                $stmt->bindParam(':id_materia_prima', $idMateriaPrima, PDO::PARAM_INT);
            }

            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'success' => true,
                'movimientos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'movimientos' => []];
        }
    }

    // Obtener información debug de una orden
    public static function debugOrden($numeroOrden)
    {
        if (!self::$activo || !self::$initialized) {
            return ['success' => false, 'error' => 'Sistema no inicializado'];
        }

        try {
            $recetas = self::obtenerRecetasOrden($numeroOrden);

            $debug = [
                'orden' => $numeroOrden,
                'total_recetas' => count($recetas),
                'recetas' => []
            ];

            foreach ($recetas as $receta) {
                $debug['recetas'][] = [
                    'nombre' => $receta['nombre_materia_prima'],
                    'es_extra' => $receta['es_materia_extra'],
                    'unidad_extra' => $receta['unidad_medida_extra'],
                    'cantidad_por_kilo' => $receta['cantidad_por_kilo'],
                    'stock_peso' => $receta['stock_peso'],
                    'stock_cantidad' => $receta['stock_cantidad']
                ];
            }

            return ['success' => true, 'debug' => $debug];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
