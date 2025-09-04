<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirRol(['1', '4']); // Administrador y Producción

// Establecer parámetros de paginación
$registrosPorPagina = 10;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$inicio = ($paginaActual - 1) * $registrosPorPagina;

// Establecer filtros
$filtroCliente = isset($_GET['cliente']) ? $_GET['cliente'] : '';
$filtroProducto = isset($_GET['producto']) ? $_GET['producto'] : '';
$filtroTipoProducto = isset($_GET['tipo_producto']) ? $_GET['tipo_producto'] : '';
$filtroFechaDesde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$filtroFechaHasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

// Construir la consulta SQL con filtros - Solo ventas en producción
$sqlWhere = " WHERE (pp.estado IS NULL OR pp.estado = '') ";

$params = [];

if (!empty($filtroCliente)) {
    $sqlWhere .= " AND v.cliente ILIKE :cliente";
    $params[':cliente'] = '%' . $filtroCliente . '%';
}

if (!empty($filtroProducto)) {
    $sqlWhere .= " AND prod.descripcion ILIKE :producto";
    $params[':producto'] = '%' . $filtroProducto . '%';
}

if (!empty($filtroTipoProducto)) {
    $sqlWhere .= " AND prod.tipoproducto ILIKE :tipo_producto";
    $params[':tipo_producto'] = '%' . $filtroTipoProducto . '%';
}

if (!empty($filtroFechaDesde)) {
    $sqlWhere .= " AND pp.fecha_asignacion >= :fecha_desde";
    $params[':fecha_desde'] = $filtroFechaDesde . ' 00:00:00';
}

if (!empty($filtroFechaHasta)) {
    $sqlWhere .= " AND pp.fecha_asignacion <= :fecha_hasta";
    $params[':fecha_hasta'] = $filtroFechaHasta . ' 23:59:59';
}

