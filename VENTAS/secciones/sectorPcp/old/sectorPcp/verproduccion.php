<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '4']); // Administrador y Producción

// Establecer la zona horaria de Paraguay
date_default_timezone_set('America/Asuncion');

$idVenta = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verificar que el ID de venta sea válido
if ($idVenta <= 0) {
    header("Location: " . $url_base . "secciones/sectorPcp/produccion.php?error=ID de venta inválido");
    exit();
}

// Verificar que existe el directorio y archivo del controller
if (file_exists("controllers/produccionController.php")) {
    include "controllers/produccionController.php";
} else {
    die("Error: No se pudo cargar el controlador de Producción.");
}

// Instanciar el controller
$controller = new ProduccionController($conexion, $url_base);

// Verificar permisos
if (!$controller->verificarPermisos('procesar', $idVenta)) {
    header("Location: " . $url_base . "secciones/sectorPcp/produccion.php?error=No tienes permisos para procesar esta venta");
    exit();
}

$error = '';
$resultadoExito = null; // Variable para almacenar el resultado exitoso

// Procesar formulario si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $resultado = $controller->procesarFormularioProduccion($idVenta, $_POST);

    if (isset($resultado['error'])) {
        $error = $resultado['error'];
    } elseif (isset($resultado['success']) && $resultado['success']) {
        // Almacenar resultado exitoso para mostrar modal
        $resultadoExito = $resultado;
    }
}

// Obtener información completa de la venta
try {
    $controller->validarVentaParaProcesamiento($idVenta);
    $datosVenta = $controller->obtenerVentaParaProcesamiento($idVenta);

    $venta = $datosVenta['venta'];
    $productosProduccion = $datosVenta['productos_produccion'];
    $productosCompletados = $datosVenta['productos_completados'];
} catch (Exception $e) {
    header("Location: " . $url_base . "secciones/sectorPcp/produccion.php?error=" . urlencode($e->getMessage()));
    exit();
}

// Obtener datos para la vista
$datosVista = $controller->obtenerDatosVista('procesar_venta');
$configuracionJS = $controller->obtenerConfiguracionJS();
$mensajes = $controller->manejarMensajes();

// Log de actividad
$controller->logActividad('Acceso procesamiento venta producción', 'ID: ' . $idVenta);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesar Producción - Venta #<?php echo $idVenta; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Estilos personalizados -->
    <link href="<?php echo $url_base; ?>secciones/sectorPcp/utils/styles.css" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .producto-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .producto-card:hover {
            background-color: #e9ecef;
        }

        .producto-card.selected {
            background-color: #d1e7dd;
            border-color: #0d6efd;
        }

        .tipo-producto-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 2;
        }

        .card-body {
            position: relative;
        }

        .bg-purple {
            background-color: #6f42c1 !important;
        }

        .bg-orange {
            background-color: #fd7e14 !important;
        }

        /* Simplificado - solo los tipos principales */
        .tipo-tnt {
            background-color: #0d6efd;
        }

        .tipo-spunlace {
            background-color: #6f42c1;
        }

        .tipo-laminadora {
            background-color: #0d6efd;
        }

        .tipo-toallitas {
            background-color: #198754;
        }

        .tipo-panos {
            background-color: #fd7e14;
        }

        .alert-devolucion-completa {
            border-left: 4px solid #ffc107;
            background-color: #fff3cd;
        }
    </style>
</head>

