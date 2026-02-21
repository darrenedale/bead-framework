<?php

namespace Bead\Authentication;

use Bead\Contracts\Models\Authenticatable as AuthenticatableContract;
use LogicException;

use function Bead\Helpers\Iterable\all;

/** Encapsulates the result of an authentication attempt that doesn't fail. */
final class AuthenticationResult
{
    /** @var AuthenticationResultCode The outcome of the authentication attempt. */
    private AuthenticationResultCode $code;

    /** @var AuthenticatableContract|null The authenticated entity, if there is one. */
    private ?AuthenticatableContract $authenticatable;

    /** @var string[] The supported additional factors, if MFA is requied. */
    private array $additionalFactors;

    /**
     * Initialise a result.
     *
     * @param string[] $additionalFactors
     */
    private function __construct(AuthenticationResultCode $code, ?AuthenticatableContract $authenticatable, array $additionalFactors)
    {
        $this->code = $code;
        $this->authenticatable = $authenticatable;
        $this->additionalFactors = $additionalFactors;
    }

    /** Factory method to create a success authentication result. */
    public static function authenticated(AuthenticatableContract $authenticatable): self
    {
        return new self(AuthenticationResultCode::Authenticated, $authenticatable, []);
    }

    /** Factory method to create a multifactor-required authentication result. */
    public static function multiFactorRequired(array $additionalFactors): self
    {
        assert(0 < count($additionalFactors), new LogicException("Expected non-empty array of strings identifying supported additional authentication factors"));
        assert(all($additionalFactors, "is_string"), new LogicException("Expected an array of strings identifying supported additional authentication factors"));
        return new self(AuthenticationResultCode::AdditionalFactorRequired, null, $additionalFactors);
    }

    /** Conveniently check whether the result was successful authentication. */
    public function isSuccess(): bool
    {
        return $this->code() === AuthenticationResultCode::Authenticated;
    }

    /** Conveniently check whether the result was "MFA required". */
    public function additionalFactorRequired(): bool
    {
        return $this->code() === AuthenticationResultCode::AdditionalFactorRequired;
    }

    /** Fetch the result code. */
    public function code(): AuthenticationResultCode
    {
        return $this->code;
    }

    /** Fetch the Authenticatable, if there is one. */
    public function authenticatable(): ?AuthenticatableContract
    {
        return $this->authenticatable;
    }

    /**
     * Fetch the supported additional factors, if there are any.
     *
     * @return string[] The supported additional factors.
     */
    public function supportedAdditionalFactors(): array
    {
        return $this->additionalFactors;
    }
}
