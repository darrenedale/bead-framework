<?php

namespace Bead\Contracts;

use DateTimeInterface;

/**
 * Interface for implementations of signed URI generators.
 */
interface UriSigner
{
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
    public function sign(string $uri, array $parameters, int|DateTimeInterface $expires): string;

    /**
     * Verify a signed URI.
     *
     * @param string $signedUri The signed URI
     * @param int|DateTimeInterface|null $at The point in time at which to verify the URI. Defaults to `null`, which
     * means verify at the current time.
     *
     * @return bool `true` if it's verified, `false` if not.
     */
    public function verify(string $signedUri, int|DateTimeInterface|null $at = null): bool;
}
