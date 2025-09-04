<?php
require_once 'repository/CuentasCobrarRepository.php';
require_once 'services/CuentasCobrarService.php';

date_default_timezone_set('America/Asuncion');

class CuentasCobrarController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new CuentasCobrarRepository($conexion);
        $this->service = new CuentasCobrarService($this->repository);
        $this->urlBase = $urlBase;
    }

    public function getConexion()
    {
        return $this->repository->getConexion();
    }


    public function handleApiRequest()
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        header('Content-Type: application/json');

        switch ($_GET['action']) {
            case 'obtener_estadisticas':
                $this->obtenerEstadisticasApi();
                break;
            case 'registrar_pago':
                $this->registrarPagoApi();
                break;
            case 'actualizar_monto':
                $this->actualizarMontoApi();
                break;
            case 'obtener_info_fechas':
                $this->obtenerInfoFechasApi();
                break;
            case 'validar_regeneracion':
                $this->validarRegeneracionApi();
                break;
            case 'regenerar_cuotas':
                $this->regenerarCuotasApi();
                break;
            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

        return true;
    }

    private function obtenerInfoFechasApi()
    {
        try {
            $idVenta = $_GET['id_venta'] ?? null;

            if (!$idVenta) {
                echo json_encode(['error' => 'ID de venta requerido']);
                return;
            }

            $infoFechas = $this->obtenerInformacionFechasVenta($idVenta);

            if ($infoFechas) {
                echo json_encode([
                    'success' => true,
                    'info_fechas' => $infoFechas
                ]);
            } else {
                echo json_encode(['error' => 'Venta no encontrada']);
            }
        } catch (Exception $e) {
            error_log("Error en API info fechas: " . $e->getMessage());
            echo json_encode(['error' => 'Error interno del servidor']);
        }
    }

    private function validarRegeneracionApi()
    {
        try {
            $idVenta = $_GET['id_venta'] ?? null;

            if (!$idVenta) {
                echo json_encode(['error' => 'ID de venta requerido']);
                return;
            }

            $validacion = $this->puedeRegenerarCuotas($idVenta);

            echo json_encode([
                'success' => true,
                'validacion' => $validacion
            ]);
        } catch (Exception $e) {
            error_log("Error en API validar regeneración: " . $e->getMessage());
            echo json_encode(['error' => 'Error interno del servidor']);
        }
    }

    private function regenerarCuotasApi()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'Método no permitido']);
            return;
        }

        try {
            $idVenta = $_POST['id_venta'] ?? null;
            $nuevaFechaInicio = $_POST['nueva_fecha_inicio'] ?? null;
            $idUsuario = $_SESSION['id'] ?? null;

            if (!$idVenta || !$nuevaFechaInicio || !$idUsuario) {
                echo json_encode(['error' => 'Datos incompletos']);
                return;
            }

            if (!$this->verificarPermisos()) {
                echo json_encode(['error' => 'Sin permisos']);
                return;
            }

            $cuota = $this->repository->obtenerCuotaPorId($idVenta);
            if (!$cuota) {
                echo json_encode(['error' => 'Venta no encontrada']);
                return;
            }

            $resultado = $this->regenerarCuotasConNuevaFecha(
                $idVenta,
                $cuota['total_venta'],
                $cuota['tipocredito'],
                $cuota['fecha_venta'],
                $nuevaFechaInicio
            );

            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en API regenerar cuotas: " . $e->getMessage());
            echo json_encode(['error' => 'Error interno del servidor']);
        }
    }

    private function obtenerEstadisticasApi()
    {
        try {
            $estadisticas = $this->service->obtenerEstadisticas();
            echo json_encode([
                'success' => true,
                'estadisticas' => $estadisticas
            ]);
        } catch (Exception $e) {
            error_log("Error en API estadísticas: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }


    private function registrarPagoApi()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'Método no permitido']);
            return;
        }

        try {
            $idCuota = $_POST['id_cuota'] ?? null;
            $idUsuario = $_SESSION['id'] ?? null;

            if (!$idCuota || !$idUsuario) {
                echo json_encode(['error' => 'Datos incompletos']);
                return;
            }

            $datos = [
                'monto_pago' => $_POST['monto_pago'] ?? 0,
                'fecha_pago' => $_POST['fecha_pago'] ?? date('Y-m-d'),
                'forma_pago' => $_POST['forma_pago'] ?? '',
                'referencia_pago' => $_POST['referencia_pago'] ?? '',
                'observaciones' => $_POST['observaciones'] ?? '',
                'comprobante' => $_FILES['comprobante'] ?? null
            ];

            $resultado = $this->service->registrarPago($idCuota, $datos, $idUsuario);
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en API registrar pago: " . $e->getMessage());
            echo json_encode(['error' => 'Error interno del servidor']);
        }
    }

    private function actualizarMontoApi()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'Método no permitido']);
            return;
        }

        try {
            $idCuota = $_POST['id_cuota'] ?? null;
            $nuevoMonto = $_POST['nuevo_monto'] ?? 0;
            $idUsuario = $_SESSION['id'] ?? null;

            if (!$idCuota || !$nuevoMonto || !$idUsuario) {
                echo json_encode(['error' => 'Datos incompletos']);
                return;
            }

            $resultado = $this->service->actualizarMontoCuota($idCuota, $nuevoMonto, $idUsuario);
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en API actualizar monto: " . $e->getMessage());
            echo json_encode(['error' => 'Error interno del servidor']);
        }
    }

    public function obtenerCuentasCobrar($filtros = [], $pagina = 1)
    {
        try {
            return $this->service->obtenerCuentasCobrar($filtros, $pagina);
        } catch (Exception $e) {
            error_log("Error obteniendo cuentas por cobrar: " . $e->getMessage());
            return [
                'cuentas' => [],
                'total' => 0,
                'total_paginas' => 0,
                'pagina_actual' => $pagina
            ];
        }
    }


    public function obtenerDetalleCuota($idCuota)
    {
        try {
            return $this->service->obtenerDetalleCuota($idCuota);
        } catch (Exception $e) {
            error_log("Error obteniendo detalle de cuota: " . $e->getMessage());
            throw new Exception('Cuota no encontrada');
        }
    }

    public function procesarRegistroPago($idCuota, $datos)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['error' => 'Método no permitido'];
        }

        if (!isset($_SESSION['id'])) {
            return ['error' => 'Usuario no autenticado'];
        }

        try {
            $idUsuario = (int)$_SESSION['id'];
            $resultado = $this->service->registrarPago($idCuota, $datos, $idUsuario);

            if ($resultado['success']) {
                $cuota = $this->repository->obtenerCuotaPorId($idCuota);
                $urlRedirect = $this->urlBase . "secciones/contable/ver_cuota.php?id=" . $idCuota . "&mensaje=" . urlencode($resultado['mensaje']);

                if (
                    strpos($resultado['mensaje'], 'VENTA CERRADA') !== false ||
                    strpos($resultado['mensaje'], 'última cuota') !== false
                ) {
                    $urlRedirect = $this->urlBase . "secciones/contable/cuentas_cobrar.php?mensaje=" . urlencode("✅ " . $resultado['mensaje']);
                }

                header("Location: " . $urlRedirect);
                exit();
            } else {
                return [
                    'error' => $resultado['error'] ?? 'Error desconocido',
                    'errores' => $resultado['errores'] ?? []
                ];
            }
        } catch (Exception $e) {
            error_log("Error procesando registro de pago: " . $e->getMessage());
            return ['error' => 'Error interno del servidor'];
        }
    }

    public function generarCuotasVentaAprobada($idVenta, $montoTotal, $tipoCreditoStr, $fechaVenta, $manejarTransaccion = true, $fechaInicioCustom = null)
    {
        try {
            $resultado = $this->service->procesarVentaAprobada($idVenta, $montoTotal, $tipoCreditoStr, $fechaVenta, $manejarTransaccion, $fechaInicioCustom);

            if ($resultado['success']) {
                $detalles = "Venta: {$idVenta}";
                if ($fechaInicioCustom) {
                    $detalles .= ", Fecha personalizada: {$fechaInicioCustom}";
                }
                $this->logActividad('Generar cuotas venta aprobada', $detalles);
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("Error generando cuotas: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    public function regenerarCuotasConNuevaFecha($idVenta, $montoTotal, $tipoCreditoStr, $fechaVenta, $nuevaFechaInicio)
    {
        try {
            $resultado = $this->service->regenerarCuotasVenta($idVenta, $montoTotal, $tipoCreditoStr, $fechaVenta, true, $nuevaFechaInicio);

            if ($resultado['success']) {
                $detalles = "Venta: {$idVenta}, Nueva fecha: {$nuevaFechaInicio}";
                if (isset($resultado['hay_pagos_perdidos']) && $resultado['hay_pagos_perdidos']) {
                    $detalles .= ", Pagos perdidos: Sí (Total: " . ($resultado['total_pagos_perdidos'] ?? 0) . ")";
                } else {
                    $detalles .= ", Pagos perdidos: No";
                }
                $this->logActividad('Regenerar cuotas con fecha personalizada', $detalles);
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("Error regenerando cuotas con nueva fecha: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }

    public function obtenerInformacionFechasVenta($idVenta)
    {
        try {
            return $this->service->obtenerInformacionFechasVenta($idVenta);
        } catch (Exception $e) {
            error_log("Error obteniendo información de fechas: " . $e->getMessage());
            return null;
        }
    }

    public function puedeRegenerarCuotas($idVenta)
    {
        try {
            $cuotasVenta = $this->repository->obtenerCuotasPorVenta($idVenta);

            $totalPagado = 0;
            $hayPagos = false;

            foreach ($cuotasVenta as $cuota) {
                if ($cuota['monto_pagado'] > 0) {
                    $totalPagado += $cuota['monto_pagado'];
                    $hayPagos = true;
                }
            }

            return [
                'puede_regenerar' => $this->verificarPermisos(),
                'hay_pagos' => $hayPagos,
                'total_pagado' => $totalPagado,
                'total_cuotas' => count($cuotasVenta),
                'info_detallada' => [
                    'cuotas_con_pagos' => array_filter($cuotasVenta, function ($c) {
                        return $c['monto_pagado'] > 0;
                    }),
                    'cuotas_sin_pagos' => array_filter($cuotasVenta, function ($c) {
                        return $c['monto_pagado'] == 0;
                    })
                ]
            ];
        } catch (Exception $e) {
            error_log("Error validando regeneración de cuotas: " . $e->getMessage());
            return [
                'puede_regenerar' => false,
                'hay_pagos' => false,
                'total_pagado' => 0,
                'total_cuotas' => 0,
                'info_detallada' => []
            ];
        }
    }

    public function restaurarFechaOriginalVenta($idVenta, $montoTotal, $tipoCreditoStr, $fechaVenta)
    {
        try {
            $resultado = $this->service->regenerarCuotasVenta($idVenta, $montoTotal, $tipoCreditoStr, $fechaVenta, true, $fechaVenta);

            if ($resultado['success']) {
                $resultado['mensaje'] = "Fecha original restaurada correctamente. Las cuotas ahora se calculan desde la fecha de venta: " . date('d/m/Y', strtotime($fechaVenta));
                $this->logActividad('Restaurar fecha original de venta', "Venta: {$idVenta}, Fecha restaurada: {$fechaVenta}");
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("Error restaurando fecha original: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error interno del servidor'];
        }
    }


    public function obtenerEstadisticas()
    {
        try {
            return $this->service->obtenerEstadisticas();
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [
                'pendientes' => 0,
                'monto_pendiente' => 0,
                'vencidas' => 0,
                'monto_vencido' => 0,
                'pagadas_mes' => 0,
                'monto_cobrado_mes' => 0
            ];
        }
    }


    public function procesarFiltros()
    {
        $filtros = [];

        if (isset($_GET['id_venta']) && !empty(trim($_GET['id_venta']))) {
            $filtros['id_venta'] = trim($_GET['id_venta']);
        }

        if (isset($_GET['cliente']) && !empty(trim($_GET['cliente']))) {
            $filtros['cliente'] = trim($_GET['cliente']);
        }

        if (isset($_GET['estado']) && !empty(trim($_GET['estado']))) {
            $filtros['estado'] = trim($_GET['estado']);
        }

        if (isset($_GET['fecha_desde']) && !empty(trim($_GET['fecha_desde']))) {
            $filtros['fecha_desde'] = trim($_GET['fecha_desde']);
        }

        if (isset($_GET['fecha_hasta']) && !empty(trim($_GET['fecha_hasta']))) {
            $filtros['fecha_hasta'] = trim($_GET['fecha_hasta']);
        }

        if (isset($_GET['solo_vencidas']) && $_GET['solo_vencidas'] === '1') {
            $filtros['solo_vencidas'] = true;
        }

        return $filtros;
    }

    public function procesarDatosFormularioPago()
    {
        return [
            'monto_pago' => $_POST['monto_pago'] ?? 0,
            'fecha_pago' => $_POST['fecha_pago'] ?? date('Y-m-d'),
            'forma_pago' => $_POST['forma_pago'] ?? '',
            'referencia_pago' => $_POST['referencia_pago'] ?? '',
            'observaciones' => $_POST['observaciones'] ?? '',
            'comprobante' => $_FILES['comprobante'] ?? null,
            'redistribuir_pago' => isset($_POST['redistribuir_pago']) ? true : false,
            'completar_cuota' => isset($_POST['completar_cuota']) && $_POST['completar_cuota'] === 'true'  // ✅ NUEVO
        ];
    }


    public function verificarPermisos()
    {
        return $this->service->validarPermisos($_SESSION['rol'] ?? '0');
    }


    public function obtenerDatosVista($titulo = 'Cuentas por Cobrar')
    {
        try {
            return [
                'titulo' => $titulo,
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => $this->esAdministrador(),
                'estadisticas' => $this->obtenerEstadisticas(),
                'config_fechas' => [
                    'fecha_actual' => date('Y-m-d'),
                    'fecha_minima' => date('Y-m-d', strtotime('-2 years')),
                    'formato_fecha' => 'dd/mm/yyyy'
                ]
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => $titulo,
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => false,
                'estadisticas' => [],
                'config_fechas' => [
                    'fecha_actual' => date('Y-m-d'),
                    'fecha_minima' => date('Y-m-d', strtotime('-2 years')),
                    'formato_fecha' => 'dd/mm/yyyy'
                ]
            ];
        }
    }

    public function manejarMensajes()
    {
        $mensaje = '';
        $error = '';

        if (isset($_GET['mensaje'])) {
            $mensaje = htmlspecialchars($_GET['mensaje']);
        }

        if (isset($_GET['error'])) {
            $error = htmlspecialchars($_GET['error']);
        }

        return [
            'mensaje' => $mensaje,
            'error' => $error
        ];
    }


    public function obtenerConfiguracionJS()
    {
        return [
            'urlBase' => $this->urlBase,
            'usuario' => $_SESSION['nombre'] ?? 'Usuario',
            'esAdmin' => $this->esAdministrador(),
            'debug' => isset($_GET['debug']),
            'rol' => $_SESSION['rol'] ?? '0',
            'fechas' => [
                'formatoJS' => 'dd/mm/yyyy',
                'fechaActual' => date('Y-m-d'),
                'fechaMinima' => date('Y-m-d', strtotime('-2 years')),
                'idioma' => 'es-PY'
            ],
            'endpoints' => [
                'obtenerInfoFechas' => $this->urlBase . 'secciones/contable/cuentas_cobrar.php?action=obtener_info_fechas',
                'validarRegeneracion' => $this->urlBase . 'secciones/contable/cuentas_cobrar.php?action=validar_regeneracion',
                'regenerarCuotas' => $this->urlBase . 'secciones/contable/cuentas_cobrar.php?action=regenerar_cuotas'
            ]
        ];
    }


    public function generarUrlConParametros($archivo, $parametros = [])
    {
        $url = $this->urlBase . "secciones/contable/" . $archivo;

        if (!empty($parametros)) {
            $url .= "?" . http_build_query($parametros);
        }

        return $url;
    }

    public function formatearMoneda($monto, $moneda)
    {
        $simbolo = $this->obtenerSimboloMoneda($moneda);
        return $simbolo . number_format((float)$monto, 2, ',', '.');
    }

    public function validarIdCuota($id)
    {
        return isset($id) && is_numeric($id) && $id > 0;
    }


    public function logActividad($accion, $detalles = null)
    {
        $usuario = $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "CUENTAS_COBRAR - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);
    }

    public function obtenerCuentasPagadas($filtros = [], $pagina = 1)
    {
        try {
            return $this->service->obtenerCuentasPagadas($filtros, $pagina);
        } catch (Exception $e) {
            error_log("Error obteniendo cuentas pagadas: " . $e->getMessage());
            return [
                'cuentas' => [],
                'total' => 0,
                'total_paginas' => 0,
                'pagina_actual' => $pagina
            ];
        }
    }

    public function procesarFiltrosPagados()
    {
        $filtros = [];

        if (isset($_GET['id_venta']) && !empty(trim($_GET['id_venta']))) {
            $filtros['id_venta'] = trim($_GET['id_venta']);
        }

        if (isset($_GET['cliente']) && !empty(trim($_GET['cliente']))) {
            $filtros['cliente'] = trim($_GET['cliente']);
        }

        if (isset($_GET['fecha_desde']) && !empty(trim($_GET['fecha_desde']))) {
            $filtros['fecha_desde'] = trim($_GET['fecha_desde']);
        }

        if (isset($_GET['fecha_hasta']) && !empty(trim($_GET['fecha_hasta']))) {
            $filtros['fecha_hasta'] = trim($_GET['fecha_hasta']);
        }

        if (isset($_GET['fecha_pago_desde']) && !empty(trim($_GET['fecha_pago_desde']))) {
            $filtros['fecha_pago_desde'] = trim($_GET['fecha_pago_desde']);
        }

        if (isset($_GET['fecha_pago_hasta']) && !empty(trim($_GET['fecha_pago_hasta']))) {
            $filtros['fecha_pago_hasta'] = trim($_GET['fecha_pago_hasta']);
        }

        return $filtros;
    }

    public function obtenerDatosVistaPagados($titulo = 'Cuentas Pagadas')
    {
        try {
            $datosBase = $this->obtenerDatosVista($titulo);

            $estadisticasPagados = $this->obtenerEstadisticasPagados();

            $datosBase['estadisticas_pagados'] = $estadisticasPagados;

            return $datosBase;
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista pagados: " . $e->getMessage());
            return $this->obtenerDatosVista($titulo);
        }
    }

    private function obtenerEstadisticasPagados()
    {
        try {
            $conexion = $this->repository->getConexion();
            $stats = [];

            $sql = "SELECT COUNT(DISTINCT cc.id_venta) as total, COALESCE(SUM(cc.monto_cuota), 0) as monto_total
                FROM public.sist_ventas_cuentas_cobrar cc
                INNER JOIN (
                    SELECT id_venta
                    FROM public.sist_ventas_cuentas_cobrar
                    GROUP BY id_venta
                    HAVING SUM(monto_pendiente) = 0
                ) pagadas ON cc.id_venta = pagadas.id_venta
                WHERE EXTRACT(MONTH FROM cc.fecha_ultimo_pago) = EXTRACT(MONTH FROM CURRENT_DATE)
                AND EXTRACT(YEAR FROM cc.fecha_ultimo_pago) = EXTRACT(YEAR FROM CURRENT_DATE)";
            $result = $conexion->query($sql)->fetch(PDO::FETCH_ASSOC);
            $stats['pagadas_mes'] = $result['total'];
            $stats['monto_cobrado_mes'] = $result['monto_total'];

            $sql = "SELECT COUNT(DISTINCT cc.id_venta) as total, COALESCE(SUM(cc.monto_cuota), 0) as monto_total
                FROM public.sist_ventas_cuentas_cobrar cc
                INNER JOIN (
                    SELECT id_venta
                    FROM public.sist_ventas_cuentas_cobrar
                    GROUP BY id_venta
                    HAVING SUM(monto_pendiente) = 0
                ) pagadas ON cc.id_venta = pagadas.id_venta
                WHERE DATE(cc.fecha_ultimo_pago) = CURRENT_DATE";
            $result = $conexion->query($sql)->fetch(PDO::FETCH_ASSOC);
            $stats['pagadas_hoy'] = $result['total'];
            $stats['monto_cobrado_hoy'] = $result['monto_total'];

            $sql = "SELECT COUNT(DISTINCT cc.id_venta) as total, COALESCE(SUM(cc.monto_cuota), 0) as monto_total
                FROM public.sist_ventas_cuentas_cobrar cc
                INNER JOIN (
                    SELECT id_venta
                    FROM public.sist_ventas_cuentas_cobrar
                    GROUP BY id_venta
                    HAVING SUM(monto_pendiente) = 0
                ) pagadas ON cc.id_venta = pagadas.id_venta
                WHERE cc.fecha_ultimo_pago >= DATE_TRUNC('week', CURRENT_DATE)";
            $result = $conexion->query($sql)->fetch(PDO::FETCH_ASSOC);
            $stats['pagadas_semana'] = $result['total'];
            $stats['monto_cobrado_semana'] = $result['monto_total'];

            return $stats;
        } catch (PDOException $e) {
            error_log("Error obteniendo estadísticas pagados: " . $e->getMessage());
            return [
                'pagadas_mes' => 0,
                'monto_cobrado_mes' => 0,
                'pagadas_hoy' => 0,
                'monto_cobrado_hoy' => 0,
                'pagadas_semana' => 0,
                'monto_cobrado_semana' => 0
            ];
        }
    }

    private function esAdministrador()
    {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === ['1', '3'];
    }


    public function obtenerSimboloMoneda($moneda)
    {
        switch ($moneda) {
            case 'Dólares':
                return 'U$D ';
            case 'Real brasileño':
                return 'R$ ';
            default:
                return '₲ ';
        }
    }
}
