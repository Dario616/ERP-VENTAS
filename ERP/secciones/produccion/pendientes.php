<?php
include "../../auth/verificar_sesion.php";
include "../../config/conexionBD.php";
include "repository/productionRepository.php";
requerirRol(['1', '2','3']);
requerirLogin();

// Crear instancia del repositorio
$productionRepo = new ProductionRepositoryUniversal($conexion);

// Variables para filtros y paginación
$filtro_numero_orden = isset($_GET['numero_orden']) ? trim($_GET['numero_orden']) : '';
$filtro_cliente = isset($_GET['cliente']) ? trim($_GET['cliente']) : '';
$filtro_tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
$filtro_estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$items_por_pagina = 10;

// Construir criterios de búsqueda
$criterios = [];
if (!empty($filtro_numero_orden)) {
    // Validar que sea un número
    if (is_numeric($filtro_numero_orden)) {
        $criterios['numero_orden'] = intval($filtro_numero_orden);
    }
}
if (!empty($filtro_cliente)) {
    $criterios['cliente'] = $filtro_cliente;
}
if (!empty($filtro_tipo)) {
    $criterios['tipo_producto'] = $filtro_tipo;
}

// Obtener órdenes
$resultado = $productionRepo->buscarOrdenesPorCriterios($criterios);
$ordenes = $resultado['ordenes'] ?? [];
$error = $resultado['error'] ?? null;

// Paginación simple
$total_ordenes = count($ordenes);
$total_paginas = ceil($total_ordenes / $items_por_pagina);
$offset = ($pagina_actual - 1) * $items_por_pagina;
$ordenes_pagina = array_slice($ordenes, $offset, $items_por_pagina);

// Función para obtener detalles adicionales de una orden
function obtenerDetallesOrden($conexion, $numeroOrden)
{
    try {
        $sql = "SELECT COUNT(*) as items_producidos, 
                       SUM(peso_liquido) as peso_total,
                       MAX(fecha_hora_producida) as ultima_produccion
                FROM sist_prod_stock 
                WHERE id_orden_produccion = :numero_orden";

        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return ['items_producidos' => 0, 'peso_total' => 0, 'ultima_produccion' => null];
    }
}

// Función para obtener el tipo de producto de una orden
function obtenerTipoProducto($conexion, $numeroOrden)
{
    try {
        // Buscar en TNT primero
        $sql = "SELECT 'TNT' as tipo, 
                       CASE WHEN LOWER(COALESCE(nombre, '')) LIKE '%laminado%' THEN 'LAMINADORA' ELSE 'TNT' END as tipo_real,
                       nombre as producto
                FROM sist_ventas_op_tnt WHERE id_orden_produccion = :numero_orden
                UNION ALL
                SELECT 'SPUNLACE' as tipo, 'SPUNLACE' as tipo_real, nombre as producto
                FROM sist_ventas_op_spunlace WHERE id_orden_produccion = :numero_orden
                UNION ALL
                SELECT 'TOALLITAS' as tipo, 'TOALLITAS' as tipo_real, nombre as producto
                FROM sist_ventas_op_toallitas WHERE id_orden_produccion = :numero_orden
                UNION ALL
                SELECT 'PAÑOS' as tipo, 'PAÑOS' as tipo_real, nombre as producto
                FROM sist_ventas_op_panos WHERE id_orden_produccion = :numero_orden
                LIMIT 1";

        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
        $stmt->execute();

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado ? $resultado : ['tipo_real' => 'N/A', 'producto' => 'Sin producto'];
    } catch (Exception $e) {
        return ['tipo_real' => 'ERROR', 'producto' => 'Error al consultar'];
    }
}

