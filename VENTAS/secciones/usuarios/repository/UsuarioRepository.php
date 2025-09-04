<?php

/**
 * Repository para operaciones de base de datos de usuarios
 */
class UsuarioRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    /**
     * Obtener la conexión para transacciones
     */
    public function getConexion()
    {
        return $this->conexion;
    }

    /**
     * Obtener todos los usuarios (excluyendo stockapp)
     */
    public function obtenerUsuarios()
    {
        try {
            $sql = "SELECT id, nombre, usuario, rol 
                    FROM public.sist_ventas_usuario 
                    WHERE stockapp IS NOT TRUE 
                    ORDER BY id ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo usuarios: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener usuario por ID
     */
    public function obtenerUsuarioPorId($id)
    {
        try {
            $sql = "SELECT * FROM public.sist_ventas_usuario WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo usuario por ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crear nuevo usuario
     */
    public function crearUsuario($datos)
    {
        try {
            $sql = "INSERT INTO public.sist_ventas_usuario (nombre, usuario, rol, contrasenia) 
                    VALUES (:nombre, :usuario, :rol, :contrasenia)";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);
            $stmt->bindParam(':usuario', $datos['usuario'], PDO::PARAM_STR);
            $stmt->bindParam(':rol', $datos['rol'], PDO::PARAM_STR);
            $stmt->bindParam(':contrasenia', $datos['contrasenia'], PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error creando usuario: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar usuario
     */
    public function actualizarUsuario($id, $datos)
    {
        try {
            if (isset($datos['cambiar_contrasenia']) && $datos['cambiar_contrasenia']) {
                // Actualizar con contraseña
                $sql = "UPDATE public.sist_ventas_usuario 
                        SET nombre = :nombre, usuario = :usuario, rol = :rol, contrasenia = :contrasenia 
                        WHERE id = :id";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);
                $stmt->bindParam(':usuario', $datos['usuario'], PDO::PARAM_STR);
                $stmt->bindParam(':rol', $datos['rol'], PDO::PARAM_STR);
                $stmt->bindParam(':contrasenia', $datos['contrasenia'], PDO::PARAM_STR);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            } else {
                // Actualizar sin contraseña
                $sql = "UPDATE public.sist_ventas_usuario 
                        SET nombre = :nombre, usuario = :usuario, rol = :rol 
                        WHERE id = :id";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);
                $stmt->bindParam(':usuario', $datos['usuario'], PDO::PARAM_STR);
                $stmt->bindParam(':rol', $datos['rol'], PDO::PARAM_STR);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            }

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando usuario: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar usuario
     */
    public function eliminarUsuario($id)
    {
        try {
            $sql = "DELETE FROM public.sist_ventas_usuario WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error eliminando usuario: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si nombre de usuario ya existe
     */
    public function verificarUsuarioExiste($usuario, $idExcluir = null)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM public.sist_ventas_usuario WHERE usuario = :usuario";
            $params = [':usuario' => $usuario];

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
            error_log("Error verificando usuario: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener estadísticas de usuarios
     */
    public function obtenerEstadisticas()
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_usuarios,
                        COUNT(CASE WHEN rol = '1' THEN 1 END) as administradores,
                        COUNT(CASE WHEN rol = '2' THEN 1 END) as vendedores,
                        COUNT(CASE WHEN rol = '3' THEN 1 END) as contadores,
                        COUNT(CASE WHEN rol = '4' THEN 1 END) as pcp
                    FROM public.sist_ventas_usuario 
                    WHERE stockapp IS NOT TRUE";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar usuarios por término
     */
    public function buscarUsuarios($termino, $limite = 10)
    {
        try {
            $sql = "SELECT id, nombre, usuario, rol 
                    FROM public.sist_ventas_usuario 
                    WHERE (nombre ILIKE :termino OR usuario ILIKE :termino) 
                    AND stockapp IS NOT TRUE
                    ORDER BY nombre 
                    LIMIT :limite";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':termino', '%' . $termino . '%', PDO::PARAM_STR);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error buscando usuarios: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener roles disponibles
     */
    public function obtenerRoles()
    {
        return [
            '1' => 'Administrador',
            '2' => 'Vendedor',
            '3' => 'Contador',
            '4' => 'PCP'
        ];
    }

    /**
     * Verificar si un usuario puede ser eliminado
     */
    public function puedeEliminarUsuario($id, $idUsuarioActual)
    {
        // No puede eliminar su propio usuario
        return $id != $idUsuarioActual;
    }

    /**
     * Actualizar sesión de usuario si es el mismo
     */
    public function actualizarSesionSiEsNecesario($id, $datos, $idUsuarioActual)
    {
        if ($id == $idUsuarioActual) {
            $_SESSION['nombre'] = $datos['nombre'];
            $_SESSION['usuario'] = $datos['usuario'];
            $_SESSION['rol'] = $datos['rol'];
            return true;
        }
        return false;
    }
}
