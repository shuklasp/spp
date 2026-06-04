/**
 * SPPUX Framework Type Definitions
 * Provides IntelliSense and Autocomplete for zero-build vanilla JS SPPUX development.
 */

declare module SPPUX {
    export class TrustedHTML {
        content: string;
        __isTrusted: boolean;
        toString(): string;
        toJSON(): string;
    }

    /**
     * Creates a reactive TrustedHTML template.
     * Note: For syntax highlighting, install the `lit-html` VSCode extension.
     */
    export function html(strings: TemplateStringsArray, ...values: any[]): TrustedHTML;

    export class SPPStore {
        constructor(initialState?: Record<string, any>);
        get(): Record<string, any>;
        set(newState: Record<string, any>): void;
        subscribe(callback: (state: Record<string, any>) => void): () => void;
        notify(): void;
    }

    export class BaseComponent {
        constructor(app?: any, container?: HTMLElement, props?: Record<string, any>);
        
        /** Internal state object. Do not mutate directly. Use setState(). */
        state: Record<string, any>;
        
        /**
         * Updates the component state and automatically triggers a reactive UI re-render.
         * @param newState Partial state object to merge into the current state.
         */
        setState(newState: Record<string, any>): void;

        /** Called once during component bootstrap. Perfect for fetching initial data. */
        onInit(): Promise<void> | void;

        /** Called after the first render. */
        onMount(): Promise<void> | void;

        /** Called after every DOM reconciliation. */
        afterUpdate(): void;

        /** Called when the component is disposed. */
        onDestroy(): void;

        /**
         * Core render function. Must return an `html` template literal or a string.
         */
        render(): TrustedHTML | string;

        /** Displays a global notification toast. */
        notify(msg: string, type?: 'info' | 'success' | 'warning' | 'error' | 'danger'): void;

        /** Opens a modal dialog. */
        openModal(title: string, content: TrustedHTML | string | HTMLElement, actions?: any[]): void;

        /** Closes the active modal dialog. */
        closeModal(): void;

        /**
         * Sends a POST request to the SPP API.
         * @param action The API action name.
         * @param data Payload to send.
         */
        apiPost(action: string, data?: Record<string, any>, options?: { lock?: boolean }): Promise<any>;

        /**
         * Sends a GET/POST request to the SPP API.
         */
        api(action: string, data?: Record<string, any>, options?: { lock?: boolean }): Promise<any>;

        /** Shows a confirmation dialog. */
        confirm(message: string): Promise<boolean>;

        /** Shows an input prompt. */
        prompt(message: string, defaultValue?: string): Promise<string>;

        /** Wraps a promise, updating a loading state automatically. */
        task<T>(name: string, promise: Promise<T>): Promise<T>;
        
        /** Global SPP Application Object */
        app: any;
        admin: any;
    }

    export const Fragment: TrustedHTML;
}

declare function html(strings: TemplateStringsArray, ...values: any[]): SPPUX.TrustedHTML;
declare const Fragment: SPPUX.TrustedHTML;
declare class BaseComponent extends SPPUX.BaseComponent {}
