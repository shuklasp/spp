# Polyglot Bridge Tutorial

Welcome to the SPP Polyglot Bridge! If you are a novice developer looking to integrate code written in other programming languages (like Python, Java, Go, or .NET) into your PHP application, you're in the right place. This tutorial will guide you step-by-step through configuring and using the new Polyglot Bridge strategies.

## Foundational Concepts

The SPP framework is built primarily in PHP, but modern applications often need to use libraries or scripts written in other languages (for example, a Python script for machine learning). The **Polyglot Bridge** solves this by providing a unified, secure way to execute non-PHP code and return the results directly into your PHP application.

Instead of one massive, confusing system, the Bridge uses a **Strategy Pattern**. This means there is a specific, optimized handler (a "Bridge") for each language:
- `JavaBridge` handles `.jar` files and Java execution.
- `GoBridge` handles compiled Go binaries.
- `DotNetBridge` handles C# / .NET execution.
- `DefaultBridge` handles generic scripts (like Python, Node.js, bash, etc.).

This modular design makes it extremely easy to add new languages without breaking existing ones!

## Lifecycle & Architecture

Here is how the Polyglot Bridge works behind the scenes when you request to run a script:

1. **Request:** Your application asks the `PolyglotBridgeFactory` to run a script for a specific language.
2. **Resolution:** The Factory checks if a specialized Bridge exists for that language (e.g., "java" -> `JavaBridge`). If not, it falls back to the `DefaultBridge`.
3. **Execution:** The selected Bridge prepares the environment, safely escapes any arguments you provided to prevent command injection, and executes the script.
4. **Response:** The Bridge captures the standard output (`stdout`) and standard error (`stderr`) and returns it to your application.

## Step-by-Step Tutorial

Let's write a simple Python script and execute it from within an SPP controller.

### Step 1: Create Your Non-PHP Script

Create a Python script named `hello.py` in your app's `resources/scripts` directory:

```python
# resources/scripts/hello.py
import sys

# Get the name from the arguments, default to "World"
name = sys.argv[1] if len(sys.argv) > 1 else "World"
print(f"Hello, {name} from Python!")
```

### Step 2: Use the PolyglotBridgeFactory in PHP

In your SPP Controller or Service, you can use the factory to execute this script safely.

```php
<?php

namespace App\Controllers;

use SPP\Core\Polyglot\PolyglotBridgeFactory;
use SPP\Core\ResourceController;

class PolyglotDemoController extends ResourceController
{
    public function runPythonAction()
    {
        // 1. Get the correct bridge for Python
        // Since there is no specific PythonBridge yet, this will return the DefaultBridge
        $bridge = PolyglotBridgeFactory::getBridge('python');

        // 2. Define the path to your script and the arguments
        $scriptPath = $this->app->getAppDir() . '/resources/scripts/hello.py';
        $arguments = ['SPP Developer'];

        // 3. Execute the script!
        try {
            $output = $bridge->execute($scriptPath, $arguments);
            return $this->respond(['message' => trim($output)]);
        } catch (\Exception $e) {
            return $this->respondError("Script failed: " . $e->getMessage());
        }
    }
}
```

### Step 3: Run and Test

When a user visits the route pointing to `runPythonAction`, the output will be:

```json
{
  "message": "Hello, SPP Developer from Python!"
}
```

That's it! You have successfully bridged PHP with Python. The framework handles all the underlying complexity, allowing you to focus on building great features.
