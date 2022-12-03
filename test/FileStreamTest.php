<?php

namespace BeadTests;

use Bead\Exceptions\FileStreamException;
use Bead\FileStream;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use InvalidArgumentException;
use SplFileInfo;

class FileStreamTest extends TestCase
{

    private const ReadFileName = __DIR__ . "/files/filestreamtest-file01.txt";
    private const WriteFileName = __DIR__ . "/files/filestreamtest-file02.txt";

    private FileStream $m_stream;

    public function setUp(): void
    {
        $this->m_stream = new FileStream(self::ReadFileName, FileStream::ModeRead);
    }

    public function tearDown(): void
    {
        if (file_exists(self::WriteFileName)) {
            @unlink(self::WriteFileName);
        }

        unset($this->m_stream);
    }

    /**
     * Ensure a stream can be created using an SplFileInfo object.
     */
    public function testConstructorFileInfo(): void
    {
        $stream = new FileStream(new SplFileInfo(self::ReadFileName), FileStream::ModeRead);
        $this->assertTrue($stream->isReadable());
        $this->assertFalse($stream->isWritable());
    }

    /**
     * Ensure a read-only stream can be created.
     */
    public function testConstructorReadOnly(): void
    {
        $stream = new FileStream(self::ReadFileName, FileStream::ModeRead);
        $this->assertTrue($stream->isReadable());
        $this->assertFalse($stream->isWritable());
    }

    /**
     * Ensure a read-write stream can be created.
     */
    public function testConstructorReadWrite(): void
    {
        file_put_contents(self::WriteFileName, '');
        $stream = new FileStream(self::WriteFileName, FileStream::ModeRead | FileStream::ModeWrite);
        $this->assertTrue($stream->isReadable());
        $this->assertTrue($stream->isWritable());
    }

    /**
     * Ensure a write-only stream can be creatd.
     */
    public function testConstructorWriteOnly(): void
    {
        file_put_contents(self::WriteFileName, '');
        $stream = new FileStream(self::WriteFileName, FileStream::ModeWrite);
        $this->assertFalse($stream->isReadable());
        $this->assertTrue($stream->isWritable());
    }

