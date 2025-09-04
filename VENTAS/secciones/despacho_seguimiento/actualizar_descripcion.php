<?php
require_once __DIR__ . '/../../config/database/conexionBD.php';
require_once __DIR__ . '/../../auth/verificar_sesion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido'
    ]);
    exit;
}

$numeroExpedicion = trim($_POST['numero_expedicion'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$usuario = $_SESSION['nombre'] ?? 'Usuario';

if (empty($numeroExpedicion)) {
    echo json_encode([
        'success' => false,
        'error' => 'Número de expedición requerido'
    ]);
    exit;
}

if (strlen($descripcion) > 1000) {
    echo json_encode([
        'success' => false,
        'error' => 'La descripción no puede exceder 1000 caracteres'
    ]);
    exit;
}

try {
    $sqlVerificar = "SELECT id FROM sist_expediciones WHERE numero_expedicion = :numero_expedicion";
    $stmtVerificar = $conexion->prepare($sqlVerificar);
    $stmtVerificar->bindValue(':numero_expedicion', $numeroExpedicion, PDO::PARAM_STR);
    $stmtVerificar->execute();

    if (!$stmtVerificar->fetch()) {
        echo json_encode([
            'success' => false,
            'error' => 'Expedición no encontrada'
        ]);
        exit;
    }
    $sql = "UPDATE sist_expediciones 
            SET descripcion = :descripcion,
                usuario_despacho = :usuario,
                fecha_despacho = CASE 
                    WHEN fecha_despacho IS NULL THEN CURRENT_TIMESTAMP 
                    ELSE fecha_despacho 
                END
            WHERE numero_expedicion = :numero_expedicion";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
    $stmt->bindValue(':usuario', $usuario, PDO::PARAM_STR);
    $stmt->bindValue(':numero_expedicion', $numeroExpedicion, PDO::PARAM_STR);

    $resultado = $stmt->execute();

    if ($resultado && $stmt->rowCount() > 0) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        error_log("DESPACHO_SEGUIMIENTO - Usuario: {$usuario} | IP: {$ip} | Acción: Actualizar descripción expedición | Detalles: {$numeroExpedicion}");

        echo json_encode([
            'success' => true,
            'mensaje' => 'Descripción actualizada correctamente'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No se pudo actualizar la descripción'
        ]);
    }
} catch (Exception $e) {
    error_log("Error actualizando descripción expedición: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}
