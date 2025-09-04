<?php
class PrintService
{
    private $productionRepo;

    public function __construct($conexion)
    {
        $this->productionRepo = new ProductionRepositoryUniversal($conexion);
    }

    /**
     * Manejar reimpresión de etiquetas UNIFICADO - ACTUALIZADO con soporte para PAÑOS
     * @param int $numeroOrden
     * @param string $tipoProducto - 'TOALLITAS', 'PAÑOS', 'TNT', 'SPUNLACE', 'LAMINADORA'
     * @param int $idStock
     * @return array
     */
    public function procesarReimpresion($numeroOrden, $tipoProducto, $idStock = null)
    {
        try {
            error_log("🔄 PrintService - Procesando reimpresión: Orden: $numeroOrden, Tipo: $tipoProducto, ID: $idStock");

            switch (strtoupper($tipoProducto)) {
                case 'TOALLITAS':
                    return $this->reimprimirToallitas($numeroOrden, $idStock);

                case 'PAÑOS':
                    return $this->reimprimirPanos($numeroOrden, $idStock);

                case 'TNT':
                case 'SPUNLACE':
                case 'LAMINADORA':
                    return $this->reimprimirTNTSpunlaceLaminadora($numeroOrden, $tipoProducto, $idStock);

                default:
                    throw new Exception("Tipo de producto no soportado para reimpresión: $tipoProducto");
            }
        } catch (Exception $e) {
            error_log("💥 Error en PrintService reimpresión: " . $e->getMessage());
            return [
                'success' => false,
                'error' => "❌ Error al reimprimir etiqueta: " . $e->getMessage(),
                'pdf_url' => null,
                'mensaje' => null
            ];
        }
    }

    /**
     * Reimpresión específica para TOALLITAS
     * @param int $numeroOrden
     * @param int $idStock
     * @return array
     */
    private function reimprimirToallitas($numeroOrden, $idStock)
    {
        if ($idStock && $idStock > 0) {
            $pdf_url = "pdf/etiquetaToallitas.php?id_orden=" . $numeroOrden . "&id_stock=" . $idStock;
            $mensaje = "🔄 Reimprimiendo etiqueta específica (Item #$idStock) para Orden #{$numeroOrden} - TOALLITAS...";
            error_log("🏷️ PDF Toallitas específico: $pdf_url");
        } else {
            $pdf_url = "pdf/etiquetaToallitas.php?id_orden=" . $numeroOrden;
            $mensaje = "🔄 Reimprimiendo última etiqueta para Orden #{$numeroOrden} - TOALLITAS...";
            error_log("🏷️ PDF Toallitas general: $pdf_url");
        }

        return [
            'success' => true,
            'pdf_url' => $pdf_url,
            'mensaje' => $mensaje,
            'error' => null
        ];
    }

    /**
     * Reimpresión específica para PAÑOS - NUEVO
     * @param int $numeroOrden
     * @param int $idStock
     * @return array
     */
    private function reimprimirPanos($numeroOrden, $idStock)
    {
        if ($idStock && $idStock > 0) {
            $pdf_url = "pdf/etiquetaPanos.php?id_orden=" . $numeroOrden . "&id_stock=" . $idStock;
            $mensaje = "🔄 Reimprimiendo etiqueta específica (Item #$idStock) para Orden #{$numeroOrden} - PAÑOS...";
            error_log("🧽 PDF Paños específico: $pdf_url");
        } else {
            $pdf_url = "pdf/etiquetaPanos.php?id_orden=" . $numeroOrden;
            $mensaje = "🔄 Reimprimiendo última etiqueta para Orden #{$numeroOrden} - PAÑOS...";
            error_log("🧽 PDF Paños general: $pdf_url");
        }

        return [
            'success' => true,
            'pdf_url' => $pdf_url,
            'mensaje' => $mensaje,
            'error' => null
        ];
    }

