<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Tests;

use ArchiPro\EventDispatcher\ListenerProvider;
use ArchiPro\Silverstripe\EventDispatcher\Contract\ListenerLoaderInterface;

/**
 * This test loader will listen for the event provided in the constructor
 * and set the eventFired property to true when the event is fired.
 */
class TestListenerLoader implements ListenerLoaderInterface
{
    public bool $loaded = false;
    public bool $eventFired = false;

    public function __construct(
        private string $eventName
    ) {}

    public function loadListeners(ListenerProvider $provider): void
    {
        $this->loaded = true;
        $provider->addListener($this->eventName, [$this, 'handleEvent']);
    }

    public function handleEvent(object $event): void
    {
        $this->eventFired = true;
    }
}
