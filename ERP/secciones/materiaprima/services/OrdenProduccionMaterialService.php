<?php
require_once __DIR__ . '/../repository/OrdenProduccionMaterialRepository.php';

/**
 * Servicio para órdenes de producción de materiales
 */
class OrdenProduccionMaterialService
{
    private $ordenProduccionMaterialRepo;

    public function __construct($conexion)
    {
        $this->ordenProduccionMaterialRepo = new OrdenProduccionMaterialRepository($conexion);
    }

    /**
     * Validar datos del formulario de orden de producción
     */
    public function validarDatosFormulario($datos)
    {
        $errores = [];

        $id_materia_prima = intval($datos['id_materia_prima'] ?? 0);
        if ($id_materia_prima <= 0) {
            $errores[] = "Debe seleccionar una materia prima válida";
        }

        $version_receta = intval($datos['version_receta'] ?? 0);
        if ($version_receta <= 0) {
            $errores[] = "Debe seleccionar una versión de receta válida";
        }

        $cantidad_solicitada = trim($datos['cantidad_solicitada'] ?? '');
        if (empty($cantidad_solicitada)) {
            $errores[] = "La cantidad solicitada es obligatoria";
        } elseif (!is_numeric($cantidad_solicitada) || floatval($cantidad_solicitada) <= 0) {
            $errores[] = "La cantidad debe ser un número positivo";
        }

        $unidad_medida = trim($datos['unidad_medida'] ?? '');
        if (empty($unidad_medida)) {
            $errores[] = "La unidad de medida es obligatoria";
        }

        $fecha_orden = trim($datos['fecha_orden'] ?? '');
        if (empty($fecha_orden)) {
            $errores[] = "La fecha de orden es obligatoria";
        } elseif (!$this->validarFecha($fecha_orden)) {
            $errores[] = "La fecha de orden no es válida";
        }

        $observaciones = trim($datos['observaciones'] ?? '');
        if (strlen($observaciones) > 500) {
            $errores[] = "Las observaciones no pueden exceder 500 caracteres";
        }

        if (!empty($errores)) {
            throw new Exception(implode('. ', $errores));
        }

        return [
            'id_materia_prima' => $id_materia_prima,
            'version_receta' => $version_receta,
            'cantidad_solicitada' => floatval($cantidad_solicitada),
            'unidad_medida' => $unidad_medida,
            'fecha_orden' => $fecha_orden,
            'estado' => 'PENDIENTE', // Siempre PENDIENTE
            'observaciones' => $observaciones
        ];
    }

    /**
     * Validar formato de fecha
     */
    private function validarFecha($fecha)
    {
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        return $d && $d->format('Y-m-d') === $fecha;
    }

