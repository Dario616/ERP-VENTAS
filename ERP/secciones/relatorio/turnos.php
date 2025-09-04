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
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = dirname(dirname($script));
    $url_base = $protocol . '://' . $host . $path . '/';
}

// Instanciar el controller
$controller = new ProduccionController($conexion, $url_base);

// Manejar peticiones API
if ($controller->handleApiRequest()) {
    exit();
}

// Obtener datos para la vista
$datosVista = $controller->obtenerDatosVista();
$configuracionJS = $controller->obtenerConfiguracionJS();

// Extraer datos de vista con valores por defecto
$titulo = "Consulta por Turnos - America TNT";
$url_base = $datosVista['url_base'] ?? '';
$usuario_actual = $datosVista['usuario_actual'] ?? 'Usuario';
$filtros_aplicados = $datosVista['filtros_aplicados'] ?? [];
$tipos_producto = $datosVista['tipos_producto'] ?? [];
$operadores = $datosVista['operadores'] ?? [];
$estados = $datosVista['estados'] ?? [];
$breadcrumb_items = ['REPORTES', 'TURNOS'];
$item_urls = [
    $url_base . 'secciones/relatorio/main.php',
];
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

    <style>
        /* Variables CSS */
        :root {
            --america-navy: #325b91;
            --america-navy-dark: #152c47;
            --america-red: #dc2626;
            --america-orange: #ea580c;
            --america-gray: #64748b;
            --america-success: #059669;
            --america-warning: #d97706;

            --text-primary: #1e293b;
            --text-secondary: var(--america-gray);
            --text-white: #ffffff;
            --shadow-md: 0 4px 6px rgba(30, 58, 95, 0.1);
            --shadow-lg: 0 8px 25px rgba(30, 58, 95, 0.15);
        }

        /* Base */
        body {
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--text-primary);
            margin: 0;
            min-height: 100vh;
        }

        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, var(--america-navy-dark) 0%, var(--america-navy) 100%);
            box-shadow: var(--shadow-lg);
            border-bottom: 3px solid var(--america-red);
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--text-white) !important;
            font-size: 1.2rem;
        }

        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.5rem 1rem !important;
            border-radius: 6px;
            margin: 0 0.2rem;
            transition: all 0.3s ease;
        }

        .navbar-dark .navbar-nav .nav-link:hover {
            background-color: rgba(220, 38, 38, 0.2);
            color: var(--text-white) !important;
        }

        .navbar-dark .navbar-nav .nav-link.active {
            background: var(--america-red);
            color: var(--text-white) !important;
        }

        /* Header */
        .page-header {
            background: linear-gradient(135deg, var(--america-navy-dark) 0%, var(--america-navy) 100%);
            color: var(--text-white);
            padding: 1.5rem 0;
            margin-top: 56px;
            position: relative;
        }

        .page-header::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--america-red) 0%, var(--america-orange) 100%);
        }

        .page-title {
            font-size: 1.6rem;
            font-weight: 800;
            margin: 0;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
        }

        .page-title i {
            color: var(--america-red);
            margin-right: 1rem;
        }

        /* Main Container */
        .main-container {
            padding: 1.5rem 0;
            min-height: calc(100vh - 200px);
        }

        /* Filtros Card */
        .filtros-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }

        .filtros-titulo {
            color: var(--america-navy);
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .form-label i {
            color: var(--america-red);
            margin-right: 0.25rem;
        }

        .form-control,
        .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--america-red);
            box-shadow: 0 0 0 0.2rem rgba(220, 38, 38, 0.1);
        }

        /* Horario Container */
        .horario-container {
            border: 2px dashed #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            background-color: #f9fafb;
            margin-top: 0.75rem;
            transition: all 0.3s ease;
            display: none;
        }

        .horario-container.active {
            border-color: var(--america-navy);
            background-color: rgba(50, 91, 145, 0.05);
            display: block;
        }

        .horario-info {
            font-size: 0.8rem;
            color: var(--america-gray);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .horario-info i {
            color: var(--america-red);
        }

        /* Botones */
        .btn {
            border-radius: 6px;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border: none;
            transition: all 0.3s ease;
            font-size: 0.8rem;
        }

        .btn-consultar {
            background: linear-gradient(135deg, var(--america-navy) 0%, var(--america-navy-dark) 100%);
            color: white;
        }

        .btn-consultar:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .btn-limpiar {
            background: linear-gradient(135deg, var(--america-gray) 0%, #475569 100%);
            color: white;
        }



        /* Resumen Card */
        .resumen-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 5px solid var(--america-red);
        }

        .resumen-titulo {
            color: var(--america-navy);
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }

        .resumen-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--america-navy);
            font-family: 'Roboto Mono', monospace;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 600;
            margin-top: 0.5rem;
        }

        /* Tabla */
        .datos-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .datos-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1rem 1.5rem;
            border-bottom: 3px solid var(--america-red);
        }

        .datos-titulo {
            color: var(--america-navy);
            font-weight: 700;
            font-size: 1.1rem;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
        }

        .table {
            margin-bottom: 0;
            font-size: 0.9rem;
        }

        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid var(--america-red);
            color: var(--text-primary);
            font-weight: 700;
            font-size: 0.8rem;
            padding: 1rem 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(50, 91, 145, 0.05);
        }

        /* Paginación */
        .pagination-container {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-top: 1px solid #e9ecef;
            background: #f8f9fa;
        }

        .pagination .page-link {
            color: var(--america-navy);
            border: 1px solid #dee2e6;
            transition: all 0.2s ease;
        }

        .pagination .page-link:hover {
            background-color: rgba(50, 91, 145, 0.1);
            border-color: var(--america-navy);
        }

        .pagination .page-item.active .page-link {
            background-color: var(--america-navy);
            border-color: var(--america-navy);
        }

        /* Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: var(--shadow-lg);
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--america-navy);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Sin datos */
        .sin-datos {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .sin-datos i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .sin-datos h5 {
            color: var(--america-navy);
            margin-bottom: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-title {
                font-size: 1.3rem;
            }

            .filtros-card {
                padding: 0.75rem;
            }

            .resumen-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .table {
                font-size: 0.8rem;
            }

            .pagination-container {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .form-label {
                font-size: 0.7rem;
            }

            .btn-group .btn {
                font-size: 0.75rem;
                padding: 0.4rem 0.75rem;
            }
        }
    </style>
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container-fluid">
            <h1 class="page-title">
                <i class="fas fa-clock"></i>
                Consulta de Producción por Turnos
            </h1>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <div class="container-fluid">

            <!-- Filtros -->
            <div class="filtros-card">
                <div class="filtros-titulo">
                    <i class="fas fa-filter"></i>
                    Filtros de Consulta
                </div>
                <form id="filtrosForm">
                    <div class="row">
                        <div class="col-lg-2 col-md-3 mb-2">
                            <label for="fechaInicio" class="form-label">
                                <i class="fas fa-calendar-alt"></i>Fecha Inicio
                            </label>
                            <input type="date" id="fechaInicio" name="fecha_inicio" class="form-control form-control-sm"
                                value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-lg-2 col-md-3 mb-2">
                            <label for="fechaFin" class="form-label">
                                <i class="fas fa-calendar-alt"></i>Fecha Fin
                            </label>
                            <input type="date" id="fechaFin" name="fecha_fin" class="form-control form-control-sm"
                                value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-lg-2 col-md-2 mb-2">
                            <label for="operador" class="form-label">
                                <i class="fas fa-user"></i>Operador
                            </label>
                            <select id="operador" name="operador" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <?php foreach ($operadores as $op): ?>
                                    <option value="<?php echo htmlspecialchars($op); ?>">
                                        <?php echo htmlspecialchars($op); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-2 mb-2">
                            <label for="tipoProducto" class="form-label">
                                <i class="fas fa-tags"></i>Tipo Producto
                            </label>
                            <select id="tipoProducto" name="tipo_producto" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <?php foreach ($tipos_producto as $tipo): ?>
                                    <option value="<?php echo htmlspecialchars($tipo); ?>">
                                        <?php echo htmlspecialchars($tipo); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-2 mb-2">
                            <label for="estado" class="form-label">
                                <i class="fas fa-info-circle"></i>Estado
                            </label>
                            <select id="estado" name="estado" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <?php foreach ($estados as $est): ?>
                                    <option value="<?php echo htmlspecialchars($est); ?>">
                                        <?php echo htmlspecialchars($est); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-12 mb-2 d-flex align-items-end">
                            <div class="btn-group w-100" role="group">
                                <button type="submit" class="btn btn-consultar btn-sm">
                                    <i class="fas fa-search me-1"></i>Consultar
                                </button>
                                <button type="button" class="btn btn-limpiar btn-sm" onclick="limpiarFiltros()">
                                    <i class="fas fa-broom me-1"></i>Limpiar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros de Horario -->
                    <div class="horario-container" id="horarioContainer">
                        <div class="horario-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Filtro por Horario Específico</strong>
                            <small>(Disponible cuando seleccionas el mismo día en inicio y fin)</small>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label for="horaInicio" class="form-label">
                                    <i class="fas fa-clock"></i>Hora Inicio
                                </label>
                                <input type="time" id="horaInicio" name="hora_inicio" class="form-control form-control-sm" value="06:00">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label for="horaFin" class="form-label">
                                    <i class="fas fa-clock"></i>Hora Fin
                                </label>
                                <input type="time" id="horaFin" name="hora_fin" class="form-control form-control-sm" value="18:00">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="resumen-card" id="resumenCard" style="display: none;">
                <div class="resumen-titulo">
                    <i class="fas fa-chart-bar me-2"></i>Resumen del Período Consultado
                </div>
                <div class="resumen-stats">
                    <div class="stat-item">
                        <div class="stat-value" id="totalItems">0</div>
                        <div class="stat-label">Total Items</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="totalBobinas">0</div>
                        <div class="stat-label">Total Bobinas</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="totalPesoBruto">0</div>
                        <div class="stat-label">Peso Bruto (kg)</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="totalPesoLiquido">0</div>
                        <div class="stat-label">Peso Líquido (kg)</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="eficienciaPeso">0%</div>
                        <div class="stat-label">Eficiencia Peso</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="totalOperadores">0</div>
                        <div class="stat-label">Operadores</div>
                    </div>
                </div>
            </div>


            <!-- Datos de Producción -->
            <div class="datos-card">
                <div class="datos-header">
                    <div class="datos-titulo">
                        <span>
                            <i class="fas fa-table me-2"></i>Datos de Producción
                        </span>
                        <span class="badge bg-primary" id="contadorRegistros">0 registros</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="tablaProduccion">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Producto</th>
                                <th>Tipo</th>
                                <th>Bobinas</th>
                                <th>Peso Bruto</th>
                                <th>Peso Líquido</th>
                                <th>Metragem</th>
                                <th>Operador</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="tablaBody">
                            <tr>
                                <td colspan="10" class="sin-datos">
                                    <i class="fas fa-search"></i>
                                    <h5>Listo para consultar</h5>
                                    <p>Selecciona los filtros y presiona "Consultar Datos" para ver la información</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="pagination-container" id="paginacionContainer" style="display: none;">
                    <div class="text-muted">
                        <small id="infoPaginacion">Mostrando 0 de 0 registros</small>
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="paginacion">
                            <!-- Se genera dinámicamente -->
                        </ul>
                    </nav>
                </div>
            </div>

        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h5 class="text-primary">Consultando datos...</h5>
            <p class="text-muted mb-0">Por favor espere mientras procesamos la información</p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Configuración JavaScript -->
    <script>
        const CONFIG = <?php echo json_encode($configuracionJS); ?>;
        console.log('🔧 Configuración cargada:', CONFIG);
    </script>

    <script>
        // Variables globales
        let paginaActual = 1;
        let datosActuales = null;

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            console.log('📊 Sistema de Consulta por Turnos iniciado');
            configurarEventos();
        });

        // Configurar eventos
        function configurarEventos() {
            // Formulario de filtros
            const form = document.getElementById('filtrosForm');
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                if (validarFormulario()) {
                    consultarDatos();
                }
            });

            // Eventos para campos de fecha
            const fechaInicio = document.getElementById('fechaInicio');
            const fechaFin = document.getElementById('fechaFin');

            fechaInicio.addEventListener('change', verificarFechasIguales);
            fechaFin.addEventListener('change', verificarFechasIguales);

            // Eventos para campos de hora
            const horaInicio = document.getElementById('horaInicio');
            const horaFin = document.getElementById('horaFin');

            horaInicio.addEventListener('change', validarHorarios);
            horaFin.addEventListener('change', validarHorarios);

            // Verificación inicial
            verificarFechasIguales();
        }

        // Verificar si las fechas son iguales para mostrar filtros de horario
        function verificarFechasIguales() {
            const fechaInicio = document.getElementById('fechaInicio').value;
            const fechaFin = document.getElementById('fechaFin').value;
            const horarioContainer = document.getElementById('horarioContainer');

            if (fechaInicio === fechaFin && fechaInicio !== '') {
                horarioContainer.classList.add('active');
                horarioContainer.style.display = 'block';
            } else {
                horarioContainer.classList.remove('active');
                horarioContainer.style.display = 'none';
            }
        }

        // Validar horarios
        function validarHorarios() {
            const horaInicio = document.getElementById('horaInicio').value;
            const horaFin = document.getElementById('horaFin').value;

            if (!horaInicio || !horaFin) return true;

            const [inicioH, inicioM] = horaInicio.split(':').map(Number);
            const [finH, finM] = horaFin.split(':').map(Number);

            const minutosInicio = inicioH * 60 + inicioM;
            const minutosFin = finH * 60 + finM;

            if (minutosInicio >= minutosFin) {
                mostrarError('La hora de inicio debe ser anterior a la hora de fin');
                document.getElementById('horaFin').focus();
                return false;
            }

            return true;
        }

        // Validar formulario
        function validarFormulario() {
            const fechaInicio = document.getElementById('fechaInicio').value;
            const fechaFin = document.getElementById('fechaFin').value;

            if (!fechaInicio || !fechaFin) {
                mostrarError('Debe seleccionar fechas de inicio y fin');
                return false;
            }

            if (new Date(fechaInicio) > new Date(fechaFin)) {
                mostrarError('La fecha de inicio no puede ser posterior a la fecha de fin');
                return false;
            }

            return validarHorarios();
        }

        // Consultar datos
        async function consultarDatos() {
            mostrarLoading(true);
            paginaActual = 1;

            try {
                const filtros = obtenerFiltros();
                const url = `?action=obtener_productos_paginados&pagina=1&${new URLSearchParams(filtros).toString()}`;

                const response = await fetch(url);
                const resultado = await response.json();

                if (resultado.success) {
                    datosActuales = resultado;
                    mostrarDatos(resultado.productos);
                    mostrarPaginacion(resultado.paginacion);
                    actualizarResumen();
                    await obtenerEstadisticas(filtros);
                } else {
                    mostrarError('Error al consultar los datos: ' + resultado.error);
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarError('Error de conexión al consultar los datos');
            } finally {
                mostrarLoading(false);
            }
        }

        // Obtener estadísticas
        async function obtenerEstadisticas(filtros) {
            try {
                const url = `?action=obtener_estadisticas_generales&${new URLSearchParams(filtros).toString()}`;
                const response = await fetch(url);
                const resultado = await response.json();

                if (resultado.success) {
                    actualizarEstadisticas(resultado.estadisticas);
                }
            } catch (error) {
                console.error('Error obteniendo estadísticas:', error);
            }
        }

        // Obtener filtros del formulario
        function obtenerFiltros() {
            const filtros = {
                fecha_inicio: document.getElementById('fechaInicio').value,
                fecha_fin: document.getElementById('fechaFin').value,
                operador: document.getElementById('operador').value,
                tipo_producto: document.getElementById('tipoProducto').value,
                estado: document.getElementById('estado').value
            };

            // Agregar horarios si están activos
            const horarioContainer = document.getElementById('horarioContainer');
            if (horarioContainer.style.display !== 'none') {
                const horaInicio = document.getElementById('horaInicio').value;
                const horaFin = document.getElementById('horaFin').value;
                if (horaInicio && horaFin) {
                    filtros.hora_inicio = horaInicio;
                    filtros.hora_fin = horaFin;
                }
            }

            return filtros;
        }

        // Mostrar datos en la tabla
        function mostrarDatos(productos) {
            const tbody = document.getElementById('tablaBody');

            if (!productos || productos.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="10" class="sin-datos">
                            <i class="fas fa-inbox"></i>
                            <h5>No se encontraron datos</h5>
                            <p>No hay registros que coincidan con los filtros seleccionados</p>
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            productos.forEach(producto => {
                const fecha = new Date(producto.fecha_hora_producida);
                const estadoClass = producto.estado === 'ACTIVO' ? 'success' : 'secondary';

                html += `
                    <tr>
                        <td><strong>${fecha.toLocaleDateString('es-PY')}</strong></td>
                        <td><code>${fecha.toLocaleTimeString('es-PY', {hour: '2-digit', minute: '2-digit'})}</code></td>
                        <td><strong>${producto.nombre_producto}</strong></td>
                        <td><span class="badge bg-info">${producto.tipo_producto}</span></td>
                        <td class="text-center"><strong>${producto.bobinas_pacote}</strong></td>
                        <td class="text-end">${parseFloat(producto.peso_bruto).toFixed(2)} kg</td>
                        <td class="text-end">${parseFloat(producto.peso_liquido).toFixed(2)} kg</td>
                        <td class="text-center">${producto.metragem || '-'}</td>
                        <td><small>${producto.usuario}</small></td>
                        <td><span class="badge bg-${estadoClass}">${producto.estado}</span></td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        // Mostrar paginación
        function mostrarPaginacion(paginacion) {
            const container = document.getElementById('paginacionContainer');
            const paginacionElement = document.getElementById('paginacion');
            const infoElement = document.getElementById('infoPaginacion');

            if (paginacion.total_paginas <= 1) {
                container.style.display = 'none';
                return;
            }

            container.style.display = 'flex';

            // Actualizar información
            const inicio = ((paginacion.pagina_actual - 1) * paginacion.por_pagina) + 1;
            const fin = Math.min(paginacion.pagina_actual * paginacion.por_pagina, paginacion.total);
            infoElement.textContent = `Mostrando ${inicio}-${fin} de ${paginacion.total} registros`;

            // Generar paginación
            let html = '';

            // Página anterior
            html += `
                <li class="page-item ${paginacion.pagina_actual === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="cambiarPagina(${paginacion.pagina_actual - 1}); return false;">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
            `;

            // Páginas
            for (let i = Math.max(1, paginacion.pagina_actual - 2); i <= Math.min(paginacion.total_paginas, paginacion.pagina_actual + 2); i++) {
                html += `
                    <li class="page-item ${i === paginacion.pagina_actual ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="cambiarPagina(${i}); return false;">${i}</a>
                    </li>
                `;
            }

            // Página siguiente
            html += `
                <li class="page-item ${paginacion.pagina_actual === paginacion.total_paginas ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="cambiarPagina(${paginacion.pagina_actual + 1}); return false;">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            `;

            paginacionElement.innerHTML = html;
        }

        // Cambiar página
        async function cambiarPagina(pagina) {
            if (pagina < 1) return;

            mostrarLoading(true);
            paginaActual = pagina;

            try {
                const filtros = obtenerFiltros();
                const url = `?action=obtener_productos_paginados&pagina=${pagina}&${new URLSearchParams(filtros).toString()}`;

                const response = await fetch(url);
                const resultado = await response.json();

                if (resultado.success) {
                    datosActuales = resultado;
                    mostrarDatos(resultado.productos);
                    mostrarPaginacion(resultado.paginacion);
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarError('Error al cambiar página');
            } finally {
                mostrarLoading(false);
            }
        }

        // Actualizar resumen
        function actualizarResumen() {
            const resumenCard = document.getElementById('resumenCard');
            const contadorRegistros = document.getElementById('contadorRegistros');

            if (datosActuales && datosActuales.paginacion) {
                resumenCard.style.display = 'block';
                contadorRegistros.textContent = `${datosActuales.paginacion.total} registros`;
            } else {
                resumenCard.style.display = 'none';
                contadorRegistros.textContent = '0 registros';
            }
        }


        function actualizarEstadisticas(stats) {
            // Items y bobinas
            document.getElementById('totalItems').textContent = stats.total_items.toLocaleString('es-PY');
            document.getElementById('totalBobinas').textContent = stats.total_bobinas.toLocaleString('es-PY');

            // Pesos separados (sin redondear)
            const pesoBruto = parseFloat(stats.total_peso_bruto || 0);
            const pesoLiquido = parseFloat(stats.total_peso_liquido || 0);

            document.getElementById('totalPesoBruto').textContent = pesoBruto.toLocaleString('es-PY', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            document.getElementById('totalPesoLiquido').textContent = pesoLiquido.toLocaleString('es-PY', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            // Calcular eficiencia de peso (peso líquido / peso bruto * 100)
            let eficiencia = 0;
            if (stats.total_peso_bruto > 0) {
                eficiencia = ((stats.total_peso_liquido / stats.total_peso_bruto) * 100);
            }
            document.getElementById('eficienciaPeso').textContent = eficiencia.toFixed(1) + '%';

            // Operadores
            document.getElementById('totalOperadores').textContent = stats.operadores_diferentes;

            // Cambiar color de eficiencia según el valor
            const eficienciaElement = document.getElementById('eficienciaPeso');
            if (eficiencia >= 95) {
                eficienciaElement.style.color = 'var(--america-success)';
            } else if (eficiencia >= 90) {
                eficienciaElement.style.color = 'var(--america-warning)';
            } else {
                eficienciaElement.style.color = 'var(--america-red)';
            }
        }

        // Limpiar filtros
        function limpiarFiltros() {
            document.getElementById('fechaInicio').value = new Date().toISOString().slice(0, 10);
            document.getElementById('fechaFin').value = new Date().toISOString().slice(0, 10);
            document.getElementById('operador').value = '';
            document.getElementById('tipoProducto').value = '';
            document.getElementById('estado').value = '';
            document.getElementById('horaInicio').value = '06:00';
            document.getElementById('horaFin').value = '18:00';

            // Limpiar tabla
            document.getElementById('tablaBody').innerHTML = `
                <tr>
                    <td colspan="10" class="sin-datos">
                        <i class="fas fa-search"></i>
                        <h5>Listo para consultar</h5>
                        <p>Selecciona los filtros y presiona "Consultar Datos" para ver la información</p>
                    </td>
                </tr>
            `;

            // Ocultar resumen
            document.getElementById('resumenCard').style.display = 'none';
            document.getElementById('paginacionContainer').style.display = 'none';
            document.getElementById('contadorRegistros').textContent = '0 registros';

            verificarFechasIguales();
        }

        // Exportar CSV
        // Función removida - exportación no disponible en esta vista

        // Funciones de utilidad
        function mostrarLoading(mostrar, mensaje = 'Consultando datos...') {
            const overlay = document.getElementById('loadingOverlay');
            const textoLoading = overlay.querySelector('h5');

            if (mostrar) {
                textoLoading.textContent = mensaje;
                overlay.style.display = 'flex';
            } else {
                overlay.style.display = 'none';
            }
        }

        function mostrarError(mensaje) {
            alert('❌ Error: ' + mensaje);
        }

        function mostrarExito(mensaje) {
            alert('✅ Éxito: ' + mensaje);
        }
    </script>

</body>

</html>