<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php?error=ID de producto no válido");
    exit();
}

$id = $_GET['id'];

if (file_exists("controllers/ProductoController.php")) {
    include "controllers/ProductoController.php";
} else {
    die("Error: No se pudo cargar el controlador de productos.");
}

$controller = new ProductoController($conexion, $url_base);
try {
    $producto = $controller->obtenerProductoParaVer($id);
} catch (Exception $e) {
    header("Location: " . $url_base . "secciones/productos/index.php?error=" . urlencode($e->getMessage()));
    exit();
}
$unidades_producto = $controller->obtenerUnidadesMedidaProducto($id);

$datosVista = $controller->obtenerDatosVista();
$configuracionJS = $controller->obtenerConfiguracionJS();

$controller->logActividad('Ver producto', 'ID: ' . $id);
$breadcrumb_items = ['Configuracion', 'Producto', 'Ver Producto'];
$item_urls = [
    $url_base . 'secciones/configuracion/index.php',
    $url_base . 'secciones/productos/index.php'
];
$additional_css = [$url_base . 'secciones/productos/utils/styles.css'];
include $path_base . "components/head.php";
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-box-open me-2"></i>Detalles del Producto</h4>
                <div>
                    <?php if ($controller->verificarPermisos('editar')): ?>
                        <a href="editar.php?id=<?php echo $id; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Editar
                        </a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-secondary ms-2">
                        <i class="fas fa-arrow-left me-2"></i>Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="card-title mb-4 fw-bold"><?php echo htmlspecialchars($producto['descripcion']); ?></h5>

                        <div class="mb-3">
                            <span class="fw-bold"><i class="fas fa-hashtag me-2"></i>ID:</span>
                            <span class="badge bg-primary"><?php echo htmlspecialchars($producto['id']); ?></span>
                        </div>

                        <div class="mb-3">
                            <span class="fw-bold"><i class="fas fa-barcode me-2"></i>Código de Barras:</span>
                            <?php echo htmlspecialchars($producto['codigobr']); ?>
                        </div>

                        <div class="mb-3">
                            <span class="fw-bold"><i class="fas fa-layer-group me-2"></i>Tipo:</span>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($producto['tipo']); ?></span>
                        </div>

                        <div class="mb-3">
                            <span class="fw-bold"><i class="fas fa-cubes me-2"></i>Peso liquido:</span>
                            <?php echo $producto['cantidad_formateada']; ?>
                        </div>

                        <div class="mb-3">
                            <span class="fw-bold"><i class="fas fa-hashtag me-2"></i>NCM:</span>
                            <?php echo htmlspecialchars($producto['ncm']); ?>
                        </div>

                        <div class="mb-3">
                            <span class="fw-bold"><i class="fas fa-ruler me-2"></i>Unidades de Medida:</span>
                            <?php if (!empty($unidades_producto)): ?>
                                <div class="ms-4 mt-2">
                                    <?php foreach ($unidades_producto as $um): ?>
                                        <span class="badge bg-success me-2 mb-1">
                                            <i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars($um); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">No hay unidades de medida definidas</span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($producto['nombreimg'])): ?>
                            <div class="mb-3">
                                <span class="fw-bold"><i class="fas fa-image me-2"></i>Nombre de Imagen:</span>
                                <?php echo htmlspecialchars($producto['nombreimg']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 text-center d-flex align-items-center justify-content-center">
                        <?php if (!empty($producto['base64img'])): ?>
                            <div class="border p-3 rounded">
                                <img src="data:<?php echo htmlspecialchars($producto['tipoimg']); ?>;base64,<?php echo $producto['base64img']; ?>"
                                    alt="<?php echo htmlspecialchars($producto['descripcion']); ?>"
                                    class="img-fluid" style="max-height: 600px;">
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info p-4 w-100">
                                <i class="fas fa-info-circle me-2"></i>No hay imagen disponible para este producto.
                            </div>
                        <?php endif; ?>
                    </div>
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