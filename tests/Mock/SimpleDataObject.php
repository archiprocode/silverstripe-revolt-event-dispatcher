<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Tests\Mock;

use ArchiPro\Silverstripe\EventDispatcher\Extension\EventDispatchExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * @property string $Title
 */
class SimpleDataObject extends DataObject implements TestOnly
{
    private static string $table_name = 'EventDispatcher_SimpleDataObject';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $extensions = [
        EventDispatchExtension::class,
    ];
} 