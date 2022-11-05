<?php

namespace Equit\Contracts;

/**
 * Interface for imlementations of signed URI generators.
 */
interface UriSigner
{
	/**
	 * Fluently set the secret to use when signing URIs.
	 *
	 * UriSigners are immutable, a clone of the current signer is altered and returned.
	 *
	 * @param string $secret The secret.
	 *
	 * @return $this A UriSigner for further methdo chaining.
	 */
	public function usingSecret(string $secret): self;

	/**
	 * Fluently set the parameters to use when signing URIs.
	 *
	 * UriSigners are immutable, a clone of the current signer is altered and returned.
	 *
	 * @param array $parameters The paramters.
	 *
	 * @return $this A UriSigner for further methdo chaining.
	 */
	public function withParameters(array $parameters): self;

	/**
	 * Fetch the secret to use to sign URIs.
	 *
	 * @return string The secret.
	 */
	public function secret(): string;

	/**
	 * Fetch the URI parameters to use in the signing process.
	 *
	 * @return array The parameters.
	 */
	public function parameters(): array;

	/**
	 * Sign the given URI with the configured secret and parameters.
	 *
	 * @param string $uri The URI to sign.
	 *
	 * @return string The signed URI.
	 */
	public function sign(string $uri): string;

	/**
	 * Verify a signed URI.
	 *
	 * @param string $uri The signed URI
	 *
	 * @return bool `true` if it's verified, `false` if not.
	 */
	public function verify(string $signedUri): bool;
}
