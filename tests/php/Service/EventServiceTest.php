<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Tests\Service;

use ArchiPro\Silverstripe\EventDispatcher\Service\EventService;
use ArchiPro\Silverstripe\EventDispatcher\Tests\TestListenerLoader;
use Revolt\EventLoop;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class EventServiceTest extends SapphireTest
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testEventDispatch(): void
    {
        // Create test event
        $event = new class () {
            public bool $handled = false;
        };

        // Create event service with real implementations
        $service = Injector::inst()->get(EventService::class);

        // Add test listener
        $service->addListener(get_class($event), function ($event) {
            $event->handled = true;
        });

        // Dispatch event
        $result = $service->dispatch($event);

        EventLoop::run();

        // Assert listener was called
        $this->assertTrue($result->handled, 'Event listener should have been called');
    }

    public function testEventDispatchWithConfiguredListener(): void
    {
        // Create test event
        $event = new class () {
            public bool $handled = false;
        };
        // Configure listener via config
        $eventClass = get_class($event);
        EventService::config()->set('listeners', [
            $eventClass => [
                function ($event) {
                    $event->handled = true;
                },
            ],
        ]);

        // Get fresh service instance with config applied
        $service = Injector::inst()->get(EventService::class);

        // Dispatch event
        $result = $service->dispatch($event);

        EventLoop::run();

        // Assert listener was called
        $this->assertTrue($result->handled, 'Configured event listener should have been called');
    }

    public function testEventDispatchWithConfiguredLoader(): void
    {
        // Create test event
        $event = new class () {
            public bool $handled = false;
        };

        // Create test loader
        $loader = new TestListenerLoader(get_class($event));

        // Configure loader via config
        EventService::config()->set('loaders', [$loader]);

        // Get fresh service instance with config applied
        $service = Injector::inst()->get(EventService::class);
        $this->assertTrue($loader->loaded, 'Loader should have been used');

        // Dispatch event
        $result = $service->dispatch($event);

        EventLoop::run();

        // Assert loader was used and listener was called
        $this->assertTrue($loader->eventFired, 'Configured event listener should have been called');
    }
}
