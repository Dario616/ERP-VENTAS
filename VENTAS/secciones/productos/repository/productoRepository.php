<?php

class ProductoRepository
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    public function getConexion()
    {
        return $this->conexion;
    }
    public function obtenerProductos($filtros = [])
    {
        try {
            $whereConditions = [];
            $params = [];

            if (!empty($filtros['descripcion'])) {
                $whereConditions[] = "descripcion ILIKE :descripcion";
                $params[':descripcion'] = '%' . $filtros['descripcion'] . '%';
            }

            if (!empty($filtros['tipo'])) {
                $whereConditions[] = "tipo ILIKE :tipo";
                $params[':tipo'] = '%' . $filtros['tipo'] . '%';
            }

            if (!empty($filtros['codigo'])) {
                $whereConditions[] = "codigobr ILIKE :codigo";
                $params[':codigo'] = '%' . $filtros['codigo'] . '%';
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            $registros_por_pagina = $filtros['registros_por_pagina'] ?? 10;
            $pagina_actual = $filtros['pagina'] ?? 1;
            $offset = ($pagina_actual - 1) * $registros_por_pagina;

            $sql = "SELECT id, descripcion, codigobr, tipo, cantidad, ncm 
                    FROM public.sist_ventas_productos 
                    {$whereClause} 
                    ORDER BY id DESC 
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }

            $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos: " . $e->getMessage());
            return [];
        }
    }

    public function contarProductos($filtros = [])
    {
        try {
            $whereConditions = [];
            $params = [];

            if (!empty($filtros['descripcion'])) {
                $whereConditions[] = "descripcion ILIKE :descripcion";
                $params[':descripcion'] = '%' . $filtros['descripcion'] . '%';
            }

            if (!empty($filtros['tipo'])) {
                $whereConditions[] = "tipo ILIKE :tipo";
                $params[':tipo'] = '%' . $filtros['tipo'] . '%';
            }

            if (!empty($filtros['codigo'])) {
                $whereConditions[] = "codigobr ILIKE :codigo";
                $params[':codigo'] = '%' . $filtros['codigo'] . '%';
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            $sql = "SELECT COUNT(*) as total FROM public.sist_ventas_productos {$whereClause}";
            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'];
        } catch (Exception $e) {
            error_log("Error contando productos: " . $e->getMessage());
            return 0;
        }
    }

    public function obtenerProductoPorId($id)
    {
        try {
            $sql = "SELECT * FROM public.sist_ventas_productos WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo producto por ID: " . $e->getMessage());
            return false;
        }
    }

    public function crearProducto($datos)
    {
        try {
            $sql = "INSERT INTO public.sist_ventas_productos 
                    (descripcion, codigobr, nombreimg, tipoimg, img, base64img, tipo, cantidad, ncm) 
                    VALUES (:descripcion, :codigobr, :nombreimg, :tipoimg, :img, :base64img, :tipo, :cantidad, :ncm) 
                    RETURNING id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':descripcion', $datos['descripcion'], PDO::PARAM_STR);
            $stmt->bindParam(':codigobr', $datos['codigobr'], PDO::PARAM_STR);
            $stmt->bindParam(':nombreimg', $datos['nombreimg'], PDO::PARAM_STR);
            $stmt->bindParam(':tipoimg', $datos['tipoimg'], PDO::PARAM_STR);
            $stmt->bindParam(':img', $datos['img'], PDO::PARAM_LOB);
            $stmt->bindParam(':base64img', $datos['base64img'], PDO::PARAM_STR);
            $stmt->bindParam(':tipo', $datos['tipo'], PDO::PARAM_STR);
            $stmt->bindParam(':cantidad', $datos['cantidad']);
            $stmt->bindParam(':ncm', $datos['ncm'], PDO::PARAM_STR);

            $stmt->execute();
            return $this->conexion->lastInsertId();
        } catch (Exception $e) {
            error_log("Error creando producto: " . $e->getMessage());
            return false;
        }
    }


    public function actualizarProducto($id, $datos)
    {
        try {
            if (isset($datos['nueva_imagen']) && $datos['nueva_imagen']) {
                $sql = "UPDATE public.sist_ventas_productos 
                        SET descripcion = :descripcion, codigobr = :codigobr, 
                            nombreimg = :nombreimg, tipoimg = :tipoimg, 
                            img = :img, base64img = :base64img, 
                            tipo = :tipo, cantidad = :cantidad, 
                            ncm = :ncm
                        WHERE id = :id";

                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(':nombreimg', $datos['nombreimg'], PDO::PARAM_STR);
                $stmt->bindParam(':tipoimg', $datos['tipoimg'], PDO::PARAM_STR);
                $stmt->bindParam(':img', $datos['img'], PDO::PARAM_LOB);
                $stmt->bindParam(':base64img', $datos['base64img'], PDO::PARAM_STR);
            } elseif (isset($datos['eliminar_imagen']) && $datos['eliminar_imagen']) {
                $sql = "UPDATE public.sist_ventas_productos 
                        SET descripcion = :descripcion, codigobr = :codigobr, 
                            nombreimg = NULL, tipoimg = NULL, 
                            img = NULL, base64img = NULL, 
                            tipo = :tipo, cantidad = :cantidad, 
                            ncm = :ncm
                        WHERE id = :id";

                $stmt = $this->conexion->prepare($sql);
            } else {
                $sql = "UPDATE public.sist_ventas_productos 
                        SET descripcion = :descripcion, codigobr = :codigobr, 
                            tipo = :tipo, cantidad = :cantidad,
                            ncm = :ncm 
                        WHERE id = :id";

                $stmt = $this->conexion->prepare($sql);
            }
            $stmt->bindParam(':descripcion', $datos['descripcion'], PDO::PARAM_STR);
            $stmt->bindParam(':codigobr', $datos['codigobr'], PDO::PARAM_STR);
            $stmt->bindParam(':tipo', $datos['tipo'], PDO::PARAM_STR);
            $stmt->bindParam(':cantidad', $datos['cantidad']);
            $stmt->bindParam(':ncm', $datos['ncm'], PDO::PARAM_STR);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando producto: " . $e->getMessage());
            return false;
        }
    }

    public function eliminarProducto($id)
    {
        try {
            $sql = "DELETE FROM public.sist_ventas_productos WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error eliminando producto: " . $e->getMessage());
            return false;
        }
    }
    public function obtenerTipos()
    {
        try {
            $sql = 'SELECT "desc" FROM sist_ventas_tipoproduc ORDER BY "desc"';
            $stmt = $this->conexion->query($sql);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo tipos: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerUnidadesMedidaDisponibles()
    {
        try {
            $sql = 'SELECT DISTINCT "desc" FROM sist_ventas_medida ORDER BY "desc"';
            $stmt = $this->conexion->query($sql);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo unidades de medida: " . $e->getMessage());
            return ['Metro', 'Kilogramo', 'Caja', 'Unidad'];
        }
    }


    public function obtenerUnidadesMedidaProducto($idProducto)
    {
        try {
            $sql = 'SELECT "desc" FROM public.sist_ventas_um WHERE id_producto = :id_producto';
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo unidades de medida del producto: " . $e->getMessage());
            return [];
        }
    }

    public function eliminarUnidadesMedidaProducto($idProducto)
    {
        try {
            $sql = "DELETE FROM public.sist_ventas_um WHERE id_producto = :id_producto";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error eliminando unidades de medida: " . $e->getMessage());
            return false;
        }
    }

    public function insertarUnidadMedidaProducto($idProducto, $unidadMedida)
    {
        try {
            $sql = 'INSERT INTO public.sist_ventas_um ("desc", id_producto) VALUES (:desc, :id_producto)';
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':desc', $unidadMedida, PDO::PARAM_STR);
            $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error insertando unidad de medida: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerTiposUnicos()
    {
        try {
            $sql = "SELECT DISTINCT tipo FROM public.sist_ventas_productos 
                    WHERE tipo IS NOT NULL AND tipo != '' 
                    ORDER BY tipo";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo tipos únicos: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerProductosParaCatalogo()
    {
        try {
            $sql = "SELECT id, descripcion, codigobr, tipo, cantidad, ncm, base64img, tipoimg, nombreimg 
                    FROM public.sist_ventas_productos 
                    ORDER BY tipo ASC, descripcion ASC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos para catálogo: " . $e->getMessage());
            return [];
        }
    }

    public function existeDescripcion($descripcion, $idExcluir = null)
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM public.sist_ventas_productos 
                WHERE LOWER(TRIM(descripcion)) = LOWER(TRIM(:descripcion))";

            $params = [':descripcion' => $descripcion];

            if ($idExcluir) {
                $sql .= " AND id != :id_excluir";
                $params[':id_excluir'] = $idExcluir;
            }

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, $key === ':id_excluir' ? PDO::PARAM_INT : PDO::PARAM_STR);
            }

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            return $resultado['total'] > 0;
        } catch (Exception $e) {
            error_log("Error verificando descripción: " . $e->getMessage());
            return false;
        }
    }
}
