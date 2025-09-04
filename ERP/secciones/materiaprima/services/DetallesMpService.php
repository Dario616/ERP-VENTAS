<?php
require_once __DIR__ . '/../repository/DetallesMpRepository.php';

/**
 * Servicio que contiene la lógica de negocio para detalles de materia prima
 * ACTUALIZADO: Con manejo del campo cantidad para unidad = "Unidad"
 */
class DetallesMpService
{
    private $detallesMpRepo;

    public function __construct($conexion)
    {
        $this->detallesMpRepo = new DetallesMpRepository($conexion);
    }

    public function buscarPorCodigoBarras($barcode)
    {
        try {
            // Validar que el código no esté vacío
            if (empty($barcode) || strlen(trim($barcode)) < 3) {
                throw new Exception("El código de barras debe tener al menos 3 caracteres");
            }

            // Buscar en el repositorio
            $resultado = $this->detallesMpRepo->buscarPorCodigoBarras(trim($barcode));

            // VERIFICACIÓN MEJORADA
            if ($resultado['success'] && isset($resultado['datos']) && is_array($resultado['datos']) && !empty($resultado['datos'])) {
                $datos = $resultado['datos'];

                // Validar y limpiar datos con valores por defecto - ACTUALIZADO con cantidad
                $datosLimpios = [
                    'id' => intval($datos['id'] ?? 0),
                    'descripcion' => trim($datos['descripcion'] ?? ''),
                    'ncm' => trim($datos['ncm'] ?? ''),
                    'unidad' => trim($datos['unidad'] ?? 'KG'),
                    'peso' => floatval($datos['peso'] ?? 0),
                    'factura' => trim($datos['factura'] ?? ''),
                    'proveedor' => trim($datos['proveedor'] ?? ''),
                    'barcode' => trim($datos['barcode'] ?? ''),
                    'codigo_unico' => trim($datos['codigo_unico'] ?? ''),
                    'id_materia' => intval($datos['id_materia'] ?? 0),
                    'cantidad' => intval($datos['cantidad'] ?? 0) // NUEVO campo
                ];

                return [
                    'success' => true,
                    'datos' => $datosLimpios,
                    'error' => null
                ];
            } else {
                return [
                    'success' => false,
                    'datos' => null,
                    'error' => $resultado['error'] ?? 'Código de barras no encontrado'
                ];
            }
        } catch (Exception $e) {
            error_log("💥 Error en servicio buscando código de barras: $barcode - Error: " . $e->getMessage());
            return [
                'success' => false,
                'datos' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validar código de barras (para evitar duplicados en creación/edición)
     */
    public function validarCodigoBarras($barcode, $excluir_id = null)
    {
        try {
            if (empty($barcode) || strlen(trim($barcode)) < 3) {
                return [
                    'valido' => false,
                    'error' => 'El código de barras debe tener al menos 3 caracteres'
                ];
            }

            return [
                'valido' => true,
                'error' => null
            ];
        } catch (Exception $e) {
            error_log("💥 Error validando código de barras: $barcode - Error: " . $e->getMessage());
            return [
                'valido' => false,
                'error' => 'Error al validar el código de barras: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generar código único automáticamente CON el ID del detalle
     */
    public function generarCodigoUnico($id_materia, $id_detalle)
    {
        try {
            $fecha = date('Ymd'); // Formato YYYYMMDD
            $prefijo = "MP{$fecha}-{$id_materia}-{$id_detalle}-";

            // Obtener el siguiente número secuencial para este patrón específico
            $numeroSecuencial = $this->detallesMpRepo->obtenerSiguienteNumeroSecuencial($prefijo);

            $codigo = $prefijo . str_pad($numeroSecuencial, 6, '0', STR_PAD_LEFT);

            error_log("✨ Código único generado: $codigo (ID Detalle: $id_detalle)");
            return $codigo;
        } catch (Exception $e) {
            error_log("💥 Error generando código único: " . $e->getMessage());
            // Fallback con timestamp
            return "MP" . date('Ymd') . "-{$id_materia}-{$id_detalle}-" . str_pad(time() % 1000000, 6, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Validar datos del formulario - ACTUALIZADO con cantidad
     */
    public function validarDatosFormulario($datos, $requiere_cantidad = false)
    {
        $errores = [];

        // NUEVO: Validar cantidad si es requerida (unidad = "Unidad")
        $cantidad = trim($datos['cantidad'] ?? '');
        if ($requiere_cantidad) {
            if (empty($cantidad) || !is_numeric($cantidad) || intval($cantidad) <= 0) {
                $errores[] = "La cantidad es obligatoria y debe ser un número entero positivo para materias primas por unidad";
            }
        } elseif (!empty($cantidad)) {
            // Si se proporciona cantidad pero no es requerida, validar que sea correcta
            if (!is_numeric($cantidad) || intval($cantidad) <= 0) {
                $errores[] = "Si proporciona la cantidad, debe ser un número entero positivo";
            }
        }

        // Validar peso
        $peso = trim($datos['peso'] ?? '');
        if (!empty($peso) && (!is_numeric($peso) || floatval($peso) <= 0)) {
            $errores[] = "Si proporciona el peso, debe ser un número positivo";
        }

        // Validar código de barras si se proporciona
        $barcode = trim($datos['barcode'] ?? '');
        if (!empty($barcode)) {
            // Validar formato básico del código de barras
            if (strlen($barcode) < 3) {
                $errores[] = "El código de barras debe tener al menos 3 caracteres";
            } elseif (preg_match('/[<>"\']/', $barcode)) {
                $errores[] = "El código de barras no puede contener caracteres especiales como < > \" '";
            }
        }

        $factura = trim($datos['factura'] ?? '');
        $proveedor = trim($datos['proveedor'] ?? '');

        // Validar caracteres especiales en campos de texto
        $campos_texto = [$factura, $proveedor];
        foreach ($campos_texto as $campo) {
            if (!empty($campo) && preg_match('/[<>"\']/', $campo)) {
                $errores[] = "Los campos no pueden contener caracteres especiales como < > \" '";
                break;
            }
        }

        if (!empty($errores)) {
            throw new Exception(implode('. ', $errores));
        }

        return [
            'peso' => !empty($peso) ? floatval($peso) : 0,
            'factura' => $factura,
            'proveedor' => $proveedor,
            'barcode' => $barcode,
            'cantidad' => !empty($cantidad) ? intval($cantidad) : 0 // NUEVO campo
        ];
    }

    /**
     * Crear nuevo detalle con validaciones y generación de código - ACTUALIZADO con cantidad
     */
    public function crearDetalle($datos, $id_materia, $requiere_cantidad = false)
    {
        try {
            // Validar datos - NUEVO parámetro
            $datosValidados = $this->validarDatosFormulario($datos, $requiere_cantidad);

            // Si se proporciona código de barras, validar que no esté duplicado
            if (!empty($datosValidados['barcode'])) {
                $validacionBarcode = $this->validarCodigoBarras($datosValidados['barcode']);
                if (!$validacionBarcode['valido']) {
                    throw new Exception($validacionBarcode['error']);
                }
            }

            // Agregar ID de materia a los datos (SIN código único todavía)
            $datosValidados['id_materia'] = $id_materia;
            $datosValidados['codigo_unico'] = 'TEMPORAL'; // Código temporal

            // Crear en repositorio primero para obtener el ID
            $resultado = $this->detallesMpRepo->crear($datosValidados);

            if ($resultado['success']) {
                $id_detalle = $resultado['id'];

                // AHORA generar el código único con el ID del detalle
                $codigo_unico = $this->generarCodigoUnico($id_materia, $id_detalle);

                // Actualizar el registro con el código único correcto
                $resultadoActualizacion = $this->detallesMpRepo->actualizarCodigoUnico($id_detalle, $codigo_unico);

                if ($resultadoActualizacion['success']) {
                    $resultado['codigo_generado'] = $codigo_unico;

                    $logMessage = "✅ Detalle MP creado - ID: {$id_detalle} - Código: $codigo_unico - Materia: $id_materia";
                    if (!empty($datosValidados['barcode'])) {
                        $logMessage .= " - Barcode: {$datosValidados['barcode']}";
                    }
                    if (!empty($datosValidados['cantidad'])) {
                        $logMessage .= " - Cantidad: {$datosValidados['cantidad']}";
                    }
                    error_log($logMessage);
                } else {
                    error_log("⚠️ Detalle creado pero error actualizando código - ID: {$id_detalle}");
                    $resultado['codigo_generado'] = 'TEMPORAL';
                }
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("💥 Error en servicio creando detalle MP: " . $e->getMessage());
            return [
                'success' => false,
                'id' => null,
                'codigo_generado' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar detalle existente con validaciones - ACTUALIZADO con cantidad
     */
    public function actualizarDetalle($id, $datos, $requiere_cantidad = false)
    {
        try {
            // Verificar que existe el detalle
            if (!$this->detallesMpRepo->existeDetalle($id)) {
                throw new Exception("El detalle con ID $id no existe");
            }

            // Validar datos - NUEVO parámetro
            $datosValidados = $this->validarDatosFormulario($datos, $requiere_cantidad);

            // Si se proporciona código de barras, validar que no esté duplicado (excluyendo el actual)
            if (!empty($datosValidados['barcode'])) {
                $validacionBarcode = $this->validarCodigoBarras($datosValidados['barcode'], $id);
                if (!$validacionBarcode['valido']) {
                    throw new Exception($validacionBarcode['error']);
                }
            }

            // Actualizar en repositorio (SIN modificar el código único)
            $resultado = $this->detallesMpRepo->actualizar($id, $datosValidados);

            if ($resultado['success']) {
                $logMessage = "🔄 Detalle MP actualizado - ID: $id";
                if (!empty($datosValidados['barcode'])) {
                    $logMessage .= " - Barcode: {$datosValidados['barcode']}";
                }
                if (!empty($datosValidados['cantidad'])) {
                    $logMessage .= " - Cantidad: {$datosValidados['cantidad']}";
                }
                error_log($logMessage);
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("💥 Error en servicio actualizando detalle MP - ID: $id - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar detalle con validaciones
     */
    public function eliminarDetalle($id)
    {
        try {
            // Verificar que existe el detalle
            if (!$this->detallesMpRepo->existeDetalle($id)) {
                throw new Exception("El detalle con ID $id no existe");
            }

            // Obtener datos antes de eliminar para logging
            $detalle = $this->detallesMpRepo->obtenerPorId($id);

            // Eliminar en repositorio
            $resultado = $this->detallesMpRepo->eliminar($id);

            if ($resultado['success'] && $detalle) {
                $logMessage = "🗑️ Detalle MP eliminado - ID: $id - Código: {$detalle['codigo_unico']}";
                if (!empty($detalle['barcode'])) {
                    $logMessage .= " - Barcode: {$detalle['barcode']}";
                }
                if (!empty($detalle['cantidad'])) {
                    $logMessage .= " - Cantidad: {$detalle['cantidad']}";
                }
                error_log($logMessage);
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("💥 Error en servicio eliminando detalle MP - ID: $id - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros_afectados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener datos de paginación completos
     */
    public function obtenerDatosPaginacion($id_materia, $itemsPorPagina, $paginaActual, $filtros = [])
    {
        $totalRegistros = $this->detallesMpRepo->contarPorMateria($id_materia, $filtros);
        $totalPaginas = ceil($totalRegistros / $itemsPorPagina);
        $offset = ($paginaActual - 1) * $itemsPorPagina;

        $registros = $this->detallesMpRepo->obtenerPorMateria($id_materia, $itemsPorPagina, $offset, $filtros);

        return [
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'pagina_actual' => $paginaActual,
            'registros' => $registros,
            'items_por_pagina' => $itemsPorPagina
        ];
    }

    /**
     * Obtener datos de paginación agrupados por proveedor
     */
    public function obtenerDatosPaginacionAgrupados($id_materia, $itemsPorPagina, $paginaActual, $filtros = [])
    {
        $totalRegistros = $this->detallesMpRepo->contarProveedoresAgrupados($id_materia, $filtros);
        $totalPaginas = ceil($totalRegistros / $itemsPorPagina);
        $offset = ($paginaActual - 1) * $itemsPorPagina;

        $registros = $this->detallesMpRepo->obtenerAgrupadosPorProveedor($id_materia, $itemsPorPagina, $offset, $filtros);

        return [
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'pagina_actual' => $paginaActual,
            'registros' => $registros,
            'items_por_pagina' => $itemsPorPagina,
            'es_vista_agrupada' => true
        ];
    }

    /**
     * Obtener detalles individuales de un proveedor
     */
    public function obtenerDetallesProveedor($id_materia, $proveedor, $itemsPorPagina = null, $paginaActual = 1)
    {
        try {
            if ($itemsPorPagina !== null) {
                // Con paginación
                $totalRegistros = $this->detallesMpRepo->contarDetallesPorProveedor($id_materia, $proveedor);
                $totalPaginas = ceil($totalRegistros / $itemsPorPagina);
                $offset = ($paginaActual - 1) * $itemsPorPagina;

                $registros = $this->detallesMpRepo->obtenerDetallesPorProveedor($id_materia, $proveedor, $itemsPorPagina, $offset);

                return [
                    'success' => true,
                    'total_registros' => $totalRegistros,
                    'total_paginas' => $totalPaginas,
                    'pagina_actual' => $paginaActual,
                    'registros' => $registros,
                    'proveedor' => $proveedor,
                    'error' => null
                ];
            } else {
                // Sin paginación - todos los registros
                $registros = $this->detallesMpRepo->obtenerDetallesPorProveedor($id_materia, $proveedor);

                return [
                    'success' => true,
                    'registros' => $registros,
                    'total_registros' => count($registros),
                    'proveedor' => $proveedor,
                    'error' => null
                ];
            }
        } catch (Exception $e) {
            error_log("💥 Error obteniendo detalles de proveedor: $proveedor - Error: " . $e->getMessage());
            return [
                'success' => false,
                'registros' => [],
                'total_registros' => 0,
                'proveedor' => $proveedor,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validar proveedor para consultas
     */
    public function validarProveedor($proveedor)
    {
        if (empty($proveedor)) {
            return [
                'valido' => false,
                'error' => 'El proveedor no puede estar vacío'
            ];
        }

        // Validar caracteres especiales
        if (preg_match('/[<>"\']/', $proveedor)) {
            return [
                'valido' => false,
                'error' => 'El proveedor contiene caracteres no válidos'
            ];
        }

        return [
            'valido' => true,
            'error' => null
        ];
    }

    /**
     * Obtener detalle por ID
     */
    public function obtenerDetallePorId($id)
    {
        return $this->detallesMpRepo->obtenerPorId($id);
    }
}
