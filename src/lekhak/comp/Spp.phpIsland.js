export default class Spp.phpIsland extends BaseComponent {
    async onInit() {
        this.setState({ count: 0 });
    }

    render() {
        return html`
            <div style="background: #ebf8ff; padding: 1rem; border-radius: 8px; border: 1px solid #bee3f8; text-align: center;">
                <h3>Rendered from SPPUX</h3>
                <p>This is a purely client-side reactive island.</p>
                <button 
                    style="background: #3182ce; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold;"
                    @click="${() => this.setState({ count: this.state.count + 1 })}">
                    Clicked ${this.state.count} times
                </button>
            </div>
        `;
    }
}