<?php
include "../../auth/verificar_sesion.php";
include "../../config/conexionBD.php";

require_once __DIR__ . '/controllers/RecetasController.php';

requerirRol(['1', '2']);
requerirLogin();

$recetasController = new RecetasController($conexion);

// Manejar peticiones AJAX
if (isset($_POST['ajax']) && $_POST['ajax'] === 'true') {
    $recetasController->manejarAjaxAgrupado();
    exit();
}

// Manejar solicitud de detalle de receta
if (isset($_GET['accion']) && $_GET['accion'] === 'obtener_detalle_receta' && isset($_GET['id_tipo_producto'])) {
    header('Content-Type: application/json');
    $detalle = $recetasController->obtenerDetalleReceta($_GET['id_tipo_producto']);
    echo json_encode($detalle, JSON_UNESCAPED_UNICODE);
    exit();
}

// ✅ NUEVO: Manejar toda la petición con PRG
$datosVista = $recetasController->manejarPeticionAgrupada();

// Si el controlador devuelve null, significa que se hizo un redirect
if ($datosVista === null) {
    exit(); // No continuar con el renderizado
}

extract($datosVista);

// Función para formatear números
function formatearNumero($numero, $decimales = 1)
{
    $num = floatval($numero);
    if ($num == intval($num)) {
        return intval($num);
    }
    return round($num, $decimales);
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>America TNT - Recetas de Productos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="<?php echo $url_base; ?>utils/icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/materiaprima/utils/recetas.css">
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
                            <i class="fas fa-boxes me-1"></i>Materiales
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="fas fa-layer-group me-1"></i>Recetas Productos
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
                        <h4 class="mb-2 text-primary">
                            <i class="fas fa-layer-group me-2"></i>Gestión de Recetas con Múltiples Versiones
                        </h4>
                        <p class="text-muted mb-0 fs-6">Administre versiones independientes con materias principales y extras</p>
                    </div>
                    <div class="quick-actions">
                        <button type="button" class="btn btn-outline-info" onclick="toggleBusqueda()">
                            <i class="fas fa-search me-2"></i>Buscar
                        </button>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalReceta" onclick="prepararModalNuevo()">
                            <i class="fas fa-plus me-2"></i>Nueva Versión
                        </button>
                    </div>
                </div>

                <!-- ✅ MEJORADO: Mostrar mensajes con mejor styling y PRG -->
                <?php if (!empty($mensaje)): ?>
                    <div class="alert alert-success alert-custom alert-success-custom alert-dismissible fade show alert-fade-in" role="alert">
                        <i class="fas fa-check-circle alert-icon"></i>
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-custom alert-danger-custom alert-dismissible fade show alert-fade-in" role="alert">
                        <i class="fas fa-exclamation-triangle alert-icon"></i>
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endif; ?>

                <!-- ✅ NUEVO: Indicador de acción reciente (si hay) -->
                <?php if (isset($_GET['action']) && !empty($_GET['action'])): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Acción completada:</strong>
                        <?php
                        $accion_texto = '';
                        switch ($_GET['action']) {
                            case 'crear_multiples':
                                $accion_texto = 'Nueva versión de receta creada';
                                break;
                            case 'eliminar':
                                $accion_texto = 'Receta eliminada';
                                break;
                            case 'eliminar_version':
                                $accion_texto = 'Versión de receta eliminada';
                                break;
                            case 'eliminar_todas_recetas':
                                $accion_texto = 'Todas las recetas del producto eliminadas';
                                break;
                            case 'editar':
                                $accion_texto = 'Receta actualizada';
                                break;
                        }
                        echo $accion_texto;
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endif; ?>

                <!-- Panel de búsqueda -->
                <div class="form-section" id="panelBusqueda" style="display: none;">
                    <div class="card border-info mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-search me-2"></i>Buscar Recetas y Versiones
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group-custom">
                                            <label class="form-label-custom">Tipo de Producto</label>
                                            <select class="form-control form-control-custom" name="id_tipo_producto">
                                                <option value="">Todos los tipos</option>
                                                <?php foreach ($tiposProducto as $tipo): ?>
                                                    <option value="<?php echo $tipo['id']; ?>"
                                                        <?php echo ($filtros['id_tipo_producto'] == $tipo['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($tipo['desc']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group-custom">
                                            <label class="form-label-custom">Buscar en Tipos</label>
                                            <input type="text" class="form-control form-control-custom"
                                                name="buscar_tipo_producto"
                                                value="<?php echo htmlspecialchars($filtros['buscar_tipo_producto'] ?? ''); ?>"
                                                placeholder="Buscar por tipo de producto...">
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-info">
                                        <i class="fas fa-search me-2"></i>Buscar
                                    </button>
                                    <a href="recetas.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-2"></i>Limpiar
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Listado de tipos de productos con versiones -->
                <div class="ultimas-cajas">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-secondary mb-0">
                            <i class="fas fa-list me-2"></i>Tipos de Productos con Versiones
                            <?php if ($datosPaginacion['total_registros'] > 0): ?>
                                <span class="badge bg-primary ms-2"><?php echo $datosPaginacion['total_registros']; ?> tipos</span>
                            <?php endif; ?>
                        </h6>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="refrescarDatos()">
                                <i class="fas fa-sync-alt me-1"></i>Actualizar
                            </button>
                        </div>
                    </div>

                    <?php if (!empty($datosPaginacion['registros'])): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="8%">ID</th>
                                        <th width="25%">Tipo de Producto</th>
                                        <th width="20%">Versiones de Recetas</th>
                                        <th width="20%">Materias Principales/Extras</th>
                                        <th width="15%">Estado</th>
                                        <th width="12%">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($datosPaginacion['registros'] as $registro): ?>
                                        <tr class="tipo-producto-row">
                                            <td><strong class="text-primary">#<?php echo $registro['id_tipo_producto']; ?></strong></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($registro['tipo_producto_desc']); ?></strong>
                                                <?php if (!empty($registro['versiones_nombres'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($registro['versiones_nombres']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($registro['total_versiones'] > 0): ?>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="badge bg-info">
                                                            <i class="fas fa-layer-group me-1"></i><?php echo $registro['total_versiones']; ?> versión(es)
                                                        </span>
                                                        <button type="button" class="btn btn-outline-info btn-sm"
                                                            onclick="verTodasLasVersiones(<?php echo $registro['id_tipo_producto']; ?>, '<?php echo htmlspecialchars($registro['tipo_producto_desc'], ENT_QUOTES); ?>')">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Sin versiones</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column gap-1">
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-cubes me-1"></i><?php echo $registro['total_materias_principales'] ?? $registro['total_materias']; ?> principales
                                                    </span>
                                                    <?php if (isset($registro['total_materias_extras']) && $registro['total_materias_extras'] > 0): ?>
                                                        <span class="badge bg-warning">
                                                            <i class="fas fa-plus-circle me-1"></i><?php echo $registro['total_materias_extras']; ?> extras
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($registro['total_versiones'] > 0): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check-circle me-1"></i>Configurado
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-minus-circle me-1"></i>Sin Configurar
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button type="button" class="btn btn-info btn-sm dropdown-toggle"
                                                        data-bs-toggle="dropdown" title="Acciones">
                                                        <i class="fas fa-cog"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li><a class="dropdown-item" href="#"
                                                                onclick="verTodasLasVersiones(<?php echo $registro['id_tipo_producto']; ?>, '<?php echo htmlspecialchars($registro['tipo_producto_desc'], ENT_QUOTES); ?>')">
                                                                <i class="fas fa-eye me-2"></i>Ver Versiones
                                                            </a></li>
                                                        <li><a class="dropdown-item" href="#"
                                                                onclick="agregarNuevaVersion(<?php echo $registro['id_tipo_producto']; ?>)">
                                                                <i class="fas fa-plus me-2"></i>Nueva Versión
                                                            </a></li>
                                                        <!-- NUEVO BOTÓN PARA PDF -->
                                                        <?php if ($registro['total_versiones'] > 0): ?>
                                                            <li>
                                                                <hr class="dropdown-divider">
                                                            </li>
                                                            <li><a class="dropdown-item text-primary" href="#"
                                                                    onclick="generarPDFRecetas(<?php echo $registro['id_tipo_producto']; ?>)">
                                                                    <i class="fas fa-file-pdf me-2"></i>Ver Resumen PDF
                                                                </a></li>
                                                        <?php endif; ?>

                                                        <?php if ($registro['total_versiones'] > 0): ?>
                                                            <li>
                                                                <hr class="dropdown-divider">
                                                            </li>
                                                            <li><a class="dropdown-item text-danger" href="#"
                                                                    onclick="eliminarTodasLasVersiones(<?php echo $registro['id_tipo_producto']; ?>, '<?php echo htmlspecialchars($registro['tipo_producto_desc'], ENT_QUOTES); ?>')">
                                                                    <i class="fas fa-trash me-2"></i>Eliminar Todo
                                                                </a></li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <?php if ($datosPaginacion['total_paginas'] > 1): ?>
                            <nav aria-label="Paginación" class="pagination-custom">
                                <ul class="pagination pagination-sm justify-content-center">
                                    <?php if ($pagina_actual > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?php echo $pagina_actual - 1;
                                                                                echo $filtrosUrl; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php
                                    $inicio = max(1, $pagina_actual - 2);
                                    $fin = min($datosPaginacion['total_paginas'], $pagina_actual + 2);

                                    for ($i = $inicio; $i <= $fin; $i++):
                                    ?>
                                        <li class="page-item <?php echo $i === $pagina_actual ? 'active' : ''; ?>">
                                            <a class="page-link" href="?pagina=<?php echo $i;
                                                                                echo $filtrosUrl; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($pagina_actual < $datosPaginacion['total_paginas']): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?php echo $pagina_actual + 1;
                                                                                echo $filtrosUrl; ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-layer-group fa-4x mb-3 text-muted"></i>
                            <h5>No hay recetas configuradas</h5>
                            <p class="mb-4">¡Comience agregando la primera versión de receta para sus productos!</p>
                            <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#modalReceta" onclick="prepararModalNuevo()">
                                <i class="fas fa-plus me-2"></i>Agregar Primera Versión
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ver Todas las Versiones -->
    <div class="modal fade" id="modalVersiones" tabindex="-1" aria-labelledby="modalVersionesLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="modalVersionesLabel">
                        <i class="fas fa-layer-group me-2"></i>
                        <span id="tituloVersiones">Versiones de Recetas</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="loadingVersiones" class="text-center py-4">
                        <div class="spinner-border text-info" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-3">Cargando versiones...</p>
                    </div>
                    <div id="contenidoVersiones" style="display: none;">
                        <!-- El contenido se carga dinámicamente -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cerrar
                    </button>
                    <button type="button" class="btn btn-success" id="btnAgregarVersion">
                        <i class="fas fa-plus me-2"></i>Agregar Nueva Versión
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Receta (Nueva Versión) -->
    <div class="modal fade" id="modalReceta" tabindex="-1" aria-labelledby="modalRecetaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalRecetaLabel">
                        <i class="fas fa-plus-circle me-2"></i>
                        <span id="tituloModal">Configurar Nueva Versión de Receta</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="formReceta">
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accionFormulario" value="crear_multiples">

                        <!-- Información de versión -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="form-group-custom">
                                    <label class="form-label-custom">
                                        <i class="fas fa-tag text-primary"></i>Tipo de Producto <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control form-control-custom" name="id_tipo_producto" id="id_tipo_producto" required onchange="cargarVersionesExistentes()">
                                        <option value="">Seleccionar tipo de producto</option>
                                        <?php foreach ($tiposProducto as $tipo): ?>
                                            <option value="<?php echo $tipo['id']; ?>">
                                                <?php echo htmlspecialchars($tipo['desc']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group-custom">
                                    <label class="form-label-custom">
                                        <i class="fas fa-edit text-success"></i>Nombre de la Receta
                                    </label>
                                    <input type="text" class="form-control form-control-custom"
                                        name="nombre_receta" id="nombreReceta"
                                        placeholder="Nombre opcional..."
                                        maxlength="100">
                                    <small class="form-text text-muted">Opcional - Se genera automáticamente si no se especifica</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group-custom">
                                    <label class="form-label-custom">
                                        <i class="fas fa-info-circle text-info"></i>Versiones Existentes
                                    </label>
                                    <div id="versionesExistentes" class="small text-muted p-3 border rounded bg-light">
                                        Seleccione un tipo de producto para ver versiones
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Indicador de suma total -->
                        <div id="indicadorSuma" class="alert alert-info d-none mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-calculator me-2"></i>
                                    <strong>Total materias principales: <span id="sumaTotal">0</span>%</strong>
                                </div>
                                <div>
                                    <span id="estadoSuma" class="badge bg-secondary">Incompleto</span>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="progress" style="height: 12px;">
                                    <div id="barraProgreso" class="progress-bar" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabs para separar materias principales y extras -->
                        <div id="seccionMateriasPrimas" style="display: none;">
                            <ul class="nav nav-tabs" id="tabsMaterias" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="tab-principales" data-bs-toggle="tab" data-bs-target="#tabPrincipales" type="button" role="tab">
                                        <i class="fas fa-percentage me-1"></i>Materias Principales (100%)
                                        <span id="contadorPrincipales" class="badge bg-primary ms-2">0</span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="tab-extras" data-bs-toggle="tab" data-bs-target="#tabExtras" type="button" role="tab">
                                        <i class="fas fa-plus-circle me-1"></i>Materias Extras
                                        <span id="contadorExtras" class="badge bg-warning ms-2">0</span>
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content mt-4" id="contenidoTabsMaterias">
                                <!-- Tab Materias Principales -->
                                <div class="tab-pane fade show active" id="tabPrincipales" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="text-secondary mb-0">
                                            <i class="fas fa-percentage me-2"></i>Composición Principal del Producto (debe sumar 100%)
                                        </h6>
                                        <button type="button" class="btn btn-success btn-sm" onclick="agregarFilaMateria('principal')">
                                            <i class="fas fa-plus me-1"></i>Agregar Materia Principal
                                        </button>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-sm" id="tablaMateriasprincipales">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="50%">Materia Prima</th>
                                                    <th width="25%">Porcentaje (%)</th>
                                                    <th width="25%">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tbodyMateriasPrincipales">
                                                <!-- Las filas se agregan dinámicamente -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Tab Materias Extras -->
                                <div class="tab-pane fade" id="tabExtras" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="text-secondary mb-0">
                                            <i class="fas fa-plus-circle me-2"></i>Materias Extras (no cuentan para el 100%)
                                        </h6>
                                        <button type="button" class="btn btn-warning btn-sm" onclick="agregarFilaMateria('extra')">
                                            <i class="fas fa-plus me-1"></i>Agregar Materia Extra
                                        </button>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-sm" id="tablaMateriasExtras">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="40%">Materia Prima</th>
                                                    <th width="20%">Cantidad</th>
                                                    <th width="20%">Unidad</th>
                                                    <th width="20%">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tbodyMateriasExtras">
                                                <!-- Las filas se agregan dinámicamente -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="limpiarFormularioMultiple()">
                            <i class="fas fa-broom me-2"></i>Limpiar Todo
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnGuardarMultiple">
                            <i class="fas fa-save me-2"></i>Guardar Nueva Versión
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formularios ocultos -->
    <form method="POST" action="" id="formEliminar" style="display: none;">
        <input type="hidden" name="accion" value="eliminar_todas_recetas">
        <input type="hidden" name="id_tipo_producto" id="idTipoProductoEliminar" value="">
    </form>

    <form method="POST" action="" id="formEliminarIndividual" style="display: none;">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id_eliminar" id="idEliminarIndividual" value="">
    </form>

    <form method="POST" action="" id="formEliminarVersion" style="display: none;">
        <input type="hidden" name="accion" value="eliminar_version">
        <input type="hidden" name="id_tipo_producto" id="idTipoProductoEliminarVersion" value="">
        <input type="hidden" name="version_receta" id="versionEliminar" value="">
    </form>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Script de datos PHP para JavaScript -->
    <script>
        // Transferir datos PHP a JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // ✅ NUEVO: Auto-cerrar alertas después de un tiempo
            setTimeout(function() {
                const alertas = document.querySelectorAll('.alert-dismissible');
                alertas.forEach(function(alerta) {
                    const bsAlert = new bootstrap.Alert(alerta);
                    setTimeout(function() {
                        try {
                            bsAlert.close();
                        } catch (e) {
                            // Ignorar errores si la alerta ya fue cerrada
                        }
                    }, 8000); // 8 segundos para auto-cerrar
                });
            }, 1000);

            // Inicializar sistema con datos del servidor
            inicializarSistema(
                <?php echo json_encode($materiasPrimas); ?>,
                <?php echo json_encode($tiposProducto); ?>
            );

            // ✅ NUEVO: Limpiar parámetros de URL después de mostrar el mensaje
            if (window.location.search.includes('action=') || window.location.search.includes('_t=')) {
                // Crear nueva URL sin los parámetros temporales
                const url = new URL(window.location);
                url.searchParams.delete('action');
                url.searchParams.delete('_t');

                // Reemplazar la URL actual sin recargar la página
                window.history.replaceState({}, '', url.toString());
            }
        });

        // ✅ NUEVO: Función para refrescar datos sin reload completo
        function refrescarDatos() {
            // Mostrar indicador de carga
            const btnRefresh = document.querySelector('[onclick="refrescarDatos()"]');
            const iconoOriginal = btnRefresh.innerHTML;
            btnRefresh.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Actualizando...';
            btnRefresh.disabled = true;

            // Recargar página después de un breve delay
            setTimeout(function() {
                window.location.reload();
            }, 500);
        }

        // ✅ NUEVO: Función mejorada para toggle de búsqueda
        function toggleBusqueda() {
            const panel = document.getElementById('panelBusqueda');
            const btn = document.querySelector('[onclick="toggleBusqueda()"]');

            if (panel.style.display === 'none') {
                panel.style.display = 'block';
                btn.classList.remove('btn-outline-info');
                btn.classList.add('btn-info');
                btn.innerHTML = '<i class="fas fa-times me-2"></i>Ocultar';
            } else {
                panel.style.display = 'none';
                btn.classList.remove('btn-info');
                btn.classList.add('btn-outline-info');
                btn.innerHTML = '<i class="fas fa-search me-2"></i>Buscar';
            }
        }
    </script>

    <!-- JavaScript externo actualizado -->
    <script src="<?php echo $url_base; ?>secciones/materiaprima/js/recetas-productos.js"></script>
</body>

</html>