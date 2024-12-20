<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Tests\Listener;

use ArchiPro\EventDispatcher\ListenerProvider;
use ArchiPro\Silverstripe\EventDispatcher\Event\DataObjectEvent;
use ArchiPro\Silverstripe\EventDispatcher\Event\Operation;
use ArchiPro\Silverstripe\EventDispatcher\Listener\DataObjectEventListener;
use ArchiPro\Silverstripe\EventDispatcher\Tests\Mock\SimpleDataObject;
use ArchiPro\Silverstripe\EventDispatcher\Tests\Mock\VersionedDataObject;
use PHPUnit\Framework\MockObject\MockObject;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;

/**
 * Tests for DataObjectEventListener to verify:
 * - Event filtering by class inheritance
 * - Event filtering by operation types
 * - Callback execution
 */
class DataObjectEventListenerTest extends SapphireTest
{
    /** @var class-string<DataObject>[] */
    protected static $extra_dataobjects = [
        SimpleDataObject::class,
        VersionedDataObject::class,
    ];

    /** @var DataObjectEvent<DataObject>[] */
    protected array $receivedEvents = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->receivedEvents = [];
    }

    public function testListenerFiltersByClass(): void
    {
        // Create listener that only handles SimpleDataObject events
        $listener = DataObjectEventListener::create(
            function (DataObjectEvent $event) {
                $this->receivedEvents[] = $event;
            },
            [SimpleDataObject::class]
        );

        /** @var SimpleDataObject $simpleObject */
        $simpleObject = SimpleDataObject::create();
        $simpleObject->ID = 1;
        /** @var VersionedDataObject $versionedObject */
        $versionedObject = VersionedDataObject::create();
        $versionedObject->ID = 1;

        // Should handle SimpleDataObject event
        $simpleEvent = DataObjectEvent::create($simpleObject, Operation::CREATE);
        $listener($simpleEvent);
        $this->assertCount(1, $this->receivedEvents, 'Listener should handle SimpleDataObject events');

        // Should not handle VersionedDataObject event
        $versionedEvent = DataObjectEvent::create($versionedObject, Operation::CREATE);
        $listener($versionedEvent);
        $this->assertCount(1, $this->receivedEvents, 'Listener should not handle VersionedDataObject events');
    }

    public function testListenerHandlesInheritedClasses(): void
    {
        /** @var SimpleDataObject $simpleObject */
        $simpleObject = SimpleDataObject::create();
        $simpleObject->write();

        /** @var VersionedDataObject $versionedObject */
        $versionedObject = VersionedDataObject::create();
        $versionedObject->write();

        // Create listener that handles all DataObject events
        $listener = DataObjectEventListener::create(
            function (DataObjectEvent $event) {
                $this->receivedEvents[] = $event;
            },
            [DataObject::class]
        );

        // Should handle both SimpleDataObject and VersionedDataObject events
        $simpleEvent = DataObjectEvent::create($simpleObject, Operation::CREATE);
        $versionedEvent = DataObjectEvent::create($versionedObject, Operation::CREATE);

        $listener($simpleEvent);
        $listener($versionedEvent);

        $this->assertCount(2, $this->receivedEvents, 'Listener should handle events from DataObject subclasses');
    }

    public function testListenerFiltersByOperation(): void
    {
        /** @var SimpleDataObject $simpleObject */
        $simpleObject = SimpleDataObject::create();
        $simpleObject->write();

        // Create listener that only handles CREATE and UPDATE operations
        $listener = DataObjectEventListener::create(
            function (DataObjectEvent $event) {
                $this->receivedEvents[] = $event;
            },
            [SimpleDataObject::class],
            [Operation::CREATE, Operation::UPDATE]
        );

        // Should handle CREATE event
        $createEvent = DataObjectEvent::create($simpleObject, Operation::CREATE);
        $listener($createEvent);
        $this->assertCount(1, $this->receivedEvents, 'Listener should handle CREATE events');

        // Should handle UPDATE event
        $updateEvent = DataObjectEvent::create($simpleObject, Operation::UPDATE);
        $listener($updateEvent);
        $this->assertCount(2, $this->receivedEvents, 'Listener should handle UPDATE events');

        // Should not handle DELETE event
        $deleteEvent = DataObjectEvent::create($simpleObject, Operation::DELETE);
        $listener($deleteEvent);
        $this->assertCount(2, $this->receivedEvents, 'Listener should not handle DELETE events');
    }

    public function testListenerHandlesAllOperationsWhenNotSpecified(): void
    {
        /** @var SimpleDataObject $simpleObject */
        $simpleObject = SimpleDataObject::create();
        $simpleObject->write();

        // Create listener without specifying operations
        $listener = DataObjectEventListener::create(
            function (DataObjectEvent $event) {
                $this->receivedEvents[] = $event;
            },
            [SimpleDataObject::class]
        );

        // Should handle all operations
        foreach (Operation::cases() as $operation) {
            $event = DataObjectEvent::create($simpleObject, $operation);
            $listener($event);
        }

        $this->assertCount(
            count(Operation::cases()),
            $this->receivedEvents,
            'Listener should handle all operations when none specified'
        );
    }

    public function testSelfRegister(): void
    {
        // Create a mock event service
        /** @var MockObject&ListenerProvider $provider */
        $provider = $this->createMock(ListenerProvider::class);
        $provider->expects($this->once())
            ->method('addListener')
            ->with(
                DataObjectEvent::class,
                $this->isInstanceOf(DataObjectEventListener::class)
            );

        // Create listener and register with mock service
        $listener = DataObjectEventListener::create(
            function (DataObjectEvent $event) {
                $this->receivedEvents[] = $event;
            },
            [SimpleDataObject::class]
        );
        $listener->selfRegister($provider);
    }
}
