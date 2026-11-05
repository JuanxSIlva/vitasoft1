<?php
session_start();
require_once "config/conexion.php";

// Validar que el código haya sido verificado previamente
if (!isset($_SESSION['codigo_verificado'])) {
    header("Location: recuperar.php");
    exit;
}
if ($_SESSION['codigo_verificado'] !== true) {
    header("Location: recuperar.php");
    exit;
}

// Detectar si la columna de contraseña se llama 'password' o 'contrasena'
$cols_u = [];
$res_cols =$conexion->query("SHOW COLUMNS FROM usuarios");
if ($res_cols) {
    while ($r_c =$res_cols->fetch_assoc()) {
        $cols_u[] = strtolower($r_c['Field']);
    }
}
$col_pass_user = in_array('password',$cols_u, true) ? 'password' : 'contrasena';
$col_correo_user = in_array('email',$cols_u, true) ? 'email' : 'correo';

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['nueva_password'])) {
    $nueva_password = trim($_POST['nueva_password']);
    $email =$_SESSION['reset_email'];

    if (empty($nueva_password)) {$mensaje = "Por favor ingresa una nueva contraseña.";
    } else {
        // Encriptamos la contraseña por seguridad
        $password_hash = password_hash($nueva_password, PASSWORD_BCRYPT);

        $stmt =$conexion->prepare("UPDATE usuarios SET `$col_pass_user` = ? WHERE `$col_correo_user` = ?");
        $stmt->bind_param("ss", $password_hash,$email);
        
        if ($stmt->execute()) {
            // Limpiamos las sesiones de recuperación
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_codigo']);
            unset($_SESSION['codigo_verificado']);

            echo "<script>alert('¡Contraseña actualizada con éxito!'); window.location.href='index.php';</script>";
            exit;
        } else {
            $mensaje = "Error al actualizar la contraseña en la base de datos.";
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
    <title>VitaSoft - Nueva Contraseña</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #0284c7;
            --primary-hover: #0369a1;
            --bg-gradient-start: #f8fafc;
            --bg-gradient-end: #e2e8f0;
        }

        body {
            background: linear-gradient(135deg, var(--bg-gradient-start) 0%, var(--bg-gradient-end) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: #334155;
            margin: 0;
        }

        .card-custom {
            background: #ffffff;
            padding: 45px 35px;
            border-radius: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 440px;
            text-align: center;
            border: 1px solid rgba(226, 232, 240, 0.8);
            position: relative;
            overflow: hidden;
        }

        /* Línea decorativa superior estilo app médica */
        .card-custom::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #06b6d4, #0284c7);
        }

        .icon-badge {
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
            color: var(--primary-color);
            width: 72px;
            height: 72px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            box-shadow: 0 10px 15px -3px rgba(2, 132, 199, 0.15);
        }

        .brand-title {
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
            font-size: 26px;
            margin-bottom: 8px;
        }

        .text-subtitle {
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 28px;
        }

        .input-group {
            position: relative;
        }

        .form-control-lg {
            border-radius: 14px;
            border: 2px solid #cbd5e1;
            height: 54px;
            font-size: 16px;
            color: #1e293b;
            background-color: #f8fafc;
            transition: all 0.25s ease;
            padding-right: 50px;
        }

        .form-control-lg:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.12);
            background-color: #ffffff;
        }

        /* Botón del ojito integrado */
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            font-size: 20px;
            cursor: pointer;
            z-index: 10;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-password:hover {
            color: var(--primary-color);
        }

        .btn-custom {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 14px;
            border-radius: 14px;
            font-size: 16px;
            box-shadow: 0 10px 15px -3px rgba(2, 132, 199, 0.3);
            transition: all 0.25s ease;
            margin-top: 10px;
        }

        .btn-custom:hover {
            background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
            transform: translateY(-1px);
            box-shadow: 0 14px 20px -3px rgba(2, 132, 199, 0.4);
            color: white;
        }

        .alert-custom {
            border-radius: 12px;
            font-size: 13px;
            border: none;
            background-color: #ffeeec;
            color: #991b1b;
            padding: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
    </style>
</head>
<body>

<div class="card-custom">
    <div class="icon-badge">
        <i class="bi bi-key-fill"></i>
    </div>
    
    <h2 class="brand-title">VitaSoft</h2>
    <p class="text-subtitle">
        Por favor ingresa y confirma tu nueva contraseña segura para la cuenta.
    </p>

    <?php if($mensaje): ?>
        <div class="alert alert-custom">
            <i class="bi bi-exclamation-circle-fill"></i>
            <span><?php echo htmlspecialchars($mensaje); ?></span>
        </div>
    <?php endif; ?>

    <form action="cambiar_clave.php" method="POST">
        <div class="mb-3 input-group">
            <input type="password" name="nueva_password" id="nueva_password" class="form-control form-control-lg" placeholder="Nueva contraseña" required autofocus>
            <button type="button" class="toggle-password" id="toggleBtn" onclick="togglePasswordVisibility()">
                <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
        </div>
        <button type="submit" class="btn btn-custom w-100">Actualizar Contraseña</button>
    </form>
</div>

<script>
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('nueva_password');
        const eyeIcon = document.getElementById('eyeIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.classList.remove('bi-eye');
            eyeIcon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            eyeIcon.classList.remove('bi-eye-slash');
            eyeIcon.classList.add('bi-eye');
        }
    }
</script>

</body>
</html>