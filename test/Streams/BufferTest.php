<?php

declare(strict_types=1);

namespace BeadTests\Streams;

use Bead\Streams\Buffer;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use RuntimeException;
use Throwable;

class BufferTest extends TestCase
{
    private const BufferContent = "the content of the buffer";

    private Buffer $buffer;

    public function setUp(): void
    {
        $this->buffer = new Buffer(self::BufferContent);
    }

    public function tearDown(): void
    {
        unset($this->buffer);
        parent::tearDown();
    }

    /** Ensure the constructor initialises the buffer as expected. */
    public function testConstructor1(): void
    {
        $buffer = new Buffer("some content");
        self::assertEquals(0, $buffer->tell());
        self::assertEquals("some content", (string) $buffer);
    }

    /** Ensure casting returns the full buffer stream is at the beginning, and seeks to the end. */
    public function testCastString1(): void
    {
        self::assertEquals(0, $this->buffer->tell());
        self::assertEquals(self::BufferContent, (string) $this->buffer);
        self::assertEquals(strlen(self::BufferContent), $this->buffer->tell());
    }

    /** Ensure casting returns the full buffer when the seek location is beyond the beginning, and seeks to the end. */
    public function testCastString2(): void
    {
        $this->buffer->seek(10);
        self::assertEquals(10, $this->buffer->tell());
        self::assertEquals(self::BufferContent, (string) $this->buffer);
        self::assertEquals(strlen(self::BufferContent), $this->buffer->tell());
    }

    /** Ensure casting returns the full buffer when eof(), and seeks to the end. */
    public function testCastString3(): void
    {
        $this->buffer->seek(0, SEEK_END);
        self::assertEquals(strlen(self::BufferContent), $this->buffer->tell());
        self::assertTrue($this->buffer->eof());
        self::assertEquals(self::BufferContent, (string) $this->buffer);
        self::assertEquals(strlen(self::BufferContent), $this->buffer->tell());
    }

    /** Ensure casting returns an empty string when the buffer is closed. */
    public function testCastString4(): void
    {
        $this->buffer->close();
        self::assertEquals("", (string) $this->buffer);
        self::assertFalse($this->buffer->isOpen());
    }
    
    /** Ensure calling close() closes the buffer. */
    public function testClose1(): void
    {
        self::assertTrue($this->buffer->isOpen());
        $this->buffer->close();
        self::assertFalse($this->buffer->isOpen());
    }

    /** Ensure detach() returns null. */
    public function testDetach1(): void
    {
        self::assertNull($this->buffer->detach());
    }
    
    /** Ensure isOpen() reports an open buffer as open. */
    public function testIsOpen1(): void
    {
        self::assertTrue($this->buffer->isOpen());
    }
    
    /** Ensure isOpen() reports a closed buffer as not open. */
    public function testIsOpen2(): void
    {
        $this->buffer->close();
        self::assertFalse($this->buffer->isOpen());
    }
    
    /** Ensure checkOpen() doesn't throw when the stream is open. */
    public function testCheckOpen1(): void
    {
        $threw = false;
        
        try {
            (new XRay($this->buffer))->checkOpen();
        } catch (Throwable) {
            $threw = true;
        }
        
        self::assertFalse($threw);
    }
    
    /** Ensure checkOpen() throws when the stream is closed. */
    public function testCheckOpen2(): void
    {
        $this->buffer->close();
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("The Buffer is not open");
        (new XRay($this->buffer))->checkOpen();
    }
    
    /** Ensure eof() is false when the buffer has not been exhausted */
    public function testEof1(): void
    {
        self::assertEquals(0, $this->buffer->tell());
        self::assertFalse($this->buffer->eof());
    }
    
    /** Ensure eof() is false when the buffer has been seeked partway through */
    public function testEof2(): void
    {
        $this->buffer->seek(10);
        self::assertEquals(10, $this->buffer->tell());
        self::assertFalse($this->buffer->eof());
    }

