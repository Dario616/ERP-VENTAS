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
    header("Location: " . $url_base . "secciones/configuracion/creditos.php?error=ID inválido");
    exit();
}

$id = $_GET['id'];

$controller = new ConfigController($conexion, $url_base);

$errores = [];
$credito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'id' => $id,
        'descripcion' => trim($_POST['descripcion'])
    ];

    $resultado = $controller->procesarCreditos('actualizar', $datos);

    if ($resultado['success']) {
        header("Location: creditos.php?mensaje=" . urlencode($resultado['mensaje']));
        exit();
    } else {
        $errores = $resultado['errores'] ?? ['Error al actualizar'];
    }
}

try {
    $resultadoCredito = $controller->procesarCreditos('obtener', ['id' => $id]);
    if ($resultadoCredito['success']) {
        $credito = $resultadoCredito['datos'];
    } else {
        header("Location: creditos.php?error=Crédito no encontrado");
        exit();
    }
} catch (Exception $e) {
    header("Location: creditos.php?error=Error de consulta");
    exit();
}

$datosVista = $controller->obtenerDatosVista();
$usuario_actual = $datosVista['usuario_actual'] ?? 'Usuario';
$configuracionJS = $controller->obtenerConfiguracionJS();
$breadcrumb_items = ['Configuracion', 'Tipos de Creditos', 'Editar Tipo'];
$item_urls = [
    $url_base . 'secciones/configuracion/index.php',
    $url_base . 'secciones/configuracion/creditos.php'
];
$additional_css = [$url_base . 'secciones/configuracion/utils/styles.css'];
include $path_base . "components/head.php";
?>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-5">
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-edit me-2"></i>Editar Cuota de Crédito</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($errores)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errores as $error): ?>
                            <p><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label>Descripción</label>
                        <input type="text" name="descripcion" class="form-control" value="<?= htmlspecialchars($credito['descripcion']) ?>" required placeholder="Ej: 12 cuotas, 24 cuotas, etc.">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?php echo $url_base; ?>secciones/configuracion/creditos.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Guardar</button>
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