    /**
     * Reimpresión para TNT/SPUNLACE/LAMINADORA - RENOMBRADO para claridad
     * @param int $numeroOrden
     * @param string $tipoProducto
     * @param int $idStock
     * @return array
     */
    private function reimprimirTNTSpunlaceLaminadora($numeroOrden, $tipoProducto, $idStock)
    {
        // Obtener información del producto para determinar el tipo de PDF
        $resultado = $this->productionRepo->buscarOrdenCompleta($numeroOrden);
        if ($resultado['error'] || empty($resultado['productos'])) {
            throw new Exception("No se pudo obtener información del producto para la orden #{$numeroOrden}");
        }

        $producto = $resultado['productos'][0];
        $largura_valor = floatval($producto['largura_metros'] ?? 0);

        // Determinar archivo PDF según largura (igual que TNT)
        if ($largura_valor > 0 && $largura_valor < 1.0) {
            $archivo_pdf = "pdf/etiquetatntAngosto.php";
            $tipo_desc = "angosto";
        } else {
            $archivo_pdf = "pdf/etiquetatntAncho.php";
            $tipo_desc = "ancho";
        }

        // Para LAMINADORA, usar los mismos PDFs que TNT
        $tipoParaPdf = ($tipoProducto === 'LAMINADORA') ? 'TNT' : $tipoProducto;

        // Construir URL según si es reimpresión específica o última
        if ($idStock && $idStock > 0) {
            $pdf_url = "{$archivo_pdf}?id_orden=" . $numeroOrden . "&id_stock=" . $idStock . "&tipo=" . urlencode($tipoParaPdf);
            $mensaje = "🔄 Reimprimiendo etiqueta específica (Item #$idStock) para Orden #{$numeroOrden} - {$tipoProducto} $tipo_desc ({$largura_valor}m)...";
            error_log("📦 PDF {$tipoProducto} específico: $pdf_url");
        } else {
            $pdf_url = "{$archivo_pdf}?id_orden=" . $numeroOrden . "&tipo=" . urlencode($tipoParaPdf);
            $mensaje = "🔄 Reimprimiendo última etiqueta para Orden #{$numeroOrden} - {$tipoProducto} $tipo_desc ({$largura_valor}m)...";
            error_log("📦 PDF {$tipoProducto} general: $pdf_url");
        }

        return [
            'success' => true,
            'pdf_url' => $pdf_url,
            'mensaje' => $mensaje,
            'error' => null
        ];
    }

    /**
     * Método auxiliar para validar tipo de producto
     * @param string $tipoProducto
     * @return bool
     */
    public function esTipoValido($tipoProducto)
    {
        $tiposValidos = ['TOALLITAS', 'PAÑOS', 'TNT', 'SPUNLACE', 'LAMINADORA'];
        return in_array(strtoupper($tipoProducto), $tiposValidos);
    }

    /**
     * Obtener URL de PDF para un tipo específico (sin reimpresión)
     * @param int $numeroOrden
     * @param string $tipoProducto
     * @param int $bobinas_pacote
     * @return string
     */
    public function generarUrlPDF($numeroOrden, $tipoProducto, $bobinas_pacote = 1)
    {
        switch (strtoupper($tipoProducto)) {
            case 'TOALLITAS':
                return "pdf/etiquetaToallitas.php?id_orden=" . $numeroOrden;

            case 'PAÑOS':
                return "pdf/etiquetaPanos.php?id_orden=" . $numeroOrden;

            case 'TNT':
            case 'SPUNLACE':
            case 'LAMINADORA':
                // Obtener largura para determinar tipo de PDF
                $resultado = $this->productionRepo->buscarOrdenCompleta($numeroOrden);
                if ($resultado['error'] || empty($resultado['productos'])) {
                    return "pdf/etiquetatntAncho.php?id_orden=" . $numeroOrden; // Fallback
                }

                $producto = $resultado['productos'][0];
                $largura_valor = floatval($producto['largura_metros'] ?? 0);

                $archivo_pdf = ($largura_valor > 0 && $largura_valor < 1.0)
                    ? "pdf/etiquetatntAngosto.php"
                    : "pdf/etiquetatntAncho.php";

                $tipoParaPdf = ($tipoProducto === 'LAMINADORA') ? 'TNT' : $tipoProducto;

                return "{$archivo_pdf}?id_orden=" . $numeroOrden . "&bobinas_pacote=" . $bobinas_pacote . "&tipo=" . urlencode($tipoParaPdf);

            default:
                throw new Exception("Tipo de producto no soportado: $tipoProducto");
        }
    }


