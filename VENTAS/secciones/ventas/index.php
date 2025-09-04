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

if ($controller->handleApiRequest()) {
    exit();
}

$registrosPorPagina = 10;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

$mostrarTodasLasVentas = tieneRol('1');
$idUsuarioActual = $_SESSION['id'];

$filtros = [
    'id_venta' => isset($_GET['id_venta']) ? $_GET['id_venta'] : '',
    'cliente' => isset($_GET['cliente']) ? $_GET['cliente'] : '',
    'cond_pago' => isset($_GET['cond_pago']) ? $_GET['cond_pago'] : '',
    'estado' => isset($_GET['estado']) ? $_GET['estado'] : ''
];

$resultadoTransportadora = $controller->procesarTransportadora();

$mensaje = '';
$error = '';

if (isset($_GET['eliminar'])) {
    if (!$controller->verificarPermisos('eliminar', $_GET['eliminar'])) {
        $error = "No tienes permisos para eliminar esta venta.";
    } else {
        $resultado = $controller->procesarEliminacion($_GET['eliminar']);
        if (isset($resultado['mensaje'])) {
            $mensaje = $resultado['mensaje'];
        } else {
            $error = $resultado['error'];
        }
        $controller->logActividad('Eliminar venta', 'ID: ' . $_GET['eliminar']);
    }
}

$datosVentas = $controller->obtenerListaVentas(
    $filtros,
    $registrosPorPagina,
    $paginaActual,
    $mostrarTodasLasVentas,
    $idUsuarioActual
);

$ventas = $datosVentas['ventas'];
$cantidadRechazadas = $controller->contarVentasRechazadas($mostrarTodasLasVentas, $idUsuarioActual);
$totalRegistros = $datosVentas['totalRegistros'];
$totalPaginas = $datosVentas['totalPaginas'];

$estados = $controller->obtenerEstadosVentas($mostrarTodasLasVentas, $idUsuarioActual);

$datosVista = $controller->obtenerDatosVista();
$mensajes = $controller->manejarMensajes();
$configuracionJS = $controller->obtenerConfiguracionJS();

if (!empty($mensajes['mensaje']) || !empty($mensaje)) $mensaje = $mensajes['mensaje'] ?: $mensaje;
if (!empty($mensajes['error']) || !empty($error)) $error = $mensajes['error'] ?: $error;
if (!empty($resultadoTransportadora['error'])) $error = $resultadoTransportadora['error'];

$titulo = $datosVista['titulo'];
$url_base = $datosVista['url_base'];
$usuario_actual = $datosVista['usuario_actual'];
$es_admin = $datosVista['es_admin'];

