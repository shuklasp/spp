# NAME

`ask`

# SYNOPSIS

`php spp.php ask "<question>"`

# PURPOSE

Interacts with the SPP AI Mentor to provide intelligent onboarding answers. If the AI Daemon is unreachable, it gracefully degrades to a localized keyword search across the documentation repository.

# OPTIONS AVAILABLE

- `"<question>"` : (Required Positional Argument) A natural language question. All trailing arguments are concatenated into a single query string.

# UNDER THE HOOD ACTIVITY

The command concatenates all trailing arguments to form the complete question string. It then attempts an inter-process communication (IPC) call to the AI Daemon via the `\SPP\PolyglotBridge::call()` method. The target bridge call explicitly requests the `python` language driver to execute the `handle_spp_request` function within the `services/python/ai_mentor.py` module, operating in daemon mode (`true`). It passes an associative array payload dictating the `ask` action and the raw question. 

If the bridge returns a valid payload without an `error` key, the command echoes the AI Mentor's answer to the console.

If an exception occurs (e.g., the daemon is offline, or the bridge fails), the `catch` block is triggered, activating the "Graceful Degradation" fallback. The `fallbackSearch()` method aggressively strips punctuation from the question and excludes basic English stop words to produce an array of search keywords. It then instantiates a `RecursiveDirectoryIterator` to traverse `SPP_APP_DIR . '/documentation'`. For every markdown file (`.md`) found, the system loads the file contents into memory, converts it to lowercase, and performs repeated `str_contains()` checks against the extracted keywords, incrementing a score variable. It collates the files with positive scores, sorts them dynamically using `usort()` in descending order, and displays the paths to the top three matched files to the user as alternative reading material.

# EXAMPLES

Ask a specific technical question:
```bash
php spp.php ask "How do I implement custom middleware?"
```
