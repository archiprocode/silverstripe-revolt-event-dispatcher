<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Service;

use ArchiPro\EventDispatcher\AsyncEventDispatcher;
use ArchiPro\EventDispatcher\ListenerProvider;
use ColinODell\PsrTestLogger\TestLogger;
use SilverStripe\Core\Injector\Injector;

/**
 * Extension of the AsyncEventDispatcher for testing purposes.
 *
 * This service will throw exceptions when errors occur to make it easier to debug issues.
 */
class TestEventService extends EventService
{
    private ?TestLogger $logger = null;

    public function __construct()
    {
        $listenerProvider = Injector::inst()->get(ListenerProvider::class);

        // The test logger is useful but we don't want to force people to install it in production.
        if (class_exists(TestLogger::class)) {
            $this->logger = new TestLogger();
        }
        $dispatcher = new AsyncEventDispatcher($listenerProvider, $this->logger, AsyncEventDispatcher::THROW_ON_ERROR);
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
     * Return a logger that can be used to see if an array errors were thrown by the event loop.
     */
    public function getLogger(): TestLogger
    {
        if (!$this->logger) {
            throw new \RuntimeException(
                'To use the EventService\'s test logger, you must install colinodell/psr-testlogger. ' .
                '`composer require --dev colinodell/psr-testlogger`'
            );
        }
        return $this->logger;
    }
}
