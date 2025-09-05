<?php

/**
 * Repositorio para recetas de materia prima
 * Extiende la funcionalidad para crear composiciones de materias primas
 */
class RecetasMateriaPrimaRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    /**
     * Crear nueva receta de materia prima
     */
    public function crear($datos)
    {
        try {
            $this->conexion->beginTransaction();

            $sql = "INSERT INTO public.sist_prod_recetas 
                (tipo_receta, id_materia_prima_objetivo, id_materia_prima, cantidad_por_kilo, 
                 usuario_creacion, fecha_creacion, fecha_modificacion, nombre_receta, 
                 version_receta, es_materia_extra, unidad_medida_extra)
                VALUES 
                (:tipo_receta, :id_materia_prima_objetivo, :id_materia_prima, :cantidad_por_kilo, 
                 :usuario_creacion, NOW(), NOW(), :nombre_receta, :version_receta,
                 :es_materia_extra, :unidad_medida_extra)
                RETURNING id";

            $stmt = $this->conexion->prepare($sql);

            // Variables para evitar problemas de referencia
            $tipo_receta = 'MATERIA_PRIMA';
            $nombre_receta = $datos['nombre_receta'] ?? 'Composición Principal';
            $version_receta = $datos['version_receta'] ?? 1;
            $es_extra = $datos['es_materia_extra'] ?? false;
            $unidad_medida_extra = $datos['unidad_medida_extra'] ?? null;

            $stmt->bindParam(':tipo_receta', $tipo_receta, PDO::PARAM_STR);
            $stmt->bindParam(':id_materia_prima_objetivo', $datos['id_materia_prima_objetivo'], PDO::PARAM_INT);
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
            error_log("📝 Composición materia prima creada - ID: $id - Objetivo: {$datos['id_materia_prima_objetivo']} - Versión: $version_receta - Materia: $tipo_materia");

            return [
                'success' => true,
                'id' => $id,
                'es_materia_extra' => $es_extra,
                'error' => null
            ];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error creando composición materia prima: " . $e->getMessage());
            return [
                'success' => false,
                'id' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Crear múltiples recetas con versión específica
     */
    public function crearMultiplesConVersion($id_materia_prima_objetivo, $materias_primas, $nombre_receta, $version_receta, $usuario)
    {
        try {
            $this->conexion->beginTransaction();

            $recetas_creadas = [];
            $errores = [];

            $sql = "INSERT INTO public.sist_prod_recetas 
                (tipo_receta, id_materia_prima_objetivo, id_materia_prima, cantidad_por_kilo, 
                 usuario_creacion, fecha_creacion, fecha_modificacion, nombre_receta, 
                 version_receta, es_materia_extra, unidad_medida_extra)
                VALUES 
                (:tipo_receta, :id_materia_prima_objetivo, :id_materia_prima, :cantidad_por_kilo, 
                 :usuario_creacion, NOW(), NOW(), :nombre_receta, :version_receta,
                 :es_materia_extra, :unidad_medida_extra)
                RETURNING id";

            $stmt = $this->conexion->prepare($sql);
            $tipo_receta = 'MATERIA_PRIMA';

            foreach ($materias_primas as $materia) {
                try {
                    if ($this->existeCombinacionEnVersion($id_materia_prima_objetivo, $materia['id_materia_prima'], $version_receta)) {
                        $errores[] = "La materia prima '{$materia['nombre_materia']}' ya está en la versión $version_receta";
                        continue;
                    }

                    // Variables para evitar problemas de referencia
                    $es_extra = $materia['es_materia_extra'] ?? false;
                    $unidad_medida_extra = $materia['unidad_medida_extra'] ?? null;

                    $stmt->bindParam(':tipo_receta', $tipo_receta, PDO::PARAM_STR);
                    $stmt->bindParam(':id_materia_prima_objetivo', $id_materia_prima_objetivo, PDO::PARAM_INT);
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
                    error_log("📝 Composición múltiple creada - ID: {$resultado['id']} - Versión: $version_receta - Tipo: $tipo_materia");
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
                    'error' => 'No se pudo crear ninguna composición'
                ];
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error creando composiciones múltiples: " . $e->getMessage());
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
     * Actualizar receta existente
     */
    public function actualizar($id, $datos)
    {
        try {
            $this->conexion->beginTransaction();

            $sql = "UPDATE public.sist_prod_recetas 
                SET id_materia_prima_objetivo = :id_materia_prima_objetivo,
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

            $sql .= " WHERE id = :id AND tipo_receta = 'MATERIA_PRIMA' AND activo = true";

            $stmt = $this->conexion->prepare($sql);

            // Variables para evitar problemas de referencia
            $es_extra = $datos['es_materia_extra'] ?? false;
            $unidad_medida_extra = $datos['unidad_medida_extra'] ?? null;

            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':id_materia_prima_objetivo', $datos['id_materia_prima_objetivo'], PDO::PARAM_INT);
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
                error_log("🔄 Composición actualizada - ID: $id");
                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo actualizar la composición");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error actualizando composición - ID: $id - Error: " . $e->getMessage());
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
                WHERE id = :id AND tipo_receta = 'MATERIA_PRIMA'";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();
                error_log("🗑️ Composición desactivada - ID: $id - Usuario: $usuario");
                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo eliminar la composición");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error eliminando composición - ID: $id - Error: " . $e->getMessage());
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
    public function eliminarRecetaVersion($id_materia_prima_objetivo, $version_receta, $usuario)
    {
        try {
            $this->conexion->beginTransaction();

            $sqlCount = "SELECT COUNT(*) as total FROM public.sist_prod_recetas 
                         WHERE tipo_receta = 'MATERIA_PRIMA'
                         AND id_materia_prima_objetivo = :id_materia_prima_objetivo 
                         AND version_receta = :version_receta 
                         AND activo = true";

            $stmtCount = $this->conexion->prepare($sqlCount);
            $stmtCount->bindParam(':id_materia_prima_objetivo', $id_materia_prima_objetivo, PDO::PARAM_INT);
            $stmtCount->bindParam(':version_receta', $version_receta, PDO::PARAM_INT);
            $stmtCount->execute();
            $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

            if ($total == 0) {
                throw new Exception("No se encontraron composiciones activas para la versión $version_receta");
            }

            $sql = "UPDATE public.sist_prod_recetas 
                    SET activo = false,
                        usuario_modificacion = :usuario,
                        fecha_modificacion = NOW()
                    WHERE tipo_receta = 'MATERIA_PRIMA'
                    AND id_materia_prima_objetivo = :id_materia_prima_objetivo 
                    AND version_receta = :version_receta
                    AND activo = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_materia_prima_objetivo', $id_materia_prima_objetivo, PDO::PARAM_INT);
            $stmt->bindParam(':version_receta', $version_receta, PDO::PARAM_INT);
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();
                error_log("🗑️ Versión composición eliminada - Objetivo: $id_materia_prima_objetivo - Versión: $version_receta - Registros: {$stmt->rowCount()}");
                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'total_esperado' => $total,
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo eliminar la versión de composición");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error eliminando versión composición - Objetivo: $id_materia_prima_objetivo - Versión: $version_receta - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'total_esperado' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar todas las recetas de una materia prima
     */
    public function eliminarTodasRecetasDeMateria($id_materia_prima_objetivo, $usuario)
    {
        try {
            $this->conexion->beginTransaction();

            $sqlCount = "SELECT COUNT(*) as total FROM public.sist_prod_recetas 
                         WHERE tipo_receta = 'MATERIA_PRIMA' 
                         AND id_materia_prima_objetivo = :id_materia_prima_objetivo 
                         AND activo = true";

            $stmtCount = $this->conexion->prepare($sqlCount);
            $stmtCount->bindParam(':id_materia_prima_objetivo', $id_materia_prima_objetivo, PDO::PARAM_INT);
            $stmtCount->execute();
            $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

            if ($total == 0) {
                throw new Exception("No hay composiciones activas para eliminar en esta materia prima");
            }

            $sql = "UPDATE public.sist_prod_recetas 
                    SET activo = false,
                        usuario_modificacion = :usuario,
                        fecha_modificacion = NOW()
                    WHERE tipo_receta = 'MATERIA_PRIMA' 
                    AND id_materia_prima_objetivo = :id_materia_prima_objetivo 
                    AND activo = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_materia_prima_objetivo', $id_materia_prima_objetivo, PDO::PARAM_INT);
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();
                error_log("🗑️ Todas las composiciones eliminadas - Objetivo: $id_materia_prima_objetivo - Cantidad: {$stmt->rowCount()}");
                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'total_esperado' => $total,
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudieron eliminar las composiciones");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error eliminando todas las composiciones - Objetivo: $id_materia_prima_objetivo - Error: " . $e->getMessage());
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
            $sql = "SELECT COUNT(*) as total FROM public.sist_prod_recetas 
                    WHERE id = :id AND tipo_receta = 'MATERIA_PRIMA' AND activo = true";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (Exception $e) {
            error_log("💥 Error verificando existencia de composición: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si existe combinación en versión específica
     */
    public function existeCombinacionEnVersion($id_materia_prima_objetivo, $id_materia_prima, $version_receta, $excluir_id = null)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM public.sist_prod_recetas 
                    WHERE tipo_receta = 'MATERIA_PRIMA'
                    AND id_materia_prima_objetivo = :id_materia_prima_objetivo 
                    AND id_materia_prima = :id_materia_prima 
                    AND version_receta = :version_receta
                    AND activo = true";

            $parametros = [
                ':id_materia_prima_objetivo' => $id_materia_prima_objetivo,
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
    public function existeRecetaVersion($id_materia_prima_objetivo, $version_receta)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM public.sist_prod_recetas 
                    WHERE tipo_receta = 'MATERIA_PRIMA'
                    AND id_materia_prima_objetivo = :id_materia_prima_objetivo 
                    AND version_receta = :version_receta
                    AND activo = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_materia_prima_objetivo', $id_materia_prima_objetivo, PDO::PARAM_INT);
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
     * Verificar si una materia prima tiene recetas
     */
    public function materiaTieneRecetas($id_materia_prima_objetivo)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM public.sist_prod_recetas 
                    WHERE tipo_receta = 'MATERIA_PRIMA' 
                    AND id_materia_prima_objetivo = :id_materia_prima_objetivo 
                    AND activo = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_materia_prima_objetivo', $id_materia_prima_objetivo, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (Exception $e) {
            error_log("💥 Error verificando si materia prima tiene composiciones: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener receta por ID
     */
    public function obtenerPorId($id)
    {
        try {
            $sql = "SELECT r.*, 
                           mp_objetivo.descripcion as materia_prima_objetivo_desc,
                           mp.descripcion as materia_prima_desc,
                           TO_CHAR(r.fecha_creacion AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_formateada
                    FROM public.sist_prod_recetas r
                    LEFT JOIN public.sist_prod_materia_prima mp_objetivo ON r.id_materia_prima_objetivo = mp_objetivo.id
                    LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                    WHERE r.id = :id AND r.tipo_receta = 'MATERIA_PRIMA' AND r.activo = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ?: null;
        } catch (Exception $e) {
            error_log("💥 Error obteniendo composición por ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener recetas por materia prima objetivo
     */
    public function obtenerPorMateriaPrima($id_materia_prima_objetivo, $version_receta = null)
    {
        try {
            $sql = "SELECT r.*,
                           mp.descripcion as materia_prima_desc,
                           TO_CHAR(r.fecha_creacion AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_formateada
                    FROM public.sist_prod_recetas r
                    LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                    WHERE r.tipo_receta = 'MATERIA_PRIMA' 
                    AND r.id_materia_prima_objetivo = :id_materia_prima_objetivo 
                    AND r.activo = true";

            $parametros = [':id_materia_prima_objetivo' => $id_materia_prima_objetivo];

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
            error_log("💥 Error obteniendo composiciones por materia prima: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener versiones por materia prima
     */
    public function obtenerVersionesPorMateria($id_materia_prima_objetivo)
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
                    WHERE tipo_receta = 'MATERIA_PRIMA'
                    AND id_materia_prima_objetivo = :id_materia_prima_objetivo 
                    AND activo = true
                    GROUP BY version_receta, nombre_receta
                    ORDER BY version_receta ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_materia_prima_objetivo', $id_materia_prima_objetivo, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo versiones por materia prima: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener última versión por materia prima
     */
    public function obtenerUltimaVersionPorMateria($id_materia_prima_objetivo)
    {
        try {
            $sql = "SELECT COALESCE(MAX(version_receta), 0) as ultima_version 
                    FROM public.sist_prod_recetas 
                    WHERE tipo_receta = 'MATERIA_PRIMA' 
                    AND id_materia_prima_objetivo = :id_materia_prima_objetivo 
                    AND activo = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_materia_prima_objetivo', $id_materia_prima_objetivo, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return intval($resultado['ultima_version']);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo última versión: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener detalle completo de recetas por materia prima
     */
    public function obtenerDetalleRecetaPorMateria($id_materia_prima_objetivo, $version_receta = null)
    {
        try {
            $sql = "SELECT 
                    mp_objetivo.id as id_materia_prima_objetivo,
                    mp_objetivo.descripcion as materia_prima_objetivo_desc,
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
                FROM public.sist_prod_materia_prima mp_objetivo
                LEFT JOIN public.sist_prod_recetas r ON mp_objetivo.id = r.id_materia_prima_objetivo 
                      AND r.tipo_receta = 'MATERIA_PRIMA' AND r.activo = true
                LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                WHERE mp_objetivo.id = :id_materia_prima_objetivo";

            $parametros = [':id_materia_prima_objetivo' => $id_materia_prima_objetivo];

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
                'id_materia_prima_objetivo' => $resultado[0]['id_materia_prima_objetivo'],
                'materia_prima_objetivo' => $resultado[0]['materia_prima_objetivo_desc'],
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
                            'nombre_receta' => $fila['nombre_receta'] ?? 'Composición Principal',
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
                        'descripcion_receta' => $fila['descripcion_receta'] ?? '',
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
            error_log("💥 Error obteniendo detalle de composición por materia prima: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener materias primas disponibles
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
     * Buscar materias primas disponibles (incluye unidad)
     */
    public function buscarMateriasDisponibles($termino = '', $excluir_id = null)
    {
        try {
            $sql = "SELECT id, descripcion, unidad FROM public.sist_prod_materia_prima WHERE 1=1";
            $parametros = [];

            if (!empty($termino)) {
                $sql .= " AND LOWER(descripcion) LIKE LOWER(:termino)";
                $parametros[':termino'] = '%' . $termino . '%';
            }

            if ($excluir_id !== null) {
                $sql .= " AND id != :excluir_id";
                $parametros[':excluir_id'] = $excluir_id;
            }

            $sql .= " ORDER BY descripcion ASC LIMIT 50";

            $stmt = $this->conexion->prepare($sql);
            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error buscando materias primas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener todas las recetas con paginación
     */
    public function obtenerTodas($limit, $offset, $filtros = [])
    {
        try {
            $condiciones = ['r.tipo_receta = :tipo_receta', 'r.activo = true'];
            $parametros = [':tipo_receta' => 'MATERIA_PRIMA'];

            if (!empty($filtros['buscar_materia_objetivo'])) {
                $condiciones[] = "LOWER(mp_objetivo.descripcion) LIKE LOWER(:buscar_materia_objetivo)";
                $parametros[':buscar_materia_objetivo'] = '%' . $filtros['buscar_materia_objetivo'] . '%';
            }

            if (!empty($filtros['buscar_materia_componente'])) {
                $condiciones[] = "LOWER(mp.descripcion) LIKE LOWER(:buscar_materia_componente)";
                $parametros[':buscar_materia_componente'] = '%' . $filtros['buscar_materia_componente'] . '%';
            }

            if (!empty($filtros['id_materia_prima_objetivo'])) {
                $condiciones[] = "r.id_materia_prima_objetivo = :id_materia_prima_objetivo";
                $parametros[':id_materia_prima_objetivo'] = $filtros['id_materia_prima_objetivo'];
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
                           mp_objetivo.descripcion as materia_prima_objetivo_desc,
                           mp.descripcion as materia_prima_desc,
                           TO_CHAR(r.fecha_creacion AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_formateada
                    FROM public.sist_prod_recetas r
                    LEFT JOIN public.sist_prod_materia_prima mp_objetivo ON r.id_materia_prima_objetivo = mp_objetivo.id
                    LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                    WHERE $whereClause
                    ORDER BY mp_objetivo.descripcion ASC, r.version_receta ASC, r.es_materia_extra ASC, mp.descripcion ASC, r.fecha_creacion DESC 
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
            error_log("💥 Error obteniendo composiciones: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar total de registros
     */
    public function contarTodas($filtros = [])
    {
        try {
            $condiciones = ['r.tipo_receta = :tipo_receta', 'r.activo = true'];
            $parametros = [':tipo_receta' => 'MATERIA_PRIMA'];

            if (!empty($filtros['buscar_materia_objetivo'])) {
                $condiciones[] = "LOWER(mp_objetivo.descripcion) LIKE LOWER(:buscar_materia_objetivo)";
                $parametros[':buscar_materia_objetivo'] = '%' . $filtros['buscar_materia_objetivo'] . '%';
            }

            if (!empty($filtros['buscar_materia_componente'])) {
                $condiciones[] = "LOWER(mp.descripcion) LIKE LOWER(:buscar_materia_componente)";
                $parametros[':buscar_materia_componente'] = '%' . $filtros['buscar_materia_componente'] . '%';
            }

            if (!empty($filtros['id_materia_prima_objetivo'])) {
                $condiciones[] = "r.id_materia_prima_objetivo = :id_materia_prima_objetivo";
                $parametros[':id_materia_prima_objetivo'] = $filtros['id_materia_prima_objetivo'];
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
                    LEFT JOIN public.sist_prod_materia_prima mp_objetivo ON r.id_materia_prima_objetivo = mp_objetivo.id
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
            error_log("💥 Error contando composiciones: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener materias primas con recetas agrupados
     */
    public function obtenerMateriasPrimasConRecetas($limit, $offset, $filtros = [])
    {
        try {
            // Agregar condición para produccion = 1
            $condiciones = ['mp.id IS NOT NULL', 'mp.produccion = 1'];
            $parametros = [];

            if (!empty($filtros['buscar_materia_objetivo'])) {
                $condiciones[] = "LOWER(mp.descripcion) LIKE LOWER(:buscar_materia_objetivo)";
                $parametros[':buscar_materia_objetivo'] = '%' . $filtros['buscar_materia_objetivo'] . '%';
            }

            if (!empty($filtros['id_materia_prima_objetivo'])) {
                $condiciones[] = "mp.id = :id_materia_prima_objetivo";
                $parametros[':id_materia_prima_objetivo'] = $filtros['id_materia_prima_objetivo'];
            }

            $whereClause = implode(' AND ', $condiciones);

            $sql = "SELECT 
                    mp.id as id_materia_prima_objetivo,
                    mp.descripcion as materia_prima_objetivo_desc,
                    COALESCE(COUNT(DISTINCT r.version_receta), 0) as total_versiones,
                    COALESCE(COUNT(CASE WHEN r.es_materia_extra = false THEN r.id END), 0) as total_materias_principales,
                    COALESCE(COUNT(CASE WHEN r.es_materia_extra = true THEN r.id END), 0) as total_materias_extras,
                    COALESCE(COUNT(r.id), 0) as total_materias,
                    COALESCE(SUM(CASE WHEN r.es_materia_extra = false THEN r.cantidad_por_kilo ELSE 0 END), 0) as total_cantidad,
                    MAX(r.fecha_modificacion) as fecha_ultima_modificacion,
                    TO_CHAR(MAX(r.fecha_modificacion) AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_formateada,
                    STRING_AGG(DISTINCT CONCAT('V', r.version_receta, ': ', r.nombre_receta), ', ' ORDER BY CONCAT('V', r.version_receta, ': ', r.nombre_receta)) as versiones_nombres
                FROM public.sist_prod_materia_prima mp
                LEFT JOIN public.sist_prod_recetas r ON mp.id = r.id_materia_prima_objetivo 
                      AND r.tipo_receta = 'MATERIA_PRIMA' AND r.activo = true
                WHERE $whereClause
                GROUP BY mp.id, mp.descripcion
                ORDER BY mp.descripcion ASC 
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
            error_log("💥 Error obteniendo materias primas con composiciones: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar materias primas
     */
    public function contarMateriasPrimas($filtros = [])
    {
        try {
            // Agregar condición para produccion = 1
            $condiciones = ['mp.id IS NOT NULL', 'mp.produccion = 1'];
            $parametros = [];

            if (!empty($filtros['buscar_materia_objetivo'])) {
                $condiciones[] = "LOWER(mp.descripcion) LIKE LOWER(:buscar_materia_objetivo)";
                $parametros[':buscar_materia_objetivo'] = '%' . $filtros['buscar_materia_objetivo'] . '%';
            }

            if (!empty($filtros['id_materia_prima_objetivo'])) {
                $condiciones[] = "mp.id = :id_materia_prima_objetivo";
                $parametros[':id_materia_prima_objetivo'] = $filtros['id_materia_prima_objetivo'];
            }

            $whereClause = implode(' AND ', $condiciones);

            $sql = "SELECT COUNT(DISTINCT mp.id) as total 
                FROM public.sist_prod_materia_prima mp
                WHERE $whereClause";

            $stmt = $this->conexion->prepare($sql);

            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'];
        } catch (Exception $e) {
            error_log("💥 Error contando materias primas: " . $e->getMessage());
            return 0;
        }
    }
}
