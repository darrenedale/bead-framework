<?php

declare(strict_types=1);

namespace Bead\Concurrent;

use Bead\Exceptions\Concurrent\SharedMemoryException;
use Bead\Exceptions\Concurrent\SharedMemoryExtensionMissingException;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

use function shmop_close;
use function shmop_delete;
use function shmop_open;
use function shmop_read;
use function shmop_size;
use function shmop_write;

/**
 * Encapsulation of a block of memory that can be shared between processes.
 */
class SharedMemory
{
    /** @var int Constant indicating a shared memory object is open in read-only mode. */
    public const ModeRead = 1;

    /** @var int Constant indicating a shared memory object is open in read-write mode. */
    public const ModeReadWrite = 2;

    /** @var int The maximum number of attempst to find an unused ID for a new shared memory block before giving up. */
    private const MaxRandomIdAttempts = 100;

    /** @var int The unique ID for the shared memory block. */
    private int $m_id;

    /** @var Shmop|resource The opaque handle for the shared memory. */
    private $m_handle;

    /** @var int The size of the shared memory block, in bytes. */
    private int $m_size;

    /** @var int The access mode for the shared memory. */
    private int $m_mode;

    /**
     * Private constructor to initialise a SharedMemory object.
     *
     * Use one of the factory methods create() and open() to obtain instances.
     *
     * @param Shmop|resource $handle The shmop instance/resource.
     * @param int $id The ID of the shared memory block.
     * @param int $size The size of the shared memory block.
     * @param int $mode The access mode the SharedMemory instance has for the memory block.
     */
    private function __construct($handle, int $id, int $size, int $mode)
    {
        assert(extension_loaded("shmop"), new RuntimeException("SharedMemory requires the 'shmop' extension to be loaded."));
        $this->m_id = $id;
        $this->m_size = $size;
        $this->m_handle = $handle;
        $this->m_mode = $mode;
    }

    /**
     * Close the shared memory object on destruction.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Helper to check whether an ID is in use for an existing shared memory block.
     *
     * @param int $id The ID to check.
     *
     * @return bool `true` if the block exists, `false` otherwise.
     */
    private static function blockIdExists(int $id): bool
    {
        return false !== @shmop_open($id, "a", 0, 0);
    }

    /**
     * Create a new SharedMemory instance.
     *
     * The created shared memory can only be used by processes owned by the same user as that which created it.
     *
     * @param int $size The size of the memory block.
     * @param int|null $id Optional ID for the memory block. If not given, a random one will be generated.
     *
     * @return SharedMemory The created instance.
     */
    public static function create(int $size, ?int $id = null): SharedMemory
    {
        assert(extension_loaded("shmop"), new SharedMemoryExtensionMissingException("Shared memory is not available - shmop extension si not loaded."));
        assert(0 < $size, new InvalidArgumentException("SharedMemory size must be > 0."));

        if (isset($id)) {
            // shmop_open() will allow "creation" of an existing shared-memory block if the size and permissions match
            // so we need to check for this to forbid the use of semantic creation to merely open an exissting block
            if (self::blockIdExists($id)) {
                // block already exists
                $handle = false;
            } else {
                $handle = @shmop_open($id, "c", 0600, $size);
            }
        } else {
            $attempts = 0;
            $handle = false;

            do {
                $id = rand(0, PHP_INT_MAX);
                ++$attempts;

                if (!self::blockIdExists($id)) {
                    $handle = @shmop_open($id, "c", 0600, $size);
                }
            } while ($attempts < self::MaxRandomIdAttempts && false === $handle);
        }

        if (false === $handle) {
            throw new SharedMemoryException("Shared memory with id {$id} could not be created.");
        }

        return new SharedMemory($handle, $id, $size, self::ModeReadWrite);
    }

