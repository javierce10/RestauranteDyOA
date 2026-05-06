<?php
session_start();
include('includes/conexion.php');
//unicamente tiene derecho el admin
// Validar rol para que solo entre el administrador
if(!isset($_SESSION['rol']) || $_SESSION['rol'] != 'admin'){
    header('Location: index.php');
    exit();
}

// Configurar zona horaria de México
date_default_timezone_set('America/Mexico_City');

// Permitir seleccionar semana (por defecto la semana actual)
$fecha_seleccionada = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

// Calcular inicio y fin de la semana (Lunes a Domingo)
$fecha_obj = new DateTime($fecha_seleccionada);
$dia_semana = $fecha_obj->format('N');
$fecha_obj->modify('-' . ($dia_semana - 1) . ' days');
$fecha_inicio = $fecha_obj->format('Y-m-d');
$fecha_obj->modify('+6 days');
$fecha_fin = $fecha_obj->format('Y-m-d');

// Obtener ventas de la semana
$res_ventas = $conn->query("
    SELECT v.id, v.pedido_id, v.total, v.metodo_pago, v.fecha, p.mesa
    FROM ventas v
    INNER JOIN pedidos p ON v.pedido_id = p.id
    WHERE DATE(v.fecha) BETWEEN '$fecha_inicio' AND '$fecha_fin'
    ORDER BY v.fecha ASC
");

$ventas = [];
$total_semana = 0;
$total_efectivo = 0;
$total_tarjeta = 0;
$ventas_por_dia = [];

while($row = $res_ventas->fetch_assoc()){
    $ventas[] = $row;
    $total_semana += $row['total'];
    
    if($row['metodo_pago'] == 'efectivo'){
        $total_efectivo += $row['total'];
    } else if($row['metodo_pago'] == 'tarjeta'){
        $total_tarjeta += $row['total'];
    }
    
    $dia = date('Y-m-d', strtotime($row['fecha']));
    if(!isset($ventas_por_dia[$dia])){
        $ventas_por_dia[$dia] = ['total' => 0, 'cantidad' => 0];
    }
    $ventas_por_dia[$dia]['total'] += $row['total'];
    $ventas_por_dia[$dia]['cantidad']++;
}

$promedio_diario = count($ventas_por_dia) > 0 ? $total_semana / count($ventas_por_dia) : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen Semanal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            color: #f5576c;
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

        .btn-back, .btn-imprimir {
            padding: 12px 20px;
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

        .btn-back {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .btn-imprimir {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .btn-back:hover, .btn-imprimir:hover {
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
            background: linear-gradient(135deg, rgba(240, 147, 251, 0.1) 0%, rgba(245, 87, 108, 0.1) 100%);
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .fecha-selector label {
            font-weight: 600;
            color: #f5576c;
            font-size: 16px;
        }

        .fecha-selector input[type="date"] {
            padding: 10px 15px;
            border: 2px solid #f5576c;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Segoe UI', sans-serif;
            transition: all 0.3s;
        }

        .fecha-selector input[type="date"]:focus {
            outline: none;
            border-color: #f093fb;
            box-shadow: 0 0 0 3px rgba(245, 87, 108, 0.1);
        }

        .btn-buscar, .btn-hoy {
            padding: 10px 20px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-decoration: none;
            display: inline-block;
        }

        .btn-buscar:hover, .btn-hoy:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }

        .resumen {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .card-efectivo {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
        }

        .card-tarjeta {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .card-promedio {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            background: linear-gradient(135deg, rgba(240, 147, 251, 0.1) 0%, rgba(245, 87, 108, 0.1) 100%) !important;
            font-size: 16px;
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

        .dia-semana {
            display: inline-block;
            padding: 4px 12px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }

        @media print {
            @page {
                margin: 10mm 10mm 10mm 10mm;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            html {
                margin: 0 !important;
                padding: 0 !important;
            }

            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .header-buttons, .fecha-selector {
                display: none !important;
            }

            .container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .header {
                box-shadow: none !important;
                border-bottom: 2px solid #f5576c;
                border-radius: 0 !important;
                page-break-after: avoid;
                margin: 0 0 10px 0 !important;
                padding: 10px !important;
            }

            .header h1 {
                font-size: 20px !important;
                margin: 0 !important;
            }

            .section {
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 5px 0 !important;
                margin: 0 0 10px 0 !important;
            }

            h2 {
                margin: 0 0 10px 0 !important;
                font-size: 16px !important;
                padding: 0 !important;
            }

            h3 {
                margin: 10px 0 5px 0 !important;
                font-size: 14px !important;
                page-break-after: avoid;
            }

            .resumen {
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 8px !important;
                margin: 0 0 10px 0 !important;
                page-break-inside: avoid;
            }

            .resumen-card {
                box-shadow: 0 1px 2px rgba(0,0,0,0.1) !important;
                padding: 10px !important;
                page-break-inside: avoid;
            }

            .resumen-card h3 {
                font-size: 11px !important;
                margin: 0 0 5px 0 !important;
            }

            .resumen-card .monto {
                font-size: 20px !important;
            }

            .table-container {
                margin: 5px 0 !important;
            }

            table {
                font-size: 10px !important;
                page-break-inside: auto;
            }

            thead {
                display: table-header-group;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            th, td {
                padding: 5px !important;
            }

            .dia-semana {
                background: #f5576c !important;
            }
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
            <h1>📅 Resumen Semanal</h1>
            <div class="header-buttons">
                <button class="btn-imprimir" onclick="window.print()">🖨️ Imprimir / PDF</button>
                <a href="ventas.php" class="btn-back">← Volver</a>
            </div>
        </div>

        <div class="section">
            <div class="fecha-selector">
                <form method="GET" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; width: 100%;">
                    <label for="fecha">Seleccionar semana:</label>
                    <input type="date" id="fecha" name="fecha" value="<?php echo $fecha_seleccionada; ?>" required>
                    <button type="submit" class="btn-buscar">Buscar</button>
                    <a href="resumen_semanal.php" class="btn-hoy">Semana Actual</a>
                </form>
            </div>

            <h2 style="color: #f5576c; margin-bottom: 20px; font-size: 22px;">
                Semana del <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> al <?php echo date('d/m/Y', strtotime($fecha_fin)); ?>
            </h2>

            <?php if(count($ventas) == 0): ?>
                <div class="no-ventas">
                    <div class="no-ventas-icon">📊</div>
                    <p>No hay ventas registradas en esta semana</p>
                </div>
            <?php else: ?>
                <div class="resumen">
                    <div class="resumen-card card-total">
                        <h3>Total Semanal</h3>
                        <div class="monto">$<?php echo number_format($total_semana, 2); ?></div>
                    </div>
                    <div class="resumen-card card-efectivo">
                        <h3>Efectivo</h3>
                        <div class="monto">$<?php echo number_format($total_efectivo, 2); ?></div>
                    </div>
                    <div class="resumen-card card-tarjeta">
                        <h3>Tarjeta</h3>
                        <div class="monto">$<?php echo number_format($total_tarjeta, 2); ?></div>
                    </div>
                    <div class="resumen-card card-promedio">
                        <h3>Promedio Diario</h3>
                        <div class="monto">$<?php echo number_format($promedio_diario, 2); ?></div>
                    </div>
                </div>

                <h3 style="color: #f5576c; margin: 30px 0 20px 0; font-size: 20px;">📊 Desglose por Día</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Día</th>
                                <th>Cantidad de Ventas</th>
                                <th>Total del Día</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $fecha_actual = new DateTime($fecha_inicio);
                            $fecha_final = new DateTime($fecha_fin);
                            $dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                            
                            while($fecha_actual <= $fecha_final):
                                $fecha_str = $fecha_actual->format('Y-m-d');
                                $dia_nombre = $dias_semana[$fecha_actual->format('N') - 1];
                                $total_dia = isset($ventas_por_dia[$fecha_str]) ? $ventas_por_dia[$fecha_str]['total'] : 0;
                                $cantidad = isset($ventas_por_dia[$fecha_str]) ? $ventas_por_dia[$fecha_str]['cantidad'] : 0;
                            ?>
                                <tr>
                                    <td><?php echo $fecha_actual->format('d/m/Y'); ?></td>
                                    <td><span class="dia-semana"><?php echo $dia_nombre; ?></span></td>
                                    <td><?php echo $cantidad; ?> venta(s)</td>
                                    <td><strong>$<?php echo number_format($total_dia, 2); ?></strong></td>
                                </tr>
                            <?php 
                                $fecha_actual->modify('+1 day');
                            endwhile; 
                            ?>
                            <tr class="total-row">
                                <td colspan="3"><strong>TOTAL DE LA SEMANA</strong></td>
                                <td><strong>$<?php echo number_format($total_semana, 2); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h3 style="color: #f5576c; margin: 30px 0 20px 0; font-size: 20px;">📋 Detalle de Ventas</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha/Hora</th>
                                <th>Mesa</th>
                                <th>Pedido ID</th>
                                <th>Total</th>
                                <th>Método de Pago</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $contador = 1;
                            foreach($ventas as $venta): 
                            ?>  
                                <tr>
                                    <td><?php echo $contador++; ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha'])); ?></td>
                                    <td><strong><?php echo $venta['mesa']; ?></strong></td>
                                    <td>#<?php echo $venta['pedido_id']; ?></td>
                                    <td><strong>$<?php echo number_format($venta['total'], 2); ?></strong></td>
                                    <td>
                                        <?php if($venta['metodo_pago']): ?>
                                            <?php echo $venta['metodo_pago'] == 'efectivo' ? '💵 Efectivo' : '💳 Tarjeta'; ?>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>  