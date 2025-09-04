<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '4']); // Solo admins y PCP

// Verificar que existe el directorio y archivo del controller
if (file_exists("controllers/pcpController.php")) {
    include "controllers/pcpController.php";
} else {
    die("Error: No se pudo cargar el controlador de PCP.");
}

// Instanciar el controller
$controller = new PcpController($conexion, $url_base);

// Manejar peticiones API
if ($controller->handleApiRequest()) {
    exit();
}

// Procesar filtros y obtener datos
$filtros = $controller->procesarFiltros('historial');
$pagina = $filtros['pagina'];
$registrosPorPagina = 10;

try {
    $resultado = $controller->obtenerHistorialAcciones($filtros, $pagina, $registrosPorPagina);
    $historial = $resultado['historial'];
    $totalRegistros = $resultado['total_registros'];
    $totalPaginas = $resultado['total_paginas'];
    $paginaActual = $resultado['pagina_actual'];
    $maxVisible = 5;
    $paginaInicio = max(1, $paginaActual - 2);
    $paginaFin = min($totalPaginas, $paginaInicio + $maxVisible - 1);

    // Si hay muy pocas páginas al final, ajustar el inicio
    if ($paginaFin - $paginaInicio + 1 < $maxVisible) {
        $paginaInicio = max(1, $paginaFin - $maxVisible + 1);
    }
    $error = '';
} catch (Exception $e) {
    $historial = [];
    $totalRegistros = 0;
    $totalPaginas = 0;
    $paginaActual = 1;
    $error = "Error al obtener los datos: " . $e->getMessage();
}

// Obtener datos para la vista
$datosVista = $controller->obtenerDatosVista('historial');
$mensajes = $controller->manejarMensajes();
$configuracionJS = $controller->obtenerConfiguracionJS();

