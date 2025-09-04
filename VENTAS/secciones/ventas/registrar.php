<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '2']);

if (file_exists("controllers/VentaController.php")) {
    include "controllers/VentaController.php";
} else {
    die("Error: No se pudo cargar el controlador de ventas.");
}

$controller = new VentaController($conexion, $url_base);

if (!$controller->verificarPermisos('crear')) {
    header("Location: " . $url_base . "secciones/ventas/index.php?error=No tienes permisos para crear ventas");
    exit();
}

$error = '';
$datos = [];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = $controller->procesarRegistro();

    if (isset($resultado['errores'])) {
        $errores = $resultado['errores'];
        $datos = $resultado['datos'] ?? [];
    } elseif (isset($resultado['error'])) {
        $error = $resultado['error'];
        $datos = $resultado['datos'] ?? [];
    }

    $controller->logActividad('Intento crear venta', 'Con errores: ' . implode(', ', $errores ?: [$error]));
}

$tipos = $controller->obtenerTiposProductos();
$tiposCredito = $controller->obtenerTiposCredito();

$datosVista = $controller->obtenerDatosVista();
$configuracionJS = $controller->obtenerConfiguracionJS();

$titulo = 'Registrar Nueva Venta';
$url_base = $datosVista['url_base'];
$usuario_actual = $datosVista['usuario_actual'];

