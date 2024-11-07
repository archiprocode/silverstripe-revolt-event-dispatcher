<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Event;

/**
 * Represents the type of operation performed on a DataObject.
 * 
 * This enum is used to identify what kind of operation triggered a DataObjectEvent.
 * Each operation maps to a specific action in the Silverstripe CMS:
 * 
 * - CREATE: First time a DataObject is written to the database
 * - UPDATE: Subsequent writes to an existing DataObject
 * - DELETE: When a DataObject is deleted (both soft and hard deletes)
 * - PUBLISH: When a versioned DataObject is published to the live stage
 * - UNPUBLISH: When a versioned DataObject is removed from the live stage
 * - ARCHIVE: When a versioned DataObject is archived
 * - RESTORE: When a versioned DataObject is restored from archive
 */
enum Operation: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case PUBLISH = 'publish';
    case UNPUBLISH = 'unpublish';
    case ARCHIVE = 'archive';
    case RESTORE = 'restore';
} 