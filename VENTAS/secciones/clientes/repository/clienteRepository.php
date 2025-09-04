<?php

class ClienteRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }


    public function obtenerClientes($idUsuario = null, $filtros = [])
    {
        try {
            $whereConditions = [];
            $params = [];

            if ($idUsuario !== null) {
                $whereConditions[] = "id_usuario = :id_usuario";
                $params[':id_usuario'] = $idUsuario;
            }

            if (!empty($filtros['nombre'])) {
                $whereConditions[] = "nombre ILIKE :nombre";
                $params[':nombre'] = '%' . $filtros['nombre'] . '%';
            }

            if (!empty($filtros['pais'])) {
                if ($filtros['pais'] === 'PY') {
                    $whereConditions[] = "(brasil = false OR brasil IS NULL)";
                } elseif ($filtros['pais'] === 'BR') {
                    $whereConditions[] = "brasil = true";
                }
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            $sql = "SELECT id, nombre, descripcion, telefono, email, direccion, ruc, cnpj, ie, nro, brasil, fecha_registro 
                    FROM sist_ventas_clientes 
                    {$whereClause} 
                    ORDER BY id DESC";

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_STR);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo clientes: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerClientePorId($id, $idUsuario = null)
    {
        try {
            $sql = "SELECT * FROM sist_ventas_clientes WHERE id = :id";
            $params = [':id' => $id];

            if ($idUsuario !== null) {
                $sql .= " AND id_usuario = :id_usuario";
                $params[':id_usuario'] = $idUsuario;
            }

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            }
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo cliente por ID: " . $e->getMessage());
            return false;
        }
    }

    public function crearCliente($datos)
    {
        try {
            $sql = "INSERT INTO sist_ventas_clientes 
                    (nombre, descripcion, telefono, email, direccion, ruc, cnpj, ie, fecha_registro, nro, brasil, id_usuario) 
                    VALUES (:nombre, :descripcion, :telefono, :email, :direccion, :ruc, :cnpj, :ie, :fecha_registro, :nro, :brasil, :id_usuario)";

            $stmt = $this->conexion->prepare($sql);

            $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);
            $stmt->bindParam(':descripcion', $datos['descripcion'], PDO::PARAM_STR);
            $stmt->bindParam(':telefono', $datos['telefono'], PDO::PARAM_STR);
            $stmt->bindParam(':email', $datos['email'], PDO::PARAM_STR);
            $stmt->bindParam(':direccion', $datos['direccion'], PDO::PARAM_STR);
            $stmt->bindParam(':ruc', $datos['ruc'], PDO::PARAM_STR);
            $stmt->bindParam(':cnpj', $datos['cnpj'], PDO::PARAM_STR);
            $stmt->bindParam(':ie', $datos['ie'], PDO::PARAM_STR);
            $stmt->bindParam(':fecha_registro', $datos['fecha_registro'], PDO::PARAM_STR);
            $stmt->bindParam(':nro', $datos['nro'], PDO::PARAM_STR);
            $stmt->bindParam(':brasil', $datos['brasil'], PDO::PARAM_BOOL);
            $stmt->bindParam(':id_usuario', $datos['id_usuario'], PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error creando cliente: " . $e->getMessage());
            return false;
        }
    }

    public function actualizarCliente($id, $datos, $idUsuario = null)
    {
        try {
            $sql = "UPDATE sist_ventas_clientes SET 
                    nombre = :nombre, 
                    descripcion = :descripcion, 
                    telefono = :telefono, 
                    email = :email, 
                    direccion = :direccion, 
                    ruc = :ruc,
                    cnpj = :cnpj,
                    ie = :ie,
                    nro = :nro,
                    brasil = :brasil
                    WHERE id = :id";

            $params = [
                ':nombre' => $datos['nombre'],
                ':descripcion' => $datos['descripcion'],
                ':telefono' => $datos['telefono'],
                ':email' => $datos['email'],
                ':direccion' => $datos['direccion'],
                ':ruc' => $datos['ruc'],
                ':cnpj' => $datos['cnpj'],
                ':ie' => $datos['ie'],
                ':nro' => $datos['nro'],
                ':brasil' => $datos['brasil'],
                ':id' => $id
            ];

            if ($idUsuario !== null) {
                $sql .= " AND id_usuario = :id_usuario";
                $params[':id_usuario'] = $idUsuario;
            }

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $key => $val) {
                if ($key === ':brasil') {
                    $stmt->bindValue($key, $val, PDO::PARAM_BOOL);
                } elseif (in_array($key, [':id', ':id_usuario'])) {
                    $stmt->bindValue($key, $val, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $val, PDO::PARAM_STR);
                }
            }

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando cliente: " . $e->getMessage());
            return false;
        }
    }

    public function eliminarCliente($id, $idUsuario = null)
    {
        try {
            $sql = "DELETE FROM sist_ventas_clientes WHERE id = :id";
            $params = [':id' => $id];

            if ($idUsuario !== null) {
                $sql .= " AND id_usuario = :id_usuario";
                $params[':id_usuario'] = $idUsuario;
            }

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            }

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error eliminando cliente: " . $e->getMessage());
            return false;
        }
    }

    public function verificarRucExiste($ruc, $idExcluir = null)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM sist_ventas_clientes 
                    WHERE ruc = :ruc AND (brasil = false OR brasil IS NULL)";
            $params = [':ruc' => $ruc];

            if ($idExcluir !== null) {
                $sql .= " AND id != :id";
                $params[':id'] = $idExcluir;
            }

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (Exception $e) {
            error_log("Error verificando RUC: " . $e->getMessage());
            return false;
        }
    }

    public function verificarCnpjExiste($cnpj, $idExcluir = null)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM sist_ventas_clientes 
                    WHERE cnpj = :cnpj AND brasil = true";
            $params = [':cnpj' => $cnpj];

            if ($idExcluir !== null) {
                $sql .= " AND id != :id";
                $params[':id'] = $idExcluir;
            }

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (Exception $e) {
            error_log("Error verificando CNPJ: " . $e->getMessage());
            return false;
        }
    }

    public function verificarIeExiste($ie, $idExcluir = null)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM sist_ventas_clientes 
                    WHERE ie = :ie AND brasil = true";
            $params = [':ie' => $ie];

            if ($idExcluir !== null) {
                $sql .= " AND id != :id";
                $params[':id'] = $idExcluir;
            }

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (Exception $e) {
            error_log("Error verificando IE: " . $e->getMessage());
            return false;
        }
    }


    public function verificarEmailExiste($email, $idExcluir = null)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM sist_ventas_clientes WHERE email = :email";
            $params = [':email' => $email];

            if ($idExcluir !== null) {
                $sql .= " AND id != :id";
                $params[':id'] = $idExcluir;
            }

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
        } catch (Exception $e) {
            error_log("Error verificando email: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerEstadisticas($idUsuario = null)
    {
        try {
            $whereClause = '';
            $params = [];

            if ($idUsuario !== null) {
                $whereClause = 'WHERE id_usuario = :id_usuario';
                $params[':id_usuario'] = $idUsuario;
            }

            $sql = "SELECT 
                        COUNT(*) as total_clientes,
                        COUNT(CASE WHEN brasil = true THEN 1 END) as clientes_brasil,
                        COUNT(CASE WHEN (brasil = false OR brasil IS NULL) THEN 1 END) as clientes_paraguay,
                        COUNT(CASE WHEN email IS NOT NULL AND email != '' THEN 1 END) as con_email,
                        COUNT(CASE WHEN telefono IS NOT NULL AND telefono != '' THEN 1 END) as con_telefono
                    FROM sist_ventas_clientes {$whereClause}";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            }
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [];
        }
    }


    public function buscarClientes($termino, $idUsuario = null, $limite = 10)
    {
        try {
            $whereConditions = ["(nombre ILIKE :termino OR email ILIKE :termino OR ruc ILIKE :termino OR cnpj ILIKE :termino)"];
            $params = [':termino' => '%' . $termino . '%'];

            if ($idUsuario !== null) {
                $whereConditions[] = "id_usuario = :id_usuario";
                $params[':id_usuario'] = $idUsuario;
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $sql = "SELECT id, nombre, email, ruc, cnpj, brasil 
                    FROM sist_ventas_clientes 
                    {$whereClause} 
                    ORDER BY nombre 
                    LIMIT :limite";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $val) {
                if ($key === ':limite') {
                    $stmt->bindValue($key, $val, PDO::PARAM_INT);
                } elseif ($key === ':id_usuario') {
                    $stmt->bindValue($key, $val, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $val, PDO::PARAM_STR);
                }
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error buscando clientes: " . $e->getMessage());
            return [];
        }
    }
}
