<?php
include "../../config/database/conexionBD.php";
include "../../auth/verificar_sesion.php";

// Cargar autoloader de Composer
require_once '../../vendor/autoload.php';

// Usar las clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

//este archivo es autorizacion_vent

requerirRol(['1', '2']); // Solo administradores y vendedores

$breadcrumb_items = ['Sector Ventas', 'Listado Ventas', 'Enviar a Contable'];
$item_urls = [
    $url_base . 'secciones/ventas/main.php',
    $url_base . 'secciones/ventas/index.php'
];

include $path_base . "components/head.php";

// Establecer la zona horaria de Paraguay/Asunción
date_default_timezone_set('America/Asuncion');

// CONFIGURACIÓN DE CORREOS
$configuracion_correos = [
    
];

$idVenta = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verificar que el ID de venta sea válido
if ($idVenta <= 0) {
    header("Location: " . $url_base . "secciones/venta/index.php?error=ID de venta inválido");
    exit();
}

// Obtener información de la venta
try {
    $sql = "SELECT v.*, u.nombre as nombre_vendedor 
            FROM public.sist_ventas_presupuesto v
            LEFT JOIN public.sist_ventas_usuario u ON v.id_usuario = u.id
            WHERE v.id = :id";
    $stmt = $conexion->prepare($sql);
    $stmt->bindParam(':id', $idVenta, PDO::PARAM_INT);
    $stmt->execute();
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        header("Location: " . $url_base . "secciones/ventas/index.php?error=Venta no encontrada");
        exit();
    }
} catch (PDOException $e) {
    header("Location: " . $url_base . "secciones/ventas/index.php?error=" . urlencode("Error al obtener información de la venta"));
    exit();
}

// Función para enviar correo personalizado
function enviarCorreoPersonalizado($venta, $descripcion, $datosCorreo, $imagenes, $configuracion, $nombreUsuario)
{
    // Verificar si las clases están disponibles
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return enviarConPHPMailerPersonalizado($venta, $descripcion, $datosCorreo, $imagenes, $configuracion, $nombreUsuario);
    } else {
        return enviarConMailBasicoPersonalizado($venta, $descripcion, $datosCorreo, $configuracion, $nombreUsuario);
    }
}

