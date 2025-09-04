<?php
include "../../auth/verificar_sesion.php";
include "../../config/conexionBD.php";
include "repository/productionRepository.php";
include "services/ProductionService.php";
include "services/PrintService.php";
include "controllers/ProductionController.php";
include "MaterialConsumptionManager.php";

requerirRol(['1', '2', '3']);
requerirLogin();

$productionController = new ProductionController($conexion);
MaterialConsumptionManager::initialize($conexion, true);
$datosVista = $productionController->manejarPeticion();
extract($datosVista);
$valorBusqueda = $productionController->obtenerValorBusqueda();
$esProductoSimplificado = false;
if ($ordenEncontrada && !empty($productosOrden)) {
    $tipoProducto = $productosOrden[0]['tipo'];
    $esProductoSimplificado = ($tipoProducto === 'TOALLITAS' || $tipoProducto === 'PAÑOS');
}
$breadcrumb_items = ['IMPRIMIR'];
$item_urls = [];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>America TNT - Imprimir</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="<?php echo $url_base; ?>utils/icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/produccion/utils/ordenproduccion.css">
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <!-- Contenido Principal -->
    <div class="main-container">
        <div class="container-fluid">
            <div class="production-card">
                <div class="form-section">

                    <!-- Mensajes -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-custom">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($mensaje): ?>
                        <div class="alert alert-success alert-custom">
                            <i class="fas fa-check-circle me-2"></i><?php echo $mensaje; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Búsqueda de orden -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <?php if (!$ordenEncontrada): ?>
                                <!-- ESTADO: No hay orden cargada - Mostrar búsqueda -->
                                <form method="POST" action="" id="formBuscarOrden">
                                    <input type="hidden" name="buscar_orden" value="1">
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text">
                                            <i class="fas fa-hashtag"></i>
                                        </span>
                                        <input type="number" class="form-control" name="numero_orden"
                                            id="numero_orden"
                                            placeholder="Número de orden de producción..."
                                            value="<?php echo $valorBusqueda; ?>"
                                            required min="1" autofocus>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-2"></i>Buscar Orden
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <!-- ESTADO: Orden cargada - Mostrar información y botón cambiar -->
                                <div class="orden-cargada-container">
                                    <div class="d-flex align-items-center justify-content-between bg-light p-3 rounded border">
                                        <div class="orden-info">
                                            <h5 class="mb-1 text-primary">
                                                <i class="fas fa-clipboard-check me-2"></i>
                                                Orden #<?php echo $ordenEncontrada['id']; ?> Cargada
                                                <?php if ($ordenEncontrada && $ordenEncontrada['finalizado']): ?>
                                                    <span class="badge bg-success ms-2">
                                                        <i class="fas fa-flag-checkered me-1"></i>Finalizada
                                                    </span>
                                                <?php endif; ?>
                                            </h5>
                                            <p class="mb-1"><strong>Tipo:</strong>
                                                <?php
                                                echo $productosOrden[0]['tipo'];
                                                if ($esProductoSimplificado) {
                                                    echo ' <span class="badge bg-info ms-2">Modo B</span>';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                        <div class="acciones-orden">
                                            <button type="button" class="btn btn-primary" onclick="buscarNuevaOrden()">
                                                <i class="fas fa-plus me-2"></i>Buscar Nueva Orden
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($ordenEncontrada): ?>
                        <!-- Layout principal en dos columnas -->
                        <div class="row">
                            <!-- Columna izquierda: Información y registro -->
                            <div class="col-md-5">
                                <!-- Formulario dinámico según tipo de producto -->
                                <form method="POST" action="" id="formRegistrarProduccion">
                                    <input type="hidden" name="registrar_produccion" value="1">
                                    <input type="hidden" name="numero_orden" value="<?php echo $ordenEncontrada['id']; ?>">
                                    <input type="hidden" id="tipo_producto_actual" value="<?php echo $productosOrden[0]['tipo']; ?>">
                                    <input type="hidden" id="es_producto_simplificado" value="<?php echo $esProductoSimplificado ? '1' : '0'; ?>">

                                    <?php if ($esProductoSimplificado): ?>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group-custom">
                                                    <label class="form-label-custom">
                                                        <i class="fas fa-weight-hanging"></i>Peso Bruto (kg)
                                                    </label>
                                                    <div class="input-group input-group-custom">
                                                        <input type="number" class="form-control form-control-custom" name="peso_bruto"
                                                            id="peso_bruto"
                                                            placeholder="0.00" step="0.01" min="0"
                                                            required autofocus>
                                                        <span class="input-group-text">kg</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group-custom">
                                                    <label class="form-label-custom">
                                                        <i class="fas fa-minus-circle"></i>Tara (kg)
                                                    </label>
                                                    <div class="input-group input-group-custom">
                                                        <input type="number" class="form-control form-control-custom" name="tara"
                                                            id="tara"
                                                            placeholder="0.00" step="0.0001" min="0"
                                                            required>
                                                        <span class="input-group-text">kg</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group-custom">
                                                    <label class="form-label-custom">
                                                        <i class="fas fa-balance-scale"></i>Peso Líquido (kg)
                                                    </label>
                                                    <div class="input-group input-group-custom">
                                                        <input type="text" class="form-control form-control-custom"
                                                            id="peso_liquido_calculado"
                                                            placeholder="0.00" readonly
                                                            style="background-color: #f8f9fa;">
                                                        <span class="input-group-text">kg</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Campos ocultos con valores por defecto para productos simplificados -->
                                        <input type="hidden" name="metragem" value="0">
                                        <input type="hidden" name="largura" value="0">
                                        <input type="hidden" name="gramatura" value="0">
                                        <input type="hidden" name="bobinas_pacote" value="1">

                                        <!-- Información específica para el tipo -->
                                        <div class="row mt-2">
                                            <div class="col-md-12">
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    <strong>Tipo de producto:</strong>
                                                    <?php echo $productosOrden[0]['tipo'] === 'TOALLITAS' ? 'Toallitas (Cajas)' : 'Paños'; ?>
                                                    - Solo se registra información de peso
                                                </small>
                                            </div>
                                        </div>

                                    <?php else: ?>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group-custom">
                                                    <label class="form-label-custom">
                                                        <i class="fas fa-weight-hanging"></i>Peso Bruto
                                                    </label>
                                                    <div class="input-group input-group-custom">
                                                        <input type="number" class="form-control form-control-custom" name="peso_bruto"
                                                            id="peso_bruto"
                                                            placeholder="0.00" step="0.01" min="0"
                                                            required autofocus>
                                                        <span class="input-group-text">kg</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group-custom">
                                                    <label class="form-label-custom">
                                                        <i class="fas fa-minus-circle"></i>Tara
                                                    </label>
                                                    <div class="input-group input-group-custom">
                                                        <input type="number" class="form-control form-control-custom" name="tara"
                                                            id="tara"
                                                            placeholder="0.00" step="0.0001" min="0"
                                                            required>
                                                        <span class="input-group-text">kg</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group-custom">
                                                    <label class="form-label-custom">
                                                        <i class="fas fa-balance-scale"></i>Peso Neto
                                                    </label>
                                                    <div class="input-group input-group-custom">
                                                        <input type="text" class="form-control form-control-custom"
                                                            id="peso_liquido_calculado"
                                                            placeholder="0.00" readonly
                                                            style="background-color: #f8f9fa;">
                                                        <span class="input-group-text">kg</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group-custom">
                                                    <label class="form-label-custom">
                                                        <i class="fas fa-ruler"></i>Metragem
                                                    </label>
                                                    <div class="input-group input-group-custom">
                                                        <input type="number"
                                                            class="form-control form-control-custom"
                                                            name="metragem"
                                                            id="metragem"
                                                            placeholder="0"
                                                            step="1"
                                                            min="0"
                                                            required
                                                            value="<?php echo intval($productosOrden[0]['longitud_bobina'] ?? 0); ?>">
                                                        <span class="input-group-text">m</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group-custom">
                                                    <label class="form-label-custom">
                                                        <i class="fas fa-ruler"></i>Largura
                                                    </label>
                                                    <div class="input-group input-group-custom">
                                                        <input type="number"
                                                            class="form-control form-control-custom"
                                                            name="largura"
                                                            id="largura"
                                                            placeholder="0"
                                                            step="0.001"
                                                            min="0"
                                                            readonly
                                                            style="background-color:rgba(178, 178, 178, 0.31);"
                                                            required
                                                            value="<?php echo floatval($productosOrden[0]['largura_metros'] ?? 0); ?>">
                                                        <span class="input-group-text">m</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group-custom">
                                                    <label class="form-label-custom">
                                                        <i class="fas fa-minus-circle"></i>Gramatura
                                                    </label>
                                                    <div class="input-group input-group-custom">
                                                        <input type="number"
                                                            class="form-control form-control-custom"
                                                            name="gramatura"
                                                            id="gramatura"
                                                            placeholder="0"
                                                            step="0.001"
                                                            min="0"
                                                            readonly
                                                            style="background-color:rgba(178, 178, 178, 0.31);"
                                                            required
                                                            value="<?php echo floatval($productosOrden[0]['gramatura'] ?? 0); ?>">
                                                        <span class="input-group-text">g/m²</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Campo Quantidade de Bobinas/Pacote (condicional) -->
                                            <div class="col-md-4" id="bobinas_pacote_container" style="display: none;">
                                                <div class="form-group-custom">
                                                    <label class="form-label-custom">
                                                        <i class="fas fa-boxes"></i>Quantidade de Bobinas/Pacote
                                                    </label>
                                                    <div class="input-group input-group-custom">
                                                        <input type="number"
                                                            class="form-control form-control-custom"
                                                            name="bobinas_pacote"
                                                            id="bobinas_pacote"
                                                            placeholder="0"
                                                            step="1"
                                                            min="1"
                                                            value="1">
                                                        <span class="input-group-text">unid</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Botones de acción -->
                                    <div class="btn-group-custom mt-3 d-flex gap-2 flex-nowrap">
                                        <!-- Botón registrar -->
                                        <button type="submit" class="btn btn-success action-btn-compact">
                                            <i class="fas fa-plus me-2"></i>Registrar
                                        </button>

                                        <button type="button" class="btn btn-warning action-btn-compact" id="btnReimprimirUnificado"
                                            onclick="reimprimirSeleccionada()" disabled>
                                            <i class="fas fa-print me-2"></i>Reimprimir
                                        </button>

                                        <button type="button" class="btn btn-outline-info action-btn-compact" id="btnAbrirPDFUnificado"
                                            onclick="abrirPDFSeleccionada()" disabled>
                                            <i class="fas fa-external-link-alt me-2"></i>Abrir PDF
                                        </button>

                                        <button type="button" class="btn btn-danger action-btn-compact" id="btnEliminarUnificado"
                                            onclick="eliminarRegistroSeleccionado()" disabled>
                                            <i class="fas fa-trash me-2"></i>Eliminar
                                        </button>

                                        <button type="button" class="btn btn-primary action-btn-compact" id="btnFinalizarOrden"
                                            onclick="finalizarOrden()" <?php echo (!$ordenEncontrada || $ordenEncontrada['finalizado']) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-flag-checkered me-2"></i>
                                            <?php echo ($ordenEncontrada && $ordenEncontrada['finalizado']) ? 'Finalizada' : 'Finalizar'; ?>
                                        </button>

                                        <button type="button" class="btn btn-info action-btn-compact" id="btnReimprimirLote"
                                            onclick="abrirModalReimpresionLote()"
                                            <?php echo (!$ordenEncontrada || empty($datosPaginacion['registros'])) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-print me-2"></i>Lote
                                        </button>

                                        <button type="button" class="btn btn-outline-secondary action-btn-compact" id="btnDesmarcarUnificado"
                                            onclick="desmarcarSeleccion()" disabled>
                                            <i class="fas fa-times me-2"></i>
                                        </button>
                                    </div>

                                    <small class="text-muted mt-2 d-block" id="reprint-help-unificado" style="display: none;">
                                        <i class="fas fa-hand-pointer me-1"></i>
                                        Seleccione una fila de la tabla para reimprimir su etiqueta
                                    </small>

                                    <!-- Información de selección actual -->
                                    <div class="alert alert-info mt-3" id="seleccion-info-unificado" style="display: none; border-radius: 8px;">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Registro seleccionado:</strong> <span id="info-registro-numero">#</span> -
                                                <span id="info-registro-tipo">TIPO</span> - <span id="info-registro-medidas">0kg</span>
                                            </div>
                                            <button type="button" class="btn-close" onclick="desmarcarSeleccion()"></button>
                                        </div>
                                    </div>
                                </form>

                                <!-- Progreso de producción -->
                                <?php if ($ordenEncontrada && $estadisticasProduccion['success']): ?>
                                    <div class="progress-info mt-3 p-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px; border-left: 4px solid <?php echo $estadisticasProduccion['completado'] ? '#28a745' : '#ffc107'; ?>;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1 text-secondary">
                                                    <i class="fas fa-chart-pie me-2"></i>Progreso de Producción
                                                </h6>
                                                <p class="mb-0">
                                                    <strong class="text-<?php echo $estadisticasProduccion['completado'] ? 'success' : 'warning'; ?>">
                                                        <?php echo $estadisticasProduccion['producido']; ?>
                                                    </strong>
                                                    <span class="text-muted">de</span>
                                                    <strong><?php echo $estadisticasProduccion['solicitado']; ?></strong>
                                                    <span class="text-muted"><?php echo $estadisticasProduccion['unidad']; ?></span>
                                                </p>
                                            </div>
                                            <div class="text-end">
                                                <div class="badge bg-<?php echo $estadisticasProduccion['completado'] ? 'success' : 'warning'; ?> fs-6">
                                                    <?php echo number_format($estadisticasProduccion['porcentaje'], 1); ?>%
                                                </div>
                                                <?php if ($estadisticasProduccion['completado']): ?>
                                                    <div class="text-success mt-1">
                                                        <i class="fas fa-check-circle me-1"></i>Completado
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Barra de progreso -->
                                        <div class="progress mt-2" style="height: 6px;">
                                            <div class="progress-bar bg-<?php echo $estadisticasProduccion['completado'] ? 'success' : 'warning'; ?>"
                                                style="width: <?php echo min(100, $estadisticasProduccion['porcentaje']); ?>%"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Diferencia de Peso (solo para productos no simplificados) -->
                                <?php if ($ordenEncontrada && !$esProductoSimplificado && isset($diferenciaPeso) && $diferenciaPeso['success']): ?>
                                    <div class="diferencia-peso-info mt-3 p-3" style="background: linear-gradient(135deg, <?php echo $diferenciaPeso['clase'] === 'success' ? '#f0fff4 0%, #e6fffa 100%' : '#fed7d7 0%, #feb2b2 100%'; ?>); border-radius: 8px; border-left: 4px solid <?php echo $diferenciaPeso['clase'] === 'success' ? '#28a745' : '#dc2626'; ?>;">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h6 class="mb-1 text-<?php echo $diferenciaPeso['clase']; ?>">
                                                    <i class="fas fa-balance-scale me-2"></i>Control de Peso
                                                </h6>
                                                <p class="mb-1"><strong>Peso Líquido Solicitado:</strong> <?php echo number_format($diferenciaPeso['peso_teorico'], 2); ?> KG</p>
                                                <p class="mb-1"><strong>Total Peso Líquido Producido:</strong> <?php echo number_format($diferenciaPeso['peso_producido'], 2); ?> KG</p>
                                                <p class="mb-0"><strong>Diferencia:</strong>
                                                    <span class="text-<?php echo $diferenciaPeso['clase']; ?>">
                                                        <?php echo ($diferenciaPeso['diferencia'] >= 0 ? '+' : '') . number_format($diferenciaPeso['diferencia'], 2); ?> KG
                                                        (<?php echo ($diferenciaPeso['porcentaje_diferencia'] >= 0 ? '+' : '') . number_format($diferenciaPeso['porcentaje_diferencia'], 2); ?>%)
                                                    </span>
                                                </p>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <div class="badge bg-<?php echo $diferenciaPeso['clase']; ?> fs-6 mb-2">
                                                    <?php echo $diferenciaPeso['estado']; ?>
                                                </div>
                                                <div class="text-<?php echo $diferenciaPeso['clase']; ?> mt-1">
                                                    <i class="fas fa-<?php echo $diferenciaPeso['dentro_tolerancia'] ? 'check-circle' : 'exclamation-triangle'; ?> me-1"></i>
                                                    <?php echo $diferenciaPeso['dentro_tolerancia'] ? 'Dentro de Tolerancia ±3%' : 'Fuera de Tolerancia ±3%'; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Formularios ocultos -->
                                <form method="POST" action="" id="formReimprimirUnificado" style="display: none;">
                                    <input type="hidden" name="reimprimir_etiqueta_unificado" value="1">
                                    <input type="hidden" name="numero_orden_reimprimir" id="orden_reimprimir_unificado" value="">
                                    <input type="hidden" name="tipo_producto_reimprimir" id="tipo_reimprimir_unificado" value="">
                                    <input type="hidden" name="id_stock_reimprimir" id="stock_reimprimir_unificado" value="">
                                </form>

                                <form method="POST" action="" id="formEliminarUnificado" style="display: none;">
                                    <input type="hidden" name="eliminar_registro" value="1">
                                    <input type="hidden" name="id_registro_eliminar" id="id_registro_eliminar" value="">
                                    <input type="hidden" name="numero_orden_eliminar" id="numero_orden_eliminar" value="">
                                </form>

                                <form method="POST" action="" id="formFinalizarOrden" style="display: none;">
                                    <input type="hidden" name="finalizar_orden" value="1">
                                    <input type="hidden" name="numero_orden_finalizar" id="numero_orden_finalizar" value="">
                                </form>
                            </div>

                            <!-- Columna derecha: Historial y estadísticas -->
                            <div class="col-md-7">
                                <!-- Información de la orden encontrada -->
                                <div class="orden-info-card mb-4 <?php echo ($ordenEncontrada && $ordenEncontrada['finalizado']) ? 'finalizada' : ''; ?>">

                                    <!-- AGREGAR ALERTA SI LA ORDEN ESTÁ FINALIZADA -->
                                    <?php if ($ordenEncontrada && $ordenEncontrada['finalizado']): ?>
                                        <div class="alert alert-orden-finalizada mb-3">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-flag-checkered fa-2x me-3"></i>
                                                <div>
                                                    <h6 class="mb-1"><strong>Orden de Producción Finalizada</strong></h6>
                                                    <p class="mb-0">Esta orden ha sido marcada como Finalizada.</p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="row">
                                        <div class="col-8">
                                            <h6 class="text-info mb-1">
                                                <i class="fas fa-check-circle me-2"></i>Orden #{<?php echo $ordenEncontrada['id']; ?>} Encontrada
                                                <?php if ($ordenEncontrada && $ordenEncontrada['finalizado']): ?>
                                                    <i class="fas fa-flag-checkered text-success ms-2" title="Orden Finalizada"></i>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="mb-1"><strong>Cliente:</strong> <?php echo htmlspecialchars($ordenEncontrada['cliente']); ?></p>
                                            <p class="mb-0"><strong>Producto:</strong> <?php echo htmlspecialchars($productosOrden[0]['descripcion']); ?> (<?php echo $productosOrden[0]['tipo']; ?>)</p>

                                            <!-- Mostrar fecha de finalización si está disponible -->
                                            <?php if ($ordenEncontrada && $ordenEncontrada['finalizado']): ?>
                                                <p class="mb-0"><small class="text-success"><i class="fas fa-calendar-check me-1"></i><strong>Estado:</strong> Orden Finalizada</small></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-4 text-end">
                                            <?php if (isset($recetaActiva)): ?>
                                                <span class="badge ms-2 fs-6 
        <?php
                                                switch ($recetaActiva['estado']) {
                                                    case 'activa':
                                                        echo 'bg-success';
                                                        break;
                                                    case 'sin_receta':
                                                        echo 'bg-warning';
                                                        break;
                                                    case 'sin_sistema':
                                                        echo 'bg-secondary';
                                                        break;
                                                    default:
                                                        echo 'bg-danger';
                                                        break;
                                                }
        ?>"
                                                    title="<?php echo htmlspecialchars($recetaActiva['mensaje']); ?>">
                                                    <i class="fas fa-<?php echo $recetaActiva['tiene_receta'] ? 'flask' : 'flask-vial'; ?> me-1"></i>
                                                    <?php
                                                    switch ($recetaActiva['estado']) {
                                                        case 'activa':
                                                            echo 'CON RECETA';
                                                            break;
                                                        case 'sin_receta':
                                                            echo 'SIN RECETA';
                                                            break;
                                                        case 'sin_sistema':
                                                            echo 'SIN SISTEMA';
                                                            break;
                                                        default:
                                                            echo 'ERROR RECETA';
                                                            break;
                                                    }
                                                    ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Búsqueda por ID -->
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <div class="input-group">
                                            <input type="number" id="buscar_id" class="form-control"
                                                value="<?= isset($_GET['filtro_id']) ? htmlspecialchars($_GET['filtro_id']) : '' ?>"
                                                placeholder="Buscar por # de etiqueta.">
                                            <button class="btn btn-primary" onclick="buscarPorId()">
                                                <i class="fas fa-search"></i> Buscar
                                            </button>
                                            <button class="btn btn-outline-secondary" onclick="limpiarFiltro()">
                                                <i class="fas fa-times"></i> Limpiar
                                            </button>
                                            <a class="btn btn-success" href="<?php echo $url_base; ?>secciones/produccion/diaria.php">
                                                <i class="fas fa-chart-line me-2"></i>Ver Producción Diaria
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Últimos registros -->
                                <?php
                                $totalRegistros = $datosPaginacion['total_registros'];
                                $totalPaginas = $datosPaginacion['total_paginas'];
                                $ultimosRegistros = $datosPaginacion['registros'];
                                ?>

                                <?php if (!empty($ultimosRegistros)): ?>
                                    <div class="ultimas-cajas">
                                        <div id="resultado_busqueda" style="display:none;"></div>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>#</th>
                                                        <?php if (!$esProductoSimplificado): ?>
                                                            <th>Estado</th>
                                                        <?php endif; ?>
                                                        <th>Peso Bruto</th>
                                                        <th>Peso Líquido</th>
                                                        <?php if (!$esProductoSimplificado): ?>
                                                            <th>Peso Teórico</th>
                                                            <th>Diferencia</th>
                                                        <?php endif; ?>
                                                        <th>Tara</th>
                                                        <?php if (!$esProductoSimplificado): ?>
                                                            <th>Metragem</th>
                                                            <th>Bobinas</th>
                                                        <?php endif; ?>
                                                        <th>Fecha</th>
                                                        <th>Hora</th>
                                                        <th>Etiqueta</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($ultimosRegistros as $registro): ?>
                                                        <tr class="registro-row <?php echo !$esProductoSimplificado ? $registro['clasificacion']['clase'] : ''; ?>"
                                                            data-id="<?php echo $registro['id']; ?>"
                                                            data-orden="<?php echo $ordenEncontrada['id']; ?>"
                                                            data-tipo="<?php echo htmlspecialchars($registro['tipo_producto']); ?>"
                                                            data-numero="<?php echo $registro['numero_item']; ?>">

                                                            <td>
                                                                <strong class="text-primary">#<?php echo $registro['numero_item']; ?></strong>
                                                                <br><small class="text-warning"><i class="fas fa-unlink"></i></small>
                                                            </td>

                                                            <!-- Estado - solo para productos no simplificados -->
                                                            <?php if (!$esProductoSimplificado): ?>
                                                                <td class="text-center">
                                                                    <div class="clasificacion-badge clasificacion-<?php echo $registro['clasificacion']['clase']; ?>"
                                                                        title="<?php echo $registro['clasificacion']['categoria']; ?>">
                                                                        <i class="fas fa-<?php echo $registro['clasificacion']['icono']; ?>"></i>
                                                                        <div class="clasificacion-texto">
                                                                            <?php
                                                                            switch ($registro['clasificacion']['clase']) {
                                                                                case 'dentro-media':
                                                                                    echo 'OK';
                                                                                    break;
                                                                                case 'pesado-05':
                                                                                    echo '+0.5%';
                                                                                    break;
                                                                                case 'liviano-3':
                                                                                    echo '-3%';
                                                                                    break;
                                                                                case 'liviano-4':
                                                                                    echo '-4%';
                                                                                    break;
                                                                                case 'muy-liviano':
                                                                                    echo '--';
                                                                                    break;
                                                                                case 'pesado-1':
                                                                                    echo '++';
                                                                                    break;
                                                                                case 'no-aplica':
                                                                                    echo 'N/A';
                                                                                    break;
                                                                                default:
                                                                                    echo '?';
                                                                                    break;
                                                                            }
                                                                            ?>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            <?php endif; ?>

                                                            <td><?php echo number_format($registro['peso_bruto'], 2); ?> kg</td>
                                                            <td><strong><?php echo number_format($registro['peso_liquido'], 2); ?> kg</strong></td>

                                                            <!-- Peso teórico y diferencia - solo para productos no simplificados -->
                                                            <?php if (!$esProductoSimplificado): ?>
                                                                <td class="text-muted">
                                                                    <?php if ($registro['peso_teorico'] > 0): ?>
                                                                        <?php echo number_format($registro['peso_teorico'], 2); ?> kg
                                                                    <?php else: ?>
                                                                        <small>N/A</small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-center">
                                                                    <?php if ($registro['peso_teorico'] > 0): ?>
                                                                        <span class="diferencia-badge diferencia-<?php echo $registro['clasificacion']['clase']; ?>">
                                                                            <?php echo number_format($registro['clasificacion']['diferencia'], 1); ?>%
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <small class="text-muted">N/A</small>
                                                                    <?php endif; ?>
                                                                </td>
                                                            <?php endif; ?>

                                                            <td><?php echo number_format($registro['tara'], 2); ?> kg</td>

                                                            <!-- Metragem y bobinas - solo para productos no simplificados -->
                                                            <?php if (!$esProductoSimplificado): ?>
                                                                <td><?php echo number_format($registro['metragem']); ?>m</td>
                                                                <td><?php echo number_format($registro['bobinas_pacote']); ?></td>
                                                            <?php endif; ?>

                                                            <td><?php echo $registro['fecha']; ?></td>
                                                            <td><?php echo $registro['hora']; ?></td>
                                                            <td><?php echo $registro['id']; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Paginación -->
                                        <?php if ($totalPaginas > 1): ?>
                                            <nav aria-label="Paginación de registros" class="pagination-custom">
                                                <ul class="pagination pagination-sm justify-content-center">
                                                    <?php if ($pagina_actual > 1): ?>
                                                        <li class="page-item">
                                                            <a class="page-link page-link-first" href="?pagina=1&orden=<?php echo $ordenEncontrada['id']; ?>#ultimos-registros"
                                                                title="Primera página">
                                                                <i class="fas fa-angle-double-left"></i>
                                                                <span class="d-none d-md-inline ms-1">Primera</span>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>

                                                    <?php if ($pagina_actual > 1): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?pagina=<?php echo $pagina_actual - 1; ?>&orden=<?php echo $ordenEncontrada['id']; ?>#ultimos-registros"
                                                                title="Página anterior">
                                                                <i class="fas fa-chevron-left"></i>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>

                                                    <?php if ($pagina_actual > 4): ?>
                                                        <li class="page-item disabled">
                                                            <span class="page-link">...</span>
                                                        </li>
                                                    <?php endif; ?>

                                                    <?php
                                                    $inicio = max(1, $pagina_actual - 2);
                                                    $fin = min($totalPaginas, $pagina_actual + 2);

                                                    if ($pagina_actual <= 3) {
                                                        $inicio = 1;
                                                        $fin = min($totalPaginas, 5);
                                                    } elseif ($pagina_actual >= $totalPaginas - 2) {
                                                        $inicio = max(1, $totalPaginas - 4);
                                                        $fin = $totalPaginas;
                                                    }

                                                    for ($i = $inicio; $i <= $fin; $i++):
                                                    ?>
                                                        <li class="page-item <?php echo $i === $pagina_actual ? 'active' : ''; ?>">
                                                            <a class="page-link" href="?pagina=<?php echo $i; ?>&orden=<?php echo $ordenEncontrada['id']; ?>#ultimos-registros">
                                                                <?php echo $i; ?>
                                                            </a>
                                                        </li>
                                                    <?php endfor; ?>

                                                    <?php if ($pagina_actual < $totalPaginas - 3): ?>
                                                        <li class="page-item disabled">
                                                            <span class="page-link">...</span>
                                                        </li>
                                                    <?php endif; ?>

                                                    <?php if ($pagina_actual < $totalPaginas): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?pagina=<?php echo $pagina_actual + 1; ?>&orden=<?php echo $ordenEncontrada['id']; ?>#ultimos-registros"
                                                                title="Página siguiente">
                                                                <i class="fas fa-chevron-right"></i>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>

                                                    <?php if ($pagina_actual < $totalPaginas): ?>
                                                        <li class="page-item">
                                                            <a class="page-link page-link-last" href="?pagina=<?php echo $totalPaginas; ?>&orden=<?php echo $ordenEncontrada['id']; ?>#ultimos-registros"
                                                                title="Última página">
                                                                <span class="d-none d-md-inline me-1">Última</span>
                                                                <i class="fas fa-angle-double-right"></i>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>

                                                <div class="pagination-info text-center mt-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Página <strong><?php echo $pagina_actual; ?></strong> de <strong><?php echo $totalPaginas; ?></strong>
                                                        (<?php echo $totalRegistros; ?> registros total)
                                                    </small>
                                                </div>
                                            </nav>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="ultimas-cajas" id="ultimos-registros">
                                        <h6 class="text-secondary mb-3">
                                            <i class="fas fa-history me-2"></i>Historial de Registros
                                        </h6>
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                            <p>No hay registros para esta orden</p>
                                            <small>¡Comience registrando el primer item!</small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Mensaje cuando no hay orden cargada -->
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-4x text-muted mb-4"></i>
                            <h4 class="text-muted">Busque una orden de producción para comenzar</h4>
                            <p class="text-muted">Ingrese el número de orden en el campo superior y haga clic en "Buscar Orden"</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Reimpresión en Lote -->
    <div class="modal fade" id="modalReimpresionLote" tabindex="-1" aria-labelledby="modalReimpresionLoteLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="modalReimpresionLoteLabel">
                        <i class="fas fa-print me-2"></i>Reimpresión en Lote - Orden #<?php echo $ordenEncontrada['id'] ?? ''; ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($ordenEncontrada && !empty($datosPaginacion['registros'])): ?>
                        <div class="alert alert-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong><i class="fas fa-info-circle me-2"></i>Producto:</strong>
                                    <?php echo htmlspecialchars($productosOrden[0]['descripcion'] ?? 'N/A'); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong><i class="fas fa-tag me-2"></i>Tipo:</strong>
                                    <?php echo htmlspecialchars($productosOrden[0]['tipo'] ?? 'N/A'); ?>
                                    <?php if ($esProductoSimplificado): ?>
                                        <span class="badge bg-info ms-2">Simplificado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <form id="formReimpresionLote" method="POST">
                            <input type="hidden" name="reimprimir_lote_etiquetas" value="1">
                            <input type="hidden" name="numero_orden_lote" value="<?php echo $ordenEncontrada['id']; ?>">
                            <input type="hidden" name="tipo_producto_lote" value="<?php echo $productosOrden[0]['tipo']; ?>">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="item_desde" class="form-label">
                                            <i class="fas fa-play me-2"></i>Desde Item #
                                        </label>
                                        <input type="number" class="form-control form-control-lg"
                                            id="item_desde" name="item_desde"
                                            min="1" required
                                            placeholder="Ej: 1">
                                        <small class="form-text text-muted">Número de item inicial</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="item_hasta" class="form-label">
                                            <i class="fas fa-stop me-2"></i>Hasta Item #
                                        </label>
                                        <input type="number" class="form-control form-control-lg"
                                            id="item_hasta" name="item_hasta"
                                            min="1" required
                                            placeholder="Ej: 10">
                                        <small class="form-text text-muted">Número de item final</small>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-secondary" id="preview-rango" style="display: none;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <strong><i class="fas fa-eye me-2"></i>Vista Previa:</strong>
                                        <span id="preview-texto">Items # al #</span>
                                    </div>
                                    <span class="badge bg-info" id="preview-cantidad">0 etiquetas</span>
                                </div>
                                <div class="mt-2" id="preview-advertencias"></div>
                            </div>
                        </form>

                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                            <h5 class="text-muted">No hay items disponibles</h5>
                            <p class="text-muted">Debe registrar al menos un item antes de poder reimprimir etiquetas en lote.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <?php if ($ordenEncontrada && !empty($datosPaginacion['registros'])): ?>
                        <button type="button" class="btn btn-info" id="btnConfirmarReimpresionLote" onclick="confirmarReimpresionLote()" disabled>
                            <i class="fas fa-print me-2"></i>Reimprimir Lote
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Scripts en orden de dependencia -->
    <script src="js/produccion/config.js"></script>
    <script src="js/produccion/weight-validator.js"></script>
    <script src="js/produccion/form-handler.js"></script>
    <script src="js/produccion/print-service.js"></script>
    <script src="js/produccion/batch-reprint.js"></script>
    <script src="js/produccion/ui-manager.js"></script>
    <script src="js/produccion/main.js"></script>

    <!-- Script principal de inicialización -->
    <script>
        // Configuración global de la aplicación
        window.APP_CONFIG = {
            esProductoSimplificado: <?php echo $esProductoSimplificado ? 'true' : 'false'; ?>,
            tipoProducto: '<?php echo $ordenEncontrada ? $productosOrden[0]['tipo'] : ''; ?>',
            numeroOrden: <?php echo $ordenEncontrada ? $ordenEncontrada['id'] : 'null'; ?>,
            autoPrintUrl: <?php echo isset($auto_print_url) && !empty($auto_print_url) ? '"' . htmlspecialchars($auto_print_url) . '"' : 'null'; ?>
        };

        <?php if (isset($auto_print_url) && !empty($auto_print_url)): ?>
            console.log("🔍 Inicializando con autoprint:", "<?php echo htmlspecialchars($auto_print_url); ?>");
            initializeAppUnificado({
                autoPrintUrl: '<?php echo htmlspecialchars($auto_print_url); ?>'
            });
        <?php else: ?>
            console.log("🔍 Inicializando sin autoprint");
            initializeAppUnificado({
                autoPrintUrl: null
            });
        <?php endif; ?>

        // Configurar validación de peso según tipo de producto
        <?php if (!$esProductoSimplificado && isset($datosPesoPromedio) && $datosPesoPromedio['success']): ?>
            console.log("📊 Configurando validación de peso TEÓRICO ±15% para <?php echo $datosPesoPromedio['bobinas_pacote']; ?> bobina(s):", <?php echo json_encode($datosPesoPromedio); ?>);
            configurarValidacionPeso(<?php echo json_encode($datosPesoPromedio); ?>);
        <?php elseif ($esProductoSimplificado): ?>
            console.log("📊 Producto simplificado - Validación de peso básica activada");
            configurarValidacionPeso({
                success: false,
                peso_teorico: 0,
                peso_promedio: 0,
                total_registros: 0,
                bobinas_pacote: 1,
                modo_simplificado: true
            });
        <?php else: ?>
            console.log("📊 Validación de peso teórico no disponible - datos faltantes");
            configurarValidacionPeso({
                success: false,
                peso_teorico: 0,
                peso_promedio: 0,
                total_registros: 0,
                bobinas_pacote: 1
            });
        <?php endif; ?>

        // Función para buscar nueva orden
        function buscarNuevaOrden() {
            const totalItems = <?php echo isset($datosPaginacion['total_registros']) ? $datosPaginacion['total_registros'] : 0; ?>;

            let mensaje = "¿Está seguro de que desea buscar una nueva orden?";

            if (totalItems > 0) {
                mensaje += `\n\nLa orden actual tiene ${totalItems} items registrados.`;
                mensaje += "\nEste cambio no afectará los datos guardados.";
            }

            if (confirm(mensaje)) {
                window.location.href = window.location.pathname;
            }
        }

        // Funciones de búsqueda
        function buscarPorId() {
            const filtroId = document.getElementById('buscar_id').value;
            if (filtroId) {
                const ordenId = <?php echo $ordenEncontrada['id'] ?? 'null'; ?>;
                if (ordenId) {
                    window.location.href = `?orden=${ordenId}&filtro_id=${filtroId}`;
                }
            }
        }

        function limpiarFiltro() {
            const ordenId = <?php echo $ordenEncontrada['id'] ?? 'null'; ?>;
            if (ordenId) {
                window.location.href = `?orden=${ordenId}`;
            }
        }

        // Inicialización cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Aplicación iniciada - Modo:', window.APP_CONFIG.esProductoSimplificado ? 'Simplificado' : 'Completo');

            // Configurar eventos específicos según el tipo de producto
            if (window.APP_CONFIG.esProductoSimplificado) {
                // Solo cálculo de peso líquido para productos simplificados
                const pesoBruto = document.getElementById('peso_bruto');
                const tara = document.getElementById('tara');

                if (pesoBruto && tara) {
                    function calcularPesoLiquido() {
                        const bruto = parseFloat(pesoBruto.value) || 0;
                        const taraVal = parseFloat(tara.value) || 0;
                        const liquido = Math.max(0, bruto - taraVal);

                        const pesoLiquidoElement = document.getElementById('peso_liquido_calculado');
                        if (pesoLiquidoElement) {
                            pesoLiquidoElement.value = liquido.toFixed(2);
                        }
                    }

                    pesoBruto.addEventListener('input', calcularPesoLiquido);
                    tara.addEventListener('input', calcularPesoLiquido);
                }
            }
        });
    </script>

    <!-- IFRAME OCULTO PARA IMPRESIÓN AUTOMÁTICA -->
    <?php if (isset($auto_print_url) && !empty($auto_print_url)): ?>
        <iframe id="print-frame-unificado"
            src="<?php echo htmlspecialchars($auto_print_url); ?>"
            style="display: none; width: 0; height: 0; border: none;"
            onload="autoPrintUnificado()">
        </iframe>

        <!-- Indicador visual de impresión -->
        <div id="print-indicator-unificado" class="position-fixed top-0 end-0 m-3" style="z-index: 9999; display: none;">
            <div class="alert alert-info alert-dismissible fade show shadow-lg" style="min-width: 300px;">
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm text-info me-3" role="status"></div>
                    <div>
                        <strong><i class="fas fa-print me-2"></i>Generando etiqueta...</strong>
                        <br><small>Verifique su impresora</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>
</body>

</html>