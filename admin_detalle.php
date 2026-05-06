<?php
include('config.php'); // SE AGREGA ESTO

if(!isset($_SESSION['rol']) || 
   ($_SESSION['rol'] != 'admin' && $_SESSION['rol'] != 'caja')){
    header('Location: index.php');
    exit();
}

// Validar que venga el ID del pedido
if(!isset($_GET['pedido_id'])){
    echo "Pedido no especificado.";
    exit();
}

$pedido_id = $_GET['pedido_id'];

// Obtener información del pedido
$res_pedido = $conn->query("SELECT * FROM pedidos WHERE id=$pedido_id");
if($res_pedido->num_rows == 0){
    echo "Pedido no encontrado.";
    exit();
}
$pedido = $res_pedido->fetch_assoc();

// Obtener detalle de los productos
$res_detalle = $conn->query("
    SELECT dp.cantidad, dp.precio_unitario, dp.notas, pr.nombre
    FROM detalle_pedido dp
    JOIN productos pr ON dp.producto_id = pr.id
    WHERE dp.pedido_id=$pedido_id
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Pedido #<?php echo $pedido_id; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        .btn-volver {
    padding: 12px 22px;
    border-radius: 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-decoration: none;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
    display: inline-block;
}

.btn-volver:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.3);
}

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 25px 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            color: #667eea;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header .pedido-numero {
            color: #764ba2;
            font-weight: bold;
        }

        .btn-back {
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }

        .info-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            margin-bottom: 25px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .info-card {
            padding: 20px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 2px solid #667eea;
            transition: all 0.3s;
        }

        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(102, 126, 234, 0.3);
        }

        .info-card .label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .info-card .value {
            font-size: 22px;
            color: #667eea;
            font-weight: bold;
        }

        .estado-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 16px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .estado-pendiente {
            background: linear-gradient(135deg, #ffd89b 0%, #ff9a56 100%);
            color: white;
        }

        .estado-completado {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            color: white;
        }

        .estado-cancelado {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }

        .productos-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .section-title {
            color: #667eea;
            font-size: 22px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
        }

        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        td {
            padding: 15px 12px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .producto-nombre {
            font-weight: 600;
            color: #333;
        }

        .cantidad-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 13px;
        }

        .notas {
            color: #666;
            font-style: italic;
            font-size: 13px;
        }

        .notas.vacio {
            color: #999;
        }

        .total-pedido {
            margin-top: 25px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            text-align: right;
            color: white;
        }

        .total-pedido .label {
            font-size: 16px;
            margin-bottom: 8px;
            opacity: 0.9;
        }

        .total-pedido .value {
            font-size: 36px;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .header h1 {
                font-size: 22px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            th, td {
                padding: 10px 8px;
                font-size: 13px;
            }

            .total-pedido {
                text-align: center;
            }

            .total-pedido .value {
                font-size: 28px;
            }
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .btn-back {
                display: none;
            }

            .container {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>
                📋 Detalle del Pedido 
                <span class="pedido-numero">#<?php echo $pedido_id; ?></span>
            </h1>
<a href="<?php echo ($_SESSION['rol'] == 'caja') ? 'caja.php' : 'admin.php'; ?>" class="btn-volver">
    ← Volver al Panel
</a>    </div>

        <!-- Información del Pedido -->
        <div class="info-section">
            <h2 class="section-title">📊 Información General</h2>
            <div class="info-grid">
                <div class="info-card">
                    <div class="label">Mesa</div>
                    <div class="value"><?php echo $pedido['mesa']; ?></div>
                </div>
                
                <div class="info-card">
                    <div class="label">Estado</div>
                    <div class="value">
                        <span class="estado-badge estado-<?php echo strtolower($pedido['estado']); ?>">
                            <?php echo ucfirst($pedido['estado']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="label">Fecha y Hora</div>
                    <div class="value" style="font-size: 16px;">
                        <?php echo date('d/m/Y H:i', strtotime($pedido['fecha_hora'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Productos del Pedido -->
        <div class="productos-section">
            <h2 class="section-title">🍽️ Productos del Pedido</h2>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th style="text-align: center;">Cantidad</th>
                            <th style="text-align: right;">Precio Unitario</th>
                            <th style="text-align: right;">Subtotal</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $subtotal_total = 0;
                        while($det = $res_detalle->fetch_assoc()): 
                            $subtotal = $det['cantidad'] * $det['precio_unitario'];
                            $subtotal_total += $subtotal;
                        ?>
                            <tr>
                                <td class="producto-nombre"><?php echo $det['nombre']; ?></td>
                                <td style="text-align: center;">
                                    <span class="cantidad-badge"><?php echo $det['cantidad']; ?></span>
                                </td>
                                <td style="text-align: right; font-weight: 600;">
                                    $<?php echo number_format($det['precio_unitario'], 2); ?>
                                </td>
                                <td style="text-align: right; font-weight: bold; color: #667eea; font-size: 15px;">
                                    $<?php echo number_format($subtotal, 2); ?>
                                </td>
                                <td>
                                    <?php if(!empty($det['notas'])): ?>
                                        <span class="notas"><?php echo $det['notas']; ?></span>
                                    <?php else: ?>
                                        <span class="notas vacio">Sin notas</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Total del Pedido -->
            <div class="total-pedido">
                <div class="label">Total del Pedido</div>
                <div class="value">$<?php echo number_format($pedido['total'], 2); ?></div>
            </div>
        </div>
    </div>
    <script>
        // Auto-refresh cada 20 segundos
        setTimeout(function(){
            location.reload();
        }, 3000);    
    </script>   
</body>
</html>     