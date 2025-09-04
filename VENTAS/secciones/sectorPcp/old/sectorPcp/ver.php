<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '2']);

function esProductoEnUnidades($tipoProducto)
{
    $tipo = mb_strtolower(trim($tipoProducto), 'UTF-8');
    return in_array($tipo, ['toallitas', 'toallita', 'paños', 'paño', 'panos', 'pano']);
}

date_default_timezone_set('America/Asuncion');

// REEMPLAZAR la sección de API con esto:
if (isset($_GET['action']) && $_GET['action'] === 'calcular_paquetes_necesarios') {
    header('Content-Type: application/json');

    try {
        $nombreProducto = $_GET['producto'] ?? '';
        $bobinasSolicitadas = (int)($_GET['bobinas'] ?? 0);

        if (empty($nombreProducto) || $bobinasSolicitadas <= 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Parámetros inválidos'
            ]);
            exit();
        }

        // ✅ USAR LA MISMA CONSULTA que en obtenerStockGeneral
        $sql = "SELECT 
            bobinas_pacote, 
            cantidad_disponible, 
            cantidad_paquetes,
            tipo_producto 
        FROM stock_agregado 
        WHERE nombre_producto = :nombre_producto 
            AND cantidad_disponible > 0
            AND cantidad_paquetes > 0
        ORDER BY cantidad_disponible DESC
        LIMIT 1";

        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':nombre_producto', $nombreProducto, PDO::PARAM_STR);
        $stmt->execute();
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        // ✅ DEBUG: Log para verificar qué datos obtiene la API
        error_log("DEBUG - API consulta producto '$nombreProducto': " . json_encode($producto));

        if (!$producto) {
            echo json_encode([
                'success' => false,
                'error' => 'Producto no encontrado o sin stock'
            ]);
            exit();
        }

        $bobinasPorPaquete = (int)$producto['bobinas_pacote'];
        $bobinasDisponibles = (int)$producto['cantidad_disponible'];
        $paquetesDisponibles = (int)$producto['cantidad_paquetes'];

        // Calcular paquetes necesarios
        $paquetesNecesarios = ceil($bobinasSolicitadas / $bobinasPorPaquete);
        $bobinasTotalesReservadas = $paquetesNecesarios * $bobinasPorPaquete;
        $bobinasExcedente = $bobinasTotalesReservadas - $bobinasSolicitadas;

        // ✅ VALIDAR DISPONIBILIDAD REAL
        $disponible = ($paquetesNecesarios <= $paquetesDisponibles) && ($bobinasTotalesReservadas <= $bobinasDisponibles);

        echo json_encode([
            'success' => true,
            'bobinas_solicitadas' => $bobinasSolicitadas,
            'bobinas_por_paquete' => $bobinasPorPaquete,
            'paquetes_necesarios' => $paquetesNecesarios,
            'paquetes_disponibles' => $paquetesDisponibles,
            'bobinas_disponibles' => $bobinasDisponibles,
            'bobinas_totales_reservadas' => $bobinasTotalesReservadas,
            'bobinas_excedente' => $bobinasExcedente,
            'disponible' => $disponible,
            'mensaje' => $disponible
                ? "Se reservarán $paquetesNecesarios paquetes ($bobinasTotalesReservadas bobinas) para cubrir $bobinasSolicitadas bobinas solicitadas"
                : "Stock insuficiente. Necesita $paquetesNecesarios paquetes, disponibles: $paquetesDisponibles paquetes ($bobinasDisponibles bobinas)"
        ]);
    } catch (Exception $e) {
        error_log("Error calculando paquetes necesarios: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Error interno del servidor'
        ]);
    }
    exit();
}

$idVenta = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($idVenta <= 0) {
    header("Location: " . $url_base . "secciones/sectorPcp/index.php?error=ID de venta inválido");
    exit();
}

if (file_exists("controllers/VerVentaController.php")) {
    include "controllers/VerVentaController.php";
} else {
    die("Error: No se pudo cargar el controlador de PCP.");
}

$controller = new VerVentaController($conexion, $url_base);

if (!$controller->verificarPermisos('procesar', $idVenta)) {
    header("Location: " . $url_base . "secciones/sectorPcp/index.php?error=No tienes permisos para procesar esta venta");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $resultado = $controller->procesarFormularioVenta($idVenta, $_POST);

    if (isset($resultado['error'])) {
        $error = $resultado['error'];
    }
}

