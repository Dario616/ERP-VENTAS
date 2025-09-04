<?php
ini_set('memory_limit', '512M');
require_once __DIR__ . "/../../../config/conexionBD.php";
include "../../../vendor/tecnickcom/tcpdf/tcpdf.php";

if (!isset($_GET['id_orden']) || empty($_GET['id_orden'])) {
    header("Location:../ordenproduccion.php?error=ID de orden no especificado");
    exit();
}

$id_orden = (int)$_GET['id_orden'];
$id_stock_especifico = isset($_GET['id_stock']) ? (int)$_GET['id_stock'] : null;

$esReimpresionLote = isset($_GET['lote']) && $_GET['lote'] == '1';
$itemDesde = isset($_GET['item_desde']) ? intval($_GET['item_desde']) : null;
$itemHasta = isset($_GET['item_hasta']) ? intval($_GET['item_hasta']) : null;

error_log("🧻 Etiqueta Toallitas Húmedas - Orden: {$id_orden}, Modo: " .
    ($esReimpresionLote ? "LOTE ($itemDesde-$itemHasta)" : "INDIVIDUAL"));

try {
    $query_orden = "SELECT op.id, op.id_venta, op.fecha_orden, op.estado, op.observaciones,
                    v.cliente, v.id as id_venta,
                    prod.descripcion, prod.tipoproducto, prod.unidadmedida,
                    
                    toal.nombre as nombre_toallitas, 
                    toal.cantidad_total as cantidad_toallitas,
                    
                    TO_CHAR(CURRENT_DATE, 'DD/MM/YYYY') as fecha_fabricacion,
                    CONCAT('LOTE:', LPAD(op.id::text, 5, '0')) as numero_lote
                    
                    FROM public.sist_ventas_orden_produccion op
                    LEFT JOIN public.sist_ventas_presupuesto v ON op.id_venta = v.id
                    LEFT JOIN public.sist_ventas_op_toallitas toal ON toal.id_orden_produccion = op.id
                    LEFT JOIN public.sist_ventas_pres_product prod ON toal.id_producto = prod.id
                    WHERE op.id = :id_orden AND toal.id IS NOT NULL";

    $stmt_orden = $conexion->prepare($query_orden);
    $stmt_orden->bindParam(':id_orden', $id_orden, PDO::PARAM_INT);
    $stmt_orden->execute();
    $orden = $stmt_orden->fetch(PDO::FETCH_ASSOC);

    if (!$orden) {
        header("Location:../ordenproduccion.php?error=Orden de toallitas no encontrada");
        exit();
    }

    if ($esReimpresionLote) {
        if (!$itemDesde || !$itemHasta || $itemDesde > $itemHasta) {
            error_log("❌ Error: Rango de items inválido para reimpresión en lote");
            header("Location:../ordenproduccion.php?error=" . urlencode("Rango de items inválido"));
            exit();
        }

        if (($itemHasta - $itemDesde + 1) > 100) {
            error_log("❌ Error: No se pueden imprimir más de 100 etiquetas en un lote");
            header("Location:../ordenproduccion.php?error=" . urlencode("Máximo 100 etiquetas por lote"));
            exit();
        }

        $sql = "SELECT id, numero_item, fecha_hora_producida
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
            header("Location:../ordenproduccion.php?error=" . urlencode("No se encontraron items en el rango especificado"));
            exit();
        }

        $totalItemsEncontrados = count($itemsLote);
        error_log("📊 Items de toallitas encontrados: {$totalItemsEncontrados}");
    } elseif ($id_stock_especifico) {
        $query_stock = "SELECT id
                        FROM public.sist_prod_stock 
                        WHERE id = :id_stock AND id_orden_produccion = :id_orden";

        $stmt_stock = $conexion->prepare($query_stock);
        $stmt_stock->bindParam(':id_stock', $id_stock_especifico, PDO::PARAM_INT);
        $stmt_stock->bindParam(':id_orden', $id_orden, PDO::PARAM_INT);
        $stmt_stock->execute();
        $stock_data = $stmt_stock->fetch(PDO::FETCH_ASSOC);

        if (!$stock_data) {
            header("Location:../ordenproduccion.php?error=Registro de stock específico no encontrado");
            exit();
        }

        error_log("🎯 Reimprimiendo registro específico - ID: " . $id_stock_especifico);
    } else {
        $query_stock = "SELECT id
                        FROM public.sist_prod_stock 
                        WHERE id_orden_produccion = :id_orden 
                        ORDER BY fecha_hora_producida DESC, id DESC
                        LIMIT 1";

        $stmt_stock = $conexion->prepare($query_stock);
        $stmt_stock->bindParam(':id_orden', $id_orden, PDO::PARAM_INT);
        $stmt_stock->execute();
        $stock_data = $stmt_stock->fetch(PDO::FETCH_ASSOC);

        if ($stock_data) {
            error_log("📦 Usando último registro - ID: " . $stock_data['id']);
        }
    }

    $nombre_producto = $orden['nombre_toallitas'];

    // Detectar si es producto URSA
    $es_producto_ursa = preg_match('/ursa/i', $nombre_producto);

    $unidades = 100;
    if (preg_match('/(\d+)\s*Unidades/i', $nombre_producto, $matches_unidades)) {
        $unidades = (int)$matches_unidades[1];
    }

    $dimensiones = '20X15CM';
    if (preg_match('/(\d+)\s*cm?\s*x\s*(\d+)\s*cm?/i', $nombre_producto, $matches_dimensiones)) {
        $dimensiones = $matches_dimensiones[1] . 'X' . $matches_dimensiones[2] . 'CM';
    }

    // Detectar si tiene TAPA en el nombre
    $tiene_tapa = preg_match('/tapa/i', $nombre_producto);

    // Lógica principal: Primero verificar si tiene TAPA (cualquier producto)
    if ($tiene_tapa) {
        $barcode_data = '8944513604817';
        error_log("🔍 Producto CON TAPA detectado: {$nombre_producto} - Usando código especial para TAPA");

        if ($es_producto_ursa) {
            $paquetes = 90; // URSA siempre 90 paquetes
            error_log("🔍 Producto URSA CON TAPA: {$nombre_producto} - 90 paquetes");
        } else {
            $paquetes = 16;
            if ($dimensiones == '20X10CM' && $unidades == 20) {
                $paquetes = 44;
            }
        }
    } else {
        // Productos SIN TAPA
        if ($es_producto_ursa) {
            $paquetes = 90; // URSA siempre 90 paquetes
            $barcode_data = '8944513604756';
            error_log("🔍 Producto URSA SIN TAPA detectado: {$nombre_producto} - SIEMPRE 90 paquetes y código URSA normal");
        } else {
            // Configuración para otros productos SIN TAPA
            $paquetes = 16;
            $barcode_data = '8944513604916';

            if ($dimensiones == '20X10CM' && $unidades == 20) {
                $paquetes = 44;
                $barcode_data = '8944513604732';
            }
        }
    }

    function generarEtiquetaToallitas($pdf, $datos)
    {
        $nombre_producto = $datos['nombre_producto'];
        $unidades = $datos['unidades'];
        $dimensiones = $datos['dimensiones'];
        $paquetes = $datos['paquetes'];
        $barcode_data = $datos['barcode_data'];
        $stock_id_original = $datos['stock_id_original'];
        $id_orden = $datos['id_orden'];
        $orden = $datos['orden'];

        $pdf->SetLineWidth(1);
        $pdf->Rect(1, 1, 98, 78, 'D');

        $es_producto_ursa = preg_match('/ursa/i', $nombre_producto);

        if ($es_producto_ursa) {
            $ursaLogoPath = '../../../utils/ursall_Nero.png';
            if (file_exists($ursaLogoPath)) {
                $pdf->Image($ursaLogoPath, 12, 4, 40, 17, '', '', '', true, 300, '', false, false, 0, false, false, false);
            } else {
                $pdf->SetFont('helvetica', 'B', 16);
                $pdf->SetXY(2, 8);
                $pdf->Cell(60, 8, 'Ursa', 0, 0, 'L');
            }
        } else {
            $cottonfreshLogoPath = '../../../utils/cotton.jpeg';
            if (file_exists($cottonfreshLogoPath)) {
                $pdf->Image($cottonfreshLogoPath, 2, 3, 62, 15, '', '', '', true, 300, '', false, false, 0, false, false, false);
            } else {
                $pdf->SetFont('helvetica', 'B', 16);
                $pdf->SetXY(2, 8);
                $pdf->Cell(60, 8, 'Cottonfresh', 0, 0, 'L');
            }
        }

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetXY(2, 21);
        $pdf->Cell(20, 4, 'CONTIENE ', 0, 0, 'L');

        $pdf->SetXY(23, 21);
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(8, 4, $paquetes, 0, 0, 'C');

        $pdf->SetXY(29, 21);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(25, 4, ' PAQUETES DE', 0, 1, 'L');

        $pdf->SetXY(7, 25);
        $pdf->Cell(55, 4, "TOALLITAS HUMEDAS -", 0, 1, 'L');

        $pdf->SetXY(2, 29);
        $pdf->Cell(8, 4, "{$dimensiones} CON ", 0, 0, 'L');

        $pdf->SetXY(28, 29);
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(15, 4, $unidades, 0, 0, 'C');

        $pdf->SetXY(39, 29);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(25, 4, ' UNIDADES', 0, 1, 'L');

        // Verificar si el nombre del producto contiene "TAPA" y agregar "C/TAPA"
        if (preg_match('/tapa/i', $nombre_producto)) {
            $pdf->SetXY(22, 33);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(25, 4, 'C/TAPA', 0, 1, 'L');
        }

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(2, 41);
        $pdf->MultiCell(55, 3, "COMTEM {$paquetes} PACOTES DE LENÇO\nHUMEDECIDO - {$dimensiones} - DE\n{$unidades} FOLHAS", 0, 'L');
        // Verificar si el nombre del producto contiene "TAPA" y agregar "C/TAPA"
        if (preg_match('/tapa/i', $nombre_producto)) {
            $pdf->SetXY(22, 49);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(25, 4, 'C/TAPA', 0, 1, 'L');
        }

        $pdf->SetLineWidth(0.5);

        $pdf->Rect(65, 3, 32, 18, 'D');
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetXY(64.7, 6);
        $pdf->Cell(28, 2.5, 'FECHA DE FABRICACION', 0, 1, 'L');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(72, 10);
        $pdf->Cell(28, 3, date('d/m/Y'), 0, 1, 'L');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(70, 15);
        $pdf->Cell(28, 2.5, 'LOTE:' . str_pad($orden['id'], 6, '0', STR_PAD_LEFT), 0, 1, 'L');

        $pdf->SetLineWidth(0.5);

        $pdf->Rect(65, 21, 32, 22, 'D');
        $pdf->SetFont('helvetica', 'B', 6);
        $pdf->SetXY(67, 22);
        $pdf->Cell(28, 2, 'FABRICADO POR', 0, 1, 'L');
        $pdf->SetXY(67, 25);
        $pdf->Cell(28, 2, 'AMERICA TNT S.A.', 0, 1, 'L');
        $pdf->SetXY(67, 28);
        $pdf->Cell(28, 2, 'RUC: 80094986-2', 0, 1, 'L');
        $pdf->SetXY(67, 31);
        $pdf->Cell(28, 2, 'FABRICADO/EMBALADO', 0, 1, 'L');
        $pdf->SetXY(67, 34);
        $pdf->Cell(28, 2, 'EN PARAGUAY', 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 4);
        $pdf->SetXY(67, 37);
        $pdf->MultiCell(28, 1.2, "SUPERCARRETERA ITAIPU\nKM 35, HERNANDARIAS,\nPARAGUAY.", 0, 'L');

        $pdf->write1DBarcode($barcode_data, 'C128', 64.5, 43.5, 33, 12, 0.3, array(
            'position' => 'S',
            'border' => false,
            'padding' => 1
        ));

        $pdf->SetLineWidth(0.5);
        $pdf->Rect(65, 43, 32, 15, 'D');

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetXY(65, 54.7);
        $barcode_display = $barcode_data;
        $pdf->Cell(32, 2, $barcode_display, 0, 0, 'C');

        // CÓDIGO DE BARRAS DE REFERENCIA INTERNA ELIMINADO COMPLETAMENTE

        $pdf->SetFont('helvetica', '', 5);
        $pdf->SetXY(2, 61);
        $pdf->Cell(20, 2.5, 'NAO PISE.', 0, 1, 'L');
        $pdf->SetXY(2, 63);
        $pdf->Cell(20, 2.5, 'NO PISAR', 0, 1, 'L');

        $icon_y = 66;
        $icon_size = 10;
        $icon_spacing = 12;
        $area_width = ($icon_spacing * 3) + $icon_size;
        $area_height = $icon_size;

        $pdf->Image('../../../utils/icon.jpg', 2, $icon_y, $area_width, $area_height, 'JPG');
    }

    class EtiquetaPDF extends TCPDF
    {
        public function Header() {}
        public function Footer() {}
    }

    $pdf = new EtiquetaPDF('L', 'mm', array(100, 80), true, 'UTF-8', false);

    $pdf->SetCreator('America TNT S.A.');
    $pdf->SetAuthor('America TNT S.A.');
    $pdf->SetTitle('Etiqueta - Toallitas Húmedas');
    $pdf->SetSubject('Etiqueta de Producto');

    $pdf->SetMargins(0, 0, 0);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(0);
    $pdf->SetAutoPageBreak(false);

    if ($esReimpresionLote && isset($itemsLote) && !empty($itemsLote)) {
        foreach ($itemsLote as $index => $item) {
            $pdf->AddPage();
            $stock_id_item = $item['id'];
            $stock_id_original = str_pad($stock_id_item, 13, '0', STR_PAD_LEFT);

            $datosEtiqueta = [
                'nombre_producto' => $nombre_producto,
                'unidades' => $unidades,
                'dimensiones' => $dimensiones,
                'paquetes' => $paquetes,
                'barcode_data' => $barcode_data,
                'stock_id_original' => $stock_id_original,
                'id_orden' => $id_orden,
                'orden' => $orden
            ];

            generarEtiquetaToallitas($pdf, $datosEtiqueta);

            error_log("🏷️ Etiqueta toallita " . ($index + 1) . " generada - Item: {$item['numero_item']}, ID: {$stock_id_item}");
        }

        error_log("✅ PDF Toallitas en lote completado: " . count($itemsLote) . " etiquetas generadas");
        $nombre_archivo = "Etiquetas_Toallitas_Orden_{$id_orden}_Items_{$itemDesde}-{$itemHasta}.pdf";
    } else {
        $pdf->AddPage();
        $stock_id_original = null;
        if ($stock_data && isset($stock_data['id'])) {
            $stock_id_original = str_pad($stock_data['id'], 13, '0', STR_PAD_LEFT);
            $numero_item = str_pad($stock_data['id'], 13, '0', STR_PAD_LEFT);
        } else {
            $numero_item = 'ORD' . str_pad($id_orden, 9, '0', STR_PAD_LEFT);
        }

        error_log("🏷️ Código de barras final: " . $numero_item . " (ID Stock: " . $stock_id_original . ") para orden: " . $id_orden);

        $datosEtiqueta = [
            'nombre_producto' => $nombre_producto,
            'unidades' => $unidades,
            'dimensiones' => $dimensiones,
            'paquetes' => $paquetes,
            'barcode_data' => $barcode_data,
            'stock_id_original' => $stock_id_original,
            'id_orden' => $id_orden,
            'orden' => $orden
        ];

        generarEtiquetaToallitas($pdf, $datosEtiqueta);

        error_log("🧻 Etiqueta toallita individual generada - Orden: {$id_orden}, Stock ID: " . ($stock_id_original ?: 'NEW'));
        $nombre_archivo = 'Etiqueta_' . $id_orden . '_Stock_' . ($stock_id_original ?: 'NEW') . '.pdf';
    }

    $pdf->Output($nombre_archivo, 'I');
    exit();
} catch (Exception $e) {
    error_log("💥 Error generando etiqueta de toallitas: " . $e->getMessage());
    header("Location:../ordenproduccion.php?error=" . urlencode("Error al generar la etiqueta: " . $e->getMessage()));
    exit();
}
