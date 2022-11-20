<?php

namespace Bead\Responses;

use Bead\Contracts\Response;

/**
 * A response to redirect the user agent to a different URL.
 */
class RedirectResponse implements Response
{
	use DoesntHaveContent;
	use SendsHeaders;

    /** @var int HTTP status code for permanent redirects. */
	public const PermanentRedirect = 308;

    /** @var int HTTP status code for temporary redirects. */
	public const TemporaryRedirect = 307;

    /** @var int The default HTTP status code to use. */
	public const DefaultRedirectCode = self::TemporaryRedirect;

	/** @var int The HTTP status code. */
	private int $m_code;

	/** @var string The redirect location. */
	private string $m_url;

    /**
     * Initialise a new redirect response.
     *
     * You can provide any HTTP status code, but in almost all cases you should use the class HTTP status code constants
     * for consistency.
     *
     * @param string $url The URL to redirect to.
     * @param int $code The HTTP status code.
     */
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

    /**
     * Send the redirect response.
     */
	public function send(): void
	{
		http_response_code($this->statusCode());
		$this->sendHeaders();
	}
}
