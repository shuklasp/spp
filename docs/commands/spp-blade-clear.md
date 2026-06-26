# NAME
spp blade:clear - Clear the compiled Blade view cache

# SYNOPSIS
`php spp.php blade:clear`

# PURPOSE
Forcefully obliterates all compiled Blade templates (`.php` files) generated from `.blade.php` source files, ensuring that subsequent requests re-compile the views from scratch.

# OPTIONS AVAILABLE
None.

# UNDER THE HOOD ACTIVITY
Bypasses the `Cache` driver abstraction and targets the filesystem explicitly. It locks onto `SPP_APP_DIR/var/cache`. It invokes a private custom `recursiveScan()` method which traverses the cache directory deeply using `scandir()`, collecting an absolute path array of all files nested within. It loops through the inventory, specifically targeting files ending in `.php` while explicitly protecting `.gitignore` files to preserve directory structure in version control. Matches are deleted via `unlink()`, and the total obliteration count is summarized.

# EXAMPLES
`php spp.php blade:clear`
