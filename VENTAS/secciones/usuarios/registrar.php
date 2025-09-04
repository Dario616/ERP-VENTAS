<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
// Solo administradores pueden registrar usuarios
requerirRol('1');

// Verificar que existe el directorio y archivo del controller
if (file_exists("controllers/UsuarioController.php")) {
    include "controllers/UsuarioController.php";
} else {
    die("Error: No se pudo cargar el controlador de usuarios.");
}

// Instanciar el controller
$controller = new UsuarioController($conexion, $url_base);

// Verificar permisos
if (!$controller->verificarPermisos('crear')) {
    header("Location: " . $url_base . "secciones/usuarios/index.php?error=No tienes permisos para crear usuarios");
    exit();
}

// Variables para mantener datos del formulario
$datos = [
    'nombre' => '',
    'usuario' => '',
    'rol' => ''
];
$errores = [];
$error = '';

// Obtener roles disponibles
$roles = $controller->obtenerRoles();

// Procesar formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = $controller->procesarRegistro();

    if (isset($resultado['errores'])) {
        $errores = $resultado['errores'];
        $datos = $resultado['datos']; // Mantener datos en caso de error
    } elseif (isset($resultado['error'])) {
        $error = $resultado['error'];
        $datos = $resultado['datos'] ?? $datos;
    }
    // Si no hay errores, procesarRegistro() ya redirigió
}

// Obtener datos para la vista
$datosVista = $controller->obtenerDatosVista();
$configuracionJS = $controller->obtenerConfiguracionJS();

// Log de actividad
$controller->logActividad('Acceso registrar usuario');
$breadcrumb_items = ['Configuracion', 'Usuarios', 'Registrar Usuario'];
$item_urls = [
    $url_base . 'secciones/configuracion/index.php',
    $url_base . 'secciones/usuarios/index.php'
];
$additional_css = [$url_base . 'secciones/usuarios/utils/styles.css'];
include $path_base . "components/head.php";
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-5">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>Registrar Nuevo Usuario</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($errores)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><strong>Por favor corrije los siguientes errores:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errores as $errorItem): ?>
                                <li><?php echo htmlspecialchars($errorItem); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="registrar.php" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="nombre" class="form-label">Nombre</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="nombre" name="nombre" maxlength="20" required
                                    value="<?php echo htmlspecialchars($datos['nombre']); ?>"
                                    placeholder="Nombre completo del usuario">
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <label for="usuario" class="form-label">Usuario</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-at"></i></span>
                                <input type="text" class="form-control" id="usuario" name="usuario" maxlength="20" required autocomplete="off"
                                    value="<?php echo htmlspecialchars($datos['usuario']); ?>"
                                    placeholder="Nombre de usuario único">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="rol" class="form-label">Rol</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                            <select class="form-select" id="rol" name="rol" required>
                                <option value="" disabled selected>Seleccione un rol</option>
                                <?php foreach ($roles as $rol_id => $rol_nombre): ?>
                                    <option value="<?php echo htmlspecialchars($rol_id); ?>"
                                        <?php echo ($datos['rol'] == $rol_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($rol_nombre); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <small class="form-text text-muted">
                            <i class="fas fa-info-circle me-1"></i>El rol determina los permisos del usuario en el sistema.
                        </small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="contrasenia" class="form-label">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="contrasenia" name="contrasenia"
                                    minlength="4" maxlength="20" required
                                    placeholder="Mínimo 4 caracteres">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">La contraseña debe tener al menos 4 caracteres.</small>
                        </div>

                        <div class="col-md-6 mb-4">
                            <label for="confirmar_contrasenia" class="form-label">Confirmar Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirmar_contrasenia" name="confirmar_contrasenia"
                                    minlength="4" maxlength="20" required
                                    placeholder="Repetir contraseña">
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">
                                Las contraseñas no coinciden.
                            </div>
                        </div>
                    </div>

                    <!-- Información adicional sobre roles -->
                    <div class="card bg-light mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información de Roles</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <span class="badge bg-danger me-2"><i class="fas fa-user-shield"></i></span>
                                        <strong>Administrador:</strong> Acceso completo al sistema
                                    </div>
                                    <div class="mb-2">
                                        <span class="badge bg-success me-2"><i class="fas fa-user-tie"></i></span>
                                        <strong>Vendedor:</strong> Gestión de ventas y clientes
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <span class="badge bg-warning me-2"><i class="fas fa-calculator"></i></span>
                                        <strong>Contador:</strong> Gestión financiera y reportes
                                    </div>
                                    <div class="mb-2">
                                        <span class="badge bg-primary me-2"><i class="fas fa-cogs"></i></span>
                                        <strong>PCP:</strong> Planificación y control de producción
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i>Registrar Usuario
                        </button>
                    </div>
                </form>
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