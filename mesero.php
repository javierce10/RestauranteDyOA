<?php
session_start();
include('includes/conexion.php');

// Validar rol
if(!isset($_SESSION['rol']) || $_SESSION['rol'] != 'mesero'){
    header('Location: login.php');
    exit();
}

// Obtener ID y nombre del mesero actual
$mesero_usuario = $_SESSION['usuario'];
$mesero_id = $_SESSION['id'];

// API AJAX para consultar platillos listos
if(isset($_GET['ajax']) && $_GET['ajax'] == 'platillos_listos'){
    header('Content-Type: application/json');
    
    $query = "
        SELECT 
            dp.id as detalle_id,
            dp.cantidad,
            dp.notas,
            p.mesa,
            prod.nombre as platillo
        FROM detalle_pedido dp
        INNER JOIN pedidos p ON dp.pedido_id = p.id
        INNER JOIN productos prod ON dp.producto_id = prod.id
        WHERE p.estado = 'pendiente' 
        AND p.mesero_id = ?
        AND prod.categoria = 'comida'
        AND dp.estado_preparacion = 'listo'
        ORDER BY p.mesa ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $mesero_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $platillos = [];
    while($row = $result->fetch_assoc()){
        $platillos[] = [
            'detalle_id' => $row['detalle_id'],
            'cantidad' => $row['cantidad'],
            'notas' => $row['notas'],
            'mesa' => $row['mesa'],
            'platillo' => $row['platillo']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'platillos' => $platillos,
        'total' => count($platillos),
        'timestamp' => time()
    ]);
    exit();
}

