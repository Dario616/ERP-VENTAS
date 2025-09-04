<?php
require_once __DIR__ . '/../repository/UsuarioRepository.php';
require_once __DIR__ . '/../services/UsuarioService.php';

// Configurar zona horaria para Paraguay
date_default_timezone_set('America/Asuncion');

/**
 * Controller para manejo de usuarios
 */
class UsuarioController
{
    private $repository;
    private $service;
    private $urlBase;

    public function __construct($conexion, $urlBase)
    {
        $this->repository = new UsuarioRepository($conexion);
        $this->service = new UsuarioService($this->repository);
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
            case 'buscar_usuarios':
                $this->buscarUsuariosApi();
                break;

            case 'obtener_estadisticas':
                $this->obtenerEstadisticasApi();
                break;

            case 'eliminar_usuario':
                $this->eliminarUsuarioApi();
                break;

            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

        return true;
    }

    /**
     * API: Buscar usuarios para autocompletado
     */
    private function buscarUsuariosApi()
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
            $usuarios = $this->service->buscarUsuarios($termino);

            echo json_encode([
                'success' => true,
                'usuarios' => $usuarios
            ]);
        } catch (Exception $e) {
            error_log("Error en API buscar usuarios: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    /**
     * API: Obtener estadísticas de usuarios
     */
    private function obtenerEstadisticasApi()
    {
        try {
            $estadisticas = $this->service->obtenerEstadisticas();

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

    /**
     * API: Eliminar usuario
     */
    private function eliminarUsuarioApi()
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
            $idUsuarioActual = $_SESSION['id'] ?? null;
            $resultado = $this->service->eliminarUsuario($id, $idUsuarioActual);

            echo json_encode($resultado);
        } catch (Exception $e) {
            error_log("Error en API eliminar usuario: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor'
            ]);
        }
    }

    /**
     * Procesar formulario de registro de usuario
     */
    public function procesarRegistro()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['error' => 'Método no permitido'];
        }

        try {
            $datos = $this->obtenerDatosFormulario();

            $resultado = $this->service->crearUsuario($datos);

            if ($resultado['success']) {
                header("Location: " . $this->urlBase . "secciones/usuarios/index.php?mensaje=" . urlencode($resultado['mensaje']));
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

    /**
     * Procesar formulario de edición de usuario
     */
    public function procesarEdicion($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['error' => 'Método no permitido'];
        }

        try {
            $datos = $this->obtenerDatosFormulario();
            $idUsuarioActual = $_SESSION['id'] ?? null;

            $resultado = $this->service->actualizarUsuario($id, $datos, $idUsuarioActual);

            if ($resultado['success']) {
                header("Location: " . $this->urlBase . "secciones/usuarios/index.php?mensaje=" . urlencode($resultado['mensaje']));
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

    /**
     * Obtener usuario para edición
     */
    public function obtenerUsuarioParaEdicion($id)
    {
        try {
            return $this->service->obtenerUsuarioPorId($id);
        } catch (Exception $e) {
            error_log("Error obteniendo usuario: " . $e->getMessage());
            throw new Exception('Usuario no encontrado');
        }
    }

    /**
     * Obtener usuario para visualización
     */
    public function obtenerUsuarioParaVer($id)
    {
        try {
            return $this->service->obtenerUsuarioPorId($id);
        } catch (Exception $e) {
            error_log("Error obteniendo usuario: " . $e->getMessage());
            throw new Exception('Usuario no encontrado');
        }
    }

    /**
     * Procesar eliminación de usuario
     */
    public function procesarEliminacion($id)
    {
        try {
            $idUsuarioActual = $_SESSION['id'] ?? null;
            $resultado = $this->service->eliminarUsuario($id, $idUsuarioActual);

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

    /**
     * Obtener lista de usuarios
     */
    public function obtenerListaUsuarios()
    {
        try {
            return $this->service->obtenerUsuarios();
        } catch (Exception $e) {
            error_log("Error obteniendo lista de usuarios: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener roles disponibles
     */
    public function obtenerRoles()
    {
        return $this->service->obtenerRoles();
    }

    /**
     * Verificar permisos para la acción solicitada
     */
    public function verificarPermisos($accion, $idUsuario = null)
    {
        // Solo administradores pueden gestionar usuarios
        $esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === '1';

        if (!$esAdmin) {
            return false;
        }

        switch ($accion) {
            case 'ver':
            case 'listar':
            case 'crear':
            case 'editar':
                return true;

            case 'eliminar':
                // No puede eliminar su propio usuario
                if ($idUsuario && isset($_SESSION['id'])) {
                    return $idUsuario != $_SESSION['id'];
                }
                return true;

            default:
                return false;
        }
    }

    /**
     * Obtener datos para la vista
     */
    public function obtenerDatosVista()
    {
        try {
            $estadisticas = $this->service->obtenerEstadisticas();

            return [
                'titulo' => 'Gestión de Usuarios',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => $this->esAdministrador(),
                'estadisticas' => $estadisticas
            ];
        } catch (Exception $e) {
            error_log("Error obteniendo datos de vista: " . $e->getMessage());
            return [
                'titulo' => 'Gestión de Usuarios',
                'url_base' => $this->urlBase,
                'fecha_actual' => date('Y-m-d H:i:s'),
                'usuario_actual' => $_SESSION['nombre'] ?? 'Usuario',
                'es_admin' => false,
                'estadisticas' => []
            ];
        }
    }

    /**
     * Obtener datos del formulario
     */
    private function obtenerDatosFormulario()
    {
        $datos = [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'usuario' => trim($_POST['usuario'] ?? ''),
            'rol' => trim($_POST['rol'] ?? '')
        ];

        // Manejo de contraseña en registro
        if (isset($_POST['contrasenia'])) {
            $datos['contrasenia'] = trim($_POST['contrasenia']);
            $datos['confirmar_contrasenia'] = trim($_POST['confirmar_contrasenia'] ?? '');
        }

        // Manejo de cambio de contraseña en edición
        if (isset($_POST['cambiar_contrasenia'])) {
            $datos['cambiar_contrasenia'] = true;
            $datos['contrasenia'] = trim($_POST['contrasenia'] ?? '');
            $datos['confirmar_contrasenia'] = trim($_POST['confirmar_contrasenia'] ?? '');
        } else {
            // IMPORTANTE: En edición, si no se marca el checkbox, no incluir cambio de contraseña
            $datos['cambiar_contrasenia'] = false;
        }

        return $datos;
    }

    /**
     * Verificar si el usuario es administrador
     */
    private function esAdministrador()
    {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === '1';
    }

    /**
     * Obtener configuración para JavaScript
     */
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

    /**
     * Manejar mensajes flash
     */
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

    /**
     * Log de actividad
     */
    public function logActividad($accion, $detalles = null)
    {
        $usuario = $_SESSION['nombre'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        $timestamp = date('Y-m-d H:i:s');

        $mensaje = "USUARIOS - Usuario: {$usuario} | IP: {$ip} | Acción: {$accion}";

        if ($detalles) {
            $mensaje .= " | Detalles: {$detalles}";
        }

        error_log($mensaje);
    }

    /**
     * Validar parámetros de entrada
     */
    public function validarParametros($parametros)
    {
        $errores = [];

        if (isset($parametros['id'])) {
            $id = (int)$parametros['id'];
            if ($id < 1) {
                $errores[] = 'ID de usuario inválido';
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
}
