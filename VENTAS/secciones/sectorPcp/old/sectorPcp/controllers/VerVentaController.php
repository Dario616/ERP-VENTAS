<?php
require_once 'repository/verVentaRepository.php';
require_once 'services/verVentaService.php';

date_default_timezone_set('America/Asuncion');

class VerVentaController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new VerVentaRepository($conexion);
        $this->service = new VerVentaService($this->repository);
        $this->urlBase = $urlBase;
    }

    public function obtenerVentaParaProcesamiento($idVenta)
    {
        try {
            return $this->service->obtenerVentaParaProcesamiento($idVenta);
        } catch (Exception $e) {
            error_log("Error obteniendo venta para procesamiento: " . $e->getMessage());
            throw new Exception('Venta no encontrada: ' . $e->getMessage());
        }
    }

    public function procesarFormularioVenta($idVenta, $datos)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['error' => 'Método no permitido'];
        }

        $accion = $datos['accion'] ?? '';

        try {
            switch ($accion) {
                case 'procesar':
                    $observaciones = $datos['observaciones_pcp'] ?? '';
                    $resultado = $this->service->procesarVenta($idVenta, $observaciones, $_SESSION['id']);
                    break;
                case 'devolver':
                    $motivo = $datos['motivo_devolucion'] ?? '';
                    if (empty(trim($motivo))) {
                        return ['error' => 'El motivo de devolución es obligatorio'];
                    }
                    $resultado = $this->service->devolverVentaContabilidad($idVenta, $motivo, $_SESSION['id']);
                    break;
                case 'enviar_produccion':
                    $resultado = $this->procesarEnvioProduccion($idVenta, $datos);
                    break;
                case 'enviar_expedicion_stock':
                    $resultado = $this->procesarEnvioExpedicionStock($idVenta, $datos);
                    break;
                // ✅ NUEVO CASO: Finalizar venta
                case 'finalizar_venta':
                    $observaciones = $datos['observaciones_finalizacion'] ?? '';
                    $resultado = $this->service->finalizarVenta($idVenta, $observaciones, $_SESSION['id']);
                    break;
                default:
                    return ['error' => 'Acción no válida'];
            }

            if ($resultado['success']) {
                header("Location: " . $this->urlBase . "secciones/sectorPcp/index.php?mensaje=" . urlencode($resultado['mensaje']));
                exit();
            } else {
                return ['error' => $resultado['error']];
            }
        } catch (Exception $e) {
            error_log("Error procesando formulario venta: " . $e->getMessage());
            return ['error' => 'Error interno del servidor'];
        }
    }

    private function procesarEnvioProduccion($idVenta, $datos)
    {
        try {
            $cantidadesProduccion = $datos['cantidad_produccion'] ?? [];
            $observaciones = $datos['observaciones_produccion'] ?? '';

            return $this->service->enviarProductosProduccion($idVenta, $cantidadesProduccion, $observaciones, $_SESSION['id']);
        } catch (Exception $e) {
            error_log("Error procesando envío a producción: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al enviar productos a producción'];
        }
    }

    private function procesarEnvioExpedicionStock($idVenta, $datos)
    {
        try {
            // ✅ CAMBIO: Trabajar con índices en lugar de nombres directos
            $cantidadesBobinas = $datos['cantidad_bobinas'] ?? [];
            $nombresProductos = $datos['nombre_producto'] ?? [];
            $observaciones = $datos['observaciones_expedicion'] ?? '';

            // LOG DETALLADO para debug
            error_log("=== DEBUG - procesarEnvioExpedicionStock ===");
            error_log("DEBUG - Cantidad bobinas (por índice): " . print_r($cantidadesBobinas, true));
            error_log("DEBUG - Nombres productos (por índice): " . print_r($nombresProductos, true));

            // Validar que tenemos datos válidos
            if (empty($cantidadesBobinas) || empty($nombresProductos)) {
                error_log("ERROR - Datos vacíos - cantidades: " . count($cantidadesBobinas) . ", nombres: " . count($nombresProductos));
                return ['success' => false, 'error' => 'No se especificaron cantidades de bobinas'];
            }

            // ✅ NUEVO: Mapear índices a nombres de productos
            $cantidadesFiltradas = [];
            foreach ($cantidadesBobinas as $indice => $cantidad) {
                $cantidadInt = (int)$cantidad;

                if (!isset($nombresProductos[$indice])) {
                    error_log("ERROR - No se encontró nombre para índice: $indice");
                    continue;
                }

                $nombreProducto = $nombresProductos[$indice];

                error_log("DEBUG - Índice: $indice | Producto: '$nombreProducto' | Cantidad: $cantidadInt");

                if ($cantidadInt > 0) {
                    $cantidadesFiltradas[$nombreProducto] = $cantidadInt;
                    error_log("SUCCESS - Producto '$nombreProducto' agregado con cantidad: $cantidadInt");
                } else {
                    error_log("SKIP - Producto '$nombreProducto' omitido (cantidad: $cantidadInt)");
                }
            }

            error_log("DEBUG - Cantidades filtradas finales: " . print_r($cantidadesFiltradas, true));

            if (empty($cantidadesFiltradas)) {
                error_log("ERROR - No se especificaron cantidades válidas después del filtrado");
                return ['success' => false, 'error' => 'No se especificaron cantidades válidas. Verificar que los valores sean números enteros mayores a 0.'];
            }

            error_log("SUCCESS - Procesando reservas de stock para " . count($cantidadesFiltradas) . " productos");

            return $this->service->crearReservasStock($idVenta, $cantidadesFiltradas, $observaciones, $_SESSION['id']);
        } catch (Exception $e) {
            error_log("ERROR - Exception en procesarEnvioExpedicionStock: " . $e->getMessage());
            error_log("ERROR - Stack trace: " . $e->getTraceAsString());
            return ['success' => false, 'error' => 'Error al procesar reservas de stock: ' . $e->getMessage()];
        }
    }
    public function verificarPermisos($accion, $idVenta = null)
    {
        $esAdmin = $this->esAdministrador();
        $esPcp = $this->esPcp();

        switch ($accion) {
            case 'ver':
            case 'procesar':
            case 'devolver':
            case 'enviar_produccion':
            case 'enviar_expedicion':
            case 'finalizar_venta': // ✅ AGREGADO
                return $esAdmin || $esPcp;
            default:
                return false;
        }
    }

    private function esAdministrador()
    {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === '1';
    }

    private function esPcp()
    {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === '4';
    }

    public function formatearMoneda($monto, $moneda)
    {
        $simbolo = $this->service->obtenerSimboloMoneda($moneda);
        $numeroFormateado = $this->service->formatearNumero($monto);

        return $simbolo . ' ' . $numeroFormateado;
    }

    public function logActividad($accion, $detalles = null)
    {
        $usuario = $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "VerVenta - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);
    }

    public function obtenerConfiguracionJS()
    {
        return [
            'urlBase' => $this->urlBase,
            'usuario' => $_SESSION['nombre'] ?? 'Usuario',
            'esAdmin' => $this->esAdministrador(),
            'esPcp' => $this->esPcp(),
            'debug' => isset($_GET['debug']),
            'rol' => $_SESSION['rol'] ?? '0'
        ];
    }

    public function obtenerDatosVista($pagina = 'procesar_venta')
    {
        try {
            return [
                'titulo' => 'Procesar Venta - PCP',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => $this->esAdministrador(),
                'pagina' => $pagina
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => 'Procesar Venta - PCP',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => false,
                'pagina' => $pagina
            ];
        }
    }

    public function handleApiRequest()
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        header('Content-Type: application/json');

        switch ($_GET['action']) {
            case 'obtener_resumen_stock_agregado':
                $this->obtenerResumenStockAgregadoApi();
                break;
            case 'cancelar_reservas':
                $this->cancelarReservasApi();
                break;
            case 'calcular_paquetes_necesarios':
                $this->calcularPaquetesNecesariosApi();
                break;
            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

        return true;
    }

    private function obtenerResumenStockAgregadoApi()
    {
        try {
            $filtros = [];
            if (!empty($_GET['producto'])) {
                $filtros['nombre_producto'] = $_GET['producto'];
            }

            $resumen = $this->repository->obtenerResumenStockAgregado($filtros);

            echo json_encode([
                'success' => true,
                'stock_agregado' => $resumen,
                'total_productos' => count($resumen)
            ]);
        } catch (Exception $e) {
            error_log("Error en API resumen stock agregado: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error obteniendo resumen de stock agregado'
            ]);
        }
    }

    private function cancelarReservasApi()
    {
        $idVenta = $_POST['id_venta'] ?? null;

        if (!$idVenta) {
            echo json_encode([
                'success' => false,
                'error' => 'ID de venta no proporcionado'
            ]);
            return;
        }

        try {
            $resultado = $this->service->cancelarReservasVenta($idVenta, $_SESSION['id']);
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en API cancelar reservas: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    /**
     * NUEVA API: Calcular paquetes necesarios basándose en bobinas solicitadas
     */
    private function calcularPaquetesNecesariosApi()
    {
        try {
            $nombreProducto = $_GET['producto'] ?? '';
            $bobinasSolicitadas = (int)($_GET['bobinas'] ?? 0);

            if (empty($nombreProducto) || $bobinasSolicitadas <= 0) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Parámetros inválidos'
                ]);
                return;
            }

            // Obtener información del producto desde stock_agregado
            $sql = "SELECT bobinas_pacote, cantidad_disponible, tipo_producto 
                    FROM stock_agregado 
                    WHERE nombre_producto = :nombre_producto 
                        AND cantidad_disponible > 0
                    ORDER BY cantidad_disponible DESC
                    LIMIT 1";

            $stmt = $this->repository->getConexion()->prepare($sql);
            $stmt->bindParam(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
            $stmt->execute();
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$producto) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Producto no encontrado o sin stock'
                ]);
                return;
            }

            $bobinasPorPaquete = (int)$producto['bobinas_pacote'];
            $paquetesDisponibles = (int)$producto['cantidad_disponible'];

            // Calcular paquetes necesarios
            $paquetesNecesarios = ceil($bobinasSolicitadas / $bobinasPorPaquete);
            $bobinasTotalesReservadas = $paquetesNecesarios * $bobinasPorPaquete;
            $bobinasExcedente = $bobinasTotalesReservadas - $bobinasSolicitadas;

            $disponible = $paquetesNecesarios <= $paquetesDisponibles;

            echo json_encode([
                'success' => true,
                'bobinas_solicitadas' => $bobinasSolicitadas,
                'bobinas_por_paquete' => $bobinasPorPaquete,
                'paquetes_necesarios' => $paquetesNecesarios,
                'paquetes_disponibles' => $paquetesDisponibles,
                'bobinas_totales_reservadas' => $bobinasTotalesReservadas,
                'bobinas_excedente' => $bobinasExcedente,
                'disponible' => $disponible,
                'mensaje' => $disponible
                    ? "Se reservarán $paquetesNecesarios paquetes ($bobinasTotalesReservadas bobinas) para cubrir $bobinasSolicitadas bobinas solicitadas"
                    : "Stock insuficiente. Necesita $paquetesNecesarios paquetes, disponibles: $paquetesDisponibles"
            ]);
        } catch (Exception $e) {
            error_log("Error calculando paquetes necesarios: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }
}

if (!file_exists('repository/verVentaRepository.php') || !file_exists('services/verVentaService.php')) {
    die("Error: Faltan archivos del sistema MVC específicos para VerVenta.");
}
