<?php
ini_set('memory_limit', '512M');
require_once __DIR__ . "/../../config/conexionBD.php";
include "../../vendor/tecnickcom/tcpdf/tcpdf.php";

require_once __DIR__ . '/controllers/OrdenProduccionMaterialController.php';

if (!isset($_GET['id_orden']) || empty($_GET['id_orden'])) {
    header("Location: ./orden-produccion-material.php?error=ID de orden no especificado");
    exit();
}

$id_orden = (int)$_GET['id_orden'];

try {
    $ordenController = new OrdenProduccionMaterialController($conexion);
    $orden = $ordenController->obtenerOrdenParaPDF($id_orden);

    if (!$orden) {
        header("Location: ./orden-produccion-material.php?error=Orden de producción no encontrada");
        exit();
    }

    // Crear una clase personalizada de TCPDF
    class MYPDF extends TCPDF
    {
        public function Header()
        {
            // No header personalizado
        }
    }

    // Crear un nuevo documento PDF
    $pdf = new MYPDF('P');

    // Establecer información del documento
    $pdf->SetCreator('America TNT S.A.');
    $pdf->SetAuthor('America TNT S.A.');
    $pdf->SetTitle('Orden de Producción Material - #' . $orden['id']);
    $pdf->SetSubject('Orden de Producción Material');
    $pdf->SetKeywords('TCPDF, PDF, orden, producción, material');

    // Establecer márgenes
    $pdf->SetMargins(5, 5, 5);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(5);
    $pdf->SetAutoPageBreak(true, 5);

    // Añadir una página
    $pdf->AddPage();

    // Encabezado principal
    $pdf->SetFillColor(52, 152, 219);
    $pdf->Rect(15, 10, 180, 15, 'F');

    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY(17, 13);
    $pdf->Cell(100, 8, 'ORDEN DE PRODUCCIÓN - MATERIAL', 0, 0, 'L');

    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetXY(150, 13);
    $pdf->Cell(40, 8, '#' . $orden['id'], 0, 0, 'R');

    // Reset colores
    $pdf->SetTextColor(0, 0, 0);

    // Información de la empresa
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetXY(15, 28);
    $pdf->Cell(180, 6, 'AMERICA TNT S.A.', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(200, 4, 'Sistema de Gestión de Producción de Materiales', 0, 1, 'C');

    // Línea separadora
    $pdf->Line(15, 40, 195, 40);

    // Información de la orden
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetXY(15, 40);
    $pdf->Cell(180, 8, 'INFORMACIÓN DE LA ORDEN', 0, 1, 'L');

    // Tabla de información principal
    $pdf->SetFont('helvetica', '', 10);
    $y_pos = 48;

    // Primera fila
    $pdf->SetXY(15, $y_pos);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(45, 8, 'Fecha de Orden:', 1, 0, 'L', 1);
    $pdf->Cell(45, 8, $orden['fecha_orden_formateada'], 1, 0, 'L');
    $pdf->Cell(45, 8, 'Estado:', 1, 0, 'L', 1);

    // Colorear el estado
    $color_estado = '';
    switch ($orden['estado']) {
        case 'PENDIENTE':
            $color_estado = [255, 193, 7];
            break;
        case 'EN_PROCESO':
            $color_estado = [23, 162, 184];
            break;
        case 'COMPLETADA':
            $color_estado = [40, 167, 69];
            break;
        case 'CANCELADA':
            $color_estado = [220, 53, 69];
            break;
        default:
            $color_estado = [108, 117, 125];
    }
    $pdf->SetFillColor($color_estado[0], $color_estado[1], $color_estado[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(45, 8, $orden['estado'], 1, 1, 'C', 1);
    $pdf->SetTextColor(0, 0, 0);

    // Segunda fila
    $y_pos += 8;
    $pdf->SetXY(15, $y_pos);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(45, 8, 'Usuario Creación:', 1, 0, 'L', 1);
    $pdf->Cell(45, 8, $orden['usuario_creacion'], 1, 0, 'L');
    $pdf->Cell(45, 8, 'Versión Receta:', 1, 0, 'L', 1);
    $pdf->Cell(45, 8, $orden['version_receta'], 1, 1, 'C');

    // Material a producir
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetXY(15, $y_pos + 8);
    $pdf->Cell(180, 8, 'MATERIAL A PRODUCIR', 0, 1, 'L');

    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetFillColor(52, 152, 219);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY(15, $y_pos + 16);
    $pdf->Cell(180, 12, $orden['materia_prima_desc'], 1, 1, 'C', 1);
    $pdf->SetTextColor(0, 0, 0);

    // Cantidad solicitada
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetFillColor(40, 167, 69);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY(15, $y_pos + 30);
    $cantidad = $orden['cantidad_solicitada'];
    $mostrarCantidad = (fmod($cantidad, 1) == 0)
        ? number_format($cantidad, 0)
        : number_format($cantidad, 3);

    $pdf->Cell(180, 15, 'CANTIDAD: ' . $mostrarCantidad . ' ' . $orden['unidad_medida'], 1, 1, 'C', 1);
    $pdf->SetTextColor(0, 0, 0);

    // Componentes de la receta
    if (!empty($orden['componentes'])) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetXY(15, $y_pos + 45);
        $pdf->Cell(180, 8, 'COMPONENTES DE LA RECETA (Versión ' . $orden['version_receta'] . ')', 0, 1, 'L');

        // Separar componentes principales y extras
        $principales = array_filter($orden['componentes'], function ($c) {
            return !$c['es_materia_extra'];
        });
        $extras = array_filter($orden['componentes'], function ($c) {
            return $c['es_materia_extra'];
        });

        $y_componentes = $y_pos + 54;

        // Componentes principales
        if (!empty($principales)) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY(15, $y_componentes);
            $pdf->SetFillColor(40, 167, 69);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(180, 6, 'COMPONENTES PRINCIPALES (100%)', 1, 1, 'C', 1);
            $pdf->SetTextColor(0, 0, 0);

            $y_componentes += 6;

            // Headers de tabla
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetXY(15, $y_componentes);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(90, 6, 'Componente', 1, 0, 'C', 1);
            $pdf->Cell(45, 6, 'Porcentaje', 1, 0, 'C', 1);
            $pdf->Cell(45, 6, 'Cantidad Utilizada', 1, 0, 'C', 1);

            $y_componentes += 6;

            $pdf->SetFont('helvetica', '', 8);
            foreach ($principales as $componente) {
                $cantidad_requerida =  (floatval(($componente['cantidad_por_kilo']) / 100) * $cantidad);

                $pdf->SetXY(15, $y_componentes);
                $pdf->Cell(90, 5, $componente['componente_desc'], 1, 0, 'L');
                $pdf->Cell(45, 5, number_format($componente['cantidad_por_kilo'], 0) . '%', 1, 0, 'C');
                $pdf->Cell(45, 5, '', 1, 0, 'C');
                $y_componentes += 5;
            }
        }

        // Componentes extras
        if (!empty($extras)) {
            $y_componentes += 5;

            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY(15, $y_componentes);
            $pdf->SetFillColor(255, 193, 7);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(180, 6, 'COMPONENTES EXTRAS', 1, 1, 'C', 1);

            $y_componentes += 6;

            // Headers de tabla
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetXY(15, $y_componentes);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(90, 6, 'Componente Extra', 1, 0, 'C', 1);
            $pdf->Cell(30, 6, 'Cant./KG', 1, 0, 'C', 1);
            $pdf->Cell(30, 6, 'Cantidad Req.', 1, 0, 'C', 1);
            $pdf->Cell(30, 6, 'Unidad', 1, 1, 'C', 1);

            $y_componentes += 6;

            $pdf->SetFont('helvetica', '', 8);
            foreach ($extras as $componente) {
                $cantidad_requerida = $orden['cantidad_solicitada'] * floatval($componente['cantidad_por_kilo']);
                $unidad = $componente['unidad_medida_extra'] ?? 'unidades';

                $pdf->SetXY(15, $y_componentes);
                $pdf->Cell(90, 5, $componente['componente_desc'], 1, 0, 'L');
                $pdf->Cell(30, 5, number_format($componente['cantidad_por_kilo'], 3), 1, 0, 'C');
                $pdf->Cell(30, 5, number_format($cantidad_requerida, 3), 1, 0, 'C');
                $pdf->Cell(30, 5, $unidad, 1, 1, 'C');

                $y_componentes += 5;
            }
        }
    }

    // Observaciones
    if (!empty($orden['observaciones'])) {
        $y_componentes += 5;

        if ($y_componentes > 250) {
            $pdf->AddPage();
            $y_componentes = 30;
        }

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(15, $y_componentes);
        $pdf->Cell(180, 6, 'OBSERVACIONES', 0, 1, 'L');

        $y_componentes += 8;
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY(15, $y_componentes);
        $pdf->MultiCell(180, 5, $orden['observaciones'], 1, 'L');
    }

    // Sección de control de producción
    if ($pdf->GetY() > 240) {
        $pdf->AddPage();
        $y_control = 30;
    } else {
        $y_control = $pdf->GetY() + 15;
    }


    // Generar el PDF
    $pdf->Output('OrdenProduccionMaterial_' . $id_orden . '.pdf', 'I');
    exit();
} catch (Exception $e) {
    header("Location: ./orden-produccion-material.php?error=" . urlencode("Error al generar el PDF: " . $e->getMessage()));
    exit();
}
