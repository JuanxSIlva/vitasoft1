<?php
session_start();
require_once "config/conexion.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario  = trim($_POST["usuario"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if (empty($usuario) || empty($password)) {
        header("Location: index.php?error=" . urlencode("Isuwerte ken isumite amin a campo."));
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = :usuario LIMIT 1");
        $stmt->execute(['usuario' => $usuario]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['estado'] !== 'Activo') {
                header("Location: index.php?error=" . urlencode("Ti account-mo ket Inactivo (saan nga aktibo)."));
                exit;
            }

            if (password_verify($password, $user['password']) || $password === $user['password']) {
                $_SESSION["usuario_id"]     = $user['id'];
                $_SESSION["usuario_nombre"] = $user['nombre'];
                $_SESSION["usuario_rol"]    = $user['rol_id'];

                $updateStmt = $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id");
                $updateStmt->execute(['id' => $user['id']]);

                header("Location: inicio.php");
                exit;
            } else {
                header("Location: index.php?error=" . urlencode("Madi ti usuario wenno password."));
                exit;
            }
        } else {
            header("Location: index.php?error=" . urlencode("Madi ti usuario wenno password."));
            exit;
        }
    } catch (PDOException $e) {
        header("Location: index.php?error=" . urlencode("Biddut iti consulta: " . $e->getMessage()));
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
?>