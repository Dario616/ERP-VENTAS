<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";

if (file_exists("controllers/DespachoController.php")) {
    include "controllers/DespachoController.php";
} else {
    die("Error: No se pudo cargar el controlador de despacho.");
}

$controller = new DespachoController($conexion, $url_base);

if (!$controller->verificarPermisos('ver')) {
    header("Location: " . $url_base . "index.php?error=No tienes permisos para ver expediciones");
    exit();
}

if ($controller->handleApiRequest()) {
    exit();
}

$resultado = $controller->procesarFiltros();
$expediciones = $resultado['expediciones'];
$filtrosAplicados = $resultado['filtros_aplicados'];
$error = $resultado['error'];
$resumen = $resultado['resumen'] ?? [];

$datosVista = $controller->obtenerDatosVista();
$configuracionJS = $controller->obtenerConfiguracionJS();
$mensajes = $controller->manejarMensajes();

$controller->logActividad('Acceso seguimiento despacho');
$breadcrumb_items = ['Seguimiento Produccion', 'Seguimiento Despacho'];
$item_urls = [
    $url_base . 'secciones/produccion_seguimiento/main.php',
];
$additional_css = [$url_base . 'secciones/despacho_seguimiento/utils/styles.css'];
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
                        <div class="col-md-4 mb-3">
                            <label for="numero_expedicion" class="form-label">N° Expedición</label>
                            <input type="text" class="form-control" id="search-expedicion" name="numero_expedicion"
                                placeholder="Buscar expedición..."
                                value="<?php echo htmlspecialchars($filtrosAplicados['numero_expedicion']); ?>">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="transportista" class="form-label">Transportista</label>
                            <select class="form-select" name="transportista">
                                <option value="">Todos</option>
                                <?php foreach ($datosVista['transportistas'] as $transportista): ?>
                                    <option value="<?php echo htmlspecialchars($transportista); ?>"
                                        <?php echo ($filtrosAplicados['transportista'] === $transportista) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($transportista); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2 mb-3">
                            <label for="destino" class="form-label">Destino</label>
                            <select class="form-select" name="destino">
                                <option value="">Todos</option>
                                <?php foreach ($datosVista['destinos'] as $destino): ?>
                                    <option value="<?php echo htmlspecialchars($destino); ?>"
                                        <?php echo ($filtrosAplicados['destino'] === $destino) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($destino); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" name="estado">
                                <option value="">Todos</option>
                                <?php foreach ($datosVista['estados_expedicion'] as $estado): ?>
                                    <option value="<?php echo htmlspecialchars($estado); ?>"
                                        <?php echo ($filtrosAplicados['estado'] === $estado) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($estado); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-1 mb-3 d-flex align-items-end">
                        </div>
                    </div>

                    <div class="row">

                        <div class="col-md-2 mb-3">
                            <label for="id_venta_asignado" class="form-label"># Venta</label>
                            <input type="number" class="form-control" name="id_venta_asignado"
                                placeholder="# de la Venta..."
                                value="<?php echo htmlspecialchars($filtrosAplicados['id_venta_asignado'] ?? ''); ?>"
                                min="1" step="1">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="id_stock" class="form-label">Etiqueta</label>
                            <input type="number" class="form-control" name="id_stock"
                                placeholder="Etiqueta del item..."
                                value="<?php echo htmlspecialchars($filtrosAplicados['id_stock'] ?? ''); ?>"
                                min="1" step="1">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="fecha_desde" class="form-label">Fecha Desde</label>
                            <input type="date" class="form-control" name="fecha_desde"
                                value="<?php echo htmlspecialchars($filtrosAplicados['fecha_desde']); ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                            <input type="date" class="form-control" name="fecha_hasta"
                                value="<?php echo htmlspecialchars($filtrosAplicados['fecha_hasta']); ?>">
                        </div>
                        <div class="col-md-4 mb-3 d-flex align-items-end">
                            <div class="btn-group w-100">
                                <button type="button" id="clear-filters" class="btn btn-outline-secondary btn-custom">
                                    <i class="fas fa-times me-1"></i>Limpiar Filtros
                                </button>
                                <button type="submit" class="btn btn-primary btn-custom">
                                    <i class="fas fa-search me-1"></i>Buscar Expediciones
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-truck me-2"></i>Expediciones de Despacho</h5>
                <span class="badge bg-primary badge-custom">
                    <?php echo count($expediciones); ?> expediciones
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($expediciones)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-truck fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No se encontraron expediciones</h5>
                        <p class="text-muted">Intenta ajustar los filtros de búsqueda</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>N° Expedición</th>
                                    <th>Descripcion</th>
                                    <th>Transportista</th>
                                    <th>Estado</th>
                                    <th>Fecha Creación</th>
                                    <th>Destino</th>
                                    <th class="text-center">Bobinas</th>
                                    <th class="text-end">Peso Liquido</th>
                                    <th class="text-end">Peso Bruto</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expediciones as $expedicion): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary"><?php echo htmlspecialchars($expedicion['numero_expedicion']); ?></strong>
                                            <?php if ($expedicion['tiempo_desde_creacion']): ?>
                                                <br><small class="text-muted">hace <?php echo $expedicion['tiempo_desde_creacion']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="text-truncate d-block" title="<?php echo htmlspecialchars($expedicion['descripcion'] ?? 'Sin descripcion'); ?>">
                                                <?php
                                                $desc = $expedicion['descripcion'] ?? 'Sin descripcion';
                                                echo htmlspecialchars(strlen($desc) > 30 ? substr($desc, 0, 30) . '...' : $desc);
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($expedicion['transportista'] ?? 'Sin asignar'); ?></strong>
                                            <?php if ($expedicion['tipovehiculo']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($expedicion['tipovehiculo']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $expedicion['estado_badge_class']; ?>">
                                                <?php echo htmlspecialchars($expedicion['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo $expedicion['fecha_creacion_formateada']; ?></small>
                                            <?php if ($expedicion['fecha_despacho_formateada']): ?>
                                                <br><small class="text-info">Desp: <?php echo $expedicion['fecha_despacho_formateada']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($expedicion['destino'] ?? 'Sin destino'); ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary badge-custom">
                                                <?php echo number_format($expedicion['total_bobinas'] ?? 0); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <strong class="text-info"><?php echo $expedicion['peso_liquido_formateado']; ?></strong>
                                        </td>
                                        <td class="text-end">
                                            <strong><?php echo $expedicion['peso_bruto_formateado']; ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?php echo $url_base; ?>secciones/despacho_seguimiento/ver.php?numero_expedicion=<?php echo urlencode($expedicion['numero_expedicion']); ?>"
                                                class="btn btn-sm btn-outline-info btn-custom"
                                                title="Ver detalles de la expedición">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($resumen)): ?>
                        <div class="card-footer bg-light">
                            <div class="row text-center">
                                <div class="col-md-2">
                                    <strong>Total Expediciones:</strong> <?php echo number_format($resumen['total_expediciones']); ?>
                                </div>
                                <div class="col-md-2">
                                    <strong>Total Items:</strong> <?php echo number_format($resumen['total_items']); ?>
                                </div>
                                <div class="col-md-2">
                                    <strong>Transportistas:</strong> <?php echo number_format($resumen['transportistas_unicos']); ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Peso Liquido:</strong> <?php echo $resumen['peso_liquido_formateado']; ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Peso Bruto:</strong> <?php echo $resumen['peso_bruto_formateado']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $url_base; ?>secciones/despacho_seguimiento/js/despacho.js"></script>
    <script>
        const DESPACHO_CONFIG = <?php echo json_encode($configuracionJS); ?>;
    </script>
</body>

</html>