<?php

declare(strict_types=1);

namespace Bead\Contracts\Web;

use Bead\Exceptions\Web\UploadedFileException;
use LogicException;
use SplFileInfo;

interface UploadedFile
{
    /** Fetch the name of the uploaded file. */
    public function name(): string;

    /** Fetch the file size (in bytes) reported by the upload. */
    public function reportedSize(): int;

    /**
     * Fetch the size (in bytes) of the actual uploaded file.
     *
     * It is an error to call this for an invalid file (see isValid());
     *
     * @throws LogicException if the file is not valid.
     * @throws UploadedFileException if the size of the uploaded file cannot be determined.
     */
    public function actualSize(): int;

    /** Fetch the uploaded file's media type. */
    public function mediaType(): string;

    /**
     * The temporary path where the uploaded file is stored.
     *
     * Once the file has been moved it is an error to call this method (see isValid()).
     *
     * @return string The path to the temporary file.
     */
    public function path(): string;

    /**
     * Move the temporary uploaded file to a permanent location.
     *
     * Once the file has been moved it is an error to call this method (see isValid()).
     *
     * @param string $path The destination path for the uploaded file.
     *
     * @return SplFileInfo An object representing the file in its permanent home.
     * @throws LogicException if the file isn't valid.
     * @throws UploadedFileException if the file is valid but can't be moved to the provided path.
     */
    public function moveTo(string $path): SplFileInfo;

    /**
     * Fetch the contents of the uploaded file.
     *
     * Once the file has been moved it is an error to call this method (see isValid()).
     *
     * @return string The contents of the temporary file.
     * @throws LogicException if the file isn't valid
     * @throws UploadedFileException if an error occurs reading the temporary file.
     */
    public function contents(): string;

    /** Fetch the error code for the upload. */
    public function error(): int;

    /**
     * Check whether the uploaded file is (still) valid.
     *
     * The uploaded file is invalid if the upload errored or it has been moved.
     */
    public function isValid(): bool;
}
