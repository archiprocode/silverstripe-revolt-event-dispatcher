# Silverstripe CMS RevoltEvent Dispatcher Module (experimental)
[![CI](https://github.com/archiprocode/silverstripe-revolt-event-dispatcher/actions/workflows/ci.yml/badge.svg)](https://github.com/archiprocode/silverstripe-revolt-event-dispatcher/actions/workflows/ci.yml)

This module adds the ability to dispatch and listen for events in Silverstripe CMS. It's built around Revolt PHP
and AMPHP. It aims to process events asynchronously. It also provides some abstraction to help managing event around
common DataObject operations.

## Installation

```bash
composer require archipro/silverstripe-revolt-event-dispatcher
```

## Features
- Automatic event dispatching for DataObject operations (create, update, delete)
- Support for versioned operations (publish, unpublish, archive, restore)
- Asynchronous event handling using Revolt Event Loop

## Setting up the Event Loop

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

    // Many methods in Silverstripe CMS rely on having a current controller with a request.
    $controller = new Controller();
    $controller->setRequest($request);
    $controller->pushCurrent();

    // Now we can process the events in the event loop
    \Revolt\EventLoop::run();
}
```

### TODO

- Need to find a an elegant way to run the event loop on `sake` commands. This won't hit `public/index.php`.

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
    error_log('MyCustomEventListener::handleEvent was called');
});
```

### Configuration-based Listeners

You can register listeners via YAML configuration:

```yaml
ArchiPro\Silverstripe\EventDispatcher\Service\EventService:
  listeners:
    MyCustomEvent:
      - ['MyApp\EventListener', 'handleEvent']
```

## Registering many listeners at once with loaders

You can use listeners loaders to register many listeners at once.

```php
<?php

use ArchiPro\EventDispatcher\ListenerProvider;
use ArchiPro\Silverstripe\EventDispatcher\Contract\ListenerLoaderInterface;
use ArchiPro\Silverstripe\EventDispatcher\Event\DataObjectEvent;
use ArchiPro\Silverstripe\EventDispatcher\Event\Operation;
use ArchiPro\Silverstripe\EventDispatcher\Listener\DataObjectEventListener;
use SilverStripe\Control\Email\Email;
use SilverStripe\Security\Member;

class MemberListenerLoader implements ListenerLoaderInterface
{
    public function loadListeners(ListenerProvider $provider): void
    {
        DataObjectEventListener::create(
            Closure::fromCallable([self::class, 'onMemberCreated']),
            [Member::class],
            [Operation::CREATE]
        )->selfRegister($provider);
    }

    public static function onMemberCreated(DataObjectEvent $event): void
    {
        $member = $event->getObject();
        error_log('Member created: ' . $member->ID);
        Email::create()
            ->setTo($member->Email)
            ->setSubject('Welcome to our site')
            ->setFrom('no-reply@example.com')
            ->setBody('Welcome to our site')
            ->send();
    }
}
```

Loaders can be registered in your YAML configuration file:
```yaml
ArchiPro\Silverstripe\EventDispatcher\Service\EventService:
  loaders:
    - MemberListenerLoader
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

## Testing Your Events

### Writing Event Tests

When testing your event listeners, you'll need to:
1. Dispatch your events
2. Run the event loop
3. Assert the expected outcomes

Here's an example test:

```php
use Revolt\EventLoop;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Injector\Injector;
use ArchiPro\Silverstripe\EventDispatcher\Service\EventService;

class MyEventTest extends SapphireTest
{
    public function testMyCustomEvent(): void
    {
        // Create your test event
        $event = new MyCustomEvent('test message');
        
        // Get the event service
        $service = Injector::inst()->get(EventService::class);
        
        // Add your test listener ... or if you have already
        $wasCalled = false;
        $service->addListener(
            MyCustomEvent::class, 
            [MyCustomEventListener::class, 'handleEvent']
        );
        
        // Dispatch the event
        $service->dispatch($event);
        
        // Run the event loop to process events
        EventLoop::run();
        
        // Assert your listener was called
        $this->assertTrue(
            MyCustomEventListener::wasCalled(),
            'Assert some side effect of the event being handled'
        );
    }
}
```

### Disabling event dispatching

You can disable event dispatching for test to avoid side affects from irrelevant events that might be fired while 
scaffolding fixtures.

Call `EventService::singleton()->disableDispatch()` to disable event dispatching while setting up your test.

When you are ready to start running your test, call `EventService::singleton()->enableDispatch()` to start listening for
events again.

### Important Testing Notes

- Always remember to run `EventLoop::run()` after dispatching events
- Events are processed asynchronously, so assertions should happen after running the event loop
- For DataObject events, make sure your test class has the `EventDispatchExtension` applied
