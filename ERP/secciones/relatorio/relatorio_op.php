<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();

if (file_exists("controllers/ordenesController.php")) {
    include "controllers/ordenesController.php";
} else {
    die("Error: No se pudo cargar el controlador de órdenes.");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($url_base) || empty($url_base)) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = dirname(dirname($script));
    $url_base = $protocol . '://' . $host . $path . '/';
}

$controller = new OrdenesController($conexion, $url_base);

if ($controller->handleApiRequest()) {
    exit();
}

$datosVista = $controller->obtenerDatosVista();
$titulo = $datosVista['titulo'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titulo); ?></title>

    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $url_base; ?>assets/favicon-32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/relatorio/utils/relatorio_op.css">


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
                        <a class="nav-link" href="<?php echo $url_base; ?>secciones/relatorio/main.php">
                            <i class="fas fa-file-alt"></i>
                            Reportes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="fas fa-clipboard-list me-1"></i>Órdenes de Producción
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="hero-title">
                        <i class="fas fa-clipboard-list me-3"></i>Órdenes de Producción
                    </h1>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <div class="container-fluid">
            <!-- Filtros -->
            <div class="filtros-container">
                <div class="filtros-titulo">
                    <i class="fas fa-filter text-success"></i>
                    Filtros de Consulta
                </div>
                <form id="filtrosForm">
                    <div class="row">
                        <div class="col-md-2 mb-3">
                            <label for="numeroOrden" class="form-label">
                                <i class="fas fa-hashtag me-1"></i>N° Orden
                            </label>
                            <input type="number" id="numeroOrden" name="numero_orden" class="form-control" placeholder="Buscar orden..." min="1" title="Busca una orden específica por su número">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="fechaInicio" class="form-label">
                                <i class="fas fa-calendar me-1"></i>Fecha Inicio
                            </label>
                            <input type="date" id="fechaInicio" name="fecha_inicio" class="form-control" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="fechaFin" class="form-label">
                                <i class="fas fa-calendar me-1"></i>Fecha Fin
                            </label>
                            <input type="date" id="fechaFin" name="fecha_fin" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="estado" class="form-label">
                                <i class="fas fa-info-circle me-1"></i>Estado
                            </label>
                            <select id="estado" name="estado" class="form-select">
                                <option value="">Todos los estados</option>
                                <option value="Pendiente">Pendiente</option>
                                <option value="En Proceso">En Proceso</option>
                                <option value="Completado">Completado</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="cliente" class="form-label">
                                <i class="fas fa-user me-1"></i>Cliente
                            </label>
                            <input type="text" id="cliente" name="cliente" class="form-control" placeholder="Buscar cliente...">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Consultar
                            </button>
                            <button type="button" class="btn btn-secondary ms-2" onclick="limpiarFiltros()">
                                <i class="fas fa-eraser me-2"></i>Limpiar
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Lista de Órdenes -->
            <div id="ordenesContainer">
                <div class="sin-datos">
                    <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                    <h5>Cargando órdenes...</h5>
                    <p>Por favor espere mientras se cargan las órdenes de producción</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_URL = window.location.href;

        // Estado global para vista de producción
        let vistasProduccion = {}; // {idOrden: 'individual'|'agrupada'}

        // Cargar órdenes al inicio
        document.addEventListener('DOMContentLoaded', function() {
            cargarOrdenes();

            // Búsqueda rápida por número de orden
            const numeroOrdenInput = document.getElementById('numeroOrden');
            numeroOrdenInput.addEventListener('input', function(e) {
                const valor = e.target.value.trim();

                // Resaltar el campo si tiene valor
                if (valor) {
                    numeroOrdenInput.classList.add('filtro-orden-destacado');
                } else {
                    numeroOrdenInput.classList.remove('filtro-orden-destacado');
                }

                // Si se presiona Enter o se escribe un número completo, buscar automáticamente
                if (valor && valor.length >= 1) {
                    // Opcional: búsqueda automática después de 500ms sin escribir
                    clearTimeout(window.busquedaTimeout);
                    window.busquedaTimeout = setTimeout(() => {
                        cargarOrdenes();
                    }, 500);
                }
            });

            // Búsqueda inmediata al presionar Enter en el campo de número de orden
            numeroOrdenInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    cargarOrdenes();
                }
            });
        });

        // Manejar formulario
        document.getElementById('filtrosForm').addEventListener('submit', function(e) {
            e.preventDefault();
            cargarOrdenes();
        });

        function cargarOrdenes() {
            const formData = new FormData(document.getElementById('filtrosForm'));
            const params = new URLSearchParams(formData);
            params.append('action', 'obtener_ordenes');

            fetch(API_URL + '?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarOrdenes(data.ordenes);
                    } else {
                        console.error('Error:', data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function mostrarOrdenes(ordenes) {
            const container = document.getElementById('ordenesContainer');
            const numeroOrdenBuscado = document.getElementById('numeroOrden').value.trim();

            if (ordenes.length === 0) {
                const mensajeNoEncontrado = numeroOrdenBuscado ?
                    `No se encontró la orden #${numeroOrdenBuscado}` :
                    'No se encontraron órdenes para los filtros seleccionados';

                container.innerHTML = `
                    <div class="sin-datos">
                        <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                        <h5>${mensajeNoEncontrado}</h5>
                        <p>Intenta ajustar los filtros de búsqueda</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = ordenes.map(orden => `
                <div class="orden-card ${numeroOrdenBuscado && orden.id == numeroOrdenBuscado ? 'orden-encontrada' : ''}">
                    <div class="orden-header" onclick="toggleProductos(${orden.id})">
                        <div class="orden-info">
                            <div>
                                <div class="orden-titulo">
                                    Orden #${orden.id} - ${orden.cliente || 'Sin cliente'}
                                    ${numeroOrdenBuscado && orden.id == numeroOrdenBuscado ? '<i class="fas fa-bullseye text-success ms-2" title="Orden encontrada"></i>' : ''}
                                </div>
                                <div class="orden-meta">
                                    <i class="fas fa-calendar"></i> ${formatearFecha(orden.fecha_orden)} | 
                                    <i class="fas fa-box"></i> ${orden.total_productos} producto(s) planificado(s)
                                    ${orden.items_producidos ? ` | <i class="fas fa-industry text-success"></i> ${orden.items_producidos} item(s) producido(s)` : ''}
                                    ${orden.peso_total_producido ? ` | <i class="fas fa-weight-hanging text-info"></i> ${orden.peso_total_producido}kg producidos` : ''}
                                </div>
                            </div>
                            <div>
                                <span class="estado-badge estado-${orden.estado.toLowerCase().replace(/\s+/g, '')}">
                                    ${orden.estado}
                                </span>
                                ${orden.items_producidos > 0 ? '<i class="fas fa-check-circle text-success ms-1" title="Tiene producción"></i>' : '<i class="fas fa-clock text-warning ms-1" title="Sin producción"></i>'}
                                <i class="fas fa-chevron-down ms-2" id="chevron-${orden.id}"></i>
                            </div>
                        </div>
                        ${orden.observaciones ? `<div class="mt-2"><small><i class="fas fa-comment"></i> ${orden.observaciones}</small></div>` : ''}
                    </div>
                    <div class="productos-container" id="productos-${orden.id}">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin"></i> Cargando datos de la orden...
                        </div>
                    </div>
                </div>
            `).join('');

            // Si se encontró una orden específica, expandirla automáticamente
            if (numeroOrdenBuscado && ordenes.length === 1 && ordenes[0].id == numeroOrdenBuscado) {
                setTimeout(() => {
                    toggleProductos(ordenes[0].id);
                }, 300);
            }
        }

        function toggleProductos(idOrden) {
            const container = document.getElementById(`productos-${idOrden}`);
            const chevron = document.getElementById(`chevron-${idOrden}`);

            if (container.style.display === 'none' || container.style.display === '') {
                cargarDatosOrden(idOrden);
                container.style.display = 'block';
                chevron.classList.remove('fa-chevron-down');
                chevron.classList.add('fa-chevron-up');
            } else {
                container.style.display = 'none';
                chevron.classList.remove('fa-chevron-up');
                chevron.classList.add('fa-chevron-down');
            }
        }

        function cargarDatosOrden(idOrden) {
            const container = document.getElementById(`productos-${idOrden}`);

            // Inicializar vista como individual si no existe
            if (!vistasProduccion[idOrden]) {
                vistasProduccion[idOrden] = 'individual';
            }

            // Crear estructura de pestañas
            container.innerHTML = `
                <div class="tabs-container">
                    <ul class="tab-nav">
                        <li><button class="tab-btn active" data-tab="planificado-${idOrden}">
                            <i class="fas fa-clipboard-list"></i> Planificado
                        </button></li>
                        <li><button class="tab-btn" data-tab="producido-${idOrden}">
                            <i class="fas fa-industry"></i> Producido
                        </button></li>
                        <li><button class="tab-btn" data-tab="resumen-${idOrden}">
                            <i class="fas fa-chart-bar"></i> Resumen
                        </button></li>
                    </ul>
                </div>
                <div id="planificado-${idOrden}" class="tab-content active">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin"></i> Cargando productos planificados...
                    </div>
                </div>
                <div id="producido-${idOrden}" class="tab-content">
                    <div class="produccion-header">
                        <div class="flex-grow-1">
                            <h6 class="mb-0">
                                <i class="fas fa-industry me-2"></i>Productos Producidos
                            </h6>
                        </div>
                        <div class="vista-toggle">
                            <button class="btn-vista ${vistasProduccion[idOrden] === 'individual' ? 'active' : ''}" 
                                    onclick="cambiarVistaProduccion(${idOrden}, 'individual')">
                                <i class="fas fa-list me-1"></i>Individual
                            </button>
                            <button class="btn-vista ${vistasProduccion[idOrden] === 'agrupada' ? 'active' : ''}" 
                                    onclick="cambiarVistaProduccion(${idOrden}, 'agrupada')">
                                <i class="fas fa-layer-group me-1"></i>Agrupada
                            </button>
                        </div>
                    </div>
                    <div id="contenido-producido-${idOrden}">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin"></i> Cargando producción real...
                        </div>
                    </div>
                </div>
                <div id="resumen-${idOrden}" class="tab-content">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin"></i> Cargando resumen...
                    </div>
                </div>
            `;

            // Agregar event listeners a las pestañas
            container.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    cambiarTab(idOrden, tabId);
                });
            });

            // Cargar datos iniciales
            cargarProductosPlanificados(idOrden);
        }

        function cambiarTab(idOrden, tabId) {
            // Actualizar botones
            document.querySelectorAll(`#productos-${idOrden} .tab-btn`).forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');

            // Mostrar contenido correspondiente
            document.querySelectorAll(`#productos-${idOrden} .tab-content`).forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabId).classList.add('active');

            // Cargar datos según la pestaña
            if (tabId.includes('planificado')) {
                cargarProductosPlanificados(idOrden);
            } else if (tabId.includes('producido')) {
                cargarProduccionSegunVista(idOrden);
            } else if (tabId.includes('resumen')) {
                cargarResumenProduccion(idOrden);
            }
        }

        function cambiarVistaProduccion(idOrden, tipoVista) {
            vistasProduccion[idOrden] = tipoVista;

            // Actualizar botones
            document.querySelectorAll(`#productos-${idOrden} .btn-vista`).forEach(btn => {
                btn.classList.remove('active');
            });

            const btnActivo = document.querySelector(`#productos-${idOrden} .btn-vista[onclick*="'${tipoVista}'"]`);
            if (btnActivo) {
                btnActivo.classList.add('active');
            }

            // Cargar datos según la vista seleccionada
            cargarProduccionSegunVista(idOrden);
        }

        function cargarProduccionSegunVista(idOrden) {
            if (vistasProduccion[idOrden] === 'agrupada') {
                cargarProduccionRealAgrupada(idOrden);
            } else {
                cargarProduccionReal(idOrden);
            }
        }

        function cargarProductosPlanificados(idOrden) {
            const params = new URLSearchParams({
                action: 'obtener_productos_orden',
                id_orden: idOrden
            });

            fetch(API_URL + '?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarProductosPlanificados(idOrden, data.productos);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function cargarProduccionReal(idOrden) {
            const params = new URLSearchParams({
                action: 'obtener_produccion_real',
                id_orden: idOrden
            });

            fetch(API_URL + '?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarProduccionReal(idOrden, data.produccion_real);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function cargarProduccionRealAgrupada(idOrden) {
            const params = new URLSearchParams({
                action: 'obtener_produccion_real_agrupada',
                id_orden: idOrden
            });

            fetch(API_URL + '?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarProduccionRealAgrupada(idOrden, data.produccion_real_agrupada);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function cargarResumenProduccion(idOrden) {
            const params = new URLSearchParams({
                action: 'obtener_resumen_produccion',
                id_orden: idOrden
            });

            fetch(API_URL + '?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarResumenProduccion(idOrden, data.resumen);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function mostrarProductosPlanificados(idOrden, productos) {
            const container = document.getElementById(`planificado-${idOrden}`);

            if (productos.length === 0) {
                container.innerHTML = '<p class="text-muted">No hay productos planificados en esta orden</p>';
                return;
            }

            container.innerHTML = productos.map(producto => `
                <div class="producto-item tipo-${producto.tipo}">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>${producto.nombre || 'Sin nombre'}</strong>
                            <br><span class="badge bg-secondary">${producto.tipo}</span>
                        </div>
                        <div class="col-md-8">
                            ${generarDetallesProducto(producto)}
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function mostrarProduccionReal(idOrden, produccion) {
            const container = document.getElementById(`contenido-producido-${idOrden}`);

            if (produccion.length === 0) {
                container.innerHTML = '<p class="text-muted">Aún no se ha producido nada para esta orden</p>';
                return;
            }

            container.innerHTML = produccion.map(item => `
                <div class="produccion-item">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>${item.nombre_producto}</strong>
                            <br><span class="badge bg-success">${item.tipo_producto}</span>
                            <br><small class="text-muted">Item #${item.numero_item}</small>
                        </div>
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-6">
                                    <small><strong>Peso:</strong> ${item.peso_liquido}kg (${item.peso_bruto}kg bruto)</small><br>
                                    <small><strong>Bobinas:</strong> ${item.bobinas_pacote || 'N/A'}</small><br>
                                    <small><strong>Metragem:</strong> ${item.metragem || 'N/A'}m</small><br>
                                    <small><strong>Etiqueta</strong> ${item.id || 'N/A'}</small>
                                </div>
                                <div class="col-6">
                                    <small><strong>Estado:</strong> <span class="badge bg-info">${item.estado}</span></small><br>
                                    <small><strong>Operador:</strong> ${item.usuario}</small><br>
                                    <small><strong>Producido:</strong> ${formatearFechaHora(item.fecha_hora_producida)}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function mostrarProduccionRealAgrupada(idOrden, produccionAgrupada) {
            const container = document.getElementById(`contenido-producido-${idOrden}`);

            if (produccionAgrupada.length === 0) {
                container.innerHTML = '<p class="text-muted">Aún no se ha producido nada para esta orden</p>';
                return;
            }

            container.innerHTML = produccionAgrupada.map(grupo => `
                <div class="produccion-item-agrupada">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <strong>${grupo.nombre_producto}</strong>
                            <span class="agrupado-badge">${grupo.total_items} items</span>
                            <br><span class="badge bg-primary">${grupo.tipo_producto}</span>
                            <div class="mt-2">
                                <small class="text-muted">
                                    ${grupo.metragem ? `${grupo.metragem}m` : ''} 
                                    ${grupo.largura ? `x ${grupo.largura}cm` : ''} 
                                    ${grupo.gramatura ? `- ${grupo.gramatura}g/m²` : ''}
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detalles-agrupado">
                                <div class="detalle-grupo">
                                    <div class="detalle-titulo">Total Bobinas</div>
                                    <div class="detalle-valor">${grupo.bobinas_pacote_total || 0}</div>
                                </div>
                                <div class="detalle-grupo">
                                    <div class="detalle-titulo">Peso Total</div>
                                    <div class="detalle-valor">${parseFloat(grupo.peso_liquido_total).toFixed(2)}kg</div>
                                </div>
                                <div class="detalle-grupo">
                                    <div class="detalle-titulo">Peso Promedio</div>
                                    <div class="detalle-valor">${parseFloat(grupo.peso_liquido_promedio).toFixed(2)}kg</div>
                                </div>
                                <div class="detalle-grupo">
                                    <div class="detalle-titulo">Eficiencia</div>
                                    <div class="detalle-valor">${(parseFloat(grupo.peso_liquido_total)/parseFloat(grupo.peso_bruto_total)*100).toFixed(1)}%</div>
                                </div>
                            </div>
                            ${grupo.fecha_primera_produccion !== grupo.fecha_ultima_produccion ? `
                                <div class="periodo-produccion">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    Producido desde ${formatearFecha(grupo.fecha_primera_produccion)} hasta ${formatearFecha(grupo.fecha_ultima_produccion)}
                                </div>
                            ` : `
                                <div class="periodo-produccion">
                                    <i class="fas fa-calendar me-1"></i>
                                    Producido el ${formatearFecha(grupo.fecha_primera_produccion)}
                                </div>
                            `}
                            <div class="mt-2">
                                <small class="text-muted">
                                    <strong>Operadores:</strong> ${grupo.operadores}<br>
                                    <strong>Estados:</strong> ${grupo.estados}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function mostrarResumenProduccion(idOrden, resumen) {
            const container = document.getElementById(`resumen-${idOrden}`);

            if (resumen.length === 0) {
                container.innerHTML = '<p class="text-muted">No hay datos de producción para mostrar resumen</p>';
                return;
            }

            // Calcular totales
            const totales = resumen.reduce((acc, item) => {
                acc.items += parseInt(item.items_producidos);
                acc.bobinas += parseInt(item.total_bobinas);
                acc.peso_bruto += parseFloat(item.peso_bruto_total);
                acc.peso_liquido += parseFloat(item.peso_liquido_total);
                return acc;
            }, {
                items: 0,
                bobinas: 0,
                peso_bruto: 0,
                peso_liquido: 0
            });

            container.innerHTML = `
                <div class="resumen-stats">
                    <div class="row">
                        <div class="col-3">
                            <div class="stat-item">
                                <div class="stat-number">${totales.items}</div>
                                <div class="stat-label">Items</div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-item">
                                <div class="stat-number">${totales.bobinas}</div>
                                <div class="stat-label">Bobinas</div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-item">
                                <div class="stat-number">${totales.peso_liquido.toFixed(1)}</div>
                                <div class="stat-label">Peso Líquido (kg)</div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-item">
                                <div class="stat-number">${((totales.peso_liquido/totales.peso_bruto)*100).toFixed(1)}%</div>
                                <div class="stat-label">Eficiencia</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h6>Por Tipo de Producto:</h6>
                ${resumen.map(item => `
                    <div class="produccion-item">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <strong>${item.tipo_producto}</strong>
                            </div>
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-3 text-center">
                                        <small>${item.items_producidos} items</small>
                                    </div>
                                    <div class="col-3 text-center">
                                        <small>${item.total_bobinas} bobinas</small>
                                    </div>
                                    <div class="col-3 text-center">
                                        <small>${parseFloat(item.peso_liquido_total).toFixed(1)}kg</small>
                                    </div>
                                    <div class="col-3 text-center">
                                        <small>${formatearFecha(item.primera_produccion)} - ${formatearFecha(item.ultima_produccion)}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('')}
            `;
        }

        function generarDetallesProducto(producto) {
            let detalles = [];

            if (producto.cantidad_total) detalles.push(`Cantidad: ${producto.cantidad_total}`);
            if (producto.gramatura) detalles.push(`Gramatura: ${producto.gramatura}`);
            if (producto.color) detalles.push(`Color: ${producto.color}`);
            if (producto.largura_metros) detalles.push(`Largura: ${producto.largura_metros}m`);
            if (producto.total_bobinas) detalles.push(`Bobinas: ${producto.total_bobinas}`);
            if (producto.peso_bobina) detalles.push(`Peso/Bobina: ${producto.peso_bobina}kg`);

            return detalles.join(' | ');
        }

        function formatearFecha(fecha) {
            return new Date(fecha).toLocaleDateString('es-PY');
        }

        function formatearFechaHora(fecha) {
            const date = new Date(fecha);
            return date.toLocaleDateString('es-PY') + ' ' + date.toLocaleTimeString('es-PY', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function limpiarFiltros() {
            document.getElementById('filtrosForm').reset();
            document.getElementById('fechaInicio').value = '<?php echo date('Y-m-01'); ?>';
            document.getElementById('fechaFin').value = '<?php echo date('Y-m-d'); ?>';
            document.getElementById('numeroOrden').value = '';
            document.getElementById('numeroOrden').classList.remove('filtro-orden-destacado');
            cargarOrdenes();
        }
    </script>
</body>

</html>