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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID no válido']);
    exit();
}

try {
    $controller = new ProductoController($conexion, $url_base);
    $_GET['action'] = 'obtener_unidades';

    if ($controller->handleApiRequest()) {
        exit();
    } else {
        $id_producto = $_GET['id'];
        $unidades = $controller->obtenerUnidadesMedidaProducto($id_producto);

        echo json_encode([
            'success' => true,
            'unidades' => $unidades
        ]);
    }
} catch (Exception $e) {
    error_log("Error en obtener_unidades.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al consultar unidades: ' . $e->getMessage()
    ]);
}
