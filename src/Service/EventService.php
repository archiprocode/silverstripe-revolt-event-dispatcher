<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use SilverStripe\Core\Injector\Injectable;

/**
 * Core service class for handling event dispatching in Silverstripe.
 * 
 * This service wraps a PSR-14 compliant event dispatcher and provides
 * a centralized way to dispatch events throughout the application.
 * 
 * @property EventDispatcherInterface $dispatcher
 * @property ListenerProviderInterface $listenerProvider
 */
class EventService
{
    use Injectable;

    /** @var EventDispatcherInterface */
    private $dispatcher;

    /** @var ListenerProviderInterface */
    private $listenerProvider;

    /**
     * @param EventDispatcherInterface $dispatcher PSR-14 event dispatcher implementation
     * @param ListenerProviderInterface $listenerProvider PSR-14 listener provider implementation
     */
    public function __construct(
        EventDispatcherInterface $dispatcher,
        ListenerProviderInterface $listenerProvider
    ) {
        $this->dispatcher = $dispatcher;
        $this->listenerProvider = $listenerProvider;
    }

    /**
     * Dispatches an event to all registered listeners
     * 
     * @param object $event The event to dispatch
     * @return object The event after it has been processed by all listeners
     */
    public function dispatch(object $event): object
    {
        return $this->dispatcher->dispatch($event);
    }

    /**
     * Gets the listener provider instance
     * 
     * @return ListenerProviderInterface
     */
    public function getListenerProvider(): ListenerProviderInterface
    {
        return $this->listenerProvider;
    }
} 