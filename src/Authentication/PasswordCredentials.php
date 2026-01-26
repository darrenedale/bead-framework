<?php

namespace Bead\Authentication;

use Bead\Contracts\Authentication\Credentials as CredentialsContract;

/** Classic username-password credentials. */
class PasswordCredentials implements CredentialsContract
{
    private string $username;

    private string $password;

    /** Initialise some credentials with a username and password. */
    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    /** Fetch the username from the credentials. */
    public function username(): string
    {
        return $this->username;
    }

    /**
     * Immutably set the username.
     *
     * @return self A clone of the credentials, with the provided username.
     */
    public function withUsername(string $username): CredentialsContract
    {
        $clone = clone $this;
        $clone->username = $username;
        return $clone;
    }

    /** Fetch the password from the credentials. */
    public function password(): string
    {
        return $this->password;
    }

    /**
     * Immutably set the password.
     *
     * @return self A clone of the credentials, with the provided password.
     */
    public function withPassword(string $password): CredentialsContract
    {
        $clone = clone $this;
        $clone->password = $password;
        return $clone;
    }
}
