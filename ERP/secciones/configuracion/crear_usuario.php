<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();
require_once 'controllers/UsuarioController.php';
$usuarioController = new UsuarioController($conexion);
$resultado = $usuarioController->procesarRequest();
$mensaje = $resultado['mensaje'] ?? '';
$tipo_mensaje = $resultado['tipo_mensaje'] ?? '';
$usuarios_existentes = $resultado['usuarios_existentes'] ?? [];
function obtenerNombreRol($rol)
{
    global $usuarioController;
    return $usuarioController->obtenerNombreRol($rol);
}
function obtenerClaseRol($rol)
{
    global $usuarioController;
    return $usuarioController->obtenerClaseRol($rol);
}
$breadcrumb_items = ['CONFIGURACION', 'GESTION DE USUARIOS'];
$item_urls = [
    $url_base . 'secciones/configuracion/index.php',
];
$additional_css = [$url_base . 'secciones/configuracion/utils/styles.css'];
include $path_base . "components/head.php";
?>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="main-container">
        <div class="container-fluid">
            <?php if (!empty($mensaje)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-<?php echo $tipo_mensaje === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show fade-in" role="alert">
                            <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                            <?php echo htmlspecialchars($mensaje); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5><i class="fas fa-users"></i>Usuarios Registrados</h5>
                                <small class="text-muted">Lista de usuarios actuales en el sistema</small>
                            </div>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRegistroUsuario">
                                <i class="fas fa-user-plus me-2"></i>Nuevo Usuario
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($usuarios_existentes)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-user-slash"></i>
                                    <p>No hay usuarios registrados en el sistema</p>
                                    <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#modalRegistroUsuario">
                                        <i class="fas fa-user-plus me-2"></i>Registrar Primer Usuario
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th><i class="fas fa-hashtag me-1"></i>ID</th>
                                                <th><i class="fas fa-user me-1"></i>Nombre</th>
                                                <th><i class="fas fa-at me-1"></i>Usuario</th>
                                                <th><i class="fas fa-user-tag me-1"></i>Rol</th>
                                                <th><i class="fas fa-tools me-1"></i>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($usuarios_existentes as $usuario_item): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($usuario_item['id']); ?></td>
                                                    <td><?php echo htmlspecialchars($usuario_item['nombre']); ?></td>
                                                    <td><?php echo htmlspecialchars($usuario_item['usuario']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo obtenerClaseRol($usuario_item['rol']); ?>">
                                                            <?php echo obtenerNombreRol($usuario_item['rol']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary btn-action me-1"
                                                            onclick="editarUsuario(<?php echo $usuario_item['id']; ?>, '<?php echo htmlspecialchars($usuario_item['nombre']); ?>', '<?php echo htmlspecialchars($usuario_item['usuario']); ?>', '<?php echo $usuario_item['rol']; ?>')"
                                                            title="Editar usuario">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger btn-action"
                                                            onclick="confirmarEliminar(<?php echo $usuario_item['id']; ?>, '<?php echo htmlspecialchars($usuario_item['nombre']); ?>')"
                                                            title="Eliminar usuario">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalRegistroUsuario" tabindex="-1" aria-labelledby="modalRegistroUsuarioLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRegistroUsuarioLabel">
                        <i class="fas fa-user-plus me-2"></i>Registro de Nuevo Usuario
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="formUsuario">
                        <input type="hidden" name="accion" value="crear">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombre" class="form-label">
                                    <i class="fas fa-user me-1"></i>Nombre Completo
                                </label>
                                <input type="text" class="form-control" id="nombre" name="nombre"
                                    value="<?php echo htmlspecialchars($nombre ?? ''); ?>"
                                    placeholder="Escriba..." required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="usuario" class="form-label">
                                    <i class="fas fa-at me-1"></i>Nombre de Usuario
                                </label>
                                <input type="text" class="form-control" id="usuario" name="usuario"
                                    value="<?php echo htmlspecialchars($usuario ?? ''); ?>"
                                    placeholder="Escriba..." required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contrasenia" class="form-label">
                                    <i class="fas fa-lock me-1"></i>Contraseña
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="contrasenia" name="contrasenia"
                                        placeholder="Mínimo 6 caracteres" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                                <small class="form-text text-muted">La contraseña debe tener al menos 6 caracteres</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirmar_contrasenia" class="form-label">
                                    <i class="fas fa-lock me-1"></i>Confirmar Contraseña
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirmar_contrasenia" name="confirmar_contrasenia"
                                        placeholder="Repita la contraseña" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword2">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div id="passwordMatch" class="mt-1"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="rol" class="form-label">
                                    <i class="fas fa-user-tag me-1"></i>Rol del Usuario
                                </label>
                                <select class="form-select" id="rol" name="rol" required>
                                    <option value="">Seleccione un rol</option>
                                    <option value="1" <?php echo (isset($rol) && $rol === '1') ? 'selected' : ''; ?>>
                                        Administrador
                                    </option>
                                    <option value="2" <?php echo (isset($rol) && $rol === '2') ? 'selected' : ''; ?>>
                                        Producción
                                    </option>
                                    <option value="3" <?php echo (isset($rol) && $rol === '3') ? 'selected' : ''; ?>>
                                        Expedición
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Descripción del Rol</label>
                                <div class="form-control-plaintext" id="rolDescription">
                                    <small class="text-muted">Seleccione un rol para ver su descripción</small>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" form="formUsuario" class="btn btn-primary" id="btnRegistrar">
                        <i class="fas fa-save me-1"></i>Registrar Usuario
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-labelledby="modalEditarUsuarioLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarUsuarioLabel">
                        <i class="fas fa-user-edit me-2"></i>Editar Usuario
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="formEditarUsuario">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" id="editId">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editNombre" class="form-label">
                                    <i class="fas fa-user me-1"></i>Nombre Completo
                                </label>
                                <input type="text" class="form-control" id="editNombre" name="nombre" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editUsuario" class="form-label">
                                    <i class="fas fa-at me-1"></i>Nombre de Usuario
                                </label>
                                <input type="text" class="form-control" id="editUsuario" name="usuario" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editRol" class="form-label">
                                    <i class="fas fa-user-tag me-1"></i>Rol del Usuario
                                </label>
                                <select class="form-select" id="editRol" name="rol" required>
                                    <option value="1">Administrador</option>
                                    <option value="2">Producción</option>
                                    <option value="3">Expedición</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nuevaContrasenia" class="form-label">
                                    <i class="fas fa-lock me-1"></i>Nueva Contraseña (Opcional)
                                </label>
                                <input type="password" class="form-control" id="nuevaContrasenia" name="nueva_contrasenia"
                                    placeholder="Dejar vacío para mantener la actual">
                                <small class="form-text text-muted">Solo ingrese si desea cambiar la contraseña</small>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" form="formEditarUsuario" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Actualizar Usuario
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalEliminarUsuario" tabindex="-1" aria-labelledby="modalEliminarUsuarioLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalEliminarUsuarioLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <i class="fas fa-user-times fa-3x text-danger mb-3"></i>
                        <h5>¿Está seguro que desea eliminar este usuario?</h5>
                        <p class="text-muted mb-0">Usuario: <strong id="eliminarNombreUsuario"></strong></p>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Esta acción no se puede deshacer.
                        </div>
                    </div>
                    <form method="POST" action="" id="formEliminarUsuario">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" id="eliminarId">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" form="formEliminarUsuario" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Eliminar Usuario
                    </button>
                </div>
            </div>
        </div>
    </div>
    <footer class="footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; <?php echo date('Y'); ?> Sistema de Producción America TNT. Todos los derechos reservados.</p>
                </div>
                <div class="col-md-6 text-end">
                    <p><i class="fas fa-users-cog me-1"></i>Gestión de Usuarios</p>
                </div>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/usuarios.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mensaje = '<?php echo addslashes($mensaje ?? ''); ?>';
            const tipoMensaje = '<?php echo addslashes($tipo_mensaje ?? ''); ?>';
            handleMessageBehavior(mensaje, tipoMensaje);
        });
    </script>
</body>

</html>