<?php
// repository/TransportadoraRepository.php

class TransportadoraRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    public function crearTransportadora($datos)
    {
        try {
            $stmt = $this->conexion->prepare("INSERT INTO sist_prod_transportadora (descripcion) VALUES (?)");
            return $stmt->execute([$datos['descripcion']]);
        } catch (PDOException $e) {
            throw new Exception("Error de base de datos: " . $e->getMessage());
        }
    }

    public function editarTransportadora($datos)
    {
        try {
            $stmt = $this->conexion->prepare("UPDATE sist_prod_transportadora SET descripcion = ? WHERE id = ?");
            return $stmt->execute([$datos['descripcion'], $datos['id']]);
        } catch (PDOException $e) {
            throw new Exception("Error de base de datos: " . $e->getMessage());
        }
    }

    public function eliminarTransportadora($id)
    {
        try {
            $stmt = $this->conexion->prepare("DELETE FROM sist_prod_transportadora WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            if ($e->getCode() == '23503') { // Foreign key violation
                throw new Exception("No se puede eliminar la transportadora porque tiene registros asociados.");
            } else {
                throw new Exception("Error de base de datos: " . $e->getMessage());
            }
        }
    }

    public function obtenerTodasLasTransportadoras()
    {
        try {
            $stmt = $this->conexion->query("SELECT id, descripcion FROM sist_prod_transportadora ORDER BY descripcion");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener transportadoras: " . $e->getMessage());
        }
    }

    public function transportadoraExiste($descripcion)
    {
        try {
            $stmt = $this->conexion->prepare("SELECT COUNT(*) FROM sist_prod_transportadora WHERE LOWER(descripcion) = LOWER(?)");
            $stmt->execute([$descripcion]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            throw new Exception("Error al verificar transportadora: " . $e->getMessage());
        }
    }

    public function transportadoraExisteExcluyendo($descripcion, $id)
    {
        try {
            $stmt = $this->conexion->prepare("SELECT COUNT(*) FROM sist_prod_transportadora WHERE LOWER(descripcion) = LOWER(?) AND id != ?");
            $stmt->execute([$descripcion, $id]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            throw new Exception("Error al verificar transportadora: " . $e->getMessage());
        }
    }

    public function obtenerTransportadoraPorId($id)
    {
        try {
            $stmt = $this->conexion->prepare("SELECT id, descripcion FROM sist_prod_transportadora WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener transportadora: " . $e->getMessage());
        }
    }

    public function obtenerTransportadoraPorDescripcion($descripcion)
    {
        try {
            $stmt = $this->conexion->prepare("SELECT id, descripcion FROM sist_prod_transportadora WHERE LOWER(descripcion) = LOWER(?)");
            $stmt->execute([$descripcion]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener transportadora: " . $e->getMessage());
        }
    }

    public function buscarTransportadorasPorDescripcion($termino)
    {
        try {
            $stmt = $this->conexion->prepare("SELECT id, descripcion FROM sist_prod_transportadora WHERE LOWER(descripcion) LIKE LOWER(?) ORDER BY descripcion");
            $stmt->execute(['%' . $termino . '%']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al buscar transportadoras: " . $e->getMessage());
        }
    }

    public function contarTransportadoras()
    {
        try {
            $stmt = $this->conexion->query("SELECT COUNT(*) FROM sist_prod_transportadora");
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw new Exception("Error al contar transportadoras: " . $e->getMessage());
        }
    }

    public function obtenerTransportadorasActivas()
    {
        try {
            // Si tu tabla tiene un campo de estado, úsa este query:
            // $stmt = $this->conexion->query("SELECT id, descripcion FROM sist_prod_transportadora WHERE activo = 1 ORDER BY descripcion");

            // Si no tienes campo de estado, simplemente retorna todas:
            $stmt = $this->conexion->query("SELECT id, descripcion FROM sist_prod_transportadora ORDER BY descripcion");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener transportadoras activas: " . $e->getMessage());
        }
    }

    public function obtenerTransportadorasMasUsadas($limite = 5)
    {
        try {
            // Esta consulta asume que existe una tabla que relaciona transportadoras con envíos
            // Ajusta según tu estructura de base de datos
            $stmt = $this->conexion->prepare("
                SELECT t.id, t.descripcion, COUNT(e.id) as total_usos 
                FROM sist_prod_transportadora t 
                LEFT JOIN envios e ON t.id = e.transportadora_id 
                GROUP BY t.id, t.descripcion 
                ORDER BY total_usos DESC 
                LIMIT ?
            ");
            $stmt->execute([$limite]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Si no existe la relación, simplemente retorna las transportadoras ordenadas alfabéticamente
            return $this->obtenerTodasLasTransportadoras();
        }
    }

    public function verificarTransportadoraEnUso($id)
    {
        try {
            // Esta consulta verifica si la transportadora está siendo usada
            // Ajusta según tus tablas relacionadas
            $stmt = $this->conexion->prepare("
                SELECT COUNT(*) FROM envios WHERE transportadora_id = ?
                UNION ALL
                SELECT COUNT(*) FROM expediciones WHERE transportadora_id = ?
            ");
            $stmt->execute([$id, $id]);
            $resultados = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return array_sum($resultados) > 0;
        } catch (PDOException $e) {
            // Si no existen las tablas relacionadas, retorna false
            return false;
        }
    }

    public function obtenerEstadisticasTransportadoras()
    {
        try {
            $stmt = $this->conexion->query("
                SELECT 
                    COUNT(*) as total_transportadoras,
                    MAX(descripcion) as ultima_agregada,
                    MIN(descripcion) as primera_alfabetica
                FROM sist_prod_transportadora
            ");
            $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

            // Intentar obtener estadísticas de uso
            try {
                $stmt_uso = $this->conexion->query("
                    SELECT COUNT(DISTINCT transportadora_id) as transportadoras_con_envios
                    FROM envios 
                    WHERE transportadora_id IS NOT NULL
                ");
                $uso = $stmt_uso->fetch(PDO::FETCH_ASSOC);
                $estadisticas['con_envios'] = $uso['transportadoras_con_envios'] ?? 0;
            } catch (PDOException $e) {
                $estadisticas['con_envios'] = 0;
            }

            return $estadisticas;
        } catch (PDOException $e) {
            throw new Exception("Error al obtener estadísticas: " . $e->getMessage());
        }
    }

    public function marcarTransportadoraComoActiva($id)
    {
        try {
            // Si tu tabla tiene un campo 'activo', úsa este query:
            // $stmt = $this->conexion->prepare("UPDATE sist_prod_transportadora SET activo = 1 WHERE id = ?");
            // return $stmt->execute([$id]);

            // Si no tienes campo de estado, este método no hace nada
            return true;
        } catch (PDOException $e) {
            throw new Exception("Error al activar transportadora: " . $e->getMessage());
        }
    }

    public function marcarTransportadoraComoInactiva($id)
    {
        try {
            // Si tu tabla tiene un campo 'activo', úsa este query:
            // $stmt = $this->conexion->prepare("UPDATE sist_prod_transportadora SET activo = 0 WHERE id = ?");
            // return $stmt->execute([$id]);

            // Si no tienes campo de estado, este método no hace nada
            return true;
        } catch (PDOException $e) {
            throw new Exception("Error al desactivar transportadora: " . $e->getMessage());
        }
    }

    public function obtenerTransportadorasPorRango($offset, $limite)
    {
        try {
            $stmt = $this->conexion->prepare("
                SELECT id, descripcion 
                FROM sist_prod_transportadora 
                ORDER BY descripcion 
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limite, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener transportadoras por rango: " . $e->getMessage());
        }
    }

    public function obtenerUltimasTransportadorasAgregadas($limite = 5)
    {
        try {
            $stmt = $this->conexion->prepare("SELECT id, descripcion FROM sist_prod_transportadora ORDER BY id DESC LIMIT ?");
            $stmt->execute([$limite]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener últimas transportadoras: " . $e->getMessage());
        }
    }
}
