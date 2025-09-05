<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";

date_default_timezone_set('America/Asuncion');

if (file_exists("controller/rejillasController.php")) {
    require_once "controller/rejillasController.php";
} else {
    die("Error: No se pudo cargar el controlador de rejillas.");
}

try {
    $controller = new RejillasController($conexion);
    $datosVista = $controller->obtenerDatosVistaRejillas();
    $rejillas = $datosVista['rejillas'];
    $estadisticasGenerales = $datosVista['estadisticas_generales'];
    $alertas = $datosVista['alertas'];
    $configuracion = $datosVista['configuracion'];
} catch (Exception $e) {
    error_log("Error fatal obteniendo datos de vista rejillas: " . $e->getMessage());
    $rejillas = [];
    $estadisticasGenerales = [];
    $alertas = [];
    $configuracion = [];
}
$breadcrumb_items = ['EXPEDICION', 'REJILLAS'];
$item_urls = [
    $url_base . 'secciones/expedicion/expedicion.php',
];
$additional_css = [$url_base . 'secciones/expedicion/utils/rejillas.css'];
include $path_base . "components/head.php";
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="main-container">
        <div class="container-fluid">
            <div class="header-section">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h1 class="header-title">
                            <i class="fas fa-th-large"></i>
                            Gestión de Rejillas
                        </h1>
                        <p class="header-description">
                            Monitor en tiempo real del estado y ocupación de las rejillas del almacén con tracking de producción real
                        </p>
                    </div>
                    <div class="col-lg-4">
                        <div class="d-flex justify-content-end align-items-center gap-2">
                            <button onclick="refrescarDatos()" class="btn btn-primary">
                                <i class="fas fa-sync-alt me-2"></i>Refrescar
                            </button>
                            <a href="expedicion.php" class="btn btn-success">
                                <i class="fas fa-shipping-fast me-2"></i>Expedición
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rejillas-grid">
                <?php if (!empty($rejillas)): ?>
                    <?php foreach ($rejillas as $rejilla): ?>
                        <?php
                        $porcentaje = ($rejilla['peso_actual'] / $rejilla['capacidad_maxima']) * 100;
                        $estadoRejilla = $rejilla['estado'];
                        if ($porcentaje > 100) {
                            $estadoRejilla = 'sobrecargada';
                        }
                        if (!empty($rejilla['tiene_expedicion_abierta']) && $rejilla['tiene_expedicion_abierta']) {
                            $estadoRejilla = 'con_expedicion_abierta';
                        }
                        if ($porcentaje > 100) {
                            $estadoRejilla = 'sobrecargada';
                        }
                        $claseProgreso = 'bg-success';
                        if ($porcentaje > 100) {
                            $claseProgreso = 'bg-sobrecargada';
                        } elseif ($porcentaje >= 90) {
                            $claseProgreso = 'bg-danger';
                        } elseif ($porcentaje >= 70) {
                            $claseProgreso = 'bg-warning';
                        }
                        ?>

                        <div class="rejilla-card estado-<?php echo $estadoRejilla; ?>"
                            onclick="abrirModalRejilla(<?php echo $rejilla['id']; ?>, '<?php echo htmlspecialchars($rejilla['numero_rejilla'], ENT_QUOTES); ?>')">
                            <div class="rejilla-header">
                                <div class="rejilla-numero">
                                    <i class="fas fa-cube"></i>
                                    <?php echo htmlspecialchars($rejilla['numero_rejilla']); ?>
                                </div>
                                <span class="rejilla-estado estado-<?php echo $estadoRejilla; ?>">
                                    <?php
                                    if ($estadoRejilla === 'sobrecargada') {
                                        echo 'SOBRECARGADA';
                                    } else {
                                        echo ucfirst($rejilla['estado']);
                                    }
                                    ?>
                                </span>
                            </div>

                            <div class="rejilla-capacidad">
                                <div class="capacidad-info">
                                    <span class="capacidad-actual">
                                        <?php echo number_format($rejilla['peso_actual'], 0); ?> kg
                                    </span>
                                    <span class="capacidad-total">
                                        / <?php echo number_format($rejilla['capacidad_maxima'], 0); ?> kg
                                    </span>
                                </div>

                                <div class="progress-rejilla">
                                    <div class="progress-bar-rejilla <?php echo $claseProgreso; ?>"
                                        style="width: <?php echo min(100, $porcentaje); ?>%">
                                    </div>
                                </div>

                                <div class="text-center mt-1">
                                    <small class="<?php echo $porcentaje > 100 ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                        <?php echo number_format($porcentaje, 1); ?>% ocupado
                                        <?php if ($porcentaje > 100): ?>
                                            <i class="fas fa-exclamation-triangle ms-1"></i>
                                        <?php endif; ?>
                                    </small>
                                </div>

                                <?php
                                $pesoTotalProducido = floatval($rejilla['peso_total_producido'] ?? 0);
                                $porcentajeProducido = $rejilla['peso_actual'] > 0 ? ($pesoTotalProducido / $rejilla['peso_actual']) * 100 : 0;
                                ?>
                                <div class="progress-produccion mt-2">
                                    <div class="progress-bar-produccion"
                                        style="width: <?php echo min(100, $porcentajeProducido); ?>%">
                                    </div>
                                </div>

                                <div class="text-center mt-1">
                                    <small class="text-success">
                                        <i class="fas fa-industry me-1"></i>
                                        <?php echo number_format($porcentajeProducido, 1); ?>% producido
                                        (<?php echo number_format($pesoTotalProducido, 0); ?> kg)
                                    </small>
                                </div>
                            </div>

                            <div class="rejilla-asignaciones">
                                <div class="asignaciones-info">
                                    <div class="asignacion-item">
                                        <div class="asignacion-value">
                                            <?php echo $rejilla['total_items_asignados'] ?? 0; ?>
                                        </div>
                                        <div class="asignacion-label">Items</div>
                                    </div>
                                    <div class="asignacion-item">
                                        <div class="asignacion-value">
                                            <?php echo $rejilla['clientes_unicos'] ?? 0; ?>
                                        </div>
                                        <div class="asignacion-label">Clientes</div>
                                    </div>
                                </div>

                                <?php if (!empty($rejilla['ultima_asignacion'])): ?>
                                    <div class="text-center mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            Última asignación: <?php echo date('d/m/Y H:i', strtotime($rejilla['ultima_asignacion'])); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                <div class="rejilla-descripcion">
                                    <div class="descripcion-container">
                                        <div class="descripcion-text" id="desc-<?php echo $rejilla['id']; ?>">
                                            <?php
                                            $descripcion = trim($rejilla['descripcion'] ?? '');
                                            if (!empty($descripcion)):
                                            ?>
                                                <i class="fas fa-info-circle me-1 text-info"></i>
                                                <span><?php echo htmlspecialchars($descripcion); ?></span>
                                            <?php else: ?>
                                                <i class="fas fa-plus-circle me-1 text-muted"></i>
                                                <span class="text-muted">Agregar descripción...</span>
                                            <?php endif; ?>
                                        </div>
                                        <button class="btn-editar-desc"
                                            onclick="event.stopPropagation(); editarDescripcion(<?php echo $rejilla['id']; ?>, '<?php echo htmlspecialchars($descripcion, ENT_QUOTES); ?>')"
                                            title="Editar descripción">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="text-center mt-2">
                                    <button class="btn btn-primary btn-sm"
                                        onclick="event.stopPropagation(); abrirPDFRejilla(<?php echo $rejilla['id']; ?>);">
                                        <i class="fas fa-file-pdf me-1"></i>RESUMEN
                                    </button>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="no-items">
                            <i class="fas fa-cube fa-4x"></i>
                            <h5>No hay rejillas configuradas</h5>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalDetallesRejilla" tabindex="-1" aria-labelledby="modalDetallesRejillaLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetallesRejillaLabel">
                        <i class="fas fa-cube"></i>
                        <span id="tituloRejillaModal">Rejilla</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="loading-spinner" id="loadingDetallesRejilla">
                        <i class="fas fa-spinner fa-spin fa-3x"></i>
                        <p class="mt-3 mb-0">Cargando detalles de la rejilla...</p>
                    </div>
                    <div id="contenidoDetallesRejilla">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalEditarDescripcion" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i>
                        Editar Descripción de Rejilla
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditarDescripcion">
                        <input type="hidden" id="idRejillaDesc" name="id_rejilla">
                        <div class="mb-3">
                            <label for="descripcionRejilla" class="form-label">Descripción:</label>
                            <textarea class="form-control" id="descripcionRejilla" name="descripcion"
                                rows="3" maxlength="500"
                                placeholder="Ingrese una descripción para esta rejilla..."></textarea>
                            <div class="form-text">
                                <span id="contadorCaracteres">0</span>/500 caracteres
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="guardarDescripcion()">
                        <i class="fas fa-save me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.REJILLAS_CONFIG = {
            urlBase: "<?php echo $url_base; ?>",
            usuario: "<?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES); ?>",
            debug: false,
            version: "4.0"
        };
    </script>


    <script src="<?php echo $url_base; ?>secciones/expedicion/js/rejillas/rejillas-config.js"></script>
    <script src="<?php echo $url_base; ?>secciones/expedicion/js/rejillas/rejillas-filters.js"></script>
    <script src="<?php echo $url_base; ?>secciones/expedicion/js/rejillas/rejillas-modal.js"></script>
    <script src="<?php echo $url_base; ?>secciones/expedicion/js/rejillas/rejillas-utils.js"></script>

    <script>
        function abrirPDFRejilla(idRejilla) {
            window.open('pdf/resumenpdf.php?id_rejilla=' + idRejilla, '_blank');
        }
    </script>
    <script>
        function editarDescripcion(idRejilla, descripcionActual) {
            document.getElementById('idRejillaDesc').value = idRejilla;
            document.getElementById('descripcionRejilla').value = descripcionActual || '';
            actualizarContadorCaracteres();

            const modal = new bootstrap.Modal(document.getElementById('modalEditarDescripcion'));
            modal.show();
            setTimeout(() => {
                document.getElementById('descripcionRejilla').focus();
            }, 500);
        }

        function actualizarContadorCaracteres() {
            const textarea = document.getElementById('descripcionRejilla');
            const contador = document.getElementById('contadorCaracteres');
            contador.textContent = textarea.value.length;

            if (textarea.value.length > 450) {
                contador.style.color = '#dc3545';
            } else if (textarea.value.length > 350) {
                contador.style.color = '#ffc107';
            } else {
                contador.style.color = '#6c757d';
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('descripcionRejilla');
            if (textarea) {
                textarea.addEventListener('input', actualizarContadorCaracteres);
            }
        });

        function guardarDescripcion() {
            const idRejilla = document.getElementById('idRejillaDesc').value;
            const descripcion = document.getElementById('descripcionRejilla').value.trim();

            if (!idRejilla) {
                alert('Error: ID de rejilla no válido');
                return;
            }

            const btnGuardar = event.target;
            const textoOriginal = btnGuardar.innerHTML;
            btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Guardando...';
            btnGuardar.disabled = true;

            const formData = new FormData();
            formData.append('accion', 'actualizar_descripcion_rejilla');
            formData.append('id_rejilla', idRejilla);
            formData.append('descripcion', descripcion);

            fetch('controller/rejillasController.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.exito) {
                        actualizarDescripcionEnVista(idRejilla, descripcion);
                        const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditarDescripcion'));
                        modal.hide();
                        mostrarMensaje('Descripción actualizada correctamente', 'success');
                    } else {
                        mostrarMensaje('Error: ' + data.mensaje, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarMensaje('Error de conexión al actualizar la descripción', 'error');
                })
                .finally(() => {
                    btnGuardar.innerHTML = textoOriginal;
                    btnGuardar.disabled = false;
                });
        }

        function actualizarDescripcionEnVista(idRejilla, nuevaDescripcion) {
            const elementoDesc = document.getElementById('desc-' + idRejilla);
            if (elementoDesc) {
                if (nuevaDescripcion && nuevaDescripcion.trim() !== '') {
                    elementoDesc.innerHTML = `
                <i class="fas fa-info-circle me-1 text-info"></i>
                <span>${nuevaDescripcion.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</span>
            `;
                } else {
                    elementoDesc.innerHTML = `
                <i class="fas fa-plus-circle me-1 text-muted"></i>
                <span class="text-muted">Agregar descripción...</span>
            `;
                }
            }
        }

        function mostrarMensaje(mensaje, tipo) {
            const alertClass = tipo === 'success' ? 'alert-success' : 'alert-danger';
            const alert = document.createElement('div');
            alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            alert.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 300px;';
            alert.innerHTML = `
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

            document.body.appendChild(alert);
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 3000);
        }
    </script>

</body>
