<?php
ini_set('memory_limit', '512M');
require_once __DIR__ . "/../../../config/conexionBD.php";
include "../../../vendor/tecnickcom/tcpdf/tcpdf.php";
include "../repository/productionRepository.php";

function formatearNumero($numero, $decimales_max = 2)
{
    $formateado = number_format($numero, $decimales_max, '.', '');

    if (strpos($formateado, '.') !== false) {
        $formateado = rtrim($formateado, '0');
        $formateado = rtrim($formateado, '.');
    }

    return $formateado;
}

if (!isset($_GET['id_orden']) || empty($_GET['id_orden'])) {
    header("Location: ../ordenproduccion.php?error=ID de orden no especificado");
    exit();
}

$id_orden = (int)$_GET['id_orden'];
$id_stock_especifico = isset($_GET['id_stock']) ? (int)$_GET['id_stock'] : null;
$tipo_producto = isset($_GET['tipo']) ? strtoupper($_GET['tipo']) : 'TNT';

$esReimpresionLote = isset($_GET['lote']) && $_GET['lote'] == '1';
$itemDesde = isset($_GET['item_desde']) ? intval($_GET['item_desde']) : null;
$itemHasta = isset($_GET['item_hasta']) ? intval($_GET['item_hasta']) : null;

error_log("📦 PDF Universal Individual - Orden: {$id_orden}, Tipo: {$tipo_producto}, Modo: " .
    ($esReimpresionLote ? "LOTE ($itemDesde-$itemHasta)" : "INDIVIDUAL"));

