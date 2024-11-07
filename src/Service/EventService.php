<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Service;

use ArchiPro\EventDispatcher\AsyncEventDispatcher;
use ArchiPro\EventDispatcher\ListenerProvider;
use ArchiPro\Silverstripe\EventDispatcher\Contract\ListenerLoaderInterface;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Config\Configurable;

/**
 * Core service class for handling event dispatching in Silverstripe.
 * 
 * This service wraps a PSR-14 compliant event dispatcher and provides
 * a centralized way to dispatch events throughout the application.
 */
class EventService
{
    use Injectable;
    use Configurable;

    /**
     * @config
     * @var array<string,array<callable>> Map of event class names to arrays of listener callbacks
     */
    private static array $listeners = [];

    /**
     * @config
     * @var array<ListenerLoaderInterface> Array of listener loaders
     */
    private static array $loaders = [];

    public function __construct(
        private readonly AsyncEventDispatcher $dispatcher,
        private readonly ListenerProvider $listenerProvider
    ) {
        $this->registerListeners();
        $this->loadListeners();
    }

    /**
     * Registers listeners from the configuration
     */
    private function registerListeners(): void
    {
        $listeners = $this->config()->get('listeners');
        if (empty($listeners)) {
            return;
        }

        foreach ($listeners as $eventClass => $listeners) {
            foreach ($listeners as $listener) {
                $this->addListener($eventClass, $listener);
            }
        }
    }

    /**
     * Loads listeners from the configuration
     */
    private function loadListeners(): void
    {
        foreach ($this->config()->get('loaders') as $loader) {
            $this->addListenerLoader($loader);
        }
    }

    /**
     * Adds a listener to the event service
     */
    public function addListener(string $event, callable $listener): void
    {
        $this->listenerProvider->addListener($event, $listener);
    }

    /**
     * Adds a listener loader to the event service
     * @throws \RuntimeException If the loader does not implement ListenerLoaderInterface
     */
    public function addListenerLoader(ListenerLoaderInterface $loader): void
    {
        if (!$loader instanceof ListenerLoaderInterface) {
            throw new \RuntimeException(sprintf(
                'Listener loader class "%s" must implement ListenerLoaderInterface',
                get_class($loader)
            ));
        }
        $loader->loadListeners($this->listenerProvider);
    }

    /**
     * Dispatches an event to all registered listeners
     */
    public function dispatch(object $event): object
    {
        return $this->dispatcher->dispatch($event);
    }

    /**
     * Gets the listener provider instance
     */
    public function getListenerProvider(): ListenerProvider
    {
        return $this->listenerProvider;
    }
}