try {
    // Contar total de registros con filtros
    $sqlCount = "SELECT COUNT(DISTINCT v.id) as total 
            FROM public.sist_ventas_presupuesto v 
            JOIN public.sist_ventas_productos_produccion pp ON v.id = pp.id_venta
            JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
            $sqlWhere AND (pp.estado IS NULL OR pp.estado <> 'Devuelto a PCP') AND pp.destino = 'Producción'";
    $stmtCount = $conexion->prepare($sqlCount);
    foreach ($params as $key => $value) {
        $stmtCount->bindValue($key, $value);
    }
    $stmtCount->execute();
    $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPaginas = ceil($totalRegistros / $registrosPorPagina);

    // Consulta para obtener los registros de la página actual con filtros
    $sql = "SELECT DISTINCT v.id, v.cliente, v.moneda, v.monto_total, v.estado,
            v.fecha_venta,
            (SELECT COUNT(*) FROM public.sist_ventas_productos_produccion 
             WHERE id_venta = v.id AND destino = 'Producción' AND (estado IS NULL OR estado <> 'Devuelto a PCP')) as total_productos,
            (SELECT MIN(fecha_asignacion) FROM public.sist_ventas_productos_produccion 
             WHERE id_venta = v.id AND destino = 'Producción') as fecha_ingreso,
            (SELECT STRING_AGG(DISTINCT prod.tipoproducto, ', ') 
             FROM public.sist_ventas_productos_produccion pp2 
             JOIN public.sist_ventas_pres_product prod ON pp2.id_producto = prod.id
             WHERE pp2.id_venta = v.id AND pp2.destino = 'Producción' 
             AND (pp2.estado IS NULL OR pp2.estado <> 'Devuelto a PCP')) as tipos_productos
            FROM public.sist_ventas_presupuesto v
            JOIN public.sist_ventas_productos_produccion pp ON v.id = pp.id_venta
            JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
            $sqlWhere AND pp.destino = 'Producción'
            ORDER BY fecha_ingreso DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $conexion->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $registrosPorPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $inicio, PDO::PARAM_INT);
    $stmt->execute();
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener estadísticas por tipo de producto
    $sqlEstadisticas = "SELECT 
                        COUNT(DISTINCT v.id) as total_ventas,
                        COUNT(DISTINCT pp.id_producto) as total_productos,
                        COUNT(CASE WHEN prod.tipoproducto = 'TNT' THEN 1 END) as productos_tnt,
                        COUNT(CASE WHEN prod.tipoproducto = 'TOALLITAS' THEN 1 END) as productos_toallitas,
                        COUNT(CASE WHEN prod.tipoproducto NOT IN ('TNT', 'TOALLITAS') OR prod.tipoproducto IS NULL THEN 1 END) as productos_otros
                        FROM public.sist_ventas_presupuesto v
                        JOIN public.sist_ventas_productos_produccion pp ON v.id = pp.id_venta
                        JOIN public.sist_ventas_pres_product prod ON pp.id_producto = prod.id
                        WHERE v.estado IN ('En Producción', 'En Producción/Expedición') AND pp.destino = 'Producción'
                        AND (pp.estado IS NULL OR pp.estado <> 'Devuelto a PCP')";
    $estadisticas = $conexion->query($sqlEstadisticas)->fetch(PDO::FETCH_ASSOC);

    // Obtener tipos de productos disponibles para el filtro
    $sqlTipos = "SELECT DISTINCT prod.tipoproducto 
                 FROM public.sist_ventas_pres_product prod 
                 JOIN public.sist_ventas_productos_produccion pp ON prod.id = pp.id_producto
                 WHERE pp.destino = 'Producción' AND prod.tipoproducto IS NOT NULL
                 ORDER BY prod.tipoproducto";
    $tiposProductos = $conexion->query($sqlTipos)->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $error = "Error al obtener los datos: " . $e->getMessage();
    $estadisticas = [
        'total_ventas' => 0,
        'total_productos' => 0,
        'productos_tnt' => 0,
        'productos_toallitas' => 0,
        'productos_otros' => 0
    ];
    $tiposProductos = [];
}
$breadcrumb_items = ['Sector Produccion', 'Productos para Produccion'];
$item_urls = [
    $url_base . 'secciones/produccion/main.php',
];
$additional_css = [$url_base . 'secciones/sectorPcp/utils/styles.css'];
include $path_base . "components/head.php";
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h4 class="mb-0">
                    <i class="fas fa-industry me-2"></i>Productos para Producción
                </h4>
            </div>

            <div class="card-body">
                <?php if (isset($_GET['mensaje'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_GET['mensaje']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label for="cliente" class="form-label">Cliente</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="cliente" name="cliente"
                                        value="<?php echo htmlspecialchars($filtroCliente); ?>"
                                        placeholder="Buscar por cliente...">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label for="producto" class="form-label">Producto</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-box"></i></span>
                                    <input type="text" class="form-control" id="producto" name="producto"
                                        value="<?php echo htmlspecialchars($filtroProducto); ?>"
                                        placeholder="Buscar por producto...">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label for="tipo_producto" class="form-label">Tipo Producto</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-tags"></i></span>
                                    <select class="form-select" id="tipo_producto" name="tipo_producto">
                                        <option value="">Todos los tipos</option>
                                        <?php foreach ($tiposProductos as $tipo): ?>
                                            <option value="<?php echo htmlspecialchars($tipo); ?>"
                                                <?php echo $filtroTipoProducto === $tipo ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($tipo); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label for="fecha_desde" class="form-label">Fecha Desde</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                    <input type="date" class="form-control" id="fecha_desde" name="fecha_desde"
                                        value="<?php echo htmlspecialchars($filtroFechaDesde); ?>">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                    <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta"
                                        value="<?php echo htmlspecialchars($filtroFechaHasta); ?>">
                                </div>
                            </div>

                            <div class="col-md-2 d-flex align-items-end">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filtrar
                                    </button>
                                    <a href="<?php echo $url_base; ?>secciones/sectorPcp/produccion.php" class="btn btn-secondary">
                                        <i class="fas fa-sync-alt me-1"></i>Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de ventas en producción -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th><i class="fas fa-hashtag me-1"></i>ID Venta</th>
                                <th><i class="fas fa-user me-1"></i>Cliente</th>
                                <th><i class="fas fa-tags me-1"></i>Tipos</th>
                                <th><i class="fas fa-money-bill-wave me-1"></i>Monto Total</th>
                                <th><i class="fas fa-calendar-check me-1"></i>Fecha Ingreso</th>
                                <th class="text-center"><i class="fas fa-cogs me-1"></i>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ventas)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i>No hay productos en producción
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ventas as $venta): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($venta['id']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($venta['cliente']); ?></td>
                                        <td>
                                            <?php
                                            // 1. Obtener la cadena de texto con los tipos de productos desde la variable correcta "$venta".
                                            $tipos_string = $venta['tipos_productos'] ?? '';

                                            // 2. Separar la cadena en un array de tipos individuales.
                                            $tipos_array = explode(', ', $tipos_string);

                                            // 3. Iterar sobre cada tipo para mostrar su respectivo ícono y badge.
                                            foreach ($tipos_array as $tipo) {
                                                if (empty(trim($tipo))) continue; // Omitir si el tipo está vacío

                                                $tipoUpper = mb_strtoupper(trim($tipo), 'UTF-8');
                                                $badgeClass = 'bg-secondary'; // Clase por defecto
                                                $icon = 'fas fa-box';         // Ícono por defecto

                                                switch ($tipoUpper) {
                                                    case 'TNT':
                                                        $badgeClass = 'bg-primary';
                                                        $icon = 'fas fa-scroll';
                                                        break;
                                                    case 'SPUNLACE':
                                                        $badgeClass = 'bg-black text-white'; // Clase personalizada para Spunlace
                                                        $icon = 'fas fa-swatchbook';
                                                        break;
                                                    case 'TOALLITAS':
                                                        $badgeClass = 'bg-success';
                                                        $icon = 'fas fa-soap';
                                                        break;
                                                    case 'PAÑOS':
                                                        $badgeClass = 'btn-pdf-paños'; // Reutilizando tu clase de botón para consistencia
                                                        $icon = 'fas fa-layer-group';
                                                        break;
                                                    case 'LAMINADORA':
                                                        $badgeClass = 'bg-dark';
                                                        $icon = 'fas fa-layer-group';
                                                        break;
                                                }

                                                // 4. Imprimir el badge con su ícono para cada tipo.
                                                echo '<span class="badge ' . $badgeClass . ' badge-tipo me-1">';
                                                echo '<i class="' . $icon . ' me-1"></i>' . htmlspecialchars(trim($tipo));
                                                echo '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <strong>
                                                <?php
                                                $simbolo = $venta['moneda'] === 'Dólares' ? 'U$D ' : '₲ ';
                                                echo $simbolo . number_format((float)$venta['monto_total'], 2, ',', '.');
                                                ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php
                                            if ($venta['fecha_ingreso']) {
                                                echo '<small>' . date('d/m/Y H:i', strtotime($venta['fecha_ingreso'])) . '</small>';
                                            } else {
                                                echo '<span class="text-muted">N/A</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?php echo $url_base; ?>secciones/sectorPcp/verproduccion.php?id=<?php echo $venta['id']; ?>"
                                                class="btn btn-danger btn-sm"
                                                title="Procesar productos">
                                                <i class="fas fa-tools me-1"></i>Procesar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($totalPaginas > 1): ?>
                    <nav aria-label="Paginación de ventas" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $paginaActual <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $paginaActual - 1; ?>&cliente=<?php echo urlencode($filtroCliente); ?>&producto=<?php echo urlencode($filtroProducto); ?>&tipo_producto=<?php echo urlencode($filtroTipoProducto); ?>&fecha_desde=<?php echo urlencode($filtroFechaDesde); ?>&fecha_hasta=<?php echo urlencode($filtroFechaHasta); ?>">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </a>
                            </li>

                            <?php
                            // Lógica de paginación inteligente
                            $rango = 2;
                            $inicio = max(1, $paginaActual - $rango);
                            $fin = min($totalPaginas, $paginaActual + $rango);

                            if ($inicio > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?pagina=1&cliente=' . urlencode($filtroCliente) . '&producto=' . urlencode($filtroProducto) . '&tipo_producto=' . urlencode($filtroTipoProducto) . '&fecha_desde=' . urlencode($filtroFechaDesde) . '&fecha_hasta=' . urlencode($filtroFechaHasta) . '">1</a></li>';
                                if ($inicio > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            for ($i = $inicio; $i <= $fin; $i++): ?>
                                <li class="page-item <?php echo $paginaActual == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?pagina=<?php echo $i; ?>&cliente=<?php echo urlencode($filtroCliente); ?>&producto=<?php echo urlencode($filtroProducto); ?>&tipo_producto=<?php echo urlencode($filtroTipoProducto); ?>&fecha_desde=<?php echo urlencode($filtroFechaDesde); ?>&fecha_hasta=<?php echo urlencode($filtroFechaHasta); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor;

                            if ($fin < $totalPaginas) {
                                if ($fin < $totalPaginas - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?pagina=' . $totalPaginas . '&cliente=' . urlencode($filtroCliente) . '&producto=' . urlencode($filtroProducto) . '&tipo_producto=' . urlencode($filtroTipoProducto) . '&fecha_desde=' . urlencode($filtroFechaDesde) . '&fecha_hasta=' . urlencode($filtroFechaHasta) . '">' . $totalPaginas . '</a></li>';
                            }
                            ?>

                            <li class="page-item <?php echo $paginaActual >= $totalPaginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $paginaActual + 1; ?>&cliente=<?php echo urlencode($filtroCliente); ?>&producto=<?php echo urlencode($filtroProducto); ?>&tipo_producto=<?php echo urlencode($filtroTipoProducto); ?>&fecha_desde=<?php echo urlencode($filtroFechaDesde); ?>&fecha_hasta=<?php echo urlencode($filtroFechaHasta); ?>">
                                    Siguiente <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>

                    <!-- Información de paginación -->
                    <div class="text-center text-muted mt-2">
                        <small>
                            Mostrando <?php echo (($paginaActual - 1) * $registrosPorPagina) + 1; ?> -
                            <?php echo min($paginaActual * $registrosPorPagina, $totalRegistros); ?>
                            de <?php echo $totalRegistros; ?> registros
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS y jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto-submit del formulario cuando cambia el select de tipo de producto
        document.getElementById('tipo_producto').addEventListener('change', function() {
            if (this.value !== '') {
                this.form.submit();
            }
        });

        // Tooltip para las estadísticas
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>

</html>