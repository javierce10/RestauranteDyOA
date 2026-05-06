<?php
session_start();
include('includes/conexion.php');

// Validar rol
if(!isset($_SESSION['rol']) || $_SESSION['rol'] != 'admin'){
    header('Location: index.php');
    exit();
}

$mensaje = '';
$tipo_mensaje = '';
$config_file = 'includes/mesas_config.php';

// Función para leer las mesas del archivo
function obtenerMesas($file) {
    if(file_exists($file)) {
        return include($file);
    }
    return [];
}

// Función para guardar las mesas en el archivo
function guardarMesas($file, $mesas) {
    $contenido = "<?php\n";
    $contenido .= "// Archivo de configuración de mesas\n";
    $contenido .= "// Este archivo almacena las mesas disponibles en el restaurante\n\n";
    $contenido .= "return [\n";
    foreach($mesas as $mesa) {
        $contenido .= "    '" . addslashes($mesa) . "',\n";
    }
    $contenido .= "];\n";
    $contenido .= "?>";
    
    return file_put_contents($file, $contenido) !== false;
}

// Obtener mesas actuales
$mesas = obtenerMesas($config_file);

// Manejo de agregar mesa
if(isset($_POST['agregar_mesa'])){
    $nombre_mesa = trim($_POST['nombre_mesa']);
    
    if(empty($nombre_mesa)) {
        $mensaje = "El nombre de la mesa no puede estar vacío";
        $tipo_mensaje = "error";
    } elseif(in_array($nombre_mesa, $mesas)) {
        $mensaje = "Ya existe una mesa con ese nombre";
        $tipo_mensaje = "error";
    } else {
        $mesas[] = $nombre_mesa;
        // Ordenar mesas numéricamente si tienen formato "Mesa X"
        usort($mesas, function($a, $b) {
            $numA = preg_match('/Mesa (\d+)/', $a, $matchesA) ? (int)$matchesA[1] : 999;
            $numB = preg_match('/Mesa (\d+)/', $b, $matchesB) ? (int)$matchesB[1] : 999;
            return $numA - $numB;
        });
        
        if(guardarMesas($config_file, $mesas)) {
            $mensaje = "Mesa agregada correctamente";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "Error al guardar la configuración";
            $tipo_mensaje = "error";
        }
    }
    // Recargar mesas
    $mesas = obtenerMesas($config_file);
}

// Manejo de eliminar mesa
if(isset($_POST['eliminar_mesa'])){
    $nombre_mesa = $_POST['nombre_mesa'];
    
    // Verificar si la mesa tiene pedidos pendientes
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pedidos WHERE mesa = ? AND estado = 'pendiente'");
    $stmt->bind_param("s", $nombre_mesa);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if($row['total'] > 0) {
        $mensaje = "No se puede eliminar la mesa porque tiene pedidos pendientes";
        $tipo_mensaje = "error";
    } else {
        $key = array_search($nombre_mesa, $mesas);
        if($key !== false) {
            unset($mesas[$key]);
            $mesas = array_values($mesas); // Reindexar array
            
            if(guardarMesas($config_file, $mesas)) {
                $mensaje = "Mesa eliminada correctamente";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al guardar la configuración";
                $tipo_mensaje = "error";
            }
        }
    }
    // Recargar mesas
    $mesas = obtenerMesas($config_file);
}

// Manejo de editar mesa
if(isset($_POST['editar_mesa'])){
    $nombre_original = $_POST['nombre_original'];
    $nombre_nuevo = trim($_POST['nombre_nuevo']);
    
    if(empty($nombre_nuevo)) {
        $mensaje = "El nombre de la mesa no puede estar vacío";
        $tipo_mensaje = "error";
    } elseif($nombre_nuevo != $nombre_original && in_array($nombre_nuevo, $mesas)) {
        $mensaje = "Ya existe una mesa con ese nombre";
        $tipo_mensaje = "error";
    } else {
        // Actualizar en el archivo de configuración
        $key = array_search($nombre_original, $mesas);
        if($key !== false) {
            $mesas[$key] = $nombre_nuevo;
            
            // Ordenar mesas
            usort($mesas, function($a, $b) {
                $numA = preg_match('/Mesa (\d+)/', $a, $matchesA) ? (int)$matchesA[1] : 999;
                $numB = preg_match('/Mesa (\d+)/', $b, $matchesB) ? (int)$matchesB[1] : 999;
                return $numA - $numB;
            });
            
            if(guardarMesas($config_file, $mesas)) {
                // Actualizar también en la base de datos (pedidos existentes)
                $stmt = $conn->prepare("UPDATE pedidos SET mesa = ? WHERE mesa = ?");
                $stmt->bind_param("ss", $nombre_nuevo, $nombre_original);
                $stmt->execute();
                $stmt->close();
                
                $mensaje = "Mesa actualizada correctamente";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al guardar la configuración";
                $tipo_mensaje = "error";
            }
        }
    }
    // Recargar mesas
    $mesas = obtenerMesas($config_file);
}

