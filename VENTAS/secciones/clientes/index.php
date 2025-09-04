<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();

if (file_exists("controllers/ClienteController.php")) {
    include "controllers/ClienteController.php";
} else {
    die("Error: No se pudo cargar el controlador de clientes.");
}
$controller = new ClienteController($conexion, $url_base);
if ($controller->handleApiRequest()) {
    exit();
}

if (isset($_GET['eliminar'])) {
    if (!$controller->verificarPermisos('eliminar')) {
        $error = "No tienes permisos para eliminar clientes.";
    } else {
        $resultado = $controller->procesarEliminacion($_GET['eliminar']);
        if (isset($resultado['mensaje'])) {
            $mensaje = $resultado['mensaje'];
        } else {
            $error = $resultado['error'];
        }
        $controller->logActividad('Eliminar cliente', 'ID: ' . $_GET['eliminar']);
    }
}

$resultadoFiltros = $controller->procesarFiltros();
$datosVista = $controller->obtenerDatosVista();
$mensajes = $controller->manejarMensajes();

if (!empty($mensajes['mensaje'])) $mensaje = $mensajes['mensaje'];
if (!empty($mensajes['error'])) $error = $mensajes['error'];

$clientes = $resultadoFiltros['clientes'];
$filtrosAplicados = $resultadoFiltros['filtros_aplicados'];
$errorFiltros = $resultadoFiltros['error'];

$titulo = $datosVista['titulo'];
$url_base = $datosVista['url_base'];
$usuario_actual = $datosVista['usuario_actual'];
$es_admin = $datosVista['es_admin'];
$estadisticas = $datosVista['estadisticas'];
$configuracionJS = $controller->obtenerConfiguracionJS();

if (!empty($_GET)) {
    $filtrosStr = !empty($filtrosAplicados['nombre']) ? 'Filtro: ' . $filtrosAplicados['nombre'] : 'Sin filtros';
    $controller->logActividad('Consulta clientes', $filtrosStr);
}
$breadcrumb_items = ['Configuracion', 'Clientes'];
$item_urls = [
    $url_base . 'secciones/configuracion/index.php',
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titulo); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/clientes/utils/styles.css">
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <?php if (isset($mensaje)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorFiltros)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($errorFiltros); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-users me-2"></i>Gestión de Clientes</h4>
                <?php if ($controller->verificarPermisos('crear')): ?>
                    <a href="<?php echo $url_base; ?>secciones/clientes/registrar.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>Nuevo Cliente
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag me-1"></i>ID</th>
                                <th><i class="fas fa-user me-1"></i>Nombre</th>
                                <th><i class="fas fa-phone me-1"></i>Teléfono</th>
                                <th><i class="fab fa-whatsapp me-2"></i>WhatsApp</th>
                                <th><i class="fas fa-flag me-1"></i>País / Documento</th>
                                <th><i class="fas fa-cogs me-1"></i>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($clientes) > 0): ?>
                                <?php foreach ($clientes as $cliente): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cliente['id']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['telefono'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['nro'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($cliente['pais'] === 'BR'): ?>
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="https://flagcdn.com/w40/br.png" alt="Brasil" class="flag-img">
                                                    <div>
                                                        <small class="text-muted d-block">CNPJ</small>
                                                        <?php if (!empty($cliente['cnpj'])): ?>
                                                            <span class="document-field"><?php echo htmlspecialchars($cliente['cnpj']); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="https://flagcdn.com/w40/py.png" alt="Paraguay" class="flag-img">
                                                    <div>
                                                        <small class="text-muted d-block">RUC/CI</small>
                                                        <?php if (!empty($cliente['ruc'])): ?>
                                                            <span class="document-field"><?php echo htmlspecialchars($cliente['ruc']); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <?php if ($controller->verificarPermisos('ver', $cliente['id'])): ?>
                                                    <a href="<?php echo $url_base; ?>secciones/clientes/ver.php?id=<?php echo $cliente['id']; ?>" class="btn btn-info btn-sm" title="Ver Cliente">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($controller->verificarPermisos('editar', $cliente['id'])): ?>
                                                    <a href="<?php echo $url_base; ?>secciones/clientes/editar.php?id=<?php echo $cliente['id']; ?>" class="btn btn-warning btn-sm" title="Editar Cliente">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($controller->verificarPermisos('eliminar')): ?>
                                                    <a href="javascript:void(0);" onclick="confirmarEliminar(<?php echo $cliente['id']; ?>)" class="btn btn-danger btn-sm" title="Eliminar Cliente">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No hay clientes registrados</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmarEliminarModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">¿Está seguro que desea eliminar este cliente? Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <a href="#" id="btn-eliminar" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Eliminar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $url_base; ?>secciones/clientes/js/clientes.js"></script>
    <script>
        const CLIENTE_CONFIG = <?php echo json_encode($configuracionJS); ?>;
    </script>

</body>

</html>