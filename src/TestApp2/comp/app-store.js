/**
 * ============================================================================
 * App Store — Shared State Across Components
 * ============================================================================
 *
 * SPPStore provides a simple, reactive shared state container.
 * Multiple components can subscribe to the same store and react to changes.
 *
 * HOW TO USE IN A COMPONENT:
 *
 *   import AppStore from './app-store.js';
 *
 *   class MyComponent extends BaseComponent {
 *     onInit() {
 *       // Subscribe to store changes
 *       this.unsubscribe = this.bindStore(AppStore, (state) => {
 *         this.setState({ user: state.user });
 *       });
 *     }
 *
 *     onDestroy() {
 *       // Always unsubscribe to prevent memory leaks
 *       this.unsubscribe();
 *     }
 *
 *     login(username) {
 *       // Update store — all subscribers are notified
 *       AppStore.set({ user: { name: username }, loggedIn: true });
 *     }
 *   }
 *
 * METHODS:
 *   AppStore.get()            — Get current state snapshot
 *   AppStore.set({ ... })     — Merge partial state (notifies subscribers)
 *   AppStore.subscribe(fn)    — Listen for changes (returns unsubscribe fn)
 *   AppStore.notify()         — Force notify all subscribers
 * ============================================================================
 */
const AppStore = new SPPStore({
    user: null,
    loggedIn: false,
    notifications: [],
    preferences: {
        theme: 'midnight',
        language: 'en'
    }
});

export default AppStore;