<?php
include "config/conexionBD.php";
include "auth/verificar_sesion.php";
$page_title = 'Inicio';
$show_breadcrumb = false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMERICA TNT - PRODUCCION</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $url_base; ?>assets/favicon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $url_base; ?>assets/favicon-192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $url_base; ?>assets/apple-touch-icon.png">
    <link rel="icon" href="<?php echo $url_base; ?>utils/icon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>index-styles.css">
</head>
<body>
    <?php include 'components/navbar.php'; ?>
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

    <div class="main-container">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h5><i class="fas fa-bolt"></i>Acciones Rápidas</h5>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions">
                                <?php
                                if (tieneRol(['1', '2', '3'])) {
                                ?>
                                    <div class="produccion-container">
                                        <a href="" class="produccion-normal quick-action-btn">
                                            <i class="fas fa-industry"></i>
                                            <span>Nueva<br>Producción</span>
                                        </a>
                                        <div class="produccion-split">
                                            <a href="<?php echo $url_base; ?>secciones/produccion/ordenproduccion.php" class="produccion-btn produccion-etiquetar">
                                                <i class="fas fa-barcode"></i>
                                                <span>Etiquetar</span>
                                            </a>
                                            <a href="<?php echo $url_base; ?>secciones/produccion/pendientes.php" class="produccion-btn produccion-pendientes">
                                                <i class="fas fa-clock"></i>
                                                <span>Pendientes</span>
                                            </a>
                                        </div>
                                    </div>
                                <?php
                                }
                                ?>

                                <a href="<?php echo $url_base; ?>secciones/stock/index.php" class="quick-action-btn btn-primary">
                                    <i class="fas fa-warehouse"></i>
                                    <span>STOCK</span>
                                </a>
                                <?php
                                if (tieneRol(['1'])) {
                                ?>
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
                                    <div class="expedicion-container">
                                        <a href="" class="expedicion-normal quick-action-btn">
                                            <i class="fas fa-shipping-fast"></i>
                                            <span>Expedición</span>
                                        </a>
                                        <div class="expedicion-split">
                                            <a href="<?php echo $url_base; ?>secciones/expedicion/expedicion.php" class="expedicion-btn expedicion-pendientes">
                                                <i class="fas fa-clock"></i>
                                                <span>Crear Expedicion</span>
                                            </a>
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
                                    <div class="despacho-container">
                                        <a href="" class="despacho-normal quick-action-btn">
                                            <i class="fas fa-truck"></i>
                                            <span>Despacho</span>
                                        </a>
                                        <div class="despacho-split">
                                            <a href="<?php echo $url_base; ?>secciones/despacho/despacho.php" class="despacho-btn despacho-pendientes">
                                                <i class="fas fa-shipping-fast"></i>
                                                <span>Crear Despacho</span>
                                            </a>
                                            <a href="<?php echo $url_base; ?>secciones/despacho/completados.php" class="despacho-btn despacho-completados">
                                                <i class="fas fa-check-double"></i>
                                                <span>Historial</span>
                                            </a>
                                        </div>
                                    </div>
                                <?php
                                }
                                ?>
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
    <footer class="footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; <?php echo date('Y'); ?> Sistema de Producción America TNT. Todos los derechos reservados.</p>
                </div>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
        setInterval(updateTime, 1000);
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
        document.addEventListener('DOMContentLoaded', function() {
            const btnOrdenManual = document.querySelector('a[href*="nueva_orden_manual"]');
            if (btnOrdenManual) {
                btnOrdenManual.style.position = 'relative';
                btnOrdenManual.style.overflow = 'hidden';
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
                setInterval(() => {
                    destello.style.left = '-100%';
                    setTimeout(() => {
                        destello.style.left = '100%';
                    }, 100);
                }, 5000);
                setTimeout(() => {
                    destello.style.left = '100%';
                }, 1000);
            }
        });
    </script>
</body>
</html>