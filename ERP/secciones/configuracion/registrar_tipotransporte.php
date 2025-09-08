<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();
require_once 'controllers/TipoTransporteController.php';
$tipoTransporteController = new TipoTransporteController($conexion);
$resultado = $tipoTransporteController->procesarRequest();
$mensaje = $resultado['mensaje'] ?? '';
$tipo_mensaje = $resultado['tipo_mensaje'] ?? '';
$tipos_existentes = $resultado['tipos_existentes'] ?? [];
function obtenerIconoTransporte($nombre)
{
    global $tipoTransporteController;
    return $tipoTransporteController->obtenerIconoTransporte($nombre);
}

function obtenerClaseIcono($nombre)
{
    global $tipoTransporteController;
    return $tipoTransporteController->obtenerClaseIcono($nombre);
}
$breadcrumb_items = ['CONFIGURACION', 'TIPOS DE TRANSPORTE'];
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
                                <h5><i class="fas fa-route"></i>Tipos de Transporte Registrados</h5>
                                <small class="text-muted">Lista de modalidades de transporte disponibles</small>
                            </div>
                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#modalRegistroTipo">
                                <i class="fas fa-plus me-2"></i>Nuevo Tipo
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($tipos_existentes)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-route"></i>
                                    <p>No hay tipos de transporte registrados en el sistema</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive mt-4">
                                    <table class="table table-hover d-none d-lg-table">
                                        <thead>
                                            <tr>
                                                <th><i class="fas fa-hashtag me-1"></i>ID</th>
                                                <th><i class="fas fa-route me-1"></i>Tipo de Transporte</th>
                                                <th><i class="fas fa-tools me-1"></i>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tipos_existentes as $tipo): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($tipo['id']); ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="transport-icon me-2 <?php echo obtenerClaseIcono($tipo['nombre']); ?>" style="width: 30px; height: 30px;">
                                                                <i class="fas fa-<?php echo obtenerIconoTransporte($tipo['nombre']); ?> text-white"></i>
                                                            </div>
                                                            <?php echo htmlspecialchars($tipo['nombre']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary btn-action me-1"
                                                            onclick="editarTipo(<?php echo $tipo['id']; ?>, '<?php echo htmlspecialchars($tipo['nombre']); ?>')"
                                                            title="Editar tipo">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger btn-action"
                                                            onclick="confirmarEliminar(<?php echo $tipo['id']; ?>, '<?php echo htmlspecialchars($tipo['nombre']); ?>')"
                                                            title="Eliminar tipo">
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
    <div class="modal fade" id="modalRegistroTipo" tabindex="-1" aria-labelledby="modalRegistroTipoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRegistroTipoLabel">
                        <i class="fas fa-plus me-2"></i>Nuevo Tipo de Transporte
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="formTipo">
                        <input type="hidden" name="accion" value="crear">

                        <div class="mb-3">
                            <label for="nombre" class="form-label">
                                <i class="fas fa-route me-1"></i>Nombre del Tipo de Transporte
                            </label>
                            <input type="text" class="form-control" id="nombre" name="nombre"
                                value="<?php echo htmlspecialchars($nombre ?? ''); ?>"
                                placeholder="Escribe..." required>
                            <div class="form-text">Ingrese la modalidad de transporte</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" form="formTipo" class="btn btn-info" id="btnRegistrar">
                        <i class="fas fa-save me-1"></i>Registrar Tipo
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalEditarTipo" tabindex="-1" aria-labelledby="modalEditarTipoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarTipoLabel">
                        <i class="fas fa-edit me-2"></i>Editar Tipo de Transporte
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="formEditarTipo">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" id="editId">

                        <div class="mb-3">
                            <label for="editNombre" class="form-label">
                                <i class="fas fa-route me-1"></i>Nombre del Tipo de Transporte
                            </label>
                            <input type="text" class="form-control" id="editNombre" name="nombre" required>
                            <div class="form-text">Modifique el nombre del tipo de transporte</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" form="formEditarTipo" class="btn btn-info">
                        <i class="fas fa-save me-1"></i>Actualizar Tipo
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalEliminarTipo" tabindex="-1" aria-labelledby="modalEliminarTipoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalEliminarTipoLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <i class="fas fa-route fa-3x text-danger mb-3"></i>
                        <h5>¿Está seguro que desea eliminar este tipo de transporte?</h5>
                        <p class="text-muted mb-0">Tipo: <strong id="eliminarNombreTipo"></strong></p>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Esta acción no se puede deshacer. Si hay registros asociados, no se podrá eliminar.
                        </div>
                    </div>
                    <form method="POST" action="" id="formEliminarTipo">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" id="eliminarId">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" form="formEliminarTipo" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Eliminar Tipo
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
                    <p><i class="fas fa-route me-1"></i>Gestión de Tipos de Transporte</p>
                </div>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/tipos-transporte.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mensaje = '<?php echo addslashes($mensaje ?? ''); ?>';
            const tipoMensaje = '<?php echo addslashes($tipo_mensaje ?? ''); ?>';
            handleMessageBehavior(mensaje, tipoMensaje);
        });
    </script>

</body>

</html>