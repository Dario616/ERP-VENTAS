<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1']);

if (file_exists("controllers/configController.php")) {
    include "controllers/configController.php";
} else {
    die("Error: No se pudo cargar el controlador de configuración.");
}

$controller = new ConfigController($conexion, $url_base);

if ($controller->handleApiRequest()) {
    exit();
}

$mensajeError = '';
$mensaje = '';

if (isset($_GET['eliminar'])) {
    $resultado = $controller->procesarUnidadesMedida('eliminar', ['id' => $_GET['eliminar']]);

    if ($resultado['success']) {
        $mensaje = $resultado['mensaje'];
    } else {
        $mensajeError = $resultado['error'] ?? 'Error al eliminar la unidad de medida';
    }
}

$datosVista = $controller->obtenerDatosVista();
$unidades = $datosVista['unidades_medida'] ?? [];

$usuario_actual = $datosVista['usuario_actual'] ?? 'Usuario';
$configuracionJS = $controller->obtenerConfiguracionJS();
$breadcrumb_items = ['Configuracion', 'Unidad de Medida'];
$item_urls = [
    $url_base . 'secciones/configuracion/index.php',
];
$additional_css = [$url_base . 'secciones/configuracion/utils/styles.css'];
include $path_base . "components/head.php";
?>
<?php include $path_base . "components/navbar.php"; ?>
<div class="container-fluid my-4">
    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($mensajeError)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($mensajeError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['mensaje'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_GET['mensaje']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-ruler me-2"></i>Unidades de Medida</h4>
            <a href="<?php echo $url_base; ?>secciones/configuracion/um_registrar.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Nueva Unidad</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Descripción</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($unidades)): ?>
                            <?php foreach ($unidades as $unidad): ?>
                                <tr>
                                    <td><?= htmlspecialchars($unidad['id']) ?></td>
                                    <td><?= htmlspecialchars($unidad['desc']) ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="<?php echo $url_base; ?>secciones/configuracion/um_editar.php?id=<?= $unidad['id'] ?>" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i></a>
                                            <button onclick="ConfigApp.confirmarEliminarUnidadMedida(<?= $unidad['id'] ?>)" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-4">
                                    <i class="fas fa-inbox text-muted mb-2" style="font-size: 2rem;"></i>
                                    <div>No hay unidades de medida registradas</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmarEliminarModal" tabindex="-1" aria-labelledby="confirmarEliminarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="confirmarEliminarModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                ¿Está seguro que desea eliminar esta unidad de medida? Esta acción no se puede deshacer.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancelar
                </button>
                <button type="button" id="btn-confirmar-eliminar" class="btn btn-danger">
                    <i class="fas fa-trash me-1"></i> Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const CONFIG_JS = <?php echo json_encode($configuracionJS); ?>;
</script>
<script src="js/config.js"></script>
</body>

</html>