// Obtener información de ocupación de mesas
$mesas_info = [];
foreach($mesas as $mesa) {
    $stmt = $conn->prepare("SELECT COUNT(*) as pedidos_pendientes, 
                                   SUM(total) as total_pendiente 
                            FROM pedidos 
                            WHERE mesa = ? AND estado = 'pendiente'");
    $stmt->bind_param("s", $mesa);
    $stmt->execute();
    $result = $stmt->get_result();
    $info = $result->fetch_assoc();
    $stmt->close();
    
    $mesas_info[$mesa] = [
        'pedidos_pendientes' => $info['pedidos_pendientes'],
        'total_pendiente' => $info['total_pendiente'] ?? 0,
        'ocupada' => $info['pedidos_pendientes'] > 0
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Mesas</title>
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-back, .btn-agregar {
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

        .btn-back {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .btn-agregar {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
        }

        .btn-back:hover, .btn-agregar:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
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

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            text-align: center;
        }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
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
            font-size: 22px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #1565c0;
        }

        .mesas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .mesa-card {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
            position: relative;
        }

        .mesa-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            border-color: #667eea;
        }

        .mesa-card.ocupada {
            border-color: #f45c43;
            background: linear-gradient(135deg, rgba(235, 51, 73, 0.1) 0%, rgba(244, 92, 67, 0.1) 100%);
        }

        .mesa-card.disponible {
            border-color: #a8e063;
            background: linear-gradient(135deg, rgba(86, 171, 47, 0.1) 0%, rgba(168, 224, 99, 0.1) 100%);
        }

        .mesa-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .mesa-nombre {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }

        .mesa-estado {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .estado-disponible {
            background: #56ab2f;
            color: white;
        }

        .estado-ocupada {
            background: #eb3349;
            color: white;
        }

        .mesa-info {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            min-height: 40px;
        }

        .mesa-acciones {
            display: flex;
            gap: 10px;
        }

        .btn-accion {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 13px;
        }

        .btn-editar {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .btn-eliminar {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
        }

        .btn-eliminar:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-accion:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            animation: fadeIn 0.3s;
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            animation: slideUp 0.3s;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px 15px 0 0;
            font-size: 20px;
            font-weight: 600;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-hint {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .modal-buttons button {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-submit {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            color: white;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(86, 171, 47, 0.4);
        }

        .btn-cancelar {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
        }

        .btn-cancelar:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(235, 51, 73, 0.4);
        }

        .no-mesas {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-mesas-icon {
            font-size: 60px;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .mesas-grid {
                grid-template-columns: 1fr;
            }

            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🪑 Gestión de Mesas</h1>
            <div class="header-actions">
                <button class="btn-agregar" onclick="abrirModalAgregar()">
                    ➕ Nueva Mesa
                </button>
                <a href="admin.php" class="btn-back">
                    ← Volver al Panel
                </a>
            </div>
        </div>

        <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                <?php echo $tipo_mensaje == 'success' ? '✅' : '❌'; ?> <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="info-box">
            💡 <strong>Información:</strong> Las mesas se guardan en un archivo de configuración. Puedes agregar, editar o eliminar mesas según las necesidades de tu restaurante. Las mesas con pedidos pendientes no pueden ser eliminadas.
        </div>

        <!-- Estadísticas -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-number"><?php echo count($mesas); ?></div>
                <div class="stat-label">Total de Mesas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo count(array_filter($mesas_info, function($m){ return !$m['ocupada']; })); ?></div>
                <div class="stat-label">Mesas Disponibles</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔴</div>
                <div class="stat-number"><?php echo count(array_filter($mesas_info, function($m){ return $m['ocupada']; })); ?></div>
                <div class="stat-label">Mesas Ocupadas</div>
            </div>
        </div>

        <!-- Lista de mesas -->
        <div class="section">
            <h3>📋 Lista de Mesas</h3>

            <?php if(count($mesas) == 0): ?>
                <div class="no-mesas">
                    <div class="no-mesas-icon">🪑</div>
                    <h3>No hay mesas registradas</h3>
                    <p>Agrega tu primera mesa haciendo clic en el botón "Nueva Mesa"</p>
                </div>
            <?php else: ?>
                <div class="mesas-grid">
                    <?php foreach($mesas as $mesa): 
                        $info = $mesas_info[$mesa];
                        $clase_estado = $info['ocupada'] ? 'ocupada' : 'disponible';
                    ?>
                        <div class="mesa-card <?php echo $clase_estado; ?>">
                            <div class="mesa-header">
                                <div class="mesa-nombre">
                                    🪑 <?php echo htmlspecialchars($mesa); ?>
                                </div>
                                <span class="mesa-estado estado-<?php echo $clase_estado; ?>">
                                    <?php echo $info['ocupada'] ? '🔴 Ocupada' : '✅ Disponible'; ?>
                                </span>
                            </div>

                            <div class="mesa-info">
                                <?php if($info['ocupada']): ?>
                                    <div>📋 Pedidos pendientes: <strong><?php echo $info['pedidos_pendientes']; ?></strong></div>
                                    <div>💰 Total pendiente: <strong>$<?php echo number_format($info['total_pendiente'], 2); ?></strong></div>
                                <?php else: ?>
                                    <div style="color: #56ab2f;">✨ Mesa disponible para nuevos clientes</div>
                                <?php endif; ?>
                            </div>

                            <div class="mesa-acciones">
                                <button class="btn-accion btn-editar" onclick='abrirModalEditar("<?php echo addslashes($mesa); ?>")'>
                                    ✏️ Editar
                                </button>
                                <button class="btn-accion btn-eliminar" 
                                        onclick="confirmarEliminar('<?php echo addslashes($mesa); ?>')"
                                        <?php echo $info['ocupada'] ? 'disabled title="No se puede eliminar una mesa ocupada"' : ''; ?>>
                                    🗑️ Eliminar
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Agregar Mesa -->
    <div id="modalAgregar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                ➕ Agregar Nueva Mesa
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Nombre de la Mesa *</label>
                        <input type="text" name="nombre_mesa" required placeholder="Ej: Mesa 7" id="input-agregar">
                        <div class="form-hint">Usa un formato como "Mesa 1", "Mesa 2", etc.</div>
                    </div>

                    <div class="modal-buttons">
                        <button type="button" class="btn-cancelar" onclick="cerrarModal('modalAgregar')">
                            Cancelar
                        </button>
                        <button type="submit" name="agregar_mesa" class="btn-submit">
                            ➕ Agregar Mesa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Mesa -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                ✏️ Editar Mesa
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="nombre_original" id="edit-original">

                    <div class="form-group">
                        <label>Nuevo Nombre de la Mesa *</label>
                        <input type="text" name="nombre_nuevo" required id="edit-nombre">
                        <div class="form-hint">Este cambio se aplicará también a los pedidos existentes</div>
                    </div>

                    <div class="modal-buttons">
                        <button type="button" class="btn-cancelar" onclick="cerrarModal('modalEditar')">
                            Cancelar
                        </button>
                        <button type="submit" name="editar_mesa" class="btn-submit">
                            💾 Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Form Eliminar (oculto) -->
    <form method="POST" id="formEliminar" style="display: none;">
        <input type="hidden" name="nombre_mesa" id="delete-nombre">
        <input type="hidden" name="eliminar_mesa" value="1">
    </form>

    <script>
        // Abrir modal agregar
        function abrirModalAgregar() {
            document.getElementById('modalAgregar').style.display = 'block';
            document.getElementById('input-agregar').focus();
        }

        // Abrir modal editar
        function abrirModalEditar(nombreMesa) {
            document.getElementById('edit-original').value = nombreMesa;
            document.getElementById('edit-nombre').value = nombreMesa;
            document.getElementById('modalEditar').style.display = 'block';
            document.getElementById('edit-nombre').focus();
        }

        // Cerrar modal
        function cerrarModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Confirmar eliminar
        function confirmarEliminar(nombreMesa) {
            if(confirm('¿Estás seguro de eliminar "' + nombreMesa + '"?\n\nEsta acción no se puede deshacer.')) {
                document.getElementById('delete-nombre').value = nombreMesa;
                document.getElementById('formEliminar').submit();
            }
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>    