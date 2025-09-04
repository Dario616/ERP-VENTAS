<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '3']);

if (file_exists("controllers/ContableController.php")) {
    include "controllers/ContableController.php";
} else {
    die("Error: No se pudo cargar el controlador contable.");
}

$controller = new ContableController($conexion, $url_base);

if (!$controller->verificarPermisos()) {
    header("Location: " . $url_base . "index.php?error=No tienes permisos para acceder a esta sección");
    exit();
}

if ($controller->handleApiRequest()) {
    exit();
}

$registrosPorPagina = 10;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

$filtros = $controller->procesarFiltros();

$resultado = $controller->obtenerAutorizacionesPendientes($filtros, $paginaActual);
$autorizaciones = $resultado['autorizaciones'];
$totalRegistros = $resultado['total'];
$totalPaginas = $resultado['total_paginas'];

$datosVista = $controller->obtenerDatosVista('Autorizaciones Pendientes');
$mensajes = $controller->manejarMensajes();
$configuracionJS = $controller->obtenerConfiguracionJS();

$filtrosStr = !empty($filtros['cliente']) || !empty($filtros['vendedor']) ?
    'Filtros aplicados: ' . json_encode($filtros) : 'Sin filtros';
$controller->logActividad('Consulta autorizaciones pendientes', $filtrosStr);
$breadcrumb_items = ['Sector Contable', 'Autorizaciones Pendientes'];
$item_urls = [
    $url_base . 'secciones/contable/main.php',
];
$additional_css = [$url_base . 'secciones/contable/utils/styles.css'];
include $path_base . "components/head.php";
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="fas fa-clock me-2"></i>Autorizaciones Pendientes de Revisión
                </h4>
                <div>
                    <span class="badge bg-danger fs-6"><?php echo $totalRegistros; ?> pendientes</span>
                </div>
            </div>

            <div class="card-body">
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

                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo $url_base; ?>secciones/contable/index.php" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="id_venta" class="form-label">Código Venta</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                    <input type="text"
                                        class="form-control"
                                        id="id_venta"
                                        name="id_venta"
                                        value="<?php echo htmlspecialchars($filtros['id_venta'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="cliente" class="form-label">Cliente</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="cliente" name="cliente"
                                        value="<?php echo htmlspecialchars($filtros['cliente'] ?? ''); ?>"
                                        placeholder="Buscar por cliente...">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label for="vendedor" class="form-label">Vendedor</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                                    <input type="text" class="form-control" id="vendedor" name="vendedor"
                                        value="<?php echo htmlspecialchars($filtros['vendedor'] ?? ''); ?>"
                                        placeholder="Buscar por vendedor...">
                                </div>
                            </div>

                            <div class="col-md-3 d-flex align-items-end">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filtrar
                                    </button>
                                    <a href="<?php echo $url_base; ?>secciones/contable/index.php" class="btn btn-secondary">
                                        <i class="fas fa-sync-alt me-1"></i>Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th><i class="fas fa-hashtag me-1"></i></th>
                                <th><i class="fas fa-user me-1"></i>Cliente</th>
                                <th><i class="fas fa-user-tie me-1"></i>Vendedor</th>
                                <th><i class="fas fa-money-bill-wave me-1"></i>Monto Total</th>
                                <th><i class="fas fa-credit-card me-1"></i>Condición</th>
                                <th><i class="fas fa-calendar me-1"></i>Fecha Envio</th>
                                <th><i class="fas fa-calendar me-1"></i>Fecha Venta</th>
                                <th><i class="fas fa-file-alt me-1"></i>Descripción</th>
                                <th class="text-center"><i class="fas fa-cogs me-1"></i>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($autorizaciones)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i>No hay autorizaciones pendientes
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($autorizaciones as $autorizacion): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($autorizacion['id']); ?></td>
                                        <td><?php echo htmlspecialchars($autorizacion['cliente']); ?></td>
                                        <td><?php echo htmlspecialchars($autorizacion['nombre_vendedor']); ?></td>
                                        <td>
                                            <?php echo $autorizacion['simbolo_moneda'] . $autorizacion['monto_formateado']; ?>
                                        </td>
                                        <td>
                                            <?php if ($autorizacion['es_credito']): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-credit-card me-1"></i>Crédito
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-money-bill me-1"></i>Contado
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $autorizacion['fecha_autorizacion_formateada'] ?? 'N/A'; ?></td>
                                        <td><?php echo $autorizacion['fecha_venta_formateada'] ?? 'N/A'; ?></td>
                                        <td>
                                            <?php if ($autorizacion['descripcion']): ?>
                                                <span title="<?php echo htmlspecialchars($autorizacion['descripcion']); ?>">
                                                    <?php echo substr(htmlspecialchars($autorizacion['descripcion']), 0, 30) . '...'; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Sin descripción</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php
                                        // Determinar el archivo PDF según la moneda y cliente
                                        $clienteBrasil = (isset($autorizacion['cliente_brasil']) && $autorizacion['cliente_brasil'] !== '') ? $autorizacion['cliente_brasil'] : false;

                                        if ($autorizacion['moneda'] === 'Dólares' && $clienteBrasil === true) {
                                            $pdfFile = 'presupuestobr.php';
                                        } elseif ($autorizacion['moneda'] === 'Real brasileño') {
                                            $pdfFile = 'presupuestogl.php';
                                        } elseif ($autorizacion['moneda'] === 'Guaraníes') {
                                            $pdfFile = 'presupuestog.php';
                                        } else {
                                            $pdfFile = 'presupuesto.php';
                                        }
                                        ?>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <!-- Botón de Ver detalles -->
                                                <a href="<?php echo $url_base; ?>secciones/contable/ver.php?id=<?php echo $autorizacion['id']; ?>"
                                                    class="btn btn-info"
                                                    title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </a>

                                                <!-- Botón de Proforma PDF -->
                                                <a href="<?php echo $url_base; ?>pdf/<?php echo $pdfFile; ?>?id=<?php echo $autorizacion['id']; ?>"
                                                    target="_blank"
                                                    class="btn btn-outline-danger"
                                                    title="Ver proforma PDF">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPaginas > 1): ?>
                    <nav aria-label="Paginación de autorizaciones" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $paginaActual <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $controller->generarUrlConParametros('index.php', array_merge($filtros, ['pagina' => $paginaActual - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </a>
                            </li>

                            <?php
                            $inicio = max(1, $paginaActual - 2);
                            $fin = min($totalPaginas, $paginaActual + 2);

                            for ($i = $inicio; $i <= $fin; $i++):
                            ?>
                                <li class="page-item <?php echo $paginaActual == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo $controller->generarUrlConParametros('index.php', array_merge($filtros, ['pagina' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?php echo $paginaActual >= $totalPaginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $controller->generarUrlConParametros('index.php', array_merge($filtros, ['pagina' => $paginaActual + 1])); ?>">
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

                <?php if ($totalRegistros > 0): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-info-circle me-2"></i>Información de la Consulta
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <p class="mb-1"><strong>Total de registros:</strong> <?php echo $totalRegistros; ?></p>
                                            <p class="mb-1"><strong>Página actual:</strong> <?php echo $paginaActual; ?> de <?php echo $totalPaginas; ?></p>
                                        </div>
                                        <div class="col-md-4">
                                            <p class="mb-1"><strong>Filtros aplicados:</strong>
                                                <?php
                                                $filtrosAplicados = array_filter($filtros);
                                                echo empty($filtrosAplicados) ? 'Ninguno' : count($filtrosAplicados);
                                                ?>
                                            </p>
                                            <p class="mb-1"><strong>Última actualización:</strong> <?php echo date('H:i:s'); ?></p>
                                        </div>
                                        <div class="col-md-4">
                                            <p class="mb-1"><strong>Usuario:</strong> <?php echo htmlspecialchars($datosVista['usuario_actual']); ?></p>
                                            <p class="mb-1"><strong>Fecha:</strong> <?php echo date('d/m/Y'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $url_base; ?>secciones/contable/js/contable.js"></script>
    <script src="<?php echo $url_base; ?>config/notificacion/sistema-notificaciones.js"></script>


    <script>
        const CONTABLE_CONFIG = <?php echo json_encode($configuracionJS); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            const autoRefreshInterval = 5 * 60 * 1000;
            setTimeout(function() {
                console.log('Auto-refresh activado');
            }, autoRefreshInterval);

            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            const verLinks = document.querySelectorAll('a[href*="ver.php"]');
            verLinks.forEach(link => {
                link.addEventListener('click', function() {
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.className = 'fas fa-spinner fa-spin me-1';
                    }
                });
            });

            function actualizarTimestamp() {
                const timestampElements = document.querySelectorAll('.timestamp');
                timestampElements.forEach(element => {
                    const now = new Date();
                    element.textContent = now.toLocaleTimeString('es-PY');
                });
            }
            setInterval(actualizarTimestamp, 1000);

            console.log('Página de autorizaciones pendientes inicializada correctamente');
            console.log('Total de registros cargados:', <?php echo $totalRegistros; ?>);
        });
    </script>
</body>

</html>