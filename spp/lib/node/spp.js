const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

class SPP {
    constructor() {
        this._config = null;
        this._sppCliPath = null;
        this._db = null;
    }

    init(configPath = null) {
        const baseDir = path.resolve(__dirname, '..', '..', '..', '..');
        this._sppCliPath = path.join(baseDir, 'spp.php');

        if (!configPath) {
            configPath = path.join(baseDir, 'var', 'shared', 'bridge_config.json');
            if (!fs.existsSync(configPath)) {
                // Fallback check
                configPath = path.join(baseDir, '..', 'var', 'shared', 'bridge_config.json');
            }
        }

        if (fs.existsSync(configPath)) {
            const raw = fs.readFileSync(configPath, 'utf8');
            this._config = JSON.parse(raw);
        } else {
            throw new Error(`SPP Bridge configuration not found at ${configPath}`);
        }
    }

    /**
     * Get database connection configuration
     */
    db() {
        if (!this._config) {
            this.init();
        }
        
        // Return db config, leaving native mysql connection up to the consumer 
        // using packages like 'mysql2' since Node doesn't bundle a native mysql connector
        return this._config.database || {};
    }

    getConfig(key, section = 'bridge_settings') {
        if (!this._config) {
            this.init();
        }
        return (this._config[section] || {})[key];
    }

    callPhp(className, methodName, args = []) {
        if (!this._config) {
            this.init();
        }

        const argsJson = JSON.stringify(args);
        
        try {
            const outputRaw = execSync(`php "${this._sppCliPath}" bridge:call "${className}" "${methodName}" '${argsJson}'`, {
                encoding: 'utf8',
                stdio: ['pipe', 'pipe', 'pipe']
            });

            // Extract JSON from output
            const jsonStart = outputRaw.indexOf('{');
            if (jsonStart !== -1) {
                const parsed = JSON.parse(outputRaw.substring(jsonStart));
                if (parsed.success) {
                    return parsed.data;
                } else {
                    throw new Error(`PHP Bridge Error: ${parsed.error}`);
                }
            } else {
                throw new Error(`Invalid response from PHP bridge: ${outputRaw}`);
            }
        } catch (e) {
            if (e.stderr) {
                throw new Error(`PHP CLI Execution failed: ${e.stderr.toString()}`);
            }
            throw e;
        }
    }
}

// Singleton instance
const spp = new SPP();

// Auto-init
try {
    spp.init();
} catch (e) {
    // Ignore auto-init errors
}

module.exports = spp;
