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

    public function __construct(UploadedFileContract $uploadedFile, string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->uploadedFile = $uploadedFile;
    }

    public function getUploadedFile(): UploadedFileContract
    {
        return $this->uploadedFile;
    }
}
