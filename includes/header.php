<?php
/**
 * HEADER ACTUALIZADO CON SISTEMA DE LOGIN
 * Archivo: includes/header.php
 */

// Incluir autenticación en todas las páginas
require_once __DIR__ . '/auth.php';

// Verificar autenticación (todas las páginas requieren login)
requireAuth();

// Obtener información del usuario actual
$usuario_actual = obtenerUsuarioActual();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema AHP - Asignación de Docentes NEE</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Estilos para la barra de usuario */
        .user-bar {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            color: white;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 14px;
        }
        
        .user-role {
            font-size: 12px;
            opacity: 0.8;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 10px;
        }
        
        .user-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-logout {
            background: rgba(231, 76, 60, 0.8);
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-logout:hover {
            background: rgba(231, 76, 60, 1);
            color: white;
            text-decoration: none;
        }
        
        .session-info {
            font-size: 11px;
            opacity: 0.7;
            color: white;
        }
        
        @media (max-width: 768px) {
            .user-bar {
                padding: 8px 15px;
                flex-direction: column;
                gap: 10px;
            }
            
            .user-info {
                justify-content: center;
            }
            
            .user-details {
                text-align: center;
            }
            
            .session-info {
                display: none;
            }
        }
        
        /* Actualizar el header principal */
        .header {
            padding: 20px;
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .header h1 {
            color: white;
            margin: 0 0 5px 0;
            font-size: 28px;
        }
        
        .header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Barra de usuario -->
        <div class="user-bar">
            <div class="user-info">
                <div class="user-avatar">
                    <?php 
                    $iniciales = '';
                    $nombres = explode(' ', $usuario_actual['nombre_completo']);
                    foreach (array_slice($nombres, 0, 2) as $nombre) {
                        $iniciales .= strtoupper(substr($nombre, 0, 1));
                    }
                    echo htmlspecialchars($iniciales);
                    ?>
                </div>
                <div class="user-details">
                    <div class="user-name">
                        <?php echo htmlspecialchars($usuario_actual['nombre_completo']); ?>
                    </div>
                    <div class="user-role">
                        <?php 
                        $roles = [
                            'admin' => '👑 Administrador',
                            'coordinador' => '📋 Coordinador',
                            'docente' => '👨‍🏫 Docente'
                        ];
                        echo $roles[$usuario_actual['rol']] ?? '👤 Administrador';
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="user-actions">
                <div class="session-info">
                    Sesión iniciada: <?php echo date('d/m/Y H:i', $usuario_actual['login_time']); ?>
                </div>
                <a href="../procesar/procesar_logout.php" class="btn-logout" 
                   onclick="return confirm('¿Está seguro de que desea cerrar sesión?')">
                    🚪 Salir
                </a>
            </div>
        </div>
        
        <!-- Header principal -->
        <div class="header">
            <h1>Sistema AHP - Asignación de Docentes NEE</h1>
            <p>Facultad de Ciencias Matemáticas y Físicas - UNIVERSIDAD ESTATAL DE GUAYAQUIL</p>
        </div>