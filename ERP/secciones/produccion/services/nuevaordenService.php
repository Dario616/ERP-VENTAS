<?php

/**
 * Service para lógica de negocio de órdenes de producción - CON CANTIDAD DIRECTA COMO BOBINAS
 */
class NuevaOrdenService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    /**
     * ⭐ ACTUALIZADA: Función de conversión SOLO para TOALLITAS ⭐
     * TNT, SPUNLACE y LAMINADORA usan cantidad directa como bobinas (SIN conversión)
     */
    public function convertirAKilos($cantidad, $unidadMedida, $descripcion, $pesoBobina = 0)
    {
        $unidad = strtolower(trim($unidadMedida));

        // ⚠️ NOTA: Esta función solo se usa para TOALLITAS
        // TNT, SPUNLACE y LAMINADORA procesan cantidad directa como bobinas

        if (in_array($unidad, ['kg', 'kilos', 'kilo', 'kilogramos', 'kilogramo'])) {
            error_log("CONVERSION TOALLITAS - Ya en kilos: {$cantidad} kg");
            return (float)$cantidad;
        }

        if (in_array($unidad, ['unidad', 'un', 'unidades', 'bobina', 'bobinas', 'rollo', 'rollos'])) {
            $kilos = $cantidad * $pesoBobina;
            error_log("CONVERSION TOALLITAS - Unidades a kilos: {$cantidad} × {$pesoBobina} = {$kilos} kg");
            return (float)$kilos;
        }

        if (in_array($unidad, ['metros', 'metro', 'm'])) {
            $kilos = $this->convertirMetrosAKilos($cantidad, $descripcion);
            error_log("CONVERSION TOALLITAS - Metros a kilos: {$cantidad}m = {$kilos} kg");
            return (float)$kilos;
        }

        error_log("CONVERSION TOALLITAS - Unidad desconocida '{$unidad}', asumiendo kilos: {$cantidad} kg");
        return (float)$cantidad;
    }
    private function convertirMetrosAKilos($cantidad, $descripcion)
    {
        $gramatura = null;
        $largura = null;

        if (preg_match('/(\d+(?:[,.]?\d+)?)\s*g\/m²/i', $descripcion, $matches)) {
            $gramatura = (float)str_replace(',', '.', $matches[1]);
        } elseif (preg_match('/(\d+)\s*GR/i', $descripcion, $matches)) {
            $gramatura = (float)$matches[1];
        }

        if (preg_match('/Ancho\s+(\d+(?:[,.]?\d+)?)\s*cm/i', $descripcion, $matches)) {
            $largura = (float)str_replace(',', '.', $matches[1]) / 100;
        } elseif (preg_match('/(\d+[,.]?\d*)\s*CM/i', $descripcion, $matches)) {
            $largura = (float)str_replace(',', '.', $matches[1]) / 100;
        }

        if ($gramatura && $largura) {
            $kilos = ($cantidad * $largura * $gramatura) / 1000;
            return $kilos;
        } else {
            // Fallback
            return $cantidad / 1000;
        }
    }

    /**
     * ⭐ ACTUALIZADO: Detección de tipo con soporte completo para PAÑOS Y LAMINADORA ⭐
     */
    public function detectarTipoProducto($descripcion, $tipoBD = null)
    {
        error_log("=== DETECCIÓN TIPO CON SOPORTE COMPLETO ===");
        error_log("Tipo BD recibido: " . ($tipoBD ? "'{$tipoBD}'" : "NULL"));

        // ✅ PRIORIDAD 1: Usar tipo de la BD (siempre confiable)
        if ($tipoBD && trim($tipoBD) !== '') {
            $tipoNormalizado = strtoupper(trim($tipoBD));

            // Normalizar solo caracteres especiales si es necesario
            $tipoNormalizado = str_replace(['ñ', 'Ñ'], ['Ñ', 'Ñ'], $tipoNormalizado);

            error_log("✅ USANDO TIPO DE BD: '{$tipoNormalizado}'");
            return $tipoNormalizado;
        }

        // ⚠️ FALLBACK: Solo si no hay tipo en BD - incluir detección completa
        error_log("⚠️ NO HAY TIPO EN BD, analizando descripción");

        $descripcionUpper = strtoupper($descripcion);

        // Detectar LAMINADORA
        if (
            strpos($descripcionUpper, 'LAMINAD') !== false ||
            strpos($descripcionUpper, 'LAMINADORA') !== false
        ) {
            error_log("✅ DETECTADO COMO LAMINADORA por descripción");
            return 'LAMINADORA';
        }

        // Detectar PAÑOS
        if (
            strpos($descripcionUpper, 'PAÑO') !== false ||
            strpos($descripcionUpper, 'PANO') !== false ||
            strpos($descripcionUpper, 'PAÑOS') !== false ||
            strpos($descripcionUpper, 'PANOS') !== false
        ) {
            error_log("✅ DETECTADO COMO PAÑOS por descripción");
            return 'PAÑOS';
        }

        // Detectar TOALLITAS
        if (
            strpos($descripcionUpper, 'TOALLITA') !== false ||
            strpos($descripcionUpper, 'TOALLA') !== false
        ) {
            error_log("✅ DETECTADO COMO TOALLITAS por descripción");
            return 'TOALLITAS';
        }

        // Detectar SPUNLACE
        if (
            strpos($descripcionUpper, 'SPUNLACE') !== false ||
            strpos($descripcionUpper, 'VISCOSE') !== false
        ) {
            error_log("✅ DETECTADO COMO SPUNLACE por descripción");
            return 'SPUNLACE';
        }

        // Por defecto TNT
        error_log("⚠️ USANDO FALLBACK TNT");
        return 'TNT';
    }

    /**
     * Extracción automática de especificaciones TNT
     */
    public function extraerEspecificacionesTNT($descripcion)
    {
        $specs = [
            'gramatura' => null,
            'largura' => null,
            'longitud' => null,
            'color' => 'Blanco',
            'peso_bobina' => 50
        ];

        if (preg_match('/(\d+(?:[,.]?\d+)?)\s*g\/m²/i', $descripcion, $matches)) {
            $specs['gramatura'] = str_replace(',', '.', $matches[1]);
        } elseif (preg_match('/(\d+)\s*GR/i', $descripcion, $matches)) {
            $specs['gramatura'] = $matches[1];
        }

        if (preg_match('/Ancho\s+(\d+(?:[,.]?\d+)?)\s*cm/i', $descripcion, $matches)) {
            $specs['largura'] = str_replace(',', '.', $matches[1]) / 100;
        } elseif (preg_match('/(\d+[,.]?\d*)\s*CM/i', $descripcion, $matches)) {
            $specs['largura'] = str_replace(',', '.', $matches[1]) / 100;
        }

        if (preg_match('/Rollo\s+de\s+(\d+)\s*metros/i', $descripcion, $matches)) {
            $specs['longitud'] = $matches[1];
        } elseif (preg_match('/(\d+)\s*METROS/i', $descripcion, $matches)) {
            $specs['longitud'] = $matches[1];
        }

        if (preg_match('/Color\s+(\w+(?:\s+\w+)*)\s*$/i', $descripcion, $matches)) {
            $specs['color'] = trim($matches[1]);
        } elseif (preg_match('/\b(blanco|negro|azul|verde|rojo|amarillo|gris|rosa|morado|celeste)\b/i', $descripcion, $matches)) {
            $specs['color'] = ucfirst(strtolower($matches[1]));
        }

        if ($specs['gramatura'] && $specs['largura'] && $specs['longitud']) {
            $specs['peso_bobina'] = ($specs['gramatura'] * $specs['largura'] * $specs['longitud']) / 1000;
        }

        return $specs;
    }

    /**
     * Extracción automática de especificaciones Spunlace
     */
    public function extraerEspecificacionesSpunlace($descripcion)
    {
        $specs = [
            'gramatura' => null,
            'largura' => null,
            'longitud' => null,
            'acabado' =>  null,
            'color' => 'Blanco',
            'peso_bobina' => null
        ];

        // 1. Extraer gramatura (ej: "45 g/m2" o "45 g/m²")
        if (preg_match('/(\d+(?:[.,]?\d+)?)\s*g\/m[²2]/i', $descripcion, $matches)) {
            $specs['gramatura'] = str_replace(',', '.', $matches[1]);
        }

        // 2. Extraer largura/ancho (ej: "Ancho 25 cm")
        if (preg_match('/Ancho\s+(\d+(?:[.,]?\d+)?)\s*cm/i', $descripcion, $matches)) {
            $specs['largura'] = str_replace(',', '.', $matches[1]) / 100;
        }

        // 3. Extraer longitud (ej: "Rollo de 4000 metros")
        if (preg_match('/Rollo\s+de\s+(\d+)\s*metros/i', $descripcion, $matches)) {
            $specs['longitud'] = (int)$matches[1];
        }

        // 4. Extraer acabado (lo que viene después de la composición)
        if (preg_match('/(?:\d+%?\s+(?:POLIESTER|VISCOSE|PET|PP|PULPA)\s*){1,}\s*(.*)$/i', $descripcion, $matches)) {
            $acabado_potencial = trim($matches[1]);
            if (!empty($acabado_potencial)) {
                $specs['acabado'] = $acabado_potencial;
            }
        }

        // 5. Calcular el peso de la bobina si se tienen todos los datos necesarios
        if ($specs['gramatura'] && $specs['largura'] && $specs['longitud']) {
            $specs['peso_bobina'] = ($specs['gramatura'] * $specs['largura'] * $specs['longitud']) / 1000;
        }

        return $specs;
    }

    /**
     * ⭐ CORREGIDO: extraerEspecificacionesLaminadora() usa las mismas que TNT ⭐
     */
    public function extraerEspecificacionesLaminadora($descripcion)
    {
        // LAMINADORA tiene las mismas especificaciones que TNT
        return $this->extraerEspecificacionesTNT($descripcion);
    }

    /**
     * ⭐ ACTUALIZADA: Extracción automática de especificaciones Paños ⭐
     */
    public function extraerEspecificacionesPanos($descripcion)
    {
        $specs = [
            'gramatura' => null,
            'ancho' => null,
            'largo' => null,
            'color' => 'Blanco',
            'peso_bobina' => 50,
            'cant_panos' => null
        ];

        error_log("EXTRAYENDO ESPECIFICACIONES PAÑOS - Descripción: {$descripcion}");

        // ⭐ EXTRACCIÓN DE GRAMATURA ⭐
        if (preg_match('/(\d+(?:[,.]?\d+)?)\s*g\/m[²2]/i', $descripcion, $matches)) {
            $specs['gramatura'] = str_replace(',', '.', $matches[1]);
            error_log("GRAMATURA DETECTADA: {$specs['gramatura']} desde '{$matches[0]}'");
        } elseif (preg_match('/(\d+(?:[,.]?\d+)?)\s*GR/i', $descripcion, $matches)) {
            $specs['gramatura'] = str_replace(',', '.', $matches[1]);
            error_log("GRAMATURA DETECTADA (GR): {$specs['gramatura']} desde '{$matches[0]}'");
        }

        // Extraer dimensiones (formato NxN como 28x40, 45x80)
        if (preg_match('/(\d+)\s*x\s*(\d+)/i', $descripcion, $matches)) {
            $specs['ancho'] = (int)$matches[1];  // 28, 45 en los ejemplos
            $specs['largo'] = (int)$matches[2];  // 40, 80 en los ejemplos
            error_log("DIMENSIONES DETECTADAS: {$specs['ancho']}x{$specs['largo']} desde '{$matches[0]}'");
        }

        // ⭐ NUEVA: Extraer cantidad de paños mejorada ⭐
        $specs['cant_panos'] = $this->extraerCantidadPanos($descripcion);

        // Extraer color mejorado
        if (preg_match('/(\w+)\s+C\/\d+/i', $descripcion, $matches)) {
            $specs['color'] = trim($matches[1]);
        } elseif (preg_match('/\s(Blanco|Azul|Verde|Amarillo|Rojo|Rosa|Negro|Gris|Branco)(?:\s|$)/i', $descripcion, $matches)) {
            $specs['color'] = trim($matches[1]);
        }

        // Normalizar colores comunes
        $coloresComunes = [
            'blanco' => 'Blanco',
            'branco' => 'Blanco',  // Portugués
            'azul' => 'Azul',
            'verde' => 'Verde',
            'rojo' => 'Rojo',
            'amarillo' => 'Amarillo',
            'negro' => 'Negro'
        ];

        if (isset($coloresComunes[strtolower($specs['color'])])) {
            $specs['color'] = $coloresComunes[strtolower($specs['color'])];
        }

        error_log("ESPECIFICACIONES PAÑOS COMPLETAS: " . json_encode($specs));

        return $specs;
    }

    /**
     * ⭐ NUEVA: Extraer cantidad de paños de la descripción ⭐
     */
    private function extraerCantidadPanos($descripcion)
    {
        // Buscar patrones como "450 panos", "100 paños", etc.
        if (preg_match('/(\d+)\s*pa[ñn]os/i', $descripcion, $matches)) {
            $cantidad = (int)$matches[1];
            error_log("CANT_PANOS DETECTADA: {$cantidad} desde '{$matches[0]}'");
            return $cantidad;
        }

        // Si no encuentra, intentar extraer el primer número grande
        if (preg_match('/(\d{2,})\s*(?:und|unidades|pcs|pieces)?/i', $descripcion, $matches)) {
            $numero = (int)$matches[1];
            // Solo si es un número razonable para cantidad de paños (entre 10 y 10000)
            if ($numero >= 10 && $numero <= 10000) {
                error_log("CANT_PANOS INFERIDA: {$numero} desde '{$matches[0]}'");
                return $numero;
            }
        }

        error_log("CANT_PANOS NO DETECTADA en: '{$descripcion}'");
        return null;
    }

    /**
     * ⭐ CORREGIDO: TNT siempre interpreta cantidad como BOBINAS (sin conversión) ⭐
     */
    public function procesarProductoTNT($idOrdenProduccion, $datos, $productoBD = null)
    {
        $specs = $this->extraerEspecificacionesTNT($datos['descripcion']);

        $peso_bobina = $productoBD ? $productoBD['stock_actual'] : $specs['peso_bobina'];

        // ⭐ CORRECCIÓN: La cantidad ingresada SIEMPRE son bobinas directamente ⭐
        $cantidadBobinas = (float)$datos['cantidad']; // NO convertir, asumir bobinas
        $cantidad_kilos = $cantidadBobinas * $peso_bobina; // Calcular peso total

        $peso_min_bobina = $peso_bobina - ($peso_bobina * 0.03);

        // ⭐ LOGGING CORREGIDO ⭐
        error_log("TNT - Producto BD: " . ($productoBD ? "SI (ID: {$productoBD['id']})" : "NO"));
        error_log("TNT - Peso líquido por bobina: {$peso_bobina} kg");
        error_log("TNT - Input directo: {$datos['cantidad']} = {$cantidadBobinas} bobinas (SIN conversión)");
        error_log("TNT - Resultado final: {$cantidadBobinas} bobinas × {$peso_bobina} kg = {$cantidad_kilos} kg total");

        $datosTNT = [
            'gramatura' => $specs['gramatura'],
            'largura' => $specs['largura'],
            'longitud' => $specs['longitud'],
            'color' => $specs['color'],
            'peso_bobina' => $peso_bobina,
            'cantidad_total' => $cantidad_kilos, // Total en kg
            'total_bobinas' => round($cantidadBobinas), // ⭐ REDONDEAR para evitar decimales en INTEGER
            'peso_min_bobina' => $peso_min_bobina,
            'nombre' => $datos['descripcion']
        ];

        $this->repository->insertarProductoTNT($idOrdenProduccion, $datosTNT);

        return [
            'id_orden' => $idOrdenProduccion,
            'tipo' => 'TNT',
            'descripcion' => $datos['descripcion'],
            'cantidad' => $datos['cantidad'],
            'unidad' => $datos['unidad_medida'],
            'specs_extraidas' => $specs,
            'producto_bd' => $productoBD,
            'stock_actual' => $productoBD ? $productoBD['stock_actual'] : 'N/A',
            'bobinas_calculadas' => $cantidadBobinas,
            'peso_total_calculado' => $cantidad_kilos
        ];
    }

    /**
     * Procesamiento de producto Spunlace
     */

    public function procesarProductoSpunlace($idOrdenProduccion, $datos, $productoBD = null)
    {
        $specs = $this->extraerEspecificacionesSpunlace($datos['descripcion']);

        $peso_bobina = $productoBD ? $productoBD['stock_actual'] : $specs['peso_bobina'];

        // ⭐ CORRECCIÓN: La cantidad ingresada SIEMPRE son bobinas directamente ⭐
        $cantidadBobinas = (float)$datos['cantidad']; // NO convertir, asumir bobinas
        $cantidad_kilos = $cantidadBobinas * $peso_bobina; // Calcular peso total

        $peso_min_bobina = $peso_bobina - ($peso_bobina * 0.03);

        // ⭐ LOGGING CORREGIDO ⭐
        error_log("SPUNLACE - Producto BD: " . ($productoBD ? "SI (ID: {$productoBD['id']})" : "NO"));
        error_log("SPUNLACE - Peso líquido por bobina: {$peso_bobina} kg");
        error_log("SPUNLACE - Input directo: {$datos['cantidad']} = {$cantidadBobinas} bobinas (SIN conversión)");
        error_log("SPUNLACE - Resultado final: {$cantidadBobinas} bobinas × {$peso_bobina} kg = {$cantidad_kilos} kg total");

        $datosSpunlace = [
            'gramatura' => $specs['gramatura'],
            'largura' => $specs['largura'],
            'longitud' => $specs['longitud'],
            'color' => $specs['color'],
            'peso_bobina' => $peso_bobina,
            'cantidad_total' => $cantidad_kilos, // Total en kg
            'total_bobinas' => round($cantidadBobinas), // ⭐ REDONDEAR para evitar decimales en INTEGER
            'peso_min_bobina' => $peso_min_bobina,
            'acabado' => $specs['acabado'],
            'nombre' => $datos['descripcion']
        ];

        $this->repository->insertarProductoSpunlace($idOrdenProduccion, $datosSpunlace);

        return [
            'id_orden' => $idOrdenProduccion,
            'tipo' => 'SPUNLACE',
            'descripcion' => $datos['descripcion'],
            'cantidad' => $datos['cantidad'],
            'unidad' => $datos['unidad_medida'],
            'specs_extraidas' => $specs,
            'producto_bd' => $productoBD,
            'stock_actual' => $productoBD ? $productoBD['stock_actual'] : 'N/A',
            'bobinas_calculadas' => $cantidadBobinas, // ⭐ NUEVO: Incluir bobinas calculadas
            'peso_total_calculado' => $cantidad_kilos  // ⭐ NUEVO: Incluir peso total calculado
        ];
    }

    /**
     * ⭐ CORREGIDO: LAMINADORA usa TNT con cantidad directa como bobinas ⭐
     */
    public function procesarProductoLaminadora($idOrdenProduccion, $datos, $productoBD = null)
    {
        // LAMINADORA usa exactamente el mismo procesamiento que TNT (cantidad directa como bobinas)
        // pero cambiamos el tipo en el resultado para identificarlo correctamente
        $resultado = $this->procesarProductoTNT($idOrdenProduccion, $datos, $productoBD);

        // Cambiar el tipo en el resultado para que se identifique como LAMINADORA
        if ($resultado) {
            $resultado['tipo'] = 'LAMINADORA';

            // ⭐ LOGGING ESPECÍFICO PARA LAMINADORA ⭐
            error_log("LAMINADORA - Procesado como TNT (cantidad directa como bobinas) con identificación LAMINADORA");
            error_log("LAMINADORA - Bobinas finales: {$resultado['bobinas_calculadas']} bobinas = {$resultado['peso_total_calculado']} kg");
        }

        return $resultado;
    }

    /**
     * Procesamiento de producto Toallitas
     */
    public function procesarProductoToallitas($idOrdenProduccion, $datos, $productoBD = null)
    {
        $datosToallitas = [
            'nombre' => $datos['descripcion'],
            'cantidad_total' => $datos['cantidad']
        ];

        $this->repository->insertarProductoToallitas($idOrdenProduccion, $datosToallitas);

        return [
            'id_orden' => $idOrdenProduccion,
            'tipo' => 'TOALLITAS',
            'descripcion' => $datos['descripcion'],
            'cantidad' => $datos['cantidad'],
            'unidad' => $datos['unidad_medida'],
            'producto_bd' => $productoBD,
            'stock_actual' => $productoBD ? $productoBD['stock_actual'] : 'N/A'
        ];
    }

    /**
     * ⭐ CORREGIDO: Procesamiento de producto Paños - SIN conversión como TOALLITAS ⭐
     */
    public function procesarProductoPanos($idOrdenProduccion, $datos, $productoBD = null)
    {
        error_log("=== PROCESANDO PRODUCTO PAÑOS SIN CONVERSIÓN ===");
        error_log("ID Orden: {$idOrdenProduccion}");
        error_log("Descripción: {$datos['descripcion']}");
        error_log("Cantidad ORIGINAL: {$datos['cantidad']}");
        error_log("Unidad: {$datos['unidad_medida']}");

        $specs = $this->extraerEspecificacionesPanos($datos['descripcion']);

        $peso_bobina = $productoBD ? $productoBD['stock_actual'] : $specs['peso_bobina'];

        // ⭐ CAMBIO CRÍTICO: NO convertir a kilos - guardar cantidad original como TOALLITAS ⭐
        $cantidadOriginal = (float)$datos['cantidad']; // SIN conversión

        error_log("PAÑOS - Producto BD: " . ($productoBD ? "SI (ID: {$productoBD['id']})" : "NO"));
        error_log("PAÑOS - Cantidad SIN CONVERSIÓN: {$cantidadOriginal}");
        error_log("PAÑOS - Unidad original mantenida: {$datos['unidad_medida']}");

        // ✅ DATOS CORRECTOS - cantidad_total SIN conversión
        $datosPanos = [
            'nombre' => $datos['descripcion'],
            'cantidad_total' => $cantidadOriginal, // ⭐ SIN CONVERSIÓN - igual que toallitas
            'color' => $specs['color'],
            'largura' => $specs['ancho'], // ancho -> largura
            'picotado' => $specs['largo'], // largo -> picotado
            'cant_panos' => $specs['cant_panos'],
            'unidad' => $datos['unidad_medida'], // ⭐ MANTENER unidad original (cajas, unidades, etc.)
            'peso' => $peso_bobina,
            'gramatura' => $specs['gramatura']
        ];

        error_log("PAÑOS - Datos FINALES (SIN conversión): " . json_encode($datosPanos));

        $resultado = $this->repository->insertarProductoPanos($idOrdenProduccion, $datosPanos);

        if ($resultado) {
            error_log("PAÑOS - ✅ INSERCIÓN EXITOSA SIN CONVERSIÓN");
        } else {
            error_log("PAÑOS - ❌ ERROR EN INSERCIÓN");
        }

        return [
            'id_orden' => $idOrdenProduccion,
            'tipo' => 'PAÑOS',
            'descripcion' => $datos['descripcion'],
            'cantidad' => $datos['cantidad'], // ⭐ Cantidad original
            'unidad' => $datos['unidad_medida'], // ⭐ Unidad original
            'specs_extraidas' => $specs,
            'producto_bd' => $productoBD,
            'stock_actual' => $productoBD ? $productoBD['stock_actual'] : 'N/A'
        ];
    }

    /**
     * ⭐ ACTUALIZADO: Crear orden con soporte completo para PAÑOS, LAMINADORA Y CAJAS ⭐
     */
    public function crearOrdenProduccion($datos)
    {
        try {
            $this->repository->beginTransaction();

            $productoBD = $this->repository->buscarProductoEnBD($datos['descripcion']);

            if (!$productoBD) {
                throw new Exception('Solo se pueden crear órdenes con productos existentes en la base de datos.');
            }

            // ✅ DETECCIÓN MEJORADA CON SOPORTE COMPLETO
            $tipoProducto = $this->detectarTipoProducto($datos['descripcion'], $productoBD['tipo']);

            error_log("=== CREANDO ORDEN PRODUCCIÓN COMPLETA ===");
            error_log("Producto BD - Tipo: '{$productoBD['tipo']}'");
            error_log("Tipo detectado: '{$tipoProducto}'");
            error_log("Unidad medida: '{$datos['unidad_medida']}'");

            // Crear orden principal
            $idOrdenProduccion = $this->repository->crearOrdenProduccionPrincipal(
                date('Y-m-d H:i:s'),
                $datos['observaciones'],
                'AMERICA TNT'
            );

            $datosProducto = [
                'descripcion' => $datos['descripcion'],
                'cantidad' => $datos['cantidad'],
                'unidad_medida' => $datos['unidad_medida']
            ];

            $ordenGenerada = null;

            // ✅ PROCESAMIENTO CON SOPORTE COMPLETO - LAMINADORA usa TNT
            switch ($tipoProducto) {
                case 'TNT':
                case 'LAMINADORA': // ⭐ LAMINADORA usa el mismo procesamiento que TNT
                    error_log("✅ Procesando como TNT/LAMINADORA (misma tabla)");
                    $ordenGenerada = $this->procesarProductoTNT($idOrdenProduccion, $datosProducto, $productoBD);
                    // Corregir el tipo en el resultado si es LAMINADORA
                    if ($tipoProducto === 'LAMINADORA' && $ordenGenerada) {
                        $ordenGenerada['tipo'] = 'LAMINADORA';
                    }
                    break;

                case 'SPUNLACE':
                    error_log("✅ Procesando como SPUNLACE");
                    $ordenGenerada = $this->procesarProductoSpunlace($idOrdenProduccion, $datosProducto, $productoBD);
                    break;

                case 'TOALLITAS':
                    error_log("✅ Procesando como TOALLITAS");
                    $ordenGenerada = $this->procesarProductoToallitas($idOrdenProduccion, $datosProducto, $productoBD);
                    break;

                case 'PAÑOS':
                    error_log("✅ Procesando como PAÑOS - SIN conversión como TOALLITAS");
                    $ordenGenerada = $this->procesarProductoPanos($idOrdenProduccion, $datosProducto, $productoBD);
                    break;

                default:
                    error_log("⚠️ Tipo desconocido '{$tipoProducto}', procesando como TNT");
                    $ordenGenerada = $this->procesarProductoTNT($idOrdenProduccion, $datosProducto, $productoBD);
                    $tipoProducto = 'TNT';
            }

            $this->repository->commit();

            error_log("✅ ORDEN CREADA - ID: {$idOrdenProduccion}, Tipo final: {$tipoProducto}");

            return [
                'success' => true,
                'id_orden' => $idOrdenProduccion,
                'tipo' => $tipoProducto,
                'orden_generada' => $ordenGenerada
            ];
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * ⭐ ACTUALIZADO: Generar URL del PDF según el tipo (LAMINADORA tiene su propio PDF) ⭐
     */
    public function generarUrlPDF($tipoProducto, $idOrden, $urlBase)
    {
        error_log("GENERANDO URL PDF - Tipo: {$tipoProducto}, ID: {$idOrden}");

        switch ($tipoProducto) {
            case 'TOALLITAS':
                $url = $urlBase . "secciones/produccion/pdf/ordenToallitas.php?id_orden=" . $idOrden;
                break;

            case 'SPUNLACE':
                $url = $urlBase . "secciones/produccion/pdf/ordenSpunlace.php?id_orden=" . $idOrden;
                break;

            case 'LAMINADORA':
                // ⭐ LAMINADORA tiene su propio PDF aunque use tabla TNT ⭐
                $url = $urlBase . "secciones/produccion/pdf/ordenTNT.php?id_orden=" . $idOrden;
                break;

            case 'PAÑOS':
                // ⭐ URL del PDF para PAÑOS ⭐
                $url = $urlBase . "secciones/produccion/pdf/produccionpanos.php?id_orden=" . $idOrden;
                break;

            default: // TNT y otros
                $url = $urlBase . "secciones/produccion/pdf/ordenTNT.php?id_orden=" . $idOrden;
        }

        error_log("URL PDF generada: {$url}");
        return $url;
    }

    /**
     * ⭐ NUEVO: Validar especificaciones de paños ⭐
     */
    public function validarEspecificacionesPanos($descripcion)
    {
        $specs = $this->extraerEspecificacionesPanos($descripcion);
        $errores = [];

        if (!$specs['gramatura']) {
            $errores[] = "No se pudo extraer la gramatura (formato esperado: XXg/m²)";
        }

        if (!$specs['ancho'] || !$specs['largo']) {
            $errores[] = "No se pudieron extraer las dimensiones (formato esperado: XXxXX cm)";
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores,
            'especificaciones' => $specs
        ];
    }

    /**
     * ⭐ ACTUALIZADO: Obtener tipos de productos soportados ⭐
     */
    public function getTiposSoportados()
    {
        return ['TNT', 'SPUNLACE', 'LAMINADORA', 'TOALLITAS', 'PAÑOS'];
    }

    /**
     * ⭐ NUEVO: Verificar soporte para un tipo específico ⭐
     */
    public function esTipoSoportado($tipo)
    {
        return in_array(strtoupper($tipo), $this->getTiposSoportados());
    }

    /**
     * ⭐ ACTUALIZADO: Obtener información de debugging con cantidad directa como bobinas ⭐
     */
    public function obtenerInfoDebug()
    {
        return [
            'tipos_soportados' => $this->getTiposSoportados(),
            'fecha_actual' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'productos_panos_bd' => $this->repository->buscarProductosPanos(),
            'productos_laminadora_bd' => $this->repository->buscarProductosLaminadora(),
            'tipos_en_bd' => $this->repository->verificarTiposEnBD(),
            'nota_laminadora' => 'LAMINADORA usa la misma tabla que TNT (sist_ventas_op_tnt)',
            'nota_panos' => 'PAÑOS guarda cantidad original SIN conversión (como TOALLITAS)',
            'sistema_bobinas_corregido' => [
                'tipos_directos' => ['TNT', 'SPUNLACE', 'LAMINADORA'],
                'comportamiento' => 'Cantidad ingresada = Número de bobinas (SIN conversión)',
                'ejemplos' => [
                    'Usuario carga 10 → 10 bobinas × peso_líquido = X kg total',
                    'Usuario carga 2.5 → 2.5 bobinas × peso_líquido = X kg total',
                    'Sin importar la unidad seleccionada'
                ],
                'campos_bd' => [
                    'total_bobinas' => 'Cantidad exacta ingresada (redondeada si es decimal)',
                    'cantidad_total' => 'Peso total en kg (bobinas × peso_liquido_producto)'
                ],
                'peso_liquido_fuente' => 'Campo cantidad de sist_ventas_productos'
            ]
        ];
    }
}
