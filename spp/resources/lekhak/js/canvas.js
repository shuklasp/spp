/**
 * Lekhak Canvas
 * Native block-based editor for Lekhak CMS.
 */
class LekhakCanvas {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.blocks = [];
        this.init();
    }

    init() {
        this.container.classList.add('lekhak-canvas');
        this.addBlock('paragraph', 'Start writing your story...');
        this.render();
    }

    addBlock(type, content = '') {
        const block = {
            id: 'b' + Math.random().toString(36).substr(2, 9),
            type: type,
            content: content
        };
        this.blocks.push(block);
        return block;
    }

    render() {
        this.container.innerHTML = '';
        this.blocks.forEach(block => {
            const blockEl = document.createElement('div');
            blockEl.className = `canvas-block block-${block.type}`;
            blockEl.contentEditable = true;
            blockEl.innerHTML = block.content;
            
            blockEl.oninput = (e) => {
                block.content = e.target.innerHTML;
            };

            // Command / Wiki Link detection
            blockEl.onkeyup = (e) => {
                if (e.target.innerText.endsWith('[[')) {
                    this.showWikiSuggester(blockEl);
                }
            };

            this.container.appendChild(blockEl);
        });
    }

    showWikiSuggester(el) {
        console.log('Wiki Suggester triggered');
        // Future: Show a dropdown with node titles
    }

    getContent() {
        return JSON.stringify(this.blocks);
    }
}
