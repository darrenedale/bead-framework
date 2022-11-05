<?php

namespace Equit;

use Equit\Contracts\UriSigner as UriSignerContract;
use Equit\Exceptions\UriSignerException;
use InvalidArgumentException;

/**
 * Implementation of the UriSigner contract that uses HMACs for signatures.
 *
 * URIs with fragments are not supported.
 */
class UriSigner implements UriSignerContract
{
	/**
	 * The minimum required length for a secret.
	 *
	 * This is not a recommendataion - it's not a very secure length - but the minimum that will be accepted.
	 */
	public const MinimumSecretLength = 6;

	/** @var string The default hashing algorithm to use when computing signature HMACs. */
	public const DefaultAlgorithm = "sha1";

	/** @var string The hasing algorithm used when computing signature HMACs. */
	private string $m_algorithm;

	/** @var string The secret to use when signing URIs. */
	private string $m_secret;

	/** @var array The parameters to use when generating the signature. */
	private array $m_parameters;

	/**
	 * Initialise a new signer.
	 *
	 * @param string $algorithm The optional algorithm
	 */
	public function __construct(string $algorithm = self::DefaultAlgorithm)
	{
		$this->setAlgorithm($algorithm);
		$this->m_secret = "";
		$this->m_parameters = [];
	}

	/**
	 * Securely scrub the stored secret when the signer is destroyed.
	 */
	public function __destruct()
	{
		// scrub before it's deallocated
		if (0 < strlen($this->m_secret)) {
			$this->m_secret = random_bytes(strlen($this->m_secret));
		}
	}

	/**
	 * Set the algorithm to use when computing the HMAC for the signature.
	 *
	 * All algorithms returned by hash_hmac_algos() are supported.
	 *
	 * @param string $algorithm The algorithm.
	 * @throws UriSignerException if the algorithm is not supported.
	 */
	public function setAlgorithm(string $algorithm): void
	{
		if (!in_array($algorithm, hash_hmac_algos())) {
			throw new UriSignerException("The hashing algorithm {$algorithm} is not available.");
		}

		$this->m_algorithm = $algorithm;
	}

	/**
	 * The hashing algorithm used when computing the HMAC for the signature.
	 *
	 * @return string The algorithm.
	 */
	public function algorithm(): string
	{
		return $this->m_algorithm;
	}

	/**
	 * Fluently set the secret to use when signing URIs.
	 *
	 * UriSigners are immutable, a clone of the current signer is altered and returned.
	 *
	 * @param string $secret The secret.
	 *
	 * @return $this A UriSigner for further methdo chaining.
	 */
	public function usingSecret(string $secret): self
	{
		if (self::MinimumSecretLength > strlen($secret)) {
			throw new InvalidArgumentException("Secrets for signing URIs must be at least " . self::MinimumSecretLength . " characters.");
		}

		$clone = clone $this;
		$clone->m_secret = $secret;
		return $clone;
	}

	/**
	 * Fluently set the parameters to use when signing URIs.
	 *
	 * UriSigners are immutable, a clone of the current signer is altered and returned.
	 *
	 * @param array $parameters The paramters.
	 *
	 * @return $this A UriSigner for further methdo chaining.
	 */
	public function withParameters(array $parameters): self
	{
		$clone = clone $this;
		$clone->m_parameters = $parameters;
		return $clone;
	}

	/**
	 * Fetch the secret to use to sign URIs.
	 *
	 * @return string The secret.
	 */
	public function secret(): string
	{
		return $this->m_secret;
	}

	/**
	 * Fetch the URI parameters to use in the signing process.
	 *
	 * @return array The parameters.
	 */
	public function parameters(): array
	{
		return $this->m_parameters;
	}

	/**
	 * Compute the signature for the URI.
	 *
	 * @param string $uriWithParams The URI to use to generate the signature, with the parameters added.
	 *
	 * @return string The signature parameter value.
	 * @throws UriSignerException if the secret has not been set to a valid value.
	 */
	protected function signature(string $uriWithParams): string
	{
		$secret = $this->secret();

		if (self::MinimumSecretLength > strlen($secret)) {
			// scrub before it's deallocated
			$secret = random_bytes(strlen($secret));
			throw new UriSignerException("The secret for signing the URI is too short or has not been set.");
		}

		$signature = hash_hmac($this->algorithm(), $uriWithParams, $secret);
		// scrub before it's deallocated
		$secret = random_bytes(strlen($secret));
		return $signature;
	}

	/**
	 * Helper to generate the query string for the signer's configured parameters.
	 *
	 * @return string The query string.
	 */
	protected function queryString(): string
	{
		$parameters = $this->parameters();

		return implode(
			"&",
			array_map(
				fn($key, $value) => urlencode($key) . "=" . urlencode($value),
				array_keys($parameters),
				array_values($parameters)
			)
		);
	}

	/**
	 * Sign the given URI with the configured secret and parameters.
	 *
	 * The parameters will be appended to the URI, appropriately encoded. The singature will then be appended using the
	 * URL parameter signature. The parameters for the signer must not include a signature parameter. The generated
	 * signature parameter will always be the last parameter in the signed URI. In order to pass verification, the URI
	 * must be exactly as returned from sign() - the order of the parameters must not change.
	 *
	 * @param string $uri The URI to sign.
	 *
	 * @return string The signed URI.
	 */
	public function sign(string $uri): string
	{
		$queryString = $this->queryString();

		if (false === strpos($uri, "?")) {
			$uri = "{$uri}?{$queryString}";
		} else {
			$uri = "{$uri}&{$queryString}";
		}

		if (false === strpos($uri, "?")) {
			return "{$uri}?signature={$this->signature($uri)}";
		} else {
			return "{$uri}&signature={$this->signature($uri)}";
		}
	}

	/**
	 * Verify the URI was signed with the configured parameters and secret.
	 *
	 * @param string $signedUri The URI to verify.
	 *
	 * @return bool `true` if the signature is verified, `false` if not.
	 */
	public function verify(string $signedUri): bool
	{
		// NOTE signature MUST always be last URI parameter, this is mandated in sign() above
		$signaturePos = strpos($signedUri, "signature=");

		if (false === $signaturePos) {
			return false;
		}

		$signature = substr($signedUri, $signaturePos + 10);
		// subtract 1 to adjust for the & or ? preceding the signature URI parameter
		$uri = substr($signedUri, 0, $signaturePos - 1);
		return $this->signature($uri) === $signature;
	}
}
