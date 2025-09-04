<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();

$mensaje = "";
$tipo_mensaje = "";

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'] ?? 'crear';

    if ($accion === 'crear') {
        $descripcion = trim($_POST['descripcion']);

        // Validaciones
        if (empty($descripcion)) {
            $mensaje = "La descripción de la transportadora es obligatoria.";
            $tipo_mensaje = "error";
        } elseif (strlen($descripcion) < 3) {
            $mensaje = "La descripción debe tener al menos 3 caracteres.";
            $tipo_mensaje = "error";
        } else {
            try {
                // Verificar si la transportadora ya existe
                $stmt_check = $conexion->prepare("SELECT COUNT(*) FROM sist_prod_transportadora WHERE LOWER(descripcion) = LOWER(?)");
                $stmt_check->execute([$descripcion]);
                $transportadora_existe = $stmt_check->fetchColumn();

                if ($transportadora_existe > 0) {
                    $mensaje = "La transportadora ya existe. Por favor, ingrese otra descripción.";
                    $tipo_mensaje = "error";
                } else {
                    // Insertar nueva transportadora
                    $stmt = $conexion->prepare("INSERT INTO sist_prod_transportadora (descripcion) VALUES (?)");
                    $resultado = $stmt->execute([$descripcion]);

                    if ($resultado) {
                        $mensaje = "Transportadora registrada exitosamente.";
                        $tipo_mensaje = "success";
                        // Limpiar el formulario
                        $descripcion = "";
                    } else {
                        $mensaje = "Error al registrar la transportadora. Intente nuevamente.";
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
        $descripcion = trim($_POST['descripcion']);

        // Validaciones
        if (empty($descripcion)) {
            $mensaje = "La descripción de la transportadora es obligatoria.";
            $tipo_mensaje = "error";
        } elseif (strlen($descripcion) < 3) {
            $mensaje = "La descripción debe tener al menos 3 caracteres.";
            $tipo_mensaje = "error";
        } else {
            try {
                // Verificar si la transportadora ya existe (excluyendo la actual)
                $stmt_check = $conexion->prepare("SELECT COUNT(*) FROM sist_prod_transportadora WHERE LOWER(descripcion) = LOWER(?) AND id != ?");
                $stmt_check->execute([$descripcion, $id]);
                $transportadora_existe = $stmt_check->fetchColumn();

                if ($transportadora_existe > 0) {
                    $mensaje = "La transportadora ya existe. Por favor, ingrese otra descripción.";
                    $tipo_mensaje = "error";
                } else {
                    // Actualizar transportadora
                    $stmt = $conexion->prepare("UPDATE sist_prod_transportadora SET descripcion = ? WHERE id = ?");
                    $resultado = $stmt->execute([$descripcion, $id]);

                    if ($resultado) {
                        $mensaje = "Transportadora actualizada exitosamente.";
                        $tipo_mensaje = "success";
                    } else {
                        $mensaje = "Error al actualizar la transportadora. Intente nuevamente.";
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
            // Verificar si la transportadora está siendo utilizada (aquí puedes agregar validaciones adicionales)
            // Por ejemplo, verificar si tiene envíos asociados

            $stmt = $conexion->prepare("DELETE FROM sist_prod_transportadora WHERE id = ?");
            $resultado = $stmt->execute([$id]);

            if ($resultado) {
                $mensaje = "Transportadora eliminada exitosamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al eliminar la transportadora.";
                $tipo_mensaje = "error";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23503') { // Foreign key violation
                $mensaje = "No se puede eliminar la transportadora porque tiene registros asociados.";
            } else {
                $mensaje = "Error de base de datos: " . $e->getMessage();
            }
            $tipo_mensaje = "error";
        }
    }
}

// Obtener lista de transportadoras existentes
try {
    $stmt_transportadoras = $conexion->query("SELECT id, descripcion FROM sist_prod_transportadora ORDER BY descripcion");
    $transportadoras_existentes = $stmt_transportadoras->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $transportadoras_existentes = [];
}
$breadcrumb_items = ['CONFIGURACION', 'TRANSPORTADORAS'];
$item_urls = [
    $url_base . 'secciones/configuracion/index.php',
];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMERICA TNT - TRANSPORTADORAS</title>

    <!-- Favicon principal -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $url_base; ?>assets/favicon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $url_base; ?>assets/favicon-192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $url_base; ?>assets/apple-touch-icon.png">
    <link rel="icon" href="<?php echo $url_base; ?>utils/icon.ico" type="image/x-icon">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Estilos del Dashboard -->
    <link rel="stylesheet" href="<?php echo $url_base; ?>index-styles.css">

    <style>
        .modal-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-bottom: none;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .transportadora-card {
            transition: all 0.3s ease;
            border-left: 4px solid #28a745;
        }

        .transportadora-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Agregar estos estilos */
        .table-responsive {
            will-change: auto;
            transform: translateZ(0);
            /* Forzar aceleración por hardware */
        }

        .table tbody tr {
            transition: none !important;
            /* Desactivar transiciones en filas */
        }

        .table tbody tr:hover {
            background-color: rgba(0, 0, 0, .075) !important;
            transform: none !important;
            /* Evitar transformaciones */
        }
    </style>
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="hero-title">
                        <i class="fas fa-truck me-3"></i>
                        Gestión de Transportadoras
                    </h1>
                    <p class="hero-subtitle">
                        Administrar empresas de transporte para el sistema de distribución America TNT
                    </p>
                    <div class="hero-timestamp">
                        <i class="fas fa-clock me-2"></i>
                        <?php echo date('l, d \d\e F \d\e Y - H:i:s'); ?>
                    </div>
                </div>
                <div class="col-lg-4 text-center">
                    <div class="hero-icon">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

            <!-- Header con botón para crear transportadora -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5><i class="fas fa-truck"></i>Transportadoras Registradas</h5>
                                <small class="text-muted">Lista de empresas de transporte disponibles</small>
                            </div>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalRegistroTransportadora">
                                <i class="fas fa-plus me-2"></i>Nueva Transportadora
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($transportadoras_existentes)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-truck-loading"></i>
                                    <p>No hay transportadoras registradas en el sistema</p>
                                </div>
                            <?php else: ?>
                                <!-- Tabla alternativa para pantallas grandes -->
                                <div class="table-responsive mt-4">
                                    <table class="table table-hover d-none d-lg-table">
                                        <thead>
                                            <tr>
                                                <th><i class="fas fa-hashtag me-1"></i>ID</th>
                                                <th><i class="fas fa-truck me-1"></i>Descripción</th>
                                                <th><i class="fas fa-tools me-1"></i>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($transportadoras_existentes as $transportadora): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($transportadora['id']); ?></td>
                                                    <td>
                                                        <i class="fas fa-truck me-2 text-success"></i>
                                                        <?php echo htmlspecialchars($transportadora['descripcion']); ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary btn-action me-1"
                                                            onclick="editarTransportadora(<?php echo $transportadora['id']; ?>, '<?php echo htmlspecialchars($transportadora['descripcion']); ?>')"
                                                            title="Editar transportadora">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger btn-action"
                                                            onclick="confirmarEliminar(<?php echo $transportadora['id']; ?>, '<?php echo htmlspecialchars($transportadora['descripcion']); ?>')"
                                                            title="Eliminar transportadora">
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

    <!-- Modal de Registro de Transportadora -->
    <div class="modal fade" id="modalRegistroTransportadora" tabindex="-1" aria-labelledby="modalRegistroTransportadoraLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRegistroTransportadoraLabel">
                        <i class="fas fa-plus me-2"></i>Nueva Transportadora
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="formTransportadora">
                        <input type="hidden" name="accion" value="crear">

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">
                                <i class="fas fa-truck me-1"></i>Nombre de la Transportadora
                            </label>
                            <input type="text" class="form-control" id="descripcion" name="descripcion"
                                value="<?php echo htmlspecialchars($descripcion ?? ''); ?>"
                                placeholder="Escribe..." required>
                            <div class="form-text">Ingrese el nombre completo de la empresa de transporte</div>
                        </div>

                        <div class="mb-3">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Información:</strong> Esta transportadora estará disponible para asignar a envíos y expediciones.
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" form="formTransportadora" class="btn btn-success" id="btnRegistrar">
                        <i class="fas fa-save me-1"></i>Registrar Transportadora
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Editar Transportadora -->
    <div class="modal fade" id="modalEditarTransportadora" tabindex="-1" aria-labelledby="modalEditarTransportadoraLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarTransportadoraLabel">
                        <i class="fas fa-edit me-2"></i>Editar Transportadora
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="formEditarTransportadora">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" id="editId">

                        <div class="mb-3">
                            <label for="editDescripcion" class="form-label">
                                <i class="fas fa-truck me-1"></i>Nombre de la Transportadora
                            </label>
                            <input type="text" class="form-control" id="editDescripcion" name="descripcion" required>
                            <div class="form-text">Modifique el nombre de la empresa de transporte</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" form="formEditarTransportadora" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>Actualizar Transportadora
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación de Eliminación -->
    <div class="modal fade" id="modalEliminarTransportadora" tabindex="-1" aria-labelledby="modalEliminarTransportadoraLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalEliminarTransportadoraLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <i class="fas fa-truck fa-3x text-danger mb-3"></i>
                        <h5>¿Está seguro que desea eliminar esta transportadora?</h5>
                        <p class="text-muted mb-0">Transportadora: <strong id="eliminarNombreTransportadora"></strong></p>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Esta acción no se puede deshacer. Si hay envíos asociados, no se podrá eliminar.
                        </div>
                    </div>
                    <form method="POST" action="" id="formEliminarTransportadora">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" id="eliminarId">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" form="formEliminarTransportadora" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Eliminar Transportadora
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
                    <p><i class="fas fa-truck me-1"></i>Gestión de Transportadoras</p>
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

        // Validación del formulario de crear transportadora
        document.getElementById('formTransportadora').addEventListener('submit', function(e) {
            const descripcion = document.getElementById('descripcion').value.trim();

            if (descripcion.length < 3) {
                e.preventDefault();
                alert('La descripción debe tener al menos 3 caracteres');
                return false;
            }

            // Deshabilitar botón para evitar doble envío
            document.getElementById('btnRegistrar').disabled = true;
            document.getElementById('btnRegistrar').innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Registrando...';
        });

        // Validación en tiempo real
        document.getElementById('descripcion').addEventListener('input', function() {
            const descripcion = this.value.trim();
            const btnRegistrar = document.getElementById('btnRegistrar');

            if (descripcion.length >= 3) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                btnRegistrar.disabled = false;
            } else {
                this.classList.remove('is-valid');
                if (descripcion.length > 0) {
                    this.classList.add('is-invalid');
                }
                btnRegistrar.disabled = descripcion.length === 0 ? false : true;
            }
        });

        // Función para editar transportadora
        function editarTransportadora(id, descripcion) {
            document.getElementById('editId').value = id;
            document.getElementById('editDescripcion').value = descripcion;

            const modal = new bootstrap.Modal(document.getElementById('modalEditarTransportadora'));
            modal.show();
        }

        // Función para confirmar eliminación
        function confirmarEliminar(id, descripcion) {
            document.getElementById('eliminarId').value = id;
            document.getElementById('eliminarNombreTransportadora').textContent = descripcion;

            const modal = new bootstrap.Modal(document.getElementById('modalEliminarTransportadora'));
            modal.show();
        }

        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.dashboard-card, .transportadora-card');
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
        document.getElementById('modalRegistroTransportadora').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formTransportadora').reset();
            document.getElementById('btnRegistrar').disabled = false;
            document.getElementById('btnRegistrar').innerHTML = '<i class="fas fa-save me-1"></i>Registrar Transportadora';

            // Limpiar validaciones visuales
            const inputs = document.querySelectorAll('#formTransportadora input');
            inputs.forEach(input => {
                input.classList.remove('is-valid', 'is-invalid');
            });
        });

        // Auto-abrir modal si hay errores después del envío
        <?php if (!empty($mensaje) && $tipo_mensaje === 'error'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const modal = new bootstrap.Modal(document.getElementById('modalRegistroTransportadora'));
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

        // Función de búsqueda rápida (opcional)
        function filtrarTransportadoras(termino) {
            const cards = document.querySelectorAll('.transportadora-card');
            const rows = document.querySelectorAll('tbody tr');

            cards.forEach(card => {
                const texto = card.textContent.toLowerCase();
                card.style.display = texto.includes(termino.toLowerCase()) ? 'block' : 'none';
            });

            rows.forEach(row => {
                const texto = row.textContent.toLowerCase();
                row.style.display = texto.includes(termino.toLowerCase()) ? 'table-row' : 'none';
            });
        }
    </script>

</body>

</html>