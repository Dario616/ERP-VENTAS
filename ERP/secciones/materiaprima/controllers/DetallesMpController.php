<?php
require_once __DIR__ . '/../services/DetallesMpService.php';
require_once __DIR__ . '/../repository/MateriaPrimaRepository.php';

class DetallesMpController
{
    private $detallesMpService;
    private $materiaPrimaRepo;

    private $mensaje = '';
    private $error = '';

    private $pagina_actual = 1;
    private $items_por_pagina = 10;
    private $filtros = [];

    private $vista_agrupada = true;
    private $proveedor_seleccionado = null;

    public function __construct($conexion)
    {
        $this->detallesMpService = new DetallesMpService($conexion);
        $this->materiaPrimaRepo = new MateriaPrimaRepository($conexion);
    }

    public function manejarPeticion($id_materia)
    {
        $this->pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
        $this->inicializarFiltros();

        $this->vista_agrupada = !isset($_GET['vista']) || $_GET['vista'] !== 'individual';
        $this->proveedor_seleccionado = $_GET['proveedor'] ?? null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->procesarPeticionPOST($id_materia);

            $accion = $_POST['accion'] ?? '';
            if (!in_array($accion, ['buscar_barcode', 'obtener_detalles_proveedor'])) {
                $this->redirectDespuesDePost($id_materia);
                return null;
            }
        }

        $this->obtenerMensajesDeSesion();

