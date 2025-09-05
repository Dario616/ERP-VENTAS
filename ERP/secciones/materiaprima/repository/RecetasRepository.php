<?php

/**
 * Repositorio actualizado para recetas con materias primas extras
 */
class RecetasRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    /**
     * Crear nueva receta (actualizada para manejar materias extras)
     */
    public function crear($datos)
    {
        try {
            $this->conexion->beginTransaction();

            $sql = "INSERT INTO public.sist_prod_recetas 
                (id_tipo_producto, id_materia_prima, cantidad_por_kilo, 
                 usuario_creacion, fecha_creacion, fecha_modificacion, nombre_receta, 
                 version_receta, es_materia_extra, unidad_medida_extra)
                VALUES 
                (:id_tipo_producto, :id_materia_prima, :cantidad_por_kilo, 
                 :usuario_creacion, NOW(), NOW(), :nombre_receta, :version_receta,
                 :es_materia_extra, :unidad_medida_extra)
                RETURNING id";

            $stmt = $this->conexion->prepare($sql);

            // Variables para evitar problemas de referencia
            $nombre_receta = $datos['nombre_receta'] ?? 'Receta Principal';
            $version_receta = $datos['version_receta'] ?? 1;
            $es_extra = $datos['es_materia_extra'] ?? false;
            $unidad_medida_extra = $datos['unidad_medida_extra'] ?? null;

            $stmt->bindParam(':id_tipo_producto', $datos['id_tipo_producto'], PDO::PARAM_INT);
            $stmt->bindParam(':id_materia_prima', $datos['id_materia_prima'], PDO::PARAM_INT);
            $stmt->bindParam(':cantidad_por_kilo', $datos['cantidad_por_kilo'], PDO::PARAM_STR);
            $stmt->bindParam(':usuario_creacion', $datos['usuario_creacion'], PDO::PARAM_STR);
            $stmt->bindParam(':nombre_receta', $nombre_receta, PDO::PARAM_STR);
            $stmt->bindParam(':version_receta', $version_receta, PDO::PARAM_INT);
            $stmt->bindParam(':es_materia_extra', $es_extra, PDO::PARAM_BOOL);
            $stmt->bindParam(':unidad_medida_extra', $unidad_medida_extra, PDO::PARAM_STR);

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $id = $resultado['id'];

            $this->conexion->commit();

            $tipo_materia = $es_extra ? 'EXTRA' : 'PRINCIPAL';
            error_log("📝 Receta creada - ID: $id - Tipo: {$datos['id_tipo_producto']} - Versión: $version_receta - Materia: $tipo_materia");

            return [
                'success' => true,
                'id' => $id,
                'es_materia_extra' => $es_extra,
                'error' => null
            ];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error creando receta: " . $e->getMessage());
            return [
                'success' => false,
                'id' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Crear múltiples recetas con versión específica (actualizada)
     */
    public function crearMultiplesConVersion($id_tipo_producto, $materias_primas, $nombre_receta, $version_receta, $usuario)
    {
        try {
            $this->conexion->beginTransaction();

            $recetas_creadas = [];
            $errores = [];

            $sql = "INSERT INTO public.sist_prod_recetas 
                (id_tipo_producto, id_materia_prima, cantidad_por_kilo, 
                 usuario_creacion, fecha_creacion, fecha_modificacion, nombre_receta, 
                 version_receta, es_materia_extra, unidad_medida_extra)
                VALUES 
                (:id_tipo_producto, :id_materia_prima, :cantidad_por_kilo, 
                 :usuario_creacion, NOW(), NOW(), :nombre_receta, :version_receta,
                 :es_materia_extra, :unidad_medida_extra)
                RETURNING id";

            $stmt = $this->conexion->prepare($sql);

            foreach ($materias_primas as $materia) {
                try {
                    if ($this->existeCombinacionEnVersion($id_tipo_producto, $materia['id_materia_prima'], $version_receta)) {
                        $errores[] = "La materia prima '{$materia['nombre_materia']}' ya está en la versión $version_receta";
                        continue;
                    }

                    // Variables para evitar problemas de referencia
                    $es_extra = $materia['es_materia_extra'] ?? false;
                    $unidad_medida_extra = $materia['unidad_medida_extra'] ?? null;

                    $stmt->bindParam(':id_tipo_producto', $id_tipo_producto, PDO::PARAM_INT);
                    $stmt->bindParam(':id_materia_prima', $materia['id_materia_prima'], PDO::PARAM_INT);
                    $stmt->bindParam(':cantidad_por_kilo', $materia['cantidad_por_kilo'], PDO::PARAM_STR);
                    $stmt->bindParam(':usuario_creacion', $usuario, PDO::PARAM_STR);
                    $stmt->bindParam(':nombre_receta', $nombre_receta, PDO::PARAM_STR);
                    $stmt->bindParam(':version_receta', $version_receta, PDO::PARAM_INT);
                    $stmt->bindParam(':es_materia_extra', $es_extra, PDO::PARAM_BOOL);
                    $stmt->bindParam(':unidad_medida_extra', $unidad_medida_extra, PDO::PARAM_STR);

                    $stmt->execute();
                    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

                    $recetas_creadas[] = [
                        'id' => $resultado['id'],
                        'materia_prima' => $materia['nombre_materia'],
                        'cantidad' => $materia['cantidad_por_kilo'],
                        'version' => $version_receta,
                        'es_extra' => $es_extra,
                        'unidad_medida' => $unidad_medida_extra ?? '%'
                    ];

                    $tipo_materia = $es_extra ? 'EXTRA' : 'PRINCIPAL';
                    error_log("📝 Receta múltiple creada - ID: {$resultado['id']} - Versión: $version_receta - Tipo: $tipo_materia");
                } catch (Exception $e) {
                    $errores[] = "Error con {$materia['nombre_materia']}: " . $e->getMessage();
                }
            }

            if (!empty($recetas_creadas)) {
                $this->conexion->commit();

                return [
                    'success' => true,
                    'recetas_creadas' => $recetas_creadas,
                    'total_creadas' => count($recetas_creadas),
                    'version_creada' => $version_receta,
                    'nombre_receta' => $nombre_receta,
                    'errores' => $errores,
                    'error' => null
                ];
            } else {
                $this->conexion->rollBack();
                return [
                    'success' => false,
                    'recetas_creadas' => [],
                    'total_creadas' => 0,
                    'errores' => $errores,
                    'error' => 'No se pudo crear ninguna receta'
                ];
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error creando recetas múltiples: " . $e->getMessage());
            return [
                'success' => false,
                'recetas_creadas' => [],
                'total_creadas' => 0,
                'errores' => [$e->getMessage()],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar receta existente (actualizada)
     */
    public function actualizar($id, $datos)
    {
        try {
            $this->conexion->beginTransaction();

            $sql = "UPDATE public.sist_prod_recetas 
                SET id_tipo_producto = :id_tipo_producto,
                    id_materia_prima = :id_materia_prima,
                    cantidad_por_kilo = :cantidad_por_kilo,
                    usuario_modificacion = :usuario_modificacion,
                    fecha_modificacion = NOW(),
                    es_materia_extra = :es_materia_extra,
                    unidad_medida_extra = :unidad_medida_extra";

            if (isset($datos['nombre_receta'])) {
                $sql .= ", nombre_receta = :nombre_receta";
            }
            if (isset($datos['version_receta'])) {
                $sql .= ", version_receta = :version_receta";
            }

            $sql .= " WHERE id = :id AND activo = true";

            $stmt = $this->conexion->prepare($sql);

            // Variables para evitar problemas de referencia
            $es_extra = $datos['es_materia_extra'] ?? false;
            $unidad_medida_extra = $datos['unidad_medida_extra'] ?? null;

            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':id_tipo_producto', $datos['id_tipo_producto'], PDO::PARAM_INT);
            $stmt->bindParam(':id_materia_prima', $datos['id_materia_prima'], PDO::PARAM_INT);
            $stmt->bindParam(':cantidad_por_kilo', $datos['cantidad_por_kilo'], PDO::PARAM_STR);
            $stmt->bindParam(':usuario_modificacion', $datos['usuario_modificacion'], PDO::PARAM_STR);
            $stmt->bindParam(':es_materia_extra', $es_extra, PDO::PARAM_BOOL);
            $stmt->bindParam(':unidad_medida_extra', $unidad_medida_extra, PDO::PARAM_STR);

            if (isset($datos['nombre_receta'])) {
                $stmt->bindParam(':nombre_receta', $datos['nombre_receta'], PDO::PARAM_STR);
            }
            if (isset($datos['version_receta'])) {
                $stmt->bindParam(':version_receta', $datos['version_receta'], PDO::PARAM_INT);
            }

            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();
                error_log("🔄 Receta actualizada - ID: $id");
                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo actualizar la receta");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error actualizando receta - ID: $id - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar receta (desactivar)
     */
    public function eliminar($id, $usuario)
    {
        try {
            $this->conexion->beginTransaction();

            $sql = "UPDATE public.sist_prod_recetas 
                SET activo = false,
                    usuario_modificacion = :usuario,
                    fecha_modificacion = NOW()
                WHERE id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();
                error_log("🗑️ Receta desactivada - ID: $id - Usuario: $usuario");
                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo eliminar la receta");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error eliminando receta - ID: $id - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar todas las recetas de una versión específica
     */
    public function eliminarRecetaVersion($id_tipo_producto, $version_receta, $usuario)
    {
        try {
            $this->conexion->beginTransaction();

            $sqlCount = "SELECT COUNT(*) as total FROM public.sist_prod_recetas 
                         WHERE id_tipo_producto = :id_tipo_producto 
                         AND version_receta = :version_receta 
                         AND activo = true";

            $stmtCount = $this->conexion->prepare($sqlCount);
            $stmtCount->bindParam(':id_tipo_producto', $id_tipo_producto, PDO::PARAM_INT);
            $stmtCount->bindParam(':version_receta', $version_receta, PDO::PARAM_INT);
            $stmtCount->execute();
            $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

            if ($total == 0) {
                throw new Exception("No se encontraron recetas activas para la versión $version_receta");
            }

            $sql = "UPDATE public.sist_prod_recetas 
                    SET activo = false,
                        usuario_modificacion = :usuario,
                        fecha_modificacion = NOW()
                    WHERE id_tipo_producto = :id_tipo_producto 
                    AND version_receta = :version_receta
                    AND activo = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_tipo_producto', $id_tipo_producto, PDO::PARAM_INT);
            $stmt->bindParam(':version_receta', $version_receta, PDO::PARAM_INT);
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();
                error_log("🗑️ Versión eliminada - Tipo: $id_tipo_producto - Versión: $version_receta - Registros: {$stmt->rowCount()}");
                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'total_esperado' => $total,
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo eliminar la versión de receta");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error eliminando versión - Tipo: $id_tipo_producto - Versión: $version_receta - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'total_esperado' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar todas las recetas de un tipo de producto
     */
    public function eliminarTodasRecetasDeTipo($id_tipo_producto, $usuario)
    {
        try {
            $this->conexion->beginTransaction();

            $sqlCount = "SELECT COUNT(*) as total FROM public.sist_prod_recetas 
                         WHERE id_tipo_producto = :id_tipo_producto AND activo = true";

            $stmtCount = $this->conexion->prepare($sqlCount);
            $stmtCount->bindParam(':id_tipo_producto', $id_tipo_producto, PDO::PARAM_INT);
            $stmtCount->execute();
            $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

            if ($total == 0) {
                throw new Exception("No hay recetas activas para eliminar en este tipo de producto");
            }

            $sql = "UPDATE public.sist_prod_recetas 
                    SET activo = false,
                        usuario_modificacion = :usuario,
                        fecha_modificacion = NOW()
                    WHERE id_tipo_producto = :id_tipo_producto AND activo = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_tipo_producto', $id_tipo_producto, PDO::PARAM_INT);
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();
                error_log("🗑️ Todas las recetas eliminadas - Tipo: $id_tipo_producto - Cantidad: {$stmt->rowCount()}");
                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'total_esperado' => $total,
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudieron eliminar las recetas");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error eliminando todas las recetas - Tipo: $id_tipo_producto - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'total_esperado' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar si existe una receta
     */
    public function existeReceta($id)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM public.sist_prod_recetas WHERE id = :id AND activo = true";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (Exception $e) {
            error_log("💥 Error verificando existencia de receta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si existe combinación en versión específica
     */
    public function existeCombinacionEnVersion($id_tipo_producto, $id_materia_prima, $version_receta, $excluir_id = null)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM public.sist_prod_recetas 
                    WHERE id_tipo_producto = :id_tipo_producto 
                    AND id_materia_prima = :id_materia_prima 
                    AND version_receta = :version_receta
                    AND activo = true";

            $parametros = [
                ':id_tipo_producto' => $id_tipo_producto,
                ':id_materia_prima' => $id_materia_prima,
                ':version_receta' => $version_receta
            ];

            if ($excluir_id !== null) {
                $sql .= " AND id != :excluir_id";
                $parametros[':excluir_id'] = $excluir_id;
            }

            $stmt = $this->conexion->prepare($sql);
            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (Exception $e) {
            error_log("💥 Error verificando combinación en versión: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si existe una receta con versión específica
     */
    public function existeRecetaVersion($id_tipo_producto, $version_receta)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM public.sist_prod_recetas 
                    WHERE id_tipo_producto = :id_tipo_producto 
                    AND version_receta = :version_receta
                    AND activo = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_tipo_producto', $id_tipo_producto, PDO::PARAM_INT);
            $stmt->bindParam(':version_receta', $version_receta, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (Exception $e) {
            error_log("💥 Error verificando existencia de versión: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si un tipo de producto tiene recetas
     */
    public function tipoTieneRecetas($id_tipo_producto)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM public.sist_prod_recetas 
                    WHERE id_tipo_producto = :id_tipo_producto AND activo = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_tipo_producto', $id_tipo_producto, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (Exception $e) {
            error_log("💥 Error verificando si tipo tiene recetas: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener receta por ID (actualizada)
     */
    public function obtenerPorId($id)
    {
        try {
            $sql = "SELECT r.*, 
                           tp.\"desc\" as tipo_producto_desc,
                           mp.descripcion as materia_prima_desc,
                           TO_CHAR(r.fecha_creacion AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_formateada
                    FROM public.sist_prod_recetas r
                    LEFT JOIN public.sist_ventas_tipoproduc tp ON r.id_tipo_producto = tp.id
                    LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                    WHERE r.id = :id AND r.activo = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ?: null;
        } catch (Exception $e) {
            error_log("💥 Error obteniendo receta por ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener recetas por tipo de producto (actualizada para separar extras)
     */
    public function obtenerPorTipoProducto($id_tipo_producto, $version_receta = null)
    {
        try {
            $sql = "SELECT r.*,
                           mp.descripcion as materia_prima_desc,
                           TO_CHAR(r.fecha_creacion AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_formateada
                    FROM public.sist_prod_recetas r
                    LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                    WHERE r.id_tipo_producto = :id_tipo_producto AND r.activo = true";

            $parametros = [':id_tipo_producto' => $id_tipo_producto];

            if ($version_receta !== null) {
                $sql .= " AND r.version_receta = :version_receta";
                $parametros[':version_receta'] = $version_receta;
            }

            $sql .= " ORDER BY r.version_receta ASC, r.es_materia_extra ASC, mp.descripcion ASC";

            $stmt = $this->conexion->prepare($sql);
            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo recetas por tipo producto: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener versiones por tipo de producto (actualizada para contar solo principales)
     */
    public function obtenerVersionesPorTipo($id_tipo_producto)
    {
        try {
            $sql = "SELECT 
                        version_receta,
                        nombre_receta,
                        COUNT(*) as total_materias,
                        COUNT(CASE WHEN es_materia_extra = false THEN 1 END) as materias_principales,
                        COUNT(CASE WHEN es_materia_extra = true THEN 1 END) as materias_extras,
                        SUM(CASE WHEN es_materia_extra = false THEN cantidad_por_kilo ELSE 0 END) as total_porcentaje,
                        MAX(fecha_modificacion) as fecha_modificacion,
                        MAX(usuario_modificacion) as usuario_modificacion,
                        TO_CHAR(MAX(fecha_modificacion) AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_formateada
                    FROM public.sist_prod_recetas 
                    WHERE id_tipo_producto = :id_tipo_producto 
                    AND activo = true
                    GROUP BY version_receta, nombre_receta
                    ORDER BY version_receta ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_tipo_producto', $id_tipo_producto, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo versiones por tipo: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener última versión por tipo de producto
     */
    public function obtenerUltimaVersionPorTipo($id_tipo_producto)
    {
        try {
            $sql = "SELECT COALESCE(MAX(version_receta), 0) as ultima_version 
                    FROM public.sist_prod_recetas 
                    WHERE id_tipo_producto = :id_tipo_producto 
                    AND activo = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_tipo_producto', $id_tipo_producto, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return intval($resultado['ultima_version']);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo última versión: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener detalle completo de recetas por tipo de producto (actualizada)
     */
    public function obtenerDetalleRecetaPorTipo($id_tipo_producto, $version_receta = null)
    {
        try {
            $sql = "SELECT 
                    tp.id as id_tipo_producto,
                    tp.\"desc\" as tipo_producto_desc,
                    r.id,
                    r.cantidad_por_kilo,
                    r.nombre_receta,
                    r.version_receta,
                    r.es_materia_extra,
                    r.unidad_medida_extra,
                    mp.descripcion,
                    mp.id as id_materia_prima,
                    r.fecha_creacion,
                    r.fecha_modificacion,
                    r.usuario_creacion,
                    r.usuario_modificacion,
                    TO_CHAR(r.fecha_creacion AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_formateada
                FROM public.sist_ventas_tipoproduc tp
                LEFT JOIN public.sist_prod_recetas r ON tp.id = r.id_tipo_producto AND r.activo = true
                LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                WHERE tp.id = :id_tipo_producto";

            $parametros = [':id_tipo_producto' => $id_tipo_producto];

            if ($version_receta !== null) {
                $sql .= " AND r.version_receta = :version_receta";
                $parametros[':version_receta'] = $version_receta;
            }

            $sql .= " ORDER BY r.version_receta ASC, r.es_materia_extra ASC, mp.descripcion ASC";

            $stmt = $this->conexion->prepare($sql);
            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }
            $stmt->execute();

            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($resultado)) {
                return null;
            }

            $detalle = [
                'id_tipo_producto' => $resultado[0]['id_tipo_producto'],
                'tipo_producto' => $resultado[0]['tipo_producto_desc'],
                'versiones' => [],
                'total_versiones' => 0,
                'total_cantidad_general' => 0
            ];

            $versiones = [];
            foreach ($resultado as $fila) {
                if (!is_null($fila['id'])) {
                    $version = intval($fila['version_receta']); // Asegurar que sea entero

                    if (!isset($versiones[$version])) {
                        $versiones[$version] = [
                            'version' => $version,
                            'nombre_receta' => $fila['nombre_receta'] ?? 'Receta Principal',
                            'materias_principales' => [], // SIEMPRE inicializar como array
                            'materias_extras' => [],      // SIEMPRE inicializar como array
                            'total_cantidad' => 0.0,
                            'total_materias_principales' => 0,
                            'total_materias_extras' => 0
                        ];
                    }

                    $materia_info = [
                        'id' => intval($fila['id']),
                        'id_materia_prima' => intval($fila['id_materia_prima']),
                        'descripcion' => $fila['descripcion'] ?? '',
                        'cantidad_por_kilo' => floatval($fila['cantidad_por_kilo']),
                        'fecha_creacion' => $fila['fecha_creacion'],
                        'fecha_modificacion' => $fila['fecha_modificacion'],
                        'fecha_formateada' => $fila['fecha_formateada'] ?? '',
                        'usuario_creacion' => $fila['usuario_creacion'] ?? '',
                        'usuario_modificacion' => $fila['usuario_modificacion'] ?? '',
                        'unidad_medida' => ($fila['es_materia_extra'] === true || $fila['es_materia_extra'] === 't')
                            ? ($fila['unidad_medida_extra'] ?? 'unidades')
                            : '%'
                    ];

                    // VERIFICAR TIPO BOOLEAN CORRECTAMENTE
                    $es_extra = ($fila['es_materia_extra'] === true || $fila['es_materia_extra'] === 't' || $fila['es_materia_extra'] === '1');

                    if ($es_extra) {
                        $versiones[$version]['materias_extras'][] = $materia_info;
                        $versiones[$version]['total_materias_extras']++;
                    } else {
                        $versiones[$version]['materias_principales'][] = $materia_info;
                        $versiones[$version]['total_cantidad'] += floatval($fila['cantidad_por_kilo']);
                        $versiones[$version]['total_materias_principales']++;
                        $detalle['total_cantidad_general'] += floatval($fila['cantidad_por_kilo']);
                    }
                }
            }

            // GARANTIZAR QUE SIEMPRE RETORNEMOS ARRAYS INDEXADOS
            $detalle['versiones'] = array_values($versiones);
            $detalle['total_versiones'] = count($versiones);

            // VERIFICACIÓN FINAL: Asegurar que cada versión tenga arrays
            foreach ($detalle['versiones'] as &$version_check) {
                if (!is_array($version_check['materias_principales'])) {
                    $version_check['materias_principales'] = [];
                }
                if (!is_array($version_check['materias_extras'])) {
                    $version_check['materias_extras'] = [];
                }
            }
            unset($version_check); // Limpiar referencia

            return $detalle;
        } catch (Exception $e) {
            error_log("💥 Error obteniendo detalle de receta por tipo: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener tipos de producto disponibles
     */
    public function obtenerTiposProducto()
    {
        try {
            $sql = 'SELECT id, "desc" FROM public.sist_ventas_tipoproduc ORDER BY "desc" ASC';
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo tipos de producto: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener materias primas disponibles (incluye unidad)
     */
    public function obtenerMateriasPrimas()
    {
        try {
            $sql = "SELECT id, descripcion, unidad FROM public.sist_prod_materia_prima ORDER BY descripcion ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo materias primas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener todas las recetas con paginación (actualizada)
     */
    public function obtenerTodas($limit, $offset, $filtros = [])
    {
        try {
            $condiciones = ['r.activo = true'];
            $parametros = [];

            if (!empty($filtros['buscar_tipo_producto'])) {
                $condiciones[] = "LOWER(tp.\"desc\") LIKE LOWER(:buscar_tipo_producto)";
                $parametros[':buscar_tipo_producto'] = '%' . $filtros['buscar_tipo_producto'] . '%';
            }

            if (!empty($filtros['buscar_materia_prima'])) {
                $condiciones[] = "LOWER(mp.descripcion) LIKE LOWER(:buscar_materia_prima)";
                $parametros[':buscar_materia_prima'] = '%' . $filtros['buscar_materia_prima'] . '%';
            }

            if (!empty($filtros['id_tipo_producto'])) {
                $condiciones[] = "r.id_tipo_producto = :id_tipo_producto";
                $parametros[':id_tipo_producto'] = $filtros['id_tipo_producto'];
            }

            if (!empty($filtros['version_receta'])) {
                $condiciones[] = "r.version_receta = :version_receta";
                $parametros[':version_receta'] = $filtros['version_receta'];
            }

            if (isset($filtros['solo_principales']) && $filtros['solo_principales']) {
                $condiciones[] = "r.es_materia_extra = false";
            }

            if (isset($filtros['solo_extras']) && $filtros['solo_extras']) {
                $condiciones[] = "r.es_materia_extra = true";
            }

            $whereClause = implode(' AND ', $condiciones);

            $sql = "SELECT r.*,
                           tp.\"desc\" as tipo_producto_desc,
                           mp.descripcion as materia_prima_desc,
                           TO_CHAR(r.fecha_creacion AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_formateada
                    FROM public.sist_prod_recetas r
                    LEFT JOIN public.sist_ventas_tipoproduc tp ON r.id_tipo_producto = tp.id
                    LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                    WHERE $whereClause
                    ORDER BY tp.\"desc\" ASC, r.version_receta ASC, r.es_materia_extra ASC, mp.descripcion ASC, r.fecha_creacion DESC 
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
            error_log("💥 Error obteniendo recetas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar total de registros (actualizada)
     */
    public function contarTodas($filtros = [])
    {
        try {
            $condiciones = ['r.activo = true'];
            $parametros = [];

            if (!empty($filtros['buscar_tipo_producto'])) {
                $condiciones[] = "LOWER(tp.\"desc\") LIKE LOWER(:buscar_tipo_producto)";
                $parametros[':buscar_tipo_producto'] = '%' . $filtros['buscar_tipo_producto'] . '%';
            }

            if (!empty($filtros['buscar_materia_prima'])) {
                $condiciones[] = "LOWER(mp.descripcion) LIKE LOWER(:buscar_materia_prima)";
                $parametros[':buscar_materia_prima'] = '%' . $filtros['buscar_materia_prima'] . '%';
            }

            if (!empty($filtros['id_tipo_producto'])) {
                $condiciones[] = "r.id_tipo_producto = :id_tipo_producto";
                $parametros[':id_tipo_producto'] = $filtros['id_tipo_producto'];
            }

            if (!empty($filtros['version_receta'])) {
                $condiciones[] = "r.version_receta = :version_receta";
                $parametros[':version_receta'] = $filtros['version_receta'];
            }

            if (isset($filtros['solo_principales']) && $filtros['solo_principales']) {
                $condiciones[] = "r.es_materia_extra = false";
            }

            if (isset($filtros['solo_extras']) && $filtros['solo_extras']) {
                $condiciones[] = "r.es_materia_extra = true";
            }

            $whereClause = implode(' AND ', $condiciones);

            $sql = "SELECT COUNT(*) as total 
                    FROM public.sist_prod_recetas r
                    LEFT JOIN public.sist_ventas_tipoproduc tp ON r.id_tipo_producto = tp.id
                    LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                    WHERE $whereClause";

            $stmt = $this->conexion->prepare($sql);

            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'];
        } catch (Exception $e) {
            error_log("💥 Error contando recetas: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener tipos de producto con recetas agrupados (actualizada)
     */
    public function obtenerTiposProductoConRecetas($limit, $offset, $filtros = [])
    {
        try {
            $condiciones = ['tp.id IS NOT NULL'];
            $parametros = [];

            if (!empty($filtros['buscar_tipo_producto'])) {
                $condiciones[] = "LOWER(tp.\"desc\") LIKE LOWER(:buscar_tipo_producto)";
                $parametros[':buscar_tipo_producto'] = '%' . $filtros['buscar_tipo_producto'] . '%';
            }

            if (!empty($filtros['id_tipo_producto'])) {
                $condiciones[] = "tp.id = :id_tipo_producto";
                $parametros[':id_tipo_producto'] = $filtros['id_tipo_producto'];
            }

            $whereClause = implode(' AND ', $condiciones);

            $sql = "SELECT 
                        tp.id as id_tipo_producto,
                        tp.\"desc\" as tipo_producto_desc,
                        COALESCE(COUNT(DISTINCT r.version_receta), 0) as total_versiones,
                        COALESCE(COUNT(CASE WHEN r.es_materia_extra = false THEN r.id END), 0) as total_materias_principales,
                        COALESCE(COUNT(CASE WHEN r.es_materia_extra = true THEN r.id END), 0) as total_materias_extras,
                        COALESCE(COUNT(r.id), 0) as total_materias,
                        COALESCE(SUM(CASE WHEN r.es_materia_extra = false THEN r.cantidad_por_kilo ELSE 0 END), 0) as total_cantidad,
                        MAX(r.fecha_modificacion) as fecha_ultima_modificacion,
                        TO_CHAR(MAX(r.fecha_modificacion) AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_formateada,
                        STRING_AGG(DISTINCT CONCAT('V', r.version_receta, ': ', r.nombre_receta), ', ' ORDER BY CONCAT('V', r.version_receta, ': ', r.nombre_receta)) as versiones_nombres
                    FROM public.sist_ventas_tipoproduc tp
                    LEFT JOIN public.sist_prod_recetas r ON tp.id = r.id_tipo_producto AND r.activo = true
                    WHERE $whereClause
                    GROUP BY tp.id, tp.\"desc\"
                    ORDER BY tp.\"desc\" ASC 
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
            error_log("💥 Error obteniendo tipos con recetas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar tipos de producto
     */
    public function contarTiposProducto($filtros = [])
    {
        try {
            $condiciones = ['tp.id IS NOT NULL'];
            $parametros = [];

            if (!empty($filtros['buscar_tipo_producto'])) {
                $condiciones[] = "LOWER(tp.\"desc\") LIKE LOWER(:buscar_tipo_producto)";
                $parametros[':buscar_tipo_producto'] = '%' . $filtros['buscar_tipo_producto'] . '%';
            }

            if (!empty($filtros['id_tipo_producto'])) {
                $condiciones[] = "tp.id = :id_tipo_producto";
                $parametros[':id_tipo_producto'] = $filtros['id_tipo_producto'];
            }

            $whereClause = implode(' AND ', $condiciones);

            $sql = "SELECT COUNT(DISTINCT tp.id) as total 
                    FROM public.sist_ventas_tipoproduc tp
                    WHERE $whereClause";

            $stmt = $this->conexion->prepare($sql);

            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'];
        } catch (Exception $e) {
            error_log("💥 Error contando tipos de producto: " . $e->getMessage());
            return 0;
        }
    }
}