    /** Ensure eof() is true when the buffer has been seeked all the way through */
    public function testEof3(): void
    {
        $this->buffer->seek(0, SEEK_END);
        self::assertEquals(strlen(self::BufferContent), $this->buffer->tell());
        self::assertTrue($this->buffer->eof());
    }

    /** Ensure eof() is false when the buffer has been closed */
    public function testEof4(): void
    {
        $this->buffer->close();
        self::assertFalse($this->buffer->eof());
    }
    
    /** Ensure an open buffer is seekable. */
    public function testIsSeekable1(): void
    {
        self::assertTrue($this->buffer->isSeekable());
    }
    
    /** Ensure an EOF buffer is seekable. */
    public function testIsSeekable2(): void
    {
        $this->buffer->seek(0, SEEK_END);
        self::assertTrue($this->buffer->eof());
        self::assertTrue($this->buffer->isSeekable());
    }
    
    /** Ensure a closed buffer is not seekable. */
    public function testIsSeekable3(): void
    {
        $this->buffer->close();
        self::assertFalse($this->buffer->isSeekable());
    }
    
    /** Ensure an open buffer is seadable. */
    public function testIsReadable1(): void
    {
        self::assertTrue($this->buffer->isReadable());
    }
    
    /** Ensure an EOF buffer is readable. */
    public function testIsReadable2(): void
    {
        $this->buffer->seek(0, SEEK_END);
        self::assertTrue($this->buffer->eof());
        self::assertTrue($this->buffer->isReadable());
    }
    
    /** Ensure a closed buffer is not readable. */
    public function testIsReadable3(): void
    {
        $this->buffer->close();
        self::assertFalse($this->buffer->isReadable());
    }

    /** Ensure Buffers are not writable */
    public function testIsWritable1(): void
    {
        self::assertTrue($this->buffer->isOpen());
        self::assertFalse($this->buffer->isWritable());
    }

    /** Ensure we can get the size of an open buffer. */
    public function testGetSize1(): void
    {
        self::assertEquals(strlen(self::BufferContent), $this->buffer->getSize());
    }

    /** Ensure seeking doesn't change the size of an open buffer. */
    public function testGetSize2(): void
    {
        $this->buffer->seek(10);
        self::assertEquals(10, $this->buffer->tell());
        self::assertEquals(strlen(self::BufferContent), $this->buffer->getSize());
    }

    /** Ensure EOF change the size of an open buffer. */
    public function testGetSize3(): void
    {
        $this->buffer->seek(0, SEEK_END);
        self::assertEquals(strlen(self::BufferContent), $this->buffer->tell());
        self::assertTrue($this->buffer->eof());
        self::assertEquals(strlen(self::BufferContent), $this->buffer->getSize());
    }

    /** Ensure we get a null size when the buffer is closed. */
    public function testGetSize4(): void
    {
        $this->buffer->close();
        self::assertNull($this->buffer->getSize());
    }

    /** Ensure we can read the position in a buffer */
    public function testTell1(): void
    {
        self::assertEquals(0, $this->buffer->tell());
    }

    /** Ensure we can read the position in a buffer after seeking. */
    public function testTell2(): void
    {
        $this->buffer->seek(10);
        self::assertEquals(10, $this->buffer->tell());
    }

    /** Ensure we can read the position in a buffer after seeking to the end. */
    public function testTell3(): void
    {
        $this->buffer->seek(0, SEEK_END);
        self::assertEquals(strlen(self::BufferContent), $this->buffer->tell());
    }

    /** Ensure tell() throws when the buffer is closed. */
    public function testTell4(): void
    {
        $this->buffer->close();
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("The Buffer is not open");
        $this->buffer->tell();
    }

