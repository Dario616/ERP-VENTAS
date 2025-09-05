<?php

/**
 * Repositorio para producciones de materiales
 * Versión modificada para manejar materiales tipo TUBO
 */
class ProduccionRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    /**
     * NUEVA FUNCIÓN: Verificar si un material es tipo tubo
     */
    private function esTubo($nombreMaterial)
    {
        return stripos($nombreMaterial, 'tubo') !== false;
    }

    /**
     * Buscar orden de producción por ID
     */
    public function buscarOrdenProduccion($idOrden)
    {
        try {
            $sql = "SELECT o.*,
                           mp.descripcion as materia_prima_desc,
                           TO_CHAR(o.fecha_orden, 'DD/MM/YYYY') as fecha_orden_formateada
                    FROM public.sist_prod_ordenes_produccion_material o
                    LEFT JOIN public.sist_prod_materia_prima mp ON o.id_materia_prima = mp.id
                    WHERE o.id = :id_orden AND o.activo = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_orden', $idOrden, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error buscando orden de producción: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener producciones existentes de una orden
     */
    public function obtenerProduccionesOrden($idOrden)
    {
        try {
            $sql = "SELECT *,
                           TO_CHAR(fecha_registro, 'DD/MM/YYYY HH24:MI:SS') as fecha_registro_formateada
                    FROM public.sist_prod_mat 
                    WHERE id_op = :id_orden 
                    ORDER BY id DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_orden', $idOrden, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo producciones: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Validar stock disponible antes de crear producción
     */
    private function validarStockDisponible($idOrden, $pesoBruto)
    {
        try {
            // Obtener el id_materia_prima de la orden
            $sqlOrden = "SELECT id_materia_prima FROM public.sist_prod_ordenes_produccion_material WHERE id = :id_orden";
            $stmtOrden = $this->conexion->prepare($sqlOrden);
            $stmtOrden->bindParam(':id_orden', $idOrden, PDO::PARAM_INT);
            $stmtOrden->execute();
            $orden = $stmtOrden->fetch(PDO::FETCH_ASSOC);

            if (!$orden || !$orden['id_materia_prima']) {
                return [
                    'valido' => false,
                    'mensaje' => 'No se encontró la orden de producción'
                ];
            }

            $idMateriaPrimaProducida = $orden['id_materia_prima'];

            // Buscar recetas para validar stock
            $sqlRecetas = "SELECT r.id_materia_prima, r.cantidad_por_kilo, mp.descripcion, mp.peso_estimado
                      FROM public.sist_prod_recetas r
                      LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                      WHERE r.tipo_receta = 'MATERIA_PRIMA' 
                      AND r.id_materia_prima_objetivo = :id_materia_prima
                      AND r.activo = true";

            $stmtRecetas = $this->conexion->prepare($sqlRecetas);
            $stmtRecetas->bindParam(':id_materia_prima', $idMateriaPrimaProducida, PDO::PARAM_INT);
            $stmtRecetas->execute();
            $recetas = $stmtRecetas->fetchAll(PDO::FETCH_ASSOC);

            if (empty($recetas)) {
                return [
                    'valido' => true,
                    'mensaje' => 'No hay recetas definidas para validar'
                ];
            }

            $materialesInsuficientes = [];

            // Validar cada materia prima de la receta
            foreach ($recetas as $receta) {
                $porcentajeDecimal = floatval($receta['cantidad_por_kilo']) / 100;
                $cantidadRequerida = $porcentajeDecimal * floatval($pesoBruto);
                $stockDisponible = floatval($receta['peso_estimado'] ?? 0);

                if ($cantidadRequerida > $stockDisponible) {
                    $materialesInsuficientes[] = [
                        'material' => $receta['descripcion'],
                        'requerido' => $cantidadRequerida,
                        'disponible' => $stockDisponible,
                        'faltante' => $cantidadRequerida - $stockDisponible
                    ];
                }
            }

            if (!empty($materialesInsuficientes)) {
                $mensaje = "Stock insuficiente para: ";
                foreach ($materialesInsuficientes as $material) {
                    $mensaje .= "\n• {$material['material']}: Requiere {$material['requerido']} kg, disponible {$material['disponible']} kg (falta {$material['faltante']} kg)";
                }

                return [
                    'valido' => false,
                    'mensaje' => $mensaje,
                    'materiales_insuficientes' => $materialesInsuficientes
                ];
            }

            return [
                'valido' => true,
                'mensaje' => 'Stock disponible para la producción'
            ];
        } catch (Exception $e) {
            error_log("💥 Error validando stock disponible: " . $e->getMessage());
            return [
                'valido' => false,
                'mensaje' => 'Error validando stock: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crear nueva producción - MODIFICADO para manejar tubos y cantidad
     */
    public function crearProduccion($datos)
    {
        try {
            // NUEVO: Detectar si es tubo
            $esTubo = $this->esTubo($datos['nombre']);

            // Verificar stock antes de crear
            $validacionStock = $this->validarStockDisponible($datos['id_op'], $datos['peso_bruto']);

            if (!$validacionStock['valido']) {
                return [
                    'success' => false,
                    'id' => null,
                    'error' => $validacionStock['mensaje'],
                    'tipo_error' => 'stock_insuficiente'
                ];
            }

            $this->conexion->beginTransaction();

            // Insertar en sist_prod_mat incluyendo cantidad
            $sql = "INSERT INTO public.sist_prod_mat 
                (nombre, id_op, peso_bruto, peso_liquido, tara, fecha_registro, cantidad)
                VALUES 
                (:nombre, :id_op, :peso_bruto, :peso_liquido, :tara, NOW(), :cantidad)
                RETURNING id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);
            $stmt->bindParam(':id_op', $datos['id_op'], PDO::PARAM_INT);
            $stmt->bindParam(':peso_bruto', $datos['peso_bruto'], PDO::PARAM_STR);
            $stmt->bindParam(':peso_liquido', $datos['peso_liquido'], PDO::PARAM_STR);
            $stmt->bindParam(':tara', $datos['tara'], PDO::PARAM_STR);

            // Bind para cantidad (puede ser null si no es unidades)
            if (isset($datos['cantidad']) && $datos['cantidad'] !== null) {
                $stmt->bindParam(':cantidad', $datos['cantidad'], PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':cantidad', null, PDO::PARAM_NULL);
            }

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $idProduccion = $resultado['id'];

            // MODIFICADO: Actualizar peso_estimado Y cantidad en sist_prod_materia_prima
            error_log("🔍 DEBUG antes de actualizarPeso - Datos: " . json_encode([
                'nombre' => $datos['nombre'],
                'peso_bruto' => $datos['peso_bruto'],
                'cantidad' => $datos['cantidad'] ?? 'NO_DEFINIDO',
                'es_tubo' => $esTubo
            ]));

            // NUEVO: Para tubos, siempre actualizar tanto peso como cantidad si es unidad
            if ($esTubo) {
                $this->actualizarPesoYCantidadEstimadoTubo($datos['nombre'], $datos['peso_bruto'], $datos['cantidad'] ?? null);
            } else {
                $this->actualizarPesoYCantidadEstimado($datos['nombre'], $datos['peso_bruto'], $datos['cantidad'] ?? null);
            }

            // Descontar materias primas según receta
            $this->descontarMateriaPrimaSegunReceta($datos['id_op'], $datos['peso_bruto']);

            $this->conexion->commit();

            $logMsg = "📦 Producción creada - ID: $idProduccion - Orden: {$datos['id_op']}";
            if (isset($datos['cantidad'])) {
                $logMsg .= " - Cantidad: {$datos['cantidad']}";
            }
            if ($esTubo) {
                $logMsg .= " - TUBO (tara: 0)";
            }
            error_log($logMsg);

            return [
                'success' => true,
                'id' => $idProduccion,
                'error' => null
            ];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error creando producción: " . $e->getMessage());
            return [
                'success' => false,
                'id' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * NUEVA FUNCIÓN: Actualizar peso y cantidad específico para tubos
     */
    private function actualizarPesoYCantidadEstimadoTubo($descripcion, $pesoBruto, $cantidad = null)
    {
        try {
            error_log("🔧 TUBO - Actualizando material: $descripcion - Peso: $pesoBruto - Cantidad: " . var_export($cantidad, true));

            // Para tubos, SIEMPRE actualizar peso
            // Si además es unidad (UN), actualizar también cantidad
            if ($cantidad !== null && intval($cantidad) > 0) {
                $cantidadInt = intval($cantidad);

                error_log("🔧 TUBO - Actualizando PESO Y CANTIDAD - Material: $descripcion - Peso: $pesoBruto - Cantidad: $cantidadInt");

                $sql = "UPDATE public.sist_prod_materia_prima 
                        SET peso_estimado = COALESCE(peso_estimado, 0) + :peso_bruto,
                            cantidad = COALESCE(cantidad, 0) + :cantidad,
                            fecha_movimiento = NOW()
                        WHERE LOWER(TRIM(descripcion)) = LOWER(TRIM(:descripcion))";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':peso_bruto', $pesoBruto, PDO::PARAM_STR);
                $stmt->bindParam(':cantidad', $cantidadInt, PDO::PARAM_INT);
                $stmt->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
                $stmt->execute();

                $registrosAfectados = $stmt->rowCount();
                if ($registrosAfectados > 0) {
                    error_log("✅ TUBO - Peso y cantidad estimados actualizados - Material: $descripcion - Peso: $pesoBruto - Cantidad: $cantidadInt");
                } else {
                    error_log("⚠️ TUBO - No se encontró materia prima para actualizar: $descripcion");
                }
            } else {
                // Solo actualizar peso para tubos sin cantidad
                error_log("🔧 TUBO - Actualizando SOLO peso (sin cantidad) - Material: $descripcion - Peso: $pesoBruto");

                $sql = "UPDATE public.sist_prod_materia_prima 
                        SET peso_estimado = COALESCE(peso_estimado, 0) + :peso_bruto,
                            fecha_movimiento = NOW()
                        WHERE LOWER(TRIM(descripcion)) = LOWER(TRIM(:descripcion))";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':peso_bruto', $pesoBruto, PDO::PARAM_STR);
                $stmt->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
                $stmt->execute();

                $registrosAfectados = $stmt->rowCount();
                if ($registrosAfectados > 0) {
                    error_log("✅ TUBO - Peso estimado actualizado - Material: $descripcion - Peso: $pesoBruto");
                } else {
                    error_log("⚠️ TUBO - No se encontró materia prima para actualizar: $descripcion");
                }
            }
        } catch (Exception $e) {
            error_log("💥 Error actualizando peso y cantidad para TUBO: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Descontar materia prima según receta
     */
    private function descontarMateriaPrimaSegunReceta($idOrden, $pesoBruto)
    {
        try {
            // Obtener el id_materia_prima de la orden (producto que se está produciendo)
            $sqlOrden = "SELECT id_materia_prima FROM public.sist_prod_ordenes_produccion_material WHERE id = :id_orden";
            $stmtOrden = $this->conexion->prepare($sqlOrden);
            $stmtOrden->bindParam(':id_orden', $idOrden, PDO::PARAM_INT);
            $stmtOrden->execute();
            $orden = $stmtOrden->fetch(PDO::FETCH_ASSOC);

            if (!$orden || !$orden['id_materia_prima']) {
                error_log("⚠️ No se encontró materia prima objetivo para la orden: $idOrden");
                return;
            }

            $idMateriaPrimaProducida = $orden['id_materia_prima'];

            // Buscar recetas para este producto
            $sqlRecetas = "SELECT r.id_materia_prima, r.cantidad_por_kilo, mp.descripcion
                          FROM public.sist_prod_recetas r
                          LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                          WHERE r.tipo_receta = 'MATERIA_PRIMA' 
                          AND r.id_materia_prima_objetivo = :id_materia_prima
                          AND r.activo = true";

            $stmtRecetas = $this->conexion->prepare($sqlRecetas);
            $stmtRecetas->bindParam(':id_materia_prima', $idMateriaPrimaProducida, PDO::PARAM_INT);
            $stmtRecetas->execute();
            $recetas = $stmtRecetas->fetchAll(PDO::FETCH_ASSOC);

            if (empty($recetas)) {
                error_log("ℹ️ No se encontraron recetas para la materia prima ID: $idMateriaPrimaProducida");
                return;
            }

            // Procesar cada materia prima de la receta
            foreach ($recetas as $receta) {
                // Convertir porcentaje a decimal
                $porcentajeDecimal = floatval($receta['cantidad_por_kilo']) / 100;
                $cantidadADescontar = $porcentajeDecimal * floatval($pesoBruto);

                if ($cantidadADescontar > 0) {
                    $this->descontarMateriaPrima($receta['id_materia_prima'], $cantidadADescontar);
                    error_log("📉 Descuento por receta - Material: {$receta['descripcion']} - Cantidad: $cantidadADescontar kg");
                }
            }
        } catch (Exception $e) {
            error_log("💥 Error descontando materia prima según receta: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Descontar cantidad específica de materia prima
     */
    private function descontarMateriaPrima($idMateriaPrima, $cantidad)
    {
        try {
            $sql = "UPDATE public.sist_prod_materia_prima 
                    SET peso_estimado = GREATEST(0, COALESCE(peso_estimado, 0) - :cantidad),
                        fecha_movimiento = NOW()
                    WHERE id = :id_materia_prima";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':cantidad', $cantidad, PDO::PARAM_STR);
            $stmt->bindParam(':id_materia_prima', $idMateriaPrima, PDO::PARAM_INT);
            $stmt->execute();

            $registrosAfectados = $stmt->rowCount();
            if ($registrosAfectados > 0) {
                error_log("✅ Materia prima descontada - ID: $idMateriaPrima - Cantidad: $cantidad");
            } else {
                error_log("⚠️ No se pudo descontar materia prima ID: $idMateriaPrima");
            }
        } catch (Exception $e) {
            error_log("💥 Error descontando materia prima: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualizar peso estimado Y cantidad en materia prima
     */
    private function actualizarPesoYCantidadEstimado($descripcion, $pesoBruto, $cantidad = null)
    {
        try {
            // Debug: Verificar qué se está recibiendo
            error_log("🔍 DEBUG actualizarPeso - Descripcion: $descripcion - Peso: $pesoBruto - Cantidad: " . var_export($cantidad, true));

            // Si hay cantidad, actualizar también el campo cantidad
            if ($cantidad !== null && intval($cantidad) > 0) {
                $cantidadInt = intval($cantidad);

                error_log("🔄 Actualizando peso Y cantidad - Material: $descripcion - Peso: $pesoBruto - Cantidad: $cantidadInt");

                $sql = "UPDATE public.sist_prod_materia_prima 
                        SET peso_estimado = COALESCE(peso_estimado, 0) + :peso_bruto,
                            cantidad = COALESCE(cantidad, 0) + :cantidad,
                            fecha_movimiento = NOW()
                        WHERE LOWER(TRIM(descripcion)) = LOWER(TRIM(:descripcion))";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':peso_bruto', $pesoBruto, PDO::PARAM_STR);
                $stmt->bindParam(':cantidad', $cantidadInt, PDO::PARAM_INT);
                $stmt->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
                $stmt->execute();

                $registrosAfectados = $stmt->rowCount();
                if ($registrosAfectados > 0) {
                    error_log("✅ Peso y cantidad estimados actualizados - Material: $descripcion - Peso: $pesoBruto - Cantidad: $cantidadInt");
                } else {
                    error_log("⚠️ No se encontró materia prima para actualizar: $descripcion");
                }
            } else {
                // Solo actualizar peso (lógica original para productos no unitarios)
                error_log("🔄 Actualizando SOLO peso (sin cantidad) - Material: $descripcion - Peso: $pesoBruto - Cantidad recibida: " . var_export($cantidad, true));

                $sql = "UPDATE public.sist_prod_materia_prima 
                        SET peso_estimado = COALESCE(peso_estimado, 0) + :peso_bruto,
                            fecha_movimiento = NOW()
                        WHERE LOWER(TRIM(descripcion)) = LOWER(TRIM(:descripcion))";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':peso_bruto', $pesoBruto, PDO::PARAM_STR);
                $stmt->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
                $stmt->execute();

                $registrosAfectados = $stmt->rowCount();
                if ($registrosAfectados > 0) {
                    error_log("✅ Peso estimado actualizado - Material: $descripcion - Peso: $pesoBruto");
                } else {
                    error_log("⚠️ No se encontró materia prima para actualizar: $descripcion");
                }
            }
        } catch (Exception $e) {
            error_log("💥 Error actualizando peso y cantidad estimados: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Eliminar producción - MODIFICADO para revertir cantidad también y manejar tubos
     */
    public function eliminarProduccion($id, $usuario)
    {
        try {
            $this->conexion->beginTransaction();

            // Obtener datos antes de eliminar para revertir peso, cantidad y receta
            $sql = "SELECT pm.id_op, pm.nombre, pm.peso_bruto, pm.cantidad 
                    FROM public.sist_prod_mat pm 
                    WHERE pm.id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $produccion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$produccion) {
                throw new Exception("Producción no encontrada");
            }

            // NUEVO: Detectar si es tubo
            $esTubo = $this->esTubo($produccion['nombre']);

            // Eliminar producción
            $sql = "DELETE FROM public.sist_prod_mat WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            // MODIFICADO: Revertir peso estimado Y cantidad
            error_log("🔍 DEBUG antes de revertirPeso - Datos: " . json_encode([
                'nombre' => $produccion['nombre'],
                'peso_bruto' => $produccion['peso_bruto'],
                'cantidad' => $produccion['cantidad'] ?? 'NO_DEFINIDO',
                'es_tubo' => $esTubo
            ]));

            // NUEVO: Para tubos, usar función específica de reversión
            if ($esTubo) {
                $this->revertirPesoYCantidadEstimadoTubo(
                    $produccion['nombre'],
                    $produccion['peso_bruto'],
                    $produccion['cantidad'] ?? null
                );
            } else {
                $this->revertirPesoYCantidadEstimado(
                    $produccion['nombre'],
                    $produccion['peso_bruto'],
                    $produccion['cantidad'] ?? null
                );
            }

            $this->revertirDescuentoReceta($produccion['id_op'], $produccion['peso_bruto']);

            $this->conexion->commit();

            $logMsg = "🗑️ Producción eliminada - ID: $id - Usuario: $usuario";
            if ($esTubo) {
                $logMsg .= " - TUBO";
            }
            error_log($logMsg);

            return [
                'success' => true,
                'registros_afectados' => $stmt->rowCount(),
                'error' => null
            ];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error eliminando producción: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * NUEVA FUNCIÓN: Revertir peso y cantidad específico para tubos
     */
    private function revertirPesoYCantidadEstimadoTubo($descripcion, $pesoBruto, $cantidad = null)
    {
        try {
            error_log("🔧 TUBO - Revirtiendo material: $descripcion - Peso: $pesoBruto - Cantidad: " . var_export($cantidad, true));

            // Para tubos, SIEMPRE revertir peso
            // Si además tiene cantidad, revertir también cantidad
            if ($cantidad !== null && intval($cantidad) > 0) {
                $cantidadInt = intval($cantidad);

                error_log("🔧 TUBO - Revirtiendo PESO Y CANTIDAD - Material: $descripcion - Peso: $pesoBruto - Cantidad: $cantidadInt");

                $sql = "UPDATE public.sist_prod_materia_prima 
                        SET peso_estimado = GREATEST(0, COALESCE(peso_estimado, 0) - :peso_bruto),
                            cantidad = GREATEST(0, COALESCE(cantidad, 0) - :cantidad),
                            fecha_movimiento = NOW()
                        WHERE LOWER(TRIM(descripcion)) = LOWER(TRIM(:descripcion))";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':peso_bruto', $pesoBruto, PDO::PARAM_STR);
                $stmt->bindParam(':cantidad', $cantidadInt, PDO::PARAM_INT);
                $stmt->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
                $stmt->execute();

                error_log("↩️ TUBO - Peso y cantidad estimados revertidos - Material: $descripcion - Peso: $pesoBruto - Cantidad: $cantidadInt");
            } else {
                // Solo revertir peso para tubos sin cantidad
                error_log("🔧 TUBO - Revirtiendo SOLO peso (sin cantidad) - Material: $descripcion - Peso: $pesoBruto");

                $sql = "UPDATE public.sist_prod_materia_prima 
                        SET peso_estimado = GREATEST(0, COALESCE(peso_estimado, 0) - :peso_bruto),
                            fecha_movimiento = NOW()
                        WHERE LOWER(TRIM(descripcion)) = LOWER(TRIM(:descripcion))";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':peso_bruto', $pesoBruto, PDO::PARAM_STR);
                $stmt->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
                $stmt->execute();

                error_log("↩️ TUBO - Peso estimado revertido - Material: $descripcion - Peso: $pesoBruto");
            }
        } catch (Exception $e) {
            error_log("💥 Error revirtiendo peso y cantidad para TUBO: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Revertir descuento de receta
     */
    private function revertirDescuentoReceta($idOrden, $pesoBruto)
    {
        try {
            // Obtener el id_materia_prima de la orden
            $sqlOrden = "SELECT id_materia_prima FROM public.sist_prod_ordenes_produccion_material WHERE id = :id_orden";
            $stmtOrden = $this->conexion->prepare($sqlOrden);
            $stmtOrden->bindParam(':id_orden', $idOrden, PDO::PARAM_INT);
            $stmtOrden->execute();
            $orden = $stmtOrden->fetch(PDO::FETCH_ASSOC);

            if (!$orden || !$orden['id_materia_prima']) {
                return;
            }

            $idMateriaPrimaProducida = $orden['id_materia_prima'];

            // Buscar recetas para revertir
            $sqlRecetas = "SELECT r.id_materia_prima, r.cantidad_por_kilo, mp.descripcion
                          FROM public.sist_prod_recetas r
                          LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                          WHERE r.tipo_receta = 'MATERIA_PRIMA' 
                          AND r.id_materia_prima_objetivo = :id_materia_prima
                          AND r.activo = true";

            $stmtRecetas = $this->conexion->prepare($sqlRecetas);
            $stmtRecetas->bindParam(':id_materia_prima', $idMateriaPrimaProducida, PDO::PARAM_INT);
            $stmtRecetas->execute();
            $recetas = $stmtRecetas->fetchAll(PDO::FETCH_ASSOC);

            // Revertir cada materia prima de la receta
            foreach ($recetas as $receta) {
                // Convertir porcentaje a decimal
                $porcentajeDecimal = floatval($receta['cantidad_por_kilo']) / 100;
                $cantidadARevertir = $porcentajeDecimal * floatval($pesoBruto);

                if ($cantidadARevertir > 0) {
                    $this->revertirMateriaPrima($receta['id_materia_prima'], $cantidadARevertir);
                    error_log("📈 Reversión por receta - Material: {$receta['descripcion']} - Cantidad: $cantidadARevertir kg");
                }
            }
        } catch (Exception $e) {
            error_log("💥 Error revirtiendo descuento de receta: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Revertir (aumentar) cantidad específica de materia prima
     */
    private function revertirMateriaPrima($idMateriaPrima, $cantidad)
    {
        try {
            $sql = "UPDATE public.sist_prod_materia_prima 
                    SET peso_estimado = COALESCE(peso_estimado, 0) + :cantidad,
                        fecha_movimiento = NOW()
                    WHERE id = :id_materia_prima";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':cantidad', $cantidad, PDO::PARAM_STR);
            $stmt->bindParam(':id_materia_prima', $idMateriaPrima, PDO::PARAM_INT);
            $stmt->execute();

            error_log("↩️ Materia prima revertida - ID: $idMateriaPrima - Cantidad: $cantidad");
        } catch (Exception $e) {
            error_log("💥 Error revirtiendo materia prima: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Revertir peso estimado Y cantidad en materia prima
     */
    private function revertirPesoYCantidadEstimado($descripcion, $pesoBruto, $cantidad = null)
    {
        try {
            // Debug: Verificar qué se está recibiendo
            error_log("🔍 DEBUG revertirPeso - Descripcion: $descripcion - Peso: $pesoBruto - Cantidad: " . var_export($cantidad, true));

            // Si hay cantidad, revertir también el campo cantidad
            if ($cantidad !== null && intval($cantidad) > 0) {
                $cantidadInt = intval($cantidad);

                error_log("🔄 Revirtiendo peso Y cantidad - Material: $descripcion - Peso: $pesoBruto - Cantidad: $cantidadInt");

                $sql = "UPDATE public.sist_prod_materia_prima 
                        SET peso_estimado = GREATEST(0, COALESCE(peso_estimado, 0) - :peso_bruto),
                            cantidad = GREATEST(0, COALESCE(cantidad, 0) - :cantidad),
                            fecha_movimiento = NOW()
                        WHERE LOWER(TRIM(descripcion)) = LOWER(TRIM(:descripcion))";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':peso_bruto', $pesoBruto, PDO::PARAM_STR);
                $stmt->bindParam(':cantidad', $cantidadInt, PDO::PARAM_INT);
                $stmt->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
                $stmt->execute();

                error_log("↩️ Peso y cantidad estimados revertidos - Material: $descripcion - Peso: $pesoBruto - Cantidad: $cantidadInt");
            } else {
                // Solo revertir peso (lógica original para productos no unitarios)
                error_log("🔄 Revirtiendo SOLO peso (sin cantidad) - Material: $descripcion - Peso: $pesoBruto - Cantidad recibida: " . var_export($cantidad, true));

                $sql = "UPDATE public.sist_prod_materia_prima 
                        SET peso_estimado = GREATEST(0, COALESCE(peso_estimado, 0) - :peso_bruto),
                            fecha_movimiento = NOW()
                        WHERE LOWER(TRIM(descripcion)) = LOWER(TRIM(:descripcion))";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':peso_bruto', $pesoBruto, PDO::PARAM_STR);
                $stmt->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
                $stmt->execute();

                error_log("↩️ Peso estimado revertido - Material: $descripcion - Peso: $pesoBruto");
            }
        } catch (Exception $e) {
            error_log("💥 Error revirtiendo peso y cantidad estimados: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener estadísticas de una orden - MODIFICADO para incluir cantidad
     */
    public function obtenerEstadisticasOrden($idOrden)
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_producciones,
                        COALESCE(SUM(peso_bruto), 0) as total_peso_bruto,
                        COALESCE(SUM(peso_liquido), 0) as total_peso_liquido,
                        COALESCE(SUM(tara), 0) as total_tara,
                        COALESCE(AVG(peso_bruto), 0) as promedio_peso_bruto,
                        COALESCE(AVG(peso_liquido), 0) as promedio_peso_liquido,
                        COALESCE(SUM(cantidad), 0) as total_cantidad_unidades
                    FROM public.sist_prod_mat 
                    WHERE id_op = :id_orden";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_orden', $idOrden, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo estadísticas: " . $e->getMessage());
            return [
                'total_producciones' => 0,
                'total_peso_bruto' => 0,
                'total_peso_liquido' => 0,
                'total_tara' => 0,
                'promedio_peso_bruto' => 0,
                'promedio_peso_liquido' => 0,
                'total_cantidad_unidades' => 0
            ];
        }
    }

    /**
     * Verificar si existe una producción
     */
    public function existeProduccion($id)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM public.sist_prod_mat WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (Exception $e) {
            error_log("💥 Error verificando existencia de producción: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Buscar órdenes de producción disponibles - MODIFICADO para incluir cantidad y detectar tubos
     */
    public function buscarOrdenesDisponibles($termino = '')
    {
        try {
            $sql = "SELECT o.id, o.estado, o.unidad_medida,
                           mp.descripcion as materia_prima_desc,
                           o.cantidad_solicitada,
                           TO_CHAR(o.fecha_orden, 'DD/MM/YYYY') as fecha_orden_formateada,
                           COALESCE(COUNT(p.id), 0) as total_producciones,
                           COALESCE(SUM(p.peso_liquido), 0) as total_producido,
                           COALESCE(SUM(p.cantidad), 0) as total_unidades_producidas
                    FROM public.sist_prod_ordenes_produccion_material o
                    LEFT JOIN public.sist_prod_materia_prima mp ON o.id_materia_prima = mp.id
                    LEFT JOIN public.sist_prod_mat p ON o.id = p.id_op
                    WHERE o.activo = true ";

            if (!empty($termino)) {
                $sql .= " AND (o.id::text LIKE :termino OR LOWER(mp.descripcion) LIKE LOWER(:termino))";
            }

            $sql .= " GROUP BY o.id, o.estado, o.unidad_medida, mp.descripcion, o.cantidad_solicitada, o.fecha_orden
                     ORDER BY o.id DESC LIMIT 20";

            $stmt = $this->conexion->prepare($sql);

            if (!empty($termino)) {
                $terminoBusqueda = '%' . $termino . '%';
                $stmt->bindParam(':termino', $terminoBusqueda, PDO::PARAM_STR);
            }

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error buscando órdenes disponibles: " . $e->getMessage());
            return [];
        }
    }
}
