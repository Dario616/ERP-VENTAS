<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";

// Solo administradores y vendedores pueden acceder a relatorios
requerirRol(['1', '2', '3']);

// Verificar que existe el directorio y archivo del controller
if (file_exists("controllers/RelatorioController.php")) {
    include "controllers/RelatorioController.php";
} else {
    die("Error: No se pudo cargar el controlador de relatorios.");
}

// Instanciar el controller
$controller = new RelatorioController($conexion, $url_base);

// Manejar peticiones API
if ($controller->handleApiRequest()) {
    exit();
}

// Verificar permisos
if (!$controller->verificarPermisos('ver')) {
    header("Location: " . $url_base . "index.php?error=No tienes permisos para acceder a relatorios");
    exit();
}

// Obtener filtros simplificados
$filtros = [
    'cliente' => $_GET['cliente'] ?? '',
    'vendedor' => $_GET['vendedor'] ?? '',
    'estado' => $_GET['estado'] ?? ''
];

// Obtener datos para la vista
$datosFiltros = $controller->obtenerDatosFiltros();
$datosVista = $controller->obtenerDatosVista();
$mensajes = $controller->manejarMensajes();
$configuracionJS = $controller->obtenerConfiguracionJS();

// Extraer datos de vista
extract($datosVista);

$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');          // Hoy (sin cambios)

