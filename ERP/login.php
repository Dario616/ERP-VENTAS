<?php
require_once "config/conexionBD.php";
require_once "config/session_config.php";
iniciarSesionAislada();
if (verificarSesionAislada()) {
    header("Location: " . $url_base . "index.php");
    exit();
}
$additional_css = [$url_base . 'login.css'];
include $path_base . "components/head.php";
?>

<body>
    <div class="login-container">
        <div class="login-header">
            <div class="brand-section">
                <div class="brand-icon">
                    <i class="fas fa-industry"></i>
                </div>
                <h1 class="brand-title">
                    <img src="<?php echo $url_base; ?>utils/logoa.png" alt="America TNT" height="30">
                </h1>
            </div>
        </div>

        <div class="login-form">
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php
                    switch ($_GET['error']) {
                        case 'empty':
                            echo "Por favor complete todos los campos requeridos.";
                            break;
                        case 'invalid':
                            echo "Usuario o contraseña incorrectos. Verifique sus credenciales.";
                            break;
                        case 'database':
                            echo "Error de conexión al sistema. Intente nuevamente en unos momentos.";
                            break;
                        default:
                            echo "Ha ocurrido un error inesperado. Contacte al administrador.";
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['logout']) && $_GET['logout'] == 'success'): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i>
                    Sesión cerrada correctamente. ¡Hasta pronto!
                </div>
            <?php endif; ?>

            <form id="loginForm" action="<?php echo $url_base; ?>auth/validar_login.php" method="POST">
                <div class="form-group">
                    <i class="fas fa-user field-icon"></i>
                    <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Nombre de usuario" required autofocus>
                </div>

                <div class="form-group">
                    <i class="fas fa-lock field-icon"></i>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña de acceso" required>
                    <button type="button" class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-login" id="loginButton">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Acceder al Sistema
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Funcionalidad para mostrar/ocultar contraseña
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');

            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);

                // Cambiar el ícono
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });

            // Efecto de carga al enviar el formulario
            const loginForm = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');

            loginForm.addEventListener('submit', function() {
                loginButton.classList.add('btn-loading');
                loginButton.disabled = true;
            });

            // Eliminar alertas después de un tiempo
            const alerts = document.querySelectorAll('.alert');
            if (alerts.length > 0) {
                setTimeout(function() {
                    alerts.forEach(function(alert) {
                        alert.style.opacity = '0';
                        alert.style.transition = 'opacity 0.5s ease';
                        setTimeout(function() {
                            alert.remove();
                        }, 500);
                    });
                }, 6000);
            }
        });
    </script>
</body>