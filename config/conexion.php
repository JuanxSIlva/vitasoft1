<?php
$host = "localhost";
$db   = "vitasoft"; 
$user = "root"; 
$pass = ""; 

// 1. Conexión PDO (Usada exclusivamente por login.php)
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error de conexión PDO: " . $e->getMessage());
}

// 2. Conexión MySQLi (Usada por inicio.php, productos.php, categorias.php, etc.)
$conexion = new mysqli($host, $user, $pass, $db);
if ($conexion->connect_error) {
    die("Error de conexión MySQLi: " . $conexion->connect_error);
}
$conexion->set_charset("utf8mb4");
?>