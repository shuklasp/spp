/**
 * SPP-UX Type Definitions
 * Providing IntelliSense for the SPP-UX Legendary Framework.
 */

declare interface Signal<T> {
    value: T;
}

declare interface SPPUX {
    signal<T>(value: T): Signal<T>;
    computed<T>(fn: () => T): Signal<T>;
    effect(fn: () => void): () => void;
    
    // UI Helpers (Populated by sppux-ui.js)
    Notify: {
        show(message: string, type?: 'info' | 'success' | 'error', duration?: number): void;
    };
    Modal: {
        open(title: string, content: string | any, actions?: Array<{label: string, type: string, fn: (m: any) => void}>): any;
    };
    Theme: {
        set(name: 'midnight' | 'emerald' | 'royal' | 'cyberpunk' | 'ocean' | 'saffron'): void;
        current: string;
    };
}

declare class TrustedHTML {
    toString(): string;
}

declare function html(strings: TemplateStringsArray, ...values: any[]): TrustedHTML;

declare class SPPStore {
    constructor(initialState?: any);
    get(): any;
    set(newState: any): void;
    subscribe(callback: (state: any) => void): () => void;
}

declare class BaseComponent {
    constructor(admin: any, container: HTMLElement, props?: any);
    state: any;
    props: any;
    container: HTMLElement;
    
    onInit(): Promise<void>;
    onMount(): Promise<void>;
    onDestroy(): void;
    render(): TrustedHTML;
    
    update(): void;
    setState(newState: any): void;
    bindStore(store: SPPStore, keyOrCallback: string | ((state: any) => void)): () => void;
    
    task(name: string, promise: Promise<any>): Promise<any>;
    service(name: string, params?: any): Promise<any>;
    dispose(): void;
}

declare class SPPForm extends BaseComponent {
    static autoInit(container: HTMLElement): SPPForm;
    refreshDependencies(): void;
}

declare var SPPUX: SPPUX;
declare var spp_admin: any;
