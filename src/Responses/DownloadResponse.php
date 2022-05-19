<?php

namespace Equit\Responses;

class DownloadResponse extends AbstractResponse
{
	const DefaultContentType = "application/octet-stream";

	private string $m_fileName = "";
	private string $m_data = "";
	private array $m_headers;

	public function __construct($data, string $contentType = self::DefaultContentType)
	{
		$this->setStatusCode(200);
		$this->setContentType($contentType);
		$this->m_data = $data;
	}

	public function fileName(): string
	{
		return $this->m_fileName;
	}

	public function setFileName(string $fileName): void
	{
		$this->m_fileName = $fileName;
	}

	public function ofType(string $contentType): self
	{
		$this->setContentType($contentType);
		return $this;
	}

	public function named(string $fileName): self
	{
		$this->setFileName($fileName);
		return $this;
	}

	public function withHeaders(array $headers): self
	{
		$this->setHeaders($headers);
		return $this;
	}

	public function setHeaders(array $headers): void
	{
		// TODO validate header names
		$this->m_headers = $headers;
	}

	public function headers(): array
	{
		$fileName = $this->fileName();

		if (empty($fileName)) {
			$fileName = "download";
		}

		$headers = $this->m_headers;
		$headers["content-disposition"] = "attachment; filename=\"$fileName\"";
		return $headers;
	}

	public function content(): string
	{
		return $this->m_data;
	}
}