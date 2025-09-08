<?php
// controllers/TransportadoraController.php

require_once __DIR__ . '/../services/TransportadoraService.php';

class TransportadoraController
{
    private $transportadoraService;

    public function __construct($conexion)
    {
        $this->transportadoraService = new TransportadoraService($conexion);
    }

    public function procesarRequest()
    {
        $resultado = [
            'mensaje' => '',
            'tipo_mensaje' => '',
            'transportadoras_existentes' => []
        ];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $accion = $_POST['accion'] ?? 'crear';

            switch ($accion) {
                case 'crear':
                    $resultado = $this->crearTransportadora();
                    break;
                case 'editar':
                    $resultado = $this->editarTransportadora();
                    break;
                case 'eliminar':
                    $resultado = $this->eliminarTransportadora();
                    break;
            }
        }

        // Obtener lista de transportadoras
        $resultado['transportadoras_existentes'] = $this->transportadoraService->obtenerTodasLasTransportadoras();

        return $resultado;
    }

    private function crearTransportadora()
    {
        $datos = [
            'descripcion' => trim($_POST['descripcion'])
        ];

        try {
            $this->transportadoraService->crearTransportadora($datos);
            return [
                'mensaje' => 'Transportadora registrada exitosamente.',
                'tipo_mensaje' => 'success'
            ];
        } catch (Exception $e) {
            return [
                'mensaje' => $e->getMessage(),
                'tipo_mensaje' => 'error'
            ];
        }
    }

    private function editarTransportadora()
    {
        $datos = [
            'id' => $_POST['id'],
            'descripcion' => trim($_POST['descripcion'])
        ];

        try {
            $this->transportadoraService->editarTransportadora($datos);
            return [
                'mensaje' => 'Transportadora actualizada exitosamente.',
                'tipo_mensaje' => 'success'
            ];
        } catch (Exception $e) {
            return [
                'mensaje' => $e->getMessage(),
                'tipo_mensaje' => 'error'
            ];
        }
    }

    private function eliminarTransportadora()
    {
        $id = $_POST['id'];

        try {
            $this->transportadoraService->eliminarTransportadora($id);
            return [
                'mensaje' => 'Transportadora eliminada exitosamente.',
                'tipo_mensaje' => 'success'
            ];
        } catch (Exception $e) {
            return [
                'mensaje' => $e->getMessage(),
                'tipo_mensaje' => 'error'
            ];
        }
    }

    public function buscarTransportadoras($termino)
    {
        try {
            return $this->transportadoraService->buscarTransportadorasPorDescripcion($termino);
        } catch (Exception $e) {
            return [];
        }
    }

    public function obtenerEstadisticas()
    {
        try {
            return $this->transportadoraService->obtenerEstadisticasTransportadoras();
        } catch (Exception $e) {
            return [
                'total' => 0,
                'activas' => 0,
                'con_envios' => 0
            ];
        }
    }

    public function obtenerTransportadorasActivas()
    {
        try {
            return $this->transportadoraService->obtenerTransportadorasActivas();
        } catch (Exception $e) {
            return [];
        }
    }
}
