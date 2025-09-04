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

$filtros = $controller->procesarFiltros();

$resultado = $controller->obtenerCuentasCobrar($filtros, $paginaActual);
$cuentas = $resultado['cuentas'];
$totalRegistros = $resultado['total'];
$totalPaginas = $resultado['total_paginas'];

$datosVista = $controller->obtenerDatosVista('Gestión de Cuentas por Cobrar');
$mensajes = $controller->manejarMensajes();
$configuracionJS = $controller->obtenerConfiguracionJS();
$estadisticas = $controller->obtenerEstadisticas();

$filtrosStr = !empty($filtros) ? 'Filtros: ' . json_encode($filtros) : 'Sin filtros';
$controller->logActividad('Consulta cuentas por cobrar', $filtrosStr);
$breadcrumb_items = ['Sector Contable', 'Cuentas por Cobrar'];
$item_urls = [
    $url_base . 'secciones/contable/main.php',
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuentas por Cobrar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="<?php echo $url_base; ?>secciones/contable/utils/styles.css" rel="stylesheet">
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
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
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="fas fa-money-check-alt me-2"></i>Gestión de Cuentas por Cobrar
                </h4>
                <div>
                    <span class="badge bg-primary fs-6"><?php echo $totalRegistros; ?> registros</span>
                </div>
            </div>

            <div class="card-body">
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
                            <div class="col-md-3">
                                <label for="cliente" class="form-label">Cliente</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="cliente" name="cliente"
                                        value="<?php echo htmlspecialchars($filtros['cliente'] ?? ''); ?>"
                                        placeholder="Buscar por cliente...">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label for="estado" class="form-label">Estado</label>
                                <select class="form-select" id="estado" name="estado">
                                    <option value="">Todos los estados</option>
                                    <option value="PENDIENTE" <?php echo ($filtros['estado'] ?? '') === 'PENDIENTE' ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="PARCIAL" <?php echo ($filtros['estado'] ?? '') === 'PARCIAL' ? 'selected' : ''; ?>>Pago Parcial</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="fecha_desde" class="form-label">Vence Desde</label>
                                <input type="date" class="form-control" id="fecha_desde" name="fecha_desde"
                                    value="<?php echo htmlspecialchars($filtros['fecha_desde'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="fecha_hasta" class="form-label">Vence Hasta</label>
                                <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta"
                                    value="<?php echo htmlspecialchars($filtros['fecha_hasta'] ?? ''); ?>">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="solo_vencidas" name="solo_vencidas" value="1"
                                        <?php echo isset($filtros['solo_vencidas']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="solo_vencidas">
                                        Solo vencidas
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="btn-group" role="group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filtrar
                                    </button>
                                    <a href="cuentas_cobrar.php" class="btn btn-secondary">
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
                                <th><i class="fas fa-list-ol me-1"></i>Cuotas</th>
                                <th><i class="fas fa-calendar me-1"></i>Próximo Vencimiento</th>
                                <th><i class="fas fa-money-bill me-1"></i>Total Cuotas</th>
                                <th><i class="fas fa-credit-card me-1"></i>Total Pagado</th>
                                <th><i class="fas fa-exclamation-circle me-1"></i>Total Pendiente</th>
                                <th><i class="fas fa-info-circle me-1"></i>Estado</th>
                                <th class="text-center"><i class="fas fa-cogs me-1"></i>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cuentas)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i>No hay ventas con cuentas por cobrar con los filtros aplicados
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
                                            <small><?php echo htmlspecialchars($venta['tipocredito']); ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($venta['proximo_vencimiento_formateado'])): ?>
                                                <?php echo $venta['proximo_vencimiento_formateado']; ?>
                                                <br><small class="text-muted">Venta: <?php echo $venta['fecha_venta_formateada']; ?>
                                                    <?php if (!empty($venta['tiene_fecha_personalizada'])): ?>
                                                        <i class="fas fa-calendar-check text-primary ms-1" title="Fecha de inicio personalizada"></i>
                                                    <?php endif; ?>
                                                </small> <?php else: ?>
                                                <span class="text-success">Todas pagadas</span>
                                                <br><small class="text-muted">Venta: <?php echo $venta['fecha_venta_formateada']; ?>
                                                    <?php if (!empty($venta['tiene_fecha_personalizada'])): ?>
                                                        <i class="fas fa-calendar-check text-primary ms-1" title="Fecha de inicio personalizada"></i>
                                                    <?php endif; ?>
                                                </small> <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $venta['simbolo_moneda'] . $venta['monto_total_cuotas_formateado']; ?>
                                        </td>
                                        <td>
                                            <?php echo $venta['simbolo_moneda'] . $venta['monto_total_pagado_formateado']; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo $venta['simbolo_moneda'] . $venta['monto_total_pendiente_formateado']; ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $venta['estado_class']; ?>">
                                                <?php echo ucfirst(strtolower($venta['estado_venta'])); ?>
                                            </span>
                                        </td>
                                        <?php
                                        $clienteBrasil = (isset($venta['cliente_brasil']) && $venta['cliente_brasil'] !== '') ? $venta['cliente_brasil'] : false;

                                        if ($venta['moneda'] === 'Dólares' && $clienteBrasil === true) {
                                            $pdfFile = 'presupuestobr.php';
                                        } elseif ($venta['moneda'] === 'Real brasileño') {
                                            $pdfFile = 'presupuestogl.php';
                                        } elseif ($venta['moneda'] === 'Guaraníes') {
                                            $pdfFile = 'presupuestog.php';
                                        } else {
                                            $pdfFile = 'presupuesto.php';
                                        }
                                        ?>

                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="ver_cuota.php?id=<?php echo $venta['primera_cuota_id']; ?>"
                                                    class="btn btn-info"
                                                    title="Ver detalles de la venta">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo $url_base; ?>pdf/<?php echo $pdfFile; ?>?id=<?php echo $venta['id_venta']; ?>"
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
                    <nav aria-label="Paginación de cuentas por cobrar" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $paginaActual <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $controller->generarUrlConParametros('cuentas_cobrar.php', array_merge($filtros, ['pagina' => $paginaActual - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </a>
                            </li>

                            <?php
                            $inicio = max(1, $paginaActual - 2);
                            $fin = min($totalPaginas, $paginaActual + 2);

                            for ($i = $inicio; $i <= $fin; $i++):
                            ?>
                                <li class="page-item <?php echo $paginaActual == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo $controller->generarUrlConParametros('cuentas_cobrar.php', array_merge($filtros, ['pagina' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?php echo $paginaActual >= $totalPaginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $controller->generarUrlConParametros('cuentas_cobrar.php', array_merge($filtros, ['pagina' => $paginaActual + 1])); ?>">
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
        const CUENTAS_COBRAR_CONFIG = <?php echo json_encode($configuracionJS); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            const btnActualizar = document.getElementById('btn-actualizar');
            if (btnActualizar) {
                btnActualizar.addEventListener('click', function() {
                    this.querySelector('i').classList.add('fa-spin');
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                });
            }

            setInterval(function() {
                console.log('Auto-refresh cuentas por cobrar');
            }, 5 * 60 * 1000);
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            console.log('Página de cuentas por cobrar inicializada correctamente');
            console.log('Total de registros cargados:', <?php echo $totalRegistros; ?>);
        });
    </script>
</body>

</html>