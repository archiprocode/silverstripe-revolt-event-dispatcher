<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Event;

use JsonSerializable;

/**
 * Base class for all DataObject-related events.
 * 
 * Provides common functionality for events that are triggered by DataObject operations.
 * All events are serializable to JSON for easy logging and external system integration.
 */
abstract class AbstractDataObjectEvent implements JsonSerializable
{
    public function __construct(
        protected readonly int $objectID,
        protected readonly string $objectClass,
        protected readonly string $action,
        protected readonly array $changes = []
    ) {}

    public function getObjectID(): int
    {
        return $this->objectID;
    }

    public function getObjectClass(): string
    {
        return $this->objectClass;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getChanges(): array
    {
        return $this->changes;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->objectID,
            'class' => $this->objectClass,
            'action' => $this->action,
            'changes' => $this->changes,
            'timestamp' => time(),
        ];
    }
} 