<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Event;

/**
 * Event class for DataObject deletion operations.
 * 
 * This event is fired before a DataObject is permanently deleted from the database.
 * It allows listeners to perform cleanup or logging operations before the deletion occurs.
 */
class DataObjectDeleteEvent extends AbstractDataObjectEvent
{
} 