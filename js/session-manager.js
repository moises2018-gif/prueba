/**
 * Session Manager - Manejo de sesiones y timeout
 * Archivo: js/session-manager.js
 */

// Variables para el control de sesión
let sessionTimeoutId;
let warningTimeoutId;
const SESSION_TIMEOUT = 5 * 60 * 1000; // 5 minutos en milisegundos
const WARNING_TIME = 1 * 60 * 1000; // Mostrar advertencia 1 minuto antes

// Crear el elemento del timer de sesión
function createSessionTimer() {
    const timer = document.createElement('div');
    timer.id = 'sessionTimer';
    timer.className = 'session-timer';
    timer.style.cssText = `
        position: fixed;
        top: 10px;
        right: 10px;
        background: rgba(231, 76, 60, 0.9);
        color: white;
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 0.8em;
        z-index: 999;
        display: none;
    `;
    document.body.appendChild(timer);
}

// Inicializar el control de sesión cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    createSessionTimer();
    initSessionTimeout();
});

// Función para inicializar el control de sesión
function initSessionTimeout() {
    resetSessionTimeout();
    
    // Reiniciar el timeout cuando hay actividad del usuario
    document.addEventListener('mousemove', resetSessionTimeout);
    document.addEventListener('keypress', resetSessionTimeout);
    document.addEventListener('click', resetSessionTimeout);
    document.addEventListener('scroll', resetSessionTimeout);
    document.addEventListener('touchstart', resetSessionTimeout);
    document.addEventListener('touchmove', resetSessionTimeout);
}

// Función para reiniciar el timeout de sesión
function resetSessionTimeout() {
    // Limpiar timeouts existentes
    clearTimeout(sessionTimeoutId);
    clearTimeout(warningTimeoutId);
    
    // Ocultar el timer si está visible
    const timer = document.getElementById('sessionTimer');
    if (timer) {
        timer.style.display = 'none';
    }
    
    // Establecer warning timeout (mostrar advertencia)
    warningTimeoutId = setTimeout(showSessionWarning, SESSION_TIMEOUT - WARNING_TIME);
    
    // Establecer logout timeout
    sessionTimeoutId = setTimeout(autoLogout, SESSION_TIMEOUT);
}

// Función para mostrar advertencia de sesión
function showSessionWarning() {
    const timer = document.getElementById('sessionTimer');
    if (!timer) return;
    
    let timeLeft = WARNING_TIME / 1000; // Convertir a segundos
    
    timer.style.display = 'block';
    
    const countdown = setInterval(() => {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        timer.textContent = `Sesión expira en: ${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        timeLeft--;
        
        if (timeLeft < 0) {
            clearInterval(countdown);
        }
    }, 1000);
}

// Función para cerrar sesión automáticamente
function autoLogout() {
    // Mostrar alerta de expiración de sesión
    if (typeof mostrarAlerta === 'function') {
        mostrarAlerta('Su sesión ha expirado por inactividad. Será redirigido al login.', 'warning');
    } else {
        alert('Su sesión ha expirado por inactividad. Será redirigido al login.');
    }
    
    // Redirigir después de 2 segundos
    setTimeout(() => {
        window.location.href = '../php/logout.php';
    }, 2000);
}

// Función para extender la sesión manualmente (opcional)
function extendSession() {
    resetSessionTimeout();
    
    if (typeof mostrarAlerta === 'function') {
        mostrarAlerta('Sesión extendida exitosamente', 'success');
    }
}

// Función para verificar si la sesión está activa (opcional)
async function checkSessionStatus() {
    try {
        const response = await fetch('../php/check_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        const data = await response.json();
        
        if (!data.active) {
            // Sesión no activa, redirigir al login
            window.location.href = '../login.php';
        }
        
        return data.active;
    } catch (error) {
        console.error('Error al verificar sesión:', error);
        return false;
    }
}

// Función para limpiar todos los timeouts (útil para testing)
function clearAllTimeouts() {
    clearTimeout(sessionTimeoutId);
    clearTimeout(warningTimeoutId);
    
    const timer = document.getElementById('sessionTimer');
    if (timer) {
        timer.style.display = 'none';
    }
}

// Exportar funciones para uso global
window.SessionManager = {
    init: initSessionTimeout,
    reset: resetSessionTimeout,
    extend: extendSession,
    checkStatus: checkSessionStatus,
    clear: clearAllTimeouts
};