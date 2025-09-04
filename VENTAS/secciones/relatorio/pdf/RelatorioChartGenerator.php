<?php

/**
 * Generador de gráficos SVG para relatorios
 */
class RelatorioChartGenerator
{
    // Colores para los gráficos
    private $coloresMonedas = [
        'USD' => '#1f8600',
        'PYG' => '#b90404',
        'BRL' => '#ffee00',
        'EUR' => '#95a5a6',
        'ARS' => '#bdc3c7'
    ];

    private $coloresSectores = [
        '#2c3e50',
        '#e74c3c',
        '#f39c12',
        '#27ae60',
        '#8e44ad',
        '#34495e',
        '#16a085',
        '#d35400'
    ];

    /**
     * Genera gráfico de distribución por monedas
     */
    public function generarGraficoMonedasSVG($datos, $ancho = 300, $alto = 280)
    {
        if (empty($datos)) {
            return '<div style="text-align: center; padding: 40px; color: #7f8c8d;">Sin datos de monedas disponibles</div>';
        }

        $centro = $ancho / 2;
        $radio = min($centro - 60, 90);

        $svg = '<svg width="' . $ancho . '" height="' . $alto . '" xmlns="http://www.w3.org/2000/svg">';
        $svg .= '<rect width="100%" height="100%" fill="white" stroke="#ecf0f1" stroke-width="1"/>';

        // Verificar si hay solo un elemento con 100%
        if (count($datos) == 1 && floatval($datos[0]['porcentaje'] ?? 0) >= 99.9) {
            $svg .= $this->generarCirculoCompleto($datos[0], $centro, $radio);
        } else {
            $svg .= $this->generarSectoresMoneda($datos, $centro, $radio);
        }

        $svg .= $this->generarLeyendaMonedas($datos, $ancho, $alto);
        $svg .= '</svg>';

        return $svg;
    }

    /**
     * Genera gráfico de distribución por sectores/tipos de productos
     */
    public function generarGraficoSectoresTipoProducto($datos, $ancho = 300, $alto = 280)
    {
        if (empty($datos)) {
            return '<div style="text-align: center; padding: 40px; color: #7f8c8d;">Sin datos de tipos de productos disponibles</div>';
        }

        $centro = $ancho / 2;
        $radio = min($centro - 60, 90);

        $svg = '<svg width="' . $ancho . '" height="' . $alto . '" xmlns="http://www.w3.org/2000/svg">';
        $svg .= '<rect width="100%" height="100%" fill="white" stroke="#ecf0f1" stroke-width="1"/>';

        // ✅ VERIFICAR SI HAY SOLO UN ELEMENTO CON 100%
        if (count($datos) == 1 && floatval($datos[0]['porcentaje'] ?? 0) >= 99.9) {
            $tipo = $datos[0]['tipo'] ?? 'Sin tipo';
            $color = $this->coloresSectores[0];

            $svg .= '<circle cx="' . $centro . '" cy="' . $centro . '" r="' . $radio . '" fill="' . $color . '" stroke="white" stroke-width="2"/>';

            // Etiqueta central
            $tipoCorto = substr($tipo, 0, 12);
            $svg .= '<text x="' . $centro . '" y="' . ($centro - 5) . '" text-anchor="middle" font-family="Arial" font-size="11" fill="white" stroke="black" stroke-width="0.5" font-weight="bold">';
            $svg .= htmlspecialchars($tipoCorto) . '</text>';
            $svg .= '<text x="' . $centro . '" y="' . ($centro + 10) . '" text-anchor="middle" font-family="Arial" font-size="12" fill="white" stroke="black" stroke-width="0.5" font-weight="bold">';
            $svg .= '100%</text>';
        } else {
            // ✅ MÚLTIPLES SECTORES (código original)
            $anguloInicio = 0;

            foreach ($datos as $index => $item) {
                $porcentaje = floatval($item['porcentaje'] ?? 0);
                $angulo = ($porcentaje / 100) * 360;

                if ($angulo > 1) {
                    $svg .= $this->generarSectorTipoProducto($item, $index, $centro, $radio, $anguloInicio, $angulo);
                }

                $anguloInicio += $angulo;
            }
        }

        $svg .= $this->generarLeyendaTiposProductos($datos, $ancho, $alto);
        $svg .= '</svg>';

        return $svg;
    }