<body>
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
                        <a class="nav-link" href="<?php echo $url_base; ?>secciones/produccion/main.php">
                            <i class="fas fa-cogs me-1"></i>Sector Producción
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $url_base; ?>secciones/sectorPcp/produccion.php">
                            <i class="fas fa-tools me-1"></i>Productos para Producción
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="fas fa-cogs me-1"></i>Venta #<?php echo $idVenta; ?>
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
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensajes['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $mensajes['error']; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensajes['mensaje'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo $mensajes['mensaje']; ?>
            </div>
        <?php endif; ?>

        <!-- Información General de la Venta -->
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información de la Venta #<?php echo $idVenta; ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Cliente:</th>
                                <td><?php echo htmlspecialchars($venta['cliente']); ?></td>
                            </tr>
                            <tr>
                                <th>Vendedor:</th>
                                <td><?php echo htmlspecialchars($venta['nombre_vendedor']); ?></td>
                            </tr>
                            <tr>
                                <th>Fecha de Venta:</th>
                                <td><?php echo date('d/m/Y', strtotime($venta['fecha_venta'])); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Moneda:</th>
                                <td><strong><?php echo htmlspecialchars($venta['moneda']); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Total:</th>
                                <td class="fs-5 fw-bold text-danger">
                                    <?php echo $controller->formatearMoneda($venta['monto_total'], $venta['moneda']); ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Productos en Producción:</th>
                                <td><span class="badge bg-danger"><?php echo count($productosProduccion); ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pestañas para las opciones -->
        <ul class="nav nav-tabs mb-4" id="opcionesTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="confirmar-tab" data-bs-toggle="tab" data-bs-target="#confirmar-tab-pane" type="button" role="tab" aria-controls="confirmar-tab-pane" aria-selected="true">
                    <i class="fas fa-check-circle me-1"></i>Emitir Producción
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="devolver-tab" data-bs-toggle="tab" data-bs-target="#devolver-tab-pane" type="button" role="tab" aria-controls="devolver-tab-pane" aria-selected="false">
                    <i class="fas fa-undo me-1"></i>Devolver a PCP
                </button>
            </li>
            <?php if (!empty($productosCompletados)): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="completados-tab" data-bs-toggle="tab" data-bs-target="#completados-tab-pane" type="button" role="tab" aria-controls="completados-tab-pane" aria-selected="false">
                        <i class="fas fa-clipboard-check me-1"></i>Productos Completados
                    </button>
                </li>
            <?php endif; ?>
        </ul>

        <div class="tab-content" id="opcionesTabsContent">
            <!-- Tab para Confirmar Producción -->
            <div class="tab-pane fade show active" id="confirmar-tab-pane" role="tabpanel" aria-labelledby="confirmar-tab" tabindex="0">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Emitir Producción</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($productosProduccion)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>No hay productos pendientes en producción
                            </div>
                        <?php else: ?>
                            <form method="POST" id="confirmarForm">
                                <input type="hidden" name="accion" value="confirmar">

                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0 fw-bold">Productos Pendientes en Producción</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="selectAllProductos" onchange="toggleSelectAll(this)">
                                            <label class="form-check-label" for="selectAllProductos">
                                                Seleccionar Todos
                                            </label>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <?php foreach ($productosProduccion as $producto): ?>
                                            <div class="col-md-3 mb-3">
                                                <div class="card producto-card h-100" onclick="toggleProductSelection(this, <?php echo $producto['id']; ?>)">
                                                    <div class="card-body p-3">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <h6 class="text-primary me-3"><?php echo htmlspecialchars($producto['descripcion']); ?></h6>
                                                            <div class="form-check">
                                                                <input class="form-check-input producto-checkbox" type="checkbox"
                                                                    id="producto_<?php echo $producto['id']; ?>"
                                                                    name="productos_completados[<?php echo $producto['id']; ?>]"
                                                                    value="1">
                                                            </div>
                                                        </div>

                                                        <!-- Badge del tipo de producto -->
                                                        <span class="badge mb-2 tipo-<?php echo strtolower($producto['tipoproducto']); ?>">
                                                            <?php echo htmlspecialchars($producto['tipoproducto']); ?>
                                                        </span>

                                                        <p class="mb-2">
                                                            <span class="text-muted">NCM:</span>
                                                            <strong><?php echo htmlspecialchars($producto['ncm'] ?: 'N/A'); ?></strong>
                                                        </p>
                                                        <p class="mb-2">
                                                            <span class="text-muted">Cantidad:</span>
                                                            <strong><?php echo $controller->formatearCantidadProducto($producto['cantidad'], $producto['tipoproducto'], $producto['unidadmedida'] ?? null); ?></strong>
                                                        </p>
                                                        <p class="mb-0">
                                                            <span class="text-muted">Fecha asignación:</span>
                                                            <strong><?php echo date('d/m/Y H:i', strtotime($producto['fecha_asignacion'])); ?></strong>
                                                        </p>
                                                        <?php if (!empty($producto['observaciones'])): ?>
                                                            <div class="mt-2 small">
                                                                <strong>Observaciones PCP:</strong><br>
                                                                <?php echo nl2br(htmlspecialchars($producto['observaciones'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="observaciones_produccion" class="form-label">
                                        Observaciones <span class="text-muted">(opcional)</span>
                                    </label>
                                    <textarea class="form-control" id="observaciones_produccion" name="observaciones_produccion" rows="3"
                                        placeholder="Añada observaciones sobre la producción..."></textarea>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="<?php echo $url_base; ?>secciones/sectorPcp/produccion.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Volver
                                    </a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check me-2"></i>Emitir Producción
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tab para Devolver a PCP - VERSIÓN MODIFICADA SIN CANTIDADES PARCIALES -->
            <div class="tab-pane fade" id="devolver-tab-pane" role="tabpanel" aria-labelledby="devolver-tab" tabindex="0">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-undo me-2"></i>Devolver a PCP</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($productosProduccion)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>No hay productos pendientes en producción
                            </div>
                        <?php else: ?>

                            <form method="POST" id="devolverForm">
                                <input type="hidden" name="accion" value="devolver">

                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0 fw-bold">Seleccione productos para devolver completamente</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="selectAllDevolucion" onchange="toggleSelectAllDevolucion(this)">
                                            <label class="form-check-label" for="selectAllDevolucion">
                                                Seleccionar Todos
                                            </label>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <?php foreach ($productosProduccion as $producto): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card producto-card h-100" onclick="toggleDevolucionSelection(this, <?php echo $producto['id']; ?>)">
                                                    <div class="card-body p-3">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <h6 class="text-primary me-3"><?php echo htmlspecialchars($producto['descripcion']); ?></h6>
                                                            <div class="form-check">
                                                                <input class="form-check-input devolucion-checkbox" type="checkbox"
                                                                    id="devolucion_<?php echo $producto['id']; ?>"
                                                                    name="productos_devueltos[]"
                                                                    value="<?php echo $producto['id']; ?>">
                                                            </div>
                                                        </div>

                                                        <!-- Badge del tipo de producto -->
                                                        <span class="badge mb-2 tipo-<?php echo strtolower($producto['tipoproducto']); ?>">
                                                            <?php echo htmlspecialchars($producto['tipoproducto']); ?>
                                                        </span>

                                                        <p class="mb-2">
                                                            <span class="text-muted">NCM:</span>
                                                            <strong><?php echo htmlspecialchars($producto['ncm'] ?: 'N/A'); ?></strong>
                                                        </p>

                                                        <div class="mb-2">
                                                            <span class="text-muted">Cantidad total a devolver:</span><br>
                                                            <strong class="text-danger fs-5">
                                                                <?php echo $controller->formatearCantidadProducto($producto['cantidad'], $producto['tipoproducto'], $producto['unidadmedida'] ?? null); ?>
                                                            </strong>
                                                        </div>

                                                        <p class="mb-2">
                                                            <span class="text-muted">Fecha asignación:</span>
                                                            <strong><?php echo date('d/m/Y H:i', strtotime($producto['fecha_asignacion'])); ?></strong>
                                                        </p>

                                                        <?php if (!empty($producto['observaciones'])): ?>
                                                            <div class="mt-2 small">
                                                                <strong>Observaciones PCP:</strong><br>
                                                                <?php echo nl2br(htmlspecialchars($producto['observaciones'])); ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Campo oculto para enviar la cantidad completa -->
                                                        <input type="hidden" name="cantidad_devuelta[<?php echo $producto['id']; ?>]" value="<?php echo $producto['cantidad']; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="motivo_devolucion" class="form-label">
                                        Motivo de Devolución <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control" id="motivo_devolucion" name="motivo_devolucion" rows="3"
                                        placeholder="Explique el motivo de la devolución..." required></textarea>
                                    <div class="form-text">Mínimo 10 caracteres. Sea específico sobre el motivo de la devolución.</div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="<?php echo $url_base; ?>secciones/sectorPcp/produccion.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Volver
                                    </a>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-undo me-2"></i>Devolver a PCP
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tab para Productos Completados -->
            <?php if (!empty($productosCompletados)): ?>
                <div class="tab-pane fade" id="completados-tab-pane" role="tabpanel" aria-labelledby="completados-tab" tabindex="0">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Productos Completados</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Tipo</th>
                                            <th>Cantidad</th>
                                            <th>Fecha Completado</th>
                                            <th>Observaciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($productosCompletados as $producto): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($producto['descripcion']); ?></td>
                                                <td>
                                                    <span class="badge tipo-<?php echo strtolower($producto['tipoproducto']); ?>">
                                                        <?php echo htmlspecialchars($producto['tipoproducto'] ?? 'N/A'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo $controller->formatearCantidadProducto($producto['cantidad'], $producto['tipoproducto'], $producto['unidadmedida'] ?? null); ?>
                                                </td>
                                                <td>
                                                    <?php echo date('d/m/Y H:i', strtotime($producto['fecha_completado'])); ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($producto['observaciones_produccion'])): ?>
                                                        <?php echo nl2br(htmlspecialchars($producto['observaciones_produccion'])); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sin observaciones</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-3">
                                <a href="<?php echo $url_base; ?>secciones/sectorPcp/produccion.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Volver
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL DE ÉXITO PARA EMISIÓN -->
    <?php if ($resultadoExito && $resultadoExito['tipo'] === 'emision'): ?>
        <div class="modal fade" id="modalExitoEmision" tabindex="-1" aria-labelledby="modalExitoEmisionLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white text-center">
                        <div class="w-100">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                            <h2 class="modal-title" id="modalExitoEmisionLabel">¡Órdenes Emitidas Exitosamente!</h2>
                            <p class="mb-0">Se han generado <?php echo count($resultadoExito['productos_emitidos']); ?> <?php echo count($resultadoExito['productos_emitidos']) === 1 ? 'orden' : 'órdenes'; ?> de producción</p>
                        </div>
                    </div>
                    <div class="modal-body">
                        <div class="row text-center mb-4">
                            <div class="col-4">
                                <small class="text-muted">Venta #</small><br>
                                <strong><?php echo $resultadoExito['id_venta']; ?></strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">Emitido por</small><br>
                                <strong><?php echo htmlspecialchars($resultadoExito['usuario']); ?></strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">Fecha y Hora</small><br>
                                <strong><?php echo $resultadoExito['fecha']; ?></strong>
                            </div>
                        </div>

                        <h6><i class="fas fa-list me-2"></i>Órdenes Generadas:</h6>
                        <?php foreach ($resultadoExito['productos_emitidos'] as $producto): ?>
                            <?php
                            $tipoColor = $controller->obtenerColorTipoProducto($producto['tipo']);
                            $tipoIcon = $controller->obtenerIconoTipoProducto($producto['tipo']);
                            ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <span class="badge me-2" style="background-color: <?php echo $tipoColor; ?>;">
                                                <i class="fas <?php echo $tipoIcon; ?> me-1"></i><?php echo htmlspecialchars($producto['tipo']); ?>
                                            </span>
                                            <span class="badge bg-secondary">Orden #<?php echo $producto['id_orden']; ?></span>
                                            <h6 class="mt-2 mb-1"><?php echo htmlspecialchars($producto['descripcion']); ?></h6>
                                            <small class="text-muted">Cantidad: <?php echo $controller->formatearCantidadProducto($producto['cantidad'], $producto['tipo'], $producto['unidad'] ?? null); ?></small>
                                        </div>
                                        <button onclick="abrirPDF(<?php echo $producto['id_orden']; ?>, '<?php echo $producto['tipo']; ?>')" class="btn btn-sm btn-info">
                                            <i class="fas fa-file-pdf me-1"></i>Ver PDF
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="modal-footer text-center">
                        <button type="button" class="btn btn-primary" onclick="continuar()">
                            <i class="fas fa-arrow-right me-2"></i>Ir a Órdenes de Producción
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- MODAL DE ÉXITO PARA DEVOLUCIÓN COMPLETA -->
    <?php if ($resultadoExito && $resultadoExito['tipo'] === 'devolucion'): ?>
        <div class="modal fade" id="modalExitoDevolucion" tabindex="-1" aria-labelledby="modalExitoDevolucionLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark text-center">
                        <div class="w-100">
                            <i class="fas fa-undo-alt fa-3x mb-3"></i>
                            <h2 class="modal-title" id="modalExitoDevolucionLabel">¡Productos Devueltos Completamente!</h2>
                            <p class="mb-0">Se han devuelto <?php echo count($resultadoExito['productos_devueltos']); ?> <?php echo count($resultadoExito['productos_devueltos']) === 1 ? 'producto completo' : 'productos completos'; ?> a PCP</p>
                        </div>
                    </div>
                    <div class="modal-body">
                        <div class="row text-center mb-4">
                            <div class="col-4">
                                <small class="text-muted">Venta #</small><br>
                                <strong><?php echo $resultadoExito['id_venta']; ?></strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">Devuelto por</small><br>
                                <strong><?php echo htmlspecialchars($resultadoExito['usuario']); ?></strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">Fecha y Hora</small><br>
                                <strong><?php echo $resultadoExito['fecha']; ?></strong>
                            </div>
                        </div>

                        <h6><i class="fas fa-list me-2"></i>Productos Devueltos Completamente:</h6>
                        <?php foreach ($resultadoExito['productos_devueltos'] as $producto): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <span class="badge bg-warning text-dark">DEVOLUCIÓN COMPLETA</span>
                                            <h6 class="mt-2 mb-1"><?php echo htmlspecialchars($producto['descripcion']); ?></h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (!empty($resultadoExito['nuevo_estado'])): ?>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Estado de la venta actualizado a:</strong> <?php echo htmlspecialchars($resultadoExito['nuevo_estado']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer text-center">
                        <button type="button" class="btn btn-primary" onclick="continuar()">
                            <i class="fas fa-arrow-right me-2"></i>Continuar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Configuración global desde el controller
        const PRODUCCION_CONFIG = <?php echo json_encode($configuracionJS); ?>;

        // Mostrar modal automáticamente si hay resultado exitoso
        <?php if ($resultadoExito): ?>
            document.addEventListener('DOMContentLoaded', function() {
                <?php if ($resultadoExito['tipo'] === 'emision'): ?>
                    const modalEmision = new bootstrap.Modal(document.getElementById('modalExitoEmision'));
                    modalEmision.show();
                <?php elseif ($resultadoExito['tipo'] === 'devolucion'): ?>
                    const modalDevolucion = new bootstrap.Modal(document.getElementById('modalExitoDevolucion'));
                    modalDevolucion.show();

                    // Auto-continuar después de 10 segundos
                    setTimeout(function() {
                        continuar();
                    }, 10000);
                <?php endif; ?>
            });
        <?php endif; ?>

        // Funciones del modal
        function abrirPDF(idOrden, tipo) {
            let url;
            if (tipo === "TOALLITAS") {
                url = "../../pdf/producciontoallitas.php?id_orden=" + idOrden;
            } else if (tipo === "SPUNLACE") {
                url = "../../pdf/produccion_spunlace.php?id_orden=" + idOrden;
            } else if (tipo === "PAÑOS") {
                url = "../../pdf/produccionpanos.php?id_orden=" + idOrden;
            } else {
                url = "../../pdf/produccion.php?id_orden=" + idOrden;
            }
            window.open(url, "_blank");
        }

        function continuar() {
            <?php if ($resultadoExito && $resultadoExito['tipo'] === 'emision'): ?>
                window.location.href = "<?php echo $url_base; ?>secciones/produccion/ordenes_produccion.php";
            <?php else: ?>
                window.location.href = "<?php echo $url_base; ?>secciones/sectorPcp/produccion.php";
            <?php endif; ?>
        }

        // FUNCIONES SIMPLIFICADAS PARA DEVOLUCIONES COMPLETAS
        function toggleDevolucionSelection(element, productId) {
            const checkbox = document.getElementById('devolucion_' + productId);

            // Si se hace clic en el checkbox, no hacer nada más
            if (event.target.type === 'checkbox') {
                return;
            }

            // Toggle del checkbox
            checkbox.checked = !checkbox.checked;

            // Actualizar apariencia visual
            if (checkbox.checked) {
                element.classList.add('selected');
            } else {
                element.classList.remove('selected');
            }
        }

        function toggleSelectAllDevolucion(checkbox) {
            const checkboxes = document.querySelectorAll('.devolucion-checkbox');
            const cards = document.querySelectorAll('#devolver-tab-pane .producto-card');

            checkboxes.forEach((item, index) => {
                item.checked = checkbox.checked;

                if (checkbox.checked) {
                    cards[index].classList.add('selected');
                } else {
                    cards[index].classList.remove('selected');
                }
            });
        }

        // Funciones para confirmación de producción (sin cambios)
        function toggleProductSelection(element, productId) {
            const checkbox = document.getElementById('producto_' + productId);
            if (event.target.type === 'checkbox') {
                return;
            }

            checkbox.checked = !checkbox.checked;

            if (checkbox.checked) {
                element.classList.add('selected');
            } else {
                element.classList.remove('selected');
            }
        }

        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.producto-checkbox');
            const cards = document.querySelectorAll('#confirmar-tab-pane .producto-card');

            checkboxes.forEach((item, index) => {
                item.checked = checkbox.checked;
                if (checkbox.checked) {
                    cards[index].classList.add('selected');
                } else {
                    cards[index].classList.remove('selected');
                }
            });
        }

        // Validación simplificada para devoluciones completas
        async function validarDevolucion() {
            const checkboxes = document.querySelectorAll('.devolucion-checkbox:checked');

            if (checkboxes.length === 0) {
                await Swal.fire('Error', 'Debe seleccionar al menos un producto para devolver', 'error');
                return false;
            }

            const motivo = document.getElementById('motivo_devolucion').value.trim();
            if (!motivo) {
                await Swal.fire('Error', 'Debe ingresar un motivo para la devolución', 'error');
                return false;
            }

            if (motivo.length < 10) {
                await Swal.fire('Error', 'El motivo debe tener al menos 10 caracteres', 'error');
                return false;
            }

            // Construir mensaje de confirmación con detalles
            let productosSeleccionados = '';
            checkboxes.forEach((checkbox, index) => {
                const card = checkbox.closest('.producto-card');
                const nombreProducto = card.querySelector('h6.text-primary').textContent.trim();
                const cantidadElement = card.querySelector('.text-danger.fs-5');
                const cantidad = cantidadElement ? cantidadElement.textContent.trim() : 'N/A';

                productosSeleccionados += `• ${nombreProducto} (${cantidad})\n`;
            });

            const result = await Swal.fire({
                title: 'Confirmar Devolución Completa',
                html: `
                    <div class="text-start">
                        <p><strong>Se devolverán COMPLETAMENTE los siguientes productos:</strong></p>
                        <div class="bg-light p-3 rounded" style="font-family: monospace; white-space: pre-line; text-align: left;">${productosSeleccionados}</div>
                        <p class="mt-3"><strong>Motivo:</strong> ${motivo}</p>
                        <p class="text-warning"><i class="fas fa-exclamation-triangle"></i> Esta acción no se puede deshacer.</p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, devolver productos',
                cancelButtonText: 'Cancelar',
                width: '600px'
            });

            return result.isConfirmed;
        }

        // Validación para confirmación (sin cambios)
        async function validarConfirmacion() {
            const checkboxes = document.querySelectorAll('.producto-checkbox:checked');
            if (checkboxes.length === 0) {
                await Swal.fire('Error', 'Seleccione al menos un producto', 'error');
                return false;
            }

            const result = await Swal.fire({
                title: 'Confirmar Producción',
                text: '¿Confirmar la emisión de producción?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d'
            });
            return result.isConfirmed;
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Evitar propagación en checkboxes
            const checkboxes = document.querySelectorAll('.form-check-input');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });

            // Formularios
            const confirmarForm = document.getElementById('confirmarForm');
            if (confirmarForm) {
                confirmarForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const confirmed = await validarConfirmacion();
                    if (confirmed) this.submit();
                });
            }

            const devolverForm = document.getElementById('devolverForm');
            if (devolverForm) {
                devolverForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const confirmed = await validarDevolucion();
                    if (confirmed) {
                        // Mostrar loading
                        Swal.fire({
                            title: 'Procesando devolución...',
                            text: 'Por favor espere',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        this.submit();
                    }
                });
            }
        });
    </script>
</body>

</html>