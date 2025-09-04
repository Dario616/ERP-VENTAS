<?php


class ClienteService
{
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }


    public function obtenerClientes($idUsuario = null, $filtros = [])
    {
        $clientes = $this->repository->obtenerClientes($idUsuario, $filtros);

        return array_map([$this, 'enriquecerDatosCliente'], $clientes);
    }

    public function obtenerClientePorId($id, $idUsuario = null)
    {
        if (!$this->validarId($id)) {
            throw new Exception('ID de cliente inválido');
        }

        $cliente = $this->repository->obtenerClientePorId($id, $idUsuario);

        if (!$cliente) {
            throw new Exception('Cliente no encontrado');
        }

        return $this->enriquecerDatosCliente($cliente);
    }


    public function crearCliente($datos, $idUsuario)
    {
        $errores = $this->validarDatosCliente($datos);

        if (!empty($errores)) {
            return ['success' => false, 'errores' => $errores];
        }

        $datosLimpios = $this->prepararDatosParaGuardar($datos, $idUsuario);

        $resultado = $this->repository->crearCliente($datosLimpios);

        if ($resultado) {
            return ['success' => true, 'mensaje' => 'Cliente creado correctamente'];
        } else {
            return ['success' => false, 'errores' => ['Error al guardar el cliente en la base de datos']];
        }
    }


    public function actualizarCliente($id, $datos, $idUsuario = null)
    {
        if (!$this->validarId($id)) {
            return ['success' => false, 'errores' => ['ID de cliente inválido']];
        }

        $errores = $this->validarDatosCliente($datos, $id);

        if (!empty($errores)) {
            return ['success' => false, 'errores' => $errores];
        }

        $datosLimpios = $this->prepararDatosParaActualizar($datos);

        $resultado = $this->repository->actualizarCliente($id, $datosLimpios, $idUsuario);

        if ($resultado) {
            return ['success' => true, 'mensaje' => 'Cliente actualizado correctamente'];
        } else {
            return ['success' => false, 'errores' => ['Error al actualizar el cliente o no tienes permisos']];
        }
    }


    public function eliminarCliente($id, $idUsuario = null)
    {
        if (!$this->validarId($id)) {
            return ['success' => false, 'error' => 'ID de cliente inválido'];
        }

        $resultado = $this->repository->eliminarCliente($id, $idUsuario);

        if ($resultado) {
            return ['success' => true, 'mensaje' => 'Cliente eliminado correctamente'];
        } else {
            return ['success' => false, 'error' => 'Error al eliminar el cliente o no tienes permisos'];
        }
    }


    private function validarDatosCliente($datos, $idExcluir = null)
    {
        $errores = [];

        if (empty(trim($datos['nombre'] ?? ''))) {
            $errores[] = "El nombre es obligatorio.";
        }

        $pais = $datos['pais'] ?? 'PY';
        if (!in_array($pais, ['PY', 'BR'])) {
            $errores[] = "Debe seleccionar un país válido.";
        }

        if ($pais === 'PY') {
            $errores = array_merge($errores, $this->validarDatosParaguay($datos, $idExcluir));
        } elseif ($pais === 'BR') {
            $errores = array_merge($errores, $this->validarDatosBrasil($datos, $idExcluir));
        }

        if (!empty($datos['email'])) {
            $errores = array_merge($errores, $this->validarEmail($datos['email'], $idExcluir));
        }

        if (!empty($datos['telefono'])) {
            if (!$this->validarFormatoTelefono($datos['telefono'])) {
                $errores[] = "El formato del teléfono no es válido.";
            }
        }

        return $errores;
    }

    private function validarDatosParaguay($datos, $idExcluir = null)
    {
        $errores = [];
        $ruc = trim($datos['ruc'] ?? '');

        if (!empty($ruc)) {
            if (!preg_match('/^[0-9-]+$/', $ruc)) {
                $errores[] = "El RUC debe contener solo dígitos y guiones.";
            } elseif ($this->repository->verificarRucExiste($ruc, $idExcluir)) {
                $errores[] = "Este RUC ya está registrado.";
            }
        }

        return $errores;
    }


    private function validarDatosBrasil($datos, $idExcluir = null)
    {
        $errores = [];

        $cnpj = trim($datos['cnpj'] ?? '');
        if (!empty($cnpj)) {
            if (!$this->validarFormatoCnpj($cnpj)) {
                $errores[] = "El CNPJ debe tener el formato XX.XXX.XXX/XXXX-XX.";
            } elseif ($this->repository->verificarCnpjExiste($cnpj, $idExcluir)) {
                $errores[] = "Este CNPJ ya está registrado.";
            }
        }

        $ie = trim($datos['ie'] ?? '');
        if (!empty($ie)) {
            if (!$this->validarFormatoIe($ie)) {
                $errores[] = "La Inscripción Estatal debe tener entre 8 y 14 dígitos.";
            } elseif ($this->repository->verificarIeExiste($ie, $idExcluir)) {
                $errores[] = "Esta Inscripción Estatal ya está registrada.";
            }
        }

        return $errores;
    }

    private function validarEmail($email, $idExcluir = null)
    {
        $errores = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = "El formato del correo electrónico no es válido.";
        } elseif ($this->repository->verificarEmailExiste($email, $idExcluir)) {
            $errores[] = "Este correo electrónico ya está registrado.";
        }

        return $errores;
    }

    private function validarFormatoCnpj($cnpj)
    {
        $cnpj_numeros = preg_replace('/[^0-9]/', '', $cnpj);
        return strlen($cnpj_numeros) === 14 && preg_match('/^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$/', $cnpj);
    }

    private function validarFormatoIe($ie)
    {
        $ie_numeros = preg_replace('/[^0-9]/', '', $ie);
        return strlen($ie_numeros) >= 8 && strlen($ie_numeros) <= 14;
    }

    private function validarFormatoTelefono($telefono)
    {
        return preg_match('/^[0-9()+\- ]{7,20}$/', $telefono);
    }


    private function validarId($id)
    {
        return is_numeric($id) && $id > 0;
    }

    private function prepararDatosParaGuardar($datos, $idUsuario)
    {
        $pais = $datos['pais'] ?? 'PY';
        $brasil = ($pais === 'BR');

        return [
            'nombre' => trim($datos['nombre']),
            'descripcion' => trim($datos['descripcion'] ?? ''),
            'telefono' => trim($datos['telefono'] ?? ''),
            'email' => trim($datos['email'] ?? ''),
            'direccion' => trim($datos['direccion'] ?? ''),
            'ruc' => $brasil ? null : trim($datos['ruc'] ?? ''),
            'cnpj' => $brasil ? trim($datos['cnpj'] ?? '') : null,
            'ie' => $brasil ? trim($datos['ie'] ?? '') : null,
            'nro' => trim($datos['nro'] ?? ''),
            'brasil' => $brasil,
            'id_usuario' => $idUsuario,
            'fecha_registro' => date('Y-m-d H:i:s')
        ];
    }

    private function prepararDatosParaActualizar($datos)
    {
        $pais = $datos['pais'] ?? 'PY';
        $brasil = ($pais === 'BR');

        return [
            'nombre' => trim($datos['nombre']),
            'descripcion' => trim($datos['descripcion'] ?? ''),
            'telefono' => trim($datos['telefono'] ?? ''),
            'email' => trim($datos['email'] ?? ''),
            'direccion' => trim($datos['direccion'] ?? ''),
            'ruc' => $brasil ? null : trim($datos['ruc'] ?? ''),
            'cnpj' => $brasil ? trim($datos['cnpj'] ?? '') : null,
            'ie' => $brasil ? trim($datos['ie'] ?? '') : null,
            'nro' => trim($datos['nro'] ?? ''),
            'brasil' => $brasil
        ];
    }


    private function enriquecerDatosCliente($cliente)
    {
        $cliente['pais'] = ($cliente['brasil'] ?? false) ? 'BR' : 'PY';
        $cliente['pais_nombre'] = ($cliente['brasil'] ?? false) ? 'Brasil' : 'Paraguay';

        if (!empty($cliente['fecha_registro'])) {
            $cliente['fecha_registro_formateada'] = $this->formatearFecha($cliente['fecha_registro']);
        }

        if ($cliente['brasil'] ?? false) {
            $cliente['documento_principal'] = $cliente['cnpj'] ?? '';
            $cliente['tipo_documento'] = 'CNPJ';
        } else {
            $cliente['documento_principal'] = $cliente['ruc'] ?? '';
            $cliente['tipo_documento'] = 'RUC/CI';
        }

        $cliente['tiene_email'] = !empty($cliente['email']);
        $cliente['tiene_telefono'] = !empty($cliente['telefono']);
        $cliente['tiene_whatsapp'] = !empty($cliente['nro']);

        return $cliente;
    }

    private function formatearFecha($fecha)
    {
        if (!$fecha) return '';

        try {
            $dt = new DateTime($fecha);
            return $dt->format('d/m/Y H:i');
        } catch (Exception $e) {
            return $fecha;
        }
    }


    public function obtenerEstadisticas($idUsuario = null)
    {
        $estadisticas = $this->repository->obtenerEstadisticas($idUsuario);

        if (!empty($estadisticas)) {
            // Calcular porcentajes
            $total = $estadisticas['total_clientes'];
            if ($total > 0) {
                $estadisticas['porcentaje_brasil'] = round(($estadisticas['clientes_brasil'] / $total) * 100, 1);
                $estadisticas['porcentaje_paraguay'] = round(($estadisticas['clientes_paraguay'] / $total) * 100, 1);
                $estadisticas['porcentaje_con_email'] = round(($estadisticas['con_email'] / $total) * 100, 1);
                $estadisticas['porcentaje_con_telefono'] = round(($estadisticas['con_telefono'] / $total) * 100, 1);
            }
        }

        return $estadisticas ?: [];
    }

    public function buscarClientes($termino, $idUsuario = null, $limite = 10)
    {
        if (strlen(trim($termino)) < 2) {
            throw new Exception('El término de búsqueda debe tener al menos 2 caracteres');
        }

        $clientes = $this->repository->buscarClientes($termino, $idUsuario, $limite);

        return array_map(function ($cliente) {
            return [
                'id' => $cliente['id'],
                'nombre' => $cliente['nombre'],
                'email' => $cliente['email'],
                'documento' => ($cliente['brasil'] ?? false) ? $cliente['cnpj'] : $cliente['ruc'],
                'pais' => ($cliente['brasil'] ?? false) ? 'BR' : 'PY',
                'texto_completo' => $cliente['nombre'] . ' - ' . (($cliente['brasil'] ?? false) ? ($cliente['cnpj'] ?? 'Sin CNPJ') : ($cliente['ruc'] ?? 'Sin RUC'))
            ];
        }, $clientes);
    }


    public function validarPermisoAcceso($idCliente, $idUsuario, $esAdmin = false)
    {
        if ($esAdmin) {
            return true;
        }

        $cliente = $this->repository->obtenerClientePorId($idCliente, $idUsuario);
        return $cliente !== false;
    }
}
