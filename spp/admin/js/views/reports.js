export default class ReportsView {
    constructor(admin, container, params) {
        this.admin = admin;
        this.container = container;
        this.params = params;
        this.BuilderClass = null;
    }

    async onInit() {
        try {
            const moduleUrl = this.admin.config.baseUrl + '/spp/modules/spp/sppreport/js/sppreport-ui.js';
            const module = await import(moduleUrl);
            this.BuilderClass = module.default;
        } catch (e) {
            console.error("Failed to load ReportBuilder:", e);
        }
    }

    update() {
        this.container.innerHTML = '';
        if (this.BuilderClass) {
            const builder = new this.BuilderClass(this.admin, this.container, {
                apiEndpoint: this.admin.config.apiBase + '?action=report_api&modname=sppreport'
            });
            builder.onInit().then(() => builder.update());
        } else {
            this.container.innerHTML = `<div class="alert error">Failed to load the Report Builder module. Please ensure the sppreport module is enabled.</div>`;
        }
    }
}
