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
    <title>Reporte de Ventas - <?php echo date('d/m/Y', strtotime($fecha_seleccionada)); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: white;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
        }

        .header h1 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header .fecha {
            font-size: 18px;
            color: #666;
            margin-top: 10px;
        }

        .header .generado {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .resumen {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px solid #667eea;
        }

        .resumen h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 22px;
        }

        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .resumen-item {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 6px;
            border: 1px solid #ddd;
        }

        .resumen-item .label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }

        .resumen-item .valor {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
        }

        .detalle {
            margin: 30px 0;
        }

        .detalle h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 22px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th {
            background: #667eea;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        td {
            padding: 10px 8px;
            border-bottom: 1px solid #ddd;
            font-size: 13px;
        }

        tr:nth-child(even) {
            background: #f8f9fa;
        }

        .total-row {
            background: #667eea !important;
            color: white !important;
            font-weight: bold;
            font-size: 15px;
        }

        .total-row td {
            border: none;
            padding: 12px 8px;
        }

        .metodo-efectivo {
            color: #28a745;
            font-weight: 600;
        }

        .metodo-tarjeta {
            color: #007bff;
            font-weight: 600;
        }

        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            text-align: center;
            font-size: 12px;
            color: #999;
        }

        .btn-imprimir {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 25px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            font-size: 15px;
            z-index: 1000;
        }

        .btn-imprimir:hover {
            background: #5568d3;
        }

        @media print {
            .btn-imprimir {
                display: none;
            }

            body {
                padding: 0;
            }

            .resumen-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            thead {
                display: table-header-group;
            }
        }

        .no-ventas {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <button class="btn-imprimir" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>

    <div class="header">
        <h1>📊 REPORTE DE VENTAS</h1>
        <div class="fecha">
            <strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($fecha_seleccionada)); ?>
        </div>
        <div class="generado">
            Generado el: <?php echo date('d/m/Y H:i:s'); ?>
        </div>
    </div>

    <?php if(count($ventas) > 0): ?>
        <!-- Resumen -->
        <div class="resumen">
            <h2>Resumen del Día</h2>
            <div class="resumen-grid">
                <div class="resumen-item">
                    <div class="label">Total del Día</div>
                    <div class="valor">$<?php echo number_format($total_dia, 2); ?></div>
                </div>
                <div class="resumen-item">
                    <div class="label">Total en Efectivo</div>
                    <div class="valor" style="color: #28a745;">$<?php echo number_format($total_efectivo, 2); ?></div>
                </div>
                <div class="resumen-item">
                    <div class="label">Total en Tarjeta</div>
                    <div class="valor" style="color: #007bff;">$<?php echo number_format($total_tarjeta, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Detalle -->
        <div class="detalle">
            <h2>Detalle de Ventas</h2>
            <p style="color: #666; margin-bottom: 10px;">Total de transacciones: <strong><?php echo count($ventas); ?></strong></p>
            
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="width: 80px;">Mesa</th>
                        <th style="width: 100px;">Pedido ID</th>
                        <th style="width: 100px;">Total</th>
                        <th style="width: 120px;">Método de Pago</th>
                        <th>Fecha y Hora</th>
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
                                    <span class="metodo-<?php echo $venta['metodo_pago']; ?>">
                                        <?php echo $venta['metodo_pago'] == 'efectivo' ? '💵 Efectivo' : '💳 Tarjeta'; ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999;">Sin registro</span>
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

        <div class="footer">
            <p>Sistema de Gestión de Restaurante</p>
            <p>Total de ventas registradas: <?php echo count($ventas); ?> | Fecha de reporte: <?php echo date('d/m/Y', strtotime($fecha_seleccionada)); ?></p>
        </div>

    <?php else: ?>
        <div class="no-ventas">
            <p>No hay ventas registradas para el día <?php echo date('d/m/Y', strtotime($fecha_seleccionada)); ?></p>
        </div>
    <?php endif; ?>
</body>
</html>     