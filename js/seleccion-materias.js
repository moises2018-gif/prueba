/**
 * SCRIPT COMPLETO PARA SELECCIÓN DE MATERIAS
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
                margin-top: 3px;
                padding: 3px 6px;
                background: rgba(255, 193, 7, 0.1);
                border-radius: 3px;
                border-left: 3px solid #ffc107;
            `;
            feedback.textContent = '⚠️ Materia diferente a la sugerida';
            
            selector.parentNode.appendChild(feedback);
        }
    }
    
    resaltarOpcionesRelevantes(selector) {
        const opciones = selector.querySelectorAll('option');
        const facultadEstudiante = this.obtenerFacultadEstudiante(selector);
        
        opciones.forEach(opcion => {
            if (opcion.textContent.includes(facultadEstudiante)) {
                opcion.style.backgroundColor = '#e3f2fd';
                opcion.style.fontWeight = 'bold';
            }
        });
    }
    
    obtenerFacultadEstudiante(selector) {
        const row = selector.closest('.asignacion-row');
        if (row) {
            const facultadElement = row.querySelector('[data-facultad]');
            return facultadElement?.dataset.facultad || '';
        }
        return '';
    }
    
    configurarEventos() {
        // Evento para seleccionar todas las sugeridas
        const btnTodasSugeridas = document.getElementById('btnTodasSugeridas');
        if (btnTodasSugeridas) {
            btnTodasSugeridas.addEventListener('click', () => {
                this.seleccionarTodasLasSugeridas();
            });
        }
        
        // Evento para limpiar selecciones
        const btnLimpiar = document.getElementById('btnLimpiarSelecciones');
        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', () => {
                this.limpiarSelecciones();
            });
        }
        
        // Evento para filtrar por facultad
        const selectFiltro = document.getElementById('filtroFacultad');
        if (selectFiltro) {
            selectFiltro.addEventListener('change', (e) => {
                this.filtrarPorFacultad(e.target.value);
            });
        }
    }
    
    crearInterfazAyuda() {
        const contenedorAyuda = document.createElement('div');
        contenedorAyuda.className = 'interfaz-ayuda-materias';
        contenedorAyuda.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        `;
        
        // Botón estadísticas
        const btnEstadisticas = this.crearBotonAyuda('📊 Estadísticas', 'bg-info', () => {
            this.mostrarEstadisticas();
        });
        
        // Botón todas sugeridas
        const btnTodasSugeridas = this.crearBotonAyuda('🎯 Usar Sugeridas', 'bg-success', () => {
            this.seleccionarTodasLasSugeridas();
        });
        btnTodasSugeridas.id = 'btnTodasSugeridas';
        
        // Botón limpiar
        const btnLimpiar = this.crearBotonAyuda('🧹 Limpiar Todo', 'bg-warning', () => {
            this.limpiarSelecciones();
        });
        btnLimpiar.id = 'btnLimpiarSelecciones';
        
        // Botón validar
        const btnValidar = this.crearBotonAyuda('✅ Validar', 'bg-primary', () => {
            this.validarYMostrarResultados();
        });
        
        contenedorAyuda.appendChild(btnEstadisticas);
        contenedorAyuda.appendChild(btnTodasSugeridas);
        contenedorAyuda.appendChild(btnLimpiar);
        contenedorAyuda.appendChild(btnValidar);
        
        document.body.appendChild(contenedorAyuda);
    }
    
    crearBotonAyuda(texto, clase, onClick) {
        const btn = document.createElement('button');
        btn.textContent = texto;
        btn.className = `btn ${clase}`;
        btn.style.cssText = `
            padding: 8px 12px;
            font-size: 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            white-space: nowrap;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        `;
        btn.addEventListener('click', onClick);
        return btn;
    }
    
    actualizarEstadisticas() {
        this.estadisticas.seleccionadas = this.selectores.filter(s => s.value).length;
        this.estadisticas.sugeridas = this.selectores.filter(s => s.value === s.dataset.sugerida && s.value).length;
        this.estadisticas.diferentes = this.estadisticas.seleccionadas - this.estadisticas.sugeridas;
    }
    
    mostrarEstadisticas() {
        this.actualizarEstadisticas();
        
        const modal = this.crearModal('Estadísticas de Selección', `
            <div style="text-align: left;">
                <p><strong>Total de asignaciones:</strong> ${this.estadisticas.total}</p>
                <p><strong>Materias seleccionadas:</strong> ${this.estadisticas.seleccionadas}</p>
                <p><strong>Materias sugeridas usadas:</strong> ${this.estadisticas.sugeridas}</p>
                <p><strong>Materias diferentes:</strong> ${this.estadisticas.diferentes}</p>
                <p><strong>Porcentaje completo:</strong> ${Math.round((this.estadisticas.seleccionadas / this.estadisticas.total) * 100)}%</p>
                
                <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                    <strong>Estado:</strong> 
                    ${this.estadisticas.seleccionadas === this.estadisticas.total ? 
                        '<span style="color: #28a745;">✅ Todas las materias seleccionadas</span>' : 
                        '<span style="color: #dc3545;">⚠️ Faltan ' + (this.estadisticas.total - this.estadisticas.seleccionadas) + ' materias por seleccionar</span>'
                    }
                </div>
            </div>
        `);
        
        document.body.appendChild(modal);
    }
    
    seleccionarTodasLasSugeridas() {
        let contador = 0;
        
        this.selectores.forEach(selector => {
            const sugerida = selector.dataset.sugerida;
            if (sugerida && !selector.value) {
                selector.value = sugerida;
                this.aplicarEstiloSelector(selector);
                contador++;
            }
        });
        
        this.actualizarEstadisticas();
        this.mostrarNotificacion(`✅ Se seleccionaron ${contador} materias sugeridas automáticamente`, 'success');
    }
    
    limpiarSelecciones() {
        if (confirm('¿Está seguro de que desea limpiar todas las selecciones de materias?')) {
            this.selectores.forEach(selector => {
                selector.value = '';
                this.aplicarEstiloSelector(selector);
                
                // Limpiar feedback
                const feedback = selector.parentNode.querySelector('.feedback-selector');
                if (feedback) {
                    feedback.remove();
                }
            });
            
            this.actualizarEstadisticas();
            this.mostrarNotificacion('🧹 Todas las selecciones han sido limpiadas', 'warning');
        }
    }
    
    filtrarPorFacultad(facultad) {
        this.selectores.forEach(selector => {
            const opciones = selector.querySelectorAll('option');
            
            opciones.forEach(opcion => {
                if (!facultad || facultad === '' || opcion.textContent.includes(facultad) || opcion.value === '') {
                    opcion.style.display = 'block';
                } else {
                    opcion.style.display = 'none';
                }
            });
        });
        
        this.mostrarNotificacion(`🔍 Filtro aplicado: ${facultad || 'Todas las facultades'}`, 'info');
    }
    
    validarFormulario() {
        const errores = [];
        
        this.selectores.forEach((selector, index) => {
            if (!selector.value) {
                errores.push(`Asignación ${index + 1}: Sin materia seleccionada`);
            }
        });
        
        if (errores.length > 0) {
            this.mostrarModal('❌ Errores de Validación', `
                <div style="text-align: left;">
                    <p>Se encontraron los siguientes errores:</p>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        ${errores.map(error => `<li style="color: #dc3545;">${error}</li>`).join('')}
                    </ul>
                    <p style="margin-top: 15px; font-weight: bold;">Por favor corrija estos errores antes de continuar.</p>
                </div>
            `);
            return false;
        }
        
        return true;
    }
    
    validarYMostrarResultados() {
        if (this.validarFormulario()) {
            this.actualizarEstadisticas();
            
            const resumen = `
                <div style="text-align: left;">
                    <h4 style="color: #28a745; margin-bottom: 15px;">✅ Validación Exitosa</h4>
                    
                    <div style="background: #f8fff9; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                        <p><strong>Resumen de la selección:</strong></p>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li>${this.estadisticas.total} asignaciones totales</li>
                            <li>${this.estadisticas.seleccionadas} materias seleccionadas</li>
                            <li>${this.estadisticas.sugeridas} materias sugeridas utilizadas</li>
                            <li>${this.estadisticas.diferentes} materias personalizadas</li>
                        </ul>
                    </div>
                    
                    <div style="background: #e3f2fd; padding: 15px; border-radius: 5px;">
                        <p><strong>🎯 Estado:</strong> Listo para confirmar las asignaciones</p>
                        <p style="margin-top: 10px; font-size: 14px; color: #666;">
                            Puede proceder a confirmar las asignaciones o realizar ajustes adicionales.
                        </p>
                    </div>
                </div>
            `;
            
            this.mostrarModal('Validación de Materias', resumen);
        }
    }
    
    confirmarEnvio() {
        this.actualizarEstadisticas();
        
        const mensaje = `¿Está seguro de confirmar ${this.estadisticas.total} asignaciones con las materias seleccionadas?\n\n` +
                       `• ${this.estadisticas.sugeridas} materias sugeridas\n` +
                       `• ${this.estadisticas.diferentes} materias personalizadas`;
        
        return confirm(mensaje);
    }
    
    mostrarNotificacion(mensaje, tipo = 'info') {
        const colores = {
            success: '#28a745',
            warning: '#ffc107',
            error: '#dc3545',
            info: '#17a2b8'
        };
        
        const notificacion = document.createElement('div');
        notificacion.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${colores[tipo]};
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            z-index: 1001;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            max-width: 300px;
            font-size: 14px;
            font-weight: 500;
        `;
        notificacion.textContent = mensaje;
        
        document.body.appendChild(notificacion);
        
        setTimeout(() => {
            notificacion.remove();
        }, 4000);
    }
    
    crearModal(titulo, contenido) {
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1002;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        const contenidoModal = document.createElement('div');
        contenidoModal.style.cssText = `
            background: white;
            padding: 25px;
            border-radius: 10px;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        `;
        
        contenidoModal.innerHTML = `
            <h3 style="margin-top: 0; margin-bottom: 20px; color: #2c3e50;">${titulo}</h3>
            ${contenido}
            <div style="margin-top: 20px; text-align: center;">
                <button onclick="this.closest('.modal-temp').remove()" 
                        style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                    Cerrar
                </button>
            </div>
        `;
        
        modal.className = 'modal-temp';
        modal.appendChild(contenidoModal);
        
        // Cerrar al hacer clic fuera del modal
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
        
        return modal;
    }
    
    mostrarModal(titulo, contenido) {
        const modal = this.crearModal(titulo, contenido);
        document.body.appendChild(modal);
    }
}

// Inicializar el gestor cuando el DOM esté listo
const gestorMaterias = new GestorSeleccionMaterias();

// Funciones globales para compatibilidad con el código existente
window.seleccionarTodasLasSugeridas = function() {
    if (gestorMaterias && gestorMaterias.seleccionarTodasLasSugeridas) {
        gestorMaterias.seleccionarTodasLasSugeridas();
    }
};

window.limpiarSelecciones = function() {
    if (gestorMaterias && gestorMaterias.limpiarSelecciones) {
        gestorMaterias.limpiarSelecciones();
    }
};

window.filtrarMateriasPorFacultad = function(facultad, selectorId) {
    const selector = document.getElementById(selectorId);
    if (selector) {
        const opciones = selector.querySelectorAll('option');
        
        opciones.forEach(function(opcion) {
            if (opcion.value === '' || !facultad || opcion.textContent.includes(facultad)) {
                opcion.style.display = 'block';
            } else {
                opcion.style.display = 'none';
            }
        });
    }
};