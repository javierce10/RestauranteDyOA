<?php
session_start();
include('includes/conexion.php');

// Validar rol - DEBE SER COCINA
if(!isset($_SESSION['rol']) || $_SESSION['rol'] != 'cocina'){
    header('Location: index.php');
    exit();
}
//Hola soy Guacamole  
// Cambiar estado de un platillo
if(isset($_POST['cambiar_estado'])){
    $detalle_id = $_POST['detalle_id'];
    $nuevo_estado = $_POST['nuevo_estado'];
    
    $stmt = $conn->prepare("UPDATE detalle_pedido SET estado_preparacion=? WHERE id=?");
    $stmt->bind_param("si", $nuevo_estado, $detalle_id);
    $stmt->execute();
    $stmt->close();
    
    // Redirigir para evitar reenvío de formulario (patrón PRG)
    header('Location: cocina.php');
    exit();
}

// Obtener todos los platillos (comidas)
$query = "
    SELECT 
        dp.id as detalle_id,
        dp.cantidad,
        dp.notas,
        dp.estado_preparacion,
        p.id as pedido_id,
        p.mesa,
        p.fecha_hora,
        prod.nombre as platillo,
        prod.categoria
    FROM detalle_pedido dp
    INNER JOIN pedidos p ON dp.pedido_id = p.id
    INNER JOIN productos prod ON dp.producto_id = prod.id
    WHERE prod.categoria = 'comida'
    AND (
        (p.estado = 'pendiente' AND dp.estado_preparacion IN ('pendiente', 'en_preparacion', 'listo'))
        OR (dp.estado_preparacion = 'entregado' AND p.fecha_hora >= DATE_SUB(NOW(), INTERVAL 2 HOUR))
    )
    ORDER BY 
        FIELD(dp.estado_preparacion, 'pendiente', 'en_preparacion', 'listo', 'entregado'),
        p.fecha_hora ASC
";

$resultado = $conn->query($query);

// Agrupar por estado
$platillos = [
    'pendiente' => [],
    'en_preparacion' => [],
    'listo' => [],
    'entregado' => []
];

