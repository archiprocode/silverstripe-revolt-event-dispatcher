# Silverstripe CMS Event Dispatcher Module

This module adds the ability to dispatch and listen for events in Silverstripe CMS. It's built around Revolt PHP
and AMPHP. It aims to process events asynchronously. It also provides some abstraction to help managing event around
common DataObject operations.

## Installation

```bash
composer require archipro/silverstripe-revolt-event-dispatcher
```

## Running the Event Loop

Because we are using Revolt PHP, you need to run the event loop to process the events.

Somewhere in your code you need to start the event loop by running `\Revolt\EventLoop::run()`. This will process all the events up to that point.

A simple approach is to put it at the end of your `public/index.php` file in a `try-finally` block. You can also add a `fastcgi_finish_request()` call to ensure all output is sent before processing the events.

```php
try {
    $kernel = new CoreKernel(BASE_PATH);
    $app = new HTTPApplication($kernel);
    $response = $app->handle($request);
    $response->output();
} finally {
    // This call will complete the request without closing the PHP worker. A nice side effect of this is that your 
    // event listeners won't block your request from being sent to the client. So you can use them to run slow
    // operations like sending emails or doing API calls without delaying the response.
    fastcgi_finish_request();

    // Now we can process the events in the event loop
    \Revolt\EventLoop::run();
}
```

## Features
- Automatic event dispatching for DataObject operations (create, update, delete)
- Support for versioned operations (publish, unpublish, archive, restore)
- Asynchronous event handling using Revolt Event Loop


## Basic Usage

### Firing a Custom Event

```php
use SilverStripe\Core\Injector\Injector;
use ArchiPro\Silverstripe\EventDispatcher\Service\EventService;

// Create your event class
class MyCustomEvent
{
    public function __construct(
        private readonly string $message
    ) {}

    public function getMessage(): string
    {
        return $this->message;
    }
}

// Dispatch the event
$event = new MyCustomEvent('Hello World');
$service = Injector::inst()->get(EventService::class);
$service->dispatch($event);
```

### Adding a Simple Event Listener

```php
use SilverStripe\Core\Injector\Injector;
use ArchiPro\Silverstripe\EventDispatcher\Service\EventService;

// Add a listener
$service = Injector::inst()->get(EventService::class);
$service->addListener(MyCustomEvent::class, function(MyCustomEvent $event) {
    echo $event->getMessage();
});
```

### Configuration-based Listeners

You can register listeners via YAML configuration:

```yaml
ArchiPro\Silverstripe\EventDispatcher\Service\EventService:
  listeners:
    MyCustomEvent:
      - ['MyApp\EventListener,handleEvent']
```

## DataObject Event Handling

This module automatically dispatches events for DataObject operations. You can listen for these events using the 
`DataObjectEventListener` class.

### Firing DataObject Events

Applying the `EventDispatchExtension` to a DataObject will automatically fire events when changes are made to an 
instance of that DataObject.

```yaml

## This will fire events for SiteTree instances only
SilverStripe\SiteTree\SiteTree:
  extensions:
    - ArchiPro\Silverstripe\EventDispatcher\Extension\EventDispatchExtension

## This will fire events for all DataObjects
SilverStripe\ORM\DataObject:
  extensions:
    - ArchiPro\Silverstripe\EventDispatcher\Extension\EventDispatchExtension
```

### Listening for DataObject Events

```php
use ArchiPro\Silverstripe\EventDispatcher\Event\DataObjectEvent;
use ArchiPro\Silverstripe\EventDispatcher\Event\Operation;
use ArchiPro\Silverstripe\EventDispatcher\Listener\DataObjectEventListener;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Member;

// Create a listener for all Member operations
DataObjectEventListener::create(
    function (DataObjectEvent $event) {
        echo "Operation {$event->getOperation()->value} performed on Member {$event->getObjectID()}";
    },
    [Member::class]
)->selfRegister();

// Listen for specific operations on multiple classes
DataObjectEventListener::create(
    function (DataObjectEvent $event) {
        // Handle create/update operations
    },
    [Member::class, Group::class],
    [Operation::CREATE, Operation::UPDATE]
)->selfRegister();
```

### Available Operations

The following operations are automatically tracked:

- `Operation::CREATE` - When a DataObject is first written
- `Operation::UPDATE` - When an existing DataObject is modified
- `Operation::DELETE` - When a DataObject is deleted
- `Operation::PUBLISH` - When a versioned DataObject is published
- `Operation::UNPUBLISH` - When a versioned DataObject is unpublished
- `Operation::ARCHIVE` - When a versioned DataObject is archived
- `Operation::RESTORE` - When a versioned DataObject is restored from archive

### Accessing Event Data

The `DataObjectEvent` class provides several methods to access information about the event:

```php
DataObjectEventListener::create(
    function (DataObjectEvent $event) {
        $object = $event->getObject();        // Get the affected DataObject
        $class = $event->getObjectClass();    // Get the class name
        $operation = $event->getOperation();  // Get the operation type
        $version = $event->getVersion();      // Get version number (if versioned)
        $member = $event->getMember();        // Get the Member who performed the action
        $time = $event->getTimestamp();       // Get when the event occurred
    },
    [DataObject::class]
)->selfRegister();
```

`DataObjectEvent` is configured to be serializable so it can easily be stored for later use.

Note that `DataObjectEvent` doesn't store the actual DataObject instance that caused the event to be fired. 
`DataObjectEvent::getObject()` will refetch the latest version of the DataObject from the database ... which will 
return `null` if the DataObject has been deleted.

`DataObjectEvent::getObject(true) will attempt to retrieve the exact version of the DataObject that fired the event,
assuming it was versioned.