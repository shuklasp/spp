const args = process.argv.slice(2);

if (args.length === 0) {
    console.log(JSON.stringify({ error: "No arguments provided" }));
    process.exit(1);
}

try {
    const data = JSON.parse(args[0]);
    const name = data.name || 'Unknown';
    
    const response = {
        status: "success",
        lang: "Node.js",
        greeting: `Hello ${name} from Node.js!`,
        received_data: data
    };
    
    console.log(JSON.stringify(response));
} catch (e) {
    console.log(JSON.stringify({ error: e.message }));
}
