<?php
include "../../auth/verificar_sesion.php";
include "../../config/conexionBD.php";

require_once __DIR__ . '/controllers/ProduccionController.php';

requerirRol(['1', '2']);
requerirLogin();

$produccionController = new ProduccionController($conexion);

// Manejar peticiones AJAX
if (isset($_POST['ajax']) && $_POST['ajax'] === 'true') {
    $produccionController->manejarAjax();
    exit();
}

// Manejar toda la petición en el controller
$datosVista = $produccionController->manejarPeticion();
extract($datosVista);

// Función para formatear números
function formatearNumero($numero, $decimales = 3)
{
    $num = floatval($numero);
    if ($num == intval($num)) {
        return intval($num);
    }
    return round($num, $decimales);
}

// Detectar si el material actual es tipo tubo
$esMaterialTubo = false;
if ($tiene_orden && $orden_actual) {
    $esMaterialTubo = stripos($orden_actual['materia_prima_desc'], 'tubo') !== false;
}
$breadcrumb_items = ['MATERIALES', 'PRODUCCION'];
$item_urls = [
    $url_base . 'secciones/materiaprima/main.php',
];
$additional_css = [$url_base . 'secciones/materiaprima/utils/produccion.css'];
include $path_base . "components/head.php";
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <!-- Contenido Principal -->
    <div class="main-container">
        <div class="container-fluid">
            <div class="production-card">
                <!-- Header de la sección -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-2 text-primary">
                            <i class="fas fa-industry me-2"></i>Producción de Materiales
                        </h4>
                    </div>
                    <div class="quick-actions">
                        <button type="button" class="btn btn-outline-info" onclick="mostrarBuscadorOrdenes()">
                            <i class="fas fa-search me-2"></i>Buscar Órdenes
                        </button>
                        <?php if ($tiene_orden): ?>
                            <button type="button" class="btn btn-outline-secondary" onclick="limpiarOrden()">
                                <i class="fas fa-times me-2"></i>Limpiar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Mostrar mensaje de éxito o error -->
                <?php if (!empty($mensaje)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!$tiene_orden): ?>
                    <!-- Buscar Orden de Producción -->
                    <div class="text-center py-5">
                        <div class="mb-4">
                            <i class="fas fa-search fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">Buscar Orden de Producción</h5>
                            <p class="text-muted">Ingrese el número de orden para comenzar a registrar la producción</p>
                        </div>

                        <form method="POST" action="" class="d-inline-block">
                            <input type="hidden" name="accion" value="buscar_orden_web">
                            <div class="input-group mb-3" style="max-width: 400px;">
                                <span class="input-group-text bg-primary text-white">
                                    <i class="fas fa-hashtag"></i>
                                </span>
                                <input type="number" class="form-control form-control-lg text-center"
                                    name="id_orden" id="id_orden_buscar"
                                    placeholder="Número de orden..."
                                    min="1" step="1" required autofocus>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-search me-2"></i>Buscar
                                </button>
                            </div>
                        </form>

                        <div class="mt-4">
                            <button type="button" class="btn btn-outline-info" onclick="mostrarBuscadorOrdenes()">
                                <i class="fas fa-list me-2"></i>Ver Órdenes Disponibles
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Información de la Orden -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white py-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">
                                            <i class="fas fa-clipboard-list me-2"></i>
                                            Orden #<?php echo $orden_actual['id']; ?>
                                            <?php if ($esMaterialTubo): ?>
                                                <span class="badge bg-secondary ms-2">TUBO</span>
                                            <?php endif; ?>
                                        </h6>
                                        <span class="badge bg-<?php echo $orden_actual['estado'] === 'PENDIENTE' ? 'warning' : ($orden_actual['estado'] === 'EN_PROCESO' ? 'info' : 'success'); ?>">
                                            <?php echo $orden_actual['estado']; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body py-3">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-box text-primary me-2"></i>
                                                <strong class="me-2">Material:</strong>
                                                <span><?php echo htmlspecialchars($orden_actual['materia_prima_desc']); ?></span>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-weight text-info me-2"></i>
                                                <strong class="me-2">Cantidad:</strong>
                                                <?php echo formatearNumero($orden_actual['cantidad_solicitada']); ?>
                                                <?php echo " " . $orden_actual['unidad_medida']; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-calendar text-danger me-2"></i>
                                                <strong class="me-2">Fecha:</strong>
                                                <small><?php echo $orden_actual['fecha_orden_formateada']; ?></small>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-user text-secondary me-2"></i>
                                                <strong class="me-2">Creado por:</strong>
                                                <small><?php echo htmlspecialchars($orden_actual['usuario_creacion']); ?></small>
                                            </div>
                                            <?php if (!empty($orden_actual['observaciones'])): ?>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-sticky-note text-warning me-2"></i>
                                                    <strong class="me-2">Obs:</strong>
                                                    <small class="text-muted"><?php echo htmlspecialchars($orden_actual['observaciones']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Layout horizontal: Listado a la izquierda, Formulario a la derecha -->
                    <div class="row">
                        <div class="col-lg-6">
                            <?php if ($puede_producir['puede']): ?>
                                <div class="card h-100">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-plus-circle me-2"></i>Registrar Nueva Producción
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($esMaterialTubo): ?>
                                            <div class="alert alert-info mb-3">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Material TUBO:</strong> Tara automática en 0 KG
                                            </div>
                                        <?php endif; ?>
                                        <form method="POST" action="" id="formProduccion">
                                            <input type="hidden" name="accion" value="crear_produccion">
                                            <input type="hidden" name="id_op" value="<?php echo $orden_actual['id']; ?>">
                                            <input type="hidden" name="nombre" value="<?php echo htmlspecialchars($orden_actual['materia_prima_desc']); ?>">
                                            <input type="hidden" name="unidad_medida" value="<?php echo $orden_actual['unidad_medida']; ?>">
                                            <input type="hidden" name="es_tubo" value="<?php echo $esMaterialTubo ? '1' : '0'; ?>">

                                            <div class="row">

                                                <?php if (isset($orden_actual['unidad_medida']) && $orden_actual['unidad_medida'] === 'UN'): ?>
                                                    <div class="col-md-3 mb-4">
                                                        <div class="form-group-custom">
                                                            <label class="form-label-custom mb-2">
                                                                <i class="fas fa-cubes text-info"></i>Cantidad<span class="text-danger">*</span>
                                                            </label>
                                                            <input type="number" class="form-control form-control-custom form-control-lg text-center"
                                                                name="cantidad" id="cantidad"
                                                                step="1" min="1" placeholder="0" required>
                                                            <small class="form-text text-muted">
                                                                <i class="fas fa-info-circle me-1"></i>
                                                                Número de unidades individuales producidas
                                                            </small>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="col-md-3 mb-4">
                                                    <div class="form-group-custom">
                                                        <label class="form-label-custom mb-2">
                                                            <i class="fas fa-weight text-primary"></i>Peso Bruto (KG) <span class="text-danger">*</span>
                                                        </label>
                                                        <input type="number" class="form-control form-control-custom form-control-lg text-center"
                                                            name="peso_bruto" id="peso_bruto"
                                                            step="0.001" min="0.001" placeholder="0" required
                                                            oninput="calcularPesoLiquido()" autofocus>
                                                    </div>
                                                </div>

                                                <div class="col-md-3 mb-4" id="container-tara">
                                                    <div class="form-group-custom">
                                                        <label class="form-label-custom mb-2">
                                                            <i class="fas fa-minus text-warning"></i>Tara (KG) <span class="text-danger">*</span>
                                                        </label>
                                                        <input type="number" class="form-control form-control-custom form-control-lg text-center"
                                                            name="tara" id="tara"
                                                            step="0.001" min="0" placeholder="0"
                                                            value="<?php echo $esMaterialTubo ? '0' : ''; ?>"
                                                            <?php echo $esMaterialTubo ? 'readonly' : 'required'; ?>
                                                            oninput="calcularPesoLiquido()">
                                                    </div>
                                                </div>

                                                <div class="col-md-3 mb-4">
                                                    <div class="form-group-custom">
                                                        <label class="form-label-custom mb-2">
                                                            <i class="fas fa-equals text-success"></i>Peso Líquido (KG)
                                                        </label>
                                                        <input type="text" class="form-control form-control-custom form-control-lg text-center bg-light"
                                                            id="peso_liquido_display" readonly placeholder="0">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="d-grid gap-2">
                                                <button type="submit" class="btn btn-success btn-lg" id="btnRegistrar">
                                                    <i class="fas fa-save me-2"></i>Registrar Producción
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" onclick="limpiarFormulario()">
                                                    <i class="fas fa-eraser me-2"></i>Limpiar Formulario
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="card h-100">
                                    <div class="card-header bg-warning text-dark">
                                        <h6 class="mb-0">
                                            <i class="fas fa-exclamation-triangle me-2"></i>Estado de Producción
                                        </h6>
                                    </div>
                                    <div class="card-body text-center">
                                        <i class="fas fa-ban fa-3x text-warning mb-3"></i>
                                        <h6 class="text-warning">No se puede producir</h6>
                                        <p class="text-muted"><?php echo $puede_producir['razon']; ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Tabla de producciones -->
                        <div class="col-lg-6">
                            <?php if (!empty($producciones)): ?>
                                <div class="card h-100">
                                    <div class="card-header bg-info text-white">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0">
                                                <i class="fas fa-list me-2"></i>Producciones Registradas
                                                <span class="badge bg-light text-dark ms-2"><?php echo count($producciones); ?> registros</span>
                                            </h6>
                                            <button type="button" class="btn btn-outline-light btn-sm" onclick="actualizarProducciones()">
                                                <i class="fas fa-sync-alt me-1"></i>Actualizar
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th width="8%">#</th>
                                                        <th width="16%">Peso Bruto</th>
                                                        <th width="16%">Tara</th>
                                                        <th width="16%">Peso Líquido</th>
                                                        <!-- Columna cantidad condicional -->
                                                        <?php if (isset($orden_actual['unidad_medida']) && $orden_actual['unidad_medida'] === 'UN'): ?>
                                                            <th width="12%">Cantidad</th>
                                                            <th width="20%">Fecha Registro</th>
                                                            <th width="12%">Acciones</th>
                                                        <?php else: ?>
                                                            <th width="24%">Fecha Registro</th>
                                                            <th width="20%">Acciones</th>
                                                        <?php endif; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($producciones as $produccion): ?>
                                                        <tr>
                                                            <td><strong class="text-primary">#<?php echo $produccion['id']; ?></strong></td>
                                                            <td>
                                                                <span class="badge bg-primary">
                                                                    <?php echo formatearNumero($produccion['peso_bruto']); ?> KG
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-warning">
                                                                    <?php echo formatearNumero($produccion['tara']); ?> KG
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-success">
                                                                    <?php echo formatearNumero($produccion['peso_liquido']); ?> KG
                                                                </span>
                                                            </td>
                                                            <!-- Mostrar cantidad si es unidades -->
                                                            <?php if (isset($orden_actual['unidad_medida']) && $orden_actual['unidad_medida'] === 'UN'): ?>
                                                                <td>
                                                                    <?php if (isset($produccion['cantidad']) && $produccion['cantidad']): ?>
                                                                        <span class="badge bg-info">
                                                                            <?php echo formatearNumero($produccion['cantidad'], 0); ?> UN
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">N/A</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            <?php endif; ?>
                                                            <td>
                                                                <small class="text-muted">
                                                                    <?php echo $produccion['fecha_registro_formateada']; ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <button type="button" class="btn btn-outline-danger btn-sm"
                                                                    onclick="eliminarProduccion(<?php echo $produccion['id']; ?>, '<?php echo $produccion['id']; ?>')"
                                                                    title="Eliminar">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="card h-100">
                                    <div class="card-header bg-secondary text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-list me-2"></i>Producciones Registradas
                                        </h6>
                                    </div>
                                    <div class="card-body text-center">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <h6 class="text-muted">Sin producciones registradas</h6>
                                        <p class="text-muted">
                                            Los registros aparecerán aquí una vez que comience a producir
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Buscador de Órdenes -->
    <div class="modal fade" id="modalBuscadorOrdenes" tabindex="-1" aria-labelledby="modalBuscadorOrdenesLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="modalBuscadorOrdenesLabel">
                        <i class="fas fa-search me-2"></i>Buscar Órdenes de Producción
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="terminoBusqueda"
                            placeholder="Buscar por número de orden o nombre de material..."
                            oninput="buscarOrdenesDisponibles()">
                    </div>

                    <div id="loadingOrdenes" class="text-center py-4" style="display: none;">
                        <div class="spinner-border text-info" role="status">
                            <span class="visually-hidden">Buscando...</span>
                        </div>
                        <p class="mt-3">Buscando órdenes...</p>
                    </div>

                    <div id="resultadosOrdenes">
                        <!-- Se carga dinámicamente -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formularios ocultos -->
    <form method="POST" action="" id="formEliminar" style="display: none;">
        <input type="hidden" name="accion" value="eliminar_produccion">
        <input type="hidden" name="id_eliminar" id="idEliminar" value="">
        <input type="hidden" name="id_orden_actual" value="<?php echo $orden_actual['id'] ?? 0; ?>">
    </form>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript externo -->
    <script src="<?php echo $url_base; ?>secciones/materiaprima/js/produccion.js"></script>

    <!-- Script específico para tubos - SIMPLIFICADO -->
    <script>
        // Variables globales para tubos
        window.esMaterialTubo = <?php echo $esMaterialTubo ? 'true' : 'false'; ?>;

        document.addEventListener("DOMContentLoaded", function() {
            if (window.esMaterialTubo) {
                // Configurar campos específicos para tubos
                const campoTara = document.getElementById("tara");
                if (campoTara) {
                    campoTara.value = "0";
                    campoTara.readOnly = true;
                    campoTara.style.backgroundColor = "#e9ecef";
                }
            }
        });
    </script>
</body>