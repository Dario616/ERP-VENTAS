<?php
require_once __DIR__ . '/../repository/ProduccionRepository.php';

/**
 * Servicio para producciones de materiales
 * Versión modificada para manejar materiales tipo TUBO
 */
class ProduccionService
{
    private $produccionRepo;

    public function __construct($conexion)
    {
        $this->produccionRepo = new ProduccionRepository($conexion);
    }

    /**
     * NUEVA FUNCIÓN: Verificar si un material es tipo tubo
     */
    private function esTubo($nombreMaterial)
    {
        return stripos($nombreMaterial, 'tubo') !== false;
    }

    /**
     * Validar datos de producción - MODIFICADO para incluir cantidad y manejar tubos
     */
    public function validarDatosProduccion($datos, $unidadMedida = null)
    {
        $errores = [];

        $idOp = intval($datos['id_op'] ?? 0);
        if ($idOp <= 0) {
            $errores[] = "ID de orden de producción inválido";
        }

        $nombre = trim($datos['nombre'] ?? '');
        if (empty($nombre)) {
            $errores[] = "El nombre del material es obligatorio";
        }

        // NUEVO: Verificar si es tubo
        $esTubo = $this->esTubo($nombre);

        $pesoBruto = trim($datos['peso_bruto'] ?? '');
        if (empty($pesoBruto)) {
            $errores[] = "El peso bruto es obligatorio";
        } elseif (!is_numeric($pesoBruto) || floatval($pesoBruto) <= 0) {
            $errores[] = "El peso bruto debe ser un número positivo";
        }

        // MODIFICADO: Validación de tara especial para tubos
        $tara = trim($datos['tara'] ?? '');

        if ($esTubo) {
            // Para tubos, establecer tara automáticamente en 0
            $tara = '0';
            error_log("🔧 Material TUBO detectado - Tara establecida en 0 automáticamente");
        } else {
            // Para materiales normales, validar tara normalmente
            if ($tara === '') {
                $errores[] = "La tara es obligatoria";
            } elseif (!is_numeric($tara) || floatval($tara) < 0) {
                $errores[] = "La tara debe ser un número mayor o igual a cero";
            }
        }

        // Validar que peso bruto > tara
        if (is_numeric($pesoBruto) && is_numeric($tara)) {
            if (floatval($pesoBruto) <= floatval($tara)) {
                if ($esTubo && floatval($tara) == 0) {
                    // Para tubos con tara 0, solo validar que peso bruto sea mayor a 0
                    if (floatval($pesoBruto) <= 0) {
                        $errores[] = "El peso bruto debe ser mayor que cero";
                    }
                } else {
                    $errores[] = "El peso bruto debe ser mayor que la tara";
                }
            }
        }

        // VALIDACIÓN: Para unidades (UN), validar cantidad
        $cantidad = null;
        if ($unidadMedida === 'UN') {
            $cantidadInput = trim($datos['cantidad'] ?? '');
            if ($cantidadInput === '') {
                $errores[] = "La cantidad de unidades es obligatoria";
            } elseif (!is_numeric($cantidadInput) || intval($cantidadInput) <= 0) {
                $errores[] = "La cantidad de unidades debe ser un número entero positivo";
            } else {
                $cantidad = intval($cantidadInput);
            }
        }

        if (!empty($errores)) {
            throw new Exception(implode('. ', $errores));
        }

        // Calcular peso líquido
        $pesoLiquido = floatval($pesoBruto) - floatval($tara);

        $datosValidados = [
            'id_op' => $idOp,
            'nombre' => $nombre,
            'peso_bruto' => floatval($pesoBruto),
            'peso_liquido' => $pesoLiquido,
            'tara' => floatval($tara),
            'es_tubo' => $esTubo  // NUEVO: Flag para identificar tubos
        ];

        // Agregar cantidad si es requerida
        if ($cantidad !== null) {
            $datosValidados['cantidad'] = $cantidad;
        }

        // Log especial para tubos
        if ($esTubo) {
            error_log("🔧 TUBO validado - Peso bruto: {$datosValidados['peso_bruto']} - Tara: {$datosValidados['tara']} - Peso líquido: {$datosValidados['peso_liquido']}");
        }

        return $datosValidados;
    }

