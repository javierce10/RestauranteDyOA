<?php
include('config.php');

if(!isset($_GET['mesa'])){
    exit("Mesa no especificada");
}

$mesa = $_GET['mesa'];

// 🔹 Obtener pedido activo de esa mesa
$stmt = $conn->prepare("
SELECT * FROM pedidos 
WHERE mesa=? AND estado='pendiente'
LIMIT 1
");
$stmt->bind_param("s", $mesa);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();

if(!$pedido){
    echo "<p>🟢 Mesa disponible</p>";
    exit();
}

// 🔹 Obtener productos del pedido
$stmt = $conn->prepare("
SELECT dp.*, pr.nombre 
FROM detalle_pedido dp
JOIN productos pr ON dp.producto_id = pr.id
WHERE dp.pedido_id=?
");
$stmt->bind_param("i", $pedido['id']);
$stmt->execute();
$res = $stmt->get_result();

// 🔥 MOSTRAR
echo "<h3>$mesa</h3>";
echo "<p>👤 Mesero: {$pedido['mesero_nombre']}</p>";
echo "<hr>";

$total = 0;

while($p = $res->fetch_assoc()){
    $subtotal = $p['precio'] * $p['cantidad'];
    $total += $subtotal;

    echo "
    <div style='margin-bottom:10px;'>
        {$p['nombre']} <br>
        {$p['cantidad']} x $ {$p['precio']} = <strong>$ ".number_format($subtotal,2)."</strong>
    </div>
    ";
}

echo "<hr>";
echo "<h3>Total: $ ".number_format($total,2)."</h3>";