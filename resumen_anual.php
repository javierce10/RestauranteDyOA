<?php
session_start();
include('includes/conexion.php');
//aqui solo validamos que el admin pueda acceder
// Validar rol
if(!isset($_SESSION['rol']) || $_SESSION['rol'] != 'admin'){
    header('Location: index.php');
    exit();
}

// Configurar zona horaria de México
date_default_timezone_set('America/Mexico_City');

// Permitir seleccionar año
$anio = isset($_GET['anio']) ? $_GET['anio'] : date('Y');

// Calcular primer y último día del año
$fecha_inicio = "$anio-01-01";
$fecha_fin = "$anio-12-31";

// Obtener ventas del año
$res_ventas = $conn->query("
    SELECT v.id, v.pedido_id, v.total, v.metodo_pago, v.fecha, p.mesa
    FROM ventas v
    INNER JOIN pedidos p ON v.pedido_id = p.id
    WHERE DATE(v.fecha) BETWEEN '$fecha_inicio' AND '$fecha_fin'
    ORDER BY v.fecha ASC
");

$ventas = [];
$total_anio = 0;
$total_efectivo = 0;
$total_tarjeta = 0;
$ventas_por_mes = [];

// Inicializar todos los meses con 0
for($i = 1; $i <= 12; $i++){
    $mes_key = str_pad($i, 2, '0', STR_PAD_LEFT);
    $ventas_por_mes[$mes_key] = ['total' => 0, 'cantidad' => 0];
}

while($row = $res_ventas->fetch_assoc()){
    $ventas[] = $row;
    $total_anio += $row['total'];
    
    // Sumar por método de pago
    if($row['metodo_pago'] == 'efectivo'){
        $total_efectivo += $row['total'];
    } else if($row['metodo_pago'] == 'tarjeta'){
        $total_tarjeta += $row['total'];
    }
    
    // Agrupar por mes
    $mes = date('m', strtotime($row['fecha']));
    $ventas_por_mes[$mes]['total'] += $row['total'];
    $ventas_por_mes[$mes]['cantidad']++;
}

// Calcular promedios
$meses_con_ventas = 0;
foreach($ventas_por_mes as $datos){
    if($datos['cantidad'] > 0) $meses_con_ventas++;
}
$promedio_mensual = $meses_con_ventas > 0 ? $total_anio / $meses_con_ventas : 0;

$meses = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen Anual</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
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
            color: #fa709a;
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
            font-size: 14px;
        }

        .btn-back {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
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
            background: linear-gradient(135deg, rgba(250, 112, 154, 0.1) 0%, rgba(254, 225, 64, 0.1) 100%);
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .fecha-selector label {
            font-weight: 600;
            color: #fa709a;
            font-size: 16px;
        }

        .fecha-selector select {
            padding: 10px 15px;
            border: 2px solid #fa709a;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Segoe UI', sans-serif;
            transition: all 0.3s;
            background: white;
            cursor: pointer;
        }

        .fecha-selector select:focus {
            outline: none;
            border-color: #fee140;
            box-shadow: 0 0 0 3px rgba(250, 112, 154, 0.1);
        }

        .btn-buscar, .btn-hoy {
            padding: 10px 20px;
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
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

        .resumen-card .info-extra {
            font-size: 14px;
            margin-top: 10px;
            opacity: 0.9;
        }

        .card-total {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .card-efectivo {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
        }

        .card-tarjeta {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .card-promedio {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .card-meses {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
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
            background: linear-gradient(135deg, rgba(250, 112, 154, 0.1) 0%, rgba(254, 225, 64, 0.1) 100%) !important;
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

        .grafico-barras {
            margin: 30px 0;
            padding: 20px;
            background: linear-gradient(135deg, rgba(250, 112, 154, 0.05) 0%, rgba(254, 225, 64, 0.05) 100%);
            border-radius: 12px;
        }

        .barra-container {
            display: flex;
            align-items: center;
            margin: 15px 0;
            gap: 15px;
        }

        .barra-label {
            min-width: 100px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .barra-wrapper {
            flex: 1;
            background: #e0e0e0;
            border-radius: 10px;
            height: 35px;
            position: relative;
            overflow: hidden;
        }

        .barra {
            height: 100%;
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: white;
            font-weight: 600;
            font-size: 13px;
            transition: width 1s ease-out;
        }

        .barra-monto {
            min-width: 120px;
            text-align: right;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        @media print {
    @page {
        margin: 10mm;
        size: auto;
    }

    html, body {
        background: white !important;
        padding: 0 !important;
        margin: 0 !important;
        height: auto !important;
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
        border-bottom: 3px solid #fa709a;
        border-radius: 0 !important;
        page-break-after: avoid;
        margin-bottom: 10px !important;
        margin-top: 0 !important;
        padding: 15px 10px !important;
    }

    .header h1 {
        font-size: 24px !important;
        margin: 0 !important;
    }

    .section {
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 10px !important;
        page-break-inside: avoid;
        margin-bottom: 15px !important;
        margin-top: 0 !important;
    }

    h2 {
        margin-top: 0 !important;
        margin-bottom: 10px !important;
        font-size: 18px !important;
    }

    .resumen {
        grid-template-columns: repeat(5, 1fr) !important;
        gap: 8px !important;
        page-break-inside: avoid;
        margin-bottom: 15px !important;
        margin-top: 0 !important;
    }

    .resumen-card {
        box-shadow: 0 1px 3px rgba(0,0,0,0.2) !important;
        page-break-inside: avoid;
        padding: 12px !important;
    }

    .resumen-card .monto {
        font-size: 22px !important;
    }

    .resumen-card .info-extra {
        font-size: 11px !important;
    }

    .resumen-card h3 {
        font-size: 13px !important;
        margin-bottom: 8px !important;
    }

    .grafico-barras {
        page-break-inside: avoid;
        margin: 10px 0 !important;
        padding: 10px !important;
    }

    .barra-container {
        margin: 8px 0 !important;
        gap: 10px !important;
    }

    .barra-label {
        min-width: 70px !important;
        font-size: 11px !important;
    }

    .barra-wrapper {
        height: 25px !important;
    }

    .barra {
        font-size: 10px !important;
    }

    .barra-monto {
        min-width: 90px !important;
        font-size: 11px !important;
    }

    table {
        page-break-inside: auto;
        font-size: 10px !important;
        margin-top: 10px !important;  
    }

    tr {
        page-break-inside: avoid;  
        page-break-after: auto;
    }

    thead {
        display: table-header-group;
    }

    th, td {
        padding: 5px 7px !important;
    }

    h3 {
        page-break-after: avoid;
        margin-top: 12px !important;
        margin-bottom: 8px !important;
        font-size: 16px !important;
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

            .barra-label {
                min-width: 70px;
                font-size: 12px;
            }

            .barra-monto {
                min-width: 90px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body> 
    <div class="container">
        <div class="header">
            <h1>📆 Resumen Anual</h1>
            <div class="header-buttons">
                <button class="btn-imprimir" onclick="window.print()">🖨️ Imprimir / PDF</button>
                <a href="ventas.php" class="btn-back">← Volver</a>
            </div>
        </div>

        <div class="section">
            <div class="fecha-selector">
                <form method="GET" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; width: 100%;">
                    <label for="anio">Año:</label>
                    <select id="anio" name="anio" required>
                        <?php for($i = date('Y'); $i >= 2020; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $anio == $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    
                    <button type="submit" class="btn-buscar">Buscar</button>
                    <a href="resumen_anual.php" class="btn-hoy">Año Actual</a>
                </form>
            </div>

            <h2 style="color: #fa709a; margin-bottom: 20px; font-size: 22px;">
                Año <?php echo $anio; ?>
            </h2>

            <?php if(count($ventas) == 0): ?>
                <div class="no-ventas">
                    <div class="no-ventas-icon">📊</div>
                    <p>No hay ventas registradas en este año</p>
                </div>
            <?php else: ?>
                <!-- Resumen de ventas -->
                <div class="resumen">
                    <div class="resumen-card card-total">
                        <h3>Total Anual</h3>
                        <div class="monto">$<?php echo number_format($total_anio, 2); ?></div>
                        <div class="info-extra"><?php echo count($ventas); ?> ventas</div>
                    </div>
                    <div class="resumen-card card-efectivo">
                        <h3>Efectivo</h3>
                        <div class="monto">$<?php echo number_format($total_efectivo, 2); ?></div>
                        <div class="info-extra"><?php echo $total_anio > 0 ? round(($total_efectivo/$total_anio)*100, 1) : 0; ?>%</div>
                    </div>
                    <div class="resumen-card card-tarjeta">
                        <h3>Tarjeta</h3>
                        <div class="monto">$<?php echo number_format($total_tarjeta, 2); ?></div>
                        <div class="info-extra"><?php echo $total_anio > 0 ? round(($total_tarjeta/$total_anio)*100, 1) : 0; ?>%</div>
                    </div>
                    <div class="resumen-card card-promedio">
                        <h3>Promedio Mensual</h3>
                        <div class="monto">$<?php echo number_format($promedio_mensual, 2); ?></div>
                        <div class="info-extra">Con ventas</div>
                    </div>
                    <div class="resumen-card card-meses">
                        <h3>Meses Activos</h3>
                        <div class="monto"><?php echo $meses_con_ventas; ?></div>
                        <div class="info-extra">de 12 meses</div>
                    </div>
                </div>

                <!-- Gráfico de barras por mes -->
                <h3 style="color: #fa709a; margin: 30px 0 20px 0; font-size: 20px;">📊 Ventas por Mes</h3>
                <div class="grafico-barras">
                    <?php 
                    $max_venta = 0;
                    foreach($ventas_por_mes as $datos){
                        if($datos['total'] > $max_venta) $max_venta = $datos['total'];
                    }
                    
                    foreach($ventas_por_mes as $mes_num => $datos):
                        $porcentaje = $max_venta > 0 ? ($datos['total'] / $max_venta) * 100 : 0;
                    ?>
                        <div class="barra-container">
                            <div class="barra-label"><?php echo $meses[$mes_num]; ?></div>
                            <div class="barra-wrapper">
                                <div class="barra" style="width: <?php echo $porcentaje; ?>%">
                                    <?php if($porcentaje > 15): ?>
                                        <?php echo $datos['cantidad']; ?> venta<?php echo $datos['cantidad'] != 1 ? 's' : ''; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="barra-monto">$<?php echo number_format($datos['total'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Tabla detallada por mes -->
                <h3 style="color: #fa709a; margin: 30px 0 20px 0; font-size: 20px;">📋 Desglose Mensual</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Mes</th>
                                <th>Cantidad de Ventas</th>
                                <th>Total del Mes</th>
                                <th>% del Total Anual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($ventas_por_mes as $mes_num => $datos): 
                                $porcentaje_anual = $total_anio > 0 ? ($datos['total'] / $total_anio) * 100 : 0;
                            ?>
                                <tr>
                                    <td><strong><?php echo $meses[$mes_num]; ?></strong></td>
                                    <td><?php echo $datos['cantidad']; ?> venta(s)</td>
                                    <td><strong>$<?php echo number_format($datos['total'], 2); ?></strong></td>
                                    <td><?php echo round($porcentaje_anual, 1); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="2"><strong>TOTAL DEL AÑO</strong></td>
                                <td colspan="2"><strong>$<?php echo number_format($total_anio, 2); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Top 3 mejores meses -->
                <?php 
                $ventas_por_mes_ordenado = $ventas_por_mes;
                arsort($ventas_por_mes_ordenado);
                $top_meses = array_slice($ventas_por_mes_ordenado, 0, 3, true);
                ?>
                <h3 style="color: #fa709a; margin: 30px 0 20px 0; font-size: 20px;">🏆 Top 3 Mejores Meses</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Posición</th>
                                <th>Mes</th>
                                <th>Cantidad de Ventas</th>
                                <th>Total del Mes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $posicion = 1;
                            foreach($top_meses as $mes_num => $datos): 
                                if($datos['total'] == 0) continue;
                            ?>
                                <tr>
                                    <td><strong><?php echo $posicion++; ?>°</strong></td>
                                    <td><?php echo $meses[$mes_num]; ?></td>
                                    <td><?php echo $datos['cantidad']; ?> venta(s)</td>
                                    <td><strong>$<?php echo number_format($datos['total'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Comparación trimestral -->
                <?php 
                $trimestres = [
                    'Q1' => ['meses' => ['01', '02', '03'], 'nombre' => '1er Trimestre (Ene-Mar)', 'total' => 0, 'cantidad' => 0],
                    'Q2' => ['meses' => ['04', '05', '06'], 'nombre' => '2do Trimestre (Abr-Jun)', 'total' => 0, 'cantidad' => 0],
                    'Q3' => ['meses' => ['07', '08', '09'], 'nombre' => '3er Trimestre (Jul-Sep)', 'total' => 0, 'cantidad' => 0],
                    'Q4' => ['meses' => ['10', '11', '12'], 'nombre' => '4to Trimestre (Oct-Dic)', 'total' => 0, 'cantidad' => 0],
                ];
                
                foreach($ventas_por_mes as $mes_num => $datos){
                    foreach($trimestres as $q => $trim){
                        if(in_array($mes_num, $trim['meses'])){
                            $trimestres[$q]['total'] += $datos['total'];
                            $trimestres[$q]['cantidad'] += $datos['cantidad'];
                        }
                    }
                }
                ?>
                <h3 style="color: #fa709a; margin: 30px 0 20px 0; font-size: 20px;">📈 Comparación Trimestral</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Trimestre</th>
                                <th>Cantidad de Ventas</th>
                                <th>Total del Trimestre</th>
                                <th>Promedio Mensual</th>
                                <th>% del Total Anual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($trimestres as $trim): 
                                $promedio_trim = $trim['total'] / 3;
                                $porcentaje_trim = $total_anio > 0 ? ($trim['total'] / $total_anio) * 100 : 0;
                            ?>
                                <tr>
                                    <td><strong><?php echo $trim['nombre']; ?></strong></td>
                                    <td><?php echo $trim['cantidad']; ?> venta(s)</td>
                                    <td><strong>$<?php echo number_format($trim['total'], 2); ?></strong></td>
                                    <td>$<?php echo number_format($promedio_trim, 2); ?></td>
                                    <td><?php echo round($porcentaje_trim, 1); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Comparación semestral -->
                <?php 
                $semestre1_total = 0;
                $semestre1_cantidad = 0;
                $semestre2_total = 0;
                $semestre2_cantidad = 0;
                
                foreach($ventas_por_mes as $mes_num => $datos){
                    if($mes_num <= '06'){
                        $semestre1_total += $datos['total'];
                        $semestre1_cantidad += $datos['cantidad'];
                    } else {
                        $semestre2_total += $datos['total'];
                        $semestre2_cantidad += $datos['cantidad'];
                    }
                }
                ?>
                <h3 style="color: #fa709a; margin: 30px 0 20px 0; font-size: 20px;">📊 Comparación Semestral</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Semestre</th>
                                <th>Cantidad de Ventas</th>
                                <th>Total del Semestre</th>
                                <th>% del Total Anual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>1er Semestre (Ene-Jun)</strong></td>
                                <td><?php echo $semestre1_cantidad; ?> venta(s)</td>
                                <td><strong>$<?php echo number_format($semestre1_total, 2); ?></strong></td>
                                <td><?php echo $total_anio > 0 ? round(($semestre1_total / $total_anio) * 100, 1) : 0; ?>%</td>
                            </tr>
                            <tr>
                                <td><strong>2do Semestre (Jul-Dic)</strong></td>
                                <td><?php echo $semestre2_cantidad; ?> venta(s)</td>
                                <td><strong>$<?php echo number_format($semestre2_total, 2); ?></strong></td>
                                <td><?php echo $total_anio > 0 ? round(($semestre2_total / $total_anio) * 100, 1) : 0; ?>%</td>
                            </tr>
                            <tr class="total-row">
                                <td><strong>TOTAL ANUAL</strong></td>
                                <td><strong><?php echo count($ventas); ?> ventas</strong></td>
                                <td colspan="2"><strong>$<?php echo number_format($total_anio, 2); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>   