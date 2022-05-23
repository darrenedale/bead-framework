<?php

namespace Equit\Responses;

/**
 * Response to stream a file to the client.
 */
class FileDownloadResponse extends DownloadResponse
{
	private string $m_sourceFileName;

	public function __construct(string $fileName, string $contentType = self::DefaultContentType)
	{
		parent::__construct("", $contentType);
		$this->setSourceFile($fileName);
	}

	public function sourceFile(): string
	{
		return $this->m_sourceFileName;
	}

	public function setSourceFile(string $fileName): void
	{
		$this->m_sourceFileName = $fileName;
	}

	public function fromFile(string $fileName): self
	{
		$this->setSourceFile($fileName);
		return $this;
	}

	public function send(): void
	{
		http_response_code($this->statusCode());
		$this->sendHeaders();
		readfile($this->fileName());
	}
}