// Log de actividad
$controller->logActividad('Acceso a relatorio de ventas');
$breadcrumb_items = ['Relatorio de Venta'];
$item_urls = [];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titulo); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <link href="<?php echo $url_base; ?>secciones/relatorio/utils/styles.css" rel="stylesheet" />
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <!-- Header -->
        <div class="row mb-4 fade-in-up">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0 text-dark">
                        <i class="fas fa-chart-bar me-2"></i>Relatorio de Ventas
                        <?php if (!$puede_ver_todos): ?>
                            <span class="badge bg-info ms-2">Mis Ventas</span>
                        <?php endif; ?>
                    </h3>
                    <!-- Botón de Configuración -->
                    <button type="button" class="btn btn-purple text-white" id="btnConfiguracion" data-bs-toggle="modal" data-bs-target="#modalConfiguracion">
                        <i class="fas fa-cogs me-1"></i>Configuración
                    </button>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-card card fade-in-up" style="animation-delay: 0.2s">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros de Consulta</h5>
            </div>
            <div class="card-body">
                <form id="filtrosForm" method="GET" action="">
                    <div class="row g-4">
                        <div class="col-md-2">
                            <label for="fecha_inicio" class="form-label">
                                <i class="fas fa-calendar-alt me-1"></i>Fecha Inicio
                            </label>
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio"
                                value="<?php echo htmlspecialchars($fechaInicio); ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label for="fecha_fin" class="form-label">
                                <i class="fas fa-calendar-alt me-1"></i>Fecha Fin
                            </label>
                            <input type="date" class="form-control" id="fecha_fin" name="fecha_fin"
                                value="<?php echo htmlspecialchars($fechaFin); ?>" required>
                        </div>
                        <?php if ($puede_ver_todos): ?>
                            <div class="col-md-2">
                                <label for="vendedor" class="form-label">
                                    <i class="fas fa-user-tie me-1"></i>Vendedor
                                </label>
                                <select class="form-select" id="vendedor" name="vendedor">
                                    <option value="">Todos los vendedores</option>
                                    <?php foreach ($datosFiltros['vendedores'] as $vendedor): ?>
                                        <?php if ($vendedor['nombre'] === 'Dario') continue; ?>
                                        <option value="<?php echo htmlspecialchars($vendedor['id']); ?>"
                                            <?php echo $filtros['vendedor'] == $vendedor['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($vendedor['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-2">
                            <label for="cliente" class="form-label">
                                <i class="fas fa-user me-1"></i>Cliente
                            </label>
                            <input type="text" class="form-control" id="cliente" name="cliente"
                                value="<?php echo htmlspecialchars($filtros['cliente']); ?>"
                                placeholder="Buscar por nombre del cliente">
                        </div>
                        <div class="col-md-2">
                            <label for="estado" class="form-label">
                                <i class="fas fa-flag me-1"></i>Estado
                            </label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="">Todos los estados</option>
                                <?php foreach ($datosFiltros['estados'] as $estado): ?>
                                    <option value="<?php echo htmlspecialchars($estado); ?>"
                                        <?php echo $filtros['estado'] == $estado ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($estado); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="d-flex gap-2 w-100">
                                <button type="submit" class="btn btn-primary flex-fill">
                                    <i class="fas fa-search me-1"></i>
                                </button>
                                <button type="button" class="btn btn-secondary" id="btnLimpiarFiltros">
                                    <i class="fas fa-eraser me-1"></i>
                                </button>
                                <!-- BOTÓN PARA GENERAR PDF -->
                                <button type="button" class="btn btn-danger" id="btnGenerarPDF" data-bs-toggle="modal" data-bs-target="#modalGenerarPDF">
                                    <i class="fas fa-file-pdf me-1"></i>PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="row mb-4 fade-in-up" style="animation-delay: 0.3s">
            <div class="col-8">
                <div class="chart-container">
                    <!-- TÍTULO MODIFICADO CON CONTROLES -->
                    <div class="chart-title">
                        <div>
                            <i class="fas fa-chart-line me-2"></i>Evolución de Ventas por Período
                            <span class="stats-badge" id="periodoCount">0 días</span>
                        </div>

                        <!-- CONTROLES DE ORDENAMIENTO -->
                        <div class="chart-controls">
                            <span class="text-muted me-2">
                                <i class="fas fa-sort me-1"></i>Mostrar:
                            </span>

                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ordenPeriodo"
                                    id="ordenIngresoPeriodo" value="ingresos" checked>
                                <label class="form-check-label" for="ordenIngresoPeriodo">
                                    <i class="fas fa-dollar-sign me-1"></i>Ingresos
                                </label>
                            </div>

                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ordenPeriodo"
                                    id="ordenVentasPeriodo" value="ventas">
                                <label class="form-check-label" for="ordenVentasPeriodo">
                                    <i class="fas fa-shopping-cart me-1"></i>Nº Ventas
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="loading-overlay" id="loadingVentasPeriodo">
                        <div class="loading-spinner"></div>
                    </div>
                    <canvas id="chartVentasPeriodo"></canvas>
                </div>
            </div>
            <div class="col-4">
                <div class="chart-container">
                    <div class="chart-title">
                        <div>
                            <i class="fas fa-trophy me-2"></i>Top 5 Vendedores
                            <span class="stats-badge" id="top5VendedoresCount">0 vendedores</span>
                        </div>

                        <!-- CONTROLES DE ORDENAMIENTO -->
                        <div class="chart-controls">
                            <span class="text-muted me-2">
                                <i class="fas fa-sort me-1"></i>Por:
                            </span>

                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ordenTop5Vendedores"
                                    id="ordenIngresosTop5" value="ingresos" checked>
                                <label class="form-check-label" for="ordenIngresosTop5">
                                    <i class="fas fa-dollar-sign me-1"></i>Ingresos
                                </label>
                            </div>

                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ordenTop5Vendedores"
                                    id="ordenVentasTop5" value="ventas">
                                <label class="form-check-label" for="ordenVentasTop5">
                                    <i class="fas fa-shopping-cart me-1"></i>Nº Ventas
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="loading-overlay" id="loadingTop5Vendedores">
                        <div class="loading-spinner"></div>
                    </div>
                    <canvas id="chartTop5Vendedores"></canvas>
                </div>
            </div>
            <div class="col-4">
                <div class="chart-container">
                    <!-- TÍTULO MODIFICADO CON CONTROLES -->
                    <div class="chart-title">
                        <div>
                            <i class="fas fa-box me-2"></i>Top 5 Productos
                            <span class="stats-badge" id="productosCount">0 productos</span>
                        </div>

                        <!-- CONTROLES DE ORDENAMIENTO -->
                        <div class="chart-controls">
                            <span class="text-muted me-2">
                                <i class="fas fa-sort me-1"></i>Por:
                            </span>

                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ordenProductos"
                                    id="ordenIngresosProductos" value="ingresos" checked>
                                <label class="form-check-label" for="ordenIngresosProductos">
                                    <i class="fas fa-dollar-sign me-1"></i>Ingresos
                                </label>
                            </div>

                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ordenProductos"
                                    id="ordenVentasProductos" value="ventas">
                                <label class="form-check-label" for="ordenVentasProductos">
                                    <i class="fas fa-shopping-cart me-1"></i>Ventas
                                </label>
                            </div>

                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ordenProductos"
                                    id="ordenCantidadProductos" value="cantidad">
                                <label class="form-check-label" for="ordenCantidadProductos">
                                    <i class="fas fa-cubes me-1"></i>Cantidad
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="loading-overlay" id="loadingProductos">
                        <div class="loading-spinner"></div>
                    </div>
                    <canvas id="chartProductos"></canvas>
                </div>
            </div>
            <div class="col-4">
                <div class="chart-container">
                    <!-- TÍTULO MODIFICADO CON CONTROLES -->
                    <div class="chart-title">
                        <div>
                            <i class="fas fa-industry me-2"></i>Distribución por Sectores
                            <span class="stats-badge" id="sectoresCount">0 sectores</span>
                        </div>

                        <div class="chart-controls">

                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ordenSectores"
                                    id="ordenIngresosSectores" value="ingresos" checked>
                                <label class="form-check-label" for="ordenIngresosSectores">
                                    <i class="fas fa-dollar-sign me-1"></i>Ingresos
                                </label>
                            </div>

                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ordenSectores"
                                    id="ordenVentasSectores" value="ventas">
                                <label class="form-check-label" for="ordenVentasSectores">
                                    <i class="fas fa-shopping-cart me-1"></i>Ventas
                                </label>
                            </div>

                            <!-- ✅ NUEVA: Productos diferentes -->
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ordenSectores"
                                    id="ordenProductosSectores" value="productos">
                                <label class="form-check-label" for="ordenProductosSectores">
                                    <i class="fas fa-boxes me-1"></i>Productos
                                </label>
                            </div>

                            <!-- ✅ NUEVA: Cantidad vendida -->
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ordenSectores"
                                    id="ordenCantidadSectores" value="cantidad">
                                <label class="form-check-label" for="ordenCantidadSectores">
                                    <i class="fas fa-cubes me-1"></i>Cantidad
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="loading-overlay" id="loadingSectores">
                        <div class="loading-spinner"></div>
                    </div>
                    <canvas id="chartDistribucionSectores"></canvas>
                </div>
            </div>
            <div class="col-4">
                <div class="chart-container">
                    <div class="chart-title">
                        <div>
                            <i class="fas fa-chart-pie me-2"></i>Distribución por Moneda
                            <span class="stats-badge" id="monedasCount">0 monedas</span>
                        </div>
                    </div>

                    <div class="loading-overlay" id="loadingMonedas">
                        <div class="loading-spinner"></div>
                    </div>
                    <canvas id="chartDistribucionMonedas"></canvas>
                </div>
            </div>
        </div>

        <!-- Gráfico de Performance por Vendedor con controles de ordenamiento -->
        <div class="row mb-4 fade-in-up" style="animation-delay: 0.4s">
            <div class="col-12">
                <div class="chart-container">
                    <div class="chart-title">
                        <div>
                            <i class="fas fa-users me-2"></i>Performance por Vendedor
                            <span class="stats-badge" id="vendedoresCount">0 vendedores</span>
                        </div>


                        <div class="chart-controls">
                            <span class="text-muted me-2">
                                <i class="fas fa-sort me-1"></i>Ordenar por:
                            </span>

                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ordenVendedores"
                                    id="ordenIngresos" value="ingresos" checked>
                                <label class="form-check-label" for="ordenIngresos">
                                    <i class="fas fa-dollar-sign me-1"></i>Ingresos
                                </label>
                            </div>

                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ordenVendedores"
                                    id="ordenVentas" value="ventas">
                                <label class="form-check-label" for="ordenVentas">
                                    <i class="fas fa-shopping-cart me-1"></i>Nº Ventas
                                </label>
                            </div>

                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ordenVendedores"
                                    id="ordenPromedio" value="promedio">
                                <label class="form-check-label" for="ordenPromedio">
                                    <i class="fas fa-chart-line me-1"></i>Ticket Promedio
                                </label>
                            </div>

                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ordenVendedores"
                                    id="ordenCombinado" value="combinado">
                                <label class="form-check-label" for="ordenCombinado">
                                    <i class="fas fa-trophy me-1"></i>Score Combinado
                                </label>
                            </div>

                            <!-- ✨ NUEVO BOTÓN "SABER MÁS" -->
                            <button type="button" class="btn btn-outline-info btn-sm ms-2"
                                id="btnInfoVendedores"
                                data-bs-toggle="modal"
                                data-bs-target="#modalInfoVendedores"
                                title="Información sobre los filtros">
                                <i class="fas fa-info-circle me-1"></i>Saber más
                            </button>
                        </div>
                    </div>

                    <div class="loading-overlay" id="loadingVendedores">
                        <div class="loading-spinner"></div>
                    </div>

                    <canvas id="chartVendedores"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabla de ventas detalladas -->
        <div class="table-container fade-in-up" style="animation-delay: 0.5s">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Ventas Detalladas</h5>
                <div class="loading-overlay" id="loadingTabla">
                    <div class="loading-spinner"></div>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-primary" id="totalRegistros">0 registros</span>
                    <span class="badge bg-success">
                        <i class="fas fa-dollar-sign me-1"></i>
                    </span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="tablaVentasDetalladas">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag me-1"></i>ID</th>
                            <th><i class="fas fa-calendar me-1"></i>Fecha</th>
                            <th><i class="fas fa-user me-1"></i>Cliente</th>
                            <?php if ($puede_ver_todos): ?>
                                <th><i class="fas fa-user-tie me-1"></i>Vendedor</th>
                            <?php endif; ?>
                            <th><i class="fas fa-flag me-1"></i>Estado</th>
                            <th><i class="fas fa-dollar-sign me-1"></i>Total</th>
                            <th><i class="fas fa-credit-card me-1"></i>Tipo Pago</th>
                            <th><i class="fas fa-box me-1"></i>Productos</th>
                            <th><i class="fas fa-cogs me-1"></i>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Se llenará via JavaScript -->
                    </tbody>
                </table>
            </div>
            <div id="controlesPaginacion" class="mt-3"></div>

        </div>
    </div>

    <!-- Toast container -->
    <div class="toast-container position-fixed top-0 end-0 p-3"></div>

    <!-- Modal de Productos de Venta -->
    <div class="modal fade" id="modalProductosVenta" tabindex="-1" aria-labelledby="modalProductosVentaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalProductosVentaLabel">
                        <i class="fas fa-shopping-cart me-2"></i>Productos de la Venta
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Información de la venta -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="card bg-light border-0">
                                <div class="card-body py-2">
                                    <small class="text-muted">Venta ID:</small>
                                    <strong id="ventaId" class="d-block">#</strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light border-0">
                                <div class="card-body py-2">
                                    <small class="text-muted">Cliente:</small>
                                    <strong id="ventaCliente" class="d-block">-</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loading productos -->
                    <div id="loadingProductosModal" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando productos...</span>
                        </div>
                        <p class="mt-2 text-muted">Cargando productos de la venta...</p>
                    </div>

                    <!-- Tabla de productos -->
                    <div id="tablaProductosContainer" style="display: none;">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th><i class="fas fa-cube me-1"></i>Producto</th>
                                        <th class="text-center"><i class="fas fa-sort-numeric-up me-1"></i>Cantidad</th>
                                        <th class="text-end"><i class="fas fa-dollar-sign me-1"></i>Precio Unit.</th>
                                        <th class="text-end"><i class="fas fa-calculator me-1"></i>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaProductosModal">
                                    <!-- Se llenará vía JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Mensaje si no hay productos -->
                        <div id="noProductosMessage" class="text-center py-4" style="display: none;">
                            <i class="fas fa-box-open text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-2">No hay productos registrados</h5>
                            <p class="text-muted">Esta venta no tiene productos asociados.</p>
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

    <!-- MODAL PARA GENERAR PDF -->
    <div class="modal fade" id="modalGenerarPDF" tabindex="-1" aria-labelledby="modalGenerarPDFLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalGenerarPDFLabel">
                        <i class="fas fa-file-pdf me-2"></i>Generar Documento
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formGenerarPDF">
                        <!-- Información del período actual -->
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Configuración actual:</strong>
                            <div id="infoConfiguracionActual" class="mt-2">
                                <!-- Se llenará vía JavaScript -->
                            </div>
                        </div>

                        <!-- Filtros para el PDF -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="pdf_fecha_inicio" class="form-label">
                                    <i class="fas fa-calendar-alt me-1"></i>Fecha Inicio
                                </label>
                                <input type="date" class="form-control" id="pdf_fecha_inicio" name="fecha_inicio" required>
                            </div>
                            <div class="col-md-6">
                                <label for="pdf_fecha_fin" class="form-label">
                                    <i class="fas fa-calendar-alt me-1"></i>Fecha Fin
                                </label>
                                <input type="date" class="form-control" id="pdf_fecha_fin" name="fecha_fin" required>
                            </div>

                            <?php if ($puede_ver_todos): ?>
                                <div class="col-md-6">
                                    <label for="pdf_vendedor" class="form-label">
                                        <i class="fas fa-user-tie me-1"></i>Vendedor
                                    </label>
                                    <select class="form-select" id="pdf_vendedor" name="vendedor">
                                        <option value="">Todos los vendedores</option>
                                        <?php foreach ($datosFiltros['vendedores'] as $vendedor): ?>
                                            <?php
                                            // Mismo filtro robusto
                                            $nombreLimpio = trim(strtolower($vendedor['nombre']));
                                            if (in_array($nombreLimpio, ['dario', 'darío', 'dário'])) continue;
                                            ?>
                                            <option value="<?php echo htmlspecialchars($vendedor['id']); ?>">
                                                <?php echo htmlspecialchars($vendedor['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <div class="col-md-6">
                                <label for="pdf_cliente" class="form-label">
                                    <i class="fas fa-user me-1"></i>Cliente
                                </label>
                                <input type="text" class="form-control" id="pdf_cliente" name="cliente"
                                    placeholder="Filtrar por nombre del cliente">
                            </div>

                            <div class="col-md-6">
                                <label for="pdf_estado" class="form-label">
                                    <i class="fas fa-flag me-1"></i>Estado
                                </label>
                                <select class="form-select" id="pdf_estado" name="estado">
                                    <option value="">Todos los estados</option>
                                    <?php foreach ($datosFiltros['estados'] as $estado): ?>
                                        <option value="<?php echo htmlspecialchars($estado); ?>">
                                            <?php echo htmlspecialchars($estado); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Opciones adicionales -->
                        <div class="mt-4">
                            <h6 class="mb-3"><i class="fas fa-cogs me-2"></i>Opciones del Documento</h6>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="incluir_totales" name="incluir_totales" checked>
                                        <label class="form-check-label" for="incluir_totales">
                                            <i class="fas fa-calculator me-1"></i>Incluir Resumen de Totales
                                        </label>
                                    </div>
                                    <small class="text-muted">Muestra resumen de métricas al final</small>
                                </div>

                                <!-- ✅ AGREGAR ESTA OPCIÓN QUE FALTABA -->
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="incluir_graficos" name="incluir_graficos">
                                        <label class="form-check-label" for="incluir_graficos">
                                            <i class="fas fa-chart-pie me-1"></i>Incluir Gráficos
                                        </label>
                                    </div>
                                    <small class="text-muted">Agrega gráficos de vendedores y monedas</small>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="incluir_productos" name="incluir_productos">
                                        <label class="form-check-label" for="incluir_productos">
                                            <i class="fas fa-boxes me-1"></i>Incluir Lista de Productos
                                        </label>
                                    </div>
                                    <small class="text-muted">Detalla productos por cada venta</small>
                                </div>

                                <div class="col-md-6">
                                    <label for="formato_papel" class="form-label">
                                        <i class="fas fa-file me-1"></i>Formato de Papel
                                    </label>
                                    <select class="form-select" id="formato_papel" name="formato_papel">
                                        <option value="A4">A4 (Vertical)</option>
                                        <option value="A4_horizontal">A4 (Horizontal)</option>
                                        <option value="Letter">Letter</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Agregar después de la sección "Opciones del Documento" y antes de "Vista previa de configuración" -->

                        <!-- Opciones de Agrupación en Horizontal -->
                        <div class="mt-4">
                            <h6 class="mb-3"><i class="fas fa-layer-group me-2"></i>Opciones de Agrupación</h6>

                            <div class="d-flex flex-wrap gap-4 align-items-center">
                                <!-- Agrupación de Ventas (mutuamente excluyente) -->
                                <div class="d-flex gap-3 me-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_agrupacion_ventas" id="sin_agrupacion" value="" checked>
                                        <label class="form-check-label" for="sin_agrupacion">
                                            <i class="fas fa-list me-1"></i>Sin Agrupación
                                        </label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_agrupacion_ventas" id="agrupar_por_cliente" value="cliente">
                                        <label class="form-check-label" for="agrupar_por_cliente">
                                            <i class="fas fa-users me-1"></i>Agrupar por Cliente
                                        </label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_agrupacion_ventas" id="agrupar_por_vendedor" value="vendedor">
                                        <label class="form-check-label" for="agrupar_por_vendedor">
                                            <i class="fas fa-user-tie me-1"></i>Agrupar por Vendedor
                                        </label>
                                    </div>
                                </div>

                                <!-- Separador visual -->
                                <div class="vr d-none d-md-block"></div>

                                <!-- Agrupación de Productos (independiente) -->
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="agrupar_productos" name="agrupar_productos">
                                    <label class="form-check-label" for="agrupar_productos">
                                        <i class="fas fa-boxes me-1"></i>Agrupar Productos por Nombre
                                    </label>
                                </div>
                            </div>

                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-info-circle me-1"></i>Selecciona cómo agrupar las ventas + opcionalmente agrupar productos idénticos
                            </small>
                        </div>

                        <!-- Vista previa de configuración -->
                        <div class="mt-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-eye me-2"></i>Vista Previa
                                    </h6>
                                    <div id="vistaPreviewConfiguracion">
                                        <small class="text-muted">Configure los filtros para ver la vista previa</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" id="btnCopiarFiltrosActuales">
                        <i class="fas fa-copy me-1"></i>Usar Filtros Actuales
                    </button>
                    <button type="button" class="btn btn-danger" id="btnGenerarDocumento">
                        <i class="fas fa-download me-1"></i>Generar PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE CONFIGURACIÓN DE TASAS -->
    <div class="modal fade" id="modalConfiguracion" tabindex="-1" aria-labelledby="modalConfiguracionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-purple text-white">
                    <h5 class="modal-title" id="modalConfiguracionLabel">
                        <i class="fas fa-cogs me-2"></i>Configuración de Tasas de Conversión
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Información sobre las tasas -->
                    <div class="tasa-info">
                        <h6 class="mb-3">
                            <i class="fas fa-info-circle me-2"></i>Información de Tasas de Conversión
                        </h6>
                        <p class="mb-1">
                            <strong>Base de conversión:</strong> Todas las monedas se convierten a USD (Dólares estadounidenses)
                        </p>
                        <p class="mb-1">
                            <strong>Actualización:</strong> Los cambios se aplicarán inmediatamente en todos los reportes
                        </p>
                        <p class="mb-0">
                            <strong>Última actualización:</strong> <span id="ultimaActualizacion">-</span>
                        </p>
                    </div>

                    <!-- Loading para cargar tasas -->
                    <div id="loadingTasas" class="text-center py-4">
                        <div class="spinner-border text-purple" role="status">
                            <span class="visually-hidden">Cargando tasas...</span>
                        </div>
                        <p class="mt-2 text-muted">Cargando tasas de conversión...</p>
                    </div>

                    <!-- Tabla de tasas -->
                    <div id="tablaTasasContainer" style="display: none;">
                        <div class="table-responsive">
                            <table class="table table-tasas table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="20%">
                                            <i class="fas fa-coins me-2"></i>Moneda
                                        </th>
                                        <th width="15%" class="text-center">
                                            <i class="fas fa-eye me-2"></i>Símbolo
                                        </th>
                                        <th width="40%">
                                            <i class="fas fa-calculator me-2"></i>Tasa de Conversión
                                        </th>
                                        <th width="25%" class="text-center">
                                            <i class="fas fa-info me-2"></i>Equivalencia
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="tablaTasas">
                                    <!-- Se llenará vía JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mensaje de error -->
                    <div id="errorTasas" class="alert alert-danger" style="display: none;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Error al cargar las tasas de conversión</strong>
                        <p class="mb-0">No se pudieron obtener las tasas actuales. Intente recargar la página.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-info" id="btnRecargarTasas">
                        <i class="fas fa-sync me-1"></i>Recargar
                    </button>
                    <button type="button" class="btn btn-success" id="btnGuardarTasas">
                        <i class="fas fa-save me-1"></i>Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE INFORMACIÓN DE FILTROS -->
    <div class="modal fade" id="modalInfoVendedores" tabindex="-1" aria-labelledby="modalInfoVendedoresLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="modalInfoVendedoresLabel">
                        <i class="fas fa-info-circle me-2"></i>¿Cómo ordenar vendedores?
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-4">Cada opción ordena a los vendedores de mayor a menor según diferentes criterios:</p>

                    <div class="row g-4">
                        <!-- Ingresos -->
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-dollar-sign text-success" style="font-size: 2rem;"></i>
                                    </div>
                                    <h6 class="card-title text-success">Ingresos</h6>
                                    <p class="card-text small">
                                        Ordena por el <strong>dinero total</strong> generado por cada vendedor.
                                        <br><br>
                                        <span class="text-muted">Ideal para ver quién aporta más al negocio.</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Número de Ventas -->
                        <div class="col-md-6">
                            <div class="card border-primary">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-shopping-cart text-primary" style="font-size: 2rem;"></i>
                                    </div>
                                    <h6 class="card-title text-primary">Nº Ventas</h6>
                                    <p class="card-text small">
                                        Ordena por la <strong>cantidad de ventas</strong> realizadas.
                                        <br><br>
                                        <span class="text-muted">Perfecto para ver quién es más activo.</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Ticket Promedio -->
                        <div class="col-md-6">
                            <div class="card border-warning">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-chart-line text-warning" style="font-size: 2rem;"></i>
                                    </div>
                                    <h6 class="card-title text-warning">Ticket Promedio</h6>
                                    <p class="card-text small">
                                        Ordena por el <strong>valor promedio</strong> por venta.
                                        <br><br>
                                        <span class="text-muted">Muestra quién vende productos más costosos.</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Score Combinado -->
                        <div class="col-md-6">
                            <div class="card border-danger">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-trophy text-danger" style="font-size: 2rem;"></i>
                                    </div>
                                    <h6 class="card-title text-danger">Score Combinado</h6>
                                    <p class="card-text small">
                                        Combina <strong>todas las métricas</strong> en un puntaje justo.
                                        <br><br>
                                        <span class="text-muted">Evaluación integral del rendimiento.</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Explicación del Score Combinado -->
                    <div class="alert alert-light border-danger mt-4">
                        <h6 class="alert-heading">
                            <i class="fas fa-trophy text-danger me-2"></i>¿Cómo funciona el Score Combinado?
                        </h6>
                        <p class="mb-2">Es una fórmula que considera:</p>
                        <ul class="mb-2">
                            <li><strong>40%</strong> - Ingresos totales</li>
                            <li><strong>35%</strong> - Cantidad de ventas</li>
                            <li><strong>25%</strong> - Ticket promedio</li>
                        </ul>
                        <p class="mb-0">
                            <small class="text-muted">
                                <i class="fas fa-lightbulb me-1"></i>
                                Resultado: Un score de 0-100 que evalúa el rendimiento integral.
                            </small>
                        </p>
                    </div>

                    <!-- Botón para mostrar explicación de Percentiles -->
                    <div class="text-center mt-4">
                        <button class="btn btn-outline-info btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePercentiles" aria-expanded="false" aria-controls="collapsePercentiles">
                            <i class="fas fa-question-circle me-1"></i>¿Qué son los percentiles?
                            <i class="fas fa-chevron-down ms-1"></i>
                        </button>
                    </div>

                    <!-- Sección colapsable: Explicación de Percentiles -->
                    <div class="collapse mt-3" id="collapsePercentiles">
                        <div class="alert alert-info border-info">
                            <h6 class="alert-heading">
                                <i class="fas fa-chart-bar text-info me-2"></i>¿Qué son los percentiles?
                            </h6>
                            <p class="mb-2">El sistema usa <strong>percentiles</strong> para hacer comparaciones justas entre vendedores:</p>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <div class="text-center p-2 bg-success bg-opacity-10 rounded">
                                        <strong class="text-success">Percentil 100%</strong>
                                        <br><small class="text-muted">Primer lugar del equipo</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center p-2 bg-warning bg-opacity-10 rounded">
                                        <strong class="text-warning">Percentil 50%</strong>
                                        <br><small class="text-muted">Promedio del equipo</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center p-2 bg-danger bg-opacity-10 rounded">
                                        <strong class="text-danger">Percentil 0%</strong>
                                        <br><small class="text-muted">Último lugar del equipo</small>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-light p-3 rounded">
                                <h6 class="mb-2">
                                    <i class="fas fa-calculator text-primary me-1"></i>Ejemplo de cálculo:
                                </h6>
                                <small class="text-muted">
                                    Si un vendedor tiene percentil 75% en ingresos, 50% en ventas y 25% en ticket promedio:
                                    <br>
                                    <strong>Score = (75 × 0.40) + (50 × 0.35) + (25 × 0.25) = 30 + 17.5 + 6.25 = 53.75 puntos</strong>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-info" data-bs-dismiss="modal">
                        <i class="fas fa-check me-1"></i>Entendido
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        window.CONFIG_RELATORIO = <?php echo json_encode($configuracionJS); ?>;
        window.PUEDE_VER_TODOS = <?php echo $puede_ver_todos ? 'true' : 'false'; ?>;
        window.ES_ADMIN = <?php echo $es_admin ? 'true' : 'false'; ?>;
        window.DATOS_FILTROS = <?php echo json_encode($datosFiltros); ?>;
        window.MENSAJES = <?php echo json_encode($mensajes); ?>;
    </script>

    <script src="<?php echo $url_base; ?>secciones/relatorio/js/utils.js"></script>
    <script src="<?php echo $url_base; ?>secciones/relatorio/js/tasas.js"></script>
    <script src="<?php echo $url_base; ?>secciones/relatorio/js/graficos.js"></script>
    <script src="<?php echo $url_base; ?>secciones/relatorio/js/tabla.js"></script>
    <script src="<?php echo $url_base; ?>secciones/relatorio/js/pdf.js"></script>
    <script src="<?php echo $url_base; ?>secciones/relatorio/js/config.js"></script>
</body>

</html>