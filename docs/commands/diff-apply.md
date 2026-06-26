# diff:apply

## NAME
diff:apply - Apply a patch or delta file

## SYNOPSIS
`php spp.php diff:apply [--file=<patch.json>]`

## PURPOSE
Applies a generic JSON delta patch to the underlying state engine.

## OPTIONS AVAILABLE
- `--file=<patch.json>`: Target delta file to apply.

## UNDER THE HOOD ACTIVITY
The command checks the system for the presence of the `\SPPMod\SPPDiff\DeltaEngine` module class. Currently acting as a stub, it asserts module availability and indicates command usage, reserving full delta assimilation logic for future module iterations.

## EXAMPLES
```bash
php spp.php diff:apply --file=patch.json
```
