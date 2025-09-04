<?php
ini_set('memory_limit', '512M');
require_once __DIR__ . "/../../../config/conexionBD.php";
include "../../../vendor/tecnickcom/tcpdf/tcpdf.php";

if (!isset($_GET['id_orden']) || empty($_GET['id_orden'])) {
    header("Location: ../nueva_orden_manual.php?error=ID de orden no especificado");
    exit();
}

$id_orden = (int)$_GET['id_orden'];

try {
    // Obtener datos de la orden de producción específica para toallitas
    $query_orden = "SELECT op.id, op.id_venta, op.fecha_orden, op.estado, op.observaciones,
                    v.cliente, v.id as id_venta,
                    prod.descripcion, prod.tipoproducto, prod.unidadmedida, prod.ncm, prod.instruccion,
                    prod.id_producto as id_producto_pres,  -- AGREGAR ESTA LÍNEA
                    
                    -- Datos específicos de Toallitas  
                    toal.nombre as nombre_toallitas, toal.cantidad_total as cantidad_toallitas,
                    toal.id_producto, toal.id_producto_produccion
                    
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
        header("Location: ../nueva_orden_manual.php?error=Orden de producción de toallitas no encontrada");
        exit();
    }

    // Crear una clase personalizada de TCPDF
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

    // Crear un nuevo documento PDF
    $pdf = new MYPDF('P'); // o el valor que corresponda
    // Establecer información del documento
    $pdf->SetCreator('America TNT S.A.');
    $pdf->SetAuthor('America TNT S.A.');
    $pdf->SetTitle('Ordem de Producão - Toallitas');
    $pdf->SetSubject('Ordem de Producão Toallitas');
    $pdf->SetKeywords('TCPDF, PDF, orden, producción, toallitas');

    // Establecer márgenes
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(0);
    $pdf->SetAutoPageBreak(true, 10);

    // Añadir una página
    $pdf->AddPage();

    // Definir algunos estilos
    $pdf->SetFont('helvetica', '', 10);

    // Encabezado principal
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect(10, 10, 190, 15, 'F');

    // Insertar logo de AMERICA TNT
    $logoPath = '../../../utils/logo.jpg'; // Ruta corregida
    if (file_exists($logoPath)) {
        // Insertar imagen: (archivo, x, y, ancho, alto, tipo, link, align, resize, dpi, palign, ismask, imgmask, border, fitbox, hidden, fitonpage)
        $pdf->Image($logoPath, 70, 10, 70, 4, '', '', '', true, 300, '', false, false, 0, false, false, false);
    } else {
        // Si no existe el logo, usar texto como respaldo
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(12, 14);
        $pdf->Cell(120, 8, 'AMERICA TNT', 0, 0, 'L');
    }


    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetXY(63, 15);
    $pdf->Cell(120, 5, 'ORDEN DE PRODUCCION SECTOR TOALLITAS', 0, 0, 'L');



    // Tabla de componentes
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetXY(10, 20);
    $pdf->SetFillColor(255, 255, 255);

    // Celdas con anchos personalizados
    $pdf->Cell(63, 6, 'ORDEN DE PRODUCCION Nº', 1, 0, 'R', 1);
    $pdf->Cell(63, 6, $orden['id'], 1, 0, 'C', 1);
    $pdf->Cell(22, 6, 'CANT:', 1, 0, 'R', 1);
    $pdf->Cell(21, 6, $orden['cantidad_toallitas'], 1, 0, 'C', 1);
    $pdf->Cell(21, 6, $orden['unidadmedida'], 1, 1, 'C', 1);


    $pdf->Cell(63, 6, 'BASE PARA PROCESAR:', 1, 0, 'R', 1);
    $pdf->SetFont('helvetica', 'B', 6);
    $pdf->Cell(63, 6, $orden['descripcion'], 1, 0, 'C', 1);
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell(22, 6, '', 1, 0, 'R', 1);
    $pdf->Cell(21, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 1, 'C', 1);

    $pdf->Cell(63, 6, 'CODIGO DE PRODUCTO:', 1, 0, 'R', 1);
    $pdf->Cell(63, 6, $orden['id_producto_pres'], 1, 0, 'C', 1);
    $pdf->Cell(22, 6, '', 1, 0, 'R', 1);
    $pdf->Cell(21, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 1, 'C', 1);



    // Solo cambiar las celdas que necesitan texto en dos líneas
    // Guardar posición inicial
    $x = 10;
    $y = $pdf->GetY();
    $altura = 9;

    // Celdas normales (funcionan bien con Cell)
    $pdf->Cell(63, $altura, 'COMPONENTES', 1, 0, 'C', 1);
    $pdf->Cell(63, $altura, 'CANTIDAD TEORICA', 1, 0, 'C', 1);

    // Celdas con texto largo - usar writeHTMLCell
    $pdf->writeHTMLCell(
        22,
        $altura,
        $x + 126,
        $y,
        '<div style="text-align:center; font-weight:bold;">CANT.<br>REFUGO</div>',
        1,
        0,
        1
    );

    $pdf->writeHTMLCell(
        21,
        $altura,
        $x + 148,
        $y,
        '<div style="text-align:center; font-weight:bold;">CANT. REAL<br>PROCESADA</div>',
        1,
        0,
        1
    );

    $pdf->writeHTMLCell(
        21,
        $altura,
        $x + 169,
        $y,
        '<div style="text-align:center; font-weight:bold;">LOTE<br>M.P</div>',
        1,
        0,
        1
    );

    // Mover cursor al final de la fila
    $pdf->SetXY($x, $y + $altura);

    $pdf->SetFont('helvetica', 'A', 5);
    $pdf->Cell(8, 6, '1', 1, 0, 'R', 1);
    $pdf->Cell(55, 6, 'SPUNLANCE 43 GR 20 CM 80% PET 20% VISC GOFRADO', 1, 0, 'C', 1);
    $pdf->Cell(38, 6, (2.06 * $orden['cantidad_toallitas']), 1, 0, 'R', 1);
    $pdf->SetFont('helvetica', 'A', 6);
    $pdf->Cell(25, 6, 'KG', 1, 0, 'C', 1);
    $pdf->Cell(22, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 1, 'C', 1);

    $pdf->Cell(8, 6, '', 1, 0, 'R', 1);
    $pdf->Cell(55, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(38, 6, '', 1, 0, 'R', 1);
    $pdf->Cell(25, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(22, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 1, 'C', 1);

    $pdf->Cell(8, 6, '', 1, 0, 'R', 1);
    $pdf->Cell(55, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(38, 6, '', 1, 0, 'R', 1);
    $pdf->Cell(25, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(22, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 1, 'C', 1);
    $pdf->SetFont('helvetica', 'B', 7);

    $pdf->Cell(101, 12, 'PROCEDIMIENTOS REALIZADOS POR:', 1, 0, 'C', 1);
    $pdf->Cell(25, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(22, 12, '', 1, 0, 'R', 1);
    $pdf->Cell(21, 12, 'VERIFI: POR:', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 1, 'C', 1);
    $pdf->SetXY(111, 71);
    $pdf->Cell(25, 6, '', 1, 0, 'C', 1);
    $pdf->SetXY(179, 71);
    $pdf->Cell(21, 6, '', 1, 1, 'C', 1);

    // Solo cambiar las celdas que necesitan texto en dos líneas
    // Guardar posición inicial
    $x = 10;
    $y = $pdf->GetY();
    $altura = 9;

    // Celdas normales (funcionan bien con Cell)
    $pdf->Cell(63, $altura, 'INSUMOS', 1, 0, 'C', 1);
    $pdf->Cell(38, $altura, 'CANTIDAD TEORICA', 1, 0, 'C', 1);
    $pdf->Cell(25, $altura, 'COD.INS', 1, 0, 'C', 1);


    // Celdas con texto largo - usar writeHTMLCell
    $pdf->writeHTMLCell(
        22,
        $altura,
        $x + 126,
        $y,
        '<div style="text-align:center; font-weight:bold;">CANT.<br>REFUGO</div>',
        1,
        0,
        1
    );

    $pdf->writeHTMLCell(
        21,
        $altura,
        $x + 148,
        $y,
        '<div style="text-align:center; font-weight:bold;">CANT. REAL<br>PROCESADA</div>',
        1,
        0,
        1
    );

    $pdf->writeHTMLCell(
        21,
        $altura,
        $x + 169,
        $y,
        '<div style="text-align:center; font-weight:bold;">LOTE<br>INS.</div>',
        1,
        0,
        1
    );

    // Mover cursor al final de la fila
    $pdf->SetXY($x, $y + $altura);

    $pdf->SetFont('helvetica', 'A', 7);
    $pdf->Cell(8, 6, '1', 1, 0, 'R', 1);
    $pdf->Cell(55, 6, 'Embalaje CottonFresh', 1, 0, 'L', 1);
    $pdf->Cell(38, 6, (16 * $orden['cantidad_toallitas']), 1, 0, 'C', 1);
    $pdf->Cell(25, 6, 'IN-1011', 1, 0, 'C', 1);
    $pdf->Cell(22, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 1, 'C', 1);


    $pdf->Cell(8, 6, '2', 1, 0, 'R', 1);
    $pdf->Cell(55, 6, 'Etiqueta Autoadesiva', 1, 0, 'L', 1);
    $pdf->Cell(38, 6, (16 * $orden['cantidad_toallitas']), 1, 0, 'C', 1);
    $pdf->Cell(25, 6, 'IN-1019', 1, 0, 'C', 1);
    $pdf->Cell(22, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 1, 'C', 1);


    $pdf->Cell(8, 6, '3', 1, 0, 'R', 1);
    $pdf->Cell(55, 6, 'Caja de carton 35x22x28,5 cm onda C', 1, 0, 'L', 1);
    $pdf->Cell(38, 6, (1 * $orden['cantidad_toallitas']), 1, 0, 'C', 1);
    $pdf->Cell(25, 6, 'IN-1015', 1, 0, 'C', 1);
    $pdf->Cell(22, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 1, 'C', 1);

    $pdf->SetFont('helvetica', 'B', 7);

    $pdf->Cell(190, 6, 'PROCESO GENERAL', 1, 1, 'C', 1);
    $pdf->SetFont('helvetica', 'A', 7);
    $pdf->Cell(8, 6, '1', 1, 0, 'C', 1);
    $pdf->Cell(182, 6, 'Verificar limpieza de equipos según POE', 1, 1, 'L', 1);

    $pdf->Cell(8, 6, '2', 1, 0, 'C', 1);
    $pdf->Cell(182, 6, 'Verificar M.P e Insumos', 1, 1, 'L', 1);

    $pdf->Cell(8, 6, '3', 1, 0, 'C', 1);
    $pdf->Cell(182, 6, 'Inicio proceso según caracteristicas (Memoria descriptiva de todo el proceso realizado)', 1, 1, 'L', 1);

    $pdf->Cell(8, 6, '4', 1, 0, 'C', 1);
    $pdf->Cell(182, 6, 'Verificar parametros de calidad para culminar', 1, 1, 'L', 1);

    $pdf->Cell(8, 6, '5', 1, 0, 'C', 1);
    $pdf->Cell(182, 6, 'Envasar y enviar a DPT', 1, 1, 'L', 1);

    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell(101, 6, 'CONTROL DE TIEMPO', 1, 0, 'C', 1);
    $pdf->Cell(47, 6, 'DIA', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, 'SEMANA', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, 'HORA', 1, 1, 'C', 1);

    $pdf->SetFont('helvetica', 'A', 7);

    $pdf->Cell(8, 6, '1', 1, 0, 'C', 1);
    $pdf->Cell(93, 6, 'Inicio proceso', 1, 0, 'C', 1);
    $pdf->Cell(47, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 1, 'C', 1);


    $pdf->Cell(8, 6, '2', 1, 0, 'C', 1);
    $pdf->Cell(93, 6, 'Termino proceso', 1, 0, 'C', 1);
    $pdf->Cell(47, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 1, 'C', 1);

    $pdf->Cell(8, 6, '3', 1, 0, 'C', 1);
    $pdf->Cell(93, 6, 'Envasado', 1, 0, 'C', 1);
    $pdf->Cell(47, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 1, 'C', 1);

    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell(63, 6, 'PROPIEDADES A VERIFICAR', 1, 0, 'C', 1);
    $pdf->Cell(38, 6, 'Tecnicas', 1, 0, 'C', 1);
    $pdf->Cell(47, 6, 'Valores Obtenidos', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, 'Valor Minimo', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, 'Valor Maximo', 1, 1, 'C', 1);

    $pdf->SetFont('helvetica', 'A', 7);
    $pdf->Cell(8, 6, '1', 1, 0, 'C', 1);
    $pdf->Cell(55, 6, 'Gramatura', 1, 0, 'C', 1);
    $pdf->Cell(38, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(47, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '41,28', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '44', 1, 1, 'C', 1);

    $pdf->Cell(8, 6, '2', 1, 0, 'C', 1);
    $pdf->Cell(55, 6, 'Resistencia MD SPL', 1, 0, 'C', 1);
    $pdf->Cell(38, 6, 'ABNT NBR 13041:2004', 1, 0, 'C', 1);
    $pdf->Cell(47, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '85', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '155,5', 1, 1, 'C', 1);

    $pdf->Cell(8, 6, '3', 1, 0, 'C', 1);
    $pdf->Cell(55, 6, 'Resistencia CD SPL', 1, 0, 'C', 1);
    $pdf->Cell(38, 6, 'ABNT NBR 13041:2004', 1, 0, 'C', 1);
    $pdf->Cell(47, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '18', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '30,6', 1, 1, 'C', 1);

    $pdf->Cell(8, 6, '4', 1, 0, 'C', 1);
    $pdf->Cell(55, 6, 'Color (cumple - no cumple)', 1, 0, 'C', 1);
    $pdf->Cell(38, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(47, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(42, 6, 'Según muestra', 1, 1, 'C', 1);

    $pdf->Cell(101, 6, 'ENCARGADO DE PRODUCCION', 1, 0, 'C', 1);
    $pdf->Cell(68, 6, 'Firma', 1, 0, 'R', 1);
    $pdf->Cell(21, 6, '', 1, 1, 'R', 1);

    $pdf->Cell(148, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, 'LOTE', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, 'VENCIMIENTO', 1, 1, 'C', 1);

    $pdf->SetFont('helvetica', 'B', 7);

    $pdf->Cell(63, 6, 'LOTE LIBERADO POR:', 1, 0, 'R', 1);
    $pdf->Cell(38, 6, '', 1, 0, 'R', 1);
    $pdf->Cell(25, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(22, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, 'FECHA:', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, ' /      / ', 1, 1, 'C', 1);



    $pdf->Cell(63, 6, 'ENCARGADO DE BODEGA:', 1, 0, 'R', 1);
    $pdf->Cell(38, 6, '', 1, 0, 'R', 1);
    $pdf->Cell(25, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(22, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, 'FECHA:', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, ' /      / ', 1, 1, 'C', 1);



    $pdf->Cell(63, 6, 'VERIFICACION ADMINISTRACION:', 1, 0, 'R', 1);
    $pdf->Cell(38, 6, '', 1, 0, 'R', 1);
    $pdf->Cell(25, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(22, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, 'FECHA:', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, ' /      / ', 1, 1, 'C', 1);

    $pdf->Cell(63, 6, 'DATOS ADICIONALES:', 1, 0, 'R', 1);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell(127, 6,   $orden['instruccion'], 1, 0, 'L', 1);






    // Generar el PDF
    $pdf->Output('OrdemProducao_Toallitas_' . $id_orden . '.pdf', 'I');
    exit();
} catch (Exception $e) {
    header("Location: ../nueva_orden_manual.php?error=" . urlencode("Error al generar el PDF de toallitas: " . $e->getMessage()));
    exit();
}
