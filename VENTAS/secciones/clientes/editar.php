<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '2']);
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: " . $url_base . "secciones/clientes/index.php?error=ID no proporcionado");
    exit();
}
$id = $_GET['id'];
if (file_exists("controllers/ClienteController.php")) {
    include "controllers/ClienteController.php";
} else {
    die("Error: No se pudo cargar el controlador de clientes.");
}
$controller = new ClienteController($conexion, $url_base);

if (!$controller->verificarPermisos('editar', $id)) {
    header("Location: " . $url_base . "secciones/clientes/index.php?error=No tienes permisos para editar este cliente");
    exit();
}

$datos = [];
$errores = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = $controller->procesarEdicion($id);

    if (isset($resultado['errores'])) {
        $errores = $resultado['errores'];
        $datos = $resultado['datos'];
    } elseif (isset($resultado['error'])) {
        $error = $resultado['error'];
        $datos = $resultado['datos'] ?? [];
    }
} else {
    try {
        $cliente = $controller->obtenerClienteParaEdicion($id);
        $datos = [
            'nombre' => $cliente['nombre'],
            'descripcion' => $cliente['descripcion'] ?? '',
            'telefono' => $cliente['telefono'] ?? '',
            'email' => $cliente['email'] ?? '',
            'direccion' => $cliente['direccion'] ?? '',
            'ruc' => $cliente['ruc'] ?? '',
            'cnpj' => $cliente['cnpj'] ?? '',
            'ie' => $cliente['ie'] ?? '',
            'nro' => $cliente['nro'] ?? '',
            'pais' => $cliente['pais']
        ];
    } catch (Exception $e) {
        header("Location: " . $url_base . "secciones/clientes/index.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

$datosVista = $controller->obtenerDatosVista();
$configuracionJS = $controller->obtenerConfiguracionJS();

$controller->logActividad('Acceso editar cliente', 'ID: ' . $id);
$breadcrumb_items = ['Configuracion', 'Clientes', 'Editar Cliente'];
$item_urls = [
    $url_base . 'secciones/configuracion/index.php',
    $url_base . 'secciones/clientes/index.php'
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/clientes/utils/styles.css">

</head>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-user-edit me-2"></i>Editar Cliente</h4>
                <div class="country-selector">
                    <div class="d-flex gap-2">
                        <div class="flag-selector-small <?php echo ($datos['pais'] === 'PY') ? 'active' : ''; ?>" data-country="PY" onclick="selectCountry('PY')" title="Paraguay">
                            <img src="https://flagcdn.com/w40/py.png" alt="Paraguay" class="flag-img-small">
                        </div>
                        <div class="flag-selector-small <?php echo ($datos['pais'] === 'BR') ? 'active' : ''; ?>" data-country="BR" onclick="selectCountry('BR')" title="Brasil">
                            <img src="https://flagcdn.com/w40/br.png" alt="Brasil" class="flag-img-small">
                        </div>
                    </div>
                </div>
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

                <form action="<?php echo $url_base; ?>secciones/clientes/editar.php?id=<?php echo $id; ?>" method="POST">
                    <input type="hidden" name="pais" id="pais" value="<?php echo htmlspecialchars($datos['pais']); ?>">

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="nombre" name="nombre" maxlength="150" required
                                    value="<?php echo htmlspecialchars($datos['nombre']); ?>">
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                                <input type="text" class="form-control" id="descripcion" name="descripcion" maxlength="150"
                                    value="<?php echo htmlspecialchars($datos['descripcion']); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="text" class="form-control" id="telefono" name="telefono" maxlength="20"
                                    value="<?php echo htmlspecialchars($datos['telefono']); ?>">
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <label for="email" class="form-label">Correo Electrónico</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" maxlength="100"
                                    value="<?php echo htmlspecialchars($datos['email']); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4" id="ruc-field">
                            <label for="ruc" class="form-label">RUC/CI</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" id="ruc" name="ruc" maxlength="20"
                                    placeholder="Ej: 12345678"
                                    value="<?php echo htmlspecialchars($datos['ruc']); ?>">
                            </div>
                            <div class="form-text">Solo números, entre 6 y 14 dígitos</div>
                        </div>
                        <div class="col-md-6 mb-4" id="cnpj-field">
                            <label for="cnpj" class="form-label">CNPJ</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" id="cnpj" name="cnpj" maxlength="18"
                                    placeholder="XX.XXX.XXX/XXXX-XX"
                                    value="<?php echo htmlspecialchars($datos['cnpj']); ?>">
                            </div>
                            <div class="form-text">Formato: XX.XXX.XXX/XXXX-XX</div>
                        </div>
                        <div class="col-md-6 mb-4" id="ie-field">
                            <label for="ie" class="form-label">Inscripción Estatal (IE)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-building"></i></span>
                                <input type="text" class="form-control" id="ie" name="ie" maxlength="20"
                                    value="<?php echo htmlspecialchars($datos['ie']); ?>">
                            </div>
                            <div class="form-text">Entre 8 y 14 dígitos</div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <label for="nro" class="form-label">WhatsApp</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fab fa-whatsapp"></i></span>
                                <input type="text" class="form-control" id="nro" name="nro" maxlength="20"
                                    value="<?php echo htmlspecialchars($datos['nro']); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="direccion" class="form-label">Dirección</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                            <input type="text" class="form-control" id="direccion" name="direccion" maxlength="200"
                                value="<?php echo htmlspecialchars($datos['direccion']); ?>">
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="<?php echo $url_base; ?>secciones/clientes/index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
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