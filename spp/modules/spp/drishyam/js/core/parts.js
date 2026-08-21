/**
 * SPP-UX Fine-Grained DOM Parts (v14)
 * 
 * Implements lit-html style template caching and fine-grained DOM updaters.
 * Replaces full-tree real DOM diffing with precise node-level updates.
 * 
 * @module core/parts
 */

import { effect, Signal } from './reactive.js';
import { registerHandler, removeHandler } from './events.js';

const marker = `__spp_${Math.random().toString(36).slice(2)}__`;
const nodeMarker = `<!--${marker}-->`;

/**
 * Represents a tagged template literal that hasn't been rendered yet.
 */
export class TemplateResult {
    constructor(strings, values) {
        this.strings = strings;
        this.values = values;
    }
}

const templateCache = new WeakMap();

/**
 * Parses a TemplateStringsArray into a reusable <template> and records
 * the paths to the dynamic "holes".
 */
export function getTemplate(strings) {
    if (templateCache.has(strings)) return templateCache.get(strings);

    // [AOT Pre-compilation Cache] Check if CLI optimized this template
    const hash = strings.join('$$spp$$');
    if (window.__SPP_UX_CACHE__ && window.__SPP_UX_CACHE__[hash]) {
        const cached = window.__SPP_UX_CACHE__[hash];
        const tpl = document.createElement('template');
        tpl.innerHTML = cached.html;
        const template = { tpl, parts: cached.parts };
        templateCache.set(strings, template);
        return template;
    }

    // JIT Fallback Parsing
    let html = '';
    for (let i = 0; i < strings.length - 1; i++) {
        html += strings[i];
        // Heuristic: Are we inside an HTML tag?
        const lastOpen = html.lastIndexOf('<');
        const lastClose = html.lastIndexOf('>');
        if (lastOpen > lastClose) {
            html += marker + i;
        } else {
            html += `<!--${marker}${i}-->`;
        }
    }
    html += strings[strings.length - 1];

    const tpl = document.createElement('template');
    tpl.innerHTML = html;

    const parts = [];
    const walker = document.createTreeWalker(tpl.content, 1 | 128);
    let node;

    const getPath = (n) => {
        const path = [];
        let curr = n;
        while (curr.parentNode) {
            path.push(Array.from(curr.parentNode.childNodes).indexOf(curr));
            curr = curr.parentNode;
        }
        return path.reverse();
    };

    while ((node = walker.nextNode())) {
        if (node.nodeType === 8 && node.data.startsWith(marker)) {
            const index = parseInt(node.data.substring(marker.length), 10);
            parts.push({ type: 'node', index, path: getPath(node) });
        } else if (node.nodeType === 1) {
            const attrsToRemove = [];
            for (const attr of Array.from(node.attributes)) {
                if (attr.name.includes(marker) || attr.value.includes(marker)) {
                    const isName = attr.name.includes(marker);
                    const indexMatch = isName ? attr.name.match(new RegExp(`${marker}(\\d+)`)) : attr.value.match(new RegExp(`${marker}(\\d+)`));
                    if (!indexMatch) continue;
                    
                    const index = parseInt(indexMatch[1], 10);
                    let type = 'attr';
                    let name = attr.name;
                    
                    if (isName) name = name.replace(new RegExp(`${marker}\\d+`), '');

                    if (name.startsWith('@') || name.startsWith('data-spp-evt')) {
                        type = 'event';
                        name = name.startsWith('@') ? name.substring(1) : name.replace('data-spp-evt-', '').replace('data-spp-evt', 'click');
                    } else if (name.startsWith('?')) {
                        type = 'boolean';
                        name = name.substring(1);
                    } else if (name.startsWith('.')) {
                        type = 'property';
                        name = name.substring(1);
                    }

                    parts.push({ type, name, index, path: getPath(node) });
                    attrsToRemove.push(attr.name);
                }
            }
            attrsToRemove.forEach(a => node.removeAttribute(a));
        }
    }

    const template = { tpl, parts };
    templateCache.set(strings, template);
    return template;
}

