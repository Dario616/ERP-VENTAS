<?php

class VentaService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function obtenerVentasPaginadas($filtros, $registrosPorPagina, $paginaActual, $mostrarTodas, $idUsuarioActual)
    {
        $inicio = ($paginaActual - 1) * $registrosPorPagina;

        $ventas = $this->repository->obtenerVentas(
            $filtros,
            $registrosPorPagina,
            $inicio,
            $mostrarTodas,
            $idUsuarioActual
        );

        $totalRegistros = $this->repository->contarVentas($filtros, $mostrarTodas, $idUsuarioActual);
        $totalPaginas = ceil($totalRegistros / $registrosPorPagina);

        return [
            'ventas' => $ventas,
            'totalRegistros' => $totalRegistros,
            'totalPaginas' => $totalPaginas,
            'paginaActual' => $paginaActual
        ];
    }

    public function obtenerVentaPorId($id, $mostrarTodas = true, $idUsuario = null)
    {
        if (!$this->validarId($id)) {
            throw new Exception('ID de venta inválido');
        }

        $venta = $this->repository->obtenerVentaCompleta($id, $mostrarTodas, $idUsuario);

        if (!$venta) {
            throw new Exception('Venta no encontrada o sin permisos');
        }

        return $venta;
    }


    public function obtenerVentaSimple($id)
    {
        if (!$this->validarId($id)) {
            throw new Exception('ID de venta inválido');
        }

        $venta = $this->repository->obtenerVentaPorId($id);

        if (!$venta) {
            throw new Exception('Venta no encontrada');
        }

        return $venta;
    }


    public function obtenerProductosVenta($idVenta)
    {
        if (!$this->validarId($idVenta)) {
            throw new Exception('ID de venta inválido');
        }

        return $this->repository->obtenerProductosVenta($idVenta);
    }

    public function crearVenta($datos, $productos, $idUsuario)
    {
        // Validar datos de entrada
        $errores = $this->validarDatosVenta($datos, $productos);

        if (!empty($errores)) {
            return ['success' => false, 'errores' => $errores];
        }

        try {
            $this->repository->getConexion()->beginTransaction();

            $nombreCliente = $this->obtenerNombreCliente($datos['cliente']);

            $datosVenta = $this->prepararDatosVenta($datos, $productos, $nombreCliente, $idUsuario);

            $idVenta = $this->repository->crearVenta($datosVenta);

            if (!$idVenta) {
                throw new Exception('Error al crear la venta');
            }

            $this->insertarProductosVenta($idVenta, $productos, $datos['moneda']);

            $this->repository->getConexion()->commit();

            $this->registrarHistorial($idVenta, $idUsuario, 'Crear', 'Presupuesto creado', 'Pendiente');

            return [
                'success' => true,
                'mensaje' => 'Presupuesto registrado correctamente',
                'id_venta' => $idVenta
            ];
        } catch (Exception $e) {
            $this->repository->getConexion()->rollBack();
            error_log("Error en crearVenta: " . $e->getMessage());
            return ['success' => false, 'errores' => ['Error al registrar la venta: ' . $e->getMessage()]];
        }
    }

    public function actualizarVenta($id, $datos, $productos, $idUsuario)
    {
        if (!$this->validarId($id)) {
            return ['success' => false, 'errores' => ['ID de venta inválido']];
        }

        $errores = $this->validarDatosVenta($datos, $productos);

        if (!empty($errores)) {
            return ['success' => false, 'errores' => $errores];
        }

        try {
            $this->repository->getConexion()->beginTransaction();

            $nombreCliente = $this->obtenerNombreCliente($datos['cliente']);

            $datosVenta = $this->prepararDatosVenta($datos, $productos, $nombreCliente, $idUsuario);

            if (!$this->repository->actualizarVenta($id, $datosVenta)) {
                throw new Exception('Error al actualizar la venta');
            }

            $this->repository->eliminarProductosVenta($id);

            $this->insertarProductosVenta($id, $productos, $datos['moneda']);

            $this->repository->getConexion()->commit();

            $this->registrarHistorial($id, $idUsuario, 'Editar', 'Presupuesto editado', 'Pendiente');

            return [
                'success' => true,
                'mensaje' => 'Presupuesto actualizado correctamente'
            ];
        } catch (Exception $e) {
            $this->repository->getConexion()->rollBack();
            error_log("Error en actualizarVenta: " . $e->getMessage());
            return ['success' => false, 'errores' => ['Error al actualizar la venta: ' . $e->getMessage()]];
        }
    }

    public function eliminarVenta($id)
    {
        if (!$this->validarId($id)) {
            return ['success' => false, 'error' => 'ID de venta inválido'];
        }

        try {
            $this->repository->getConexion()->beginTransaction();

            if (!$this->repository->eliminarVenta($id)) {
                throw new Exception('Error al eliminar la venta');
            }

            $this->repository->getConexion()->commit();

            $idUsuarioHistorial = $_SESSION['id'] ?? 1;
            $this->registrarHistorial($id, $idUsuarioHistorial, 'Eliminar', 'Presupuesto eliminado', 'Eliminado');

            return ['success' => true, 'mensaje' => 'Venta eliminada correctamente'];
        } catch (Exception $e) {
            $this->repository->getConexion()->rollBack();
            error_log("Error en eliminarVenta: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al eliminar la venta: ' . $e->getMessage()];
        }
    }

    public function actualizarTransportadora($idVenta, $transportadora)
    {
        if (!$this->validarId($idVenta)) {
            return ['success' => false, 'error' => 'ID de venta inválido'];
        }

        try {
            if (!$this->repository->actualizarTransportadora($idVenta, $transportadora)) {
                throw new Exception('Error al actualizar la transportadora');
            }

            $idUsuarioHistorial = $_SESSION['id'] ?? 1;
            $observaciones = "Transportadora actualizada: " . $transportadora;
            $this->registrarHistorial($idVenta, $idUsuarioHistorial, 'Actualizar transportadora', $observaciones, 'Pendiente');

            return ['success' => true, 'mensaje' => 'Transportadora actualizada correctamente'];
        } catch (Exception $e) {
            error_log("Error en actualizarTransportadora: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al actualizar la transportadora: ' . $e->getMessage()];
        }
    }

    public function procesarAutorizacion($idVenta, $descripcion, $archivos, $idUsuario)
    {
        if (!$this->validarId($idVenta)) {
            return ['success' => false, 'error' => 'ID de venta inválido'];
        }

        try {
            $this->repository->getConexion()->beginTransaction();

            $fechaParaguay = date('Y-m-d H:i:s');

            $this->repository->actualizarEstadoVenta($idVenta, 'En revision');

            $autorizacionExistente = $this->repository->verificarAutorizacionExistente($idVenta);

            $idAutorizacion = null;

            if ($autorizacionExistente) {
                $idAutorizacion = $autorizacionExistente['id'];

                $datosAutorizacion = [
                    'descripcion' => $descripcion,
                    'fecha_registro' => $fechaParaguay,
                    'id_usuario' => $idUsuario
                ];

                $this->repository->actualizarAutorizacion($idVenta, $datosAutorizacion);

                $this->repository->eliminarImagenesAutorizacion($idAutorizacion);
            } else {
                $datosAutorizacion = [
                    'id_venta' => $idVenta,
                    'descripcion' => $descripcion,
                    'fecha_registro' => $fechaParaguay,
                    'id_usuario' => $idUsuario
                ];

                $idAutorizacion = $this->repository->crearAutorizacion($datosAutorizacion);
            }

            if (!empty($archivos) && $idAutorizacion) {
                $this->procesarArchivosAutorizacion($idAutorizacion, $archivos);
            }

            $this->repository->getConexion()->commit();

            $this->registrarHistorial($idVenta, $idUsuario, 'Enviar al sector contable', $descripcion, 'En revision');

            return ['success' => true, 'mensaje' => 'Venta enviada al sector contable correctamente'];
        } catch (Exception $e) {
            $this->repository->getConexion()->rollBack();
            error_log("Error en procesarAutorizacion: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al procesar la autorización: ' . $e->getMessage()];
        }
    }

    public function obtenerEstadosVentas($mostrarTodas = true, $idUsuario = null)
    {
        return $this->repository->obtenerEstadosVentas($mostrarTodas, $idUsuario);
    }

    public function obtenerTiposProductos()
    {
        return $this->repository->obtenerTiposProductos();
    }


    public function obtenerTiposCredito()
    {
        return $this->repository->obtenerTiposCredito();
    }

    private function obtenerNombreCliente($idONombreCliente)
    {
        if (is_numeric($idONombreCliente)) {
            try {
                $conexion = $this->repository->getConexion();
                $query = "SELECT nombre FROM public.sist_ventas_clientes WHERE id = :id";
                $stmt = $conexion->prepare($query);
                $stmt->bindParam(':id', $idONombreCliente, PDO::PARAM_INT);
                $stmt->execute();
                $nombreCliente = $stmt->fetchColumn();

                if (!$nombreCliente) {
                    error_log("Cliente con ID {$idONombreCliente} no encontrado");
                    return $idONombreCliente;
                }

                return $nombreCliente;
            } catch (Exception $e) {
                error_log("Error obteniendo nombre del cliente: " . $e->getMessage());
                return $idONombreCliente;
            }
        }
        return $idONombreCliente;
    }

    private function validarDatosVenta($datos, $productos)
    {
        $errores = [];

        if (empty($datos['cliente'])) {
            $errores[] = "El cliente es obligatorio.";
        }

        if (!isset($productos) || !is_array($productos) || empty($productos)) {
            $errores[] = "Debe agregar al menos un producto.";
        }
        if (is_array($productos)) {
            foreach ($productos as $index => $producto) {
                if (empty($producto['id_producto']) || empty($producto['descripcion'])) {
                    $errores[] = "Producto en posición " . ($index + 1) . " tiene datos incompletos.";
                }

                $cantidad = floatval($producto['cantidad'] ?? 0);
                $precio = floatval($producto['precio'] ?? 0);

                if ($cantidad <= 0) {
                    $errores[] = "Cantidad inválida en producto: " . ($producto['descripcion'] ?? 'Desconocido');
                }

                if ($precio < 0) {
                    $errores[] = "Precio inválido en producto: " . ($producto['descripcion'] ?? 'Desconocido');
                }
            }
            $idsProductos = array_column($productos, 'id_producto');
            $idsUnicos = array_unique($idsProductos);

            if (count($idsProductos) !== count($idsUnicos)) {
                $errores[] = "Se detectaron productos duplicados en los datos recibidos.";
            }
        }

        if (empty($datos['moneda'])) {
            $errores[] = "La moneda es obligatoria.";
        }

        $camposObligatorios = ['tipoflete', 'cond_pago', 'tipo_pago'];
        foreach ($camposObligatorios as $campo) {
            if (empty($datos[$campo])) {
                $errores[] = "El campo $campo es obligatorio.";
            }
        }

        return $errores;
    }

    private function validarId($id)
    {
        return is_numeric($id) && $id > 0;
    }


    private function prepararDatosVenta($datos, $productos, $nombreCliente, $idUsuario)
    {
        $aplicarIva = isset($datos['aplicar_iva']) && $datos['aplicar_iva'] === '1';
        $porcentajeIva = $aplicarIva ? 10 : 0;
        $totales = $this->calcularTotalesVenta($productos, $aplicarIva);

        error_log("DEBUG VentaService - Totales calculados: Subtotal: " . $totales['subtotal'] . ", Total final: " . $totales['total_final'] . ", IVA aplicado: " . ($aplicarIva ? 'Sí' : 'No'));

        $esCredito = ($datos['cond_pago'] === 'Crédito');
        $tipoCredito = $esCredito ? ($datos['tipocredito'] ?? null) : null;

        return [
            'cliente' => $nombreCliente,
            'tipoflete' => $datos['tipoflete'],
            'moneda' => $datos['moneda'],
            'cond_pago' => $datos['cond_pago'],
            'subtotal' => $totales['subtotal'],
            'es_credito' => $esCredito,
            'monto_total' => $totales['total_final'],
            'tipocredito' => $tipoCredito,
            'tipo_pago' => $datos['tipo_pago'],
            'fecha_venta' => !empty($datos['fecha_venta']) ? $datos['fecha_venta'] : date('Y-m-d'),
            'iva' => $porcentajeIva,
            'id_usuario' => $idUsuario,
            'descripcion' => $datos['descripcion'] ?? ''
        ];
    }

    private function calcularTotalesVenta($productos, $aplicarIva)
    {
        $totalGeneral = 0;

        error_log("DEBUG VentaService - Calculando totales para " . count($productos) . " productos");

        foreach ($productos as $index => $producto) {
            $cantidad = (float)($producto['cantidad'] ?? 0);
            $precio = (float)($producto['precio'] ?? 0);
            $totalProducto = $precio * $cantidad;
            $totalGeneral += $totalProducto;

            error_log("DEBUG VentaService - Producto {$index}: Cantidad: {$cantidad}, Precio: {$precio}, Total: {$totalProducto}");
        }

        error_log("DEBUG VentaService - Total general antes de IVA: {$totalGeneral}");

        if ($aplicarIva) {
            $subtotal = $totalGeneral / 1.10;
            $totalFinal = $totalGeneral;
        } else {
            $subtotal = $totalGeneral;
            $totalFinal = $totalGeneral;
        }

        error_log("DEBUG VentaService - Subtotal final: {$subtotal}, Total final: {$totalFinal}");

        return [
            'subtotal' => round($subtotal, 4),
            'total_final' => round($totalFinal, 4)
        ];
    }

    private function insertarProductosVenta($idVenta, $productos, $moneda)
    {
        $monedaCodigo = match ($moneda) {
            'Dólares' => 'USD',
            'Real brasileño' => 'BRL',
            default => 'PYG'
        };

        foreach ($productos as $producto) {
            $cantidad = floatval($producto['cantidad'] ?? 0);
            $precio = floatval($producto['precio'] ?? 0);
            $total = $cantidad * $precio;

            $datosProducto = [
                'descripcion' => trim($producto['descripcion']),
                'id_presupuesto' => $idVenta,
                'unidadmedida' => trim($producto['unidad_medida'] ?? ''),
                'ncm' => trim($producto['ncm'] ?? ''),
                'instruccion' => trim($producto['instruccion'] ?? ''),
                'cantidad' => $cantidad,
                'precio' => $precio,
                'total' => $total,
                'moneda' => $monedaCodigo,
                'tipoproducto' => trim($producto['tipo_producto'] ?? ''),
                'id_producto' => intval($producto['id_producto'])
            ];

            if (!$this->repository->insertarProductoVenta($datosProducto)) {
                throw new Exception("Error al insertar producto: " . $producto['descripcion']);
            }
        }
    }

    private function procesarArchivosAutorizacion($idAutorizacion, $archivos)
    {
        if (!isset($archivos['imagenes']) || !is_array($archivos['imagenes']['name'])) {
            return;
        }

        $totalImagenes = count($archivos['imagenes']['name']);

        for ($i = 0; $i < $totalImagenes; $i++) {
            if ($archivos['imagenes']['error'][$i] === UPLOAD_ERR_OK) {
                $nombreimg = $archivos['imagenes']['name'][$i];
                $tipoimg = $archivos['imagenes']['type'][$i];
                $tmpName = $archivos['imagenes']['tmp_name'][$i];
                $descripcionImagen = $_POST['descripcion_imagen'][$i] ?? '';

                $extension = strtolower(pathinfo($nombreimg, PATHINFO_EXTENSION));
                $tiposPermitidos = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

                if (in_array($extension, $tiposPermitidos)) {
                    $imgData = file_get_contents($tmpName);
                    $base64img = base64_encode($imgData);

                    $datosImagen = [
                        'id_autorizacion' => $idAutorizacion,
                        'nombre_archivo' => $nombreimg,
                        'tipo_archivo' => $tipoimg,
                        'imagen' => $imgData,
                        'base64_imagen' => $base64img,
                        'descripcion_imagen' => $descripcionImagen,
                        'orden_imagen' => $i + 1
                    ];

                    if (!$this->repository->insertarImagenAutorizacion($datosImagen)) {
                        throw new Exception("Error al insertar imagen: $nombreimg");
                    }
                }
            }
        }
    }

    public function formatearNumeroSegunMoneda($numero, $moneda)
    {
        $numero = floatval($numero);
        $formateado = number_format($numero, 4, ',', '.');
        $formateado = preg_replace('/(,\d*?)0+$/', '$1', $formateado);
        $formateado = rtrim($formateado, ',');
        return $formateado;
    }

    public function obtenerSimboloMoneda($moneda)
    {
        switch ($moneda) {
            case 'Dólares':
                return 'USD';
            case 'Real brasileño':
                return 'R$';
            case 'Guaraníes':
            default:
                return '₲';
        }
    }

    public function formatearParaMostrar($numero, $decimales = 4)
    {
        if (!$numero) return '0';
        $formateado = number_format((float)$numero, $decimales, ',', '.');
        // Eliminar ceros del final
        $formateado = rtrim($formateado, '0');
        // Eliminar la coma si queda sola
        $formateado = rtrim($formateado, ',');
        return $formateado;
    }

    private function registrarHistorial($idVenta, $idUsuario, $accion, $observaciones = '', $estadoResultante = 'Pendiente')
    {
        try {
            error_log("DEBUG HISTORIAL SERVICE - Intentando registrar: Venta ID: $idVenta, Usuario: $idUsuario, Acción: $accion");

            if (!method_exists($this->repository, 'insertarHistorialAccion')) {
                error_log("DEBUG HISTORIAL SERVICE - ❌ ERROR: Método insertarHistorialAccion no existe en VentaRepository");
                return false;
            }

            $datos = [
                'id_venta' => (int)$idVenta,
                'id_usuario' => (int)$idUsuario,
                'sector' => 'Ventas',
                'accion' => $accion,
                'fecha_accion' => date('Y-m-d H:i:s'),
                'observaciones' => $observaciones,
                'estado_resultante' => $estadoResultante
            ];

            error_log("DEBUG HISTORIAL SERVICE - Datos preparados: " . json_encode($datos));

            $resultado = $this->repository->insertarHistorialAccion($datos);

            if ($resultado) {
                error_log("DEBUG HISTORIAL SERVICE - ✅ Historial registrado exitosamente");
            } else {
                error_log("DEBUG HISTORIAL SERVICE - ❌ Error registrando historial");
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("DEBUG HISTORIAL SERVICE - Exception: " . $e->getMessage());
            error_log("DEBUG HISTORIAL SERVICE - Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    public function obtenerHistorialAccionesPaginado($filtros, $registrosPorPagina, $paginaActual, $idUsuarioActual, $rolUsuario)
    {
        $esAdministrador = ($rolUsuario === '1');
        $inicio = ($paginaActual - 1) * $registrosPorPagina;

        $acciones = $this->repository->obtenerHistorialAcciones(
            $filtros,
            $registrosPorPagina,
            $inicio,
            $esAdministrador,
            $idUsuarioActual
        );

        $totalRegistros = $this->repository->contarHistorialAcciones(
            $filtros,
            $esAdministrador,
            $idUsuarioActual
        );

        $totalPaginas = ceil($totalRegistros / $registrosPorPagina);

        return [
            'acciones' => $acciones,
            'totalRegistros' => $totalRegistros,
            'totalPaginas' => $totalPaginas,
            'paginaActual' => $paginaActual,
            'esAdministrador' => $esAdministrador
        ];
    }
}
