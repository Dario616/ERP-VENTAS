<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '3']);

if (file_exists("controllers/RelatorioController.php")) {
    include "controllers/RelatorioController.php";
} else {
    die("Error: No se pudo cargar el controlador de relatorios.");
}

$controller = new RelatorioController($conexion, $url_base);

if (!$controller->verificarPermisos()) {
    header("Location: " . $url_base . "secciones/contable/main.php?error=No tienes permisos para acceder a esta sección");
    exit();
}

// Manejo de peticiones API
if ($controller->handleApiRequest()) {
    exit();
}

// Procesar filtros
$filtros = $controller->procesarFiltros();

// CAMBIO PRINCIPAL: Siempre obtener datos del reporte (en lugar de solo cuando hay filtros)
$reporte = $controller->obtenerReportePagos($filtros);
$estadisticas = $controller->obtenerEstadisticasGenerales($filtros);

// Obtener datos para la vista
$datosVista = $controller->obtenerDatosVista('Reporte de Pagos por Cliente');
$configuracionJS = $controller->obtenerConfiguracionJS();
$mensajes = $controller->manejarMensajes();

$listaVendedores = $controller->obtenerListaVendedores();
$listaClientes = $controller->obtenerListaClientes();
$formasPago = $controller->obtenerFormasPago();
$mesesDisponibles = $controller->obtenerMesesDisponibles();

// ✅ OBTENER DATOS REALES (sin simulación)
$datosGraficos = [];
$datosCumplimientoReales = [];
$datosClientesReales = [];
$diasAtrasoReales = [];

if ($reporte && !empty($reporte['clientes'])) {
    try {
        // ✅ 1. Datos reales de cumplimiento de fechas
        $datosCumplimientoReales = $controller->obtenerDatosCumplimientoFechas($filtros);

        // ✅ 2. Datos reales de clientes con puntajes basados en fechas de vencimiento
        $datosClientesReales = $controller->obtenerDatosClientesConPuntajeReal($filtros);

        // ✅ 3. Datos reales de días de atraso
        $diasAtrasoReales = $controller->obtenerEstadisticasAtrasoReales($filtros);

        error_log("Datos reales obtenidos - Cumplimiento: " . count($datosCumplimientoReales) . ", Clientes: " . count($datosClientesReales) . ", Atrasos: " . json_encode($diasAtrasoReales));
    } catch (Exception $e) {
        error_log("Error obteniendo datos reales: " . $e->getMessage());
        $datosCumplimientoReales = [];
        $datosClientesReales = [];
        $diasAtrasoReales = [
            'al_dia' => 0,
            'atraso_1_7' => 0,
            'atraso_8_15' => 0,
            'atraso_16_30' => 0,
            'atraso_mas_30' => 0
        ];
    }

    // Preparar datos básicos para gráficos (solo los que no necesitan simulación)
    $monedas = [];
    $vendedores = [];
    $formasPagoStats = [];
    $evolucionTemporal = [];

    foreach ($reporte['clientes'] as $cliente) {
        foreach ($cliente['monedas'] as $moneda) {
            // Agrupar por moneda
            if (!isset($monedas[$moneda['moneda']])) {
                $monedas[$moneda['moneda']] = [
                    'total_monto' => 0,
                    'total_pagos' => 0,
                    'clientes' => 0
                ];
            }
            $monedas[$moneda['moneda']]['total_monto'] += $moneda['total_monto'];
            $monedas[$moneda['moneda']]['total_pagos'] += $moneda['total_pagos'];
            $monedas[$moneda['moneda']]['clientes']++;

            // Agrupar por vendedor
            if (!isset($vendedores[$cliente['vendedor']])) {
                $vendedores[$cliente['vendedor']] = [
                    'total_monto' => 0,
                    'total_pagos' => 0,
                    'clientes' => []
                ];
            }
            $vendedores[$cliente['vendedor']]['total_monto'] += $moneda['total_monto'];
            $vendedores[$cliente['vendedor']]['total_pagos'] += $moneda['total_pagos'];
            if (!in_array($cliente['nombre'], $vendedores[$cliente['vendedor']]['clientes'])) {
                $vendedores[$cliente['vendedor']]['clientes'][] = $cliente['nombre'];
            }

            // Formas de pago
            foreach ($moneda['formas_pago'] as $forma) {
                if (!isset($formasPagoStats[$forma])) {
                    $formasPagoStats[$forma] = 0;
                }
                $formasPagoStats[$forma] += $moneda['total_pagos'];
            }

            // Evolución temporal
            if (isset($moneda['detalle_mensual']) && is_array($moneda['detalle_mensual'])) {
                foreach ($moneda['detalle_mensual'] as $clave => $detalle) {
                    if (!isset($evolucionTemporal[$clave])) {
                        $evolucionTemporal[$clave] = [
                            'fecha' => $clave,
                            'total_monto' => 0,
                            'total_pagos' => 0,
                            'año' => $detalle['año'],
                            'mes' => $detalle['mes']
                        ];
                    }
                    $evolucionTemporal[$clave]['total_monto'] += $detalle['total_monto'];
                    $evolucionTemporal[$clave]['total_pagos'] += $detalle['total_pagos'];
                }
            }
        }
    }

    // Ordenar evolución temporal
    ksort($evolucionTemporal);

    // ✅ USAR DATOS REALES en lugar de simulados
    $datosGraficos = [
        'monedas' => $monedas,
        'vendedores' => $vendedores,
        'formas_pago' => $formasPagoStats,
        'clientes_top' => $datosClientesReales, // ✅ DATOS REALES
        'evolucion_temporal' => array_values($evolucionTemporal),
        'dias_atraso' => $diasAtrasoReales // ✅ DATOS REALES
    ];
}