export class TemplateInstance {
    constructor(template, existingRoot = null) {
        this.template = template;
        this.parts = new Array(template.parts.length);
        
        const isHydrating = existingRoot !== null;
        this.fragment = isHydrating ? existingRoot : template.tpl.content.cloneNode(true);
        
        const getNode = (path) => {
            let curr = this.fragment;
            // When hydrating from container, children start directly inside
            for (const i of path) curr = curr.childNodes[i];
            return curr;
        };

        for (const desc of template.parts) {
            const node = getNode(desc.path);
            let part;
            
            if (desc.type === 'node') {
                if (isHydrating) {
                    // For node holes, the server must output a comment or an element we can bound.
                    // If it's a TextNode or Element, we wrap it in our comment boundaries for future updates.
                    const startNode = document.createComment('');
                    const endNode = document.createComment('');
                    node.parentNode.insertBefore(startNode, node);
                    // insert endNode after the target node so the target node is preserved initially
                    if (node.nextSibling) {
                        node.parentNode.insertBefore(endNode, node.nextSibling);
                    } else {
                        node.parentNode.appendChild(endNode);
                    }
                    part = new NodePart(startNode, endNode);
                    // We don't remove `node` here, it is the initial server-rendered state!
                    // We seed the part's value with it to prevent overriding it if unchanged.
                    part._textNode = node.nodeType === 3 ? node : null;
                } else {
                    const startNode = document.createComment('');
                    const endNode = document.createComment('');
                    node.parentNode.insertBefore(startNode, node);
                    node.parentNode.insertBefore(endNode, node);
                    node.parentNode.removeChild(node);
                    part = new NodePart(startNode, endNode);
                }
            } else if (desc.type === 'attr') {
                part = new AttributePart(node, desc.name);
            } else if (desc.type === 'event') {
                part = new EventPart(node, desc.name);
            } else if (desc.type === 'boolean') {
                part = new BooleanPart(node, desc.name);
            } else if (desc.type === 'property') {
                part = new PropertyPart(node, desc.name);
            }

            this.parts[desc.index] = part;
        }
    }

    update(values) {
        for (let i = 0; i < this.parts.length; i++) {
            if (this.parts[i]) {
                this.parts[i].setValue(values[i]);
            }
        }
    }
}

class Part {
    constructor() {
        this.value = undefined;
        this._unsub = null;
    }

    setValue(newValue) {
        if (newValue instanceof Signal) {
            if (this._unsub) this._unsub();
            this._unsub = effect(() => {
                this.commit(newValue.value);
            });
        } else {
            if (this._unsub) {
                this._unsub();
                this._unsub = null;
            }
            this.commit(newValue);
        }
    }

    commit(value) {}
}

export class NodePart extends Part {
    constructor(startNode, endNode) {
        super();
        this.startNode = startNode;
        this.endNode = endNode;
        this._instance = null;
        this._textNode = null;
        this._arrayItems = [];
    }

    commit(value) {
        if (value === this.value) return;

        if (value instanceof TemplateResult) {
            this._commitTemplate(value);
        } else if (value && value._isPortal) {
            this._commitPortal(value);
        } else if (value && value._isRepeat) {
            this._commitRepeat(value);
        } else if (value && value._isUntil) {
            this._commitUntil(value);
        } else if (Array.isArray(value)) {
            this._commitIterable(value);
        } else if (value === null || value === undefined) {
            this._clear();
        } else {
            this._commitText(String(value));
        }
        
        this.value = value;
    }

    _commitText(text) {
        if (this._textNode && this._textNode.parentNode === this.startNode.parentNode) {
            this._textNode.data = text;
        } else {
            this._clear();
            this._textNode = document.createTextNode(text);
            this.startNode.parentNode.insertBefore(this._textNode, this.endNode);
        }
    }

    _commitTemplate(result) {
        if (this._instance && this._instance.template === getTemplate(result.strings)) {
            this._instance.update(result.values);
        } else {
            this._clear();
            const template = getTemplate(result.strings);
            this._instance = new TemplateInstance(template);
            this._instance.update(result.values);
            this.startNode.parentNode.insertBefore(this._instance.fragment, this.endNode);
        }
    }

