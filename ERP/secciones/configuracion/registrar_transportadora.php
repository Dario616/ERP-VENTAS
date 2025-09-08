<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();
require_once 'controllers/TransportadoraController.php';
$transportadoraController = new TransportadoraController($conexion);
$resultado = $transportadoraController->procesarRequest();
$mensaje = $resultado['mensaje'] ?? '';
$tipo_mensaje = $resultado['tipo_mensaje'] ?? '';
$transportadoras_existentes = $resultado['transportadoras_existentes'] ?? [];
function buscarTransportadoras($termino)
{
    global $transportadoraController;
    return $transportadoraController->buscarTransportadoras($termino);
}
function obtenerEstadisticasTransportadoras()
{
    global $transportadoraController;
    return $transportadoraController->obtenerEstadisticas();
}
$breadcrumb_items = ['CONFIGURACION', 'TRANSPORTADORAS'];
$item_urls = [
    $url_base . 'secciones/configuracion/index.php',
];
$additional_css = [$url_base . 'secciones/configuracion/utils/styles.css'];
include $path_base . "components/head.php";
?>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="main-container">
        <div class="container-fluid">
            <?php if (!empty($mensaje)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-<?php echo $tipo_mensaje === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show fade-in" role="alert">
                            <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                            <?php echo htmlspecialchars($mensaje); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5><i class="fas fa-truck"></i>Transportadoras Registradas</h5>
                                <small class="text-muted">Lista de empresas de transporte disponibles</small>
                            </div>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalRegistroTransportadora">
                                <i class="fas fa-plus me-2"></i>Nueva Transportadora
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($transportadoras_existentes)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-truck-loading"></i>
                                    <p>No hay transportadoras registradas en el sistema</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive mt-4">
                                    <table class="table table-hover d-none d-lg-table">
                                        <thead>
                                            <tr>
                                                <th><i class="fas fa-hashtag me-1"></i>ID</th>
                                                <th><i class="fas fa-truck me-1"></i>Descripción</th>
                                                <th><i class="fas fa-tools me-1"></i>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($transportadoras_existentes as $transportadora): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($transportadora['id']); ?></td>
                                                    <td>
                                                        <i class="fas fa-truck me-2 text-success"></i>
                                                        <?php echo htmlspecialchars($transportadora['descripcion']); ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary btn-action me-1"
                                                            onclick="editarTransportadora(<?php echo $transportadora['id']; ?>, '<?php echo htmlspecialchars($transportadora['descripcion']); ?>')"
                                                            title="Editar transportadora">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger btn-action"
                                                            onclick="confirmarEliminar(<?php echo $transportadora['id']; ?>, '<?php echo htmlspecialchars($transportadora['descripcion']); ?>')"
                                                            title="Eliminar transportadora">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalRegistroTransportadora" tabindex="-1" aria-labelledby="modalRegistroTransportadoraLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRegistroTransportadoraLabel">
                        <i class="fas fa-plus me-2"></i>Nueva Transportadora
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="formTransportadora">
                        <input type="hidden" name="accion" value="crear">

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">
                                <i class="fas fa-truck me-1"></i>Nombre de la Transportadora
                            </label>
                            <input type="text" class="form-control" id="descripcion" name="descripcion"
                                value="<?php echo htmlspecialchars($descripcion ?? ''); ?>"
                                placeholder="Escribe..." required>
                            <div class="form-text">Ingrese el nombre completo de la empresa de transporte</div>
                        </div>

                        <div class="mb-3">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Información:</strong> Esta transportadora estará disponible para asignar a envíos y expediciones.
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" form="formTransportadora" class="btn btn-success" id="btnRegistrar">
                        <i class="fas fa-save me-1"></i>Registrar Transportadora
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalEditarTransportadora" tabindex="-1" aria-labelledby="modalEditarTransportadoraLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarTransportadoraLabel">
                        <i class="fas fa-edit me-2"></i>Editar Transportadora
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="formEditarTransportadora">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" id="editId">

                        <div class="mb-3">
                            <label for="editDescripcion" class="form-label">
                                <i class="fas fa-truck me-1"></i>Nombre de la Transportadora
                            </label>
                            <input type="text" class="form-control" id="editDescripcion" name="descripcion" required>
                            <div class="form-text">Modifique el nombre de la empresa de transporte</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" form="formEditarTransportadora" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>Actualizar Transportadora
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalEliminarTransportadora" tabindex="-1" aria-labelledby="modalEliminarTransportadoraLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalEliminarTransportadoraLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <i class="fas fa-truck fa-3x text-danger mb-3"></i>
                        <h5>¿Está seguro que desea eliminar esta transportadora?</h5>
                        <p class="text-muted mb-0">Transportadora: <strong id="eliminarNombreTransportadora"></strong></p>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Esta acción no se puede deshacer. Si hay envíos asociados, no se podrá eliminar.
                        </div>
                    </div>
                    <form method="POST" action="" id="formEliminarTransportadora">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" id="eliminarId">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" form="formEliminarTransportadora" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Eliminar Transportadora
                    </button>
                </div>
            </div>
        </div>
    </div>
    <footer class="footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; <?php echo date('Y'); ?> Sistema de Producción America TNT. Todos los derechos reservados.</p>
                </div>
                <div class="col-md-6 text-end">
                    <p><i class="fas fa-truck me-1"></i>Gestión de Transportadoras</p>
                </div>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/transportadoras.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mensaje = '<?php echo addslashes($mensaje ?? ''); ?>';
            const tipoMensaje = '<?php echo addslashes($tipo_mensaje ?? ''); ?>';
            handleMessageBehavior(mensaje, tipoMensaje);
        });
    </script>

</body>

</html>