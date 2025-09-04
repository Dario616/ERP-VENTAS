<?php

/**
 * Repository para operaciones de base de datos de relatorios de ventas
 * ✅ VERSIÓN CORREGIDA: Usa tasas dinámicas desde sist_ventas_monedas
 * Excluye: estado NULL/vacío, "En revision", "Pendiente" y monto <= 0
 */
class RelatorioRepository
{
    private $conexion;
    private $debug;
    private static $tasasCache = null; // ✅ Cache estático compartido
    private static $cacheTimestamp = null; // ✅ Para detectar cuando refrescar

    public function __construct($conexion, $debug = false)
    {
        $this->conexion = $conexion;
        $this->debug = $debug;
    }

    private function aplicarFiltrosFecha($fechaInicio, $fechaFin, &$sqlWhere, &$params)
    {
        if ($fechaInicio && $fechaFin) {
            // 🎯 SIMPLE: fecha_venta es DATE, comparar directamente con fechas
            $sqlWhere .= " AND v.fecha_venta >= :fecha_inicio AND v.fecha_venta <= :fecha_fin";
            $params[':fecha_inicio'] = $fechaInicio;  // Solo fecha, sin hora
            $params[':fecha_fin'] = $fechaFin;        // Solo fecha, sin hora
        } elseif ($fechaInicio) {
            $sqlWhere .= " AND v.fecha_venta >= :fecha_inicio";
            $params[':fecha_inicio'] = $fechaInicio;
        } elseif ($fechaFin) {
            $sqlWhere .= " AND v.fecha_venta <= :fecha_fin";
            $params[':fecha_fin'] = $fechaFin;
        }
    }

    /**
     * Obtener métricas generales de ventas convertidas a USD
     * Excluye: estado NULL/vacío, "En revision", "Pendiente" y monto <= 0
     */
    public function obtenerMetricasGenerales($fechaInicio = null, $fechaFin = null, $filtros = [])
    {
        try {
            $sqlWhere = $this->construirWhereBasico();
            $params = [];

            // Aplicar filtros de fecha usando el método auxiliar
            $this->aplicarFiltrosFecha($fechaInicio, $fechaFin, $sqlWhere, $params);

            // Aplicar filtros adicionales
            $sqlWhere .= $this->aplicarFiltros($filtros, $params);

            $sql = "SELECT 
                COUNT(*) as cantidad_ventas,
                COALESCE(SUM(" . $this->getConversionMoneda('v.monto_total') . "), 0) as total_ventas,
                COALESCE(AVG(" . $this->getConversionMoneda('v.monto_total') . "), 0) as promedio_venta,
                COUNT(CASE WHEN v.es_credito = true THEN 1 END) as ventas_credito,
                COUNT(CASE WHEN v.es_credito = false THEN 1 END) as ventas_contado,
                MIN(" . $this->getConversionMoneda('v.monto_total') . ") as venta_minima,
                MAX(" . $this->getConversionMoneda('v.monto_total') . ") as venta_maxima,
                COUNT(DISTINCT v.cliente) as clientes_unicos,
                COUNT(DISTINCT DATE(v.fecha_venta)) as dias_con_ventas,
                -- ✅ NUEVO: PESO TOTAL CALCULADO DINÁMICAMENTE
                COALESCE(SUM(
                    CASE 
                        WHEN LOWER(p.unidadmedida) LIKE '%kilo%' OR LOWER(p.unidadmedida) LIKE '%kg%' 
                        THEN p.cantidad
                        ELSE COALESCE(prod.cantidad * p.cantidad, 0)
                    END
                ), 0) as peso_total_kg
            FROM public.sist_ventas_presupuesto v
            LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
            LEFT JOIN public.sist_ventas_pres_product p ON v.id = p.id_presupuesto
            LEFT JOIN public.sist_ventas_productos prod ON p.id_producto = prod.id  -- ✅ JOIN correcto con tabla productos
            WHERE $sqlWhere";

            $stmt = $this->ejecutarConsulta($sql, $params);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            // ✅ Agregar información de tasas usadas para debug
            $tasasActuales = $this->obtenerTasasActuales();
            $resultado['tasas_aplicadas'] = [
                'PYG_USD' => $tasasActuales['PYG'] ?? 7500,
                'BRL_USD' => $tasasActuales['BRL'] ?? 5.55,
                'USD_USD' => 1,
                'source' => 'BD_dinamica'
            ];

            // ✅ Procesar datos de peso para facilitar uso
            $resultado['peso_total_kg'] = (float)($resultado['peso_total_kg'] ?? 0);
            $resultado['peso_total_formateado'] = $this->formatearPeso($resultado['peso_total_kg']);
            $resultado['peso_promedio_por_venta'] = $resultado['cantidad_ventas'] > 0 ?
                $resultado['peso_total_kg'] / $resultado['cantidad_ventas'] : 0;

            return $resultado;
        } catch (Exception $e) {
            $this->logError("obtenerMetricasGenerales", $e);
            return $this->obtenerMetricasVacias();
        }
    }

    /**
     * ✅ MÉTODO AUXILIAR: Formatear peso para display
     */
    private function formatearPeso($peso)
    {
        if (!is_numeric($peso) || $peso <= 0) return '0 kg';

        if ($peso >= 1000) {
            return number_format($peso / 1000, 1) . ' ton';
        } elseif ($peso >= 1) {
            return number_format($peso, 0) . ' kg';
        } else {
            return number_format($peso * 1000, 0) . ' g';
        }
    }