    _commitRepeat(directive) {
        this._clearNonArray();
        
        const { items, keyFn, templateFn } = directive;
        const newArrayItems = [];
        const newKeyMap = new Map();
        
        // Phase 1: Create or update parts
        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            const key = keyFn(item, i);
            const templateResult = templateFn(item, i);
            
            let part;
            if (this._keyMap && this._keyMap.has(key)) {
                part = this._keyMap.get(key);
                this._keyMap.delete(key);
            } else {
                const start = document.createComment('');
                const end = document.createComment('');
                part = new NodePart(start, end);
                part._key = key;
                part._isNew = true;
            }
            
            part.commit(templateResult);
            newArrayItems.push(part);
            newKeyMap.set(key, part);
        }
        
        // Phase 2: Remove old unmapped parts
        if (this._keyMap) {
            for (const part of this._keyMap.values()) {
                part._clear();
                if (part.startNode.parentNode) part.startNode.parentNode.removeChild(part.startNode);
                if (part.endNode.parentNode) part.endNode.parentNode.removeChild(part.endNode);
            }
        }
        
        // Phase 3: Insert and move nodes into correct order
        const parent = this.startNode.parentNode;
        let insertAnchor = this.startNode.nextSibling;
        
        for (let i = 0; i < newArrayItems.length; i++) {
            const part = newArrayItems[i];
            
            // If the part is new or out of order, move its nodes
            if (part._isNew || part.startNode !== insertAnchor) {
                let curr = part.startNode;
                const end = part.endNode.nextSibling;
                
                // If it's new, we need to extract from wherever it was (nowhere)
                // If it's existing, we detach its entire range and move it
                const frag = document.createDocumentFragment();
                while (curr && curr !== end) {
                    const next = curr.nextSibling;
                    frag.appendChild(curr);
                    curr = next;
                }
                
                // insertAnchor could be a previous part's node, or this.endNode if we are at the end
                parent.insertBefore(frag, insertAnchor);
            }
            
            part._isNew = false;
            insertAnchor = part.endNode.nextSibling;
        }
        
        this._arrayItems = newArrayItems;
        this._keyMap = newKeyMap;
    }

    _commitUntil(directive) {
        const { promise, defaultContent } = directive;
        
        // Render fallback instantly
        if (this._activePromise !== promise) {
            this.commit(defaultContent);
            this._activePromise = promise;
            
            promise.then(resolvedValue => {
                // Prevent race conditions: only update if this promise is still the active one
                if (this._activePromise === promise) {
                    this.commit(resolvedValue);
                }
            }).catch(err => {
                if (this._activePromise === promise) {
                    console.error('[SPPUX] until() directive rejected:', err);
                }
            });
        }
    }

    _commitIterable(items) {
        this._clearNonArray();
        
        const newArrayItems = [];
        const max = Math.max(this._arrayItems.length, items.length);
        
        for (let i = 0; i < max; i++) {
            if (i < items.length) {
                let part;
                if (i < this._arrayItems.length) {
                    part = this._arrayItems[i];
                } else {
                    const start = document.createComment('');
                    const end = document.createComment('');
                    this.startNode.parentNode.insertBefore(start, this.endNode);
                    this.startNode.parentNode.insertBefore(end, this.endNode);
                    part = new NodePart(start, end);
                }
                part.commit(items[i]);
                newArrayItems.push(part);
            } else {
                const part = this._arrayItems[i];
                part._clear();
                part.startNode.parentNode.removeChild(part.startNode);
                part.endNode.parentNode.removeChild(part.endNode);
            }
        }
        
        this._arrayItems = newArrayItems;
    }

    _commitPortal(directive) {
        this._clearNonArray();
        const { templateResult, targetElement } = directive;
        
        if (this._portalInstance && this._portalInstance.template === getTemplate(templateResult.strings) && this._portalTarget === targetElement) {
            this._portalInstance.update(templateResult.values);
        } else {
            this._clear();
            const template = getTemplate(templateResult.strings);
            this._portalInstance = new TemplateInstance(template);
            this._portalTarget = targetElement;
            this._portalInstance.update(templateResult.values);
            
            // Store a reference to the nodes so we can remove them later
            this._portalNodes = Array.from(this._portalInstance.fragment.childNodes);
            targetElement.appendChild(this._portalInstance.fragment);
        }
    }

    _clearNonArray() {
        if (this._instance || this._textNode || this._portalInstance) {
            this._clear();
            this._instance = null;
            this._textNode = null;
            this._portalInstance = null;
        }
    }

    _clear() {
        // Clear standard Light DOM nodes between comment boundaries
        let curr = this.startNode.nextSibling;
        while (curr && curr !== this.endNode) {
            const next = curr.nextSibling;
            if (curr.parentNode) curr.parentNode.removeChild(curr);
            curr = next;
        }
        
        // Clear detached Portal nodes if any
        if (this._portalNodes) {
            for (const node of this._portalNodes) {
                if (node.parentNode) node.parentNode.removeChild(node);
            }
            this._portalNodes = null;
            this._portalTarget = null;
        }
        
        this._arrayItems = [];
    }
}

