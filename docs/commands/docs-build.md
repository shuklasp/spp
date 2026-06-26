# docs:build

## NAME
docs:build - Documentation utilities

## SYNOPSIS
`php spp.php docs:build`
`php spp.php docs:phpdoc`

## PURPOSE
A dual-purpose command meant to either generate native static HTML SPP documentation or run an external phpDocumentor PHAR script to document API classes.

## OPTIONS AVAILABLE
- `docs:build` (argument): Builds the native SPP documentation.
- `docs:phpdoc` (argument): Invokes the standalone phpDocumentor generator.

## UNDER THE HOOD ACTIVITY
When the `docs:build` argument is passed, the script requires the localized `DocParser.php` and `StaticGenerator.php` elements of the `SPPDoc` module. It instructs `\SPPMod\SPPDoc\StaticGenerator::build()` to generate the output files directly into `docs/sppdoc`. 
When `docs:phpdoc` is supplied, it verifies the existence of `phpDocumentor.phar` in the project root. It crafts a shell command spanning the PHP executable over the PHAR file and runs it with `passthru()`, monitoring the exact `$returnVar` code to determine terminal output success or failure.

## EXAMPLES
```bash
php spp.php docs:build
php spp.php docs:phpdoc
```
