const fs = require("fs");
const path = require("path");
const net = require("net");

function loadModule(moduleName) {
    if (fs.existsSync(moduleName) || fs.existsSync(moduleName + ".js")) {
        return require(path.resolve(moduleName));
    }
    return require(moduleName);
}

function atomicWrite(targetPath, content) {
    const tmpPath = targetPath + ".tmp." + process.pid;
    fs.writeFileSync(tmpPath, content, "utf8");
    try {
        if (fs.existsSync(targetPath)) {
            fs.unlinkSync(targetPath);
        }
        fs.renameSync(tmpPath, targetPath);
    } catch (e) {
        // Fallback for Windows cross-process locks
        try {
            fs.writeFileSync(targetPath, content, "utf8");
            fs.unlinkSync(tmpPath);
        } catch (_) {}
    }
}

async function main() {
    if (process.argv.length < 3) {
        process.stderr.write("Usage: node dispatch.js <module_name> [<func_name> | --daemon <port_file>] [--auth-token <token>] [--pid-file <pid_file>]\n");
        process.exit(1);
    }

    const moduleName = process.argv[2];

    if (process.argv.includes("--daemon")) {
        const portFile = process.argv[process.argv.indexOf("--daemon") + 1];
        let authToken = "";
        if (process.argv.includes("--auth-token")) {
            authToken = process.argv[process.argv.indexOf("--auth-token") + 1];
        }
        let pidFile = "";
        if (process.argv.includes("--pid-file")) {
            pidFile = process.argv[process.argv.indexOf("--pid-file") + 1];
        }

        let mod;
        try {
            mod = loadModule(moduleName);
        } catch (e) {
            process.stderr.write("Failed to load module: " + (e.stack || e.message) + "\n");
            process.exit(1);
        }

        const server = net.createServer((socket) => {
            socket.setTimeout(15000);
            let buffer = "";
            const maxBytes = 10 * 1024 * 1024; // 10MB

            socket.on("data", async (data) => {
                buffer += data.toString();
                if (buffer.length > maxBytes) {
                    socket.write(JSON.stringify({
                        success: false,
                        error: "Payload limit exceeded (max 10MB)",
                        error_code: 413
                    }) + "\n");
                    socket.end();
                    return;
                }

                if (buffer.includes("\n")) {
                    let reqId = "";
                    try {
                        const raw = buffer.trim();
                        const req = JSON.parse(raw);
                        reqId = req.request_id || "";

                        // 1. Authenticate Request
                        if (authToken && req.auth_token !== authToken) {
                            socket.write(JSON.stringify({
                                success: false,
                                error: "Unauthorized: invalid or missing auth_token",
                                error_code: 401,
                                request_id: reqId
                            }) + "\n");
                            socket.end();
                            return;
                        }

                        // 2. Control Commands (ping, shutdown)
                        const funcName = req.func || req.action || "";
                        if (funcName === "__ping__" || funcName === "ping") {
                            socket.write(JSON.stringify({
                                success: true,
                                data: "PONG",
                                status: "ready",
                                pid: process.pid,
                                request_id: reqId
                            }) + "\n");
                            socket.end();
                            return;
                        }

                        if (funcName === "__shutdown__" || funcName === "shutdown") {
                            socket.write(JSON.stringify({
                                success: true,
                                message: "Worker shutting down",
                                request_id: reqId
                            }) + "\n");
                            socket.end();
                            server.close(() => process.exit(0));
                            return;
                        }

                        // 3. Validate Function Name
                        if (!/^[a-zA-Z0-9_]+$/.test(funcName)) {
                            socket.write(JSON.stringify({
                                success: false,
                                error: `Invalid function identifier '${funcName}'`,
                                error_code: 400,
                                request_id: reqId
                            }) + "\n");
                            socket.end();
                            return;
                        }

                        const func = mod[funcName];
                        if (typeof func !== "function") {
                            socket.write(JSON.stringify({
                                success: false,
                                error: `Function '${funcName}' not found in module '${moduleName}'`,
                                error_code: 404,
                                request_id: reqId
                            }) + "\n");
                            socket.end();
                            return;
                        }

                        const args = req.args || [];
                        let result = Array.isArray(args) ? func(...args) : func(args);
                        if (result instanceof Promise) {
                            result = await result;
                        }

                        socket.write(JSON.stringify({
                            success: true,
                            data: result,
                            request_id: reqId
                        }) + "\n");
                    } catch (e) {
                        socket.write(JSON.stringify({
                            success: false,
                            error: e.message || String(e),
                            error_type: e.name || "Error",
                            request_id: reqId
                        }) + "\n");
                    } finally {
                        socket.end();
                    }
                }
            });

            socket.on("timeout", () => {
                socket.end();
            });
        });

        server.listen(0, "127.0.0.1", () => {
            const port = server.address().port.toString();
            atomicWrite(portFile, port);
            if (pidFile) {
                atomicWrite(pidFile, process.pid.toString());
            }
        });
    } else {
        // Ephemeral subprocess mode
        const funcName = process.argv[3];
        const argsRaw = fs.readFileSync(0, "utf8");
        const args = argsRaw ? JSON.parse(argsRaw) : [];
        try {
            const mod = loadModule(moduleName);
            const func = mod[funcName];
            if (typeof func !== "function") {
                throw new Error(`Function '${funcName}' not found in module '${moduleName}'`);
            }
            let result = Array.isArray(args) ? func(...args) : func(args);
            if (result instanceof Promise) {
                result = await result;
            }
            process.stdout.write(JSON.stringify(result));
        } catch (e) {
            process.stderr.write(e.stack || e.message);
            process.exit(1);
        }
    }
}

main();
