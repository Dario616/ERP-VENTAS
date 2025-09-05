<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '3']);

date_default_timezone_set('America/Asuncion');

if (file_exists("controller/expedicionController.php")) {
    include "controller/expedicionController.php";
} else {
    die("Error: No se pudo cargar el controlador de expedición.");
}


$controller = new ExpedicionController($conexion, $url_base);

$configuracion = $controller->obtenerConfiguracion();

try {
    $resultadosPorPagina = 10;
    $paginaActual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    if ($paginaActual < 1) {
        $paginaActual = 1;
    }
    $filtroCliente = $_GET['cliente'] ?? '';
    $datosVista = $controller->obtenerDatosVistaExpedicion($filtroCliente, $paginaActual, $resultadosPorPagina);
    $clientesConVentas = $datosVista['clientes_con_ventas'];
    $rejillasDisponibles = $datosVista['rejillas_disponibles'];
    $estadisticasGlobalesProduccionExpedicion = $datosVista['estadisticas_globales_produccion_expedicion'];
    $totalClientes = $datosVista['total_clientes'];
    $totalPaginas = $datosVista['total_paginas'];
    $paginaActual = $datosVista['pagina_actual'];
    $filtroCliente = $datosVista['filtro_cliente'];
} catch (Exception $e) {
    error_log("Error fatal obteniendo datos de vista expedición: " . $e->getMessage());
    $clientesConVentas = [];
    $rejillasDisponibles = [];
    $estadisticasGlobalesProduccionExpedicion = [];
    $totalClientes = 0;
    $totalPaginas = 0;
    $paginaActual = 1;
    $filtroCliente = '';
}

