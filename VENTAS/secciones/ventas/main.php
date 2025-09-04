<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";

requerirRol(['1', '2']);
$breadcrumb_items = ['Sector Ventas'];
$item_urls = [];
$additional_css = [$url_base . 'secciones/ventas/utils/main.css']; 
include $path_base . "components/head.php";
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-cash-register me-2"></i>Gestión de Ventas</h4>
                        <a href="<?php echo $url_base; ?>index.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-arrow-left me-1"></i>Volver al Inicio
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row mt-4">
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-success text-white rounded-circle mx-auto mb-3">
                                            <i class="fas fa-file-invoice-dollar fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Nueva Venta</h5>
                                        <p class="card-text flex-grow-1">Registrar un nuevo presupuesto o venta para un cliente. Seleccione productos y condiciones de pago.</p>
                                        <a href="<?php echo $url_base; ?>secciones/ventas/registrar.php" class="btn btn-success">
                                            <i class="fas fa-plus-circle me-1"></i>Crear Venta
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-info text-white rounded-circle mx-auto mb-3">
                                            <i class="fas fa-chart-bar fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Mis Ventas</h5>
                                        <p class="card-text flex-grow-1">Gestionar mis ventas por cliente, producto o período.</p>
                                        <a href="<?php echo $url_base; ?>secciones/ventas/index.php" class="btn btn-info text-white">
                                            <i class="fas fa-chart-line me-1"></i>Ver mis Ventas
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
                                        <a href="<?php echo $url_base; ?>secciones/ventas/historial.php" class="btn btn-warning">
                                            <i class="fas fa-arrow-right me-1"></i>Acceder
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
</body>

</html>