# Tutorial: Modern SPP Application Development

Welcome to the Modern SPP Development Tutorial! In this guide, we'll build a simple "Task Manager" application to demonstrate the latest features of the SPP Framework: **Attribute-Based Routing**, **LiveComponents (Reactivity)**, and **JSX-Like PHP Components**.

---

## Step 1: Attribute-Based Routing
Forget manually editing `pages.yml`. Let's create a controller and define its route directly in the code using PHP 8 attributes.

Create a file `src/app/Controllers/TaskController.php`:

```php
namespace App\Controllers;

use SPP\SPPObject;
use SPP\Core\Attributes\Route;

class TaskController extends SPPObject
{
    // The #[Route] attribute automatically registers this endpoint!
    #[Route(path: '/tasks', method: 'GET')]
    public function index()
    {
        // Serve a template that will hold our LiveComponent
        return \SPPMod\SPPView\ViewPage::renderFile('pages/tasks.php', [
            'title' => 'My Task Manager'
        ]);
    }
}
```

*Behind the scenes:* During boot, SPP automatically scans your controllers, discovers `#[Route]`, and caches it into `routes.cache.php` for O(1) lightning-fast resolution.

---

## Step 2: Creating a LiveComponent
We want our task manager to be interactive (adding tasks) without writing custom Javascript or reloading the page. We will use `LiveComponent`.

Create `src/app/Components/TaskList.php`:

```php
namespace App\Components;

use SPPMod\SPPView\LiveComponent;

class TaskList extends LiveComponent
{
    // Public properties are automatically tracked as Reactive State!
    public array $tasks = ['Buy groceries', 'Walk the dog'];
    public string $newTask = '';

    // A reactive action triggered from the frontend
    public function addTask()
    {
        if (trim($this->newTask) !== '') {
            $this->tasks[] = $this->newTask;
            $this->newTask = ''; // Reset input field
        }
    }

    public function render(): string
    {
        // This is pure PHP, but it behaves like React!
        // Notice the 'wire:model' and 'wire:click' attributes.
        $html = '<div class="task-container">';
        $html .= '<h3>Task List</h3>';
        
        $html .= '<ul>';
        foreach ($this->tasks as $task) {
            $html .= '<li>' . htmlspecialchars($task) . '</li>';
        }
        $html .= '</ul>';

        // Two-way data binding to $this->newTask
        $html .= '<input type="text" wire:model="newTask" placeholder="Enter a task...">';
        
        // Trigger the addTask() PHP method on click without page reload
        $html .= '<button wire:click="addTask">Add Task</button>';

        $html .= '</div>';

        return $html;
    }
}
```

---

## Step 3: Rendering using JSX-Like Syntax (`<php-comp>`)
Now, let's inject our `TaskList` component into our page view. With the new ViewCompiler, we can use clean XML-like syntax instead of clunky PHP snippets.

Create your view file `src/app/views/pages/tasks.php`:

```html
<!DOCTYPE html>
<html>
<head>
    <title><?= $title ?></title>
    <!-- 1. Include the SPPLive manager for zero-dependency reactivity -->
    <script src="/spp/modules/spp/sppview/js/spplive.js"></script>
    <style>
        .task-container { border: 1px solid #ccc; padding: 20px; width: 300px; }
    </style>
</head>
<body>
    <h1>Welcome to SPP Next</h1>

    <!-- 2. Use the new JSX-Like component syntax! -->
    <!-- The ViewCompiler will pre-compile this into native PHP execution -->
    <php-comp name="\App\Components\TaskList"></php-comp>

</body>
</html>
```

---

## What Happens Under The Hood?

1. **Routing:** You visit `http://localhost/tasks`. The `AttributeRouter` maps this to `TaskController::index()`.
2. **Compilation:** The framework sees `<php-comp name="...">` and natively compiles it into PHP object instantiation. It renders the initial HTML state.
3. **Hydration:** Your component's state (`$tasks`, `$newTask`) is serialized to JSON, cryptographically signed with HMAC SHA-256 (preventing tampering), and embedded in the HTML.
4. **Reactivity:** You type a task and click "Add Task". `spplive.js` intercepts this, sends the new state and the `addTask` instruction to the backend via WebSocket or AJAX.
5. **DOM Patching:** The PHP backend rehydrates the component, executes `addTask()`, generates a new HTML chunk, and sends it back. `spplive.js` intelligently patches only the DOM elements that changed!

And just like that, you have built a secure, reactive, single-page-like application entirely in PHP!
