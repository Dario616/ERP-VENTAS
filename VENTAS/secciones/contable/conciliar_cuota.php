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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_pago'])) {
    $datos = $controller->procesarDatosFormularioPago();
    $resultado = $controller->procesarRegistroPago($idCuota, $datos);

    if (isset($resultado['error'])) {
        $error = $resultado['error'];
    }
}

try {
    $datosCuota = $controller->obtenerDetalleCuota($idCuota);
    $cuota = $datosCuota['cuota'];
    $pagos = $datosCuota['pagos'];
    $otrasCuotas = $datosCuota['otras_cuotas'];
    $totalPendienteVenta = array_sum(array_column($otrasCuotas, 'monto_pendiente'));
} catch (Exception $e) {
    header("Location: " . $url_base . "secciones/contable/cuentas_cobrar.php?error=" . urlencode($e->getMessage()));
    exit();
}

$datosVista = $controller->obtenerDatosVista('Conciliar Cuota - Venta #' . $cuota['id_venta']);
$configuracionJS = $controller->obtenerConfiguracionJS();
$controller->logActividad('Ver conciliación cuota', 'ID: ' . $idCuota);
$breadcrumb_items = ['Sector Contable', 'Cuentas por Cobrar', 'Detalles de Cuota', 'Conciliar Cuota'];
$item_urls = [
    $url_base . 'secciones/contable/main.php',
    $url_base . 'secciones/contable/cuentas_cobrar.php',
    $url_base . 'secciones/contable/ver_cuota.php?id=' . $idCuota,
];
$additional_css = [$url_base . 'secciones/contable/utils/styles.css'];
include $path_base . "components/head.php";
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Información de la Cuota
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <th width="40%">Venta:</th>
                                        <td><strong>#<?php echo $cuota['id_venta']; ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th>Cliente:</th>
                                        <td><?php echo htmlspecialchars($cuota['cliente']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Vendedor:</th>
                                        <td><?php echo htmlspecialchars($cuota['vendedor']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Fecha de Venta:</th>
                                        <td><?php echo $cuota['fecha_venta_formateada']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Tipo de Crédito:</th>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($cuota['tipocredito']); ?></span></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <th width="40%">Número de Cuota:</th>
                                        <td><span class="badge bg-primary"><?php echo $cuota['numero_cuota']; ?></span></td>
                                    </tr>
                                    <tr>
                                        <th>Fecha de Vencimiento:</th>
                                        <td>
                                            <?php echo $cuota['fecha_vencimiento_formateada']; ?>
                                            <?php if (isset($cuota['dias_texto'])): ?>
                                                <br><small class="<?php echo $cuota['dias_class']; ?>"><?php echo $cuota['dias_texto']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Estado:</th>
                                        <td>
                                            <span class="badge <?php echo $cuota['estado_class']; ?>">
                                                <?php echo ucfirst(strtolower($cuota['estado_actual'] ?? $cuota['estado'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Total de la Venta:</th>
                                        <td><strong><?php echo $controller->formatearMoneda($cuota['total_venta'], $cuota['moneda']); ?></strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-dollar-sign me-2"></i>Resumen de Pagos
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <th>Monto de la Cuota:</th>
                                <td><strong><?php echo $controller->formatearMoneda($cuota['monto_cuota'], $cuota['moneda']); ?></strong></td>
                            </tr>
                            <tr class="table-success">
                                <th>Total Pagado:</th>
                                <td><strong class="text-success"><?php echo $controller->formatearMoneda($cuota['monto_pagado'], $cuota['moneda']); ?></strong></td>
                            </tr>
                            <tr class="table-warning">
                                <th>Saldo Pendiente:</th>
                                <td><strong class="text-danger"><?php echo $controller->formatearMoneda($cuota['monto_pendiente'], $cuota['moneda']); ?></strong></td>
                            </tr>
                        </table>

                        <?php if ($cuota['fecha_ultimo_pago']): ?>
                            <hr>
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                Último pago: <?php echo $cuota['fecha_ultimo_pago_formateada']; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (count($otrasCuotas) > 1): ?>
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Todas las Cuotas de esta Venta
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Cuota</th>
                                    <th>Vencimiento</th>
                                    <th>Monto</th>
                                    <th>Pagado</th>
                                    <th>Pendiente</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($otrasCuotas as $otraCuota): ?>
                                    <tr class="<?php echo $otraCuota['id'] == $cuota['id'] ? 'table-primary' : ''; ?>">
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $otraCuota['numero_cuota']; ?></span>
                                            <?php if ($otraCuota['id'] == $cuota['id']): ?>
                                                <small class="text-primary">(actual)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $otraCuota['fecha_vencimiento_formateada']; ?></td>
                                        <td><?php echo $controller->formatearMoneda($otraCuota['monto_cuota'], $otraCuota['moneda']); ?></td>
                                        <td><?php echo $controller->formatearMoneda($otraCuota['monto_pagado'], $otraCuota['moneda']); ?></td>
                                        <td><?php echo $controller->formatearMoneda($otraCuota['monto_pendiente'], $otraCuota['moneda']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $otraCuota['estado_class']; ?>">
                                                <?php echo ucfirst(strtolower($otraCuota['estado'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($otraCuota['id'] != $cuota['id']): ?>
                                                <a href="conciliar_cuota.php?id=<?php echo $otraCuota['id']; ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-plus-circle me-2"></i>Registrar Nuevo Pago
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($cuota['monto_pendiente'] > 0): ?>
                            <form method="POST" enctype="multipart/form-data" id="form-pago">
                                <input type="hidden" name="registrar_pago" value="1">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="monto_pago" class="form-label">
                                            Monto del Pago <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><?php echo $cuota['simbolo_moneda']; ?></span>
                                            <input type="number"
                                                class="form-control"
                                                id="monto_pago"
                                                name="monto_pago"
                                                step="0.01"
                                                min="0.01"
                                                max="<?php echo $cuota['monto_pendiente']; ?>"
                                                value="<?php echo $cuota['monto_pendiente']; ?>"
                                                required>
                                        </div>
                                        <div class="form-text">
                                            Saldo pendiente: <?php echo $controller->formatearMoneda($cuota['monto_pendiente'], $cuota['moneda']); ?>
                                        </div>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" id="completar_cuota" name="completar_cuota" value="true" checked>
                                            <label class="form-check-label" for="completar_cuota">
                                                <strong>Completar esta cuota y redistribuir saldo</strong>
                                                <br><small class="text-muted">Si está marcado, la cuota se marca como PAGADA y el saldo se redistribuye a las demás cuotas</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="fecha_pago" class="form-label">
                                            Fecha del Pago <span class="text-danger">*</span>
                                        </label>
                                        <input type="date"
                                            class="form-control"
                                            id="fecha_pago"
                                            name="fecha_pago"
                                            value="<?php echo date('Y-m-d'); ?>"
                                            max="<?php echo date('Y-m-d'); ?>"
                                            required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="forma_pago" class="form-label">Forma de Pago</label>
                                        <select class="form-select" id="forma_pago" name="forma_pago">
                                            <option value="">Seleccionar...</option>
                                            <option value="Efectivo">Efectivo</option>
                                            <option value="Transferencia">Transferencia</option>
                                            <option value="Cheque">Cheque</option>
                                            <option value="Deposito">Boleto Bancario</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="referencia_pago" class="form-label">Referencia/Comprobante N°</label>
                                        <input type="text"
                                            class="form-control"
                                            id="referencia_pago"
                                            name="referencia_pago"
                                            placeholder="Número de transferencia, cheque, etc.">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="comprobante" class="form-label">Adjuntar Comprobante</label>
                                    <input type="file"
                                        class="form-control"
                                        id="comprobante"
                                        name="comprobante"
                                        accept="image/*,.pdf">
                                    <div class="form-text">
                                        Formatos permitidos: JPG, PNG, PDF. Tamaño máximo: 5MB
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="observaciones" class="form-label">Observaciones</label>
                                    <textarea class="form-control"
                                        id="observaciones"
                                        name="observaciones"
                                        rows="3"
                                        placeholder="Observaciones adicionales sobre el pago..."></textarea>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-save me-2"></i>Registrar Pago
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-success text-center">
                                <i class="fas fa-check-circle fa-3x mb-3"></i>
                                <h5>¡Cuota Totalmente Pagada!</h5>
                                <p class="mb-0">Esta cuota ya se encuentra completamente cancelada.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Historial de Pagos
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pagos)): ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>
                                No se han registrado pagos para esta cuota
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($pagos as $pago): ?>
                                    <div class="card mb-3 border-left-success">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="text-success mb-1">
                                                        <i class="fas fa-check-circle me-1"></i>
                                                        <?php echo $controller->formatearMoneda($pago['monto_pago'], $cuota['moneda']); ?>
                                                    </h6>
                                                    <p class="mb-1">
                                                        <strong>Fecha:</strong> <?php echo $pago['fecha_pago_formateada']; ?>
                                                    </p>
                                                    <?php if ($pago['forma_pago']): ?>
                                                        <p class="mb-1">
                                                            <strong>Forma:</strong> <?php echo htmlspecialchars($pago['forma_pago']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if ($pago['referencia_pago']): ?>
                                                        <p class="mb-1">
                                                            <strong>Referencia:</strong> <?php echo htmlspecialchars($pago['referencia_pago']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if ($pago['observaciones']): ?>
                                                        <p class="mb-1">
                                                            <strong>Observaciones:</strong> <?php echo htmlspecialchars($pago['observaciones']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($pago['comprobante_base64']): ?>
                                                    <div>
                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-primary"
                                                            onclick="verComprobante('<?php echo $pago['comprobante_tipo']; ?>', '<?php echo $pago['comprobante_base64']; ?>', '<?php echo htmlspecialchars($pago['comprobante_nombre']); ?>')">
                                                            <i class="fas fa-file-alt"></i>
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>
                                                Registrado por: <?php echo htmlspecialchars($pago['usuario_registro']); ?>
                                                el <?php echo $pago['fecha_registro_formateada']; ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
        document.addEventListener('DOMContentLoaded', function() {
            const formPago = document.getElementById('form-pago');
            if (formPago) {
                const totalPendienteVenta = <?php echo isset($totalPendienteVenta) && is_numeric($totalPendienteVenta) ? $totalPendienteVenta : 0; ?>;
                const esUltimaCuota = document.getElementById('es_ultima_cuota')?.value === 'true';
                const montoPendienteCuota = <?php echo $cuota['monto_pendiente']; ?>;
                if (esUltimaCuota) {
                    const montoInput = document.getElementById('monto_pago');
                    montoInput.readOnly = true;
                    montoInput.style.backgroundColor = '#f8f9fa';
                    montoInput.title = 'Monto fijo - Última cuota debe pagarse completa';
                }

                formPago.addEventListener('submit', function(e) {
                    const montoPago = parseFloat(document.getElementById('monto_pago').value);

                    if (montoPago <= 0) {
                        e.preventDefault();
                        alert('El monto debe ser mayor a 0');
                        return false;
                    }
                    if (esUltimaCuota) {
                        if (Math.abs(montoPago - montoPendienteCuota) > 0.01) {
                            e.preventDefault();
                            alert(`¡Última cuota!\n\nDebe pagar exactamente: ${new Intl.NumberFormat('es-PY').format(montoPendienteCuota)}\nMonto ingresado: ${new Intl.NumberFormat('es-PY').format(montoPago)}`);
                            return false;
                        }

                        if (!confirm(`¿Confirmar pago final de ${new Intl.NumberFormat('es-PY').format(montoPago)}?\n\nEsto cerrará completamente la venta.`)) {
                            e.preventDefault();
                            return false;
                        }
                        return true;
                    }
                    const completarCuota = document.getElementById('completar_cuota')?.checked;

                    if (!completarCuota) {
                        if (montoPago > montoPendienteCuota) {
                            e.preventDefault();
                            alert('El monto del pago no puede ser mayor al saldo de esta cuota (redistribución deshabilitada)');
                            return false;
                        }
                    } else {
                        if (montoPago > totalPendienteVenta) {
                            e.preventDefault();
                            alert(`El monto no puede ser mayor al total pendiente de la venta: ${new Intl.NumberFormat('es-PY').format(totalPendienteVenta)}`);
                            return false;
                        }
                        if (montoPago > montoPendienteCuota) {
                            const exceso = montoPago - montoPendienteCuota;
                            if (!confirm(`¿Confirmas el pago de ${new Intl.NumberFormat('es-PY').format(montoPago)}?\n\nSe aplicará:\n• ${new Intl.NumberFormat('es-PY').format(montoPendienteCuota)} a esta cuota\n• ${new Intl.NumberFormat('es-PY').format(exceso)} a las siguientes cuotas`)) {
                                e.preventDefault();
                                return false;
                            }
                        }
                    }
                });
            }
            console.log('Página de conciliación de cuota inicializada correctamente');
        });
    </script>
</body>

</html>