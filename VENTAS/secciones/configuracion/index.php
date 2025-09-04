<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();

if (file_exists("controllers/configController.php")) {
    include "controllers/configController.php";
} else {
    die("Error: No se pudo cargar el controlador de configuración.");
}
$controller = new ConfigController($conexion, $url_base);
$datosVista = $controller->obtenerDatosVista();
$usuario_actual = $datosVista['usuario_actual'] ?? 'Usuario';
$breadcrumb_items = ['Configuracion'];
$item_urls = [];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/configuracion/utils/styles.css">
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-5">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-tools me-2"></i>Configuraciones del Sistema</h4>
                    </div>
                    <div class="card-body">
                        <div class="row mt-4">
                            <?php if (tieneRol('1')): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body text-center d-flex flex-column">
                                            <div class="icon-wrapper bg-primary text-white rounded-circle mx-auto mb-3">
                                                <i class="fas fa-tags fa-2x"></i>
                                            </div>
                                            <h5 class="card-title">Tipos de Producto</h5>
                                            <p class="card-text flex-grow-1">Gestiona las categorías y tipos de productos del sistema</p>
                                            <a href="<?php echo $url_base; ?>secciones/configuracion/tipoprod_index.php" class="btn btn-primary">
                                                <i class="fas fa-arrow-right me-1"></i>Acceder
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (tieneRol('1')): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body text-center d-flex flex-column">
                                            <div class="icon-wrapper bg-info text-white rounded-circle mx-auto mb-3">
                                                <i class="fas fa-ruler fa-2x"></i>
                                            </div>
                                            <h5 class="card-title">Unidades de Medida</h5>
                                            <p class="card-text flex-grow-1">Configura las unidades de medida para productos</p>
                                            <a href="<?php echo $url_base; ?>secciones/configuracion/um_index.php" class="btn btn-info text-white">
                                                <i class="fas fa-arrow-right me-1"></i>Acceder
                                            </a>
                                        </div>
                                    </div>
                                </div>

                            <?php endif; ?>

                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-success text-white rounded-circle mx-auto mb-3">
                                            <i class="fas fa-box fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Productos</h5>
                                        <p class="card-text flex-grow-1">Administra el catálogo de productos y servicios</p>
                                        <a href="<?php echo $url_base; ?>secciones/productos/index.php" class="btn btn-success">
                                            <i class="fas fa-arrow-right me-1"></i>Acceder
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <?php if (tieneRol('1')): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body text-center d-flex flex-column">
                                            <div class="icon-wrapper bg-warning text-white rounded-circle mx-auto mb-3">
                                                <i class="fas fa-users fa-2x"></i>
                                            </div>
                                            <h5 class="card-title">Usuarios</h5>
                                            <p class="card-text flex-grow-1">Gestiona usuarios y permisos del sistema</p>
                                            <a href="<?php echo $url_base; ?>secciones/usuarios/index.php" class="btn btn-warning text-white">
                                                <i class="fas fa-arrow-right me-1"></i>Acceder
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center d-flex flex-column">
                                        <div class="icon-wrapper bg-danger text-white rounded-circle mx-auto mb-3">
                                            <i class="fas fa-handshake fa-2x"></i>
                                        </div>
                                        <h5 class="card-title">Clientes</h5>
                                        <p class="card-text flex-grow-1">Administra la base de datos de clientes</p>
                                        <a href="<?php echo $url_base; ?>secciones/clientes/index.php" class="btn btn-danger">
                                            <i class="fas fa-arrow-right me-1"></i>Acceder
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php if (tieneRol('1')): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body text-center d-flex flex-column">
                                            <div class="icon-wrapper bg-secondary text-white rounded-circle mx-auto mb-3">
                                                <i class="fas fa-money-check-alt fa-2x"></i>
                                            </div>
                                            <h5 class="card-title">Créditos</h5>
                                            <p class="card-text flex-grow-1">Gestiona los tipos de créditos disponibles</p>
                                            <a href="<?php echo $url_base; ?>secciones/configuracion/creditos.php" class="btn btn-secondary text-black">
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>