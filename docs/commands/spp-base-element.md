# NAME
spp base-element - Abstract base command for element CRUD operations

# SYNOPSIS
*This is an abstract class, not a direct command.*
`php spp.php <command> <action> [name] [--app=appname] [--editor=editor]`

# PURPOSE
Provides a standardized abstraction layer (`BaseElementCommand`) for all SPP CLI commands that involve creating, reading, updating, or deleting (CRUD) framework elements such as components, services, forms, or entities. It handles repetitive filesystem operations, template generation, and interactive editor invocation.

# OPTIONS AVAILABLE
- `<action>` : The CRUD action to perform (`list`, `create`, `edit`, `delete`).
- `[name]` : The name of the element to operate on. Required for `create`, `edit`, and `delete`.
- `--app=<appname>` : The application context to operate within. Defaults to `default`.
- `--editor=<editor>` : The text editor to use when editing an element interactively. Options include `code`, `notepad`, `nano`, `vim`. If omitted, prompts the user.

# UNDER THE HOOD ACTIVITY
When an inheriting command triggers `handleCrud`, the `BaseElementCommand` parses the options and delegates to abstract methods (`getElementPath` and `listElements`) which must be implemented by child classes to define the exact filesystem targets.
- **list:** Fetches the list of elements from the subclass and prints them.
- **create:** Checks if the target path exists. If not, it creates the directory tree via `mkdir`. It then generates boilerplate content using `createElementTemplate()`. For entities (`.yml`/`.yaml`), it interactively prompts the user for a table name and writes a schema stub. For forms, it prompts for a public name. For PHP and JS files, it sets up basic file headers and class stubs. After creation, it interactively asks if the user wants to open the file in an editor immediately.
- **edit:** Validates the element's existence and passes the absolute file path to `openEditor()`. `openEditor()` determines the OS environment. On Windows, it executes the editor using PHP's `system()` function. On Unix, it carefully constructs a descriptor spec bridging `/dev/tty` so terminal-based editors like `nano` or `vim` can assume interactive foreground control using `proc_open()`.
- **delete:** Ensures the element exists, interactively confirms deletion via a prompt, and cleanly removes the file using `unlink()`.

# EXAMPLES
Since this is an abstract base class, it is not executed directly. An inheriting class (like `component:crud`) would be executed as:
`php spp.php component:crud create Button --app=admin --editor=code`
