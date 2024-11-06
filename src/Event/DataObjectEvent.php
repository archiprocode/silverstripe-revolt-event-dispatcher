<?php

namespace YourVendor\Events\Event;

use SilverStripe\ORM\DataObject;

class DataObjectEvent
{
    private DataObject $dataObject;
    private string $action;

    public function __construct(DataObject $dataObject, string $action)
    {
        $this->dataObject = $dataObject;
        $this->action = $action;
    }

    public function getDataObject(): DataObject
    {
        return $this->dataObject;
    }

    public function getAction(): string
    {
        return $this->action;
    }
} 