// Log de actividad
if (!empty($filtros)) {
    $filtrosStr = !empty($filtros['fecha_desde']) ? 'Desde: ' . $filtros['fecha_desde'] : 'Consulta general';
    $controller->logActividad('Consulta historial acciones', $filtrosStr);
}
$breadcrumb_items = ['Gestion PCP', 'Historial'];
$item_urls = [
    $url_base . 'secciones/sectorPcp/main.php',
];
$additional_css = [$url_base . 'secciones/sectorPcp/utils/styles.css'];
include $path_base . "components/head.php";
?>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-history me-2"></i>Historial de Acciones del Sector PCP
                </h4>
            </div>

            <div class="card-body">
                <?php if (isset($error) && !empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-2">
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
                                    <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                                    <input type="text" class="form-control" id="cliente" name="cliente"
                                        value="<?php echo htmlspecialchars($filtros['cliente'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label for="fecha_desde" class="form-label">Fecha Desde</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                    <input type="date" class="form-control" id="fecha_desde" name="fecha_desde"
                                        value="<?php echo htmlspecialchars($filtros['fecha_desde']); ?>">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                    <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta"
                                        value="<?php echo htmlspecialchars($filtros['fecha_hasta']); ?>">
                                </div>
                            </div>

                            <div class="col-md-3 d-flex align-items-end">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filtrar
                                    </button>
                                    <a href="<?php echo $url_base; ?>secciones/sectorPcp/historial.php" class="btn btn-secondary">
                                        <i class="fas fa-sync-alt me-1"></i>Limpiar
                                    </a>
                                </div>
                            </div>
                    </div>
                    </form>
                </div>
            </div>

            <!-- Información de resultados -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <span class="text-muted">
                        Mostrando <?php echo count($historial); ?> de <?php echo $totalRegistros; ?> acciones
                    </span>
                </div>
                <div>
                    <span class="text-muted">Página <?php echo $paginaActual; ?> de <?php echo $totalPaginas; ?></span>
                </div>
            </div>

            <!-- Tabla de Historial de Acciones -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th><i class="fas fa-hashtag me-1"></i></th>
                            <th><i class="fas fa-user me-1"></i>Cliente</th>
                            <th><i class="fas fa-user-tie me-1"></i>Usuario</th>
                            <th><i class="fas fa-tasks me-1"></i>Acción</th>
                            <th><i class="fas fa-calendar me-1"></i>Fecha</th>
                            <th><i class="fas fa-tag me-1"></i>Estado Resultante</th>
                            <th class="text-center"><i class="fas fa-cogs me-1"></i>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historial)): ?>
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="alert alert-info mb-0">
                                        <i class="fas fa-info-circle me-2"></i>No se encontraron registros con los criterios seleccionados
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($historial as $accion): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($accion['id_venta']); ?></td>
                                    <td><?php echo htmlspecialchars($accion['cliente']); ?></td>
                                    <td><?php echo htmlspecialchars($accion['nombre_usuario']); ?></td>
                                    <td>
                                        <?php
                                        $accionBadge = 'bg-secondary';
                                        $accionIcon = 'fa-question';

                                        if ($accion['accion'] === 'Procesar') {
                                            $accionBadge = 'bg-primary';
                                            $accionIcon = 'fa-cog';
                                        } elseif ($accion['accion'] === 'Enviar a Produccion') {
                                            $accionBadge = 'bg-info';
                                            $accionIcon = 'fa-industry';
                                        } elseif ($accion['accion'] === 'Finalizar Venta') {
                                            $accionBadge = 'bg-black';
                                            $accionIcon = 'fa-industry';
                                        } elseif ($accion['accion'] === 'Reasignar Producto Específico') {
                                            $accionBadge = 'bg-success';
                                            $accionIcon = 'fa-industry';
                                        } elseif ($accion['accion'] === 'Devolver') {
                                            $accionBadge = 'bg-warning';
                                            $accionIcon = 'fa-undo';
                                        } elseif ($accion['accion'] === 'Crear Reservas Stock Automáticas') {
                                            $accionBadge = 'bg-danger';
                                            $accionIcon = 'fa-undo';
                                        }
                                        ?>
                                        <span class="badge <?php echo $accionBadge; ?>">
                                            <i class="fas <?php echo $accionIcon; ?> me-1"></i><?php echo htmlspecialchars($accion['accion']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($accion['fecha_accion'])); ?></td>
                                    <td>
                                        <?php
                                        $estadoBadge = 'bg-secondary';
                                        $estadoIcon = 'fa-question';

                                        if ($accion['estado_resultante'] === 'Procesado') {
                                            $estadoBadge = 'bg-primary';
                                            $estadoIcon = 'fa-cog';
                                        } elseif ($accion['estado_resultante'] === 'En Producción') {
                                            $estadoBadge = 'bg-info';
                                            $estadoIcon = 'fa-industry';
                                        } elseif ($accion['estado_resultante'] === 'Enviado a PCP') {
                                            $estadoBadge = 'bg-success';
                                            $estadoIcon = 'fa-industry';
                                        } elseif ($accion['estado_resultante'] === 'Finalizado Manualmente') {
                                            $estadoBadge = 'bg-black';
                                            $estadoIcon = 'fa-industry';
                                        } elseif ($accion['estado_resultante'] === 'Devuelto a Contabilidad') {
                                            $estadoBadge = 'bg-warning';
                                            $estadoIcon = 'fa-undo';
                                        } elseif ($accion['estado_resultante'] === 'En Expedición') {
                                            $estadoBadge = 'bg-danger';
                                            $estadoIcon = 'fa-undo';
                                        }
                                        ?>
                                        <span class="badge <?php echo $estadoBadge; ?>">
                                            <i class="fas <?php echo $estadoIcon; ?> me-1"></i><?php echo htmlspecialchars($accion['estado_resultante']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-info btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalDetalles"
                                            data-id="<?php echo $accion['id']; ?>"
                                            data-venta="<?php echo $accion['id_venta']; ?>"
                                            data-cliente="<?php echo htmlspecialchars($accion['cliente']); ?>"
                                            data-usuario="<?php echo htmlspecialchars($accion['nombre_usuario']); ?>"
                                            data-accion="<?php echo htmlspecialchars($accion['accion']); ?>"
                                            data-fecha="<?php echo date('d/m/Y H:i', strtotime($accion['fecha_accion'])); ?>"
                                            data-estado="<?php echo htmlspecialchars($accion['estado_resultante']); ?>"
                                            data-observaciones="<?php echo htmlspecialchars($accion['observaciones']); ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPaginas > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <!-- Anterior -->
                        <?php if ($paginaActual > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?pagina=<?= $paginaActual - 1 ?>&fecha_desde=<?= urlencode($filtros['fecha_desde']) ?>&fecha_hasta=<?= urlencode($filtros['fecha_hasta']) ?>">
                                    ‹ Anterior
                                </a>
                            </li>
                        <?php endif; ?>

                        <!-- Primera página -->
                        <?php if ($paginaInicio > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?pagina=1&fecha_desde=<?= urlencode($filtros['fecha_desde']) ?>&fecha_hasta=<?= urlencode($filtros['fecha_hasta']) ?>">1</a>
                            </li>
                            <?php if ($paginaInicio > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- Páginas numeradas -->
                        <?php for ($i = $paginaInicio; $i <= $paginaFin; $i++): ?>
                            <li class="page-item <?= $paginaActual == $i ? 'active' : '' ?>">
                                <a class="page-link" href="?pagina=<?= $i ?>&fecha_desde=<?= urlencode($filtros['fecha_desde']) ?>&fecha_hasta=<?= urlencode($filtros['fecha_hasta']) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <!-- Última página -->
                        <?php if ($paginaFin < $totalPaginas): ?>
                            <?php if ($paginaFin < $totalPaginas - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?pagina=<?= $totalPaginas ?>&fecha_desde=<?= urlencode($filtros['fecha_desde']) ?>&fecha_hasta=<?= urlencode($filtros['fecha_hasta']) ?>"><?= $totalPaginas ?></a>
                            </li>
                        <?php endif; ?>

                        <!-- Siguiente -->
                        <?php if ($paginaActual < $totalPaginas): ?>
                            <li class="page-item">
                                <a class="page-link" href="?pagina=<?= $paginaActual + 1 ?>&fecha_desde=<?= urlencode($filtros['fecha_desde']) ?>&fecha_hasta=<?= urlencode($filtros['fecha_hasta']) ?>">
                                    Siguiente ›
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
    </div>

    <!-- Modal para Detalles -->
    <div class="modal fade" id="modalDetalles" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>Detalles de la Acción
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered">
                        <tr>
                            <th width="40%">ID Venta:</th>
                            <td id="modal-id-venta"></td>
                        </tr>
                        <tr>
                            <th>Cliente:</th>
                            <td id="modal-cliente"></td>
                        </tr>
                        <tr>
                            <th>Usuario:</th>
                            <td id="modal-usuario"></td>
                        </tr>
                        <tr>
                            <th>Acción:</th>
                            <td id="modal-accion"></td>
                        </tr>
                        <tr>
                            <th>Fecha:</th>
                            <td id="modal-fecha"></td>
                        </tr>
                        <tr>
                            <th>Estado Resultante:</th>
                            <td id="modal-estado"></td>
                        </tr>
                    </table>

                    <div class="mb-3">
                        <label class="fw-bold">Observaciones:</label>
                        <div class="border rounded p-3 bg-light" id="modal-observaciones">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript personalizado -->
    <script src="<?php echo $url_base; ?>secciones/sectorPcp/js/pcp.js"></script>
    <script>
        // Configuración global para JavaScript
        const PCP_CONFIG = <?php echo json_encode($configuracionJS); ?>;
    </script>
</body>

</html>