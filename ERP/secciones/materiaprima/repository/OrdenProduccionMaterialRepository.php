<?php

/**
 * Repositorio para órdenes de producción de materiales
 */
class OrdenProduccionMaterialRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    /**
     * Crear nueva orden de producción con validación de stock
     */
    public function crear($datos)
    {
        try {
            $this->conexion->beginTransaction();
            $sql = "INSERT INTO public.sist_prod_ordenes_produccion_material 
                (id_materia_prima, version_receta, cantidad_solicitada, unidad_medida, 
                 fecha_orden, estado, usuario_creacion, fecha_creacion, fecha_modificacion, observaciones)
                VALUES 
                (:id_materia_prima, :version_receta, :cantidad_solicitada, :unidad_medida,
                 :fecha_orden, :estado, :usuario_creacion, NOW(), NOW(), :observaciones)
                RETURNING id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_materia_prima', $datos['id_materia_prima'], PDO::PARAM_INT);
            $stmt->bindParam(':version_receta', $datos['version_receta'], PDO::PARAM_INT);
            $stmt->bindParam(':cantidad_solicitada', $datos['cantidad_solicitada'], PDO::PARAM_STR);
            $stmt->bindParam(':unidad_medida', $datos['unidad_medida'], PDO::PARAM_STR);
            $stmt->bindParam(':fecha_orden', $datos['fecha_orden'], PDO::PARAM_STR);
            $stmt->bindParam(':estado', $datos['estado'], PDO::PARAM_STR);
            $stmt->bindParam(':usuario_creacion', $datos['usuario_creacion'], PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $datos['observaciones'], PDO::PARAM_STR);

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $id = $resultado['id'];

            $this->conexion->commit();

            error_log("📝 Orden de producción material creada - ID: $id - Materia: {$datos['id_materia_prima']} - Stock validado ✅");

            return [
                'success' => true,
                'id' => $id,
                'error' => null
            ];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error creando orden producción material: " . $e->getMessage());
            return [
                'success' => false,
                'id' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar orden existente con validación de stock si cambia la cantidad
     */
    public function actualizar($id, $datos)
    {
        try {
            // Obtener datos actuales para comparar
            $ordenActual = $this->obtenerPorId($id);
            if (!$ordenActual) {
                return [
                    'success' => false,
                    'registros_afectados' => 0,
                    'error' => 'Orden no encontrada'
                ];
            }
            $this->conexion->beginTransaction();

            $sql = "UPDATE public.sist_prod_ordenes_produccion_material 
                SET id_materia_prima = :id_materia_prima,
                    version_receta = :version_receta,
                    cantidad_solicitada = :cantidad_solicitada,
                    unidad_medida = :unidad_medida,
                    fecha_orden = :fecha_orden,
                    estado = :estado,
                    observaciones = :observaciones,
                    fecha_modificacion = NOW()
                WHERE id = :id AND activo = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':id_materia_prima', $datos['id_materia_prima'], PDO::PARAM_INT);
            $stmt->bindParam(':version_receta', $datos['version_receta'], PDO::PARAM_INT);
            $stmt->bindParam(':cantidad_solicitada', $datos['cantidad_solicitada'], PDO::PARAM_STR);
            $stmt->bindParam(':unidad_medida', $datos['unidad_medida'], PDO::PARAM_STR);
            $stmt->bindParam(':fecha_orden', $datos['fecha_orden'], PDO::PARAM_STR);
            $stmt->bindParam(':estado', $datos['estado'], PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $datos['observaciones'], PDO::PARAM_STR);

            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();
                error_log("🔄 Orden producción material actualizada - ID: $id - Stock validado ✅");
                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo actualizar la orden de producción");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error actualizando orden producción material - ID: $id - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * NUEVA FUNCIÓN: Validar stock disponible para producción
     */
    public function validarStockParaProduccion($idMateriaPrima, $versionReceta, $cantidadSolicitada)
    {
        try {
            // Buscar recetas para validar stock
            $sqlRecetas = "SELECT r.id_materia_prima, r.cantidad_por_kilo, mp.descripcion, mp.peso_estimado
                          FROM public.sist_prod_recetas r
                          LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                          WHERE r.tipo_receta = 'MATERIA_PRIMA' 
                          AND r.id_materia_prima_objetivo = :id_materia_prima
                          AND r.version_receta = :version_receta
                          AND r.activo = true";

            $stmtRecetas = $this->conexion->prepare($sqlRecetas);
            $stmtRecetas->bindParam(':id_materia_prima', $idMateriaPrima, PDO::PARAM_INT);
            $stmtRecetas->bindParam(':version_receta', $versionReceta, PDO::PARAM_INT);
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
                $cantidadRequerida = $porcentajeDecimal * floatval($cantidadSolicitada);
                $stockDisponible = floatval($receta['peso_estimado'] ?? 0);

                if ($cantidadRequerida > $stockDisponible) {
                    $materialesInsuficientes[] = [
                        'material' => $receta['descripcion'],
                        'requerido' => round($cantidadRequerida, 3),
                        'disponible' => round($stockDisponible, 3),
                        'faltante' => round($cantidadRequerida - $stockDisponible, 3)
                    ];
                }
            }

            if (!empty($materialesInsuficientes)) {
                $mensaje = "Stock insuficiente para producir {$cantidadSolicitada} kg:";
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
     * NUEVA FUNCIÓN: Verificar stock disponible sin crear orden (para preview)
     */
    public function verificarStockDisponible($idMateriaPrima, $versionReceta, $cantidadSolicitada)
    {
        return $this->validarStockParaProduccion($idMateriaPrima, $versionReceta, $cantidadSolicitada);
    }

    /**
     * NUEVA FUNCIÓN: Obtener detalle de materiales requeridos para una cantidad
     */
    public function obtenerMaterialesRequeridos($idMateriaPrima, $versionReceta, $cantidadSolicitada)
    {
        try {
            $sqlRecetas = "SELECT r.id_materia_prima, r.cantidad_por_kilo, mp.descripcion, mp.peso_estimado, mp.unidad
                          FROM public.sist_prod_recetas r
                          LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                          WHERE r.tipo_receta = 'MATERIA_PRIMA' 
                          AND r.id_materia_prima_objetivo = :id_materia_prima
                          AND r.version_receta = :version_receta
                          AND r.activo = true
                          ORDER BY mp.descripcion ASC";

            $stmtRecetas = $this->conexion->prepare($sqlRecetas);
            $stmtRecetas->bindParam(':id_materia_prima', $idMateriaPrima, PDO::PARAM_INT);
            $stmtRecetas->bindParam(':version_receta', $versionReceta, PDO::PARAM_INT);
            $stmtRecetas->execute();
            $recetas = $stmtRecetas->fetchAll(PDO::FETCH_ASSOC);

            $materialesRequeridos = [];

            foreach ($recetas as $receta) {
                $porcentajeDecimal = floatval($receta['cantidad_por_kilo']) / 100;
                $cantidadRequerida = $porcentajeDecimal * floatval($cantidadSolicitada);
                $stockDisponible = floatval($receta['peso_estimado'] ?? 0);

                $materialesRequeridos[] = [
                    'id_materia_prima' => $receta['id_materia_prima'],
                    'descripcion' => $receta['descripcion'],
                    'porcentaje' => $receta['cantidad_por_kilo'],
                    'cantidad_requerida' => round($cantidadRequerida, 3),
                    'stock_disponible' => round($stockDisponible, 3),
                    'unidad_medida' => $receta['unidad_medida'],
                    'suficiente' => $cantidadRequerida <= $stockDisponible,
                    'faltante' => $cantidadRequerida > $stockDisponible ? round($cantidadRequerida - $stockDisponible, 3) : 0
                ];
            }

            return $materialesRequeridos;
        } catch (Exception $e) {
            error_log("💥 Error obteniendo materiales requeridos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cambiar estado de una orden
     */
    public function cambiarEstado($id, $nuevoEstado, $usuario)
    {
        try {
            $this->conexion->beginTransaction();

            $sql = "UPDATE public.sist_prod_ordenes_produccion_material 
                SET estado = :estado,
                    fecha_modificacion = NOW()
                WHERE id = :id AND activo = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':estado', $nuevoEstado, PDO::PARAM_STR);

            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();
                error_log("🔄 Estado orden producción material cambiado - ID: $id - Estado: $nuevoEstado");
                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo cambiar el estado de la orden");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error cambiando estado orden - ID: $id - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar orden (desactivar)
     */
    public function eliminar($id, $usuario)
    {
        try {
            $this->conexion->beginTransaction();

            $sql = "UPDATE public.sist_prod_ordenes_produccion_material 
                SET activo = false,
                    fecha_modificacion = NOW()
                WHERE id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();
                error_log("🗑️ Orden producción material eliminada - ID: $id - Usuario: $usuario");
                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo eliminar la orden de producción");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error eliminando orden producción material - ID: $id - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar si existe una orden
     */
    public function existeOrden($id)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM public.sist_prod_ordenes_produccion_material 
                    WHERE id = :id AND activo = true";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (Exception $e) {
            error_log("💥 Error verificando existencia de orden: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener orden por ID con detalles completos
     */
    public function obtenerPorId($id)
    {
        try {
            $sql = "SELECT o.*,
                           mp.descripcion as materia_prima_desc,
                           TO_CHAR(o.fecha_orden, 'DD/MM/YYYY') as fecha_orden_formateada,
                           TO_CHAR(o.fecha_creacion AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_creacion_formateada,
                           TO_CHAR(o.fecha_modificacion AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_modificacion_formateada
                    FROM public.sist_prod_ordenes_produccion_material o
                    LEFT JOIN public.sist_prod_materia_prima mp ON o.id_materia_prima = mp.id
                    WHERE o.id = :id AND o.activo = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ?: null;
        } catch (Exception $e) {
            error_log("💥 Error obteniendo orden por ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener orden por ID con datos para PDF
     */
    public function obtenerParaPDF($id)
    {
        try {
            $sql = "SELECT o.*,
                           mp.descripcion as materia_prima_desc,
                           TO_CHAR(o.fecha_orden, 'DD/MM/YYYY') as fecha_orden_formateada
                    FROM public.sist_prod_ordenes_produccion_material o
                    LEFT JOIN public.sist_prod_materia_prima mp ON o.id_materia_prima = mp.id
                    WHERE o.id = :id AND o.activo = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $orden = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$orden) {
                return null;
            }

            // Obtener componentes de la receta
            $sql_componentes = "SELECT r.*,
                                       mp.descripcion as componente_desc
                                FROM public.sist_prod_recetas r
                                LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                                WHERE r.tipo_receta = 'MATERIA_PRIMA' 
                                AND r.id_materia_prima_objetivo = :id_materia_prima 
                                AND r.version_receta = :version_receta
                                AND r.activo = true
                                ORDER BY r.es_materia_extra ASC, mp.descripcion ASC";

            $stmt_componentes = $this->conexion->prepare($sql_componentes);
            $stmt_componentes->bindParam(':id_materia_prima', $orden['id_materia_prima'], PDO::PARAM_INT);
            $stmt_componentes->bindParam(':version_receta', $orden['version_receta'], PDO::PARAM_INT);
            $stmt_componentes->execute();

            $componentes = $stmt_componentes->fetchAll(PDO::FETCH_ASSOC);
            $orden['componentes'] = $componentes;

            return $orden;
        } catch (Exception $e) {
            error_log("💥 Error obteniendo orden para PDF: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener todas las órdenes con filtros y paginación
     */
    public function obtenerTodas($limit, $offset, $filtros = [])
    {
        try {
            $condiciones = ['o.activo = true'];
            $parametros = [];

            if (!empty($filtros['buscar_materia'])) {
                $condiciones[] = "LOWER(mp.descripcion) LIKE LOWER(:buscar_materia)";
                $parametros[':buscar_materia'] = '%' . $filtros['buscar_materia'] . '%';
            }

            if (!empty($filtros['id_materia_prima'])) {
                $condiciones[] = "o.id_materia_prima = :id_materia_prima";
                $parametros[':id_materia_prima'] = $filtros['id_materia_prima'];
            }

            if (!empty($filtros['estado'])) {
                $condiciones[] = "o.estado = :estado";
                $parametros[':estado'] = $filtros['estado'];
            }

            if (!empty($filtros['fecha_desde'])) {
                $condiciones[] = "o.fecha_orden >= :fecha_desde";
                $parametros[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $condiciones[] = "o.fecha_orden <= :fecha_hasta";
                $parametros[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            $whereClause = implode(' AND ', $condiciones);

            $sql = "SELECT o.*,
                           mp.descripcion as materia_prima_desc,
                           TO_CHAR(o.fecha_orden, 'DD/MM/YYYY') as fecha_orden_formateada,
                           TO_CHAR(o.fecha_creacion AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_creacion_formateada
                    FROM public.sist_prod_ordenes_produccion_material o
                    LEFT JOIN public.sist_prod_materia_prima mp ON o.id_materia_prima = mp.id
                    WHERE $whereClause
                    ORDER BY o.fecha_orden DESC, o.fecha_creacion DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);

            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }

            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo órdenes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar total de registros
     */
    public function contarTodas($filtros = [])
    {
        try {
            $condiciones = ['o.activo = true'];
            $parametros = [];

            if (!empty($filtros['buscar_materia'])) {
                $condiciones[] = "LOWER(mp.descripcion) LIKE LOWER(:buscar_materia)";
                $parametros[':buscar_materia'] = '%' . $filtros['buscar_materia'] . '%';
            }

            if (!empty($filtros['id_materia_prima'])) {
                $condiciones[] = "o.id_materia_prima = :id_materia_prima";
                $parametros[':id_materia_prima'] = $filtros['id_materia_prima'];
            }

            if (!empty($filtros['estado'])) {
                $condiciones[] = "o.estado = :estado";
                $parametros[':estado'] = $filtros['estado'];
            }

            if (!empty($filtros['fecha_desde'])) {
                $condiciones[] = "o.fecha_orden >= :fecha_desde";
                $parametros[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $condiciones[] = "o.fecha_orden <= :fecha_hasta";
                $parametros[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            $whereClause = implode(' AND ', $condiciones);

            $sql = "SELECT COUNT(*) as total 
                    FROM public.sist_prod_ordenes_produccion_material o
                    LEFT JOIN public.sist_prod_materia_prima mp ON o.id_materia_prima = mp.id
                    WHERE $whereClause";

            $stmt = $this->conexion->prepare($sql);

            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'];
        } catch (Exception $e) {
            error_log("💥 Error contando órdenes: " . $e->getMessage());
            return 0;
        }
    }

    public function obtenerMateriasPrimasConRecetas()
    {
        try {
            $sql = "SELECT DISTINCT mp.id, mp.descripcion, mp.unidad as unidad
                FROM public.sist_prod_materia_prima mp
                INNER JOIN public.sist_prod_recetas r ON mp.id = r.id_materia_prima_objetivo
                WHERE r.tipo_receta = 'MATERIA_PRIMA' AND r.activo = true
                ORDER BY mp.descripcion ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo materias primas con recetas: " . $e->getMessage());
            return [];
        }
    }
    /**
     * Obtener versiones de receta disponibles para una materia prima
     */
    public function obtenerVersionesReceta($idMateriaPrima)
    {
        try {
            $sql = "SELECT DISTINCT version_receta, nombre_receta,
                           COUNT(*) as total_componentes,
                           SUM(CASE WHEN es_materia_extra = false THEN cantidad_por_kilo ELSE 0 END) as total_porcentaje
                    FROM public.sist_prod_recetas 
                    WHERE tipo_receta = 'MATERIA_PRIMA' 
                    AND id_materia_prima_objetivo = :id_materia_prima 
                    AND activo = true
                    GROUP BY version_receta, nombre_receta
                    ORDER BY version_receta ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_materia_prima', $idMateriaPrima, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo versiones de receta: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener componentes de una receta específica
     */
    public function obtenerComponentesReceta($idMateriaPrima, $versionReceta)
    {
        try {
            $sql = "SELECT r.*,
                           mp.descripcion as componente_desc
                    FROM public.sist_prod_recetas r
                    LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                    WHERE r.tipo_receta = 'MATERIA_PRIMA' 
                    AND r.id_materia_prima_objetivo = :id_materia_prima 
                    AND r.version_receta = :version_receta
                    AND r.activo = true
                    ORDER BY r.es_materia_extra ASC, mp.descripcion ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_materia_prima', $idMateriaPrima, PDO::PARAM_INT);
            $stmt->bindParam(':version_receta', $versionReceta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo componentes de receta: " . $e->getMessage());
            return [];
        }
    }
}
