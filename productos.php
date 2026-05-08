<?php
session_start();
include('includes/conexion.php');

// Validar el rol que cumple
if(!isset($_SESSION['rol']) || $_SESSION['rol'] != 'admin'){
    header('Location: index.php');
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

// Manejo de agregar producto
if(isset($_POST['agregar_producto'])){
    $nombre = $_POST['nombre'];
    $precio = $_POST['precio'];
    $categoria = $_POST['categoria'];
    $descripcion = $_POST['descripcion'] ?? null;
    $disponible = isset($_POST['disponible']) ? 1 : 0;
    
    $stmt = $conn->prepare("INSERT INTO productos (nombre, precio, categoria, descripcion, disponible) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sdssi", $nombre, $precio, $categoria, $descripcion, $disponible);
    
    if($stmt->execute()){
        $mensaje = "Producto agregado correctamente";
        $tipo_mensaje = "success";
    } else {
        $mensaje = "Error al agregar el producto";
        $tipo_mensaje = "error";
    }
    $stmt->close();
}

// Manejo de editar producto
if(isset($_POST['editar_producto'])){
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $precio = $_POST['precio'];
    $categoria = $_POST['categoria'];
    $descripcion = $_POST['descripcion'] ?? null;
    $disponible = isset($_POST['disponible']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE productos SET nombre=?, precio=?, categoria=?, descripcion=?, disponible=? WHERE id=?");
    $stmt->bind_param("sdssii", $nombre, $precio, $categoria, $descripcion, $disponible, $id);
    
    if($stmt->execute()){
        $mensaje = "Producto actualizado correctamente";
        $tipo_mensaje = "success";
    } else {
        $mensaje = "Error al actualizar el producto";
        $tipo_mensaje = "error";
    }
    $stmt->close();
}

// Manejo de eliminar producto en lista
if(isset($_POST['eliminar_producto'])){
    $id = $_POST['id'];
    
    $stmt = $conn->prepare("DELETE FROM productos WHERE id=?");
    $stmt->bind_param("i", $id);
    
    if($stmt->execute()){
        $mensaje = "Producto eliminado correctamente";
        $tipo_mensaje = "success";
    } else {
        $mensaje = "Error al eliminar el producto";
        $tipo_mensaje = "error";
    }
    $stmt->close();
}

// Manejo de cambiar disponibilidad rápida de productos
if(isset($_POST['toggle_disponible'])){
    $id = $_POST['id'];
    $disponible = $_POST['disponible'];
    
    $stmt = $conn->prepare("UPDATE productos SET disponible=? WHERE id=?");
    $stmt->bind_param("ii", $disponible, $id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true]);
    exit();
}

// Obtener todos los productos
$res_productos = $conn->query("SELECT * FROM productos ORDER BY categoria, nombre");
$productos = [];
while($row = $res_productos->fetch_assoc()){
    $productos[] = $row;
}

// Contar productos por categoría
$count_comidas = count(array_filter($productos, function($p){ return $p['categoria'] == 'comida'; }));
$count_bebidas = count(array_filter($productos, function($p){ return $p['categoria'] == 'bebida'; }));
$count_disponibles = count(array_filter($productos, function($p){ return $p['disponible'] == 1; }));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos</title>
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

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header h3 {
            color: #667eea;
            font-size: 22px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }

        .filtros {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-filtro {
            padding: 10px 20px;
            border: 3px solid #e0e0e0;
            background: white;
            color: #666;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }

        .btn-filtro:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .btn-filtro.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        .productos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .producto-card {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
            position: relative;
        }

        .producto-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            border-color: #667eea;
        }

        .producto-card.no-disponible {
            opacity: 0.6;
            background: #f0f0f0;
        }

        .producto-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .producto-nombre {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            flex: 1;
        }

        .producto-precio {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 16px;
        }

        .producto-categoria {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .categoria-comida {
            background: #fff3cd;
            color: #856404;
        }

        .categoria-bebida {
            background: #d1ecf1;
            color: #0c5460;
        }

        .producto-descripcion {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            min-height: 40px;
        }

        .producto-estado {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
            background: #ccc;
            border-radius: 13px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .toggle-switch.active {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
        }

        .toggle-slider {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s;
        }

        .toggle-switch.active .toggle-slider {
            left: 27px;
        }

        .estado-label {
            font-size: 13px;
            font-weight: 600;
        }

        .producto-acciones {
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

        .btn-accion:hover {
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
            overflow-y: auto;
        }

        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
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

        .no-productos {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-productos-icon {
            font-size: 60px;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .productos-grid {
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
            <h1>🍽️ Gestión de Productos</h1>
            <div class="header-actions">
                <button class="btn-agregar" onclick="abrirModalAgregar()">
                    ➕ Nuevo Producto
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

        <!-- Estadísticas -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-number"><?php echo count($productos); ?></div>
                <div class="stat-label">Total Productos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🍽️</div>
                <div class="stat-number"><?php echo $count_comidas; ?></div>
                <div class="stat-label">Comidas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🥤</div>
                <div class="stat-number"><?php echo $count_bebidas; ?></div>
                <div class="stat-label">Bebidas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo $count_disponibles; ?></div>
                <div class="stat-label">Disponibles</div>
            </div>
        </div>

        <!-- Lista de productos -->
        <div class="section">
            <div class="section-header">
                <h3>📋 Lista de Productos</h3>  
                <div class="filtros">
                    <button class="btn-filtro active" onclick="filtrarProductos('todos')" id="btn-todos">
                        📊 Todos
                    </button>
                    <button class="btn-filtro" onclick="filtrarProductos('comida')" id="btn-comida">
                        🍽️ Comidas
                    </button>
                    <button class="btn-filtro" onclick="filtrarProductos('bebida')" id="btn-bebida">
                        🥤 Bebidas
                    </button>
                    <button class="btn-filtro" onclick="filtrarProductos('disponible')" id="btn-disponible">
                        ✅ Disponibles
                    </button>
                    <button class="btn-filtro" onclick="filtrarProductos('no-disponible')" id="btn-no-disponible">
                        ❌ No Disponibles
                    </button>
                </div>
            </div>
            <div style="margin-top:20px;">
    <input 
        type="text" 
        id="buscador-productos"
        placeholder="🔍 Buscar producto..."
        style="
            width:100%;
            padding:15px;
            border-radius:10px;
            border:2px solid #e0e0e0;
            font-size:16px;
        "
    >
</div>

            <?php if(count($productos) == 0): ?>
                <div class="no-productos">
                    <div class="no-productos-icon">🍽️</div>
                    <h3>No hay productos registrados</h3>
                    <p>Agrega tu primer producto haciendo clic en el botón "Nuevo Producto"</p>
                </div>
            <?php else: ?>
                <div class="productos-grid" id="productos-grid">
                    <?php foreach($productos as $prod): ?>
                        <div class="producto-card <?php echo !$prod['disponible'] ? 'no-disponible' : ''; ?>" 
                             data-categoria="<?php echo $prod['categoria']; ?>"
                             data-disponible="<?php echo $prod['disponible']; ?>">  
                            
                            <div class="producto-header">
                                <div class="producto-nombre">
                                    <?php echo $prod['categoria'] == 'comida' ? '🍽️' : '🥤'; ?>
                                    <?php echo htmlspecialchars($prod['nombre']); ?>
                                </div>
                                <div class="producto-precio">
                                    $<?php echo number_format($prod['precio'], 2); ?>
                                </div>
                            </div>

                            <span class="producto-categoria categoria-<?php echo $prod['categoria']; ?>">
                                <?php echo ucfirst($prod['categoria']); ?>
                            </span>

                            <div class="producto-descripcion">
                                <?php echo htmlspecialchars($prod['descripcion'] ?? 'Sin descripción'); ?>
                            </div>

                            <div class="producto-estado">
                                <div class="toggle-switch <?php echo $prod['disponible'] ? 'active' : ''; ?>" 
                                     onclick="toggleDisponible(<?php echo $prod['id']; ?>, <?php echo $prod['disponible']; ?>)">
                                    <div class="toggle-slider"></div>
                                </div>
                                <span class="estado-label">
                                    <?php echo $prod['disponible'] ? '✅ Disponible' : '❌ No disponible'; ?>
                                </span>
                            </div>

                            <div class="producto-acciones">
                                <button class="btn-accion btn-editar" onclick='abrirModalEditar(<?php echo json_encode($prod); ?>)'>
                                    ✏️ Editar
                                </button>
                                <button class="btn-accion btn-eliminar" onclick="confirmarEliminar(<?php echo $prod['id']; ?>, '<?php echo htmlspecialchars($prod['nombre']); ?>')">
                                    🗑️ Eliminar
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Agregar Producto -->
    <div id="modalAgregar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                ➕ Agregar Nuevo Producto
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Nombre del Producto *</label>
                        <input type="text" name="nombre" required placeholder="Ej: Hamburguesa Especial">
                    </div>

                    <div class="form-group">
                        <label>Precio *</label>
                        <input type="number" name="precio" step="0.01" min="0" required placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label>Categoría *</label>
                        <select name="categoria" required>
                            <option value="">Seleccionar...</option>
                            <option value="comida">🍽️ Comida</option>
                            <option value="bebida">🥤 Bebida</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="descripcion" placeholder="Descripción opcional del producto"></textarea>
                    </div>

                    <div class="form-group checkbox-group">
                        <input type="checkbox" name="disponible" id="disponible-agregar" checked>
                        <label for="disponible-agregar">Producto disponible</label>
                    </div>

                    <div class="modal-buttons">
                        <button type="button" class="btn-cancelar" onclick="cerrarModal('modalAgregar')">
                            Cancelar
                        </button>
                        <button type="submit" name="agregar_producto" class="btn-submit">
                            ➕ Agregar Producto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Producto -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                ✏️ Editar Producto
            </div>
            <div class="modal-body">
                <form method="POST" id="formEditar">
                    <input type="hidden" name="id" id="edit-id">

                    <div class="form-group">
                        <label>Nombre del Producto *</label>
                        <input type="text" name="nombre" id="edit-nombre" required>
                    </div>

                    <div class="form-group">
                        <label>Precio *</label>
                        <input type="number" name="precio" id="edit-precio" step="0.01" min="0" required>
                    </div>

                    <div class="form-group">
                        <label>Categoría *</label>
                        <select name="categoria" id="edit-categoria" required>
                            <option value="comida">🍽️ Comida</option>
                            <option value="bebida">🥤 Bebida</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="descripcion" id="edit-descripcion"></textarea>
                    </div>

                    <div class="form-group checkbox-group">
                        <input type="checkbox" name="disponible" id="edit-disponible">
                        <label for="edit-disponible">Producto disponible</label>
                    </div>

                    <div class="modal-buttons">
                        <button type="button" class="btn-cancelar" onclick="cerrarModal('modalEditar')">
                            Cancelar
                        </button>
                        <button type="submit" name="editar_producto" class="btn-submit">
                            💾 Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar (oculto) -->
    <form method="POST" id="formEliminar" style="display: none;">
        <input type="hidden" name="id" id="delete-id">
        <input type="hidden" name="eliminar_producto" value="1">
    </form>

    <script>
        // Abrir modal agregar
        function abrirModalAgregar() {
            document.getElementById('modalAgregar').style.display = 'block';
        }

        // Abrir modal editar
        function abrirModalEditar(producto) {
            document.getElementById('edit-id').value = producto.id;
            document.getElementById('edit-nombre').value = producto.nombre;
            document.getElementById('edit-precio').value = producto.precio;
            document.getElementById('edit-categoria').value = producto.categoria;
            document.getElementById('edit-descripcion').value = producto.descripcion || '';
            document.getElementById('edit-disponible').checked = producto.disponible == 1;
            
            document.getElementById('modalEditar').style.display = 'block';
        }

        // Cerrar modal
        function cerrarModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Confirmar eliminar
        function confirmarEliminar(id, nombre) {
            if(confirm('¿Estás seguro de eliminar el producto "' + nombre + '"?\n\nEsta acción no se puede deshacer.')) {
                document.getElementById('delete-id').value = id;
                document.getElementById('formEliminar').submit();
            }
        }

        // Toggle disponibilidad
        function toggleDisponible(id, estadoActual) {
            const nuevoEstado = estadoActual ? 0 : 1;
            
            const formData = new FormData();
            formData.append('toggle_disponible', '1');
            formData.append('id', id);
            formData.append('disponible', nuevoEstado);
            
            fetch('productos.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                }
            });
        }

        // Filtrar productos
        function filtrarProductos(filtro) {
            const cards = document.querySelectorAll('.producto-card');
            const botones = document.querySelectorAll('.btn-filtro');
            
            // Actualizar botones activos
            botones.forEach(btn => btn.classList.remove('active'));
            document.getElementById('btn-' + filtro).classList.add('active');
            
            // Mostrar/ocultar cards
            cards.forEach(card => {
                const categoria = card.dataset.categoria;
                const disponible = card.dataset.disponible;
                
                let mostrar = false;
                
                switch(filtro) {
                    case 'todos':
                        mostrar = true;
                        break;
                    case 'comida':
                        mostrar = categoria === 'comida';
                        break;
                    case 'bebida':
                        mostrar = categoria === 'bebida';
                        break;
                    case 'disponible':
                        mostrar = disponible === '1';
                        break;
                    case 'no-disponible':
                        mostrar = disponible === '0';
                        break;
                }
                
                card.style.display = mostrar ? '' : 'none';
            });
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        // Buscador en tiempo real
document.getElementById('buscador-productos').addEventListener('keyup', function() {

    const texto = this.value.toLowerCase();
    const productos = document.querySelectorAll('.producto-card');

    productos.forEach(producto => {

        const nombre = producto.querySelector('.producto-nombre').textContent.toLowerCase();
        const descripcion = producto.querySelector('.producto-descripcion').textContent.toLowerCase();

        if(nombre.includes(texto) || descripcion.includes(texto)){
            producto.style.display = '';
        } else {
            producto.style.display = 'none';
        }

    });

});
    </script>
</body>
</html>  