// Función para obtener historial reciente de una orden
function obtenerHistorialOrden($conexion, $numeroOrden, $limite = 3)
{
    try {
        $sql = "SELECT numero_item, peso_bruto, peso_liquido, tipo_producto,
                       TO_CHAR(fecha_hora_producida, 'DD/MM HH24:MI') as fecha_hora
                FROM sist_prod_stock 
                WHERE id_orden_produccion = :numero_orden 
                ORDER BY fecha_hora_producida DESC 
                LIMIT :limite";

        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>America TNT - Órdenes Pendientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="<?php echo $url_base; ?>utils/icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/produccion/utils/pendientes.css">
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
                        <a class="nav-link active" href="<?php echo $url_base; ?>secciones/produccion/pendientes.php">
                            <i class="fas fa-clock me-1"></i>Pendientes
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="main-container">
        <div class="container-fluid">
            <!-- Filtros -->
            <div class="filtros-container">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">
                            <i class="fas fa-hashtag me-1"></i>Número de Orden
                        </label>
                        <div class="filtro-numero-orden">
                            <i class="fas fa-hashtag input-icon"></i>
                            <input type="number"
                                class="form-control"
                                name="numero_orden"
                                value="<?php echo htmlspecialchars($filtro_numero_orden); ?>"
                                min="1"
                                title="Ingrese el número exacto de la orden">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">
                            <i class="fas fa-user me-1"></i>Cliente
                        </label>
                        <input type="text" class="form-control" name="cliente"
                            value="<?php echo htmlspecialchars($filtro_cliente); ?>"
                            placeholder="Buscar por cliente...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">
                            <i class="fas fa-cog me-1"></i>Tipo de Producto
                        </label>
                        <select class="form-select" name="tipo">
                            <option value="">Todos los tipos</option>
                            <option value="TNT" <?php echo $filtro_tipo === 'TNT' ? 'selected' : ''; ?>>TNT</option>
                            <option value="SPUNLACE" <?php echo $filtro_tipo === 'SPUNLACE' ? 'selected' : ''; ?>>SPUNLACE</option>
                            <option value="TOALLITAS" <?php echo $filtro_tipo === 'TOALLITAS' ? 'selected' : ''; ?>>TOALLITAS</option>
                            <option value="PAÑOS" <?php echo $filtro_tipo === 'PAÑOS' ? 'selected' : ''; ?>>PAÑOS</option>
                            <option value="LAMINADORA" <?php echo $filtro_tipo === 'LAMINADORA' ? 'selected' : ''; ?>>LAMINADORA</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-1"></i>Filtrar
                        </button>
                        <a href="?" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-times me-1"></i>Limpiar
                        </a>
                        <?php if (!empty($filtro_numero_orden) && is_numeric($filtro_numero_orden)): ?>
                            <button type="button" class="btn btn-info btn-sm" onclick="buscarOrdenDirecta(<?php echo $filtro_numero_orden; ?>)">
                                <i class="fas fa-external-link-alt me-1"></i>Ir Directamente
                            </button>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Mostrar filtros activos -->
                <?php if (!empty($filtro_numero_orden) || !empty($filtro_cliente) || !empty($filtro_tipo)): ?>
                    <div class="filtros-activos">
                        <small class="text-muted me-2"><i class="fas fa-filter me-1"></i>Filtros activos:</small>

                        <?php if (!empty($filtro_numero_orden)): ?>
                            <div class="filtro-activo">
                                <i class="fas fa-hashtag me-1"></i>Orden: <?php echo htmlspecialchars($filtro_numero_orden); ?>
                                <button type="button" class="btn-close-filtro" onclick="removerFiltro('numero_orden')" title="Quitar filtro">×</button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($filtro_cliente)): ?>
                            <div class="filtro-activo">
                                <i class="fas fa-user me-1"></i>Cliente: <?php echo htmlspecialchars(substr($filtro_cliente, 0, 15)); ?><?php echo strlen($filtro_cliente) > 15 ? '...' : ''; ?>
                                <button type="button" class="btn-close-filtro" onclick="removerFiltro('cliente')" title="Quitar filtro">×</button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($filtro_tipo)): ?>
                            <div class="filtro-activo">
                                <i class="fas fa-cog me-1"></i>Tipo: <?php echo htmlspecialchars($filtro_tipo); ?>
                                <button type="button" class="btn-close-filtro" onclick="removerFiltro('tipo')" title="Quitar filtro">×</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Mensajes de error -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-custom">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Lista de órdenes -->
            <?php if (empty($ordenes_pagina)): ?>
                <div class="no-orders">
                    <i class="fas fa-inbox fa-4x mb-3 d-block"></i>
                    <h4>No se encontraron órdenes</h4>
                    <p class="mb-0">
                        <?php if (!empty($filtro_numero_orden)): ?>
                            La orden #<?php echo htmlspecialchars($filtro_numero_orden); ?> no existe, no está pendiente o fue finalizada.
                        <?php else: ?>
                            No hay órdenes que coincidan con los filtros seleccionados
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($ordenes_pagina as $orden): ?>
                    <?php
                    $detalles = obtenerDetallesOrden($conexion, $orden['numero_orden']);
                    $tipoInfo = obtenerTipoProducto($conexion, $orden['numero_orden']);
                    $historial = obtenerHistorialOrden($conexion, $orden['numero_orden'], 3);
                    $estadoClase = '';
                    switch ($orden['estado']) {
                        case 'Pendiente':
                            $estadoClase = 'estado-pendiente';
                            break;
                        case 'Completado':
                            $estadoClase = 'estado-completado';
                            break;
                        default:
                            $estadoClase = 'estado-en-proceso';
                            break;
                    }
                    ?>
                    <div class="orden-card" onclick="selectOrder(this, <?php echo $orden['numero_orden']; ?>)">
                        <div class="orden-header">
                            <div class="d-flex align-items-center gap-3">
                                <div class="orden-numero">
                                    <i class="fas fa-hashtag me-1"></i>
                                    Orden <?php echo $orden['numero_orden']; ?>
                                    <?php if (!empty($filtro_numero_orden) && $filtro_numero_orden == $orden['numero_orden']): ?>
                                        <span class="badge bg-success ms-2" style="font-size: 0.6rem;">
                                            <i class="fas fa-bullseye me-1"></i>Encontrada
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="orden-estado <?php echo $estadoClase; ?>">
                                    <?php echo htmlspecialchars($orden['estado']); ?>
                                </div>
                            </div>
                            <div class="text-muted">
                                <small>
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($orden['fecha_orden'])); ?>
                                </small>
                            </div>
                        </div>

                        <div class="orden-info">
                            <div class="info-item">
                                <i class="fas fa-user info-icon"></i>
                                <div>
                                    <div class="info-label">Cliente</div>
                                    <div class="info-value"><?php echo htmlspecialchars($orden['cliente'] ?: 'Sin cliente'); ?></div>
                                </div>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-cog info-icon"></i>
                                <div>
                                    <div class="info-label">Tipo</div>
                                    <div class="info-value"><?php echo htmlspecialchars($tipoInfo['tipo_real']); ?></div>
                                </div>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-cube info-icon"></i>
                                <div>
                                    <div class="info-label">Items Producidos</div>
                                    <div class="info-value"><?php echo intval($detalles['items_producidos']); ?></div>
                                </div>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-weight info-icon"></i>
                                <div>
                                    <div class="info-label">Peso Total</div>
                                    <div class="info-value"><?php echo number_format(floatval($detalles['peso_total']), 2); ?> kg</div>
                                </div>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-box info-icon"></i>
                                <div>
                                    <div class="info-label">Producto</div>
                                    <div class="info-value"><?php echo htmlspecialchars(substr($tipoInfo['producto'], 0)); ?><?php echo strlen($tipoInfo['producto']) > 25 ? '' : ''; ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Historial mini -->
                        <?php if (!empty($historial)): ?>
                            <div class="historial-mini">
                                <div style="font-size: 0.65rem; font-weight: 600; color: var(--america-navy); margin-bottom: 0.2rem;">
                                    <i class="fas fa-history me-1"></i>Últimos registros:
                                </div>
                                <?php foreach ($historial as $item): ?>
                                    <div class="historial-item">
                                        #<?php echo $item['numero_item']; ?> - <?php echo $item['tipo_producto']; ?> - <?php echo number_format($item['peso_liquido'], 1); ?>kg - <?php echo $item['fecha_hora']; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="acciones-orden">
                            <a href="ordenproduccion.php?orden=<?php echo $orden['numero_orden']; ?>" class="btn-accion btn-producir" onclick="event.stopPropagation()">
                                <i class="fas fa-play"></i>
                                Producir
                            </a>
                            <button onclick="event.stopPropagation(); showOrderDetails(<?php echo $orden['numero_orden']; ?>)" class="btn-accion btn-ver">
                                <i class="fas fa-eye"></i>
                                Ver Detalles
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                    <nav aria-label="Paginación de órdenes" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($pagina_actual > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?pagina=<?php echo $pagina_actual - 1; ?>&numero_orden=<?php echo urlencode($filtro_numero_orden); ?>&cliente=<?php echo urlencode($filtro_cliente); ?>&tipo=<?php echo urlencode($filtro_tipo); ?>&estado=<?php echo urlencode($filtro_estado); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $pagina_actual - 2); $i <= min($total_paginas, $pagina_actual + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $pagina_actual ? 'active' : ''; ?>">
                                    <a class="page-link" href="?pagina=<?php echo $i; ?>&numero_orden=<?php echo urlencode($filtro_numero_orden); ?>&cliente=<?php echo urlencode($filtro_cliente); ?>&tipo=<?php echo urlencode($filtro_tipo); ?>&estado=<?php echo urlencode($filtro_estado); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($pagina_actual < $total_paginas): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?pagina=<?php echo $pagina_actual + 1; ?>&numero_orden=<?php echo urlencode($filtro_numero_orden); ?>&cliente=<?php echo urlencode($filtro_cliente); ?>&tipo=<?php echo urlencode($filtro_tipo); ?>&estado=<?php echo urlencode($filtro_estado); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </div>

    <!-- Modal para detalles de orden -->
    <div class="modal fade modal-orden-detalle" id="modalDetallesOrden" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>Detalles de la Orden
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2">Obteniendo detalles de la orden...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" id="btnIrAProduccion">
                        <i class="fas fa-play me-2"></i>Ir a Producción
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let selectedOrder = null;

        function selectOrder(card, numeroOrden) {
            // Limpiar selección anterior
            document.querySelectorAll('.orden-card').forEach(c => {
                c.classList.remove('selected');
            });

            // Seleccionar nueva tarjeta
            card.classList.add('selected');
            selectedOrder = numeroOrden;

            // Efecto visual
            card.style.transform = 'translateY(-2px) scale(1.01)';
            card.style.boxShadow = '0 8px 25px rgba(220, 38, 38, 0.3)';
        }

        function goToProduction(numeroOrden) {
            window.location.href = `ordenproduccion.php?orden=${numeroOrden}`;
        }

        function buscarOrdenDirecta(numeroOrden) {
            window.location.href = `ordenproduccion.php?orden=${numeroOrden}`;
        }

        function removerFiltro(filtro) {
            const url = new URL(window.location);
            url.searchParams.delete(filtro);
            url.searchParams.delete('pagina'); // Reset página cuando cambia filtro
            window.location.href = url.toString();
        }

        function showOrderDetails(numeroOrden) {
            selectedOrder = numeroOrden;
            const modal = new bootstrap.Modal(document.getElementById('modalDetallesOrden'));

            // Mostrar modal con loading
            document.getElementById('modalContent').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando detalles...</span>
                    </div>
                    <p class="mt-2">Obteniendo información de la orden #${numeroOrden}...</p>
                </div>
            `;

            modal.show();

            // Simular carga de detalles
            setTimeout(() => {
                // Obtener datos de la orden desde el DOM
                const orderCard = document.querySelector(`[onclick*="${numeroOrden}"]`);
                let clienteText = 'Cliente no disponible';
                let tipoText = 'Tipo no disponible';
                let itemsText = '0';
                let pesoText = '0.00 kg';

                if (orderCard) {
                    const infoItems = orderCard.querySelectorAll('.info-value');
                    if (infoItems.length >= 4) {
                        clienteText = infoItems[0].textContent;
                        tipoText = infoItems[1].textContent;
                        itemsText = infoItems[2].textContent;
                        pesoText = infoItems[3].textContent;
                    }
                }

                document.getElementById('modalContent').innerHTML = `
                    <div class="order-details">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-hashtag me-2"></i>Orden de Producción #${numeroOrden}</h6>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detalle-section">
                                    <h6><i class="fas fa-info-circle me-2"></i>Información General</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li><strong>Cliente:</strong> ${clienteText}</li>
                                        <li><strong>Tipo de Producto:</strong> ${tipoText}</li>
                                        <li><strong>Estado:</strong> <span class="badge bg-warning">Pendiente</span></li>
                                        <li><strong>Fecha de Creación:</strong> ${new Date().toLocaleDateString()}</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detalle-section">
                                    <h6><i class="fas fa-chart-bar me-2"></i>Progreso de Producción</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li><strong>Items Producidos:</strong> ${itemsText}</li>
                                        <li><strong>Peso Total:</strong> ${pesoText}</li>
                                        <li><strong>Progreso:</strong> <span class="badge bg-info">En espera</span></li>
                                        <li><strong>Última Actividad:</strong> Sin actividad</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detalle-section">
                            <h6><i class="fas fa-cogs me-2"></i>Configuración de Producción</h6>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Para ver detalles completos y configurar la producción, haga clic en "Ir a Producción"
                            </div>
                        </div>
                    </div>
                `;
            }, 800);
        }

        // Configurar botón de ir a producción en modal
        document.getElementById('btnIrAProduccion').addEventListener('click', function() {
            if (selectedOrder) {
                window.location.href = `ordenproduccion.php?orden=${selectedOrder}`;
            }
        });

        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Escape para limpiar selección
            if (e.key === 'Escape') {
                document.querySelectorAll('.orden-card').forEach(c => {
                    c.classList.remove('selected');
                    c.style.transform = '';
                    c.style.boxShadow = '';
                });
                selectedOrder = null;
            }

            // Enter para ir a producción con orden seleccionada
            if (e.key === 'Enter' && selectedOrder) {
                goToProduction(selectedOrder);
            }

            // F5 para actualizar
            if (e.key === 'F5') {
                e.preventDefault();
                location.reload();
            }

            // Ctrl+F para focus en filtro de número de orden
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.querySelector('input[name="numero_orden"]').focus();
            }
        });

        // Auto-completar número de orden mientras escribes
        document.querySelector('input[name="numero_orden"]').addEventListener('input', function(e) {
            const valor = e.target.value;
            if (valor.length >= 3) {
                // Aquí podrías implementar autocompletado si tienes un endpoint para ello
                console.log(`Buscando órdenes que contengan: ${valor}`);
            }
        });

        // Auto-actualizar cada 2 minutos
        setInterval(function() {
            // Mostrar indicador sutil de actualización
            const indicator = document.createElement('div');
            indicator.style.cssText = `
                position: fixed; 
                top: 80px; 
                right: 20px; 
                background: rgba(59, 130, 246, 0.9); 
                color: white; 
                padding: 0.5rem 1rem; 
                border-radius: 20px; 
                font-size: 0.75rem; 
                z-index: 1000;
                animation: fadeInOut 2s ease;
            `;
            indicator.innerHTML = '<i class="fas fa-sync fa-spin me-2"></i>Actualizando...';

            // Agregar animación CSS
            const style = document.createElement('style');
            style.textContent = `
                @keyframes fadeInOut {
                    0%, 100% { opacity: 0; transform: translateX(100%); }
                    20%, 80% { opacity: 1; transform: translateX(0); }
                }
            `;
            document.head.appendChild(style);
            document.body.appendChild(indicator);

            setTimeout(() => {
                location.reload();
            }, 1500);

        }, 120000); // 2 minutos

        console.log('🏭 Sistema de Órdenes Pendientes America TNT cargado correctamente con filtro de número de orden');
    </script>
</body>

</html>