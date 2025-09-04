<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();

// Verificar que existe el directorio y archivo del controller
if (file_exists("controllers/produccionController.php")) {
    include "controllers/produccionController.php";
} else {
    die("Error: No se pudo cargar el controlador de producción.");
}

// Manejar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Establecer URL base si no está definida
if (!isset($url_base) || empty($url_base)) {
    // Detectar automáticamente la URL base
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = dirname(dirname($script)); // Subir dos niveles desde /secciones/produccion/
    $url_base = $protocol . '://' . $host . $path . '/';
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titulo); ?></title>

    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $url_base; ?>assets/favicon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $url_base; ?>assets/favicon-192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $url_base; ?>assets/apple-touch-icon.png">
    <link rel="icon" href="<?php echo $url_base; ?>utils/icon.ico" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/relatorio/utils/relatorio.css">

    <style>
        /* Estilos adicionales para los campos de hora */
        .hora-container {
            border: 2px dashed #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            background-color: #f9fafb;
            transition: all 0.3s ease;
        }

        .hora-container.visible {
            border-color: #325b91;
            background-color: rgba(50, 91, 145, 0.05);
        }

        .hora-info {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .hora-info i {
            color: #325b91;
        }

        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .campo-hora input {
            font-family: 'Roboto Mono', monospace;
            font-weight: 500;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $url_base; ?>index.php">
                <img src="<?php echo $url_base; ?>utils/logoa.png" alt="America TNT" height="30">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $url_base; ?>index.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <a class="nav-link" href="<?php echo $url_base; ?>secciones/relatorio/main.php">
                        <i class="fas fa-file-alt"></i>
                        Reportes
                    </a>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="fas fa-chart-line me-1"></i>Reporte General
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="hero-title">
                        <i class="fas fa-chart-line me-3"></i>
                        Reporte General
                    </h1>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <div class="container-fluid">
            <!-- Filtros de Consulta -->
            <div class="filtros-container">
                <div class="filtros-titulo">
                    <i class="fas fa-filter text-success"></i>
                    Filtros de Consulta
                </div>
                <form id="filtrosForm">
                    <div class="row">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <label for="fechaInicio" class="form-label">
                                <i class="fas fa-calendar me-1"></i>Fecha Inicio
                            </label>
                            <input
                                type="date"
                                id="fechaInicio"
                                name="fecha_inicio"
                                class="form-control"
                                value="<?php echo $filtros_aplicados['fecha_inicio'] ?? date('Y-01-01'); ?>"
                                required>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <label for="fechaFin" class="form-label">
                                <i class="fas fa-calendar me-1"></i>Fecha Fin
                            </label>
                            <input
                                type="date"
                                id="fechaFin"
                                name="fecha_fin"
                                class="form-control"
                                value="<?php echo $filtros_aplicados['fecha_fin'] ?? date('Y-m-d'); ?>"
                                required>
                        </div>
                        <div class="col-lg-2 col-md-6 mb-3">
                            <label for="operador" class="form-label">
                                <i class="fas fa-user me-1"></i>Operador
                            </label>
                            <select id="operador" name="operador" class="form-select">
                                <option value="">Todos los operadores</option>
                                <?php foreach ($operadores as $op): ?>
                                    <option value="<?php echo htmlspecialchars($op); ?>"
                                        <?php echo ($filtros_aplicados['operador'] ?? '') === $op ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($op); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6 mb-3">
                            <label for="tipoProducto" class="form-label">
                                <i class="fas fa-tags me-1"></i>Tipo Producto
                            </label>
                            <select id="tipoProducto" name="tipo_producto" class="form-select">
                                <option value="">Todos los tipos</option>
                                <?php foreach ($tipos_producto as $tipo): ?>
                                    <option value="<?php echo htmlspecialchars($tipo); ?>"
                                        <?php echo ($filtros_aplicados['tipo_producto'] ?? '') === $tipo ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tipo); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-12 mb-3">
                            <label for="estado" class="form-label">
                                <i class="fas fa-info-circle me-1"></i>Estado
                            </label>
                            <select id="estado" name="estado" class="form-select">
                                <option value="">Todos los estados</option>
                                <?php foreach ($estados as $est): ?>
                                    <option value="<?php echo htmlspecialchars($est); ?>"
                                        <?php echo ($filtros_aplicados['estado'] ?? '') === $est ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($est); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- ✅ NUEVO: Filtros de Horario -->
                    <div class="hora-container" id="horaContainer" style="display: none;">
                        <div class="hora-info">
                            <i class="fas fa-clock"></i>
                            <strong>Filtro por Horario</strong>
                            <small>(Disponible cuando seleccionas un solo día)</small>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="horaInicio" class="form-label">
                                    <i class="fas fa-clock me-1"></i>Hora Inicio
                                </label>
                                <div class="campo-hora">
                                    <input
                                        type="time"
                                        id="horaInicio"
                                        name="hora_inicio"
                                        class="form-control"
                                        value="00:00">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="horaFin" class="form-label">
                                    <i class="fas fa-clock me-1"></i>Hora Fin
                                </label>
                                <div class="campo-hora">
                                    <input
                                        type="time"
                                        id="horaFin"
                                        name="hora_fin"
                                        class="form-control"
                                        value="23:59">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="btn-group w-100" role="group">
                                <button type="submit" class="btn btn-filtro">
                                    <i class="fas fa-search me-2"></i>Consultar
                                </button>
                                <button type="button" class="btn btn-limpiar" onclick="limpiarFiltros()">
                                    <i class="fas fa-eraser me-2"></i>Limpiar
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Estadísticas Principales -->
            <div class="row mb-4" id="estadisticasContainer">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card stat-primary">
                        <div class="stat-icon">
                            <i class="fas fa-industry"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="totalBobinas">0</h3>
                            <p>Total Bobinas</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-3">
                    <div class="stat-card stat-success">
                        <div class="stat-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="totalItems">0</h3>
                            <p>Items Producidos</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-3">
                    <div class="stat-card stat-warning">
                        <div class="stat-icon">
                            <i class="fas fa-weight-hanging"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="totalPeso">0</h3>
                            <p>Peso Total (kg)</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-3">
                    <div class="stat-card stat-warning">
                        <div class="stat-icon">
                            <i class="fas fa-weight-hanging"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="totalPesoli">0</h3>
                            <p>Peso Liq Total (kg)</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card stat-danger">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="totalOperadores">0</h3>
                            <p>Operadores Activos</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficos y Top Productos -->
            <div class="row">
                <!-- Evolución de Producción -->
                <div class="col-lg-8 mb-4">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h5 class="chart-titulo">
                                <i class="fas fa-chart-line text-primary me-2"></i>
                                Evolución de Producción por Período
                                <span class="badge bg-primary ms-2" id="periodoMostrar">7 meses</span>
                            </h5>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="mostrarMetrica" id="mostrarBobinas" value="bobinas" checked>
                                <label class="btn btn-outline-primary" for="mostrarBobinas">Bobinas</label>

                                <input type="radio" class="btn-check" name="mostrarMetrica" id="mostrarItems" value="items">
                                <label class="btn btn-outline-primary" for="mostrarItems">Items</label>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="chartEvolucion"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top 5 Productos -->
                <div class="col-lg-4 mb-4">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h5 class="chart-titulo">
                                <i class="fas fa-trophy text-warning me-0"></i>
                                Top 5 Productos mas Producidos
                                <span class="badge bg-warning ms-0" id="topProductosCount">5 productos</span>
                            </h5>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="ordenarPor" id="porBobinas" value="bobinas" checked>
                                <label class="btn btn-outline-warning" for="porBobinas">Bobinas</label>

                                <input type="radio" class="btn-check" name="ordenarPor" id="porVentas" value="ventas">
                                <label class="btn btn-outline-warning" for="porVentas">Items</label>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="productos-lista" id="topProductosLista">
                                <div class="sin-datos">
                                    <i class="fas fa-box-open"></i>
                                    <h5>Sin datos</h5>
                                    <p>Selecciona un período para ver los productos más producidos</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-6 mb-4">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h5 class="chart-titulo">
                                    <i class="fas fa-chart-bar text-info me-2"></i>
                                    Performance por Sector (Considerando Tara)
                                    <span class="badge bg-info ms-2" id="sectorCount">Sectores</span>
                                </h5>
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="metricaSector" id="metricaTara" value="tara" checked>
                                    <label class="btn btn-outline-info" for="metricaTara">Tara Total (kg)</label>

                                    <input type="radio" class="btn-check" name="metricaSector" id="metricaEficiencia" value="eficiencia">
                                    <label class="btn btn-outline-info" for="metricaEficiencia">% Eficiencia</label>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="chartSector"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h5 class="chart-titulo">Clasificación de Peso</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="chartClasificacion"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center text-white">
            <div class="loading-spinner mb-3"></div>
            <h5>Cargando datos...</h5>
            <p>Por favor espere mientras procesamos la información</p>
        </div>
    </div>

    <!-- Lista de Productos Producidos -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="dashboard-card">
                <div class="card-header">
                    <h5 class="chart-titulo">
                        <i class="fas fa-list text-primary me-2"></i>
                        Productos Producidos
                        <span class="badge bg-primary ms-2" id="totalProductosCount">0 productos</span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="tablaProductos">
                            <thead class="table-dark">
                                <tr>
                                    <th>Fecha/Hora</th>
                                    <th>Producto</th>
                                    <th>Tipo</th>
                                    <th>Bobinas</th>
                                    <th>Metragem</th>
                                    <th>Peso (kg)</th>
                                    <th>Eficiencia</th>
                                    <th>Estado</th>
                                    <th>Operador</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyProductos">
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="fas fa-spinner fa-spin"></i> Cargando productos...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div class="d-flex justify-content-between align-items-center p-3 border-top">
                        <div class="text-muted">
                            <small id="infoPaginacion">Mostrando 0 de 0 productos</small>
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0" id="paginacionProductos">
                                <!-- Se genera dinámicamente -->
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Configuración global para JavaScript -->
    <script>
        const PRODUCCION_CONFIG = <?php echo json_encode($configuracionJS); ?>;
        console.log('🔧 Configuración cargada:', PRODUCCION_CONFIG);
    </script>

    <!-- Script principal del sistema -->
    <script src="js/reportes_produccion.js"></script>

</body>

</html>