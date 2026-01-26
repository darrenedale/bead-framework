<?php

namespace Bead\Authentication;

use Bead\Contracts\Authentication\Credentials as CredentialsContract;

/** Username/password credentials with optional second factor. */
class MultiFactorPasswordCredentials extends PasswordCredentials
{
    /** Identifier for the type of MFA (e.g. TOTP). */
    private ?string $secondFactorMethod;

    /** The MFA password (e.g. the 6-digit TOTP code). */
    private ?string $secondFactorPassword;

    /**
     * Initialise some credentials with a username, password, optional second-factor method and optional second-
     * factor password.
     */
    public function __construct(string $username, string $password, ?string $secondFactorMethod, ?string $secondFactorPassword)
    {
        parent::__construct($username, $password);
        $this->secondFactorMethod = $secondFactorMethod;
        $this->secondFactorPassword = $secondFactorPassword;
    }

    /** Check whether MFA credentials are present. */
    public function hasMultiFactorCredentials(): bool
    {
        return null !== $this->secondFactorMethod() && null !== $this->secondFactorPassword();
    }

    /** Get the second factor method, if set. */
    public function secondFactorMethod(): ?string
    {
        return $this->secondFactorMethod;
    }

    /**
     * Immutably set the second factor method.
     *
     * @return self A clone of the credentials, with the provided second-factor method.
     */
    public function withSecondFactorMethod(string $secondFactorMethod): CredentialsContract
    {
        $clone = clone $this;
        $clone->secondFactorMethod = $secondFactorMethod;
        return $clone;
    }

    /** Fetch the second-factor password, if set. */
    public function secondFactorPassword(): ?string
    {
        return $this->secondFactorPassword;
    }

    /**
     * Immutably set the second factor password.
     *
     * @return self A clone of the credentials, with the provided second-factor password.
     */
    public function withSecondFactorPassword(string $secondFactorPassword): CredentialsContract
    {
        $clone = clone $this;
        $clone->secondFactorPassword = $secondFactorPassword;
        return $clone;
    }
}
