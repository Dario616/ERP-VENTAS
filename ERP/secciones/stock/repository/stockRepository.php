<?php

/**
 * Repository para operaciones de base de datos del stock agregado
 * Versión optimizada para PC/Notebooks con opción de stock completo
 */
class StockRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    /**
     * Obtener stock agregado paginado con filtros
     * @param string $filtroProducto Filtro por nombre de producto
     * @param string $filtroTipo Filtro por tipo de producto
     * @param int $limit Límite de registros
     * @param int $offset Offset para paginación
     * @param bool $stockCompleto Si true, muestra todos los productos, si false solo con stock disponible
     */
    public function obtenerStockAgregadoPaginado($filtroProducto = '', $filtroTipo = '', $limit = 10, $offset = 0, $stockCompleto = false)
    {
        try {
            $whereConditions = [];
            $params = [];

            // Solo agregar condición de stock disponible si NO es stock completo
            if (!$stockCompleto) {
                $whereConditions[] = "cantidad_disponible > 0";
            }

            if (!empty($filtroProducto)) {
                $whereConditions[] = "nombre_producto ILIKE :producto";
                $params[':producto'] = '%' . $filtroProducto . '%';
            }

            if (!empty($filtroTipo)) {
                $whereConditions[] = "tipo_producto = :tipo";
                $params[':tipo'] = $filtroTipo;
            }

            // Construir WHERE clause
            $whereClause = '';
            if (!empty($whereConditions)) {
                $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            }

            $sql = "
            SELECT 
                id,
                nombre_producto,
                bobinas_pacote,
                tipo_producto,
                gramatura,
                largura,
                metragem,
                cantidad_total,
                cantidad_disponible,
                cantidad_reservada,
                cantidad_despachada,
                cantidad_paquetes,
                fecha_actualizacion,
                CASE 
                    WHEN cantidad_disponible = 0 THEN 'sin_stock'
                    WHEN cantidad_disponible <= 2 THEN 'critico'
                    WHEN cantidad_disponible <= 5 THEN 'bajo'
                    ELSE 'normal'
                END as estado_stock
            FROM stock_agregado
            {$whereClause}
            ORDER BY nombre_producto, bobinas_pacote
            LIMIT :limit OFFSET :offset
        ";

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo stock agregado paginado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar total de productos en stock agregado
     * @param string $filtroProducto Filtro por nombre de producto
     * @param string $filtroTipo Filtro por tipo de producto
     * @param bool $stockCompleto Si true, cuenta todos los productos, si false solo con stock disponible
     */
    public function contarTotalStockAgregado($filtroProducto = '', $filtroTipo = '', $stockCompleto = false)
    {
        try {
            $whereConditions = [];
            $params = [];

            // Solo agregar condición de stock disponible si NO es stock completo
            if (!$stockCompleto) {
                $whereConditions[] = "cantidad_disponible > 0";
            }

            if (!empty($filtroProducto)) {
                $whereConditions[] = "nombre_producto ILIKE :producto";
                $params[':producto'] = '%' . $filtroProducto . '%';
            }

            if (!empty($filtroTipo)) {
                $whereConditions[] = "tipo_producto = :tipo";
                $params[':tipo'] = $filtroTipo;
            }

            // Construir WHERE clause
            $whereClause = '';
            if (!empty($whereConditions)) {
                $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            }

            $sql = "SELECT COUNT(*) FROM stock_agregado {$whereClause}";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_STR);
            }

            $stmt->execute();
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error contando total de stock agregado: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener estadísticas generales del stock agregado
     */
    public function obtenerEstadisticasStockAgregado()
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_productos,
                    COUNT(DISTINCT tipo_producto) as tipos_diferentes,
                    SUM(cantidad_total) as total_items,
                    SUM(cantidad_disponible) as disponible_total,
                    SUM(cantidad_reservada) as reservado_total,
                    SUM(cantidad_despachada) as despachado_total,
                    SUM(cantidad_paquetes) as total_paquetes,
                    COUNT(CASE WHEN cantidad_disponible = 0 THEN 1 END) as productos_sin_stock,
                    COUNT(CASE WHEN cantidad_disponible <= 2 AND cantidad_disponible > 0 THEN 1 END) as productos_criticos,
                    COUNT(CASE WHEN cantidad_disponible <= 5 AND cantidad_disponible > 2 THEN 1 END) as productos_bajo_stock,
                    AVG(cantidad_disponible) as promedio_disponible
                FROM stock_agregado
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas de stock agregado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener productos por tipo desde stock agregado
     */
    public function obtenerProductosPorTipoAgregado()
    {
        try {
            $sql = "
                SELECT 
                    tipo_producto,
                    COUNT(*) as total_variantes,
                    SUM(cantidad_total) as total_items,
                    SUM(cantidad_disponible) as disponible_total,
                    SUM(cantidad_reservada) as reservado_total,
                    SUM(cantidad_despachada) as despachado_total,
                    SUM(cantidad_paquetes) as total_paquetes,
                    AVG(cantidad_disponible) as promedio_disponible
                FROM stock_agregado
                WHERE tipo_producto IS NOT NULL
                GROUP BY tipo_producto
                ORDER BY disponible_total DESC
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos por tipo agregado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar productos para autocompletado en stock agregado
     */
    public function buscarProductosAutocompletadoAgregado($termino, $limite = 10)
    {
        try {
            $sql = "
                SELECT DISTINCT 
                    nombre_producto,
                    tipo_producto,
                    SUM(cantidad_disponible) as cantidad_disponible_total,
                    SUM(cantidad_paquetes) as cantidad_paquetes_total,
                    COUNT(*) as variantes
                FROM stock_agregado
                WHERE nombre_producto ILIKE :termino
                GROUP BY nombre_producto, tipo_producto
                ORDER BY cantidad_disponible_total DESC, nombre_producto
                LIMIT :limite
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':termino', '%' . $termino . '%', PDO::PARAM_STR);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en autocompletado de productos agregados: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener tipos de producto únicos desde stock agregado
     */
    public function obtenerTiposProductoAgregado()
    {
        try {
            $sql = "
                SELECT DISTINCT tipo_producto
                FROM stock_agregado 
                WHERE tipo_producto IS NOT NULL
                ORDER BY tipo_producto
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo tipos de producto agregados: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener productos con bajo stock desde stock agregado
     */
    public function obtenerProductosBajoStockAgregado($umbral = 5)
    {
        try {
            $sql = "
                SELECT 
                    id,
                    nombre_producto,
                    bobinas_pacote,
                    tipo_producto,
                    cantidad_disponible,
                    cantidad_total,
                    cantidad_reservada,
                    cantidad_paquetes
                FROM stock_agregado
                WHERE cantidad_disponible <= :umbral AND cantidad_disponible > 0
                ORDER BY cantidad_disponible ASC, nombre_producto
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':umbral', $umbral, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos con bajo stock agregado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener productos sin stock desde stock agregado
     */
    public function obtenerProductosSinStockAgregado()
    {
        try {
            $sql = "
                SELECT 
                    id,
                    nombre_producto,
                    bobinas_pacote,
                    tipo_producto,
                    cantidad_total,
                    cantidad_reservada,
                    cantidad_despachada,
                    cantidad_paquetes
                FROM stock_agregado
                WHERE cantidad_disponible = 0
                ORDER BY cantidad_total DESC, nombre_producto
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos sin stock agregado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener detalles de un producto específico desde stock agregado
     */
    public function obtenerDetallesProductoAgregado($nombreProducto, $bobinasPacote = null, $tipoProducto = null)
    {
        try {
            $whereConditions = ["nombre_producto = :nombre_producto"];
            $params = [':nombre_producto' => $nombreProducto];

            if ($bobinasPacote !== null) {
                $whereConditions[] = "bobinas_pacote = :bobinas_pacote";
                $params[':bobinas_pacote'] = $bobinasPacote;
            }

            if ($tipoProducto !== null) {
                $whereConditions[] = "tipo_producto = :tipo_producto";
                $params[':tipo_producto'] = $tipoProducto;
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $sql = "
                SELECT 
                    id,
                    nombre_producto,
                    bobinas_pacote,
                    tipo_producto,
                    gramatura,
                    largura,
                    metragem,
                    cantidad_total,
                    cantidad_disponible,
                    cantidad_reservada,
                    cantidad_despachada,
                    cantidad_paquetes,
                    fecha_actualizacion
                FROM stock_agregado
                {$whereClause}
                ORDER BY bobinas_pacote
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo detalles del producto agregado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener stock completo para exportación
     * @param string $filtroProducto Filtro por nombre de producto
     * @param string $filtroTipo Filtro por tipo de producto
     * @param bool $stockCompleto Si true, incluye todos los productos, si false solo con stock disponible
     */
    public function obtenerStockCompletoAgregado($filtroProducto = '', $filtroTipo = '', $stockCompleto = false)
    {
        try {
            $whereConditions = [];
            $params = [];

            // Solo agregar condición de stock disponible si NO es stock completo
            if (!$stockCompleto) {
                $whereConditions[] = "cantidad_disponible > 0";
            }

            if (!empty($filtroProducto)) {
                $whereConditions[] = "nombre_producto ILIKE :producto";
                $params[':producto'] = '%' . $filtroProducto . '%';
            }

            if (!empty($filtroTipo)) {
                $whereConditions[] = "tipo_producto = :tipo";
                $params[':tipo'] = $filtroTipo;
            }

            // Construir WHERE clause
            $whereClause = '';
            if (!empty($whereConditions)) {
                $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            }

            $sql = "
            SELECT 
                id,
                nombre_producto,
                bobinas_pacote,
                tipo_producto,
                gramatura,
                largura,
                metragem,
                cantidad_total,
                cantidad_disponible,
                cantidad_reservada,
                cantidad_despachada,
                cantidad_paquetes,
                fecha_actualizacion
            FROM stock_agregado
            {$whereClause}
            ORDER BY nombre_producto, bobinas_pacote
        ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_STR);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo stock completo agregado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Actualizar cantidad de un producto específico
     */
    public function actualizarCantidadProducto($id, $cantidadTotal, $cantidadDisponible, $cantidadReservada, $cantidadDespachada, $cantidadPaquetes)
    {
        try {
            $sql = "
                UPDATE stock_agregado 
                SET 
                    cantidad_total = :cantidad_total,
                    cantidad_disponible = :cantidad_disponible,
                    cantidad_reservada = :cantidad_reservada,
                    cantidad_despachada = :cantidad_despachada,
                    cantidad_paquetes = :cantidad_paquetes,
                    fecha_actualizacion = CURRENT_TIMESTAMP
                WHERE id = :id
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':cantidad_total', $cantidadTotal, PDO::PARAM_INT);
            $stmt->bindValue(':cantidad_disponible', $cantidadDisponible, PDO::PARAM_INT);
            $stmt->bindValue(':cantidad_reservada', $cantidadReservada, PDO::PARAM_INT);
            $stmt->bindValue(':cantidad_despachada', $cantidadDespachada, PDO::PARAM_INT);
            $stmt->bindValue(':cantidad_paquetes', $cantidadPaquetes, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando cantidad de producto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Insertar nuevo producto en stock agregado
     */
    public function insertarProducto($nombreProducto, $bobinasPacote, $tipoProducto, $gramatura = null, $largura = null, $metragem = null, $cantidadTotal = 0, $cantidadDisponible = 0, $cantidadPaquetes = 0)
    {
        try {
            $sql = "
                INSERT INTO stock_agregado 
                (nombre_producto, bobinas_pacote, tipo_producto, gramatura, largura, metragem, cantidad_total, cantidad_disponible, cantidad_paquetes, fecha_actualizacion)
                VALUES 
                (:nombre_producto, :bobinas_pacote, :tipo_producto, :gramatura, :largura, :metragem, :cantidad_total, :cantidad_disponible, :cantidad_paquetes, CURRENT_TIMESTAMP)
                RETURNING id
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
            $stmt->bindValue(':bobinas_pacote', $bobinasPacote, PDO::PARAM_INT);
            $stmt->bindValue(':tipo_producto', $tipoProducto, PDO::PARAM_STR);
            $stmt->bindValue(':gramatura', $gramatura, PDO::PARAM_INT);
            $stmt->bindValue(':largura', $largura, PDO::PARAM_STR);
            $stmt->bindValue(':metragem', $metragem, PDO::PARAM_INT);
            $stmt->bindValue(':cantidad_total', $cantidadTotal, PDO::PARAM_INT);
            $stmt->bindValue(':cantidad_disponible', $cantidadDisponible, PDO::PARAM_INT);
            $stmt->bindValue(':cantidad_paquetes', $cantidadPaquetes, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error insertando producto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Buscar producto específico
     */
    public function buscarProducto($nombreProducto, $bobinasPacote, $tipoProducto, $gramatura = null, $largura = null, $metragem = null)
    {
        try {
            $sql = "
                SELECT * FROM stock_agregado
                WHERE nombre_producto = :nombre_producto 
                AND bobinas_pacote = :bobinas_pacote 
                AND tipo_producto = :tipo_producto
                AND (:gramatura IS NULL OR gramatura = :gramatura)
                AND (:largura IS NULL OR largura = :largura)
                AND (:metragem IS NULL OR metragem = :metragem)
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
            $stmt->bindValue(':bobinas_pacote', $bobinasPacote, PDO::PARAM_INT);
            $stmt->bindValue(':tipo_producto', $tipoProducto, PDO::PARAM_STR);
            $stmt->bindValue(':gramatura', $gramatura, PDO::PARAM_INT);
            $stmt->bindValue(':largura', $largura, PDO::PARAM_STR);
            $stmt->bindValue(':metragem', $metragem, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error buscando producto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registrar log de actividad
     */
    public function registrarLog($usuario, $ip, $accion, $detalles = null)
    {
        try {
            // Verificar si la tabla existe, si no crearla
            $this->crearTablaLogSiNoExiste();

            $sql = "INSERT INTO log_stock_consultas 
                    (usuario, ip_address, accion, detalles, fecha_consulta) 
                    VALUES (:usuario, :ip, :accion, :detalles, NOW())";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':usuario', $usuario, PDO::PARAM_STR);
            $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
            $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
            $stmt->bindValue(':detalles', $detalles, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error registrando log en BD: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crear tabla de log si no existe
     */
    private function crearTablaLogSiNoExiste()
    {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS log_stock_consultas (
                id SERIAL PRIMARY KEY,
                usuario VARCHAR(100),
                ip_address VARCHAR(45),
                accion VARCHAR(100),
                detalles TEXT,
                fecha_consulta TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";

            $this->conexion->exec($sql);
        } catch (Exception $e) {
            error_log("Error creando tabla de log: " . $e->getMessage());
        }
    }

    /**
     * Verificar estado de la tabla stock_agregado
     */
    public function verificarTablaStockAgregado()
    {
        try {
            $sql = "SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = 'stock_agregado'
            )";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error verificando tabla stock_agregado: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener estructura de la tabla para debug
     */
    public function obtenerEstructuraTabla()
    {
        try {
            $sql = "
                SELECT column_name, data_type, is_nullable, column_default
                FROM information_schema.columns 
                WHERE table_name = 'stock_agregado' 
                AND table_schema = 'public'
                ORDER BY ordinal_position
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estructura de tabla: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener productos que necesitan reposición
     */
    public function obtenerProductosParaReposicion($umbralCritico = 2)
    {
        try {
            $sql = "
                SELECT 
                    id,
                    nombre_producto,
                    bobinas_pacote,
                    tipo_producto,
                    cantidad_disponible,
                    cantidad_paquetes,
                    CASE 
                        WHEN cantidad_disponible = 0 THEN 'sin_stock'
                        WHEN cantidad_disponible <= :umbral THEN 'critico'
                        ELSE 'normal'
                    END as prioridad
                FROM stock_agregado
                WHERE cantidad_disponible <= :umbral
                ORDER BY cantidad_disponible ASC, nombre_producto
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':umbral', $umbralCritico, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos para reposición: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener resumen por tipo de producto
     */
    public function obtenerResumenPorTipo()
    {
        try {
            $sql = "
                SELECT 
                    tipo_producto,
                    COUNT(*) as total_variantes,
                    SUM(cantidad_total) as suma_total,
                    SUM(cantidad_disponible) as suma_disponible,
                    SUM(cantidad_reservada) as suma_reservada,
                    SUM(cantidad_despachada) as suma_despachada,
                    SUM(cantidad_paquetes) as suma_paquetes,
                    AVG(cantidad_disponible) as promedio_disponible,
                    COUNT(CASE WHEN cantidad_disponible = 0 THEN 1 END) as productos_agotados
                FROM stock_agregado
                WHERE tipo_producto IS NOT NULL
                GROUP BY tipo_producto
                ORDER BY suma_disponible DESC
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo resumen por tipo: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener estadísticas de stock completo vs disponible
     */
    public function obtenerEstadisticasComparativas()
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_productos_sistema,
                    COUNT(CASE WHEN cantidad_disponible > 0 THEN 1 END) as productos_con_stock,
                    COUNT(CASE WHEN cantidad_disponible = 0 THEN 1 END) as productos_sin_stock,
                    SUM(cantidad_total) as suma_total_sistema,
                    SUM(cantidad_disponible) as suma_disponible_sistema,
                    SUM(cantidad_reservada) as suma_reservada_sistema,
                    SUM(cantidad_despachada) as suma_despachada_sistema,
                    ROUND(AVG(cantidad_disponible), 2) as promedio_disponible,
                    ROUND((COUNT(CASE WHEN cantidad_disponible > 0 THEN 1 END) * 100.0 / COUNT(*)), 2) as porcentaje_con_stock
                FROM stock_agregado
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas comparativas: " . $e->getMessage());
            return [];
        }
    }
}
