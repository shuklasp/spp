# diff:history

## NAME
diff:history - View revision history of an entity

## SYNOPSIS
`php spp.php diff:history --type=<ModelClass> --id=<ID>`

## PURPOSE
Pulls the complete list of chronological revisions logged by the SPPDiff RevisionManager for a given Entity instance.

## OPTIONS AVAILABLE
- `--type=<ModelClass>`: **Required**. The fully qualified class name of the entity.
- `--id=<ID>`: **Required**. The primary key ID of the entity.

## UNDER THE HOOD ACTIVITY
The command verifies that the passed ModelClass exists. It invokes the static `find_one()` method on the entity class to query the active instance from the database using the provided ID. If found, it delegates the entity to `\SPPMod\SPPDiff\RevisionManager::getHistory($entity)`. It iterates through the chronological revision payload returning metadata such as the Revision ID, creation timestamp, and the responsible User ID.

## EXAMPLES
```bash
php spp.php diff:history --type="\App\Entities\User" --id=42
```
