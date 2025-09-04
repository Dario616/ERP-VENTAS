<?php

class PesoEstimadoHistorialRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    /**
     * Registrar cambio en el peso estimado
     * @param array $datos
     * @return array
     */
    public function registrarCambio($datos)
    {
        try {
            $sql = "INSERT INTO public.sist_prod_peso_estimado_historial 
                    (materia_prima_id, peso_anterior, peso_nuevo, usuario_id, motivo, observaciones)
                    VALUES 
                    (:materia_prima_id, :peso_anterior, :peso_nuevo, :usuario_id, :motivo, :observaciones)
                    RETURNING id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':materia_prima_id', $datos['materia_prima_id'], PDO::PARAM_INT);
            $stmt->bindParam(':peso_anterior', $datos['peso_anterior'], PDO::PARAM_STR);
            $stmt->bindParam(':peso_nuevo', $datos['peso_nuevo'], PDO::PARAM_STR);
            $stmt->bindParam(':usuario_id', $datos['usuario_id'], PDO::PARAM_INT);
            $stmt->bindParam(':motivo', $datos['motivo'], PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $datos['observaciones'], PDO::PARAM_STR);

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'id' => $resultado['id'],
                'error' => null
            ];
        } catch (Exception $e) {
            error_log("💥 Error registrando cambio de peso estimado: " . $e->getMessage());
            return [
                'success' => false,
                'id' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener historial de cambios por materia prima
     * @param int $materiaPrimaId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function obtenerHistorialPorMateriaPrima($materiaPrimaId, $limit = 50, $offset = 0)
    {
        try {
            $sql = "SELECT h.id, h.materia_prima_id, h.peso_anterior, h.peso_nuevo, 
                           h.fecha_cambio,
                           TO_CHAR(h.fecha_cambio AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI:SS') as fecha_cambio_formateada,
                           h.usuario_id, h.motivo, h.observaciones,
                           mp.descripcion as materia_prima_descripcion
                    FROM public.sist_prod_peso_estimado_historial h
                    LEFT JOIN public.sist_prod_materia_prima mp ON h.materia_prima_id = mp.id
                    WHERE h.materia_prima_id = :materia_prima_id
                    ORDER BY h.fecha_cambio DESC 
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':materia_prima_id', $materiaPrimaId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo historial de peso estimado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener último cambio de peso estimado
     * @param int $materiaPrimaId
     * @return array|null
     */
    public function obtenerUltimoCambio($materiaPrimaId)
    {
        try {
            $sql = "SELECT h.id, h.peso_anterior, h.peso_nuevo, h.fecha_cambio,
                           TO_CHAR(h.fecha_cambio AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI:SS') as fecha_cambio_formateada,
                           h.motivo, h.observaciones
                    FROM public.sist_prod_peso_estimado_historial h
                    WHERE h.materia_prima_id = :materia_prima_id
                    ORDER BY h.fecha_cambio DESC 
                    LIMIT 1";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':materia_prima_id', $materiaPrimaId, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ?: null;
        } catch (Exception $e) {
            error_log("💥 Error obteniendo último cambio de peso estimado: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Contar total de cambios por materia prima
     * @param int $materiaPrimaId
     * @return int
     */
    public function contarCambiosPorMateriaPrima($materiaPrimaId)
    {
        try {
            $sql = "SELECT COUNT(*) as total 
                    FROM public.sist_prod_peso_estimado_historial 
                    WHERE materia_prima_id = :materia_prima_id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':materia_prima_id', $materiaPrimaId, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'];
        } catch (Exception $e) {
            error_log("💥 Error contando cambios de peso estimado: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener historial completo con paginación
     * @param int $limit
     * @param int $offset
     * @param array $filtros
     * @return array
     */
    public function obtenerHistorialCompleto($limit = 50, $offset = 0, $filtros = [])
    {
        try {
            $condiciones = ['1=1'];
            $parametros = [];

            // Filtro por materia prima
            if (!empty($filtros['materia_prima_id'])) {
                $condiciones[] = "h.materia_prima_id = :materia_prima_id";
                $parametros[':materia_prima_id'] = $filtros['materia_prima_id'];
            }

            // Filtro por rango de fechas
            if (!empty($filtros['fecha_desde'])) {
                $condiciones[] = "DATE(h.fecha_cambio) >= :fecha_desde";
                $parametros[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $condiciones[] = "DATE(h.fecha_cambio) <= :fecha_hasta";
                $parametros[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            $whereClause = implode(' AND ', $condiciones);

            $sql = "SELECT h.id, h.materia_prima_id, h.peso_anterior, h.peso_nuevo, 
                           h.fecha_cambio,
                           TO_CHAR(h.fecha_cambio AT TIME ZONE 'America/Asuncion', 'DD/MM/YYYY HH24:MI:SS') as fecha_cambio_formateada,
                           h.usuario_id, h.motivo, h.observaciones,
                           mp.descripcion as materia_prima_descripcion,
                           mp.tipo as materia_prima_tipo
                    FROM public.sist_prod_peso_estimado_historial h
                    LEFT JOIN public.sist_prod_materia_prima mp ON h.materia_prima_id = mp.id
                    WHERE $whereClause
                    ORDER BY h.fecha_cambio DESC 
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);

            // Bind parámetros de filtros
            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }

            // Bind parámetros de paginación
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("💥 Error obteniendo historial completo: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar total de registros en el historial
     * @param array $filtros
     * @return int
     */
    public function contarTotalHistorial($filtros = [])
    {
        try {
            $condiciones = ['1=1'];
            $parametros = [];

            // Aplicar mismos filtros que en obtenerHistorialCompleto
            if (!empty($filtros['materia_prima_id'])) {
                $condiciones[] = "h.materia_prima_id = :materia_prima_id";
                $parametros[':materia_prima_id'] = $filtros['materia_prima_id'];
            }

            if (!empty($filtros['fecha_desde'])) {
                $condiciones[] = "DATE(h.fecha_cambio) >= :fecha_desde";
                $parametros[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $condiciones[] = "DATE(h.fecha_cambio) <= :fecha_hasta";
                $parametros[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            $whereClause = implode(' AND ', $condiciones);

            $sql = "SELECT COUNT(*) as total 
                    FROM public.sist_prod_peso_estimado_historial h
                    LEFT JOIN public.sist_prod_materia_prima mp ON h.materia_prima_id = mp.id
                    WHERE $whereClause";

            $stmt = $this->conexion->prepare($sql);

            foreach ($parametros as $param => $valor) {
                $stmt->bindValue($param, $valor);
            }

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'];
        } catch (Exception $e) {
            error_log("💥 Error contando historial: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener estadísticas de cambios
     * @param int $materiaPrimaId
     * @return array
     */
    public function obtenerEstadisticasCambios($materiaPrimaId = null)
    {
        try {
            $whereClause = $materiaPrimaId ? "WHERE materia_prima_id = :materia_prima_id" : "";

            $sql = "SELECT 
                        COUNT(*) as total_cambios,
                        AVG(peso_nuevo - peso_anterior) as cambio_promedio,
                        MAX(peso_nuevo - peso_anterior) as mayor_aumento,
                        MIN(peso_nuevo - peso_anterior) as mayor_disminucion,
                        MAX(fecha_cambio) as ultimo_cambio
                    FROM public.sist_prod_peso_estimado_historial
                    $whereClause";

            $stmt = $this->conexion->prepare($sql);

            if ($materiaPrimaId) {
                $stmt->bindParam(':materia_prima_id', $materiaPrimaId, PDO::PARAM_INT);
            }

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total_cambios' => $resultado['total_cambios'] ?? 0,
                'cambio_promedio' => round($resultado['cambio_promedio'] ?? 0, 2),
                'mayor_aumento' => round($resultado['mayor_aumento'] ?? 0, 2),
                'mayor_disminucion' => round($resultado['mayor_disminucion'] ?? 0, 2),
                'ultimo_cambio' => $resultado['ultimo_cambio']
            ];
        } catch (Exception $e) {
            error_log("💥 Error obteniendo estadísticas de cambios: " . $e->getMessage());
            return [
                'total_cambios' => 0,
                'cambio_promedio' => 0,
                'mayor_aumento' => 0,
                'mayor_disminucion' => 0,
                'ultimo_cambio' => null
            ];
        }
    }
}
