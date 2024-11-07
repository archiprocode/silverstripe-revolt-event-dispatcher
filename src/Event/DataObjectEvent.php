<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Event;

use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;

/**
 * Event class representing operations performed on DataObjects.
 *
 * This event is dispatched whenever a significant operation occurs on a DataObject,
 * such as creation, updates, deletion, or versioning operations. It captures key
 * information about the operation including:
 *
 * - The ID of the affected DataObject
 * - The class of the DataObject
 * - The type of operation performed
 * - The version number (for versioned objects)
 * - The ID of the member who performed the operation
 * - The timestamp when the operation occurred
 *
 * Example usage:
 * ```php
 * $event = DataObjectEvent::create(
 *     $dataObject->ID,
 *     get_class($dataObject),
 *     Operation::UPDATE,
 *     $dataObject->Version,
 *     Security::getCurrentUser()?->ID
 * );
 * ```
 */
class DataObjectEvent
{
    use Injectable;

    /**
     * @var int Unix timestamp when the event was created
     */
    private readonly int $timestamp;

    /**
     * @param int       $objectID    The ID of the affected DataObject
     * @param string    $objectClass The class name of the affected DataObject
     * @param Operation $operation   The type of operation performed
     * @param int|null  $version     The version number (for versioned objects)
     * @param int|null  $memberID    The ID of the member who performed the operation
     */
    public function __construct(
        private readonly int $objectID,
        private readonly string $objectClass,
        private readonly Operation $operation,
        private readonly ?int $version = null,
        private readonly ?int $memberID = null
    ) {
        $this->timestamp = time();
    }

    /**
     * Get the ID of the affected DataObject
     */
    public function getObjectID(): int
    {
        return $this->objectID;
    }

    /**
     * Get the class name of the affected DataObject
     */
    public function getObjectClass(): string
    {
        return $this->objectClass;
    }

    /**
     * Get the type of operation performed
     */
    public function getOperation(): Operation
    {
        return $this->operation;
    }

    /**
     * Get the version number (for versioned objects)
     */
    public function getVersion(): ?int
    {
        return $this->version;
    }

    /**
     * Get the ID of the member who performed the operation
     */
    public function getMemberID(): ?int
    {
        return $this->memberID;
    }

    /**
     * Get the timestamp when the event was created
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Get the DataObject associated with this event
     *
     * @param bool $useVersion If true and the object is versioned, retrieves the specific version that was affected
     *                         Note: This may return null if the object has been deleted since the event was created
     */
    public function getObject(bool $useVersion = false): ?DataObject
    {
        if (!$this->objectID) {
            return null;
        }

        if (!$useVersion || empty($this->version)) {
            return DataObject::get_by_id($this->objectClass, $this->objectID, false);
        }

        return Versioned::get_version($this->objectClass, $this->objectID, $this->version);
    }

    /**
     * Get the Member who performed the operation
     *
     * Note: This may return null if the member has been deleted since the event was created
     * or if the operation was performed by a system process
     */
    public function getMember(): ?Member
    {
        if (!$this->memberID) {
            return null;
        }

        return Member::get()->byID($this->memberID);
    }

    /**
     * Serialize the event to a string
     */
    public function serialize(): string
    {
        return serialize([
            'objectID' => $this->objectID,
            'objectClass' => $this->objectClass,
            'operation' => $this->operation,
            'version' => $this->version,
            'memberID' => $this->memberID,
            'timestamp' => $this->timestamp,
        ]);
    }

    /**
     * Unserialize the event from a string
     *
     * @param string $data
     */
    public function unserialize(string $data): void
    {
        $unserialized = unserialize($data);

        // Use reflection to set readonly properties
        $reflection = new \ReflectionClass($this);

        foreach ($unserialized as $property => $value) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($this, $value);
        }
    }
}
