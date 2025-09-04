<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '3']);
$breadcrumb_items = ['Sector Produccion'];
$item_urls = [];
$additional_css = [$url_base . 'secciones/produccion/utils/styles.css'];
include $path_base . "components/head.php";
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-5">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><i class="fas fa-industry me-2"></i>Gestión de Producción</h4>
                        </div>
                        <a href="<?php echo $url_base; ?>index.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-arrow-left me-1"></i>Volver al Inicio
                        </a>
                    </div>
                    <!-- Módulos principales -->
                    <div class="row mt-4">
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body text-center d-flex flex-column">
                                    <div class="icon-wrapper bg-primary text-white rounded-circle mx-auto mb-3">
                                        <i class="fas fa-clipboard-list fa-2x"></i>
                                    </div>
                                    <h5 class="card-title">Productos para Producción</h5>
                                    <p class="card-text flex-grow-1">Gestionar la lista de productos pendientes para entrar al proceso de producción.</p>
                                    <a href="<?php echo $url_base; ?>secciones/sectorPcp/produccion.php" class="btn btn-primary">
                                        <i class="fas fa-tasks me-1"></i>Ver Pendientes
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body text-center d-flex flex-column position-relative">
                                    <div class="icon-wrapper bg-info text-white rounded-circle mx-auto mb-3">
                                        <i class="fas fa-file-invoice fa-2x"></i>
                                    </div>
                                    <h5 class="card-title">Órdenes de Producción</h5>
                                    <p class="card-text flex-grow-1">Gestionar y dar seguimiento a las órdenes de producción activas y programadas con sistema simplificado.</p>
                                    <a href="<?php echo $url_base; ?>secciones/produccion/ordenes_produccion.php" class="btn btn-info text-white">
                                        <i class="fas fa-clipboard-list me-1"></i>Ver Órdenes
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body text-center d-flex flex-column">
                                    <div class="icon-wrapper bg-warning text-white rounded-circle mx-auto mb-3">
                                        <i class="fas fa-history fa-2x"></i>
                                    </div>
                                    <h5 class="card-title">Historial de Acciones</h5>
                                    <p class="card-text flex-grow-1">Consultar el registro histórico de todas las acciones realizadas por los sectores.</p>
                                    <a href="<?php echo $url_base; ?>secciones/sectorPcp/prodhistorial.php" class="btn btn-warning">
                                        <i class="fas fa-arrow-right me-1"></i>Acceder
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body text-center d-flex flex-column position-relative">
                                    <span class="badge badge-nuevo position-absolute" style="top: 10px; right: 10px; z-index: 10;">NUEVO</span>
                                    <div class="icon-wrapper bg-danger text-white rounded-circle mx-auto mb-3">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                    <h5 class="card-title">Producción Pendiente</h5>
                                    <p class="card-text flex-grow-1">Ver resumen de producción pendiente por sectores y calcular plazos para nuevos pedidos.</p>
                                    <a href="<?php echo $url_base; ?>secciones/produccion/pendientes.php" class="btn btn-danger">
                                        <i class="fas fa-hourglass-half me-1"></i>Ver Pendientes
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if (file_exists("js/produccion.js")): ?>
        <script src="<?php echo $url_base; ?>secciones/produccion/js/produccion.js"></script>
        <script>
            const PRODUCCION_CONFIG = <?php echo json_encode($configuracionJS); ?>;
        </script>
    <?php endif; ?>
</body>

</html>