# NAME
spp component:crud - Manage SPP UI components (list, create, edit, delete)

# SYNOPSIS
`php spp.php component:crud <action> [name] [--app=appname] [--editor=editor]`

# PURPOSE
A concrete implementation of `BaseElementCommand` dedicated entirely to managing `.edge` (Edge template engine) UI component fragments within a specific application's `views/components` directory.

# OPTIONS AVAILABLE
Inherits all options from `BaseElementCommand`.
- `<action>` : `list`, `create`, `edit`, `delete`
- `[name]` : Name of the component fragment.
- `--app=<appname>` : App context (Defaults to `default`).
- `--editor=<editor>` : Text editor to spawn.

# UNDER THE HOOD ACTIVITY
The command extends `BaseElementCommand`.
- **Path Resolution:** Implements `getElementPath()` to route target destinations directly into `App::getApp($appname)->getAppSrcDir() . '/views/components/' . $name . '.edge'`.
- **Listing:** Implements `listElements()` utilizing a simple `glob()` search within the scoped components directory targeting `*.edge` files, safely omitting paths to return clean basenames.
Delegates file creation, filesystem removal, and interactive TTY editor spawning entirely to the parent abstract class.

# EXAMPLES
Create a generic button component template:
`php spp.php component:crud create Button --app=frontend`

List all existing components:
`php spp.php component:crud list --app=frontend`
