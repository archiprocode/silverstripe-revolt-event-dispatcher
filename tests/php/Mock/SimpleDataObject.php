<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Tests\Mock;

use ArchiPro\Silverstripe\EventDispatcher\Extension\EventDispatchExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Extension;

/**
 * @property string $Title
 */
class SimpleDataObject extends DataObject implements TestOnly
{
    
    private static string $table_name = 'EventDispatcher_SimpleDataObject';

    /** @var array<string, string> */
    private static array $db = [
        'Title' => 'Varchar',
    ];

    /** @var class-string<Extension<DataObject>>[] */
    private static array $extensions = [
        EventDispatchExtension::class,
    ];
}
