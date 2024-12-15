<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Tests\Service;

use ArchiPro\Silverstripe\EventDispatcher\Service\TestEventService;
use Exception;
use Revolt\EventLoop;
use SilverStripe\Dev\SapphireTest;

class TestEventServiceTest extends SapphireTest
{
    private TestEventService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = TestEventService::bootstrap();
    }

    public function testGetTestLogger(): void
    {
        // Create test event
        $event = new class () {};

        // Add test listener
        $this->service->addListener(get_class($event), function ($event) {
            throw new Exception('Test exception');
        });

        $this->assertFalse(
            $this->service->getTestLogger()->hasErrorRecords(),
            'No exceptions have been thrown yet'
        );

        // Dispatch event
        $this->service->dispatch($event);

        EventLoop::run();

        $this->assertCount(
            1,
            $this->service->getTestLogger()->records,
            'Running the event loop will cause an error to be logged'
        );
    }
}
