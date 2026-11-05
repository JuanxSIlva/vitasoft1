<?php
session_start();
include("conexion.php"); // Tu archivo de conexión a la base de datos

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);

    // Busca si el correo está registrado en la BD
    $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        // Genera el código aleatorio de 6 dígitos
        $codigo = rand(100000, 999999);

        // Guarda el correo y el código en la sesión
        $_SESSION["reset_email"] = $email;
        $_SESSION["reset_codigo"] = $codigo;

        // Lo manda a la pantalla de digitar el código
        header("Location: verificar_codigo.php");
        exit;
    } else {
        echo "<script>alert('El correo no se encuentra registrado.'); window.location.href='index.php';</script>";
    }
}
?>