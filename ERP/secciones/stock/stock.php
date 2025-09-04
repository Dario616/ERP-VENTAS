<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1']);

// Verificar que existe el directorio y archivo del controller
if (file_exists("controllers/reservasController.php")) {
    include "controllers/reservasController.php";
} else {
    die("Error: No se pudo cargar el controlador de reservas.");
}

$estadisticas = $estadisticas ?? [];
$filtrosAplicados = $filtrosAplicados ?? [];
$mensajeError = $mensajeError ?? '';
$paginacion = $paginacion ?? [];

// Función helper para evitar el error de htmlspecialchars con null
function safeHtmlSpecialChars($string, $flags = ENT_QUOTES, $encoding = 'UTF-8')
{
    return htmlspecialchars($string ?? '', $flags, $encoding);
}

// Manejar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Establecer URL base si no está definida
if (!isset($url_base) || empty($url_base)) {
    // Detectar automáticamente la URL base
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = dirname(dirname($script)); // Subir dos niveles desde /secciones/stock/
    $url_base = $protocol . '://' . $host . $path . '/';
}

// Verificar que todas las variables necesarias estén definidas
$configuracionJS = $configuracionJS ?? $controller->obtenerConfiguracionJS();
$datosReservas = $datosReservas ?? ['datos' => [], 'paginacion' => [], 'estadisticas' => []];
$filtrosAplicados = $filtrosAplicados ?? [];
$mensajeError = $mensajeError ?? '';

