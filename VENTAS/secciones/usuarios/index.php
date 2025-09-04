<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
// Solo administradores pueden acceder a la gestión de usuarios
requerirRol('1');

// Verificar que existe el directorio y archivo del controller
if (file_exists("controllers/UsuarioController.php")) {
    include "controllers/UsuarioController.php";
} else {
    die("Error: No se pudo cargar el controlador de usuarios.");
}

// Instanciar el controller
$controller = new UsuarioController($conexion, $url_base);

// Manejar peticiones API
if ($controller->handleApiRequest()) {
    exit();
}

// Procesar eliminación si se solicita
if (isset($_GET['eliminar'])) {
    if (!$controller->verificarPermisos('eliminar', $_GET['eliminar'])) {
        $error = "No tienes permisos para eliminar este usuario.";
    } else {
        $resultado = $controller->procesarEliminacion($_GET['eliminar']);
        if (isset($resultado['mensaje'])) {
            $mensaje = $resultado['mensaje'];
        } else {
            $error = $resultado['error'];
        }
        $controller->logActividad('Eliminar usuario', 'ID: ' . $_GET['eliminar']);
    }
}

// Obtener datos para la vista
$datosVista = $controller->obtenerDatosVista();
$mensajes = $controller->manejarMensajes();
$usuarios = $controller->obtenerListaUsuarios();
$roles = $controller->obtenerRoles();
$configuracionJS = $controller->obtenerConfiguracionJS();

// Fusionar mensajes
if (!empty($mensajes['mensaje'])) $mensaje = $mensajes['mensaje'];
if (!empty($mensajes['error'])) $error = $mensajes['error'];

// Extraer datos de vista
$titulo = $datosVista['titulo'];
$url_base = $datosVista['url_base'];
$usuario_actual = $datosVista['usuario_actual'];
$es_admin = $datosVista['es_admin'];
$estadisticas = $datosVista['estadisticas'];

// Log de actividad
$controller->logActividad('Consulta usuarios');
$breadcrumb_items = ['Configuracion', 'Usuarios'];
$item_urls = [
    $url_base . 'secciones/configuracion/index.php',
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titulo); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="<?php echo $url_base; ?>secciones/usuarios/utils/styles.css" rel="stylesheet">

</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <?php if (isset($mensaje)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <!-- Búsqueda -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-search me-2"></i>Búsqueda de Usuarios</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="position-relative">
                            <input type="text" class="form-control" id="searchUsuarios"
                                placeholder="Buscar por nombre, usuario o rol...">
                            <div class="position-absolute top-50 end-0 translate-middle-y pe-3">
                                <i class="fas fa-search text-muted"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-outline-secondary w-100" onclick="document.getElementById('searchUsuarios').value=''; document.getElementById('searchUsuarios').dispatchEvent(new Event('input'));">
                            <i class="fas fa-times me-1"></i>Limpiar Búsqueda
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de usuarios -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0"><i class="fas fa-users me-2"></i>Gestión de Usuarios</h4>
                    <small class="text-muted">
                        Mostrando <?php echo count($usuarios); ?> usuarios registrados
                    </small>
                </div>
                <?php if ($controller->verificarPermisos('crear')): ?>
                    <a href="registrar.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>Nuevo Usuario
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag me-1"></i>ID</th>
                                <th><i class="fas fa-user me-1"></i>Nombre</th>
                                <th><i class="fas fa-at me-1"></i>Usuario</th>
                                <th><i class="fas fa-user-tag me-1"></i>Rol</th>
                                <th><i class="fas fa-cogs me-1"></i>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="usuariosTableBody">
                            <?php if (count($usuarios) > 0): ?>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($usuario['id']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['usuario']); ?></td>
                                        <td><?php echo $usuario['rol_badge']; ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="ver.php?id=<?php echo $usuario['id']; ?>" class="btn btn-info btn-sm" title="Ver Usuario">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($controller->verificarPermisos('editar')): ?>
                                                    <a href="editar.php?id=<?php echo $usuario['id']; ?>" class="btn btn-warning btn-sm" title="Editar Usuario">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($controller->verificarPermisos('eliminar', $usuario['id'])): ?>
                                                    <a href="javascript:void(0);" onclick="confirmarEliminar(<?php echo $usuario['id']; ?>)" class="btn btn-danger btn-sm" title="Eliminar Usuario">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No hay usuarios registrados</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
                    <p class="mb-0">¿Está seguro que desea eliminar este usuario? Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <a href="#" id="btn-eliminar" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Eliminar
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