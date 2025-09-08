<?php
// services/UsuarioService.php

require_once __DIR__ . '/../repository/UsuarioRepository.php';

class UsuarioService
{
    private $usuarioRepository;

    public function __construct($conexion)
    {
        $this->usuarioRepository = new UsuarioRepository($conexion);
    }

    public function crearUsuario($datos)
    {
        // Validaciones de negocio
        $this->validarDatosUsuario($datos);

        if ($datos['contrasenia'] !== $datos['confirmar_contrasenia']) {
            throw new Exception("Las contraseñas no coinciden.");
        }

        if (strlen($datos['contrasenia']) < 6) {
            throw new Exception("La contraseña debe tener al menos 6 caracteres.");
        }

        // Verificar si el usuario ya existe
        if ($this->usuarioRepository->usuarioExiste($datos['usuario'])) {
            throw new Exception("El nombre de usuario ya existe. Por favor, elija otro.");
        }

        // Crear usuario
        $resultado = $this->usuarioRepository->crearUsuario($datos);

        if (!$resultado) {
            throw new Exception("Error al registrar el usuario. Intente nuevamente.");
        }

        return $resultado;
    }

    public function editarUsuario($datos)
    {
        // Validaciones de negocio
        $this->validarDatosBasicosUsuario($datos);

        // Verificar si el usuario ya existe (excluyendo el actual)
        if ($this->usuarioRepository->usuarioExisteExcluyendo($datos['usuario'], $datos['id'])) {
            throw new Exception("El nombre de usuario ya existe. Por favor, elija otro.");
        }

        // Validar nueva contraseña si se proporciona
        if (!empty($datos['nueva_contrasenia'])) {
            if (strlen($datos['nueva_contrasenia']) < 6) {
                throw new Exception("La nueva contraseña debe tener al menos 6 caracteres.");
            }
        }

        // Actualizar usuario
        $resultado = $this->usuarioRepository->editarUsuario($datos);

        if (!$resultado) {
            throw new Exception("Error al actualizar el usuario. Intente nuevamente.");
        }

        return $resultado;
    }

    public function eliminarUsuario($id)
    {
        if (empty($id)) {
            throw new Exception("ID de usuario no válido.");
        }

        $resultado = $this->usuarioRepository->eliminarUsuario($id);

        if (!$resultado) {
            throw new Exception("Error al eliminar el usuario.");
        }

        return $resultado;
    }

    public function obtenerTodosLosUsuarios()
    {
        return $this->usuarioRepository->obtenerTodosLosUsuarios();
    }

    public function obtenerNombreRol($rol)
    {
        switch ($rol) {
            case '1':
                return 'Administrador';
            case '2':
                return 'Producción';
            case '3':
                return 'Expedición';
            default:
                return 'Desconocido';
        }
    }

    public function obtenerClaseRol($rol)
    {
        switch ($rol) {
            case '1':
                return 'danger';
            case '2':
                return 'primary';
            case '3':
                return 'warning';
            default:
                return 'secondary';
        }
    }

    private function validarDatosUsuario($datos)
    {
        if (empty($datos['nombre']) || empty($datos['usuario']) || empty($datos['contrasenia']) || empty($datos['rol'])) {
            throw new Exception("Todos los campos son obligatorios.");
        }

        $this->validarDatosBasicosUsuario($datos);
    }

    private function validarDatosBasicosUsuario($datos)
    {
        if (empty($datos['nombre']) || empty($datos['usuario']) || empty($datos['rol'])) {
            throw new Exception("Todos los campos son obligatorios.");
        }

        // Validar que el rol sea válido
        if (!in_array($datos['rol'], ['1', '2', '3'])) {
            throw new Exception("Rol no válido.");
        }

        // Validaciones adicionales de formato
        if (strlen(trim($datos['nombre'])) < 2) {
            throw new Exception("El nombre debe tener al menos 2 caracteres.");
        }

        if (strlen(trim($datos['usuario'])) < 3) {
            throw new Exception("El nombre de usuario debe tener al menos 3 caracteres.");
        }
    }
}
