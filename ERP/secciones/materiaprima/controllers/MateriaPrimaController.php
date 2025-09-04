<?php
class MateriaPrimaController
{
    private $materiaPrimaService;
    private $conexion;

    private $mensaje = '';
    private $error = '';

    private $pagina_actual = 1;
    private $items_por_pagina = 10;
    private $filtros = [];

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
        $materiaPrimaRepo = new MateriaPrimaRepository($conexion);
        $pesoHistorialRepo = new PesoEstimadoHistorialRepository($conexion);
        $this->materiaPrimaService = new MateriaPrimaService($materiaPrimaRepo, $pesoHistorialRepo);
    }

    public function manejarPeticion()
    {
        $this->pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
        $this->inicializarFiltros();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->procesarPeticionPOST();
            $this->redirectDespuesDePost();
            return null;
        }

        $this->obtenerMensajesDeSesion();

        return $this->obtenerDatosVista();
    }

    private function redirectDespuesDePost()
    {
        if (!empty($this->mensaje)) {
            $_SESSION['mensaje_exito'] = $this->mensaje;
        }
        if (!empty($this->error)) {
            $_SESSION['mensaje_error'] = $this->error;
        }

        $url_redirect = $_SERVER['PHP_SELF'];
        $filtrosUrl = $this->generarFiltrosUrl();

        if (!empty($filtrosUrl)) {
            $url_redirect .= '?' . ltrim($filtrosUrl, '&');
        }

        if ($this->pagina_actual > 1) {
            $separator = (strpos($url_redirect, '?') !== false) ? '&' : '?';
            $url_redirect .= $separator . 'pagina=' . $this->pagina_actual;
        }

        $separator = (strpos($url_redirect, '?') !== false) ? '&' : '?';
        $url_redirect .= $separator . '_t=' . time();

        header("Location: $url_redirect");
        exit();
    }

    private function obtenerMensajesDeSesion()
    {
        if (isset($_SESSION['mensaje_exito'])) {
            $this->mensaje = $_SESSION['mensaje_exito'];
            unset($_SESSION['mensaje_exito']);
        }

        if (isset($_SESSION['mensaje_error'])) {
            $this->error = $_SESSION['mensaje_error'];
            unset($_SESSION['mensaje_error']);
        }
    }

    private function inicializarFiltros()
    {
        $this->filtros = [
            'buscar_descripcion' => trim($_GET['buscar_descripcion'] ?? ''),
            'buscar_ncm' => trim($_GET['buscar_ncm'] ?? ''),
            'buscar_tipo' => trim($_GET['buscar_tipo'] ?? ''),
            'produccion' => 0
        ];
    }

    private function procesarPeticionPOST()
    {
        $accion = $_POST['accion'] ?? '';

        switch ($accion) {
            case 'crear':
                $this->manejarCreacion();
                break;
            case 'editar':
                $this->manejarEdicion();
                break;
            case 'actualizar_peso':
                $this->manejarActualizacionPeso();
                break;
            case 'actualizar_cantidad':
                $this->manejarActualizacionCantidad();
                break;
            case 'eliminar':
                $this->manejarEliminacion();
                break;
            default:
                $this->error = "Acción no válida";
                break;
        }
    }

    private function manejarCreacion()
    {
        try {
            $datos = $this->extraerDatosFormulario($_POST);
            $resultado = $this->materiaPrimaService->crear($datos);

            if ($resultado['success']) {
                $this->mensaje = "✅ <strong>Materia Prima registrada exitosamente!</strong><br>";
                $this->mensaje .= "ID: #{$resultado['id']} - {$datos['descripcion']}<br>";
                $this->mensaje .= "Tipo: {$datos['tipo']}<br>";
                $this->mensaje .= "Unidad: {$datos['unidad']}<br>";

                if (!empty($datos['cantidad']) && $datos['cantidad'] > 0) {
                    $this->mensaje .= "Cantidad: " . number_format($datos['cantidad']) . "<br>";
                }

                if (!empty($datos['peso_estimado'])) {
                    $this->mensaje .= "Peso estimado: " . number_format($datos['peso_estimado'], 2) . " kg<br>";
                }

                if (!empty($datos['ncm'])) {
                    $this->mensaje .= "NCM: {$datos['ncm']}";
                }

                error_log("✅ Materia Prima creada - ID: {$resultado['id']} - Tipo: {$datos['tipo']} - Unidad: {$datos['unidad']} - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al registrar materia prima: " . $e->getMessage();
            error_log("💥 Error creando materia prima: " . $e->getMessage());
        }
    }

    private function manejarEdicion()
    {
        try {
            $id = intval($_POST['id_editar'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de materia prima inválido");
            }

            $datos = $this->extraerDatosFormulario($_POST);
            $resultado = $this->materiaPrimaService->actualizar($id, $datos);

            if ($resultado['success']) {
                $this->mensaje = "✅ <strong>Materia Prima actualizada exitosamente!</strong><br>";
                $this->mensaje .= "ID: #{$id} - {$datos['descripcion']}<br>";
                $this->mensaje .= "Tipo: {$datos['tipo']}<br>";
                $this->mensaje .= "Unidad: {$datos['unidad']}<br>";

                if (!empty($datos['cantidad']) && $datos['cantidad'] > 0) {
                    $this->mensaje .= "Cantidad: " . number_format($datos['cantidad']) . "<br>";
                }

                if (!empty($datos['peso_estimado'])) {
                    $this->mensaje .= "Peso estimado: " . number_format($datos['peso_estimado'], 2) . " kg<br>";
                }

                if (!empty($datos['ncm'])) {
                    $this->mensaje .= "NCM: {$datos['ncm']}";
                }

                error_log("🔄 Materia Prima actualizada - ID: $id - Tipo: {$datos['tipo']} - Unidad: {$datos['unidad']} - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al actualizar materia prima: " . $e->getMessage();
            error_log("💥 Error actualizando materia prima: " . $e->getMessage());
        }
    }

    private function manejarActualizacionPeso()
    {
        try {
            $id = intval($_POST['id_peso'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de materia prima inválido");
            }

            $pesoEstimado = floatval($_POST['peso_estimado'] ?? 0);
            $motivo = trim($_POST['motivo_peso'] ?? '');
            $observaciones = trim($_POST['observaciones_peso'] ?? '');

            if ($pesoEstimado < 0) {
                throw new Exception("El peso estimado no puede ser negativo");
            }

            $resultado = $this->materiaPrimaService->actualizarPesoEstimado($id, $pesoEstimado, $motivo, $observaciones);

            if ($resultado['success']) {
                $this->mensaje = "✅ <strong>Peso estimado actualizado exitosamente!</strong><br>";
                $this->mensaje .= "ID: #{$id}<br>";
                $this->mensaje .= "Peso anterior: " . number_format($resultado['peso_anterior'], 2) . " kg<br>";
                $this->mensaje .= "Peso nuevo: " . number_format($resultado['peso_nuevo'], 2) . " kg<br>";

                if (!empty($motivo)) {
                    $this->mensaje .= "Motivo: " . htmlspecialchars($motivo);
                }

                error_log("⚖️ Peso estimado actualizado - ID: $id - Peso: {$resultado['peso_anterior']} kg → {$resultado['peso_nuevo']} kg - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al actualizar peso estimado: " . $e->getMessage();
            error_log("💥 Error actualizando peso estimado: " . $e->getMessage());
        }
    }

    private function manejarActualizacionCantidad()
    {
        try {
            $id = intval($_POST['id_cantidad'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de materia prima inválido");
            }

            $cantidad = intval($_POST['cantidad'] ?? 0);
            $motivo = trim($_POST['motivo_cantidad'] ?? '');
            $observaciones = trim($_POST['observaciones_cantidad'] ?? '');

            if ($cantidad < 0) {
                throw new Exception("La cantidad no puede ser negativa");
            }

            $resultado = $this->materiaPrimaService->actualizarCantidad($id, $cantidad, $motivo, $observaciones);

            if ($resultado['success']) {
                $this->mensaje = "✅ <strong>Cantidad actualizada exitosamente!</strong><br>";
                $this->mensaje .= "ID: #{$id}<br>";
                $this->mensaje .= "Cantidad anterior: " . number_format($resultado['cantidad_anterior']) . " unidades<br>";
                $this->mensaje .= "Cantidad nueva: " . number_format($resultado['cantidad_nueva']) . " unidades<br>";

                if (!empty($motivo)) {
                    $this->mensaje .= "Motivo: " . htmlspecialchars($motivo);
                }

                error_log("🔢 Cantidad actualizada - ID: $id - Cantidad: {$resultado['cantidad_anterior']} → {$resultado['cantidad_nueva']} unidades - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al actualizar cantidad: " . $e->getMessage();
            error_log("💥 Error actualizando cantidad: " . $e->getMessage());
        }
    }

    private function manejarEliminacion()
    {
        try {
            $id = intval($_POST['id_eliminar'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de materia prima inválido");
            }

            $resultado = $this->materiaPrimaService->eliminar($id);

            if ($resultado['success']) {
                $materiaPrima = $resultado['data'];
                $this->mensaje = "✅ <strong>Materia Prima eliminada exitosamente!</strong><br>";
                $this->mensaje .= "Se eliminó: {$materiaPrima['descripcion']} - ID: #{$id}<br>";
                $this->mensaje .= "Tipo: " . ($materiaPrima['tipo'] ?? 'Sin definir') . "<br>";
                $this->mensaje .= "Unidad: " . ($materiaPrima['unidad'] ?? 'Sin definir');

                if (!empty($materiaPrima['ncm'])) {
                    $this->mensaje .= "<br>NCM: {$materiaPrima['ncm']}";
                }

                error_log("🗑️ Materia Prima eliminada - ID: $id - Descripción: {$materiaPrima['descripcion']} - Tipo: " . ($materiaPrima['tipo'] ?? 'Sin definir') . " - Unidad: " . ($materiaPrima['unidad'] ?? 'Sin definir') . " - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al eliminar materia prima: " . $e->getMessage();
            error_log("💥 Error eliminando materia prima: " . $e->getMessage());
        }
    }

    private function extraerDatosFormulario($datos)
    {
        $resultado = [
            'descripcion' => trim($datos['descripcion'] ?? ''),
            'tipo' => trim($datos['tipo'] ?? ''),
            'ncm' => trim($datos['ncm'] ?? ''),
            'unidad' => trim($datos['unidad'] ?? ''),
            'produccion' => 0,
        ];
        return $resultado;
    }

    public function obtenerDatosPaginacion()
    {
        return $this->materiaPrimaService->obtenerDatosPaginacion(
            $this->items_por_pagina,
            $this->pagina_actual,
            $this->filtros
        );
    }

    public function obtenerHistorialPesoEstimado($materiaPrimaId, $limit = 20, $offset = 0)
    {
        return $this->materiaPrimaService->obtenerHistorialPesoEstimado($materiaPrimaId, $limit, $offset);
    }

    public function obtenerEstadisticasPesoEstimado($materiaPrimaId = null)
    {
        return $this->materiaPrimaService->obtenerEstadisticasPesoEstimado($materiaPrimaId);
    }

    public function generarFiltrosUrl()
    {
        $params = [];

        $filtrosParaUrl = array_filter($this->filtros, function ($key) {
            return $key !== 'produccion';
        }, ARRAY_FILTER_USE_KEY);

        foreach ($filtrosParaUrl as $key => $value) {
            if (!empty($value)) {
                $params[] = urlencode($key) . '=' . urlencode($value);
            }
        }

        return !empty($params) ? implode('&', $params) : '';
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
            'datosPaginacion' => $this->obtenerDatosPaginacion()
        ];
    }

    public function obtenerMateriaPrimaPorId($id)
    {
        return $this->materiaPrimaService->obtenerPorId($id);
    }

    public function buscarPorDescripcion($termino)
    {
        return $this->materiaPrimaService->buscarPorDescripcion($termino);
    }

    public function buscarPorNCM($termino)
    {
        return $this->materiaPrimaService->buscarPorNCM($termino);
    }

    public function buscarPorTipo($tipo)
    {
        return $this->materiaPrimaService->buscarPorTipo($tipo);
    }

    public function buscarPorUnidad($unidad)
    {
        return $this->materiaPrimaService->buscarPorUnidad($unidad);
    }

    public function obtenerTodasOrdenadas()
    {
        return $this->materiaPrimaService->obtenerTodasOrdenadas();
    }

    public function obtenerPorTipo($tipo)
    {
        return $this->materiaPrimaService->obtenerPorTipo($tipo);
    }

    public function obtenerPorUnidad($unidad)
    {
        return $this->materiaPrimaService->obtenerPorUnidad($unidad);
    }

    public function obtenerMaterialesConfiguracion()
    {
        return $this->materiaPrimaService->obtenerPorProduccion(0);
    }

    public function obtenerMaterialesProduccion()
    {
        return $this->materiaPrimaService->obtenerPorProduccion(1);
    }

    public function existeDescripcion($descripcion, $excluirId = null)
    {
        return $this->materiaPrimaService->existeDescripcion($descripcion, $excluirId);
    }

    public function existeNCM($ncm, $excluirId = null)
    {
        return $this->materiaPrimaService->existeNCM($ncm, $excluirId);
    }

    public function setItemsPorPagina($items)
    {
        $this->items_por_pagina = max(5, min(100, intval($items)));
    }

    public function limpiarFiltros()
    {
        $this->filtros = [
            'buscar_descripcion' => '',
            'buscar_ncm' => '',
            'buscar_tipo' => '',
            'produccion' => 0
        ];
        $this->pagina_actual = 1;
    }

    public function getMensaje()
    {
        return $this->mensaje;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getPaginaActual()
    {
        return $this->pagina_actual;
    }

    public function getItemsPorPagina()
    {
        return $this->items_por_pagina;
    }

    public function getFiltros()
    {
        return $this->filtros;
    }

    public function getConexion()
    {
        return $this->conexion;
    }

    public function formatearNCM($ncm)
    {
        return $this->materiaPrimaService->formatearNCM($ncm);
    }

    public function esTipoValido($tipo)
    {
        return in_array($tipo, ['Materia Prima', 'Insumo']);
    }

    public function esUnidadValida($unidad)
    {
        return in_array($unidad, ['Unidad', 'Kilos']);
    }

    public function getTiposDisponibles()
    {
        return ['Materia Prima', 'Insumo'];
    }

    public function getUnidadesDisponibles()
    {
        return ['Unidad', 'Kilos'];
    }

    public function obtenerStockPorUnidad($unidad = null)
    {
        return $this->materiaPrimaService->obtenerStockPorUnidad($unidad);
    }

    public function obtenerStockBajo($umbralMinimo = 5)
    {
        return $this->materiaPrimaService->obtenerStockBajo($umbralMinimo);
    }

    public function exportarDatosConfiguracion($filtros = [])
    {
        $filtros['produccion'] = 0;
        return $this->materiaPrimaService->exportarDatos($filtros);
    }
}
