<?php
include "../config/database/conexionBD.php";
include "auth_functions.php";
requerirLogin();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Denegado</title>
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h4>Acceso Denegado</h4>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <i class="fas fa-exclamation-triangle text-danger" style="font-size: 4rem;"></i>
                        </div>
                        <p class="text-center">Lo sentimos, no tienes permisos suficientes para acceder a esta sección.</p>

                        <?php if (estaLogueado()): ?>
                            <div class="text-center mb-3">
                                <small class="text-muted">
                                    Usuario: <strong><?php echo obtenerNombreUsuario(); ?></strong><br>
                                    Rol actual: <strong><?php echo obtenerRolUsuario(); ?></strong>
                                </small>
                            </div>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <a href="<?php echo $url_base; ?>index.php" class="btn btn-primary">Volver al Inicio</a>
                            <a href="<?php echo $url_base; ?>auth/logout.php" class="btn btn-secondary">Cerrar Sesión</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>

</html>