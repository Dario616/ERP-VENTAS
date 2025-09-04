<?php

/**
 * Servicio para manejo de datos del relatorio
 */
class RelatorioDataService
{
    private $controller;

    public function __construct($controller)
    {
        $this->controller = $controller;
    }

    /**
     * Obtiene datos para PDF con manejo de errores
     */
    public function obtenerDatosParaPDF($action, $filtros)
    {
        try {
            // Limpiar output buffer previo
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Configurar la acción
            $_GET['action'] = $action;

            // Aplicar filtros específicamente
            foreach ($filtros as $key => $value) {
                if (!empty($value)) {
                    $_GET[$key] = $value;
                }
            }

            // Capturar la salida del controller
            ob_start();
            $this->controller->handleApiRequest();
            $jsonOutput = ob_get_clean();

            // Decodificar JSON
            $response = json_decode($jsonOutput, true);

            if ($response && isset($response['success']) && $response['success'] && isset($response['datos'])) {
                error_log("✅ Datos obtenidos para $action: " . count($response['datos']) . " registros");
                return $response['datos'];
            } else {
                error_log("❌ Error en respuesta para $action: " . ($response['error'] ?? 'Respuesta inválida'));
                return [];
            }
        } catch (Exception $e) {
            error_log("❌ Excepción en obtenerDatosParaPDF para $action: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene distribución por tipo de producto
     */
    public function obtenerDistribucionTipoProducto($filtros)
    {
        try {
            // Limpiar output buffer previo
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Configurar la acción para obtener productos
            $_GET['action'] = 'productos_mas_vendidos';
            $_GET['limite'] = 50;

            // Aplicar filtros
            foreach ($filtros as $key => $value) {
                if (!empty($value)) {
                    $_GET[$key] = $value;
                }
            }

            // Capturar la salida del controller
            ob_start();
            $this->controller->handleApiRequest();
            $jsonOutput = ob_get_clean();

            // Decodificar JSON
            $response = json_decode($jsonOutput, true);

            if ($response && isset($response['success']) && $response['success'] && isset($response['datos'])) {
                $productos = $response['datos'];

                // Agrupar por tipo de producto
                $tiposProductos = [];
                foreach ($productos as $producto) {
                    $tipo = $producto['tipoproducto'] ?: 'Sin categoría';

                    if (!isset($tiposProductos[$tipo])) {
                        $tiposProductos[$tipo] = [
                            'tipo' => $tipo,
                            'cantidad_vendida' => 0,
                            'total_ingresos' => 0,
                            'productos_diferentes' => 0
                        ];
                    }

                    $tiposProductos[$tipo]['cantidad_vendida'] += (float)$producto['cantidad_vendida'];
                    $tiposProductos[$tipo]['total_ingresos'] += (float)$producto['total_ingresos'];
                    $tiposProductos[$tipo]['productos_diferentes']++;
                }

                // Convertir a array y ordenar por ingresos
                $tiposArray = array_values($tiposProductos);
                usort($tiposArray, function ($a, $b) {
                    return $b['total_ingresos'] <=> $a['total_ingresos'];
                });

                // Calcular porcentajes
                $totalIngresos = array_sum(array_column($tiposArray, 'total_ingresos'));
                foreach ($tiposArray as &$tipo) {
                    $tipo['porcentaje'] = $totalIngresos > 0 ? ($tipo['total_ingresos'] / $totalIngresos) * 100 : 0;
                }

                error_log("✅ Distribución por tipos obtenida: " . count($tiposArray) . " tipos");
                return array_slice($tiposArray, 0, 6); // Top 6 tipos para el gráfico
            }

            error_log("❌ Error en respuesta para distribución tipos: " . ($response['error'] ?? 'Respuesta inválida'));
            return [];
        } catch (Exception $e) {
            error_log("❌ Excepción en obtenerDistribucionTipoProducto: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Prepara todos los datos necesarios para el PDF
     */
    public function prepararDatosCompletos($params)
    {
        extract($params);

        // Configurar filtros
        $filtros = [];
        if (!empty($cliente)) $filtros['cliente'] = $cliente;
        if (!empty($vendedor)) $filtros['vendedor'] = $vendedor;
        if (!empty($estado)) $filtros['estado'] = $estado;

        $_GET = array_merge($_GET, [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'cliente' => $cliente,
            'vendedor' => $vendedor,
            'estado' => $estado
        ]);

        // Obtener datos base
        $datos = [
            'ventasDetalladas' => $this->obtenerDatosParaPDF('ventas_detalladas', $filtros),
            'metricas' => $this->obtenerDatosParaPDF('datos_dashboard', $filtros),
            'vendedores' => $this->controller->obtenerDatosFiltros()['vendedores'] ?? []
        ];

        // Datos opcionales según configuración
        if ($incluirGraficos) {
            $datos['monedas'] = $this->obtenerDatosParaPDF('distribucion_por_moneda', $filtros);
            $datos['tiposProductos'] = $this->obtenerDistribucionTipoProducto($filtros);
            $datos['kilosVendedor'] = $this->obtenerDatosParaPDF('distribucion_kilos_vendedor', $filtros); // NUEVO
            $datos['creditoContado'] = $this->obtenerDatosParaPDF('distribucion_credito_contado', $filtros); // NUEVO
        } else {
            $datos['monedas'] = [];
            $datos['tiposProductos'] = [];
            $datos['kilosVendedor'] = []; // NUEVO
            $datos['creditoContado'] = []; // NUEVO
        }

        if ($incluirProductos) {
            $datos['productos'] = $this->obtenerDatosParaPDF('productos_mas_vendidos', $filtros);
        } else {
            $datos['productos'] = [];
        }

        return $datos;
    }

    /**
     * Obtiene nombre del vendedor por ID
     */
    public function obtenerNombreVendedorPorId($vendedorId, $datosVendedores)
    {
        if (empty($vendedorId) || empty($datosVendedores)) {
            return 'Todos';
        }

        foreach ($datosVendedores as $vendedor) {
            if ($vendedor['id'] == $vendedorId) {
                return $vendedor['nombre'];
            }
        }

        return 'Vendedor ID: ' . $vendedorId;
    }
}
