<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Tests\Service;

use ArchiPro\Silverstripe\EventDispatcher\Service\TestEventService;
use Exception;
use Revolt\EventLoop;
use Revolt\EventLoop\UncaughtThrowable;
use SilverStripe\Dev\SapphireTest;

class TestEventServiceTest extends SapphireTest
{
    private TestEventService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = TestEventService::bootstrap();
    }

    public function testEventDispatchLogger(): void
    {
        // Create test event
        $event = new class () {};

        // Add test listener
        $this->service->addListener(get_class($event), function ($event) {
            throw new Exception('Test exception');
        });

        // Dispatch event
        $result = $this->service->dispatch($event);

        EventLoop::run();

        $this->assertCount(
            1,
            $this->service->getLogger()->records,
            'Running the event loop will cause an error to be logged'
        );
    }

    public function testEventDispatchThrow(): void
    {
        // Create test event
        $event = new class () {};

        // Add test listener
        $this->service->addListener(get_class($event), function ($event) {
            throw new Exception('Test exception');
        });

        $this->expectException(
            UncaughtThrowable::class,
            'Dispatching an event with a listener that throws an exception will throw an UncaughtThrowable'
        );

        // Dispatch event
        $result = $this->service->dispatch($event)->await();
    }
}
