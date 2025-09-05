<?php
include "../../auth/verificar_sesion.php";
include "../../config/conexionBD.php";
include "repository/MateriaPrimaRepository.php";
include "controllers/MateriaPrimaController.php";
include "services/MateriaPrimaService.php";
include "repository/PesoEstimadoHistorialRepository.php";

requerirRol(['1', '2']);
requerirLogin();

// Crear instancia del controller
$materiaPrimaController = new MateriaPrimaController($conexion);

// Manejar toda la petición en el controller
$datosVista = $materiaPrimaController->manejarPeticion();

// Extraer datos para usar en la vista
extract($datosVista);
$breadcrumb_items = ['MATERIALES', 'CONFIGURAR MATERIALES'];
$item_urls = [
    $url_base . 'secciones/materiaprima/main.php',
];
$additional_css = [$url_base . 'secciones/materiaprima/utils/materia_prima.css'];
include $path_base . "components/head.php";
?>
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
                            <i class="fas fa-boxes me-2"></i>Configurar Materiales
                        </h4>
                        <p class="text-muted mb-0">Configurar y gestionar el inventario de materia prima e insumos</p>
                    </div>
                    <div class="quick-actions">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalMateriaPrima" onclick="prepararModalNuevo()">
                            <i class="fas fa-plus me-2"></i>Nuevo Material
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="toggleBusqueda()">
                            <i class="fas fa-search me-2"></i>Buscar
                        </button>
                    </div>
                </div>

                <!-- Mensajes -->
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-custom">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($mensaje): ?>
                    <div class="alert alert-success alert-custom">
                        <i class="fas fa-check-circle me-2"></i><?php echo $mensaje; ?>
                    </div>
                <?php endif; ?>

                <!-- Panel de búsqueda -->
                <div class="form-section" id="panelBusqueda" style="display: none;">
                    <div class="card border-info mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-search me-2"></i>Buscar Materia Prima
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group-custom">
                                            <label class="form-label-custom">Descripción</label>
                                            <input type="text" class="form-control form-control-custom"
                                                name="buscar_descripcion"
                                                value="<?php echo htmlspecialchars($filtros['buscar_descripcion'] ?? ''); ?>"
                                                placeholder="Buscar por descripción...">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group-custom">
                                            <label class="form-label-custom">Código NCM</label>
                                            <input type="text" class="form-control form-control-custom"
                                                name="buscar_ncm"
                                                value="<?php echo htmlspecialchars($filtros['buscar_ncm'] ?? ''); ?>"
                                                placeholder="Buscar por código NCM...">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group-custom">
                                            <label class="form-label-custom">Tipo</label>
                                            <select class="form-control form-control-custom" name="buscar_tipo">
                                                <option value="">Todos los tipos</option>
                                                <option value="Materia Prima" <?php echo ($filtros['buscar_tipo'] ?? '') === 'Materia Prima' ? 'selected' : ''; ?>>Materia Prima</option>
                                                <option value="Insumo" <?php echo ($filtros['buscar_tipo'] ?? '') === 'Insumo' ? 'selected' : ''; ?>>Insumo</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="btn-group-custom mt-2">
                                    <button type="submit" class="btn btn-info action-btn-compact">
                                        <i class="fas fa-search me-2"></i>Buscar
                                    </button>
                                    <a href="?" class="btn btn-outline-secondary action-btn-compact">
                                        <i class="fas fa-times me-2"></i>Limpiar
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Listado de materia prima -->
                <div class="ultimas-cajas">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-secondary mb-0">
                            <i class="fas fa-list me-2"></i>Inventario de Materiales (Configuración)
                        </h6>
                        <?php if (!empty($filtros['buscar_descripcion']) || !empty($filtros['buscar_ncm']) || !empty($filtros['buscar_tipo'])): ?>
                            <small class="text-info">
                                <i class="fas fa-filter me-1"></i>Resultados filtrados
                            </small>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($datosPaginacion['registros'])): ?>
                        <!-- Tabla actualizada sin peso_retirado y con unidad y cantidad -->
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Descripción</th>
                                        <th>Tipo</th>
                                        <th>NCM</th>
                                        <th>Peso Estimado</th>
                                        <th>Peso Registrado</th>
                                        <th>Unidad</th>
                                        <th>Cantidad</th>
                                        <th>Fecha Movimiento</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($datosPaginacion['registros'] as $registro): ?>
                                        <tr class="materia-prima-row" data-id="<?php echo $registro['id']; ?>">
                                            <td><strong class="text-primary">#<?php echo $registro['id']; ?></strong></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($registro['descripcion']); ?></strong>
                                            </td>
                                            <td>
                                                <?php if (!empty($registro['tipo'])): ?>
                                                    <span class="badge <?php echo $registro['tipo'] === 'Materia Prima' ? 'bg-primary' : 'bg-success'; ?>">
                                                        <i class="fas <?php echo $registro['tipo'] === 'Materia Prima' ? 'fa-industry' : 'fa-tools'; ?> me-1"></i>
                                                        <?php echo htmlspecialchars($registro['tipo']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-question me-1"></i>
                                                        Sin definir
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($registro['ncm'])): ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-barcode me-1"></i>
                                                        <?php echo htmlspecialchars($registro['ncm']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($registro['peso_estimado']) && $registro['peso_estimado'] > 0): ?>
                                                    <span class="badge bg-info">
                                                        <i class="fas fa-calculator me-1"></i>
                                                        <?php echo number_format((float)$registro['peso_estimado'], 2); ?> kg
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($registro['peso_registrado']) && $registro['peso_registrado'] > 0): ?>
                                                    <span class="badge bg-primary">
                                                        <i class="fas fa-plus me-1"></i>
                                                        <?php echo number_format((float)$registro['peso_registrado'], 2); ?> kg
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($registro['unidad'])): ?>
                                                    <span class="badge <?php echo $registro['unidad'] === 'Kilos' ? 'bg-warning' : 'bg-info'; ?>">
                                                        <i class="fas <?php echo $registro['unidad'] === 'Kilos' ? 'fa-weight-hanging' : 'fa-cube'; ?> me-1"></i>
                                                        <?php echo htmlspecialchars($registro['unidad']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($registro['cantidad']) && $registro['cantidad'] > 0): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-hashtag me-1"></i>
                                                        <?php echo number_format((int)$registro['cantidad']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($registro['fecha_movimiento_formateada'])): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo htmlspecialchars($registro['fecha_movimiento_formateada']); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-success btn-sm"
                                                        onclick="window.location.href='detalles.php?id_materia=<?php echo $registro['id']; ?>'"
                                                        title="Registrar Detalles">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                    <?php
                                                    if (tieneRol(['1'])) {
                                                    ?>
                                                        <button type="button" class="btn btn-warning btn-sm"
                                                            onclick="editarMateriaPrima(<?php echo htmlspecialchars(json_encode($registro)); ?>)"
                                                            title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-info btn-sm"
                                                            onclick="editarCampoSegunUnidad('<?php echo $registro['unidad']; ?>', <?php echo $registro['id']; ?>, '<?php echo htmlspecialchars($registro['descripcion']); ?>', <?php echo $registro['peso_estimado'] ?? 0; ?>, <?php echo $registro['cantidad'] ?? 0; ?>)"
                                                            title="<?php echo $registro['unidad'] === 'Kilos' ? 'Editar Peso Estimado' : 'Editar Cantidad'; ?>">
                                                            <i class="fas <?php echo $registro['unidad'] === 'Kilos' ? 'fa-weight-hanging' : 'fa-hashtag'; ?>"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm"
                                                            onclick="eliminarMateriaPrima(<?php echo $registro['id']; ?>, '<?php echo htmlspecialchars($registro['descripcion']); ?>')"
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
                                    <!-- Botón Anterior -->
                                    <?php if ($pagina_actual > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?php echo $pagina_actual - 1;
                                                                                echo $filtrosUrl; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    <!-- Números de página -->
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
                                    <!-- Botón Siguiente -->
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
                            <i class="fas fa-box-open fa-3x mb-3 d-block"></i>
                            <?php if (!empty($filtros['buscar_descripcion']) || !empty($filtros['buscar_ncm']) || !empty($filtros['buscar_tipo'])): ?>
                                <h5>No se encontraron registros</h5>
                                <p>No hay materia prima que coincida con los criterios de búsqueda.</p>
                                <a href="?" class="btn btn-primary">
                                    <i class="fas fa-times me-2"></i>Limpiar filtros
                                </a>
                            <?php else: ?>
                                <h5>No hay materia prima registrada</h5>
                                <p>¡Comience agregando su primera materia prima!</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalMateriaPrima" onclick="prepararModalNuevo()">
                                    <i class="fas fa-plus me-2"></i>Agregar Primera Materia Prima
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Materia Prima -->
    <div class="modal fade" id="modalMateriaPrima" tabindex="-1" aria-labelledby="modalMateriaPrimaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalMateriaPrimaLabel">
                        <i class="fas fa-plus-circle me-2"></i>
                        <span id="tituloModal">Registrar Nueva Materia Prima</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="formMateriaPrima">
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accionFormulario" value="crear">
                        <input type="hidden" name="id_editar" id="idEditar" value="">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-group-custom">
                                    <label class="form-label-custom">
                                        <i class="fas fa-tag text-primary me-1"></i>Descripción <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control form-control-custom"
                                        name="descripcion" id="descripcion"
                                        required maxlength="500"
                                        placeholder="Ingrese la descripción de la materia prima">
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="form-group-custom">
                                    <label class="form-label-custom">
                                        <i class="fas fa-list-alt text-warning me-1"></i>Tipo <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control form-control-custom" name="tipo" id="tipo" required>
                                        <option value="">Seleccione un tipo</option>
                                        <option value="Materia Prima">Materia Prima</option>
                                        <option value="Insumo">Insumo</option>
                                    </select>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle me-1"></i>Seleccione el tipo de material
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-group-custom">
                                    <label class="form-label-custom">
                                        <i class="fas fa-balance-scale text-success me-1"></i>Unidad <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control form-control-custom" name="unidad" id="unidad" required>
                                        <option value="">Seleccione una unidad</option>
                                        <option value="Unidad">Unidad</option>
                                        <option value="Kilos">Kilos</option>
                                    </select>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle me-1"></i>Unidad de medida del material
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-group-custom">
                                    <label class="form-label-custom">
                                        <i class="fas fa-barcode text-info me-1"></i>Código NCM
                                    </label>
                                    <input type="text" class="form-control form-control-custom"
                                        name="ncm" id="ncm"
                                        maxlength="100"
                                        pattern="[0-9\.]*"
                                        title="Solo números y puntos">
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle me-1"></i>Código NCM del producto (opcional)
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="limpiarFormulario()">
                            <i class="fas fa-broom me-2"></i>Limpiar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <span id="textoBotonGuardar">Registrar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Editar Peso Estimado (solo para unidad Kilos) -->
    <div class="modal fade" id="modalPesoEstimado" tabindex="-1" aria-labelledby="modalPesoEstimadoLabel" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="modalPesoEstimadoLabel">
                        <i class="fas fa-weight-hanging me-2"></i>
                        <span id="tituloPesoModal">Actualizar Peso Estimado</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="formPesoEstimado">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="actualizar_peso">
                        <input type="hidden" name="id_peso" id="idPeso" value="">

                        <div class="mb-3">
                            <label class="form-label-custom">
                                <i class="fas fa-tag text-primary me-1"></i>Material
                            </label>
                            <input type="text" class="form-control form-control-custom" id="descripcionPeso" disabled>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label-custom">
                                    <i class="fas fa-history text-secondary me-1"></i>Peso Actual
                                </label>
                                <input type="text" class="form-control form-control-custom" id="pesoActual" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-custom">
                                    <i class="fas fa-weight-hanging text-success me-1"></i>Nuevo Peso (kg) <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control form-control-custom"
                                    name="peso_estimado" id="nuevoPesoEstimado"
                                    min="0" max="999999.99" step="0.01"
                                    required placeholder="0.00">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-custom">
                                <i class="fas fa-comment text-warning me-1"></i>Motivo del Cambio <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-custom"
                                name="motivo_peso" id="motivoPeso"
                                maxlength="500" required
                                placeholder="Describa el motivo del cambio de peso...">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-save me-2"></i>Actualizar Peso
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- NUEVO Modal para Editar Cantidad (solo para unidad Unidad) -->
    <div class="modal fade" id="modalCantidad" tabindex="-1" aria-labelledby="modalCantidadLabel" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalCantidadLabel">
                        <i class="fas fa-hashtag me-2"></i>
                        <span id="tituloCantidadModal">Actualizar Cantidad</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="formCantidad">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="actualizar_cantidad">
                        <input type="hidden" name="id_cantidad" id="idCantidad" value="">

                        <div class="mb-3">
                            <label class="form-label-custom">
                                <i class="fas fa-tag text-primary me-1"></i>Material
                            </label>
                            <input type="text" class="form-control form-control-custom" id="descripcionCantidad" disabled>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label-custom">
                                    <i class="fas fa-history text-secondary me-1"></i>Cantidad Actual
                                </label>
                                <input type="text" class="form-control form-control-custom" id="cantidadActual" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-custom">
                                    <i class="fas fa-hashtag text-success me-1"></i>Nueva Cantidad <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control form-control-custom"
                                    name="cantidad" id="nuevaCantidad"
                                    min="0" max="999999" step="1"
                                    required placeholder="0">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-custom">
                                <i class="fas fa-comment text-warning me-1"></i>Motivo del Cambio <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-custom"
                                name="motivo_cantidad" id="motivoCantidad"
                                maxlength="500" required
                                placeholder="Describa el motivo del cambio de cantidad...">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Actualizar Cantidad
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formulario oculto para eliminación -->
    <form method="POST" action="" id="formEliminar" style="display: none;">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id_eliminar" id="idEliminar" value="">
    </form>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript personalizado -->
    <script>
        // Variables para los modales
        let modalMateriaPrima;
        let modalPesoEstimado;
        let modalCantidad; // NUEVO MODAL

        // Inicializar modales al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            modalMateriaPrima = new bootstrap.Modal(document.getElementById('modalMateriaPrima'));
            modalPesoEstimado = new bootstrap.Modal(document.getElementById('modalPesoEstimado'));
            modalCantidad = new bootstrap.Modal(document.getElementById('modalCantidad')); // NUEVO MODAL

            // Manejo de mensajes automáticos
            const alertSuccess = document.querySelector('.alert-success');
            const alertDanger = document.querySelector('.alert-danger');

            if (alertSuccess) {
                setTimeout(() => {
                    alertSuccess.style.opacity = '0';
                    setTimeout(() => alertSuccess.style.display = 'none', 300);
                }, 4000);
            }

            if (alertDanger) {
                setTimeout(() => {
                    alertDanger.style.opacity = '0';
                    setTimeout(() => alertDanger.style.display = 'none', 300);
                }, 6000);
            }

            // Eventos para modales
            document.getElementById('modalMateriaPrima').addEventListener('shown.bs.modal', function() {
                document.getElementById('descripcion').focus();
            });

            document.getElementById('modalMateriaPrima').addEventListener('hidden.bs.modal', function() {
                limpiarFormulario();
            });

            document.getElementById('modalPesoEstimado').addEventListener('shown.bs.modal', function() {
                document.getElementById('nuevoPesoEstimado').focus();
            });

            document.getElementById('modalPesoEstimado').addEventListener('hidden.bs.modal', function() {
                limpiarFormularioPeso();
            });

            // NUEVO: Eventos para modal de cantidad
            document.getElementById('modalCantidad').addEventListener('shown.bs.modal', function() {
                document.getElementById('nuevaCantidad').focus();
            });

            document.getElementById('modalCantidad').addEventListener('hidden.bs.modal', function() {
                limpiarFormularioCantidad();
            });

            // Formatear campo NCM al escribir
            document.getElementById('ncm').addEventListener('input', function(e) {
                // Permitir solo números y puntos
                let valor = e.target.value.replace(/[^0-9\.]/g, '');
                e.target.value = valor;
            });

            // Formatear NCM al salir del campo
            document.getElementById('ncm').addEventListener('blur', function(e) {
                let valor = e.target.value.replace(/[^0-9]/g, '');

                // Formatear como XXXX.XX.XX si tiene 8 dígitos
                if (valor.length === 8) {
                    e.target.value = valor.substring(0, 4) + '.' + valor.substring(4, 6) + '.' + valor.substring(6, 8);
                }
            });
        });

        // NUEVA FUNCIÓN: Determina qué modal abrir según la unidad
        function editarCampoSegunUnidad(unidad, id, descripcion, pesoEstimado, cantidad) {
            if (unidad === 'Kilos') {
                editarPesoEstimado(id, descripcion, pesoEstimado);
            } else if (unidad === 'Unidad') {
                editarCantidad(id, descripcion, cantidad);
            } else {
                alert('Unidad no reconocida: ' + unidad);
            }
        }

        // Función original para editar peso estimado (solo para Kilos)
        function editarPesoEstimado(id, descripcion, pesoActual) {
            document.getElementById('idPeso').value = id;
            document.getElementById('descripcionPeso').value = descripcion;
            document.getElementById('pesoActual').value = pesoActual + ' kg';
            document.getElementById('nuevoPesoEstimado').value = pesoActual;
            document.getElementById('tituloPesoModal').textContent = 'Actualizar Peso Estimado - #' + id;

            modalPesoEstimado.show();
        }

        // NUEVA FUNCIÓN: Editar cantidad (solo para Unidad)
        function editarCantidad(id, descripcion, cantidadActual) {
            document.getElementById('idCantidad').value = id;
            document.getElementById('descripcionCantidad').value = descripcion;
            document.getElementById('cantidadActual').value = cantidadActual + ' unidades';
            document.getElementById('nuevaCantidad').value = cantidadActual;
            document.getElementById('tituloCantidadModal').textContent = 'Actualizar Cantidad - #' + id;

            modalCantidad.show();
        }

        // Preparar modal para nuevo registro
        function prepararModalNuevo() {
            limpiarFormulario();
        }

        // Toggle panel de búsqueda
        function toggleBusqueda() {
            const panelBusqueda = document.getElementById('panelBusqueda');
            const isVisible = panelBusqueda.style.display !== 'none';

            if (isVisible) {
                panelBusqueda.style.display = 'none';
            } else {
                panelBusqueda.style.display = 'block';
            }
        }

        // Limpiar formulario
        function limpiarFormulario() {
            document.getElementById('formMateriaPrima').reset();
            document.getElementById('accionFormulario').value = 'crear';
            document.getElementById('idEditar').value = '';
            document.getElementById('tituloModal').textContent = 'Registrar Nueva Materia Prima';
            document.getElementById('textoBotonGuardar').textContent = 'Registrar';
        }

        // Limpiar formulario de peso
        function limpiarFormularioPeso() {
            document.getElementById('formPesoEstimado').reset();
            document.getElementById('idPeso').value = '';
            document.getElementById('descripcionPeso').value = '';
            document.getElementById('pesoActual').value = '';
        }

        // NUEVA FUNCIÓN: Limpiar formulario de cantidad
        function limpiarFormularioCantidad() {
            document.getElementById('formCantidad').reset();
            document.getElementById('idCantidad').value = '';
            document.getElementById('descripcionCantidad').value = '';
            document.getElementById('cantidadActual').value = '';
        }

        // Editar materia prima
        function editarMateriaPrima(registro) {
            // Cambiar a modo edición
            document.getElementById('accionFormulario').value = 'editar';
            document.getElementById('idEditar').value = registro.id;
            document.getElementById('tituloModal').textContent = 'Editar Materia Prima #' + registro.id;
            document.getElementById('textoBotonGuardar').textContent = 'Actualizar';

            // Llenar campos
            document.getElementById('descripcion').value = registro.descripcion || '';
            document.getElementById('tipo').value = registro.tipo || '';
            document.getElementById('ncm').value = registro.ncm || '';
            document.getElementById('unidad').value = registro.unidad || '';

            // Mostrar modal
            modalMateriaPrima.show();
        }

        // Eliminar materia prima
        function eliminarMateriaPrima(id, descripcion) {
            // Crear modal de confirmación personalizado
            const modalHtml = `
                <div class="modal fade" id="modalConfirmarEliminar" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Confirmar eliminación
                                </h5>
                            </div>
                            <div class="modal-body">
                                <div class="text-center">
                                    <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                                    <h6>¿Está seguro de que desea eliminar esta materia prima?</h6>
                                    <p class="mb-0"><strong>"${descripcion}"</strong></p>
                                    <small class="text-muted">Esta acción no se puede deshacer.</small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>Cancelar
                                </button>
                                <button type="button" class="btn btn-danger" onclick="confirmarEliminacion(${id})">
                                    <i class="fas fa-trash me-2"></i>Eliminar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Insertar modal en el DOM
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Mostrar modal
            const modalEliminar = new bootstrap.Modal(document.getElementById('modalConfirmarEliminar'));
            modalEliminar.show();

            // Eliminar modal del DOM cuando se cierre
            document.getElementById('modalConfirmarEliminar').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        // Confirmar eliminación
        function confirmarEliminacion(id) {
            document.getElementById('idEliminar').value = id;
            document.getElementById('formEliminar').submit();
        }

        // Validaciones del formulario principal
        document.getElementById('formMateriaPrima').addEventListener('submit', function(e) {
            const descripcion = document.getElementById('descripcion').value.trim();
            const tipo = document.getElementById('tipo').value.trim();
            const unidad = document.getElementById('unidad').value.trim();
            const ncm = document.getElementById('ncm').value.trim();

            let errores = [];

            // Validar descripción
            if (descripcion.length < 3) {
                errores.push('La descripción debe tener al menos 3 caracteres.');
            }

            // Validar tipo
            if (tipo === '') {
                errores.push('Debe seleccionar un tipo (Materia Prima o Insumo).');
            }

            // Validar unidad
            if (unidad === '') {
                errores.push('Debe seleccionar una unidad (Unidad o Kilos).');
            }

            // Validar NCM (opcional)
            if (ncm && ncm.length > 0) {
                // Verificar que solo contenga números y puntos
                if (!/^[0-9\.]+$/.test(ncm)) {
                    errores.push('El código NCM solo puede contener números y puntos.');
                }

                // Verificar longitud
                const ncmLimpio = ncm.replace(/\./g, '');
                if (ncmLimpio.length < 4 || ncmLimpio.length > 10) {
                    errores.push('El código NCM debe tener entre 4 y 10 dígitos.');
                }
            }

            if (errores.length > 0) {
                e.preventDefault();

                // Mostrar alerta personalizada
                const alertHtml = `
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Errores de validación:</strong>
                        <ul class="mb-0 mt-2">
                            ${errores.map(error => `<li>${error}</li>`).join('')}
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;

                const modalBody = document.querySelector('#modalMateriaPrima .modal-body');
                const existingAlert = modalBody.querySelector('.alert');
                if (existingAlert) {
                    existingAlert.remove();
                }

                modalBody.insertAdjacentHTML('afterbegin', alertHtml);

                document.getElementById('descripcion').focus();

                // Remover alerta después de 8 segundos
                setTimeout(() => {
                    const alert = modalBody.querySelector('.alert');
                    if (alert) {
                        alert.remove();
                    }
                }, 8000);

                return false;
            }
        });
    </script>
</body>

</html>