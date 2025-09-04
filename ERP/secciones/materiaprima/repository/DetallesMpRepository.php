<?php

/**
 * Repositorio para manejo de datos de detalles de materia prima
 * ACTUALIZADO: Con manejo del campo cantidad para unidad = "Unidad"
 */
class DetallesMpRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    /**
     * Buscar detalle por código de barras con descripción de materia prima - ACTUALIZADO
     */
    public function buscarPorCodigoBarras($barcode)
    {
        try {
            $sql = "SELECT d.*, mp.descripcion as descripcion_materia
                    FROM public.sist_prod_detalles_mp d
                    INNER JOIN public.sist_prod_materia_prima mp ON d.id_materia = mp.id
                    WHERE LOWER(TRIM(d.barcode)) = LOWER(TRIM(:barcode)) 
                    ORDER BY d.id DESC 
                    LIMIT 1";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':barcode', $barcode, PDO::PARAM_STR);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado) {
                error_log("📋 Código de barras encontrado en BD: $barcode - ID: {$resultado['id']}");
                return [
                    'success' => true,
                    'datos' => $resultado,
                    'error' => null
                ];
            } else {
                error_log("📋 Código de barras NO encontrado en BD: $barcode");
                return [
                    'success' => false,
                    'datos' => null,
                    'error' => 'Código de barras no encontrado'
                ];
            }
        } catch (Exception $e) {
            error_log("💥 Error buscando código de barras en BD: $barcode - Error: " . $e->getMessage());
            return [
                'success' => false,
                'datos' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar si existe un código de barras (para evitar duplicados)
     */
    public function existeCodigoBarras($barcode, $excluir_id = null)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM public.sist_prod_detalles_mp 
                    WHERE LOWER(TRIM(barcode)) = LOWER(TRIM(:barcode))";

            $parametros = [':barcode' => $barcode];

            // Si se proporciona un ID para excluir (útil en ediciones)
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
            error_log("💥 Error verificando existencia de código de barras: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener datos agrupados por proveedor para una materia prima
     */
    public function obtenerAgrupadosPorProveedor($id_materia, $limit, $offset, $filtros = [])
    {
        try {
            $condiciones = ['d.id_materia = :id_materia'];
            $parametros = [':id_materia' => $id_materia];

            // Aplicar filtros
            if (!empty($filtros['buscar_proveedor'])) {
                $condiciones[] = "LOWER(COALESCE(d.proveedor, 'Sin Proveedor')) LIKE LOWER(:buscar_proveedor)";
                $parametros[':buscar_proveedor'] = '%' . $filtros['buscar_proveedor'] . '%';
            }

            if (!empty($filtros['buscar_codigo'])) {
                $condiciones[] = "d.id IN (
                SELECT DISTINCT id_materia FROM public.sist_prod_detalles_mp 
                WHERE LOWER(codigo_unico) LIKE LOWER(:buscar_codigo)
            )";
                $parametros[':buscar_codigo'] = '%' . $filtros['buscar_codigo'] . '%';
            }

            $whereClause = implode(' AND ', $condiciones);

            // ACTUALIZADO: Incluir suma de cantidad
            $sql = "SELECT 
                    COALESCE(d.proveedor, 'Sin Proveedor') as proveedor_agrupado,
                    COUNT(*) as total_detalles,
                    SUM(COALESCE(d.cantidad, 0)) as total_cantidad,
                    MAX(d.fecha) as fecha_ultimo_registro,
                    TO_CHAR(MAX(d.fecha) AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_ultimo_registro_formateada,
                    mp.descripcion as descripcion_materia,
                    mp.unidad as unidad_materia,
                    MIN(d.id) as id_ejemplo
                FROM public.sist_prod_detalles_mp d
                INNER JOIN public.sist_prod_materia_prima mp ON d.id_materia = mp.id
                WHERE $whereClause
                GROUP BY COALESCE(d.proveedor, 'Sin Proveedor'), mp.descripcion, mp.unidad
                ORDER BY 
                    CASE WHEN COALESCE(d.proveedor, 'Sin Proveedor') = 'Sin Proveedor' THEN 1 ELSE 0 END,
                    total_detalles DESC, 
                    fecha_ultimo_registro DESC
                LIMIT :limit OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);

            // Bind parámetros de filtros
            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }

            // Bind parámetros de paginación
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatear datos adicionales
            foreach ($resultados as &$registro) {
                if (empty($registro['fecha_ultimo_registro_formateada'])) {
                    $registro['fecha_ultimo_registro_formateada'] = 'N/A';
                }
                $registro['es_sin_proveedor'] = $registro['proveedor_agrupado'] === 'Sin Proveedor';

                // NUEVO: Formatear total cantidad
                $registro['total_cantidad'] = intval($registro['total_cantidad'] ?? 0);
                $registro['mostrar_cantidad'] = isset($registro['unidad_materia']) &&
                    strtolower($registro['unidad_materia']) === 'unidad';
            }

            return $resultados;
        } catch (Exception $e) {
            error_log("💥 Error obteniendo datos agrupados por proveedor: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar total de proveedores agrupados
     */
    public function contarProveedoresAgrupados($id_materia, $filtros = [])
    {
        try {
            $condiciones = ['d.id_materia = :id_materia'];
            $parametros = [':id_materia' => $id_materia];

            // Aplicar filtros
            if (!empty($filtros['buscar_proveedor'])) {
                $condiciones[] = "LOWER(COALESCE(d.proveedor, 'Sin Proveedor')) LIKE LOWER(:buscar_proveedor)";
                $parametros[':buscar_proveedor'] = '%' . $filtros['buscar_proveedor'] . '%';
            }

            if (!empty($filtros['buscar_codigo'])) {
                $condiciones[] = "d.id IN (
                    SELECT DISTINCT id_materia FROM public.sist_prod_detalles_mp 
                    WHERE LOWER(codigo_unico) LIKE LOWER(:buscar_codigo)
                )";
                $parametros[':buscar_codigo'] = '%' . $filtros['buscar_codigo'] . '%';
            }

            $whereClause = implode(' AND ', $condiciones);

            $sql = "SELECT COUNT(DISTINCT COALESCE(d.proveedor, 'Sin Proveedor')) as total 
                    FROM public.sist_prod_detalles_mp d
                    INNER JOIN public.sist_prod_materia_prima mp ON d.id_materia = mp.id
                    WHERE $whereClause";

            $stmt = $this->conexion->prepare($sql);

            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'];
        } catch (Exception $e) {
            error_log("💥 Error contando proveedores agrupados: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener detalles individuales de un proveedor específico
     */
    public function obtenerDetallesPorProveedor($id_materia, $proveedor, $limit = null, $offset = null)
    {
        try {
            $condiciones = ['d.id_materia = :id_materia'];
            $parametros = [':id_materia' => $id_materia];

            // Manejar el caso de "Sin Proveedor"
            if ($proveedor === 'Sin Proveedor') {
                $condiciones[] = "(d.proveedor IS NULL OR TRIM(d.proveedor) = '')";
            } else {
                $condiciones[] = "d.proveedor = :proveedor";
                $parametros[':proveedor'] = $proveedor;
            }

            $whereClause = implode(' AND ', $condiciones);

            $limitClause = '';
            if ($limit !== null) {
                $limitClause = "LIMIT :limit";
                if ($offset !== null) {
                    $limitClause .= " OFFSET :offset";
                }
            }

            $sql = "SELECT d.*, 
                       mp.descripcion as descripcion_materia,
                       mp.unidad as unidad_materia,
                       TO_CHAR(d.fecha AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY') as fecha_solo,
                       TO_CHAR(d.fecha AT TIME ZONE 'America/Asuncion', 'HH24:MI') as hora_solo,
                       TO_CHAR(d.fecha AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_formateada
                FROM public.sist_prod_detalles_mp d
                INNER JOIN public.sist_prod_materia_prima mp ON d.id_materia = mp.id
                WHERE $whereClause
                ORDER BY d.fecha DESC, d.id DESC 
                $limitClause";

            $stmt = $this->conexion->prepare($sql);

            // Bind parámetros de filtros
            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }

            // Bind parámetros de paginación si se proporcionan
            if ($limit !== null) {
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                if ($offset !== null) {
                    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                }
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo detalles por proveedor: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar detalles de un proveedor específico
     */
    public function contarDetallesPorProveedor($id_materia, $proveedor)
    {
        try {
            $condiciones = ['d.id_materia = :id_materia'];
            $parametros = [':id_materia' => $id_materia];

            // Manejar el caso de "Sin Proveedor"
            if ($proveedor === 'Sin Proveedor') {
                $condiciones[] = "(d.proveedor IS NULL OR TRIM(d.proveedor) = '')";
            } else {
                $condiciones[] = "d.proveedor = :proveedor";
                $parametros[':proveedor'] = $proveedor;
            }

            $whereClause = implode(' AND ', $condiciones);

            $sql = "SELECT COUNT(*) as total 
                    FROM public.sist_prod_detalles_mp d
                    WHERE $whereClause";

            $stmt = $this->conexion->prepare($sql);

            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'];
        } catch (Exception $e) {
            error_log("💥 Error contando detalles por proveedor: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Crear nuevo detalle de materia prima - ACTUALIZADO con cantidad
     */
    public function crear($datos)
    {
        try {
            $this->conexion->beginTransaction();

            $sql = "INSERT INTO public.sist_prod_detalles_mp 
                (peso, factura, proveedor, codigo_unico, id_materia, barcode, fecha, cantidad)
                VALUES 
                (:peso, :factura, :proveedor, :codigo_unico, :id_materia, :barcode, NOW(), :cantidad)
                RETURNING id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':peso', $datos['peso'], PDO::PARAM_STR);
            $stmt->bindParam(':factura', $datos['factura'], PDO::PARAM_STR);
            $stmt->bindParam(':proveedor', $datos['proveedor'], PDO::PARAM_STR);
            $stmt->bindParam(':codigo_unico', $datos['codigo_unico'], PDO::PARAM_STR);
            $stmt->bindParam(':id_materia', $datos['id_materia'], PDO::PARAM_INT);
            $stmt->bindParam(':barcode', $datos['barcode'], PDO::PARAM_STR);
            $stmt->bindParam(':cantidad', $datos['cantidad'], PDO::PARAM_INT); // NUEVO campo

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $id = $resultado['id'];

            $this->conexion->commit();

            error_log("📅 Detalle MP creado con fecha - ID: $id - Fecha: " . date('Y-m-d H:i:s') .
                " - Cantidad: " . ($datos['cantidad'] ?? 0));

            return [
                'success' => true,
                'id' => $id,
                'error' => null
            ];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            return [
                'success' => false,
                'id' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar solo el código único de un detalle
     */
    public function actualizarCodigoUnico($id, $codigo_unico)
    {
        try {
            $this->conexion->beginTransaction();

            $sql = "UPDATE public.sist_prod_detalles_mp 
                    SET codigo_unico = :codigo_unico
                    WHERE id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':codigo_unico', $codigo_unico, PDO::PARAM_STR);

            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();
                error_log("📝 Código único actualizado - ID: $id - Código: $codigo_unico");
                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo actualizar el código único del detalle");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error actualizando código único - ID: $id - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar detalle existente - ACTUALIZADO con cantidad
     */
    public function actualizar($id, $datos)
    {
        try {
            $this->conexion->beginTransaction();

            $sql = "UPDATE public.sist_prod_detalles_mp 
                SET peso = :peso, 
                    factura = :factura, 
                    proveedor = :proveedor,
                    barcode = :barcode,
                    cantidad = :cantidad
                WHERE id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':peso', $datos['peso'], PDO::PARAM_STR);
            $stmt->bindParam(':factura', $datos['factura'], PDO::PARAM_STR);
            $stmt->bindParam(':proveedor', $datos['proveedor'], PDO::PARAM_STR);
            $stmt->bindParam(':barcode', $datos['barcode'], PDO::PARAM_STR);
            $stmt->bindParam(':cantidad', $datos['cantidad'], PDO::PARAM_INT); // NUEVO campo

            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();
                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo actualizar el detalle");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar detalle
     */
    public function eliminar($id)
    {
        try {
            $this->conexion->beginTransaction();

            $sql = "DELETE FROM public.sist_prod_detalles_mp WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();
                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo eliminar el detalle");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar si existe un detalle
     */
    public function existeDetalle($id)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM public.sist_prod_detalles_mp WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (Exception $e) {
            error_log("💥 Error verificando existencia de detalle: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener detalle por ID con descripción de materia prima
     */
    public function obtenerPorId($id)
    {
        try {
            $sql = "SELECT d.*, mp.descripcion as descripcion_materia, mp.unidad as unidad_materia
                    FROM public.sist_prod_detalles_mp d
                    INNER JOIN public.sist_prod_materia_prima mp ON d.id_materia = mp.id
                    WHERE d.id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ?: null;
        } catch (Exception $e) {
            error_log("💥 Error obteniendo detalle por ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener todos los detalles de una materia prima con paginación 
     */
    public function obtenerPorMateria($id_materia, $limit, $offset, $filtros = [])
    {
        try {
            $condiciones = ['d.id_materia = :id_materia'];
            $parametros = [':id_materia' => $id_materia];

            // Aplicar filtros
            if (!empty($filtros['buscar_descripcion'])) {
                $condiciones[] = "LOWER(mp.descripcion) LIKE LOWER(:buscar_descripcion)";
                $parametros[':buscar_descripcion'] = '%' . $filtros['buscar_descripcion'] . '%';
            }

            if (!empty($filtros['buscar_codigo'])) {
                $condiciones[] = "LOWER(d.codigo_unico) LIKE LOWER(:buscar_codigo)";
                $parametros[':buscar_codigo'] = '%' . $filtros['buscar_codigo'] . '%';
            }

            if (!empty($filtros['buscar_proveedor'])) {
                $condiciones[] = "LOWER(d.proveedor) LIKE LOWER(:buscar_proveedor)";
                $parametros[':buscar_proveedor'] = '%' . $filtros['buscar_proveedor'] . '%';
            }

            $whereClause = implode(' AND ', $condiciones);

            $sql = "SELECT d.*, 
                       mp.descripcion as descripcion_materia,
                       mp.unidad as unidad_materia,
                       TO_CHAR(d.fecha AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY') as fecha_solo,
                       TO_CHAR(d.fecha AT TIME ZONE 'America/Asuncion', 'HH24:MI') as hora_solo,
                       TO_CHAR(d.fecha AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_formateada
                FROM public.sist_prod_detalles_mp d
                INNER JOIN public.sist_prod_materia_prima mp ON d.id_materia = mp.id
                WHERE $whereClause
                ORDER BY d.fecha DESC, d.id DESC 
                LIMIT :limit OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);

            // Bind parámetros de filtros
            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }

            // Bind parámetros de paginación
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo detalles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar total de registros por materia
     */
    public function contarPorMateria($id_materia, $filtros = [])
    {
        try {
            $condiciones = ['d.id_materia = :id_materia'];
            $parametros = [':id_materia' => $id_materia];

            // Aplicar filtros
            if (!empty($filtros['buscar_descripcion'])) {
                $condiciones[] = "LOWER(mp.descripcion) LIKE LOWER(:buscar_descripcion)";
                $parametros[':buscar_descripcion'] = '%' . $filtros['buscar_descripcion'] . '%';
            }

            if (!empty($filtros['buscar_codigo'])) {
                $condiciones[] = "LOWER(d.codigo_unico) LIKE LOWER(:buscar_codigo)";
                $parametros[':buscar_codigo'] = '%' . $filtros['buscar_codigo'] . '%';
            }

            if (!empty($filtros['buscar_proveedor'])) {
                $condiciones[] = "LOWER(d.proveedor) LIKE LOWER(:buscar_proveedor)";
                $parametros[':buscar_proveedor'] = '%' . $filtros['buscar_proveedor'] . '%';
            }

            $whereClause = implode(' AND ', $condiciones);

            $sql = "SELECT COUNT(*) as total 
                    FROM public.sist_prod_detalles_mp d
                    INNER JOIN public.sist_prod_materia_prima mp ON d.id_materia = mp.id
                    WHERE $whereClause";
            $stmt = $this->conexion->prepare($sql);

            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'];
        } catch (Exception $e) {
            error_log("💥 Error contando detalles: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener el siguiente número secuencial para un prefijo dado
     */
    public function obtenerSiguienteNumeroSecuencial($prefijo)
    {
        try {
            // Buscar códigos que empiecen con el prefijo dado
            $sql = "SELECT codigo_unico FROM public.sist_prod_detalles_mp 
                    WHERE codigo_unico LIKE :prefijo 
                    ORDER BY codigo_unico DESC 
                    LIMIT 1";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':prefijo', $prefijo . '%');
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado) {
                $ultimoCodigo = $resultado['codigo_unico'];
                // Extraer los últimos 6 dígitos del código
                $ultimoNumero = intval(substr($ultimoCodigo, -6));
                return $ultimoNumero + 1;
            }

            return 1; // Primer código para este patrón

        } catch (Exception $e) {
            error_log("💥 Error obteniendo siguiente número secuencial: " . $e->getMessage());
            return 1;
        }
    }
}