function enviarConPHPMailerPersonalizado($venta, $descripcion, $datosCorreo, $imagenes, $configuracion, $nombreUsuario)
{
    try {
        // Crear instancia de PHPMailer
        $mail = new PHPMailer(true);

        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = $configuracion['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $configuracion['smtp_username'];
        $mail->Password = $configuracion['smtp_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $configuracion['smtp_port'];
        $mail->CharSet = 'UTF-8';

        // Remitente
        $mail->setFrom($configuracion['from_email'], $configuracion['from_name']);

        // Destinatarios principales
        foreach ($datosCorreo['destinatarios'] as $destinatario) {
            if (!empty(trim($destinatario))) {
                $mail->addAddress(trim($destinatario));
            }
        }

        // Asunto del correo
        $mail->Subject = $datosCorreo['asunto'];

        // Determinar si usar plantilla HTML o contenido personalizado
        if ($datosCorreo['usar_plantilla']) {
            $cuerpoCorreo = generarPlantillaHTML($venta, $descripcion, $datosCorreo, $nombreUsuario);
        } else {
            $cuerpoCorreo = $datosCorreo['contenido_personalizado'];
        }

        $mail->isHTML($datosCorreo['formato_html']);
        $mail->Body = $cuerpoCorreo;

        // Si es texto plano, también configurar AltBody
        if (!$datosCorreo['formato_html']) {
            $mail->AltBody = strip_tags($cuerpoCorreo);
        }

        // Adjuntar imágenes
        if (!empty($imagenes)) {
            foreach ($imagenes as $imagen) {
                if (isset($imagen['tmp_name']) && !empty($imagen['tmp_name'])) {
                    $mail->addAttachment($imagen['tmp_name'], $imagen['name']);
                }
            }
        }

        $mail->send();

        return ['success' => true, 'message' => 'Correo enviado exitosamente'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error PHPMailer: ' . $e->getMessage()];
    }
}

function enviarConMailBasicoPersonalizado($venta, $descripcion, $datosCorreo, $configuracion, $nombreUsuario)
{
    try {
        // CONFIGURAR SMTP PARA GMAIL SIN PHPMAILER
        ini_set('SMTP', $configuracion['smtp_host']);
        ini_set('smtp_port', $configuracion['smtp_port']);
        ini_set('sendmail_from', $configuracion['from_email']);

        $asunto = $datosCorreo['asunto'];

        // Determinar contenido
        if ($datosCorreo['usar_plantilla']) {
            $mensaje = generarPlantillaTexto($venta, $descripcion, $datosCorreo, $nombreUsuario);
        } else {
            $mensaje = strip_tags($datosCorreo['contenido_personalizado']);
        }

        $headers = "From: {$configuracion['from_name']} <{$configuracion['from_email']}>\r\n";
        $headers .= "Reply-To: {$configuracion['from_email']}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        if ($datosCorreo['formato_html']) {
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }

        $destinatarios = implode(', ', $datosCorreo['destinatarios']);

        if (mail($destinatarios, $asunto, $mensaje, $headers)) {
            return ['success' => true, 'message' => 'Correo enviado exitosamente'];
        } else {
            return ['success' => false, 'message' => 'Error al enviar correo'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error al enviar correo: ' . $e->getMessage()];
    }
}

function generarPlantillaHTML($venta, $descripcion, $datosCorreo, $nombreUsuario)
{
    $montoFormateado =  number_format((float)$venta['monto_total'], 2, ',', '.');
    $condicion = $venta['es_credito'] ? 'Crédito' : 'Contado';
    $estadoDestino = $venta['es_credito'] ? 'Enviado a PCP' : 'En revision';

    return "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .info-box { background-color: #f8f9fa; border-left: 4px solid #007bff; padding: 15px; margin: 15px 0; }
            .highlight { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 5px; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>🏢 Sistema de Ventas - America TNT</h2>
            <p>Nueva venta enviada para revisión" . ($venta['es_credito'] ? ' - PCP' : ' contable') . "</p>
        </div>
        
        <div class='content'>
            " . $datosCorreo['mensaje_personalizado'] . "
            
            <h3>📋 Información de la Venta</h3>
            <table>
                <tr><th>Codigo Venta:</th><td>#{$venta['id']}</td></tr>
                <tr><th>Cliente:</th><td>{$venta['cliente']}</td></tr>
                <tr><th>Monto Total:</th><td><strong>{$montoFormateado}</strong></td></tr>
                <tr><th>Moneda:</th><td>{$venta['moneda']}</td></tr>
                <tr><th>Condición:</th><td><strong>{$condicion}</strong></td></tr>
                <tr><th>Estado:</th><td><strong>{$estadoDestino}</strong></td></tr>
                <tr><th>Vendedor:</th><td>{$venta['nombre_vendedor']}</td></tr>
                <tr><th>Fecha de Envío:</th><td>" . date('d/m/Y H:i:s') . "</td></tr>
                <tr><th>Enviado por:</th><td>{$nombreUsuario}</td></tr>
            </table>
            
            <div class='info-box'>
                <h4>📝 Descripción General:</h4>
                <p>" . nl2br(htmlspecialchars($descripcion)) . "</p>
            </div>
            
            <div class='info-box'>
                <h4>🔗 Acceso al Sistema:</h4>
                <p>Para revisar los detalles completos y procesar esta venta, accede al sistema de gestión: http://192.168.1.127/VENTAS/.</p>
            </div>
            
            <hr style='margin: 30px 0;'>
            <p style='color: #666; font-size: 12px;'>
                Este es un mensaje automático del Sistema de Ventas de America TNT.<br>
                Fecha y hora: " . date('d/m/Y H:i:s') . " (Hora de Paraguay)
            </p>
        </div>
    </body>
    </html>";
}

function generarPlantillaTexto($venta, $descripcion, $datosCorreo, $nombreUsuario)
{
    $simbolo = $venta['moneda'] === 'Dólares' ? 'U$D ' : '₲ ';
    $montoFormateado = $simbolo . number_format((float)$venta['monto_total'], 4, ',', '.');
    $condicion = $venta['es_credito'] ? 'Crédito' : 'Contado';
    $estadoDestino = $venta['es_credito'] ? 'Enviado a PCP' : 'En revision';

    return "SISTEMA DE VENTAS - AMERICA TNT\n" .
        "Nueva venta enviada para revisión" . ($venta['es_credito'] ? ' - PCP' : ' contable') . "\n\n" .
        strip_tags($datosCorreo['mensaje_personalizado']) . "\n\n" .
        "==================================================\n" .
        "INFORMACIÓN DE LA VENTA\n" .
        "==================================================\n" .
        "ID Venta: #{$venta['id']}\n" .
        "Cliente: {$venta['cliente']}\n" .
        "Monto Total: {$montoFormateado}\n" .
        "Condición: {$condicion}\n" .
        "Estado: {$estadoDestino}\n" .
        "Vendedor: {$venta['nombre_vendedor']}\n" .
        "Enviado por: {$nombreUsuario}\n" .
        "Fecha de Envío: " . date('d/m/Y H:i:s') . "\n\n" .
        "DESCRIPCIÓN GENERAL:\n" .
        $descripcion . "\n\n" .
        "==================================================\n" .
        "Este es un mensaje automático del Sistema de Ventas\n" .
        "Fecha y hora: " . date('d/m/Y H:i:s') . " (Hora de Paraguay)\n";
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descripcion = $_POST['descripcion'] ?? '';
    $enviarCorreo = isset($_POST['enviar_correo']) && $_POST['enviar_correo'] === '1';

    try {
        // Iniciar transacción
        $conexion->beginTransaction();

        // Obtener la fecha/hora actual en zona horaria de Paraguay
        $fechaParaguay = date('Y-m-d H:i:s');

        // Determinar el estado según el tipo de venta
        $nuevoEstado = $venta['es_credito'] ? 'Enviado a PCP' : 'En revision';
        $tipoSector = $venta['es_credito'] ? 'PCP' : 'contable';

        // Actualizar el estado de la venta
        $sqlUpdateEstado = "UPDATE public.sist_ventas_presupuesto SET estado = :estado WHERE id = :id";
        $stmtUpdateEstado = $conexion->prepare($sqlUpdateEstado);
        $stmtUpdateEstado->bindParam(':estado', $nuevoEstado, PDO::PARAM_STR);
        $stmtUpdateEstado->bindParam(':id', $idVenta, PDO::PARAM_INT);
        $stmtUpdateEstado->execute();

        // Verificar si ya existe una autorización previa
        $sqlVerificar = "SELECT id FROM public.sist_ventas_autorizaciones WHERE id_venta = :id_venta";
        $stmtVerificar = $conexion->prepare($sqlVerificar);
        $stmtVerificar->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
        $stmtVerificar->execute();

        $idAutorizacion = null;

        if ($stmtVerificar->rowCount() > 0) {
            // Si existe una autorización previa, actualizarla
            $autorizacionExistente = $stmtVerificar->fetch(PDO::FETCH_ASSOC);
            $idAutorizacion = $autorizacionExistente['id'];

            $sqlUpdate = "UPDATE public.sist_ventas_autorizaciones 
                          SET descripcion = :descripcion,
                              fecha_registro = :fecha_registro,
                              id_usuario = :id_usuario,
                              fecha_respuesta = NULL,
                              observaciones_contador = NULL,
                              id_contador = NULL,
                              estado_autorizacion = :estado_autorizacion
                          WHERE id_venta = :id_venta";

            $stmtUpdate = $conexion->prepare($sqlUpdate);
            $stmtUpdate->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmtUpdate->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
            $stmtUpdate->bindParam(':fecha_registro', $fechaParaguay, PDO::PARAM_STR);
            $stmtUpdate->bindParam(':id_usuario', $_SESSION['id'], PDO::PARAM_INT);
            $stmtUpdate->bindParam(':estado_autorizacion', $nuevoEstado, PDO::PARAM_STR);
            $stmtUpdate->execute();

            // Eliminar imágenes anteriores
            $sqlDeleteImages = "DELETE FROM public.sist_ventas_autorizaciones_imagenes WHERE id_autorizacion = :id_autorizacion";
            $stmtDeleteImages = $conexion->prepare($sqlDeleteImages);
            $stmtDeleteImages->bindParam(':id_autorizacion', $idAutorizacion, PDO::PARAM_INT);
            $stmtDeleteImages->execute();
        } else {
            // Si no existe, crear una nueva autorización
            $sqlInsert = "INSERT INTO public.sist_ventas_autorizaciones 
                          (id_venta, descripcion, id_usuario, estado_autorizacion, fecha_registro) 
                          VALUES (:id_venta, :descripcion, :id_usuario, :estado_autorizacion, :fecha_registro)
                          RETURNING id";

            $stmtInsert = $conexion->prepare($sqlInsert);
            $stmtInsert->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmtInsert->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
            $stmtInsert->bindParam(':fecha_registro', $fechaParaguay, PDO::PARAM_STR);
            $stmtInsert->bindParam(':id_usuario', $_SESSION['id'], PDO::PARAM_INT);
            $stmtInsert->bindParam(':estado_autorizacion', $nuevoEstado, PDO::PARAM_STR);
            $stmtInsert->execute();

            $result = $stmtInsert->fetch(PDO::FETCH_ASSOC);
            $idAutorizacion = $result['id'];
        }

        // Array para almacenar información de las imágenes para el correo
        $imagenesParaCorreo = [];

        // Procesar las imágenes múltiples
        if (isset($_FILES['imagenes']) && is_array($_FILES['imagenes']['name'])) {
            $totalImagenes = count($_FILES['imagenes']['name']);

            for ($i = 0; $i < $totalImagenes; $i++) {
                if ($_FILES['imagenes']['error'][$i] === UPLOAD_ERR_OK) {
                    $nombreimg = $_FILES['imagenes']['name'][$i];
                    $tipoimg = $_FILES['imagenes']['type'][$i];
                    $tmpName = $_FILES['imagenes']['tmp_name'][$i];
                    $descripcionImagen = $_POST['descripcion_imagen'][$i] ?? '';

                    // Validar tipo de archivo
                    $extension = strtolower(pathinfo($nombreimg, PATHINFO_EXTENSION));
                    $tiposPermitidos = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

                    if (in_array($extension, $tiposPermitidos)) {
                        // Leer el archivo de imagen
                        $imgData = file_get_contents($tmpName);
                        $base64img = base64_encode($imgData);

                        // Insertar la imagen en la tabla de imágenes
                        $sqlInsertImage = "INSERT INTO public.sist_ventas_autorizaciones_imagenes 
                                          (id_autorizacion, nombre_archivo, tipo_archivo, imagen, base64_imagen, descripcion_imagen, orden_imagen) 
                                          VALUES (:id_autorizacion, :nombre_archivo, :tipo_archivo, :imagen, :base64_imagen, :descripcion_imagen, :orden_imagen)";

                        $stmtInsertImage = $conexion->prepare($sqlInsertImage);
                        $ordenImagen = $i + 1; // Crear variable para bindParam
                        $stmtInsertImage->bindParam(':id_autorizacion', $idAutorizacion, PDO::PARAM_INT);
                        $stmtInsertImage->bindParam(':nombre_archivo', $nombreimg, PDO::PARAM_STR);
                        $stmtInsertImage->bindParam(':tipo_archivo', $tipoimg, PDO::PARAM_STR);
                        $stmtInsertImage->bindParam(':imagen', $imgData, PDO::PARAM_LOB);
                        $stmtInsertImage->bindParam(':base64_imagen', $base64img, PDO::PARAM_STR);
                        $stmtInsertImage->bindParam(':descripcion_imagen', $descripcionImagen, PDO::PARAM_STR);
                        $stmtInsertImage->bindParam(':orden_imagen', $ordenImagen, PDO::PARAM_INT);
                        $stmtInsertImage->execute();

                        // Agregar al array para el correo
                        $imagenesParaCorreo[] = [
                            'name' => $nombreimg,
                            'tmp_name' => $tmpName,
                            'type' => $tipoimg,
                            'descripcion' => $descripcionImagen
                        ];
                    }
                }
            }
        }

        try {
            $accionRealizada = $venta['es_credito'] ? 'Enviar al PCP' : 'Enviar al sector contable';

            $sqlHistorial = "INSERT INTO public.sist_ventas_historial_acciones 
                            (id_venta, id_usuario, sector, accion, fecha_accion, observaciones, estado_resultante)
                            VALUES (:id_venta, :id_usuario, :sector, :accion, :fecha_accion, :observaciones, :estado_resultante)";

            $stmtHistorial = $conexion->prepare($sqlHistorial);
            $stmtHistorial->bindParam(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmtHistorial->bindParam(':id_usuario', $_SESSION['id'], PDO::PARAM_INT);
            $stmtHistorial->bindValue(':sector', 'Ventas', PDO::PARAM_STR);
            $stmtHistorial->bindParam(':accion', $accionRealizada, PDO::PARAM_STR);
            $stmtHistorial->bindParam(':fecha_accion', $fechaParaguay, PDO::PARAM_STR);
            $stmtHistorial->bindParam(':observaciones', $descripcion, PDO::PARAM_STR);
            $stmtHistorial->bindParam(':estado_resultante', $nuevoEstado, PDO::PARAM_STR);
            $stmtHistorial->execute();
        } catch (Exception $eHistorial) {
            // No fallar si hay error en historial, solo logearlo
        }

        // ENVIAR CORREO ELECTRÓNICO (solo si está habilitado)
        $resultadoCorreo = ['success' => true, 'message' => 'No se envió correo (deshabilitado)'];

        if ($enviarCorreo) {
            // Obtener configuración del emisor seleccionado
            $emisorSeleccionado = $_POST['emisor_correo'] ?? 'ventas';
            $configEmisor = $configuracion_correos['emisores_disponibles'][$emisorSeleccionado];

            // Preparar datos del correo
            $datosCorreo = [
                'destinatarios' => array_filter(array_map('trim', explode(',', $_POST['email_destinatarios'] ?? ''))),
                'asunto' => $_POST['email_asunto'] ?? '',
                'mensaje_personalizado' => $_POST['email_mensaje_personalizado'] ?? '',
                'usar_plantilla' => isset($_POST['usar_plantilla']) && $_POST['usar_plantilla'] === '1',
                'formato_html' => isset($_POST['formato_html']) && $_POST['formato_html'] === '1',
                'contenido_personalizado' => $_POST['contenido_personalizado'] ?? ''
            ];

            $resultadoCorreo = enviarCorreoPersonalizado(
                $venta,
                $descripcion,
                $datosCorreo,
                $imagenesParaCorreo,
                $configEmisor,
                $_SESSION['nombre']
            );
        }

        // Confirmar transacción
        $conexion->commit();

        // Redirigir con mensaje de éxito (incluir info del correo)
        $mensaje = "Venta enviada al sector {$tipoSector} correctamente";
        if ($enviarCorreo) {
            if ($resultadoCorreo['success']) {
                $mensaje .= " y correo de notificación enviado";
            } else {
                $mensaje .= " (Error al enviar correo de notificación: " . $resultadoCorreo['message'] . ")";
            }
        }

        header("Location: " . $url_base . "secciones/ventas/index.php?mensaje=" . urlencode($mensaje));
        exit();
    } catch (PDOException $e) {
        // Si hay error, revertir transacción
        $conexion->rollBack();
        $error = "Error al guardar la información: " . $e->getMessage();
    }
}
?>
<body>
    <?php include $path_base . "components/navbar.php"; ?>
    <div class="container-fluid my-4">
        <div class="row">
            <div class="col-md-12 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-paper-plane me-2"></i>Enviar al Sector <?php echo $venta['es_credito'] ? 'PCP' : 'Contable'; ?> - Venta #<?php echo $idVenta; ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Alerta informativa sobre el tipo de venta -->
                        <?php if ($venta['es_credito']): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Venta a Crédito:</strong> Esta venta será enviada directamente al PCP para su revisión y aprobación.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Venta al Contado:</strong> Esta venta será enviada al sector contable para su revisión.
                            </div>
                        <?php endif; ?>

                        <!-- Información de la venta -->
                        <div class="card mb-4 bg-light">
                            <div class="card-body">
                                <h5 class="card-title">Información de la Venta</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Cliente:</strong> <?php echo htmlspecialchars($venta['cliente']); ?></p>
                                        <p><strong>Monto Total:</strong>
                                            <?php
                                            $simbolo = $venta['moneda'] === 'Dólares' ? 'U$D ' : '₲ ';
                                            echo $simbolo . number_format((float)$venta['monto_total'], 4, ',', '.');
                                            ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Vendedor:</strong> <?php echo htmlspecialchars($venta['nombre_vendedor']); ?></p>
                                        <p><strong>Condición:</strong>
                                            <span class="badge <?php echo $venta['es_credito'] ? 'bg-warning text-dark' : 'bg-success'; ?>">
                                                <?php echo $venta['es_credito'] ? 'Crédito' : 'Contado'; ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Formulario de autorización -->
                        <form method="POST" enctype="multipart/form-data" id="autorizacionForm">
                            <!-- SECCIÓN DE CONFIGURACIÓN DE CORREO -->
                            <div class="card mb-4">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-envelope me-2"></i>Configuración de Correo Electrónico
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <!-- Switch para habilitar/deshabilitar correo -->
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="enviar_correo" name="enviar_correo" value="1" checked onchange="toggleCorreoSection()">
                                        <label class="form-check-label" for="enviar_correo">
                                            <strong>Enviar notificación por correo electrónico</strong>
                                        </label>
                                    </div>

                                    <!-- Sección de configuración de correo (visible por defecto) -->
                                    <div id="correoConfigSection" style="display: block;">

                                        <!-- Selector de emisor -->
                                        <div class="mb-3">
                                            <label for="emisor_correo" class="form-label">
                                                <i class="fas fa-user-tie me-1"></i>Enviar desde * (Selecciona)
                                            </label>
                                            <select class="form-select" id="emisor_correo" name="emisor_correo">
                                                <?php foreach ($configuracion_correos['emisores_disponibles'] as $key => $emisor): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo $key === 'ventas' ? 'selected' : ''; ?>>
                                                        <?php echo $emisor['display_name']; ?> (<?php echo $emisor['from_email']; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Destinatarios -->
                                        <div class="mb-3">
                                            <label for="email_destinatarios" class="form-label">
                                                <i class="fas fa-users me-1"></i>Destinatarios *
                                            </label>
                                            <div class="input-group">
                                                <textarea class="form-control" id="email_destinatarios" name="email_destinatarios" rows="3"
                                                    placeholder="correo1@ejemplo.com, correo2@ejemplo.com, correo3@ejemplo.com"><?php echo implode(', ', array_keys($configuracion_correos['correos_predefinidos'])); ?></textarea>
                                                <button type="button" class="btn btn-outline-secondary" onclick="mostrarCorreosPredefinidos()">
                                                    <i class="fas fa-address-book"></i>
                                                </button>
                                            </div>
                                            <div class="form-text">Separar múltiples correos con comas. Puedes enviar a varios destinatarios a la vez.</div>
                                        </div>
                                        <!-- Asunto -->
                                        <div class="mb-3">
                                            <label for="email_asunto" class="form-label">
                                                <i class="fas fa-tag me-1"></i>Asunto del Correo *
                                            </label>
                                            <input type="text" class="form-control" id="email_asunto" name="email_asunto"
                                                value="Nueva Venta para Revisión <?php echo $venta['es_credito'] ? '- PCP' : '- Contable'; ?> - Venta #<?php echo $idVenta; ?> - <?php echo htmlspecialchars($venta['cliente']); ?>"
                                                placeholder="Asunto del correo">
                                        </div>

                                        <!-- Opciones de formato -->
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="usar_plantilla" name="usar_plantilla" value="1" checked onchange="toggleContenidoPersonalizado()">
                                                    <label class="form-check-label" for="usar_plantilla">
                                                        Usar plantilla automática
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="formato_html" name="formato_html" value="1" checked>
                                                    <label class="form-check-label" for="formato_html">
                                                        Formato HTML
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Mensaje personalizado (cuando usa plantilla) -->
                                        <div class="mb-3" id="mensajePersonalizadoDiv">
                                            <label for="email_mensaje_personalizado" class="form-label">
                                                <i class="fas fa-edit me-1"></i>Mensaje Personalizado (se agregará al inicio del correo)
                                            </label>
                                            <textarea class="form-control" id="email_mensaje_personalizado" name="email_mensaje_personalizado" rows="3"
                                                placeholder="Mensaje personalizado que aparecerá al inicio del correo..."></textarea>
                                        </div>

                                        <!-- Contenido completamente personalizado (cuando no usa plantilla) -->
                                        <div class="mb-3" id="contenidoPersonalizadoDiv" style="display: none;">
                                            <label for="contenido_personalizado" class="form-label">
                                                <i class="fas fa-code me-1"></i>Contenido Completamente Personalizado
                                            </label>
                                            <textarea class="form-control" id="contenido_personalizado" name="contenido_personalizado" rows="8"
                                                placeholder="Escriba aquí el contenido completo del correo..."></textarea>
                                            <div class="form-text">
                                                <strong>Variables disponibles:</strong> {CLIENTE}, {MONTO}, {VENDEDOR}, {ID_VENTA}, {FECHA}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">
                                    <i class="fas fa-comment-alt me-1"></i>Descripción General
                                </label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="4"
                                    placeholder="Agregue una descripción o nota adicional para el sector <?php echo $venta['es_credito'] ? 'PCP' : 'contable'; ?> y para el final del correo..."></textarea>
                            </div>

                            <!-- Sección de imágenes múltiples -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label">
                                        <i class="fas fa-images me-1"></i>Adjuntar Imágenes o Documentos (Opcional)
                                    </label>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="agregarImagen()">
                                        <i class="fas fa-plus me-1"></i>Agregar
                                    </button>
                                </div>

                                <div id="imagenesContainer">
                                    <!-- Las imágenes se agregarán aquí dinámicamente -->
                                </div>

                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>Formatos aceptados: JPG, PNG, GIF, PDF. Tamaño máximo: 5MB por archivo. Las imágenes se adjuntarán al correo electrónico.
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="<?php echo $url_base; ?>secciones/ventas/index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Volver
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Enviar al Sector <?php echo $venta['es_credito'] ? 'PCP' : 'Contable'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para correos predefinidos -->
    <div class="modal fade" id="correosPredefinidosModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Seleccionar Correos Predefinidos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php foreach ($configuracion_correos['correos_predefinidos'] as $email => $nombre): ?>
                        <div class="form-check">
                            <input class="form-check-input correo-predefinido" type="checkbox" value="<?php echo $email; ?>" id="correo_<?php echo md5($email); ?>">
                            <label class="form-check-label" for="correo_<?php echo md5($email); ?>">
                                <strong><?php echo $nombre; ?></strong><br>
                                <small class="text-muted"><?php echo $email; ?></small>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="agregarCorreosSeleccionados()">Agregar Seleccionados</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let contadorImagenes = 0;

        function toggleCorreoSection() {
            const checkbox = document.getElementById('enviar_correo');
            const section = document.getElementById('correoConfigSection');
            section.style.display = checkbox.checked ? 'block' : 'none';
        }

        function toggleContenidoPersonalizado() {
            const usarPlantilla = document.getElementById('usar_plantilla').checked;
            document.getElementById('mensajePersonalizadoDiv').style.display = usarPlantilla ? 'block' : 'none';
            document.getElementById('contenidoPersonalizadoDiv').style.display = usarPlantilla ? 'none' : 'block';
        }

        function mostrarCorreosPredefinidos() {
            // Limpiar selecciones previas
            document.querySelectorAll('.correo-predefinido').forEach(el => el.checked = false);
            const modal = new bootstrap.Modal(document.getElementById('correosPredefinidosModal'));
            modal.show();
        }

        function agregarCorreosSeleccionados() {
            const correosSeleccionados = [];
            document.querySelectorAll('.correo-predefinido:checked').forEach(el => {
                correosSeleccionados.push(el.value);
            });

            if (correosSeleccionados.length > 0) {
                const campoDestino = document.getElementById('email_destinatarios');
                const valorActual = campoDestino.value.trim();
                const nuevosCorreos = correosSeleccionados.join(', ');

                if (valorActual) {
                    campoDestino.value = valorActual + ', ' + nuevosCorreos;
                } else {
                    campoDestino.value = nuevosCorreos;
                }
            }

            bootstrap.Modal.getInstance(document.getElementById('correosPredefinidosModal')).hide();
        }

        function agregarImagen() {
            contadorImagenes++;
            const container = document.getElementById('imagenesContainer');

            const imagenDiv = document.createElement('div');
            imagenDiv.className = 'card mb-3';
            imagenDiv.id = `imagen-${contadorImagenes}`;

            imagenDiv.innerHTML = `
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-image me-2"></i>Archivo ${contadorImagenes}
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarImagen(${contadorImagenes})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Seleccionar archivo</label>
                            <input type="file" class="form-control" name="imagenes[]" 
                                   accept="image/*,.pdf" onchange="previsualizarImagen(this, ${contadorImagenes})">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Descripción de la imagen (Opcional)</label>
                            <textarea class="form-control" name="descripcion_imagen[]" rows="3" 
                                      placeholder="Describe esta imagen..."></textarea>
                        </div>
                    </div>
                    <div class="mt-3" id="preview-${contadorImagenes}">
                        <!-- Vista previa aparecerá aquí -->
                    </div>
                </div>
            `;

            container.appendChild(imagenDiv);
        }

        function eliminarImagen(id) {
            const elemento = document.getElementById(`imagen-${id}`);
            if (elemento) {
                elemento.remove();
            }
        }

        function previsualizarImagen(input, id) {
            const file = input.files[0];
            const previewContainer = document.getElementById(`preview-${id}`);

            if (file) {
                const fileType = file.type;
                const fileName = file.name;

                if (fileType.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewContainer.innerHTML = `
                            <div class="text-center">
                                <img src="${e.target.result}" 
                                     style="max-width: 100%; max-height: 300px;" 
                                     class="img-thumbnail">
                                <p class="mt-2 mb-0"><small class="text-muted">${fileName}</small></p>
                            </div>
                        `;
                    }
                    reader.readAsDataURL(file);
                } else if (fileType === 'application/pdf') {
                    previewContainer.innerHTML = `
                        <div class="text-center">
                            <i class="fas fa-file-pdf" style="font-size: 80px; color: #dc3545;"></i>
                            <p class="mt-2 mb-0">
                                <strong>${fileName}</strong><br>
                                <small class="text-muted">Tamaño: ${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                            </p>
                        </div>
                    `;
                }
            } else {
                previewContainer.innerHTML = '';
            }
        }

        // Validación del formulario con indicador de carga
        document.getElementById('autorizacionForm').addEventListener('submit', function(e) {
            const enviarCorreo = document.getElementById('enviar_correo').checked;

            if (enviarCorreo) {
                const destinatarios = document.getElementById('email_destinatarios').value.trim();
                const asunto = document.getElementById('email_asunto').value.trim();

                if (!destinatarios) {
                    e.preventDefault();
                    alert('Por favor, ingrese al menos un destinatario para el correo.');
                    return false;
                }

                if (!asunto) {
                    e.preventDefault();
                    alert('Por favor, ingrese el asunto del correo.');
                    return false;
                }
            }

            // Mostrar indicador de carga después de un pequeño delay
            setTimeout(mostrarCargando, 100);
        });

        function mostrarCargando() {
            const submitBtn = document.querySelector('button[type="submit"]');

            // Deshabilitar botón y mostrar spinner
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
    Enviando... esto puede tardar unos segundos
    `;
        }

        // Agregar una imagen por defecto al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            agregarImagen();
        });
    </script>

    <style>
        .card-header {
            border-bottom: 1px solid #dee2e6;
        }

        .card-body img {
            cursor: zoom-in;
        }

        #imagenesContainer .card {
            border: 2px dashed #dee2e6;
            transition: border-color 0.3s;
        }

        #imagenesContainer .card:hover {
            border-color: #007bff;
        }

        .btn-sm:hover {
            transform: scale(1.05);
            transition: transform 0.2s;
        }

        .alert-info {
            border-left: 4px solid #0dcaf0;
        }

        .form-check-label {
            cursor: pointer;
        }

        .input-group .btn {
            border-left: 0;
        }

        #correoConfigSection {
            border: 1px solid #e9ecef;
            border-radius: 0.375rem;
            padding: 1rem;
            background-color: #f8f9fa;
        }
    </style>
</body>

</html>