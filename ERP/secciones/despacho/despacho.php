<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '3']);

if (file_exists("controller/despachoController.php")) {
    include "controller/despachoController.php";
} else {
    die("Error: No se pudo cargar el controlador de despacho.");
}
$breadcrumb_items = ['DESPACHOS'];
$item_urls = [];
$additional_css = [$url_base . 'secciones/despacho/utils/despacho.css'];
include $path_base . "components/head.php";
?>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid">
        <?php if (!$estadoSistema['sistema_operativo']): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Sistema no operativo:</strong> <?php echo $estadoSistema['mensaje']; ?>
                        <br><small>Se requieren rejillas con asignaciones para crear expediciones.</small>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-header bg-success text-white">
                        <h5>
                            <i class="fas fa-robot me-2"></i>
                            Despachos
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($expedicionesAbiertas)): ?>
                            <div class="empty-state">
                                <i class="fas fa-robot"></i>
                                <h4>No hay expediciones abiertas</h4>
                            </div>
                        <?php else: ?>
                            <?php foreach ($expedicionesAbiertas as $exp): ?>
                                <div class="expedicion-item expedicion-validada">
                                    <div class="expedicion-header">
                                        <div>
                                            <h4 class="expedicion-numero"><?php echo htmlspecialchars($exp['numero_expedicion']); ?></h4>
                                            <div class="expedicion-info">
                                                Creada: <?php echo $exp['fecha_creacion_formateada']; ?>
                                                por <?php echo htmlspecialchars($exp['usuario_creacion']); ?>
                                                <?php if (isset($exp['tiempo_abierta'])): ?>
                                                    <br><small class="<?php echo $exp['requiere_atencion'] ? 'text-warning' : 'text-info'; ?>">
                                                        <i class="fas fa-clock me-1"></i>
                                                        Abierta: <?php echo $exp['tiempo_abierta']; ?>
                                                        <?php if ($exp['requiere_atencion']): ?>
                                                            <i class="fas fa-exclamation-triangle ms-1 text-warning" title="Expedición abierta por más de 2 horas"></i>
                                                        <?php endif; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="expedicion-stats">
                                            <span class="stat-badge">
                                                <i class="fas fa-box me-1"></i>
                                                <?php echo $exp['total_items'] ?? 0; ?> items
                                            </span>
                                            <span class="stat-badge">
                                                <i class="fas fa-weight me-1"></i>
                                                <?php echo $exp['peso_total_formateado']; ?> kg
                                            </span>
                                            <span class="stat-badge bg-success">
                                                <i class="fas fa-map-marker-alt me-1"></i>Rejilla #<?php echo $exp['numero_rejilla']; ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="expedicion-details">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <?php if ($exp['transportista']): ?>
                                                    <div class="detail-row">
                                                        <i class="fas fa-truck detail-icon"></i>
                                                        <strong>Transportista:</strong>&nbsp;<?php echo htmlspecialchars($exp['transportista']); ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($exp['conductor']): ?>
                                                    <div class="detail-row">
                                                        <i class="fas fa-user detail-icon"></i>
                                                        <strong>Conductor:</strong>&nbsp;<?php echo htmlspecialchars($exp['conductor']); ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($exp['tipovehiculo']): ?>
                                                    <div class="detail-row">
                                                        <i class="fas fa-truck detail-icon"></i>
                                                        <strong>Tipo Vehículo:</strong>&nbsp;<?php echo htmlspecialchars($exp['tipovehiculo']); ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($exp['placa_vehiculo']): ?>
                                                    <div class="detail-row">
                                                        <i class="fas fa-car detail-icon"></i>
                                                        <strong>Placa:</strong>&nbsp;<?php echo htmlspecialchars($exp['placa_vehiculo']); ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($exp['peso']): ?>
                                                    <div class="detail-row">
                                                        <i class="fas fa-weight detail-icon"></i>
                                                        <strong>Peso Total Productos:</strong>&nbsp;<?php echo htmlspecialchars($exp['peso']); ?> kg
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($exp['destino']): ?>
                                                    <div class="detail-row">
                                                        <i class="fas fa-map-marker-alt detail-icon"></i>
                                                        <strong>Destino:</strong>&nbsp;<?php echo htmlspecialchars($exp['destino']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="col-md-2 ms-auto">
                                                <div class="d-flex flex-column btn-group-actions">
                                                    <button class="btn btn-success btn-sm mb-2"
                                                        onclick="abrirScanner('<?php echo htmlspecialchars($exp['numero_expedicion'], ENT_QUOTES); ?>')">
                                                        <i class="fas fa-robot me-2"></i>Scanner Automático
                                                    </button>

                                                    <?php if (($exp['total_items'] ?? 0) > 0): ?>
                                                        <button class="btn btn-warning btn-sm mb-2"
                                                            onclick="abrirResumenPDF('<?php echo htmlspecialchars($exp['numero_expedicion'], ENT_QUOTES); ?>')">
                                                            <i class="fas fa-file-pdf me-2"></i>Resumen PDF
                                                        </button>

                                                        <button class="btn btn-primary btn-sm"
                                                            onclick="despacharExpedicion('<?php echo htmlspecialchars($exp['numero_expedicion'], ENT_QUOTES); ?>')">
                                                            <i class="fas fa-shipping-fast me-2"></i>Despachar
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-danger btn-sm"
                                                            onclick="eliminarExpedicion('<?php echo htmlspecialchars($exp['numero_expedicion'], ENT_QUOTES); ?>')">
                                                            <i class="fas fa-trash me-2"></i>Eliminar
                                                        </button>

                                                        <button class="btn btn-outline-secondary btn-sm mt-2" disabled>
                                                            <i class="fas fa-ban me-2"></i>Sin Items
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($estadoSistema['sistema_operativo']): ?>
        <button class="floating-action-btn-text" data-bs-toggle="modal" data-bs-target="#modalNuevaExpedicion"
            title="Nueva Expedición Solo Automática">
            <i class="fas fa-robot"></i>
            <span>Nuevo Despacho</span>
        </button>
    <?php endif; ?>

    <div class="modal fade" id="modalNuevaExpedicion" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-robot me-2"></i>Crear Nueva Expedición
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formNuevaExpedicion">
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3">
                                    <i class="fas fa-info-circle me-2"></i>Información de la Expedición
                                </h6>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-truck me-1"></i>Transportista
                                        <span class="required-field">*</span>
                                    </label>
                                    <select class="form-select validacion-obligatoria" name="transportista" required>
                                        <option value="">Seleccione transportista...</option>
                                        <?php foreach ($transportistas as $transportista): ?>
                                            <option value="<?php echo htmlspecialchars($transportista); ?>">
                                                <?php echo htmlspecialchars($transportista); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-user me-1"></i>Conductor
                                    </label>
                                    <input type="text" class="form-control" name="conductor"
                                        placeholder="Nombre del conductor">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-truck me-1"></i>Tipo de Vehículo
                                    </label>
                                    <select class="form-select" name="tipovehiculo">
                                        <option value="">Seleccione tipo de vehículo...</option>
                                        <?php foreach ($tiposVehiculo as $tipo): ?>
                                            <option value="<?php echo htmlspecialchars($tipo); ?>">
                                                <?php echo htmlspecialchars($tipo); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-car me-1"></i>Placa del Vehículo
                                    </label>
                                    <input type="text" class="form-control" name="placa"
                                        placeholder="ABC-123">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-weight me-1"></i>Peso Total Productos (kg)
                                    </label>
                                    <input type="number" class="form-control" name="peso" step="0.1"
                                        placeholder="0.0">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-map-marker-alt me-1"></i>Destino
                                    </label>
                                    <input type="text" class="form-control" name="destino"
                                        placeholder="Ciudad de destino">
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3 text-success">
                                    <i class="fas fa-robot me-2"></i>Selección de Rejilla
                                </h6>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-warehouse me-1"></i>Rejilla para Validación
                                        <span class="required-field">*</span>
                                        <span class="badge badge-validacion ms-2">OBLIGATORIA</span>
                                    </label>
                                    <select class="form-select form-select-lg validacion-obligatoria"
                                        id="selectRejilla" name="id_rejilla" required onchange="cargarItemsRejilla()">
                                        <option value="">Seleccione rejilla OBLIGATORIAMENTE...</option>
                                        <?php foreach ($rejillasInfo as $rejilla): ?>
                                            <option value="<?php echo $rejilla['id']; ?>"
                                                data-peso="<?php echo $rejilla['peso_actual']; ?>"
                                                data-items="<?php echo $rejilla['total_asignaciones']; ?>">
                                                Rejilla #<?php echo $rejilla['numero_rejilla']; ?> -
                                                <?php echo $rejilla['peso_actual_formateado']; ?> kg -
                                                <?php echo $rejilla['total_asignaciones']; ?> asignaciones
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row" id="contenedorItemsRejilla" style="display: none;">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3">
                                    <i class="fas fa-boxes me-2"></i>Asignaciones en esta Rejilla
                                </h6>
                                <div class="table-responsive" style="max-height: 300px;">
                                    <table class="table table-sm table-hover">
                                        <thead class="table-success sticky-top">
                                            <tr>
                                                <th>Producto</th>
                                                <th class="text-center">Peso Asignado</th>
                                                <th class="text-center">Cantidad</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tablaItemsRejilla">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formNuevaExpedicion" class="btn btn-success" id="btnCrearExpedicion" disabled>
                        <i class="fas fa-robot me-2"></i>Crear Despacho
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEscanear" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-robot me-2"></i><span class="text-warning">Interfaz de Despacho</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="text-center">
                                <div class="alert alert-warning alert-desconocidos" id="alertaDesconocidos" style="display: none;">
                                </div>
                                <div class="row mt-4">
                                    <div class="col-6">
                                        <div class="card bg-success text-white">
                                            <div class="card-body text-center py-1">
                                                <div class="h5 mb-1" id="totalItemsEscaneados">0</div>
                                                <small>Items Validados</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="card bg-primary text-white">
                                            <div class="card-body text-center py-1">
                                                <div class="h5 mb-1" id="pesoTotalEscaneado">0</div>
                                                <small>Peso Bruto Total (kg)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mt-3">
                                    <div class="card-header bg-light py-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0">
                                                <i class="fas fa-list-alt me-1"></i>
                                                Items Escaneados - Gestión Rápida
                                                <span class="badge bg-primary ms-1" id="contadorItemsEscaneados">0</span>
                                            </h6>

                                            <div class="input-group input-group-sm" style="width: 250px;">
                                                <input type="text" class="form-control" placeholder="Buscar por # Eiqueta..."
                                                    onkeyup="filtrarItemsEscaneados(this.value)" id="buscarItems">
                                                <span class="input-group-text">
                                                    <i class="fas fa-search"></i>
                                                </span>
                                            </div>

                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#collapseItemsEscaneados"
                                                aria-expanded="true">
                                                <i class="fas fa-chevron-up" id="iconoColapsoItems"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="collapse show" id="collapseItemsEscaneados">
                                        <div class="card-body p-2">
                                            <div id="listaItemsEscaneados">
                                                <div class="text-center text-muted p-4">
                                                    <i class="fas fa-box-open fa-2x mb-2"></i>
                                                    <p>No hay items escaneados aún</p>
                                                </div>
                                            </div>

                                            <div class="border-top pt-2 mt-2">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <button type="button" class="btn btn-outline-primary btn-sm w-100"
                                                            onclick="cargarItemsEscaneadosDetallados(expedicionActiva)">
                                                            <i class="fas fa-sync-alt"></i> Actualizar Lista
                                                        </button>
                                                    </div>
                                                    <div class="col-6">
                                                        <button type="button" class="btn btn-outline-secondary btn-sm w-100"
                                                            onclick="limpiarSeleccionItems()">
                                                            <i class="fas fa-square"></i> Limpiar Selección
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">
                                    <i class="fas fa-users me-2"></i>Items por Cliente
                                </h6>
                                <div>
                                    <button class="btn btn-outline-success btn-sm me-2" onclick="actualizarListaItems()">
                                        <i class="fas fa-sync-alt me-1"></i>Actualizar
                                    </button>
                                </div>
                            </div>

                            <div id="contenedorItemsClientes" class="border border-light rounded p-3 bg-light" style="max-height: 70vh; overflow-y: auto;">
                                <div class="text-center text-muted p-3">
                                    <i class="fas fa-clipboard-list me-2"></i>No hay items escaneados aún
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalConfirmarDespacho" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-shipping-fast me-2"></i>Confirmar Despacho
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-4">
                        <i class="fas fa-robot text-success" style="font-size: 4rem;"></i>
                    </div>

                    <h4 class="mb-3">¿Despachar la expedición? </h4>

                    <div class="alert alert-primary mb-4">
                        <h5 class="mb-2">
                            <i class="fas fa-barcode me-2"></i>
                            <span id="expedicionDespacho">-</span>
                        </h5>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-primary px-4" id="btnConfirmarDespacho">
                        <i class="fas fa-shipping-fast me-2"></i>Despachar
                    </button>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const DESPACHO_CONFIG = {
            urlBase: "<?php echo $url_base; ?>",
            usuario: "<?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?>",
            debug: <?php echo isset($_GET['debug']) ? 'true' : 'false'; ?>,
            arquitectura: "<?php echo $configuracion['arquitectura']; ?>",
            version: "<?php echo $configuracion['version_sistema']; ?>",
            validacionRejillaObligatoria: true,
            soloModoAutomatico: true,
            logicaDesconocidos: true
        };

        document.addEventListener('DOMContentLoaded', function() {
            const selectRejilla = document.getElementById('selectRejilla');
            const selectTransportista = document.querySelector('[name="transportista"]');

            function actualizarEstilosValidacion() {
                if (selectRejilla && selectRejilla.value) {
                    selectRejilla.classList.remove('validacion-obligatoria');
                    selectRejilla.classList.add('rejilla-seleccionada');
                } else {
                    selectRejilla.classList.remove('rejilla-seleccionada');
                    selectRejilla.classList.add('validacion-obligatoria');
                }

                if (selectTransportista && selectTransportista.value) {
                    selectTransportista.classList.remove('validacion-obligatoria');
                    selectTransportista.classList.add('validacion-exitosa');
                } else {
                    selectTransportista.classList.remove('validacion-exitosa');
                    selectTransportista.classList.add('validacion-obligatoria');
                }
            }

            if (selectRejilla) {
                selectRejilla.addEventListener('change', actualizarEstilosValidacion);
            }

            if (selectTransportista) {
                selectTransportista.addEventListener('change', actualizarEstilosValidacion);
            }
            actualizarEstilosValidacion();
        });
    </script>

    <script src="js/despacho-core.js"></script>
    <script src="js/despacho-expediciones.js"></script>
    <script src="js/despacho-scanner.js"></script>
    <script src="js/despacho-clientes.js"></script>
</body>