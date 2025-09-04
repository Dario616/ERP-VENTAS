<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();

$mensaje = "";
$tipo_mensaje = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'] ?? 'crear';

    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre']);
        $usuario = trim($_POST['usuario']);
        $contrasenia = $_POST['contrasenia'];
        $confirmar_contrasenia = $_POST['confirmar_contrasenia'];
        $rol = $_POST['rol'];

        if (empty($nombre) || empty($usuario) || empty($contrasenia) || empty($rol)) {
            $mensaje = "Todos los campos son obligatorios.";
            $tipo_mensaje = "error";
        } elseif ($contrasenia !== $confirmar_contrasenia) {
            $mensaje = "Las contraseñas no coinciden.";
            $tipo_mensaje = "error";
        } elseif (strlen($contrasenia) < 6) {
            $mensaje = "La contraseña debe tener al menos 6 caracteres.";
            $tipo_mensaje = "error";
        } else {
            try {
                $stmt_check = $conexion->prepare("SELECT COUNT(*) FROM sist_prod_usuario WHERE usuario = ?");
                $stmt_check->execute([$usuario]);
                $usuario_existe = $stmt_check->fetchColumn();

                if ($usuario_existe > 0) {
                    $mensaje = "El nombre de usuario ya existe. Por favor, elija otro.";
                    $tipo_mensaje = "error";
                } else {
                    $stmt = $conexion->prepare("INSERT INTO sist_prod_usuario (nombre, usuario, contrasenia, rol) VALUES (?, ?, ?, ?)");
                    $resultado = $stmt->execute([$nombre, $usuario, $contrasenia, $rol]);

                    if ($resultado) {
                        $mensaje = "Usuario registrado exitosamente.";
                        $tipo_mensaje = "success";
                        $nombre = $usuario = $contrasenia = $confirmar_contrasenia = $rol = "";
                    } else {
                        $mensaje = "Error al registrar el usuario. Intente nuevamente.";
                        $tipo_mensaje = "error";
                    }
                }
            } catch (PDOException $e) {
                $mensaje = "Error de base de datos: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    } elseif ($accion === 'editar') {
        $id = $_POST['id'];
        $nombre = trim($_POST['nombre']);
        $usuario = trim($_POST['usuario']);
        $rol = $_POST['rol'];
        $nueva_contrasenia = $_POST['nueva_contrasenia'] ?? '';

        if (empty($nombre) || empty($usuario) || empty($rol)) {
            $mensaje = "Todos los campos son obligatorios.";
            $tipo_mensaje = "error";
        } else {
            try {
                $stmt_check = $conexion->prepare("SELECT COUNT(*) FROM sist_prod_usuario WHERE usuario = ? AND id != ?");
                $stmt_check->execute([$usuario, $id]);
                $usuario_existe = $stmt_check->fetchColumn();

                if ($usuario_existe > 0) {
                    $mensaje = "El nombre de usuario ya existe. Por favor, elija otro.";
                    $tipo_mensaje = "error";
                } else {
                    if (!empty($nueva_contrasenia)) {
                        if (strlen($nueva_contrasenia) < 6) {
                            $mensaje = "La nueva contraseña debe tener al menos 6 caracteres.";
                            $tipo_mensaje = "error";
                        } else {
                            $stmt = $conexion->prepare("UPDATE sist_prod_usuario SET nombre = ?, usuario = ?, contrasenia = ?, rol = ? WHERE id = ?");
                            $resultado = $stmt->execute([$nombre, $usuario, $nueva_contrasenia, $rol, $id]);
                        }
                    } else {
                        $stmt = $conexion->prepare("UPDATE sist_prod_usuario SET nombre = ?, usuario = ?, rol = ? WHERE id = ?");
                        $resultado = $stmt->execute([$nombre, $usuario, $rol, $id]);
                    }

                    if (isset($resultado) && $resultado) {
                        $mensaje = "Usuario actualizado exitosamente.";
                        $tipo_mensaje = "success";
                    } else {
                        $mensaje = "Error al actualizar el usuario. Intente nuevamente.";
                        $tipo_mensaje = "error";
                    }
                }
            } catch (PDOException $e) {
                $mensaje = "Error de base de datos: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    } elseif ($accion === 'eliminar') {
        $id = $_POST['id'];

        try {
            $stmt = $conexion->prepare("DELETE FROM sist_prod_usuario WHERE id = ?");
            $resultado = $stmt->execute([$id]);

            if ($resultado) {
                $mensaje = "Usuario eliminado exitosamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al eliminar el usuario.";
                $tipo_mensaje = "error";
            }
        } catch (PDOException $e) {
            $mensaje = "Error de base de datos: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

try {
    $stmt_usuarios = $conexion->query("SELECT id, nombre, usuario, rol FROM sist_prod_usuario ORDER BY nombre");
    $usuarios_existentes = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usuarios_existentes = [];
}

function obtenerNombreRol($rol)
{
    switch ($rol) {
        case '1':
            return 'Administrador';
        case '2':
            return 'Producción';
        case '3':
            return 'Expedición';
        default:
            return 'Desconocido';
    }
}

function obtenerClaseRol($rol)
{
    switch ($rol) {
        case '1':
            return 'danger';
        case '2':
            return 'primary';
        case '3':
            return 'warning';
        default:
            return 'secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de Usuarios</title>

    <!-- Favicon principal -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $url_base; ?>assets/favicon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $url_base; ?>assets/favicon-192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $url_base; ?>assets/apple-touch-icon.png">
    <link rel="icon" href="<?php echo $url_base; ?>utils/icon.ico" type="image/x-icon">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Estilos del Dashboard -->
    <link rel="stylesheet" href="<?php echo $url_base; ?>index-styles.css">

    <style>
        .modal-header {
            background: linear-gradient(135deg, #2d30ffff 0%, #1d00a0ff 100%);
            color: white;
            border-bottom: none;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .password-strength {
            height: 4px;
            margin-top: 5px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .password-weak {
            background-color: #dc3545;
            width: 33%;
        }

        .password-medium {
            background-color: #ffc107;
            width: 66%;
        }

        .password-strong {
            background-color: #198754;
            width: 100%;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Agregar estos estilos */
        .table-responsive {
            will-change: auto;
            transform: translateZ(0);
            /* Forzar aceleración por hardware */
        }

        .table tbody tr {
            transition: none !important;
            /* Desactivar transiciones en filas */
        }

        .table tbody tr:hover {
            background-color: rgba(0, 0, 0, .075) !important;
            transform: none !important;
            /* Evitar transformaciones */
        }

        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $url_base; ?>index.php">
                <img src="<?php echo $url_base; ?>utils/logoa.png" alt="America TNT" height="30">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $url_base; ?>index.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $url_base; ?>secciones/configuracion/index.php">
                            <i class="fas fa-cogs me-1"></i>Configuración
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo $url_base; ?>secciones/configuracion/crear_usuario.php">
                            <i class="fas fa-user-plus me-1"></i>Gestion de Usuarios
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="hero-title">
                        <i class="fas fa-users-cog me-3"></i>
                        Gestión de Usuarios
                    </h1>
                    <p class="hero-subtitle">
                        Administrar cuentas de usuario para el sistema de producción America TNT
                    </p>
                    <div class="hero-timestamp">
                        <i class="fas fa-clock me-2"></i>
                        <?php echo date('l, d \d\e F \d\e Y - H:i:s'); ?>
                    </div>
                </div>
                <div class="col-lg-4 text-center">
                    <div class="hero-icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <div class="container-fluid">

            <!-- Mostrar mensajes -->
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

            <!-- Header con botón para crear usuario -->
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

    <!-- Modal de Registro de Usuario -->
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

    <!-- Modal de Editar Usuario -->
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

    <!-- Modal de Confirmación de Eliminación -->
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

    <!-- Footer -->
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Scripts personalizados -->
    <script>
        // Actualizar reloj
        function updateTime() {
            const now = new Date();
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            const timeString = now.toLocaleDateString('es-ES', options);
            const timeElement = document.querySelector('.hero-timestamp');
            if (timeElement) {
                timeElement.innerHTML = '<i class="fas fa-clock me-2"></i>' + timeString;
            }
        }
        setInterval(updateTime, 1000);

        // Descriptions for roles
        const roleDescriptions = {
            '1': '<small class="text-danger"><i class="fas fa-crown me-1"></i>Acceso completo al sistema, gestión de usuarios y configuración</small>',
            '2': '<small class="text-primary"><i class="fas fa-industry me-1"></i>Gestión de procesos de producción y control de líneas</small>',
            '3': '<small class="text-warning"><i class="fas fa-truck me-1"></i>Gestión de expedición, envíos y distribución</small>'
        };

        // Update role description
        document.getElementById('rol').addEventListener('change', function() {
            const description = roleDescriptions[this.value] || '<small class="text-muted">Seleccione un rol para ver su descripción</small>';
            document.getElementById('rolDescription').innerHTML = description;
        });

        // Password strength indicator
        document.getElementById('contrasenia').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');

            if (password.length === 0) {
                strengthBar.className = 'password-strength';
                return;
            }

            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;

            strengthBar.className = 'password-strength ';
            if (strength <= 2) {
                strengthBar.className += 'password-weak';
            } else if (strength === 3) {
                strengthBar.className += 'password-medium';
            } else {
                strengthBar.className += 'password-strong';
            }
        });

        // Mostrar/ocultar contraseñas
        document.getElementById('togglePassword1').addEventListener('click', function() {
            const password = document.getElementById('contrasenia');
            const icon = this.querySelector('i');

            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        document.getElementById('togglePassword2').addEventListener('click', function() {
            const password = document.getElementById('confirmar_contrasenia');
            const icon = this.querySelector('i');

            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Validación de contraseñas en tiempo real
        document.getElementById('confirmar_contrasenia').addEventListener('input', function() {
            const password1 = document.getElementById('contrasenia').value;
            const password2 = this.value;
            const matchDiv = document.getElementById('passwordMatch');

            if (password2.length === 0) {
                matchDiv.innerHTML = '';
                this.classList.remove('is-valid', 'is-invalid');
                return;
            }

            if (password1 === password2) {
                matchDiv.innerHTML = '<small class="text-success"><i class="fas fa-check me-1"></i>Las contraseñas coinciden</small>';
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                this.setCustomValidity('');
            } else {
                matchDiv.innerHTML = '<small class="text-danger"><i class="fas fa-times me-1"></i>Las contraseñas no coinciden</small>';
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
                this.setCustomValidity('Las contraseñas no coinciden');
            }
        });

        // Validación del formulario de crear usuario
        document.getElementById('formUsuario').addEventListener('submit', function(e) {
            const password1 = document.getElementById('contrasenia').value;
            const password2 = document.getElementById('confirmar_contrasenia').value;

            if (password1 !== password2) {
                e.preventDefault();
                alert('Las contraseñas no coinciden');
                return false;
            }

            if (password1.length < 6) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 6 caracteres');
                return false;
            }

            // Deshabilitar botón para evitar doble envío
            document.getElementById('btnRegistrar').disabled = true;
            document.getElementById('btnRegistrar').innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Registrando...';
        });

        // Función para editar usuario
        function editarUsuario(id, nombre, usuario, rol) {
            document.getElementById('editId').value = id;
            document.getElementById('editNombre').value = nombre;
            document.getElementById('editUsuario').value = usuario;
            document.getElementById('editRol').value = rol;
            document.getElementById('nuevaContrasenia').value = '';

            const modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
            modal.show();
        }

        // Función para confirmar eliminación
        function confirmarEliminar(id, nombre) {
            document.getElementById('eliminarId').value = id;
            document.getElementById('eliminarNombreUsuario').textContent = nombre;

            const modal = new bootstrap.Modal(document.getElementById('modalEliminarUsuario'));
            modal.show();
        }

        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.dashboard-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });

        // Limpiar formulario cuando se cierre el modal de crear
        document.getElementById('modalRegistroUsuario').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formUsuario').reset();
            document.getElementById('passwordStrength').className = 'password-strength';
            document.getElementById('passwordMatch').innerHTML = '';
            document.getElementById('rolDescription').innerHTML = '<small class="text-muted">Seleccione un rol para ver su descripción</small>';
            document.getElementById('btnRegistrar').disabled = false;
            document.getElementById('btnRegistrar').innerHTML = '<i class="fas fa-save me-1"></i>Registrar Usuario';

            // Limpiar validaciones visuales
            const inputs = document.querySelectorAll('#formUsuario input, #formUsuario select');
            inputs.forEach(input => {
                input.classList.remove('is-valid', 'is-invalid');
            });
        });

        // Auto-abrir modal si hay errores después del envío
        <?php if (!empty($mensaje) && $tipo_mensaje === 'error'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const modal = new bootstrap.Modal(document.getElementById('modalRegistroUsuario'));
                modal.show();
            });
        <?php endif; ?>

        // Auto-cerrar modal si registro fue exitoso
        <?php if (!empty($mensaje) && $tipo_mensaje === 'success'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                // El modal se mantendrá cerrado para mostrar el mensaje de éxito
                setTimeout(function() {
                    const alert = document.querySelector('.alert');
                    if (alert) {
                        alert.style.transition = 'all 0.5s ease';
                        alert.style.opacity = '0';
                        alert.style.transform = 'translateY(-20px)';
                        setTimeout(() => alert.remove(), 500);
                    }
                }, 5000);
            });
        <?php endif; ?>
    </script>

</body>

</html>