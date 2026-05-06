<?php
//HOLAAAAAAAAAAAAAAAAAAAAAAAAAAAA  
session_start();
include('includes/conexion.php');

// Validar rol
if(!isset($_SESSION['rol']) || $_SESSION['rol'] != 'mesero'){
    header('Location: index.php');
    exit();
}

// Obtener ID del mesero actual
$mesero_id = $_SESSION['id'];
$mesero_usuario = $_SESSION['usuario'];

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

// Marcar platillo como entregado
if(isset($_POST['entregar'])){
    $detalle_id = $_POST['detalle_id'];
    
    $stmt = $conn->prepare("UPDATE detalle_pedido SET estado_preparacion='entregado' WHERE id=?");
    $stmt->bind_param("i", $detalle_id);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['mensaje_entrega'] = 'Platillo marcado como entregado';
    header("Location: mesero_entregas.php");
    exit();
}

// Obtener y limpiar mensaje
$mensaje = null;
if(isset($_SESSION['mensaje_entrega'])){
    $mensaje = $_SESSION['mensaje_entrega'];
    unset($_SESSION['mensaje_entrega']);
}

// Obtener platillos listos para entregar - SOLO DEL MESERO ACTUAL
$query_listos = "
    SELECT 
        dp.id as detalle_id,
        dp.cantidad,
        dp.notas,
        p.id as pedido_id,
        p.mesa,
        p.fecha_hora,
        p.mesero_nombre,
        prod.nombre as platillo
    FROM detalle_pedido dp
    INNER JOIN pedidos p ON dp.pedido_id = p.id
    INNER JOIN productos prod ON dp.producto_id = prod.id
    WHERE p.estado = 'pendiente' 
    AND p.mesero_id = ?
    AND prod.categoria = 'comida'
    AND dp.estado_preparacion = 'listo'
    ORDER BY p.mesa ASC, p.fecha_hora ASC
";

$stmt_listos = $conn->prepare($query_listos);
$stmt_listos->bind_param("i", $mesero_id);
$stmt_listos->execute();
$listos = $stmt_listos->get_result();

// Agrupar por mesa
$platillos_por_mesa = [];
while($row = $listos->fetch_assoc()){
    $mesa = $row['mesa'];
    if(!isset($platillos_por_mesa[$mesa])){
        $platillos_por_mesa[$mesa] = [];
    }
    $platillos_por_mesa[$mesa][] = $row;
}

// Obtener platillos entregados recientemente - SOLO DEL MESERO ACTUAL
$query_entregados = "
    SELECT 
        dp.cantidad,
        p.mesa,
        prod.nombre as platillo
    FROM detalle_pedido dp
    INNER JOIN pedidos p ON dp.pedido_id = p.id
    INNER JOIN productos prod ON dp.producto_id = prod.id
    WHERE p.estado = 'pendiente' 
    AND p.mesero_id = ?
    AND prod.categoria = 'comida'
    AND dp.estado_preparacion = 'entregado'
    ORDER BY p.mesa ASC
    LIMIT 20
";

$stmt_entregados = $conn->prepare($query_entregados);
$stmt_entregados->bind_param("i", $mesero_id);
$stmt_entregados->execute();
$entregados = $stmt_entregados->get_result();

