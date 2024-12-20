<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Service;

use Amp\Future;
use ArchiPro\EventDispatcher\AsyncEventDispatcher;
use ArchiPro\EventDispatcher\ListenerProvider;
use ArchiPro\Silverstripe\EventDispatcher\Contract\ListenerLoaderInterface;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use Throwable;

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
     *
     * @var array<string,array<callable>> Map of event class names to arrays of listener callbacks
     */
    private static array $listeners = [];

    /**
     * @config
     *
     * @var array<ListenerLoaderInterface> Array of listener loaders
     */
    private static array $loaders = [];

    /** Whether events should be suppressed from being dispatched. Used for testing. */
    private bool $suppressDispatch = false;

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
                if (is_string($listener)) {
                    $listener = Injector::inst()->get($listener);
                }
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
            if (is_string($loader)) {
                $loader = Injector::inst()->get($loader);
            }
            $this->addListenerLoader($loader);
        }
    }

    /**
     * Adds a listener to the event service
     *
     * @template T of object
     *
     * @param class-string<T>   $event    The event class name
     * @param callable(T): void $listener The listener callback
     */
    public function addListener(string $event, callable $listener): void
    {
        $this->listenerProvider->addListener($event, $listener);
    }

    /**
     * Adds a listener loader to the event service
     *
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
     *
     * @template T of object
     *
     * @param T $event
     *
     * @return Future<T>
     */
    public function dispatch(object $event): Future
    {
        if ($this->suppressDispatch) {
            return Future::complete($event);
        }
        return $this->dispatcher->dispatch($event);
    }

    /**
     * Gets the listener provider instance
     */
    public function getListenerProvider(): ListenerProvider
    {
        return $this->listenerProvider;
    }

    /**
     * Enables event dispatching. Use when testing to avoid side effects.
     */
    public function enableDispatch(): void
    {
        $this->suppressDispatch = false;
    }

    /**
     * Disables event dispatching. Use when testing to avoid side effects.
     */
    public function disableDispatch(): void
    {
        $this->suppressDispatch = true;
    }

    /**
     * Handle an error that occurred during event dispatching by logging them
     * with the default Silverstripe CMS error handler logger.
     *
     * @internal This method is wired to the AsyncEventDispatcher with the Injector
     *
     * @see _config/events.yml
     */
    public static function handleError(Throwable $error): void
    {
        Injector::inst()
            ->get(LoggerInterface::class)
            ->error($error->getMessage(), ['exception' => $error]);
    }
}
