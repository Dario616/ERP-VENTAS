<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '3']);

if (file_exists("controllers/ContableController.php")) {
    include "controllers/ContableController.php";
} else {
    die("Error: No se pudo cargar el controlador contable.");
}

$controller = new ContableController($conexion, $url_base);

if (!$controller->verificarPermisos()) {
    header("Location: " . $url_base . "index.php?error=No tienes permisos para acceder a esta sección");
    exit();
}

if ($controller->handleApiRequest()) {
    exit();
}

$estadisticas = $controller->obtenerEstadisticas();

$datosVista = $controller->obtenerDatosVista('Gestión Contable');
$configuracionJS = $controller->obtenerConfiguracionJS();

$controller->logActividad('Acceso dashboard contable');
$breadcrumb_items = ['Sector Contable'];
$item_urls = [];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Contable</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="<?php echo $url_base; ?>secciones/contable/utils/styles.css" rel="stylesheet">

</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-5">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-cash-register me-2"></i>Gestión Contable</h4>
                        <a href="<?php echo $url_base; ?>index.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-arrow-left me-1"></i>Volver al Inicio
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row mt-4">
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-danger text-white rounded-circle mx-auto mb-3">
                                            <i class="fas fa-clock fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Autorización Pendientes</h5>
                                        <p class="card-text flex-grow-1">Revisar y autorizar las transacciones pendientes de aprobación contable.</p>
                                        <a href="<?php echo $url_base; ?>secciones/contable/index.php" class="btn btn-danger">
                                            <i class="fas fa-arrow-right me-1"></i>Acceder
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-success text-white rounded-circle mx-auto mb-3">
                                            <i class="fas fa-money-check-alt fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Cuentas por Cobrar</h5>
                                        <p class="card-text flex-grow-1">Gestionar cuotas de ventas a crédito, registrar pagos y conciliar cuentas.</p>
                                        <a href="<?php echo $url_base; ?>secciones/contable/cuentas_cobrar.php" class="btn btn-success">
                                            <i class="fas fa-arrow-right me-1"></i>Acceder
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-primary text-white rounded-circle mx-auto mb-3">
                                            <i class="fas fa-check-circle fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Pagados</h5>
                                        <p class="card-text flex-grow-1">Consultar cuentas ya pagadas y generar reportes de pagos realizados.</p>
                                        <a href="<?php echo $url_base; ?>secciones/contable/pagados.php" class="btn btn-primary">
                                            <i class="fas fa-arrow-right me-1"></i>Acceder
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-warning text-dark rounded-circle mx-auto mb-3">
                                            <i class="fas fa-undo-alt fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Devoluciones de PCP</h5>
                                        <p class="card-text flex-grow-1">Revisar ventas devueltas por el sector PCP para su reevaluación.</p>
                                        <a href="<?php echo $url_base; ?>secciones/contable/devoluciones_pcp.php" class="btn btn-warning text-dark">
                                            <i class="fas fa-arrow-right me-1"></i>Acceder
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-secondary text-white rounded-circle mx-auto mb-3">
                                            <i class="fas fa-file-invoice-dollar fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Reportes de Pagos</h5>
                                        <p class="card-text flex-grow-1">Generar reportes detallados de pagos realizados por clientes específicos.</p>
                                        <a href="<?php echo $url_base; ?>secciones/contable/relatorio_pagos.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-right me-1"></i>Acceder
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-info text-white rounded-circle mx-auto mb-3">
                                            <i class="fas fa-history fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Historial de Acciones</h5>
                                        <p class="card-text flex-grow-1">Consultar el registro histórico de todas las acciones realizadas por los sectores.</p>
                                        <a href="<?php echo $url_base; ?>secciones/contable/historial.php" class="btn btn-info">
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

    <script src="<?php echo $url_base; ?>config/notificacion/sistema-notificaciones.js"></script>

    <script>
        const CONTABLE_CONFIG = <?php echo json_encode($configuracionJS); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            function actualizarHora() {
                const now = new Date();
                const horaElement = document.getElementById('hora-actual');
                if (horaElement) {
                    horaElement.textContent = now.toLocaleTimeString('es-PY');
                }
            }

            setInterval(actualizarHora, 1000);
            const btnActualizar = document.getElementById('btn-actualizar');
            if (btnActualizar) {
                btnActualizar.addEventListener('click', function() {
                    this.querySelector('i').classList.add('fa-spin');
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                });
            }

            const tarjetas = document.querySelectorAll('.card');
            tarjetas.forEach(tarjeta => {
                tarjeta.addEventListener('mouseenter', function() {});
            });

            console.log('Dashboard contable inicializado correctamente');
        });
    </script>
</body>

</html>