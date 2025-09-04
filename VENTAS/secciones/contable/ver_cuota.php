<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '3']);

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: " . $url_base . "secciones/contable/cuentas_cobrar.php?error=ID de cuota no proporcionado");
    exit();
}

$idCuota = $_GET['id'];

if (file_exists("controllers/CuentasCobrarController.php")) {
    include "controllers/CuentasCobrarController.php";
} else {
    die("Error: No se pudo cargar el controlador de cuentas por cobrar.");
}

$controller = new CuentasCobrarController($conexion, $url_base);

if (!$controller->verificarPermisos()) {
    header("Location: " . $url_base . "secciones/contable/cuentas_cobrar.php?error=No tienes permisos para acceder a esta sección");
    exit();
}

try {
    $datosCuota = $controller->obtenerDetalleCuota($idCuota);
    $cuota = $datosCuota['cuota'];
    $pagos = $datosCuota['pagos'];
    $otrasCuotas = $datosCuota['otras_cuotas'];

    // Calcular totales de la venta
    $totalCuotas = array_sum(array_column($otrasCuotas, 'monto_cuota'));
    $totalPagado = array_sum(array_column($otrasCuotas, 'monto_pagado'));
    $totalPendiente = array_sum(array_column($otrasCuotas, 'monto_pendiente'));
} catch (Exception $e) {
    header("Location: " . $url_base . "secciones/contable/cuentas_cobrar.php?error=" . urlencode($e->getMessage()));
    exit();
}

// Procesar regeneración de cuotas
$error = '';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regenerar_cuotas'])) {
    $idVenta = $_POST['id_venta'];
    $nuevaFechaInicio = $_POST['nueva_fecha_inicio'];

    if (!$controller->verificarPermisos()) {
        $error = 'No tienes permisos para esta acción';
    } else {
        try {
            $resultado = $controller->regenerarCuotasConNuevaFecha(
                $idVenta,
                $cuota['total_venta'],
                $cuota['tipocredito'],
                $cuota['fecha_venta'],
                $nuevaFechaInicio
            );

            if ($resultado['success']) {
                $mensaje = "Cuotas regeneradas con nueva fecha de inicio: " . date('d/m/Y', strtotime($nuevaFechaInicio));
                $controller->logActividad('Regenerar cuotas con nueva fecha', "Venta: $idVenta, Nueva fecha: $nuevaFechaInicio");

                // ✅ OBTENER EL ID DE LA NUEVA PRIMERA CUOTA DESPUÉS DE LA REGENERACIÓN
                try {
                    $nuevasCuotas = $controller->getConexion()->prepare("SELECT id FROM public.sist_ventas_cuentas_cobrar WHERE id_venta = ? ORDER BY numero_cuota ASC LIMIT 1");
                    $nuevasCuotas->execute([$idVenta]);
                    $nuevaPrimeraCuota = $nuevasCuotas->fetch(PDO::FETCH_ASSOC);

                    if ($nuevaPrimeraCuota) {
                        $nuevoIdCuota = $nuevaPrimeraCuota['id'];
                        header("Location: ver_cuota.php?id=" . $nuevoIdCuota . "&mensaje=" . urlencode($mensaje));
                    } else {
                        // Fallback si no se encuentra la nueva cuota
                        header("Location: cuentas_cobrar.php?mensaje=" . urlencode($mensaje));
                    }
                } catch (Exception $e) {
                    // Fallback en caso de error
                    header("Location: cuentas_cobrar.php?mensaje=" . urlencode($mensaje));
                }
                exit();
            } else {
                $error = $resultado['error'];
            }
        } catch (Exception $e) {
            $error = 'Error al regenerar cuotas: ' . $e->getMessage();
        }
    }
}

