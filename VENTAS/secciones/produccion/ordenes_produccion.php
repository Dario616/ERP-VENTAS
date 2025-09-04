<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '4']);

if (file_exists("controllers/ProduccionController.php")) {
    include "controllers/ProduccionController.php";
} else {
    die("Error: No se pudo cargar el controlador de producción.");
}

$controller = new ProduccionController($conexion, $url_base);

if ($controller->handleApiRequest()) {
    exit();
}

$resultadoFiltros = $controller->procesarFiltrosOrdenes();
$datosVista = $controller->obtenerDatosVista();
$mensajes = $controller->manejarMensajes();

$ordenes = $resultadoFiltros['ordenes'];
$totalPaginas = $resultadoFiltros['total_paginas'];
$totalRegistros = $resultadoFiltros['total_registros'];
$tiposProductos = $resultadoFiltros['tipos_productos'];
$filtrosAplicados = $resultadoFiltros['filtros_aplicados'];
$errorFiltros = $resultadoFiltros['error'];

$titulo = $datosVista['titulo'];
$url_base = $datosVista['url_base'];
$usuario_actual = $datosVista['usuario_actual'];
$es_admin = $datosVista['es_admin'];
$configuracionJS = $controller->obtenerConfiguracionJS();

if (!empty($_GET)) {
    $filtrosStr = !empty($filtrosAplicados['cliente']) ? 'Cliente: ' . $filtrosAplicados['cliente'] : 'Consulta general';
    $controller->logActividad('Consulta órdenes de producción', $filtrosStr);
}

