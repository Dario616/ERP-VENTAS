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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: " . $url_base . "secciones/ventas/index.php?error=ID de presupuesto no válido");
    exit();
}

$idPresupuesto = $_GET['id'];

if (!$controller->verificarPermisos('editar', $idPresupuesto)) {
    header("Location: " . $url_base . "secciones/ventas/index.php?error=No tienes permisos para editar esta venta");
    exit();
}

$error = '';
$datos = [];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = $controller->procesarEdicion($idPresupuesto);

    if (isset($resultado['errores'])) {
        $errores = $resultado['errores'];
        $datos = $resultado['datos'] ?? [];
    } elseif (isset($resultado['error'])) {
        $error = $resultado['error'];
        $datos = $resultado['datos'] ?? [];
    }

    $controller->logActividad('Intento editar venta', 'ID: ' . $idPresupuesto . ' - Errores: ' . implode(', ', $errores ?: [$error]));
}

try {
    $presupuesto = $controller->obtenerVentaParaEdicion($idPresupuesto);
    $productos_presupuesto = $controller->obtenerProductosVenta($idPresupuesto);
} catch (Exception $e) {
    header("Location: " . $url_base . "secciones/ventas/index.php?error=" . urlencode($e->getMessage()));
    exit();
}

$tipos = $controller->obtenerTiposProductos();
$tiposCredito = $controller->obtenerTiposCredito();

foreach ($productos_presupuesto as &$producto) {
    if (!empty($producto['id_producto'])) {
        try {
            $query_producto_info = "SELECT id, base64img, tipoimg FROM public.sist_ventas_productos WHERE id = :id_producto LIMIT 1";
            $stmt_producto_info = $conexion->prepare($query_producto_info);
            $stmt_producto_info->bindParam(':id_producto', $producto['id_producto'], PDO::PARAM_INT);
            $stmt_producto_info->execute();

            if ($stmt_producto_info->rowCount() > 0) {
                $producto_info = $stmt_producto_info->fetch(PDO::FETCH_ASSOC);
                $producto['base64img'] = $producto_info['base64img'];
                $producto['tipoimg'] = $producto_info['tipoimg'];

                $query_unidades = "SELECT \"desc\" FROM public.sist_ventas_um WHERE id_producto = :id_producto";
                $stmt_unidades = $conexion->prepare($query_unidades);
                $stmt_unidades->bindParam(':id_producto', $producto['id_producto'], PDO::PARAM_INT);
                $stmt_unidades->execute();

                $unidades_medida = [];
                while ($row = $stmt_unidades->fetch(PDO::FETCH_ASSOC)) {
                    $unidades_medida[] = $row['desc'];
                }
                $producto['unidades_medida'] = $unidades_medida;
            } else {
                $producto['base64img'] = null;
                $producto['tipoimg'] = null;
                $producto['unidades_medida'] = [];
            }
        } catch (Exception $e) {
            error_log("Error obteniendo información del producto: " . $e->getMessage());
            $producto['base64img'] = null;
            $producto['tipoimg'] = null;
            $producto['unidades_medida'] = [];
        }
    } else {
        $producto['base64img'] = null;
        $producto['tipoimg'] = null;
        $producto['unidades_medida'] = [];
    }
}

$idCliente = null;
try {
    $query_buscar_cliente = "SELECT id FROM public.sist_ventas_clientes WHERE nombre = :nombre LIMIT 1";
    $stmt_buscar_cliente = $conexion->prepare($query_buscar_cliente);
    $stmt_buscar_cliente->bindParam(':nombre', $presupuesto['cliente'], PDO::PARAM_STR);
    $stmt_buscar_cliente->execute();

    if ($stmt_buscar_cliente->rowCount() > 0) {
        $idCliente = $stmt_buscar_cliente->fetchColumn();
    }
} catch (Exception $e) {
    error_log("Error buscando cliente: " . $e->getMessage());
}

$datosVista = $controller->obtenerDatosVista();
$configuracionJS = $controller->obtenerConfiguracionJS();

$titulo = 'Editar Presupuesto/Venta';
$url_base = $datosVista['url_base'];
$usuario_actual = $datosVista['usuario_actual'];

