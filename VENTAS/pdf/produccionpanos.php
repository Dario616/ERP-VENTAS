<?php
ini_set('memory_limit', '512M');
include "../config/database/conexionBD.php";
include "../vendor/tecnickcom/tcpdf/tcpdf.php";

if (!isset($_GET['id_orden']) || empty($_GET['id_orden'])) {
    header("Location: ../secciones/sectorPcp/produccion.php?error=ID de orden no especificado");
    exit();
}

$id_orden = (int)$_GET['id_orden'];

try {
    // Obtener datos de la orden de producción específica para panos
    $query_orden = "SELECT op.id, op.id_venta, op.fecha_orden, op.estado, op.observaciones,
                    v.cliente, v.id as id_venta,
                    prod.descripcion, prod.tipoproducto, prod.unidadmedida, prod.ncm, prod.instruccion,
                    prod.id_producto as id_producto_pres,
                    
                    -- Datos específicos de Panos  
                    panos.nombre, panos.cantidad_total, panos.color, panos.largura,
                    panos.picotado, panos.cant_panos, panos.unidad, panos.peso,
                    panos.id_producto, panos.id_producto_produccion, panos.gramatura
                    
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
        header("Location: ../secciones/sectorPcp/produccion.php?error=Orden de producción de panos no encontrada");
        exit();
    }

    $orden['instruccion'] = $orden['instruccion'] ?: '';

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
    $pdf = new MYPDF('P');

    // Establecer información del documento
    $pdf->SetCreator('America TNT S.A.');
    $pdf->SetAuthor('America TNT S.A.');
    $pdf->SetTitle('Ordem de Producão - PANOS');
    $pdf->SetSubject('Ordem de Producão PANOS');
    $pdf->SetKeywords('TCPDF, PDF, orden, producción, panos');

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
    $logoPath = '../utils/logo.jpg'; // Ruta corregida
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
    $pdf->Cell(120, 5, 'ORDEN DE PRODUCCION SETOR PANO MULTIUSO', 0, 0, 'L');

    // Tabla de información principal
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetXY(10, 20);
    $pdf->SetFillColor(255, 255, 255);

    // Celdas con anchos personalizados
    $pdf->Cell(63, 6, 'ORDEN DE PRODUCCION Nº', 1, 0, 'R', 1);
    //AQUI AGREGAR EL ID DE ORDEN DE PRODUCCION 
    $pdf->Cell(63, 6, $orden['id'], 1, 0, 'C', 1);
    $pdf->Cell(22, 6, 'CANT:', 1, 0, 'R', 1);
    //AQUI LA CANTIDAD SOLICITADA DEL PRODUCTO  QUE ES CANTIDAD
    $pdf->Cell(21, 6, $orden['cantidad_total'], 1, 0, 'C', 1);
    //AQUI AGREGAR LA UNIDAD DE MEDIDA 
    $pdf->Cell(21, 6, $orden['unidad'], 1, 1, 'C', 1);

    $pdf->Cell(63, 6, 'BASE PARA PROCESAR:', 1, 0, 'R', 1);
    $pdf->SetFont('helvetica', 'B', 6);
    //AUI IRIA EL NOMBRE/DESCRIPCION DEL PRODUCTO
    // Usa esta solución:
    $pdf->SetFont('helvetica', 'B', 6);
    $x = $pdf->GetX();
    $y = $pdf->GetY();

    // Crear la MultiCell para el texto largo
    $pdf->MultiCell(63, 6, $orden['nombre'], 1, 'C', 1, 0, $x, $y);

    // Reposicionar para las siguientes celdas
    $pdf->SetXY($x + 63, $y);
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell(22, 6, 'CANT REAL:', 1, 0, 'R', 1);
    $pdf->Cell(21, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, $orden['unidad'], 1, 1, 'C', 1);

    $pdf->Cell(63, 6, 'CODIGO DE PRODUCTO:', 1, 0, 'R', 1);
    //AQUI IRIA EL ID DEL PRODUCTO QUE SE DEBE BUSCAR BUSCANDO EN LA BASE DE DATOS SIST_VENTAS_PRODUCT ACCEDIENDO AL CAMPO 'ID_PRODUCTO ESPECIFICAMENTE' 
    $pdf->Cell(63, 6, $orden['id_producto_pres'], 1, 0, 'C', 1);
    $pdf->Cell(22, 6, 'COLOR:', 1, 0, 'R', 1);
    //AQUI IRIA EL COLOR 
    $pdf->Cell(21, 6, $orden['color'], 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '-', 1, 1, 'C', 1);

    $pdf->Cell(63, 6, 'TERMINACION:', 1, 0, 'R', 1);
    $pdf->Cell(63, 6, 'PERFORADO', 1, 0, 'C', 1);
    $pdf->Cell(22, 6, 'PICOTADO:', 1, 0, 'R', 1);
    //aqui IRIA PICOTADO
    $pdf->Cell(21, 6, $orden['picotado'], 1, 0, 'C', 1);
    $pdf->Cell(21, 6, 'CM', 1, 1, 'C', 1);

    $pdf->Cell(63, 6, 'LARGURA:', 1, 0, 'R', 1);
    //AQUI IRIA LARGURA
    $pdf->Cell(63, 6, $orden['largura'], 1, 0, 'C', 1);
    $pdf->Cell(22, 6, 'CANT PANOS:', 1, 0, 'R', 1);
    //AQUI IRIA CANTPANOS
    $pdf->Cell(21, 6, $orden['cant_panos'], 1, 0, 'C', 1);
    $pdf->Cell(21, 6, 'UNIDADES', 1, 1, 'C', 1);

    // Encabezados de componentes con texto en dos líneas
    $x = 10;
    $y = $pdf->GetY();
    $altura = 9;

    // Celdas normales
    $pdf->Cell(63, $altura, 'COMPONENTES', 1, 0, 'C', 1);
    $pdf->Cell(63, $altura, 'CANTIDAD TEORICA', 1, 0, 'C', 1);

    // Celdas con texto largo - usar writeHTMLCell
    $pdf->writeHTMLCell(
        22,
        $altura,
        $x + 126,
        $y,
        '<div style="text-align:center; font-weight:bold;">COD.<br>MP</div>',
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

    // Filas de componentes (vacías)
    $pdf->SetFont('helvetica', 'A', 7);
    //quiero que todo lo que esta en cantidad teorica se multiplique por cantidad igual que TECIDO SPL 50 GR 60% PET/40% VISC por ejemplo si se pidio 2 aqui todos tendran 2 , 1 es el valor base
    $pdf->Cell(8, 6, '1', 1, 0, 'R', 1);
    $pdf->Cell(55, 6, 'TECIDO SPL ' . $orden['gramatura'] . ' GR 60% PET/40% VISC', 1, 0, 'C', 1);
    //AQUI EL NUMERO NORMAL ES 2,52 PERO SE MULTIPLICARA POR CANTIDAD
    $pdf->Cell(38, 6, ($orden['peso'] * $orden['cantidad_total']), 1, 0, 'R', 1);
    $pdf->SetFont('helvetica', 'A', 6);
    $pdf->Cell(25, 6, 'KG', 1, 0, 'C', 1);
    $pdf->Cell(22, 6, 'TH001', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 1, 'C', 1);

    // Filas vacías adicionales
    for ($i = 0; $i < 2; $i++) {
        $pdf->Cell(8, 6, '', 1, 0, 'R', 1);
        $pdf->Cell(55, 6, '', 1, 0, 'C', 1);
        $pdf->Cell(38, 6, '', 1, 0, 'R', 1);
        $pdf->Cell(25, 6, '', 1, 0, 'C', 1);
        $pdf->Cell(22, 6, '', 1, 0, 'C', 1);
        $pdf->Cell(21, 6, '', 1, 0, 'C', 1);
        $pdf->Cell(21, 6, '', 1, 1, 'C', 1);
    }

    $pdf->SetFont('helvetica', 'B', 7);

    $pdf->Cell(101, 12, 'PROCEDIMIENTOS REALIZADOS POR:', 1, 0, 'C', 1);
    $pdf->Cell(25, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(22, 12, '', 1, 0, 'R', 1);
    $pdf->Cell(21, 12, 'VERIFI: POR:', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, '', 1, 1, 'C', 1);
    $pdf->SetXY(111, 83);
    $pdf->Cell(25, 6, '', 1, 0, 'C', 1);
    $pdf->SetXY(179, 83);
    $pdf->Cell(21, 6, '', 1, 1, 'C', 1);

    // Sección de INSUMOS
    $x = 10;
    $y = $pdf->GetY();
    $altura = 9;
    $pdf->Cell(101, $altura, 'INSUMOS', 1, 0, 'C', 1);
    $pdf->SetX(111);
    $pdf->Cell(25, $altura, 'CANT. TEORICA', 1, 0, 'C', 1);
    $pdf->SetX(136);
    $pdf->Cell(22, $altura, 'COD.INS', 1, 0, 'C', 1);


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

    $pdf->SetXY($x, $y + $altura);

    $pdf->SetFont('helvetica', 'A', 7);
    $insumos = [
        ['1', 'Embalaje Termocontraible 30 mic x 48 cm', 'IN-1011', 0],
        ['2', 'Tubos de Carton 27mm Ø x 28 cm', 'IN-1019', 1],
        ['3', 'Caja de carton 35x22x28,5 cm onda C', 'IN-1015', 1]
    ];

    foreach ($insumos as $insumo) {
        $pdf->Cell(8, 6, $insumo[0], 1, 0, 'R', 1);
        $pdf->Cell(93, 6, $insumo[1], 1, 0, 'L', 1);
        $pdf->Cell(25, 6, ($insumo[3] * $orden['cantidad_total']), 1, 0, 'C', 1);
        $pdf->Cell(22, 6, $insumo[2], 1, 0, 'C', 1);
        $pdf->Cell(21, 6, '', 1, 0, 'C', 1);
        $pdf->Cell(21, 6, '', 1, 1, 'C', 1);
    }

    $pdf->SetFont('helvetica', 'B', 7);

    // Proceso general
    $pdf->Cell(190, 6, 'PROCESO GENERAL', 1, 1, 'C', 1);
    $pdf->SetFont('helvetica', 'A', 7);

    $procesos = [
        'Verificar limpieza de equipos según POE',
        'Verificar M.P e Insumos',
        'Inicio proceso según caracteristicas (Memoria descriptiva de todo el proceso realizado)',
        'Verificar parametros de calidad para culminar',
        'Envasar y enviar a DPT'
    ];

    for ($i = 0; $i < 5; $i++) {
        $pdf->Cell(8, 6, ($i + 1), 1, 0, 'C', 1);
        $pdf->Cell(182, 6, $procesos[$i], 1, 1, 'L', 1);
    }

    // Control de tiempo
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell(101, 6, 'CONTROL DE TIEMPO', 1, 0, 'C', 1);
    $pdf->Cell(47, 6, 'DIA', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, 'SEMANA', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, 'HORA', 1, 1, 'C', 1);

    $pdf->SetFont('helvetica', 'A', 7);

    $tiempos = ['Inicio proceso', 'Termino proceso', 'Envasado'];
    for ($i = 0; $i < 3; $i++) {
        $pdf->Cell(8, 6, ($i + 1), 1, 0, 'C', 1);
        $pdf->Cell(93, 6, $tiempos[$i], 1, 0, 'C', 1);
        $pdf->Cell(47, 6, '', 1, 0, 'C', 1);
        $pdf->Cell(21, 6, '', 1, 0, 'C', 1);
        $pdf->Cell(21, 6, '', 1, 1, 'C', 1);
    }

    // Propiedades a verificar
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell(63, 6, 'PROPIEDADES A VERIFICAR', 1, 0, 'C', 1);
    $pdf->Cell(38, 6, 'Tecnicas', 1, 0, 'C', 1);
    $pdf->Cell(47, 6, 'Valores Obtenidos', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, 'Valor Minimo', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, 'Valor Maximo', 1, 1, 'C', 1);

    $pdf->SetFont('helvetica', 'A', 7);
    $propiedades = [
        ['1', 'Gramatura', '', '', '48,5', '51'],
        ['2', 'Resistencia MD SPL', 'ABNT NBR 13041:2004', '', '30', '60'],
        ['3', 'Resistencia CD SPL', 'ABNT NBR 13041:2004', '', '8', '25'],
        ['4', 'Resistencia MD Picote', 'ABNT NBR 13041:2004', '', '', ''],
        ['5', 'Color (cumple - no cumple)', '', '', 'Según muestra', '']
    ];

    foreach ($propiedades as $prop) {
        $pdf->Cell(8, 6, $prop[0], 1, 0, 'C', 1);
        $pdf->Cell(55, 6, $prop[1], 1, 0, 'C', 1);
        $pdf->Cell(38, 6, $prop[2], 1, 0, 'C', 1);
        $pdf->Cell(47, 6, $prop[3], 1, 0, 'C', 1);
        $pdf->Cell(21, 6, $prop[4], 1, 0, 'C', 1);
        $pdf->Cell(21, 6, $prop[5], 1, 1, 'C', 1);
    }

    // Firmas y validaciones
    $pdf->Cell(101, 6, 'ENCARGADO DE PRODUCCION', 1, 0, 'C', 1);
    $pdf->Cell(68, 6, 'Firma', 1, 0, 'R', 1);
    $pdf->Cell(21, 6, '', 1, 1, 'R', 1);

    $pdf->Cell(148, 6, '', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, 'LOTE', 1, 0, 'C', 1);
    $pdf->Cell(21, 6, 'VENCIMIENTO', 1, 1, 'C', 1);

    $pdf->SetFont('helvetica', 'B', 7);

    $validaciones = [
        'LOTE LIBERADO POR:',
        'ENCARGADO DE BODEGA:',
        'VERIFICACION ADMINISTRACION:',
    ];

    foreach ($validaciones as $validacion) {
        $pdf->Cell(63, 6, $validacion, 1, 0, 'R', 1);
        $pdf->Cell(38, 6, '', 1, 0, 'R', 1);
        $pdf->Cell(25, 6, '', 1, 0, 'C', 1);
        $pdf->Cell(22, 6, '', 1, 0, 'C', 1);
        $pdf->Cell(21, 6, 'FECHA:', 1, 0, 'C', 1);
        $pdf->Cell(21, 6, ' /      / ', 1, 1, 'C', 1);
    }
    $pdf->Cell(63, 6, 'DATOS ADICIONALES:', 1, 0, 'R', 1);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell(127, 6,   $orden['instruccion'], 1, 0, 'L', 1);


    $pdf->SetXY(111, 200);
    $pdf->Cell(25, 6, '', 1, 0, 'C', 1);

    // Generar el PDF
    $pdf->Output('OrdemProducao_Panos_' . $id_orden . '.pdf', 'I');
    exit();
} catch (Exception $e) {
    header("Location: ../secciones/sectorPcp/produccion.php?error=" . urlencode("Error al generar el PDF de panos: " . $e->getMessage()));
    exit();
}
