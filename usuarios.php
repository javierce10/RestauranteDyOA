<?php
session_start();
include('includes/conexion.php');

// Validar que sea administrador
if(!isset($_SESSION['rol']) || $_SESSION['rol'] != 'admin'){
    header('Location: index.php');
    exit();
}

// Agregar usuario
if (isset($_POST['agregar'])) {
    $nombre = $_POST['nombre'];
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];
    $rol = $_POST['rol'];

    $stmt = $conn->prepare("INSERT INTO usuarios (nombre, usuario, password, rol, activo, creado_en, must_change_password) VALUES (?, ?, ?, ?, 1, NOW(), 1)");
    $stmt->bind_param("ssss", $nombre, $usuario, $password, $rol);
    $stmt->execute();
    $stmt->close();
    $mensaje = "Usuario agregado correctamente";
}

// Cambiar estado (activar / desactivar)
if (isset($_GET['toggle'])) {
    $id = $_GET['toggle'];
    $stmt = $conn->prepare("UPDATE usuarios SET activo = IF(activo=1, 0, 1) WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: usuarios.php");
    exit();
}

// Eliminar usuario
if (isset($_GET['borrar'])) {
    $id = $_GET['borrar'];
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: usuarios.php");
    exit();
}

// Editar usuario
if (isset($_POST['editar'])) {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $usuario = $_POST['usuario'];
    $rol = $_POST['rol'];
    
    $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, usuario=?, rol=? WHERE id=?");
    $stmt->bind_param("sssi", $nombre, $usuario, $rol, $id);
    $stmt->execute();
    $stmt->close();
    $mensaje = "Usuario actualizado correctamente";
}

// Obtener lista de usuarios
$resultado = $conn->query("SELECT * FROM usuarios ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 5px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-submit {
            padding: 12px 30px;
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            font-size: 16px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
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

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-activo {
            background-color: #28a745;
            color: white;
        }

        .badge-inactivo {
            background-color: #dc3545;
            color: white;
        }

        .badge-rol {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            margin: 2px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-toggle {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .btn-editar {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            color: white;
        }

        .btn-borrar {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 14px;
            }

            th, td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gestión de Usuarios</h1>
            <a href="admin.php" class="btn-back">← Volver al Panel</a>
        </div>

        <?php if(isset($mensaje)): ?>
            <div class="alert-success">
                ✅ <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="section">
            <h2><?php echo isset($_GET['edit']) ? 'Editar Usuario' : 'Agregar Nuevo Usuario'; ?></h2>
            
            <?php 
            $usuarioEditar = null;
            if (isset($_GET['edit'])):
                $id = $_GET['edit'];
                $usuarioEditar = $conn->query("SELECT * FROM usuarios WHERE id=$id")->fetch_assoc();
            endif;
            ?>

            <form method="POST">
                <?php if($usuarioEditar): ?>
                    <input type="hidden" name="id" value="<?php echo $usuarioEditar['id']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre Completo</label>
                        <input type="text" name="nombre" 
                               value="<?php echo $usuarioEditar ? $usuarioEditar['nombre'] : ''; ?>" 
                               placeholder="Ej: Juan Pérez" required>
                    </div>

                    <div class="form-group">
                        <label>Usuario</label>
                        <input type="text" name="usuario" 
                               value="<?php echo $usuarioEditar ? $usuarioEditar['usuario'] : ''; ?>" 
                               placeholder="Nombre de usuario" required>
                    </div>

                    <?php if(!$usuarioEditar): ?>
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" name="password" placeholder="Contraseña" required>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Rol</label>
                        <select name="rol" required>
                            <option value="mesero" <?php echo ($usuarioEditar && $usuarioEditar['rol']=='mesero')?'selected':''; ?>>Mesero</option>
                            <option value="cocina" <?php echo ($usuarioEditar && $usuarioEditar['rol']=='cocina')?'selected':''; ?>>Cocina</option>
                            <option value="Caja" <?php echo ($usuarioEditar && $usuarioEditar['rol']=='caja')?'selected':''; ?>>Caja</option>
                            <option value="admin" <?php echo ($usuarioEditar && $usuarioEditar['rol']=='admin')?'selected':''; ?>>Administrador</option>
                        </select>
                    </div>
                </div>

                <button type="submit" name="<?php echo $usuarioEditar ? 'editar' : 'agregar'; ?>" class="btn-submit">
                    <?php echo $usuarioEditar ? '💾 Guardar Cambios' : '➕ Agregar Usuario'; ?>
                </button>

                <?php if($usuarioEditar): ?>
                    <a href="usuarios.php" class="btn-submit" style="background: linear-gradient(135deg, #666 0%, #999 100%); margin-left: 10px; text-decoration: none;">
                        ✖ Cancelar
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="section">
            <h2>Lista de Usuarios</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?php echo $row['id']; ?></strong></td>
                            <td><?php echo $row['nombre']; ?></td>
                            <td><?php echo $row['usuario']; ?></td>
                            <td>
                                <span class="badge badge-rol">
                                    <?php echo ucfirst($row['rol']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $row['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                    <?php echo $row['activo'] ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </td>
                            <td>
                                <a class="btn-action btn-toggle" href="usuarios.php?toggle=<?php echo $row['id']; ?>">
                                    <?php echo $row['activo'] ? '🔒 Desactivar' : '🔓 Activar'; ?>
                                </a>
                                <a class="btn-action btn-editar" href="usuarios.php?edit=<?php echo $row['id']; ?>">
                                    ✏️ Editar
                                </a>
                                <a class="btn-action btn-borrar" 
                                   href="usuarios.php?borrar=<?php echo $row['id']; ?>" 
                                   onclick="return confirm('¿Seguro que deseas eliminar este usuario?')">
                                    🗑️ Borrar
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>  