<?php
include "../../../config/database/conexionBD.php";
header('Content-Type: application/json');

try {
    $busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
    $results = [];

    if ($busqueda !== '') {
        $query = "SELECT id, nombre, descripcion, telefono, email, direccion, ruc, nro
                  FROM public.sist_ventas_clientes 
                  WHERE LOWER(nombre) LIKE LOWER(:busqueda) 
                     OR LOWER(ruc) LIKE LOWER(:busqueda)
                  ORDER BY nombre
                  LIMIT 15";

        $stmt = $conexion->prepare($query);
        $terminoBusqueda = '%' . $busqueda . '%';
        $stmt->bindParam(':busqueda', $terminoBusqueda, PDO::PARAM_STR);
        $stmt->execute();

        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($clientes as $cliente) {
            $texto = $cliente['nombre'];
            if (!empty($cliente['ruc'])) {
                $texto .= ' (RUC: ' . $cliente['ruc'] . ')';
            }

            $results[] = [
                'id' => $cliente['id'],
                'text' => $texto,
                'nombre' => $cliente['nombre'],
                'descripcion' => $cliente['descripcion'],
                'ruc' => $cliente['ruc'],
                'telefono' => $cliente['telefono'],
                'direccion' => $cliente['direccion'],
                'email' => $cliente['email'],
                'nro' => $cliente['nro'],
            ];
        }
    }

    echo json_encode(['results' => $results]);
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Error en la base de datos']);
}
