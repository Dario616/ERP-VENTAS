<?php
include "../../auth/verificar_sesion.php";
include "../../config/conexionBD.php";

require_once __DIR__ . '/controllers/OrdenProduccionMaterialController.php';

requerirRol(['1', '2']);
requerirLogin();

$ordenController = new OrdenProduccionMaterialController($conexion);

// Manejar peticiones AJAX
if (isset($_POST['ajax']) && $_POST['ajax'] === 'true') {
    $ordenController->manejarAjax();
    exit();
}

// Manejar toda la petición en el controller
$datosVista = $ordenController->manejarPeticion();
extract($datosVista);
$breadcrumb_items = ['MATERIALES', 'ORDENES DE PRODUCCIÓN'];
$item_urls = [
    $url_base . 'secciones/materiaprima/main.php',
];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>America TNT - Órdenes de Producción Material</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="<?php echo $url_base; ?>utils/icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/materiaprima/utils/orden-produccion-material.css">
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <!-- Contenido Principal -->
    <div class="main-container">
        <div class="container-fluid">
            <div class="production-card">
                <!-- Header de la sección -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-1 text-primary">
                            <i class="fas fa-clipboard-list me-2"></i>Órdenes de Producción de Materiales
                        </h4>
                        <p class="text-muted mb-0">Gestión completa de órdenes de producción usando recetas de materias primas</p>
                    </div>
                    <div class="quick-actions">
                        <button type="button" class="btn btn-outline-info" onclick="toggleBusqueda()">
                            <i class="fas fa-search me-2"></i>Buscar
                        </button>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalOrden" onclick="prepararModalNuevo()">
                            <i class="fas fa-plus me-2"></i>Nueva Orden
                        </button>
                    </div>
                </div>

                <!-- Mostrar mensaje de éxito o error -->
                <?php if (!empty($mensaje)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Panel de búsqueda -->
                <div class="form-section" id="panelBusqueda" style="display: none;">
                    <div class="card border-info mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-search me-2"></i>Buscar Órdenes de Producción
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group-custom">
                                            <label class="form-label-custom">Materia Prima</label>
                                            <select class="form-control form-control-custom" name="id_materia_prima">
                                                <option value="">Todas las materias primas</option>
                                                <?php foreach ($materiasPrimasConRecetas as $materia): ?>
                                                    <option value="<?php echo $materia['id']; ?>"
                                                        <?php echo ($filtros['id_materia_prima'] == $materia['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($materia['descripcion']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group-custom">
                                            <label class="form-label-custom">Fecha Desde</label>
                                            <input type="date" class="form-control form-control-custom"
                                                name="fecha_desde" value="<?php echo htmlspecialchars($filtros['fecha_desde']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group-custom">
                                            <label class="form-label-custom">Fecha Hasta</label>
                                            <input type="date" class="form-control form-control-custom"
                                                name="fecha_hasta" value="<?php echo htmlspecialchars($filtros['fecha_hasta']); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-12">
                                        <div class="form-group-custom">
                                            <label class="form-label-custom">Buscar en Material</label>
                                            <input type="text" class="form-control form-control-custom"
                                                name="buscar_materia"
                                                value="<?php echo htmlspecialchars($filtros['buscar_materia']); ?>"
                                                placeholder="Buscar por nombre de materia prima...">
                                        </div>
                                    </div>
                                </div>
                                <div class="btn-group-custom mt-3">
                                    <button type="submit" class="btn btn-info action-btn-compact">
                                        <i class="fas fa-search me-2"></i>Buscar
                                    </button>
                                    <a href="orden-produccion-material.php" class="btn btn-outline-secondary action-btn-compact">
                                        <i class="fas fa-times me-2"></i>Limpiar
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Listado de órdenes -->
                <div class="ultimas-cajas">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-secondary mb-0">
                            <i class="fas fa-list me-2"></i>Órdenes de Producción
                            <?php if ($datosPaginacion['total_registros'] > 0): ?>
                                <span class="badge bg-primary ms-2"><?php echo $datosPaginacion['total_registros']; ?> órdenes</span>
                            <?php endif; ?>
                        </h6>

                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary" onclick="refrescarDatos()">
                                <i class="fas fa-sync-alt me-1"></i>Actualizar
                            </button>
                        </div>
                    </div>

                    <?php if (!empty($datosPaginacion['registros'])): ?>
                        <div class="table">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="8%">ID</th>
                                        <th width="30%">Material</th>
                                        <th width="15%">Cantidad</th>
                                        <th width="10%">Versión</th>
                                        <th width="15%">Fecha</th>
                                        <th width="22%">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($datosPaginacion['registros'] as $orden): ?>
                                        <tr>
                                            <td><strong class="text-primary">#<?php echo $orden['id']; ?></strong></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($orden['materia_prima_desc']); ?></strong>
                                                <br><small class="text-muted">Por: <?php echo htmlspecialchars($orden['usuario_creacion']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo number_format($orden['cantidad_solicitada'], 2); ?> <?php echo $orden['unidad_medida']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">V<?php echo $orden['version_receta']; ?></span>
                                            </td>
                                            <td><?php echo $orden['fecha_orden_formateada']; ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary"
                                                        onclick="verDetalleOrden(<?php echo $orden['id']; ?>)" title="Ver Detalle">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <a href="generar_pdf_orden_material.php?id_orden=<?php echo $orden['id']; ?>"
                                                        class="btn btn-outline-danger" target="_blank" title="PDF">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                    <?php
                                                    if (tieneRol(['1'])) {
                                                    ?>
                                                        <button type="button" class="btn btn-outline-danger"
                                                            onclick="eliminarOrden(<?php echo $orden['id']; ?>, '<?php echo htmlspecialchars($orden['materia_prima_desc'], ENT_QUOTES); ?>')"
                                                            title="Eliminar">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <?php if ($datosPaginacion['total_paginas'] > 1): ?>
                            <nav aria-label="Paginación" class="pagination-custom">
                                <ul class="pagination pagination-sm justify-content-center">
                                    <?php if ($pagina_actual > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?php echo $pagina_actual - 1;
                                                                                echo $filtrosUrl; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php
                                    $inicio = max(1, $pagina_actual - 2);
                                    $fin = min($datosPaginacion['total_paginas'], $pagina_actual + 2);

                                    for ($i = $inicio; $i <= $fin; $i++):
                                    ?>
                                        <li class="page-item <?php echo $i === $pagina_actual ? 'active' : ''; ?>">
                                            <a class="page-link" href="?pagina=<?php echo $i;
                                                                                echo $filtrosUrl; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($pagina_actual < $datosPaginacion['total_paginas']): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?php echo $pagina_actual + 1;
                                                                                echo $filtrosUrl; ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-clipboard-list fa-3x mb-3 d-block"></i>
                            <h5>No hay órdenes de producción</h5>
                            <p>¡Comience creando la primera orden de producción de material!</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalOrden" onclick="prepararModalNuevo()">
                                <i class="fas fa-plus me-2"></i>Crear Primera Orden
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nueva/Editar Orden -->
    <div class="modal fade" id="modalOrden" tabindex="-1" aria-labelledby="modalOrdenLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalOrdenLabel">
                        <i class="fas fa-plus-circle me-2"></i>
                        <span id="tituloModal">Nueva Orden de Producción</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="formOrden">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="crear">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-custom">
                                    <label class="form-label-custom">
                                        <i class="fas fa-box text-primary me-1"></i>Materia Prima <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control form-control-custom" name="id_materia_prima" id="id_materia_prima" required onchange="cargarVersionesReceta()">
                                        <option value="">Seleccionar materia prima</option>
                                        <?php foreach ($materiasPrimasConRecetas as $materia): ?>
                                            <option value="<?php echo $materia['id']; ?>">
                                                <?php echo htmlspecialchars($materia['descripcion']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-custom">
                                    <label class="form-label-custom">
                                        <i class="fas fa-layer-group text-success me-1"></i>Versión de Receta <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control form-control-custom" name="version_receta" id="version_receta" required disabled>
                                        <option value="">Primero seleccione materia prima</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-custom">
                                    <label class="form-label-custom">
                                        <i class="fas fa-weight text-info me-1"></i>Cantidad Solicitada <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control form-control-custom"
                                        name="cantidad_solicitada" id="cantidad_solicitada"
                                        step="0.001" min="0.001" placeholder="0.000" required
                                        oninput="calcularComponentes()">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-custom">
                                    <label class="form-label-custom">
                                        <i class="fas fa-ruler text-warning me-1"></i>Unidad de Medida <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control form-control-custom" name="unidad_medida" id="unidad_medida" required>
                                        <option value="UN" selected>UN</option>
                                        <option value="KG" selected>KG</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group-custom">
                                    <label class="form-label-custom">
                                        <i class="fas fa-calendar text-danger me-1"></i>Fecha de Orden <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control form-control-custom"
                                        name="fecha_orden" id="fecha_orden" required
                                        value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group-custom">
                                    <label class="form-label-custom">
                                        <i class="fas fa-sticky-note text-secondary me-1"></i>Observaciones
                                    </label>
                                    <textarea class="form-control form-control-custom" name="observaciones" id="observaciones"
                                        rows="3" placeholder="Observaciones adicionales..." maxlength="500"></textarea>
                                    <small class="form-text text-muted">Máximo 500 caracteres</small>
                                </div>
                            </div>
                        </div>

                        <!-- Previsualización de componentes -->
                        <div id="seccionComponentes" style="display: none;">
                            <hr>
                            <h6 class="text-secondary">
                                <i class="fas fa-list-ul me-2"></i>Componentes Necesarios para la Producción
                            </h6>
                            <div id="listaComponentes" class="table-responsive">
                                <!-- Se carga dinámicamente -->
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnGuardar">
                            <i class="fas fa-save me-2"></i>Guardar Orden
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Detalle Orden -->
    <div class="modal fade" id="modalDetalle" tabindex="-1" aria-labelledby="modalDetalleLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="modalDetalleLabel">
                        <i class="fas fa-eye me-2"></i>Detalle de Orden de Producción
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="loadingDetalle" class="text-center">
                        <div class="spinner-border text-info" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2">Cargando detalle...</p>
                    </div>
                    <div id="contenidoDetalle" style="display: none;">
                        <!-- El contenido se carga dinámicamente -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cerrar
                    </button>
                    <button type="button" class="btn btn-primary" id="btnDescargarPDF">
                        <i class="fas fa-file-pdf me-2"></i>Descargar PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Formularios ocultos -->
    <form method="POST" action="" id="formEliminar" style="display: none;">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id_eliminar" id="idEliminar" value="">
    </form>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Script de datos PHP para JavaScript -->
    <script>
        // Transferir datos PHP a JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar sistema con datos del servidor
            inicializarSistema(
                <?php echo json_encode($materiasPrimasConRecetas); ?>
            );
        });
    </script>

    <!-- JavaScript externo -->
    <script src="<?php echo $url_base; ?>secciones/materiaprima/js/orden-produccion-material.js"></script>
</body>

</html>