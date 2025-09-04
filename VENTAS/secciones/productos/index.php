<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();
if (file_exists("controllers/ProductoController.php")) {
    include "controllers/ProductoController.php";
} else {
    die("Error: No se pudo cargar el controlador de productos.");
}
$controller = new ProductoController($conexion, $url_base);
if ($controller->handleApiRequest()) {
    exit();
}
if (isset($_GET['eliminar'])) {
    if (!$controller->verificarPermisos('eliminar')) {
        $error = "No tienes permisos para eliminar productos.";
    } else {
        $resultado = $controller->procesarEliminacion($_GET['eliminar']);
        if (isset($resultado['mensaje'])) {
            $mensaje = $resultado['mensaje'];
        } else {
            $error = $resultado['error'];
        }
        $controller->logActividad('Eliminar producto', 'ID: ' . $_GET['eliminar']);
    }
}
$resultadoFiltros = $controller->procesarFiltros();
$datosVista = $controller->obtenerDatosVista();
$mensajes = $controller->manejarMensajes();

if (!empty($mensajes['mensaje'])) $mensaje = $mensajes['mensaje'];
if (!empty($mensajes['error'])) $error = $mensajes['error'];

$productos = $resultadoFiltros['productos'];
$paginacion = $resultadoFiltros['paginacion'];
$filtrosAplicados = $resultadoFiltros['filtros_aplicados'];
$errorFiltros = $resultadoFiltros['error'];

$tipos_disponibles = $controller->obtenerTiposUnicos();

$titulo = $datosVista['titulo'];
$url_base = $datosVista['url_base'];
$usuario_actual = $datosVista['usuario_actual'];
$es_admin = $datosVista['es_admin'];
$configuracionJS = $controller->obtenerConfiguracionJS();

if (!empty($_GET)) {
    $filtrosStr = !empty($filtrosAplicados['descripcion']) ? 'Filtro: ' . $filtrosAplicados['descripcion'] : 'Sin filtros';
    $controller->logActividad('Consulta productos', $filtrosStr);
}
$breadcrumb_items = ['Configuracion', 'Producto'];
$item_urls = [
    $url_base . 'secciones/configuracion/index.php',
];
$additional_css = [$url_base . 'secciones/productos/utils/styles.css'];
include $path_base . "components/head.php";
?>
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

        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros de Búsqueda</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="index.php" class="row g-3 align-items-end">

                    <div class="col-md">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <input type="text" class="form-control" id="descripcion" name="descripcion"
                            value="<?php echo htmlspecialchars($filtrosAplicados['descripcion']); ?>"
                            placeholder="Buscar por descripción...">
                    </div>

                    <div class="col-md-3">
                        <label for="tipo" class="form-label">Tipo</label>
                        <select class="form-select" id="tipo" name="tipo">
                            <option value="">Todos los tipos</option>
                            <?php foreach ($tipos_disponibles as $tipo): ?>
                                <option value="<?php echo htmlspecialchars($tipo); ?>"
                                    <?php echo ($filtrosAplicados['tipo'] === $tipo) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-auto">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Buscar
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Limpiar
                            </a>
                        </div>
                    </div>

                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0"><i class="fas fa-boxes me-2"></i>Gestión de Productos</h4>
                    <small class="text-muted">
                        Mostrando <?php echo count($productos); ?> de <?php echo $paginacion['total_registros']; ?> productos
                        <?php if ($paginacion['total_registros'] > 0): ?>
                            (Página <?php echo $paginacion['pagina_actual']; ?> de <?php echo $paginacion['total_paginas']; ?>)
                        <?php endif; ?>
                    </small>
                </div>
                <?php if ($controller->verificarPermisos('crear')): ?>
                    <a href="registrar.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Nuevo Producto
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Descripción</th>
                                <th>Código de Barras</th>
                                <th>Tipo</th>
                                <th class="text-end">Peso liquido</th>
                                <th class="text-end">NCM</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($productos) > 0): ?>
                                <?php foreach ($productos as $producto): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($producto['id']); ?></td>
                                        <td><?php echo htmlspecialchars($producto['descripcion']); ?></td>
                                        <td><?php echo htmlspecialchars($producto['codigobr']); ?></td>
                                        <td><?php echo htmlspecialchars($producto['tipo']); ?></td>
                                        <td class="text-end"><?php echo $producto['cantidad_formateada']; ?></td>
                                        <td class="text-end"><?php echo htmlspecialchars($producto['ncm_formateado']); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="ver.php?id=<?php echo $producto['id']; ?>" class="btn btn-info btn-sm" title="Ver Producto">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($controller->verificarPermisos('editar')): ?>
                                                    <a href="editar.php?id=<?php echo $producto['id']; ?>" class="btn btn-warning btn-sm" title="Editar Producto">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="javascript:void(0);" onclick="confirmarEliminar(<?php echo $producto['id']; ?>)" class="btn btn-danger btn-sm" title="Eliminar Producto">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <?php if (!empty($filtrosAplicados['descripcion']) || !empty($filtrosAplicados['tipo'])): ?>
                                            No se encontraron productos que coincidan con los filtros aplicados.
                                            <br><a href="index.php" class="btn btn-sm btn-outline-primary mt-2">Ver todos los productos</a>
                                        <?php else: ?>
                                            No hay productos registrados
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($paginacion['total_paginas'] > 1): ?>
                    <nav aria-label="Navegación de páginas" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($paginacion['pagina_actual'] <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $controller->construirURL(['pagina' => $paginacion['pagina_actual'] - 1]); ?>">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </a>
                            </li>
                            <?php
                            $inicio = max(1, $paginacion['pagina_actual'] - 2);
                            $fin = min($paginacion['total_paginas'], $paginacion['pagina_actual'] + 2);

                            if ($inicio > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo $controller->construirURL(['pagina' => 1]); ?>">1</a>
                                </li>
                                <?php if ($inicio > 2): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                                <li class="page-item <?php echo ($i == $paginacion['pagina_actual']) ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo $controller->construirURL(['pagina' => $i]); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($fin < $paginacion['total_paginas']): ?>
                                <?php if ($fin < $paginacion['total_paginas'] - 1): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo $controller->construirURL(['pagina' => $paginacion['total_paginas']]); ?>"><?php echo $paginacion['total_paginas']; ?></a>
                                </li>
                            <?php endif; ?>
                            <li class="page-item <?php echo ($paginacion['pagina_actual'] >= $paginacion['total_paginas']) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $controller->construirURL(['pagina' => $paginacion['pagina_actual'] + 1]); ?>">
                                    Siguiente <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
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
                    <p class="mb-0">¿Está seguro que desea eliminar este producto? Esta acción no se puede deshacer.</p>
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
    <script src="<?php echo $url_base; ?>secciones/productos/js/ProductosManager.js"></script>
    <script>
        const PRODUCTOS_CONFIG = <?php echo json_encode($configuracionJS); ?>;
    </script>
</body>

</html>