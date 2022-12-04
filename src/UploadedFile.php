<?php

namespace Bead;

use Bead\Exceptions\UploadedFileException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use SplFileInfo;

/**
 * Represents a file uploaded to the application.
 *
 * This class is used by the Bead\Request class to manage files uploaded to the application. Each file uploaded is
 * represented by a single object of this class.
 *
 * This is a read-only class - instances can only be retrieved from the Request object that has read them from the
 * incoming HTTP request.
 */
final class UploadedFile implements UploadedFileInterface
{
	/** The original name of the file uploaded by the user. */
	private string $m_name;

	/** The full original path of the file uploaded by the user. Only available after PHP 8.1 and not trustworthy. */
	private string $m_clientPath;

	/**
     * The path to the file's content.
     *
     * `null` once the uploaded file has been moved or discarded.
     */
	private ?string $m_tempFile;

	/**
     * The file data, if loaded.
     *
     * `null` until first required.
     */
	private ?string $m_fileData;

	/** The MIME type of the file reported by the user agent. */
	private string $m_mediaType;

	/** The size in bytes of the file reported by the user agent. */
	private int $m_size;

	/**
     * The actual size, in bytes, of the file uploaded.
     *
     * `null` until first required.
     */
	private ?int $m_actualSize;

    /** @var int The error code for the upload. */
    private int $m_errorCode;

	/**
	 * Create a new UploadedFile object.
	 *
	 * @param array $uploadedFile The entry in `$_FILES` from which to initialise the object.
	 */
	private function __construct(array $uploadedFile)
	{
        $this->m_tempFile = $uploadedFile["tmp_name"];
        $this->m_name = $uploadedFile["name"];
        $this->m_clientPath = $uploadedFile["full_path"] ?? "";
        $this->m_size = $uploadedFile["size"];
        $this->m_errorCode = $uploadedFile["error"] ?? 0;
        $this->m_mediaType = $uploadedFile["type"] ?? "";
        $this->m_actualSize = null;
        $this->m_fileData = null;
	}

    /**
     * Fetch all the files uploaded.
     *
     * The superglobal $_FILES is parsed and the result cached. Subsequent calls are therefore fast.
     *
     * @return self[]
     */
    public static function allUploadedFiles(): array
    {
        static $files = null;

        if (!isset($files)) {
			$files = [];

            foreach ($_FILES as $name => $file) {
                $files[$name] = new UploadedFile($file);
            }
        }

        return $files;
    }

    /**
     * Internal helper to invalidate the UploadedFile when moved/discarded.
     */
    private function invalidate(): void
    {
        $this->m_tempFile = null;
        $this->m_name = "";
        $this->m_clientPath = "";
        $this->m_size = 0;
        $this->m_errorCode = 0;
        $this->m_mediaType = "";
        $this->m_actualSize = null;
        $this->m_fileData = null;
    }

    /**
     * Helper to throw if the uploaded file is not valid (i.e. has been moved).
     *
     * Throws UploadedFileException if the UploadedFile is not valid.
     */
    private function checkValid(): void
    {
        if (!isset($this->m_tempFile)) {
            throw new UploadedFileException("The uploaded file is no longer valid.");
        }
    }

	/**
	 * Fetch the name of the uploaded file on the client's machine.
	 *
	 * @return string The file name.
	 */
	public function name(): string
	{
        $this->checkValid();
		return $this->m_name;
	}

    /**
     * The original full path name on the client machine of the file.
     *
     * This is reported by the user agent and is therefore not trustworthy. It will be an empty string if the user agent
     * did not supply this or if running on PHP < 8.1.
     *
     * @return string
     */
    public function clientPath(): string
    {
        $this->checkValid();
        return $this->m_clientPath;
    }

	/**
	 * Fetch the path for the uploaded file's temporary file.
	 *
	 * @return string The path to the temporary file.
	 */
	public function tempFile(): string
	{
        $this->checkValid();
		return $this->m_tempFile;
	}

