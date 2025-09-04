<?php
require_once 'repository/despachoRepository.php';
require_once 'services/despachoServices.php';

date_default_timezone_set('America/Asuncion');

class DespachoController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new DespachoRepository($conexion);
        $this->service = new DespachoServices($this->repository);
        $this->urlBase = $urlBase;
    }

    public function handleRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['accion'])) {
            return false;
        }

        header('Content-Type: application/json');
        $usuario = $_SESSION['nombre'] ?? 'Sistema';

        try {
            switch ($_POST['accion']) {
                case 'crear_expedicion':
                    $this->crearExpedicion($usuario);
                    break;

                case 'escanear_item':
                    $this->escanearItem($usuario);
                    break;

                case 'despachar_expedicion':
                    $this->despacharExpedicion($usuario);
                    break;

                case 'obtener_vista_previa_clientes':
                    $this->obtenerVistaPreviaClientes();
                    break;

                case 'obtener_items_por_cliente':
                    $this->obtenerItemsPorCliente();
                    break;

                case 'obtener_resumen_expedicion':
                    $this->obtenerResumenExpedicion();
                    break;

                case 'mover_item_a_cliente':
                    $this->moverItemACliente($usuario);
                    break;

                case 'obtener_clientes_disponibles':
                    $this->obtenerClientesDisponibles();
                    break;

                case 'obtener_clientes_misma_rejilla':
                    $this->obtenerClientesMismaRejilla();
                    break;

                case 'obtener_asignaciones_rejilla':
                    $this->obtenerAsignacionesRejilla();
                    break;

                case 'obtener_items_desconocidos':
                    $this->obtenerItemsDesconocidos();
                    break;

                case 'obtener_info_expedicion':
                    $this->obtenerInfoExpedicion();
                    break;

                case 'estado_sistema':
                    $this->obtenerEstadoSistema();
                    break;
                case 'obtener_items_escaneados_detallados':
                    $this->obtenerItemsEscaneadosDetallados();
                    break;

                case 'eliminar_item_escaneado':
                    $this->eliminarItemEscaneado($usuario);
                    break;

                case 'eliminar_multiples_items':
                    $this->eliminarMultiplesItems($usuario);
                    break;
                case 'eliminar_expedicion':
                    $this->eliminarExpedicion($usuario);
                    break;

                default:
                    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            }
        } catch (Exception $e) {
            error_log("Error en handleRequest: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
        }

        return true;
    }

    private function crearExpedicion($usuario)
    {
        try {
            $this->validarDatosExpedicion($_POST);

            $datos = [
                'transportista' => trim($_POST['transportista'] ?? ''),
                'conductor' => trim($_POST['conductor'] ?? ''),
                'placa' => trim($_POST['placa'] ?? ''),
                'destino' => trim($_POST['destino'] ?? ''),
                'peso' => trim($_POST['peso'] ?? ''),
                'tipovehiculo' => trim($_POST['tipovehiculo'] ?? ''),
                'id_rejilla' => (int)($_POST['id_rejilla'] ?? 0),
                'usuario' => $usuario
            ];

            $resultado = $this->service->crearNuevaExpedicion($datos);
            echo json_encode($resultado);

            if ($resultado['success']) {
                $this->logActividad(
                    'Crear expedición con validación rejilla - Solo automático',
                    $resultado['numero_expedicion'],
                    $usuario,
                    "Expedición creada para Rejilla #{$resultado['rejilla_info']['numero_rejilla']}"
                );
            }
        } catch (Exception $e) {
            error_log("Error creando expedición: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function eliminarExpedicion($usuario)
    {
        try {
            $numeroExpedicion = trim($_POST['numero_expedicion'] ?? '');

            if (empty($numeroExpedicion)) {
                throw new Exception('Número de expedición requerido');
            }

            $resultado = $this->service->eliminarExpedicion($numeroExpedicion, $usuario);
            echo json_encode($resultado);

            if ($resultado['success']) {
                $this->logActividad(
                    'Eliminar expedición',
                    $numeroExpedicion,
                    $usuario,
                    "Expedición eliminada con {$resultado['items_eliminados']} items"
                );
            }
        } catch (Exception $e) {
            error_log("Error eliminando expedición: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function obtenerInfoExpedicion()
    {
        try {
            $numeroExpedicion = trim($_POST['numero_expedicion'] ?? '');

            if (empty($numeroExpedicion)) {
                throw new Exception('Número de expedición requerido');
            }

            $expedicion = $this->repository->verificarExpedicion($numeroExpedicion);

            if (!$expedicion) {
                throw new Exception('Expedición no encontrada');
            }

            echo json_encode([
                'success' => true,
                'expedicion' => [
                    'numero_expedicion' => $expedicion['numero_expedicion'],
                    'estado' => $expedicion['estado'],
                    'id_rejilla' => $expedicion['id_rejilla'],
                    'numero_rejilla' => $expedicion['numero_rejilla'],
                    'transportista' => $expedicion['transportista'],
                    'validacion_rejilla_activa' => true,
                    'solo_modo_automatico' => true
                ],
                'mensaje' => "Validación de rejilla OBLIGATORIA para Rejilla #{$expedicion['numero_rejilla']}"
            ]);
        } catch (Exception $e) {
            error_log("Error obteniendo info de expedición: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function obtenerVistaPreviaClientes()
    {
        try {
            $numeroExpedicion = trim($_POST['numero_expedicion'] ?? '');

            if (empty($numeroExpedicion)) {
                throw new Exception('Número de expedición requerido');
            }

            $resumen = $this->service->obtenerResumenExpedicion($numeroExpedicion);

            if ($resumen['success']) {
                $expedicion = $this->repository->verificarExpedicion($numeroExpedicion);

                echo json_encode([
                    'success' => true,
                    'clientes' => $resumen['clientes'],
                    'estadisticas' => $resumen['estadisticas'],
                    'expedicion' => $numeroExpedicion,
                    'rejilla_info' => [
                        'numero_rejilla' => $expedicion['numero_rejilla'],
                        'validacion_activa' => true,
                        'solo_modo_automatico' => true
                    ],
                    'timestamp' => date('Y-m-d H:i:s'),
                    'modo' => 'vista_previa_con_validacion_rejilla_solo_automatico'
                ]);
            } else {
                throw new Exception($resumen['error']);
            }
        } catch (Exception $e) {
            error_log("Error obteniendo vista previa: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function obtenerResumenExpedicion()
    {
        try {
            $numeroExpedicion = trim($_POST['numero_expedicion'] ?? '');

            if (empty($numeroExpedicion)) {
                throw new Exception('Número de expedición requerido');
            }

            $resumen = $this->service->obtenerResumenExpedicion($numeroExpedicion);
            echo json_encode($resumen);
        } catch (Exception $e) {
            error_log("Error obteniendo resumen: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function escanearItem($usuario)
    {
        try {
            $numeroExpedicion = trim($_POST['numero_expedicion'] ?? '');
            $idItem = (int)($_POST['id_item'] ?? 0);

            if (empty($numeroExpedicion)) {
                throw new Exception('Número de expedición requerido');
            }

            if ($idItem <= 0) {
                throw new Exception('ID de item inválido');
            }

            $resultado = $this->service->procesarEscaneoItem($numeroExpedicion, $idItem, $usuario);

            if ($resultado['success']) {
                $resultado['timestamp'] = time();
                $resultado['version_sistema'] = '7.1-FLEXIBLE';
                $resultado['validacion_rejilla_obligatoria'] = true;
                $resultado['solo_modo_automatico'] = true;
                $resultado['sistema_flexible'] = true; // 🆕
            }

            echo json_encode($resultado);

            if ($resultado['success']) {
                $detalles = "Item ID: $idItem - {$resultado['item']['numero_item']} - Cliente: {$resultado['item']['cliente']}";

                // 🆕 LOGS DE FLEXIBILIDAD
                if (isset($resultado['flexibilidad']) && $resultado['flexibilidad']['reserva_cancelada']) {
                    $infoCancelacion = $resultado['flexibilidad']['info_cancelacion'];
                    $detalles .= " - 🔄 FLEXIBILIDAD APLICADA: Reserva #{$infoCancelacion['reserva_cancelada']} cancelada automáticamente";
                    $detalles .= " (Cliente anterior: {$infoCancelacion['cliente_anterior']}, Bobinas liberadas: {$infoCancelacion['bobinas_liberadas']})";
                }

                if (isset($resultado['validacion_rejilla'])) {
                    $rejillaInfo = $resultado['validacion_rejilla'];
                    $detalles .= " - Rejilla: #{$rejillaInfo['rejilla_item']} ✓ VALIDADA";
                }

                if ($resultado['item']['es_desconocido']) {
                    $detalles .= " (DESCONOCIDO)";

                    // Diferentes tipos de DESCONOCIDO
                    $modoAsignacion = $resultado['item']['modo_asignacion'];
                    if ($modoAsignacion === 'desconocido_flexible') {
                        $detalles .= " - TIPO: Liberado de reserva sin asignación disponible";
                    } elseif ($modoAsignacion === 'desconocido_fuera_rejilla') {
                        $detalles .= " - TIPO: Fuera de rejilla";
                    }
                } else {
                    $detalles .= " - Origen: {$resultado['info_asignacion']['origen']}";
                    $detalles .= " - Modo: AUTOMÁTICO";

                    // Si fue asignado tras liberar reserva
                    if ($resultado['info_asignacion']['modo_asignacion'] === 'automatico_flexible') {
                        $detalles .= " - FLEXIBLE: Asignado tras cancelar reserva conflictiva";
                    }

                    if (isset($resultado['info_asignacion']['info_despacho'])) {
                        $infoDespacho = $resultado['info_asignacion']['info_despacho'];
                        if (isset($infoDespacho['ya_despachado']) && $infoDespacho['ya_despachado'] > 0) {
                            $detalles .= " - Ya despachado anteriormente: {$infoDespacho['ya_despachado']}";
                        }
                        if (isset($infoDespacho['disponible'])) {
                            $detalles .= " - Disponible: {$infoDespacho['disponible']}";
                        }
                    }
                }

                $accionLog = 'Escanear item con flexibilidad total - Sistema automático';

                $this->logActividad(
                    $accionLog,
                    $numeroExpedicion,
                    $usuario,
                    $detalles
                );
            } else {
                $tipoError = $resultado['tipo_error'] ?? 'DESCONOCIDO';
                $detalles = "Item ID: $idItem - Error: {$resultado['error']} - Tipo: $tipoError";

                if (in_array($tipoError, ['ITEM_REJILLA_INCORRECTA', 'ITEM_NO_ENCONTRADO'])) {
                    $detalles .= " - VALIDACIÓN REJILLA OBLIGATORIA FALLIDA";
                }

                $this->logActividad(
                    'Error escaneado con sistema flexible',
                    $numeroExpedicion,
                    $usuario,
                    $detalles
                );
            }
        } catch (Exception $e) {
            error_log("Error escaneando item: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'tipo_error' => 'EXCEPTION',
                'codigo_error' => 'EXC001'
            ]);
        }
    }

    private function obtenerItemsPorCliente()
    {
        try {
            $numeroExpedicion = trim($_POST['numero_expedicion'] ?? '');

            if (empty($numeroExpedicion)) {
                throw new Exception('Número de expedición requerido');
            }

            $itemsPorCliente = $this->service->obtenerVistaPreviaClientes($numeroExpedicion);
            $expedicion = $this->repository->verificarExpedicion($numeroExpedicion);

            echo json_encode([
                'success' => true,
                'items_por_cliente' => $itemsPorCliente,
                'total_clientes' => count($itemsPorCliente),
                'expedicion' => $numeroExpedicion,
                'estado_expedicion' => $expedicion['estado'] ?? 'DESCONOCIDO',
                'rejilla_info' => [
                    'numero_rejilla' => $expedicion['numero_rejilla'],
                    'validacion_obligatoria' => true,
                    'solo_modo_automatico' => true
                ],
                'timestamp' => date('Y-m-d H:i:s'),
                'modo' => 'sistema_con_validacion_obligatoria_solo_automatico'
            ]);
        } catch (Exception $e) {
            error_log("Error obteniendo items por cliente: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function obtenerItemsDesconocidos()
    {
        try {
            $numeroExpedicion = trim($_POST['numero_expedicion'] ?? '');

            if (empty($numeroExpedicion)) {
                throw new Exception('Número de expedición requerido');
            }

            $itemsIds = $this->repository->obtenerIdsItemsDesconocidos($numeroExpedicion);

            echo json_encode([
                'success' => true,
                'items_ids' => $itemsIds,
                'total_items' => count($itemsIds),
                'expedicion' => $numeroExpedicion
            ]);
        } catch (Exception $e) {
            error_log("Error obteniendo items DESCONOCIDOS: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function moverItemACliente($usuario)
    {
        try {
            $idExpedicionItem = (int)($_POST['id_expedicion_item'] ?? 0);
            $nuevoCliente = trim($_POST['nuevo_cliente'] ?? '');
            $idVenta = !empty($_POST['id_venta']) ? (int)$_POST['id_venta'] : null;

            if ($idExpedicionItem <= 0) {
                throw new Exception('ID de expedición item inválido');
            }

            if (empty($nuevoCliente)) {
                throw new Exception('Cliente destino es requerido');
            }

            $resultado = $this->service->moverItemACliente($idExpedicionItem, $nuevoCliente, $idVenta);
            echo json_encode($resultado);

            if ($resultado['success']) {
                $detallesLog = "Item DESCONOCIDO ID: $idExpedicionItem reasignado a cliente: $nuevoCliente";

                if ($idVenta) {
                    $detallesLog .= " (ID Venta: $idVenta)";
                }

                $detallesLog .= " - Actualizada expedición (producto se actualizará al despachar)";

                $this->logActividad(
                    'Reasignar item DESCONOCIDO - Solo expedición',
                    null,
                    $usuario,
                    $detallesLog
                );
            }
        } catch (Exception $e) {
            error_log("Error reasignando item: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function obtenerClientesDisponibles()
    {
        try {
            $clientes = $this->service->obtenerClientesDisponibles();
            echo json_encode([
                'success' => true,
                'clientes' => $clientes,
                'total_clientes' => count($clientes),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error obteniendo clientes: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function obtenerClientesMismaRejilla()
    {
        try {
            $numeroExpedicion = trim($_POST['numero_expedicion'] ?? '');

            if (empty($numeroExpedicion)) {
                throw new Exception('Número de expedición requerido');
            }

            $clientes = $this->service->obtenerClientesMismaRejilla($numeroExpedicion);
            echo json_encode([
                'success' => true,
                'clientes' => $clientes,
                'total_clientes' => count($clientes),
                'expedicion' => $numeroExpedicion,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error obteniendo clientes de la misma rejilla: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function despacharExpedicion($usuario)
    {
        try {
            $numeroExpedicion = trim($_POST['numero_expedicion'] ?? '');

            if (empty($numeroExpedicion)) {
                throw new Exception('Número de expedición requerido');
            }

            $resultado = $this->service->procesarDespacho($numeroExpedicion, $usuario);
            echo json_encode($resultado);

            if ($resultado['success']) {
                $detallesLog = "Items despachados: " . ($resultado['total_items'] ?? 0) .
                    " - Peso total: " . ($resultado['total_peso_kg'] ?? 0) . " kg" .
                    " - Rejilla: #{$resultado['rejilla']}" .
                    " - Items con asignación: " . ($resultado['items_con_asignacion'] ?? 0) .
                    " - Items DESCONOCIDOS: " . ($resultado['items_desconocidos_despachados'] ?? 0) .
                    " - Items fuera de rejilla: " . ($resultado['items_fuera_de_rejilla_despachados'] ?? 0) .
                    " - Asignaciones actualizadas: " . ($resultado['asignaciones_rejilla_actualizadas'] ?? 0) .
                    " - Sistema: FLEXIBLE con validación obligatoria de rejilla";

                if (isset($resultado['ventas_actualizadas']) && !empty($resultado['ventas_actualizadas'])) {
                    $totalVentas = $resultado['total_ventas_actualizadas'] ?? 0;
                    $ventasIds = implode(', ', $resultado['ventas_actualizadas']);
                    $detallesLog .= " - VENTAS ACTUALIZADAS: {$totalVentas} venta(s) [IDs: {$ventasIds}]";
                } else {
                    $detallesLog .= " - SIN VENTAS ASOCIADAS para actualizar";
                }

                // 🆕 INFO DE FLEXIBILIDAD EN DESPACHO
                $detallesLog .= " - FLEXIBILIDAD: Reservas canceladas automáticamente durante escaneado";

                $this->logActividad(
                    'Despachar expedición con sistema flexible y actualización de asignaciones/ventas',
                    $numeroExpedicion,
                    $usuario,
                    $detallesLog
                );
            }
        } catch (Exception $e) {
            error_log("Error despachando expedición: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function obtenerAsignacionesRejilla()
    {
        try {
            $idRejilla = (int)($_POST['id_rejilla'] ?? 0);

            if ($idRejilla <= 0) {
                throw new Exception('ID de rejilla inválido');
            }

            $asignaciones = $this->repository->obtenerAsignacionesRejilla($idRejilla);
            echo json_encode([
                'success' => true,
                'asignaciones' => $asignaciones,
                'total_asignaciones' => count($asignaciones),
                'rejilla_id' => $idRejilla
            ]);
        } catch (Exception $e) {
            error_log("Error obteniendo asignaciones de rejilla: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function obtenerEstadoSistema()
    {
        try {
            $estado = $this->service->validarEstadoSistema();
            echo json_encode([
                'success' => true,
                'estado' => $estado,
                'timestamp' => date('Y-m-d H:i:s'),
                'version_sistema' => '7.0',
                'arquitectura' => 'Sistema con validación OBLIGATORIA de rejilla - Solo modo automático'
            ]);
        } catch (Exception $e) {
            error_log("Error obteniendo estado: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function validarDatosExpedicion($datos)
    {
        if (empty(trim($datos['transportista'] ?? ''))) {
            throw new Exception('El transportista es requerido');
        }

        if (empty($datos['id_rejilla'])) {
            throw new Exception('La rejilla es obligatoria para crear una expedición');
        }

        if (!is_numeric($datos['id_rejilla']) || $datos['id_rejilla'] <= 0) {
            throw new Exception('Debe seleccionar una rejilla válida');
        }

        if (!empty($datos['peso']) && (!is_numeric($datos['peso']) || $datos['peso'] < 0)) {
            throw new Exception('El peso debe ser un número válido');
        }

        if (!empty($datos['placa']) && strlen(trim($datos['placa'])) > 50) {
            throw new Exception('La placa no puede exceder 50 caracteres');
        }

        return true;
    }

    public function obtenerDatosVista()
    {
        try {
            $expedicionesAbiertas = $this->repository->obtenerExpedicionesAbiertas();
            $rejillasInfo = $this->service->obtenerRejillasConAsignaciones();

            $expedicionesAbiertas = $this->service->enriquecerExpediciones($expedicionesAbiertas);
            $estadoSistema = $this->service->validarEstadoSistema();

            return [
                'expediciones_abiertas' => $expedicionesAbiertas,
                'rejillas_info' => $rejillasInfo,
                'estadisticas' => $this->service->calcularEstadisticas($expedicionesAbiertas),
                'transportistas' => $this->service->obtenerTransportistas(),
                'tipos_vehiculo' => $this->service->obtenerTiposVehiculo(),
                'estado_sistema' => $estadoSistema
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'expediciones_abiertas' => [],
                'rejillas_info' => [],
                'estadisticas' => ['total_expediciones' => 0, 'total_items' => 0, 'total_rejillas' => 0],
                'transportistas' => [],
                'tipos_vehiculo' => [],
                'estado_sistema' => ['sistema_operativo' => false, 'mensaje' => 'Error en el sistema']
            ];
        }
    }

    public function logActividad($accion, $expedicion = null, $usuario = null, $detalles = null)
    {
        $usuario = $usuario ?? $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $infoSistema = [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido',
            'metodo' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'timestamp' => $timestamp,
            'version_sistema' => '7.0',
            'arquitectura' => 'validacion_rejilla_obligatoria_solo_automatico'
        ];

        $mensaje = "EXPEDICION v7.0 - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($expedicion) {
            $mensaje .= " | Expedición: {$expedicion}";
        }

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);

        try {
            $detallesCompletos = $detalles ? $detalles . ' | ' . json_encode($infoSistema) : json_encode($infoSistema);
            $this->repository->registrarLog($accion, $expedicion, $usuario, $ip, $detallesCompletos);
        } catch (Exception $e) {
            error_log("Error guardando log en BD: " . $e->getMessage());
        }
    }

    public function obtenerConfiguracion()
    {
        return [
            'items_por_pagina' => 10,
            'max_items_por_expedicion' => 1000,
            'tipos_estado' => ['ABIERTA', 'DESPACHADA'],
            'formatos_fecha' => [
                'corta' => 'd/m/Y',
                'completa' => 'd/m/Y H:i',
                'sistema' => 'Y-m-d H:i:s'
            ],
            'validaciones' => [
                'requiere_rejilla_obligatoria' => true,
                'validacion_rejilla_en_escaneo' => true,
                'prevenir_duplicados' => true,
                'validar_estado_expedicion' => true,
                'asignacion_automatica_cliente' => true,
                'permitir_reasignacion_desconocidos' => true,
                'solo_modo_automatico' => true,
                'logica_desconocidos_activa' => true,
                'reasignar_solo_misma_rejilla' => true,
                'actualizacion_rejillas_automatica' => true,
                'rastreo_despachos_detallado' => true,
                'considerar_despachos_anteriores' => true,

                // 🆕 CONFIGURACIONES DE FLEXIBILIDAD
                'sistema_flexible' => true,
                'cancelar_reservas_conflictivas' => true,
                'liberar_items_automaticamente' => true,
                'permite_escaneado_universal' => true,
                'trazabilidad_reservas_canceladas' => true
            ],
            'version_sistema' => '7.1-FLEXIBLE',
            'ultima_actualizacion' => '2025-01-30',
            'arquitectura' => 'Sistema FLEXIBLE con validación obligatoria de rejilla y cancelación automática de reservas conflictivas'
        ];
    }

    private function obtenerItemsEscaneadosDetallados()
    {
        try {
            $numeroExpedicion = trim($_POST['numero_expedicion'] ?? '');

            if (empty($numeroExpedicion)) {
                throw new Exception('Número de expedición requerido');
            }

            $items = $this->repository->obtenerItemsEscaneadosDetallados($numeroExpedicion);

            echo json_encode([
                'success' => true,
                'items' => $items,
                'total_items' => count($items),
                'expedicion' => $numeroExpedicion,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error obteniendo items escaneados: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function eliminarItemEscaneado($usuario)
    {
        try {
            $idExpedicionItem = (int)($_POST['id_expedicion_item'] ?? 0);
            $numeroExpedicion = trim($_POST['numero_expedicion'] ?? '');

            if ($idExpedicionItem <= 0) {
                throw new Exception('ID de item inválido');
            }

            if (empty($numeroExpedicion)) {
                throw new Exception('Número de expedición requerido');
            }

            $resultado = $this->service->eliminarItemEscaneado($idExpedicionItem, $numeroExpedicion, $usuario);
            echo json_encode($resultado);

            if ($resultado['success']) {
                $this->logActividad(
                    'Eliminar item escaneado - Mudanza',
                    $numeroExpedicion,
                    $usuario,
                    "Item eliminado: {$resultado['item_eliminado']['numero_item']} - {$resultado['item_eliminado']['nombre_producto']}"
                );
            }
        } catch (Exception $e) {
            error_log("Error eliminando item: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    private function eliminarMultiplesItems($usuario)
    {
        try {
            $idsItemsRaw = $_POST['ids_items'] ?? '';
            $numeroExpedicion = trim($_POST['numero_expedicion'] ?? '');

            // 🆕 FIX: Decodificar JSON si viene como string
            if (is_string($idsItemsRaw)) {
                $idsItems = json_decode($idsItemsRaw, true);
            } else {
                $idsItems = $idsItemsRaw;
            }

            // Validar que tenemos datos válidos
            if (empty($idsItems) || !is_array($idsItems)) {
                throw new Exception('Debe seleccionar al menos un item válido');
            }

            if (empty($numeroExpedicion)) {
                throw new Exception('Número de expedición requerido');
            }

            // Convertir a enteros y filtrar valores inválidos
            $idsItems = array_filter(array_map('intval', $idsItems), function ($id) {
                return $id > 0;
            });

            if (empty($idsItems)) {
                throw new Exception('No se encontraron IDs válidos para eliminar');
            }

            // Debug log para verificar qué se está recibiendo
            error_log("ELIMINAR MÚLTIPLES - IDs recibidos: " . implode(', ', $idsItems) . " | Total: " . count($idsItems));

            $resultado = $this->service->eliminarMultiplesItems($idsItems, $numeroExpedicion, $usuario);
            echo json_encode($resultado);

            if ($resultado['success']) {
                $this->logActividad(
                    'Eliminar múltiples items escaneados - Mudanza masiva',
                    $numeroExpedicion,
                    $usuario,
                    "Items eliminados: {$resultado['total_eliminados']} de {$resultado['total_solicitados']}"
                );
            }
        } catch (Exception $e) {
            error_log("Error eliminando múltiples items: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}


if (!file_exists('repository/despachoRepository.php') || !file_exists('services/despachoServices.php')) {
    die("Error: Faltan archivos del sistema MVC.");
}

$controller = new DespachoController($conexion, $url_base);

if ($controller->handleRequest()) {
    exit();
}

try {
    $datosVista = $controller->obtenerDatosVista();
} catch (Exception $e) {
    error_log("Error fatal obteniendo datos de vista: " . $e->getMessage());
    $datosVista = [
        'expediciones_abiertas' => [],
        'rejillas_info' => [],
        'estadisticas' => ['total_expediciones' => 0, 'total_items' => 0, 'total_rejillas' => 0],
        'transportistas' => [],
        'tipos_vehiculo' => [],
        'estado_sistema' => ['sistema_operativo' => false, 'mensaje' => 'Error en el sistema']
    ];
}

$expedicionesAbiertas = $datosVista['expediciones_abiertas'] ?? [];
$rejillasInfo = $datosVista['rejillas_info'] ?? [];
$estadisticas = $datosVista['estadisticas'] ?? ['total_expediciones' => 0, 'total_items' => 0, 'total_rejillas' => 0];
$transportistas = $datosVista['transportistas'] ?? [];
$tiposVehiculo = $datosVista['tipos_vehiculo'] ?? [];
$estadoSistema = $datosVista['estado_sistema'] ?? ['sistema_operativo' => false, 'mensaje' => 'Error en el sistema'];
$configuracion = $controller->obtenerConfiguracion();