// Manejar mensajes de la URL
if (isset($_GET['mensaje'])) {
    $mensaje = htmlspecialchars($_GET['mensaje']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

$datosVista = $controller->obtenerDatosVista('Detalles de Cuota - Venta #' . $cuota['id_venta']);
$configuracionJS = $controller->obtenerConfiguracionJS();
$controller->logActividad('Ver detalles cuota', 'ID: ' . $idCuota);

// Obtener información de validación para regeneración
$infoRegeneracion = $controller->puedeRegenerarCuotas($cuota['id_venta']);
$breadcrumb_items = ['Sector Contable', 'Cuentas por Cobrar', 'Detalles de Cuota'];
$item_urls = [
    $url_base . 'secciones/contable/main.php',
    $url_base . 'secciones/contable/cuentas_cobrar.php'
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Cuota - Venta #<?php echo $cuota['id_venta']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="<?php echo $url_base; ?>secciones/contable/utils/styles.css" rel="stylesheet">
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <!-- Mensajes de éxito/error -->
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Header con información principal -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card card-stats">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h3 class="mb-1">
                                    <i class="fas fa-file-invoice me-2"></i>
                                    Venta #<?php echo $cuota['id_venta']; ?> - Cuota <?php echo $cuota['numero_cuota']; ?>
                                </h3>
                                <p class="mb-2">
                                    <strong>Cliente:</strong> <?php echo htmlspecialchars($cuota['cliente']); ?> |
                                    <strong>Vendedor:</strong> <?php echo htmlspecialchars($cuota['vendedor']); ?>
                                </p>
                                <div class="d-flex align-items-center">
                                    <span class="estado-cuota <?php echo $cuota['estado_class']; ?> me-3">
                                        <?php echo ucfirst(strtolower($cuota['estado_actual'] ?? $cuota['estado'])); ?>
                                    </span>
                                    <?php if (isset($cuota['dias_texto'])): ?>
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-calendar me-1"></i><?php echo $cuota['dias_texto']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <h2 class="mb-1"><?php echo $controller->formatearMoneda($cuota['monto_cuota'], $cuota['moneda']); ?></h2>
                                <p class="mb-0">Monto de la cuota</p>
                                <small>Vence: <?php echo $cuota['fecha_vencimiento_formateada']; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Información detallada de la venta -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Información de la Venta
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <strong>Código de Venta:</strong>
                            </div>
                            <div class="col-6">
                                <span class="badge bg-primary">#<?php echo $cuota['id_venta']; ?></span>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <strong>Cliente:</strong>
                            </div>
                            <div class="col-6">
                                <?php echo htmlspecialchars($cuota['cliente']); ?>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <strong>Vendedor:</strong>
                            </div>
                            <div class="col-6">
                                <?php echo htmlspecialchars($cuota['vendedor']); ?>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <strong>Fecha de Venta:</strong>
                            </div>
                            <div class="col-6">
                                <?php echo $cuota['fecha_venta_formateada']; ?>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <strong>Total de la Venta:</strong>
                            </div>
                            <div class="col-6">
                                <strong class="text-success"><?php echo $controller->formatearMoneda($cuota['total_venta'], $cuota['moneda']); ?></strong>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <strong>Tipo de Crédito:</strong>
                            </div>
                            <div class="col-6">
                                <span class="badge bg-info"><?php echo htmlspecialchars($cuota['tipocredito']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>Resumen del Plan de Pagos
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <h6 class="text-primary">Total Plan</h6>
                                <h5><?php echo $controller->formatearMoneda($cuota['total_venta'], $cuota['moneda']); ?></h5>
                            </div>
                            <div class="col-4">
                                <h6 class="text-success">Total Pagado</h6>
                                <h5><?php echo $controller->formatearMoneda($totalPagado, $cuota['moneda']); ?></h5>
                            </div>
                            <div class="col-4">
                                <h6 class="text-danger">Total Pendiente</h6>
                                <h5><?php echo $controller->formatearMoneda($totalPendiente, $cuota['moneda']); ?></h5>
                            </div>
                        </div>

                        <?php
                        $porcentajeTotal = $cuota['total_venta'] > 0 ? ($totalPagado / $cuota['total_venta']) * 100 : 0;
                        ?>
                        <div class="progress mb-3" style="height: 20px;">
                            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated"
                                role="progressbar"
                                style="width: <?php echo $porcentajeTotal; ?>%"
                                aria-valuenow="<?php echo $porcentajeTotal; ?>"
                                aria-valuemin="0"
                                aria-valuemax="100">
                                <?php echo round($porcentajeTotal, 1); ?>% pagado
                            </div>
                        </div>

                        <p class="text-center mb-0">
                            <small class="text-muted">
                                Plan de <?php echo count($otrasCuotas); ?> cuota(s) |
                                <?php
                                $cuotasPagadas = array_filter($otrasCuotas, function ($c) {
                                    return $c['estado'] === 'PAGADO';
                                });
                                echo count($cuotasPagadas);
                                ?> completamente pagada(s)
                            </small>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0">
                            <i class="fas fa-tools me-2"></i>Herramientas Administrativas
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h6 class="mb-1">Regenerar Fechas de Vencimiento</h6>
                                <p class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Recalcula todas las fechas usando una nueva fecha de inicio.
                                    </small>
                                </p>

                                <!-- Información actual -->
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <small class="text-muted">Fecha actual de cálculo:</small>
                                        <br>
                                        <?php if ($cuota['tiene_fecha_personalizada']): ?>
                                            <span class="badge bg-primary">
                                                <?php echo $cuota['fecha_calculo_efectiva_formateada']; ?>
                                            </span>
                                            <small class="text-info d-block">
                                                (Personalizada - Original: <?php echo $cuota['fecha_venta_formateada']; ?>)
                                            </small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <?php echo $cuota['fecha_venta_formateada']; ?>
                                            </span>
                                            <small class="text-muted d-block">(Fecha de venta original)</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Tipo de crédito:</small>
                                        <br><span class="badge bg-info"><?php echo $cuota['tipocredito']; ?></span>
                                    </div>
                                </div>

                                <!-- Advertencia sobre pagos -->
                                <?php if ($infoRegeneracion['hay_pagos']): ?>
                                    <div class="alert alert-danger alert-sm">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>¡ADVERTENCIA!</strong> Esta venta tiene
                                        <strong><?php echo $controller->formatearMoneda($infoRegeneracion['total_pagado'], $cuota['moneda']); ?></strong>
                                        en pagos registrados. Al regenerar las cuotas, estos pagos se perderán y deberán ser re-registrados manualmente.
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-success alert-sm">
                                        <i class="fas fa-check-circle me-2"></i>
                                        No hay pagos registrados. Es seguro regenerar las cuotas.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-4 text-end">
                                <button type="button"
                                    class="btn btn-warning btn-lg"
                                    onclick="mostrarModalCambiarFecha()"
                                    data-tiene-pagos="<?php echo $infoRegeneracion['hay_pagos'] ? 'true' : 'false'; ?>"
                                    data-total-pagos="<?php echo $infoRegeneracion['total_pagado']; ?>">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    Cambiar Fecha de Inicio
                                </button>

                                <?php if ($cuota['tiene_fecha_personalizada']): ?>
                                    <br><br>
                                    <button type="button"
                                        class="btn btn-outline-secondary btn-sm"
                                        onclick="mostrarModalRestaurarFecha()">
                                        <i class="fas fa-undo me-2"></i>
                                        Restaurar Fecha Original
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Plan completo de cuotas -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Plan Completo de Cuotas
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th><i class="fas fa-list-ol me-1"></i>Cuota</th>
                                <th><i class="fas fa-calendar me-1"></i>Vencimiento</th>
                                <th><i class="fas fa-money-bill me-1"></i>Monto</th>
                                <th><i class="fas fa-credit-card me-1"></i>Pagado</th>
                                <th><i class="fas fa-exclamation-circle me-1"></i>Pendiente</th>
                                <th><i class="fas fa-info-circle me-1"></i>Estado</th>
                                <th><i class="fas fa-cogs me-1"></i>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($otrasCuotas as $otraCuota): ?>
                                <tr class="<?php echo $otraCuota['id'] == $cuota['id'] ? 'table-primary' : ''; ?>">
                                    <td>
                                        <span class="badge <?php echo $otraCuota['id'] == $cuota['id'] ? 'bg-primary' : 'bg-secondary'; ?>">
                                            <?php echo $otraCuota['numero_cuota']; ?>
                                        </span>
                                        <?php if ($otraCuota['id'] == $cuota['id']): ?>
                                            <small class="text-primary d-block">(Actual)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $otraCuota['fecha_vencimiento_formateada']; ?>
                                        <?php if (isset($otraCuota['dias_texto'])): ?>
                                            <br><small class="<?php echo $otraCuota['dias_class']; ?>"><?php echo $otraCuota['dias_texto']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $controller->formatearMoneda($otraCuota['monto_cuota'], $otraCuota['moneda']); ?></td>
                                    <td>
                                        <?php echo $controller->formatearMoneda($otraCuota['monto_pagado'], $otraCuota['moneda']); ?>
                                        <?php if ($otraCuota['fecha_ultimo_pago']): ?>
                                            <br><small class="text-muted"><?php echo $otraCuota['fecha_ultimo_pago_formateada']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $controller->formatearMoneda($otraCuota['monto_pendiente'], $otraCuota['moneda']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $otraCuota['estado_class']; ?>">
                                            <?php echo ucfirst(strtolower($otraCuota['estado'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if ($otraCuota['estado'] !== 'PAGADO'): ?>
                                                <a href="conciliar_cuota.php?id=<?php echo $otraCuota['id']; ?>"
                                                    class="btn btn-warning" title="Conciliar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($otraCuota['id'] != $cuota['id']): ?>
                                                <a href="ver_cuota.php?id=<?php echo $otraCuota['id']; ?>"
                                                    class="btn btn-info" title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Historial de pagos de esta cuota -->
        <?php if (!empty($pagos)): ?>
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>Historial de Pagos de esta Cuota
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($pagos as $index => $pago): ?>
                            <div class="col-md-6 mb-3">
                                <div class="timeline-item">
                                    <div class="card shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="text-success mb-0">
                                                    <i class="fas fa-check-circle me-1"></i>
                                                    Pago #<?php echo count($pagos) - $index; ?>
                                                </h6>
                                                <span class="badge bg-success">
                                                    <?php echo $controller->formatearMoneda($pago['monto_pago'], $cuota['moneda']); ?>
                                                </span>
                                            </div>

                                            <div class="row">
                                                <div class="col-6">
                                                    <small class="text-muted">Fecha:</small>
                                                    <p class="mb-1"><?php echo $pago['fecha_pago_formateada']; ?></p>
                                                </div>
                                                <?php if ($pago['forma_pago']): ?>
                                                    <div class="col-6">
                                                        <small class="text-muted">Forma:</small>
                                                        <p class="mb-1"><?php echo htmlspecialchars($pago['forma_pago']); ?></p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($pago['referencia_pago']): ?>
                                                <div class="mb-2">
                                                    <small class="text-muted">Referencia:</small>
                                                    <p class="mb-0"><?php echo htmlspecialchars($pago['referencia_pago']); ?></p>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($pago['observaciones']): ?>
                                                <div class="mb-2">
                                                    <small class="text-muted">Observaciones:</small>
                                                    <p class="mb-0"><?php echo htmlspecialchars($pago['observaciones']); ?></p>
                                                </div>
                                            <?php endif; ?>

                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($pago['usuario_registro']); ?>
                                                </small>
                                                <?php if ($pago['comprobante_base64']): ?>
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline-primary"
                                                        onclick="verComprobante('<?php echo $pago['comprobante_tipo']; ?>', '<?php echo $pago['comprobante_base64']; ?>', '<?php echo htmlspecialchars($pago['comprobante_nombre']); ?>')">
                                                        <i class="fas fa-file-alt me-1"></i>Ver Comprobante
                                                    </button>
                                                <?php endif; ?>
                                            </div>

                                            <hr class="my-2">
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                Registrado: <?php echo $pago['fecha_registro_formateada']; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal para ver comprobantes -->
    <div class="modal fade" id="modalComprobante" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-alt me-2"></i>Comprobante de Pago
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center" id="comprobante-container">
                    <!-- Contenido dinámico -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cerrar
                    </button>
                    <button type="button" id="btn-descargar-comprobante" class="btn btn-primary">
                        <i class="fas fa-download me-2"></i>Descargar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para cambiar fecha de inicio -->
    <div class="modal fade" id="modalCambiarFecha" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-calendar-alt me-2"></i>Cambiar Fecha de Inicio de Cuotas
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formCambiarFecha">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Esto recalculará TODAS las fechas de vencimiento de las cuotas usando la nueva fecha como punto de partida.
                            La nueva fecha se guardará como fecha de inicio personalizada para esta venta.
                        </div>

                        <input type="hidden" name="regenerar_cuotas" value="1">
                        <input type="hidden" name="id_venta" value="<?php echo $cuota['id_venta']; ?>">

                        <!-- Información actual mejorada -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card border-primary">
                                    <div class="card-body p-3">
                                        <h6 class="card-title text-primary">
                                            <i class="fas fa-info-circle me-1"></i>Información Actual
                                        </h6>
                                        <p class="mb-1"><strong>Venta:</strong> #<?php echo $cuota['id_venta']; ?></p>
                                        <p class="mb-1"><strong>Cliente:</strong> <?php echo htmlspecialchars($cuota['cliente']); ?></p>
                                        <p class="mb-1"><strong>Tipo de crédito:</strong> <span class="badge bg-info"><?php echo $cuota['tipocredito']; ?></span></p>
                                        <p class="mb-1"><strong>Total cuotas:</strong> <?php echo count($otrasCuotas); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-secondary">
                                    <div class="card-body p-3">
                                        <h6 class="card-title text-secondary">
                                            <i class="fas fa-calendar me-1"></i>Fechas Actuales
                                        </h6>
                                        <p class="mb-1">
                                            <strong>Fecha de venta:</strong><br>
                                            <?php echo $cuota['fecha_venta_formateada']; ?>
                                        </p>
                                        <p class="mb-1">
                                            <strong>Fecha de cálculo actual:</strong><br>
                                            <?php if ($cuota['tiene_fecha_personalizada']): ?>
                                                <span class="text-primary"><?php echo $cuota['fecha_calculo_efectiva_formateada']; ?></span>
                                                <small class="text-muted d-block">(Personalizada)</small>
                                            <?php else: ?>
                                                <span class="text-secondary"><?php echo $cuota['fecha_venta_formateada']; ?></span>
                                                <small class="text-muted d-block">(Original)</small>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Nueva fecha -->
                        <div class="mb-3">
                            <label for="nueva_fecha_inicio" class="form-label">
                                <i class="fas fa-calendar me-1"></i>Nueva Fecha de Inicio <span class="text-danger">*</span>
                            </label>
                            <input type="date"
                                class="form-control form-control-lg"
                                id="nueva_fecha_inicio"
                                name="nueva_fecha_inicio"
                                value="<?php echo $cuota['fecha_calculo_efectiva'] ?? $cuota['fecha_venta']; ?>"
                                max="<?php echo date('Y-m-d'); ?>"
                                required>
                            <div class="form-text">
                                <i class="fas fa-lightbulb me-1"></i>
                                Esta fecha se guardará como la nueva fecha de referencia para todos los cálculos de vencimiento de esta venta.
                            </div>
                        </div>

                        <!-- Vista previa mejorada -->
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-eye me-1"></i>Vista Previa de Nuevos Vencimientos:
                            </label>
                            <div id="preview-vencimientos" class="preview-fechas">
                                <small class="text-muted">Selecciona una fecha para ver la vista previa</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="button" class="btn btn-warning" onclick="confirmarRegeneracion()">
                            <i class="fas fa-sync-alt me-2"></i>Regenerar Cuotas
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para restaurar fecha original -->
    <?php if ($cuota['tiene_fecha_personalizada']): ?>
        <div class="modal fade" id="modalRestaurarFecha" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-undo me-2"></i>Restaurar Fecha Original
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" id="formRestaurarFecha">
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Esto restaurará el cálculo de cuotas usando la fecha de venta original como punto de partida.
                            </div>

                            <?php if ($infoRegeneracion['hay_pagos']): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Advertencia:</strong> Los pagos registrados (<?php echo $controller->formatearMoneda($infoRegeneracion['total_pagado'], $cuota['moneda']); ?>) se perderán.
                                </div>
                            <?php endif; ?>

                            <input type="hidden" name="regenerar_cuotas" value="1">
                            <input type="hidden" name="id_venta" value="<?php echo $cuota['id_venta']; ?>">
                            <input type="hidden" name="nueva_fecha_inicio" value="<?php echo $cuota['fecha_venta']; ?>">

                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">Fecha actual de cálculo:</small>
                                    <p class="mb-0"><strong><?php echo $cuota['fecha_calculo_efectiva_formateada']; ?></strong></p>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Fecha original de venta:</small>
                                    <p class="mb-0"><strong><?php echo $cuota['fecha_venta_formateada']; ?></strong></p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-outline-warning" onclick="confirmarRestauracion()">
                                <i class="fas fa-undo me-2"></i>Restaurar Fecha Original
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- NUEVO: Modal de confirmación personalizado -->
    <div class="modal fade modal-confirmacion" id="modalConfirmacion" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-3"></i>
                        <span id="tituloConfirmacion">Confirmar Acción</span>
                    </h4>
                </div>
                <div class="modal-body text-center">
                    <div class="warning-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>

                    <div id="contenidoConfirmacion">
                        <!-- Contenido dinámico -->
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-cancelar" onclick="cerrarModalConfirmacion()">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-confirmar" id="btnConfirmarAccion">
                        <i class="fas fa-check me-2"></i>Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $url_base; ?>config/notificacion/sistema-notificaciones.js"></script>

    <script>
        const CUENTAS_COBRAR_CONFIG = <?php echo json_encode($configuracionJS); ?>;

        // Variables globales
        let modalConfirmacion;
        let accionPendiente = null;

        // Datos de PHP para JavaScript
        const DATOS_VENTA = {
            tipoCredito: '<?php echo $cuota['tipocredito']; ?>',
            fechaVenta: '<?php echo $cuota['fecha_venta']; ?>',
            fechaCalculoActual: '<?php echo $cuota['fecha_calculo_efectiva'] ?? $cuota['fecha_venta']; ?>',
            tienePagos: <?php echo $infoRegeneracion['hay_pagos'] ? 'true' : 'false'; ?>,
            totalPagos: <?php echo $infoRegeneracion['total_pagado']; ?>,
            moneda: '<?php echo $cuota['moneda']; ?>',
            simboloMoneda: '<?php echo $controller->obtenerSimboloMoneda($cuota['moneda']); ?>'
        };

        // Función para ver comprobantes
        function verComprobante(tipo, base64, nombre) {
            const container = document.getElementById('comprobante-container');
            const btnDescargar = document.getElementById('btn-descargar-comprobante');

            if (tipo.startsWith('image/')) {
                container.innerHTML = `
                    <img src="data:${tipo};base64,${base64}" 
                         class="img-fluid rounded shadow" 
                         style="max-height: 70vh; object-fit: contain;" 
                         alt="${nombre}">
                `;
            } else if (tipo === 'application/pdf') {
                container.innerHTML = `
                    <iframe src="data:${tipo};base64,${base64}" 
                            style="width: 100%; height: 70vh; border: none;"
                            title="${nombre}">
                    </iframe>
                `;
            } else {
                container.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No se puede previsualizar este tipo de archivo
                    </div>
                `;
            }

            btnDescargar.onclick = function() {
                const link = document.createElement('a');
                link.href = `data:${tipo};base64,${base64}`;
                link.download = nombre;
                link.click();
            };

            const modal = new bootstrap.Modal(document.getElementById('modalComprobante'));
            modal.show();
        }

        // NUEVA FUNCIÓN: Mostrar modal personalizado de confirmación
        function mostrarModalConfirmacion(titulo, contenido, accionConfirmar) {
            document.getElementById('tituloConfirmacion').textContent = titulo;
            document.getElementById('contenidoConfirmacion').innerHTML = contenido;

            const btnConfirmar = document.getElementById('btnConfirmarAccion');
            btnConfirmar.onclick = accionConfirmar;

            modalConfirmacion.show();
        }

        function cerrarModalConfirmacion() {
            modalConfirmacion.hide();
            accionPendiente = null;
        }

        // NUEVA FUNCIÓN: Mostrar modal para cambiar fecha
        function mostrarModalCambiarFecha() {
            const modal = new bootstrap.Modal(document.getElementById('modalCambiarFecha'));
            modal.show();
        }

        // NUEVA FUNCIÓN: Mostrar modal para restaurar fecha
        function mostrarModalRestaurarFecha() {
            const modal = new bootstrap.Modal(document.getElementById('modalRestaurarFecha'));
            modal.show();
        }

        // NUEVA FUNCIÓN: Confirmar regeneración con modal personalizado
        function confirmarRegeneracion() {
            const fechaNueva = document.getElementById('nueva_fecha_inicio').value;
            const fechaHoy = new Date().toISOString().split('T')[0];

            if (!fechaNueva) {
                alert('Por favor selecciona una fecha');
                return;
            }

            if (fechaNueva > fechaHoy) {
                alert('La fecha no puede ser futura');
                return;
            }

            const fechaFormateada = new Date(fechaNueva).toLocaleDateString('es-PY');

            let contenido = `
                <h5 class="mb-3">¿Regenerar cuotas con nueva fecha?</h5>
                
                <div class="info-box">
                    <strong><i class="fas fa-calendar me-2"></i>Nueva fecha de inicio:</strong> 
                    <span class="badge bg-primary">${fechaFormateada}</span>
                </div>
            `;

            if (DATOS_VENTA.tienePagos) {
                contenido += `
                    <div class="danger-box">
                        <strong><i class="fas fa-exclamation-triangle me-2"></i>¡ADVERTENCIA!</strong><br>
                        Se perderán los pagos registrados por un total de:<br>
                        <strong class="text-danger fs-5">${DATOS_VENTA.simboloMoneda}${new Intl.NumberFormat('es-PY').format(DATOS_VENTA.totalPagos)}</strong><br>
                        <small>Deberán ser re-registrados manualmente después de la regeneración.</small>
                    </div>
                `;
            } else {
                contenido += `
                    <div class="info-box">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        No hay pagos registrados. Es seguro proceder.
                    </div>
                `;
            }

            contenido += `
                <div class="diferencia-fechas">
                    <i class="fas fa-info-circle me-2"></i>
                    Esta acción no se puede deshacer
                </div>
            `;

            mostrarModalConfirmacion(
                'Regenerar Cuotas',
                contenido,
                function() {
                    // Ejecutar regeneración
                    const btnSubmit = document.querySelector('#formCambiarFecha button[type="button"]:last-child');
                    btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Regenerando...';
                    btnSubmit.disabled = true;

                    // Cerrar modal de confirmación
                    cerrarModalConfirmacion();

                    // Enviar formulario
                    document.getElementById('formCambiarFecha').submit();
                }
            );
        }

        // NUEVA FUNCIÓN: Confirmar restauración
        function confirmarRestauracion() {
            let contenido = `
                <h5 class="mb-3">¿Restaurar fecha original de venta?</h5>
                
                <div class="info-box">
                    <strong><i class="fas fa-undo me-2"></i>Fecha a restaurar:</strong> 
                    <span class="badge bg-secondary">${new Date(DATOS_VENTA.fechaVenta).toLocaleDateString('es-PY')}</span>
                </div>
            `;

            if (DATOS_VENTA.tienePagos) {
                contenido += `
                    <div class="danger-box">
                        <strong><i class="fas fa-exclamation-triangle me-2"></i>¡ADVERTENCIA!</strong><br>
                        Se perderán los pagos registrados por un total de:<br>
                        <strong class="text-danger fs-5">${DATOS_VENTA.simboloMoneda}${new Intl.NumberFormat('es-PY').format(DATOS_VENTA.totalPagos)}</strong>
                    </div>
                `;
            }

            contenido += `
                <div class="diferencia-fechas">
                    <i class="fas fa-info-circle me-2"></i>
                    Las cuotas se calcularán desde la fecha de venta original
                </div>
            `;

            mostrarModalConfirmacion(
                'Restaurar Fecha Original',
                contenido,
                function() {
                    // Ejecutar restauración
                    cerrarModalConfirmacion();
                    document.getElementById('formRestaurarFecha').submit();
                }
            );
        }

        // Función para actualizar vista previa de fechas
        function actualizarPreview() {
            const fechaInicio = document.getElementById('nueva_fecha_inicio').value;
            const previewDiv = document.getElementById('preview-vencimientos');

            if (!fechaInicio) {
                previewDiv.innerHTML = '<small class="text-muted">Selecciona una fecha para ver la vista previa</small>';
                return;
            }

            try {
                const diasCuotas = DATOS_VENTA.tipoCredito.split('/');
                let html = '';

                diasCuotas.forEach((dias, index) => {
                    const fechaVencimiento = new Date(fechaInicio);
                    fechaVencimiento.setDate(fechaVencimiento.getDate() + parseInt(dias));

                    const fechaFormateada = fechaVencimiento.toLocaleDateString('es-PY');
                    const esHoy = fechaVencimiento.toDateString() === new Date().toDateString();
                    const esPasado = fechaVencimiento < new Date();

                    let claseItem = 'cuota-item';
                    let iconoEstado = 'fa-check-circle text-success';

                    if (esPasado && !esHoy) {
                        claseItem += ' vencida';
                        iconoEstado = 'fa-exclamation-circle text-danger';
                    } else if (esHoy) {
                        claseItem += ' hoy';
                        iconoEstado = 'fa-clock text-warning';
                    }

                    html += `
                        <div class="${claseItem}">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>
                                    <span class="badge bg-secondary me-2">${index + 1}</span>
                                    <strong>Cuota ${index + 1}</strong>
                                </span>
                                <span class="text-end">
                                    <i class="fas ${iconoEstado} me-1"></i>
                                    ${fechaFormateada}
                                </span>
                            </div>
                        </div>
                    `;
                });

                // Mostrar diferencia con fecha actual de cálculo
                if (fechaInicio !== DATOS_VENTA.fechaCalculoActual) {
                    const fechaActual = new Date(DATOS_VENTA.fechaCalculoActual);
                    const fechaNueva = new Date(fechaInicio);
                    const diferenciaDias = Math.abs((fechaNueva - fechaActual) / (1000 * 60 * 60 * 24));

                    const direccion = fechaNueva > fechaActual ? 'adelantó' : 'atrasó';
                    html += `
                        <div class="diferencia-fechas mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Se ${direccion} ${Math.round(diferenciaDias)} día(s) respecto a la fecha actual de cálculo
                        </div>
                    `;
                }

                previewDiv.innerHTML = html;
            } catch (error) {
                previewDiv.innerHTML = '<small class="text-danger">Error al calcular preview</small>';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar modal de confirmación
            modalConfirmacion = new bootstrap.Modal(document.getElementById('modalConfirmacion'));

            // Event listeners para vista previa
            const fechaInput = document.getElementById('nueva_fecha_inicio');
            if (fechaInput) {
                fechaInput.addEventListener('change', actualizarPreview);
                fechaInput.addEventListener('input', actualizarPreview);
                actualizarPreview(); // Inicial
            }

            // Animación de las barras de progreso
            const progressBars = document.querySelectorAll('.progress-bar');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.transition = 'width 1s ease-in-out';
                    bar.style.width = width;
                }, 100);
            });

            // Estilo de impresión
            const printStyles = `
                <style>
                    @media print {
                        .btn-group, .navbar, .modal { display: none !important; }
                        .card { break-inside: avoid; }
                        .timeline-item { page-break-inside: avoid; }
                    }
                </style>
            `;
            document.head.insertAdjacentHTML('beforeend', printStyles);

            console.log('Vista de cuotas con fechas dinámicas y modal personalizado inicializada correctamente');
        });
    </script>
</body>

</html>