while($row = $resultado->fetch_assoc()){
    $platillos[$row['estado_preparacion']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cocina - Órdenes</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
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
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .user-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
        }

        .btn-logout {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 10px rgba(235, 51, 73, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(235, 51, 73, 0.5);
        }

        .columns {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .column {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .column-header {
            padding: 15px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 20px;
            text-align: center;
        }

        .pendiente-header {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        }

        .preparacion-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .listo-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .entregado-header {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
        }

        .platillo-card {
            background: #f8f9fa;
            border-left: 4px solid;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .platillo-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }

        .pendiente-card { border-color: #eb3349; }
        .preparacion-card { border-color: #f093fb; }
        .listo-card { border-color: #4facfe; }
        .entregado-card { border-color: #56ab2f; }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .mesa-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }

        .cantidad-badge {
            background: #ffc107;
            color: #333;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }

        .platillo-nombre {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 10px 0;
        }

        .notas {
            background: white;
            padding: 10px;
            border-radius: 8px;
            margin: 10px 0;
            font-size: 14px;
            color: #666;
        }

        .tiempo {
            font-size: 12px;
            color: #999;
            margin: 5px 0;
        }

        .acciones {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .btn-accion {
            padding: 8px 15px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 13px;
            flex: 1;
            min-width: 120px;
        }

        .btn-preparar {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .btn-listo {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .btn-entregar {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            color: white;
        }

        .btn-accion:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #999;
        }

        .entregado-time {
            color: #56ab2f;
            font-weight: 600;
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .header h1 {
                width: 100%;
            }

            .header-actions {
                width: 100%;
                justify-content: center;
            }

            .columns {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Panel de Cocina</h1>
            <div class="header-actions">
                <div class="user-info">
                    <?php echo $_SESSION['usuario']; ?>
                </div>
                <a href="logout.php" class="btn-logout">
                    Cerrar Sesión
                </a>
            </div>
        </div>

        <div class="columns">
            <!-- Columna Pendientes -->
            <div class="column">
                <div class="column-header pendiente-header">
                    Pendientes (<?php echo count($platillos['pendiente']); ?>)
                </div>
                <?php if(empty($platillos['pendiente'])): ?>
                    <div class="empty-state">Sin órdenes pendientes</div>
                <?php else: ?>
                    <?php foreach($platillos['pendiente'] as $plat): ?>
                        <div class="platillo-card pendiente-card">
                            <div class="card-header">
                                <span class="mesa-badge"><?php echo $plat['mesa']; ?></span>
                                <span class="cantidad-badge">x<?php echo $plat['cantidad']; ?></span>
                            </div>
                            <div class="platillo-nombre"><?php echo $plat['platillo']; ?></div>
                            <?php if($plat['notas']): ?>
                                <div class="notas"><?php echo $plat['notas']; ?></div>
                            <?php endif; ?>
                            <div class="tiempo">Pedido #<?php echo $plat['pedido_id']; ?> - <?php echo date('H:i', strtotime($plat['fecha_hora'])); ?></div>
                            <form method="POST" class="acciones">
                                <input type="hidden" name="detalle_id" value="<?php echo $plat['detalle_id']; ?>">
                                <input type="hidden" name="nuevo_estado" value="en_preparacion">
                                <button type="submit" name="cambiar_estado" class="btn-accion btn-preparar">
                                    Iniciar Preparación
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Columna En Preparación -->
            <div class="column">
                <div class="column-header preparacion-header">
                    En Preparación (<?php echo count($platillos['en_preparacion']); ?>)
                </div>
                <?php if(empty($platillos['en_preparacion'])): ?>
                    <div class="empty-state">Sin platillos en preparación</div>
                <?php else: ?>
                    <?php foreach($platillos['en_preparacion'] as $plat): ?>
                        <div class="platillo-card preparacion-card">
                            <div class="card-header">
                                <span class="mesa-badge"><?php echo $plat['mesa']; ?></span>
                                <span class="cantidad-badge">x<?php echo $plat['cantidad']; ?></span>
                            </div>
                            <div class="platillo-nombre"><?php echo $plat['platillo']; ?></div>
                            <?php if($plat['notas']): ?>
                                <div class="notas"><?php echo $plat['notas']; ?></div>
                            <?php endif; ?>
                            <div class="tiempo">Pedido #<?php echo $plat['pedido_id']; ?> - <?php echo date('H:i', strtotime($plat['fecha_hora'])); ?></div>
                            <form method="POST" class="acciones">
                                <input type="hidden" name="detalle_id" value="<?php echo $plat['detalle_id']; ?>">
                                <input type="hidden" name="nuevo_estado" value="listo">
                                <button type="submit" name="cambiar_estado" class="btn-accion btn-listo">
                                    Marcar Listo
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Columna Listos -->
            <div class="column">
                <div class="column-header listo-header">
                    Listos para Entregar (<?php echo count($platillos['listo']); ?>)
                </div>
                <?php if(empty($platillos['listo'])): ?>
                    <div class="empty-state">Nada listo aún</div>
                <?php else: ?>
                    <?php foreach($platillos['listo'] as $plat): ?>
                        <div class="platillo-card listo-card">
                            <div class="card-header">
                                <span class="mesa-badge"><?php echo $plat['mesa']; ?></span>
                                <span class="cantidad-badge">x<?php echo $plat['cantidad']; ?></span>
                            </div>
                            <div class="platillo-nombre"><?php echo $plat['platillo']; ?></div>
                            <?php if($plat['notas']): ?>
                                <div class="notas"><?php echo $plat['notas']; ?></div>
                            <?php endif; ?>
                            <div class="tiempo">Pedido #<?php echo $plat['pedido_id']; ?> - <?php echo date('H:i', strtotime($plat['fecha_hora'])); ?></div>
                            <div class="acciones">
                                <span style="color: #4facfe; font-weight: 600; flex: 1; text-align: center; padding: 8px;">
                                    Esperando mesero...
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Columna Entregados -->
            <div class="column">
                <div class="column-header entregado-header">
                    Entregados (<?php echo count($platillos['entregado']); ?>)
                </div>
                <?php if(empty($platillos['entregado'])): ?>
                    <div class="empty-state">Sin platillos entregados</div>
                <?php else: ?>
                    <?php foreach($platillos['entregado'] as $plat): ?>
                        <div class="platillo-card entregado-card">
                            <div class="card-header">
                                <span class="mesa-badge"><?php echo $plat['mesa']; ?></span>
                                <span class="cantidad-badge">x<?php echo $plat['cantidad']; ?></span>
                            </div>
                            <div class="platillo-nombre"><?php echo $plat['platillo']; ?></div>
                            <div class="entregado-time">Entregado - Pedido #<?php echo $plat['pedido_id']; ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh cada 20 segundos
        setTimeout(function(){  
            window.location.href = 'cocina.php';
        }, 2000);  
    </script>   

</body>
</html>   