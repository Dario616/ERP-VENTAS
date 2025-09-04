<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1']);
include "controllers/nuevaordenController.php";
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Orden de Producción</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo $url_base; ?>utils/icon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/produccion/utils/nueva_orden_manual.css">
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
                        <a class="nav-link active" href="#">
                            <i class="fas fa-plus-circle me-1"></i>Nueva Orden
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <div class="container-fluid">
            <div class="row">
                <!-- Formulario - Columna Izquierda -->
                <div class="col-lg-5 col-xl-4">
                    <form method="POST" id="formNuevaOrden">
                        <input type="hidden" name="crear_orden" value="1">

                        <!-- Información del Producto -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Nueva Orden de Producción para Stock</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="product-input-container">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="descripcion" name="descripcion" required
                                                placeholder="Buscar producto existente"
                                                autocomplete="off">
                                            <label for="descripcion">Buscar Producto Existente *</label>
                                        </div>
                                        <div class="loading-indicator" id="loadingIndicator">
                                            <i class="fas fa-spinner fa-spin me-1"></i>Buscando productos...
                                        </div>
                                        <div class="product-suggestions" id="productSuggestions"></div>
                                    </div>

                                    <div class="form-text mt-2">
                                        <i class="fas fa-database me-1"></i>
                                        <strong>Solo productos registrados en el sistema.</strong>
                                    </div>

                                    <div class="required-product-notice" id="requiredProductNotice">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Producto requerido:</strong> Solo se pueden crear órdenes con productos existentes en la base de datos.
                                    </div>

                                    <!-- Vista previa de especificaciones -->
                                    <div id="previewBox" class="preview-box">
                                        <h6 id="previewTitle"><i class="fas fa-database me-2"></i>Producto Seleccionado:</h6>
                                        <div id="statusIndicator"></div>
                                        <div id="productDetails"></div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-6 mb-3" id="unidadMedidaContainer" style="display: none;">
                                        <div class="form-floating position-relative">
                                            <select class="form-control" id="unidad_medida" name="unidad_medida" required>
                                                <option value="">Seleccionar...</option>
                                            </select>
                                            <label for="unidad_medida" id="labelUnidadMedida">Unidad *</label>
                                            <div class="unidades-loading" id="unidadesLoading">
                                                <i class="fas fa-spinner fa-spin me-1"></i>Cargando...
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="form-floating">
                                            <input type="number" class="form-control" id="cantidad" name="cantidad" step="0.01" min="0.01" required placeholder="Cantidad">
                                            <label for="cantidad">Cantidad *</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-floating">
                                        <textarea class="form-control" id="observaciones" name="observaciones" style="height: 80px" placeholder="Observaciones"></textarea>
                                        <label for="observaciones">Observaciones</label>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-create-order" id="btnCrearOrden" disabled>
                                    <i class="fas fa-cogs me-2"></i>Crear Orden de Producción
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Listado de Órdenes - Columna Derecha -->
                <div class="col-lg-7 col-xl-8">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="fas fa-list-alt me-2"></i>Órdenes de Producción Recientes</h6>
                                <div class="input-group input-group-sm" style="width: 200px;">
                                    <input type="number" id="filterOrden" class="form-control" placeholder="Filtrar # Orden"
                                        value="<?php echo htmlspecialchars($_GET['orden'] ?? ''); ?>">
                                    <button class="btn btn-outline-secondary" id="btnFiltrar">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" onclick="cargarOrdenes(1)">
                                    <i class="fas fa-sync-alt me-1"></i>Actualizar
                                </button>
                            </div>
                        </div>
                        <div class="orders-container" id="ordersContainer">
                            <div class="orders-loading" id="ordersLoading">
                                <i class="fas fa-spinner fa-spin me-2"></i>Cargando órdenes...
                            </div>
                            <div id="ordersList"></div>
                        </div>
                        <div class="pagination-container" id="paginationContainer" style="display: none;">
                            <nav>
                                <ul class="pagination pagination-sm" id="pagination"></ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ⭐ INFORMACIÓN DE DEBUG (OPCIONAL) ⭐ -->
    <div class="debug-info" id="debugInfo">
        <strong>Debug Info:</strong><br>
        <span id="debugContent">Sistema inicializado</span>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const ITEMS_POR_PAGINA = <?php echo $items_por_pagina; ?>;

        // ⭐ CONFIGURACIÓN PARA TIPOS SOPORTADOS ⭐
        const TIPOS_SOPORTADOS = <?php echo json_encode($tipos_soportados); ?>;

        // ⭐ INFORMACIÓN DEL USUARIO Y SESIÓN ⭐
        const USUARIO_ACTUAL = "<?php echo htmlspecialchars($_SESSION['nombre']); ?>";
        const URL_BASE = "<?php echo $url_base; ?>";

        // ⭐ FUNCIÓN DE DEBUG (OPCIONAL) ⭐
        function updateDebugInfo(mensaje) {
            const debugElement = document.getElementById('debugContent');
            if (debugElement) {
                const timestamp = new Date().toLocaleTimeString();
                debugElement.innerHTML = `${timestamp}: ${mensaje}`;
            }
        }

        // Log inicial
        console.log('Nueva Orden de Producción - Sistema iniciado');
        console.log('Tipos soportados:', TIPOS_SOPORTADOS);
        updateDebugInfo('Sistema iniciado correctamente');
    </script>
    <script src="js/nueva-orden.js"></script>
</body>

</html>