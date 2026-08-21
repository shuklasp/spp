/**
 * SPP-UX Drag and Drop Engine
 * 
 * Extracted from the monolithic sppux.js (v11 lines 2084-2301).
 * Provides Draggable and Sortable classes for pointer-based
 * drag-and-drop interactions.
 * 
 * @module dnd
 * @version 13.0.0
 */

/**
 * Make an element draggable via pointer events.
 * 
 * @example
 * const d = new Draggable(element, {
 *     handle: '.drag-handle',
 *     onDragEnd: (el, x, y) => console.log('Dropped at', x, y)
 * });
 */
export class Draggable {
    constructor(element, options = {}) {
        this.element = typeof element === 'string' ? document.querySelector(element) : element;
        if (!this.element) return;
        this.options = Object.assign({
            handle: null,
            onDragStart: () => {},
            onDrag: () => {},
            onDragEnd: () => {}
        }, options);

        this.isDragging = false;
        this.handleNode = this.options.handle ? this.element.querySelector(this.options.handle) || this.element : this.element;

        this.onPointerDown = this.onPointerDown.bind(this);
        this.onPointerMove = this.onPointerMove.bind(this);
        this.onPointerUp = this.onPointerUp.bind(this);

        this.handleNode.addEventListener('pointerdown', this.onPointerDown);
        this.handleNode.style.cursor = 'move';
        this.handleNode.style.touchAction = 'none';
    }

    onPointerDown(e) {
        if (e.button && e.button !== 0) return;
        this.isDragging = true;
        this.startX = e.clientX;
        this.startY = e.clientY;

        const rect = this.element.getBoundingClientRect();
        const parentRect = this.element.offsetParent ? this.element.offsetParent.getBoundingClientRect() : {left: 0, top: 0};

        const styleLeft = parseFloat(this.element.style.left);
        const styleTop = parseFloat(this.element.style.top);

        this.startLeft = isNaN(styleLeft) ? (rect.left - parentRect.left) : styleLeft;
        this.startTop = isNaN(styleTop) ? (rect.top - parentRect.top) : styleTop;

        document.addEventListener('pointermove', this.onPointerMove);
        document.addEventListener('pointerup', this.onPointerUp);

        this.element.setPointerCapture(e.pointerId);
        this.originalZ = this.element.style.zIndex;
        this.element.style.zIndex = '9999';

        this.options.onDragStart(this.element);
    }

    onPointerMove(e) {
        if (!this.isDragging) return;
        const dx = e.clientX - this.startX;
        const dy = e.clientY - this.startY;

        const newLeft = this.startLeft + dx;
        const newTop = this.startTop + dy;

        this.element.style.left = newLeft + 'px';
        this.element.style.top = newTop + 'px';

        this.options.onDrag(this.element, newLeft, newTop);
    }

    onPointerUp(e) {
        if (!this.isDragging) return;
        this.isDragging = false;

        document.removeEventListener('pointermove', this.onPointerMove);
        document.removeEventListener('pointerup', this.onPointerUp);

        try { this.element.releasePointerCapture(e.pointerId); } catch(err){}
        this.element.style.zIndex = this.originalZ;

        this.options.onDragEnd(this.element, parseFloat(this.element.style.left), parseFloat(this.element.style.top));
    }

    destroy() {
        this.handleNode.removeEventListener('pointerdown', this.onPointerDown);
        this.handleNode.style.cursor = '';
        this.handleNode.style.touchAction = '';
    }
}

/**
 * Make children of a container sortable via drag-and-drop.
 * 
 * @example
 * const s = new Sortable(listElement, {
 *     itemSelector: '.list-item',
 *     handle: '.grip',
 *     onSortEnd: (item, newIndex, items) => saveSortOrder(items)
 * });
 */
export class Sortable {
    constructor(container, options = {}) {
        this.container = typeof container === 'string' ? document.querySelector(container) : container;
        if (!this.container) return;
        this.options = Object.assign({
            itemSelector: '> *',
            handle: null,
            onSortEnd: () => {}
        }, options);

        this.onPointerDown = this.onPointerDown.bind(this);
        this.onPointerMove = this.onPointerMove.bind(this);
        this.onPointerUp = this.onPointerUp.bind(this);

        this.container.addEventListener('pointerdown', this.onPointerDown);
        this.container.style.touchAction = 'none';
    }

