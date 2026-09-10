<?php

namespace ArchiPro\Silverstripe\EventDispatcher\Event;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Dev\Deprecation;
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
 * - A CHANGE_VALUE snapshot of $db / has_one fields that differed on the live object
 *
 * @template T of DataObject
 */
class DataObjectEvent
{
    use Injectable;

    /**
     * @var class-string<T>
     */
    private readonly string $objectClass;

    /**
     * @var int
     */
    private readonly int $objectID;

    /**
     * @var array<string,mixed>
     */
    private readonly array $record;

    /**
     * @var int|null
     */
    private readonly ?int $version;

    /**
     * @var int Unix timestamp when the event was created
     */
    private readonly int $timestamp;

    /**
     * Database-field changes at dispatch, captured at CHANGE_VALUE.
     *
     * A default empty map lets old native payloads omit this property on PHP 8.1.
     *
     * @var array<string, array{before: mixed, after: mixed, level: int}>
     */
    private array $changedFields = [];

    /**
     * @param T         $object    The DataObject this event relates to
     * @param Operation $operation The type of operation performed
     * @param int|null  $memberID  Member who performed the operation
     */
    public function __construct(
        DataObject $object,
        private readonly Operation $operation,
        private readonly ?int $memberID = null
    ) {
        $this->objectClass = get_class($object);
        $this->objectID = $object->ID;
        $this->record = $object->getQueriedDatabaseFields();
        // @phpstan-ignore property.notFound
        $this->version = $object->hasExtension(Versioned::class) ? $object->Version : null;
        $this->timestamp = time();
        $this->changedFields = $object->getChangedFields(true, DataObject::CHANGE_VALUE);
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
     *
     * @return class-string<T>
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
     *
     * @param bool $useVersion If true and the object is versioned, retrieves the specific version that was affected
     *                         Note: This may return null if the object has been deleted since the event was created
     *
     * @phpstan-return T|null
     */
    public function getObject(bool $useVersion = false): ?DataObject
    {
        if (!$this->objectID) {
            return null;
        }

        if (!$useVersion || empty($this->version)) {
            /** @var T|null $object */
            $object = DataObject::get($this->objectClass)->byID($this->objectID);
            return $object;
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
     * Get the record data at the time of the event
     *
     * @return array<string,mixed>
     */
    public function getRecord(): array
    {
        return $this->record;
    }

    /**
     * The fields that changed on this write, with before/after values.
     *
     * @return array<string, array{before: mixed, after: mixed, level: int}>
     */
    public function getChangedFields(): array
    {
        return $this->changedFields;
    }

    /**
     * Whether a field (or any field) changed on this write.
     *
     * The snapshot is always CHANGE_VALUE, so this does not take a $level argument.
     */
    public function isChanged(?string $fieldName = null): bool
    {
        if ($fieldName === null) {
            return $this->changedFields !== [];
        }

        return array_key_exists($fieldName, $this->changedFields);
    }

    /**
     * Serialize the event to a string
     *
     * @deprecated 0.3.0 Use PHP native serialize($event) instead
     */
    public function serialize(): string
    {
        Deprecation::notice(
            '0.3.0',
            'Use PHP native serialize($event) instead of the instance serialize() method',
            Deprecation::SCOPE_METHOD
        );

        return serialize($this->__serialize());
    }

    /**
     * Fields stored by PHP native serialize() and by serialize().
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'objectID' => $this->objectID,
            'objectClass' => $this->objectClass,
            'record' => $this->record,
            'operation' => $this->operation,
            'version' => $this->version,
            'memberID' => $this->memberID,
            'timestamp' => $this->timestamp,
            'changedFields' => $this->changedFields,
        ];
    }

    /**
     * Unserialize the event from a string
     *
     * @param string $data
     *
     * @deprecated 0.3.0 Use PHP native unserialize($string) instead
     */
    public function unserialize(string $data): void
    {
        Deprecation::notice(
            '0.3.0',
            'Use PHP native unserialize($string) instead of the instance unserialize() method',
            Deprecation::SCOPE_METHOD
        );

        $unserialized = unserialize($data);
        if (!is_array($unserialized)) {
            throw new \UnexpectedValueException('Serialized DataObjectEvent payload must be an array');
        }

        $this->hydrateFromSerialized($unserialized);
    }

    /**
     * Restore from PHP native serialize() payloads, including those without changedFields.
     *
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->hydrateFromSerialized($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrateFromSerialized(array $data): void
    {
        $reflection = new \ReflectionClass($this);

        foreach ($data as $property => $value) {
            if (!$reflection->hasProperty($property)) {
                continue;
            }

            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($this, $value);
        }
    }
}
