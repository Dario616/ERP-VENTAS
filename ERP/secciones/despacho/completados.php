<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '3']);

$archivosRequeridos = [
    "controller/expedicionesDespachadasController.php",
    "repository/expedicionesDespachadasRepository.php",
    "services/expedicionesDespachadasService.php"
];

foreach ($archivosRequeridos as $archivo) {
    if (!file_exists($archivo)) {
        die("Error: No se pudo cargar el archivo requerido: $archivo");
    }
}

include "controller/expedicionesDespachadasController.php";

$controller = new ExpedicionesDespachadasController($conexion, $url_base);

if ($controller->handleRequest()) {
    exit();
}

$fechaInicio = $_GET['fecha_inicio'] ?? '';
$fechaFin = $_GET['fecha_fin'] ?? '';
$transportista = trim($_GET['transportista'] ?? '');
$codigoExpedicion = trim($_GET['codigo_expedicion'] ?? '');
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina = 10;

try {
    $datosVista = $controller->obtenerExpedicionesDespachadas($fechaInicio, $fechaFin, $transportista, $codigoExpedicion, $pagina, $porPagina);

    if (!$datosVista['success']) {
        throw new Exception($datosVista['error'] ?? 'Error obteniendo datos');
    }

    $expedicionesDespachadas = $datosVista['expediciones'];
    $totalExpediciones = $datosVista['total'];
    $transportistasDisponibles = $datosVista['transportistas'];
    $estadisticas = $datosVista['estadisticas'];
    $totalPaginas = $datosVista['paginacion']['total_paginas'];
} catch (Exception $e) {
    error_log("Error fatal en expediciones despachadas: " . $e->getMessage());

    $expedicionesDespachadas = [];
    $totalExpediciones = 0;
    $transportistasDisponibles = [];
    $estadisticas = [
        'total_expediciones' => 0,
        'total_items' => 0,
        'peso_total_bruto_formateado' => '0.00 kg',
        'peso_total_liquido_formateado' => '0.00 kg',
        'clientes_unicos' => 0
    ];
    $totalPaginas = 0;
}

$configuracion = $controller->obtenerConfiguracion();

