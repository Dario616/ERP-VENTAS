<?php
// services/TipoTransporteService.php

require_once __DIR__ . '/../repository/TipoTransporteRepository.php';

class TipoTransporteService
{
    private $tipoTransporteRepository;

    public function __construct($conexion)
    {
        $this->tipoTransporteRepository = new TipoTransporteRepository($conexion);
    }

    public function crearTipo($datos)
    {
        // Validaciones de negocio
        $this->validarDatosTipo($datos);

        // Verificar si el tipo de transporte ya existe
        if ($this->tipoTransporteRepository->tipoExiste($datos['nombre'])) {
            throw new Exception("El tipo de transporte ya existe. Por favor, ingrese otro nombre.");
        }

        // Crear tipo de transporte
        $resultado = $this->tipoTransporteRepository->crearTipo($datos);

        if (!$resultado) {
            throw new Exception("Error al registrar el tipo de transporte. Intente nuevamente.");
        }

        return $resultado;
    }

    public function editarTipo($datos)
    {
        // Validaciones de negocio
        $this->validarDatosTipo($datos);

        // Verificar si el tipo de transporte ya existe (excluyendo el actual)
        if ($this->tipoTransporteRepository->tipoExisteExcluyendo($datos['nombre'], $datos['id'])) {
            throw new Exception("El tipo de transporte ya existe. Por favor, ingrese otro nombre.");
        }

        // Actualizar tipo de transporte
        $resultado = $this->tipoTransporteRepository->editarTipo($datos);

        if (!$resultado) {
            throw new Exception("Error al actualizar el tipo de transporte. Intente nuevamente.");
        }

        return $resultado;
    }

    public function eliminarTipo($id)
    {
        if (empty($id)) {
            throw new Exception("ID de tipo de transporte no válido.");
        }

        // Aquí puedes agregar validaciones adicionales
        // Por ejemplo, verificar si tiene registros asociados antes de eliminar

        $resultado = $this->tipoTransporteRepository->eliminarTipo($id);

        if (!$resultado) {
            throw new Exception("Error al eliminar el tipo de transporte.");
        }

        return $resultado;
    }

    public function obtenerTodosLosTipos()
    {
        return $this->tipoTransporteRepository->obtenerTodosLosTipos();
    }

    public function obtenerTipoPorId($id)
    {
        if (empty($id)) {
            throw new Exception("ID de tipo de transporte no válido.");
        }

        return $this->tipoTransporteRepository->obtenerTipoPorId($id);
    }

    public function obtenerIconoTransporte($nombre)
    {
        $nombre_lower = strtolower($nombre);

        if (strpos($nombre_lower, 'terrestre') !== false) {
            return 'truck';
        } elseif (strpos($nombre_lower, 'aereo') !== false || strpos($nombre_lower, 'aéreo') !== false) {
            return 'plane';
        } elseif (strpos($nombre_lower, 'maritimo') !== false || strpos($nombre_lower, 'marítimo') !== false) {
            return 'ship';
        } elseif (strpos($nombre_lower, 'ferroviario') !== false) {
            return 'train';
        } else {
            return 'route';
        }
    }

    public function obtenerClaseIcono($nombre)
    {
        $nombre_lower = strtolower($nombre);

        if (strpos($nombre_lower, 'terrestre') !== false) {
            return 'icon-terrestre';
        } elseif (strpos($nombre_lower, 'aereo') !== false || strpos($nombre_lower, 'aéreo') !== false) {
            return 'icon-aereo';
        } elseif (strpos($nombre_lower, 'maritimo') !== false || strpos($nombre_lower, 'marítimo') !== false) {
            return 'icon-maritimo';
        } elseif (strpos($nombre_lower, 'ferroviario') !== false) {
            return 'icon-ferroviario';
        } else {
            return 'icon-default';
        }
    }

    public function buscarTiposPorNombre($termino)
    {
        if (empty($termino)) {
            return $this->obtenerTodosLosTipos();
        }

        return $this->tipoTransporteRepository->buscarTiposPorNombre($termino);
    }

    public function contarTiposDeTransporte()
    {
        return $this->tipoTransporteRepository->contarTiposDeTransporte();
    }

    private function validarDatosTipo($datos)
    {
        if (empty($datos['nombre'])) {
            throw new Exception("El nombre del tipo de transporte es obligatorio.");
        }

        if (strlen($datos['nombre']) < 3) {
            throw new Exception("El nombre debe tener al menos 3 caracteres.");
        }

        // Validaciones adicionales de formato
        if (strlen($datos['nombre']) > 100) {
            throw new Exception("El nombre no puede exceder los 100 caracteres.");
        }

        // Validar que no contenga solo espacios
        if (trim($datos['nombre']) === '') {
            throw new Exception("El nombre no puede estar vacío o contener solo espacios.");
        }

        // Validar caracteres especiales si es necesario
        if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-\.]+$/u', $datos['nombre'])) {
            throw new Exception("El nombre solo puede contener letras, espacios, guiones y puntos.");
        }
    }
}
