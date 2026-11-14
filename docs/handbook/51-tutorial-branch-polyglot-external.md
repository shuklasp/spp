# Tutorial Branch — Multi-Language Applications, Guest Daemons & Sovereign Service Mesh

**Evidence & Source Fixtures:** `etc/apps.yml`, `spp/core/apps/ForeignAppRegistry.php`, `spp/core/apps/SubpathVirtualizer.php`, `spp/core/apps/SubpathGateway.php`, `spp/core/apps/AppSupervisor.php`, `spp/core/apps/Mesh.php`, `spp/lib/mesh/python/spp_mesh.py`, `spp/dev/js/views/apps.js`, and `tests/core/test.multi_language_app_mesh.php`.

This tutorial branch walks a developer who is brand new to SPP through building, hosting, supervising, and integrating a multi-language microservice (Python FastAPI) alongside a native SPP application, accessible inside a route directory (`/ai`) and fully participating in the SPP Service Mesh.

---

## 51.1 The Scenario

You are building an Educational ERP in SPP (`src/school1/`). You want to add an AI Prediction Engine written in Python using FastAPI.

**Requirements**:
1. Users visit `https://school.local/ai/` and interact with the Python web UI natively, with zero broken assets or redirect loops.
2. The Python app automatically senses it is running inside SPP and receives the logged-in user's identity without requiring an extra login screen (Single Sign-On).
3. Native SPP PHP controllers can invoke the Python AI service directly via `\SPP\Mesh::call()`.
4. Developers can manage, inspect, and test the microservice directly from `/sppdev#apps`.

---

## 51.2 Lab 1: Declaring the Application in `etc/apps.yml`

In the root configuration directory, open `etc/apps.yml` and declare the new application:

```yaml
version: '2.0'

apps:
  # Native Base SPP Application
  school:
    type: spp
    name: "School ERP"
    route: /
    enabled: true

  # Multi-Language Python Microservice
  ai_engine:
    type: python
    name: "AI Analytics Microservice"
    route: /ai
    subdomain: ai.school.local
    root_dir: apps/ai_engine
    command: "python -m uvicorn main:app --host 127.0.0.1 --port {PORT}"
    health_check: /health
    enabled: true
    spp_integration:
      ambient_sensing: true
      service_mesh: true
      expose_services:
        - name: ai.predict
          endpoint: /api/predict
          method: POST
          description: "Predict student performance and dropout risk"
```

Notice the `{PORT}` placeholder: SPP's `AppSupervisor` will automatically scan for a free loopback port between `10000` and `20000` and pass it to your command dynamically!

---

## 51.3 Lab 2: Creating the Python FastAPI Microservice

Create the application directory:
```bash
mkdir -p apps/ai_engine
```

Copy the zero-dependency Python SDK from SPP:
```bash
cp spp/lib/mesh/python/spp_mesh.py apps/ai_engine/spp_mesh.py
```

Create `apps/ai_engine/main.py`:

```python
from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse
from spp_mesh import SPPMesh

app = FastAPI()
mesh = SPPMesh()

# 1. Health check endpoint (checked by AppSupervisor on boot)
@app.get("/health")
def health():
    return {"status": "ok", "app": mesh.app_id}

# 2. Web UI with relative assets
@app.get("/", response_class=HTMLResponse)
def index():
    return """
    <!DOCTYPE html>
    <html>
    <head>
        <title>AI Analytics Portal</title>
        <!-- Notice relative link: SPP injects <base href="/ai/"> automatically -->
        <link rel="stylesheet" href="style.css">
    </head>
    <body style="font-family: sans-serif; padding: 2rem; background: #0d1117; color: #c9d1d9;">
        <h1>🤖 AI Student Performance Engine</h1>
        <p>Running natively under SPP subpath route <code>/ai</code>!</p>
        <a href="dashboard" style="color: #58a6ff;">Go to Dashboard</a>
    </body>
    </html>
    """

# 3. Ambient Single Sign-On (SSO) Endpoint
@app.get("/dashboard")
def dashboard(request: Request):
    user = mesh.get_auth_user(dict(request.headers))
    if not user:
        return {"error": "Unauthorized. Please log in via SPP."}, 401
    
    return {
        "status": "authenticated",
        "user_id": user["id"],
        "email": user["email"],
        "role": user.get("claims", {}).get("role", "student"),
        "message": f"Welcome back, {user['email']}!"
    }

# 4. Service Mesh RPC Endpoint
@app.post("/api/predict")
async def predict(data: dict):
    student_id = data.get("student_id", 0)
    scores = data.get("scores", [80, 85, 90])
    avg_score = sum(scores) / len(scores)
    predicted_grade = "A" if avg_score >= 85 else "B"

    # Publish an event to the SPP Event Bus
    mesh.publish_event("ai.prediction_completed", {
        "student_id": student_id,
        "predicted_grade": predicted_grade
    })

    return {
        "student_id": student_id,
        "predicted_grade": predicted_grade,
        "confidence": 0.94
    }
```

---

## 51.4 Lab 3: Booting and Supervising the Application

You can launch and monitor your microservice using the SPP CLI:

```bash
# Start the AI daemon
php spp.php app:start ai_engine
```

