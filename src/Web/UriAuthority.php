<?php

declare(strict_types=1);

namespace Bead\Web;

use Bead\Contracts\Web\UriAuthority as UriAuthorityContract;
use Bead\Contracts\Web\UriUserInfo as UriUserInfoContract;

/** Abstract representation of the Authority section of a URI. */
class UriAuthority implements UriAuthorityContract
{
    /** @var UriUserInfoContract|null The user info section of the URI authority, if set. */
    private ?UriUserInfoContract $m_userInfo;

    /** @var string The URI host. */
    private string $m_host;

    /** @var int|null The URI port, if set. */
    private ?int $m_port;

    /**
     * Initialise a new instance.
     *
     * @param string $host The URI host.
     * @param int|null $port The URI port, or null (the default) for no explicit port.
     * @param UriUserInfoContract|null $userInfo The URI user info, or null for no user info section.
     */
    public function __construct(string $host, ?int $port = null, ?UriUserInfoContract $userInfo = null)
    {
        $this->m_userInfo = $userInfo;
        $this->m_host = $host;
        $this->m_port = $port;
    }

    /**
     * Ensure the user info section is cloned (if set) when the authority is cloned, to avoid more than one UriAuthority
     * object sharing a UriUserInfo object.
     */
    public function __clone(): void
    {
        if (null !== $this->m_userInfo) {
            $this->m_userInfo = clone $this->m_userInfo;
        }
    }

    /** @inheritDoc */
    public function userInfo(): ?UriUserInfo
    {
        return $this->m_userInfo;
    }

    /**
     * Obtain a replica of the UriAuthority with a potentially different user info section.
     *
     * The immutability of the original object is preserved.
     *
     * @param UriUserInfoContract $userInfo The new user info.
     *
     * @return static A replica of the URI authority with a potentially different user info section.
     */
    public function withUserInfo(UriUserInfoContract $userInfo): static
    {
        $clone = clone $this;
        $clone->m_userInfo = $userInfo;
        return $clone;
    }

    /** Fetch the username, if the authority has one. */
    public function username(): ?string
    {
        return $this->m_userInfo?->username();
    }

    /** Fetch the password, if the authority has one. */
    public function password(): ?string
    {
        return $this->m_userInfo?->password();
    }

    /**
     * Obtain a replica of the UriAuthority without a user info section.
     *
     * The immutability of the original object is preserved.
     *
     * @return static A replica of the URI authority without a user info section.
     */
    public function withoutUserInfo(): static
    {
        $clone = clone $this;
        $clone->m_userInfo = null;
        return $clone;
    }

    /**
     * Obtain a replica of the UriAuthority with a potentially different username.
     *
     * The immutability of the original object is preserved.
     *
     * @param string $username The new username.
     *
     * @return static A replica of the URI authority with a potentially different username.
     */
    public function withUsername(string $username): static
    {
        $clone = clone $this;

        if (null === $clone->m_userInfo) {
            $clone->m_userInfo = new UriUserInfo($username);
        } else {
            $clone->m_userInfo = $clone->m_userInfo->withUsername($username);
        }

        return $clone;
    }

    /**
     * Obtain a replica of the UriAuthority with a potentially different username and password.
     *
     * The immutability of the original object is preserved.
     *
     * @param string $username The new username.
     * @param string|null $password The new password.
     *
     * @return static A replica of the URI authority with a potentially different username and password.
     */
    public function withUsernameAndPassword(string $username, ?string $password = null): static
    {
        $clone = clone $this;
        $clone->m_userInfo = new UriUserInfo($username, $password);
        return $clone;
    }

    /**
     * Obtain a replica of the UriAuthority with a potentially different password.
     *
     * If the authority doesn't yet have a user info part one will be created with an empty username.
     *
     * The immutability of the original object is preserved.
     *
     * @param string $password The new password.
     *
     * @return static A replica of the URI authority with a potentially different password.
     */
    public function withPassword(string $password): static
    {
        $clone = clone $this;

        if (null === $clone->m_userInfo) {
            $clone->m_userInfo = new UriUserInfo("", $password);
        } else {
            $clone->m_userInfo = $clone->m_userInfo->withPassword($password);
        }

        return $clone;
    }

    /**
     * Obtain a replica of the UriAuthority without a password.
     *
     * The immutability of the original object is preserved.
     *
     * @return static A replica of the URI authority without a password.
     */
    public function withoutPassword(): static
    {
        $clone = clone $this;

        if (null !== $clone->m_userInfo) {
            $clone->m_userInfo = $clone->m_userInfo->withoutPassword();
        }

        return $clone;
    }

    /** @inheritDoc */
    public function host(): string
    {
        return $this->m_host;
    }

    /**
     * Obtain a replica of the UriAuthority with a potentially different host.
     *
     * The immutability of the original object is preserved.
     *
     * @param string $host The new host.
     *
     * @return static A replica of the URI authority with a potentially different host.
     */
    public function withHost(string $host): static
    {
        $clone = clone $this;
        $clone->m_host = $host;
        return $clone;
    }

    /** @inheritDoc */
    public function port(): ?int
    {
        return $this->m_port;
    }

    /**
     * Obtain a replica of the UriAuthority with a potentially different port.
     *
     * The immutability of the original object is preserved.
     *
     * @param int $port The new port.
     *
     * @return static A replica of the URI authority with a potentially different port.
     */
    public function withPort(int $port): static
    {
        $clone = clone $this;
        $clone->m_port = $port;
        return $clone;
    }

    /**
     * Obtain a replica of the UriAuthority without a port.
     *
     * The immutability of the original object is preserved.
     *
     * @return static A replica of the URI authority without a port.
     */
    public function withoutPort(): static
    {
        $clone = clone $this;
        $clone->m_port = null;
        return $clone;
    }

    /** @inheritDoc */
    public function __toString(): string
    {
        $authority = "";

        if (null !== $this->userInfo()) {
            $authority = "{$this->userInfo()}@";
        }

        $authority .= $this->host();

        if (null !== $this->port()) {
            $authority .= ":{$this->port()}";
        }

        return $authority;
    }
}
