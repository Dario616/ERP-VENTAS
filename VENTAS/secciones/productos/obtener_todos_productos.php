<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";
requerirLogin();

if (file_exists("controllers/ProductoController.php")) {
    include "controllers/ProductoController.php";
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error: No se pudo cargar el controlador de productos']);
    exit();
}

header('Content-Type: application/json');

try {
    $controller = new ProductoController($conexion, $url_base);
    $_GET['action'] = 'obtener_productos';
    if ($controller->handleApiRequest()) {
        exit();
    } else {
        $productos_por_tipo = $controller->obtenerProductosParaCatalogo();
        $productos = [];
        foreach ($productos_por_tipo as $tipo => $productos_tipo) {
            foreach ($productos_tipo as $producto) {
                $productos[] = $producto;
            }
        }

        echo json_encode([
            'success' => true,
            'productos' => $productos
        ]);
    }
} catch (Exception $e) {
    error_log("Error en obtener_todos_productos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al consultar productos: ' . $e->getMessage()
    ]);
}
