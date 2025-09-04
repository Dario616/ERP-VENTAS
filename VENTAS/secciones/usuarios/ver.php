<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
// Solo administradores pueden ver detalles de usuarios
requerirRol('1');

// Verificar que se proporcione un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php?error=ID de usuario no válido");
    exit();
}

$id = $_GET['id'];

// Verificar que existe el directorio y archivo del controller
if (file_exists("controllers/UsuarioController.php")) {
    include "controllers/UsuarioController.php";
} else {
    die("Error: No se pudo cargar el controlador de usuarios.");
}

// Instanciar el controller
$controller = new UsuarioController($conexion, $url_base);

// Obtener datos del usuario
try {
    $usuario = $controller->obtenerUsuarioParaVer($id);
} catch (Exception $e) {
    header("Location: " . $url_base . "secciones/usuarios/index.php?error=" . urlencode($e->getMessage()));
    exit();
}

// Obtener datos para la vista
$datosVista = $controller->obtenerDatosVista();
$configuracionJS = $controller->obtenerConfiguracionJS();

// Log de actividad
$controller->logActividad('Ver usuario', 'ID: ' . $id);
$breadcrumb_items = ['Configuracion', 'Usuarios', 'Ver Usuario'];
$item_urls = [
    $url_base . 'secciones/configuracion/index.php',
    $url_base . 'secciones/usuarios/index.php'
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Usuario</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Estilos personalizados -->
    <link href="<?php echo $url_base; ?>secciones/usuarios/utils/styles.css" rel="stylesheet">

</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-user-circle me-2"></i>Detalles del Usuario</h4>
                <div>
                    <?php if ($controller->verificarPermisos('editar')): ?>
                        <a href="editar.php?id=<?php echo $id; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Editar
                        </a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-secondary ms-2">
                        <i class="fas fa-arrow-left me-2"></i>Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="card-title mb-4 fw-bold"><?php echo htmlspecialchars($usuario['nombre']); ?></h5>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <span class="fw-bold"><i class="fas fa-hashtag me-2 text-primary"></i>ID:</span>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($usuario['id']); ?></span>
                                </div>

                                <div class="mb-3">
                                    <span class="fw-bold"><i class="fas fa-user me-2 text-primary"></i>Nombre:</span>
                                    <?php echo htmlspecialchars($usuario['nombre']); ?>
                                </div>

                                <div class="mb-3">
                                    <span class="fw-bold"><i class="fas fa-at me-2 text-primary"></i>Usuario:</span>
                                    <code><?php echo htmlspecialchars($usuario['usuario']); ?></code>
                                </div>

                                <div class="mb-3">
                                    <span class="fw-bold"><i class="fas fa-user-tag me-2 text-primary"></i>Rol:</span>
                                    <?php echo $usuario['rol_badge']; ?>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <!-- Información adicional del usuario -->
                                <div class="card bg-light h-100">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información del Sistema</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <small class="text-muted">Estado de la cuenta:</small><br>
                                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Activa</span>
                                        </div>

                                        <div class="mb-3">
                                            <small class="text-muted">Permisos:</small><br>
                                            <?php
                                            switch ($usuario['rol']) {
                                                case '1':
                                                    echo '<small>Acceso completo al sistema</small>';
                                                    break;
                                                case '2':
                                                    echo '<small>Gestión de ventas y clientes</small>';
                                                    break;
                                                case '3':
                                                    echo '<small>Gestión financiera y reportes</small>';
                                                    break;
                                                case '4':
                                                    echo '<small>Planificación y control de producción</small>';
                                                    break;
                                                default:
                                                    echo '<small>Permisos básicos</small>';
                                            }
                                            ?>
                                        </div>

                                        <?php if ($usuario['id'] == $_SESSION['id']): ?>
                                            <div class="alert alert-info mb-0" role="alert">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <small>Este es tu usuario actual</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card border-primary h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Acciones</h5>
                            </div>
                            <div class="card-body d-flex flex-column justify-content-center">
                                <div class="d-grid gap-3">
                                    <?php if ($controller->verificarPermisos('editar')): ?>
                                        <a href="editar.php?id=<?php echo $usuario['id']; ?>" class="btn btn-warning">
                                            <i class="fas fa-edit me-2"></i>Editar Usuario
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($controller->verificarPermisos('eliminar', $usuario['id'])): ?>
                                        <a href="javascript:void(0);" onclick="confirmarEliminar(<?php echo $usuario['id']; ?>)" class="btn btn-danger">
                                            <i class="fas fa-trash me-2"></i>Eliminar Usuario
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-danger" disabled data-bs-toggle="tooltip" title="No puedes eliminar tu propio usuario">
                                            <i class="fas fa-ban me-2"></i>Eliminar Usuario
                                        </button>
                                    <?php endif; ?>

                                    <hr class="my-2">

                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-list me-2"></i>Lista de Usuarios
                                    </a>

                                    <?php if ($controller->verificarPermisos('crear')): ?>
                                        <a href="registrar.php" class="btn btn-outline-primary">
                                            <i class="fas fa-user-plus me-2"></i>Nuevo Usuario
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información adicional sobre roles -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-users-cog me-2"></i>Información de Roles del Sistema</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <span class="badge bg-danger me-2"><i class="fas fa-user-shield"></i></span>
                                            <strong>Administrador:</strong> Acceso completo, gestión de usuarios y configuración
                                        </div>
                                        <div class="mb-2">
                                            <span class="badge bg-success me-2"><i class="fas fa-user-tie"></i></span>
                                            <strong>Vendedor:</strong> Gestión de ventas, clientes y productos
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <span class="badge bg-warning me-2"><i class="fas fa-calculator"></i></span>
                                            <strong>Contador:</strong> Gestión financiera, reportes y contabilidad
                                        </div>
                                        <div class="mb-2">
                                            <span class="badge bg-primary me-2"><i class="fas fa-cogs"></i></span>
                                            <strong>PCP:</strong> Planificación y control de producción
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación para eliminar -->
    <div class="modal fade" id="confirmarEliminarModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>¡Atención!</strong> Esta acción no se puede deshacer.
                    </div>
                    <p class="mb-0">¿Está seguro que desea eliminar este usuario del sistema?</p>
                    <p class="text-muted">Se eliminará permanentemente toda la información asociada.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <a href="#" id="btn-eliminar" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Eliminar Usuario
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript personalizado -->
    <script src="<?php echo $url_base; ?>secciones/usuarios/js/UsuariosManager.js"></script>
    <script>
        // Configuración global para JavaScript
        const USUARIOS_CONFIG = <?php echo json_encode($configuracionJS); ?>;
    </script>
</body>

</html>