// Contar mesas del mesero
$query_mesas = "SELECT COUNT(DISTINCT mesa) as total FROM pedidos WHERE mesero_id = ? AND estado = 'pendiente'";
$stmt_mesas = $conn->prepare($query_mesas);
$stmt_mesas->bind_param("i", $mesero_id);
$stmt_mesas->execute();
$res_mesas = $stmt_mesas->get_result();
$total_mesas = $res_mesas->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Entregas - <?php echo $mesero_usuario; ?></title>
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

        .header-left {
            flex: 1;
        }

        .header h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 8px;
        }

        .user-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .mesas-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-left: 10px;
        }

        .nav-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-nav {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            color: white;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .btn-pedidos {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .btn-logout {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        }

        .btn-nav:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }

        .controles-audio {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
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

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid #28a745;
            margin-bottom: 20px;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            margin-bottom: 25px;
        }

        .section h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 22px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-left: 4px solid #667eea;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #333;
        }

        .mesa-group {
            margin-bottom: 30px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
        }

        .mesa-group:hover {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .mesa-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            font-size: 20px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .platillos-count {
            background: rgba(255,255,255,0.3);
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 14px;
        }

        .platillos-list {
            padding: 15px;
        }

        .platillo-item {
            background: #f8f9fa;
            border-left: 4px solid #4facfe;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            transition: all 0.3s;
        }

        .platillo-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .platillo-info {
            flex: 1;
        }

        .platillo-nombre {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .cantidad-badge {
            background: #ffc107;
            color: #333;
            padding: 4px 10px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 13px;
            margin-right: 10px;
        }

        .notas {
            background: white;
            padding: 8px 12px;
            border-radius: 6px;
            margin-top: 8px;
            font-size: 14px;
            color: #666;
            border: 1px solid #e0e0e0;
        }

        .btn-entregar {
            padding: 10px 20px;
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .btn-entregar:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(86, 171, 47, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }

        .empty-state p {
            font-size: 16px;
            color: #999;
        }

        .entregados-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .entregado-card {
            background: #f8f9fa;
            border-left: 4px solid #56ab2f;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s;
        }

        .entregado-card:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .entregado-card strong {
            color: #667eea;
            font-size: 16px;
        }

        /* Estilos para notificaciones */
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

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .header-left {
                width: 100%;
            }

            .nav-buttons {
                width: 100%;
                justify-content: center;
            }

            .platillo-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .btn-entregar {
                width: 100%;
            }

            .mesa-header {
                flex-direction: column;
                gap: 10px;
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
        <div class="header">
            <div class="header-left">
                <h1>📦 Mis Entregas</h1>
                <div>
                    <span class="user-badge">👤 <?php echo $mesero_usuario; ?></span>
                    <span class="mesas-badge">🪑 <?php echo $total_mesas; ?> mesas activas</span>
                </div>
            </div>
            <div class="nav-buttons">
                <a href="mesero.php" class="btn-nav btn-pedidos">🍽️ Tomar Pedidos</a>
                <a href="logout.php" class="btn-nav btn-logout">🚪 Cerrar Sesión</a>
            </div>
        </div>

        <!-- Controles de audio -->
        <div class="controles-audio">
            <button onclick="notificaciones.toggleSonido()" class="btn-control-audio" id="btn-audio" style="background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);">
                🔊 Sonido: ON
            </button>
            <button onclick="notificaciones.probarSonido()" class="btn-control-audio" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                🎵 Probar Sonido
            </button>
        </div>

        <?php if($mensaje): ?>
            <div class="alert-success">
                ✅ <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="section">
            <h2>🔔 Platillos Listos para Entregar</h2>
            
            <div class="info-box">
                ℹ️ Aquí solo aparecen los platillos listos de <strong>tus mesas</strong> (las que tú eres el encargado). Los platillos de otros meseros no se muestran. El sistema te notificará automáticamente cuando haya nuevos platillos listos.
            </div>
            
            <?php if(empty($platillos_por_mesa)): ?>
                <div class="empty-state">
                    <div class="empty-icon">✅</div>
                    <h3 style="margin-bottom: 10px; color: #667eea;">Todo al día</h3>
                    <p>No hay platillos listos para entregar en tus mesas</p>
                </div>
            <?php else: ?>
                <?php foreach($platillos_por_mesa as $mesa => $platillos): ?>
                    <div class="mesa-group">
                        <div class="mesa-header">
                            <span><?php echo $mesa; ?></span>
                            <span class="platillos-count"><?php echo count($platillos); ?> platillo(s)</span>
                        </div>
                        <div class="platillos-list">
                            <?php foreach($platillos as $plat): ?>
                                <div class="platillo-item">
                                    <div class="platillo-info">
                                        <div class="platillo-nombre">
                                            <span class="cantidad-badge">x<?php echo $plat['cantidad']; ?></span>
                                            <?php echo $plat['platillo']; ?>
                                        </div>
                                        <?php if($plat['notas']): ?>
                                            <div class="notas">
                                                📝 <?php echo htmlspecialchars($plat['notas']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="detalle_id" value="<?php echo $plat['detalle_id']; ?>">
                                        <button type="submit" name="entregar" class="btn-entregar" 
                                                onclick="return confirm('¿Confirmar entrega de este platillo?')">
                                            ✓ Marcar Entregado
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>✅ Últimos Platillos Entregados</h2>
            <?php if($entregados->num_rows == 0): ?>
                <div class="empty-state">
                    <p>Aún no has entregado platillos</p>
                </div>
            <?php else: ?>
                <div class="entregados-list">
                    <?php while($ent = $entregados->fetch_assoc()): ?>
                        <div class="entregado-card">
                            <strong><?php echo $ent['mesa']; ?></strong><br>
                            <span class="cantidad-badge">x<?php echo $ent['cantidad']; ?></span>
                            <?php echo $ent['platillo']; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Sistema de Notificaciones Integrado
        class NotificacionesMesero {
            constructor() {
                this.platillosAnteriores = new Set();
                this.sonidoHabilitado = localStorage.getItem('sonidoHabilitado') !== 'false';
                this.notificacionesHabilitadas = false;
                this.init();
            }

            init() {
                this.cargarPlatillosActuales();
                this.solicitarPermisoNotificaciones();
                this.actualizarBotonSonido();
                
                // Verificar cada 10 segundos
                setInterval(() => this.verificarNuevosPlatillos(), 10000);
            }

            cargarPlatillosActuales() {
                const items = document.querySelectorAll('.platillo-item');
                items.forEach(item => {
                    const detalleId = item.querySelector('input[name="detalle_id"]')?.value;
                    if (detalleId) {
                        this.platillosAnteriores.add(detalleId);
                    }
                });
            }

            async verificarNuevosPlatillos() {
                try {
                    const response = await fetch('?ajax=platillos_listos');
                    const data = await response.json();
                    
                    const platillosNuevos = [];
                    data.platillos.forEach(platillo => {
                        if (!this.platillosAnteriores.has(platillo.detalle_id)) {
                            platillosNuevos.push(platillo);
                            this.platillosAnteriores.add(platillo.detalle_id);
                        }
                    });

                    if (platillosNuevos.length > 0) {
                        this.mostrarNotificacion(platillosNuevos);
                        this.reproducirSonido();
                        this.mostrarAlertaVisual(platillosNuevos);
                        
                        // Recargar después de 2 segundos
                        setTimeout(() => location.reload(), 2000);
                    }
                } catch (error) {
                    console.error('Error al verificar platillos:', error);
                }
            }

            reproducirSonido() {
                if (!this.sonidoHabilitado) return;
                
                try {
                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    const now = audioContext.currentTime;
                    
                    // Tres tonos agradables
                    this.crearTono(audioContext, 800, now, 0.15);
                    this.crearTono(audioContext, 1000, now + 0.15, 0.15);
                    this.crearTono(audioContext, 1200, now + 0.3, 0.2);
                } catch (error) {
                    console.error('Error al reproducir sonido:', error);
                }
            }

            crearTono(audioContext, frecuencia, tiempo, duracion) {
                const oscilador = audioContext.createOscillator();
                const ganancia = audioContext.createGain();
                
                oscilador.connect(ganancia);
                ganancia.connect(audioContext.destination);
                
                oscilador.frequency.value = frecuencia;
                oscilador.type = 'sine';
                
                ganancia.gain.setValueAtTime(0.3, tiempo);
                ganancia.gain.exponentialRampToValueAtTime(0.01, tiempo + duracion);
                
                oscilador.start(tiempo);
                oscilador.stop(tiempo + duracion);
            }

            async solicitarPermisoNotificaciones() {
                if ('Notification' in window && Notification.permission === 'default') {
                    const permiso = await Notification.requestPermission();
                    this.notificacionesHabilitadas = (permiso === 'granted');
                } else if ('Notification' in window && Notification.permission === 'granted') {
                    this.notificacionesHabilitadas = true;
                }
            }

            mostrarNotificacion(platillos) {
                if (!this.notificacionesHabilitadas) return;
                
                const cantidad = platillos.length;
                const titulo = `🔔 ${cantidad} platillo${cantidad > 1 ? 's' : ''} ${cantidad > 1 ? 'listos' : 'listo'}`;
                const mensaje = platillos.map(p => `${p.mesa}: ${p.platillo}`).join('\n');
                
                try {
                    new Notification(titulo, {
                        body: mensaje,
                        icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y="75" font-size="75">🍽️</text></svg>',
                        requireInteraction: true
                    });
                } catch (error) {
                    console.error('Error al mostrar notificación:', error);
                }
            }

            mostrarAlertaVisual(platillos) {
                const alerta = document.createElement('div');
                alerta.className = 'notificacion-flotante';
                alerta.innerHTML = `
                    <div class="notificacion-contenido">
                        <div class="notificacion-icono">🔔</div>
                        <div class="notificacion-texto">
                            <strong>¡Platillos Listos!</strong>
                            ${platillos.map(p => `<div>📍 ${p.mesa}: ${p.platillo} (x${p.cantidad})</div>`).join('')}
                        </div>
                        <button onclick="this.parentElement.parentElement.remove()" class="notificacion-cerrar">✕</button>
                    </div>
                `;
                
                document.body.appendChild(alerta);
                setTimeout(() => alerta.remove(), 10000);
            }

            toggleSonido() {
                this.sonidoHabilitado = !this.sonidoHabilitado;
                localStorage.setItem('sonidoHabilitado', this.sonidoHabilitado);
                this.actualizarBotonSonido();
            }

            actualizarBotonSonido() {
                const btn = document.getElementById('btn-audio');
                if (btn) {
                    btn.textContent = this.sonidoHabilitado ? '🔊 Sonido: ON' : '🔇 Sonido: OFF';
                    btn.style.background = this.sonidoHabilitado 
                        ? 'linear-gradient(135deg, #56ab2f 0%, #a8e063 100%)'
                        : 'linear-gradient(135deg, #eb3349 0%, #f45c43 100%)';
                }
            }

            probarSonido() {
                this.reproducirSonido();
                this.mostrarAlertaVisual([{
                    mesa: 'Mesa de Prueba',
                    platillo: 'Sonido de prueba',
                    cantidad: 1
                }]);
            }
        }

        // Inicializar al cargar la página
        let notificaciones;
        document.addEventListener('DOMContentLoaded', () => {
            notificaciones = new NotificacionesMesero();
        });
    </script>
</body>
</html>    