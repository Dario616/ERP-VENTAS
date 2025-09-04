<?php
require_once __DIR__ . '/../../config/database/conexionBD.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';
require_once __DIR__ . '/controllers/VentaController.php';
require_once __DIR__ . '/services/ProductoService.php';

requerirLogin();

date_default_timezone_set('America/Asuncion');

$ventaController = new VentaController($conexion, $url_base);
$productoService = new ProductoService($conexion);

try {
    $productos_por_tipo = $productoService->obtenerProductosAgrupadosPorTipo();
    $configuracion = $ventaController->obtenerConfiguracionJS();
} catch (Exception $e) {
    error_log("Error en seleccionar_productos_modal: " . $e->getMessage());
    $productos_por_tipo = [];
    $configuracion = [];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar Productos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="ico" href="<?php echo $url_base; ?>utils/icono.ico">
    <style>
        .product-selection-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            min-height: 200px;
        }

        .product-selection-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }

        .product-selection-card.selected {
            border-color: #28a745;
            background-color: #f8fff9;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .product-checkbox {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 20px;
            height: 20px;
            z-index: 10;
            cursor: pointer;
        }

        .product-image-small {
            width: 100%;
            height: 80px;
            background: linear-gradient(45deg, #e9ecef, #dee2e6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #6c757d;
            border-radius: 8px;
            margin-bottom: 10px;
            overflow: hidden;
        }

        .product-image-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 6px;
        }

        .selection-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .tipo-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .tipo-title {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 15px;
        }

        .selected-counter {
            background: #28a745;
            color: white;
            border-radius: 20px;
            padding: 5px 15px;
            font-weight: 600;
            position: sticky;
            top: 10px;
            z-index: 1000;
        }

        .search-section {
            position: sticky;
            top: 0;
            background: white;
            z-index: 999;
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .product-info-text {
            font-size: 0.85rem;
            line-height: 1.3;
            padding: 5px;
        }

        .product-title-small {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 5px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
            line-height: 1.3;
            color: #2c3e50;
        }

        .select-all-section {
            background: #e3f2fd;
            border-radius: 10px;
            padding: 10px 15px;
            margin-bottom: 15px;
            border: 1px solid #bbdefb;
        }

        .footer-buttons {
            background: white;
            border-top: 1px solid #e9ecef;
            padding: 15px;
            position: sticky;
            bottom: 0;
            z-index: 1000;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        }

        .specific-search-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .specific-search-toggle {
            background: #17a2b8;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .specific-search-content {
            display: none;
        }

        .specific-search-content.active {
            display: block;
        }

        .specific-search-help {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 10px 15px;
            margin-top: 10px;
            border-radius: 0 5px 5px 0;
        }

        .specific-search-input {
            border: 2px solid #17a2b8;
            border-radius: 5px;
        }

        .specific-search-input:focus {
            border-color: #138496;
            box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25);
        }

        /* Loading state */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin-top: -10px;
            margin-left: -10px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Responsividad mejorada */
        @media (max-width: 768px) {

            .col-lg-2,
            .col-md-3 {
                flex: 0 0 50%;
                max-width: 50%;
            }

            .selection-header h4 {
                font-size: 1.2rem;
            }

            .footer-buttons .d-flex {
                flex-direction: column;
                gap: 10px;
            }

            .footer-buttons .justify-content-end {
                justify-content: stretch !important;
            }
        }

        /* Indicador de stock */
        .stock-indicator {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
        }

        .stock-indicator.low-stock {
            background: #ffc107;
            color: #000;
        }

        .stock-indicator.out-of-stock {
            background: #dc3545;
        }

        .highlight-match {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="selection-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h4 class="mb-0">
                        <i class="fas fa-shopping-cart me-2"></i>Seleccionar Productos
                    </h4>
                    <p class="mb-0">Marque los productos que desea agregar al presupuesto</p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="selected-counter">
                        <i class="fas fa-check-circle me-2"></i>
                        <span id="contador-seleccionados">0</span> productos seleccionados
                    </div>
                </div>
            </div>
        </div>

        <div class="search-section">
            <div class="specific-search-section">
                <button type="button" class="specific-search-toggle" id="toggleSpecificSearch">
                    <i class="fas fa-search-plus me-1"></i>
                    CLICK AQUI! - Búsqueda específica TNT/SPUNLACE/LAMINADORA
                    <i class="fas fa-chevron-down ms-1" id="toggleIcon"></i>
                </button>

                <div class="specific-search-content" id="specificSearchContent">
                    <div class="row">
                        <div class="col-md-8">
                            <label class="form-label">
                                <i class="fas fa-calculator me-1"></i>
                                Buscar por: Gramatura, Ancho, Metros (separados por comas)
                            </label>
                            <input type="text"
                                id="specificSearchInput"
                                class="form-control specific-search-input"
                                placeholder="Ej: 60, 200, 250 (para 60 g/m², 200 cm de ancho, 250 metros)"
                                autocomplete="off">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn-outline-info" type="button" id="clearSpecificSearch">
                                <i class="fas fa-eraser me-1"></i>Limpiar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="searchProducts" class="form-control"
                            placeholder="Buscar productos por nombre, código o tipo..."
                            autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <select class="form-select" id="filterByType">
                        <option value="">Todos los tipos</option>
                        <?php foreach ($productos_por_tipo as $tipo => $productos_tipo): ?>
                            <option value="<?php echo htmlspecialchars($tipo); ?>">
                                <?php echo htmlspecialchars($tipo); ?> (<?php echo count($productos_tipo); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div id="productos-container">
            <?php if (empty($productos_por_tipo)): ?>
                <div class="alert alert-warning text-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    No se encontraron productos disponibles
                </div>
            <?php else: ?>
                <?php foreach ($productos_por_tipo as $tipo => $productos_tipo): ?>
                    <div class="tipo-section" data-tipo="<?php echo htmlspecialchars($tipo); ?>">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="tipo-title">
                                <i class="fas fa-layer-group me-2"></i>
                                <?php echo htmlspecialchars($tipo); ?>
                                <span class="badge bg-light text-dark ms-2"><?php echo count($productos_tipo); ?></span>
                            </div>
                        </div>

                        <div class="row">
                            <?php foreach ($productos_tipo as $producto): ?>
                                <?php
                                $cantidad = (float)($producto['cantidad'] ?? 0);
                                $stockClass = '';
                                $stockText = '';

                                if ($cantidad <= 0) {
                                    $stockClass = 'out-of-stock';
                                    $stockText = 'Sin stock';
                                } elseif ($cantidad < 10) {
                                    $stockClass = 'low-stock';
                                    $stockText = 'Stock bajo';
                                } else {
                                    $stockText = 'En stock';
                                }
                                ?>
                                <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-3 producto-item"
                                    data-tipo="<?php echo htmlspecialchars($tipo); ?>"
                                    data-descripcion="<?php echo htmlspecialchars($producto['descripcion']); ?>"
                                    data-search="<?php
                                                    $searchDataOriginal = $producto['descripcion'] . ' ' . $producto['id'] . ' ' . ($producto['codigobr'] ?? '') . ' ' . $tipo;
                                                    $variaciones = [
                                                        $searchDataOriginal,
                                                        str_replace(['²', '³', '°'], ['2', '3', 'o'], $searchDataOriginal),
                                                        str_replace(['²', '³', '°', '/'], ['2', '3', 'o', ''], $searchDataOriginal),
                                                        str_replace(['²', '³', '°', '/'], ['2', '3', 'o', ' '], $searchDataOriginal)
                                                    ];

                                                    $searchDataFinal = strtolower(implode(' ', array_unique($variaciones)));
                                                    echo htmlspecialchars($searchDataFinal);
                                                    ?>" data-stock="<?php echo $cantidad; ?>">
                                    <div class="product-selection-card h-100 p-2 <?php echo $cantidad <= 0 ? 'loading' : ''; ?>"
                                        onclick="<?php echo $cantidad > 0 ? "toggleProductSelection({$producto['id']})" : ''; ?>">

                                        <input type="checkbox"
                                            class="product-checkbox"
                                            data-product-id="<?php echo $producto['id']; ?>"
                                            data-tipo="<?php echo htmlspecialchars($tipo); ?>"
                                            <?php echo $cantidad <= 0 ? 'disabled' : ''; ?>
                                            onchange="updateSelection(this)">

                                        <div class="product-image-small">
                                            <?php if (!empty($producto['base64img'])): ?>
                                                <img src="data:<?php echo htmlspecialchars($producto['tipoimg']); ?>;base64,<?php echo $producto['base64img']; ?>"
                                                    alt="<?php echo htmlspecialchars($producto['descripcion']); ?>"
                                                    loading="lazy">
                                            <?php else: ?>
                                                <i class="fas fa-box"></i>
                                            <?php endif; ?>
                                        </div>

                                        <div class="product-info-text">
                                            <div class="product-title-small text-primary">
                                                <?php echo htmlspecialchars($producto['descripcion']); ?>
                                            </div>
                                            <div class="text-muted">
                                                <small>
                                                    <i class="fas fa-hashtag"></i>
                                                    <?php echo htmlspecialchars($producto['id']); ?>
                                                    <?php if (!empty($producto['codigobr'])): ?>
                                                        | <?php echo htmlspecialchars($producto['codigobr']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <?php if ($cantidad > 0): ?>
                                                <div class="text-success">
                                                    <small>Peso liq:
                                                        <?php echo number_format($cantidad, 2, ',', '.'); ?>
                                                    </small>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-danger">
                                                    <small><i class="fas fa-exclamation-triangle"></i> Sin stock</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <script type="application/json" class="product-data-<?php echo $producto['id']; ?>">
                                            <?php echo json_encode([
                                                'id' => $producto['id'],
                                                'descripcion' => $producto['descripcion'],
                                                'ncm' => $producto['ncm'] ?? '',
                                                'tipo_producto' => $tipo,
                                                'unidades_medida' => $productoService->obtenerUnidadesMedida($producto['id']),
                                                'base64img' => $producto['base64img'] ?? '',
                                                'tipoimg' => $producto['tipoimg'] ?? '',
                                                'cantidad_disponible' => $cantidad,
                                                'codigobr' => $producto['codigobr'] ?? ''
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
                                        </script>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="footer-buttons mt-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary" id="selectAllProducts">
                            <i class="fas fa-check-double me-2"></i>Seleccionar Todos
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="clearAllSelections">
                            <i class="fas fa-times me-2"></i>Limpiar Selección
                        </button>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <div class="d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-secondary" onclick="cerrarModal()">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="button" class="btn btn-success" id="addSelectedProducts" disabled>
                            <i class="fas fa-plus me-2"></i>Agregar Seleccionados (<span id="contador-btn">0</span>)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const CONFIG = <?php echo json_encode($configuracion); ?>;

        let productosSeleccionados = new Set();
        let isLoading = false;
        let isSpecificSearchActive = false;
        const PRODUCTOS_ESPECIFICOS = ['TNT', 'SPUNLACE', 'LAMINADORA'];

        function toggleProductSelection(productId) {
            if (isLoading) return;

            const checkbox = document.querySelector(`[data-product-id="${productId}"]`);
            if (checkbox && !checkbox.disabled) {
                checkbox.checked = !checkbox.checked;
                updateSelection(checkbox);
            }
        }

        function updateSelection(checkbox) {
            const productId = checkbox.getAttribute('data-product-id');
            const card = checkbox.closest('.product-selection-card');

            if (checkbox.checked) {
                productosSeleccionados.add(productId);
                card.classList.add('selected');
            } else {
                productosSeleccionados.delete(productId);
                card.classList.remove('selected');
            }

            updateCounters();
            updateSelectAllCheckboxes();
        }

        function updateCounters() {
            const count = productosSeleccionados.size;
            document.getElementById('contador-seleccionados').textContent = count;
            document.getElementById('contador-btn').textContent = count;
            document.getElementById('addSelectedProducts').disabled = count === 0;
        }

        function updateSelectAllCheckboxes() {
            document.querySelectorAll('.select-all-tipo').forEach(selectAllCheckbox => {
                const tipo = selectAllCheckbox.getAttribute('data-tipo');
                const checkboxesTipo = document.querySelectorAll(`.producto-item[data-tipo="${tipo}"]:not([style*="display: none"]) .product-checkbox:not([disabled])`);
                const checkedCount = Array.from(checkboxesTipo).filter(cb => cb.checked).length;

                if (checkboxesTipo.length > 0) {
                    selectAllCheckbox.checked = checkedCount === checkboxesTipo.length;
                    selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < checkboxesTipo.length;
                } else {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                }
            });
        }

        function parseSpecificSearch(input) {
            if (!input.trim()) return null;

            const parts = input.split(',').map(part => part.trim()).filter(part => part !== '');

            if (parts.length === 0) return null;

            return {
                gramatura: parts[0] || null,
                ancho: parts[1] || null,
                metros: parts[2] || null
            };
        }

        function matchesSpecificSearch(descripcion, searchParams) {
            if (!searchParams) return false;

            const desc = descripcion.toLowerCase();
            let matches = true;

            if (searchParams.gramatura) {
                const gramaturaPattern = new RegExp(`${searchParams.gramatura}\\s*g/m[²2]`, 'i');
                const gramaturaMatch = gramaturaPattern.test(desc);
                matches = matches && gramaturaMatch;
            }

            if (searchParams.ancho) {
                const anchoPattern = new RegExp(`ancho\\s+${searchParams.ancho}\\s*cm`, 'i');
                const anchoMatch = anchoPattern.test(desc);
                matches = matches && anchoMatch;
            }

            if (searchParams.metros) {
                const metrosPatterns = [
                    new RegExp(`rollo\\s+de\\s+${searchParams.metros}\\s*metros?`, 'i'),
                    new RegExp(`rollo\\s+${searchParams.metros}\\s*metros?`, 'i'),
                    new RegExp(`${searchParams.metros}\\s*metros?\\s+rollo`, 'i'),
                    new RegExp(`${searchParams.metros}\\s*metros?(?!.*\\d)`, 'i'),
                    new RegExp(`${searchParams.metros}\\s*m(?!etros)\\b`, 'i'),
                    new RegExp(`${searchParams.metros}\\s*mts?\\b`, 'i'),
                    new RegExp(`de\\s+${searchParams.metros}\\s*metros?`, 'i'),
                    new RegExp(`x\\s+${searchParams.metros}\\s*metros?`, 'i')
                ];

                const metrosMatch = metrosPatterns.some(pattern => pattern.test(desc));
                matches = matches && metrosMatch;
            }

            return matches;
        }

        function applyFilters() {
            const searchTerm = document.getElementById('searchProducts').value.toLowerCase().trim();
            const selectedType = document.getElementById('filterByType').value;
            const specificSearchInput = document.getElementById('specificSearchInput').value.trim();
            const specificSearchParams = parseSpecificSearch(specificSearchInput);

            document.querySelectorAll('.producto-item').forEach(item => {
                item.querySelector('.product-selection-card').classList.remove('highlight-match');
            });

            document.querySelectorAll('.producto-item').forEach(item => {
                const itemType = item.getAttribute('data-tipo');
                const searchData = item.getAttribute('data-search');
                const descripcion = item.getAttribute('data-descripcion');

                let shouldShow = true;
                const typeMatch = (selectedType === "" || itemType === selectedType);
                if (specificSearchParams && PRODUCTOS_ESPECIFICOS.includes(itemType.toUpperCase())) {
                    const specificMatch = matchesSpecificSearch(descripcion, specificSearchParams);
                    if (specificMatch) {
                        item.querySelector('.product-selection-card').classList.add('highlight-match');
                    }
                    shouldShow = typeMatch && specificMatch;
                } else if (specificSearchParams) {
                    shouldShow = false;
                } else {
                    const searchMatch = (searchTerm === "" || searchData.includes(searchTerm));
                    shouldShow = typeMatch && searchMatch;
                }

                item.style.display = shouldShow ? 'block' : 'none';
            });
            document.querySelectorAll('.tipo-section').forEach(section => {
                const visibleItems = section.querySelectorAll('.producto-item:not([style*="display: none"])');
                section.style.display = visibleItems.length > 0 ? 'block' : 'none';
            });

            updateSelectAllCheckboxes();
        }
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchProducts');
            const clearSearch = document.getElementById('clearSearch');
            const filterTypeSelect = document.getElementById('filterByType');
            const specificSearchInput = document.getElementById('specificSearchInput');
            const clearSpecificSearch = document.getElementById('clearSpecificSearch');
            const toggleSpecificSearch = document.getElementById('toggleSpecificSearch');
            const specificSearchContent = document.getElementById('specificSearchContent');
            const toggleIcon = document.getElementById('toggleIcon');
            toggleSpecificSearch.addEventListener('click', function() {
                isSpecificSearchActive = !isSpecificSearchActive;
                specificSearchContent.classList.toggle('active');
                toggleIcon.classList.toggle('fa-chevron-down');
                toggleIcon.classList.toggle('fa-chevron-up');

                if (isSpecificSearchActive) {
                    specificSearchInput.focus();
                } else {
                    specificSearchInput.value = '';
                    applyFilters();
                }
            });
            let searchTimeout;

            function debounceSearch() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(applyFilters, 300);
            }

            searchInput.addEventListener('input', debounceSearch);
            specificSearchInput.addEventListener('input', debounceSearch);
            filterTypeSelect.addEventListener('change', applyFilters);

            clearSearch.addEventListener('click', function() {
                searchInput.value = '';
                applyFilters();
                searchInput.focus();
            });

            clearSpecificSearch.addEventListener('click', function() {
                specificSearchInput.value = '';
                applyFilters();
                specificSearchInput.focus();
            });

            document.getElementById('selectAllProducts').addEventListener('click', function() {
                const visibleCheckboxes = document.querySelectorAll('.producto-item:not([style*="display: none"]) .product-checkbox:not([disabled])');
                const allChecked = visibleCheckboxes.length > 0 && Array.from(visibleCheckboxes).every(cb => cb.checked);

                visibleCheckboxes.forEach(checkbox => {
                    if (checkbox.checked !== !allChecked) {
                        checkbox.checked = !allChecked;
                        updateSelection(checkbox);
                    }
                });
            });

            document.getElementById('clearAllSelections').addEventListener('click', function() {
                const selectionArray = Array.from(productosSeleccionados);
                selectionArray.forEach(productId => {
                    const checkbox = document.querySelector(`.product-checkbox[data-product-id="${productId}"]`);
                    if (checkbox && checkbox.checked) {
                        checkbox.checked = false;
                        updateSelection(checkbox);
                    }
                });
            });

            document.getElementById('addSelectedProducts').addEventListener('click', function() {
                if (productosSeleccionados.size === 0) {
                    alert('No hay productos seleccionados');
                    return;
                }

                if (isLoading) return;
                isLoading = true;
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Agregando...';

                try {
                    const productosParaAgregar = [];
                    productosSeleccionados.forEach(productId => {
                        const dataScript = document.querySelector(`.product-data-${productId}`);
                        if (dataScript) {
                            const productData = JSON.parse(dataScript.textContent);
                            productosParaAgregar.push(productData);
                        }
                    });

                    if (window.opener && typeof window.opener.agregarProductosSeleccionados === 'function') {
                        window.opener.agregarProductosSeleccionados(productosParaAgregar);
                        window.close();
                    } else {
                        throw new Error('No se pudo comunicar con la ventana principal');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error: ' + error.message);

                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-plus me-2"></i>Agregar Seleccionados (<span id="contador-btn">' + productosSeleccionados.size + '</span>)';
                } finally {
                    isLoading = false;
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    cerrarModal();
                } else if (e.ctrlKey && e.key === 'Enter') {
                    document.getElementById('addSelectedProducts').click();
                } else if (e.ctrlKey && e.key === 'a') {
                    e.preventDefault();
                    document.getElementById('selectAllProducts').click();
                } else if (e.ctrlKey && e.key === 'f') {
                    e.preventDefault();
                    if (isSpecificSearchActive) {
                        specificSearchInput.focus();
                    } else {
                        searchInput.focus();
                    }
                }
            });

            searchInput.focus();
        });

        function cerrarModal() {
            if (productosSeleccionados.size > 0) {
                if (confirm('¿Está seguro de que desea salir? Se perderán las selecciones realizadas.')) {
                    window.close();
                }
            } else {
                window.close();
            }
        }

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('product-checkbox')) {
                e.stopPropagation();
            }
        });

        window.addEventListener('error', function(e) {
            console.error('Error en la página:', e.error);
        });
    </script>
</body>

</html>