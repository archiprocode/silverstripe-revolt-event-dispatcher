<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Event;

/**
 * Event class for DataObject write operations.
 * 
 * This event is fired when a DataObject is:
 * - Created (first write)
 * - Updated (subsequent writes)
 * 
 * The event includes all changes made to the DataObject during the write operation.
 */
class DataObjectWriteEvent extends AbstractDataObjectEvent
{
} 