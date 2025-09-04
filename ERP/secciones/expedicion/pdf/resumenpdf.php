<?php
ini_set('memory_limit', '512M');
require_once "../../../config/conexionBD.php";
include "../../../vendor/tecnickcom/tcpdf/tcpdf.php";

date_default_timezone_set('America/Asuncion');

if (!isset($_GET['id_rejilla']) || empty($_GET['id_rejilla'])) {
    header("Content-Type: text/html; charset=utf-8");
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Error - PDF de Rejilla</title>
        <meta charset='UTF-8'>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body>
        <div class='container mt-5'>
            <div class='alert alert-danger'>
                <h4><i class='fas fa-exclamation-triangle'></i> Error</h4>
                <p>Se requiere especificar el ID de la rejilla para generar el PDF.</p>
                <hr>
                <a href='rejillas.php' class='btn btn-primary'>
                    <i class='fas fa-arrow-left'></i> Volver a Rejillas
                </a>
            </div>
        </div>
    </body>
    </html>";
    exit();
}

$id_rejilla = (int)$_GET['id_rejilla'];

function determinarTipoProducto($nombre_producto)
{
    $nombre_clean = trim($nombre_producto);
    $nombre_lower = mb_strtolower($nombre_clean, 'UTF-8');

    $nombre_lower = preg_replace('/\s+/', ' ', $nombre_lower);

    if (
        strpos($nombre_lower, 'toallita') !== false ||
        strpos($nombre_lower, 'toallitas') !== false
    ) {
        return 'Toallitas';
    }

    if (
        strpos($nombre_lower, 'paño') !== false ||
        strpos($nombre_lower, 'paños') !== false ||
        strpos($nombre_lower, 'panos') !== false ||
        strpos($nombre_lower, 'paño') !== false
    ) {
        return 'Paño';
    }

    if (
        strpos($nombre_lower, 'tela no tejida spunbond') !== false ||
        preg_match('/^tela\s+no\s+tejida\s+spunbond/i', $nombre_clean)
    ) {
        return 'TNT';
    }

    if (
        strpos($nombre_lower, 'tela no tejida spunlace') !== false ||
        preg_match('/^tela\s+no\s+tejida\s+spunlace/i', $nombre_clean)
    ) {
        return 'Spunlace';
    }

    if (
        strpos($nombre_lower, 'laminado') !== false ||
        strpos($nombre_lower, 'laminadora') !== false ||
        strpos($nombre_lower, 'laminada') !== false
    ) {
        return 'Laminadora';
    }
    return 'Otro';
}