    /**
     * Move the uploaded file to a more permanent storage location.
     *
     * If this is successful, the `UploadedFile` object will become invalid.
     *
     * @param string $path The destination for the file.
     *
     * @throws UploadedFileException if the UploadedFile has already been moved or discarded or if the move fails for
     * any reason.
     */
    public function moveTo($path)
    {
        $this->checkValid();
        $res = @move_uploaded_file($this->m_tempFile, $path);
        $this->invalidate();

        if (!$res) {
            throw new UploadedFileException("The uploaded file {$this->name()} could not be moved to '{$path}'.");
        }
    }

    /**
     * Discard the uploaded file.
     *
     * After calling this method, the `UploadedFile` object will become invalid.
     *
     * @throws UploadedFileException if the UploadedFile has already been moved or discarded.
     */
    public function discard(): void
    {
        $this->checkValid();
        @unlink($this->m_tempFile);
        $this->invalidate();
    }

	/**
	 * Fetch the MIME type for the uploaded file data.
	 *
	 * This is reported by the user agent and may not be accurate. It will be an empty string if the user agent did not
     * supply a MIME type.
	 *
	 * @return string The MIME type.
	 */
	public function mediaType(): ?string
	{
        $this->checkValid();
		return $this->m_mediaType;
	}

    /**
     * Fetch the size, in bytes, reported for the uploaded file by the user agent.
     *
     * @return int The size.
     */
    public function reportedSize(): int
    {
        $this->checkValid();
        return $this->m_size;
    }

    /**
     * Fetch the actual size, in bytes, of the uploaded file.
     *
     * @return int The size if the file.
     * @throws UploadedFileException if the uploaded file has been moved or discarded.
     */
    public function actualSize(): ?int
    {
        $this->checkValid();

        if (!isset($this->m_actualSize)) {
            if (isset($this->m_fileData)) {
                $this->m_actualSize = strlen($this->m_fileData);
            } else {
                $size = @filesize($this->m_tempFile);

                if (false === $size) {
                    throw new UploadedFileException("Could not determine the size of the uploaded file.");
                }

                $this->m_actualSize = $size;
            }
        }

        return $this->m_actualSize;
    }

	/**
	 * Fetch the file data.
	 *
	 * This method returns the uploaded file content unless the file is invalid. The content of the file is read and
     * cached on the first call. If the file is valid but cannot be read for some reason, null is returned.
	 *
	 * @return string The file data, or `null` the file is not valid or the temporary file cannot be read.
	 */
	public function data(): ?string
	{
        $this->checkValid();

		if (!isset($this->m_fileData)) {
            $data = @file_get_contents($this->m_tempFile);

            if (false === $data) {
                throw new UploadedFileException("Unable to read the uploaded file.");
            }

            $this->m_fileData = $data;
		}

		return $this->m_fileData;
	}

    /**
     * Fetch the upload error code.
     *
     * @return int The error code. 0 if successful.
     */
    public function errorCode(): int
    {
        $this->checkValid();
        return $this->m_errorCode;
    }

    /**
     * Check whether the uploaded file is valid.
     *
     * Valid files have not been discarded or moved and have an error code of 0.
     *
     * @return bool
     */
    public function wasSuccessful(): bool
    {
        return UPLOAD_ERR_OK === $this->errorCode();
    }

    // UploadedFileInterface methods

    /**
     * Fetch a stream of the uploaded file.
     */
    public function getStream(): StreamInterface
    {
        return new FileStream($this->tempFile());
    }

    /**
     * Fetch the size of the uploaded file.
     */
    public function getSize(): ?int
    {
        return $this->reportedSize();
    }

    /**
     * Fetch the upload error code associated with this uploaded file.
     *
     * @return int The error code.
     */
    public function getError(): int
    {
        return $this->errorCode();
    }

    /**
     * Fetch the client-provided original path for the file.
     *
     * This value should be treated as untrustworthy.
     *
     * @return string|null The path.
     */
    public function getClientFilename(): ?string
    {
        return $this->clientPath();
    }

    /**
     * Fetch the media type of the uploaded file.
     *
     * @return string|null
     */
    public function getClientMediaType(): ?string
    {
        return $this->mediaType();
    }
}