    public static function dataForTestSeek1(): iterable
    {
        yield "10 from beginning" => [0, 10, SEEK_CUR, 10,];
        yield "5 from 10" => [10, 5, SEEK_CUR, 15,];
        yield "-5 from 10" => [10, -5, SEEK_CUR, 5,];
        yield "-5 from end" => [strlen(self::BufferContent), -5, SEEK_CUR, strlen(self::BufferContent) - 5,];
        yield "set 10 from beginning" => [0, 10, SEEK_SET, 10,];
        yield "set 15 from 10" => [10, 15, SEEK_SET, 15,];
        yield "set 5 from 10" => [10, 5, SEEK_SET, 5,];
        yield "set 10 from end" => [strlen(self::BufferContent), 10, SEEK_SET, 10,];
        yield "set 0 from beginning" => [0, 0, SEEK_SET, 0,];
        yield "set 0 from 10" => [10, 0, SEEK_SET, 0,];
        yield "set 0 from end" => [strlen(self::BufferContent), 0, SEEK_SET, 0,];
        yield "end 0 from beginning" => [0, 0, SEEK_END, strlen(self::BufferContent),];
        yield "end -1 from beginning" => [0, -1, SEEK_END, strlen(self::BufferContent) - 1,];
        yield "end 0 from 10" => [10, 0, SEEK_END, strlen(self::BufferContent),];
        yield "end -1 from 10" => [10, -1, SEEK_END, strlen(self::BufferContent) - 1,];
        yield "end 0 from end" => [strlen(self::BufferContent), 0, SEEK_END, strlen(self::BufferContent),];
        yield "end -1 from end" => [strlen(self::BufferContent), -1, SEEK_END, strlen(self::BufferContent) - 1,];
    }

    /**
     * Ensure seeking ends up in the expected location.
     *
     * @dataProvider dataForTestSeek1
     */
    public function testSeek1(int $preOffset, int $offset, int $whence, int $expectedTell): void
    {
        if (0 < $preOffset) {
            $this->buffer->seek($preOffset);
        }

        self::assertEquals($preOffset, $this->buffer->tell());
        $this->buffer->seek($offset, $whence);
        self::assertEquals($expectedTell, $this->buffer->tell());
    }

    public static function dataForTestSeek2(): iterable
    {
        yield "empty string" => ["",];
        yield "whitespace" => ["  ",];
        yield "int string" => ["2",];
        yield "float string" => ["3.14",];
        yield "float" => [3.14,];
        yield "array" => [[10],];
        yield "true" => [true,];
        yield "false" => [false,];
        yield "null" => [null,];
        yield "object" => [(object) ["int" => 10],];
    }

