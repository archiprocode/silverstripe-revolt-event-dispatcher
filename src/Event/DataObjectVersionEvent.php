<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Event;

/**
 * Event class for versioning-related actions on DataObjects.
 * 
 * This event is fired when versioning actions occur, such as:
 * - Publishing
 * - Unpublishing
 * - Archiving
 * - Restoring
 */
class DataObjectVersionEvent extends AbstractDataObjectEvent
{
    public function __construct(
        int $objectID,
        string $objectClass,
        string $action,
        private readonly ?int $version,
        array $changes = []
    ) {
        parent::__construct($objectID, $objectClass, $action, $changes);
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'version' => $this->version,
        ]);
    }
} 