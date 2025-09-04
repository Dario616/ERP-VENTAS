<?php
require_once 'repository/expedicionesDespachadasRepository.php';
require_once 'services/expedicionesDespachadasService.php';

date_default_timezone_set('America/Asuncion');

class ExpedicionesDespachadasController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new ExpedicionesDespachadasRepository($conexion);
        $this->service = new ExpedicionesDespachadasService($this->repository);
        $this->urlBase = $urlBase;
    }

    public function handleRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['accion'])) {
            return false;
        }

        header('Content-Type: application/json');

        try {
            switch ($_POST['accion']) {
                case 'obtener_items_expedicion':
                    $this->obtenerItemsExpedicion();
                    break;

                default:
                    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            }
        } catch (Exception $e) {
            error_log("Error en ExpedicionesDespachadasController: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
        }

        return true;
    }

    public function obtenerExpedicionesDespachadas($fechaInicio, $fechaFin, $transportista, $codigoExpedicion, $pagina, $porPagina)
    {
        try {
            $resultado = $this->service->obtenerDatosVista($fechaInicio, $fechaFin, $transportista, $codigoExpedicion, $pagina, $porPagina);

            if ($resultado['success']) {
                $filtrosTexto = [];
                if ($fechaInicio) $filtrosTexto[] = "Desde: {$fechaInicio}";
                if ($fechaFin) $filtrosTexto[] = "Hasta: {$fechaFin}";
                if ($transportista) $filtrosTexto[] = "Transportista: {$transportista}";
                if ($codigoExpedicion) $filtrosTexto[] = "Código: {$codigoExpedicion}"; // NUEVO

                $filtrosDescripcion = empty($filtrosTexto) ? 'Sin filtros' : implode(', ', $filtrosTexto);

                $this->logActividad(
                    'Consultar expediciones despachadas con datos básicos',
                    null,
                    $_SESSION['nombre'] ?? 'Sistema',
                    "{$filtrosDescripcion}, Total: {$resultado['total']}"
                );
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("Error obteniendo expediciones despachadas: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'expediciones' => [],
                'total' => 0,
                'estadisticas' => [],
                'transportistas' => []
            ];
        }
    }

    private function obtenerItemsExpedicion()
    {
        try {
            $numeroExpedicion = trim($_POST['numero_expedicion'] ?? '');

            if (empty($numeroExpedicion)) {
                throw new Exception('Número de expedición requerido');
            }

            $resultado = $this->service->obtenerItemsExpedicion($numeroExpedicion);
            echo json_encode($resultado);

            if ($resultado['success']) {
                $this->logActividad(
                    'Ver items de expedición despachada',
                    $numeroExpedicion,
                    $_SESSION['nombre'] ?? 'Sistema',
                    "Items: " . count($resultado['items']) .
                        ", Peso bruto: " . ($resultado['estadisticas']['peso_total_bruto_formateado'] ?? 'N/A')
                );
            }
        } catch (Exception $e) {
            error_log("Error obteniendo items de expedición: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function obtenerConfiguracion()
    {
        return [
            'registros_por_pagina_defecto' => 10,
            'registros_por_pagina_opciones' => [5, 10, 20, 50],
            'sin_filtros_fecha_defecto' => true,
            'formatos_fecha' => [
                'entrada' => 'Y-m-d',
                'visualizacion' => 'd/m/Y',
                'completa' => 'd/m/Y H:i'
            ],
            'campos_basicos_sist_prod_stock' => [
                'peso_bruto_liquido' => true,
                'tara' => true,
                'metragem' => true,
                'bobinas_pacote' => true,
                'largura' => true,
                'gramatura' => true,
                'id_orden_produccion' => true,
                'id_venta' => true,
                'usuario_produccion' => true,
                'fecha_produccion' => true,
                'tipo_producto' => true
            ],
            'version_modulo' => '2.1-simple-fixed',
            'autor' => 'Sistema de Expediciones America TNT',
            'descripcion' => 'Módulo SIMPLE para consulta de expediciones despachadas con datos básicos de producción - CORREGIDO búsqueda por código',
            'caracteristicas' => [
                'datos_basicos_sist_prod_stock',
                'formateo_simple_numeros',
                'busqueda_por_codigo_expedicion',
                'sin_exportacion_csv',
                'sin_reportes_complejos',
                'estadisticas_simples'
            ]
        ];
    }

    public function logActividad($accion, $expedicion = null, $usuario = null, $detalles = null)
    {
        $usuario = $usuario ?? $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "EXPEDICIONES DESPACHADAS SIMPLE v2.1-fixed - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($expedicion) {
            $mensaje .= " | Expedición: {$expedicion}";
        }

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);

        try {
            $sql = "INSERT INTO sist_expediciones_log 
                    (accion, numero_expedicion, usuario, ip_address, detalles) 
                    VALUES (:accion, :numero_expedicion, :usuario, :ip, :detalles)";

            $stmt = $this->repository->getConexion()->prepare($sql);
            $stmt->bindParam(':accion', $accion, PDO::PARAM_STR);
            $stmt->bindParam(':numero_expedicion', $expedicion, PDO::PARAM_STR);
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
            $stmt->bindParam(':ip', $ip, PDO::PARAM_STR);
            $stmt->bindParam(':detalles', $detalles, PDO::PARAM_STR);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Error guardando log en BD: " . $e->getMessage());
        }
    }
}