$controller->logActividad('Acceso a edición de venta', 'ID: ' . $idPresupuesto);
$breadcrumb_items = ['Sector Ventas', 'Listado Ventas', 'Editar Venta'];
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
    <title><?php echo htmlspecialchars($titulo); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="<?php echo $url_base; ?>secciones/ventas/utils/editar.css" rel="stylesheet" />
    <style>
    </style>
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Editar Presupuesto/Venta #<?php echo $idPresupuesto; ?></h4>
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

                <form id="formPresupuesto" method="POST" action="editar.php?id=<?php echo $idPresupuesto; ?>">

                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información General</h5>
                            <div class="iva-switch">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="aplicar_iva" name="aplicar_iva" value="1"
                                        <?php echo ($presupuesto['iva'] > 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="aplicar_iva">
                                        <i class="fas fa-percentage me-2"></i>IVA Incluido (10%)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-6">
                                    <label for="selectCliente" class="form-label">Cliente</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <select class="form-select" id="selectCliente" name="cliente" required>
                                            <?php if ($idCliente): ?>
                                                <option value="<?php echo $idCliente; ?>" selected><?php echo htmlspecialchars($presupuesto['cliente']); ?></option>
                                            <?php else: ?>
                                                <option value="">Buscar cliente...</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="form-text" id="clienteInfo"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="fecha_venta" class="form-label">Fecha de Venta</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                        <input type="date" class="form-control" id="fecha_venta" name="fecha_venta"
                                            value="<?php echo $presupuesto['fecha_venta']; ?>" required>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="tipoflete" class="form-label">Tipo de Flete</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-truck"></i></span>
                                        <select class="form-select" id="tipoflete" name="tipoflete" required>
                                            <option value="">Seleccione...</option>
                                            <option value="FOB" <?php echo ($presupuesto['tipoflete'] === 'FOB') ? 'selected' : ''; ?>>FOB</option>
                                            <option value="CIF" <?php echo ($presupuesto['tipoflete'] === 'CIF') ? 'selected' : ''; ?>>CIF</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="moneda" class="form-label">Moneda</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-coins"></i></span>
                                        <select class="form-select" id="moneda" name="moneda" required>
                                            <option value="">Seleccione...</option>
                                            <option value="Guaraníes" <?php echo ($presupuesto['moneda'] === 'Guaraníes') ? 'selected' : ''; ?>>Guaraníes</option>
                                            <option value="Dólares" <?php echo ($presupuesto['moneda'] === 'Dólares') ? 'selected' : ''; ?>>Dólares</option>
                                            <option value="Real brasileño" <?php echo ($presupuesto['moneda'] === 'Real brasileño') ? 'selected' : ''; ?>>Real brasileño</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="cond_pago" class="form-label">Condición de Pago</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-money-check-alt"></i></span>
                                        <select class="form-select" id="cond_pago" name="cond_pago" required>
                                            <option value="">Seleccione...</option>
                                            <option value="Contado" <?php echo ($presupuesto['cond_pago'] === 'Contado') ? 'selected' : ''; ?>>Contado</option>
                                            <option value="Crédito" <?php echo ($presupuesto['cond_pago'] === 'Crédito') ? 'selected' : ''; ?>>Crédito</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="tipo_pago" class="form-label">Tipo de Pago</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-credit-card"></i></span>
                                        <select class="form-select" id="tipo_pago" name="tipo_pago" required>
                                            <option value="">Seleccione...</option>
                                            <option value="Efectivo" <?php echo ($presupuesto['tipo_pago'] === 'Efectivo') ? 'selected' : ''; ?>>Efectivo</option>
                                            <option value="Transferencia" <?php echo ($presupuesto['tipo_pago'] === 'Transferencia') ? 'selected' : ''; ?>>Transferencia</option>
                                            <option value="Cheque" <?php echo ($presupuesto['tipo_pago'] === 'Cheque') ? 'selected' : ''; ?>>Cheque</option>
                                            <option value="Boleto Bancario" <?php echo ($presupuesto['tipo_pago'] === 'Boleto Bancario') ? 'selected' : ''; ?>>Boleto Bancario</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label for="descripcion" class="form-label">Informacion Adicional (Opcional)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"
                                            placeholder="Descripción general del presupuesto..." maxlength="200"><?php echo htmlspecialchars($presupuesto['descripcion'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="campos-credito" style="display: <?php echo ($presupuesto['es_credito']) ? 'block' : 'none'; ?>;">
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
                                                        <?php echo ($presupuesto['tipocredito'] === $credito['descripcion']) ? 'selected' : ''; ?>>
                                                        Crédito a <?php echo htmlspecialchars($credito['descripcion']); ?> días
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div id="info-credito" style="display: <?php echo ($presupuesto['es_credito'] && $presupuesto['tipocredito']) ? 'block' : 'none'; ?>;">
                                    <div class="credito-info">
                                        <h6><i class="fas fa-calendar-check me-2"></i>Resumen del Crédito</h6>
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <span class="badge me-2">Modalidad:</span>
                                                <span class="credito-tipo-display" id="credito-tipo-display">
                                                    <?php echo $presupuesto['tipocredito'] ? 'Crédito a ' . htmlspecialchars($presupuesto['tipocredito']) . ' días' : '-'; ?>
                                                </span>
                                            </div>
                                            <div class="col-md-6 text-end">
                                                <span class="badge me-2">Monto Total:</span>
                                                <span class="credito-tipo-display" id="credito-total-display">
                                                    <?php
                                                    $simboloMoneda = match ($presupuesto['moneda']) {
                                                        'Dólares' => 'USD',
                                                        'Real brasileño' => 'R$',
                                                        default => '₲'
                                                    };
                                                    echo $simboloMoneda . ' ' . number_format((float)$presupuesto['monto_total'], 2, ',', '.');
                                                    ?>
                                                </span>
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
                                        <span id="subtotal-valor"><?php echo number_format((float)$presupuesto['subtotal'], 2, ',', '.'); ?></span>
                                        <span id="moneda-simbolo"><?php echo $simboloMoneda; ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-success mb-0">
                                        <strong><i class="fas fa-hand-holding-usd me-1"></i><span id="total-label">
                                                <?php echo ($presupuesto['iva'] > 0) ? 'Total con IVA (10%):' : 'Total:'; ?>
                                            </span></strong>
                                        <span id="total-con-iva-valor"><?php echo number_format((float)$presupuesto['monto_total'], 2, ',', '.'); ?></span>
                                        <span id="total-con-iva-simbolo"><?php echo $simboloMoneda; ?></span>
                                    </div>
                                </div>
                            </div>

                            <div id="iva-explanation" class="alert iva-explanation mt-2" style="display: <?php echo ($presupuesto['iva'] > 0) ? 'none' : 'block'; ?>;">
                                <small><i class="fas fa-info-circle me-2"></i>
                                    Los precios ingresados NO incluyen IVA. El subtotal y total son iguales.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="<?php echo $url_base; ?>secciones/ventas/index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Actualizar Presupuesto
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

            function cargarProductosExistentes() {
                console.log('🔄 Cargando productos existentes en EDITAR - VERSIÓN LIMPIA...');

                <?php if (!empty($productos_presupuesto)): ?>
                    const productosExistentes = <?php echo json_encode($productos_presupuesto); ?>;

                    productosExistentes.forEach(function(producto, index) {
                        const productoParaFormulario = {
                            id_producto: producto.id_producto,
                            id: producto.id_producto,
                            descripcion: producto.descripcion,
                            ncm: producto.ncm || '',
                            tipo_producto: producto.tipoproducto || '',
                            unidades_medida: producto.unidades_medida || [],
                            base64img: producto.base64img || '',
                            tipoimg: producto.tipoimg || ''
                        };

                        agregarProductoAlFormulario(
                            productoParaFormulario,
                            producto.unidadmedida,
                            producto.cantidad,
                            producto.precio,
                            producto.tipoproducto
                        );
                    });

                    console.log('✅ Productos existentes cargados correctamente - SIN banner verde');
                <?php endif; ?>
            }

            function agregarProductoAlFormulario(producto, unidadMedidaSeleccionada, cantidad, precio, tipoProducto) {
                const indiceActual = contadorProductos;
                contadorProductos++;

                const productoUnico = {
                    id: parseInt(producto.id_producto || producto.id),
                    descripcion: String(producto.descripcion),
                    ncm: String(producto.ncm || ''),
                    tipo_producto: String(tipoProducto || ''),
                    unidades_medida: Array.isArray(producto.unidades_medida) ?
                        producto.unidades_medida.map(um => String(um)) : [],
                    base64img: String(producto.base64img || ''),
                    tipoimg: String(producto.tipoimg || '')
                };

                productosData[indiceActual] = productoUnico;

                const htmlProducto = createProductHTML(indiceActual, productoUnico);
                $('#productos-container').append(htmlProducto);

                const productoRow = $(`.product-row[data-index="${indiceActual}"]`);

                if (unidadMedidaSeleccionada) {
                    productoRow.find('select[name*="[unidad_medida]"]').val(unidadMedidaSeleccionada);
                }
                if (cantidad) {
                    productoRow.find('.producto-cantidad').val(cantidad);
                }
                if (precio) {
                    productoRow.find('.producto-precio').val(precio);
                }
                <?php if (!empty($productos_presupuesto)): ?>
                    const productosExistentes = <?php echo json_encode($productos_presupuesto); ?>;
                    const productoActual = productosExistentes.find(p => p.id_producto == productoUnico.id);
                    if (productoActual && productoActual.instruccion) {
                        productoRow.find('textarea[name*="[instruccion]"]').val(productoActual.instruccion);
                    }
                <?php endif; ?>

                setTimeout(function() {
                    console.log('🔧 Configurando producto existente:', productoUnico.descripcion);
                    console.log('   - Tipo:', tipoProducto);
                    console.log('   - Unidad:', unidadMedidaSeleccionada);

                    const puedeUsarBobinas = puedeUsarCargaPorBobinas(tipoProducto, unidadMedidaSeleccionada);
                    console.log('   - Puede usar bobinas:', puedeUsarBobinas);

                    if (puedeUsarBobinas) {
                        mostrarOpcionCargaPorBobinas(productoRow);

                        productoRow.find('.carga-por-bobinas-switch').prop('checked', false);
                        productoRow.find('.carga-bobinas-inputs').hide();

                        mostrarEquivalenciaBobinas(productoRow);
                    } else {
                        ocultarOpcionCargaPorBobinas(productoRow);
                        ocultarEquivalenciaBobinas(productoRow);
                    }

                    calcularTotalProducto(productoRow);

                }, 100);
            }

            cargarProductosExistentes();

            console.log('🚀 Página editar.php inicializada correctamente - VERSIÓN LIMPIA SIN banner verde');
        });
    </script>
</body>

</html>