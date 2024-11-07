<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Tests\Mock;

use ArchiPro\Silverstripe\EventDispatcher\Extension\EventDispatchExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @property string $Title
 *
 * @mixin Versioned
 */
class VersionedDataObject extends DataObject implements TestOnly
{
    private static string $table_name = 'EventDispatcher_VersionedDataObject';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $extensions = [
        EventDispatchExtension::class,
        Versioned::class,
    ];
}
