<?php
session_start();

if (!isset($_SESSION["reset_email"])) {
    header("Location: index.php");
    exit;
}

$email = $_SESSION["reset_email"];
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $codigo_ingresado = trim($_POST["codigo"] ?? "");

    if ($codigo_ingresado == $_SESSION["reset_codigo"]) {
        header("Location: cambiar_clave.php");
        exit;
    } else {
        $error = "El código ingresado es incorrecto. Intenta de nuevo.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VitaSoft - Verificar Código</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #cbd5e1; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { background: white; padding: 40px 35px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 420px; text-align: center; }
        .icon-badge { background-color: #e0edff; color: #1a73e8; width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 28px; }
        .info-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px; display: flex; align-items: center; gap: 12px; text-align: left; margin-bottom: 20px; }
        .info-box i { font-size: 20px; color: #2563eb; }
        .info-box div { font-size: 13px; color: #64748b; line-height: 1.3; }
        .info-box strong { color: #0f172a; font-size: 13px; }
        .code-input { letter-spacing: 12px; font-size: 22px; text-align: center; font-weight: 600; border-radius: 10px; border: 1.5px solid #2563eb; height: 50px; color: #1e293b; }
        .btn-blue { background-color: #0d6efd; color: white; border: none; font-weight: 600; padding: 12px; border-radius: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; font-size: 15px; }
        .btn-blue:hover { background-color: #0b5ed7; color: white; }
    </style>
</head>
<body>

<div class="card-custom">
    <div class="icon-badge"><i class="bi bi-shield-check"></i></div>
    <h3 class="fw-bold mb-1" style="color: #1e293b;">VitaSoft</h3>
    <p class="text-muted small mb-4"><i class="bi bi-capsule"></i> Sistema de Droguería</p>

    <div class="info-box">
        <i class="bi bi-envelope-at"></i>
        <div>
            Hemos enviado un código de 6 dígitos a:<br>
            <strong><?php echo htmlspecialchars($email); ?></strong>
        </div>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger py-2 small mb-3"><?php echo $error; ?></div>
    <?php endif; ?>

    <form action="verificar_codigo.php" method="POST">
        <div class="mb-4">
            <input type="text" name="codigo" class="form-control code-input" placeholder="0 0 0 0 0 0" maxlength="6" required autofocus>
        </div>
        <button type="submit" class="btn btn-blue">
            <i class="bi bi-check-circle"></i> Verificar Código
        </button>
    </form>

    <div class="mt-4">
        <a href="index.php" class="text-decoration-none text-secondary small"><i class="bi bi-arrow-left"></i> Volver al Inicio</a>
    </div>
</div>

</body>
</html>