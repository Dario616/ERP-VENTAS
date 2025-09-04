<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1']);

if (file_exists("controllers/configController.php")) {
    include "controllers/configController.php";
} else {
    die("Error: No se pudo cargar el controlador de configuración.");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location:" . $url_base . "secciones/configuracion/tipoprod_index.php?error=ID de tipo no válido");
    exit();
}

$id = $_GET['id'];

$controller = new ConfigController($conexion, $url_base);

$errores = [];
$tipo_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'id' => $id,
        'desc' => trim($_POST['desc'])
    ];

    $resultado = $controller->procesarTiposProducto('actualizar', $datos);

    if ($resultado['success']) {
        header("Location:" . $url_base . "secciones/configuracion/tipoprod_index.php?mensaje=" . urlencode($resultado['mensaje']));
        exit();
    } else {
        $errores = $resultado['errores'] ?? ['Error al actualizar'];
    }
}

try {
    $resultadoTipo = $controller->procesarTiposProducto('obtener', ['id' => $id]);
    if ($resultadoTipo['success']) {
        $tipo_data = $resultadoTipo['datos'];
    } else {
        header("Location:" . $url_base . "secciones/configuracion/tipoprod_index.php?error=Tipo de producto no encontrado");
        exit();
    }
} catch (Exception $e) {
    header("Location:" . $url_base . "secciones/configuracion/tipoprod_index.php?error=" . urlencode("Error al consultar el tipo: " . $e->getMessage()));
    exit();
}

$datosVista = $controller->obtenerDatosVista();
$usuario_actual = $datosVista['usuario_actual'] ?? 'Usuario';
$configuracionJS = $controller->obtenerConfiguracionJS();
$breadcrumb_items = ['Configuracion', 'Tipo de Producto', 'Editar Tipo'];
$item_urls = [
    $url_base . 'secciones/configuracion/index.php',
    $url_base . 'secciones/configuracion/tipoprod_index.php'
];
$additional_css = [$url_base . 'secciones/configuracion/utils/styles.css'];
include $path_base . "components/head.php";
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-5">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Editar Tipo de Producto</h4>
            </div>
            <div class="card-body">
                <?php if (isset($errores) && !empty($errores)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><strong>Por favor corrija los siguientes errores:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errores as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="tipoprod_editar.php?id=<?php echo $id; ?>" method="POST">
                    <div class="mb-4">
                        <label for="id" class="form-label">ID</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                            <input type="text" class="form-control" id="id" value="<?php echo htmlspecialchars($tipo_data['id']); ?>" disabled>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="desc" class="form-label">Descripción del Tipo</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-file-alt"></i></span>
                            <input type="text" class="form-control" id="desc" name="desc" required
                                value="<?php echo htmlspecialchars($tipo_data['desc']); ?>">
                        </div>
                        <small class="form-text text-muted">Ingrese una descripción clara del tipo de producto.</small>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="tipoprod_index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Actualizar Tipo
                        </button>
                    </div>
                </form>
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