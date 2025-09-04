<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
if (file_exists("controllers/DespachoController.php")) {
    include "controllers/DespachoController.php";
} else {
    die("Error: No se pudo cargar el controlador de despacho.");
}

$numeroExpedicion = $_GET['numero_expedicion'] ?? '';

if (empty($numeroExpedicion)) {
    header("Location: " . $url_base . "secciones/despacho_seguimiento/main.php?error=Número de expedición requerido");
    exit();
}

$controller = new DespachoController($conexion, $url_base);

if (!$controller->verificarPermisos('ver')) {
    header("Location: " . $url_base . "index.php?error=No tienes permisos para ver expediciones");
    exit();
}

try {
    $detalles = $controller->obtenerDetallesExpedicion($numeroExpedicion);
    $expedicion = $detalles['expedicion'];
    $items = $detalles['items'];
    $resumen = $detalles['resumen'];
} catch (Exception $e) {
    header("Location: " . $url_base . "secciones/despacho_seguimiento/main.php?error=" . urlencode($e->getMessage()));
    exit();
}

$datosVista = $controller->obtenerDatosVista();
$configuracionJS = $controller->obtenerConfiguracionJS();
$mensajes = $controller->manejarMensajes();

$controller->logActividad('Ver detalles expedición', $numeroExpedicion);
$breadcrumb_items = ['Seguimiento Produccion', 'Seguimiento Despacho', 'Ver Despacho'];
$item_urls = [
    $url_base . 'secciones/produccion_seguimiento/main.php',
    $url_base . 'secciones/despacho_seguimiento/main.php',
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expedición <?php echo htmlspecialchars($numeroExpedicion); ?> - <?php echo $datosVista['titulo']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/despacho_seguimiento/utils/styles.css">
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo $url_base; ?>index.php">Inicio</a></li>
                <li class="breadcrumb-item"><a href="<?php echo $url_base; ?>secciones/despacho_seguimiento/main.php">Seguimiento Despacho</a></li>
                <li class="breadcrumb-item active">Expedición <?php echo htmlspecialchars($numeroExpedicion); ?></li>
            </ol>
        </nav>

        <!-- Contenedor para mensajes dinámicos -->
        <div id="mensajes-container"></div>

        <?php if (!empty($mensajes['mensaje'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $mensajes['mensaje']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensajes['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $mensajes['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-truck me-2"></i>Información de la Expedición
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>N° Expedición:</strong></td>
                                        <td><?php echo htmlspecialchars($expedicion['numero_expedicion']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Estado:</strong></td>
                                        <td>
                                            <span class="badge <?php echo $expedicion['estado_badge_class']; ?>">
                                                <?php echo htmlspecialchars($expedicion['estado']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Fecha Creación:</strong></td>
                                        <td><?php echo $expedicion['fecha_creacion_formateada']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Usuario Creación:</strong></td>
                                        <td><?php echo htmlspecialchars($expedicion['usuario_creacion'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <?php if ($expedicion['fecha_despacho_formateada']): ?>
                                        <tr>
                                            <td><strong>Fecha Despacho:</strong></td>
                                            <td><?php echo $expedicion['fecha_despacho_formateada']; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Usuario Despacho:</strong></td>
                                            <td><?php echo htmlspecialchars($expedicion['usuario_despacho'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Transportista:</strong></td>
                                        <td><?php echo htmlspecialchars($expedicion['transportista'] ?? 'Sin asignar'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Conductor:</strong></td>
                                        <td><?php echo htmlspecialchars($expedicion['conductor'] ?? 'Sin asignar'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Placa Vehículo:</strong></td>
                                        <td><?php echo htmlspecialchars($expedicion['placa_vehiculo'] ?? 'Sin asignar'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Tipo Vehículo:</strong></td>
                                        <td><?php echo htmlspecialchars($expedicion['tipovehiculo'] ?? 'Sin especificar'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Destino:</strong></td>
                                        <td><?php echo htmlspecialchars($expedicion['destino'] ?? 'Sin destino'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Peso Expedición:</strong></td>
                                        <td><?php echo htmlspecialchars($expedicion['peso_expedicion'] ?? 'Sin especificar'); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Sección de Observaciones y Descripción -->
                        <?php if (!empty($expedicion['observaciones']) || !empty($expedicion['descripcion']) || $controller->verificarPermisos('editar_descripcion')): ?>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <?php if (!empty($expedicion['observaciones'])): ?>
                                        <h6><strong>Observaciones:</strong></h6>
                                        <p class="text-muted mb-3"><?php echo nl2br(htmlspecialchars($expedicion['observaciones'])); ?></p>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6><strong>Descripción:</strong></h6>
                                            <div id="descripcion-contenido">
                                                <?php if (!empty($expedicion['descripcion'])): ?>
                                                    <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars($expedicion['descripcion'])); ?></p>
                                                <?php else: ?>
                                                    <p class="text-muted fst-italic mb-0">Sin descripción</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($controller->verificarPermisos('editar_descripcion')): ?>
                                            <button type="button" class="btn btn-outline-primary btn-sm ms-2"
                                                onclick="abrirModalDescripcion()"
                                                title="Editar descripción">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card" style="border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div class="card-header" style="padding: 0.75rem 1rem; background: linear-gradient(135deg, #2196F3, #21CBF3); color: white; border-radius: 8px 8px 0 0;">
                        <h6 class="mb-0" style="font-weight: 500; font-size: 0.9rem;">
                            <i class="fas fa-chart-bar me-2"></i>Resumen de Items
                        </h6>
                    </div>
                    <div class="card-body" style="padding: 1rem;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                            <div class="text-center" style="padding: 0.5rem; border-radius: 6px; transition: all 0.2s ease;">
                                <div style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.25rem;" class="text-primary">
                                    <?php echo number_format($resumen['total_items']); ?>
                                </div>
                                <p style="font-size: 0.75rem; color: #6c757d; margin: 0;">Total Items</p>
                            </div>
                            <div class="text-center" style="padding: 0.5rem; border-radius: 6px; transition: all 0.2s ease;">
                                <div style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.25rem;" class="text-info">
                                    <?php echo number_format($resumen['clientes_unicos']); ?>
                                </div>
                                <p style="font-size: 0.75rem; color: #6c757d; margin: 0;">Clientes</p>
                            </div>
                            <div class="text-center" style="padding: 0.5rem; border-radius: 6px; transition: all 0.2s ease;">
                                <div style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.25rem;" class="text-success">
                                    <?php echo number_format($resumen['productos_unicos']); ?>
                                </div>
                                <p style="font-size: 0.75rem; color: #6c757d; margin: 0;">Productos</p>
                            </div>
                            <!-- Mostrar bobinas o peso bruto -->
                            <?php if ($resumen['total_bobinas'] > 0): ?>
                                <div class="text-center" style="padding: 0.5rem; border-radius: 6px; transition: all 0.2s ease;">
                                    <div style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.25rem;" class="text-warning">
                                        <?php echo number_format($resumen['total_bobinas']); ?>
                                    </div>
                                    <p style="font-size: 0.75rem; color: #6c757d; margin: 0;">
                                        Bobinas
                                        <?php if ($resumen['paquetes_con_bobinas'] > 0): ?>
                                            <br><span style="font-size: 0.6rem;">(<?php echo $resumen['paquetes_con_bobinas']; ?> paquetes)</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="text-center" style="padding: 0.5rem; border-radius: 6px; transition: all 0.2s ease;">
                                    <div style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.25rem;" class="text-warning">
                                        <?php echo $resumen['peso_bruto_formateado']; ?>
                                    </div>
                                    <p style="font-size: 0.75rem; color: #6c757d; margin: 0;">P. Bruto</p>
                                </div>
                            <?php endif; ?>

                            <div class="text-center" style="padding: 0.5rem; border-radius: 6px; transition: all 0.2s ease; grid-column: 1 / -1;">
                                <div style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.25rem;" class="text-info">
                                    <?php echo $resumen['peso_escaneado_formateado']; ?>
                                </div>
                                <p style="font-size: 0.75rem; color: #6c757d; margin: 0;">Peso Escaneado</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!isset($_GET['agrupar']) || $_GET['agrupar'] !== 'true'): ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card border-0" style="background: #f8f9fa; border-radius: 8px;">
                        <div class="card-body py-2 px-3">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white border-end-0">
                                            <i class="fas fa-search text-muted"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control border-start-0"
                                            id="filtro-id-stock"
                                            placeholder="Filtrar por etiqueta..."
                                            style="box-shadow: none;">
                                        <button class="btn btn-outline-secondary btn-sm"
                                            type="button"
                                            id="limpiar-filtro-stock"
                                            title="Limpiar filtro">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-boxes me-2"></i>Items Escaneados en la Expedición
                    <?php if (isset($_GET['agrupar']) && $_GET['agrupar'] === 'true'): ?>
                        <span class="badge bg-info ms-2">Vista Agrupada</span>
                    <?php else: ?>
                        <span class="badge bg-secondary ms-2">Vista Individual</span>
                    <?php endif; ?>
                </h5>
                <div class="d-flex gap-2 align-items-center">
                    <?php if (isset($_GET['debug'])): ?>
                        <small class="text-muted">
                            Agrupar: <?php echo isset($_GET['agrupar']) ? $_GET['agrupar'] : 'false'; ?>
                        </small>
                    <?php endif; ?>
                    <button type="button"
                        class="btn <?php echo (isset($_GET['agrupar']) && $_GET['agrupar'] === 'true') ? 'btn-dark' : 'btn-outline-dark'; ?> btn-sm"
                        id="toggle-agrupamiento"
                        onclick="toggleAgrupamiento()"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="<?php echo (isset($_GET['agrupar']) && $_GET['agrupar'] === 'true') ? 'Cambiar a vista individual de items' : 'Agrupar items similares por producto y cliente'; ?>">
                        <i class="fas <?php echo (isset($_GET['agrupar']) && $_GET['agrupar'] === 'true') ? 'fa-list' : 'fa-layer-group'; ?> me-1"></i>
                        <?php echo (isset($_GET['agrupar']) && $_GET['agrupar'] === 'true') ? 'Vista Normal' : 'Agrupar Items'; ?>
                    </button>
                    <span class="badge bg-primary badge-custom">
                        <?php echo count($items); ?>
                        <?php echo (isset($_GET['agrupar']) && $_GET['agrupar'] === 'true') ? 'grupos' : 'items'; ?>
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($items)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-boxes fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No se encontraron items escaneados</h5>
                        <p class="text-muted">Esta expedición no tiene items registrados</p>
                    </div>
                <?php else: ?>
                    <?php if (isset($_GET['agrupar']) && $_GET['agrupar'] === 'true'): ?>
                        <div class="alert alert-info alert-sm mb-0" style="border-radius: 0;">
                            <i class="fas fa-info-circle me-1"></i>
                            <small>
                                Items agrupados por producto, dimensiones y cliente.
                                Los pesos y cantidades son sumados por grupo.
                                <strong>Grupos mostrados: <?php echo count($items); ?></strong>
                            </small>
                        </div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="items-table">
                            <thead class="table-light">
                                <tr>
                                    <th>
                                        <?php echo (isset($_GET['agrupar']) && $_GET['agrupar'] === 'true') ? 'Detalles' : 'Etiqueta'; ?>
                                    </th>
                                    <th>Producto</th>
                                    <th>Cliente Asignado</th>
                                    <th>Dimensiones</th>
                                    <th>
                                        Peso Escaneado
                                        <?php if (isset($_GET['agrupar']) && $_GET['agrupar'] === 'true'): ?>
                                            <small class="text-muted d-block">(Suma del grupo)</small>
                                        <?php endif; ?>
                                    </th>
                                    <th>Peso Bruto</th>
                                    <th>Peso Líquido</th>
                                    <th>
                                        <?php echo (isset($_GET['agrupar']) && $_GET['agrupar'] === 'true') ? 'Primer Escaneo' : 'Fecha Escaneo'; ?>
                                    </th>
                                    <th>Usuario</th>
                                    <th>
                                        <?php echo (isset($_GET['agrupar']) && $_GET['agrupar'] === 'true') ? 'Items en Grupo' : 'Asignación'; ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr <?php echo $item['cliente_reasignado'] ? 'class="table-warning"' : ''; ?>
                                        data-agrupado="<?php echo isset($_GET['agrupar']) && $_GET['agrupar'] === 'true' ? 'true' : 'false'; ?>">
                                        <td>
                                            <?php if (isset($_GET['agrupar']) && $_GET['agrupar'] === 'true' && isset($item['items_en_grupo']) && $item['items_en_grupo'] > 1): ?>
                                                <span class="badge bg-info"><?php echo $item['items_en_grupo']; ?> items</span>
                                            <?php else: ?>
                                                <strong class="text-primary">#<?php echo htmlspecialchars($item['stock_id']); ?></strong>
                                                <br><small class="text-muted">Item: <?php echo htmlspecialchars($item['numero_item']); ?></small>
                                            <?php endif; ?>

                                            <?php if ($item['id_orden_produccion']): ?>
                                                <br><small class="text-muted">Orden: <?php echo htmlspecialchars($item['id_orden_produccion']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['nombre_producto']); ?></strong>
                                            <br><span class="badge bg-info"><?php echo htmlspecialchars($item['tipo_producto']); ?></span>
                                            <?php if ($item['bobinas_pacote']): ?>
                                                <br><small class="text-muted"><?php echo number_format($item['bobinas_pacote']); ?> bobinas</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['cliente_asignado'] ?? 'Sin asignar'); ?></strong>
                                            <?php if ($item['cliente_reasignado']): ?>
                                                <br><small class="text-warning">
                                                    <i class="fas fa-exclamation-triangle"></i> Reasignado
                                                </small>
                                                <br><small class="text-muted">Original: <?php echo htmlspecialchars($item['cliente_original'] ?? 'N/A'); ?></small>
                                            <?php endif; ?>
                                            <?php if ($item['id_venta_asignado']): ?>
                                                <br><small class="text-muted">Venta: <?php echo htmlspecialchars($item['id_venta_asignado']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($item['dimensiones']): ?>
                                                <strong><?php echo htmlspecialchars($item['dimensiones']); ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">Sin dimensiones</span>
                                            <?php endif; ?>
                                            <?php if ($item['gramatura']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($item['gramatura']); ?>g</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong class="text-info"><?php echo $item['peso_escaneado_formateado']; ?></strong>
                                            <?php if (isset($item['items_en_grupo']) && $item['items_en_grupo'] > 1): ?>
                                                <br><small class="text-muted">
                                                    <i class="fas fa-layer-group"></i> <?php echo $item['items_en_grupo']; ?> items agrupados
                                                </small>
                                            <?php elseif ($item['cantidad_escaneada'] > 1): ?>
                                                <br><small class="text-muted">Cant: <?php echo number_format($item['cantidad_escaneada']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo $item['peso_bruto_formateado']; ?></strong>
                                        </td>
                                        <td>
                                            <strong class="text-success"><?php echo $item['peso_liquido_formateado']; ?></strong>
                                            <?php if ($item['tara_formateada'] && $item['tara_formateada'] !== '0 kg'): ?>
                                                <br><small class="text-muted">Tara: <?php echo $item['tara_formateada']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo $item['fecha_escaneado_formateada']; ?></strong>
                                            <?php if ($item['tiempo_desde_escaneo']): ?>
                                                <br><small class="text-muted">hace <?php echo $item['tiempo_desde_escaneo']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['usuario_escaneo'] ?? 'N/A'); ?></strong>
                                            <?php if ($item['usuario_produccion'] && $item['usuario_produccion'] !== $item['usuario_escaneo']): ?>
                                                <br><small class="text-muted">Maquina: <?php echo htmlspecialchars($item['usuario_produccion']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($item['items_en_grupo']) && $item['items_en_grupo'] > 1): ?>
                                                <span class="badge bg-success">
                                                    <?php echo $item['items_en_grupo']; ?> items
                                                </span>
                                                <br><small class="text-muted">Agrupados</small>
                                            <?php else: ?>
                                                <span class="badge <?php echo $item['modo_asignacion_badge']; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($item['modo_asignacion'] ?? 'N/A')); ?>
                                                </span>
                                                <br><small class="text-muted">Producido: <?php echo $item['fecha_producida_formateada']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="<?php echo $url_base; ?>secciones/despacho_seguimiento/main.php" class="btn btn-secondary btn-custom">
                    <i class="fas fa-arrow-left me-2"></i>Volver al Listado de Expediciones
                </a>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDescripcion" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Editar Descripción - Expedición <?php echo htmlspecialchars($numeroExpedicion); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formDescripcion">
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción:</label>
                            <textarea class="form-control"
                                id="descripcion"
                                name="descripcion"
                                rows="4"
                                maxlength="1000"
                                placeholder="Ingrese una descripción para esta expedición..."><?php echo htmlspecialchars($expedicion['descripcion'] ?? ''); ?></textarea>
                            <div class="form-text">
                                <span id="contador-caracteres">0</span>/1000 caracteres
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarDescripcion()">
                        <i class="fas fa-save me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $url_base; ?>secciones/despacho_seguimiento/js/despacho.js"></script>
    <script>
        const DESPACHO_CONFIG = <?php echo json_encode($configuracionJS); ?>;

        function abrirModalDescripcion() {
            const modal = new bootstrap.Modal(document.getElementById('modalDescripcion'));
            modal.show();

            // Actualizar contador de caracteres
            const textarea = document.getElementById('descripcion');
            const contador = document.getElementById('contador-caracteres');

            function actualizarContador() {
                contador.textContent = textarea.value.length;
            }

            textarea.addEventListener('input', actualizarContador);
            actualizarContador();
        }

        function guardarDescripcion() {
            const descripcion = document.getElementById('descripcion').value;
            const numeroExpedicion = '<?php echo htmlspecialchars($numeroExpedicion); ?>';

            // Mostrar loading en el botón
            const btnGuardar = event.target;
            const textoOriginal = btnGuardar.innerHTML;
            btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Guardando...';
            btnGuardar.disabled = true;

            // Crear formData
            const formData = new FormData();
            formData.append('numero_expedicion', numeroExpedicion);
            formData.append('descripcion', descripcion);

            // URL simplificada del archivo PHP
            const url = `${DESPACHO_CONFIG.url_base}secciones/despacho_seguimiento/actualizar_descripcion.php`;

            fetch(url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    // Verificar si la respuesta es OK
                    if (!response.ok) {
                        throw new Error(`Error HTTP: ${response.status}`);
                    }

                    // Verificar content-type
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        // Si no es JSON, obtener el texto para ver qué está devolviendo
                        return response.text().then(text => {
                            console.error('Respuesta no es JSON:', text);
                            throw new Error('El servidor devolvió: ' + text.substring(0, 200));
                        });
                    }

                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Actualizar el contenido en la página
                        const contenidoDescripcion = document.getElementById('descripcion-contenido');
                        if (descripcion.trim()) {
                            contenidoDescripcion.innerHTML = `<p class="text-muted mb-0">${descripcion.replace(/\n/g, '<br>')}</p>`;
                        } else {
                            contenidoDescripcion.innerHTML = '<p class="text-muted fst-italic mb-0">Sin descripción</p>';
                        }

                        // Cerrar modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('modalDescripcion'));
                        modal.hide();

                        // Mostrar mensaje de éxito
                        mostrarMensaje('success', data.mensaje || 'Descripción actualizada correctamente');
                    } else {
                        mostrarMensaje('error', data.error || 'Error al actualizar la descripción');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarMensaje('error', 'Error: ' + error.message);
                })
                .finally(() => {
                    // Restaurar botón
                    btnGuardar.innerHTML = textoOriginal;
                    btnGuardar.disabled = false;
                });
        }

        function mostrarMensaje(tipo, mensaje) {
            // Limpiar mensajes anteriores
            const mensajesAnteriores = document.querySelectorAll('.alert.mensaje-dinamico');
            mensajesAnteriores.forEach(msg => msg.remove());

            const alertClass = tipo === 'success' ? 'alert-success' : 'alert-danger';
            const iconClass = tipo === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

            const alertDiv = document.createElement('div');
            alertDiv.className = `alert ${alertClass} alert-dismissible fade show mensaje-dinamico`;
            alertDiv.innerHTML = `
        <i class="fas ${iconClass} me-2"></i>${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

            // Insertar al principio del container principal
            const container = document.querySelector('.container-fluid');
            const primerElemento = container.querySelector('nav[aria-label="breadcrumb"]');

            if (primerElemento) {
                container.insertBefore(alertDiv, primerElemento.nextElementSibling);
            } else {
                container.insertBefore(alertDiv, container.firstChild);
            }

            // Auto-eliminar después de 5 segundos
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);

            // Scroll suave hacia arriba para mostrar el mensaje
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
    </script>
</body>

</html>