<?php

/**
 * Service para lógica de negocio de usuarios
 */
class UsuarioService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    /**
     * Obtener usuarios con información enriquecida
     */
    public function obtenerUsuarios()
    {
        $usuarios = $this->repository->obtenerUsuarios();
        return array_map([$this, 'enriquecerDatosUsuario'], $usuarios);
    }

    /**
     * Obtener usuario por ID con validaciones
     */
    public function obtenerUsuarioPorId($id)
    {
        if (!$this->validarId($id)) {
            throw new Exception('ID de usuario inválido');
        }

        $usuario = $this->repository->obtenerUsuarioPorId($id);

        if (!$usuario) {
            throw new Exception('Usuario no encontrado');
        }

        return $this->enriquecerDatosUsuario($usuario);
    }

    /**
     * Crear nuevo usuario con validaciones
     */
    public function crearUsuario($datos)
    {
        // Validar datos de entrada
        $errores = $this->validarDatosUsuario($datos, null, true); // true = es creación

        if (!empty($errores)) {
            return ['success' => false, 'errores' => $errores];
        }

        try {
            // Preparar datos para inserción
            $datosLimpios = $this->prepararDatosParaGuardar($datos);

            // Crear usuario
            if (!$this->repository->crearUsuario($datosLimpios)) {
                throw new Exception('Error al crear el usuario');
            }

            return [
                'success' => true,
                'mensaje' => 'Usuario registrado correctamente'
            ];
        } catch (Exception $e) {
            error_log("Error en crearUsuario: " . $e->getMessage());
            return ['success' => false, 'errores' => ['Error al registrar el usuario: ' . $e->getMessage()]];
        }
    }

    /**
     * Actualizar usuario con validaciones
     */
    public function actualizarUsuario($id, $datos, $idUsuarioActual)
    {
        if (!$this->validarId($id)) {
            return ['success' => false, 'errores' => ['ID de usuario inválido']];
        }

        // Validar datos de entrada (incluyendo ID para exclusión en validaciones únicas)
        $errores = $this->validarDatosUsuario($datos, $id, false); // false = es edición

        if (!empty($errores)) {
            return ['success' => false, 'errores' => $errores];
        }

        try {
            // Preparar datos para actualización
            $datosLimpios = $this->prepararDatosParaActualizar($datos);

            // Actualizar usuario
            if (!$this->repository->actualizarUsuario($id, $datosLimpios)) {
                throw new Exception('Error al actualizar el usuario');
            }

            // Actualizar sesión si es necesario
            $this->repository->actualizarSesionSiEsNecesario($id, $datosLimpios, $idUsuarioActual);

            return [
                'success' => true,
                'mensaje' => 'Usuario actualizado correctamente'
            ];
        } catch (Exception $e) {
            error_log("Error en actualizarUsuario: " . $e->getMessage());
            return ['success' => false, 'errores' => ['Error al actualizar el usuario: ' . $e->getMessage()]];
        }
    }

    /**
     * Eliminar usuario
     */
    public function eliminarUsuario($id, $idUsuarioActual)
    {
        if (!$this->validarId($id)) {
            return ['success' => false, 'error' => 'ID de usuario inválido'];
        }

        // Verificar si puede eliminar el usuario
        if (!$this->repository->puedeEliminarUsuario($id, $idUsuarioActual)) {
            return ['success' => false, 'error' => 'No puedes eliminar tu propio usuario'];
        }

        try {
            if (!$this->repository->eliminarUsuario($id)) {
                throw new Exception('Error al eliminar el usuario');
            }

            return ['success' => true, 'mensaje' => 'Usuario eliminado correctamente'];
        } catch (Exception $e) {
            error_log("Error en eliminarUsuario: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al eliminar el usuario: ' . $e->getMessage()];
        }
    }

    /**
     * Obtener roles disponibles
     */
    public function obtenerRoles()
    {
        return $this->repository->obtenerRoles();
    }

    /**
     * Obtener estadísticas de usuarios
     */
    public function obtenerEstadisticas()
    {
        $estadisticas = $this->repository->obtenerEstadisticas();

        if (!empty($estadisticas)) {
            // Calcular porcentajes
            $total = $estadisticas['total_usuarios'];
            if ($total > 0) {
                $estadisticas['porcentaje_administradores'] = round(($estadisticas['administradores'] / $total) * 100, 1);
                $estadisticas['porcentaje_vendedores'] = round(($estadisticas['vendedores'] / $total) * 100, 1);
                $estadisticas['porcentaje_contadores'] = round(($estadisticas['contadores'] / $total) * 100, 1);
                $estadisticas['porcentaje_pcp'] = round(($estadisticas['pcp'] / $total) * 100, 1);
            }
        }

        return $estadisticas ?: [];
    }

    /**
     * Buscar usuarios para autocompletado
     */
    public function buscarUsuarios($termino, $limite = 10)
    {
        if (strlen(trim($termino)) < 2) {
            throw new Exception('El término de búsqueda debe tener al menos 2 caracteres');
        }

        $usuarios = $this->repository->buscarUsuarios($termino, $limite);

        return array_map(function ($usuario) {
            return [
                'id' => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'usuario' => $usuario['usuario'],
                'rol' => $usuario['rol'],
                'rol_nombre' => $this->obtenerNombreRol($usuario['rol']),
                'texto_completo' => $usuario['nombre'] . ' (' . $usuario['usuario'] . ')'
            ];
        }, $usuarios);
    }

    /**
     * Validar datos del usuario
     */
    private function validarDatosUsuario($datos, $idExcluir = null, $esCreacion = false)
    {
        $errores = [];

        // Validar nombre (obligatorio)
        if (empty(trim($datos['nombre'] ?? ''))) {
            $errores[] = "El nombre es obligatorio.";
        }

        // Validar usuario (obligatorio y único)
        $usuario = trim($datos['usuario'] ?? '');
        if (empty($usuario)) {
            $errores[] = "El usuario es obligatorio.";
        } elseif ($this->repository->verificarUsuarioExiste($usuario, $idExcluir)) {
            $errores[] = "El nombre de usuario ya está en uso. Por favor, elija otro.";
        }

        // Validar rol (obligatorio)
        if (empty(trim($datos['rol'] ?? ''))) {
            $errores[] = "El rol es obligatorio.";
        } elseif (!array_key_exists($datos['rol'], $this->repository->obtenerRoles())) {
            $errores[] = "El rol seleccionado no es válido.";
        }

        // Validar contraseña según el contexto
        if ($esCreacion) {
            // En creación, la contraseña siempre es obligatoria
            $errores = array_merge($errores, $this->validarContrasenia($datos));
        } else {
            // En edición, solo validar si se está cambiando la contraseña
            if (isset($datos['cambiar_contrasenia']) && $datos['cambiar_contrasenia']) {
                $errores = array_merge($errores, $this->validarContrasenia($datos));
            }
        }

        return $errores;
    }

    /**
     * Validar contraseña
     */
    private function validarContrasenia($datos)
    {
        $errores = [];
        $contrasenia = trim($datos['contrasenia'] ?? '');
        $confirmar_contrasenia = trim($datos['confirmar_contrasenia'] ?? '');

        if (empty($contrasenia)) {
            $errores[] = "La contraseña es obligatoria.";
        } elseif (strlen($contrasenia) < 4) {
            $errores[] = "La contraseña debe tener al menos 4 caracteres.";
        }

        if ($contrasenia !== $confirmar_contrasenia) {
            $errores[] = "Las contraseñas no coinciden.";
        }

        return $errores;
    }

    /**
     * Validar ID
     */
    private function validarId($id)
    {
        return is_numeric($id) && $id > 0;
    }

    /**
     * Preparar datos para guardar (nuevo usuario)
     */
    private function prepararDatosParaGuardar($datos)
    {
        return [
            'nombre' => trim($datos['nombre']),
            'usuario' => trim($datos['usuario']),
            'rol' => trim($datos['rol']),
            'contrasenia' => trim($datos['contrasenia']) // En producción: password_hash($datos['contrasenia'], PASSWORD_DEFAULT)
        ];
    }

    /**
     * Preparar datos para actualizar
     */
    private function prepararDatosParaActualizar($datos)
    {
        $datosLimpios = [
            'nombre' => trim($datos['nombre']),
            'usuario' => trim($datos['usuario']),
            'rol' => trim($datos['rol'])
        ];

        // Manejo de contraseña
        if (isset($datos['cambiar_contrasenia']) && $datos['cambiar_contrasenia']) {
            $datosLimpios['cambiar_contrasenia'] = true;
            $datosLimpios['contrasenia'] = trim($datos['contrasenia']); // En producción: password_hash
        }

        return $datosLimpios;
    }

    /**
     * Enriquecer datos del usuario con información adicional
     */
    private function enriquecerDatosUsuario($usuario)
    {
        // Nombre del rol
        $usuario['rol_nombre'] = $this->obtenerNombreRol($usuario['rol']);

        // Badge HTML del rol
        $usuario['rol_badge'] = $this->obtenerBadgeRol($usuario['rol']);

        // Información adicional
        $usuario['puede_ser_eliminado'] = isset($_SESSION['id']) ?
            $this->repository->puedeEliminarUsuario($usuario['id'], $_SESSION['id']) : false;

        return $usuario;
    }

    /**
     * Obtener nombre del rol
     */
    private function obtenerNombreRol($rol)
    {
        $roles = $this->repository->obtenerRoles();
        return $roles[$rol] ?? 'Usuario';
    }

    /**
     * Obtener badge HTML del rol
     */
    private function obtenerBadgeRol($rol)
    {
        switch ($rol) {
            case '1':
                return '<span class="badge bg-danger"><i class="fas fa-user-shield me-1"></i>Administrador</span>';
            case '2':
                return '<span class="badge bg-success"><i class="fas fa-user-tie me-1"></i>Vendedor</span>';
            case '3':
                return '<span class="badge bg-warning"><i class="fas fa-calculator me-1"></i>Contador</span>';
            case '4':
                return '<span class="badge bg-primary"><i class="fas fa-cogs me-1"></i>PCP</span>';
            default:
                return '<span class="badge bg-secondary"><i class="fas fa-user me-1"></i>Usuario</span>';
        }
    }
}
