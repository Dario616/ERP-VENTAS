<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
if (file_exists("controllers/stockController.php")) {
    include "controllers/stockController.php";
} else {
    die("Error: No se pudo cargar el controlador de stock.");
}
$breadcrumb_items = ['STOCK AGREGADO'];
$item_urls = [];
$additional_css = [$url_base . 'secciones/stock/utils/index.css'];
include $path_base . "components/head.php";
?>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="main-container">
        <div class="container-fluid">
            <?php if (!empty($mensajeError)): ?>
                <div class="alert alert-danger alert-custom">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($mensajeError); ?>
                </div>
            <?php endif; ?>
            <div class="filter-section">
                <form class="filtros-form" method="GET">
                    <div class="row align-items-end">
                        <div class="col-lg-3 col-md-4 mb-3">
                            <label for="filtroProducto" class="form-label">
                                <i class="fas fa-search me-1"></i>Buscar Producto
                            </label>
                            <input
                                type="text"
                                id="filtroProducto"
                                name="producto"
                                class="form-control"
                                placeholder="Escriba el nombre del producto..."
                                value="<?php echo htmlspecialchars($filtrosAplicados['producto'] ?? ''); ?>"
                                autocomplete="off">
                        </div>
                        <div class="col-lg-2 col-md-3 mb-3">
                            <label for="filtroTipo" class="form-label">
                                <i class="fas fa-tags me-1"></i>Tipo de Producto
                            </label>
                            <select id="filtroTipo" name="tipo" class="form-select">
                                <option value="">Todos los tipos</option>
                                <?php foreach ($tipos_producto as $tipo): ?>
                                    <option value="<?php echo htmlspecialchars($tipo); ?>"
                                        <?php echo ($filtrosAplicados['tipo'] ?? '') === $tipo ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($tipo)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3 mb-3">
                            <label for="stockCompleto" class="form-label">
                                <i class="fas fa-eye me-1"></i>Mostrar Stock
                            </label>
                            <select id="stockCompleto" name="stock_completo" class="form-select">
                                <option value="0" <?php echo ($filtrosAplicados['stock_completo'] ?? '0') === '0' ? 'selected' : ''; ?>>Solo disponible</option>
                                <option value="1" <?php echo ($filtrosAplicados['stock_completo'] ?? '0') === '1' ? 'selected' : ''; ?>>Stock completo</option>
                            </select>
                        </div>
                        <div class="col-lg-5 col-md-12 mb-3">
                            <div class="btn-group w-100" role="group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Buscar
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="limpiarFiltros()">
                                    <i class="fas fa-eraser me-1"></i>Limpiar
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-body p-0">
                            <?php if ($datosStock && !empty($datosStock['datos'])): ?>
                                <div class="table-responsive">
                                    <table class="table stock-table mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="min-width: 300px;">Producto</th>
                                                <th style="width: 100px;">Tipo</th>
                                                <th style="width: 100px;">Categoria x Bob/Paq</th>
                                                <th style="width: 80px;">Total</th>
                                                <th style="width: 100px;">Disponible</th>
                                                <th style="width: 100px;">Reservado</th>
                                                <th style="width: 100px;">Despachado</th>
                                                <th style="width: 120px;">Cant. Paquetes</th>
                                                <th style="width: 120px;">Última Act.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($datosStock['datos'] as $index => $producto): ?>
                                                <tr class="fade-in" style="animation-delay: <?php echo $index * 50; ?>ms">
                                                    <td>
                                                        <div class="producto-info">
                                                            <div class="producto-nombre fw-medium">
                                                                <?php echo htmlspecialchars($producto['nombre_producto']); ?>
                                                            </div>
                                                            <?php if (!empty($producto['gramatura']) || !empty($producto['largura']) || !empty($producto['metragem'])): ?>
                                                                <div class="producto-specs">
                                                                    <small class="text-muted">
                                                                        <?php if (!empty($producto['gramatura'])): ?>
                                                                            <?php echo $producto['gramatura']; ?>g/m²
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($producto['largura'])): ?>
                                                                            | <?php echo $producto['largura']; ?>cm
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($producto['metragem'])): ?>
                                                                            | <?php echo $producto['metragem']; ?>m
                                                                        <?php endif; ?>
                                                                    </small>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>

                                                    <td>
                                                        <span class="badge badge-tipo"
                                                            style="background-color: <?php echo $producto['configuracion_tipo']['color']; ?>;">
                                                            <?php echo htmlspecialchars($producto['tipo_producto']); ?>
                                                        </span>
                                                    </td>

                                                    <td class="text-center">
                                                        <span class="badge bg-info">
                                                            <?php echo $producto['bobinas_pacote_formateado']; ?>
                                                        </span>
                                                    </td>

                                                    <td class="text-center fw-medium">
                                                        <?php echo $producto['cantidad_total_formateada']; ?>
                                                    </td>

                                                    <td class="text-center">
                                                        <div class="cantidad-container">
                                                            <?php if (($producto['cantidad_disponible'] ?? 0) === 0): ?>
                                                                <span class="text-warning">
                                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                                    0
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="fw-medium text-success">
                                                                    <?php echo $producto['cantidad_disponible_formateada']; ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>

                                                    <td class="text-center">
                                                        <?php if (($producto['cantidad_reservada'] ?? 0) > 0): ?>
                                                            <span class="fw-medium text-warning">
                                                                <?php echo $producto['cantidad_reservada_formateada']; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">0</span>
                                                        <?php endif; ?>
                                                    </td>

                                                    <td class="text-center">
                                                        <?php if (($producto['cantidad_despachada'] ?? 0) > 0): ?>
                                                            <span class="fw-medium text-primary">
                                                                <?php echo $producto['cantidad_despachada_formateada']; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">0</span>
                                                        <?php endif; ?>
                                                    </td>

                                                    <td class="text-center">
                                                        <?php if (($producto['cantidad_paquetes'] ?? 0) > 0): ?>
                                                            <span class="fw-medium text-primary">
                                                                <?php echo $producto['cantidad_paquetes_formateada']; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">0</span>
                                                        <?php endif; ?>
                                                    </td>

                                                    <td class="text-center">
                                                        <small class="text-muted">
                                                            <?php echo date('d/m/Y'); ?>
                                                        </small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="card-footer bg-light">
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center mb-0">
                                        </ul>
                                    </nav>
                                </div>

                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-database text-muted mb-3" style="font-size: 3rem;"></i>
                                    <h4 class="text-muted">No hay productos en el stock agregado</h4>
                                    <p class="text-muted">
                                        <?php if (!empty($filtrosAplicados['producto']) || !empty($filtrosAplicados['tipo'])): ?>
                                            No se encontraron productos que coincidan con los filtros aplicados.
                                        <?php else: ?>
                                            No se encontraron productos en el sistema de stock agregado.
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!empty($filtrosAplicados['producto']) || !empty($filtrosAplicados['tipo'])): ?>
                                        <button class="btn btn-outline-primary" onclick="limpiarFiltros()">
                                            <i class="fas fa-eraser me-1"></i>Limpiar Filtros
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button class="btn btn-primary btn-floating" onclick="aplicarFiltros()" title="Actualizar datos">
        <i class="fas fa-sync-alt"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const STOCK_CONFIG = <?php echo json_encode($configuracionJS); ?>;
    </script>
    <script src="js/stock.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.stock-table tbody tr.fade-in');
            rows.forEach((row, index) => {
                setTimeout(() => {
                    row.classList.add('visible');
                }, index * 50);
            });
            console.log('🎯 Sistema de Stock Agregado v2.0 cargado correctamente');
            console.log('📊 Configuración:', STOCK_CONFIG);
        });
    </script>
</body>
