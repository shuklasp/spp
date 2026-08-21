

async function run() {
    const res = await fetch('http://localhost/school1/sppadmin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'login',
            payload: { username: 'admin', password: 'hello' }
        })
    });
    const text = await res.text();
    console.log(text);
}
run();
