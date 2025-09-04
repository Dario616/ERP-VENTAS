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

error_log("🧽 Etiqueta Paños URSA - Orden: {$id_orden}, Modo: " .
    ($esReimpresionLote ? "LOTE ($itemDesde-$itemHasta)" : "INDIVIDUAL"));

try {
    $query_orden = "SELECT op.id, op.id_venta, op.fecha_orden, op.estado, op.observaciones,
                    v.cliente, v.id as id_venta,
                    prod.descripcion, prod.tipoproducto, prod.unidadmedida,
                    panos.nombre as nombre_panos, 
                    panos.cantidad_total as cantidad_panos,
                    panos.color,
                    panos.largura,
                    panos.picotado,
                    panos.cant_panos,
                    panos.unidad,
                    panos.peso,
                    panos.gramatura,
                    
                    TO_CHAR(CURRENT_DATE, 'DD/MM/YYYY') as fecha_fabricacion,
                    CONCAT('LOTE:', LPAD(op.id::text, 5, '0')) as numero_lote
                    
                    FROM public.sist_ventas_orden_produccion op
                    LEFT JOIN public.sist_ventas_presupuesto v ON op.id_venta = v.id
                    LEFT JOIN public.sist_ventas_op_panos panos ON panos.id_orden_produccion = op.id
                    LEFT JOIN public.sist_ventas_pres_product prod ON panos.id_producto = prod.id
                    WHERE op.id = :id_orden AND panos.id IS NOT NULL";

    $stmt_orden = $conexion->prepare($query_orden);
    $stmt_orden->bindParam(':id_orden', $id_orden, PDO::PARAM_INT);
    $stmt_orden->execute();
    $orden = $stmt_orden->fetch(PDO::FETCH_ASSOC);

    if (!$orden) {
        header("Location:../ordenproduccion.php?error=Orden de paños no encontrada");
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
        error_log("📊 Items de paños encontrados: {$totalItemsEncontrados}");
    } elseif ($id_stock_especifico) {
        // 🎯 REIMPRESIÓN ESPECÍFICA
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

        error_log("🎯 Reimprimiendo paño específico - ID: " . $id_stock_especifico);
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
            error_log("🧽 Usando último registro de paño - ID: " . $stock_data['id']);
        }
    }

    $nombre_producto = $orden['nombre_panos'];

    $largura_cm = $orden['largura'] ? $orden['largura'] : 28;
    $ancho_cm = 50;
    if (preg_match('/(\d+)\s*(?:cm)?\s*x\s*(\d+)\s*(?:cm)?/i', $nombre_producto, $matches)) {
        $largura_cm = (int)$matches[1];
        $ancho_cm = (int)$matches[2];
    }

    $cantidad_panos = $orden['cant_panos'] ? $orden['cant_panos'] : 50;
    if (preg_match('/(\d+)\s*paños?/i', $nombre_producto, $matches)) {
        $cantidad_panos = (int)$matches[1];
    }

    $color = $orden['color'] ? $orden['color'] : 'Blanco';
    if (preg_match('/\b(blanco|azul|amarillo|verde|rojo|rosa|celeste)\b/i', $nombre_producto, $matches)) {
        $color = ucfirst(strtolower($matches[1]));
    }

    $unidades_paquete = 12;
    if (preg_match('/(\d+)\s*unidades?/i', $nombre_producto, $matches)) {
        $unidades_paquete = (int)$matches[1];
    }

    if (strtolower($color) === 'azul') {
        $barcode_data = '9780201379624';
    } else {
        $barcode_data = '9780201379617';
    }

    function generarEtiquetaPano($pdf, $datos)
    {
        $nombre_producto = $datos['nombre_producto'];
        $largura_cm = $datos['largura_cm'];
        $ancho_cm = $datos['ancho_cm'];
        $cantidad_panos = $datos['cantidad_panos'];
        $color = $datos['color'];
        $unidades_paquete = $datos['unidades_paquete'];
        $barcode_data = $datos['barcode_data'];
        $numero_item = $datos['numero_item'];

        $ursaLogoPath = '../../../utils/ursall_Nero.png';
        if (file_exists($ursaLogoPath)) {
            $pdf->Image($ursaLogoPath, 32, 3, 36, 21, '', '', '', true, 300, '', false, false, 0, false, false, false);
        } else {
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetXY(5, 15);
            $pdf->Cell(35, 8, 'URSA', 0, 0, 'C');
        }

        $barraLogoPath = '../../../utils/barra.png';
        $pdf->Image($barraLogoPath, 2.5, 23, 15, 52, '', '', '', true, 300, '', false, false, 0, false, false, false);

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(24, 24);
        $pdf->Cell(45, 4, "Contiene {$unidades_paquete} unidades", 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(18, 30);
        $pdf->Cell(45, 5, "Paño Multiuso {$largura_cm}cm x {$ancho_cm}cm", 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(31, 36);
        $pdf->Cell(45, 5, "{$cantidad_panos} Paños {$color}", 0, 1, 'L');

        $pdf->write1DBarcode($barcode_data, 'EAN13', 18, 43, 30, 13, 0.4, [
            'position' => 'S',
            'border' => false,
            'padding' => 1
        ]);

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY(15.5, 55);
        $pdf->Cell(35, 3, $barcode_data, 0, 0, 'C');

        $pdf->SetFont('helvetica', '', 6);
        $pdf->SetXY(52.5, 41);
        $pdf->Cell(35, 3,  'REFERENCIA INTERNA', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);

        $pdf->write1DBarcode($numero_item, 'C128', 55, 43, 30, 13, 0.4, [
            'position' => 'S',
            'border' => false,
            'padding' => 1
        ]);

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY(52.5, 55);
        $pdf->Cell(35, 3, $numero_item, 0, 0, 'C');

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY(35, 62);
        $pdf->Cell(35, 3,  'Fabricado por America TNT S.A', 0, 0, 'C');
        $pdf->SetXY(35, 65);
        $pdf->Cell(35, 3,  'RUC: 80094986-2', 0, 0, 'C');
        $pdf->SetXY(35, 68);
        $pdf->Cell(35, 3,  'Fabricado/Envasado en Paraguay', 0, 0, 'C');
        $pdf->SetXY(35, 71);
        $pdf->Cell(35, 3,  'Supercarretera Itaipu Km35 Hernandarias Paraguay', 0, 0, 'C');
    }

    class EtiquetaPanosPDF extends TCPDF
    {
        public function Header() {}
        public function Footer() {}
    }

    $pdf = new EtiquetaPanosPDF('L', 'mm', array(100, 80), true, 'UTF-8', false);

    $pdf->SetCreator('America TNT S.A.');
    $pdf->SetAuthor('America TNT S.A.');
    $pdf->SetTitle('Etiqueta - Paños URSA');
    $pdf->SetSubject('Etiqueta de Paños');

    $pdf->SetMargins(0, 0, 0);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(0);
    $pdf->SetAutoPageBreak(false);

    if ($esReimpresionLote && isset($itemsLote) && !empty($itemsLote)) {
        foreach ($itemsLote as $index => $item) {
            $pdf->AddPage();

            $stock_id_item = $item['id'];
            $numero_item = str_pad($stock_id_item, 12, '0', STR_PAD_LEFT);

            $datosEtiqueta = [
                'nombre_producto' => $nombre_producto,
                'largura_cm' => $largura_cm,
                'ancho_cm' => $ancho_cm,
                'cantidad_panos' => $cantidad_panos,
                'color' => $color,
                'unidades_paquete' => $unidades_paquete,
                'barcode_data' => $barcode_data,
                'numero_item' => $numero_item
            ];

            generarEtiquetaPano($pdf, $datosEtiqueta);

            error_log("🏷️ Etiqueta paño " . ($index + 1) . " generada - Item: {$item['numero_item']}, ID: {$stock_id_item}");
        }

        error_log("✅ PDF Paños en lote completado: " . count($itemsLote) . " etiquetas generadas");
        $nombre_archivo = "Etiquetas_Panos_URSA_Orden_{$id_orden}_Items_{$itemDesde}-{$itemHasta}.pdf";
    } else {
        $pdf->AddPage();

        $stock_id_original = null;
        if ($stock_data && isset($stock_data['id'])) {
            $stock_id_original = $stock_data['id'];
            $numero_item = str_pad($stock_data['id'], 12, '0', STR_PAD_LEFT);
        } else {
            $numero_item = 'PAN' . str_pad($id_orden, 9, '0', STR_PAD_LEFT);
        }

        error_log("🧽 Código de barras paño: " . $numero_item . " (ID Stock: " . $stock_id_original . ") para orden: " . $id_orden);

        $datosEtiqueta = [
            'nombre_producto' => $nombre_producto,
            'largura_cm' => $largura_cm,
            'ancho_cm' => $ancho_cm,
            'cantidad_panos' => $cantidad_panos,
            'color' => $color,
            'unidades_paquete' => $unidades_paquete,
            'barcode_data' => $barcode_data,
            'numero_item' => $numero_item
        ];

        generarEtiquetaPano($pdf, $datosEtiqueta);

        error_log("🧽 Etiqueta paño individual generada - Orden: {$id_orden}, Stock ID: " . ($stock_id_original ?: 'NEW'));
        $nombre_archivo = 'Etiqueta_Panos_' . $id_orden . '_Stock_' . ($stock_id_original ?: 'NEW') . '.pdf';
    }

    $pdf->Output($nombre_archivo, 'I');
    exit();
} catch (Exception $e) {
    error_log("💥 Error generando etiqueta de paños: " . $e->getMessage());
    header("Location:../ordenproduccion.php?error=" . urlencode("Error al generar la etiqueta de paños: " . $e->getMessage()));
    exit();
}
