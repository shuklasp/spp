# NAME

`spp generate` - AI Copilot: Generate an entire application feature from a natural language prompt.

# SYNOPSIS

`php spp.php generate "prompt description"`

# PURPOSE

The `generate` command leverages the SPP AI Copilot heuristic engine to interpret natural language prompts and automatically scaffold full application features. This includes automatically designing the database schema, scaffolding entities, writing controllers, APIs, and generating basic UI views based on the interpreted requirements.

# OPTIONS AVAILABLE

- `<prompt>` (string): The natural language description of the feature you want to generate. All arguments after the command name are joined to form the prompt.

# UNDER THE HOOD ACTIVITY

The command captures all arguments following `generate` and concatenates them into a single prompt string. It enforces that a prompt is provided.
To simulate the behavior of a sophisticated AI engine, the command implements timed artificial delays (`sleep(1)`) while printing status messages (analyzing, identifying entities, scaffolding).
It uses a heuristic fallback logic that scans the lowercased prompt for keywords:
- If it detects `ecommerce` or `store`, it assumes the entities required are `Product`, `Order`, and `Customer`.
- If it detects `blog`, it assumes `Post`, `Comment`, and `Tag`.
- Otherwise, it falls back to a generic `FeatureModel`.
For each identified entity, it constructs a configuration array defining table properties (`table`, `id_field`, `sequence`), login configuration, and basic attributes (`name` as varchar, `created_at` as timestamp). It then invokes `\SPPMod\SPPDB\SPPEntity::saveEntityDefinition()` to write the formal entity definition schemas to the file system (within the 'default' app context).

# EXAMPLES

**Generate an e-commerce feature:**
```bash
php spp.php generate "A complete ecommerce store with products and orders"
```

**Generate a blog module:**
```bash
php spp.php generate "A blog system with posts and comments"
```
