<?php

namespace Bead\Contracts;

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
     * Fetch the secret to use to sign URIs.
     *
     * @return string The secret.
     */
    public function secret(): string;

    /**
     * Sign the given URI with the configured secret.
     *
     * The provided parameters, if not empty, will be appended to the URI. How the signing and expiry are handled is
     * implementation-defined.
     *
     * @param string $uri The URI to sign.
     * @param array<string,string> $parameters The parameters for the URI.
     * @param int|DateTimeInterface $expires The point in time at which the signed URI expires.
     *
     * @return string The signed URI.
     */
    public function sign(string $uri, array $parameters, $expires): string;

    /**
     * Verify a signed URI.
     *
     * @param string $uri The signed URI
     * @param int|DateTimeInterface|null $at The point in time at which to verify the URI. Defaults to `null`, which
     * means verify at the current time.
     *
     * @return bool `true` if it's verified, `false` if not.
     */
    public function verify(string $signedUri, $at = null): bool;
}
