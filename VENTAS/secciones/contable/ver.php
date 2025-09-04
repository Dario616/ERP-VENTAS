<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '3']);

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: " . $url_base . "secciones/contable/index.php?error=ID no proporcionado");
    exit();
}

$id = $_GET['id'];

if (file_exists("controllers/ContableController.php")) {
    include "controllers/ContableController.php";
} else {
    die("Error: No se pudo cargar el controlador contable.");
}

$controller = new ContableController($conexion, $url_base);

if (!$controller->verificarPermisos()) {
    header("Location: " . $url_base . "secciones/contable/index.php?error=No tienes permisos para acceder a esta sección");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $datos = $controller->procesarDatosFormulario();

    $resultado = $controller->procesarAccionAutorizacion($id, $accion, $datos);

    if (isset($resultado['error'])) {
        $error = $resultado['error'];
    }
}

try {
    $datosVenta = $controller->obtenerVentaParaRevision($id);
    $venta = $datosVenta['venta'];
    $productos = $datosVenta['productos'];
    $imagenesAutorizacion = $datosVenta['imagenes_autorizacion'];
    $imagenesProductos = $datosVenta['imagenes_productos'];
    $fueRechazada = $datosVenta['fue_rechazada'];
} catch (Exception $e) {
    header("Location: " . $url_base . "secciones/contable/index.php?error=" . urlencode($e->getMessage()));
    exit();
}

