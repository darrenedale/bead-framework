<?php

declare(strict_types=1);

namespace Bead\Web;

use Bead\Contracts\Web\UriUserInfo as UriUserInfoContract;

use function Bead\Helpers\Str\scrub;

/** Abstract representation of the user info section of a URI. */
class UriUserInfo implements UriUserInfoContract
{
    /** @var string The user info's username. */
    private string $m_username;

    /** @var string|null The user info's password, if set. */
    private ?string $m_password;

    /**
     * Initialise a new UriUserInfo with a username and optional password.
     *
     * @param string $username The username.
     * @param string|null $password The password, or null for no password.
     */
    public function __construct(string $username, ?string $password = null)
    {
        $this->m_username = $username;
        $this->m_password = $password;
    }

    /** The destructor securely erases the password string before the object is deallocated. */
    public function __destruct()
    {
        if (null !== $this->m_password) {
            scrub($this->m_password);
        }
    }

    /** @inheritDoc */
    public function username(): string
    {
        return $this->m_username;
    }

    /**
     * Obtain a replica of the UriUserInfo with a potentially different username.
     *
     * The immutability of the original object is preserved.
     *
     * @param string $username The new username.
     *
     * @return static A replica of the URI user info with a potentially different username.
     */
    public function withUsername(string $username): static
    {
        $clone = clone $this;
        $clone->m_username = $username;
        return $clone;
    }

    /** @inheritDoc */
    public function password(): ?string
    {
        return $this->m_password;
    }

    /**
     * Obtain a replica of the UriUserInfo with a potentially different password.
     *
     * The immutability of the original object is preserved.
     *
     * @param string $password The new password.
     *
     * @return static A replica of the URI user info with a potentially different password.
     */
    public function withPassword(string $password): static
    {
        $clone = clone $this;
        $clone->m_password = $password;
        return $clone;
    }

    /**
     * Obtain a replica of the UriUserInfo without a password.
     *
     * The immutability of the original object is preserved.
     *
     * @return static A replica of the URI user info without a password.
     */
    public function withoutPassword(): static
    {
        $clone = clone $this;
        $clone->m_password = null;
        return $clone;
    }

    /** @inheritDoc */
    public function __toString(): string
    {
        $ui = rawurlencode($this->username());

        if (null !== $this->password()) {
            $ui .= ":" . rawurlencode($this->password());
        }

        return $ui;
    }
}
