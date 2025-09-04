<?php
require_once __DIR__ . '/../services/ProduccionService.php';

class ProduccionController
{
    private $produccionService;
    private $mensaje = '';
    private $error = '';
    private $ordenActual = null;
    private $estadisticas = null;
    private $producciones = [];

    public function __construct($conexion)
    {
        $this->produccionService = new ProduccionService($conexion);
    }

    public function manejarPeticion()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->procesarPeticionPOST();
        }

        $idOrden = intval($_GET['orden'] ?? 0);
        if ($idOrden > 0) {
            $this->cargarOrden($idOrden);
        }

        return $this->obtenerDatosVista();
    }

    public function manejarAjax()
    {
        header('Content-Type: application/json');

        $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

        switch ($accion) {
            case 'buscar_orden':
                $this->buscarOrden();
                break;
            case 'buscar_ordenes_disponibles':
                $this->buscarOrdenesDisponibles();
                break;
            case 'obtener_producciones':
                $this->obtenerProducciones();
                break;
            case 'obtener_estadisticas':
                $this->obtenerEstadisticas();
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

    private function procesarPeticionPOST()
    {
        $accion = $_POST['accion'] ?? '';
        $usuario = $_SESSION['nombre'] ?? 'SISTEMA';

        switch ($accion) {
            case 'buscar_orden_web':
                $this->buscarOrdenWeb();
                break;
            case 'crear_produccion':
                $this->manejarCreacionProduccion($usuario);
                break;
            case 'eliminar_produccion':
                $this->manejarEliminacionProduccion($usuario);
                break;
            default:
                $this->error = "Acción no válida";
                break;
        }
    }

    private function buscarOrdenWeb()
    {
        $idOrden = intval($_POST['id_orden'] ?? 0);

        if ($idOrden <= 0) {
            $this->error = "Debe especificar un número de orden válido";
            return;
        }

        header("Location: ?orden=$idOrden");
        exit();
    }

    private function cargarOrden($idOrden)
    {
        $resultado = $this->produccionService->buscarOrdenProduccion($idOrden);

        if ($resultado['success']) {
            $this->ordenActual = $resultado['orden'];
            $this->estadisticas = $resultado['estadisticas'];
            $this->producciones = $resultado['producciones'];

            if (isset($this->ordenActual['es_tubo']) && $this->ordenActual['es_tubo']) {
                error_log("🔧 Orden #$idOrden cargada - Material TUBO detectado: {$this->ordenActual['materia_prima_desc']}");
            }
        } else {
            $this->error = $resultado['error'];
        }
    }

    private function manejarCreacionProduccion($usuario)
    {
        try {
            error_log("📝 Datos recibidos para producción: " . json_encode($_POST));

            $nombreMaterial = trim($_POST['nombre'] ?? '');
            $esTubo = stripos($nombreMaterial, 'tubo') !== false;

            if ($esTubo) {
                $_POST['tara'] = '0';
                error_log("🔧 Material TUBO detectado - Tara establecida automáticamente en 0");
            }

            $resultado = $this->produccionService->crearProduccion($_POST, $usuario);

            if ($resultado['success']) {
                $mensajeBase = "✅ <strong>Producción registrada exitosamente!</strong><br>";
                $mensajeBase .= "ID: {$resultado['id']}<br>";
                $mensajeBase .= "Peso: " . number_format($_POST['peso_bruto'], 3) . " KG bruto<br>";

                if ($esTubo) {
                    $mensajeBase .= "Material: TUBO (tara automática: 0.000 KG)<br>";
                    $pesoLiquido = floatval($_POST['peso_bruto']) - 0;
                    $mensajeBase .= "Peso líquido: " . number_format($pesoLiquido, 3) . " KG<br>";
                }

                if (isset($_POST['cantidad']) && !empty($_POST['cantidad'])) {
                    $mensajeBase .= "Cantidad: " . number_format($_POST['cantidad']) . " unidades<br>";
                }

                $this->mensaje = $mensajeBase;

                error_log("✅ Producción creada - ID: {$resultado['id']} - Usuario: $usuario" . ($esTubo ? " - TUBO" : ""));

                $idOrden = intval($_POST['id_op']);
                $this->cargarOrden($idOrden);
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al registrar producción: " . $e->getMessage();
            error_log("💥 Error creando producción: " . $e->getMessage() . " - Datos: " . json_encode($_POST));
        }
    }

    private function manejarEliminacionProduccion($usuario)
    {
        try {
            $id = intval($_POST['id_eliminar'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de producción inválido");
            }

            $resultado = $this->produccionService->eliminarProduccion($id, $usuario);

            if ($resultado['success']) {
                $this->mensaje = "✅ <strong>Producción eliminada exitosamente!</strong>";
                error_log("🗑️ Producción eliminada - ID: $id - Usuario: $usuario");

                if (isset($_POST['id_orden_actual'])) {
                    $idOrden = intval($_POST['id_orden_actual']);
                    $this->cargarOrden($idOrden);
                }
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al eliminar producción: " . $e->getMessage();
        }
    }

    private function buscarOrden()
    {
        try {
            $idOrden = intval($_POST['id_orden'] ?? $_GET['id_orden'] ?? 0);
            $resultado = $this->produccionService->buscarOrdenProduccion($idOrden);
            echo json_encode($resultado);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function buscarOrdenesDisponibles()
    {
        try {
            $termino = trim($_POST['termino'] ?? $_GET['termino'] ?? '');
            $resultado = $this->produccionService->buscarOrdenesDisponibles($termino);
            echo json_encode($resultado);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function obtenerProducciones()
    {
        try {
            $idOrden = intval($_POST['id_orden'] ?? $_GET['id_orden'] ?? 0);
            $producciones = $this->produccionService->obtenerProduccionesOrden($idOrden);

            echo json_encode([
                'success' => true,
                'producciones' => $producciones,
                'total' => count($producciones)
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function obtenerEstadisticas()
    {
        try {
            $idOrden = intval($_POST['id_orden'] ?? $_GET['id_orden'] ?? 0);
            $estadisticas = $this->produccionService->obtenerEstadisticasOrden($idOrden);

            echo json_encode([
                'success' => true,
                'estadisticas' => $estadisticas
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function obtenerDatosVista()
    {
        $datosVista = [
            'mensaje' => $this->mensaje,
            'error' => $this->error,
            'orden_actual' => $this->ordenActual,
            'estadisticas' => $this->estadisticas,
            'producciones' => $this->producciones,
            'tiene_orden' => $this->ordenActual !== null,
            'puede_producir' => $this->ordenActual ? $this->produccionService->puedeProducir($this->ordenActual) : ['puede' => false, 'razon' => 'No hay orden seleccionada']
        ];

        if ($this->ordenActual) {
            $datosVista['requiere_cantidad'] = $this->produccionService->requiereCantidad($this->ordenActual);
            $datosVista['texto_unidad_medida'] = $this->produccionService->obtenerTextoUnidadMedida($this->ordenActual['unidad_medida']);
            $datosVista['es_material_tubo'] = $this->produccionService->esMaterialTubo($this->ordenActual);
            $datosVista['info_tubo'] = $this->produccionService->obtenerInfoTubo($this->ordenActual);

            if ($datosVista['es_material_tubo']) {
                error_log("🔧 Vista configurada para material TUBO - Orden: {$this->ordenActual['id']}");
            }

            if ($this->estadisticas && $datosVista['requiere_cantidad']) {
                $totalSolicitado = floatval($this->ordenActual['cantidad_solicitada']);
                $totalProducido = intval($this->estadisticas['total_cantidad_unidades']);

                $datosVista['progreso_unidades'] = [
                    'total_solicitado' => $totalSolicitado,
                    'total_producido' => $totalProducido,
                    'porcentaje' => $totalSolicitado > 0 ? min(100, ($totalProducido / $totalSolicitado) * 100) : 0,
                    'pendiente' => max(0, $totalSolicitado - $totalProducido)
                ];
            }

            if ($datosVista['es_material_tubo'] && $this->estadisticas) {
                $datosVista['estadisticas_tubo'] = [
                    'total_peso_bruto' => floatval($this->estadisticas['total_peso_bruto']),
                    'total_peso_liquido' => floatval($this->estadisticas['total_peso_liquido']),
                    'tara_total_ahorrada' => 0,
                    'eficiencia_tubo' => 100
                ];
            }
        }

        return $datosVista;
    }

    public function formatearPeso($peso, $decimales = 3)
    {
        return $this->produccionService->formatearPeso($peso, $decimales);
    }

    public function formatearCantidad($cantidad)
    {
        return number_format(intval($cantidad));
    }

    public function obtenerClaseProgreso($porcentaje)
    {
        if ($porcentaje >= 100) return 'success';
        if ($porcentaje >= 75) return 'info';
        if ($porcentaje >= 50) return 'warning';
        return 'danger';
    }

    public function obtenerClaseTubo($esTubo)
    {
        return $esTubo ? 'material-tubo' : '';
    }

    public function obtenerIconoMaterial($orden)
    {
        if ($this->produccionService->esMaterialTubo($orden)) {
            return 'fas fa-circle text-secondary';
        } elseif ($this->produccionService->requiereCantidad($orden)) {
            return 'fas fa-cubes text-info';
        } else {
            return 'fas fa-weight text-primary';
        }
    }

    public function generarDescripcionMaterial($orden)
    {
        $descripcion = [];

        if ($this->produccionService->esMaterialTubo($orden)) {
            $descripcion[] = "Material tipo TUBO";
            $descripcion[] = "Tara automática: 0 KG";
        }

        if ($this->produccionService->requiereCantidad($orden)) {
            $descripcion[] = "Requiere cantidad de unidades";
        }

        $descripcion[] = "Unidad: " . $this->produccionService->obtenerTextoUnidadMedida($orden['unidad_medida']);

        return implode(" • ", $descripcion);
    }
}
