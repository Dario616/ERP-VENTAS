<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();

if (file_exists("controllers/VentaController.php")) {
    include "controllers/VentaController.php";
} else {
    die("Error: No se pudo cargar el controlador de ventas.");
}

$controller = new VentaController($conexion, $url_base);

if ($controller->handleApiRequest()) {
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    header("Location: " . $url_base . "?error=" . urlencode("ID de venta no válido"));
    exit();
}

try {
    $resumenVenta = $controller->obtenerResumenVenta($id);
    $venta = $resumenVenta['venta'];
    $esFinalizadoManualmente = $resumenVenta['es_finalizado_manualmente'];
    $procesoPcp = $resumenVenta['proceso_pcp'];
    $historialPcp = $resumenVenta['historial_pcp'];
    $resumenProduccion = $resumenVenta['resumen_produccion'];
    $progresoGeneral = $resumenVenta['progreso_general'];

    $datosVista = $controller->obtenerDatosVista();

    $controller->logActividad('Ver estado venta', 'ID: ' . $id);
} catch (Exception $e) {
    header("Location: " . $url_base . "?error=" . urlencode($e->getMessage()));
    exit();
}

$usuario_actual = $datosVista['usuario_actual'];
$breadcrumb_items = ['Sector Ventas', 'Listado Ventas', 'Estado de Venta'];
$item_urls = [
    $url_base . 'secciones/ventas/main.php',
    $url_base . 'secciones/ventas/index.php'
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de Venta #<?php echo htmlspecialchars($venta['id']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="<?php echo $url_base; ?>secciones/estado/utils/styles.css" rel="stylesheet">
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header <?php echo $esFinalizadoManualmente ? 'finalizado-manualmente' : ''; ?> d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">
                            <i class="fas fa-file-invoice me-2"></i>
                            Venta #<?php echo htmlspecialchars($venta['id']); ?>
                        </h3>
                        <span class="status-badge bg-<?php echo $venta['estado_class']; ?> text-white">
                            <i class="<?php echo $venta['estado_icono']; ?>"></i>
                            <?php echo $venta['estado_label']; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($esFinalizadoManualmente): ?>
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header estado-finalizado">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>Información de la Venta
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <div class="mb-3">
                                        <small class="text-muted">Cliente</small>
                                        <div class="fw-bold"><?php echo htmlspecialchars($venta['cliente']); ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-3">
                                        <small class="text-muted">Fecha de Venta</small>
                                        <div class="fw-bold"><?php echo $venta['fecha_venta_formateada']; ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-3">
                                        <small class="text-muted">Monto Total</small>
                                        <div class="fw-bold text-success"><?php echo $venta['monto_total_formateado']; ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-3">
                                        <small class="text-muted">Es Crédito</small>
                                        <div class="fw-bold"><?php echo $venta['es_credito_texto']; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="progress-section">
                        <h5 class="mb-3">
                            <i class="fas fa-hand-paper icono-manual text-dark me-2"></i>
                            Venta Finalizada Manualmente
                        </h5>
                        <div class="alert alert-dark border-0">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-double me-3" style="font-size: 2rem;"></i>
                                <div>
                                    <h6 class="mb-1">Proceso Completado</h6>
                                    <p class="mb-0">Esta venta ha sido procesada y finalizada manualmente por el equipo de PCP.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($procesoPcp): ?>
                <div class="card mb-4">
                    <div class="card-header estado-finalizado">
                        <h5 class="mb-0">
                            <i class="fas fa-clipboard-check me-2"></i>
                            Información del Procesamiento PCP
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <small class="text-muted">Fecha de Procesamiento:</small>
                                    <div class="fecha-procesamiento"><?php echo htmlspecialchars($procesoPcp['fecha_procesamiento_formateada']); ?></div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($procesoPcp['observaciones'])): ?>
                            <div class="observaciones-pcp">
                                <h6 class="mb-3">
                                    <i class="fas fa-sticky-note me-2"></i>
                                    Observaciones del Procesamiento
                                </h6>
                                <div class="border-start border-4 border-primary ps-3">
                                    <?php echo nl2br(htmlspecialchars($procesoPcp['observaciones'])); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                No se registraron observaciones específicas para este procesamiento.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($historialPcp) && count($historialPcp) > 1): ?>
                <div class="card mb-4">
                    <div class="card-header estado-finalizado">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            Historial de Procesamientos
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($historialPcp as $index => $proceso): ?>
                            <?php if ($index > 0):
                            ?>
                                <div class="historial-pcp">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="usuario-pcp"><?php echo htmlspecialchars($proceso['usuario_pcp_completo']); ?></span>
                                        <span class="fecha-procesamiento"><?php echo htmlspecialchars($proceso['fecha_procesamiento_formateada']); ?></span>
                                    </div>
                                    <?php if (!empty($proceso['observaciones'])): ?>
                                        <div class="border-start border-3 border-secondary ps-3 mt-2">
                                            <?php echo nl2br(htmlspecialchars($proceso['observaciones'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted fst-italic">Sin observaciones</div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="card mb-4">
                <div class="card-header estado-finalizado">
                    <h5 class="mb-0">
                        <i class="fas fa-box me-2"></i>
                        Productos de la Venta
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($venta['productos'])): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-center">Cantidad</th>
                                        <th class="text-center">Precio Unitario</th>
                                        <th class="text-center">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($venta['productos'] as $producto): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($producto['descripcion']); ?></td>
                                            <td class="text-center"><?php echo htmlspecialchars($producto['cantidad']); ?></td>
                                            <td class="text-center">₲ <?php echo number_format($producto['precio'], 0, ',', '.'); ?></td>
                                            <td class="text-center">₲ <?php echo number_format($producto['cantidad'] * $producto['precio'], 0, ',', '.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No se encontraron productos asociados a esta venta.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información de la Venta</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <div class="mb-3">
                                        <small class="text-muted">Cliente</small>
                                        <div class="fw-bold"><?php echo htmlspecialchars($venta['cliente']); ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-3">
                                        <small class="text-muted">Fecha de Venta</small>
                                        <div class="fw-bold"><?php echo $venta['fecha_venta_formateada']; ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-3">
                                        <small class="text-muted">Es Crédito</small>
                                        <div class="fw-bold"><?php echo $venta['es_credito_texto']; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="progress-section">
                        <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Progreso General de la Venta</h5>
                        <div class="progress mb-3" style="height: 30px;">
                            <div class="progress-bar <?php echo $progresoGeneral['clase_progreso']; ?>"
                                role="progressbar"
                                style="width: <?php echo $progresoGeneral['porcentaje']; ?>%"
                                aria-valuenow="<?php echo $progresoGeneral['porcentaje']; ?>"
                                aria-valuemin="0"
                                aria-valuemax="100">
                                <?php echo $progresoGeneral['porcentaje']; ?>% Completado
                            </div>
                        </div>
                        <p class="text-muted mb-0">
                            <?php echo $progresoGeneral['items_completos']; ?> de <?php echo $progresoGeneral['items_total']; ?> productos completamente despachados
                        </p>
                    </div>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-box me-2"></i>Productos y Progreso</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($resumenProduccion as $producto): ?>
                        <?php
                        $porcentajeProduccion = $producto['porcentaje_produccion'];
                        $porcentajeDespacho = $producto['porcentaje_despacho'];
                        $unidad = $producto['unidad_medida'] ?? 'kg';
                        $esBobinas = $unidad === 'bobinas';
                        ?>

                        <div class="product-item">

                            <div class="product-header">
                                <div class="product-title">
                                    <i class="fas fa-industry text-primary"></i>
                                    <?php echo htmlspecialchars($producto['producto']); ?>
                                </div>
                                <div class="product-subtitle">
                                    Tipo: <?php echo $producto['tipo_producto'] ?? 'No especificado'; ?>
                                </div>
                            </div>

                            <div class="quantity-grid">
                                <div class="quantity-item pedido">
                                    <div class="quantity-label">Pedido</div>
                                    <div class="quantity-value"><?php echo $producto['cantidad_pedida_formateada']; ?></div>
                                </div>
                                <div class="quantity-item producido">
                                    <div class="quantity-label">Producido</div>
                                    <div class="quantity-value"><?php echo $producto['cantidad_producida_formateada']; ?></div>
                                </div>
                                <div class="quantity-item despachado">
                                    <div class="quantity-label">Despachado</div>
                                    <div class="quantity-value"><?php echo $producto['cantidad_despachada_formateada']; ?></div>
                                </div>
                            </div>

                            <div class="progress-section-product">
                                <div class="progress-item">
                                    <div class="progress-label">
                                        <i class="fas fa-cogs"></i>
                                        Progreso Producción
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar <?php echo $producto['clase_progreso_produccion']; ?>"
                                            style="width: <?php echo $porcentajeProduccion; ?>%">
                                            <?php echo $porcentajeProduccion; ?>%
                                        </div>
                                    </div>
                                </div>
                                <div class="progress-item">
                                    <div class="progress-label">
                                        <i class="fas fa-truck"></i>
                                        Progreso Despacho
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar <?php echo $producto['clase_progreso_despacho']; ?>"
                                            style="width: <?php echo $porcentajeDespacho; ?>%">
                                            <?php echo $porcentajeDespacho; ?>%
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="action-buttons mt-3">
                                <button type="button" class="btn btn-production"
                                    onclick="verItemsProduccionProducto(<?php echo $producto['id_producto'] ?? 0; ?>, '<?php echo htmlspecialchars($producto['producto'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-search me-1"></i>Ver Producción
                                </button>
                                <button type="button" class="btn btn-success"
                                    onclick="verItemsDespachosProducto(<?php echo $producto['id_producto'] ?? 0; ?>, '<?php echo htmlspecialchars($producto['producto'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-truck me-1"></i>Ver Despacho
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const VENTA_CONFIG = <?php echo json_encode($controller->obtenerConfiguracionJS()); ?>;
    </script>
    <script src="<?php echo $url_base; ?>secciones/estado/js/ventas.js"></script>
</body>

</html>