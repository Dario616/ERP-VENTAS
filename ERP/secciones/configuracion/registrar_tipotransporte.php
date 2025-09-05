<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();

$mensaje = "";
$tipo_mensaje = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'] ?? 'crear';

    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre']);

        // Validaciones
        if (empty($nombre)) {
            $mensaje = "El nombre del tipo de transporte es obligatorio.";
            $tipo_mensaje = "error";
        } elseif (strlen($nombre) < 3) {
            $mensaje = "El nombre debe tener al menos 3 caracteres.";
            $tipo_mensaje = "error";
        } else {
            try {
                // Verificar si el tipo de transporte ya existe
                $stmt_check = $conexion->prepare("SELECT COUNT(*) FROM sist_prod_tipo_transporte WHERE LOWER(nombre) = LOWER(?)");
                $stmt_check->execute([$nombre]);
                $tipo_existe = $stmt_check->fetchColumn();
                if ($tipo_existe > 0) {
                    $mensaje = "El tipo de transporte ya existe. Por favor, ingrese otro nombre.";
                    $tipo_mensaje = "error";
                } else {
                    // Insertar nuevo tipo de transporte
                    $stmt = $conexion->prepare("INSERT INTO sist_prod_tipo_transporte (nombre) VALUES (?)");
                    $resultado = $stmt->execute([$nombre]);

                    if ($resultado) {
                        $mensaje = "Tipo de transporte registrado exitosamente.";
                        $tipo_mensaje = "success";
                        // Limpiar el formulario
                        $nombre = "";
                    } else {
                        $mensaje = "Error al registrar el tipo de transporte. Intente nuevamente.";
                        $tipo_mensaje = "error";
                    }
                }
            } catch (PDOException $e) {
                $mensaje = "Error de base de datos: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    } elseif ($accion === 'editar') {
        $id = $_POST['id'];
        $nombre = trim($_POST['nombre']);

        // Validaciones
        if (empty($nombre)) {
            $mensaje = "El nombre del tipo de transporte es obligatorio.";
            $tipo_mensaje = "error";
        } elseif (strlen($nombre) < 3) {
            $mensaje = "El nombre debe tener al menos 3 caracteres.";
            $tipo_mensaje = "error";
        } else {
            try {
                // Verificar si el tipo de transporte ya existe (excluyendo el actual)
                $stmt_check = $conexion->prepare("SELECT COUNT(*) FROM sist_prod_tipo_transporte WHERE LOWER(nombre) = LOWER(?) AND id != ?");
                $stmt_check->execute([$nombre, $id]);
                $tipo_existe = $stmt_check->fetchColumn();

                if ($tipo_existe > 0) {
                    $mensaje = "El tipo de transporte ya existe. Por favor, ingrese otro nombre.";
                    $tipo_mensaje = "error";
                } else {
                    // Actualizar tipo de transporte
                    $stmt = $conexion->prepare("UPDATE sist_prod_tipo_transporte SET nombre = ? WHERE id = ?");
                    $resultado = $stmt->execute([$nombre, $id]);

                    if ($resultado) {
                        $mensaje = "Tipo de transporte actualizado exitosamente.";
                        $tipo_mensaje = "success";
                    } else {
                        $mensaje = "Error al actualizar el tipo de transporte. Intente nuevamente.";
                        $tipo_mensaje = "error";
                    }
                }
            } catch (PDOException $e) {
                $mensaje = "Error de base de datos: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    } elseif ($accion === 'eliminar') {
        $id = $_POST['id'];

        try {
            // Verificar si el tipo de transporte está siendo utilizado (aquí puedes agregar validaciones adicionales)
            // Por ejemplo, verificar si tiene envíos asociados

            $stmt = $conexion->prepare("DELETE FROM sist_prod_tipo_transporte WHERE id = ?");
            $resultado = $stmt->execute([$id]);

            if ($resultado) {
                $mensaje = "Tipo de transporte eliminado exitosamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al eliminar el tipo de transporte.";
                $tipo_mensaje = "error";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23503') { // Foreign key violation
                $mensaje = "No se puede eliminar el tipo de transporte porque tiene registros asociados.";
            } else {
                $mensaje = "Error de base de datos: " . $e->getMessage();
            }
            $tipo_mensaje = "error";
        }
    }
}

// Obtener lista de tipos de transporte existentes
try {
    $stmt_tipos = $conexion->query("SELECT id, nombre FROM sist_prod_tipo_transporte ORDER BY nombre");
    $tipos_existentes = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tipos_existentes = [];
}
$breadcrumb_items = ['CONFIGURACION', 'TIPOS DE TRANSPORTE'];
$item_urls = [
    $url_base . 'secciones/configuracion/index.php',
];
$additional_css = [$url_base . 'secciones/configuracion/utils/styles.css'];
include $path_base . "components/head.php";
?>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <!-- Main Content -->
    <div class="main-container">
        <div class="container-fluid">

            <!-- Mostrar mensajes -->
            <?php if (!empty($mensaje)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-<?php echo $tipo_mensaje === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show fade-in" role="alert">
                            <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                            <?php echo htmlspecialchars($mensaje); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Header con botón para crear tipo de transporte -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5><i class="fas fa-route"></i>Tipos de Transporte Registrados</h5>
                                <small class="text-muted">Lista de modalidades de transporte disponibles</small>
                            </div>
                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#modalRegistroTipo">
                                <i class="fas fa-plus me-2"></i>Nuevo Tipo
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($tipos_existentes)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-route"></i>
                                    <p>No hay tipos de transporte registrados en el sistema</p>
                                </div>
                            <?php else: ?>


                                <!-- Tabla alternativa para pantallas grandes -->
                                <div class="table-responsive mt-4">
                                    <table class="table table-hover d-none d-lg-table">
                                        <thead>
                                            <tr>
                                                <th><i class="fas fa-hashtag me-1"></i>ID</th>
                                                <th><i class="fas fa-route me-1"></i>Tipo de Transporte</th>
                                                <th><i class="fas fa-tools me-1"></i>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tipos_existentes as $tipo): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($tipo['id']); ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="transport-icon me-2 <?php
                                                                                            $nombre_lower = strtolower($tipo['nombre']);
                                                                                            if (strpos($nombre_lower, 'terrestre') !== false) echo 'icon-terrestre';
                                                                                            elseif (strpos($nombre_lower, 'aereo') !== false) echo 'icon-aereo';
                                                                                            elseif (strpos($nombre_lower, 'maritimo') !== false) echo 'icon-maritimo';
                                                                                            elseif (strpos($nombre_lower, 'ferroviario') !== false) echo 'icon-ferroviario';
                                                                                            else echo 'icon-default';
                                                                                            ?>" style="width: 30px; height: 30px;">
                                                                <i class="fas fa-<?php
                                                                                    if (strpos($nombre_lower, 'terrestre') !== false) echo 'truck';
                                                                                    elseif (strpos($nombre_lower, 'aereo') !== false) echo 'plane';
                                                                                    elseif (strpos($nombre_lower, 'maritimo') !== false) echo 'ship';
                                                                                    elseif (strpos($nombre_lower, 'ferroviario') !== false) echo 'train';
                                                                                    else echo 'route';
                                                                                    ?> text-white"></i>
                                                            </div>
                                                            <?php echo htmlspecialchars($tipo['nombre']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary btn-action me-1"
                                                            onclick="editarTipo(<?php echo $tipo['id']; ?>, '<?php echo htmlspecialchars($tipo['nombre']); ?>')"
                                                            title="Editar tipo">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger btn-action"
                                                            onclick="confirmarEliminar(<?php echo $tipo['id']; ?>, '<?php echo htmlspecialchars($tipo['nombre']); ?>')"
                                                            title="Eliminar tipo">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Registro de Tipo de Transporte -->
    <div class="modal fade" id="modalRegistroTipo" tabindex="-1" aria-labelledby="modalRegistroTipoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRegistroTipoLabel">
                        <i class="fas fa-plus me-2"></i>Nuevo Tipo de Transporte
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="formTipo">
                        <input type="hidden" name="accion" value="crear">

                        <div class="mb-3">
                            <label for="nombre" class="form-label">
                                <i class="fas fa-route me-1"></i>Nombre del Tipo de Transporte
                            </label>
                            <input type="text" class="form-control" id="nombre" name="nombre"
                                value="<?php echo htmlspecialchars($nombre ?? ''); ?>"
                                placeholder="Escribe..." required>
                            <div class="form-text">Ingrese la modalidad de transporte</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" form="formTipo" class="btn btn-info" id="btnRegistrar">
                        <i class="fas fa-save me-1"></i>Registrar Tipo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Editar Tipo de Transporte -->
    <div class="modal fade" id="modalEditarTipo" tabindex="-1" aria-labelledby="modalEditarTipoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarTipoLabel">
                        <i class="fas fa-edit me-2"></i>Editar Tipo de Transporte
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="formEditarTipo">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" id="editId">

                        <div class="mb-3">
                            <label for="editNombre" class="form-label">
                                <i class="fas fa-route me-1"></i>Nombre del Tipo de Transporte
                            </label>
                            <input type="text" class="form-control" id="editNombre" name="nombre" required>
                            <div class="form-text">Modifique el nombre del tipo de transporte</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" form="formEditarTipo" class="btn btn-info">
                        <i class="fas fa-save me-1"></i>Actualizar Tipo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación de Eliminación -->
    <div class="modal fade" id="modalEliminarTipo" tabindex="-1" aria-labelledby="modalEliminarTipoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalEliminarTipoLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <i class="fas fa-route fa-3x text-danger mb-3"></i>
                        <h5>¿Está seguro que desea eliminar este tipo de transporte?</h5>
                        <p class="text-muted mb-0">Tipo: <strong id="eliminarNombreTipo"></strong></p>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Esta acción no se puede deshacer. Si hay registros asociados, no se podrá eliminar.
                        </div>
                    </div>
                    <form method="POST" action="" id="formEliminarTipo">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" id="eliminarId">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" form="formEliminarTipo" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Eliminar Tipo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; <?php echo date('Y'); ?> Sistema de Producción America TNT. Todos los derechos reservados.</p>
                </div>
                <div class="col-md-6 text-end">
                    <p><i class="fas fa-route me-1"></i>Gestión de Tipos de Transporte</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Scripts personalizados -->
    <script>
        // Actualizar reloj
        function updateTime() {
            const now = new Date();
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            const timeString = now.toLocaleDateString('es-ES', options);
            const timeElement = document.querySelector('.hero-timestamp');
            if (timeElement) {
                timeElement.innerHTML = '<i class="fas fa-clock me-2"></i>' + timeString;
            }
        }
        setInterval(updateTime, 1000);

        // Validación del formulario de crear tipo
        document.getElementById('formTipo').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();

            if (nombre.length < 3) {
                e.preventDefault();
                alert('El nombre debe tener al menos 3 caracteres');
                return false;
            }

            // Deshabilitar botón para evitar doble envío
            document.getElementById('btnRegistrar').disabled = true;
            document.getElementById('btnRegistrar').innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Registrando...';
        });

        // Validación en tiempo real
        document.getElementById('nombre').addEventListener('input', function() {
            const nombre = this.value.trim();
            const btnRegistrar = document.getElementById('btnRegistrar');

            if (nombre.length >= 3) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                btnRegistrar.disabled = false;
            } else {
                this.classList.remove('is-valid');
                if (nombre.length > 0) {
                    this.classList.add('is-invalid');
                }
                btnRegistrar.disabled = nombre.length === 0 ? false : true;
            }
        });

        // Función para editar tipo
        function editarTipo(id, nombre) {
            document.getElementById('editId').value = id;
            document.getElementById('editNombre').value = nombre;

            const modal = new bootstrap.Modal(document.getElementById('modalEditarTipo'));
            modal.show();
        }

        // Función para confirmar eliminación
        function confirmarEliminar(id, nombre) {
            document.getElementById('eliminarId').value = id;
            document.getElementById('eliminarNombreTipo').textContent = nombre;

            const modal = new bootstrap.Modal(document.getElementById('modalEliminarTipo'));
            modal.show();
        }

        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.dashboard-card, .tipo-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Limpiar formulario cuando se cierre el modal de crear
        document.getElementById('modalRegistroTipo').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formTipo').reset();
            document.getElementById('btnRegistrar').disabled = false;
            document.getElementById('btnRegistrar').innerHTML = '<i class="fas fa-save me-1"></i>Registrar Tipo';

            // Limpiar validaciones visuales
            const inputs = document.querySelectorAll('#formTipo input');
            inputs.forEach(input => {
                input.classList.remove('is-valid', 'is-invalid');
            });
        });

        // Auto-abrir modal si hay errores después del envío
        <?php if (!empty($mensaje) && $tipo_mensaje === 'error'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const modal = new bootstrap.Modal(document.getElementById('modalRegistroTipo'));
                modal.show();
            });
        <?php endif; ?>

        // Auto-cerrar alert si registro fue exitoso
        <?php if (!empty($mensaje) && $tipo_mensaje === 'success'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    const alert = document.querySelector('.alert');
                    if (alert) {
                        alert.style.transition = 'all 0.5s ease';
                        alert.style.opacity = '0';
                        alert.style.transform = 'translateY(-20px)';
                        setTimeout(() => alert.remove(), 500);
                    }
                }, 5000);
            });
        <?php endif; ?>
    </script>

</body>

</html>