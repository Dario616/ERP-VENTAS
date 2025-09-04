<?php
require_once __DIR__ . '/../repository/RelatorioRepository.php';
require_once __DIR__ . '/../services/RelatorioService.php';

date_default_timezone_set('America/Asuncion');

class RelatorioController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new RelatorioRepository($conexion);
        $this->service = new RelatorioService($this->repository);
        $this->urlBase = $urlBase;
    }

    public function handleApiRequest()
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? null;

        if (!$action) {
            return false;
        }

        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');

        try {
            switch ($action) {
                case 'datos_dashboard':
                    $this->obtenerDatosDashboardApi();
                    break;

                case 'ventas_por_periodo':
                    $this->obtenerVentasPorPeriodoApi();
                    break;

                case 'productos_mas_vendidos':
                    $this->obtenerProductosMasVendidosApi();
                    break;

                case 'ventas_por_vendedor':
                    $this->obtenerVentasPorVendedorApi();
                    break;

                case 'ventas_detalladas':
                    $this->obtenerVentasDetalladasApi();
                    break;

                case 'productos_venta':
                    $this->obtenerProductosVentaApi();
                    break;

                case 'obtener_tasas_conversion':
                    $this->obtenerTasasConversionApi();
                    break;

                case 'actualizar_tasas_conversion':
                    $this->actualizarTasasConversionApi();
                    break;

                case 'distribucion_por_moneda':
                    $this->obtenerDistribucionPorMonedaApi();
                    break;

                case 'estadisticas_distribucion_moneda':
                    $this->obtenerEstadisticasDistribucionApi();
                    break;

                case 'distribucion_por_sectores':
                    $this->obtenerDistribucionPorSectorApi();
                    break;
                case 'distribucion_kilos_vendedor':
                    $this->obtenerDistribucionKilosVendedorApi();
                    break;

                case 'distribucion_credito_contado':
                    $this->obtenerDistribucionCreditoApi();
                    break;

                default:
                    echo json_encode([
                        'success' => false,
                        'error' => 'Acción no válida: ' . $action
                    ]);
            }
        } catch (Exception $e) {
            error_log("Error en API request: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor: ' . $e->getMessage()
            ]);
        }

        return true;
    }

    private function obtenerDistribucionKilosVendedorApi()
    {
        $fechaInicio = !empty($_GET['fecha_inicio']) ? $this->validarFecha($_GET['fecha_inicio']) : null;
        $fechaFin = !empty($_GET['fecha_fin']) ? $this->validarFecha($_GET['fecha_fin']) : null;
        $filtros = $this->construirFiltros();

        try {
            $datos = $this->service->obtenerDistribucionKilosPorVendedor($fechaInicio, $fechaFin, $filtros);

            echo json_encode([
                'success' => true,
                'datos' => $datos,
                'periodo' => $fechaInicio && $fechaFin ? "$fechaInicio al $fechaFin" : 'Ventas históricas válidas',
                'total_vendedores' => count($datos),
                'nota' => 'Distribución de kilos vendidos por vendedor'
            ]);

            $this->logActividad(
                'Distribución kilos por vendedor consultada',
                "Período: " . ($fechaInicio && $fechaFin ? "$fechaInicio - $fechaFin" : "Historial completo")
            );
        } catch (Exception $e) {
            error_log("Error en API distribución kilos vendedor: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener distribución de kilos por vendedor'
            ]);
        }
    }

    private function obtenerDistribucionCreditoApi()
    {
        $fechaInicio = !empty($_GET['fecha_inicio']) ? $this->validarFecha($_GET['fecha_inicio']) : null;
        $fechaFin = !empty($_GET['fecha_fin']) ? $this->validarFecha($_GET['fecha_fin']) : null;
        $filtros = $this->construirFiltros();

        try {
            $datos = $this->service->obtenerDistribucionCredito($fechaInicio, $fechaFin, $filtros);

            echo json_encode([
                'success' => true,
                'datos' => $datos,
                'periodo' => $fechaInicio && $fechaFin ? "$fechaInicio al $fechaFin" : 'Ventas históricas válidas',
                'nota' => 'Distribución por tipo de pago: Crédito vs Contado'
            ]);

            $this->logActividad(
                'Distribución crédito/contado consultada',
                "Período: " . ($fechaInicio && $fechaFin ? "$fechaInicio - $fechaFin" : "Historial completo")
            );
        } catch (Exception $e) {
            error_log("Error en API distribución crédito: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener distribución crédito/contado'
            ]);
        }
    }

    private function obtenerDistribucionPorSectorApi()
    {
        $fechaInicio = !empty($_GET['fecha_inicio']) ? $this->validarFecha($_GET['fecha_inicio']) : null;
        $fechaFin = !empty($_GET['fecha_fin']) ? $this->validarFecha($_GET['fecha_fin']) : null;

        $filtros = $this->construirFiltros();

        try {
            $productos = $this->service->obtenerProductosMasVendidos($fechaInicio, $fechaFin, 50, $filtros);

            if (empty($productos)) {
                echo json_encode([
                    'success' => true,
                    'datos' => [],
                    'mensaje' => 'No hay productos para el período seleccionado',
                    'total_sectores' => 0
                ]);
                return;
            }

            $sectores = [];
            foreach ($productos as $producto) {
                $tipo = trim($producto['tipoproducto']) ?: 'Sin categoría';

                if (!isset($sectores[$tipo])) {
                    $sectores[$tipo] = [
                        'tipo' => $tipo,
                        'cantidad_vendida' => 0,
                        'total_ingresos' => 0,
                        'productos_diferentes' => 0,
                        'ventas_asociadas' => 0
                    ];
                }

                $sectores[$tipo]['cantidad_vendida'] += (float)$producto['cantidad_vendida'];
                $sectores[$tipo]['total_ingresos'] += (float)$producto['total_ingresos'];
                $sectores[$tipo]['productos_diferentes']++;
                $sectores[$tipo]['ventas_asociadas'] += (int)$producto['ventas_asociadas'];
            }

            $sectoresArray = array_values($sectores);
            usort($sectoresArray, function ($a, $b) {
                return $b['total_ingresos'] <=> $a['total_ingresos'];
            });

            $totalIngresos = array_sum(array_column($sectoresArray, 'total_ingresos'));
            foreach ($sectoresArray as &$sector) {
                $sector['porcentaje'] = $totalIngresos > 0 ? ($sector['total_ingresos'] / $totalIngresos) * 100 : 0;
                $sector['total_ingresos_formateado'] = '$' . number_format($sector['total_ingresos'], 2);
                $sector['participacion'] = round($sector['porcentaje'], 1) . '%';
            }

            $topSectores = array_slice($sectoresArray, 0, 8);

            echo json_encode([
                'success' => true,
                'datos' => $topSectores,
                'total_sectores' => count($sectoresArray),
                'top_mostrados' => count($topSectores),
                'total_ingresos_general' => $totalIngresos,
                'periodo' => $fechaInicio && $fechaFin ? "$fechaInicio al $fechaFin" : 'Ventas históricas válidas',
                'nota' => 'Distribución por tipo de producto - Top sectores por ingresos'
            ]);

            $this->logActividad(
                'Distribución por sectores consultada',
                "Período: " . ($fechaInicio && $fechaFin ? "$fechaInicio - $fechaFin" : "Historial completo") .
                    " | Sectores encontrados: " . count($sectoresArray)
            );
        } catch (Exception $e) {
            error_log("Error en API distribución por sectores: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener distribución por sectores',
                'detalle' => $e->getMessage()
            ]);
        }
    }

    private function obtenerEstadisticasDistribucionApi()
    {
        $fechaInicio = !empty($_GET['fecha_inicio']) ? $this->validarFecha($_GET['fecha_inicio']) : null;
        $fechaFin = !empty($_GET['fecha_fin']) ? $this->validarFecha($_GET['fecha_fin']) : null;

        $filtros = $this->construirFiltros();

        try {
            $estadisticas = $this->service->obtenerEstadisticasDistribucion($fechaInicio, $fechaFin, $filtros);

            echo json_encode([
                'success' => true,
                'datos' => $estadisticas,
                'periodo' => $fechaInicio && $fechaFin ? "$fechaInicio al $fechaFin" : 'Ventas históricas válidas',
                'nota' => 'Estadísticas de distribución por moneda'
            ]);

            $this->logActividad(
                'Estadísticas de distribución consultadas',
                "Período: " . ($fechaInicio && $fechaFin ? "$fechaInicio - $fechaFin" : "Historial completo") .
                    " | Monedas activas: " . $estadisticas['total_monedas']
            );
        } catch (Exception $e) {
            error_log("Error en API estadísticas distribución: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener estadísticas de distribución'
            ]);
        }
    }

    private function obtenerDistribucionPorMonedaApi()
    {
        $fechaInicio = !empty($_GET['fecha_inicio']) ? $this->validarFecha($_GET['fecha_inicio']) : null;
        $fechaFin = !empty($_GET['fecha_fin']) ? $this->validarFecha($_GET['fecha_fin']) : null;

        $filtros = $this->construirFiltros();

        try {
            $datos = $this->service->obtenerDistribucionPorMoneda($fechaInicio, $fechaFin, $filtros);

            if (empty($datos)) {
                echo json_encode([
                    'success' => true,
                    'datos' => [],
                    'mensaje' => 'No hay ventas para el período seleccionado',
                    'moneda_base' => 'USD'
                ]);
                return;
            }

            echo json_encode([
                'success' => true,
                'datos' => $datos,
                'moneda_base' => 'USD',
                'periodo' => $fechaInicio && $fechaFin ? "$fechaInicio al $fechaFin" : 'Ventas históricas válidas',
                'total_monedas' => count($datos),
                'nota' => 'Distribución por moneda original - Valores convertidos a USD para comparación'
            ]);

            $this->logActividad(
                'Distribución por moneda consultada',
                "Período: " . ($fechaInicio && $fechaFin ? "$fechaInicio - $fechaFin" : "Historial completo") .
                    " | Monedas encontradas: " . count($datos)
            );
        } catch (Exception $e) {
            error_log("Error en API distribución por moneda: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener distribución por moneda',
                'detalle' => $e->getMessage()
            ]);
        }
    }
    private function obtenerTasasConversionApi()
    {
        try {
            $tasas = $this->repository->obtenerTasasMoneda();

            $mapeoMonedas = [
                'guaraníes' => 'PYG',
                'guaranies' => 'PYG',
                'guarani' => 'PYG',
                'real brasileño' => 'BRL',
                'real brasileno' => 'BRL',
                'real' => 'BRL',
                'reales' => 'BRL',
                'dólares' => 'USD',
                'dolares' => 'USD',
                'dolar' => 'USD',
                'usd' => 'USD'
            ];

            $tasasFormateadas = [];

            foreach ($tasas as $moneda => $valor) {
                $monedaNormalizada = strtolower(trim($moneda));
                $monedaNormalizada = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $monedaNormalizada);

                $codigo = $mapeoMonedas[$monedaNormalizada] ?? strtoupper($moneda);

                $tasasFormateadas[$codigo] = (float)$valor;
            }

            $tasasFormateadas['USD'] = 1.0;

            error_log("DEBUG: Tasas desde BD: " . json_encode($tasas));
            error_log("DEBUG: Tasas formateadas: " . json_encode($tasasFormateadas));

            echo json_encode([
                'success' => true,
                'datos' => $tasasFormateadas,
                'timestamp' => date('Y-m-d H:i:s'),
                'debug_original' => $tasas
            ]);
        } catch (Exception $e) {
            error_log("Error obteniendo tasas de conversión: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener tasas de conversión: ' . $e->getMessage()
            ]);
        }
    }

    private function actualizarTasasConversionApi()
    {
        error_log("DEBUG: REQUEST_METHOD = " . $_SERVER['REQUEST_METHOD']);
        error_log("DEBUG: POST data = " . print_r($_POST, true));

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode([
                'success' => false,
                'error' => 'Método no permitido'
            ]);
            return;
        }

        $tasasJson = $_POST['tasas'] ?? '';
        error_log("DEBUG: tasasJson recibido = " . $tasasJson);

        if (empty($tasasJson)) {
            echo json_encode([
                'success' => false,
                'error' => 'No se recibieron datos de tasas'
            ]);
            return;
        }

        try {
            $tasas = json_decode($tasasJson, true);
            error_log("DEBUG: tasas decodificadas = " . print_r($tasas, true));

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Datos de tasas inválidos: ' . json_last_error_msg());
            }

            $mapeoCodigosANombres = [
                'PYG' => 'Guaraníes',
                'BRL' => 'Real brasileño',
                'USD' => 'Dólares'
            ];

            $tasasParaBD = [];
            foreach ($tasas as $codigo => $valor) {
                if (!is_numeric($valor) || $valor <= 0) {
                    throw new Exception("Tasa inválida para $codigo: $valor");
                }

                $nombreBD = $mapeoCodigosANombres[$codigo] ?? $codigo;
                $tasasParaBD[$nombreBD] = (float)$valor;
            }

            error_log("DEBUG: Tasas para BD: " . print_r($tasasParaBD, true));

            $resultado = $this->repository->actualizarTasasMoneda($tasasParaBD);
            error_log("DEBUG: Resultado actualización = " . ($resultado ? 'true' : 'false'));

            if ($resultado) {
                $this->logActividad('Tasas de conversión actualizadas', json_encode($tasasParaBD));

                echo json_encode([
                    'success' => true,
                    'mensaje' => 'Tasas actualizadas correctamente',
                    'tasas_actualizadas' => $tasas,
                    'debug_bd' => $tasasParaBD
                ]);
            } else {
                throw new Exception('Error al actualizar tasas en la base de datos');
            }
        } catch (Exception $e) {
            error_log("DEBUG ERROR: " . $e->getMessage());
            error_log("DEBUG ERROR TRACE: " . $e->getTraceAsString());

            echo json_encode([
                'success' => false,
                'error' => 'Error al actualizar tasas: ' . $e->getMessage()
            ]);
        }
    }

    private function obtenerDatosDashboardApi()
    {
        $fechaInicio = !empty($_GET['fecha_inicio']) ? $this->validarFecha($_GET['fecha_inicio']) : null;
        $fechaFin = !empty($_GET['fecha_fin']) ? $this->validarFecha($_GET['fecha_fin']) : null;

        $filtros = $this->construirFiltros();

        try {
            $datos = $this->service->obtenerDatosDashboard($fechaInicio, $fechaFin, $filtros);

            $this->logActividad(
                'Dashboard consultado',
                "Período: " . ($fechaInicio && $fechaFin ? "$fechaInicio - $fechaFin" : "Historial completo") .
                    " | Filtros: " . json_encode($filtros) . " (valores en USD)"
            );

            echo json_encode([
                'success' => true,
                'datos' => $datos,
                'moneda' => 'USD',
                'periodo' => $fechaInicio && $fechaFin ? "$fechaInicio al $fechaFin" : 'Ventas históricas válidas',
                'filtros_aplicados' => $filtros,
                'nota' => 'Valores en USD - Excluye: NULL, vacío, "En revision", "Pendiente" - Filtros aplicados'
            ]);
        } catch (Exception $e) {
            error_log("Error en API dashboard: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error al obtener métricas']);
        }
    }

    private function obtenerVentasPorPeriodoApi()
    {
        $fechaInicio = !empty($_GET['fecha_inicio']) ? $this->validarFecha($_GET['fecha_inicio']) : null;
        $fechaFin = !empty($_GET['fecha_fin']) ? $this->validarFecha($_GET['fecha_fin']) : null;
        $agruparPor = $this->validarAgrupacion($_GET['agrupar_por'] ?? 'mes');

        $filtros = $this->construirFiltros();

        try {
            $resultado = $this->service->obtenerVentasPorPeriodo($fechaInicio, $fechaFin, $agruparPor, $filtros);
            echo json_encode([
                'success' => true,
                'datos' => $resultado['datos'] ?? $resultado,
                'moneda' => 'USD',
                'periodo' => $fechaInicio && $fechaFin ? "$fechaInicio al $fechaFin" : 'Ventas históricas válidas',
                'conversion_info' => $resultado['tasas_conversion'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Error en API ventas por período: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error al obtener ventas por período']);
        }
    }

    private function obtenerProductosMasVendidosApi()
    {
        $fechaInicio = !empty($_GET['fecha_inicio']) ? $this->validarFecha($_GET['fecha_inicio']) : null;
        $fechaFin = !empty($_GET['fecha_fin']) ? $this->validarFecha($_GET['fecha_fin']) : null;

        $limite = $this->validarLimite($_GET['limite'] ?? 100);

        $filtros = $this->construirFiltros();

        try {
            $datos = $this->service->obtenerProductosMasVendidos($fechaInicio, $fechaFin, $limite, $filtros);

            echo json_encode([
                'success' => true,
                'datos' => $datos,
                'moneda' => 'USD',
                'periodo' => $fechaInicio && $fechaFin ? "$fechaInicio al $fechaFin" : 'Ventas históricas válidas',
                'total_disponibles' => count($datos),
                'limite_solicitado' => $limite,
                'nota' => 'Productos ampliados para ordenamiento flexible en frontend'
            ]);
        } catch (Exception $e) {
            error_log("Error en API productos más vendidos: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error al obtener productos más vendidos']);
        }
    }

    private function obtenerVentasPorVendedorApi()
    {
        $fechaInicio = !empty($_GET['fecha_inicio']) ? $this->validarFecha($_GET['fecha_inicio']) : null;
        $fechaFin = !empty($_GET['fecha_fin']) ? $this->validarFecha($_GET['fecha_fin']) : null;

        $filtros = $this->construirFiltros();
        unset($filtros['vendedor']);

        try {
            $datos = $this->service->obtenerVentasPorVendedor($fechaInicio, $fechaFin, $filtros);
            echo json_encode([
                'success' => true,
                'datos' => $datos,
                'moneda' => 'USD',
                'periodo' => $fechaInicio && $fechaFin ? "$fechaInicio al $fechaFin" : 'Ventas históricas válidas'
            ]);
        } catch (Exception $e) {
            error_log("Error en API ventas por vendedor: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error al obtener ventas por vendedor']);
        }
    }

    private function obtenerVentasDetalladasApi()
    {
        $fechaInicio = !empty($_GET['fecha_inicio']) ? $this->validarFecha($_GET['fecha_inicio']) : null;
        $fechaFin = !empty($_GET['fecha_fin']) ? $this->validarFecha($_GET['fecha_fin']) : null;
        $filtros = $this->construirFiltros();

        try {
            $datos = $this->service->obtenerVentasDetalladas($fechaInicio, $fechaFin, $filtros);
            echo json_encode([
                'success' => true,
                'datos' => $datos,
                'moneda' => 'USD',
                'periodo' => $fechaInicio && $fechaFin ? "$fechaInicio al $fechaFin" : 'Ventas históricas válidas',
                'nota' => 'Valores convertidos desde múltiples monedas - Excluye estados inválidos'
            ]);
        } catch (Exception $e) {
            error_log("Error en API ventas detalladas: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error al obtener ventas detalladas']);
        }
    }

    public function obtenerDatosFiltros()
    {
        try {
            $datos = [
                'vendedores' => $this->service->obtenerVendedores(),
                'clientes' => $this->service->obtenerClientesConVentas(),
                'estados' => $this->service->obtenerEstadosVentas()
            ];

            return $datos;
        } catch (Exception $e) {
            error_log("Error obteniendo datos de filtros: " . $e->getMessage());
            return [
                'vendedores' => [],
                'clientes' => [],
                'estados' => []
            ];
        }
    }

    public function verificarPermisos($accion = 'ver')
    {
        return true;
    }

    public function obtenerDatosVista()
    {
        try {
            return [
                'titulo' => 'Relatorio de Ventas - Estados Válidos (Excluye Pendientes/Revisión)',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'usuario_id' => $_SESSION['id'] ?? null,
                'es_admin' => true,
                'es_vendedor' => true,
                'puede_ver_todos' => true,
                'moneda_sistema' => 'USD',
                'modo_sin_restricciones' => true,
                'nota_conversion' => 'Valores en USD - Excluye: NULL, vacío, "En revision", "Pendiente"'
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => 'Relatorio de Ventas - Estados Válidos',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => 'Usuario',
                'usuario_id' => null,
                'es_admin' => true,
                'es_vendedor' => true,
                'puede_ver_todos' => true,
                'moneda_sistema' => 'USD',
                'modo_sin_restricciones' => true,
                'nota_conversion' => 'Valores en USD - Excluye: NULL, vacío, "En revision", "Pendiente"'
            ];
        }
    }

    public function obtenerConfiguracionJS()
    {
        $tasasConversion = $this->service->obtenerTasasConversion();

        $tasasJS = [];
        foreach ($tasasConversion as $codigo => $info) {
            $tasasJS[$codigo] = $info['tasa'];
        }

        return [
            'url_base' => $this->urlBase,
            'moneda' => 'USD',
            'simbolo_moneda' => '$',
            'tasas_conversion' => $tasasJS,
            'nota_conversion' => 'Valores en USD - Tasas actualizadas desde BD',
            'fechaActual' => date('Y-m-d'),
            'mesActual' => date('Y-m-01'),
            'esAdmin' => true,
            'puedeVerTodos' => true,
            'usuario' => $_SESSION['nombre'] ?? 'Usuario',
            'usuarioId' => $_SESSION['id'] ?? null
        ];
    }

    public function logActividad($accion, $detalles = null)
    {
        $usuario = $_SESSION['nombre'] ?? 'Sistema';
        $usuarioId = $_SESSION['id'] ?? 'N/A';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "RELATORIO USD - ESTADOS VÁLIDOS - Usuario: {$usuario} (ID: {$usuarioId}) | IP: {$ip} | Acción: {$accion}";

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);
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

    public function obtenerTasasConversion()
    {
        return [
            'PYG_USD' => [
                'tasa' => 7500,
                'descripcion' => 'Guaraníes a Dólares',
                'simbolo_origen' => '₲',
                'simbolo_destino' => '$'
            ],
            'BRL_USD' => [
                'tasa' => 5.55,
                'descripcion' => 'Reales a Dólares',
                'simbolo_origen' => 'R$',
                'simbolo_destino' => '$'
            ],
            'USD_USD' => [
                'tasa' => 1,
                'descripcion' => 'Dólares (sin conversión)',
                'simbolo_origen' => '$',
                'simbolo_destino' => '$'
            ]
        ];
    }

    public function obtenerResumenConfiguracion()
    {
        return [
            'modo' => 'FILTROS_ESTADOS_VALIDOS',
            'descripcion' => 'Ventas con estados válidos únicamente',
            'permisos' => [
                'ver_todas_ventas' => true,
                'ver_todos_vendedores' => true,
                'acceso_reportes' => true
            ],
            'filtros_aplicados' => [
                'restricciones_usuario' => false,
                'filtros_fecha_defecto' => false,
                'estados_excluidos' => ['NULL', 'vacío', 'En revision', 'Pendiente'],
                'monto_minimo' => 'mayor_a_cero'
            ],
            'datos_mostrados' => 'Historial de ventas con estados válidos',
            'moneda_unificada' => 'USD'
        ];
    }

    private function obtenerProductosVentaApi()
    {
        $ventaId = $_GET['venta_id'] ?? null;

        if (!$ventaId || !is_numeric($ventaId)) {
            echo json_encode([
                'success' => false,
                'error' => 'ID de venta no válido'
            ]);
            return;
        }

        try {
            $productos = $this->service->obtenerProductosVenta($ventaId);

            if (empty($productos)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'No se encontraron productos para esta venta',
                    'datos' => []
                ]);
                return;
            }

            $this->logActividad('Productos venta consultados', "Venta ID: $ventaId, Productos encontrados: " . count($productos));

            echo json_encode([
                'success' => true,
                'datos' => $productos,
                'moneda' => 'USD',
                'total_productos' => count($productos),
                'nota' => 'Valores convertidos a USD según moneda original'
            ]);
        } catch (Exception $e) {
            error_log("Error en API productos venta: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener productos de la venta'
            ]);
        }
    }

    private function validarFecha($fecha)
    {
        if (!$fecha) {
            return null;
        }

        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) {
            throw new Exception('Formato de fecha inválido');
        }

        return $fecha;
    }

    private function validarAgrupacion($tipo)
    {
        $permitidos = ['dia', 'semana', 'mes', 'año'];
        return in_array($tipo, $permitidos) ? $tipo : 'mes';
    }

    private function validarLimite($limite)
    {
        $limite = (int)$limite;
        return ($limite > 0 && $limite <= 100) ? $limite : 10;
    }

    private function construirFiltros()
    {
        return [
            'cliente' => trim($_GET['cliente'] ?? ''),
            'vendedor' => $_GET['vendedor'] ?? '',
            'estado' => $_GET['estado'] ?? ''
        ];
    }
}