    /**
     * Ensure seek() throws with non-int offset and SEEK_SET.
     *
     * @dataProvider dataForTestSeek2
     */
    public function testSeek2(mixed $offset): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("Expecting int offset for Argument #1 \$offset of seek(), found " . gettype($offset));
        $this->buffer->seek($offset, SEEK_SET);
    }

    /**
     * Ensure seek() throws with non-int offset and SEEK_CUR.
     *
     * @dataProvider dataForTestSeek2
     */
    public function testSeek3(mixed $offset): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("Expecting int offset for Argument #1 \$offset of seek(), found " . gettype($offset));
        $this->buffer->seek($offset, SEEK_CUR);
    }

    /**
     * Ensure seek() throws with non-int offset and SEEK_END.
     *
     * @dataProvider dataForTestSeek2
     */
    public function testSeek4(mixed $offset): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("Expecting int offset for Argument #1 \$offset of seek(), found " . gettype($offset));
        $this->buffer->seek($offset, SEEK_END);
    }

    /**
     * Ensure seek() throws with non-int whence.
     *
     * @dataProvider dataForTestSeek2
     */
    public function testSeek5(mixed $whence): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("Expecting SEEK_SET, SEEK_CUR or SEEK_END for Argument #2 \$whence of seek(), found " . gettype($whence));
        $this->buffer->seek(10, $whence);
    }

    /** Ensure seeking a closed Buffer throws. */
    public function testSeek6(): void
    {
        $this->buffer->close();
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("The Buffer is not open");
        $this->buffer->seek(1);
    }

    public static function dataForTestSeek7(): iterable
    {
        for ($idx = -10; $idx < 10; ++$idx) {
            if (SEEK_CUR === $idx || SEEK_SET === $idx || SEEK_END === $idx) {
                continue;
            }

            yield "{$idx}" => [$idx,];
        }
    }

    /**
     * Ensure invalid $whence ints throw.
     *
     * @dataProvider dataForTestSeek7
     */
    public function testSeek7(int $whence): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("Expecting SEEK_SET, SEEK_CUR or SEEK_END for Argument #2 \$whence of seek(), found {$whence}");
        $this->buffer->seek(1, $whence);
    }

    /** Ensure seeking beyond the end of the buffer throws. */
    public function testSeek8(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("Requested seek is beyond the bounds of the buffer");
        $this->buffer->seek(1, SEEK_END);
    }

    /** Ensure seeking beyond the beginning of the buffer throws. */
    public function testSeek9(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("Requested seek is beyond the bounds of the buffer");
        $this->buffer->seek(-1, SEEK_SET);
    }

    /** Ensure we can rewind to the start of the buffer. */
    public function testRewind1(): void
    {
        $this->buffer->seek(10);
        self::assertEquals(10, $this->buffer->tell());
        $this->buffer->rewind();
        self::assertEquals(0, $this->buffer->tell());
    }

    /** Ensure we can rewind to the start of the buffer from EOF. */
    public function testRewind2(): void
    {
        $this->buffer->seek(0, SEEK_END);
        self::assertTrue($this->buffer->eof());
        $this->buffer->rewind();
        self::assertEquals(0, $this->buffer->tell());
    }

    /** Ensure rewinding a closed buffer throws. */
    public function testRewind3(): void
    {
        $this->buffer->close();
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("The Buffer is not open");
        $this->buffer->rewind();
    }

    public static function dataForTestRead1(): iterable
    {
        yield "0 bytes from start" => [0, 0, "", 0,];
        yield "0 bytes from end" => [strlen(self::BufferContent), 0, "", strlen(self::BufferContent),];
        yield "0 bytes from middle" => [10, 0, "", 10,];
        yield "10 bytes from start" => [0, 10, substr(self::BufferContent, 0, 10), 10,];
        yield "10 bytes from end" => [strlen(self::BufferContent), 10, "", strlen(self::BufferContent),];
        yield "10 bytes from middle" => [10, 10, substr(self::BufferContent, 10, 10), 20,];
        yield "100 bytes from start" => [0, 100, self::BufferContent, strlen(self::BufferContent),];
        yield "100 bytes from end" => [strlen(self::BufferContent), 100, "", strlen(self::BufferContent),];
        yield "100 bytes from middle" => [10, 100, substr(self::BufferContent, 10), strlen(self::BufferContent),];

        for ($idx = 0; $idx < strlen(self::BufferContent); ++$idx) {
            yield "1 byte from offset {$idx}" => [$idx, 1, substr(self::BufferContent, $idx, 1), $idx + 1,];
        }

        yield "1 byte from penultimate position" => [strlen(self::BufferContent) - 1, 1, substr(self::BufferContent, -1), strlen(self::BufferContent),];

        yield "10 bytes from penultimate position" => [strlen(self::BufferContent) - 1, 1, substr(self::BufferContent, -1), strlen(self::BufferContent),];
    }

    /**
     * Ensure we read the expected content.
     * @dataProvider dataForTestRead1
     */
    public function testRead1(int $seek, int $length, string $expectedRead, int $expectedTell): void
    {
        $this->buffer->seek($seek);
        self::assertEquals($expectedRead, $this->buffer->read($length));
        self::assertEquals($expectedTell, $this->buffer->tell());
    }

    public static function dataForTestRead2(): iterable
    {
        yield "empty string" => ["",];
        yield "whitespace" => ["  ",];
        yield "int string" => ["2",];
        yield "float string" => ["3.14",];
        yield "float" => [3.14,];
        yield "array" => [[10],];
        yield "true" => [true,];
        yield "false" => [false,];
        yield "null" => [null,];
        yield "object" => [(object) ["int" => 10],];
    }

    /**
     * Ensure read() throws with non-int lengths.
     *
     * @dataProvider dataForTestRead2
     */
    public function testRead2(mixed $length): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("Expecting int >= 0 length for Argument #1 \$length of read(), found " . gettype($length));
        $this->buffer->read($length);
    }

    public static function dataForTestRead3(): iterable
    {
        yield "minus-1" => [-1];
        yield "minus-42" => [-42];
        yield "PHP_INT_MIN" => [PHP_INT_MIN];
    }

    /**
     * Ensure read() throws with negative lengths.
     *
     * @dataProvider dataForTestRead3
     */
    public function testRead3(int $length): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("Expecting int >= 0 length for Argument #1 \$length of read(), found {$length}");
        $this->buffer->read($length);
    }

    /** Ensure we get an empty string when reading a closed buffer. */
    public function testRead4(): void
    {
        $this->buffer->close();
        $actual = $this->buffer->read(1);
        self::assertIsString($actual);
        self::assertEquals("", $actual);
    }

    /** Ensure the metadata is null */
    public function testGetMetadata1(): void
    {
        self::assertNull($this->buffer->getMetadata());
    }

    public static function dataForTestGetMetadata2(): iterable
    {
        yield "empty-string" => [""];
        yield "whitespace" => [" "];

        for ($idx = 65; $idx < 91; ++$idx) {
            $ch = chr($idx);
            yield $ch => [$ch];
            $ch = strtolower($ch);
            yield $ch => [$ch];
        }

        yield "longer-text-key" => ["a-key"];
    }

    /**
     * Ensure the metadata is null when given a key.
     *
     * @dataProvider dataForTestGetMetadata2
     */
    public function testGetMetadata2(string $key): void
    {
        self::assertNull($this->buffer->getMetadata($key));
    }

    /** Ensure writing to a Buffer throws. */
    public function testWrite1(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("Buffers are read-only");
        $this->buffer->write("bead");
    }

    /** Ensure getContents() fetches the full buffer when positioned at the start. */
    public function testGetContents1(): void
    {
        self::assertEquals(0, $this->buffer->tell());
        self::assertEquals(self::BufferContent, $this->buffer->getContents());
        self::assertEquals(strlen(self::BufferContent), $this->buffer->tell());
        self::assertTrue($this->buffer->eof());
    }

    /** Ensure getContents() fetches the expected part of the buffer when partially seeked. */
    public function testGetContents2(): void
    {
        $this->buffer->seek(10);
        self::assertEquals(10, $this->buffer->tell());
        self::assertEquals(substr(self::BufferContent, 10), $this->buffer->getContents());
        self::assertEquals(strlen(self::BufferContent), $this->buffer->tell());
        self::assertTrue($this->buffer->eof());
    }

    /** Ensure getContents() returns an empty string when EOF. */
    public function testGetContents3(): void
    {
        $this->buffer->seek(0, SEEK_END);
        self::assertEquals(strlen(self::BufferContent), $this->buffer->tell());
        self::assertTrue($this->buffer->eof());
        $actual = $this->buffer->getContents();
        self::assertIsString($actual);
        self::assertEquals("", $actual);
        self::assertEquals(strlen(self::BufferContent), $this->buffer->tell());
        self::assertTrue($this->buffer->eof());
    }

    /** Ensure getContents() returns null when closed. */
    public function testGetContents4(): void
    {
        $this->buffer->close();
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("The Buffer is not open");
        $this->buffer->getContents();
    }
}
