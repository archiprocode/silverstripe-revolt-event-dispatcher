<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Tests\Event;

use ArchiPro\Silverstripe\EventDispatcher\Event\DataObjectVersionEvent;
use SilverStripe\Dev\SapphireTest;

class DataObjectVersionEventTest extends SapphireTest
{
    public function testVersionEventCreation(): void
    {
        $event = new DataObjectVersionEvent(
            1,
            'Page',
            'publish',
            2,
            ['Title' => ['old' => 'Old Title', 'new' => 'New Title']]
        );

        $this->assertEquals(1, $event->getObjectID());
        $this->assertEquals('Page', $event->getObjectClass());
        $this->assertEquals('publish', $event->getAction());
        $this->assertEquals(2, $event->getVersion());
    }

    public function testVersionJsonSerialization(): void
    {
        $event = new DataObjectVersionEvent(
            1,
            'Page',
            'publish',
            2,
            []
        );

        $json = json_encode($event);
        $data = json_decode($json, true);

        $this->assertArrayHasKey('version', $data);
        $this->assertEquals(2, $data['version']);
    }
}
