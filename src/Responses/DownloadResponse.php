<?php

namespace Bead\Responses;

/**
 * Send a response to be downloaded as a file.
 */
class DownloadResponse extends AbstractResponse
{
    /** @var string The default content-type header value for responses. */
	const DefaultContentType = "application/octet-stream";

    /** @var string The filename for the downloaded content. */
	private string $m_fileName = "";

    /** @var string The content for the download. */
	private string $m_data = "";

    /** @var array The headers. */
	private array $m_headers = [];

    /**
     * Initialise a new download response.
     *
     * @param string $data The data to download.
     * @param string $contentType The content-type for the data.
     */
	public function __construct(string $data, string $contentType = self::DefaultContentType)
	{
		$this->setStatusCode(200);
		$this->setContentType($contentType);
		$this->m_data = $data;
	}

    /**
     * Fetch the filename of the downloaded content.
     *
     * @return string The filename.
     */
	public function fileName(): string
	{
		return $this->m_fileName;
	}

    /**
     * Set the filename for the downloaded content.
     *
     * @param $fileName string The filename.
     */
	public function setFileName(string $fileName): void
	{
		$this->m_fileName = $fileName;
	}

    /**
     * Fluently set the content-type for the downloaded content.
     *
     * @param string $contentType The content type.
     *
     * @return $this This Response object for further method chaining.
     */
	public function ofType(string $contentType): self
	{
		$this->setContentType($contentType);
		return $this;
	}

    /**
     * Fluently set the filename for the downloaded content.
     *
     * @param string $fileName The filename to use.
     *
     * @return $this This Response object for further method chaining.
     */
	public function named(string $fileName): self
	{
		$this->setFileName($fileName);
		return $this;
	}

    /**
     * Fluently set the headers for the download response.
     *
     * @param array<string,string> $headers The response headers.
     *
     * @return $this This Response object for further method chaining.
     */
	public function withHeaders(array $headers): self
	{
		$this->setHeaders($headers);
		return $this;
	}

    /**
     * Set the download response HTTP headers.
     *
     * @param array<string,string> $headers The headers.
     */
	public function setHeaders(array $headers): void
	{
		$this->m_headers = $headers;
	}

    /**
     * Fetch the download response HTTP headers.
     *
     * Regardless of whether the `content-disposition` header has been set manually, the `content-disposition` in the
     * returned array is always set to 'attachment; filename="..."' (where ... is the filename set using `setFileName()`.
     * @return array<string,string>
     */
	public function headers(): array
	{
		$fileName = $this->fileName();

		if (empty($fileName)) {
			$fileName = "download";
		}

		$headers = $this->m_headers;
		$headers["content-disposition"] = "attachment; filename=\"{$fileName}\"";
		return $headers;
	}

    /**
     * Fetch the response content.
     *
     * The response content for DownloadResponse objects is the download data.
     *
     * @return string The content.
     */
	public function content(): string
	{
		return $this->m_data;
	}
}