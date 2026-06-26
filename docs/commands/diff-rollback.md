# diff:rollback

## NAME
diff:rollback - Rollback an entity to a previous state

## SYNOPSIS
`php spp.php diff:rollback --type=<ModelClass> --id=<ID> --rev=<RevID>`

## PURPOSE
Reverts a specific Entity to an exact historic revision tracked by the RevisionManager.

## OPTIONS AVAILABLE
- `--type=<ModelClass>`: **Required**. The fully qualified class name of the entity.
- `--id=<ID>`: **Required**. The primary key ID of the entity.
- `--rev=<RevID>`: **Required**. The specific integer Revision ID to roll back to.

## UNDER THE HOOD ACTIVITY
The command instantiates the target ModelClass and uses `find_one()` to verify the entity's current existence. It queries `\SPPMod\SPPDiff\RevisionManager::getRevision($entity, $revId)` to synthesize an older state of the entity object based on the delta logs. Finally, it executes the entity's native `save()` method, flushing the synthesized previous state directly back to the database as the new definitive state.

## EXAMPLES
```bash
php spp.php diff:rollback --type="\App\Entities\User" --id=42 --rev=5
```
