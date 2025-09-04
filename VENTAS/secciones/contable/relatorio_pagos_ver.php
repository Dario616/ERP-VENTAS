<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '3']);

if (file_exists("controllers/RelatorioController.php")) {
    include "controllers/RelatorioController.php";
} else {
    die("Error: No se pudo cargar el controlador de relatorios.");
}

date_default_timezone_set('America/Asuncion');

try {
    // Inicializar controlador
    $controller = new RelatorioController($conexion, $url_base);

    // Verificar permisos
    if (!$controller->verificarPermisos()) {
        header("Location: " . $url_base . "secciones/contable/main.php?error=No tienes permisos para acceder a esta sección");
        exit();
    }

    // Manejar vista detallada
    $datosVista = $controller->manejarVistaDetallada();
    $mensajes = $controller->manejarMensajes();
    $configJS = $controller->obtenerConfiguracionJS();

    // Log de actividad
    $cliente = $_GET['cliente'] ?? 'Desconocido';
    $controller->logActividad('Vista detallada de cliente', "Cliente: {$cliente}");
} catch (Exception $e) {
    error_log("Error en relatorio_pagos_ver.php: " . $e->getMessage());
    header("Location: " . $url_base . "secciones/contable/relatorio_pagos.php?error=Error interno del servidor");
    exit;
}