// Datos adicionales para debugging
$configuracionJS['currentUrl'] = $_SERVER['REQUEST_URI'] ?? '';
$configuracionJS['timestamp'] = date('Y-m-d H:i:s');
$configuracionJS['debug'] = isset($_GET['debug']) ? true : false;
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titulo); ?></title>

    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $url_base; ?>assets/favicon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $url_base; ?>assets/favicon-192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $url_base; ?>assets/apple-touch-icon.png">
    <link rel="icon" href="<?php echo $url_base; ?>utils/icon.ico" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/stock/utils/stock.css">


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
                        <a class="nav-link active" href="#">
                            <i class="fas fa-boxes me-1"></i>Reservas por Productos
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <div class="container-fluid">
            <!-- Error Message -->
            <?php if (!empty($mensajeError)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($mensajeError); ?>
                </div>
            <?php endif; ?>
            <!-- Filtros -->
            <div class="filter-section">
                <form class="filtros-form" method="GET">
                    <div class="row align-items-end">
                        <div class="col-lg-8 col-md-8 mb-3">
                            <label for="filtroProducto" class="form-label">
                                <i class="fas fa-search me-1"></i>Buscar Producto
                            </label>
                            <input
                                type="text"
                                id="filtroProducto"
                                name="producto"
                                class="form-control"
                                placeholder="Nombre del producto..."
                                value="<?php echo htmlspecialchars($filtrosAplicados['producto'] ?? ''); ?>"
                                autocomplete="off">
                        </div>

                        <div class="col-lg-4 col-md-4 mb-3">
                            <div class="btn-group w-100" role="group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Buscar
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="limpiarFiltros()">
                                    <i class="fas fa-eraser me-1"></i>Limpiar
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tabla de Productos -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-boxes text-danger me-2"></i>
                                    Productos con Reservas Activas
                                </h5>
                                <div>
                                    <small class="text-muted" id="paginationInfo">
                                        Productos con reservas activas
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table productos-table mb-0">
                                    <thead>
                                        <tr>
                                            <th style="min-width: 250px;">Producto</th>
                                            <th style="width: 120px;">Total Reservas</th>
                                            <th style="width: 120px;">Bobinas/Paquetes</th>
                                            <th style="width: 200px;">Clientes</th>
                                            <th style="width: 200px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaProductosBody">
                                        <tr>
                                            <td colspan="7" class="text-center py-5">
                                                <div class="loading-spinner mb-3 mx-auto"></div>
                                                <div class="text-muted">Cargando productos con reservas...</div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Paginación -->
                            <div class="card-footer bg-light" id="paginacionContainer" style="display: none;">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <small class="text-muted" id="infoPaginacion">
                                            <!-- Info dinámica -->
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination justify-content-end mb-0" id="paginacionLista">
                                                <!-- Páginas dinámicas -->
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Detalles de Reservas -->
    <div class="modal fade" id="modalDetallesReservas" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-clipboard-list me-2"></i>
                        Detalles de Reservas - <span id="modalProductoNombre">Producto</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>Producto:</strong><br>
                                            <span id="detalleProductoInfo">-</span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Stock Disponible:</strong><br>
                                            <span id="detalleStockDisponible" class="stock-disponible">-</span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Total Reservado:</strong><br>
                                            <span id="detalleTotalReservado" class="stock-comprometido">-</span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>% Comprometido:</strong><br>
                                            <span id="detallePorcentajeComprometido">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente</th>
                                    <th>Cantidad</th>
                                    <th>Paquetes</th>
                                    <th>Fecha Reserva</th>
                                    <th>Venta</th>
                                    <th>Estado</th>
                                    <th>Días</th>
                                </tr>
                            </thead>
                            <tbody id="tablaReservasDetalle">
                                <!-- Reservas dinámicas -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-warning" onclick="abrirModalCancelacion()">
                        <i class="fas fa-times me-1"></i>Cancelar Reservas
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Cancelación de Reservas -->
    <div class="modal fade" id="modalCancelarReservas" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle me-2"></i>
                        Cancelar Reservas - <span id="modalCancelacionProducto">Producto</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Atención:</strong> Al cancelar una reserva, el stock se liberará y volverá a estar disponible.
                        Esta acción no se puede deshacer.
                    </div>

                    <!-- Filtros de búsqueda -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="filtroClienteCancelacion" class="form-label">Filtrar por Cliente:</label>
                            <input type="text" id="filtroClienteCancelacion" class="form-control"
                                placeholder="Nombre del cliente..." onkeyup="filtrarReservasCancelacion()">
                        </div>
                    </div>

                    <!-- Lista de reservas para cancelar -->
                    <div id="listaReservasCancelacion">
                        <div class="text-center py-4">
                            <div class="loading-spinner mb-3 mx-auto"></div>
                            <div class="text-muted">Cargando reservas disponibles...</div>
                        </div>
                    </div>

                    <!-- Motivo de cancelación -->
                    <div class="mt-4">
                        <label for="motivoCancelacionReservas" class="form-label">Motivo de la cancelación:</label>
                        <textarea class="form-control" id="motivoCancelacionReservas" rows="3"
                            placeholder="Describa el motivo de la cancelación..."></textarea>
                    </div>

                    <!-- Resumen de cancelación -->
                    <div id="resumenCancelacion" class="mt-3 p-3 bg-light rounded" style="display: none;">
                        <h6>Resumen de Cancelación:</h6>
                        <ul id="listaCancelaciones" class="mb-0">
                            <!-- Lista dinámica -->
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarCancelaciones"
                        onclick="confirmarCancelaciones()" disabled>
                        <i class="fas fa-times me-1"></i>Confirmar Cancelaciones
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Botón flotante de actualización -->
    <button class="btn btn-primary btn-floating" onclick="cargarProductos()" title="Actualizar datos">
        <i class="fas fa-sync-alt"></i>
    </button>

    <!-- Scripts Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Configuración global para JavaScript -->
    <script>
        // ===== CONFIGURACIÓN GLOBAL PARA JAVASCRIPT =====
        window.RESERVAS_CONFIG = <?php echo json_encode($configuracionJS); ?>;

        // ===== DATOS INICIALES =====
        window.DATOS_INICIALES = {
            datosReservas: <?php echo json_encode($datosReservas); ?>,
            filtrosAplicados: <?php echo json_encode($filtrosAplicados); ?>,
            mensajeError: <?php echo json_encode($mensajeError); ?>,
            timestamp: '<?php echo date('Y-m-d H:i:s'); ?>'
        };

        // ===== DEBUGGING AVANZADO =====
        window.DEBUG_INFO = {
            urlBase: '<?php echo $url_base ?? ''; ?>',
            usuarioActual: '<?php echo $_SESSION['nombre'] ?? 'Desconocido'; ?>',
            requestUri: '<?php echo $_SERVER['REQUEST_URI'] ?? ''; ?>',
            serverName: '<?php echo $_SERVER['SERVER_NAME'] ?? ''; ?>',
            phpVersion: '<?php echo PHP_VERSION; ?>',
            sessionId: '<?php echo session_id(); ?>',
            controllerLoaded: <?php echo isset($controller) ? 'true' : 'false'; ?>,
            connectionAvailable: <?php
                                    try {
                                        $conexion->query("SELECT 1");
                                        echo 'true';
                                    } catch (Exception $e) {
                                        echo 'false';
                                    }
                                    ?>
        };

        // ===== FUNCIONES DE DEBUGGING GLOBAL =====
        window.debugReservas = function() {
            console.group('🔍 DEBUG RESERVAS - Información del sistema');
            console.log('📊 Configuración:', window.RESERVAS_CONFIG);
            console.log('💾 Datos iniciales:', window.DATOS_INICIALES);
            console.log('🐞 Info de debug:', window.DEBUG_INFO);
            console.log('🌐 URL actual:', window.location.href);
            console.log('📋 Variables globales disponibles:', {
                currentProductData: typeof currentProductData !== 'undefined',
                selectedReservations: typeof selectedReservations !== 'undefined',
                isLoading: typeof isLoading !== 'undefined',
                bootstrap: typeof bootstrap !== 'undefined'
            });
            console.groupEnd();

            return {
                config: window.RESERVAS_CONFIG,
                data: window.DATOS_INICIALES,
                debug: window.DEBUG_INFO
            };
        };

        // ===== TEST DE CONECTIVIDAD AUTOMÁTICO =====
        window.testConectividadAutomatico = function() {
            return fetch(window.location.pathname + '?action=test_conectividad', {
                    method: 'GET',
                    cache: 'no-cache',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    console.log('🌐 Test conectividad - Status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('🌐 Test conectividad - Respuesta:', data);
                    return data.success;
                })
                .catch(error => {
                    console.error('🌐 Test conectividad - Error:', error);
                    return false;
                });
        };

        // ===== MANEJO DE ERRORES GLOBAL =====
        window.addEventListener('error', function(event) {
            console.error('❌ Error JavaScript:', {
                message: event.message,
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno,
                error: event.error
            });

            // Mostrar toast de error si existe la función
            if (typeof showToast === 'function') {
                showToast('❌ Error JavaScript: ' + event.message, 'error');
            }
        });

        // ===== MANEJO DE PROMESAS RECHAZADAS =====
        window.addEventListener('unhandledrejection', function(event) {
            console.error('❌ Promesa rechazada:', event.reason);

            if (typeof showToast === 'function') {
                showToast('❌ Error de conexión no manejado', 'error');
            }
        });

        // ===== LOG INICIAL =====
        console.log('🎯 Sistema de Reservas por Productos iniciado');
        console.log('⚙️ Configuración cargada:', window.RESERVAS_CONFIG);
        console.log('💡 Funciones de debug disponibles: debugReservas(), testConectividadAutomatico()');

        // ===== VERIFICACIÓN AUTOMÁTICA AL CARGAR =====
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar elementos críticos
            const elementosRequeridos = [
                'tablaProductosBody',
                'modalDetallesReservas',
                'modalCancelarReservas',
                'filtroProducto'
            ];

            const elementosFaltantes = elementosRequeridos.filter(id => !document.getElementById(id));

            if (elementosFaltantes.length > 0) {
                console.error('❌ Elementos HTML faltantes:', elementosFaltantes);

                if (typeof showToast === 'function') {
                    showToast('❌ Error: Faltan elementos en la página', 'error');
                }
            } else {
                console.log('✅ Todos los elementos HTML necesarios están presentes');
            }

            // Test de conectividad inicial
            if (window.DEBUG_INFO.connectionAvailable) {
                console.log('✅ Conexión a base de datos OK');
            } else {
                console.error('❌ Sin conexión a base de datos');

                if (typeof showToast === 'function') {
                    showToast('❌ Error de conexión a base de datos', 'error');
                }
            }

            // Verificar Bootstrap
            if (typeof bootstrap === 'undefined') {
                console.error('❌ Bootstrap no está cargado');

                if (typeof showToast === 'function') {
                    showToast('❌ Error: Bootstrap no disponible', 'error');
                }
            } else {
                console.log('✅ Bootstrap cargado correctamente');
            }
        });
    </script>

    <!-- Script principal de reservas -->
    <script src="js/reservas.js"></script>

    <!-- Script de inicialización final -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🎯 Sistema de Reservas por Productos v2.0 cargado correctamente');
            console.log('📊 Configuración:', window.RESERVAS_CONFIG);

            // Ejecutar debugging si está habilitado
            if (window.RESERVAS_CONFIG && window.RESERVAS_CONFIG.debug) {
                setTimeout(() => {
                    debugReservas();
                }, 1000);
            }
        });
    </script>
</body>

</html>