    onPointerDown(e) {
        if (e.button && e.button !== 0) return;

        let target = e.target;
        if (this.options.handle) {
            const handleEl = target.closest(this.options.handle);
            if (!handleEl || !this.container.contains(handleEl)) return;
            target = handleEl;
        }

        const item = target.closest(this.options.itemSelector);
        if (!item || item === this.container) return;

        e.preventDefault();

        this.dragItem = item;
        this.items = Array.from(this.container.querySelectorAll(this.options.itemSelector));
        this.dragIndex = this.items.indexOf(this.dragItem);

        this.startX = e.clientX;
        this.startY = e.clientY;

        const rect = this.dragItem.getBoundingClientRect();

        this.ghost = this.dragItem.cloneNode(true);
        this.ghost.style.position = 'fixed';
        this.ghost.style.left = rect.left + 'px';
        this.ghost.style.top = rect.top + 'px';
        this.ghost.style.width = rect.width + 'px';
        this.ghost.style.height = rect.height + 'px';
        this.ghost.style.margin = '0';
        this.ghost.style.zIndex = '99999';
        this.ghost.style.opacity = '0.9';
        this.ghost.style.pointerEvents = 'none';
        this.ghost.style.boxShadow = '0 10px 25px rgba(0,0,0,0.2)';
        this.ghost.style.transition = 'none';

        document.body.appendChild(this.ghost);

        this.dragItem.style.opacity = '0';

        this.items.forEach(el => {
            if (el !== this.dragItem) {
                el.style.transition = 'transform 0.2s ease-in-out';
            }
        });

        document.addEventListener('pointermove', this.onPointerMove);
        document.addEventListener('pointerup', this.onPointerUp);
    }

    onPointerMove(e) {
        if (!this.dragItem) return;

        const dx = e.clientX - this.startX;
        const dy = e.clientY - this.startY;

        this.ghost.style.transform = `translate(${dx}px, ${dy}px)`;

        let overItem = null;
        for (const item of this.items) {
            if (item === this.dragItem) continue;
            const rect = item.getBoundingClientRect();
            if (e.clientY > rect.top && e.clientY < rect.bottom && e.clientX > rect.left && e.clientX < rect.right) {
                overItem = item;
                break;
            }
        }

        if (overItem) {
            const overIndex = this.items.indexOf(overItem);
            const isMovingDown = overIndex > this.dragIndex;

            if (isMovingDown) {
                this.container.insertBefore(this.dragItem, overItem.nextSibling);
            } else {
                this.container.insertBefore(this.dragItem, overItem);
            }

            this.items = Array.from(this.container.querySelectorAll(this.options.itemSelector));
            this.dragIndex = this.items.indexOf(this.dragItem);
        }
    }

    onPointerUp(e) {
        if (!this.dragItem) return;

        document.removeEventListener('pointermove', this.onPointerMove);
        document.removeEventListener('pointerup', this.onPointerUp);

        const rect = this.dragItem.getBoundingClientRect();
        this.ghost.style.transition = 'transform 0.2s ease, opacity 0.2s ease';
        this.ghost.style.transform = `translate(${rect.left - parseFloat(this.ghost.style.left)}px, ${rect.top - parseFloat(this.ghost.style.top)}px)`;

        setTimeout(() => {
            if (this.ghost && this.ghost.parentNode) {
                this.ghost.parentNode.removeChild(this.ghost);
            }
            this.ghost = null;
            if (this.dragItem) this.dragItem.style.opacity = '';

            this.items.forEach(el => {
                el.style.transition = '';
            });

            if (this.dragItem) {
                const newIndex = this.items.indexOf(this.dragItem);
                this.options.onSortEnd(this.dragItem, newIndex, this.items);
            }
            this.dragItem = null;
        }, 200);
    }
}
