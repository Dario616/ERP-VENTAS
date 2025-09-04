<?php
ini_set('memory_limit', '512M');
require_once __DIR__ . "/../../config/conexionBD.php";
include "../../vendor/tecnickcom/tcpdf/tcpdf.php";

require_once __DIR__ . '/services/RecetasService.php';

if (!isset($_GET['id_tipo_producto']) || empty($_GET['id_tipo_producto'])) {
    header("Location: ./recetas.php?error=ID de tipo de producto no especificado");
    exit();
}

$id_tipo_producto = (int)$_GET['id_tipo_producto'];

try {
    $recetasService = new RecetasService($conexion);
    $detalle = $recetasService->obtenerDetalleRecetaTipo($id_tipo_producto);

    if (!$detalle || !$detalle['success']) {
        header("Location: ./recetas.php?error=Tipo de producto no encontrado");
        exit();
    }

    // Crear una clase personalizada de TCPDF
    class MYPDF extends TCPDF
    {
        public function Header()
        {
            // No header personalizado
        }

        public function Footer()
        {
            $this->SetY(-10);
            $this->SetFont('helvetica', 'I', 7);
            $this->SetTextColor(128, 128, 128);
            $this->Cell(0, 5, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }

    // Crear un nuevo documento PDF
    $pdf = new MYPDF('P', 'mm');

    // Establecer información del documento
    $pdf->SetCreator('America TNT S.A.');
    $pdf->SetAuthor('America TNT S.A.');
    $pdf->SetTitle('Resumen de Recetas - ' . $detalle['tipo_producto']);
    $pdf->SetSubject('Resumen de Recetas');
    $pdf->SetKeywords('TCPDF, PDF, recetas, materias primas');

    // Establecer márgenes más pequeños para aprovechar mejor el espacio
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(true, 15);

    // Añadir una página
    $pdf->AddPage();

    $ancho_pagina = 190; // 210mm - 20mm de márgenes

    // Encabezado principal más compacto
    $pdf->SetFillColor(52, 152, 219);
    $pdf->Rect(10, 10, $ancho_pagina, 15, 'F');

    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY(10, 13);
    $pdf->Cell($ancho_pagina, 9, 'RESUMEN DE RECETAS', 0, 0, 'C');

    // Reset colores
    $pdf->SetTextColor(0, 0, 0);

    // Información de la empresa más compacta
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetXY(10, 28);
    $pdf->Cell($ancho_pagina, 5, 'AMERICA TNT S.A.', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell($ancho_pagina + 10, 4, 'Sistema de Gestión de Recetas de Materiales', 0, 1, 'C');

    // Información del producto en una sola línea
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetXY(10, 40);
    $pdf->Cell($ancho_pagina, 6, 'TIPO PRODUCTO: ' . strtoupper($detalle['tipo_producto']), 0, 1, 'C');

    // Información general en formato compacto (2x2)
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY(10, 48);
    $ancho_celda = $ancho_pagina / 2;

    $pdf->Cell($ancho_celda, 5, 'ID: #' . $detalle['id_tipo_producto'], 1, 0, 'L');
    $pdf->Cell($ancho_celda, 5, 'Versiones: ' . $detalle['total_versiones'], 1, 1, 'L');

    $pdf->SetX(10);
    $pdf->Cell($ancho_celda, 5, 'Generado: ' . date('d/m/Y H:i'), 1, 0, 'L');
    $pdf->Cell($ancho_celda, 5, 'Total Materias: ' . $detalle['total_materias_general'], 1, 1, 'L');

    $y_actual = 60;

    // Verificar si hay versiones
    if (empty($detalle['versiones'])) {
        $pdf->SetXY(10, $y_actual);
        $pdf->SetFont('helvetica', 'I', 11);
        $pdf->SetTextColor(220, 53, 69);
        $pdf->Cell($ancho_pagina, 12, 'No hay recetas configuradas para este producto', 1, 1, 'C');
    } else {
        // Procesar cada versión de forma más compacta
        foreach ($detalle['versiones'] as $index => $version) {
            // Verificar si necesitamos nueva página
            if ($y_actual > 250) {
                $pdf->AddPage();
                $y_actual = 20;
            }

            // Encabezado de versión más compacto
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(40, 167, 69);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY(10, $y_actual);

            $nombre_version = $version['nombre_receta'] ?? 'Receta Principal';
            $pdf->Cell($ancho_pagina, 6, 'VERSIÓN ' . $version['version'] . ': ' . strtoupper($nombre_version), 1, 1, 'C', 1);
            $y_actual += 6;

            // Información de la versión en una sola fila
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY(10, $y_actual);

            $estado_completitud = $version['es_completo'] ? 'COMPLETA (100%)' : 'INCOMPLETA (' . number_format($version['total_cantidad'], 1) . '%)';
            $color_estado = $version['es_completo'] ? [40, 167, 69] : [255, 193, 7];

            $ancho_estado = $ancho_pagina / 3;
            $pdf->Cell($ancho_estado, 5, 'Estado:', 1, 0, 'L');
            $pdf->SetFillColor($color_estado[0], $color_estado[1], $color_estado[2]);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell($ancho_estado, 5, $estado_completitud, 1, 0, 'C', 1);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell($ancho_estado, 5, 'Principales: ' . count($version['materias_principales']) . ' | Extras: ' . count($version['materias_extras']), 1, 1, 'C');
            $y_actual += 6;

            // COMBINAR MATERIAS PRINCIPALES Y EXTRAS EN UNA SOLA TABLA
            $tiene_principales = !empty($version['materias_principales']);
            $tiene_extras = !empty($version['materias_extras']);

            if ($tiene_principales || $tiene_extras) {
                // Verificar espacio disponible
                $total_materias = count($version['materias_principales']) + count($version['materias_extras']);
                $espacio_necesario = 12 + ($total_materias * 4);
                if ($y_actual + $espacio_necesario > 270) {
                    $pdf->AddPage();
                    $y_actual = 20;
                }

                // Header combinado de tabla
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetFillColor(240, 240, 240);
                $pdf->SetXY(10, $y_actual);
                $pdf->Cell(90, 6, 'Materia Prima', 1, 0, 'C', 1);
                $pdf->Cell(25, 6, 'Tipo', 1, 0, 'C', 1);
                $pdf->Cell(30, 6, 'Cantidad', 1, 0, 'C', 1);
                $pdf->Cell(20, 6, 'Unidad', 1, 0, 'C', 1);
                $pdf->Cell(25, 6, 'Descripción', 1, 1, 'C', 1);
                $y_actual += 6;

                // Datos de materias principales
                $pdf->SetFont('helvetica', '', 7);
                if ($tiene_principales) {
                    foreach ($version['materias_principales'] as $materia) {
                        $pdf->SetXY(10, $y_actual);
                        $pdf->SetFillColor(230, 245, 230); // Verde claro para principales
                        $pdf->Cell(90, 4, substr($materia['descripcion'], 0, 45), 1, 0, 'L', 1);
                        $pdf->Cell(25, 4, 'PRINCIPAL', 1, 0, 'C');
                        $pdf->Cell(30, 4, number_format($materia['cantidad_por_kilo'], 1), 1, 0, 'C');
                        $pdf->Cell(20, 4, '%', 1, 0, 'C');
                        $descripcion_receta = !empty($materia['descripcion_receta']) ? substr($materia['descripcion_receta'], 0, 12) : '-';
                        $pdf->Cell(25, 4, $descripcion_receta, 1, 1, 'L');
                        $y_actual += 4;
                    }
                }

                // Datos de materias extras
                if ($tiene_extras) {
                    foreach ($version['materias_extras'] as $materia) {
                        $pdf->SetXY(10, $y_actual);
                        $pdf->SetFillColor(255, 250, 205); // Amarillo claro para extras
                        $pdf->Cell(90, 4, substr($materia['descripcion'], 0, 45), 1, 0, 'L', 1);
                        $pdf->Cell(25, 4, 'EXTRA', 1, 0, 'C');
                        $pdf->Cell(30, 4, number_format($materia['cantidad_por_kilo'], 2), 1, 0, 'C');
                        $pdf->Cell(20, 4, substr($materia['unidad_medida'], 0, 8), 1, 0, 'C');
                        $descripcion_receta = !empty($materia['descripcion_receta']) ? substr($materia['descripcion_receta'], 0, 12) : '-';
                        $pdf->Cell(25, 4, $descripcion_receta, 1, 1, 'L');
                        $y_actual += 4;
                    }
                }

                $y_actual += 2;
            }

            // Separador más pequeño entre versiones
            if ($index < count($detalle['versiones']) - 1) {
                $y_actual += 2;
                $pdf->Line(10, $y_actual, 200, $y_actual);
                $y_actual += 4;
            }
        }
    }

    // Pie de página informativo más compacto
    if ($pdf->GetY() > 265) {
        $pdf->AddPage();
        $y_final = 20;
    } else {
        $y_final = $pdf->GetY() + 5;
    }


    // Generar el PDF
    $nombre_archivo = 'Recetas_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $detalle['tipo_producto']) . '_' . date('Ymd_His') . '.pdf';
    $pdf->Output($nombre_archivo, 'I');
    exit();
} catch (Exception $e) {
    error_log("Error generando PDF de recetas: " . $e->getMessage());
    header("Location: ./recetas.php?error=" . urlencode("Error al generar el PDF: " . $e->getMessage()));
    exit();
}