    /**
     * 🆕 Procesar reimpresión en lote de etiquetas
     * @param int $numeroOrden
     * @param string $tipoProducto
     * @param int $itemDesde
     * @param int $itemHasta
     * @return array
     */
    public function procesarReimpresionLote($numeroOrden, $tipoProducto, $itemDesde, $itemHasta)
    {
        try {
            error_log("🔄 PrintService - Procesando reimpresión en lote: Orden: $numeroOrden, Tipo: $tipoProducto, Rango: $itemDesde-$itemHasta");

            // Validar el rango usando ProductionController
            $controller = new ProductionController($this->productionRepo->getConexion());
            $validacion = $controller->validarRangoItems($numeroOrden, $itemDesde, $itemHasta);

            if (!$validacion['success']) {
                throw new Exception($validacion['error']);
            }

            $itemsExistentes = $validacion['items_existentes'];
            $itemsFaltantes = $validacion['items_faltantes'];
            $totalEncontrados = $validacion['total_encontrados'];

            // Construir mensaje de confirmación
            $mensaje = "🔄 Reimprimiendo etiquetas en lote - Orden #{$numeroOrden} - {$tipoProducto}<br>";
            $mensaje .= "Rango solicitado: Items #{$itemDesde} al #{$itemHasta} ({$validacion['total_solicitados']} items)<br>";
            $mensaje .= "Items encontrados: {$totalEncontrados}<br>";

            if (!empty($itemsFaltantes)) {
                $mensaje .= "<span class='text-warning'><i class='fas fa-exclamation-triangle me-1'></i>Items no encontrados: " . implode(', ', $itemsFaltantes) . "</span><br>";
            }

            // Generar URL del PDF según el tipo de producto
            switch (strtoupper($tipoProducto)) {
                case 'TOALLITAS':
                    $pdf_url = $this->generarUrlLoteToallitas($numeroOrden, $itemDesde, $itemHasta);
                    break;

                case 'PAÑOS':
                    $pdf_url = $this->generarUrlLotePanos($numeroOrden, $itemDesde, $itemHasta);
                    break;

                case 'TNT':
                case 'SPUNLACE':
                case 'LAMINADORA':
                    $pdf_url = $this->generarUrlLoteTNTSpunlaceLaminadora($numeroOrden, $tipoProducto, $itemDesde, $itemHasta);
                    break;

                default:
                    throw new Exception("Tipo de producto no soportado para reimpresión en lote: $tipoProducto");
            }

            $mensaje .= "<small class='text-info'><i class='fas fa-print me-1'></i>Generando PDF con todas las etiquetas...</small>";

            error_log("🏷️ PDF en lote generado: $pdf_url");

            return [
                'success' => true,
                'pdf_url' => $pdf_url,
                'mensaje' => $mensaje,
                'items_procesados' => $totalEncontrados,
                'items_faltantes' => $itemsFaltantes,
                'error' => null
            ];
        } catch (Exception $e) {
            error_log("💥 Error en PrintService reimpresión lote: " . $e->getMessage());
            return [
                'success' => false,
                'error' => "❌ Error al reimprimir etiquetas en lote: " . $e->getMessage(),
                'pdf_url' => null,
                'mensaje' => null,
                'items_procesados' => 0,
                'items_faltantes' => []
            ];
        }
    }

    /**
     * 🆕 Generar URL para lote de TOALLITAS
     */
    private function generarUrlLoteToallitas($numeroOrden, $itemDesde, $itemHasta)
    {
        return "pdf/etiquetaToallitas.php?id_orden={$numeroOrden}&lote=1&item_desde={$itemDesde}&item_hasta={$itemHasta}";
    }

    /**
     * 🆕 Generar URL para lote de PAÑOS
     */
    private function generarUrlLotePanos($numeroOrden, $itemDesde, $itemHasta)
    {
        return "pdf/etiquetaPanos.php?id_orden={$numeroOrden}&lote=1&item_desde={$itemDesde}&item_hasta={$itemHasta}";
    }

    /**
     * 🆕 Generar URL para lote de TNT/SPUNLACE/LAMINADORA
     */
    private function generarUrlLoteTNTSpunlaceLaminadora($numeroOrden, $tipoProducto, $itemDesde, $itemHasta)
    {
        // Obtener información del producto para determinar el tipo de PDF
        $resultado = $this->productionRepo->buscarOrdenCompleta($numeroOrden);
        if ($resultado['error'] || empty($resultado['productos'])) {
            throw new Exception("No se pudo obtener información del producto para la orden #{$numeroOrden}");
        }

        $producto = $resultado['productos'][0];
        $largura_valor = floatval($producto['largura_metros'] ?? 0);

        // Determinar archivo PDF según largura
        if ($largura_valor > 0 && $largura_valor < 1.0) {
            $archivo_pdf = "pdf/etiquetatntAngosto.php";
        } else {
            $archivo_pdf = "pdf/etiquetatntAncho.php";
        }

        // Para LAMINADORA, usar los mismos PDFs que TNT
        $tipoParaPdf = ($tipoProducto === 'LAMINADORA') ? 'TNT' : $tipoProducto;

        return "{$archivo_pdf}?id_orden={$numeroOrden}&lote=1&item_desde={$itemDesde}&item_hasta={$itemHasta}&tipo=" . urlencode($tipoParaPdf);
    }

    /**
     * 🆕 Obtener lista de items disponibles para una orden
     * @param int $numeroOrden
     * @return array
     */
    public function obtenerItemsDisponibles($numeroOrden)
    {
        try {
            return $this->productionRepo->obtenerItemsOrden($numeroOrden);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo items disponibles: " . $e->getMessage());
            return [
                'success' => false,
                'items' => [],
                'error' => $e->getMessage()
            ];
        }
    }
}
