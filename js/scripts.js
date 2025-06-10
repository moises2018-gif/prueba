// Función para manejar mensajes de éxito/error en la URL
document.addEventListener('DOMContentLoaded', () => {
    // Obtener parámetros de la URL
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get('success');
    const error = urlParams.get('error');
    
    // Si hay mensaje de éxito, mostrarlo
    if (success) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-success';
        alertDiv.textContent = success;
        
        // Insertar después del nav
        const navElement = document.querySelector('.nav-tabs');
        if (navElement && navElement.nextSibling) {
            navElement.parentNode.insertBefore(alertDiv, navElement.nextSibling);
        }
        
        // Eliminar el mensaje después de 5 segundos
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
    
    // Si hay mensaje de error, mostrarlo
    if (error) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-error';
        alertDiv.textContent = error;
        
        // Insertar después del nav
        const navElement = document.querySelector('.nav-tabs');
        if (navElement && navElement.nextSibling) {
            navElement.parentNode.insertBefore(alertDiv, navElement.nextSibling);
        }
        
        // Eliminar el mensaje después de 5 segundos
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
});

// Función para confirmar eliminación
function confirmarEliminacion(mensaje) {
    return confirm(mensaje || '¿Está seguro de que desea eliminar este registro?');
}