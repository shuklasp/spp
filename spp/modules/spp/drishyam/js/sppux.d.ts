/**
 * SPP-UX Type Definitions
 * Provides type-safety for zero-build SPP-UX development via JSDoc.
 */

export class Signal<T> {
    value: T;
    constructor(initial: T);
}

export class Computed<T> {
    value: T;
    constructor(fn: () => T);
}

export class SPPStore {
    [key: string]: any;
}

export class TrustedHTML {
    __html: string;
}

/**
 * Parses tagged template literals into TrustedHTML for the SPP-UX Reconciler.
 * Compatible with lit-analyzer for IDE type-checking.
 */
export function html(strings: TemplateStringsArray, ...values: any[]): TrustedHTML;

/**
 * Creates a deeply reactive proxy store that tracks property access.
 */
export function createStore<T extends object>(initial: T, options?: { syncKey?: string }): T;

export function effect(fn: () => void): void;
export function batch(fn: () => void): void;

/**
 * Registers a standard class as a native Custom Web Component.
 */
export function defineElement(tagName: string, ComponentClass: any, options?: { useShadow?: boolean }): void;

// Directives
export function repeat<T>(items: T[], keyFn: (item: T) => any, templateFn: (item: T, index: number) => TrustedHTML): TrustedHTML;
export function until(promise: Promise<any>, fallback: TrustedHTML): TrustedHTML;
export function portal(template: TrustedHTML, target: HTMLElement): TrustedHTML;
export function ref(callback: (el: HTMLElement) => void): string;
export function bind(store: object, key: string): string;
export function action(apiFn: (formData: FormData, e: Event) => Promise<any>): string;

export function bootSPPLive(): void;
