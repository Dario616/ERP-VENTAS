<?php

class ProductoService
{
    private $conexion;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
    }

    public function obtenerProductosAgrupadosPorTipo()
    {
        try {
            $query = "SELECT id, descripcion, codigobr, tipo, cantidad, ncm, base64img, tipoimg, nombreimg 
                      FROM public.sist_ventas_productos 
                      ORDER BY tipo ASC, descripcion ASC";

            $stmt = $this->conexion->prepare($query);
            $stmt->execute();
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $productos_por_tipo = [];
            foreach ($productos as $producto) {
                $tipo = $producto['tipo'] ?: 'Sin categoría';
                if (!isset($productos_por_tipo[$tipo])) {
                    $productos_por_tipo[$tipo] = [];
                }
                $productos_por_tipo[$tipo][] = $producto;
            }

            ksort($productos_por_tipo);
            return $productos_por_tipo;
        } catch (PDOException $e) {
            error_log("Error obteniendo productos: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerUnidadesMedida($idProducto)
    {
        try {
            $query = "SELECT \"desc\" FROM public.sist_ventas_um WHERE id_producto = :id_producto";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->execute();

            $unidades = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $unidades[] = $row['desc'];
            }

            if (empty($unidades)) {
                $unidades = ['Unidad', 'Kg', 'Metros', 'Litros'];
            }

            return $unidades;
        } catch (PDOException $e) {
            error_log("Error obteniendo unidades de medida: " . $e->getMessage());
            return ['Unidad', 'Kg', 'Metros', 'Litros'];
        }
    }

    public function obtenerProductoPorId($id)
    {
        try {
            $query = "SELECT * FROM public.sist_ventas_productos WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo producto por ID: " . $e->getMessage());
            return false;
        }
    }

    public function buscarProductos($termino, $tipo = null, $limite = 50)
    {
        try {
            $sql = "SELECT id, descripcion, codigobr, tipo, cantidad, ncm, base64img, tipoimg, nombreimg 
                    FROM public.sist_ventas_productos 
                    WHERE 1=1";

            $params = [];

            if (!empty($termino)) {
                $sql .= " AND (descripcion ILIKE :termino OR codigobr ILIKE :termino OR CAST(id AS TEXT) ILIKE :termino)";
                $params[':termino'] = '%' . $termino . '%';
            }

            if (!empty($tipo)) {
                $sql .= " AND tipo = :tipo";
                $params[':tipo'] = $tipo;
            }

            $sql .= " ORDER BY descripcion ASC LIMIT :limite";

            $stmt = $this->conexion->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error buscando productos: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerTiposProductos()
    {
        try {
            $query = "SELECT DISTINCT tipo FROM public.sist_ventas_productos 
                      WHERE tipo IS NOT NULL AND tipo != '' 
                      ORDER BY tipo ASC";

            $stmt = $this->conexion->prepare($query);
            $stmt->execute();

            $tipos = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $tipos[] = $row['tipo'];
            }

            return $tipos;
        } catch (PDOException $e) {
            error_log("Error obteniendo tipos de productos: " . $e->getMessage());
            return [];
        }
    }

    public function verificarStock($idProducto, $cantidadRequerida)
    {
        try {
            $producto = $this->obtenerProductoPorId($idProducto);

            if (!$producto) {
                return ['disponible' => false, 'mensaje' => 'Producto no encontrado'];
            }

            $stockDisponible = (float)($producto['cantidad'] ?? 0);
            $cantidadRequerida = (float)$cantidadRequerida;

            if ($stockDisponible <= 0) {
                return [
                    'disponible' => false,
                    'mensaje' => 'Producto sin stock',
                    'stock_actual' => $stockDisponible
                ];
            }

            if ($cantidadRequerida > $stockDisponible) {
                return [
                    'disponible' => false,
                    'mensaje' => "Stock insuficiente. Disponible: {$stockDisponible}",
                    'stock_actual' => $stockDisponible
                ];
            }

            return [
                'disponible' => true,
                'mensaje' => 'Stock disponible',
                'stock_actual' => $stockDisponible
            ];
        } catch (Exception $e) {
            error_log("Error verificando stock: " . $e->getMessage());
            return ['disponible' => false, 'mensaje' => 'Error verificando stock'];
        }
    }

    public function obtenerProductosStockBajo($limite = 10)
    {
        try {
            $query = "SELECT id, descripcion, tipo, cantidad 
                      FROM public.sist_ventas_productos 
                      WHERE cantidad > 0 AND cantidad < :limite_stock
                      ORDER BY cantidad ASC";

            $stmt = $this->conexion->prepare($query);
            $stmt->bindValue(':limite_stock', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo productos con stock bajo: " . $e->getMessage());
            return [];
        }
    }

    public function formatearProductoParaMostrar($producto)
    {
        if (!is_array($producto)) {
            return null;
        }

        $cantidad = (float)($producto['cantidad'] ?? 0);

        return [
            'id' => $producto['id'],
            'descripcion' => $producto['descripcion'],
            'tipo' => $producto['tipo'] ?? 'Sin categoría',
            'cantidad_formateada' => number_format($cantidad, 2, ',', '.'),
            'tiene_stock' => $cantidad > 0,
            'stock_bajo' => $cantidad > 0 && $cantidad < 10,
            'codigo' => $producto['codigobr'] ?? '',
            'ncm' => $producto['ncm'] ?? '',
            'imagen_base64' => $producto['base64img'] ?? '',
            'tipo_imagen' => $producto['tipoimg'] ?? '',
            'unidades_medida' => $this->obtenerUnidadesMedida($producto['id'])
        ];
    }

    public function validarProducto($datos)
    {
        $errores = [];

        if (empty($datos['descripcion'])) {
            $errores[] = 'La descripción del producto es obligatoria';
        }

        if (empty($datos['tipo'])) {
            $errores[] = 'El tipo de producto es obligatorio';
        }

        if (isset($datos['cantidad']) && !is_numeric($datos['cantidad'])) {
            $errores[] = 'La cantidad debe ser un número válido';
        }

        if (isset($datos['cantidad']) && (float)$datos['cantidad'] < 0) {
            $errores[] = 'La cantidad no puede ser negativa';
        }

        return $errores;
    }

    public function obtenerEstadisticas()
    {
        try {
            $stats = [];

            $stmt = $this->conexion->query("SELECT COUNT(*) as total FROM public.sist_ventas_productos");
            $stats['total_productos'] = $stmt->fetchColumn();

            $stmt = $this->conexion->query("SELECT COUNT(*) as total FROM public.sist_ventas_productos WHERE cantidad > 0");
            $stats['productos_con_stock'] = $stmt->fetchColumn();

            $stmt = $this->conexion->query("SELECT COUNT(*) as total FROM public.sist_ventas_productos WHERE cantidad <= 0");
            $stats['productos_sin_stock'] = $stmt->fetchColumn();

            $stmt = $this->conexion->query("SELECT COUNT(*) as total FROM public.sist_ventas_productos WHERE cantidad > 0 AND cantidad < 10");
            $stats['productos_stock_bajo'] = $stmt->fetchColumn();

            $stmt = $this->conexion->query("SELECT COUNT(DISTINCT tipo) as total FROM public.sist_ventas_productos WHERE tipo IS NOT NULL");
            $stats['tipos_productos'] = $stmt->fetchColumn();

            return $stats;
        } catch (PDOException $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [
                'total_productos' => 0,
                'productos_con_stock' => 0,
                'productos_sin_stock' => 0,
                'productos_stock_bajo' => 0,
                'tipos_productos' => 0
            ];
        }
    }
}
