// daemon_service.js
console.log("Simulating heavy Node.js startup (takes 2 seconds)...");

let loaded = false;

// Simulate async heavy load
setTimeout(() => {
    loaded = true;
    console.log("Node.js heavy model loaded!");
}, 2000);

// We need a synchronous sleep just to simulate the module load blocking,
// or we just return an error if called before loaded.
// Since PolyglotBridge's ephemeral mode waits for the command to finish, 
// let's do a synchronous sleep so it's a true 1:1 comparison.
const start = Date.now();
while (Date.now() - start < 2000) {
    // Synchronous block to simulate heavy require() e.g. tfjs
}
console.log("Node.js module initialized.");

module.exports = {
    generate: function(prompt) {
        return `Node.js AI says: Hello! You asked: ${prompt}`;
    }
};
