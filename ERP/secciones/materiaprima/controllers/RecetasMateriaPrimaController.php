<?php
require_once __DIR__ . '/../services/RecetasMateriaPrimaService.php';

/**
 * Controlador para recetas de materia prima con Post-Redirect-Get
 * Permite crear materias primas compuestas usando otras materias primas
 */
class RecetasMateriaPrimaController
{
    private $recetasMateriaPrimaService;
    private $mensaje = '';
    private $error = '';
    private $pagina_actual = 1;
    private $items_por_pagina = 15;
    private $filtros = [];

    public function __construct($conexion)
    {
        $this->recetasMateriaPrimaService = new RecetasMateriaPrimaService($conexion);
    }

    /**
     * Método principal que maneja todas las peticiones CON PRG
     */
    public function manejarPeticion()
    {
        $this->pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
        $this->inicializarFiltros();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->procesarPeticionPOST();

            // ✅ NUEVO: Implementar Post-Redirect-Get
            if ($this->debeHacerRedirect()) {
                $this->redirectDespuesDePost();
                return null; // No continuar con el renderizado, se hizo redirect
            }
        }

        // ✅ NUEVO: Obtener mensajes de la sesión (si los hay)
        $this->obtenerMensajesDeSesion();

        return $this->obtenerDatosVista();
    }

    /**
     * ✅ NUEVO: Verificar si se debe hacer redirect después de POST
     */
    private function debeHacerRedirect()
    {
        // NO hacer redirect para peticiones AJAX
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            return false;
        }

        // NO hacer redirect si se marcó explícitamente que es AJAX
        if (isset($_POST['ajax']) && $_POST['ajax'] === 'true') {
            return false;
        }

        // SÍ hacer redirect para todas las operaciones POST regulares
        return true;
    }

    /**
     * ✅ NUEVO: Hacer redirect después de procesar POST exitosamente
     */
    private function redirectDespuesDePost()
    {
        // Guardar mensajes en la sesión para mostrarlos después del redirect
        if (!empty($this->mensaje)) {
            $_SESSION['mensaje_exito'] = $this->mensaje;
        }
        if (!empty($this->error)) {
            $_SESSION['mensaje_error'] = $this->error;
        }

        // Construir URL de redirect
        $url_redirect = $_SERVER['PHP_SELF'];
        $params = [];

        // Mantener filtros existentes
        foreach ($this->filtros as $key => $value) {
            if (!empty($value)) {
                $params[] = urlencode($key) . '=' . urlencode($value);
            }
        }

        // Mantener página actual si no es la primera
        if ($this->pagina_actual > 1) {
            $params[] = 'pagina=' . $this->pagina_actual;
        }

        // Agregar acción para tracking (opcional)
        $accion = $this->determinarAccionRealizada();
        if ($accion) {
            $params[] = 'action=' . urlencode($accion);
        }

        // Construir URL final
        if (!empty($params)) {
            $url_redirect .= '?' . implode('&', $params);
        }

        // Agregar timestamp para evitar caché
        $separator = (strpos($url_redirect, '?') !== false) ? '&' : '?';
        $url_redirect .= $separator . '_t=' . time();

        // Hacer el redirect
        header("Location: $url_redirect");
        exit();
    }

    /**
     * ✅ NUEVO: Determinar qué acción se realizó para el tracking
     */
    private function determinarAccionRealizada()
    {
        if (isset($_POST['accion'])) {
            return $_POST['accion'];
        }
        return null;
    }

    /**
     * ✅ NUEVO: Obtener mensajes de la sesión y limpiarlos
     */
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

    /**
     * Manejar peticiones AJAX
     */
    public function manejarAjax()
    {
        header('Content-Type: application/json');

        $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

        switch ($accion) {
            case 'obtener_recetas_por_materia':
                $this->obtenerRecetasPorMateria();
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
            case 'buscar_materias_disponibles':
                $this->buscarMateriasDisponibles();
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

    /**
     * Inicializar filtros de búsqueda
     */
    private function inicializarFiltros()
    {
        $this->filtros = [
            'buscar_materia_objetivo' => trim($_GET['buscar_materia_objetivo'] ?? ''),
            'buscar_materia_componente' => trim($_GET['buscar_materia_componente'] ?? ''),
            'id_materia_prima_objetivo' => intval($_GET['id_materia_prima_objetivo'] ?? 0),
            'version_receta' => intval($_GET['version_receta'] ?? 0)
        ];
    }

    /**
     * Procesar peticiones POST - MEJORADO con mejor manejo de errores
     */
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
            error_log("💥 Error en RecetasMateriaPrimaController: " . $e->getMessage());
        }
    }

    /**
     * Manejar creación de nueva receta individual - MEJORADO
     */
    private function manejarCreacion($usuario)
    {
        try {
            $resultado = $this->recetasMateriaPrimaService->crearReceta($_POST, $usuario);

            if ($resultado['success']) {
                $tipo_materia = isset($_POST['es_materia_extra']) && $_POST['es_materia_extra'] ? 'EXTRA' : 'PRINCIPAL';
                $unidad = $tipo_materia === 'EXTRA' ? ($_POST['unidad_medida_extra'] ?? 'unidades') : '%';

                $this->mensaje = "✅ <strong>Composición de materia prima registrada exitosamente!</strong><br>";
                $this->mensaje .= "ID: #{$resultado['id']}<br>";
                $this->mensaje .= "<strong>Tipo:</strong> $tipo_materia<br>";
                $this->mensaje .= "<strong>Cantidad:</strong> {$_POST['cantidad_por_kilo']} $unidad<br>";
                $this->mensaje .= "<strong>Versión:</strong> " . ($_POST['version_receta'] ?? 'Auto') . "<br>";

                error_log("✅ Composición materia prima creada - ID: {$resultado['id']} - Tipo: $tipo_materia - Usuario: $usuario");
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al registrar composición de materia prima: " . $e->getMessage();
            error_log("💥 Error creando composición: " . $e->getMessage());
        }
    }

    /**
     * Manejar creación múltiple de recetas - MEJORADO
     */
    private function manejarCreacionMultiple($usuario)
    {
        try {
            $id_materia_prima_objetivo = intval($_POST['id_materia_prima_objetivo'] ?? 0);
            $materias_primas = $_POST['materias_primas'] ?? [];

            if ($id_materia_prima_objetivo <= 0) {
                throw new Exception("Debe seleccionar una materia prima objetivo válida");
            }

            if (empty($materias_primas) || !is_array($materias_primas)) {
                throw new Exception("Debe agregar al menos una materia prima componente");
            }

            // Procesar y separar materias principales de extras
            $materias_procesadas = $this->procesarMateriasPrimas($materias_primas);

            if (empty($materias_procesadas)) {
                throw new Exception("No hay materias primas válidas para procesar");
            }

            $resultado = $this->recetasMateriaPrimaService->crearRecetasMultiples($id_materia_prima_objetivo, $materias_procesadas, $usuario);

            if ($resultado['success']) {
                $materiasPrimas = $this->recetasMateriaPrimaService->obtenerMateriasPrimas();
                $nombreMateria = '';

                foreach ($materiasPrimas as $materia) {
                    if ($materia['id'] == $id_materia_prima_objetivo) {
                        $nombreMateria = $materia['descripcion'];
                        break;
                    }
                }

                // Contar materias principales y extras
                $principales = array_filter($resultado['recetas_creadas'], function ($r) {
                    return !isset($r['es_extra']) || !$r['es_extra'];
                });
                $extras = array_filter($resultado['recetas_creadas'], function ($r) {
                    return isset($r['es_extra']) && $r['es_extra'];
                });

                $this->mensaje = "✅ <strong>Nueva versión de composición creada exitosamente!</strong><br>";
                $this->mensaje .= "<strong>Materia Prima:</strong> $nombreMateria<br>";
                $this->mensaje .= "<strong>Versión:</strong> {$resultado['version_creada']} - {$resultado['nombre_receta']}<br>";
                $this->mensaje .= "<strong>Total creadas:</strong> {$resultado['total_creadas']} componentes<br>";
                $this->mensaje .= "<strong>Componentes principales:</strong> " . count($principales) . " (100%)<br>";

                if (count($extras) > 0) {
                    $this->mensaje .= "<strong>Componentes extras:</strong> " . count($extras) . "<br>";
                }

                if (!empty($resultado['recetas_creadas'])) {
                    $this->mensaje .= "<br><strong>Detalle:</strong><br>";

                    // Mostrar principales
                    if (!empty($principales)) {
                        $this->mensaje .= "<em>Principales:</em><br>";
                        foreach ($principales as $receta) {
                            $this->mensaje .= "• {$receta['materia_prima']} - {$receta['cantidad']}%<br>";
                        }
                    }

                    // Mostrar extras
                    if (!empty($extras)) {
                        $this->mensaje .= "<em>Extras:</em><br>";
                        foreach ($extras as $receta) {
                            $unidad = $receta['unidad_medida'] ?? 'unidades';
                            $this->mensaje .= "• {$receta['materia_prima']} - {$receta['cantidad']} $unidad<br>";
                        }
                    }
                }

                error_log("✅ Nueva versión composición creada - Objetivo: $id_materia_prima_objetivo - Versión: {$resultado['version_creada']} - Usuario: $usuario");
            } else {
                $errorMsg = !empty($resultado['errores']) ? implode('<br>', $resultado['errores']) : $resultado['error'];
                throw new Exception($errorMsg);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al crear nueva versión de composición: " . $e->getMessage();
            error_log("💥 Error creación múltiple composición: " . $e->getMessage());
        }
    }

    /**
     * Procesar materias primas del formulario
     */
    private function procesarMateriasPrimas($materias_primas_raw)
    {
        $materias_procesadas = [];

        foreach ($materias_primas_raw as $key => $materia) {
            if (empty($materia['id_materia_prima']) || empty($materia['cantidad_por_kilo'])) {
                continue; // Saltar filas vacías
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

            // Obtener nombre de la materia prima
            $materias_disponibles = $this->recetasMateriaPrimaService->obtenerMateriasPrimas();
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

    /**
     * Manejar creación de nueva versión específica - MEJORADO
     */
    private function manejarCreacionNuevaVersion($usuario)
    {
        try {
            $id_materia_prima_objetivo = intval($_POST['id_materia_prima_objetivo'] ?? 0);
            $materias_primas = $_POST['materias_primas'] ?? [];
            $nombre_receta = trim($_POST['nombre_receta'] ?? '');
            $version_especifica = intval($_POST['version_receta'] ?? 0);

            if ($id_materia_prima_objetivo <= 0) {
                throw new Exception("Debe seleccionar una materia prima objetivo válida");
            }

            if (empty($materias_primas) || !is_array($materias_primas)) {
                throw new Exception("Debe agregar al menos una materia prima componente");
            }

            if (empty($nombre_receta)) {
                $versiones_existentes = $this->recetasMateriaPrimaService->listarVersionesReceta($id_materia_prima_objetivo);
                $total_versiones = $versiones_existentes['total_versiones'] ?? 0;
                $nombre_receta = $total_versiones > 0 ? "Composición Versión " . ($total_versiones + 1) : "Composición Principal";
            }

            // Procesar materias primas
            $materias_procesadas = $this->procesarMateriasPrimas($materias_primas);

            $resultado = $this->recetasMateriaPrimaService->crearRecetasMultiplesConVersion(
                $id_materia_prima_objetivo,
                $materias_procesadas,
                $nombre_receta,
                $version_especifica > 0 ? $version_especifica : null,
                $usuario
            );

            if ($resultado['success']) {
                $materiasPrimas = $this->recetasMateriaPrimaService->obtenerMateriasPrimas();
                $nombreMateria = '';

                foreach ($materiasPrimas as $materia) {
                    if ($materia['id'] == $id_materia_prima_objetivo) {
                        $nombreMateria = $materia['descripcion'];
                        break;
                    }
                }

                $this->mensaje = "✅ <strong>Nueva versión específica de composición creada exitosamente!</strong><br>";
                $this->mensaje .= "<strong>Materia Prima:</strong> $nombreMateria<br>";
                $this->mensaje .= "<strong>Versión:</strong> {$resultado['version_creada']} - {$resultado['nombre_receta']}<br>";
                $this->mensaje .= "<strong>Total creadas:</strong> {$resultado['total_creadas']} componentes<br>";

                error_log("✅ Nueva versión específica composición creada - Objetivo: $id_materia_prima_objetivo - Versión: {$resultado['version_creada']} - Usuario: $usuario");
            } else {
                $errorMsg = !empty($resultado['errores']) ? implode('<br>', $resultado['errores']) : $resultado['error'];
                throw new Exception($errorMsg);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al crear nueva versión específica de composición: " . $e->getMessage();
            error_log("💥 Error versión específica composición: " . $e->getMessage());
        }
    }

    /**
     * Manejar edición de receta existente - MEJORADO
     */
    private function manejarEdicion($usuario)
    {
        try {
            $id = intval($_POST['id_editar'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de composición inválido");
            }

            $resultado = $this->recetasMateriaPrimaService->actualizarReceta($id, $_POST, $usuario);

            if ($resultado['success']) {
                $tipo_materia = isset($_POST['es_materia_extra']) && $_POST['es_materia_extra'] ? 'EXTRA' : 'PRINCIPAL';
                $unidad = $tipo_materia === 'EXTRA' ? ($_POST['unidad_medida_extra'] ?? 'unidades') : '%';

                $this->mensaje = "✅ <strong>Componente de materia prima actualizado exitosamente!</strong><br>";
                $this->mensaje .= "ID: #{$id}<br>";
                $this->mensaje .= "<strong>Tipo:</strong> $tipo_materia<br>";
                $this->mensaje .= "<strong>Nueva cantidad:</strong> {$_POST['cantidad_por_kilo']} $unidad<br>";

                if (isset($_POST['version_receta'])) {
                    $this->mensaje .= "<strong>Versión:</strong> {$_POST['version_receta']}<br>";
                }

                error_log("🔄 Componente composición actualizado - ID: $id - Tipo: $tipo_materia - Usuario: $usuario");
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al actualizar componente de composición: " . $e->getMessage();
            error_log("💥 Error actualizando componente: " . $e->getMessage());
        }
    }

    /**
     * Manejar eliminación de receta individual - MEJORADO
     */
    private function manejarEliminacion($usuario)
    {
        try {
            $id = intval($_POST['id_eliminar'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de composición inválido");
            }

            $receta = $this->recetasMateriaPrimaService->obtenerRecetaPorId($id);
            if (!$receta) {
                throw new Exception("La composición no existe");
            }

            $resultado = $this->recetasMateriaPrimaService->eliminarReceta($id, $usuario);

            if ($resultado['success']) {
                $tipo_materia = $receta['es_materia_extra'] ? 'EXTRA' : 'PRINCIPAL';
                $unidad = $receta['es_materia_extra'] ? ($receta['unidad_medida_extra'] ?? 'unidades') : '%';

                $this->mensaje = "✅ <strong>Componente de composición eliminado exitosamente!</strong><br>";
                $this->mensaje .= "Se eliminó: {$receta['materia_prima_objetivo_desc']} - {$receta['materia_prima_desc']}<br>";
                $this->mensaje .= "Tipo: $tipo_materia<br>";
                $this->mensaje .= "Versión: {$receta['version_receta']}<br>";
                $this->mensaje .= "Cantidad: {$receta['cantidad_por_kilo']} $unidad";

                error_log("🗑️ Componente composición eliminado - ID: $id - Tipo: $tipo_materia - Usuario: $usuario");
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al eliminar componente de composición: " . $e->getMessage();
            error_log("💥 Error eliminando componente: " . $e->getMessage());
        }
    }

    /**
     * Manejar eliminación de versión completa - MEJORADO
     */
    private function manejarEliminacionVersion($usuario)
    {
        try {
            $id_materia_prima_objetivo = intval($_POST['id_materia_prima_objetivo'] ?? 0);
            $version_receta = intval($_POST['version_receta'] ?? 0);

            if ($id_materia_prima_objetivo <= 0 || $version_receta <= 0) {
                throw new Exception("Parámetros inválidos");
            }

            $resultado = $this->recetasMateriaPrimaService->eliminarRecetaVersion($id_materia_prima_objetivo, $version_receta, $usuario);

            if ($resultado['success']) {
                $this->mensaje = "✅ <strong>Versión de composición eliminada exitosamente!</strong><br>";
                $this->mensaje .= "Versión eliminada: $version_receta<br>";
                $this->mensaje .= "Registros afectados: {$resultado['registros_afectados']}<br>";
                $this->mensaje .= "<em>Se eliminaron tanto componentes principales como extras</em>";

                error_log("🗑️ Versión composición eliminada - Objetivo: $id_materia_prima_objetivo - Versión: $version_receta - Usuario: $usuario");
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al eliminar versión de composición: " . $e->getMessage();
            error_log("💥 Error eliminando versión composición: " . $e->getMessage());
        }
    }

    /**
     * Manejar eliminación completa de todas las recetas de una materia prima - MEJORADO
     */
    private function manejarEliminacionCompleta($usuario)
    {
        try {
            $id_materia_prima_objetivo = intval($_POST['id_materia_prima_objetivo'] ?? 0);

            if ($id_materia_prima_objetivo <= 0) {
                throw new Exception("ID de materia prima objetivo inválido");
            }

            $resultado = $this->recetasMateriaPrimaService->eliminarTodasRecetasMateria($id_materia_prima_objetivo, $usuario);

            if ($resultado['success']) {
                $this->mensaje = "✅ <strong>Todas las composiciones eliminadas exitosamente!</strong><br>";
                $this->mensaje .= "Registros afectados: {$resultado['registros_afectados']}<br>";
                $this->mensaje .= "<strong>Se eliminaron TODAS las versiones (principales y extras) para esta materia prima</strong>";

                error_log("🗑️ Todas las composiciones eliminadas - Objetivo: $id_materia_prima_objetivo - Usuario: $usuario");
            } else {
                throw new Exception($resultado['error']);
            }
        } catch (Exception $e) {
            $this->error = "❌ Error al eliminar todas las composiciones: " . $e->getMessage();
            error_log("💥 Error eliminación completa composiciones: " . $e->getMessage());
        }
    }

    /**
     * AJAX: Obtener recetas por materia prima objetivo
     */
    private function obtenerRecetasPorMateria()
    {
        try {
            $id_materia_prima_objetivo = intval($_POST['id_materia_prima_objetivo'] ?? $_GET['id_materia_prima_objetivo'] ?? 0);
            $version_receta = intval($_POST['version_receta'] ?? $_GET['version_receta'] ?? 0);

            if ($id_materia_prima_objetivo <= 0) {
                throw new Exception("ID de materia prima objetivo inválido");
            }

            if ($version_receta > 0) {
                $recetas = $this->recetasMateriaPrimaService->obtenerRecetasPorMateriaPrima($id_materia_prima_objetivo, $version_receta);

                // Separar principales de extras
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

    /**
     * AJAX: Validar porcentajes completos
     */
    private function validarPorcentajeCompleto()
    {
        try {
            $id_materia_prima_objetivo = intval($_POST['id_materia_prima_objetivo'] ?? $_GET['id_materia_prima_objetivo'] ?? 0);
            $version_receta = intval($_POST['version_receta'] ?? $_GET['version_receta'] ?? 0);

            if ($id_materia_prima_objetivo <= 0) {
                throw new Exception("ID de materia prima objetivo inválido");
            }

            if ($version_receta > 0) {
                $validacion = $this->recetasMateriaPrimaService->validarPorcentajeCompleto($id_materia_prima_objetivo, $version_receta);
            } else {
                $validacion = $this->recetasMateriaPrimaService->validarPorcentajeCompleto($id_materia_prima_objetivo);
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

    /**
     * AJAX: Obtener versiones de una materia prima
     */
    private function obtenerVersionesReceta()
    {
        try {
            $id_materia_prima_objetivo = intval($_POST['id_materia_prima_objetivo'] ?? $_GET['id_materia_prima_objetivo'] ?? 0);

            if ($id_materia_prima_objetivo <= 0) {
                throw new Exception("ID de materia prima objetivo inválido");
            }

            $resultado = $this->recetasMateriaPrimaService->listarVersionesReceta($id_materia_prima_objetivo);
            echo json_encode($resultado);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX: Obtener detalle de una versión específica
     */
    private function obtenerDetalleVersion()
    {
        try {
            $id_materia_prima_objetivo = intval($_POST['id_materia_prima_objetivo'] ?? $_GET['id_materia_prima_objetivo'] ?? 0);
            $version_receta = intval($_POST['version_receta'] ?? $_GET['version_receta'] ?? 0);

            if ($id_materia_prima_objetivo <= 0 || $version_receta <= 0) {
                throw new Exception("Parámetros inválidos");
            }

            $resultado = $this->recetasMateriaPrimaService->obtenerDetalleRecetaVersion($id_materia_prima_objetivo, $version_receta);
            echo json_encode($resultado);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX: Buscar materias primas disponibles
     */
    private function buscarMateriasDisponibles()
    {
        try {
            $termino = trim($_GET['termino'] ?? $_POST['termino'] ?? '');
            $id_materia_excluir = intval($_GET['excluir'] ?? $_POST['excluir'] ?? 0);

            $materias = $this->recetasMateriaPrimaService->buscarMateriasDisponibles($termino, $id_materia_excluir);

            echo json_encode([
                'success' => true,
                'materias' => $materias
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener datos de paginación
     */
    public function obtenerDatosPaginacion()
    {
        return $this->recetasMateriaPrimaService->obtenerDatosPaginacion(
            $this->items_por_pagina,
            $this->pagina_actual,
            $this->filtros
        );
    }

    /**
     * Obtener datos para la vista
     */
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
            'materiasPrimas' => $this->recetasMateriaPrimaService->obtenerMateriasPrimas()
        ];
    }

    /**
     * Generar URL con filtros para paginación
     */
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

    /**
     * Obtener materias primas
     */
    public function obtenerMateriasPrimas()
    {
        return $this->recetasMateriaPrimaService->obtenerMateriasPrimas();
    }

    /**
     * Manejar petición principal para vista agrupada CON PRG - MEJORADO
     */
    public function manejarPeticionAgrupada()
    {
        try {
            $mensaje = '';
            $error = '';
            $usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->procesarPeticionAgrupada($usuario);

                // ✅ NUEVO: Implementar Post-Redirect-Get para petición agrupada
                if ($this->debeHacerRedirect()) {
                    $this->redirectDespuesDePostAgrupada();
                    return null; // No continuar con el renderizado
                }
            }

            // ✅ NUEVO: Obtener mensajes de la sesión
            $this->obtenerMensajesDeSesion();

            $filtros = $this->obtenerFiltrosAgrupados();
            $filtrosUrl = $this->construirUrlFiltrosAgrupados($filtros);

            $itemsPorPagina = 20;
            $paginaActual = max(1, intval($_GET['pagina'] ?? 1));

            $datosPaginacion = $this->recetasMateriaPrimaService->obtenerDatosPaginacionAgrupados($itemsPorPagina, $paginaActual, $filtros);

            $materiasPrimas = $this->recetasMateriaPrimaService->obtenerMateriasPrimas();

            return [
                'mensaje' => $this->mensaje,
                'error' => $this->error,
                'datosPaginacion' => $datosPaginacion,
                'pagina_actual' => $paginaActual,
                'filtros' => $filtros,
                'filtrosUrl' => $filtrosUrl,
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
                'materiasPrimas' => [],
                'url_base' => $this->obtenerUrlBase()
            ];
        }
    }

    /**
     * ✅ NUEVO: Procesar petición agrupada POST
     */
    private function procesarPeticionAgrupada($usuario)
    {
        $accion = $_POST['accion'] ?? '';

        switch ($accion) {
            case 'crear_multiples':
                $resultado = $this->procesarCreacionMultiple($usuario);
                if ($resultado['success']) {
                    // Contar principales y extras
                    $principales = array_filter($resultado['recetas_creadas'], function ($r) {
                        return !isset($r['es_extra']) || !$r['es_extra'];
                    });
                    $extras = array_filter($resultado['recetas_creadas'], function ($r) {
                        return isset($r['es_extra']) && $r['es_extra'];
                    });

                    $this->mensaje = "✅ <strong>Nueva composición creada exitosamente!</strong><br>";
                    $this->mensaje .= "Total: {$resultado['total_creadas']} componentes (" . count($principales) . " principales + " . count($extras) . " extras)<br>";
                    $this->mensaje .= "Versión: {$resultado['version_creada']}";

                    error_log("✅ Composición múltiple creada - Total: {$resultado['total_creadas']} - Usuario: $usuario");
                } else {
                    $this->error = "❌ Error creando composición múltiple: " . $resultado['error'];
                    error_log("💥 Error composición múltiple: " . $resultado['error']);
                }
                break;

            case 'eliminar':
                $id = intval($_POST['id_eliminar'] ?? 0);
                $resultado = $this->recetasMateriaPrimaService->eliminarReceta($id, $usuario);
                if ($resultado['success']) {
                    $this->mensaje = "✅ <strong>Componente eliminado exitosamente!</strong>";
                    error_log("🗑️ Componente eliminado individual - ID: $id - Usuario: $usuario");
                } else {
                    $this->error = "❌ Error eliminando componente: " . $resultado['error'];
                    error_log("💥 Error eliminando componente individual: " . $resultado['error']);
                }
                break;

            case 'eliminar_version':
                $id_materia_prima_objetivo = intval($_POST['id_materia_prima_objetivo'] ?? 0);
                $version_receta = intval($_POST['version_receta'] ?? 0);
                $resultado = $this->recetasMateriaPrimaService->eliminarRecetaVersion($id_materia_prima_objetivo, $version_receta, $usuario);
                if ($resultado['success']) {
                    $this->mensaje = "✅ <strong>Versión $version_receta eliminada exitosamente!</strong><br>";
                    $this->mensaje .= "Registros afectados: {$resultado['registros_afectados']}";
                    error_log("🗑️ Versión eliminada agrupada - Objetivo: $id_materia_prima_objetivo - Versión: $version_receta - Usuario: $usuario");
                } else {
                    $this->error = "❌ Error eliminando versión: " . $resultado['error'];
                    error_log("💥 Error eliminando versión agrupada: " . $resultado['error']);
                }
                break;

            case 'eliminar_todas_recetas':
                $id_materia_prima_objetivo = intval($_POST['id_materia_prima_objetivo'] ?? 0);
                $resultado = $this->recetasMateriaPrimaService->eliminarTodasRecetasMateria($id_materia_prima_objetivo, $usuario);
                if ($resultado['success']) {
                    $this->mensaje = "✅ <strong>Todas las versiones de la materia prima eliminadas exitosamente!</strong><br>";
                    $this->mensaje .= "Registros afectados: {$resultado['registros_afectados']}";
                    error_log("🗑️ Todas las composiciones eliminadas agrupada - Objetivo: $id_materia_prima_objetivo - Usuario: $usuario");
                } else {
                    $this->error = "❌ Error eliminando todas las composiciones: " . $resultado['error'];
                    error_log("💥 Error eliminación completa agrupada: " . $resultado['error']);
                }
                break;

            default:
                $this->error = "❌ Acción no válida: $accion";
                error_log("⚠️ Acción agrupada no válida: $accion");
                break;
        }
    }

    /**
     * ✅ NUEVO: Redirect específico para petición agrupada
     */
    private function redirectDespuesDePostAgrupada()
    {
        // Guardar mensajes en la sesión
        if (!empty($this->mensaje)) {
            $_SESSION['mensaje_exito'] = $this->mensaje;
        }
        if (!empty($this->error)) {
            $_SESSION['mensaje_error'] = $this->error;
        }

        // Construir URL de redirect
        $url_redirect = $_SERVER['PHP_SELF'];
        $params = [];

        // Mantener filtros agrupados
        $filtros = $this->obtenerFiltrosAgrupados();
        foreach ($filtros as $key => $value) {
            if (!empty($value)) {
                $params[] = urlencode($key) . '=' . urlencode($value);
            }
        }

        // Mantener página actual
        $paginaActual = max(1, intval($_GET['pagina'] ?? 1));
        if ($paginaActual > 1) {
            $params[] = 'pagina=' . $paginaActual;
        }

        // Agregar acción realizada
        $accion = $_POST['accion'] ?? '';
        if ($accion) {
            $params[] = 'action=' . urlencode($accion);
        }

        // Construir URL final
        if (!empty($params)) {
            $url_redirect .= '?' . implode('&', $params);
        }

        // Timestamp anti-caché
        $separator = (strpos($url_redirect, '?') !== false) ? '&' : '?';
        $url_redirect .= $separator . '_t=' . time();

        header("Location: $url_redirect");
        exit();
    }

    /**
     * Procesar creación múltiple
     */
    private function procesarCreacionMultiple($usuario)
    {
        $id_materia_prima_objetivo = intval($_POST['id_materia_prima_objetivo'] ?? 0);
        $materias_primas_data = $_POST['materias_primas'] ?? [];
        $nombre_receta = trim($_POST['nombre_receta'] ?? '');

        if (empty($nombre_receta)) {
            $versiones = $this->recetasMateriaPrimaService->listarVersionesReceta($id_materia_prima_objetivo);
            $total = $versiones['total_versiones'] ?? 0;
            $nombre_receta = $total > 0 ? "Composición Versión " . ($total + 1) : "Composición Principal";
        }

        // Procesar materias primas
        $materias_procesadas = $this->procesarMateriasPrimas($materias_primas_data);

        return $this->recetasMateriaPrimaService->crearRecetasMultiplesConVersion($id_materia_prima_objetivo, $materias_procesadas, $nombre_receta, null, $usuario);
    }

    /**
     * Obtener filtros para vista agrupada
     */
    private function obtenerFiltrosAgrupados()
    {
        return [
            'id_materia_prima_objetivo' => $_GET['id_materia_prima_objetivo'] ?? '',
            'buscar_materia_objetivo' => trim($_GET['buscar_materia_objetivo'] ?? ''),
            'version_receta' => $_GET['version_receta'] ?? '',
        ];
    }

    /**
     * Construir URL de filtros
     */
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

    /**
     * Obtener URL base
     */
    private function obtenerUrlBase()
    {
        global $url_base;
        return $url_base ?? '/';
    }

    /**
     * Manejar AJAX agrupado
     */
    public function manejarAjaxAgrupado()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
            $respuesta = ['success' => false, 'error' => 'Acción no válida'];

            switch ($accion) {
                case 'obtener_detalle_receta':
                    $id_materia_prima_objetivo = intval($_GET['id_materia_prima_objetivo'] ?? $_POST['id_materia_prima_objetivo'] ?? 0);
                    $respuesta = $this->obtenerDetalleReceta($id_materia_prima_objetivo);
                    break;

                case 'obtener_versiones':
                    $id_materia_prima_objetivo = intval($_GET['id_materia_prima_objetivo'] ?? $_POST['id_materia_prima_objetivo'] ?? 0);
                    $respuesta = $this->recetasMateriaPrimaService->listarVersionesReceta($id_materia_prima_objetivo);
                    break;

                case 'obtener_detalle_version':
                    $id_materia_prima_objetivo = intval($_GET['id_materia_prima_objetivo'] ?? $_POST['id_materia_prima_objetivo'] ?? 0);
                    $version_receta = intval($_GET['version_receta'] ?? $_POST['version_receta'] ?? 0);
                    $respuesta = $this->recetasMateriaPrimaService->obtenerDetalleRecetaVersion($id_materia_prima_objetivo, $version_receta);
                    break;

                case 'validar_porcentaje_completo':
                    $id_materia_prima_objetivo = intval($_POST['id_materia_prima_objetivo'] ?? $_GET['id_materia_prima_objetivo'] ?? 0);
                    $version_receta = intval($_POST['version_receta'] ?? $_GET['version_receta'] ?? 0);
                    if ($id_materia_prima_objetivo > 0) {
                        if ($version_receta > 0) {
                            $validacion = $this->recetasMateriaPrimaService->validarPorcentajeCompleto($id_materia_prima_objetivo, $version_receta);
                        } else {
                            $validacion = $this->recetasMateriaPrimaService->validarPorcentajeCompleto($id_materia_prima_objetivo);
                        }
                        $respuesta = [
                            'success' => true,
                            'validacion' => $validacion
                        ];
                    } else {
                        $respuesta = [
                            'success' => false,
                            'error' => 'ID de materia prima objetivo inválido'
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

    /**
     * Obtener detalle de receta por materia prima objetivo
     */
    public function obtenerDetalleReceta($id_materia_prima_objetivo)
    {
        try {
            $resultado = $this->recetasMateriaPrimaService->obtenerDetalleRecetaMateria($id_materia_prima_objetivo);
            return $resultado;
        } catch (Exception $e) {
            error_log("💥 Error obteniendo detalle de composición: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error interno: ' . $e->getMessage()
            ];
        }
    }
}
