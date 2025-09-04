<?php

/**
 * Repository para operaciones de base de datos de producción con filtros de horario
 * ✅ CORREGIDO: Sintaxis PostgreSQL consistente
 */
class ProduccionRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    /**
     * ✅ CORREGIDA: Obtener evolución de producción por período - Sintaxis PostgreSQL
     */
    public function obtenerEvolucionProduccion($filtros)
    {
        try {
            $whereConditions = $this->construirCondicionesWhere($filtros);
            $params = $this->construirParametros($filtros);

            // ✅ CORREGIDO: Usar sintaxis PostgreSQL consistente
            $campoAgrupacion = "fecha_hora_producida::DATE";
            $campoFecha = "fecha_hora_producida::DATE as fecha";

            // Si hay filtros de hora y es el mismo día, agrupar por hora
            if ($this->esFiltroPorHorario($filtros)) {
                $campoAgrupacion = "DATE_TRUNC('hour', fecha_hora_producida)";
                $campoFecha = "EXTRACT(HOUR FROM fecha_hora_producida)::INTEGER as hora, fecha_hora_producida::DATE as fecha";
            }

            $sql = "
                SELECT 
                    {$campoFecha},
                    SUM(bobinas_pacote) as cantidad_producida,
                    COUNT(*) as items_producidos,
                    SUM(peso_bruto) as peso_bruto_total,
                    SUM(peso_liquido) as peso_liquido_total
                FROM sist_prod_stock
                {$whereConditions}
                GROUP BY {$campoAgrupacion}
                ORDER BY {$campoAgrupacion}
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo evolución de producción: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ NUEVA FUNCIÓN: Verificar si es un filtro por horario
     */
    private function esFiltroPorHorario($filtros)
    {
        return !empty($filtros['hora_inicio']) &&
            !empty($filtros['hora_fin']) &&
            !empty($filtros['fecha_inicio']) &&
            !empty($filtros['fecha_fin']) &&
            $filtros['fecha_inicio'] === $filtros['fecha_fin'];
    }

    /**
     * Obtener top productos más producidos
     */
    public function obtenerTopProductos($filtros, $limite = 5)
    {
        try {
            $whereConditions = $this->construirCondicionesWhere($filtros);
            $params = $this->construirParametros($filtros);

            $sql = "
                SELECT 
                    nombre_producto,
                    tipo_producto,
                    SUM(bobinas_pacote) as cantidad_total,
                    COUNT(*) as items_producidos,
                    SUM(peso_bruto) as peso_bruto_total,
                    SUM(peso_liquido) as peso_liquido_total,
                    AVG(bobinas_pacote) as promedio_bobinas,
                    MIN(fecha_hora_producida) as primera_produccion,
                    MAX(fecha_hora_producida) as ultima_produccion
                FROM sist_prod_stock
                {$whereConditions}
                GROUP BY nombre_producto, tipo_producto
                ORDER BY cantidad_total DESC
                LIMIT :limite
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo top productos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener estadísticas generales de producción
     */
    public function obtenerEstadisticasGenerales($filtros)
    {
        try {
            $whereConditions = $this->construirCondicionesWhere($filtros);
            $params = $this->construirParametros($filtros);

            $sql = "
                SELECT 
                    COUNT(*) as total_items,
                    SUM(bobinas_pacote) as total_bobinas,
                    SUM(peso_bruto) as total_peso_bruto,
                    SUM(peso_liquido) as total_peso_liquido,
                    COUNT(DISTINCT nombre_producto) as productos_diferentes,
                    COUNT(DISTINCT tipo_producto) as tipos_diferentes,
                    COUNT(DISTINCT usuario) as operadores_diferentes,
                    AVG(bobinas_pacote) as promedio_bobinas,
                    AVG(peso_bruto) as promedio_peso_bruto,
                    AVG(peso_liquido) as promedio_peso_liquido,
                    MIN(fecha_hora_producida) as primera_produccion,
                    MAX(fecha_hora_producida) as ultima_produccion
                FROM sist_prod_stock
                {$whereConditions}
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }

            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas generales: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener estadísticas por estado
     */
    public function obtenerEstadisticasPorEstado($filtros)
    {
        try {
            $whereConditions = $this->construirCondicionesWhere($filtros);
            $params = $this->construirParametros($filtros);

            $sql = "
                SELECT 
                    estado,
                    COUNT(*) as cantidad_items,
                    SUM(bobinas_pacote) as total_bobinas,
                    SUM(peso_bruto) as total_peso_bruto,
                    SUM(peso_liquido) as total_peso_liquido,
                    AVG(bobinas_pacote) as promedio_bobinas
                FROM sist_prod_stock
                {$whereConditions}
                GROUP BY estado
                ORDER BY total_bobinas DESC
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas por estado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener estadísticas por operador
     */
    public function obtenerEstadisticasPorOperador($filtros)
    {
        try {
            $whereConditions = $this->construirCondicionesWhere($filtros);
            $params = $this->construirParametros($filtros);

            $sql = "
                SELECT 
                    usuario as operador,
                    COUNT(*) as cantidad_items,
                    SUM(bobinas_pacote) as total_bobinas,
                    SUM(peso_bruto) as total_peso_bruto,
                    SUM(peso_liquido) as total_peso_liquido,
                    AVG(bobinas_pacote) as promedio_bobinas,
                    COUNT(DISTINCT nombre_producto) as productos_diferentes
                FROM sist_prod_stock
                {$whereConditions}
                GROUP BY usuario
                ORDER BY total_bobinas DESC
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas por operador: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener tipos de producto únicos
     */
    public function obtenerTiposProducto()
    {
        try {
            $sql = "
                SELECT DISTINCT tipo_producto
                FROM sist_prod_stock 
                WHERE tipo_producto IS NOT NULL AND tipo_producto != ''
                ORDER BY tipo_producto
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo tipos de producto: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener operadores únicos
     */
    public function obtenerOperadores()
    {
        try {
            $sql = "
                SELECT DISTINCT usuario
                FROM sist_prod_stock 
                WHERE usuario IS NOT NULL AND usuario != ''
                ORDER BY usuario
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo operadores: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener estados únicos
     */
    public function obtenerEstados()
    {
        try {
            $sql = "
                SELECT DISTINCT estado
                FROM sist_prod_stock 
                WHERE estado IS NOT NULL AND estado != ''
                ORDER BY estado
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo estados: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar operadores para autocompletado
     */
    public function buscarOperadores($termino, $limite = 10)
    {
        try {
            $sql = "
                SELECT DISTINCT 
                    usuario,
                    COUNT(*) as cantidad_producciones,
                    SUM(bobinas_pacote) as total_bobinas
                FROM sist_prod_stock
                WHERE usuario ILIKE :termino
                GROUP BY usuario
                ORDER BY total_bobinas DESC, usuario
                LIMIT :limite
            ";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':termino', '%' . $termino . '%', PDO::PARAM_STR);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en autocompletado de operadores: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener datos para exportar
     */
    public function obtenerDatosParaExportar($filtros)
    {
        try {
            $whereConditions = $this->construirCondicionesWhere($filtros);
            $params = $this->construirParametros($filtros);

            $sql = "
                SELECT 
                    id,
                    fecha_hora_producida,
                    nombre_producto,
                    tipo_producto,
                    bobinas_pacote,
                    peso_bruto,
                    peso_liquido,
                    gramatura,
                    largura,
                    metragem,
                    estado,
                    usuario,
                    id_orden_produccion
                FROM sist_prod_stock
                {$whereConditions}
                ORDER BY fecha_hora_producida DESC
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo datos para exportar: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ CORREGIDA: Obtener producción por hora del día - Sintaxis PostgreSQL
     */
    public function obtenerProduccionPorHora($filtros)
    {
        try {
            $whereConditions = $this->construirCondicionesWhere($filtros);
            $params = $this->construirParametros($filtros);

            $sql = "
                SELECT 
                    EXTRACT(HOUR FROM fecha_hora_producida)::INTEGER as hora,
                    SUM(bobinas_pacote) as cantidad_producida,
                    COUNT(*) as items_producidos
                FROM sist_prod_stock
                {$whereConditions}
                GROUP BY EXTRACT(HOUR FROM fecha_hora_producida)
                ORDER BY hora
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo producción por hora: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ CORREGIDA: Construir condiciones WHERE dinámicamente - Sintaxis PostgreSQL
     */
    private function construirCondicionesWhere($filtros)
    {
        $conditions = ["1=1"]; // Condición base

        if (!empty($filtros['fecha_inicio'])) {
            $conditions[] = "fecha_hora_producida::DATE >= :fecha_inicio::DATE";
        }

        if (!empty($filtros['fecha_fin'])) {
            $conditions[] = "fecha_hora_producida::DATE <= :fecha_fin::DATE";
        }

        // ✅ CORREGIDO: Filtros de horario con sintaxis PostgreSQL
        if (!empty($filtros['hora_inicio']) && !empty($filtros['hora_fin'])) {
            // Solo aplicar si es el mismo día
            if (
                !empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin']) &&
                $filtros['fecha_inicio'] === $filtros['fecha_fin']
            ) {
                $conditions[] = "fecha_hora_producida::TIME >= :hora_inicio::TIME";
                $conditions[] = "fecha_hora_producida::TIME <= :hora_fin::TIME";
            }
        }

        if (!empty($filtros['operador'])) {
            $conditions[] = "usuario ILIKE :operador";
        }

        if (!empty($filtros['tipo_producto'])) {
            $conditions[] = "tipo_producto = :tipo_producto";
        }

        if (!empty($filtros['estado'])) {
            $conditions[] = "estado = :estado";
        }

        if (!empty($filtros['producto'])) {
            $conditions[] = "nombre_producto ILIKE :producto";
        }

        return "WHERE " . implode(" AND ", $conditions);
    }

    /**
     * Construir parámetros para la consulta - Sin cambios
     */
    private function construirParametros($filtros)
    {
        $params = [];

        if (!empty($filtros['fecha_inicio'])) {
            $params[':fecha_inicio'] = $filtros['fecha_inicio'];
        }

        if (!empty($filtros['fecha_fin'])) {
            $params[':fecha_fin'] = $filtros['fecha_fin'];
        }

        // Agregar parámetros de horario
        if (!empty($filtros['hora_inicio']) && !empty($filtros['hora_fin'])) {
            // Solo agregar si es el mismo día
            if (
                !empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin']) &&
                $filtros['fecha_inicio'] === $filtros['fecha_fin']
            ) {
                $params[':hora_inicio'] = $filtros['hora_inicio'];
                $params[':hora_fin'] = $filtros['hora_fin'];
            }
        }

        if (!empty($filtros['operador'])) {
            $params[':operador'] = '%' . $filtros['operador'] . '%';
        }

        if (!empty($filtros['tipo_producto'])) {
            $params[':tipo_producto'] = $filtros['tipo_producto'];
        }

        if (!empty($filtros['estado'])) {
            $params[':estado'] = $filtros['estado'];
        }

        if (!empty($filtros['producto'])) {
            $params[':producto'] = '%' . $filtros['producto'] . '%';
        }

        return $params;
    }

    /**
     * Registrar log de actividad
     */
    public function registrarLog($usuario, $ip, $accion, $detalles = null)
    {
        try {
            // Verificar si la tabla existe, si no crearla
            $this->crearTablaLogSiNoExiste();

            $sql = "INSERT INTO log_produccion_reportes 
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
            $sql = "CREATE TABLE IF NOT EXISTS log_produccion_reportes (
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
     * ✅ CORREGIDA: Obtener resumen de rendimiento por período - Sintaxis PostgreSQL
     */
    public function obtenerRendimientoPorPeriodo($filtros, $periodo = 'day')
    {
        try {
            $whereConditions = $this->construirCondicionesWhere($filtros);
            $params = $this->construirParametros($filtros);

            // Ajustar período según filtros de horario
            if ($this->esFiltroPorHorario($filtros)) {
                $periodo = 'hour';
            }

            $formatoFecha = match ($periodo) {
                'hour' => "DATE_TRUNC('hour', fecha_hora_producida)",
                'day' => "DATE_TRUNC('day', fecha_hora_producida)",
                'week' => "DATE_TRUNC('week', fecha_hora_producida)",
                'month' => "DATE_TRUNC('month', fecha_hora_producida)",
                default => "DATE_TRUNC('day', fecha_hora_producida)"
            };

            $sql = "
                SELECT 
                    {$formatoFecha} as periodo,
                    SUM(bobinas_pacote) as cantidad_producida,
                    COUNT(*) as items_producidos,
                    SUM(peso_bruto) as peso_bruto_total,
                    SUM(peso_liquido) as peso_liquido_total,
                    AVG(bobinas_pacote) as promedio_bobinas,
                    COUNT(DISTINCT usuario) as operadores_activos
                FROM sist_prod_stock
                {$whereConditions}
                GROUP BY {$formatoFecha}
                ORDER BY periodo
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo rendimiento por período: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener productos con paginación
     */
    public function obtenerProductosPaginados($filtros, $pagina = 1, $porPagina = 10)
    {
        try {
            $whereConditions = $this->construirCondicionesWhere($filtros);
            $params = $this->construirParametros($filtros);

            $offset = ($pagina - 1) * $porPagina;

            // Query para contar total
            $sqlCount = "SELECT COUNT(*) as total FROM sist_prod_stock {$whereConditions}";
            $stmtCount = $this->conexion->prepare($sqlCount);
            foreach ($params as $key => $val) {
                $stmtCount->bindValue($key, $val);
            }
            $stmtCount->execute();
            $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

            // Query para datos
            $sql = "
                SELECT 
                    id,
                    fecha_hora_producida,
                    nombre_producto,
                    tipo_producto,
                    bobinas_pacote,
                    peso_bruto,
                    peso_liquido,
                    estado,
                    usuario,
                    metragem
                FROM sist_prod_stock
                {$whereConditions}
                ORDER BY fecha_hora_producida DESC
                LIMIT :limite OFFSET :offset
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'datos' => $datos,
                'total' => $total,
                'pagina_actual' => $pagina,
                'por_pagina' => $porPagina,
                'total_paginas' => ceil($total / $porPagina)
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo productos paginados: " . $e->getMessage());
            return ['datos' => [], 'total' => 0, 'pagina_actual' => 1, 'por_pagina' => $porPagina, 'total_paginas' => 0];
        }
    }

    /**
     * ✅ CORREGIDA: Obtener estadísticas de producción por rango horario - Sintaxis PostgreSQL
     */
    public function obtenerEstadisticasPorRangoHorario($filtros)
    {
        try {
            $whereConditions = $this->construirCondicionesWhere($filtros);
            $params = $this->construirParametros($filtros);

            $sql = "
                SELECT 
                    CASE 
                        WHEN EXTRACT(HOUR FROM fecha_hora_producida)::INTEGER BETWEEN 6 AND 13 THEN 'Mañana (06:00-13:59)'
                        WHEN EXTRACT(HOUR FROM fecha_hora_producida)::INTEGER BETWEEN 14 AND 21 THEN 'Tarde (14:00-21:59)'
                        ELSE 'Noche (22:00-05:59)'
                    END as turno,
                    COUNT(*) as total_items,
                    SUM(bobinas_pacote) as total_bobinas,
                    SUM(peso_bruto) as peso_bruto_total,
                    SUM(peso_liquido) as peso_liquido_total,
                    AVG(bobinas_pacote) as promedio_bobinas,
                    COUNT(DISTINCT usuario) as operadores_diferentes
                FROM sist_prod_stock
                {$whereConditions}
                GROUP BY 
                    CASE 
                        WHEN EXTRACT(HOUR FROM fecha_hora_producida)::INTEGER BETWEEN 6 AND 13 THEN 'Mañana (06:00-13:59)'
                        WHEN EXTRACT(HOUR FROM fecha_hora_producida)::INTEGER BETWEEN 14 AND 21 THEN 'Tarde (14:00-21:59)'
                        ELSE 'Noche (22:00-05:59)'
                    END
                ORDER BY total_bobinas DESC
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas por rango horario: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ CORREGIDA: Obtener producción promedio por hora del día - Sintaxis PostgreSQL
     */
    public function obtenerPromedioProduccionPorHora($filtros, $diasAComparar = 30)
    {
        try {
            // Modificar filtros para incluir los últimos X días
            $filtrosExtendidos = $filtros;
            if (empty($filtrosExtendidos['fecha_inicio'])) {
                $filtrosExtendidos['fecha_inicio'] = date('Y-m-d', strtotime("-{$diasAComparar} days"));
            }

            $whereConditions = $this->construirCondicionesWhere($filtrosExtendidos);
            $params = $this->construirParametros($filtrosExtendidos);

            $sql = "
                SELECT 
                    EXTRACT(HOUR FROM fecha_hora_producida)::INTEGER as hora,
                    AVG(bobinas_pacote) as promedio_bobinas,
                    COUNT(DISTINCT fecha_hora_producida::DATE) as dias_con_produccion,
                    SUM(bobinas_pacote)::NUMERIC / COUNT(DISTINCT fecha_hora_producida::DATE) as bobinas_por_dia
                FROM sist_prod_stock
                {$whereConditions}
                GROUP BY EXTRACT(HOUR FROM fecha_hora_producida)
                HAVING COUNT(DISTINCT fecha_hora_producida::DATE) > 0
                ORDER BY hora
            ";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo promedio de producción por hora: " . $e->getMessage());
            return [];
        }
    }
}
