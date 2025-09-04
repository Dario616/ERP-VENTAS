<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";

if (!isset($_GET['id_orden']) || empty($_GET['id_orden'])) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>ID de orden no proporcionado</div>';
    exit;
}

$idOrden = (int)$_GET['id_orden'];
if (file_exists("controllers/ProduccionController.php")) {
    include "controllers/ProduccionController.php";
} else {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error: No se pudo cargar el controlador de producción.</div>';
    exit;
}

try {
    $controller = new ProduccionController($conexion, $url_base);

    $orden = $controller->obtenerDetallesOrden($idOrden);

    if (!$orden) {
        echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Orden no encontrada</div>';
        exit;
    }

    $tipoProducto = $orden['tipo_detectado'];
    $datosEspecificos = $orden['datos_especificos'];
} catch (Exception $e) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error al obtener los detalles: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información General</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td><strong>Orden #:</strong></td>
                        <td><?php echo htmlspecialchars($orden['id']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Venta #:</strong></td>
                        <td><?php echo htmlspecialchars($orden['id_venta']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Cliente:</strong></td>
                        <td><?php echo htmlspecialchars($orden['cliente']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Vendedor:</strong></td>
                        <td><?php echo htmlspecialchars($orden['nombre_vendedor'] ?? 'No especificado'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Fecha Orden:</strong></td>
                        <td><?php echo htmlspecialchars($orden['fecha_orden_formateada']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Estado:</strong></td>
                        <td>
                            <?php if ($orden['estado'] === 'Completado'): ?>
                                <span class="badge bg-success">Completado</span>
                            <?php elseif ($orden['estado'] === 'Pendiente'): ?>
                                <span class="badge bg-warning text-dark">Pendiente</span>
                            <?php elseif ($orden['estado'] === 'Orden Emitida'): ?>
                                <span class="badge bg-info">Orden Emitida</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($orden['estado']); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="fas fa-dollar-sign me-2"></i>Información Comercial</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td><strong>Moneda:</strong></td>
                        <td><?php echo htmlspecialchars($orden['moneda']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Monto Total:</strong></td>
                        <td class="fw-bold text-success">
                            <?php echo $orden['simbolo_moneda'] . number_format((float)$orden['monto_total'], 2, ',', '.'); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Precio Unitario:</strong></td>
                        <td>
                            <?php
                            if ($orden['precio']) {
                                echo $orden['simbolo_moneda'] . number_format((float)$orden['precio'], 2, ',', '.');
                            } else {
                                echo 'No especificado';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-secondary text-white">
        <h6 class="mb-0"><i class="fas fa-comment-alt me-2"></i>Observaciones</h6>
    </div>
    <div class="card-body">
        <p class="mb-0">
            <?php echo !empty($orden['observaciones']) ? nl2br(htmlspecialchars($orden['observaciones'])) : 'Sin observaciones'; ?>
        </p>
    </div>
</div>

<!-- NUEVA SECCIÓN: Información de Recetas -->
<div class="card mb-4">
    <div class="card-header" style="background-color: #6f42c1; color: white;">
        <h6 class="mb-0">
            <i class="fas fa-flask me-2"></i>Información de Recetas
            <?php if ($orden['tiene_receta']): ?>
                <span class="badge bg-success ms-2">
                    <i class="fas fa-check-circle me-1"></i>
                    <?php echo $orden['cantidad_recetas']; ?> receta(s) asignada(s)
                </span>
            <?php else: ?>
                <span class="badge bg-warning text-dark ms-2">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Sin recetas asignadas
                </span>
            <?php endif; ?>
        </h6>
    </div>
    <div class="card-body">
        <?php if ($orden['tiene_receta'] && !empty($orden['recetas_detalles'])): ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th><i class="fas fa-flask me-1"></i>Receta</th>
                            <th><i class="fas fa-code-branch me-1"></i>Versión</th>
                            <th><i class="fas fa-tag me-1"></i>Tipo</th>
                            <th><i class="fas fa-calendar me-1"></i>Fecha Asignación</th>
                            <th><i class="fas fa-info-circle me-1"></i>Estado</th>
                            <th><i class="fas fa-comment me-1"></i>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orden['recetas_detalles'] as $receta): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($receta['nombre_receta']); ?></strong>
                                    <?php if (!empty($receta['descripcion'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($receta['descripcion']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">v<?php echo $receta['version_receta']; ?></span>
                                </td>
                                <td>
                                    <?php if ($receta['tipo_receta'] === 'PRODUCTO'): ?>
                                        <span class="badge bg-primary">
                                            <i class="fas fa-box me-1"></i>Producto
                                        </span>
                                        <?php if (!empty($receta['tipo_producto_nombre'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($receta['tipo_producto_nombre']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-info">
                                            <i class="fas fa-flask me-1"></i>Materia Prima
                                        </span>
                                        <?php if (!empty($receta['materia_prima_objetivo'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($receta['materia_prima_objetivo']); ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo date('d/m/Y H:i', strtotime($receta['fecha_asignacion'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($receta['estado_asignacion'] === 'Completado'): ?>
                                        <span class="badge bg-success">Completado</span>
                                    <?php elseif ($receta['estado_asignacion'] === 'En Proceso'): ?>
                                        <span class="badge bg-info">En Proceso</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($receta['observaciones'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($receta['observaciones']); ?></small>
                                    <?php else: ?>
                                        <small class="text-muted fst-italic">Sin observaciones</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-flask fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No hay recetas asignadas a esta orden</h6>
                <p class="text-muted small">
                    Las recetas son necesarias para definir los materiales y procesos
                    requeridos para la producción de este artículo.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header bg-info text-white">
        <h6 class="mb-0"><i class="fas fa-box me-2"></i>Información del Producto - Sistema Simplificado</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive mb-4">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Descripción</th>
                        <th>Tipo</th>
                        <th>Cantidad</th>
                        <th>Unidad</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo htmlspecialchars($orden['nombre_producto'] ?? 'No especificado'); ?></td>
                        <td>
                            <span class="badge <?php echo $orden['clase_badge']; ?> text-white">
                                <i class="<?php echo $orden['icono_tipo']; ?> me-1"></i>
                                <?php echo htmlspecialchars($orden['tipo_simplificado']); ?>
                            </span>
                        </td>
                        <td><strong><?php echo number_format($orden['cantidad_total'], 2); ?></strong></td>
                        <td><?php echo htmlspecialchars($orden['unidad_medida'] ?? 'UN'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php if ($tipoProducto === 'TNT' && $datosEspecificos): ?>
            <div class="alert alert-primary">
                <h6><i class="fas fa-scroll me-2"></i>Especificaciones TNT </h6>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Gramatura:</strong> <?php echo htmlspecialchars($datosEspecificos['gramatura'] ?? 'N/A'); ?> g/m²</p>
                        <p><strong>Largura:</strong> <?php echo number_format($datosEspecificos['largura'] ?? 0, 2); ?> metros</p>
                        <p><strong>Longitud:</strong> <?php echo number_format($datosEspecificos['longitud'] ?? 0); ?> metros</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Color:</strong> <?php echo htmlspecialchars($datosEspecificos['color'] ?? 'N/A'); ?></p>
                        <p><strong>Peso por bobina:</strong> <?php echo number_format($datosEspecificos['peso_bobina'] ?? 0, 2); ?> kg</p>
                        <p><strong>Total bobinas:</strong> <?php echo number_format($datosEspecificos['total_bobinas'] ?? 0); ?></p>
                    </div>
                </div>
            </div>

        <?php elseif ($tipoProducto === 'SPUNLACE' && $datosEspecificos): ?>
            <div class="alert" style="background-color: #f8f4ff; border-color: #6f42c1;">
                <h6><i class="fas fa-swatchbook me-2" style="color: #6f42c1;"></i>Especificaciones Spunlace </h6>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Gramatura:</strong> <?php echo htmlspecialchars($datosEspecificos['gramatura'] ?? 'N/A'); ?> g/m²</p>
                        <p><strong>Largura:</strong> <?php echo number_format($datosEspecificos['largura'] ?? 0, 2); ?> metros</p>
                        <p><strong>Longitud:</strong> <?php echo number_format($datosEspecificos['longitud'] ?? 0); ?> metros</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Color:</strong> <?php echo htmlspecialchars($datosEspecificos['color'] ?? 'N/A'); ?></p>
                        <p><strong>Acabado:</strong> <?php echo htmlspecialchars($datosEspecificos['acabado'] ?? 'N/A'); ?></p>
                        <p><strong>Peso por bobina:</strong> <?php echo number_format($datosEspecificos['peso_bobina'] ?? 0, 2); ?> kg</p>
                        <p><strong>Total bobinas:</strong> <?php echo number_format($datosEspecificos['total_bobinas'] ?? 0); ?></p>
                    </div>
                </div>
            </div>

        <?php elseif ($tipoProducto === 'LAMINADORA'): ?>
            <div class="alert" style="background-color: #e8f7ff; border-color: #17a2b8;">
                <h6><i class="fas fa-layer-group me-2" style="color: #17a2b8;"></i>Especificaciones Laminadora </h6>
                <p><strong>Producto:</strong> <?php echo htmlspecialchars($orden['nombre_producto'] ?? 'No especificado'); ?></p>
            </div>

        <?php elseif ($tipoProducto === 'TOALLITAS' && $datosEspecificos): ?>
            <div class="alert alert-success">
                <h6><i class="fas fa-soap me-2"></i>Especificaciones Toallitas - Por Unidades</h6>
                <p><strong>Nombre del producto:</strong> <?php echo htmlspecialchars($datosEspecificos['nombre'] ?? 'N/A'); ?></p>
                <div class="alert alert-info mt-2 mb-0">
                    <small><i class="fas fa-info-circle me-1"></i><strong>Nota:</strong> Las toallitas se manejan por unidades individuales.</small>
                </div>
            </div>

        <?php elseif ($tipoProducto === 'PAÑOS' && $datosEspecificos): ?>
            <div class="alert" style="background-color: #fff3cd; border-color: #ffc107;">
                <h6><i class="fas fa-tshirt me-2" style="color: #856404;"></i>Especificaciones Paños </h6>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($datosEspecificos['nombre'] ?? 'N/A'); ?></p>
                        <p><strong>Color:</strong> <?php echo htmlspecialchars($datosEspecificos['color'] ?? 'N/A'); ?></p>
                        <p><strong>Largura:</strong> <?php echo number_format($datosEspecificos['largura'] ?? 0); ?> cm</p>
                        <p><strong>Picotado:</strong> <?php echo htmlspecialchars($datosEspecificos['picotado'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Cantidad:</strong> <?php echo number_format($orden['cantidad_total'] ?? 0); ?></p>
                        <p><strong>Unidad:</strong> <?php echo htmlspecialchars($datosEspecificos['unidad'] ?? 'N/A'); ?></p>
                        <p><strong>Peso:</strong> <?php echo number_format($datosEspecificos['peso'] ?? 0, 2); ?> kg</p>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Producto:</strong> <?php echo htmlspecialchars($orden['nombre_producto'] ?? 'No especificado'); ?>
                <br><small>Tipo no reconocido en el sistema simplificado</small>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .bg-purple {
        background-color: #6f42c1 !important;
    }

    .text-purple {
        color: #6f42c1 !important;
    }

    .bg-panos {
        background-color: #ffc107 !important;
        color: #212529 !important;
    }
</style>