    /**
     * Open an existing shared memory block.
     *
     * @param int $id The ID of the block to access.
     * @param int $mode The access mode.
     *
     * @return SharedMemory The opened instance.
     */
    public static function open(int $id, int $mode = self::ModeRead): SharedMemory
    {
        assert(extension_loaded("shmop"), new SharedMemoryExtensionMissingException("Shared memory is not available - shmop extension si not loaded."));
        assert($mode === self::ModeRead || $mode === self::ModeReadWrite, new SharedMemoryException("Invalid open mode."));
        $handle = @shmop_open($id, (self::ModeReadWrite === $mode ? "w" : "a"), 0, 0);

        if (false === $handle) {
            throw new SharedMemoryException("SharedMemory with ID {$id} could not be opened.");
        }

        $size = shmop_size($handle);
        return new SharedMemory($handle, $id, $size, $mode);
    }

    /**
     * Fetch the ID of the shared memory block.
     *
     * @return int The ID.
     */
    public function id(): int
    {
        return $this->m_id;
    }

    /**
     * Fetch the size of the shared memory block.
     *
     * @return int The size, in bytes.
     */
    public function size(): int
    {
        return $this->m_size;
    }

    /**
     * Check whether the shared memory is accessible.
     *
     * @return bool `true` if it is open, `false` otherwise.
     */
    public function isOpen(): bool
    {
        return isset($this->m_handle);
    }

    /**
     * Check whether data can be read from the shared memory.
     *
     * @return bool `true` if it can be read from, `false` otherwise.
     */
    public function isReadable(): bool
    {
        return $this->isOpen();
    }

    /**
     * Check whether data can be written to the shared memory.
     *
     * @return bool `true` if it can be written to, `false` otherwise.
     */
    public function isWritable(): bool
    {
        return $this->isOpen() && self::ModeReadWrite === $this->m_mode;
    }

    /**
     * Close the shared memory object.
     *
     * After closing, no further reads or writes are possible with this object. This does not prevent other objects that
     * point to the same shared memory block from using it - the shared memory block still exists.
     */
    public function close(): void
    {
        if (!isset($this->m_handle)) {
            return;
        }

        @shmop_close($this->m_handle);
        $this->nullify();
    }

    /**
     * Delete the shared memory block.
     *
     * Any other objects using the shared memory block with the same ID will no longer be able to use it.
     */
    public function delete(): void
    {
        if (!isset($this->m_handle)) {
            throw new SharedMemoryException("SharedMemory can't be deleted as it's already been closed or deleted.");
        }

        @shmop_delete($this->m_handle);
        $this->nullify();
    }

    /**
     * Helper to nullify the state of the object when it is closed, deleted or has been detected as unusable.
     */
    private function nullify(): void
    {
        $this->m_handle = null;
        $this->m_id = 0;
        $this->m_size = 0;
        $this->m_mode = self::ModeRead;
    }

    /**
     * Helper to perform all reading from the memory.
     *
     * Implements bounds checking to protect against overflows.
     *
     * @param int $offset The offset to read from. Must be >= 0 and <= the length of the memory block less read size.
     * @param int|null $size The size of the read. If null or not given, the read is to the end of the memory block.
     *
     * @return string The bytes read.
     */
    private function read(int $offset = 0, ?int $size = null): string
    {
        if (!$this->isReadable()) {
            throw new SharedMemoryException("The shared memory is not readable.");
        }

        $size = $size ?? $this->size() - $offset;

        if (0 > $size || 0 > $offset || $this->size() < $offset + $size) {
            throw new InvalidArgumentException("Can't read outside bounds of SharedMemory.");
        }

        $str = shmop_read($this->m_handle, $offset, $size ?? ($this->size() - $offset));

        if (false === $str) {
            $this->nullify();
            throw new SharedMemoryException("The shared memory is no longer accessible.");
        }

        return $str;
    }

    /**
     * Helper to read a numeric value from the shared memory block.
     *
     * @param string $format The unpack() format.
     * @param int $offset The offset from which to read the value.
     * @param int $size The size of the value.
     * @return int|float The value.
     */
    private function readValue(string $format, int $offset, int $size)
    {
        return unpack($format, $this->read($offset, $size))[1];
    }

