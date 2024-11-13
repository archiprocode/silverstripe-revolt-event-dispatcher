<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Extension;

use Amp\Future;
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
 * @phpstan-template T of DataObject
 * @phpstan-extends Extension<T>
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
            get_class($owner),
            $owner->ID,
            // By this point isInDB() will return true even for new records since the ID is already set
            // Instead check if the ID field was changed which indicates this is a new record
            $owner->isChanged('ID') ? Operation::CREATE : Operation::UPDATE,
            $this->getVersion(),
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
            get_class($owner),
            $owner->ID,
            Operation::DELETE,
            $this->getVersion(),
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
            get_class($owner),
            $owner->ID,
            Operation::PUBLISH,
            $this->getVersion(),
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
            get_class($owner),
            $owner->ID,
            Operation::UNPUBLISH,
            $this->getVersion(),
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
            get_class($owner),
            $owner->ID,
            Operation::ARCHIVE,
            $this->getVersion(),
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
            get_class($owner),
            $owner->ID,
            Operation::RESTORE,
            $this->getVersion(),
            Security::getCurrentUser()?->ID
        );

        $this->dispatchEvent($event);
    }

    /**
     * Dispatches an event using the EventService
     *
     * @phpstan-param DataObjectEvent<T> $event
     * @phpstan-return Future<DataObjectEvent<T>>
     */
    protected function dispatchEvent(DataObjectEvent $event): Future
    {
        return Injector::inst()->get(EventService::class)->dispatch($event);
    }

    private function getVersion(): ?int
    {
        $owner = $this->getOwner();
        if (!$owner->hasExtension(Versioned::class)) {
            return null;
        }

        /** @var Versioned $owner */
        return $owner->Version;
    }
}
