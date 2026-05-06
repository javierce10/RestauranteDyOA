<?php
session_start();
include('includes/conexion.php');

// Validar rol
if(!isset($_SESSION['rol']) || $_SESSION['rol'] != 'cocina'){
    header('Location: index.php');
    exit();
}

// Marcar producto como en preparación......
if(isset($_POST['iniciar_preparacion'])){
    $detalle_id = $_POST['detalle_id'];
    $conn->query("UPDATE detalle_pedido SET estado_preparacion='en_preparacion' WHERE id=$detalle_id");
    $_SESSION['mensaje'] = 'preparacion_iniciada';
    header("Location: cocina.php");
    exit();
}

// Marcar producto como listo........
if(isset($_POST['marcar_listo'])){
    $detalle_id = $_POST['detalle_id'];
    $conn->query("UPDATE detalle_pedido SET estado_preparacion='listo' WHERE id=$detalle_id");
    $_SESSION['mensaje'] = 'producto_listo';
    header("Location: cocina.php");
    exit();
}

// Imprimir ticket
if(isset($_POST['imprimir_ticket'])){
    $pedido_id = $_POST['pedido_id'];
    
    // Obtener datos del pedido
    $res_pedido = $conn->query("SELECT * FROM pedidos WHERE id=$pedido_id");
    $pedido = $res_pedido->fetch_assoc();
    
    // Obtener productos del pedido (solo comidas pendientes)
    $res_items = $conn->query("
        SELECT dp.*, p.nombre, p.categoria
        FROM detalle_pedido dp
        JOIN productos p ON dp.producto_id = p.id
        WHERE dp.pedido_id = $pedido_id 
        AND p.categoria = 'comida'
        AND dp.estado_preparacion = 'pendiente'
        ORDER BY p.nombre
    ");
    
    // Generar contenido del ticket
    $ticket_content = generarTicket($pedido, $res_items);
    
    // Guardar ticket en archivo temporal
    $ticket_file = 'temp/ticket_' . $pedido_id . '_' . time() . '.txt';
    file_put_contents($ticket_file, $ticket_content);
    
    $_SESSION['mensaje'] = 'ticket_impreso';
    $_SESSION['ticket_file'] = $ticket_file;
    header("Location: cocina.php");
    exit();
}

// Función para generar el contenido del ticket
function generarTicket($pedido, $items_result) {
    $ticket = "";
    $ticket .= "================================\n";
    $ticket .= "      ORDEN DE COCINA\n";
    $ticket .= "================================\n";
    $ticket .= "Mesa: " . $pedido['mesa'] . "\n";
    $ticket .= "Pedido #" . $pedido['id'] . "\n";
    $ticket .= "Mesero: " . $pedido['mesero_nombre'] . "\n";
    $ticket .= "Fecha: " . date('d/m/Y H:i', strtotime($pedido['fecha_hora'])) . "\n";
    $ticket .= "================================\n\n";
    
    $items = [];
    while($item = $items_result->fetch_assoc()) {
        $items[] = $item;
    }
    
    if(count($items) > 0) {
        $ticket .= "PRODUCTOS A PREPARAR:\n";
        $ticket .= "--------------------------------\n";
        
        foreach($items as $item) {
            $ticket .= "\n";
            $ticket .= "* " . strtoupper($item['nombre']) . "\n";
            $ticket .= "  Cantidad: " . $item['cantidad'] . "\n";
            
            if(!empty($item['notas'])) {
                $ticket .= "  NOTAS: " . $item['notas'] . "\n";
            }
        }
        
        $ticket .= "\n================================\n";
        $ticket .= "  TOTAL DE PLATILLOS: " . count($items) . "\n";
        $ticket .= "================================\n";
    } else {
        $ticket .= "Sin productos para cocina\n";
        $ticket .= "================================\n";
    }
    
    return $ticket;
}

// Obtener mensaje de sesión
$mensaje = null;
$ticket_file = null;
if(isset($_SESSION['mensaje'])){
    $mensaje = $_SESSION['mensaje'];
    $ticket_file = $_SESSION['ticket_file'] ?? null;
    unset($_SESSION['mensaje']);
    unset($_SESSION['ticket_file']);
}

// Obtener pedidos pendientes y en preparación
$query_pedidos = "
    SELECT DISTINCT p.id, p.mesa, p.fecha_hora, p.mesero_nombre, p.estado,
           COUNT(CASE WHEN dp.estado_preparacion = 'pendiente' AND pr.categoria = 'comida' THEN 1 END) as pendientes,
           COUNT(CASE WHEN dp.estado_preparacion = 'en_preparacion' AND pr.categoria = 'comida' THEN 1 END) as en_preparacion
    FROM pedidos p
    JOIN detalle_pedido dp ON p.id = dp.pedido_id
    JOIN productos pr ON dp.producto_id = pr.id
    WHERE p.estado = 'pendiente'
    AND pr.categoria = 'comida'
    AND dp.estado_preparacion IN ('pendiente', 'en_preparacion')
    GROUP BY p.id
    ORDER BY p.fecha_hora ASC
";
$pedidos = $conn->query($query_pedidos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cocina - Sistema de Pedidos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 25px;
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
            color: #f5576c;
            font-size: 28px;
        }

        .btn-logout {
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
            box-shadow: 0 4px 10px rgba(235, 51, 73, 0.3);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        .pedidos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .pedido-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s;
        }

        .pedido-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .pedido-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f5576c;
        }

        .mesa-numero {
            font-size: 24px;
            font-weight: bold;
            color: #f5576c;
        }

        .pedido-info {
            font-size: 12px;
            color: #666;
            text-align: right;
        }

        .btn-imprimir {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .btn-imprimir:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.4);
        }

        .producto-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #ccc;
        }

        .producto-item.pendiente {
            border-left-color: #ffc107;
            background: #fff3cd;
        }

        .producto-item.en_preparacion {
            border-left-color: #f093fb;
            background: #fce4ec;
        }

        .producto-nombre {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
            color: #333;
        }

        .producto-detalles {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }

        .producto-notas {
            background: white;
            padding: 8px;
            border-radius: 5px;
            font-size: 13px;
            color: #856404;
            margin-bottom: 10px;
            border-left: 3px solid #ffc107;
        }

        .estado-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .badge-pendiente {
            background: #ffc107;
            color: #856404;
        }

        .badge-en_preparacion {
            background: #f093fb;
            color: white;
        }

        .btn-accion {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 5px;
            font-size: 13px;
        }

        .btn-iniciar {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .btn-listo {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            color: white;
        }

        .btn-accion:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }

        .stats {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .stat-item {
            flex: 1;
            min-width: 150px;
            text-align: center;
            padding: 15px;
            border-radius: 10px;
            background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
            color: white;
        }

        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .empty-state h2 {
            color: #666;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
        }

        .ticket-preview {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            border: 2px dashed #667eea;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .ticket-preview, .ticket-preview * {
                visibility: visible;
            }
            .ticket-preview {
                position: absolute;
                left: 0;
                top: 0;
                width: 80mm;
                border: none;
            }
        }

        @media (max-width: 768px) {
            .pedidos-grid {
                grid-template-columns: 1fr;
            }
            .stats {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👨‍🍳 Panel de Cocina</h1>
            <a href="logout.php" class="btn-logout">🚪 Cerrar Sesión</a>
        </div>

        <?php if($mensaje == 'preparacion_iniciada'): ?>
            <div class="alert alert-info">
                🔥 Preparación iniciada correctamente
            </div>
        <?php elseif($mensaje == 'producto_listo'): ?>
            <div class="alert alert-success">
                ✅ Producto marcado como listo
            </div>
        <?php elseif($mensaje == 'ticket_impreso'): ?>
            <div class="alert alert-success">
                🖨️ Ticket generado correctamente
                <?php if($ticket_file && file_exists($ticket_file)): ?>
                    <div class="ticket-preview">
                        <?php echo htmlspecialchars(file_get_contents($ticket_file)); ?>
                    </div>
                    <button onclick="window.print()" style="margin-top: 10px; padding: 8px 15px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        🖨️ Imprimir Ticket
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php
        // Calcular estadísticas
        $total_pendientes = 0;
        $total_preparacion = 0;
        $pedidos_array = [];
        
        if($pedidos->num_rows > 0) {
            while($p = $pedidos->fetch_assoc()) {
                $pedidos_array[] = $p;
                $total_pendientes += $p['pendientes'];
                $total_preparacion += $p['en_preparacion'];
            }
            $pedidos->data_seek(0);
        }
        ?>

        <div class="stats">
            <div class="stat-item">
                <div class="stat-number"><?php echo count($pedidos_array); ?></div>
                <div class="stat-label">Pedidos Activos</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $total_pendientes; ?></div>
                <div class="stat-label">Platillos Pendientes</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $total_preparacion; ?></div>
                <div class="stat-label">En Preparación</div>
            </div>
        </div>

        <?php if(count($pedidos_array) == 0): ?>
            <div class="empty-state">
                <div class="empty-state-icon">😴</div>
                <h2>No hay pedidos pendientes</h2>
                <p>La cocina está al día. Los nuevos pedidos aparecerán aquí.</p>
            </div>
        <?php else: ?>
            <div class="pedidos-grid">
                <?php foreach($pedidos_array as $pedido): 
                    // Obtener productos del pedido
                    $pedido_id = $pedido['id'];
                    $res_productos = $conn->query("
                        SELECT dp.*, p.nombre, p.categoria
                        FROM detalle_pedido dp
                        JOIN productos p ON dp.producto_id = p.id
                        WHERE dp.pedido_id = $pedido_id
                        AND p.categoria = 'comida'
                        AND dp.estado_preparacion IN ('pendiente', 'en_preparacion')
                        ORDER BY dp.estado_preparacion ASC, p.nombre
                    ");
                    
                    $tiene_pendientes = false;
                    $productos = [];
                    while($prod = $res_productos->fetch_assoc()) {
                        $productos[] = $prod;
                        if($prod['estado_preparacion'] == 'pendiente') {
                            $tiene_pendientes = true;
                        }
                    }
                ?>
                    <div class="pedido-card">
                        <div class="pedido-header">
                            <div class="mesa-numero">🪑 <?php echo $pedido['mesa']; ?></div>
                            <div class="pedido-info">
                                Pedido #<?php echo $pedido['id']; ?><br>
                                <?php echo date('H:i', strtotime($pedido['fecha_hora'])); ?><br>
                                👤 <?php echo htmlspecialchars($pedido['mesero_nombre']); ?>
                            </div>
                        </div>

                        <?php if($tiene_pendientes): ?>
                            <form method="POST" style="margin-bottom: 15px;">
                                <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
                                <button type="submit" name="imprimir_ticket" class="btn-imprimir">
                                    🖨️ Imprimir Ticket de Cocina
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php foreach($productos as $producto): ?>
                            <div class="producto-item <?php echo $producto['estado_preparacion']; ?>">
                                <span class="estado-badge badge-<?php echo $producto['estado_preparacion']; ?>">
                                    <?php 
                                    if($producto['estado_preparacion'] == 'pendiente') {
                                        echo '⏳ Pendiente';
                                    } else {
                                        echo '🔥 En Preparación';
                                    }
                                    ?>
                                </span>
                                
                                <div class="producto-nombre">
                                    🍽️ <?php echo $producto['nombre']; ?>
                                </div>
                                
                                <div class="producto-detalles">
                                    Cantidad: <strong><?php echo $producto['cantidad']; ?></strong>
                                </div>
                                
                                <?php if(!empty($producto['notas'])): ?>
                                    <div class="producto-notas">
                                        📌 <strong>Notas:</strong> <?php echo htmlspecialchars($producto['notas']); ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="detalle_id" value="<?php echo $producto['id']; ?>">
                                    
                                    <?php if($producto['estado_preparacion'] == 'pendiente'): ?>
                                        <button type="submit" name="iniciar_preparacion" class="btn-accion btn-iniciar">
                                            🔥 Iniciar Preparación
                                        </button>
                                    <?php elseif($producto['estado_preparacion'] == 'en_preparacion'): ?>
                                        <button type="submit" name="marcar_listo" class="btn-accion btn-listo">
                                            ✅ Marcar como Listo
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh cada 30 segundos
        setTimeout(function(){
            location.reload();
        }, 30000);
    </script>
</body>
</html>    