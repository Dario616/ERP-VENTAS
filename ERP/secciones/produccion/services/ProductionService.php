<?php

/**
 * ProductionService - Con validación de materias primas ANTES del registro
 * CAMBIOS:
 * - ✅ Agregada validación de disponibilidad ANTES del registro
 * - ✅ Mensajes informativos de materias faltantes
 * - ✅ Bloqueo automático si no hay suficiente stock
 */
class ProductionService
{
    private $productionRepo;
    private $printService;
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
        $this->productionRepo = new ProductionRepositoryUniversal($conexion);
        $this->printService = new PrintService($conexion);
    }

    /**
     * 🔄 ACTUALIZADO: Procesar TOALLITAS y PAÑOS con validación de materias primas
     */
    private function procesarToallitasYPanos($datos, $ordenEncontrada, $producto)
    {
        $pesoBruto = $datos['peso_bruto'];
        $tara = $datos['tara'];
        $pesoLiquido = $pesoBruto - $tara;
        $bobinas_pacote = 1;
        $tipoProducto = $producto['tipo'];
        $usuario = $_SESSION['nombre'] ?? 'SISTEMA_PRODUCCION';

        // 🆕 VALIDAR DISPONIBILIDAD DE MATERIAS PRIMAS ANTES DEL REGISTRO
        $validacionMaterias = $this->validarDisponibilidadMateriasPrimas($datos['numero_orden'], $datos);
        if (!$validacionMaterias['puede_continuar']) {
            throw new Exception($this->construirMensajeStockInsuficiente($validacionMaterias));
        }

        $datosRegistro = [
            'numero_orden' => $datos['numero_orden'],
            'peso_bruto' => $pesoBruto,
            'peso_liquido' => $pesoLiquido,
            'tara' => $tara,
            'nombre_producto' => $producto['descripcion'],
            'tipo_producto' => $tipoProducto,
            'bobinas_pacote' => $bobinas_pacote,
            'usuario' => $usuario,
        ];

        $resultadoRegistro = $this->productionRepo->registrarEnStock($datosRegistro);

        if ($resultadoRegistro['success']) {
            // 🆕 PROCESAR DESCUENTO DE MATERIAS PRIMAS (ahora sabemos que hay stock)
            $descuentoMaterias = $this->procesarDescuentoMateriasPrimas($datos['numero_orden'], $datos);

            $siguienteItem = $resultadoRegistro['numero_item'];

            // Intentar reserva automática
            $resultadoReserva = $this->realizarReservaAutomatica(
                $ordenEncontrada,
                $bobinas_pacote,
                $producto['descripcion'],
                $bobinas_pacote,
                $producto
            );

            // Generar URL del PDF
            $pdf_url = $tipoProducto === 'TOALLITAS'
                ? "pdf/etiquetaToallitas.php?id_orden=" . $datos['numero_orden']
                : "pdf/etiquetaPanos.php?id_orden=" . $datos['numero_orden'];

            $tipoEtiqueta = $tipoProducto === 'TOALLITAS' ? "Caja" : "Paño";

            // Construir mensaje con información de reserva y materias primas
            $mensaje = $this->construirMensajeRegistro(
                $tipoEtiqueta,
                $siguienteItem,
                $pesoBruto,
                $pesoLiquido,
                $tara,
                $datos['numero_orden'],
                $tipoProducto,
                $resultadoReserva
            );

            // Agregar información de materias primas al mensaje
            $mensaje .= $this->construirMensajeMaterias($descuentoMaterias);

            // 🆕 AGREGAR INFORMACIÓN DE VALIDACIÓN EXITOSA
            if ($validacionMaterias['success'] && !empty($validacionMaterias['materias_validadas'])) {
                $mensaje .= $this->construirMensajeValidacionExitosa($validacionMaterias);
            }

            return [
                'success' => true,
                'auto_print_url' => $pdf_url,
                'mensaje' => $mensaje,
                'error' => null
            ];
        }

        throw new Exception($resultadoRegistro['error']);
    }

    /**
     * 🔄 ACTUALIZADO: Procesar TNT/SPUNLACE/LAMINADORA con validación de materias primas
     */
    private function procesarTNTSpunlaceLaminadora($datos, $ordenEncontrada, $producto)
    {
        $largura_valor = floatval($datos['largura']);
        $pesoBruto = $datos['peso_bruto'];
        $tara = $datos['tara'];
        $tipoProducto = $producto['tipo'];
        $usuario = $_SESSION['nombre'] ?? 'SISTEMA_PRODUCCION';

        // Cálculo de bobinas y tara total
        if ($largura_valor < 1.0) {
            $bobinas_pacote = isset($datos['bobinas_pacote']) && !empty($datos['bobinas_pacote'])
                ? intval($datos['bobinas_pacote']) : 1;

            if ($bobinas_pacote < 1) {
                throw new Exception("Para productos de largura menor a 1 metro, debe especificar la cantidad de bobinas en el paquete (mínimo 1)");
            }

            $tara_total = $tara * $bobinas_pacote;
            $peso_liquido_calculado = $pesoBruto - $tara_total;
        } else {
            $bobinas_pacote = 1;
            $tara_total = $tara;
            $peso_liquido_calculado = $pesoBruto - $tara;
        }

        if ($peso_liquido_calculado <= 0) {
            throw new Exception("El peso líquido debe ser mayor a 0. Verifique el peso bruto y la tara.");
        }

        // 🆕 VALIDAR DISPONIBILIDAD DE MATERIAS PRIMAS ANTES DEL REGISTRO
        $datosParaValidacion = $datos;
        $datosParaValidacion['bobinas_pacote'] = $bobinas_pacote; // Asegurar que tenga el valor correcto

        $validacionMaterias = $this->validarDisponibilidadMateriasPrimas($datos['numero_orden'], $datosParaValidacion);
        if (!$validacionMaterias['puede_continuar']) {
            throw new Exception($this->construirMensajeStockInsuficiente($validacionMaterias));
        }

        $datosRegistro = [
            'numero_orden' => $datos['numero_orden'],
            'peso_bruto' => $pesoBruto,
            'peso_liquido' => $peso_liquido_calculado,
            'tara' => $tara_total,
            'metragem' => intval($datos['metragem']),
            'largura' => $producto['largura_metros'],
            'nombre_producto' => $producto['descripcion'],
            'gramatura' => $producto['gramatura'],
            'tipo_producto' => $tipoProducto,
            'bobinas_pacote' => $bobinas_pacote,
            'usuario' => $usuario,
        ];

        $resultadoRegistro = $this->productionRepo->registrarEnStock($datosRegistro);

        if ($resultadoRegistro['success']) {
            // 🆕 PROCESAR DESCUENTO DE MATERIAS PRIMAS (ahora sabemos que hay stock)
            $descuentoMaterias = $this->procesarDescuentoMateriasPrimas($datos['numero_orden'], $datosParaValidacion);

            $siguienteItem = $resultadoRegistro['numero_item'];

            // Intentar reserva automática
            $resultadoReserva = $this->realizarReservaAutomatica(
                $ordenEncontrada,
                $bobinas_pacote,
                $producto['descripcion'],
                $bobinas_pacote,
                $producto
            );

            // Generar URL del PDF
            $archivo_pdf = ($largura_valor > 0 && $largura_valor < 1.0) ? "pdf/etiquetatntAngosto.php" : "pdf/etiquetatntAncho.php";
            $tipoParaPdf = ($tipoProducto === 'LAMINADORA') ? 'TNT' : $tipoProducto;
            $pdf_url = "{$archivo_pdf}?id_orden=" . $datos['numero_orden'] . "&bobinas_pacote=" . $bobinas_pacote . "&tipo=" . urlencode($tipoParaPdf);

            $tipo_etiqueta = ($bobinas_pacote > 1) ? "paquete de $bobinas_pacote bobinas" : "bobina individual";
            $tipo_pdf = ($largura_valor > 0 && $largura_valor < 1.0) ? "angosto" : "ancho";

            // Construir mensaje
            $mensaje = "✅ $tipo_etiqueta #$siguienteItem registrada exitosamente!<br>";
            $mensaje .= "Peso Bruto: {$pesoBruto}kg | Peso Líquido: {$peso_liquido_calculado}kg | Tara Total: {$tara_total}kg<br>";
            $mensaje .= "Bobinas en paquete: $bobinas_pacote - Orden #{$datos['numero_orden']} - Tipo: {$tipoProducto} - Producto $tipo_pdf ({$largura_valor}m)<br>";

            // Información de reserva
            if ($resultadoReserva['reserva_creada']) {
                $mensaje .= "<span class='text-success'><i class='fas fa-lock me-1'></i><strong>Reserva automática creada para: {$resultadoReserva['cliente']}</strong></span><br>";
                $mensaje .= "<small class='text-info'>ID Reserva: {$resultadoReserva['id_reserva']} | Paquetes: {$resultadoReserva['paquetes_reservados']} | Bobinas: {$resultadoReserva['bobinas_reservadas']}</small><br>";
            } else {
                $mensaje .= "<span class='text-warning'><i class='fas fa-unlock me-1'></i><strong>Stock disponible para venta general</strong></span><br>";
                $mensaje .= "<small class='text-muted'>Motivo: {$resultadoReserva['motivo']}</small><br>";
            }

            $mensaje .= "<small class='text-info'><i class='fas fa-print me-1'></i>Generando etiqueta automáticamente...</small>";

            // Agregar información de materias primas al mensaje
            $mensaje .= $this->construirMensajeMaterias($descuentoMaterias);

            // 🆕 AGREGAR INFORMACIÓN DE VALIDACIÓN EXITOSA
            if ($validacionMaterias['success'] && !empty($validacionMaterias['materias_validadas'])) {
                $mensaje .= $this->construirMensajeValidacionExitosa($validacionMaterias);
            }

            return [
                'success' => true,
                'auto_print_url' => $pdf_url,
                'mensaje' => $mensaje,
                'error' => null
            ];
        }

        throw new Exception($resultadoRegistro['error']);
    }

    /**
     * 🆕 NUEVO: Validar disponibilidad de materias primas
     */
    private function validarDisponibilidadMateriasPrimas($numeroOrden, $datos)
    {
        // Verificar si MaterialConsumptionManager está disponible y activo
        if (!class_exists('MaterialConsumptionManager')) {
            return [
                'success' => false,
                'puede_continuar' => true,
                'mensaje' => 'MaterialConsumptionManager no disponible - Registro permitido sin validación'
            ];
        }

        try {
            $resultado = MaterialConsumptionManager::validarDisponibilidad($numeroOrden, $datos);

            // Agregar información adicional para debugging
            if ($resultado['success']) {
                $materiasValidadas = [];
                if (!empty($resultado['materias_faltantes'])) {
                    error_log("⚠️ VALIDACIÓN FALLIDA - Materias faltantes para orden $numeroOrden:");
                    foreach ($resultado['materias_faltantes'] as $faltante) {
                        error_log("   • {$faltante['materia_prima']}: Necesario {$faltante['necesario']} {$faltante['unidad']}, Disponible {$faltante['disponible']} {$faltante['unidad']}");
                    }
                } else {
                    // Si no hay faltantes, obtener las materias validadas
                    error_log("✅ VALIDACIÓN EXITOSA - Stock suficiente para orden $numeroOrden");
                    $materiasValidadas = $this->obtenerMateriasValidadas($numeroOrden, $datos);
                }

                $resultado['materias_validadas'] = $materiasValidadas;
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("💥 Error en validación de materias primas: " . $e->getMessage());
            return [
                'success' => false,
                'puede_continuar' => true,
                'mensaje' => 'Error en validación - Registro permitido: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 🆕 NUEVO: Obtener materias validadas (para mostrar información positiva)
     */
    private function obtenerMateriasValidadas($numeroOrden, $datos)
    {
        try {
            // Simular el proceso de cálculo para obtener las materias que SÍ tienen stock suficiente
            $pesoLiquido = $this->calcularPesoLiquido($datos);
            $cantidad = $this->calcularCantidadItems($datos);

            // Obtener debug info del MaterialConsumptionManager
            if (method_exists('MaterialConsumptionManager', 'debugOrden')) {
                $debugInfo = MaterialConsumptionManager::debugOrden($numeroOrden);
                if ($debugInfo['success']) {
                    $materiasValidadas = [];
                    foreach ($debugInfo['debug']['recetas'] as $receta) {
                        $cantidadNecesaria = $this->calcularCantidadNecesariaLocal($receta, $pesoLiquido, $cantidad);

                        // Determinar si hay stock suficiente
                        $stockSuficiente = false;
                        $stockDisponible = 0;
                        $unidad = 'kg';

                        if ($receta['es_extra']) {
                            $unidadExtra = strtoupper(trim($receta['unidad_extra']));
                            if ($unidadExtra === 'KILOS' || $unidadExtra === 'KILOGRAMOS') {
                                $stockDisponible = $receta['stock_peso'];
                                $stockSuficiente = $receta['stock_peso'] >= $cantidadNecesaria;
                                $unidad = 'kg';
                            } else if ($unidadExtra === 'UNIDAD' || $unidadExtra === 'UNIDADES') {
                                $stockDisponible = $receta['stock_cantidad'];
                                $stockSuficiente = $receta['stock_cantidad'] >= $cantidadNecesaria;
                                $unidad = 'unid';
                            }
                        } else {
                            $stockDisponible = $receta['stock_peso'];
                            $stockSuficiente = $receta['stock_peso'] >= $cantidadNecesaria;
                            $unidad = 'kg';
                        }

                        if ($stockSuficiente) {
                            $materiasValidadas[] = [
                                'nombre' => $receta['nombre'],
                                'cantidad_necesaria' => $cantidadNecesaria,
                                'stock_disponible' => $stockDisponible,
                                'unidad' => $unidad,
                                'es_extra' => $receta['es_extra']
                            ];
                        }
                    }
                    return $materiasValidadas;
                }
            }

            return [];
        } catch (Exception $e) {
            error_log("⚠️ Error obteniendo materias validadas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 🆕 NUEVO: Calcular cantidad necesaria (copia local del método del MaterialConsumptionManager)
     */
    private function calcularCantidadNecesariaLocal($receta, $pesoLiquido, $cantidad)
    {
        if ($receta['es_extra']) {
            $unidadExtra = strtoupper(trim($receta['unidad_extra']));
            if ($unidadExtra === 'KILOS' || $unidadExtra === 'KILOGRAMOS') {
                return $receta['cantidad_por_kilo'] * $pesoLiquido;
            } else if ($unidadExtra === 'UNIDAD' || $unidadExtra === 'UNIDADES') {
                return $receta['cantidad_por_kilo'] * $cantidad;
            }
        } else {
            // Materia prima normal: porcentaje del peso líquido
            $porcentajeDecimal = $receta['cantidad_por_kilo'] / 100.0;
            return $porcentajeDecimal * $pesoLiquido;
        }
        return 0;
    }

    /**
     * 🆕 NUEVO: Construir mensaje de stock insuficiente (CORREGIDO)
     */
    private function construirMensajeStockInsuficiente($validacionResult)
    {
        // NO incluir el div contenedor - la vista ya lo tiene
        $mensaje = "<h6><i class='fas fa-exclamation-triangle me-2'></i>STOCK INSUFICIENTE</h6>";
        $mensaje .= "<p class='mb-2'>No se puede registrar la producción por falta de materias primas:</p>";

        if (!empty($validacionResult['materias_faltantes'])) {
            $mensaje .= "<div class='bg-light p-3 rounded mt-2'>";
            $mensaje .= "<h6 class='text-danger mb-2'><i class='fas fa-box me-2'></i>MATERIAS PRIMAS FALTANTES:</h6>";

            foreach ($validacionResult['materias_faltantes'] as $faltante) {
                $deficit = $faltante['necesario'] - $faltante['disponible'];
                $mensaje .= "<div class='border-start border-danger ps-3 mb-2'>";
                $mensaje .= "<strong class='text-danger'>{$faltante['materia_prima']}:</strong><br>";
                $mensaje .= "<small class='text-muted'>";
                $mensaje .= "Necesario: <strong>{$faltante['necesario']} {$faltante['unidad']}</strong> | ";
                $mensaje .= "Disponible: <strong>{$faltante['disponible']} {$faltante['unidad']}</strong> | ";
                $mensaje .= "<span class='text-danger'>FALTA: <strong>{$deficit} {$faltante['unidad']}</strong></span>";
                $mensaje .= "</small>";
                $mensaje .= "</div>";
            }

            $mensaje .= "</div>";
            $mensaje .= "<div class='mt-3 p-2 bg-alert bg-opacity-10 rounded'>";
            $mensaje .= "<small><i class='fas fa-tools me-2'></i><strong>SOLUCIÓN:</strong> Agregue stock de las materias primas faltantes antes de continuar.</small>";
            $mensaje .= "</div>";
        } else {
            $mensaje .= "<div class='bg-light p-2 rounded'>";
            $mensaje .= "<small>Error en validación: " . ($validacionResult['mensaje'] ?? 'Error desconocido') . "</small>";
            $mensaje .= "</div>";
        }

        return $mensaje; // Sin el div contenedor
    }

    /**
     * 🆕 NUEVO: Construir mensaje de validación exitosa
     */
    private function construirMensajeValidacionExitosa($validacionResult)
    {
        if (empty($validacionResult['materias_validadas'])) {
            return "";
        }

        $mensaje = "<br><div class='alert alert-success mt-2'>";
        $mensaje .= "<i class='fas fa-check-circle me-2'></i>";
        $mensaje .= "<strong>Validación de Stock - OK:</strong><br>";

        foreach ($validacionResult['materias_validadas'] as $materia) {
            $tipo = $materia['es_extra'] ? "(Extra)" : "(Normal)";
            $mensaje .= "• {$materia['nombre']}: {$materia['cantidad_necesaria']} {$materia['unidad']} de {$materia['stock_disponible']} {$materia['unidad']} disponibles $tipo<br>";
        }

        $mensaje .= "</div>";
        return $mensaje;
    }

    /**
     * Calcular peso líquido
     */
    private function calcularPesoLiquido($datos)
    {
        $pesoBruto = floatval($datos['peso_bruto'] ?? 0);
        $tara = floatval($datos['tara'] ?? 0);

        if (isset($datos['bobinas_pacote']) && $datos['bobinas_pacote'] > 1) {
            $bobinas = intval($datos['bobinas_pacote']);
            return $pesoBruto - ($tara * $bobinas);
        }

        return $pesoBruto - $tara;
    }

    /**
     * Calcular cantidad de items
     */
    private function calcularCantidadItems($datos)
    {
        return isset($datos['bobinas_pacote']) && $datos['bobinas_pacote'] > 1
            ? intval($datos['bobinas_pacote'])
            : 1;
    }

    // ... resto de métodos sin cambios ...

    /**
     * 🔄 MOVIDO DEL CONTROLLER: Realizar reserva automática
     */
    public function realizarReservaAutomatica($ordenEncontrada, $bobinasProducidas, $nombreProducto, $bobinas_pacote, $producto)
    {
        try {
            $cliente = trim(strtoupper($ordenEncontrada['cliente'] ?? ''));

            // Solo reservar si NO es AMERICA TNT
            if ($cliente === 'AMERICA TNT' || $cliente === 'AMERICATNT' || empty($cliente)) {
                return [
                    'reserva_creada' => false,
                    'motivo' => 'Cliente AMERICA TNT - Stock queda disponible para venta general',
                    'error' => null
                ];
            }

            // Hacer reserva automática
            $sql = "SELECT * FROM reservar_stock_paquetes_mejorado(
                :nombre_producto,
                :bobinas_solicitadas,
                :bobinas_pacote,
                :id_venta,
                :cliente,
                :usuario
            )";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
            $stmt->bindParam(':bobinas_solicitadas', $bobinasProducidas, PDO::PARAM_INT);
            $stmt->bindParam(':bobinas_pacote', $bobinas_pacote, PDO::PARAM_INT);
            $stmt->bindParam(':id_venta', $producto['id_venta'], PDO::PARAM_INT);
            $stmt->bindParam(':cliente', $ordenEncontrada['cliente'], PDO::PARAM_STR);
            $usuario = $_SESSION['nombre'] ?? 'SISTEMA_PRODUCCION';
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado && $resultado['exito']) {
                error_log("🎯 Reserva automática creada - Cliente: {$cliente} - ID Reserva: {$resultado['id_reserva']}");

                return [
                    'reserva_creada' => true,
                    'id_reserva' => $resultado['id_reserva'],
                    'paquetes_reservados' => $resultado['paquetes_reservados'],
                    'bobinas_reservadas' => $resultado['bobinas_reservadas'],
                    'mensaje_reserva' => $resultado['mensaje'],
                    'cliente' => $cliente,
                    'error' => null
                ];
            } else {
                throw new Exception($resultado['mensaje'] ?? 'Error desconocido en reserva');
            }
        } catch (Exception $e) {
            error_log("⚠️ Error en reserva automática: " . $e->getMessage());
            return [
                'reserva_creada' => false,
                'motivo' => 'Error al crear reserva: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 🔄 MOVIDO DEL CONTROLLER: Procesar registro de producción
     */
    public function procesarRegistroProduccion($datos)
    {
        try {
            // Validar datos de entrada
            $this->validarFormularioRegistro($datos);

            $numeroOrden = $datos['numero_orden'];
            $resultado = $this->productionRepo->buscarOrdenCompleta($numeroOrden);

            if ($resultado['error']) {
                throw new Exception($resultado['error']);
            }

            $ordenEncontrada = $resultado['orden'];
            $productosOrden = $resultado['productos'];

            if (empty($productosOrden)) {
                throw new Exception("No se encontraron productos para esta orden");
            }

            $producto = $productosOrden[0];
            $tipoProducto = $producto['tipo'];

            // Procesar según tipo de producto
            if ($tipoProducto === 'TOALLITAS' || $tipoProducto === 'PAÑOS') {
                return $this->procesarToallitasYPanos($datos, $ordenEncontrada, $producto);
            } else {
                return $this->procesarTNTSpunlaceLaminadora($datos, $ordenEncontrada, $producto);
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => "❌ Error al registrar: " . $e->getMessage(),
                'auto_print_url' => null,
                'mensaje' => null
            ];
        }
    }

    /**
     * 🆕 NUEVO: Procesar descuento de materias primas
     */
    private function procesarDescuentoMateriasPrimas($numeroOrden, $datos)
    {
        // Verificar si MaterialConsumptionManager está disponible
        if (!class_exists('MaterialConsumptionManager')) {
            return ['success' => false, 'error' => 'MaterialConsumptionManager no disponible'];
        }

        try {
            return MaterialConsumptionManager::procesarDescuento($numeroOrden, $datos);
        } catch (Exception $e) {
            error_log("⚠️ Error procesando descuento de materias primas: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 🆕 NUEVO: Revertir descuento de materias primas (al eliminar registro)
     */
    private function revertirDescuentoMateriasPrimas($numeroOrden, $datosRegistro)
    {
        // Verificar si MaterialConsumptionManager está disponible
        if (!class_exists('MaterialConsumptionManager')) {
            return ['success' => false, 'error' => 'MaterialConsumptionManager no disponible'];
        }

        try {
            return MaterialConsumptionManager::revertirDescuento($numeroOrden, $datosRegistro);
        } catch (Exception $e) {
            error_log("⚠️ Error revirtiendo descuento de materias primas: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 🆕 NUEVO: Construir mensaje de materias primas
     */
    private function construirMensajeMaterias($descuentoResult)
    {
        if (!$descuentoResult['success']) {
            // Si hubo error, mostrar advertencia
            if (isset($descuentoResult['error'])) {
                return "<br><div class='alert alert-warning mt-2'>" .
                    "<i class='fas fa-exclamation-triangle me-2'></i>" .
                    "<strong>Advertencia - Materias Primas:</strong><br>" .
                    $descuentoResult['error'] .
                    "</div>";
            }
            return "";
        }

        // Si no hay descuentos realizados, no mostrar nada
        if (empty($descuentoResult['descuentos_realizados'])) {
            return "";
        }

        // Construir mensaje de descuentos exitosos
        $mensaje = "<br><div class='alert alert-info mt-2'>";
        $mensaje .= "<i class='fas fa-minus-circle me-2'></i>";
        $mensaje .= "<strong>Materias Primas Descontadas:</strong><br>";

        foreach ($descuentoResult['descuentos_realizados'] as $descuento) {
            $unidad = $descuento['tipo_descuento'] === 'peso' ? 'kg' : 'unid';
            $tipo = $descuento['es_materia_extra'] ? " (Extra: {$descuento['unidad_medida_extra']})" : " (Normal)";
            $mensaje .= "• {$descuento['nombre_materia_prima']}: -{$descuento['cantidad_descontada']} $unidad$tipo<br>";
        }

        $mensaje .= "</div>";
        return $mensaje;
    }

    /**
     * 🆕 NUEVO: Construir mensaje de reversión de materias primas
     */
    private function construirMensajeReversionMaterias($reversionResult)
    {
        if (!$reversionResult['success']) {
            // Si hubo error, mostrar advertencia
            if (isset($reversionResult['error'])) {
                return "<br><div class='alert alert-warning mt-2'>" .
                    "<i class='fas fa-exclamation-triangle me-2'></i>" .
                    "<strong>Advertencia - Reversión Materias Primas:</strong><br>" .
                    $reversionResult['error'] .
                    "</div>";
            }
            return "";
        }

        // Si no hay reversiones realizadas, no mostrar nada
        if (empty($reversionResult['reversiones_realizadas'])) {
            return "";
        }

        // Construir mensaje de reversiones exitosas
        $mensaje = "<br><div class='alert alert-success mt-2'>";
        $mensaje .= "<i class='fas fa-plus-circle me-2'></i>";
        $mensaje .= "<strong>Materias Primas Revertidas:</strong><br>";

        foreach ($reversionResult['reversiones_realizadas'] as $reversion) {
            $unidad = $reversion['tipo_descuento'] === 'peso' ? 'kg' : 'unid';
            $tipo = $reversion['es_materia_extra'] ? " (Extra: {$reversion['unidad_medida_extra']})" : " (Normal)";
            $mensaje .= "• {$reversion['nombre_materia_prima']}: +{$reversion['cantidad_revertida']} $unidad$tipo<br>";
        }

        $mensaje .= "</div>";
        return $mensaje;
    }

    /**
     * 🔄 CORREGIDO: Procesar eliminación de registro con reversión de materias primas
     */
    public function procesarEliminacionRegistro($datos)
    {
        try {
            $idRegistro = intval($datos['id_registro_eliminar']);
            $numeroOrden = intval($datos['numero_orden_eliminar']);

            // Validaciones básicas
            if ($idRegistro <= 0) {
                throw new Exception("ID de registro inválido");
            }

            if ($numeroOrden <= 0) {
                throw new Exception("Número de orden inválido");
            }

            // Verificar que el registro existe y pertenece a la orden
            $registroExistente = $this->productionRepo->verificarRegistroExistente($idRegistro, $numeroOrden);

            if (!$registroExistente['exists']) {
                throw new Exception("El registro no existe o no pertenece a esta orden");
            }

            $detallesRegistro = $registroExistente['registro'];

            // 🆕 CONSTRUIR DATOS COMPLETOS PARA REVERSIÓN
            $datosParaReversion = [
                'numero_orden' => $numeroOrden,
                'peso_bruto' => floatval($detallesRegistro['peso_bruto']),
                'tara' => floatval($detallesRegistro['tara']),
                'bobinas_pacote' => intval($detallesRegistro['bobinas_pacote']) // ✅ CORREGIDO: Ahora viene de la BD
            ];

            // Agregar campos adicionales si existen (para TNT/SPUNLACE/LAMINADORA)
            if (!empty($detallesRegistro['metragem'])) {
                $datosParaReversion['metragem'] = intval($detallesRegistro['metragem']);
            }

            if (!empty($detallesRegistro['largura'])) {
                $datosParaReversion['largura'] = floatval($detallesRegistro['largura']);
            }

            if (!empty($detallesRegistro['gramatura'])) {
                $datosParaReversion['gramatura'] = floatval($detallesRegistro['gramatura']);
            }

            // Agregar tipo de producto para el cálculo correcto
            $datosParaReversion['tipo_producto'] = $detallesRegistro['tipo_producto'];
            $datosParaReversion['nombre_producto'] = $detallesRegistro['nombre_producto'];

            // 🆕 LOG PARA DEBUG
            error_log("🔄 REVERSIÓN - Registro ID: $idRegistro, Bobinas Pacote: {$datosParaReversion['bobinas_pacote']}, Tipo: {$detallesRegistro['tipo_producto']}");

            // REVERTIR DESCUENTOS DE MATERIAS PRIMAS ANTES DE ELIMINAR
            $reversionMaterias = $this->revertirDescuentoMateriasPrimas($numeroOrden, $datosParaReversion);

            // Proceder con la eliminación
            $resultadoEliminacion = $this->productionRepo->eliminarRegistro($idRegistro, $numeroOrden);

            if ($resultadoEliminacion['success']) {
                // Construir mensaje detallado
                $mensaje = "✅ Registro #{$detallesRegistro['numero_item']} eliminado exitosamente<br>";
                $mensaje .= "Tipo: {$detallesRegistro['tipo_producto']} | ";
                $mensaje .= "Peso Bruto: {$detallesRegistro['peso_bruto']}kg | ";
                $mensaje .= "Peso Líquido: {$detallesRegistro['peso_liquido']}kg<br>";

                // Información sobre bobinas liberadas
                if ($resultadoEliminacion['bobinas_liberadas'] > 0) {
                    $mensaje .= "Bobinas liberadas: {$resultadoEliminacion['bobinas_liberadas']}<br>";
                }

                // Información sobre reserva eliminada
                if ($resultadoEliminacion['reserva_eliminada']) {
                    $mensaje .= "<span class='text-info'><i class='fas fa-unlock me-1'></i>Reserva automática eliminada (ID: {$resultadoEliminacion['id_reserva_afectada']})</span><br>";
                    $mensaje .= "<small class='text-muted'>Las cantidades han sido liberadas del stock reservado</small><br>";
                } else {
                    $mensaje .= "<small class='text-muted'>No se encontró reserva asociada al registro</small><br>";
                }

                $mensaje .= "<small class='text-warning'><i class='fas fa-exclamation-triangle me-1'></i>Registro y reserva eliminados permanentemente</small>";

                // 🆕 AGREGAR INFORMACIÓN DE REVERSIÓN DE MATERIAS PRIMAS
                $mensaje .= $this->construirMensajeReversionMaterias($reversionMaterias);

                return [
                    'success' => true,
                    'mensaje' => $mensaje,
                    'error' => null,
                    'registro_eliminado' => $detallesRegistro,
                    'reserva_eliminada' => $resultadoEliminacion['reserva_eliminada'],
                    'bobinas_liberadas' => $resultadoEliminacion['bobinas_liberadas'],
                    'materias_revertidas' => $reversionMaterias
                ];
            } else {
                throw new Exception($resultadoEliminacion['error']);
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => "❌ Error al eliminar registro: " . $e->getMessage(),
                'mensaje' => null,
                'registro_eliminado' => null,
                'reserva_eliminada' => false,
                'bobinas_liberadas' => 0,
                'materias_revertidas' => ['success' => false, 'error' => $e->getMessage()]
            ];
        }
    }

    /**
     * 🔄 MOVIDO DEL CONTROLLER: Procesar finalización de orden
     */
    public function procesarFinalizacionOrden($datos)
    {
        try {
            $numeroOrden = intval($datos['numero_orden_finalizar']);

            // Validaciones básicas
            if ($numeroOrden <= 0) {
                throw new Exception("Número de orden inválido");
            }

            // Verificar que la orden existe y no está finalizada
            $verificacion = $this->productionRepo->verificarOrdenFinalizada($numeroOrden);

            if (!$verificacion['exists']) {
                throw new Exception("La orden de producción #$numeroOrden no existe");
            }

            if ($verificacion['finalizado']) {
                throw new Exception("La orden de producción #$numeroOrden ya está finalizada");
            }

            // Proceder con la finalización
            $resultadoFinalizacion = $this->productionRepo->finalizarOrdenProduccion($numeroOrden);

            if ($resultadoFinalizacion['success']) {
                $cliente = $resultadoFinalizacion['cliente'];
                $estadoAnterior = $resultadoFinalizacion['estado_anterior'];

                $mensaje = "🏁 <strong>Orden de Producción #$numeroOrden FINALIZADA</strong><br>";
                $mensaje .= "Cliente: $cliente<br>";
                $mensaje .= "Estado anterior: $estadoAnterior → Completado<br>";
                $mensaje .= "<small class='text-success'><i class='fas fa-check-circle me-1'></i>La orden ha sido marcada como finalizada en el sistema</small>";

                return [
                    'success' => true,
                    'mensaje' => $mensaje,
                    'error' => null,
                    'orden_finalizada' => $resultadoFinalizacion
                ];
            } else {
                throw new Exception($resultadoFinalizacion['error']);
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => "❌ Error al finalizar orden: " . $e->getMessage(),
                'mensaje' => null,
                'orden_finalizada' => null
            ];
        }
    }

    /**
     * 🔄 MOVIDO DEL CONTROLLER: Validar formulario de registro
     */
    private function validarFormularioRegistro($datos)
    {
        if (!isset($datos['numero_orden']) || empty($datos['numero_orden'])) {
            throw new Exception("Número de orden requerido");
        }

        if (!isset($datos['peso_bruto']) || $datos['peso_bruto'] <= 0) {
            throw new Exception("Peso bruto debe ser mayor a 0");
        }

        if (!isset($datos['tara']) || $datos['tara'] < 0) {
            throw new Exception("Tara debe ser mayor o igual a 0");
        }

        $pesoLiquido = $datos['peso_bruto'] - $datos['tara'];
        if ($pesoLiquido <= 0) {
            throw new Exception("El peso líquido debe ser mayor a 0. Verifique el peso bruto y la tara.");
        }
    }

    /**
     * 🆕 NUEVO: Validar rango de items para reimpresión en lote
     */
    public function validarRangoItems($numeroOrden, $itemDesde, $itemHasta)
    {
        try {
            // Validaciones básicas
            if ($itemDesde <= 0 || $itemHasta <= 0) {
                throw new Exception("Los números de item deben ser mayores a 0");
            }

            if ($itemDesde > $itemHasta) {
                throw new Exception("El item 'desde' no puede ser mayor al item 'hasta'");
            }

            if (($itemHasta - $itemDesde + 1) > 100) {
                throw new Exception("No se pueden reimprimir más de 100 etiquetas a la vez");
            }

            // Verificar que los items existen en la base de datos
            $itemsExistentes = $this->productionRepo->verificarRangoItems($numeroOrden, $itemDesde, $itemHasta);

            if (empty($itemsExistentes)) {
                throw new Exception("No se encontraron items en el rango especificado para esta orden");
            }

            // Verificar items faltantes en el rango
            $itemsEsperados = range($itemDesde, $itemHasta);
            $itemsEncontrados = array_column($itemsExistentes, 'numero_item');
            $itemsFaltantes = array_diff($itemsEsperados, $itemsEncontrados);

            return [
                'success' => true,
                'items_existentes' => $itemsExistentes,
                'items_faltantes' => $itemsFaltantes,
                'total_encontrados' => count($itemsExistentes),
                'total_solicitados' => count($itemsEsperados)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'items_existentes' => [],
                'items_faltantes' => [],
                'total_encontrados' => 0,
                'total_solicitados' => 0
            ];
        }
    }

    /**
     * 🆕 NUEVO: Obtener estadísticas de producción
     */
    public function obtenerEstadisticasProduccion($ordenEncontrada, $productosOrden)
    {
        if ($ordenEncontrada && !empty($productosOrden)) {
            return $this->productionRepo->obtenerEstadisticasProduccion(
                $ordenEncontrada['id'],
                $productosOrden[0]
            );
        }
        return ['success' => false];
    }

    /**
     * 🆕 NUEVO: Obtener diferencia de peso
     */
    public function obtenerDiferenciaPeso($ordenEncontrada, $productosOrden)
    {
        if ($ordenEncontrada && !empty($productosOrden)) {
            return $this->productionRepo->obtenerDiferenciaPeso(
                $ordenEncontrada['id'],
                $productosOrden[0]
            );
        }
        return ['success' => false];
    }

    /**
     * 🆕 NUEVO: Construir mensaje de registro con información de reserva
     */
    private function construirMensajeRegistro($tipoEtiqueta, $siguienteItem, $pesoBruto, $pesoLiquido, $tara, $numeroOrden, $tipoProducto, $resultadoReserva, $bobinas_pacote = 1)
    {
        $mensaje = "✅ $tipoEtiqueta #$siguienteItem registrada exitosamente!<br>";
        $mensaje .= "Peso Bruto: {$pesoBruto}kg | Peso Líquido: {$pesoLiquido}kg | Tara: {$tara}kg<br>";

        if ($bobinas_pacote > 1) {
            $mensaje .= "Bobinas en paquete: $bobinas_pacote - ";
        }

        $mensaje .= "Orden #{$numeroOrden} - {$tipoProducto}<br>";

        // Información de reserva
        if ($resultadoReserva['reserva_creada']) {
            $mensaje .= "<span class='text-success'><i class='fas fa-lock me-1'></i><strong>Reserva automática creada para: {$resultadoReserva['cliente']}</strong></span><br>";
            $mensaje .= "<small class='text-info'>ID Reserva: {$resultadoReserva['id_reserva']} | Bobinas reservadas: {$resultadoReserva['bobinas_reservadas']}</small><br>";
        } else {
            $mensaje .= "<span class='text-warning'><i class='fas fa-unlock me-1'></i><strong>Stock disponible para venta general</strong></span><br>";
            $mensaje .= "<small class='text-muted'>Motivo: {$resultadoReserva['motivo']}</small><br>";
        }

        $mensaje .= "<small class='text-info'><i class='fas fa-print me-1'></i>Imprimiendo etiqueta automáticamente...</small>";

        return $mensaje;
    }
    /**
     * 🆕 NUEVO: Verificar si orden tiene receta activa
     */
    public function verificarRecetaActiva($numeroOrden)
    {
        // Verificar si MaterialConsumptionManager está disponible
        if (!class_exists('MaterialConsumptionManager')) {
            return [
                'tiene_receta' => false,
                'estado' => 'sin_sistema',
                'mensaje' => 'Sistema de recetas no disponible'
            ];
        }

        try {
            // Usar el método debug existente para verificar si hay recetas
            if (method_exists('MaterialConsumptionManager', 'debugOrden')) {
                $debugInfo = MaterialConsumptionManager::debugOrden($numeroOrden);

                if ($debugInfo['success'] && !empty($debugInfo['debug']['recetas'])) {
                    return [
                        'tiene_receta' => true,
                        'estado' => 'activa',
                        'total_recetas' => count($debugInfo['debug']['recetas']),
                        'mensaje' => 'Receta configurada - ' . count($debugInfo['debug']['recetas']) . ' materia(s)'
                    ];
                }
            }

            return [
                'tiene_receta' => false,
                'estado' => 'sin_receta',
                'mensaje' => 'Sin receta configurada'
            ];
        } catch (Exception $e) {
            return [
                'tiene_receta' => false,
                'estado' => 'error',
                'mensaje' => 'Error verificando receta'
            ];
        }
    }

    public function obtenerDatosPesoTeorico($ordenEncontrada, $bobinasPacote = null, $metragem = null)
    {
        if ($ordenEncontrada) {
            if ($bobinasPacote === null) {
                $bobinasPacote = 1;
            }

            return $this->productionRepo->obtenerPesoTeoricoOrden(
                $ordenEncontrada['id'],
                $bobinasPacote,
                $metragem // Nuevo parámetro para metragem dinámico
            );
        }

        return [
            'success' => false,
            'peso_teorico' => 0,
            'peso_promedio' => 0, // Mantener compatibilidad
            'rango_15_inferior' => 0,
            'rango_15_superior' => 0,
            'total_registros' => 0,
            'bobinas_pacote' => $bobinasPacote ?? 1,
            'error' => 'No hay orden cargada'
        ];
    }

    // Getters para acceder al repository y print service si es necesario
    public function getProductionRepo()
    {
        return $this->productionRepo;
    }

    public function getPrintService()
    {
        return $this->printService;
    }
}
