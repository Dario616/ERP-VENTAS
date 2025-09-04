<?php

class ProductoService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function obtenerProductos($filtros = [])
    {
        $productos = $this->repository->obtenerProductos($filtros);
        return array_map([$this, 'enriquecerDatosProducto'], $productos);
    }

    public function obtenerDatosPaginacion($filtros = [])
    {
        $registros_por_pagina = $filtros['registros_por_pagina'] ?? 10;
        $pagina_actual = $filtros['pagina'] ?? 1;
        $total_registros = $this->repository->contarProductos($filtros);
        $total_paginas = ceil($total_registros / $registros_por_pagina);

        return [
            'total_registros' => $total_registros,
            'total_paginas' => $total_paginas,
            'pagina_actual' => $pagina_actual,
            'registros_por_pagina' => $registros_por_pagina
        ];
    }

    public function obtenerProductoPorId($id)
    {
        if (!$this->validarId($id)) {
            throw new Exception('ID de producto inválido');
        }

        $producto = $this->repository->obtenerProductoPorId($id);

        if (!$producto) {
            throw new Exception('Producto no encontrado');
        }

        return $this->enriquecerDatosProducto($producto);
    }

    public function crearProducto($datos, $unidades_medida = [])
    {
        $errores = $this->validarDatosProducto($datos);

        if (!empty($errores)) {
            return ['success' => false, 'errores' => $errores];
        }

        if (empty($unidades_medida)) {
            return ['success' => false, 'errores' => ['Debe seleccionar al menos una unidad de medida']];
        }

        $conexion = $this->repository->getConexion();

        try {
            $datosLimpios = $this->prepararDatosParaGuardar($datos);

            // Calcular peso automático si es posible
            $pesoAutomatico = $this->calcularPesoAutomatico($datosLimpios['descripcion']);
            if ($pesoAutomatico !== null) {
                $datosLimpios['cantidad'] = $pesoAutomatico;
                error_log("Peso automático calculado para '{$datosLimpios['descripcion']}': {$pesoAutomatico}");
            }

            $conexion->beginTransaction();

            $idProducto = $this->repository->crearProducto($datosLimpios);

            if (!$idProducto) {
                throw new Exception('Error al crear el producto');
            }

            foreach ($unidades_medida as $um) {
                if (!$this->repository->insertarUnidadMedidaProducto($idProducto, $um)) {
                    throw new Exception('Error al insertar unidades de medida');
                }
            }

            $conexion->commit();

            $mensaje = 'Producto registrado correctamente';
            if ($pesoAutomatico !== null) {
                $mensaje .= ' (Peso calculado automáticamente: ' . number_format($pesoAutomatico, 3, ',', '.') . ' kg)';
            }

            return [
                'success' => true,
                'mensaje' => $mensaje
            ];
        } catch (Exception $e) {
            $conexion->rollBack();
            error_log("Error en crearProducto: " . $e->getMessage());
            return ['success' => false, 'errores' => ['Error al registrar el producto: ' . $e->getMessage()]];
        }
    }

    public function actualizarProducto($id, $datos, $unidades_medida = [])
    {
        if (!$this->validarId($id)) {
            return ['success' => false, 'errores' => ['ID de producto inválido']];
        }

        $errores = $this->validarDatosProducto($datos, $id);

        if (!empty($errores)) {
            return ['success' => false, 'errores' => $errores];
        }

        if (empty($unidades_medida)) {
            return ['success' => false, 'errores' => ['Debe seleccionar al menos una unidad de medida']];
        }

        $conexion = $this->repository->getConexion();

        try {
            $datosLimpios = $this->prepararDatosParaActualizar($datos);

            // Calcular peso automático si es posible
            $pesoAutomatico = $this->calcularPesoAutomatico($datosLimpios['descripcion']);
            if ($pesoAutomatico !== null) {
                $datosLimpios['cantidad'] = $pesoAutomatico;
                error_log("Peso automático recalculado para '{$datosLimpios['descripcion']}': {$pesoAutomatico}");
            }

            $conexion->beginTransaction();

            if (!$this->repository->actualizarProducto($id, $datosLimpios)) {
                throw new Exception('Error al actualizar el producto');
            }

            if (!$this->repository->eliminarUnidadesMedidaProducto($id)) {
                throw new Exception('Error al eliminar unidades de medida actuales');
            }

            foreach ($unidades_medida as $um) {
                if (!$this->repository->insertarUnidadMedidaProducto($id, $um)) {
                    throw new Exception('Error al insertar unidades de medida');
                }
            }

            $conexion->commit();

            $mensaje = 'Producto actualizado correctamente';
            if ($pesoAutomatico !== null) {
                $mensaje .= ' (Peso recalculado automáticamente: ' . number_format($pesoAutomatico, 3, ',', '.') . ' kg)';
            }

            return [
                'success' => true,
                'mensaje' => $mensaje
            ];
        } catch (Exception $e) {
            $conexion->rollBack();
            error_log("Error en actualizarProducto: " . $e->getMessage());
            return ['success' => false, 'errores' => ['Error al actualizar el producto: ' . $e->getMessage()]];
        }
    }

    public function eliminarProducto($id)
    {
        if (!$this->validarId($id)) {
            return ['success' => false, 'error' => 'ID de producto inválido'];
        }

        $conexion = $this->repository->getConexion();

        try {
            $conexion->beginTransaction();

            $this->repository->eliminarUnidadesMedidaProducto($id);

            if (!$this->repository->eliminarProducto($id)) {
                throw new Exception('Error al eliminar el producto');
            }

            $conexion->commit();

            return ['success' => true, 'mensaje' => 'Producto eliminado correctamente'];
        } catch (Exception $e) {
            $conexion->rollBack();
            error_log("Error en eliminarProducto: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al eliminar el producto: ' . $e->getMessage()];
        }
    }

    /**
     * Calcula automáticamente el peso del producto basándose en su descripción
     * Busca patrones de gramatura (g/m²), ancho (cm) y rollo (metros)
     * Fórmula: gramatura * (ancho/100) * rollo / 1000
     */
    public function calcularPesoAutomatico($descripcion)
    {
        // Verificar si la descripción contiene g/m²
        if (!preg_match('/g\/m²/i', $descripcion)) {
            return null;
        }

        try {
            // Extraer gramatura (número antes de g/m²)
            $gramatura = 0;
            if (preg_match('/(\d+[,.]?\d*)\s*g\/m²/i', $descripcion, $matches)) {
                $gramatura = (float) str_replace(',', '.', preg_replace('/[^\d.,]/', '', $matches[1]));
            }

            // Extraer ancho (patrón: Ancho XXX cm)
            $ancho = 0;
            if (preg_match('/Ancho\s+(\d+[,.]?\d*)\s*cm/i', $descripcion, $matches)) {
                $ancho = (float) str_replace(',', '.', preg_replace('/[^\d.,]/', '', $matches[1]));
            }

            // Extraer rollo (patrón: Rollo de XXX metros)
            $rollo = 0;
            if (preg_match('/Rollo\s+de\s+(\d+[,.]?\d*)\s*metros/i', $descripcion, $matches)) {
                $rollo = (float) str_replace(',', '.', preg_replace('/[^\d.,]/', '', $matches[1]));
            }

            // Si algún valor es 0, no se puede calcular
            if ($gramatura <= 0 || $ancho <= 0 || $rollo <= 0) {
                error_log("No se pudo calcular peso automático - Gramatura: {$gramatura}, Ancho: {$ancho}, Rollo: {$rollo}");
                return null;
            }

            // Calcular peso: gramatura * (ancho/100) * rollo / 1000
            $peso = round($gramatura * ($ancho / 100.0) * $rollo / 1000.0, 3);

            error_log("Cálculo automático - Descripción: '{$descripcion}' | Gramatura: {$gramatura} | Ancho: {$ancho} | Rollo: {$rollo} | Peso: {$peso}");

            return $peso;
        } catch (Exception $e) {
            error_log("Error calculando peso automático: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verifica si una descripción puede tener peso calculado automáticamente
     */
    public function puedeCalcularPesoAutomatico($descripcion)
    {
        return $this->calcularPesoAutomatico($descripcion) !== null;
    }

    public function obtenerTipos()
    {
        return $this->repository->obtenerTipos();
    }

    public function obtenerUnidadesMedidaDisponibles()
    {
        return $this->repository->obtenerUnidadesMedidaDisponibles();
    }

    public function obtenerUnidadesMedidaProducto($idProducto)
    {
        if (!$this->validarId($idProducto)) {
            return [];
        }

        return $this->repository->obtenerUnidadesMedidaProducto($idProducto);
    }

    public function obtenerTiposUnicos()
    {
        return $this->repository->obtenerTiposUnicos();
    }

    public function obtenerProductosParaCatalogo()
    {
        $productos = $this->repository->obtenerProductosParaCatalogo();

        $productos_por_tipo = [];
        foreach ($productos as $producto) {
            $tipo = $producto['tipo'] ?: 'Sin categoría';
            if (!isset($productos_por_tipo[$tipo])) {
                $productos_por_tipo[$tipo] = [];
            }
            $productos_por_tipo[$tipo][] = $this->enriquecerDatosProducto($producto);
        }

        ksort($productos_por_tipo);

        return $productos_por_tipo;
    }

    public function procesarImagen($archivo)
    {
        if (!isset($archivo) || $archivo['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $nombreimg = $archivo['name'];
        $tipoimg = $archivo['type'];
        $tmpName = $archivo['tmp_name'];

        $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($tipoimg, $tiposPermitidos)) {
            throw new Exception('Tipo de archivo no permitido. Solo se aceptan JPG, PNG y GIF.');
        }

        if ($archivo['size'] > 2 * 1024 * 1024) {
            throw new Exception('El archivo es demasiado grande. Tamaño máximo: 2MB.');
        }

        $imgData = file_get_contents($tmpName);
        $base64img = base64_encode($imgData);

        return [
            'nombreimg' => $nombreimg,
            'tipoimg' => $tipoimg,
            'img' => $imgData,
            'base64img' => $base64img
        ];
    }

    private function validarDatosProducto($datos, $idExcluir = null)
    {
        $errores = [];

        if (empty(trim($datos['descripcion'] ?? ''))) {
            $errores[] = "La descripción es obligatoria.";
        } else {
            $descripcion = trim($datos['descripcion']);
            if ($this->repository->existeDescripcion($descripcion, $idExcluir)) {
                $errores[] = "Ya existe un producto con esta descripción.";
            }
        }

        $codigobr = trim($datos['codigobr'] ?? '');
        if (empty($codigobr)) {
            $errores[] = "El código de barras es obligatorio.";
        }

        if (empty(trim($datos['tipo'] ?? ''))) {
            $errores[] = "El tipo de producto es obligatorio.";
        }

        // Solo validar cantidad manualmente si no se puede calcular automáticamente
        $descripcion = trim($datos['descripcion'] ?? '');
        if (!$this->puedeCalcularPesoAutomatico($descripcion)) {
            $cantidad = $this->limpiarNumero($datos['cantidad'] ?? '');
            if ($cantidad <= 0) {
                $errores[] = "El peso líquido debe ser un valor numérico mayor a 0.";
            }
        }

        if (empty(trim($datos['ncm'] ?? ''))) {
            $errores[] = "El código NCM es obligatorio.";
        }

        return $errores;
    }

    private function validarId($id)
    {
        return is_numeric($id) && $id > 0;
    }

    private function prepararDatosParaGuardar($datos)
    {
        $datosLimpios = [
            'descripcion' => trim($datos['descripcion']),
            'codigobr' => trim($datos['codigobr']),
            'tipo' => trim($datos['tipo']),
            'cantidad' => $this->limpiarNumero($datos['cantidad']),
            'ncm' => trim($datos['ncm']),
            'nombreimg' => null,
            'tipoimg' => null,
            'img' => null,
            'base64img' => null
        ];

        if (isset($datos['imagen_data'])) {
            $datosLimpios = array_merge($datosLimpios, $datos['imagen_data']);
        }

        return $datosLimpios;
    }

    private function prepararDatosParaActualizar($datos)
    {
        $datosLimpios = [
            'descripcion' => trim($datos['descripcion']),
            'codigobr' => trim($datos['codigobr']),
            'tipo' => trim($datos['tipo']),
            'cantidad' => $this->limpiarNumero($datos['cantidad']),
            'ncm' => trim($datos['ncm'])
        ];

        if (isset($datos['nueva_imagen'])) {
            $datosLimpios['nueva_imagen'] = $datos['nueva_imagen'];
            if ($datos['nueva_imagen'] && isset($datos['imagen_data'])) {
                $datosLimpios = array_merge($datosLimpios, $datos['imagen_data']);
            }
        }

        if (isset($datos['eliminar_imagen'])) {
            $datosLimpios['eliminar_imagen'] = $datos['eliminar_imagen'];
        }

        return $datosLimpios;
    }

    private function limpiarNumero($numero)
    {
        if (empty($numero)) {
            return 0;
        }

        $numero = trim($numero);

        if (strpos($numero, ',') !== false) {
            $numero = str_replace('.', '', $numero);
            $numero = str_replace(',', '.', $numero);
        }

        return is_numeric($numero) ? (float)$numero : 0;
    }

    private function enriquecerDatosProducto($producto)
    {
        if (isset($producto['cantidad'])) {
            $producto['cantidad_formateada'] = number_format((float)$producto['cantidad'], 2, ',', '.');
        } else {
            $producto['cantidad_formateada'] = '-';
        }

        $producto['tiene_imagen'] = !empty($producto['base64img']);

        $producto['ncm_formateado'] = !empty($producto['ncm']) ? $producto['ncm'] : 'N/A';

        // Indicar si el peso fue calculado automáticamente
        $producto['peso_automatico'] = $this->puedeCalcularPesoAutomatico($producto['descripcion'] ?? '');

        return $producto;
    }
}
