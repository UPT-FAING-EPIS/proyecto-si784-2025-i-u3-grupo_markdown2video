// --- Lógica mejorada para Mermaid.js con expansión a pantalla completa ---

document.addEventListener('DOMContentLoaded', function() {
    // 1. Inicialización de Mermaid.js
    if (typeof mermaid !== 'undefined') {
        try {
            mermaid.initialize({ startOnLoad: false, theme: 'neutral', securityLevel: 'loose' });
        } catch (e) {
            console.error("Error inicializando Mermaid:", e);
        }
    }

    // 2. Lógica para abrir y cerrar el modal de expansión
    const mermaidModal = document.getElementById('mermaidModal');
    const closeMermaidModalBtn = document.getElementById('closeMermaidModalBtn');
    const mermaidModalBody = document.getElementById('mermaidModalBody');
    
    if (closeMermaidModalBtn && mermaidModal) {
        closeMermaidModalBtn.addEventListener('click', () => {
            mermaidModal.style.display = 'none';
            mermaidModalBody.innerHTML = ''; // Limpiar contenido al cerrar
        });
    }

    // 3. Añadimos un listener al body para capturar los clics en los botones de expandir
    document.body.addEventListener('click', function(event) {
        const expandBtn = event.target.closest('.mermaid-expand-btn');
        if (expandBtn && mermaidModal) {
            // Obtenemos el SVG del diagrama que está justo al lado del botón
            const diagramContainer = expandBtn.closest('.mermaid-container');
            const svgElement = diagramContainer ? diagramContainer.querySelector('svg') : null;

            if (svgElement) {
                // Clonamos el SVG para no mover el original y lo ponemos en el modal
                mermaidModalBody.innerHTML = ''; // Limpiar por si acaso
                mermaidModalBody.appendChild(svgElement.cloneNode(true));
                mermaidModal.style.display = 'flex'; // Mostramos el modal
            }
        }
    });
});

// 4. Función global para renderizar los diagramas (modificada para añadir el botón)
function renderMermaidDiagrams(targetElement) {
    if (typeof mermaid === 'undefined' || !targetElement) return;

    const mermaidElements = targetElement.querySelectorAll('code.language-mermaid');

    mermaidElements.forEach((element, index) => {
        const diagramId = `mermaid-diagram-${Date.now()}-${index}`;
        const mermaidCode = element.textContent || '';

        try {
            mermaid.render(diagramId, mermaidCode, (svgCode) => {
                const containerDiv = document.createElement('div');
                containerDiv.className = 'mermaid-container';
                
                // --- ¡NUEVO! Añadimos el SVG y el botón de expandir ---
                containerDiv.innerHTML = `
                    ${svgCode}
                    <button class="mermaid-expand-btn" title="Expandir diagrama">
                        <i class="fa-solid fa-expand"></i>
                    </button>
                `;
                
                if (element.parentNode && element.parentNode.parentNode) {
                    element.parentNode.parentNode.replaceChild(containerDiv, element.parentNode);
                }
            });
        } catch (error) {
            console.error(`Error renderizando diagrama Mermaid #${index}:`, error);
        }
    });
}