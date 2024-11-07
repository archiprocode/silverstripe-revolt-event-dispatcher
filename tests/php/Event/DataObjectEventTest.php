<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Tests\Event;

use ArchiPro\Silverstripe\EventDispatcher\Event\DataObjectEvent;
use ArchiPro\Silverstripe\EventDispatcher\Event\Operation;
use ArchiPro\Silverstripe\EventDispatcher\Tests\Mock\SimpleDataObject;
use ArchiPro\Silverstripe\EventDispatcher\Tests\Mock\VersionedDataObject;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;

class DataObjectEventTest extends SapphireTest
{
    protected static $fixture_file = 'DataObjectEventTest.yml';

    protected static $extra_dataobjects = [
        SimpleDataObject::class,
        VersionedDataObject::class,
    ];

    public function testEventCreation(): void
    {
        $event = DataObjectEvent::create(1, SimpleDataObject::class, Operation::CREATE, null, 1);

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
        
        $event = DataObjectEvent::create($object->ID, SimpleDataObject::class, Operation::UPDATE);
        
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
        
        $event = DataObjectEvent::create($object->ID, VersionedDataObject::class, Operation::UPDATE, $object->Version);
        
        // Get current version
        $currentObject = $event->getObject(false);
        $this->assertEquals('Updated Title', $currentObject->Title);
        
        // Get specific version
        $versionedObject = $event->getObject(true);
        $this->assertEquals('Updated Title', $versionedObject->Title);
        
        // Get previous version
        $previousEvent = DataObjectEvent::create($object->ID, VersionedDataObject::class, Operation::UPDATE, $object->Version - 1);
        $previousVersion = $previousEvent->getObject(true);
        $this->assertEquals('Original Title', $previousVersion->Title);
    }

    public function testGetMember(): void
    {
        /** @var Member $member */
        $member = $this->objFromFixture(Member::class, 'member1');
        
        $event = DataObjectEvent::create(1, SimpleDataObject::class, Operation::CREATE, null, $member->ID);
        
        $this->assertNotNull($event->getMember());
        $this->assertEquals($member->ID, $event->getMember()->ID);
    }

    public function testSerialization(): void
    {
        $event = DataObjectEvent::create(1, SimpleDataObject::class, Operation::CREATE, 2, 3);
        
        $serialized = serialize($event);
        /** @var DataObjectEvent $unserialized */
        $unserialized = unserialize($serialized);
        
        $this->assertEquals(1, $unserialized->getObjectID());
        $this->assertEquals(SimpleDataObject::class, $unserialized->getObjectClass());
        $this->assertEquals(Operation::CREATE, $unserialized->getOperation());
        $this->assertEquals(2, $unserialized->getVersion());
        $this->assertEquals(3, $unserialized->getMemberID());
        $this->assertEquals($event->getTimestamp(), $unserialized->getTimestamp());
    }
} 