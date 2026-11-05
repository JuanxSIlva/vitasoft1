<?php
session_start();
require_once "config/conexion.php";

// ESTA LÍNEA ES OBLIGATORIA porque instalamos PHPMailer con Composer
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mensaje = "";

$cols_u = [];
$res_cols =$conexion->query("SHOW COLUMNS FROM usuarios");
if ($res_cols) {
    while ($r_c =$res_cols->fetch_assoc()) {
        $cols_u[] = strtolower($r_c['Field']);
    }
}
$col_correo_user = in_array('email',$cols_u, true) ? 'email' : 'correo';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    if (empty($email)) {$mensaje = "Por favor ingresa tu correo electrónico.";
    } else {
        $stmt =$conexion->prepare("SELECT id FROM usuarios WHERE `$col_correo_user` = ?");
        $stmt->bind_param("s", $email);$stmt->execute();
        $res =$stmt->get_result();

        if ($res && $res->num_rows > 0) {$codigo = rand(100000, 999999);

            $_SESSION['reset_email'] =$email;
            $_SESSION['reset_codigo'] =$codigo;

            // --- CONFIGURACIÓN SMTP REAL CON GMAIL ---
            $mail = new PHPMailer(true);

            try {
                $mail->SMTPDebug = 0;                      
                $mail->isSMTP();                                            
                $mail->Host       = 'smtp.gmail.com';$mail->SMTPAuth   = true;                                   
                $mail->Username   = 'sebastejada163@gmail.com';             
                $mail->Password   = 'audb xdgw sosm wbsn';                  // Tu contraseña de aplicación correcta
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
                $mail->Port       = 587;                                    

                $mail->setFrom('sebastejada163@gmail.com', 'Soporte VitaSoft');
                $mail->addAddress($email);                                  

                $mail->isHTML(true);                                  
                $mail->Subject = 'Codigo de recuperacion - VitaSoft';$mail->Body    = '<h2>Hola</h2><p>Has solicitado restablecer tu contraseña en VitaSoft.</p><p>Tu codigo de verificacion es: <b>' . $codigo . '</b></p>';
                $mail->AltBody = 'Tu codigo de verificacion en VitaSoft es: ' .$codigo;

                $mail->send();
                
                // Redirigimos a la pantalla para ingresar el código
                header("Location: verificar_codigo.php");
                exit;

            } catch (Exception $e) {$mensaje = "No se pudo enviar el correo. Error técnico: {$mail->ErrorInfo}";
            }

        } else {
            $mensaje = "El correo $email no se encuentra registrado en el sistema.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VitaSoft - Recuperar Cuenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #d1d5db; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { background: white; padding: 40px 35px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); width: 100%; max-width: 440px; text-align: center; }
        .icon-badge { background-color: #e8f0fe; color: #1a73e8; width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 28px; }
        .btn-blue { background-color: #0066ff; color: white; border: none; font-weight: 600; padding: 12px; border-radius: 10px; }
        .btn-blue:hover { background-color: #0052cc; color: white; }
        .form-control-lg { border-radius: 10px; border: 1px solid #0066ff; height: 50px; font-size: 16px; }
    </style>
</head>
<body>

<div class="card-custom">
    <div class="icon-badge"><i class="bi bi-shield-check"></i></div>
    <h3 class="fw-bold mb-1" style="color: #111827;">VitaSoft</h3>
    <p class="text-muted small mb-4"><i class="bi bi-capsule"></i> Recuperación de Cuenta</p>

    <?php if($mensaje): ?>
        <div class="alert alert-danger py-2 small mb-3"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <form action="recuperar.php" method="POST">
        <div class="mb-3">
            <input type="email" name="email" class="form-control form-control-lg text-center" placeholder="Tu correo registrado" required autofocus>
        </div>
        <button type="submit" class="btn btn-blue w-100 fs-6">Enviar código</button>
    </form>

    <div class="mt-4">
        <a href="index.php" class="text-decoration-none text-secondary small"><i class="bi bi-arrow-left"></i> Volver al Inicio</a>
    </div>
</div>

</body>
</html>