$breadcrumb_items = ['DESPACHADOS'];
$item_urls = [];
$additional_css = [$url_base . 'secciones/despacho/utils/completados.css'];
include $path_base . "components/head.php";
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-header bg-primary text-white">
                        <h5>
                            <i class="fas fa-filter me-2"></i>
                            Filtros de Búsqueda
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3" id="formFiltros">
                            <div class="col-md-3">
                                <label class="form-label">
                                    <i class="fas fa-search me-1"></i>Código de Expedición
                                </label>
                                <input type="text" class="form-control" name="codigo_expedicion"
                                    value="<?php echo htmlspecialchars($codigoExpedicion); ?>"
                                    placeholder="Ej: EXP2024001">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">
                                    <i class="fas fa-calendar me-1"></i>Fecha Inicio
                                </label>
                                <input type="date" class="form-control" name="fecha_inicio"
                                    value="<?php echo htmlspecialchars($fechaInicio); ?>"
                                    placeholder="Seleccionar fecha inicio">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">
                                    <i class="fas fa-calendar me-1"></i>Fecha Fin
                                </label>
                                <input type="date" class="form-control" name="fecha_fin"
                                    value="<?php echo htmlspecialchars($fechaFin); ?>"
                                    placeholder="Seleccionar fecha fin">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">
                                    <i class="fas fa-truck me-1"></i>Transportista
                                </label>
                                <select class="form-select" name="transportista">
                                    <option value="">Todos los transportistas</option>
                                    <?php foreach ($transportistasDisponibles as $trans): ?>
                                        <option value="<?php echo htmlspecialchars($trans); ?>"
                                            <?php echo $trans === $transportista ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($trans); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="btn-group w-100">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Buscar
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="limpiarFiltros()">
                                        <i class="fas fa-eraser"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

     
        <div class="row">
            <div class="col-12">
                <div class="dashboard-card2">
                    <div class="card-body">
                        <?php if (empty($expedicionesDespachadas)): ?>
                            <div class="empty-state">
                                <i class="fas fa-search"></i>
                                <h4>No hay expediciones despachadas</h4>
                                <p>No se encontraron expediciones despachadas con los filtros aplicados</p>
                                <a href="?" class="btn btn-primary">
                                    <i class="fas fa-refresh me-2"></i>Ver Todas
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($expedicionesDespachadas as $exp): ?>
                                <div class="expedicion-item expedicion-despachada">
                                    <div class="expedicion-header">
                                        <div>
                                            <h4 class="expedicion-numero">
                                                <?php echo htmlspecialchars($exp['numero_expedicion']); ?>
                                                <span class="badge bg-success ms-2">DESPACHADA</span>

                                                <?php if (isset($exp['total_tipos_producto']) && $exp['total_tipos_producto'] > 1): ?>
                                                    <span class="badge bg-info ms-1">
                                                        <i class="fas fa-tags me-1"></i>
                                                        <?php echo $exp['total_tipos_producto']; ?> Tipos
                                                    </span>
                                                <?php endif; ?>

                                                <?php if (isset($exp['ordenes_produccion']) && $exp['ordenes_produccion'] > 1): ?>
                                                    <span class="badge bg-warning ms-1">
                                                        <i class="fas fa-industry me-1"></i>
                                                        <?php echo $exp['ordenes_produccion']; ?> OP
                                                    </span>
                                                <?php endif; ?>
                                            </h4>
                                            <div class="expedicion-info">
                                                <strong>Despachada:</strong> <?php echo $exp['fecha_despacho_formateada']; ?>
                                                por <?php echo htmlspecialchars($exp['usuario_despacho']); ?>
                                                <br><small class="text-light">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    Creada: <?php echo $exp['fecha_creacion_formateada']; ?>
                                                    por <?php echo htmlspecialchars($exp['usuario_creacion']); ?>
                                                </small>

                                                <?php if (isset($exp['fecha_produccion_mas_antigua_formateada']) && $exp['fecha_produccion_mas_antigua_formateada']): ?>
                                                    <br><small class="text-info">
                                                        <i class="fas fa-industry me-1"></i>
                                                        Producción: <?php echo $exp['fecha_produccion_mas_antigua_formateada']; ?>
                                                        <?php if ($exp['fecha_produccion_mas_reciente_formateada'] !== $exp['fecha_produccion_mas_antigua_formateada']): ?>
                                                            - <?php echo $exp['fecha_produccion_mas_reciente_formateada']; ?>
                                                        <?php endif; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="expedicion-stats">
                                            <span class="stat-badge">
                                                <i class="fas fa-box me-1"></i>
                                                <?php echo number_format($exp['total_items'] ?? 0); ?> items
                                            </span>
                                            <span class="stat-badge" title="Peso Bruto">
                                                <i class="fas fa-weight-hanging me-1"></i>
                                                <?php echo $exp['peso_total_bruto_formateado']; ?> kg
                                            </span>
                                            <span class="stat-badge" title="Peso Líquido">
                                                <i class="fas fa-weight me-1"></i>
                                                <?php echo $exp['peso_total_liquido_formateado']; ?> kg
                                            </span>

                                            <?php if (isset($exp['total_bobinas']) && $exp['total_bobinas'] > 0): ?>
                                                <span class="stat-badge">
                                                    <i class="fas fa-circle me-1"></i>
                                                    <?php echo $exp['total_bobinas_formateado']; ?> bobinas
                                                </span>
                                            <?php endif; ?>

                                            <?php if (isset($exp['metragem_total_formateada']) && $exp['metragem_total_formateada'] !== '0'): ?>
                                                <span class="stat-badge">
                                                    <i class="fas fa-ruler me-1"></i>
                                                    <?php echo $exp['metragem_total_formateada']; ?> m
                                                </span>
                                            <?php endif; ?>

                                            <?php if (isset($exp['total_clientes'])): ?>
                                                <span class="stat-badge">
                                                    <i class="fas fa-users me-1"></i>
                                                    <?php echo number_format($exp['total_clientes']); ?> clientes
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="expedicion-details">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <?php if ($exp['transportista']): ?>
                                                    <div class="detail-row">
                                                        <i class="fas fa-truck detail-icon"></i>
                                                        <strong>Transportista:</strong>&nbsp;<?php echo htmlspecialchars($exp['transportista']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($exp['conductor']): ?>
                                                    <div class="detail-row">
                                                        <i class="fas fa-user detail-icon"></i>
                                                        <strong>Conductor:</strong>&nbsp;<?php echo htmlspecialchars($exp['conductor']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($exp['destino']): ?>
                                                    <div class="detail-row">
                                                        <i class="fas fa-map-marker-alt detail-icon"></i>
                                                        <strong>Destino:</strong>&nbsp;<?php echo htmlspecialchars($exp['destino']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (isset($exp['numero_rejilla'])): ?>
                                                    <div class="detail-row">
                                                        <i class="fas fa-th-large detail-icon"></i>
                                                        <strong>Rejilla Utilizada:</strong>&nbsp;#<?php echo htmlspecialchars($exp['numero_rejilla']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <div class="d-flex flex-column btn-group-actions">
                                                    <button class="btn btn-info btn-sm mb-2"
                                                        onclick="verDetalleExpedicionEnriquecido('<?php echo htmlspecialchars($exp['numero_expedicion'], ENT_QUOTES); ?>')">
                                                        <i class="fas fa-eye me-2"></i>Ver Items (<?php echo $exp['total_items']; ?>)
                                                    </button>

                                                    <button class="btn btn-warning btn-sm mb-2"
                                                        onclick="abrirResumenPDF('<?php echo htmlspecialchars($exp['numero_expedicion'], ENT_QUOTES); ?>')">
                                                        <i class="fas fa-file-pdf me-2"></i>Resumen PDF
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($totalPaginas > 1): ?>
                                <nav aria-label="Paginación" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>">
                                                <i class="fas fa-angle-double-left"></i>
                                            </a>
                                        </li>

                                        <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>">
                                                <i class="fas fa-chevron-left"></i> Anterior
                                            </a>
                                        </li>

                                        <?php
                                        $inicio = max(1, $pagina - 2);
                                        $fin = min($totalPaginas, $pagina + 2);

                                        for ($i = $inicio; $i <= $fin; $i++):
                                        ?>
                                            <li class="page-item <?php echo $i == $pagina ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <li class="page-item <?php echo $pagina >= $totalPaginas ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>">
                                                Siguiente <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>

                                        <li class="page-item <?php echo $pagina >= $totalPaginas ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $totalPaginas])); ?>">
                                                <i class="fas fa-angle-double-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>

                                <div class="text-center text-muted mt-3">
                                    <small>
                                        Mostrando <?php echo (($pagina - 1) * $porPagina) + 1; ?> -
                                        <?php echo min($pagina * $porPagina, $totalExpediciones); ?>
                                        de <?php echo number_format($totalExpediciones); ?> expediciones
                                    </small>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalVerItemsEnriquecido" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-boxes me-2"></i>Items de Expedición (Con datos de producción) - <span id="numeroExpedicionItemsEnriquecido"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Producto</th>
                                    <th>Tipo</th>
                                    <th>Clientes</th>
                                    <th class="text-center">Items</th>
                                    <th class="text-center">Peso Bruto</th>
                                    <th class="text-center">Peso Líquido</th>
                                    <th class="text-center">Metragem</th>
                                    <th class="text-center">Bobinas</th>
                                    <th class="text-center">Órdenes Prod.</th>
                                </tr>
                            </thead>
                            <tbody id="tablaItemsEnriquecidos">
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const DESPACHO_CONFIG = {
            urlBase: "<?php echo $url_base; ?>",
            usuario: "<?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?>",
            moduloVersion: "<?php echo $configuracion['version_modulo']; ?>",
            debug: <?php echo isset($_GET['debug']) ? 'true' : 'false'; ?>
        };

        function verDetalleExpedicionEnriquecido(numeroExpedicion) {
            document.getElementById('numeroExpedicionItemsEnriquecido').textContent = numeroExpedicion;

            fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `accion=obtener_items_expedicion&numero_expedicion=${encodeURIComponent(numeroExpedicion)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const tbody = document.getElementById('tablaItemsEnriquecidos');
                        tbody.innerHTML = '';

                        if (data.items && data.items.length > 0) {
                            data.items.forEach(item => {
                                const row = `
                                <tr>
                                    <td><strong>${item.nombre_producto}</strong></td>
                                    <td><span class="badge bg-secondary">${item.tipo_producto || 'N/A'}</span></td>
                                    <td><small>${item.clientes_list || 'N/A'}</small></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary">${item.cantidad_items}</span>
                                    </td>
                                    <td class="text-center">
                                        <strong>${item.peso_bruto_formateado || '0.00 kg'}</strong>
                                    </td>
                                    <td class="text-center">
                                        <strong>${item.peso_liquido_formateado || '0.00 kg'}</strong>
                                    </td>
                                    <td class="text-center">
                                        ${item.metragem_formateada || '0 m'}
                                    </td>
                                    <td class="text-center">
                                        ${item.bobinas_formateadas || '0'}
                                    </td>
                                    <td class="text-center">
                                        <small>${item.ordenes_produccion_list || 'N/A'}</small>
                                    </td>
                                </tr>
                            `;
                                tbody.innerHTML += row;
                            });
                        } else {
                            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No hay items registrados</td></tr>';
                        }

                        new bootstrap.Modal(document.getElementById('modalVerItemsEnriquecido')).show();
                    } else {
                        mostrarToast('Error al cargar items: ' + (data.error || 'Error desconocido'), 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarToast('Error de conexión al cargar items', 'danger');
                });
        }

        function abrirResumenPDF(numeroExpedicion) {
            const url = `pdf/resumen.php?expedicion=${encodeURIComponent(numeroExpedicion)}`;
            window.open(url, '_blank');
            mostrarToast(`Abriendo PDF de resumen para expedición ${numeroExpedicion}`, 'info');
        }

        function limpiarFiltros() {
            window.location.href = window.location.pathname;
        }

        function actualizarVista() {
            window.location.reload();
        }

        function mostrarToast(mensaje, tipo = 'info') {
            const iconos = {
                'success': 'fas fa-check',
                'danger': 'fas fa-exclamation-triangle',
                'warning': 'fas fa-exclamation',
                'info': 'fas fa-info'
            };

            const toast = document.createElement('div');
            toast.className = `alert alert-${tipo} position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; opacity: 0.95;';
            toast.innerHTML = `
                <i class="${iconos[tipo]} me-2"></i>
                ${mensaje}
            `;
            document.body.appendChild(toast);

            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 4000);
        }
    </script>
</body>