    /**
     * Genera gráfico de kilos por vendedor (MODIFICADO: muestra kilos)
     */
    public function generarGraficoKilosPorVendedor($datos, $ancho = 300, $alto = 280)
    {
        if (empty($datos)) {
            return '<div style="text-align: center; padding: 40px; color: #7f8c8d;">Sin datos de kilos por vendedor disponibles</div>';
        }

        $centro = $ancho / 2;
        $radio = min($centro - 60, 90);

        $svg = '<svg width="' . $ancho . '" height="' . $alto . '" xmlns="http://www.w3.org/2000/svg">';
        $svg .= '<rect width="100%" height="100%" fill="white" stroke="#ecf0f1" stroke-width="1"/>';

        // ✅ VERIFICAR SI HAY SOLO UN VENDEDOR CON 100%
        if (count($datos) == 1 && floatval($datos[0]['porcentaje'] ?? 0) >= 99.9) {
            $vendedor = $datos[0]['vendedor'] ?? 'Sin vendedor';
            $kilos = floatval($datos[0]['kilos_total'] ?? 0);
            $color = $this->coloresSectores[0];

            $svg .= '<circle cx="' . $centro . '" cy="' . $centro . '" r="' . $radio . '" fill="' . $color . '" stroke="white" stroke-width="2"/>';

            // Etiqueta central con vendedor y kilos
            $vendedorCorto = substr($vendedor, 0, 10);
            $kilosTexto = $this->formatearKilosCompacto($kilos);

            $svg .= '<text x="' . $centro . '" y="' . ($centro - 8) . '" text-anchor="middle" font-family="Arial" font-size="10" fill="white" stroke="black" stroke-width="0.5" font-weight="bold">';
            $svg .= htmlspecialchars($vendedorCorto) . '</text>';
            $svg .= '<text x="' . $centro . '" y="' . ($centro + 3) . '" text-anchor="middle" font-family="Arial" font-size="12" fill="white" stroke="black" stroke-width="0.5" font-weight="bold">';
            $svg .= $kilosTexto . '</text>';
            $svg .= '<text x="' . $centro . '" y="' . ($centro + 15) . '" text-anchor="middle" font-family="Arial" font-size="11" fill="white" stroke="black" stroke-width="0.5" font-weight="bold">';
            $svg .= '100%</text>';
        } else {
            // ✅ MÚLTIPLES VENDEDORES (código original)
            $anguloInicio = 0;

            foreach ($datos as $index => $item) {
                $porcentaje = floatval($item['porcentaje'] ?? 0);
                $kilos = floatval($item['kilos_total'] ?? 0);
                $angulo = ($porcentaje / 100) * 360;

                if ($angulo > 1) {
                    $color = $this->coloresSectores[$index % count($this->coloresSectores)];
                    $svg .= $this->generarSector($centro, $radio, $anguloInicio, $angulo, $color);

                    // Etiqueta mejorada: Mostrar kilos Y porcentaje
                    if ($porcentaje >= 10) {
                        $anguloMedio = deg2rad($anguloInicio + ($angulo / 2));
                        $xTexto = $centro + ($radio - 15) * cos($anguloMedio);
                        $yTexto = $centro + ($radio - 15) * sin($anguloMedio);

                        // Formatear kilos de forma compacta
                        $kilosTexto = $this->formatearKilosCompacto($kilos);

                        $svg .= '<text x="' . $xTexto . '" y="' . ($yTexto - 5) . '" text-anchor="middle" font-family="Arial" font-size="8" fill="white" stroke="black" stroke-width="0.3" font-weight="bold">';
                        $svg .= $kilosTexto . '</text>';
                        $svg .= '<text x="' . $xTexto . '" y="' . ($yTexto + 5) . '" text-anchor="middle" font-family="Arial" font-size="7" fill="white" stroke="black" stroke-width="0.3" font-weight="bold">';
                        $svg .= round($porcentaje, 1) . '%</text>';
                    }
                }

                $anguloInicio += $angulo;
            }
        }

        $svg .= $this->generarLeyendaKilosVendedor($datos, $ancho, $alto);
        $svg .= '</svg>';

        return $svg;
    }

    /**
     * ✅ NUEVO MÉTODO: Formatear kilos de forma compacta para gráficos
     */
    private function formatearKilosCompacto($kilos)
    {
        if (!is_numeric($kilos) || $kilos <= 0) return '0kg';

        if ($kilos >= 1000) {
            return number_format($kilos / 1000, 1) . 't';
        } elseif ($kilos >= 1) {
            return number_format($kilos, 0) . 'kg';
        } else {
            return number_format($kilos * 1000, 0) . 'g';
        }
    }

