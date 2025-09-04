<?php
require_once 'repository/produccionRepository.php';
require_once 'services/produccionService.php';

// Configurar zona horaria para Paraguay
date_default_timezone_set('America/Asuncion');

/**
 * Controller para manejo de producción - CORREGIDO SIN cantidad_completada
 */
class ProduccionController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new ProduccionRepository($conexion);
        $this->service = new ProduccionService($this->repository);
        $this->urlBase = $urlBase;
    }

    /**
     * Obtener venta completa para procesamiento
     */
    public function obtenerVentaParaProcesamiento($idVenta)
    {
        try {
            return $this->service->obtenerVentaCompleta($idVenta);
        } catch (Exception $e) {
            throw new Exception('Venta no encontrada: ' . $e->getMessage());
        }
    }

    /**
     * Procesar formulario de producción - CORREGIDO
     */
    public function procesarFormularioProduccion($idVenta, $datos)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['error' => 'Método no permitido'];
        }

        $accion = $datos['accion'] ?? '';

        try {
            switch ($accion) {
                case 'confirmar':
                    return $this->procesarEmisionOrdenesCorregido($idVenta, $datos);

                case 'devolver':
                    return $this->procesarDevolucionPCPCorregido($idVenta, $datos);

                default:
                    return ['error' => 'Acción no válida'];
            }
        } catch (Exception $e) {
            error_log("Error procesando formulario producción: " . $e->getMessage());
            return ['error' => 'Error interno del servidor'];
        }
    }

    /**
     * ✅ CORREGIDO: Procesar emisión de órdenes - ahora actualiza campo cantidad
     */
    private function procesarEmisionOrdenesCorregido($idVenta, $datos)
    {
        try {
            $observaciones = $datos['observaciones_produccion'] ?? '';
            $productosCompletados = $datos['productos_completados'] ?? [];

            // Validar datos
            $errores = $this->service->validarDatos(['productos_completados' => $productosCompletados], 'emision');
            if (!empty($errores)) {
                return ['error' => implode(', ', $errores)];
            }

            // LOG PARA DEBUGGING
            error_log("CONTROLLER - Procesando emisión para venta: {$idVenta}");
            error_log("CONTROLLER - Productos completados: " . json_encode($productosCompletados));

            $resultado = $this->service->emitirOrdenesProduccion($idVenta, $productosCompletados, $observaciones, $_SESSION['id']);

            if ($resultado['success']) {
                // ✅ CORRECCIÓN: Formatear productos emitidos con unidades correctas
                $productosFormateados = [];
                foreach ($resultado['productos_emitidos'] as $producto) {
                    $unidadReal = $producto['unidad'] ?? 'unidades';

                    $productosFormateados[] = [
                        'id_orden' => $producto['id_orden'],
                        'descripcion' => $producto['descripcion'],
                        'tipo' => $producto['tipo'],
                        'cantidad' => $producto['cantidad'],
                        'unidad' => $unidadReal, // ✅ UNIDAD CORRECTA DE LA BD
                        'cantidad_formateada' => $this->formatearCantidadConUnidadCorrecta($producto['cantidad'], $unidadReal),
                        'cantidad_inventario' => $producto['cantidad_inventario'] ?? 0,
                        'gramatura' => $producto['gramatura'] ?? 0,
                        'cantidad_actualizada' => $producto['cantidad_actualizada'] ?? false // ✅ FLAG para indicar actualización
                    ];
                }

                // Retornar datos para el modal en la vista
                return [
                    'success' => true,
                    'tipo' => 'emision',
                    'productos_emitidos' => $productosFormateados,
                    'id_venta' => $idVenta,
                    'usuario' => $_SESSION['nombre'] ?? 'Usuario',
                    'fecha' => date('d/m/Y H:i'),
                    'mensaje_adicional' => 'Campo cantidad actualizado con valores reales de orden de producción'
                ];
            } else {
                return ['error' => $resultado['error']];
            }
        } catch (Exception $e) {
            error_log("Error procesando emisión: " . $e->getMessage());
            return ['error' => 'Error al emitir órdenes de producción'];
        }
    }

    /**
     * ✅ CORREGIDO: Procesar devolución a PCP con unidades correctas
     */
    private function procesarDevolucionPCPCorregido($idVenta, $datos)
    {
        try {
            $motivoDevolucion = $datos['motivo_devolucion'] ?? '';
            $productosDevueltos = $datos['productos_devueltos'] ?? [];
            $cantidadesDevueltas = $datos['cantidad_devuelta'] ?? [];

            // Validar datos
            $errores = $this->service->validarDatos([
                'productos_devueltos' => $productosDevueltos,
                'motivo_devolucion' => $motivoDevolucion
            ], 'devolucion');

            if (!empty($errores)) {
                return ['error' => implode(', ', $errores)];
            }

            // LOG PARA DEBUGGING
            error_log("CONTROLLER - Procesando devolución para venta: {$idVenta}");
            error_log("CONTROLLER - Productos devueltos: " . json_encode($productosDevueltos));
            error_log("CONTROLLER - Cantidades devueltas: " . json_encode($cantidadesDevueltas));

            $resultado = $this->service->devolverProductosPCP($idVenta, $productosDevueltos, $cantidadesDevueltas, $motivoDevolucion, $_SESSION['id']);

            if ($resultado['success']) {
                // ✅ CORRECCIÓN: Formatear productos devueltos con unidades correctas
                $productosFormateados = [];
                foreach ($resultado['productos_devueltos'] as $producto) {
                    $unidadReal = $producto['unidad'] ?? 'unidades';

                    $productosFormateados[] = [
                        'descripcion' => $producto['descripcion'],
                        'tipo' => $producto['tipo'],
                        'cantidad_devuelta' => $producto['cantidad_devuelta'],
                        'cantidad_original' => $producto['cantidad_original'],
                        'unidad' => $unidadReal, // ✅ UNIDAD CORRECTA DE LA BD
                        'cantidad_devuelta_formateada' => $this->formatearCantidadConUnidadCorrecta($producto['cantidad_devuelta'], $unidadReal),
                        'cantidad_original_formateada' => $this->formatearCantidadConUnidadCorrecta($producto['cantidad_original'], $unidadReal)
                    ];
                }

                // Retornar datos para el modal en la vista
                return [
                    'success' => true,
                    'tipo' => 'devolucion',
                    'productos_devueltos' => $productosFormateados,
                    'id_venta' => $idVenta,
                    'usuario' => $_SESSION['nombre'] ?? 'Usuario',
                    'fecha' => date('d/m/Y H:i'),
                    'motivo' => $motivoDevolucion
                ];
            } else {
                return ['error' => $resultado['error']];
            }
        } catch (Exception $e) {
            error_log("Error procesando devolución: " . $e->getMessage());
            return ['error' => 'Error al devolver productos a PCP'];
        }
    }

    /**
     * Obtener ventas en producción con filtros
     */
    public function obtenerVentasProduccion($filtros = [], $pagina = 1, $registrosPorPagina = 10)
    {
        try {
            return $this->service->obtenerVentasProduccion($filtros, $pagina, $registrosPorPagina);
        } catch (Exception $e) {
            error_log("Error obteniendo ventas producción: " . $e->getMessage());
            return [
                'ventas' => [],
                'total_registros' => 0,
                'total_paginas' => 0,
                'pagina_actual' => $pagina
            ];
        }
    }

    /**
     * Procesar filtros de búsqueda
     */
    public function procesarFiltros()
    {
        return [
            'cliente' => trim($_GET['cliente'] ?? ''),
            'vendedor' => trim($_GET['vendedor'] ?? ''),
            'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
            'pagina' => max(1, (int)($_GET['pagina'] ?? 1))
        ];
    }

    /**
     * Verificar permisos
     */
    public function verificarPermisos($accion, $idVenta = null)
    {
        $esAdmin = $this->esAdministrador();
        $esProduccion = $this->esProduccion();

        switch ($accion) {
            case 'ver':
            case 'listar':
            case 'procesar':
            case 'emitir':
            case 'devolver':
                return $esAdmin || $esProduccion;

            default:
                return false;
        }
    }

    /**
     * Verificar si el usuario es administrador
     */
    private function esAdministrador()
    {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === '1';
    }

    /**
     * Verificar si el usuario es de producción
     */
    private function esProduccion()
    {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === '4';
    }

    /**
     * Obtener datos para la vista
     */
    public function obtenerDatosVista($pagina = 'dashboard')
    {
        try {
            $datos = [
                'titulo' => 'Gestión de Producción',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => $this->esAdministrador(),
                'es_produccion' => $this->esProduccion(),
                'pagina' => $pagina
            ];

            switch ($pagina) {
                case 'procesar_venta':
                    $datos['titulo'] = 'Procesar Venta - Producción';
                    break;

                case 'ventas_produccion':
                    $datos['titulo'] = 'Ventas en Producción';
                    break;
            }

            return $datos;
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => 'Gestión de Producción',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => false,
                'es_produccion' => false,
                'pagina' => $pagina
            ];
        }
    }

    /**
     * Obtener configuración para JavaScript
     */
    public function obtenerConfiguracionJS()
    {
        return [
            'urlBase' => $this->urlBase,
            'usuario' => $_SESSION['nombre'] ?? 'Usuario',
            'esAdmin' => $this->esAdministrador(),
            'esProduccion' => $this->esProduccion(),
            'tiposProductos' => $this->service->getTiposProductosSoportados(),
            'debug' => isset($_GET['debug']),
            'rol' => $_SESSION['rol'] ?? '0'
        ];
    }

    /**
     * Formatear moneda para vista
     */
    public function formatearMoneda($monto, $moneda)
    {
        return $this->service->formatearMoneda($monto, $moneda);
    }

    /**
     * NUEVO: Formatear cantidad con unidad correcta
     */
    public function formatearCantidadConUnidadCorrecta($cantidad, $unidad)
    {
        // Para unidades como "unidades" no usar decimales
        if (in_array(strtolower($unidad), ['unidades', 'cajas', 'piezas'])) {
            return number_format((float)$cantidad, 0, ',', '.') . ' ' . $unidad;
        } else {
            // Para kg y otros, usar 2 decimales
            return number_format((float)$cantidad, 2, ',', '.') . ' ' . $unidad;
        }
    }

    /**
     * Log de actividad
     */
    public function logActividad($accion, $detalles = null)
    {
        $usuario = $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "PRODUCCIÓN - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);
    }

    /**
     * Validar venta para procesamiento en producción
     */
    public function validarVentaParaProcesamiento($idVenta)
    {
        try {
            $venta = $this->repository->obtenerVentaProduccion($idVenta);

            if (!$venta) {
                throw new Exception('Venta no encontrada');
            }

            $estadosValidos = ['En Producción/Expedición', 'En Producción'];
            if (!in_array($venta['estado'], $estadosValidos)) {
                throw new Exception('La venta no está en estado válido para procesamiento de producción');
            }

            return true;
        } catch (Exception $e) {
            throw new Exception('Error validando venta: ' . $e->getMessage());
        }
    }

    /**
     * Obtener color para tipo de producto (PÚBLICO para usar en la vista)
     */
    public function obtenerColorTipoProducto($tipo)
    {
        switch (strtoupper($tipo)) {
            case 'TNT':
            case 'LAMINADORA':
                return '#0d6efd';
            case 'SPUNLACE':
                return '#6f42c1';
            case 'TOALLITAS':
                return '#198754';
            case 'PAÑOS':
                return '#fd7e14';
            default:
                return '#6c757d';
        }
    }

    /**
     * Obtener icono para tipo de producto (PÚBLICO para usar en la vista)
     */
    public function obtenerIconoTipoProducto($tipo)
    {
        switch (strtoupper($tipo)) {
            case 'TNT':
            case 'LAMINADORA':
                return 'fa-industry';
            case 'SPUNLACE':
                return 'fa-fabric';
            case 'TOALLITAS':
                return 'fa-soap';
            case 'PAÑOS':
                return 'fa-handshirt';
            default:
                return 'fa-box';
        }
    }

    /**
     * Manejar mensajes flash
     */
    public function manejarMensajes()
    {
        $mensaje = '';
        $error = '';

        if (isset($_GET['mensaje'])) {
            $mensaje = htmlspecialchars($_GET['mensaje']);
        }

        if (isset($_GET['error'])) {
            $error = htmlspecialchars($_GET['error']);
        }

        return [
            'mensaje' => $mensaje,
            'error' => $error
        ];
    }

    /**
     * Generar URL con parámetros
     */
    public function generarUrlConParametros($pagina, $parametros = [])
    {
        $url = $this->urlBase . $pagina;

        if (!empty($parametros)) {
            $url .= '?' . http_build_query($parametros);
        }

        return $url;
    }

    /**
     * CORREGIDO: Determinar si un producto es de tipo unidades usando la BD
     */
    public function esProductoEnUnidades($tipoProducto, $unidadMedidaBD = null)
    {
        // ✅ USAR LA UNIDAD DE LA BASE DE DATOS SI ESTÁ DISPONIBLE
        if ($unidadMedidaBD) {
            $unidadLower = strtolower($unidadMedidaBD);
            return in_array($unidadLower, ['unidades', 'cajas', 'piezas']);
        }

        // Fallback al tipo de producto si no hay unidad de BD
        $tipoLower = strtolower($tipoProducto);
        return in_array($tipoLower, ['toallitas', 'paños']);
    }

    /**
     * CORREGIDO: Obtener unidad de medida usando la BD como fuente principal
     */
    public function obtenerUnidadMedidaProducto($tipoProducto, $unidadMedidaBD = null)
    {
        // ✅ PRIORIZAR LA UNIDAD DE LA BASE DE DATOS
        if (!empty($unidadMedidaBD)) {
            return $unidadMedidaBD;
        }

        // Fallback basado en tipo de producto (solo si no hay unidad en BD)
        $tipoLower = strtolower($tipoProducto);

        if ($tipoLower === 'toallitas') {
            return 'cajas';
        } elseif ($tipoLower === 'paños') {
            return 'unidades';
        } else {
            return 'kg'; // TNT, Spunlace, Laminadora
        }
    }

    /**
     * CORREGIDO: Formatear cantidad según unidad real de la BD
     */
    public function formatearCantidadProducto($cantidad, $tipoProducto, $unidadMedidaBD = null)
    {
        // ✅ USAR LA UNIDAD REAL DE LA BASE DE DATOS
        $unidad = $this->obtenerUnidadMedidaProducto($tipoProducto, $unidadMedidaBD);

        return $this->formatearCantidadConUnidadCorrecta($cantidad, $unidad);
    }

    /**
     * CORREGIDO: Extraer información específica de paños según especificaciones del usuario
     */
    public function extraerInfoPanosParaVista($descripcion)
    {
        $info = [
            'largura' => null,
            'picotado' => null,
            'cant_panos' => null,
            'gramatura' => null,
            'color' => 'Blanco'
        ];

        // Extraer dimensiones (formato NxN como 28x50, 45x80)
        if (preg_match('/(\d+)\s*x\s*(\d+)/i', $descripcion, $matches)) {
            $info['largura'] = (int)$matches[1]; // 28, 45 en los ejemplos
            $info['picotado'] = (int)$matches[2]; // 50, 80 en los ejemplos
        }

        // ✅ CORRECCIÓN ROBUSTA: Extraer cantidad de paños - REGEX SIMPLIFICADA
        // Primero intentar con "panos"
        if (preg_match('/(\d+)\s*panos/i', $descripcion, $matches)) {
            $info['cant_panos'] = (int)$matches[1];
            error_log("CONTROLLER - CANT_PANOS EXTRAÍDO CON 'panos': " . $info['cant_panos']);
        }
        // Si falla, intentar con "paños" (con ñ)
        elseif (preg_match('/(\d+)\s*paños/i', $descripcion, $matches)) {
            $info['cant_panos'] = (int)$matches[1];
            error_log("CONTROLLER - CANT_PANOS EXTRAÍDO CON 'paños': " . $info['cant_panos']);
        }
        // Regex más amplia para cualquier variación
        elseif (preg_match('/(\d+)\s*pa[ñn]os/i', $descripcion, $matches)) {
            $info['cant_panos'] = (int)$matches[1];
            error_log("CONTROLLER - CANT_PANOS EXTRAÍDO CON REGEX AMPLIA: " . $info['cant_panos']);
        } else {
            error_log("CONTROLLER - FALLO EXTRAYENDO CANT_PANOS de: '" . $descripcion . "'");
            error_log("CONTROLLER - Longitud descripción: " . strlen($descripcion));
            // Buscar todos los números en la descripción para debug
            if (preg_match_all('/\d+/', $descripcion, $allNumbers)) {
                error_log("CONTROLLER - Números encontrados: " . json_encode($allNumbers[0]));
            }
        }

        // ✅ CORRECCIÓN: Extraer gramatura específicamente del formato XXg/m2
        if (preg_match('/(\d+(?:[.,]?\d+)?)\s*g\/m[²2]/i', $descripcion, $matches)) {
            $info['gramatura'] = (float)str_replace(',', '.', $matches[1]); // 35, 70 en los ejemplos
        }

        // Extraer color después de la gramatura (35g/m2 Blanco, 70g/m2 Branco)
        if (preg_match('/g\/m[²2]\s+(Blanco|Azul|Verde|Amarillo|Rojo|Rosa|Negro|Gris|Branco)/i', $descripcion, $matches)) {
            $info['color'] = trim($matches[1]);
        } elseif (preg_match('/\s(Blanco|Azul|Verde|Amarillo|Rojo|Rosa|Negro|Gris|Branco)(?:\s|$)/i', $descripcion, $matches)) {
            $info['color'] = trim($matches[1]);
        }

        return $info;
    }

    /**
     * Obtener detalles completos de una orden de paños
     */
    public function obtenerDetallesOrdenPanos($idOrdenProduccion)
    {
        try {
            return $this->repository->obtenerDetallesOrdenPanos($idOrdenProduccion);
        } catch (Exception $e) {
            error_log("Error obteniendo detalles orden paños: " . $e->getMessage());
            return false;
        }
    }

    /**
     * CORREGIDO: Validar datos específicos de paños según nuevas especificaciones
     */
    public function validarDatosPanos($descripcion)
    {
        $errores = [];
        $info = $this->extraerInfoPanosParaVista($descripcion);

        // Validar dimensiones (formato NxN)
        if (!$info['largura'] || !$info['picotado']) {
            $errores[] = "No se pudieron extraer las dimensiones del paño (formato esperado: NxN como 28x50, 45x80)";
        }

        // ✅ CORRECCIÓN: Validar cantidad de paños (XX panos)
        if (!$info['cant_panos']) {
            $errores[] = "No se pudo extraer la cantidad de paños (formato esperado: 'XX panos' como '50 panos', '100 panos')";
        }

        // ✅ CORRECCIÓN: Validar gramatura del formato XXg/m2
        if (!$info['gramatura']) {
            $errores[] = "No se pudo extraer la gramatura (formato esperado: 'XXg/m2' como '35g/m2', '70g/m2')";
        }

        // Log para debugging
        if (!empty($errores)) {
            error_log("ERRORES VALIDACIÓN PAÑOS: " . implode(', ', $errores));
            error_log("DESCRIPCIÓN ANALIZADA: {$descripcion}");
            error_log("DATOS EXTRAÍDOS: " . json_encode($info));
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores,
            'datos_extraidos' => $info
        ];
    }

    /**
     * ✅ NUEVO: Obtener información completa de producto con cálculos correctos
     */
    public function obtenerInfoCompletaProducto($idProductoProduccion)
    {
        try {
            // Obtener información básica del producto
            $producto = $this->repository->obtenerInfoProductoProduccion($idProductoProduccion);
            if (!$producto) {
                return false;
            }

            // Obtener cantidad del inventario (para campo peso)
            $cantidadInventario = $this->repository->obtenerCantidadInventario($idProductoProduccion);

            // Obtener gramatura calculada (el peso que se calculaba antes)
            $gramaturaCalculada = $this->repository->obtenerGramaturaCalculada($idProductoProduccion);

            // Combinar toda la información
            $producto['cantidad_inventario'] = $cantidadInventario;
            $producto['gramatura_calculada'] = $gramaturaCalculada;
            $producto['unidad_real'] = $producto['unidadmedida'] ?? 'unidades';

            return $producto;
        } catch (Exception $e) {
            error_log("Error obteniendo info completa producto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Procesar filtros para historial de producción
     */
    public function procesarFiltrosHistorial()
    {
        return [
            'id_venta' => trim($_GET['id_venta'] ?? ''),
            'cliente_historial' => trim($_GET['cliente_historial'] ?? ''),
            'id_usuario' => trim($_GET['id_usuario'] ?? ''),
            'accion' => trim($_GET['accion'] ?? ''),
            'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => trim($_GET['fecha_hasta'] ?? '')
        ];
    }

    /**
     * Obtener historial de acciones de producción con paginación
     */
    public function obtenerHistorialProduccion($filtros, $paginaActual)
    {
        try {
            $registrosPorPagina = 10;

            $resultado = $this->service->obtenerHistorialProduccionPaginado(
                $filtros,
                $registrosPorPagina,
                $paginaActual
            );

            // Formatear datos para la vista
            foreach ($resultado['acciones'] as &$accion) {
                // Formatear fecha
                $accion['fecha_accion_formateada'] = date('d/m/Y H:i', strtotime($accion['fecha_accion']));

                // Badge para acción
                $accion['accion_badge'] = $this->obtenerBadgeAccionProduccion($accion['accion']);

                // Badge para estado
                $accion['estado_badge'] = $this->obtenerBadgeEstadoProduccion($accion['estado_resultante']);
            }

            return [
                'historial' => $resultado['acciones'],
                'total' => $resultado['totalRegistros'],
                'total_paginas' => $resultado['totalPaginas']
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo historial producción: " . $e->getMessage());
            return [
                'historial' => [],
                'total' => 0,
                'total_paginas' => 0
            ];
        }
    }

    /**
     * Obtener badge para acciones de producción
     */
    private function obtenerBadgeAccionProduccion($accion)
    {
        switch ($accion) {
            case 'Emitir Orden de Producción':
                return ['class' => 'bg-primary', 'icon' => 'fa-industry'];
            case 'Devolver a PCP':
                return ['class' => 'bg-warning text-dark', 'icon' => 'fa-undo'];
            case 'Procesar Orden':
                return ['class' => 'bg-info', 'icon' => 'fa-cogs'];
            case 'Completar Producción':
                return ['class' => 'bg-success', 'icon' => 'fa-check-circle'];
            case 'Cancelar Orden':
                return ['class' => 'bg-danger', 'icon' => 'fa-times-circle'];
            default:
                return ['class' => 'bg-secondary', 'icon' => 'fa-cog'];
        }
    }

    /**
     * Obtener badge para estados de producción
     */
    private function obtenerBadgeEstadoProduccion($estado)
    {
        switch ($estado) {
            case 'En Producción':
            case 'En Producción/Expedición':
                return ['class' => 'bg-primary', 'icon' => 'fa-industry'];
            case 'Orden Emitida':
                return ['class' => 'bg-info', 'icon' => 'fa-paper-plane'];
            case 'Devuelto a PCP':
                return ['class' => 'bg-warning text-dark', 'icon' => 'fa-undo'];
            case 'Completado':
                return ['class' => 'bg-success', 'icon' => 'fa-check'];
            case 'Cancelado':
                return ['class' => 'bg-danger', 'icon' => 'fa-times'];
            default:
                return ['class' => 'bg-secondary', 'icon' => 'fa-question'];
        }
    }

    /**
     * Obtener usuarios de producción para filtro
     */
    public function obtenerUsuariosProduccion()
    {
        try {
            return $this->service->obtenerUsuariosProduccion();
        } catch (Exception $e) {
            error_log("Error obteniendo usuarios producción: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener acciones de producción para filtro
     */
    public function obtenerAccionesProduccion()
    {
        try {
            return $this->service->obtenerAccionesProduccion();
        } catch (Exception $e) {
            error_log("Error obteniendo acciones producción: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener estadísticas del historial
     */
    public function obtenerEstadisticasHistorial($filtros = [])
    {
        try {
            return $this->service->obtenerEstadisticasHistorial($filtros);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [
                'total_acciones' => 0,
                'acciones_por_tipo' => [],
                'acciones_por_usuario' => [],
                'acciones_ultima_semana' => 0,
                'ventas_procesadas' => 0
            ];
        }
    }

    /**
     * Verificar permisos para historial (todos los de producción pueden ver)
     */
    public function verificarPermisosHistorial()
    {
        // Permitir a administradores y personal de producción
        $esAdmin = $this->esAdministrador();
        $esProduccion = $this->esProduccion();

        return $esAdmin || $esProduccion;
    }

    /**
     * Generar URL con parámetros para historial
     */
    public function generarUrlHistorialConParametros($archivo, $parametros)
    {
        $url = $this->urlBase . "secciones/sectorPcp/" . $archivo;
        if (!empty($parametros)) {
            $url .= '?' . http_build_query(array_filter($parametros));
        }
        return $url;
    }

    /**
     * Obtener configuración específica para historial
     */
    public function obtenerConfiguracionHistorial()
    {
        $config = $this->obtenerConfiguracionJS();
        $config['historial'] = [
            'actualizar_automatico' => true,
            'intervalo_actualizacion' => 30000, // 30 segundos
            'mostrar_estadisticas' => true
        ];
        return $config;
    }

    /**
     * Validar filtros de historial
     */
    public function validarFiltrosHistorial($filtros)
    {
        $errores = [];

        // Validar fechas
        if (!empty($filtros['fecha_desde']) && !empty($filtros['fecha_hasta'])) {
            if (strtotime($filtros['fecha_desde']) > strtotime($filtros['fecha_hasta'])) {
                $errores[] = 'La fecha desde no puede ser mayor que la fecha hasta';
            }
        }

        // Validar ID de venta
        if (!empty($filtros['id_venta']) && !is_numeric($filtros['id_venta'])) {
            $errores[] = 'El ID de venta debe ser numérico';
        }

        return $errores;
    }


    /**
     * ✅ NUEVO: Formatear información de producto para mostrar en vista
     */
    public function formatearProductoParaVista($producto)
    {
        $unidadReal = $producto['unidadmedida'] ?? 'unidades';

        return [
            'id' => $producto['id'],
            'descripcion' => $producto['descripcion'],
            'tipoproducto' => $producto['tipoproducto'],
            'cantidad' => $producto['cantidad'], // ✅ Ahora este campo contiene la cantidad real de la orden
            'cantidad_formateada' => $this->formatearCantidadConUnidadCorrecta($producto['cantidad'], $unidadReal),
            'unidad' => $unidadReal,
            'color_tipo' => $this->obtenerColorTipoProducto($producto['tipoproducto']),
            'icono_tipo' => $this->obtenerIconoTipoProducto($producto['tipoproducto']),
            'es_unidades' => $this->esProductoEnUnidades($producto['tipoproducto'], $unidadReal),
            'cantidad_inventario' => $producto['cantidad_inventario'] ?? 0,
            'gramatura_calculada' => $producto['gramatura_calculada'] ?? 0
        ];
    }

    /**
     * ✅ NUEVO: Obtener resumen de procesamiento para una venta
     */
    public function obtenerResumenProcesamiento($idVenta)
    {
        try {
            $venta = $this->obtenerVentaParaProcesamiento($idVenta);

            if (!$venta) {
                return false;
            }

            $resumen = [
                'id_venta' => $idVenta,
                'cliente' => $venta['venta']['cliente'],
                'fecha_venta' => $venta['venta']['fecha_venta'],
                'total_productos' => count($venta['productos_produccion']),
                'productos_pendientes' => 0,
                'productos_completados' => count($venta['productos_completados']),
                'productos_formateados' => []
            ];

            // Formatear productos pendientes
            foreach ($venta['productos_produccion'] as $producto) {
                $productoCompleto = $this->obtenerInfoCompletaProducto($producto['id']);
                if ($productoCompleto) {
                    $resumen['productos_formateados'][] = $this->formatearProductoParaVista($productoCompleto);
                    $resumen['productos_pendientes']++;
                }
            }

            return $resumen;
        } catch (Exception $e) {
            error_log("Error obteniendo resumen procesamiento: " . $e->getMessage());
            return false;
        }
    }
}

// Verificar que las dependencias existan
if (!file_exists('repository/produccionRepository.php') || !file_exists('services/produccionService.php')) {
    die("Error: Faltan archivos del sistema MVC. Verifique que existan produccionRepository.php y produccionService.php");
}