try {
    $query_rejilla = "SELECT numero_rejilla 
                      FROM public.sist_rejillas 
                      WHERE id = :id_rejilla";

    $stmt_rejilla = $conexion->prepare($query_rejilla);
    $stmt_rejilla->bindParam(':id_rejilla', $id_rejilla, PDO::PARAM_INT);
    $stmt_rejilla->execute();
    $rejilla = $stmt_rejilla->fetch(PDO::FETCH_ASSOC);

    if (!$rejilla) {
        throw new Exception("Rejilla no encontrada");
    }
    $query_ventas = "SELECT DISTINCT id_venta 
                     FROM public.sist_rejillas_asignaciones 
                     WHERE id_rejilla = :id_rejilla 
                     AND id_venta IS NOT NULL 
                     AND (estado_asignacion IS NULL 
                          OR (estado_asignacion != 'completada' AND estado_asignacion != 'cancelada'))
                     ORDER BY id_venta ASC";

    $stmt_ventas = $conexion->prepare($query_ventas);
    $stmt_ventas->bindParam(':id_rejilla', $id_rejilla, PDO::PARAM_INT);
    $stmt_ventas->execute();
    $ventas = $stmt_ventas->fetchAll(PDO::FETCH_COLUMN);
    $query_productos = "SELECT 
    nombre_producto,
    SUM(COALESCE(cant_uni, 0)) as total_items,
    SUM(COALESCE(peso_asignado, 0.00)) as peso_total,
    COUNT(*) as cantidad_registros
FROM public.sist_rejillas_asignaciones
WHERE id_rejilla = :id_rejilla 
AND (estado_asignacion IS NULL 
     OR (estado_asignacion != 'completada' AND estado_asignacion != 'cancelada'))
AND nombre_producto IS NOT NULL 
AND nombre_producto != ''
GROUP BY nombre_producto
ORDER BY nombre_producto ASC";

    $stmt_productos = $conexion->prepare($query_productos);
    $stmt_productos->bindParam(':id_rejilla', $id_rejilla, PDO::PARAM_INT);
    $stmt_productos->execute();
    $productos_raw = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);

    $debug_query = "SELECT 
                        nombre_producto,
                        cant_uni,
                        peso_asignado,
                        estado_asignacion,
                        estado
                    FROM public.sist_rejillas_asignaciones
                    WHERE id_rejilla = :id_rejilla 
                    AND (estado IS NULL OR estado != 'COMPLETADO')
                    ORDER BY nombre_producto, id";

    $debug_stmt = $conexion->prepare($debug_query);
    $debug_stmt->bindParam(':id_rejilla', $id_rejilla, PDO::PARAM_INT);
    $debug_stmt->execute();
    $debug_data = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);

    $productos = array();
    foreach ($productos_raw as $producto) {
        $producto['tipo_producto'] = determinarTipoProducto($producto['nombre_producto']);
        $productos[] = $producto;
    }

    class MYPDF extends TCPDF
    {
        private $numero_rejilla;
        private $codigos_venta;

        public function __construct($numero_rejilla, $codigos_venta = array())
        {
            parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
            $this->numero_rejilla = $numero_rejilla;
            $this->codigos_venta = $codigos_venta;
        }

        public function Header()
        {
            $this->SetFont('helvetica', 'B', 16);
            $this->SetXY(65, 12);
            $this->Cell(0, 8, 'REJILLA #' . $this->numero_rejilla . ' - PRODUCTOS', 0, 1, 'L');
            if (!empty($this->codigos_venta)) {
                $this->SetFont('helvetica', 'B', 10);
                $this->SetXY(15, 22);
                $this->Cell(0, 6, 'CÓDIGOS DE VENTA: ' . implode(', ', $this->codigos_venta), 0, 1, 'L');
                $linea_y = 32;
            } else {
                $linea_y = 28;
            }
            $this->Line(15, $linea_y, 195, $linea_y);
        }

        public function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'America TNT S.A. - Rejilla #' . $this->numero_rejilla . ' - Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }

    $pdf = new MYPDF($rejilla['numero_rejilla'], $ventas);

    $pdf->SetCreator('America TNT S.A.');
    $pdf->SetAuthor('America TNT S.A.');
    $pdf->SetTitle('Rejilla #' . $rejilla['numero_rejilla'] . ' - Productos');
    $pdf->SetSubject('Listado de Productos por Rejilla');
    $pdf->SetKeywords('TCPDF, PDF, rejilla, productos, ventas');

    $margen_superior = !empty($ventas) ? 36 : 32;
    $pdf->SetMargins(10, $margen_superior, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(true, 25);

    $pdf->AddPage();

    if (empty($productos)) {
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(0, 10, 'Esta rejilla no tiene productos asignados', 1, 1, 'C');
    } else {
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(200, 200, 200);
        $pdf->Cell(110, 8, 'NOMBRE DEL PRODUCTO', 1, 0, 'C', 1);
        $pdf->Cell(30, 8, 'TIPO', 1, 0, 'C', 1);
        $pdf->Cell(25, 8, 'ITEMS', 1, 0, 'C', 1);
        $pdf->Cell(25, 8, 'PESO (kg)', 1, 1, 'C', 1);

        $pdf->SetFont('helvetica', '', 8);
        $fill = false;
        $total_items = 0;
        $total_peso = 0;

        foreach ($productos as $producto) {
            if ($pdf->GetY() > 240) {
                $pdf->AddPage();
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetFillColor(200, 200, 200);
                $pdf->Cell(110, 8, 'NOMBRE DEL PRODUCTO', 1, 0, 'C', 1);
                $pdf->Cell(30, 8, 'TIPO', 1, 0, 'C', 1);
                $pdf->Cell(25, 8, 'ITEMS', 1, 0, 'C', 1);
                $pdf->Cell(25, 8, 'PESO (kg)', 1, 1, 'C', 1);
                $pdf->SetFont('helvetica', '', 8);
            }

            $nombre_producto = $producto['nombre_producto'];
            $pdf->SetFont('helvetica', '', 8);

            $test_height = $pdf->getStringHeight(110, $nombre_producto);
            $cell_height = max(6, $test_height + 1); // Mínimo 6mm + 1mm de padding

            $start_x = $pdf->GetX();
            $start_y = $pdf->GetY();

            $bg_color = $fill ? array(248, 248, 248) : array(255, 255, 255);

            $pdf->SetFillColor($bg_color[0], $bg_color[1], $bg_color[2]);
            $pdf->Rect($start_x, $start_y, 190, $cell_height, 'F');

            $pdf->writeHTMLCell(110, $cell_height, $start_x, $start_y, $nombre_producto, 0, 0, 0, true, 'L', true);

            $pdf->Rect($start_x, $start_y, 110, $cell_height, 'D');

            $pdf->SetXY($start_x + 110, $start_y);
            $pdf->Cell(30, $cell_height, $producto['tipo_producto'], 1, 0, 'C', 0);

            $pdf->SetXY($start_x + 140, $start_y);
            $pdf->Cell(25, $cell_height, number_format($producto['total_items']), 1, 0, 'C', 0);

            $pdf->SetXY($start_x + 165, $start_y);
            $pdf->Cell(25, $cell_height, number_format($producto['peso_total'], 2), 1, 0, 'R', 0);

            $pdf->SetXY($start_x, $start_y + $cell_height);

            $total_items += $producto['total_items'];
            $total_peso += $producto['peso_total'];

            $fill = !$fill;
        }

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(110, 7, 'TOTALES', 1, 0, 'R', 1);
        $pdf->Cell(30, 7, '', 1, 0, 'C', 1);
        $pdf->Cell(25, 7, number_format($total_items), 1, 0, 'C', 1);
        $pdf->Cell(25, 7, number_format($total_peso, 2), 1, 1, 'R', 1);
    }

    $filename = 'Rejilla_' . $rejilla['numero_rejilla'] . '_Productos_' . date('Y-m-d_H-i-s') . '.pdf';
    $pdf->Output($filename, 'I');
    exit();
} catch (Exception $e) {
    header("Content-Type: text/html; charset=utf-8");
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Error - PDF de Rejilla</title>
        <meta charset='UTF-8'>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body>
        <div class='container mt-5'>
            <div class='alert alert-danger'>
                <h4><i class='fas fa-exclamation-triangle'></i> Error al generar el PDF</h4>
                <p><strong>Detalles del error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                <hr>
                <a href='rejillas.php' class='btn btn-primary'>
                    <i class='fas fa-arrow-left'></i> Volver a Rejillas
                </a>
            </div>
        </div>
    </body>
    </html>";
    exit();
}