    /**
     * Genera gráfico de crédito vs contado
     */
    public function generarGraficoCreditoContado($datos, $ancho = 300, $alto = 280)
    {
        if (empty($datos)) {
            return '<div style="text-align: center; padding: 40px; color: #7f8c8d;">Sin datos de crédito/contado disponibles</div>';
        }

        $centro = $ancho / 2;
        $radio = min($centro - 60, 90);

        $coloresCredito = [
            'Crédito' => '#e74c3c',  // Rojo
            'Contado' => '#27ae60'   // Verde
        ];

        $svg = '<svg width="' . $ancho . '" height="' . $alto . '" xmlns="http://www.w3.org/2000/svg">';
        $svg .= '<rect width="100%" height="100%" fill="white" stroke="#ecf0f1" stroke-width="1"/>';

        // Verificar si hay solo un tipo con 100%
        if (count($datos) == 1 && floatval($datos[0]['porcentaje'] ?? 0) >= 99.9) {
            $tipo = $datos[0]['tipo'] ?? 'Sin tipo';
            $color = $coloresCredito[$tipo] ?? '#95a5a6';

            $svg .= '<circle cx="' . $centro . '" cy="' . $centro . '" r="' . $radio . '" fill="' . $color . '" stroke="white" stroke-width="2"/>';
            $svg .= '<text x="' . $centro . '" y="' . ($centro - 5) . '" text-anchor="middle" font-family="Arial" font-size="14" fill="white" stroke="black" stroke-width="0.5" font-weight="bold">';
            $svg .= $tipo . '</text>';
            $svg .= '<text x="' . $centro . '" y="' . ($centro + 10) . '" text-anchor="middle" font-family="Arial" font-size="12" fill="white" stroke="black" stroke-width="0.5" font-weight="bold">';
            $svg .= '100%</text>';
        } else {
            $anguloInicio = 0;

            foreach ($datos as $item) {
                $tipo = $item['tipo'] ?? 'Sin tipo';
                $porcentaje = floatval($item['porcentaje'] ?? 0);
                $angulo = ($porcentaje / 100) * 360;

                if ($angulo >= 359.9) {
                    $angulo = 359.9;
                }

                if ($angulo > 1) {
                    $color = $coloresCredito[$tipo] ?? '#95a5a6';
                    $svg .= $this->generarSector($centro, $radio, $anguloInicio, $angulo, $color);

                    // Etiqueta (solo si es mayor a 5%)
                    if ($porcentaje >= 10) {
                        $svg .= $this->generarEtiquetaSector($centro, $radio, $anguloInicio, $angulo, $tipo, $porcentaje);
                    }
                }

                $anguloInicio += $angulo;
            }
        }

        $svg .= $this->generarLeyendaCreditoContado($datos, $ancho, $alto, $coloresCredito);
        $svg .= '</svg>';

        return $svg;
    }

    /**
     * Genera leyenda para gráfico de kilos por vendedor (MEJORADA: todo en una línea)
     */
    private function generarLeyendaKilosVendedor($datos, $ancho, $alto)
    {
        $svg = '';
        $yInicio = $alto - 33;
        $columnas = 2;
        $itemsPorColumna = ceil(count($datos) / $columnas);

        foreach ($datos as $index => $item) {
            if ($index >= 6) break; // Máximo 6 items

            $columna = intval($index / $itemsPorColumna);
            $fila = $index % $itemsPorColumna;

            $x = 5 + ($columna * 100);
            $y = $yInicio + ($fila * 12);

            $color = $this->coloresSectores[$index % count($this->coloresSectores)];
            $vendedor = substr($item['vendedor'], 0, 10); // ✅ Más corto
            $porcentaje = round($item['porcentaje'], 1);
            $kilosFormateado = $item['kilos_formateado'] ?? $this->formatearKilosCompacto($item['kilos_total']);

            $svg .= '<rect x="' . $x . '" y="' . ($y - 4) . '" width="8" height="8" fill="' . $color . '"/>';

            // ✅ TODO EN UNA LÍNEA: Vendedor (%) - kilos
            $svg .= '<text x="' . ($x + 12) . '" y="' . ($y + 3) . '" font-family="Arial" font-size="6" fill="#2c3e50" font-weight="bold">';
            $svg .= htmlspecialchars($vendedor) . ' (' . $porcentaje . '%) - ' . $kilosFormateado . '</text>';
        }

        return $svg;
    }

