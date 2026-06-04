const fs = require("fs");
const path = require("path");
const net = require("net");

function loadModule(moduleName) {
    if (fs.existsSync(moduleName) || fs.existsSync(moduleName + ".js")) {
        return require(path.resolve(moduleName));
    }
    return require(moduleName);
}

async function main() {
    const moduleName = process.argv[2];
    
    if (process.argv.includes("--daemon")) {
        const portFile = process.argv[process.argv.indexOf("--daemon") + 1];
        let mod;
        try { mod = loadModule(moduleName); } catch(e) { console.error(e); process.exit(1); }
        
        const server = net.createServer((socket) => {
            let buffer = "";
            socket.on("data", async (data) => {
                buffer += data.toString();
                if (buffer.includes("\n")) {
                    try {
                        const req = JSON.parse(buffer.trim());
                        const func = mod[req.func];
                        const args = req.args || [];
                        let result = func(...(Array.isArray(args) ? args : [args]));
                        if (result instanceof Promise) result = await result;
                        socket.write(JSON.stringify(result) + "\n");
                    } catch (e) {
                        // ignore or error
                    }
                    socket.end();
                }
            });
        });
        
        server.listen(0, "127.0.0.1", () => {
            fs.writeFileSync(portFile, server.address().port.toString());
        });
    } else {
        const funcName = process.argv[3];
        const argsRaw = fs.readFileSync(0, "utf8");
        const args = argsRaw ? JSON.parse(argsRaw) : [];
        try {
            const mod = loadModule(moduleName);
            const func = mod[funcName];
            let result = func(...(Array.isArray(args) ? args : [args]));
            if (result instanceof Promise) result = await result;
            process.stdout.write(JSON.stringify(result));
        } catch (e) {
            process.stderr.write(e.stack || e.message);
            process.exit(1);
        }
    }
}
main();