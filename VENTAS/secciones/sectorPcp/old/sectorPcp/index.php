<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '4']); // Administrador y PCP

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
$filtros = $controller->procesarFiltros('ventas');
$pagina = $filtros['pagina'];
$registrosPorPagina = 10;

try {
    $resultado = $controller->obtenerVentasAprobadas($filtros, $pagina, $registrosPorPagina);
    $ventas = $resultado['ventas'];
    $totalRegistros = $resultado['total_registros'];
    $totalPaginas = $resultado['total_paginas'];
    $paginaActual = $resultado['pagina_actual'];
    $error = '';
} catch (Exception $e) {
    $ventas = [];
    $totalRegistros = 0;
    $totalPaginas = 0;
    $paginaActual = 1;
    $error = "Error al obtener los datos: " . $e->getMessage();
}

// Obtener datos para la vista
$datosVista = $controller->obtenerDatosVista('ventas_aprobadas');
$mensajes = $controller->manejarMensajes();
$configuracionJS = $controller->obtenerConfiguracionJS();

// Fusionar mensajes
if (!empty($mensajes['mensaje'])) $mensaje = $mensajes['mensaje'];
if (!empty($mensajes['error'])) $errorMensaje = $mensajes['error'];

// Log de actividad
if (!empty($filtros)) {
    $filtrosStr = !empty($filtros['cliente']) ? 'Cliente: ' . $filtros['cliente'] : 'Sin filtros específicos';
    $controller->logActividad('Consulta ventas aprobadas', $filtrosStr);
}

/**
 * Función para obtener símbolo de moneda
 */
function obtenerSimboloMoneda($moneda)
{
    switch ($moneda) {
        case 'Dólares':
            return 'USD';
        case 'Real brasileño':
            return 'R$';
        case 'Guaraníes':
        default:
            return '₲';
    }
}

/**
 * Función para formatear números
 */
