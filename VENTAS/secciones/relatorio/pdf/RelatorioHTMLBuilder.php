<?php

/**
 * Constructor de HTML para relatorios
 */
class RelatorioHTMLBuilder
{
    private $chartGenerator;
    private $dataService;

    public function __construct($chartGenerator, $dataService)
    {
        $this->chartGenerator = $chartGenerator;
        $this->dataService = $dataService;
    }

    /**
     * Construye el HTML completo del relatorio con orientación dinámica
     */
    public function construirHTMLCompleto($params, $empresa, $usuario, $fechaGeneracion, $datos)
    {
        extract($params);
        extract($datos);

        $periodoFormateado = date('d/m/Y', strtotime($fechaInicio)) . ' - ' . date('d/m/Y', strtotime($fechaFin));
        $esHorizontal = ($formatoPapel === 'A4_horizontal');
        $esLetter = ($formatoPapel === 'Letter');

        $html = $this->generarCabeceraHTML($periodoFormateado, $esHorizontal, $esLetter);
        $html .= $this->generarHeaderEmpresa($empresa, $periodoFormateado, $usuario, $fechaGeneracion);
        $html .= $this->generarSeccionFiltros($params, $vendedores, $esHorizontal);

        if ($incluirTotales) {
            $html .= $this->generarSeccionMetricas($ventasDetalladas, $metricas, $esHorizontal);
        }

        if ($incluirGraficos && (!empty($monedas) || !empty($tiposProductos) || !empty($kilosVendedor) || !empty($creditoContado))) {
            $html .= $this->generarSeccionGraficos($monedas, $tiposProductos, $esHorizontal, $kilosVendedor, $creditoContado);
        }

        if ($agruparPorCliente) {
            $html .= $this->generarTablaVentasAgrupadasPorCliente($ventasDetalladas, $esHorizontal, $incluirGraficos);
        } elseif ($agruparPorVendedor) {
            $html .= $this->generarTablaVentasAgrupadasPorVendedor($ventasDetalladas, $esHorizontal, $incluirGraficos);
        } else {
            $html .= $this->generarTablaVentas($ventasDetalladas, $esHorizontal, $incluirGraficos);
        }

        if ($incluirProductos && !empty($productos)) {
            if ($agruparProductos) {
                $html .= $this->generarTablaProductosAgrupados($productos, $esHorizontal);
            } else {
                $html .= $this->generarTablaProductos($productos, $esHorizontal);
            }
        }
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Genera la cabecera HTML con CSS dinámico
     */
    private function generarCabeceraHTML($periodoFormateado, $esHorizontal, $esLetter)
    {
        $cssOrientacion = $this->obtenerCSSOrientacion($esHorizontal, $esLetter);
        $fontSizes = $this->obtenerTamanosFuente($esHorizontal);

        return '<!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Relatorio de Ventas - ' . $periodoFormateado . '</title>
            <style>' . $this->generarCSS($cssOrientacion, $fontSizes, $esHorizontal) . '</style>
        </head>
        <body>';
    }

    /**
     * Obtiene la configuración CSS según orientación
     */
    private function obtenerCSSOrientacion($esHorizontal, $esLetter)
    {
        if ($esHorizontal) {
            return 'size: A4 landscape;';
        } elseif ($esLetter) {
            return 'size: Letter;';
        } else {
            return 'size: A4 portrait;';
        }
    }

    /**
     * Obtiene tamaños de fuente según orientación
     */
    private function obtenerTamanosFuente($esHorizontal)
    {
        return [
            'body' => $esHorizontal ? '8pt' : '9pt',
            'company_name' => $esHorizontal ? '14pt' : '16pt',
            'company_info' => $esHorizontal ? '6pt' : '7pt',
            'report_title' => $esHorizontal ? '12pt' : '14pt',
            'report_period' => $esHorizontal ? '9pt' : '10pt',
            'section_title' => $esHorizontal ? '9pt' : '10pt',
            'metric_value' => $esHorizontal ? '12pt' : '14pt',
            'table_text' => $esHorizontal ? '7pt' : '8pt'
        ];
    }

    /**
     * Genera todo el CSS del documento
     */
    private function generarCSS($cssOrientacion, $fontSizes, $esHorizontal)
    {
        $anchoMetrics = $esHorizontal ? 5 : 5;
        $anchoFiltros = $esHorizontal ? 4 : 4;
        $columnasPadding = $esHorizontal ? '6px' : '8px';
        $sectionMargin = $esHorizontal ? '8px 0 6px 0' : '12px 0 8px 0';

        return "
            @page {
                margin: 10mm;
                {$cssOrientacion}
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body { 
                font-family: 'Segoe UI', Arial, sans-serif;
                font-size: {$fontSizes['body']};
                line-height: 1.2;
                color: #1a1a1a;
                background: white;
                padding: 5px;
            }
            
            .header {
                text-align: center;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #0a0a0a;
            }
            
            .company-name {
                font-size: {$fontSizes['company_name']};
                font-weight: bold;
                color: #1a1a1a;
                margin-bottom: 5px;
                letter-spacing: 0.3px;
            }
            
            .company-info {
                font-size: {$fontSizes['company_info']};
                color: #000000;
                margin-bottom: 2px;
            }
            
            .report-title {
                font-size: {$fontSizes['report_title']};
                font-weight: bold;
                color: #000000;
                margin: 8px 0 5px 0;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .report-period {
                font-size: {$fontSizes['report_period']};
                color: #1a1a1a;
                font-weight: 600;
            }
            
            .report-meta {
                font-size: {$fontSizes['company_info']};
                color: #000000;
                margin-top: 5px;
            }
            
            .section-title {
                background: #000000 !important;
                background-color: #000000 !important;
                color: white !important;
                padding: {$columnasPadding} 10px;
                font-weight: bold;
                font-size: {$fontSizes['section_title']};
                margin: {$sectionMargin};
                text-transform: uppercase;
                letter-spacing: 0.3px;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .filters-info {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 3px;
                padding: {$columnasPadding};
                margin-bottom: 12px;
                font-size: {$fontSizes['table_text']};
            }
            
            .filters-grid {
                display: grid;
                grid-template-columns: repeat({$anchoFiltros}, 1fr);
                gap: 8px;
            }
            
            .filter-item {
                text-align: center;
            }
            
            .filter-label {
                font-weight: bold;
                color: #1a1a1a;
                font-size: {$fontSizes['company_info']};
                text-transform: uppercase;
                margin-bottom: 2px;
            }
            
            .filter-value {
                color: #000000;
                font-weight: 600;
                font-size: {$fontSizes['table_text']};
            }
            
            .metrics-grid {
                display: grid;
                grid-template-columns: repeat({$anchoMetrics}, 1fr);
                gap: 6px;
                margin: 10px 0;
            }
            
            .metric-card {
                background: white;
                border: 1px solid #dee2e6;
                border-radius: 3px;
                padding: {$columnasPadding};
                text-align: center;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            
            .metric-value {
                font-size: {$fontSizes['metric_value']};
                font-weight: bold;
                color: #1a1a1a;
                display: block;
                margin-bottom: 3px;
            }
            
            .metric-label {
                font-size: {$fontSizes['company_info']};
                color: #000000;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
            
            /* ✅ CSS MEJORADO PARA GRÁFICOS */
            .charts-container {
                display: grid;
                gap: " . ($esHorizontal ? '15px' : '20px') . ";
                margin: " . ($esHorizontal ? '15px 0' : '20px 0') . ";
            }
            
            .charts-container.horizontal {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: 1fr 1fr;
            }
            
            .charts-container.vertical {
                grid-template-columns: 1fr 1fr;
            }
            
            .chart-box {
                background: white;
                border: 1px solid #000000;
                border-radius: 6px;
                padding: " . ($esHorizontal ? '10px' : '15px') . ";
                text-align: center;
                min-height: " . ($esHorizontal ? '200px' : '300px') . ";
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }
            
            .chart-title {
                font-weight: bold;
                font-size: " . ($esHorizontal ? '9pt' : '11pt') . ";
                color: #2c3e50;
                margin-bottom: " . ($esHorizontal ? '8px' : '12px') . ";
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border-bottom: 1px solid #ecf0f1;
                padding-bottom: " . ($esHorizontal ? '6px' : '8px') . ";
            }
            
            .chart-content {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .table-container {
                margin-top: 20px;
                overflow: hidden;
            }
            
            .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: {$fontSizes['table_text']};
            border: 1px solid #000000;
            box-decoration-break: clone;
            -webkit-box-decoration-break: clone;
        }
            
            .table th {
                background: #000000 !important;
                background-color: #000000 !important;
                color: white !important;
                font-weight: bold;
                text-align: center;
                padding: {$columnasPadding} 6px;
                font-size: {$fontSizes['table_text']};
                text-transform: uppercase;
                letter-spacing: 0.3px;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .table td {
                padding: {$columnasPadding} 6px;
                vertical-align: middle;
                font-size: {$fontSizes['table_text']};
            }
            
            .table tbody tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .text-left { text-align: left; }
            
            .currency {
                color: #27ae60;
                font-weight: bold;
            }
            
            .page-break {
                page-break-before: always;
            }
        ";
    }

    /**
     * Genera el header de la empresa
     */
    private function generarHeaderEmpresa($empresa, $periodoFormateado, $usuario, $fechaGeneracion)
    {
        return '
        <div class="header">
            <div class="company-name">' . $empresa['nombre'] . '</div>
            <div class="company-info">' . $empresa['direccion'] . '</div>
            <div class="company-info">Email: ' . $empresa['email'] . ' | RUC: ' . $empresa['ruc'] . '</div>
            <div class="report-title">Relatorio de Ventas</div>
            <div class="report-period">Período: ' . $periodoFormateado . '</div>
            <div class="report-meta">Generado por: ' . $usuario . ' | Fecha: ' . $fechaGeneracion . '</div>
        </div>';
    }

    /**
     * Genera la sección de filtros aplicados
     */
    private function generarSeccionFiltros($params, $vendedores, $esHorizontal)
    {
        extract($params);
        $periodoFormateado = date('d/m/Y', strtotime($fechaInicio)) . ' - ' . date('d/m/Y', strtotime($fechaFin));

        $html = '<div class="section-title">Filtros Aplicados</div>';
        $html .= '<div class="filters-info"><div class="filters-grid">';

        $html .= '<div class="filter-item">
        <div class="filter-label">Período</div>
        <div class="filter-value">' . $periodoFormateado . '</div>
    </div>';

        $html .= '<div class="filter-item">
        <div class="filter-label">Cliente</div>
        <div class="filter-value">' . ($cliente ?: 'Todos') . '</div>
    </div>';

        $html .= '<div class="filter-item">
        <div class="filter-label">Vendedor</div>
        <div class="filter-value">' . $this->dataService->obtenerNombreVendedorPorId($vendedor, $vendedores) . '</div>
    </div>';

        $html .= '<div class="filter-item">
        <div class="filter-label">Estado</div>
        <div class="filter-value">' . ($estado ?: 'Todos') . '</div>
    </div>';

        $html .= '</div></div>';
        return $html;
    }

    /**
     * Genera la sección de métricas
     */
    private function generarSeccionMetricas($ventasDetalladas, $metricas, $esHorizontal)
    {
        $totalVentas = count($ventasDetalladas);
        $montoTotal = array_sum(array_column($ventasDetalladas, 'monto_total'));
        $clientesUnicos = count(array_unique(array_column($ventasDetalladas, 'cliente')));
        $ticketPromedio = $totalVentas > 0 ? $montoTotal / $totalVentas : 0;

        $pesoTotal = isset($metricas['peso_total_kg']) ? (float)$metricas['peso_total_kg'] : 0;
        $pesoFormateado = $pesoTotal >= 1000 ?
            number_format($pesoTotal / 1000, 1) . ' ton' :
            number_format($pesoTotal, 0) . ' kg';

        $html = '<div class="section-title">Resumen Ejecutivo</div>';
        $html .= '<div class="metrics-grid">';

        $html .= '<div class="metric-card">
            <span class="metric-value">' . number_format($totalVentas) . '</span>
            <div class="metric-label">Total Ventas</div>
        </div>';

        $html .= '<div class="metric-card">
            <span class="metric-value currency">$' . number_format($montoTotal, 0) . '</span>
            <div class="metric-label">Monto Total (USD)</div>
        </div>';

        $html .= '<div class="metric-card">
            <span class="metric-value currency">$' . number_format($ticketPromedio, 0) . '</span>
            <div class="metric-label">Ticket Promedio</div>
        </div>';

        $html .= '<div class="metric-card">
            <span class="metric-value">' . number_format($clientesUnicos) . '</span>
            <div class="metric-label">Clientes Únicos</div>
        </div>';

        $html .= '<div class="metric-card" style="background: linear-gradient(135deg, #e8f5e8, #f0f8f0); border-color: #27ae60;">
            <span class="metric-value" style="color: #27ae60;">' . $pesoFormateado . '</span>
            <div class="metric-label">Peso Total</div>
        </div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * ✅ MÉTODO COMPLETAMENTE REESCRITO PARA GRÁFICOS MEJORADOS
     */
    private function generarSeccionGraficos($monedas, $tiposProductos, $esHorizontal, $kilosVendedor = [], $creditoContado = [])
    {
        // ✅ TAMAÑOS OPTIMIZADOS PARA CADA ORIENTACIÓN
        if ($esHorizontal) {
            $tamanoGrafico = 205;  // ✅ MÁS GRANDE en horizontal
            $altoGrafico = 185;    // ✅ MÁS ALTO también
        } else {
            $tamanoGrafico = 280;
            $altoGrafico = 260;
        }

        $html = '<div class="section-title">Análisis Gráfico</div>';

        // ✅ ESTRUCTURA MEJORADA: 2x2 en horizontal, 2x1 en vertical
        if ($esHorizontal) {
            $html .= '<div class="charts-container horizontal">';
        } else {
            $html .= '<div class="charts-container vertical">';
        }

        // Contar gráficos disponibles
        $graficosDisponibles = [];
        if (!empty($monedas)) $graficosDisponibles[] = 'monedas';
        if (!empty($tiposProductos)) $graficosDisponibles[] = 'sectores';
        if (!empty($kilosVendedor)) $graficosDisponibles[] = 'kilos';
        if (!empty($creditoContado)) $graficosDisponibles[] = 'credito';

        // Generar gráficos en orden de prioridad
        foreach ($graficosDisponibles as $tipo) {
            $html .= '<div class="chart-box">';

            switch ($tipo) {
                case 'monedas':
                    $html .= '<div class="chart-title">Distribución por Moneda</div>';
                    $html .= '<div class="chart-content">';
                    $html .= $this->chartGenerator->generarGraficoMonedasSVG($monedas, $tamanoGrafico, $altoGrafico);
                    $html .= '</div>';
                    break;

                case 'sectores':
                    $html .= '<div class="chart-title">Distribución por Sectores</div>';
                    $html .= '<div class="chart-content">';
                    $html .= $this->chartGenerator->generarGraficoSectoresTipoProducto($tiposProductos, $tamanoGrafico, $altoGrafico);
                    $html .= '</div>';
                    break;

                case 'kilos':
                    $html .= '<div class="chart-title">Kilos por Vendedor</div>';
                    $html .= '<div class="chart-content">';
                    $html .= $this->chartGenerator->generarGraficoKilosPorVendedor($kilosVendedor, $tamanoGrafico, $altoGrafico);
                    $html .= '</div>';
                    break;

                case 'credito':
                    $html .= '<div class="chart-title">Crédito vs Contado</div>';
                    $html .= '<div class="chart-content">';
                    $html .= $this->chartGenerator->generarGraficoCreditoContado($creditoContado, $tamanoGrafico, $altoGrafico);
                    $html .= '</div>';
                    break;
            }

            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Genera la tabla de ventas
     */
    private function generarTablaVentas($ventasDetalladas, $esHorizontal, $incluirGraficos = false)
    {
        $html = '';

        // ✅ SOLO SALTAR PÁGINA SI HAY GRÁFICOS
        if ($incluirGraficos) {
            $html .= '<div class="page-break"></div>';
        }

        $html .= '<div class="section-title">Ventas Detalladas</div>';
        $html .= '<div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th ' . ($esHorizontal ? 'width="6%"' : 'width="8%"') . '>ID</th>
                    <th ' . ($esHorizontal ? 'width="8%"' : 'width="10%"') . '>Fecha</th>
                    <th ' . ($esHorizontal ? 'width="25%"' : 'width="28%"') . '>Cliente</th>
                    <th ' . ($esHorizontal ? 'width="12%"' : 'width="15%"') . '>Vendedor</th>
                    <th ' . ($esHorizontal ? 'width="8%"' : 'width="10%"') . '>Estado</th>
                    <th ' . ($esHorizontal ? 'width="10%"' : 'width="12%"') . '>Total (USD)</th>
                    <th ' . ($esHorizontal ? 'width="31%"' : 'width="17%"') . '>Tipo Pago</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($ventasDetalladas as $venta) {
            $tipoPago = ($venta['cond_pago'] ?: '') . ($venta['tipo_pago'] ? ' - ' . $venta['tipo_pago'] : '');
            $clienteTexto = $esHorizontal ?
                htmlspecialchars(substr($venta['cliente'], 0, 35)) :
                htmlspecialchars(substr($venta['cliente'], 0, 50));

            $html .= '<tr>
            <td class="text-center"><strong>#' . $venta['id'] . '</strong></td>
            <td class="text-center">' . date('d/m/Y', strtotime($venta['fecha_venta'])) . '</td>
            <td>' . $clienteTexto . '</td>
            <td class="text-center">' . htmlspecialchars($venta['nombre_vendedor']) . '</td>
            <td class="text-center">' . htmlspecialchars($venta['estado']) . '</td>
            <td class="text-right currency">$' . number_format($venta['monto_total'], 2) . '</td>
            <td class="text-center">' . htmlspecialchars(substr($tipoPago, 0, ($esHorizontal ? 70 : 50))) . '</td>
        </tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    /**
     * Genera la tabla de productos
     */
    private function generarTablaProductos($productos, $esHorizontal)
    {
        $html = '<div class="page-break"></div>';
        $html .= '<div class="section-title">Análisis de Productos</div>';
        $html .= '<div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th ' . ($esHorizontal ? 'width="35%"' : 'width="40%"') . '>Producto</th>
                        <th width="12%">Cantidad</th>
                        <th width="12%">Ventas</th>
                        <th width="18%">Ingresos (USD)</th>
                        <th width="18%">% del Total</th>
                    </tr>
                </thead>
                <tbody>';

        $totalIngresos = array_sum(array_column($productos, 'total_ingresos'));

        foreach ($productos as $producto) {
            $porcentaje = $totalIngresos > 0 ? ($producto['total_ingresos'] / $totalIngresos) * 100 : 0;

            $html .= '<tr>
                <td>' . htmlspecialchars($producto['descripcion']) . '</td>
                <td class="text-center">' . number_format($producto['cantidad_vendida'],2) . '</td>
                <td class="text-center">' . number_format($producto['ventas_asociadas']) . '</td>
                <td class="text-right currency">$' . number_format($producto['total_ingresos'], 2) . '</td>
                <td class="text-right"><strong>' . number_format($porcentaje, 1) . '%</strong></td>
            </tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    /**
     * Genera tabla de ventas agrupadas por cliente
     */
    private function generarTablaVentasAgrupadasPorCliente($ventasDetalladas, $esHorizontal, $incluirGraficos = false)
    {
        // Agrupar por cliente
        $ventasPorCliente = [];
        foreach ($ventasDetalladas as $venta) {
            $cliente = $venta['cliente'] ?: 'Sin cliente';
            if (!isset($ventasPorCliente[$cliente])) {
                $ventasPorCliente[$cliente] = [
                    'cliente' => $cliente,
                    'cantidad_ventas' => 0,
                    'monto_total' => 0,
                    'primera_venta' => $venta['fecha_venta'],
                    'ultima_venta' => $venta['fecha_venta'],
                    'vendedores' => []
                ];
            }

            $ventasPorCliente[$cliente]['cantidad_ventas']++;
            $ventasPorCliente[$cliente]['monto_total'] += $venta['monto_total'];
            $ventasPorCliente[$cliente]['ultima_venta'] = max($ventasPorCliente[$cliente]['ultima_venta'], $venta['fecha_venta']);
            $ventasPorCliente[$cliente]['vendedores'][$venta['nombre_vendedor']] = true;
        }

        // Ordenar por monto total descendente
        uasort($ventasPorCliente, function ($a, $b) {
            return $b['monto_total'] <=> $a['monto_total'];
        });

        $html = '';

        // ✅ SOLO SALTAR PÁGINA SI HAY GRÁFICOS
        if ($incluirGraficos) {
            $html .= '<div class="page-break"></div>';
        }

        $html .= '<div class="section-title">Ventas Agrupadas por Cliente</div>';
        $html .= '<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th width="35%">Cliente</th>
                <th width="12%">Ventas</th>
                <th width="15%">Total (USD)</th>
                <th width="15%">Promedio</th>
                <th width="12%">Última Venta</th>
                <th width="11%">Vendedores</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($ventasPorCliente as $grupo) {
            $promedio = $grupo['cantidad_ventas'] > 0 ? $grupo['monto_total'] / $grupo['cantidad_ventas'] : 0;
            $vendedores = count($grupo['vendedores']);

            $html .= '<tr>
        <td>' . htmlspecialchars($grupo['cliente']) . '</td>
        <td class="text-center"><strong>' . number_format($grupo['cantidad_ventas']) . '</strong></td>
        <td class="text-right currency">$' . number_format($grupo['monto_total'], 2) . '</td>
        <td class="text-right currency">$' . number_format($promedio, 2) . '</td>
        <td class="text-center">' . date('d/m/Y', strtotime($grupo['ultima_venta'])) . '</td>
        <td class="text-center">' . $vendedores . '</td>
    </tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    /**
     * Genera tabla de ventas agrupadas por vendedor
     */
    private function generarTablaVentasAgrupadasPorVendedor($ventasDetalladas, $esHorizontal, $incluirGraficos = false)
    {
        $html = '';

        // ✅ SOLO SALTAR PÁGINA SI HAY GRÁFICOS
        if ($incluirGraficos) {
            $html .= '<div class="page-break"></div>';
        }

        // Agrupar por vendedor
        $ventasPorVendedor = [];
        foreach ($ventasDetalladas as $venta) {
            $vendedor = $venta['nombre_vendedor'] ?: 'Sin asignar';
            if (!isset($ventasPorVendedor[$vendedor])) {
                $ventasPorVendedor[$vendedor] = [
                    'vendedor' => $vendedor,
                    'cantidad_ventas' => 0,
                    'monto_total' => 0,
                    'primera_venta' => $venta['fecha_venta'],
                    'ultima_venta' => $venta['fecha_venta'],
                    'clientes' => []
                ];
            }

            $ventasPorVendedor[$vendedor]['cantidad_ventas']++;
            $ventasPorVendedor[$vendedor]['monto_total'] += $venta['monto_total'];
            $ventasPorVendedor[$vendedor]['ultima_venta'] = max($ventasPorVendedor[$vendedor]['ultima_venta'], $venta['fecha_venta']);
            $ventasPorVendedor[$vendedor]['clientes'][$venta['cliente']] = true;
        }

        // Ordenar por monto total descendente
        uasort($ventasPorVendedor, function ($a, $b) {
            return $b['monto_total'] <=> $a['monto_total'];
        });

        $html .= '<div class="section-title">Ventas Agrupadas por Vendedor</div>';
        $html .= '<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th width="25%">Vendedor</th>
                <th width="12%">Ventas</th>
                <th width="15%">Total (USD)</th>
                <th width="15%">Promedio</th>
                <th width="12%">Última Venta</th>
                <th width="11%">Clientes</th>
                <th width="10%">Ranking</th>
            </tr>
        </thead>
        <tbody>';

        $ranking = 1;
        foreach ($ventasPorVendedor as $grupo) {
            $promedio = $grupo['cantidad_ventas'] > 0 ? $grupo['monto_total'] / $grupo['cantidad_ventas'] : 0;
            $clientes = count($grupo['clientes']);

            $html .= '<tr>
        <td>' . htmlspecialchars($grupo['vendedor']) . '</td>
        <td class="text-center"><strong>' . number_format($grupo['cantidad_ventas']) . '</strong></td>
        <td class="text-right currency">$' . number_format($grupo['monto_total'], 2) . '</td>
        <td class="text-right currency">$' . number_format($promedio, 2) . '</td>
        <td class="text-center">' . date('d/m/Y', strtotime($grupo['ultima_venta'])) . '</td>
        <td class="text-center">' . $clientes . '</td>
        <td class="text-center"><strong>#' . $ranking . '</strong></td>
    </tr>';
            $ranking++;
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    /**
     * Genera tabla de productos agrupados por nombre
     */
    private function generarTablaProductosAgrupados($productos, $esHorizontal)
    {
        // Agrupar productos por descripción
        $productosAgrupados = [];
        foreach ($productos as $producto) {
            $nombre = $producto['descripcion'] ?: 'Sin descripción';
            if (!isset($productosAgrupados[$nombre])) {
                $productosAgrupados[$nombre] = [
                    'descripcion' => $nombre,
                    'cantidad_vendida' => 0,
                    'total_ingresos' => 0,
                    'ventas_asociadas' => 0,
                    'precio_promedio' => 0
                ];
            }

            $productosAgrupados[$nombre]['cantidad_vendida'] += $producto['cantidad_vendida'];
            $productosAgrupados[$nombre]['total_ingresos'] += $producto['total_ingresos'];
            $productosAgrupados[$nombre]['ventas_asociadas'] += $producto['ventas_asociadas'];
        }

        // Calcular precio promedio y ordenar
        foreach ($productosAgrupados as &$grupo) {
            $grupo['precio_promedio'] = $grupo['cantidad_vendida'] > 0 ?
                $grupo['total_ingresos'] / $grupo['cantidad_vendida'] : 0;
        }

        uasort($productosAgrupados, function ($a, $b) {
            return $b['total_ingresos'] <=> $a['total_ingresos'];
        });

        $html = '<div class="section-title">Productos Agrupados por Nombre</div>';
        $html .= '<div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th width="40%">Producto</th>
                    <th width="12%">Cantidad</th>
                    <th width="12%">Ventas</th>
                    <th width="18%">Ingresos (USD)</th>
                    <th width="18%">Precio Promedio</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($productosAgrupados as $grupo) {
            $html .= '<tr>
            <td>' . htmlspecialchars($grupo['descripcion']) . '</td>
            <td class="text-center">' . number_format($grupo['cantidad_vendida'] , 2) . '</td>
            <td class="text-center">' . number_format($grupo['ventas_asociadas']) . '</td>
            <td class="text-right currency">$' . number_format($grupo['total_ingresos'], 2) . '</td>
            <td class="text-right currency">$' . number_format($grupo['precio_promedio'], 2) . '</td>
        </tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }
}
