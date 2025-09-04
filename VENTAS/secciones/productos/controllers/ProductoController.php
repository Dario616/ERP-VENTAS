<?php
require_once __DIR__ . '/../repository/ProductoRepository.php';
require_once __DIR__ . '/../services/ProductoService.php';

date_default_timezone_set('America/Asuncion');


class ProductoController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new ProductoRepository($conexion);
        $this->service = new ProductoService($this->repository);
        $this->urlBase = $urlBase;
    }
    public function handleApiRequest()
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        header('Content-Type: application/json');

        switch ($_GET['action']) {
            case 'obtener_productos':
                $this->obtenerProductosApi();
                break;

            case 'obtener_unidades':
                $this->obtenerUnidadesApi();
                break;

            case 'eliminar_producto':
                $this->eliminarProductoApi();
                break;

            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

        return true;
    }

    private function obtenerProductosApi()
    {
        try {
            $productos = $this->service->obtenerProductosParaCatalogo();

            echo json_encode([
                'success' => true,
                'productos' => $productos
            ]);
        } catch (Exception $e) {
            error_log("Error en API obtener productos: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerUnidadesApi()
    {
        $id = $_GET['id'] ?? null;

        if (!$id || !is_numeric($id)) {
            echo json_encode([
                'success' => false,
                'error' => 'ID no válido'
            ]);
            return;
        }

        try {
            $unidades = $this->service->obtenerUnidadesMedidaProducto($id);

            echo json_encode([
                'success' => true,
                'unidades' => $unidades
            ]);
        } catch (Exception $e) {
            error_log("Error en API obtener unidades: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }


    private function eliminarProductoApi()
    {
        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'error' => 'ID no proporcionado'
            ]);
            return;
        }

        try {
            $resultado = $this->service->eliminarProducto($id);
            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en API eliminar producto: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    public function procesarRegistro()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['error' => 'Método no permitido'];
        }

        try {
            $datos = $this->obtenerDatosFormulario();
            $unidades_medida = $_POST['unidades_medida'] ?? [];

            if (isset($_FILES['imagen'])) {
                try {
                    $imagen_data = $this->service->procesarImagen($_FILES['imagen']);
                    if ($imagen_data) {
                        $datos['imagen_data'] = $imagen_data;
                    }
                } catch (Exception $e) {
                    return [
                        'errores' => [$e->getMessage()],
                        'datos' => $datos
                    ];
                }
            }

            $resultado = $this->service->crearProducto($datos, $unidades_medida);

            if ($resultado['success']) {
                header("Location: " . $this->urlBase . "secciones/productos/index.php?mensaje=" . urlencode($resultado['mensaje']));
                exit();
            } else {
                return [
                    'errores' => $resultado['errores'],
                    'datos' => $datos
                ];
            }
        } catch (Exception $e) {
            error_log("Error procesando registro: " . $e->getMessage());
            return [
                'error' => 'Error interno del servidor',
                'datos' => $this->obtenerDatosFormulario()
            ];
        }
    }

    public function procesarEdicion($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['error' => 'Método no permitido'];
        }

        try {
            $datos = $this->obtenerDatosFormulario();
            $unidades_medida = $_POST['unidades_medida'] ?? [];

            $eliminar_imagen = isset($_POST['eliminar_imagen']);

            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                try {
                    $imagen_data = $this->service->procesarImagen($_FILES['imagen']);
                    $datos['nueva_imagen'] = true;
                    $datos['imagen_data'] = $imagen_data;
                } catch (Exception $e) {
                    return [
                        'errores' => [$e->getMessage()],
                        'datos' => $datos
                    ];
                }
            } elseif ($eliminar_imagen) {
                $datos['eliminar_imagen'] = true;
            }

            $resultado = $this->service->actualizarProducto($id, $datos, $unidades_medida);

            if ($resultado['success']) {
                header("Location: " . $this->urlBase . "secciones/productos/index.php?mensaje=" . urlencode($resultado['mensaje']));
                exit();
            } else {
                return [
                    'errores' => $resultado['errores'],
                    'datos' => $datos
                ];
            }
        } catch (Exception $e) {
            error_log("Error procesando edición: " . $e->getMessage());
            return [
                'error' => 'Error interno del servidor',
                'datos' => $this->obtenerDatosFormulario()
            ];
        }
    }

    public function obtenerProductoParaEdicion($id)
    {
        try {
            return $this->service->obtenerProductoPorId($id);
        } catch (Exception $e) {
            error_log("Error obteniendo producto: " . $e->getMessage());
            throw new Exception('Producto no encontrado');
        }
    }

    public function obtenerProductoParaVer($id)
    {
        try {
            return $this->service->obtenerProductoPorId($id);
        } catch (Exception $e) {
            error_log("Error obteniendo producto: " . $e->getMessage());
            throw new Exception('Producto no encontrado');
        }
    }

    public function procesarEliminacion($id)
    {
        try {
            $resultado = $this->service->eliminarProducto($id);

            if ($resultado['success']) {
                return ['mensaje' => $resultado['mensaje']];
            } else {
                return ['error' => $resultado['error']];
            }
        } catch (Exception $e) {
            error_log("Error procesando eliminación: " . $e->getMessage());
            return ['error' => 'Error interno del servidor'];
        }
    }

    public function obtenerListaProductos($filtros = [])
    {
        try {
            return $this->service->obtenerProductos($filtros);
        } catch (Exception $e) {
            error_log("Error obteniendo lista de productos: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerDatosPaginacion($filtros = [])
    {
        try {
            return $this->service->obtenerDatosPaginacion($filtros);
        } catch (Exception $e) {
            error_log("Error obteniendo datos de paginación: " . $e->getMessage());
            return [
                'total_registros' => 0,
                'total_paginas' => 0,
                'pagina_actual' => 1,
                'registros_por_pagina' => 10
            ];
        }
    }

    public function obtenerTipos()
    {
        return $this->service->obtenerTipos();
    }

    public function obtenerUnidadesMedidaDisponibles()
    {
        return $this->service->obtenerUnidadesMedidaDisponibles();
    }

    public function obtenerUnidadesMedidaProducto($idProducto)
    {
        return $this->service->obtenerUnidadesMedidaProducto($idProducto);
    }

    public function obtenerTiposUnicos()
    {
        return $this->service->obtenerTiposUnicos();
    }

    public function obtenerProductosParaCatalogo()
    {
        return $this->service->obtenerProductosParaCatalogo();
    }


    public function verificarPermisos($accion)
    {
        switch ($accion) {
            case 'ver':
            case 'listar':
                return true;

            case 'crear':
            case 'editar':
            case 'eliminar':
                return isset($_SESSION['rol']) && $_SESSION['rol'] === '1';

            default:
                return false;
        }
    }


    public function obtenerDatosVista()
    {
        return [
            'titulo' => 'Gestión de Productos',
            'url_base' => $this->urlBase,
            'fecha_actual' => date('Y-m-d H:i:s'),
            'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
            'es_admin' => $this->esAdministrador()
        ];
    }

    public function procesarFiltros()
    {
        $filtros = [
            'descripcion' => trim($_GET['descripcion'] ?? ''),
            'tipo' => trim($_GET['tipo'] ?? ''),
            'codigo' => trim($_GET['codigo'] ?? ''),
            'pagina' => max(1, (int)($_GET['pagina'] ?? 1)),
            'registros_por_pagina' => 10
        ];

        try {
            $productos = $this->obtenerListaProductos($filtros);
            $paginacion = $this->obtenerDatosPaginacion($filtros);

            return [
                'error' => '',
                'productos' => $productos,
                'paginacion' => $paginacion,
                'filtros_aplicados' => $filtros
            ];
        } catch (Exception $e) {
            error_log("Error procesando filtros: " . $e->getMessage());
            return [
                'error' => 'Error al procesar la búsqueda',
                'productos' => [],
                'paginacion' => $this->obtenerDatosPaginacion(),
                'filtros_aplicados' => $filtros
            ];
        }
    }

    private function obtenerDatosFormulario()
    {
        return [
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'codigobr' => trim($_POST['codigobr'] ?? ''),
            'tipo' => trim($_POST['tipo'] ?? ''),
            'cantidad' => str_replace(['.', ','], ['', '.'], $_POST['cantidad'] ?? ''),
            'ncm' => trim($_POST['ncm'] ?? '')
        ];
    }


    private function esAdministrador()
    {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === '1';
    }


    public function obtenerConfiguracionJS()
    {
        return [
            'url_base' => $this->urlBase,
            'usuario' => $_SESSION['nombre'] ?? 'Usuario',
            'esAdmin' => $this->esAdministrador(),
            'debug' => isset($_GET['debug']),
            'rol' => $_SESSION['rol'] ?? '0'
        ];
    }


    public function manejarMensajes()
    {
        $mensaje = '';
        $error = '';

        if (isset($_GET['mensaje'])) {
            $mensaje = htmlspecialchars($_GET['mensaje']);
        }

        if (isset($_GET['error'])) {
            $error = htmlspecialchars($_GET['error']);
        }

        return [
            'mensaje' => $mensaje,
            'error' => $error
        ];
    }


    public function logActividad($accion, $detalles = null)
    {
        $usuario = $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "PRODUCTOS - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);
    }


    public function construirURL($nuevos_params = [])
    {
        $params_actuales = $_GET;
        $params_finales = array_merge($params_actuales, $nuevos_params);

        $params_finales = array_filter($params_finales, function ($value) {
            return $value !== '' && $value !== null;
        });

        return 'index.php' . (!empty($params_finales) ? '?' . http_build_query($params_finales) : '');
    }
}