// Inicializar carrito en sesión si no existe
if(!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Manejo de agregar al carrito
if(isset($_POST['agregar_carrito'])){
    $mesa = $_POST['mesa'];
    $productos_pedido = $_POST['producto'] ?? []; 
    $notas = $_POST['notas'] ?? [];
    
    // Validar que haya al menos un producto
    $tiene_producto = false;
    foreach($productos_pedido as $cantidad){
        if($cantidad > 0){
            $tiene_producto = true;
            break;
        }
    }
    
    if(!$tiene_producto){
        $_SESSION['mensaje_pedido'] = 'sin_productos';
        header("Location: mesero.php?mesa=$mesa");
        exit();
    }
    
    // Agregar productos al carrito
    foreach($productos_pedido as $id_prod => $cantidad){
        if($cantidad > 0){
            $res = $conn->query("SELECT nombre, precio, categoria FROM productos WHERE id=$id_prod");
            $prod = $res->fetch_assoc();
            
            $_SESSION['carrito'][] = [
                'producto_id' => $id_prod,
                'nombre' => $prod['nombre'],
                'cantidad' => $cantidad,
                'precio_unitario' => $prod['precio'],
                'categoria' => $prod['categoria'],
                'notas' => $notas[$id_prod] ?? ''
            ];
        }
    }
    
    $_SESSION['mensaje_pedido'] = 'agregado_carrito';
    $_SESSION['carrito_mesa'] = $mesa;
    header("Location: mesero.php?mesa=$mesa");
    exit();
}

// Manejo de eliminar del carrito
if(isset($_POST['eliminar_carrito'])){
    $indice = $_POST['indice'];
    $mesa = $_POST['mesa'];
    
    if(isset($_SESSION['carrito'][$indice])){
        unset($_SESSION['carrito'][$indice]);
        $_SESSION['carrito'] = array_values($_SESSION['carrito']); // Reindexar
    }
    
    $_SESSION['mensaje_pedido'] = 'eliminado_carrito';
    header("Location: mesero.php?mesa=$mesa");
    exit();
}

// Manejo de vaciar carrito
if(isset($_POST['vaciar_carrito'])){
    $mesa = $_POST['mesa'];
    $_SESSION['carrito'] = [];
    unset($_SESSION['carrito_mesa']);
    
    $_SESSION['mensaje_pedido'] = 'carrito_vaciado';
    header("Location: mesero.php?mesa=$mesa");
    exit();
}

// Manejo de confirmar pedido (enviar a cocina)
if(isset($_POST['confirmar_pedido'])){
    $mesa = $_POST['mesa'];
    
    if(empty($_SESSION['carrito'])){
        $_SESSION['mensaje_pedido'] = 'carrito_vacio';
        header("Location: mesero.php?mesa=$mesa");
        exit();
    }
    
    // Verificar si la mesa tiene pedido pendiente
    $res_pedido = $conn->query("SELECT id, total, mesero_id, mesero_nombre FROM pedidos WHERE mesa='$mesa' AND estado='pendiente' ORDER BY fecha_hora DESC LIMIT 1");
    
    if($res_pedido->num_rows > 0){
        // La mesa ya tiene un pedido - VERIFICAR que sea el mismo mesero
        $pedido = $res_pedido->fetch_assoc();
        
        if($pedido['mesero_id'] != $mesero_id){
            $_SESSION['mensaje_pedido'] = 'sin_permiso';
            $_SESSION['mesero_encargado'] = $pedido['mesero_nombre'];
            header("Location: mesero.php?mesa=$mesa");
            exit();
        }
        
        $pedido_id = $pedido['id'];
        $total_pedido = $pedido['total'];
    } else {
        // Crear nuevo pedido
        $total_pedido = 0;
        $stmt = $conn->prepare("INSERT INTO pedidos (mesa, total, mesero_id, mesero_nombre) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sdis", $mesa, $total_pedido, $mesero_id, $mesero_usuario);
        $stmt->execute();
        $pedido_id = $stmt->insert_id;
    }
    
    // Agregar productos del carrito al pedido
    foreach($_SESSION['carrito'] as $item){
        $estado_inicial = ($item['categoria'] == 'bebida') ? 'listo' : 'pendiente';
        
        $stmt_det = $conn->prepare("INSERT INTO detalle_pedido (pedido_id, producto_id, cantidad, precio_unitario, notas, estado_preparacion) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_det->bind_param("iiidss", $pedido_id, $item['producto_id'], $item['cantidad'], $item['precio_unitario'], $item['notas'], $estado_inicial);
        $stmt_det->execute();
        
        $total_pedido += $item['precio_unitario'] * $item['cantidad'];
    }
    
    // Actualizar total del pedido
    $stmt_total = $conn->prepare("UPDATE pedidos SET total=? WHERE id=?");
    $stmt_total->bind_param("di", $total_pedido, $pedido_id);
    $stmt_total->execute();
    
    // Limpiar carrito
    $_SESSION['carrito'] = [];
    unset($_SESSION['carrito_mesa']);
    
    $_SESSION['mensaje_pedido'] = 'pedido_confirmado';
    header("Location: mesero.php?mesa=$mesa");
    exit();
}

// Manejo de marcar bebida como entregada
if(isset($_POST['entregar_bebida'])){
    $detalle_id = $_POST['detalle_id'];
    $pedido_id = $_POST['pedido_id'];
    $mesa = $_POST['mesa'];
    
    $res_pedido_verificar = $conn->query("SELECT mesero_id, mesero_nombre FROM pedidos WHERE id=$pedido_id");
    $pedido_verificar = $res_pedido_verificar->fetch_assoc();
    
    if($pedido_verificar['mesero_id'] != $mesero_id){
        $_SESSION['mensaje_pedido'] = 'sin_permiso';
        $_SESSION['mesero_encargado'] = $pedido_verificar['mesero_nombre'];
        header("Location: mesero.php?mesa=$mesa");
        exit();
    }
    
    $conn->query("UPDATE detalle_pedido SET estado_preparacion='entregado' WHERE id=$detalle_id");
    
    $_SESSION['mensaje_pedido'] = 'bebida_entregada';
    header("Location: mesero.php?mesa=$mesa");
    exit();
}

// Manejo de eliminación de producto del pedido
if(isset($_POST['eliminar_producto'])){
    $detalle_id = $_POST['detalle_id'];
    $pedido_id = $_POST['pedido_id'];
    $mesa = $_POST['mesa'];
    
    $res_pedido_verificar = $conn->query("SELECT mesero_id, mesero_nombre FROM pedidos WHERE id=$pedido_id");
    $pedido_verificar = $res_pedido_verificar->fetch_assoc();
    
    if($pedido_verificar['mesero_id'] != $mesero_id){
        $_SESSION['mensaje_pedido'] = 'sin_permiso_eliminar';
        $_SESSION['mesero_encargado'] = $pedido_verificar['mesero_nombre'];
        header("Location: mesero.php?mesa=$mesa");
        exit();
    }
    
    $res_detalle = $conn->query("SELECT cantidad, precio_unitario FROM detalle_pedido WHERE id=$detalle_id");
    $detalle = $res_detalle->fetch_assoc();
    $monto_eliminar = $detalle['cantidad'] * $detalle['precio_unitario'];
    
    $conn->query("DELETE FROM detalle_pedido WHERE id=$detalle_id");
    $conn->query("UPDATE pedidos SET total = total - $monto_eliminar WHERE id=$pedido_id");
    
    $_SESSION['mensaje_pedido'] = 'producto_eliminado';
    header("Location: mesero.php?mesa=$mesa");
    exit();
}

// Obtener productos disponibles
$productos = $conn->query("SELECT * FROM productos WHERE disponible=1 ORDER BY categoria, nombre");

// Obtener mesas
$config_file = 'includes/mesas_config.php';
if(file_exists($config_file)) {
    $mesas = include($config_file);
} else {
    $mesas = ['Mesa 1','Mesa 2','Mesa 3','Mesa 4','Mesa 5','Mesa 6'];
}

// Obtener mesas ocupadas
$mesas_info = [];
$res_mesas = $conn->query("SELECT mesa, mesero_nombre, mesero_id FROM pedidos WHERE estado='pendiente'");
while($fila = $res_mesas->fetch_assoc()){
    $mesas_info[$fila['mesa']] = [
        'mesero_nombre' => $fila['mesero_nombre'],
        'mesero_id' => $fila['mesero_id'],
        'es_encargado' => ($fila['mesero_id'] == $mesero_id)
    ];
}

// Capturar mesa seleccionada
$mesa_seleccionada = $_GET['mesa'] ?? $_POST['mesa'] ?? null;

// Obtener y limpiar mensajes
$mensaje_mostrar = null;
$mesa_mensaje = null;
$mesero_encargado_msg = null;
if(isset($_SESSION['mensaje_pedido'])){
    $mensaje_mostrar = $_SESSION['mensaje_pedido'];
    $mesa_mensaje = $_SESSION['mesa_mensaje'] ?? $mesa_seleccionada;
    $mesero_encargado_msg = $_SESSION['mesero_encargado'] ?? null;
    unset($_SESSION['mensaje_pedido']);
    unset($_SESSION['mesa_mensaje']);
    unset($_SESSION['mesero_encargado']);
}

// Obtener pedido actual de la mesa
$pedido_actual = null;
$items_pedido = [];
$total_pedido_mesa = 0;
$mesero_encargado_mesa = null;
$soy_encargado = false;
if($mesa_seleccionada){
    $res_pedido = $conn->query("SELECT id, total, mesero_id, mesero_nombre FROM pedidos WHERE mesa='$mesa_seleccionada' AND estado='pendiente' ORDER BY fecha_hora DESC LIMIT 1");
    if($res_pedido->num_rows > 0){
        $pedido_actual = $res_pedido->fetch_assoc();
        $total_pedido_mesa = $pedido_actual['total'];
        $mesero_encargado_mesa = $pedido_actual['mesero_nombre'];
        $soy_encargado = ($pedido_actual['mesero_id'] == $mesero_id);
        
        $pedido_id = $pedido_actual['id'];
        $res_items = $conn->query("
            SELECT dp.id as detalle_id, dp.cantidad, dp.precio_unitario, dp.notas, 
                   dp.estado_preparacion,
                   p.nombre, p.categoria
            FROM detalle_pedido dp
            JOIN productos p ON dp.producto_id = p.id
            WHERE dp.pedido_id = $pedido_id
            ORDER BY p.categoria, p.nombre
        ");
        while($item = $res_items->fetch_assoc()){
            $items_pedido[] = $item;
        }
    }
}

// Organizar productos por categoría
$productos_por_categoria = [];
while($prod = $productos->fetch_assoc()){
    $categoria = ucfirst($prod['categoria']);
    $productos_por_categoria[$categoria][] = $prod;
}

// Calcular total del carrito
$total_carrito = 0;
foreach($_SESSION['carrito'] as $item){
    $total_carrito += $item['cantidad'] * $item['precio_unitario'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesero - Tomar Pedidos</title>
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

        .btn-entregas, .btn-logout {
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            color: white;
        }

        .btn-entregas {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            box-shadow: 0 4px 10px rgba(240, 147, 251, 0.3);
            position: relative;
        }

        .badge-notificacion {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4444;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            animation: pulseNotification 1.5s infinite;
        }

        @keyframes pulseNotification {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }

        .btn-logout {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            box-shadow: 0 4px 10px rgba(235, 51, 73, 0.3);
        }

        .btn-entregas:hover, .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.3);
        }

        /* Controles de audio */
        .controles-audio {
            background: white;
            padding: 15px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-control-audio {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            color: white;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .btn-control-audio:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }

        /* Estilos para notificaciones flotantes */
        .notificacion-flotante {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            animation: slideInRight 0.5s ease, pulse 2s infinite;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        .notificacion-contenido {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            display: flex;
            gap: 15px;
            align-items: flex-start;
            max-width: 400px;
            min-width: 300px;
        }

        .notificacion-icono {
            font-size: 40px;
            animation: ring 1s ease infinite;
        }

        @keyframes ring {
            0%, 100% {
                transform: rotate(0deg);
            }
            10%, 30% {
                transform: rotate(-10deg);
            }
            20%, 40% {
                transform: rotate(10deg);
            }
            50% {
                transform: rotate(0deg);
            }
        }

        .notificacion-texto {
            flex: 1;
        }

        .notificacion-texto strong {
            display: block;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .notificacion-texto div {
            background: rgba(255,255,255,0.2);
            padding: 8px 12px;
            border-radius: 8px;
            margin: 5px 0;
            font-size: 14px;
        }

        .notificacion-cerrar {
            background: rgba(255,255,255,0.3);
            border: none;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            transition: all 0.3s;
        }

        .notificacion-cerrar:hover {
            background: rgba(255,255,255,0.5);
            transform: rotate(90deg);
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

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            margin-bottom: 25px;
        }

        .section h3 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 22px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .collapse-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            user-select: none;
            padding: 5px 0;
            transition: all 0.3s ease;
        }

        .collapse-header:hover {
            opacity: 0.8;
        }

        .collapse-icon {
            font-size: 24px;
            transition: transform 0.3s ease;
            color: #667eea;
            font-weight: bold;
        }

        .collapse-icon.collapsed {
            transform: rotate(-90deg);
        }

        .collapse-content {
            max-height: 5000px;
            overflow: hidden;
            transition: max-height 0.4s ease, opacity 0.3s ease, margin-top 0.3s ease;
            opacity: 1;
            margin-top: 15px;
        }

        .collapse-content.collapsed {
            max-height: 0;
            opacity: 0;
            margin-top: 0;
        }

        .mesas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .mesa {
            padding: 20px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: relative;
        }

        .mesa.libre {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            color: white;
        }

        .mesa.ocupada-encargado {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .mesa.ocupada-otro {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .mesa.selected {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        .mesero-badge {
            display: inline-block;
            background: rgba(255,255,255,0.3);
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            margin-top: 5px;
        }

        .orden-actual {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-radius: 12px;
            padding: 20px;
        }

        .mesero-encargado-badge {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: white;
            box-shadow: 0 2px 5px rgba(240, 147, 251, 0.3);
        }

        .mesero-encargado-badge.soy-encargado {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
        }

        .item-orden {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            gap: 10px;
            flex-wrap: wrap;
        }

        .item-info {
            flex: 1;
            min-width: 200px;
        }

        .item-nombre {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .item-detalles {
            font-size: 13px;
            color: #666;
        }

        .item-notas {
            font-size: 12px;
            color: #999;
            font-style: italic;
            margin-top: 5px;
        }

        .item-precio {
            font-weight: bold;
            color: #667eea;
            font-size: 16px;
            margin: 0 15px;
        }

        .item-estado {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 5px;
        }

        .estado-pendiente {
            background: #fff3cd;
            color: #856404;
        }

        .estado-en_preparacion {
            background: #f093fb;
            color: white;
        }

        .estado-listo {
            background: #4facfe;
            color: white;
        }

        .estado-entregado {
            background: #56ab2f;
            color: white;
        }

        .item-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-eliminar, .btn-entregar-bebida {
            padding: 8px 15px;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 13px;
        }

        .btn-eliminar {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        }

        .btn-entregar-bebida {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
        }

        .btn-eliminar:hover, .btn-entregar-bebida:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }

        .btn-eliminar:disabled, .btn-entregar-bebida:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-eliminar:disabled:hover, .btn-entregar-bebida:disabled:hover {
            transform: none;
            box-shadow: none;
        }

        /* Estilos para el Carrito */
        .carrito-section {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(240, 147, 251, 0.4);
            margin-bottom: 25px;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .carrito-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255,255,255,0.3);
        }

        .carrito-header h3 {
            color: white;
            border: none;
            margin: 0;
            padding: 0;
        }

        .carrito-badge {
            background: white;
            color: #f5576c;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }

        .carrito-item {
            background: rgba(255,255,255,0.95);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .carrito-item-info {
            flex: 1;
            min-width: 200px;
        }

        .carrito-item-nombre {
            font-weight: 600;
            color: #333;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .carrito-item-detalles {
            color: #666;
            font-size: 14px;
        }

        .carrito-item-notas {
            background: #fff3cd;
            padding: 8px 12px;
            border-radius: 6px;
            margin-top: 8px;
            font-size: 13px;
            color: #856404;
            font-style: italic;
        }

        .carrito-item-precio {
            font-weight: bold;
            color: #f5576c;
            font-size: 18px;
        }

        .btn-eliminar-carrito {
            padding: 8px 15px;
            background: #eb3349;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 13px;
        }

        .btn-eliminar-carrito:hover {
            background: #c62828;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }

        .carrito-acciones {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn-vaciar-carrito {
            flex: 1;
            padding: 15px 25px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid white;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
        }

        .btn-vaciar-carrito:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .btn-confirmar-pedido {
            flex: 2;
            padding: 15px 25px;
            background: white;
            color: #f5576c;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 18px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .btn-confirmar-pedido:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .carrito-total {
            background: rgba(255,255,255,0.2);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-top: 20px;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .carrito-total-label {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .carrito-total-amount {
            font-size: 36px;
            font-weight: bold;
        }

        .carrito-vacio {
            text-align: center;
            padding: 40px;
            opacity: 0.8;
        }

        .carrito-vacio-icon {
            font-size: 64px;
            margin-bottom: 15px;
        }

        .menu-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 15px 30px;
            border: 3px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 16px;
            flex: 1;
            min-width: 150px;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: scale(1.05);
            box-shadow: 0 6px 15px rgba(102, 126, 234, 0.4);
        }

        .tab-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }

        .search-box {
            margin-bottom: 20px;
        }

        .search-input {
            width: 100%;
            padding: 15px 20px;
            border: 3px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
            background: white;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .categoria-content {
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .producto-item.oculto {
            display: none;
        }

        .productos-lista {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .producto-item {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s;
        }

        .producto-item:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .producto-nombre-precio {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .producto-nombre {
            font-weight: 600;
            color: #333;
            font-size: 15px;
            flex: 1;
        }

        .producto-precio {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 13px;
            margin-left: 10px;
        }

        .cantidad-control {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .btn-cantidad {
            width: 35px;
            height: 35px;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 50%;
            font-weight: bold;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cantidad:hover {
            background: #667eea;
            color: white;
        }

        .cantidad-display {
            width: 50px;
            text-align: center;
            font-weight: bold;
            font-size: 18px;
            color: #667eea;
        }

        .notas-input {
            width: 100%;
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
            resize: vertical;
            min-height: 40px;
        }

        .notas-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .total-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            margin-top: 25px;
        }

        .total-amount {
            font-size: 36px;
            font-weight: bold;
        }

        .btn-submit {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(240, 147, 251, 0.6);
        }

        .info-colaboracion {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 12px 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 14px;
            color: #1565c0;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            .mesas-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .productos-lista {
                grid-template-columns: 1fr;
            }
            .item-orden, .carrito-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .item-actions, .carrito-acciones {
                width: 100%;
            }
            .btn-eliminar, .btn-entregar-bebida, .btn-eliminar-carrito {
                flex: 1;
            }
            .notificacion-flotante {
                right: 10px;
                left: 10px;
            }
            .notificacion-contenido {
                min-width: auto;
            }
        }
</style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🍽️ Panel de Mesero</h1>
            <div class="header-actions">
                <span class="user-info">👤 <?php echo htmlspecialchars($mesero_usuario); ?></span>
                <a href="mesero_entregas.php" class="btn-entregas" id="btn-ver-entregas">
                    🔔 Ver Entregas
                    <span class="badge-notificacion" id="badge-entregas" style="display:none;">0</span>
                </a>
                <a href="logout.php" class="btn-logout">🚪 Cerrar Sesión</a>
            </div>
        </div>

        <!-- Controles de Audio -->
        <div class="controles-audio">
            <button class="btn-control-audio" onclick="sistemaSonido.toggleSonido()" id="btn-toggle-sonido" style="background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);">
                🔊 Sonido: ON
            </button>
            <button class="btn-control-audio" onclick="sistemaSonido.probarSonido()" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                🎵 Probar Sonido
            </button>
        </div>

        <!-- Mensajes -->
        <?php if($mensaje_mostrar): ?>
            <div class="alert <?php 
                if(in_array($mensaje_mostrar, ['pedido_confirmado', 'bebida_entregada', 'agregado_carrito'])) echo 'alert-success';
                elseif(in_array($mensaje_mostrar, ['sin_permiso', 'sin_permiso_eliminar'])) echo 'alert-error';
                elseif(in_array($mensaje_mostrar, ['sin_productos', 'carrito_vacio'])) echo 'alert-warning';
                else echo 'alert-info';
            ?>">
                <?php
                switch($mensaje_mostrar) {
                    case 'pedido_confirmado':
                        echo "✅ Pedido enviado a cocina exitosamente para Mesa $mesa_mensaje";
                        break;
                    case 'bebida_entregada':
                        echo "✅ Bebida marcada como entregada";
                        break;
                    case 'sin_permiso':
                        echo "⚠️ No puedes modificar esta mesa. Está siendo atendida por: " . htmlspecialchars($mesero_encargado_msg);
                        break;
                    case 'sin_permiso_eliminar':
                        echo "⚠️ No puedes eliminar productos de esta mesa. Está siendo atendida por: " . htmlspecialchars($mesero_encargado_msg);
                        break;
                    case 'producto_eliminado':
                        echo "✅ Producto eliminado del pedido";
                        break;
                    case 'sin_productos':
                        echo "⚠️ Debes agregar al menos un producto al carrito";
                        break;
                    case 'agregado_carrito':
                        echo "✅ Productos agregados al carrito";
                        break;
                    case 'eliminado_carrito':
                        echo "ℹ️ Producto eliminado del carrito";
                        break;
                    case 'carrito_vaciado':
                        echo "ℹ️ Carrito vaciado";
                        break;
                    case 'carrito_vacio':
                        echo "⚠️ El carrito está vacío. Agrega productos antes de confirmar";
                        break;
                }
                ?>
            </div>
        <?php endif; ?>

        <!-- Selección de Mesa -->
        <div class="section">
            <h3>
                <div class="collapse-header" onclick="toggleCollapse('mesas')">
                    <span>📋 Seleccionar Mesa</span>
                    <span class="collapse-icon" id="icon-mesas">▼</span>
                </div>
            </h3>
            <div class="collapse-content" id="content-mesas">
                <div class="mesas-grid">
                    <?php foreach($mesas as $mesa): 
                        $estado_mesa = 'libre';
                        $mesero_label = '';
                        
                        if(isset($mesas_info[$mesa])){
                            if($mesas_info[$mesa]['es_encargado']){
                                $estado_mesa = 'ocupada-encargado';
                                $mesero_label = '<div class="mesero-badge">👤 Tu mesa</div>';
                            } else {
                                $estado_mesa = 'ocupada-otro';
                                $mesero_label = '<div class="mesero-badge">👤 ' . htmlspecialchars($mesas_info[$mesa]['mesero_nombre']) . '</div>';
                            }
                        }
                        
                        $selected = ($mesa_seleccionada == $mesa) ? 'selected' : '';
                    ?>
                        <button type="button" 
                                class="mesa <?php echo $estado_mesa . ' ' . $selected; ?>"
                                onclick="window.location.href='mesero.php?mesa=<?php echo urlencode($mesa); ?>'">
                            <?php echo htmlspecialchars($mesa); ?>
                            <?php echo $mesero_label; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if($mesa_seleccionada): ?>
            
            <!-- Carrito de Compras -->
            <?php if(!empty($_SESSION['carrito']) && $_SESSION['carrito_mesa'] == $mesa_seleccionada): ?>
                <div class="carrito-section">
                    <div class="carrito-header">
                        <h3>🛒 Carrito - <?php echo htmlspecialchars($mesa_seleccionada); ?></h3>
                        <span class="carrito-badge"><?php echo count($_SESSION['carrito']); ?> productos</span>
                    </div>

                    <?php foreach($_SESSION['carrito'] as $index => $item): ?>
                        <div class="carrito-item">
                            <div class="carrito-item-info">
                                <div class="carrito-item-nombre"><?php echo htmlspecialchars($item['nombre']); ?></div>
                                <div class="carrito-item-detalles">
                                    Cantidad: <?php echo $item['cantidad']; ?> × $<?php echo number_format($item['precio_unitario'], 2); ?>
                                </div>
                                <?php if(!empty($item['notas'])): ?>
                                    <div class="carrito-item-notas">
                                        📝 <?php echo htmlspecialchars($item['notas']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="carrito-item-precio">
                                $<?php echo number_format($item['cantidad'] * $item['precio_unitario'], 2); ?>
                            </div>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="indice" value="<?php echo $index; ?>">
                                <input type="hidden" name="mesa" value="<?php echo htmlspecialchars($mesa_seleccionada); ?>">
                                <button type="submit" name="eliminar_carrito" class="btn-eliminar-carrito">🗑️ Eliminar</button>
                            </form>
                        </div>
                    <?php endforeach; ?>

                    <div class="carrito-total">
                        <div class="carrito-total-label">Total del Carrito:</div>
                        <div class="carrito-total-amount">$<?php echo number_format($total_carrito, 2); ?></div>
                    </div>

                    <div class="carrito-acciones">
                        <form method="POST" style="flex: 1;">
                            <input type="hidden" name="mesa" value="<?php echo htmlspecialchars($mesa_seleccionada); ?>">
                            <button type="submit" name="vaciar_carrito" class="btn-vaciar-carrito" 
                                    onclick="return confirm('¿Estás seguro de vaciar el carrito?')">
                                🗑️ Vaciar Carrito
                            </button>
                        </form>
                        <form method="POST" style="flex: 2;">
                            <input type="hidden" name="mesa" value="<?php echo htmlspecialchars($mesa_seleccionada); ?>">
                            <button type="submit" name="confirmar_pedido" class="btn-confirmar-pedido">
                                ✅ Confirmar y Enviar a Cocina
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Orden Actual de la Mesa -->
            <?php if($pedido_actual): ?>
                <div class="section">
                    <h3>
                        <div class="collapse-header" onclick="toggleCollapse('orden')">
                            <span>📝 Orden Actual - <?php echo htmlspecialchars($mesa_seleccionada); ?></span>
                            <span class="collapse-icon" id="icon-orden">▼</span>
                        </div>
                    </h3>
                    <div class="collapse-content" id="content-orden">
                        <div class="orden-actual">
                            <?php if($mesero_encargado_mesa): ?>
                                <div class="mesero-encargado-badge <?php echo $soy_encargado ? 'soy-encargado' : ''; ?>">
                                    👤 Mesero: <?php echo htmlspecialchars($mesero_encargado_mesa); ?>
                                    <?php if($soy_encargado): ?>
                                        <span style="margin-left: 10px;">✓ Tú eres el encargado</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if(empty($items_pedido)): ?>
                                <p style="text-align: center; color: #999; padding: 20px;">
                                    No hay productos en esta orden todavía.
                                </p>
                            <?php else: ?>
                                <?php foreach($items_pedido as $item): ?>
                                    <div class="item-orden">
                                        <div class="item-info">
                                            <div class="item-nombre"><?php echo htmlspecialchars($item['nombre']); ?></div>
                                            <div class="item-detalles">
                                                Cantidad: <?php echo $item['cantidad']; ?> × $<?php echo number_format($item['precio_unitario'], 2); ?>
                                                <span class="item-estado estado-<?php echo $item['estado_preparacion']; ?>">
                                                    <?php 
                                                    $estados_texto = [
                                                        'pendiente' => '⏳ Pendiente',
                                                        'en_preparacion' => '👨‍🍳 En Preparación',
                                                        'listo' => '✅ Listo',
                                                        'entregado' => '🎉 Entregado'
                                                    ];
                                                    echo $estados_texto[$item['estado_preparacion']] ?? $item['estado_preparacion'];
                                                    ?>
                                                </span>
                                            </div>
                                            <?php if(!empty($item['notas'])): ?>
                                                <div class="item-notas">📝 <?php echo htmlspecialchars($item['notas']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="item-precio">
                                            $<?php echo number_format($item['cantidad'] * $item['precio_unitario'], 2); ?>
                                        </div>
                                        <?php if($soy_encargado): ?>
                                            <div class="item-actions">
                                                <?php if($item['categoria'] == 'bebida' && $item['estado_preparacion'] == 'listo'): ?>
                                                    <form method="POST" style="margin: 0;">
                                                        <input type="hidden" name="detalle_id" value="<?php echo $item['detalle_id']; ?>">
                                                        <input type="hidden" name="pedido_id" value="<?php echo $pedido_actual['id']; ?>">
                                                        <input type="hidden" name="mesa" value="<?php echo htmlspecialchars($mesa_seleccionada); ?>">
                                                        <button type="submit" name="entregar_bebida" class="btn-entregar-bebida">
                                                            ✓ Marcar Entregada
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" style="margin: 0;">
                                                    <input type="hidden" name="detalle_id" value="<?php echo $item['detalle_id']; ?>">
                                                    <input type="hidden" name="pedido_id" value="<?php echo $pedido_actual['id']; ?>">
                                                    <input type="hidden" name="mesa" value="<?php echo htmlspecialchars($mesa_seleccionada); ?>">
                                                    <button type="submit" name="eliminar_producto" class="btn-eliminar"
                                                            onclick="return confirm('¿Eliminar este producto del pedido?')"
                                                            <?php echo ($item['estado_preparacion'] == 'entregado') ? 'disabled' : ''; ?>>
                                                        🗑️ Eliminar
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <div class="total-section">
                                    <div style="font-size: 18px; margin-bottom: 10px;">Total de la Mesa:</div>
                                    <div class="total-amount">$<?php echo number_format($total_pedido_mesa, 2); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if(!$soy_encargado && $mesero_encargado_mesa): ?>
                                <div class="info-colaboracion">
                                    ℹ️ Esta mesa está siendo atendida por <strong><?php echo htmlspecialchars($mesero_encargado_mesa); ?></strong>. 
                                    Solo puedes ver la orden, pero no modificarla.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Agregar Productos -->
            <div class="section">
                <h3>
                    <div class="collapse-header" onclick="toggleCollapse('menu')">
                        <span>🍽️ Agregar Productos - <?php echo htmlspecialchars($mesa_seleccionada); ?></span>
                        <span class="collapse-icon" id="icon-menu">▼</span>
                    </div>
                </h3>
                <div class="collapse-content" id="content-menu">
                    <form method="POST" id="form-pedido">
                        <input type="hidden" name="mesa" value="<?php echo htmlspecialchars($mesa_seleccionada); ?>">
                        
                        <!-- Búsqueda -->
                        <div class="search-box">
                            <input type="text" 
                                   id="search-productos" 
                                   class="search-input" 
                                   placeholder="🔍 Buscar productos...">
                        </div>

                        <!-- Tabs de Categorías -->
                        <div class="menu-tabs">
                            <button type="button" class="tab-btn active" onclick="cambiarCategoria('todas')">
                                Todas
                            </button>
                            <?php foreach(array_keys($productos_por_categoria) as $cat): ?>
                                <button type="button" class="tab-btn" onclick="cambiarCategoria('<?php echo strtolower($cat); ?>')">
                                    <?php echo $cat; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Productos -->
                        <?php foreach($productos_por_categoria as $categoria => $prods): ?>
                            <div class="categoria-content" data-categoria="<?php echo strtolower($categoria); ?>">
                                <div class="productos-lista">
                                    <?php foreach($prods as $prod): ?>
                                        <div class="producto-item" data-nombre="<?php echo strtolower($prod['nombre']); ?>">
                                            <div class="producto-nombre-precio">
                                                <span class="producto-nombre"><?php echo htmlspecialchars($prod['nombre']); ?></span>
                                                <span class="producto-precio">$<?php echo number_format($prod['precio'], 2); ?></span>
                                            </div>
                                            
                                            <div class="cantidad-control">
                                                <button type="button" class="btn-cantidad" onclick="cambiarCantidad(<?php echo $prod['id']; ?>, -1)">−</button>
                                                <input type="number" 
                                                       class="cantidad-display" 
                                                       id="cant_<?php echo $prod['id']; ?>" 
                                                       name="producto[<?php echo $prod['id']; ?>]" 
                                                       value="0" 
                                                       min="0" 
                                                       readonly>
                                                <button type="button" class="btn-cantidad" onclick="cambiarCantidad(<?php echo $prod['id']; ?>, 1)">+</button>
                                            </div>
                                            
                                            <textarea class="notas-input" 
                                                      name="notas[<?php echo $prod['id']; ?>]" 
                                                      placeholder="Notas especiales (opcional)..."
                                                      rows="2"></textarea>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <button type="submit" name="agregar_carrito" class="btn-submit">
                            🛒 Agregar al Carrito
                        </button>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <div class="section">
                <p style="text-align: center; color: #999; padding: 40px; font-size: 18px;">
                    👆 Selecciona una mesa para comenzar
                </p>
            </div>
        <?php endif; ?>

    </div>

    <script>
        // Sistema de Sonido con Web Audio API (el mismo que funciona en mesero_entregas.php)
        class SistemaSonido {
            constructor() {
                this.sonidoHabilitado = localStorage.getItem('sonidoHabilitado') !== 'false';
                this.audioContext = null;
                this.actualizarBotonSonido();
            }

            inicializarAudio() {
                if (!this.audioContext) {
                    try {
                        this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    } catch (e) {
                        console.error('Web Audio API no soportada:', e);
                    }
                }
            }

            reproducirSonido() {
                if (!this.sonidoHabilitado) return;
                
                this.inicializarAudio();
                
                if (!this.audioContext) return;
                
                if (this.audioContext.state === 'suspended') {
                    this.audioContext.resume();
                }
                
                const now = this.audioContext.currentTime;
                
                // Tres tonos agradables
                this.crearTono(800, now, 0.15);
                this.crearTono(1000, now + 0.15, 0.15);
                this.crearTono(1200, now + 0.3, 0.2);
            }

            crearTono(frecuencia, tiempo, duracion) {
                const oscilador = this.audioContext.createOscillator();
                const ganancia = this.audioContext.createGain();
                
                oscilador.connect(ganancia);
                ganancia.connect(this.audioContext.destination);
                
                oscilador.frequency.value = frecuencia;
                oscilador.type = 'sine';
                
                ganancia.gain.setValueAtTime(0.3, tiempo);
                ganancia.gain.exponentialRampToValueAtTime(0.01, tiempo + duracion);
                
                oscilador.start(tiempo);
                oscilador.stop(tiempo + duracion);
            }

            toggleSonido() {
                this.sonidoHabilitado = !this.sonidoHabilitado;
                localStorage.setItem('sonidoHabilitado', this.sonidoHabilitado);
                this.actualizarBotonSonido();
            }

            actualizarBotonSonido() {
                const btn = document.getElementById('btn-toggle-sonido');
                if (btn) {
                    btn.textContent = this.sonidoHabilitado ? '🔊 Sonido: ON' : '🔇 Sonido: OFF';
                    btn.style.background = this.sonidoHabilitado 
                        ? 'linear-gradient(135deg, #56ab2f 0%, #a8e063 100%)'
                        : 'linear-gradient(135deg, #eb3349 0%, #f45c43 100%)';
                }
            }

            probarSonido() {
                this.reproducirSonido();
                mostrarNotificacionFlotante({
                    detalle_id: 'test_' + Date.now(),
                    mesa: 'Mesa de Prueba',
                    platillo: 'Sonido de prueba',
                    cantidad: 1,
                    notas: '¡El sonido funciona correctamente!'
                });
            }
        }

        // Inicializar sistema de sonido
        const sistemaSonido = new SistemaSonido();

        // Variables globales
        let categoriaActual = 'todas';
        let ultimaActualizacion = 0;
        let notificacionesActivas = new Set();

        // Función para cambiar cantidad de productos
        function cambiarCantidad(id, cambio) {
            const input = document.getElementById('cant_' + id);
            let valor = parseInt(input.value) || 0;
            valor = Math.max(0, valor + cambio);
            input.value = valor;
        }

        // Función para cambiar de categoría
        function cambiarCategoria(categoria) {
            categoriaActual = categoria;
            
            // Actualizar botones
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Mostrar/ocultar categorías
            document.querySelectorAll('.categoria-content').forEach(content => {
                if(categoria === 'todas' || content.dataset.categoria === categoria) {
                    content.style.display = 'block';
                } else {
                    content.style.display = 'none';
                }
            });
            
            // Aplicar filtro de búsqueda si existe
            const searchValue = document.getElementById('search-productos').value;
            if(searchValue) {
                filtrarProductos(searchValue);
            }
        }

        // Función para filtrar productos por búsqueda
        function filtrarProductos(termino) {
            termino = termino.toLowerCase();
            document.querySelectorAll('.producto-item').forEach(item => {
                const nombre = item.dataset.nombre;
                const categoriaParent = item.closest('.categoria-content');
                const categoriaVisible = categoriaActual === 'todas' || 
                                       categoriaParent.dataset.categoria === categoriaActual;
                
                if(categoriaVisible && (termino === '' || nombre.includes(termino))) {
                    item.classList.remove('oculto');
                } else {
                    item.classList.add('oculto');
                }
            });
        }

        // Event listener para búsqueda
        document.getElementById('search-productos')?.addEventListener('input', function() {
            filtrarProductos(this.value);
        });

        // Función para toggle de secciones colapsables
        function toggleCollapse(seccion) {
            const content = document.getElementById('content-' + seccion);
            const icon = document.getElementById('icon-' + seccion);
            
            content.classList.toggle('collapsed');
            icon.classList.toggle('collapsed');
        }

        // Sistema de notificaciones
        function mostrarNotificacionFlotante(platillo) {
            // Evitar duplicados
            if(notificacionesActivas.has(platillo.detalle_id)) {
                return;
            }
            notificacionesActivas.add(platillo.detalle_id);

            const div = document.createElement('div');
            div.className = 'notificacion-flotante';
            div.dataset.detalleId = platillo.detalle_id;
            
            div.innerHTML = `
                <div class="notificacion-contenido">
                    <div class="notificacion-icono">🔔</div>
                    <div class="notificacion-texto">
                        <strong>¡Platillo Listo!</strong>
                        <div><strong>${platillo.mesa}</strong></div>
                        <div>${platillo.cantidad}× ${platillo.platillo}</div>
                        ${platillo.notas ? `<div style="font-size: 12px;">📝 ${platillo.notas}</div>` : ''}
                    </div>
                    <button class="notificacion-cerrar" onclick="cerrarNotificacion(this)">×</button>
                </div>
            `;
            
            document.body.appendChild(div);
            
            // Auto-cerrar después de 10 segundos
            setTimeout(() => {
                if(div.parentElement) {
                    cerrarNotificacion(div.querySelector('.notificacion-cerrar'));
                }
            }, 10000);
        }

        function cerrarNotificacion(btn) {
            const notif = btn.closest('.notificacion-flotante');
            const detalleId = notif.dataset.detalleId;
            notificacionesActivas.delete(detalleId);
            
            notif.style.animation = 'slideOutRight 0.5s ease';
            setTimeout(() => notif.remove(), 500);
        }

        // Verificar platillos listos periódicamente
        function verificarPlatillosListos() {
            fetch('mesero.php?ajax=platillos_listos')
                .then(res => res.json())
                .then(data => {
                    if(data.success && data.platillos.length > 0) {
                        // Actualizar badge
                        const badge = document.getElementById('badge-entregas');
                        badge.textContent = data.total;
                        badge.style.display = data.total > 0 ? 'flex' : 'none';
                        
                        // Mostrar notificaciones solo para platillos nuevos
                        if(data.timestamp > ultimaActualizacion) {
                            data.platillos.forEach(platillo => {
                                mostrarNotificacionFlotante(platillo);
                                sistemaSonido.reproducirSonido();
                            });
                            ultimaActualizacion = data.timestamp;
                        }
                    } else {
                        document.getElementById('badge-entregas').style.display = 'none';
                    }
                })
                .catch(err => console.error('Error al verificar platillos:', err));
        }

        // Iniciar verificación periódica
        setInterval(verificarPlatillosListos, 5000);
        verificarPlatillosListos(); // Verificar inmediatamente al cargar

        // Animación inicial y configuración
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar audio con primera interacción
            document.body.addEventListener('click', function() {
                sistemaSonido.inicializarAudio();
            }, { once: true });
            
            // Auto-ocultar alertas después de 5 segundos
            setTimeout(() => {
                document.querySelectorAll('.alert').forEach(alert => {
                    alert.style.animation = 'slideDown 0.5s ease reverse';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
        });
    </script>

    <style>
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    </style>
</body>
</html>   