$cliente_data = $datosVista['cliente'];
$info_general = $cliente_data['info_general'];
$ventas = $cliente_data['ventas'];
$cuotas_y_pagos = $cliente_data['cuotas_y_pagos'];
$estadisticas = $cliente_data['estadisticas'];
$historial_pagos = $cliente_data['historial_pagos'];
$resumen_por_moneda = $cliente_data['resumen_por_moneda'];
$breadcrumb_items = ['Sector Contable', 'Reporte de Pagos', 'Vista Detallada'];
$item_urls = [
    $url_base . 'secciones/contable/main.php',
    $url_base . 'secciones/contable/relatorio_pagos.php',
];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($datosVista['datos_vista']['titulo']); ?></title>
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
        <?php if (!empty($mensajes['mensaje'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $mensajes['mensaje']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensajes['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $mensajes['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Header con información del cliente -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-user-circle me-2"></i>
                            <?php echo htmlspecialchars($info_general['cliente'] ?? 'Cliente no encontrado'); ?>
                        </h4>
                        <small>
                            Vendedor: <?php echo htmlspecialchars($info_general['vendedor_principal'] ?? 'No asignado'); ?>
                        </small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="info-item">
                                    <span class="info-label">Total Ventas:</span>
                                    <span class="text-info fw-bold">
                                        <?php echo $info_general['total_ventas_cantidad'] ?? 0; ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Primera Venta:</span>
                                    <span><?php echo $info_general['primera_venta_formateada'] ?? 'N/A'; ?></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-item">
                                    <span class="info-label">Monto Total Vendido:</span>
                                    <span class="text-success fw-bold">
                                        <?php echo $info_general['total_ventas_formateado'] ?? '0,00'; ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Última Venta:</span>
                                    <span><?php echo $info_general['ultima_venta_formateada'] ?? 'N/A'; ?></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-item">
                                    <span class="info-label">Total Pagado:</span>
                                    <span class="text-primary fw-bold">
                                        <?php echo $info_general['total_pagado_formateado'] ?? '0,00'; ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Último Pago:</span>
                                    <span><?php echo $info_general['ultimo_pago_formateado'] ?? 'N/A'; ?></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-item">
                                    <span class="info-label">Saldo Pendiente:</span>
                                    <span class="text-warning fw-bold">
                                        <?php echo $info_general['total_pendiente_formateado'] ?? '0,00'; ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Monedas:</span>
                                    <span><?php echo $info_general['monedas_utilizadas'] ?? 'Guaraníes'; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen por Monedas -->
        <?php if (!empty($resumen_por_moneda)): ?>
            <div class="row mb-4">
                <?php foreach ($resumen_por_moneda as $moneda): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card card-stat primary">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="fas fa-coins me-2"></i>
                                    <?php echo htmlspecialchars($moneda['moneda']); ?>
                                </h6>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">Vendido</small>
                                        <div class="fw-bold text-success">
                                            <?php echo $moneda['simbolo'] . $moneda['total_vendido_formateado']; ?>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Pagado</small>
                                        <div class="fw-bold text-primary">
                                            <?php echo $moneda['simbolo'] . $moneda['total_pagado_formateado']; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <div class="progress progress-custom">
                                        <div class="progress-bar bg-success"
                                            style="width: <?php echo $moneda['porcentaje_pagado']; ?>%">
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $moneda['porcentaje_pagado']; ?>% pagado
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Tabs de contenido -->
        <div class="row">
            <div class="col-12">
                <nav>
                    <div class="nav nav-tabs" id="nav-tab" role="tablist">
                        <button class="nav-link active" id="nav-ventas-tab" data-bs-toggle="tab"
                            data-bs-target="#nav-ventas" type="button" role="tab">
                            <i class="fas fa-shopping-cart me-2"></i>Ventas
                        </button>
                        <button class="nav-link" id="nav-cuotas-tab" data-bs-toggle="tab"
                            data-bs-target="#nav-cuotas" type="button" role="tab">
                            <i class="fas fa-calendar-check me-2"></i>Cuotas
                        </button>
                        <button class="nav-link" id="nav-pagos-tab" data-bs-toggle="tab"
                            data-bs-target="#nav-pagos" type="button" role="tab">
                            <i class="fas fa-money-check me-2"></i>Historial de Pagos
                        </button>
                    </div>
                </nav>

                <div class="tab-content" id="nav-tabContent">
                    <!-- Tab Ventas -->
                    <div class="tab-pane fade show active" id="nav-ventas" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="section-header">
                                    <i class="fas fa-shopping-cart me-2"></i>Ventas Realizadas
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($ventas)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="tabla-ventas">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Codigo Venta</th>
                                                    <th>Fecha</th>
                                                    <th>Monto Total</th>
                                                    <th>Moneda</th>
                                                    <th>Estado</th>
                                                    <th>Pagado</th>
                                                    <th>Pendiente</th>
                                                    <th>Progreso</th>
                                                    <th>Cuotas</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($ventas as $venta): ?>
                                                    <tr>
                                                        <td>
                                                            <strong>#<?php echo $venta['id']; ?></strong>
                                                            <?php if ($venta['descripcion']): ?>
                                                                <br><small class="text-muted">
                                                                    <?php echo htmlspecialchars(substr($venta['descripcion'], 0, 50)); ?>...
                                                                </small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo $venta['fecha_venta_formateada']; ?></td>
                                                        <td>
                                                            <span class="fw-bold">
                                                                <?php echo $controller->obtenerSimboloMoneda($venta['moneda']) . $venta['monto_total_formateado']; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $venta['moneda'] ?: 'Guaraníes'; ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $venta['clase_css']; ?>">
                                                                <?php echo ucfirst($venta['estado_visual']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php echo $controller->obtenerSimboloMoneda($venta['moneda']) . $venta['monto_pagado_formateado']; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo $controller->obtenerSimboloMoneda($venta['moneda']) . $venta['monto_pendiente_formateado']; ?>
                                                        </td>
                                                        <td>
                                                            <div class="progress progress-custom">
                                                                <div class="progress-bar bg-<?php echo $venta['clase_css']; ?>"
                                                                    style="width: <?php echo $venta['porcentaje_pagado']; ?>%">
                                                                </div>
                                                            </div>
                                                            <small><?php echo $venta['porcentaje_pagado']; ?>%</small>
                                                        </td>
                                                        <td>
                                                            <small>
                                                                Total: <?php echo $venta['total_cuotas']; ?><br>
                                                                <span class="text-success">Pagadas: <?php echo $venta['cuotas_pagadas']; ?></span><br>
                                                                <span class="text-warning">Pendientes: <?php echo $venta['cuotas_pendientes']; ?></span>
                                                                <?php if ($venta['cuotas_vencidas'] > 0): ?>
                                                                    <br><span class="text-danger">Vencidas: <?php echo $venta['cuotas_vencidas']; ?></span>
                                                                <?php endif; ?>
                                                            </small>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No se encontraron ventas para este cliente.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Cuotas -->
                    <div class="tab-pane fade" id="nav-cuotas" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="section-header">
                                    <i class="fas fa-calendar-check me-2"></i>Cuotas y Pagos
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($cuotas_y_pagos)): ?>
                                    <?php foreach ($cuotas_y_pagos as $cuota): ?>
                                        <div class="card mb-3 border-start border-3 border-<?php
                                                                                            echo $cuota['estado_vencimiento'] == 'vencido' ? 'danger' : ($cuota['estado_vencimiento'] == 'pagado' ? 'success' : 'warning');
                                                                                            ?>">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <h6>
                                                            Cuota #<?php echo $cuota['numero_cuota']; ?>
                                                            - Codigo Venta #<?php echo $cuota['id_venta']; ?>
                                                        </h6>
                                                        <p class="mb-1">
                                                            <strong>Vencimiento:</strong>
                                                            <?php echo $cuota['fecha_vencimiento_formateada']; ?>
                                                            <?php if (isset($cuota['dias_vencido']) && $cuota['dias_vencido'] > 0): ?>
                                                                <span class="badge badge-vencido ms-2">
                                                                    <?php echo $cuota['dias_vencido']; ?> días vencido
                                                                </span>
                                                            <?php elseif (isset($cuota['dias_para_vencimiento']) && $cuota['dias_para_vencimiento'] > 0): ?>
                                                                <span class="badge badge-por-vencer ms-2">
                                                                    <?php echo $cuota['dias_para_vencimiento']; ?> días restantes
                                                                </span>
                                                            <?php endif; ?>
                                                        </p>
                                                        <p class="mb-1">
                                                            <strong>Estado:</strong>
                                                            <span class="badge bg-<?php
                                                                                    echo $cuota['estado'] == 'PAGADO' ? 'success' : 'warning';
                                                                                    ?>">
                                                                <?php echo $cuota['estado']; ?>
                                                            </span>
                                                        </p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p class="mb-1">
                                                            <strong>Monto Cuota:</strong>
                                                            <?php echo $controller->obtenerSimboloMoneda($cuota['moneda']) . $cuota['monto_cuota_formateado']; ?>
                                                        </p>
                                                        <p class="mb-1">
                                                            <strong>Pagado:</strong>
                                                            <?php echo $controller->obtenerSimboloMoneda($cuota['moneda']) . $cuota['monto_pagado_formateado']; ?>
                                                        </p>
                                                        <p class="mb-1">
                                                            <strong>Pendiente:</strong>
                                                            <?php echo $controller->obtenerSimboloMoneda($cuota['moneda']) . $cuota['monto_pendiente_formateado']; ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <!-- Pagos de la cuota -->
                                                <?php if (!empty($cuota['pagos'])): ?>
                                                    <hr>
                                                    <h6><i class="fas fa-receipt me-2"></i>Pagos Realizados</h6>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm">
                                                            <thead>
                                                                <tr>
                                                                    <th>Fecha Pago</th>
                                                                    <th>Monto</th>
                                                                    <th>Forma de Pago</th>
                                                                    <th>Referencia</th>
                                                                    <th>Usuario</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($cuota['pagos'] as $pago): ?>
                                                                    <tr>
                                                                        <td><?php echo $pago['fecha_pago_formateada']; ?></td>
                                                                        <td>
                                                                            <strong>
                                                                                <?php echo $controller->obtenerSimboloMoneda($cuota['moneda']) . $pago['monto_pago_formateado']; ?>
                                                                            </strong>
                                                                        </td>
                                                                        <td><?php echo $pago['forma_pago']; ?></td>
                                                                        <td><?php echo $pago['referencia_pago'] ?: '-'; ?></td>
                                                                        <td><?php echo $pago['usuario_registro']; ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No se encontraron cuotas para este cliente.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Historial de Pagos -->
                    <div class="tab-pane fade" id="nav-pagos" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="section-header">
                                    <i class="fas fa-money-check me-2"></i>Historial Completo de Pagos
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($historial_pagos)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="tabla-historial">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Codigo Venta</th>
                                                    <th>Cuota</th>
                                                    <th>Monto</th>
                                                    <th>Forma de Pago</th>
                                                    <th>Referencia</th>
                                                    <th>Puntualidad</th>
                                                    <th>Usuario</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($historial_pagos as $pago): ?>
                                                    <tr>
                                                        <td><?php echo $pago['fecha_pago_formateada']; ?></td>
                                                        <td>
                                                            <strong>#<?php echo $pago['id_venta']; ?></strong>
                                                        </td>
                                                        <td>
                                                            Cuota #<?php echo $pago['numero_cuota']; ?>
                                                            <br><small class="text-muted">
                                                                Vto: <?php echo date('d/m/Y', strtotime($pago['fecha_vencimiento'])); ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <strong>
                                                                <?php echo $pago['simbolo_moneda'] . $pago['monto_pago_formateado']; ?>
                                                            </strong>
                                                        </td>
                                                        <td><?php echo $pago['forma_pago']; ?></td>
                                                        <td><?php echo $pago['referencia_pago'] ?: '-'; ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $pago['puntualidad'] == 'A tiempo' ? 'success' : 'warning'; ?>">
                                                                <?php echo $pago['puntualidad']; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $pago['usuario_registro']; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-money-check fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No se encontraron pagos para este cliente.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $url_base; ?>config/notificacion/sistema-notificaciones.js"></script>

    <script>
        // Configuración JavaScript del cliente
        const CONFIG = <?php echo json_encode($configJS); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            console.log('Vista detallada del cliente inicializada correctamente');
        });
    </script>
</body>

</html>