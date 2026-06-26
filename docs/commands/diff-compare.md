# diff:compare

## NAME
diff:compare - Compare two JSON arrays or states

## SYNOPSIS
`php spp.php diff:compare`

## PURPOSE
Compares two datasets or serialized states using the DeltaEngine component.

## OPTIONS AVAILABLE
This command requires external/custom integration to feed JSON files.

## UNDER THE HOOD ACTIVITY
It assesses the presence of `\SPPMod\SPPDiff\DeltaEngine`. It prints a confirmation if the module is active and exits, currently serving as a placeholder stub that relies on custom programmatic hooks rather than direct CLI file handling.

## EXAMPLES
```bash
php spp.php diff:compare
```
