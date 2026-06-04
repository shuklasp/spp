const readline = require('readline');

class SPPService {
    static listen(serviceName, callback) {
        const rl = readline.createInterface({
            input: process.stdin,
            output: process.stdout,
            terminal: false
        });

        rl.on('line', async (line) => {
            try {
                const payload = JSON.parse(line);
                try {
                    const result = await callback(payload);
                    SPPService._respond({ status: "success", data: result });
                } catch (err) {
                    SPPService._respond({ 
                        status: "error", 
                        error: err.message, 
                        trace: err.stack 
                    });
                }
            } catch (err) {
                SPPService._respond({ error: "Invalid JSON payload" });
            }
        });
    }

    static _respond(data) {
        process.stdout.write(JSON.stringify(data) + "\n");
    }
}

module.exports = SPPService;
