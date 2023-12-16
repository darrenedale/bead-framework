<?php

declare(strict_types=1);

namespace Bead\Streams;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * An implementation of the PSR-7 StreamInterface backed by a fixed string buffer.
 */
class Buffer implements StreamInterface
{
    /** @var string The buffer backing the stream. */
    private string $buffer;

    /**
     * This is null when the stream has been manually closed.
     *
     * @var int|null The current position in the buffer or `null` if there's no buffer or the stream has been closed.
     */
    private ?int $pos;

    /**
     * Initialise a new StringStream.
     *
     * @param string $buffer The string buffer with which to back the stream.
     */
    public function __construct(string $buffer)
    {
        $this->buffer = $buffer;
        $this->pos = 0;
    }

    /**
     * Cast the stream to a string.
     *
     * Provides the full content of the buffer if the stream is open, or an empty string if it's closed. The buffer is
     * seeked to the end on exit (i.e. eof() === true).
     *
     * @return string The full buffer, or an empty string if the stream is closed.
     */
    public function __toString()
    {
        if ($this->isOpen()) {
            $this->pos = $this->getSize();
            return $this->buffer;
        }

        return "";
    }

    /**
     * Close the stream.
     */
    public function close()
    {
        $this->pos = null;
    }

    /**
     * Detach the stream.
     *
     * StringStream objects cannot be detached.
     *
     * @return null
     */
    public function detach()
    {
        return null;
    }

    /** Determine whether the stream is open. */
    public function isOpen(): bool
    {
        return null !== $this->pos;
    }

    /**
     * Throw if the stream is not open.
     *
     * @throws RuntimeException
     */
    private function checkOpen(): void
    {
        if (!$this->isOpen()) {
            throw new RuntimeException("The Buffer is not open");
        }
    }

    /**
     * Determine whether the end of the stream has been reached.
     *
     * A stream that isn't open is not eof().
     *
     * @return bool `true` if it has, `false` if not.
     */
    public function eof(): bool
    {
        return $this->pos === strlen($this->buffer);
    }

    /**
     * Determine whether the stream is seekable.
     *
     * @return bool `true` if the stream is open, `false` if not.
     */
    public function isSeekable()
    {
        return $this->isOpen();
    }

    /**
     * Determine whether the stream is readable.
     *
     * @return bool `true` if the stream is open, `false` if not.
     */
    public function isReadable()
    {
        return $this->isOpen();
    }

    /**
     * Determine whether the stream is writable.
     *
     * Buffers are never writable.
     *
     * @return bool `false`.
     */
    public function isWritable()
    {
        return false;
    }

    /**
     * Fetch the size of the stream.
     *
     * @return int|null The size of the buffer if open, `null` if not.
     */
    public function getSize()
    {
        return $this->isOpen() ? strlen($this->buffer) : null;
    }

    /**
     * Fetch the location of the current seek pointer.
     *
     * This is expressed in bytes from the beginning of the stream.
     *
     * @return int The position.
     * @throws RuntimeException if the stream is not open.
     */
    public function tell(): int
    {
        $this->checkOpen();
        return $this->pos;
    }

    /**
     * @param int $offset How far to seek in bytes.
     * @param int $whence Where to seek from. Must be either `SEEK_SET`, `SEEK_CUR` or `SEEK_END`. Defaults to SEEK_SET.
     *
     * @throws RuntimeException if the stream is not open.
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        assert(is_int($offset), new RuntimeException("Expecting int offset for Argument #1 \$offset of seek(), found " . gettype($offset)));
        assert(is_int($whence), new RuntimeException("Expecting SEEK_SET, SEEK_CUR or SEEK_END for Argument #2 \$whence of seek(), found " . gettype($whence)));
        $this->checkOpen();

        switch ($whence) {
            case SEEK_SET:
                break;

            case SEEK_CUR:
                $offset += $this->pos;
                break;

            case SEEK_END:
                $offset += $this->getSize();
                break;

            default:
                throw new RuntimeException("Expecting SEEK_SET, SEEK_CUR or SEEK_END for Argument #2 \$whence of seek(), found {$whence}");
        }

        if (0 > $offset || $offset > $this->getSize()) {
            throw new RuntimeException("Requested seek is beyond the bounds of the buffer");
        }

        $this->pos = $offset;
    }

    /**
     * Seek to the beginning of the stream.
     *
     * @throws RuntimeException if the stream is not open.
     */
    public function rewind()
    {
        $this->seek(0);
    }

    /**
     * Read bytes from the stream.
     *
     * @param int $length The maximum number of bytes to read.
     *
     * @return Buffer
     * @throws RuntimeException if the read length is invalid.
     */
    public function read($length): string
    {
        assert(is_int($length), new RuntimeException("Expecting int >= 0 length for Argument #1 \$length of read(), found " . gettype($length)));

        if (0 > $length) {
            throw new RuntimeException("Expecting int >= 0 length for Argument #1 \$length of read(), found {$length}");
        }

        if (!$this->isOpen()) {
            return "";
        }

        $start = $this->pos;
        $length = min($length, $this->getSize() - $this->pos);
        $this->pos += $length;
        return substr($this->buffer, $start, $length);
    }

    /**
     * Fetch the metatdata for the stream.
     *
     * Buffers don't have metadata.
     *
     * @param string $key The optional metatdata key to fetch.
     *
     * @return null
     */
    public function getMetadata($key = null)
    {
        return null;
    }

    /**
     * Write to the stream.
     *
     * Always throws an exception - Buffers are not writable.
     *
     * @param string $string The bytes to write.
     *
     * @throws RuntimeException
     */
    public function write($string)
    {
        throw new RuntimeException("Buffers are read-only");
    }

    /**
     * Fetch the remaining content of the stream.
     *
     * @return string The content of the buffer from the current position, or an empty string if the stream is EOF.
     * @throws RuntimeException if the buffer is closed
     */
    public function getContents()
    {
        $this->checkOpen();
        $pos = $this->tell();
        $this->seek(0, SEEK_END);
        return substr($this->buffer, $pos);
    }
}
