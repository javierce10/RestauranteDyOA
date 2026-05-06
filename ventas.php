<?php
session_start();
include('includes/conexion.php');

// Validar rol
if(!isset($_SESSION['rol']) || $_SESSION['rol'] != 'admin'){
    header('Location: login.php');
    exit();
}

// Configurar zona horaria de México
date_default_timezone_set('America/Mexico_City');

// Permitir seleccionar fecha (por defecto hoy)
$fecha_seleccionada = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

// Obtener ventas del día desde la tabla ventas
$res_ventas = $conn->query("
    SELECT v.id, v.pedido_id, v.total, v.metodo_pago, v.fecha, p.mesa
    FROM ventas v
    INNER JOIN pedidos p ON v.pedido_id = p.id
    WHERE DATE(v.fecha) = '$fecha_seleccionada'
    ORDER BY v.fecha ASC
");

$ventas = [];
$total_dia = 0;
$total_efectivo = 0;
$total_tarjeta = 0;

while($row = $res_ventas->fetch_assoc()){
    $ventas[] = $row;
    $total_dia += $row['total'];
    
    // Sumar por método de pago
    if($row['metodo_pago'] == 'efectivo'){
        $total_efectivo += $row['total'];
    } else if($row['metodo_pago'] == 'tarjeta'){
        $total_tarjeta += $row['total'];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas del Día</title>
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
            max-width: 1200px;
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

        .header-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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

        .btn-imprimir {
            padding: 12px 20px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: none;
            cursor: pointer;
        }

        .btn-imprimir:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }

        .section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            margin-bottom: 25px;
        }

        .fecha-selector {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            padding: 20px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .fecha-selector label {
            font-weight: 600;
            color: #667eea;
            font-size: 16px;
        }

        .fecha-selector input[type="date"] {
            padding: 10px 15px;
            border: 2px solid #667eea;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Segoe UI', sans-serif;
            transition: all 0.3s;
        }

        .fecha-selector input[type="date"]:focus {
            outline: none;
            border-color: #764ba2;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-buscar {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .btn-buscar:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }

        .btn-hoy {
            padding: 10px 20px;
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .btn-hoy:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }

        /* Sección de botones de resumen */
        .resumenes-section {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .resumenes-section h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .resumenes-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }

        .btn-resumen {
            padding: 14px 20px;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
        }

        .btn-resumen:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }

        .btn-resumen-semanal {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .btn-resumen-mensual {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .btn-resumen-anual {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .resumen {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .resumen-card {
            padding: 25px;
            border-radius: 12px;
            color: white;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s;
        }

        .resumen-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }

        .resumen-card h3 {
            margin: 0 0 15px 0;
            font-size: 16px;
            opacity: 0.95;
            font-weight: 600;
        }

        .resumen-card .monto {
            font-size: 36px;
            font-weight: bold;
        }

        .card-total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .card-efectivo {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
        }

        .card-tarjeta {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .table-container {
            overflow-x: auto;
        }

        table { 
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .total-row { 
            font-weight: bold;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%) !important;
            font-size: 16px;
        }

        .metodo-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .metodo-efectivo {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            color: white;
        }

        .metodo-tarjeta {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .no-ventas {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            font-size: 18px;
        }

        .no-ventas-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .header-buttons {
                width: 100%;
                justify-content: center;
            }

            .fecha-selector {
                flex-direction: column;
            }

            .fecha-selector form {
                width: 100%;
            }

            .resumenes-buttons {
                grid-template-columns: 1fr;
            }

            th, td {
                padding: 10px 8px;
                font-size: 14px;
            }

            .resumen-card .monto {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Ventas del Día</h1>
            <div class="header-buttons">
                <?php if(count($ventas) > 0): ?>
                    <a href="imprimir_ventas.php?fecha=<?php echo $fecha_seleccionada; ?>" target="_blank" class="btn-imprimir">
                        🖨️ Imprimir PDF
                    </a>
                <?php endif; ?>
                <a href="admin.php" class="btn-back">← Volver al Panel</a>
            </div>
        </div>

        <!-- Nueva sección de resúmenes -->
        <div class="section resumenes-section">
            <h3>📈 Ver Resúmenes</h3>
            <div class="resumenes-buttons">
                <a href="resumen_semanal.php" class="btn-resumen btn-resumen-semanal">
                    📅 Resumen Semanal
                </a>
                <a href="resumen_mensual.php" class="btn-resumen btn-resumen-mensual">
                    📊 Resumen Mensual
                </a>
                <a href="resumen_anual.php" class="btn-resumen btn-resumen-anual">
                    📆 Resumen Anual
                </a>
            </div>
        </div>

        <div class="section">
            <div class="fecha-selector">
                <form method="GET" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; width: 100%;">
                    <label for="fecha">Seleccionar fecha:</label>
                    <input type="date" id="fecha" name="fecha" value="<?php echo $fecha_seleccionada; ?>" required>
                    <button type="submit" class="btn-buscar">Buscar</button>
                    <a href="ventas.php?fecha=<?php echo date('Y-m-d'); ?>" class="btn-hoy">Hoy</a>
                </form>
            </div>

            <h2 style="color: #667eea; margin-bottom: 20px; font-size: 22px;">
                Fecha: <?php echo date('d/m/Y', strtotime($fecha_seleccionada)); ?>
            </h2>

            <?php if(count($ventas) == 0): ?>
                <div class="no-ventas">
                    <div class="no-ventas-icon">📊</div>
                    <p>No hay ventas registradas para el <?php echo date('d/m/Y', strtotime($fecha_seleccionada)); ?></p>
                </div>
            <?php else: ?>
                <!-- Resumen de ventas -->
                <div class="resumen">
                    <div class="resumen-card card-total">
                        <h3>Total del Día</h3>
                        <div class="monto">$<?php echo number_format($total_dia, 2); ?></div>
                    </div>
                    <div class="resumen-card card-efectivo">
                        <h3>Efectivo</h3>
                        <div class="monto">$<?php echo number_format($total_efectivo, 2); ?></div>
                    </div>
                    <div class="resumen-card card-tarjeta">
                        <h3>Tarjeta</h3>
                        <div class="monto">$<?php echo number_format($total_tarjeta, 2); ?></div>
                    </div>
                </div>

                <!-- Tabla de ventas -->
                <div class="table-container">
                    <table>  
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Mesa</th>
                                <th>Pedido ID</th>
                                <th>Total</th>
                                <th>Método de Pago</th>
                                <th>Fecha/Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $contador = 1;
                            foreach($ventas as $venta): 
                            ?>
                                <tr>
                                    <td><?php echo $contador++; ?></td>
                                    <td><strong><?php echo $venta['mesa']; ?></strong></td>
                                    <td>#<?php echo $venta['pedido_id']; ?></td>
                                    <td><strong>$<?php echo number_format($venta['total'], 2); ?></strong></td>
                                    <td>
                                        <?php if($venta['metodo_pago']): ?>
                                            <span class="metodo-badge metodo-<?php echo $venta['metodo_pago']; ?>">
                                                <?php echo $venta['metodo_pago'] == 'efectivo' ? 'Efectivo' : 'Tarjeta'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999; font-style: italic;">Sin registro</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($venta['fecha'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="3"><strong>TOTAL DEL DÍA</strong></td>
                                <td colspan="3"><strong>$<?php echo number_format($total_dia, 2); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>    
</html>   