    /**
     * Obtener ventas por período para gráficos (convertidas a USD)
     * ✅ COMPLETAR PERÍODOS FALTANTES: Añade días con 0 ventas si período ≤ 30 días
     */
    public function obtenerVentasPorPeriodo($fechaInicio = null, $fechaFin = null, $agruparPor = 'dia', $filtros = [])
    {
        try {
            $formatoFecha = $this->obtenerFormatoFecha($agruparPor);
            $sqlWhere = $this->construirWhereBasico();
            $params = [];

            $this->aplicarFiltrosFecha($fechaInicio, $fechaFin, $sqlWhere, $params);
            $sqlWhere .= $this->aplicarFiltros($filtros, $params);

            $sql = "SELECT 
                        TO_CHAR(v.fecha_venta, '$formatoFecha') as periodo,
                        COUNT(*) as cantidad_ventas,
                        COALESCE(SUM(" . $this->getConversionMoneda('v.monto_total') . "), 0) as total_ventas,
                        COALESCE(AVG(" . $this->getConversionMoneda('v.monto_total') . "), 0) as promedio_venta,
                        v.fecha_venta,
                        COUNT(DISTINCT v.cliente) as clientes_periodo
                    FROM public.sist_ventas_presupuesto v
                    LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                    WHERE $sqlWhere
                    GROUP BY periodo, v.fecha_venta
                    ORDER BY v.fecha_venta ASC";

            $stmt = $this->ejecutarConsulta($sql, $params);
            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ✅ COMPLETAR PERÍODOS FALTANTES AUTOMÁTICAMENTE
            $datosCompletos = $this->completarPeriodosFaltantes($datos, $fechaInicio, $fechaFin, $agruparPor);

            if ($this->debug && count($datosCompletos) > count($datos)) {
                $diasOriginales = count($datos);
                $diasCompletos = count($datosCompletos);
                $diasAnadidos = $diasCompletos - $diasOriginales;

                error_log("📈 GRÁFICO PERÍODO COMPLETADO: $diasOriginales → $diasCompletos datos (+$diasAnadidos días con 0 ventas)");
            }

            return $datosCompletos;
        } catch (Exception $e) {
            $this->logError("obtenerVentasPorPeriodo", $e);
            return [];
        }
    }

    /**
     * Obtener productos más vendidos (valores convertidos a USD)
     */
    /**
     * Obtener productos más vendidos (valores convertidos a USD)
     * ✅ FIX: Ahora trae más productos para permitir ordenamiento flexible en frontend
     */
    public function obtenerProductosMasVendidos($fechaInicio = null, $fechaFin = null, $limite = 10, $filtros = [])
    {
        try {
            $sqlWhere = $this->construirWhereBasico();
            $params = [];

            // ✅ FIX: Aumentar límite para dar más flexibilidad al frontend
            $limiteAmpliado = max($limite * 3, 15); // Mínimo 15 productos
            $params[':limite'] = $limiteAmpliado;

            $this->aplicarFiltrosFecha($fechaInicio, $fechaFin, $sqlWhere, $params);
            $sqlWhere .= $this->aplicarFiltros($filtros, $params);
            $sql = "SELECT 
                p.descripcion,
                p.tipoproducto,
                SUM(p.cantidad) as cantidad_vendida,
                COUNT(DISTINCT v.id) as ventas_asociadas,
                COALESCE(SUM(" . $this->getConversionMonedaProducto('p.total', 'v') . "), 0) as total_ingresos,
                COALESCE(AVG(" . $this->getConversionMonedaProducto('p.precio', 'v') . "), 0) as precio_promedio,
                MIN(" . $this->getConversionMonedaProducto('p.precio', 'v') . ") as precio_minimo,
                MAX(" . $this->getConversionMonedaProducto('p.precio', 'v') . ") as precio_maximo
            FROM public.sist_ventas_pres_product p
            INNER JOIN public.sist_ventas_presupuesto v ON p.id_presupuesto = v.id
            LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
            WHERE $sqlWhere
            GROUP BY p.descripcion, p.tipoproducto
            HAVING SUM(p.cantidad) > 0
            ORDER BY 
                total_ingresos DESC,     -- ✅ Priorizar por ingresos
                cantidad_vendida DESC,   -- ✅ Luego por cantidad
                ventas_asociadas DESC    -- ✅ Finalmente por número de ventas
            LIMIT :limite";

            $stmt = $this->ejecutarConsulta($sql, $params, true);
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ✅ DEBUG: Log para verificar que funciona
            if ($this->debug) {
                error_log("✅ Productos obtenidos: " . count($productos) . " (límite solicitado: $limite, ampliado: $limiteAmpliado)");
                if (!empty($productos)) {
                    error_log("🥇 Top producto: " . $productos[0]['descripcion'] . " (Ingresos: " . $productos[0]['total_ingresos'] . ")");
                }
            }

            return $productos;
        } catch (Exception $e) {
            $this->logError("obtenerProductosMasVendidos", $e);
            return [];
        }
    }

