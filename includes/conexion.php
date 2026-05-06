<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "restaurante";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Error en la conexión: " . $conn->connect_error);
}
?>
         