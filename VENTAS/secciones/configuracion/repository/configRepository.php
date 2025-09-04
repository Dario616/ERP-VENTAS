<?php


class ConfigRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }


    public function obtenerCreditos()
    {
        try {
            $query = "SELECT id, descripcion FROM public.sist_ventas_cuotas ORDER BY id ASC";
            $stmt = $this->conexion->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo créditos: " . $e->getMessage());
            return [];
        }
    }


    public function obtenerCredito($id)
    {
        try {
            $query = "SELECT * FROM public.sist_ventas_cuotas WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo crédito: " . $e->getMessage());
            return false;
        }
    }


    public function crearCredito($descripcion)
    {
        try {
            $query = "INSERT INTO public.sist_ventas_cuotas (descripcion) VALUES (:descripcion)";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error creando crédito: " . $e->getMessage());
            throw $e;
        }
    }


    public function actualizarCredito($id, $descripcion)
    {
        try {
            $query = "UPDATE public.sist_ventas_cuotas SET descripcion = :descripcion WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando crédito: " . $e->getMessage());
            throw $e;
        }
    }

    public function eliminarCredito($id)
    {
        try {
            $query = "DELETE FROM public.sist_ventas_cuotas WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error eliminando crédito: " . $e->getMessage());
            throw $e;
        }
    }


    public function existeCreditoDescripcion($descripcion, $excluirId = null)
    {
        try {
            $query = "SELECT COUNT(*) as total FROM public.sist_ventas_cuotas WHERE descripcion = :descripcion";
            $params = [':descripcion' => $descripcion];

            if ($excluirId !== null) {
                $query .= " AND id != :id";
                $params[':id'] = $excluirId;
            }

            $stmt = $this->conexion->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
        } catch (Exception $e) {
            error_log("Error verificando crédito: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerTiposProducto()
    {
        try {
            $query = "SELECT id, \"desc\" FROM public.sist_ventas_tipoproduc ORDER BY id ASC";
            $stmt = $this->conexion->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo tipos de producto: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerTipoProducto($id)
    {
        try {
            $query = "SELECT * FROM public.sist_ventas_tipoproduc WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo tipo de producto: " . $e->getMessage());
            return false;
        }
    }

    public function crearTipoProducto($descripcion)
    {
        try {
            $query = "INSERT INTO public.sist_ventas_tipoproduc (\"desc\") VALUES (:desc)";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindValue(':desc', $descripcion, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error creando tipo de producto: " . $e->getMessage());
            throw $e;
        }
    }

    public function actualizarTipoProducto($id, $descripcion)
    {
        try {
            $query = "UPDATE public.sist_ventas_tipoproduc SET \"desc\" = :desc WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindValue(':desc', $descripcion, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando tipo de producto: " . $e->getMessage());
            throw $e;
        }
    }

    public function eliminarTipoProducto($id)
    {
        try {
            $query = "DELETE FROM public.sist_ventas_tipoproduc WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error eliminando tipo de producto: " . $e->getMessage());
            throw $e;
        }
    }

    public function existeTipoProductoDescripcion($descripcion, $excluirId = null)
    {
        try {
            $query = "SELECT COUNT(*) as total FROM public.sist_ventas_tipoproduc WHERE \"desc\" = :desc";
            $params = [':desc' => $descripcion];

            if ($excluirId !== null) {
                $query .= " AND id != :id";
                $params[':id'] = $excluirId;
            }

            $stmt = $this->conexion->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
        } catch (Exception $e) {
            error_log("Error verificando tipo de producto: " . $e->getMessage());
            return false;
        }
    }

    public function contarProductosConTipo($descripcionTipo)
    {
        try {
            $query = "SELECT COUNT(*) as total FROM public.sist_ventas_productos WHERE tipo = :tipo_desc";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindValue(':tipo_desc', $descripcionTipo, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (Exception $e) {
            error_log("Error contando productos con tipo: " . $e->getMessage());
            return 0;
        }
    }


    public function obtenerUnidadesMedida()
    {
        try {
            $query = "SELECT id, \"desc\" FROM public.sist_ventas_medida ORDER BY id ASC";
            $stmt = $this->conexion->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo unidades de medida: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerUnidadMedida($id)
    {
        try {
            $query = "SELECT * FROM public.sist_ventas_medida WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo unidad de medida: " . $e->getMessage());
            return false;
        }
    }

    public function crearUnidadMedida($descripcion)
    {
        try {
            $query = "INSERT INTO public.sist_ventas_medida (\"desc\") VALUES (:desc)";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindValue(':desc', $descripcion, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error creando unidad de medida: " . $e->getMessage());
            throw $e;
        }
    }


    public function actualizarUnidadMedida($id, $descripcion)
    {
        try {
            $query = "UPDATE public.sist_ventas_medida SET \"desc\" = :desc WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindValue(':desc', $descripcion, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando unidad de medida: " . $e->getMessage());
            throw $e;
        }
    }


    public function eliminarUnidadMedida($id)
    {
        try {
            $query = "DELETE FROM public.sist_ventas_medida WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error eliminando unidad de medida: " . $e->getMessage());
            throw $e;
        }
    }


    public function existeUnidadMedidaDescripcion($descripcion, $excluirId = null)
    {
        try {
            $query = "SELECT COUNT(*) as total FROM public.sist_ventas_medida WHERE \"desc\" = :desc";
            $params = [':desc' => $descripcion];

            if ($excluirId !== null) {
                $query .= " AND id != :id";
                $params[':id'] = $excluirId;
            }

            $stmt = $this->conexion->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
        } catch (Exception $e) {
            error_log("Error verificando unidad de medida: " . $e->getMessage());
            return false;
        }
    }
}
