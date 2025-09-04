<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '4']); // Admin y Producción

// Incluir estructura MVC refactorizada
require_once "controllers/PendienteController.php";

// Inicializar controlador
$controller = new PendienteController($conexion, $url_base);

// Manejar peticiones API
if ($controller->handleApiRequest()) {
    exit; // Terminar ejecución para peticiones API
}

// Verificar permisos
if (!$controller->verificarPermisos('ver')) {
    header("Location: {$url_base}index.php?error=" . urlencode("No tienes permisos para acceder a esta sección"));
    exit;
}

// Obtener datos para la vista
$datosCompletos = $controller->obtenerDatosVista();
$mensajes = $controller->manejarMensajes();
$configuracionJS = $controller->obtenerConfiguracionJS();

// Extraer variables para facilitar uso en la vista
$datosVista = $datosCompletos['datos_vista'];
$resumenPendiente = $datosCompletos['resumen_pendiente'];
$detallesPorTipo = $datosCompletos['detalles_por_tipo'];
$estadisticas = $datosCompletos['estadisticas'];
$filtrosAplicados = $datosCompletos['filtros_aplicados'];
$error = $datosCompletos['error'] ?: $mensajes['error'];

// Obtener datos adicionales para filtros
$destinosDisponibles = $controller->obtenerDestinos();
$estadosDisponibles = $controller->obtenerEstados();

