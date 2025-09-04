<?php
include "config/conexionBD.php"; // Agregar para tener acceso a $url_base
include "auth/verificar_sesion.php";
requerirLogin();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMERICA TNT - PRODUCCION</title>

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
                        <a class="nav-link active" href="<?php echo $url_base; ?>index.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($_SESSION['nombre']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            </li>
                            <li><a class="dropdown-item" href="<?php echo $url_base; ?>auth/cerrar_sesion.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </div>
                </div>
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
                        Centro de Control de Producción
                    </h1>
                    <p class="hero-subtitle">
                        Gestión integral de órdenes, registro de producción y control de calidad
                    </p>
                    <div class="hero-timestamp">
                        <i class="fas fa-clock me-2"></i>
                        <?php echo date('l, d \d\e F \d\e Y - H:i:s'); ?>
                    </div>
                </div>
                <div class="col-lg-4 text-center">
                    <div class="hero-icon">
                        <i class="fas fa-industry"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <div class="container-fluid">
            <!-- Acciones Rápidas -->
            <div class="row mb-4">
                <div class="col-12">
                    <!-- Acciones Rápidas -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h5><i class="fas fa-bolt"></i>Acciones Rápidas</h5>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions">


                                <?php
                                if (tieneRol(['1','2','3'])) {
                                ?>
                                    <!-- Botón de Nueva Producción con división -->
                                    <div class="produccion-container">
                                        <!-- Botón normal (visible por defecto) -->
                                        <a href="" class="produccion-normal quick-action-btn">
                                            <i class="fas fa-industry"></i>
                                            <span>Nueva<br>Producción</span>
                                        </a>

                                        <!-- Botones divididos (visibles al hacer hover) -->
                                        <div class="produccion-split">
                                            <!-- Botón Etiquetar -->
                                            <a href="<?php echo $url_base; ?>secciones/produccion/ordenproduccion.php" class="produccion-btn produccion-etiquetar">
                                                <i class="fas fa-barcode"></i>
                                                <span>Etiquetar</span>
                                            </a>

                                            <!-- Botón Pendientes -->
                                            <a href="<?php echo $url_base; ?>secciones/produccion/pendientes.php" class="produccion-btn produccion-pendientes">
                                                <i class="fas fa-clock"></i>
                                                <span>Pendientes</span>
                                            </a>
                                        </div>
                                    </div>
                                <?php
                                }
                                ?>

                                <!-- Botón de Stock -->
                                <a href="<?php echo $url_base; ?>secciones/stock/index.php" class="quick-action-btn btn-primary">
                                    <i class="fas fa-warehouse"></i>
                                    <span>STOCK</span>
                                </a>
                                <?php
                                if (tieneRol(['1'])) {
                                ?>
                                    <!-- Botón de Orden de Producción -->
                                    <a href="<?php echo $url_base; ?>secciones/produccion/nueva_orden_manual.php" class="quick-action-btn btn-warning">
                                        <i class="fas fa-clipboard-list"></i>
                                        <span>Orden de<br>Producción</span>
                                    </a>
                                <?php
                                }
                                ?>
                                <?php
                                if (tieneRol(['1', '3'])) {
                                ?>
                                    <!-- Botón de Expedición con división -->
                                    <div class="expedicion-container">
                                        <!-- Botón normal (visible por defecto) -->
                                        <a href="" class="expedicion-normal quick-action-btn">
                                            <i class="fas fa-shipping-fast"></i>
                                            <span>Expedición</span>
                                        </a>

                                        <!-- Botones divididos (visibles al hacer hover) -->
                                        <div class="expedicion-split">
                                            <!-- Botón Pendientes -->
                                            <a href="<?php echo $url_base; ?>secciones/expedicion/expedicion.php" class="expedicion-btn expedicion-pendientes">
                                                <i class="fas fa-clock"></i>
                                                <span>Crear Expedicion</span>
                                            </a>

                                            <!-- Botón Completadas -->
                                            <a href="<?php echo $url_base; ?>secciones/expedicion/historial.php" class="expedicion-btn expedicion-completadas">
                                                <i class="fas fa-check-circle"></i>
                                                <span>Historial</span>
                                            </a>
                                        </div>
                                    </div>
                                <?php
                                }
                                ?>
                                <?php
                                if (tieneRol(['1', '3'])) {
                                ?>
                                    <!-- Botón de Despacho con división -->
                                    <div class="despacho-container">
                                        <!-- Botón normal (visible por defecto) -->
                                        <a href="" class="despacho-normal quick-action-btn">
                                            <i class="fas fa-truck"></i>
                                            <span>Despacho</span>
                                        </a>

                                        <!-- Botones divididos (visibles al hacer hover) -->
                                        <div class="despacho-split">
                                            <!-- Botón Pendientes -->
                                            <a href="<?php echo $url_base; ?>secciones/despacho/despacho.php" class="despacho-btn despacho-pendientes">
                                                <i class="fas fa-shipping-fast"></i>

                                                <span>Crear Despacho</span>
                                            </a>

                                            <!-- Botón Completados -->
                                            <a href="<?php echo $url_base; ?>secciones/despacho/completados.php" class="despacho-btn despacho-completados">
                                                <i class="fas fa-check-double"></i>
                                                <span>Historial</span>
                                            </a>
                                        </div>
                                    </div>
                                <?php
                                }
                                ?>
                                <!-- Botón de Relatorio -->
                                <a href="<?php echo $url_base; ?>secciones/relatorio/main.php" class="quick-action-btn btn-info text-white">
                                    <i class="fas fa-file-alt me-1"></i>
                                    <span>Reportes</span>
                                </a>

                                <a href="<?php echo $url_base; ?>secciones/materiaprima/main.php" class="quick-action-btn btn-second">
                                    <i class="fas fa-boxes"></i>
                                    <span>Materiales</span>
                                </a>

                                <?php
                                if (tieneRol(['1', '3'])) {
                                ?>
                                    <!-- Botón de Configuración (negro) -->
                                    <a href="<?php echo $url_base; ?>secciones/configuracion/index.php" class="quick-action-btn btn-dark">
                                        <i class="fas fa-cogs"></i>
                                        <span>Configuración<br>Del Sistema</span>
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

        // Efectos de hover para las tarjetas estadísticas
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Efecto especial para el nuevo botón de Crear Orden Manual
        document.addEventListener('DOMContentLoaded', function() {
            const btnOrdenManual = document.querySelector('a[href*="nueva_orden_manual"]');
            if (btnOrdenManual) {
                // Agregar un destello sutil para destacar la nueva funcionalidad
                btnOrdenManual.style.position = 'relative';
                btnOrdenManual.style.overflow = 'hidden';

                // Crear elemento de destello
                const destello = document.createElement('div');
                destello.style.position = 'absolute';
                destello.style.top = '0';
                destello.style.left = '-100%';
                destello.style.width = '100%';
                destello.style.height = '100%';
                destello.style.background = 'linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent)';
                destello.style.transition = 'left 2s ease-in-out';
                destello.style.pointerEvents = 'none';

                btnOrdenManual.appendChild(destello);

                // Animar el destello cada 5 segundos
                setInterval(() => {
                    destello.style.left = '-100%';
                    setTimeout(() => {
                        destello.style.left = '100%';
                    }, 100);
                }, 5000);

                // Primer destello después de 1 segundo
                setTimeout(() => {
                    destello.style.left = '100%';
                }, 1000);
            }
        });
    </script>

</body>

</html>