<?php

/**
 * Repository para manejo de datos de producción - CORREGIDO CON GESTIÓN DE RECETAS
 */
class ProduccionRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    /**
     * Obtener conexión (para transacciones)
     */
    public function getConexion()
    {
        return $this->conexion;
    }

    /**
     * Obtener información de venta en producción
     */
    public function obtenerVentaProduccion($idVenta)
    {
        try {
            $sql = "SELECT v.*, 
                    u.nombre as nombre_vendedor,
                    (SELECT COUNT(*) FROM public.sist_ventas_productos_produccion 
                     WHERE id_venta = v.id AND destino = 'Producción') AS num_productos_produccion
                    FROM public.sist_ventas_presupuesto v
                    LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                    WHERE v.id = :id 
                      AND (v.estado = 'En Producción/Expedición' OR v.estado = 'En Producción')";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo venta de producción: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener productos pendientes en producción
     */
    public function obtenerProductosPendientes($idVenta)
    {
        try {
            $sql = "SELECT pp.id, pp.id_producto, pp.fecha_asignacion, pp.cantidad, pp.observaciones,
                    prod.descripcion, prod.precio, prod.unidadmedida, prod.ncm, prod.tipoproducto
                    FROM public.sist_ventas_productos_produccion pp
                    JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
                    WHERE pp.id_venta = :id_venta AND pp.destino = 'Producción' 
                    AND (pp.estado IS NULL OR pp.estado = 'Pendiente')";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos pendientes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener productos completados en producción
     */
    public function obtenerProductosCompletados($idVenta)
    {
        try {
            $sql = "SELECT pp.id, pp.id_producto, pp.fecha_asignacion, pp.cantidad, pp.observaciones,
                      pp.fecha_completado, pp.observaciones_produccion,
                      prod.descripcion, prod.precio, prod.unidadmedida, prod.ncm, prod.tipoproducto
                      FROM public.sist_ventas_productos_produccion pp
                      JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
                      WHERE pp.id_venta = :id_venta AND pp.destino = 'Producción' 
                      AND pp.estado = 'Completado'";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos completados: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener información de producto de producción
     */
    public function obtenerInfoProductoProduccion($idProductoProduccion)
    {
        try {
            $sql = "SELECT pp.id_producto, pp.cantidad, prod.descripcion, prod.tipoproducto, prod.unidadmedida
                    FROM public.sist_ventas_productos_produccion pp
                    JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
                    WHERE pp.id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $idProductoProduccion, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo info producto producción: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ NUEVO: Obtener recetas por tipo de producto - CORREGIDO CON TABLA CORRECTA
     */
    public function obtenerRecetasPorTipoProducto($tipoProducto)
    {
        try {
            $sql = "SELECT r.id, r.nombre_receta, r.version_receta, r.cantidad_por_kilo, 
                           r.es_materia_extra, r.unidad_medida_extra, r.activo, 
                           r.fecha_creacion, r.usuario_creacion,
                           tp.\"desc\" as tipo_producto,
                           mp.descripcion as nombre_materia_prima
                    FROM public.sist_prod_recetas r
                    JOIN public.sist_ventas_tipoproduc tp ON r.id_tipo_producto = tp.id
                    LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                    WHERE tp.\"desc\" = :tipo_producto 
                      AND r.tipo_receta = 'PRODUCTO'
                      AND r.activo = true
                    ORDER BY r.nombre_receta, r.version_receta DESC, r.fecha_creacion DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':tipo_producto', $tipoProducto, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo recetas por tipo producto: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ NUEVO: Obtener todas las recetas activas - CORREGIDO CON TABLA CORRECTA
     */
    public function obtenerTodasLasRecetas()
    {
        try {
            $sql = "SELECT r.id, r.nombre_receta, r.version_receta, r.cantidad_por_kilo, 
                           r.es_materia_extra, r.unidad_medida_extra, r.activo, 
                           r.fecha_creacion, r.usuario_creacion, 
                           tp.\"desc\" as tipo_producto,
                           mp.descripcion as nombre_materia_prima
                    FROM public.sist_prod_recetas r
                    JOIN public.sist_ventas_tipoproduc tp ON r.id_tipo_producto = tp.id
                    LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                    WHERE r.tipo_receta = 'PRODUCTO' AND r.activo = true
                    ORDER BY tp.\"desc\", r.nombre_receta, r.version_receta DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo todas las recetas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ NUEVO: Obtener tipos de producto disponibles
     */
    public function obtenerTiposProductoConRecetas()
    {
        try {
            $sql = "SELECT DISTINCT tp.id, tp.\"desc\" as tipo_producto
                    FROM public.sist_ventas_tipoproduc tp
                    JOIN public.sist_prod_recetas r ON tp.id = r.id_tipo_producto
                    WHERE r.activo = true AND r.tipo_receta = 'PRODUCTO'
                    ORDER BY tp.\"desc\"";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo tipos producto con recetas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ NUEVO: Asignar recetas a orden de producción - SIMPLIFICADO
     */
    public function asignarRecetasAOrden($idOrdenProduccion, $recetasSeleccionadas)
    {
        try {
            error_log("REPOSITORY - Asignando recetas a orden {$idOrdenProduccion}: " . json_encode($recetasSeleccionadas));

            $insertadas = 0;

            foreach ($recetasSeleccionadas as $idReceta) {
                // Verificar que la receta existe y está activa
                $sqlVerificar = "SELECT id FROM public.sist_prod_recetas WHERE id = :id_receta AND activo = true";
                $stmtVerificar = $this->conexion->prepare($sqlVerificar);
                $stmtVerificar->bindParam(':id_receta', $idReceta, PDO::PARAM_INT);
                $stmtVerificar->execute();

                if ($stmtVerificar->fetch()) {
                    // Insertar relación orden-receta
                    $sqlInsertar = "INSERT INTO public.sist_ventas_orden_produccion_recetas 
                                   (id_orden_produccion, id_receta, estado, fecha_asignacion) 
                                   VALUES (:id_orden, :id_receta, 'Pendiente', NOW())
                                   ON CONFLICT (id_orden_produccion, id_receta) 
                                   DO UPDATE SET fecha_asignacion = NOW()";

                    $stmtInsertar = $this->conexion->prepare($sqlInsertar);
                    $stmtInsertar->bindParam(':id_orden', $idOrdenProduccion, PDO::PARAM_INT);
                    $stmtInsertar->bindParam(':id_receta', $idReceta, PDO::PARAM_INT);

                    if ($stmtInsertar->execute()) {
                        $insertadas++;
                        error_log("REPOSITORY - Receta {$idReceta} insertada exitosamente");
                    } else {
                        error_log("REPOSITORY - ERROR insertando receta {$idReceta}: " . implode(", ", $stmtInsertar->errorInfo()));
                    }
                } else {
                    error_log("REPOSITORY - Receta {$idReceta} no existe o no está activa");
                }
            }

            error_log("REPOSITORY - Total recetas insertadas: {$insertadas} de " . count($recetasSeleccionadas));
            return $insertadas > 0;
        } catch (Exception $e) {
            error_log("REPOSITORY ERROR - Error asignando recetas: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ NUEVO: Obtener recetas asignadas a una orden de producción - SIMPLIFICADO SIN cantidad_requerida
     */
    public function obtenerRecetasAsignadasAOrden($idOrdenProduccion)
    {
        try {
            $sql = "SELECT opr.id, opr.estado, opr.fecha_asignacion,
                           r.nombre_receta, r.version_receta, r.cantidad_por_kilo,
                           r.es_materia_extra, r.unidad_medida_extra,
                           mp.descripcion as nombre_materia_prima,
                           tp.\"desc\" as tipo_producto
                    FROM public.sist_ventas_orden_produccion_recetas opr
                    JOIN public.sist_prod_recetas r ON opr.id_receta = r.id
                    JOIN public.sist_ventas_tipoproduc tp ON r.id_tipo_producto = tp.id
                    LEFT JOIN public.sist_prod_materia_prima mp ON r.id_materia_prima = mp.id
                    WHERE opr.id_orden_produccion = :id_orden
                    ORDER BY r.nombre_receta, r.version_receta DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_orden', $idOrdenProduccion, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo recetas asignadas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * CORREGIDO: Obtener cantidad real de inventario del producto desde sist_ventas_productos
     */
    public function obtenerCantidadInventario($idProductoProduccion)
    {
        try {
            $sql = "SELECT sp.cantidad as cantidad_inventario
                    FROM public.sist_ventas_productos_produccion pp
                    JOIN public.sist_ventas_pres_product ppp ON pp.id_producto = ppp.id
                    JOIN public.sist_ventas_productos sp ON ppp.id_producto = sp.id
                    WHERE pp.id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $idProductoProduccion, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ? (float)$resultado['cantidad_inventario'] : 0;
        } catch (Exception $e) {
            error_log("Error obteniendo cantidad inventario: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * NUEVO: Obtener gramatura actual (el peso que se calculaba antes)
     */
    public function obtenerGramaturaCalculada($idProductoProduccion)
    {
        try {
            // Obtener la cantidad del pedido y el peso líquido del inventario
            $sql = "SELECT 
                        pp.cantidad as cantidad_pedido,
                        sp.cantidad as peso_liquido_inventario
                    FROM public.sist_ventas_productos_produccion pp
                    JOIN public.sist_ventas_pres_product ppp ON pp.id_producto = ppp.id
                    JOIN public.sist_ventas_productos sp ON ppp.id_producto = sp.id
                    WHERE pp.id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $idProductoProduccion, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resultado) {
                return 0;
            }

            // Calcular gramatura = peso_liquido_inventario * cantidad_pedido
            $gramatura = (float)$resultado['peso_liquido_inventario'] * (float)$resultado['cantidad_pedido'];

            return $gramatura;
        } catch (Exception $e) {
            error_log("Error obteniendo gramatura calculada: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * MODIFICADO: Actualizar estado de producto en producción - mantener compatibilidad
     */
    public function actualizarEstadoProducto($idProductoProduccion, $estado, $observaciones = null)
    {
        try {
            $sql = "UPDATE public.sist_ventas_productos_produccion 
                SET estado = :estado, 
                    observaciones_produccion = :observaciones,
                    origen = 'Producción'
                WHERE id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $observaciones, PDO::PARAM_STR);
            $stmt->bindParam(':id', $idProductoProduccion, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando estado producto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crear orden de producción principal
     */
    public function crearOrdenProduccion($idVenta, $cliente, $observaciones)
    {
        try {
            $fechaProcesamiento = date('Y-m-d H:i:s');

            $sql = "INSERT INTO public.sist_ventas_orden_produccion 
                    (id_venta, fecha_orden, estado, observaciones, cliente) 
                    VALUES (:id_venta, :fecha, 'Orden Emitida', :observaciones, :cliente)
                    RETURNING id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->bindParam(':fecha', $fechaProcesamiento, PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $observaciones, PDO::PARAM_STR);
            $stmt->bindParam(':cliente', $cliente, PDO::PARAM_STR);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ? (int)$resultado['id'] : false;
        } catch (Exception $e) {
            error_log("Error creando orden de producción: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Insertar orden específica de TNT
     */
    public function insertarOrdenTNT($datos)
    {
        try {
            $sql = "INSERT INTO public.sist_ventas_op_tnt 
                    (id_orden_produccion, gramatura, largura_metros, longitud_bobina, color, 
                     peso_bobina, cantidad_total, total_bobinas, id_producto, id_producto_produccion, pesominbobina, nombre, id_venta) 
                    VALUES (:id_orden_produccion, :gramatura, :largura, :longitud, :color, 
                            :peso_bobina, :cantidad_total, :total_bobinas, :id_producto, :id_producto_produccion, :peso_min_bobina, :nombre, :id_venta)";

            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute($datos);
        } catch (Exception $e) {
            error_log("Error insertando orden TNT: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Insertar orden específica de Spunlace
     */
    public function insertarOrdenSpunlace($datos)
    {
        try {
            $sql = "INSERT INTO public.sist_ventas_op_spunlace 
                    (id_orden_produccion, gramatura, largura_metros, longitud_bobina, color, 
                     peso_bobina, cantidad_total, total_bobinas, id_producto, id_producto_produccion, pesominbobina, acabado, nombre, id_venta) 
                    VALUES (:id_orden_produccion, :gramatura, :largura, :longitud, :color, 
                            :peso_bobina, :cantidad_total, :total_bobinas, :id_producto, :id_producto_produccion, :peso_min_bobina, :acabado, :nombre, :id_venta)";

            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute($datos);
        } catch (Exception $e) {
            error_log("Error insertando orden Spunlace: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Insertar orden específica de Toallitas
     */
    public function insertarOrdenToallitas($datos)
    {
        try {
            $sql = "INSERT INTO public.sist_ventas_op_toallitas 
                    (id_orden_produccion, nombre, cantidad_total, id_producto, id_producto_produccion, id_venta) 
                    VALUES (:id_orden_produccion, :nombre, :cantidad_total, :id_producto, :id_producto_produccion, :id_venta)";

            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute($datos);
        } catch (Exception $e) {
            error_log("Error insertando orden Toallitas: " . $e->getMessage());
            return false;
        }
    }

    /**
     * CORREGIDO: Insertar orden específica de Paños - Ahora incluye gramatura
     */
    public function insertarOrdenPanosActualizado($datos)
    {
        error_log("=== INSERTANDO EN OP_PANOS CORREGIDO ===");
        error_log("Datos recibidos: " . json_encode($datos));

        try {
            $sql = "INSERT INTO public.sist_ventas_op_panos 
                (id_orden_produccion, nombre, cantidad_total, color, largura, picotado, cant_panos, unidad, peso, gramatura, id_producto, id_venta, id_producto_produccion) 
                VALUES (:id_orden_produccion, :nombre, :cantidad_total, :color, :largura, :picotado, :cant_panos, :unidad, :peso, :gramatura, :id_producto, :id_venta, :id_producto_produccion)";

            error_log("SQL a ejecutar: " . $sql);

            $stmt = $this->conexion->prepare($sql);
            $resultado = $stmt->execute($datos);

            if (!$resultado) {
                error_log("ERROR SQL: " . implode(", ", $stmt->errorInfo()));
            } else {
                error_log("INSERCIÓN EXITOSA EN OP_PANOS CON GRAMATURA");
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("EXCEPCIÓN en insertarOrdenPanosActualizado: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Insertar orden específica de Paños (método antiguo - mantener por compatibilidad)
     * @deprecated Usar insertarOrdenPanosActualizado en su lugar
     */
    public function insertarOrdenPanos($datos)
    {
        try {
            $sql = "INSERT INTO public.sist_ventas_op_panos 
                    (id_orden_produccion, nombre, gramatura, ancho, largo, color, 
                     peso_bobina, cantidad_total, id_producto, id_producto_produccion, pesominbobina, id_venta) 
                    VALUES (:id_orden_produccion, :nombre, :gramatura, :ancho, :largo, :color, 
                            :peso_bobina, :cantidad_total, :id_producto, :id_producto_produccion, :peso_min_bobina, :id_venta)";

            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute($datos);
        } catch (Exception $e) {
            error_log("Error insertando orden Paños (método antiguo): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar cantidad de producto en producción (para devoluciones parciales)
     */
    public function actualizarCantidadProducto($idProductoProduccion, $nuevaCantidad, $observaciones)
    {
        try {
            $fechaProcesamiento = date('Y-m-d H:i:s');

            $sql = "UPDATE public.sist_ventas_productos_produccion 
                    SET cantidad = :cantidad,
                        fecha_asignacion = :fecha,
                        observaciones_produccion = COALESCE(observaciones_produccion, '') || '\n' || :observaciones
                    WHERE id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':fecha', $fechaProcesamiento, PDO::PARAM_STR);
            $stmt->bindParam(':cantidad', $nuevaCantidad, PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $observaciones, PDO::PARAM_STR);
            $stmt->bindParam(':id', $idProductoProduccion, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando cantidad producto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Marcar producto como devuelto COMPLETO a PCP - VERSIÓN CORREGIDA
     */
    public function marcarProductoDevuelto($idProductoProduccion, $observaciones, $idUsuario = null)
    {
        try {
            $fechaProcesamiento = date('Y-m-d H:i:s');

            // CORRECCIÓN: Agregar id_usuario_pcp
            $sql = "UPDATE public.sist_ventas_productos_produccion 
                SET estado = 'Devuelto a PCP', 
                    destino = 'Devuelto a PCP',
                    fecha_asignacion = :fecha,
                    observaciones_produccion = :observaciones,
                    origen = 'Producción',
                    id_usuario_pcp = :id_usuario
                WHERE id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':fecha', $fechaProcesamiento, PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $observaciones, PDO::PARAM_STR);
            $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmt->bindParam(':id', $idProductoProduccion, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error marcando producto como devuelto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crear registro de devolución a PCP - VERSIÓN CORREGIDA
     */
    public function crearRegistroDevolucion($idVenta, $idProducto, $cantidadDevuelta, $observaciones, $idUsuario)
    {
        try {
            $fechaProcesamiento = date('Y-m-d H:i:s');

            // CORRECCIÓN: Asegurar que todos los campos necesarios estén presentes
            $sql = "INSERT INTO public.sist_ventas_productos_produccion 
                (id_venta, id_producto, id_usuario_pcp, fecha_asignacion, destino, cantidad, observaciones_produccion, estado, origen) 
                VALUES (:id_venta, :id_producto, :id_usuario, :fecha, 'Devuelto a PCP', :cantidad, :observaciones, 'Devuelto a PCP', 'Producción')";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmt->bindParam(':fecha', $fechaProcesamiento, PDO::PARAM_STR);
            $stmt->bindParam(':cantidad', $cantidadDevuelta, PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $observaciones, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error creando registro devolución: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ CORREGIDO: Actualizar estado completo de producto - AHORA SIN cantidad_completada
     * Actualiza el campo cantidad con la cantidad real de la orden de producción
     */
    public function actualizarEstadoCompletoProducto($idProductoProduccion, $estado, $cantidadRealOrden, $observaciones = null)
    {
        try {
            $fechaCompletado = date('Y-m-d H:i:s');

            // ✅ CORRECCIÓN: Usar campo cantidad en lugar de cantidad_completada que ya no existe
            $sql = "UPDATE public.sist_ventas_productos_produccion 
                SET estado = :estado, 
                    movimiento = 'PENDIENTE',
                    cantidad = :cantidad_real,
                    fecha_completado = :fecha_completado,
                    observaciones_produccion = :observaciones,
                    origen = 'Producción'
                WHERE id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
            $stmt->bindParam(':cantidad_real', $cantidadRealOrden, PDO::PARAM_STR); // ✅ Ahora va a campo cantidad
            $stmt->bindParam(':fecha_completado', $fechaCompletado, PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $observaciones, PDO::PARAM_STR);
            $stmt->bindParam(':id', $idProductoProduccion, PDO::PARAM_INT);

            $resultado = $stmt->execute();

            if ($resultado) {
                error_log("SUCCESS - Actualizado producto producción ID: $idProductoProduccion con movimiento=PENDIENTE, cantidad=$cantidadRealOrden");
            } else {
                error_log("ERROR - Fallo al actualizar producto producción ID: $idProductoProduccion");
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("Error actualizando estado completo producto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * NUEVO MÉTODO: Obtener cantidad real de la orden de producción creada
     */
    public function obtenerCantidadRealOrdenProduccion($idProductoProduccion, $tipoProducto)
    {
        try {
            $tabla = '';
            $campoTotal = '';

            switch (strtoupper($tipoProducto)) {
                case 'TNT':
                case 'LAMINADORA':
                    $tabla = 'sist_ventas_op_tnt';
                    $campoTotal = 'cantidad_total';
                    break;
                case 'SPUNLACE':
                    $tabla = 'sist_ventas_op_spunlace';
                    $campoTotal = 'cantidad_total';
                    break;
                case 'TOALLITAS':
                    $tabla = 'sist_ventas_op_toallitas';
                    $campoTotal = 'cantidad_total';
                    break;
                case 'PAÑOS':
                    $tabla = 'sist_ventas_op_panos';
                    $campoTotal = 'cantidad_total';
                    break;
                default:
                    return null;
            }

            $sql = "SELECT $campoTotal as cantidad_real 
                FROM public.$tabla 
                WHERE id_producto_produccion = :id_producto_produccion 
                ORDER BY id DESC LIMIT 1";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_producto_produccion', $idProductoProduccion, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ? (float)$resultado['cantidad_real'] : null;
        } catch (Exception $e) {
            error_log("Error obteniendo cantidad real orden: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Insertar historial de acciones
     */
    public function insertarHistorialAccion($datos)
    {
        try {
            $sql = "INSERT INTO public.sist_ventas_historial_acciones 
                    (id_venta, id_usuario, sector, accion, fecha_accion, observaciones, estado_resultante) 
                    VALUES (:id_venta, :id_usuario, :sector, :accion, :fecha, :observaciones, :estado)";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $datos['id_venta'], PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario', $datos['id_usuario'], PDO::PARAM_INT);
            $stmt->bindParam(':sector', $datos['sector'], PDO::PARAM_STR);
            $stmt->bindParam(':accion', $datos['accion'], PDO::PARAM_STR);
            $stmt->bindParam(':fecha', $datos['fecha_accion'], PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $datos['observaciones'], PDO::PARAM_STR);
            $stmt->bindParam(':estado', $datos['estado_resultante'], PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error insertando historial: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener ventas en producción con filtros
     */
    public function obtenerVentasProduccion($filtros = [], $pagina = 1, $registrosPorPagina = 10)
    {
        try {
            $offset = ($pagina - 1) * $registrosPorPagina;
            $condiciones = [];
            $parametros = [];

            // Condición base
            $condiciones[] = "(v.estado = 'En Producción' OR v.estado = 'En Producción/Expedición')";

            // Aplicar filtros
            if (!empty($filtros['cliente'])) {
                $condiciones[] = "v.cliente ILIKE :cliente";
                $parametros[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['vendedor'])) {
                $condiciones[] = "u.nombre ILIKE :vendedor";
                $parametros[':vendedor'] = '%' . $filtros['vendedor'] . '%';
            }

            $whereClause = 'WHERE ' . implode(' AND ', $condiciones);

            // Consulta principal
            $sql = "SELECT v.*, u.nombre as nombre_vendedor,
                    (SELECT COUNT(*) FROM public.sist_ventas_productos_produccion pp 
                     WHERE pp.id_venta = v.id AND pp.destino = 'Producción' 
                     AND (pp.estado IS NULL OR pp.estado = 'Pendiente')) as productos_pendientes,
                    (SELECT COUNT(*) FROM public.sist_ventas_productos_produccion pp 
                     WHERE pp.id_venta = v.id AND pp.destino = 'Producción' 
                     AND pp.estado = 'Completado') as productos_completados
                    FROM public.sist_ventas_presupuesto v
                    LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                    $whereClause
                    ORDER BY v.fecha_venta DESC
                    LIMIT :limite OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':limite', $registrosPorPagina, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

            foreach ($parametros as $param => $valor) {
                $stmt->bindParam($param, $valor);
            }

            $stmt->execute();
            $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Contar total
            $sqlCount = "SELECT COUNT(*) as total
                         FROM public.sist_ventas_presupuesto v
                         LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                         $whereClause";

            $stmtCount = $this->conexion->prepare($sqlCount);
            foreach ($parametros as $param => $valor) {
                $stmtCount->bindParam($param, $valor);
            }
            $stmtCount->execute();
            $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'ventas' => $ventas,
                'total_registros' => $totalRegistros,
                'total_paginas' => ceil($totalRegistros / $registrosPorPagina),
                'pagina_actual' => $pagina
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo ventas en producción: " . $e->getMessage());
            return [
                'ventas' => [],
                'total_registros' => 0,
                'total_paginas' => 0,
                'pagina_actual' => $pagina
            ];
        }
    }

    /**
     * Verificar si existe tabla
     */
    public function verificarTablaExiste($nombreTabla)
    {
        try {
            $sql = "SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = :tabla
            )";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':tabla', $nombreTabla, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error verificando tabla: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener detalles de orden de producción de paños
     */
    public function obtenerDetallesOrdenPanos($idOrdenProduccion)
    {
        try {
            $sql = "SELECT op.*, o.cliente, o.fecha_orden
                    FROM public.sist_ventas_op_panos op
                    JOIN public.sist_ventas_orden_produccion o ON op.id_orden_produccion = o.id
                    WHERE op.id_orden_produccion = :id_orden";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_orden', $idOrdenProduccion, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo detalles orden paños: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar estado de venta
     */
    public function actualizarEstadoVenta($idVenta, $nuevoEstado)
    {
        try {
            $sql = "UPDATE public.sist_ventas_presupuesto 
                SET estado = :estado 
                WHERE id = :id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':estado', $nuevoEstado, PDO::PARAM_STR);
            $stmt->bindParam(':id', $idVenta, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando estado de venta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si todos los productos de una venta están devueltos a PCP
     */
    public function verificarTodosProductosDevueltos($idVenta)
    {
        try {
            $sql = "SELECT 
                    COUNT(*) as total_productos,
                    COUNT(CASE WHEN estado = 'Devuelto a PCP' OR destino = 'Devuelto a PCP' THEN 1 END) as productos_devueltos
                FROM public.sist_ventas_productos_produccion 
                WHERE id_venta = :id_venta AND destino IN ('Producción', 'Devuelto a PCP')";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return $resultado && $resultado['total_productos'] > 0 &&
                $resultado['total_productos'] == $resultado['productos_devueltos'];
        } catch (Exception $e) {
            error_log("Error verificando productos devueltos: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener historial de acciones del sector Producción (SIN restricciones de usuario)
     */
    public function obtenerHistorialProduccion($filtros = [], $limite = 10, $offset = 0)
    {
        try {
            $whereConditions = ["h.sector = 'Producción'"]; // SOLO sector Producción
            $params = [];

            // Filtro por ID de venta
            if (!empty($filtros['id_venta'])) {
                $whereConditions[] = "v.id::text ILIKE :id_venta";
                $params[':id_venta'] = '%' . $filtros['id_venta'] . '%';
            }

            // Filtro por cliente
            if (!empty($filtros['cliente_historial'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente_historial";
                $params[':cliente_historial'] = '%' . $filtros['cliente_historial'] . '%';
            }

            // Filtro por usuario específico
            if (!empty($filtros['id_usuario'])) {
                $whereConditions[] = "h.id_usuario = :id_usuario_filtro";
                $params[':id_usuario_filtro'] = $filtros['id_usuario'];
            }

            // Filtro por tipo de acción
            if (!empty($filtros['accion'])) {
                $whereConditions[] = "h.accion ILIKE :accion";
                $params[':accion'] = '%' . $filtros['accion'] . '%';
            }

            // Filtros de fecha
            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "h.fecha_accion >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "h.fecha_accion <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "SELECT h.id, h.id_venta, h.sector, h.accion, h.fecha_accion, h.observaciones, h.estado_resultante,
                v.cliente, v.moneda, v.monto_total,
                u.nombre as nombre_usuario
                FROM public.sist_ventas_historial_acciones h
                JOIN public.sist_ventas_presupuesto v ON h.id_venta = v.id
                JOIN public.sist_ventas_usuario u ON h.id_usuario = u.id
                WHERE {$whereClause}
                ORDER BY h.fecha_accion DESC
                LIMIT :limit OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo historial de producción: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar historial de acciones del sector Producción
     */
    public function contarHistorialProduccion($filtros = [])
    {
        try {
            $whereConditions = ["h.sector = 'Producción'"]; // SOLO sector Producción
            $params = [];

            // Aplicar los mismos filtros que en obtenerHistorialProduccion
            if (!empty($filtros['id_venta'])) {
                $whereConditions[] = "v.id::text ILIKE :id_venta";
                $params[':id_venta'] = '%' . $filtros['id_venta'] . '%';
            }

            if (!empty($filtros['cliente_historial'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente_historial";
                $params[':cliente_historial'] = '%' . $filtros['cliente_historial'] . '%';
            }

            if (!empty($filtros['id_usuario'])) {
                $whereConditions[] = "h.id_usuario = :id_usuario_filtro";
                $params[':id_usuario_filtro'] = $filtros['id_usuario'];
            }

            if (!empty($filtros['accion'])) {
                $whereConditions[] = "h.accion ILIKE :accion";
                $params[':accion'] = '%' . $filtros['accion'] . '%';
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "h.fecha_accion >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "h.fecha_accion <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "SELECT COUNT(*) as total
                FROM public.sist_ventas_historial_acciones h
                JOIN public.sist_ventas_presupuesto v ON h.id_venta = v.id
                JOIN public.sist_ventas_usuario u ON h.id_usuario = u.id
                WHERE {$whereClause}";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'];
        } catch (PDOException $e) {
            error_log("Error contando historial de producción: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener usuarios que han realizado acciones en producción
     */
    public function obtenerUsuariosProduccion()
    {
        try {
            $sql = "SELECT DISTINCT u.id, u.nombre 
                FROM public.sist_ventas_usuario u
                JOIN public.sist_ventas_historial_acciones h ON u.id = h.id_usuario
                WHERE h.sector = 'Producción' AND u.activo = true
                ORDER BY u.nombre";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo usuarios de producción: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener tipos de acciones realizadas en producción
     */
    public function obtenerAccionesProduccion()
    {
        try {
            $sql = "SELECT DISTINCT accion 
                FROM public.sist_ventas_historial_acciones 
                WHERE sector = 'Producción' AND accion IS NOT NULL 
                ORDER BY accion";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo acciones de producción: " . $e->getMessage());
            return [];
        }
    }
}
