<?php
ini_set('memory_limit', '512M');
require_once __DIR__ . "/../../../config/conexionBD.php";
include "../../../vendor/tecnickcom/tcpdf/tcpdf.php";

if (!isset($_GET['id_orden']) || empty($_GET['id_orden'])) {
    header("Location: ../nueva_orden_manual.php?error=ID de orden no especificado");
    exit();
}

$id_orden = (int)$_GET['id_orden'];

function extraerDiametro($nombre)
{
    $nombre = trim((string)$nombre);

    if (preg_match('/(\d+)\s*CM\s*$/i', $nombre, $matches)) {
        return $matches[1];
    }
    return '-';
}

function extraerPrimeraPalabra($texto)
{
    if (empty(trim($texto))) {
        return '';
    }

    $primeraPalabra = strtok(trim($texto), ' ');
    return $primeraPalabra ?: '';
}

try {
    $query_orden = "SELECT op.id, op.id_venta, op.fecha_orden, op.estado, op.observaciones,
                    v.cliente, v.id as id_venta,
                    prod.descripcion, prod.tipoproducto, prod.unidadmedida, prod.instruccion,
                    sp.gramatura, sp.largura_metros, sp.longitud_bobina, sp.color,
                    sp.peso_bobina, sp.cantidad_total, sp.total_bobinas, sp.pesominbobina,
                    sp.acabado,sp.nombre,
                    
                    'SPUNLACE' as tipo_producto_real
                    
                    FROM public.sist_ventas_orden_produccion op
                    LEFT JOIN public.sist_ventas_presupuesto v ON op.id_venta = v.id
                    LEFT JOIN public.sist_ventas_op_spunlace sp ON sp.id_orden_produccion = op.id
                    LEFT JOIN public.sist_ventas_pres_product prod ON sp.id_producto = prod.id
                    WHERE op.id = :id_orden";

    $stmt_orden = $conexion->prepare($query_orden);
    $stmt_orden->bindParam(':id_orden', $id_orden, PDO::PARAM_INT);
    $stmt_orden->execute();
    $orden = $stmt_orden->fetch(PDO::FETCH_ASSOC);

    if (!$orden) {
        header("Location: ../secciones/sectorPcp/produccion.php?error=Orden de producción no encontrada");
        exit();
    }
    $orden['gramatura'] = $orden['gramatura'] ?: '-';
    $orden['largura_metros'] = $orden['largura_metros'] ?: 0;
    $orden['longitud_bobina'] = $orden['longitud_bobina'] ?: '-';
    $orden['color'] = $orden['color'] ?: 'N/A';
    $orden['peso_bobina'] = $orden['peso_bobina'] ?: '-';
    $orden['total_bobinas'] = $orden['total_bobinas'] ?: '-';
    $orden['pesominbobina'] = $orden['pesominbobina'] ?: '-';
    $orden['cantidad_total'] = $orden['cantidad_total'] ?: 0;
    $orden['acabado'] = $orden['acabado'] ?: '';
    $orden['instruccion'] = $orden['instruccion'] ?: '';


    $diametro_extraido = extraerDiametro($orden['nombre']);

    $acabado_primera_palabra = extraerPrimeraPalabra($orden['acabado']);

    class MYPDF extends TCPDF
    {
        public function Header()
        {
            // No header
        }
        public function Footer()
        {
            // No footer
        }
    }

    $pdf = new MYPDF('P');

    $pdf->SetCreator('America TNT S.A.');
    $pdf->SetAuthor('America TNT S.A.');
    $pdf->SetTitle('Ordem de Producão - KAN BAN');
    $pdf->SetSubject('Ordem de Producão');
    $pdf->SetKeywords('TCPDF, PDF, orden, producción, kanban, spunlace');

    $pdf->SetMargins(10, 10, 10);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(0);
    $pdf->SetAutoPageBreak(true, 10);

    $pdf->AddPage();

    $pdf->SetFont('helvetica', '', 10);

    $pdf->SetFillColor(200, 240, 200);
    $pdf->Rect(10, 10, 190, 12, 'F');

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetXY(12, 12);
    $pdf->Cell(100, 8, 'ORDEM DE PRODUÇÃO - KAN BAN', 0, 0, 'L');

    // Texto "AMERICA  S.A." en la parte superior derecha, sin borde
    $pdf->SetFont('helvetica', 'B', 9); // Tamaño más pequeño
    $pdf->SetXY(160, 10); // Posición ajustada más arriba
    $pdf->Cell(30, 5, 'AMERICA TNT S.A.', 0, 0, 'R');

    // Número de orden debajo, también sin borde y centrado
    $pdf->SetFont('helvetica', 'B', 10);
    // Texto "Nº"
    $pdf->SetXY(156, 15);
    $pdf->Cell(10, 5, 'Nº', 0, 0, 'R');
    // Número (con más espacio a la izquierda)
    $pdf->SetXY(169, 15);
    $pdf->Cell(20, 5, $orden['id'], 0, 0, 'L');

    // Línea de PRODUTO
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetXY(9, 25);
    $pdf->Cell(25, 8, 'PRODUTO:', 0, 0, 'L');

    // Descripción del producto
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY(29, 25);
    $pdf->Cell(158, 8, $orden['nombre'], 0, 0, 'L');

    // Creamos la tabla principal
    $pdf->SetFont('helvetica', 'B', 9);

    $pdf->SetXY(10, 35);
    // Primera fila de la tabla
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell(40, 10, $orden['total_bobinas'], 1, 0, 'C', 1);
    $pdf->Cell(70, 10, 'Total bobinas a produzir', 1, 0, 'C', 1);
    // Dividir la tercera columna en 2 celdas
    $pdf->Cell(40, 10, $orden['gramatura'], 1, 0, 'C', 1); // Primera parte (mitad izquierda)
    $pdf->Cell(40, 10, 'GRAMATURA (GR/M2)', 1, 1, 'C', 1);      // Segunda parte (mitad derecha)

    // Segunda fila - Largura
    $pdf->SetXY(10, 45);
    $pdf->Cell(40, 10, rtrim(rtrim(number_format($orden['largura_metros'], 3), '0'), '.'), 1, 0, 'C', 0);
    $pdf->Cell(70, 10, 'Largura (Metros)', 1, 0, 'C', 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(80, 10, 'COR E CARACTERISTICA', 1, 1, 'C', 1);

    // Tercera fila - KGS
    $pdf->SetXY(10, 55);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(40, 10, rtrim(rtrim(number_format($orden['cantidad_total'], 3), '0'), '.'), 1, 0, 'C', 0);
    $pdf->Cell(70, 10, 'KGS (Quantidade de kgs a ser produzido)', 1, 0, 'C', 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(80, 30, $orden['color'], 1, 1, 'C', 0);

    // Cuarta fila - Metros
    $pdf->SetXY(10, 65);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(40, 10, $orden['longitud_bobina'], 1, 0, 'C', 0);
    $pdf->Cell(70, 10, 'Metros (Comprimento bobina)', 1, 0, 'C', 0);
    $pdf->SetFont('helvetica', 'B', 10);

    // 🆕 Quinta fila - Diámetro (AHORA CON EL VALOR EXTRAÍDO)
    $pdf->SetXY(10, 75);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(40, 10, $diametro_extraido, 1, 0, 'C', 0); // 🔥 AQUÍ USAMOS EL DIÁMETRO EXTRAÍDO
    $pdf->Cell(70, 10, 'Diametro Externo do Rolo (CM)', 1, 0, 'C', 0);
    $pdf->SetFont('helvetica', '', 12);

    // Sexta fila - Peso Mínimo/Bobina
    $pdf->SetXY(10, 85);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(40, 10, $orden['pesominbobina'], 1, 0, 'C', 0);
    $pdf->Cell(70, 10, 'Peso Mínimo/Bobina', 1, 0, 'C', 0);
    $pdf->Cell(40, 10, $orden['peso_bobina'], 1, 0, 'C', 1);
    $pdf->Cell(40, 10, 'Peso Máximo/Bobina', 1, 1, 'C', 0);

    $pdf->SetFont('helvetica', 'B', 6);
    $pdf->Cell(29, 10, 'INSTRUÇÕES ADICIONAIS', 1, 0, 'L', 0);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell(161, 10, $orden['instruccion'], 1, 1, 'L', 0);

    // Fila ACABAMENTO con solo la primera palabra del campo acabado
    $pdf->SetXY(10, 110);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(30, 10, 'ACABAMENTO:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(160, 10, $acabado_primera_palabra, 1, 1, 'L', 0); // AQUÍ USAMOS SOLO LA PRIMERA PALABRA

    // Instrucciones
    $pdf->SetXY(10, 120);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(
        110,
        30,
        "1.- Quando finiquitar a Ordem de produção, mover este KANBAN para o quadro LISTO!\n\n" .
            "2.- Caso não tenha conseguido finalizar a produção, favor marcar com bolígrafo amarello, e manter a ordem de produção no quadro até finalizar",
        1,
        'L',
        0,
        1
    );

    // Previsión de embarque
    $pdf->SetXY(120, 120);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(80, 10, 'PREVISÃO DE EMBARQUE', 1, 1, 'C', 1);

    $pdf->SetXY(120, 130);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(80, 20, 'null', 1, 1, 'C', 0);

    // Encabezados de la tabla (con bordes completos)
    $pdf->SetXY(10, 160);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(38, 10, 'Nº DO ROLO', 1, 0, 'C', 1);
    $pdf->Cell(38, 10, 'ASINATURA', 1, 0, 'C', 1);
    $pdf->Cell(38, 10, 'DATA', 1, 0, 'C', 1);
    $pdf->Cell(38, 10, 'TURNO', 1, 0, 'C', 1);
    $pdf->Cell(38, 10, 'OBS:', 1, 1, 'C', 1);

    // Filas sin bordes horizontales (excepto la última)
    for ($i = 0; $i < 11; $i++) {  // 11 filas
        // Si es la última fila (índice 10), añade borde inferior ('LRB')
        $border = ($i === 10) ? 'LRB' : 'LR'; // 'LRB' = Left, Right, Bottom

        $pdf->Cell(38, 10, '', $border, 0, 'C');
        $pdf->Cell(38, 10, '', $border, 0, 'C');
        $pdf->Cell(38, 10, '', $border, 0, 'C');
        $pdf->Cell(38, 10, '', $border, 0, 'C');
        $pdf->Cell(38, 10, '', $border, 1, 'C');
    }


    // Generar el PDF
    $pdf->Output('OrdemProducao_Spunlace_' . $id_orden . '.pdf', 'I');
    exit();
} catch (Exception $e) {
    header("Location: ../nueva_orden_manual.php?error=" . urlencode("Error al generar el PDF: " . $e->getMessage()));
    exit();
}
