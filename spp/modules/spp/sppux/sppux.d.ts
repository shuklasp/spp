/**
 * SPP-UX Master Enterprise Type Definitions
 * Providing state-of-the-art IntelliSense for the native zero-dependency SPP-UX framework.
 */

declare interface Signal<T> {
    value: T;
}

declare interface SPPAdminFacade {
    api(action: string, data?: Record<string, any>): Promise<any>;
    apiPost(actionOrFormData: string | FormData, data?: Record<string, any>): Promise<any>;
    callAppService(name: string, params?: Record<string, any>): Promise<any>;
}

declare interface SPPUXFacade {
    signal<T>(value: T): Signal<T>;
    computed<T>(fn: () => T): Signal<T>;
    effect(fn: () => void): () => void;
    
    // UI Helpers Facade
    Notify: {
        show(message: string, type?: 'info' | 'success' | 'error', duration?: number): void;
    };
    Modal: {
        open(title: string, content: string | any, actions?: Array<{ label: string; type: string; fn: (m: any) => void }>): any;
        close(): void;
    };
    Theme: {
        set(name: 'midnight' | 'emerald' | 'royal' | 'cyberpunk' | 'ocean' | 'saffron' | 'day'): void;
        current: string;
    };
    Busy: {
        start(): void;
        stop(): void;
    };
    Confirm(message: string): Promise<boolean>;
    Prompt(message: string, defaultValue?: string): Promise<string | null>;
}

declare class TrustedHTML {
    toString(): string;
    toJSON(): string;
}

declare function html(strings: TemplateStringsArray, ...values: any[]): TrustedHTML;

declare class SPPStore<T = Record<string, any>> {
    constructor(initialState?: T);
    get(): T;
    set(newState: Partial<T>): void;
    subscribe(callback: (state: T) => void): () => void;
    notify(): void;
}

declare class BaseComponent<P = Record<string, any>, S = Record<string, any>> {
    constructor(admin: SPPAdminFacade | null, container: HTMLElement, props?: P);
    state: S;
    props: P;
    container: HTMLElement;
    app: SPPAdminFacade | null;
    api: Record<string, (data?: Record<string, any>, options?: { lock?: boolean }) => Promise<any>>;
    serv: Record<string, (params?: Record<string, any>) => Promise<any>>;
    
    onInit(): Promise<void>;
    onMount(): Promise<void>;
    onDestroy(): void;
    render(): TrustedHTML;
    afterUpdate(): void;
    
    update(): void;
    setState(newState: Partial<S>): void;
    bindStore(store: SPPStore, keyOrCallback: string | ((state: any) => void)): () => void;
    
    task<R>(name: string, promise: Promise<R>): Promise<R>;
    service<R>(name: string, params?: Record<string, any>): Promise<R>;
    dispose(): void;
    
    notify(msg: string, type?: 'info' | 'success' | 'error'): void;
    confirm(msg: string): Promise<boolean>;
    prompt(msg: string, defaultValue?: string): Promise<string | null>;
    renderLoading(message?: string): TrustedHTML;
}

declare class SPPForm extends BaseComponent {
    static autoInit(container: HTMLElement): SPPForm;
    refreshDependencies(): void;
}

declare var SPPUX: SPPUXFacade;
declare var spp_admin: SPPAdminFacade;
declare var TrustedHTML: typeof TrustedHTML;
declare var Fragment: TrustedHTML;