export class AttributePart extends Part {
    constructor(element, name) {
        super();
        this.element = element;
        this.name = name;
    }
    commit(value) {
        if (this.name === 'ref' && value && value._isRef) {
            if (this.value !== value) {
                value.callback(this.element);
                this.value = value;
            }
            return;
        }
        
        if (this.name === 'bind' && value && value._isBind) {
            const signal = value.signal;
            const isCheckbox = this.element.type === 'checkbox' || this.element.type === 'radio';
            
            // 1. One-time setup: attach DOM event listener
            if (!this._boundSignal) {
                this._boundSignal = signal;
                const eventName = isCheckbox ? 'change' : 'input';
                
                this.element.addEventListener(eventName, (e) => {
                    signal.value = isCheckbox ? e.target.checked : e.target.value;
                });
            }
            
            // 2. State to DOM sync
            if (isCheckbox) {
                if (this.element.checked !== !!signal.value) {
                    this.element.checked = !!signal.value;
                }
            } else {
                const newStr = signal.value !== undefined && signal.value !== null ? String(signal.value) : '';
                if (this.element.value !== newStr) {
                    this.element.value = newStr;
                }
            }
            return;
        }
        
        if (value === this.value) return;
        if (value == null) {
            this.element.removeAttribute(this.name);
        } else {
            this.element.setAttribute(this.name, value);
        }
        this.value = value;
    }
}

export class BooleanPart extends Part {
    constructor(element, name) {
        super();
        this.element = element;
        this.name = name;
    }
    commit(value) {
        if (value === this.value) return;
        if (value) {
            this.element.setAttribute(this.name, '');
        } else {
            this.element.removeAttribute(this.name);
        }
        this.value = value;
    }
}

export class PropertyPart extends Part {
    constructor(element, name) {
        super();
        this.element = element;
        this.name = name;
    }
    commit(value) {
        if (value === this.value) return;
        if (this.name === 'value' && document.activeElement === this.element && this.element.value === value) {
            return;
        }
        this.element[this.name] = value;
        this.value = value;
    }
}

export class EventPart extends Part {
    constructor(element, eventName) {
        super();
        this.element = element;
        this.eventName = eventName;
    }
    commit(value) {
        if (value && value._isAction) {
            const apiFunction = value.apiFunction;
            if (this.value !== apiFunction) {
                if (this._actionHandler) {
                    removeHandler(this.element, this.eventName, this._actionHandler);
                }
                
                this._actionHandler = async (e) => {
                    e.preventDefault();
                    let data = {};
                    if (e.target instanceof HTMLFormElement) {
                        data = new FormData(e.target);
                    }
                    try {
                        await apiFunction(data);
                    } catch (err) {
                        console.error('[SPPUX] Action directive failed:', err);
                    }
                };
                
                registerHandler(this.element, this.eventName, this._actionHandler);
                this.value = apiFunction;
            }
            return;
        }
        
        if (value === this.value) return;
        if (this.value && !this._actionHandler) {
            removeHandler(this.element, this.eventName, this.value);
        } else if (this._actionHandler) {
            removeHandler(this.element, this.eventName, this._actionHandler);
            this._actionHandler = null;
        }
        
        if (typeof value === 'function') {
            registerHandler(this.element, this.eventName, value);
        }
        this.value = value;
    }
}
