<?php
// services/TransportadoraService.php

require_once __DIR__ . '/../repository/TransportadoraRepository.php';

class TransportadoraService
{
    private $transportadoraRepository;

    public function __construct($conexion)
    {
        $this->transportadoraRepository = new TransportadoraRepository($conexion);
    }

    public function crearTransportadora($datos)
    {
        // Validaciones de negocio
        $this->validarDatosTransportadora($datos);

        // Verificar si la transportadora ya existe
        if ($this->transportadoraRepository->transportadoraExiste($datos['descripcion'])) {
            throw new Exception("La transportadora ya existe. Por favor, ingrese otra descripción.");
        }

        // Crear transportadora
        $resultado = $this->transportadoraRepository->crearTransportadora($datos);

        if (!$resultado) {
            throw new Exception("Error al registrar la transportadora. Intente nuevamente.");
        }

        return $resultado;
    }

    public function editarTransportadora($datos)
    {
        // Validaciones de negocio
        $this->validarDatosTransportadora($datos);

        // Verificar si la transportadora ya existe (excluyendo la actual)
        if ($this->transportadoraRepository->transportadoraExisteExcluyendo($datos['descripcion'], $datos['id'])) {
            throw new Exception("La transportadora ya existe. Por favor, ingrese otra descripción.");
        }

        // Actualizar transportadora
        $resultado = $this->transportadoraRepository->editarTransportadora($datos);

        if (!$resultado) {
            throw new Exception("Error al actualizar la transportadora. Intente nuevamente.");
        }

        return $resultado;
    }

    public function eliminarTransportadora($id)
    {
        if (empty($id)) {
            throw new Exception("ID de transportadora no válido.");
        }

        // Verificar si la transportadora está en uso antes de eliminar
        if ($this->transportadoraRepository->verificarTransportadoraEnUso($id)) {
            throw new Exception("No se puede eliminar la transportadora porque tiene registros asociados.");
        }

        $resultado = $this->transportadoraRepository->eliminarTransportadora($id);

        if (!$resultado) {
            throw new Exception("Error al eliminar la transportadora.");
        }

        return $resultado;
    }

    public function obtenerTodasLasTransportadoras()
    {
        return $this->transportadoraRepository->obtenerTodasLasTransportadoras();
    }

    public function obtenerTransportadoraPorId($id)
    {
        if (empty($id)) {
            throw new Exception("ID de transportadora no válido.");
        }

        return $this->transportadoraRepository->obtenerTransportadoraPorId($id);
    }

    public function buscarTransportadorasPorDescripcion($termino)
    {
        if (empty($termino)) {
            return $this->obtenerTodasLasTransportadoras();
        }

        return $this->transportadoraRepository->buscarTransportadorasPorDescripcion($termino);
    }

    public function obtenerTransportadorasActivas()
    {
        return $this->transportadoraRepository->obtenerTransportadorasActivas();
    }

    public function obtenerTransportadorasMasUsadas($limite = 5)
    {
        return $this->transportadoraRepository->obtenerTransportadorasMasUsadas($limite);
    }

    public function obtenerEstadisticasTransportadoras()
    {
        return $this->transportadoraRepository->obtenerEstadisticasTransportadoras();
    }

    public function marcarTransportadoraComoActiva($id)
    {
        if (empty($id)) {
            throw new Exception("ID de transportadora no válido.");
        }

        return $this->transportadoraRepository->marcarTransportadoraComoActiva($id);
    }

    public function marcarTransportadoraComoInactiva($id)
    {
        if (empty($id)) {
            throw new Exception("ID de transportadora no válido.");
        }

        return $this->transportadoraRepository->marcarTransportadoraComoInactiva($id);
    }

    public function contarTransportadoras()
    {
        return $this->transportadoraRepository->contarTransportadoras();
    }

    public function validarNombreEmpresa($descripcion)
    {
        // Validaciones adicionales específicas para nombres de empresas
        if (preg_match('/^\d+$/', $descripcion)) {
            throw new Exception("El nombre de la transportadora no puede ser solo números.");
        }

        // Verificar palabras comunes que podrían indicar entrada incorrecta
        $palabras_invalidas = ['test', 'prueba', 'xxx', 'aaa'];
        $descripcion_lower = strtolower($descripcion);

        foreach ($palabras_invalidas as $palabra) {
            if ($descripcion_lower === $palabra) {
                throw new Exception("Por favor, ingrese un nombre válido para la transportadora.");
            }
        }

        return true;
    }

    private function validarDatosTransportadora($datos)
    {
        if (empty($datos['descripcion'])) {
            throw new Exception("La descripción de la transportadora es obligatoria.");
        }

        if (strlen($datos['descripcion']) < 3) {
            throw new Exception("La descripción debe tener al menos 3 caracteres.");
        }

        // Validaciones adicionales de formato
        if (strlen($datos['descripcion']) > 200) {
            throw new Exception("La descripción no puede exceder los 200 caracteres.");
        }

        // Validar que no contenga solo espacios
        if (trim($datos['descripcion']) === '') {
            throw new Exception("La descripción no puede estar vacía o contener solo espacios.");
        }

        // Validar caracteres permitidos (letras, números, espacios y algunos símbolos comunes)
        if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-\.\,\&\(\)]+$/u', $datos['descripcion'])) {
            throw new Exception("La descripción contiene caracteres no válidos.");
        }

        // Validaciones específicas para nombres de empresas
        $this->validarNombreEmpresa($datos['descripcion']);
    }
}