try {
    $venta = $controller->obtenerVentaParaProcesamiento($idVenta);
    $productos = $venta['productos'];
    $imagenesAutorizacion = $venta['imagenes_autorizacion'];
    $imagenesProductos = $venta['imagenes_productos'];
    $stockGeneral = $venta['stock_general'];
    // DEBUG: Ver qué datos tiene stockGeneral
    error_log("DEBUG - stockGeneral en ver.php: " . print_r($stockGeneral, true));
    if (!empty($stockGeneral)) {
        foreach ($stockGeneral as $index => $stock) {
            error_log("DEBUG - Stock[$index]: " . print_r($stock, true));
        }
    }
} catch (Exception $e) {
    header("Location: " . $url_base . "secciones/sectorPcp/index.php?error=" . urlencode($e->getMessage()));
    exit();
}

$datosVista = $controller->obtenerDatosVista('procesar_venta');
$configuracionJS = $controller->obtenerConfiguracionJS();
$controller->logActividad('Acceso procesamiento venta', 'ID: ' . $idVenta);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesar Venta - PCP #<?php echo $idVenta; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="<?php echo $url_base; ?>secciones/sectorPcp/utils/styles.css" rel="stylesheet">
</head>

<body>
    <!-- [NAVEGACIÓN SIN CAMBIOS] -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $url_base; ?>index.php">
                <img src="<?php echo $url_base; ?>utils/logoa.png" alt="America TNT" height="20">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $url_base; ?>index.php">
                            <i class="fas fa-home me-1"></i>Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $url_base; ?>secciones/sectorPcp/main.php">
                            <i class="fas fa-industry me-1"></i>Gestión PCP
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $url_base; ?>secciones/sectorPcp/index.php">
                            <i class="fas fa-check-circle me-1"></i>Ventas Aprobadas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="fas fa-file-alt me-1"></i>Venta #<?php echo $idVenta; ?>
                        </a>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($_SESSION['nombre']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo $url_base; ?>perfil.php"><i class="fas fa-id-card me-2"></i>Mi Perfil</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="<?php echo $url_base; ?>cerrar_sesion.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid my-4">
        <!-- [ALERTAS SIN CAMBIOS] -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['mensaje'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_GET['mensaje']); ?>
            </div>
        <?php endif; ?>

        <!-- [INFORMACIÓN DE LA VENTA - SIN CAMBIOS] -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información de la Venta</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">ID Venta:</th>
                                <td><strong>#<?php echo $venta['id']; ?></strong></td>
                            </tr>
                            <tr>
                                <th>Cliente:</th>
                                <td><?php echo htmlspecialchars($venta['cliente']); ?></td>
                            </tr>
                            <tr>
                                <th>Vendedor:</th>
                                <td><?php echo htmlspecialchars($venta['nombre_vendedor']); ?></td>
                            </tr>
                            <tr>
                                <th>Aprobado por:</th>
                                <td>
                                    <span class="badge bg-info text-dark">
                                        <i class="fas fa-user-check me-1"></i>
                                        <?php echo htmlspecialchars($venta['nombre_contador'] ?? 'Directo'); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Fecha de Venta:</th>
                                <td><?php echo date('d/m/Y', strtotime($venta['fecha_venta'])); ?></td>
                            </tr>
                            <tr>
                                <th>Fecha de Aprobación:</th>
                                <td>
                                    <?php
                                    echo !empty($venta['fecha_aprobacion'])
                                        ? date('d/m/Y H:i', strtotime($venta['fecha_aprobacion']))
                                        : 'Directo';
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Condición de Pago:</th>
                                <td>
                                    <?php if ($venta['es_credito']): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-credit-card me-1"></i>
                                            <?php if (!empty($venta['tipocredito'])): ?>
                                                Crédito <?php echo htmlspecialchars($venta['tipocredito']); ?>d
                                            <?php else: ?>
                                                Crédito
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-money-bill me-1"></i>Contado
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Tipo de Flete:</th>
                                <td><?php echo htmlspecialchars($venta['tipoflete']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-dollar-sign me-2"></i>Resumen Financiero</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Moneda:</th>
                                <td><strong><?php echo htmlspecialchars($venta['moneda']); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Subtotal:</th>
                                <td>
                                    <?php echo $controller->formatearMoneda($venta['subtotal'], $venta['moneda']); ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Total con IVA:</th>
                                <td class="fs-5 fw-bold text-success">
                                    <?php echo $controller->formatearMoneda($venta['monto_total'], $venta['moneda']); ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Número de Productos:</th>
                                <td><?php echo $venta['num_productos']; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- [OBSERVACIONES DEL CONTADOR - SIN CAMBIOS] -->
        <?php if (!empty($venta['observaciones_contador'])): ?>
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-comment-dots me-2"></i>Observaciones del Contador</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($venta['observaciones_contador'])); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- [DETALLE DE PRODUCTOS - SIN CAMBIOS] -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Detalle de Productos</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>NCM</th>
                                <th>Producto</th>
                                <th>Tipo Producto</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-center">UM</th>
                                <th class="text-end">Precio Unit.</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-center">Imagen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $producto): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($producto['ncm'] ?: 'N/A'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($producto['descripcion']); ?>
                                        <?php if (esProductoEnUnidades($producto['tipoproducto']) && mb_strtolower($producto['tipoproducto'], 'UTF-8') === 'toallitas'): ?>
                                            <span class="producto-info-badge badge-toallitas">Toallitas - 1 caja/unidad</span>
                                        <?php elseif (esProductoEnUnidades($producto['tipoproducto']) && mb_strtolower($producto['tipoproducto'], 'UTF-8') !== 'toallitas'): ?>
                                            <span class="producto-info-badge badge-toallitas">
                                                <?php
                                                $unidadMedida = strtolower($producto['unidadmedida'] ?? 'unidad');
                                                if ($unidadMedida === 'caja' || $unidadMedida === 'cajas') {
                                                    echo 'Paños - 1 caja/unidad';
                                                } else {
                                                    echo 'Paños - 1 unidad/pieza';
                                                }
                                                ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="producto-info-badge badge-produccion">
                                                <?php echo number_format($producto['peso_por_bobina'], 2); ?> kg/bobina
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($producto['tipoproducto']); ?></td>
                                    <td class="text-end">
                                        <?php echo number_format((float)$producto['cantidad'], 2, ',', '.'); ?>
                                        <?php if (esProductoEnUnidades($producto['tipoproducto']) && mb_strtolower($producto['tipoproducto'], 'UTF-8') === 'toallitas'): ?>
                                            <div class="info-peso">(Cajas)</div>
                                        <?php elseif (esProductoEnUnidades($producto['tipoproducto']) && mb_strtolower($producto['tipoproducto'], 'UTF-8') !== 'toallitas'): ?>
                                            <div class="info-peso">
                                                <?php
                                                $unidadMedida = strtolower($producto['unidadmedida'] ?? 'unidad');
                                                if ($unidadMedida === 'caja' || $unidadMedida === 'cajas') {
                                                    echo '(Cajas)';
                                                } else {
                                                    echo '(Unidades)';
                                                }
                                                ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="info-peso">
                                                (~<?php echo $producto['peso_por_bobina'] > 0 ? number_format($producto['cantidad'] / $producto['peso_por_bobina'], 0) : 0; ?> bobinas)
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo htmlspecialchars($producto['unidadmedida'] ?: 'N/A'); ?></td>
                                    <td class="text-end">
                                        <?php echo $controller->formatearMoneda($producto['precio'], $venta['moneda']); ?>
                                    </td>
                                    <td class="text-end fw-bold">
                                        <?php
                                        $subtotal = $producto['cantidad'] * $producto['precio'];
                                        echo $controller->formatearMoneda($subtotal, $venta['moneda']);
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (isset($imagenesProductos[$producto['id']])): ?>
                                            <img src="data:<?php echo $imagenesProductos[$producto['id']]['tipo']; ?>;base64,<?php echo $imagenesProductos[$producto['id']]['imagen']; ?>"
                                                class="img-thumbnail ver-imagen-producto"
                                                style="width: 50px; height: 50px; object-fit: cover; cursor: pointer;"
                                                data-id-producto="<?php echo $producto['id']; ?>"
                                                title="Click para ver a tamaño completo">
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-secondary">
                                                <i class="fas fa-image-slash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-secondary">
                                <th colspan="7" class="text-end">Total sin IVA:</th>
                                <th class="text-end"><?php echo $controller->formatearMoneda($venta['subtotal'], $venta['moneda']); ?></th>
                            </tr>
                            <tr class="table-primary">
                                <th colspan="7" class="text-end">Total con IVA:</th>
                                <th class="text-end fs-5"><?php echo $controller->formatearMoneda($venta['monto_total'], $venta['moneda']); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php if (!empty($venta['descripcion_vendedor'])): ?>
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Descripción del Vendedor</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($venta['descripcion_vendedor'])); ?></p>
                </div>
            </div>
        <?php endif; ?>
        <!-- [DOCUMENTOS ADJUNTOS - SIN CAMBIOS] -->
        <?php if (!empty($imagenesAutorizacion)): ?>
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-paperclip me-2"></i>Documentos Adjuntos de la Autorización</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            <h6 class="text-muted mb-3">Archivos adjuntos del vendedor (<?php echo count($imagenesAutorizacion); ?>):</h6>
                            <p class="small text-muted mb-3">Click sobre las imágenes o PDFs para verlos en tamaño completo</p>
                            <div class="galeria-imagenes">
                                <?php foreach ($imagenesAutorizacion as $index => $imagen): ?>
                                    <div class="text-center">
                                        <?php if (strpos($imagen['tipo_archivo'], 'image') !== false): ?>
                                            <img src="data:<?php echo $imagen['tipo_archivo']; ?>;base64,<?php echo $imagen['base64_imagen']; ?>"
                                                class="img-thumbnail imagen-autorizacion-thumb"
                                                style="width: 150px; height: 150px; object-fit: cover;"
                                                data-imagen-index="<?php echo $index; ?>"
                                                data-tipo-archivo="imagen"
                                                title="<?php echo htmlspecialchars($imagen['nombre_archivo'] ?: 'Imagen ' . ($index + 1)); ?>">
                                        <?php else: ?>
                                            <div class="pdf-thumbnail imagen-autorizacion-thumb"
                                                style="width: 150px; height: 150px; display: flex; align-items: center; justify-content: center; flex-direction: column; background: linear-gradient(135deg, #ff6b6b, #ee5a24); color: white; border-radius: 8px; cursor: pointer; transition: all 0.3s ease;"
                                                data-imagen-index="<?php echo $index; ?>"
                                                data-tipo-archivo="pdf"
                                                title="Click para ver el PDF: <?php echo htmlspecialchars($imagen['nombre_archivo']); ?>">
                                                <i class="fas fa-file-pdf fa-3x mb-2"></i>
                                                <small class="fw-bold">PDF</small>
                                                <small style="font-size: 10px;">Click para ver</small>
                                            </div>
                                            <a href="data:<?php echo $imagen['tipo_archivo']; ?>;base64,<?php echo $imagen['base64_imagen']; ?>"
                                                download="<?php echo htmlspecialchars($imagen['nombre_archivo']); ?>"
                                                class="btn btn-sm btn-outline-primary mt-2"
                                                title="Descargar PDF">
                                                <i class="fas fa-download me-1"></i>Descargar
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($imagen['nombre_archivo']): ?>
                                            <p class="text-muted small mt-2 mb-0" style="max-width: 150px; word-wrap: break-word;">
                                                <i class="fas fa-file me-1"></i><?php echo htmlspecialchars($imagen['nombre_archivo']); ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if ($imagen['descripcion_imagen']): ?>
                                            <p class="text-muted small mt-1 mb-0" style="max-width: 150px; word-wrap: break-word;">
                                                <?php echo htmlspecialchars($imagen['descripcion_imagen']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ✅ SECCIÓN DE ACCIONES - AGREGADA NUEVA OPCIÓN FINALIZAR VENTA -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Acciones Disponibles</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- ENVIAR A PRODUCCIÓN (sin cambios importantes) -->
                    <div class="col-lg-3 mb-4">
                        <div class="card border-primary h-100">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="fas fa-industry me-2"></i>Enviar a Producción</h6>
                            </div>
                            <div class="card-body btn-option">
                                <div class="option-content">
                                    <p class="text-muted small mb-3">
                                        Se enviarán automáticamente todos los productos disponibles a producción.
                                    </p>

                                    <form method="POST" id="formProduccion">
                                        <input type="hidden" name="accion" value="enviar_produccion">
                                        <div class="mb-3">
                                            <h6 class="fw-bold">Productos que se enviarán a producción:</h6>
                                            <div class="border rounded p-3" style="max-height: 350px; overflow-y: auto;">
                                                <?php foreach ($productos as $producto): ?>
                                                    <?php if ($producto['max_produccion'] > 0): ?>
                                                        <div class="card producto-card mb-3">
                                                            <div class="card-body p-3">
                                                                <h6 class="text-primary">
                                                                    <?php echo htmlspecialchars($producto['descripcion']); ?>
                                                                    <?php if (esProductoEnUnidades($producto['tipoproducto']) && mb_strtolower($producto['tipoproducto'], 'UTF-8') === 'toallitas'): ?>
                                                                        <span class="producto-info-badge badge-toallitas">Toallitas - 1 caja/unidad</span>
                                                                    <?php elseif (esProductoEnUnidades($producto['tipoproducto']) && mb_strtolower($producto['tipoproducto'], 'UTF-8') !== 'toallitas'): ?>
                                                                        <span class="producto-info-badge badge-toallitas">
                                                                            <?php
                                                                            $unidadMedida = strtolower($producto['unidadmedida'] ?? 'unidad');
                                                                            if ($unidadMedida === 'caja' || $unidadMedida === 'cajas') {
                                                                                echo 'Paños - 1 caja/unidad';
                                                                            } else {
                                                                                echo 'Paños - 1 unidad/pieza';
                                                                            }
                                                                            ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="producto-info-badge badge-produccion">
                                                                            <?php echo number_format($producto['peso_por_bobina'], 2); ?> kg/bobina
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </h6>
                                                                <p class="mb-2">
                                                                    <span class="text-muted">Cantidad total:</span>
                                                                    <strong><?php echo number_format($producto['cantidad'], 2); ?></strong>
                                                                    <?php echo htmlspecialchars($producto['unidadmedida'] ?: 'UN'); ?>
                                                                </p>

                                                                <div class="alert alert-success">
                                                                    <strong>Se enviará a producción:</strong>
                                                                    <?php if (esProductoEnUnidades($producto['tipoproducto']) && mb_strtolower($producto['tipoproducto'], 'UTF-8') === 'toallitas'): ?>
                                                                        <?php echo number_format($producto['max_produccion']); ?>
                                                                        <?php echo ($producto['max_produccion'] == 1) ? 'caja' : 'cajas'; ?>
                                                                    <?php elseif (esProductoEnUnidades($producto['tipoproducto']) && mb_strtolower($producto['tipoproducto'], 'UTF-8') !== 'toallitas'): ?>
                                                                        <?php echo number_format($producto['max_produccion']); ?>
                                                                        <?php
                                                                        $unidadMedida = strtolower($producto['unidadmedida'] ?? 'unidad');
                                                                        if ($unidadMedida === 'caja' || $unidadMedida === 'cajas') {
                                                                            echo ($producto['max_produccion'] == 1) ? 'caja' : 'cajas';
                                                                        } else {
                                                                            echo ($producto['max_produccion'] == 1) ? 'unidad' : 'unidades';
                                                                        }
                                                                        ?>
                                                                    <?php else: ?>
                                                                        <?php echo number_format($producto['max_produccion']); ?>
                                                                        <?php echo ($producto['max_produccion'] == 1) ? 'bobina' : 'bobinas'; ?>
                                                                        (<?php echo number_format($producto['max_produccion'] * $producto['peso_por_bobina'], 2); ?> kg)
                                                                    <?php endif; ?>
                                                                </div>
                                                                <input type="hidden"
                                                                    name="cantidad_produccion[<?php echo $producto['id']; ?>]"
                                                                    value="<?php echo $producto['max_produccion']; ?>">
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="card producto-card mb-3" style="opacity: 0.6;">
                                                            <div class="card-body p-3">
                                                                <h6 class="text-muted">
                                                                    <?php echo htmlspecialchars($producto['descripcion']); ?>
                                                                    <span class="badge bg-secondary">No disponible</span>
                                                                </h6>
                                                                <p class="mb-0 text-muted">
                                                                    <?php if ($producto['cantidad_expedicion'] > 0): ?>
                                                                        Ya enviado completamente a expedición
                                                                    <?php else: ?>
                                                                        Sin cantidad disponible para producción
                                                                    <?php endif; ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="observaciones_produccion" class="form-label">
                                                Observaciones <span class="text-muted">(opcional)</span>
                                            </label>
                                            <textarea class="form-control" id="observaciones_produccion"
                                                name="observaciones_produccion" rows="2"
                                                placeholder="Instrucciones especiales..."></textarea>
                                        </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-industry me-2"></i>Enviar a Producción
                                </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- CREAR RESERVAS DE STOCK (sin cambios) -->
                    <div class="col-lg-3 mb-4">
                        <div class="card border-info h-100">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-shipping-fast me-2"></i>Crear Reservas de Stock
                                    <span class="badge bg-light text-dark ms-2" id="contador-productos-seleccionados">0 productos</span>
                                </h6>
                                <small>Sistema automático de paquetes - Solo ingresa bobinas necesarias</small>
                            </div>
                            <div class="card-body btn-option">
                                <div class="option-content">
                                    <div class="alert alert-info alert-sm mb-3">
                                        <i class="fas fa-lightbulb me-2"></i>
                                        <strong>¿Cómo funciona?</strong><br>
                                        • Ingresa las <strong>bobinas que necesitas</strong><br>
                                        • El sistema calcula automáticamente los <strong>paquetes necesarios</strong><br>
                                        • Se reservan los paquetes completos automáticamente
                                    </div>

                                    <form method="POST" id="formStockExpedicion">
                                        <input type="hidden" name="accion" value="enviar_expedicion_stock">
                                        <?php if (!empty($stockGeneral)): ?>
                                            <div class="mb-3">
                                                <h6 class="fw-bold text-info d-flex align-items-center">
                                                    <i class="fas fa-list-check me-2"></i>
                                                    Productos Disponibles:
                                                    <span class="badge bg-success text-white ms-2"><?php echo count($stockGeneral); ?></span>
                                                </h6>
                                                <div class="border rounded p-3" style="max-height: 500px; overflow-y: auto;">
                                                    <?php foreach ($stockGeneral as $index => $stock):
                                                        $esUnidades = esProductoEnUnidades($stock['tipo_producto']);
                                                        $bobinasPacote = (int)($stock['bobinas_pacote'] ?? 1);
                                                        $nombreProducto = $stock['nombre_producto'];
                                                        $nombreProductoSafe = htmlspecialchars($nombreProducto, ENT_QUOTES, 'UTF-8');
                                                        // CREAR ID ÚNICO PARA EVITAR PROBLEMAS CON CARACTERES ESPECIALES
                                                        $productoId = 'producto_' . md5($nombreProducto);
                                                        $tipoProducto = htmlspecialchars($stock['tipo_producto'] ?? 'N/A');
                                                        // ✅ USAR LOS CAMPOS CORRECTOS que ahora retorna obtenerStockGeneral
                                                        $paquetesDisponibles = (int)($stock['total_paquetes_disponibles'] ?? 0);
                                                        $bobinasDisponibles = (int)($stock['total_bobinas_disponibles'] ?? 0);
                                                        $bobinasPacote = (int)($stock['bobinas_pacote'] ?? 1);

                                                        // DEBUG para verificar que llegan los campos correctos
                                                        error_log("DEBUG - Campos corregidos: Producto: {$stock['nombre_producto']} | Paquetes: $paquetesDisponibles | Bobinas: $bobinasDisponibles | Bob/Paq: $bobinasPacote");
                                                        $pesoPromedio = (float)($stock['peso_promedio'] ?? 0);
                                                    ?>
                                                        <div class="stock-card mb-3" id="<?php echo $productoId; ?>">
                                                            <div class="stock-header">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <h6 class="mb-1"><?php echo $nombreProductoSafe; ?></h6>
                                                                        <span class="badge bg-light text-dark"><?php echo $tipoProducto; ?></span>
                                                                    </div>
                                                                    <div class="text-end">
                                                                        <?php if (!$esUnidades): ?>
                                                                            <small class="text-light"><?php echo $bobinasPacote; ?> bob/paquete</small>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="card-body">
                                                                <!-- Información del stock -->
                                                                <div class="row mb-3">
                                                                    <div class="col-6">
                                                                        <div class="text-center p-2 bg-light rounded">
                                                                            <div class="fw-bold text-success fs-5"><?php echo $paquetesDisponibles; ?></div>
                                                                            <small class="text-muted"><?php echo $esUnidades ? 'Unidades' : 'Paquetes'; ?></small>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <div class="text-center p-2 bg-light rounded">
                                                                            <div class="fw-bold text-primary fs-5"><?php echo $bobinasDisponibles; ?></div>
                                                                            <small class="text-muted"><?php echo $esUnidades ? 'kg Totales' : 'Bobinas'; ?></small>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <!-- Input para cantidad necesaria -->
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">
                                                                        <i class="fas fa-circle-dot me-1"></i>
                                                                        <?php echo $esUnidades ? 'Unidades Necesarias:' : 'Bobinas Necesarias:'; ?>
                                                                    </label>
                                                                    <div class="input-group">
                                                                        <!-- ✅ CAMBIO: Usar índice en lugar del nombre completo -->
                                                                        <input type="number"
                                                                            class="form-control input-bobinas bobinas-calculator"
                                                                            name="cantidad_bobinas[<?php echo $index; ?>]"
                                                                            data-nombre-producto="<?php echo $nombreProductoSafe; ?>"
                                                                            data-bobinas-por-paquete="<?php echo $bobinasPacote; ?>"
                                                                            data-es-unidades="<?php echo $esUnidades ? 'true' : 'false'; ?>"
                                                                            data-producto-index="<?php echo $index; ?>"
                                                                            data-producto-id="<?php echo $productoId; ?>"
                                                                            step="1"
                                                                            placeholder="Ingrese cantidad"
                                                                            value="">

                                                                        <!-- ✅ AGREGAR: Campo oculto con el nombre del producto -->
                                                                        <input type="hidden"
                                                                            name="nombre_producto[<?php echo $index; ?>]"
                                                                            value="<?php echo $nombreProductoSafe; ?>">

                                                                        <span class="input-group-text bg-primary text-white">
                                                                            <i class="fas fa-circle-dot me-1"></i><?php echo $esUnidades ? 'unidades' : 'bobinas'; ?>
                                                                        </span>
                                                                    </div>
                                                                    <small class="form-text text-muted">
                                                                        Máximo: <?php echo $bobinasDisponibles; ?> <?php echo $esUnidades ? 'unidades' : 'bobinas'; ?>
                                                                        <?php if (!$esUnidades): ?>
                                                                            (<?php echo $paquetesDisponibles; ?> paquetes × <?php echo $bobinasPacote; ?> bob/paq)
                                                                        <?php endif; ?>
                                                                    </small>
                                                                </div>

                                                                <!-- Información calculada (solo para productos con bobinas) -->
                                                                <?php if (!$esUnidades): ?>
                                                                    <div class="info-paquetes-container" id="info-paquetes-<?php echo $index; ?>">
                                                                        <div class="info-paquetes-header">
                                                                            <i class="fas fa-calculator me-1"></i>
                                                                            Cálculo Automático
                                                                        </div>
                                                                        <div class="row text-center">
                                                                            <div class="col-4">
                                                                                <div class="p-2 bg-white rounded">
                                                                                    <div class="fw-bold text-primary">
                                                                                        <span id="paquetes-necesarios-<?php echo $index; ?>">0</span>
                                                                                    </div>
                                                                                    <small>Paquetes</small>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-4">
                                                                                <div class="p-2 bg-white rounded">
                                                                                    <div class="fw-bold text-success">
                                                                                        <span id="bobinas-totales-<?php echo $index; ?>">0</span>
                                                                                    </div>
                                                                                    <small>Bobinas</small>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-4">
                                                                                <div class="p-2 bg-white rounded">
                                                                                    <div class="fw-bold" id="excedente-container-<?php echo $index; ?>">
                                                                                        <span id="excedente-bobinas-<?php echo $index; ?>">0</span>
                                                                                    </div>
                                                                                    <small>Excedente</small>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <!-- Mensaje de validación -->
                                                                <div class="mensaje-validacion" id="mensaje-validacion-<?php echo $index; ?>"></div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label for="observaciones_expedicion" class="form-label fw-bold">
                                                    <i class="fas fa-comment me-1"></i>
                                                    Observaciones
                                                </label>
                                                <textarea class="form-control" id="observaciones_expedicion"
                                                    name="observaciones_expedicion" rows="2"
                                                    placeholder="Notas sobre las reservas..."></textarea>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                No hay stock disponible para esta venta.
                                            </div>
                                        <?php endif; ?>
                                </div>

                                <?php if (!empty($stockGeneral)): ?>
                                    <button type="submit" class="btn btn-info w-100" id="btnCrearReservas" disabled>
                                        <i class="fas fa-shipping-fast me-2"></i>Crear Reservas Automáticas
                                        <span class="badge bg-light text-dark ms-2" id="contador-boton">0 productos</span>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-secondary w-100" disabled>
                                        <i class="fas fa-ban me-2"></i>Sin Stock Disponible
                                    </button>
                                <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- 3. FINALIZAR VENTA -->
                    <div class="col-lg-3 mb-4">
                        <div class="card border-success h-100">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="fas fa-check-double me-2"></i>Finalizar Venta</h6>
                            </div>
                            <div class="card-body btn-option">
                                <form method="POST" id="formFinalizar">
                                    <input type="hidden" name="accion" value="finalizar_venta">
                                    <div class="option-content">
                                        <p class="text-muted small mb-3">
                                            Marcar la venta como finalizada. Esta acción cambiará el estado de la venta a "Finalizado".
                                        </p>
                                        <div class="mb-3">
                                            <label for="observaciones_finalizacion" class="form-label">
                                                Observaciones de Finalización <span class="text-muted">(opcional)</span>
                                            </label>
                                            <textarea class="form-control" id="observaciones_finalizacion"
                                                name="observaciones_finalizacion"
                                                rows="3"
                                                placeholder="Comentarios sobre la finalización de la venta..."></textarea>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-check-double me-2"></i>Finalizar Venta
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- DEVOLVER A CONTABILIDAD (sin cambios) -->
                    <div class="col-lg-3 mb-4">
                        <div class="card border-warning h-100">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0"><i class="fas fa-undo me-2"></i>Devolver a Contabilidad</h6>
                            </div>
                            <div class="card-body btn-option">
                                <form method="POST" id="formDevolucion">
                                    <input type="hidden" name="accion" value="devolver">
                                    <div class="option-content">
                                        <p class="text-muted small mb-3">
                                            Devolver la venta a contabilidad para revisión o corrección.
                                            Esta acción requerirá nueva aprobación del contador.
                                        </p>
                                        <div class="mb-3">
                                            <label for="motivo_devolucion" class="form-label">
                                                Motivo de la Devolución <span class="text-danger">*</span>
                                            </label>
                                            <textarea class="form-control" id="motivo_devolucion" name="motivo_devolucion"
                                                rows="4" required placeholder="Explique el motivo de la devolución..."></textarea>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-warning w-100">
                                        <i class="fas fa-undo me-2"></i>Devolver a Contabilidad
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <a href="<?php echo $url_base; ?>secciones/sectorPcp/index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver a la Lista
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- [MODALES SIN CAMBIOS] -->
    <div class="modal fade" id="modalImagenAutorizacion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-file me-2"></i><span id="titulo-imagen-modal">Documento de Autorización</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center bg-light p-3 position-relative">
                    <div class="imagen-counter" id="imagen-counter" style="display: none;">
                        <span id="imagen-actual">1</span> / <span id="total-imagenes">1</span>
                    </div>

                    <button type="button" class="btn imagen-modal-navegacion btn-prev" id="btn-imagen-anterior" style="display: none;">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="btn imagen-modal-navegacion btn-next" id="btn-imagen-siguiente" style="display: none;">
                        <i class="fas fa-chevron-right"></i>
                    </button>

                    <h6 id="nombre-imagen-modal" class="mb-3 fw-bold text-primary"></h6>
                    <div id="imagen-autorizacion-container">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>No hay documento de autorización disponible
                        </div>
                    </div>
                    <div id="descripcion-imagen-modal" class="mt-3"></div>
                </div>
                <div class="modal-footer bg-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cerrar
                    </button>
                    <button type="button" id="btn-descargar-imagen" class="btn btn-primary" style="display: none;">
                        <i class="fas fa-download me-2"></i>Descargar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalImagenProducto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-image me-2"></i>Imagen del Producto
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center bg-light p-3">
                    <h6 id="producto-nombre" class="mb-3 fw-bold text-primary"></h6>
                    <div id="producto-imagen-container">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>No hay imagen disponible para este producto
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade modal-confirmation" id="modalConfirmacion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" id="confirmationHeader">
                    <h5 class="modal-title" id="confirmationTitle">
                        <i class="fas fa-question-circle me-2"></i>Confirmar Acción
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center" id="confirmationBody">
                    <div class="confirmation-icon" id="confirmationIcon">
                        <i class="fas fa-question-circle"></i>
                    </div>

                    <h4 id="confirmationMessage" class="mb-3">¿Está seguro que desea continuar?</h4>

                    <div class="confirmation-details" id="confirmationDetails">
                        <!-- Los detalles se cargarán dinámicamente -->
                    </div>

                    <div class="alert alert-warning" id="confirmationWarning" style="display: none;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Atención:</strong> <span id="warningText">Esta acción no se puede deshacer fácilmente.</span>
                    </div>

                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin me-2"></i>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-primary btn-confirm" id="btnConfirmar">
                        <i class="fas fa-check me-2"></i>Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- ✅ SCRIPTS SEPARADOS: Solo variables PHP y referencia al archivo externo -->
    <script>
        // ===== CONFIGURACIÓN Y DATOS DESDE PHP =====
        const PCP_CONFIG = <?php echo json_encode($configuracionJS); ?>;

        // Variables globales para imágenes y productos
        window.imagenesAutorizacionData = <?php echo json_encode($imagenesAutorizacion); ?>;
        window.imagenesProductosData = <?php echo json_encode($imagenesProductos); ?>;
        window.nombresProductosData = {};
        <?php foreach ($productos as $prod): ?>
            window.nombresProductosData[<?php echo $prod['id']; ?>] = "<?php echo addslashes(htmlspecialchars($prod['descripcion'])); ?>";
        <?php endforeach; ?>

        // ✅ DATOS DE STOCK PARA DEBUG
        window.stockGeneralData = <?php echo json_encode($stockGeneral); ?>;
        window.productosData = <?php echo json_encode(array_column($productos, 'descripcion')); ?>;
    </script>

    <!-- ✅ ARCHIVO JAVASCRIPT EXTERNO -->
    <script src="<?php echo $url_base; ?>secciones/sectorPcp/js/ver-pcp-manager.js"></script>
</body>

</html>