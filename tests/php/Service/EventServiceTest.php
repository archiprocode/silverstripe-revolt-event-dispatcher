<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Tests\Service;

use ArchiPro\Silverstripe\EventDispatcher\Service\EventService;
use ArchiPro\Silverstripe\EventDispatcher\Tests\TestListenerLoader;
use Revolt\EventLoop;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class EventServiceTest extends SapphireTest
{
    private function getService(): EventService 
    {
        return Injector::inst()->get(EventService::class);
    }

    public function testEventDispatch(): void
    {
        // Create test event
        $event = new class () {
            public bool $handled = false;
        };

        $service = $this->getService();

        // Add test listener
        $service->addListener(get_class($event), function ($event) {
            $event->handled = true;
        });

        // Dispatch event
        $result = $service->dispatch($event)->await();

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

        $service = $this->getService();

        // Dispatch event
        $result = $service->dispatch($event)->await();

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

        $service = $this->getService();
        $this->assertTrue($loader->loaded, 'Loader should have been used');

        // Dispatch event
        $result = $service->dispatch($event);

        EventLoop::run();

        // Assert loader was used and listener was called
        $this->assertTrue($loader->eventFired, 'Configured event listener should have been called');
    }

    public function testEventDispatchWithDisabledDispatch(): void
    {
        // Create test event
        $event = new class () {
            public bool $handled = false;
        };

        $service = $this->getService();

        // Add test listener
        $service->addListener(get_class($event), function ($event) {
            $event->handled = true;
        });

        // Dispatch event
        $service->disableDispatch();
        $result = $service->dispatch($event)->await();

        // Assert listener was called
        $this->assertFalse($result->handled, 'Event listener should not have been called when dispatch is disabled');

        // Re-enabled dispatch
        $service->enableDispatch();
        $result = $service->dispatch($event)->await();

        // Assert listener was called
        $this->assertTrue($result->handled, 'Event listener should have been called when dispatch is re-enabled');
    }
}
