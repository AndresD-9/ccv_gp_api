<?php
header("Content-Type: application/json");

// Conecta con tu BD de cPanel
$mysqli = new mysqli("localhost", "USUARIO_CPNL", "PASSWORD_CPNL", "NOMBRE_BD");

if ($mysqli->connect_errno) {
    echo json_encode(["error" => $mysqli->connect_error]);
    exit();
}

$result = $mysqli->query("SELECT id, nombre, email FROM usuarios");

$usuarios = [];
while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
}

echo json_encode($usuarios);
$mysqli->close();
?>