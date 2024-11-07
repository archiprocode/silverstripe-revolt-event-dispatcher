<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Extension;

use ArchiPro\Silverstripe\EventDispatcher\Event\DataObjectEvent;
use ArchiPro\Silverstripe\EventDispatcher\Event\Operation;
use ArchiPro\Silverstripe\EventDispatcher\Service\EventService;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;

/**
 * Extension that adds event dispatching capabilities to DataObjects.
 *
 * @property DataObject|Versioned $owner
 *
 * @method DataObject getOwner()
 */
class EventDispatchExtension extends Extension
{
    /**
     * Fires an event after the object is written (created or updated)
     */
    public function onAfterWrite(): void
    {
        $owner = $this->getOwner();
        $event = DataObjectEvent::create(
            $owner->ID,
            get_class($owner),
            // By this point isInDB() will return true even for new records since the ID is already set
            // Instead check if the ID field was changed which indicates this is a new record
            $owner->isChanged('ID') ? Operation::CREATE : Operation::UPDATE,
            $owner->hasExtension(Versioned::class) ? $owner->Version : null,
            Security::getCurrentUser()?->ID
        );

        $this->dispatchEvent($event);
    }

    /**
     * Fires before a DataObject is deleted from the database
     */
    public function onBeforeDelete(): void
    {
        $owner = $this->getOwner();
        $event = DataObjectEvent::create(
            $owner->ID,
            get_class($owner),
            Operation::DELETE,
            $owner->hasExtension(Versioned::class) ? $owner->Version : null,
            Security::getCurrentUser()?->ID
        );

        $this->dispatchEvent($event);
    }

    /**
     * Fires when a versioned DataObject is published
     */
    public function onAfterPublish(): void
    {
        $owner = $this->getOwner();
        if (!$owner->hasExtension(Versioned::class)) {
            return;
        }

        $event = DataObjectEvent::create(
            $owner->ID,
            get_class($owner),
            Operation::PUBLISH,
            $owner->Version,
            Security::getCurrentUser()?->ID
        );

        $this->dispatchEvent($event);
    }

    /**
     * Fires when a versioned DataObject is unpublished
     */
    public function onAfterUnpublish(): void
    {
        $owner = $this->getOwner();
        if (!$owner->hasExtension(Versioned::class)) {
            return;
        }

        $event = DataObjectEvent::create(
            $owner->ID,
            get_class($owner),
            Operation::UNPUBLISH,
            $owner->Version,
            Security::getCurrentUser()?->ID
        );

        $this->dispatchEvent($event);
    }

    /**
     * Fires when a versioned DataObject is archived
     */
    public function onAfterArchive(): void
    {
        $owner = $this->getOwner();
        if (!$owner->hasExtension(Versioned::class)) {
            return;
        }

        $event = DataObjectEvent::create(
            $owner->ID,
            get_class($owner),
            Operation::ARCHIVE,
            $owner->Version,
            Security::getCurrentUser()?->ID
        );

        $this->dispatchEvent($event);
    }

    /**
     * Fires when a versioned DataObject is restored from archive
     */
    public function onAfterRestore(): void
    {
        $owner = $this->getOwner();
        if (!$owner->hasExtension(Versioned::class)) {
            return;
        }

        $event = DataObjectEvent::create(
            $owner->ID,
            get_class($owner),
            Operation::RESTORE,
            $owner->Version,
            Security::getCurrentUser()?->ID
        );

        $this->dispatchEvent($event);
    }

    /**
     * Dispatches an event using the EventService
     */
    protected function dispatchEvent(DataObjectEvent $event): DataObjectEvent
    {
        return Injector::inst()->get(EventService::class)->dispatch($event);
    }
}
