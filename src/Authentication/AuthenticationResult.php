<?php

namespace Bead\Authentication;

use Bead\Contracts\Models\Authenticatable as AuthenticatableContract;
use LogicException;

use function Bead\Helpers\Iterable\all;

/** Encapsulates the result of an authentication attempt that doesn't fail. */
class AuthenticationResult
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
    public function __construct(AuthenticationResultCode $code, ?AuthenticatableContract $authenticatable = null, array $additionalFactors = [])
    {
        assert(all($additionalFactors, "is_string"), new LogicException("Expected an array of strings identifying supported additional authentication factors"));
        assert((AuthenticationResultCode::Authenticated === $code && null !== $authenticatable) || (AuthenticationResultCode::AdditionalFactorRequired === $code && null === $authenticatable), new LogicException("Expected valid combination of result code and authenticatable, found {$code->name} and " . (null === $authenticatable ? "no " : "an") . " authenticatable"));
        assert(!(AuthenticationResultCode::AdditionalFactorRequired === $code && 0 === count($additionalFactors)), new LogicException("Expected one or more additional supported factors for code = {$code->name}"));
        $this->code = $code;
        $this->authenticatable = $authenticatable;
        $this->additionalFactors = $additionalFactors;
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
