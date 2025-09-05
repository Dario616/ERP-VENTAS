<?php
require_once __DIR__ . '/../repository/RecetasMateriaPrimaRepository.php';

/**
 * Servicio para recetas de materia prima
 * Permite crear composiciones de materias primas usando otras materias primas
 */
class RecetasMateriaPrimaService
{
    private $recetasMateriaPrimaRepo;

    public function __construct($conexion)
    {
        $this->recetasMateriaPrimaRepo = new RecetasMateriaPrimaRepository($conexion);
    }

    /**
     * Validar datos del formulario de recetas de materia prima
     */
    public function validarDatosFormulario($datos)
    {
        $errores = [];

        $id_materia_prima_objetivo = intval($datos['id_materia_prima_objetivo'] ?? 0);
        if ($id_materia_prima_objetivo <= 0) {
            $errores[] = "Debe seleccionar una materia prima objetivo válida";
        }

        $id_materia_prima = intval($datos['id_materia_prima'] ?? 0);
        if ($id_materia_prima <= 0) {
            $errores[] = "Debe seleccionar una materia prima componente válida";
        }

        // Validar que no se use la misma materia prima como objetivo y componente
        if ($id_materia_prima_objetivo === $id_materia_prima) {
            $errores[] = "Una materia prima no puede ser componente de sí misma";
        }

        $porcentaje = trim($datos['cantidad_por_kilo'] ?? '');
        if (empty($porcentaje)) {
            $errores[] = "La cantidad es obligatoria";
        } elseif (!is_numeric($porcentaje) || floatval($porcentaje) <= 0) {
            $errores[] = "La cantidad debe ser un número positivo";
        }

        // Validar según el tipo de materia prima
        $es_materia_extra = $datos['es_materia_extra'] ?? false;
        if (!$es_materia_extra) {
            // Para materias principales, validar como porcentaje
            if (floatval($porcentaje) > 100) {
                $errores[] = "El porcentaje no puede ser mayor a 100%";
            }
        } else {
            // Para materias extras, validar unidad de medida
            $unidad_medida = trim($datos['unidad_medida_extra'] ?? '');
            if (empty($unidad_medida)) {
                $errores[] = "Debe especificar la unidad de medida para componentes extras";
            }
        }
        $nombre_receta = trim($datos['nombre_receta'] ?? '');
        if (!empty($nombre_receta) && strlen($nombre_receta) < 3) {
            $errores[] = "El nombre de la composición debe tener al menos 3 caracteres";
        }

        $version_receta = intval($datos['version_receta'] ?? 1);
        if ($version_receta < 1) {
            $errores[] = "La versión de composición debe ser un número positivo";
        }

        if (!empty($errores)) {
            throw new Exception(implode('. ', $errores));
        }

        return [
            'id_materia_prima_objetivo' => $id_materia_prima_objetivo,
            'id_materia_prima' => $id_materia_prima,
            'cantidad_por_kilo' => floatval($porcentaje),
            'nombre_receta' => empty($nombre_receta) ? 'Composición Principal' : $nombre_receta,
            'version_receta' => $version_receta,
            'es_materia_extra' => $es_materia_extra,
            'unidad_medida_extra' => $es_materia_extra ? trim($datos['unidad_medida_extra'] ?? '') : null
        ];
    }

