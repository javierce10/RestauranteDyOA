<?php
session_start();
include('includes/conexion.php');

// Inicializar control de intentos
if (!isset($_SESSION['intentos_fallidos'])) {
    $_SESSION['intentos_fallidos'] = 0;
}
if (!isset($_SESSION['tiempo_bloqueo'])) {
    $_SESSION['tiempo_bloqueo'] = 0;
}

// Verificar bloqueo
$tiempo_actual = time();
$bloqueado = false;

if ($_SESSION['tiempo_bloqueo'] > $tiempo_actual) {
    $bloqueado = true;
} else if ($_SESSION['tiempo_bloqueo'] > 0 && $_SESSION['tiempo_bloqueo'] <= $tiempo_actual) {
    $_SESSION['intentos_fallidos'] = 0;
    $_SESSION['tiempo_bloqueo'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($bloqueado) {
        $_SESSION['error_msg'] = "Cuenta bloqueada temporalmente.";
        header('Location: login.php');
        exit();
    }

    $usuario = $_POST['usuario'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE usuario=?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $user = $result->fetch_assoc();

        if ($password == $user['password']) {

            if ($user['activo'] == 0) {
                $_SESSION['error_msg'] = "Cuenta desactivada.";
            } else {

                // LOGIN OK
                $_SESSION['intentos_fallidos'] = 0;
                $_SESSION['tiempo_bloqueo'] = 0;

                $_SESSION['id'] = $user['id'];
                $_SESSION['usuario'] = $user['usuario'];
                $_SESSION['rol'] = $user['rol'];

                switch($user['rol']) {
                    case 'admin':
                        header('Location: admin.php');
                        break;
                    case 'caja':
                        header('Location: caja.php');
                        break;
                    case 'mesero':
                        header('Location: mesero.php');
                        break;
                    case 'cocina':
                        header('Location: cocina.php');
                        break;
                    default:
                        header('Location: login.php');
                        break;
                }
                exit();
            }

        } else {
            $_SESSION['error_msg'] = "Contraseña incorrecta";
            $_SESSION['intentos_fallidos']++;
        }

    } else {
        $_SESSION['error_msg'] = "Usuario no encontrado";
        $_SESSION['intentos_fallidos']++;
    }

    if ($_SESSION['intentos_fallidos'] >= 5) {
        $_SESSION['tiempo_bloqueo'] = time() + 30;
    }

    header('Location: login.php');
    exit();
}

// ✅ TODO ESTO DEBE ESTAR DENTRO DE PHP
$intentos_restantes = 5 - $_SESSION['intentos_fallidos'];

$error = null;
if (isset($_SESSION['error_msg'])) {
    $error = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🍽️ Login Restaurante</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }

        .login-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .login-icon {
            font-size: 60px;
            margin-bottom: 15px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        .login-body {
            padding: 40px 30px;
        }

        .error-message {
            background-color: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
            animation: shake 0.5s;
        }

        .warning-message {
            background-color: #fff3cd;
            color: #856404;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
            font-size: 14px;
        }

        .blocked-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
            text-align: center;
            font-weight: 600;
        }

        .countdown {
            font-size: 24px;
            color: #dc3545;
            margin: 10px 0;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: #667eea;
        }

        .form-group input {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            outline: none;
        }

        .form-group input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-login:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .btn-login:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn-login:disabled {
            background: #ccc;
            cursor: not-allowed;
            box-shadow: none;
        }

        .login-footer {
            text-align: center;
            padding: 20px;
            background-color: #f8f9fa;
            color: #666;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                margin: 10px;
            }
            
            .login-header h1 {
                font-size: 24px;
            }
            
            .login-body {
                padding: 30px 20px;
            }
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 20px;
            color: #999;
            user-select: none;
        }

        .toggle-password:hover {
            color: #667eea;
        }

        .attempts-counter {
            text-align: center;
            margin-top: 15px;
            font-size: 13px;
            color: #666;
        }

        .attempts-counter.warning {
            color: #dc3545;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-icon">🍽️</div>
            <h1>Bienvenido</h1>
            <p>🍽️Restaurante🍽️</p> 
        </div>

        <div class="login-body">
            <?php if($bloqueado): ?>
                <div class="blocked-message">
                    ⛔ Cuenta bloqueada temporalmente
                    <div class="countdown" id="countdown"><?php echo $tiempo_restante; ?>s</div>
                    <small>Demasiados intentos fallidos</small>
                </div>
            <?php endif; ?>

            <?php if(isset($error)): ?>
                <div class="error-message">
                    ⚠️ <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if(!$bloqueado && $_SESSION['intentos_fallidos'] > 0 && $_SESSION['intentos_fallidos'] < 5): ?>
                <div class="warning-message">
                    🥑Tienes <strong><?php echo $intentos_restantes; ?></strong> intentos antes del bloqueo
                </div>
            <?php endif; ?> 

            <form method="POST" autocomplete="off" id="loginForm">
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <div class="input-wrapper">
                        <span class="input-icon">👤</span>
                        <input 
                            type="text" 
                            id="usuario" 
                            name="usuario" 
                            placeholder="Ingresa tu usuario"
                            required 
                            autofocus
                            <?php echo $bloqueado ? 'disabled' : ''; ?>
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Ingresa tu contraseña"
                            required
                            <?php echo $bloqueado ? 'disabled' : ''; ?>
                        >
                        <span class="toggle-password" onclick="togglePassword()">👁️</span>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="btnLogin" <?php echo $bloqueado ? 'disabled' : ''; ?>>
                    <?php echo $bloqueado ? '⛔ Bloqueado' : 'Iniciar Sesión 🚀'; ?>
                </button>

                <?php if(!$bloqueado && $_SESSION['intentos_fallidos'] > 0): ?>
                    <div class="attempts-counter <?php echo $_SESSION['intentos_fallidos'] >= 3 ? 'warning' : ''; ?>">
                        <?php 
                        if($_SESSION['intentos_fallidos'] >= 3) {
                            echo "⚠️ ";
                        }
                        echo "Llevas " . $_SESSION['intentos_fallidos'] . " intento(s) fallido(s)"; 
                        ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <div class="login-footer">
            ©2025 Copyright by Group Guacamole Company   
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.toggle-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleIcon.textContent = '👁️';
            }
        }

        // Evitar envíos múltiples del formulario
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('btnLogin');
            if (!btn.disabled) {
                btn.disabled = true;
                btn.textContent = 'Ingresando...';
            }
        });

        <?php if($bloqueado): ?>
        // Contador regresivo
        let tiempoRestante = <?php echo $tiempo_restante; ?>;
        const countdownElement = document.getElementById('countdown');
        const btnLogin = document.getElementById('btnLogin');
        const usuarioInput = document.getElementById('usuario');
        const passwordInput = document.getElementById('password');

        const interval = setInterval(function() {
            tiempoRestante--;
            if (countdownElement) {
                countdownElement.textContent = tiempoRestante + 's';
            }

            if (tiempoRestante <= 0) {
                clearInterval(interval);
                // Recargar la página para desbloquear
                location.reload();
            }
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html> 