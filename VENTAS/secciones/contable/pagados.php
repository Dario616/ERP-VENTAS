<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '3']);

if (file_exists("controllers/CuentasCobrarController.php")) {
    include "controllers/CuentasCobrarController.php";
} else {
    die("Error: No se pudo cargar el controlador de cuentas por cobrar.");
}

$controller = new CuentasCobrarController($conexion, $url_base);

if (!$controller->verificarPermisos()) {
    header("Location: " . $url_base . "index.php?error=No tienes permisos para acceder a esta sección");
    exit();
}

if ($controller->handleApiRequest()) {
    exit();
}

$registrosPorPagina = 15;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

$filtros = $controller->procesarFiltrosPagados();

$resultado = $controller->obtenerCuentasPagadas($filtros, $paginaActual);
$cuentas = $resultado['cuentas'];
$totalRegistros = $resultado['total'];
$totalPaginas = $resultado['total_paginas'];

$datosVista = $controller->obtenerDatosVistaPagados('Cuentas Pagadas');
$mensajes = $controller->manejarMensajes();
$configuracionJS = $controller->obtenerConfiguracionJS();

$filtrosStr = !empty($filtros) ? 'Filtros: ' . json_encode($filtros) : 'Sin filtros';
$controller->logActividad('Consulta cuentas pagadas', $filtrosStr);
$breadcrumb_items = ['Sector Contable', 'Cuentas Pagadas'];
$item_urls = [
    $url_base . 'secciones/contable/main.php',
];
$additional_css = [$url_base . 'secciones/contable/utils/styles.css'];
include $path_base . "components/head.php";
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <!-- Mensajes -->
        <?php if (!empty($mensajes['mensaje'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($mensajes['mensaje']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensajes['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($mensajes['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <!-- Panel principal -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="fas fa-check-circle me-2 text-success"></i>Cuentas Pagadas
                </h4>
            </div>

            <div class="card-body">
                <!-- Filtros -->
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros de Búsqueda</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label for="id_venta" class="form-label">Código Venta</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                    <input type="text" class="form-control" id="id_venta" name="id_venta"
                                        value="<?php echo htmlspecialchars($filtros['id_venta'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label for="cliente" class="form-label">Cliente</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="cliente" name="cliente"
                                        value="<?php echo htmlspecialchars($filtros['cliente'] ?? ''); ?>"
                                        placeholder="Buscar por cliente...">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label for="fecha_desde" class="form-label">Venta Desde</label>
                                <input type="date" class="form-control" id="fecha_desde" name="fecha_desde"
                                    value="<?php echo htmlspecialchars($filtros['fecha_desde'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="fecha_hasta" class="form-label">Venta Hasta</label>
                                <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta"
                                    value="<?php echo htmlspecialchars($filtros['fecha_hasta'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="fecha_pago_desde" class="form-label">Pago Desde</label>
                                <input type="date" class="form-control" id="fecha_pago_desde" name="fecha_pago_desde"
                                    value="<?php echo htmlspecialchars($filtros['fecha_pago_desde'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="fecha_pago_hasta" class="form-label">Pago Hasta</label>
                                <input type="date" class="form-control" id="fecha_pago_hasta" name="fecha_pago_hasta"
                                    value="<?php echo htmlspecialchars($filtros['fecha_pago_hasta'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <div class="btn-group" role="group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filtrar
                                    </button>
                                    <a href="pagados.php" class="btn btn-secondary">
                                        <i class="fas fa-sync-alt me-1"></i>Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de cuentas pagadas -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-success">
                            <tr>
                                <th><i class="fas fa-hashtag me-1"></i>Venta</th>
                                <th><i class="fas fa-user me-1"></i>Cliente</th>
                                <th><i class="fas fa-list-ol me-1"></i>Cuotas</th>
                                <th><i class="fas fa-calendar me-1"></i>Fecha Venta</th>
                                <th><i class="fas fa-calendar-check me-1"></i>Último Pago</th>
                                <th><i class="fas fa-money-bill me-1"></i>Total Cobrado</th>
                                <th><i class="fas fa-info-circle me-1"></i>Estado</th>
                                <th class="text-center"><i class="fas fa-cogs me-1"></i>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cuentas)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i>No hay cuentas pagadas con los filtros aplicados
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cuentas as $venta): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?php echo htmlspecialchars($venta['id_venta']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($venta['vendedor']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($venta['cliente']); ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $venta['total_cuotas']; ?> cuotas</span>
                                            <br><small><?php echo htmlspecialchars($venta['tipocredito']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo $venta['fecha_venta_formateada']; ?>
                                            <?php if (!empty($venta['tiene_fecha_personalizada'])): ?>
                                                <br><small class="text-primary">
                                                    <i class="fas fa-calendar-check me-1"></i>Inicio personalizado
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo $venta['fecha_ultimo_pago_formateada']; ?></strong>
                                            <br><small class="<?php echo $venta['dias_pago_class']; ?>">
                                                <?php echo $venta['dias_pago_texto']; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php echo $venta['simbolo_moneda'] . $venta['monto_total_pagado_formateado']; ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i>Pagado
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="ver_cuota_detalle.php?id=<?php echo $venta['primera_cuota_id']; ?>"
                                                    class="btn btn-info" title="Ver detalles de la venta">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($totalPaginas > 1): ?>
                    <nav aria-label="Paginación de cuentas pagadas" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $paginaActual <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $controller->generarUrlConParametros('pagados.php', array_merge($filtros, ['pagina' => $paginaActual - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </a>
                            </li>

                            <?php
                            $inicio = max(1, $paginaActual - 2);
                            $fin = min($totalPaginas, $paginaActual + 2);

                            for ($i = $inicio; $i <= $fin; $i++):
                            ?>
                                <li class="page-item <?php echo $paginaActual == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo $controller->generarUrlConParametros('pagados.php', array_merge($filtros, ['pagina' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?php echo $paginaActual >= $totalPaginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $controller->generarUrlConParametros('pagados.php', array_merge($filtros, ['pagina' => $paginaActual + 1])); ?>">
                                    Siguiente <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <div class="text-center text-muted">
                        <small>
                            Mostrando <?php echo (($paginaActual - 1) * $registrosPorPagina) + 1; ?> -
                            <?php echo min($paginaActual * $registrosPorPagina, $totalRegistros); ?>
                            de <?php echo $totalRegistros; ?> registros
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $url_base; ?>config/notificacion/sistema-notificaciones.js"></script>

    <script>
        const PAGADOS_CONFIG = <?php echo json_encode($configuracionJS); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh cada 10 minutos
            setInterval(function() {
                console.log('Auto-refresh cuentas pagadas');
            }, 10 * 60 * 1000);

            // Tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            console.log('Página de cuentas pagadas inicializada correctamente');
            console.log('Total de registros cargados:', <?php echo $totalRegistros; ?>);
        });
    </script>
</body>

</html>