$configuracion = $controller->obtenerConfiguracion();
$breadcrumb_items = ['EXPEDICION'];
$item_urls = [];
$additional_css = [$url_base . 'secciones/expedicion/utils/expedicion.css'];
include $path_base . "components/head.php";
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="main-container">
        <div class="container-fluid">
            <div class="header-section">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <h1 class="header-title">
                            <i class="fas fa-rocket"></i>
                            Asignar a Rejillas
                        </h1>
                    </div>
                    <div class="col-lg-6">
                        <div class="d-flex justify-content-end align-items-center gap-2">
                            <form method="GET" class="d-flex flex-grow-1 gap-2">
                                <input type="text" name="cliente" class="form-control"
                                    placeholder="Buscar cliente..."
                                    value="<?php echo htmlspecialchars($filtroCliente ?? ''); ?>">
                                <button type="submit" class="btn btn-search" title="Buscar cliente">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (!empty($filtroCliente)): ?>
                                    <a href="?" class="btn btn-outline-secondary" title="Limpiar filtro">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </form>
                            <a href="rejillas.php" class="btn btn-primary">
                                <i class="fas fa-th-large me-2"></i>Ver Rejillas
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg">
                    <?php if (empty($clientesConVentas)): ?>
                        <div class="no-items">
                            <i class="fas fa-check-circle fa-4x text-success"></i>
                            <h5>¡Excelente! No hay productos pendientes</h5>
                            <p class="text-muted mb-0">
                                <?php if (!empty($filtroCliente)): ?>
                                    No se encontraron productos pendientes para el cliente "<?php echo htmlspecialchars($filtroCliente ?? ''); ?>"
                                <?php else: ?>
                                    Todos los productos están asignados a rejillas o no hay ventas registradas
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($clientesConVentas as $cliente): ?>
                            <div class="cliente-card" onclick="abrirModalCliente('<?php echo addslashes($cliente['nombre']); ?>')">
                                <div class="cliente-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="flex-grow-1">
                                            <h3 class="cliente-nombre">
                                                <i class="fas fa-building"></i>
                                                <?php echo htmlspecialchars($cliente['nombre'] ?? ''); ?>
                                            </h3>
                                            <div class="mt-2">
                                                <span class="cliente-actividad-badge actividad-<?php echo $cliente['estado_actividad'] ?? 'baja'; ?>">
                                                    <i class="<?php echo $cliente['icono_actividad'] ?? 'fas fa-minus'; ?>"></i>
                                                    Actividad <?php echo ucfirst($cliente['estado_actividad'] ?? 'baja'); ?>
                                                </span>
                                            </div>
                                            <?php if (isset($cliente['estadisticas_produccion_expedicion'])): ?>
                                                <div class="prod-exp-badges">
                                                    <?php
                                                    $stats = $cliente['estadisticas_produccion_expedicion'];
                                                    $pesoProduccion = $stats['total_peso_produccion'] ?? 0;
                                                    $pesoExpedicion = $stats['total_peso_expedicion'] ?? 0;
                                                    $porcentajeProduccion = $stats['porcentaje_produccion'] ?? 0;
                                                    $porcentajeExpedicion = $stats['porcentaje_expedicion'] ?? 0;
                                                    $porcentajePendiente = $stats['porcentaje_pendiente'] ?? 0;
                                                    ?>
                                                    <?php if ($pesoProduccion > 0): ?>
                                                        <span class="badge-produccion">
                                                            <i class="fas fa-industry me-1"></i>
                                                            Prod: <?php echo number_format($pesoProduccion, 0); ?>kg (<?php echo $porcentajeProduccion; ?>%)
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($pesoExpedicion > 0): ?>
                                                        <span class="badge-expedicion">
                                                            <i class="fas fa-warehouse me-1"></i>
                                                            Exp: <?php echo number_format($pesoExpedicion, 0); ?>kg (<?php echo $porcentajeExpedicion; ?>%)
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($porcentajePendiente > 0): ?>
                                                        <span class="badge-pendiente">
                                                            <i class="fas fa-clock me-1"></i>
                                                            Pend: <?php echo $porcentajePendiente; ?>%
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end d-flex align-items-center gap-3">
                                            <div class="text-end">
                                                <span class="cliente-badge">
                                                    <?php echo $cliente['total_ventas'] ?? 0; ?> ventas
                                                </span>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo $cliente['total_productos'] ?? 0; ?> productos •
                                                    <?php echo number_format($cliente['total_cantidad_vendida'] ?? 0, 0); ?> kg total
                                                </small>
                                                <?php if (!empty($cliente['ultima_venta_formateada'])): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        Última: <?php echo $cliente['ultima_venta_formateada']; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <i class="fas fa-chevron-right arrow-icon"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($totalPaginas > 1): ?>
                        <nav aria-label="Navegación de páginas" class="mt-4 d-flex justify-content-center">
                            <ul class="pagination">
                                <?php
                                $parametrosUrl = [];
                                if (!empty($filtroCliente)) {
                                    $parametrosUrl['cliente'] = $filtroCliente;
                                }
                                ?>
                                <li class="page-item <?php echo ($paginaActual <= 1) ? 'disabled' : ''; ?>">
                                    <?php $parametrosUrl['pagina'] = $paginaActual - 1; ?>
                                    <a class="page-link" href="?<?php echo http_build_query($parametrosUrl); ?>">Anterior</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                    <li class="page-item <?php echo ($paginaActual == $i) ? 'active' : ''; ?>">
                                        <?php $parametrosUrl['pagina'] = $i; ?>
                                        <a class="page-link" href="?<?php echo http_build_query($parametrosUrl); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($paginaActual >= $totalPaginas) ? 'disabled' : ''; ?>">
                                    <?php $parametrosUrl['pagina'] = $paginaActual + 1; ?>
                                    <a class="page-link" href="?<?php echo http_build_query($parametrosUrl); ?>">Siguiente</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalVentasProductosCliente" tabindex="-1" aria-labelledby="modalVentasProductosClienteLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalVentasProductosClienteLabel">
                        <i class="fas fa-building me-3"></i>
                        <span id="nombreClienteModal">Cliente</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="loading-spinner" id="loadingVentasProductos">
                        <i class="fas fa-spinner fa-spin fa-3x"></i>
                        <p class="mt-3 mb-0">Cargando productos pendientes de asignación...</p>
                    </div>
                    <div id="contenidoVentasProductos">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const EXPEDICION_CONFIG = {
            urlBase: "<?php echo $url_base; ?>",
            usuario: "<?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?>",
            debug: <?php echo isset($_GET['debug']) ? 'true' : 'false'; ?>,
            rejillasDisponibles: <?php echo json_encode($rejillasDisponibles); ?>,
            version: "<?php echo $configuracion['version_sistema'] ?? '4.0'; ?>",
            sistemaSimplificado: <?php echo $configuracion['sistema_simplificado'] ? 'true' : 'false'; ?>,
            calculoPesoExacto: <?php echo $configuracion['calculo_peso_exacto'] ? 'true' : 'false'; ?>,
            sinStockFisico: <?php echo $configuracion['sin_stock_fisico'] ? 'true' : 'false'; ?>,
            diferenciacionProduccionExpedicion: <?php echo $configuracion['diferenciacion_produccion_expedicion'] ? 'true' : 'false'; ?>,
            manejoMovimientoEnRejillas: <?php echo $configuracion['manejo_movimiento_en_rejillas'] ? 'true' : 'false'; ?>,
            autoOcultarAsignados: <?php echo $configuracion['auto_ocultar_asignados'] ? 'true' : 'false'; ?>
        };
    </script>
    <script src="<?php echo $url_base; ?>secciones/expedicion/js/expedicion/expedicion-core.js"></script>
    <script src="<?php echo $url_base; ?>secciones/expedicion/js/expedicion/expedicion-ui.js"></script>
    <script src="<?php echo $url_base; ?>secciones/expedicion/js/expedicion/expedicion-productos2.js"></script>
    <script src="<?php echo $url_base; ?>secciones/expedicion/js/expedicion/expedicion-ventas.js"></script>
</body>

