<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
// Solo administradores pueden editar usuarios
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

// Verificar permisos
if (!$controller->verificarPermisos('editar')) {
    header("Location: " . $url_base . "secciones/usuarios/index.php?error=No tienes permisos para editar usuarios");
    exit();
}

// Variables para el formulario
$datos = [];
$errores = [];
$error = '';

// Obtener roles disponibles
$roles = $controller->obtenerRoles();

// Obtener datos del usuario ANTES de procesar el formulario
try {
    $usuario_original = $controller->obtenerUsuarioParaEdicion($id);
} catch (Exception $e) {
    header("Location: " . $url_base . "secciones/usuarios/index.php?error=" . urlencode($e->getMessage()));
    exit();
}

// Inicializar datos con los datos originales del usuario
$datos = [
    'nombre' => $usuario_original['nombre'],
    'usuario' => $usuario_original['usuario'],
    'rol' => $usuario_original['rol']
];

// Procesar formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = $controller->procesarEdicion($id);

    if (isset($resultado['errores'])) {
        $errores = $resultado['errores'];
        // Mantener datos del formulario en caso de error, pero preservar el original como fallback
        $datos = array_merge($datos, $resultado['datos']);
    } elseif (isset($resultado['error'])) {
        $error = $resultado['error'];
        // Mantener datos del formulario en caso de error
        if (isset($resultado['datos'])) {
            $datos = array_merge($datos, $resultado['datos']);
        }
    }
    // Si no hay errores, procesarEdicion() ya redirigió
}

// Obtener datos para la vista
$datosVista = $controller->obtenerDatosVista();
$configuracionJS = $controller->obtenerConfiguracionJS();

// Log de actividad
$controller->logActividad('Acceso editar usuario', 'ID: ' . $id);
$breadcrumb_items = ['Configuracion', 'Usuarios', 'Editar Usuario'];
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
                <h4 class="mb-0"><i class="fas fa-user-edit me-2"></i>Editar Usuario</h4>
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

                <form action="editar.php?id=<?php echo $id; ?>" method="POST">
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
                                <input type="text" class="form-control" id="usuario" name="usuario" maxlength="20" required
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
                                <option value="" disabled>Seleccione un rol</option>
                                <?php foreach ($roles as $rol_id => $rol_nombre): ?>
                                    <option value="<?php echo htmlspecialchars($rol_id); ?>"
                                        <?php echo ($datos['rol'] == $rol_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($rol_nombre); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input" id="cambiar_contrasenia" name="cambiar_contrasenia">
                        <label class="form-check-label" for="cambiar_contrasenia">
                            <i class="fas fa-key me-1"></i>Cambiar contraseña
                        </label>
                        <small class="form-text text-muted d-block">
                            Marque esta opción solo si desea actualizar la contraseña del usuario.
                        </small>
                    </div>

                    <div id="password-fields" style="display: none;">
                        <div class="card bg-light mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-lock me-2"></i>Nueva Contraseña</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="contrasenia" class="form-label">Nueva Contraseña</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="contrasenia" name="contrasenia"
                                                minlength="4" maxlength="20"
                                                placeholder="Mínimo 4 caracteres">
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <small class="form-text text-muted">La contraseña debe tener al menos 4 caracteres.</small>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="confirmar_contrasenia" class="form-label">Confirmar Nueva Contraseña</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="confirmar_contrasenia" name="confirmar_contrasenia"
                                                minlength="4" maxlength="20"
                                                placeholder="Repetir nueva contraseña">
                                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback">
                                            Las contraseñas no coinciden.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información adicional sobre el usuario -->
                    <div class="card bg-info bg-opacity-10 mb-4">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-info-circle me-2"></i>Información Importante</h6>
                            <ul class="mb-0">
                                <li>Los cambios en el rol afectarán inmediatamente los permisos del usuario.</li>
                                <li>Si este es tu propio usuario, los cambios se verán reflejados en tu sesión actual.</li>
                                <li>La contraseña solo se modificará si marcas la opción "Cambiar contraseña".</li>
                            </ul>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Actualizar Usuario
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

        // Función para manejar el toggle de campos de contraseña
        function togglePasswordFields() {
            const passwordFields = document.getElementById('password-fields');
            const checkBox = document.getElementById('cambiar_contrasenia');
            const contrasenia = document.getElementById('contrasenia');
            const confirmarContrasenia = document.getElementById('confirmar_contrasenia');

            if (checkBox.checked) {
                passwordFields.style.display = 'block';
                contrasenia.setAttribute('required', '');
                confirmarContrasenia.setAttribute('required', '');
            } else {
                passwordFields.style.display = 'none';
                contrasenia.removeAttribute('required');
                confirmarContrasenia.removeAttribute('required');
                // Limpiar valores
                contrasenia.value = '';
                confirmarContrasenia.value = '';
                // Limpiar clases de validación
                contrasenia.classList.remove('is-valid', 'is-invalid');
                confirmarContrasenia.classList.remove('is-valid', 'is-invalid');
            }
        }

        // Agregar event listener al checkbox
        document.addEventListener('DOMContentLoaded', function() {
            const cambiarCheckbox = document.getElementById('cambiar_contrasenia');
            if (cambiarCheckbox) {
                cambiarCheckbox.addEventListener('change', togglePasswordFields);
            }
        });
    </script>
</body>

</html>