Output:
```text
[OK] Application 'ai_engine' started successfully.
  PID: 14208
  Port: 10042
  Health: Healthy (HTTP 200)
```

Inspect the operational state across the server:
```bash
php spp.php app:status
```

```text
===================================================================================
                    SPP APPLICATION & DAEMON SUPERVISOR DASHBOARD                 
===================================================================================
APP ID         TYPE       ROUTE        SUBDOMAIN      PID      PORT    STATUS  
-----------------------------------------------------------------------------------
school         spp        /            -              N/A      N/A     Active
ai_engine      python     /ai          ai             14208    10042   Active
===================================================================================
```

Inspect live stdout and stderr output:
```bash
php spp.php app:logs ai_engine --tail=20
```

---

## 51.5 Lab 4: Ingress Virtualization in the Browser

Open your browser and navigate to:
```text
http://localhost/ai/
```

### What Happens Under the Hood:
1. `public/index.php` delegates to `ForeignAppRegistry`.
2. The router detects `/ai/` matches the `ai_engine` route prefix.
3. `SubpathGateway` proxies the request to `http://127.0.0.1:10042/`.
4. `SubpathVirtualizer` intercepts the HTML response and injects `<base href="/ai/">`.
5. The relative link `<a href="dashboard">` automatically resolves to `http://localhost/ai/dashboard`.
6. If the Python app returns an HTTP redirect `Location: /login`, `SubpathVirtualizer` rewrites it to `/ai/login`. The user is never kicked out of the subpath!

---

## 51.6 Lab 5: Invoking from Native SPP PHP Controllers

In your native SPP application (e.g. `src/school1/controllers/StudentController.php`), you can invoke the Python AI microservice using the fluent `\SPP\Mesh` API:

```php
<?php
namespace App\Controllers;

use SPPMod\SPPView\ViewController;
use SPP\Mesh;

class StudentController extends ViewController
{
    public function evaluatePerformance(int $studentId): void
    {
        // 1. Invoke Python Microservice via the Mesh
        $result = Mesh::call('ai_engine', '/api/predict', [
            'student_id' => $studentId,
            'scores'     => [88, 92, 95]
        ]);

        // 2. Broadcast domain event into the SPP Event Bus
        Mesh::publishEvent('student.evaluated', [
            'student_id' => $studentId,
            'grade'      => $result['predicted_grade']
        ]);

        // 3. Render external partial view without inline HTML literals
        $this->renderPartial('partials/evaluation_result.html', [
            'student_id'      => $studentId,
            'predicted_grade' => $result['predicted_grade'],
            'confidence'      => $result['confidence']
        ]);
    }
}
```

---

## 51.7 Lab 6: Using the Unified Developer Studio (`/sppdev#apps`)

Instead of switching between terminal windows and browser tabs, open `/sppdev#apps` in your browser:

1. **`📱 Applications` Tab**:
   - Both your native SPP apps and the `ai_engine` Python microservice appear in a single unified catalog.
   - Click `▶️ Start`, `⏹️ Stop`, or `🔄 Restart` to control the daemon.
   - Click `📜 Logs` to inspect live terminal logs in a dark-mode modal.
   - Click `📁 /ai ↗` to open the microservice in your browser.

2. **`🕸️ Service Mesh & Daemons` Tab (`#apps?tab=mesh` or `#mesh`)**:
   - **RPC Sandbox**: Select `ai_engine`, click the discovered endpoint `POST /api/predict`, enter JSON `{"student_id": 101}`, and click `🚀 Invoke Mesh Service`.
   - Inspect the live JSON response and latency (e.g. `12.4 ms`) instantly.
   - **Event Bus Inspector**: Dispatch synthetic events to test cross-language event listeners in real time.

---

## 51.8 Lab 7: Running Parikshak Automated Tests

Run the native SPP test suite to verify route resolution, header injection, supervisor lifecycle, and mesh calls:

```bash
php spp.php test tests/core/test.multi_language_app_mesh.php
```

```text
Running tests for [school1]...
✔ PASS Tests\Core\MultiLanguageAppMeshParikshakTest::testRoutePrefixAndSubdomainResolution
✔ PASS Tests\Core\MultiLanguageAppMeshParikshakTest::testSubpathVirtualizerIngressHeadersAndSsoSigning
✔ PASS Tests\Core\MultiLanguageAppMeshParikshakTest::testSubpathResponseRewriting
✔ PASS Tests\Core\MultiLanguageAppMeshParikshakTest::testAppSupervisorPortAllocationAndRuntimeState
✔ PASS Tests\Core\MultiLanguageAppMeshParikshakTest::testServiceMeshDiscoveryAndEventBus
✔ PASS Tests\Core\MultiLanguageAppMeshParikshakTest::testAmbientPhpMeshClient
✔ PASS Tests\Core\MultiLanguageAppMeshParikshakTest::testServerConfiguratorGeneratesSubpathRules
✔ PASS Tests\Core\MultiLanguageAppMeshParikshakTest::testCommandBridgeAndDeveloperAdminMeshIntegration

Test Run Summary:
Total: 8, Passed: 8, Failed: 0 (100% Pass Rate)
```
