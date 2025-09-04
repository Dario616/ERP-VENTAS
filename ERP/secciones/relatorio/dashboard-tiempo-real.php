<?php
include "../../config/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();

// Configurar zona horaria
date_default_timezone_set('America/Asuncion');

// Manejar peticiones AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {
            case 'test_session':
                echo json_encode([
                    'authenticated' => estaLogueado(), // Usar la función del sistema
                    'message' => 'Sesión verificada',
                    'debug' => [
                        'session_id' => session_id(),
                        'loggedin' => $_SESSION['loggedin'] ?? null,
                        'usuario_id' => $_SESSION['id'] ?? null, // Corregido: usar 'id' no 'usuario_id'
                        'nombre' => $_SESSION['nombre'] ?? null,
                        'usuario' => $_SESSION['usuario'] ?? null,
                        'rol' => $_SESSION['rol'] ?? null,
                        'timestamp' => date('Y-m-d H:i:s'),
                        'session_vars' => array_keys($_SESSION) // Para debug
                    ]
                ]);
                exit;

            case 'get_metrics':
                // Métricas en tiempo real - CORREGIDO: Sin conversión (timestamp without time zone ya en hora local)
                $timezone_py = 'America/Asuncion'; // Mantenemos variable por compatibilidad pero no la usamos

                // Métricas del día actual - CORREGIDO: Sin conversión de zona horaria
                $sql = "SELECT 
                            COUNT(*) as items_hoy,
                            COALESCE(SUM(peso_liquido::numeric), 0) as peso_hoy,
                            COUNT(DISTINCT id_orden_produccion) as ordenes_hoy,
                            CASE 
                                WHEN EXTRACT(HOUR FROM NOW()) > 0 
                                THEN COUNT(*) / EXTRACT(HOUR FROM NOW())
                                ELSE 0 
                            END as promedio_hora
                        FROM public.sist_prod_stock 
                        WHERE DATE(fecha_hora_producida) = CURRENT_DATE";

                $stmt = $conexion->prepare($sql);
                $stmt->execute();
                $metricas_hoy = $stmt->fetch(PDO::FETCH_ASSOC);

                // Métricas de ayer - CORREGIDO: Sin conversión de zona horaria
                $sql = "SELECT 
                            COUNT(*) as items_ayer,
                            COALESCE(SUM(peso_liquido::numeric), 0) as peso_ayer,
                            COUNT(DISTINCT id_orden_produccion) as ordenes_ayer
                        FROM public.sist_prod_stock 
                        WHERE DATE(fecha_hora_producida) = CURRENT_DATE - INTERVAL '1 day'";

                $stmt = $conexion->prepare($sql);
                $stmt->execute();
                $metricas_ayer = $stmt->fetch(PDO::FETCH_ASSOC);

                // ✅ PRODUCCIÓN POR HORA - CORREGIDA (SIN CONVERSIÓN DE ZONA HORARIA)
                // Los datos ya están en hora local de Paraguay (timestamp without time zone)
                $sql = "
                    WITH horas_del_dia AS (
                        SELECT generate_series(0, 23) as hora
                    ),
                    produccion_hoy AS (
                        SELECT 
                            EXTRACT(HOUR FROM fecha_hora_producida)::integer as hora,
                            COUNT(*) as items
                        FROM public.sist_prod_stock 
                        WHERE DATE(fecha_hora_producida) = CURRENT_DATE
                        GROUP BY EXTRACT(HOUR FROM fecha_hora_producida)
                    )
                    SELECT 
                        h.hora,
                        COALESCE(p.items, 0) as items
                    FROM horas_del_dia h
                    LEFT JOIN produccion_hoy p ON h.hora = p.hora
                    WHERE h.hora <= EXTRACT(HOUR FROM NOW())
                    ORDER BY h.hora";

                $stmt = $conexion->prepare($sql);
                $stmt->execute();
                $produccion_horaria = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Distribución por tipo - CORREGIDO: Sin conversión de zona horaria
                $sql = "SELECT 
                            tipo_producto as tipo,
                            COUNT(*) as cantidad
                        FROM public.sist_prod_stock 
                        WHERE DATE(fecha_hora_producida) = CURRENT_DATE
                        GROUP BY tipo_producto
                        ORDER BY cantidad DESC";

                $stmt = $conexion->prepare($sql);
                $stmt->execute();
                $distribucion_tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Actividad reciente - CORREGIDO: Sin conversión de zona horaria
                $sql = "SELECT 
                            s.numero_item,
                            s.tipo_producto,
                            s.id_orden_produccion as orden,
                            s.peso_liquido,
                            TO_CHAR(s.fecha_hora_producida, 'HH24:MI') as hora,
                            TO_CHAR(s.fecha_hora_producida, 'DD/MM') as fecha
                        FROM public.sist_prod_stock s
                        WHERE DATE(s.fecha_hora_producida) = CURRENT_DATE
                        ORDER BY s.fecha_hora_producida DESC
                        LIMIT 10";

                $stmt = $conexion->prepare($sql);
                $stmt->execute();
                $actividad = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Clientes activos - CORREGIDO: Sin conversión de zona horaria
                $sql = "SELECT 
                            o.cliente,
                            COUNT(*) as items,
                            COUNT(DISTINCT s.id_orden_produccion) as ordenes
                        FROM public.sist_prod_stock s
                        LEFT JOIN public.sist_ventas_orden_produccion op ON s.id_orden_produccion = op.id
                        LEFT JOIN public.sist_ventas_presupuesto o ON op.id_venta = o.id
                        WHERE DATE(s.fecha_hora_producida) = CURRENT_DATE
                        GROUP BY o.cliente
                        ORDER BY items DESC
                        LIMIT 5";

                $stmt = $conexion->prepare($sql);
                $stmt->execute();
                $clientes_activos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // ✅ CONSULTA DE DEBUG PARA VERIFICAR TIMESTAMPS (SIN CONVERSIÓN)
                $sql_debug = "
                    SELECT 
                        fecha_hora_producida as timestamp_original,
                        EXTRACT(HOUR FROM fecha_hora_producida) as hora_real,
                        DATE(fecha_hora_producida) as fecha_real,
                        CURRENT_DATE as fecha_actual,
                        EXTRACT(HOUR FROM NOW()) as hora_actual,
                        COUNT(*) OVER() as total_registros_hoy
                    FROM public.sist_prod_stock 
                    WHERE DATE(fecha_hora_producida) = CURRENT_DATE
                    ORDER BY fecha_hora_producida DESC
                    LIMIT 10";

                $stmt_debug = $conexion->prepare($sql_debug);
                $stmt_debug->execute();
                $debug_info = $stmt_debug->fetchAll(PDO::FETCH_ASSOC);

                // Calcular tendencias
                function calcularTendencia($actual, $anterior)
                {
                    if ($anterior == 0) {
                        return $actual > 0 ? "🚀 Nuevo récord!" : "📊 Sin datos previos";
                    }
                    $diferencia = $actual - $anterior;
                    $porcentaje = round(($diferencia / $anterior) * 100, 1);
                    if ($diferencia > 0) {
                        return "📈 +" . abs($porcentaje) . "% vs ayer";
                    } elseif ($diferencia < 0) {
                        return "📉 -" . abs($porcentaje) . "% vs ayer";
                    } else {
                        return "➡️ Igual que ayer";
                    }
                }

                // Formatear actividad - FIXED: Round in PHP instead of SQL
                foreach ($actividad as &$item) {
                    $item['peso_liquido'] = round(floatval($item['peso_liquido']), 1) . ' kg';
                }

                $response = [
                    // DATOS CORREGIDOS: Sin conversión de zona horaria (timestamp without time zone ya está en hora local)
                    'items_hoy' => (int)$metricas_hoy['items_hoy'],
                    'peso_hoy' => round($metricas_hoy['peso_hoy'], 1),
                    'ordenes_hoy' => (int)$metricas_hoy['ordenes_hoy'],
                    'promedio_hora' => round($metricas_hoy['promedio_hora'], 1),
                    'trend_items' => calcularTendencia($metricas_hoy['items_hoy'], $metricas_ayer['items_ayer']),
                    'trend_peso' => calcularTendencia($metricas_hoy['peso_hoy'], $metricas_ayer['peso_ayer']),
                    'trend_ordenes' => calcularTendencia($metricas_hoy['ordenes_hoy'], $metricas_ayer['ordenes_ayer']),
                    'trend_promedio' => $metricas_hoy['promedio_hora'] > 0 ?
                        "📈 " . round($metricas_hoy['promedio_hora'], 1) . " items/hora" : "⏳ Iniciando día",
                    'produccion_horaria' => $produccion_horaria, // ✅ DATOS CORREGIDOS (SIN CONVERSIÓN)
                    'distribucion_tipos' => $distribucion_tipos,
                    'actividad_reciente' => $actividad,
                    'clientes_activos' => $clientes_activos,
                    'debug_timezone' => $debug_info, // ✅ INFORMACIÓN DE DEBUG (SIN CONVERSIÓN)
                    'timestamp' => date('Y-m-d H:i:s'),
                    'status' => 'online'
                ];

                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit;

            default:
                echo json_encode(['error' => 'Acción no válida']);
                exit;
        }
    } catch (Exception $e) {
        // Enhanced error logging
        error_log("Dashboard Error: " . $e->getMessage());
        echo json_encode([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        exit;
    }
}

// Dashboard siempre muestra datos sin conversión de zona horaria (timestamp without time zone ya en hora local)
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard en Tiempo Real - Sistema de Producción (TIMESTAMP SIN ZONA HORARIA CORREGIDO)</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="icon" href="../../utils/icon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link rel="stylesheet" href="<?php echo $url_base; ?>secciones/relatorio/utils/dashboard-tiempo-real.css">

</head>

<body class="live-dashboard">
    <!-- Indicador de actualización -->
    <div id="refreshIndicator" class="refresh-indicator">
        <i class="fas fa-sync-alt"></i>
    </div>

    <!-- Error Alert -->
    <div id="errorAlert" class="error-alert" style="display: none;">
        <strong>⚠️ Error del Sistema:</strong>
        <div id="errorMessage"></div>
        <button class="btn btn-sm btn-outline-light mt-2" onclick="location.reload()">
            <i class="fas fa-refresh"></i> Recargar Página
        </button>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../index.php">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard en Vivo
            </a>
            <div class="navbar-nav ms-auto">
                <span id="statusIndicator" class="status-indicator status-warning"></span>
                <span id="statusText" class="text-light me-3">Verificando...</span>
                <a class="nav-link" href="../relatorio/main.php">
                    <i class="fas fa-arrow-left me-1"></i>Volver a Reportes
                </a>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container-fluid mt-4">

        <!-- Reloj en tiempo real -->
        <div class="time-display" id="currentTime">
            <?php echo date('H:i:s'); ?>
        </div>

        <!-- Métricas principales en tiempo real -->
        <div class="row mb-4" id="liveMetrics">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="live-metric">
                    <div class="live-number" id="itemsHoy">--</div>
                    <div class="live-label">Items Hoy</div>
                    <div class="live-trend" id="trendItems">Cargando...</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="live-metric">
                    <div class="live-number" id="pesoHoy">--</div>
                    <div class="live-label">Kg Producidos</div>
                    <div class="live-trend" id="trendPeso">Cargando...</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="live-metric">
                    <div class="live-number" id="ordenesHoy">--</div>
                    <div class="live-label">Órdenes Activas</div>
                    <div class="live-trend" id="trendOrdenes">Cargando...</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="live-metric">
                    <div class="live-number" id="promedioHora">--</div>
                    <div class="live-label">Items/Hora</div>
                    <div class="live-trend" id="trendPromedio">Cargando...</div>
                </div>
            </div>
        </div>

        <!-- Gráficos en tiempo real -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="glass-card">
                    <h5><i class="fas fa-chart-line me-2"></i>Producción por Hora - Hoy</h5>
                    <canvas id="chartHoraActual" height="100"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="glass-card">
                    <h5><i class="fas fa-chart-pie me-2"></i>Distribución por Tipo</h5>
                    <canvas id="chartTipoHoy" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript para dashboard en tiempo real -->
    <script>
        // Variables globales
        let chartHora, chartTipo;
        let updateInterval;
        const colors = {
            primary: '#3182ce',
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
            accent: '#ed8936'
        };

        // Mostrar errores en la UI
        function mostrarError(mensaje, detalles = null) {
            const errorAlert = document.getElementById('errorAlert');
            const errorMessage = document.getElementById('errorMessage');

            errorMessage.innerHTML = `
                <div>${mensaje}</div>
                ${detalles ? `<details class="mt-2"><summary>Detalles técnicos</summary><pre class="small mt-2">${detalles}</pre></details>` : ''}
            `;

            errorAlert.style.display = 'block';
            console.error('🚨 Error mostrado al usuario:', mensaje);
        }

        // Ocultar errores
        function ocultarError() {
            document.getElementById('errorAlert').style.display = 'none';
        }

        // Actualizar reloj
        function actualizarReloj() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }

        // Función para animar números
        function animateNumber(elementId, targetValue) {
            const element = document.getElementById(elementId);
            const currentValue = parseFloat(element.textContent.replace(/[^\d.-]/g, '')) || 0;
            const target = parseFloat(targetValue) || 0;

            if (currentValue === target) return;

            const difference = target - currentValue;
            const steps = 10;
            const stepValue = difference / steps;
            let currentStep = 0;

            const timer = setInterval(() => {
                currentStep++;
                const newValue = currentValue + (stepValue * currentStep);

                if (currentStep >= steps) {
                    element.textContent = target % 1 === 0 ? target.toString() : target.toFixed(1);
                    clearInterval(timer);
                } else {
                    element.textContent = newValue % 1 === 0 ? Math.round(newValue).toString() : newValue.toFixed(1);
                }
            }, 50);
        }

        // Función de diagnóstico mejorada
        function diagnosticarSesion() {
            console.log('🔍 Iniciando diagnóstico completo...');

            fetch('?action=test_session', {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    console.log('🌐 Respuesta HTTP:', response.status, response.statusText);
                    return response.json();
                })
                .then(data => {
                    console.log('🐛 Diagnóstico completo:', data);

                    const info = `
DIAGNÓSTICO DEL SISTEMA
========================
✅ Estado de Sesión: ${data.authenticated ? '✅ ACTIVA' : '❌ INACTIVA'}
🆔 Session ID: ${data.debug.session_id || 'Sin ID'}
🔐 Loggedin: ${data.debug.loggedin ? 'true' : 'false'}
👤 Usuario ID: ${data.debug.usuario_id || 'No autenticado'}
👤 Nombre: ${data.debug.nombre || 'No disponible'}
👤 Usuario: ${data.debug.usuario || 'No disponible'}
🎭 Rol: ${data.debug.rol || 'Sin rol'}
⏰ Timestamp: ${data.debug.timestamp}
📋 Variables de sesión: ${data.debug.session_vars ? data.debug.session_vars.join(', ') : 'No disponibles'}

${data.authenticated ? '🎉 Sistema funcionando correctamente' : '⚠️ Problema de autenticación detectado'}
                `;

                    alert(info);

                    // Si no está autenticado, intentar recargar
                    if (!data.authenticated) {
                        if (confirm('¿Quieres intentar recargar la página para corregir el problema?')) {
                            location.reload();
                        }
                    }
                })
                .catch(error => {
                    console.error('❌ Error crítico en diagnóstico:', error);
                    mostrarError('Error crítico del sistema', error.message);
                });
        }

        // ✅ FUNCIÓN MEJORADA PARA ACTUALIZAR GRÁFICO DE HORAS
        function actualizarGraficoHora(datos) {
            console.log('📊 Actualizando gráfico con datos:', datos);

            // Validar que los datos sean correctos
            if (!Array.isArray(datos) || datos.length === 0) {
                console.warn('⚠️ Datos de producción horaria vacíos o inválidos');
                datos = []; // Asegurar que sea un array vacío
            }

            // Preparar labels y datos
            const labels = datos.map(d => {
                const hora = parseInt(d.hora);
                return hora.toString().padStart(2, '0') + ':00';
            });

            const values = datos.map(d => parseInt(d.items) || 0);

            console.log('📈 Labels del gráfico:', labels);
            console.log('📈 Valores del gráfico:', values);

            if (chartHora) {
                // Actualizar gráfico existente
                chartHora.data.labels = labels;
                chartHora.data.datasets[0].data = values;
                chartHora.update('none'); // Sin animación para updates en tiempo real
            } else {
                // Crear gráfico nuevo
                inicializarGraficoHora(datos);
            }
        }

        function inicializarGraficoHora(datos) {
            const ctx = document.getElementById('chartHoraActual').getContext('2d');

            // Preparar datos iniciales
            const labels = datos.map(d => {
                const hora = parseInt(d.hora);
                return hora.toString().padStart(2, '0') + ':00';
            });

            const values = datos.map(d => parseInt(d.items) || 0);

            console.log('🎯 Inicializando gráfico con:', {
                labels,
                values
            });

            chartHora = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Items Producidos',
                        data: values,
                        borderColor: colors.accent,
                        backgroundColor: 'rgba(237, 137, 54, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 6,
                        pointBackgroundColor: colors.accent,
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false,
                            labels: {
                                color: 'rgba(255,255,255,0.8)',
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    return 'Hora: ' + context[0].label;
                                },
                                label: function(context) {
                                    return 'Items producidos: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255,255,255,0.1)',
                                drawBorder: false
                            },
                            ticks: {
                                color: 'rgba(255,255,255,0.8)',
                                stepSize: 1, // Mostrar solo números enteros
                                callback: function(value) {
                                    return Number.isInteger(value) ? value : '';
                                }
                            },
                            title: {
                                display: true,
                                text: 'Cantidad de Items',
                                color: 'rgba(255,255,255,0.8)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: 'rgba(255,255,255,0.8)',
                                maxTicksLimit: 12 // Limitar para evitar sobrecarga visual
                            },
                            title: {
                                display: true,
                                text: 'Hora del Día',
                                color: 'rgba(255,255,255,0.8)'
                            }
                        }
                    },
                    animation: {
                        duration: 500,
                        easing: 'easeInOutQuart'
                    },
                    // Configuración adicional para debugging
                    onHover: function(event, elements) {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            const hora = labels[index];
                            const valor = values[index];
                            console.log(`🎯 Hover en hora ${hora}: ${valor} items`);
                        }
                    }
                }
            });
        }

        // ✅ FUNCIÓN MEJORADA PARA ACTUALIZAR MÉTRICAS
        function actualizarMetricas() {
            const indicator = document.getElementById('refreshIndicator');
            indicator.classList.add('updating');

            // Ocultar errores previos
            ocultarError();

            fetch('?action=get_metrics', {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    console.log('📊 Respuesta de métricas:', response.status);

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        console.error('❌ Error del servidor:', data);
                        mostrarError('Error en el servidor de métricas', JSON.stringify(data, null, 2));
                        return;
                    }

                    console.log('✅ Métricas recibidas:', data);

                    // 🐛 DEBUG: Información de zona horaria
                    if (data.debug_timezone) {
                        console.log('🕐 Debug zona horaria:', data.debug_timezone);
                    }

                    // Actualizar números principales
                    animateNumber('itemsHoy', data.items_hoy || 0);
                    animateNumber('pesoHoy', data.peso_hoy ? parseFloat(data.peso_hoy).toFixed(0) : 0);
                    animateNumber('ordenesHoy', data.ordenes_hoy || 0);
                    animateNumber('promedioHora', data.promedio_hora ? parseFloat(data.promedio_hora).toFixed(1) : 0);

                    // Actualizar tendencias
                    document.getElementById('trendItems').textContent = data.trend_items || 'Sin datos';
                    document.getElementById('trendPeso').textContent = data.trend_peso || 'Sin datos';
                    document.getElementById('trendOrdenes').textContent = data.trend_ordenes || 'Sin datos';
                    document.getElementById('trendPromedio').textContent = data.trend_promedio || 'Sin datos';

                    // ✅ ACTUALIZACIÓN CRÍTICA: Gráfico de horas con validación
                    if (data.produccion_horaria && Array.isArray(data.produccion_horaria)) {
                        console.log('📈 Datos de producción horaria recibidos:', data.produccion_horaria);
                        actualizarGraficoHora(data.produccion_horaria);
                    } else {
                        console.warn('⚠️ Datos de producción horaria inválidos:', data.produccion_horaria);
                        // Crear datos vacíos si no hay información
                        const horaActual = new Date().getHours();
                        const datosVacios = [];
                        for (let h = 0; h <= horaActual; h++) {
                            datosVacios.push({
                                hora: h,
                                items: 0
                            });
                        }
                        actualizarGraficoHora(datosVacios);
                    }

                    // Actualizar gráfico de tipos
                    if (data.distribucion_tipos) {
                        actualizarGraficoTipo(data.distribucion_tipos);
                    }


                    // Actualizar status
                    const statusIndicator = document.getElementById('statusIndicator');
                    const statusText = document.getElementById('statusText');
                    statusIndicator.className = 'status-indicator status-online';
                    statusText.textContent = 'Sistema Activo';

                    console.log('✅ Dashboard actualizado exitosamente:', new Date().toLocaleTimeString());
                })
                .catch(error => {
                    console.error('❌ Error al actualizar métricas:', error);

                    // Actualizar status de error
                    const statusIndicator = document.getElementById('statusIndicator');
                    const statusText = document.getElementById('statusText');

                    // Manejar errores específicos
                    if (error.message.includes('401')) {
                        statusIndicator.className = 'status-indicator status-offline';
                        statusText.textContent = 'Sesión Expirada';
                        mostrarError('Tu sesión ha expirado', 'Necesitas iniciar sesión nuevamente');

                        setTimeout(() => {
                            if (confirm('Tu sesión ha expirado. ¿Quieres ir al login?')) {
                                window.location.href = '../../login.php';
                            }
                        }, 3000);
                    } else if (error.message.includes('500')) {
                        statusIndicator.className = 'status-indicator status-warning';
                        statusText.textContent = 'Error del Servidor';
                        mostrarError('Error interno del servidor', error.message);
                    } else {
                        statusIndicator.className = 'status-indicator status-warning';
                        statusText.textContent = 'Error de Conexión';
                        mostrarError('Problema de conectividad', error.message);
                    }
                })
                .finally(() => {
                    indicator.classList.remove('updating');
                });
        }

        // Resto de funciones sin cambios...
        function actualizarGraficoTipo(datos) {
            if (chartTipo) {
                chartTipo.data.labels = datos.map(d => d.tipo);
                chartTipo.data.datasets[0].data = datos.map(d => d.cantidad);
                chartTipo.update('none');
            } else {
                inicializarGraficoTipo(datos);
            }
        }

        function inicializarGraficoTipo(datos) {
            const ctx = document.getElementById('chartTipoHoy').getContext('2d');
            chartTipo = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: datos.map(d => d.tipo),
                    datasets: [{
                        data: datos.map(d => d.cantidad),
                        backgroundColor: [colors.success, colors.primary, colors.warning, colors.danger],
                        borderWidth: 3,
                        borderColor: 'rgba(255,255,255,0.8)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: 'rgba(255,255,255,0.8)',
                                padding: 15,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        }

        // Inicialización mejorada - TIMESTAMP WITHOUT TIME ZONE CORREGIDO
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Dashboard en tiempo real iniciado - TIMESTAMP SIN ZONA HORARIA CORREGIDO DEFINITIVAMENTE');

            // Actualizar reloj inmediatamente y luego cada segundo
            actualizarReloj();
            setInterval(actualizarReloj, 1000);

            // Verificar sesión y luego iniciar métricas
            verificarSesionInicial()
                .then(sesionValida => {
                    if (sesionValida) {
                        console.log('✅ Sesión verificada, iniciando dashboard...');

                        // Actualizar métricas inmediatamente y luego cada 30 segundos
                        actualizarMetricas();
                        updateInterval = setInterval(actualizarMetricas, 30000);

                    } else {
                        console.warn('⚠️ Problema con la sesión detectado al inicio');
                        mostrarError('Problema de autenticación', 'Tu sesión no está activa o ha expirado');
                    }
                })
                .catch(error => {
                    console.error('❌ Error durante inicialización:', error);
                    mostrarError('Error durante la inicialización', error.message);
                });

            // Mensaje de bienvenida
            setTimeout(() => {
                console.log('⚡ Dashboard completamente cargado y funcionando');
                console.log('🎯 CORREGIDO: Timestamps sin zona horaria - NO aplicamos conversión');
                console.log('📊 Los datos ahora aparecen en las horas correctas: 07:33 -> hora 7, 00:10 -> hora 0');
            }, 2000);
        });

        // Verificar sesión inicial - Sin cambios
        function verificarSesionInicial() {
            return fetch('?action=test_session', {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    console.log('🔐 Verificación inicial de sesión:', data.authenticated ? '✅ OK' : '❌ FALLO');

                    // Actualizar indicador visual
                    const statusIndicator = document.getElementById('statusIndicator');
                    const statusText = document.getElementById('statusText');

                    if (data.authenticated) {
                        statusIndicator.className = 'status-indicator status-online';
                        statusText.textContent = 'Sistema Activo';
                    } else {
                        statusIndicator.className = 'status-indicator status-offline';
                        statusText.textContent = 'Sesión Inválida';
                        console.log('🐛 Debug de sesión:', data.debug);
                    }

                    return data.authenticated;
                })
                .catch(error => {
                    console.error('❌ Error verificando sesión inicial:', error);

                    const statusIndicator = document.getElementById('statusIndicator');
                    const statusText = document.getElementById('statusText');
                    statusIndicator.className = 'status-indicator status-warning';
                    statusText.textContent = 'Error de Conexión';

                    return false;
                });
        }

        // Manejar visibilidad de la página
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                console.log('⏸️ Dashboard pausado (pestaña no visible)');
                clearInterval(updateInterval);
            } else {
                console.log('▶️ Dashboard reanudado');
                actualizarMetricas();
                updateInterval = setInterval(actualizarMetricas, 30000);
            }
        });

        // Exponer funciones globalmente
        window.diagnosticarSesion = diagnosticarSesion;
    </script>

</body>

</html>