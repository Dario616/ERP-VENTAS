<?php
include "../../auth/verificar_sesion.php";
include "../../config/conexionBD.php";

// Importar el controlador
require_once __DIR__ . '/controllers/DetallesMpController.php';

requerirRol(['1', '2']);
requerirLogin();

// Verificar que se reciba el ID de materia prima
$id_materia = isset($_GET['id_materia']) ? intval($_GET['id_materia']) : 0;

if ($id_materia <= 0) {
    header("Location: main.php");
    exit();
}

// Crear instancia del controller
$detallesMpController = new DetallesMpController($conexion);

// Manejar búsqueda AJAX de código de barras
if (isset($_POST['accion']) && $_POST['accion'] === 'buscar_barcode' && isset($_POST['barcode'])) {
    header('Content-Type: application/json');
    $resultado = $detallesMpController->buscarPorCodigoBarras($_POST['barcode']);
    echo json_encode($resultado);
    exit();
}

// Manejar petición AJAX para detalles de proveedor
if (isset($_POST['accion']) && $_POST['accion'] === 'obtener_detalles_proveedor' && isset($_POST['proveedor'])) {
    header('Content-Type: application/json');
    $resultado = $detallesMpController->obtenerDetallesProveedorAjax($id_materia, $_POST['proveedor']);
    echo json_encode($resultado);
    exit();
}

// Manejar toda la petición en el controller (con PRG para acciones no-AJAX)
$datosVista = $detallesMpController->manejarPeticion($id_materia);

// Si $datosVista es null, significa que se hizo un redirect
if ($datosVista === null) {
    exit(); // Ya se redirigió, no continuar
}

// Extraer datos para usar en la vista
extract($datosVista);

// Verificar que existe la materia prima
if (!$materiaPrima) {
    header("Location: main.php");
    exit();
}
?>


