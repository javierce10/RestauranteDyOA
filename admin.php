<?php
session_start();
include('includes/conexion.php');

// Validar rol
if(!isset($_SESSION['rol']) || $_SESSION['rol'] != 'admin'){
    header('Location: index.php');
    exit();
}

// ====== MÉTRICAS DEL DASHBOARD ======

// Ventas de hoy
$res = $conn->query("SELECT IFNULL(SUM(total),0) as total FROM ventas WHERE DATE(fecha)=CURDATE()");
$ventas_hoy = $res->fetch_assoc()['total'];

// Pedidos de hoy
$res = $conn->query("SELECT COUNT(*) as total FROM pedidos WHERE DATE(fecha_hora)=CURDATE()");
$pedidos_hoy = $res->fetch_assoc()['total'];

// Productos registrados
$res = $conn->query("SELECT COUNT(*) as total FROM productos");
$productos_total = $res->fetch_assoc()['total'];

// Usuarios registrados
$res = $conn->query("SELECT COUNT(*) as total FROM usuarios");
$usuarios_total = $res->fetch_assoc()['total'];

//top productos
$top_productos = $conn->query("
    SELECT pr.nombre AS nombre, SUM(dp.cantidad) AS veces
    FROM detalle_pedido dp
    JOIN productos pr ON dp.producto_id = pr.id
    GROUP BY dp.producto_id
    ORDER BY veces DESC
    LIMIT 5
");

// Mesero top
$mesero_top = $conn->query("
    SELECT mesero_nombre, SUM(total) as total
    FROM pedidos
    WHERE DATE(fecha_hora)=CURDATE()
    GROUP BY mesero_nombre
    ORDER BY total DESC
    LIMIT 1
")->fetch_assoc();

// Últimas 5 ventas
$ultimas_ventas = $conn->query("
    SELECT pedido_id, total, metodo_pago, fecha
    FROM ventas
    ORDER BY fecha DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Admin - Panel</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI';background:linear-gradient(135deg,#667eea,#764ba2);padding:20px;}
.container{max-width:1400px;margin:auto;}
.header{background:white;padding:25px;border-radius:15px;margin-bottom:25px;display:flex;justify-content:space-between;align-items:center;}
.btn-nav{padding:12px 20px;border:none;border-radius:10px;color:white;text-decoration:none;font-weight:bold;margin-right:10px;}
.btn-usuarios{background:#667eea;}
.btn-productos{background:#f5576c;}
.btn-mesas{background:#00c6ff;}
.btn-ventas{background:#56ab2f;}
.btn-logout{background:#eb3349;}
.section{
    background:white;
    padding:25px;
    border-radius:15px;
    margin-bottom:25px;
    box-shadow:0 4px 15px rgba(0,0,0,0.2);
}
table{width:100%;border-collapse:collapse;margin-top:15px;}
th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left;}

.section{
    background:white;
    padding:25px 30px;
    border-radius:15px;
    box-shadow:0 4px 15px rgba(0,0,0,0.2);
    margin-bottom:25px;
}

.section h3{
    color:#667eea;
    margin-bottom:20px;
    font-size:22px;
    border-bottom:3px solid #667eea;
    padding-bottom:10px;
}

.dashboard-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
}

.card-metrica{
    background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
    color:white;
    padding:20px;
    border-radius:12px;
    box-shadow:0 4px 10px rgba(0,0,0,0.15);
}

.card-metrica h4{
    font-size:15px;
    opacity:.9;
    margin-bottom:8px;
}

.card-metrica .valor{
    font-size:26px;
    font-weight:700;
}

.tabla-dashboard{
    width:100%;
    border-collapse:collapse;
}

.tabla-dashboard th{
    text-align:left;
    padding:12px;
    background:#667eea;
    color:white;
}

.tabla-dashboard td{
    padding:12px;
    border-bottom:1px solid #eee;
}

.tabla-dashboard tr:hover{
    background:#f8f9fa;
}
</style>
</head>
<body>

<div class="container">
<div class="header">
    <h1>🎯 Panel de Administración</h1>
    <div>
        <a href="usuarios.php" class="btn-nav btn-usuarios">Usuarios</a>
        <a href="productos.php" class="btn-nav btn-productos">Productos</a>
        <a href="mesas.php" class="btn-nav btn-mesas">Mesas</a>
        <a href="ventas.php" class="btn-nav btn-ventas">Ventas</a>
        <a href="logout.php" class="btn-nav btn-logout">Salir</a>
    </div>
</div>

<!-- ✅ RESUMEN DEL DÍA -->
<div class="section">
    <h3>📊 Resumen del Día</h3>

    <div class="dashboard-grid">
        <div class="card-metrica">
            <h4>💰 Ventas de hoy</h4>
            <div class="valor">$<?php echo number_format($ventas_hoy,2); ?></div>
        </div>

        <div class="card-metrica">
            <h4>🧾 Pedidos realizados</h4>
            <div class="valor"><?php echo $pedidos_hoy; ?></div>
        </div>

        <div class="card-metrica">
            <h4>🍽️ Productos registrados</h4>
            <div class="valor"><?php echo $productos_total; ?></div>
        </div>

        <div class="card-metrica">
            <h4>👥 Usuarios registrados</h4>
            <div class="valor"><?php echo $usuarios_total; ?></div>
        </div>
    </div>
</div>

<!-- ✅ PRODUCTOS MÁS VENDIDOS -->
<div class="section">
    <h3>🔥 Productos Más Vendidos</h3>

    <table class="tabla-dashboard">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Veces pedido</th>
            </tr>
        </thead>
        <tbody>
            <?php while($p = $top_productos->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $p['nombre']; ?></td>
                    <td><?php echo $p['veces']; ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- ✅ ÚLTIMAS VENTAS -->
<div class="section">
    <h3>🕒 Últimas Ventas</h3>
    <table>
        <tr>
            <th>Pedido</th>
            <th>Total</th>
            <th>Método</th>
            <th>Fecha</th>
        </tr>
        <?php while($v = $ultimas_ventas->fetch_assoc()): ?>
        <tr>
            <td>#<?php echo $v['pedido_id']; ?></td>
            <td>$<?php echo $v['total']; ?></td>
            <td><?php echo $v['metodo_pago']; ?></td>
            <td><?php echo $v['fecha']; ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

</div>
</body>
</html>