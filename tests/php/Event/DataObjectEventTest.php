<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Tests\Event;

use ArchiPro\Silverstripe\EventDispatcher\Event\DataObjectEvent;
use ArchiPro\Silverstripe\EventDispatcher\Event\Operation;
use ArchiPro\Silverstripe\EventDispatcher\Tests\Mock\SimpleDataObject;
use ArchiPro\Silverstripe\EventDispatcher\Tests\Mock\VersionedDataObject;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

class DataObjectEventTest extends SapphireTest
{
    /** @var string */
    protected static $fixture_file = 'DataObjectEventTest.yml';

    /** @var string[] */
    protected static $extra_dataobjects = [
        SimpleDataObject::class,
        VersionedDataObject::class,
    ];

    public function testEventCreation(): void
    {
        $event = DataObjectEvent::create(SimpleDataObject::class, 1, Operation::CREATE, null, 1);

        $this->assertEquals(1, $event->getObjectID());
        $this->assertEquals(SimpleDataObject::class, $event->getObjectClass());
        $this->assertEquals(Operation::CREATE, $event->getOperation());
        $this->assertNull($event->getVersion());
        $this->assertEquals(1, $event->getMemberID());
        $this->assertGreaterThan(0, $event->getTimestamp());
    }

    public function testGetObject(): void
    {
        /** @var SimpleDataObject $object */
        $object = $this->objFromFixture(SimpleDataObject::class, 'object1');

        $event = DataObjectEvent::create(SimpleDataObject::class, $object->ID, Operation::UPDATE);

        $this->assertNotNull($event->getObject());
        $this->assertEquals($object->ID, $event->getObject()->ID);
    }

    public function testGetVersionedObject(): void
    {
        /** @var VersionedDataObject $object */
        $object = $this->objFromFixture(VersionedDataObject::class, 'versioned1');

        // Create a new version
        $object->Title = 'Updated Title';
        $object->write();

        /** @var DataObjectEvent<VersionedDataObject> $event */
        $event = DataObjectEvent::create(VersionedDataObject::class, $object->ID, Operation::UPDATE, $object->Version);

        // Get current version
        /** @var VersionedDataObject $currentObject */
        $currentObject = $event->getObject(false);
        $this->assertEquals('Updated Title', $currentObject->Title);

        // Get specific version
        /** @var VersionedDataObject $versionedObject */
        $versionedObject = $event->getObject(true);
        $this->assertEquals('Updated Title', $versionedObject->Title);

        // Get previous version
        /** @var DataObjectEvent<VersionedDataObject> $previousEvent */
        $previousEvent = DataObjectEvent::create(VersionedDataObject::class, $object->ID, Operation::UPDATE, $object->Version - 1);
        /** @var VersionedDataObject $previousVersion */
        $previousVersion = $previousEvent->getObject(true);
        $this->assertEquals('Original Title', $previousVersion->Title);
    }

    public function testGetMember(): void
    {
        /** @var Member $member */
        $member = $this->objFromFixture(Member::class, 'member1');

        $event = DataObjectEvent::create(SimpleDataObject::class, 1, Operation::CREATE, null, $member->ID);

        $this->assertNotNull($event->getMember());
        $this->assertEquals($member->ID, $event->getMember()->ID);
    }

    public function testSerialization(): void
    {
        $event = DataObjectEvent::create(SimpleDataObject::class, 1, Operation::CREATE, 2, 3);

        $serialized = serialize($event);
        /** @var DataObjectEvent<SimpleDataObject> $unserialized */
        $unserialized = unserialize($serialized);

        $this->assertEquals(1, $unserialized->getObjectID());
        $this->assertEquals(SimpleDataObject::class, $unserialized->getObjectClass());
        $this->assertEquals(Operation::CREATE, $unserialized->getOperation());
        $this->assertEquals(2, $unserialized->getVersion());
        $this->assertEquals(3, $unserialized->getMemberID());
        $this->assertEquals($event->getTimestamp(), $unserialized->getTimestamp());
    }
}
