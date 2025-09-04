<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
$breadcrumb_items = ['Seguimiento Produccion'];
$item_urls = [];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento de Produccion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/produccion_seguimiento/utils/styles.css">
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-tools me-2"></i>Seguimiento Produccion</h4>
                        <a href="<?php echo $url_base; ?>index.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-arrow-left me-1"></i>Volver al Inicio
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row mt-4">
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-dark text-white rounded-circle mx-auto mb-3">
                                            <i class="fas fa-industry fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Producción en Tiempo Real</h5>
                                        <p class="card-text flex-grow-1">Visualice el estado actual de la producción en curso. Monitoreo en tiempo real de procesos y líneas activas.</p>
                                        <a href="<?php echo $url_base; ?>secciones/produccion_seguimiento/productos.php" class="btn btn-dark">
                                            <i class="fas fa-eye me-1"></i>Ver Producción
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-primary text-white rounded-circle mx-auto mb-3">
                                            <i class="fas fa-industry fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Produccion Asignada</h5>
                                        <p class="card-text flex-grow-1">Acceda al detalle de los productos producidos para un cliente en Especifico.
                                        </p>
                                        <a href="<?php echo $url_base; ?>secciones/produccion_seguimiento/productos_asignados.php" class="btn btn-primary">
                                            <i class="fas fa-eye me-1"></i>Ver Producción
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-danger text-white rounded-circle mx-auto mb-3">
                                            <i class="fas fa-truck-loading fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Despachos</h5>
                                        <p class="card-text flex-grow-1">Revise y consulte los despachos realizados. Filtre por cliente, producto o fecha.</p>
                                        <a href="<?php echo $url_base; ?>secciones/despacho_seguimiento/main.php" class="btn btn-danger text-white">
                                            <i class="fas fa-box-open me-1"></i>Ver Despachos
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