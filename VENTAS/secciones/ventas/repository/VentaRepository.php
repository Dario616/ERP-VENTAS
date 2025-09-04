<?php

class VentaRepository
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

    public function obtenerVentas($filtros = [], $limite = 10, $offset = 0, $mostrarTodas = true, $idUsuario = null)
    {
        try {
            $sqlWhere = "";
            $params = [];

            if (!empty($filtros['id_venta'])) {
                $sqlWhere .= " AND v.id = :id_venta";
                $params[':id_venta'] = intval($filtros['id_venta']);
            }
            if (!$mostrarTodas) {
                $sqlWhere .= " AND v.id_usuario = :id_usuario";
                $params[':id_usuario'] = $idUsuario;
            }

            if (!empty($filtros['cliente'])) {
                $sqlWhere .= " AND v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['estado'])) {
                $sqlWhere .= " AND v.estado = :estado";
                $params[':estado'] = $filtros['estado'];
            }

            if (!empty($filtros['cond_pago'])) {
                $sqlWhere .= " AND v.cond_pago = :cond_pago";
                $params[':cond_pago'] = $filtros['cond_pago'];
            }

            $sql = "SELECT v.id, v.cliente, v.tipoflete, v.moneda, 
                v.cond_pago, v.subtotal, v.es_credito, v.estado, v.monto_total, v.id_usuario,
                v.transportadora, v.fecha_venta AS fecha_referencia,
                (SELECT COUNT(*) FROM public.sist_ventas_pres_product WHERE id_presupuesto = v.id) AS num_productos,
                u.nombre as nombre_usuario,
                a.observaciones_contador, a.fecha_respuesta,
                uc.nombre as nombre_contador,
                c.brasil as cliente_brasil
                FROM public.sist_ventas_presupuesto v
                LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                LEFT JOIN public.sist_ventas_autorizaciones a ON v.id = a.id_venta
                LEFT JOIN public.sist_ventas_usuario uc ON a.id_contador = uc.id
                LEFT JOIN public.sist_ventas_clientes c ON v.cliente = c.nombre
                WHERE TRUE $sqlWhere
                ORDER BY v.id DESC
                LIMIT :limit OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo ventas: " . $e->getMessage());
            return [];
        }
    }

    public function contarVentas($filtros = [], $mostrarTodas = true, $idUsuario = null)
    {
        try {
            $sqlWhere = "";
            $params = [];

            // ✅ NUEVO: Filtro por ID de venta
            if (!empty($filtros['id_venta'])) {
                $sqlWhere .= " AND v.id = :id_venta";
                $params[':id_venta'] = intval($filtros['id_venta']);
            }

            if (!$mostrarTodas) {
                $sqlWhere .= " AND v.id_usuario = :id_usuario";
                $params[':id_usuario'] = $idUsuario;
            }

            if (!empty($filtros['cliente'])) {
                $sqlWhere .= " AND v.cliente ILIKE :cliente";
                $params[':cliente'] = '%' . $filtros['cliente'] . '%';
            }

            if (!empty($filtros['estado'])) {
                $sqlWhere .= " AND v.estado = :estado";
                $params[':estado'] = $filtros['estado'];
            }

            if (!empty($filtros['cond_pago'])) {
                $sqlWhere .= " AND v.cond_pago = :cond_pago";
                $params[':cond_pago'] = $filtros['cond_pago'];
            }

            $sqlCount = "SELECT COUNT(*) as total FROM public.sist_ventas_presupuesto v WHERE TRUE $sqlWhere";
            $stmtCount = $this->conexion->prepare($sqlCount);
            foreach ($params as $key => $value) {
                $stmtCount->bindValue($key, $value);
            }
            $stmtCount->execute();

            return $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (Exception $e) {
            error_log("Error contando ventas: " . $e->getMessage());
            return 0;
        }
    }


    public function obtenerVentaPorId($id)
    {
        try {
            $sql = "SELECT * FROM public.sist_ventas_presupuesto WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo venta por ID: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerVentaCompleta($id, $mostrarTodas = true, $idUsuario = null)
    {
        try {
            $sqlVenta = "SELECT v.*, 
                                u.nombre as nombre_vendedor,
                                a.descripcion as descripcion_autorizacion,
                                a.fecha_respuesta as fecha_aprobacion,
                                a.observaciones_contador,
                                uc.nombre as nombre_contador,
                                a.estado_autorizacion
                         FROM public.sist_ventas_presupuesto v
                         LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
                         LEFT JOIN public.sist_ventas_autorizaciones a ON v.id = a.id_venta
                         LEFT JOIN public.sist_ventas_usuario uc ON a.id_contador = uc.id
                         WHERE v.id = :id";

            if (!$mostrarTodas) {
                $sqlVenta .= " AND v.id_usuario = :id_usuario";
            }

            $stmtVenta = $this->conexion->prepare($sqlVenta);
            $stmtVenta->bindParam(':id', $id, PDO::PARAM_INT);
            if (!$mostrarTodas) {
                $stmtVenta->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
            }
            $stmtVenta->execute();

            return $stmtVenta->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo venta completa: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerProductosVenta($idVenta)
    {
        try {
            $sql = "SELECT * FROM public.sist_ventas_pres_product WHERE id_presupuesto = :id_presupuesto ORDER BY id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_presupuesto', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos de venta: " . $e->getMessage());
            return [];
        }
    }


    public function crearVenta($datos)
    {
        try {
            $sql = "INSERT INTO public.sist_ventas_presupuesto 
                    (cliente, tipoflete, moneda, cond_pago, subtotal,
                     es_credito, monto_total, tipocredito, tipo_pago, 
                     fecha_venta, iva, id_usuario, descripcion)
                    VALUES (:cliente, :tipoflete, :moneda, :cond_pago, :subtotal, 
                     :es_credito, :monto_total, :tipocredito, :tipo_pago, 
                     :fecha_venta, :iva, :id_usuario, :descripcion)
                    RETURNING id";

            $stmt = $this->conexion->prepare($sql);

            error_log("DEBUG VentaRepository - Datos para insertar: " . json_encode($datos));

            $stmt->bindParam(':cliente', $datos['cliente'], PDO::PARAM_STR);
            $stmt->bindParam(':tipoflete', $datos['tipoflete'], PDO::PARAM_STR);
            $stmt->bindParam(':moneda', $datos['moneda'], PDO::PARAM_STR);
            $stmt->bindParam(':cond_pago', $datos['cond_pago'], PDO::PARAM_STR);

            $subtotalStr = (string)$datos['subtotal'];
            $montoTotalStr = (string)$datos['monto_total'];
            $ivaStr = (string)$datos['iva'];

            $stmt->bindParam(':subtotal', $subtotalStr, PDO::PARAM_STR);
            $stmt->bindParam(':monto_total', $montoTotalStr, PDO::PARAM_STR);
            $stmt->bindParam(':iva', $ivaStr, PDO::PARAM_STR);

            error_log("DEBUG VentaRepository - Binding decimales: Subtotal: $subtotalStr, Monto Total: $montoTotalStr, IVA: $ivaStr");

            $stmt->bindParam(':es_credito', $datos['es_credito'], PDO::PARAM_BOOL);
            $stmt->bindParam(':tipocredito', $datos['tipocredito'], PDO::PARAM_STR);
            $stmt->bindParam(':tipo_pago', $datos['tipo_pago'], PDO::PARAM_STR);
            $stmt->bindParam(':fecha_venta', $datos['fecha_venta'], PDO::PARAM_STR);
            $stmt->bindParam(':id_usuario', $datos['id_usuario'], PDO::PARAM_INT);
            $stmt->bindParam(':descripcion', $datos['descripcion'], PDO::PARAM_STR);

            $stmt->execute();
            $id = $stmt->fetchColumn();

            error_log("DEBUG VentaRepository - Venta creada con ID: $id");

            return $id;
        } catch (Exception $e) {
            error_log("Error creando venta: " . $e->getMessage());
            error_log("SQL Error Info: " . print_r($stmt->errorInfo(), true));
            return false;
        }
    }

    public function actualizarVenta($id, $datos)
    {
        try {
            $sql = "UPDATE public.sist_ventas_presupuesto SET 
                    cliente = :cliente, 
                    tipoflete = :tipoflete, 
                    moneda = :moneda, 
                    cond_pago = :cond_pago, 
                    subtotal = :subtotal,
                    es_credito = :es_credito, 
                    monto_total = :monto_total, 
                    tipocredito = :tipocredito, 
                    tipo_pago = :tipo_pago, 
                    fecha_venta = :fecha_venta, 
                    iva = :iva, 
                    descripcion = :descripcion
                    WHERE id = :id_presupuesto";

            $stmt = $this->conexion->prepare($sql);

            error_log("DEBUG VentaRepository - Datos para actualizar ID $id: " . json_encode($datos));

            $stmt->bindParam(':cliente', $datos['cliente'], PDO::PARAM_STR);
            $stmt->bindParam(':tipoflete', $datos['tipoflete'], PDO::PARAM_STR);
            $stmt->bindParam(':moneda', $datos['moneda'], PDO::PARAM_STR);
            $stmt->bindParam(':cond_pago', $datos['cond_pago'], PDO::PARAM_STR);

            $subtotalStr = (string)$datos['subtotal'];
            $montoTotalStr = (string)$datos['monto_total'];
            $ivaStr = (string)$datos['iva'];

            $stmt->bindParam(':subtotal', $subtotalStr, PDO::PARAM_STR);
            $stmt->bindParam(':monto_total', $montoTotalStr, PDO::PARAM_STR);
            $stmt->bindParam(':iva', $ivaStr, PDO::PARAM_STR);

            error_log("DEBUG VentaRepository - Actualizando decimales: Subtotal: $subtotalStr, Monto Total: $montoTotalStr, IVA: $ivaStr");

            $stmt->bindParam(':es_credito', $datos['es_credito'], PDO::PARAM_BOOL);
            $stmt->bindParam(':tipocredito', $datos['tipocredito'], PDO::PARAM_STR);
            $stmt->bindParam(':tipo_pago', $datos['tipo_pago'], PDO::PARAM_STR);
            $stmt->bindParam(':descripcion', $datos['descripcion'], PDO::PARAM_STR);
            $stmt->bindParam(':id_presupuesto', $id, PDO::PARAM_INT);
            $stmt->bindParam(':fecha_venta', $datos['fecha_venta'], PDO::PARAM_STR);

            $resultado = $stmt->execute();

            error_log("DEBUG VentaRepository - Actualización " . ($resultado ? 'exitosa' : 'fallida') . " para ID: $id");

            return $resultado;
        } catch (Exception $e) {
            error_log("Error actualizando venta: " . $e->getMessage());
            error_log("SQL Error Info: " . print_r($stmt->errorInfo(), true));
            return false;
        }
    }


    public function eliminarVenta($id)
    {
        try {

            $sqlEliminarHistorial = "DELETE FROM public.sist_ventas_historial_acciones WHERE id_venta = :id";
            $stmtEliminarHistorial = $this->conexion->prepare($sqlEliminarHistorial);
            $stmtEliminarHistorial->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtEliminarHistorial->execute();

            $sqlEliminarImagenes = "DELETE FROM public.sist_ventas_autorizaciones_imagenes 
                               WHERE id_autorizacion IN 
                               (SELECT id FROM public.sist_ventas_autorizaciones WHERE id_venta = :id)";
            $stmtEliminarImagenes = $this->conexion->prepare($sqlEliminarImagenes);
            $stmtEliminarImagenes->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtEliminarImagenes->execute();

            $sqlEliminarAutorizaciones = "DELETE FROM public.sist_ventas_autorizaciones WHERE id_venta = :id";
            $stmtEliminarAutorizaciones = $this->conexion->prepare($sqlEliminarAutorizaciones);
            $stmtEliminarAutorizaciones->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtEliminarAutorizaciones->execute();

            $sqlEliminarProductos = "DELETE FROM public.sist_ventas_pres_product WHERE id_presupuesto = :id";
            $stmtEliminarProductos = $this->conexion->prepare($sqlEliminarProductos);
            $stmtEliminarProductos->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtEliminarProductos->execute();

            $sqlEliminarVenta = "DELETE FROM public.sist_ventas_presupuesto WHERE id = :id";
            $stmtEliminarVenta = $this->conexion->prepare($sqlEliminarVenta);
            $stmtEliminarVenta->bindParam(':id', $id, PDO::PARAM_INT);
            $result = $stmtEliminarVenta->execute();

            if ($result) {
                error_log("DEBUG ELIMINAR - Venta ID $id eliminada exitosamente con todo su historial");
            }

            return $result;
        } catch (Exception $e) {
            error_log("Error eliminando venta: " . $e->getMessage());
            return false;
        }
    }

    public function insertarProductoVenta($datos)
    {
        try {
            $sql = "INSERT INTO public.sist_ventas_pres_product
           (descripcion, id_presupuesto, unidadmedida, ncm, cantidad, precio, total, moneda, tipoproducto, id_producto, instruccion)
             VALUES (:descripcion, :id_presupuesto, :unidadmedida, :ncm, :cantidad, :precio, :total, :moneda, :tipoproducto, :id_producto, :instruccion)";

            $stmt = $this->conexion->prepare($sql);

            $stmt->bindParam(':descripcion', $datos['descripcion'], PDO::PARAM_STR);
            $stmt->bindParam(':id_presupuesto', $datos['id_presupuesto'], PDO::PARAM_INT);
            $stmt->bindParam(':unidadmedida', $datos['unidadmedida'], PDO::PARAM_STR);
            $stmt->bindParam(':ncm', $datos['ncm'], PDO::PARAM_STR);
            $stmt->bindParam(':moneda', $datos['moneda'], PDO::PARAM_STR);
            $stmt->bindParam(':tipoproducto', $datos['tipoproducto'], PDO::PARAM_STR);
            $stmt->bindParam(':id_producto', $datos['id_producto'], PDO::PARAM_INT);
            $stmt->bindParam(':instruccion', $datos['instruccion'], PDO::PARAM_STR);

            $cantidadStr = (string)$datos['cantidad'];
            $precioStr = (string)$datos['precio'];
            $totalStr = (string)$datos['total'];

            $stmt->bindParam(':cantidad', $cantidadStr, PDO::PARAM_STR);
            $stmt->bindParam(':precio', $precioStr, PDO::PARAM_STR);
            $stmt->bindParam(':total', $totalStr, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error insertando producto: " . $e->getMessage());
            return false;
        }
    }

    public function eliminarProductosVenta($idVenta)
    {
        try {
            $sql = "DELETE FROM public.sist_ventas_pres_product WHERE id_presupuesto = :id_presupuesto";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_presupuesto', $idVenta, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error eliminando productos de venta: " . $e->getMessage());
            return false;
        }
    }

    public function actualizarTransportadora($idVenta, $transportadora)
    {
        try {
            $sql = "UPDATE public.sist_ventas_presupuesto 
                    SET transportadora = :transportadora 
                    WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':transportadora', $transportadora);
            $stmt->bindParam(':id', $idVenta, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando transportadora: " . $e->getMessage());
            return false;
        }
    }

    public function actualizarEstadoVenta($idVenta, $estado)
    {
        try {
            $sql = "UPDATE public.sist_ventas_presupuesto SET estado = :estado WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
            $stmt->bindParam(':id', $idVenta, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando estado: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerEstadosVentas($mostrarTodas = true, $idUsuario = null)
    {
        try {
            $sql = "SELECT DISTINCT estado FROM public.sist_ventas_presupuesto 
                    WHERE estado IS NOT NULL";

            if (!$mostrarTodas) {
                $sql .= " AND id_usuario = :id_usuario";
            }

            $sql .= " ORDER BY estado";

            $stmt = $this->conexion->prepare($sql);
            if (!$mostrarTodas) {
                $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estados: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerTiposProductos()
    {
        try {
            $sql = "SELECT DISTINCT tipo FROM public.sist_ventas_productos WHERE tipo IS NOT NULL ORDER BY tipo";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo tipos de productos: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerTiposCredito()
    {
        try {
            $sql = "SELECT id, descripcion FROM public.sist_ventas_cuotas ORDER BY id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo tipos de crédito: " . $e->getMessage());
            return [];
        }
    }

    public function verificarAutorizacionExistente($idVenta)
    {
        try {
            $sql = "SELECT id FROM public.sist_ventas_autorizaciones WHERE id_venta = :id_venta";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error verificando autorización: " . $e->getMessage());
            return false;
        }
    }

    public function crearAutorizacion($datos)
    {
        try {
            $sql = "INSERT INTO public.sist_ventas_autorizaciones 
                    (id_venta, descripcion, id_usuario, estado_autorizacion, fecha_registro) 
                    VALUES (:id_venta, :descripcion, :id_usuario, 'En revision', :fecha_registro)
                    RETURNING id";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $datos['id_venta'], PDO::PARAM_INT);
            $stmt->bindParam(':descripcion', $datos['descripcion'], PDO::PARAM_STR);
            $stmt->bindParam(':fecha_registro', $datos['fecha_registro'], PDO::PARAM_STR);
            $stmt->bindParam(':id_usuario', $datos['id_usuario'], PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error creando autorización: " . $e->getMessage());
            return false;
        }
    }

    public function actualizarAutorizacion($idVenta, $datos)
    {
        try {
            $sql = "UPDATE public.sist_ventas_autorizaciones 
                    SET descripcion = :descripcion,
                        fecha_registro = :fecha_registro,
                        id_usuario = :id_usuario,
                        fecha_respuesta = NULL,
                        observaciones_contador = NULL,
                        id_contador = NULL,
                        estado_autorizacion = 'En revision'
                    WHERE id_venta = :id_venta";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->bindParam(':descripcion', $datos['descripcion'], PDO::PARAM_STR);
            $stmt->bindParam(':fecha_registro', $datos['fecha_registro'], PDO::PARAM_STR);
            $stmt->bindParam(':id_usuario', $datos['id_usuario'], PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error actualizando autorización: " . $e->getMessage());
            return false;
        }
    }

    public function eliminarImagenesAutorizacion($idAutorizacion)
    {
        try {
            $sql = "DELETE FROM public.sist_ventas_autorizaciones_imagenes WHERE id_autorizacion = :id_autorizacion";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_autorizacion', $idAutorizacion, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error eliminando imágenes: " . $e->getMessage());
            return false;
        }
    }


    public function insertarImagenAutorizacion($datos)
    {
        try {
            $sql = "INSERT INTO public.sist_ventas_autorizaciones_imagenes 
                    (id_autorizacion, nombre_archivo, tipo_archivo, imagen, base64_imagen, descripcion_imagen, orden_imagen) 
                    VALUES (:id_autorizacion, :nombre_archivo, :tipo_archivo, :imagen, :base64_imagen, :descripcion_imagen, :orden_imagen)";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_autorizacion', $datos['id_autorizacion'], PDO::PARAM_INT);
            $stmt->bindParam(':nombre_archivo', $datos['nombre_archivo'], PDO::PARAM_STR);
            $stmt->bindParam(':tipo_archivo', $datos['tipo_archivo'], PDO::PARAM_STR);
            $stmt->bindParam(':imagen', $datos['imagen'], PDO::PARAM_LOB);
            $stmt->bindParam(':base64_imagen', $datos['base64_imagen'], PDO::PARAM_STR);
            $stmt->bindParam(':descripcion_imagen', $datos['descripcion_imagen'], PDO::PARAM_STR);
            $stmt->bindParam(':orden_imagen', $datos['orden_imagen'], PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error insertando imagen: " . $e->getMessage());
            return false;
        }
    }

    public function verificarClienteExiste($nombreCliente)
    {
        try {
            $sql = "SELECT COUNT(*) FROM public.sist_ventas_clientes WHERE nombre = :nombre";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':nombre', $nombreCliente, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("Error verificando cliente: " . $e->getMessage());
            return false;
        }
    }


    public function obtenerClientesAutocompletado($termino = '', $limite = 10)
    {
        try {
            $sql = "SELECT nombre FROM public.sist_ventas_clientes WHERE 1=1";
            $params = [];

            if (!empty($termino)) {
                $sql .= " AND nombre ILIKE :termino";
                $params[':termino'] = '%' . $termino . '%';
            }

            $sql .= " ORDER BY nombre LIMIT :limite";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo clientes para autocompletado: " . $e->getMessage());
            return [];
        }
    }

    public function insertarHistorialAccion($datos)
    {
        try {
            error_log("DEBUG HISTORIAL - Datos recibidos: " . json_encode($datos));

            $sql = "INSERT INTO public.sist_ventas_historial_acciones 
                (id_venta, id_usuario, sector, accion, fecha_accion, observaciones, estado_resultante)
                VALUES (:id_venta, :id_usuario, :sector, :accion, :fecha_accion, :observaciones, :estado_resultante)";

            $stmt = $this->conexion->prepare($sql);

            if (!$stmt) {
                error_log("DEBUG HISTORIAL - Error preparando statement: " . print_r($this->conexion->errorInfo(), true));
                return false;
            }

            $result = true;
            $result &= $stmt->bindParam(':id_venta', $datos['id_venta'], PDO::PARAM_INT);
            $result &= $stmt->bindParam(':id_usuario', $datos['id_usuario'], PDO::PARAM_INT);
            $result &= $stmt->bindParam(':sector', $datos['sector'], PDO::PARAM_STR);
            $result &= $stmt->bindParam(':accion', $datos['accion'], PDO::PARAM_STR);
            $result &= $stmt->bindParam(':fecha_accion', $datos['fecha_accion'], PDO::PARAM_STR);
            $result &= $stmt->bindParam(':observaciones', $datos['observaciones'], PDO::PARAM_STR);
            $result &= $stmt->bindParam(':estado_resultante', $datos['estado_resultante'], PDO::PARAM_STR);

            if (!$result) {
                error_log("DEBUG HISTORIAL - Error en binding de parámetros");
                return false;
            }

            $executeResult = $stmt->execute();

            if ($executeResult) {
                error_log("DEBUG HISTORIAL - ✅ Historial insertado correctamente para venta ID: " . $datos['id_venta']);
            } else {
                error_log("DEBUG HISTORIAL - ❌ Error ejecutando insert: " . print_r($stmt->errorInfo(), true));
            }

            return $executeResult;
        } catch (PDOException $e) {
            error_log("DEBUG HISTORIAL - PDOException: " . $e->getMessage());
            error_log("DEBUG HISTORIAL - SQL State: " . $e->getCode());
            return false;
        } catch (Exception $e) {
            error_log("DEBUG HISTORIAL - Exception general: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerHistorialAcciones($filtros = [], $limite = 10, $offset = 0, $mostrarTodo = true, $idUsuarioActual = null)
    {
        try {
            $whereConditions = ["h.sector = 'Ventas'"];
            $params = [];

            if (!$mostrarTodo && $idUsuarioActual) {
                $whereConditions[] = "h.id_usuario = :id_usuario_actual";
                $params[':id_usuario_actual'] = $idUsuarioActual;
            }

            if (!empty($filtros['id_venta'])) {
                $whereConditions[] = "v.id::text ILIKE :id_venta";
                $params[':id_venta'] = '%' . $filtros['id_venta'] . '%';
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "h.fecha_accion >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "h.fecha_accion <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }

            if (!empty($filtros['cliente_historial'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente_historial";
                $params[':cliente_historial'] = '%' . $filtros['cliente_historial'] . '%';
            }

            if (!empty($filtros['id_usuario']) && $mostrarTodo) {
                $whereConditions[] = "h.id_usuario = :id_usuario_filtro";
                $params[':id_usuario_filtro'] = $filtros['id_usuario'];
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "SELECT h.id, h.id_venta, h.sector, h.accion, h.fecha_accion, h.observaciones, h.estado_resultante,
            v.cliente, v.moneda, v.monto_total,
            u.nombre as nombre_usuario
            FROM public.sist_ventas_historial_acciones h
            JOIN public.sist_ventas_presupuesto v ON h.id_venta = v.id
            JOIN public.sist_ventas_usuario u ON h.id_usuario = u.id
            WHERE {$whereClause}
            ORDER BY h.fecha_accion DESC
            LIMIT :limit OFFSET :offset";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo historial de acciones: " . $e->getMessage());
            return [];
        }
    }

    public function contarHistorialAcciones($filtros = [], $mostrarTodo = true, $idUsuarioActual = null)
    {
        try {
            $whereConditions = ["h.sector = 'Ventas'"];
            $params = [];

            if (!$mostrarTodo && $idUsuarioActual) {
                $whereConditions[] = "h.id_usuario = :id_usuario_actual";
                $params[':id_usuario_actual'] = $idUsuarioActual;
            }

            if (!empty($filtros['id_venta'])) {
                $whereConditions[] = "v.id::text ILIKE :id_venta";
                $params[':id_venta'] = '%' . $filtros['id_venta'] . '%';
            }

            if (!empty($filtros['fecha_desde'])) {
                $whereConditions[] = "h.fecha_accion >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
            }

            if (!empty($filtros['fecha_hasta'])) {
                $whereConditions[] = "h.fecha_accion <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
            }

            if (!empty($filtros['cliente_historial'])) {
                $whereConditions[] = "v.cliente ILIKE :cliente_historial";
                $params[':cliente_historial'] = '%' . $filtros['cliente_historial'] . '%';
            }

            if (!empty($filtros['id_usuario']) && $mostrarTodo) {
                $whereConditions[] = "h.id_usuario = :id_usuario_filtro";
                $params[':id_usuario_filtro'] = $filtros['id_usuario'];
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "SELECT COUNT(*) as total
            FROM public.sist_ventas_historial_acciones h
            JOIN public.sist_ventas_presupuesto v ON h.id_venta = v.id
            JOIN public.sist_ventas_usuario u ON h.id_usuario = u.id
            WHERE {$whereClause}";

            $stmt = $this->conexion->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'];
        } catch (PDOException $e) {
            error_log("Error contando historial de acciones: " . $e->getMessage());
            return 0;
        }
    }
}
