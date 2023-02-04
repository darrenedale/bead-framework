<?php

declare(strict_types=1);

namespace Bead\Streams;

use Bead\Exceptions\FileStreamException;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use SplFileInfo;
use Throwable;

/**
 * Implementation of Psr7 StreamInterface for local files.
 *
 * Note that many methods don't have return type specifiers because the definition of the interface omits them.
 */
class File implements StreamInterface
{
    /** @var int Open the file for reading. */
    public const ModeRead = 0x01;

    /** @var int Open the file for writing. */
    public const ModeWrite = 0x02;

    /** @var int Open the file for both reading and writing. */
    public const ModeReadWrite = self::ModeRead | self::ModeWrite;

    /** @var int The default open mode. */
    public const DefaultMode = self::ModeRead;

    /** @var resource|null The file being streamed to/from. */
    private $m_fh;

    /** @var int The open mode of the stream. */
    private $m_mode;

    /** @var string The name of the file being streamed to/from. */
    private string $m_fileName;

    /**
     * Initialise and open a new FileStream.
     *
     * @param SplFileInfo|string $file The file to stream.
     * @param int $mode The mode to open in.
     */
    public function __construct(SplFileInfo|string $file, int $mode = self::DefaultMode)
    {
        $mode &= 0x03;

        if (0 === $mode) {
            throw new InvalidArgumentException("Invalid stream open mode 0x" . sprintf("%08x", $mode));
        }

        if ($file instanceof SplFileInfo) {
            $file = $file->getPathname();
        }

        $this->m_fh = @fopen($file, match ($mode) {
            self::ModeRead => "r",
            self::ModeWrite => "w",
            self::ModeReadWrite => "r+",
        }, false) ?: null;

        if (!isset($this->m_fh)) {
            throw new FileStreamException("Could not open file {$file}.");
        }

        $this->m_mode = $mode;
        $this->m_fileName = $file;
    }

    /**
     * Destroy the stream.
     */
    public function __destruct()
    {
        if (isset($this->m_fh)) {
            $this->close();
        }
    }

    /**
     * Throw a FileStreamException if the stream is not open.
     */
    private function checkOpen(): void
    {
        if (!isset($this->m_fh)) {
            throw new FileStreamException("The stream is not open.");
        }
    }

    /**
     * Fetch the name of the file being streamed.
     *
     * @return string The file name.
     */
    public function fileName(): string
    {
        return $this->m_fileName;
    }

    /**
     * Close the stream.
     *
     * @throws FileStreamException if the stream is not open.
     */
    public function close()
    {
        $this->checkOpen();
        fclose($this->m_fh);
        $this->cleanUp();
    }

    /**
     * Detach and return the underlying stream resource.
     *
     * After this is done, the FileStream is no longer usable.
     *
     * @throws FileStreamException if the stream is not open.
     */
    public function detach()
    {
        $this->checkOpen();
        $fh = $this->m_fh;
        $this->cleanUp();
        return $fh;
    }

    /**
     * Clean up when the stream is closed/detached/destroyed.
     */
    protected function cleanUp(): void
    {
        $this->m_fh = null;
        $this->m_mode = 0;
        $this->m_fileName = "";
    }

    /**
     * Seek back to the beginning of the stream.
     *
     * @throws FileStreamException if the stream is not open.
     */
    public function rewind()
    {
        $this->checkOpen();
        rewind($this->m_fh);
    }

    /**
     * Fetch the file's full content.
     *
     * @return string The content, or `false` if the stream is not open.
     */
    public function __toString()
    {
        try {
            return $this->getContents();
        } catch (Throwable $err) {
            return "";
        }
    }

    /**
     * Fetch the full content of the file.
     *
     * @return string The content.
     * @throws FileStreamException if the content can't be read.
     */
    public function getContents()
    {
        // NOTE rewind() throws the appropriate exception if the stream isn't seekable
        $this->rewind();
        return $this->read($this->getSize());
    }

    /**
     * Fetch the size of the file.
     *
     * @return int|null The size in bytes, or `null` if it's not open or can't be determined.
     * @throws FileStreamException if the stream is not open.
     */
    public function getSize(): ?int
    {
        // NOTE tell() throws the appropriate exception if the file is not open
        $pos = $this->tell();
        $this->seek(0, SEEK_END);
        $size = $this->tell();
        $this->seek($pos, SEEK_SET);
        return $size;
    }

    /**
     * Fetch the current seek pointer within the file.
     *
     * @return int The seek pointer.
     * @throws FileStreamException if the stream is not open.
     */
    public function tell()
    {
        $this->checkOpen();
        return ftell($this->m_fh);
    }

    /**
     * Seek to a given offset in the stream.
     *
     * @param int $offset Where to seek to.
     * @param int $whence Where to seek from.
     *
     * @throws FileStreamException if the stream is not open.
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        $this->checkOpen();
        fseek($this->m_fh, $offset, $whence);
    }

    /**
     * Check whether the stream is exhausted.
     *
     * The stream is exhausted when an attempt is made to read beyond the end of the file. Note that this means reading
     * precisely the number of bytes in the file from position 0 does *not* set EOF.
     *
     * @return bool `true` if the stream is exhausted `false` if not.
     * @throws FileStreamException if the stream is not open.
     */
    public function eof()
    {
        $this->checkOpen();
        return feof($this->m_fh);
    }

    /**
     * Check whether the stream can be read from.
     *
     * @return bool `true` if it's readable, `false` if not.
     */
    public function isReadable()
    {
        return (bool) (self::ModeRead & $this->m_mode);
    }

    /**
     * Check whether the stream can be written to.
     *
     * @return bool `true` if it's writable, `false` if not.
     */
    public function isWritable()
    {
        return (bool) (self::ModeWrite & $this->m_mode);
    }

    /**
     * Check whether the stream pointer can be moved.
     *
     * @return bool `true` if it's seekable, `false` if not.
     */
    public function isSeekable()
    {
        return isset($this->m_fh);
    }

    /**
     * Read up to a given number of bytes from the stream.
     *
     * The stream is read from the current pointer for the number of bytes, or to the end of the stream, whichever is
     * nearest.
     *
     * @return string The bytes read.
     * @throws FileStreamException if the stream cannot be read.
     */
    public function read($length)
    {
        if (!$this->isReadable()) {
            throw new FileStreamException("The stream is not open or is not readable.");
        }

        $data = fread($this->m_fh, $length);

        if (false === $data) {
            throw new FileStreamException("Error reading from the stream.");
        }

        return $data;
    }

    /**
     * Write some data to the stream.
     *
     * @param string $data The data to write.
     *
     * @return int The number of bytes written.
     * @throws FileStreamException if the stream cannot be written.
     */
    public function write($data)
    {
        if (!$this->isWritable()) {
            throw new FileStreamException("The stream is not open or is not writable.");
        }

        $bytes = fwrite($this->m_fh, $data);

        if (false === $bytes) {
            throw new FileStreamException("The stream could not be written.");
        }

        return $bytes;
    }

    /**
     * Fetch meta-data about the stream.
     *
     * Implemented for compatibility with StreamInterface only. No data is returned.
     *
     * @param ?string $key The optional meta-data key.
     *
     * @return array|null An empty array (if all meta-data is sought) or null (if a specific key is sought).
     */
    public function getMetadata($key = null)
    {
        return (isset($key) ? null : []);
    }
}
