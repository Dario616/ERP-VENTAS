<?php

/**
 * ProductionController con Post-Redirect-Get implementado
 * Solo maneja HTTP requests/responses - La lógica de negocio está en ProductionService
 */
class ProductionController
{
    private $productionService;
    private $productionRepo;
    private $printService;

    // Variables de estado para la vista
    private $ordenEncontrada = null;
    private $productosOrden = [];
    private $mensaje = '';
    private $error = '';
    private $auto_print_url = null;

    // Variables de paginación
    private $pagina_actual = 1;
    private $items_por_pagina = 5;
    private $numeroOrdenActual = null;

    public function __construct($conexion)
    {
        // ✅ NUEVA ARQUITECTURA: Usar ProductionService
        $this->productionService = new ProductionService($conexion);
        $this->productionRepo = $this->productionService->getProductionRepo();
        $this->printService = $this->productionService->getPrintService();
    }

    /**
     * Método principal que maneja todas las peticiones HTTP
     */
    public function manejarPeticion()
    {
        // Manejar petición AJAX para peso promedio
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            if (isset($_POST['numero_orden']) && isset($_POST['bobinas_pacote'])) {
                // Si tiene 'obtener_peso_teorico', usar el nuevo método
                if (isset($_POST['obtener_peso_teorico'])) {
                    $this->obtenerPesoTeoricoAjax();
                    return;
                }
            }
        }

        // Inicializar variables de paginación
        $this->pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;

        // Detectar número de orden
        $this->detectarNumeroOrden();

        // Cargar orden si hay número disponible
        if ($this->numeroOrdenActual) {
            $this->cargarOrden();
        }