    /**
     * Ensure the constructor throws when an invalid mode is provided.
     */
    public function testConstructorBadMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid stream open mode 0x00000000");
        $stream = new FileStream(self::ReadFileName, 0);
    }

    /**
     * Ensure the constructor throws when a non-existent file is opened for reading.
     */
    public function testConstructorBadFile(): void
    {
        $this->expectException(FileStreamException::class);
        $this->expectExceptionMessage("Could not open file " . __DIR__ . "/files/filestreamtest-this-file-does-not-exist.txt.");
        $stream = new FileStream(__DIR__ . "/files/filestreamtest-this-file-does-not-exist.txt", FileStream::ModeRead);
    }

    /**
     * Ensure seeking uses SEEK_SET by default.
     */
    public function testSeekDefault(): void
    {
        $this->m_stream->read(1);
        $this->assertEquals(1, $this->m_stream->tell());
        $this->m_stream->seek(3);
        $this->assertEquals(3, $this->m_stream->tell());
    }

    /**
     * Ensure a stream can be seeked from the current position.
     */
    public function testSeekFromCurrent(): void
    {
        $this->m_stream->read(1);
        $this->assertEquals(1, $this->m_stream->tell());
        $this->m_stream->seek(3, SEEK_CUR);
        $this->assertEquals(4, $this->m_stream->tell());
    }

    /**
     * Ensure a stream can be seeked relative to the beginning of the file.
     */
    public function testSeekFromBeginning(): void
    {
        $this->m_stream->read(1);
        $this->assertEquals(1, $this->m_stream->tell());
        $this->m_stream->seek(3, SEEK_SET);
        $this->assertEquals(3, $this->m_stream->tell());
    }

    /**
     * Ensure a stream can be seeked relative to the end of the file.
     */
    public function testSeekFromEnd(): void
    {
        $this->m_stream->read(1);
        $this->assertEquals(1, $this->m_stream->tell());
        $this->m_stream->seek(-3, SEEK_END);
        $this->assertEquals(35, $this->m_stream->tell());
    }

    /**
     * Ensure seeking a closed stream throws an exception.
     */
    public function testSeekThrowsWhenClosed(): void
    {
        $this->m_stream->close();
        $this->expectException(FileStreamException::class);
        $this->expectExceptionMessage("The stream is not open.");
        $this->m_stream->seek(1);
    }

    /**
     * Ensure seeking a closed stream throws an exception.
     */
    public function testSeekThrowsWhenDetached(): void
    {
        $this->m_stream->detach();
        $this->expectException(FileStreamException::class);
        $this->expectExceptionMessage("The stream is not open.");
        $this->m_stream->seek(1);
    }

    /**
     * Ensure detaching the underlying resource from the stream renders it unusable.
     */
    public function testDetach()
    {
        $stream = new Xray($this->m_stream);
        $expectedFh = $stream->m_fh;
        $fh = $this->m_stream->detach();
        $this->assertSame($expectedFh, $fh);
        $this->assertNull($stream->m_fh);
        $this->assertEquals(0, $stream->m_mode);
        $this->assertEquals("", $stream->m_fileName);
    }

    /**
     * Ensure detaching the underlying resource from the stream throws when it's closed.
     */
    public function testDetachThrowsWhenClosed()
    {
        $this->m_stream->close();
        $this->expectException(FileStreamException::class);
        $this->expectExceptionMessage("The stream is not open.");
        $this->m_stream->detach();
    }

    /**
     * Ensure detaching the underlying resource from the stream throws when it's already been detached.
     */
    public function testDetachThrowsWhenDetached()
    {
        $this->m_stream->detach();
        $this->expectException(FileStreamException::class);
        $this->expectExceptionMessage("The stream is not open.");
        $this->m_stream->detach();
    }

    /**
     * Ensure a read-only stream reports it is not writable.
     */
    public function testIsNotWritable()
    {
        $this->assertFalse($this->m_stream->isWritable());
    }

    /**
     * Ensure a writable stream reports it is writable.
     */
    public function testIsWritable()
    {
        $stream = new FileStream(self::WriteFileName, FileStream::ModeWrite);
        $this->assertTrue($stream->isWritable());
    }

    /**
     * Ensure a closed stream reports it is not writable.
     */
    public function testClosedIsNotWritable()
    {
        $stream = new FileStream(self::WriteFileName, FileStream::ModeWrite);
        $this->assertTrue($stream->isWritable());
        $stream->close();
        $this->assertFalse($stream->isWritable());
    }

    /**
     * Ensure a detached stream reports it is not writable.
     */
    public function testDetachedIsNotWritable()
    {
        $stream = new FileStream(self::WriteFileName, FileStream::ModeWrite);
        $this->assertTrue($stream->isWritable());
        $stream->detach();
        $this->assertFalse($stream->isWritable());
    }

    /**
     * Ensure the correct filename is reported.
     */
    public function testFileName()
    {
        $this->assertEquals(self::ReadFileName, $this->m_stream->fileName());
    }

    /**
     * Ensure an empty filename is reported when closed.
     */
    public function testFileNameWhenClosed()
    {
        $this->m_stream->close();
        $this->assertEquals("", $this->m_stream->fileName());
    }

    /**
     * Ensure an empty filename is reported when detached.
     */
    public function testFileNameWhenDetached()
    {
        $this->m_stream->detach();
        $this->assertEquals("", $this->m_stream->fileName());
    }

    /**
     * Ensure getMetaData() returns no content.
     */
    public function testGetMetadata()
    {
        $this->assertEquals([], $this->m_stream->getMetadata());
        $this->assertEquals(null, $this->m_stream->getMetadata("any-key"));
    }

    /**
     * Ensure a stream's full content can be retrieved.
     */
    public function testGetContents()
    {
        $this->assertEquals("A file with a small amount of content.", $this->m_stream->getContents());
    }

    /**
     * Ensure fetching a file's contents sets the file position pointer.
     */
    public function testGetContentsSetsPointer()
    {
        $this->m_stream->getContents();
        self::assertEquals(38, $this->m_stream->tell());
    }

    /**
     * Ensure an exception is thrown when the contents are requested for a closed stream.
     */
    public function testGetContentsThrowsWhenClosed()
    {
        $this->m_stream->close();
        $this->expectException(FileStreamException::class);
        $this->m_stream->getContents();
    }

    /**
     * Ensure an exception is thrown when the contents are requested for a detached stream.
     */
    public function testGetContentsThrowsWhenDetached()
    {
        $this->m_stream->detach();
        $this->expectException(FileStreamException::class);
        $this->m_stream->getContents();
    }

    /**
     * Ensure a stream reports the correct pointer position.
     */
    public function testTell()
    {
        $this->assertEquals(0, $this->m_stream->tell());
        $this->m_stream->seek(4);
        $this->assertEquals(4, $this->m_stream->tell());
    }

    /**
     * Ensure rewind resets the pointer position.
     */
    public function testRewind()
    {
        $this->m_stream->read(1);
        $this->assertEquals(1, $this->m_stream->tell());
        $this->m_stream->rewind();
        $this->assertEquals(0, $this->m_stream->tell());
    }

    /**
     * Ensure rewinding a closed stream throws an exception.
     */
    public function testRewindThrowsWhenClosed()
    {
        $this->m_stream->close();
        $this->expectException(FileStreamException::class);
        $this->m_stream->rewind();
    }

    /**
     * Ensure rewinding a detached stream throws an exception.
     */
    public function testRewindThrowsWhenDetached()
    {
        $this->m_stream->detach();
        $this->expectException(FileStreamException::class);
        $this->m_stream->rewind();
    }

    /**
     * Ensure streams are seekable.
     */
    public function testIsSeekable()
    {
        $this->assertTrue($this->m_stream->isSeekable());
    }

    /**
     * Ensure closed streams are not seekable.
     */
    public function testIsSeekableWithClosed()
    {
        $this->m_stream->close();
        $this->assertFalse($this->m_stream->isSeekable());
    }

    /**
     * Ensure detached streams are not seekable.
     */
    public function testIsSeekableWithDetached()
    {
        $this->m_stream->detach();
        $this->assertFalse($this->m_stream->isSeekable());
    }

    /**
     * Ensure content can be read correctly from a stream.
     */
    public function testRead()
    {
        $this->m_stream->seek(2);
        $content = $this->m_stream->read(4);
        $this->assertEquals("file", $content);
    }

    /**
     * Ensure reading from a closed stream throws.
     */
    public function testReadThrowsWhenClosed()
    {
        $this->m_stream->seek(2);
        $this->m_stream->close();
        $this->expectException(FileStreamException::class);
        $content = $this->m_stream->read(4);
    }

    /**
     * Ensure reading from a detached stream throws.
     */
    public function testReadThrowsWhenDetached()
    {
        $this->m_stream->seek(2);
        $this->m_stream->detach();
        $this->expectException(FileStreamException::class);
        $content = $this->m_stream->read(4);
    }

    /**
     * Ensure reading from a write-only stream throws.
     */
    public function testReadThrowsWithWriteOnly()
    {
        $stream = new FileStream(self::WriteFileName, FileStream::ModeWrite);
        $this->expectException(FileStreamException::class);
        $content = $stream->read(4);
    }

    /**
     * Ensure streams correctly report readability.
     */
    public function testIsReadable()
    {
        $this->assertTrue($this->m_stream->isReadable());
    }

    /**
     * Ensure write-only streams report they are not readable.
     */
    public function testIsReadableWithWriteOnly()
    {
        $stream = new FileStream(self::WriteFileName, FileStream::ModeWrite);
        $this->assertFalse($stream->isReadable());
    }

    /**
     * Ensure closed streams report they are not readable.
     */
    public function testIsReadableWhenClosed()
    {
        $this->m_stream->close();
        $this->assertFalse($this->m_stream->isReadable());
    }

    /**
     * Ensure detached streams report they are not readable.
     */
    public function testIsReadableWhenDetached()
    {
        $this->m_stream->detach();
        $this->assertFalse($this->m_stream->isReadable());
    }

    /**
     * Ensure a non-exhausted stream does not report EOF.
     */
    public function testEof()
    {
        $this->assertFalse($this->m_stream->eof());
    }

    /**
     * Ensure a read to the end of a stream does not report EOF.
     */
    public function testEofAtEnd()
    {
        $this->m_stream->seek(0, SEEK_END);
        $this->assertFalse($this->m_stream->eof());
    }

    /**
     * Ensure a read past the end of a stream reports EOF.
     */
    public function testEofPastEnd()
    {
        $this->m_stream->seek(0, SEEK_END);
        $this->m_stream->read(1);
        $this->assertTrue($this->m_stream->eof());
    }

    /**
     * Ensure a closed stream throws when checking eof.
     */
    public function testEofWhenClosed()
    {
        $this->m_stream->seek(0, SEEK_END);
        $this->m_stream->read(1);
        $this->assertTrue($this->m_stream->eof());
        $this->m_stream->close();
        $this->expectException(FileStreamException::class);
        $this->expectExceptionMessage("The stream is not open.");
        $this->assertFalse($this->m_stream->eof());
    }

    /**
     * Ensure a detached stream throws when checking eof.
     */
    public function testEofWhenDetached()
    {
        $this->m_stream->seek(0, SEEK_END);
        $this->m_stream->read(1);
        $this->assertTrue($this->m_stream->eof());
        $this->m_stream->detach();
        $this->expectException(FileStreamException::class);
        $this->expectExceptionMessage("The stream is not open.");
        $this->assertFalse($this->m_stream->eof());
    }

    /**
     * Ensure a stream can be written.
     */
    public function testWrite()
    {
        $stream = new FileStream(self::WriteFileName, FileStream::ModeWrite);
        $this->assertEquals(4, $stream->write("file"));
    }

    /**
     * Ensure read-only streams can't be writteen.
     */
    public function testWriteThrowsWithReadOnly()
    {
        $this->expectException(FileStreamException::class);
        $this->m_stream->write("file");
    }

    /**
     * Ensure closed streams can't be writteen.
     */
    public function testWriteThrowsWithClosed()
    {
        $this->m_stream->close();
        $this->expectException(FileStreamException::class);
        $this->m_stream->write("file");
    }

    /**
     * Ensure detached streams can't be writteen.
     */
    public function testWriteThrowsWithDetached()
    {
        $this->m_stream->detach();
        $this->expectException(FileStreamException::class);
        $this->expectExceptionMessage("The stream is not open or is not writable.");
        $this->m_stream->write("file");
    }

    /**
     * Ensure closing a stream nullifies its state.
     */
    public function testClose()
    {
        $stream = new XRay($this->m_stream);
        $this->assertNotNull($stream->m_fh);
        $this->assertNotEquals(0, $stream->m_mode);
        $this->assertNotEquals("", $stream->m_fileName);
        $this->m_stream->close();
        $this->assertNull($stream->m_fh);
        $this->assertEquals(0, $stream->m_mode);
        $this->assertEquals("", $stream->m_fileName);
        $this->assertFalse($this->m_stream->isReadable());
    }

    /**
     * Ensure closing a closed stream throws an exception.
     */
    public function testCloseThrowsWhenClosed()
    {
        $this->m_stream->close();
        $this->expectException(FileStreamException::class);
        $this->expectExceptionMessage("The stream is not open.");
        $this->m_stream->close();
    }

    /**
     * Ensure closing a detached stream throws an exception.
     */
    public function testCloseThrowsWhenDetached()
    {
        $this->m_stream->detach();
        $this->expectException(FileStreamException::class);
        $this->expectExceptionMessage("The stream is not open.");
        $this->m_stream->close();
    }

    /**
     * Ensure the stream's size can be fetched.
     */
    public function testGetSize()
    {
        $this->assertEquals(38, $this->m_stream->getSize());
    }

    /**
     * Ensure fetching the stream's size throws when closed.
     */
    public function testGetSizeThrowsWhenClosed()
    {
        $this->m_stream->close();
        $this->expectException(FileStreamException::class);
        $this->expectExceptionMessage("The stream is not open.");
        $this->m_stream->getSize();
    }

    /**
     * Ensure fetching the stream's size throws when closed.
     */
    public function testGetSizeThrowsWhenDetached()
    {
        $this->m_stream->close();
        $this->expectException(FileStreamException::class);
        $this->expectExceptionMessage("The stream is not open.");
        $this->m_stream->getSize();
    }

    /**
     * Ensure fetching the size of the stream does not affect the position.
     */
    public function testGetSizeLeavesPointer()
    {
        $this->m_stream->seek(4, SEEK_SET);
        $this->m_stream->getSize();
        $this->assertEquals(4, $this->m_stream->tell());
    }
}
