const fs = require("fs");
const path = require("path");

async function main() {
    try {
        const moduleName = process.argv[2];
        const funcName = process.argv[3];
        const argsRaw = fs.readFileSync(0, "utf8");
        const args = argsRaw ? JSON.parse(argsRaw) : [];

        // Check if module is relative or global
        let module;
        if (fs.existsSync(moduleName) || fs.existsSync(moduleName + ".js")) {
            module = require(path.resolve(moduleName));
        } else {
            module = require(moduleName);
        }

        const func = module[funcName];
        if (typeof func !== "function") throw new Error(`Function ${funcName} not found in module ${moduleName}`);

        let result = func(...(Array.isArray(args) ? args : [args]));
        if (result instanceof Promise) result = await result;

        process.stdout.write(JSON.stringify(result));
    } catch (e) {
        process.stderr.write(e.stack || e.message);
        process.exit(1);
    }
}

main();