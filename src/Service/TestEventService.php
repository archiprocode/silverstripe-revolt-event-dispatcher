<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Service;

use ArchiPro\EventDispatcher\AsyncEventDispatcher;
use ArchiPro\EventDispatcher\ListenerProvider;
use Closure;
use ColinODell\PsrTestLogger\TestLogger;
use SilverStripe\Core\Injector\Injector;
use Throwable;

/**
 * Extension of the AsyncEventDispatcher for testing purposes.
 *
 * This service will throw exceptions when errors occur to make it easier to debug issues.
 */
class TestEventService extends EventService
{
    private TestLogger $logger;

    public function __construct()
    {
        if (!class_exists(TestLogger::class)) {
            throw new \Exception(
                'To use the TestEventService, you must require the "colinodell/psr-testlogger" ' .
                'package in your dev dependencies.'
            );
        }

        $this->logger = new TestLogger();

        $listenerProvider = Injector::inst()->get(ListenerProvider::class);
        $dispatcher = new AsyncEventDispatcher(
            $listenerProvider,
            Closure::fromCallable([$this, 'recordError'])
        );
        parent::__construct($dispatcher, $listenerProvider);
    }

    /**
     * Bootstrap the TestEventService. Will replace the default EventService with a TestEventService.
     */
    public static function bootstrap(): self
    {
        $service = new self();
        Injector::inst()->registerService($service, AsyncEventDispatcher::class);
        return $service;
    }

    /**
     * Catch errors and store them for later inspection.
     */
    private function recordError(Throwable $message): void
    {
        $this->logger->error($message->getMessage(), ['exception' => $message]);
    }

    /**
     * Test logger where exception thrown by listeners are logged.
     */
    public function getTestLogger(): TestLogger
    {
        return $this->logger;
    }
}
