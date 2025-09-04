<?php
require_once "config/conexionBD.php";
require_once "config/session_config.php";

// 🔑 INICIAR SESIÓN AISLADA DE AMERICA TNT
iniciarSesionAislada();

// Si ya está logueado en ESTA aplicación, redirigir al index
if (verificarSesionAislada()) {
    header("Location: " . $url_base . "index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMERICA TNT - PRODUCCION</title>

    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/favicon-192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png">
    <link rel="icon" href="utils/icon.ico" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            /* 🏢 Colores principales America TNT */
            --america-navy: #1e3a5f;
            /* Azul navy principal */
            --america-navy-dark: #152c47;
            /* Azul navy oscuro */
            --america-navy-light: #2d4d73;
            /* Azul navy claro */
            --america-red: #dc2626;
            /* Rojo corporativo */
            --america-orange: #ea580c;
            /* Naranja corporativo */
            --america-gray: #64748b;
            /* Gris corporativo */
            --america-blue-light: #3b82f6;
            /* Azul claro */

            /* 🎨 Paleta funcional */
            --america-success: #059669;
            /* Verde para éxito */
            --america-warning: #d97706;
            /* Amarillo/naranja para advertencias */
            --america-danger: #dc2626;
            /* Rojo para peligro */
            --america-info: #3b82f6;
            /* Azul para información */

            /* 🖼️ Backgrounds */
            --bg-primary: #f8fafc;
            /* Fondo principal claro */
            --bg-secondary: #e2e8f0;
            /* Fondo secundario */
            --bg-card: #ffffff;
            /* Fondo de tarjetas */
            --bg-hero: linear-gradient(135deg, var(--america-navy-dark) 0%, var(--america-navy) 50%, var(--america-navy-light) 100%);

            /* 📝 Texto */
            --text-primary: #1e293b;
            /* Texto principal */
            --text-secondary: var(--america-gray);
            --text-muted: #94a3b8;
            --text-white: #ffffff;

            /* 🌫️ Sombras America TNT */
            --shadow-sm: 0 2px 4px rgba(30, 58, 95, 0.1);
            --shadow-md: 0 4px 8px rgba(30, 58, 95, 0.12);
            --shadow-lg: 0 8px 16px rgba(30, 58, 95, 0.15);
            --shadow-xl: 0 12px 24px rgba(30, 58, 95, 0.18);
            --shadow-hero: 0 20px 40px rgba(30, 58, 95, 0.2);

            /* 🔄 Bordes y radios */
            --border-radius-sm: 6px;
            --border-radius-md: 12px;
            --border-radius-lg: 16px;
            --border-radius-xl: 24px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", "Roboto", "Segoe UI", sans-serif;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 50%, #cbd5e0 100%);
            color: var(--text-primary);
            line-height: 1.6;
            margin: 0;
            min-height: 100vh;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* 🏗️ Fondo con patrón America TNT */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(30,58,95,0.03)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            z-index: -1;
        }

        .login-container {
            max-width: 450px;
            width: 100%;
            background: var(--bg-card);
            border-radius: var(--border-radius-xl);
            box-shadow: var(--shadow-hero);
            overflow: hidden;
            position: relative;
            border: 1px solid rgba(220, 38, 38, 0.1);
            animation: slideInUp 0.8s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* 🏢 Header de login America TNT */
        .login-header {
            background: var(--bg-hero);
            padding: 2rem 2rem 1.5rem;
            color: var(--text-white);
            position: relative;
            text-align: center;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .login-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--america-red) 0%, var(--america-orange) 100%);
            box-shadow: 0 0 20px rgba(220, 38, 38, 0.5);
        }

        .brand-section {
            position: relative;
            z-index: 1;
            margin-bottom: 1rem;
        }

        .brand-icon {
            font-size: 2.5rem;
            color: var(--america-red);
            margin-bottom: 0.75rem;
            filter: drop-shadow(0 0 10px rgba(220, 38, 38, 0.5));
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        .brand-title {
            font-size: 1.5rem;
            font-weight: 900;
            margin-bottom: 0.25rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .brand-subtitle {
            font-size: 0.85rem;
            font-weight: 400;
            opacity: 0.9;
            margin: 0;
        }

        /* 📝 Formulario de login */
        .login-form {
            padding: 1.75rem;
            position: relative;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .form-control {
            height: 50px;
            border-radius: var(--border-radius-md);
            padding-left: 50px;
            padding-right: 50px;
            border: 2px solid #e2e8f0;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            color: var(--text-primary);
        }

        .form-control::placeholder {
            color: var(--text-muted);
            font-weight: 400;
        }

        .form-control:focus {
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.15);
            border-color: var(--america-red);
            background: var(--bg-card);
        }

        .form-control:hover {
            border-color: rgba(220, 38, 38, 0.3);
        }

        .form-group i.field-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--america-red);
            font-size: 1.1rem;
            z-index: 5;
            transition: all 0.3s ease;
        }

        .form-group:focus-within i.field-icon {
            color: var(--america-orange);
            transform: translateY(-50%) scale(1.1);
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            cursor: pointer;
            padding: 10px;
            z-index: 10;
            background: none;
            border: none;
            border-radius: var(--border-radius-sm);
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .password-toggle:hover {
            color: var(--america-red);
            background: rgba(220, 38, 38, 0.1);
            transform: translateY(-50%) scale(1.1);
        }

        /* 🔘 Botón de login America TNT */
        .btn-login {
            background: linear-gradient(135deg, var(--america-red) 0%, var(--america-orange) 100%);
            border: none;
            height: 50px;
            border-radius: var(--border-radius-md);
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-lg);
            color: var(--text-white);
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            box-shadow: var(--shadow-xl);
            background: linear-gradient(135deg, var(--america-orange) 0%, var(--america-warning) 100%);
        }

        .btn-login:active {
            transform: scale(0.98);
        }

        /* ⚠️ Alertas America TNT */
        .alert {
            border-radius: var(--border-radius-md);
            padding: 1.25rem;
            margin-bottom: 2rem;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            box-shadow: var(--shadow-md);
            border-left: 4px solid;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
            color: var(--america-danger);
            border-left-color: var(--america-danger);
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: var(--america-success);
            border-left-color: var(--america-success);
        }

        .alert i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        /* 📊 Estado del sistema */
        .system-status {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px solid #e2e8f0;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--america-success);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(5, 150, 105, 0.7);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(5, 150, 105, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(5, 150, 105, 0);
            }
        }

        /* 🦶 Footer de información */
        .footer-info {
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 1.5rem;
            font-weight: 500;
        }

        .footer-info p {
            margin: 0.25rem 0;
        }

        .footer-info .version {
            color: var(--america-red);
            font-weight: 700;
            font-family: "Roboto Mono", monospace;
        }

        /* ⏳ Animación de carga para el botón */
        .btn-loading {
            position: relative;
            color: transparent !important;
        }

        .btn-loading:after {
            content: "";
            position: absolute;
            width: 24px;
            height: 24px;
            top: 50%;
            left: 50%;
            margin: -12px 0 0 -12px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* 📱 Responsividad */
        @media (max-width: 576px) {
            .login-container {
                margin: 10px;
                max-width: calc(100% - 20px);
            }

            .login-form {
                padding: 2rem 1.5rem;
            }

            .login-header {
                padding: 2rem 1.5rem 1.5rem;
            }

            .brand-title {
                font-size: 1.5rem;
            }

            .brand-icon {
                font-size: 3rem;
            }

            .form-control {
                height: 55px;
                padding-left: 50px;
                padding-right: 50px;
            }

            .btn-login {
                height: 55px;
                font-size: 1rem;
            }
        }

        /* 🎨 Estados de focus mejorados */
        .form-control:focus-within {
            border-color: var(--america-red);
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.15);
        }

        /* 🏷️ Mejoras visuales del logo */
        .brand-title img {
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
            transition: all 0.3s ease;
        }

        /* 📊 Indicadores de estado dinámicos */
        .status-indicator.connected .status-dot {
            background: var(--america-success);
        }

        .status-indicator.warning .status-dot {
            background: var(--america-warning);
            animation-name: pulse-warning;
        }

        @keyframes pulse-warning {
            0% {
                box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.7);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(217, 119, 6, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(217, 119, 6, 0);
            }
        }

        .status-indicator.error .status-dot {
            background: var(--america-danger);
            animation-name: pulse-error;
        }

        @keyframes pulse-error {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(220, 38, 38, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(220, 38, 38, 0);
            }
        }
    </style>
</head>

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

    <!-- Bootstrap JS -->
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

            // Actualizar estado del sistema (simulado)
            const statusDot = document.querySelector('.status-dot');
            const statusText = document.querySelector('.status-indicator');

            // Simulación de verificación de estado
            setTimeout(function() {
                statusDot.style.background = 'var(--america-success)';
                statusText.innerHTML = '<span class="status-dot"></span>Conexión Establecida';
            }, 1000);
        });
    </script>
</body>

</html>