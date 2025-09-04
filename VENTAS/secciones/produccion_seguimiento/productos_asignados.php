<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";

if (file_exists("controllers/ProductosAsignadosController.php")) {
    include "controllers/ProductosAsignadosController.php";
} else {
    die("Error: No se pudo cargar el controlador de productos asignados.");
}

$controller = new ProductosAsignadosController($conexion, $url_base);

if (!$controller->verificarPermisos('ver')) {
    header("Location: " . $url_base . "index.php?error=No tienes permisos para ver productos asignados");
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

$controller->logActividad('Acceso productos asignados');
$breadcrumb_items = ['Seguimiento Produccion', 'Produccion Asignada'];
$item_urls = [
    $url_base . 'secciones/produccion_seguimiento/main.php',
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $datosVista['titulo']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/produccion_seguimiento/utils/styles.css">
</head>

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
                        <div class="col-md-2 mb-3">
                            <label for="cliente" class="form-label">Cliente</label>
                            <input type="text" class="form-control" id="search-cliente" name="cliente"
                                placeholder="Buscar cliente..."
                                value="<?php echo htmlspecialchars($filtrosAplicados['cliente']); ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="producto" class="form-label">Producto</label>
                            <input type="text" class="form-control" id="search-producto" name="producto"
                                placeholder="Buscar producto..."
                                value="<?php echo htmlspecialchars($filtrosAplicados['producto']); ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="tipo_producto" class="form-label">Tipo</label>
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
                        <div class="col-md-2 mb-3">
                            <label for="fecha_desde" class="form-label">Fecha Orden Desde</label>
                            <input type="date" class="form-control" name="fecha_desde"
                                value="<?php echo htmlspecialchars($filtrosAplicados['fecha_desde']); ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="fecha_hasta" class="form-label">Fecha Orden Hasta</label>
                            <input type="date" class="form-control" name="fecha_hasta"
                                value="<?php echo htmlspecialchars($filtrosAplicados['fecha_hasta']); ?>">
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end gap-2">
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
                <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Productos Asignados a Órdenes</h5>
                <span class="badge bg-primary badge-custom">
                    <?php echo count($grupos); ?> órdenes
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($grupos)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-clipboard fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No se encontraron órdenes con productos asignados</h5>
                        <p class="text-muted">Intenta ajustar los filtros de búsqueda</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Orden #</th>
                                    <th>Cliente</th>
                                    <th>Estado</th>
                                    <th>Fecha Orden</th>
                                    <th class="text-center">Items</th>
                                    <th class="text-center">Productos</th>
                                    <th class="text-end">Peso Bruto</th>
                                    <th class="text-end">Peso Líquido</th>
                                    <th class="text-center">Bobinas</th>
                                    <th>Productos</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grupos as $grupo): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary">#<?php echo htmlspecialchars($grupo['id_orden_produccion']); ?></strong>
                                            <?php if ($grupo['id_venta']): ?>
                                                <br><small class="text-muted">Venta: <?php echo htmlspecialchars($grupo['id_venta']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($grupo['cliente']); ?></strong>
                                            <?php if ($grupo['tiempo_desde_orden']): ?>
                                                <br><small class="text-muted">hace <?php echo $grupo['tiempo_desde_orden']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $grupo['estado_badge_class']; ?>">
                                                <?php echo htmlspecialchars($grupo['estado_orden']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo $grupo['fecha_orden_formateada']; ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary badge-custom">
                                                <?php echo number_format($grupo['total_items']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info">
                                                <?php echo number_format($grupo['productos_diferentes']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <strong><?php echo $grupo['peso_bruto_formateado']; ?></strong>
                                        </td>
                                        <td class="text-end">
                                            <strong><?php echo $grupo['peso_liquido_formateado']; ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <?php echo number_format($grupo['bobinas_pacote_total']); ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($grupo['productos_lista_corta'] ?? $grupo['productos_lista']); ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-info btn-custom"
                                                title="Ver detalles de la orden"
                                                onclick="verDetallesOrden(<?php echo $grupo['id_orden_produccion']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
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
                                    <strong>Total Órdenes:</strong> <?php echo number_format($resumen['total_ordenes']); ?>
                                </div>
                                <div class="col-md-2">
                                    <strong>Total Items:</strong> <?php echo number_format($resumen['total_items']); ?>
                                </div>
                                <div class="col-md-2">
                                    <strong>Clientes:</strong> <?php echo number_format($resumen['clientes_unicos']); ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Peso Bruto:</strong> <?php echo $resumen['peso_bruto_formateado']; ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Peso Líquido:</strong> <?php echo $resumen['peso_liquido_formateado']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="modal fade" id="detallesOrdenModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-clipboard-list me-2"></i>Detalles de la Orden
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detalles-orden-content">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script src="<?php echo $url_base; ?>secciones/produccion_seguimiento/js/productos_asignados.js"></script>
    <script>
        const PRODUCTOS_ASIGNADOS_CONFIG = <?php echo json_encode($configuracionJS); ?>;
    </script>
</body>

</html>