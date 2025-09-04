<?php

ini_set('memory_limit', '512M');
include "../config/database/conexionBD.php";

require_once '../vendor/autoload.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php?mensaje=Error: ID de presupuesto no especificado");
    exit();
}

$id_presupuesto = (int)$_GET['id'];

function normalizarTipoProducto($tipo)
{
    $tipoLimpio = strtoupper(trim($tipo));
    $tipoLimpio = str_replace(['Ñ', 'ñ'], 'N', $tipoLimpio);
    $tipoLimpio = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $tipoLimpio);

    return $tipoLimpio;
}

function esTipoToallitasOPanos($tipo)
{
    $tipoNormalizado = normalizarTipoProducto($tipo);
    return in_array($tipoNormalizado, ['TOALLITAS', 'PANOS', 'PAÑOS', 'PAÑUELO', 'PANUELO']);
}

function deberMostrarSelloAprobacion($estado)
{
    $estadosAprobados = [
        'Enviado a PCP',
        'Finalizado',
        'Finalizado Manualmente',
        'En Producción'
    ];

    return in_array(trim($estado), $estadosAprobados);
}

function generarPDFPresupuesto($id_presupuesto, $conexion)
{
    try {
        error_log("=== INICIANDO DEBUG DE TIPOS DE PRODUCTOS ===");
        $debug_query = "SELECT DISTINCT tipo, 
                       CONCAT('[', tipo, ']') as tipo_con_brackets,
                       LENGTH(tipo) as longitud,
                       ASCII(SUBSTRING(tipo, 1, 1)) as primer_caracter
                       FROM public.sist_ventas_productos 
                       WHERE UPPER(tipo) LIKE '%PAÑO%' OR UPPER(tipo) LIKE '%PANO%' OR UPPER(tipo) LIKE '%TOALLITA%'";
        $stmt_debug = $conexion->prepare($debug_query);
        $stmt_debug->execute();
        $tipos_debug = $stmt_debug->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tipos_debug as $tipo_debug) {
            error_log("TIPO EN BD: " . $tipo_debug['tipo_con_brackets'] .
                " (longitud: " . $tipo_debug['longitud'] .
                ", primer char ASCII: " . $tipo_debug['primer_caracter'] . ")");
        }

        $query_presupuesto = "SELECT v.*, u.nombre as nombre_usuario, v.proforma, v.descripcion, v.estado
                              FROM public.sist_ventas_presupuesto v 
                              LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id 
                              WHERE v.id = :id";
        $stmt_presupuesto = $conexion->prepare($query_presupuesto);
        $stmt_presupuesto->bindParam(':id', $id_presupuesto, PDO::PARAM_INT);
        $stmt_presupuesto->execute();
        $presupuesto = $stmt_presupuesto->fetch(PDO::FETCH_ASSOC);

        if (!$presupuesto) {
            header("Location: index.php?mensaje=Error: Presupuesto no encontrado");
            exit();
        }

        $mostrarSelloAprobacion = deberMostrarSelloAprobacion($presupuesto['estado'] ?? '');

        $numeroProforma = isset($presupuesto['proforma']) ? $presupuesto['proforma'] : 0;
        $anioActual = date('y');
        $proformaFormateada = $anioActual . '/' . str_pad($numeroProforma, 6, '0', STR_PAD_LEFT);

        $query_cliente = "SELECT * FROM public.sist_ventas_clientes 
                          WHERE nombre = :nombre_cliente LIMIT 1";
        $stmt_cliente = $conexion->prepare($query_cliente);
        $stmt_cliente->bindParam(':nombre_cliente', $presupuesto['cliente'], PDO::PARAM_STR);
        $stmt_cliente->execute();
        $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

        if (isset($presupuesto['fecha_venta']) && !empty($presupuesto['fecha_venta'])) {
            $fecha_venta = $presupuesto['fecha_venta'];
            $fecha_hora_actual = date('d/m/Y', strtotime($fecha_venta));
            $fecha_presupuesto = date('d/m/Y', strtotime($fecha_venta));
            $fecha_para_proforma = date('d/m/Y', strtotime($fecha_venta));
        } else {
            date_default_timezone_set('America/Asuncion');
            $fecha_hora_actual = date('d/m/Y H:i:s');
            $fecha_presupuesto = date('d/m/Y');
            $fecha_para_proforma = date('d/m/Y');
        }

        $query_productos = "SELECT pp.*, p.cantidad as peso_por_bobina, p.tipo 
                    FROM public.sist_ventas_pres_product pp 
                    LEFT JOIN public.sist_ventas_productos p ON pp.id_producto = p.id 
                    WHERE pp.id_presupuesto = :id_presupuesto 
                    ORDER BY pp.ncm ASC";
        $stmt_productos = $conexion->prepare($query_productos);
        $stmt_productos->bindParam(':id_presupuesto', $id_presupuesto, PDO::PARAM_INT);
        $stmt_productos->execute();
        $productos = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);

        $debug_productos_query = "SELECT pp.*, p.cantidad as peso_por_bobina, p.tipo,
                        TRIM(p.tipo) as tipo_limpio,
                        LENGTH(p.tipo) as longitud_tipo,
                        ASCII(SUBSTRING(p.tipo, 1, 1)) as primer_caracter
                        FROM public.sist_ventas_pres_product pp
                        LEFT JOIN public.sist_ventas_productos p ON pp.id_producto = p.id
                        WHERE pp.id_presupuesto = :id_presupuesto
                        ORDER BY pp.id";

        $stmt_debug_prod = $conexion->prepare($debug_productos_query);
        $stmt_debug_prod->bindParam(':id_presupuesto', $id_presupuesto, PDO::PARAM_INT);
        $stmt_debug_prod->execute();
        $productos_debug = $stmt_debug_prod->fetchAll(PDO::FETCH_ASSOC);

        error_log("=== PRODUCTOS EN ESTE PRESUPUESTO ===");
        foreach ($productos_debug as $prod_debug) {
            error_log("ID: " . $prod_debug['id_producto'] .
                " | Tipo: '" . $prod_debug['tipo'] . "'" .
                " | Tipo limpio: '" . $prod_debug['tipo_limpio'] . "'" .
                " | Longitud: " . $prod_debug['longitud_tipo'] .
                " | Primer carácter ASCII: " . $prod_debug['primer_caracter'] .
                " | Normalizado: '" . normalizarTipoProducto($prod_debug['tipo']) . "'" .
                " | Es toallitas/paños: " . (esTipoToallitasOPanos($prod_debug['tipo']) ? 'SÍ' : 'NO'));
        }

        $pesoLiquidoTotal = 0;
        foreach ($productos as $producto) {
            $pesoProducto = 0;
            $tipoNormalizado = normalizarTipoProducto($producto['tipo']);

            if (in_array($tipoNormalizado, ['TNT', 'LAMINADORA', 'SPUNLACE'])) {
                if (strtoupper($producto['unidadmedida']) === 'METROS') {
                    $metrosTotales = (float)$producto['cantidad'];
                    $pesoPorBobina = (float)$producto['peso_por_bobina'];
                    preg_match('/(\d+)\s*metros?/i', $producto['descripcion'], $matches);
                    $metrosPorBobina = isset($matches[1]) ? (float)$matches[1] : 1;
                    $cantidadBobinas = ($metrosPorBobina > 0) ? $metrosTotales / $metrosPorBobina : 0;
                    $pesoProducto = $cantidadBobinas * $pesoPorBobina;
                } elseif (strtoupper($producto['unidadmedida']) === 'UNIDAD') {
                    $cantidadBobinas = (float)$producto['cantidad'];
                    $pesoPorBobina = (float)$producto['peso_por_bobina'];
                    $pesoProducto = $cantidadBobinas * $pesoPorBobina;
                } else {
                    $pesoProducto = (float)$producto['cantidad'];
                }
            } elseif (esTipoToallitasOPanos($producto['tipo'])) {
                error_log("=== CÁLCULO DE PESO ===");
                error_log("Producto ID: " . $producto['id_producto']);
                error_log("Tipo original: '" . $producto['tipo'] . "'");
                error_log("Tipo normalizado: '" . $tipoNormalizado . "'");
                error_log("Función detecta toallitas/paños: " . (esTipoToallitasOPanos($producto['tipo']) ? 'SÍ' : 'NO'));
                error_log("Cantidad del presupuesto: " . $producto['cantidad']);
                error_log("Peso por bobina/caja desde BD: " . $producto['peso_por_bobina']);

                $cantidadCajas = (float)$producto['cantidad'];
                $pesoPorCaja = (float)$producto['peso_por_bobina'];
                $pesoProducto = $cantidadCajas * $pesoPorCaja;

                error_log("Peso calculado: " . $pesoProducto);
                error_log("========================");
            } else {
                $pesoProducto = (float)$producto['cantidad'];
            }

            $pesoLiquidoTotal += $pesoProducto;
        }

        error_log("PESO LÍQUIDO TOTAL CALCULADO: " . $pesoLiquidoTotal);

        $pesoBrutoTotal = $pesoLiquidoTotal * 1.05;

        $empresa = [
            'nombre' => 'AMERICA TNT SOCIEDAD ANONIMA',
            'direccion' => 'PARQUE INDUSTRIAL SANTA MONICA ALTO PARANA HERNANDARIAS (MUNICIPIO)',
            'email' => 'contato@americatnt.com',
            'ruc' => '80094986-2'
        ];

        $totalConIva = $presupuesto['monto_total'];
        $porcentajeIva = isset($presupuesto['iva']) ? $presupuesto['iva'] : 0;

        if ($porcentajeIva > 0) {
            $subtotal = $totalConIva / 1.10;
            $montoIva = $totalConIva - $subtotal;
        } else {
            $subtotal = $totalConIva;
            $montoIva = 0;
        }

        $infoAdicional = [
            'tipooperacion' => $presupuesto['tipooperacion'] ?? '',
            'exportador' => $presupuesto['exportador'] ?? '',
            'instruccionespago' => $presupuesto['instruccionespago'] ?? '',
            'pesoneto' => $presupuesto['pesoneto'] ?? '',
            'transportadora' => $presupuesto['transportadora'] ?? '',
            'tipoflete' => $presupuesto['tipoflete'] ?? '',
            'terminoembarque' => $presupuesto['terminoembarque'] ?? '',
            'pesobruto' => $presupuesto['pesobruto'] ?? ''
        ];

        $moneda = $presupuesto['moneda'];
        $monedaISO = 'USD';

        if (stripos($moneda, 'dolar') !== false || stripos($moneda, 'dólar') !== false || stripos($moneda, 'dollar') !== false) {
            $monedaISO = 'USD';
            $nombreMoneda = 'DÓLAR AMERICANO';
        } elseif (stripos($moneda, 'real') !== false || stripos($moneda, 'brasil') !== false || stripos($moneda, 'brasileño') !== false) {
            $monedaISO = 'BRL';
            $nombreMoneda = 'REAL BRASILEÑO';
        } elseif (stripos($moneda, 'guarani') !== false || stripos($moneda, 'guaraní') !== false) {
            $monedaISO = 'PYG';
            $nombreMoneda = 'GUARANÍ PARAGUAYO';
        } else {
            $nombreMoneda = strtoupper($moneda);
        }

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('chroot', __DIR__);
        $options->set('defaultFont', 'Helvetica');
        $options->set('tempDir', sys_get_temp_dir());
        $options->set('fontDir', __DIR__ . '/fonts/');
        $options->set('fontCache', __DIR__ . '/fonts/');
        $options->set('defaultMediaType', 'print');
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');

        $productosEnPagina = 11;
        $totalPaginas = ceil(count($productos) / $productosEnPagina);

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <title>Proforma ' . $proformaFormateada . '</title>
            <style>
                @page {
                    margin: 10mm;
                    padding: 0;
                }
                body {
                    font-family: Helvetica, Arial, sans-serif;
                    margin: 0;
                    padding: 0;
                    font-size: 10pt;
                    line-height: 1.2;
                    position: relative;
                }
                .page-container {
    position: absolute;
    width: 100%;
    height: 100%;
    box-sizing: border-box;
    border: 2px solid #000;
}
                .header {
                    text-align: center;
                    margin-bottom: 1mm;
                    margin-top: 2mm;
                    position: relative;
                }
                .header h1 {
                    font-size: 12pt;
                    font-weight: bold;
                    margin: ;
                    padding: 0;
                }
                .header p {
                    font-size: 7pt;
                    margin: 1mm 0;
                }
                .proforma-box {
                    position: absolute;
                    top: -8.5;
                    right: 0;
                    width: 22mm;
                    height: 18.5mm;
                    border: 1px solid #000;
                    background-color: #f0f0f0;
                    text-align: center;
                    padding-top: 1mm;
                }
                .proforma-box .title {
                    font-weight: bold;
                    font-size: 8pt;
                }
                .proforma-box .number {
                    font-weight: bold;
                    font-size: 10pt;
                    margin-top: 1mm;
                }
                .proforma-box .date,
                .proforma-box .page {
                    font-size: 7pt;
                    margin-top: 0.5mm;
                }
                
                .approval-stamp {
                    position: absolute;
                    top: -3mm;
                    left: -5mm;
                    width: 45mm;
                    height: 25mm;
                    border: 3px solid #025302ff;
                    background-color: rgba(34, 139, 34, 0.1);
                    border-radius: 8px;
                    transform: rotate(-15deg);
                    z-index: 10;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    text-align: center;
                }
                .approval-stamp .stamp-text {
                    color: #004100ff;
                    font-weight: bold;
                    font-size: 8pt;
                    line-height: 1.1;
                    text-transform: uppercase;
                }
                .approval-stamp .stamp-date {
                    color: #005700ff;
                    font-size: 6pt;
                    margin-top: 1mm;
                }
                
                .info-section {
                    margin-bottom: 0mm;
                    width: 100%;
                }
                .section-title {
                    background-color: #e6e6e6;
                    padding: 1mm;
                    font-weight: bold;
                    font-size: 9pt;
                    border: 1px solid #000;
                    width: 98.7%;
                    box-sizing: border-box;
                }
                .info-table {
                    width: 100%;
                    border-collapse: collapse;
                    border-left: 1px solid #000;
                    border-right: 1px solid #000;
                    border-bottom: 1px solid #000;
                }
                .info-table td {
                    padding: 0.5mm 1mm;
                    vertical-align: middle;
                }
                .info-label {
                    font-weight: bold;
                    font-size: 8pt;
                    text-align: left;
                    width: 25%;
                }
                .info-value {
                    font-size: 8pt;
                    text-align: left;
                    width: 75%;
                }
                .products-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 3mm;
                }
                .products-table th {
                    background-color: #cccccc;
                    font-size: 7pt;
                    text-align: center;
                    padding: 0.5mm;
                    border: 0.5px solid #000;
                    font-weight: normal;
                }
                .products-table td {
                    font-size: 7pt;
                    padding: 0.5mm;
                    border-left: 0.5px solid #000;
                    border-right: 0.5px solid #000;
                    vertical-align: top;
                }
                .products-table tr:last-child td {
                    border-bottom: 0.5px solid #000;
                }
                .text-right {
                    text-align: right;
                }
                .text-center {
                    text-align: center;
                }
                .footer {
    position: absolute;
    bottom: 0;
    width: 98.7%;
}

                .additional-info {
                    margin-bottom: 3mm;
                    width: 100%;
                    position: relative;
                }
                .additional-info-header {
                    background-color: #dcdcdc;
                    padding: 1mm;
                    font-weight: bold;
                    font-size: 9pt;
                    text-align: center;
                    border: 1px solid #000;
                    margin-bottom: 1mm;
                    width: 100%;
                    box-sizing: border-box;
                }
                .additional-info-content {
                    width: 100%;
                    border-collapse: collapse;
                    table-layout: fixed;
                }
                .additional-info-content td {
                    vertical-align: top;
                    padding: 1mm;
                    border: none;
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                }
                .additional-info-left {
                    width: 58%;
                    padding-right: 2mm;
                    height: auto;
                }
                .additional-info-right {
                    width: 42%;
                    padding-left: 2mm;
                    height: auto;
                }
                .info-item {
                    margin: 1mm 0;
                    font-size: 7.5pt;
                    line-height: 1.3;
                    display: block;
                }
                .info-item .label {
                    font-weight: bold;
                    display: inline-block;
                    width: 32%;
                    vertical-align: top;
                }
                .info-item .value {
                    display: inline-block;
                    width: 67%;
                    vertical-align: top;
                    word-wrap: break-word;
                    word-break: break-word;
                    overflow-wrap: break-word;
                    hyphens: auto;
                }
                .info-item.observations {
                    margin: 2mm 0;
                }
                .info-item.observations .label {
                    width: 32%;
                    vertical-align: top;
                }
                .info-item.observations .value {
                    width: 67%;
                    line-height: 1.4;
                    text-align: justify;
                    vertical-align: top;
                    word-wrap: break-word;
                    word-break: break-word;
                    overflow-wrap: break-word;
                    hyphens: auto;
                }
                .financial-info {
                    margin-top: 3mm;
                    border-top: 1px solid #000;
                    padding-top: 1mm;
                    width: 101.3%;
                    border-collapse: collapse;
                }
                .financial-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .financial-table td {
                    padding: 0.5mm 1mm;
                    border: none;
                    font-size: 8pt;
                }
                .financial-table .financial-label {
                    text-align: right;
                    font-weight: bold;
                    width: 70%;
                    padding-right: 5mm;
                }
                .financial-table .financial-value {
                    text-align: right;
                    width: 30%;
                    padding-right: 3mm;
                }
                .financial-table .financial-total {
                    border-top: 1px solid #000;
                    padding-top: 1mm;
                    font-weight: bold;
                    font-size: 9pt;
                }
                .description-cell {
                    max-width: 40mm;
                    word-wrap: break-word;
                }
                
                .conditions-page {
                   position: absolute;
    width: 100%;
    height: 100%;
                    box-sizing: border-box;
                    border: 0px solid #000;
                    padding: 0mm;
                    font-size: 10pt;
                    line-height: 1.1;
                }
                .conditions-title {
                    text-align: center;
                    font-weight: bold;
                    font-size: 13pt;
                    margin-bottom: 5mm;
                    text-decoration: underline;
                }
                .condition-item {
                    margin-bottom: 3mm;
                }
                .condition-title {
                    font-weight: bold;
                    margin-bottom: 1mm;
                }
                .condition-content {
                    text-align: justify;
                    line-height: 1.2;
                }
                .signature-section {
                    margin-top: 15mm;
                    border-top: 1px solid #000;
                    padding-top: 5mm;
                }
                .signature-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 5mm;
                }
                .signature-box {
                    width: 45%;
                    text-align: center;
                }
                .signature-line {
                    border-bottom: 1px solid #000;
                    height: 10mm;
                    margin-bottom: 2mm;
                }
                .signature-label {
                    font-size: 8pt;
                    font-weight: bold;
                }
                .signature-data {
                    font-size: 8pt;
                    margin-top: 1mm;
                }
            </style>
        </head>
        <body>';

        for ($pagina = 0; $pagina < $totalPaginas; $pagina++) {
            $indiceInicio = $pagina * $productosEnPagina;
            $productosEnEstaPagina = array_slice($productos, $indiceInicio, $productosEnPagina);

            if ($pagina > 0) {
                $html .= '<div style="page-break-before: always;"></div>';
            }

            $html .= '
            <div class="page-container">
                ' . ($mostrarSelloAprobacion ? '
                <div class="approval-stamp">
                    <div class="stamp-text">
                        Aprobado por<br>
                        Contabilidad
                    </div>
                    <div class="stamp-date">' . date('d/m/Y') . '</div>
                </div>
                ' : '') . '
                
                <div class="header">
                    <h1>' . $empresa['nombre'] . '</h1>
                    <p>' . $empresa['direccion'] . '</p>
                    <p>Email: ' . $empresa['email'] . ' | RUC: ' . $empresa['ruc'] . '</p>
                    
                    <div class="proforma-box">
                        <div class="title">PROFORMA</div>
                        <div class="number">' . $proformaFormateada . '</div>
                        <div class="date">Fecha: ' . $fecha_para_proforma . '</div>
                        <div class="page">Página: ' . ($pagina + 1) . ' de ' . $totalPaginas . '</div>
                    </div>
                </div>
                
                <div class="info-section">
                    <div class="section-title">INFORMACIÓN GENERAL</div>
                    <table class="info-table" cellpadding="1" cellspacing="0">
                        <tr>
                            <td class="info-label" width="20%">Fecha:</td>
                            <td class="info-value" width="80%">' . $fecha_hora_actual . '</td>
                        </tr>
                        <tr>
                            <td class="info-label">RUC:</td>
                            <td class="info-value">' . ($cliente['ruc'] ?? 'No disponible') . '</td>
                        </tr>
                        <tr>
                            <td class="info-label">Nombre o Razón Social:</td>
                            <td class="info-value">' . $presupuesto['cliente'] . '</td>
                        </tr>
                        <tr>
                            <td class="info-label">Dirección:</td>
                            <td class="info-value">' . ($cliente['direccion'] ?? 'No disponible') . '</td>
                        </tr>
                        <tr>
                            <td class="info-label">Teléfono:</td>
                            <td class="info-value">' . ($cliente['telefono'] ?? 'No disponible') . '</td>
                        </tr>
                       <tr>
    <td class="info-label">Cond. de Pago:</td>
    <td class="info-value">' .
                ($presupuesto['cond_pago'] === 'Crédito' && !empty($presupuesto['tipocredito']) ?
                    $presupuesto['cond_pago'] . ' - ' . $presupuesto['tipocredito'] :
                    $presupuesto['cond_pago']
                ) . '
    </td>
</tr>
                        <tr>
                            <td class="info-label">Moneda:</td>
                            <td class="info-value">' . $presupuesto['moneda'] . '</td>
                        </tr>
                        <tr>
                            <td class="info-label">Tipo de Pago:</td>
                            <td class="info-value">' . $presupuesto['tipo_pago'] . '</td>
                        </tr>
                        <tr>
                            <td class="info-label">Vendedor:</td>
                            <td class="info-value">' . ($presupuesto['nombre_usuario'] ?? 'No asignado') . '</td>
                        </tr>
                        <tr>
                            <td class="info-label">Nº Venta:</td>
                            <td class="info-value">' . ($presupuesto['id'] ?? 'No asignado') . '</td>
                        </tr>
                    </table>
                </div>
                
                <div class="info-section">
                    <div class="section-title text-center">DETALLE DE PRODUCTOS</div>
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th width="8%">Cod°</th>
                                <th width="10%">NCM</th>
                                <th width="28%">Descripción</th>
                                <th width="10%">Unidad</th>
                                <th width="11%">Cantidad</th>
                                <th width="12%">Peso liquido(kg)</th>
                                <th width="11%">Precio Unit.</th>
                                <th width="11%">Subtotal s/IVA</th>
                                <th width="11%">IVA 10%</th>
                            </tr>
                        </thead>
                        <tbody>';

            $fill = false;
            foreach ($productosEnEstaPagina as $producto) {
                if ($porcentajeIva > 0) {
                    $totalProductoConIva = (float)$producto['total'];
                    $subtotalProducto = $totalProductoConIva / 1.10;
                    $ivaProducto = $totalProductoConIva - $subtotalProducto;
                } else {
                    $totalProductoConIva = (float)$producto['total'];
                    $subtotalProducto = $totalProductoConIva;
                    $ivaProducto = 0;
                }

                $bgcolor = $fill ? '#f8f8f8' : '#ffffff';

                $html .= '<tr style="background-color: ' . $bgcolor . ';">';
                $html .= '<td class="text-center">' . htmlspecialchars($producto['id_producto']) . '</td>';
                $html .= '<td class="text-center">' . (isset($producto['ncm']) ? htmlspecialchars($producto['ncm']) : '') . '</td>';
                $html .= '<td class="description-cell">' . nl2br(htmlspecialchars($producto['descripcion'])) . '</td>';
                $html .= '<td class="text-center">' . htmlspecialchars($producto['unidadmedida']) . '</td>';

                $tipoProductoNormalizado = normalizarTipoProducto($producto['tipo']);
                $unidadMedidaUpper = strtoupper($producto['unidadmedida']);

                if (in_array($tipoProductoNormalizado, ['TNT', 'LAMINADORA', 'SPUNLACE'])) {
                    if ($unidadMedidaUpper === 'METROS') {
                        $metrosTotales = (float)$producto['cantidad'];
                        preg_match('/(\d+)\s*metros?/i', $producto['descripcion'], $matches);
                        $metrosPorBobina = isset($matches[1]) ? (float)$matches[1] : 1;
                        $cantidadCalculada = ($metrosPorBobina > 0) ? $metrosTotales / $metrosPorBobina : 0;
                        $html .= '<td class="text-right">' . number_format($cantidadCalculada, 2, ',', '.') . '</td>';
                    } elseif ($unidadMedidaUpper === 'UNIDAD') {
                        $html .= '<td class="text-right">' . number_format((float)$producto['cantidad'], 0, ',', '.') . '</td>';
                    } elseif ($unidadMedidaUpper === 'KILOS') {
                        $pesoTotal = (float)$producto['cantidad'];
                        $pesoPorBobina = (float)$producto['peso_por_bobina'];
                        $numeroBobinas = ($pesoPorBobina > 0) ? $pesoTotal / $pesoPorBobina : 0;
                        $html .= '<td class="text-right">' . number_format($numeroBobinas, 0, ',', '.') . '</td>';
                    } else {
                        $html .= '<td class="text-right">' . number_format((float)$producto['cantidad'], 2, ',', '.') . '</td>';
                    }
                } else {
                    $html .= '<td class="text-right">' . number_format((float)$producto['cantidad'], 0, ',', '.') . '</td>';
                }

                if (in_array($tipoProductoNormalizado, ['TNT', 'LAMINADORA', 'SPUNLACE'])) {
                    if ($unidadMedidaUpper === 'METROS') {
                        $metrosTotales = (float)$producto['cantidad'];
                        $pesoPorBobina = (float)$producto['peso_por_bobina'];
                        preg_match('/(\d+)\s*metros?/i', $producto['descripcion'], $matches);
                        $metrosPorBobina = isset($matches[1]) ? (float)$matches[1] : 1;
                        $cantidadBobinas = ($metrosPorBobina > 0) ? $metrosTotales / $metrosPorBobina : 0;
                        $pesoTotal = $cantidadBobinas * $pesoPorBobina;
                        $html .= '<td class="text-right">' . number_format($pesoTotal, 2, ',', '.') . '</td>';
                    } elseif ($unidadMedidaUpper === 'UNIDAD') {
                        $cantidadBobinas = (float)$producto['cantidad'];
                        $pesoPorBobina = (float)$producto['peso_por_bobina'];
                        $pesoLiquido = $cantidadBobinas * $pesoPorBobina;
                        $html .= '<td class="text-right">' . number_format($pesoLiquido, 2, ',', '.') . '</td>';
                    } else {
                        $html .= '<td class="text-right">' . number_format((float)$producto['cantidad'], 2, ',', '.') . '</td>';
                    }
                } elseif (esTipoToallitasOPanos($producto['tipo'])) {
                    $cantidadCajas = (float)$producto['cantidad'];
                    $pesoPorCaja = (float)$producto['peso_por_bobina'];
                    $pesoLiquidoProducto = $cantidadCajas * $pesoPorCaja;

                    error_log("HTML - ID: " . $producto['id_producto'] .
                        " | Tipo: '" . $producto['tipo'] . "'" .
                        " | Cantidad: " . $cantidadCajas .
                        " | Peso por caja: " . $pesoPorCaja .
                        " | Peso calculado: " . $pesoLiquidoProducto);

                    $html .= '<td class="text-right">' . number_format($pesoLiquidoProducto, 2, ',', '.') . '</td>';
                } else {
                    $html .= '<td class="text-right">' . number_format((float)$producto['cantidad'], 2, ',', '.') . '</td>';
                }

                $html .= '<td class="text-right">' . number_format((float)$producto['precio'], 2, ',', '.') . '</td>';
                $html .= '<td class="text-right">' . number_format($subtotalProducto, 2, ',', '.') . '</td>';
                $html .= '<td class="text-right">' . number_format($ivaProducto, 2, ',', '.') . '</td>';
                $html .= '</tr>';

                $fill = !$fill;
            }

            if ($pagina == $totalPaginas - 1) {
                $rowsToAdd = max(0, 0 - count($productosEnEstaPagina));
                for ($i = 0; $i < $rowsToAdd; $i++) {
                    $bgcolor = $fill ? '#f8f8f8' : '#ffffff';
                    $html .= '<tr style="background-color: ' . $bgcolor . ';">';
                    $html .= '<td class="text-center">&nbsp;</td>';
                    $html .= '<td class="text-center">&nbsp;</td>';
                    $html .= '<td class="description-cell">&nbsp;</td>';
                    $html .= '<td class="text-center">&nbsp;</td>';
                    $html .= '<td class="text-right">&nbsp;</td>';
                    $html .= '<td class="text-right">&nbsp;</td>';
                    $html .= '<td class="text-right">&nbsp;</td>';
                    $html .= '<td class="text-right">&nbsp;</td>';
                    $html .= '</tr>';
                    $fill = !$fill;
                }
            }

            $html .= '
                        </tbody>
                    </table>
                </div>
                
                <div class="footer">
                    <div class="additional-info">
                        <div class="additional-info-header">INFORMACIÓN ADICIONAL</div>
                        <table class="additional-info-content" cellpadding="0" cellspacing="0">
                            <tr>
                                <td class="additional-info-left">
                                    <div class="info-item">
                                     <span class="label">' . $nombreMoneda . '</span>
                                     <span class="value">' . $monedaISO . '</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">In favor of:</span>
                                        <span class="value">SUDAMERIS BANK S.A.E.C.A</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Beneficiario Final:</span>
                                        <span class="value">8059024 (Cuenta Corriente en guaraníes)</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Empresa:</span>
                                        <span class="value">AMERICA TNT SOCIEDAD ANONIMA</span>
                                    </div>
                                     <div class="info-item observations">
                                        <span class="label">Observaciones:</span>
                                        <span class="value">' . (empty($presupuesto['descripcion']) ? '<em>Sin observaciones</em>' : htmlspecialchars($presupuesto['descripcion'])) . '</span>
                                    </div>
                                </td>
                                <td class="additional-info-right">
                                    <div class="info-item">
                                        <span class="label">Tipo de Operación:</span>
                                        <span class="value">Venta ' . ($infoAdicional['tipooperacion'] ?? '') . '</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Empresa Fletera:</span>
                                        <span class="value">' . ($infoAdicional['transportadora'] ?? '') . '</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Tipo de Flete:</span>
                                        <span class="value">' . ($infoAdicional['tipoflete'] ?? '') . '</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Peso Liquido:</span>
                                        <span class="value">' . number_format($pesoLiquidoTotal, 2, ',', '.') . ' kg</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Peso Bruto:</span>
                                        <span class="value">' . number_format($pesoBrutoTotal, 2, ',', '.') . ' kg</span>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="financial-info">
                        <table class="financial-table">
                            <tr>
                                <td class="financial-label">SUBTOTAL</td>
                                <td class="financial-value">' . $monedaISO . ' ' . number_format($subtotal, 2, ',', '.') . '</td>
                            </tr>
                            <tr>
                                <td class="financial-label">IVA (' . $porcentajeIva . '%)</td>
                                <td class="financial-value">' . $monedaISO . ' ' . number_format($montoIva, 2, ',', '.') . '</td>
                            </tr>
                            <tr class="financial-total">
                                <td class="financial-label">TOTAL A PAGAR</td>
                                <td class="financial-value">' . $monedaISO . ' ' . number_format($totalConIva, 2, ',', '.') . '</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>';
        }

        $html .= '
        <div style="page-break-before: always;"></div>
        <div class="conditions-page">
            <div class="conditions-title">CONDICIONES COMERCIALES GENERALES Y ACLARACIONES RELEVANTES</div>
            
            <div class="condition-item">
                <div class="condition-title">1. Responsabilidad sobre Costos Bancarios</div>
                <div class="condition-content">
                    Todos los gastos derivados de transferencias bancarias —incluyendo aquellos cobrados por entidades intermedias— deberán ser asumidos íntegramente por el comprador. El importe enviado debe acreditarse en su totalidad a nombre de <strong>AMERICA TNT SOCIEDAD ANÓNIMA</strong>, sin ningún tipo de deducción. Los pagos deberán efectuarse exclusivamente en la moneda establecida en el pedido de venta.
                </div>
            </div>
            
            <div class="condition-item">
                <div class="condition-title">2. Política de Cancelación de Pedidos</div>
                <div class="condition-content">
                    Los pedidos confirmados no podrán ser anulados sin previa aprobación. Una vez iniciada la producción, no será posible su cancelación. El comprador acepta que, en estos casos, se generará automáticamente una obligación de carácter comercial.
                </div>
            </div>
            
            <div class="condition-item">
                <div class="condition-title">3. Cargos por Descarga</div>
                <div class="condition-content">
                    La descarga de los productos en las instalaciones del cliente no forma parte del valor de venta. Este servicio corre exclusivamente por cuenta del comprador.
                </div>
            </div>
            
            <div class="condition-item">
                <div class="condition-title">4. Procedimiento en Caso de Daños o Pérdidas</div>
                <div class="condition-content">
                    Para poder efectuar un reclamo por mercancía dañada o faltante, es imprescindible documentar la situación con fotografías tomadas mientras los productos aún estén dentro del vehículo de transporte. Además, los daños deberán consignarse en el documento de transporte, el cual debe ser firmado por el chofer, indicando su nombre completo, número de documento, fecha y hora de la entrega. No se considerarán válidas las reclamaciones que no sigan este procedimiento.
                </div>
            </div>
            
            <div class="condition-item">
                <div class="condition-title">5. Uso de Pallets</div>
                <div class="condition-content">
                    El valor de los pallets no está incluido en el precio de la venta. La mercancía será despachada en forma suelta, salvo que se acuerde lo contrario con antelación. En tal caso, la paletización conlleva un costo adicional y puede influir en la capacidad de carga del transporte.
                </div>
            </div>
            
            <div class="condition-item">
                <div class="condition-title">6. Alcance del Documento Comercial</div>
                <div class="condition-content">
                    Todo lo referente a este pedido está expresamente detallado en el presente documento. Cualquier acuerdo adicional realizado a través de medios informales (como correos electrónicos o mensajería instantánea) no tendrá validez si no se encuentra formalmente incorporado a este texto.
                </div>
            </div>
            
            <div class="condition-item">
                <div class="condition-title">7. Márgenes de Tolerancia en Producción</div>
                <div class="condition-content">
                    Se aceptan las siguientes variaciones estándar: • Cantidad, peso, espesor, color y longitud: ±10% • Ancho: ±3 mm
                </div>
            </div>
            
            <div class="condition-item">
                <div class="condition-title">8. Posibles Demoras en el Despacho</div>
                <div class="condition-content">
                    Teniendo en cuenta los procedimientos de exportación e importación, es posible que ocurran demoras ocasionadas por las autoridades aduaneras. Por lo tanto, la fecha de entrega será considerada como la fecha de salida de la planta de <strong>AMERICA TNT SOCIEDAD ANÓNIMA</strong>.
                </div>
            </div>
            
            <div class="condition-item">
                <div class="condition-title">9. Condiciones sobre Pagos Anticipados</div>
                <div class="condition-content">
                    No se admitirán devoluciones de anticipos en caso de cancelación del pedido, ya que toda fabricación se realiza bajo demanda específica. Si el producto ya ha sido finalizado y no se efectúa el pago del saldo dentro de los 30 días siguientes a la notificación, el anticipo será aplicado a los costos de fabricación. Transcurridos 60 días sin respuesta, la empresa se reserva el derecho de iniciar acciones legales para el cobro del saldo pendiente.
                </div>
            </div>
            
            <div class="condition-item">
                <div class="condition-title">10. Confirmación de Pedido y Plazos de Entrega</div>
                <div class="condition-content">
                    El pedido se considerará válido únicamente tras la recepción del pago anticipado y su correspondiente confirmación bancaria. Los plazos de entrega empezarán a contarse a partir de esa fecha.
                </div>
            </div>
            
            <div class="condition-item">
                <div class="condition-title">11. Despacho en Operaciones al Contado</div>
                <div class="condition-content">
                    Los envíos correspondientes a operaciones al contado serán liberados solamente una vez confirmado el ingreso del pago en las cuentas de <strong>AMERICA TNT SOCIEDAD ANÓNIMA</strong>.
                </div>
            </div>
            
