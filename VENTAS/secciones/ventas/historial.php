<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";

requerirRol(['1', '2']);

if (file_exists("controllers/VentaController.php")) {
    include "controllers/VentaController.php";
} else {
    die("Error: No se pudo cargar el controlador de ventas.");
}

$controller = new VentaController($conexion, $url_base);

if (!$controller->verificarPermisos('listar')) {
    header("Location: " . $url_base . "index.php?error=No tienes permisos para acceder a esta sección");
    exit();
}

$registrosPorPagina = 10;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

$filtros = $controller->procesarFiltrosHistorial();

$resultado = $controller->obtenerHistorialAcciones($filtros, $paginaActual);
$historial = $resultado['historial'];
$totalRegistros = $resultado['total'];
$totalPaginas = $resultado['total_paginas'];
$esAdministrador = $resultado['es_administrador'];

$datosVista = $controller->obtenerDatosVista();
$mensajes = $controller->manejarMensajes();
$configuracionJS = $controller->obtenerConfiguracionJS();

$usuariosParaFiltro = $controller->obtenerUsuariosParaFiltro();

$filtrosStr = !empty($filtros['fecha_desde']) || !empty($filtros['fecha_hasta']) ?
    'Filtros aplicados: ' . json_encode($filtros) : 'Sin filtros';
$controller->logActividad('Consulta historial ventas', $filtrosStr);
$breadcrumb_items = ['Sector Ventas', 'Historial de Acciones'];
$item_urls = [
    $url_base . 'secciones/ventas/main.php',
];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Acciones - Sector Ventas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="<?php echo $url_base; ?>secciones/ventas/utils/styles.css" rel="stylesheet">
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-history me-2"></i>Historial de Acciones - Sector Ventas
                    <?php if (!$esAdministrador): ?>
                        <small class="ms-2">(Solo tus acciones)</small>
                    <?php endif; ?>
                </h4>
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
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros de Búsqueda</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo $url_base; ?>secciones/ventas/historial.php" method="GET" class="row g-3 align-items-end">
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
                                <label for="cliente_historial" class="form-label">Cliente</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="cliente_historial" name="cliente_historial"
                                        placeholder="Nombre del cliente"
                                        value="<?php echo htmlspecialchars($filtros['cliente_historial'] ?? ''); ?>">
                                </div>
                            </div>

                            <?php if ($esAdministrador && !empty($usuariosParaFiltro)): ?>
                                <div class="col-md-2">
                                    <label for="id_usuario" class="form-label">Usuario</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                                        <select class="form-select" id="id_usuario" name="id_usuario">
                                            <option value="">Todos</option>
                                            <?php foreach ($usuariosParaFiltro as $usuario): ?>
                                                <option value="<?php echo $usuario['id']; ?>"
                                                    <?php echo ($filtros['id_usuario'] == $usuario['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($usuario['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="col-md-2">
                                <label for="fecha_desde" class="form-label">Fecha Desde</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                    <input type="date" class="form-control" id="fecha_desde" name="fecha_desde"
                                        value="<?php echo htmlspecialchars($filtros['fecha_desde'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                    <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta"
                                        value="<?php echo htmlspecialchars($filtros['fecha_hasta'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="col-md-3 d-flex align-items-end">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filtrar
                                    </button>
                                    <a href="<?php echo $url_base; ?>secciones/ventas/historial.php" class="btn btn-secondary">
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
                                <th><i class="fas fa-hashtag me-1"></i>Venta</th>
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
                                            <i class="fas fa-info-circle me-2"></i>No se encontraron acciones con los criterios seleccionados
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
                                            <span class="badge <?php echo $accion['accion_badge']['class']; ?>">
                                                <i class="fas <?php echo $accion['accion_badge']['icon']; ?> me-1"></i><?php echo htmlspecialchars($accion['accion']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $accion['fecha_accion_formateada']; ?></td>
                                        <td>
                                            <span class="badge <?php echo $accion['estado_badge']['class']; ?>">
                                                <i class="fas <?php echo $accion['estado_badge']['icon']; ?> me-1"></i><?php echo htmlspecialchars($accion['estado_resultante']); ?>
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
                                                data-sector="<?php echo htmlspecialchars($accion['sector']); ?>"
                                                data-accion="<?php echo htmlspecialchars($accion['accion']); ?>"
                                                data-fecha="<?php echo $accion['fecha_accion_formateada']; ?>"
                                                data-estado="<?php echo htmlspecialchars($accion['estado_resultante']); ?>"
                                                data-observaciones="<?php echo htmlspecialchars($accion['observaciones']); ?>">
                                                <i class="fas fa-eye me-1"></i>Detalles
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPaginas > 1): ?>
                    <nav aria-label="Paginación de historial" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $paginaActual <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $controller->generarUrlConParametros('historial.php', array_merge($filtros, ['pagina' => $paginaActual - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </a>
                            </li>

                            <?php
                            $inicio = max(1, $paginaActual - 2);
                            $fin = min($totalPaginas, $paginaActual + 2);

                            for ($i = $inicio; $i <= $fin; $i++):
                            ?>
                                <li class="page-item <?php echo $paginaActual == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo $controller->generarUrlConParametros('historial.php', array_merge($filtros, ['pagina' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?php echo $paginaActual >= $totalPaginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $controller->generarUrlConParametros('historial.php', array_merge($filtros, ['pagina' => $paginaActual + 1])); ?>">
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
                            <th>Sector:</th>
                            <td><span class="badge bg-primary"><i class="fas fa-chart-line me-1"></i>Ventas</span></td>
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const VENTAS_CONFIG = <?php echo json_encode($configuracionJS); ?>;

        $(document).ready(function() {
            $('#modalDetalles').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var idVenta = button.data('venta');
                var cliente = button.data('cliente');
                var usuario = button.data('usuario');
                var accion = button.data('accion');
                var fecha = button.data('fecha');
                var estado = button.data('estado');
                var observaciones = button.data('observaciones');

                $('#modal-id-venta').text(idVenta);
                $('#modal-cliente').text(cliente);
                $('#modal-usuario').text(usuario);
                $('#modal-accion').text(accion);
                $('#modal-fecha').text(fecha);
                $('#modal-estado').text(estado);
                $('#modal-observaciones').html(observaciones ? observaciones.replace(/\n/g, '<br>') : '<em>Sin observaciones</em>');
            });

            $('#fecha_desde, #fecha_hasta').on('change', function() {
                var fechaDesde = $('#fecha_desde').val();
                var fechaHasta = $('#fecha_hasta').val();

                if (fechaDesde && fechaHasta && fechaDesde > fechaHasta) {
                    alert('La fecha desde no puede ser mayor que la fecha hasta');
                    $(this).val('');
                }
            });

            console.log('Página de historial de ventas inicializada correctamente');
            console.log('Total de registros:', <?php echo $totalRegistros; ?>);
            console.log('Es administrador:', <?php echo $esAdministrador ? 'true' : 'false'; ?>);
        });
    </script>
</body>

</html>