<?php
session_start();
include('includes/conexion.php');

// Permitir admin y caja
if(!isset($_SESSION['rol']) || 
   ($_SESSION['rol'] != 'admin' && $_SESSION['rol'] != 'caja')){
    header('Location: login.php');
    exit();
}
if(!isset($_GET['pedido_id'])){
    echo "ID de pedido no proporcionado";
    exit();
}
$pedido_id = $_GET['pedido_id'];
// Obtener información del pedido
$res_pedido = $conn->query("SELECT * FROM pedidos WHERE id=$pedido_id");
if($res_pedido->num_rows==0){
    echo "Pedido no encontrado";
    exit();
}
$pedido = $res_pedido->fetch_assoc();
// Obtener detalles del pedido
$res_detalles = $conn->query("
    SELECT dp.cantidad, dp.precio_unitario, dp.notas, p.nombre
    FROM detalle_pedido dp
    JOIN productos p ON dp.producto_id = p.id
    WHERE dp.pedido_id=$pedido_id
");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ticket Pedido #<?php echo $pedido_id; ?></title>
    <style>
        body { font-family: monospace; }
        .ticket { width: 300px; margin: auto; border:1px solid #000; padding:10px; }
        .ticket h2 { text-align: center; margin: 5px 0; font-size: 18px; }
        .ticket h3, .ticket p { text-align: center; margin: 5px 0; }
        .ticket .direccion { font-size: 11px; text-align: center; margin: 3px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 2px; }
        .total { text-align: right; font-weight: bold; }
        .btn-imprimir { margin-top: 10px; padding:5px 10px; background:#4CAF50; color:white; border:none; cursor:pointer; }
        @media print {
            .btn-imprimir { display: none; }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <h2>RESTAURANTE</h2>
        <p class="direccion">Dirección SN, Centro</p>
        <p class="direccion">40000 Iguala de la Independencia</p>
        <hr>
        <p>Mesa: <?php echo $pedido['mesa']; ?></p>
        <p>Fecha: <?php echo $pedido['fecha_hora']; ?></p>
        <p>Pedido #<?php echo $pedido_id; ?></p>
        <hr>
        <table>  
            <tr>
                <th>Cant</th>
                <th>Producto</th>
                <th>Precio</th>
            </tr>
            <?php while($det = $res_detalles->fetch_assoc()): ?>
            <tr>
                <td><?php echo $det['cantidad']; ?></td>
                <td>
                    <?php echo $det['nombre']; ?>
                    <?php if($det['notas']) echo "<br><small>(".$det['notas'].")</small>"; ?>
                </td>
                <td>$<?php echo number_format($det['precio_unitario']*$det['cantidad'],2); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
        <hr>
        <p class="total">TOTAL: $<?php echo number_format($pedido['total'],2); ?></p>
        <hr>
        <p style="text-align:center; font-size:11px;">*Propina no incluida*</p>
        <p style="text-align:center; font-size:12px;">¡Gracias por su preferencia!</p>
        <button class="btn-imprimir" onclick="window.print()">Imprimir</button>
    </div>
</body>
</html>  