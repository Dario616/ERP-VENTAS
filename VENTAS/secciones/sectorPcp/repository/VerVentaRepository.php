<?php

class VerVentaRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    public function obtenerVentaPorId($idVenta)
    {
        try {
            $sql = "SELECT v.*, 
                    u.nombre as nombre_vendedor,
                    a.id as id_autorizacion,
                    a.descripcion as descripcion_vendedor,
                    a.descripcion as descripcion_autorizacion,
                    a.fecha_registro as fecha_envio,
                    a.fecha_respuesta as fecha_aprobacion,
                    a.observaciones_contador,
                    uc.nombre as nombre_contador,
                    (SELECT COUNT(*) FROM public.sist_ventas_pres_product WHERE id_presupuesto = v.id) AS num_productos
                    FROM public.sist_ventas_presupuesto v
                    LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                    LEFT JOIN public.sist_ventas_autorizaciones a ON v.id = a.id_venta
                    LEFT JOIN public.sist_ventas_usuario uc ON a.id_contador = uc.id
                    WHERE v.id = :id AND v.estado = 'Enviado a PCP'";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo venta por ID: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerProductosVenta($idVenta)
    {
        try {
            $sql = "SELECT pp.*, 
                           pp.unidadmedida,
                           sp.cantidad as peso_por_bobina,
                           sp.tipo as tipo_catalogo
                     FROM public.sist_ventas_pres_product pp
                     LEFT JOIN public.sist_ventas_productos sp ON pp.id_producto = sp.id
                     WHERE pp.id_presupuesto = :id_presupuesto";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_presupuesto', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos de venta: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerImagenesAutorizacion($idAutorizacion)
    {
        try {
            $sql = "SELECT id, nombre_archivo, tipo_archivo, base64_imagen, descripcion_imagen, orden_imagen
                   FROM public.sist_ventas_autorizaciones_imagenes 
                   WHERE id_autorizacion = :id_autorizacion 
                   ORDER BY orden_imagen ASC, id ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_autorizacion', $idAutorizacion, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo imágenes de autorización: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerImagenesProductos($idsProductos)
    {
        try {
            if (empty($idsProductos)) {
                return [];
            }

            $idsString = implode(',', $idsProductos);
            $sql = "SELECT id, base64img as imagen, tipoimg as tipo, nombreimg as nombre 
                   FROM public.sist_ventas_productos 
                   WHERE id IN ($idsString) AND base64img IS NOT NULL";

            $stmt = $this->conexion->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo imágenes de productos: " . $e->getMessage());
            return [];
        }
    }


    public function obtenerStockGeneral($nombresProductos = [])
    {
        try {
            if (empty($nombresProductos)) {
                error_log("DEBUG - obtenerStockGeneral: Array de productos está vacío");
                return [];
            }

            error_log("DEBUG - obtenerStockGeneral: Buscando productos: " . json_encode($nombresProductos));

            $resultados = [];

            // ✅ USAR CONSULTA DIRECTA que retorna campos con nombres correctos
            foreach ($nombresProductos as $nombreProducto) {
                $sql = "SELECT 
                nombre_producto,
                tipo_producto,
                bobinas_pacote,
                cantidad_disponible as total_bobinas_disponibles,  -- ✅ NOMBRE CORRECTO  
                cantidad_paquetes as total_paquetes_disponibles,   -- ✅ NOMBRE CORRECTO
                0 as peso_promedio
            FROM stock_agregado 
            WHERE nombre_producto = :nombre_producto 
                AND cantidad_disponible > 0
                AND cantidad_paquetes > 0
            ORDER BY cantidad_disponible DESC
            LIMIT 1";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
                $stmt->execute();
                $producto = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($producto) {
                    $resultados[] = $producto;
                    error_log("SUCCESS - obtenerStockGeneral CORREGIDO: {$producto['nombre_producto']} | Paquetes: {$producto['total_paquetes_disponibles']} | Bobinas: {$producto['total_bobinas_disponibles']}");
                } else {
                    error_log("WARNING - Producto no encontrado: '$nombreProducto'");
                }
            }

            error_log("DEBUG - obtenerStockGeneral CORREGIDO: Total resultados: " . count($resultados));

            return $resultados;
        } catch (Exception $e) {
            error_log("ERROR - obtenerStockGeneral: " . $e->getMessage());
            return [];
        }
    }


    public function obtenerReservasVenta($idVenta)
    {
        try {
            $sql = "SELECT 
            r.id,
            r.cantidad_reservada,
            r.fecha_reserva,
            r.observaciones,
            r.estado,
            sa.nombre_producto,
            sa.bobinas_pacote,
            sa.tipo_producto,
            (r.cantidad_reservada * sa.bobinas_pacote) as bobinas_totales  -- NUEVO: cálculo de bobinas totales
        FROM reservas_stock r
        JOIN stock_agregado sa ON r.id_stock_agregado = sa.id
        WHERE r.id_venta = :id_venta 
        ORDER BY r.fecha_reserva ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo reservas de venta: " . $e->getMessage());
            return [];
        }
    }

    /**
     * MÉTODO ORIGINAL: Mantener compatibilidad
     */
    public function cancelarReserva($idReserva)
    {
        try {
            $sql = "SELECT * FROM cancelar_reserva(:id_reserva)";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_reserva', $idReserva, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'exito' => $resultado['exito'] ?? false,
                'mensaje' => $resultado['mensaje'] ?? 'Error al cancelar reserva'
            ];
        } catch (Exception $e) {
            error_log("Error cancelando reserva: " . $e->getMessage());
            return [
                'exito' => false,
                'mensaje' => 'Error interno al cancelar reserva'
            ];
        }
    }


    public function crearReservaStockMejorado($nombreProducto, $bobinasSolicitadas, $idVenta, $cliente, $usuario = 'SISTEMA')
    {
        try {
            error_log("=== INICIANDO crearReservaStockMejorado CORREGIDO ===");
            error_log("Parámetros: producto='$nombreProducto', bobinas=$bobinasSolicitadas, venta=$idVenta");

            // Validaciones básicas
            if (empty($nombreProducto) || $bobinasSolicitadas <= 0 || empty($idVenta)) {
                return ['exito' => false, 'mensaje' => 'Parámetros inválidos'];
            }

            // Iniciar transacción
            $this->conexion->beginTransaction();

            // 1. Buscar el producto en stock_agregado
            $sqlStock = "SELECT id, bobinas_pacote, cantidad_disponible, cantidad_paquetes, tipo_producto 
                     FROM stock_agregado 
                     WHERE nombre_producto = :nombre_producto 
                       AND cantidad_disponible > 0 
                       AND cantidad_paquetes > 0 
                     ORDER BY cantidad_disponible DESC 
                     LIMIT 1";

            $stmtStock = $this->conexion->prepare($sqlStock);
            $stmtStock->bindParam(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
            $stmtStock->execute();
            $stockInfo = $stmtStock->fetch(PDO::FETCH_ASSOC);

            if (!$stockInfo) {
                $this->conexion->rollBack();
                return ['exito' => false, 'mensaje' => 'Producto no encontrado o sin stock disponible'];
            }

            $idStockAgregado = $stockInfo['id'];
            $bobinasPorPaquete = (int)$stockInfo['bobinas_pacote'];
            $bobinasDisponibles = (int)$stockInfo['cantidad_disponible'];
            $paquetesDisponibles = (int)$stockInfo['cantidad_paquetes'];

            // 2. Calcular paquetes necesarios
            $paquetesNecesarios = ceil($bobinasSolicitadas / $bobinasPorPaquete);
            $bobinasTotalesReservadas = $paquetesNecesarios * $bobinasPorPaquete;

            error_log("Cálculos: paquetes_necesarios=$paquetesNecesarios, bobinas_totales=$bobinasTotalesReservadas");

            // 3. Verificar disponibilidad
            if ($paquetesNecesarios > $paquetesDisponibles) {
                $this->conexion->rollBack();
                return [
                    'exito' => false,
                    'mensaje' => "Stock insuficiente. Necesita $paquetesNecesarios paquetes, disponibles: $paquetesDisponibles"
                ];
            }

            if ($bobinasTotalesReservadas > $bobinasDisponibles) {
                $this->conexion->rollBack();
                return [
                    'exito' => false,
                    'mensaje' => "Bobinas insuficientes. Necesita $bobinasTotalesReservadas bobinas, disponibles: $bobinasDisponibles"
                ];
            }

            // 4. Crear la reserva
            $sqlReserva = "INSERT INTO reservas_stock 
                       (id_stock_agregado, id_venta, cantidad_reservada, cliente, usuario, fecha_reserva, observaciones, estado)
                       VALUES (:id_stock, :id_venta, :cantidad, :cliente, :usuario, CURRENT_TIMESTAMP, :obs, 'activa')
                       RETURNING id";

            $observaciones = "Reserva automática: $bobinasSolicitadas bobinas solicitadas → $paquetesNecesarios paquetes ($bobinasTotalesReservadas bobinas)";

            $stmtReserva = $this->conexion->prepare($sqlReserva);
            $stmtReserva->bindParam(':id_stock', $idStockAgregado, PDO::PARAM_INT);
            $stmtReserva->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmtReserva->bindParam(':cantidad', $paquetesNecesarios, PDO::PARAM_INT);
            $stmtReserva->bindParam(':cliente', $cliente, PDO::PARAM_STR);
            $stmtReserva->bindParam(':usuario', $usuario, PDO::PARAM_STR);
            $stmtReserva->bindParam(':obs', $observaciones, PDO::PARAM_STR);
            $stmtReserva->execute();

            $idReserva = $stmtReserva->fetchColumn();

            if (!$idReserva) {
                $this->conexion->rollBack();
                return ['exito' => false, 'mensaje' => 'Error al crear la reserva'];
            }

            // 5. Actualizar stock_agregado
            $sqlUpdate = "UPDATE stock_agregado 
                      SET cantidad_disponible = cantidad_disponible - :bobinas_reservadas,
                          cantidad_reservada = cantidad_reservada + :paquetes_reservados,
                          cantidad_paquetes = cantidad_paquetes - :paquetes_reservados,
                          fecha_actualizacion = CURRENT_TIMESTAMP
                      WHERE id = :id_stock";

            $stmtUpdate = $this->conexion->prepare($sqlUpdate);
            $stmtUpdate->bindParam(':bobinas_reservadas', $bobinasTotalesReservadas, PDO::PARAM_INT);
            $stmtUpdate->bindParam(':paquetes_reservados', $paquetesNecesarios, PDO::PARAM_INT);
            $stmtUpdate->bindParam(':id_stock', $idStockAgregado, PDO::PARAM_INT);

            if (!$stmtUpdate->execute()) {
                $this->conexion->rollBack();
                return ['exito' => false, 'mensaje' => 'Error al actualizar el stock'];
            }

            // 6. Confirmar transacción
            $this->conexion->commit();

            error_log("SUCCESS - Reserva creada: ID=$idReserva, paquetes=$paquetesNecesarios, bobinas=$bobinasTotalesReservadas");

            return [
                'exito' => true,
                'mensaje' => "Reserva creada exitosamente: $paquetesNecesarios paquetes ($bobinasTotalesReservadas bobinas)",
                'id_reserva' => $idReserva,
                'paquetes_reservados' => $paquetesNecesarios,
                'bobinas_reservadas' => $bobinasTotalesReservadas
            ];
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            error_log("ERROR PDO en crearReservaStockMejorado: " . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error de base de datos: ' . $e->getMessage()];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("ERROR Exception en crearReservaStockMejorado: " . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error interno: ' . $e->getMessage()];
        }
    }


    public function cancelarReservaMejorada($idReserva, $motivo = 'Cancelación solicitada', $usuario = 'SISTEMA')
    {
        try {
            $sql = "SELECT * FROM cancelar_reserva_mejorada(:id_reserva, :motivo, :usuario)";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_reserva', $idReserva, PDO::PARAM_INT);
            $stmt->bindParam(':motivo', $motivo, PDO::PARAM_STR);
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'exito' => $resultado['exito'] ?? false,
                'mensaje' => $resultado['mensaje'] ?? 'Error al cancelar reserva',
                'paquetes_liberados' => $resultado['paquetes_liberados'] ?? 0,
                'bobinas_liberadas' => $resultado['bobinas_liberadas'] ?? 0
            ];
        } catch (Exception $e) {
            error_log("Error cancelando reserva mejorada: " . $e->getMessage());
            return [
                'exito' => false,
                'mensaje' => 'Error interno al cancelar reserva: ' . $e->getMessage()
            ];
        }
    }

    public function obtenerResumenStockAgregado($filtros = [])
    {
        try {
            $whereConditions = [];
            $params = [];

            if (!empty($filtros['nombre_producto'])) {
                $whereConditions[] = "sa.nombre_producto ILIKE :nombre_producto";
                $params[':nombre_producto'] = '%' . $filtros['nombre_producto'] . '%';
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            $sql = "SELECT 
            sa.nombre_producto,
            sa.bobinas_pacote,
            sa.tipo_producto,
            sa.cantidad_total,
            sa.cantidad_disponible,
            sa.cantidad_reservada,
            sa.cantidad_despachada,
            sa.fecha_actualizacion,
            sa.cantidad_paquetes,
            (sa.cantidad_disponible * sa.bobinas_pacote) as bobinas_disponibles,  -- NUEVO
            (sa.cantidad_reservada * sa.bobinas_pacote) as bobinas_reservadas,   -- NUEVO
            (sa.cantidad_despachada * sa.bobinas_pacote) as bobinas_despachadas, -- NUEVO
            COUNT(sps.id) as productos_fisicos_reales
        FROM stock_agregado sa
        LEFT JOIN sist_prod_stock sps ON (
            sps.nombre_producto = sa.nombre_producto 
            AND sps.bobinas_pacote = sa.bobinas_pacote
            AND sps.estado = 'en stock'
            AND COALESCE(sps.tipo_producto, '') = COALESCE(sa.tipo_producto, '')
        )
        $whereClause
        GROUP BY sa.id, sa.nombre_producto, sa.bobinas_pacote, sa.tipo_producto,
                 sa.cantidad_total, sa.cantidad_disponible, sa.cantidad_reservada, 
                 sa.cantidad_despachada, sa.fecha_actualizacion, sa.cantidad_paquetes
        ORDER BY sa.nombre_producto, sa.bobinas_pacote";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo resumen stock agregado: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerItemsEnviadosExpedicion($idVenta)
    {
        try {
            $sql = "SELECT 
                pp.id as id_producto_venta,
                pp.tipoproducto,
                pp.cantidad as cantidad_original,
                pp.id_producto,
                COALESCE(exp.cantidad_expedicion, 0) as cantidad_expedicion,
                COALESCE(exp.cantidad_stock, 0) as cantidad_desde_stock
            FROM public.sist_ventas_pres_product pp
            LEFT JOIN (
                SELECT 
                    id_producto,
                    SUM(CASE WHEN COALESCE(origen, '') != 'stock_general' THEN cantidad ELSE 0 END) as cantidad_expedicion,
                    SUM(CASE WHEN origen = 'stock_general' THEN cantidad ELSE 0 END) as cantidad_stock
                FROM public.sist_ventas_productos_expedicion
                WHERE id_venta = :id_venta
                GROUP BY id_producto
            ) exp ON pp.id = exp.id_producto
            WHERE pp.id_presupuesto = :id_presupuesto";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->bindParam(':id_presupuesto', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo items enviados a expedición: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerCantidadEfectivaProducto($idProducto)
    {
        try {
            $sql = "SELECT cantidad FROM public.sist_ventas_productos WHERE id = :id_producto";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return $resultado ? (float)$resultado['cantidad'] : 1;
        } catch (Exception $e) {
            error_log("Error obteniendo cantidad efectiva producto: " . $e->getMessage());
            return 1;
        }
    }

    public function obtenerCantidadTotalEnviada($idVenta, $idProducto)
    {
        try {
            $sqlExpedicion = "SELECT 
                COALESCE(SUM(CASE WHEN origen = 'stock_general' THEN cantidad ELSE 0 END), 0) as cantidad_expedicion,
                COALESCE(SUM(CASE WHEN COALESCE(origen, '') != 'stock_general' THEN cantidad ELSE 0 END), 0) as cantidad_produccion
                FROM public.sist_ventas_productos_expedicion 
                WHERE id_venta = :id_venta AND id_producto = :id_producto";

            $stmt = $this->conexion->prepare($sqlExpedicion);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->execute();
            $resultadoExpedicion = $stmt->fetch(PDO::FETCH_ASSOC);

            $cantidadExpedicion = $resultadoExpedicion ? (float)$resultadoExpedicion['cantidad_expedicion'] : 0;
            $cantidadProduccionExp = $resultadoExpedicion ? (float)$resultadoExpedicion['cantidad_produccion'] : 0;

            $sqlProduccion = "SELECT COALESCE(SUM(cantidad), 0) as cantidad_produccion
                             FROM public.sist_ventas_productos_produccion 
                             WHERE id_venta = :id_venta AND id_producto = :id_producto";
            $stmt = $this->conexion->prepare($sqlProduccion);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->execute();
            $resultadoProduccion = $stmt->fetch(PDO::FETCH_ASSOC);

            $cantidadProduccion = $resultadoProduccion ? (float)$resultadoProduccion['cantidad_produccion'] : 0;

            return $cantidadExpedicion + $cantidadProduccionExp + $cantidadProduccion;
        } catch (Exception $e) {
            error_log("Error obteniendo cantidad total enviada: " . $e->getMessage());
            return 0;
        }
    }

    public function verificarProductosEnProduccion($idVenta)
    {
        try {
            $sql = "SELECT COUNT(*) as productos_en_produccion 
                    FROM public.sist_ventas_productos_produccion 
                    WHERE id_venta = :id_venta";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return ($resultado['productos_en_produccion'] > 0);
        } catch (Exception $e) {
            error_log("Error verificando productos en producción: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerInfoVentaParaExpedicion($idVenta)
    {
        try {
            $sql = "SELECT cliente FROM public.sist_ventas_presupuesto WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo info venta para expedición: " . $e->getMessage());
            return false;
        }
    }

    public function actualizarEstadoVenta($idVenta, $estado)
    {
        try {
            $sql = "UPDATE public.sist_ventas_presupuesto SET estado = :estado WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
            $stmt->bindParam(':id', $idVenta, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando estado de venta: " . $e->getMessage());
            return false;
        }
    }

    public function actualizarEstadoAutorizacion($idVenta, $estado)
    {
        try {
            $sql = "UPDATE public.sist_ventas_autorizaciones 
                    SET estado_autorizacion = :estado
                    WHERE id_venta = :id_venta";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando estado de autorización: " . $e->getMessage());
            return false;
        }
    }

    public function insertarProcesoPcp($datos)
    {
        try {
            $sql = "INSERT INTO public.sist_ventas_proceso_pcp 
                    (id_venta, id_usuario_pcp, fecha_procesamiento, observaciones, estado) 
                    VALUES (:id_venta, :id_usuario, :fecha, :observaciones, :estado)";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $datos['id_venta'], PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario', $datos['id_usuario'], PDO::PARAM_INT);
            $stmt->bindParam(':fecha', $datos['fecha_procesamiento'], PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $datos['observaciones'], PDO::PARAM_STR);
            $stmt->bindParam(':estado', $datos['estado'], PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error insertando proceso PCP: " . $e->getMessage());
            return false;
        }
    }

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
            error_log("Error insertando historial de acción: " . $e->getMessage());
            return false;
        }
    }

    public function crearTablaProduccion()
    {
        try {
            $sqlCheck = "SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = 'sist_ventas_productos_produccion'
            )";
            $tableExists = $this->conexion->query($sqlCheck)->fetchColumn();

            if (!$tableExists) {
                $sqlCreate = "CREATE TABLE public.sist_ventas_productos_produccion (
                    id SERIAL PRIMARY KEY,
                    id_venta INTEGER NOT NULL,
                    id_producto INTEGER NOT NULL,
                    id_usuario_pcp INTEGER NOT NULL,
                    fecha_asignacion TIMESTAMP NOT NULL,
                    destino VARCHAR(50) NOT NULL,
                    cantidad DECIMAL(10,2) NOT NULL DEFAULT 0,
                    observaciones TEXT,
                    estado VARCHAR(50) DEFAULT 'Enviado',
                    origen VARCHAR(50) DEFAULT 'PCP',
                    FOREIGN KEY (id_venta) REFERENCES public.sist_ventas_presupuesto(id),
                    FOREIGN KEY (id_producto) REFERENCES public.sist_ventas_pres_product(id),
                    FOREIGN KEY (id_usuario_pcp) REFERENCES public.sist_ventas_usuario(id)
                )";
                $this->conexion->exec($sqlCreate);
            }
            return true;
        } catch (Exception $e) {
            error_log("Error creando tabla producción: " . $e->getMessage());
            return false;
        }
    }

    public function insertarProductoProduccion($datos)
    {
        try {
            $sql = "INSERT INTO public.sist_ventas_productos_produccion 
                    (id_venta, id_producto, id_usuario_pcp, fecha_asignacion, destino, cantidad, observaciones) 
                    VALUES (:id_venta, :id_producto, :id_usuario, :fecha, :destino, :cantidad, :observaciones)";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $datos['id_venta'], PDO::PARAM_INT);
            $stmt->bindParam(':id_producto', $datos['id_producto'], PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario', $datos['id_usuario'], PDO::PARAM_INT);
            $stmt->bindParam(':fecha', $datos['fecha_asignacion'], PDO::PARAM_STR);
            $stmt->bindParam(':destino', $datos['destino'], PDO::PARAM_STR);
            $stmt->bindParam(':cantidad', $datos['cantidad'], PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $datos['observaciones'], PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error insertando producto en producción: " . $e->getMessage());
            return false;
        }
    }

    public function insertarProductoExpedicion($datos)
    {
        try {
            // ✅ AGREGAR campo cantidad_bobinas al INSERT
            $sql = "INSERT INTO sist_ventas_productos_expedicion 
            (id_venta, id_producto, id_usuario_pcp, fecha_asignacion, cantidad, cantidad_bobinas, observaciones, origen, movimiento) 
            VALUES (:id_venta, :id_producto, :id_usuario, :fecha, :cantidad, :cantidad_bobinas, :observaciones, :origen, 'PENDIENTE')";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $datos['id_venta'], PDO::PARAM_INT);
            $stmt->bindParam(':id_producto', $datos['id_producto'], PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario', $datos['id_usuario'], PDO::PARAM_INT);
            $stmt->bindParam(':fecha', $datos['fecha_asignacion'], PDO::PARAM_STR);
            $stmt->bindParam(':cantidad', $datos['cantidad'], PDO::PARAM_STR);
            $stmt->bindParam(':cantidad_bobinas', $datos['cantidad_bobinas'], PDO::PARAM_INT);
            $stmt->bindParam(':observaciones', $datos['observaciones'], PDO::PARAM_STR);
            $stmt->bindParam(':origen', $datos['origen'], PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error insertando expedición: " . $e->getMessage());
            return false;
        }
    }

    public function getConexion()
    {
        return $this->conexion;
    }
}
