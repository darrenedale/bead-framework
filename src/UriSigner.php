<?php

namespace Bead;

use DateTimeInterface;
use Bead\Contracts\UriSigner as UriSignerContract;
use Bead\Exceptions\UriSignerException;
use InvalidArgumentException;
use TypeError;

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

  /**
     * Initialise a new signer.
     *
     * @param string $algorithm The optional algorithm
     */
    public function __construct(string $algorithm = self::DefaultAlgorithm)
    {
        $this->setAlgorithm($algorithm);
        $this->m_secret = "";
    }

    /**
     * Securely scrub the stored secret when the signer is destroyed.
     */
    public function __destruct()
    {
        self::scrubSecret($this->m_secret);
    }

    /**
     * Helper to securely erase a string before it's deallocated.
     *
     * @param string $secret A reference to the string to scrub.
     */
    final protected static function scrubSecret(string & $secret): void
    {
        for ($idx = 0; $idx < strlen($secret); ++$idx) {
            $secret[$idx] = chr(mt_rand(0, 255));
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
     * Fetch the secret to use to sign URIs.
     *
     * @return string The secret.
     */
    public function secret(): string
    {
        return $this->m_secret;
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
            self::scrubSecret($secret);
            throw new UriSignerException("The secret for signing the URI is too short or has not been set.");
        }

        $signature = hash_hmac($this->algorithm(), $uriWithParams, $secret);
        self::scrubSecret($secret);
        return $signature;
    }

    /**
     * Helper to generate the query string for the signer's configured parameters.
     *
     * @param array $parameters The parameters to place in the query string.
     *
     * @return string The query string.
     */
    final protected static function queryString(array $parameters): string
    {
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
     * Extract the URI parameters from a URI string.
     *
     * @param string $uri The URI.
     *
     * @return array<string,string> The parameters.
     */
    final protected static function uriParameters(string $uri): array
    {
        $queryString = parse_url($uri, PHP_URL_QUERY);
        $params = [];

        foreach (explode("&", $queryString) as $param) {
            [$key, $value] = explode("=", $param, 2);
            $params[urldecode($key)] = urldecode($value);
        }

        return $params;
    }

    /**
     * Sign the given URI with the configured secret.
     *
     * The parameters will be appended to the URI, appropriately encoded. An expiry parameter will also be added with
     * the UNIX timestamp equivalent of the given expiry. The singature will then be appended using the URL parameter
     * `signature`. The parameters, if not empty, must not include a `signature` parameter. If the given parameters
     * include an `expires` parameter, it will be overwritten. The generated signature parameter will always be the last
     * parameter in the signed URI. In order to pass verification, the URI must be exactly as returned from sign() - the
     * order of the parameters must not change. The signed URI will only pass verify() until the expiry time.
     *
     * @param string $uri The URI to sign.
     * @param array<string,string> $parameters The parameters for the URI. Can be empty.
     * @param int|DateTimeInterface $expires The point in time at which the signed URI expires.
     *
     * @return string The signed URI.
     */
    public function sign(string $uri, array $parameters, $expires): string
    {
        assert($expires instanceof DateTimeInterface || is_int($expires), new TypeError("Argument for parameter #3 '\$expires' is not valied - expected DateTimeInterface or int."));
        $parameters["expires"] = ($expires instanceof DateTimeInterface ? $expires->getTimestamp() : $expires);
        $queryString = self::queryString($parameters);

        if (false === strpos($uri, "?")) {
            $uri = "{$uri}?{$queryString}";
        } else {
            $uri = "{$uri}&{$queryString}";
        }

        return "{$uri}&signature={$this->signature($uri)}";
    }

    /**
     * Verify the URI was signed with the configured secret and has not expired.
     *
     * @param string $signedUri The URI to verify.
     * @param int|DateTimeInterface|null $at The point in time at which to do the verification. Defaults to `null` for
     * the current time.
     *
     * @return bool `true` if the signature is verified, `false` if not.
     */
    public function verify(string $signedUri, $at = null): bool
    {
        if (!isset($at)) {
            $at = time();
        } else {
            assert($at instanceof DateTimeInterface || is_int($at), new TypeError("Argument for parameter #2 '\$at' is not valied - expected DateTimeInterface or int."));

            if ($at instanceof DateTimeInterface) {
                $at = $at->getTimestamp();
            }
        }

        // NOTE signature MUST always be last URI parameter, this is mandated in sign() above
        $signaturePos = strpos($signedUri, "signature=");

        if (false === $signaturePos) {
            return false;
        }

        $signature = substr($signedUri, $signaturePos + 10);
        // subtract 1 to adjust for the & or ? preceding the signature URI parameter
        $uri = substr($signedUri, 0, $signaturePos - 1);
        $params = self::uriParameters($uri);
        $params["expires"] = filter_var($params["expires"] ?? null, FILTER_VALIDATE_INT, ["flags" => FILTER_NULL_ON_FAILURE,]);

        return isset($params["expires"]) && $params["expires"] > $at && $this->signature($uri) === $signature;
    }
}
