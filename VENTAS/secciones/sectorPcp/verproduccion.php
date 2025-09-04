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
$resultadoRecetas = null; // Variable para almacenar resultado de configuración de recetas

// Procesar formulario si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $resultado = $controller->procesarFormularioProduccion($idVenta, $_POST);

    if (isset($resultado['error'])) {
        $error = $resultado['error'];
    } elseif (isset($resultado['success']) && $resultado['success']) {
        if ($resultado['tipo'] === 'configurar_recetas') {
            // Mostrar modal de configuración de recetas
            $resultadoRecetas = $resultado;
        } else {
            // Almacenar resultado exitoso para mostrar modal
            $resultadoExito = $resultado;
        }
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
$breadcrumb_items = ['Sector Produccion', 'Productos para Produccion', 'Ver Produccion'];
$item_urls = [
    $url_base . 'secciones/produccion/main.php',
    $url_base . 'secciones/sectorPcp/produccion.php',
];
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

        /* ✅ ESTILOS MEJORADOS Y COMPACTOS PARA RECETAS */
        .receta-card {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            transition: all 0.2s ease;
            cursor: pointer;
            margin-bottom: 0.75rem;
            padding: 0.75rem;
            font-size: 0.875rem;
        }

        .receta-card:hover {
            border-color: #0d6efd;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .receta-card.selected {
            border-color: #0d6efd;
            background-color: #f8f9ff;
            box-shadow: 0 3px 6px rgba(13, 110, 253, 0.15);
        }

        .version-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
        }

        .materia-prima-item {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 0.4rem 0.5rem;
            margin-bottom: 0.4rem;
            border-left: 2px solid #6c757d;
            font-size: 0.8rem;
        }

        .materia-prima-item.extra {
            border-left-color: #ffc107;
            background-color: #fff3cd;
        }

        .materia-prima-item .d-flex {
            align-items: center;
        }

        .materia-prima-item strong {
            font-size: 0.8rem;
            font-weight: 600;
        }

        .materia-prima-item .badge {
            font-size: 0.65rem;
            padding: 0.15rem 0.3rem;
        }

        .materia-prima-item .text-muted {
            font-size: 0.75rem;
            font-weight: 500;
        }

        .materia-prima-item small {
            font-size: 0.7rem;
            margin-top: 0.2rem;
            display: block;
        }

        .recetas-section {
            max-height: 450px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 0.75rem;
            background-color: #fefefe;
        }

        .no-recetas {
            color: #6c757d;
            font-style: italic;
            text-align: center;
            padding: 1.5rem;
            font-size: 0.9rem;
        }

        /* ✅ Estilos para toggle de recetas */
        .recetas-toggle {
            background: linear-gradient(45deg, #6f42c1, #0d6efd);
            border: none;
            color: white;
            transition: all 0.3s ease;
            position: relative;
        }

        .recetas-toggle:hover {
            background: linear-gradient(45deg, #5a359a, #0b5ed7);
            color: white;
            transform: translateY(-2px);
        }

        .recetas-toggle.active {
            background: linear-gradient(45deg, #198754, #20c997);
        }

        .recetas-toggle.active:hover {
            background: linear-gradient(45deg, #157347, #1aa179);
        }

        .recetas-configuradas {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #28a745;
            border-radius: 50%;
            width: 10px;
            height: 10px;
            border: 2px solid white;
        }

        .receta-radio {
            transform: scale(1.1);
            accent-color: #0d6efd;
        }

        .version-1-default {
            border: 2px solid #28a745;
            background-color: #f8fff9;
        }

        .producto-recetas-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px 8px 0 0;
        }

        /* ✅ NUEVO: Estilos compactos para el grupo de recetas */
        .receta-grupo {
            margin-bottom: 1rem;
        }

        .receta-grupo h6 {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            color: #0d6efd;
            font-weight: 600;
        }

        .receta-version-compact {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .receta-content-compact {
            flex: 1;
            min-width: 0;
        }

        .receta-radio-compact {
            margin-top: 0.5rem;
            flex-shrink: 0;
        }

        .version-header-compact {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.4rem;
            flex-wrap: wrap;
        }

        .version-info-compact {
            font-size: 0.7rem;
            color: #6c757d;
        }

        /* ✅ NUEVO: Grid compacto para múltiples recetas */
        .recetas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 0.75rem;
        }

        /* ✅ Responsive para pantallas pequeñas */
        @media (max-width: 768px) {
            .recetas-grid {
                grid-template-columns: 1fr;
            }

            .receta-card {
                padding: 0.5rem;
                font-size: 0.8rem;
            }

            .materia-prima-item {
                padding: 0.3rem 0.4rem;
                font-size: 0.75rem;
            }
        }

        /* ✅ Mejoras adicionales para compacidad */
        .receta-card .d-flex {
            gap: 0.5rem;
        }

        .materias-primas-compact {
            margin-top: 0.5rem;
        }

        .producto-recetas-card {
            margin-bottom: 1.5rem;
        }

        .producto-recetas-card .card-header {
            padding: 0.75rem;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .producto-recetas-card .card-body {
            padding: 0.75rem;
        }

        .producto-recetas-card h6 {
            font-size: 0.95rem;
            margin-bottom: 0;
        }
    </style>
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
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
            <!-- Tab para Confirmar Producción CON RECETAS COMPACTAS -->
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

                                <!-- ✅ Toggle para recetas -->
                                <div class="mb-4">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <h6 class="mb-1">Configuración de Recetas</h6>
                                            <small class="text-muted">Configura recetas específicas para la producción (opcional)</small>
                                        </div>
                                        <button type="button" class="btn recetas-toggle" id="btnToggleRecetas" onclick="toggleRecetas()">
                                            <i class="fas fa-flask me-2"></i>
                                            <span id="textoToggleRecetas">Usar Recetas</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="<?php echo $url_base; ?>secciones/sectorPcp/produccion.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Volver
                                    </a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check me-2"></i>Emitir Producción
                                    </button>
                                </div>

                                <!-- Campos ocultos para recetas -->
                                <input type="hidden" name="recetas_configuradas" id="recetas_configuradas" value="">
                                <input type="hidden" name="usar_recetas" id="usar_recetas" value="0">
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tab para Devolver a PCP - Sin cambios -->
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

            <!-- Tab para Productos Completados - Sin cambios -->
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

    <!-- ✅ MODAL COMPACTO PARA CONFIGURAR RECETAS -->
    <div class="modal fade" id="modalRecetas" tabindex="-1" aria-labelledby="modalRecetasLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header producto-recetas-header">
                    <h5 class="modal-title" id="modalRecetasLabel">
                        <i class="fas fa-flask me-2"></i>Configurar Recetas de Producción
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Configuración Simplificada:</strong> Para cada producto, seleccione la receta que desea usar.
                        La <strong>Versión 1</strong> viene preseleccionada por defecto. Solo puede seleccionar una receta por producto.
                    </div>

                    <form id="formRecetas" method="POST">
                        <input type="hidden" name="accion" value="configurar_recetas">
                        <div id="productos-recetas-container">
                            <!-- Aquí se cargarán dinámicamente los productos con sus recetas disponibles -->
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-success" onclick="aplicarRecetas()">
                        <i class="fas fa-check me-2"></i>Aplicar Configuración
                    </button>
                </div>
            </div>
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
                                            <?php if (!empty($producto['recetas_asignadas'])): ?>
                                                <span class="badge bg-primary ms-1">
                                                    <i class="fas fa-flask me-1"></i><?php echo count($producto['recetas_asignadas']); ?> Recetas
                                                </span>
                                            <?php endif; ?>
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

        // Variables globales simplificadas
        let recetasSeleccionadas = {};
        let productosConRecetas = {};
        let usandoRecetas = false;

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

        // ✅ Toggle simplificado para recetas
        function toggleRecetas() {
            const btn = document.getElementById('btnToggleRecetas');
            const texto = document.getElementById('textoToggleRecetas');
            const usarRecetasInput = document.getElementById('usar_recetas');

            if (!usandoRecetas) {
                // Activar uso de recetas
                configurarRecetas();
            } else {
                // Desactivar uso de recetas
                usandoRecetas = false;
                btn.classList.remove('active');
                btn.querySelector('.recetas-configuradas')?.remove();
                texto.textContent = 'Usar Recetas';
                usarRecetasInput.value = '0';
                document.getElementById('recetas_configuradas').value = '';
                recetasSeleccionadas = {};

                console.log('Recetas desactivadas');

                Swal.fire({
                    title: 'Recetas Desactivadas',
                    text: 'La producción se emitirá sin recetas específicas',
                    icon: 'info',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        }

        async function configurarRecetas() {
            const productosSeleccionados = obtenerProductosSeleccionados();

            if (productosSeleccionados.length === 0) {
                await Swal.fire('Error', 'Debe seleccionar al menos un producto antes de configurar recetas', 'error');
                return;
            }

            console.log('Configurando recetas para productos:', productosSeleccionados);

            // Mostrar loading
            Swal.fire({
                title: 'Cargando recetas disponibles...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                // Enviar solicitud para obtener recetas
                const formData = new FormData();
                formData.append('accion', 'configurar_recetas');
                productosSeleccionados.forEach(id => {
                    formData.append('productos_seleccionados[]', id);
                });

                console.log('Enviando solicitud de recetas...');

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();
                console.log('Respuesta del servidor:', data);

                Swal.close();

                if (data.success && data.tipo === 'configurar_recetas') {
                    productosConRecetas = data.productos_con_recetas;
                    console.log('Productos con recetas cargados:', productosConRecetas);
                    mostrarModalRecetas(data.productos_con_recetas);
                } else {
                    console.error('Error en respuesta:', data);
                    await Swal.fire('Error', data.error || 'Error al cargar recetas', 'error');
                }
            } catch (error) {
                Swal.close();
                console.error('Error de conexión:', error);
                await Swal.fire('Error', 'Error de conexión al servidor: ' + error.message, 'error');
            }
        }

        function mostrarModalRecetas(productosConRecetas) {
            const container = document.getElementById('productos-recetas-container');
            container.innerHTML = '';

            // Verificar si hay productos con recetas
            const tieneRecetas = Object.keys(productosConRecetas).some(idProducto =>
                productosConRecetas[idProducto].recetas && productosConRecetas[idProducto].recetas.length > 0
            );

            if (!tieneRecetas) {
                container.innerHTML = `
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                        <h5>No hay recetas disponibles</h5>
                        <p>Los productos seleccionados no tienen recetas configuradas en el sistema. 
                        Puede continuar sin recetas o configurar recetas en el módulo de administración.</p>
                    </div>
                `;
            } else {
                // Generar HTML para productos con recetas
                Object.entries(productosConRecetas).forEach(([idProducto, data]) => {
                    const productoHtml = crearHtmlProductoConRecetas(idProducto, data);
                    container.innerHTML += productoHtml;
                });

                // Preseleccionar versión 1 después de crear el HTML
                setTimeout(() => {
                    preseleccionarVersion1();
                }, 100);
            }

            const modal = new bootstrap.Modal(document.getElementById('modalRecetas'));
            modal.show();
        }

        // ✅ Preseleccionar versión 1 automáticamente
        function preseleccionarVersion1() {
            console.log('Preseleccionando versión 1 para productos:', Object.keys(productosConRecetas));

            Object.keys(productosConRecetas).forEach(idProducto => {
                const version1Radio = document.querySelector(`input[name="receta_${idProducto}"][data-version="1"]`);
                if (version1Radio) {
                    version1Radio.checked = true;
                    version1Radio.closest('.receta-card').classList.add('selected');

                    // Agregar a recetas seleccionadas
                    const nombreReceta = version1Radio.dataset.nombre;
                    const versionData = productosConRecetas[idProducto].recetas
                        .find(g => g.nombre === nombreReceta)?.versiones['1'];

                    if (versionData && versionData.materias_primas.length > 0) {
                        if (!recetasSeleccionadas[idProducto]) {
                            recetasSeleccionadas[idProducto] = {};
                        }
                        versionData.materias_primas.forEach(mp => {
                            recetasSeleccionadas[idProducto][mp.id_receta] = true;
                        });

                        console.log(`Producto ${idProducto} - Recetas preseleccionadas:`, recetasSeleccionadas[idProducto]);
                    }
                } else {
                    console.log(`No se encontró versión 1 para producto ${idProducto}`);
                }
            });

            console.log('Estado final de recetas seleccionadas:', recetasSeleccionadas);
        }

        // ✅ FUNCIÓN MEJORADA: Crear HTML compacto con grid layout
        function crearHtmlProductoConRecetas(idProducto, data) {
            const producto = data.producto;
            const recetasAgrupadas = data.recetas;

            let recetasHtml = '';

            if (recetasAgrupadas.length === 0) {
                recetasHtml = `<div class="no-recetas">No hay recetas disponibles para este tipo de producto</div>`;
            } else {
                recetasHtml = `<div class="recetas-grid">`;

                recetasAgrupadas.forEach(grupo => {
                    recetasHtml += `
                        <div class="receta-grupo">
                            <h6 class="fw-bold text-primary">${grupo.nombre}</h6>
                            ${Object.entries(grupo.versiones).map(([version, versionData]) => `
                                <div class="receta-card ${version === '1' ? 'version-1-default' : ''}" onclick="toggleRecetaSelection(this, '${idProducto}', '${version}', '${grupo.nombre}')">
                                    <div class="receta-version-compact">
                                        <div class="receta-content-compact">
                                            <div class="version-header-compact">
                                                <span class="badge version-badge ${version === '1' ? 'bg-success' : 'bg-secondary'}">
                                                    ${version === '1' ? '✓ v' + version + ' (Por defecto)' : 'v' + version}
                                                </span>
                                                <span class="version-info-compact">
                                                    ${new Date(versionData.fecha_creacion).toLocaleDateString()} | ${versionData.usuario_creacion}
                                                </span>
                                            </div>
                                            <div class="materias-primas-compact">
                                                ${versionData.materias_primas.map(mp => `
                                                    <div class="materia-prima-item ${mp.es_materia_extra ? 'extra' : ''}">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div class="d-flex align-items-center flex-wrap">
                                                                <strong>${mp.nombre_materia_prima}</strong>
                                                                <span class="badge ${mp.es_materia_extra ? 'bg-warning text-dark' : 'bg-info text-dark'} ms-1">
                                                                    ${mp.es_materia_extra ? 'Extra' : 'Porcentaje'}
                                                                </span>
                                                            </div>
                                                            <span class="text-muted">
                                                                ${mp.cantidad_por_kilo} ${mp.es_materia_extra ? (mp.unidad_medida_extra || 'kg') + '/kg' : '%'}
                                                            </span>
                                                        </div>
                                                        ${mp.descripcion ? `<small class="text-muted">${mp.descripcion}</small>` : ''}
                                                    </div>
                                                `).join('')}
                                            </div>
                                        </div>
                                        <div class="receta-radio-compact">
                                            <div class="form-check">
                                                <input class="form-check-input receta-radio" type="radio" 
                                                       name="receta_${idProducto}"
                                                       id="receta_${idProducto}_${version}_${grupo.nombre.replace(/\s+/g, '_')}"
                                                       data-id-producto="${idProducto}" 
                                                       data-version="${version}" 
                                                       data-nombre="${grupo.nombre}"
                                                       onchange="manejarSeleccionReceta(this)">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `;
                });

                recetasHtml += `</div>`;
            }

            return `
                <div class="card producto-recetas-card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <span class="badge tipo-${producto.tipoproducto.toLowerCase()} me-2">${producto.tipoproducto}</span>
                            ${producto.descripcion}
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="recetas-section">
                            ${recetasHtml}
                        </div>
                    </div>
                </div>
            `;
        }

        // Manejar selección de receta con radio buttons
        function manejarSeleccionReceta(radio) {
            const idProducto = radio.dataset.idProducto;
            const version = radio.dataset.version;
            const nombreReceta = radio.dataset.nombre;

            console.log(`Cambiando receta para producto ${idProducto} a versión ${version} de ${nombreReceta}`);

            // Remover selección visual de todas las tarjetas de este producto
            document.querySelectorAll(`input[name="receta_${idProducto}"]`).forEach(r => {
                r.closest('.receta-card').classList.remove('selected');
            });

            // Agregar selección visual a la tarjeta seleccionada
            radio.closest('.receta-card').classList.add('selected');

            // Limpiar recetas anteriores de este producto
            if (recetasSeleccionadas[idProducto]) {
                recetasSeleccionadas[idProducto] = {};
            } else {
                recetasSeleccionadas[idProducto] = {};
            }

            // Obtener los IDs de las recetas desde las materias primas
            const versionData = productosConRecetas[idProducto].recetas
                .find(g => g.nombre === nombreReceta)?.versiones[version];

            if (versionData && versionData.materias_primas.length > 0) {
                versionData.materias_primas.forEach(mp => {
                    recetasSeleccionadas[idProducto][mp.id_receta] = true;
                });

                console.log(`Producto ${idProducto} - Nuevas recetas seleccionadas:`, recetasSeleccionadas[idProducto]);
            } else {
                console.log(`No se encontraron materias primas para ${nombreReceta} v${version} del producto ${idProducto}`);
            }

            console.log('Estado actual de todas las recetas:', recetasSeleccionadas);
        }

        function toggleRecetaSelection(element, idProducto, version, nombreReceta) {
            if (event.target.type === 'radio') {
                return;
            }

            const radio = element.querySelector('.receta-radio');
            radio.checked = true;
            radio.dispatchEvent(new Event('change'));
        }

        function aplicarRecetas() {
            // Guardar las recetas seleccionadas en el campo oculto
            document.getElementById('recetas_configuradas').value = JSON.stringify(recetasSeleccionadas);
            document.getElementById('usar_recetas').value = '1';

            // Cerrar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalRecetas'));
            modal.hide();

            // Actualizar botón toggle
            const btn = document.getElementById('btnToggleRecetas');
            const texto = document.getElementById('textoToggleRecetas');

            usandoRecetas = true;
            btn.classList.add('active');
            texto.textContent = 'Recetas Configuradas';

            // Agregar indicador visual
            if (!btn.querySelector('.recetas-configuradas')) {
                const indicator = document.createElement('div');
                indicator.className = 'recetas-configuradas';
                btn.appendChild(indicator);
            }

            // Mostrar mensaje de éxito
            Swal.fire({
                title: 'Recetas Configuradas',
                text: 'Las recetas han sido configuradas correctamente para la producción',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });

            console.log('Recetas aplicadas:', recetasSeleccionadas);
        }

        // Preparar formulario antes del envío
        function prepararFormularioParaEnvio() {
            const form = document.getElementById('confirmarForm');

            // Limpiar inputs dinámicos anteriores
            const existingInputs = form.querySelectorAll('input[name="recetas_seleccionadas"]');
            existingInputs.forEach(input => input.remove());

            console.log('Preparando formulario para envío...');
            console.log('usandoRecetas:', usandoRecetas);
            console.log('recetasSeleccionadas:', recetasSeleccionadas);

            // Si estamos usando recetas, asegurar que se envíen
            if (usandoRecetas && Object.keys(recetasSeleccionadas).length > 0) {
                // Crear input para recetas seleccionadas
                const recetasInput = document.createElement('input');
                recetasInput.type = 'hidden';
                recetasInput.name = 'recetas_seleccionadas';
                recetasInput.value = JSON.stringify(recetasSeleccionadas);
                form.appendChild(recetasInput);

                // Asegurar que usar_recetas esté en 1
                document.getElementById('usar_recetas').value = '1';
                document.getElementById('recetas_configuradas').value = JSON.stringify(recetasSeleccionadas);

                console.log('✅ ENVIANDO CON RECETAS - JSON:', JSON.stringify(recetasSeleccionadas));
            } else {
                // No usar recetas
                document.getElementById('usar_recetas').value = '0';
                document.getElementById('recetas_configuradas').value = '';

                console.log('❌ ENVIANDO SIN RECETAS');
            }

            // Verificar campos finales del formulario
            console.log('Campos del formulario antes del envío:');
            console.log('- usar_recetas:', document.getElementById('usar_recetas').value);
            console.log('- recetas_configuradas:', document.getElementById('recetas_configuradas').value);
            const recetasSeleccionadasInput = form.querySelector('input[name="recetas_seleccionadas"]');
            console.log('- recetas_seleccionadas:', recetasSeleccionadasInput ? recetasSeleccionadasInput.value : 'No existe');
        }

        function obtenerProductosSeleccionados() {
            const checkboxes = document.querySelectorAll('.producto-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.id.replace('producto_', ''));
        }

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

        // FUNCIONES PARA DEVOLUCIONES
        function toggleDevolucionSelection(element, productId) {
            const checkbox = document.getElementById('devolucion_' + productId);

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

        // Funciones para confirmación de producción
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

        // Validaciones
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

        async function validarConfirmacion() {
            const checkboxes = document.querySelectorAll('.producto-checkbox:checked');
            if (checkboxes.length === 0) {
                await Swal.fire('Error', 'Seleccione al menos un producto', 'error');
                return false;
            }

            // Verificar estado de recetas
            const recetasCount = Object.keys(recetasSeleccionadas).length;
            let mensajeRecetas = '';

            if (usandoRecetas && recetasCount > 0) {
                mensajeRecetas = `<p class="text-success"><i class="fas fa-flask me-2"></i>Se emitirá con recetas configuradas para ${recetasCount} producto(s)</p>`;
                console.log('Confirmación: Usando recetas para productos:', Object.keys(recetasSeleccionadas));
            } else {
                mensajeRecetas = '<p class="text-muted"><i class="fas fa-info-circle me-2"></i>Se emitirá sin recetas específicas</p>';
                console.log('Confirmación: Sin recetas');
            }

            const result = await Swal.fire({
                title: 'Confirmar Emisión de Producción',
                html: `
                    <p>¿Confirmar la emisión de ${checkboxes.length} producto(s) para producción?</p>
                    ${mensajeRecetas}
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, emitir producción',
                cancelButtonText: 'Cancelar'
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
                    if (confirmed) {
                        // Asegurar que las recetas se envíen correctamente
                        prepararFormularioParaEnvio();
                        this.submit();
                    }
                });
            }

            const devolverForm = document.getElementById('devolverForm');
            if (devolverForm) {
                devolverForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const confirmed = await validarDevolucion();
                    if (confirmed) {
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