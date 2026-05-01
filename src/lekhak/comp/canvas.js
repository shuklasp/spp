import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js';

/**
 * CanvasView - The Visual Editing Hub for Lekhak
 */
export default class CanvasView extends BaseComponent {
    async onInit() {
        this.state = {
            loading: false
        };
    }

    render() {
        return html`
            <div class="lekhak-canvas">
                <header class="canvas-header">
                    <div class="header-main">
                        <h2>Visual <span>Canvas</span></h2>
                        <p>Design complex layouts with block-level precision.</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn-primary" @click="${() => location.hash = 'editor'}">🚀 Launch Editor</button>
                    </div>
                </header>

                <div class="canvas-preview-container">
                    <div class="canvas-mock">
                        <div class="mock-sidebar">
                            <div class="block-item">🧱 Text</div>
                            <div class="block-item">🖼️ Media</div>
                            <div class="block-item">📦 Widget</div>
                            <div class="block-item">📜 List</div>
                        </div>
                        <div class="mock-stage">
                            <div class="stage-placeholder">
                                <span class="pulse-icon">✨</span>
                                <h3>Ready to Design</h3>
                                <p>Drag blocks here to start building your layout.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="canvas-features">
                    <div class="feature-card">
                        <h4>Modular Blocks</h4>
                        <p>Dozens of built-in components for text, media, and interactive UI.</p>
                    </div>
                    <div class="feature-card">
                        <h4>Real-time Preview</h4>
                        <p>What you see in the canvas is exactly what your visitors will experience.</p>
                    </div>
                    <div class="feature-card">
                        <h4>Dynamic Data</h4>
                        <p>Bind blocks to your database entities with simple zero-code integration.</p>
                    </div>
                </div>
            </div>

            <style>
                .lekhak-canvas { font-family: 'Inter', sans-serif; color: #f1f5f9; }
                
                .canvas-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 2.5rem;
                    border-bottom: 1px solid #334155;
                    padding-bottom: 2rem;
                }
                .canvas-header h2 { font-family: 'Outfit'; font-size: 1.75rem; margin: 0; }
                .canvas-header h2 span { color: #6366f1; }
                .canvas-header p { color: #94a3b8; margin-top: 4px; }

                .btn-primary {
                    background: #6366f1;
                    color: white;
                    border: none;
                    padding: 0.75rem 1.25rem;
                    border-radius: 8px;
                    font-weight: 600;
                    cursor: pointer;
                    font-family: 'Outfit';
                }

                .canvas-preview-container {
                    background: #1e293b;
                    border-radius: 12px;
                    border: 1px solid #334155;
                    height: 450px;
                    margin-bottom: 2rem;
                    overflow: hidden;
                    display: flex;
                }

                .canvas-mock { display: flex; width: 100%; }
                .mock-sidebar {
                    width: 200px;
                    background: #0f172a;
                    border-right: 1px solid #334155;
                    padding: 1.5rem;
                    display: flex;
                    flex-direction: column;
                    gap: 0.75rem;
                }
                .block-item {
                    padding: 0.75rem;
                    background: #1e293b;
                    border-radius: 8px;
                    font-size: 0.85rem;
                    border: 1px dashed #475569;
                    color: #94a3b8;
                    cursor: grab;
                }

                .mock-stage {
                    flex-grow: 1;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
                }
                .stage-placeholder { text-align: center; color: #64748b; }
                .pulse-icon { font-size: 3rem; display: block; margin-bottom: 1rem; animation: pulse 2s infinite; }
                
                @keyframes pulse {
                    0% { transform: scale(1); opacity: 0.5; }
                    50% { transform: scale(1.1); opacity: 1; }
                    100% { transform: scale(1); opacity: 0.5; }
                }

                .canvas-features {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 1.5rem;
                }
                .feature-card {
                    background: #1e293b;
                    padding: 1.5rem;
                    border-radius: 12px;
                    border: 1px solid #334155;
                }
                .feature-card h4 { margin-bottom: 0.75rem; color: #f8fafc; font-family: 'Outfit'; }
                .feature-card p { font-size: 0.875rem; color: #94a3b8; line-height: 1.5; }
            </style>
        `;
    }
}
