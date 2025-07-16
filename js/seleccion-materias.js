/**
 * SCRIPT MEJORADO PARA SELECCIÓN DE MATERIAS
 * Archivo: js/seleccion-materias.js
 * 
 * Maneja la interfaz avanzada de selección de materias en asignaciones
 */

class GestorSeleccionMaterias {
    constructor() {
        this.form = null;
        this.selectores = [];
        this.estadisticas = {
            total: 0,
            seleccionadas: 0,
            sugeridas: 0,
            diferentes: 0
        };
        
        this.init();
    }
    
    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.form = document.getElementById('formAsignacionConMaterias');
            
            if (this.form) {
                this.configurarFormulario();
                this.inicializarSelectores();
                this.configurarEventos();
                this.crearInterfazAyuda();
                this.actualizarEstadisticas();
            }
        });
    }
    
    configurarFormulario() {
        // Configurar validación del formulario
        this.form.addEventListener('submit', (e) => {
            if (!this.validarFormulario()) {
                e.preventDefault();
                return false;
            }
            
            return this.confirmarEnvio();
        });
        
        // Prevenir envío accidental
        this.form.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
                e.preventDefault();
            }
        });
    }
    
    inicializarSelectores() {
        this.selectores = Array.from(this.form.querySelectorAll('.materia-selector'));
        this.estadisticas.total = this.selectores.length;
        
        this.selectores.forEach((selector, index) => {
            this.configurarSelector(selector, index);
        });
    }
    
    configurarSelector(selector, index) {
        // Añadir atributos de datos
        selector.dataset.index = index;
        selector.dataset.sugerida = selector.querySelector('option[selected]')?.value || '';
        
        // Configurar eventos
        selector.addEventListener('change', () => {
            this.onSelectorChange(selector);
        });
        
        selector.addEventListener('focus', () => {
            this.onSelectorFocus(selector);
        });
        
        selector.addEventListener('blur', () => {
            this.onSelectorBlur(selector);
        });
        
        // Aplicar estilo inicial
        this.aplicarEstiloSelector(selector);
    }
    
    onSelectorChange(selector) {
        this.aplicarEstiloSelector(selector);
        this.actualizarEstadisticas();
        this.mostrarFeedbackSelector(selector);
    }
    
    onSelectorFocus(selector) {
        const row = selector.closest('.asignacion-row');
        if (row) {
            row.style.boxShadow = '0 4px 12px rgba(52, 152, 219, 0.4)';
            row.style.transform = 'translateY(-2px)';
            row.style.transition = 'all 0.3s ease';
        }
        
        // Resaltar opciones relevantes
        this.resaltarOpcionesRelevantes(selector);
    }
    
    onSelectorBlur(selector) {
        const row = selector.closest('.asignacion-row');
        if (row) {
            row.style.boxShadow = '0 2px 5px rgba(0, 0, 0, 0.1)';
            row.style.transform = 'translateY(0)';
        }
    }
    
    aplicarEstiloSelector(selector) {
        const valor = selector.value;
        const sugerida = selector.dataset.sugerida;
        
        if (!valor) {
            // Sin selección
            selector.style.borderColor = '#dc3545';
            selector.style.backgroundColor = '#fff5f5';
            selector.style.color = '#721c24';
        } else if (valor === sugerida) {
            // Materia sugerida seleccionada
            selector.style.borderColor = '#28a745';
            selector.style.backgroundColor = '#f8fff9';
            selector.style.color = '#155724';
        } else {
            // Materia diferente seleccionada
            selector.style.borderColor = '#ffc107';
            selector.style.backgroundColor = '#fffdf0';
            selector.style.color = '#856404';
        }
    }
    
    mostrarFeedbackSelector(selector) {
        const valor = selector.value;
        const sugerida = selector.dataset.sugerida;
        
        // Eliminar feedback anterior
        const feedbackAnterior = selector.parentNode.querySelector('.feedback-selector');
        if (feedbackAnterior) {
            feedbackAnterior.remove();
        }
        
        // Crear nuevo feedback
        if (valor && valor !== sugerida) {
            const feedback = document.createElement('div');
            feedback.className = 'feedback-selector';
            feedback.style.cssText = `
                font-size: 11px;
                color: #856404;
                margin