    /**
     * Validar datos de múltiples materias primas componentes
     */
    public function validarMateriasPrimasMultiples($id_materia_prima_objetivo, $materias_primas_data, $version_receta = null)
    {
        $errores = [];
        $materias_validadas = [];

        if (!is_numeric($id_materia_prima_objetivo) || intval($id_materia_prima_objetivo) <= 0) {
            throw new Exception("Debe seleccionar una materia prima objetivo válida");
        }

        if (empty($materias_primas_data) || !is_array($materias_primas_data)) {
            throw new Exception("Debe agregar al menos una materia prima componente");
        }

        if ($version_receta === null) {
            $version_receta = $this->obtenerSiguienteVersionDisponible($id_materia_prima_objetivo);
        }

        $materias_disponibles = $this->obtenerMateriasPrimas();
        $materias_map = [];
        foreach ($materias_disponibles as $materia) {
            $materias_map[$materia['id']] = $materia['descripcion'];
        }

        $materias_procesadas = [];
        $suma_porcentajes_principales = 0;
        $tiene_materias_principales = false;

        foreach ($materias_primas_data as $index => $materia) {
            $fila = $index + 1;

            $id_materia_prima = intval($materia['id_materia_prima'] ?? 0);
            if ($id_materia_prima <= 0) {
                $errores[] = "Fila $fila: Debe seleccionar una materia prima componente válida";
                continue;
            }

            if (!isset($materias_map[$id_materia_prima])) {
                $errores[] = "Fila $fila: La materia prima componente seleccionada no existe";
                continue;
            }

            // Validar que no se use la misma materia prima como objetivo y componente
            if ($id_materia_prima_objetivo === $id_materia_prima) {
                $errores[] = "Fila $fila: Una materia prima no puede ser componente de sí misma";
                continue;
            }

            if (in_array($id_materia_prima, $materias_procesadas)) {
                $errores[] = "Fila $fila: La materia prima '{$materias_map[$id_materia_prima]}' está duplicada";
                continue;
            }

            if ($this->recetasMateriaPrimaRepo->existeCombinacionEnVersion($id_materia_prima_objetivo, $id_materia_prima, $version_receta)) {
                $errores[] = "Fila $fila: La materia prima '{$materias_map[$id_materia_prima]}' ya existe en la versión $version_receta";
                continue;
            }

            $cantidad = trim($materia['cantidad_por_kilo'] ?? '');
            if (empty($cantidad)) {
                $errores[] = "Fila $fila: La cantidad es obligatoria";
                continue;
            }

            if (!is_numeric($cantidad) || floatval($cantidad) <= 0) {
                $errores[] = "Fila $fila: La cantidad debe ser un número positivo";
                continue;
            }

            $es_materia_extra = isset($materia['es_materia_extra']) && $materia['es_materia_extra'];
            $unidad_medida_extra = trim($materia['unidad_medida_extra'] ?? '');

            // Validaciones específicas según el tipo de materia
            if ($es_materia_extra) {
                if (empty($unidad_medida_extra)) {
                    $errores[] = "Fila $fila: Debe especificar la unidad de medida para componentes extras";
                    continue;
                }
            } else {
                // Es materia principal - validar como porcentaje
                if (floatval($cantidad) > 100) {
                    $errores[] = "Fila $fila: El porcentaje no puede ser mayor a 100%";
                    continue;
                }
                $tiene_materias_principales = true;
            }

            $cantidad_valor = floatval($cantidad);
            $materias_validadas[] = [
                'id_materia_prima' => $id_materia_prima,
                'nombre_materia' => $materias_map[$id_materia_prima],
                'cantidad_por_kilo' => $cantidad_valor,
                'es_materia_extra' => $es_materia_extra,
                'unidad_medida_extra' => $es_materia_extra ? $unidad_medida_extra : null
            ];

            $materias_procesadas[] = $id_materia_prima;

            // Solo sumar al porcentaje principal si no es materia extra
            if (!$es_materia_extra) {
                $suma_porcentajes_principales += $cantidad_valor;
            }
        }

        if (!empty($errores)) {
            throw new Exception(implode('<br>', $errores));
        }

        if (empty($materias_validadas)) {
            throw new Exception("No hay materias primas válidas para procesar");
        }

        // Validar que existe al menos una materia principal
        if (!$tiene_materias_principales) {
            throw new Exception("Debe incluir al menos una materia prima principal (que sume al 100%)");
        }

        // Validar que las materias principales sumen 100%
        $suma_porcentajes_principales = round($suma_porcentajes_principales, 3);
        if (abs($suma_porcentajes_principales - 100) > 0.001) {
            throw new Exception("La suma de porcentajes de componentes principales debe ser exactamente 100%. Suma actual: {$suma_porcentajes_principales}%");
        }

        return $materias_validadas;
    }

