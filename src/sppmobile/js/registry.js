/**
 * SPP Mobile Studio - Dynamic Component Registry
 * 
 * Defines the schemas, icons, and default properties for all UI atoms.
 */
export default class ComponentRegistry {
    static _plugins = [];

    static register(plugin) {
        if (!plugin || !plugin.type) return;
        // Avoid duplicate registration
        if (ComponentRegistry._plugins.some(p => p.type === plugin.type)) return;
        ComponentRegistry._plugins.push(plugin);
        console.log(`[ComponentRegistry] Registered Plugin: ${plugin.name || plugin.type}`);
    }

    static getPlugins() {
        return ComponentRegistry._plugins;
    }

    static getDefinitions() {
        const base = [
            {
                group: 'Layout & Structure',
                items: [
                    { name: 'Column', type: 'column', icon: '↕️', schema: { gap: 'number', padding: 'number' } },
                    { name: 'Row', type: 'row', icon: '↔️', schema: { gap: 'number', padding: 'number' } },
                    { name: 'Container', type: 'container', icon: '📦', schema: { padding: 'number', backgroundColor: 'color', borderRadius: 'number' } },
                    { name: 'Card', type: 'card', icon: '🃏', schema: { elevation: 'number', borderRadius: 'number', padding: 'number' } },
                    { name: 'List', type: 'list', icon: '📜', schema: { padding: 'number' } },
                    { name: 'Grid', type: 'grid_view', icon: '🏁', schema: { cols: 'number', gap: 'number' } }
                ]
            },
            {
                group: 'Navigation',
                items: [
                    { name: 'App Bar', type: 'app_bar', icon: '🔝', schema: { text: 'string', elevation: 'number', backgroundColor: 'color' } },
                    { name: 'Tab Bar', type: 'tab_bar', icon: '📑', schema: { activeIndex: 'number' } },
                    { name: 'Drawer', type: 'drawer', icon: '📋', schema: { title: 'string' } },
                    { name: 'Nav Rail', type: 'nav_rail', icon: '🛤️', schema: { expanded: 'boolean' } }
                ]
            },
            {
                group: 'Atomic Widgets',
                items: [
                    { name: 'Text', type: 'text', icon: '📝', schema: { text: 'string', fontSize: 'number', fontWeight: 'string', color: 'color' } },
                    { name: 'Button', type: 'button', icon: '🔘', schema: { text: 'string', variant: 'string', borderRadius: 'number' } },
                    { name: 'Image', type: 'image', icon: '🖼️', schema: { src: 'string', borderRadius: 'number', objectFit: 'string' } },
                    { name: 'Icon', type: 'icon', icon: '✨', schema: { icon: 'string', size: 'number', color: 'color' } },
                    { name: 'Floating Action', type: 'fab', icon: '➕', schema: { icon: 'string', backgroundColor: 'color' } }
                ]
            },
            {
                group: 'Interactive Forms',
                items: [
                    { name: 'Text Field', type: 'input', icon: '⌨️', schema: { text: 'string', placeholder: 'string', type: 'string' } },
                    { name: 'Switch', type: 'switch', icon: '🔘', schema: { text: 'string', value: 'boolean' } },
                    { name: 'Slider', type: 'slider', icon: '📏', schema: { min: 'number', max: 'number', value: 'number' } },
                    { name: 'Checkbox', type: 'checkbox', icon: '☑️', schema: { text: 'string', value: 'boolean' } }
                ]
            },
            {
                group: 'Social & Media',
                items: [
                    { name: 'Story Circle', type: 'story_circle', icon: '⭕', schema: { src: 'string', size: 'number', viewed: 'boolean' } },
                    { name: 'Video Player', type: 'video_player', icon: '🎬', schema: { src: 'string', autoPlay: 'boolean' } },
                    { name: 'Lottie Animation', type: 'lottie', icon: '🌀', schema: { src: 'string', loop: 'boolean', autoPlay: 'boolean' } },
                    { name: 'Rive Asset', type: 'rive', icon: '🛸', schema: { src: 'string', stateMachine: 'string' } },
                    { name: 'Audio Node', type: 'audio_player', icon: '📻', schema: { src: 'string' } },
                    { name: 'Post Card', type: 'social_post', icon: '📱', schema: { author: 'string', content: 'string', image: 'string' } }
                ]
            },
            {
                group: 'Commerce',
                items: [
                    { name: 'Product Card', type: 'product_card', icon: '🛍️', schema: { name: 'string', price: 'number', image: 'string' } },
                    { name: 'Price Tag', type: 'price_tag', icon: '🏷️', schema: { value: 'string', currency: 'string' } },
                    { name: 'Checkout Btn', type: 'checkout_btn', icon: '💳', schema: { text: 'string' } }
                ]
            }
        ];

        // Merge dynamically registered plugins into a 'Custom Plugins' group
        if (ComponentRegistry._plugins.length > 0) {
            base.push({
                group: 'Custom Plugins',
                items: ComponentRegistry._plugins
            });
        }

        return base;
    }

    static getDefaultProps(type) {
        const defs = ComponentRegistry.getDefinitions();
        for (const group of defs) {
            const item = group.items.find(i => i.type === type);
            if (item) {
                const props = {};
                for (const [key, valType] of Object.entries(item.schema || {})) {
                    if (valType === 'number') props[key] = 0;
                    else if (valType === 'string') props[key] = '';
                    else if (valType === 'boolean') props[key] = false;
                    else if (valType === 'color') props[key] = '#6366f1';
                }
                // Overrides for specific types
                if (type === 'text') props.text = 'New Text Label';
                if (type === 'button') props.text = 'Click Me';
                if (type === 'app_bar') props.text = 'App Title';
                return props;
            }
        }
        return {};
    }
}