// Log de acceso
$controller->logActividad('acceso_vista_pendientes_v2.1');
$breadcrumb_items = ['Sector Produccion', 'Pendientes'];
$item_urls = [
    $url_base . 'secciones/produccion/main.php',
];
$additional_css = [$url_base . 'secciones/produccion/utils/styles.css'];
include $path_base . "components/head.php";
?>
<body>
    <!-- Overlay de carga -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-2">Actualizando datos...</p>
        </div>
    </div>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <!-- Mensajes de alerta -->
        <?php if (!empty($mensajes['mensaje'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $mensajes['mensaje']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card filtros-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-filter me-2"></i>Filtros de Búsqueda
                            <button class="btn btn-outline-secondary btn-sm float-end" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosCollapse">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </h5>
                    </div>
                    <div class="collapse" id="filtrosCollapse">
                        <div class="card-body">
                            <form method="GET" id="filtrosForm">
                                <div class="row g-3">
                                    <div class="col-md-2">
                                        <label for="cliente" class="form-label">Cliente</label>
                                        <input type="text" class="form-control" id="cliente" name="cliente"
                                            value="<?php echo htmlspecialchars($filtrosAplicados['cliente']); ?>"
                                            placeholder="Buscar por cliente...">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="tipo_producto" class="form-label">Tipo de Producto</label>
                                        <select class="form-select" id="tipo_producto" name="tipo_producto">
                                            <option value="">Todos los tipos</option>
                                            <option value="TNT" <?php echo $filtrosAplicados['tipo_producto'] === 'TNT' ? 'selected' : ''; ?>>TNT (incluye Laminadora)</option>
                                            <option value="SPUNLACE" <?php echo $filtrosAplicados['tipo_producto'] === 'SPUNLACE' ? 'selected' : ''; ?>>Spunlace</option>
                                            <option value="TOALLITAS" <?php echo $filtrosAplicados['tipo_producto'] === 'TOALLITAS' ? 'selected' : ''; ?>>Toallitas</option>
                                            <option value="PAÑOS" <?php echo $filtrosAplicados['tipo_producto'] === 'PAÑOS' ? 'selected' : ''; ?>>Paños</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="destino" class="form-label">Destino</label>
                                        <select class="form-select" id="destino" name="destino">
                                            <option value="">Todos los destinos</option>
                                            <?php foreach ($destinosDisponibles as $destino): ?>
                                                <option value="<?php echo htmlspecialchars($destino); ?>"
                                                    <?php echo $filtrosAplicados['destino'] === $destino ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($destino); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="fecha_desde" class="form-label">Desde</label>
                                        <input type="date" class="form-control" id="fecha_desde" name="fecha_desde"
                                            value="<?php echo $filtrosAplicados['fecha_desde']; ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="fecha_hasta" class="form-label">Hasta</label>
                                        <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta"
                                            value="<?php echo $filtrosAplicados['fecha_hasta']; ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <div class="d-flex gap-2 align-items-end h-100">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search me-1"></i>Filtrar
                                            </button>
                                            <a href="?" class="btn btn-outline-secondary">
                                                <i class="fas fa-times me-1"></i>Limpiar
                                            </a>
                                            <button type="button" class="btn btn-outline-info" onclick="actualizarDatos()" id="btnActualizar">
                                                <i class="fas fa-sync-alt me-1"></i>Actualizar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjetas de resumen por sector -->
        <div class="row g-4" id="tarjetasResumen">
            <!-- TNT M1 -->
            <div class="col-lg-4 col-md-6">
                <div class="card card-sector sector-tnt-m1 h-100">
                    <div class="card-body position-relative">
                        <i class="fas fa-scroll icono-sector" style="color: var(--color-tnt-m1);"></i>
                        <h5 class="card-title mb-3" style="color: var(--color-tnt-m1);">
                            <i class="fas fa-scroll me-2"></i>TNT - Máquina 1
                        </h5>
                        <div class="metricas-duales">
                            <div class="metrica-principal" style="color: var(--color-tnt-m1);">
                                <span><?php echo number_format($resumenPendiente['TNT_M1']['total_kg'] ?? 0, 2); ?></span>
                                <small>kg</small>
                            </div>
                        </div>
                        <div class="detalle-sector">
                            <span class="badge badge-pendiente" style="background: var(--color-tnt-m1);">
                                <?php echo $resumenPendiente['TNT_M1']['ordenes'] ?? 0; ?> órdenes pendientes
                            </span>
                        </div>
                        <?php if (($resumenPendiente['TNT_M1']['ordenes'] ?? 0) > 0): ?>
                            <button class="btn btn-outline-primary btn-sm mt-3" type="button"
                                data-bs-toggle="collapse" data-bs-target="#detalle-tnt-m1">
                                <i class="fas fa-eye me-1"></i>Ver detalles
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- TNT M2 -->
            <div class="col-lg-4 col-md-6">
                <div class="card card-sector sector-tnt-m2 h-100">
                    <div class="card-body position-relative">
                        <i class="fas fa-scroll icono-sector" style="color: var(--color-tnt-m2);"></i>
                        <h5 class="card-title mb-3" style="color: var(--color-tnt-m2);">
                            <i class="fas fa-scroll me-2"></i>TNT - Máquina 2
                        </h5>
                        <div class="metricas-duales">
                            <div class="metrica-principal" style="color: var(--color-tnt-m2);">
                                <span><?php echo number_format($resumenPendiente['TNT_M2']['total_kg'] ?? 0, 2); ?></span>
                                <small>kg</small>
                            </div>
                        </div>
                        <div class="detalle-sector">
                            <span class="badge badge-pendiente" style="background: var(--color-tnt-m2);">
                                <?php echo $resumenPendiente['TNT_M2']['ordenes'] ?? 0; ?> órdenes pendientes
                            </span>
                        </div>
                        <?php if (($resumenPendiente['TNT_M2']['ordenes'] ?? 0) > 0): ?>
                            <button class="btn btn-sm mt-3" type="button"
                                style="border-color: var(--color-tnt-m2); color: var(--color-tnt-m2);"
                                data-bs-toggle="collapse" data-bs-target="#detalle-tnt-m2">
                                <i class="fas fa-eye me-1"></i>Ver detalles
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- SPUNLACE -->
            <div class="col-lg-4 col-md-6">
                <div class="card card-sector sector-spunlace h-100">
                    <div class="card-body position-relative">
                        <i class="fas fa-swatchbook icono-sector" style="color: var(--color-spunlace);"></i>
                        <h5 class="card-title mb-3" style="color: var(--color-spunlace);">
                            <i class="fas fa-swatchbook me-2"></i>Spunlace
                        </h5>
                        <div class="metricas-duales">
                            <div class="metrica-principal" style="color: var(--color-spunlace);">
                                <span><?php echo number_format($resumenPendiente['SPUNLACE']['total_kg'] ?? 0, 2); ?></span>
                                <small>kg</small>
                            </div>
                        </div>
                        <div class="detalle-sector">
                            <span class="badge badge-pendiente" style="background: var(--color-spunlace);">
                                <?php echo $resumenPendiente['SPUNLACE']['ordenes'] ?? 0; ?> órdenes pendientes
                            </span>
                        </div>
                        <?php if (($resumenPendiente['SPUNLACE']['ordenes'] ?? 0) > 0): ?>
                            <button class="btn btn-sm mt-3" type="button"
                                style="border-color: var(--color-spunlace); color: var(--color-spunlace);"
                                data-bs-toggle="collapse" data-bs-target="#detalle-spunlace">
                                <i class="fas fa-eye me-1"></i>Ver detalles
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- TOALLITAS - Con métricas duales -->
            <div class="col-lg-4 col-md-6">
                <div class="card card-sector sector-toallitas h-100">
                    <div class="card-body position-relative">
                        <i class="fas fa-soap icono-sector" style="color: var(--color-toallitas);"></i>
                        <h5 class="card-title mb-3" style="color: var(--color-toallitas);">
                            <i class="fas fa-soap me-2"></i>Toallitas
                        </h5>
                        <div class="metricas-duales">
                            <div class="metrica-principal" style="color: var(--color-toallitas);">
                                <span><?php echo number_format($resumenPendiente['TOALLITAS']['total_unidades'] ?? 0, 0); ?></span>
                                <small>Cajas</small>
                            </div>
                            <?php if (($resumenPendiente['TOALLITAS']['total_kg'] ?? 0) > 0): ?>
                                <div class="metrica-secundaria" style="color: var(--color-toallitas);">
                                    <span><?php echo number_format($resumenPendiente['TOALLITAS']['total_kg'], 2); ?></span>
                                    <small>kg</small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="detalle-sector">
                            <span class="badge badge-pendiente" style="background: var(--color-toallitas);">
                                <?php echo $resumenPendiente['TOALLITAS']['ordenes'] ?? 0; ?> órdenes pendientes
                            </span>
                        </div>
                        <?php if (($resumenPendiente['TOALLITAS']['ordenes'] ?? 0) > 0): ?>
                            <button class="btn btn-outline-success btn-sm mt-3" type="button"
                                data-bs-toggle="collapse" data-bs-target="#detalle-toallitas">
                                <i class="fas fa-eye me-1"></i>Ver detalles
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- PAÑOS - Solo kg ahora -->
            <div class="col-lg-4 col-md-6">
                <div class="card card-sector sector-panos h-100">
                    <div class="card-body position-relative">
                        <i class="fas fa-tshirt icono-sector" style="color: var(--color-panos);"></i>
                        <h5 class="card-title mb-3" style="color: var(--color-panos);">
                            <i class="fas fa-tshirt me-2"></i>Paños
                        </h5>
                        <div class="metricas-duales">
                            <div class="metrica-principal" style="color: var(--color-panos);">
                                <span><?php echo number_format($resumenPendiente['PAÑOS']['total_kg'] ?? 0, 2); ?></span>
                                <small>kg</small>
                            </div>
                        </div>
                        <div class="detalle-sector">
                            <span class="badge badge-pendiente" style="background: var(--color-panos);">
                                <?php echo $resumenPendiente['PAÑOS']['ordenes'] ?? 0; ?> órdenes pendientes
                            </span>
                        </div>
                        <?php if (($resumenPendiente['PAÑOS']['ordenes'] ?? 0) > 0): ?>
                            <button class="btn btn-sm mt-3" type="button"
                                style="border-color: var(--color-panos); color: var(--color-panos);"
                                data-bs-toggle="collapse" data-bs-target="#detalle-panos">
                                <i class="fas fa-eye me-1"></i>Ver detalles
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- OTROS (si existen) -->
            <?php if (!empty($resumenPendiente['OTROS']) && $resumenPendiente['OTROS']['ordenes'] > 0): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card card-sector h-100">
                        <div class="card-body position-relative">
                            <i class="fas fa-boxes icono-sector" style="color: var(--color-otros);"></i>
                            <h5 class="card-title mb-3" style="color: var(--color-otros);">
                                <i class="fas fa-boxes me-2"></i>Otros Productos
                            </h5>
                            <div class="metricas-duales">
                                <div class="metrica-principal" style="color: var(--color-otros);">
                                    <span><?php echo number_format($resumenPendiente['OTROS']['total_kg'] ?? 0, 2); ?></span>
                                    <small>kg</small>
                                </div>
                            </div>
                            <div class="detalle-sector">
                                <span class="badge badge-pendiente" style="background: var(--color-otros);">
                                    <?php echo $resumenPendiente['OTROS']['ordenes']; ?> órdenes pendientes
                                </span>
                            </div>
                            <button class="btn btn-sm mt-3" type="button"
                                style="border-color: var(--color-otros); color: var(--color-otros);"
                                data-bs-toggle="collapse" data-bs-target="#detalle-otros">
                                <i class="fas fa-eye me-1"></i>Ver detalles
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Detalles por sector (colapsables) -->
        <div id="contenedorDetalles">
            <?php
            $sectores = [
                'TNT_M1' => ['titulo' => 'TNT Máquina 1', 'color' => 'primary', 'id' => 'detalle-tnt-m1'],
                'TNT_M2' => ['titulo' => 'TNT Máquina 2', 'color' => 'purple', 'id' => 'detalle-tnt-m2', 'style' => 'background: var(--color-tnt-m2);'],
                'SPUNLACE' => ['titulo' => 'Spunlace', 'color' => 'purple', 'id' => 'detalle-spunlace', 'style' => 'background: var(--color-spunlace);'],
                'TOALLITAS' => ['titulo' => 'Toallitas', 'color' => 'success', 'id' => 'detalle-toallitas'],
                'PAÑOS' => ['titulo' => 'Paños', 'color' => 'warning', 'id' => 'detalle-panos', 'style' => 'background: var(--color-panos);'],
                'OTROS' => ['titulo' => 'Otros Productos', 'color' => 'secondary', 'id' => 'detalle-otros', 'style' => 'background: var(--color-otros);']
            ];

            foreach ($sectores as $sectorKey => $sectorInfo):
                $detalles = $detallesPorTipo[$sectorKey] ?? [];
                if (empty($detalles)) continue;
            ?>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="collapse" id="<?php echo $sectorInfo['id']; ?>">
                            <div class="card">
                                <div class="card-header <?php echo isset($sectorInfo['style']) ? '' : 'bg-' . $sectorInfo['color']; ?>"
                                    <?php echo isset($sectorInfo['style']) ? 'style="' . $sectorInfo['style'] . '"' : ''; ?>>
                                    <h5 class="mb-0 text-white">
                                        <i class="fas fa-list me-2"></i>Detalle: <?php echo $sectorInfo['titulo']; ?>
                                        <span class="badge bg-light text-dark ms-2"><?php echo count($detalles); ?> productos</span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-detalle table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th># Venta</th>
                                                    <th>Cliente</th>
                                                    <th>Producto</th>
                                                    <th>Destino</th>
                                                    <th>Total</th>
                                                    <th>Producido</th>
                                                    <th>Pendiente</th>
                                                    <?php if ($sectorKey === 'TOALLITAS'): ?>
                                                        <th>Peso (kg)</th>
                                                    <?php endif; ?>
                                                    <th>Progreso</th>
                                                    <th>Estado</th>
                                                    <th>Fecha</th>
                                                    <th>Días</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($detalles as $detalle): ?>
                                                    <tr>
                                                        <td><strong><?php echo $detalle['id_venta']; ?></strong></td>
                                                        <td title="<?php echo htmlspecialchars($detalle['cliente']); ?>">
                                                            <?php echo htmlspecialchars(substr($detalle['cliente'], 0, 15)); ?>
                                                            <?php if (strlen($detalle['cliente']) > 15): ?>...<?php endif; ?>
                                                        </td>
                                                        <td title="<?php echo htmlspecialchars($detalle['producto_descripcion']); ?>">
                                                            <small><?php echo htmlspecialchars(substr($detalle['producto_descripcion'], 0, 200)); ?></small>
                                                        </td>
                                                        <td><small><?php echo htmlspecialchars($detalle['destino'] ?? 'N/A'); ?></small></td>

                                                        <!-- Columna Total -->
                                                        <td>
                                                            <?php
                                                            if ($sectorKey === 'PAÑOS' && isset($detalle['peso_pendiente_kg']) && $detalle['peso_unitario'] > 0) {
                                                                // Para paños: mostrar cantidad total convertida a kg
                                                                echo number_format($detalle['cantidad_total'] * $detalle['peso_unitario'], 2) . ' kg';
                                                            } else {
                                                                echo number_format($detalle['cantidad_total'], 2) . ' ' . $detalle['unidad'];
                                                            }
                                                            ?>
                                                        </td>

                                                        <!-- Columna Producido -->
                                                        <td>
                                                            <?php
                                                            if ($sectorKey === 'PAÑOS' && isset($detalle['peso_unitario']) && $detalle['peso_unitario'] > 0) {
                                                                // Para paños: mostrar stock producido convertido a kg
                                                                echo number_format($detalle['stock_producido'] * $detalle['peso_unitario'], 2) . ' kg';
                                                            } else {
                                                                echo number_format($detalle['stock_producido'], 2) . ' ' . $detalle['unidad'];
                                                            }
                                                            ?>
                                                        </td>

                                                        <!-- Reemplazar SOLO la columna Pendiente -->
                                                        <td>
                                                            <strong class="text-warning">
                                                                <?php
                                                                if ($sectorKey === 'PAÑOS' && isset($detalle['peso_unitario']) && $detalle['peso_unitario'] > 0) {
                                                                    // Para paños: calcular cantidad pendiente en kg
                                                                    echo number_format($detalle['cantidad_pendiente'] * $detalle['peso_unitario'], 2) . ' kg';
                                                                } else {
                                                                    echo number_format($detalle['cantidad_pendiente'], 2) . ' ' . $detalle['unidad'];
                                                                }
                                                                ?>
                                                            </strong>
                                                        </td>
                                                        <?php if ($sectorKey === 'TOALLITAS' && isset($detalle['peso_pendiente_kg'])): ?>
                                                            <td><small class="text-info"><?php echo number_format($detalle['peso_pendiente_kg'], 2); ?> kg</small></td>
                                                        <?php endif; ?>
                                                        <td>
                                                            <div class="progress progress-custom">
                                                                <?php
                                                                $porcentaje = $detalle['porcentaje_completado'];
                                                                $colorProgress = $porcentaje >= 80 ? 'bg-success' : ($porcentaje >= 50 ? 'bg-warning' : 'bg-danger');
                                                                ?>
                                                                <div class="progress-bar <?php echo $colorProgress; ?>" style="width: <?php echo $porcentaje; ?>%"></div>
                                                            </div>
                                                            <small><?php echo number_format($porcentaje, 1); ?>%</small>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $estado = $detalle['estado_orden'] ?? 'Sin Estado';
                                                            $badgeClass = 'bg-secondary';

                                                            switch ($estado) {
                                                                case 'Completado':
                                                                    $badgeClass = 'bg-success';
                                                                    break;
                                                                case 'Pendiente':
                                                                    $badgeClass = 'bg-warning text-dark';
                                                                    break;
                                                                case 'Orden Emitida':
                                                                    $badgeClass = 'bg-info';
                                                                    break;
                                                                case 'En Produccion':
                                                                    $badgeClass = 'bg-primary';
                                                                    break;
                                                                case 'Sin Estado':
                                                                    $badgeClass = 'bg-secondary';
                                                                    break;
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($estado); ?></span>
                                                        </td>
                                                        <td><small><?php echo date('d/m/Y', strtotime($detalle['fecha_orden'])); ?></small></td>
                                                        <td>
                                                            <small class="<?php echo $detalle['dias_pendiente'] > 30 ? 'text-danger fw-bold' : ($detalle['dias_pendiente'] > 15 ? 'text-warning' : 'text-muted'); ?>">
                                                                <?php echo $detalle['dias_pendiente']; ?>d
                                                            </small>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        </div>

        <!-- JavaScript -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        <script>
            // Configuración global
            window.PendientesConfig = <?php echo json_encode($configuracionJS); ?>;

            // Función para actualizar datos
            function actualizarDatos() {
                const btn = document.getElementById('btnActualizar');
                const overlay = document.getElementById('loadingOverlay');

                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Actualizando...';
                overlay.style.display = 'block';

                fetch('?action=actualizar_datos')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            console.error('Error:', data.error);
                            alert('Error al actualizar datos: ' + (data.error || 'Error desconocido'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error de conexión al actualizar datos');
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Actualizar';
                        overlay.style.display = 'none';
                    });
            }

            // Mejorar UX de filtros
            document.getElementById('filtrosForm').addEventListener('submit', function() {
                document.getElementById('loadingOverlay').style.display = 'block';
            });

            // Auto-refresh opcional
            if (window.PendientesConfig.autoRefresh) {
                setInterval(function() {
                    actualizarDatos();
                }, window.PendientesConfig.refreshInterval);
            }

            // Log de depuración
            if (window.PendientesConfig.debug) {
                console.log('PendientesConfig:', window.PendientesConfig);
                console.log('Sistema v2.1 activo - Con peso en kg para toallitas y paños');
            }
        </script>
    </div>
</body>

</html>