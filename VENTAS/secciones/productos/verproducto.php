<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();

if (file_exists("controllers/ProductoController.php")) {
    include "controllers/ProductoController.php";
} else {
    die("Error: No se pudo cargar el controlador de productos.");
}

$controller = new ProductoController($conexion, $url_base);

try {
    $productos_por_tipo = $controller->obtenerProductosParaCatalogo();

    $total_productos = 0;
    foreach ($productos_por_tipo as $productos_tipo) {
        $total_productos += count($productos_tipo);
    }
} catch (Exception $e) {
    $error = "Error al consultar productos: " . $e->getMessage();
    $productos_por_tipo = [];
    $total_productos = 0;
}

$vista = isset($_GET['vista']) ? $_GET['vista'] : 'tarjetas';

$datosVista = $controller->obtenerDatosVista();
$configuracionJS = $controller->obtenerConfiguracionJS();

$productos_planos = [];
foreach ($productos_por_tipo as $tipo => $productos_tipo) {
    foreach ($productos_tipo as $producto) {
        $productos_planos[] = $producto;
    }
}

$controller->logActividad('Ver catálogo de productos');
$breadcrumb_items = ['Productos'];
$item_urls = [];
$additional_css = [$url_base . 'secciones/productos/utils/styles.css'];
include $path_base . "components/head.php";
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h4 class="mb-0"><i class="fas fa-box me-2"></i>Catálogo de Productos</h4>
                        <p class="text-muted mb-0">Total: <?php echo $total_productos; ?> productos en <?php echo count($productos_por_tipo); ?> categorías</p>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-end align-items-center gap-3">
                            <div class="flex-grow-1">
                                <input type="text" id="searchInput" class="form-control search-box" placeholder="Buscar productos...">
                            </div>
                            <div class="btn-group" role="group">
                                <a href="?vista=lista" class="btn btn-outline-primary btn-toggle <?php echo $vista === 'lista' ? 'active' : ''; ?>">
                                    <i class="fas fa-list"></i>
                                </a>
                                <a href="?vista=tarjetas" class="btn btn-outline-primary btn-toggle <?php echo $vista === 'tarjetas' ? 'active' : ''; ?>">
                                    <i class="fas fa-th"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="filtros-tipo">
            <div class="d-flex flex-wrap align-items-center">
                <span class="me-3 fw-semibold"><i class="fas fa-filter me-2"></i>Filtrar por tipo:</span>
                <button type="button" class="btn btn-outline-primary btn-tipo-filter active" data-tipo="todos">
                    <i class="fas fa-th-large me-1"></i>Todos
                </button>
                <?php foreach ($productos_por_tipo as $tipo => $productos_tipo): ?>
                    <button type="button" class="btn btn-outline-primary btn-tipo-filter" data-tipo="<?php echo htmlspecialchars($tipo); ?>">
                        <?php echo htmlspecialchars($tipo); ?> (<?php echo count($productos_tipo); ?>)
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="contenidoProductos">
            <?php foreach ($productos_por_tipo as $tipo => $productos_tipo): ?>
                <div class="section-productos" data-tipo="<?php echo htmlspecialchars($tipo); ?>">
                    <div class="tipo-header d-flex justify-content-between align-items-center"
                        data-bs-toggle="collapse"
                        data-bs-target="#productos-<?php echo md5($tipo); ?>"
                        aria-expanded="true">
                        <div>
                            <i class="fas fa-layer-group"></i>
                            <?php echo htmlspecialchars($tipo); ?>
                            <span class="contador-productos"><?php echo count($productos_tipo); ?> productos</span>
                        </div>
                        <i class="fas fa-chevron-down collapse-toggle" data-bs-toggle="collapse" data-bs-target="#productos-<?php echo md5($tipo); ?>"></i>
                    </div>

                    <div class="collapse show" id="productos-<?php echo md5($tipo); ?>">
                        <?php if ($vista === 'lista'): ?>
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Código</th>
                                                    <th>Descripción</th>
                                                    <th>Código de Barras</th>
                                                    <th class="text-end">Cantidad</th>
                                                    <th class="text-end">NCM</th>
                                                    <th class="text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($productos_tipo as $producto): ?>
                                                    <tr class="producto-row">
                                                        <td><span class="badge badge-custom"><?php echo htmlspecialchars($producto['id']); ?></span></td>
                                                        <td class="fw-semibold"><?php echo htmlspecialchars($producto['descripcion']); ?></td>
                                                        <td><?php echo htmlspecialchars($producto['codigobr']); ?></td>
                                                        <td class="text-end"><?php echo $producto['cantidad_formateada']; ?></td>
                                                        <td class="text-end"><?php echo htmlspecialchars($producto['ncm_formateado']); ?></td>
                                                        <td class="text-center">
                                                            <button class="btn btn-primary btn-sm" onclick="verDetalles(<?php echo $producto['id']; ?>)">
                                                                <i class="fas fa-eye me-1"></i>Ver
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($productos_tipo as $producto): ?>
                                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4 producto-card">
                                        <div class="card product-card h-100">
                                            <div class="product-image">
                                                <?php if (!empty($producto['base64img'])): ?>
                                                    <img src="data:<?php echo htmlspecialchars($producto['tipoimg']); ?>;base64,<?php echo $producto['base64img']; ?>"
                                                        alt="<?php echo htmlspecialchars($producto['descripcion']); ?>"
                                                        style="width: 100%; height: 100%; object-fit: cover;">
                                                <?php else: ?>
                                                    <i class="fas fa-box"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="product-info">
                                                <h6 class="product-title"><?php echo htmlspecialchars($producto['descripcion']); ?></h6>
                                                <div class="product-details">
                                                    <p class="mb-1"><strong>Código:</strong> <span class="product-value"><?php echo htmlspecialchars($producto['id']); ?></span></p>
                                                    <p class="mb-2"><strong>Cantidad:</strong>
                                                        <span class="product-value"><?php echo $producto['cantidad_formateada']; ?></span>
                                                    </p>
                                                </div>
                                                <div class="product-action text-center">
                                                    <button class="btn btn-primary btn-sm" onclick="verDetalles(<?php echo $producto['id']; ?>)">
                                                        <i class="fas fa-eye me-1"></i>Ver Detalles
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($productos_por_tipo)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted">No hay productos registrados</h4>
                    <p class="text-muted">Aún no se han agregado productos al catálogo</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="detallesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Detalles del Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalContent">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script src="<?php echo $url_base; ?>secciones/productos/js/ProductosManager.js"></script>
    <script>
        const PRODUCTOS_CONFIG = <?php echo json_encode($configuracionJS); ?>;

        const productos = <?php echo json_encode($productos_planos); ?>;

        document.addEventListener("DOMContentLoaded", function() {
            ProductosManager.catalogPage.init(productos);
        });
    </script>
</body>

</html>