    /**
     * Crear nueva orden de producción
     */
    public function crearOrden($datos, $usuario = 'SISTEMA')
    {
        try {
            $datosValidados = $this->validarDatosFormulario($datos);

            // Verificar que la materia prima tenga recetas en la versión especificada
            $versiones = $this->ordenProduccionMaterialRepo->obtenerVersionesReceta($datosValidados['id_materia_prima']);
            $versionExiste = false;
            $versionInfo = null;

            foreach ($versiones as $version) {
                if ($version['version_receta'] == $datosValidados['version_receta']) {
                    $versionExiste = true;
                    $versionInfo = $version;
                    break;
                }
            }

            if (!$versionExiste) {
                throw new Exception("La versión de receta seleccionada no existe para esta materia prima");
            }

            // Verificar que la receta esté completa (100%)
            $totalPorcentaje = floatval($versionInfo['total_porcentaje']);
            if (abs($totalPorcentaje - 100) > 0.001) {
                throw new Exception("La receta versión {$datosValidados['version_receta']} no está completa (suma {$totalPorcentaje}%). No se puede crear la orden.");
            }

            $datosValidados['usuario_creacion'] = $usuario;
            $resultado = $this->ordenProduccionMaterialRepo->crear($datosValidados);

            if ($resultado['success']) {
                error_log("✅ Orden producción material creada - ID: {$resultado['id']} - Materia: {$datosValidados['id_materia_prima']}");
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("💥 Error creando orden producción material: " . $e->getMessage());
            return [
                'success' => false,
                'id' => null,
                'error' => $e->getMessage()
            ];
        }
    }



    /**
     * Eliminar orden
     */
    public function eliminarOrden($id, $usuario = 'SISTEMA')
    {
        try {
            if (!$this->ordenProduccionMaterialRepo->existeOrden($id)) {
                throw new Exception("La orden de producción con ID $id no existe");
            }

            $orden = $this->ordenProduccionMaterialRepo->obtenerPorId($id);

            // Solo permitir eliminar órdenes PENDIENTES
            if ($orden['estado'] !== 'PENDIENTE') {
                throw new Exception("Solo se pueden eliminar órdenes en estado PENDIENTE");
            }

            $resultado = $this->ordenProduccionMaterialRepo->eliminar($id, $usuario);

            if ($resultado['success']) {
                error_log("🗑️ Orden producción material eliminada - ID: $id - Usuario: $usuario");
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("💥 Error eliminando orden producción material - ID: $id - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener datos de paginación
     */
    public function obtenerDatosPaginacion($itemsPorPagina, $paginaActual, $filtros = [])
    {
        $totalRegistros = $this->ordenProduccionMaterialRepo->contarTodas($filtros);
        $totalPaginas = ceil($totalRegistros / $itemsPorPagina);
        $offset = ($paginaActual - 1) * $itemsPorPagina;

        $registros = $this->ordenProduccionMaterialRepo->obtenerTodas($itemsPorPagina, $offset, $filtros);

        return [
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'pagina_actual' => $paginaActual,
            'registros' => $registros,
            'items_por_pagina' => $itemsPorPagina
        ];
    }

    /**
     * Obtener orden por ID
     */
    public function obtenerOrdenPorId($id)
    {
        return $this->ordenProduccionMaterialRepo->obtenerPorId($id);
    }

    /**
     * Obtener orden para PDF
     */
    public function obtenerOrdenParaPDF($id)
    {
        return $this->ordenProduccionMaterialRepo->obtenerParaPDF($id);
    }

    /**
     * Obtener materias primas con recetas
     */
    public function obtenerMateriasPrimasConRecetas()
    {
        return $this->ordenProduccionMaterialRepo->obtenerMateriasPrimasConRecetas();
    }

    /**
     * Obtener versiones de receta para una materia prima
     */
    public function obtenerVersionesReceta($idMateriaPrima)
    {
        try {
            if (!is_numeric($idMateriaPrima) || intval($idMateriaPrima) <= 0) {
                throw new Exception("ID de materia prima inválido");
            }

            $versiones = $this->ordenProduccionMaterialRepo->obtenerVersionesReceta($idMateriaPrima);

            // Enriquecer datos de las versiones
            foreach ($versiones as &$version) {
                $totalPorcentaje = floatval($version['total_porcentaje']);
                $version['es_completa'] = abs($totalPorcentaje - 100) <= 0.001;
                $version['porcentaje_formateado'] = number_format($totalPorcentaje, 1);
                $version['estado_completitud'] = $version['es_completa'] ? 'Completa' : 'Incompleta';
                $version['color_estado'] = $version['es_completa'] ? 'success' : 'warning';
            }

            return [
                'success' => true,
                'versiones' => $versiones,
                'total_versiones' => count($versiones),
                'error' => null
            ];
        } catch (Exception $e) {
            error_log("💥 Error obteniendo versiones de receta: " . $e->getMessage());
            return [
                'success' => false,
                'versiones' => [],
                'total_versiones' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calcular componentes necesarios para una orden
     */
    public function calcularComponentesNecesarios($idMateriaPrima, $versionReceta, $cantidadSolicitada)
    {
        try {
            // Obtener componentes directamente de la receta
            $componentes = $this->ordenProduccionMaterialRepo->obtenerComponentesReceta($idMateriaPrima, $versionReceta);

            if (empty($componentes)) {
                throw new Exception("No se encontraron componentes para esta receta");
            }

            $componentes_calculados = [];

            foreach ($componentes as $componente) {
                $cantidad_componente = 0;

                if (!$componente['es_materia_extra']) {
                    // Componente principal: calcular basado en porcentaje
                    $porcentaje = floatval($componente['cantidad_por_kilo']);
                    $cantidad_componente = ($cantidadSolicitada * $porcentaje) / 100;
                    $unidad = 'KG';
                } else {
                    // Componente extra: multiplicar directamente
                    $cantidad_por_kilo = floatval($componente['cantidad_por_kilo']);
                    $cantidad_componente = $cantidadSolicitada * $cantidad_por_kilo;
                    $unidad = $componente['unidad_medida_extra'] ?? 'unidades';
                }

                $componentes_calculados[] = [
                    'descripcion' => $componente['componente_desc'],
                    'cantidad_original' => $componente['cantidad_por_kilo'],
                    'cantidad_necesaria' => $cantidad_componente,
                    'unidad' => $unidad,
                    'es_extra' => $componente['es_materia_extra'],
                    'tipo' => $componente['es_materia_extra'] ? 'Extra' : 'Principal'
                ];
            }

            return [
                'success' => true,
                'componentes' => $componentes_calculados,
                'error' => null
            ];
        } catch (Exception $e) {
            error_log("💥 Error calculando componentes necesarios: " . $e->getMessage());
            return [
                'success' => false,
                'componentes' => [],
                'error' => $e->getMessage()
            ];
        }
    }
}
