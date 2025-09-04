<?php

ini_set('memory_limit', '512M');
include "../config/database/conexionBD.php";

require_once '../vendor/autoload.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php?mensaje=Erro: ID de orçamento não especificado");
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
        error_log("=== INICIANDO DEBUG DE TIPOS DE PRODUTOS - GEHLEN ===");
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
            error_log("TIPO EM BD: " . $tipo_debug['tipo_con_brackets'] .
                " (comprimento: " . $tipo_debug['longitud'] .
                ", primeiro char ASCII: " . $tipo_debug['primer_caracter'] . ")");
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
            header("Location: index.php?mensaje=Erro: Orçamento não encontrado");
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

        error_log("=== PRODUTOS NESTE ORÇAMENTO ===");
        foreach ($productos_debug as $prod_debug) {
            error_log("ID: " . $prod_debug['id_producto'] .
                " | Tipo: '" . $prod_debug['tipo'] . "'" .
                " | Tipo limpo: '" . $prod_debug['tipo_limpio'] . "'" .
                " | Comprimento: " . $prod_debug['longitud_tipo'] .
                " | Primeiro caractere ASCII: " . $prod_debug['primer_caracter'] .
                " | Normalizado: '" . normalizarTipoProducto($prod_debug['tipo']) . "'" .
                " | É toallitas/paños: " . (esTipoToallitasOPanos($prod_debug['tipo']) ? 'SIM' : 'NÃO'));
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
                error_log("Produto ID: " . $producto['id_producto']);
                error_log("Tipo original: '" . $producto['tipo'] . "'");
                error_log("Tipo normalizado: '" . $tipoNormalizado . "'");
                error_log("Função detecta toallitas/paños: " . (esTipoToallitasOPanos($producto['tipo']) ? 'SIM' : 'NÃO'));
                error_log("Quantidade do orçamento: " . $producto['cantidad']);
                error_log("Peso por bobina/caixa da BD: " . $producto['peso_por_bobina']);

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

        $subtotal = $presupuesto['monto_total'];
        $total = $subtotal;

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
            $nombreMoneda = 'REAL BRASILEIRO';
        } elseif (stripos($moneda, 'guarani') !== false || stripos($moneda, 'guaraní') !== false) {
            $monedaISO = 'PYG';
            $nombreMoneda = 'GUARANI PARAGUAIO';
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

        $productosEnPagina = 17;
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
                    margin-bottom: 0mm;
                    margin-top: 2mm;
                    position: relative;
                }
                .header h1 {
                    font-size: 12pt;
                    font-weight: bold;
                    margin: 0;
                    padding: 0;
                }
                .header p {
                    font-size: 7pt;
                    margin: 1mm 0;
                }
                .proforma-box {
                    position: absolute;
                    top: -6.5;
                    right: 0;
                    width: 22mm;
                    height: 19mm;
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
                    padding-top:1mm;
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
                    <h1>J.GEHLEN TECIDOS LTDA</h1>
                    <p>Rua Maria Cecato Bonato, 971 - CASA</p>
                    <p>Rondinha - 83607-311</p>
                    <p>Campo Largo - PR Fone:</p>
                    
                    <div class="proforma-box">
                        <div class="title">PROFORMA</div>
                        <div class="number">' . $proformaFormateada . '</div>
                        <div class="date">Data: ' . $fecha_para_proforma . '</div>
                        <div class="page">Página: ' . ($pagina + 1) . ' de ' . $totalPaginas . '</div>
                    </div>
                </div>
                
                <div class="info-section">
                    <div class="section-title">INFORMAÇÕES GERAIS</div>
                    <table class="info-table" cellpadding="1" cellspacing="0">
                        <tr>
                            <td class="info-label" width="20%">Data:</td>
                            <td class="info-value" width="80%">' . $fecha_hora_actual . '</td>
                        </tr>
                        <tr>
                            <td class="info-label">CNPJ:</td>
                            <td class="info-value">' . ($cliente['cnpj'] ?? 'Não disponível') . '</td>
                        </tr>
                        <tr>
                            <td class="info-label">Nome ou Razão Social:</td>
                            <td class="info-value">' . $presupuesto['cliente'] . '</td>
                        </tr>
                        <tr>
                            <td class="info-label">Endereço:</td>
                            <td class="info-value">' . ($cliente['direccion'] ?? 'Não disponível') . '</td>
                        </tr>
                         <tr>
                            <td class="info-label">Email:</td>
                            <td class="info-value">' . ($cliente['email'] ?? '') . '</td>
                        </tr>
                        <tr>
                            <td class="info-label">Telefone:</td>
                            <td class="info-value">' . ($cliente['telefone'] ?? 'Não disponível') . '</td>
                        </tr>
                         <tr>
                            <td class="info-label">IE:</td>
                            <td class="info-value">' . ($cliente['ie'] ?? '') . '</td>
                        </tr>
                        <tr>
    <td class="info-label">Condições de Pagamento:</td>
    <td class="info-value">' .
                ($presupuesto['cond_pago'] === 'Contado' ? 'A vista' : ($presupuesto['cond_pago'] === 'Crédito' && !empty($presupuesto['tipocredito']) ?
                    $presupuesto['cond_pago'] . ' - ' . $presupuesto['tipocredito'] :
                    $presupuesto['cond_pago']
                )
                ) . '
    </td>
</tr>
                       <tr>
    <td class="info-label">Moeda:</td>
    <td class="info-value">' . (stripos($presupuesto['moneda'], 'real') !== false || stripos($presupuesto['moneda'], 'brasil') !== false ? 'Real' : $presupuesto['moneda']) . '</td>
</tr>

                        <tr>
                            <td class="info-label">Tipo de Pagamento:</td>
                            <td class="info-value">' . $presupuesto['tipo_pago'] . '</td>
                        </tr>
                        <tr>
                            <td class="info-label">Vendedor:</td>
                            <td class="info-value">' . ($presupuesto['nombre_usuario'] ?? 'Não atribuído') . '</td>
                        </tr>
                        <tr>
                            <td class="info-label">Nº Venda:</td>
                            <td class="info-value">' . ($presupuesto['id'] ?? 'Não atribuído') . '</td>
                        </tr>
                    </table>
                </div>
                
                <div class="info-section">
                    <div class="section-title text-center">DETALHE DOS PRODUTOS</div>
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th width="8%">Cod°</th>
                                <th width="10%">NCM</th>
                                <th width="28%">Descrição</th>
                                <th width="10%">Unidade</th>
                                <th width="11%">Quantidade</th>
                                <th width="12%">Peso liquido(kg)</th>
                                <th width="11%">Preço Unit.</th>
                                <th width="11%">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>';

            $fill = false;
            foreach ($productosEnEstaPagina as $producto) {
                $bgcolor = $fill ? '#f8f8f8' : '#ffffff';

                $unidadMedida = htmlspecialchars($producto['unidadmedida']);
                switch ($unidadMedida) {
                    case 'Unidad':
                        $unidadMedida = 'Un';
                        break;
                    case 'Metros':
                        $unidadMedida = 'M';
                        break;
                    case 'Kilos':
                        $unidadMedida = 'Kg';
                        break;
                    case 'Cajas':
                        $unidadMedida = 'Cx';
                        break;
                }

                $html .= '<tr style="background-color: ' . $bgcolor . ';">';
                $html .= '<td class="text-center">' . htmlspecialchars($producto['id_producto']) . '</td>';
                $html .= '<td class="text-center">' . (isset($producto['ncm']) ? htmlspecialchars($producto['ncm']) : '') . '</td>';
                $html .= '<td class="description-cell">' . nl2br(htmlspecialchars($producto['descripcion'])) . '</td>';
                $html .= '<td class="text-center">' . $unidadMedida . '</td>';

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
                        " | Quantidade: " . $cantidadCajas .
                        " | Peso por caixa: " . $pesoPorCaja .
                        " | Peso calculado: " . $pesoLiquidoProducto);

                    $html .= '<td class="text-right">' . number_format($pesoLiquidoProducto, 2, ',', '.') . '</td>';
                } else {
                    $html .= '<td class="text-right">' . number_format((float)$producto['cantidad'], 2, ',', '.') . '</td>';
                }

                $html .= '<td class="text-right">' . number_format((float)$producto['precio'], 2, ',', '.') . '</td>';
                $html .= '<td class="text-right">' . number_format((float)$producto['total'], 2, ',', '.') . '</td>';
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
                        <div class="additional-info-header">INFORMAÇÕES ADICIONAIS</div>
                        <table class="additional-info-content" cellpadding="0" cellspacing="0">
                         <tr>
                                <td class="additional-info-left">
                                    <div class="info-item">
                                        <span class="label">Tipo de Operação:</span>
                                        <span class="value">Venda ' . ($infoAdicional['tipooperacion'] ?? '') . '</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Empresa Transportadora:</span>
                                        <span class="value">' . ($infoAdicional['transportadora'] ?? '') . '</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Tipo de Frete:</span>
                                        <span class="value">' . ($infoAdicional['tipoflete'] ?? '') . '</span>
                                    </div>
                                    <div class="info-item observations">
                                        <span class="label">Dados adicionais:</span>
                                        <span class="value">' . (empty($presupuesto['descripcion']) ? '<em></em>' : htmlspecialchars($presupuesto['descripcion'])) . '</span>
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
                            <tr>
                                <td class="additional-info-left">
                                
                            </tr>
                        </table>
                    </div>
                    
                    <div class="financial-info">
                        <table class="financial-table">
                        <tr>
                                <td class="financial-label">SUBTOTAL</td>
                                <td class="financial-value">' . $monedaISO . ' ' . number_format($subtotal, 2, ',', '.') . '</td>
                            </tr>
                            <tr class="financial-total">
                                <td class="financial-label">TOTAL A PAGAR</td>
                                <td class="financial-value">' . $monedaISO . ' ' . number_format($total, 2, ',', '.') . '</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>';
        }

        $html .= '
    <div style="page-break-before: always;"></div>
    <div class="conditions-page">
        <div class="conditions-title">CONDIÇÕES COMERCIAIS GERAIS E ESCLARECIMENTOS RELEVANTES</div>        
        <div class="condition-item">
            <div class="condition-title">1. Responsabilidade sobre Custos Bancários</div>
            <div class="condition-content">
                Todas as despesas decorrentes de transferências bancárias — incluindo aquelas cobradas por instituições intermediárias — devem ser integralmente assumidas pelo comprador. O valor enviado deve ser creditado na totalidade em nome de <strong>J.GEHLEN TECIDOS LTDA</strong>, sem qualquer tipo de dedução. Os pagamentos devem ser efetuados exclusivamente na moeda estabelecida no pedido de venda.
            </div>
        </div>
        
        <div class="condition-item">
            <div class="condition-title">2. Política de Cancelamento de Pedidos</div>
            <div class="condition-content">
                Pedidos confirmados não poderão ser cancelados sem aprovação prévia. Uma vez iniciada a produção, não será possível realizar o cancelamento. O comprador aceita que, nesses casos, será gerada automaticamente uma obrigação de caráter comercial.
            </div>
        </div>
        
        <div class="condition-item">
            <div class="condition-title">3. Custos de Descarga</div>
            <div class="condition-content">
                A descarga dos produtos nas instalações do cliente não está incluída no valor da venda. Este serviço é de responsabilidade exclusiva do comprador.
            </div>
        </div>
        
        <div class="condition-item">
            <div class="condition-title">4. Procedimento em Caso de Danos ou Perdas</div>
            <div class="condition-content">
                Para que uma reclamação por mercadoria danificada ou faltante seja válida, é imprescindível documentar a situação com fotografias tiradas enquanto os produtos ainda estiverem dentro do veículo de transporte. Além disso, os danos devem ser registrados no documento de transporte, que deve ser assinado pelo motorista, indicando nome completo, número do documento, data e hora da entrega. Reclamações que não sigam esse procedimento não serão consideradas válidas.
            </div>
        </div>
        
        <div class="condition-item">
            <div class="condition-title">5. Uso de Paletes</div>
            <div class="condition-content">
                O valor dos paletes não está incluído no preço da venda. A mercadoria será despachada solta, salvo acordo prévio em contrário. Neste caso, a paletização implicará em custo adicional e poderá influenciar na capacidade de carga do transporte.
            </div>
        </div>
        
        <div class="condition-item">
            <div class="condition-title">6. Alcance do Documento Comercial</div>
            <div class="condition-content">
                Tudo o que diz respeito a este pedido está expressamente detalhado neste documento. Qualquer acordo adicional realizado por meios informais (como e-mails ou mensagens instantâneas) não terá validade se não estiver formalmente incorporado a este texto.
            </div>
        </div>
        
        <div class="condition-item">
            <div class="condition-title">7. Margens de Tolerância na Produção</div>
            <div class="condition-content">
                São aceitas as seguintes variações padrão: • Quantidade, peso, espessura, cor e comprimento: ±10% • Largura: ±3 mm
            </div>
        </div>
        
        <div class="condition-item">
            <div class="condition-title">8. Possíveis Atrasos na Expedição</div>
            <div class="condition-content">
                Considerando os procedimentos de exportação e importação, podem ocorrer atrasos causados pelas autoridades alfandegárias. Portanto, a data de entrega será considerada como a data de saída da planta da <strong>J.GEHLEN TECIDOS LTDA</strong>.
            </div>
        </div>
        
        <div class="condition-item">
            <div class="condition-title">9. Condições sobre Pagamentos Antecipados</div>
            <div class="condition-content">
                Não serão aceitas devoluções de adiantamentos em caso de cancelamento do pedido, pois toda fabricação é realizada sob demanda específica. Se o produto já estiver finalizado e o saldo não for pago dentro de 30 dias após a notificação, o adiantamento será utilizado para cobrir os custos de produção. Após 60 dias sem resposta, a empresa reserva-se o direito de iniciar ações legais para a cobrança do saldo pendente.
            </div>
        </div>
        
        <div class="condition-item">
            <div class="condition-title">10. Confirmação do Pedido e Prazos de Entrega</div>
            <div class="condition-content">
                O pedido será considerado válido apenas após o recebimento do pagamento antecipado e sua respectiva confirmação bancária. Os prazos de entrega começarão a contar a partir dessa data.
            </div>
        </div>
        
        <div class="condition-item">
            <div class="condition-title">11. Expedição em Operações à Vista</div>
            <div class="condition-content">
                Os envios correspondentes às operações à vista serão liberados somente após a confirmação do pagamento nas contas da <strong>J.GEHLEN TECIDOS LTDA</strong>.
            </div>
        </div>
        
        <div style="margin-top: 15mm; border-top: 0px solid #000; padding-top: 5mm;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="10%" style="text-align: center;"></td>
                    <td width="35%" style="text-align: center; vertical-align: top;">
                        <div style="border-bottom: 1px solid #000; height: 10mm; margin-bottom: 2mm; width: 70%; margin: 0 auto 2mm auto;"></div>
                        <div style="font-size: 8pt; font-weight: bold; width: 70%; margin: 0 auto;">' . htmlspecialchars($presupuesto['cliente']) . '</div>
                        <div style="font-size: 8pt; margin-top: 1mm; width: 70%; margin: 1mm auto 0 auto;">' . ($cliente['cnpj'] ?? 'Não disponível') . '</div>
                    </td>
                    <td width="10%" style="text-align: center;"></td>
                    <td width="35%" style="text-align: center; vertical-align: top;">
                        <div style="border-bottom: 1px solid #000; height: 10mm; margin-bottom: 2mm; width: 70%; margin: 0 auto 2mm auto;"></div>
                        <div style="font-size: 8pt; font-weight: bold; width: 70%; margin: 0 auto;">J.GEHLEN TECIDOS LTDA</div>
                        <div style="font-size: 8pt; margin-top: 1mm; width: 70%; margin: 1mm auto 0 auto;"></div>
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

        $nombreArchivo = 'Proforma' . $id_presupuesto . '_' . date('Ymd') . '.pdf';
        $dompdf->stream($nombreArchivo, array('Attachment' => false));

        exit;
    } catch (PDOException $e) {
        header("Location: index.php?mensaje=Erro ao gerar o PDF: " . urlencode($e->getMessage()));
        exit();
    } catch (Exception $e) {
        header("Location: index.php?mensaje=Erro ao gerar o PDF: " . urlencode($e->getMessage()));
        exit();
    }
}

generarPDFPresupuesto($id_presupuesto, $conexion);
