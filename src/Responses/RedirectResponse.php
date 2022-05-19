<?php

namespace Equit\Responses;

use Equit\Contracts\Response;

class RedirectResponse implements Response
{
	use DoesntHaveContent;
	use SendsHeaders;

	public const PermanentRedirect = 308;
	public const TemporaryRedirect = 307;

	public const DefaultRedirectCode = self::TemporaryRedirect;

	/** @var int The response code. */
	private int $m_code;

	/** @var string The redirect location. */
	private string $m_url;

	public function __construct(string $url, int $code = self::DefaultRedirectCode)
	{
		$this->m_url  = $url;
		$this->m_code = $code;
	}

	/**
	 * @inheritDoc
	 */
	public function statusCode(): int
	{
		return $this->m_code;
	}

	/**
	 * The redirect headers.
	 *
	 * @return array
	 */
	public function headers(): array
	{
		return ["location" => $this->url(),];
	}

	/**
	 * Fetch the redirect URL.
	 *
	 * @return string The URL.
	 */
	public function url(): string
	{
		return $this->m_url;
	}

	/**
	 * Set the redirect URL.
	 *
	 * @param string $url The URL.
	 */
	public function setUrl(string $url): void
	{
		$this->m_url = $url;
	}

	public function send(): void
	{
		http_response_code($this->statusCode());
		$this->sendHeaders();
	}
}
