<?php
/**
 * PÁGINA DE LOGIN DEL SISTEMA AHP
 * Archivo: login.php
 */

session_start();

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: pages/dashboard.php');
    exit;
}

include 'includes/config.php';
include 'includes/conexion.php';

$error = '';
$success = '';

// Obtener mensajes de la URL
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
if (isset($_GET['logout'])) {
    $success = 'Sesión cerrada exitosamente';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema AHP</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        /* Estilos específicos para login */
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        
        .login-box {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        
        .login-header {
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #2c3e50;
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        
        .login-header p {
            color: #7f8c8d;
            margin: 0;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
        }
        
        .alert-success {
            background: #efe;
            border: 1px solid #cfc;
            color: #3c3;
        }
        
        .login-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e8ed;
        }
        
        .login-footer p {
            color: #7f8c8d;
            font-size: 12px;
            margin: 0;
        }
        
        .demo-credentials {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: left;
        }
        
        .demo-credentials h4 {
            color: #2c3e50;
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        
        .demo-credentials p {
            color: #7f8c8d;
            margin: 5px 0;
            font-size: 12px;
        }
        
        .demo-credentials strong {
            color: #2c3e50;
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle-btn {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #7f8c8d;
            cursor: pointer;
            font-size: 14px;
        }
        
        .university-logo {
            margin-bottom: 20px;
        }
        
        .university-logo h2 {
            color: #667eea;
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 10px;
            }
            
            .login-box {
                padding: 30px 20px;
            }
            
            .login-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="university-logo">
                <h2>🎓 Universidad Estatal de Guayaquil</h2>
            </div>
            
            <div class="login-header">
                <h1>Sistema AHP</h1>
                <p>Asignación de Docentes NEE</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form action="procesar/procesar_login.php" method="POST" id="loginForm">
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <input type="text" id="usuario" name="usuario" required 
                           placeholder="Ingrese su usuario"
                           value="<?php echo isset($_GET['usuario']) ? htmlspecialchars($_GET['usuario']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <div class="password-toggle">
                        <input type="password" id="password" name="password" required 
                               placeholder="Ingrese su contraseña">
                        <button type="button" class="password-toggle-btn" onclick="togglePassword()">
                            👁️
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    🔐 Iniciar Sesión
                </button>
            </form>
            
            <!-- Credenciales del único usuario -->
            <div class="demo-credentials">
                <h4>👤 Usuario del Sistema:</h4>
                <p><strong>Administrador:</strong> admin / admin123</p>
                <p style="margin-top: 10px; font-style: italic; color: #95a5a6;">
                    Haz clic en las credenciales para completar automáticamente
                </p>
                <p style="margin-top: 10px; font-size: 11px; color: #7f8c8d;">
                    📝 Nota: Para crear usuarios adicionales, usar directamente la base de datos
                </p>
            </div>
            
            <div class="login-footer">
                <p>© 2025 Facultad de Ciencias Matemáticas y Físicas</p>
                <p>Sistema de Asignación Automatizada con Algoritmo AHP</p>
            </div>
        </div>
    </div>
    
    <script>
        // Función para mostrar/ocultar contraseña
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle-btn');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }
        
        // Autocompletar credenciales al hacer clic
        document.addEventListener('DOMContentLoaded', function() {
            const demoCredentials = document.querySelector('.demo-credentials');
            
            demoCredentials.addEventListener('click', function(e) {
                if (e.target.textContent.includes('admin / admin123')) {
                    document.getElementById('usuario').value = 'admin';
                    document.getElementById('password').value = 'admin123';
                }
            });
        });
        
        // Validación del formulario
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const usuario = document.getElementById('usuario').value.trim();
            const password = document.getElementById('password').value;
            
            if (usuario.length < 3) {
                e.preventDefault();
                alert('❌ El usuario debe tener al menos 3 caracteres');
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('❌ La contraseña debe tener al menos 6 caracteres');
                return;
            }
        });
        
        // Mostrar mensaje de carga al enviar
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.querySelector('.btn-login');
            btn.textContent = '🔄 Validando...';
            btn.disabled = true;
        });
        
        // Auto-focus en el campo usuario
        document.getElementById('usuario').focus();
    </script>
</body>
</html>