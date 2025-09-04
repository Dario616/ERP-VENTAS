<?php

class MateriaPrimaService
{
    private $materiaPrimaRepo;
    private $pesoHistorialRepo;

    // Tipos válidos para materia prima
    private const TIPOS_VALIDOS = ['Materia Prima', 'Insumo'];

    // Unidades válidas
    private const UNIDADES_VALIDAS = ['Unidad', 'Kilos'];

    public function __construct(MateriaPrimaRepository $materiaPrimaRepo, PesoEstimadoHistorialRepository $pesoHistorialRepo = null)
    {
        $this->materiaPrimaRepo = $materiaPrimaRepo;
        $this->pesoHistorialRepo = $pesoHistorialRepo;
    }

    /**
     * Crear nueva materia prima con validaciones de negocio
     * @param array $datos
     * @return array
     */
    public function crear($datos)
    {
        try {
            // Validaciones de negocio
            $this->validarDatos($datos);

            // Verificar duplicados en el mismo contexto de producción
            $produccion = intval($datos['produccion'] ?? 0);
            if ($this->materiaPrimaRepo->existeDescripcion($datos['descripcion'], null, $produccion)) {
                throw new Exception("Ya existe una materia prima con esa descripción en este contexto");
            }
            // Crear registro
            $resultado = $this->materiaPrimaRepo->crear($datos);

            if ($resultado['success']) {
                // Si tiene peso estimado y repositorio de historial disponible, registrar como primer peso
                if (!empty($datos['peso_estimado']) && $this->pesoHistorialRepo && $datos['peso_estimado'] > 0) {
                    $this->registrarCambioPesoInicial($resultado['id'], $datos['peso_estimado'], $datos['motivo_peso'] ?? 'Peso inicial al crear la materia prima');
                }

                return [
                    'success' => true,
                    'id' => $resultado['id'],
                    'error' => null
                ];
            }

            throw new Exception($resultado['error']);
        } catch (Exception $e) {
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
            // Validar que existe el registro
            $materiaPrimaActual = $this->materiaPrimaRepo->obtenerPorId($id);
            if (!$materiaPrimaActual) {
                throw new Exception("La materia prima con ID $id no existe");
            }

            // Validaciones de negocio
            $this->validarDatos($datos);

            // Obtener el contexto de producción actual
            $produccion = intval($materiaPrimaActual['produccion'] ?? 0);

            // Verificar duplicados excluyendo el registro actual
            if ($this->materiaPrimaRepo->existeDescripcion($datos['descripcion'], $id, $produccion)) {
                throw new Exception("Ya existe otra materia prima con esa descripción en este contexto");
            }
            // Verificar si cambió el peso estimado
            $pesoEstimadoAnterior = floatval($materiaPrimaActual['peso_estimado'] ?? 0);
            $pesoEstimadoNuevo = floatval($datos['peso_estimado'] ?? 0);
            $cambiosAPRegistrar = [];

            if ($pesoEstimadoAnterior != $pesoEstimadoNuevo && $this->pesoHistorialRepo) {
                $cambiosAPRegistrar[] = [
                    'peso_anterior' => $pesoEstimadoAnterior,
                    'peso_nuevo' => $pesoEstimadoNuevo,
                    'motivo' => $datos['motivo_peso'] ?? 'Actualización del peso estimado',
                    'observaciones' => $datos['observaciones_peso'] ?? ''
                ];
            }

            // Actualizar registro
            $resultado = $this->materiaPrimaRepo->actualizar($id, $datos);

            if ($resultado['success']) {
                // Registrar cambios en el historial si hay cambios de peso
                foreach ($cambiosAPRegistrar as $cambio) {
                    $this->registrarCambioPeso($id, $cambio['peso_anterior'], $cambio['peso_nuevo'], $cambio['motivo'], $cambio['observaciones']);
                }

                return [
                    'success' => true,
                    'error' => null
                ];
            }

            throw new Exception($resultado['error']);
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar solo el peso estimado
     * @param int $id
     * @param float $pesoEstimado
     * @param string $motivo
     * @param string $observaciones
     * @return array
     */
    public function actualizarPesoEstimado($id, $pesoEstimado, $motivo = '', $observaciones = '')
    {
        try {
            // Obtener peso actual
            $pesoAnterior = $this->materiaPrimaRepo->obtenerPesoEstimadoActual($id);
            if ($pesoAnterior === null) {
                throw new Exception("La materia prima con ID $id no existe");
            }

            // Validar nuevo peso
            if ($pesoEstimado < 0) {
                throw new Exception("El peso estimado no puede ser negativo");
            }

            if ($pesoEstimado == $pesoAnterior) {
                throw new Exception("El peso estimado no ha cambiado");
            }

            // Actualizar peso
            $resultado = $this->materiaPrimaRepo->actualizarPesoEstimado($id, $pesoEstimado);

            if ($resultado['success']) {
                // Registrar cambio en el historial
                if ($this->pesoHistorialRepo) {
                    $this->registrarCambioPeso($id, $pesoAnterior, $pesoEstimado, $motivo, $observaciones);
                }

                return [
                    'success' => true,
                    'peso_anterior' => $pesoAnterior,
                    'peso_nuevo' => $pesoEstimado,
                    'error' => null
                ];
            }

            throw new Exception($resultado['error']);
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * NUEVA FUNCIÓN: Actualizar solo la cantidad
     * @param int $id
     * @param int $cantidad
     * @param string $motivo
     * @param string $observaciones
     * @return array
     */
    public function actualizarCantidad($id, $cantidad, $motivo = '', $observaciones = '')
    {
        try {
            // Obtener cantidad actual
            $cantidadAnterior = $this->materiaPrimaRepo->obtenerCantidadActual($id);
            if ($cantidadAnterior === null) {
                throw new Exception("La materia prima con ID $id no existe");
            }

            // Validar nueva cantidad
            if ($cantidad < 0) {
                throw new Exception("La cantidad no puede ser negativa");
            }

            if ($cantidad == $cantidadAnterior) {
                throw new Exception("La cantidad no ha cambiado");
            }

            // Actualizar cantidad
            $resultado = $this->materiaPrimaRepo->actualizarCantidad($id, $cantidad);

            if ($resultado['success']) {
                // Registrar cambio en el historial (usando el método de peso pero con cantidad)
                if ($this->pesoHistorialRepo) {
                    $this->registrarCambioCantidad($id, $cantidadAnterior, $cantidad, $motivo, $observaciones);
                }

                return [
                    'success' => true,
                    'cantidad_anterior' => $cantidadAnterior,
                    'cantidad_nueva' => $cantidad,
                    'error' => null
                ];
            }

            throw new Exception($resultado['error']);
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Registrar cambio de peso en el historial
     * @param int $materiaPrimaId
     * @param float $pesoAnterior
     * @param float $pesoNuevo
     * @param string $motivo
     * @param string $observaciones
     * @return void
     */
    private function registrarCambioPeso($materiaPrimaId, $pesoAnterior, $pesoNuevo, $motivo = '', $observaciones = '')
    {
        if (!$this->pesoHistorialRepo) {
            return;
        }

        $datos = [
            'materia_prima_id' => $materiaPrimaId,
            'peso_anterior' => $pesoAnterior,
            'peso_nuevo' => $pesoNuevo,
            'usuario_id' => $_SESSION['id_usuario'] ?? null,
            'motivo' => !empty($motivo) ? $motivo : 'Actualización del peso estimado',
            'observaciones' => $observaciones
        ];

        $resultado = $this->pesoHistorialRepo->registrarCambio($datos);

        if (!$resultado['success']) {
            error_log("💥 Error registrando cambio de peso en historial: " . $resultado['error']);
        }
    }

    /**
     * NUEVA FUNCIÓN: Registrar cambio de cantidad en el historial
     * @param int $materiaPrimaId
     * @param int $cantidadAnterior
     * @param int $cantidadNueva
     * @param string $motivo
     * @param string $observaciones
     * @return void
     */
    private function registrarCambioCantidad($materiaPrimaId, $cantidadAnterior, $cantidadNueva, $motivo = '', $observaciones = '')
    {
        if (!$this->pesoHistorialRepo) {
            return;
        }

        // Usamos el sistema de historial de peso pero para cantidad
        // Podríamos crear una tabla separada para cantidad, pero por simplicidad usamos la misma
        $datos = [
            'materia_prima_id' => $materiaPrimaId,
            'peso_anterior' => floatval($cantidadAnterior), // Convertimos cantidad a float para compatibilidad
            'peso_nuevo' => floatval($cantidadNueva),
            'usuario_id' => $_SESSION['id_usuario'] ?? null,
            'motivo' => !empty($motivo) ? $motivo : 'Actualización de cantidad',
            'observaciones' => $observaciones . ' (Cambio de cantidad de unidades)'
        ];

        $resultado = $this->pesoHistorialRepo->registrarCambio($datos);

        if (!$resultado['success']) {
            error_log("💥 Error registrando cambio de cantidad en historial: " . $resultado['error']);
        }
    }

    /**
     * Registrar peso inicial (cuando se crea la materia prima)
     * @param int $materiaPrimaId
     * @param float $pesoInicial
     * @param string $motivo
     * @return void
     */
    private function registrarCambioPesoInicial($materiaPrimaId, $pesoInicial, $motivo = 'Peso inicial')
    {
        $this->registrarCambioPeso($materiaPrimaId, 0.00, $pesoInicial, $motivo, 'Peso estimado establecido al crear la materia prima');
    }

    /**
     * Obtener historial de cambios del peso estimado
     * @param int $materiaPrimaId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function obtenerHistorialPesoEstimado($materiaPrimaId, $limit = 50, $offset = 0)
    {
        if (!$this->pesoHistorialRepo) {
            return [];
        }

        return $this->pesoHistorialRepo->obtenerHistorialPorMateriaPrima($materiaPrimaId, $limit, $offset);
    }

    /**
     * Obtener estadísticas de cambios del peso estimado
     * @param int $materiaPrimaId
     * @return array
     */
    public function obtenerEstadisticasPesoEstimado($materiaPrimaId = null)
    {
        if (!$this->pesoHistorialRepo) {
            return [
                'total_cambios' => 0,
                'cambio_promedio' => 0,
                'mayor_aumento' => 0,
                'mayor_disminucion' => 0,
                'ultimo_cambio' => null
            ];
        }

        return $this->pesoHistorialRepo->obtenerEstadisticasCambios($materiaPrimaId);
    }

    /**
     * Eliminar materia prima
     * @param int $id
     * @return array
     */
    public function eliminar($id)
    {
        try {
            // Verificar que existe
            $materiaPrima = $this->materiaPrimaRepo->obtenerPorId($id);
            if (!$materiaPrima) {
                throw new Exception("La materia prima con ID $id no existe");
            }

            // Eliminar registro (el historial se eliminará en cascada por la foreign key)
            $resultado = $this->materiaPrimaRepo->eliminar($id);

            if ($resultado['success']) {
                return [
                    'success' => true,
                    'data' => $materiaPrima,
                    'error' => null
                ];
            }

            throw new Exception($resultado['error']);
        } catch (Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener materia prima por ID
     * @param int $id
     * @return array|null
     */
    public function obtenerPorId($id)
    {
        return $this->materiaPrimaRepo->obtenerPorId($id);
    }

    /**
     * Buscar materias primas con paginación
     * @param int $itemsPorPagina
     * @param int $paginaActual
     * @param array $filtros
     * @return array
     */
    public function obtenerDatosPaginacion($itemsPorPagina, $paginaActual, $filtros = [])
    {
        return $this->materiaPrimaRepo->obtenerDatosPaginacion($itemsPorPagina, $paginaActual, $filtros);
    }

    /**
     * Buscar por descripción
     * @param string $termino
     * @param int $produccion
     * @return array
     */
    public function buscarPorDescripcion($termino, $produccion = 0)
    {
        return $this->materiaPrimaRepo->buscarPorDescripcion($termino, $produccion);
    }

    /**
     * Buscar por NCM
     * @param string $termino
     * @param int $produccion
     * @return array
     */
    public function buscarPorNCM($termino, $produccion = 0)
    {
        return $this->materiaPrimaRepo->buscarPorNCM($termino, $produccion);
    }

    /**
     * Buscar por tipo
     * @param string $tipo
     * @param int $produccion
     * @return array
     */
    public function buscarPorTipo($tipo, $produccion = 0)
    {
        if (!$this->esTipoValido($tipo)) {
            throw new Exception("Tipo de materia prima no válido: $tipo");
        }

        return $this->materiaPrimaRepo->buscarPorTipo($tipo, $produccion);
    }

    /**
     * Buscar por unidad
     * @param string $unidad
     * @param int $produccion
     * @return array
     */
    public function buscarPorUnidad($unidad, $produccion = 0)
    {
        if (!$this->esUnidadValida($unidad)) {
            throw new Exception("Unidad de medida no válida: $unidad");
        }

        return $this->materiaPrimaRepo->buscarPorUnidad($unidad, $produccion);
    }

    /**
     * Obtener todas las materias primas por tipo
     * @param string $tipo
     * @param int $produccion
     * @return array
     */
    public function obtenerPorTipo($tipo, $produccion = 0)
    {
        if (!$this->esTipoValido($tipo)) {
            throw new Exception("Tipo de materia prima no válido: $tipo");
        }

        return $this->materiaPrimaRepo->obtenerPorTipo($tipo, $produccion);
    }

    /**
     * Obtener todas las materias primas por unidad
     * @param string $unidad
     * @param int $produccion
     * @return array
     */
    public function obtenerPorUnidad($unidad, $produccion = 0)
    {
        if (!$this->esUnidadValida($unidad)) {
            throw new Exception("Unidad de medida no válida: $unidad");
        }

        return $this->materiaPrimaRepo->obtenerPorUnidad($unidad, $produccion);
    }

    /**
     * Obtener todas las materias primas por valor de producción
     * @param int $produccion
     * @return array
     */
    public function obtenerPorProduccion($produccion)
    {
        return $this->materiaPrimaRepo->obtenerPorProduccion($produccion);
    }

    /**
     * Obtener todas ordenadas para selects
     * @param int $produccion
     * @return array
     */
    public function obtenerTodasOrdenadas($produccion = 0)
    {
        return $this->materiaPrimaRepo->obtenerTodasOrdenadas($produccion);
    }

    /**
     * Verificar si existe descripción
     * @param string $descripcion
     * @param int $excluirId
     * @param int $produccion
     * @return bool
     */
    public function existeDescripcion($descripcion, $excluirId = null, $produccion = 0)
    {
        return $this->materiaPrimaRepo->existeDescripcion($descripcion, $excluirId, $produccion);
    }

    /**
     * Verificar si existe NCM
     * @param string $ncm
     * @param int $excluirId
     * @param int $produccion
     * @return bool
     */
    public function existeNCM($ncm, $excluirId = null, $produccion = 0)
    {
        return $this->materiaPrimaRepo->existeNCM($ncm, $excluirId, $produccion);
    }

    /**
     * Validar datos del formulario
     * @param array $datos
     * @throws Exception
     */
    private function validarDatos($datos)
    {
        $errores = [];

        // Validar descripción (obligatorio)
        $descripcion = trim($datos['descripcion'] ?? '');
        if (empty($descripcion)) {
            $errores[] = "La descripción es obligatoria";
        } elseif (strlen($descripcion) < 3) {
            $errores[] = "La descripción debe tener al menos 3 caracteres";
        } elseif (strlen($descripcion) > 500) {
            $errores[] = "La descripción no puede exceder 500 caracteres";
        }

        // Validar tipo (obligatorio)
        $tipo = trim($datos['tipo'] ?? '');
        if (empty($tipo)) {
            $errores[] = "El tipo es obligatorio";
        } elseif (!$this->esTipoValido($tipo)) {
            $errores[] = "El tipo debe ser 'Materia Prima' o 'Insumo'";
        }

        // Validar unidad (obligatorio)
        $unidad = trim($datos['unidad'] ?? '');
        if (empty($unidad)) {
            $errores[] = "La unidad es obligatoria";
        } elseif (!$this->esUnidadValida($unidad)) {
            $errores[] = "La unidad debe ser 'Unidad' o 'Kilos'";
        }

        // Validar cantidad (opcional)
        if (isset($datos['cantidad']) && $datos['cantidad'] !== '') {
            $cantidad = intval($datos['cantidad']);
            if ($cantidad < 0) {
                $errores[] = "La cantidad no puede ser negativa";
            } elseif ($cantidad > 999999) {
                $errores[] = "La cantidad no puede exceder 999,999";
            }
        }

        // Validar peso estimado (opcional)
        if (isset($datos['peso_estimado']) && $datos['peso_estimado'] !== '') {
            $peso = floatval($datos['peso_estimado']);
            if ($peso < 0) {
                $errores[] = "El peso estimado no puede ser negativo";
            } elseif ($peso > 999999.99) {
                $errores[] = "El peso estimado no puede exceder 999,999.99 kg";
            }
        }

        // Validar NCM (opcional)
        $ncm = trim($datos['ncm'] ?? '');
        if (!empty($ncm)) {
            // Validar longitud del NCM
            if (strlen($ncm) > 100) {
                $errores[] = "El código NCM no puede exceder 100 caracteres";
            }

            // Validar formato básico del NCM (solo números y puntos)
            if (!preg_match('/^[0-9\.]+$/', $ncm)) {
                $errores[] = "El código NCM solo puede contener números y puntos";
            }

            // Validar longitud típica del NCM (8 dígitos)
            $ncm_limpio = str_replace('.', '', $ncm);
            if (strlen($ncm_limpio) < 4 || strlen($ncm_limpio) > 10) {
                $errores[] = "El código NCM debe tener entre 4 y 10 dígitos";
            }
        }

        // Validar caracteres especiales peligrosos en descripción
        if (preg_match('/[<>"\']/', $descripcion)) {
            $errores[] = "La descripción no puede contener caracteres especiales como < > \" '";
        }

        // Validar caracteres especiales peligrosos en NCM
        if (!empty($ncm) && preg_match('/[<>"\'\;\(\)\[\]\{\}]/', $ncm)) {
            $errores[] = "El código NCM no puede contener caracteres especiales peligrosos";
        }

        // Validar motivo del peso (opcional)
        if (isset($datos['motivo_peso']) && strlen($datos['motivo_peso']) > 500) {
            $errores[] = "El motivo del cambio de peso no puede exceder 500 caracteres";
        }

        // Validar valor de producción
        if (isset($datos['produccion'])) {
            $produccion = intval($datos['produccion']);
            if ($produccion < 0 || $produccion > 1) {
                $errores[] = "El valor de producción debe ser 0 o 1";
            }
        }

        if (!empty($errores)) {
            throw new Exception(implode('. ', $errores));
        }
    }

    /**
     * Verificar si un tipo es válido
     * @param string $tipo
     * @return bool
     */
    public function esTipoValido($tipo)
    {
        return in_array($tipo, self::TIPOS_VALIDOS);
    }

    /**
     * Verificar si una unidad es válida
     * @param string $unidad
     * @return bool
     */
    public function esUnidadValida($unidad)
    {
        return in_array($unidad, self::UNIDADES_VALIDAS);
    }

    /**
     * Obtener tipos válidos
     * @return array
     */
    public function getTiposValidos()
    {
        return self::TIPOS_VALIDOS;
    }

    /**
     * Obtener unidades válidas
     * @return array
     */
    public function getUnidadesValidas()
    {
        return self::UNIDADES_VALIDAS;
    }

    /**
     * Formatear código NCM
     * @param string $ncm
     * @return string
     */
    public function formatearNCM($ncm)
    {
        if (empty($ncm)) {
            return '';
        }

        // Limpiar el NCM de caracteres no numéricos excepto puntos
        $ncm_limpio = preg_replace('/[^0-9\.]/', '', $ncm);

        // Si no tiene puntos y tiene 8 dígitos, formatear como XXXX.XX.XX
        if (strpos($ncm_limpio, '.') === false && strlen($ncm_limpio) == 8) {
            return substr($ncm_limpio, 0, 4) . '.' . substr($ncm_limpio, 4, 2) . '.' . substr($ncm_limpio, 6, 2);
        }

        return $ncm_limpio;
    }

    /**
     * Validar formato específico de NCM brasileño
     * @param string $ncm
     * @return bool
     */
    public function validarFormatoNCM($ncm)
    {
        if (empty($ncm)) {
            return true; // NCM vacío es válido
        }

        // Limpiar el NCM
        $ncm_limpio = str_replace('.', '', $ncm);

        // Verificar que solo contenga números
        if (!ctype_digit($ncm_limpio)) {
            return false;
        }

        // Verificar longitud (NCM brasileño tiene 8 dígitos)
        return strlen($ncm_limpio) >= 4 && strlen($ncm_limpio) <= 10;
    }

    /**
     * Obtener estadísticas por unidad
     * @param string $unidad
     * @param int $produccion
     * @return array
     */
    public function obtenerStockPorUnidad($unidad = null, $produccion = 0)
    {
        return $this->materiaPrimaRepo->obtenerEstadisticasPorUnidad($unidad, $produccion);
    }

    /**
     * Exportar datos para reportes
     * @param array $filtros
     * @return array
     */
    public function exportarDatos($filtros = [])
    {
        // Obtener todos los datos sin paginación
        $datos = $this->materiaPrimaRepo->obtenerTodas(99999, 0, $filtros);

        $exportData = [];
        foreach ($datos as $registro) {
            $exportData[] = [
                'ID' => $registro['id'],
                'Descripcion' => $registro['descripcion'],
                'Tipo' => $registro['tipo'] ?? 'Sin definir',
                'Unidad' => $registro['unidad'] ?? 'Sin definir',
                'Cantidad' => $registro['cantidad'] ?? 0,
                'NCM' => $registro['ncm'] ?? '',
                'Peso_Estimado_kg' => $registro['peso_estimado'] ?? 0,
                'Peso_Registrado_kg' => $registro['peso_registrado'] ?? 0,
                'Fecha_Movimiento' => $registro['fecha_movimiento'] ?? '',
                'Produccion' => $registro['produccion'] == 1 ? 'Sí' : 'No',
                'Stock_Total_Estimado_kg' => ($registro['peso_estimado'] ?? 0) * ($registro['cantidad'] ?? 0)
            ];
        }

        return $exportData;
    }

    /**
     * Obtener materiales con stock bajo
     * @param int $umbralMinimo
     * @param int $produccion
     * @return array
     */
    public function obtenerStockBajo($umbralMinimo = 5, $produccion = 0)
    {
        $datos = $this->materiaPrimaRepo->obtenerTodasOrdenadas($produccion);
        $stockBajo = [];

        foreach ($datos as $registro) {
            $cantidad = intval($registro['cantidad'] ?? 0);

            if ($cantidad <= $umbralMinimo) {
                $stockBajo[] = array_merge($registro, [
                    'cantidad_disponible' => $cantidad,
                    'umbral_minimo' => $umbralMinimo
                ]);
            }
        }

        return $stockBajo;
    }

    /**
     * Transferir material de configuración a producción
     * @param int $id
     * @return array
     */
    public function transferirAProduccion($id)
    {
        try {
            // Verificar que el material existe y está en configuración
            $material = $this->materiaPrimaRepo->obtenerPorId($id);
            if (!$material) {
                throw new Exception("El material con ID $id no existe");
            }

            if (intval($material['produccion']) !== 0) {
                throw new Exception("El material ya está en producción");
            }

            // Crear copia en producción
            $datosProduccion = [
                'descripcion' => $material['descripcion'],
                'tipo' => $material['tipo'],
                'ncm' => $material['ncm'],
                'unidad' => $material['unidad'],
                'cantidad' => $material['cantidad'],
                'peso_estimado' => $material['peso_estimado'],
                'produccion' => 1
            ];

            $resultado = $this->materiaPrimaRepo->crear($datosProduccion);

            if ($resultado['success']) {
                return [
                    'success' => true,
                    'id_produccion' => $resultado['id'],
                    'error' => null
                ];
            }

            throw new Exception($resultado['error']);
        } catch (Exception $e) {
            return [
                'success' => false,
                'id_produccion' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener resumen por contexto de producción
     * @return array
     */
    public function obtenerResumenPorContexto()
    {
        try {
            $configuracion = $this->materiaPrimaRepo->obtenerEstadisticasPorUnidad(null, 0);
            $produccion = $this->materiaPrimaRepo->obtenerEstadisticasPorUnidad(null, 1);

            return [
                'configuracion' => $configuracion,
                'produccion' => $produccion,
                'total_configuracion' => array_sum(array_column($configuracion, 'total_items')),
                'total_produccion' => array_sum(array_column($produccion, 'total_items'))
            ];
        } catch (Exception $e) {
            error_log("💥 Error obteniendo resumen por contexto: " . $e->getMessage());
            return [
                'configuracion' => [],
                'produccion' => [],
                'total_configuracion' => 0,
                'total_produccion' => 0
            ];
        }
    }
}
