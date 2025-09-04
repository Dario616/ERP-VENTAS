<?php

/**
 * Service para lógica de negocio de reservas de stock
 * Modificado para vista agrupada por productos
 */
class ReservasService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    /**
     * Obtener productos con reservas paginados
     */
    public function obtenerProductosConReservasPaginados($filtroProducto = '', $pagina = 1, $registrosPorPagina = 20)
    {
        // Validar parámetros
        $this->validarParametrosPaginacion($pagina, $registrosPorPagina);

        $offset = ($pagina - 1) * $registrosPorPagina;

        // Obtener datos del repositorio
        $datos = $this->repository->obtenerProductosConReservas($filtroProducto, $registrosPorPagina, $offset);
        $totalRegistros = $this->repository->contarProductosConReservas($filtroProducto);

        // Enriquecer datos
        $datosEnriquecidos = array_map([$this, 'enriquecerDatosProducto'], $datos);

        // Calcular información de paginación
        $totalPaginas = ceil($totalRegistros / $registrosPorPagina);

        return [
            'datos' => $datosEnriquecidos,
            'paginacion' => [
                'pagina_actual' => $pagina,
                'registros_por_pagina' => $registrosPorPagina,
                'total_registros' => $totalRegistros,
                'total_paginas' => $totalPaginas,
                'hay_pagina_anterior' => $pagina > 1,
                'hay_pagina_siguiente' => $pagina < $totalPaginas,
                'pagina_anterior' => max(1, $pagina - 1),
                'pagina_siguiente' => min($totalPaginas, $pagina + 1)
            ],
            'estadisticas' => $this->calcularEstadisticasPagina($datosEnriquecidos)
        ];
    }

    /**
     * Enriquecer datos de producto con información calculada
     */
    private function enriquecerDatosProducto($producto)
    {
        // Formatear números para visualización
        $producto['total_bobinas_reservadas_formateado'] = number_format($producto['total_bobinas_reservadas'] ?? 0, 0, ',', '.');
        $producto['total_paquetes_reservados_formateado'] = number_format($producto['total_paquetes_reservados'] ?? 0, 0, ',', '.');
        $producto['cantidad_disponible_formateada'] = number_format($producto['cantidad_disponible'] ?? 0, 0, ',', '.');
        $producto['cantidad_total_formateada'] = number_format($producto['cantidad_total'] ?? 0, 0, ',', '.');

        // Formatear fechas
        if (!empty($producto['fecha_primera_reserva'])) {
            $fecha = new DateTime($producto['fecha_primera_reserva']);
            $producto['fecha_primera_reserva_formateada'] = $fecha->format('d/m/Y H:i');
        }

        if (!empty($producto['fecha_ultima_reserva'])) {
            $fecha = new DateTime($producto['fecha_ultima_reserva']);
            $producto['fecha_ultima_reserva_formateada'] = $fecha->format('d/m/Y H:i');
        }

        // Determinar urgencia basada en días promedio
        $producto['configuracion_urgencia'] = $this->determinarUrgenciaProducto($producto['max_dias_reserva'] ?? 0);

        // Configurar tipo de producto
        $producto['configuracion_tipo'] = $this->configurarTipoProducto($producto['tipo_producto'] ?? '');

        // Calcular disponibilidad después de cancelar todas las reservas
        $producto['disponible_sin_reservas'] = ($producto['cantidad_disponible'] ?? 0) + ($producto['total_bobinas_reservadas'] ?? 0);

        // Generar alertas
        $producto['alertas'] = $this->generarAlertasProducto($producto);

        // Estado del stock
        $producto['estado_stock'] = $this->determinarEstadoStock($producto);

        // Lista de clientes formateada
        $producto['clientes_formateados'] = $this->formatearListaClientes($producto['clientes_list'] ?? '');

        return $producto;
    }

    /**
     * Determinar urgencia del producto basada en días máximos
     */
    private function determinarUrgenciaProducto($diasMaximos)
    {
        if ($diasMaximos >= 30) {
            return [
                'nivel' => 'muy_alta',
                'texto' => 'Crítico',
                'color' => '#dc2626',
                'color_fondo' => '#fef2f2',
                'icono' => 'fas fa-exclamation-triangle',
                'descripcion' => 'Reservas muy antiguas'
            ];
        } elseif ($diasMaximos >= 15) {
            return [
                'nivel' => 'alta',
                'texto' => 'Alto',
                'color' => '#ea580c',
                'color_fondo' => '#fff7ed',
                'icono' => 'fas fa-clock',
                'descripcion' => 'Reservas antiguas'
            ];
        } elseif ($diasMaximos >= 7) {
            return [
                'nivel' => 'media',
                'texto' => 'Medio',
                'color' => '#d97706',
                'color_fondo' => '#fffbeb',
                'icono' => 'fas fa-calendar-alt',
                'descripcion' => 'Algunas reservas pendientes'
            ];
        } else {
            return [
                'nivel' => 'baja',
                'texto' => 'Bajo',
                'color' => '#059669',
                'color_fondo' => '#f0fdf4',
                'icono' => 'fas fa-calendar-check',
                'descripcion' => 'Reservas recientes'
            ];
        }
    }

    /**
     * Configurar tipo de producto
     */
    private function configurarTipoProducto($tipoProducto)
    {
        $configuraciones = [
            'TOALLITAS' => [
                'color' => '#3b82f6',
                'icono' => 'fas fa-tissue'
            ],
            'TNT' => [
                'color' => '#6366f1',
                'icono' => 'fas fa-industry'
            ],
            'SPUNLACE' => [
                'color' => '#8b5cf6',
                'icono' => 'fas fa-layer-group'
            ],
            'LAMINADO' => [
                'color' => '#06b6d4',
                'icono' => 'fas fa-layers'
            ]
        ];

        $tipoUpper = strtoupper($tipoProducto);
        return $configuraciones[$tipoUpper] ?? [
            'color' => '#6b7280',
            'icono' => 'fas fa-box'
        ];
    }

    /**
     * Determinar estado del stock
     */
    private function determinarEstadoStock($producto)
    {
        $porcentajeComprometido = $producto['porcentaje_comprometido'] ?? 0;
        $cantidadDisponible = $producto['cantidad_disponible'] ?? 0;

        if ($porcentajeComprometido >= 80) {
            return [
                'nivel' => 'critico',
                'texto' => 'Stock Crítico',
                'color' => '#dc2626',
                'icono' => 'fas fa-exclamation-triangle'
            ];
        } elseif ($porcentajeComprometido >= 50) {
            return [
                'nivel' => 'alto',
                'texto' => 'Stock Alto',
                'color' => '#ea580c',
                'icono' => 'fas fa-chart-pie'
            ];
        } elseif ($cantidadDisponible <= 5) {
            return [
                'nivel' => 'bajo',
                'texto' => 'Stock Bajo',
                'color' => '#d97706',
                'icono' => 'fas fa-exclamation'
            ];
        } else {
            return [
                'nivel' => 'normal',
                'texto' => 'Stock Normal',
                'color' => '#059669',
                'icono' => 'fas fa-check-circle'
            ];
        }
    }

    /**
     * Generar alertas para un producto
     */
    private function generarAlertasProducto($producto)
    {
        $alertas = [];

        // Alerta por días máximos de reserva
        if (($producto['max_dias_reserva'] ?? 0) >= 30) {
            $alertas[] = [
                'tipo' => 'danger',
                'mensaje' => 'Reservas muy antiguas (' . $producto['max_dias_reserva'] . ' días máx.)',
                'icono' => 'fas fa-exclamation-triangle',
                'prioridad' => 'alta'
            ];
        } elseif (($producto['max_dias_reserva'] ?? 0) >= 15) {
            $alertas[] = [
                'tipo' => 'warning',
                'mensaje' => 'Reservas antiguas (' . $producto['max_dias_reserva'] . ' días máx.)',
                'icono' => 'fas fa-clock',
                'prioridad' => 'media'
            ];
        }

        // Alerta por alto porcentaje comprometido
        if (($producto['porcentaje_comprometido'] ?? 0) >= 80) {
            $alertas[] = [
                'tipo' => 'danger',
                'mensaje' => 'Stock altamente comprometido (' . $producto['porcentaje_comprometido'] . '%)',
                'icono' => 'fas fa-chart-pie',
                'prioridad' => 'alta'
            ];
        } elseif (($producto['porcentaje_comprometido'] ?? 0) >= 50) {
            $alertas[] = [
                'tipo' => 'warning',
                'mensaje' => 'Stock comprometido (' . $producto['porcentaje_comprometido'] . '%)',
                'icono' => 'fas fa-chart-pie',
                'prioridad' => 'media'
            ];
        }

        // Alerta por stock bajo
        if (($producto['cantidad_disponible'] ?? 0) <= 5) {
            $alertas[] = [
                'tipo' => 'info',
                'mensaje' => 'Stock disponible muy bajo',
                'icono' => 'fas fa-inventory',
                'prioridad' => 'baja'
            ];
        }

        // Alerta por muchos clientes
        if (($producto['total_clientes'] ?? 0) >= 5) {
            $alertas[] = [
                'tipo' => 'info',
                'mensaje' => 'Múltiples clientes (' . $producto['total_clientes'] . ')',
                'icono' => 'fas fa-users',
                'prioridad' => 'baja'
            ];
        }

        return $alertas;
    }

    /**
     * Formatear lista de clientes
     */
    private function formatearListaClientes($clientesString)
    {
        if (empty($clientesString)) {
            return 'Sin especificar';
        }

        $clientes = array_map('trim', explode(',', $clientesString));
        $clientes = array_filter($clientes);

        if (count($clientes) <= 3) {
            return implode(', ', $clientes);
        } else {
            return implode(', ', array_slice($clientes, 0, 2)) . ' y ' . (count($clientes) - 2) . ' más...';
        }
    }

    /**
     * Calcular estadísticas de la página actual
     */
    private function calcularEstadisticasPagina($datos)
    {
        if (empty($datos)) {
            return [
                'total_productos' => 0,
                'total_reservas' => 0,
                'total_bobinas' => 0,
                'total_paquetes' => 0,
                'productos_criticos' => 0
            ];
        }

        $totalProductos = count($datos);
        $totalReservas = array_sum(array_column($datos, 'total_reservas'));
        $totalBobinas = array_sum(array_column($datos, 'total_bobinas_reservadas'));
        $totalPaquetes = array_sum(array_column($datos, 'total_paquetes_reservados'));
        $productosCriticos = 0;

        foreach ($datos as $producto) {
            if (($producto['max_dias_reserva'] ?? 0) >= 15 || ($producto['porcentaje_comprometido'] ?? 0) >= 50) {
                $productosCriticos++;
            }
        }

        return [
            'total_productos' => $totalProductos,
            'total_reservas' => $totalReservas,
            'total_bobinas' => $totalBobinas,
            'total_paquetes' => $totalPaquetes,
            'productos_criticos' => $productosCriticos,
            'total_bobinas_formateado' => number_format($totalBobinas, 0, ',', '.'),
            'total_paquetes_formateado' => number_format($totalPaquetes, 0, ',', '.')
        ];
    }

    /**
     * Obtener reservas específicas de un producto
     */
    public function obtenerReservasPorProducto($idStock)
    {
        if (empty($idStock) || !is_numeric($idStock)) {
            throw new Exception('ID de stock inválido');
        }

        $reservas = $this->repository->obtenerReservasPorProducto($idStock);

        // Enriquecer cada reserva
        return array_map([$this, 'enriquecerDatosReservaIndividual'], $reservas);
    }

    /**
     * Enriquecer datos de reserva individual
     */
    private function enriquecerDatosReservaIndividual($reserva)
    {
        // Formatear números
        $reserva['cantidad_reservada_formateada'] = number_format($reserva['cantidad_reservada'] ?? 0, 0, ',', '.');
        $reserva['paquetes_reservados_formateados'] = number_format($reserva['paquetes_reservados'] ?? 0, 0, ',', '.');

        // Formatear fechas
        if (!empty($reserva['fecha_reserva'])) {
            $fecha = new DateTime($reserva['fecha_reserva']);
            $reserva['fecha_reserva_formateada'] = $fecha->format('d/m/Y H:i');
            $reserva['fecha_reserva_corta'] = $fecha->format('d/m/Y');
        }

        if (!empty($reserva['fecha_venta'])) {
            $fecha = new DateTime($reserva['fecha_venta']);
            $reserva['fecha_venta_formateada'] = $fecha->format('d/m/Y');
        }

        // Configurar estado de venta
        $reserva['configuracion_estado_venta'] = $this->configurarEstadoVenta($reserva['estado_venta'] ?? '');

        // Cliente formateado
        $reserva['cliente_formateado'] = $this->formatearNombreCliente($reserva['cliente'] ?? '');

        return $reserva;
    }

    /**
     * Configurar estado de venta
     */
    private function configurarEstadoVenta($estadoVenta)
    {
        $configuraciones = [
            'pendiente' => [
                'texto' => 'Pendiente',
                'color' => '#d97706',
                'icono' => 'fas fa-clock'
            ],
            'confirmada' => [
                'texto' => 'Confirmada',
                'color' => '#059669',
                'icono' => 'fas fa-check-circle'
            ],
            'despachada' => [
                'texto' => 'Despachada',
                'color' => '#3b82f6',
                'icono' => 'fas fa-truck'
            ],
            'cancelada' => [
                'texto' => 'Cancelada',
                'color' => '#dc2626',
                'icono' => 'fas fa-times-circle'
            ]
        ];

        return $configuraciones[$estadoVenta] ?? [
            'texto' => ucfirst($estadoVenta ?: 'Sin Estado'),
            'color' => '#6b7280',
            'icono' => 'fas fa-question-circle'
        ];
    }

    /**
     * Formatear nombre del cliente
     */
    private function formatearNombreCliente($cliente)
    {
        if (empty($cliente)) {
            return 'Sin especificar';
        }

        return ucwords(strtolower(trim($cliente)));
    }

    /**
     * Buscar reservas para cancelación
     */
    public function buscarReservasParaCancelacion($idStock, $cliente = '', $cantidadMinima = 0)
    {
        if (empty($idStock) || !is_numeric($idStock)) {
            throw new Exception('ID de stock inválido');
        }

        $reservas = $this->repository->buscarReservasParaCancelacion($idStock, $cliente, $cantidadMinima);

        return array_map(function ($reserva) {
            $reserva['cantidad_reservada_formateada'] = number_format($reserva['cantidad_reservada'] ?? 0, 0, ',', '.');
            $reserva['paquetes_reservados_formateados'] = number_format($reserva['paquetes_reservados'] ?? 0, 0, ',', '.');

            if (!empty($reserva['fecha_reserva'])) {
                $fecha = new DateTime($reserva['fecha_reserva']);
                $reserva['fecha_reserva_formateada'] = $fecha->format('d/m/Y');
            }

            return $reserva;
        }, $reservas);
    }

    /**
     * Cancelar reserva
     */
    public function cancelarReserva($idReserva, $motivo, $usuario)
    {
        // Validar parámetros
        if (empty($idReserva) || !is_numeric($idReserva)) {
            throw new Exception('ID de reserva inválido');
        }

        if (empty($motivo)) {
            $motivo = 'Cancelación desde interfaz';
        }

        if (empty($usuario)) {
            $usuario = 'SISTEMA';
        }

        // Obtener detalles antes de cancelar
        $detalleAntes = $this->repository->obtenerDetalleReserva($idReserva);
        if (!$detalleAntes) {
            throw new Exception('Reserva no encontrada');
        }

        // Ejecutar cancelación
        $resultado = $this->repository->cancelarReserva($idReserva, $motivo, $usuario);

        // Registrar log
        $this->repository->registrarLogCancelacion($idReserva, $usuario, $motivo, $resultado);

        // Enriquecer resultado con información adicional
        if ($resultado['exito']) {
            $resultado['detalle_producto'] = $detalleAntes['nombre_producto'];
            $resultado['detalle_cliente'] = $detalleAntes['cliente'];
            $resultado['detalle_proforma'] = $detalleAntes['proforma'];
        }

        return $resultado;
    }

    /**
     * Obtener estadísticas generales de reservas
     */
    public function obtenerEstadisticasGenerales()
    {
        $estadisticas = $this->repository->obtenerEstadisticasReservas();

        // Enriquecer estadísticas
        if ($estadisticas) {
            $estadisticas['total_bobinas_reservadas_formateado'] = number_format($estadisticas['total_bobinas_reservadas'] ?? 0, 0, ',', '.');
            $estadisticas['total_paquetes_reservados_formateado'] = number_format($estadisticas['total_paquetes_reservados'] ?? 0, 0, ',', '.');
        }

        return [
            'generales' => $estadisticas ?: [],
            'alertas' => $this->generarAlertasGenerales($estadisticas)
        ];
    }

    /**
     * Generar alertas generales del sistema
     */
    private function generarAlertasGenerales($estadisticas)
    {
        $alertas = [];

        if ($estadisticas) {
            // Alerta por muchas reservas activas
            if (($estadisticas['total_reservas_activas'] ?? 0) > 50) {
                $alertas[] = [
                    'tipo' => 'warning',
                    'titulo' => 'Alto Volumen de Reservas',
                    'mensaje' => 'Hay ' . $estadisticas['total_reservas_activas'] . ' reservas activas',
                    'icono' => 'fas fa-exclamation-triangle',
                    'prioridad' => 'media'
                ];
            }

            // Alerta por promedio de días alto
            if (($estadisticas['promedio_dias_reserva'] ?? 0) > 15) {
                $alertas[] = [
                    'tipo' => 'danger',
                    'titulo' => 'Reservas Antiguas',
                    'mensaje' => 'Promedio de ' . $estadisticas['promedio_dias_reserva'] . ' días por reserva',
                    'icono' => 'fas fa-clock',
                    'prioridad' => 'alta'
                ];
            }

            // Alerta por muchos productos con reservas
            if (($estadisticas['productos_con_reservas'] ?? 0) > 20) {
                $alertas[] = [
                    'tipo' => 'info',
                    'titulo' => 'Múltiples Productos Reservados',
                    'mensaje' => $estadisticas['productos_con_reservas'] . ' productos con reservas activas',
                    'icono' => 'fas fa-boxes',
                    'prioridad' => 'baja'
                ];
            }
        }

        return $alertas;
    }

    /**
     * Validar parámetros de paginación
     */
    private function validarParametrosPaginacion($pagina, $registrosPorPagina)
    {
        if (!is_numeric($pagina) || $pagina < 1) {
            throw new Exception('Número de página inválido');
        }

        if (!is_numeric($registrosPorPagina) || $registrosPorPagina < 1 || $registrosPorPagina > 100) {
            throw new Exception('Número de registros por página inválido');
        }
    }

    /**
     * Verificar integridad del sistema
     */
    public function verificarIntegridad()
    {
        return $this->repository->verificarIntegridad();
    }

    /**
     * Obtener resumen ejecutivo
     */
    public function obtenerResumenEjecutivo()
    {
        $estadisticas = $this->repository->obtenerEstadisticasReservas();

        return [
            'total_reservas' => $estadisticas['total_reservas_activas'] ?? 0,
            'total_productos_afectados' => $estadisticas['productos_con_reservas'] ?? 0,
            'total_clientes' => $estadisticas['clientes_con_reservas'] ?? 0,
            'promedio_antiguedad' => $estadisticas['promedio_dias_reserva'] ?? 0,
            'bobinas_comprometidas' => $estadisticas['total_bobinas_reservadas'] ?? 0,
            'paquetes_comprometidos' => $estadisticas['total_paquetes_reservados'] ?? 0,
            'alertas' => $this->generarAlertasGenerales($estadisticas),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Exportar datos para reportes
     */
    public function exportarDatos($filtroProducto = '', $formato = 'array')
    {
        // Obtener todos los datos sin paginación
        $datos = $this->repository->obtenerProductosConReservas($filtroProducto, 1000, 0);
        $datosEnriquecidos = array_map([$this, 'enriquecerDatosProducto'], $datos);

        if ($formato === 'csv') {
            return $this->convertirACSV($datosEnriquecidos);
        }

        return $datosEnriquecidos;
    }

    /**
     * Convertir datos a formato CSV
     */
    private function convertirACSV($datos)
    {
        if (empty($datos)) {
            return '';
        }

        $csv = [];

        // Headers
        $csv[] = [
            'Producto',
            'Tipo',
            'Stock Disponible',
            'Stock Total',
            'Total Reservas',
            'Bobinas Reservadas',
            'Paquetes Reservados',
            'Clientes',
            'Días Máximo Reserva',
            '% Comprometido',
            'Estado'
        ];

        // Datos
        foreach ($datos as $producto) {
            $csv[] = [
                $producto['nombre_producto'],
                $producto['tipo_producto'],
                $producto['cantidad_disponible'],
                $producto['cantidad_total'],
                $producto['total_reservas'],
                $producto['total_bobinas_reservadas'],
                $producto['total_paquetes_reservados'],
                $producto['total_clientes'],
                $producto['max_dias_reserva'],
                $producto['porcentaje_comprometido'],
                $producto['estado_stock']['texto']
            ];
        }

        return $csv;
    }
}
