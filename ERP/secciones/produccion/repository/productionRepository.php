<?php

class ProductionRepositoryUniversal
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;

        // ✅ NUEVO: Configurar zona horaria de Paraguay en PostgreSQL
        $this->configurarZonaHoraria();
    }

    /**
     * ✅ NUEVO: Configurar zona horaria de Paraguay en la sesión de PostgreSQL
     * Esto asegura que todas las funciones de fecha/hora usen la zona horaria correcta
     */
    private function configurarZonaHoraria()
    {
        try {
            // Configurar zona horaria en PostgreSQL para esta sesión
            $sql = "SET timezone = 'America/Asuncion'";
            $this->conexion->exec($sql);

            error_log("✅ Zona horaria configurada: America/Asuncion");
        } catch (PDOException $e) {
            error_log("⚠️ Error configurando zona horaria: " . $e->getMessage());
        }
    }

    /**
     * ✅ NUEVO: Obtener fecha y hora actual de Paraguay
     * Usa PostgreSQL para calcular la hora correcta independiente del servidor
     */
    private function obtenerFechaHoraParaguay()
    {
        try {
            $sql = "SELECT NOW()::timestamp as fecha_hora_paraguay";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return $resultado['fecha_hora_paraguay'];
        } catch (PDOException $e) {
            error_log("⚠️ Error obteniendo fecha de Paraguay: " . $e->getMessage());
            // Fallback: usar PHP con zona horaria configurada
            date_default_timezone_set('America/Asuncion');
            return date('Y-m-d H:i:s');
        }
    }

    /**
     * Buscar una orden de producción completa con sus datos relacionados
     * @param int $numeroOrden
     * @return array
     */
    public function buscarOrdenCompleta($numeroOrden)
    {
        try {
            // Buscar la orden de producción 
            $sql = "SELECT id, 
                       fecha_orden,
                       estado,
                       observaciones,
                       finalizado,
                       COALESCE(cliente, 'SIN CLIENTE') as cliente,
                       TO_CHAR(fecha_orden, 'DD/MM/YYYY HH24:MI') as fecha_orden_formateada
                FROM public.sist_ventas_orden_produccion 
                WHERE id = :numero_orden";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
            $stmt->execute();
            $ordenEncontrada = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ordenEncontrada) {
                return [
                    'error' => "No se encontró la orden de producción #$numeroOrden",
                    'orden' => null,
                    'productos' => []
                ];
            }

            // Convertir finalizado a boolean
            $ordenEncontrada['finalizado'] = (bool)$ordenEncontrada['finalizado'];

            // Buscar productos de cualquier tipo
            $productos = $this->buscarProductosOrden($numeroOrden);

            if (empty($productos)) {
                return [
                    'error' => "La orden #$numeroOrden no tiene productos asociados",
                    'orden' => $ordenEncontrada,
                    'productos' => []
                ];
            }

            return [
                'error' => null,
                'orden' => $ordenEncontrada,
                'productos' => $productos
            ];
        } catch (PDOException $e) {
            return [
                'error' => "Error al buscar la orden: " . $e->getMessage(),
                'orden' => null,
                'productos' => []
            ];
        }
    }

    /**
     * Buscar productos asociados a una orden de producción (cualquier tipo)
     * @param int $numeroOrden
     * @return array
     */
    private function buscarProductosOrden($numeroOrden)
    {
        // 1. BUSCAR EN TABLA TNT 
        $sqlTNT = "SELECT 
                id,
                id_venta,
                COALESCE(nombre, 'Producto TNT') as descripcion, 
                CASE 
                    WHEN LOWER(COALESCE(nombre, '')) LIKE '%laminado%' THEN 'LAMINADORA'
                    ELSE 'TNT'
                END as tipo,
                cantidad_total as cantidad_solicitada,
                0 as cantidad_completada,
                'pendiente' as estado_produccion,
                gramatura,
                largura_metros,
                longitud_bobina,
                color,
                peso_bobina,
                cantidad_total,
                total_bobinas,
                pesominbobina,
                nombre
            FROM public.sist_ventas_op_tnt
            WHERE id_orden_produccion = :numero_orden";

        $stmt = $this->conexion->prepare($sqlTNT);
        $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
        $stmt->execute();
        $resultadosTNT = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($resultadosTNT)) {
            // Log para debug
            foreach ($resultadosTNT as $producto) {
                $nombreProducto = $producto['nombre'] ?? 'Sin nombre';
                $tipoDetectado = $producto['tipo'];
                error_log("🔍 Producto detectado: '$nombreProducto' -> Tipo: $tipoDetectado");
            }
            return $resultadosTNT;
        }

        // 2. BUSCAR EN TABLA SPUNLACE 
        $sqlSpunlace = "SELECT 
                    id,
                    id_venta,
                    COALESCE(nombre, 'Producto SPUNLACE') as descripcion, 
                    'SPUNLACE' as tipo,
                    cantidad_total as cantidad_solicitada,
                    0 as cantidad_completada,
                    'pendiente' as estado_produccion,
                    gramatura,
                    largura_metros,
                    longitud_bobina,
                    color,
                    peso_bobina,
                    cantidad_total,
                    total_bobinas,
                    pesominbobina,
                    acabado
                FROM public.sist_ventas_op_spunlace
                WHERE id_orden_produccion = :numero_orden";

        $stmt = $this->conexion->prepare($sqlSpunlace);
        $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
        $stmt->execute();
        $resultadosSpunlace = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($resultadosSpunlace)) {
            return $resultadosSpunlace;
        }

        // 3. BUSCAR EN TABLA TOALLITAS 
        $sqlToallitas = "SELECT 
                     id,
                     id_venta,
                     COALESCE(nombre, 'Toallitas') as descripcion, 
                     'TOALLITAS' as tipo,
                     cantidad_total as cantidad_solicitada,
                     0 as cantidad_completada,
                     'pendiente' as estado_produccion,
                     NULL as gramatura,  -- Toallitas no usan estos campos
                     NULL as largura_metros,
                     NULL as longitud_bobina,
                     NULL as color,
                     NULL as peso_bobina,
                     cantidad_total,
                     cantidad_total as total_bobinas,
                     NULL as pesominbobina
                 FROM public.sist_ventas_op_toallitas
                 WHERE id_orden_produccion = :numero_orden";

        $stmt = $this->conexion->prepare($sqlToallitas);
        $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
        $stmt->execute();
        $resultadosToallitas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($resultadosToallitas)) {
            return $resultadosToallitas;
        }

        // 4. BUSCAR EN TABLA PAÑOS 
        $sqlPanos = "SELECT 
                     id,
                     id_venta,
                     COALESCE(nombre, 'Paños') as descripcion, 
                     'PAÑOS' as tipo,
                     cantidad_total as cantidad_solicitada,
                     0 as cantidad_completada,
                     'pendiente' as estado_produccion,
                     gramatura,  -- Paños tienen gramatura pero no se usa en producción
                     largura as largura_metros,  -- Paños tienen largura pero no se usa en producción
                     NULL as longitud_bobina,
                     color,
                     peso as peso_bobina,
                     cantidad_total,
                     cant_panos as total_bobinas,  -- Mapear cant_panos a total_bobinas
                     NULL as pesominbobina,
                     picotado,
                     unidad
                 FROM public.sist_ventas_op_panos
                 WHERE id_orden_produccion = :numero_orden";

        $stmt = $this->conexion->prepare($sqlPanos);
        $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
        $stmt->execute();
        $resultadosPanos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $resultadosPanos; // Retorna array vacío si no encuentra nada
    }

    /**
     * Buscar órdenes por nombre de producto
     * @param string $nombreProducto
     * @return array
     */
    public function buscarOrdenesPorProducto($nombreProducto)
    {
        try {
            $sql = "SELECT DISTINCT 
                        op.id as numero_orden,
                        op.fecha_orden,
                        op.cliente,
                        op.estado,
                        CASE 
                            WHEN LOWER(COALESCE(tnt.nombre, '')) LIKE '%laminado%' THEN 'LAMINADORA'
                            ELSE 'TNT'
                        END as tipo_producto,
                        tnt.nombre as nombre_producto
                    FROM public.sist_ventas_orden_produccion op
                    JOIN public.sist_ventas_op_tnt tnt ON op.id = tnt.id_orden_produccion
                    WHERE LOWER(tnt.nombre) LIKE LOWER(:nombre_producto)
                    
                    UNION ALL
                    
                    SELECT DISTINCT 
                        op.id as numero_orden,
                        op.fecha_orden,
                        op.cliente,
                        op.estado,
                        'SPUNLACE' as tipo_producto,
                        spu.nombre as nombre_producto
                    FROM public.sist_ventas_orden_produccion op
                    JOIN public.sist_ventas_op_spunlace spu ON op.id = spu.id_orden_produccion
                    WHERE LOWER(spu.nombre) LIKE LOWER(:nombre_producto)
                    
                    UNION ALL
                    
                    SELECT DISTINCT 
                        op.id as numero_orden,
                        op.fecha_orden,
                        op.cliente,
                        op.estado,
                        'TOALLITAS' as tipo_producto,
                        toa.nombre as nombre_producto
                    FROM public.sist_ventas_orden_produccion op
                    JOIN public.sist_ventas_op_toallitas toa ON op.id = toa.id_orden_produccion
                    WHERE LOWER(toa.nombre) LIKE LOWER(:nombre_producto)
                    
                    UNION ALL
                    
                    SELECT DISTINCT 
                        op.id as numero_orden,
                        op.fecha_orden,
                        op.cliente,
                        op.estado,
                        'PAÑOS' as tipo_producto,
                        pan.nombre as nombre_producto
                    FROM public.sist_ventas_orden_produccion op
                    JOIN public.sist_ventas_op_panos pan ON op.id = pan.id_orden_produccion
                    WHERE LOWER(pan.nombre) LIKE LOWER(:nombre_producto)
                    
                    ORDER BY fecha_orden DESC";

            $stmt = $this->conexion->prepare($sql);
            $nombreBusqueda = '%' . $nombreProducto . '%';
            $stmt->bindParam(':nombre_producto', $nombreBusqueda, PDO::PARAM_STR);
            $stmt->execute();

            return [
                'error' => null,
                'ordenes' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (PDOException $e) {
            return [
                'error' => "Error en búsqueda por producto: " . $e->getMessage(),
                'ordenes' => []
            ];
        }
    }

    /**
     * Buscar órdenes por múltiples criterios 
     * Solo muestra órdenes con estado 'Orden Emitida' y finalizado = null
     * @param array $criterios - puede incluir: numero_orden, cliente, tipo_producto, nombre_producto
     * @return array
     */
    public function buscarOrdenesPorCriterios($criterios)
    {
        try {
            $condiciones = [];
            $parametros = [];

            // 🔒 CONDICIONES FIJAS - SIEMPRE SE APLICAN
            $sql = "SELECT DISTINCT
                 op.id as numero_orden,
                 op.fecha_orden,
                 op.cliente,
                 op.estado,
                 op.finalizado,
                 COUNT(sps.id) as items_producidos
            FROM public.sist_ventas_orden_produccion op
            LEFT JOIN public.sist_prod_stock sps ON op.id = sps.id_orden_produccion";

            // ✅ CONDICIONES BÁSICAS - SIEMPRE PRESENTES
            $condiciones[] = "op.estado = 'Orden Emitida'";
            $condiciones[] = "op.finalizado IS NULL";

            // 🎯 FILTRO POR NÚMERO DE ORDEN (si se proporciona)
            if (!empty($criterios['numero_orden'])) {
                $condiciones[] = "op.id = :numero_orden";
                $parametros[':numero_orden'] = $criterios['numero_orden'];
            }

            // 👤 FILTRO POR CLIENTE (si se proporciona)
            if (!empty($criterios['cliente'])) {
                $condiciones[] = "LOWER(op.cliente) LIKE LOWER(:cliente)";
                $parametros[':cliente'] = '%' . $criterios['cliente'] . '%';
            }

            // 📦 FILTRO POR TIPO DE PRODUCTO (NUEVA LÓGICA)
            if (!empty($criterios['tipo_producto'])) {
                $tipoFiltro = $criterios['tipo_producto'];

                switch (strtoupper($tipoFiltro)) {
                    case 'TNT':
                        $condiciones[] = "EXISTS (
                        SELECT 1 FROM public.sist_ventas_op_tnt tnt 
                        WHERE tnt.id_orden_produccion = op.id 
                        AND (LOWER(COALESCE(tnt.nombre, '')) NOT LIKE '%laminado%' OR tnt.nombre IS NULL)
                    )";
                        break;

                    case 'LAMINADORA':
                        $condiciones[] = "EXISTS (
                        SELECT 1 FROM public.sist_ventas_op_tnt tnt 
                        WHERE tnt.id_orden_produccion = op.id 
                        AND LOWER(COALESCE(tnt.nombre, '')) LIKE '%laminado%'
                    )";
                        break;

                    case 'SPUNLACE':
                        $condiciones[] = "EXISTS (
                        SELECT 1 FROM public.sist_ventas_op_spunlace spu 
                        WHERE spu.id_orden_produccion = op.id
                    )";
                        break;

                    case 'TOALLITAS':
                        $condiciones[] = "EXISTS (
                        SELECT 1 FROM public.sist_ventas_op_toallitas toa 
                        WHERE toa.id_orden_produccion = op.id
                    )";
                        break;

                    case 'PAÑOS':
                        $condiciones[] = "EXISTS (
                        SELECT 1 FROM public.sist_ventas_op_panos pan 
                        WHERE pan.id_orden_produccion = op.id
                    )";
                        break;
                }
            }

            // 🔧 CONSTRUIR WHERE CLAUSE
            $sql .= " WHERE " . implode(' AND ', $condiciones);

            // 📊 GROUP BY y ORDER BY
            $sql .= " GROUP BY op.id, op.fecha_orden, op.cliente, op.estado, op.finalizado 
                  ORDER BY op.fecha_orden DESC";

            // 🚀 EJECUTAR CONSULTA
            $stmt = $this->conexion->prepare($sql);
            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }
            $stmt->execute();

            $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("✅ Búsqueda exitosa - Criterios: " . json_encode($criterios) . " - Resultados: " . count($ordenes));

            return [
                'error' => null,
                'ordenes' => $ordenes
            ];
        } catch (PDOException $e) {
            error_log("💥 Error en búsqueda: " . $e->getMessage());
            return [
                'error' => "Error en búsqueda: " . $e->getMessage(),
                'ordenes' => []
            ];
        }
    }

    /**
     * Obtener detalles de productos de una orden específica por tipo
     * @param int $numeroOrden
     * @param string $tipo - 'TNT', 'SPUNLACE', 'TOALLITAS', 'PAÑOS', 'LAMINADORA'
     * @return array
     */
    public function obtenerProductosPorTipo($numeroOrden, $tipo)
    {
        try {
            switch (strtoupper($tipo)) {
                case 'TNT':
                    $sql = "SELECT * FROM public.sist_ventas_op_tnt 
                            WHERE id_orden_produccion = :numero_orden 
                            AND (LOWER(COALESCE(nombre, '')) NOT LIKE '%laminado%' OR nombre IS NULL)";
                    break;
                case 'LAMINADORA':
                    $sql = "SELECT * FROM public.sist_ventas_op_tnt 
                            WHERE id_orden_produccion = :numero_orden 
                            AND LOWER(COALESCE(nombre, '')) LIKE '%laminado%'";
                    break;
                case 'SPUNLACE':
                    $sql = "SELECT * FROM public.sist_ventas_op_spunlace WHERE id_orden_produccion = :numero_orden";
                    break;
                case 'TOALLITAS':
                    $sql = "SELECT * FROM public.sist_ventas_op_toallitas WHERE id_orden_produccion = :numero_orden";
                    break;
                case 'PAÑOS':
                    $sql = "SELECT * FROM public.sist_ventas_op_panos WHERE id_orden_produccion = :numero_orden";
                    break;
                default:
                    return [
                        'error' => "Tipo de producto no válido: $tipo",
                        'productos' => []
                    ];
            }

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'error' => null,
                'productos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (PDOException $e) {
            return [
                'error' => "Error al obtener productos: " . $e->getMessage(),
                'productos' => []
            ];
        }
    }

    /**
     * Registrar en stock - MODIFICADO para usar hora correcta de Paraguay
     * Para TOALLITAS/PAÑOS: peso_bruto, peso_liquido y tara
     * Para TNT/SPUNLACE/LAMINADORA: todos los campos
     * @param array $datosRegistro
     * @return array
     */
    public function registrarEnStock($datosRegistro)
    {
        try {
            $this->conexion->beginTransaction();

            error_log("🔄 Registrando en stock - Tipo: " . ($datosRegistro['tipo_producto'] ?? 'N/A'));

            // Obtener el siguiente número de item para esta orden
            $siguienteItem = $this->obtenerSiguienteNumeroItem($datosRegistro['numero_orden']);

            // ✅ CAMBIO: Usar hora correcta de Paraguay
            $fechaHoraParaguay = $this->obtenerFechaHoraParaguay();

            $tipoProducto = $datosRegistro['tipo_producto'] ?? 'GENERICO';

            if ($tipoProducto === 'TOALLITAS' || $tipoProducto === 'PAÑOS') {
                // 🏷️ REGISTRO PARA TOALLITAS Y PAÑOS 
                $sql = "INSERT INTO public.sist_prod_stock 
                    (peso_bruto, peso_liquido, tara, fecha_hora_producida, estado, numero_item, 
                     nombre_producto, tipo_producto, id_orden_produccion, bobinas_pacote, cliente, id_venta, usuario)
                    VALUES 
                    (:peso_bruto, :peso_liquido, :tara, :fecha_hora_producida, 'en stock', :numero_item, 
                     :nombre_producto, :tipo_producto, :id_orden_produccion, :bobinas_pacote, NULL, NULL, :usuario)";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':peso_bruto', $datosRegistro['peso_bruto'], PDO::PARAM_STR);
                $stmt->bindParam(':peso_liquido', $datosRegistro['peso_liquido'], PDO::PARAM_STR);
                $stmt->bindParam(':tara', $datosRegistro['tara'], PDO::PARAM_STR);
                $stmt->bindParam(':fecha_hora_producida', $fechaHoraParaguay, PDO::PARAM_STR); // ✅ CAMBIO AQUÍ
                $stmt->bindParam(':numero_item', $siguienteItem, PDO::PARAM_INT);
                $stmt->bindParam(':nombre_producto', $datosRegistro['nombre_producto'], PDO::PARAM_STR);
                $stmt->bindParam(':tipo_producto', $tipoProducto, PDO::PARAM_STR);
                $stmt->bindParam(':id_orden_produccion', $datosRegistro['numero_orden'], PDO::PARAM_INT);
                $stmt->bindParam(':bobinas_pacote', $datosRegistro['bobinas_pacote'], PDO::PARAM_INT);
                $stmt->bindParam(':usuario', $datosRegistro['usuario'], PDO::PARAM_INT);

                error_log("🏷️ Insertando $tipoProducto con tara - Item: $siguienteItem - Hora Paraguay: $fechaHoraParaguay");
            } else {
                // 📦 REGISTRO COMPLETO PARA TNT/SPUNLACE/LAMINADORA/OTROS 
                $sql = "INSERT INTO public.sist_prod_stock 
                    (peso_bruto, peso_liquido, tara, fecha_hora_producida, estado, numero_item, 
                     nombre_producto, tipo_producto, id_orden_produccion, metragem, largura, 
                     gramatura, bobinas_pacote, cliente, id_venta, usuario)
                    VALUES 
                    (:peso_bruto, :peso_liquido, :tara, :fecha_hora_producida, 'en stock', :numero_item, 
                     :nombre_producto, :tipo_producto, :id_orden_produccion, :metragem, :largura, 
                     :gramatura, :bobinas_pacote, NULL, NULL, :usuario)";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':peso_bruto', $datosRegistro['peso_bruto'], PDO::PARAM_STR);
                $stmt->bindParam(':peso_liquido', $datosRegistro['peso_liquido'], PDO::PARAM_STR);
                $stmt->bindParam(':tara', $datosRegistro['tara'], PDO::PARAM_STR);
                $stmt->bindParam(':fecha_hora_producida', $fechaHoraParaguay, PDO::PARAM_STR); // ✅ CAMBIO AQUÍ
                $stmt->bindParam(':numero_item', $siguienteItem, PDO::PARAM_INT);
                $stmt->bindParam(':nombre_producto', $datosRegistro['nombre_producto'], PDO::PARAM_STR);
                $stmt->bindParam(':tipo_producto', $tipoProducto, PDO::PARAM_STR);
                $stmt->bindParam(':id_orden_produccion', $datosRegistro['numero_orden'], PDO::PARAM_INT);
                $stmt->bindParam(':metragem', $datosRegistro['metragem'], PDO::PARAM_INT);
                $stmt->bindParam(':largura', $datosRegistro['largura'], PDO::PARAM_STR);
                $stmt->bindParam(':gramatura', $datosRegistro['gramatura'], PDO::PARAM_INT);
                $stmt->bindParam(':bobinas_pacote', $datosRegistro['bobinas_pacote'], PDO::PARAM_INT);
                $stmt->bindParam(':usuario', $datosRegistro['usuario'], PDO::PARAM_INT);

                error_log("📦 Insertando $tipoProducto - Item: $siguienteItem - Hora Paraguay: $fechaHoraParaguay");
            }

            $stmt->execute();
            $this->conexion->commit();

            error_log("✅ Registro exitoso en stock - Item: $siguienteItem - Fecha/Hora Paraguay: $fechaHoraParaguay");

            return [
                'success' => true,
                'numero_item' => $siguienteItem,
                'fecha_hora' => $fechaHoraParaguay, // ✅ CAMBIO AQUÍ
                'tipo_producto' => $tipoProducto,
                'error' => null
            ];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error en registrarEnStock: " . $e->getMessage());
            return [
                'success' => false,
                'numero_item' => null,
                'fecha_hora' => null,
                'tipo_producto' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener el siguiente número de item para una orden (cualquier tipo)
     * @param int $numeroOrden
     * @return int
     */
    private function obtenerSiguienteNumeroItem($numeroOrden)
    {
        $sql = "SELECT COALESCE(MAX(numero_item), 0) + 1 as siguiente 
                FROM public.sist_prod_stock 
                WHERE id_orden_produccion = :numero_orden";

        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
        $stmt->execute();

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado['siguiente'];
    }

    /**
     * Obtener estadísticas de registros hoy para una orden - MODIFICADO para usar zona horaria correcta
     * @param int $numeroOrden
     * @return array
     */
    public function obtenerRegistrosHoy($numeroOrden)
    {
        // ✅ CAMBIO: Usar CURRENT_DATE con zona horaria de Paraguay
        $sql = "SELECT 
                COUNT(*) as registros_hoy, 
                SUM(peso_bruto) as peso_bruto_hoy,
                SUM(peso_liquido) as peso_liquido_hoy,
                AVG(peso_liquido) as peso_liquido_promedio_hoy,
                SUM(COALESCE(tara, 0)) as tara_total_hoy
                FROM public.sist_prod_stock 
                WHERE id_orden_produccion = :numero_orden 
                AND fecha_hora_producida::date = (NOW() AT TIME ZONE 'America/Asuncion')::date";

        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Contar total de registros para una orden - cualquier tipo
     * @param int $numeroOrden
     * @return int
     */
    public function contarTotalRegistros($numeroOrden)
    {
        $sql = "SELECT COUNT(*) as total
                FROM public.sist_prod_stock 
                WHERE id_orden_produccion = :numero_orden";

        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
        $stmt->execute();

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado['total'];
    }

    /**
     * Obtener últimos registros con paginación (cualquier tipo)
     * @param int $numeroOrden
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function obtenerUltimosRegistros($numeroOrden, $limit, $offset)
    {
        // Primero obtener datos del producto de la orden para cálculos
        $sqlProducto = "SELECT gramatura, largura_metros FROM public.sist_ventas_op_tnt 
                   WHERE id_orden_produccion = :numero_orden 
                   UNION ALL
                   SELECT gramatura, largura_metros FROM public.sist_ventas_op_spunlace 
                   WHERE id_orden_produccion = :numero_orden
                   LIMIT 1";

        $stmtProducto = $this->conexion->prepare($sqlProducto);
        $stmtProducto->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
        $stmtProducto->execute();
        $datosProducto = $stmtProducto->fetch(PDO::FETCH_ASSOC);

        $sql = "SELECT id, numero_item, peso_bruto, peso_liquido,
                   metragem, bobinas_pacote, gramatura, largura,
                   COALESCE(tara, 0) as tara, tipo_producto,
                   TO_CHAR(fecha_hora_producida, 'DD/MM') as fecha,
                   TO_CHAR(fecha_hora_producida, 'HH24:MI') as hora
            FROM public.sist_prod_stock 
            WHERE id_orden_produccion = :numero_orden 
            ORDER BY fecha_hora_producida DESC, id DESC 
            LIMIT :limit OFFSET :offset";

        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agregar clasificación a cada registro
        foreach ($registros as &$registro) {
            // Usar datos del registro o del producto según disponibilidad
            $gramatura = $registro['gramatura'] ?? $datosProducto['gramatura'] ?? 0;
            $largura = $registro['largura'] ?? $datosProducto['largura_metros'] ?? 0;
            $metragem = $registro['metragem'] ?? 0;
            $bobinas_pacote = $registro['bobinas_pacote'] ?? 1;

            // Solo clasificar para TNT/SPUNLACE/LAMINADORA
            if ($registro['tipo_producto'] !== 'TOALLITAS' && $registro['tipo_producto'] !== 'PAÑOS') {
                $peso_teorico = $this->calcularPesoTeorico($gramatura, $metragem, $largura, $bobinas_pacote);
                $clasificacion = $this->clasificarPeso(floatval($registro['peso_liquido']), $peso_teorico);

                $registro['peso_teorico'] = $peso_teorico;
                $registro['clasificacion'] = $clasificacion;
            } else {
                $registro['peso_teorico'] = 0;
                $registro['clasificacion'] = ['categoria' => 'N/A', 'clase' => 'no-aplica', 'icono' => 'info-circle', 'diferencia' => 0];
            }
        }

        return $registros;
    }
    /**
     * Obtener estadísticas generales de una orden
     * @param int $numeroOrden
     * @return array
     */
    public function obtenerEstadisticasOrden($numeroOrden)
    {
        $sql = "SELECT 
                COUNT(*) as total_registros,
                SUM(peso_bruto) as peso_total_bruto,
                SUM(peso_liquido) as peso_total_liquido,
                AVG(peso_liquido) as peso_promedio,
                MIN(peso_liquido) as peso_minimo,
                MAX(peso_liquido) as peso_maximo
                FROM public.sist_prod_stock 
                WHERE id_orden_produccion = :numero_orden";

        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Método auxiliar para obtener datos completos de paginación
     * @param int $numeroOrden
     * @param int $itemsPorPagina
     * @param int $paginaActual
     * @return array
     */
    public function obtenerDatosPaginacion($numeroOrden, $itemsPorPagina, $paginaActual)
    {
        $totalRegistros = $this->contarTotalRegistros($numeroOrden);
        $totalPaginas = ceil($totalRegistros / $itemsPorPagina);
        $offset = ($paginaActual - 1) * $itemsPorPagina;

        $ultimosRegistros = $this->obtenerUltimosRegistros($numeroOrden, $itemsPorPagina, $offset);

        return [
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'pagina_actual' => $paginaActual,
            'registros' => $ultimosRegistros,
            'items_por_pagina' => $itemsPorPagina
        ];
    }

    /**
     * Verificar que un registro existe y pertenece a la orden especificada
     * ✅ CORREGIDO: Agregar bobinas_pacote y otros campos necesarios para reversión
     * @param int $idRegistro
     * @param int $numeroOrden
     * @return array
     */
    public function verificarRegistroExistente($idRegistro, $numeroOrden)
    {
        try {
            $sql = "SELECT id, numero_item, peso_bruto, peso_liquido, tara, 
                       tipo_producto, nombre_producto, cliente,
                       COALESCE(bobinas_pacote, 1) as bobinas_pacote,
                       COALESCE(metragem, 0) as metragem,
                       COALESCE(largura, 0) as largura,
                       COALESCE(gramatura, 0) as gramatura,
                       TO_CHAR(fecha_hora_producida, 'DD/MM/YYYY HH24:MI:SS') as fecha_hora_formateada
                FROM public.sist_prod_stock 
                WHERE id = :id_registro 
                AND id_orden_produccion = :numero_orden";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_registro', $idRegistro, PDO::PARAM_INT);
            $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
            $stmt->execute();

            $registro = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($registro) {
                return [
                    'exists' => true,
                    'registro' => $registro,
                    'error' => null
                ];
            } else {
                return [
                    'exists' => false,
                    'registro' => null,
                    'error' => "Registro no encontrado"
                ];
            }
        } catch (PDOException $e) {
            return [
                'exists' => false,
                'registro' => null,
                'error' => "Error al verificar registro: " . $e->getMessage()
            ];
        }
    }
    /**
     * Eliminar un registro específico de la tabla de stock y manejar reservas relacionadas
     * @param int $idRegistro
     * @param int $numeroOrden
     * @return array
     */
    public function eliminarRegistro($idRegistro, $numeroOrden)
    {
        try {
            $this->conexion->beginTransaction();

            // 1. Verificar una vez más antes de eliminar
            $verificacion = $this->verificarRegistroExistente($idRegistro, $numeroOrden);
            if (!$verificacion['exists']) {
                throw new Exception("El registro ya no existe o no pertenece a esta orden");
            }

            $registroAEliminar = $verificacion['registro'];
            $bobinas_pacote = $this->obtenerBobinasPacoteDelRegistro($idRegistro);

            error_log("🗑️ Iniciando eliminación - Registro ID: $idRegistro, Bobinas: $bobinas_pacote");

            // 2. Buscar reserva relacionada con este registro de producción
            $reservaRelacionada = $this->buscarReservaRelacionada($idRegistro, $numeroOrden);

            if ($reservaRelacionada['encontrada']) {
                $idReserva = $reservaRelacionada['id_reserva'];
                $idStockAgregado = $reservaRelacionada['id_stock_agregado'];

                error_log("🔍 Reserva encontrada - ID: $idReserva, Stock Agregado: $idStockAgregado");

                // 3. Actualizar cantidades en stock_agregado (disminuir)
                $this->disminuirCantidadesStockAgregado($idStockAgregado, $bobinas_pacote);

                // 4. Registrar movimiento de eliminación
                $this->registrarMovimientoEliminacion($idStockAgregado, $idReserva, $bobinas_pacote, $registroAEliminar);

                // 5. Cambiar estado de reserva a "eliminado"
                $this->marcarReservaComoEliminada($idReserva, $registroAEliminar);
            } else {
                error_log("⚠️ No se encontró reserva relacionada para el registro $idRegistro");
            }

            // 6. Proceder con la eliminación del registro original
            $sql = "DELETE FROM public.sist_prod_stock 
                WHERE id = :id_registro 
                AND id_orden_produccion = :numero_orden";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_registro', $idRegistro, PDO::PARAM_INT);
            $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();

                error_log("✅ Eliminación completa exitosa - Registro: $idRegistro, Reserva manejada: " .
                    ($reservaRelacionada['encontrada'] ? "Sí (ID: {$reservaRelacionada['id_reserva']})" : "No"));

                return [
                    'success' => true,
                    'registros_afectados' => $stmt->rowCount(),
                    'registro_eliminado' => $registroAEliminar,
                    'reserva_eliminada' => $reservaRelacionada['encontrada'],
                    'id_reserva_afectada' => $reservaRelacionada['encontrada'] ? $reservaRelacionada['id_reserva'] : null,
                    'bobinas_liberadas' => $bobinas_pacote,
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo eliminar el registro de producción");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error en eliminación completa - ID: $idRegistro, Error: " . $e->getMessage());

            return [
                'success' => false,
                'registros_afectados' => 0,
                'registro_eliminado' => null,
                'reserva_eliminada' => false,
                'id_reserva_afectada' => null,
                'bobinas_liberadas' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener cantidad de bobinas_pacote de un registro específico
     * @param int $idRegistro
     * @return int
     */
    private function obtenerBobinasPacoteDelRegistro($idRegistro)
    {
        try {
            $sql = "SELECT COALESCE(bobinas_pacote, 1) as bobinas_pacote 
                FROM public.sist_prod_stock 
                WHERE id = :id_registro";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_registro', $idRegistro, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ? intval($resultado['bobinas_pacote']) : 1;
        } catch (Exception $e) {
            error_log("⚠️ Error obteniendo bobinas_pacote: " . $e->getMessage());
            return 1; // Valor por defecto
        }
    }

    /**
     * Buscar reserva relacionada con un registro de producción 
     * @param int $idRegistro
     * @param int $numeroOrden
     * @return array
     */
    private function buscarReservaRelacionada($idRegistro, $numeroOrden)
    {
        try {
            // 1. OBTENER DATOS DEL REGISTRO DE PRODUCCIÓN
            $sqlRegistro = "SELECT 
            sps.nombre_producto,
            sps.tipo_producto,
            sps.bobinas_pacote,
            sps.cliente
        FROM public.sist_prod_stock sps 
        WHERE sps.id = :id_registro 
        AND sps.id_orden_produccion = :numero_orden";

            $stmtRegistro = $this->conexion->prepare($sqlRegistro);
            $stmtRegistro->bindParam(':id_registro', $idRegistro, PDO::PARAM_INT);
            $stmtRegistro->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
            $stmtRegistro->execute();

            $datosRegistro = $stmtRegistro->fetch(PDO::FETCH_ASSOC);

            if (!$datosRegistro) {
                error_log("⚠️ No se encontraron datos del registro $idRegistro");
                return [
                    'encontrada' => false,
                    'id_reserva' => null,
                    'id_stock_agregado' => null,
                    'estado' => null,
                    'cantidad_reservada' => 0,
                    'motivo' => 'Registro no encontrado'
                ];
            }

            // 2. OBTENER EL ID_VENTA REAL DE LA ORDEN DE PRODUCCIÓN
            $idVentaReal = $this->obtenerIdVentaDeOrden($numeroOrden, $datosRegistro['nombre_producto'], $datosRegistro['tipo_producto']);

            error_log("🔍 Buscando reserva para: {$datosRegistro['nombre_producto']} | Bobinas: {$datosRegistro['bobinas_pacote']} | ID Venta Real: " . ($idVentaReal ?? 'NULL'));

            // 3. BUSCAR RESERVA ESPECÍFICA usando nombre_producto, cantidad_reservada e id_venta real
            $condicionesWhere = [
                "r.nombre_producto = :nombre_producto",
                "r.cantidad_reservada = :cantidad_reservada",
                "r.estado = 'activa'"
            ];

            $parametros = [
                ':nombre_producto' => $datosRegistro['nombre_producto'],
                ':cantidad_reservada' => $datosRegistro['bobinas_pacote']
            ];

            // 4. USAR EL ID_VENTA REAL SI ESTÁ DISPONIBLE
            if (!empty($idVentaReal)) {
                $condicionesWhere[] = "r.id_venta = :id_venta";
                $parametros[':id_venta'] = $idVentaReal;
                error_log("🎯 Búsqueda con ID_VENTA REAL: {$idVentaReal}");
            } else {
                // Si no hay id_venta, usar cliente como criterio adicional
                if (!empty($datosRegistro['cliente'])) {
                    $condicionesWhere[] = "r.cliente = :cliente";
                    $parametros[':cliente'] = $datosRegistro['cliente'];
                    error_log("👤 Búsqueda con CLIENTE: {$datosRegistro['cliente']}");
                }
            }

            $sqlReserva = "SELECT 
            r.id as id_reserva,
            r.id_stock_agregado,
            r.estado,
            r.cantidad_reservada,
            r.id_venta,
            r.cliente,
            r.nombre_producto,
            r.fecha_reserva,
            sa.nombre_producto as stock_nombre_producto
        FROM public.reservas_stock r
        INNER JOIN public.stock_agregado sa ON r.id_stock_agregado = sa.id
        WHERE " . implode(' AND ', $condicionesWhere) . "
        ORDER BY r.fecha_reserva DESC, r.id DESC
        LIMIT 1";

            $stmtReserva = $this->conexion->prepare($sqlReserva);

            foreach ($parametros as $param => $valor) {
                $stmtReserva->bindValue($param, $valor);
            }

            $stmtReserva->execute();
            $reserva = $stmtReserva->fetch(PDO::FETCH_ASSOC);

            if ($reserva) {
                error_log("✅ Reserva encontrada - ID: {$reserva['id_reserva']} | Stock ID: {$reserva['id_stock_agregado']} | Cantidad: {$reserva['cantidad_reservada']}");

                return [
                    'encontrada' => true,
                    'id_reserva' => $reserva['id_reserva'],
                    'id_stock_agregado' => $reserva['id_stock_agregado'],
                    'estado' => $reserva['estado'],
                    'cantidad_reservada' => $reserva['cantidad_reservada'],
                    'datos_reserva' => $reserva,
                    'datos_registro' => $datosRegistro,
                    'id_venta_real' => $idVentaReal,
                    'motivo' => 'Encontrada por nombre_producto + cantidad + ' . (isset($parametros[':id_venta']) ? 'id_venta_real' : 'cliente')
                ];
            } else {
                error_log("❌ No se encontró reserva exacta para: {$datosRegistro['nombre_producto']} | Cantidad: {$datosRegistro['bobinas_pacote']}");

                // 5. BÚSQUEDA ALTERNATIVA: Solo por nombre_producto (para debugging)
                $sqlAlternativa = "SELECT COUNT(*) as total_reservas
                FROM public.reservas_stock r
                WHERE r.nombre_producto = :nombre_producto 
                AND r.estado = 'activa'";

                $stmtAlt = $this->conexion->prepare($sqlAlternativa);
                $stmtAlt->bindParam(':nombre_producto', $datosRegistro['nombre_producto'], PDO::PARAM_STR);
                $stmtAlt->execute();
                $totalReservas = $stmtAlt->fetch(PDO::FETCH_ASSOC);

                error_log("🔎 Total reservas activas para '{$datosRegistro['nombre_producto']}': {$totalReservas['total_reservas']}");

                return [
                    'encontrada' => false,
                    'id_reserva' => null,
                    'id_stock_agregado' => null,
                    'estado' => null,
                    'cantidad_reservada' => 0,
                    'datos_registro' => $datosRegistro,
                    'id_venta_real' => $idVentaReal,
                    'motivo' => "No encontrada - Total reservas del producto: {$totalReservas['total_reservas']}"
                ];
            }
        } catch (Exception $e) {
            error_log("💥 Error buscando reserva relacionada: " . $e->getMessage());
            return [
                'encontrada' => false,
                'id_reserva' => null,
                'id_stock_agregado' => null,
                'estado' => null,
                'cantidad_reservada' => 0,
                'motivo' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener el ID_VENTA real de la orden de producción
     * Busca en las tablas de productos según el tipo y nombre
     * @param int $numeroOrden
     * @param string $nombreProducto
     * @param string $tipoProducto
     * @return int|null
     */
    private function obtenerIdVentaDeOrden($numeroOrden, $nombreProducto, $tipoProducto)
    {
        try {
            $sql = "";

            switch (strtoupper($tipoProducto)) {
                case 'TNT':
                case 'LAMINADORA':
                    $sql = "SELECT id_venta FROM public.sist_ventas_op_tnt 
                        WHERE id_orden_produccion = :numero_orden 
                        AND nombre = :nombre_producto 
                        LIMIT 1";
                    break;

                case 'SPUNLACE':
                    $sql = "SELECT id_venta FROM public.sist_ventas_op_spunlace 
                        WHERE id_orden_produccion = :numero_orden 
                        AND nombre = :nombre_producto 
                        LIMIT 1";
                    break;

                case 'TOALLITAS':
                    $sql = "SELECT id_venta FROM public.sist_ventas_op_toallitas 
                        WHERE id_orden_produccion = :numero_orden 
                        AND nombre = :nombre_producto 
                        LIMIT 1";
                    break;

                case 'PAÑOS':
                    $sql = "SELECT id_venta FROM public.sist_ventas_op_panos 
                        WHERE id_orden_produccion = :numero_orden 
                        AND nombre = :nombre_producto 
                        LIMIT 1";
                    break;

                default:
                    error_log("⚠️ Tipo de producto desconocido: $tipoProducto");
                    return null;
            }

            if (empty($sql)) {
                return null;
            }

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
            $stmt->bindParam(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado && !empty($resultado['id_venta'])) {
                error_log("✅ ID_VENTA encontrado para $tipoProducto '$nombreProducto': {$resultado['id_venta']}");
                return intval($resultado['id_venta']);
            } else {
                error_log("⚠️ No se encontró ID_VENTA para $tipoProducto '$nombreProducto' en orden $numeroOrden");
                return null;
            }
        } catch (Exception $e) {
            error_log("💥 Error obteniendo ID_VENTA: " . $e->getMessage());
            return null;
        }
    }
    /**
     * Disminuir cantidades en stock_agregado
     * @param int $idStockAgregado
     * @param int $bobinas_pacote
     * @return bool
     */
    private function disminuirCantidadesStockAgregado($idStockAgregado, $bobinas_pacote)
    {
        try {
            $sql = "UPDATE public.stock_agregado 
                SET cantidad_total = GREATEST(cantidad_total - :bobinas_pacote, 0),
                    cantidad_reservada = GREATEST(cantidad_reservada - :bobinas_pacote, 0)
                WHERE id = :id_stock_agregado";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':bobinas_pacote', $bobinas_pacote, PDO::PARAM_INT);
            $stmt->bindParam(':id_stock_agregado', $idStockAgregado, PDO::PARAM_INT);
            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                error_log("📊 Cantidades actualizadas en stock_agregado ID: $idStockAgregado - Disminuidas: $bobinas_pacote bobinas");
                return true;
            } else {
                error_log("⚠️ No se actualizaron cantidades en stock_agregado ID: $idStockAgregado");
                return false;
            }
        } catch (Exception $e) {
            error_log("💥 Error actualizando stock_agregado: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Registrar movimiento de eliminación en movimientos_stock
     * @param int $idStockAgregado
     * @param int $idReserva
     * @param int $bobinas_pacote
     * @param array $registroEliminado
     * @return bool
     */
    private function registrarMovimientoEliminacion($idStockAgregado, $idReserva, $bobinas_pacote, $registroEliminado)
    {
        try {
            $usuario = $_SESSION['nombre'] ?? 'SISTEMA_PRODUCCION';
            $observaciones = "Eliminación de registro de producción - Item #{$registroEliminado['numero_item']} - Orden #{$registroEliminado['id_orden_produccion']} - Producto: {$registroEliminado['nombre_producto']}";

            $sql = "INSERT INTO public.movimientos_stock 
                (id_stock_agregado, tipo_movimiento, cantidad, id_reserva, usuario, observaciones, id_producto_fisico)
                VALUES 
                (:id_stock_agregado, 'ELIMINACION_REGISTRO', :cantidad, :id_reserva, :usuario, :observaciones, :id_producto_fisico)";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_stock_agregado', $idStockAgregado, PDO::PARAM_INT);
            $stmt->bindParam(':cantidad', $bobinas_pacote, PDO::PARAM_INT);
            $stmt->bindParam(':id_reserva', $idReserva, PDO::PARAM_INT);
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $observaciones, PDO::PARAM_STR);
            $stmt->bindParam(':id_producto_fisico', $registroEliminado['id'], PDO::PARAM_INT); // ID del registro eliminado

            $resultado = $stmt->execute();

            if ($resultado) {
                error_log("📝 Movimiento registrado - Tipo: ELIMINACION_REGISTRO, Cantidad: $bobinas_pacote, Reserva: $idReserva");
                return true;
            } else {
                error_log("⚠️ No se pudo registrar el movimiento de eliminación");
                return false;
            }
        } catch (Exception $e) {
            error_log("💥 Error registrando movimiento: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Marcar reserva como eliminada
     * @param int $idReserva
     * @param array $registroEliminado
     * @return bool
     */
    private function marcarReservaComoEliminada($idReserva, $registroEliminado)
    {
        try {
            $usuario = $_SESSION['nombre'] ?? 'SISTEMA_PRODUCCION';
            $motivoCancelacion = "Eliminación del registro de producción - Item #{$registroEliminado['numero_item']} de la Orden #{$registroEliminado['id_orden_produccion']}";

            $sql = "UPDATE public.reservas_stock 
                SET estado = 'eliminado',
                    fecha_cancelacion = CURRENT_TIMESTAMP,
                    motivo_cancelacion = :motivo_cancelacion,
                    usuario_cancelacion = :usuario_cancelacion
                WHERE id = :id_reserva";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':motivo_cancelacion', $motivoCancelacion, PDO::PARAM_STR);
            $stmt->bindParam(':usuario_cancelacion', $usuario, PDO::PARAM_STR);
            $stmt->bindParam(':id_reserva', $idReserva, PDO::PARAM_INT);

            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                error_log("🔒 Reserva marcada como eliminada - ID: $idReserva");
                return true;
            } else {
                error_log("⚠️ No se pudo marcar la reserva como eliminada - ID: $idReserva");
                return false;
            }
        } catch (Exception $e) {
            error_log("💥 Error marcando reserva como eliminada: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener estadísticas después de eliminación
     * @param int $numeroOrden
     * @return array
     */
    public function obtenerEstadisticasPostEliminacion($numeroOrden)
    {
        try {
            $sql = "SELECT 
                    COUNT(*) as total_registros_restantes,
                    SUM(peso_bruto) as peso_total_bruto,
                    SUM(peso_liquido) as peso_total_liquido,
                    MAX(numero_item) as ultimo_numero_item
                    FROM public.sist_prod_stock 
                    WHERE id_orden_produccion = :numero_orden";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'success' => true,
                'estadisticas' => $stmt->fetch(PDO::FETCH_ASSOC),
                'error' => null
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'estadisticas' => null,
                'error' => "Error al obtener estadísticas: " . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener estadísticas de producción vs solicitado
     * @param int $numeroOrden
     * @param array $producto - datos del producto de la orden
     * @return array
     */
    public function obtenerEstadisticasProduccion($numeroOrden, $producto)
    {
        try {
            $tipoProducto = $producto['tipo'];

            if ($tipoProducto === 'TOALLITAS' || $tipoProducto === 'PAÑOS') {
                // Para TOALLITAS y PAÑOS: cada registro = 1 unidad (caja/paño)
                $sql = "SELECT 
                        COUNT(*) as producido,
                        :cantidad_solicitada as solicitado
                    FROM public.sist_prod_stock 
                    WHERE id_orden_produccion = :numero_orden 
                    AND tipo_producto = :tipo_producto";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
                $stmt->bindParam(':cantidad_solicitada', $producto['cantidad_total'], PDO::PARAM_INT);
                $stmt->bindParam(':tipo_producto', $tipoProducto, PDO::PARAM_STR);
            } else {
                // Para TNT/SPUNLACE/LAMINADORA: sumar bobinas_pacote
                $sql = "SELECT 
                        COALESCE(SUM(bobinas_pacote), 0) as producido,
                        :cantidad_solicitada as solicitado
                    FROM public.sist_prod_stock 
                    WHERE id_orden_produccion = :numero_orden 
                    AND tipo_producto = :tipo_producto";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
                $stmt->bindParam(':cantidad_solicitada', $producto['total_bobinas'], PDO::PARAM_INT);
                $stmt->bindParam(':tipo_producto', $tipoProducto, PDO::PARAM_STR);
            }

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            $producido = intval($resultado['producido']);
            $solicitado = intval($resultado['solicitado']);
            $porcentaje = $solicitado > 0 ? ($producido / $solicitado) * 100 : 0;

            // Determinar unidad según tipo
            $unidad = '';
            switch ($tipoProducto) {
                case 'TOALLITAS':
                    $unidad = 'cajas';
                    break;
                case 'PAÑOS':
                    $unidad = 'paños';
                    break;
                default:
                    $unidad = 'bobinas';
                    break;
            }

            return [
                'success' => true,
                'producido' => $producido,
                'solicitado' => $solicitado,
                'porcentaje' => $porcentaje,
                'completado' => $producido >= $solicitado,
                'unidad' => $unidad,
                'error' => null
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'producido' => 0,
                'solicitado' => 0,
                'porcentaje' => 0,
                'completado' => false,
                'unidad' => '',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Finalizar una orden de producción
     * Actualiza el campo 'finalizado' a true en sist_ventas_orden_produccion
     * @param int $numeroOrden
     * @return array
     */
    public function finalizarOrdenProduccion($numeroOrden)
    {
        try {
            $this->conexion->beginTransaction();

            // Verificar que la orden existe
            $sqlVerificar = "SELECT id, cliente, estado, finalizado 
                        FROM public.sist_ventas_orden_produccion 
                        WHERE id = :numero_orden";

            $stmtVerificar = $this->conexion->prepare($sqlVerificar);
            $stmtVerificar->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
            $stmtVerificar->execute();

            $orden = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            if (!$orden) {
                throw new Exception("La orden de producción #$numeroOrden no existe");
            }

            // Verificar si ya está finalizada
            if ($orden['finalizado']) {
                throw new Exception("La orden de producción #$numeroOrden ya está finalizada");
            }

            // Actualizar el campo finalizado
            $sqlActualizar = "UPDATE public.sist_ventas_orden_produccion 
                         SET finalizado = true,
                             estado = CASE 
                                 WHEN estado = 'Pendiente' THEN 'Completado'
                                 ELSE estado
                             END
                         WHERE id = :numero_orden";

            $stmtActualizar = $this->conexion->prepare($sqlActualizar);
            $stmtActualizar->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
            $resultado = $stmtActualizar->execute();

            if ($resultado && $stmtActualizar->rowCount() > 0) {
                $this->conexion->commit();

                error_log("🏁 Orden finalizada exitosamente - ID: $numeroOrden");

                return [
                    'success' => true,
                    'orden_id' => $numeroOrden,
                    'cliente' => $orden['cliente'],
                    'estado_anterior' => $orden['estado'],
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo actualizar el estado de la orden");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("💥 Error al finalizar orden - ID: $numeroOrden, Error: " . $e->getMessage());

            return [
                'success' => false,
                'orden_id' => null,
                'cliente' => null,
                'estado_anterior' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar si una orden está finalizada
     * @param int $numeroOrden
     * @return array
     */
    public function verificarOrdenFinalizada($numeroOrden)
    {
        try {
            $sql = "SELECT finalizado, estado, cliente 
                FROM public.sist_ventas_orden_produccion 
                WHERE id = :numero_orden";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado) {
                return [
                    'exists' => true,
                    'finalizado' => (bool)$resultado['finalizado'],
                    'estado' => $resultado['estado'],
                    'cliente' => $resultado['cliente'],
                    'error' => null
                ];
            } else {
                return [
                    'exists' => false,
                    'finalizado' => false,
                    'estado' => null,
                    'cliente' => null,
                    'error' => "Orden no encontrada"
                ];
            }
        } catch (PDOException $e) {
            return [
                'exists' => false,
                'finalizado' => false,
                'estado' => null,
                'cliente' => null,
                'error' => "Error al verificar orden: " . $e->getMessage()
            ];
        }
    }

    /**
     * Revertir finalización de una orden 
     * @param int $numeroOrden
     * @return array
     */
    public function revertirFinalizacionOrden($numeroOrden)
    {
        try {
            $this->conexion->beginTransaction();

            $sql = "UPDATE public.sist_ventas_orden_produccion 
                SET finalizado = false,
                    estado = 'Pendiente'
                WHERE id = :numero_orden AND finalizado = true";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                $this->conexion->commit();

                error_log("🔄 Finalización revertida - Orden: $numeroOrden");

                return [
                    'success' => true,
                    'orden_id' => $numeroOrden,
                    'error' => null
                ];
            } else {
                throw new Exception("No se pudo revertir la finalización o la orden no estaba finalizada");
            }
        } catch (Exception $e) {
            $this->conexion->rollBack();

            return [
                'success' => false,
                'orden_id' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 🆕 Verificar que items existen en un rango específico
     * @param int $numeroOrden
     * @param int $itemDesde
     * @param int $itemHasta
     * @return array
     */
    public function verificarRangoItems($numeroOrden, $itemDesde, $itemHasta)
    {
        try {
            $sql = "SELECT 
                    id,
                    numero_item,
                    peso_bruto,
                    peso_liquido,
                    tara,
                    tipo_producto,
                    fecha_hora_producida,
                    fecha_hora_producida::time as hora_registro
                FROM public.sist_prod_stock
                WHERE id_orden_produccion = :orden_id 
                AND numero_item BETWEEN :item_desde AND :item_hasta 
                ORDER BY numero_item ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':orden_id', $numeroOrden, PDO::PARAM_INT);
            $stmt->bindParam(':item_desde', $itemDesde, PDO::PARAM_INT);
            $stmt->bindParam(':item_hasta', $itemHasta, PDO::PARAM_INT);
            $stmt->execute();

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("🔍 Verificación de rango - Orden: $numeroOrden, Rango: $itemDesde-$itemHasta, Encontrados: " . count($items));

            return $items;
        } catch (PDOException $e) {
            error_log("💥 Error verificando rango de items: " . $e->getMessage());
            throw new Exception("Error al verificar items en el rango especificado");
        }
    }

    /**
     * 🆕 Obtener todos los items disponibles para una orden
     * @param int $numeroOrden
     * @return array
     */
    public function obtenerItemsOrden($numeroOrden)
    {
        try {
            $sql = "SELECT 
                    MIN(numero_item) as primer_item,
                    MAX(numero_item) as ultimo_item,
                    COUNT(*) as total_items,
                    ARRAY_AGG(numero_item ORDER BY numero_item) as items_disponibles
                FROM public.sist_prod_stock
                WHERE id_orden_produccion = :orden_id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':orden_id', $numeroOrden, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado && $resultado['total_items'] > 0) {
                // Convertir el array de PostgreSQL a array PHP
                $itemsArray = $resultado['items_disponibles'];
                if (is_string($itemsArray)) {
                    // Limpiar formato de PostgreSQL: {1,2,3} -> [1,2,3]
                    $itemsArray = str_replace(['{', '}'], '', $itemsArray);
                    $itemsArray = !empty($itemsArray) ? explode(',', $itemsArray) : [];
                    $itemsArray = array_map('intval', $itemsArray);
                }

                return [
                    'success' => true,
                    'primer_item' => (int)$resultado['primer_item'],
                    'ultimo_item' => (int)$resultado['ultimo_item'],
                    'total_items' => (int)$resultado['total_items'],
                    'items_disponibles' => $itemsArray,
                    'error' => null
                ];
            } else {
                return [
                    'success' => false,
                    'primer_item' => 0,
                    'ultimo_item' => 0,
                    'total_items' => 0,
                    'items_disponibles' => [],
                    'error' => 'No hay items registrados para esta orden'
                ];
            }
        } catch (PDOException $e) {
            error_log("💥 Error obteniendo items de orden: " . $e->getMessage());
            return [
                'success' => false,
                'primer_item' => 0,
                'ultimo_item' => 0,
                'total_items' => 0,
                'items_disponibles' => [],
                'error' => 'Error al consultar items de la orden'
            ];
        }
    }

    /**
     * 🆕 Obtener estadísticas de items para reimpresión en lote
     * @param int $numeroOrden
     * @return array
     */
    public function obtenerEstadisticasItems($numeroOrden)
    {
        try {
            $sql = "SELECT 
                    tipo_producto,
                    COUNT(*) as cantidad,
                    MIN(numero_item) as primer_item,
                    MAX(numero_item) as ultimo_item,
                    SUM(peso_bruto) as peso_total_bruto,
                    SUM(peso_liquido) as peso_total_liquido
                FROM public.sist_prod_stock
                WHERE id_orden_produccion = :orden_id 
                GROUP BY tipo_producto
                ORDER BY tipo_producto";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':orden_id', $numeroOrden, PDO::PARAM_INT);
            $stmt->execute();

            $estadisticas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener totales generales
            $sqlTotal = "SELECT 
                        COUNT(*) as total_items,
                        MIN(numero_item) as primer_item_global,
                        MAX(numero_item) as ultimo_item_global
                     FROM public.sist_prod_stock
                     WHERE id_orden_produccion = :orden_id";

            $stmtTotal = $this->conexion->prepare($sqlTotal);
            $stmtTotal->bindParam(':orden_id', $numeroOrden, PDO::PARAM_INT);
            $stmtTotal->execute();

            $totales = $stmtTotal->fetch(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'estadisticas_por_tipo' => $estadisticas,
                'totales' => $totales,
                'error' => null
            ];
        } catch (PDOException $e) {
            error_log("💥 Error obteniendo estadísticas de items: " . $e->getMessage());
            return [
                'success' => false,
                'estadisticas_por_tipo' => [],
                'totales' => [],
                'error' => 'Error al obtener estadísticas de items'
            ];
        }
    }


    /**
     * Obtener datos de peso teórico basado en especificaciones del producto
     * REEMPLAZA a obtenerPesoPromedioOrden()
     * @param int $numeroOrden
     * @param int $bobinasPacote - cantidad de bobinas por paquete
     * @param int $metragem - metraje a usar para el cálculo (del formulario)
     * @return array
     */
    public function obtenerPesoTeoricoOrden($numeroOrden, $bobinasPacote = 1, $metragem = null)
    {
        try {
            // Obtener especificaciones del producto de la orden
            $sql = "SELECT 
                    gramatura, 
                    largura_metros, 
                    longitud_bobina,
                    tipo
                FROM (
                    SELECT gramatura, largura_metros, longitud_bobina, 'TNT' as tipo
                    FROM public.sist_ventas_op_tnt 
                    WHERE id_orden_produccion = :numero_orden 
                    AND (LOWER(COALESCE(nombre, '')) NOT LIKE '%laminado%' OR nombre IS NULL)
                    
                    UNION ALL
                    
                    SELECT gramatura, largura_metros, longitud_bobina, 'LAMINADORA' as tipo
                    FROM public.sist_ventas_op_tnt 
                    WHERE id_orden_produccion = :numero_orden 
                    AND LOWER(COALESCE(nombre, '')) LIKE '%laminado%'
                    
                    UNION ALL
                    
                    SELECT gramatura, largura_metros, longitud_bobina, 'SPUNLACE' as tipo
                    FROM public.sist_ventas_op_spunlace 
                    WHERE id_orden_produccion = :numero_orden
                ) especificaciones
                LIMIT 1";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
            $stmt->execute();

            $especificaciones = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$especificaciones ||
                !$especificaciones['gramatura'] ||
                !$especificaciones['largura_metros']
            ) {

                return [
                    'success' => false,
                    'peso_teorico' => 0,
                    'rango_15_inferior' => 0,
                    'rango_15_superior' => 0,
                    'bobinas_pacote' => $bobinasPacote,
                    'error' => 'Producto no tiene especificaciones técnicas (TOALLITAS/PAÑOS) o faltan datos'
                ];
            }

            $gramatura = floatval($especificaciones['gramatura']);
            $largura = floatval($especificaciones['largura_metros']);

            // Usar metragem del parámetro o por defecto del producto
            $metragemUsar = $metragem ?? intval($especificaciones['longitud_bobina'] ?? 0);

            if ($metragemUsar <= 0) {
                return [
                    'success' => false,
                    'peso_teorico' => 0,
                    'rango_15_inferior' => 0,
                    'rango_15_superior' => 0,
                    'bobinas_pacote' => $bobinasPacote,
                    'error' => 'Metragem debe ser mayor a 0'
                ];
            }

            // CALCULAR PESO TEÓRICO: (gramatura * metragem * largura / 1000) * bobinas_pacote
            $pesoTeorico = ($gramatura * $metragemUsar * $largura / 1000.0) * $bobinasPacote;

            // RANGOS ±15%
            $rango15Inferior = round($pesoTeorico * 0.85, 2);
            $rango15Superior = round($pesoTeorico * 1.15, 2);

            return [
                'success' => true,
                'peso_teorico' => round($pesoTeorico, 2),
                'peso_promedio' => round($pesoTeorico, 2), // Mantener compatibilidad con JS
                'rango_15_inferior' => $rango15Inferior,
                'rango_15_superior' => $rango15Superior,
                'bobinas_pacote' => $bobinasPacote,
                'gramatura' => $gramatura,
                'largura' => $largura,
                'metragem' => $metragemUsar,
                'total_registros' => 1, // Siempre 1 para peso teórico
                'error' => null
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'peso_teorico' => 0,
                'peso_promedio' => 0,
                'rango_15_inferior' => 0,
                'rango_15_superior' => 0,
                'bobinas_pacote' => $bobinasPacote,
                'error' => 'Error al calcular peso teórico: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Calcular peso teórico basado en especificaciones
     */
    private function calcularPesoTeorico($gramatura, $metragem, $largura, $bobinas_pacote)
    {
        if (!$gramatura || !$metragem || !$largura || !$bobinas_pacote) {
            return 0;
        }
        return ($gramatura * $metragem * $largura / 1000.0) * $bobinas_pacote;
    }

    /**
     * Clasificar material según diferencia de peso
     */
    private function clasificarPeso($peso_real, $peso_teorico)
    {
        if ($peso_teorico == 0) {
            return [
                'categoria' => 'Sin datos técnicos',
                'clase' => 'sin-datos',
                'icono' => 'question-circle',
                'diferencia' => 0
            ];
        }

        $diferencia_porcentual = (($peso_real - $peso_teorico) / $peso_teorico) * 100;

        // 🟢 1. RANGO ÓPTIMO: Entre -2.1% y +0.5%
        if ($peso_real > ($peso_teorico * 0.979) && $peso_real <= ($peso_teorico * 1.005)) {
            return [
                'categoria' => 'DENTRO DE LA MEDIA ±2%',
                'clase' => 'dentro-media',
                'icono' => 'check-circle',
                'diferencia' => $diferencia_porcentual
            ];
        }

        // 🟡 2. MATERIAL LIGERAMENTE PESADO: +0.5% a +1%
        elseif ($peso_real > ($peso_teorico * 1.005) && $peso_real <= ($peso_teorico * 1.01)) {
            return [
                'categoria' => 'Material Ligeramente Pesado',
                'clase' => 'pesado-05',
                'icono' => 'arrow-up',
                'diferencia' => $diferencia_porcentual
            ];
        }

        // 🔵 3. MATERIAL LIVIANO LEVE: -2.1% a -4%
        elseif ($peso_real >= ($peso_teorico * 0.96) && $peso_real <= ($peso_teorico * 0.979)) {
            return [
                'categoria' => 'Material Liviano Leve',
                'clase' => 'liviano-3',
                'icono' => 'arrow-down',
                'diferencia' => $diferencia_porcentual
            ];
        }

        // 🔴 4. MATERIAL MUY LIVIANO: Menor a -4%
        elseif ($peso_real < ($peso_teorico * 0.96)) {
            return [
                'categoria' => 'CRÍTICO: Muy Liviano >4%',
                'clase' => 'muy-liviano',
                'icono' => 'exclamation-triangle',
                'diferencia' => $diferencia_porcentual
            ];
        }

        // 🟠 5. MATERIAL MUY PESADO: Mayor a +1%
        elseif ($peso_real > ($peso_teorico * 1.01)) {
            return [
                'categoria' => 'CRÍTICO: Muy Pesado >1%',
                'clase' => 'pesado-1',
                'icono' => 'exclamation-triangle',
                'diferencia' => $diferencia_porcentual
            ];
        }

        // 🟰 6. CASO EXTREMO: Peso exacto (raro pero posible)
        else {
            return [
                'categoria' => 'Peso Exacto',
                'clase' => 'peso-exacto',
                'icono' => 'bullseye',
                'diferencia' => 0
            ];
        }
    }


    /**
     * 🆕 Obtener registro filtrado por ID - CORREGIDO SIN CONFLICTOS DE TIPOS
     */
    public function obtenerRegistroFiltrado($idStock)
    {
        try {
            $idStock = intval($idStock);

            // ✅ CONSULTA SIMPLIFICADA - Solo usar datos del registro actual
            $sql = "SELECT id, numero_item, peso_bruto, peso_liquido,
                       metragem, bobinas_pacote, 
                       COALESCE(gramatura, 0) as gramatura, 
                       COALESCE(largura, 0) as largura,
                       COALESCE(tara, 0) as tara, 
                       tipo_producto,
                       TO_CHAR(fecha_hora_producida, 'DD/MM') as fecha,
                       TO_CHAR(fecha_hora_producida, 'HH24:MI') as hora,
                       id_orden_produccion
                FROM public.sist_prod_stock 
                WHERE id = :id_stock 
                ORDER BY numero_item DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_stock', $idStock, PDO::PARAM_INT);
            $stmt->execute();
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($registro) {
                // ✅ Usar datos directos del registro (ya están correctos)
                $gramatura = floatval($registro['gramatura'] ?? 0);
                $largura = floatval($registro['largura'] ?? 0);
                $metragem = intval($registro['metragem'] ?? 0);
                $bobinas_pacote = intval($registro['bobinas_pacote'] ?? 1);

                // Solo clasificar para TNT/SPUNLACE/LAMINADORA
                if ($registro['tipo_producto'] !== 'TOALLITAS' && $registro['tipo_producto'] !== 'PAÑOS') {
                    $peso_teorico = $this->calcularPesoTeorico($gramatura, $metragem, $largura, $bobinas_pacote);
                    $clasificacion = $this->clasificarPeso(floatval($registro['peso_liquido']), $peso_teorico);

                    $registro['peso_teorico'] = $peso_teorico;
                    $registro['clasificacion'] = $clasificacion;
                } else {
                    $registro['peso_teorico'] = 0;
                    $registro['clasificacion'] = [
                        'categoria' => 'N/A',
                        'clase' => 'no-aplica',
                        'icono' => 'info-circle',
                        'diferencia' => 0
                    ];
                }

                return [
                    'total_registros' => 1,
                    'total_paginas' => 1,
                    'registros' => [$registro],
                    'filtrado' => true,
                    'id_filtro' => $idStock
                ];
            } else {
                return [
                    'total_registros' => 0,
                    'total_paginas' => 0,
                    'registros' => [],
                    'filtrado' => true,
                    'id_filtro' => $idStock,
                    'error' => "ID $idStock no encontrado"
                ];
            }
        } catch (PDOException $e) {
            error_log("💥 Error en obtenerRegistroFiltrado: " . $e->getMessage());
            return [
                'total_registros' => 0,
                'total_paginas' => 0,
                'registros' => [],
                'filtrado' => true,
                'id_filtro' => $idStock,
                'error' => "Error al buscar registro: " . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener diferencia de peso entre solicitado y producido
     * @param int $numeroOrden
     * @param array $producto - datos del producto de la orden
     * @return array
     */
    public function obtenerDiferenciaPeso($numeroOrden, $producto)
    {
        try {
            $tipoProducto = $producto['tipo'];

            // Solo para TNT/SPUNLACE/LAMINADORA que tienen especificaciones técnicas
            if ($tipoProducto === 'TOALLITAS' || $tipoProducto === 'PAÑOS') {
                return [
                    'success' => false,
                    'motivo' => 'No aplica para ' . $tipoProducto
                ];
            }

            // 1. CALCULAR PESO TEÓRICO SOLICITADO
            $gramatura = floatval($producto['gramatura'] ?? 0);
            $largura = floatval($producto['largura_metros'] ?? 0);
            $totalBobinas = intval($producto['total_bobinas'] ?? 0);
            $longitud = floatval($producto['longitud_bobina'] ?? 0);

            if ($gramatura <= 0 || $largura <= 0 || $totalBobinas <= 0 || $longitud <= 0) {
                return [
                    'success' => false,
                    'motivo' => 'Faltan especificaciones técnicas del producto'
                ];
            }

            // Peso teórico = gramatura * longitud * largura * total_bobinas / 1000
            $pesoTeorico = ($gramatura * $longitud * $largura * $totalBobinas) / 1000.0;

            // 2. OBTENER PESO REAL PRODUCIDO
            $sql = "SELECT SUM(peso_liquido) as peso_total_producido 
                FROM public.sist_prod_stock 
                WHERE id_orden_produccion = :numero_orden 
                AND tipo_producto = :tipo_producto";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
            $stmt->bindParam(':tipo_producto', $tipoProducto, PDO::PARAM_STR);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $pesoProducido = floatval($resultado['peso_total_producido'] ?? 0);

            if ($pesoProducido <= 0) {
                return [
                    'success' => false,
                    'motivo' => 'No hay producción registrada'
                ];
            }

            // 3. CALCULAR DIFERENCIA Y PORCENTAJE
            $diferencia = $pesoProducido - $pesoTeorico;
            $porcentajeDiferencia = ($diferencia / $pesoTeorico) * 100;

            // 4. DETERMINAR ESTADO
            $dentrotolerancia = abs($porcentajeDiferencia) <= 3.0;
            $estado = '';
            $clase = '';

            if ($porcentajeDiferencia > 3) {
                $estado = 'MAT. PESADO';
                $clase = 'danger';
            } elseif ($porcentajeDiferencia < -3) {
                $estado = 'MAT. LIVIANO';
                $clase = 'danger';
            } else {
                $estado = 'OK';
                $clase = 'success';
            }

            return [
                'success' => true,
                'peso_teorico' => round($pesoTeorico, 3),
                'peso_producido' => round($pesoProducido, 3),
                'diferencia' => round($diferencia, 3),
                'porcentaje_diferencia' => round($porcentajeDiferencia, 2),
                'dentro_tolerancia' => $dentrotolerancia,
                'estado' => $estado,
                'clase' => $clase,
                'error' => null
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * ✅ NUEVO: Método para verificar la configuración de zona horaria
     * Útil para debugging
     */
    public function verificarZonaHoraria()
    {
        try {
            $sql = "SELECT 
                NOW() as hora_postgresql,
                NOW() AT TIME ZONE 'America/Asuncion' as hora_paraguay,
                current_setting('timezone') as timezone_config";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            error_log("🕐 Verificación de zona horaria:");
            error_log("   PostgreSQL NOW(): " . $resultado['hora_postgresql']);
            error_log("   Paraguay NOW(): " . $resultado['hora_paraguay']);
            error_log("   Timezone config: " . $resultado['timezone_config']);

            return $resultado;
        } catch (PDOException $e) {
            error_log("⚠️ Error verificando zona horaria: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener la conexión (método auxiliar)
     * @return PDO
     */
    public function getConexion()
    {
        return $this->conexion;
    }
}