$paginaActual = $filtrosAplicados['pagina'];
$breadcrumb_items = ['Sector Produccion', 'Ordenes de Produccion'];
$item_urls = [
    $url_base . 'secciones/produccion/main.php',
];
$additional_css = [$url_base . 'secciones/produccion/utils/styles.css'];
include $path_base . "components/head.php";
?>
<body class="page-ordenes">
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
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

        <?php if (!empty($errorFiltros)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($errorFiltros); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0 text-dark"><i class="fas fa-filter me-2"></i>Filtros</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label for="cliente" class="form-label">Cliente</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="cliente" name="cliente"
                                value="<?php echo htmlspecialchars($filtrosAplicados['cliente']); ?>"
                                placeholder="Buscar por cliente...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label for="tipo" class="form-label">Tipo Producto</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-tags"></i></span>
                            <select class="form-select" id="tipo" name="tipo">
                                <option value="">Todos los tipos</option>
                                <?php foreach ($tiposProductos as $tipo): ?>
                                    <option value="<?php echo htmlspecialchars($tipo); ?>"
                                        <?php echo $filtrosAplicados['tipo'] === $tipo ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tipo); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label for="estado" class="form-label">Estado</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-tag"></i></span>
                            <select class="form-select" id="estado" name="estado">
                                <option value="">Todos los estados</option>
                                <option value="Pendiente" <?php echo $filtrosAplicados['estado'] === 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="Orden Emitida" <?php echo $filtrosAplicados['estado'] === 'Orden Emitida' ? 'selected' : ''; ?>>Orden Emitida</option>
                                <option value="Completado" <?php echo $filtrosAplicados['estado'] === 'Completado' ? 'selected' : ''; ?>>Completado</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label for="tiene_receta" class="form-label">Recetas</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-flask"></i></span>
                            <select class="form-select" id="tiene_receta" name="tiene_receta">
                                <option value="">Todas las órdenes</option>
                                <option value="si" <?php echo $filtrosAplicados['tiene_receta'] === 'si' ? 'selected' : ''; ?>>Con receta</option>
                                <option value="no" <?php echo $filtrosAplicados['tiene_receta'] === 'no' ? 'selected' : ''; ?>>Sin receta</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="btn-group w-100" role="group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Filtrar
                            </button>
                            <a href="<?php echo $url_base; ?>secciones/produccion/ordenes_produccion.php" class="btn btn-secondary">
                                <i class="fas fa-sync-alt me-1"></i>Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Órdenes de Producción</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th><i class="fas fa-hashtag me-1"></i>Cod. Orden</th>
                                <th><i class="fas fa-hashtag me-1"></i>Cod. Venta</th>
                                <th><i class="fas fa-user me-1"></i>Cliente</th>
                                <th><i class="fas fa-box me-1"></i>Producto</th>
                                <th><i class="fas fa-tags me-1"></i>Tipo</th>
                                <th><i class="fas fa-balance-scale me-1"></i>Cantidad</th>
                                <th><i class="fas fa-calendar me-1"></i>Fecha Orden</th>
                                <th><i class="fas fa-tag me-1"></i>Estado</th>
                                <th class="receta-column"><i class="fas fa-flask me-1"></i>Recetas</th>
                                <th class="actions-column"><i class="fas fa-cogs me-1"></i>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ordenes)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i>No hay órdenes de producción registradas
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ordenes as $orden): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($orden['id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($orden['id_venta']); ?></td>
                                        <td><?php echo htmlspecialchars($orden['cliente']); ?></td>
                                        <td>
                                            <small><?php echo htmlspecialchars(substr($orden['nombre_producto'] ?? 'No especificado', 0, 30)); ?><?php echo strlen($orden['nombre_producto'] ?? '') > 30 ? '...' : ''; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $orden['clase_badge']; ?> badge-tipo text-white">
                                                <i class="<?php echo $orden['icono_tipo']; ?> me-1"></i>
                                                <?php echo htmlspecialchars($orden['tipo_simplificado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo $orden['cantidad_formateada']; ?></strong>
                                            <small class="text-muted"><?php echo htmlspecialchars($orden['unidad_medida'] ?? 'UN'); ?></small>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($orden['fecha_orden_formateada']); ?></small></td>
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
                                        <td class="receta-column">
                                            <div class="<?php echo $orden['tiene_receta'] ? 'con-receta' : 'sin-receta'; ?>">
                                                <span class="badge <?php echo $orden['clase_badge_receta']; ?> badge-receta"
                                                    title="<?php echo $orden['tiene_receta'] ? 'Orden con ' . $orden['texto_receta'] : 'Sin receta asignada'; ?>">
                                                    <i class="<?php echo $orden['icono_receta']; ?> me-1"></i>
                                                    <?php echo $orden['texto_receta']; ?>
                                                </span>
                                                <?php if ($orden['tiene_receta'] && !empty($orden['estado_general_recetas'])): ?>
                                                    <div class="estado-recetas">
                                                        <span class="badge <?php echo $orden['clase_estado_recetas']; ?>" style="font-size: 0.6em;">
                                                            <?php echo htmlspecialchars($orden['estado_general_recetas']); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="actions-column">
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-info btn-sm btn-ver-detalles"
                                                    data-id-orden="<?php echo $orden['id']; ?>"
                                                    data-tipo-producto="<?php echo htmlspecialchars($orden['tipo_simplificado']); ?>"
                                                    title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-primary btn-sm btn-generar-pdf"
                                                    data-id-orden="<?php echo $orden['id']; ?>"
                                                    data-tipo-producto="<?php echo htmlspecialchars($orden['tipo_simplificado']); ?>"
                                                    title="Generar PDF">
                                                    <i class="fas fa-file-pdf"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPaginas > 1): ?>
                    <nav aria-label="Paginación de órdenes de producción" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $paginaActual <= 1 ? 'disabled' : ''; ?>">
                                <?php if ($paginaActual > 1): ?>
                                    <a class="page-link" href="?<?php
                                                                $params = $filtrosAplicados;
                                                                $params['pagina'] = $paginaActual - 1;
                                                                echo http_build_query(array_filter($params, function ($v) {
                                                                    return $v !== '';
                                                                }));
                                                                ?>">
                                        <i class="fas fa-chevron-left"></i> Anterior
                                    </a>
                                <?php else: ?>
                                    <span class="page-link">
                                        <i class="fas fa-chevron-left"></i> Anterior
                                    </span>
                                <?php endif; ?>
                            </li>
                            <?php
                            $startPage = max(1, $paginaActual - 2);
                            $endPage = min($totalPaginas, $paginaActual + 2);
                            ?>
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?php echo $paginaActual == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php
                                                                $params = $filtrosAplicados;
                                                                $params['pagina'] = $i;
                                                                echo http_build_query(array_filter($params, function ($v) {
                                                                    return $v !== '';
                                                                }));
                                                                ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $paginaActual >= $totalPaginas ? 'disabled' : ''; ?>">
                                <?php if ($paginaActual < $totalPaginas): ?>
                                    <a class="page-link" href="?<?php
                                                                $params = $filtrosAplicados;
                                                                $params['pagina'] = $paginaActual + 1;
                                                                echo http_build_query(array_filter($params, function ($v) {
                                                                    return $v !== '';
                                                                }));
                                                                ?>">
                                        Siguiente <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="page-link">
                                        Siguiente <i class="fas fa-chevron-right"></i>
                                    </span>
                                <?php endif; ?>
                            </li>
                        </ul>

                        <div class="text-center text-muted mt-2">
                            <small>
                                Página <?php echo $paginaActual; ?> de <?php echo $totalPaginas; ?>
                                (<?php echo $totalRegistros; ?> registros total)
                            </small>
                        </div>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDetallesProduccion" tabindex="-1" aria-labelledby="modalDetallesLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalDetallesLabel">
                        <i class="fas fa-info-circle me-2"></i>Detalles de la Orden de Producción
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cerrar
                    </button>
                    <button type="button" class="btn btn-primary" id="btnGenerarPDF">
                        <i class="fas fa-file-pdf me-1"></i>Generar PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $url_base; ?>secciones/produccion/js/produccion.js"></script>
    <script>
        const PRODUCCION_CONFIG = <?php echo json_encode($configuracionJS); ?>;
        $(document).ready(function() {
            $(document).on('click', '.btn-generar-pdf', function(e) {
                e.preventDefault();
                const idOrden = $(this).data('id-orden');
                const tipoProducto = $(this).data('tipo-producto');
                ProduccionManager.generarPDF(idOrden, tipoProducto);
            });
        });
    </script>
</body>

</html>