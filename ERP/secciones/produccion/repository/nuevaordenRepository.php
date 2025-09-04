<?php

/**
 * Repository para operaciones de base de datos de órdenes de producción - CON CANTIDAD DIRECTA COMO BOBINAS
 */
class NuevaOrdenRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    /**
     * Obtener unidades de medida de la BD
     */
    public function obtenerUnidadesMedida($id_producto = null)
    {
        try {
            if ($id_producto) {
                $sql = 'SELECT DISTINCT um.id, um."desc" as descripcion 
                        FROM public.sist_ventas_um um 
                        WHERE um.id_producto = :id_producto 
                        ORDER BY um."desc"';
                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
            } else {
                $sql = 'SELECT DISTINCT um.id, um."desc" as descripcion 
                        FROM public.sist_ventas_um um 
                        WHERE um."desc" IS NOT NULL AND um."desc" != \'\' 
                        ORDER BY um."desc"';
                $stmt = $this->conexion->prepare($sql);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo unidades de medida: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener órdenes de producción con paginación y filtro - ACTUALIZADO PARA PAÑOS Y LAMINADORA
     */
    public function obtenerOrdenesProduccion($pagina = 1, $limite = 10, $filtroOrden = null)
    {
        try {
            $offset = ($pagina - 1) * $limite;

            $whereClauses = [];
            $params = [];

            if (!empty($filtroOrden)) {
                $whereClauses[] = "op.id = :filtroOrden";
                $params[':filtroOrden'] = (int)$filtroOrden;
            }

            $whereSQL = count($whereClauses)
                ? "WHERE " . implode(" AND ", $whereClauses)
                : "";

            // Contar total con filtro
            $sqlCount = "SELECT COUNT(*) as total
                         FROM public.sist_ventas_orden_produccion op
                         $whereSQL";
            $stmtCount = $this->conexion->prepare($sqlCount);
            foreach ($params as $key => $val) {
                $stmtCount->bindValue($key, $val, PDO::PARAM_INT);
            }
            $stmtCount->execute();
            $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

            // ⭐ CORREGIDO: LAMINADORA usa la misma tabla que TNT ⭐
            $sql = "SELECT 
                        op.id,
                        op.fecha_orden,
                        op.estado,
                        op.observaciones,
                        op.cliente,
                        CASE 
                            WHEN tnt.id IS NOT NULL THEN 
                                CASE 
                                    WHEN UPPER(tnt.nombre) LIKE '%LAMINAD%' THEN 'LAMINADORA'
                                    ELSE 'TNT'
                                END
                            WHEN sp.id IS NOT NULL THEN 'SPUNLACE'
                            WHEN toal.id IS NOT NULL THEN 'TOALLITAS'
                            WHEN panos.id IS NOT NULL THEN 'PAÑOS'
                            ELSE 'DESCONOCIDO'
                        END as tipo_producto,
                        COALESCE(tnt.nombre, sp.nombre, toal.nombre, panos.nombre) as nombre_producto,
                        COALESCE(tnt.cantidad_total, sp.cantidad_total, toal.cantidad_total, panos.cantidad_total) as cantidad_total
                    FROM public.sist_ventas_orden_produccion op
                    LEFT JOIN public.sist_ventas_op_tnt tnt ON op.id = tnt.id_orden_produccion
                    LEFT JOIN public.sist_ventas_op_spunlace sp ON op.id = sp.id_orden_produccion
                    LEFT JOIN public.sist_ventas_op_toallitas toal ON op.id = toal.id_orden_produccion
                    LEFT JOIN public.sist_ventas_op_panos panos ON op.id = panos.id_orden_produccion
                    $whereSQL
                    ORDER BY op.fecha_orden DESC, op.id DESC
                    LIMIT :limite OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            }
            $stmt->execute();

            $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'ordenes'      => $ordenes,
                'total'        => $total,
                'paginas'      => ceil($total / $limite),
                'pagina_actual' => $pagina
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo órdenes: " . $e->getMessage());
            return [
                'ordenes'       => [],
                'total'         => 0,
                'paginas'       => 0,
                'pagina_actual' => 1
            ];
        }
    }

    /**
     * Buscar producto en base de datos
     */
    public function buscarProductoEnBD($descripcion)
    {
        try {
            // Buscar por descripción exacta primero
            $sql = "SELECT id, descripcion, tipo, cantidad as stock_actual 
                    FROM public.sist_ventas_productos 
                    WHERE LOWER(descripcion) = LOWER(:descripcion)
                    LIMIT 1";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
            $stmt->execute();
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($producto) {
                error_log("PRODUCTO ENCONTRADO - ID: {$producto['id']}, Tipo: {$producto['tipo']}");
                return $producto;
            }

            // Si no encuentra exacto, buscar por coincidencia parcial
            $sql = "SELECT id, descripcion, tipo, cantidad as stock_actual 
                    FROM public.sist_ventas_productos 
                    WHERE LOWER(descripcion) LIKE LOWER(:descripcion)
                    ORDER BY 
                        CASE 
                            WHEN LOWER(descripcion) LIKE LOWER(:descripcion_start) THEN 1
                            WHEN LOWER(descripcion) LIKE LOWER(:descripcion_words) THEN 2
                            ELSE 3
                        END
                    LIMIT 1";

            $stmt = $this->conexion->prepare($sql);
            $descripcionLike = '%' . $descripcion . '%';
            $descripcionStart = $descripcion . '%';
            $descripcionWords = '%' . str_replace(' ', '%', $descripcion) . '%';

            $stmt->bindParam(':descripcion', $descripcionLike, PDO::PARAM_STR);
            $stmt->bindParam(':descripcion_start', $descripcionStart, PDO::PARAM_STR);
            $stmt->bindParam(':descripcion_words', $descripcionWords, PDO::PARAM_STR);
            $stmt->execute();

            $producto = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($producto) {
                error_log("PRODUCTO ENCONTRADO (parcial) - ID: {$producto['id']}, Descripción: {$producto['descripcion']}");
            }

            return $producto;
        } catch (Exception $e) {
            error_log("Error buscando producto: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener sugerencias de productos
     */
    public function obtenerSugerenciasProductos($termino, $limite = 10)
    {
        try {
            $sql = "SELECT id, descripcion, tipo, cantidad as stock_actual 
                    FROM public.sist_ventas_productos 
                    WHERE LOWER(descripcion) LIKE LOWER(:termino)
                    ORDER BY 
                        CASE 
                            WHEN LOWER(descripcion) LIKE LOWER(:termino_start) THEN 1
                            ELSE 2
                        END,
                        descripcion
                    LIMIT :limite";

            $stmt = $this->conexion->prepare($sql);
            $terminoLike = '%' . $termino . '%';
            $terminoStart = $termino . '%';

            $stmt->bindParam(':termino', $terminoLike, PDO::PARAM_STR);
            $stmt->bindParam(':termino_start', $terminoStart, PDO::PARAM_STR);
            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo sugerencias: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Crear orden de producción principal
     */
    public function crearOrdenProduccionPrincipal($fechaProcesamiento, $observaciones, $cliente)
    {
        $sql = "INSERT INTO public.sist_ventas_orden_produccion 
                (fecha_orden, estado, observaciones, cliente) 
                VALUES (:fecha, 'Orden Emitida', :observaciones, :cliente)
                RETURNING id";

        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':fecha', $fechaProcesamiento, PDO::PARAM_STR);
        $stmt->bindParam(':observaciones', $observaciones, PDO::PARAM_STR);
        $stmt->bindParam(':cliente', $cliente, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC)['id'];
    }

    /**
     * Insertar producto TNT
     */
    public function insertarProductoTNT($idOrdenProduccion, $datos)
    {
        $sql = "INSERT INTO public.sist_ventas_op_tnt 
                (id_orden_produccion, gramatura, largura_metros, longitud_bobina, color, 
                 peso_bobina, cantidad_total, total_bobinas, pesominbobina, nombre) 
                VALUES (:id_orden_produccion, :gramatura, :largura, :longitud, :color, 
                        :peso_bobina, :cantidad_total, :total_bobinas, :peso_min_bobina, :nombre)";

        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':id_orden_produccion', $idOrdenProduccion, PDO::PARAM_INT);
        $stmt->bindParam(':gramatura', $datos['gramatura'], PDO::PARAM_STR);
        $stmt->bindParam(':largura', $datos['largura'], PDO::PARAM_STR);
        $stmt->bindParam(':longitud', $datos['longitud'], PDO::PARAM_INT);
        $stmt->bindParam(':color', $datos['color'], PDO::PARAM_STR);
        $stmt->bindParam(':peso_bobina', $datos['peso_bobina'], PDO::PARAM_STR);
        $stmt->bindParam(':cantidad_total', $datos['cantidad_total'], PDO::PARAM_STR);
        $stmt->bindParam(':total_bobinas', $datos['total_bobinas'], PDO::PARAM_INT);
        $stmt->bindParam(':peso_min_bobina', $datos['peso_min_bobina'], PDO::PARAM_STR);
        $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Insertar producto Spunlace
     */
    public function insertarProductoSpunlace($idOrdenProduccion, $datos)
    {
        $sql = "INSERT INTO public.sist_ventas_op_spunlace 
                (id_orden_produccion, gramatura, largura_metros, longitud_bobina, color, 
                 peso_bobina, cantidad_total, total_bobinas, pesominbobina, acabado, nombre) 
                VALUES (:id_orden_produccion, :gramatura, :largura, :longitud, :color, 
                        :peso_bobina, :cantidad_total, :total_bobinas, :peso_min_bobina, :acabado, :nombre)";

        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':id_orden_produccion', $idOrdenProduccion, PDO::PARAM_INT);
        $stmt->bindParam(':gramatura', $datos['gramatura'], PDO::PARAM_STR);
        $stmt->bindParam(':largura', $datos['largura'], PDO::PARAM_STR);
        $stmt->bindParam(':longitud', $datos['longitud'], PDO::PARAM_INT);
        $stmt->bindParam(':color', $datos['color'], PDO::PARAM_STR);
        $stmt->bindParam(':peso_bobina', $datos['peso_bobina'], PDO::PARAM_STR);
        $stmt->bindParam(':cantidad_total', $datos['cantidad_total'], PDO::PARAM_STR);
        $stmt->bindParam(':total_bobinas', $datos['total_bobinas'], PDO::PARAM_INT);
        $stmt->bindParam(':peso_min_bobina', $datos['peso_min_bobina'], PDO::PARAM_STR);
        $stmt->bindParam(':acabado', $datos['acabado'], PDO::PARAM_STR);
        $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * ⭐ ELIMINADO: insertarProductoLaminadora() - LAMINADORA usa insertarProductoTNT() ⭐
     */

    /**
     * Insertar producto Toallitas
     */
    public function insertarProductoToallitas($idOrdenProduccion, $datos)
    {
        $sql = "INSERT INTO public.sist_ventas_op_toallitas 
                (id_orden_produccion, nombre, cantidad_total) 
                VALUES (:id_orden_produccion, :nombre, :cantidad_total)";

        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':id_orden_produccion', $idOrdenProduccion, PDO::PARAM_INT);
        $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);
        $stmt->bindParam(':cantidad_total', $datos['cantidad_total'], PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * ⭐ ACTUALIZADO: Insertar producto Paños - Versión mejorada con soporte para CAJAS ⭐
     */
    public function insertarProductoPanos($idOrdenProduccion, $datos)
    {
        error_log("=== INSERTANDO PAÑOS MEJORADO ===");
        error_log("Datos recibidos: " . json_encode($datos));

        try {
            // ✅ USAR LA ESTRUCTURA COMPLETA CON SOPORTE PARA CAJAS
            $sql = "INSERT INTO public.sist_ventas_op_panos 
            (id_orden_produccion, nombre, cantidad_total, color, largura, picotado, cant_panos, unidad, peso, gramatura) 
            VALUES (:id_orden_produccion, :nombre, :cantidad_total, :color, :largura, :picotado, :cant_panos, :unidad, :peso, :gramatura)";

            $stmt = $this->conexion->prepare($sql);

            // ✅ PARÁMETROS CORREGIDOS CON SOPORTE PARA CAJAS
            $stmt->bindParam(':id_orden_produccion', $idOrdenProduccion, PDO::PARAM_INT);
            $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);
            $stmt->bindParam(':cantidad_total', $datos['cantidad_total'], PDO::PARAM_STR);
            $stmt->bindParam(':color', $datos['color'], PDO::PARAM_STR);
            $stmt->bindParam(':largura', $datos['largura'], PDO::PARAM_INT);
            $stmt->bindParam(':picotado', $datos['picotado'], PDO::PARAM_INT);
            $stmt->bindParam(':cant_panos', $datos['cant_panos'], PDO::PARAM_INT);
            $stmt->bindParam(':unidad', $datos['unidad'], PDO::PARAM_STR); // ⭐ SOPORTA 'cajas', 'unidades', etc.
            $stmt->bindParam(':peso', $datos['peso'], PDO::PARAM_STR);
            $stmt->bindParam(':gramatura', $datos['gramatura'], PDO::PARAM_STR);

            $resultado = $stmt->execute();

            if (!$resultado) {
                error_log("ERROR SQL PAÑOS: " . implode(", ", $stmt->errorInfo()));
            } else {
                error_log("✅ INSERCIÓN PAÑOS EXITOSA CON SOPORTE CAJAS");
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("EXCEPCIÓN insertarProductoPanos: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ⭐ NUEVO: Buscar productos específicos de tipo Paños ⭐
     */
    public function buscarProductosPanos()
    {
        try {
            $sql = "SELECT id, descripcion, tipo, cantidad as stock_actual 
                    FROM public.sist_ventas_productos 
                    WHERE UPPER(tipo) IN ('PAÑOS', 'PANOS', 'PAÑO', 'PANO')
                    OR UPPER(descripcion) LIKE '%PAÑO%'
                    OR UPPER(descripcion) LIKE '%PANO%'
                    ORDER BY descripcion";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error buscando productos de paños: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ⭐ CORREGIDO: buscarProductosLaminadora() - LAMINADORA se identifica por tipo en productos ⭐
     */
    public function buscarProductosLaminadora()
    {
        try {
            $sql = "SELECT id, descripcion, tipo, cantidad as stock_actual 
                    FROM public.sist_ventas_productos 
                    WHERE UPPER(tipo) IN ('LAMINADORA', 'LAMINADO')
                    OR UPPER(descripcion) LIKE '%LAMINAD%'
                    ORDER BY descripcion";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error buscando productos de laminadora: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ⭐ NUEVO: Verificar tipos de productos en la BD ⭐
     */
    public function verificarTiposEnBD()
    {
        try {
            $sql = "SELECT DISTINCT tipo, COUNT(*) as cantidad 
                    FROM public.sist_ventas_productos 
                    WHERE tipo IS NOT NULL AND tipo != '' 
                    GROUP BY tipo 
                    ORDER BY cantidad DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error verificando tipos en BD: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ⭐ NUEVO: Obtener detalles específicos de una orden de paños ⭐
     */
    public function obtenerDetallesOrdenPanos($idOrdenProduccion)
    {
        try {
            $sql = "SELECT op.*, o.cliente, o.fecha_orden, o.observaciones as observaciones_orden
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
     * ⭐ NOTA: LAMINADORA usa la misma tabla que TNT (sist_ventas_op_tnt) ⭐
     * Por eso no hay métodos específicos de laminadora - se distingue por el nombre/tipo
     */

    /**
     * ⭐ NUEVO: Verificar estructura de tabla op_panos ⭐
     */
    public function verificarEstructuraTablaOpPanos()
    {
        try {
            $sql = "SELECT column_name, data_type, is_nullable
                    FROM information_schema.columns 
                    WHERE table_schema = 'public'
                    AND table_name = 'sist_ventas_op_panos'
                    ORDER BY ordinal_position";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error verificando estructura tabla op_panos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ⭐ NUEVO: Crear tabla de paños si no existe ⭐
     */
    public function crearTablaOpPanos()
    {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS public.sist_ventas_op_panos (
                id SERIAL PRIMARY KEY,
                id_orden_produccion INTEGER NOT NULL REFERENCES public.sist_ventas_orden_produccion(id),
                nombre VARCHAR(500) NOT NULL,
                gramatura DECIMAL(10,2),
                largura INTEGER,
                picotado INTEGER,
                color VARCHAR(50) DEFAULT 'Blanco',
                peso DECIMAL(10,2),
                cantidad_total DECIMAL(10,2) NOT NULL,
                cant_panos INTEGER,
                unidad VARCHAR(50) DEFAULT 'unidades',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";

            $stmt = $this->conexion->prepare($sql);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error creando tabla op_panos: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Iniciar transacción
     */
    public function beginTransaction()
    {
        return $this->conexion->beginTransaction();
    }

    /**
     * Confirmar transacción
     */
    public function commit()
    {
        return $this->conexion->commit();
    }

    /**
     * Revertir transacción
     */
    public function rollBack()
    {
        return $this->conexion->rollBack();
    }
}
