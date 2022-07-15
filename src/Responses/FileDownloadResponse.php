<?php

namespace Equit\Responses;

/**
 * Response to stream a file to the client.
 */
class FileDownloadResponse extends DownloadResponse
{
	/** @var string The path to the file to stream. */
	private string $m_sourceFileName;

	/**
	 * Initialise a new instance of the response.
	 *
	 * @param string $fileName The name of the file when downloaded.
	 * @param string $contentType The media type for the download.
	 */
	public function __construct(string $fileName, string $contentType = self::DefaultContentType)
	{
		parent::__construct("", $contentType);
		$this->setSourceFile($fileName);
	}

	/**
	 * Fetch the path to the file that will be streamed.
	 *
	 * @return string The path.
	 */
	public function sourceFile(): string
	{
		return $this->m_sourceFileName;
	}

	/**
	 * Set the path to the file that will be streamed.
	 *
	 * @param string $fileName The path.
	 */
	public function setSourceFile(string $fileName): void
	{
		$this->m_sourceFileName = $fileName;
	}

	/**
	 * Fluently set the path to the file that will be streamed.
	 *
	 * @param string $fileName The path.
	 *
	 * @return $this
	 */
	public function fromFile(string $fileName): self
	{
		$this->setSourceFile($fileName);
		return $this;
	}

	/**
	 * Send the response.
	 */
	public function send(): void
	{
		http_response_code($this->statusCode());
		$this->sendHeaders();
		readfile($this->sourceFile());
	}
}
