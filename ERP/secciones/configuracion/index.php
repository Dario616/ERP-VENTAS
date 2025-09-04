<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMERICA TNT - CONFIGURACIÓN</title>

    <!-- Favicon principal -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $url_base; ?>assets/favicon-32.png">
    <!-- Opcional: favicon de alto resolución para pantallas retina -->
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $url_base; ?>assets/favicon-192.png">
    <!-- Opcional: favicon para Apple Touch Icon -->
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $url_base; ?>assets/apple-touch-icon.png">
    <!-- Opcional: favicon clásico .ico (fallback para navegadores antiguos) -->
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
                        <a class="nav-link active" href="<?php echo $url_base; ?>secciones/configuracion/index.php">
                            <i class="fas fa-cogs me-1"></i>Configuración
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
                        <i class="fas fa-cogs me-3"></i>
                        Centro de Configuración del Sistema
                    </h1>
                    <p class="hero-subtitle">
                        Gestión de usuarios, transportadoras y configuración general del sistema
                    </p>
                    <div class="hero-timestamp">
                        <i class="fas fa-clock me-2"></i>
                        <?php echo date('l, d \d\e F \d\e Y - H:i:s'); ?>
                    </div>
                </div>
                <div class="col-lg-4 text-center">
                    <div class="hero-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <div class="container-fluid">
            <!-- Sección de Configuración -->
            <div class="row mb-4">
                <div class="col-12">
                    <!-- Opciones de Configuración -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h5><i class="fas fa-sliders-h"></i>Opciones de Configuración</h5>
                            <small class="text-muted">Administración y configuración del sistema</small>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions">

                                <?php
                                if (tieneRol(['1'])) {
                                ?>
                                    <!-- Botón Crear Usuarios -->
                                    <a href="<?php echo $url_base; ?>secciones/configuracion/crear_usuario.php" class="quick-action-btn btn-primary">
                                        <i class="fas fa-user-plus"></i>
                                        <span>Registrar Usuario</span>
                                    </a>

                                <?php
                                }
                                ?>
                                <!-- Botón Registrar Transportadora -->
                                <a href="<?php echo $url_base; ?>secciones/configuracion/registrar_transportadora.php" class="quick-action-btn btn-warning">
                                    <i class="fas fa-truck-loading"></i>
                                    <span>Registrar Transportadora</span>
                                </a>
                                <a href="<?php echo $url_base; ?>secciones/configuracion/registrar_tipotransporte.php" class="quick-action-btn btn-success">
                                    <i class="fas fa-truck"></i>
                                    <span>Registrar Tipo de Transporte</span>
                                </a>

                                <?php
                                if (tieneRol(['1'])) {
                                ?>

                                    <a href="<?php echo $url_base; ?>secciones/stock/stock.php" class="quick-action-btn btn btn-danger">
                                        <i class="fas fa-boxes"></i>
                                        <span>Organizar Stock</span>
                                    </a>
                                <?php
                                }
                                ?>
                            </div>
                        </div>
                    </div>
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
                    <p><i class="fas fa-cogs me-1"></i>Módulo de Configuración</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Script para actualizar la hora -->
    <script>
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

        // Actualizar cada segundo
        setInterval(updateTime, 1000);

        // Efectos de hover para las tarjetas
        document.querySelectorAll('.tipo-summary').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px) scale(1.02)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Efectos para botones de configuración
        document.querySelectorAll('.quick-action-btn').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px) scale(1.02)';
            });

            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Animación de entrada para las tarjetas
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
    </script>

</body>

</html>