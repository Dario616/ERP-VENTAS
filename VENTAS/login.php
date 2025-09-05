<?php include "config/database/conexionBD.php"; 
$additional_css = [$url_base . 'login.css'];
include $path_base . "components/head.php";
?>

<body>
    <div class="login-container">
        <div class="login-header">
            <h2>Sistema de Ventas</h2>
            <p>Inicia sesión para acceder a tu cuenta</p>
        </div>
        <div class="login-form">
            <img src="utils/logoa.png" alt="Logo" class="logo" />
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <div>
                        <?php
                        switch ($_GET['error']) {
                            case 'empty':
                                echo "Por favor complete todos los campos.";
                                break;
                            case 'invalid':
                                echo "Usuario o contraseña incorrectos.";
                                break;
                            case 'database':
                                echo "Error de conexión a la base de datos. Intente nuevamente.";
                                break;
                            default:
                                echo "Ha ocurrido un error. Intente nuevamente.";
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
            <form action="<?php echo $url_base; ?>auth/validar_login.php" method="POST">
                <div class="form-group">
                    <i class="fas fa-user"></i>
                    <?php
                    $usuario_previo = isset($_GET['usuario']) ? htmlspecialchars($_GET['usuario']) : '';
                    ?>

                    <input type="text" class="form-control" name="usuario" placeholder="Nombre de usuario" required value="<?php echo $usuario_previo; ?>" />
                </div>
                <div class="form-group">
                    <i class="fas fa-lock field-icon"></i>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
                    <button type="button" class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <button type="submit" class="btn btn-login w-100">Iniciar sesión</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
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
    </script>
</body>
