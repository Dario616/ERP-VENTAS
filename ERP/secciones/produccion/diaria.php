<?php
include "../../auth/verificar_sesion.php";
include "../../config/conexionBD.php";
requerirRol(['1', '2']);
requerirLogin();
$filtro_cliente = isset($_GET['cliente']) ? trim($_GET['cliente']) : '';
$filtro_tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
$filtro_estado = isset($_GET['estado']) ? trim($_GET['estado']) : 'en stock';

$usuario_actual = obtenerUsuarioActual();
$es_admi = $usuario_actual['rol'] == '1';

function calcularPesoTeorico($gramatura, $metragem, $largura, $bobinas_pacote)
{
    if (!$gramatura || !$metragem || !$largura || !$bobinas_pacote) {
        return 0;
    }
    return ($gramatura * $metragem * $largura / 1000.0) * $bobinas_pacote;
}

function clasificarPeso($peso_real, $peso_teorico)
{
    if ($peso_teorico == 0) return ['categoria' => 'Sin datos', 'clase' => 'sin-datos'];

    $diferencia_porcentual = (($peso_real - $peso_teorico) / $peso_teorico) * 100;

    if ($peso_real <= $peso_teorico && $peso_real > ($peso_teorico * 0.979)) {
        return ['categoria' => 'DENTRO DE LA MEDIA 2%', 'clase' => 'dentro-media'];
    } elseif ($peso_real > $peso_teorico && $peso_real <= ($peso_teorico * 1.005)) {
        return ['categoria' => 'Material Pesado rango de 0.5%', 'clase' => 'pesado-05'];
    } elseif ($peso_real < ($peso_teorico * 0.979) && $peso_real < ($peso_teorico * 0.96)) {
        return ['categoria' => 'Material Liviano rango 3%', 'clase' => 'liviano-3'];
    } elseif ($peso_real < ($peso_teorico * 0.979) && $peso_real >= ($peso_teorico * 0.96)) {
        return ['categoria' => 'Material Liviano rango de 4%', 'clase' => 'liviano-4'];
    } elseif ($peso_real < ($peso_teorico * 0.96)) {
        return ['categoria' => 'Material muy liviano rango menor de 4.1%', 'clase' => 'muy-liviano'];
    } elseif ($peso_real > ($peso_teorico * 1.01)) {
        return ['categoria' => 'Material Pesado 1% arriba', 'clase' => 'pesado-1'];
    }

    return ['categoria' => 'Fuera de rango', 'clase' => 'fuera-rango'];
}

try {
    $fecha_actual = date('Y-m-d');
    $sql_base = "SELECT id, peso_bruto, peso_liquido, fecha_hora_producida, estado, 
                        numero_item, nombre_producto, tipo_producto, id_orden_produccion,
                        tara, metragem, largura, gramatura, bobinas_pacote, cliente,
                        id_venta, usuario
                 FROM sist_prod_stock 
                 WHERE 1=1";

    $params = [];

    if ($es_admi) {
        $sql_base .= " AND DATE(fecha_hora_producida) = :fecha_actual 
                       AND estado = 'en stock'";
        $params[':fecha_actual'] = $fecha_actual;
    } else {
        $sql_base .= " AND DATE(fecha_hora_producida) = :fecha_actual 
                       AND estado = 'en stock' 
                       AND usuario = :usuario_actual";
        $params[':fecha_actual'] = $fecha_actual;
        $params[':usuario_actual'] = $usuario_actual['usuario'];
    }

    if (!empty($filtro_cliente)) {
        $sql_base .= " AND LOWER(cliente) LIKE LOWER(:cliente)";
        $params[':cliente'] = '%' . $filtro_cliente . '%';
    }

    if (!empty($filtro_tipo)) {
        $sql_base .= " AND LOWER(tipo_producto) LIKE LOWER(:tipo)";
        $params[':tipo'] = '%' . $filtro_tipo . '%';
    }

    if (!empty($filtro_estado) && $filtro_estado !== 'en stock') {
        $sql_base .= " AND estado = :estado";
        $params[':estado'] = $filtro_estado;
    }

    $sql_base .= " ORDER BY id DESC";

    $stmt = $conexion->prepare($sql_base);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_items = count($stock_items);
    $peso_total = array_sum(array_column($stock_items, 'peso_liquido'));
} catch (PDOException $e) {
    $error = "Error al consultar la base de datos: " . $e->getMessage();
    $stock_items = [];
    $total_items = 0;
    $peso_total = 0;
}
$breadcrumb_items = ['Produccion Diaria'];
$item_urls = [];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>America TNT - Produccion del Día</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="<?php echo $url_base; ?>utils/icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/produccion/utils/diaria.css">
</head>

