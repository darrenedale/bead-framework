<?php

declare(strict_types=1);

namespace Bead\Web;

use Bead\Contracts\Web\UploadedFile as UploadedFileContract;
use Bead\Exceptions\Web\UploadedFileException;
use LogicException;
use SplFileInfo;

use const UPLOAD_ERR_OK;

/** Default implementation of the UploadedFile contract. */
class UploadedFile implements UploadedFileContract
{
    /** @var int Indicator that the actual size of the uploaded file has yet to be determined. */
    private const ActualSizeUnknown = -1;

    /** @var string The name of the uploaded file. */
    private string $m_name;

    /** @var int The size in bytes of the file reported by the user agent. */
    private int $m_reportedSize;

    /** @var int The actual size in bytes of the file received. */
    private int $m_actualSize;

    /** @var string The temporary location of the uploaded file, or an empty string once it's been moved */
    private string $m_temporaryPath;

    /** @var int The error code for the upload. */
    private int $m_error;

    /** @var string The file's media type. */
    private string $m_mediaType;

    /** @var string The contents of the temporary file (lazy-initialised). */
    private string $m_contents;

    /** UploadedFile instances can't be constructed directly, use one of the factory methods. */
    private function __construct()
    {}

    /**
     * Create a new uploaded file from the content of an entry in $_FILES.
     *
     * @param array{
     *     tmp_name: string,
     *     name: string,
     *     size: int,
     *     error: int,
     *     type: string,
     * } $uploadedFile
     */
    public static function fromFilesArray(array $uploadedFile): self
    {
        $file = new UploadedFile();
        $file->m_name = $uploadedFile["name"];
        $file->m_mediaType = $uploadedFile["type"] ?? "";
        $file->m_temporaryPath = $uploadedFile["tmp_name"];
        $file->m_reportedSize = $uploadedFile["size"];
        $file->m_error = $uploadedFile["error"] ?? 0;
        $file->m_actualSize = self::ActualSizeUnknown;
        $file->m_contents = "";
        return $file;
    }

    /** Create a new uploaded file. */
    public static function create(string $name, string $type, string $tempPath, int $size, int $errorCode = UPLOAD_ERR_OK): self
    {
        $file = new UploadedFile();
        $file->m_name = $name;
        $file->m_mediaType = $type;
        $file->m_temporaryPath = $tempPath;
        $file->m_reportedSize = $size;
        $file->m_error = $errorCode;
        $file->m_actualSize = -1;
        $file->m_contents = "";
        return $file;
    }

    /** Helper to read the contents of the temporary file. */
    private function readTemporaryFile(): void
    {
        $contents = file_get_contents($this->m_temporaryPath);

        if (false === $contents) {
            throw new UploadedFileException($this, "The contents of the temporary uploaded file cannot be read");
        }

        $this->m_contents = $contents;
    }

    /** @inheritDoc */
    public function name(): string
    {
        return $this->m_name;
    }

    /** @inheritDoc */
    public function reportedSize(): int
    {
        return $this->m_reportedSize;
    }

    /** @inheritDoc */
    public function actualSize(): int
    {
        if (!$this->isValid()) {
            throw new LogicException("The uploaded file \"{$this->name()}\" is not valid");
        }

        if (self::ActualSizeUnknown === $this->m_actualSize) {
            $size = @filesize($this->m_temporaryPath);

            if (false === $size) {
                throw new UploadedFileException($this, "The size of the temporary uploaded file \"{$this->m_temporaryPath}\" could not be determined");
            }

            $this->m_actualSize = $size;
        }

        return $this->m_actualSize;
    }

    /** @inheritDoc */
    public function mediaType(): string
    {
        return $this->m_mediaType;
    }

    /** @inheritDoc */
    public function path(): string
    {
        if (!$this->isValid()) {
            throw new LogicException("The uploaded file \"{$this->name()}\" is not valid");
        }

        return $this->m_temporaryPath;
    }

    /** @inheritDoc */
    public function moveTo(string $path): SplFileInfo
    {
        if (!$this->isValid()) {
            throw new LogicException("The uploaded file \"{$this->name()}\" is not valid and cannot be moved");
        }

        if (!@move_uploaded_file($this->m_temporaryPath, $path)) {
            throw new UploadedFileException($this, "The file \"{$this->name()}\" could not be moved to \"{$path}\"");
        }

        $this->m_temporaryPath = "";
        return new SplFileInfo($path);
    }

    /** @inheritDoc */
    public function contents(): string
    {
        if (!$this->isValid()) {
            throw new LogicException("The uploaded file \"{$this->name()}\" is not valid");
        }

        if ("" === $this->m_contents) {
            $this->readTemporaryFile();
        }

        return $this->m_contents;
    }

    /** @inheritDoc */
    public function error(): int
    {
        return $this->m_error;
    }

    /** @inheritDoc */
    public function isValid(): bool
    {
        return UPLOAD_ERR_OK === $this->m_error && "" !== $this->m_temporaryPath;
    }
}