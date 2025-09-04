<?php
include "auth/verificar_sesion.php";
include "config/database/conexionBD.php";
$page_title = 'Inicio';
$show_breadcrumb = false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Ventas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>style.css">
</head>
<body>
    <?php include "components/navbar.php"; ?>
    <div class="container-fluid my-3">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>
                            <?php
                            if (tieneRol(['1']) && !tieneRol(['2', '3', '4'])) {
                                echo "Panel de Control Administrador";
                            } elseif (tieneRol(['2'])) {
                                echo "Panel de Control Sector Vendedor";
                            } elseif (tieneRol(['3'])) {
                                echo "Panel de Control Sector Contable";
                            } elseif (tieneRol(['4'])) {
                                echo "Panel de Control Sector PCP";
                            } else {
                                echo "Panel de Control";
                            }
                            ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row mt-4">
                            <?php if (tieneRol(['1', '2'])): 
                            ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body text-center d-flex flex-column">
                                            <div class="icon-wrapper bg-success text-white rounded-circle mx-auto mb-3">
                                                <i class="fas fa-cash-register fa-2x"></i>
                                            </div>
                                            <h5 class="card-title">Sector Ventas</h5>
                                            <p class="card-text flex-grow-1">Registrar ventas diarias, consultar historial de transacciones y generar reportes de ventas.</p>
                                            <a href="<?php echo $url_base; ?>secciones/ventas/main.php" class="btn btn-success">
                                                <i class="fas fa-arrow-right me-1"></i>Acceder
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (tieneRol(['1', '3'])):
                            ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body text-center d-flex flex-column">
                                            <div class="icon-wrapper bg-primary text-white rounded-circle mx-auto mb-3">
                                                <i class="fas fa-calculator fa-2x"></i>
                                            </div>
                                            <h5 class="card-title">Sector Contable</h5>
                                            <p class="card-text flex-grow-1">Gestionar la contabilidad, revisar balances, informes financieros y control de gastos.</p>
                                            <a href="<?php echo $url_base; ?>secciones/contable/main.php" class="btn btn-primary">
                                                <i class="fas fa-arrow-right me-1"></i>Acceder
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (tieneRol(['1', '4'])): 
                            ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body text-center d-flex flex-column">
                                            <div class="icon-wrapper bg-warning text-white rounded-circle mx-auto mb-3">
                                                <i class="fas fa-wrench fa-2x"></i>
                                            </div>
                                            <h5 class="card-title">Sector PCP</h5>
                                            <p class="card-text flex-grow-1">Planificación y Control de Producción, gestión de inventarios y control de procesos.</p>
                                            <a href="<?php echo $url_base; ?>secciones/sectorPcp/main.php" class="btn btn-warning">
                                                <i class="fas fa-arrow-right me-1"></i>Acceder
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if (tieneRol(['1', '4'])): 
                            ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body text-center d-flex flex-column">
                                            <div class="icon-wrapper bg-danger text-white rounded-circle mx-auto mb-3">
                                                <i class="fas fa-industry fa-2x"></i>
                                            </div>
                                            <h5 class="card-title">Sector Producción</h5>
                                            <p class="card-text flex-grow-1">Gestión de procesos productivos, control de calidad y seguimiento de fabricación.</p>
                                            <a href="<?php echo $url_base; ?>secciones/produccion/main.php" class="btn btn-danger">
                                                <i class="fas fa-arrow-right me-1"></i>Acceder
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-info text-white rounded-circle mx-auto mb-3">
                                            <i class="fas fa-box fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Ver Productos</h5>
                                        <p class="card-text flex-grow-1">Consultar catálogo completo de productos disponibles en el sistema.</p>
                                        <a href="<?php echo $url_base; ?>secciones/productos/verproducto.php" class="btn btn-info text-white">
                                            <i class="fas fa-arrow-right me-1"></i>Acceder
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-secondary text-white rounded-circle mx-auto mb-3">
                                            <i class="fas fa-chart-bar fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Relatorio</h5>
                                        <p class="card-text flex-grow-1">Genera y consulta reportes detallados del sistema con métricas y estadísticas actualizadas.</p>
                                        <a href="<?php echo $url_base; ?>secciones/relatorio/relatorio.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-right me-1"></i>Acceder
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-orange rounded-circle mx-auto mb-3">
                                            <i class="fas fa-industry fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Seguimiento Producción</h5>
                                        <p class="card-text flex-grow-1">Consulta el estado y avance de los procesos de producción en tiempo real.</p>
                                        <a href="<?php echo $url_base; ?>secciones/produccion_seguimiento/main.php" class="btn bg-orange">
                                            <i class="fas fa-arrow-right me-1"></i>Acceder
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php if (tieneRol(['1', '2'])):
                            ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body text-center d-flex flex-column">
                                            <div class="icon-wrapper bg-dark text-white rounded-circle mx-auto mb-3">
                                                <i class="fas fa-cog fa-2x"></i>
                                            </div>
                                            <h5 class="card-title">Configuración</h5>
                                            <p class="card-text flex-grow-1">Configurar parámetros del sistema, personalizar la aplicación y ajustes generales.</p>
                                            <a href="<?php echo $url_base; ?>secciones/configuracion/index.php" class="btn btn-dark">
                                                <i class="fas fa-arrow-right me-1"></i>Acceder
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $url_base; ?>config/notificacion/sistema-notificaciones.js"></script>
</body>
</html>