<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="main-container">
        <div class="container-fluid">
            <div id="seleccion-info" class="seleccion-info" style="display: none;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong><span id="contador-seleccion">0</span> items seleccionados</strong>
                        - Peso total: <strong><span id="peso-total-seleccion">0.00</span> kg</strong>
                    </div>
                    <button class="btn btn-sm btn-outline-light" onclick="limpiarSeleccion()">
                        <i class="fas fa-times me-1"></i>Limpiar
                    </button>
                </div>
            </div>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-custom">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php elseif (empty($stock_items)): ?>
                <div class="no-orders">
                    <i class="fas fa-inbox fa-4x mb-3 d-block"></i>
                    <h4>No se encontraron items</h4>
                    <p class="mb-0">No hay items en stock que coincidan con los filtros seleccionados</p>
                </div>
            <?php else: ?>
                <?php foreach ($stock_items as $item): ?>
                    <?php
                    $peso_teorico = calcularPesoTeorico(
                        $item['gramatura'],
                        $item['metragem'],
                        $item['largura'],
                        $item['bobinas_pacote']
                    );
                    $clasificacion = clasificarPeso($item['peso_liquido'], $peso_teorico);
                    ?>
                    <div class="stock-card" onclick="toggleSelection(this, <?php echo $item['id']; ?>, <?php echo $item['peso_liquido']; ?>)">
                        <div class="item-header">
                            <div class="d-flex align-items-center gap-3">
                                <div class="item-numero">
                                    <i class="fas fa-hashtag me-1"></i>
                                    Item <?php echo $item['numero_item']; ?>
                                </div>
                                <div class="parametro-badge <?php echo $clasificacion['clase']; ?>">
                                    <?php echo $clasificacion['categoria']; ?>
                                </div>
                            </div>
                            <div class="text-muted">
                                <small>
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($item['fecha_hora_producida'])); ?>
                                </small>
                            </div>
                        </div>

                        <div class="item-info">
                            <div class="info-item">
                                <i class="fas fa-cog info-icon"></i>
                                <div>
                                    <div class="info-label">Tipo</div>
                                    <div class="info-value"><?php echo htmlspecialchars($item['tipo_producto'] ?: 'N/A'); ?></div>
                                </div>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-tag info-icon"></i>
                                <div>
                                    <div class="info-label">Etiqueta</div>
                                    <div class="info-value"><?php echo htmlspecialchars($item['id'] ?: 'N/A'); ?></div>
                                </div>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-weight info-icon"></i>
                                <div>
                                    <div class="info-label">Peso Líquido</div>
                                    <div class="info-value"><?php echo number_format($item['peso_liquido'], 2); ?> kg</div>
                                </div>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-balance-scale info-icon"></i>
                                <div>
                                    <div class="info-label">Peso Bruto</div>
                                    <div class="info-value"><?php echo number_format($item['peso_bruto'], 2); ?> kg</div>
                                </div>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-ruler info-icon"></i>
                                <div>
                                    <div class="info-label">Medidas</div>
                                    <div class="info-value"><?php echo $item['gramatura']; ?>g - <?php echo $item['metragem']; ?>m - <?php echo $item['largura']; ?>mm</div>
                                </div>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-cubes info-icon"></i>
                                <div>
                                    <div class="info-label">Bobinas/Paquete</div>
                                    <div class="info-value"><?php echo $item['bobinas_pacote'] ?: 'N/A'; ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Comparación de peso -->
                        <?php if ($peso_teorico > 0): ?>
                            <div class="peso-comparison">
                                <div class="peso-item">
                                    <strong>Peso Teórico:</strong> <?php echo number_format($peso_teorico, 2); ?> kg
                                </div>
                                <div class="peso-item">
                                    <strong>Diferencia:</strong> <?php echo number_format($item['peso_liquido'] - $peso_teorico, 2); ?> kg
                                    (<?php echo number_format((($item['peso_liquido'] - $peso_teorico) / $peso_teorico) * 100, 1); ?>%)
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let itemsSeleccionados = new Set();
        let pesoSeleccionado = 0;

        function toggleSelection(card, itemId, pesoLiquido) {
            const isSelected = itemsSeleccionados.has(itemId);

            if (isSelected) {
                // Deseleccionar
                itemsSeleccionados.delete(itemId);
                pesoSeleccionado -= parseFloat(pesoLiquido);
                card.classList.remove('selected');
            } else {
                // Seleccionar
                itemsSeleccionados.add(itemId);
                pesoSeleccionado += parseFloat(pesoLiquido);
                card.classList.add('selected');
            }

            actualizarEstadisticas();
        }

        function actualizarEstadisticas() {
            const cantidadSeleccionados = itemsSeleccionados.size;
            const infoSeleccion = document.getElementById('seleccion-info');
            const mainContainer = document.querySelector('.main-container');

            // Mostrar/ocultar información de selección
            if (cantidadSeleccionados > 0) {
                infoSeleccion.style.display = 'block';
                mainContainer.classList.add('with-floating-summary');

                // Actualizar los valores
                document.getElementById('contador-seleccion').textContent = cantidadSeleccionados;
                document.getElementById('peso-total-seleccion').textContent = pesoSeleccionado.toFixed(2);

                // Efecto de entrada suave
                setTimeout(() => {
                    infoSeleccion.style.opacity = '1';
                }, 10);

            } else {
                // Efecto de salida suave
                infoSeleccion.style.opacity = '0';
                mainContainer.classList.remove('with-floating-summary');

                setTimeout(() => {
                    infoSeleccion.style.display = 'none';
                }, 300);
            }
        }

        function limpiarSeleccion() {
            // Limpiar todas las selecciones
            document.querySelectorAll('.stock-card.selected').forEach(card => {
                card.classList.remove('selected');
            });

            itemsSeleccionados.clear();
            pesoSeleccionado = 0;
            actualizarEstadisticas();

            // Mostrar notificación de limpieza
            mostrarNotificacion('Selección limpiada', 'info');
        }

        // Función para mostrar notificaciones temporales
        function mostrarNotificacion(mensaje, tipo = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${tipo === 'info' ? 'rgba(59, 130, 246, 0.9)' : 'rgba(34, 197, 94, 0.9)'};
                color: white;
                padding: 0.8rem 1.2rem;
                border-radius: 10px;
                font-size: 0.8rem;
                z-index: 1100;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
                animation: notificationSlide 3s ease;
                backdrop-filter: blur(10px);
            `;
            notification.innerHTML = `<i class="fas fa-${tipo === 'info' ? 'info-circle' : 'check-circle'} me-2"></i>${mensaje}`;

            // Agregar animación CSS si no existe
            if (!document.getElementById('notification-styles')) {
                const style = document.createElement('style');
                style.id = 'notification-styles';
                style.textContent = `
                    @keyframes notificationSlide {
                        0% { opacity: 0; transform: translateX(100%); }
                        15%, 85% { opacity: 1; transform: translateX(0); }
                        100% { opacity: 0; transform: translateX(100%); }
                    }
                `;
                document.head.appendChild(style);
            }

            document.body.appendChild(notification);

            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
        }

        // Atajos de teclado mejorados
        document.addEventListener('keydown', function(e) {
            // Escape para limpiar selección
            if (e.key === 'Escape') {
                if (itemsSeleccionados.size > 0) {
                    limpiarSeleccion();
                }
            }

            // F5 para actualizar
            if (e.key === 'F5') {
                e.preventDefault();
                mostrarNotificacion('Actualizando página...', 'info');
                setTimeout(() => location.reload(), 500);
            }

            // Ctrl+A para seleccionar todos los items visibles
            if (e.ctrlKey && e.key === 'a') {
                e.preventDefault();
                const cards = document.querySelectorAll('.stock-card:not(.selected)');
                cards.forEach(card => {
                    card.click();
                });
                if (cards.length > 0) {
                    mostrarNotificacion(`${cards.length} items adicionales seleccionados`, 'success');
                }
            }

            // Ctrl+D para deseleccionar todos
            if (e.ctrlKey && e.key === 'd') {
                e.preventDefault();
                limpiarSeleccion();
            }
        });

        // Auto-actualizar mejorado
        setInterval(function() {
            const indicator = document.createElement('div');
            indicator.style.cssText = `
                position: fixed; 
                top: ${itemsSeleccionados.size > 0 ? '150px' : '80px'}; 
                right: 20px; 
                background: rgba(59, 130, 246, 0.9); 
                color: white; 
                padding: 0.8rem 1.2rem; 
                border-radius: 20px; 
                font-size: 0.8rem; 
                z-index: 1000;
                animation: fadeInOut 2s ease;
                backdrop-filter: blur(10px);
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            `;
            indicator.innerHTML = '<i class="fas fa-sync fa-spin me-2"></i>Actualizando stock...';

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

        }, 180000); // 3 minutos

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            console.log('📦 Sistema de Stock del Día America TNT cargado correctamente');
            console.log('🎯 Sumatoria flotante activada');
            console.log('⌨️  Atajos disponibles:');
            console.log('   • ESC: Limpiar selección');
            console.log('   • F5: Actualizar página');
            console.log('   • Ctrl+A: Seleccionar todos');
            console.log('   • Ctrl+D: Deseleccionar todos');
            console.log(`👤 Usuario: <?php echo $usuario_actual['nombre']; ?> (${<?php echo $es_admi ? "'Supervisor'" : "'Operario'"; ?>})`);
            console.log(`📊 Items mostrados: <?php echo $total_items; ?> total`);
        });
    </script>
</body>

</html>