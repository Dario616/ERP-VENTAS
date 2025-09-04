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
$filtros = $controller->procesarFiltros('devoluciones');
$pagina = $filtros['pagina'];
$registrosPorPagina = 10;

try {
    $resultado = $controller->obtenerDevolucionesPcp($filtros, $pagina, $registrosPorPagina);
    $devoluciones = $resultado['devoluciones'];
    $totalRegistros = $resultado['total_registros'];
    $totalPaginas = $resultado['total_paginas'];
    $paginaActual = $resultado['pagina_actual'];
    $error = '';
} catch (Exception $e) {
    $devoluciones = [];
    $totalRegistros = 0;
    $totalPaginas = 0;
    $paginaActual = 1;
    $error = "Error al obtener los datos: " . $e->getMessage();
}

// Obtener datos para la vista
$datosVista = $controller->obtenerDatosVista('devoluciones');
$mensajes = $controller->manejarMensajes();
$configuracionJS = $controller->obtenerConfiguracionJS();

// Fusionar mensajes
if (!empty($mensajes['mensaje'])) $mensaje = $mensajes['mensaje'];
if (!empty($mensajes['error'])) $errorMensaje = $mensajes['error'];

// Log de actividad
if (!empty($filtros)) {
    $filtrosStr = !empty($filtros['cliente']) ? 'Cliente: ' . $filtros['cliente'] : 'Consulta general';
    $controller->logActividad('Consulta devoluciones PCP', $filtrosStr);
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
    <style>
        .row-produccion {
            border-left: 4px solid #dc3545;
        }

        .row-expedicion {
            border-left: 4px solid #198754;
        }

        [class^="row-"] {
            border-left: 4px solid #0dcaf0;
        }

        .row-produccion:hover {
            background-color: rgba(220, 53, 69, 0.05) !important;
        }

        .row-expedicion:hover {
            background-color: rgba(25, 135, 84, 0.05) !important;
        }

        [class^="row-"]:hover {
            background-color: rgba(13, 202, 240, 0.05) !important;
        }

        .stats-card {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
    </style>
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
                            <i class="fas fa-undo me-1"></i>Devoluciones a PCP
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
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-undo me-2"></i>Devoluciones a PCP
                </h4>
            </div>

            <div class="card-body">
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

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
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
                                <label for="producto" class="form-label">Producto</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-box"></i></span>
                                    <input type="text" class="form-control" id="producto" name="producto"
                                        value="<?php echo htmlspecialchars($filtros['producto']); ?>"
                                        placeholder="Buscar por producto...">
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
                                    <a href="<?php echo $url_base; ?>secciones/sectorPcp/devuelto_pcp.php" class="btn btn-secondary">
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
                            Mostrando <?php echo count($devoluciones); ?> de <?php echo $totalRegistros; ?> devoluciones
                        </span>
                    </div>
                    <div>
                        <span class="text-muted">Página <?php echo $paginaActual; ?> de <?php echo $totalPaginas; ?></span>
                    </div>
                </div>

                <!-- Tabla de devoluciones -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th><i class="fas fa-hashtag me-1"></i></th>
                                <th><i class="fas fa-user me-1"></i>Cliente</th>
                                <th><i class="fas fa-box me-1"></i>Producto</th>
                                <th><i class="fas fa-balance-scale me-1"></i>Cantidad</th>
                                <th><i class="fas fa-calendar-day me-1"></i>Fecha Devolución</th>
                                <th><i class="fas fa-user-tag me-1"></i>Devuelto por</th>
                                <th><i class="fas fa-map-marker-alt me-1"></i>Origen</th>
                                <th>Motivo</th>
                                <th><i class="fas fa-comment me-1"></i>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($devoluciones)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i>No hay devoluciones registradas
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($devoluciones as $devolucion): ?>
                                    <tr class="row-<?php echo strtolower(str_replace('ó', 'o', $devolucion['origen'])); ?>">
                                        <td>
                                            <?php echo htmlspecialchars($devolucion['id_venta']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($devolucion['cliente']); ?></td>
                                        <td>
                                            <span class="d-block"><?php echo htmlspecialchars($devolucion['descripcion']); ?></span>
                                            <small class="text-muted">Código: <?php echo htmlspecialchars($devolucion['ncm'] ?: 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info text-dark">
                                                <?php echo number_format($devolucion['cantidad'], 2); ?>
                                                <?php echo htmlspecialchars($devolucion['unidadmedida'] ?: 'UN'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($devolucion['fecha_asignacion'])); ?></td>
                                        <td><?php echo htmlspecialchars($devolucion['nombre_usuario']); ?></td>
                                        <td>
                                            <?php
                                            $origenClass = 'secondary';
                                            $origenIcon = 'question-circle';
                                            $origenText = 'N/A';

                                            if (!empty($devolucion['origen'])) {
                                                $origenText = htmlspecialchars($devolucion['origen']);

                                                if ($devolucion['origen'] == 'Producción') {
                                                    $origenClass = 'danger';
                                                    $origenIcon = 'industry';
                                                } elseif ($devolucion['origen'] == 'Expedición') {
                                                    $origenClass = 'success';
                                                    $origenIcon = 'truck';
                                                } else {
                                                    $origenClass = 'info';
                                                    $origenIcon = 'tag';
                                                }
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $origenClass; ?>">
                                                <i class="fas fa-<?php echo $origenIcon; ?> me-1"></i>
                                                <?php echo $origenText; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($devolucion['observaciones_extra']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-success reasignar-venta"
                                                data-id-venta="<?php echo $devolucion['id_venta']; ?>"
                                                data-id-producto="<?php echo $devolucion['id_producto']; ?>">
                                                <i class="fas fa-undo me-1"></i>Reasignar a Aprobado
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($totalPaginas > 1): ?>
                    <nav aria-label="Paginación de devoluciones" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $paginaActual <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $paginaActual - 1; ?>&cliente=<?php echo urlencode($filtros['cliente']); ?>&producto=<?php echo urlencode($filtros['producto']); ?>&fecha_desde=<?php echo urlencode($filtros['fecha_desde']); ?>&fecha_hasta=<?php echo urlencode($filtros['fecha_hasta']); ?>">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </a>
                            </li>

                            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                <li class="page-item <?php echo $paginaActual == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?pagina=<?php echo $i; ?>&cliente=<?php echo urlencode($filtros['cliente']); ?>&producto=<?php echo urlencode($filtros['producto']); ?>&fecha_desde=<?php echo urlencode($filtros['fecha_desde']); ?>&fecha_hasta=<?php echo urlencode($filtros['fecha_hasta']); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?php echo $paginaActual >= $totalPaginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $paginaActual + 1; ?>&cliente=<?php echo urlencode($filtros['cliente']); ?>&producto=<?php echo urlencode($filtros['producto']); ?>&fecha_desde=<?php echo urlencode($filtros['fecha_desde']); ?>&fecha_hasta=<?php echo urlencode($filtros['fecha_hasta']); ?>">
                                    Siguiente <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal para ver detalles del motivo -->
    <div class="modal fade" id="modalMotivo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-comment-dots me-2"></i>Motivo de Devolución
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="textoMotivo"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
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

        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM cargado - buscando botones de reasignación...');

            const botones = document.querySelectorAll('.reasignar-venta');
            console.log('Botones encontrados:', botones.length);

            if (botones.length === 0) {
                console.warn('No se encontraron botones con clase .reasignar-venta');
                return;
            }

            botones.forEach((button, index) => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Botón clickeado!');

                    const idVenta = this.getAttribute('data-id-venta');
                    const idProducto = this.getAttribute('data-id-producto');

                    console.log('ID Venta:', idVenta);
                    console.log('ID Producto:', idProducto);

                    if (!idVenta) {
                        alert('Error: No se pudo obtener el ID de la venta');
                        return;
                    }

                    if (confirm('¿Está seguro de reasignar este producto al estado "Enviado a PCP"?')) {
                        console.log('Usuario confirmó la acción');

                        // Deshabilitar botón durante la petición
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Procesando...';

                        const formData = new FormData();
                        formData.append('id_venta', idVenta);
                        if (idProducto) {
                            formData.append('id_producto', idProducto); // ← NUEVO: enviar id_producto
                        }

                        console.log('Enviando petición AJAX...');

                        fetch('?action=reasignar_venta', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => {
                                console.log('Respuesta recibida:', response);
                                if (!response.ok) {
                                    throw new Error(`HTTP error! status: ${response.status}`);
                                }
                                return response.json();
                            })
                            .then(data => {
                                console.log('Datos recibidos:', data);

                                if (data.success) {
                                    alert(data.mensaje || 'Producto reasignado correctamente');
                                    console.log('Recargando página...');
                                    location.reload();
                                } else {
                                    alert('Error: ' + (data.error || 'Error desconocido'));
                                    console.error('Error del servidor:', data.error);
                                }
                            })
                            .catch(error => {
                                console.error('Error en fetch:', error);
                                alert('Error de conexión: ' + error.message);
                            })
                            .finally(() => {
                                // Rehabilitar botón
                                this.disabled = false;
                                this.innerHTML = '<i class="fas fa-undo me-1"></i>Reasignar a Aprobado';
                            });
                    } else {
                        console.log('Usuario canceló la acción');
                    }
                });
            });
        });
    </script>

</body>

</html>