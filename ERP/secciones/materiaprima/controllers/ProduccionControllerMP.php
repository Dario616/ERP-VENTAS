<?php
class ProduccionControllerMP
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
            'produccion' => 1
        ];
    }

    private function procesarPeticionPOST()
    {
        $accion = $_POST['accion'] ?? '';

        switch ($accion) {
            case 'agregar_produccion':
                $this->manejarCreacion();
                break;
            case 'editar_produccion':
                $this->manejarEdicion();
                break;
            case 'actualizar_peso':
                $this->manejarActualizacionPeso();
                break;
            case 'actualizar_cantidad':
                $this->manejarActualizacionCantidad();
                break;
            case 'finalizar_produccion':
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
            $datos['produccion'] = 1;

            $resultado = $this->materiaPrimaService->crear($datos);

            if ($resultado['success']) {
                $this->mensaje = "✅ <strong>Material agregado a producción exitosamente!</strong><br>";
                $this->mensaje .= "ID: #{$resultado['id']} - {$datos['descripcion']}<br>";
                $this->mensaje .= "Tipo: {$datos['tipo']}<br>";
                $this->mensaje .= "Unidad: {$datos['unidad']}<br>";

                if (!empty($datos['ncm'])) {
                    $this->mensaje .= "NCM: {$datos['ncm']}";
                }

                error_log("✅ Material agregado directamente a producción - ID: {$resultado['id']} - Descripción: {$datos['descripcion']} - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al agregar material a producción: " . $e->getMessage();
            error_log("💥 Error agregando material directo a producción: " . $e->getMessage());
        }
    }

    private function manejarEdicion()
    {
        try {
            $id = intval($_POST['id_editar_produccion'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de material en producción inválido");
            }

            $material = $this->materiaPrimaService->obtenerPorId($id);
            if (!$material || intval($material['produccion']) !== 1) {
                throw new Exception("El material no está en producción");
            }

            $datos = $this->extraerDatosFormulario($_POST);
            $datos['produccion'] = 1;

            $resultado = $this->materiaPrimaService->actualizar($id, $datos);

            if ($resultado['success']) {
                $this->mensaje = "✅ <strong>Material en producción actualizado exitosamente!</strong><br>";
                $this->mensaje .= "ID: #{$id} - {$datos['descripcion']}<br>";
                $this->mensaje .= "Tipo: {$datos['tipo']}<br>";
                $this->mensaje .= "Unidad: {$datos['unidad']}<br>";

                if (!empty($datos['ncm'])) {
                    $this->mensaje .= "NCM: {$datos['ncm']}";
                }

                error_log("🔄 Material en producción actualizado - ID: $id - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al actualizar material en producción: " . $e->getMessage();
            error_log("💥 Error actualizando material en producción: " . $e->getMessage());
        }
    }

    private function manejarActualizacionPeso()
    {
        try {
            $id = intval($_POST['id_peso'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de material inválido");
            }

            $material = $this->materiaPrimaService->obtenerPorId($id);
            if (!$material || intval($material['produccion']) !== 1) {
                throw new Exception("El material no está en producción");
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

                error_log("⚖️ Peso estimado actualizado en producción - ID: $id - Peso: {$resultado['peso_anterior']} kg → {$resultado['peso_nuevo']} kg - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al actualizar peso estimado: " . $e->getMessage();
            error_log("💥 Error actualizando peso estimado en producción: " . $e->getMessage());
        }
    }

    private function manejarActualizacionCantidad()
    {
        try {
            $id = intval($_POST['id_cantidad'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de material inválido");
            }

            $material = $this->materiaPrimaService->obtenerPorId($id);
            if (!$material || intval($material['produccion']) !== 1) {
                throw new Exception("El material no está en producción");
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

                error_log("🔢 Cantidad actualizada en producción - ID: $id - Cantidad: {$resultado['cantidad_anterior']} → {$resultado['cantidad_nueva']} unidades - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al actualizar cantidad: " . $e->getMessage();
            error_log("💥 Error actualizando cantidad en producción: " . $e->getMessage());
        }
    }

    private function manejarEliminacion()
    {
        try {
            $id = intval($_POST['id_finalizar'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de material inválido");
            }

            $material = $this->materiaPrimaService->obtenerPorId($id);
            if (!$material || intval($material['produccion']) !== 1) {
                throw new Exception("El material no está en producción");
            }

            $resultado = $this->materiaPrimaService->eliminar($id);

            if ($resultado['success']) {
                $this->mensaje = "✅ <strong>Material eliminado de producción exitosamente!</strong><br>";
                $this->mensaje .= "Se eliminó: {$material['descripcion']} - ID: #{$id}<br>";
                $this->mensaje .= "Tipo: " . ($material['tipo'] ?? 'Sin definir') . "<br>";
                $this->mensaje .= "Unidad: " . ($material['unidad'] ?? 'Sin definir');

                if (!empty($material['ncm'])) {
                    $this->mensaje .= "<br>NCM: {$material['ncm']}";
                }

                error_log("🗑️ Material eliminado de producción - ID: $id - Descripción: {$material['descripcion']} - Tipo: " . ($material['tipo'] ?? 'Sin definir') . " - Unidad: " . ($material['unidad'] ?? 'Sin definir') . " - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al eliminar material de producción: " . $e->getMessage();
            error_log("💥 Error eliminando material de producción: " . $e->getMessage());
        }
    }

    private function extraerDatosFormulario($datos)
    {
        return [
            'descripcion' => trim($datos['descripcion'] ?? ''),
            'tipo' => trim($datos['tipo'] ?? ''),
            'ncm' => trim($datos['ncm'] ?? ''),
            'unidad' => trim($datos['unidad'] ?? ''),
            'produccion' => 1,
        ];
    }

    public function obtenerDatosPaginacion()
    {
        return $this->materiaPrimaService->obtenerDatosPaginacion(
            $this->items_por_pagina,
            $this->pagina_actual,
            $this->filtros
        );
    }

    public function obtenerMaterialesProduccion()
    {
        return $this->materiaPrimaService->obtenerPorProduccion(1);
    }

    public function obtenerEstadisticasProduccion()
    {
        $materialesProduccion = $this->obtenerMaterialesProduccion();

        $estadisticas = [
            'total_materiales' => count($materialesProduccion),
            'total_por_tipo' => [],
            'total_por_unidad' => [],
            'cantidad_total' => 0,
            'peso_total_estimado' => 0
        ];

        foreach ($materialesProduccion as $material) {
            $tipo = $material['tipo'] ?? 'Sin definir';
            $estadisticas['total_por_tipo'][$tipo] = ($estadisticas['total_por_tipo'][$tipo] ?? 0) + 1;

            $unidad = $material['unidad'] ?? 'Sin definir';
            $estadisticas['total_por_unidad'][$unidad] = ($estadisticas['total_por_unidad'][$unidad] ?? 0) + 1;

            $cantidad = intval($material['cantidad'] ?? 0);
            $estadisticas['cantidad_total'] += $cantidad;

            $pesoEstimado = floatval($material['peso_estimado'] ?? 0);
            $estadisticas['peso_total_estimado'] += ($pesoEstimado * $cantidad);
        }

        return $estadisticas;
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
            'datosPaginacion' => $this->obtenerDatosPaginacion(),
            'estadisticasProduccion' => $this->obtenerEstadisticasProduccion()
        ];
    }

    public function obtenerMaterialProduccionPorId($id)
    {
        $material = $this->materiaPrimaService->obtenerPorId($id);

        if ($material && intval($material['produccion']) === 1) {
            return $material;
        }

        return null;
    }

    public function buscarMaterialesProduccion($criterios = [])
    {
        $criterios['produccion'] = 1;

        $resultados = [];

        if (!empty($criterios['descripcion'])) {
            $resultados = array_merge($resultados, $this->materiaPrimaService->buscarPorDescripcion($criterios['descripcion'], 1));
        }

        if (!empty($criterios['tipo'])) {
            $resultados = array_merge($resultados, $this->materiaPrimaService->buscarPorTipo($criterios['tipo'], 1));
        }

        if (!empty($criterios['unidad'])) {
            $resultados = array_merge($resultados, $this->materiaPrimaService->buscarPorUnidad($criterios['unidad'], 1));
        }

        if (!empty($criterios['ncm'])) {
            $resultados = array_merge($resultados, $this->materiaPrimaService->buscarPorNCM($criterios['ncm'], 1));
        }

        $materialesUnicos = [];
        foreach ($resultados as $material) {
            $materialesUnicos[$material['id']] = $material;
        }

        return array_values($materialesUnicos);
    }

    public function buscarPorDescripcion($termino)
    {
        return $this->materiaPrimaService->buscarPorDescripcion($termino, 1);
    }

    public function buscarPorNCM($termino)
    {
        return $this->materiaPrimaService->buscarPorNCM($termino, 1);
    }

    public function buscarPorTipo($tipo)
    {
        return $this->materiaPrimaService->buscarPorTipo($tipo, 1);
    }

    public function buscarPorUnidad($unidad)
    {
        return $this->materiaPrimaService->buscarPorUnidad($unidad, 1);
    }

    public function existeDescripcion($descripcion, $excluirId = null)
    {
        $materiaPrimaRepo = new MateriaPrimaRepository($this->conexion);
        return $materiaPrimaRepo->existeDescripcion($descripcion, $excluirId, 1);
    }

    public function existeNCM($ncm, $excluirId = null)
    {
        if (empty($ncm)) {
            return false;
        }

        $materiaPrimaRepo = new MateriaPrimaRepository($this->conexion);
        return $materiaPrimaRepo->existeNCM($ncm, $excluirId, 1);
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
            'produccion' => 1
        ];
        $this->pagina_actual = 1;
    }

    public function validarMaterialProduccion($datos)
    {
        $errores = [];

        $descripcion = trim($datos['descripcion'] ?? '');
        if (empty($descripcion)) {
            $errores[] = "La descripción es obligatoria";
        } elseif (strlen($descripcion) < 3) {
            $errores[] = "La descripción debe tener al menos 3 caracteres";
        } elseif (strlen($descripcion) > 200) {
            $errores[] = "La descripción no puede exceder 200 caracteres";
        }

        $tipo = trim($datos['tipo'] ?? '');
        if (empty($tipo)) {
            $errores[] = "El tipo es obligatorio";
        } elseif (!in_array($tipo, ['Materia Prima', 'Insumo'])) {
            $errores[] = "Tipo de material no válido";
        }

        $unidad = trim($datos['unidad'] ?? '');
        if (empty($unidad)) {
            $errores[] = "La unidad es obligatoria";
        } elseif (!in_array($unidad, ['Unidad', 'Kilos'])) {
            $errores[] = "Unidad no válida";
        }

        $ncm = trim($datos['ncm'] ?? '');
        if (!empty($ncm) && strlen($ncm) > 20) {
            $errores[] = "El código NCM no puede exceder 20 caracteres";
        }

        if (!empty($errores)) {
            throw new Exception(implode('. ', $errores));
        }

        return true;
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

    public function obtenerAlertasStockBajo($umbral = 5)
    {
        return $this->materiaPrimaService->obtenerStockBajo($umbral, 1);
    }

    public function estaDescripcionDisponible($descripcion, $excluir_id = null)
    {
        $materiaPrimaRepo = new MateriaPrimaRepository($this->conexion);
        return !$materiaPrimaRepo->existeDescripcion($descripcion, $excluir_id, 1);
    }

    public function estaNCMDisponible($ncm, $excluir_id = null)
    {
        if (empty($ncm)) {
            return true;
        }

        $materiaPrimaRepo = new MateriaPrimaRepository($this->conexion);
        return !$materiaPrimaRepo->existeNCM($ncm, $excluir_id, 1);
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
        return $this->materiaPrimaService->obtenerStockPorUnidad($unidad, 1);
    }

    public function obtenerStockBajo($umbralMinimo = 5)
    {
        return $this->materiaPrimaService->obtenerStockBajo($umbralMinimo, 1);
    }

    public function exportarDatosProduccion()
    {
        $filtros = $this->filtros;
        return $this->materiaPrimaService->exportarDatos($filtros);
    }
}
