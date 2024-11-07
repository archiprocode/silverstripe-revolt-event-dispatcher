<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Tests\Event;

use SilverStripe\Dev\SapphireTest;
use ArchiPro\Silverstripe\EventDispatcher\Event\DataObjectWriteEvent;

class AbstractDataObjectEventTest extends SapphireTest
{
    public function testEventCreation(): void
    {
        $event = new DataObjectWriteEvent(
            1,
            'Page',
            'create',
            ['Title' => ['old' => null, 'new' => 'New Page']]
        );

        $this->assertEquals(1, $event->getObjectID());
        $this->assertEquals('Page', $event->getObjectClass());
        $this->assertEquals('create', $event->getAction());
        $this->assertArrayHasKey('Title', $event->getChanges());
    }

    public function testJsonSerialization(): void
    {
        $event = new DataObjectWriteEvent(
            1,
            'Page',
            'create',
            ['Title' => ['old' => null, 'new' => 'New Page']]
        );

        $json = json_encode($event);
        $data = json_decode($json, true);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('class', $data);
        $this->assertArrayHasKey('action', $data);
        $this->assertArrayHasKey('changes', $data);
        $this->assertArrayHasKey('timestamp', $data);
    }
} 