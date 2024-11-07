<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Tests\Extension;

use ArchiPro\Silverstripe\EventDispatcher\DataObjectEventListener;
use ArchiPro\Silverstripe\EventDispatcher\Event\DataObjectEvent;
use ArchiPro\Silverstripe\EventDispatcher\Event\Operation;
use ArchiPro\Silverstripe\EventDispatcher\Tests\Mock\SimpleDataObject;
use ArchiPro\Silverstripe\EventDispatcher\Tests\Mock\VersionedDataObject;
use Revolt\EventLoop;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

class EventDispatchExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'EventDispatchExtensionTest.yml';

    protected static $extra_dataobjects = [
        SimpleDataObject::class,
        VersionedDataObject::class,
    ];

    /** @var DataObjectEvent[] */
    private static array $events = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        DataObjectEventListener::create(
            function (DataObjectEvent $event) {
                static::$events[] = $event;
            },
            [SimpleDataObject::class, VersionedDataObject::class]
        )->selfRegister();
    }

    protected function setUp(): void
    {
        parent::setUp();
        static::$events = [];
    }


    public function testWriteEvents(): void
    {
        // Test create
        $object = SimpleDataObject::create(['Title' => 'Test']);
        $object->write();
        EventLoop::run();

        $this->assertCount(1, static::$events);
        $this->assertEquals(Operation::CREATE, static::$events[0]->getOperation());

        // Clear events
        static::$events = [];

        // Test update
        $object->Title = 'Updated';
        $object->write();

        EventLoop::run();

        $this->assertCount(1, static::$events);
        $this->assertEquals(Operation::UPDATE, static::$events[0]->getOperation());
    }

    public function testDeleteEvent(): void
    {
        $object = SimpleDataObject::create(['Title' => 'Test']);
        $object->write();
        EventLoop::run();

        static::$events = [];
        $object->delete();
        EventLoop::run();

        $this->assertCount(1, static::$events);
        $this->assertEquals(Operation::DELETE, static::$events[0]->getOperation());
    }

    public function testVersionedEvents(): void
    {
        /** @var Member $member */
        $member = $this->objFromFixture(Member::class, 'member1');
        Security::setCurrentUser($member);

        /** @var VersionedDataObject $object */
        $object = VersionedDataObject::create(['Title' => 'Test']);
        $object->write();

        EventLoop::run();
        static::$events = [];

        // Test publish
        $object->publishRecursive();
        EventLoop::run();

        $this->assertCount(2, static::$events, 'Expected 2 events, 1 for create and 1 for publish');
        $this->assertEquals(Operation::PUBLISH, static::$events[1]->getOperation());
        $this->assertEquals($member->ID, static::$events[1]->getMemberID());

        // Test unpublish
        static::$events = [];
        $object->doUnpublish();
        EventLoop::run();

        $this->assertCount(2, static::$events, 'Expected 2 events, 1 for deleting the live version and 1 for unpublish');
        $this->assertEquals(Operation::UNPUBLISH, static::$events[1]->getOperation());

        // Test archive
        static::$events = [];
        $object->doArchive();
        EventLoop::run();

        $this->assertCount(2, static::$events, 'Expected 2 events, 1 for deleting the draft version version and 1 for archive');
        $this->assertEquals(Operation::ARCHIVE, static::$events[1]->getOperation());
    }
}
