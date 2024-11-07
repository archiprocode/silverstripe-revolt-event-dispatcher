<?php

namespace ArchiPro\Silverstripe\EventDispatcher;

use ArchiPro\Silverstripe\EventDispatcher\Event\DataObjectEvent;
use ArchiPro\Silverstripe\EventDispatcher\Event\Operation;
use Closure;
use SilverStripe\ORM\DataObject;

/**
 * Event listener for DataObject events that filters events based on operation type and object class.
 *
 * This listener can be configured to only handle specific operations (create, update, delete etc)
 * and specific DataObject classes. When an event matches the configured criteria, the callback
 * is executed with the event.
 */
class DataObjectEventListener
{
    /**
     * Creates a new DataObject event listener.
     *
     * @param Closure(DataObjectEvent): void $callback   Callback to execute when an event matches
     * @param class-string<DataObject>[]     $classes    Array of DataObject class names to listen for
     * @param Operation[]|null               $operations Array of operations to listen for. If null, listens for all operations.
     */
    public function __construct(
        private Closure $callback,
        private array $classes,
        private ?array $operations = null
    ) {
        $this->operations = $operations ?? Operation::cases();
    }

    /**
     * Handles a DataObject event.
     *
     * Checks if the event matches the configured operations and classes,
     * and executes the callback if it does.
     *
     * @param DataObjectEvent $event The event to handle
     */
    public function __invoke(DataObjectEvent $event): void
    {
        // Check if we should handle this class
        if (!$this->shouldHandleClass($event->getObjectClass())) {
            return;
        }

        // Check if we should handle this operation
        if (!in_array($event->getOperation(), $this->operations)) {
            return;
        }

        // Execute callback
        call_user_func($this->callback, $event);
    }

    /**
     * Checks if the given class matches any of the configured target classes.
     *
     * A match occurs if the class is either the same as or a subclass of any target class.
     *
     * @param string $class The class name to check
     *
     * @return bool True if the class should be handled, false otherwise
     */
    private function shouldHandleClass(string $class): bool
    {
        foreach ($this->classes as $targetClass) {
            if (is_a($class, $targetClass, true)) {
                return true;
            }
        }
        return false;
    }
}
