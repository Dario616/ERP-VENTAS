<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";

requerirLogin();

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

if (!$controller->verificarPermisos('ver', $id)) {
    header("Location: " . $url_base . "secciones/clientes/index.php?error=No tienes permisos para ver este cliente");
    exit();
}

try {
    $cliente = $controller->obtenerClienteParaVer($id);
} catch (Exception $e) {
    header("Location: " . $url_base . "secciones/clientes/index.php?error=" . urlencode($e->getMessage()));
    exit();
}

$datosVista = $controller->obtenerDatosVista();
$configuracionJS = $controller->obtenerConfiguracionJS();

$controller->logActividad('Ver cliente', 'ID: ' . $id);
$breadcrumb_items = ['Configuracion', 'Clientes', 'Ver Cliente'];
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
    <title>Detalles del Cliente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/clientes/utils/styles.css">
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3 text-black">
                    <h4 class="mb-0"><i class="fas fa-user me-2"></i>Detalles del Cliente</h4>
                    <?php if ($cliente['pais'] === 'BR'): ?>
                        <div class="country-badge text">
                            <img src="https://flagcdn.com/w40/br.png" alt="Brasil" class="flag-img">
                            <span>Brasil</span>
                        </div>
                    <?php else: ?>
                        <div class="country-badge">
                            <img src="https://flagcdn.com/w40/py.png" alt="Paraguay" class="flag-img">
                            <span>Paraguay</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($controller->verificarPermisos('editar', $id)): ?>
                        <a href="<?php echo $url_base; ?>secciones/clientes/editar.php?id=<?php echo $id; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Editar
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo $url_base; ?>secciones/clientes/index.php" class="btn btn-secondary ms-2">
                        <i class="fas fa-arrow-left me-2"></i>Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Información Personal</h5>
                        <table class="table">
                            <tbody>
                                <tr>
                                    <th style="width: 30%;"><i class="fas fa-hashtag me-2"></i>ID:</th>
                                    <td><?php echo htmlspecialchars($cliente['id']); ?></td>
                                </tr>
                                <tr>
                                    <th><i class="fas fa-user me-2"></i>Nombre:</th>
                                    <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                                </tr>
                                <tr>
                                    <th><i class="fas fa-info-circle me-2"></i>Descripción:</th>
                                    <td><?php echo !empty($cliente['descripcion']) ? htmlspecialchars($cliente['descripcion']) : '<span class="text-muted">No registrada</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th><i class="fas fa-flag me-2"></i>País:</th>
                                    <td>
                                        <?php if ($cliente['pais'] === 'BR'): ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="https://flagcdn.com/w40/br.png" alt="Brasil" style="width: 20px; height: 15px; object-fit: cover; border-radius: 2px;">
                                                <span>Brasil</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="https://flagcdn.com/w40/py.png" alt="Paraguay" style="width: 20px; height: 15px; object-fit: cover; border-radius: 2px;">
                                                <span>Paraguay</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <?php if ($cliente['pais'] === 'BR'): ?>
                                        <th><i class="fas fa-id-card me-2"></i>CNPJ:</th>
                                        <td>
                                            <?php if (!empty($cliente['cnpj'])): ?>
                                                <span class="document-field"><?php echo htmlspecialchars($cliente['cnpj']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">No registrado</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php else: ?>
                                        <th><i class="fas fa-id-card me-2"></i>RUC/CI:</th>
                                        <td>
                                            <?php if (!empty($cliente['ruc'])): ?>
                                                <span class="document-field"><?php echo htmlspecialchars($cliente['ruc']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">No registrado</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                                <?php if ($cliente['pais'] === 'BR' && !empty($cliente['ie'])): ?>
                                    <tr>
                                        <th><i class="fas fa-building me-2"></i>IE:</th>
                                        <td>
                                            <span class="document-field"><?php echo htmlspecialchars($cliente['ie']); ?></span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="col-md-6 mb-4">
                        <h5 class="mb-3"><i class="fas fa-address-book me-2"></i>Información de Contacto</h5>
                        <table class="table">
                            <tbody>
                                <tr>
                                    <th style="width: 30%;"><i class="fas fa-phone me-2"></i>Teléfono:</th>
                                    <td><?php echo !empty($cliente['telefono']) ? htmlspecialchars($cliente['telefono']) : '<span class="text-muted">No registrado</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th><i class="fab fa-whatsapp me-2"></i>WhatsApp:</th>
                                    <td>
                                        <?php if (!empty($cliente['nro'])): ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <span><?php echo htmlspecialchars($cliente['nro']); ?></span>
                                                <a href="https://wa.me/<?php echo htmlspecialchars($cliente['nro']); ?>"
                                                    target="_blank"
                                                    class="btn btn-sm btn-success"
                                                    title="Abrir WhatsApp">
                                                    <i class="fab fa-whatsapp"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">No registrado</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><i class="fas fa-envelope me-2"></i>Email:</th>
                                    <td>
                                        <?php if (!empty($cliente['email'])): ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <span><?php echo htmlspecialchars($cliente['email']); ?></span>
                                                <a href="mailto:<?php echo htmlspecialchars($cliente['email']); ?>"
                                                    class="btn btn-sm btn-primary"
                                                    title="Enviar correo">
                                                    <i class="fas fa-envelope"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">No registrado</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><i class="fas fa-map-marker-alt me-2"></i>Dirección:</th>
                                    <td style="word-break: break-word; white-space: normal;">
                                        <?php echo !empty($cliente['direccion']) ? htmlspecialchars($cliente['direccion']) : '<span class="text-muted">No registrada</span>'; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!empty($cliente['fecha_registro_formateada'])): ?>
                    <div class="mt-2">
                        <p class="text-muted">
                            <i class="fas fa-clock me-2"></i>Registrado el:
                            <?php echo $cliente['fecha_registro_formateada']; ?>
                        </p>
                    </div>
                <?php endif; ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="fas fa-info-circle me-2"></i>Información del Documento
                                </h6>
                                <?php if ($cliente['pais'] === 'BR'): ?>
                                    <p class="card-text mb-1">
                                        <strong>País:</strong> Brasil 🇧🇷
                                    </p>
                                    <p class="card-text mb-1">
                                        <strong>Tipo de documento:</strong> CNPJ (Cadastro Nacional da Pessoa Jurídica)
                                    </p>
                                    <?php if (!empty($cliente['cnpj'])): ?>
                                        <p class="card-text mb-1">
                                            <strong>CNPJ:</strong> <code><?php echo htmlspecialchars($cliente['cnpj']); ?></code>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($cliente['ie'])): ?>
                                        <p class="card-text mb-0">
                                            <strong>Inscripción Estatal:</strong> <code><?php echo htmlspecialchars($cliente['ie']); ?></code>
                                        </p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="card-text mb-1">
                                        <strong>País:</strong> Paraguay 🇵🇾
                                    </p>
                                    <p class="card-text mb-1">
                                        <strong>Tipo de documento:</strong> RUC/CI (Registro Único del Contribuyente / Cédula de Identidad)
                                    </p>
                                    <?php if (!empty($cliente['ruc'])): ?>
                                        <p class="card-text mb-0">
                                            <strong>RUC/CI:</strong> <code><?php echo htmlspecialchars($cliente['ruc']); ?></code>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card border-primary">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-chart-bar me-2"></i>Resumen del Cliente
                                </h6>
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <div class="border-end">
                                            <h5 class="text-<?php echo $cliente['tiene_email'] ? 'success' : 'muted'; ?>">
                                                <i class="fas fa-envelope"></i>
                                            </h5>
                                            <small><?php echo $cliente['tiene_email'] ? 'Con Email' : 'Sin Email'; ?></small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border-end">
                                            <h5 class="text-<?php echo $cliente['tiene_telefono'] ? 'success' : 'muted'; ?>">
                                                <i class="fas fa-phone"></i>
                                            </h5>
                                            <small><?php echo $cliente['tiene_telefono'] ? 'Con Teléfono' : 'Sin Teléfono'; ?></small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border-end">
                                            <h5 class="text-<?php echo $cliente['tiene_whatsapp'] ? 'success' : 'muted'; ?>">
                                                <i class="fab fa-whatsapp"></i>
                                            </h5>
                                            <small><?php echo $cliente['tiene_whatsapp'] ? 'Con WhatsApp' : 'Sin WhatsApp'; ?></small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <h5 class="text-<?php echo !empty($cliente['documento_principal']) ? 'success' : 'muted'; ?>">
                                            <i class="fas fa-id-card"></i>
                                        </h5>
                                        <small><?php echo !empty($cliente['documento_principal']) ? 'Con ' . $cliente['tipo_documento'] : 'Sin Documento'; ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $url_base; ?>secciones/clientes/js/clientes.js"></script>
    <script>
        const CLIENTE_CONFIG = <?php echo json_encode($configuracionJS); ?>;
        ClienteManager.viewPage.init({
            id: <?php echo $cliente['id']; ?>,
            nombre: '<?php echo addslashes($cliente['nombre']); ?>',
            pais: '<?php echo $cliente['pais']; ?>'
        });
    </script>
</body>

</html>