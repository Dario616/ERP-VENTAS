<?php


class ConfigService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function obtenerCreditos()
    {
        return $this->repository->obtenerCreditos();
    }

    public function obtenerCredito($id)
    {
        $this->validarId($id);
        $credito = $this->repository->obtenerCredito($id);

        if (!$credito) {
            throw new Exception("Crédito no encontrado");
        }

        return $credito;
    }

    public function crearCredito($datos)
    {
        $descripcion = trim($datos['descripcion'] ?? '');

        $errores = $this->validarCredito($descripcion);

        if (!empty($errores)) {
            return [
                'success' => false,
                'errores' => $errores
            ];
        }

        if ($this->repository->existeCreditoDescripcion($descripcion)) {
            return [
                'success' => false,
                'errores' => ['Ya existe un crédito con esa descripción']
            ];
        }

        try {
            $this->repository->crearCredito($descripcion);
            return [
                'success' => true,
                'mensaje' => 'Crédito creado correctamente'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'errores' => ['Error al crear el crédito: ' . $e->getMessage()]
            ];
        }
    }

    public function actualizarCredito($id, $datos)
    {
        $this->validarId($id);
        $descripcion = trim($datos['descripcion'] ?? '');

        $errores = $this->validarCredito($descripcion);

        if (!empty($errores)) {
            return [
                'success' => false,
                'errores' => $errores
            ];
        }

        if (!$this->repository->obtenerCredito($id)) {
            return [
                'success' => false,
                'errores' => ['Crédito no encontrado']
            ];
        }

        if ($this->repository->existeCreditoDescripcion($descripcion, $id)) {
            return [
                'success' => false,
                'errores' => ['Ya existe otro crédito con esa descripción']
            ];
        }

        try {
            $this->repository->actualizarCredito($id, $descripcion);
            return [
                'success' => true,
                'mensaje' => 'Crédito actualizado correctamente'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'errores' => ['Error al actualizar el crédito: ' . $e->getMessage()]
            ];
        }
    }

    public function eliminarCredito($id)
    {
        $this->validarId($id);

        if (!$this->repository->obtenerCredito($id)) {
            return [
                'success' => false,
                'error' => 'Crédito no encontrado'
            ];
        }

        try {
            $this->repository->eliminarCredito($id);
            return [
                'success' => true,
                'mensaje' => 'Crédito eliminado correctamente'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al eliminar el crédito: ' . $e->getMessage()
            ];
        }
    }

    private function validarCredito($descripcion)
    {
        $errores = [];

        if (empty($descripcion)) {
            $errores[] = 'La descripción es obligatoria';
        } elseif (strlen($descripcion) < 2) {
            $errores[] = 'La descripción debe tener al menos 2 caracteres';
        } elseif (strlen($descripcion) > 100) {
            $errores[] = 'La descripción no puede exceder 100 caracteres';
        }

        return $errores;
    }


    public function obtenerTiposProducto()
    {
        return $this->repository->obtenerTiposProducto();
    }


    public function obtenerTipoProducto($id)
    {
        $this->validarId($id);
        $tipo = $this->repository->obtenerTipoProducto($id);

        if (!$tipo) {
            throw new Exception("Tipo de producto no encontrado");
        }

        return $tipo;
    }

    public function crearTipoProducto($datos)
    {
        $descripcion = trim($datos['desc'] ?? '');

        $errores = $this->validarTipoProducto($descripcion);

        if (!empty($errores)) {
            return [
                'success' => false,
                'errores' => $errores
            ];
        }

        if ($this->repository->existeTipoProductoDescripcion($descripcion)) {
            return [
                'success' => false,
                'errores' => ['Ya existe un tipo de producto con esa descripción']
            ];
        }

        try {
            $this->repository->crearTipoProducto($descripcion);
            return [
                'success' => true,
                'mensaje' => 'Tipo de producto creado correctamente'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'errores' => ['Error al crear el tipo de producto: ' . $e->getMessage()]
            ];
        }
    }


    public function actualizarTipoProducto($id, $datos)
    {
        $this->validarId($id);
        $descripcion = trim($datos['desc'] ?? '');

        $errores = $this->validarTipoProducto($descripcion);

        if (!empty($errores)) {
            return [
                'success' => false,
                'errores' => $errores
            ];
        }

        if (!$this->repository->obtenerTipoProducto($id)) {
            return [
                'success' => false,
                'errores' => ['Tipo de producto no encontrado']
            ];
        }

        if ($this->repository->existeTipoProductoDescripcion($descripcion, $id)) {
            return [
                'success' => false,
                'errores' => ['Ya existe otro tipo de producto con esa descripción']
            ];
        }

        try {
            $this->repository->actualizarTipoProducto($id, $descripcion);
            return [
                'success' => true,
                'mensaje' => 'Tipo de producto actualizado correctamente'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'errores' => ['Error al actualizar el tipo de producto: ' . $e->getMessage()]
            ];
        }
    }

    public function eliminarTipoProducto($id)
    {
        $this->validarId($id);

        $tipo = $this->repository->obtenerTipoProducto($id);
        if (!$tipo) {
            return [
                'success' => false,
                'error' => 'Tipo de producto no encontrado'
            ];
        }

        $productosAsociados = $this->repository->contarProductosConTipo($tipo['desc']);
        if ($productosAsociados > 0) {
            return [
                'success' => false,
                'error' => "No se puede eliminar este tipo de producto porque tiene {$productosAsociados} productos asociados"
            ];
        }

        try {
            $this->repository->eliminarTipoProducto($id);
            return [
                'success' => true,
                'mensaje' => 'Tipo de producto eliminado correctamente'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al eliminar el tipo de producto: ' . $e->getMessage()
            ];
        }
    }


    private function validarTipoProducto($descripcion)
    {
        $errores = [];

        if (empty($descripcion)) {
            $errores[] = 'La descripción es obligatoria';
        } elseif (strlen($descripcion) < 2) {
            $errores[] = 'La descripción debe tener al menos 2 caracteres';
        } elseif (strlen($descripcion) > 50) {
            $errores[] = 'La descripción no puede exceder 50 caracteres';
        }

        return $errores;
    }


    public function obtenerUnidadesMedida()
    {
        return $this->repository->obtenerUnidadesMedida();
    }


    public function obtenerUnidadMedida($id)
    {
        $this->validarId($id);
        $unidad = $this->repository->obtenerUnidadMedida($id);

        if (!$unidad) {
            throw new Exception("Unidad de medida no encontrada");
        }

        return $unidad;
    }


    public function crearUnidadMedida($datos)
    {
        $descripcion = trim($datos['desc'] ?? '');

        $errores = $this->validarUnidadMedida($descripcion);

        if (!empty($errores)) {
            return [
                'success' => false,
                'errores' => $errores
            ];
        }

        if ($this->repository->existeUnidadMedidaDescripcion($descripcion)) {
            return [
                'success' => false,
                'errores' => ['Ya existe una unidad de medida con esa descripción']
            ];
        }

        try {
            $this->repository->crearUnidadMedida($descripcion);
            return [
                'success' => true,
                'mensaje' => 'Unidad de medida creada correctamente'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'errores' => ['Error al crear la unidad de medida: ' . $e->getMessage()]
            ];
        }
    }


    public function actualizarUnidadMedida($id, $datos)
    {
        $this->validarId($id);
        $descripcion = trim($datos['desc'] ?? '');

        // Validaciones
        $errores = $this->validarUnidadMedida($descripcion);

        if (!empty($errores)) {
            return [
                'success' => false,
                'errores' => $errores
            ];
        }

        if (!$this->repository->obtenerUnidadMedida($id)) {
            return [
                'success' => false,
                'errores' => ['Unidad de medida no encontrada']
            ];
        }

        if ($this->repository->existeUnidadMedidaDescripcion($descripcion, $id)) {
            return [
                'success' => false,
                'errores' => ['Ya existe otra unidad de medida con esa descripción']
            ];
        }

        try {
            $this->repository->actualizarUnidadMedida($id, $descripcion);
            return [
                'success' => true,
                'mensaje' => 'Unidad de medida actualizada correctamente'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'errores' => ['Error al actualizar la unidad de medida: ' . $e->getMessage()]
            ];
        }
    }


    public function eliminarUnidadMedida($id)
    {
        $this->validarId($id);

        if (!$this->repository->obtenerUnidadMedida($id)) {
            return [
                'success' => false,
                'error' => 'Unidad de medida no encontrada'
            ];
        }

        try {
            $this->repository->eliminarUnidadMedida($id);
            return [
                'success' => true,
                'mensaje' => 'Unidad de medida eliminada correctamente'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al eliminar la unidad de medida: ' . $e->getMessage()
            ];
        }
    }


    private function validarUnidadMedida($descripcion)
    {
        $errores = [];

        if (empty($descripcion)) {
            $errores[] = 'La descripción es obligatoria';
        } elseif (strlen($descripcion) < 1) {
            $errores[] = 'La descripción debe tener al menos 1 caracter';
        } elseif (strlen($descripcion) > 20) {
            $errores[] = 'La descripción no puede exceder 20 caracteres';
        }

        return $errores;
    }


    private function validarId($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            throw new Exception("ID inválido");
        }
    }


    public function obtenerEstadisticas()
    {
        try {
            $creditos = $this->repository->obtenerCreditos();
            $tipos = $this->repository->obtenerTiposProducto();
            $unidades = $this->repository->obtenerUnidadesMedida();

            return [
                'total_creditos' => count($creditos),
                'total_tipos_producto' => count($tipos),
                'total_unidades_medida' => count($unidades),
                'ultima_actualizacion' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [
                'total_creditos' => 0,
                'total_tipos_producto' => 0,
                'total_unidades_medida' => 0,
                'ultima_actualizacion' => date('Y-m-d H:i:s')
            ];
        }
    }


    public function generarReporte()
    {
        try {
            return [
                'creditos' => $this->repository->obtenerCreditos(),
                'tipos_producto' => $this->repository->obtenerTiposProducto(),
                'unidades_medida' => $this->repository->obtenerUnidadesMedida(),
                'estadisticas' => $this->obtenerEstadisticas(),
                'fecha_generacion' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            error_log("Error generando reporte: " . $e->getMessage());
            throw new Exception("Error al generar el reporte de configuración");
        }
    }
}