$controller->logActividad('Consulta ventas');
$breadcrumb_items = ['Sector Ventas', 'Listado Ventas'];
$item_urls = [
    $url_base . 'secciones/ventas/main.php',
];
$additional_css = [$url_base . 'secciones/ventas/utils/index.css'];
include $path_base . "components/head.php";
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="fas fa-cash-register me-2"></i>
                    <?php if (!$mostrarTodasLasVentas): ?>
                        Mis Ventas Registradas
                    <?php else: ?>
                        Gestión de Ventas
                    <?php endif; ?>
                </h4>
                <a href="registrar.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Nueva Venta
                </a>
            </div>

            <div class="card-body">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label for="Codigo Venta" class="form-label">Codigo Venta</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                    <input type="text" class="form-control" id="id_venta" name="id_venta" value="<?php echo htmlspecialchars($filtros['id_venta']); ?>" min="1">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label for="cliente" class="form-label">Cliente</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="cliente" name="cliente" value="<?php echo htmlspecialchars($filtros['cliente']); ?>">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label for="estado" class="form-label">Estado</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-tasks"></i></span>
                                    <select class="form-select" id="estado" name="estado">
                                        <option value="">Todos</option>
                                        <?php foreach ($estados as $estado): ?>
                                            <option value="<?php echo htmlspecialchars($estado['estado']); ?>" <?php echo $filtros['estado'] === $estado['estado'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($estado['estado']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label for="cond_pago" class="form-label">Condición</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-money-check-alt"></i></span>
                                    <select class="form-select" id="cond_pago" name="cond_pago">
                                        <option value="">Todas</option>
                                        <option value="Contado" <?php echo $filtros['cond_pago'] === 'Contado' ? 'selected' : ''; ?>>Contado</option>
                                        <option value="Crédito" <?php echo $filtros['cond_pago'] === 'Crédito' ? 'selected' : ''; ?>>Crédito</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 d-flex align-items-end">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filtrar
                                    </button>
                                    <a href="<?php echo $url_base; ?>secciones/ventas/index.php" class="btn btn-secondary">
                                        <i class="fas fa-sync-alt me-1"></i>Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="tabla-ventas" class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th><i class="fas fa-hashtag me-1"></i></th>
                                <th><i class="fas fa-user me-1"></i>Cliente</th>
                                <th><i class="fas fa-calendar-alt me-1"></i>Fecha</th>
                                <th><i class="fas fa-boxes me-1"></i>Productos</th>
                                <th><i class="fas fa-coins me-1"></i>Moneda</th>
                                <th><i class="fas fa-money-bill-wave me-1"></i>Total</th>
                                <?php if ($mostrarTodasLasVentas): ?>
                                    <th><i class="fas fa-user-tie me-1"></i>Vendedor</th>
                                <?php endif; ?>
                                <th><i class="fas fa-credit-card me-1"></i>Condición</th>
                                <th><i class="fas fa-tasks me-1"></i>Estado</th>
                                <th class="text-center"><i class="fas fa-cogs me-1"></i>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ventas)): ?>
                                <tr>
                                    <td colspan="<?php echo $mostrarTodasLasVentas ? '10' : '9'; ?>" class="text-center">
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i>No se encontraron ventas
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ventas as $venta): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($venta['id']); ?></td>
                                        <td><?php echo htmlspecialchars($venta['cliente']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($venta['fecha_referencia'])); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($venta['num_productos']); ?></td>
                                        <td><?php echo htmlspecialchars($venta['moneda']); ?></td>
                                        <td>
                                            <?php
                                            switch ($venta['moneda']) {
                                                case 'Dólares':
                                                    $simbolo = 'U$D ';
                                                    break;
                                                case 'Guaraníes':
                                                    $simbolo = '₲ ';
                                                    break;
                                                case 'Real brasileño':
                                                    $simbolo = 'R$ ';
                                                    break;
                                                default:
                                                    $simbolo = '';
                                            }
                                            echo $simbolo . number_format((float)$venta['monto_total'], 2, ',', '.');
                                            ?>
                                        </td>
                                        <?php if ($mostrarTodasLasVentas): ?>
                                            <td>
                                                <?php echo $venta['nombre_usuario'] ? htmlspecialchars($venta['nombre_usuario']) : 'No asignado'; ?>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <?php if ($venta['es_credito']): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-credit-card me-1"></i>Crédito
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-money-bill me-1"></i>Contado
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $estadoColor = 'bg-primary';
                                            $estadoIcon = 'fa-clock';

                                            if ($venta['estado'] === 'Procesado') {
                                                $estadoColor = 'bg-primary';
                                                $estadoIcon = 'fa-sync';
                                            } elseif ($venta['estado'] === 'Finalizado') {
                                                $estadoColor = 'bg-success';
                                                $estadoIcon = 'fa-check-circle';
                                            } elseif ($venta['estado'] === 'Cancelado') {
                                                $estadoColor = 'bg-danger';
                                                $estadoIcon = 'fa-ban';
                                            } elseif ($venta['estado'] === 'En revision') {
                                                $estadoColor = 'bg-warning';
                                                $estadoIcon = 'fa-search';
                                            } elseif ($venta['estado'] === 'Rechazado') {
                                                $estadoColor = 'bg-danger';
                                                $estadoIcon = 'fa-times-circle';
                                            } elseif ($venta['estado'] === 'Aprobado') {
                                                $estadoColor = 'bg-success';
                                                $estadoIcon = 'fa-check-circle';
                                            } elseif ($venta['estado'] === 'En Producción') {
                                                $estadoColor = 'bg-info';
                                                $estadoIcon = 'fa-tools';
                                            } elseif ($venta['estado'] === 'En Despacho') {
                                                $estadoColor = 'bg-secondary';
                                                $estadoIcon = 'fa-truck';
                                            } elseif ($venta['estado'] === 'Finalizado Manualmente') {
                                                $estadoColor = 'bg-dark';
                                                $estadoIcon = 'fa-hand-paper';
                                            } elseif ($venta['estado'] === 'En Expedición') {
                                                $estadoColor = 'bg-info';
                                                $estadoIcon = 'fa-box-open';
                                            } elseif ($venta['estado'] === 'Enviado a PCP') {
                                                $estadoColor = 'bg-pcp';
                                                $estadoIcon = 'fa-paper-plane';
                                            }

                                            ?>


                                            <span class="badge <?php echo $estadoColor; ?>">
                                                <i class="fas <?php echo $estadoIcon; ?> me-1"></i>
                                                <?php echo $venta['estado'] ? htmlspecialchars($venta['estado']) : 'Pendiente'; ?>
                                            </span>

                                            <?php if ($venta['estado'] === 'Rechazado' && !empty($venta['observaciones_contador'])): ?>
                                                <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalRechazo"
                                                    data-cliente="<?php echo htmlspecialchars($venta['cliente']); ?>"
                                                    data-fecha="<?php echo date('d/m/Y H:i', strtotime($venta['fecha_respuesta'])); ?>"
                                                    data-observaciones="<?php echo htmlspecialchars($venta['observaciones_contador']); ?>"
                                                    data-contador="<?php echo htmlspecialchars($venta['nombre_contador'] ?? 'No especificado'); ?>"
                                                    title="Ver motivo del rechazo">
                                                    <i class="fas fa-info-circle"></i>Ver Motivo
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($venta['estado'] === 'Aprobado' && !empty($venta['observaciones_contador'])): ?>
                                                <button type="button" class="btn btn-sm btn-link text-success p-0 ms-1"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalRechazo"
                                                    data-cliente="<?php echo htmlspecialchars($venta['cliente']); ?>"
                                                    data-fecha="<?php echo date('d/m/Y H:i', strtotime($venta['fecha_respuesta'])); ?>"
                                                    data-observaciones="<?php echo htmlspecialchars($venta['observaciones_contador']); ?>"
                                                    data-contador="<?php echo htmlspecialchars($venta['nombre_contador'] ?? 'No especificado'); ?>"
                                                    title="Ver observaciones">
                                                    <i class="fas fa-info-circle">Ver Motivo</i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="<?php echo $url_base; ?>secciones/ventas/ver.php?id=<?php echo $venta['id']; ?>" class="btn btn-info btn-sm" title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($controller->verificarPermisos('editar', $venta['id'])): ?>
                                                    <a href="<?php echo $url_base; ?>secciones/ventas/editar.php?id=<?php echo $venta['id']; ?>" class="btn btn-warning btn-sm" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-secondary btn-sm" disabled title="No se puede editar - Estado: <?php echo htmlspecialchars($venta['estado'] ?: 'No definido'); ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <?php
                                                    if ($venta['moneda'] === 'Dólares' && $venta['cliente_brasil'] === true) {
                                                        $pdfFile = 'presupuestobr.php';
                                                    } elseif ($venta['moneda'] === 'Real brasileño') {
                                                        $pdfFile = 'presupuestogl.php';
                                                    } elseif ($venta['moneda'] === 'Guaraníes') {
                                                        $pdfFile = 'presupuestog.php';
                                                    } else {
                                                        $pdfFile = 'presupuesto.php';
                                                    }
                                                    ?>
                                                    <li>
                                                        <a class="dropdown-item" href="<?php echo $url_base; ?>pdf/<?php echo $pdfFile; ?>?id=<?php echo $venta['id']; ?>" target="_blank">
                                                            <i class="fas fa-file-invoice me-2"></i>Proforma PDF
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="#"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modalTransportadora"
                                                            data-id="<?php echo $venta['id']; ?>"
                                                            data-cliente="<?php echo htmlspecialchars($venta['cliente']); ?>"
                                                            data-transportadora="<?php echo htmlspecialchars($venta['transportadora'] ?? ''); ?>">
                                                            <i class="fas fa-truck me-2"></i>Agregar Empresa Fletera
                                                        </a>
                                                    </li>
                                                    <?php if ($controller->verificarPermisos('autorizar', $venta['id'])): ?>
                                                        <li>
                                                            <a class="dropdown-item enviar-contable" href="#" data-bs-toggle="modal" data-bs-target="#confirmarEnvioContableModal" data-id="<?php echo $venta['id']; ?>">
                                                                <i class="fas fa-paper-plane me-2"></i>Enviar al Sector Contable
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <a class="dropdown-item" href="<?php echo $url_base; ?>secciones/estado/index.php?id=<?php echo $venta['id']; ?>">
                                                            <i class="fas fa-info-circle me-2"></i>Ver Estado
                                                        </a>
                                                    </li>
                                                    <?php if ($controller->verificarPermisos('eliminar', $venta['id'])): ?>
                                                        <li>
                                                            <a class="dropdown-item text-danger btn-eliminar" href="#"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#confirmarEliminarModal"
                                                                data-id="<?php echo $venta['id']; ?>">
                                                                <i class="fas fa-trash me-2"></i>Eliminar
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPaginas > 1): ?>
                    <nav aria-label="Paginación de ventas" class="mt-4">
                        <ul class="pagination justify-content-center">

                            <li class="page-item <?php echo $paginaActual <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $paginaActual - 1; ?>&cliente=<?php echo urlencode($filtros['cliente']); ?>&estado=<?php echo urlencode($filtros['estado']); ?>&cond_pago=<?php echo urlencode($filtros['cond_pago']); ?>#tabla-ventas">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </a>
                            </li>

                            <?php
                            $limiteDeEnlaces = 2;
                            $inicioBucle = max(1, $paginaActual - $limiteDeEnlaces);
                            $finBucle = min($totalPaginas, $paginaActual + $limiteDeEnlaces);

                            if ($inicioBucle > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?pagina=1&cliente=' . urlencode($filtros['cliente']) . '&estado=' . urlencode($filtros['estado']) . '&cond_pago=' . urlencode($filtros['cond_pago']) . '#tabla-ventas">1</a></li>';
                                if ($inicioBucle > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            for ($i = $inicioBucle; $i <= $finBucle; $i++):
                            ?>
                                <li class="page-item <?php echo $paginaActual == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?pagina=<?php echo $i; ?>&cliente=<?php echo urlencode($filtros['cliente']); ?>&estado=<?php echo urlencode($filtros['estado']); ?>&cond_pago=<?php echo urlencode($filtros['cond_pago']); ?>#tabla-ventas">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <?php
                            if ($finBucle < $totalPaginas) {
                                if ($finBucle < $totalPaginas - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?pagina=' . $totalPaginas . '&cliente=' . urlencode($filtros['cliente']) . '&estado=' . urlencode($filtros['estado']) . '&cond_pago=' . urlencode($filtros['cond_pago']) . '#tabla-ventas">' . $totalPaginas . '</a></li>';
                            }
                            ?>
                            <li class="page-item <?php echo $paginaActual >= $totalPaginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $paginaActual + 1; ?>&cliente=<?php echo urlencode($filtros['cliente']); ?>&estado=<?php echo urlencode($filtros['estado']); ?>&cond_pago=<?php echo urlencode($filtros['cond_pago']); ?>#tabla-ventas">
                                    Siguiente <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmarEliminarModal" tabindex="-1" aria-labelledby="confirmarEliminarModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmarEliminarModalLabel"><i class="fas fa-trash me-2"></i>Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">¿Está seguro de que desea eliminar esta venta? Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <a href="#" id="btn-confirmar-eliminar" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Eliminar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmarEnvioContableModal" tabindex="-1" aria-labelledby="confirmarEnvioContableModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="confirmarEnvioContableModalLabel">
                        <i class="fas fa-paper-plane me-2"></i>Confirmar Envío al Sector Contable
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <span>Esta acción enviará la venta seleccionada al sector contable para su revisión y autorización.</span>
                    </div>
                    <p>¿Está seguro de que desea continuar?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <a href="#" id="btn-confirmar-envio-contable" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Enviar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalTransportadora" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-truck me-2"></i>Agregar Empresa Fletera
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="fw-bold">Cliente:</label>
                            <p id="modalTransportadoraCliente" class="text-primary"></p>
                        </div>
                        <div class="mb-3">
                            <label for="transportadora" class="form-label fw-bold">Empresa Transportadora</label>
                            <input type="text"
                                class="form-control"
                                id="transportadora"
                                name="transportadora"
                                placeholder="Ingrese el nombre de la transportadora"
                                required>
                            <small class="text-muted">Deje vacío para eliminar la transportadora asignada</small>
                        </div>
                        <input type="hidden" name="id_venta" id="modalTransportadoraId">
                        <input type="hidden" name="actualizar_transportadora" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalRechazo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="modalTitulo">Observaciones del Contador</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="fw-bold">Cliente:</label>
                        <p id="modalCliente"></p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Contador:</label>
                        <p id="modalContador"></p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Fecha de respuesta:</label>
                        <p id="modalFecha"></p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Observaciones:</label>
                        <div class="alert alert-light border" id="modalObservaciones">
                        </div>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            function crearContenedorToasts() {
                if (!document.getElementById('toast-container')) {
                    const toastContainer = document.createElement('div');
                    toastContainer.id = 'toast-container';
                    toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
                    toastContainer.style.zIndex = '9999';
                    document.body.appendChild(toastContainer);
                }
            }

            function mostrarToast(mensaje, tipo = 'info', duracion = 5000) {
                crearContenedorToasts();

                const iconos = {
                    success: 'fas fa-check-circle',
                    error: 'fas fa-exclamation-circle',
                    warning: 'fas fa-exclamation-triangle',
                    info: 'fas fa-info-circle'
                };

                const colores = {
                    success: 'text-success',
                    error: 'text-danger',
                    warning: 'text-warning',
                    info: 'text-primary'
                };

                const fondos = {
                    success: 'bg-success-subtle border-success',
                    error: 'bg-danger-subtle border-danger',
                    warning: 'bg-warning-subtle border-warning',
                    info: 'bg-primary-subtle border-primary'
                };

                const toastId = 'toast-' + Date.now();

                const toastHTML = `
            <div id="${toastId}" class="toast ${fondos[tipo]} border" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header ${fondos[tipo]} border-0">
                    <i class="${iconos[tipo]} ${colores[tipo]} me-2"></i>
                    <strong class="me-auto ${colores[tipo]}">Sistema de Ventas</strong>
                    <small class="text-muted">ahora</small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${mensaje}
                </div>
            </div>
        `;

                document.getElementById('toast-container').insertAdjacentHTML('beforeend', toastHTML);

                const toastElement = document.getElementById(toastId);
                const toast = new bootstrap.Toast(toastElement, {
                    delay: duracion,
                    autohide: true
                });

                toast.show();

                toastElement.addEventListener('hidden.bs.toast', function() {
                    toastElement.remove();
                });
            }
            <?php if (!empty($mensaje)): ?>
                mostrarToast('<?php echo addslashes($mensaje); ?>', 'success', 5000);
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                mostrarToast('<?php echo addslashes($error); ?>', 'error', 6000);
            <?php endif; ?>

            <?php if ($cantidadRechazadas > 0): ?>
                mostrarToast(
                    '⚠️ Tienes <?php echo $cantidadRechazadas; ?> venta<?php echo $cantidadRechazadas > 1 ? 's' : ''; ?> rechazada<?php echo $cantidadRechazadas > 1 ? 's' : ''; ?> que requieren atención.',
                    'error',
                    12000
                );
            <?php endif; ?>
            if (window.location.hash) {
                setTimeout(function() {
                    const target = $(window.location.hash);
                    if (target.length) {
                        $('html, body').animate({
                            scrollTop: target.offset().top - 100
                        }, 500, 'ease-in-out');
                    }
                }, 100);
            }

            $('.pagination a[href*="#tabla-ventas"]').on('click', function(e) {
                const href = $(this).attr('href');
                if (href.includes('#tabla-ventas')) {
                    setTimeout(function() {
                        $('html, body').animate({
                            scrollTop: $('#tabla-ventas').offset().top - 100
                        }, 300, 'ease-in-out');
                    }, 50);
                }
            });

            $('.btn-eliminar').click(function() {
                const id = $(this).data('id');
                $('#btn-confirmar-eliminar').attr('href', '<?php echo $url_base; ?>secciones/ventas/index.php?eliminar=' + id);
            });

            $('[data-bs-target="#modalTransportadora"]').click(function() {
                const id = $(this).data('id');
                const cliente = $(this).data('cliente');
                const transportadora = $(this).data('transportadora');

                $('#modalTransportadoraId').val(id);
                $('#modalTransportadoraCliente').text(cliente);
                $('#transportadora').val(transportadora);
            });

            $(document).on('click', '[data-bs-target="#modalRechazo"]', function() {
                const cliente = $(this).data('cliente');
                const fecha = $(this).data('fecha');
                const observaciones = $(this).data('observaciones');
                const contador = $(this).data('contador');

                $('#modalCliente').text(cliente);
                $('#modalContador').text(contador);
                $('#modalFecha').text(fecha);
                $('#modalObservaciones').html(observaciones.replace(/\n/g, '<br>'));

                const esRechazo = $(this).hasClass('text-danger');
                $('#modalTitulo').text(esRechazo ? 'Motivo del Rechazo' : 'Observaciones de Aprobación');
            });

            $('.enviar-contable').click(function() {
                const id = $(this).data('id');
                $('#btn-confirmar-envio-contable').attr('href', '<?php echo $url_base; ?>secciones/ventas/autorizacion_vent.php?id=' + id);
            });
        });
    </script>
</body>

</html>