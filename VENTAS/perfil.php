<?php
include "config/database/conexionBD.php";
include "auth/verificar_sesion.php";

// Obtener datos actuales del usuario
$usuario_id = $_SESSION['id'];
$usuario_actual = null;

try {
    $query = "SELECT * FROM public.sist_ventas_usuario WHERE id = :id";
    $stmt = $conexion->prepare($query);
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario_actual = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al obtener los datos del usuario: " . $e->getMessage();
}

// Procesar formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener datos del formulario
    $nombre = trim($_POST['nombre']);
    $usuario = trim($_POST['usuario']);
    $contrasenia_actual = trim($_POST['contrasenia_actual']);
    $nueva_contrasenia = trim($_POST['nueva_contrasenia']);
    $confirmar_contrasenia = trim($_POST['confirmar_contrasenia']);

    // Validar datos
    $errores = [];

    if (empty($nombre)) {
        $errores[] = "El nombre es obligatorio.";
    }

    if (empty($usuario)) {
        $errores[] = "El usuario es obligatorio.";
    } else {
        // Verificar si el usuario ya existe (excluyendo el usuario actual)
        try {
            $query = "SELECT COUNT(*) as total FROM public.sist_ventas_usuario WHERE usuario = :usuario AND id != :id";
            $stmt = $conexion->prepare($query);
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
            $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado['total'] > 0) {
                $errores[] = "El nombre de usuario ya está en uso. Por favor, elija otro.";
            }
        } catch (PDOException $e) {
            $errores[] = "Error al verificar el usuario: " . $e->getMessage();
        }
    }

    // Validar contraseña solo si se quiere cambiar
    if (!empty($nueva_contrasenia) || !empty($confirmar_contrasenia)) {
        if (empty($contrasenia_actual)) {
            $errores[] = "Debe ingresar su contraseña actual para cambiarla.";
        } else {
            // Verificar contraseña actual
            if ($usuario_actual['contrasenia'] !== $contrasenia_actual) {
                $errores[] = "La contraseña actual es incorrecta.";
            }
        }

        if (empty($nueva_contrasenia)) {
            $errores[] = "La nueva contraseña es obligatoria.";
        } elseif (strlen($nueva_contrasenia) < 4) {
            $errores[] = "La nueva contraseña debe tener al menos 4 caracteres.";
        }

        if ($nueva_contrasenia !== $confirmar_contrasenia) {
            $errores[] = "Las contraseñas nuevas no coinciden.";
        }
    }

    // Si no hay errores, actualizar en la base de datos
    if (empty($errores)) {
        try {
            if (!empty($nueva_contrasenia)) {
                // Actualizar con nueva contraseña
                $query = "UPDATE public.sist_ventas_usuario 
                         SET nombre = :nombre, usuario = :usuario, contrasenia = :contrasenia 
                         WHERE id = :id";
                $stmt = $conexion->prepare($query);
                $stmt->bindParam(':contrasenia', $nueva_contrasenia, PDO::PARAM_STR);
            } else {
                // Actualizar sin cambiar contraseña
                $query = "UPDATE public.sist_ventas_usuario 
                         SET nombre = :nombre, usuario = :usuario 
                         WHERE id = :id";
                $stmt = $conexion->prepare($query);
            }

            $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
            $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);

            $stmt->execute();

            // Actualizar datos de la sesión
            $_SESSION['nombre'] = $nombre;
            $_SESSION['usuario'] = $usuario;

            // Obtener datos actualizados
            $query = "SELECT * FROM public.sist_ventas_usuario WHERE id = :id";
            $stmt = $conexion->prepare($query);
            $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
            $stmt->execute();
            $usuario_actual = $stmt->fetch(PDO::FETCH_ASSOC);

            $mensaje_exito = "Perfil actualizado correctamente.";
        } catch (PDOException $e) {
            $error = "Error al actualizar el perfil: " . $e->getMessage();
        }
    }
}
$breadcrumb_items = ['Cuenta'];
$item_urls = [];
include $path_base . "components/head.php";
?>
<!DOCTYPE html>
<html lang="es">


<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-5">
        <div class="row">
            <!-- Información del Perfil -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Información del Perfil</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-user-circle fa-5x text-primary"></i>
                        </div>
                        <h5><?php echo htmlspecialchars($usuario_actual['nombre']); ?></h5>
                        <p class="text-muted">@<?php echo htmlspecialchars($usuario_actual['usuario']); ?></p>

                        <div class="row text-start">
                            <div class="col-12 mb-2">
                                <small class="text-muted">Rol:</small><br>
                                <span class="badge bg-primary">
                                    <?php
                                    switch ($usuario_actual['rol']) {
                                        case '1':
                                            echo 'Administrador';
                                            break;
                                        case '2':
                                            echo 'Vendedor';
                                            break;
                                        case '3':
                                            echo 'Contador';
                                            break;
                                        case '4':
                                            echo 'PCP';
                                            break;
                                        default:
                                            echo 'Sin rol';
                                            break;
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de Edición -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Editar Perfil</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($mensaje_exito)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $mensaje_exito; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($errores) && !empty($errores)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><strong>Por favor corrije los siguientes errores:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($errores as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="nombre" class="form-label">Nombre</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="nombre" name="nombre" maxlength="20" required
                                            value="<?php echo htmlspecialchars($usuario_actual['nombre']); ?>">
                                    </div>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <label for="usuario" class="form-label">Usuario</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-at"></i></span>
                                        <input type="text" class="form-control" id="usuario" name="usuario" maxlength="20" required
                                            value="<?php echo htmlspecialchars($usuario_actual['usuario']); ?>">
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <h6 class="mb-3"><i class="fas fa-key me-2"></i>Cambiar Contraseña (opcional)</h6>

                            <div class="mb-4">
                                <label for="contrasenia_actual" class="form-label">Contraseña Actual</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="contrasenia_actual" name="contrasenia_actual" maxlength="20">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="form-text text-muted">Ingrese su contraseña actual solo si desea cambiarla.</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="nueva_contrasenia" class="form-label">Nueva Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="nueva_contrasenia" name="nueva_contrasenia" minlength="4" maxlength="20">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <label for="confirmar_contrasenia" class="form-label">Confirmar Nueva Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="confirmar_contrasenia" name="confirmar_contrasenia" minlength="4" maxlength="20">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <a href="<?php echo $url_base; ?>index.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar/ocultar contraseñas
        function togglePasswordVisibility(inputId, buttonId) {
            document.getElementById(buttonId).addEventListener('click', function() {
                const passwordInput = document.getElementById(inputId);
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        }

        togglePasswordVisibility('contrasenia_actual', 'toggleCurrentPassword');
        togglePasswordVisibility('nueva_contrasenia', 'toggleNewPassword');
        togglePasswordVisibility('confirmar_contrasenia', 'toggleConfirmPassword');

        // Validación en tiempo real de contraseñas
        document.getElementById('confirmar_contrasenia').addEventListener('input', function() {
            const nuevaContrasenia = document.getElementById('nueva_contrasenia').value;
            const confirmarContrasenia = this.value;

            if (nuevaContrasenia !== confirmarContrasenia && confirmarContrasenia.length > 0) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });

        document.getElementById('nueva_contrasenia').addEventListener('input', function() {
            const confirmarContrasenia = document.getElementById('confirmar_contrasenia');
            if (confirmarContrasenia.value.length > 0) {
                confirmarContrasenia.dispatchEvent(new Event('input'));
            }
        });
    </script>
</body>

</html>