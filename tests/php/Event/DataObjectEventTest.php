<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Tests\Event;

use ArchiPro\Silverstripe\EventDispatcher\Event\DataObjectEvent;
use ArchiPro\Silverstripe\EventDispatcher\Event\Operation;
use ArchiPro\Silverstripe\EventDispatcher\Tests\Mock\SimpleDataObject;
use ArchiPro\Silverstripe\EventDispatcher\Tests\Mock\VersionedDataObject;
use SilverStripe\Dev\Deprecation;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
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
        $this->assertEquals($event->getChangedFields(), $unserialized->getChangedFields());
    }

    public function testCreateCapturesAssignedFields(): void
    {
        $object = SimpleDataObject::create();
        $object->Title = 'Assigned Title';
        $event = DataObjectEvent::create($object, Operation::CREATE);

        $this->assertTrue($event->isChanged('Title'));
        $this->assertArrayHasKey('Title', $event->getChangedFields());
        $this->assertArrayHasKey('before', $event->getChangedFields()['Title']);
        $this->assertArrayHasKey('after', $event->getChangedFields()['Title']);
        $this->assertArrayHasKey('level', $event->getChangedFields()['Title']);
        $this->assertEquals('Assigned Title', $event->getRecord()['Title']);
        $this->assertEquals('Assigned Title', $event->getChangedFields()['Title']['after']);
    }

    public function testUpdateCapturesBeforeAndAfter(): void
    {
        /** @var SimpleDataObject $object */
        $object = $this->objFromFixture(SimpleDataObject::class, 'object1');
        $object->Title = 'Old';
        $object->write();
        $object->Title = 'New';
        $event = DataObjectEvent::create($object, Operation::UPDATE);

        $this->assertTrue($event->isChanged('Title'));
        $this->assertEquals('Old', $event->getChangedFields()['Title']['before']);
        $this->assertEquals('New', $event->getChangedFields()['Title']['after']);
    }

    public function testReloadedObjectHasNoChangeFlag(): void
    {
        /** @var SimpleDataObject $object */
        $object = $this->objFromFixture(SimpleDataObject::class, 'object1');
        $object->Title = 'Old';
        $object->write();
        $object->Title = 'New';
        $event = DataObjectEvent::create($object, Operation::UPDATE);

        $reloaded = $event->getObject();
        $this->assertNotNull($reloaded);
        $this->assertFalse($reloaded->isChanged('Title'));
        $this->assertTrue($event->isChanged('Title'));
    }

    public function testUnchangedValueIsNotChangedAtValueLevel(): void
    {
        /** @var SimpleDataObject $object */
        $object = $this->objFromFixture(SimpleDataObject::class, 'object1');
        $object->Title = $object->Title;
        $event = DataObjectEvent::create($object, Operation::UPDATE);

        $this->assertFalse($event->isChanged('Title'));
        $this->assertFalse(
            $object->isChanged('Title', DataObject::CHANGE_VALUE),
            'ORM also reports no CHANGE_VALUE when the same Title string is assigned again'
        );
    }

    public function testIsChangedWithoutFieldName(): void
    {
        $changed = SimpleDataObject::create();
        $changed->Title = 'Assigned Title';
        $changedEvent = DataObjectEvent::create($changed, Operation::CREATE);
        $this->assertTrue($changedEvent->isChanged());

        /** @var SimpleDataObject $clean */
        $clean = $this->objFromFixture(SimpleDataObject::class, 'object1');
        $cleanEvent = DataObjectEvent::create($clean, Operation::UPDATE);
        $this->assertFalse($cleanEvent->isChanged());
    }

    public function testUnchangedFieldIsAbsentFromChangedFields(): void
    {
        /** @var SimpleDataObject $object */
        $object = $this->objFromFixture(SimpleDataObject::class, 'object1');
        $event = DataObjectEvent::create($object, Operation::UPDATE);

        $this->assertArrayNotHasKey('Title', $event->getChangedFields());
    }

    public function testCleanObjectHasEmptyChangedFields(): void
    {
        /** @var SimpleDataObject $object */
        $object = $this->objFromFixture(SimpleDataObject::class, 'object1');
        $event = DataObjectEvent::create($object, Operation::UPDATE);

        $this->assertSame([], $event->getChangedFields());
    }

    public function testSerializationPreservesChangedFields(): void
    {
        $object = SimpleDataObject::create();
        $object->Title = 'Old';
        $object->write();
        $object->Title = 'New';
        $event = DataObjectEvent::create($object, Operation::UPDATE, 3);

        $serialized = serialize($event);
        /** @var DataObjectEvent<SimpleDataObject> $unserialized */
        $unserialized = unserialize($serialized);

        $this->assertEquals($event->getChangedFields(), $unserialized->getChangedFields());
        $this->assertEquals('Old', $unserialized->getChangedFields()['Title']['before']);
        $this->assertTrue($unserialized->isChanged('Title'));
    }

    public function testUnserializeWithoutChangedFieldsDefaultsEmpty(): void
    {
        $object = SimpleDataObject::create();
        $object->Title = 'Test alpha';
        $object->write();
        $event = DataObjectEvent::create($object, Operation::CREATE, 3);

        $nativeData = $event->__serialize();
        unset($nativeData['changedFields']);
        $fromNative = (new \ReflectionClass(DataObjectEvent::class))->newInstanceWithoutConstructor();
        $fromNative->__unserialize($nativeData);
        $this->assertSame([], $fromNative->getChangedFields());
        $this->assertFalse($fromNative->isChanged('Title'));
        $this->assertEquals($event->getObjectID(), $fromNative->getObjectID());
    }

    public function testSerializeMethodIsDeprecated(): void
    {
        $object = SimpleDataObject::create();
        $object->Title = 'Test alpha';
        $object->write();
        $event = DataObjectEvent::create($object, Operation::CREATE, 3);

        $wasEnabled = Deprecation::isEnabled();
        Deprecation::enable();
        try {
            $this->flushDeprecationNotices();

            $serialized = $event->serialize();
            $payload = unserialize($serialized);
            $this->assertIsArray($payload);
            $restored = (new \ReflectionClass(DataObjectEvent::class))->newInstanceWithoutConstructor();
            $restored->__unserialize($payload);
            $this->assertEquals($event->getObjectID(), $restored->getObjectID());

            $this->assertDeprecationNoticeMatches('/instance serialize\(\) method/');
        } finally {
            if (!$wasEnabled) {
                Deprecation::disable();
            }
        }
    }

    public function testUnserializeMethodIsDeprecated(): void
    {
        $object = SimpleDataObject::create();
        $object->Title = 'Test alpha';
        $object->write();
        $event = DataObjectEvent::create($object, Operation::CREATE, 3);
        $payload = serialize($event->__serialize());

        $wasEnabled = Deprecation::isEnabled();
        Deprecation::enable();
        try {
            $this->flushDeprecationNotices();

            $restored = (new \ReflectionClass(DataObjectEvent::class))->newInstanceWithoutConstructor();
            $restored->unserialize($payload);
            $this->assertEquals($event->getObjectID(), $restored->getObjectID());
            $this->assertEquals($event->getChangedFields(), $restored->getChangedFields());

            $this->assertDeprecationNoticeMatches('/instance unserialize\(\) method/');
        } finally {
            if (!$wasEnabled) {
                Deprecation::disable();
            }
        }
    }

    public function testNativeSerializeStaysQuietWhenDeprecationsEnabled(): void
    {
        $object = SimpleDataObject::create();
        $object->Title = 'Test alpha';
        $object->write();
        $event = DataObjectEvent::create($object, Operation::CREATE, 3);

        $wasEnabled = Deprecation::isEnabled();
        Deprecation::enable();
        try {
            $this->flushDeprecationNotices();
            $restored = unserialize(serialize($event));
            $this->assertSame([], $this->flushDeprecationNotices());
            $this->assertEquals($event->getObjectID(), $restored->getObjectID());
        } finally {
            if (!$wasEnabled) {
                Deprecation::disable();
            }
        }
    }

    /**
     * Flush Silverstripe's deprecation buffer and capture E_USER_DEPRECATED messages.
     *
     * @return list<string>
     */
    private function flushDeprecationNotices(): array
    {
        $notices = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$notices): bool {
            if ($errno === E_USER_DEPRECATED) {
                $notices[] = $errstr;

                return true;
            }

            return false;
        });
        try {
            Deprecation::outputNotices();

            return $notices;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Assert the flushed deprecation buffer contains a notice matching $pattern.
     */
    private function assertDeprecationNoticeMatches(string $pattern): void
    {
        $notices = $this->flushDeprecationNotices();
        $matched = array_filter(
            $notices,
            static fn (string $notice): bool => (bool) preg_match($pattern, $notice)
        );
        $this->assertNotEmpty(
            $matched,
            'Expected a deprecation matching ' . $pattern . '; got: ' . implode('; ', $notices)
        );
    }
}
