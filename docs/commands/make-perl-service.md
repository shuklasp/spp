# NAME
`make:perl-service` - Create a new Perl service script

# SYNOPSIS
`php spp.php make:perl-service <name> [--app=context]`

# PURPOSE
The `make:perl-service` command scaffolds a `.pl` Perl executable script intended to be consumed as a Polyglot service within the broader SPP application lifecycle, optimal for complex text processing or legacy script integration.

# OPTIONS AVAILABLE
- `<name>` (string, required): The logic identifier for the Perl script.
- `--app=<context>` (string, optional): Determines the target execution namespace.

# UNDER THE HOOD ACTIVITY
It defines the target path resolving to `services/perl/service.{lowercase_name}.pl`. Through the `buildFromStub()` method mapping to the `perl_service` stub, it physically constructs the Perl script on the filesystem, wiring the `$className` into the script's internal logic structures.

# EXAMPLES
**1. Scaffold a Regex Parser in Perl:**
```bash
php spp.php make:perl-service RegexParser --app=utilities
```