<div style="margin-top: 15mm; border-top: 0px solid #000; padding-top: 5mm;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="10%" style="text-align: center;"></td>
            <td width="35%" style="text-align: center; vertical-align: top;">
                <div style="border-bottom: 1px solid #000; height: 10mm; margin-bottom: 2mm; width: 70%; margin: 0 auto 2mm auto;"></div>
                <div style="font-size: 8pt; font-weight: bold; width: 70%; margin: 0 auto;">' . htmlspecialchars($presupuesto['cliente']) . '</div>
                <div style="font-size: 8pt; margin-top: 1mm; width: 70%; margin: 1mm auto 0 auto;">' . ($cliente['ruc'] ?? 'No disponible') . '</div>
            </td>
            <td width="10%" style="text-align: center;"></td>
            <td width="35%" style="text-align: center; vertical-align: top;">
                <div style="border-bottom: 1px solid #000; height: 10mm; margin-bottom: 2mm; width: 70%; margin: 0 auto 2mm auto;"></div>
                <div style="font-size: 8pt; font-weight: bold; width: 70%; margin: 0 auto;">AMERICA TNT S.A</div>
                <div style="font-size: 8pt; margin-top: 1mm; width: 70%; margin: 1mm auto 0 auto;">80094986-2</div>
            </td>
            <td width="10%" style="text-align: center;"></td>
        </tr>
    </table>
</div>
        </div>';

        $html .= '
        </body>
        </html>';

        $dompdf->loadHtml($html, 'UTF-8');

        $dompdf->render();

        $nombreArchivo = 'Presupuesto' . $id_presupuesto . '_' . date('Ymd') . '.pdf';
        $dompdf->stream($nombreArchivo, array('Attachment' => false));

        exit;
    } catch (PDOException $e) {
        header("Location: index.php?mensaje=Error al generar el PDF: " . urlencode($e->getMessage()));
        exit();
    } catch (Exception $e) {
        header("Location: index.php?mensaje=Error al generar el PDF: " . urlencode($e->getMessage()));
        exit();
    }
}

generarPDFPresupuesto($id_presupuesto, $conexion);
