<?php
ini_set('memory_limit', '512M');
include "../../../config/database/conexionBD.php";
include "../../../auth/verificar_sesion.php";

requerirLogin();

// Verificar y cargar controladores
if (file_exists("../controllers/RelatorioController.php")) {
    include "../controllers/RelatorioController.php";
} else {
    die("Error: No se pudo cargar el controlador de relatorios.");
}

// Cargar módulos del relatorio
require_once 'RelatorioDataService.php';
require_once 'RelatorioChartGenerator.php';
require_once 'RelatorioHTMLBuilder.php';

// Importar Browsershot
require_once '../../../vendor/autoload.php';

use Spatie\Browsershot\Browsershot;

// Instanciar el controller
$controller = new RelatorioController($conexion, $url_base);

// Verificar permisos
if (!$controller->verificarPermisos('ver')) {
    header("Location: " . $url_base . "index.php?error=No tienes permisos para generar relatorios");
    exit();
}

// Obtener parámetros del PDF
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');
$cliente = $_GET['cliente'] ?? '';
$vendedor = $_GET['vendedor'] ?? '';
$estado = $_GET['estado'] ?? '';
$incluirGraficos = ($_GET['incluir_graficos'] ?? '0') === '1';
$incluirTotales = ($_GET['incluir_totales'] ?? '1') === '1';
$incluirProductos = ($_GET['incluir_productos'] ?? '0') === '1';
$formatoPapel = $_GET['formato_papel'] ?? 'A4';
$agruparPorCliente = ($_GET['agrupar_por_cliente'] ?? '0') === '1';
$agruparPorVendedor = ($_GET['agrupar_por_vendedor'] ?? '0') === '1';
$agruparProductos = ($_GET['agrupar_productos'] ?? '0') === '1';

// Validar fechas
if (!$fechaInicio || !$fechaFin) {
    header("Location: relatorio.php?error=Las fechas son requeridas para generar el PDF");
    exit();
}

if (strtotime($fechaInicio) > strtotime($fechaFin)) {
    header("Location: relatorio.php?error=La fecha de inicio no puede ser mayor que la fecha fin");
    exit();
}

/**
 * Función principal para generar el PDF del relatorio
 */
function generarPDFRelatorio($controller, $params)
{
    try {
        extract($params);

        error_log("=== INICIANDO PDF EJECUTIVO ===");
        error_log("Formato papel solicitado: $formatoPapel");

        // Instanciar servicios
        $dataService = new RelatorioDataService($controller);
        $chartGenerator = new RelatorioChartGenerator();
        $htmlBuilder = new RelatorioHTMLBuilder($chartGenerator, $dataService);

        // Preparar todos los datos
        $datos = $dataService->prepararDatosCompletos($params);

        // Información de la empresa
        $empresa = [
            'nombre' => 'AMERICA TNT SOCIEDAD ANONIMA',
            'direccion' => 'PARQUE INDUSTRIAL SANTA MONICA ALTO PARANA HERNANDARIAS (MUNICIPIO)',
            'email' => 'contato@americatnt.com',
            'ruc' => '80094986-2'
        ];

        $usuarioActual = $_SESSION['nombre'] ?? 'Usuario';
        $fechaGeneracion = date('d/m/Y H:i:s');

        // Generar HTML completo
        $html = $htmlBuilder->construirHTMLCompleto(
            $params,
            $empresa,
            $usuarioActual,
            $fechaGeneracion,
            $datos
        );

        // Configurar Browsershot según orientación
        $esHorizontal = ($formatoPapel === 'A4_horizontal');
        $esLetter = ($formatoPapel === 'Letter');

        error_log("Configuración: Horizontal=" . ($esHorizontal ? 'SÍ' : 'NO') . ", Letter=" . ($esLetter ? 'SÍ' : 'NO'));

        $pdf = configurarBrowsershot($html, $esHorizontal, $esLetter);

        $nombreArchivo = 'Relatorio_Ventas_' . date('Y-m-d_H-i-s') . '.pdf';

        // Generar y enviar PDF
        $pdfContent = $pdf->pdf();

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
        header('Content-Length: ' . strlen($pdfContent));

        echo $pdfContent;

        error_log("✅ PDF generado exitosamente con orientación: " . ($esHorizontal ? 'Horizontal' : 'Vertical'));
        exit();
    } catch (Exception $e) {
        error_log("❌ Error generando PDF: " . $e->getMessage());
        header("Location: relatorio.php?error=" . urlencode("Error al generar el PDF: " . $e->getMessage()));
        exit();
    }
}

/**
 * Configura Browsershot según la orientación solicitada
 */
function configurarBrowsershot($html, $esHorizontal, $esLetter)
{
    $pdf = Browsershot::html($html)
        ->setNodeBinary('C:\Program Files\nodejs\node.exe')
        ->setNpmBinary('C:\Program Files\nodejs\npm.cmd')
        ->timeout(60)
        ->dismissDialogs()
        ->ignoreHttpsErrors();

    // Configuración de orientación
    if ($esHorizontal) {
        $pdf->landscape()
            ->format('A4')
            ->margins(10, 10, 10, 10);
        error_log("✅ Configurado: A4 Horizontal");
    } elseif ($esLetter) {
        $pdf->format('Letter')
            ->margins(12, 12, 12, 12);
        error_log("✅ Configurado: Letter Vertical");
    } else {
        $pdf->format('A4')
            ->margins(12, 12, 12, 12);
        error_log("✅ Configurado: A4 Vertical");
    }

    // Configuraciones adicionales
    $pdf->scale(0.85)
        ->setOption('print-media-type', true)
        ->setOption('prefer-css-page-size', false);

    return $pdf;
}

// Preparar parámetros y ejecutar generación
$parametros = compact(
    'fechaInicio',
    'fechaFin',
    'cliente',
    'vendedor',
    'estado',
    'incluirGraficos',
    'incluirTotales',
    'incluirProductos',
    'formatoPapel',
    'agruparPorCliente',      // ✅ NUEVO
    'agruparPorVendedor',     // ✅ NUEVO
    'agruparProductos'        // ✅ NUEVO
);

// Ejecutar la generación del PDF
generarPDFRelatorio($controller, $parametros);
