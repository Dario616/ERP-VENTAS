<?php
ini_set('memory_limit', '512M');
require_once "../../../config/conexionBD.php";
include "../../../vendor/tecnickcom/tcpdf/tcpdf.php";

date_default_timezone_set('America/Asuncion');

if (!isset($_GET['expedicion']) || empty($_GET['expedicion'])) {
    header("Content-Type: text/html; charset=utf-8");
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Error - PDF de Expedición</title>
        <meta charset='UTF-8'>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body>
        <div class='container mt-5'>
            <div class='alert alert-danger'>
                <h4><i class='fas fa-exclamation-triangle'></i> Error</h4>
                <p>Se requiere especificar el número de expedición para generar el PDF.</p>
                <hr>
                <a href='despacho.php' class='btn btn-primary'>
                    <i class='fas fa-arrow-left'></i> Volver a Expediciones
                </a>
            </div>
        </div>
    </body>
    </html>";
    exit();
}

$numero_expedicion = trim($_GET['expedicion']);

try {
    $query_expedicion = "SELECT 
                            numero_expedicion,
                            fecha_creacion,
                            estado,
                            transportista,
                            conductor,
                            destino,
                            id_rejilla
                         FROM sist_expediciones 
                         WHERE numero_expedicion = :numero_expedicion";

    $stmt_expedicion = $conexion->prepare($query_expedicion);
    $stmt_expedicion->bindParam(':numero_expedicion', $numero_expedicion, PDO::PARAM_STR);
    $stmt_expedicion->execute();
    $expedicion = $stmt_expedicion->fetch(PDO::FETCH_ASSOC);

    if (!$expedicion) {
        throw new Exception("Expedición no encontrada");
    }
    $query_productos = "SELECT 
                            sps.nombre_producto,
                            sps.tipo_producto,
                            sps.metragem, 
                            sps.id_orden_produccion,
                            SUM(sps.bobinas_pacote) as total_bobinas,
                            COUNT(*) as total_items,
                            SUM(sps.peso_liquido) as peso_total,
                            COUNT(DISTINCT sps.cliente) as clientes_unicos,
                            COUNT(CASE WHEN ei.modo_asignacion = 'desconocido_fuera_rejilla' THEN 1 END) as items_fuera_rejilla
                        FROM sist_expedicion_items ei
                        INNER JOIN sist_prod_stock sps ON ei.id_stock = sps.id
                        WHERE ei.numero_expedicion = :numero_expedicion
                        GROUP BY sps.nombre_producto, sps.tipo_producto, sps.metragem, sps.id_orden_produccion
                        ORDER BY sps.id_orden_produccion ASC, sps.nombre_producto ASC";

    $stmt_productos = $conexion->prepare($query_productos);
    $stmt_productos->bindParam(':numero_expedicion', $numero_expedicion, PDO::PARAM_STR);
    $stmt_productos->execute();
    $productos = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);

    function noTieneBobinas($tipo_producto)
    {
        $tipos_sin_bobinas = ['toallitas', 'paños'];
        return in_array(mb_strtolower(trim($tipo_producto), 'UTF-8'), $tipos_sin_bobinas);
    }

    function todosFueraDeRejilla($producto)
    {
        return $producto['total_items'] > 0 && $producto['items_fuera_rejilla'] == $producto['total_items'];
    }

    class MYPDF extends TCPDF
    {
        private $numero_expedicion;
        private $expedicion_info;

        public function __construct($numero_expedicion, $expedicion_info)
        {
            parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
            $this->numero_expedicion = $numero_expedicion;
            $this->expedicion_info = $expedicion_info;
        }

        public function Header()
        {
            $this->SetFont('helvetica', 'B', 16);
            $this->SetXY(15, 12);
            $this->Cell(0, 8, 'EXPEDICIÓN ' . $this->numero_expedicion . ' - PRODUCTOS', 0, 1, 'C');

            $this->SetFont('helvetica', 'B', 12);
            $this->SetXY(15, 22);
            $rejilla_text = 'REJILLA: ' . ($this->expedicion_info['id_rejilla'] ?? 'N/A');
            $this->Cell(0, 6, $rejilla_text, 0, 1, 'L');

            $this->Line(15, 30, 195, 30);
        }

        public function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'America TNT S.A. - Expedición ' . $this->numero_expedicion . ' - Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }

    $pdf = new MYPDF($expedicion['numero_expedicion'], $expedicion);

    $pdf->SetCreator('America TNT S.A.');
    $pdf->SetAuthor('America TNT S.A.');
    $pdf->SetTitle('Expedición ' . $expedicion['numero_expedicion'] . ' - Productos');
    $pdf->SetSubject('Listado de Productos por Expedición');
    $pdf->SetKeywords('TCPDF, PDF, expedición, productos, despacho');

    $pdf->SetMargins(10, 34, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(true, 25);

    $pdf->AddPage();

    if (empty($productos)) {
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(0, 10, 'Esta expedición no tiene productos escaneados', 1, 1, 'C');
    } else {
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(200, 200, 200);
        $pdf->Cell(84, 8, 'NOMBRE DEL PRODUCTO', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'TIPO', 1, 0, 'C', 1);
        $pdf->Cell(15, 8, 'OP', 1, 0, 'C', 1);
        $pdf->Cell(18, 8, 'METRAGEM', 1, 0, 'C', 1);
        $pdf->Cell(18, 8, 'BOBINAS', 1, 0, 'C', 1);
        $pdf->Cell(14, 8, 'ITEMS', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'PESO (kg)', 1, 1, 'C', 1);

        $pdf->SetFont('helvetica', '', 8);
        $fill = false;
        $total_bobinas = 0;
        $total_items = 0;
        $total_peso = 0;
        $total_fuera_rejilla = 0;

        foreach ($productos as $producto) {
            if ($pdf->GetY() > 240) {
                $pdf->AddPage();
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetFillColor(200, 200, 200);
                $pdf->Cell(84, 8, 'NOMBRE DEL PRODUCTO', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'TIPO', 1, 0, 'C', 1);
                $pdf->Cell(15, 8, 'OP', 1, 0, 'C', 1);
                $pdf->Cell(18, 8, 'METRAGEM', 1, 0, 'C', 1);
                $pdf->Cell(18, 8, 'BOBINAS', 1, 0, 'C', 1);
                $pdf->Cell(14, 8, 'ITEMS', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'PESO (kg)', 1, 1, 'C', 1);
                $pdf->SetFont('helvetica', '', 8);
            }

            $todos_fuera_rejilla = todosFueraDeRejilla($producto);
            $nombre_producto = $producto['nombre_producto'];
            $pdf->SetFont('helvetica', '', 8);
            $test_height = $pdf->getStringHeight(84, $nombre_producto);
            $cell_height = max(6, $test_height + 1);
            $start_x = $pdf->GetX();
            $start_y = $pdf->GetY();

            $bg_color = $fill ? array(248, 248, 248) : array(255, 255, 255);

            $pdf->SetFillColor($bg_color[0], $bg_color[1], $bg_color[2]);
            $pdf->Rect($start_x, $start_y, 189, $cell_height, 'F');

            if ($todos_fuera_rejilla) {
                $pdf->SetTextColor(200, 0, 0);
            } else {
                $pdf->SetTextColor(0, 0, 0);
            }

            $pdf->writeHTMLCell(84, $cell_height, $start_x, $start_y, $nombre_producto, 0, 0, 0, true, 'L', true);
            $pdf->Rect($start_x, $start_y, 84, $cell_height, 'D');
            $pdf->SetXY($start_x + 84, $start_y);
            $pdf->Cell(20, $cell_height, $producto['tipo_producto'], 1, 0, 'C', 0);

            $pdf->Cell(15, $cell_height, $producto['id_orden_produccion'] ?? 'N/A', 1, 0, 'C', 0);

            $pdf->Cell(18, $cell_height, $producto['metragem'] ?? 'N/A', 1, 0, 'C', 0);

            $pdf->Cell(18, $cell_height, number_format($producto['total_bobinas']), 1, 0, 'C', 0);

            $pdf->Cell(14, $cell_height, number_format($producto['total_items']), 1, 0, 'C', 0);

            $pdf->Cell(20, $cell_height, number_format($producto['peso_total'], 2), 1, 1, 'R', 0);

            $pdf->SetTextColor(0, 0, 0);

            $pdf->SetXY($start_x, $start_y + $cell_height);

            $total_bobinas += $producto['total_bobinas'];
            $total_items += $producto['total_items'];
            $total_peso += $producto['peso_total'];
            if ($todos_fuera_rejilla) {
                $total_fuera_rejilla += $producto['total_items'];
            }

            $fill = !$fill;
        }

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(84, 7, 'TOTALES', 1, 0, 'R', 1);
        $pdf->Cell(20, 7, '', 1, 0, 'C', 1);
        $pdf->Cell(15, 7, '', 1, 0, 'C', 1);
        $pdf->Cell(18, 7, '', 1, 0, 'C', 1);
        $pdf->Cell(18, 7, number_format($total_bobinas), 1, 0, 'C', 1);
        $pdf->Cell(14, 7, number_format($total_items), 1, 0, 'C', 1);
        $pdf->Cell(20, 7, number_format($total_peso, 2), 1, 1, 'R', 1);

        if ($total_fuera_rejilla > 0) {
            $pdf->SetTextColor(200, 0, 0);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(84, 6, 'ITEMS COMPLETAMENTE', 1, 0, 'R', 1);
            $pdf->Cell(20, 6, 'FUERA DE', 1, 0, 'C', 1);
            $pdf->Cell(15, 6, 'REJILLA', 1, 0, 'C', 1);
            $pdf->Cell(18, 6, '', 1, 0, 'C', 1);
            $pdf->Cell(18, 6, '', 1, 0, 'C', 1);
            $pdf->Cell(14, 6, number_format($total_fuera_rejilla), 1, 0, 'C', 1);
            $pdf->Cell(20, 6, '', 1, 1, 'C', 1);
            $pdf->SetTextColor(0, 0, 0); // Restaurar a negro
        }
    }

    $filename = 'Expedicion_' . $expedicion['numero_expedicion'] . '_Productos_' . date('Y-m-d_H-i-s') . '.pdf';
    $pdf->Output($filename, 'I');
    exit();
} catch (Exception $e) {
    header("Content-Type: text/html; charset=utf-8");
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Error - PDF de Expedición</title>
        <meta charset='UTF-8'>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body>
        <div class='container mt-5'>
            <div class='alert alert-danger'>
                <h4><i class='fas fa-exclamation-triangle'></i> Error al generar el PDF</h4>
                <p><strong>Detalles del error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                <hr>
                <a href='despacho.php' class='btn btn-primary'>
                    <i class='fas fa-arrow-left'></i> Volver a Expediciones
                </a>
            </div>
        </div>
    </body>
    </html>";
    exit();
}
