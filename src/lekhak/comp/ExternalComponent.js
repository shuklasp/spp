/**
 * Component: ExternalComponent
 * Generated via SPP CLI
 */
export default class ExternalComponent extends BaseComponent {
    async onInit() {
        // Fetch external HTML template if not already in DOM
        const tplId = `spp-tpl-${this.constructor.name.toLowerCase()}`;
        if (!document.getElementById(tplId)) {
            try {
                // Fetch template relative to the current script or base URL
                const res = await fetch(`${this.app.config.baseUrl}/src/lekhak/comp/externalcomponent.html`);
                const htmlText = await res.text();
                const div = document.createElement('template');
                div.id = tplId;
                div.innerHTML = htmlText;
                document.body.appendChild(div);
            } catch (e) {
                console.error("Failed to load external template for ExternalComponent", e);
            }
        }

        // Initialize Local State (ALWAYS use this.setState to mutate)
        this.setState({
            loading: false,
            message: 'External Template Loaded!'
        });
    }

    render() {
        // BaseComponent automatically looks for <template id="spp-tpl-classname">
        // if render() returns an empty Fragment.
        return Fragment; 
    }
}