        // Procesar peticiones POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->procesarPeticionPOST();
            // ¡IMPORTANTE! Después de procesar POST, verificar si se debe hacer redirect
            if ($this->debeHacerRedirect()) {
                $this->redirectDespuesDePost();
                return null; // No continuar con el renderizado, se hizo redirect
            }
        }

        // Obtener mensajes de la sesión (si los hay)
        $this->obtenerMensajesDeSesion();

        return $this->obtenerDatosVista();
    }

    /**
     * ✅ NUEVO: Verificar si se debe hacer redirect después de POST
     */
    private function debeHacerRedirect()
    {
        // NO hacer redirect si hay auto_print_url (necesario para impresión automática)
        if (!empty($this->auto_print_url)) {
            return false;
        }

        // NO hacer redirect para peticiones AJAX
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            return false;
        }

        // NO hacer redirect para búsqueda de orden (queremos mantener el estado)
        if (isset($_POST['buscar_orden'])) {
            return false;
        }

        // SÍ hacer redirect para todas las demás operaciones POST
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

        // Mantener orden actual si existe
        if ($this->numeroOrdenActual) {
            $params[] = 'orden=' . $this->numeroOrdenActual;
        }

        // Mantener página actual si no es la primera
        if ($this->pagina_actual > 1) {
            $params[] = 'pagina=' . $this->pagina_actual;
        }

        // Mantener filtros existentes
        if (isset($_GET['filtro_id']) && !empty($_GET['filtro_id'])) {
            $params[] = 'filtro_id=' . urlencode($_GET['filtro_id']);
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
        if (isset($_POST['registrar_produccion'])) {
            return 'registrar';
        } elseif (isset($_POST['reimprimir_etiqueta_unificado'])) {
            return 'reimprimir';
        } elseif (isset($_POST['reimprimir_lote_etiquetas'])) {
            return 'reimprimir_lote';
        } elseif (isset($_POST['eliminar_registro'])) {
            return 'eliminar';
        } elseif (isset($_POST['finalizar_orden'])) {
            return 'finalizar';
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
     * Detectar número de orden desde GET o POST
     */
    private function detectarNumeroOrden()
    {
        if (isset($_GET['orden']) && !empty($_GET['orden'])) {
            $this->numeroOrdenActual = intval($_GET['orden']);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar_orden'])) {
            $this->numeroOrdenActual = intval(trim($_POST['numero_orden']));
        }
    }

    /**
     * Cargar orden completa
     */
    private function cargarOrden()
    {
        $resultado = $this->productionRepo->buscarOrdenCompleta($this->numeroOrdenActual);
        $this->error = $resultado['error'];
        $this->ordenEncontrada = $resultado['orden'];
        $this->productosOrden = $resultado['productos'];
    }

    /**
     * Procesar peticiones POST - MEJORADO con PRG
     */
    private function procesarPeticionPOST()
    {
        try {
            if (isset($_POST['buscar_stock_id'])) {
                $this->buscarStockPorId();
                return;
            }

            if (isset($_POST['reimprimir_etiqueta_unificado'])) {
                $this->manejarReimpresion();
            } elseif (isset($_POST['reimprimir_lote_etiquetas'])) {
                $this->manejarReimpresionLote();
            } elseif (isset($_POST['registrar_produccion'])) {
                $this->manejarRegistroProduccion();
            } elseif (isset($_POST['eliminar_registro'])) {
                $this->manejarEliminacionRegistro();
            } elseif (isset($_POST['finalizar_orden'])) {
                $this->manejarFinalizacionOrden();
            } elseif (isset($_POST['buscar_orden'])) {
                // Búsqueda de orden - no requiere redirect
                return;
            }
        } catch (Exception $e) {
            $this->error = "❌ Error inesperado: " . $e->getMessage();
            error_log("💥 Error en ProductionController: " . $e->getMessage());
        }
    }

    /**
     * ✅ MEJORADO: Manejar registro de producción
     */
    private function manejarRegistroProduccion()
    {
        $resultadoRegistro = $this->productionService->procesarRegistroProduccion($_POST);

        if ($resultadoRegistro['success']) {
            // ✅ REGISTRO EXITOSO
            $this->auto_print_url = $resultadoRegistro['auto_print_url'];
            $this->mensaje = "✅ <strong>Registro exitoso!</strong><br>";
            $this->mensaje .= $resultadoRegistro['mensaje'];
            $this->recargarOrden($_POST['numero_orden']);

            error_log("✅ Producción registrada exitosamente - Orden: {$_POST['numero_orden']} - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
        } else {
            // ❌ ERROR DE VALIDACIÓN: NO hacer redirect, mantener el error para mostrarlo
            $this->error = "❌ Error en el registro:<br>" . $resultadoRegistro['error'];
            error_log("❌ Error registrando producción - Orden: {$_POST['numero_orden']} - Error: " . $resultadoRegistro['error']);

            // Recargar orden para mantener datos actualizados
            if (isset($_POST['numero_orden'])) {
                $this->recargarOrden($_POST['numero_orden']);
            }
        }
    }

    /**
     * ✅ MEJORADO: Manejar eliminación de registros
     */
    private function manejarEliminacionRegistro()
    {
        $resultadoEliminacion = $this->productionService->procesarEliminacionRegistro($_POST);

        if ($resultadoEliminacion['success']) {
            $this->mensaje = "✅ <strong>Registro eliminado exitosamente!</strong><br>";
            $this->mensaje .= $resultadoEliminacion['mensaje'];
            $this->recargarOrden($_POST['numero_orden_eliminar']);
            $this->pagina_actual = 1; // Resetear paginación

            error_log("🗑️ Registro eliminado - Orden: {$_POST['numero_orden_eliminar']} - ID: {$_POST['id_registro_eliminar']} - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
        } else {
            $this->error = "❌ Error al eliminar registro:<br>" . $resultadoEliminacion['error'];
            error_log("❌ Error eliminando registro - Error: " . $resultadoEliminacion['error']);
        }
    }

    /**
     * ✅ MEJORADO: Manejar finalización de orden
     */
    private function manejarFinalizacionOrden()
    {
        $resultadoFinalizacion = $this->productionService->procesarFinalizacionOrden($_POST);

        if ($resultadoFinalizacion['success']) {
            $this->mensaje = "✅ <strong>Orden finalizada exitosamente!</strong><br>";
            $this->mensaje .= $resultadoFinalizacion['mensaje'];
            $this->recargarOrden($_POST['numero_orden_finalizar']);

            error_log("🏁 Orden finalizada - Orden: {$_POST['numero_orden_finalizar']} - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
        } else {
            $this->error = "❌ Error al finalizar orden:<br>" . $resultadoFinalizacion['error'];
            error_log("❌ Error finalizando orden - Error: " . $resultadoFinalizacion['error']);
        }
    }

    /**
     * ✅ MEJORADO: Manejar reimpresión de etiquetas
     */
    private function manejarReimpresion()
    {
        $numeroOrden = intval($_POST['numero_orden_reimprimir']);
        $tipoProducto = $_POST['tipo_producto_reimprimir'];
        $idStock = isset($_POST['id_stock_reimprimir']) ? intval($_POST['id_stock_reimprimir']) : null;

        $resultadoReimpresion = $this->printService->procesarReimpresion($numeroOrden, $tipoProducto, $idStock);

        if ($resultadoReimpresion['success']) {
            $this->auto_print_url = $resultadoReimpresion['pdf_url'];
            $this->mensaje = "✅ <strong>Reimpresión iniciada!</strong><br>";
            $this->mensaje .= $resultadoReimpresion['mensaje'];
            $this->recargarOrden($numeroOrden);

            error_log("🖨️ Reimpresión exitosa - Orden: $numeroOrden - ID Stock: " . ($idStock ?? 'N/A') . " - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
        } else {
            $this->error = "❌ Error en la reimpresión:<br>" . $resultadoReimpresion['error'];
            error_log("❌ Error en reimpresión - Error: " . $resultadoReimpresion['error']);
        }
    }

    /**
     * ✅ MEJORADO: Manejar reimpresión en lote de etiquetas
     */
    private function manejarReimpresionLote()
    {
        $numeroOrden = intval($_POST['numero_orden_lote']);
        $tipoProducto = $_POST['tipo_producto_lote'];
        $itemDesde = intval($_POST['item_desde']);
        $itemHasta = intval($_POST['item_hasta']);

        $resultadoLote = $this->printService->procesarReimpresionLote($numeroOrden, $tipoProducto, $itemDesde, $itemHasta);

        if ($resultadoLote['success']) {
            $this->auto_print_url = $resultadoLote['pdf_url'];
            $this->mensaje = "✅ <strong>Reimpresión en lote iniciada!</strong><br>";
            $this->mensaje .= $resultadoLote['mensaje'];
            $this->recargarOrden($numeroOrden);

            error_log("🖨️📦 Reimpresión en lote exitosa - Orden: $numeroOrden - Items: $itemDesde-$itemHasta - Usuario: " . ($_SESSION['nombre'] ?? 'SISTEMA'));
        } else {
            $this->error = "❌ Error en la reimpresión en lote:<br>" . $resultadoLote['error'];
            error_log("❌ Error en reimpresión en lote - Error: " . $resultadoLote['error']);
        }
    }

    public function obtenerDatosPesoTeorico($bobinasPacote = null, $metragem = null)
    {
        return $this->productionService->obtenerDatosPesoTeorico($this->ordenEncontrada, $bobinasPacote, $metragem);
    }

    /**
     * 🆕 NUEVO: Obtener peso teórico AJAX - para recálculo dinámico
     */
    public function obtenerPesoTeoricoAjax()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            return;
        }

        $numeroOrden = isset($_POST['numero_orden']) ? intval($_POST['numero_orden']) : 0;
        $bobinasPacote = isset($_POST['bobinas_pacote']) ? intval($_POST['bobinas_pacote']) : 1;
        $metragem = isset($_POST['metragem']) ? intval($_POST['metragem']) : null;

        if ($numeroOrden <= 0) {
            echo json_encode(['success' => false, 'error' => 'Número de orden inválido']);
            return;
        }

        // ✅ USAR PESO TEÓRICO en lugar de promedio
        $datosPeso = $this->productionRepo->obtenerPesoTeoricoOrden($numeroOrden, $bobinasPacote, $metragem);

        echo json_encode($datosPeso);
        exit();
    }


    /**
     * ✅ SIMPLIFICADO: Validar rango de items - DELEGADO AL SERVICE
     */
    public function validarRangoItems($numeroOrden, $itemDesde, $itemHasta)
    {
        return $this->productionService->validarRangoItems($numeroOrden, $itemDesde, $itemHasta);
    }

    /**
     * Recargar orden después de operaciones
     */
    private function recargarOrden($numeroOrden)
    {
        $resultado = $this->productionRepo->buscarOrdenCompleta($numeroOrden);
        if (!$resultado['error']) {
            $this->ordenEncontrada = $resultado['orden'];
            $this->productosOrden = $resultado['productos'];
            $this->numeroOrdenActual = $numeroOrden;
        }
    }

    /**
     * ✅ SIMPLIFICADO: Obtener estadísticas - DELEGADO AL SERVICE
     */
    public function obtenerEstadisticasProduccion()
    {
        return $this->productionService->obtenerEstadisticasProduccion($this->ordenEncontrada, $this->productosOrden);
    }

    /**
     * ✅ SIMPLIFICADO: Obtener diferencia de peso - DELEGADO AL SERVICE
     */
    public function obtenerDiferenciaPeso()
    {
        return $this->productionService->obtenerDiferenciaPeso($this->ordenEncontrada, $this->productosOrden);
    }

  

    /**
     * Obtener datos de paginación
     */
    public function obtenerDatosPaginacion()
    {
        // Verificar filtro por ID primero
        if (isset($_GET['filtro_id']) && !empty($_GET['filtro_id'])) {
            return $this->productionRepo->obtenerRegistroFiltrado($_GET['filtro_id']);
        }

        // Si no hay orden, devolver vacío
        if (!$this->ordenEncontrada) {
            return ['total_registros' => 0, 'total_paginas' => 0, 'registros' => []];
        }

        // Paginación normal por orden
        return $this->productionRepo->obtenerDatosPaginacion(
            $this->ordenEncontrada['id'],
            $this->items_por_pagina,
            $this->pagina_actual
        );
    }

    /**
     * Obtener datos para la vista - MEJORADO
     */
    public function obtenerDatosVista()
    {
        // Obtener bobinas_pacote inicial basado en el tipo de producto
        $bobinasPacoteInicial = 1;
        if ($this->ordenEncontrada && !empty($this->productosOrden)) {
            $tipoProducto = $this->productosOrden[0]['tipo'];

            if ($tipoProducto !== 'TOALLITAS' && $tipoProducto !== 'PAÑOS') {
                $largura = floatval($this->productosOrden[0]['largura_metros'] ?? 0);
                if ($largura > 0 && $largura < 1.0) {
                    $bobinasPacoteInicial = 1;
                }
            }
        }



        return [
            'ordenEncontrada' => $this->ordenEncontrada,
            'productosOrden' => $this->productosOrden,
            'mensaje' => $this->mensaje,
            'error' => $this->error,
            'auto_print_url' => $this->auto_print_url,
            'pagina_actual' => $this->pagina_actual,
            'items_por_pagina' => $this->items_por_pagina,
            'numeroOrdenActual' => $this->numeroOrdenActual,
            'estadisticasProduccion' => $this->obtenerEstadisticasProduccion(),
            'diferenciaPeso' => $this->obtenerDiferenciaPeso(),
            'datosPaginacion' => $this->obtenerDatosPaginacion(),
            'datosPesoPromedio' => $this->obtenerDatosPesoTeorico($bobinasPacoteInicial), // CAMBIO AQUÍ
            'recetaActiva' => $this->productionService->verificarRecetaActiva($this->numeroOrdenActual ?? 0),
            'bobinasPacoteInicial' => $bobinasPacoteInicial
        ];
    }

    /**
     * Buscar stock por ID - AJAX
     */
    private function buscarStockPorId()
    {
        $id = intval($_POST['stock_id']);

        $sql = "SELECT id, numero_item, tipo_producto, peso_bruto, peso_liquido, tara 
            FROM sist_prod_stock WHERE id = ?";
        $stmt = $this->productionRepo->getConexion()->prepare($sql);
        $stmt->execute([$id]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        if ($registro) {
            echo json_encode([
                'success' => true,
                'id' => $registro['id'],
                'numero' => $registro['numero_item'],
                'tipo' => $registro['tipo_producto'],
                'peso_bruto' => $registro['peso_bruto'],
                'peso_liquido' => $registro['peso_liquido'],
                'tara' => $registro['tara']
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'ID no encontrado']);
        }
        exit();
    }


    /**
     * Obtener valor del campo de búsqueda
     */
    public function obtenerValorBusqueda()
    {
        if (isset($_POST['numero_orden'])) {
            return htmlspecialchars($_POST['numero_orden']);
        } elseif (isset($_GET['orden'])) {
            return htmlspecialchars($_GET['orden']);
        } elseif ($this->ordenEncontrada) {
            return $this->ordenEncontrada['id'];
        }
        return '';
    }

    // ✅ GETTERS SIMPLIFICADOS - Solo para la vista
    public function getOrdenEncontrada()
    {
        return $this->ordenEncontrada;
    }
    public function getProductosOrden()
    {
        return $this->productosOrden;
    }
    public function getMensaje()
    {
        return $this->mensaje;
    }
    public function getError()
    {
        return $this->error;
    }
    public function getAutoPrintUrl()
    {
        return $this->auto_print_url;
    }
    public function getPaginaActual()
    {
        return $this->pagina_actual;
    }
    public function getItemsPorPagina()
    {
        return $this->items_por_pagina;
    }
    public function getNumeroOrdenActual()
    {
        return $this->numeroOrdenActual;
    }
}
