<?php

declare(strict_types=1);

namespace BeadTests\Concurrent;

use Bead\Concurrent\SharedMemory;
use Bead\Exceptions\Concurrent\SharedMemoryException;
use Bead\Exceptions\Concurrent\SharedMemoryExtensionMissingException;
use BeadTests\Framework\TestCase;
use Bead\Testing\XRay;
use Bead\Util\ScopeGuard;
use InvalidArgumentException;
use JsonException;

class SharedMemoryTest extends TestCase
{
    /** @var int The ID of the test SharedMemory object. */
    private const SharedMemoryTestId = 0xBEAD7E57;

    /** @var int The size, in bytes, of the test SharedMemory object. */
    private const SharedMemoryTestSize = 100;

    /** @var SharedMemory The test SharedMemory object. */
    private SharedMemory $m_memory;

    /** @var array Any additional test-specific SharedMemory objects that need deleting at tear-down */
    private array $m_memories = [];

    /**
     * Initialise the test shared memmory object.
     */
    public function setUp(): void
    {
        try {
            $this->m_memory = SharedMemory::create(self::SharedMemoryTestSize, self::SharedMemoryTestId);
        } catch (SharedMemoryExtensionMissingException $err) {
            $this->markTestSkipped("Shared memory is not available.");
        }
    }

    /**
     * Ensure all SharedMemory objects created during testing are deleted.
     */
    public function tearDown(): void
    {
        if (!is_null(uopz_get_return("rand"))) {
            uopz_unset_return("rand");
        }

        $this->m_memory->delete();

        /** @var SharedMemory $memory */
        foreach ($this->m_memories as $memory) {
            try {
                $memory->delete();
            } catch (SharedMemoryException $err) {
            }
        }

        unset($this->m_memory, $this->m_memories);
    }

    public function testCreateWithId(): void
    {
        $memory = SharedMemory::create(10, 0x80808080);
        $this->m_memories[] = $memory;
        self::assertEquals(0x80808080, $memory->id());
    }

    public function testCreateThrows(): void
    {
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("Shared memory with id {$this->m_memory->id()} could not be created.");
        $this->m_memories[] = SharedMemory::create(100, self::SharedMemoryTestId);
    }

    public function testCreateGeneratesId(): void
    {
        $existingId = self::SharedMemoryTestId;
        $newId = 0x80808080;
        $called = false;

        uopz_set_return("rand", function() use ($existingId, $newId, &$called): int
        {
            if (!$called) {
                $called = true;
                return $existingId;
            }

            return $newId;
        }, true);

        $memory = SharedMemory::create(10);
        $this->m_memories[] = $memory;
        self::assertIsInt($memory->id());
        self::assertEquals($newId, $memory->id());
    }

    public function testOpen(): void
    {
        $memory = SharedMemory::open(self::SharedMemoryTestId);
        self::assertInstanceOf(SharedMemory::class, $memory);
        self::assertEquals(self::SharedMemoryTestId, $memory->id());
    }

    public function testOpenThrows(): void
    {
        $id = 0x80808080;
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("SharedMemory with ID {$id} could not be opened.");
        $memory = SharedMemory::open($id);
    }

    public function testId(): void
    {
        self::assertEquals(self::SharedMemoryTestId, $this->m_memory->id());
    }

    public function testIdWithClosed(): void
    {
        $memory = SharedMemory::create(100, 0x80808080);
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        $memory->close();
        self::assertEquals(0, $memory->id());
    }

    public function testIdWithDeleted(): void
    {
        $memory = SharedMemory::create(100, 0x80808080);
        $memory->delete();
        self::assertEquals(0, $memory->id());
    }

    public function testSize(): void
    {
        self::assertEquals(self::SharedMemoryTestSize, $this->m_memory->size());
    }

    public function testSizeWithClosed(): void
    {
        $memory = SharedMemory::create(100, 0x80808080);
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        $memory->close();
        self::assertEquals(0, $memory->size());
    }

    public function testSizeWithDeleted(): void
    {
        $memory = SharedMemory::create(100, 0x80808080);
        $memory->delete();
        self::assertEquals(0, $memory->size());
    }

    public function testIsOpen(): void
    {
        self::assertTrue($this->m_memory->isOpen());
    }

    public function testIsOpenWithClosed(): void
    {
        $memory = SharedMemory::create(100, 0x80808080);
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        $memory->close();
        self::assertFalse($memory->isOpen());
    }

    public function tesIsOpenWithDeleted(): void
    {
        $memory = SharedMemory::create(100, 0x80808080);
        $memory->delete();
        self::assertFalse($memory->isOpen());
    }

    public function testIsReadable(): void
    {
        self::assertTrue($this->m_memory->isReadable());
    }

    public function testIsReadableWithClosed(): void
    {
        $memory = SharedMemory::create(100, 0x80808080);
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        $memory->close();
        self::assertFalse($memory->isReadable());
    }