    /**
     * Read a 64-bit signed integer from the shared memory block.
     *
     * @param int $offset The offset within the shared memory block from which to read the int.
     *
     * @return int The int.
     */
    public function readInt64(int $offset = 0): int
    {
        return $this->readValue("q", $offset, 8);
    }

    /**
     * Read a 64-bit unsigned integer from the shared memory block.
     *
     * @param int $offset The offset within the shared memory block from which to read the int.
     *
     * @return int The int.
     */
    public function readUint64(int $offset = 0): int
    {
        return $this->readValue("Q", $offset, 8);
    }

    /**
     * Read a 32-bit signed integer from the shared memory block.
     *
     * @param int $offset The offset within the shared memory block from which to read the int.
     *
     * @return int The int.
     */
    public function readInt32(int $offset = 0): int
    {
        return $this->readValue("i", $offset, 4);
    }

    /**
     * Read a 32-bit unsigned integer from the shared memory block.
     *
     * @param int $offset The offset within the shared memory block from which to read the int.
     *
     * @return int The int.
     */
    public function readUint32(int $offset = 0): int
    {
        return $this->readValue("I", $offset, 4);
    }

    /**
     * Read a 16-bit signed integer from the shared memory block.
     *
     * @param int $offset The offset within the shared memory block from which to read the int.
     *
     * @return int The int.
     */
    public function readInt16(int $offset = 0): int
    {
        return $this->readValue("s", $offset, 2);
    }

    /**
     * Read a 16-bit unsigned integer from the shared memory block.
     *
     * @param int $offset The offset within the shared memory block from which to read the int.
     *
     * @return int The int.
     */
    public function readUint16(int $offset = 0): int
    {
        return $this->readValue("S", $offset, 2);
    }

    /**
     * Read an 8-bit signed integer from the shared memory block.
     *
     * @param int $offset The offset within the shared memory block from which to read the int.
     *
     * @return int The int.
     */
    public function readInt8(int $offset = 0): int
    {
        return $this->readValue("c", $offset, 1);
    }

    /**
     * Read an 8-bit unsigned integer from the shared memory block.
     *
     * @param int $offset The offset within the shared memory block from which to read the int.
     *
     * @return int The int.
     */
    public function readUint8(int $offset = 0): int
    {
        return $this->readValue("C", $offset, 1);
    }

    /**
     * Read a float from the shared memory block.
     *
     * @param int $offset The offset within the shared memory block from which to read the float.
     *
     * @return float The float.
     */
    public function readFloat(int $offset = 0): float
    {
        return $this->readValue("f", $offset, $this->size() - $offset);
    }

    /**
     * Read a double from the shared memory block.
     *
     * @param int $offset The offset within the shared memory block from which to read the double.
     *
     * @return float The double.
     */
    public function readDouble(int $offset = 0): float
    {
        return $this->readValue("d", $offset, $this->size() - $offset);
    }

    /**
     * Read a string from the shared memory block.
     *
     * If no size is given, the string from the offset to the end of the memory block will be returned.
     *
     * @param int $offset The offset from which to read the string.
     * @param int|null $size The optional number of bytes to read.
     *
     * @return string The string from the memory block.
     */
    public function readString(int $offset = 0, ?int $size = null): string
    {
        return $this->read($offset, $size);
    }

    /**
     * Read a c-style null-terminated string from the shared memory block.
     *
     * If no size is given, the string from the offset to the end of the memory block will be returned.
     *
     * @param int $offset The offset from which to read the string.
     *
     * @return string The string from the memory block (wihtout the null terminating byte).
     */
    public function readCString(int $offset = 0): string
    {
        $str = $this->read($offset);
        $end = strpos($str, "\0");

        if (false === $end) {
            throw new SharedMemoryException("Can't read outside bounds of SharedMemory.");
        }

        return substr($str, 0, $end);
    }

