<?php
namespace SPP\Entity\Revision;

/**
 * Interface RevisionStorageInterface
 * 
 * Core contract for revision persistence.
 */
interface RevisionStorageInterface
{
    /**
     * Save a new revision of the given entity.
     *
     * @param object $entity  The entity instance.
     * @param string $message Optional log message for the revision.
     * @param string $author  Optional author identifier.
     * @return mixed          The new revision ID.
     */
    public function saveRevision(object $entity, string $message = '', string $author = '');

    /**
     * Load a specific revision of an entity.
     *
     * @param string $entityType The type of the entity (e.g., 'node').
     * @param mixed  $entityId   The ID of the entity.
     * @param mixed  $revisionId The ID of the revision to load.
     * @return array|null        The revision data array, or null if not found.
     */
    public function loadRevision(string $entityType, $entityId, $revisionId): ?array;

    /**
     * List all revisions for a specific entity.
     *
     * @param string $entityType The type of the entity.
     * @param mixed  $entityId   The ID of the entity.
     * @return array             Array of revisions (metadata only, not full data).
     */
    public function listRevisions(string $entityType, $entityId): array;

    /**
     * Delete a specific revision.
     *
     * @param string $entityType
     * @param mixed  $entityId
     * @param mixed  $revisionId
     * @return bool
     */
    public function deleteRevision(string $entityType, $entityId, $revisionId): bool;
}
