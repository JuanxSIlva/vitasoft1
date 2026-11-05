<?php
session_start();
if (isset($_SESSION["usuario_id"])) { 
    header("Location: inicio.php"); 
    exit; 
}
$error = $_GET["error"] ?? "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - VitaSoft</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
        }

        /* Barra Superior Azul */
        .top-navbar {
            background-color: #0b5ed7;
            color: #ffffff;
            padding: 0.6rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .top-navbar .brand-logo {
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Contenedor Principal Centrado */
        .main-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        /* Tarjeta Dividida Principal */
        .login-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 920px;
            overflow: hidden;
            border: none;
        }

        /* Panel Izquierdo */
        .left-panel {
            background: linear-gradient(180deg, #eef5ff 0%, #dbeafe 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            padding: 2.5rem 1.5rem 0rem 1.5rem;
            text-align: center;
            position: relative;
        }
        .logo-title {
            color: #0d6efd;
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 0.2rem;
        }
        
        /* Línea Verde con Punto */
        .green-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin: 0.5rem 0 1rem 0;
        }
        .green-line {
            height: 3px;
            background-color: #198754;
            width: 120px;
            position: relative;
            border-radius: 2px;
        }
        .green-dot {
            width: 12px;
            height: 12px;
            background-color: #198754;
            border-radius: 50%;
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
        }

        .subtitle-main {
            color: #0b2545;
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 0.2rem;
        }
        .subtitle-sub {
            color: #0d6efd;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .meds-img {
            max-width: 85%;
            height: auto;
            margin-top: 1.5rem;
            object-fit: contain;
        }

        /* Panel Derecho */
        .right-panel {
            padding: 3rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .avatar-icon {
            width: 55px;
            height: 55px;
            background-color: #e0edff;
            color: #0d6efd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin: 0 auto 1rem auto;
        }

        .form-control {
            border-radius: 8px;
            padding: 0.65rem 0.85rem;
            font-size: 0.95rem;
            background-color: #f8fafc;
            border: 1px solid #dee2e6;
        }
        .form-control:focus {
            background-color: #ffffff;
        }

        .input-group-text {
            border-radius: 8px 0 0 8px;
            background-color: #f8fafc;
            border-right: none;
        }
        .input-group .form-control {
            border-left: none;
        }

        .btn-toggle-eye {
            border-radius: 0 8px 8px 0;
            background-color: #f8fafc;
            border: 1px solid #dee2e6;
            border-left: none;
            color: #6c757d;
            cursor: pointer;
        }
        .btn-toggle-eye:hover {
            color: #0d6efd;
        }

        .btn-primary-custom {
            background-color: #0d6efd;
            border: none;
            border-radius: 8px;
            padding: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            transition: background 0.2s;
        }
        .btn-primary-custom:hover {
            background-color: #0b5ed7;
        }
    </style>
</head>
<body>

    <!-- Header Azul -->
    <div class="top-navbar">
        <div class="brand-logo">
            <i class="bi bi-mortarboard-fill"></i> VitaSoft
        </div>
        <div class="small">
            <i class="bi bi-geo-alt-fill"></i> Teruel, Huila - Colombia
        </div>
    </div>

    <!-- Contenido Centrado -->
    <div class="main-container">
        <div class="login-card">
            <div class="row g-0">
                
                <!-- Panel Izquierdo: Branding, Logos e Imagen -->
                <div class="col-md-6 left-panel">
                    <div>
                        <!-- Logo del Mortero -->
                        <img src="img/logo.png" alt="Logo VitaSoft" class="img-fluid mb-2" style="max-width: 110px;">

                        <h1 class="logo-title">VitaSoft</h1>
                        
                        <!-- Línea Verde Decorativa -->
                        <div class="green-divider">
                            <div class="green-line">
                                <div class="green-dot"></div>
                            </div>
                        </div>

                        <div class="subtitle-main">Sistema de Inventario</div>
                        <div class="subtitle-sub">para Droguerías</div>
                    </div>

                    <!-- Imagen de Medicamentos -->
                    <img src="img/medicina.png" alt="Medicamentos" class="meds-img">
                </div>

                <!-- Panel Derecho: Formulario de Login -->
                <div class="col-md-6 right-panel">
                    <div class="avatar-icon">
                        <i class="bi bi-person"></i>
                    </div>
                    <h3 class="fw-bold text-center mb-1">Iniciar Sesión</h3>
                    <p class="text-muted small text-center mb-4">Ingrese sus credenciales para acceder al sistema</p>

                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="login.php">
                        <div class="mb-3">
                            <label class="form-label small text-secondary fw-semibold">Usuario</label>
                            <div class="input-group">
                                <span class="input-group-text text-secondary"><i class="bi bi-person"></i></span>
                                <input type="text" name="usuario" class="form-control" placeholder="Ingrese su usuario" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-secondary fw-semibold">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text text-secondary"><i class="bi bi-lock"></i></span>
                                <input type="password" id="passwordInput" name="password" class="form-control" placeholder="Ingrese su contraseña" required>
                                <button class="btn btn-toggle-eye" type="button" id="togglePassword">
                                    <i class="bi bi-eye-slash" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="text-end mb-4">
                          <a href="#" data-bs-toggle="modal" data-bs-target="#modalRecuperar">¿Has olvidado tu contraseña?</a>
                        </div>

                        <button type="submit" class="btn btn-primary btn-primary-custom w-100 shadow-sm mb-3">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión
                        </button>
                    </form>

                </div>

            </div>
        </div>
    </div>

    <!-- Script para Mostrar/Ocultar Contraseña con el Ojito -->
    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('passwordInput');
        const eyeIcon = document.getElementById('eyeIcon');

        togglePassword.addEventListener('click', function () {
            // Cambiar entre tipo password y text
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Cambiar el ícono del ojo
            eyeIcon.classList.toggle('bi-eye');
            eyeIcon.classList.toggle('bi-eye-slash');
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Modal Ventana Flotante Recuperar Contraseña -->
<div class="modal fade" id="modalRecuperar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title font-weight-bold">Recuperar Contraseña</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
      <form action="recuperar.php" method="POST">
    <div class="mb-3">
        <label class="form-label text-secondary fw-bold">Correo Electrónico</label>
        <input type="email" name="email" class="form-control form-control-lg" placeholder="ejemplo@vitasoft.com" required>
    </div>
    <button type="submit" class="btn btn-primary btn-lg w-100 mt-2">Enviar instrucciones</button>
</form>
      </div>
    </div>
  </div>
</div>

</body>
</html>