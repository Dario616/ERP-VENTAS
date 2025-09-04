<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";

if (file_exists("controllers/ProduccionController.php")) {
    include "controllers/ProduccionController.php";
} else {
    die("Error: No se pudo cargar el controlador de producción.");
}

$controller = new ProduccionController($conexion, $url_base);

if (!$controller->verificarPermisos('ver')) {
    header("Location: " . $url_base . "index.php?error=No tienes permisos para ver producción");
    exit();
}

if ($controller->handleApiRequest()) {
    exit();
}

$resultado = $controller->procesarFiltros();
$grupos = $resultado['grupos'];
$filtrosAplicados = $resultado['filtros_aplicados'];
$error = $resultado['error'];
$resumen = $resultado['resumen'] ?? [];

$datosVista = $controller->obtenerDatosVista();
$configuracionJS = $controller->obtenerConfiguracionJS();
$mensajes = $controller->manejarMensajes();

$controller->logActividad('Acceso seguimiento producción');
$breadcrumb_items = ['Seguimiento Produccion', 'Produccion'];
$item_urls = [
    $url_base . 'secciones/produccion_seguimiento/main.php',
];
$additional_css = [$url_base . 'secciones/produccion_seguimiento/utils/styles.css'];
include $path_base . "components/head.php";
?>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <?php if (!empty($mensajes['mensaje'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $mensajes['mensaje']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensajes['error']) || !empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $mensajes['error'] ?: $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <div class="card mb-4">
            <div class="card-header filter-card">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros de Búsqueda</h5>
            </div>
            <div class="card-body">
                <form id="filter-form" method="GET">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="producto" class="form-label">Producto</label>
                            <input type="text" class="form-control" id="search-producto" name="producto"
                                placeholder="Buscar producto..."
                                value="<?php echo htmlspecialchars($filtrosAplicados['producto']); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="tipo_producto" class="form-label">Tipo de Producto</label>
                            <select class="form-select" name="tipo_producto">
                                <option value="">Todos los tipos</option>
                                <?php foreach ($datosVista['tipos_producto'] as $tipo): ?>
                                    <option value="<?php echo htmlspecialchars($tipo); ?>"
                                        <?php echo ($filtrosAplicados['tipo_producto'] === $tipo) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tipo); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3 d-flex align-items-end gap-2">
                            <button type="button" id="clear-filters" class="btn btn-outline-secondary btn-custom">
                                <i class="fas fa-times me-1"></i>Limpiar
                            </button>
                            <button type="submit" class="btn btn-primary btn-custom">
                                <i class="fas fa-search me-1"></i>Buscar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Producción</h5>
                <span class="badge bg-primary badge-custom">
                    <?php echo count($grupos); ?> grupos
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($grupos)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No se encontraron grupos de producción</h5>
                        <p class="text-muted">Intenta ajustar los filtros de búsqueda</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Producto</th>
                                    <th>Tipo</th>
                                    <th>Metragem</th>
                                    <th>Largura</th>
                                    <th>Gramatura</th>
                                    <th class="text-center">Items</th>
                                    <th class="text-center">Bobinas</th>
                                    <th class="text-end">Peso Bruto</th>
                                    <th class="text-end">Peso Líquido</th>
                                    <th class="text-center">Período</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grupos as $grupo): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($grupo['nombre_producto']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($grupo['tipo_producto']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <small>
                                                <?php echo htmlspecialchars($grupo['metragem'] ?: 'N/A'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo htmlspecialchars($grupo['largura'] ?: 'N/A'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo htmlspecialchars($grupo['gramatura'] ?: 'N/A'); ?>
                                                <?php if ($grupo['gramatura']): ?>g/m²<?php endif; ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary badge-custom">
                                                <?php echo number_format($grupo['total_items']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php echo number_format($grupo['bobinas_pacote_total']); ?>
                                        </td>
                                        <td class="text-end">
                                            <strong><?php echo $grupo['peso_bruto_formateado']; ?></strong>
                                        </td>
                                        <td class="text-end">
                                            <strong><?php echo $grupo['peso_liquido_formateado']; ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <small class="text-muted">
                                                <?php if ($grupo['dias_produccion'] == 1): ?>
                                                    <?php echo $grupo['fecha_primera_formateada']; ?>
                                                <?php else: ?>
                                                    <?php echo $grupo['dias_produccion']; ?> días
                                                    <br><?php echo $grupo['fecha_primera_formateada']; ?>
                                                    <br><?php echo $grupo['fecha_ultima_formateada']; ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-info btn-custom"
                                                title="Ver detalles"
                                                onclick="verDetallesGrupo(
                                                        '<?php echo addslashes($grupo['nombre_producto']); ?>',
                                                        '<?php echo addslashes($grupo['tipo_producto']); ?>',
                                                        '<?php echo addslashes($grupo['metragem']); ?>',
                                                        '<?php echo addslashes($grupo['largura']); ?>',
                                                        '<?php echo addslashes($grupo['gramatura']); ?>'
                                                    )">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detallesGrupoModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-list-ul me-2"></i>Detalles del Grupo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detalles-grupo-content">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $url_base; ?>secciones/produccion_seguimiento/js/produccion.js"></script>
    <script>
        const PRODUCCION_CONFIG = <?php echo json_encode($configuracionJS); ?>;
    </script>
</body>

</html>