<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();

function formatearNumero($numero, $decimales = 4)
{
    $formateado = number_format((float)$numero, $decimales, ',', '.');
    $formateado = rtrim($formateado, '0');
    $formateado = rtrim($formateado, ',');
    return $formateado;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: " . $url_base . "secciones/ventas/index.php?error=ID de presupuesto no válido");
    exit();
}

$id = $_GET['id'];

try {
    $query = "SELECT * FROM public.sist_ventas_presupuesto WHERE id = :id";
    $stmt = $conexion->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        header("Location: index.php?error=Presupuesto no encontrado");
        exit();
    }

    $presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);

    $query_productos = "SELECT * FROM public.sist_ventas_pres_product WHERE id_presupuesto = :id_presupuesto";
    $stmt_productos = $conexion->prepare($query_productos);
    $stmt_productos->bindParam(':id_presupuesto', $id, PDO::PARAM_INT);
    $stmt_productos->execute();
    $productos_presupuesto = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    header("Location: index.php?error=" . urlencode("Error al consultar el presupuesto: " . $e->getMessage()));
    exit();
}

$subtotal = (float)$presupuesto['subtotal'];
$porcentajeIva = (float)$presupuesto['iva'];
$montoIva = $subtotal * ($porcentajeIva / 100);
$totalConIva = round($subtotal + $montoIva, 2);
$montoIva = round($subtotal * ($porcentajeIva / 100), 2);
$totalConIva = $subtotal + $montoIva;