<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>America TNT - Registrar Materiales</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="<?php echo $url_base; ?>utils/icon.ico" type="image/x-icon">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/materiaprima/utils/styles.css">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $url_base; ?>index.php">
                <img src="<?php echo $url_base; ?>utils/logoa.png" alt="America TNT" height="30">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $url_base; ?>index.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $url_base; ?>secciones/materiaprima/main.php">
                            <i class="fas fa-boxes"></i>
                            Materiales
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $url_base; ?>secciones/materiaprima/materia_prima.php">
                            <i class="fas fa-boxes me-1"></i>Configurar Materiales
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="fas fa-list me-1"></i>Registrar Materiales
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="main-container">
        <div class="container-fluid">
            <div class="production-card">
                <!-- Header de la sección -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-1 text-primary">
                            <i class="fas fa-list me-2"></i>Registrar Materiales:
                            <strong><?php echo htmlspecialchars($materiaPrima['descripcion']); ?></strong>
                        </h4>
                    </div>
                    <div class="quick-actions">
                        <a href="materia_prima.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver
                        </a>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalBarcode">
                            <i class="fas fa-barcode me-2"></i>Escanear Código
                        </button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalDetalle" onclick="prepararModalNuevo()">
                            <i class="fas fa-plus me-2"></i>Registrar
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="toggleBusqueda()">
                            <i class="fas fa-search me-2"></i>Buscar
                        </button>
                    </div>
                </div>

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

                <!-- Panel de búsqueda -->
                <div class="form-section" id="panelBusqueda" style="display: none;">
                    <div class="card border-info mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-search me-2"></i>Buscar Detalles
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="">
                                <input type="hidden" name="id_materia" value="<?php echo $id_materia; ?>">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group-custom">
                                            <label class="form-label-custom">Materia Prima</label>
                                            <input type="text" class="form-control form-control-custom"
                                                name="buscar_descripcion"
                                                value="<?php echo htmlspecialchars($filtros['buscar_descripcion'] ?? ''); ?>"
                                                placeholder="Buscar por materia prima...">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group-custom">
                                            <label class="form-label-custom">Código Único</label>
                                            <input type="text" class="form-control form-control-custom"
                                                name="buscar_codigo"
                                                value="<?php echo htmlspecialchars($filtros['buscar_codigo'] ?? ''); ?>"
                                                placeholder="Buscar por código...">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group-custom">
                                            <label class="form-label-custom">Proveedor</label>
                                            <input type="text" class="form-control form-control-custom"
                                                name="buscar_proveedor"
                                                value="<?php echo htmlspecialchars($filtros['buscar_proveedor'] ?? ''); ?>"
                                                placeholder="Buscar por proveedor...">
                                        </div>
                                    </div>
                                </div>
                                <div class="btn-group-custom mt-2">
                                    <button type="submit" class="btn btn-info action-btn-compact">
                                        <i class="fas fa-search me-2"></i>Buscar
                                    </button>
                                    <a href="?id_materia=<?php echo $id_materia; ?>" class="btn btn-outline-secondary action-btn-compact">
                                        <i class="fas fa-times me-2"></i>Limpiar
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Controles de vista -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <!-- Botones para cambiar vista -->
                    <div class="btn-group btn-group-sm" role="group">
                        <?php if (!empty($proveedor_seleccionado)): ?>
                            <a href="?id_materia=<?php echo $id_materia; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Volver a Proveedores
                            </a>
                        <?php else: ?>
                            <a href="?id_materia=<?php echo $id_materia; ?>&vista=agrupada"
                                class="btn <?php echo $vista_agrupada ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                <i class="fas fa-layer-group me-1"></i>Por Proveedor
                            </a>
                            <a href="?id_materia=<?php echo $id_materia; ?>&vista=individual"
                                class="btn <?php echo !$vista_agrupada ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                <i class="fas fa-list me-1"></i>Individual
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Listado de detalles -->
                <div class="ultimas-cajas">
                    <?php if (!empty($datosPaginacion['registros'])): ?>
                        <div class="table-responsive">
                            <?php if ($vista_agrupada && empty($proveedor_seleccionado)): ?>
                                <!-- TABLA AGRUPADA POR PROVEEDOR -->
                                <table class="table table-sm table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Proveedor</th>
                                            <th><i class="fas fa-boxes me-1"></i>Registros</th>
                                            <!-- NUEVO: Mostrar columna cantidad total solo si la unidad es "Unidad" -->
                                            <?php if (isset($materiaPrima['unidad']) && strtolower($materiaPrima['unidad']) === 'unidad'): ?>
                                                <th><i class="fas fa-sort-numeric-up me-1"></i>Total Cantidad</th>
                                            <?php endif; ?>
                                            <th><i class="fas fa-calendar me-1"></i>Último Registro</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($datosPaginacion['registros'] as $grupo): ?>
                                            <tr class="proveedor-row" data-proveedor="<?php echo htmlspecialchars($grupo['proveedor_agrupado']); ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($grupo['es_sin_proveedor']): ?>
                                                            <i class="fas fa-question-circle text-muted me-2"></i>
                                                            <span class="text-muted fst-italic"><?php echo $grupo['proveedor_agrupado']; ?></span>
                                                        <?php else: ?>
                                                            <i class="fas fa-truck text-primary me-2"></i>
                                                            <strong><?php echo htmlspecialchars($grupo['proveedor_agrupado']); ?></strong>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span><?php echo $grupo['total_detalles']; ?></span>
                                                </td>

                                                <!-- NUEVO: Mostrar total cantidad solo si la unidad es "Unidad" -->
                                                <?php if (isset($materiaPrima['unidad']) && strtolower($materiaPrima['unidad']) === 'unidad'): ?>
                                                    <td>
                                                        <?php if (isset($grupo['total_cantidad']) && $grupo['total_cantidad'] > 0): ?>
                                                            <span class="badge bg-info">
                                                                <i class="fas fa-sort-numeric-up me-1"></i>
                                                                <?php echo number_format($grupo['total_cantidad']); ?> unidades
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">0 unidades</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>

                                                <td>
                                                    <div class="fecha-completa">
                                                        <span class="fecha-badge">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <?php echo $grupo['fecha_ultimo_registro_formateada']; ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary btn-sm"
                                                            onclick="verDetallesProveedor('<?php echo htmlspecialchars($grupo['proveedor_agrupado']); ?>')"
                                                            title="Ver detalles">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <a href="?id_materia=<?php echo $id_materia; ?>&vista=individual&proveedor=<?php echo urlencode($grupo['proveedor_agrupado']); ?>"
                                                            class="btn btn-outline-secondary btn-sm" title="Ver en página separada">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <!-- TABLA INDIVIDUAL -->
                                <table class="table table-sm table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Materia Prima</th>
                                            <!-- NUEVO: Mostrar columna cantidad solo si la unidad es "Unidad" -->
                                            <?php if (isset($materiaPrima['unidad']) && strtolower($materiaPrima['unidad']) === 'unidad'): ?>
                                                <th><i class="fas fa-sort-numeric-up me-1"></i>Cantidad</th>
                                            <?php endif; ?>
                                            <th>Peso</th>
                                            <th>Factura</th>
                                            <th>Proveedor</th>
                                            <th>Código Único</th>
                                            <th>Código de Barras</th>
                                            <th><i class="fas fa-calendar-alt me-1"></i>Fecha/Hora</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $registros = isset($datosPaginacion['registros']) ? $datosPaginacion['registros'] : [];
                                        foreach ($registros as $registro):
                                        ?>
                                            <tr class="detalle-row" data-id="<?php echo $registro['id']; ?>">
                                                <td><strong class="text-primary">#<?php echo $registro['id']; ?></strong></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($registro['descripcion_materia']); ?></strong>
                                                    <br><small class="text-muted">MP #<?php echo $registro['id_materia']; ?></small>
                                                </td>

                                                <!-- NUEVO: Mostrar cantidad solo si la unidad es "Unidad" -->
                                                <?php if (isset($materiaPrima['unidad']) && strtolower($materiaPrima['unidad']) === 'unidad'): ?>
                                                    <td>
                                                        <?php if (isset($registro['cantidad']) && $registro['cantidad'] > 0): ?>
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-sort-numeric-up me-1"></i>
                                                                <?php echo number_format($registro['cantidad']); ?> unidades
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>

                                                <td><?php echo $registro['peso'] > 0 ? number_format($registro['peso'], 2) . ' kg' : '<span class="text-muted">-</span>'; ?></td>
                                                <td><?php echo htmlspecialchars($registro['factura'] ?: '-'); ?></td>
                                                <td><?php echo htmlspecialchars($registro['proveedor'] ?: '-'); ?></td>
                                                <td>
                                                    <code class="codigo-unico"><?php echo htmlspecialchars($registro['codigo_unico']); ?></code>
                                                </td>
                                                <td><?php echo !empty($registro['barcode']) ? '<code>' . htmlspecialchars($registro['barcode']) . '</code>' : '<span class="text-muted">-</span>'; ?></td>
                                                <td>
                                                    <?php if (!empty($registro['fecha_formateada'])): ?>
                                                        <div class="fecha-completa">
                                                            <span class="fecha-badge">
                                                                <i class="fas fa-calendar me-1"></i>
                                                                <?php echo htmlspecialchars($registro['fecha_solo'] ?? 'N/A'); ?>
                                                            </span>
                                                            <br>
                                                            <span class="hora-badge">
                                                                <i class="fas fa-clock me-1"></i>
                                                                <?php echo htmlspecialchars($registro['hora_solo'] ?? 'N/A'); ?>
                                                            </span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">
                                                            <i class="fas fa-question-circle me-1"></i>N/A
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-warning btn-sm"
                                                            onclick="editarDetalle(<?php echo htmlspecialchars(json_encode($registro)); ?>)"
                                                            title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm"
                                                            onclick="eliminarDetalle(<?php echo $registro['id']; ?>, '<?php echo htmlspecialchars($registro['descripcion_materia']); ?>')"
                                                            title="Eliminar">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                        <!-- Paginación -->
                        <?php if ($datosPaginacion['total_paginas'] > 1): ?>
                            <nav aria-label="Paginación" class="pagination-custom">
                                <ul class="pagination pagination-sm justify-content-center">
                                    <!-- Botón Anterior -->
                                    <?php if ($pagina_actual > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?id_materia=<?php echo $id_materia; ?>&pagina=<?php echo $pagina_actual - 1;
                                                                                                                        echo $filtrosUrl; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <!-- Números de página -->
                                    <?php
                                    $inicio = max(1, $pagina_actual - 2);
                                    $fin = min($datosPaginacion['total_paginas'], $pagina_actual + 2);

                                    for ($i = $inicio; $i <= $fin; $i++):
                                    ?>
                                        <li class="page-item <?php echo $i === $pagina_actual ? 'active' : ''; ?>">
                                            <a class="page-link" href="?id_materia=<?php echo $id_materia; ?>&pagina=<?php echo $i;
                                                                                                                        echo $filtrosUrl; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <!-- Botón Siguiente -->
                                    <?php if ($pagina_actual < $datosPaginacion['total_paginas']): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?id_materia=<?php echo $id_materia; ?>&pagina=<?php echo $pagina_actual + 1;
                                                                                                                        echo $filtrosUrl; ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-clipboard-list fa-3x mb-3 d-block"></i>
                            <?php if ($vista_agrupada): ?>
                                <h5>No hay proveedores registrados</h5>
                                <p>¡Comience agregando el primer detalle para esta materia prima!</p>
                            <?php else: ?>
                                <h5>No hay detalles registrados</h5>
                                <p>¡Comience agregando el primer detalle para esta materia prima!</p>
                            <?php endif; ?>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalDetalle" onclick="prepararModalNuevo()">
                                <i class="fas fa-plus me-2"></i>Agregar Primer Detalle
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Código de Barras -->
    <div class="modal fade" id="modalBarcode" tabindex="-1" aria-labelledby="modalBarcodeLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalBarcodeLabel">
                        <i class="fas fa-barcode me-2"></i>
                        Escanear Código de Barras
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group-custom mb-3">
                                <label class="form-label-custom">
                                    <i class="fas fa-barcode text-success me-1"></i>Código de Barras <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control form-control-custom"
                                    id="inputBarcode"
                                    placeholder="Escanee o escriba el código de barras..."
                                    autocomplete="off">
                                <small class="text-muted">Enfoque este campo y escanee el código, o escríbalo manualmente</small>
                            </div>
                        </div>
                    </div>

                    <!-- Estado de búsqueda -->
                    <div id="estadoBusqueda" class="d-none">
                        <div class="alert alert-info">
                            <i class="fas fa-search fa-spin me-2"></i>Buscando código de barras...
                        </div>
                    </div>

                    <!-- Resultado encontrado -->
                    <div id="resultadoEncontrado" class="d-none">
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>¡Código encontrado! Los datos se han cargado.
                        </div>
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Datos encontrados
                                </h6>
                            </div>
                            <div class="card-body" id="datosEncontrados">
                                <!-- Los datos se cargarán aquí -->
                            </div>
                        </div>
                    </div>

                    <!-- No encontrado -->
                    <div id="noEncontrado" class="d-none">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>No se encontró ningún detalle con este código de barras.
                            <br><small>Puede usar el botón "Nuevo Detalle" para crear uno manualmente.</small>
                        </div>
                    </div>

                    <!-- Error -->
                    <div id="errorBusqueda" class="d-none">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>Error al buscar el código: <span id="mensajeError"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" onclick="limpiarBusquedaBarcode()">
                        <i class="fas fa-broom me-2"></i>Limpiar Campo
                    </button>
                    <button type="button" class="btn btn-success" id="btnUsarDatos" onclick="usarDatosEncontrados()" disabled>
                        <i class="fas fa-edit me-2"></i>Completar
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Detalle - SECCIÓN ACTUALIZADA -->
    <div class="modal fade" id="modalDetalle" tabindex="-1" aria-labelledby="modalDetalleLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalDetalleLabel">
                        <i class="fas fa-plus-circle me-2"></i>
                        <span id="tituloModal">Registrar Nuevo Detalle</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="formDetalle">
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accionFormulario" value="crear">
                        <input type="hidden" name="id_editar" id="idEditar" value="">

                        <!-- Información de la materia prima -->
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Materia Prima:</strong> <?php echo htmlspecialchars($materiaPrima['descripcion']); ?>
                            <br><small>Unidad: <strong><?php echo htmlspecialchars($materiaPrima['unidad'] ?? 'N/A'); ?></strong></small>
                            <br><small>La descripción se toma automáticamente de la materia prima seleccionada</small>
                        </div>

                        <!-- Alerta cuando se usan datos de código de barras -->
                        <div class="alert alert-warning d-none" id="alertaDatosBarcode">
                            <i class="fas fa-barcode me-2"></i>
                            <strong>Datos cargados desde código de barras:</strong>
                            <span id="codigoBarcodeUsado"></span>
                        </div>

                        <div class="row">
                            <!-- NUEVO: Campo Cantidad (solo si unidad = "Unidad") -->
                            <?php if (isset($materiaPrima['unidad']) && strtolower($materiaPrima['unidad']) === 'unidad'): ?>
                                <div class="col-md-6">
                                    <div class="form-group-custom mb-3">
                                        <label class="form-label-custom">
                                            <i class="fas fa-sort-numeric-up text-primary me-1"></i>Cantidad
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" class="form-control form-control-custom"
                                            name="cantidad" id="cantidad" min="1" step="1" placeholder="0" required>
                                        <small class="text-success"><strong>Obligatorio para unidades discretas</strong></small>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="col-md-6">
                                <div class="form-group-custom mb-3">
                                    <label class="form-label-custom">
                                        <i class="fas fa-truck text-primary me-1"></i>Proveedor
                                    </label>
                                    <input type="text" class="form-control form-control-custom"
                                        name="proveedor" id="proveedor" placeholder="Nombre del proveedor">
                                    <small class="text-muted">Opcional</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-custom mb-3">
                                    <label class="form-label-custom">
                                        <i class="fas fa-weight text-primary me-1"></i>Peso (kg)
                                        <span class="text-danger" id="pesoObligatorio" style="display: none;">*</span>
                                    </label>
                                    <input type="number" class="form-control form-control-custom"
                                        name="peso" id="peso" step="0.01" placeholder="0.00">
                                    <small class="text-muted" id="pesoAyuda">Opcional</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-custom mb-3">
                                    <label class="form-label-custom">
                                        <i class="fas fa-file-invoice text-primary me-1"></i>Factura
                                    </label>
                                    <input type="text" class="form-control form-control-custom"
                                        name="factura" id="factura" maxlength="100" placeholder="Número de factura">
                                    <small class="text-muted">Opcional</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-custom mb-3">
                                    <label class="form-label-custom">
                                        <i class="fas fa-barcode text-primary me-1"></i>Código de Barras
                                    </label>
                                    <input type="text" class="form-control form-control-custom"
                                        name="barcode" id="barcode" placeholder="Escanear o escribir código">
                                    <small class="text-muted">Opcional - Para verificar duplicados</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="limpiarFormulario()">
                            <i class="fas fa-broom me-2"></i>Limpiar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <span id="textoBotonGuardar">Registrar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- MODAL PARA VER DETALLES DE PROVEEDOR -->
    <div class="modal fade" id="modalDetallesProveedor" tabindex="-1" aria-labelledby="modalDetallesProveedorLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="modalDetallesProveedorLabel">
                        <i class="fas fa-eye me-2"></i>
                        Detalles del Proveedor: <span id="nombreProveedorModal"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Estado de carga -->
                    <div id="estadoCargaDetalles" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2">Cargando detalles del proveedor...</p>
                    </div>

                    <!-- Contenido de detalles -->
                    <div id="contenidoDetallesProveedor" class="d-none">
                        <!-- Los detalles se cargarán aquí -->
                    </div>

                    <!-- Error -->
                    <div id="errorDetallesProveedor" class="d-none">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <span id="mensajeErrorDetalles"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="btnVerEnPagina">
                        <i class="fas fa-external-link-alt me-2"></i>Ver en Página Separada
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario oculto para eliminación -->
    <form method="POST" action="" id="formEliminar" style="display: none;">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id_eliminar" id="idEliminar" value="">
    </form>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript personalizado -->
    <script>
        // Variables globales
        let modalDetalle;
        let modalBarcode;
        let modalDetallesProveedor;
        let datosUltimoEscaneo = null;
        let timerBusqueda = null;
        let esDatosDeEscaneo = false;
        let proveedorActual = null;

        // Inicializar modales al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            modalDetalle = new bootstrap.Modal(document.getElementById('modalDetalle'));
            modalBarcode = new bootstrap.Modal(document.getElementById('modalBarcode'));
            modalDetallesProveedor = new bootstrap.Modal(document.getElementById('modalDetallesProveedor'));

            // Manejo de mensajes automáticos
            const alertSuccess = document.querySelector('.alert-success');
            const alertDanger = document.querySelector('.alert-danger');

            if (alertSuccess) {
                setTimeout(() => {
                    alertSuccess.style.opacity = '0';
                    setTimeout(() => alertSuccess.style.display = 'none', 300);
                }, 5000);
            }

            if (alertDanger) {
                setTimeout(() => {
                    alertDanger.style.opacity = '0';
                    setTimeout(() => alertDanger.style.display = 'none', 300);
                }, 6000);
            }

            // Configurar modal de código de barras
            document.getElementById('modalBarcode').addEventListener('shown.bs.modal', function() {
                document.getElementById('inputBarcode').focus();
                limpiarBusquedaBarcode();
            });

            // Búsqueda automática mientras se escribe
            document.getElementById('inputBarcode').addEventListener('input', function(e) {
                const barcode = e.target.value.trim();

                if (timerBusqueda) {
                    clearTimeout(timerBusqueda);
                }

                if (barcode.length >= 3) {
                    timerBusqueda = setTimeout(() => {
                        buscarCodigoBarras(barcode);
                    }, 500);
                } else {
                    limpiarResultadosBusqueda();
                }
            });

            document.getElementById('modalDetalle').addEventListener('shown.bs.modal', function() {
                document.getElementById('peso').focus();
            });

            document.getElementById('modalDetalle').addEventListener('hidden.bs.modal', function() {
                limpiarFormulario();
            });

            // Hover effects para filas de proveedor
            document.querySelectorAll('.proveedor-row').forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                    this.style.transform = 'translateX(2px)';
                    this.style.transition = 'all 0.2s ease';
                });

                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                    this.style.transform = '';
                });
            });
        });

        // Función para buscar código de barras
        async function buscarCodigoBarras(barcode) {
            if (!barcode || barcode.trim().length === 0) return;

            mostrarEstadoBusqueda('buscando');

            try {
                const formData = new FormData();
                formData.append('accion', 'buscar_barcode');
                formData.append('barcode', barcode.trim());

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }

                const resultado = await response.json();

                if (resultado.success && resultado.datos) {
                    datosUltimoEscaneo = resultado.datos;
                    mostrarDatosEncontrados(resultado.datos);
                } else {
                    mostrarEstadoBusqueda('no_encontrado');
                    datosUltimoEscaneo = null;
                }

            } catch (error) {
                console.error('Error buscando código:', error);
                mostrarEstadoBusqueda('error', error.message);
                datosUltimoEscaneo = null;
            }
        }

        // Mostrar diferentes estados de búsqueda
        function mostrarEstadoBusqueda(estado, mensaje = '') {
            limpiarResultadosBusqueda();

            const btnUsar = document.getElementById('btnUsarDatos');

            switch (estado) {
                case 'buscando':
                    document.getElementById('estadoBusqueda').classList.remove('d-none');
                    btnUsar.disabled = true;
                    break;
                case 'no_encontrado':
                    document.getElementById('noEncontrado').classList.remove('d-none');
                    btnUsar.disabled = true;
                    break;
                case 'error':
                    document.getElementById('errorBusqueda').classList.remove('d-none');
                    document.getElementById('mensajeError').textContent = mensaje;
                    btnUsar.disabled = true;
                    break;
            }
        }

        // Mostrar datos encontrados
        function mostrarDatosEncontrados(datos) {
            limpiarResultadosBusqueda();

            const contenedorDatos = document.getElementById('datosEncontrados');
            contenedorDatos.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Peso:</strong> ${datos.peso || 'N/A'} kg</p>
                        <p><strong>Factura:</strong> ${datos.factura || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Proveedor:</strong> ${datos.proveedor || 'N/A'}</p>
                        <p><strong>Código de Barras:</strong> <code>${datos.barcode}</code></p>
                    </div>
                </div>
            `;

            document.getElementById('resultadoEncontrado').classList.remove('d-none');
            document.getElementById('btnUsarDatos').disabled = false;
        }

        // Limpiar resultados de búsqueda
        function limpiarResultadosBusqueda() {
            document.getElementById('estadoBusqueda').classList.add('d-none');
            document.getElementById('resultadoEncontrado').classList.add('d-none');
            document.getElementById('noEncontrado').classList.add('d-none');
            document.getElementById('errorBusqueda').classList.add('d-none');
        }

        // Limpiar búsqueda de código de barras
        function limpiarBusquedaBarcode() {
            document.getElementById('inputBarcode').value = '';
            limpiarResultadosBusqueda();
            datosUltimoEscaneo = null;
            document.getElementById('btnUsarDatos').disabled = true;

            if (timerBusqueda) {
                clearTimeout(timerBusqueda);
            }

            setTimeout(() => {
                document.getElementById('inputBarcode').focus();
            }, 100);
        }

        // Usar datos encontrados
        function usarDatosEncontrados() {
            if (!datosUltimoEscaneo) {
                alert('No hay datos para usar');
                return;
            }

            modalBarcode.hide();
            cargarDatosEnFormulario(datosUltimoEscaneo);
            modalDetalle.show();
        }

        // Cargar datos en formulario
        function cargarDatosEnFormulario(datos) {
            prepararModalNuevo();

            esDatosDeEscaneo = true;

            document.getElementById('peso').value = '0';
            document.getElementById('factura').value = datos.factura || '';
            document.getElementById('proveedor').value = datos.proveedor || '';
            document.getElementById('barcode').value = datos.barcode || '';

            // NUEVO: Cargar cantidad si existe en los datos y el campo está presente
            if (document.getElementById('cantidad') && datos.cantidad) {
                document.getElementById('cantidad').value = datos.cantidad;
            }

            const alerta = document.getElementById('alertaDatosBarcode');
            document.getElementById('codigoBarcodeUsado').textContent = datos.barcode;
            alerta.classList.remove('d-none');

            hacerPesoObligatorio();
        }

        // Hacer el peso obligatorio
        function hacerPesoObligatorio() {
            const camposPeso = {
                campo: document.getElementById('peso'),
                etiqueta: document.getElementById('pesoObligatorio'),
                ayuda: document.getElementById('pesoAyuda')
            };

            camposPeso.etiqueta.style.display = 'inline';
            camposPeso.ayuda.innerHTML = '<span class="text-danger"><strong>Obligatorio para datos escaneados</strong></span>';
            camposPeso.campo.required = true;
            camposPeso.campo.classList.add('campo-obligatorio-escaneo');
        }

        // Quitar obligatoriedad del peso
        function quitarPesoObligatorio() {
            const camposPeso = {
                campo: document.getElementById('peso'),
                etiqueta: document.getElementById('pesoObligatorio'),
                ayuda: document.getElementById('pesoAyuda')
            };

            camposPeso.etiqueta.style.display = 'none';
            camposPeso.ayuda.innerHTML = 'Opcional';
            camposPeso.campo.required = false;
            camposPeso.campo.classList.remove('campo-obligatorio-escaneo');
        }

        // Preparar modal para nuevo registro
        function prepararModalNuevo() {
            limpiarFormulario();
        }

        // Toggle panel de búsqueda
        function toggleBusqueda() {
            const panelBusqueda = document.getElementById('panelBusqueda');
            const isVisible = panelBusqueda.style.display !== 'none';

            if (isVisible) {
                panelBusqueda.style.display = 'none';
            } else {
                panelBusqueda.style.display = 'block';
            }
        }

        // Limpiar formulario
        function limpiarFormulario() {
            document.getElementById('formDetalle').reset();
            document.getElementById('accionFormulario').value = 'crear';
            document.getElementById('idEditar').value = '';
            document.getElementById('tituloModal').textContent = 'Registrar Nuevo Detalle';
            document.getElementById('textoBotonGuardar').textContent = 'Registrar';

            document.getElementById('alertaDatosBarcode').classList.add('d-none');

            esDatosDeEscaneo = false;
            quitarPesoObligatorio();
        }

        // Editar detalle
        function editarDetalle(registro) {
            document.getElementById('accionFormulario').value = 'editar';
            document.getElementById('idEditar').value = registro.id;
            document.getElementById('tituloModal').textContent = 'Editar Detalle #' + registro.id;
            document.getElementById('textoBotonGuardar').textContent = 'Actualizar';

            document.getElementById('peso').value = registro.peso || '';
            document.getElementById('factura').value = registro.factura || '';
            document.getElementById('proveedor').value = registro.proveedor || '';
            document.getElementById('barcode').value = registro.barcode || '';

            // NUEVO: Cargar cantidad si el campo existe
            if (document.getElementById('cantidad')) {
                document.getElementById('cantidad').value = registro.cantidad || '';
            }

            document.getElementById('alertaDatosBarcode').classList.add('d-none');

            esDatosDeEscaneo = false;
            quitarPesoObligatorio();

            modalDetalle.show();
        }

        // Eliminar detalle
        function eliminarDetalle(id, descripcionMateria) {
            const modalHtml = `
                <div class="modal fade" id="modalConfirmarEliminar" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Confirmar eliminación
                                </h5>
                            </div>
                            <div class="modal-body">
                                <div class="text-center">
                                    <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                                    <h6>¿Está seguro de que desea eliminar este detalle?</h6>
                                    <p class="mb-0"><strong>"${descripcionMateria}"</strong></p>
                                    <small class="text-muted">Esta acción no se puede deshacer.</small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>Cancelar
                                </button>
                                <button type="button" class="btn btn-danger" onclick="confirmarEliminacion(${id})">
                                    <i class="fas fa-trash me-2"></i>Eliminar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHtml);

            const modalEliminar = new bootstrap.Modal(document.getElementById('modalConfirmarEliminar'));
            modalEliminar.show();

            document.getElementById('modalConfirmarEliminar').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        // Confirmar eliminación
        function confirmarEliminacion(id) {
            document.getElementById('idEliminar').value = id;
            document.getElementById('formEliminar').submit();
        }

        // Función para ver detalles de un proveedor
        async function verDetallesProveedor(proveedor) {
            if (!proveedor) {
                alert('Error: Proveedor no especificado');
                return;
            }

            proveedorActual = proveedor;

            document.getElementById('nombreProveedorModal').textContent = proveedor;
            document.getElementById('btnVerEnPagina').onclick = function() {
                window.open(`?id_materia=<?php echo $id_materia; ?>&vista=individual&proveedor=${encodeURIComponent(proveedor)}`, '_blank');
            };

            modalDetallesProveedor.show();
            mostrarEstadoCargaDetalles('cargando');

            try {
                const formData = new FormData();
                formData.append('accion', 'obtener_detalles_proveedor');
                formData.append('proveedor', proveedor);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }

                const resultado = await response.json();

                if (resultado.success && resultado.datos) {
                    mostrarDetallesProveedor(resultado.datos);
                } else {
                    mostrarEstadoCargaDetalles('error', resultado.error || 'No se pudieron cargar los detalles');
                }

            } catch (error) {
                console.error('Error cargando detalles:', error);
                mostrarEstadoCargaDetalles('error', 'Error de conexión: ' + error.message);
            }
        }

        // Mostrar diferentes estados de carga
        function mostrarEstadoCargaDetalles(estado, mensaje = '') {
            document.getElementById('estadoCargaDetalles').classList.add('d-none');
            document.getElementById('contenidoDetallesProveedor').classList.add('d-none');
            document.getElementById('errorDetallesProveedor').classList.add('d-none');

            switch (estado) {
                case 'cargando':
                    document.getElementById('estadoCargaDetalles').classList.remove('d-none');
                    break;
                case 'error':
                    document.getElementById('errorDetallesProveedor').classList.remove('d-none');
                    document.getElementById('mensajeErrorDetalles').textContent = mensaje;
                    break;
                case 'contenido':
                    document.getElementById('contenidoDetallesProveedor').classList.remove('d-none');
                    break;
            }
        }

        // Mostrar detalles del proveedor
        function mostrarDetallesProveedor(datos) {
    const contenedor = document.getElementById('contenidoDetallesProveedor');
    
    // Verificar si debe mostrar columna cantidad
    const materiaPrima = <?php echo json_encode($materiaPrima); ?>;
    const mostrarCantidad = materiaPrima && 
                           materiaPrima.unidad && 
                           materiaPrima.unidad.toLowerCase() === 'unidad';

    if (!datos.registros || datos.registros.length === 0) {
        contenedor.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-inbox fa-3x mb-3"></i>
                <h5>No hay registros para este proveedor</h5>
                <p>El proveedor "${datos.proveedor}" no tiene detalles registrados.</p>
            </div>
        `;
    } else {
        let encabezadoCantidad = '';
        if (mostrarCantidad) {
            encabezadoCantidad = '<th>Cantidad</th>';
        }
        
        contenedor.innerHTML = `
            <div class="alert alert-info mb-3">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Total de registros:</strong> ${datos.total_registros}
                ${mostrarCantidad && datos.registros.length > 0 ? 
                  '<br><strong>Total cantidad:</strong> ' + 
                  datos.registros.reduce((sum, reg) => sum + (parseInt(reg.cantidad) || 0), 0) + 
                  ' unidades' : ''}
            </div>
            
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            ${encabezadoCantidad}
                            <th>Peso</th>
                            <th>Factura</th>
                            <th>Código Único</th>
                            <th>Código de Barras</th>
                            <th>Fecha/Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${datos.registros.map(registro => {
                            let columnaCantidad = '';
                            if (mostrarCantidad) {
                                if (registro.cantidad && parseInt(registro.cantidad) > 0) {
                                    columnaCantidad = `<td><span class="badge bg-success">${parseInt(registro.cantidad)} unidades</span></td>`;
                                } else {
                                    columnaCantidad = '<td><span class="text-muted">-</span></td>';
                                }
                            }
                            
                            return `
                                <tr>
                                    <td><strong class="text-primary">#${registro.id}</strong></td>
                                    ${columnaCantidad}
                                    <td>${registro.peso > 0 ? parseFloat(registro.peso).toFixed(2) + ' kg' : '<span class="text-muted">-</span>'}</td>
                                    <td>${registro.factura || '<span class="text-muted">-</span>'}</td>
                                    <td><code class="codigo-unico">${registro.codigo_unico}</code></td>
                                    <td>${registro.barcode ? `<code>${registro.barcode}</code>` : '<span class="text-muted">-</span>'}</td>
                                    <td>
                                        <div class="fecha-completa">
                                            <span class="fecha-badge">
                                                <i class="fas fa-calendar me-1"></i>
                                                ${registro.fecha_solo || 'N/A'}
                                            </span>
                                            <br>
                                            <span class="hora-badge">
                                                <i class="fas fa-clock me-1"></i>
                                                ${registro.hora_solo || 'N/A'}
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    mostrarEstadoCargaDetalles('contenido');
}

        document.getElementById('formDetalle').addEventListener('submit', function(e) {
            const peso = document.getElementById('peso').value.trim();
            const cantidad = document.getElementById('cantidad') ? document.getElementById('cantidad').value.trim() : null;
            let errores = [];

            // Validar cantidad si el campo existe (unidad = "Unidad")
            if (cantidad !== null) {
                if (!cantidad || cantidad === '' || parseInt(cantidad) <= 0) {
                    errores.push('La cantidad es obligatoria y debe ser mayor a 0 para materias primas por unidad');
                }
            }

            if (esDatosDeEscaneo && (!peso || peso === '')) {
                errores.push('El peso es obligatorio cuando se usan datos de código escaneado');
            }

            if (peso && (isNaN(parseFloat(peso)) || parseFloat(peso) <= 0)) {
                errores.push('Si proporciona el peso, debe ser un número positivo');
            }

            if (errores.length > 0) {
                e.preventDefault();

                const alertHtml = `
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <ul class="mb-0">
                    ${errores.map(error => `<li>${error}</li>`).join('')}
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

                const modalBody = document.querySelector('#modalDetalle .modal-body');
                modalBody.insertAdjacentHTML('afterbegin', alertHtml);

                // Enfocar el primer campo con error
                if (cantidad !== null && (!cantidad || cantidad === '' || parseInt(cantidad) <= 0)) {
                    document.getElementById('cantidad').focus();
                } else {
                    document.getElementById('peso').focus();
                }

                setTimeout(() => {
                    const alert = modalBody.querySelector('.alert-warning');
                    if (alert) {
                        alert.remove();
                    }
                }, 6000);

                return false;
            }
        });

        // Validación en tiempo real del peso
        document.getElementById('peso').addEventListener('input', function(e) {
            const value = e.target.value.trim();

            if (esDatosDeEscaneo && (!value || value === '')) {
                e.target.setCustomValidity('El peso es obligatorio cuando se usan datos escaneados');
            } else if (value === '') {
                e.target.setCustomValidity('');
            } else {
                const numValue = parseFloat(value);
                if (isNaN(numValue) || numValue <= 0) {
                    e.target.setCustomValidity('Si proporciona el peso, debe ser un número positivo');
                } else {
                    e.target.setCustomValidity('');
                }
            }
        });
    </script>

    <style>
        /* Estilos adicionales para detalles */
        .detalle-row {
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            animation: fadeInUp 0.3s ease-out;
        }

        .detalle-row:hover {
            background: linear-gradient(90deg, #f8f9fa 0%, #e9ecef 100%);
            border-left-color: var(--america-red);
            transform: translateX(2px);
            box-shadow: var(--shadow-sm);
        }

        .proveedor-row {
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .proveedor-row:hover {
            border-left-color: var(--america-red, #dc3545);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .modal-xl {
            max-width: 90%;
        }

        .form-control:invalid:not(:placeholder-shown) {
            border-left: 3px solid var(--america-danger);
        }

        .form-control:valid:not(:placeholder-shown) {
            border-left: 3px solid var(--america-success);
        }

        .campo-obligatorio-escaneo {
            border-left: 3px solid #ffc107 !important;
            background-color: rgba(255, 193, 7, 0.1);
        }

        .campo-obligatorio-escaneo:focus {
            border-left-color: #ffc107 !important;
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
        }

        .codigo-unico {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            font-family: 'Courier New', monospace;
            box-shadow: 0 2px 4px rgba(0, 123, 255, 0.2);
            border: none;
        }

        code {
            background-color: #e9ecef;
            color: var(--america-navy-dark);
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-size: 0.75rem;
        }

        @keyframes pulseCode {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        .codigo-unico:hover {
            animation: pulseCode 0.3s ease;
        }

        .alert-info {
            border-left: 4px solid #0dcaf0;
        }

        .alert-warning {
            border-left: 4px solid #ffc107;
        }

        .alert-success {
            border-left: 4px solid #198754;
        }

        .alert-danger {
            border-left: 4px solid #dc3545;
        }

        #inputBarcode {
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            font-weight: bold;
            text-align: center;
            background-color: #f8f9fa;
            border: 2px solid #198754;
        }

        #inputBarcode:focus {
            border-color: #20c997;
            box-shadow: 0 0 0 0.2rem rgba(32, 201, 151, 0.25);
            background-color: white;
        }

        @keyframes slideInDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        #resultadoEncontrado:not(.d-none) {
            animation: slideInDown 0.3s ease;
        }

        #alertaDatosBarcode {
            border-left: 4px solid #ffc107;
            background-color: rgba(255, 193, 7, 0.1);
        }

        .fecha-badge,
        .hora-badge {
            font-size: 0.75rem;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            background-color: #e9ecef;
            color: #495057;
        }

        .btn-group-sm>.btn {
            font-size: 0.75rem;
        }

        .badge {
            font-weight: 500;
        }

        .fecha-completa {
            line-height: 1.2;
        }

        #modalDetallesProveedor .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }
    </style>
</body>

</html>