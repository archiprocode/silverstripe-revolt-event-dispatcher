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
        /** @var SimpleDataObject $object */
        $object = $this->objFromFixture(SimpleDataObject::class, 'object1');
        $event = DataObjectEvent::create($object, Operation::CREATE, 1);

        $this->assertEquals($object->ID, $event->getObjectID());
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
        $event = DataObjectEvent::create($object, Operation::UPDATE);

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
        $event = DataObjectEvent::create($object, Operation::UPDATE);

        // Get current version
        /** @var VersionedDataObject $currentObject */
        $currentObject = $event->getObject(false);
        $this->assertEquals('Updated Title', $currentObject->Title);

        // Get specific version
        /** @var VersionedDataObject $versionedObject */
        $versionedObject = $event->getObject(true);
        $this->assertEquals('Updated Title', $versionedObject->Title);

        // Get previous version
        $previousObject = $object;
        $previousObject->Version--;
        /** @var DataObjectEvent<VersionedDataObject> $previousEvent */
        $previousEvent = DataObjectEvent::create($previousObject, Operation::UPDATE);
        /** @var VersionedDataObject $previousVersion */
        $previousVersion = $previousEvent->getObject(true);
        $this->assertEquals('Original Title', $previousVersion->Title);
    }

    public function testGetMember(): void
    {
        /** @var Member $member */
        $member = $this->objFromFixture(Member::class, 'member1');
        /** @var SimpleDataObject $object */
        $object = $this->objFromFixture(SimpleDataObject::class, 'object1');

        $event = DataObjectEvent::create($object, Operation::CREATE, $member->ID);

        $this->assertNotNull($event->getMember());
        $this->assertEquals($member->ID, $event->getMember()->ID);
    }

    public function testSerialization(): void
    {
        $do = new SimpleDataObject();
        $do->Title = 'Test alpha';
        $do->write();
        $event = DataObjectEvent::create($do, Operation::CREATE, 3);

        $serialized = serialize($event);
        /** @var DataObjectEvent<SimpleDataObject> $unserialized */
        $unserialized = unserialize($serialized);

        $this->assertEquals($do->ID, $unserialized->getObjectID());
        $this->assertEquals(SimpleDataObject::class, $unserialized->getObjectClass());
        $this->assertEquals($do->getQueriedDatabaseFields(), $unserialized->getRecord());
        $this->assertEquals(Operation::CREATE, $unserialized->getOperation());
        $this->assertNull($unserialized->getVersion());
        $this->assertEquals(3, $unserialized->getMemberID());
        $this->assertEquals($event->getTimestamp(), $unserialized->getTimestamp());
    }
}
