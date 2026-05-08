<?php
$host = "localhost";
$user = "u204741856_userestaurante";
$pass = "SistemaDyOA123";
$db = "u204741856_restaurante";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Error en la conexión: " . $conn->connect_error);
}
?>
         
