<?php


class ContableService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function obtenerAutorizacionesPendientes($filtros = [], $pagina = 1, $registrosPorPagina = 10)
    {
        $offset = ($pagina - 1) * $registrosPorPagina;

        $autorizaciones = $this->repository->obtenerAutorizacionesPendientes($filtros, $registrosPorPagina, $offset);
        $total = $this->repository->contarAutorizacionesPendientes($filtros);

        return [
            'autorizaciones' => array_map([$this, 'enriquecerDatosAutorizacion'], $autorizaciones),
            'total' => $total,
            'total_paginas' => ceil($total / $registrosPorPagina),
            'pagina_actual' => $pagina
        ];
    }


    public function obtenerVentaParaRevision($idVenta)
    {
        $venta = $this->repository->obtenerVentaPorId($idVenta);

        if (!$venta) {
            throw new Exception('Venta no encontrada o ya procesada');
        }

        $productos = $this->repository->obtenerProductosVenta($idVenta);

        $imagenesAutorizacion = [];
        if ($venta['id_autorizacion']) {
            $imagenesAutorizacion = $this->repository->obtenerImagenesAutorizacion($venta['id_autorizacion']);
        }

        $imagenesProductos = $this->obtenerImagenesProductosOrganizadas($productos);

        $fueRechazada = $this->repository->ventaFueRechazada($idVenta);

        return [
            'venta' => $this->enriquecerDatosVenta($venta),
            'productos' => $productos,
            'imagenes_autorizacion' => $imagenesAutorizacion,
            'imagenes_productos' => $imagenesProductos,
            'fue_rechazada' => $fueRechazada
        ];
    }

    public function procesarAccionAutorizacion($idVenta, $accion, $datos, $idUsuario)
    {
        if (!in_array($accion, ['rechazar', 'enviar_pcp'])) {
            return ['success' => false, 'error' => 'Acción no válida'];
        }

        if ($accion === 'rechazar') {
            $errores = $this->validarDatosRechazo($datos);
            if (!empty($errores)) {
                return ['success' => false, 'errores' => $errores];
            }
        }

        try {
            $this->repository->beginTransaction();
            $fechaRespuesta = date('Y-m-d H:i:s');

            if ($accion === 'rechazar') {
                $estadoAutorizacion = 'Rechazado';
                $nuevoEstadoVenta = 'Rechazado';
                $observaciones = $datos['descripcion_rechazo'];
                $accionHistorial = 'Rechazar';
            } else {
                $estadoAutorizacion = 'Enviado a PCP';
                $nuevoEstadoVenta = 'Enviado a PCP';
                $observaciones = $datos['observaciones_pcp'] ?? '';
                $accionHistorial = 'Aprobar';
            }

            if (!$this->repository->actualizarEstadoVenta($idVenta, $nuevoEstadoVenta)) {
                throw new Exception("No se pudo actualizar el estado de la venta");
            }

            $datosAutorizacion = [
                'fecha_respuesta' => $fechaRespuesta,
                'observaciones_contador' => $observaciones,
                'id_contador' => $idUsuario,
                'estado_autorizacion' => $estadoAutorizacion
            ];

            if (!$this->repository->actualizarAutorizacion($idVenta, $datosAutorizacion)) {
                throw new Exception("No se pudo actualizar el registro de autorización");
            }

            $datosHistorial = [
                'id_venta' => $idVenta,
                'id_usuario' => $idUsuario,
                'sector' => 'Contable',
                'accion' => $accionHistorial,
                'fecha_accion' => $fechaRespuesta,
                'observaciones' => $observaciones,
                'estado_resultante' => $nuevoEstadoVenta
            ];

            if (!$this->repository->insertarHistorialAccion($datosHistorial)) {
                throw new Exception("No se pudo insertar el registro en el historial");
            }

            // SI LA VENTA ES APROBADA Y ES A CRÉDITO, GENERAR CUOTAS AUTOMÁTICAMENTE
            if ($accion === 'enviar_pcp') {
                $this->generarCuotasVentaAprobada($idVenta);
            }

            $this->repository->commit();

            $mensaje = $accion === 'rechazar'
                ? 'Venta rechazada correctamente'
                : 'Venta enviada al sector PCP correctamente';

            return ['success' => true, 'mensaje' => $mensaje];
        } catch (Exception $e) {
            $this->repository->rollBack();
            error_log("Error procesando acción de autorización: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al procesar la acción: ' . $e->getMessage()];
        }
    }

    /**
     * Genera cuotas automáticamente para ventas a crédito aprobadas
     * Verifica primero si ya existen cuotas para evitar duplicados
     */
    private function generarCuotasVentaAprobada($idVenta)
    {
        try {
            // PASO 1: Verificar si ya existen cuotas para esta venta
            if ($this->verificarCuotasExistentes($idVenta)) {
                error_log("Las cuotas para la venta $idVenta ya fueron generadas previamente. Se omite la generación.");
                return; // Salir sin generar cuotas
            }

            // Obtener datos de la venta
            $venta = $this->repository->obtenerVentaCompletaPorId($idVenta);

            if (!$venta) {
                throw new Exception("Venta no encontrada");
            }

            // Solo generar cuotas si es a crédito y tiene tipo de crédito definido
            if ($venta['es_credito'] && !empty($venta['tipocredito'])) {
                // Incluir el repository de cuentas por cobrar si no está cargado
                if (!class_exists('CuentasCobrarRepository')) {
                    require_once 'repository/CuentasCobrarRepository.php';
                }

                if (!class_exists('CuentasCobrarService')) {
                    require_once 'services/CuentasCobrarService.php';
                }

                // Usar el getter method para obtener la conexión
                $cuentasCobrarRepo = new CuentasCobrarRepository($this->repository->getConexion());
                $cuentasCobrarService = new CuentasCobrarService($cuentasCobrarRepo);

                // Generar cuotas SIN manejar transacción (ya hay una activa)
                $resultado = $cuentasCobrarService->procesarVentaAprobada(
                    $idVenta,
                    $venta['monto_total'],
                    $venta['tipocredito'],
                    $venta['fecha_venta'],
                    false // NO manejar transacción propia
                );

                if (!$resultado['success']) {
                    error_log("Error generando cuotas para venta $idVenta: " . $resultado['error']);
                    // No lanzar excepción para no afectar el flujo principal
                } else {
                    error_log("Cuotas generadas exitosamente para venta $idVenta");
                }
            } else {
                error_log("La venta $idVenta no es a crédito o no tiene tipo de crédito definido. No se generan cuotas.");
            }
        } catch (Exception $e) {
            error_log("Error en generarCuotasVentaAprobada: " . $e->getMessage());
            // No lanzar excepción para no afectar el flujo principal
        }
    }

    /**
     * Verifica si ya existen cuotas generadas para una venta
     * @param int $idVenta ID de la venta a verificar
     * @return bool true si ya existen cuotas, false si no existen
     */
    private function verificarCuotasExistentes($idVenta)
    {
        try {
            // Consulta directa para verificar si existen cuotas
            $sql = "SELECT COUNT(*) as total FROM public.sist_ventas_cuentas_cobrar WHERE id_venta = :id_venta";
            $stmt = $this->repository->getConexion()->prepare($sql);
            $stmt->bindValue(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalCuotas = (int)$resultado['total'];

            return $totalCuotas > 0;
        } catch (Exception $e) {
            error_log("Error verificando cuotas existentes para venta $idVenta: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerEstadisticas()
    {
        return $this->repository->obtenerEstadisticas();
    }

    public function obtenerHistorialAcciones($filtros = [], $pagina = 1, $registrosPorPagina = 10)
    {
        $this->repository->verificarCrearTablaHistorial();

        $offset = ($pagina - 1) * $registrosPorPagina;

        $historial = $this->repository->obtenerHistorialAcciones($filtros, $registrosPorPagina, $offset);
        $total = $this->repository->contarHistorialAcciones($filtros);

        return [
            'historial' => array_map([$this, 'enriquecerDatosHistorial'], $historial),
            'total' => $total,
            'total_paginas' => ceil($total / $registrosPorPagina),
            'pagina_actual' => $pagina
        ];
    }


    public function validarPermisos($rol)
    {
        return in_array($rol, ['1', '3']);
    }


    private function validarDatosRechazo($datos)
    {
        $errores = [];

        if (empty(trim($datos['descripcion_rechazo'] ?? ''))) {
            $errores[] = 'El motivo del rechazo es obligatorio';
        }

        return $errores;
    }


    private function enriquecerDatosAutorizacion($autorizacion)
    {
        $autorizacion['simbolo_moneda'] = $autorizacion['moneda'] === 'Dólares'
            ? 'U$D '
            : ($autorizacion['moneda'] === 'Real brasileño' ? 'R$ ' : '₲ ');

        $autorizacion['monto_formateado'] = number_format((float)$autorizacion['monto_total'], 2, ',', '.');

        if (!empty($autorizacion['fecha_autorizacion'])) {
            $autorizacion['fecha_autorizacion_formateada'] = date('d/m/Y H:i', strtotime($autorizacion['fecha_autorizacion']));
        }

        if (!empty($autorizacion['fecha_venta'])) {
            $autorizacion['fecha_venta_formateada'] = date('d/m/Y', strtotime($autorizacion['fecha_venta']));
        }

        return $autorizacion;
    }

    private function enriquecerDatosVenta($venta)
    {
        $venta['simbolo_moneda'] = $venta['moneda'] === 'Dólares'
            ? 'U$D '
            : ($venta['moneda'] === 'Real brasileño' ? 'R$ ' : '₲ ');


        if (!empty($venta['fecha_venta'])) {
            $venta['fecha_venta_formateada'] = date('d/m/Y', strtotime($venta['fecha_venta']));
        }

        if (!empty($venta['fecha_solicitud_autorizacion'])) {
            $venta['fecha_solicitud_formateada'] = date('d/m/Y H:i', strtotime($venta['fecha_solicitud_autorizacion']));
        }

        return $venta;
    }


    private function enriquecerDatosHistorial($historial)
    {
        if (!empty($historial['fecha_accion'])) {
            $historial['fecha_accion_formateada'] = date('d/m/Y H:i', strtotime($historial['fecha_accion']));
        }

        $historial['accion_badge'] = $this->obtenerBadgeAccion($historial['accion']);
        $historial['estado_badge'] = $this->obtenerBadgeEstado($historial['estado_resultante']);

        return $historial;
    }


    private function obtenerImagenesProductosOrganizadas($productos)
    {
        $imagenesProductos = [];

        $mapeoProductos = [];
        foreach ($productos as $producto) {
            if (!empty($producto['id_producto'])) {
                $mapeoProductos[$producto['id']] = $producto['id_producto'];
            }
        }

        if (!empty($mapeoProductos)) {
            $idsProductos = array_values($mapeoProductos);
            $imagenes = $this->repository->obtenerImagenesProductos($idsProductos);

            foreach ($imagenes as $img) {
                foreach ($mapeoProductos as $idLineaPresupuesto => $idProductoCatalogo) {
                    if ($idProductoCatalogo == $img['id']) {
                        $imagenesProductos[$idLineaPresupuesto] = [
                            'imagen' => $img['imagen'],
                            'tipo' => $img['tipo'],
                            'nombre' => $img['nombre']
                        ];
                    }
                }
            }
        }

        return $imagenesProductos;
    }


    private function obtenerBadgeAccion($accion)
    {
        $badges = [
            'Aprobar' => ['class' => 'bg-success', 'icon' => 'fa-check-circle'],
            'Rechazar' => ['class' => 'bg-danger', 'icon' => 'fa-times-circle'],
            'Devolver' => ['class' => 'bg-warning', 'icon' => 'fa-undo'],
            'Procesar' => ['class' => 'bg-primary', 'icon' => 'fa-cog'],
            'Enviar a Produccion' => ['class' => 'bg-info', 'icon' => 'fa-industry']
        ];

        return $badges[$accion] ?? ['class' => 'bg-secondary', 'icon' => 'fa-question'];
    }


    private function obtenerBadgeEstado($estado)
    {
        $badges = [
            'Aprobado' => ['class' => 'bg-success', 'icon' => 'fa-check-circle'],
            'Rechazado' => ['class' => 'bg-danger', 'icon' => 'fa-times-circle'],
            'Enviado a PCP' => ['class' => 'bg-primary', 'icon' => 'fa-cog'],
            'Devuelto a Contabilidad' => ['class' => 'bg-warning', 'icon' => 'fa-undo'],
            'Procesado' => ['class' => 'bg-primary', 'icon' => 'fa-cog'],
            'En Producción' => ['class' => 'bg-info', 'icon' => 'fa-industry']
        ];

        return $badges[$estado] ?? ['class' => 'bg-secondary', 'icon' => 'fa-question'];
    }


    public function procesarRechazoVentaDevuelta($idVenta, $datos, $idUsuario)
    {
        $errores = $this->validarDatosRechazo($datos);
        if (!empty($errores)) {
            return ['success' => false, 'errores' => $errores];
        }

        try {
            $this->repository->beginTransaction();

            $fechaRechazo = date('Y-m-d H:i:s');
            $observaciones = $datos['descripcion_rechazo'];

            if (!$this->repository->actualizarEstadoVentaDevuelta($idVenta, 'Rechazado')) {
                throw new Exception("La venta no existe, ya fue procesada o no está en estado 'Devuelto a Contabilidad'");
            }

            $datosAutorizacion = [
                'fecha_respuesta' => $fechaRechazo,
                'observaciones_contador' => $observaciones,
                'id_contador' => $idUsuario,
                'estado_autorizacion' => 'Rechazado'
            ];

            if (!$this->repository->actualizarAutorizacion($idVenta, $datosAutorizacion)) {
                throw new Exception("No se pudo actualizar el registro de autorización");
            }

            $datosHistorial = [
                'id_venta' => $idVenta,
                'id_usuario' => $idUsuario,
                'sector' => 'Contable',
                'accion' => 'Rechazar',
                'fecha_accion' => $fechaRechazo,
                'observaciones' => $observaciones,
                'estado_resultante' => 'Rechazado'
            ];

            if (!$this->repository->insertarHistorialAccion($datosHistorial)) {
                throw new Exception("No se pudo insertar el registro en el historial");
            }

            $this->repository->commit();

            return ['success' => true, 'mensaje' => 'Venta rechazada correctamente'];
        } catch (Exception $e) {
            $this->repository->rollBack();
            error_log("Error procesando rechazo de venta devuelta: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al procesar el rechazo: ' . $e->getMessage()];
        }
    }


    public function obtenerDevolucionesPCP($filtros = [], $pagina = 1, $registrosPorPagina = 10)
    {
        $offset = ($pagina - 1) * $registrosPorPagina;

        $devoluciones = $this->repository->obtenerDevolucionesPCP($filtros, $registrosPorPagina, $offset);
        $total = $this->repository->contarDevolucionesPCP($filtros);

        return [
            'devoluciones' => array_map([$this, 'enriquecerDatosDevolucion'], $devoluciones),
            'total' => $total,
            'total_paginas' => ceil($total / $registrosPorPagina),
            'pagina_actual' => $pagina
        ];
    }

    private function enriquecerDatosDevolucion($devolucion)
    {
        $devolucion['simbolo_moneda'] = $devolucion['moneda'] === 'Dólares'
            ? 'U$D '
            : ($devolucion['moneda'] === 'Real brasileño' ? 'R$ ' : '₲ ');


        $devolucion['monto_formateado'] = number_format((float)$devolucion['monto_total'], 2, ',', '.');

        if (!empty($devolucion['fecha_devolucion'])) {
            $devolucion['fecha_devolucion_formateada'] = date('d/m/Y H:i', strtotime($devolucion['fecha_devolucion']));
        }

        return $devolucion;
    }

    public function obtenerVentaCompleta($idVenta)
    {
        $venta = $this->repository->obtenerVentaCompletaPorId($idVenta);

        if (!$venta) {
            throw new Exception('Venta no encontrada');
        }

        $productos = $this->repository->obtenerProductosVenta($idVenta);

        $imagenesAutorizacion = [];
        if ($venta['id_autorizacion']) {
            $imagenesAutorizacion = $this->repository->obtenerImagenesAutorizacion($venta['id_autorizacion']);
        }

        $imagenesProductos = $this->obtenerImagenesProductosOrganizadas($productos);

        $fueRechazada = $this->repository->ventaFueRechazada($idVenta);

        return [
            'venta' => $this->enriquecerDatosVenta($venta),
            'productos' => $productos,
            'imagenes_autorizacion' => $imagenesAutorizacion,
            'imagenes_productos' => $imagenesProductos,
            'fue_rechazada' => $fueRechazada
        ];
    }


    public function logActividad($accion, $detalles = null, $sesion = [])
    {
        $usuario = $sesion['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "CONTABLE - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);
    }
}