try {
    $productionRepo = new ProductionRepositoryUniversal($conexion);
    $resultado = $productionRepo->buscarOrdenCompleta($id_orden);

    if ($resultado['error'] || empty($resultado['productos'])) {
        header("Location: ../ordenproduccion.php?error=" . urlencode("Orden {$tipo_producto} no encontrada"));
        exit();
    }

    $orden = $resultado['orden'];
    $producto = $resultado['productos'][0];

    if (strtoupper($producto['tipo']) !== $tipo_producto) {
        error_log("⚠️ Advertencia: Tipo solicitado ({$tipo_producto}) no coincide con tipo de orden ({$producto['tipo']})");
    }

    if ($esReimpresionLote) {
        if (!$itemDesde || !$itemHasta || $itemDesde > $itemHasta) {
            error_log("❌ Error: Rango de items inválido para reimpresión en lote");
            header("Location: ../ordenproduccion.php?error=" . urlencode("Rango de items inválido"));
            exit();
        }

        if (($itemHasta - $itemDesde + 1) > 100) {
            error_log("❌ Error: No se pueden imprimir más de 100 etiquetas en un lote");
            header("Location: ../ordenproduccion.php?error=" . urlencode("Máximo 100 etiquetas por lote"));
            exit();
        }

        $sql = "SELECT numero_item, id, largura as ancho, metragem as metraje, gramatura, 
                       peso_liquido as peso, fecha_hora_producida, tipo_producto
                FROM public.sist_prod_stock 
                WHERE id_orden_produccion = :orden_id 
                AND numero_item BETWEEN :item_desde AND :item_hasta
                ORDER BY numero_item ASC";

        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':orden_id', $id_orden, PDO::PARAM_INT);
        $stmt->bindParam(':item_desde', $itemDesde, PDO::PARAM_INT);
        $stmt->bindParam(':item_hasta', $itemHasta, PDO::PARAM_INT);
        $stmt->execute();

        $itemsLote = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($itemsLote)) {
            error_log("❌ Error: No se encontraron items en el rango especificado");
            header("Location: ../ordenproduccion.php?error=" . urlencode("No se encontraron items en el rango especificado"));
            exit();
        }

        $totalItemsEncontrados = count($itemsLote);
        error_log("📊 Items encontrados: {$totalItemsEncontrados}");
    } elseif ($id_stock_especifico) {
        // 🎯 REIMPRESIÓN ESPECÍFICA
        $query_stock = "SELECT numero_item, id, largura as ancho, metragem as metraje, gramatura, 
                        peso_liquido as peso, fecha_hora_producida, tipo_producto
                        FROM public.sist_prod_stock 
                        WHERE id = :id_stock AND id_orden_produccion = :id_orden";

        $stmt_stock = $conexion->prepare($query_stock);
        $stmt_stock->bindParam(':id_stock', $id_stock_especifico, PDO::PARAM_INT);
        $stmt_stock->bindParam(':id_orden', $id_orden, PDO::PARAM_INT);
        $stmt_stock->execute();
        $stock_data = $stmt_stock->fetch(PDO::FETCH_ASSOC);

        if (!$stock_data) {
            header("Location: ../ordenproduccion.php?error=" . urlencode("Registro de stock específico no encontrado"));
            exit();
        }

        error_log("🎯 Reimprimiendo registro específico - ID: " . $id_stock_especifico . ", Item: " . $stock_data['numero_item'] . ", Tipo: " . $stock_data['tipo_producto']);
    } else {
        $query_stock = "SELECT numero_item, id, largura as ancho, metragem as metraje, gramatura, 
                        peso_liquido as peso, fecha_hora_producida, tipo_producto
                        FROM public.sist_prod_stock 
                        WHERE id_orden_produccion = :id_orden 
                        ORDER BY fecha_hora_producida DESC
                        LIMIT 1";

        $stmt_stock = $conexion->prepare($query_stock);
        $stmt_stock->bindParam(':id_orden', $id_orden, PDO::PARAM_INT);
        $stmt_stock->execute();
        $stock_data = $stmt_stock->fetch(PDO::FETCH_ASSOC);

        if ($stock_data) {
            error_log("📦 Usando último registro - ID: " . $stock_data['id'] . ", Item: " . $stock_data['numero_item'] . ", Tipo: " . $stock_data['tipo_producto']);
        }
    }

    $descripcion_producto = $producto['descripcion'] ?: 'Descripción no disponible';

    switch ($tipo_producto) {
        case 'TNT':
            $etiqueta_tipo = "TNT";
            $descripcion_adicional = "Material no tejido de polipropileno";
            break;

        case 'SPUNLACE':
            $etiqueta_tipo = "Spunlace";
            $descripcion_adicional = "Material hidroenmarañado";
            break;

        case 'TOALLITAS':
            $etiqueta_tipo = "Toallitas";
            $descripcion_adicional = "Material para toallitas húmedas";
            break;

        default:
            $etiqueta_tipo = $tipo_producto;
            $descripcion_adicional = "Material textil no tejido";
    }

    $cliente = $orden['cliente'] ?? '';

    switch ($tipo_producto) {
        case 'TNT':
            $texto_validez_pt = "VALIDADE DE DOIS ANOS DESDE QUE SEJAM SEGUIDAS AS CONDIÇÕES DE MANUSEIO E ARMAZENAGEM.";
            $texto_validez_en = "TWO YEARS VALID FROM PRODUCT DATE IF THE HANDLING AND STORAGE CONDITIONS ARE FOLLOWED.";
            break;

        case 'SPUNLACE':
            if ($cliente === 'MILI S/A') {
                $texto_validez_pt = "VALIDADE DE UM ANO DESDE QUE SEJAM SEGUIDAS AS CONDIÇÕES DE MANUSEIO E ARMAZENAGEM.";
                $texto_validez_en = "ONE YEAR VALID FROM PRODUCT DATE IF THE HANDLING AND STORAGE CONDITIONS ARE FOLLOWED.";
            } else {
                $texto_validez_pt = "VALIDADE DE NOVE MESES DESDE QUE SEJAM SEGUIDAS AS CONDIÇÕES DE MANUSEIO E ARMAZENAGEM.";
                $texto_validez_en = "NINE MONTHS VALID FROM PRODUCT DATE IF THE HANDLING AND STORAGE CONDITIONS ARE FOLLOWED.";
            }
            break;

        default:
            $texto_validez_pt = "VALIDADE DE DOIS ANOS DESDE QUE SEJAM SEGUIDAS AS CONDIÇÕES DE MANUSEIO E ARMAZENAGEM.";
            $texto_validez_en = "TWO YEARS VALID FROM PRODUCT DATE IF THE HANDLING AND STORAGE CONDITIONS ARE FOLLOWED.";
    }

    error_log("🏷️ Validez aplicada - Tipo: {$tipo_producto}, Cliente: {$cliente}, Período: " . substr($texto_validez_pt, 12, 15));

    if (!function_exists('dividirTextoEnLineas')) {
        function dividirTextoEnLineas($pdf, $texto, $ancho_max, $font_size = 8)
        {
            $pdf->SetFont('helvetica', 'B', $font_size);

            $palabras = explode(' ', $texto);
            $lineas = array();
            $linea_actual = '';

            foreach ($palabras as $palabra) {
                $test_linea = $linea_actual . ($linea_actual ? ' ' : '') . $palabra;
                $ancho_test = $pdf->GetStringWidth($test_linea);

                if ($ancho_test <= $ancho_max) {
                    $linea_actual = $test_linea;
                } else {
                    if ($linea_actual) {
                        $lineas[] = $linea_actual;
                        $linea_actual = $palabra;
                    } else {
                        $lineas[] = $palabra;
                    }
                }
            }

            if ($linea_actual) {
                $lineas[] = $linea_actual;
            }

            return $lineas;
        }
    }

    function generarEtiquetaIndividual($pdf, $datos)
    {
        // Extraer datos
        $numero_item = $datos['numero_item'];
        $numero_lote = $datos['numero_lote'];
        $ancho_metros = $datos['ancho_metros'];
        $metraje = $datos['metraje'];
        $gramatura = $datos['gramatura'];
        $peso_kg = $datos['peso_kg'];
        $fecha_produccion = $datos['fecha_produccion'];
        $numero_etiqueta = $datos['numero_etiqueta'];
        $etiqueta_tipo = $datos['etiqueta_tipo'];
        $descripcion_producto = $datos['descripcion_producto'];
        $producto = $datos['producto'];
        $texto_validez_pt = $datos['texto_validez_pt'];
        $texto_validez_en = $datos['texto_validez_en'];

        $width = 96;
        $height = 76;
        $barcode_etiqueta = str_pad($numero_etiqueta, 12, '0', STR_PAD_LEFT);

        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Line(3, 3, 97, 3);

        $pdf->write1DBarcode($barcode_etiqueta, 'C128', 21, 6, 30, 10, 0.4, array(
            'position' => 'S',
            'border' => false,
            'padding' => 1
        ));

        $qr_data = "AMERICA TNT S.A\n" .
            $etiqueta_tipo . ": " . $descripcion_producto . "\n" .
            "Fecha de Producción: " . $fecha_produccion . "\n" .
            "Peso: " . formatearNumero($peso_kg, 2) . " kg\n" .
            "Dimensiones: " . formatearNumero($ancho_metros, 2) . "m x " . formatearNumero($metraje, 1) . "m\n" .
            "Tipo: " . $etiqueta_tipo . "\n" .
            "www.americatnt.com\n" .
            "contato@americatnt.com";

        $pdf->write2DBarcode($qr_data, 'QRCODE,L', 82, 5, 15, 15, array(
            'border' => false,
            'padding' => 0
        ));

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY(3, 17);
        $pdf->Cell(30, 3, 'www.americatnt.com', 0, 0, 'L');

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY(44, 17);
        $pdf->Cell(30, 3, 'contato@americatnt.com', 0, 0, 'L');

        $font_size_descripcion = 9;
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Line(3, 22, 97, 22);

        $ancho_disponible = $width - 6;
        $lineas_descripcion = dividirTextoEnLineas($pdf, $descripcion_producto, $ancho_disponible, $font_size_descripcion);

        $pdf->SetFont('helvetica', 'B', $font_size_descripcion);
        $y_inicio = 26;
        $altura_linea = 4;

        for ($i = 0; $i < count($lineas_descripcion) && $i < 2; $i++) {
            $y_actual = $y_inicio + ($i * $altura_linea);
            $pdf->SetXY(3, $y_actual);
            $pdf->Cell($width - 2, $altura_linea, $lineas_descripcion[$i], 0, 1, 'C');
        }

        $nueva_y = $y_inicio + (min(count($lineas_descripcion), 2) * $altura_linea) + 3;

        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(0, 0, 0);

        $table_y = $nueva_y + 2;
        $row_height = 6;
        $col_widths = array(16, 31, 21, 26);

        $headers1 = array('LOTE Nº', 'COR/COLOR', 'GRAMATURA BASIS WEIGHT', 'ETIQUETA/LABEL');

        $pdf->SetFont('helvetica', 'B', 5);
        $x_pos = 3;

        for ($i = 0; $i < count($headers1); $i++) {
            $pdf->SetLineWidth(0.3);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->Rect($x_pos, $table_y, $col_widths[$i], $row_height, 'D');

            if ($i == 2) {
                $pdf->SetXY($x_pos, $table_y + 0.5);
                $pdf->MultiCell($col_widths[$i], ($row_height - 1) / 2, $headers1[$i], 0, 'C', false, 0);
            } else {
                $pdf->SetXY($x_pos, $table_y + 1);
                $pdf->Cell($col_widths[$i], $row_height - 2, $headers1[$i], 0, 0, 'C');
            }
            $x_pos += $col_widths[$i];
        }

        $datos1 = array(
            $numero_lote,
            explode(' ', trim($producto['color'] ?: 'BLANCO'))[0],
            formatearNumero($gramatura, 0) . ' G/M²',
            $numero_etiqueta
        );

        $table_y += $row_height;
        $pdf->SetFont('helvetica', 'B', 7);
        $x_pos = 3;

        for ($i = 0; $i < count($datos1); $i++) {
            $pdf->SetLineWidth(0.3);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->Rect($x_pos, $table_y, $col_widths[$i], $row_height, 'D');
            $pdf->SetXY($x_pos, $table_y + 1);
            $pdf->Cell($col_widths[$i], $row_height - 2, $datos1[$i], 0, 0, 'C');
            $x_pos += $col_widths[$i];
        }

        $col_widths = array(17, 16, 18, 20, 23);

        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(0, 0, 0);

        $table_y += $row_height + 0;

        $headers2 = array(
            'Nº ITEM',
            'PESO(KG)',
            'LARGURA WIDTH(M)',
            'METRAGEN LENGHT(M)',
            'DATA-TURNO DATE-SHIFT'
        );

        $pdf->SetFont('helvetica', 'B', 5);
        $x_pos = 3;

        for ($i = 0; $i < count($headers2); $i++) {
            $pdf->SetLineWidth(0.3);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->Rect($x_pos, $table_y, $col_widths[$i], $row_height, 'D');

            if ($i >= 2) {
                $pdf->SetXY($x_pos, $table_y + 0.5);
                $pdf->MultiCell($col_widths[$i], ($row_height - 1) / 2, $headers2[$i], 0, 'C', false, 0);
            } else {
                $pdf->SetXY($x_pos, $table_y + 1);
                $pdf->Cell($col_widths[$i], $row_height - 2, $headers2[$i], 0, 0, 'C');
            }
            $x_pos += $col_widths[$i];
        }

        $datos2 = array(
            $numero_item,
            formatearNumero($peso_kg, 3) . ' KG',
            formatearNumero($ancho_metros, 3) . ' M',
            formatearNumero($metraje, 3) . ' M',
            $fecha_produccion
        );

        $table_y += $row_height;
        $pdf->SetFont('helvetica', 'B', 7);
        $x_pos = 3;

        for ($i = 0; $i < count($datos2); $i++) {
            $pdf->SetLineWidth(0.3);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->Rect($x_pos, $table_y, $col_widths[$i], $row_height, 'D');
            $pdf->SetXY($x_pos, $table_y + 1);
            $pdf->Cell($col_widths[$i], $row_height - 2, $datos2[$i], 0, 0, 'C');
            $x_pos += $col_widths[$i];
        }

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetXY(3, $table_y + 6);
        $pdf->Cell($width - 2, 4, 'FABRICADO NO PARAGUAY', 0, 1, 'C');

        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Line(3, 67, 97, 67);

        $pdf->SetFont('helvetica', 'B', 5.1);
        $pdf->SetXY(3, 68);
        $pdf->Cell($width - 2, 4, $texto_validez_pt, 0, 1, 'C');

        $pdf->SetFont('helvetica', 'B', 5.1);
        $pdf->SetXY(3, 72);
        $pdf->Cell($width - 2, 4, $texto_validez_en, 0, 1, 'C');

        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Line(3, 77, 97, 77);
    }

    class EtiquetaUniversalIndividualPDF extends TCPDF
    {
        public function Header() {}
        public function Footer() {}
    }

    $pdf = new EtiquetaUniversalIndividualPDF('L', 'mm', array(100, 80), true, 'UTF-8', false);

    $pdf->SetCreator('America TNT S.A.');
    $pdf->SetAuthor('America TNT S.A.');
    $pdf->SetTitle("Etiqueta {$etiqueta_tipo} - " . $descripcion_producto);
    $pdf->SetSubject("Etiqueta de Producto {$etiqueta_tipo}");

    $pdf->SetMargins(2, 2, 2);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(0);
    $pdf->SetAutoPageBreak(false);

    if ($esReimpresionLote && isset($itemsLote) && !empty($itemsLote)) {
        foreach ($itemsLote as $index => $item) {
            $pdf->AddPage();
            $numero_item = $item['numero_item'];
            $numero_lote = str_pad($orden['id'], 7, '0', STR_PAD_LEFT);
            $ancho_metros = $item['ancho'] ?: $producto['largura_metros'];
            $metraje = $item['metraje'] ?: $producto['longitud_bobina'];
            $gramatura = $item['gramatura'] ?: $producto['gramatura'];
            $peso_kg = ($ancho_metros * $gramatura * $metraje) / 1000;
            $fecha_produccion = date('d/m/Y', strtotime($item['fecha_hora_producida']));
            $numero_etiqueta = $item['id'];

            $datosEtiqueta = [
                'numero_item' => $numero_item,
                'numero_lote' => $numero_lote,
                'ancho_metros' => $ancho_metros,
                'metraje' => $metraje,
                'gramatura' => $gramatura,
                'peso_kg' => $peso_kg,
                'fecha_produccion' => $fecha_produccion,
                'numero_etiqueta' => $numero_etiqueta,
                'etiqueta_tipo' => $etiqueta_tipo,
                'descripcion_producto' => $descripcion_producto,
                'producto' => $producto,
                'texto_validez_pt' => $texto_validez_pt,
                'texto_validez_en' => $texto_validez_en
            ];

            generarEtiquetaIndividual($pdf, $datosEtiqueta);

            error_log("🏷️ Etiqueta " . ($index + 1) . " generada - Item: {$numero_item}, Peso: {$peso_kg}kg");
        }

        error_log("✅ PDF Individual en lote completado: " . count($itemsLote) . " etiquetas generadas");
        $nombre_archivo = "Etiquetas_{$tipo_producto}_Individual_Orden_{$id_orden}_Items_{$itemDesde}-{$itemHasta}.pdf";
    } else {
        $pdf->AddPage();
        $numero_item = $stock_data ? $stock_data['numero_item'] : 1;
        $numero_lote = str_pad($orden['id'], 7, '0', STR_PAD_LEFT);

        if ($stock_data) {
            $ancho_metros = $stock_data['ancho'];
            $metraje = $stock_data['metraje'];
            $gramatura = $stock_data['gramatura'];
            $peso_kg = ($ancho_metros * $gramatura * $metraje) / 1000;
            $fecha_produccion = $stock_data['fecha_hora_producida'] ?
                date('d/m/Y', strtotime($stock_data['fecha_hora_producida'])) :
                date('d/m/Y');
        } else {
            $ancho_metros = $producto['largura_metros'] ?: 1.0;
            $metraje = $producto['longitud_bobina'] ?: 100;
            $gramatura = $producto['gramatura'] ?: 17;
            $peso_kg = ($ancho_metros * $gramatura * $metraje) / 1000;
            $fecha_produccion = date('d/m/Y');
        }

        $numero_etiqueta = $stock_data ? $stock_data['id'] : $id_orden;

        $datosEtiqueta = [
            'numero_item' => $numero_item,
            'numero_lote' => $numero_lote,
            'ancho_metros' => $ancho_metros,
            'metraje' => $metraje,
            'gramatura' => $gramatura,
            'peso_kg' => $peso_kg,
            'fecha_produccion' => $fecha_produccion,
            'numero_etiqueta' => $numero_etiqueta,
            'etiqueta_tipo' => $etiqueta_tipo,
            'descripcion_producto' => $descripcion_producto,
            'producto' => $producto,
            'texto_validez_pt' => $texto_validez_pt,
            'texto_validez_en' => $texto_validez_en
        ];

        generarEtiquetaIndividual($pdf, $datosEtiqueta);

        error_log("📄 PDF Universal Individual generado - Tipo: {$tipo_producto}, Orden: {$id_orden}, Item: {$numero_item}, Peso: {$peso_kg}kg, Dimensiones: {$ancho_metros}m x {$metraje}m");
        $nombre_archivo = "Etiqueta_{$tipo_producto}_Individual_{$numero_etiqueta}_Item_{$numero_item}_" . formatearNumero($peso_kg, 1) . 'kg.pdf';
    }

    $pdf->Output($nombre_archivo, 'I');
    exit();
} catch (Exception $e) {
    error_log("💥 Error generando PDF Universal Individual: " . $e->getMessage());
    header("Location: ../ordenproduccion.php?error=" . urlencode("Error al generar la etiqueta {$tipo_producto}: " . $e->getMessage()));
    exit();
}
