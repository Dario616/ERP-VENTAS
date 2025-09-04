<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '4']); // Solo admins y PCP

// Verificar que existe el directorio y archivo del controller
if (file_exists("controllers/pcpController.php")) {
    include "controllers/pcpController.php";
} else {
    die("Error: No se pudo cargar el controlador de PCP.");
}

// Instanciar el controller
$controller = new PcpController($conexion, $url_base);

// Manejar peticiones API
if ($controller->handleApiRequest()) {
    exit();
}

// Obtener datos para la vista
$datosVista = $controller->obtenerDatosVista('dashboard');
$configuracionJS = $controller->obtenerConfiguracionJS();

// Log de actividad
$controller->logActividad('Acceso dashboard PCP');
$breadcrumb_items = ['Gestion PCP'];
$item_urls = [];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($datosVista['titulo']); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Estilos personalizados -->
    <link href="<?php echo $url_base; ?>secciones/sectorPcp/utils/styles.css" rel="stylesheet">

</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-5">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-industry me-2"></i><?php echo htmlspecialchars($datosVista['titulo']); ?></h4>
                        <div>
                            <a href="<?php echo $url_base; ?>index.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-arrow-left me-1"></i>Volver al Inicio
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mt-4">
                            <!-- Ventas Aprobadas -->
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-primary text-white rounded-circle mx-auto mb-3">
                                            <i class="fas fa-check-circle fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Ventas Aprobadas</h5>
                                        <p class="card-text flex-grow-1">Procesar ventas aprobadas por el sector contable para producción o expedición.</p>
                                        <a href="<?php echo $url_base; ?>secciones/sectorPcp/index.php" class="btn btn-primary">
                                            <i class="fas fa-arrow-right me-1"></i>Acceder
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <!-- Devueltos -->
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-warning text-white rounded-circle mx-auto mb-3">
                                            <i class="fas fa-undo-alt fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Devueltos</h5>
                                        <p class="card-text flex-grow-1">Gestionar los productos devueltos y procesar las devoluciones para su correcta administración.</p>
                                        <a href="<?php echo $url_base; ?>secciones/sectorPcp/devuelto_pcp.php" class="btn btn-warning">
                                            <i class="fas fa-arrow-right me-1"></i>Acceder
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <!-- Historial de Acciones -->
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-success text-white rounded-circle mx-auto mb-3">
                                            <i class="fas fa-history fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Historial de Acciones</h5>
                                        <p class="card-text flex-grow-1">Consultar el registro histórico de todas las acciones realizadas por el sector PCP.</p>
                                        <a href="<?php echo $url_base; ?>secciones/sectorPcp/historial.php" class="btn btn-success">
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript personalizado -->
    <script src="<?php echo $url_base; ?>secciones/sectorPcp/js/pcp.js"></script>
    <script>
        // Configuración global para JavaScript
        const PCP_CONFIG = <?php echo json_encode($configuracionJS); ?>;
    </script>

    <style>
        /* Estilos adicionales para el panel de ventas */
        .icon-wrapper {
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            margin-left: auto;
            margin-right: auto;
        }

        .card {
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
        }

        .alert-info {
            border-left: 5px solid #17a2b8;
        }

        /* Estilo para las tarjetas de resumen */
        .card-body h4 {
            font-size: 1.8rem;
            transition: transform 0.2s ease;
        }
    </style>
</body>

</html>