        return $this->obtenerDatosVista($id_materia);
    }

    private function redirectDespuesDePost($id_materia)
    {
        if (!empty($this->mensaje)) {
            $_SESSION['mensaje_exito_detalles'] = $this->mensaje;
        }
        if (!empty($this->error)) {
            $_SESSION['mensaje_error_detalles'] = $this->error;
        }

        $url_redirect = $_SERVER['PHP_SELF'] . '?id_materia=' . $id_materia;
        $filtrosUrl = $this->generarFiltrosUrl();

        if (!empty($filtrosUrl)) {
            $url_redirect .= $filtrosUrl;
        }

        if ($this->pagina_actual > 1) {
            $url_redirect .= '&pagina=' . $this->pagina_actual;
        }

        $url_redirect .= '&_t=' . time();

        header("Location: $url_redirect");
        exit();
    }

    private function obtenerMensajesDeSesion()
    {
        if (isset($_SESSION['mensaje_exito_detalles'])) {
            $this->mensaje = $_SESSION['mensaje_exito_detalles'];
            unset($_SESSION['mensaje_exito_detalles']);
        }

        if (isset($_SESSION['mensaje_error_detalles'])) {
            $this->error = $_SESSION['mensaje_error_detalles'];
            unset($_SESSION['mensaje_error_detalles']);
        }
    }

    public function buscarPorCodigoBarras($barcode)
    {
        try {
            if (empty($barcode) || strlen(trim($barcode)) < 3) {
                return [
                    'success' => false,
                    'datos' => null,
                    'error' => 'El código de barras debe tener al menos 3 caracteres'
                ];
            }

            $resultado = $this->detallesMpService->buscarPorCodigoBarras(trim($barcode));

            if ($resultado['success'] && $resultado['datos']) {
                return [
                    'success' => true,
                    'datos' => $resultado['datos'],
                    'error' => null
                ];
            } else {
                error_log("🔍 Código de barras no encontrado: $barcode");
                return [
                    'success' => false,
                    'datos' => null,
                    'error' => 'No se encontró ningún detalle con este código de barras'
                ];
            }
        } catch (Exception $e) {
            error_log("💥 Error buscando código de barras: $barcode - Error: " . $e->getMessage());
            return [
                'success' => false,
                'datos' => null,
                'error' => 'Error al buscar el código: ' . $e->getMessage()
            ];
        }
    }

    public function obtenerDetallesProveedorAjax($id_materia, $proveedor)
    {
        try {
            $validacion = $this->detallesMpService->validarProveedor($proveedor);
            if (!$validacion['valido'] && $proveedor !== 'Sin Proveedor') {
                return [
                    'success' => false,
                    'datos' => null,
                    'error' => $validacion['error']
                ];
            }

            $resultado = $this->detallesMpService->obtenerDetallesProveedor($id_materia, $proveedor);

            if ($resultado['success']) {
                return [
                    'success' => true,
                    'datos' => $resultado,
                    'error' => null
                ];
            } else {
                return [
                    'success' => false,
                    'datos' => null,
                    'error' => $resultado['error'] ?? 'Error desconocido'
                ];
            }
        } catch (Exception $e) {
            error_log("💥 Error obteniendo detalles de proveedor via AJAX: $proveedor - Error: " . $e->getMessage());
            return [
                'success' => false,
                'datos' => null,
                'error' => 'Error al obtener detalles del proveedor: ' . $e->getMessage()
            ];
        }
    }

    private function inicializarFiltros()
    {
        $this->filtros = [
            'buscar_descripcion' => trim($_GET['buscar_descripcion'] ?? ''),
            'buscar_codigo' => trim($_GET['buscar_codigo'] ?? ''),
            'buscar_proveedor' => trim($_GET['buscar_proveedor'] ?? '')
        ];
    }

    private function procesarPeticionPOST($id_materia)
    {
        $accion = $_POST['accion'] ?? '';

        switch ($accion) {
            case 'crear':
                $this->manejarCreacion($id_materia);
                break;
            case 'editar':
                $this->manejarEdicion();
                break;
            case 'eliminar':
                $this->manejarEliminacion();
                break;
            case 'buscar_barcode':
                break;
            case 'obtener_detalles_proveedor':
                break;
            default:
                $this->error = "Acción no válida";
                break;
        }
    }

    private function manejarCreacion($id_materia)
    {
        try {
            $materiaPrima = $this->materiaPrimaRepo->obtenerPorId($id_materia);
            $descripcionMateria = $materiaPrima ? $materiaPrima['descripcion'] : "Materia ID: $id_materia";

            $requiereCantidad = $materiaPrima &&
                isset($materiaPrima['unidad']) &&
                strtolower($materiaPrima['unidad']) === 'unidad';

            $resultado = $this->detallesMpService->crearDetalle($_POST, $id_materia, $requiereCantidad);

            if ($resultado['success']) {
                $this->mensaje = "✅ <strong>Detalle registrado exitosamente!</strong><br>";
                $this->mensaje .= "ID: #{$resultado['id']} - <strong>{$descripcionMateria}</strong><br>";
                $this->mensaje .= "<strong>Código generado:</strong> <code>{$resultado['codigo_generado']}</code>";

                $campos_registrados = [];

                if (!empty($_POST['cantidad']) && intval($_POST['cantidad']) > 0) {
                    $campos_registrados[] = "Cantidad: " . $_POST['cantidad'] . " unidades";
                }

                if (!empty($_POST['peso']) && floatval($_POST['peso']) > 0) {
                    $campos_registrados[] = "Peso: " . $_POST['peso'] . " kg";
                }
                if (!empty($_POST['factura'])) {
                    $campos_registrados[] = "Factura: " . $_POST['factura'];
                }
                if (!empty($_POST['proveedor'])) {
                    $campos_registrados[] = "Proveedor: " . $_POST['proveedor'];
                }
                if (!empty($_POST['barcode'])) {
                    $campos_registrados[] = "Código barras: " . $_POST['barcode'];
                }

                if (!empty($campos_registrados)) {
                    $this->mensaje .= "<br><small class='text-muted'>Datos: " . implode(" | ", $campos_registrados) . "</small>";
                }

                error_log("✅ Detalle MP creado via controller - ID: {$resultado['id']} - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al registrar detalle: " . $e->getMessage();
        }
    }

    private function manejarEdicion()
    {
        try {
            $id = intval($_POST['id_editar'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de detalle inválido");
            }

            $detalleExistente = $this->detallesMpService->obtenerDetallePorId($id);
            $descripcionMateria = $detalleExistente ? $detalleExistente['descripcion_materia'] : "Detalle ID: $id";

            $materiaPrima = null;
            $requiereCantidad = false;
            if ($detalleExistente && $detalleExistente['id_materia']) {
                $materiaPrima = $this->materiaPrimaRepo->obtenerPorId($detalleExistente['id_materia']);
                $requiereCantidad = $materiaPrima &&
                    isset($materiaPrima['unidad']) &&
                    strtolower($materiaPrima['unidad']) === 'unidad';
            }

            $resultado = $this->detallesMpService->actualizarDetalle($id, $_POST, $requiereCantidad);

            if ($resultado['success']) {
                $this->mensaje = "✅ <strong>Detalle actualizado exitosamente!</strong><br>";
                $this->mensaje .= "ID: #{$id} - <strong>{$descripcionMateria}</strong><br>";

                if ($requiereCantidad && !empty($_POST['cantidad'])) {
                    $this->mensaje .= "<strong>Cantidad:</strong> {$_POST['cantidad']} unidades<br>";
                }

                $this->mensaje .= "<small class='text-muted'>Nota: El código único y la descripción de materia prima se mantienen sin cambios</small>";

                error_log("🔄 Detalle MP actualizado via controller - ID: $id - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al actualizar detalle: " . $e->getMessage();
        }
    }

    private function manejarEliminacion()
    {
        try {
            $id = intval($_POST['id_eliminar'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de detalle inválido");
            }

            $detalle = $this->detallesMpService->obtenerDetallePorId($id);
            if (!$detalle) {
                throw new Exception("El detalle no existe");
            }

            $resultado = $this->detallesMpService->eliminarDetalle($id);

            if ($resultado['success']) {
                $this->mensaje = "✅ <strong>Detalle eliminado exitosamente!</strong><br>";
                $this->mensaje .= "Se eliminó: <strong>{$detalle['descripcion_materia']}</strong> - ID: #{$id}<br>";
                $this->mensaje .= "Código: <code>{$detalle['codigo_unico']}</code>";

                error_log("🗑️ Detalle MP eliminado via controller - ID: $id - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al eliminar detalle: " . $e->getMessage();
        }
    }

    public function obtenerDatosPaginacion($id_materia)
    {
        if ($this->vista_agrupada && empty($this->proveedor_seleccionado)) {
            return $this->detallesMpService->obtenerDatosPaginacionAgrupados(
                $id_materia,
                $this->items_por_pagina,
                $this->pagina_actual,
                $this->filtros
            );
        } elseif (!empty($this->proveedor_seleccionado)) {
            return $this->detallesMpService->obtenerDetallesProveedor(
                $id_materia,
                $this->proveedor_seleccionado,
                $this->items_por_pagina,
                $this->pagina_actual
            );
        } else {
            return $this->detallesMpService->obtenerDatosPaginacion(
                $id_materia,
                $this->items_por_pagina,
                $this->pagina_actual,
                $this->filtros
            );
        }
    }

    public function generarFiltrosUrl()
    {
        $params = [];

        foreach ($this->filtros as $key => $value) {
            if (!empty($value)) {
                $params[] = urlencode($key) . '=' . urlencode($value);
            }
        }

        if (!$this->vista_agrupada) {
            $params[] = 'vista=individual';
        }

        if (!empty($this->proveedor_seleccionado)) {
            $params[] = 'proveedor=' . urlencode($this->proveedor_seleccionado);
        }

        return !empty($params) ? '&' . implode('&', $params) : '';
    }

    public function obtenerDatosVista($id_materia)
    {
        return [
            'mensaje' => $this->mensaje,
            'error' => $this->error,
            'pagina_actual' => $this->pagina_actual,
            'items_por_pagina' => $this->items_por_pagina,
            'filtros' => $this->filtros,
            'filtrosUrl' => $this->generarFiltrosUrl(),
            'datosPaginacion' => $this->obtenerDatosPaginacion($id_materia),
            'materiaPrima' => $this->materiaPrimaRepo->obtenerPorId($id_materia),
            'vista_agrupada' => $this->vista_agrupada,
            'proveedor_seleccionado' => $this->proveedor_seleccionado
        ];
    }
}