$controller->logActividad('Acceso a formulario registro venta');
$breadcrumb_items = ['Sector Ventas', 'Registrar Ventas'];
$item_urls = [$url_base . 'secciones/ventas/main.php'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titulo); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="<?php echo $url_base; ?>secciones/ventas/utils/styles.css" rel="stylesheet" />
    <style>
        .currency-input-decimal {
            text-align: right;
        }

        .producto-precio {
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .producto-precio:focus {
            border-color: #4a6cf7;
            box-shadow: 0 0 0 0.2rem rgba(74, 108, 247, 0.25);
        }

        .moneda-simbolo {
            font-weight: 600;
            color: #495057;
            background-color: #e9ecef;
        }

        .iva-switch {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 2px;
            max-width: 250px;
            margin-bottom: 2px;
        }

        .iva-switch .form-check {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .iva-switch .form-check-input {
            width: 3rem;
            height: 1.5rem;
            border-radius: 1rem;
            background-color: rgba(255, 255, 255, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
        }

        .iva-switch .form-check-input:checked {
            background-color: #28a745;
            border-color: #28a745;
        }

        .iva-switch .form-check-label {
            color: white;
            font-weight: 500;
            font-size: 1.1rem;
            margin: 0;
        }

        #productos-container {
            max-height: 700px;
            overflow-y: auto;
            padding-bottom: 20px;
        }

        .mensaje-duplicado-shake {
            animation: shake 0.6s ease-in-out;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
        }

        .producto-existente-highlight {
            animation: highlight 2s ease-in-out;
            border: 2px solid #ffc107 !important;
            border-radius: 8px;
        }

        @keyframes highlight {
            0% {
                background-color: transparent;
            }

            50% {
                background-color: rgba(255, 193, 7, 0.1);
            }

            100% {
                background-color: transparent;
            }
        }

        .credito-info {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
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

        .iva-explanation {
            font-size: 0.9rem;
            background-color: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }

        .bobinas-container {
            background: linear-gradient(135deg, #17a2b8, #20c997);
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
            max-width: 50%;
            box-shadow: 0 3px 10px rgba(23, 162, 184, 0.2)
        }

        .bobinas-info {
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .bobinas-calc {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        }

        .bobinas-result {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .bobinas-formula {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .bobinas-icon {
            font-size: 1.5rem;
            margin-right: 8px;
        }

        @media (max-width: 768px) {
            .bobinas-info {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Nuevo Presupuesto/Venta</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($errores)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Se encontraron los siguientes errores:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errores as $errorItem): ?>
                                <li><?php echo htmlspecialchars($errorItem); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form id="formPresupuesto" method="POST" action="">

                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información General</h5>
                            <div class="iva-switch">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="aplicar_iva" name="aplicar_iva" value="1"
                                        <?php echo (isset($datos['aplicar_iva']) && $datos['aplicar_iva'] === '1') ? 'checked' : 'checked'; ?>>
                                    <label class="form-check-label" for="aplicar_iva">
                                        <i class="fas fa-percentage me-2"></i>IVA Incluido (10%)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="selectCliente" class="form-label">Cliente</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <select class="form-select" id="selectCliente" name="cliente" required>
                                            <option value="">Buscar cliente...</option>
                                            <?php if (!empty($datos['cliente'])): ?>
                                                <option value="<?php echo htmlspecialchars($datos['cliente']); ?>" selected>
                                                    <?php echo htmlspecialchars($datos['cliente']); ?>
                                                </option>
                                            <?php endif; ?>
                                        </select>
                                        <a href="<?php echo $url_base; ?>secciones/ventas/registrarcliente.php"
                                            class="btn btn-outline-success"
                                            id="btnNuevoCliente"
                                            title="Agregar nuevo cliente">
                                            <i class="fas fa-user-plus"></i>
                                        </a>
                                    </div>
                                    <div class="form-text" id="clienteInfo"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="fecha_venta" class="form-label">Fecha de Venta</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                        <input type="date" class="form-control" id="fecha_venta" name="fecha_venta"
                                            value="<?php echo htmlspecialchars($datos['fecha_venta'] ?? date('Y-m-d')); ?>" required>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="tipoflete" class="form-label">Tipo de Flete</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-truck"></i></span>
                                        <select class="form-select" id="tipoflete" name="tipoflete" required>
                                            <option value="">Seleccione...</option>
                                            <option value="FOB" <?php echo (($datos['tipoflete'] ?? '') === 'FOB') ? 'selected' : ''; ?>>FOB</option>
                                            <option value="CIF" <?php echo (($datos['tipoflete'] ?? '') === 'CIF') ? 'selected' : ''; ?>>CIF</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="moneda" class="form-label">Moneda</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-coins"></i></span>
                                        <select class="form-select" id="moneda" name="moneda" required>
                                            <option value="">Seleccione...</option>
                                            <option value="Guaraníes" <?php echo (($datos['moneda'] ?? '') === 'Guaraníes') ? 'selected' : ''; ?>>Guaraníes</option>
                                            <option value="Dólares" <?php echo (($datos['moneda'] ?? '') === 'Dólares') ? 'selected' : ''; ?>>Dólares</option>
                                            <option value="Real brasileño" <?php echo (($datos['moneda'] ?? '') === 'Real brasileño') ? 'selected' : ''; ?>>Real brasileño</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="cond_pago" class="form-label">Condición de Pago</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-money-check-alt"></i></span>
                                        <select class="form-select" id="cond_pago" name="cond_pago" required>
                                            <option value="">Seleccione...</option>
                                            <option value="Contado" <?php echo (($datos['cond_pago'] ?? '') === 'Contado') ? 'selected' : ''; ?>>Contado</option>
                                            <option value="Crédito" <?php echo (($datos['cond_pago'] ?? '') === 'Crédito') ? 'selected' : ''; ?>>Crédito</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="tipo_pago" class="form-label">Tipo de Pago</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-credit-card"></i></span>
                                        <select class="form-select" id="tipo_pago" name="tipo_pago" required>
                                            <option value="">Seleccione...</option>
                                            <option value="Efectivo" <?php echo (($datos['tipo_pago'] ?? '') === 'Efectivo') ? 'selected' : ''; ?>>Efectivo</option>
                                            <option value="Transferencia" <?php echo (($datos['tipo_pago'] ?? '') === 'Transferencia') ? 'selected' : ''; ?>>Transferencia</option>
                                            <option value="Cheque" <?php echo (($datos['tipo_pago'] ?? '') === 'Cheque') ? 'selected' : ''; ?>>Cheque</option>
                                            <option value="Boleto Bancario" <?php echo (($datos['tipo_pago'] ?? '') === 'Boleto Bancario') ? 'selected' : ''; ?>>Boleto Bancario</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="descripcion" class="form-label">Informacion Adicional (Opcional)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"
                                            placeholder="Descripción general del presupuesto..." maxlength="200"><?php echo htmlspecialchars($datos['descripcion'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="campos-credito" style="display: <?php echo (($datos['cond_pago'] ?? '') === 'Crédito') ? 'block' : 'none'; ?>;">
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Información de Crédito</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="tipocredito" class="form-label">Tipo de Crédito</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-calendar-days"></i></span>
                                            <select class="form-select" id="tipocredito" name="tipocredito">
                                                <option value="">Seleccionar tipo de crédito...</option>
                                                <?php foreach ($tiposCredito as $credito): ?>
                                                    <option value="<?php echo htmlspecialchars($credito['descripcion']); ?>"
                                                        <?php echo (($datos['tipocredito'] ?? '') === $credito['descripcion']) ? 'selected' : ''; ?>>
                                                        Crédito a <?php echo htmlspecialchars($credito['descripcion']); ?> días
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div id="info-credito" style="display: none;">
                                    <div class="credito-info">
                                        <h6><i class="fas fa-calendar-check me-2"></i>Resumen del Crédito</h6>
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <span class="badge me-2">Modalidad:</span>
                                                <span class="credito-tipo-display" id="credito-tipo-display">-</span>
                                            </div>
                                            <div class="col-md-6 text-end">
                                                <span class="badge me-2">Monto Total:</span>
                                                <span class="credito-tipo-display" id="credito-total-display">₲ 0</span>
                                            </div>
                                        </div>
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
                            <div class="productos-selection-section mb-4">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="mb-2">
                                            <i class="fas fa-th-list me-2 text-success"></i>
                                            Selección Rápida de Productos
                                        </h6>
                                        <p class="text-muted mb-0 small">
                                            Agregue múltiples productos de una vez desde el catálogo completo
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <button type="button" class="btn btn-success btn-lg" id="btnSeleccionMultiple">
                                            <i class="fas fa-th-list me-2"></i>Seleccionar Productos
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="productos-container" class="product-container mb-4">
                            </div>
                        </div>

                        <div id="mensaje-duplicado" class="alert alert-warning alert-dismissible fade" role="alert" style="display: none; margin-top: 15px;">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="alert-heading mb-1">
                                        <i class="fas fa-copy me-2"></i>Producto Duplicado
                                    </h5>
                                    <p class="mb-0">
                                        Este producto ya está agregado en la lista. Si desea modificar la cantidad o precio,
                                        puede editarlo directamente en el producto existente.
                                    </p>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Resumen</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="alert alert-info mb-0">
                                        <strong><i class="fas fa-money-bill me-1"></i><span id="subtotal-label">Subtotal (sin IVA):</span></strong>
                                        <span id="subtotal-valor">0,00</span> <span id="moneda-simbolo">₲</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-success mb-0">
                                        <strong><i class="fas fa-hand-holding-usd me-1"></i><span id="total-label">Total con IVA (10%):</span></strong>
                                        <span id="total-con-iva-valor">0,00</span> <span id="total-con-iva-simbolo">₲</span>
                                    </div>
                                </div>
                            </div>
                            <div id="iva-explanation" class="alert iva-explanation mt-2" style="display: none;">
                                <small><i class="fas fa-info-circle me-2"></i>
                                    Los precios ingresados NO incluyen IVA. El subtotal y total son iguales.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="<?php echo $url_base; ?>secciones/ventas/main.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="imagenProductoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
            <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); overflow: hidden;">
                <div class="modal-header" style="border-bottom: none; padding: 20px 24px; background-color: #f8f9fa;">
                    <h5 class="modal-title" style="font-weight: 500; font-size: 18px; color: #222;">
                        <i class="fas fa-image me-2" style="color: #4a6cf7;"></i><span id="modal-producto-titulo">Imagen del Producto</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center" style="padding: 0;">
                    <div style="background-color: #fafafa; position: relative; min-height: 280px; display: flex; align-items: center; justify-content: center; padding: 15px;">
                        <img id="modal-producto-imagen" src="" alt="Imagen del producto" class="img-fluid" style="max-height: 450px; max-width: 100%; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.08);">
                    </div>
                </div>
                <div class="modal-footer" style="border-top: none; padding: 16px 24px; background-color: #f8f9fa;">
                    <button type="button" class="btn" data-bs-dismiss="modal" style="background-color: #4a6cf7; color: white; border-radius: 8px; padding: 10px 20px;">
                        <i class="fas fa-times me-2"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="<?php echo $url_base; ?>secciones/ventas/js/ventas.js"></script>

    <script>
        $(document).ready(function() {
            const configuracionJS = <?php echo json_encode($configuracionJS); ?>;
            inicializarVentas(configuracionJS);
            console.log('Página registrar.php inicializada correctamente');
        });
    </script>
</body>

</html>