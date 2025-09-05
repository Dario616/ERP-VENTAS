<?php
require_once __DIR__ . '/../services/RecetasService.php';

class RecetasController
{
    private $recetasService;
    private $mensaje = '';
    private $error = '';
    private $pagina_actual = 1;
    private $items_por_pagina = 15;
    private $filtros = [];

    public function __construct($conexion)
    {
        $this->recetasService = new RecetasService($conexion);
    }

    public function manejarPeticion()
    {
        $this->pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
        $this->inicializarFiltros();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->procesarPeticionPOST();

            if ($this->debeHacerRedirect()) {
                $this->redirectDespuesDePost();
                return null;
            }
        }

        $this->obtenerMensajesDeSesion();

        return $this->obtenerDatosVista();
    }

    private function debeHacerRedirect()
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            return false;
        }

        if (isset($_POST['ajax']) && $_POST['ajax'] === 'true') {
            return false;
        }

        return true;
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
        $params = [];

        foreach ($this->filtros as $key => $value) {
            if (!empty($value)) {
                $params[] = urlencode($key) . '=' . urlencode($value);
            }
        }

        if ($this->pagina_actual > 1) {
            $params[] = 'pagina=' . $this->pagina_actual;
        }

        $accion = $this->determinarAccionRealizada();
        if ($accion) {
            $params[] = 'action=' . urlencode($accion);
        }

        if (!empty($params)) {
            $url_redirect .= '?' . implode('&', $params);
        }

        $separator = (strpos($url_redirect, '?') !== false) ? '&' : '?';
        $url_redirect .= $separator . '_t=' . time();

        header("Location: $url_redirect");
        exit();
    }

    private function determinarAccionRealizada()
    {
        if (isset($_POST['accion'])) {
            return $_POST['accion'];
        }
        return null;
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

    public function manejarAjax()
    {
        header('Content-Type: application/json');

        $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

        switch ($accion) {
            case 'obtener_recetas_por_tipo':
                $this->obtenerRecetasPorTipo();
                break;
            case 'validar_porcentaje_completo':
                $this->validarPorcentajeCompleto();
                break;
            case 'obtener_versiones':
                $this->obtenerVersionesReceta();
                break;
            case 'obtener_detalle_version':
                $this->obtenerDetalleVersion();
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
            'buscar_tipo_producto' => trim($_GET['buscar_tipo_producto'] ?? ''),
            'buscar_materia_prima' => trim($_GET['buscar_materia_prima'] ?? ''),
            'id_tipo_producto' => intval($_GET['id_tipo_producto'] ?? 0),
            'version_receta' => intval($_GET['version_receta'] ?? 0)
        ];
    }

    private function procesarPeticionPOST()
    {
        try {
            $accion = $_POST['accion'] ?? '';
            $usuario = $_SESSION['nombre'] ?? 'SISTEMA';

            switch ($accion) {
                case 'crear':
                    $this->manejarCreacion($usuario);
                    break;
                case 'crear_multiples':
                    $this->manejarCreacionMultiple($usuario);
                    break;
                case 'crear_nueva_version':
                    $this->manejarCreacionNuevaVersion($usuario);
                    break;
                case 'editar':
                    $this->manejarEdicion($usuario);
                    break;
                case 'eliminar':
                    $this->manejarEliminacion($usuario);
                    break;
                case 'eliminar_version':
                    $this->manejarEliminacionVersion($usuario);
                    break;
                case 'eliminar_todas_recetas':
                    $this->manejarEliminacionCompleta($usuario);
                    break;
                default:
                    $this->error = "❌ Acción no válida: $accion";
                    break;
            }
        } catch (Exception $e) {
            $this->error = "❌ Error inesperado: " . $e->getMessage();
            error_log("💥 Error en RecetasController: " . $e->getMessage());
        }
    }

    private function manejarCreacion($usuario)
    {
        try {
            $resultado = $this->recetasService->crearReceta($_POST, $usuario);

            if ($resultado['success']) {
                $tipo_materia = isset($_POST['es_materia_extra']) && $_POST['es_materia_extra'] ? 'EXTRA' : 'PRINCIPAL';
                $unidad = $tipo_materia === 'EXTRA' ? ($_POST['unidad_medida_extra'] ?? 'unidades') : '%';

                $this->mensaje = "✅ <strong>Receta registrada exitosamente!</strong><br>";
                $this->mensaje .= "ID: #{$resultado['id']}<br>";
                $this->mensaje .= "<strong>Tipo:</strong> $tipo_materia<br>";
                $this->mensaje .= "<strong>Cantidad:</strong> {$_POST['cantidad_por_kilo']} $unidad<br>";
                $this->mensaje .= "<strong>Versión:</strong> " . ($_POST['version_receta'] ?? 'Auto') . "<br>";

                error_log("✅ Receta creada - ID: {$resultado['id']} - Tipo: $tipo_materia - Usuario: $usuario");
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al registrar receta: " . $e->getMessage();
            error_log("💥 Error creando receta: " . $e->getMessage());
        }
    }

    private function manejarCreacionMultiple($usuario)
    {
        try {
            $id_tipo_producto = intval($_POST['id_tipo_producto'] ?? 0);
            $materias_primas = $_POST['materias_primas'] ?? [];

            if ($id_tipo_producto <= 0) {
                throw new Exception("Debe seleccionar un tipo de producto válido");
            }

            if (empty($materias_primas) || !is_array($materias_primas)) {
                throw new Exception("Debe agregar al menos una materia prima");
            }

            $materias_procesadas = $this->procesarMateriasPrimas($materias_primas);

            if (empty($materias_procesadas)) {
                throw new Exception("No hay materias primas válidas para procesar");
            }

            $resultado = $this->recetasService->crearRecetasMultiples($id_tipo_producto, $materias_procesadas, $usuario);

            if ($resultado['success']) {
                $tiposProducto = $this->recetasService->obtenerTiposProducto();
                $nombreTipo = '';

                foreach ($tiposProducto as $tipo) {
                    if ($tipo['id'] == $id_tipo_producto) {
                        $nombreTipo = $tipo['desc'];
                        break;
                    }
                }

                $principales = array_filter($resultado['recetas_creadas'], function ($r) {
                    return !isset($r['es_extra']) || !$r['es_extra'];
                });
                $extras = array_filter($resultado['recetas_creadas'], function ($r) {
                    return isset($r['es_extra']) && $r['es_extra'];
                });

                $this->mensaje = "✅ <strong>Nueva versión de receta creada exitosamente!</strong><br>";
                $this->mensaje .= "<strong>Producto:</strong> $nombreTipo<br>";
                $this->mensaje .= "<strong>Versión:</strong> {$resultado['version_creada']} - {$resultado['nombre_receta']}<br>";
                $this->mensaje .= "<strong>Total creadas:</strong> {$resultado['total_creadas']} recetas<br>";
                $this->mensaje .= "<strong>Materias principales:</strong> " . count($principales) . " (100%)<br>";

                if (count($extras) > 0) {
                    $this->mensaje .= "<strong>Materias extras:</strong> " . count($extras) . "<br>";
                }

                if (!empty($resultado['recetas_creadas'])) {
                    $this->mensaje .= "<br><strong>Detalle:</strong><br>";

                    if (!empty($principales)) {
                        $this->mensaje .= "<em>Principales:</em><br>";
                        foreach ($principales as $receta) {
                            $this->mensaje .= "• {$receta['materia_prima']} - {$receta['cantidad']}%<br>";
                        }
                    }

                    if (!empty($extras)) {
                        $this->mensaje .= "<em>Extras:</em><br>";
                        foreach ($extras as $receta) {
                            $unidad = $receta['unidad_medida'] ?? 'unidades';
                            $this->mensaje .= "• {$receta['materia_prima']} - {$receta['cantidad']} $unidad<br>";
                        }
                    }
                }

                error_log("✅ Nueva versión creada - Tipo: $id_tipo_producto - Versión: {$resultado['version_creada']} - Usuario: $usuario");
            } else {
                $errorMsg = !empty($resultado['errores']) ? implode('<br>', $resultado['errores']) : $resultado['error'];
                throw new Exception($errorMsg);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al crear nueva versión de receta: " . $e->getMessage();
            error_log("💥 Error creación múltiple: " . $e->getMessage());
        }
    }

    private function procesarMateriasPrimas($materias_primas_raw)
    {
        $materias_procesadas = [];

        foreach ($materias_primas_raw as $key => $materia) {
            if (empty($materia['id_materia_prima']) || empty($materia['cantidad_por_kilo'])) {
                continue;
            }

            $es_extra = isset($materia['es_materia_extra']) && $materia['es_materia_extra'] === 'true';

            $materia_procesada = [
                'id_materia_prima' => intval($materia['id_materia_prima']),
                'cantidad_por_kilo' => floatval($materia['cantidad_por_kilo']),
                'es_materia_extra' => $es_extra
            ];

            if ($es_extra) {
                $materia_procesada['unidad_medida_extra'] = trim($materia['unidad_medida_extra'] ?? 'unidades');
            }

            $materias_disponibles = $this->recetasService->obtenerMateriasPrimas();
            foreach ($materias_disponibles as $mp) {
                if ($mp['id'] == $materia_procesada['id_materia_prima']) {
                    $materia_procesada['nombre_materia'] = $mp['descripcion'];
                    break;
                }
            }

            $materias_procesadas[] = $materia_procesada;
        }

        return $materias_procesadas;
    }

    private function manejarCreacionNuevaVersion($usuario)
    {
        try {
            $id_tipo_producto = intval($_POST['id_tipo_producto'] ?? 0);
            $materias_primas = $_POST['materias_primas'] ?? [];
            $nombre_receta = trim($_POST['nombre_receta'] ?? '');
            $version_especifica = intval($_POST['version_receta'] ?? 0);

            if ($id_tipo_producto <= 0) {
                throw new Exception("Debe seleccionar un tipo de producto válido");
            }

            if (empty($materias_primas) || !is_array($materias_primas)) {
                throw new Exception("Debe agregar al menos una materia prima");
            }

            if (empty($nombre_receta)) {
                $versiones_existentes = $this->recetasService->listarVersionesReceta($id_tipo_producto);
                $total_versiones = $versiones_existentes['total_versiones'] ?? 0;
                $nombre_receta = $total_versiones > 0 ? "Receta Versión " . ($total_versiones + 1) : "Receta Principal";
            }

            $materias_procesadas = $this->procesarMateriasPrimas($materias_primas);

            $resultado = $this->recetasService->crearRecetasMultiplesConVersion(
                $id_tipo_producto,
                $materias_procesadas,
                $nombre_receta,
                $version_especifica > 0 ? $version_especifica : null,
                $usuario
            );

            if ($resultado['success']) {
                $tiposProducto = $this->recetasService->obtenerTiposProducto();
                $nombreTipo = '';

                foreach ($tiposProducto as $tipo) {
                    if ($tipo['id'] == $id_tipo_producto) {
                        $nombreTipo = $tipo['desc'];
                        break;
                    }
                }

                $this->mensaje = "✅ <strong>Nueva versión específica creada exitosamente!</strong><br>";
                $this->mensaje .= "<strong>Producto:</strong> $nombreTipo<br>";
                $this->mensaje .= "<strong>Versión:</strong> {$resultado['version_creada']} - {$resultado['nombre_receta']}<br>";
                $this->mensaje .= "<strong>Total creadas:</strong> {$resultado['total_creadas']} recetas<br>";

                error_log("✅ Nueva versión específica creada - Tipo: $id_tipo_producto - Versión: {$resultado['version_creada']} - Usuario: $usuario");
            } else {
                $errorMsg = !empty($resultado['errores']) ? implode('<br>', $resultado['errores']) : $resultado['error'];
                throw new Exception($errorMsg);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al crear nueva versión específica: " . $e->getMessage();
            error_log("💥 Error versión específica: " . $e->getMessage());
        }
    }

    private function manejarEdicion($usuario)
    {
        try {
            $id = intval($_POST['id_editar'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de receta inválido");
            }

            $resultado = $this->recetasService->actualizarReceta($id, $_POST, $usuario);

            if ($resultado['success']) {
                $tipo_materia = isset($_POST['es_materia_extra']) && $_POST['es_materia_extra'] ? 'EXTRA' : 'PRINCIPAL';
                $unidad = $tipo_materia === 'EXTRA' ? ($_POST['unidad_medida_extra'] ?? 'unidades') : '%';

                $this->mensaje = "✅ <strong>Receta actualizada exitosamente!</strong><br>";
                $this->mensaje .= "ID: #{$id}<br>";
                $this->mensaje .= "<strong>Tipo:</strong> $tipo_materia<br>";
                $this->mensaje .= "<strong>Nueva cantidad:</strong> {$_POST['cantidad_por_kilo']} $unidad<br>";

                if (isset($_POST['version_receta'])) {
                    $this->mensaje .= "<strong>Versión:</strong> {$_POST['version_receta']}<br>";
                }

                error_log("🔄 Receta actualizada - ID: $id - Tipo: $tipo_materia - Usuario: $usuario");
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al actualizar receta: " . $e->getMessage();
            error_log("💥 Error actualizando receta: " . $e->getMessage());
        }
    }

    private function manejarEliminacion($usuario)
    {
        try {
            $id = intval($_POST['id_eliminar'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de receta inválido");
            }

            $receta = $this->recetasService->obtenerRecetaPorId($id);
            if (!$receta) {
                throw new Exception("La receta no existe");
            }

            $resultado = $this->recetasService->eliminarReceta($id, $usuario);

            if ($resultado['success']) {
                $tipo_materia = $receta['es_materia_extra'] ? 'EXTRA' : 'PRINCIPAL';
                $unidad = $receta['es_materia_extra'] ? ($receta['unidad_medida_extra'] ?? 'unidades') : '%';

                $this->mensaje = "✅ <strong>Receta eliminada exitosamente!</strong><br>";
                $this->mensaje .= "Se eliminó: {$receta['tipo_producto_desc']} - {$receta['materia_prima_desc']}<br>";
                $this->mensaje .= "Tipo: $tipo_materia<br>";
                $this->mensaje .= "Versión: {$receta['version_receta']}<br>";
                $this->mensaje .= "Cantidad: {$receta['cantidad_por_kilo']} $unidad";

                error_log("🗑️ Receta eliminada - ID: $id - Tipo: $tipo_materia - Usuario: $usuario");
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al eliminar receta: " . $e->getMessage();
            error_log("💥 Error eliminando receta: " . $e->getMessage());
        }
    }

    private function manejarEliminacionVersion($usuario)
    {
        try {
            $id_tipo_producto = intval($_POST['id_tipo_producto'] ?? 0);
            $version_receta = intval($_POST['version_receta'] ?? 0);

            if ($id_tipo_producto <= 0 || $version_receta <= 0) {
                throw new Exception("Parámetros inválidos");
            }

            $resultado = $this->recetasService->eliminarRecetaVersion($id_tipo_producto, $version_receta, $usuario);

            if ($resultado['success']) {
                $this->mensaje = "✅ <strong>Versión de receta eliminada exitosamente!</strong><br>";
                $this->mensaje .= "Versión eliminada: $version_receta<br>";
                $this->mensaje .= "Registros afectados: {$resultado['registros_afectados']}<br>";
                $this->mensaje .= "<em>Se eliminaron tanto materias principales como extras</em>";

                error_log("🗑️ Versión eliminada - Tipo: $id_tipo_producto - Versión: $version_receta - Usuario: $usuario");
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al eliminar versión de receta: " . $e->getMessage();
            error_log("💥 Error eliminando versión: " . $e->getMessage());
        }
    }

    private function manejarEliminacionCompleta($usuario)
    {
        try {
            $id_tipo_producto = intval($_POST['id_tipo_producto'] ?? 0);

            if ($id_tipo_producto <= 0) {
                throw new Exception("ID de tipo de producto inválido");
            }

            $resultado = $this->recetasService->eliminarTodasRecetasTipo($id_tipo_producto, $usuario);

            if ($resultado['success']) {
                $this->mensaje = "✅ <strong>Todas las recetas eliminadas exitosamente!</strong><br>";
                $this->mensaje .= "Registros afectados: {$resultado['registros_afectados']}<br>";
                $this->mensaje .= "<strong>Se eliminaron TODAS las versiones (principales y extras) para este producto</strong>";

                error_log("🗑️ Todas las recetas eliminadas - Tipo: $id_tipo_producto - Usuario: $usuario");
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al eliminar todas las recetas: " . $e->getMessage();
            error_log("💥 Error eliminación completa: " . $e->getMessage());
        }
    }

    private function obtenerRecetasPorTipo()
    {
        try {
            $id_tipo_producto = intval($_POST['id_tipo_producto'] ?? $_GET['id_tipo_producto'] ?? 0);
            $version_receta = intval($_POST['version_receta'] ?? $_GET['version_receta'] ?? 0);

            if ($id_tipo_producto <= 0) {
                throw new Exception("ID de tipo de producto inválido");
            }

            if ($version_receta > 0) {
                $recetas = $this->recetasService->obtenerRecetasPorTipoProducto($id_tipo_producto, $version_receta);

                $principales = array_filter($recetas, function ($r) {
                    return !$r['es_materia_extra'];
                });
                $extras = array_filter($recetas, function ($r) {
                    return $r['es_materia_extra'];
                });

                echo json_encode([
                    'success' => true,
                    'recetas' => $recetas,
                    'principales' => array_values($principales),
                    'extras' => array_values($extras),
                    'total' => count($recetas),
                    'total_principales' => count($principales),
                    'total_extras' => count($extras),
                    'version_receta' => $version_receta
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'recetas' => [],
                    'principales' => [],
                    'extras' => [],
                    'total_versiones' => 0
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function validarPorcentajeCompleto()
    {
        try {
            $id_tipo_producto = intval($_POST['id_tipo_producto'] ?? $_GET['id_tipo_producto'] ?? 0);
            $version_receta = intval($_POST['version_receta'] ?? $_GET['version_receta'] ?? 0);

            if ($id_tipo_producto <= 0) {
                throw new Exception("ID de tipo de producto inválido");
            }

            if ($version_receta > 0) {
                $validacion = $this->recetasService->validarPorcentajeCompleto($id_tipo_producto, $version_receta);
            } else {
                $validacion = $this->recetasService->validarPorcentajeCompleto($id_tipo_producto);
            }

            echo json_encode([
                'success' => true,
                'validacion' => $validacion
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function obtenerVersionesReceta()
    {
        try {
            $id_tipo_producto = intval($_POST['id_tipo_producto'] ?? $_GET['id_tipo_producto'] ?? 0);

            if ($id_tipo_producto <= 0) {
                throw new Exception("ID de tipo de producto inválido");
            }

            $resultado = $this->recetasService->listarVersionesReceta($id_tipo_producto);
            echo json_encode($resultado);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function obtenerDetalleVersion()
    {
        try {
            $id_tipo_producto = intval($_POST['id_tipo_producto'] ?? $_GET['id_tipo_producto'] ?? 0);
            $version_receta = intval($_POST['version_receta'] ?? $_GET['version_receta'] ?? 0);

            if ($id_tipo_producto <= 0 || $version_receta <= 0) {
                throw new Exception("Parámetros inválidos");
            }

            $resultado = $this->recetasService->obtenerDetalleRecetaVersion($id_tipo_producto, $version_receta);
            echo json_encode($resultado);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function manejarPeticionAgrupada()
    {
        try {
            $mensaje = '';
            $error = '';
            $usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->procesarPeticionAgrupada($usuario);

                if ($this->debeHacerRedirect()) {
                    $this->redirectDespuesDePostAgrupada();
                    return null;
                }
            }

            $this->obtenerMensajesDeSesion();

            $filtros = $this->obtenerFiltrosAgrupados();
            $filtrosUrl = $this->construirUrlFiltrosAgrupados($filtros);

            $itemsPorPagina = 20;
            $paginaActual = max(1, intval($_GET['pagina'] ?? 1));

            $datosPaginacion = $this->recetasService->obtenerDatosPaginacionAgrupados($itemsPorPagina, $paginaActual, $filtros);

            $tiposProducto = $this->recetasService->obtenerTiposProducto();
            $materiasPrimas = $this->recetasService->obtenerMateriasPrimas();

            return [
                'mensaje' => $this->mensaje,
                'error' => $this->error,
                'datosPaginacion' => $datosPaginacion,
                'pagina_actual' => $paginaActual,
                'filtros' => $filtros,
                'filtrosUrl' => $filtrosUrl,
                'tiposProducto' => $tiposProducto,
                'materiasPrimas' => $materiasPrimas,
                'url_base' => $this->obtenerUrlBase()
            ];
        } catch (Exception $e) {
            error_log("💥 Error en controller manejando petición agrupada: " . $e->getMessage());
            return [
                'mensaje' => '',
                'error' => 'Error interno del sistema: ' . $e->getMessage(),
                'datosPaginacion' => ['registros' => [], 'total_registros' => 0, 'total_paginas' => 0],
                'pagina_actual' => 1,
                'filtros' => [],
                'filtrosUrl' => '',
                'tiposProducto' => [],
                'materiasPrimas' => [],
                'url_base' => $this->obtenerUrlBase()
            ];
        }
    }

    private function procesarPeticionAgrupada($usuario)
    {
        $accion = $_POST['accion'] ?? '';

        switch ($accion) {
            case 'crear_multiples':
                $resultado = $this->procesarCreacionMultiple($usuario);
                if ($resultado['success']) {
                    $principales = array_filter($resultado['recetas_creadas'], function ($r) {
                        return !isset($r['es_extra']) || !$r['es_extra'];
                    });
                    $extras = array_filter($resultado['recetas_creadas'], function ($r) {
                        return isset($r['es_extra']) && $r['es_extra'];
                    });

                    $this->mensaje = "✅ <strong>Nueva versión creada exitosamente!</strong><br>";
                    $this->mensaje .= "Total: {$resultado['total_creadas']} recetas (" . count($principales) . " principales + " . count($extras) . " extras)<br>";
                    $this->mensaje .= "Versión: {$resultado['version_creada']}";

                    error_log("✅ Versión múltiple creada - Total: {$resultado['total_creadas']} - Usuario: $usuario");
                } else {
                    $this->error = "❌ Error creando versión múltiple: " . $resultado['error'];
                    error_log("💥 Error versión múltiple: " . $resultado['error']);
                }
                break;

            case 'eliminar':
                $id = intval($_POST['id_eliminar'] ?? 0);
                $resultado = $this->recetasService->eliminarReceta($id, $usuario);
                if ($resultado['success']) {
                    $this->mensaje = "✅ <strong>Receta eliminada exitosamente!</strong>";
                    error_log("🗑️ Receta eliminada individual - ID: $id - Usuario: $usuario");
                } else {
                    $this->error = "❌ Error eliminando receta: " . $resultado['error'];
                    error_log("💥 Error eliminando individual: " . $resultado['error']);
                }
                break;

            case 'eliminar_version':
                $id_tipo_producto = intval($_POST['id_tipo_producto'] ?? 0);
                $version_receta = intval($_POST['version_receta'] ?? 0);
                $resultado = $this->recetasService->eliminarRecetaVersion($id_tipo_producto, $version_receta, $usuario);
                if ($resultado['success']) {
                    $this->mensaje = "✅ <strong>Versión $version_receta eliminada exitosamente!</strong><br>";
                    $this->mensaje .= "Registros afectados: {$resultado['registros_afectados']}";
                    error_log("🗑️ Versión eliminada agrupada - Tipo: $id_tipo_producto - Versión: $version_receta - Usuario: $usuario");
                } else {
                    $this->error = "❌ Error eliminando versión: " . $resultado['error'];
                    error_log("💥 Error eliminando versión agrupada: " . $resultado['error']);
                }
                break;

            case 'eliminar_todas_recetas':
                $id_tipo_producto = intval($_POST['id_tipo_producto'] ?? 0);
                $resultado = $this->recetasService->eliminarTodasRecetasTipo($id_tipo_producto, $usuario);
                if ($resultado['success']) {
                    $this->mensaje = "✅ <strong>Todas las versiones del producto eliminadas exitosamente!</strong><br>";
                    $this->mensaje .= "Registros afectados: {$resultado['registros_afectados']}";
                    error_log("🗑️ Todas las recetas eliminadas agrupada - Tipo: $id_tipo_producto - Usuario: $usuario");
                } else {
                    $this->error = "❌ Error eliminando todas las recetas: " . $resultado['error'];
                    error_log("💥 Error eliminación completa agrupada: " . $resultado['error']);
                }
                break;

            default:
                $this->error = "❌ Acción no válida: $accion";
                error_log("⚠️ Acción agrupada no válida: $accion");
                break;
        }
    }

    private function redirectDespuesDePostAgrupada()
    {
        if (!empty($this->mensaje)) {
            $_SESSION['mensaje_exito'] = $this->mensaje;
        }
        if (!empty($this->error)) {
            $_SESSION['mensaje_error'] = $this->error;
        }

        $url_redirect = $_SERVER['PHP_SELF'];
        $params = [];

        $filtros = $this->obtenerFiltrosAgrupados();
        foreach ($filtros as $key => $value) {
            if (!empty($value)) {
                $params[] = urlencode($key) . '=' . urlencode($value);
            }
        }

        $paginaActual = max(1, intval($_GET['pagina'] ?? 1));
        if ($paginaActual > 1) {
            $params[] = 'pagina=' . $paginaActual;
        }

        $accion = $_POST['accion'] ?? '';
        if ($accion) {
            $params[] = 'action=' . urlencode($accion);
        }

        if (!empty($params)) {
            $url_redirect .= '?' . implode('&', $params);
        }

        $separator = (strpos($url_redirect, '?') !== false) ? '&' : '?';
        $url_redirect .= $separator . '_t=' . time();

        header("Location: $url_redirect");
        exit();
    }

    private function procesarCreacionMultiple($usuario)
    {
        $id_tipo_producto = intval($_POST['id_tipo_producto'] ?? 0);
        $materias_primas_data = $_POST['materias_primas'] ?? [];
        $nombre_receta = trim($_POST['nombre_receta'] ?? '');

        if (empty($nombre_receta)) {
            $versiones = $this->recetasService->listarVersionesReceta($id_tipo_producto);
            $total = $versiones['total_versiones'] ?? 0;
            $nombre_receta = $total > 0 ? "Receta Versión " . ($total + 1) : "Receta Principal";
        }

        $materias_procesadas = $this->procesarMateriasPrimas($materias_primas_data);

        return $this->recetasService->crearRecetasMultiplesConVersion($id_tipo_producto, $materias_procesadas, $nombre_receta, null, $usuario);
    }

    public function obtenerDetalleReceta($id_tipo_producto)
    {
        try {
            $resultado = $this->recetasService->obtenerDetalleRecetaTipo($id_tipo_producto);
            return $resultado;
        } catch (Exception $e) {
            error_log("💥 Error obteniendo detalle de receta: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error interno: ' . $e->getMessage()
            ];
        }
    }

    private function obtenerFiltrosAgrupados()
    {
        return [
            'id_tipo_producto' => $_GET['id_tipo_producto'] ?? '',
            'buscar_tipo_producto' => trim($_GET['buscar_tipo_producto'] ?? ''),
            'version_receta' => $_GET['version_receta'] ?? '',
        ];
    }

    private function construirUrlFiltrosAgrupados($filtros)
    {
        $params = [];

        foreach ($filtros as $key => $value) {
            if (!empty($value)) {
                $params[] = urlencode($key) . '=' . urlencode($value);
            }
        }

        return !empty($params) ? '&' . implode('&', $params) : '';
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

    public function obtenerTiposProducto()
    {
        return $this->recetasService->obtenerTiposProducto();
    }

    public function obtenerMateriasPrimas()
    {
        return $this->recetasService->obtenerMateriasPrimas();
    }

    public function obtenerDatosPaginacion()
    {
        return $this->recetasService->obtenerDatosPaginacion(
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
            'tiposProducto' => $this->obtenerTiposProducto(),
            'materiasPrimas' => $this->obtenerMateriasPrimas()
        ];
    }

    private function obtenerUrlBase()
    {
        global $url_base;
        return $url_base ?? '/';
    }

    public function manejarAjaxAgrupado()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
            $respuesta = ['success' => false, 'error' => 'Acción no válida'];

            switch ($accion) {
                case 'obtener_detalle_receta':
                    $id_tipo_producto = intval($_GET['id_tipo_producto'] ?? $_POST['id_tipo_producto'] ?? 0);
                    $respuesta = $this->obtenerDetalleReceta($id_tipo_producto);
                    break;

                case 'obtener_versiones':
                    $id_tipo_producto = intval($_GET['id_tipo_producto'] ?? $_POST['id_tipo_producto'] ?? 0);
                    $respuesta = $this->recetasService->listarVersionesReceta($id_tipo_producto);
                    break;

                case 'obtener_detalle_version':
                    $id_tipo_producto = intval($_GET['id_tipo_producto'] ?? $_POST['id_tipo_producto'] ?? 0);
                    $version_receta = intval($_GET['version_receta'] ?? $_POST['version_receta'] ?? 0);
                    $respuesta = $this->recetasService->obtenerDetalleRecetaVersion($id_tipo_producto, $version_receta);
                    break;

                case 'validar_porcentaje_completo':
                    $id_tipo_producto = intval($_POST['id_tipo_producto'] ?? $_GET['id_tipo_producto'] ?? 0);
                    $version_receta = intval($_POST['version_receta'] ?? $_GET['version_receta'] ?? 0);
                    if ($id_tipo_producto > 0) {
                        if ($version_receta > 0) {
                            $validacion = $this->recetasService->validarPorcentajeCompleto($id_tipo_producto, $version_receta);
                        } else {
                            $validacion = $this->recetasService->validarPorcentajeCompleto($id_tipo_producto);
                        }
                        $respuesta = [
                            'success' => true,
                            'validacion' => $validacion
                        ];
                    } else {
                        $respuesta = [
                            'success' => false,
                            'error' => 'ID de tipo de producto inválido'
                        ];
                    }
                    break;

                default:
                    $respuesta = ['success' => false, 'error' => "Acción AJAX no válida: $accion"];
                    break;
            }

            echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (Exception $e) {
            error_log("💥 Error en AJAX agrupado: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