    /**
     * Obtener ventas por vendedor (valores convertidos a USD)
     */
    public function obtenerVentasPorVendedor($fechaInicio = null, $fechaFin = null, $filtros = [])
    {
        try {
            $params = [];
            $whereConditions = [
                "v.monto_total > 0",
                "v.estado IS NOT NULL",
                "v.estado != ''",
                "v.estado NOT IN ('En revision', 'Pendiente', 'Rechazado')"
            ];

            // 🔥 CORREGIR: usar filtros consistentes
            if ($fechaInicio && $fechaFin) {
                $whereConditions[] = "v.fecha_venta >= :fecha_inicio";
                $whereConditions[] = "v.fecha_venta <= :fecha_fin";
                $params[':fecha_inicio'] = $fechaInicio;  // Sin agregar tiempo
                $params[':fecha_fin'] = $fechaFin;        // Sin agregar tiempo
            }

            $sqlWhere = implode(' AND ', $whereConditions);

            // Aplicar filtros adicionales (excepto vendedor)
            $filtrosParaAplicar = $filtros;
            unset($filtrosParaAplicar['vendedor']);
            $sqlWhere .= $this->aplicarFiltros($filtrosParaAplicar, $params);

            $sql = "SELECT 
                u.id as id_vendedor,
                u.nombre as nombre_vendedor,
                COUNT(v.id) as cantidad_ventas,
                COALESCE(SUM(" . $this->getConversionMoneda('v.monto_total') . "), 0) as total_ventas,
                COALESCE(AVG(" . $this->getConversionMoneda('v.monto_total') . "), 0) as promedio_venta,
                COUNT(CASE WHEN v.es_credito = true THEN 1 END) as ventas_credito,
                COUNT(CASE WHEN v.es_credito = false THEN 1 END) as ventas_contado,
                COUNT(DISTINCT v.cliente) as clientes_atendidos,
                MAX(v.fecha_venta) as ultima_venta,
                MIN(v.fecha_venta) as primera_venta
            FROM public.sist_ventas_usuario u
            LEFT JOIN public.sist_ventas_presupuesto v ON u.id = v.id_usuario 
                AND $sqlWhere
            WHERE u.rol IN ('1', '2')
            GROUP BY u.id, u.nombre
            HAVING COUNT(v.id) > 0
            ORDER BY SUM(" . $this->getConversionMoneda('v.monto_total') . ") DESC";

            $stmt = $this->ejecutarConsulta($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logError("obtenerVentasPorVendedor", $e);
            return [];
        }
    }

    /**
     * Obtener ventas detalladas (valores convertidos a USD)
     */
    public function obtenerVentasDetalladas($fechaInicio = null, $fechaFin = null, $filtros = [])
    {
        try {
            $sqlWhere = $this->construirWhereBasico();
            $params = [];

            // 🔥 USAR EL MÉTODO ESTÁNDAR
            $this->aplicarFiltrosFecha($fechaInicio, $fechaFin, $sqlWhere, $params);
            $sqlWhere .= $this->aplicarFiltros($filtros, $params);

            $sql = "SELECT 
                    v.id,
                    v.fecha_venta,
                    COALESCE(v.cliente, 'Sin cliente') as cliente,
                    COALESCE(u.nombre, 'Sin asignar') as nombre_vendedor,
                    COALESCE(v.estado, 'Sin estado') as estado,
                    COALESCE(v.moneda, 'USD') as moneda_original,
                    'USD' as moneda,
                    COALESCE(" . $this->getConversionMoneda('v.subtotal') . ", 0) as subtotal,
                    COALESCE(" . $this->getConversionMoneda('v.monto_total') . ", 0) as monto_total,
                    COALESCE(v.cond_pago, 'Sin especificar') as cond_pago,
                    COALESCE(v.tipo_pago, 'Sin especificar') as tipo_pago,
                    v.es_credito,
                    COALESCE(v.tipocredito, 'Sin especificar') as tipocredito,
                    COALESCE(v.descripcion, '') as descripcion,
                    COALESCE(v.tipoflete, 'Sin especificar') as tipoflete,
                    COALESCE(v.transportadora, 'Sin especificar') as transportadora,
                    COALESCE(" . $this->getConversionMoneda('v.iva') . ", 0) as iva,
                    COALESCE(" . $this->getConversionMoneda('v.descuento') . ", 0) as descuento,
                    v.proforma,
                    (SELECT COUNT(*) FROM public.sist_ventas_pres_product WHERE id_presupuesto = v.id) as cantidad_productos
                FROM public.sist_ventas_presupuesto v
                LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                WHERE $sqlWhere
                ORDER BY v.fecha_venta DESC, v.id DESC";

            $stmt = $this->ejecutarConsulta($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logError("obtenerVentasDetalladas", $e);
            return [];
        }
    }

    /**
     * Obtener vendedores
     */
    public function obtenerVendedores()
    {
        try {
            $sql = "SELECT DISTINCT 
                        u.id, 
                        u.nombre,
                        u.rol,
                        COUNT(v.id) as total_ventas_historicas
                    FROM public.sist_ventas_usuario u
                    LEFT JOIN public.sist_ventas_presupuesto v ON u.id = v.id_usuario
                    WHERE u.rol IN ('1', '2')
                    GROUP BY u.id, u.nombre, u.rol
                    ORDER BY u.nombre";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logError("obtenerVendedores", $e);
            return [];
        }
    }

    /**
     * Obtener clientes con ventas
     */
    public function obtenerClientesConVentas()
    {
        try {
            $sql = "SELECT DISTINCT v.cliente 
                    FROM public.sist_ventas_presupuesto v
                    WHERE v.cliente IS NOT NULL 
                        AND v.cliente != ''
                        AND v.monto_total > 0
                        AND v.estado IS NOT NULL 
                        AND v.estado != ''
                        AND v.estado NOT IN ('En revision', 'Pendiente', 'Rechazado')
                    ORDER BY v.cliente";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $this->logError("obtenerClientesConVentas", $e);
            return [];
        }
    }

    /**
     * Obtener estados de ventas
     */
    public function obtenerEstadosVentas()
    {
        try {
            $sql = "SELECT DISTINCT v.estado 
                    FROM public.sist_ventas_presupuesto v
                    WHERE v.estado IS NOT NULL 
                        AND v.estado != ''
                        AND v.monto_total > 0
                        AND v.estado NOT IN ('En revision', 'Pendiente', 'Rechazado')
                    ORDER BY v.estado";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $this->logError("obtenerEstadosVentas", $e);
            return [];
        }
    }

    /**
     * Obtener productos de una venta específica (valores convertidos a USD)
     */
    public function obtenerProductosVenta($ventaId)
    {
        try {
            $sql = "SELECT 
                    p.id,
                    COALESCE(p.descripcion, 'Producto sin descripción') as descripcion,
                    COALESCE(p.tipoproducto, 'Sin categoría') as categoria,
                    p.cantidad,
                    p.unidadmedida,
                    p.ncm as codigo,
                    COALESCE(p.moneda, 'PYG') as moneda_original,
                    'USD' as moneda,
                    p.precio as precio_original,
                    p.total as total_original,
                    -- Conversión a USD del precio unitario
                    " . $this->getConversionMonedaProducto('p.precio', 'v') . " as precio_unitario,
                    -- Conversión a USD del total
                    " . $this->getConversionMonedaProducto('p.total', 'v') . " as total_usd,
                    -- Verificar si pertenece a una venta válida
                    v.id as venta_id,
                    v.cliente,
                    v.fecha_venta,
                    v.estado as estado_venta
                FROM public.sist_ventas_pres_product p
                INNER JOIN public.sist_ventas_presupuesto v ON p.id_presupuesto = v.id
                WHERE p.id_presupuesto = :venta_id
                    AND v.monto_total > 0
                    AND v.estado IS NOT NULL 
                    AND v.estado != ''
                    AND v.estado NOT IN ('En revision', 'Pendiente', 'Rechazado')
                ORDER BY p.id ASC";

            $params = [':venta_id' => $ventaId];
            $stmt = $this->ejecutarConsulta($sql, $params);

            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Enriquecer datos con información adicional
            return array_map(function ($producto) {
                // Validar cantidad
                $cantidad = max(0, (float)$producto['cantidad']);
                $precioUnitario = (float)$producto['precio_unitario'];

                // Recalcular total para verificar consistencia
                $totalCalculado = $cantidad * $precioUnitario;

                return [
                    'id' => $producto['id'],
                    'nombre_producto' => $producto['descripcion'],
                    'descripcion' => $producto['categoria'],
                    'codigo' => $producto['codigo'] ?: 'Sin código',
                    'cantidad' => $cantidad,
                    'unidad_medida' => $producto['unidadmedida'] ?: 'Unidad',
                    'precio_original' => $producto['precio_original'],
                    'precio_unitario' => $precioUnitario,
                    'total_original' => $producto['total_original'],
                    'total_usd' => $producto['total_usd'],
                    'total_calculado' => $totalCalculado,
                    'moneda_original' => $producto['moneda_original'],
                    'moneda' => 'USD',
                    'venta_id' => $producto['venta_id'],
                    'cliente' => $producto['cliente'],
                    'fecha_venta' => $producto['fecha_venta'],
                    'estado_venta' => $producto['estado_venta']
                ];
            }, $productos);
        } catch (Exception $e) {
            $this->logError("obtenerProductosVenta", $e);
            return [];
        }
    }

    // ============================================================================
    // MÉTODOS PRIVADOS AUXILIARES - CONVERSIÓN DINÁMICA
    // ============================================================================

    /**
     * ✅ MÉTODO PRINCIPAL: Obtener tasas actuales desde BD (con cache eficiente)
     */
    private function obtenerTasasActuales()
    {
        // Cache por 5 minutos para performance
        $ahora = time();
        if (
            self::$tasasCache === null ||
            self::$cacheTimestamp === null ||
            ($ahora - self::$cacheTimestamp) > 300
        ) { // 5 minutos

            try {
                $sql = "SELECT moneda, valor, fecha_actualizacion 
                        FROM public.sist_ventas_monedas 
                        WHERE valor > 0 
                        ORDER BY moneda";

                $stmt = $this->conexion->prepare($sql);
                $stmt->execute();
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

                self::$tasasCache = [];
                foreach ($resultados as $fila) {
                    $moneda = strtolower(trim($fila['moneda']));

                    // Mapeo robusto de nombres de moneda
                    if (in_array($moneda, ['guaraníes', 'guaranies', 'guarani', 'pyg'])) {
                        self::$tasasCache['PYG'] = (float)$fila['valor'];
                    } elseif (in_array($moneda, ['real brasileño', 'real brasileno', 'real', 'brl', 'reales'])) {
                        self::$tasasCache['BRL'] = (float)$fila['valor'];
                    } elseif (in_array($moneda, ['dólares', 'dolares', 'dolar', 'usd', 'dollar'])) {
                        self::$tasasCache['USD'] = (float)$fila['valor'];
                    }
                }

                // Asegurar valores por defecto
                if (!isset(self::$tasasCache['PYG'])) {
                    self::$tasasCache['PYG'] = 7500;
                }
                if (!isset(self::$tasasCache['BRL'])) {
                    self::$tasasCache['BRL'] = 5.55;
                }
                if (!isset(self::$tasasCache['USD'])) {
                    self::$tasasCache['USD'] = 1;
                }

                self::$cacheTimestamp = $ahora;

                if ($this->debug) {
                    error_log("✅ Tasas cargadas desde BD: " . json_encode(self::$tasasCache));
                }
            } catch (Exception $e) {
                error_log("❌ Error cargando tasas dinámicas: " . $e->getMessage());

                // Fallback robusto
                self::$tasasCache = [
                    'PYG' => 7500,
                    'BRL' => 5.55,
                    'USD' => 1
                ];
                self::$cacheTimestamp = $ahora;
            }
        }

        return self::$tasasCache;
    }

    /**
     * ✅ CONVERSIÓN DINÁMICA para tablas principales (ventas)
     */
    private function getConversionMoneda($campo, $tablaAlias = 'v')
    {
        $tasas = $this->obtenerTasasActuales();
        $tasaPYG = $tasas['PYG'];
        $tasaBRL = $tasas['BRL'];

        return "CASE 
                WHEN {$tablaAlias}.moneda IN ('Guaraníes', 'PYG', 'G', '₲', 'Guarani', 'GS') 
                    THEN {$campo} / {$tasaPYG}
                WHEN {$tablaAlias}.moneda IN ('Real', 'BRL', 'R$', 'Real brasileño', 'Reales') 
                    THEN {$campo} / {$tasaBRL}
                WHEN {$tablaAlias}.moneda IN ('Dólares', 'USD', '$', 'Dolares', 'Dollar') 
                    THEN {$campo}
                WHEN {$tablaAlias}.moneda IS NULL OR {$tablaAlias}.moneda = '' 
                    THEN {$campo} / {$tasaPYG}
                ELSE {$campo}
            END";
    }

    /**
     * ✅ CONVERSIÓN DINÁMICA para productos (corregido)
     */
    private function getConversionMonedaProducto($campo, $tablaAliasVenta = 'v')
    {
        $tasas = $this->obtenerTasasActuales();
        $tasaPYG = $tasas['PYG'];
        $tasaBRL = $tasas['BRL'];

        // ✅ CORREGIDO: Usar la moneda de la venta (no del producto)
        return "CASE 
                WHEN {$tablaAliasVenta}.moneda IN ('Guaraníes', 'PYG', 'G', '₲', 'Guarani', 'GS') 
                    THEN {$campo} / {$tasaPYG}
                WHEN {$tablaAliasVenta}.moneda IN ('Real', 'BRL', 'R$', 'Real brasileño', 'Reales') 
                    THEN {$campo} / {$tasaBRL}
                WHEN {$tablaAliasVenta}.moneda IN ('Dólares', 'USD', '$', 'Dolares', 'Dollar') 
                    THEN {$campo}
                WHEN {$tablaAliasVenta}.moneda IS NULL OR {$tablaAliasVenta}.moneda = '' 
                    THEN {$campo} / {$tasaPYG}
                ELSE {$campo}
            END";
    }

    // ============================================================================
    // MÉTODOS PARA GESTIÓN DE TASAS
    // ============================================================================

    /**
     * ✅ Obtener tasas de conversión desde BD (para API)
     */
    public function obtenerTasasMoneda()
    {
        try {
            $sql = "SELECT moneda, valor, fecha_actualizacion
                    FROM public.sist_ventas_monedas 
                    WHERE valor > 0 
                    ORDER BY moneda";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $tasas = [];
            foreach ($resultados as $fila) {
                $tasas[strtolower(trim($fila['moneda']))] = (float)$fila['valor'];
            }

            return $tasas;
        } catch (Exception $e) {
            error_log("Error obteniendo tasas de moneda: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ✅ Actualizar tasas en BD y limpiar cache
     */
    public function actualizarTasasMoneda($tasas)
    {
        try {
            $this->conexion->beginTransaction();

            foreach ($tasas as $moneda => $valor) {
                // Primero intentar UPDATE
                $sql = "UPDATE public.sist_ventas_monedas 
                        SET valor = :valor, fecha_actualizacion = NOW() 
                        WHERE moneda = :moneda";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindValue(':moneda', $moneda);
                $stmt->bindValue(':valor', (float)$valor);
                $stmt->execute();

                // Si no se actualizó ninguna fila, insertar
                if ($stmt->rowCount() === 0) {
                    $sqlInsert = "INSERT INTO public.sist_ventas_monedas (moneda, valor, fecha_actualizacion) 
                                  VALUES (:moneda, :valor, NOW())";
                    $stmtInsert = $this->conexion->prepare($sqlInsert);
                    $stmtInsert->bindValue(':moneda', $moneda);
                    $stmtInsert->bindValue(':valor', (float)$valor);
                    $stmtInsert->execute();
                }
            }

            $this->conexion->commit();

            // ✅ LIMPIAR CACHE CORRECTAMENTE
            $this->limpiarCacheTasas();

            if ($this->debug) {
                error_log("✅ Tasas actualizadas en BD: " . json_encode($tasas));
            }

            return true;
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("❌ Error actualizando tasas: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ✅ Limpiar cache de tasas (corregido)
     */
    public function limpiarCacheTasas()
    {
        self::$tasasCache = null;
        self::$cacheTimestamp = null;

        if ($this->debug) {
            error_log("🔄 Cache de tasas limpiado - se recargará en próxima consulta");
        }
    }

    /**
     * ✅ Obtener info de tasas para API frontend 
     */
    public function obtenerTasasParaFrontend()
    {
        $tasasActuales = $this->obtenerTasasActuales();

        return [
            'PYG' => $tasasActuales['PYG'],
            'BRL' => $tasasActuales['BRL'],
            'USD' => $tasasActuales['USD'],
            'timestamp' => self::$cacheTimestamp,
            'source' => 'BD_dinamica'
        ];
    }

    // ============================================================================
    // MÉTODOS AUXILIARES EXISTENTES (sin cambios)
    // ============================================================================

    /**
     * Construir WHERE básico (monto > 0 y estado válido, excluyendo pendientes y en revisión)
     */
    private function construirWhereBasico()
    {
        return "v.monto_total > 0 AND v.estado IS NOT NULL AND v.estado != '' AND v.estado NOT IN ('En revision', 'Pendiente', 'Rechazado')";
    }

    /**
     * Aplicar filtros adicionales
     */
    private function aplicarFiltros($filtros, &$params)
    {
        $sqlWhere = '';

        if (!empty($filtros['cliente'])) {
            $sqlWhere .= " AND v.cliente ILIKE :cliente";
            $params[':cliente'] = '%' . $filtros['cliente'] . '%';
        }

        if (!empty($filtros['vendedor'])) {
            $sqlWhere .= " AND u.id = :vendedor";
            $params[':vendedor'] = $filtros['vendedor'];
        }

        if (!empty($filtros['estado'])) {
            $sqlWhere .= " AND v.estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }

        return $sqlWhere;
    }

    /**
     * Obtener formato de fecha según agrupación
     */
    private function obtenerFormatoFecha($agruparPor)
    {
        switch ($agruparPor) {
            case 'dia':
                return 'YYYY-MM-DD';
            case 'semana':
                return 'YYYY-"W"WW';
            case 'mes':
                return 'YYYY-MM';
            case 'año':
                return 'YYYY';
            default:
                return 'YYYY-MM-DD';
        }
    }

    /**
     * Ejecutar consulta con manejo de errores
     */
    private function ejecutarConsulta($sql, $params, $conLimite = false)
    {
        $stmt = $this->conexion->prepare($sql);

        foreach ($params as $key => $value) {
            if ($conLimite && $key === ':limite') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }

        if ($this->debug) {
            error_log("SQL: " . $sql);
            error_log("Params: " . json_encode($params));
            error_log("Tasas actuales: " . json_encode($this->obtenerTasasActuales()));
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * Obtener distribución de ventas por moneda (valores originales y convertidos)
     * Muestra el total en cada moneda original y su equivalente en USD
     */
    public function obtenerDistribucionPorMoneda($fechaInicio = null, $fechaFin = null, $filtros = [])
    {
        try {
            $sqlWhere = $this->construirWhereBasico();
            $params = [];

            // Aplicar filtros de fecha
            $this->aplicarFiltrosFecha($fechaInicio, $fechaFin, $sqlWhere, $params);

            // Aplicar filtros adicionales
            $sqlWhere .= $this->aplicarFiltros($filtros, $params);

            $sql = "SELECT 
                COALESCE(v.moneda, 'PYG') as moneda_original,
                COUNT(*) as cantidad_ventas,
                COALESCE(SUM(v.monto_total), 0) as total_original,
                COALESCE(SUM(" . $this->getConversionMoneda('v.monto_total') . "), 0) as total_usd,
                COALESCE(AVG(v.monto_total), 0) as promedio_original,
                COALESCE(AVG(" . $this->getConversionMoneda('v.monto_total') . "), 0) as promedio_usd,
                MIN(v.monto_total) as venta_minima_original,
                MAX(v.monto_total) as venta_maxima_original,
                COUNT(DISTINCT v.cliente) as clientes_unicos
            FROM public.sist_ventas_presupuesto v
            LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
            WHERE $sqlWhere
            GROUP BY v.moneda
            ORDER BY total_usd DESC";

            $stmt = $this->ejecutarConsulta($sql, $params);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular porcentajes
            $totalGeneral = array_sum(array_column($resultados, 'total_usd'));

            return array_map(function ($item) use ($totalGeneral) {
                $porcentaje = $totalGeneral > 0 ? ($item['total_usd'] / $totalGeneral) * 100 : 0;

                // Normalizar nombre de moneda
                $monedaNormalizada = $this->normalizarCodigoMoneda($item['moneda_original']);

                return [
                    'moneda_original' => $monedaNormalizada,
                    'cantidad_ventas' => (int)$item['cantidad_ventas'],
                    'total_original' => (float)$item['total_original'],
                    'total_usd' => (float)$item['total_usd'],
                    'promedio_original' => (float)$item['promedio_original'],
                    'promedio_usd' => (float)$item['promedio_usd'],
                    'porcentaje' => round($porcentaje, 2),
                    'participacion_texto' => round($porcentaje, 1) . '%',
                    'venta_minima_original' => (float)$item['venta_minima_original'],
                    'venta_maxima_original' => (float)$item['venta_maxima_original'],
                    'clientes_unicos' => (int)$item['clientes_unicos'],
                    'tasa_conversion' => $this->obtenerTasaConversion($monedaNormalizada)
                ];
            }, $resultados);
        } catch (Exception $e) {
            $this->logError("obtenerDistribucionPorMoneda", $e);
            return [];
        }
    }

    /**
     * Normalizar código de moneda para consistencia
     */
    private function normalizarCodigoMoneda($moneda)
    {
        if (!$moneda || trim($moneda) === '') {
            return 'PYG'; // Por defecto Guaraníes
        }

        $moneda = strtolower(trim($moneda));

        // Mapeo de variantes a códigos estándar
        $mapeo = [
            'guaraníes' => 'PYG',
            'guaranies' => 'PYG',
            'guarani' => 'PYG',
            'pyg' => 'PYG',
            'g' => 'PYG',
            '₲' => 'PYG',
            'gs' => 'PYG',

            'real brasileño' => 'BRL',
            'real brasileno' => 'BRL',
            'real' => 'BRL',
            'brl' => 'BRL',
            'r$' => 'BRL',
            'reales' => 'BRL',

            'dólares' => 'USD',
            'dolares' => 'USD',
            'dolar' => 'USD',
            'usd' => 'USD',
            '$' => 'USD',
            'dollar' => 'USD'
        ];

        return $mapeo[$moneda] ?? strtoupper($moneda);
    }

    /**
     * Obtener tasa de conversión para una moneda específica
     */
    private function obtenerTasaConversion($codigoMoneda)
    {
        $tasas = $this->obtenerTasasActuales();

        switch ($codigoMoneda) {
            case 'PYG':
                return $tasas['PYG'] ?? 7500;
            case 'BRL':
                return $tasas['BRL'] ?? 5.55;
            case 'USD':
                return 1;
            default:
                return 1;
        }
    }

    /**
     * Log de errores
     */
    private function logError($metodo, $exception)
    {
        error_log("Error en RelatorioRepository::$metodo: " . $exception->getMessage());
        if ($this->debug) {
            error_log("Stack trace: " . $exception->getTraceAsString());
        }
    }

    /**
     * Obtener métricas vacías por defecto
     */
    private function obtenerMetricasVacias()
    {
        return [
            'cantidad_ventas' => 0,
            'total_ventas' => 0,
            'promedio_venta' => 0,
            'ventas_credito' => 0,
            'ventas_contado' => 0,
            'venta_minima' => 0,
            'venta_maxima' => 0,
            'clientes_unicos' => 0,
            'dias_con_ventas' => 0,
            'peso_total_kg' => 0,  // ✅ AGREGAR
            'peso_total_formateado' => '0 kg',  // ✅ AGREGAR
            'peso_promedio_por_venta' => 0,  // ✅ AGREGAR
            'tasas_aplicadas' => [
                'PYG_USD' => 7500,
                'BRL_USD' => 5.55,
                'USD_USD' => 1,
                'source' => 'fallback'
            ]
        ];
    }
    /**
     * Obtener distribución de kilos por vendedor
     */
    public function obtenerDistribucionKilosPorVendedor($fechaInicio = null, $fechaFin = null, $filtros = [])
    {
        try {
            $sqlWhere = $this->construirWhereBasico();
            $params = [];

            $this->aplicarFiltrosFecha($fechaInicio, $fechaFin, $sqlWhere, $params);
            $filtrosParaAplicar = $filtros;
            unset($filtrosParaAplicar['vendedor']); // Remover filtro vendedor
            $sqlWhere .= $this->aplicarFiltros($filtrosParaAplicar, $params);

            $sql = "SELECT 
            u.id as id_vendedor,
            u.nombre as nombre_vendedor,
            COUNT(v.id) as cantidad_ventas,
            COALESCE(SUM(
                CASE 
                    WHEN LOWER(p.unidadmedida) LIKE '%kilo%' OR LOWER(p.unidadmedida) LIKE '%kg%' 
                    THEN p.cantidad
                    ELSE COALESCE(prod.cantidad * p.cantidad, 0)
                END
            ), 0) as kilos_total,
            COALESCE(SUM(" . $this->getConversionMoneda('v.monto_total') . "), 0) as total_ventas
        FROM public.sist_ventas_usuario u
        LEFT JOIN public.sist_ventas_presupuesto v ON u.id = v.id_usuario AND $sqlWhere
        LEFT JOIN public.sist_ventas_pres_product p ON v.id = p.id_presupuesto
        LEFT JOIN public.sist_ventas_productos prod ON p.id_producto = prod.id
        WHERE u.rol IN ('1', '2')
        GROUP BY u.id, u.nombre
        HAVING SUM(
            CASE 
                WHEN LOWER(p.unidadmedida) LIKE '%kilo%' OR LOWER(p.unidadmedida) LIKE '%kg%' 
                THEN p.cantidad
                ELSE COALESCE(prod.cantidad * p.cantidad, 0)
            END
        ) > 0
        ORDER BY kilos_total DESC";

            $stmt = $this->ejecutarConsulta($sql, $params);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular porcentajes
            $totalKilos = array_sum(array_column($resultados, 'kilos_total'));

            return array_map(function ($item) use ($totalKilos) {
                $porcentaje = $totalKilos > 0 ? ($item['kilos_total'] / $totalKilos) * 100 : 0;

                return [
                    'vendedor' => $item['nombre_vendedor'],
                    'kilos_total' => (float)$item['kilos_total'],
                    'cantidad_ventas' => (int)$item['cantidad_ventas'],
                    'total_ventas' => (float)$item['total_ventas'],
                    'porcentaje' => round($porcentaje, 2),
                    'kilos_formateado' => $this->formatearPeso($item['kilos_total'])
                ];
            }, $resultados);
        } catch (Exception $e) {
            $this->logError("obtenerDistribucionKilosPorVendedor", $e);
            return [];
        }
    }

    /**
     * Obtener distribución crédito vs contado
     */
    public function obtenerDistribucionCredito($fechaInicio = null, $fechaFin = null, $filtros = [])
    {
        try {
            $sqlWhere = $this->construirWhereBasico();
            $params = [];

            $this->aplicarFiltrosFecha($fechaInicio, $fechaFin, $sqlWhere, $params);
            $sqlWhere .= $this->aplicarFiltros($filtros, $params);

            $sql = "SELECT 
            CASE 
                WHEN v.es_credito = true THEN 'Crédito'
                ELSE 'Contado'
            END as tipo_pago,
            COUNT(*) as cantidad_ventas,
            COALESCE(SUM(" . $this->getConversionMoneda('v.monto_total') . "), 0) as total_ventas,
            COALESCE(AVG(" . $this->getConversionMoneda('v.monto_total') . "), 0) as promedio_venta,
            COUNT(DISTINCT v.cliente) as clientes_unicos
        FROM public.sist_ventas_presupuesto v
        LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
        WHERE $sqlWhere
        GROUP BY v.es_credito
        ORDER BY total_ventas DESC";

            $stmt = $this->ejecutarConsulta($sql, $params);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular porcentajes
            $totalVentas = array_sum(array_column($resultados, 'total_ventas'));

            return array_map(function ($item) use ($totalVentas) {
                $porcentaje = $totalVentas > 0 ? ($item['total_ventas'] / $totalVentas) * 100 : 0;

                return [
                    'tipo' => $item['tipo_pago'],
                    'cantidad_ventas' => (int)$item['cantidad_ventas'],
                    'total_ventas' => (float)$item['total_ventas'],
                    'promedio_venta' => (float)$item['promedio_venta'],
                    'clientes_unicos' => (int)$item['clientes_unicos'],
                    'porcentaje' => round($porcentaje, 2),
                    'total_formateado' => '$' . number_format($item['total_ventas'], 2)
                ];
            }, $resultados);
        } catch (Exception $e) {
            $this->logError("obtenerDistribucionCredito", $e);
            return [];
        }
    }

    /**
     * Completar períodos faltantes con valores cero
     * ✅ IMPLEMENTACIÓN COMPLETA: Llena días/meses faltantes según el tipo de agrupación
     */
    private function completarPeriodosFaltantes($datos, $fechaInicio, $fechaFin, $agruparPor)
    {
        if (empty($datos) || !$fechaInicio || !$fechaFin) {
            return $datos;
        }

        try {
            // Calcular diferencia de días para decidir si completar
            $inicio = new DateTime($fechaInicio);
            $fin = new DateTime($fechaFin);
            $diferenciaDias = $fin->diff($inicio)->days + 1;

            // 🎯 COMPLETAR SOLO SI ES POR DÍAS Y PERÍODO ≤ 30 DÍAS
            if ($agruparPor !== 'dia' || $diferenciaDias > 31) {
                if ($this->debug) {
                    error_log("⏭️ Saltando completar períodos: agrupar=$agruparPor, días=$diferenciaDias");
                }
                return $datos;
            }

            // Crear array asociativo con los datos existentes
            $datosExistentes = [];
            foreach ($datos as $item) {
                $datosExistentes[$item['fecha_venta']] = $item;
            }

            // Generar array completo de fechas
            $fechaActual = clone $inicio;
            $datosCompletos = [];

            while ($fechaActual <= $fin) {
                $fechaStr = $fechaActual->format('Y-m-d');

                if (isset($datosExistentes[$fechaStr])) {
                    // Usar datos existentes
                    $datosCompletos[] = $datosExistentes[$fechaStr];
                } else {
                    // Crear entrada con 0 ventas para día faltante
                    $datosCompletos[] = [
                        'periodo' => $fechaActual->format('Y-m-d'),
                        'cantidad_ventas' => 0,
                        'total_ventas' => 0,
                        'promedio_venta' => 0,
                        'fecha_venta' => $fechaStr,
                        'clientes_periodo' => 0,
                        // Marcar como día completado para debug
                        '_completado' => true
                    ];
                }

                $fechaActual->add(new DateInterval('P1D'));
            }

            if ($this->debug) {
                $datosOriginales = count($datos);
                $datosFinales = count($datosCompletos);
                $diasCompletados = $datosFinales - $datosOriginales;

                error_log("✅ Períodos completados: $datosOriginales → $datosFinales datos ($diasCompletados días con 0 ventas)");

                // Mostrar algunos ejemplos de días completados
                $ejemplosCompletados = array_filter($datosCompletos, function ($item) {
                    return isset($item['_completado']);
                });

                if (!empty($ejemplosCompletados)) {
                    $primeros3 = array_slice($ejemplosCompletados, 0, 3);
                    error_log("📋 Ejemplos de días completados: " .
                        implode(', ', array_column($primeros3, 'fecha_venta')));
                }
            }

            return $datosCompletos;
        } catch (Exception $e) {
            error_log("❌ Error completando períodos faltantes: " . $e->getMessage());
            // En caso de error, devolver datos originales
            return $datos;
        }
    }
}