    /**
     * Read some JSON from the shared memory block, optionally from a given byte offset.
     *
     * @param int $offset The optional byte offset to read from. Default is the start of the block.
     * @param int|null $size The optional size to restrict the json read size to. Defaults to the distance from
     *   `$offset` to the end of the block.
     *
     * @return mixed The JSON read from the shared memory.
     * @throws JsonException if the content of the shared memory (at the offset) is not valid serializsed JSON.
     */
    public function readJson(int $offset = 0, ?int $size = null)
    {
        $data = $this->read($offset, $size);
        return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Unserialise a value from the shared memory block, optionally from a given byte offset.
     *
     * Do not use this with SharedMemory objects that contain user-supplied strings - unserializing data of utrusted
     * provenance is a security risk. Only use it when you need to share objects between processes and you know the
     * object was created by trusted code, and a JSON representation is not adequate.
     *
     * @template<T>
     *
     * @param int $offset The optional byte offset to read from. Default is the start of the block.
     * @param int|null $size The optional size to restrict the unserialisation read to.
     *
     * @return T The unserialised value.
     */
    public function unserialize(int $offset = 0, ?int $size = null)
    {
        $data = $this->read($offset, $size);
        $value = @unserialize($data);

        if (false === $value && $data !== serialize(false)) {
            $end = $offset + ($size ?? $this->size() - $offset) - 1;
            throw new SharedMemoryException("The SharedMemory does not contain a serialized object in byte(s) {$offset} - {$end}.");
        }

        return $value;
    }

    /**
     * Helper to perform all writing to the memory.
     *
     * Implements bounds checking to protect against overflows.
     *
     * @param string $bytes The bytes to write.
     * @param int $offset The offset to write to. Must be >= 0 and <= the length of the memory block less write size.
     * @param int|null $size The size of the write. If null or not given, the whole of the provided string is written.
     */
    private function write(string $bytes, int $offset = 0, ?int $size = null): void
    {
        if (!$this->isWritable()) {
            throw new SharedMemoryException("The shared memory is not writable.");
        }

        $effectiveSize = $size ?? strlen($bytes);

        if (0 > $offset || 0 > $effectiveSize || $this->size() < $offset + $effectiveSize) {
            throw new InvalidArgumentException("Can't write outside bounds of SharedMemory.");
        }

        $result = shmop_write($this->m_handle, (isset($size) ? substr($bytes, 0, $size) : $bytes), $offset);

        if (false === $result) {
            $this->nullify();
            throw new SharedMemoryException("The shared memory is no longer accessible.");
        }
    }

    /**
     * Helper to write a numeric value to the shared memory block.
     *
     * @param int|float $value The value to write.
     * @param string $format The pack() format.
     * @param int $offset The offset from which to write the value.
     */
    private function writeValue($value, string $format, int $offset): void
    {
        $this->write(pack($format, $value), $offset);
    }

    /**
     * Write a 64-bit signed integer to the shared memory block, optionally at a given byte offset.
     *
     * @param int $value The value to write.
     * @param int $offset The offset within the shared memory block from which to write the int.
     */
    public function writeInt64(int $value, int $offset = 0): void
    {
        $this->writeValue($value, "q", $offset);
    }

    /**
     * Write a 64-bit unsigned integer to the shared memory block, optionally at a given byte offset.
     *
     * @param int $value The value to write.
     * @param int $offset The offset within the shared memory block from which to write the int.
     */
    public function writeUint64(int $value, int $offset = 0): void
    {
        $this->writeValue($value, "Q", $offset, 8);
    }

    /**
     * Write a 32-bit signed integer to the shared memory block, optionally at a given byte offset.
     *
     * Only the least significant 32 bits of the provided value are used.
     *
     * @param int $value The value to write.
     * @param int $offset The offset within the shared memory block from which to write the int.
     */
    public function writeInt32(int $value, int $offset = 0): void
    {
        $this->writeValue($value & 0xffffffff, "i", $offset, 4);
    }

    /**
     * Write a 32-bit unsigned integer to the shared memory block, optionally at a given byte offset.
     *
     * Only the least significant 32 bits of the provided value are used.
     *
     * @param int $value The value to write.
     * @param int $offset The offset within the shared memory block from which to write the int.
     */
    public function writeUint32(int $value, int $offset = 0): void
    {
        $this->writeValue(($value & 0xffffffff), "I", $offset, 4);
    }

    /**
     * Write a 16-bit signed integer to the shared memory block, optionally at a given byte offset.
     *
     * Only the least significant 16 bits of the provided value are used.
     *
     * @param int $value The value to write.
     * @param int $offset The offset within the shared memory block at which to write the int.
     */
    public function writeInt16(int $value, int $offset = 0): void
    {
        $this->writeValue($value & 0xffff, "s", $offset, 2);
    }

    /**
     * Write a 16-bit unsigned integer to the shared memory block, optionally at a given byte offset.
     *
     * Only the least significant 16 bits of the provided value are used.
     *
     * @param int $value The value to write.
     * @param int $offset The offset within the shared memory block at which to write the int.
     */
    public function writeUint16(int $value, int $offset = 0): void
    {
        $this->writeValue($value & 0xffff, "S", $offset, 2);
    }

    /**
     * Write an 8-bit signed integer to the shared memory block, optionally at a given byte offset.
     *
     * Only the least significant 8 bits of the provided value are used.
     *
     * @param int $value The value to write.
     * @param int $offset The offset within the shared memory block at which to write the int.
     */
    public function writeInt8(int $value, int $offset = 0): void
    {
        $this->writeValue($value & 0xff, "c", $offset);
    }

    /**
     * Read an 8-bit unsigned integer to the shared memory block, optionally at a given byte offset.
     *
     * Only the least significant 8 bits of the provided value are used.
     *
     * @param int $value The value to write.
     * @param int $offset The offset within the shared memory block at which to write the int.
     */
    public function writeUint8(int $value, int $offset = 0): void
    {
        $this->writeValue($value & 0xff, "C", $offset);
    }

    /**
     * Write a float to the shared memory block, optionally at a given byte offset.
     *
     * @param float $value The value to write.
     * @param int $offset The offset within the shared memory block at which to write the float.
     */
    public function writeFloat(float $value, int $offset = 0): void
    {
        $this->writeValue($value, "f", $offset);
    }

    /**
     * Write a double to the shared memory block, optionally at a given byte offset.
     *
     * @param float $value The value to write.
     * @param int $offset The offset within the shared memory block at which to write the double.
     */
    public function writeDouble(float $value, int $offset = 0): void
    {
        $this->writeValue($value, "d", $offset);
    }

    /**
     * Write a string from the shared memory block, optionally at a given byte offset.
     *
     * If no size is given, the string from the offset to the end of the memory block will be returned.
     *
     * @param int $offset The offset from which to read the string.
     * @param int|null $size The optional number of bytes to read.
     */
    public function writeString(string $str, int $offset = 0, ?int $size = null): void
    {
        $this->write($str, $offset, $size);
    }

    /**
     * Write a c-style null-terminated string to the shared memory block, optionally at a given byte offset.
     *
     * The string's full content is written to the shared memory at the given offset, followed by a null byte.
     *
     * @param string $str The string to write.
     * @param int $offset The offset at which to write the string.
     */
    public function writeCString(string $str, int $offset = 0): void
    {
        $str = $this->write("{$str}\0", $offset);
    }

    /**
     * Write some JSON to the shared memory block, optionally at a given byte offset.
     *
     * @param mixed $value The value to write.
     * @param int $offset The optional byte offset to read from. Default is the start of the block.
     *
     * @return int The number of bytes written for the JSON representation of the value.
     */
    public function writeJson($value, int $offset = 0): int
    {
        $data = json_encode($value, JSON_THROW_ON_ERROR);
        $this->write($data, $offset);
        return strlen($data);
    }

    /**
     * Serialise a value to the shared memory, optionally at a given offset.
     *
     * @template<T>
     *
     * @param T $value The value to serialise.
     * @param int $offset The byte offset at which to store the serialisation. Defaults to 0.
     *
     * @return int The number of bytes written for the serialised value.
     */
    public function serialize($value, int $offset = 0): int
    {
        $data = serialize($value);
        $this->write($data, $offset);
        return strlen($data);
    }
}
