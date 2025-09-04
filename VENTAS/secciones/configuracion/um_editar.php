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
    header("Location: " . $url_base . "secciones/configuracion/um_index.php?error=ID inválido");
    exit();
}

$id = $_GET['id'];

$controller = new ConfigController($conexion, $url_base);

$errores = [];
$unidad = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'id' => $id,
        'desc' => trim($_POST['desc'])
    ];

    $resultado = $controller->procesarUnidadesMedida('actualizar', $datos);

    if ($resultado['success']) {
        header("Location: um_index.php?mensaje=" . urlencode($resultado['mensaje']));
        exit();
    } else {
        $errores = $resultado['errores'] ?? ['Error al actualizar'];
    }
}

try {
    $resultadoUnidad = $controller->procesarUnidadesMedida('obtener', ['id' => $id]);
    if ($resultadoUnidad['success']) {
        $unidad = $resultadoUnidad['datos'];
    } else {
        header("Location: um_index.php?error=Unidad no encontrada");
        exit();
    }
} catch (Exception $e) {
    header("Location: um_index.php?error=Error de consulta");
    exit();
}

$datosVista = $controller->obtenerDatosVista();
$usuario_actual = $datosVista['usuario_actual'] ?? 'Usuario';
$configuracionJS = $controller->obtenerConfiguracionJS();
$breadcrumb_items = ['Configuracion', 'Unidad de Medida', 'Editar'];
$item_urls = [
    $url_base . 'secciones/configuracion/index.php',
    $url_base . 'secciones/configuracion/um_index.php'
];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Unidades de Medida</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/configuracion/utils/styles.css">
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-5">
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-edit me-2"></i>Editar Unidad</h4>
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
                        <input type="text" name="desc" class="form-control" value="<?= htmlspecialchars($unidad['desc']) ?>" required>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?php echo $url_base; ?>secciones/configuracion/um_index.php" class="btn btn-secondary">Cancelar</a>
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