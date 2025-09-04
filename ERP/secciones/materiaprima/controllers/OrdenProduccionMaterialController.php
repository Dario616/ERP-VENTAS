<?php
require_once __DIR__ . '/../services/OrdenProduccionMaterialService.php';

class OrdenProduccionMaterialController
{
    private $ordenProduccionMaterialService;
    private $mensaje = '';
    private $error = '';
    private $pagina_actual = 1;
    private $items_por_pagina = 15;
    private $filtros = [];

    public function __construct($conexion)
    {
        $this->ordenProduccionMaterialService = new OrdenProduccionMaterialService($conexion);
    }

    public function manejarPeticion()
    {
        $this->pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
        $this->inicializarFiltros();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->procesarPeticionPOST();
        }

        return $this->obtenerDatosVista();
    }

    public function manejarAjax()
    {
        header('Content-Type: application/json');

        $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

        switch ($accion) {
            case 'obtener_versiones_receta':
                $this->obtenerVersionesReceta();
                break;
            case 'calcular_componentes':
                $this->calcularComponentes();
                break;
            case 'obtener_detalle_orden':
                $this->obtenerDetalleOrden();
                break;
            default:
                echo json_encode([
                    'success' => false,
                    'error' => 'Acción no válida'
                ]);
                break;
        }
        exit();
    }

    private function inicializarFiltros()
    {
        $this->filtros = [
            'buscar_materia' => trim($_GET['buscar_materia'] ?? ''),
            'id_materia_prima' => intval($_GET['id_materia_prima'] ?? 0),
            'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => trim($_GET['fecha_hasta'] ?? '')
        ];
    }

    private function procesarPeticionPOST()
    {
        $accion = $_POST['accion'] ?? '';
        $usuario = $_SESSION['nombre'] ?? 'SISTEMA';

        switch ($accion) {
            case 'crear':
                $this->manejarCreacion($usuario);
                break;
            case 'eliminar':
                $this->manejarEliminacion($usuario);
                break;
            default:
                $this->error = "Acción no válida";
                break;
        }
    }

    private function manejarCreacion($usuario)
    {
        try {
            // Forzar estado PENDIENTE
            $_POST['estado'] = 'PENDIENTE';

            $resultado = $this->ordenProduccionMaterialService->crearOrden($_POST, $usuario);

            if ($resultado['success']) {
                $this->mensaje = "✅ <strong>Orden de producción creada exitosamente!</strong><br>";
                $this->mensaje .= "ID: #{$resultado['id']}<br>";
                $this->mensaje .= "<strong>Materia Prima:</strong> " . ($_POST['materia_prima_nombre'] ?? 'N/A') . "<br>";
                $this->mensaje .= "<strong>Cantidad:</strong> {$_POST['cantidad_solicitada']} {$_POST['unidad_medida']}<br>";
                $this->mensaje .= "<strong>Versión de Receta:</strong> {$_POST['version_receta']}<br>";
                $this->mensaje .= "<strong>Estado:</strong> PENDIENTE<br>";

                error_log("✅ Orden producción material creada - ID: {$resultado['id']} - Usuario: $usuario");
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al crear orden de producción: " . $e->getMessage();
        }
    }

    private function manejarEliminacion($usuario)
    {
        try {
            $id = intval($_POST['id_eliminar'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de orden inválido");
            }

            $orden = $this->ordenProduccionMaterialService->obtenerOrdenPorId($id);
            if (!$orden) {
                throw new Exception("La orden no existe");
            }

            $resultado = $this->ordenProduccionMaterialService->eliminarOrden($id, $usuario);

            if ($resultado['success']) {
                $this->mensaje = "✅ <strong>Orden de producción eliminada exitosamente!</strong><br>";
                $this->mensaje .= "Se eliminó: {$orden['materia_prima_desc']}<br>";
                $this->mensaje .= "Cantidad: {$orden['cantidad_solicitada']} {$orden['unidad_medida']}<br>";

                error_log("🗑️ Orden producción material eliminada - ID: $id - Usuario: $usuario");
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al eliminar orden de producción: " . $e->getMessage();
        }
    }

    private function obtenerVersionesReceta()
    {
        try {
            $id_materia_prima = intval($_POST['id_materia_prima'] ?? $_GET['id_materia_prima'] ?? 0);

            if ($id_materia_prima <= 0) {
                throw new Exception("ID de materia prima inválido");
            }

            $resultado = $this->ordenProduccionMaterialService->obtenerVersionesReceta($id_materia_prima);
            echo json_encode($resultado);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function calcularComponentes()
    {
        try {
            $id_materia_prima = intval($_POST['id_materia_prima'] ?? 0);
            $version_receta = intval($_POST['version_receta'] ?? 0);
            $cantidad_solicitada = floatval($_POST['cantidad_solicitada'] ?? 0);

            if ($id_materia_prima <= 0 || $version_receta <= 0 || $cantidad_solicitada <= 0) {
                throw new Exception("Parámetros inválidos");
            }

            $resultado = $this->ordenProduccionMaterialService->calcularComponentesNecesarios(
                $id_materia_prima,
                $version_receta,
                $cantidad_solicitada
            );

            echo json_encode($resultado);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function obtenerDetalleOrden()
    {
        try {
            $id_orden = intval($_POST['id_orden'] ?? $_GET['id_orden'] ?? 0);

            if ($id_orden <= 0) {
                throw new Exception("ID de orden inválido");
            }

            $orden = $this->ordenProduccionMaterialService->obtenerOrdenParaPDF($id_orden);

            if (!$orden) {
                throw new Exception("Orden no encontrada");
            }

            // Calcular componentes necesarios
            $componentes_calc = $this->ordenProduccionMaterialService->calcularComponentesNecesarios(
                $orden['id_materia_prima'],
                $orden['version_receta'],
                $orden['cantidad_solicitada']
            );

            echo json_encode([
                'success' => true,
                'orden' => $orden,
                'componentes_calculados' => $componentes_calc['componentes'] ?? []
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function obtenerDatosPaginacion()
    {
        return $this->ordenProduccionMaterialService->obtenerDatosPaginacion(
            $this->items_por_pagina,
            $this->pagina_actual,
            $this->filtros
        );
    }

    public function obtenerDatosVista()
    {
        return [
            'mensaje' => $this->mensaje,
            'error' => $this->error,
            'pagina_actual' => $this->pagina_actual,
            'items_por_pagina' => $this->items_por_pagina,
            'filtros' => $this->filtros,
            'filtrosUrl' => $this->generarFiltrosUrl(),
            'datosPaginacion' => $this->obtenerDatosPaginacion(),
            'materiasPrimasConRecetas' => $this->ordenProduccionMaterialService->obtenerMateriasPrimasConRecetas()
        ];
    }

    public function generarFiltrosUrl()
    {
        $params = [];

        foreach ($this->filtros as $key => $value) {
            if (!empty($value)) {
                $params[] = urlencode($key) . '=' . urlencode($value);
            }
        }

        return !empty($params) ? '&' . implode('&', $params) : '';
    }

    public function obtenerMateriasPrimasConRecetas()
    {
        return $this->ordenProduccionMaterialService->obtenerMateriasPrimasConRecetas();
    }

    public function obtenerOrdenParaPDF($id)
    {
        return $this->ordenProduccionMaterialService->obtenerOrdenParaPDF($id);
    }
}