    /**
     * Crear nueva receta de materia prima
     */
    public function crearReceta($datos, $usuario = 'SISTEMA')
    {
        try {
            $datosValidados = $this->validarDatosFormulario($datos);

            if (!isset($datosValidados['version_receta'])) {
                $datosValidados['version_receta'] = $this->obtenerSiguienteVersionDisponible($datosValidados['id_materia_prima_objetivo']);
            }

            if ($this->recetasMateriaPrimaRepo->existeCombinacionEnVersion(
                $datosValidados['id_materia_prima_objetivo'],
                $datosValidados['id_materia_prima'],
                $datosValidados['version_receta']
            )) {
                throw new Exception("Ya existe una composición para esta combinación en la versión {$datosValidados['version_receta']}");
            }

            $datosValidados['usuario_creacion'] = $usuario;
            $resultado = $this->recetasMateriaPrimaRepo->crear($datosValidados);

            if ($resultado['success']) {
                $tipo_materia = $datosValidados['es_materia_extra'] ? 'EXTRA' : 'PRINCIPAL';
                error_log("✅ Composición materia prima creada - ID: {$resultado['id']} - Versión: {$datosValidados['version_receta']} - Tipo: $tipo_materia");
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("💥 Error creando composición materia prima: " . $e->getMessage());
            return [
                'success' => false,
                'id' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Crear múltiples recetas de materia prima
     */
    public function crearRecetasMultiples($id_materia_prima_objetivo, $materias_primas_data, $usuario = 'SISTEMA')
    {
        try {
            $siguiente_version = $this->obtenerSiguienteVersionDisponible($id_materia_prima_objetivo);
            $nombre_receta = $siguiente_version === 1 ? 'Composición Principal' : "Composición Versión $siguiente_version";

            return $this->crearRecetasMultiplesConVersion(
                $id_materia_prima_objetivo,
                $materias_primas_data,
                $nombre_receta,
                $siguiente_version,
                $usuario
            );
        } catch (Exception $e) {
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
     * Crear múltiples recetas con versión específica
     */
    public function crearRecetasMultiplesConVersion($id_materia_prima_objetivo, $materias_primas_data, $nombre_receta, $version_receta = null, $usuario = 'SISTEMA')
    {
        try {
            if (is_null($version_receta)) {
                $version_receta = $this->obtenerSiguienteVersionDisponible($id_materia_prima_objetivo);
            }

            if ($this->existeRecetaVersion($id_materia_prima_objetivo, $version_receta)) {
                throw new Exception("Ya existe una composición versión $version_receta para esta materia prima.");
            }

            $materias_validadas = $this->validarMateriasPrimasMultiples(
                $id_materia_prima_objetivo,
                $materias_primas_data,
                $version_receta
            );

            $resultado = $this->recetasMateriaPrimaRepo->crearMultiplesConVersion(
                $id_materia_prima_objetivo,
                $materias_validadas,
                $nombre_receta,
                $version_receta,
                $usuario
            );

            if ($resultado['success']) {
                error_log("✅ Composiciones múltiples creadas - Objetivo: $id_materia_prima_objetivo - Versión: $version_receta");
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("💥 Error creando composiciones múltiples con versión: " . $e->getMessage());
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
    public function actualizarReceta($id, $datos, $usuario = 'SISTEMA')
    {
        try {
            if (!$this->recetasMateriaPrimaRepo->existeReceta($id)) {
                throw new Exception("La composición con ID $id no existe");
            }

            $receta_actual = $this->recetasMateriaPrimaRepo->obtenerPorId($id);
            if (!$receta_actual) {
                throw new Exception("No se pudieron obtener los datos de la composición actual");
            }

            $datosValidados = $this->validarDatosFormulario($datos);

            if (!isset($datosValidados['version_receta'])) {
                $datosValidados['version_receta'] = $receta_actual['version_receta'];
            }

            if ($this->recetasMateriaPrimaRepo->existeCombinacionEnVersion(
                $datosValidados['id_materia_prima_objetivo'],
                $datosValidados['id_materia_prima'],
                $datosValidados['version_receta'],
                $id
            )) {
                throw new Exception("Ya existe otra composición para esta combinación en la versión {$datosValidados['version_receta']}");
            }

            $datosValidados['usuario_modificacion'] = $usuario;
            $resultado = $this->recetasMateriaPrimaRepo->actualizar($id, $datosValidados);

            if ($resultado['success']) {
                error_log("🔄 Composición actualizada - ID: $id - Versión: {$datosValidados['version_receta']}");
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("💥 Error actualizando composición - ID: $id - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar receta
     */
    public function eliminarReceta($id, $usuario = 'SISTEMA')
    {
        try {
            if (!$this->recetasMateriaPrimaRepo->existeReceta($id)) {
                throw new Exception("La composición con ID $id no existe");
            }

            $receta = $this->recetasMateriaPrimaRepo->obtenerPorId($id);
            $resultado = $this->recetasMateriaPrimaRepo->eliminar($id, $usuario);

            if ($resultado['success'] && $receta) {
                error_log("🗑️ Composición eliminada - ID: $id - Usuario: $usuario");
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("💥 Error eliminando composición - ID: $id - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar versión completa de receta
     */
    public function eliminarRecetaVersion($id_materia_prima_objetivo, $version_receta, $usuario = 'SISTEMA')
    {
        try {
            if (!is_numeric($id_materia_prima_objetivo) || intval($id_materia_prima_objetivo) <= 0) {
                throw new Exception("ID de materia prima objetivo inválido");
            }

            if (!is_numeric($version_receta) || intval($version_receta) <= 0) {
                throw new Exception("Versión de composición inválida");
            }

            if (!$this->existeRecetaVersion($id_materia_prima_objetivo, $version_receta)) {
                throw new Exception("No existe la versión $version_receta para esta materia prima");
            }

            $resultado = $this->recetasMateriaPrimaRepo->eliminarRecetaVersion($id_materia_prima_objetivo, $version_receta, $usuario);

            if ($resultado['success']) {
                error_log("🗑️ Versión composición eliminada - Objetivo: $id_materia_prima_objetivo - Versión: $version_receta - Usuario: $usuario");
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("💥 Error eliminando versión composición: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar todas las recetas de una materia prima
     */
    public function eliminarTodasRecetasMateria($id_materia_prima_objetivo, $usuario = 'SISTEMA')
    {
        try {
            if (!is_numeric($id_materia_prima_objetivo) || intval($id_materia_prima_objetivo) <= 0) {
                throw new Exception("ID de materia prima objetivo inválido");
            }

            if (!$this->recetasMateriaPrimaRepo->materiaTieneRecetas($id_materia_prima_objetivo)) {
                throw new Exception("Esta materia prima no tiene composiciones configuradas");
            }

            $resultado = $this->recetasMateriaPrimaRepo->eliminarTodasRecetasDeMateria($id_materia_prima_objetivo, $usuario);

            if ($resultado['success']) {
                error_log("🗑️ Todas las composiciones eliminadas - Objetivo: $id_materia_prima_objetivo - Usuario: $usuario");
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("💥 Error eliminando todas las composiciones - Objetivo: $id_materia_prima_objetivo - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener siguiente versión disponible
     */
    private function obtenerSiguienteVersionDisponible($id_materia_prima_objetivo)
    {
        try {
            $ultima_version = $this->recetasMateriaPrimaRepo->obtenerUltimaVersionPorMateria($id_materia_prima_objetivo);
            return $ultima_version + 1;
        } catch (Exception $e) {
            error_log("💥 Error obteniendo siguiente versión: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Verificar si existe una receta con versión específica
     */
    private function existeRecetaVersion($id_materia_prima_objetivo, $version_receta)
    {
        try {
            return $this->recetasMateriaPrimaRepo->existeRecetaVersion($id_materia_prima_objetivo, $version_receta);
        } catch (Exception $e) {
            error_log("💥 Error verificando versión: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validar que una versión tenga exactamente 100%
     */
    public function validarPorcentajeCompleto($id_materia_prima_objetivo, $version_receta = null)
    {
        try {
            if ($version_receta !== null) {
                $suma = $this->obtenerSumaPorcentajesExistentesVersion($id_materia_prima_objetivo, $version_receta);
                $es_completo = abs($suma - 100) <= 0.001;

                return [
                    'es_completo' => $es_completo,
                    'suma_actual' => $suma,
                    'porcentaje_faltante' => $es_completo ? 0 : (100 - $suma),
                    'version_receta' => $version_receta,
                    'mensaje' => $es_completo ? "Versión $version_receta completa (100%)" : "Versión $version_receta: Faltan " . (100 - $suma) . "% para completar"
                ];
            } else {
                $versiones = $this->listarVersionesReceta($id_materia_prima_objetivo);
                $resultado = [
                    'versiones_validadas' => [],
                    'versiones_completas' => 0,
                    'versiones_incompletas' => 0
                ];

                if ($versiones['success'] && !empty($versiones['versiones'])) {
                    foreach ($versiones['versiones'] as $version) {
                        $validacion = $this->validarPorcentajeCompleto($id_materia_prima_objetivo, $version['version_receta']);
                        $resultado['versiones_validadas'][] = $validacion;

                        if ($validacion['es_completo']) {
                            $resultado['versiones_completas']++;
                        } else {
                            $resultado['versiones_incompletas']++;
                        }
                    }
                }

                return $resultado;
            }
        } catch (Exception $e) {
            return [
                'es_completo' => false,
                'suma_actual' => 0,
                'porcentaje_faltante' => 100,
                'version_receta' => $version_receta,
                'mensaje' => 'Error al validar porcentajes: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Listar versiones de recetas para una materia prima
     */
    public function listarVersionesReceta($id_materia_prima_objetivo)
    {
        try {
            if (!is_numeric($id_materia_prima_objetivo) || intval($id_materia_prima_objetivo) <= 0) {
                throw new Exception("ID de materia prima objetivo inválido");
            }

            $versiones = $this->recetasMateriaPrimaRepo->obtenerVersionesPorMateria($id_materia_prima_objetivo);

            return [
                'success' => true,
                'versiones' => $versiones,
                'total_versiones' => count($versiones),
                'error' => null
            ];
        } catch (Exception $e) {
            error_log("💥 Error listando versiones: " . $e->getMessage());
            return [
                'success' => false,
                'versiones' => [],
                'total_versiones' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener detalle de una versión específica
     */
    public function obtenerDetalleRecetaVersion($id_materia_prima_objetivo, $version_receta)
    {
        try {
            if (!is_numeric($id_materia_prima_objetivo) || intval($id_materia_prima_objetivo) <= 0) {
                throw new Exception("ID de materia prima objetivo inválido");
            }

            if (!is_numeric($version_receta) || intval($version_receta) <= 0) {
                throw new Exception("Versión de composición inválida");
            }

            $detalle = $this->recetasMateriaPrimaRepo->obtenerDetalleRecetaPorMateria(intval($id_materia_prima_objetivo), intval($version_receta));

            if (is_null($detalle)) {
                throw new Exception("No se encontró la versión especificada");
            }

            $version_encontrada = null;
            foreach ($detalle['versiones'] as $version) {
                if ($version['version'] == $version_receta) {
                    $version_encontrada = $version;
                    break;
                }
            }

            if (!$version_encontrada) {
                throw new Exception("No se encontró la versión $version_receta");
            }

            $total_porcentaje = $version_encontrada['total_cantidad'];
            $es_completo = abs($total_porcentaje - 100) <= 0.001;

            return [
                'success' => true,
                'id_materia_prima_objetivo' => $detalle['id_materia_prima_objetivo'],
                'materia_prima_objetivo' => $detalle['materia_prima_objetivo'],
                'version_receta' => $version_receta,
                'nombre_receta' => $version_encontrada['nombre_receta'],
                'materias_principales' => $version_encontrada['materias_principales'] ?? [],
                'materias_extras' => $version_encontrada['materias_extras'] ?? [],
                'total_cantidad' => number_format($total_porcentaje, 3),
                'total_materias_principales' => $version_encontrada['total_materias_principales'] ?? 0,
                'total_materias_extras' => $version_encontrada['total_materias_extras'] ?? 0,
                'total_materias' => ($version_encontrada['total_materias_principales'] ?? 0) + ($version_encontrada['total_materias_extras'] ?? 0),
                'es_completo' => $es_completo,
                'porcentaje_faltante' => $es_completo ? 0 : (100 - $total_porcentaje),
                'mensaje_completitud' => $es_completo ? 'Composición completa (100%)' : 'Composición incompleta - Faltan ' . number_format(100 - $total_porcentaje, 3) . '%',
                'error' => null
            ];
        } catch (Exception $e) {
            error_log("💥 Error obteniendo detalle de versión: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener suma de porcentajes existentes para una versión específica
     */
    private function obtenerSumaPorcentajesExistentesVersion($id_materia_prima_objetivo, $version_receta, $excluir_id = null)
    {
        try {
            $recetas = $this->obtenerRecetasPorMateriaPrima($id_materia_prima_objetivo, $version_receta);
            $suma = 0;

            foreach ($recetas as $receta) {
                if ($excluir_id && $receta['id'] == $excluir_id) {
                    continue;
                }
                // Solo sumar las materias principales (no extras)
                if (!$receta['es_materia_extra']) {
                    $suma += floatval($receta['cantidad_por_kilo']);
                }
            }

            return round($suma, 3);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo suma de porcentajes: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener datos de paginación
     */
    public function obtenerDatosPaginacion($itemsPorPagina, $paginaActual, $filtros = [])
    {
        $totalRegistros = $this->recetasMateriaPrimaRepo->contarTodas($filtros);
        $totalPaginas = ceil($totalRegistros / $itemsPorPagina);
        $offset = ($paginaActual - 1) * $itemsPorPagina;

        $registros = $this->recetasMateriaPrimaRepo->obtenerTodas($itemsPorPagina, $offset, $filtros);

        return [
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'pagina_actual' => $paginaActual,
            'registros' => $registros,
            'items_por_pagina' => $itemsPorPagina
        ];
    }

    /**
     * Obtener datos de paginación agrupados
     */
    public function obtenerDatosPaginacionAgrupados($itemsPorPagina, $paginaActual, $filtros = [])
    {
        $totalRegistros = $this->recetasMateriaPrimaRepo->contarMateriasPrimas($filtros);
        $totalPaginas = ceil($totalRegistros / $itemsPorPagina);
        $offset = ($paginaActual - 1) * $itemsPorPagina;

        $registros = $this->recetasMateriaPrimaRepo->obtenerMateriasPrimasConRecetas($itemsPorPagina, $offset, $filtros);

        return [
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'pagina_actual' => $paginaActual,
            'registros' => $registros,
            'items_por_pagina' => $itemsPorPagina
        ];
    }

    /**
     * Obtener receta por ID
     */
    public function obtenerRecetaPorId($id)
    {
        return $this->recetasMateriaPrimaRepo->obtenerPorId($id);
    }

    /**
     * Obtener recetas por materia prima objetivo
     */
    public function obtenerRecetasPorMateriaPrima($id_materia_prima_objetivo, $version_receta = null)
    {
        try {
            if (!is_numeric($id_materia_prima_objetivo) || intval($id_materia_prima_objetivo) <= 0) {
                throw new Exception("ID de materia prima objetivo inválido");
            }

            return $this->recetasMateriaPrimaRepo->obtenerPorMateriaPrima(intval($id_materia_prima_objetivo), $version_receta);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo composiciones por materia prima: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener materias primas
     */
    public function obtenerMateriasPrimas()
    {
        return $this->recetasMateriaPrimaRepo->obtenerMateriasPrimas();
    }

    /**
     * Buscar materias primas disponibles
     */
    public function buscarMateriasDisponibles($termino = '', $excluir_id = null)
    {
        return $this->recetasMateriaPrimaRepo->buscarMateriasDisponibles($termino, $excluir_id);
    }

    /**
     * Obtener detalle completo de recetas por materia prima
     */
    public function obtenerDetalleRecetaMateria($id_materia_prima_objetivo)
    {
        try {
            if (!is_numeric($id_materia_prima_objetivo) || intval($id_materia_prima_objetivo) <= 0) {
                throw new Exception("ID de materia prima objetivo inválido");
            }

            $detalle = $this->recetasMateriaPrimaRepo->obtenerDetalleRecetaPorMateria(intval($id_materia_prima_objetivo));

            if (is_null($detalle)) {
                throw new Exception("No se encontró la materia prima especificada");
            }

            // VERIFICAR QUE VERSIONES SEA UN ARRAY
            if (!isset($detalle['versiones']) || !is_array($detalle['versiones'])) {
                $detalle['versiones'] = [];
            }

            foreach ($detalle['versiones'] as $index => &$version) {
                // VERIFICAR QUE VERSION SEA UN ARRAY
                if (!is_array($version)) {
                    error_log("🚨 Versión no es array en índice $index: " . print_r($version, true));
                    continue;
                }

                $total_porcentaje = floatval($version['total_cantidad'] ?? 0);
                $version['total_cantidad'] = number_format($total_porcentaje, 3);

                // CORRECCIÓN ROBUSTA: Verificar y corregir tipos de datos
                if (!isset($version['materias_principales'])) {
                    $version['materias_principales'] = [];
                    error_log("🔧 Inicializando materias_principales vacío para versión {$version['version']}");
                } elseif (!is_array($version['materias_principales'])) {
                    error_log("🚨 materias_principales no es array: " . gettype($version['materias_principales']) . " - Valor: " . print_r($version['materias_principales'], true));
                    $version['materias_principales'] = [];
                }

                if (!isset($version['materias_extras'])) {
                    $version['materias_extras'] = [];
                    error_log("🔧 Inicializando materias_extras vacío para versión {$version['version']}");
                } elseif (!is_array($version['materias_extras'])) {
                    error_log("🚨 materias_extras no es array: " . gettype($version['materias_extras']) . " - Valor: " . print_r($version['materias_extras'], true));
                    $version['materias_extras'] = [];
                }

                // Formatear materias principales CON VERIFICACIÓN ADICIONAL
                if (is_array($version['materias_principales']) && !empty($version['materias_principales'])) {
                    foreach ($version['materias_principales'] as $mat_index => &$materia) {
                        if (!is_array($materia)) {
                            error_log("🚨 Materia principal en índice $mat_index no es array: " . print_r($materia, true));
                            continue;
                        }
                        $materia['cantidad_por_kilo'] = number_format(floatval($materia['cantidad_por_kilo'] ?? 0), 3);
                    }
                    unset($materia); // Limpiar referencia
                }

                // Formatear materias extras CON VERIFICACIÓN ADICIONAL
                if (is_array($version['materias_extras']) && !empty($version['materias_extras'])) {
                    foreach ($version['materias_extras'] as $mat_index => &$materia) {
                        if (!is_array($materia)) {
                            error_log("🚨 Materia extra en índice $mat_index no es array: " . print_r($materia, true));
                            continue;
                        }
                        $cantidad = floatval($materia['cantidad_por_kilo'] ?? 0);
                        $unidad = $materia['unidad_medida'] ?? 'unidades';
                        $materia['cantidad_formateada'] = number_format($cantidad, 3) . ' ' . $unidad;
                    }
                    unset($materia); // Limpiar referencia
                }

                $es_completo = abs($total_porcentaje - 100) <= 0.001;
                $version['es_completo'] = $es_completo;
                $version['porcentaje_faltante'] = $es_completo ? 0 : (100 - $total_porcentaje);
                $version['mensaje_completitud'] = $es_completo
                    ? 'Composición completa (100%)'
                    : 'Composición incompleta - Faltan ' . number_format(100 - $total_porcentaje, 3) . '%';
            }
            unset($version); // Limpiar referencia del foreach principal

            return [
                'success' => true,
                'id_materia_prima_objetivo' => $detalle['id_materia_prima_objetivo'],
                'materia_prima_objetivo' => $detalle['materia_prima_objetivo'],
                'versiones' => $detalle['versiones'],
                'total_versiones' => $detalle['total_versiones'],
                'total_cantidad_general' => number_format(floatval($detalle['total_cantidad_general'] ?? 0), 3),
                'total_materias_general' => array_sum(array_column($detalle['versiones'], 'total_materias_principales')) + array_sum(array_column($detalle['versiones'], 'total_materias_extras')),
                'error' => null
            ];
        } catch (Exception $e) {
            error_log("💥 Error obteniendo detalle de composición por materia prima: " . $e->getMessage());
            return [
                'success' => false,
                'id_materia_prima_objetivo' => null,
                'materia_prima_objetivo' => null,
                'versiones' => [],
                'total_versiones' => 0,
                'total_cantidad_general' => '0.000',
                'total_materias_general' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
}
