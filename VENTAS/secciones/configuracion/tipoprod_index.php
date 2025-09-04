 <?php
    include "../../config/database/conexionBD.php";
    include "../../auth/verificar_sesion.php";
    requerirRol(['1']);

    if (file_exists("controllers/configController.php")) {
        include "controllers/configController.php";
    } else {
        die("Error: No se pudo cargar el controlador de configuración.");
    }

    $controller = new ConfigController($conexion, $url_base);

    if ($controller->handleApiRequest()) {
        exit();
    }

    $mensajeError = '';
    $mensaje = '';

    if (isset($_GET['eliminar'])) {
        $resultado = $controller->procesarTiposProducto('eliminar', ['id' => $_GET['eliminar']]);

        if ($resultado['success']) {
            $mensaje = $resultado['mensaje'];
        } else {
            $mensajeError = $resultado['error'] ?? 'Error al eliminar el tipo de producto';
        }
    }

    $datosVista = $controller->obtenerDatosVista();
    $tipos = $datosVista['tipos_producto'] ?? [];


    $usuario_actual = $datosVista['usuario_actual'] ?? 'Usuario';
    $configuracionJS = $controller->obtenerConfiguracionJS();
    $breadcrumb_items = ['Configuracion', 'Tipo de Producto'];
    $item_urls = [
        $url_base . 'secciones/configuracion/index.php',
    ];
    ?>
 <!DOCTYPE html>
 <html lang="es">

 <head>
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <title>Gestión de Tipos de Producto</title>
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
     <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
     <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
     <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/configuracion/utils/styles.css">
 </head>

 <body>
     <?php include $path_base . "components/navbar.php"; ?>
     <div class="container-fluid my-4">
         <?php if (!empty($mensaje)): ?>
             <div class="alert alert-success alert-dismissible fade show">
                 <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($mensaje) ?>
                 <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
             </div>
         <?php endif; ?>

         <?php if (!empty($mensajeError)): ?>
             <div class="alert alert-danger alert-dismissible fade show">
                 <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($mensajeError) ?>
                 <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
             </div>
         <?php endif; ?>

         <?php if (isset($_GET['mensaje'])): ?>
             <div class="alert alert-success alert-dismissible fade show" role="alert">
                 <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_GET['mensaje']); ?>
                 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
             </div>
         <?php endif; ?>

         <?php if (isset($_GET['error'])): ?>
             <div class="alert alert-danger alert-dismissible fade show" role="alert">
                 <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($_GET['error']); ?>
                 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
             </div>
         <?php endif; ?>

         <div class="card">
             <div class="card-header d-flex justify-content-between align-items-center">
                 <h4 class="mb-0"><i class="fas fa-tags me-2"></i>Gestión de Tipos de Producto</h4>
                 <a href="<?php echo $url_base; ?>secciones/configuracion/tipoprod_registrar.php" class="btn btn-primary">
                     <i class="fas fa-plus me-2"></i>Nuevo Tipo
                 </a>
             </div>
             <div class="card-body">
                 <div class="table-responsive">
                     <table class="table table-striped table-hover">
                         <thead>
                             <tr>
                                 <th><i class="fas fa-hashtag me-1"></i>ID</th>
                                 <th><i class="fas fa-file-alt me-1"></i>Descripción</th>
                                 <th><i class="fas fa-cogs me-1"></i>Acciones</th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php if (count($tipos) > 0): ?>
                                 <?php foreach ($tipos as $tipo): ?>
                                     <tr>
                                         <td><?php echo htmlspecialchars($tipo['id']); ?></td>
                                         <td><?php echo htmlspecialchars($tipo['desc']); ?></td>
                                         <td>
                                             <div class="btn-group">
                                                 <a href="<?php echo $url_base; ?>secciones/configuracion/tipoprod_editar.php?id=<?php echo $tipo['id']; ?>" class="btn btn-warning btn-sm" title="Editar Tipo">
                                                     <i class="fas fa-edit"></i>
                                                 </a>
                                                 <button onclick="ConfigApp.confirmarEliminarTipoProducto(<?php echo $tipo['id']; ?>)" class="btn btn-danger btn-sm" title="Eliminar Tipo">
                                                     <i class="fas fa-trash"></i>
                                                 </button>
                                             </div>
                                         </td>
                                     </tr>
                                 <?php endforeach; ?>
                             <?php else: ?>
                                 <tr>
                                     <td colspan="3" class="text-center py-4">
                                         <i class="fas fa-inbox text-muted mb-2" style="font-size: 2rem;"></i>
                                         <div>No hay tipos de producto registrados</div>
                                     </td>
                                 </tr>
                             <?php endif; ?>
                         </tbody>
                     </table>
                 </div>
             </div>
         </div>
     </div>

     <div class="modal fade" id="confirmarEliminarModal" tabindex="-1" aria-hidden="true">
         <div class="modal-dialog">
             <div class="modal-content">
                 <div class="modal-header bg-danger text-white">
                     <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Confirmar Eliminación</h5>
                     <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                 </div>
                 <div class="modal-body">
                     <p class="mb-0">¿Está seguro que desea eliminar este tipo de producto? Esta acción no se puede deshacer.</p>
                 </div>
                 <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                         <i class="fas fa-times me-2"></i>Cancelar
                     </button>
                     <button type="button" id="btn-confirmar-eliminar" class="btn btn-danger">
                         <i class="fas fa-trash me-2"></i>Eliminar
                     </button>
                 </div>
             </div>
         </div>
     </div>

     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
     <script>
         const CONFIG_JS = <?php echo json_encode($configuracionJS); ?>;
     </script>
     <script src="js/config.js"></script>
 </body>

 </html>