<?php
session_start();
require_once "config/conexion.php";

$mensaje = "";

// Si no hay un correo en sesión, lo devolvemos al inicio del flujo
if (!isset($_SESSION['reset_email'])) {
    header("Location: recuperar.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['codigo'])) {
    $codigo_ingresado = trim($_POST['codigo']);
    $codigo_guardado = $_SESSION['reset_codigo'] ?? '';

    if (empty($codigo_ingresado)) {
        $mensaje = "Por favor ingresa el código de verificación.";
    } elseif ($codigo_ingresado == $codigo_guardado) {
        // Código correcto: autorizamos el cambio de contraseña en sesión
        $_SESSION['codigo_verificado'] = true;
        header("Location: cambiar_clave.php");
        exit;
    } else {
        $mensaje = "El código ingresado es incorrecto. Verifícalo en tu correo.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VitaSoft - Verificar Código de Seguridad</title>
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

        .form-control-lg {
            border-radius: 14px;
            border: 2px solid #cbd5e1;
            height: 56px;
            font-size: 24px;
            letter-spacing: 8px;
            text-align: center;
            font-weight: 600;
            color: #1e293b;
            background-color: #f8fafc;
            transition: all 0.25s ease;
        }

        .form-control-lg:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.12);
            background-color: #ffffff;
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

        .back-link {
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .back-link:hover {
            color: var(--primary-color);
        }
    </style>
</head>
<body>

<div class="card-custom">
    <div class="icon-badge">
        <i class="bi bi-shield-lock-fill"></i>
    </div>
    
    <h2 class="brand-title">VitaSoft</h2>
    <p class="text-subtitle">
        Hemos enviado un código de verificación de 6 dígitos a tu bandeja de correo electrónico.
    </p>

    <?php if($mensaje): ?>
        <div class="alert alert-custom">
            <i class="bi bi-exclamation-circle-fill"></i>
            <span><?php echo htmlspecialchars($mensaje); ?></span>
        </div>
    <?php endif; ?>

    <form action="verificar_codigo.php" method="POST">
        <div class="mb-4">
            <input type="text" name="codigo" class="form-control form-control-lg" placeholder="• • • • • •" maxlength="6" autocomplete="off" required autofocus>
        </div>
        <button type="submit" class="btn btn-custom w-100">Verificar Código</button>
    </form>

    <div class="mt-4 pt-3 border-top border-light">
        <a href="recuperar.php" class="back-link">
            <i class="bi bi-arrow-left"></i> Reenviar código o cambiar correo
        </a>
    </div>
</div>

</body>
</html>