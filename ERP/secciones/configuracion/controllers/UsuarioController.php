<?php
// controllers/UsuarioController.php

require_once __DIR__ . '/../services/UsuarioService.php';

class UsuarioController
{
    private $usuarioService;

    public function __construct($conexion)
    {
        $this->usuarioService = new UsuarioService($conexion);
    }

    public function procesarRequest()
    {
        $resultado = [
            'mensaje' => '',
            'tipo_mensaje' => '',
            'usuarios_existentes' => []
        ];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $accion = $_POST['accion'] ?? 'crear';

            switch ($accion) {
                case 'crear':
                    $resultado = $this->crearUsuario();
                    break;
                case 'editar':
                    $resultado = $this->editarUsuario();
                    break;
                case 'eliminar':
                    $resultado = $this->eliminarUsuario();
                    break;
            }
        }

        // Obtener lista de usuarios
        $resultado['usuarios_existentes'] = $this->usuarioService->obtenerTodosLosUsuarios();

        return $resultado;
    }

    private function crearUsuario()
    {
        $datos = [
            'nombre' => trim($_POST['nombre']),
            'usuario' => trim($_POST['usuario']),
            'contrasenia' => $_POST['contrasenia'],
            'confirmar_contrasenia' => $_POST['confirmar_contrasenia'],
            'rol' => $_POST['rol']
        ];

        try {
            $this->usuarioService->crearUsuario($datos);
            return [
                'mensaje' => 'Usuario registrado exitosamente.',
                'tipo_mensaje' => 'success'
            ];
        } catch (Exception $e) {
            return [
                'mensaje' => $e->getMessage(),
                'tipo_mensaje' => 'error'
            ];
        }
    }

    private function editarUsuario()
    {
        $datos = [
            'id' => $_POST['id'],
            'nombre' => trim($_POST['nombre']),
            'usuario' => trim($_POST['usuario']),
            'rol' => $_POST['rol'],
            'nueva_contrasenia' => $_POST['nueva_contrasenia'] ?? ''
        ];

        try {
            $this->usuarioService->editarUsuario($datos);
            return [
                'mensaje' => 'Usuario actualizado exitosamente.',
                'tipo_mensaje' => 'success'
            ];
        } catch (Exception $e) {
            return [
                'mensaje' => $e->getMessage(),
                'tipo_mensaje' => 'error'
            ];
        }
    }

    private function eliminarUsuario()
    {
        $id = $_POST['id'];

        try {
            $this->usuarioService->eliminarUsuario($id);
            return [
                'mensaje' => 'Usuario eliminado exitosamente.',
                'tipo_mensaje' => 'success'
            ];
        } catch (Exception $e) {
            return [
                'mensaje' => $e->getMessage(),
                'tipo_mensaje' => 'error'
            ];
        }
    }

    public function obtenerNombreRol($rol)
    {
        return $this->usuarioService->obtenerNombreRol($rol);
    }

    public function obtenerClaseRol($rol)
    {
        return $this->usuarioService->obtenerClaseRol($rol);
    }
}