    /**
     * Genera leyenda para gráfico de crédito/contado
     */
    private function generarLeyendaCreditoContado($datos, $ancho, $alto, $colores)
    {
        $svg = '';
        $yLeyenda = $alto - 20;
        $xInicio = 10;

        foreach ($datos as $index => $item) {
            $tipo = $item['tipo'] ?? 'Sin tipo';
            $porcentaje = floatval($item['porcentaje'] ?? 0);
            $total = floatval($item['total_ventas'] ?? 0);
            $color = $colores[$tipo] ?? '#95a5a6';

            $x = $xInicio + ($index * 90);

            $svg .= '<rect x="' . $x . '" y="' . ($yLeyenda - 5) . '" width="8" height="8" fill="' . $color . '"/>';
            $svg .= '<text x="' . ($x + 12) . '" y="' . ($yLeyenda + 2) . '" font-family="Arial" font-size="8" fill="#2c3e50">';
            $svg .= $tipo . ' (' . round($porcentaje, 1) . '%)</text>';
            $svg .= '<text x="' . ($x + 12) . '" y="' . ($yLeyenda + 12) . '" font-family="Arial" font-size="7" fill="#2c3e50" font-weight="bold">';
            $svg .= '$' . number_format($total, 0) . '</text>';
        }

        return $svg;
    }

    /**
     * Genera un círculo completo para un solo elemento
     */
    private function generarCirculoCompleto($item, $centro, $radio)
    {
        $moneda = $item['moneda_original'] ?? 'USD';
        $color = $this->coloresMonedas[$moneda] ?? '#95a5a6';

        $svg = '<circle cx="' . $centro . '" cy="' . $centro . '" r="' . $radio . '" fill="' . $color . '" stroke="white" stroke-width="2"/>';

        // Etiqueta central
        $svg .= '<text x="' . $centro . '" y="' . ($centro - 5) . '" text-anchor="middle" font-family="Arial" font-size="14" fill="white" stroke="black" stroke-width="0.5" font-weight="bold">';
        $svg .= $moneda . '</text>';
        $svg .= '<text x="' . $centro . '" y="' . ($centro + 10) . '" text-anchor="middle" font-family="Arial" font-size="12" fill="white" stroke="black" stroke-width="0.5" font-weight="bold">';
        $svg .= '100%</text>';

        return $svg;
    }

    /**
     * Genera sectores múltiples para monedas
     */
    private function generarSectoresMoneda($datos, $centro, $radio)
    {
        $svg = '';
        $anguloInicio = 0;

        foreach ($datos as $item) {
            $moneda = $item['moneda_original'] ?? 'USD';
            $porcentaje = floatval($item['porcentaje'] ?? 0);
            $angulo = ($porcentaje / 100) * 360;

            // Evitar ángulos de 360° exactos
            if ($angulo >= 359.9) {
                $angulo = 359.9;
            }

            if ($angulo > 1) {
                $color = $this->coloresMonedas[$moneda] ?? '#95a5a6';
                $svg .= $this->generarSector($centro, $radio, $anguloInicio, $angulo, $color);

                // Etiqueta (solo si es mayor a 5%)
                if ($porcentaje >= 10) {
                    $svg .= $this->generarEtiquetaSector($centro, $radio, $anguloInicio, $angulo, $moneda, $porcentaje);
                }
            }

            $anguloInicio += $angulo;
        }

        return $svg;
    }

    /**
     * Genera un sector para tipos de productos
     */
    private function generarSectorTipoProducto($item, $index, $centro, $radio, $anguloInicio, $angulo)
    {
        $color = $this->coloresSectores[$index % count($this->coloresSectores)];
        $svg = $this->generarSector($centro, $radio, $anguloInicio, $angulo, $color);

        // Etiqueta (solo si es mayor a 5%)
        $porcentaje = floatval($item['porcentaje'] ?? 0);
        if ($porcentaje >= 5) {
            $anguloMedio = deg2rad($anguloInicio + ($angulo / 2));
            $xTexto = $centro + ($radio - 15) * cos($anguloMedio);
            $yTexto = $centro + ($radio - 15) * sin($anguloMedio);

            $svg .= '<text x="' . $xTexto . '" y="' . $yTexto . '" text-anchor="middle" font-family="Arial" font-size="8" fill="white" stroke="black" stroke-width="0.3" font-weight="bold">';
            $svg .= round($porcentaje, 1) . '%</text>';
        }

        return $svg;
    }

