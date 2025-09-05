<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '3']);

date_default_timezone_set('America/Asuncion');

if (file_exists("controller/historialController.php")) {
    include "controller/historialController.php";
} else {
    die("Error: No se pudo cargar el controlador de historial.");
}

$filtros = [
    'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
    'fecha_fin' => $_GET['fecha_fin'] ?? '',
    'cliente' => $_GET['cliente'] ?? '',
    'estado' => $_GET['estado'] ?? '',
    'rejilla' => $_GET['rejilla'] ?? '',
    'usuario' => $_GET['usuario'] ?? '',
    'producto' => $_GET['producto'] ?? ''
];

$filtros = array_filter($filtros, function ($value) {
    return !empty($value);
});

$resultadosPorPagina = 20;
$paginaActual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($paginaActual < 1) {
    $paginaActual = 1;
}

try {
    $datosVista = $historialController->obtenerDatosVistaHistorial($filtros, $paginaActual, $resultadosPorPagina);
    $historialAsignaciones = $datosVista['historial_asignaciones'];
    $estadisticasGenerales = $datosVista['estadisticas_generales'];
    $clientesDisponibles = $datosVista['clientes_disponibles'];
    $rejillasDisponibles = $datosVista['rejillas_disponibles'];
    $usuariosDisponibles = $datosVista['usuarios_disponibles'];
    $totalRegistros = $datosVista['total_registros'];
    $totalPaginas = $datosVista['total_paginas'];
    $paginaActual = $datosVista['pagina_actual'];
    $filtrosAplicados = $datosVista['filtros_aplicados'];
} catch (Exception $e) {
    error_log("Error fatal obteniendo datos de vista historial: " . $e->getMessage());
    $historialAsignaciones = [];
    $estadisticasGenerales = [];
    $clientesDisponibles = [];
    $rejillasDisponibles = [];
    $usuariosDisponibles = [];
    $totalRegistros = 0;
    $totalPaginas = 0;
    $paginaActual = 1;
    $filtrosAplicados = [];
}
$configuracion = $historialController->obtenerConfiguracion();
$breadcrumb_items = ['Historial'];
$item_urls = [];
$additional_css = [$url_base . 'secciones/expedicion/utils/historial.css'];
include $path_base . "components/head.php";
?>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="main-container">
        <div class="container-fluid">
            <div class="header-section">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h3 class="header-title">
                            <i class="fas fa-history"></i>
                            Historial de Expedición
                        </h3>
                        <p class="header-description">
                            Registro completo de todas las asignaciones a rejillas
                            <?php if (count($filtrosAplicados) > 0): ?>
                                - <?php echo count($filtrosAplicados); ?> filtro(s) aplicado(s)
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-lg-4 text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalFiltros">
                                <i class="fas fa-filter me-2"></i>Filtros
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <?php if (empty($historialAsignaciones)): ?>
                        <div class="no-items">
                            <i class="fas fa-search fa-4x text-muted"></i>
                            <h5>No se encontraron registros</h5>
                            <p class="text-muted mb-0">
                                <?php if (count($filtrosAplicados) > 0): ?>
                                    No hay asignaciones que coincidan con los filtros aplicados
                                <?php else: ?>
                                    No hay historial de asignaciones registradas
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($historialAsignaciones as $asignacion): ?>
                            <div class="historial-item" onclick="verDetalleAsignacion(<?php echo $asignacion['id']; ?>)">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="estado-badge <?php echo $asignacion['estado_clase']; ?>">
                                                <i class="<?php echo $asignacion['estado_icono']; ?>"></i>
                                                <?php echo $asignacion['estado_texto']; ?>
                                            </span>
                                        </div>
                                        <div class="small text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo $asignacion['fecha_asignacion_formateada']; ?>
                                        </div>
                                        <div class="small">
                                            <span class="tiempo-badge <?php echo $asignacion['tiempo_clase']; ?>">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo $asignacion['tiempo_texto']; ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="fw-bold text-primary mb-1">
                                            <i class="fas fa-building me-1"></i>
                                            <?php echo htmlspecialchars($asignacion['cliente_unificado']); ?>
                                        </div>
                                        <div class="small mb-1">
                                            <i class="fas fa-box me-1"></i>
                                            <?php echo htmlspecialchars($asignacion['producto_unificado']); ?>
                                        </div>
                                        <?php if (!empty($asignacion['tipo_producto_badge'])): ?>
                                            <span class="tipo-badge <?php echo $asignacion['tipo_producto_badge']['clase']; ?>">
                                                <?php echo $asignacion['tipo_producto_badge']['texto']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="small mb-1">
                                            <i class="fas fa-th-large me-1"></i>
                                            Rejilla: <strong><?php echo $asignacion['numero_rejilla']; ?></strong>
                                        </div>
                                        <div class="small mb-1">
                                            <i class="fas fa-weight me-1"></i>
                                            <?php echo $asignacion['peso_asignado_formateado']; ?> kg
                                        </div>
                                        <div class="small">
                                            <i class="fas fa-cubes me-1"></i>
                                            <?php echo $asignacion['cantidad_unidades_formateada']; ?> <?php echo $asignacion['tipo_unidad_texto']; ?>
                                        </div>
                                    </div>

                                    <div class="col-md-2 text-end">
                                        <div class="small text-muted mb-1">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($asignacion['usuario_asignacion']); ?>
                                        </div>

                                        <?php if (isset($asignacion['porcentaje_despachado']) && $asignacion['porcentaje_despachado'] > 0): ?>
                                            <div class="small">
                                                <i class="fas fa-truck me-1"></i>
                                                <?php echo $asignacion['porcentaje_despachado']; ?>% despachado
                                            </div>
                                        <?php endif; ?>

                                        <div class="mt-2">
                                            <i class="fas fa-chevron-right text-primary"></i>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($asignacion['observaciones_procesadas'])): ?>
                                    <div class="row mt-2">
                                        <div class="col-12">
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach (array_slice($asignacion['observaciones_procesadas'], 0, 2) as $obs): ?>
                                                    <span class="observaciones-badge <?php echo $obs['clase']; ?>">
                                                        <i class="<?php echo $obs['icono']; ?> me-1"></i>
                                                        <?php echo htmlspecialchars(substr($obs['texto'], 0, 50)); ?>
                                                        <?php if (strlen($obs['texto']) > 50): ?>...<?php endif; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                                <?php if (count($asignacion['observaciones_procesadas']) > 2): ?>
                                                    <span class="observaciones-badge text-muted">
                                                        +<?php echo count($asignacion['observaciones_procesadas']) - 2; ?> más
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($totalPaginas > 1): ?>
                        <nav aria-label="Navegación de páginas" class="mt-4 d-flex justify-content-center">
                            <ul class="pagination">
                                <?php
                                $parametrosUrl = array_merge($_GET, ['pagina' => max(1, $paginaActual - 1)]);
                                ?>
                                <li class="page-item <?php echo ($paginaActual <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query($parametrosUrl); ?>">Anterior</a>
                                </li>

                                <?php
                                $inicio = max(1, $paginaActual - 2);
                                $fin = min($totalPaginas, $paginaActual + 2);
                                ?>

                                <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                                    <?php $parametrosUrl = array_merge($_GET, ['pagina' => $i]); ?>
                                    <li class="page-item <?php echo ($paginaActual == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query($parametrosUrl); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php
                                $parametrosUrl = array_merge($_GET, ['pagina' => min($totalPaginas, $paginaActual + 1)]);
                                ?>
                                <li class="page-item <?php echo ($paginaActual >= $totalPaginas) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query($parametrosUrl); ?>">Siguiente</a>
                                </li>
                            </ul>
                        </nav>

                        <div class="text-center text-muted small">
                            Mostrando registros <?php echo (($paginaActual - 1) * $resultadosPorPagina) + 1; ?> -
                            <?php echo min($totalRegistros, $paginaActual * $resultadosPorPagina); ?> de
                            <?php echo number_format($totalRegistros, 0); ?> total
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalFiltros" tabindex="-1" aria-labelledby="modalFiltrosLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFiltrosLabel">
                        <i class="fas fa-filter me-2"></i>Filtros de Búsqueda
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="GET" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                                <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio"
                                    value="<?php echo $filtrosAplicados['fecha_inicio'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="fecha_fin" class="form-label">Fecha Fin</label>
                                <input type="date" class="form-control" id="fecha_fin" name="fecha_fin"
                                    value="<?php echo $filtrosAplicados['fecha_fin'] ?? ''; ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cliente" class="form-label">Cliente</label>
                                <select class="form-select" id="cliente" name="cliente">
                                    <option value="">Todos los clientes</option>
                                    <?php foreach ($clientesDisponibles as $cliente): ?>
                                        <option value="<?php echo htmlspecialchars($cliente['cliente']); ?>"
                                            <?php echo (isset($filtrosAplicados['cliente']) && $filtrosAplicados['cliente'] == $cliente['cliente']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cliente['cliente']); ?> (<?php echo $cliente['total_asignaciones']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="estado" class="form-label">Estado</label>
                                <select class="form-select" id="estado" name="estado">
                                    <option value="">Todos los estados</option>
                                    <option value="activa" <?php echo (isset($filtrosAplicados['estado']) && $filtrosAplicados['estado'] == 'activa') ? 'selected' : ''; ?>>Activa</option>
                                    <option value="completada" <?php echo (isset($filtrosAplicados['estado']) && $filtrosAplicados['estado'] == 'completada') ? 'selected' : ''; ?>>Completada</option>
                                    <option value="cancelada" <?php echo (isset($filtrosAplicados['estado']) && $filtrosAplicados['estado'] == 'cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="rejilla" class="form-label">Rejilla</label>
                                <select class="form-select" id="rejilla" name="rejilla">
                                    <option value="">Todas las rejillas</option>
                                    <?php foreach ($rejillasDisponibles as $rejilla): ?>
                                        <option value="<?php echo htmlspecialchars($rejilla['numero_rejilla']); ?>"
                                            <?php echo (isset($filtrosAplicados['rejilla']) && $filtrosAplicados['rejilla'] == $rejilla['numero_rejilla']) ? 'selected' : ''; ?>>
                                            Rejilla <?php echo htmlspecialchars($rejilla['numero_rejilla']); ?> (<?php echo $rejilla['total_asignaciones']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="usuario" class="form-label">Usuario</label>
                                <select class="form-select" id="usuario" name="usuario">
                                    <option value="">Todos los usuarios</option>
                                    <?php foreach ($usuariosDisponibles as $usuario): ?>
                                        <option value="<?php echo htmlspecialchars($usuario['usuario']); ?>"
                                            <?php echo (isset($filtrosAplicados['usuario']) && $filtrosAplicados['usuario'] == $usuario['usuario']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($usuario['usuario']); ?> (<?php echo $usuario['total_asignaciones']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="producto" class="form-label">Producto</label>
                            <input type="text" class="form-control" id="producto" name="producto"
                                placeholder="Buscar por nombre de producto..."
                                value="<?php echo htmlspecialchars($filtrosAplicados['producto'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="historial.php" class="btn btn-outline-secondary">Limpiar Filtros</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Aplicar Filtros
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalDetalleAsignacion" tabindex="-1" aria-labelledby="modalDetalleAsignacionLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetalleAsignacionLabel">
                        <i class="fas fa-info-circle me-2"></i>Detalle de Asignación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="loading-spinner" id="loadingDetalle">
                        <i class="fas fa-spinner fa-spin fa-3x"></i>
                        <p class="mt-3 mb-0">Cargando detalle de asignación...</p>
                    </div>
                    <div id="contenidoDetalle" style="display: none;">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const HISTORIAL_CONFIG = {
            urlBase: "<?php echo $url_base; ?>",
            usuario: "<?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?>",
            version: "<?php echo $configuracion['version_historial'] ?? '1.0'; ?>"
        };

        function verDetalleAsignacion(idAsignacion) {
            const modal = new bootstrap.Modal(document.getElementById('modalDetalleAsignacion'));
            modal.show();
            document.getElementById('loadingDetalle').style.display = 'block';
            document.getElementById('contenidoDetalle').style.display = 'none';
            const formData = new FormData();
            formData.append('accion', 'obtener_detalle_asignacion');
            formData.append('id_asignacion', idAsignacion);

            fetch('historial.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loadingDetalle').style.display = 'none';
                    document.getElementById('contenidoDetalle').style.display = 'block';

                    if (data.success) {
                        mostrarDetalleAsignacion(data.detalle, data.historial_estados);
                    } else {
                        document.getElementById('contenidoDetalle').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error al cargar el detalle: ${data.error || 'Error desconocido'}
                        </div>
                    `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('loadingDetalle').style.display = 'none';
                    document.getElementById('contenidoDetalle').style.display = 'block';
                    document.getElementById('contenidoDetalle').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error de conexión al cargar el detalle
                    </div>
                `;
                });
        }

        function mostrarDetalleAsignacion(detalle, historialEstados) {
            const html = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3"><i class="fas fa-info-circle me-2"></i>Información General</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>ID Asignación:</strong></td>
                                <td>${detalle.id}</td>
                            </tr>
                            <tr>
                                <td><strong>Estado:</strong></td>
                                <td>
                                    <span class="estado-badge ${detalle.estado_clase}">
                                        <i class="${detalle.estado_icono}"></i>
                                        ${detalle.estado_texto}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Fecha Asignación:</strong></td>
                                <td>${detalle.fecha_asignacion_formateada}</td>
                            </tr>
                            <tr>
                                <td><strong>Usuario:</strong></td>
                                <td>${detalle.usuario_asignacion}</td>
                            </tr>
                            <tr>
                                <td><strong>Cliente:</strong></td>
                                <td>${detalle.cliente_unificado}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3"><i class="fas fa-box me-2"></i>Información del Producto</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Producto:</strong></td>
                                <td>${detalle.producto_unificado}</td>
                            </tr>
                            <tr>
                                <td><strong>Peso Asignado:</strong></td>
                                <td>${detalle.peso_asignado_formateado} kg</td>
                            </tr>
                            <tr>
                                <td><strong>Cantidad:</strong></td>
                                <td>${detalle.cantidad_unidades_formateada} ${detalle.tipo_unidad_texto}</td>
                            </tr>
                            <tr>
                                <td><strong>Peso Unitario:</strong></td>
                                <td>${detalle.peso_unitario_formateado} kg</td>
                            </tr>
                            <tr>
                                <td><strong>Rejilla:</strong></td>
                                <td>Rejilla ${detalle.numero_rejilla}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                ${detalle.observaciones ? `
                <div class="mt-3">
                    <h6 class="text-primary mb-2"><i class="fas fa-sticky-note me-2"></i>Observaciones</h6>
                    <div class="bg-light p-3 rounded">
                        ${detalle.observaciones.replace(/\n/g, '<br>')}
                    </div>
                </div>
                ` : ''}

                ${historialEstados && historialEstados.length > 0 ? `
                <div class="mt-3">
                    <h6 class="text-primary mb-2"><i class="fas fa-history me-2"></i>Historial de Estados</h6>
                    <div class="timeline">
                        ${historialEstados.map(estado => `
                            <div class="timeline-item">
                                <div class="timeline-marker bg-${estado.tipo_cambio === 'creacion' ? 'primary' : 
                                    estado.tipo_cambio === 'completado' ? 'success' : 
                                    estado.tipo_cambio === 'cancelacion' ? 'danger' : 'info'}"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">${estado.estado_nuevo}</h6>
                                    <p class="mb-1 text-muted small">${estado.observaciones}</p>
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i>${estado.usuario}
                                        <i class="fas fa-calendar ms-3 me-1"></i>${estado.fecha}
                                    </small>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
            `;

            document.getElementById('contenidoDetalle').innerHTML = html;
        }

        function exportarHistorial() {
            const filtros = new URLSearchParams(window.location.search);

            const formData = new FormData();
            formData.append('accion', 'exportar_historial');

            for (const [key, value] of filtros) {
                if (key !== 'pagina') {
                    formData.append(key, value);
                }
            }

            fetch('historial.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const csv = convertirACSV(data.datos);
                        descargarCSV(csv, `historial_expedicion_${new Date().toISOString().split('T')[0]}.csv`);
                        mostrarNotificacion('Historial exportado exitosamente', 'success');
                    } else {
                        mostrarNotificacion('Error al exportar: ' + (data.error || 'Error desconocido'), 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarNotificacion('Error de conexión al exportar', 'danger');
                });
        }

        function convertirACSV(datos) {
            if (!datos || datos.length === 0) return '';

            const headers = Object.keys(datos[0]);
            const csvContent = [
                headers.join(','),
                ...datos.map(row => headers.map(header => `"${row[header] || ''}"`).join(','))
            ].join('\n');

            return csvContent;
        }

        function descargarCSV(csvContent, filename) {
            const blob = new Blob([csvContent], {
                type: 'text/csv;charset=utf-8;'
            });
            const link = document.createElement('a');

            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }

        function mostrarNotificacion(mensaje, tipo) {
            const alertas = document.createElement('div');
            alertas.className = `alert alert-${tipo} alert-dismissible fade show position-fixed`;
            alertas.style.cssText = 'top: 100px; right: 20px; z-index: 9999; min-width: 300px;';
            alertas.innerHTML = `
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(alertas);

            setTimeout(() => {
                if (alertas.parentNode) {
                    alertas.parentNode.removeChild(alertas);
                }
            }, 5000);
        }
        document.addEventListener('DOMContentLoaded', function() {
            const fechaFin = document.getElementById('fecha_fin');
            const fechaInicio = document.getElementById('fecha_inicio');

            if (!fechaFin.value) {
                fechaFin.value = new Date().toISOString().split('T')[0];
            }

            if (!fechaInicio.value) {
                const fechaInicioDefault = new Date();
                fechaInicioDefault.setMonth(fechaInicioDefault.getMonth() - 1);
                fechaInicio.value = fechaInicioDefault.toISOString().split('T')[0];
            }
        });
    </script>
</body>