if ($presupuesto['moneda'] === 'Dólares') {
    $simboloMoneda = 'USD';
} elseif ($presupuesto['moneda'] === 'Real brasileño') {
    $simboloMoneda = 'R$';
} else {
    $simboloMoneda = '₲';
}
$breadcrumb_items = ['Sector Ventas', 'Listado Ventas', 'Detalles de Venta'];
$item_urls = [
    $url_base . 'secciones/ventas/main.php',
    $url_base . 'secciones/ventas/index.php'
];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Presupuesto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="<?php echo $url_base; ?>secciones/ventas/utils/styles.css" rel="stylesheet" />
    <style>
        .credito-info {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .credito-info h6 {
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .credito-info .badge {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            font-size: 0.9em;
            padding: 6px 12px;
            border-radius: 20px;
        }

        .credito-tipo-display {
            font-size: 1.2rem;
            font-weight: 600;
            color: #fff3cd;
        }
    </style>
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Detalles del Presupuesto/Venta</h4>
                <a href="<?php echo $url_base; ?>secciones/ventas/index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Volver a la Lista
                </a>
            </div>
            <div class="card-body">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información General</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-hashtag me-2"></i><strong>Código Venta:</strong></span>
                                        <span class="badge bg-primary rounded-pill"><?php echo htmlspecialchars($presupuesto['id']); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-user me-2"></i><strong>Cliente:</strong></span>
                                        <span><?php echo htmlspecialchars($presupuesto['cliente']); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-calendar-alt me-2"></i><strong>Fecha de Venta:</strong></span>
                                        <span><?php echo date('d/m/Y', strtotime($presupuesto['fecha_venta'])); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-truck me-2"></i><strong>Tipo de Flete:</strong></span>
                                        <span><?php echo htmlspecialchars($presupuesto['tipoflete']); ?></span>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-coins me-2"></i><strong>Moneda:</strong></span>
                                        <span><?php echo htmlspecialchars($presupuesto['moneda']); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-money-check-alt me-2"></i><strong>Condición de Pago:</strong></span>
                                        <span>
                                            <?php if ($presupuesto['es_credito']): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-credit-card me-1"></i>Crédito
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-money-bill me-1"></i>Contado
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-credit-card me-2"></i><strong>Tipo de Pago:</strong></span>
                                        <span><?php echo htmlspecialchars($presupuesto['tipo_pago']); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-truck me-2"></i><strong>Empresa Fletera:</strong></span>
                                        <span><?php echo (isset($presupuesto['transportadora']) && $presupuesto['transportadora'] !== '') ? htmlspecialchars($presupuesto['transportadora']) : "No asignada"; ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <?php if (!empty($presupuesto['descripcion'])): ?>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <strong><i class="fas fa-align-left me-2"></i>Descripción:</strong>
                                        <?php echo htmlspecialchars($presupuesto['descripcion']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($presupuesto['es_credito']): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Información de Crédito</h5>
                        </div>
                        <div class="card-body">
                            <div class="credito-info">
                                <h6><i class="fas fa-calendar-check me-2"></i>Resumen del Crédito</h6>
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <span class="badge me-2">Modalidad:</span>
                                        <span class="credito-tipo-display">
                                            <?php if (!empty($presupuesto['tipocredito'])): ?>
                                                Crédito a <?php echo htmlspecialchars($presupuesto['tipocredito']); ?> días
                                            <?php else: ?>
                                                Crédito simple
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <span class="badge me-2">Monto Total:</span>
                                        <span class="credito-tipo-display">
                                            <?php echo $simboloMoneda . ' ' . formatearNumero($presupuesto['monto_total']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Resumen Financiero</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="alert alert-info mb-0">
                                    <strong><i class="fas fa-money-bill me-1"></i>Subtotal:</strong>
                                    <span class="float-end"><?php echo $simboloMoneda . ' ' . formatearNumero($subtotal); ?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-warning mb-0">
                                    <strong><i class="fas fa-percentage me-1"></i>IVA (<?php echo $porcentajeIva; ?>%):</strong>
                                    <span class="float-end"><?php echo $simboloMoneda . ' ' . formatearNumero($montoIva, 2); ?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-success mb-0">
                                    <strong><i class="fas fa-hand-holding-usd me-1"></i>Total <?php echo ($porcentajeIva > 0) ? 'con IVA' : ''; ?>:</strong>
                                    <span class="float-end"><?php echo $simboloMoneda . ' ' . formatearNumero($totalConIva); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Productos</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($productos_presupuesto)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th><i class="fas fa-hashtag me-1"></i>#</th>
                                            <th><i class="fas fa-box me-1"></i>Descripción</th>
                                            <th><i class="fas fa-layer-group me-1"></i>Tipo</th>
                                            <th><i class="fas fa-ruler me-1"></i>Unidad</th>
                                            <th><i class="fas fa-hashtag me-1"></i>NCM</th>
                                            <th class="text-end"><i class="fas fa-balance-scale me-1"></i>Cantidad</th>
                                            <th class="text-end"><i class="fas fa-tag me-1"></i>Precio Unitario</th>
                                            <th class="text-end"><i class="fas fa-calculator me-1"></i>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($productos_presupuesto as $index => $producto): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($producto['descripcion']); ?></td>
                                                <td>
                                                    <?php if (!empty($producto['tipoproducto'])): ?>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($producto['tipoproducto']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($producto['unidadmedida']); ?></td>
                                                <td><?php echo htmlspecialchars($producto['ncm']); ?></td>
                                                <td class="text-end"><?php echo formatearNumero($producto['cantidad']); ?></td>
                                                <td class="text-end"><?php echo $simboloMoneda . ' ' . formatearNumero($producto['precio']); ?></td>
                                                <td class="text-end"><?php echo $simboloMoneda . ' ' . formatearNumero((float)$producto['cantidad'] * (float)$producto['precio']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-dark">
                                        <tr>
                                            <td colspan="7" class="text-end"><strong>Subtotal:</strong></td>
                                            <td class="text-end"><strong><?php echo $simboloMoneda . ' ' . formatearNumero($subtotal); ?></strong></td>
                                        </tr>
                                        <?php if ($porcentajeIva > 0): ?>
                                            <tr>
                                                <td colspan="7" class="text-end"><strong>IVA (<?php echo $porcentajeIva; ?>%):</strong></td>
                                                <td class="text-end"><strong><?php echo $simboloMoneda . ' ' . formatearNumero($montoIva, 2); ?></strong></td>
                                            </tr>
                                            <tr class="table-success">
                                                <td colspan="7" class="text-end"><strong>TOTAL CON IVA:</strong></td>
                                                <td class="text-end"><strong><?php echo $simboloMoneda . ' ' . formatearNumero($totalConIva); ?></strong></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tfoot>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>No hay productos asociados a este presupuesto.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style media="print">
        .btn,
        .navbar,
        .card-header .btn {
            display: none !important;
        }

        body {
            font-size: 12px;
        }

        .card {
            border: none;
            box-shadow: none;
        }

        .table {
            font-size: 11px;
        }
    </style>
</body>

</html>