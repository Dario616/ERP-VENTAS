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

// Manejar mensajes de la URL (solo para mostrar)
$mensaje = '';
$error = '';
if (isset($_GET['mensaje'])) {
    $mensaje = htmlspecialchars($_GET['mensaje']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

$datosVista = $controller->obtenerDatosVista('Detalles de Cuota - Venta #' . $cuota['id_venta']);
$configuracionJS = $controller->obtenerConfiguracionJS();
$controller->logActividad('Ver detalles cuota', 'ID: ' . $idCuota);
$breadcrumb_items = ['Sector Contable', 'Cuentas Pagadas', 'Detalles de Cuota'];
$item_urls = [
    $url_base . 'secciones/contable/main.php',
    $url_base . 'secciones/contable/pagados.php',
];
$additional_css = [$url_base . 'secciones/contable/utils/styles.css'];
include $path_base . "components/head.php";
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <!-- Mensajes informativos -->
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
                                    Venta #<?php echo $cuota['id_venta']; ?>
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

        <!-- Información de fechas de cálculo (solo informativa) -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card card-readonly">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Información de Fechas de Cálculo
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <small class="text-muted">Fecha de Venta Original:</small>
                                <p class="mb-1"><strong><?php echo $cuota['fecha_venta_formateada']; ?></strong></p>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Fecha de Cálculo Actual:</small>
                                <?php if (isset($cuota['tiene_fecha_personalizada']) && $cuota['tiene_fecha_personalizada']): ?>
                                    <p class="mb-1">
                                        <strong class="text-primary"><?php echo $cuota['fecha_calculo_efectiva_formateada']; ?></strong>
                                        <span class="info-badge ms-2">Personalizada</span>
                                    </p>
                                <?php else: ?>
                                    <p class="mb-1">
                                        <strong><?php echo $cuota['fecha_venta_formateada']; ?></strong>
                                        <span class="info-badge ms-2">Original</span>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Tipo de Crédito:</small>
                                <p class="mb-1"><span class="badge bg-info"><?php echo $cuota['tipocredito']; ?></span></p>
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

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="<?php echo $url_base; ?>config/notificacion/sistema-notificaciones.js"></script>

        <script>
            const CUENTAS_COBRAR_CONFIG = <?php echo json_encode($configuracionJS); ?>;

            // Función para ver comprobantes (solo visualización)
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
                        No se puede previsualizar este tipo de archivo: ${nombre}
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

            document.addEventListener('DOMContentLoaded', function() {
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
                        .navbar, .modal, .btn { display: none !important; }
                        .card { break-inside: avoid; }
                        .timeline-item { page-break-inside: avoid; }
                        .alert-info { background: #f8f9fa !important; color: #333 !important; }
                        body { font-size: 12px; }
                        .card-header { background: #f8f9fa !important; color: #333 !important; }
                    }
                </style>
            `;
                document.head.insertAdjacentHTML('beforeend', printStyles);

                // Mensaje informativo en consola
                console.log('Vista de cuotas en modo solo lectura inicializada correctamente');
                console.log('Esta vista no permite modificaciones - Solo visualización');
            });
        </script>
</body>

</html>