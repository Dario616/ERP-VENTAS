<?php
// controllers/TipoTransporteController.php

require_once __DIR__ . '/../services/TipoTransporteService.php';

class TipoTransporteController
{
    private $tipoTransporteService;

    public function __construct($conexion)
    {
        $this->tipoTransporteService = new TipoTransporteService($conexion);
    }

    public function procesarRequest()
    {
        $resultado = [
            'mensaje' => '',
            'tipo_mensaje' => '',
            'tipos_existentes' => []
        ];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $accion = $_POST['accion'] ?? 'crear';

            switch ($accion) {
                case 'crear':
                    $resultado = $this->crearTipo();
                    break;
                case 'editar':
                    $resultado = $this->editarTipo();
                    break;
                case 'eliminar':
                    $resultado = $this->eliminarTipo();
                    break;
            }
        }

        // Obtener lista de tipos de transporte
        $resultado['tipos_existentes'] = $this->tipoTransporteService->obtenerTodosLosTipos();

        return $resultado;
    }

    private function crearTipo()
    {
        $datos = [
            'nombre' => trim($_POST['nombre'])
        ];

        try {
            $this->tipoTransporteService->crearTipo($datos);
            return [
                'mensaje' => 'Tipo de transporte registrado exitosamente.',
                'tipo_mensaje' => 'success'
            ];
        } catch (Exception $e) {
            return [
                'mensaje' => $e->getMessage(),
                'tipo_mensaje' => 'error'
            ];
        }
    }

    private function editarTipo()
    {
        $datos = [
            'id' => $_POST['id'],
            'nombre' => trim($_POST['nombre'])
        ];

        try {
            $this->tipoTransporteService->editarTipo($datos);
            return [
                'mensaje' => 'Tipo de transporte actualizado exitosamente.',
                'tipo_mensaje' => 'success'
            ];
        } catch (Exception $e) {
            return [
                'mensaje' => $e->getMessage(),
                'tipo_mensaje' => 'error'
            ];
        }
    }

    private function eliminarTipo()
    {
        $id = $_POST['id'];

        try {
            $this->tipoTransporteService->eliminarTipo($id);
            return [
                'mensaje' => 'Tipo de transporte eliminado exitosamente.',
                'tipo_mensaje' => 'success'
            ];
        } catch (Exception $e) {
            return [
                'mensaje' => $e->getMessage(),
                'tipo_mensaje' => 'error'
            ];
        }
    }

    public function obtenerIconoTransporte($nombre)
    {
        return $this->tipoTransporteService->obtenerIconoTransporte($nombre);
    }

    public function obtenerClaseIcono($nombre)
    {
        return $this->tipoTransporteService->obtenerClaseIcono($nombre);
    }
}