function formatearNumero($numero, $decimales = 2)
{
    $formateado = number_format((float)$numero, $decimales, ',', '.');
    if ($decimales > 0) {
        $formateado = rtrim($formateado, '0');
        $formateado = rtrim($formateado, ',');
    }
    return $formateado;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($datosVista['titulo']); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Estilos personalizados -->
    <link href="<?php echo $url_base; ?>secciones/sectorPcp/utils/styles.css" rel="stylesheet">

</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $url_base; ?>index.php">
                <img src="<?php echo $url_base; ?>utils/logoa.png" alt="America TNT" height="20">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $url_base; ?>index.php">
                            <i class="fas fa-home me-1"></i>Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $url_base; ?>secciones/sectorPcp/main.php">
                            <i class="fas fa-industry me-1"></i>Gestión PCP
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="fas fa-check-circle me-1"></i>Ventas Aprobadas
                        </a>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($datosVista['usuario_actual']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo $url_base; ?>perfil.php"><i class="fas fa-id-card me-2"></i>Mi Perfil</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="<?php echo $url_base; ?>cerrar_sesion.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid my-4">
        <?php if (isset($mensaje)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($errorMensaje)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($errorMensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-check-circle me-2"></i>Ventas Aprobadas por Contabilidad
                </h4>
            </div>

            <div class="card-body">
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
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="cliente" name="cliente"
                                        value="<?php echo htmlspecialchars($filtros['cliente']); ?>"
                                        placeholder="Buscar por cliente...">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label for="vendedor" class="form-label">Vendedor</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                                    <input type="text" class="form-control" id="vendedor" name="vendedor"
                                        value="<?php echo htmlspecialchars($filtros['vendedor']); ?>"
                                        placeholder="Buscar por vendedor...">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label for="contador" class="form-label">Contador</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calculator"></i></span>
                                    <input type="text" class="form-control" id="contador" name="contador"
                                        value="<?php echo htmlspecialchars($filtros['contador']); ?>"
                                        placeholder="Buscar por contador...">
                                </div>
                            </div>

                            <div class="col-md-3 d-flex align-items-end">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filtrar
                                    </button>
                                    <a href="<?php echo $url_base; ?>secciones/sectorPcp/index.php" class="btn btn-secondary">
                                        <i class="fas fa-sync-alt me-1"></i>Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Información de resultados -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="text-muted">
                            Mostrando <?php echo count($ventas); ?> de <?php echo $totalRegistros; ?> ventas
                        </span>
                    </div>
                    <div>
                        <span class="text-muted">Página <?php echo $paginaActual; ?> de <?php echo $totalPaginas; ?></span>
                    </div>
                </div>

                <!-- Tabla de ventas aprobadas -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th><i class="fas fa-hashtag me-1"></i></th>
                                <th><i class="fas fa-user me-1"></i>Cliente</th>
                                <th><i class="fas fa-user-tie me-1"></i>Vendedor</th>
                                <th><i class="fas fa-calculator me-1"></i>Aprobado por</th>
                                <th><i class="fas fa-money-bill-wave me-1"></i>Monto Total</th>
                                <th><i class="fas fa-credit-card me-1"></i>Condición</th>
                                <th><i class="fas fa-truck me-1"></i>Flete</th>
                                <th><i class="fas fa-calendar-check me-1"></i>Fecha Aprobación</th>
                                <th class="text-center"><i class="fas fa-cogs me-1"></i>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ventas)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">
                                        <div class="alert alert-success mb-0">
                                            <i class="fas fa-check-circle me-2"></i>
                                            <?php if ($totalRegistros == 0): ?>
                                                ¡Excelente! No hay ventas pendientes de procesar. Todas las ventas aprobadas han sido completamente procesadas.
                                            <?php else: ?>
                                                No hay ventas que coincidan con los filtros aplicados.
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ventas as $venta): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo htmlspecialchars($venta['id']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo !empty($venta['cliente']) ? htmlspecialchars($venta['cliente']) : '<span class="text-muted">Sin cliente</span>'; ?></td>
                                        <td>
                                            <?php if (!empty($venta['nombre_vendedor'])): ?>
                                                <span class="badge bg-info text-dark">
                                                    <i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($venta['nombre_vendedor']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-question-circle me-1"></i>No asignado
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($venta['nombre_contador'])): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-user-check me-1"></i><?php echo htmlspecialchars($venta['nombre_contador']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    Directo
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong>
                                                <?php
                                                $simbolo = obtenerSimboloMoneda($venta['moneda']);
                                                echo $simbolo . ' ' . formatearNumero($venta['monto_total']);
                                                ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php if ($venta['es_credito']): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-credit-card me-1"></i>
                                                    <?php if (!empty($venta['tipocredito'])): ?>
                                                        Crédito <?php echo htmlspecialchars($venta['tipocredito']); ?>d
                                                    <?php else: ?>
                                                        Crédito
                                                    <?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-money-bill me-1"></i>Contado
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($venta['tipoflete'])): ?>
                                                <span class="badge bg-secondary">
                                                    <?php echo htmlspecialchars($venta['tipoflete']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($venta['fecha_aprobacion']) {
                                                echo '<i class="fas fa-clock me-1"></i>' . date('d/m/Y H:i', strtotime($venta['fecha_aprobacion']));
                                            } else {
                                                echo '<span class="text-muted">Directo</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <?php if ($controller->verificarPermisos('procesar', $venta['id'])): ?>
                                                    <a href="<?php echo $url_base; ?>secciones/sectorPcp/ver.php?id=<?php echo $venta['id']; ?>"
                                                        class="btn btn-primary btn-sm"
                                                        title="Procesar venta"
                                                        data-bs-toggle="tooltip">
                                                        <i class="fas fa-cog me-1"></i>Procesar
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <i class="fas fa-lock" title="Sin permisos"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($totalPaginas > 1): ?>
                    <nav aria-label="Paginación de ventas" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $paginaActual <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $paginaActual - 1; ?>&cliente=<?php echo urlencode($filtros['cliente']); ?>&vendedor=<?php echo urlencode($filtros['vendedor']); ?>&contador=<?php echo urlencode($filtros['contador']); ?>">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </a>
                            </li>

                            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                <li class="page-item <?php echo $paginaActual == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?pagina=<?php echo $i; ?>&cliente=<?php echo urlencode($filtros['cliente']); ?>&vendedor=<?php echo urlencode($filtros['vendedor']); ?>&contador=<?php echo urlencode($filtros['contador']); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?php echo $paginaActual >= $totalPaginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $paginaActual + 1; ?>&cliente=<?php echo urlencode($filtros['cliente']); ?>&vendedor=<?php echo urlencode($filtros['vendedor']); ?>&contador=<?php echo urlencode($filtros['contador']); ?>">
                                    Siguiente <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
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