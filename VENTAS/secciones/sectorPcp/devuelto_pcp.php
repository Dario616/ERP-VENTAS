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
$breadcrumb_items = ['Gestion PCP', 'Devoluciones a PCP'];
$item_urls = [
    $url_base . 'secciones/sectorPcp/main.php',
];
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
    <?php include $path_base . "components/navbar.php"; ?>
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
                <!-- Tabla de devoluciones -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th><i class="fas fa-hashtag me-1"></i></th>
                                <th><i class="fas fa-user me-1"></i>Cliente</th>
                                <th><i class="fas fa-box me-1"></i>Producto</th>
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
                                    <td colspan="8" class="text-center">
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i>No hay devoluciones registradas
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($devoluciones as $devolucion): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($devolucion['id_venta']); ?></td>
                                        <td><?php echo htmlspecialchars($devolucion['cliente']); ?></td>
                                        <td>
                                            <span class="badge bg-info text-dark">
                                                <?php echo $devolucion['productos_devueltos']; ?> productos
                                            </span>
                                            <small class="d-block text-muted">
                                                <?php echo htmlspecialchars($devolucion['productos_descripcion']); ?>
                                            </small>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($devolucion['fecha_asignacion'])); ?></td>
                                        <td><?php echo htmlspecialchars($devolucion['nombre_usuario']); ?></td>
                                        <td>Producción</td>
                                        <td><?php echo htmlspecialchars($devolucion['observaciones_combinadas']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-success reasignar-venta"
                                                data-id-venta="<?php echo $devolucion['id_venta']; ?>">
                                                <i class="fas fa-undo me-1"></i>Reasignar Venta
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

    <div class="modal fade" id="modalReasignar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Reasignación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-undo-alt text-warning" style="font-size: 4rem;"></i>
                    </div>
                    <h6 class="mb-3">¿Está seguro de reasignar esta venta?</h6>
                    <div class="alert alert-info">
                        <strong>Venta ID: <span id="ventaIdModal"></span></strong><br>
                        <small>Esto devolverá TODA la venta al estado "Enviado a PCP" y eliminará todos los registros de producción y expedición.</small>
                    </div>
                    <p class="text-muted mb-0">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-warning" id="confirmarReasignar">
                        <i class="fas fa-check me-1"></i>Sí, Reasignar
                    </button>
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
            const modalReasignar = new bootstrap.Modal(document.getElementById('modalReasignar'));
            const ventaIdModal = document.getElementById('ventaIdModal');
            const confirmarBtn = document.getElementById('confirmarReasignar');

            let ventaActual = null;
            let botonActual = null;

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
                    console.log('ID Venta:', idVenta);

                    if (!idVenta) {
                        alert('Error: No se pudo obtener el ID de la venta');
                        return;
                    }

                    // Configurar modal
                    ventaActual = idVenta;
                    botonActual = this;
                    ventaIdModal.textContent = idVenta;

                    // Mostrar modal
                    modalReasignar.show();
                });
            });

            // Manejar confirmación del modal
            confirmarBtn.addEventListener('click', function() {
                if (!ventaActual || !botonActual) return;

                console.log('Usuario confirmó la acción');

                // Cerrar modal
                modalReasignar.hide();

                // Deshabilitar botón durante la petición
                botonActual.disabled = true;
                botonActual.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Procesando...';

                const formData = new FormData();
                formData.append('id_venta', ventaActual);

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
                            // Mostrar notificación de éxito
                            const alertSuccess = document.createElement('div');
                            alertSuccess.className = 'alert alert-success alert-dismissible fade show position-fixed';
                            alertSuccess.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                            alertSuccess.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i>${data.mensaje || 'Venta reasignada correctamente'}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                            document.body.appendChild(alertSuccess);

                            // Recargar página después de 2 segundos
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        } else {
                            // Mostrar error
                            const alertError = document.createElement('div');
                            alertError.className = 'alert alert-danger alert-dismissible fade show position-fixed';
                            alertError.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                            alertError.innerHTML = `
                        <i class="fas fa-exclamation-circle me-2"></i>Error: ${data.error || 'Error desconocido'}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                            document.body.appendChild(alertError);
                            console.error('Error del servidor:', data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error en fetch:', error);
                        const alertError = document.createElement('div');
                        alertError.className = 'alert alert-danger alert-dismissible fade show position-fixed';
                        alertError.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                        alertError.innerHTML = `
                    <i class="fas fa-exclamation-circle me-2"></i>Error de conexión: ${error.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                        document.body.appendChild(alertError);
                    })
                    .finally(() => {
                        // Rehabilitar botón
                        if (botonActual) {
                            botonActual.disabled = false;
                            botonActual.innerHTML = '<i class="fas fa-undo me-1"></i>Reasignar Venta';
                        }

                        // Limpiar variables
                        ventaActual = null;
                        botonActual = null;
                    });
            });

            // Limpiar variables al cerrar modal
            document.getElementById('modalReasignar').addEventListener('hidden.bs.modal', function() {
                ventaActual = null;
                botonActual = null;
            });
        });
    </script>

</body>

</html>