// ========== CONFIGURACIÓN DE PAGINACIÓN ==========
$itemsPorPagina = 10;
$paginaActual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Preparar datos planos para paginación
$datosPlanos = [];
if ($reporte && !empty($reporte['clientes'])) {
    foreach ($reporte['clientes'] as $cliente) {
        foreach ($cliente['monedas'] as $moneda) {
            $datosPlanos[] = [
                'cliente_data' => $cliente,
                'moneda_data' => $moneda
            ];
        }
    }
}

// Calcular paginación
$totalRegistros = count($datosPlanos);
$totalPaginas = ceil($totalRegistros / $itemsPorPagina);
$offset = ($paginaActual - 1) * $itemsPorPagina;
$datosPagina = array_slice($datosPlanos, $offset, $itemsPorPagina);

// Función para generar URL con parámetros
function generarUrlPaginacion($pagina, $filtros)
{
    $params = $filtros;
    $params['page'] = $pagina;
    return '?' . http_build_query($params);
}

$controller->logActividad('Acceso a reporte de pagos', 'Filtros: ' . json_encode($filtros));
$breadcrumb_items = ['Sector Contable', 'Reporte de Pagos'];
$item_urls = [
    $url_base . 'secciones/contable/main.php',
];
$additional_css = [$url_base . 'secciones/contable/utils/styles.css'];
include $path_base . "components/head.php";
?>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <!-- Mensajes de éxito/error -->
        <?php if (!empty($mensajes['mensaje'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $mensajes['mensaje']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensajes['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $mensajes['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="card mb-4 no-print">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2"></i>Filtros de Búsqueda
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" id="formFiltros">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="fecha_desde" class="form-label">Fecha Desde</label>
                            <input type="date" class="form-control" id="fecha_desde" name="fecha_desde"
                                value="<?php echo $filtros['fecha_desde'] ?? $datosVista['config_fechas']['fecha_defecto_desde']; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                            <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta"
                                value="<?php echo $filtros['fecha_hasta'] ?? $datosVista['config_fechas']['fecha_defecto_hasta']; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="cliente" class="form-label">Cliente</label>
                            <input type="text"
                                class="form-control"
                                id="cliente"
                                name="cliente"
                                list="lista_clientes"
                                placeholder="Escribir nombre del cliente..."
                                value="<?php echo htmlspecialchars($filtros['cliente'] ?? ''); ?>">

                            <datalist id="lista_clientes">
                                <?php foreach ($listaClientes as $cliente): ?>
                                    <option value="<?php echo htmlspecialchars($cliente); ?>">
                                    <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="moneda" class="form-label">Moneda</label>
                            <select class="form-select" id="moneda" name="moneda">
                                <option value="">Todas las monedas</option>
                                <option value="Guaraníes" <?php echo (isset($filtros['moneda']) && $filtros['moneda'] === 'Guaraníes') ? 'selected' : ''; ?>>₲ Guaraníes</option>
                                <option value="Dólares" <?php echo (isset($filtros['moneda']) && $filtros['moneda'] === 'Dólares') ? 'selected' : ''; ?>>U$D Dólares</option>
                                <option value="Real brasileño" <?php echo (isset($filtros['moneda']) && $filtros['moneda'] === 'Real brasileño') ? 'selected' : ''; ?>>R$ Real brasileño</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="vendedor" class="form-label">Vendedor</label>
                            <select class="form-select" id="vendedor" name="vendedor">
                                <option value="">Todos los vendedores</option>
                                <?php foreach ($listaVendedores as $vendedor): ?>
                                    <option value="<?php echo htmlspecialchars($vendedor); ?>"
                                        <?php echo (isset($filtros['vendedor']) && $filtros['vendedor'] === $vendedor) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($vendedor); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="forma_pago" class="form-label">Forma de Pago</label>
                            <select class="form-select" id="forma_pago" name="forma_pago">
                                <option value="">Todas las formas</option>
                                <?php foreach ($formasPago as $forma): ?>
                                    <option value="<?php echo htmlspecialchars($forma); ?>"
                                        <?php echo (isset($filtros['forma_pago']) && $filtros['forma_pago'] === $forma) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($forma); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <div class="btn-group w-100" role="group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Buscar
                                </button>
                                <a href="relatorio_pagos.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-broom me-1"></i>Limpiar
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!$reporte || empty($reporte['clientes'])): ?>
            <!-- Sin resultados -->
            <div class="text-center py-5">
                <i class="fas fa-search fa-4x text-muted mb-3"></i>
                <h4>No se encontraron datos</h4>
                <p class="text-muted">No hay pagos registrados para los filtros seleccionados. Intenta con diferentes criterios de búsqueda.</p>
            </div>
        <?php else: ?>

            <!-- Dashboard de Estadísticas -->
            <div class="dashboard-section no-print">
                <h4 class="mb-4">
                    <i class="fas fa-chart-pie me-2"></i>Dashboard de Estadísticas
                </h4>
                <!-- Gráficos -->
                <div class="row mb-4">
                    <!-- Distribución por Moneda -->
                    <div class="col-lg-2 mb-4">
                        <div class="chart-container">
                            <h6 class="text-center mb-3">
                                <i class="fas fa-chart-pie me-2"></i>Distribución por Moneda
                            </h6>
                            <canvas id="chartMonedas"></canvas>
                        </div>
                    </div>
                    <!-- Formas de Pago -->
                    <div class="col-lg-2 mb-4">
                        <div class="chart-container">
                            <h6 class="text-center mb-3">
                                <i class="fas fa-credit-card me-2"></i>Formas de Pago
                            </h6>
                            <canvas id="chartFormasPago"></canvas>
                        </div>
                    </div>
                    <!-- Días de Atraso -->
                    <div class="col-lg-2 mb-4">
                        <div class="chart-container">
                            <h6 class="text-center mb-3">
                                <i class="fas fa-clock me-2"></i>Días de Atraso
                            </h6>
                            <canvas id="chartDiasAtraso"></canvas>
                        </div>
                    </div>
                    <!-- Top 5 Clientes por Puntaje de Cumplimiento -->
                    <div class="col-lg-6 mb-4">
                        <div class="chart-container">
                            <h6 class="text-center mb-3">
                                <i class="fas fa-medal me-2"></i>Top 5 Clientes - Puntaje de Cumplimiento
                            </h6>
                            <canvas id="chartTopClientes"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Cumplimiento de Fechas de Pago -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="chart-container chart-container-tall">
                            <h5 class="text-center mb-3">
                                <i class="fas fa-calendar-check me-2"></i>Cumplimiento de Fechas de Pago
                                <small class="text-muted d-block">Vencimiento vs Pago Real</small>
                            </h5>
                            <canvas id="chartCumplimientoPagos"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toolbar con información de paginación -->
            <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                <h4 class="mb-0">
                    <i class="fas fa-users me-2"></i>Detalle por Cliente
                </h4>
                <div class="d-flex align-items-center">
                    <span class="badge bg-primary me-3">
                        <?php echo $totalRegistros; ?> registros totales
                    </span>
                    <span class="text-muted">
                        Mostrando <?php echo $offset + 1; ?>-<?php echo min($offset + $itemsPorPagina, $totalRegistros); ?>
                        de <?php echo $totalRegistros; ?> registros
                    </span>
                </div>
            </div>

            <!-- Tabla de Resultados -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th><i class="fas fa-user me-1"></i>Cliente</th>
                                    <th><i class="fas fa-calendar me-1"></i>Período</th>
                                    <th><i class="fas fa-shopping-cart me-1"></i>Pagos</th>
                                    <th><i class="fas fa-coins me-1"></i>Moneda</th>
                                    <th><i class="fas fa-dollar-sign me-1"></i>Total</th>
                                    <th><i class="fas fa-user-tie me-1"></i>Vendedor</th>
                                    <th><i class="fas fa-credit-card me-1"></i>Condición</th>
                                    <th><i class="fas fa-chart-line me-1"></i>Estado</th>
                                    <th style="width: 120px;"><i class="fas fa-cog me-1"></i>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($datosPagina as $item):
                                    $cliente = $item['cliente_data'];
                                    $moneda = $item['moneda_data'];
                                ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($cliente['nombre']); ?></div>
                                            <small class="text-muted"><?php echo $cliente['totales_cliente']['total_ventas']; ?> ventas</small>
                                        </td>
                                        <td>
                                            <?php if ($moneda['primera_fecha_formateada'] && $moneda['ultima_fecha_formateada']): ?>
                                                <small>
                                                    <?php echo $moneda['primera_fecha_formateada']; ?><br>
                                                    <?php echo $moneda['ultima_fecha_formateada']; ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $moneda['total_pagos']; ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $badgeClass = 'bg-secondary';
                                            if ($moneda['moneda'] == 'Guaraníes') $badgeClass = 'bg-success';
                                            if ($moneda['moneda'] == 'Dólares') $badgeClass = 'bg-primary';
                                            if ($moneda['moneda'] == 'Real brasileño') $badgeClass = 'bg-warning text-dark';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>">
                                                <?php echo $moneda['simbolo_moneda'] . $moneda['moneda']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-success"><?php echo $moneda['simbolo_moneda'] . $moneda['total_ventas_monto_formateado']; ?></div>
                                            <small class="text-danger">Pagado: <?php echo $moneda['simbolo_moneda'] . $moneda['total_monto_formateado']; ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($cliente['vendedor']); ?></td>
                                        <td>
                                            <?php if (!empty($moneda['formas_pago'])): ?>
                                                <span class="badge bg-info"><?php echo implode(', ', array_slice($moneda['formas_pago'], 0, 2)); ?></span>
                                                <?php if (count($moneda['formas_pago']) > 2): ?>
                                                    <small class="text-muted">+<?php echo count($moneda['formas_pago']) - 2; ?> más</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">Procesado</span>
                                        </td>
                                        <td>
                                            <a href="relatorio_pagos_ver.php?cliente=<?php echo urlencode($cliente['nombre']); ?>&moneda=<?php echo urlencode($moneda['moneda']); ?>"
                                                class="btn btn-sm btn-primary"
                                                title="Ver detalles completos del cliente">
                                                <i class="fas fa-eye me-1"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Controles de Paginación -->
            <?php if ($totalPaginas > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4 no-print">
                    <div class="text-muted">
                        Página <?php echo $paginaActual; ?> de <?php echo $totalPaginas; ?>
                    </div>

                    <nav aria-label="Paginación de resultados">
                        <ul class="pagination pagination-sm mb-0">
                            <!-- Botón Anterior -->
                            <?php if ($paginaActual > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo generarUrlPaginacion($paginaActual - 1, $filtros); ?>" aria-label="Anterior">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link">&laquo;</span>
                                </li>
                            <?php endif; ?>

                            <!-- Números de página -->
                            <?php
                            $inicio = max(1, $paginaActual - 2);
                            $fin = min($totalPaginas, $paginaActual + 2);

                            // Mostrar primera página si no está en el rango
                            if ($inicio > 1) {
                                echo '<li class="page-item"><a class="page-link" href="' . generarUrlPaginacion(1, $filtros) . '">1</a></li>';
                                if ($inicio > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            // Páginas en el rango
                            for ($i = $inicio; $i <= $fin; $i++): ?>
                                <li class="page-item <?php echo ($i == $paginaActual) ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo generarUrlPaginacion($i, $filtros); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor;

                            // Mostrar última página si no está en el rango
                            if ($fin < $totalPaginas) {
                                if ($fin < $totalPaginas - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="' . generarUrlPaginacion($totalPaginas, $filtros) . '">' . $totalPaginas . '</a></li>';
                            }
                            ?>

                            <!-- Botón Siguiente -->
                            <?php if ($paginaActual < $totalPaginas): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo generarUrlPaginacion($paginaActual + 1, $filtros); ?>" aria-label="Siguiente">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link">&raquo;</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>

                    <!-- Selector rápido de página -->
                    <div class="d-flex align-items-center">
                        <span class="text-muted me-2">Ir a:</span>
                        <select class="form-select form-select-sm" style="width: auto;" onchange="window.location.href='<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>' + '<?php echo http_build_query(array_merge($filtros, ['page' => ''])); ?>' + this.value">
                            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($i == $paginaActual) ? 'selected' : ''; ?>>
                                    Página <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?php echo $url_base; ?>config/notificacion/sistema-notificaciones.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Validación de fechas
            const fechaDesde = document.getElementById('fecha_desde');
            const fechaHasta = document.getElementById('fecha_hasta');

            if (fechaDesde) {
                fechaDesde.addEventListener('change', function() {
                    const fechaDesdeValue = this.value;
                    const fechaHastaValue = fechaHasta?.value;

                    if (fechaDesdeValue && fechaHastaValue && fechaDesdeValue > fechaHastaValue) {
                        fechaHasta.value = fechaDesdeValue;
                    }
                });
            }

            if (fechaHasta) {
                fechaHasta.addEventListener('change', function() {
                    const fechaHastaValue = this.value;
                    const fechaDesdeValue = fechaDesde?.value;

                    if (fechaDesdeValue && fechaHastaValue && fechaHastaValue < fechaDesdeValue) {
                        fechaDesde.value = fechaHastaValue;
                    }
                });
            }

            // Función para actualizar URL manteniendo filtros
            function actualizarPagina(numeroPagina) {
                const url = new URL(window.location);
                url.searchParams.set('page', numeroPagina);
                window.location.href = url.toString();
            }

            // Agregar funcionalidad de teclado para navegación
            document.addEventListener('keydown', function(e) {
                // Solo si no estamos escribiendo en un input
                if (e.target.tagName.toLowerCase() !== 'input' && e.target.tagName.toLowerCase() !== 'textarea' && e.target.tagName.toLowerCase() !== 'select') {
                    const paginaActual = <?php echo $paginaActual; ?>;
                    const totalPaginas = <?php echo $totalPaginas; ?>;

                    if (e.key === 'ArrowLeft' && paginaActual > 1) {
                        actualizarPagina(paginaActual - 1);
                    } else if (e.key === 'ArrowRight' && paginaActual < totalPaginas) {
                        actualizarPagina(paginaActual + 1);
                    }
                }
            });

            // Mostrar información de navegación con teclado
            const infoNavegacion = document.createElement('small');
            infoNavegacion.className = 'text-muted d-none d-md-block mt-2';
            infoNavegacion.innerHTML = '<i class="fas fa-info-circle me-1"></i>Usa las flechas del teclado ← → para navegar entre páginas';

            const paginacionContainer = document.querySelector('.d-flex.justify-content-between.align-items-center.mt-4');
            if (paginacionContainer && <?php echo $totalPaginas; ?> > 1) {
                paginacionContainer.appendChild(infoNavegacion);
            }

            // Inicializar gráficos si hay datos
            <?php if (!empty($datosGraficos) && !empty($reporte['clientes'])): ?>
                setTimeout(initCharts, 100);
            <?php endif; ?>
        });

        // Función para mostrar notificaciones
        function mostrarNotificacion(mensaje, tipo) {
            if (typeof mostrarMensaje === 'function') {
                mostrarMensaje(mensaje, tipo);
            } else {
                alert(mensaje);
            }
        }

        <?php if (!empty($datosGraficos) && !empty($reporte['clientes'])): ?>
            // ✅ TODOS LOS DATOS SON REALES AHORA
            const datosGraficos = <?php echo json_encode($datosGraficos); ?>;
            const datosCumplimientoReales = <?php echo json_encode($datosCumplimientoReales); ?>;

            console.log('📊 Datos gráficos (REALES):', datosGraficos);
            console.log('📅 Datos cumplimiento (REALES):', datosCumplimientoReales);

            function initCharts() {
                if (typeof Chart === 'undefined') return;

                // ===== GRÁFICO DE MONEDAS =====
                const ctxMonedas = document.getElementById('chartMonedas');
                if (ctxMonedas && datosGraficos.monedas && Object.keys(datosGraficos.monedas).length > 0) {
                    const monedaLabels = Object.keys(datosGraficos.monedas);
                    const monedaData = Object.values(datosGraficos.monedas).map(m => parseFloat(m.total_monto) || 0);
                    const monedaColors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'];

                    new Chart(ctxMonedas, {
                        type: 'pie',
                        data: {
                            labels: monedaLabels,
                            datasets: [{
                                data: monedaData,
                                backgroundColor: monedaColors.slice(0, monedaLabels.length),
                                borderWidth: 0,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        padding: 10,
                                        usePointStyle: true,
                                        font: {
                                            size: 10
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const total = monedaData.reduce((a, b) => a + b, 0);
                                            const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : '0';
                                            return context.label + ': ' + percentage + '%';
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                // ===== GRÁFICO DE FORMAS DE PAGO =====
                const ctxFormasPago = document.getElementById('chartFormasPago');
                if (ctxFormasPago && datosGraficos.formas_pago && Object.keys(datosGraficos.formas_pago).length > 0) {
                    const formasLabels = Object.keys(datosGraficos.formas_pago);
                    const formasData = Object.values(datosGraficos.formas_pago).map(f => parseInt(f) || 0);

                    new Chart(ctxFormasPago, {
                        type: 'pie',
                        data: {
                            labels: formasLabels,
                            datasets: [{
                                data: formasData,
                                backgroundColor: [
                                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                                    '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
                                ],
                                borderWidth: 0,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        padding: 10,
                                        usePointStyle: true,
                                        font: {
                                            size: 10
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.label + ': ' + context.raw + ' pagos';
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                // ===== ✅ GRÁFICO DE TOP 5 CLIENTES (DATOS REALES) =====
                const ctxTopClientes = document.getElementById('chartTopClientes');
                if (ctxTopClientes && datosGraficos.clientes_top && datosGraficos.clientes_top.length > 0) {
                    const clientesTop5 = datosGraficos.clientes_top.slice(0, 5);
                    const clientesLabels = clientesTop5.map(c => c.nombre);
                    const clientesPuntajes = clientesTop5.map(c => parseFloat(c.puntaje_cumplimiento) || 0);

                    // Definir colores basados en el puntaje
                    const clientesColors = clientesPuntajes.map(puntaje => {
                        if (puntaje >= 90) return '#28a745'; // Verde - Excelente
                        if (puntaje >= 80) return '#20c997'; // Verde claro - Muy bueno
                        if (puntaje >= 70) return '#ffc107'; // Amarillo - Bueno
                        if (puntaje >= 60) return '#fd7e14'; // Naranja - Regular
                        return '#dc3545'; // Rojo - Malo
                    });

                    new Chart(ctxTopClientes, {
                        type: 'bar',
                        data: {
                            labels: clientesLabels,
                            datasets: [{
                                label: 'Puntaje de Cumplimiento',
                                data: clientesPuntajes,
                                backgroundColor: clientesColors,
                                borderColor: clientesColors.map(color => color + 'CC'),
                                borderWidth: 2,
                                borderRadius: 8,
                                borderSkipped: false,
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    max: 100,
                                    ticks: {
                                        callback: function(value) {
                                            return value + ' pts';
                                        }
                                    },
                                    title: {
                                        display: true,
                                    }
                                },
                                y: {
                                    ticks: {
                                        font: {
                                            size: 11
                                        }
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        title: function(context) {
                                            const index = context[0].dataIndex;
                                            return clientesTop5[index].nombre;
                                        },
                                        label: function(context) {
                                            const index = context.dataIndex;
                                            const cliente = clientesTop5[index];
                                            const puntaje = context.raw;

                                            let nivel = '';
                                            if (puntaje >= 90) nivel = '🏆 Excelente';
                                            else if (puntaje >= 80) nivel = '🥇 Muy bueno';
                                            else if (puntaje >= 70) nivel = '🥈 Bueno';
                                            else if (puntaje >= 60) nivel = '🥉 Regular';
                                            else nivel = '⚠️ Necesita mejorar';

                                            return [
                                                `Puntaje: ${puntaje} puntos`,
                                                `Nivel: ${nivel}`,
                                                `Total de pagos: ${cliente.total_pagos}`,
                                                `Vendedor: ${cliente.vendedor}`
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                // ===== ✅ GRÁFICO DE DÍAS DE ATRASO (DATOS REALES) =====
                const ctxDiasAtraso = document.getElementById('chartDiasAtraso');
                if (ctxDiasAtraso && datosGraficos.dias_atraso) {
                    const atrasoLabels = ['Al día', '1-7 días', '8-15 días', '16-30 días', '+30 días'];
                    const atrasoData = [
                        datosGraficos.dias_atraso.al_dia || 0,
                        datosGraficos.dias_atraso.atraso_1_7 || 0,
                        datosGraficos.dias_atraso.atraso_8_15 || 0,
                        datosGraficos.dias_atraso.atraso_16_30 || 0,
                        datosGraficos.dias_atraso.atraso_mas_30 || 0
                    ];
                    const atrasoColors = ['#28a745', '#ffc107', '#fd7e14', '#dc3545', '#6f42c1'];

                    new Chart(ctxDiasAtraso, {
                        type: 'pie',
                        data: {
                            labels: atrasoLabels,
                            datasets: [{
                                data: atrasoData,
                                backgroundColor: atrasoColors,
                                borderWidth: 0,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        padding: 20,
                                        usePointStyle: true,
                                        font: {
                                            size: 9
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.label + ': ' + context.raw + ' pagos';
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                // ===== ✅ GRÁFICO DE CUMPLIMIENTO CON DATOS REALES =====
                const ctxCumplimiento = document.getElementById('chartCumplimientoPagos');
                if (ctxCumplimiento && datosCumplimientoReales && datosCumplimientoReales.length > 0) {

                    console.log('✅ Generando gráfico con datos REALES:', datosCumplimientoReales.length, 'registros');

                    // Usar datos reales en lugar de simulados
                    const etiquetas = datosCumplimientoReales.map((item, index) => `#${index + 1}`);

                    // Convertir timestamps a fechas
                    const fechasVencimiento = datosCumplimientoReales.map(item =>
                        new Date(item.timestamp_vencimiento * 1000)
                    );
                    const fechasPagoReal = datosCumplimientoReales.map(item =>
                        new Date(item.timestamp_pago_real * 1000)
                    );

                    // Usar días desde la fecha más antigua para mejor visualización
                    const fechaBase = new Date(Math.min(...fechasVencimiento, ...fechasPagoReal));

                    const diasVencimiento = fechasVencimiento.map(fecha =>
                        Math.floor((fecha - fechaBase) / (1000 * 60 * 60 * 24))
                    );
                    const diasPagoReal = fechasPagoReal.map(fecha =>
                        Math.floor((fecha - fechaBase) / (1000 * 60 * 60 * 24))
                    );

                    new Chart(ctxCumplimiento, {
                        type: 'line',
                        data: {
                            labels: etiquetas,
                            datasets: [{
                                label: 'Fecha de Vencimiento',
                                data: diasVencimiento,
                                borderColor: 'rgb(54, 162, 235)',
                                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                borderWidth: 3,
                                pointRadius: 5,
                                pointHoverRadius: 7,
                                tension: 0.1,
                                fill: false
                            }, {
                                label: 'Fecha de Pago Real',
                                data: diasPagoReal,
                                borderColor: 'rgb(255, 99, 132)',
                                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                                borderWidth: 3,
                                pointRadius: 5,
                                pointHoverRadius: 7,
                                tension: 0.1,
                                fill: '-1',
                                pointBackgroundColor: function(context) {
                                    const index = context.dataIndex;
                                    const item = datosCumplimientoReales[index];
                                    const diasDif = parseInt(item.dias_diferencia) || 0;

                                    if (diasDif > 5) return 'rgb(220, 53, 69)'; // Rojo - atraso grande
                                    if (diasDif > 0) return 'rgb(255, 193, 7)'; // Amarillo - atraso menor  
                                    return 'rgb(40, 167, 69)'; // Verde - a tiempo o adelantado
                                }
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            scales: {
                                x: {
                                    display: true,
                                    title: {
                                        display: true,
                                        text: 'Secuencia de Pagos'
                                    }
                                },
                                y: {
                                    display: true,
                                    title: {
                                        display: true,
                                        text: 'Días desde ' + fechaBase.toLocaleDateString('es-ES')
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            const fecha = new Date(fechaBase.getTime() + (value * 24 * 60 * 60 * 1000));
                                            return fecha.toLocaleDateString('es-ES', {
                                                day: '2-digit',
                                                month: '2-digit'
                                            });
                                        }
                                    }
                                }
                            },
                            plugins: {
                                title: {
                                    display: true,
                                    font: {
                                        size: 14
                                    }
                                },
                                legend: {
                                    display: true,
                                    position: 'top'
                                },
                                tooltip: {
                                    callbacks: {
                                        title: function(context) {
                                            const index = context[0].dataIndex;
                                            const item = datosCumplimientoReales[index];
                                            return `${item.cliente} - Cuota #${item.numero_cuota}`;
                                        },
                                        label: function(context) {
                                            const index = context.dataIndex;
                                            const item = datosCumplimientoReales[index];

                                            if (context.datasetIndex === 0) {
                                                // Fecha de Vencimiento
                                                return 'Vencimiento: ' +
                                                    new Date(item.timestamp_vencimiento * 1000).toLocaleDateString('es-ES');
                                            } else {
                                                // Fecha de Pago Real
                                                const diasDif = parseInt(item.dias_diferencia) || 0;
                                                let atrasoTexto = '';

                                                if (diasDif > 0) {
                                                    atrasoTexto = ` (${diasDif} días de atraso)`;
                                                } else if (diasDif < 0) {
                                                    atrasoTexto = ` (${Math.abs(diasDif)} días adelantado)`;
                                                } else {
                                                    atrasoTexto = ' (a tiempo)';
                                                }

                                                return 'Pago Real: ' +
                                                    new Date(item.timestamp_pago_real * 1000).toLocaleDateString('es-ES') +
                                                    atrasoTexto;
                                            }
                                        },
                                        afterLabel: function(context) {
                                            const index = context.dataIndex;
                                            const item = datosCumplimientoReales[index];

                                            if (context.datasetIndex === 1) { // Solo para pago real
                                                return [
                                                    `Venta #: ${item.id}`,
                                                    `Monto: ${parseFloat(item.monto_pago).toLocaleString('es-PY')}`,
                                                    `Estado: ${item.categoria_cumplimiento}`,
                                                    `Vendedor: ${item.vendedor}`
                                                ];
                                            }
                                            return '';
                                        }
                                    }
                                }
                            }
                        }
                    });
                } else {
                    console.log('❌ No hay datos de cumplimiento reales disponibles');
                }
            }
        <?php endif; ?>
    </script>
</body>

</html>