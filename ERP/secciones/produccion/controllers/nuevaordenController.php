<?php
require_once 'repository/nuevaordenRepository.php';
require_once 'services/nuevaordenService.php';

// Establecer la zona horaria de Paraguay/Asunción
date_default_timezone_set('America/Asuncion');

// Configuración de paginación
define('ITEMS_POR_PAGINA', 10);

/**
 * Controller para manejo de órdenes de producción - CON CANTIDAD DIRECTA COMO BOBINAS
 */
class NuevaOrdenController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new NuevaOrdenRepository($conexion);
        $this->service = new NuevaOrdenService($this->repository);
        $this->urlBase = $urlBase;
    }

    /**
     * Maneja las peticiones API
     */
    public function handleApiRequest()
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        header('Content-Type: application/json');

        switch ($_GET['action']) {
            case 'buscar_productos':
                $this->buscarProductos();
                break;

            case 'detalles_producto':
                $this->detallesProducto();
                break;

            case 'obtener_unidades':
                $this->obtenerUnidades();
                break;

            case 'obtener_ordenes':
                $this->obtenerOrdenes();
                break;

            case 'validar_disponibilidad_pdfs':
                $this->validarDisponibilidadPDFs();
                break;

            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

        return true;
    }

    /**
     * API: Buscar productos para autocompletado
     */
    private function buscarProductos()
    {
        $termino = $_GET['q'] ?? '';
        if (strlen($termino) < 2) {
            echo json_encode([]);
            return;
        }

        $productos = $this->repository->obtenerSugerenciasProductos($termino, 15);

        // ⭐ ENRIQUECER PRODUCTOS CON TIPO DETECTADO (INCLUYE PAÑOS Y LAMINADORA) ⭐
        foreach ($productos as &$producto) {
            $producto['tipo_detectado'] = $this->service->detectarTipoProducto(
                $producto['descripcion'],
                $producto['tipo']
            );

            // Log para debug
            error_log("PRODUCTO SUGERENCIA - {$producto['descripcion']} -> Tipo BD: {$producto['tipo']} -> Detectado: {$producto['tipo_detectado']}");
        }

        echo json_encode($productos);
    }

    /**
     * API: Obtener detalles de producto
     */
    private function detallesProducto()
    {
        $descripcion = $_GET['descripcion'] ?? '';
        if (empty($descripcion)) {
            echo json_encode(['encontrado' => false]);
            return;
        }

        $producto = $this->repository->buscarProductoEnBD($descripcion);
        if ($producto) {
            $tipoDetectado = $this->service->detectarTipoProducto($producto['descripcion'], $producto['tipo']);

            echo json_encode([
                'encontrado' => true,
                'producto' => $producto,
                'tipo_detectado' => $tipoDetectado
            ]);

            error_log("PRODUCTO DETALLE - {$producto['descripcion']} -> Tipo BD: {$producto['tipo']} -> Detectado: {$tipoDetectado}");
        } else {
            // ⭐ INCLUSO SIN PRODUCTO EN BD, DETECTAR TIPO PARA MOSTRAR EN PREVIEW ⭐
            $tipoDetectado = $this->service->detectarTipoProducto($descripcion);

            echo json_encode([
                'encontrado' => false,
                'tipo_detectado' => $tipoDetectado
            ]);

            error_log("PRODUCTO NO ENCONTRADO - {$descripcion} -> Tipo detectado: {$tipoDetectado}");
        }
    }

    /**
     * API: Obtener unidades de medida
     */
    private function obtenerUnidades()
    {
        $id_producto = $_GET['id_producto'] ?? null;
        $unidades = $this->repository->obtenerUnidadesMedida($id_producto);
        echo json_encode($unidades);
    }

    /**
     * API: Obtener órdenes de producción
     */
    private function obtenerOrdenes()
    {
        $pagina = intval($_GET['pagina'] ?? 1);
        $limite = intval($_GET['limite'] ?? ITEMS_POR_PAGINA);
        $filtroOrden = $_GET['orden'] ?? null;

        $resultado = $this->repository->obtenerOrdenesProduccion($pagina, $limite, $filtroOrden);

        // ⭐ ENRIQUECER ÓRDENES CON INFORMACIÓN ADICIONAL (INCLUYE PAÑOS Y LAMINADORA) ⭐
        foreach ($resultado['ordenes'] as &$orden) {
            // Asegurar que el tipo esté bien formateado
            $orden['tipo_producto'] = strtoupper(trim($orden['tipo_producto']));

            // Agregar información de URLs de PDF
            $orden['pdf_url'] = $this->service->generarUrlPDF(
                $orden['tipo_producto'],
                $orden['id'],
                $this->urlBase
            );
        }

        echo json_encode($resultado);
    }

    /**
     * Procesar formulario de creación de orden
     */
    public function procesarFormulario()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['crear_orden'])) {
            return false;
        }

        try {
            // Validar datos de entrada
            $this->validarDatosFormulario($_POST);

            $datos = [
                'descripcion' => trim($_POST['descripcion']),
                'cantidad' => floatval($_POST['cantidad']),
                'unidad_medida' => trim($_POST['unidad_medida']),
                'observaciones' => trim($_POST['observaciones'] ?? '')
            ];

            // ⭐ VALIDACIÓN MEJORADA: Verificar producto existe Y coincide con selección ⭐
            $productoBD = $this->repository->buscarProductoEnBD($datos['descripcion']);
            if (!$productoBD) {
                throw new Exception('Solo se pueden crear órdenes con productos existentes en la base de datos.');
            }

            // ⭐ VALIDACIÓN ADICIONAL: Si se envió ID, verificar que coincida ⭐
            if (isset($_POST['id_producto_seleccionado'])) {
                $idSeleccionado = intval($_POST['id_producto_seleccionado']);

                if ($productoBD['id'] != $idSeleccionado) {
                    error_log("ALERTA SEGURIDAD - ID seleccionado: {$idSeleccionado}, ID encontrado: {$productoBD['id']}, Descripción: {$datos['descripcion']}");
                    throw new Exception('Error de validación: El producto seleccionado no coincide con la descripción enviada.');
                }

                // ⭐ VALIDACIÓN EXACTA DE DESCRIPCIÓN ⭐
                if (trim($productoBD['descripcion']) !== trim($datos['descripcion'])) {
                    error_log("ALERTA SEGURIDAD - Descripción modificada. BD: '{$productoBD['descripcion']}', Enviada: '{$datos['descripcion']}'");
                    throw new Exception('Error de validación: La descripción fue modificada después de la selección.');
                }

                error_log("✅ VALIDACIÓN EXITOSA - Producto ID {$idSeleccionado} coincide perfectamente");
            }

            // Crear orden usando el service
            $resultado = $this->service->crearOrdenProduccion($datos);

            // Generar URL del PDF
            $pdfUrl = $this->service->generarUrlPDF(
                $resultado['tipo'],
                $resultado['id_orden'],
                $this->urlBase
            );

            // ⭐ LOG DE ÉXITO CON INFORMACIÓN DE SEGURIDAD ⭐
            error_log("ORDEN CREADA SEGURA - ID: {$resultado['id_orden']}, Tipo: {$resultado['tipo']}, Producto BD ID: {$productoBD['id']}, Descripción: {$datos['descripcion']}");

            // Respuesta exitosa
            echo json_encode([
                'success' => true,
                'pdf_url' => $pdfUrl,
                'id_orden' => $resultado['id_orden'],
                'tipo' => $resultado['tipo'],
                'producto' => $datos['descripcion'],
                'producto_bd_id' => $productoBD['id'], // ⭐ INCLUIR ID PARA CONFIRMACIÓN ⭐
                'cantidad' => $datos['cantidad'],
                'unidad' => $datos['unidad_medida']
            ]);
        } catch (Exception $e) {
            error_log("ERROR CREANDO ORDEN: " . $e->getMessage());
            error_log("Datos recibidos: " . json_encode($_POST));

            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        return true;
    }

    /**
     * Validar datos del formulario
     */
    private function validarDatosFormulario($datos)
    {
        if (empty(trim($datos['descripcion']))) {
            throw new Exception('La descripción del producto es requerida.');
        }

        if (empty($datos['cantidad']) || $datos['cantidad'] <= 0) {
            throw new Exception('La cantidad debe ser mayor a 0.');
        }

        if (empty(trim($datos['unidad_medida']))) {
            throw new Exception('La unidad de medida es requerida.');
        }

        // ⭐ VALIDACIÓN DE LONGITUD MÍNIMA PARA DESCRIPCIÓN ⭐
        if (strlen(trim($datos['descripcion'])) < 5) {
            throw new Exception('La descripción debe tener al menos 5 caracteres.');
        }

        // ⭐ VALIDACIÓN DE CANTIDAD MÁXIMA RAZONABLE ⭐
        if ($datos['cantidad'] > 999999) {
            throw new Exception('La cantidad es demasiado grande.');
        }

        // ⭐ VALIDACIÓN DE CARACTERES PERMITIDOS EN DESCRIPCIÓN ⭐
        if (preg_match('/[<>"\']/', $datos['descripcion'])) {
            throw new Exception('La descripción contiene caracteres no permitidos.');
        }

        // ⭐ VALIDACIÓN ADICIONAL: Si hay ID seleccionado, debe ser numérico válido ⭐
        if (isset($datos['id_producto_seleccionado'])) {
            $idSeleccionado = $datos['id_producto_seleccionado'];
            if (!is_numeric($idSeleccionado) || $idSeleccionado <= 0) {
                throw new Exception('ID de producto seleccionado inválido.');
            }
        }
    }

    public function validarCoincidenciaProducto($descripcionEnviada, $idEsperado = null)
    {
        $productoBD = $this->repository->buscarProductoEnBD($descripcionEnviada);

        if (!$productoBD) {
            return [
                'valido' => false,
                'error' => 'Producto no encontrado en la base de datos'
            ];
        }

        if ($idEsperado && $productoBD['id'] != $idEsperado) {
            return [
                'valido' => false,
                'error' => 'El ID del producto no coincide con la descripción',
                'id_encontrado' => $productoBD['id'],
                'id_esperado' => $idEsperado
            ];
        }

        if (trim($productoBD['descripcion']) !== trim($descripcionEnviada)) {
            return [
                'valido' => false,
                'error' => 'La descripción no coincide exactamente',
                'descripcion_bd' => $productoBD['descripcion'],
                'descripcion_enviada' => $descripcionEnviada
            ];
        }

        return [
            'valido' => true,
            'producto' => $productoBD
        ];
    }



    /**
     * Obtener datos para la vista
     */
    public function obtenerDatosVista()
    {
        $paginaActual = intval($_GET['pagina'] ?? 1);

        return [
            'unidades_medida' => $this->repository->obtenerUnidadesMedida(),
            'ordenes_data' => $this->repository->obtenerOrdenesProduccion($paginaActual, ITEMS_POR_PAGINA),
            'items_por_pagina' => ITEMS_POR_PAGINA,
            'tipos_soportados' => ['TNT', 'SPUNLACE', 'LAMINADORA', 'TOALLITAS', 'PAÑOS'] // ⭐ INCLUIR LAMINADORA Y PAÑOS ⭐
        ];
    }

    /**
     * ⭐ ACTUALIZADA: Validar disponibilidad de PDFs (INCLUYE PAÑOS Y LAMINADORA) ⭐
     */
    public function validarDisponibilidadPDFs()
    {
        $tipos = ['TNT', 'SPUNLACE', 'LAMINADORA', 'TOALLITAS', 'PAÑOS'];
        $disponibilidad = [];

        foreach ($tipos as $tipo) {
            $url = $this->service->generarUrlPDF($tipo, 1, $this->urlBase);
            $archivo = str_replace($this->urlBase, '', $url);
            $disponibilidad[$tipo] = file_exists($archivo);
        }

        echo json_encode($disponibilidad);
    }

    /**
     * ⭐ NUEVO: Obtener información específica de paños para debugging ⭐
     */
    public function obtenerInfoPanos()
    {
        $productos = $this->repository->buscarProductosPanos();

        header('Content-Type: application/json');
        echo json_encode([
            'productos_panos' => $productos,
            'total' => count($productos)
        ]);
    }

    /**
     * ⭐ NUEVO: Validar estructura de paños ⭐
     */
    public function validarEstructuraPanos($descripcion)
    {
        $info = $this->service->extraerEspecificacionesPanos($descripcion);

        $esValido = !empty($info['gramatura']) && !empty($info['ancho']) && !empty($info['largo']);

        return [
            'es_valido' => $esValido,
            'especificaciones' => $info,
            'sugerencias' => $esValido ? [] : [
                'Formato esperado: "Paño XXcm x XXcm, XXg/m², Color"',
                'Ejemplo: "Paño 28cm x 40cm, 35g/m², Blanco"'
            ]
        ];
    }

    /**
     * ⭐ CORREGIDO: obtenerInfoLaminadora() - LAMINADORA usa tabla TNT ⭐
     */
    public function obtenerInfoLaminadora()
    {
        $productos = $this->repository->buscarProductosLaminadora();

        header('Content-Type: application/json');
        echo json_encode([
            'productos_laminadora' => $productos,
            'total' => count($productos),
            'nota' => 'LAMINADORA se guarda en la misma tabla que TNT (sist_ventas_op_tnt)'
        ]);
    }

    /**
     * ⭐ CORREGIDO: Formatear cantidad con unidad correcta - PAÑOS sin conversión ⭐
     */
    public function formatearCantidadConUnidadCorrecta($cantidad, $unidad)
    {
        // Para unidades como "unidades", "cajas" no usar decimales
        if (in_array(strtolower($unidad), ['unidades', 'cajas', 'piezas'])) {
            return number_format((float)$cantidad, 0, ',', '.') . ' ' . $unidad;
        } else {
            // Para kg y otros, usar 2 decimales
            return number_format((float)$cantidad, 2, ',', '.') . ' ' . $unidad;
        }
    }

    /**
     * ⭐ CORREGIDO: Determinar si un producto es de tipo unidades usando la BD ⭐
     */
    public function esProductoEnUnidades($tipoProducto, $unidadMedidaBD = null)
    {
        // ✅ USAR LA UNIDAD DE LA BASE DE DATOS SI ESTÁ DISPONIBLE
        if ($unidadMedidaBD) {
            $unidadLower = strtolower($unidadMedidaBD);
            return in_array($unidadLower, ['unidades', 'cajas', 'piezas']);
        }

        // Fallback al tipo de producto si no hay unidad de BD
        $tipoLower = strtolower($tipoProducto);
        return in_array($tipoLower, ['toallitas', 'paños']);
    }

    /**
     * ⭐ CORREGIDO: Obtener unidad de medida usando la BD como fuente principal ⭐
     */
    public function obtenerUnidadMedidaProducto($tipoProducto, $unidadMedidaBD = null)
    {
        // ✅ PRIORIZAR LA UNIDAD DE LA BASE DE DATOS
        if (!empty($unidadMedidaBD)) {
            return $unidadMedidaBD;
        }

        // Fallback basado en tipo de producto (solo si no hay unidad en BD)
        $tipoLower = strtolower($tipoProducto);

        if ($tipoLower === 'toallitas') {
            return 'cajas';
        } elseif ($tipoLower === 'paños') {
            return 'unidades';
        } else {
            return 'kg'; // TNT, Spunlace, Laminadora
        }
    }
}

// Instanciar el controller
$controller = new NuevaOrdenController($conexion, $url_base);

// Manejar peticiones API
if ($controller->handleApiRequest()) {
    exit();
}

// Procesar formulario
if ($controller->procesarFormulario()) {
    exit();
}

// Obtener datos para la vista
$datosVista = $controller->obtenerDatosVista();
extract($datosVista);