    /**
     * Genera un sector genérico
     */
    private function generarSector($centro, $radio, $anguloInicio, $angulo, $color)
    {
        $anguloInicioRad = deg2rad($anguloInicio);
        $anguloFinRad = deg2rad($anguloInicio + $angulo);

        $x1 = $centro + $radio * cos($anguloInicioRad);
        $y1 = $centro + $radio * sin($anguloInicioRad);
        $x2 = $centro + $radio * cos($anguloFinRad);
        $y2 = $centro + $radio * sin($anguloFinRad);

        $largeArc = $angulo > 180 ? 1 : 0;
        $path = "M $centro $centro L $x1 $y1 A $radio $radio 0 $largeArc 1 $x2 $y2 Z";

        return '<path d="' . $path . '" fill="' . $color . '" stroke="white" stroke-width="2"/>';
    }

    /**
     * Genera etiqueta para sector de moneda
     */
    private function generarEtiquetaSector($centro, $radio, $anguloInicio, $angulo, $moneda, $porcentaje)
    {
        $anguloMedio = deg2rad($anguloInicio + ($angulo / 2));
        $xTexto = $centro + ($radio - 15) * cos($anguloMedio);
        $yTexto = $centro + ($radio - 15) * sin($anguloMedio);

        $svg = '<text x="' . $xTexto . '" y="' . $yTexto . '" text-anchor="middle" font-family="Arial" font-size="9" fill="white" stroke="black" stroke-width="0.3" font-weight="bold">';
        $svg .= $moneda . '<tspan x="' . $xTexto . '" dy="10" font-size="8">' . round($porcentaje, 1) . '%</tspan></text>';

        return $svg;
    }

    /**
     * Genera leyenda para gráfico de monedas
     */
    private function generarLeyendaMonedas($datos, $ancho, $alto)
    {
        $svg = '';
        $yLeyenda = $alto - 20;
        $xInicio = 5;

        foreach ($datos as $index => $item) {
            if ($index > 2) break;

            $moneda = $item['moneda_original'] ?? 'USD';
            $porcentaje = floatval($item['porcentaje'] ?? 0);
            $total = floatval($item['total_original'] ?? 0);
            $color = $this->coloresMonedas[$moneda] ?? '#95a5a6';

            $x = $xInicio + ($index * 60);

            $svg .= '<rect x="' . $x . '" y="' . ($yLeyenda - 5) . '" width="8" height="8" fill="' . $color . '"/>';
            $svg .= '<text x="' . ($x + 12) . '" y="' . ($yLeyenda + 2) . '" font-family="Arial" font-size="7" fill="#2c3e50">';
            $svg .= $moneda . ' (' . round($porcentaje, 1) . '%)</text>';
            $svg .= '<text x="' . ($x + 12) . '" y="' . ($yLeyenda + 12) . '" font-family="Arial" font-size="7" fill="#2c3e50" font-weight="bold">';
            $svg .= number_format($total, 0) . ' ' . $moneda . '</text>';
        }

        return $svg;
    }

    /**
     * Genera leyenda para gráfico de tipos de productos
     */
    private function generarLeyendaTiposProductos($datos, $ancho, $alto)
    {
        $svg = '';
        $yInicio = $alto - 33;
        $columnas = 2;
        $itemsPorColumna = ceil(count($datos) / $columnas);

        foreach ($datos as $index => $item) {
            if ($index >= 6) break; // Máximo 6 items en leyenda

            $columna = intval($index / $itemsPorColumna);
            $fila = $index % $itemsPorColumna;

            $x = 5 + ($columna * 80);
            $y = $yInicio + ($fila * 12);

            $color = $this->coloresSectores[$index % count($this->coloresSectores)];
            $tipo = substr($item['tipo'], 0, 16);
            $porcentaje = round($item['porcentaje'], 1);

            $svg .= '<rect x="' . $x . '" y="' . ($y - 4) . '" width="8" height="8" fill="' . $color . '"/>';
            $svg .= '<text x="' . ($x + 12) . '" y="' . ($y + 3) . '" font-family="Arial" font-size="7" fill="#2c3e50" font-weight="bold">';
            $svg .= htmlspecialchars($tipo) . ' (' . $porcentaje . '%)</text>';
        }

        return $svg;
    }
}
