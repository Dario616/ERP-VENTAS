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

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'descripcion' => trim($_POST['descripcion'])
    ];

    $resultado = $controller->procesarCreditos('crear', $datos);

    if ($resultado['success']) {
        header("Location: " . $url_base . "secciones/configuracion/creditos.php?mensaje=" . urlencode($resultado['mensaje']));
        exit();
    } else {
        $errores = $resultado['errores'] ?? ['Error al registrar'];
    }
}

$datosVista = $controller->obtenerDatosVista();
$usuario_actual = $datosVista['usuario_actual'] ?? 'Usuario';
$configuracionJS = $controller->obtenerConfiguracionJS();
$breadcrumb_items = ['Configuracion', 'Tipos de Creditos', 'Nuevo Tipo'];
$item_urls = [
    $url_base . 'secciones/configuracion/index.php',
    $url_base . 'secciones/configuracion/creditos.php'
];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Cuota de Crédito</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/configuracion/utils/styles.css">
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-5">
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-plus me-2"></i>Nueva Cuota de Crédito</h4>
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
                        <input type="text" name="descripcion" class="form-control" required placeholder="Ej: 12 cuotas, 24 cuotas, etc.">
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