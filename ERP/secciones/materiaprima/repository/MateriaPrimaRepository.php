<?php

class MateriaPrimaRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    /**
     * Crear nueva materia prima (puede ser para configuración o producción directa)
     * @param array $datos
     * @return array
     */
    public function crear($datos)
    {
        try {
            $this->conexion->beginTransaction();

            $sql = "INSERT INTO public.sist_prod_materia_prima 
                    (descripcion, tipo, ncm, peso_estimado, unidad, cantidad, produccion)
                    VALUES 
                    (:descripcion, :tipo, :ncm, :peso_estimado, :unidad, :cantidad, :produccion)
                    RETURNING id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':descripcion', $datos['descripcion'], PDO::PARAM_STR);
            $stmt->bindParam(':tipo', $datos['tipo'], PDO::PARAM_STR);
            $stmt->bindParam(':unidad', $datos['unidad'], PDO::PARAM_STR);

            // Manejar ncm - convertir a NULL si está vacío
            $ncm = !empty($datos['ncm']) ? trim($datos['ncm']) : null;
            $stmt->bindParam(':ncm', $ncm, $ncm === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

            // Manejar peso estimado
            $peso_estimado = !empty($datos['peso_estimado']) ? floatval($datos['peso_estimado']) : 0.00;
            $stmt->bindParam(':peso_estimado', $peso_estimado, PDO::PARAM_STR);

            // Manejar cantidad
            $cantidad = !empty($datos['cantidad']) ? intval($datos['cantidad']) : 0;
            $stmt->bindParam(':cantidad', $cantidad, PDO::PARAM_INT);

            // Manejar produccion (0 para configuración, 1 para producción directa)
            $produccion = intval($datos['produccion'] ?? 0);
            $stmt->bindParam(':produccion', $produccion, PDO::PARAM_INT);

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $id = $resultado['id'];

            $this->conexion->commit();

            $tipo_destino = $produccion === 1 ? 'PRODUCCIÓN' : 'CONFIGURACIÓN';
            error_log("✅ Materia prima creada en $tipo_destino - ID: $id - Descripción: {$datos['descripcion']}");

            return [
                'success' => true,
                'id' => $id,
                'error' => null
            ];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error creando materia prima: " . $e->getMessage());
            return [
                'success' => false,
                'id' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar materia prima existente
     * @param int $id
     * @param array $datos
     * @return array
     */
    public function actualizar($id, $datos)
    {
        try {
            $this->conexion->beginTransaction();

            // Verificar que el registro existe
            if (!$this->existeMateriaPrima($id)) {
                throw new Exception("La materia prima con ID $id no existe");
            }

            // ✅ CONSTRUIR UPDATE DINÁMICO - Solo campos proporcionados
            $camposUpdate = [];
            $parametros = [':id' => $id];

            // Campos básicos que siempre se pueden actualizar
            if (array_key_exists('descripcion', $datos)) {
                $camposUpdate[] = "descripcion = :descripcion";
                $parametros[':descripcion'] = $datos['descripcion'];
            }

            if (array_key_exists('tipo', $datos)) {
                $camposUpdate[] = "tipo = :tipo";
                $parametros[':tipo'] = $datos['tipo'];
            }

            if (array_key_exists('unidad', $datos)) {
                $camposUpdate[] = "unidad = :unidad";
                $parametros[':unidad'] = $datos['unidad'];
            }

            if (array_key_exists('ncm', $datos)) {
                $camposUpdate[] = "ncm = :ncm";
                // Manejar ncm - convertir a NULL si está vacío
                $ncm = !empty($datos['ncm']) ? trim($datos['ncm']) : null;
                $parametros[':ncm'] = $ncm;
            }

            // ✅ CAMPOS OPCIONALES - Solo se actualizan si se envían
            if (array_key_exists('peso_estimado', $datos)) {
                $camposUpdate[] = "peso_estimado = :peso_estimado";
                $parametros[':peso_estimado'] = floatval($datos['peso_estimado']);
            }

            if (array_key_exists('cantidad', $datos)) {
                $camposUpdate[] = "cantidad = :cantidad";
                $parametros[':cantidad'] = intval($datos['cantidad']);
            }

            if (array_key_exists('produccion', $datos)) {
                $camposUpdate[] = "produccion = :produccion";
                $parametros[':produccion'] = intval($datos['produccion']);
            }

            // Verificar que hay campos para actualizar
            if (empty($camposUpdate)) {
                throw new Exception("No hay campos para actualizar");
            }

            // Construir y ejecutar consulta
            $sql = "UPDATE public.sist_prod_materia_prima SET " .
                implode(', ', $camposUpdate) . " WHERE id = :id";

            $stmt = $this->conexion->prepare($sql);

            // Bind parámetros
            foreach ($parametros as $param => $valor) {
                if ($param === ':id' || $param === ':cantidad' || $param === ':produccion') {
                    $stmt->bindValue($param, $valor, PDO::PARAM_INT);
                } elseif ($param === ':ncm' && $valor === null) {
                    $stmt->bindValue($param, $valor, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue($param, $valor, PDO::PARAM_STR);
                }
            }

            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();

                // Log de campos actualizados
                $camposLog = implode(', ', array_keys(array_diff_key($parametros, [':id' => ''])));
                error_log("🔄 Materia Prima actualizada - ID: $id - Campos: $camposLog");

                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'campos_actualizados' => array_keys(array_diff_key($parametros, [':id' => ''])),
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo actualizar la materia prima");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error actualizando materia prima - ID: $id - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'campos_actualizados' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar solo el peso estimado
     * @param int $id
     * @param float $pesoEstimado
     * @return array
     */
    public function actualizarPesoEstimado($id, $pesoEstimado)
    {
        try {
            $this->conexion->beginTransaction();

            // Verificar que el registro existe
            if (!$this->existeMateriaPrima($id)) {
                throw new Exception("La materia prima con ID $id no existe");
            }

            $sql = "UPDATE public.sist_prod_materia_prima 
                    SET peso_estimado = :peso_estimado
                    WHERE id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':peso_estimado', $pesoEstimado, PDO::PARAM_STR);

            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();
                error_log("🔄 Peso estimado actualizado - ID: $id - Nuevo peso: $pesoEstimado kg");

                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo actualizar el peso estimado");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error actualizando peso estimado - ID: $id - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * NUEVA FUNCIÓN: Actualizar solo la cantidad
     * @param int $id
     * @param int $cantidad
     * @return array
     */
    public function actualizarCantidad($id, $cantidad)
    {
        try {
            $this->conexion->beginTransaction();

            // Verificar que el registro existe
            if (!$this->existeMateriaPrima($id)) {
                throw new Exception("La materia prima con ID $id no existe");
            }

            $sql = "UPDATE public.sist_prod_materia_prima 
                    SET cantidad = :cantidad
                    WHERE id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':cantidad', $cantidad, PDO::PARAM_INT);

            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();
                error_log("🔄 Cantidad actualizada - ID: $id - Nueva cantidad: $cantidad unidades");

                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo actualizar la cantidad");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error actualizando cantidad - ID: $id - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener peso estimado actual
     * @param int $id
     * @return float|null
     */
    public function obtenerPesoEstimadoActual($id)
    {
        try {
            $sql = "SELECT peso_estimado FROM public.sist_prod_materia_prima WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ? floatval($resultado['peso_estimado']) : null;
        } catch (Exception $e) {
            error_log("💥 Error obteniendo peso estimado actual: " . $e->getMessage());
            return null;
        }
    }

    /**
     * NUEVA FUNCIÓN: Obtener cantidad actual
     * @param int $id
     * @return int|null
     */
    public function obtenerCantidadActual($id)
    {
        try {
            $sql = "SELECT cantidad FROM public.sist_prod_materia_prima WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ? intval($resultado['cantidad']) : null;
        } catch (Exception $e) {
            error_log("💥 Error obteniendo cantidad actual: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener valor de producción por ID
     * @param int $id
     * @return int|null
     */
    public function obtenerProduccionPorId($id)
    {
        try {
            $sql = "SELECT produccion FROM public.sist_prod_materia_prima WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ? intval($resultado['produccion']) : null;
        } catch (Exception $e) {
            error_log("💥 Error obteniendo valor de producción: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Eliminar materia prima
     * @param int $id
     * @return array
     */
    public function eliminar($id)
    {
        try {
            $this->conexion->beginTransaction();

            // Verificar que el registro existe y obtener información antes de eliminar
            $material_info = $this->obtenerPorId($id);
            if (!$material_info) {
                throw new Exception("La materia prima con ID $id no existe");
            }

            $sql = "DELETE FROM public.sist_prod_materia_prima WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();

                $contexto = intval($material_info['produccion']) === 1 ? 'PRODUCCIÓN' : 'CONFIGURACIÓN';
                error_log("🗑️ Materia Prima eliminada de $contexto - ID: $id - Descripción: {$material_info['descripcion']}");

                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo eliminar la materia prima");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error eliminando materia prima - ID: $id - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar si existe una materia prima por ID
     * @param int $id
     * @return bool
     */
    public function existeMateriaPrima($id)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM public.sist_prod_materia_prima WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (Exception $e) {
            error_log("💥 Error verificando existencia de materia prima: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener una materia prima por ID
     * @param int $id
     * @return array|null
     */
    public function obtenerPorId($id)
    {
        try {
            $sql = "SELECT id, descripcion, tipo, peso_estimado, peso_registrado, 
                           fecha_movimiento,
                           TO_CHAR(fecha_movimiento AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_movimiento_formateada,
                           ncm, unidad, cantidad, produccion
                    FROM public.sist_prod_materia_prima 
                    WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ?: null;
        } catch (Exception $e) {
            error_log("💥 Error obteniendo materia prima por ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener todas las materias primas con paginación y filtros
     * @param int $limit
     * @param int $offset
     * @param array $filtros
     * @return array
     */
    public function obtenerTodas($limit, $offset, $filtros = [])
    {
        try {
            $condiciones = ['1=1']; // Condición base siempre verdadera
            $parametros = [];

            // Aplicar filtro de producción
            $produccion = isset($filtros['produccion']) ? intval($filtros['produccion']) : 0;
            $condiciones[] = "produccion = :produccion";
            $parametros[':produccion'] = $produccion;

            // Aplicar filtro de búsqueda por descripción
            if (!empty($filtros['buscar_descripcion'])) {
                $condiciones[] = "LOWER(descripcion) LIKE LOWER(:buscar_descripcion)";
                $parametros[':buscar_descripcion'] = '%' . $filtros['buscar_descripcion'] . '%';
            }

            // Aplicar filtro de búsqueda por NCM
            if (!empty($filtros['buscar_ncm'])) {
                $condiciones[] = "LOWER(ncm) LIKE LOWER(:buscar_ncm)";
                $parametros[':buscar_ncm'] = '%' . $filtros['buscar_ncm'] . '%';
            }

            // Aplicar filtro de búsqueda por tipo
            if (!empty($filtros['buscar_tipo'])) {
                $condiciones[] = "tipo = :buscar_tipo";
                $parametros[':buscar_tipo'] = $filtros['buscar_tipo'];
            }

            // Aplicar filtro de búsqueda por unidad
            if (!empty($filtros['buscar_unidad'])) {
                $condiciones[] = "unidad = :buscar_unidad";
                $parametros[':buscar_unidad'] = $filtros['buscar_unidad'];
            }

            $whereClause = implode(' AND ', $condiciones);

            $sql = "SELECT id, descripcion, tipo, peso_estimado, peso_registrado, 
                           fecha_movimiento,
                           TO_CHAR(fecha_movimiento AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_movimiento_formateada,
                           ncm, unidad, cantidad, produccion
                    FROM public.sist_prod_materia_prima 
                    WHERE $whereClause
                    ORDER BY id DESC 
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
            error_log("💥 Error obteniendo materias primas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar total de registros con filtros
     * @param array $filtros
     * @return int
     */
    public function contarTotal($filtros = [])
    {
        try {
            $condiciones = ['1=1'];
            $parametros = [];

            // Aplicar filtro de producción
            $produccion = isset($filtros['produccion']) ? intval($filtros['produccion']) : 0;
            $condiciones[] = "produccion = :produccion";
            $parametros[':produccion'] = $produccion;

            // Aplicar filtro de búsqueda por descripción
            if (!empty($filtros['buscar_descripcion'])) {
                $condiciones[] = "LOWER(descripcion) LIKE LOWER(:buscar_descripcion)";
                $parametros[':buscar_descripcion'] = '%' . $filtros['buscar_descripcion'] . '%';
            }

            // Aplicar filtro de búsqueda por NCM
            if (!empty($filtros['buscar_ncm'])) {
                $condiciones[] = "LOWER(ncm) LIKE LOWER(:buscar_ncm)";
                $parametros[':buscar_ncm'] = '%' . $filtros['buscar_ncm'] . '%';
            }

            // Aplicar filtro de búsqueda por tipo
            if (!empty($filtros['buscar_tipo'])) {
                $condiciones[] = "tipo = :buscar_tipo";
                $parametros[':buscar_tipo'] = $filtros['buscar_tipo'];
            }

            // Aplicar filtro de búsqueda por unidad
            if (!empty($filtros['buscar_unidad'])) {
                $condiciones[] = "unidad = :buscar_unidad";
                $parametros[':buscar_unidad'] = $filtros['buscar_unidad'];
            }

            $whereClause = implode(' AND ', $condiciones);

            $sql = "SELECT COUNT(*) as total FROM public.sist_prod_materia_prima WHERE $whereClause";
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

    /**
     * Obtener datos de paginación completos
     * @param int $itemsPorPagina
     * @param int $paginaActual
     * @param array $filtros
     * @return array
     */
    public function obtenerDatosPaginacion($itemsPorPagina, $paginaActual, $filtros = [])
    {
        $totalRegistros = $this->contarTotal($filtros);
        $totalPaginas = ceil($totalRegistros / $itemsPorPagina);
        $offset = ($paginaActual - 1) * $itemsPorPagina;

        $registros = $this->obtenerTodas($itemsPorPagina, $offset, $filtros);

        return [
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'pagina_actual' => $paginaActual,
            'registros' => $registros,
            'items_por_pagina' => $itemsPorPagina
        ];
    }

    /**
     * Buscar materia prima por descripción
     * @param string $termino
     * @param int $produccion
     * @return array
     */
    public function buscarPorDescripcion($termino, $produccion = 0)
    {
        try {
            $sql = "SELECT id, descripcion, tipo, peso_estimado, peso_registrado, 
                           fecha_movimiento,
                           TO_CHAR(fecha_movimiento AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_movimiento_formateada,
                           ncm, unidad, cantidad, produccion
                    FROM public.sist_prod_materia_prima 
                    WHERE LOWER(descripcion) LIKE LOWER(:termino) AND produccion = :produccion
                    ORDER BY descripcion";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':termino', '%' . $termino . '%', PDO::PARAM_STR);
            $stmt->bindValue(':produccion', $produccion, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error buscando por descripción: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar materia prima por NCM
     * @param string $termino
     * @param int $produccion
     * @return array
     */
    public function buscarPorNCM($termino, $produccion = 0)
    {
        try {
            $sql = "SELECT id, descripcion, tipo, peso_estimado, peso_registrado, 
                           fecha_movimiento,
                           TO_CHAR(fecha_movimiento AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_movimiento_formateada,
                           ncm, unidad, cantidad, produccion
                    FROM public.sist_prod_materia_prima 
                    WHERE LOWER(ncm) LIKE LOWER(:termino) AND produccion = :produccion
                    ORDER BY ncm";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':termino', '%' . $termino . '%', PDO::PARAM_STR);
            $stmt->bindValue(':produccion', $produccion, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error buscando por NCM: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar materia prima por tipo
     * @param string $tipo
     * @param int $produccion
     * @return array
     */
    public function buscarPorTipo($tipo, $produccion = 0)
    {
        try {
            $sql = "SELECT id, descripcion, tipo, peso_estimado, peso_registrado, 
                           fecha_movimiento,
                           TO_CHAR(fecha_movimiento AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_movimiento_formateada,
                           ncm, unidad, cantidad, produccion
                    FROM public.sist_prod_materia_prima 
                    WHERE tipo = :tipo AND produccion = :produccion
                    ORDER BY descripcion";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
            $stmt->bindValue(':produccion', $produccion, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error buscando por tipo: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar materia prima por unidad
     * @param string $unidad
     * @param int $produccion
     * @return array
     */
    public function buscarPorUnidad($unidad, $produccion = 0)
    {
        try {
            $sql = "SELECT id, descripcion, tipo, peso_estimado, peso_registrado, 
                           fecha_movimiento,
                           TO_CHAR(fecha_movimiento AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_movimiento_formateada,
                           ncm, unidad, cantidad, produccion
                    FROM public.sist_prod_materia_prima 
                    WHERE unidad = :unidad AND produccion = :produccion
                    ORDER BY descripcion";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':unidad', $unidad, PDO::PARAM_STR);
            $stmt->bindValue(':produccion', $produccion, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error buscando por unidad: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener todas las materias primas por tipo
     * @param string $tipo
     * @param int $produccion
     * @return array
     */
    public function obtenerPorTipo($tipo, $produccion = 0)
    {
        try {
            $sql = "SELECT id, descripcion, tipo, peso_estimado, peso_registrado, 
                           fecha_movimiento,
                           TO_CHAR(fecha_movimiento AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_movimiento_formateada,
                           ncm, unidad, cantidad, produccion
                    FROM public.sist_prod_materia_prima 
                    WHERE tipo = :tipo AND produccion = :produccion
                    ORDER BY descripcion ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
            $stmt->bindValue(':produccion', $produccion, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo por tipo: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener todas las materias primas por unidad
     * @param string $unidad
     * @param int $produccion
     * @return array
     */
    public function obtenerPorUnidad($unidad, $produccion = 0)
    {
        try {
            $sql = "SELECT id, descripcion, tipo, peso_estimado, peso_registrado, 
                           fecha_movimiento,
                           TO_CHAR(fecha_movimiento AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_movimiento_formateada,
                           ncm, unidad, cantidad, produccion
                    FROM public.sist_prod_materia_prima 
                    WHERE unidad = :unidad AND produccion = :produccion
                    ORDER BY descripcion ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':unidad', $unidad, PDO::PARAM_STR);
            $stmt->bindValue(':produccion', $produccion, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo por unidad: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener todas las materias primas por valor de producción
     * @param int $produccion
     * @return array
     */
    public function obtenerPorProduccion($produccion)
    {
        try {
            $sql = "SELECT id, descripcion, tipo, peso_estimado, peso_registrado, 
                           fecha_movimiento,
                           TO_CHAR(fecha_movimiento AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_movimiento_formateada,
                           ncm, unidad, cantidad, produccion
                    FROM public.sist_prod_materia_prima 
                    WHERE produccion = :produccion
                    ORDER BY descripcion ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':produccion', $produccion, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo por producción: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verificar duplicados por descripción
     * @param string $descripcion
     * @param int $excluirId - ID a excluir (para ediciones)
     * @param int $produccion
     * @return bool
     */
    public function existeDescripcion($descripcion, $excluirId = null, $produccion = null)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM public.sist_prod_materia_prima 
                    WHERE LOWER(descripcion) = LOWER(:descripcion)";

            $parametros = [':descripcion' => $descripcion];

            if ($excluirId) {
                $sql .= " AND id != :excluir_id";
                $parametros[':excluir_id'] = $excluirId;
            }

            // Si se especifica producción, filtrar por ella
            if ($produccion !== null) {
                $sql .= " AND produccion = :produccion";
                $parametros[':produccion'] = $produccion;
            }

            $stmt = $this->conexion->prepare($sql);
            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (Exception $e) {
            error_log("💥 Error verificando descripción duplicada: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar duplicados por NCM
     * @param string $ncm
     * @param int $excluirId - ID a excluir (para ediciones)
     * @param int $produccion
     * @return bool
     */
    public function existeNCM($ncm, $excluirId = null, $produccion = null)
    {
        try {
            if (empty($ncm)) {
                return false; // NCM vacío no se considera duplicado
            }

            $sql = "SELECT COUNT(*) as total FROM public.sist_prod_materia_prima 
                    WHERE LOWER(ncm) = LOWER(:ncm) AND ncm IS NOT NULL AND ncm != ''";

            $parametros = [':ncm' => $ncm];

            if ($excluirId) {
                $sql .= " AND id != :excluir_id";
                $parametros[':excluir_id'] = $excluirId;
            }

            // Si se especifica producción, filtrar por ella
            if ($produccion !== null) {
                $sql .= " AND produccion = :produccion";
                $parametros[':produccion'] = $produccion;
            }

            $stmt = $this->conexion->prepare($sql);
            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (Exception $e) {
            error_log("💥 Error verificando NCM duplicado: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener todas las materias primas ordenadas alfabéticamente (para selects)
     * @param int $produccion
     * @return array
     */
    public function obtenerTodasOrdenadas($produccion = 0)
    {
        try {
            $sql = "SELECT id, descripcion, tipo, peso_estimado, peso_registrado, 
                           fecha_movimiento,
                           TO_CHAR(fecha_movimiento AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI') as fecha_movimiento_formateada,
                           ncm, unidad, cantidad, produccion
                    FROM public.sist_prod_materia_prima 
                    WHERE produccion = :produccion
                    ORDER BY descripcion ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':produccion', $produccion, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo materias primas ordenadas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener estadísticas por unidad
     * @param string $unidad
     * @param int $produccion
     * @return array
     */
    public function obtenerEstadisticasPorUnidad($unidad = null, $produccion = 0)
    {
        try {
            $sql = "SELECT 
                        unidad,
                        COUNT(*) as total_items,
                        SUM(cantidad) as total_cantidad,
                        AVG(cantidad) as promedio_cantidad,
                        SUM(peso_estimado * cantidad) as peso_total_estimado
                    FROM public.sist_prod_materia_prima 
                    WHERE produccion = :produccion";

            $parametros = [':produccion' => $produccion];

            if ($unidad) {
                $sql .= " AND unidad = :unidad";
                $parametros[':unidad'] = $unidad;
            }

            $sql .= " GROUP BY unidad ORDER BY unidad";

            $stmt = $this->conexion->prepare($sql);
            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo estadísticas por unidad: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener conteos por contexto
     * @return array
     */
    public function obtenerConteosPorContexto()
    {
        try {
            $sql = "SELECT 
                        produccion,
                        COUNT(*) as total,
                        COUNT(CASE WHEN tipo = 'Materia Prima' THEN 1 END) as materias_prima,
                        COUNT(CASE WHEN tipo = 'Insumo' THEN 1 END) as insumos,
                        COUNT(CASE WHEN unidad = 'Kilos' THEN 1 END) as por_kilos,
                        COUNT(CASE WHEN unidad = 'Unidad' THEN 1 END) as por_unidad
                    FROM public.sist_prod_materia_prima 
                    GROUP BY produccion 
                    ORDER BY produccion";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $conteos = [
                'configuracion' => ['total' => 0, 'materias_prima' => 0, 'insumos' => 0, 'por_kilos' => 0, 'por_unidad' => 0],
                'produccion' => ['total' => 0, 'materias_prima' => 0, 'insumos' => 0, 'por_kilos' => 0, 'por_unidad' => 0]
            ];

            foreach ($resultados as $resultado) {
                $contexto = intval($resultado['produccion']) === 1 ? 'produccion' : 'configuracion';
                $conteos[$contexto] = [
                    'total' => intval($resultado['total']),
                    'materias_prima' => intval($resultado['materias_prima']),
                    'insumos' => intval($resultado['insumos']),
                    'por_kilos' => intval($resultado['por_kilos']),
                    'por_unidad' => intval($resultado['por_unidad'])
                ];
            }

            return $conteos;
        } catch (Exception $e) {
            error_log("💥 Error obteniendo conteos por contexto: " . $e->getMessage());
            return [
                'configuracion' => ['total' => 0, 'materias_prima' => 0, 'insumos' => 0, 'por_kilos' => 0, 'por_unidad' => 0],
                'produccion' => ['total' => 0, 'materias_prima' => 0, 'insumos' => 0, 'por_kilos' => 0, 'por_unidad' => 0]
            ];
        }
    }

    /**
     * Obtener la conexión (método auxiliar)
     * @return PDO
     */
    public function getConexion()
    {
        return $this->conexion;
    }
}
