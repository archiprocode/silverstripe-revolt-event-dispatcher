<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Contract;

use ArchiPro\EventDispatcher\ListenerProvider;

/**
 * Interface for classes that load event listeners into a ListenerProvider.
 * 
 * This interface allows for modular and configurable loading of event listeners,
 * making it easier to organize and maintain event listeners in different parts
 * of the application.
 */
interface ListenerLoaderInterface
{
    /**
     * Loads event listeners into the provided ListenerProvider.
     * 
     * Implementations should use this method to register their event listeners
     * with the provider, typically using the provider's addListener method.
     * 
     * @param ListenerProvider $provider The provider to load listeners into
     */
    public function loadListeners(ListenerProvider $provider): void;
} 