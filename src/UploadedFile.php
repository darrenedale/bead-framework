<?php

namespace Bead;

use Bead\Facades\Log;
use SplFileInfo;

/**
 * Represents a file uploaded to the application.
 *
 * This class is used by the Bead\Request class to manage files uploaded to the application. Each file uploaded is
 * represented by a single object of this class.
 *
 * This is a read-only class - instances can only be retrieved from the Request object that has read them from the
 * incoming HTTP request.
 *
 * @package bead-framework
 */
final class UploadedFile
{
	/** The original name of the file uploaded by the user. */
	private string $m_name;

	/** The full original path of the file uploaded by the user. Only available after PHP 8.1 and not trustworthy. */
	private string $m_clientPath;

	/** The path to the file's content. */
	private ?string $m_tempFile;

	/** The file data, if loaded. */
	private ?string $m_fileData;

	/** The MIME type of the file reported by the user agent. */
	private string $m_mimeType;

	/** The size in bytes of the file reported by the user agent. */
	private int $m_size;

    /** @var int The error code for the upload. */
    private int $m_errorCode;

	/**
	 * Create a new UploadedFile object.
	 *
	 * @param array $uploadedFile The entry from $_FILES from which to initialise the object..
	 */
	private function __construct(array $uploadedFile)
	{
        $this->m_tempFile = $uploadedFile["tmp_name"];
        $this->m_name = $uploadedFile["name"];
        $this->m_clientPath = $uploadedFile["full_path"] ?? "";
        $this->m_size = $uploadedFile["size"];
        $this->m_errorCode = $uploadedFile["error"] ?? 0;
        $this->m_mimeType = $uploadedFile["type"] ?? "";
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
        $this->m_fileData = null;
    }

	/**
	 * Fetch the name of the uploaded file on the client's machine.
	 *
	 * @return string The file name.
	 */
	public function name(): string
	{
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
        return $this->m_clientPath;
    }

	/**
	 * Fetch the path for the uploaded file's temporary file.
	 *
	 * @return string The path to the temporary file, or `null` if the file has been discarded or moved.
	 */
	public function tempFile(): string
	{
		return $this->m_tempFile;
	}

    /**
     * Move the uploaded file to a more permanent storage location.
     *
     * If this is successful, the `UploadedFile` object will become invalid.
     *
     * @param string $path The destination for the file.
     *
     * @return SplFileInfo|null The moved file, or null if the file could not be moved.
     */
    public function moveTo(string $path): ?SplFileInfo
    {
        if (isset($this->m_tempFile) && move_uploaded_file($this->m_tempFile, $path)) {
            $this->invalidate();
            return new SplFileInfo($path);
        }

        return null;
    }

    /**
     * Discard the uploaded file.
     *
     * If this is successful, the `UploadedFile` object will become invalid.
     *
     * @return bool `true` if the file was discarded, `false` if not or if the uploaded file is not valid.
     */
    public function discard(): bool
    {
        if (isset($this->m_tempFile) && @unlink($this->m_tempFile)) {
            $this->invalidate();
            return true;
        }

        return false;
    }

	/**
	 * Fetch the MIME type for the uploaded file data.
	 *
	 * This is reported by the user agent and may not be accurate. It will be an empty string if the user agent did not
     * supply a MIME type.
	 *
	 * @return string|null The MIME type.
	 */
	public function mimeType(): ?string
	{
		return $this->m_mimeType;
	}

    /**
     * Fetch the size, in bytes, reported for the uploaded file by the user agent.
     *
     * @return int The size.
     */
    public function reportedSize(): int
    {
        return $this->m_size;
    }

    /**
     * Fetch the actual size, in bytes, of the uploaded file.
     *
     * @return int|null The size if the file is valid, `null` if it is not.
     */
    public function actualSize(): ?int
    {
        if (!$this->isValid()) {
            return null;
        }

        if (isset($this->m_fileData)) {
            return strlen($this->m_fileData);
        }

        $size = (new SplFileInfo($this->m_tempFile))->getSize();
        return (false === $size ? null : $size);
    }

	/**
	 * Fetch the file data.
	 *
	 * This method returns the uploaded file content unless the file is invalid. The content of the file is read and
     * cached on the first call. If the file is valid but cannot be read for some reason, null is returned.
	 *
	 * @return string|null The file data, or `null` the file is not valid or the temporary file cannot be read.
	 */
	public function data(): ?string
	{
		if (!isset($this->m_fileData) && $this->isValid()) {
			if (!is_file($this->m_tempFile)) {
				Log::error("file \"{$this->m_tempFile}\" is not a file");
			} else if (!is_readable($this->m_tempFile)) {
				Log::error("file \"{$this->m_tempFile}\" is not readable");
			} else {
				$this->m_fileData = file_get_contents($this->m_tempFile);
			}
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
        return $this->m_errorCode;
    }

    /**
     * @return bool
     * @deprecated Use !isValid() instead.
     */
    public function isNull(): bool
    {
        return !$this->isValid();
    }

    /**
     * Check whether the uploaded file is valid.
     *
     * Valid files have not been discarded or moved and have an error code of 0.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return isset($this->m_tempFile) && (0 === $this->m_errorCode) && (file_exists($this->m_tempFile) || isset($this->m_fileData));
    }
}
