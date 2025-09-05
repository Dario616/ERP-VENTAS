<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1']);
include "controllers/nuevaordenController.php";
$breadcrumb_items = ['NUEVA ORDEN'];
$item_urls = [];
$additional_css = [$url_base . 'secciones/produccion/utils/nueva_orden_manual.css'];
include $path_base . "components/head.php";
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="main-container">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-5 col-xl-4">
                    <form method="POST" id="formNuevaOrden">
                        <input type="hidden" name="crear_orden" value="1">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Nueva Orden de Producción para Stock</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="product-input-container">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="descripcion" name="descripcion" required
                                                placeholder="Buscar producto existente"
                                                autocomplete="off">
                                            <label for="descripcion">Buscar Producto Existente *</label>
                                        </div>
                                        <div class="loading-indicator" id="loadingIndicator">
                                            <i class="fas fa-spinner fa-spin me-1"></i>Buscando productos...
                                        </div>
                                        <div class="product-suggestions" id="productSuggestions"></div>
                                    </div>

                                    <div class="form-text mt-2">
                                        <i class="fas fa-database me-1"></i>
                                        <strong>Solo productos registrados en el sistema.</strong>
                                    </div>

                                    <div class="required-product-notice" id="requiredProductNotice">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Producto requerido:</strong> Solo se pueden crear órdenes con productos existentes en la base de datos.
                                    </div>
                                    <div id="previewBox" class="preview-box">
                                        <h6 id="previewTitle"><i class="fas fa-database me-2"></i>Producto Seleccionado:</h6>
                                        <div id="statusIndicator"></div>
                                        <div id="productDetails"></div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-6 mb-3" id="unidadMedidaContainer" style="display: none;">
                                        <div class="form-floating position-relative">
                                            <select class="form-control" id="unidad_medida" name="unidad_medida" required>
                                                <option value="">Seleccionar...</option>
                                            </select>
                                            <label for="unidad_medida" id="labelUnidadMedida">Unidad *</label>
                                            <div class="unidades-loading" id="unidadesLoading">
                                                <i class="fas fa-spinner fa-spin me-1"></i>Cargando...
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="form-floating">
                                            <input type="number" class="form-control" id="cantidad" name="cantidad" step="0.01" min="0.01" required placeholder="Cantidad">
                                            <label for="cantidad">Cantidad *</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-floating">
                                        <textarea class="form-control" id="observaciones" name="observaciones" style="height: 80px" placeholder="Observaciones"></textarea>
                                        <label for="observaciones">Observaciones</label>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-create-order" id="btnCrearOrden" disabled>
                                    <i class="fas fa-cogs me-2"></i>Crear Orden de Producción
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-lg-7 col-xl-8">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="fas fa-list-alt me-2"></i>Órdenes de Producción Recientes</h6>
                                <div class="input-group input-group-sm" style="width: 200px;">
                                    <input type="number" id="filterOrden" class="form-control" placeholder="Filtrar # Orden"
                                        value="<?php echo htmlspecialchars($_GET['orden'] ?? ''); ?>">
                                    <button class="btn btn-outline-secondary" id="btnFiltrar">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" onclick="cargarOrdenes(1)">
                                    <i class="fas fa-sync-alt me-1"></i>Actualizar
                                </button>
                            </div>
                        </div>
                        <div class="orders-container" id="ordersContainer">
                            <div class="orders-loading" id="ordersLoading">
                                <i class="fas fa-spinner fa-spin me-2"></i>Cargando órdenes...
                            </div>
                            <div id="ordersList"></div>
                        </div>
                        <div class="pagination-container" id="paginationContainer" style="display: none;">
                            <nav>
                                <ul class="pagination pagination-sm" id="pagination"></ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const ITEMS_POR_PAGINA = <?php echo $items_por_pagina; ?>;
        const TIPOS_SOPORTADOS = <?php echo json_encode($tipos_soportados); ?>;
        const USUARIO_ACTUAL = "<?php echo htmlspecialchars($_SESSION['nombre']); ?>";
        const URL_BASE = "<?php echo $url_base; ?>";
    </script>
    <script src="js/nueva-orden.js"></script>
</body>

</html>