    public function tesIsReadableWithDeleted(): void
    {
        $memory = SharedMemory::create(100, 0x80808080);
        $memory->delete();
        self::assertFalse($memory->isReadable());
    }

    public function testIsWritable(): void
    {
        $memory = SharedMemory::open(self::SharedMemoryTestId, SharedMemory::ModeReadWrite);
        self::assertTrue($memory->isWritable());
    }

    public function testIsWritableWithClosed(): void
    {
        $memory = SharedMemory::create(100, 0x80808080);
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        $memory->close();
        self::assertFalse($memory->isWritable());
    }

    public function tesIsWritableWithDeleted(): void
    {
        $memory = SharedMemory::create(100, 0x80808080);
        $memory->delete();
        self::assertFalse($memory->isWritable());
    }

    public function testClose(): void
    {
        $memory = SharedMemory::create(100, 0x80808080);
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            $memory = SharedMemory::open(0x80808080);

            if (isset($memory)) {
                $memory->delete();
            }
        });

        $memory->close();
        self::assertFalse($memory->isOpen());
    }

    public function testDelete(): void
    {
        $id = 0x80808080;
        $memory = SharedMemory::create(10, $id);
        $this->m_memories[] = $memory;
        $memory->delete();
        self::assertFalse($memory->isOpen());

        // prove that the shared memory has actually been deleted, not just closed
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("SharedMemory with ID {$id} could not be opened.");
        $memory = SharedMemory::open($id);
    }

    public function testNullify(): void
    {
        $memory = new XRay(SharedMemory::create(10));
        $handle = $memory->m_handle;
        $memory->nullify();
        shmop_delete($handle);
        self::assertNull($memory->m_handle);
        self::assertEquals(0, $memory->id());
        self::assertEquals(0, $memory->size());
        self::assertEquals(SharedMemory::ModeRead, $memory->m_mode);
    }

    public function testReadInt64(): void
    {
        $value = -9187201950435737600;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, pack("q", $value), 0);
        $actual = $this->m_memory->readInt64();
        self::assertEquals($value, $actual);
    }

    public function testReadInt64WithOffset(): void
    {
        $value = -9187201950435737600;
        $offset = 10;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, pack("q", $value), $offset);
        $actual = $this->m_memory->readInt64($offset);
        self::assertEquals($value, $actual);
    }

    public function testReadInt64ThrowsWithClosed(): void
    {
        $value = -9187201950435737600;
        $memory = new XRay(SharedMemory::create(100, 0x80808080));
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        shmop_write($memory->m_handle, pack("q", $value), 0);
        $memory->close();
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("The shared memory is not readable.");
        $actual = $memory->readInt64(0);
    }

    public function testReadInt64ThrowsWithNegativeOffset(): void
    {
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readInt64($offset);
    }

    public function testReadInt64ThrowsWithInvalidOffset(): void
    {
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readInt64($offset);
    }

    public function testReadInt64ThrowsWithOverflow(): void
    {
        $offset = self::SharedMemoryTestSize - 7;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readInt64($offset);
    }

    public function testReadUInt64(): void
    {
        $value = 0x7fffffffffffffff;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, pack("Q", $value), 0);
        $actual = $this->m_memory->readUInt64();
        self::assertEquals($value, $actual);
    }

    public function testReadUInt64WithOffset(): void
    {
        $value = 0x7fffffffffffffff;
        $offset = 10;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, pack("Q", $value), $offset);
        $actual = $this->m_memory->readUInt64($offset);
        self::assertEquals($value, $actual);
    }

    public function testReadUInt64ThrowsWithClosed(): void
    {
        $value = 0x7fffffffffffffff;
        $memory = new XRay(SharedMemory::create(100, 0x80808080));
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        shmop_write($memory->m_handle, pack("Q", $value), 0);
        $memory->close();
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("The shared memory is not readable.");
        $actual = $memory->readUInt64(0);
    }

    public function testReadUInt64ThrowsWithNegativeOffset(): void
    {
        $value = 0x7fffffffffffffff;
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readUInt64($offset);
    }

    public function testReadUInt64ThrowsWithInvalidOffset(): void
    {
        $value = 0x7fffffffffffffff;
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readUInt64($offset);
    }

    public function testReadUInt64ThrowsWithOverflow(): void
    {
        $value = 0x7fffffffffffffff;
        $offset = self::SharedMemoryTestSize - 7;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readUInt64($offset);
    }

    public function testReadInt32(): void
    {
        $value = -2147483648;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, pack("i", $value), 0);
        $actual = $this->m_memory->readInt32();
        self::assertEquals($value, $actual);
    }

    public function testReadInt32WithOffset(): void
    {
        $value = -2147483648;
        $offset = 10;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, pack("i", $value), $offset);
        $actual = $this->m_memory->readInt32($offset);
        self::assertEquals($value, $actual);
    }

    public function testReadInt32ThrowsWithClosed(): void
    {
        $value = -2147483648;
        $memory = new XRay(SharedMemory::create(100, 0x80808080));
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        shmop_write($memory->m_handle, pack("i", $value), 0);
        $memory->close();
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("The shared memory is not readable.");
        $actual = $memory->readInt32(0);
    }

    public function testReadInt32ThrowsWithNegativeOffset(): void
    {
        $value = -2147483648;
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readInt32($offset);
    }

    public function testReadInt32ThrowsWithInvalidOffset(): void
    {
        $value = -2147483648;
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readInt32($offset);
    }

    public function testReadInt32ThrowsWithOverflow(): void
    {
        $value = -2147483648;
        $offset = self::SharedMemoryTestSize - 3;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readInt32($offset);
    }

    public function testReadUInt32(): void
    {
        $value = 0x7fffffff;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, pack("I", $value), 0);
        $actual = $this->m_memory->readUInt32();
        self::assertEquals($value, $actual);
    }

    public function testReadUInt32WithOffset(): void
    {
        $value = 0x7fffffff;
        $offset = 10;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, pack("I", $value), $offset);
        $actual = $this->m_memory->readUInt32($offset);
        self::assertEquals($value, $actual);
    }

    public function testReadUInt32ThrowsWithClosed(): void
    {
        $value = 0x7fffffff;
        $memory = new XRay(SharedMemory::create(100, 0x80808080));
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        shmop_write($memory->m_handle, pack("I", $value), 0);
        $memory->close();
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("The shared memory is not readable.");
        $actual = $memory->readUInt32(0);
    }

    public function testReadUInt32ThrowsWithNegativeOffset(): void
    {
        $value = 0x7fffffff;
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readUInt32($offset);
    }

    public function testReadUInt32ThrowsWithInvalidOffset(): void
    {
        $value = 0x7fffffff;
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readUInt32($offset);
    }

    public function testReadUInt32ThrowsWithOverflow(): void
    {
        $value = 0x7fffffff;
        $offset = self::SharedMemoryTestSize - 3;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readUInt32($offset);
    }

    public function testReadInt16(): void
    {
        $value = -32768;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, pack("s", $value), 0);
        $actual = $this->m_memory->readInt16();
        self::assertEquals($value, $actual);
    }

    public function testReadInt16WithOffset(): void
    {
        $value = -32768;
        $offset = 10;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, pack("s", $value), $offset);
        $actual = $this->m_memory->readInt16($offset);
        self::assertEquals($value, $actual);
    }

    public function testReadInt16ThrowsWithClosed(): void
    {
        $value = -32768;
        $memory = new XRay(SharedMemory::create(100, 0x80808080));
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        shmop_write($memory->m_handle, pack("s", $value), 0);
        $memory->close();
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("The shared memory is not readable.");
        $actual = $memory->readInt16(0);
    }

    public function testReadInt16ThrowsWithNegativeOffset(): void
    {
        $value = -32768;
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readInt16($offset);
    }

    public function testReadInt16ThrowsWithInvalidOffset(): void
    {
        $value = -32768;
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readInt16($offset);
    }

    public function testReadInt16ThrowsWithOverflow(): void
    {
        $value = -32768;
        $offset = self::SharedMemoryTestSize - 1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readInt16($offset);
    }

    public function testReadUInt16(): void
    {
        $value = 0x7fff;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, pack("S", $value), 0);
        $actual = $this->m_memory->readUInt16();
        self::assertEquals($value, $actual);
    }

    public function testReadUInt16WithOffset(): void
    {
        $value = 0x7fff;
        $offset = 10;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, pack("S", $value), $offset);
        $actual = $this->m_memory->readUInt16($offset);
        self::assertEquals($value, $actual);
    }

    public function testReadUInt16ThrowsWithClosed(): void
    {
        $value = 0x7fff;
        $memory = new XRay(SharedMemory::create(100, 0x80808080));
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        shmop_write($memory->m_handle, pack("S", $value), 0);
        $memory->close();
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("The shared memory is not readable.");
        $actual = $memory->readUInt16(0);
    }

    public function testReadUInt16ThrowsWithNegativeOffset(): void
    {
        $value = 0x7fff;
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readUInt16($offset);
    }

    public function testReadUInt16ThrowsWithInvalidOffset(): void
    {
        $value = 0x7fff;
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readUInt16($offset);
    }

    public function testReadUInt16ThrowsWithOverflow(): void
    {
        $value = 0x7fff;
        $offset = self::SharedMemoryTestSize - 1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readUInt16($offset);
    }

    public function testReadInt8(): void
    {
        $value = -128;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, pack("c", $value), 0);
        $actual = $this->m_memory->readInt8();
        self::assertEquals($value, $actual);
    }

    public function testReadInt8WithOffset(): void
    {
        $value = -128;
        $offset = 10;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, pack("c", $value), $offset);
        $actual = $this->m_memory->readInt8($offset);
        self::assertEquals($value, $actual);
    }

    public function testReadInt8ThrowsWithClosed(): void
    {
        $value = -128;
        $memory = new XRay(SharedMemory::create(100, 0x80808080));
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        shmop_write($memory->m_handle, pack("c", $value), 0);
        $memory->close();
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("The shared memory is not readable.");
        $actual = $memory->readInt8(0);
    }

    public function testReadInt8ThrowsWithNegativeOffset(): void
    {
        $value = -128;
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readInt8($offset);
    }

    public function testReadInt8ThrowsWithInvalidOffset(): void
    {
        $value = -128;
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readInt8($offset);
    }
    
    // NOTE 8-bit overflow test is identical to 8-bit invalid offset test, so omitted

    public function testReadUInt8(): void
    {
        $value = 0x7f;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, pack("C", $value), 0);
        $actual = $this->m_memory->readUInt8();
        self::assertEquals($value, $actual);
    }

    public function testReadUInt8WithOffset(): void
    {
        $value = 0x7f;
        $offset = 10;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, pack("C", $value), $offset);
        $actual = $this->m_memory->readUInt8($offset);
        self::assertEquals($value, $actual);
    }

    public function testReadUInt8ThrowsWithClosed(): void
    {
        $value = 0x7f;
        $memory = new XRay(SharedMemory::create(100, 0x80808080));
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        shmop_write($memory->m_handle, pack("C", $value), 0);
        $memory->close();
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("The shared memory is not readable.");
        $actual = $memory->readUInt8(0);
    }

    public function testReadUInt8ThrowsWithNegativeOffset(): void
    {
        $value = 0x7f;
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readUInt8($offset);
    }

    public function testReadUInt8ThrowsWithInvalidOffset(): void
    {
        $value = 0x7f;
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readUInt8($offset);
    }

    // NOTE 8-bit overflow test is identical to 8-bit invalid offset test, so omitted

    public function testReadString(): void
    {
        $value = "bead-framework";
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, $value, 0);
        $actual = $this->m_memory->readString(0, strlen($value));
        self::assertEquals($value, $actual);
    }

    public function testReadStringWithOffset(): void
    {
        $value = "bead-framework";
        $offset = 10;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, $value, $offset);
        $actual = $this->m_memory->readString($offset, strlen($value));
        self::assertEquals($value, $actual);
    }

    public function testReadStringWholeBlock(): void
    {
        $value = "bead-framework-bead-framework-bead-framework-bead-framework-bead-framework-bead-framework-framework-";
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, $value, 0);
        $actual = $this->m_memory->readString();
        self::assertEquals($value, $actual);
    }

    public function testReadStringWithNegativeOffset(): void
    {
        $value = "bead-framework";
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readString($offset);
    }

    public function testReadStringWithInvalidOffset(): void
    {
        $value = "bead-framework";
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readString($offset, 1);
    }

    public function testReadStringWithOverflow(): void
    {
        $value = "bead-framework";
        $offset = self::SharedMemoryTestSize - 1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readString($offset, 2);
    }

    public function testReadCString(): void
    {
        $value = "bead-framework\0";
        $expected = "bead-framework";
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, $value, 0);
        $actual = $this->m_memory->readCString(0);
        self::assertEquals($expected, $actual);
    }

    public function testReadCStringWithManyNulls(): void
    {
        $value = "bead-framework\0shared-memory\0";
        $expected = "bead-framework";
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, $value, 0);
        self::assertEquals($value, $this->m_memory->readString(0, strlen($value)));
        $actual = $this->m_memory->readCString(0);
        self::assertEquals($expected, $actual);
    }

    public function testReadCStringWithOffset(): void
    {
        $value = "bead-framework\0";
        $offset = 5;
        $expected = "framework";
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, $value, 0);
        $actual = $this->m_memory->readCString($offset);
        self::assertEquals($expected, $actual);
    }

    public function testReadCStringWithNegativeOffset(): void
    {
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readCString($offset);
    }

    public function testReadCStringWithInvalidOffset(): void
    {
        $offset = self::SharedMemoryTestSize + 1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readCString($offset);
    }

    public function testReadCStringWithOverflow(): void
    {
        $offset = self::SharedMemoryTestSize - 1;
        $memory = new XRay($this->m_memory);
        // ensure memory doesn't end with null byte, which would represent an empty string at the last offset
        shmop_write($memory->m_handle, "x", $offset);
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $actual = $this->m_memory->readCString($offset, 2);
    }

    public function testReadJson(): void
    {
        $value = ["bead" => "framework",];
        $json = json_encode($value);
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, $json, 0);
        $actual = $this->m_memory->readJson(0, strlen($json));
        self::assertEquals($value, $actual);
    }

    public function testReadJsonWithOffset(): void
    {
        $value = ["bead" => "framework",];
        $offset = 5;
        $json = json_encode($value);
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, $json, $offset);
        $actual = $this->m_memory->readJson($offset, strlen($json));
        self::assertEquals($value, $actual);
    }

    public function testReadJsonWithTruncatedSize(): void
    {
        $value = ["bead" => "framework",];
        $json = json_encode($value);
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, $json, 0);
        $this->expectException(JsonException::class);
        $this->m_memory->readJson(0, 2);
    }

    public function testReadJsonWithNegativeOffset(): void
    {
        $value = ["bead" => "framework",];
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $this->m_memory->readJson($offset);
    }

    public function testReadJsonWithInvalidOffset(): void
    {
        $value = ["bead" => "framework",];
        $offset = self::SharedMemoryTestSize + 1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $this->m_memory->readJson($offset);
    }

    public function testReadJsonWithOverflow(): void
    {
        $value = ["bead" => "framework",];
        $offset = self::SharedMemoryTestSize - 1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $this->m_memory->readJson($offset, 2);
    }
    
    public function testUnserialize(): void
    {
        $value = [1, 2, 3,];
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, serialize($value), 0);
        $actual = $this->m_memory->unserialize(0);
        self::assertEquals($value, $actual);
    }

    public function testUnserializeWithOffset(): void
    {
        $value = [1, 2, 3,];
        $offset = 5;
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, serialize($value), $offset);
        $actual = $this->m_memory->unserialize($offset);
        self::assertEquals($value, $actual);
    }

    public function testUnserializeWithTruncatedSize(): void
    {
        $value = [1, 2, 3,];
        $memory = new XRay($this->m_memory);
        shmop_write($memory->m_handle, serialize($value), 0);
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("The SharedMemory does not contain a serialized object in byte(s) 0 - 1.");
        $this->m_memory->unserialize(0, 2);
    }

    public function testUnserializeWithNegativeOffset(): void
    {
        $value = [1, 2, 3,];
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $this->m_memory->unserialize($offset);
    }

    public function testUnserializeWithInvalidOffset(): void
    {
        $value = [1, 2, 3,];
        $offset = self::SharedMemoryTestSize + 1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $this->m_memory->unserialize($offset);
    }

    public function testUnserializeWithOverflow(): void
    {
        $value = [1, 2, 3,];
        $offset = self::SharedMemoryTestSize - 1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't read outside bounds of SharedMemory.");
        $this->m_memory->unserialize($offset, 2);
    }

    public function testWriteInt64(): void
    {
        $value = -9187201950435737600;
        $this->m_memory->writeInt64($value);
        $memory = new XRay($this->m_memory);
        $actual = unpack("q", shmop_read($memory->m_handle, 0,8))[1];
        self::assertEquals($value, $actual);
    }

    public function testWriteInt64WithOffset(): void
    {
        $value = -9187201950435737600;
        $offset = 4;
        $this->m_memory->writeInt64($value, $offset);
        $memory = new XRay($this->m_memory);
        $actual = unpack("q", shmop_read($memory->m_handle, $offset,8))[1];
        self::assertEquals($value, $actual);
    }

    public function testWriteInt64ThrowsWithClosed(): void
    {
        $value = -9187201950435737600;
        $memory = SharedMemory::create(100, 0x80808080);
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        $memory->close();
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("The shared memory is not writable.");
        $actual = $memory->writeInt64(0, $value);
    }

    public function testWriteInt64ThrowsWithNegativeOffset(): void
    {
        $value = -9187201950435737600;
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeInt64($offset, $value);
    }

    public function testWriteInt64ThrowsWithInvalidOffset(): void
    {
        $value = -9187201950435737600;
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeInt64($offset, $value);
    }

    public function testWriteInt64ThrowsWithOverflow(): void
    {
        $value = -9187201950435737600;
        $offset = self::SharedMemoryTestSize - 7;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeInt64($offset, $value);
    }

    public function testWriteUInt64(): void
    {
        $value = 0x7fffffffffffffff;
        $this->m_memory->writeUInt64($value);
        $memory = new XRay($this->m_memory);
        $actual = unpack("Q", shmop_read($memory->m_handle, 0,8))[1];
        self::assertEquals($value, $actual);
    }

    public function testWriteUInt64WithOffset(): void
    {
        $value = 0x7fffffffffffffff;
        $offset = 4;
        $this->m_memory->writeUInt64($value, $offset);
        $memory = new XRay($this->m_memory);
        $actual = unpack("Q", shmop_read($memory->m_handle, $offset,8))[1];
        self::assertEquals($value, $actual);
    }

    public function testWriteUInt64ThrowsWithClosed(): void
    {
        $value = 0x7fffffffffffffff;
        $memory = SharedMemory::create(100, 0x80808080);
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        $memory->close();
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("The shared memory is not writable.");
        $actual = $memory->writeUInt64(0, $value);
    }

    public function testWriteUInt64ThrowsWithNegativeOffset(): void
    {
        $value = 0x7fffffffffffffff;
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeUInt64($offset, $value);
    }

    public function testWriteUInt64ThrowsWithInvalidOffset(): void
    {
        $value = 0x7fffffffffffffff;
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeUInt64($offset, $value);
    }

    public function testWriteUInt64ThrowsWithOverflow(): void
    {
        $value = 0x7fffffffffffffff;
        $offset = self::SharedMemoryTestSize - 7;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeUInt64($offset, $value);
    }

    public function testWriteInt32(): void
    {
        $value = -2147483648;
        $this->m_memory->writeInt32($value);
        $memory = new XRay($this->m_memory);
        $actual = unpack("i", shmop_read($memory->m_handle, 0,4))[1];
        self::assertEquals($value, $actual);
    }

    public function testWriteInt32WithOffset(): void
    {
        $value = -2147483648;
        $offset = 4;
        $this->m_memory->writeInt32($value, $offset);
        $memory = new XRay($this->m_memory);
        $actual = unpack("i", shmop_read($memory->m_handle, $offset,4))[1];
        self::assertEquals($value, $actual);
    }

    public function testWriteInt32ThrowsWithClosed(): void
    {
        $value = -2147483648;
        $memory = SharedMemory::create(100, 0x80808080);
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        $memory->close();
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("The shared memory is not writable.");
        $actual = $memory->writeInt32(0, $value);
    }

    public function testWriteInt32ThrowsWithNegativeOffset(): void
    {
        $value = -2147483648;
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeInt32($offset, $value);
    }

    public function testWriteInt32ThrowsWithInvalidOffset(): void
    {
        $value = -2147483648;
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeInt32($offset, $value);
    }

    public function testWriteInt32ThrowsWithOverflow(): void
    {
        $value = -2147483648;
        $offset = self::SharedMemoryTestSize - 3;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeInt32($offset, $value);
    }

    public function testWriteUInt32(): void
    {
        $value = 0x7fffffff;
        $this->m_memory->writeUInt32($value);
        $memory = new XRay($this->m_memory);
        $actual = unpack("I", shmop_read($memory->m_handle, 0,4))[1];
        self::assertEquals($value, $actual);
    }

    public function testWriteUInt32WithOffset(): void
    {
        $value = 0x7fffffff;
        $offset = 4;
        $this->m_memory->writeUInt32($value, $offset);
        $memory = new XRay($this->m_memory);
        $actual = unpack("I", shmop_read($memory->m_handle, $offset,4))[1];
        self::assertEquals($value, $actual);
    }

    public function testWriteUInt32ThrowsWithClosed(): void
    {
        $value = 0x7fffffff;
        $memory = SharedMemory::create(100, 0x80808080);
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        $memory->close();
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("The shared memory is not writable.");
        $actual = $memory->writeUInt32(0, $value);
    }

    public function testWriteUInt32ThrowsWithNegativeOffset(): void
    {
        $value = 0x7fffffff;
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeUInt32($offset, $value);
    }

    public function testWriteUInt32ThrowsWithInvalidOffset(): void
    {
        $value = 0x7fffffff;
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeUInt32($offset, $value);
    }

    public function testWriteUInt32ThrowsWithOverflow(): void
    {
        $value = 0x7fffffff;
        $offset = self::SharedMemoryTestSize - 3;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeUInt32($offset, $value);
    }

    public function testWriteInt16(): void
    {
        $value = -32768;
        $this->m_memory->writeInt16($value);
        $memory = new XRay($this->m_memory);
        $actual = unpack("s", shmop_read($memory->m_handle, 0,2))[1];
        self::assertEquals($value, $actual);
    }

    public function testWriteInt16WithOffset(): void
    {
        $value = -32768;
        $offset = 4;
        $this->m_memory->writeInt16($value, $offset);
        $memory = new XRay($this->m_memory);
        $actual = unpack("s", shmop_read($memory->m_handle, $offset,2))[1];
        self::assertEquals($value, $actual);
    }

    public function testWriteInt16ThrowsWithClosed(): void
    {
        $value = -32768;
        $memory = SharedMemory::create(100, 0x80808080);
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        $memory->close();
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("The shared memory is not writable.");
        $actual = $memory->writeInt16(0, $value);
    }

    public function testWriteInt16ThrowsWithNegativeOffset(): void
    {
        $value = -32768;
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeInt16($offset, $value);
    }

    public function testWriteInt16ThrowsWithInvalidOffset(): void
    {
        $value = -32768;
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeInt16($offset, $value);
    }

    public function testWriteInt16ThrowsWithOverflow(): void
    {
        $value = -32768;
        $offset = self::SharedMemoryTestSize - 1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeInt16($offset, $value);
    }

    public function testWriteUInt16(): void
    {
        $value = 0x7fff;
        $this->m_memory->writeUInt16($value);
        $memory = new XRay($this->m_memory);
        $actual = unpack("S", shmop_read($memory->m_handle, 0,4))[1];
        self::assertEquals($value, $actual);
    }

    public function testWriteUInt16WithOffset(): void
    {
        $value = 0x7fff;
        $offset = 4;
        $this->m_memory->writeUInt16($value, $offset);
        $memory = new XRay($this->m_memory);
        $actual = unpack("S", shmop_read($memory->m_handle, $offset,4))[1];
        self::assertEquals($value, $actual);
    }

    public function testWriteUInt16ThrowsWithClosed(): void
    {
        $value = 0x7fff;
        $memory = SharedMemory::create(100, 0x80808080);
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        $memory->close();
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("The shared memory is not writable.");
        $actual = $memory->writeUInt16(0, $value);
    }

    public function testWriteUInt16ThrowsWithNegativeOffset(): void
    {
        $value = 0x7fff;
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeUInt16($offset, $value);
    }

    public function testWriteUInt16ThrowsWithInvalidOffset(): void
    {
        $value = 0x7fff;
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeUInt16($offset, $value);
    }

    public function testWriteUInt16ThrowsWithOverflow(): void
    {
        $value = 0x7fff;
        $offset = self::SharedMemoryTestSize - 1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeUInt16($offset, $value);
    }

    public function testWriteInt8(): void
    {
        $value = -128;
        $this->m_memory->writeInt8($value);
        $memory = new XRay($this->m_memory);
        $actual = unpack("c", shmop_read($memory->m_handle, 0,2))[1];
        self::assertEquals($value, $actual);
    }

    public function testWriteInt8WithOffset(): void
    {
        $value = -128;
        $offset = 4;
        $this->m_memory->writeInt8($value, $offset);
        $memory = new XRay($this->m_memory);
        $actual = unpack("c", shmop_read($memory->m_handle, $offset,2))[1];
        self::assertEquals($value, $actual);
    }

    public function testWriteInt8ThrowsWithClosed(): void
    {
        $value = -128;
        $memory = SharedMemory::create(100, 0x80808080);
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        $memory->close();
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("The shared memory is not writable.");
        $actual = $memory->writeInt8(0, $value);
    }

    public function testWriteInt8ThrowsWithNegativeOffset(): void
    {
        $value = -128;
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeInt8($offset, $value);
    }

    public function testWriteInt8ThrowsWithInvalidOffset(): void
    {
        $value = -128;
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeInt8($offset, $value);
    }

    public function testWriteInt8ThrowsWithOverflow(): void
    {
        $value = -128;
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeInt8($offset, $value);
    }

    public function testWriteUInt8(): void
    {
        $value = 0x7f;
        $this->m_memory->writeUInt8($value);
        $memory = new XRay($this->m_memory);
        $actual = unpack("C", shmop_read($memory->m_handle, 0,4))[1];
        self::assertEquals($value, $actual);
    }

    public function testWriteUInt8WithOffset(): void
    {
        $value = 0x7f;
        $offset = 4;
        $this->m_memory->writeUInt8($value, $offset);
        $memory = new XRay($this->m_memory);
        $actual = unpack("C", shmop_read($memory->m_handle, $offset,4))[1];
        self::assertEquals($value, $actual);
    }

    public function testWriteUInt8ThrowsWithClosed(): void
    {
        $value = 0x7f;
        $memory = SharedMemory::create(100, 0x80808080);
        $this->m_memories[] = $memory;

        $guard = new ScopeGuard(function(): void {
            try {
                SharedMemory::open(0x80808080)->delete();
            }
            catch (SharedMemoryException $err) {
            }
        });

        $memory->close();
        $this->expectException(SharedMemoryException::class);
        $this->expectExceptionMessage("The shared memory is not writable.");
        $actual = $memory->writeUInt8(0, $value);
    }

    public function testWriteUInt8ThrowsWithNegativeOffset(): void
    {
        $value = 0x7fff;
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeUInt8($offset, $value);
    }

    public function testWriteUInt8ThrowsWithInvalidOffset(): void
    {
        $value = 0x7f;
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeUInt8($offset, $value);
    }

    public function testWriteUInt8ThrowsWithOverflow(): void
    {
        $value = 0x7f;
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $actual = $this->m_memory->writeUInt8($offset, $value);
    }

    public function testWriteString(): void
    {
        $value = "bead-framework";
        $this->m_memory->writeString($value);
        $memory = new XRay($this->m_memory);
        $actual = shmop_read($memory->m_handle, 0, strlen($value));
        self::assertEquals($value, $actual);
    }

    public function testWriteStringWithOffset(): void
    {
        $value = "bead-framework";
        $offset = 10;
        $this->m_memory->writeString($value, $offset);
        $memory = new XRay($this->m_memory);
        $actual = shmop_read($memory->m_handle, $offset, strlen($value));
        self::assertEquals($value, $actual);
    }

    public function testWriteStringWholeBlock(): void
    {
        $value = "bead-framework-bead-framework-bead-framework-bead-framework-bead-framework-bead-framework-framework-";
        $this->m_memory->writeString($value);
        $memory = new XRay($this->m_memory);
        $actual = shmop_read($memory->m_handle, 0, self::SharedMemoryTestSize);
        self::assertEquals($value, $actual);
    }

    public function testWriteStringWithNegativeOffset(): void
    {
        $value = "bead-framework";
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $this->m_memory->writeString($value, $offset);
    }

    public function testWriteStringWithInvalidOffset(): void
    {
        $value = "bead-framework";
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $this->m_memory->writeString($value, $offset);
    }

    public function testWriteStringWithOverflow(): void
    {
        $value = "bead-framework";
        $offset = self::SharedMemoryTestSize - 1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $this->m_memory->writeString($value, $offset);
    }

    public function testWriteCString(): void
    {
        $value = "bead-framework";
        $expected = "bead-framework\0";
        $this->m_memory->writeCString($value);
        $memory = new XRay($this->m_memory);
        $actual = shmop_read($memory->m_handle, 0, strlen($expected));
        self::assertEquals($expected, $actual);
    }

    public function testWriteCStringWithOffset(): void
    {
        $value = "bead-framework";
        $offset = 5;
        $expected = "bead-framework\0";
        $this->m_memory->writeCString($value, $offset);
        $memory = new XRay($this->m_memory);
        $actual = shmop_read($memory->m_handle, $offset, strlen($expected));
        self::assertEquals($expected, $actual);
    }

    public function testWriteCStringWithNegativeOffset(): void
    {
        $value = "bead-framework";
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $this->m_memory->writeCString($value, $offset);
    }

    public function testWriteCStringWithInvalidOffset(): void
    {
        $value = "bead-framework";
        $offset = self::SharedMemoryTestSize + 1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $this->m_memory->writeCString($value, $offset);
    }

    public function testWriteCStringWithOverflow(): void
    {
        $value = "bead-framework";
        $offset = self::SharedMemoryTestSize - 1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $this->m_memory->writeCString($value, $offset);
    }

    public function testWriteJson(): void
    {
        $value = ["bead" => "framework",];
        $actual = $this->m_memory->writeJson($value);
        $expected = json_encode($value);
        self::assertEquals(strlen($expected), $actual);
        self::assertEquals($expected, $this->m_memory->readString(0, strlen($expected)));
    }

    public function testWriteJsonWithOffset(): void
    {
        $value = ["bead" => "framework",];
        $offset = 5;
        $actual = $this->m_memory->writeJson($value, $offset);
        $expected = json_encode($value);
        self::assertEquals(strlen($expected), $actual);
        self::assertEquals($expected, $this->m_memory->readString($offset, strlen($expected)));
    }

    public function testWriteJsonWithNegativeOffset(): void
    {
        $value = ["bead" => "framework",];
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $this->m_memory->writeJson($offset, $offset);
    }

    public function testWriteJsonWithInvalidOffset(): void
    {
        $value = ["bead" => "framework",];
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $this->m_memory->writeJson($value, $offset);
    }

    public function testWriteJsonWithOverflow(): void
    {
        $value = ["bead" => "framework",];
        $offset = self::SharedMemoryTestSize - 1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $this->m_memory->writeJson($value, $offset);
    }

    public function testSerialize(): void
    {
        $value = [1, 2, 3,];
        $actual = $this->m_memory->serialize($value);
        $expected = serialize($value);
        self::assertEquals(strlen($expected), $actual);
        self::assertEquals($expected, $this->m_memory->readString(0, strlen($expected)));
    }

    public function testSerializeWithOffset(): void
    {
        $value = [1, 2, 3,];
        $offset = 5;
        $actual = $this->m_memory->serialize($value, $offset);
        $expected = serialize($value);
        self::assertEquals(strlen($expected), $actual);
        self::assertEquals($expected, $this->m_memory->readString($offset, strlen($expected)));
    }

    public function testSerializeWithNegativeOffset(): void
    {
        $value = [1, 2, 3,];
        $offset = -1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $this->m_memory->serialize($offset, $offset);
    }

    public function testSerializeWithInvalidOffset(): void
    {
        $value = [1, 2, 3,];
        $offset = self::SharedMemoryTestSize;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $this->m_memory->serialize($value, $offset);
    }

    public function testSerializeWithOverflow(): void
    {
        $value = [1, 2, 3,];
        $offset = self::SharedMemoryTestSize - 1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't write outside bounds of SharedMemory.");
        $this->m_memory->serialize($value, $offset);
    }
}