    /**
     * Buscar orden de producción
     */
    public function buscarOrdenProduccion($idOrden)
    {
        try {
            if (!is_numeric($idOrden) || intval($idOrden) <= 0) {
                throw new Exception("ID de orden inválido");
            }

            $orden = $this->produccionRepo->buscarOrdenProduccion($idOrden);

            if (!$orden) {
                throw new Exception("Orden de producción no encontrada");
            }

            // NUEVO: Agregar información si es tubo
            if (isset($orden['materia_prima_desc'])) {
                $orden['es_tubo'] = $this->esTubo($orden['materia_prima_desc']);
                if ($orden['es_tubo']) {
                    error_log("🔧 Orden #$idOrden detectada como TUBO: {$orden['materia_prima_desc']}");
                }
            }

            // Obtener estadísticas
            $estadisticas = $this->produccionRepo->obtenerEstadisticasOrden($idOrden);

            // Obtener producciones existentes
            $producciones = $this->produccionRepo->obtenerProduccionesOrden($idOrden);

            return [
                'success' => true,
                'orden' => $orden,
                'estadisticas' => $estadisticas,
                'producciones' => $producciones,
                'error' => null
            ];
        } catch (Exception $e) {
            error_log("💥 Error buscando orden: " . $e->getMessage());
            return [
                'success' => false,
                'orden' => null,
                'estadisticas' => null,
                'producciones' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Crear nueva producción - MODIFICADO para incluir cantidad y manejar tubos
     */
    public function crearProduccion($datos, $usuario = 'SISTEMA')
    {
        try {
            // Primero obtener la orden para conocer la unidad de medida
            $orden = $this->produccionRepo->buscarOrdenProduccion($datos['id_op']);
            if (!$orden) {
                throw new Exception("La orden de producción no existe");
            }

            // NUEVO: Log para detección de tubos
            $esTubo = $this->esTubo($datos['nombre'] ?? '');
            if ($esTubo) {
                error_log("🔧 Creando producción para material TUBO: {$datos['nombre']}");
            }

            // Validar datos con la unidad de medida
            $datosValidados = $this->validarDatosProduccion($datos, $orden['unidad_medida']);

            // Verificar que la orden esté en estado válido para producción
            if (!in_array($orden['estado'], ['PENDIENTE', 'EN_PROCESO'])) {
                throw new Exception("No se puede producir en una orden con estado: {$orden['estado']}");
            }

            $resultado = $this->produccionRepo->crearProduccion($datosValidados);

            if ($resultado['success']) {
                $logMsg = "✅ Producción creada - ID: {$resultado['id']} - Orden: {$datosValidados['id_op']} - Usuario: $usuario";
                if (isset($datosValidados['cantidad'])) {
                    $logMsg .= " - Cantidad: {$datosValidados['cantidad']} unidades";
                }
                if ($datosValidados['es_tubo']) {
                    $logMsg .= " - TUBO (tara automática: 0)";
                }
                error_log($logMsg);
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("💥 Error creando producción: " . $e->getMessage());
            return [
                'success' => false,
                'id' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar producción
     */
    public function eliminarProduccion($id, $usuario = 'SISTEMA')
    {
        try {
            if (!$this->produccionRepo->existeProduccion($id)) {
                throw new Exception("La producción con ID $id no existe");
            }

            $resultado = $this->produccionRepo->eliminarProduccion($id, $usuario);

            if ($resultado['success']) {
                error_log("🗑️ Producción eliminada - ID: $id - Usuario: $usuario");
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("💥 Error eliminando producción - ID: $id - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Buscar órdenes disponibles - MODIFICADO para incluir información de cantidad y tubos
     */
    public function buscarOrdenesDisponibles($termino = '')
    {
        try {
            $ordenes = $this->produccionRepo->buscarOrdenesDisponibles($termino);

            // Enriquecer datos
            foreach ($ordenes as &$orden) {
                $orden['porcentaje_completado'] = 0;

                // NUEVO: Detectar si es tubo
                $orden['es_tubo'] = $this->esTubo($orden['materia_prima_desc'] ?? '');

                // Calcular porcentaje según el tipo de unidad de medida
                if ($orden['unidad_medida'] === 'UN' && $orden['cantidad_solicitada'] > 0) {
                    // Para unidades, usar la cantidad de unidades producidas
                    $orden['porcentaje_completado'] = min(100, ($orden['total_unidades_producidas'] / $orden['cantidad_solicitada']) * 100);
                    $orden['progreso_texto'] = "{$orden['total_unidades_producidas']} / {$orden['cantidad_solicitada']} unidades";
                } elseif ($orden['cantidad_solicitada'] > 0) {
                    // Para peso (KG), usar el peso líquido producido
                    $orden['porcentaje_completado'] = min(100, ($orden['total_producido'] / $orden['cantidad_solicitada']) * 100);
                    $orden['progreso_texto'] = number_format($orden['total_producido'], 3) . " / " . number_format($orden['cantidad_solicitada'], 3) . " KG";
                }

                $orden['porcentaje_completado_formateado'] = number_format($orden['porcentaje_completado'], 1);

                // Color del progreso
                if ($orden['porcentaje_completado'] >= 100) {
                    $orden['color_progreso'] = 'success';
                } elseif ($orden['porcentaje_completado'] >= 75) {
                    $orden['color_progreso'] = 'info';
                } elseif ($orden['porcentaje_completado'] >= 50) {
                    $orden['color_progreso'] = 'warning';
                } else {
                    $orden['color_progreso'] = 'danger';
                }

                // Flag para identificar si requiere cantidad
                $orden['requiere_cantidad'] = ($orden['unidad_medida'] === 'UN');
            }

            return [
                'success' => true,
                'ordenes' => $ordenes,
                'total' => count($ordenes),
                'error' => null
            ];
        } catch (Exception $e) {
            error_log("💥 Error buscando órdenes: " . $e->getMessage());
            return [
                'success' => false,
                'ordenes' => [],
                'total' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener estadísticas de orden
     */
    public function obtenerEstadisticasOrden($idOrden)
    {
        return $this->produccionRepo->obtenerEstadisticasOrden($idOrden);
    }

    /**
     * Obtener producciones de una orden
     */
    public function obtenerProduccionesOrden($idOrden)
    {
        return $this->produccionRepo->obtenerProduccionesOrden($idOrden);
    }

    /**
     * Formatear peso para visualización
     */
    public function formatearPeso($peso, $decimales = 3)
    {
        return number_format(floatval($peso), $decimales, '.', ',');
    }

    /**
     * Validar si una orden puede ser procesada - MODIFICADO para tubos
     */
    public function puedeProducir($orden)
    {
        if (!$orden) {
            return ['puede' => false, 'razon' => 'Orden no encontrada'];
        }

        if (!$orden['activo']) {
            return ['puede' => false, 'razon' => 'Orden inactiva'];
        }

        if (!in_array($orden['estado'], ['PENDIENTE', 'EN_PROCESO'])) {
            return ['puede' => false, 'razon' => "Estado no válido: {$orden['estado']}"];
        }

        // NUEVO: Log especial para tubos
        if (isset($orden['materia_prima_desc']) && $this->esTubo($orden['materia_prima_desc'])) {
            error_log("🔧 Orden #{$orden['id']} es TUBO - Producción permitida con tara automática");
        }

        return ['puede' => true, 'razon' => ''];
    }

    /**
     * Verificar si una orden requiere cantidad de unidades
     */
    public function requiereCantidad($orden)
    {
        return isset($orden['unidad_medida']) && $orden['unidad_medida'] === 'UN';
    }

    /**
     * NUEVA FUNCIÓN: Verificar si una orden es de material tipo tubo
     */
    public function esMaterialTubo($orden)
    {
        return isset($orden['materia_prima_desc']) && $this->esTubo($orden['materia_prima_desc']);
    }

    /**
     * Obtener texto descriptivo para la unidad de medida
     */
    public function obtenerTextoUnidadMedida($unidadMedida)
    {
        switch ($unidadMedida) {
            case 'UN':
                return 'Unidades';
            case 'KG':
                return 'Kilogramos';
            case 'LT':
                return 'Litros';
            default:
                return $unidadMedida;
        }
    }

    /**
     * NUEVA FUNCIÓN: Obtener información especial para materiales tubo
     */
    public function obtenerInfoTubo($orden)
    {
        if (!$this->esMaterialTubo($orden)) {
            return null;
        }

        return [
            'es_tubo' => true,
            'tara_automatica' => 0,
            'descripcion' => 'Material tipo TUBO - Tara establecida automáticamente en 0 KG',
            'icono' => 'fas fa-pipe',
            'color' => 'secondary'
        ];
    }

    /**
     * NUEVA FUNCIÓN: Validar datos específicos para tubos
     */
    public function validarDatosTubo($datos)
    {
        $errores = [];

        $nombre = trim($datos['nombre'] ?? '');
        if (!$this->esTubo($nombre)) {
            $errores[] = "Esta función es solo para materiales tipo TUBO";
        }

        $pesoBruto = floatval($datos['peso_bruto'] ?? 0);
        if ($pesoBruto <= 0) {
            $errores[] = "El peso bruto debe ser mayor a 0 para tubos";
        }

        // Para tubos, la tara siempre debe ser 0
        $datos['tara'] = 0;

        if (!empty($errores)) {
            throw new Exception(implode('. ', $errores));
        }

        return [
            'id_op' => intval($datos['id_op']),
            'nombre' => $nombre,
            'peso_bruto' => $pesoBruto,
            'peso_liquido' => $pesoBruto, // Para tubos, peso líquido = peso bruto
            'tara' => 0,
            'es_tubo' => true,
            'cantidad' => isset($datos['cantidad']) ? intval($datos['cantidad']) : null
        ];
    }
}
