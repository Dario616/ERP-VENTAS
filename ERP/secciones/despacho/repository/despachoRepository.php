<?php
require_once __DIR__ . '/StockFlexibleManager.php';

class DespachoRepository
{
    private $conexion;
    private $stockManager;

    public function __construct($conexion)
    {
        $this->conexion = $conexion;
        $this->stockManager = new StockFlexibleManager($conexion);
    }

    public function obtenerInfoRejilla($idRejilla)
    {
        try {
            $sql = "SELECT numero_rejilla, peso_actual, capacidad_maxima FROM sist_rejillas WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $idRejilla, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo info de rejilla: " . $e->getMessage());
            return ['numero_rejilla' => 'Desconocida', 'peso_actual' => 0, 'capacidad_maxima' => 0];
        }
    }

    public function crearExpedicion($datos)
    {
        try {
            if (empty($datos['id_rejilla'])) {
                throw new Exception('La rejilla es obligatoria para crear una expedición');
            }

            $fechaCompleta = date('Ymd');
            $prefijo = 'EXP' . $fechaCompleta;

            $sqlUltimo = "SELECT numero_expedicion FROM sist_expediciones 
                         WHERE numero_expedicion LIKE :prefijo 
                         ORDER BY numero_expedicion DESC LIMIT 1";
            $stmt = $this->conexion->prepare($sqlUltimo);
            $stmt->bindValue(':prefijo', $prefijo . '%', PDO::PARAM_STR);
            $stmt->execute();
            $ultimo = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($ultimo) {
                $ultimoNumero = (int)substr($ultimo['numero_expedicion'], -6);
                $nuevoNumero = $ultimoNumero + 1;
            } else {
                $nuevoNumero = 1;
            }

            $numeroExpedicion = $prefijo . str_pad($nuevoNumero, 6, '0', STR_PAD_LEFT);

            $sql = "INSERT INTO sist_expediciones 
                   (numero_expedicion, transportista, conductor, placa_vehiculo, destino, peso, tipovehiculo, usuario_creacion, id_rejilla)
                   VALUES (:numero, :transportista, :conductor, :placa, :destino, :peso, :tipovehiculo, :usuario, :id_rejilla)";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero', $numeroExpedicion, PDO::PARAM_STR);
            $stmt->bindParam(':transportista', $datos['transportista'], PDO::PARAM_STR);
            $stmt->bindParam(':conductor', $datos['conductor'], PDO::PARAM_STR);
            $stmt->bindParam(':placa', $datos['placa'], PDO::PARAM_STR);
            $stmt->bindParam(':destino', $datos['destino'], PDO::PARAM_STR);
            $stmt->bindParam(':peso', $datos['peso'], PDO::PARAM_STR);
            $stmt->bindParam(':tipovehiculo', $datos['tipovehiculo'], PDO::PARAM_STR);
            $stmt->bindParam(':usuario', $datos['usuario'], PDO::PARAM_STR);
            $stmt->bindParam(':id_rejilla', $datos['id_rejilla'], PDO::PARAM_INT);
            $stmt->execute();

            return [
                'numero_expedicion' => $numeroExpedicion,
                'fecha_formato' => date('d/m/Y'),
                'secuencial_dia' => $nuevoNumero,
                'id_rejilla' => $datos['id_rejilla']
            ];
        } catch (Exception $e) {
            error_log("Error creando expedición: " . $e->getMessage());
            throw $e;
        }
    }

    public function verificarExpedicion($numeroExpedicion)
    {
        try {
            $sql = "SELECT e.*, r.numero_rejilla, r.id as id_rejilla_real
                    FROM sist_expediciones e
                    INNER JOIN sist_rejillas r ON e.id_rejilla = r.id
                    WHERE e.numero_expedicion = :numero";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero', $numeroExpedicion, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error verificando expedición: " . $e->getMessage());
            return false;
        }
    }

    public function verificarItem($idItem)
    {
        try {
            $sql = "SELECT 
                       sps.id, sps.cliente, sps.nombre_producto, sps.numero_item,
                       sps.peso_bruto, sps.estado, sps.bobinas_pacote, sps.id_venta
                   FROM sist_prod_stock sps
                   WHERE sps.id = :id_item AND sps.estado = 'en stock'";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_item', $idItem, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error verificando item: " . $e->getMessage());
            return false;
        }
    }

    public function validarItemParaEscaneado($idItem, $idRejillaExpedicion)
    {
        try {
            $item = $this->verificarItem($idItem);
            if (!$item) {
                return [
                    'valido' => false,
                    'error' => 'Item no encontrado o no está en stock',
                    'codigo_error' => 'ITEM_NO_DISPONIBLE'
                ];
            }

            $duplicado = $this->verificarItemDuplicado($idItem);
            if ($duplicado) {
                return [
                    'valido' => false,
                    'error' => "Item ya está en expedición {$duplicado['numero_expedicion']}",
                    'codigo_error' => 'ITEM_YA_ESCANEADO'
                ];
            }

            $bobinasItem = $item['bobinas_pacote'] ?: 1;
            $cancelacionReserva = $this->stockManager->cancelarReservaConflictiva(
                $item['nombre_producto'],
                $item['bobinas_pacote'],
                $bobinasItem,
                'EXPEDICION_FLEXIBLE'
            );

            $validacionProducto = $this->validarProductoEnRejilla($item['nombre_producto'], $idRejillaExpedicion);

            $item['rejilla_info'] = $validacionProducto['rejilla_info'] ?? [
                'numero_rejilla' => 'Sin rejilla específica',
                'id_rejilla' => $idRejillaExpedicion,
                'tiene_asignaciones' => false
            ];

            $item['fuera_de_rejilla'] = !$validacionProducto['valido'];
            $item['reserva_cancelada'] = $cancelacionReserva['cancelado'];
            $item['info_cancelacion'] = $cancelacionReserva;

            $mensaje = '';
            if ($cancelacionReserva['cancelado']) {
                $mensaje = "✅ ESCANEADO - Reserva cancelada automáticamente";
            } elseif ($validacionProducto['valido']) {
                $mensaje = "✅ ESCANEADO - Item válido con asignación";
            } else {
                $mensaje = "✅ ESCANEADO - Item sin asignación → DESCONOCIDO";
            }

            return [
                'valido' => true,
                'item' => $item,
                'mensaje' => $mensaje,
                'flexibilidad_aplicada' => $cancelacionReserva['cancelado'],
                'modo_escaneado' => 'FLEXIBLE_TOTAL'
            ];
        } catch (Exception $e) {
            error_log("Error validando item: " . $e->getMessage());
            return [
                'valido' => false,
                'error' => 'Error interno del sistema',
                'codigo_error' => 'ERROR_SISTEMA'
            ];
        }
    }


    public function validarProductoEnRejilla($nombreProducto, $idRejillaExpedicion)
    {
        try {
            $rejillaExpedicion = $this->obtenerInfoRejilla($idRejillaExpedicion);
            if (!$rejillaExpedicion) {
                return [
                    'valido' => false,
                    'error' => 'Rejilla de expedición no encontrada',
                    'rejilla_info' => [
                        'numero_rejilla' => 'Desconocida',
                        'id_rejilla' => $idRejillaExpedicion,
                        'tiene_asignaciones' => false
                    ]
                ];
            }

            $sql = "SELECT ra.id, ra.cliente, ra.cant_uni, ra.peso_asignado, r.numero_rejilla
                FROM sist_rejillas_asignaciones ra
                INNER JOIN sist_rejillas r ON ra.id_rejilla = r.id
                WHERE ra.id_rejilla = :id_rejilla_expedicion
                AND LOWER(TRIM(ra.nombre_producto)) = LOWER(TRIM(:nombre_producto))
                AND ra.estado_asignacion = 'activa'
                AND ra.cliente IS NOT NULL AND TRIM(ra.cliente) != ''
                LIMIT 1";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_rejilla_expedicion', $idRejillaExpedicion, PDO::PARAM_INT);
            $stmt->bindParam(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
            $stmt->execute();
            $asignacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($asignacion) {
                return [
                    'valido' => true,
                    'mensaje' => "Producto con asignación en Rejilla #{$rejillaExpedicion['numero_rejilla']}",
                    'rejilla_info' => [
                        'numero_rejilla' => $rejillaExpedicion['numero_rejilla'],
                        'id_rejilla' => $idRejillaExpedicion,
                        'tiene_asignaciones' => true,
                        'cliente_asignado' => $asignacion['cliente']
                    ]
                ];
            } else {
                return [
                    'valido' => false,
                    'mensaje' => "Producto sin asignación en Rejilla #{$rejillaExpedicion['numero_rejilla']} → Irá a DESCONOCIDOS",
                    'rejilla_info' => [
                        'numero_rejilla' => $rejillaExpedicion['numero_rejilla'],
                        'id_rejilla' => $idRejillaExpedicion,
                        'tiene_asignaciones' => false
                    ]
                ];
            }
        } catch (Exception $e) {
            error_log("Error validando producto en rejilla: " . $e->getMessage());
            return [
                'valido' => false,
                'error' => 'Error validando producto en rejilla',
                'rejilla_info' => [
                    'numero_rejilla' => 'Error',
                    'id_rejilla' => $idRejillaExpedicion,
                    'tiene_asignaciones' => false
                ]
            ];
        }
    }
    public function buscarAsignacionDisponible($nombreProducto, $numeroExpedicion)
    {
        try {
            $expedicion = $this->verificarExpedicion($numeroExpedicion);
            $idRejillaExpedicion = $expedicion['id_rejilla'];

            $sql = "SELECT 
               ra.*, r.numero_rejilla, ra.cliente, ra.id_venta as id_venta_real,
               COALESCE(ra.despachado, 0) as cantidad_ya_despachada,
               COALESCE(ra.peso_despachado, 0) as peso_ya_despachado
           FROM sist_rejillas_asignaciones ra
           INNER JOIN sist_rejillas r ON ra.id_rejilla = r.id
           WHERE ra.estado_asignacion = 'activa'
           AND LOWER(TRIM(ra.nombre_producto)) = LOWER(TRIM(:nombre_producto))
           AND ra.cliente IS NOT NULL AND TRIM(ra.cliente) != ''
           AND ra.id_rejilla = :id_rejilla_filtro
           ORDER BY ra.fecha_asignacion ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':nombre_producto', $nombreProducto);
            $stmt->bindValue(':id_rejilla_filtro', $idRejillaExpedicion, PDO::PARAM_INT);
            $stmt->execute();
            $todasAsignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($todasAsignaciones)) {
                return null;
            }

            $unidadesEscaneadasPorCliente = $this->contarUnidadesEscaneadasPorCliente($numeroExpedicion, $nombreProducto);

            foreach ($todasAsignaciones as $asignacion) {
                $cliente = $asignacion['cliente'];
                $cantidadAsignada = (int)$asignacion['cant_uni'];
                $cantidadYaDespachada = (int)$asignacion['cantidad_ya_despachada'];
                $unidadesEscaneadas = $unidadesEscaneadasPorCliente[$cliente] ?? 0;

                $unidadesDisponibles = $cantidadAsignada - $cantidadYaDespachada - $unidadesEscaneadas;

                if ($unidadesDisponibles > 0) {
                    return [
                        'asignacion' => $asignacion,
                        'items_pendientes' => $unidadesDisponibles,
                        'items_asignados' => $cantidadAsignada,
                        'items_ya_despachados' => $cantidadYaDespachada,
                        'items_escaneados' => $unidadesEscaneadas,
                        'items_disponibles_total' => $unidadesDisponibles,
                        'progreso_total' => round((($cantidadYaDespachada + $unidadesEscaneadas) / $cantidadAsignada) * 100, 1),
                        'progreso_solo_expedicion' => round(($unidadesEscaneadas / $cantidadAsignada) * 100, 1)
                    ];
                }
            }

            return [
                'asignacion' => null,
                'todas_completadas' => true,
                'total_asignaciones' => count($todasAsignaciones),
                'mensaje' => 'Todas las asignaciones están completadas (considerando despachos anteriores)'
            ];
        } catch (Exception $e) {
            error_log("Error buscando asignación: " . $e->getMessage());
            return null;
        }
    }

    private function contarUnidadesEscaneadasPorCliente($numeroExpedicion, $nombreProducto)
    {
        try {
            $sql = "SELECT 
                   ei.cliente_asignado,
                   SUM(ei.cantidad_escaneada) as unidades_escaneadas_total
               FROM sist_expedicion_items ei
               INNER JOIN sist_prod_stock sps ON ei.id_stock = sps.id
               WHERE ei.numero_expedicion = :numero_expedicion
               AND LOWER(TRIM(sps.nombre_producto)) = LOWER(TRIM(:nombre_producto))
               AND ei.es_desconocido = FALSE
               GROUP BY ei.cliente_asignado";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':numero_expedicion', $numeroExpedicion);
            $stmt->bindValue(':nombre_producto', $nombreProducto);
            $stmt->execute();

            $resultado = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $resultado[$row['cliente_asignado']] = (int)$row['unidades_escaneadas_total'];
            }
            return $resultado;
        } catch (Exception $e) {
            error_log("Error contando unidades: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerClientePorVenta($idVenta)
    {
        try {
            $sql = "SELECT cliente FROM sist_rejillas_asignaciones 
                WHERE id_venta = :id_venta AND estado_asignacion = 'activa'
                AND cliente IS NOT NULL LIMIT 1";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($resultado) {
                return $resultado['cliente'];
            }

            $sql = "SELECT cliente FROM sist_ventas_presupuesto WHERE id = :id_venta";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ? $resultado['cliente'] : null;
        } catch (Exception $e) {
            error_log("Error obteniendo cliente: " . $e->getMessage());
            return null;
        }
    }

    public function verificarItemDuplicado($idItem)
    {
        try {
            $sql = "SELECT ei.numero_expedicion, e.estado as estado_expedicion
                    FROM sist_expedicion_items ei
                    INNER JOIN sist_expediciones e ON ei.numero_expedicion = e.numero_expedicion
                    WHERE ei.id_stock = :id_stock";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_stock', $idItem, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error verificando duplicado: " . $e->getMessage());
            return true;
        }
    }

    public function agregarItemExpedicion($numeroExpedicion, $idItem, $usuario, $infoAdicional = [])
    {
        try {
            $item = $this->verificarItem($idItem);
            if (!$item) {
                throw new Exception("Item no encontrado");
            }

            $cantidadReal = !empty($item['bobinas_pacote']) && $item['bobinas_pacote'] > 0
                ? (int)$item['bobinas_pacote'] : 1;

            // 🆕 LOG DE FLEXIBILIDAD
            if (isset($infoAdicional['reserva_cancelada']) && $infoAdicional['reserva_cancelada']) {
                error_log("FLEXIBILIDAD APLICADA - Item {$item['numero_item']}: Reserva cancelada automáticamente para expedición {$numeroExpedicion}");
            }

            $sql = "INSERT INTO sist_expedicion_items 
                    (numero_expedicion, id_stock, usuario_escaneo, cliente_asignado, 
                     id_venta_asignado, id_asignacion_rejilla, cantidad_escaneada, 
                     peso_escaneado, es_desconocido, modo_asignacion)
                    VALUES (:numero, :id_stock, :usuario, :cliente, :id_venta, 
                            :id_asignacion, :cantidad, :peso, :es_desconocido, :modo_asignacion)";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero', $numeroExpedicion, PDO::PARAM_STR);
            $stmt->bindParam(':id_stock', $idItem, PDO::PARAM_INT);
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
            $stmt->bindParam(':cliente', $infoAdicional['cliente_asignado'], PDO::PARAM_STR);
            $stmt->bindValue(
                ':id_venta',
                $infoAdicional['id_venta_asignado'],
                $infoAdicional['id_venta_asignado'] ? PDO::PARAM_INT : PDO::PARAM_NULL
            );
            $stmt->bindValue(
                ':id_asignacion',
                $infoAdicional['id_asignacion_rejilla'],
                $infoAdicional['id_asignacion_rejilla'] ? PDO::PARAM_INT : PDO::PARAM_NULL
            );
            $stmt->bindParam(':cantidad', $cantidadReal, PDO::PARAM_INT);
            $stmt->bindParam(':peso', $infoAdicional['peso_escaneado'], PDO::PARAM_STR);
            $stmt->bindParam(':es_desconocido', $infoAdicional['es_desconocido'], PDO::PARAM_BOOL);
            $stmt->bindParam(':modo_asignacion', $infoAdicional['modo_asignacion'], PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error agregando item: " . $e->getMessage());
            throw $e;
        }
    }


    public function obtenerAsignacionesConProgreso($numeroExpedicion)
    {
        try {
            $expedicion = $this->verificarExpedicion($numeroExpedicion);
            $idRejillaExpedicion = $expedicion['id_rejilla'];

            $sql = "SELECT 
               ra.id as asignacion_id,
               ra.cliente,
               ra.nombre_producto,
               ra.cant_uni as total_asignado,
               ra.peso_asignado as peso_total_asignado,
               ra.fecha_asignacion,
               ra.id_venta,
               r.numero_rejilla,
               COALESCE(ra.despachado, 0) as cantidad_ya_despachada,
               COALESCE(ra.peso_despachado, 0) as peso_ya_despachado
           FROM sist_rejillas_asignaciones ra
           INNER JOIN sist_rejillas r ON ra.id_rejilla = r.id
           WHERE ra.estado_asignacion = 'activa'
           AND ra.cliente IS NOT NULL AND TRIM(ra.cliente) != ''
           AND ra.id_rejilla = :id_rejilla_filtro
           ORDER BY ra.cliente, ra.fecha_asignacion ASC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':id_rejilla_filtro', $idRejillaExpedicion, PDO::PARAM_INT);
            $stmt->execute();
            $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($asignaciones)) {
                return [];
            }

            $progresoActual = $this->obtenerProgresoActualPorAsignacion($numeroExpedicion);

            $resultado = [];
            foreach ($asignaciones as $asignacion) {
                $asignacionId = $asignacion['asignacion_id'];
                $progreso = $progresoActual[$asignacionId] ?? [
                    'items_escaneados' => 0,
                    'cantidad_escaneada' => 0,
                    'peso_escaneado' => 0,
                    'productos_escaneados' => '',
                    'numeros_items' => '',
                    'ids_expedicion_items' => []
                ];

                $cantidadAsignada = (int)$asignacion['total_asignado'];
                $cantidadYaDespachada = (int)$asignacion['cantidad_ya_despachada'];
                $cantidadEscaneadaExpedicion = (int)$progreso['cantidad_escaneada'];
                $pesoAsignado = (float)$asignacion['peso_total_asignado'];
                $pesoYaDespachado = (float)$asignacion['peso_ya_despachado'];
                $pesoEscaneadoExpedicion = (float)$progreso['peso_escaneado'];

                $cantidadTotalDespachada = $cantidadYaDespachada + $cantidadEscaneadaExpedicion;
                $pesoTotalDespachado = $pesoYaDespachado + $pesoEscaneadoExpedicion;

                $cantidadDisponible = max(0, $cantidadAsignada - $cantidadYaDespachada - $cantidadEscaneadaExpedicion);
                $pesoDisponible = max(0, $pesoAsignado - $pesoYaDespachado - $pesoEscaneadoExpedicion);

                $progresoTotal = $cantidadAsignada > 0 ? round(($cantidadTotalDespachada / $cantidadAsignada) * 100, 1) : 0;
                $progresoPeso = $pesoAsignado > 0 ? round(($pesoTotalDespachado / $pesoAsignado) * 100, 1) : 0;

                $estado = 'pendiente';
                if ($cantidadTotalDespachada > $cantidadAsignada) {
                    $estado = 'excedido';
                } elseif ($cantidadTotalDespachada >= $cantidadAsignada) {
                    $estado = 'completado';
                } elseif ($cantidadEscaneadaExpedicion > 0) {
                    $estado = 'en_progreso';
                }

                $resultado[] = [
                    'asignacion_id' => $asignacionId,
                    'cliente' => $asignacion['cliente'],
                    'cliente_asignado' => $asignacion['cliente'],
                    'es_desconocido' => false,
                    'total_items_escaneados' => $progreso['items_escaneados'],
                    'cantidad_total_escaneada' => $cantidadEscaneadaExpedicion,
                    'peso_total_escaneado' => $pesoEscaneadoExpedicion,
                    'peso_total_formateado' => number_format($pesoEscaneadoExpedicion, 2) . ' kg',
                    'productos' => !empty($progreso['productos_escaneados']) ?
                        $progreso['productos_escaneados'] : $asignacion['nombre_producto'],
                    'numeros_items' => $progreso['numeros_items'],
                    'ids_expedicion_items' => $progreso['ids_expedicion_items'],
                    'total_asignado' => $cantidadAsignada,
                    'peso_asignado' => $pesoAsignado,
                    'peso_asignado_formateado' => number_format($pesoAsignado, 2) . ' kg',

                    'cantidad_ya_despachada' => $cantidadYaDespachada,
                    'peso_ya_despachado' => $pesoYaDespachado,
                    'cantidad_total_despachada' => $cantidadTotalDespachada,
                    'peso_total_despachado' => $pesoTotalDespachado,
                    'cantidad_disponible' => $cantidadDisponible,
                    'peso_disponible' => $pesoDisponible,

                    'asignaciones_relacionadas' => 1,
                    'productos_asignados' => $asignacion['nombre_producto'],
                    'progreso_cantidad' => $progresoTotal,
                    'progreso_peso' => $progresoPeso,
                    'progreso_solo_expedicion' => $cantidadAsignada > 0 ?
                        round(($cantidadEscaneadaExpedicion / $cantidadAsignada) * 100, 1) : 0,
                    'estado' => $estado,
                    'prioridad' => $asignacion['fecha_asignacion'],
                    'cantidad_pendiente' => $cantidadDisponible,
                    'cantidad_excedida' => max(0, $cantidadTotalDespachada - $cantidadAsignada),
                    'peso_pendiente' => $pesoDisponible,
                    'rejilla_numero' => $asignacion['numero_rejilla'],
                    'id_venta_asignacion' => $asignacion['id_venta'],

                    'info_despachos' => [
                        'ya_despachado_anterior' => $cantidadYaDespachada,
                        'escaneado_expedicion_actual' => $cantidadEscaneadaExpedicion,
                        'total_despachado' => $cantidadTotalDespachada,
                        'disponible_para_escanear' => $cantidadDisponible
                    ]
                ];
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("Error obteniendo asignaciones: " . $e->getMessage());
            return [];
        }
    }

    private function obtenerProgresoActualPorAsignacion($numeroExpedicion)
    {
        try {
            $sql = "SELECT 
                   ei.id_asignacion_rejilla,
                   COUNT(ei.id) as items_escaneados,
                   SUM(ei.cantidad_escaneada) as cantidad_escaneada,
                   SUM(ei.peso_escaneado) as peso_escaneado,
                   STRING_AGG(DISTINCT sps.nombre_producto, ', ' ORDER BY sps.nombre_producto) as productos_escaneados,
                   STRING_AGG(CAST(sps.numero_item AS VARCHAR), ', ' ORDER BY sps.numero_item) as numeros_items,
                   ARRAY_AGG(DISTINCT ei.id) as ids_expedicion_items
               FROM sist_expedicion_items ei
               INNER JOIN sist_prod_stock sps ON ei.id_stock = sps.id
               WHERE ei.numero_expedicion = :numero_expedicion
               AND ei.es_desconocido = FALSE
               AND ei.id_asignacion_rejilla IS NOT NULL
               GROUP BY ei.id_asignacion_rejilla";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero_expedicion', $numeroExpedicion, PDO::PARAM_STR);
            $stmt->execute();

            $resultado = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $resultado[$row['id_asignacion_rejilla']] = [
                    'items_escaneados' => (int)$row['items_escaneados'],
                    'cantidad_escaneada' => (int)$row['cantidad_escaneada'],
                    'peso_escaneado' => (float)$row['peso_escaneado'],
                    'productos_escaneados' => $row['productos_escaneados'],
                    'numeros_items' => $row['numeros_items'],
                    'ids_expedicion_items' => $row['ids_expedicion_items'] ?
                        explode(',', trim($row['ids_expedicion_items'], '{}')) : []
                ];
            }
            return $resultado;
        } catch (Exception $e) {
            error_log("Error obteniendo progreso por asignación: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerItemsExpedicionPorCliente($numeroExpedicion)
    {
        try {
            $clientesConAsignaciones = $this->obtenerAsignacionesConProgreso($numeroExpedicion);

            $sql = "SELECT 
                       ei.cliente_asignado,
                       ei.es_desconocido,
                       ei.modo_asignacion,
                       COUNT(ei.id) as total_items_escaneados,
                       SUM(ei.cantidad_escaneada) as cantidad_total_escaneada,
                       SUM(ei.peso_escaneado) as peso_total_escaneado,
                       STRING_AGG(DISTINCT sps.nombre_producto, ', ' ORDER BY sps.nombre_producto) as productos,
                       STRING_AGG(CAST(sps.numero_item AS VARCHAR), ', ' ORDER BY sps.numero_item) as numeros_items,
                       ARRAY_AGG(DISTINCT ei.id) as ids_expedicion_items
                   FROM sist_expedicion_items ei
                   INNER JOIN sist_prod_stock sps ON ei.id_stock = sps.id
                   WHERE ei.numero_expedicion = :numero_expedicion
                   AND ei.es_desconocido = TRUE
                   GROUP BY ei.cliente_asignado, ei.es_desconocido, ei.modo_asignacion
                   ORDER BY ei.cliente_asignado";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero_expedicion', $numeroExpedicion, PDO::PARAM_STR);
            $stmt->execute();
            $itemsDesconocidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($itemsDesconocidos as $item) {
                $clientesConAsignaciones[] = [
                    'cliente' => 'DESCONOCIDO',
                    'es_desconocido' => true,
                    'modo_asignacion' => $item['modo_asignacion'],
                    'total_items_escaneados' => $item['total_items_escaneados'],
                    'cantidad_total_escaneada' => $item['cantidad_total_escaneada'],
                    'peso_total_escaneado' => $item['peso_total_escaneado'],
                    'peso_total_formateado' => number_format($item['peso_total_escaneado'], 2) . ' kg',
                    'productos' => $item['productos'],
                    'numeros_items' => $item['numeros_items'],
                    'ids_expedicion_items' => $item['ids_expedicion_items'],
                    'total_asignado' => 0,
                    'peso_asignado' => 0,
                    'peso_asignado_formateado' => '0.00 kg',
                    'asignaciones_relacionadas' => 0,
                    'progreso_cantidad' => 0,
                    'progreso_peso' => 0,
                    'estado' => 'desconocido',
                    'cantidad_pendiente' => 0,
                    'peso_pendiente' => 0
                ];
            }

            return $clientesConAsignaciones;
        } catch (Exception $e) {
            error_log("Error obteniendo items por cliente: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerIdsItemsDesconocidos($numeroExpedicion)
    {
        try {
            $sql = "SELECT ei.id as expedicion_item_id
                    FROM sist_expedicion_items ei
                    WHERE ei.numero_expedicion = :numero_expedicion
                    AND ei.es_desconocido = TRUE
                    ORDER BY ei.fecha_escaneado DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero_expedicion', $numeroExpedicion, PDO::PARAM_STR);
            $stmt->execute();

            $resultados = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return array_map('intval', $resultados);
        } catch (Exception $e) {
            error_log("Error obteniendo IDs de items DESCONOCIDOS: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerClientesMismaRejilla($numeroExpedicion)
    {
        try {
            $expedicion = $this->verificarExpedicion($numeroExpedicion);
            if (!$expedicion) {
                return [];
            }

            $sql = "SELECT DISTINCT ra.cliente 
                FROM sist_rejillas_asignaciones ra
                WHERE ra.estado_asignacion = 'activa'
                AND ra.cliente IS NOT NULL AND TRIM(ra.cliente) != ''
                AND ra.id_rejilla = :id_rejilla
                ORDER BY ra.cliente";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_rejilla', $expedicion['id_rejilla'], PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo clientes de la misma rejilla: " . $e->getMessage());
            return [];
        }
    }

    public function moverItemACliente($idExpedicionItem, $nuevoCliente, $idVenta = null)
    {
        try {
            $sqlObtenerInfo = "SELECT ei.id_stock, sps.nombre_producto
                           FROM sist_expedicion_items ei
                           INNER JOIN sist_prod_stock sps ON ei.id_stock = sps.id
                           WHERE ei.id = :id_expedicion_item";

            $stmt = $this->conexion->prepare($sqlObtenerInfo);
            $stmt->bindParam(':id_expedicion_item', $idExpedicionItem, PDO::PARAM_INT);
            $stmt->execute();
            $itemInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$itemInfo) {
                throw new Exception("Item de expedición no encontrado");
            }

            $sqlBuscarAsignacion = "SELECT ra.id as id_asignacion, ra.id_venta
                                FROM sist_rejillas_asignaciones ra
                                WHERE LOWER(TRIM(ra.nombre_producto)) = LOWER(TRIM(:nombre_producto))
                                AND ra.cliente = :nuevo_cliente
                                AND ra.estado_asignacion = 'activa'
                                LIMIT 1";

            $stmt = $this->conexion->prepare($sqlBuscarAsignacion);
            $stmt->bindParam(':nombre_producto', $itemInfo['nombre_producto'], PDO::PARAM_STR);
            $stmt->bindParam(':nuevo_cliente', $nuevoCliente, PDO::PARAM_STR);
            $stmt->execute();
            $asignacion = $stmt->fetch(PDO::FETCH_ASSOC);

            $idAsignacionRejilla = $asignacion ? $asignacion['id_asignacion'] : null;
            $idVentaAsignacion = $idVenta ?: ($asignacion ? $asignacion['id_venta'] : null);

            $sql = "UPDATE sist_expedicion_items 
                SET cliente_asignado = :nuevo_cliente,
                    id_venta_asignado = :id_venta,
                    id_asignacion_rejilla = :id_asignacion_rejilla,
                    es_desconocido = FALSE,
                    modo_asignacion = 'reasignado_manual'
                WHERE id = :id_expedicion_item";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':nuevo_cliente', $nuevoCliente, PDO::PARAM_STR);
            $stmt->bindValue(':id_venta', $idVentaAsignacion, $idVentaAsignacion ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':id_asignacion_rejilla', $idAsignacionRejilla, $idAsignacionRejilla ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindParam(':id_expedicion_item', $idExpedicionItem, PDO::PARAM_INT);

            $resultado = $stmt->execute();

            if ($resultado) {
                error_log("REASIGNACIÓN EXITOSA - Item Expedición ID: {$idExpedicionItem}, Nuevo Cliente: {$nuevoCliente}");
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("Error moviendo item: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerClientesDisponibles()
    {
        try {
            $sql = "SELECT DISTINCT ra.cliente 
                FROM sist_rejillas_asignaciones ra
                WHERE ra.estado_asignacion = 'activa'
                AND ra.cliente IS NOT NULL AND TRIM(ra.cliente) != ''
                UNION
                SELECT DISTINCT sps.cliente
                FROM sist_prod_stock sps
                WHERE sps.estado = 'en stock'
                AND sps.cliente IS NOT NULL AND TRIM(sps.cliente) != ''
                ORDER BY cliente";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo clientes: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerItemsParaDespacho($numeroExpedicion)
    {
        try {
            $sql = "SELECT 
                        ei.id_stock, ei.cliente_asignado, ei.id_venta_asignado, ei.id_asignacion_rejilla,
                        sps.numero_item, sps.id_venta, sps.nombre_producto, sps.peso_bruto, sps.cliente 
                    FROM sist_expedicion_items ei
                    INNER JOIN sist_prod_stock sps ON ei.id_stock = sps.id
                    WHERE ei.numero_expedicion = :numero";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero', $numeroExpedicion, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo items para despacho: " . $e->getMessage());
            return [];
        }
    }


    public function marcarItemsDespachados($itemsConInfo)
    {
        try {
            if (empty($itemsConInfo)) {
                return true;
            }

            foreach ($itemsConInfo as $item) {
                $sqlProducto = "
                    UPDATE sist_prod_stock 
                    SET estado = 'despachado',
                        id_venta = COALESCE(:id_venta_nuevo, id_venta),
                        cliente = COALESCE(:cliente_nuevo, cliente)
                    WHERE id = :id_stock";

                $stmt = $this->conexion->prepare($sqlProducto);
                $stmt->bindValue(
                    ':id_venta_nuevo',
                    $item['id_venta_asignado'],
                    $item['id_venta_asignado'] ? PDO::PARAM_INT : PDO::PARAM_NULL
                );
                $stmt->bindValue(
                    ':cliente_nuevo',
                    $item['cliente_asignado'],
                    $item['cliente_asignado'] ? PDO::PARAM_STR : PDO::PARAM_NULL
                );
                $stmt->bindParam(':id_stock', $item['id_stock'], PDO::PARAM_INT);
                $stmt->execute();

                $producto = $this->obtenerInfoProducto($item['id_stock']);
                if ($producto) {
                    $bobinasDespachar = $producto['bobinas_pacote'] ?: 1;

                    $this->stockManager->despacharItemCompleto(
                        $producto['nombre_producto'],
                        $producto['bobinas_pacote'],
                        $bobinasDespachar,
                        $item['id_stock'],
                        $item['id_asignacion_rejilla']
                    );

                    error_log("DESPACHO INTEGRADO - Producto: {$producto['nombre_producto']}, ID Stock: {$item['id_stock']}, Asignación: {$item['id_asignacion_rejilla']}, Bobinas: {$bobinasDespachar}");
                }
            }

            $this->actualizarEstadoVentas($itemsConInfo);
            return true;
        } catch (Exception $e) {
            error_log("Error despachando items con StockFlexibleManager completo: " . $e->getMessage());
            throw $e;
        }
    }

    private function obtenerInfoProducto($idStock)
    {
        $sql = "SELECT nombre_producto, bobinas_pacote FROM sist_prod_stock WHERE id = :id";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':id', $idStock, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function actualizarEstadoVentas($itemsConInfo)
    {
        try {
            $ventasIds = [];

            foreach ($itemsConInfo as $item) {
                if ($item['id_venta_asignado']) {
                    $ventasIds[] = $item['id_venta_asignado'];
                }
            }

            $ventasIds = array_unique($ventasIds);

            if (empty($ventasIds)) {
                return true;
            }

            foreach ($ventasIds as $idVenta) {
                $sqlUpdate = "UPDATE sist_ventas_presupuesto 
                         SET estado = 'Finalizado'
                         WHERE id = :id_venta 
                         AND estado != 'Finalizado'";

                $stmt = $this->conexion->prepare($sqlUpdate);
                $stmt->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
                $stmt->execute();
            }

            return true;
        } catch (Exception $e) {
            error_log("Error actualizando estado de ventas: " . $e->getMessage());
            throw $e;
        }
    }



    public function cerrarExpedicion($numeroExpedicion, $usuario)
    {
        try {
            $expedicion = $this->verificarExpedicion($numeroExpedicion);
            if (!$expedicion) {
                throw new Exception("Expedición no encontrada: {$numeroExpedicion}");
            }

            $idRejilla = $expedicion['id_rejilla'];

            $sql = "UPDATE sist_expediciones 
                SET estado = 'DESPACHADA',
                    fecha_despacho = CURRENT_TIMESTAMP,
                    usuario_despacho = :usuario
                WHERE numero_expedicion = :numero";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
            $stmt->bindParam(':numero', $numeroExpedicion, PDO::PARAM_STR);
            $resultado = $stmt->execute();
            if ($resultado && $idRejilla) {
                $descripcionLimpiada = $this->limpiarDescripcionRejilla($idRejilla);
                if ($descripcionLimpiada) {
                    error_log("DESPACHO COMPLETADO - Expedición: {$numeroExpedicion} | Rejilla: {$idRejilla} | Descripción limpiada automáticamente");
                } else {
                    error_log("ADVERTENCIA - Expedición cerrada pero no se pudo limpiar descripción de rejilla {$idRejilla}");
                }
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("Error cerrando expedición y limpiando descripción: " . $e->getMessage());
            throw $e;
        }
    }

    public function obtenerExpedicionesAbiertas()
    {
        try {
            $sql = "SELECT 
                       e.*, COUNT(ei.id) as total_items,
                       COALESCE(SUM(sps.peso_bruto), 0) as peso_total,
                       r.numero_rejilla
                   FROM sist_expediciones e
                   INNER JOIN sist_rejillas r ON e.id_rejilla = r.id
                   LEFT JOIN sist_expedicion_items ei ON e.numero_expedicion = ei.numero_expedicion
                   LEFT JOIN sist_prod_stock sps ON ei.id_stock = sps.id
                   WHERE e.estado = 'ABIERTA'
                   GROUP BY e.id, e.numero_expedicion, e.fecha_creacion, e.estado, 
                            e.transportista, e.conductor, e.placa_vehiculo, e.destino, 
                            e.observaciones, e.usuario_creacion, e.peso, e.tipovehiculo, 
                            e.id_rejilla, r.numero_rejilla
                   ORDER BY e.fecha_creacion DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo expediciones: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerRejillasConAsignaciones()
    {
        try {
            $sql = "SELECT 
                       r.id, r.numero_rejilla, r.capacidad_maxima, r.peso_actual, r.estado,
                       COUNT(ra.id) as total_asignaciones,
                       SUM(ra.peso_asignado) as peso_total_asignado,
                       COUNT(DISTINCT ra.id_venta) as total_ventas
                   FROM sist_rejillas r
                   LEFT JOIN sist_rejillas_asignaciones ra ON r.id = ra.id_rejilla AND ra.estado_asignacion = 'activa'
                   WHERE r.estado IN ('disponible', 'ocupada', 'llena', 'sobrecargada') AND r.peso_actual > 0
                   GROUP BY r.id, r.numero_rejilla, r.capacidad_maxima, r.peso_actual, r.estado
                   ORDER BY r.numero_rejilla";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo rejillas: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerAsignacionesRejilla($idRejilla)
    {
        try {
            $sql = "SELECT ra.*, ra.cliente,
                           COALESCE(ra.nombre_producto, 'Producto sin especificar') as nombre_producto
                   FROM sist_rejillas_asignaciones ra
                   WHERE ra.id_rejilla = :id_rejilla 
                   AND ra.estado_asignacion = 'activa'
                   AND ra.cliente IS NOT NULL AND TRIM(ra.cliente) != ''
                   ORDER BY ra.fecha_asignacion DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_rejilla', $idRejilla, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo asignaciones de rejilla: " . $e->getMessage());
            return [];
        }
    }

    public function registrarLog($accion, $numeroExpedicion, $usuario, $ip, $detalles = null)
    {
        try {
            $sql = "INSERT INTO sist_expediciones_log 
                    (accion, numero_expedicion, usuario, ip_address, detalles) 
                    VALUES (:accion, :numero_expedicion, :usuario, :ip, :detalles)";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':accion', $accion, PDO::PARAM_STR);
            $stmt->bindParam(':numero_expedicion', $numeroExpedicion, PDO::PARAM_STR);
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
            $stmt->bindParam(':ip', $ip, PDO::PARAM_STR);
            $stmt->bindParam(':detalles', $detalles, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error registrando log: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerTransportistasPorDescripcion()
    {
        try {
            $sql = "SELECT descripcion 
                    FROM sist_prod_transportadora 
                    WHERE descripcion IS NOT NULL 
                    AND TRIM(descripcion) != ''
                    ORDER BY descripcion";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo descripciones de transportistas: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerTiposVehiculoPorNombre()
    {
        try {
            $sql = "SELECT nombre 
                    FROM sist_prod_tipo_transporte 
                    WHERE nombre IS NOT NULL 
                    AND TRIM(nombre) != ''
                    ORDER BY nombre";

            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo nombres de tipos de vehículo: " . $e->getMessage());
            return [];
        }
    }
    public function obtenerItemsEscaneadosDetallados($numeroExpedicion)
    {
        try {
            $sql = "SELECT 
                   ei.id as expedicion_item_id,
                   ei.cliente_asignado,
                   ei.es_desconocido,
                   ei.modo_asignacion,
                   ei.cantidad_escaneada,
                   ei.peso_escaneado,
                   ei.fecha_escaneado,
                   ei.usuario_escaneo,
                   sps.id as stock_id,
                   sps.numero_item,
                   sps.nombre_producto,
                   sps.metragem,
                   sps.cliente as cliente_original,
                   sps.peso_bruto,
                   sps.bobinas_pacote,
                   ra.id as asignacion_id,
                   ra.cliente as cliente_asignacion
               FROM sist_expedicion_items ei
               INNER JOIN sist_prod_stock sps ON ei.id_stock = sps.id
               LEFT JOIN sist_rejillas_asignaciones ra ON ei.id_asignacion_rejilla = ra.id
               WHERE ei.numero_expedicion = :numero_expedicion
               ORDER BY ei.fecha_escaneado DESC";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':numero_expedicion', $numeroExpedicion, PDO::PARAM_STR);
            $stmt->execute();

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $itemsFormateados = [];
            foreach ($items as $item) {
                $itemsFormateados[] = [
                    'expedicion_item_id' => $item['expedicion_item_id'],
                    'stock_id' => $item['stock_id'],
                    'numero_item' => $item['numero_item'],
                    'nombre_producto' => $item['nombre_producto'],
                    'cliente_asignado' => $item['cliente_asignado'],
                    'cliente_original' => $item['cliente_original'],
                    'es_desconocido' => (bool)$item['es_desconocido'],
                    'modo_asignacion' => $item['modo_asignacion'],
                    'cantidad_escaneada' => (int)$item['cantidad_escaneada'],
                    'peso_escaneado' => (float)$item['peso_escaneado'],
                    'peso_bruto' => (float)$item['peso_bruto'],
                    'peso_formateado' => number_format($item['peso_escaneado'], 2) . ' kg',
                    'bobinas_pacote' => (int)$item['bobinas_pacote'],
                    'fecha_escaneado' => $item['fecha_escaneado'],
                    'fecha_formateada' => date('d/m/Y H:i', strtotime($item['fecha_escaneado'])),
                    'usuario_escaneo' => $item['usuario_escaneo'],
                    'asignacion_id' => $item['asignacion_id'],
                    'metragem' => $item['metragem'],
                    'tiene_asignacion' => !empty($item['asignacion_id']),
                    'cliente_display' => $item['es_desconocido'] ? 'DESCONOCIDO' : $item['cliente_asignado'],
                    'icono_estado' => $this->obtenerIconoEstado($item),
                    'clase_estado' => $this->obtenerClaseEstado($item),
                    'puede_eliminar' => true,
                    'tiempo_transcurrido' => $this->calcularTiempoTranscurrido($item['fecha_escaneado'])
                ];
            }

            return $itemsFormateados;
        } catch (Exception $e) {
            error_log("Error obteniendo items escaneados detallados: " . $e->getMessage());
            return [];
        }
    }

    public function eliminarItemEscaneado($idExpedicionItem, $numeroExpedicion)
    {
        try {
            $sqlInfo = "SELECT 
                       ei.id_stock,
                       ei.cliente_asignado,
                       ei.cantidad_escaneada,
                       ei.peso_escaneado,
                       sps.numero_item,
                       sps.nombre_producto
                   FROM sist_expedicion_items ei
                   INNER JOIN sist_prod_stock sps ON ei.id_stock = sps.id
                   WHERE ei.id = :id_expedicion_item
                   AND ei.numero_expedicion = :numero_expedicion";

            $stmt = $this->conexion->prepare($sqlInfo);
            $stmt->bindParam(':id_expedicion_item', $idExpedicionItem, PDO::PARAM_INT);
            $stmt->bindParam(':numero_expedicion', $numeroExpedicion, PDO::PARAM_STR);
            $stmt->execute();

            $itemInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$itemInfo) {
                throw new Exception("Item no encontrado en la expedición");
            }
            $sqlDelete = "DELETE FROM sist_expedicion_items 
                     WHERE id = :id_expedicion_item 
                     AND numero_expedicion = :numero_expedicion";
            $stmt = $this->conexion->prepare($sqlDelete);
            $stmt->bindParam(':id_expedicion_item', $idExpedicionItem, PDO::PARAM_INT);
            $stmt->bindParam(':numero_expedicion', $numeroExpedicion, PDO::PARAM_STR);
            $resultado = $stmt->execute();

            if ($resultado) {
                $sqlRestaurar = "UPDATE sist_prod_stock 
                           SET estado = 'en stock'
                           WHERE id = :id_stock";

                $stmt = $this->conexion->prepare($sqlRestaurar);
                $stmt->bindParam(':id_stock', $itemInfo['id_stock'], PDO::PARAM_INT);
                $stmt->execute();
            }

            return [
                'eliminado' => $resultado,
                'item_info' => $itemInfo
            ];
        } catch (Exception $e) {
            error_log("Error eliminando item de expedición: " . $e->getMessage());
            throw $e;
        }
    }

    public function eliminarMultiplesItemsEscaneados($idsItems, $numeroExpedicion)
    {
        try {
            $itemsEliminados = [];
            $errores = [];

            foreach ($idsItems as $idItem) {
                try {
                    $resultado = $this->eliminarItemEscaneado($idItem, $numeroExpedicion);
                    if ($resultado['eliminado']) {
                        $itemsEliminados[] = $resultado['item_info'];
                    }
                } catch (Exception $e) {
                    $errores[] = [
                        'id_item' => $idItem,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return [
                'items_eliminados' => $itemsEliminados,
                'errores' => $errores,
                'total_eliminados' => count($itemsEliminados),
                'total_errores' => count($errores)
            ];
        } catch (Exception $e) {
            error_log("Error eliminando múltiples items: " . $e->getMessage());
            throw $e;
        }
    }

    private function obtenerIconoEstado($item)
    {
        if ($item['es_desconocido']) {
            if ($item['modo_asignacion'] === 'desconocido_fuera_rejilla') {
                return '📍';
            }
            return '❓';
        }

        return '✅';
    }

    private function obtenerClaseEstado($item)
    {
        if ($item['es_desconocido']) {
            if ($item['modo_asignacion'] === 'desconocido_fuera_rejilla') {
                return 'badge bg-info';
            }
            return 'badge bg-warning';
        }

        return 'badge bg-success';
    }

    private function calcularTiempoTranscurrido($fechaEscaneo)
    {
        try {
            $inicio = new DateTime($fechaEscaneo);
            $ahora = new DateTime();
            $diferencia = $ahora->diff($inicio);

            if ($diferencia->days > 0) {
                return "hace {$diferencia->days} días";
            } elseif ($diferencia->h > 0) {
                return "hace {$diferencia->h} horas";
            } elseif ($diferencia->i > 30) {
                return "hace {$diferencia->i} minutos";
            } else {
                return "recién escaneado";
            }
        } catch (Exception $e) {
            return "tiempo desconocido";
        }
    }

    public function eliminarExpedicion($numeroExpedicion)
    {
        try {
            $this->conexion->beginTransaction();

            $expedicion = $this->verificarExpedicion($numeroExpedicion);
            if (!$expedicion) {
                throw new Exception("Expedición no encontrada");
            }

            if ($expedicion['estado'] !== 'ABIERTA') {
                throw new Exception("Solo se pueden eliminar expediciones abiertas");
            }

            $sqlCount = "SELECT COUNT(*) as total FROM sist_expedicion_items WHERE numero_expedicion = :numero";
            $stmt = $this->conexion->prepare($sqlCount);
            $stmt->bindParam(':numero', $numeroExpedicion, PDO::PARAM_STR);
            $stmt->execute();
            $totalItems = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            if ($totalItems > 0) {
                throw new Exception("No se puede eliminar una expedición con items escaneados. Tiene $totalItems item(s).");
            }

            $sqlExpedicion = "DELETE FROM sist_expediciones WHERE numero_expedicion = :numero";
            $stmt = $this->conexion->prepare($sqlExpedicion);
            $stmt->bindParam(':numero', $numeroExpedicion, PDO::PARAM_STR);
            $stmt->execute();

            $this->conexion->commit();

            return [
                'success' => true,
                'items_eliminados' => 0
            ];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }

    public function cancelarReservaConflictiva($idItem, $numeroExpedicion = null)
    {
        try {
            $sqlItem = "SELECT id_venta, nombre_producto, bobinas_pacote 
                       FROM sist_prod_stock 
                       WHERE id = :id_item";
            $stmt = $this->conexion->prepare($sqlItem);
            $stmt->bindParam(':id_item', $idItem, PDO::PARAM_INT);
            $stmt->execute();
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                return ['cancelado' => false, 'motivo' => 'Item no encontrado'];
            }

            $bobinasItem = $item['bobinas_pacote'] ?: 1;

            $sqlBuscarReserva = "
                SELECT r.id, r.cantidad_reservada, r.id_venta, r.cliente,
                       sa.nombre_producto, sa.bobinas_pacote
                FROM reservas_stock r
                INNER JOIN stock_agregado sa ON r.id_stock_agregado = sa.id  
                WHERE sa.nombre_producto = :nombre_producto
                AND sa.bobinas_pacote = :bobinas_pacote
                AND r.estado = 'activa'
                AND r.cantidad_reservada >= :bobinas_item
                ORDER BY r.fecha_reserva ASC
                LIMIT 1";

            $stmt = $this->conexion->prepare($sqlBuscarReserva);
            $stmt->bindParam(':nombre_producto', $item['nombre_producto'], PDO::PARAM_STR);
            $stmt->bindParam(':bobinas_pacote', $item['bobinas_pacote'], PDO::PARAM_INT);
            $stmt->bindParam(':bobinas_item', $bobinasItem, PDO::PARAM_INT);
            $stmt->execute();
            $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reserva) {
                return ['cancelado' => false, 'motivo' => 'No hay reservas que cancelar'];
            }

            $sqlCancelarReserva = "
                UPDATE reservas_stock 
                SET cantidad_reservada = cantidad_reservada - :bobinas_cancelar,
                    cantidad_cancelada = COALESCE(cantidad_cancelada, 0) + :bobinas_cancelar,
                    fecha_cancelacion = CURRENT_TIMESTAMP,
                    observaciones = CONCAT(COALESCE(observaciones, ''), 
                                         ' | CANCELADO AUTOMÁTICO por escaneado flexible: Expedición ', 
                                         COALESCE(:numero_expedicion, 'N/A'))
                WHERE id = :id_reserva";

            $stmt = $this->conexion->prepare($sqlCancelarReserva);
            $stmt->bindParam(':bobinas_cancelar', $bobinasItem, PDO::PARAM_INT);
            $stmt->bindParam(':id_reserva', $reserva['id'], PDO::PARAM_INT);
            $stmt->bindParam(':numero_expedicion', $numeroExpedicion, PDO::PARAM_STR);
            $resultado = $stmt->execute();

            $sqlMarcarCancelada = "
                UPDATE reservas_stock 
                SET estado = 'cancelada'
                WHERE id = :id_reserva AND cantidad_reservada <= 0";

            $stmt = $this->conexion->prepare($sqlMarcarCancelada);
            $stmt->bindParam(':id_reserva', $reserva['id'], PDO::PARAM_INT);
            $stmt->execute();

            $sqlLiberarStock = "
                UPDATE stock_agregado 
                SET cantidad_reservada = GREATEST(0, cantidad_reservada - :bobinas_liberar),
                    cantidad_disponible = cantidad_disponible + :bobinas_liberar,
                    fecha_actualizacion = CURRENT_TIMESTAMP
                WHERE nombre_producto = :nombre_producto 
                AND bobinas_pacote = :bobinas_pacote";

            $stmt = $this->conexion->prepare($sqlLiberarStock);
            $stmt->bindParam(':bobinas_liberar', $bobinasItem, PDO::PARAM_INT);
            $stmt->bindParam(':nombre_producto', $item['nombre_producto'], PDO::PARAM_STR);
            $stmt->bindParam(':bobinas_pacote', $item['bobinas_pacote'], PDO::PARAM_INT);
            $stmt->execute();

            return [
                'cancelado' => true,
                'reserva_cancelada' => $reserva['id'],
                'cliente_anterior' => $reserva['cliente'],
                'venta_anterior' => $reserva['id_venta'],
                'bobinas_liberadas' => $bobinasItem,
                'motivo' => 'Reserva cancelada automáticamente por flexibilidad del sistema'
            ];
        } catch (Exception $e) {
            error_log("Error cancelando reserva conflictiva: " . $e->getMessage());
            return ['cancelado' => false, 'motivo' => 'Error: ' . $e->getMessage()];
        }
    }
    public function limpiarDescripcionRejilla($idRejilla)
    {
        try {
            $sql = "UPDATE sist_rejillas 
                SET descripcion = NULL,
                    fecha_actualizacion = CURRENT_TIMESTAMP
                WHERE id = :id_rejilla";

            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id_rejilla', $idRejilla, PDO::PARAM_INT);
            $resultado = $stmt->execute();

            if ($resultado && $stmt->rowCount() > 0) {
                error_log("DESCRIPCIÓN LIMPIADA - Rejilla ID: {$idRejilla} - Descripción borrada al despachar");
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("Error limpiando descripción de rejilla al despachar: " . $e->getMessage());
            return false;
        }
    }
}
