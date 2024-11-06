<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Extension;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use ArchiPro\Silverstripe\EventDispatcher\Event\DataObjectWriteEvent;
use ArchiPro\Silverstripe\EventDispatcher\Event\DataObjectDeleteEvent;
use ArchiPro\Silverstripe\EventDispatcher\Event\DataObjectVersionEvent;
use ArchiPro\Silverstripe\EventDispatcher\Service\EventService;

/**
 * Extension that adds event dispatching capabilities to DataObjects.
 * 
 * This extension automatically fires events for various DataObject operations:
 * - Create/Update (write)
 * - Delete (both soft and hard deletes)
 * - Publish/Unpublish (if versioned)
 * - Archive/Restore (if versioned)
 * 
 * It also tracks changes made to the DataObject and includes them in the fired events.
 * 
 * @property DataObject|Versioned $owner
 */
class EventDispatchExtension extends DataExtension
{
    /** @var array Stores the original state of the object before changes */
    private $originalData = [];

    /** @var bool Flag to track if this is a soft delete operation */
    private $isSoftDelete = false;

    /**
     * Captures the original state of the object before it's written
     */
    public function onBeforeWrite(): void
    {
        $this->originalData = $this->owner->exists() ? $this->owner->getQueriedDatabaseFields() : [];
    }

    /**
     * Fires an event after the object is written (created or updated)
     */
    public function onAfterWrite(): void
    {
        // Don't fire write events during deletion process
        if ($this->isSoftDelete) {
            return;
        }

        $event = new DataObjectWriteEvent(
            $this->owner->ID,
            get_class($this->owner),
            $this->owner->isInDB() ? 'update' : 'create',
            $this->getChanges()
        );
        
        $this->dispatchEvent($event);
    }

    /**
     * Fires before a DataObject is deleted from the database
     * For versioned objects, this is called during both soft and hard deletes
     */
    public function onBeforeDelete(): void
    {
        $isVersioned = $this->owner->hasExtension(Versioned::class);
        $this->isSoftDelete = $isVersioned && !$this->owner->getIsDeleteFromStage();

        $event = new DataObjectDeleteEvent(
            $this->owner->ID,
            get_class($this->owner),
            $this->isSoftDelete ? 'soft_delete' : 'hard_delete',
            [
                'is_versioned' => $isVersioned,
                'deleted_from_stage' => $this->owner->getIsDeleteFromStage(),
                'version' => $isVersioned ? $this->owner->Version : null,
            ]
        );
        
        $this->dispatchEvent($event);
    }

    /**
     * Fires after a DataObject is deleted from the database
     */
    public function onAfterDelete(): void
    {
        // Reset the soft delete flag
        $this->isSoftDelete = false;
    }

    /**
     * Fires when a versioned DataObject is published
     */
    public function onAfterPublish(): void
    {
        if (!$this->owner->hasExtension(Versioned::class)) {
            return;
        }

        $event = new DataObjectVersionEvent(
            $this->owner->ID,
            get_class($this->owner),
            'publish',
            $this->getChanges(),
            $this->owner->Version
        );
        
        $this->dispatchEvent($event);
    }

    /**
     * Fires when a versioned DataObject is unpublished
     */
    public function onAfterUnpublish(): void
    {
        if (!$this->owner->hasExtension(Versioned::class)) {
            return;
        }

        $event = new DataObjectVersionEvent(
            $this->owner->ID,
            get_class($this->owner),
            'unpublish',
            [],
            $this->owner->Version
        );
        
        $this->dispatchEvent($event);
    }

    /**
     * Fires when a versioned DataObject is archived
     */
    public function onAfterArchive(): void
    {
        if (!$this->owner->hasExtension(Versioned::class)) {
            return;
        }

        $event = new DataObjectVersionEvent(
            $this->owner->ID,
            get_class($this->owner),
            'archive',
            [],
            $this->owner->Version
        );
        
        $this->dispatchEvent($event);
    }

    /**
     * Fires when a versioned DataObject is restored from archive
     */
    public function onAfterRestore(): void
    {
        if (!$this->owner->hasExtension(Versioned::class)) {
            return;
        }

        $event = new DataObjectVersionEvent(
            $this->owner->ID,
            get_class($this->owner),
            'restore',
            [],
            $this->owner->Version
        );
        
        $this->dispatchEvent($event);
    }

    /**
     * Calculates the changes made to the object by comparing original and new state
     * 
     * @return array Array of changes with 'old' and 'new' values for each changed field
     */
    protected function getChanges(): array
    {
        if (empty($this->originalData)) {
            return $this->owner->toMap();
        }

        $changes = [];
        $newData = $this->owner->toMap();

        foreach ($newData as $field => $value) {
            if (!isset($this->originalData[$field]) || $this->originalData[$field] !== $value) {
                $changes[$field] = [
                    'old' => $this->originalData[$field] ?? null,
                    'new' => $value
                ];
            }
        }

        return $changes;
    }

    /**
     * Dispatches an event using the EventService
     * 
     * @param object $event The event to dispatch
     * @return object The processed event
     */
    protected function dispatchEvent(object $event): object
    {
        return Injector::inst()->get(EventService::class)->dispatch($event);
    }
} 