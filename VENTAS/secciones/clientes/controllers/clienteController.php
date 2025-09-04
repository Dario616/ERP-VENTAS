<?php
require_once __DIR__ . '/../repository/ClienteRepository.php';
require_once __DIR__ . '/../services/ClienteService.php';

date_default_timezone_set('America/Asuncion');

class ClienteController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new ClienteRepository($conexion);
        $this->service = new ClienteService($this->repository);
        $this->urlBase = $urlBase;
    }

    public function handleApiRequest()
    {
        if (!isset($_GET['action'])) {
            return false;
        }

        header('Content-Type: application/json');

        switch ($_GET['action']) {
            case 'buscar_clientes':
                $this->buscarClientesApi();
                break;

            case 'obtener_estadisticas':
                $this->obtenerEstadisticasApi();
                break;

            case 'eliminar_cliente':
                $this->eliminarClienteApi();
                break;

            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

        return true;
    }

    private function buscarClientesApi()
    {
        $termino = trim($_GET['termino'] ?? '');

        if (strlen($termino) < 2) {
            echo json_encode([
                'success' => false,
                'error' => 'Mínimo 2 caracteres'
            ]);
            return;
        }

        try {
            $idUsuario = $this->obtenerIdUsuarioSegunRol();
            $clientes = $this->service->buscarClientes($termino, $idUsuario);

            echo json_encode([
                'success' => true,
                'clientes' => $clientes
            ]);
        } catch (Exception $e) {
            error_log("Error en API buscar clientes: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function obtenerEstadisticasApi()
    {
        try {
            $idUsuario = $this->obtenerIdUsuarioSegunRol();
            $estadisticas = $this->service->obtenerEstadisticas($idUsuario);

            echo json_encode([
                'success' => true,
                'estadisticas' => $estadisticas
            ]);
        } catch (Exception $e) {
            error_log("Error en API estadísticas: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    private function eliminarClienteApi()
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
            $idUsuario = $this->obtenerIdUsuarioSegunRol();
            $resultado = $this->service->eliminarCliente($id, $idUsuario);

            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en API eliminar cliente: " . $e->getMessage());
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
            $idUsuario = $_SESSION['id'];

            $resultado = $this->service->crearCliente($datos, $idUsuario);

            if ($resultado['success']) {
                $urlRedireccionPorDefecto = $this->urlBase . "secciones/clientes/index.php";
                $urlRedireccion = filter_input(INPUT_POST, 'redirect_url', FILTER_SANITIZE_URL);
                if (empty($urlRedireccion)) {
                    $urlRedireccion = $urlRedireccionPorDefecto;
                }
                header("Location: " . $urlRedireccion . "?mensaje=" . urlencode($resultado['mensaje']));
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
            $idUsuario = $this->obtenerIdUsuarioSegunRol();

            $resultado = $this->service->actualizarCliente($id, $datos, $idUsuario);

            if ($resultado['success']) {
                // Redirigir con mensaje de éxito
                header("Location: " . $this->urlBase . "secciones/clientes/index.php?mensaje=" . urlencode($resultado['mensaje']));
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

    public function obtenerClienteParaEdicion($id)
    {
        try {
            $idUsuario = $this->obtenerIdUsuarioSegunRol();
            return $this->service->obtenerClientePorId($id, $idUsuario);
        } catch (Exception $e) {
            error_log("Error obteniendo cliente: " . $e->getMessage());
            throw new Exception('Cliente no encontrado');
        }
    }

    public function obtenerClienteParaVer($id)
    {
        try {
            $idUsuario = $this->obtenerIdUsuarioSegunRol();
            return $this->service->obtenerClientePorId($id, $idUsuario);
        } catch (Exception $e) {
            error_log("Error obteniendo cliente: " . $e->getMessage());
            throw new Exception('Cliente no encontrado');
        }
    }

    public function procesarEliminacion($id)
    {
        try {
            $idUsuario = $this->obtenerIdUsuarioSegunRol();
            $resultado = $this->service->eliminarCliente($id, $idUsuario);

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


    public function obtenerListaClientes($filtros = [])
    {
        try {
            $idUsuario = $this->obtenerIdUsuarioSegunRol();
            return $this->service->obtenerClientes($idUsuario, $filtros);
        } catch (Exception $e) {
            error_log("Error obteniendo lista de clientes: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerDatosVista()
    {
        try {
            $idUsuario = $this->obtenerIdUsuarioSegunRol();
            $estadisticas = $this->service->obtenerEstadisticas($idUsuario);

            return [
                'titulo' => 'Gestión de Clientes',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => $this->esAdministrador(),
                'estadisticas' => $estadisticas
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => 'Gestión de Clientes',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => false,
                'estadisticas' => []
            ];
        }
    }

    private function obtenerDatosFormulario()
    {
        return [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'direccion' => trim($_POST['direccion'] ?? ''),
            'ruc' => trim($_POST['ruc'] ?? ''),
            'cnpj' => trim($_POST['cnpj'] ?? ''),
            'ie' => trim($_POST['ie'] ?? ''),
            'nro' => trim($_POST['nro'] ?? ''),
            'pais' => $_POST['pais'] ?? 'PY'
        ];
    }

    private function obtenerIdUsuarioSegunRol()
    {
        return $this->esAdministrador() ? null : $_SESSION['id'];
    }

    private function esAdministrador()
    {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === '1';
    }

    public function validarParametros($parametros)
    {
        $errores = [];

        if (isset($parametros['id'])) {
            $id = (int)$parametros['id'];
            if ($id < 1) {
                $errores[] = 'ID de cliente inválido';
            }
        }

        if (isset($parametros['termino'])) {
            $termino = trim($parametros['termino']);
            if (strlen($termino) > 0 && strlen($termino) < 2) {
                $errores[] = 'El término de búsqueda debe tener al menos 2 caracteres';
            }
        }

        return $errores;
    }

    public function logActividad($accion, $detalles = null)
    {
        $usuario = $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "CLIENTES - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);
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

    public function procesarFiltros()
    {
        $filtros = [
            'nombre' => trim($_GET['nombre'] ?? ''),
            'pais' => trim($_GET['pais'] ?? ''),
            'pagina' => max(1, (int)($_GET['page'] ?? 1))
        ];

        try {
            $clientes = $this->obtenerListaClientes($filtros);

            return [
                'error' => '',
                'clientes' => $clientes,
                'filtros_aplicados' => $filtros
            ];
        } catch (Exception $e) {
            error_log("Error procesando filtros: " . $e->getMessage());
            return [
                'error' => 'Error al procesar la búsqueda',
                'clientes' => [],
                'filtros_aplicados' => $filtros
            ];
        }
    }


    public function verificarPermisos($accion, $idCliente = null)
    {
        $esAdmin = $this->esAdministrador();
        $tienePermiso = false;

        switch ($accion) {
            case 'ver':
            case 'listar':
                $tienePermiso = true;
                break;

            case 'crear':
            case 'editar':
                $tienePermiso = $esAdmin || (isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['1', '2']));
                break;

            case 'eliminar':
                $tienePermiso = $esAdmin;
                break;

            default:
                $tienePermiso = false;
        }

        if ($tienePermiso && !$esAdmin && $idCliente && in_array($accion, ['ver', 'editar'])) {
            try {
                $cliente = $this->service->obtenerClientePorId($idCliente, $_SESSION['id']);
                $tienePermiso = ($cliente !== false);
            } catch (Exception $e) {
                $tienePermiso = false;
            }
        }

        return $tienePermiso;
    }
}

$repositoryPath = __DIR__ . '/../repository/ClienteRepository.php';
$servicePath = __DIR__ . '/../services/ClienteService.php';

if (!file_exists($repositoryPath) || !file_exists($servicePath)) {
    die("Error crítico: Faltan archivos de dependencia del controlador." .
        "<br>Ruta del repositorio buscada: " . htmlspecialchars($repositoryPath) .
        "<br>Ruta del servicio buscada: " . htmlspecialchars($servicePath));
}
