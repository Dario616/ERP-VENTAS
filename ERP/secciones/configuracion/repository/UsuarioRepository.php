<?php
// repository/UsuarioRepository.php

class UsuarioRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    public function crearUsuario($datos)
    {
        try {
            $stmt = $this->conexion->prepare("INSERT INTO sist_prod_usuario (nombre, usuario, contrasenia, rol) VALUES (?, ?, ?, ?)");
            return $stmt->execute([
                $datos['nombre'],
                $datos['usuario'],
                $datos['contrasenia'],
                $datos['rol']
            ]);
        } catch (PDOException $e) {
            throw new Exception("Error de base de datos: " . $e->getMessage());
        }
    }

    public function editarUsuario($datos)
    {
        try {
            if (!empty($datos['nueva_contrasenia'])) {
                $stmt = $this->conexion->prepare("UPDATE sist_prod_usuario SET nombre = ?, usuario = ?, contrasenia = ?, rol = ? WHERE id = ?");
                return $stmt->execute([
                    $datos['nombre'],
                    $datos['usuario'],
                    $datos['nueva_contrasenia'],
                    $datos['rol'],
                    $datos['id']
                ]);
            } else {
                $stmt = $this->conexion->prepare("UPDATE sist_prod_usuario SET nombre = ?, usuario = ?, rol = ? WHERE id = ?");
                return $stmt->execute([
                    $datos['nombre'],
                    $datos['usuario'],
                    $datos['rol'],
                    $datos['id']
                ]);
            }
        } catch (PDOException $e) {
            throw new Exception("Error de base de datos: " . $e->getMessage());
        }
    }

    public function eliminarUsuario($id)
    {
        try {
            $stmt = $this->conexion->prepare("DELETE FROM sist_prod_usuario WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            throw new Exception("Error de base de datos: " . $e->getMessage());
        }
    }

    public function obtenerTodosLosUsuarios()
    {
        try {
            $stmt = $this->conexion->query("SELECT id, nombre, usuario, rol FROM sist_prod_usuario ORDER BY nombre");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener usuarios: " . $e->getMessage());
        }
    }

    public function usuarioExiste($usuario)
    {
        try {
            $stmt = $this->conexion->prepare("SELECT COUNT(*) FROM sist_prod_usuario WHERE usuario = ?");
            $stmt->execute([$usuario]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            throw new Exception("Error al verificar usuario: " . $e->getMessage());
        }
    }

    public function usuarioExisteExcluyendo($usuario, $id)
    {
        try {
            $stmt = $this->conexion->prepare("SELECT COUNT(*) FROM sist_prod_usuario WHERE usuario = ? AND id != ?");
            $stmt->execute([$usuario, $id]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            throw new Exception("Error al verificar usuario: " . $e->getMessage());
        }
    }

    public function obtenerUsuarioPorId($id)
    {
        try {
            $stmt = $this->conexion->prepare("SELECT id, nombre, usuario, rol FROM sist_prod_usuario WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener usuario: " . $e->getMessage());
        }
    }

    public function obtenerUsuarioPorNombreUsuario($usuario)
    {
        try {
            $stmt = $this->conexion->prepare("SELECT id, nombre, usuario, rol FROM sist_prod_usuario WHERE usuario = ?");
            $stmt->execute([$usuario]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener usuario: " . $e->getMessage());
        }
    }

    public function contarUsuariosPorRol($rol)
    {
        try {
            $stmt = $this->conexion->prepare("SELECT COUNT(*) FROM sist_prod_usuario WHERE rol = ?");
            $stmt->execute([$rol]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw new Exception("Error al contar usuarios: " . $e->getMessage());
        }
    }

    public function obtenerUsuariosPorRol($rol)
    {
        try {
            $stmt = $this->conexion->prepare("SELECT id, nombre, usuario, rol FROM sist_prod_usuario WHERE rol = ? ORDER BY nombre");
            $stmt->execute([$rol]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener usuarios por rol: " . $e->getMessage());
        }
    }
}
