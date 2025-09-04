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
if (isset($_GET['usuario_pcp'])) {
    $filtros['usuario_pcp'] = trim($_GET['usuario_pcp']);
}

$resultado = $controller->obtenerDevolucionesPCP($filtros, $paginaActual);
$devoluciones = $resultado['devoluciones'];
$totalRegistros = $resultado['total'];
$totalPaginas = $resultado['total_paginas'];

$datosVista = $controller->obtenerDatosVista('Ventas Devueltas por PCP');
$mensajes = $controller->manejarMensajes();
$configuracionJS = $controller->obtenerConfiguracionJS();

$filtrosStr = !empty($filtros['cliente']) || !empty($filtros['vendedor']) || !empty($filtros['usuario_pcp']) ?
    'Filtros aplicados: ' . json_encode($filtros) : 'Sin filtros';
$controller->logActividad('Consulta devoluciones PCP', $filtrosStr);
$breadcrumb_items = ['Sector Contable', 'Devoluciones PCP'];
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
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0">
                    <i class="fas fa-undo-alt me-2"></i>Ventas Devueltas por PCP
                </h4>
                <div class="mt-2">
                    <span class="badge bg-warning text-dark fs-6"><?php echo $totalRegistros; ?> devueltas</span>
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

                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo $url_base; ?>secciones/contable/devoluciones_pcp.php" method="GET" class="row g-3">
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
                                    <a href="<?php echo $url_base; ?>secciones/contable/devoluciones_pcp.php" class="btn btn-secondary">
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
                                <th><i class="fas fa-industry me-1"></i>Devuelto por</th>
                                <th><i class="fas fa-calendar-alt me-1"></i>Fecha Devolución</th>
                                <th><i class="fas fa-money-bill-wave me-1"></i>Monto Total</th>
                                <th><i class="fas fa-exclamation me-1"></i>Motivo</th>
                                <th class="text-center"><i class="fas fa-cogs me-1"></i>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($devoluciones)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i>No hay ventas devueltas por PCP
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($devoluciones as $devolucion): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($devolucion['id'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($devolucion['cliente'] ?? 'Sin cliente'); ?></td>
                                        <td><?php echo htmlspecialchars($devolucion['nombre_vendedor'] ?? 'Sin vendedor'); ?></td>
                                        <td>
                                            <span class="badge bg-info text-dark">
                                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($devolucion['nombre_pcp'] ?? 'No asignado');
                                                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($devolucion['fecha_devolucion_formateada'] ?? 'Sin fecha');
                                            ?></td>
                                        <td>
                                            <?php echo htmlspecialchars(($devolucion['simbolo_moneda'] ?? '') . ($devolucion['monto_formateado'] ?? '0,00'));
                                            ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($devolucion['motivo_devolucion'])):
                                            ?>
                                                <button type="button" class="btn btn-sm btn-warning text-dark"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalMotivo"
                                                    data-id="<?php echo $devolucion['id']; ?>"
                                                    data-cliente="<?php echo htmlspecialchars($devolucion['cliente'] ?? 'Sin cliente'); ?>"
                                                    data-pcp="<?php echo htmlspecialchars($devolucion['nombre_pcp'] ?? 'No asignado');
                                                                ?>"
                                                    data-fecha="<?php echo htmlspecialchars($devolucion['fecha_devolucion_formateada'] ?? 'Sin fecha');
                                                                ?>"
                                                    data-motivo="<?php echo htmlspecialchars($devolucion['motivo_devolucion']); ?>">
                                                    <i class="fas fa-eye me-1"></i>Ver Motivo
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">Sin detalles</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalRechazar"
                                                    data-id="<?php echo $devolucion['id']; ?>"
                                                    data-cliente="<?php echo htmlspecialchars($devolucion['cliente'] ?? 'Sin cliente'); ?>">
                                                    <i class="fas fa-times-circle me-1"></i>Rechazar
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPaginas > 1): ?>
                    <nav aria-label="Paginación de devoluciones" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $paginaActual <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $controller->generarUrlConParametros('devoluciones_pcp.php', array_merge($filtros, ['pagina' => $paginaActual - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </a>
                            </li>

                            <?php
                            $inicio = max(1, $paginaActual - 2);
                            $fin = min($totalPaginas, $paginaActual + 2);

                            for ($i = $inicio; $i <= $fin; $i++):
                            ?>
                                <li class="page-item <?php echo $paginaActual == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo $controller->generarUrlConParametros('devoluciones_pcp.php', array_merge($filtros, ['pagina' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?php echo $paginaActual >= $totalPaginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $controller->generarUrlConParametros('devoluciones_pcp.php', array_merge($filtros, ['pagina' => $paginaActual + 1])); ?>">
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

    <div class="modal fade" id="modalMotivo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="fas fa-undo-alt me-2"></i>Motivo de Devolución
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="fw-bold">Venta:</label>
                        <p><span id="modal-id"></span> - <span id="modal-cliente"></span></p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Devuelto por:</label>
                        <p id="modal-pcp"></p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Fecha de devolución:</label>
                        <p id="modal-fecha"></p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Motivo de la devolución:</label>
                        <div class="alert alert-warning" id="modal-motivo">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalRechazar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle me-2"></i>Rechazar Venta
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="form-rechazo" method="POST" action="rechazar_venta.php">
                    <div class="modal-body">
                        <input type="hidden" id="id_venta_modal" name="id_venta">
                        <div class="mb-3">
                            <label class="fw-bold">Venta:</label>
                            <p><strong>#<span id="modal-id-rechazar"></span></strong> - <span id="modal-cliente-rechazar"></span></p>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion_rechazo" class="form-label">
                                Motivo del Rechazo <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" id="descripcion_rechazo" name="descripcion_rechazo"
                                rows="4" required placeholder="Explique el motivo del rechazo..."></textarea>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <span>Esta acción rechazará la venta, enviando una notificación al vendedor y no podrá ser revertida.</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times-circle me-1"></i>Confirmar Rechazo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $url_base; ?>secciones/contable/js/contable.js"></script>
    <script src="<?php echo $url_base; ?>config/notificacion/sistema-notificaciones.js"></script>


    <script>
        const CONTABLE_CONFIG = <?php echo json_encode($configuracionJS); ?>;

        $(document).ready(function() {
            // Modal de motivo (existente)
            $('#modalMotivo').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var cliente = button.data('cliente');
                var pcp = button.data('pcp');
                var fecha = button.data('fecha');
                var motivo = button.data('motivo');

                $('#modal-id').text(id);
                $('#modal-cliente').text(cliente);
                $('#modal-pcp').text(pcp);
                $('#modal-fecha').text(fecha);
                $('#modal-motivo').html(motivo.replace(/\n/g, '<br>'));
            });

            // DEBUG: Modal de rechazo
            $('#modalRechazar').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var cliente = button.data('cliente');

                console.log('Modal abierto - ID:', id, 'Cliente:', cliente);

                $('#id_venta_modal').val(id);
                $('#modal-id-rechazar').text(id);
                $('#modal-cliente-rechazar').text(cliente);

                // Limpiar el textarea
                $('#descripcion_rechazo').val('');

                console.log('Valor asignado al campo hidden:', $('#id_venta_modal').val());
            });

            // DEBUG: Envío del formulario
            $('#form-rechazo').on('submit', function(e) {
                var idVenta = $('#id_venta_modal').val();
                var descripcion = $('#descripcion_rechazo').val().trim();

                console.log('=== DEBUG FORMULARIO ===');
                console.log('ID Venta:', idVenta);
                console.log('Descripción:', descripcion);
                console.log('Longitud descripción:', descripcion.length);

                if (!idVenta || idVenta <= 0) {
                    e.preventDefault();
                    alert('ERROR: ID de venta no válido: ' + idVenta);
                    return false;
                }

                if (!descripcion) {
                    e.preventDefault();
                    alert('ERROR: Debe ingresar un motivo de rechazo');
                    $('#descripcion_rechazo').focus();
                    return false;
                }

                console.log('Formulario válido, enviando...');
                console.log('Action del formulario:', $(this).attr('action'));
                console.log('Method del formulario:', $(this).attr('method'));
            });

            console.log('Página de devoluciones PCP inicializada con DEBUG');
        });
    </script>
</body>

</html>