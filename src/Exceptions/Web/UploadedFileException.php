<?php

declare(strict_types=1);

namespace Bead\Exceptions\Web;

use Bead\Contracts\Web\UploadedFile as UploadedFileContract;
use RuntimeException;
use Throwable;

/** Exception thrown when an operation on an UploadedFile produces an error condition. */
class UploadedFileException extends RuntimeException
{
    /** @var UploadedFileContract The uploaded file that caused the error. */
    private UploadedFileContract $uploadedFile;

    /**
     * Initialise a new UploadedFileException.
     *
     * @param UploadedFileContract $uploadedFile The UploadedFile that triggered the exception.
     * @param string $message The error message.
     * @param int $code The error code, if one is required.
     * @param Throwable|null $previous The previous exception if the UploadedFileException was triggered by another.
     */
    public function __construct(UploadedFileContract $uploadedFile, string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->uploadedFile = $uploadedFile;
    }

    /** The uploaded file that caused the error. */
    public function getUploadedFile(): UploadedFileContract
    {
        return $this->uploadedFile;
    }
}
