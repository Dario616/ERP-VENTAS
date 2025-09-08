<?php
// repository/TipoTransporteRepository.php

class TipoTransporteRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    public function crearTipo($datos)
    {
        try {
            $stmt = $this->conexion->prepare("INSERT INTO sist_prod_tipo_transporte (nombre) VALUES (?)");
            return $stmt->execute([$datos['nombre']]);
        } catch (PDOException $e) {
            throw new Exception("Error de base de datos: " . $e->getMessage());
        }
    }

    public function editarTipo($datos)
    {
        try {
            $stmt = $this->conexion->prepare("UPDATE sist_prod_tipo_transporte SET nombre = ? WHERE id = ?");
            return $stmt->execute([$datos['nombre'], $datos['id']]);
        } catch (PDOException $e) {
            throw new Exception("Error de base de datos: " . $e->getMessage());
        }
    }

    public function eliminarTipo($id)
    {
        try {
            $stmt = $this->conexion->prepare("DELETE FROM sist_prod_tipo_transporte WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            if ($e->getCode() == '23503') { // Foreign key violation
                throw new Exception("No se puede eliminar el tipo de transporte porque tiene registros asociados.");
            } else {
                throw new Exception("Error de base de datos: " . $e->getMessage());
            }
        }
    }

    public function obtenerTodosLosTipos()
    {
        try {
            $stmt = $this->conexion->query("SELECT id, nombre FROM sist_prod_tipo_transporte ORDER BY nombre");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener tipos de transporte: " . $e->getMessage());
        }
    }

    public function tipoExiste($nombre)
    {
        try {
            $stmt = $this->conexion->prepare("SELECT COUNT(*) FROM sist_prod_tipo_transporte WHERE LOWER(nombre) = LOWER(?)");
            $stmt->execute([$nombre]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            throw new Exception("Error al verificar tipo de transporte: " . $e->getMessage());
        }
    }

    public function tipoExisteExcluyendo($nombre, $id)
    {
        try {
            $stmt = $this->conexion->prepare("SELECT COUNT(*) FROM sist_prod_tipo_transporte WHERE LOWER(nombre) = LOWER(?) AND id != ?");
            $stmt->execute([$nombre, $id]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            throw new Exception("Error al verificar tipo de transporte: " . $e->getMessage());
        }
    }

    public function obtenerTipoPorId($id)
    {
        try {
            $stmt = $this->conexion->prepare("SELECT id, nombre FROM sist_prod_tipo_transporte WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener tipo de transporte: " . $e->getMessage());
        }
    }

    public function obtenerTipoPorNombre($nombre)
    {
        try {
            $stmt = $this->conexion->prepare("SELECT id, nombre FROM sist_prod_tipo_transporte WHERE LOWER(nombre) = LOWER(?)");
            $stmt->execute([$nombre]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener tipo de transporte: " . $e->getMessage());
        }
    }

    public function buscarTiposPorNombre($termino)
    {
        try {
            $stmt = $this->conexion->prepare("SELECT id, nombre FROM sist_prod_tipo_transporte WHERE LOWER(nombre) LIKE LOWER(?) ORDER BY nombre");
            $stmt->execute(['%' . $termino . '%']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al buscar tipos de transporte: " . $e->getMessage());
        }
    }

    public function contarTiposDeTransporte()
    {
        try {
            $stmt = $this->conexion->query("SELECT COUNT(*) FROM sist_prod_tipo_transporte");
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw new Exception("Error al contar tipos de transporte: " . $e->getMessage());
        }
    }

    public function obtenerTiposMasUsados($limite = 5)
    {
        try {
            // Esta consulta asume que existe una tabla que relaciona tipos de transporte con envíos
            // Ajusta según tu estructura de base de datos
            $stmt = $this->conexion->prepare("
                SELECT tt.id, tt.nombre, COUNT(e.id) as total_usos 
                FROM sist_prod_tipo_transporte tt 
                LEFT JOIN envios e ON tt.id = e.tipo_transporte_id 
                GROUP BY tt.id, tt.nombre 
                ORDER BY total_usos DESC 
                LIMIT ?
            ");
            $stmt->execute([$limite]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Si no existe la relación, simplemente retorna los tipos ordenados alfabéticamente
            return $this->obtenerTodosLosTipos();
        }
    }

    public function verificarTipoEnUso($id)
    {
        try {
            // Esta consulta verifica si el tipo de transporte está siendo usado
            // Ajusta según tus tablas relacionadas
            $stmt = $this->conexion->prepare("
                SELECT COUNT(*) FROM envios WHERE tipo_transporte_id = ?
                UNION ALL
                SELECT COUNT(*) FROM expediciones WHERE tipo_transporte_id = ?
            ");
            $stmt->execute([$id, $id]);
            $resultados = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return array_sum($resultados) > 0;
        } catch (PDOException $e) {
            // Si no existen las tablas relacionadas, retorna false
            return false;
        }
    }

    public function obtenerEstadisticasTipos()
    {
        try {
            $stmt = $this->conexion->query("
                SELECT 
                    COUNT(*) as total_tipos,
                    MAX(nombre) as ultimo_agregado,
                    MIN(nombre) as primero_alfabetico
                FROM sist_prod_tipo_transporte
            ");
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener estadísticas: " . $e->getMessage());
        }
    }
}