$datosVista = $controller->obtenerDatosVista('Detalle de Autorización - Venta #' . $id);
$configuracionJS = $controller->obtenerConfiguracionJS();
$controller->logActividad('Ver detalle autorización', 'ID: ' . $id);
$breadcrumb_items = ['Sector Contable', 'Autorizaciones Pendientes', 'Ver Detalles'];
$item_urls = [
    $url_base . 'secciones/contable/main.php',
    $url_base . 'secciones/contable/index.php'
];
$additional_css = [$url_base . 'secciones/contable/utils/styles.css'];
include $path_base . "components/head.php";
?>
<!DOCTYPE html>
<html lang="es">
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <?php if ($datosVenta['fue_rechazada']): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Atención:</strong> Esta venta fue rechazada anteriormente y ha sido editada y reenviada para su revisión.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="container-fluid my-4">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información de la Venta</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Codigo Venta:</th>
                                    <td><strong>#<?php echo $venta['id']; ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Cliente:</th>
                                    <td><?php echo htmlspecialchars($venta['cliente']); ?></td>
                                </tr>
                                <tr>
                                    <th>Vendedor:</th>
                                    <td><?php echo htmlspecialchars($venta['nombre_vendedor']); ?></td>
                                </tr>
                                <tr>
                                    <th>Fecha de Venta:</th>
                                    <td><?php echo $venta['fecha_venta_formateada']; ?></td>
                                </tr>
                                <tr>
                                    <th>Condición de Pago:</th>
                                    <td>
                                        <?php if ($venta['es_credito']): ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-credit-card me-1"></i>Crédito
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-money-bill me-1"></i>Contado
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Tipo de Flete:</th>
                                    <td><?php echo htmlspecialchars($venta['tipoflete']); ?></td>
                                </tr>
                                <tr>
                                    <th>Empresa Fletera:</th>
                                    <td><?php echo (isset($venta['transportadora']) && $venta['transportadora'] !== '') ? htmlspecialchars($venta['transportadora']) : "no asignada"; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-dollar-sign me-2"></i>Resumen Financiero</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Moneda:</th>
                                    <td><strong><?php echo htmlspecialchars($venta['moneda']); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Subtotal:</th>
                                    <td>
                                        <?php echo $controller->formatearMoneda($venta['subtotal'], $venta['moneda']); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Total con IVA:</th>
                                    <td class="fs-5 fw-bold text-success">
                                        <?php echo $controller->formatearMoneda($venta['monto_total'], $venta['moneda']); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Número de Productos:</th>
                                    <td><?php echo $venta['num_productos']; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Detalle de Productos</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>NCM</th>
                                    <th>Producto</th>
                                    <th>Tipo de Producto</th>
                                    <th class="text-end">Cantidad</th>
                                    <th class="text-center">UM</th>
                                    <th class="text-end">Precio Unit.</th>
                                    <th class="text-end">Subtotal</th>
                                    <th class="text-center">Imagen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos as $producto): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($producto['ncm'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($producto['descripcion']); ?></td>
                                        <td><?php echo htmlspecialchars($producto['tipoproducto']); ?></td>
                                        <td class="text-end"><?php echo number_format((float)$producto['cantidad'], 2, ',', '.'); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($producto['unidadmedida'] ?: 'N/A'); ?></td>
                                        <td class="text-end">
                                            <?php echo $controller->formatearMoneda($producto['precio'], $venta['moneda']); ?>
                                        </td>
                                        <td class="text-end fw-bold">
                                            <?php
                                            $subtotal = $producto['cantidad'] * $producto['precio'];
                                            echo $controller->formatearMoneda($subtotal, $venta['moneda']);
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if (isset($imagenesProductos[$producto['id']])): ?>
                                                <img src="data:<?php echo $imagenesProductos[$producto['id']]['tipo']; ?>;base64,<?php echo $imagenesProductos[$producto['id']]['imagen']; ?>"
                                                    class="img-thumbnail ver-imagen-producto"
                                                    style="width: 50px; height: 50px; object-fit: cover; cursor: pointer;"
                                                    data-id-producto="<?php echo $producto['id']; ?>"
                                                    title="Click para ver a tamaño completo">
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-image-slash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-secondary">
                                    <th colspan="7" class="text-end">Total sin IVA:</th>
                                    <th class="text-end"><?php echo $controller->formatearMoneda($venta['subtotal'], $venta['moneda']); ?></th>
                                </tr>
                                <tr class="table-primary">
                                    <th colspan="7" class="text-end">Total con IVA:</th>
                                    <th class="text-end fs-5"><?php echo $controller->formatearMoneda($venta['monto_total'], $venta['moneda']); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($venta['es_credito'] && $venta['tipocredito']): ?>
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Información de Crédito</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Tipo de Crédito:</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($venta['tipocredito']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($venta['descripcion_autorizacion'] || !empty($imagenesAutorizacion)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Solicitud de Autorización del Vendedor</h5>
                        <?php if ($venta['fecha_solicitud_formateada']): ?>
                            <small class="d-block mt-1">
                                <i class="fas fa-clock me-1"></i>
                                Enviado el: <?php echo $venta['fecha_solicitud_formateada']; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if ($venta['descripcion_autorizacion']): ?>
                                <div class="col-md-<?php echo !empty($imagenesAutorizacion) ? '8' : '12'; ?>">
                                    <h6 class="text-primary"><i class="fas fa-comment me-2"></i>Descripción/Justificación:</h6>
                                    <div class="alert alert-light border">
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($venta['descripcion_autorizacion'])); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($imagenesAutorizacion)): ?>
                                <div class="col-md-<?php echo $venta['descripcion_autorizacion'] ? '4' : '12'; ?>">
                                    <h6 class="text-primary">
                                        <i class="fas fa-images me-2"></i>
                                        Archivos Adjuntos (<?php echo count($imagenesAutorizacion); ?>)
                                    </h6>
                                    <p class="small text-muted mb-2">Click sobre las imágenes o PDFs para verlos en tamaño completo</p>
                                    <div class="galeria-imagenes">
                                        <?php foreach ($imagenesAutorizacion as $index => $imagen): ?>
                                            <div class="text-center">
                                                <?php if (strpos($imagen['tipo_archivo'], 'image') !== false): ?>
                                                    <img src="data:<?php echo $imagen['tipo_archivo']; ?>;base64,<?php echo $imagen['base64_imagen']; ?>"
                                                        class="img-thumbnail imagen-autorizacion-thumb"
                                                        style="width: 100px; height: 100px; object-fit: cover;"
                                                        data-imagen-index="<?php echo $index; ?>"
                                                        data-tipo-archivo="imagen"
                                                        title="<?php echo htmlspecialchars($imagen['nombre_archivo'] ?: 'Imagen ' . ($index + 1)); ?>">
                                                <?php else: ?>
                                                    <div class="pdf-thumbnail imagen-autorizacion-thumb d-flex align-items-center justify-content-center"
                                                        style="width: 100px; height: 100px; background: linear-gradient(135deg, #ff6b6b, #ee5a24); color: white; border-radius: 8px; cursor: pointer;"
                                                        data-imagen-index="<?php echo $index; ?>"
                                                        data-tipo-archivo="pdf"
                                                        title="Click para ver el PDF: <?php echo htmlspecialchars($imagen['nombre_archivo']); ?>">
                                                        <div class="text-center">
                                                            <i class="fas fa-file-pdf fa-2x mb-1"></i>
                                                            <div style="font-size: 10px; font-weight: bold;">PDF</div>
                                                            <div style="font-size: 8px;">Click para ver</div>
                                                        </div>
                                                    </div>
                                                    <a href="data:<?php echo $imagen['tipo_archivo']; ?>;base64,<?php echo $imagen['base64_imagen']; ?>"
                                                        download="<?php echo htmlspecialchars($imagen['nombre_archivo']); ?>"
                                                        class="btn btn-sm btn-outline-primary mt-1"
                                                        style="font-size: 10px; padding: 2px 6px;"
                                                        title="Descargar PDF">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($imagen['nombre_archivo']): ?>
                                                    <p class="text-muted small mt-1 mb-0" style="max-width: 100px; word-wrap: break-word; font-size: 10px;">
                                                        <i class="fas fa-file me-1"></i><?php echo htmlspecialchars($imagen['nombre_archivo']); ?>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if ($imagen['descripcion_imagen']): ?>
                                                    <p class="text-muted small mt-1 mb-0" style="max-width: 100px; word-wrap: break-word; font-size: 10px;">
                                                        <?php echo htmlspecialchars($imagen['descripcion_imagen']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="modal fade" id="modalImagenAutorizacion" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title">
                                <i class="fas fa-file me-2"></i><span id="titulo-imagen-modal">Documento de Autorización</span>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center bg-light p-3 position-relative">
                            <div class="imagen-counter" id="imagen-counter" style="display: none;">
                                <span id="imagen-actual">1</span> / <span id="total-imagenes">1</span>
                            </div>
                            <button type="button" class="btn imagen-modal-navegacion btn-prev" id="btn-imagen-anterior" style="display: none;">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button type="button" class="btn imagen-modal-navegacion btn-next" id="btn-imagen-siguiente" style="display: none;">
                                <i class="fas fa-chevron-right"></i>
                            </button>

                            <h6 id="nombre-imagen-modal" class="mb-3 fw-bold text-primary"></h6>
                            <div id="imagen-autorizacion-container">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>No hay documento de autorización disponible
                                </div>
                            </div>
                            <div id="descripcion-imagen-modal" class="mt-3"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Cerrar
                            </button>
                            <button type="button" id="btn-descargar-imagen" class="btn btn-primary" style="display: none;">
                                <i class="fas fa-download me-2"></i>Descargar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Acciones Disponibles</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0"><i class="fas fa-times-circle me-2"></i>Rechazar Venta</h6>
                                </div>
                                <div class="card-body">
                                    <form id="form-rechazo" method="POST">
                                        <input type="hidden" name="accion" value="rechazar">
                                        <div class="mb-3">
                                            <label for="descripcion_rechazo" class="form-label">
                                                Motivo del Rechazo <span class="text-danger">*</span>
                                            </label>
                                            <textarea class="form-control" id="descripcion_rechazo" name="descripcion_rechazo"
                                                rows="4" required placeholder="Explique el motivo del rechazo..."></textarea>
                                        </div>
                                        <button type="button" class="btn btn-danger w-100" id="btn-rechazar">
                                            <i class="fas fa-times me-2"></i>Rechazar Venta
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0"><i class="fas fa-check-circle me-2"></i>Enviar al Sector PCP</h6>
                                </div>
                                <div class="card-body">
                                    <form id="form-aprobacion" method="POST">
                                        <input type="hidden" name="accion" value="enviar_pcp">
                                        <div class="mb-3">
                                            <label for="observaciones_pcp" class="form-label">
                                                Observaciones <span class="text-muted">(opcional)</span>
                                            </label>
                                            <textarea class="form-control" id="observaciones_pcp" name="observaciones_pcp"
                                                rows="4" placeholder="Observaciones adicionales..."></textarea>
                                        </div>
                                        <button type="button" class="btn btn-success w-100" id="btn-aprobar">
                                            <i class="fas fa-paper-plane me-2"></i>Aprobar y Enviar al Sector PCP
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver a Pendientes
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalImagenProducto" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-image me-2"></i>Imagen del Producto
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center bg-light p-3">
                        <h6 id="producto-nombre" class="mb-3 fw-bold text-primary"></h6>
                        <div id="producto-imagen-container">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>No hay imagen disponible para este producto
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="confirmarRechazoModal" tabindex="-1" aria-labelledby="confirmarRechazoModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="confirmarRechazoModalLabel">
                            <i class="fas fa-times-circle me-2"></i>Confirmar Rechazo
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <span>Esta acción rechazará la venta y notificará al vendedor.</span>
                        </div>
                        <p>El motivo del rechazo será enviado al vendedor para su conocimiento.</p>
                        <p class="mb-0 fw-bold">¿Está seguro de que desea rechazar esta venta?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="button" id="btn-confirmar-rechazo" class="btn btn-danger">
                            <i class="fas fa-times-circle me-2"></i>Sí, Rechazar Venta
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="confirmarAprobacionModal" tabindex="-1" aria-labelledby="confirmarAprobacionModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="confirmarAprobacionModalLabel">
                            <i class="fas fa-check-circle me-2"></i>Confirmar Aprobación
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-success">
                            <i class="fas fa-info-circle me-2"></i>
                            <span>Esta acción aprobará la venta y la enviará al sector PCP para su procesamiento.</span>
                        </div>
                        <p>Las observaciones opcionales que haya ingresado se incluirán en el registro.</p>
                        <p class="mb-0 fw-bold">¿Está seguro de que desea aprobar y enviar esta venta?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="button" id="btn-confirmar-aprobacion" class="btn btn-success">
                            <i class="fas fa-check-circle me-2"></i>Sí, Aprobar y Enviar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="<?php echo $url_base; ?>secciones/contable/js/contable.js"></script>
        <script src="<?php echo $url_base; ?>config/notificacion/sistema-notificaciones.js"></script>

        <script>
            const CONTABLE_CONFIG = <?php echo json_encode($configuracionJS); ?>;
            const IMAGENES_AUTORIZACION = <?php echo json_encode($imagenesAutorizacion); ?>;
            const IMAGENES_PRODUCTOS = <?php echo json_encode($imagenesProductos); ?>;
        </script>
</body>

</html>