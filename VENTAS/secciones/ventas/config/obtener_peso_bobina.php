<?php
include "../../../config/database/conexionBD.php";

header('Content-Type: application/json');

if (!isset($_GET['id_producto']) || empty($_GET['id_producto'])) {
    echo json_encode(['success' => false, 'error' => 'ID de producto requerido']);
    exit;
}

try {
    $id_producto = intval($_GET['id_producto']);
    
    $query = "SELECT cantidad FROM public.sist_ventas_productos WHERE id = :id";
    $stmt = $conexion->prepare($query);
    $stmt->bindParam(':id', $id_producto, PDO::PARAM_INT);
    $stmt->execute();
    
    $peso_bobina = $stmt->fetchColumn();
    
    if ($peso_bobina !== false && $peso_bobina > 0) {
        echo json_encode([
            'success' => true, 
            'peso_bobina' => floatval($peso_bobina)
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'Peso por bobina no encontrado o es cero'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Error al consultar peso por bobina: ' . $e->getMessage()
    ]);
}
?>