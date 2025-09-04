<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol('1');

if (file_exists("controllers/ProductoController.php")) {
    include "controllers/ProductoController.php";
} else {
    die("Error: No se pudo cargar el controlador de productos.");
}

$controller = new ProductoController($conexion, $url_base);

if (!$controller->verificarPermisos('crear')) {
    header("Location: " . $url_base . "secciones/productos/index.php?error=No tienes permisos para crear productos");
    exit();
}

$datos = [
    'descripcion' => '',
    'codigobr' => '',
    'tipo' => '',
    'cantidad' => '',
    'ncm' => ''
];
$errores = [];
$error = '';

$tipos = $controller->obtenerTipos();
$unidades_medida_disponibles = $controller->obtenerUnidadesMedidaDisponibles();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = $controller->procesarRegistro();

    if (isset($resultado['errores'])) {
        $errores = $resultado['errores'];
        $datos = $resultado['datos'];
    } elseif (isset($resultado['error'])) {
        $error = $resultado['error'];
        $datos = $resultado['datos'] ?? $datos;
    }
}

$datosVista = $controller->obtenerDatosVista();
$configuracionJS = $controller->obtenerConfiguracionJS();
$controller->logActividad('Acceso registrar producto');
$breadcrumb_items = ['Configuracion', 'Producto', 'Nuevo Producto'];
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
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Registrar Nuevo Producto</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($errores)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><strong>Por favor corrije los siguientes errores:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errores as $errorItem): ?>
                                <li><?php echo htmlspecialchars($errorItem); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="registrar.php" method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="descripcion" class="form-label">
                                Descripción
                                <span class="text-muted">
                                    <i class="fas fa-info-circle" data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Incluye gramatura, ancho y metros para cálculo automático de peso"></i>
                                </span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                <input type="text" class="form-control" id="descripcion" name="descripcion" required
                                    value="<?php echo htmlspecialchars($datos['descripcion']); ?>"
                                    placeholder="Nombre o Descripcion del Producto">
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <label for="codigobr" class="form-label">Código de Barras</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                <input type="text" class="form-control" id="codigobr" name="codigobr" required
                                    value="<?php echo htmlspecialchars($datos['codigobr']); ?>"
                                    placeholder="Código de barras del producto">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="tipo" class="form-label">Tipo de Producto</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-layer-group"></i></span>
                                <select class="form-select" id="tipo" name="tipo" required>
                                    <option value="" disabled selected>Seleccione un tipo</option>
                                    <?php foreach ($tipos as $tipo_option): ?>
                                        <option value="<?php echo htmlspecialchars($tipo_option); ?>"
                                            <?php echo ($datos['tipo'] === $tipo_option) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tipo_option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="ncm" class="form-label">Código NCM</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                <input type="text" class="form-control" id="ncm" name="ncm" required
                                    value="<?php echo htmlspecialchars($datos['ncm']); ?>"
                                    placeholder="Código NCM"
                                    maxlength="100">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="cantidad" class="form-label">
                                Peso líquido (kg)
                                <span class="text-muted">
                                    <i class="fas fa-magic" data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Se calcula automáticamente si la descripción contiene la información necesaria"></i>
                                </span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-cubes"></i></span>
                                <input type="text" class="form-control" id="cantidad" name="cantidad" required
                                    value="<?php echo htmlspecialchars($datos['cantidad']); ?>"
                                    placeholder="0,000">
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Se calculará automáticamente si la descripción incluye gramatura, ancho y metros.
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="imagen" class="form-label">Imagen del Producto</label>
                            <input type="file" class="form-control custom-file-input" id="imagen" name="imagen" accept="image/*">
                            <div class="form-text"><i class="fas fa-info-circle me-1"></i>Formatos aceptados: JPG, PNG, GIF. Tamaño máximo: 2MB.</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Unidades de Medida</label>
                            <div class="card p-3">
                                <?php if (!empty($unidades_medida_disponibles)): ?>
                                    <?php foreach ($unidades_medida_disponibles as $index => $um): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="unidades_medida[]"
                                                value="<?php echo htmlspecialchars($um); ?>"
                                                id="um_<?php echo $index; ?>">
                                            <label class="form-check-label" for="um_<?php echo $index; ?>">
                                                <?php echo htmlspecialchars($um); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No hay unidades de medida disponibles en la base de datos.</p>
                                <?php endif; ?>
                                <div class="mt-2">
                                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Selecciona todas las unidades de medida aplicables para este producto.</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4" id="image-preview-container" style="display:none;">
                            <label class="form-label fw-bold mb-3">Vista previa</label>
                            <div>
                                <img id="image-preview" src="#" alt="Vista previa" style="max-width: 300px; max-height: 300px;">
                            </div>
                        </div>
                    </div>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Registrar Producto
                        </button>
                    </div>
                </form>
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